<?php

namespace Tests\Unit\Application\UseCases\Commands\CreateTicket;

use App\Application\Events\DomainEventsPersisted;
use App\Application\UseCases\Commands\CreateTicket\CreateTicketCommand;
use App\Application\UseCases\Commands\CreateTicket\CreateTicketHandler;
use App\Domain\Entities\Ticket;
use App\Domain\Events\TicketCreated;
use App\Domain\Interfaces\Repositories\TicketEventStoreInterface;
use Illuminate\Contracts\Events\Dispatcher;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class CreateTicketHandlerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private Mockery\MockInterface|TicketEventStoreInterface $mockEventStore;
    private Mockery\MockInterface|Dispatcher $mockEventDispatcher;
    private CreateTicketHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockEventStore = Mockery::mock(TicketEventStoreInterface::class);
        $this->mockEventDispatcher = Mockery::mock(Dispatcher::class);
        $this->handler = new CreateTicketHandler($this->mockEventStore, $this->mockEventDispatcher);
    }

    /** @test */
    public function it_handles_create_ticket_command_saves_events_and_dispatches_persisted_event(): void
    {
        // Arrange
        $command = new CreateTicketCommand(
            title: 'New Ticket',
            description: 'Description here',
            priority: 'high',
            id: 'ticket-abc'
        );

        // Mocking the event that Ticket::create would generate and save
        $mockEvent = Mockery::mock(TicketCreated::class);
        $mockEvents = [$mockEvent];

        // Expect event store save to be called once with a Ticket instance
        // We don't need to mock Ticket::create itself, just the result of saving it.
        $this->mockEventStore
            ->shouldReceive('save')
            ->once()
            ->with(Mockery::type(Ticket::class)) // Assert that a Ticket object is passed
            ->andReturn($mockEvents); // Simulate that save returned the events

        // Expect event dispatcher to be called once with DomainEventsPersisted
        $this->mockEventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::on(function ($event) use ($mockEvents, $command) {
                return $event instanceof DomainEventsPersisted &&
                       $event->domainEvents === $mockEvents &&
                       $event->aggregateId === $command->id &&
                       $event->aggregateType === 'Ticket';
            }));

        // Act
        $resultId = $this->handler->handle($command);

        // Assert
        $this->assertSame($command->id, $resultId);
    }

    /** @test */
    public function it_does_not_dispatch_event_if_no_events_are_saved(): void
    {
        // Arrange
        $command = new CreateTicketCommand('Title', 'Desc', 'low', 'ticket-xyz');

        // Simulate event store save returning an empty array
        $this->mockEventStore
            ->shouldReceive('save')
            ->once()
            ->with(Mockery::type(Ticket::class))
            ->andReturn([]); // No events saved

        // Expect event dispatcher NOT to be called
        $this->mockEventDispatcher->shouldNotReceive('dispatch');

        // Act
        $resultId = $this->handler->handle($command);

        // Assert
        $this->assertSame($command->id, $resultId);
    }
}
