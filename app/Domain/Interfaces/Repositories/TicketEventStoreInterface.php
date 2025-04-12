<?php

namespace App\Domain\Interfaces\Repositories;

use App\Domain\Entities\Ticket;
use App\Domain\Events\DomainEvent;
use App\Domain\Exceptions\AggregateNotFoundException;

/**
 * Interface para o repositório de Event Store específico para o agregado Ticket.
 * Define o contrato para salvar e carregar agregados Ticket através de seus eventos.
 */
interface TicketEventStoreInterface
{
    /**
     * Persiste os eventos não commitados de um agregado Ticket.
     *
     * @param Ticket $ticket O agregado Ticket contendo novos eventos.
     * @return DomainEvent[] Retorna os eventos que foram efetivamente salvos.
     * @throws \Exception Em caso de falha na persistência.
     */
    public function save(Ticket $ticket): array;

    /**
     * Carrega um agregado Ticket a partir de seu histórico de eventos.
     *
     * @param string $aggregateId
     * @return Ticket O agregado Ticket reconstituído.
     * @throws AggregateNotFoundException Se nenhum evento for encontrado para o ID fornecido.
     * @throws \Exception Em caso de outras falhas ao carregar ou reconstituir.
     */
    public function load(string $aggregateId): Ticket;
}
