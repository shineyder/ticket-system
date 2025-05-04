<?php

namespace App\Infrastructure\Http\Resources;

use App\Application\DTOs\TicketDTO;
use App\Domain\ValueObjects\Status;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @mixin TicketDTO
 */
#[OA\Schema(
    schema: "TicketResource",
    title: "Ticket Resource",
    description: "Representação de um ticket na API",
    properties: [
        new OA\Property(property: "id", type: "string", format: "uuid"),
        new OA\Property(property: "title", type: "string"),
        new OA\Property(property: "description", type: "string"),
        new OA\Property(property: "priority", type: "string", enum: ['low','medium','high']),
        new OA\Property(property: "status", type: "string", enum: ['open','resolved']),
        new OA\Property(property: "createdAt", type: "string", format: "date-time"),
        new OA\Property(property: "resolvedAt", type: "string", format: "date-time", nullable: true),
        new OA\Property(property: "_links", ref: "#/components/schemas/TicketLinks")
    ]
)]
#[OA\Schema(
    schema: "TicketLinks",
    title: "Ticket HATEOAS Links",
    properties: [
        new OA\Property(property: "self", type: "object", properties: [new OA\Property(property: "href", type: "string", format: "url")]),
        new OA\Property(property: "collection", type: "object", properties: [new OA\Property(property: "href", type: "string", format: "url")]),
        new OA\Property(property: "resolve", type: "object", nullable: true, properties: [new OA\Property(property: "href", type: "string", format: "url"), new OA\Property(property: "method", type: "string", example: "PUT")])
    ],
    description: "Links HATEOAS relacionados a um ticket. O link 'resolve' só aparece para tickets abertos."
)]
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

