<?php

namespace App\Application\UseCases\Queries\GetTicketById;

use App\Application\DTOs\TicketDTO;
use App\Domain\Interfaces\Repositories\TicketReadRepositoryInterface;

class GetTicketByIdHandler
{
    public function __construct(private TicketReadRepositoryInterface $readRepository)
    {
    }

    // Retorna um TicketDTO ou lança exception
    public function handle(GetTicketByIdQuery $query): ?TicketDTO
    {
        $ticketDto = $this->readRepository->findById($query->ticketId);

        if (!$ticketDto) {
            throw new TicketNotFoundException("Ticket com ID {$query->ticketId} não encontrado.");
        }

        return $ticketDto;
    }
}
