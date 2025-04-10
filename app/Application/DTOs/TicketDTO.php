<?php

namespace App\Application\DTO;

// Simples DTO para transferir dados para fora da camada de aplicação/domínio
class TicketDTO
{
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly string $description,
        public readonly string $priority,
        public readonly string $status
    ) {}
}
