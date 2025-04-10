<?php

namespace App\Application\UseCases\Commands\ResolveTicket;

use App\Domain\Exceptions\TicketNotFoundException;
use App\Domain\Interfaces\Repositories\TicketRepository;
use Illuminate\Contracts\Events\Dispatcher;

class ResolveTicketHandler
{
    public function __construct(
        private TicketRepository $ticketRepository,
        private Dispatcher $eventDispatcher
    ) {}

    public function handle(ResolveTicketCommand $command): void
    {
        $ticket = $this->ticketRepository->findById($command->ticketId);

        if (!$ticket) {
            throw new TicketNotFoundException("Ticket com ID {$command->ticketId} não encontrado.");
        }

        // Chama o método de domínio para alterar o estado
        $ticket->resolve();

        // Persiste a alteração
        $this->ticketRepository->save($ticket);

        // Libera e despacha os eventos
        $events = $ticket->releaseEvents();
        foreach ($events as $event) {
            // Use o dispatcher de eventos do Laravel
            $this->eventDispatcher->dispatch($event);
        }

        // Commands geralmente não retornam dados, apenas indicam sucesso/falha (via exceção)
    }
}
