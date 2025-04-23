<?php

namespace App\Application\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Domain\Events\DomainEvent;

/**
 * Evento de aplicação disparado após eventos de domínio serem persistidos com sucesso.
 */
class DomainEventsPersisted implements ShouldQueue
{
    use Dispatchable, SerializesModels;

    /**
     * @var DomainEvent[] Os eventos de domínio que foram persistidos.
     */
    public readonly array $domainEvents;
    public readonly string $aggregateId;
    public readonly string $aggregateType;

    /**
     * Cria uma nova instância do evento.
     *
     * @param DomainEvent[] $domainEvents Array de eventos de domínio persistidos.
     * @param string $aggregateId ID do agregado relacionado.
     * @param string $aggregateType Tipo do agregado relacionado.
     */
    public function __construct(
        array $domainEvents,
        string $aggregateId,
        string $aggregateType
    )
    {
        $this->domainEvents = $domainEvents;
        $this->aggregateId = $aggregateId;
        $this->aggregateType = $aggregateType;
    }
}
