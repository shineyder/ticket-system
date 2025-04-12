<?php

namespace App\Domain\Interfaces\Repositories;

use App\Application\DTOs\TicketDTO;

/**
 * Interface para o repositório de leitura de Tickets (Read Models/Projections).
 */
interface TicketReadRepositoryInterface
{
    /**
     * Salva ou atualiza um DTO de Ticket no repositório de leitura.
     *
     * @param TicketDTO $ticketDto O DTO contendo os dados atualizados do ticket.
     * @return void
     */
    public function save(TicketDTO $ticketDto): void;

    /**
     * Encontra um TicketDTO pelo seu ID.
     *
     * @param string $ticketId O ID do ticket.
     * @return TicketDTO|null O DTO do ticket encontrado ou null se não existir.
     */
    public function findById(string $ticketId): ?TicketDTO;

    /**
     * Recupera todos os TicketDTOs, com opções de ordenação.
     *
     * @param string $orderBy Campo pelo qual ordenar.
     * @param string $orderDirection Direção da ordenação ('asc' ou 'desc').
     * @return TicketDTO[] Um array de DTOs de ticket.
     */
    public function findAll(
        string $orderBy = 'created_at',
        string $orderDirection = 'desc'
    ): array;
}
