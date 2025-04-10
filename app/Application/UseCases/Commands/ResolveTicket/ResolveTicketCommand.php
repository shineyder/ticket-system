<?php

namespace App\Application\UseCases\Commands\ResolveTicket;

class ResolveTicketCommand
{
    public function __construct(
        public readonly string $ticketId
    ) {}
}
