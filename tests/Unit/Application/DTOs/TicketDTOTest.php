<?php

namespace Tests\Unit\Application\DTOs;

use App\Application\DTOs\TicketDTO;
use App\Domain\ValueObjects\Status;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class TicketDTOTest extends TestCase
{
    private TicketDTO $baseDto;
    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = new DateTimeImmutable();
        $this->baseDto = new TicketDTO(
            id: 'dto-123',
            title: 'Test DTO',
            description: 'DTO Description',
            priority: 'medium',
            status: Status::OPEN,
            createdAt: $this->now->modify('-1 day'),
            resolvedAt: null
        );
    }

    /** @test */
    public function with_status_updates_status_correctly(): void
    {
        $newDto = $this->baseDto->withStatus(Status::RESOLVED);

        $this->assertSame(Status::RESOLVED, $newDto->status);
        // Check other properties remain unchanged
        $this->assertSame($this->baseDto->id, $newDto->id);
        $this->assertSame($this->baseDto->title, $newDto->title);
        $this->assertSame($this->baseDto->createdAt, $newDto->createdAt);
    }

    /** @test */
    public function with_status_sets_resolved_at_when_status_becomes_resolved_and_date_is_provided(): void
    {
        $resolveDate = new DateTimeImmutable('2024-05-10 15:00:00');
        $newDto = $this->baseDto->withStatus(Status::RESOLVED, $resolveDate);

        $this->assertSame(Status::RESOLVED, $newDto->status);
        $this->assertEquals($resolveDate, $newDto->resolvedAt);
    }

    /** @test */
    public function with_status_returns_a_new_instance(): void
    {
        $newDto = $this->baseDto->withStatus(Status::RESOLVED);

        $this->assertNotSame($this->baseDto, $newDto);
    }
}
