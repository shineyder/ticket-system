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
     * Cria uma nova instância do DTO com o status e opcionalmente resolvedAt atualizados.
     * Mantém a imutabilidade.
     */
    public function withStatus(string $newStatus, ?DateTimeImmutable $resolvedAtDate = null): self
    {
        // Se o novo status for RESOLVED, usa a data fornecida, senão mantém a data atual (ou null)
        $newResolvedAt = ($newStatus === Status::RESOLVED)
            ? ($resolvedAtDate ?? $this->resolvedAt) // Usa a nova data se fornecida
            : $this->resolvedAt; // Mantém a data anterior para outros status

        if (
            $this->status === Status::RESOLVED &&
            $newStatus !== Status::RESOLVED
        ) {
            $newResolvedAt = null;
        }

        return new self(
            $this->id,
            $this->title,
            $this->description,
            $this->priority,
            $newStatus,
            $this->createdAt,
            $newResolvedAt // Nova data de resolução (ou a mesma)
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
