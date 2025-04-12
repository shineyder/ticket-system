<?php

namespace App\Application\UseCases\Queries\GetAllTickets;

use App\Application\DTOs\TicketDTO;
use App\Application\UseCases\Queries\GetAllTickets\GetAllTicketsQuery;
use App\Domain\Interfaces\Repositories\TicketReadRepositoryInterface;

class GetAllTicketsHandler
{
    public function __construct(private TicketReadRepositoryInterface $readRepository)
    {
    }

    // Retorna um array de TicketDTO ou lança exception
    /**
     * @return TicketDTO[]
     */
    public function handle(GetAllTicketsQuery $query): ?array
    {
        return $this->readRepository->findAll(
            $query->orderBy,
            $query->orderDirection
        );
    }
}
