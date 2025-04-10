<?php

namespace App\Application\Commands\ResolveTicket;

class ResolveTicketCommand
{
    public function __construct(
        public readonly string $ticketId
    ) {}
}
