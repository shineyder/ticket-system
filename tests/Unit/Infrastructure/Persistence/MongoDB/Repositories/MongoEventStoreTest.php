<?php

namespace Tests\Unit\Infrastructure\Persistence\MongoDB\Repositories;

use App\Domain\Entities\Ticket;
use App\Domain\Events\TicketCreated;
use App\Domain\Exceptions\AggregateNotFoundException;
use App\Domain\ValueObjects\Priority;
use App\Infrastructure\Persistence\Exceptions\EventClassNotFoundException;
use App\Infrastructure\Persistence\Exceptions\EventInstantiateFailedException;
use App\Infrastructure\Persistence\Exceptions\EventLoadFailedException;
use App\Infrastructure\Persistence\MongoDB\MongoConnection;
use App\Infrastructure\Persistence\MongoDB\Repositories\MongoEventStore;
use DateTimeImmutable;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;
use MongoDB\Driver\CursorInterface;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class MongoEventStoreTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private Mockery\MockInterface|MongoConnection $mockConnection;
    private Mockery\MockInterface|Collection $mockCollection;
    private MongoEventStore $eventStore;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockConnection = Mockery::mock(MongoConnection::class);
        $this->mockCollection = Mockery::mock(Collection::class);

        $this->mockConnection->shouldReceive('getDatabase->selectCollection')
            ->with('ticket_events')
            ->andReturn($this->mockCollection);

        $this->eventStore = new MongoEventStore($this->mockConnection);

        $this->mockCollection->shouldReceive('findOne')
            ->zeroOrMoreTimes()
            ->andReturnNull();
    }

    /** @test */
    public function load_throws_aggregate_not_found_if_no_events(): void
    {
        // Arrange
        $aggregateId = 'not-found-1';
        $mockCursor = Mockery::mock(
            $this->getMockBuilder(CursorInterface::class)
                ->disableOriginalConstructor()
                ->getMock()
        );
        // Mock find para retornar um array vazio diretamente
        $this->mockCollection->shouldReceive('find')
            ->once()
            ->with(['aggregate_id' => $aggregateId], Mockery::any())
            ->andReturn($mockCursor);
        $mockCursor->shouldReceive('toArray')
            ->once()
            ->andReturn([]);

        // Assert
        $this->expectException(AggregateNotFoundException::class);
        $this->expectExceptionMessage('Ticket com ID ' . $aggregateId . ' não encontrado.');
        $this->expectExceptionCode(0);

        // Act
        $this->eventStore->load($aggregateId);
    }

    /** @test */
    public function load_throws_event_load_failed_on_cursor_error(): void
    {
        // Arrange
        $aggregateId = 'cursor-fail-1';
        $exception = new EventLoadFailedException($aggregateId, 'Cursor error');

        $mockCursor = Mockery::mock(
            $this->getMockBuilder(CursorInterface::class)
                ->disableOriginalConstructor()
                ->getMock()
        );
        // Mock find para lançar a exceção diretamente
        $this->mockCollection->shouldReceive('find')
            ->once()
            ->with(['aggregate_id' => $aggregateId], Mockery::any())
            ->andReturn($mockCursor);
        $mockCursor->shouldReceive('toArray')
            ->once()
            ->andThrow($exception);

        // Assert
        $this->expectException(EventLoadFailedException::class);
        $this->expectExceptionMessage('Falha ao carregar agregado ' . $aggregateId . ': Cursor error');
        $this->expectExceptionCode(0);

        // Act
        $this->eventStore->load($aggregateId);
    }

    /** @test */
    public function load_throws_event_load_failed_on_json_decode_error(): void
    {
        // Arrange
        $aggregateId = 'json-fail-1';
        $eventData = [
            '_id' => new ObjectId(),
            'aggregate_id' => $aggregateId,
            'event_type' => TicketCreated::class,
            'event_id' => 'evt-json-fail',
            'payload' => '{"title": "Bad JSON', // JSON inválido
            'sequence_number' => 1,
            'occurred_on' => new UTCDateTime(),
            'version' => 1,
        ];
        $mockCursor = Mockery::mock(
            $this->getMockBuilder(CursorInterface::class)
                ->disableOriginalConstructor()
                ->getMock()
        );
        // Mock find para retornar o array com dados inválidos
        $this->mockCollection->shouldReceive('find')
            ->once()
            ->andReturn($mockCursor);
        $mockCursor->shouldReceive('toArray')
            ->once()
            ->andReturn([$eventData]);

        // Assert
        $this->expectException(EventInstantiateFailedException::class);
        $this->expectExceptionMessage('Falha ao instanciar evento App\Domain\Events\TicketCreated.');
        $this->expectExceptionCode(0);

        // Act
        $this->eventStore->load($aggregateId);
    }

    /** @test */
    public function load_throws_event_class_not_found(): void
    {
        // Arrange
        $aggregateId = 'class-not-found-1';
        $eventData = [
            '_id' => new ObjectId(),
            'aggregate_id' => $aggregateId,
            'event_type' => 'App\\Domain\\Events\\NonExistentEvent', // Classe não existe
            'event_id' => 'evt-class-fail',
            'payload' => '{}',
            'sequence_number' => 1,
            'occurred_on' => new UTCDateTime(),
            'version' => 1,
        ];
        $mockCursor = Mockery::mock(
            $this->getMockBuilder(CursorInterface::class)
                ->disableOriginalConstructor()
                ->getMock()
        );
        // Mock find para retornar o array com dados inválidos
        $this->mockCollection->shouldReceive('find')
            ->once()
           ->andReturn($mockCursor);
        $mockCursor->shouldReceive('toArray')
            ->once()
            ->andReturn([$eventData]);

        // Assert
        $this->expectException(EventClassNotFoundException::class);
        $this->expectExceptionMessage('Falha ao salvar eventos para o agregado App\Domain\Events\NonExistentEvent.');
        $this->expectExceptionCode(0);

        // Act
        $this->eventStore->load($aggregateId);
    }

     /** @test */
    public function load_throws_event_instantiate_failed_on_type_error(): void
    {
        // Arrange
        $aggregateId = 'type-error-1';
        $eventData = [
            '_id' => new ObjectId(),
            'aggregate_id' => $aggregateId,
            'event_type' => TicketCreated::class, // Evento real
            'event_id' => 'evt-type-fail',
            // Payload com tipo errado para Priority (string em vez de int)
            'payload' => json_encode(['title' => 'T', 'description' => 'D', 'priority' => 'not-an-int']),
            'sequence_number' => 1,
            'occurred_on' => new UTCDateTime(),
            'version' => 1,
        ];
        $mockCursor = Mockery::mock(
            $this->getMockBuilder(CursorInterface::class)
                ->disableOriginalConstructor()
                ->getMock()
        );
        // Mock find para retornar o array com dados inválidos
        $this->mockCollection->shouldReceive('find')
            ->once()
            ->andReturn($mockCursor);
        $mockCursor->shouldReceive('toArray')
            ->once()
            ->andReturn([$eventData]);

        // Assert
        $this->expectException(EventInstantiateFailedException::class);
        $this->expectExceptionMessage('Falha ao instanciar evento App\Domain\Events\TicketCreated.');
        $this->expectExceptionCode(0);

        // Act
        $this->eventStore->load($aggregateId);
    }

    /** @test */
    public function load_correctly_reconstitutes_event_with_value_objects(): void
    {
        // Arrange
        $aggregateId = 'vo-test-1';
        $priorityValue = Priority::HIGH;
        $occurredOn = new DateTimeImmutable();
        $eventData = [
            '_id' => new ObjectId(),
            'aggregate_id' => $aggregateId,
            'event_type' => TicketCreated::class,
            'event_id' => 'evt-vo-test',
            'payload' => json_encode([
                'title' => 'VO Test',
                'description' => 'Desc',
                'priority' => $priorityValue // Valor int para Priority
            ]),
            'sequence_number' => 1,
            'occurred_on' => new UTCDateTime($occurredOn),
            'version' => 1,
        ];
        $mockCursor = Mockery::mock(
            $this->getMockBuilder(CursorInterface::class)
                ->disableOriginalConstructor()
                ->getMock()
        );
        // Mock find para retornar o array com dados válidos
        $this->mockCollection->shouldReceive('find')
            ->once()
            ->andReturn($mockCursor);
        $mockCursor->shouldReceive('toArray')
            ->once()
            ->andReturn([$eventData]);

        // Act
        $ticket = $this->eventStore->load($aggregateId);

        // Assert: Verifica o estado do Ticket reconstituído
        $this->assertInstanceOf(Ticket::class, $ticket);
        $this->assertInstanceOf(Priority::class, $ticket->getPriority());
        $this->assertEquals($priorityValue, $ticket->getPriority()->value());
        $this->assertEquals('VO Test', $ticket->getTitle());
    }

    /** @test */
    public function load_correctly_reconstitutes_event_with_timestamp_occurred_on(): void
    {
        // Arrange
        $aggregateId = 'timestamp-test-1';
        $timestamp = time(); // Usa um timestamp Unix
        $expectedOccurredOn = new DateTimeImmutable('@' . $timestamp);

        $eventData = [
            '_id' => new ObjectId(),
            'aggregate_id' => $aggregateId,
            'event_type' => TicketCreated::class,
            'event_id' => 'evt-ts-test',
            'payload' => json_encode(['title' => 'TS', 'description' => 'D', 'priority' => 0]),
            'sequence_number' => 1,
            'occurred_on' => $timestamp, // Passa o timestamp diretamente
            'version' => 1,
        ];
        $mockCursor = Mockery::mock(
            $this->getMockBuilder(CursorInterface::class)
                ->disableOriginalConstructor()
                ->getMock()
        );
        // Mock find para retornar o array com dados válidos
        $this->mockCollection->shouldReceive('find')
            ->once()
            ->andReturn($mockCursor);
        $mockCursor->shouldReceive('toArray')
            ->once()
            ->andReturn([$eventData]);

        // Act
        $ticket = $this->eventStore->load($aggregateId);

        // Assert: Verifica o estado do Ticket reconstituído
        $this->assertInstanceOf(Ticket::class, $ticket);
        // Compara os timestamps formatados para ignorar diferenças de microsegundos/timezone
        $this->assertEquals(
            $expectedOccurredOn->format('Y-m-d H:i:s'),
            $ticket->getCreatedAt()->format('Y-m-d H:i:s') // Verifica o createdAt do Ticket
        );
    }
}
