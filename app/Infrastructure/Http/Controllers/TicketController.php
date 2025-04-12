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
use App\Infrastructure\Http\Requests\GetAllTicketsRequest;

class TicketController extends Controller
{
    public function store(
        CreateTicketRequest $request,
        CreateTicketHandler $createHandler
    ) {
        // A validação já foi feita automaticamente pelo Laravel!
        // Se a validação falhar, o Laravel retorna uma resposta de erro JSON

        // $request->validatedData() para obter apenas os dados validados
        $validated = $request->validatedData();

        $command = CreateTicketCommand::createWithUuid(
            $validated['title'],
            $validated['description'] ?? null,
            $validated['priority'] ?? 'low'
        );

        $ticketId = $createHandler->handle($command);

        // Retornar resposta de sucesso com o ID
        return response()->json([
            'message' => 'Ticket criado!',
            'ticket_id' => $ticketId
        ], 201);
    }

    public function resolve(
        string $id,
        ResolveTicketHandler $resolveHandler
    ) {
        $command = new ResolveTicketCommand($id);

        $resolveHandler->handle($command);

        return response()->json(['message' => 'Ticket resolvido!']); //Status 200 por padrão
    }

    public function show(
        string $id,
        GetTicketByIdHandler $getByIdHandler
    ) {
        $query = new GetTicketByIdQuery($id);

        // Retorna TicketDTO ou lança exception
        $ticket = $getByIdHandler->handle($query);

        return response()->json($ticket);
    }

    public function all(
        GetAllTicketsHandler $getAllTicketsHandler,
        GetAllTicketsRequest $request
    )
    {
        $validated = $request->validatedData();

        $query = new GetAllTicketsQuery(
            $validated['orderBy'],
            $validated['orderDirection']
        );

        // Retorna array de TicketDTO ou lança exception
        $ticket = $getAllTicketsHandler->handle($query);

        return response()->json($ticket);
    }
}
