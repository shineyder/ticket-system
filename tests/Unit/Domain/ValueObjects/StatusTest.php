<?php

namespace Tests\Unit\Domain\ValueObjects;

use App\Domain\ValueObjects\Status;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class StatusTest extends TestCase
{
    /** @test */
    public function it_can_be_created_with_valid_string_values(): void
    {
        $open = new Status(Status::OPEN);
        $resolved = new Status(Status::RESOLVED);

        $this->assertSame(Status::OPEN, $open->value());
        $this->assertSame(Status::RESOLVED, $resolved->value());
    }

    /** @test */
    public function it_throws_exception_for_invalid_string_value(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Status inválido: pending");
        $this->expectExceptionCode(0);

        new Status('pending');//NOSONAR
    }

    /** @test */
    public function it_can_check_equality(): void
    {
        $status1 = new Status(Status::OPEN);
        $status2 = new Status(Status::OPEN);
        $status3 = new Status(Status::RESOLVED);

        $this->assertTrue($status1->equals($status2));
        $this->assertFalse($status1->equals($status3));
    }

    /** @test */
    public function it_returns_allowed_values(): void
    {
        $expected = [Status::OPEN, Status::RESOLVED];
        $this->assertSame($expected, Status::getAllowedValues());
    }
}
