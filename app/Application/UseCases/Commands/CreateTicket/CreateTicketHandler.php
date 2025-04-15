<?php

namespace App\Application\UseCases\Commands\CreateTicket;

use App\Application\Events\DomainEventsPersisted;
use App\Domain\Entities\Ticket;
use App\Domain\Interfaces\Repositories\TicketEventStoreInterface;
use Illuminate\Contracts\Events\Dispatcher;

class CreateTicketHandler
{
    public function __construct(
        private TicketEventStoreInterface $eventStore,
        private Dispatcher $eventDispatcher
    ) {}

     /**
     * Manipula o comando para criar um novo Ticket usando Event Sourcing.
     *
     * @param CreateTicketCommand $command Dados para criação do ticket.
     * @return string O ID do ticket criado.
     * @throws \Exception Se ocorrer erro ao salvar os eventos.
     */
    public function handle(CreateTicketCommand $command): string
    {
        $id = $command->id;

        // Cria o agregado. O evento TicketCreated será disparado e aplicado aqui dentro.
        $ticket = Ticket::create(
            $id,
            $command->title,
            $command->description,
            $command->priority
        );

        // Persistência via Event Store e retorno dos eventos salvos
        $savedEvents = $this->eventStore->save($ticket);

        // Dispara o evento de aplicação se houver eventos salvos
        if (!empty($savedEvents)) {
            $this->eventDispatcher->dispatch(
                new DomainEventsPersisted($savedEvents, $ticket->getId(), 'Ticket')
            );
        }

        return $id;
    }
}
