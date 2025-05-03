<?php

namespace App\Domain\Events;

use DateTimeImmutable;
use Illuminate\Support\Str;

class TicketResolved implements DomainEvent
{
    /** Identificador único para esta instância específica do evento */
    private readonly string $eventId;
    private readonly DateTimeImmutable $occurredOn;

    public function __construct(
        public readonly string $id,
        ?DateTimeImmutable $occurredOn = null,
        ?string $eventId = null
    ) {
        // Se $occurredOn for null (novo evento), usa new DateTimeImmutable()
        // Se $occurredOn for fornecido (reconstituição), usa o valor fornecido
        $this->occurredOn = $occurredOn ?? new DateTimeImmutable(); // Define o momento da criação do evento

        // Gera um novo UUID se nenhum for fornecido
        $this->eventId = $eventId ?? Str::uuid()->toString();
    }

    /**
     * Retorna o ID desse evento especifico.
     */
    public function getEventId(): string
    {
        return $this->eventId;
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

    /**
     * Retorna os dados específicos deste evento para o payload.
     *
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [];
    }
}
