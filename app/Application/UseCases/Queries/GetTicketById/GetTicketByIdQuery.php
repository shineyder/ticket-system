<?php

namespace App\Application\UseCases\Queries\GetTicketById;

class GetTicketByIdQuery
{
    public function __construct(
        public readonly string $ticketId
    ) {}
}
