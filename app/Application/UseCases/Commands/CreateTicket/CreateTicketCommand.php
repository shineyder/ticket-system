<?php

namespace App\Application\UseCases\Commands\CreateTicket;

use Illuminate\Support\Str;

class CreateTicketCommand
{
    public function __construct(
        public readonly string $title,
        public readonly string $description,
        public readonly string $priority,
        public readonly string $id
    ) {}

    public static function createWithUuid(
        string $title,
        string $description,
        string $priority
    ): self {
        return new self($title, $description, $priority, Str::uuid()->toString());
    }
}
