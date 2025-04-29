<?php

namespace Tests\Integration\Infrastructure\Persistence\MongoDB\Projections;

use App\Application\Events\DomainEventsPersisted;
use App\Domain\Events\TicketCreated;
use App\Domain\Events\TicketResolved;
use App\Domain\ValueObjects\Priority;
use App\Domain\ValueObjects\Status;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use MongoDB\BSON\UTCDateTime;
use Tests\TestCase;
use DateTimeImmutable;
use Throwable;

class UpdateTicketsReadModelProjectionIntegrationTest extends TestCase
{
    const ERRORMESSAGE = "O listener UpdateTicketsReadModelProjection lançou uma exceção inesperada: ";
    use DatabaseMigrations; // Essencial para limpar e migrar 'tickets_test'

    private Dispatcher $dispatcher;
    private string $collectionName = 'ticket_read_models';

    protected function setUp(): void
    {
        parent::setUp();
        $this->dispatcher = $this->app->make(Dispatcher::class);
    }

    /**
     * Helper para buscar um documento diretamente no DB.
     */
    private function findReadModelInDb(string $ticketId): ?array
    {
        $document = DB::connection('mongodb')
            ->collection($this->collectionName)
            ->where('ticket_id', $ticketId)
            ->first();

        return $document ? (array) $document : null;
    }

    /** @test */
    public function it_creates_read_model_when_ticket_created_event_is_dispatched(): void
    {
        // Arrange
        $aggregateId = 'projection-create-1';
        $createdAt = new DateTimeImmutable('2024-03-10T10:00:00Z');
        $event = new TicketCreated(
            $aggregateId,
            'Projection Create Title',
            'Desc projection create',
            Priority::HIGH,
            $createdAt
        );
        $appEvent = new DomainEventsPersisted([$event], $aggregateId, 'Ticket');

        // Act
        try {
            $this->dispatcher->dispatch($appEvent); // Listener será executado via sync queue
        } catch (Throwable $e) {
            $this->fail(self::ERRORMESSAGE . $e->getMessage());
        }

        // Assert: Verifica o documento no banco de dados 'tickets_test'
        $dbData = $this->findReadModelInDb($aggregateId);

        $this->assertNotNull($dbData, "Documento não encontrado no read model.");
        $this->assertSame($aggregateId, $dbData['ticket_id']);
        $this->assertSame('Projection Create Title', $dbData['title']);
        $this->assertSame('Desc projection create', $dbData['description']);
        $this->assertSame('high', $dbData['priority']); // Verifica a string
        $this->assertSame(Status::OPEN, $dbData['status']);
        $this->assertInstanceOf(UTCDateTime::class, $dbData['created_at']);
        $this->assertEquals(
            $createdAt,
            $dbData['created_at']->toDateTimeImmutable()->setTimezone(new \DateTimeZone(date_default_timezone_get()))
        );
        $this->assertNull($dbData['resolved_at']);
        $this->assertInstanceOf(UTCDateTime::class, $dbData['last_updated_at']);
    }

    /** @test */
    public function it_updates_read_model_when_ticket_resolved_event_is_dispatched(): void
    {
        // Arrange: Primeiro, cria o estado inicial disparando TicketCreated
        $aggregateId = 'projection-resolve-1';
        $createdAt = new DateTimeImmutable('2024-03-10T11:00:00Z');
        $createdEvent = new TicketCreated($aggregateId, 'To Be Resolved', 'Desc resolve', Priority::LOW, $createdAt);
        $createdAppEvent = new DomainEventsPersisted([$createdEvent], $aggregateId, 'Ticket');
        $this->dispatcher->dispatch($createdAppEvent); // Cria o documento inicial

        // Arrange: Prepara o evento TicketResolved
        sleep(1); // Garante timestamp diferente
        $resolvedAt = new DateTimeImmutable();
        $resolvedEvent = new TicketResolved($aggregateId, $resolvedAt);
        $resolvedAppEvent = new DomainEventsPersisted([$resolvedEvent], $aggregateId, 'Ticket');

        // Act
        try {
            $this->dispatcher->dispatch($resolvedAppEvent); // Dispara o evento de resolução
        } catch (Throwable $e) {
            $this->fail(self::ERRORMESSAGE . $e->getMessage());
        }

        // Assert: Verifica o documento atualizado no banco
        $dbData = $this->findReadModelInDb($aggregateId);

        $this->assertNotNull($dbData);
        $this->assertSame($aggregateId, $dbData['ticket_id']);
        $this->assertSame('To Be Resolved', $dbData['title']); // Título deve permanecer
        $this->assertSame(Status::RESOLVED, $dbData['status']); // Status atualizado
        $this->assertInstanceOf(UTCDateTime::class, $dbData['resolved_at']);
        $this->assertEquals(
            $resolvedAt,
            $dbData['resolved_at']->toDateTimeImmutable()->setTimezone(new \DateTimeZone(date_default_timezone_get()))
        );
        // Verifica se created_at não mudou
        $this->assertEquals(
            $createdAt,
            $dbData['created_at']->toDateTimeImmutable()->setTimezone(new \DateTimeZone(date_default_timezone_get()))
        );
    }

    /** @test */
    public function it_handles_multiple_events_in_one_dispatch_correctly(): void
    {
        // Arrange
        $aggregateId = 'projection-multi-1';
        $createdAt = new DateTimeImmutable('2024-03-10T12:00:00Z');
        $resolvedAt = $createdAt->modify('+1 hour');
        $createdEvent = new TicketCreated($aggregateId, 'Multi Event Proj', 'Desc multi', Priority::MEDIUM, $createdAt);
        $resolvedEvent = new TicketResolved($aggregateId, $resolvedAt);
        // Evento com ambos os eventos
        $appEvent = new DomainEventsPersisted([$createdEvent, $resolvedEvent], $aggregateId, 'Ticket');

        // Act
        try {
            $this->dispatcher->dispatch($appEvent);
        } catch (Throwable $e) {
            $this->fail(self::ERRORMESSAGE . $e->getMessage());
        }

        // Assert: Verifica o estado final no banco
        $dbData = $this->findReadModelInDb($aggregateId);

        $this->assertNotNull($dbData);
        $this->assertSame($aggregateId, $dbData['ticket_id']);
        $this->assertSame('Multi Event Proj', $dbData['title']);
        $this->assertSame('medium', $dbData['priority']);
        $this->assertSame(Status::RESOLVED, $dbData['status']); // Estado final deve ser resolvido
        $this->assertEquals(
            $createdAt,
            $dbData['created_at']->toDateTimeImmutable()->setTimezone(new \DateTimeZone(date_default_timezone_get()))
        );
        $this->assertEquals(
            $resolvedAt,
            $dbData['resolved_at']->toDateTimeImmutable()->setTimezone(new \DateTimeZone(date_default_timezone_get()))
        );
    }

    /** @test */
    public function it_ignores_events_for_other_aggregate_types_in_projection(): void
    {
        // Arrange
        $aggregateId = 'projection-ignore-1';
        $event = new TicketCreated($aggregateId, 'Ignore Title', 'Ignore Desc', Priority::LOW);
        $appEvent = new DomainEventsPersisted([$event], $aggregateId, 'NonTicketAggregate'); // Tipo diferente

        // Act
        try {
            $this->dispatcher->dispatch($appEvent);
        } catch (Throwable $e) {
            $this->fail(self::ERRORMESSAGE . $e->getMessage());
        }

        // Assert: Verifica que NENHUM documento foi criado no banco
        $dbData = $this->findReadModelInDb($aggregateId);
        $this->assertNull($dbData, "Documento foi criado indevidamente para tipo de agregado diferente.");
    }
}
