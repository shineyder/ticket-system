<?php

namespace Tests\Unit\Domain\Events;

use App\Domain\Events\TicketResolved;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class TicketResolvedTest extends TestCase
{
    private string $ticketId = 'evt-456';

    /** @test */
    public function it_can_be_instantiated_correctly_and_sets_occurred_on_automatically(): void
    {
        $before = new DateTimeImmutable();
        $event = new TicketResolved($this->ticketId);
        $after = new DateTimeImmutable();

        $this->assertSame($this->ticketId, $event->id);
        $this->assertInstanceOf(DateTimeImmutable::class, $event->getOccurredOn());
        $this->assertNotNull($event->getEventId());
        // Check if occurredOn is within the time range of creation
        $this->assertGreaterThanOrEqual($before, $event->getOccurredOn());
        $this->assertLessThanOrEqual($after, $event->getOccurredOn());
    }

    /** @test */
    public function it_accepts_a_specific_occurred_on_timestamp(): void
    {
        $specificTime = new DateTimeImmutable('2023-01-01 11:00:00');
        $event = new TicketResolved($this->ticketId, $specificTime);

        $this->assertEquals($specificTime, $event->getOccurredOn());
        $this->assertNotNull($event->getEventId());
    }

    /** @test */
    public function get_aggregate_id_returns_correct_id(): void
    {
        $event = new TicketResolved($this->ticketId);
        $this->assertSame($this->ticketId, $event->getAggregateId());
    }

    /** @test */
    public function to_payload_returns_an_empty_array(): void
    {
        $event = new TicketResolved($this->ticketId);
        $expectedPayload = [];

        $this->assertSame($expectedPayload, $event->toPayload());
    }

    /** @test */
    public function it_accepts_a_specific_event_id(): void
    {
        $specificEventId = 'test-event-id-123';
        $event = new TicketResolved(
            $this->ticketId,
            null,
            $specificEventId
        );

        $this->assertSame($specificEventId, $event->getEventId());
    }
}
