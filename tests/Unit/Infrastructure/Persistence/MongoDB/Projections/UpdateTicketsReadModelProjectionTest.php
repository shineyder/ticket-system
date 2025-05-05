<?php

namespace Tests\Unit\Infrastructure\Persistence\MongoDB\Projections;

use App\Application\DTOs\TicketDTO;
use App\Application\Events\DomainEventsPersisted;
use App\Domain\Events\TicketCreated;
use App\Domain\Events\TicketResolved;
use App\Domain\Interfaces\Repositories\TicketReadRepositoryInterface;
use App\Domain\ValueObjects\Priority;
use App\Domain\ValueObjects\Status;
use App\Infrastructure\Persistence\Cache\CachingTicketReadRepository;
use App\Infrastructure\Persistence\Exceptions\PersistenceOperationFailedException;
use App\Infrastructure\Persistence\MongoDB\Projections\UpdateTicketsReadModelProjection;
use DateTimeImmutable;
use Illuminate\Support\Facades\Log;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;
use Illuminate\Cache\CacheManager;

class UpdateTicketsReadModelProjectionTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private Mockery\MockInterface|TicketReadRepositoryInterface $mockReadRepository;
    private UpdateTicketsReadModelProjection $projection;
    private Mockery\MockInterface|CacheManager $mockCacheManager;

    private const CACHE_PREFIX = 'processed_event:';
    private const LOG_DEBUG_MESSAGE = 'Cache de listagem de tickets invalidado.';

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockReadRepository = Mockery::mock(TicketReadRepositoryInterface::class);
        $this->mockCacheManager = Mockery::mock(CacheManager::class);

        // Instancia a projeção injetando o mock
        $this->projection = new UpdateTicketsReadModelProjection($this->mockReadRepository, $this->mockCacheManager);
    }

    /** @test */
    public function it_handles_ticket_created_event_and_saves_new_dto(): void
    {
        // Arrange
        $aggregateId = 'proj-create-123';
        $createdAt = new DateTimeImmutable('2024-02-01T12:00:00Z');
        $eventId = 'event-proj-create-1';
        $eventPriorityInt = Priority::HIGH;
        $event = new TicketCreated(
            $aggregateId,
            'Projection Title',
            'Projection Desc',
            $eventPriorityInt,
            $createdAt,
            $eventId
        );
        $appEvent = new DomainEventsPersisted([$event], $aggregateId, 'Ticket');

        // Espera que findById seja chamado e retorne null (novo ticket)
        $this->mockReadRepository
            ->shouldReceive('findById')
            ->once()
            ->with($aggregateId)
            ->andReturnNull();

        // Espera verificação de idempotência (retorna false)
        $this->mockCacheManager->shouldReceive('has')
            ->with(self::CACHE_PREFIX . $eventId)
            ->once()
            ->andReturnFalse();

        // Espera que save seja chamado com o DTO correto
        $expectedPriorityString = (new Priority($eventPriorityInt))->toString();
        $this->mockReadRepository
            ->shouldReceive('save')
            ->once()
            ->with(Mockery::on(function (TicketDTO $dto) use ($aggregateId, $event, $createdAt, $expectedPriorityString) {
                return $dto->id === $aggregateId &&
                       $dto->title === $event->title &&
                       $dto->description === $event->description &&
                       $dto->priority === $expectedPriorityString &&
                       $dto->status === Status::OPEN &&
                       $dto->createdAt == $createdAt && // Use == for DateTime comparison
                       $dto->resolvedAt === null;
            }))
            ->ordered();

        // Espera que o cache de idempotência seja setado APÓS o save
        $this->mockCacheManager->shouldReceive('put')
            ->with(self::CACHE_PREFIX . $eventId, true, Mockery::any())
            ->once()
            ->ordered();

        // Espera que o cache seja invalidado
        $mockTaggedCache = Mockery::mock(\Illuminate\Cache\TaggedCache::class);
        $this->mockCacheManager
            ->shouldReceive('tags')
            ->once()
            ->with(CachingTicketReadRepository::CACHE_TAG)
            ->andReturn($mockTaggedCache);
        $mockTaggedCache->shouldReceive('flush')
            ->once();

        // Espera o log de debug da invalidação do cache
        Log::shouldReceive('debug')
            ->once()
            ->with(
                self::LOG_DEBUG_MESSAGE,
                ['tag' => CachingTicketReadRepository::CACHE_TAG]
            );

        // Act
        $this->projection->handle($appEvent);

        // Assert (implicit via Mockery)
        $this->assertTrue(true);
    }

    /** @test */
    public function it_handles_ticket_resolved_event_and_updates_existing_dto(): void
    {
        // Arrange
        $aggregateId = 'proj-resolve-456';
        $createdAt = new DateTimeImmutable('2024-02-01T13:00:00Z');
        $resolvedAt = new DateTimeImmutable('2024-02-01T14:00:00Z');
        $eventId = 'event-proj-resolve-1';
        $event = new TicketResolved(
            $aggregateId,
            $resolvedAt,
            $eventId
        );

        $appEvent = new DomainEventsPersisted([$event], $aggregateId, 'Ticket');

        // DTO existente que será retornado por findById
        $existingDto = new TicketDTO(
            $aggregateId,
            'Existing Title',
            'Existing Desc',
            Priority::LOW,
            Status::OPEN,
            $createdAt,
            null
        );

        // Espera que findById seja chamado e retorne o DTO existente
        $this->mockReadRepository
            ->shouldReceive('findById')
            ->once()
            ->with($aggregateId)
            ->andReturn($existingDto);

        // Espera verificação de idempotência (retorna false)
        $this->mockCacheManager->shouldReceive('has')
            ->with(self::CACHE_PREFIX . $eventId)
            ->once()
            ->andReturnFalse();

        // Espera que save seja chamado com o DTO atualizado
        $this->mockReadRepository
            ->shouldReceive('save')
            ->once()
            ->with(Mockery::on(function (TicketDTO $dto) use ($aggregateId, $resolvedAt, $createdAt) {
                return $dto->id === $aggregateId &&
                       $dto->status === Status::RESOLVED &&
                       $dto->resolvedAt == $resolvedAt && // Verifica se a data de resolução foi atualizada
                       $dto->createdAt == $createdAt; // Verifica se outras props foram mantidas
            }))
            ->ordered();

        // Espera que o cache de idempotência seja setado APÓS o save
        $this->mockCacheManager->shouldReceive('put')
            ->with(self::CACHE_PREFIX . $eventId, true, Mockery::any())
            ->once()
            ->ordered();

        // Espera que o cache seja invalidado
        $mockTaggedCache = Mockery::mock(\Illuminate\Cache\TaggedCache::class);
        $this->mockCacheManager
            ->shouldReceive('tags')
            ->once()
            ->with(CachingTicketReadRepository::CACHE_TAG)
            ->andReturn($mockTaggedCache);
        $mockTaggedCache->shouldReceive('flush')
            ->once();

        // Espera o log de debug da invalidação do cache
        Log::shouldReceive('debug')
            ->once()
            ->with(
                self::LOG_DEBUG_MESSAGE,
                ['tag' => CachingTicketReadRepository::CACHE_TAG]
            );

        // Act
        $this->projection->handle($appEvent);

        // Assert (implicit via Mockery)
        $this->assertTrue(true);
    }

    /** @test */
    public function it_ignores_events_for_different_aggregate_types(): void
    {
        // Arrange
        $aggregateId = 'other-agg-789';
        // Usando um evento genérico ou um evento de outro agregado (se existir)
        $event = new TicketCreated($aggregateId, 'Title', 'Desc', 0);
        $appEvent = new DomainEventsPersisted([$event], $aggregateId, 'OtherAggregate'); // Tipo diferente

        // Espera que NENHUM método do repositório seja chamado
        $this->mockReadRepository->shouldNotReceive('findById');
        $this->mockReadRepository->shouldNotReceive('save');
        $this->mockCacheManager->shouldNotReceive('tags');

        // Act
        $this->projection->handle($appEvent);

        // Assert (implicit via Mockery)
        $this->assertTrue(true);
    }

     /** @test */
    public function it_handles_multiple_events_in_order(): void
    {
        // Arrange
        $aggregateId = 'proj-multi-000';
        $createdAt = new DateTimeImmutable('2024-03-01T10:00:00Z');
        $resolvedAt = new DateTimeImmutable('2024-03-01T11:00:00Z');
        $eventId1 = 'event-proj-multi-1';
        $eventId2 = 'event-proj-multi-2';
        $eventPriorityInt = Priority::MEDIUM;

        $createdEvent = new TicketCreated(
            $aggregateId,
            'Multi Title',
            'Multi Desc',
            Priority::MEDIUM,
            $createdAt,
            $eventId1
        );
        $resolvedEvent = new TicketResolved(
            $aggregateId,
            $resolvedAt,
            $eventId2
        );

        $appEvent = new DomainEventsPersisted([$createdEvent, $resolvedEvent], $aggregateId, 'Ticket');

        // Expect findById for TicketCreated (returns null)
        $this->mockReadRepository
            ->shouldReceive('findById')
            ->once()
            ->with($aggregateId)
            ->andReturnNull();

        // Expect idempotency check for event 1
        $this->mockCacheManager->shouldReceive('has')
            ->with(self::CACHE_PREFIX . $eventId1)
            ->once()
            ->andReturnFalse();

        // Expect save for TicketCreated
        $expectedPriorityString = (new Priority($eventPriorityInt))->toString();
        $this->mockReadRepository
            ->shouldReceive('save')
            ->once()
            ->with(Mockery::on(function(TicketDTO $dto) use ($aggregateId, $expectedPriorityString) {
                return $dto->id === $aggregateId &&
                       $dto->priority === $expectedPriorityString &&
                       $dto->status === Status::OPEN;
            }))
            ->ordered();

        // Expect cache put for event 1
        $this->mockCacheManager->shouldReceive('put')
            ->with(self::CACHE_PREFIX . $eventId1, true, Mockery::any())
            ->once()
            ->ordered();

        // Expect findById for TicketResolved (returns the DTO created above)
        $dtoAfterCreate = new TicketDTO($aggregateId, 'Multi Title', 'Multi Desc', $expectedPriorityString, Status::OPEN, $createdAt, null);
        $this->mockReadRepository
            ->shouldReceive('findById')
            ->once()
            ->with($aggregateId)
            ->andReturn($dtoAfterCreate); // Retorna o DTO como se tivesse sido salvo

        // Expect idempotency check for event 2
        $this->mockCacheManager->shouldReceive('has')
            ->with(self::CACHE_PREFIX . $eventId2)
            ->once()
            ->andReturnFalse();

        // Expect save for TicketResolved (com o DTO atualizado)
        $this->mockReadRepository
            ->shouldReceive('save')
            ->once()
            ->with(Mockery::on(function (TicketDTO $dto) use ($aggregateId, $resolvedAt) {
                return $dto->id === $aggregateId &&
                       $dto->status === Status::RESOLVED &&
                       $dto->resolvedAt == $resolvedAt;
            }))
            ->ordered();

        // Expect cache put for event 2
        $this->mockCacheManager->shouldReceive('put')
            ->with(self::CACHE_PREFIX . $eventId2, true, Mockery::any())
            ->once()
            ->ordered();

        // Espera que o cache seja invalidado (apenas uma vez no final do handle)
        $mockTaggedCache = Mockery::mock(\Illuminate\Cache\TaggedCache::class);
        $this->mockCacheManager
            ->shouldReceive('tags')
            ->once()
            ->with(CachingTicketReadRepository::CACHE_TAG) // Usar a constante
            ->andReturn($mockTaggedCache);
        $mockTaggedCache->shouldReceive('flush')
            ->once();

        // Espera o log de debug da invalidação do cache (apenas uma vez no final)
        Log::shouldReceive('debug')
            ->once()
            ->with(
                self::LOG_DEBUG_MESSAGE,
                ['tag' => CachingTicketReadRepository::CACHE_TAG]
            );

        // Act
        $this->projection->handle($appEvent);

        // Assert (implicit)
        $this->assertTrue(true);
    }

    /** @test */
    public function it_logs_error_if_repository_throws_exception(): void
    {
        // Arrange
        $aggregateId = 'proj-error-111';
        $eventId = 'event-proj-error-1';
        $eventPriorityInt = Priority::LOW;
        $event = new TicketCreated(
            $aggregateId,
            'Error Title',
            'Error Desc',
            $eventPriorityInt,
            null,
            $eventId
        );
        $appEvent = new DomainEventsPersisted([$event], $aggregateId, 'Ticket');
        $exception = new PersistenceOperationFailedException('DB error');

        $this->mockReadRepository
            ->shouldReceive('findById')
            ->once()
            ->with($aggregateId)
            ->andReturnNull();

        // Espera verificação de idempotência (retorna false)
        $this->mockCacheManager->shouldReceive('has')
            ->with(self::CACHE_PREFIX . $eventId)
            ->once()
            ->andReturnFalse();

        // Simula erro no save
        $this->mockReadRepository
            ->shouldReceive('save')
            ->once()
            ->with(Mockery::type(TicketDTO::class))
            ->andThrow($exception);

        $this->mockCacheManager->shouldNotReceive('put');

        // Espera que o erro seja logado
        Log::shouldReceive('error')
            ->once()
            ->with(
                'Erro ao atualizar read model do ticket',
                Mockery::on(function ($context) use ($aggregateId, $exception) {
                    return $context['ticket_id'] === $aggregateId &&
                           $context['event'] === TicketCreated::class &&
                           $context['error'] === $exception->getMessage();
                })
            );

        $this->mockCacheManager->shouldNotReceive('tags');

        // Act
        $this->projection->handle($appEvent);

        // Assert (implicit)
        $this->assertTrue(true); // O listener não deve relançar a exceção por padrão
    }

    /** @test */
    public function it_skips_processing_if_event_already_processed_in_cache(): void
    {
        // Arrange
        $aggregateId = 'proj-skip-789';
        $eventId = 'event-proj-skip-1';
        $event = new TicketCreated(
            $aggregateId,
            'Skip Title',
            'Skip Desc',
            Priority::LOW,
            null,
            $eventId
        );

        $appEvent = new DomainEventsPersisted([$event], $aggregateId, 'Ticket');

        // Espera verificação de idempotência (retorna TRUE)
        $this->mockCacheManager->shouldReceive('has')
            ->with(self::CACHE_PREFIX . $eventId)
            ->once()
            ->andReturnTrue();

        // Espera o log de debug da idempotência
        Log::shouldReceive('debug')
            ->once()
            ->with(
                'Evento já processado, pulando (idempotência).',
                Mockery::on(function ($context) use ($eventId, $event) {
                    return $context['eventId'] === $eventId &&
                           $context['eventType'] === get_class($event);
                })
            );

        // Assert: Nenhum método do repositório deve ser chamado
        $this->mockReadRepository->shouldNotReceive('findById');
        $this->mockReadRepository->shouldNotReceive('save');

        // Assert: Cache put não deve ser chamado
        $this->mockCacheManager->shouldNotReceive('put');

        // Assert: Invalidação de tag também não deve ocorrer (pois não houve save)
        $this->mockCacheManager->shouldNotReceive('tags');

        // Act
        $this->projection->handle($appEvent);

        // Assert (implicit)
        $this->assertTrue(true);
    }

    /** @test */
    public function it_logs_error_if_cache_invalidation_fails(): void
    {
        // Arrange
        $aggregateId = 'proj-cache-fail-123';
        $eventId = 'event-proj-cache-fail-1';
        $event = new TicketCreated(
            $aggregateId,
            'Cache Fail Title',
            'Desc',
            Priority::LOW,
            null,
            $eventId
        );
        $appEvent = new DomainEventsPersisted([$event], $aggregateId, 'Ticket');
        $exception = new \RuntimeException('Redis connection failed'); // Simulate cache error

        // Expect findById (returns null for new ticket)
        $this->mockReadRepository
            ->shouldReceive('findById')
            ->once()
            ->with($aggregateId)
            ->andReturnNull();

        // Expect idempotency check (returns false)
        $this->mockCacheManager->shouldReceive('has')
            ->with(self::CACHE_PREFIX . $eventId)
            ->once()
            ->andReturnFalse();

        // Expect save to succeed
        $this->mockReadRepository
            ->shouldReceive('save')
            ->once()
            ->with(Mockery::type(TicketDTO::class))
            ->ordered(); // Save happens before cache invalidation

        // Expect cache put for idempotency
        $this->mockCacheManager->shouldReceive('put')
            ->with(self::CACHE_PREFIX . $eventId, true, Mockery::any())
            ->once()
            ->ordered();

        // Expect cache invalidation attempt, which will fail
        $mockTaggedCache = Mockery::mock(\Illuminate\Cache\TaggedCache::class);
        $this->mockCacheManager
            ->shouldReceive('tags')
            ->once()
            ->with(CachingTicketReadRepository::CACHE_TAG)
            ->andReturn($mockTaggedCache);
        $mockTaggedCache->shouldReceive('flush') // This is the call that will fail
            ->once()
            ->andThrow($exception);

        // Expect Log::error for the cache invalidation failure
        Log::shouldReceive('error')
            ->once()
            ->with(
                'Falha ao invalidar cache de tickets.',
                Mockery::on(function ($context) use ($exception) {
                    return $context['tag'] === CachingTicketReadRepository::CACHE_TAG &&
                           $context['error'] === $exception->getMessage();
                })
            );

        // Act
        $this->projection->handle($appEvent);

        // Assert (implicit)
        $this->assertTrue(true);
    }
}
