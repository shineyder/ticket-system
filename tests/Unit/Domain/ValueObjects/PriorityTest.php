<?php

namespace Tests\Unit\Domain\ValueObjects;

use App\Domain\ValueObjects\Priority;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PriorityTest extends TestCase
{
    /** @test */
    public function it_can_be_created_with_valid_integer_values(): void
    {
        $low = new Priority(Priority::LOW);
        $medium = new Priority(Priority::MEDIUM);
        $high = new Priority(Priority::HIGH);

        $this->assertSame(Priority::LOW, $low->value());
        $this->assertSame(Priority::MEDIUM, $medium->value());
        $this->assertSame(Priority::HIGH, $high->value());
    }

    /** @test */
    public function it_throws_exception_for_invalid_integer_value(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Prioridade inválida: 5");
        $this->expectExceptionCode(0);

        new Priority(5);//NOSONAR
    }

    /** @test */
    public function it_can_be_created_from_valid_string_values(): void
    {
        $low = Priority::fromString('low');
        $medium = Priority::fromString('medium');
        $high = Priority::fromString('high');

        $this->assertSame(Priority::LOW, $low->value());
        $this->assertSame(Priority::MEDIUM, $medium->value());
        $this->assertSame(Priority::HIGH, $high->value());
    }

    /** @test */
    public function it_throws_exception_for_invalid_string_value(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Prioridade inválida: urgent");
        $this->expectExceptionCode(0);

        Priority::fromString('urgent');
    }

    /** @test */
    public function it_can_check_equality(): void
    {
        $priority1 = new Priority(Priority::MEDIUM);
        $priority2 = Priority::fromString('medium');
        $priority3 = new Priority(Priority::HIGH);

        $this->assertTrue($priority1->equals($priority2));
        $this->assertFalse($priority1->equals($priority3));
        $this->assertFalse($priority2->equals($priority3));
    }

    /** @test */
    public function it_returns_allowed_string_values(): void
    {
        $expected = ['low', 'medium', 'high'];
        $this->assertSame($expected, Priority::getAllowedStringValues());
    }
}
