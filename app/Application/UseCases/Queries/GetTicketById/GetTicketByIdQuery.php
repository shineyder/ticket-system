<?php

namespace App\Application\Query\GetTicketById;

class GetTicketByIdQuery
{
    public function __construct(
        public readonly string $ticketId
    ) {}
}
