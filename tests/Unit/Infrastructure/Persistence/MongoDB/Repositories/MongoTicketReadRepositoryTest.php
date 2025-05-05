<?php

namespace Tests\Unit\Infrastructure\Persistence\MongoDB\Repositories;

use App\Application\DTOs\TicketDTO;
use App\Infrastructure\Persistence\Exceptions\PersistenceOperationFailedException;
use App\Infrastructure\Persistence\MongoDB\MongoConnection;
use App\Infrastructure\Persistence\MongoDB\Repositories\MongoTicketReadRepository;
use DateTimeImmutable;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;
use MongoDB\Driver\Exception\RuntimeException as MongoRuntimeException;
use MongoDB\Driver\CursorInterface;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class MongoTicketReadRepositoryTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private Mockery\MockInterface|MongoConnection $mockConnection;
    private Mockery\MockInterface|Collection $mockCollection;
    private MongoTicketReadRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockConnection = Mockery::mock(MongoConnection::class);
        $this->mockCollection = Mockery::mock(Collection::class);

        $this->mockConnection->shouldReceive('getDatabase->selectCollection')
            ->with('ticket_read_models')
            ->andReturn($this->mockCollection);

        $this->repository = new MongoTicketReadRepository($this->mockConnection);
    }

    /** @test */
    public function save_throws_persistence_exception_on_driver_error(): void
    {
        // Arrange
        $dto = new TicketDTO('id1', 'Title', 'Desc', 'low', 'open');
        $exception = new MongoRuntimeException("Update failed");

        $this->mockCollection->shouldReceive('updateOne')
            ->once()
            ->with(['ticket_id' => $dto->id], Mockery::any(), ['upsert' => true])
            ->andThrow($exception);

        // Assert
        $this->expectException(PersistenceOperationFailedException::class);
        $this->expectExceptionMessage('Erro ao salvar read model do ticket com ID id1: Update failed');
        $this->expectExceptionCode(0);

        // Act
        $this->repository->save($dto);
    }

    /** @test */
    public function findById_throws_persistence_exception_on_driver_error(): void
    {
        // Arrange
        $ticketId = 'find-fail-id';
        $exception = new MongoRuntimeException("Find failed");

        $this->mockCollection->shouldReceive('findOne')
            ->once()
            ->with(['ticket_id' => $ticketId])
            ->andThrow($exception);

        // Assert
        $this->expectException(PersistenceOperationFailedException::class);
        $this->expectExceptionMessage('Erro ao buscar read model do ticket com ID find-fail-id: Find failed');
        $this->expectExceptionCode(0);

        // Act
        $this->repository->findById($ticketId);
    }

    /** @test */
    public function findAll_throws_persistence_exception_on_driver_error(): void
    {
        // Arrange
        $exception = new MongoRuntimeException("Find failed");

        $this->mockCollection->shouldReceive('find')
            ->once()
            ->with([], ['sort' => ['created_at' => -1]]) // Default sort
            ->andThrow($exception);

        // Assert
        $this->expectException(PersistenceOperationFailedException::class);
        $this->expectExceptionMessage('Erro ao buscar todos os read models de tickets: Find failed');
        $this->expectExceptionCode(0);

        // Act
        $this->repository->findAll();
    }

    /** @test */
    public function findAll_uses_default_sort_when_invalid_orderby_provided(): void
    {
        // Arrange
        $mockCursor = Mockery::mock(
            $this->getMockBuilder(CursorInterface::class)
                ->disableOriginalConstructor()
                ->getMock()
        );
        $mockCursor->shouldReceive('rewind', 'valid', 'current', 'key', 'next'); // Mock iterator methods

        // Expect find to be called with the DEFAULT sort options, even though we pass invalid ones
        $this->mockCollection->shouldReceive('find')
            ->once()
            ->with([], ['sort' => ['created_at' => -1]]) // Expect default sort
            ->andReturn($mockCursor);

        // Act
        $this->repository->findAll('invalid_field', 'desc');

        // Assert (implicit via Mockery expectation)
        $this->assertTrue(true);
    }

    /** @test */
    public function findById_maps_null_created_at_when_null_in_document(): void
    {
        // Arrange
        $ticketId = 'null-created-at-id';
        $documentData = [
            'ticket_id' => $ticketId,
            'title' => 'Null CreatedAt Test',
            'description' => 'This ticket has null creation date in DB',
            'priority' => 'medium',
            'status' => 'open',
            'created_at' => null, // Campo é null no documento
            'resolved_at' => null
        ];

        $this->mockCollection->shouldReceive('findOne')
            ->once()
            ->with(['ticket_id' => $ticketId])
            ->andReturn((object) $documentData); // Retorna como objeto

        // Act
        $dto = $this->repository->findById($ticketId);

        // Assert
        $this->assertInstanceOf(TicketDTO::class, $dto);
        $this->assertNull($dto->createdAt); // Verifica se createdAt é null no DTO
        $this->assertSame('Null CreatedAt Test', $dto->title);
        $this->assertSame('This ticket has null creation date in DB', $dto->description);
        $this->assertSame('medium', $dto->priority);
    }
}
