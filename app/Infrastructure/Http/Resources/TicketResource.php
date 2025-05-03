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
     * O DTO sendo encapsulado.
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
        $data = $this->resource->jsonSerialize();

        // Links HATEOAS
        $links = self::generateLinks($this->resource->id, $this->resource->status);

        $data['_links'] = $links;

        return $data;
    }

    /**
     * Gera os links HATEOAS para um ticket com base no seu ID e status.
     *
     * @param string $ticketId O ID do ticket.
     * @param string $ticketStatus O status atual do ticket (ex: Status::OPEN).
     * @return array<string, array<string, string>> Os links HATEOAS.
     */
    public static function generateLinks(string $ticketId, string $ticketStatus): array
    {
        $links = [
            'self' => ['href' => route('tickets.show', ['id' => $ticketId])],
            'collection' => ['href' => route('tickets.index')],
        ];

        // Adiciona o link para resolver APENAS se o ticket estiver aberto
        if ($ticketStatus === Status::OPEN) {
            $links['resolve'] = ['href' => route('tickets.resolve', ['id' => $ticketId]), 'method' => 'PUT'];
        }

        return $links;
    }
}

