<?php

namespace App\Domain\Events;

use DateTimeImmutable;

/**
 * Interface base para todos os eventos de domínio.
 */
interface DomainEvent
{
    /**
     * Retorna o ID do agregado ao qual este evento pertence.
     */
    public function getAggregateId(): string;

    /**
     * Retorna o momento em que o evento ocorreu.
     */
    public function getOccurredOn(): DateTimeImmutable;

    /**
     * Retorna um array com os dados do evento a serem serializados no payload.
     * Exclui metadados como aggregateId e occurredOn, que são tratados separadamente.
     *
     * @return array<string, mixed>
     */
    public function toPayload(): array;
}
