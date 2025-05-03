<?php

namespace App\Infrastructure\Http\Resources;

use App\Application\DTOs\TicketDTO;
use App\Domain\ValueObjects\Status;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use DateTimeImmutable;

/**
 * @mixin TicketDTO
 */
class TicketResource extends JsonResource
{
    /**
     * O DTO ou Entidade sendo encapsulado.
     *
     * @var TicketDTO
     */
    public $resource;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Dados principais do Ticket (vindos do DTO)
        $data = [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'description' => $this->resource->description,
            'status' => $this->resource->status, // Ex: 'OPEN', 'RESOLVED'
            'priority' => $this->resource->priority, // Ex: 0, 1, 2
            'created_at' => $this->resource->createdAt?->format(DateTimeImmutable::ATOM), // Formato ISO8601 (RFC3339),
            'resolved_at' => $this->resource->resolvedAt?->format(DateTimeImmutable::ATOM), // Formato ISO8601 (RFC3339),
        ];

        // Links HATEOAS
        $links = [
            'self' => ['href' => route('tickets.show', ['id' => $this->resource->id])],
            'collection' => ['href' => route('tickets.index')],
        ];

        // Adiciona o link para resolver APENAS se o ticket estiver aberto
        if ($this->resource->status === Status::OPEN) {
            $links['resolve'] = [
                'href' => route('tickets.resolve',
                ['id' => $this->resource->id]),
                'method' => 'PUT'
            ];
        }

        $data['_links'] = $links;

        return $data;
    }
}

