<?php

namespace App\Application\UseCases\Commands\ResolveTicket;

use App\Domain\Interfaces\Repositories\TicketEventStoreInterface;
use Illuminate\Contracts\Events\Dispatcher;

class ResolveTicketHandler
{
    public function __construct(
        private TicketEventStoreInterface $eventStore,
        private Dispatcher $eventDispatcher
    ) {}

    /**
     * Manipula o comando para resolver um Ticket usando Event Sourcing.
     *
     * @param ResolveTicketCommand $command Contém o ID do ticket a ser resolvido.
     * @return void
     * @throws AggregateNotFoundException Se o ticket não for encontrado.
     * @throws \App\Domain\Exceptions\InvalidTicketStateException Se a regra de negócio for violada no agregado.
     * @throws \Exception Se ocorrer erro ao salvar os eventos.
     */
    public function handle(ResolveTicketCommand $command): void
    {
        // Carregamento do Agregado via Event Store
        $ticket = $this->eventStore->load($command->ticketId);

        // Execução da Lógica de Negócio no Agregado
        $ticket->resolve();

        // Persistência via Event Store e retorno dos eventos salvos
        $savedEvents = $this->eventStore->save($ticket);

        // Dispara o evento de aplicação se houver eventos salvos
        if (!empty($savedEvents)) {
            $this->eventDispatcher->dispatch(
                new DomainEventsPersisted($savedEvents, $ticket->getId(), 'Ticket')
            );
        }

        // Commands geralmente não retornam dados, sucesso é implícito se nenhuma exceção foi lançada.
    }
}
