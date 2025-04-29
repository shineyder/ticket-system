<?php

namespace Tests\Unit\Domain\Exceptions;

use App\Domain\Exceptions\AggregateNotFoundException;
use PHPUnit\Framework\TestCase;

class AggregateNotFoundExceptionTest extends TestCase
{
    /** @test */
    public function it_creates_correct_message_with_default_type(): void
    {
        $aggregateId = 'agg-123';
        $exception = new AggregateNotFoundException($aggregateId);

        $expectedMessage = "Agregado com ID $aggregateId não encontrado.";
        $this->assertSame($expectedMessage, $exception->getMessage());
    }

    /** @test */
    public function it_creates_correct_message_with_specific_type(): void
    {
        $aggregateId = 'ticket-xyz';
        $aggregateType = 'Ticket';
        $exception = new AggregateNotFoundException($aggregateId, $aggregateType);

        $expectedMessage = "$aggregateType com ID $aggregateId não encontrado.";
        $this->assertSame($expectedMessage, $exception->getMessage());
    }
}
