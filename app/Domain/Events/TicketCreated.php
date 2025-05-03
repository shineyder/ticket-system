<?php

namespace App\Domain\Events;

use DateTimeImmutable;
use Illuminate\Support\Str;

class TicketCreated implements DomainEvent
{
    /** Identificador único para esta instância específica do evento */
    private readonly string $eventId;
    private readonly DateTimeImmutable $occurredOn;

    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly string $description,
        public readonly int $priority,
        ?DateTimeImmutable $occurredOn = null,
        ?string $eventId = null
    ) {
        // Se $occurredOn for null (novo evento), usa new DateTimeImmutable()
        // Se $occurredOn for fornecido (reconstituição), usa o valor fornecido
        $this->occurredOn = $occurredOn ?? new DateTimeImmutable();

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
        // Retorna apenas as propriedades que fazem parte do payload específico do evento
        return [
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority
        ];
    }
}
