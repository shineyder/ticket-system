<?php

namespace Tests\Unit\Domain\Entities;

use App\Domain\Entities\Ticket;
use App\Domain\Events\TicketCreated;
use App\Domain\Events\TicketResolved;
use App\Domain\Exceptions\InvalidTicketStateException;
use App\Domain\ValueObjects\Priority;
use App\Domain\ValueObjects\Status;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class TicketTest extends TestCase
{
    private string $ticketId;
    private string $title;
    private string $description;
    private string $priorityString;
    private int $priorityInt;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ticketId = 'ticket-123';
        $this->title = 'Test Ticket';
        $this->description = 'A description for the test ticket.';
        $this->priorityString = 'high';
        $this->priorityInt = Priority::HIGH; // Correspondente a 'high'
    }

    /** @test */
    public function it_can_be_created_successfully_and_generates_created_event(): void
    {
        // Act
        $ticket = Ticket::create(
            $this->ticketId,
            $this->title,
            $this->description,
            $this->priorityString
        );

        // Assert State
        $this->assertSame($this->ticketId, $ticket->getId());
        $this->assertSame($this->title, $ticket->getTitle());
        $this->assertSame($this->description, $ticket->getDescription());
        $this->assertNotNull($ticket->getPriority());
        $this->assertTrue($ticket->getPriority()->equals(new Priority($this->priorityInt)));
        $this->assertNotNull($ticket->getStatus());
        $this->assertTrue($ticket->getStatus()->equals(new Status(Status::OPEN)));
        $this->assertInstanceOf(DateTimeImmutable::class, $ticket->getCreatedAt());
        $this->assertNull($ticket->getResolvedAt());

        // Assert Event
        $events = $ticket->pullUncommittedEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(TicketCreated::class, $events[0]);

        /** @var TicketCreated $createdEvent */
        $createdEvent = $events[0];
        $this->assertSame($this->ticketId, $createdEvent->getAggregateId());
        $this->assertSame($this->title, $createdEvent->title);
        $this->assertSame($this->description, $createdEvent->description);
        $this->assertSame($this->priorityInt, $createdEvent->priority); // Verifica se o int foi passado
        $this->assertInstanceOf(DateTimeImmutable::class, $createdEvent->getOccurredOn());
    }

    /** @test */
    public function it_can_be_resolved_when_open_and_generates_resolved_event(): void
    {
        // Arrange: Create an open ticket
        $ticket = Ticket::create(
            $this->ticketId,
            $this->title,
            $this->description,
            $this->priorityString
        );
        $ticket->pullUncommittedEvents(); // Clear initial event

        // Act
        $ticket->resolve();

        // Assert State
        $this->assertTrue($ticket->getStatus()->equals(new Status(Status::RESOLVED)));
        $this->assertInstanceOf(DateTimeImmutable::class, $ticket->getResolvedAt());

        // Assert Event
        $events = $ticket->pullUncommittedEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(TicketResolved::class, $events[0]);

        /** @var TicketResolved $resolvedEvent */
        $resolvedEvent = $events[0];
        $this->assertSame($this->ticketId, $resolvedEvent->getAggregateId());
        $this->assertInstanceOf(DateTimeImmutable::class, $resolvedEvent->getOccurredOn());
    }

    /** @test */
    public function it_cannot_be_resolved_when_already_resolved(): void
    {
        // Arrange: Create and resolve a ticket using reconstitution for simplicity
        $createdEvent = new TicketCreated($this->ticketId, $this->title, $this->description, $this->priorityInt);
        // Simulate some time passing
        sleep(1); // Small delay to ensure different timestamps if needed, though not strictly necessary here
        $resolvedEvent = new TicketResolved($this->ticketId);

        $ticket = Ticket::reconstituteFromHistory($this->ticketId, [$createdEvent, $resolvedEvent]);

        // Assert Precondition
        $this->assertTrue($ticket->getStatus()->equals(new Status(Status::RESOLVED)));

        // Expect Exception on Act
        $this->expectException(InvalidTicketStateException::class);
        $this->expectExceptionMessage("O ticket {$this->ticketId} não pode ser resolvido pois não está aberto.");
        $this->expectExceptionCode(0);

        // Act
        $ticket->resolve();
    }

    /** @test */
    public function it_can_be_reconstituted_from_history_correctly(): void
    {
        // Arrange
        $id = 'reconstitute-test';
        $event1 = new TicketCreated($id, 'Recon Title', 'Recon Desc', Priority::LOW);
        // Simulate time passing
        $timeAfterCreation = $event1->getOccurredOn()->modify('+1 hour');
        $event2 = new TicketResolved($id, $timeAfterCreation); // Pass specific time to event if constructor allows

        // Act
        $ticket = Ticket::reconstituteFromHistory($id, [$event1, $event2]);

        // Assert State reflects BOTH events
        $this->assertSame($id, $ticket->getId());
        $this->assertSame('Recon Title', $ticket->getTitle());
        $this->assertTrue($ticket->getStatus()->equals(new Status(Status::RESOLVED)));
        $this->assertEquals($event1->getOccurredOn(), $ticket->getCreatedAt());
        $this->assertEquals($event2->getOccurredOn(), $ticket->getResolvedAt()); // Check if resolved time matches event

        // Assert No New Events
        $this->assertEmpty($ticket->pullUncommittedEvents());
    }

    /** @test */
    public function pull_uncommitted_events_clears_the_list(): void
    {
        // Arrange
        $ticket = Ticket::create($this->ticketId, $this->title, $this->description, $this->priorityString);
        $this->assertNotEmpty($ticket->pullUncommittedEvents(), 'Should have events initially');

        // Act & Assert
        $this->assertEmpty($ticket->pullUncommittedEvents(), 'Should be empty after pulling');

        // Arrange 2
        $ticket->resolve();
        $this->assertNotEmpty($ticket->pullUncommittedEvents(), 'Should have events after resolve');

        // Act & Assert 2
        $this->assertEmpty($ticket->pullUncommittedEvents(), 'Should be empty after pulling again');
    }
}
