<?php

namespace App\Domain\Interfaces\Repositories;

use App\Domain\Entities\Ticket;

interface TicketRepository
{
    public function findById(string $id): ?Ticket;

    /**
     * @param string $orderBy por padrão 'created_at'
     * @param string $orderDirection por padrão 'desc'
     * @return Ticket[]
     */
    public function findAll(
        string $orderBy = 'created_at',
        string $orderDirection = 'desc'
    ): array;

    public function save(Ticket $ticket): void;

    public function nextIdentity(): string; // Helper para gerar IDs
}
