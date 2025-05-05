<?php

namespace App\Application\DTOs;

use App\Domain\ValueObjects\Status;
use DateTimeImmutable;
use JsonSerializable;

/**
 * DTO para representar um Ticket em respostas de API e Read Models.
 */
readonly class TicketDTO implements JsonSerializable
{
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly string $description,
        public readonly string $priority,
        public readonly string $status,
        public ?DateTimeImmutable $createdAt = null,
        public ?DateTimeImmutable $resolvedAt = null
    ) {}

    /**
     * Cria uma nova instância do DTO marcada como resolvida, com a data de resolução fornecida.
     * Mantém a imutabilidade.
     */
    public function markAsResolved(DateTimeImmutable $resolvedAtDate): self
    {
        return new self(
            $this->id,
            $this->title,
            $this->description,
            $this->priority,
            Status::RESOLVED, // Status é sempre RESOLVED neste método
            $this->createdAt,
            $resolvedAtDate // Data de resolução fornecida
        );
    }

    /**
     * Especifica os dados que devem ser serializados para JSON.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority,
            'status' => $this->status,
            'createdAt' => $this->createdAt?->format(DateTimeImmutable::ATOM), // Formato ISO8601 (RFC3339)
            'resolvedAt' => $this->resolvedAt?->format(DateTimeImmutable::ATOM), // Formato ISO8601 se não for null
        ];
    }
}
