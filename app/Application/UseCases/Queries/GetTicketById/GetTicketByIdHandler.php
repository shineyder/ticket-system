<?php

namespace App\Application\UseCases\Queries\GetTicketById;

use App\Domain\Interfaces\Repositories\TicketRepository;
use App\Application\DTOs\TicketDTO;

class GetTicketByIdHandler
{
    public function __construct(private TicketRepository $ticketRepository)
    {
    }

    // Retorna um TicketDTO ou lança exception
    public function handle(GetTicketByIdQuery $query): ?TicketDTO
    {
        $ticket = $this->ticketRepository->findById($query->ticketId);

        if (!$ticket) {
            throw new TicketNotFoundException("Ticket com ID {$query->ticketId} não encontrado.");
        }

        return new TicketDTO(
            $ticket->id,
            $ticket->title,
            $ticket->description,
            $ticket->priority->value(),
            $ticket->status->value()
        );
    }
}
