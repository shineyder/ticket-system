<?php

namespace App\Application\Commands\CreateTicket;

class CreateTicketCommand
{
    public function __construct(
        public readonly string $title,
        public readonly string $description,
        public readonly string $priority
    ) {}
}
