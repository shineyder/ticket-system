<?php

namespace Tests\Unit\Application\UseCases\Commands\ResolveTicket;

use App\Application\Events\DomainEventsPersisted;
use App\Application\UseCases\Commands\ResolveTicket\ResolveTicketCommand;
use App\Application\UseCases\Commands\ResolveTicket\ResolveTicketHandler;
use App\Domain\Entities\Ticket;
use App\Domain\Events\TicketResolved;
use App\Domain\Exceptions\AggregateNotFoundException;
use App\Domain\Exceptions\InvalidTicketStateException;
use App\Domain\Interfaces\Repositories\TicketEventStoreInterface;
use Illuminate\Contracts\Events\Dispatcher;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class ResolveTicketHandlerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private Mockery\MockInterface|TicketEventStoreInterface $mockEventStore;
    private Mockery\MockInterface|Dispatcher $mockEventDispatcher;
    private Mockery\MockInterface|Ticket $mockTicket;
    private ResolveTicketHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockEventStore = Mockery::mock(TicketEventStoreInterface::class);
        $this->mockEventDispatcher = Mockery::mock(Dispatcher::class);
        $this->mockTicket = Mockery::mock(Ticket::class);
        $this->handler = new ResolveTicketHandler($this->mockEventStore, $this->mockEventDispatcher);
    }

    /** @test */
    public function it_handles_resolve_ticket_command_loads_resolves_saves_and_dispatches(): void
    {
        // Arrange
        $ticketId = 'ticket-resolve-123';
        $command = new ResolveTicketCommand($ticketId);

        // Mocking the event that Ticket::resolve would generate
        $mockEvent = Mockery::mock(TicketResolved::class);
        $mockEvents = [$mockEvent];

        // Expect load to be called and return the mock Ticket
        $this->mockEventStore
            ->shouldReceive('load')
            ->once()
            ->with($ticketId)
            ->andReturn($this->mockTicket);

        // Expect resolve() to be called on the mock Ticket
        $this->mockTicket
            ->shouldReceive('resolve')
            ->once();

        // Expect save to be called with the mock Ticket and return events
        $this->mockEventStore
            ->shouldReceive('save')
            ->once()
            ->with($this->mockTicket)
            ->andReturn($mockEvents);

        // Expect getId() to be called on the mock Ticket for the event
        $this->mockTicket
            ->shouldReceive('getId')
            ->andReturn($ticketId); // Needed for DomainEventsPersisted

        // Expect event dispatcher to be called
        $this->mockEventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::on(function ($event) use ($mockEvents, $ticketId) {
                return $event instanceof DomainEventsPersisted &&
                       $event->domainEvents === $mockEvents &&
                       $event->aggregateId === $ticketId &&
                       $event->aggregateType === 'Ticket';
            }));

        // Act
        $this->handler->handle($command);

        // Assert (implicit via Mockery expectations)
        $this->assertTrue(true);
    }

    /** @test */
    public function it_does_not_dispatch_event_if_resolve_does_not_produce_events(): void
    {
        // Arrange
        $ticketId = 'ticket-no-event-456';
        $command = new ResolveTicketCommand($ticketId);

        $this->mockEventStore
            ->shouldReceive('load')
            ->once()
            ->with($ticketId)
            ->andReturn($this->mockTicket);

        $this->mockTicket
            ->shouldReceive('resolve')
            ->once();

        // Simulate save returning no events
        $this->mockEventStore
            ->shouldReceive('save')
            ->once()
            ->with($this->mockTicket)
            ->andReturn([]);

        // Expect dispatcher NOT to be called
        $this->mockEventDispatcher->shouldNotReceive('dispatch');

        // Act
        $this->handler->handle($command);

        // Assert
        $this->assertTrue(true);
    }

    /** @test */
    public function it_throws_exception_if_ticket_is_not_found(): void
    {
        // Arrange
        $ticketId = 'ticket-not-found-789';
        $command = new ResolveTicketCommand($ticketId);

        // Expect load to be called and throw exception
        $this->mockEventStore
            ->shouldReceive('load')
            ->once()
            ->with($ticketId)
            ->andThrow(new AggregateNotFoundException($ticketId, 'Ticket'));

        // Expect other methods (resolve, save, dispatch) not to be called
        $this->mockTicket->shouldNotReceive('resolve');
        $this->mockEventStore->shouldNotReceive('save');
        $this->mockEventDispatcher->shouldNotReceive('dispatch');

        // Assert
        $this->expectException(AggregateNotFoundException::class);

        // Act
        $this->handler->handle($command);
    }

    // Add test for InvalidTicketStateException if resolve() throws it
    /** @test */
    public function it_lets_invalid_state_exception_bubble_up(): void
    {
        // Arrange
        $ticketId = 'ticket-invalid-state-000';
        $command = new ResolveTicketCommand($ticketId);
        $exception = new InvalidTicketStateException("Ticket already resolved");

        $this->mockEventStore
            ->shouldReceive('load')
            ->once()
            ->with($ticketId)
            ->andReturn($this->mockTicket);

        // Expect resolve() to be called and throw the exception
        $this->mockTicket
            ->shouldReceive('resolve')
            ->once()
            ->andThrow($exception);

        // Expect save and dispatch not to be called
        $this->mockEventStore->shouldNotReceive('save');
        $this->mockEventDispatcher->shouldNotReceive('dispatch');

        // Assert
        $this->expectException(InvalidTicketStateException::class);
        $this->expectExceptionMessage("Ticket already resolved");

        // Act
        $this->handler->handle($command);
    }
}
