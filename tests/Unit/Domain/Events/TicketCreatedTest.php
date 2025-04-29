<?php

namespace Tests\Unit\Domain\Events;

use App\Domain\Events\TicketCreated;
use App\Domain\ValueObjects\Priority;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class TicketCreatedTest extends TestCase
{
    private string $ticketId = 'evt-123';
    private string $title = 'Event Test Title';
    private string $description = 'Event test description.';
    private int $priority = Priority::MEDIUM;

    /** @test */
    public function it_can_be_instantiated_correctly_and_sets_occurred_on_automatically(): void
    {
        $before = new DateTimeImmutable();
        $event = new TicketCreated(
            $this->ticketId,
            $this->title,
            $this->description,
            $this->priority
        );
        $after = new DateTimeImmutable();

        $this->assertSame($this->ticketId, $event->id);
        $this->assertSame($this->title, $event->title);
        $this->assertSame($this->description, $event->description);
        $this->assertSame($this->priority, $event->priority);
        $this->assertInstanceOf(DateTimeImmutable::class, $event->getOccurredOn());
        // Check if occurredOn is within the time range of creation
        $this->assertGreaterThanOrEqual($before, $event->getOccurredOn());
        $this->assertLessThanOrEqual($after, $event->getOccurredOn());
    }

    /** @test */
    public function it_accepts_a_specific_occurred_on_timestamp(): void
    {
        $specificTime = new DateTimeImmutable('2023-01-01 10:00:00');
        $event = new TicketCreated(
            $this->ticketId,
            $this->title,
            $this->description,
            $this->priority,
            $specificTime
        );

        $this->assertEquals($specificTime, $event->getOccurredOn());
    }

    /** @test */
    public function get_aggregate_id_returns_correct_id(): void
    {
        $event = new TicketCreated($this->ticketId, $this->title, $this->description, $this->priority);
        $this->assertSame($this->ticketId, $event->getAggregateId());
    }

    /** @test */
    public function to_payload_returns_correct_data_structure(): void
    {
        $event = new TicketCreated($this->ticketId, $this->title, $this->description, $this->priority);
        $expectedPayload = [
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority,
        ];

        $this->assertSame($expectedPayload, $event->toPayload());
    }
}
