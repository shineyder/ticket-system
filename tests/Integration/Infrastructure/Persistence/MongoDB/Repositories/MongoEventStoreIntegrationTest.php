<?php

namespace Tests\Integration\Infrastructure\Persistence\MongoDB\Repositories;

use App\Domain\Entities\Ticket;
use App\Domain\Events\TicketCreated;
use App\Domain\Events\TicketResolved;
use App\Domain\Exceptions\AggregateNotFoundException;
use App\Domain\Interfaces\Repositories\TicketEventStoreInterface;
use App\Domain\ValueObjects\Priority;
use App\Domain\ValueObjects\Status;
use App\Infrastructure\Persistence\MongoDB\MongoConnection;
use App\Infrastructure\Persistence\MongoDB\Repositories\MongoEventStore;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use MongoDB\BSON\UTCDateTime;
use Tests\TestCase;

class MongoEventStoreIntegrationTest extends TestCase
{
    private const DATEFORMAT = 'Y-m-d\TH:i:s.v';
    use DatabaseMigrations; // Garante DB limpo e migrations rodadas a cada teste

    private TicketEventStoreInterface $eventStore;
    private string $collectionName = 'ticket_events'; // Nome da coleção
    private MongoConnection $mongoConnection;

    protected function setUp(): void
    {
        parent::setUp();
        // Resolve a implementação real do container do Laravel
        $this->eventStore = $this->app->make(TicketEventStoreInterface::class);

        $this->mongoConnection = $this->app->make(MongoConnection::class);

        $this->assertInstanceOf(MongoEventStore::class, $this->eventStore);
    }

    /**
     * Helper para buscar eventos diretamente no DB para asserções.
     */
    private function findEventsInDb(string $aggregateId): array
    {
        $cursor = $this->mongoConnection->getDatabase()
            ->selectCollection($this->collectionName)
            ->find(
                ['aggregate_id' => $aggregateId],
                ['sort' => ['sequence_number' => 1]]
            );
        return $cursor->toArray();
    }

    /** @test */
    public function it_can_save_and_load_a_ticket_aggregate_with_single_event(): void
    {
        // Arrange
        $ticketId = 'integration-save-load-1';
        $title = 'Integration Save Load Single';
        $description = 'Desc single event';
        $priorityString = 'medium';
        $priorityInt = Priority::MEDIUM;

        $ticket = Ticket::create($ticketId, $title, $description, $priorityString);
        $creationTime = $ticket->getCreatedAt(); // Captura o tempo exato da criação

        // Act: Save
        $savedEvents = $this->eventStore->save($ticket);

        // Assert: Save - Verifica o retorno e o estado do DB
        $this->assertCount(1, $savedEvents);
        $this->assertInstanceOf(TicketCreated::class, $savedEvents[0]);

        $dbEvents = $this->findEventsInDb($ticketId);
        $this->assertCount(1, $dbEvents);
        $dbEventData = (array) $dbEvents[0];

        $this->assertSame($ticketId, $dbEventData['aggregate_id']);
        $this->assertSame(TicketCreated::class, $dbEventData['event_type']);
        $this->assertSame(1, $dbEventData['sequence_number']);
        $this->assertInstanceOf(UTCDateTime::class, $dbEventData['occurred_on']);
        // Compara timestamps convertendo BSON para DateTimeImmutable
        $this->assertEquals(
            $creationTime->format(self::DATEFORMAT),
            $dbEventData['occurred_on']->toDateTimeImmutable()
                ->setTimezone(new \DateTimeZone(date_default_timezone_get()))
                ->format(self::DATEFORMAT)
        );

        $payload = json_decode($dbEventData['payload'], true);
        $this->assertSame($title, $payload['title']);
        $this->assertSame($description, $payload['description']);
        $this->assertSame($priorityInt, $payload['priority']); // Verifica se o INT foi salvo

        // Act: Load
        $loadedTicket = $this->eventStore->load($ticketId);

        // Assert: Load - Verifica o estado reconstituído
        $this->assertInstanceOf(Ticket::class, $loadedTicket);
        $this->assertSame($ticketId, $loadedTicket->getId());
        $this->assertSame($title, $loadedTicket->getTitle());
        $this->assertSame($description, $loadedTicket->getDescription());
        $this->assertTrue($loadedTicket->getPriority()->equals(new Priority($priorityInt)));
        $this->assertTrue($loadedTicket->getStatus()->equals(new Status(Status::OPEN)));
        $this->assertEquals(
            $creationTime->format(self::DATEFORMAT),
            $loadedTicket->getCreatedAt()->format(self::DATEFORMAT)
        );
        $this->assertNull($loadedTicket->getResolvedAt());
        $this->assertEmpty($loadedTicket->pullUncommittedEvents()); // Não deve ter eventos após carregar
    }

    /** @test */
    public function it_can_save_and_load_a_ticket_aggregate_with_multiple_events(): void
    {
        // Arrange
        $ticketId = 'integration-save-load-multi';
        $ticket = Ticket::create($ticketId, 'Multi Event', 'Desc multi', 'low');
        $creationTime = $ticket->getCreatedAt();
        // Força um tempo diferente para o resolve
        sleep(1); // Garante um timestamp diferente
        $ticket->resolve();
        $resolveTime = $ticket->getResolvedAt();

        // Act: Save (ambos os eventos)
        $savedEvents = $this->eventStore->save($ticket);

        // Assert: Save
        $this->assertCount(2, $savedEvents);
        $this->assertInstanceOf(TicketCreated::class, $savedEvents[0]);
        $this->assertInstanceOf(TicketResolved::class, $savedEvents[1]);

        $dbEvents = $this->findEventsInDb($ticketId);
        $this->assertCount(2, $dbEvents);

        // Verifica evento 1 (Created)
        $dbEvent1Data = (array) $dbEvents[0];
        $this->assertSame(1, $dbEvent1Data['sequence_number']);
        $this->assertSame(TicketCreated::class, $dbEvent1Data['event_type']);
        $this->assertEquals(
            $creationTime->format(self::DATEFORMAT),
            $dbEvent1Data['occurred_on']->toDateTimeImmutable()
                ->setTimezone(new \DateTimeZone(date_default_timezone_get()))
                ->format(self::DATEFORMAT)
        );

        // Verifica evento 2 (Resolved)
        $dbEvent2Data = (array) $dbEvents[1];
        $this->assertSame(2, $dbEvent2Data['sequence_number']);
        $this->assertSame(TicketResolved::class, $dbEvent2Data['event_type']);
        $this->assertEquals(
            $resolveTime->format(self::DATEFORMAT),
            $dbEvent2Data['occurred_on']->toDateTimeImmutable()
                ->setTimezone(new \DateTimeZone(date_default_timezone_get()))
                ->format(self::DATEFORMAT)
        );
        $this->assertSame('[]', $dbEvent2Data['payload']); // Payload vazio para TicketResolved

        // Act: Load
        $loadedTicket = $this->eventStore->load($ticketId);

        // Assert: Load (estado final)
        $this->assertInstanceOf(Ticket::class, $loadedTicket);
        $this->assertSame($ticketId, $loadedTicket->getId());
        $this->assertTrue($loadedTicket->getStatus()->equals(new Status(Status::RESOLVED)));
        $this->assertEquals(
            $creationTime->format(self::DATEFORMAT),
            $loadedTicket->getCreatedAt()->format(self::DATEFORMAT)
        );
        $this->assertEquals(
            $resolveTime->format(self::DATEFORMAT),
            $loadedTicket->getResolvedAt()->format(self::DATEFORMAT)
        );
        $this->assertEmpty($loadedTicket->pullUncommittedEvents());
    }

    /** @test */
    public function it_correctly_appends_events_to_existing_aggregate(): void
    {
        // Arrange: Save initial event
        $ticketId = 'integration-append';
        $ticket = Ticket::create($ticketId, 'Append Test', 'Desc append', 'high');
        $creationTime = $ticket->getCreatedAt();
        $this->eventStore->save($ticket); // Salva TicketCreated (seq 1)

        // Arrange: Load and apply new event
        $loadedTicket = $this->eventStore->load($ticketId);
        sleep(1); // Garante timestamp diferente
        $loadedTicket->resolve();
        $resolveTime = $loadedTicket->getResolvedAt();

        // Act: Save the appended event
        $savedEvents = $this->eventStore->save($loadedTicket); // Salva TicketResolved (seq 2)

        // Assert: Save (apenas o novo evento é retornado)
        $this->assertCount(1, $savedEvents);
        $this->assertInstanceOf(TicketResolved::class, $savedEvents[0]);

        // Assert: Database state (verifica ambos os eventos com sequências corretas)
        $dbEvents = $this->findEventsInDb($ticketId);
        $this->assertCount(2, $dbEvents);
        $this->assertSame(1, ((array)$dbEvents[0])['sequence_number']);
        $this->assertSame(TicketCreated::class, ((array)$dbEvents[0])['event_type']);
        $this->assertSame(2, ((array)$dbEvents[1])['sequence_number']);
        $this->assertSame(TicketResolved::class, ((array)$dbEvents[1])['event_type']);
        $this->assertEquals(
            $resolveTime->format(self::DATEFORMAT),
            ((array)$dbEvents[1])['occurred_on']->toDateTimeImmutable()
                ->setTimezone(new \DateTimeZone(date_default_timezone_get()))
                ->format(self::DATEFORMAT)
        );

        // Act: Load again
        $reloadedTicket = $this->eventStore->load($ticketId);

        // Assert: Load (estado final)
        $this->assertTrue($reloadedTicket->getStatus()->equals(new Status(Status::RESOLVED)));
        $this->assertEquals(
            $creationTime->format(self::DATEFORMAT),
            $reloadedTicket->getCreatedAt()->format(self::DATEFORMAT)
        );
        $this->assertEquals(
            $resolveTime->format(self::DATEFORMAT),
            $reloadedTicket->getResolvedAt()->format(self::DATEFORMAT)
        );
    }

    /** @test */
    public function load_throws_exception_if_aggregate_not_found(): void
    {
        // Assert
        $this->expectException(AggregateNotFoundException::class);
        $this->expectExceptionMessage("Ticket com ID non-existent-id não encontrado.");

        // Act
        $this->eventStore->load('non-existent-id');
    }

    /** @test */
    public function save_does_nothing_if_no_uncommitted_events(): void
    {
        // Arrange
        $ticketId = 'integration-no-events';
        $ticket = Ticket::create($ticketId, 'No Events', 'Desc no events', 'low');
        $this->eventStore->save($ticket); // Salva o evento inicial

        $loadedTicket = $this->eventStore->load($ticketId); // Carrega, sem eventos novos

        // Act
        $savedEvents = $this->eventStore->save($loadedTicket); // Tenta salvar sem novos eventos

        // Assert
        $this->assertEmpty($savedEvents); // Nenhum evento deve ser retornado
        $dbEvents = $this->findEventsInDb($ticketId);
        $this->assertCount(1, $dbEvents); // Apenas o evento original deve estar no DB
    }
}
