<?php

namespace Tests\Integration\Infrastructure\Persistence\MongoDB\Repositories;

use App\Domain\Entities\Ticket;
use App\Domain\Events\TicketCreated;
use App\Domain\Events\TicketResolved;
use App\Domain\Exceptions\AggregateNotFoundException;
use App\Domain\Interfaces\Repositories\TicketEventStoreInterface;
use App\Domain\ValueObjects\Priority;
use App\Domain\ValueObjects\Status;
use App\Infrastructure\Persistence\Exceptions\EventPersistenceFailedException;
use App\Infrastructure\Persistence\MongoDB\MongoConnection;
use App\Infrastructure\Persistence\MongoDB\Repositories\MongoEventStore;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;
use MongoDB\Driver\Exception\BulkWriteException;
use Mockery;
use Tests\TestCase;

class MongoEventStoreIntegrationTest extends TestCase
{
    private const DATE_FORMAT = 'Y-m-d\TH:i:s.v';
    use DatabaseMigrations; // Garante DB limpo e migrations rodadas a cada teste

    private TicketEventStoreInterface $eventStore;
    private string $collectionName = 'ticket_events'; // Nome da coleção
    private MongoConnection $mongoConnection;
    private Collection $collection;

    protected function setUp(): void
    {
        parent::setUp();
        // Resolve a implementação real do container do Laravel
        $this->eventStore = $this->app->make(TicketEventStoreInterface::class);

        $this->mongoConnection = $this->app->make(MongoConnection::class);
        $this->collection = $this->mongoConnection->getDatabase()
            ->selectCollection($this->collectionName);

        $this->assertInstanceOf(MongoEventStore::class, $this->eventStore);
    }

    /**
     * Helper para buscar eventos diretamente no DB para asserções.
     */
    private function findEventsInDb(string $aggregateId): array
    {
        $cursor = $this->collection->find(
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
        $this->assertArrayHasKey('event_id', $dbEventData);
        $this->assertIsString($dbEventData['event_id']);
        $this->assertInstanceOf(UTCDateTime::class, $dbEventData['occurred_on']);
        // Compara timestamps convertendo BSON para DateTimeImmutable
        $this->assertEquals(
            $creationTime->format(self::DATE_FORMAT),
            $dbEventData['occurred_on']->toDateTimeImmutable()
                ->setTimezone(new \DateTimeZone(date_default_timezone_get()))
                ->format(self::DATE_FORMAT)
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
            $creationTime->format(self::DATE_FORMAT),
            $loadedTicket->getCreatedAt()->format(self::DATE_FORMAT)
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
        $this->assertArrayHasKey('event_id', $dbEvent1Data);
        $this->assertIsString($dbEvent1Data['event_id']);
        $this->assertEquals(
            $creationTime->format(self::DATE_FORMAT),
            $dbEvent1Data['occurred_on']->toDateTimeImmutable()
                ->setTimezone(new \DateTimeZone(date_default_timezone_get()))
                ->format(self::DATE_FORMAT)
        );

        // Verifica evento 2 (Resolved)
        $dbEvent2Data = (array) $dbEvents[1];
        $this->assertSame(2, $dbEvent2Data['sequence_number']);
        $this->assertSame(TicketResolved::class, $dbEvent2Data['event_type']);
        $this->assertArrayHasKey('event_id', $dbEvent2Data);
        $this->assertIsString($dbEvent2Data['event_id']);
        $this->assertEquals(
            $resolveTime->format(self::DATE_FORMAT),
            $dbEvent2Data['occurred_on']->toDateTimeImmutable()
                ->setTimezone(new \DateTimeZone(date_default_timezone_get()))
                ->format(self::DATE_FORMAT)
        );
        $this->assertSame('[]', $dbEvent2Data['payload']); // Payload vazio para TicketResolved

        // Act: Load
        $loadedTicket = $this->eventStore->load($ticketId);

        // Assert: Load (estado final)
        $this->assertInstanceOf(Ticket::class, $loadedTicket);
        $this->assertSame($ticketId, $loadedTicket->getId());
        $this->assertTrue($loadedTicket->getStatus()->equals(new Status(Status::RESOLVED)));
        $this->assertEquals(
            $creationTime->format(self::DATE_FORMAT),
            $loadedTicket->getCreatedAt()->format(self::DATE_FORMAT)
        );
        $this->assertEquals(
            $resolveTime->format(self::DATE_FORMAT),
            $loadedTicket->getResolvedAt()->format(self::DATE_FORMAT)
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
        $this->assertArrayHasKey('event_id', ((array)$dbEvents[1]));
        $this->assertIsString(((array)$dbEvents[1])['event_id']);
        $this->assertSame(TicketResolved::class, ((array)$dbEvents[1])['event_type']);
        $this->assertEquals(
            $resolveTime->format(self::DATE_FORMAT),
            ((array)$dbEvents[1])['occurred_on']->toDateTimeImmutable()
                ->setTimezone(new \DateTimeZone(date_default_timezone_get()))
                ->format(self::DATE_FORMAT)
        );

        // Act: Load again
        $reloadedTicket = $this->eventStore->load($ticketId);

        // Assert: Load (estado final)
        $this->assertTrue($reloadedTicket->getStatus()->equals(new Status(Status::RESOLVED)));
        $this->assertEquals(
            $creationTime->format(self::DATE_FORMAT),
            $reloadedTicket->getCreatedAt()->format(self::DATE_FORMAT)
        );
        $this->assertEquals(
            $resolveTime->format(self::DATE_FORMAT),
            $reloadedTicket->getResolvedAt()->format(self::DATE_FORMAT)
        );
    }

    /** @test */
    public function load_throws_exception_if_aggregate_not_found(): void
    {
        // Assert
        $this->expectException(AggregateNotFoundException::class);
        $this->expectExceptionMessage("Ticket com ID non-existent-id não encontrado.");
        $this->expectExceptionCode(0);

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

    /** @test */
    public function save_aborts_transaction_on_insert_failure_and_throws_exception(): void
    {
        // Arrange
        $aggregateId = 'tx-abort-test-1';

        // Cria um ticket com um evento (TicketCreated - sequence 1)
        $ticket = Ticket::create($aggregateId, 'Transaction Test', 'Desc', 'low');
        $this->eventStore->save($ticket); // Salva o estado inicial
        $this->assertEquals(1, $this->collection->countDocuments(['aggregate_id' => $aggregateId]));

        // Arrange
        $loadedTicket = $this->eventStore->load($aggregateId);
        $loadedTicket->resolve(); // Agora TicketResolved está pronto para ser salvo (sequence 2)

        // Arrange
        // Usamos o mock do Laravel para substituir a instância da Collection no container
        // APENAS para este teste.
        $mockCollection = $this->mock(Collection::class);
        /** @var \Mockery\MockInterface|Collection $mockCollection */
        $mockCollection->shouldReceive('insertMany')
            ->once()
            ->andThrow(new BulkWriteException('Simulated insertMany failure'));

        // Adiciona a expectativa para findOne, que é chamado por getLastSequenceNumber
        $mockCollection->shouldReceive('findOne')
            ->once()
            ->with(['aggregate_id' => $aggregateId], \Mockery::subset(['sort' => ['sequence_number' => -1]]))
            ->andReturn(['sequence_number' => 1]);

        // Obtém a conexão real do container
        $realConnection = $this->app->make(MongoConnection::class);

        // Instancia o MongoEventStore manualmente, injetando a conexão REAL
        // e a Collection MOCKADA. Isso permite que a gestão da transação (getClient, startSession)
        // use a conexão real, mas as operações na coleção (findOne, insertMany) usem o mock.
        $eventStoreWithMock = new MongoEventStore($realConnection, $mockCollection);

        // Assert: Espera a exceção específica do Event Store
        $this->expectException(EventPersistenceFailedException::class);
        $this->expectExceptionMessage("Falha ao salvar eventos para o agregado tx-abort-test-1.");
        $this->expectExceptionCode(0);

        // Act: Tenta salvar o ticket com o novo evento (Resolved)
        // A operação insertMany dentro do save() deve falhar devido ao mock
        $eventStoreWithMock->save($loadedTicket);

        // Assert extra
        // Verifica se o evento TicketResolved (sequence 2) NÃO foi persistido no DB real
        $finalCount = $this->mongoConnection->getDatabase()
            ->selectCollection($this->collectionName)
            ->countDocuments(['aggregate_id' => $aggregateId]);
        $this->assertEquals(1, $finalCount, "A transação não foi abortada corretamente. O evento TicketResolved foi salvo indevidamente no DB real.");
    }
}
