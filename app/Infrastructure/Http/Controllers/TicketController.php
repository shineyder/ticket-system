<?php

namespace App\Infrastructure\Http\Controllers;

use App\Application\UseCases\Commands\CreateTicket\CreateTicketCommand;
use App\Application\UseCases\Commands\CreateTicket\CreateTicketHandler;
use App\Application\UseCases\Commands\ResolveTicket\ResolveTicketCommand;
use App\Application\UseCases\Commands\ResolveTicket\ResolveTicketHandler;
use App\Application\UseCases\Queries\GetAllTickets\GetAllTicketsQuery;
use App\Application\UseCases\Queries\GetAllTickets\GetAllTicketsHandler;
use App\Application\UseCases\Queries\GetTicketById\GetTicketByIdQuery;
use App\Application\UseCases\Queries\GetTicketById\GetTicketByIdHandler;
use App\Domain\ValueObjects\Status;
use App\Infrastructure\Http\Requests\CreateTicketRequest;
use App\Infrastructure\Http\Resources\TicketResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use App\Infrastructure\Http\Requests\GetAllTicketsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use OpenApi\Attributes as OA;

#[OA\Info(
    version: "1.0.0",
    title: "Ticket System API",
    description: "API para gerenciamento de tickets usando DDD, CQRS, ES com Laravel e MongoDB."
)]
#[OA\Server(
    url: "/api/v1",
    description: "Servidor Principal"
)]
class TicketController extends Controller
{
    #[OA\Post(
        path: "/ticket", // O caminho relativo à URL base definida no #[OA\Server]
        summary: "Cria um novo ticket",
        description: "Registra um novo ticket no sistema. A prioridade padrão é 'low'.",
        tags: ["Tickets"], // Agrupa endpoints na documentação
        requestBody: new OA\RequestBody(
            description: "Dados necessários para criar o ticket",
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/CreateTicketRequest")
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Ticket criado com sucesso",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Ticket criado!"),
                        new OA\Property(property: "ticket_id", type: "string", format: "uuid", example: "a1b2c3d4-e5f6-7890-1234-567890abcdef"),
                        new OA\Property(property: "_links", type: "object", ref: "#/components/schemas/TicketLinks")
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: "Erro de validação",
                content: new OA\JsonContent(ref: "#/components/schemas/ValidationError")
            )
        ]
    )]
    public function store(
        CreateTicketRequest $request,
        CreateTicketHandler $createHandler
    ): JsonResponse
    {
        // A validação já foi feita automaticamente pelo Laravel!
        // Se a validação falhar, o Laravel retorna uma resposta de erro JSON

        // Obter apenas os dados validados
        $validated = $request->validated();

        $command = CreateTicketCommand::createWithUuid(
            $validated['title'],
            $validated['description'],
            $validated['priority']
        );

        $ticketId = $createHandler->handle($command);

        $links = TicketResource::generateLinks($ticketId, Status::OPEN);

        // Retornar resposta de sucesso com o ID
        return response()->json([
            'message' => 'Ticket criado!',
            'ticket_id' => $ticketId,
            '_links' => $links
        ], JsonResponse::HTTP_CREATED);
    }

    #[OA\Put(
        path: "/ticket/{id}",
        summary: "Resolve um ticket existente",
        description: "Marca um ticket como resolvido com base no seu ID.",
        tags: ["Tickets"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, description: "ID do ticket a ser resolvido", schema: new OA\Schema(type: "string", format: "uuid"))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Ticket resolvido com sucesso",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string", example: "Ticket resolvido!"),
                        new OA\Property(property: "_links", type: "object", ref: "#/components/schemas/TicketLinks")
                    ]
                )
            ),
            new OA\Response(response: 404, description: "Ticket não encontrado")
        ]
    )]
    public function resolve(
        string $id,
        ResolveTicketHandler $resolveHandler
    ): JsonResponse
    {
        $command = new ResolveTicketCommand($id);

        $resolveHandler->handle($command);

        // Construir os links HATEOAS
        $links = TicketResource::generateLinks($id, Status::RESOLVED);

        return response()->json([
            'message' => 'Ticket resolvido!',
            '_links' => $links
        ]); //Status 200 por padrão
    }

    #[OA\Get(
        path: "/ticket/{id}",
        summary: "Busca um ticket pelo ID",
        description: "Retorna os detalhes de um ticket específico.",
        tags: ["Tickets"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, description: "ID do ticket a ser buscado", schema: new OA\Schema(type: "string", format: "uuid"))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Detalhes do ticket",
                content: new OA\JsonContent(ref: "#/components/schemas/TicketResource")
            ),
            new OA\Response(response: 404, description: "Ticket não encontrado")
        ]
    )]
    public function show(
        string $id,
        GetTicketByIdHandler $getByIdHandler
    ): TicketResource
    {
        $query = new GetTicketByIdQuery($id);

        // Retorna TicketDTO ou lança exception
        $ticket = $getByIdHandler->handle($query);

        return new TicketResource($ticket);
    }

    #[OA\Get(
        path: "/ticket",
        summary: "Lista todos os tickets",
        description: "Retorna uma lista paginada ou completa de tickets, com opções de ordenação.",
        tags: ["Tickets"],
        parameters: [
            new OA\Parameter(name: "orderBy", in: "query", required: false, description: "Campo para ordenação (ex: createdAt, priority)", schema: new OA\Schema(type: "string", default: "createdAt")),
            new OA\Parameter(name: "orderDirection", in: "query", required: false, description: "Direção da ordenação", schema: new OA\Schema(type: "string", enum: ["asc", "desc"], default: "desc"))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Lista de tickets",
                content: new OA\JsonContent(
                    type: "array",
                    items: new OA\Items(ref: "#/components/schemas/TicketResource")
                )
            ),
            new OA\Response(response: 422, description: "Erro de validação nos parâmetros de query", content: new OA\JsonContent(ref: "#/components/schemas/ValidationError"))
        ]
    )]
    public function all(
        GetAllTicketsHandler $getAllTicketsHandler,
        GetAllTicketsRequest $request
    ): AnonymousResourceCollection
    {
        $validated = $request->validated();

        $query = new GetAllTicketsQuery(
            $validated['orderBy'],
            $validated['orderDirection']
        );

        // Retorna array de TicketDTO ou lança exception
        $tickets = $getAllTicketsHandler->handle($query);

        return TicketResource::collection($tickets);
    }
}
