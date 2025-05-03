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
use App\Infrastructure\Http\Requests\CreateTicketRequest;
use App\Infrastructure\Http\Resources\TicketResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use App\Infrastructure\Http\Requests\GetAllTicketsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class TicketController extends Controller
{
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

        $links = [
            'self' => ['href' => route('tickets.show', ['id' => $ticketId])],
        ];

        // Retornar resposta de sucesso com o ID
        return response()->json([
            'message' => 'Ticket criado!',
            'ticket_id' => $ticketId,
            '_links' => $links
        ], JsonResponse::HTTP_CREATED);
    }

    public function resolve(
        string $id,
        ResolveTicketHandler $resolveHandler
    ): JsonResponse
    {
        $command = new ResolveTicketCommand($id);

        $resolveHandler->handle($command);

        // Construir os links HATEOAS
        $links = [
            'self' => ['href' => route('tickets.show', ['id' => $id])],
        ];

        return response()->json([
            'message' => 'Ticket resolvido!',
            '_links' => $links
        ]); //Status 200 por padrão
    }

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
