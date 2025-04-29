<?php

namespace Tests\Unit\Infrastructure\Persistence\MongoDB\Projections;

use App\Application\DTOs\TicketDTO;
use App\Application\Events\DomainEventsPersisted;
use App\Domain\Events\TicketCreated;
use App\Domain\Events\TicketResolved;
use App\Domain\Interfaces\Repositories\TicketReadRepositoryInterface;
use App\Domain\ValueObjects\Priority;
use App\Domain\ValueObjects\Status;
use App\Infrastructure\Persistence\Exceptions\PersistenceOperationFailedException;
use App\Infrastructure\Persistence\MongoDB\Projections\UpdateTicketsReadModelProjection;
use DateTimeImmutable;
use Illuminate\Support\Facades\Log;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class UpdateTicketsReadModelProjectionTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private Mockery\MockInterface|TicketReadRepositoryInterface $mockReadRepository;
    private UpdateTicketsReadModelProjection $projection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockReadRepository = Mockery::mock(TicketReadRepositoryInterface::class);

        // Instancia a projeção injetando o mock
        $this->projection = new UpdateTicketsReadModelProjection($this->mockReadRepository);
    }

    /** @test */
    public function it_handles_ticket_created_event_and_saves_new_dto(): void
    {
        // Arrange
        $aggregateId = 'proj-create-123';
        $createdAt = new DateTimeImmutable('2024-02-01T12:00:00Z');
        $eventPriorityInt = Priority::HIGH;
        $event = new TicketCreated(
            $aggregateId,
            'Projection Title',
            'Projection Desc',
            $eventPriorityInt,
            $createdAt
        );
        $appEvent = new DomainEventsPersisted([$event], $aggregateId, 'Ticket');

        // Espera que findById seja chamado e retorne null (novo ticket)
        $this->mockReadRepository
            ->shouldReceive('findById')
            ->once()
            ->with($aggregateId)
            ->andReturnNull();

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
            }));

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
        $event = new TicketResolved($aggregateId, $resolvedAt);
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

        // Espera que save seja chamado com o DTO atualizado
        $this->mockReadRepository
            ->shouldReceive('save')
            ->once()
            ->with(Mockery::on(function (TicketDTO $dto) use ($aggregateId, $resolvedAt, $createdAt) {
                return $dto->id === $aggregateId &&
                       $dto->status === Status::RESOLVED &&
                       $dto->resolvedAt == $resolvedAt && // Verifica se a data de resolução foi atualizada
                       $dto->createdAt == $createdAt; // Verifica se outras props foram mantidas
            }));

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
        $eventPriorityInt = Priority::MEDIUM;

        $createdEvent = new TicketCreated($aggregateId, 'Multi Title', 'Multi Desc', Priority::MEDIUM, $createdAt);
        $resolvedEvent = new TicketResolved($aggregateId, $resolvedAt);

        $appEvent = new DomainEventsPersisted([$createdEvent, $resolvedEvent], $aggregateId, 'Ticket');

        // Expect findById for TicketCreated (returns null)
        $this->mockReadRepository
            ->shouldReceive('findById')
            ->once()
            ->with($aggregateId)
            ->andReturnNull();

        // Expect save for TicketCreated
        $expectedPriorityString = (new Priority($eventPriorityInt))->toString();
        $this->mockReadRepository
            ->shouldReceive('save')
            ->once()
            ->with(Mockery::on(function(TicketDTO $dto) use ($aggregateId, $expectedPriorityString) {
                return $dto->id === $aggregateId && $dto->priority === $expectedPriorityString && $dto->status === Status::OPEN;
            }));

        // Expect findById for TicketResolved (returns the DTO created above)
        $dtoAfterCreate = new TicketDTO($aggregateId, 'Multi Title', 'Multi Desc', $expectedPriorityString, Status::OPEN, $createdAt, null);
        $this->mockReadRepository
            ->shouldReceive('findById')
            ->once()
            ->with($aggregateId)
            ->andReturn($dtoAfterCreate); // Retorna o DTO como se tivesse sido salvo

        // Expect save for TicketResolved (com o DTO atualizado)
        $this->mockReadRepository
            ->shouldReceive('save')
            ->once()
            ->with(Mockery::on(function (TicketDTO $dto) use ($aggregateId, $resolvedAt) {
                return $dto->id === $aggregateId &&
                       $dto->status === Status::RESOLVED &&
                       $dto->resolvedAt == $resolvedAt;
            }));

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
        $eventPriorityInt = Priority::LOW;
        $event = new TicketCreated($aggregateId, 'Error Title', 'Error Desc', $eventPriorityInt);
        $appEvent = new DomainEventsPersisted([$event], $aggregateId, 'Ticket');
        $exception = new PersistenceOperationFailedException('DB error');

        $this->mockReadRepository
            ->shouldReceive('findById')
            ->once()
            ->with($aggregateId)
            ->andReturnNull();

        // Simula erro no save
        $this->mockReadRepository
            ->shouldReceive('save')
            ->once()
            ->with(Mockery::type(TicketDTO::class))
            ->andThrow($exception);

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

        // Act
        $this->projection->handle($appEvent);

        // Assert (implicit)
        $this->assertTrue(true); // O listener não deve relançar a exceção por padrão
    }
}
