<?php

namespace App\Domain\Events;

use DateTimeImmutable;

class TicketStatusChanged implements DomainEvent
{
    private readonly DateTimeImmutable $occurredOn;

    public function __construct(
        public readonly string $id,
        public readonly string $status
    ) {
        $this->occurredOn = new DateTimeImmutable(); // Define o momento da criação do evento
    }

    /**
     * Retorna o ID do agregado (Ticket).
     */
    public function getAggregateId(): string
    {
        return $this->id;
    }

    /**
     * Retorna quando o evento ocorreu.
     */
    public function getOccurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
