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
}
