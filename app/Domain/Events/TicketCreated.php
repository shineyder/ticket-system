<?php

namespace App\Domain\Events;

use DateTimeImmutable;

class TicketCreated implements DomainEvent
{
    private readonly DateTimeImmutable $occurredOn;

    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly string $description,
        public readonly string $priority,
        ?DateTimeImmutable $occurredOn = null
    ) {
        // Se $occurredOn for null (novo evento), usa new DateTimeImmutable()
        // Se $occurredOn for fornecido (reconstituição), usa o valor fornecido
        $this->occurredOn = $occurredOn ?? new DateTimeImmutable();
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
        // Retorna apenas as propriedades que fazem parte do payload específico do evento
        return [
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority
        ];
    }
}
