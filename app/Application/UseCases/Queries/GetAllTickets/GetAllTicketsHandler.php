<?php

namespace App\Application\UseCases\Queries\GetAllTickets;

use App\Domain\Interfaces\Repositories\TicketRepository;
use App\Application\DTOs\TicketDTO;
use App\Application\UseCases\Queries\GetAllTickets\GetAllTicketsQuery;

class GetAllTicketsHandler
{
    public function __construct(private TicketRepository $ticketRepository)
    {
    }

    // Retorna um array de TicketDTO ou lança exception
    /**
     * @return TicketDTO[]
     */
    public function handle(GetAllTicketsQuery $query): ?array
    {
        $tickets = $this->ticketRepository->findAll(
            $query->orderBy,
            $query->orderDirection
        );

        $ticketDTOs = [];
        if($tickets){
            foreach ($tickets as $ticket) {
                $ticketDTO = new TicketDTO(
                    $ticket->id,
                    $ticket->title,
                    $ticket->description,
                    $ticket->priority->value(),
                    $ticket->status->value()
                );
                $ticketDTOs[] = $ticketDTO;
            }
        }

        return $ticketDTOs;
    }
}
