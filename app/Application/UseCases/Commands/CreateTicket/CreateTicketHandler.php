<?php

namespace App\Application\Commands\CreateTicket;

use App\Domain\Entities\Ticket;
use App\Domain\Interfaces\Repositories\TicketRepository;
use App\Domain\ValueObjects\Priority;
use App\Domain\ValueObjects\Status;
use Illuminate\Contracts\Events\Dispatcher;

class CreateTicketHandler
{
    public function __construct(
        private TicketRepository $ticketRepository,
        private Dispatcher $eventDispatcher
    ) {}

    public function handle(CreateTicketCommand $command): string // Retorna o ID do ticket criado
    {
        $id = $this->ticketRepository->nextIdentity();
        $priority = Priority::fromString($command->priority);
        $status = new Status(Status::OPEN); // Status inicial

        // Cria a entidade. O evento TicketCreated será disparado aqui dentro.
        $ticket = new Ticket(
            $id,
            $command->title,
            $command->description,
            $priority,
            $status
        );

        // Persiste a entidade
        $this->ticketRepository->save($ticket);

        // Libera e despacha os eventos
        $events = $ticket->releaseEvents();
        foreach ($events as $event) {
            // Use o dispatcher de eventos do Laravel
            $this->eventDispatcher->dispatch($event);
        }

        return $id; // Retorna o ID para referência
    }
}
