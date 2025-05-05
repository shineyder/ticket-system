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
    public function mark_as_resolved_creates_new_resolved_dto_correctly(): void
    {
        // Arrange
        $resolveDate = new DateTimeImmutable('2024-05-10 15:00:00');

        // Act
        $newDto = $this->baseDto->markAsResolved($resolveDate);

        // Assert - Immutability (New Instance)
        $this->assertNotSame($this->baseDto, $newDto, 'Should return a new instance.');

        // Assert - Correct State
        $this->assertSame(Status::RESOLVED, $newDto->status);
        $this->assertEquals($resolveDate, $newDto->resolvedAt);

        // Assert - Unchanged Properties
        $this->assertSame($this->baseDto->id, $newDto->id);
        $this->assertSame($this->baseDto->title, $newDto->title);
        $this->assertSame($this->baseDto->description, $newDto->description);
        $this->assertSame($this->baseDto->priority, $newDto->priority);
        $this->assertSame($this->baseDto->createdAt, $newDto->createdAt);
    }
}
