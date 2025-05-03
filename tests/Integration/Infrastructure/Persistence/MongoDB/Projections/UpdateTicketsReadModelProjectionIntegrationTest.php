<?php

namespace Tests\Integration\Infrastructure\Persistence\MongoDB\Projections;

use App\Application\Events\DomainEventsPersisted;
use App\Domain\Events\TicketCreated;
use App\Domain\Events\TicketResolved;
use App\Infrastructure\Persistence\Cache\CachingTicketReadRepository;
use App\Domain\ValueObjects\Priority;
use App\Domain\ValueObjects\Status;
use App\Infrastructure\Persistence\MongoDB\MongoConnection;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;
use DateTimeImmutable;
use Throwable;
use MongoDB\BSON\UTCDateTime;
use Illuminate\Support\Facades\Cache;

class UpdateTicketsReadModelProjectionIntegrationTest extends TestCase
{
    private const DATE_FORMAT = 'Y-m-d\TH:i:s.v';
    private const ERROR_MESSAGE = "O listener UpdateTicketsReadModelProjection lançou uma exceção inesperada: ";
    private const CACHE_ERROR_MESSAGE = "Cache não foi setado corretamente antes do teste.";
    private const IDEMPOTENCY_CACHE_PREFIX = 'processed_event:';
    private const CACHE_TEST_KEY = 'integration-test-cache-key';
    private const CACHE_TEST_TAG = CachingTicketReadRepository::CACHE_TAG; // Usar a mesma tag da projeção

    use DatabaseMigrations; // Essencial para limpar e migrar 'tickets_test'

    private Dispatcher $dispatcher;
    private string $collectionName = 'ticket_read_models';
    private MongoConnection $mongoConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['cache']->store('redis')->flush();

        $this->mongoConnection = $this->app->make(MongoConnection::class);
        $this->dispatcher = $this->app->make(Dispatcher::class);
    }

    /**
     * Helper para buscar um documento diretamente no DB.
     */
    private function findReadModelInDb(string $ticketId): ?array
    {
        $document = $this->mongoConnection->getDatabase()
            ->selectCollection($this->collectionName)
            ->findOne(['ticket_id' => $ticketId]);

        return $document ? (array) $document : null;
    }

    /** @test */
    public function it_creates_read_model_when_ticket_created_event_is_dispatched(): void
    {
        // Arrange
        $aggregateId = 'projection-create-1';
        $eventId = 'event-proj-int-create-1';
        $createdAt = new DateTimeImmutable('2024-03-10T10:00:00Z');
        $event = new TicketCreated(
            $aggregateId,
            'Projection Create Title',
            'Desc projection create',
            Priority::HIGH,
            $createdAt,
            $eventId
        );
        $appEvent = new DomainEventsPersisted([$event], $aggregateId, 'Ticket');

        // Arrange: Coloca um item no cache para verificar a invalidação
        Cache::tags(self::CACHE_TEST_TAG)->put(self::CACHE_TEST_KEY, 'exists', 600);
        $this->assertTrue(
            Cache::tags(self::CACHE_TEST_TAG)->has(self::CACHE_TEST_KEY),
            self::CACHE_ERROR_MESSAGE
        );

        // Act
        try {
            $this->dispatcher->dispatch($appEvent); // Listener será executado via sync queue
        } catch (Throwable $e) {
            $this->fail(self::ERROR_MESSAGE . $e->getMessage());
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
            $createdAt->format(self::DATE_FORMAT),
            $dbData['created_at']->toDateTimeImmutable()
                ->setTimezone(new \DateTimeZone(date_default_timezone_get()))
                ->format(self::DATE_FORMAT)
        );
        $this->assertNull($dbData['resolved_at']);
        $this->assertInstanceOf(UTCDateTime::class, $dbData['last_updated_at']);

        // Assert: Verifica se o cache foi invalidado
        $this->assertFalse(
            Cache::tags(self::CACHE_TEST_TAG)->has(self::CACHE_TEST_KEY),
            "Cache não foi invalidado após TicketCreated."
        );
    }

    /** @test */
    public function it_updates_read_model_when_ticket_resolved_event_is_dispatched(): void
    {
        // Arrange: Primeiro, cria o estado inicial disparando TicketCreated
        $aggregateId = 'projection-resolve-1';
        $createdEventId = 'event-proj-int-resolve-c1';
        $createdAt = new DateTimeImmutable('2024-03-10T11:00:00Z');
        $createdEvent = new TicketCreated(
            $aggregateId,
            'To Be Resolved',
            'Desc resolve',
            Priority::LOW,
            $createdAt,
            $createdEventId
        );
        $createdAppEvent = new DomainEventsPersisted([$createdEvent], $aggregateId, 'Ticket');
        $this->dispatcher->dispatch($createdAppEvent); // Cria o documento inicial

        // Arrange: Prepara o evento TicketResolved
        sleep(1); // Garante timestamp diferente
        $resolvedAt = new DateTimeImmutable();
        $resolvedEvent = new TicketResolved($aggregateId, $resolvedAt);
        $resolvedAppEvent = new DomainEventsPersisted([$resolvedEvent], $aggregateId, 'Ticket');

        // Arrange: Coloca um item no cache para verificar a invalidação
        Cache::tags(self::CACHE_TEST_TAG)->put(self::CACHE_TEST_KEY, 'exists', 600);
        $this->assertTrue(
            Cache::tags(self::CACHE_TEST_TAG)->has(self::CACHE_TEST_KEY),
            self::CACHE_ERROR_MESSAGE
        );

        // Act
        try {
            $this->dispatcher->dispatch($resolvedAppEvent); // Dispara o evento de resolução
        } catch (Throwable $e) {
            $this->fail(self::ERROR_MESSAGE . $e->getMessage());
        }

        // Assert: Verifica o documento atualizado no banco
        $dbData = $this->findReadModelInDb($aggregateId);

        $this->assertNotNull($dbData);
        $this->assertSame($aggregateId, $dbData['ticket_id']);
        $this->assertSame('To Be Resolved', $dbData['title']); // Título deve permanecer
        $this->assertSame(Status::RESOLVED, $dbData['status']); // Status atualizado
        $this->assertInstanceOf(UTCDateTime::class, $dbData['resolved_at']);
        $this->assertEquals(
            $resolvedAt->format(self::DATE_FORMAT),
            $dbData['resolved_at']->toDateTimeImmutable()
                ->setTimezone(new \DateTimeZone(date_default_timezone_get()))
                ->format(self::DATE_FORMAT)
        );
        // Verifica se created_at não mudou
        $this->assertEquals(
            $createdAt->format(self::DATE_FORMAT),
            $dbData['created_at']->toDateTimeImmutable()
                ->setTimezone(new \DateTimeZone(date_default_timezone_get()))
                ->format(self::DATE_FORMAT)
        );

        // Assert: Verifica se o cache foi invalidado
        $this->assertFalse(
            Cache::tags(self::CACHE_TEST_TAG)->has(self::CACHE_TEST_KEY),
            "Cache não foi invalidado após TicketResolved."
        );
    }

    /** @test */
    public function it_handles_multiple_events_in_one_dispatch_correctly(): void
    {
        // Arrange
        $aggregateId = 'projection-multi-1';
        $eventId1 = 'event-proj-int-multi-1';
        $eventId2 = 'event-proj-int-multi-2';
        $createdAt = new DateTimeImmutable('2024-03-10T12:00:00Z');
        $resolvedAt = $createdAt->modify('+1 hour');
        $createdEvent = new TicketCreated(
            $aggregateId,
            'Multi Event Proj',
            'Desc multi',
            Priority::MEDIUM,
            $createdAt,
            $eventId1
        );
        $resolvedEvent = new TicketResolved(
            $aggregateId,
            $resolvedAt,
            $eventId2
        );
        // Evento com ambos os eventos
        $appEvent = new DomainEventsPersisted([$createdEvent, $resolvedEvent], $aggregateId, 'Ticket');

        // Arrange: Coloca um item no cache para verificar a invalidação
        Cache::tags(self::CACHE_TEST_TAG)->put(self::CACHE_TEST_KEY, 'exists', 600);
        $this->assertTrue(
            Cache::tags(self::CACHE_TEST_TAG)->has(self::CACHE_TEST_KEY),
            self::CACHE_ERROR_MESSAGE
        );

        // Act
        try {
            $this->dispatcher->dispatch($appEvent);
        } catch (Throwable $e) {
            $this->fail(self::ERROR_MESSAGE . $e->getMessage());
        }

        // Assert: Verifica o estado final no banco
        $dbData = $this->findReadModelInDb($aggregateId);

        $this->assertNotNull($dbData);
        $this->assertSame($aggregateId, $dbData['ticket_id']);
        $this->assertSame('Multi Event Proj', $dbData['title']);
        $this->assertSame('medium', $dbData['priority']);
        $this->assertSame(Status::RESOLVED, $dbData['status']); // Estado final deve ser resolvido
        $this->assertEquals(
            $createdAt->format(self::DATE_FORMAT),
            $dbData['created_at']->toDateTimeImmutable()
                ->setTimezone(new \DateTimeZone(date_default_timezone_get()))
                ->format(self::DATE_FORMAT)
        );
        $this->assertEquals(
            $resolvedAt->format(self::DATE_FORMAT),
            $dbData['resolved_at']->toDateTimeImmutable()
                ->setTimezone(new \DateTimeZone(date_default_timezone_get()))
                ->format(self::DATE_FORMAT)
        );

        // Assert: Verifica se o cache foi invalidado
        $this->assertFalse(
            Cache::tags(self::CACHE_TEST_TAG)->has(self::CACHE_TEST_KEY),
            "Cache não foi invalidado após múltiplos eventos."
        );
    }

    /** @test */
    public function it_ignores_events_for_other_aggregate_types_in_projection(): void
    {
        // Arrange
        $aggregateId = 'projection-ignore-1';
        $event = new TicketCreated($aggregateId, 'Ignore Title', 'Ignore Desc', Priority::LOW);
        $appEvent = new DomainEventsPersisted([$event], $aggregateId, 'NonTicketAggregate'); // Tipo diferente

        // Arrange: Coloca um item no cache para verificar se ele NÃO será invalidado
        Cache::tags(self::CACHE_TEST_TAG)->put(self::CACHE_TEST_KEY, 'exists', 600);
        $this->assertTrue(
            Cache::tags(self::CACHE_TEST_TAG)->has(self::CACHE_TEST_KEY),
            self::CACHE_ERROR_MESSAGE
        );

        // Act
        try {
            $this->dispatcher->dispatch($appEvent);
        } catch (Throwable $e) {
            $this->fail(self::ERROR_MESSAGE . $e->getMessage());
        }

        // Assert: Verifica que NENHUM documento foi criado no banco
        $dbData = $this->findReadModelInDb($aggregateId);
        $this->assertNull($dbData, "Documento foi criado indevidamente para tipo de agregado diferente.");

        // Assert: Verifica que o cache NÃO foi invalidado
        $this->assertTrue(
            Cache::tags(self::CACHE_TEST_TAG)->has(self::CACHE_TEST_KEY),
            "Cache foi invalidado indevidamente para tipo de agregado diferente."
        );
    }

    /** @test */
    public function it_handles_duplicate_event_dispatch_idempotently(): void
    {
        // Arrange
        $aggregateId = 'projection-idempotency-1';
        $eventId = 'event-proj-idem-1';
        $createdAt = new DateTimeImmutable('2024-03-10T15:00:00Z');
        $event = new TicketCreated(
            $aggregateId,
            'Idempotency Proj Test',
            'Desc idempotency proj',
            Priority::LOW,
            $createdAt,
            $eventId
        );

        $appEvent = new DomainEventsPersisted([$event], $aggregateId, 'Ticket');

        $idempotencyCacheKey = self::IDEMPOTENCY_CACHE_PREFIX . $eventId;

        // Act: First dispatch
        try {
            $this->dispatcher->dispatch($appEvent);
        } catch (Throwable $e) {
            $this->fail("Primeira execução do listener falhou: " . $e->getMessage());
        }

        // Assert: Check DB state after first run
        $dbDataAfterFirstRun = $this->findReadModelInDb($aggregateId);
        $this->assertNotNull($dbDataAfterFirstRun, "Documento não encontrado após primeira execução.");
        $this->assertSame(Status::OPEN, $dbDataAfterFirstRun['status']);
        $this->assertInstanceOf(UTCDateTime::class, $dbDataAfterFirstRun['last_updated_at']);
        $lastUpdateAfterFirst = $dbDataAfterFirstRun['last_updated_at']->toDateTimeImmutable();

        // Assert: Check idempotency cache key exists
        $this->assertTrue(Cache::has($idempotencyCacheKey), "Chave de idempotência não encontrada no cache após primeira execução.");

        // Act: Second dispatch (same event)
        sleep(1);
        try {
            $this->dispatcher->dispatch($appEvent);
        } catch (Throwable $e) {
            $this->fail("Segunda execução do listener (idempotência) falhou inesperadamente: " . $e->getMessage());
        }

        // Assert: Check DB state again - last_updated_at NÃO deve ter mudado
        $dbDataAfterSecondRun = $this->findReadModelInDb($aggregateId);
        $this->assertNotNull($dbDataAfterSecondRun);
        $this->assertEquals(
            $lastUpdateAfterFirst,
            $dbDataAfterSecondRun['last_updated_at']->toDateTimeImmutable(),
            "last_updated_at foi modificado na segunda execução (falha na idempotência)."
        );
    }
}
