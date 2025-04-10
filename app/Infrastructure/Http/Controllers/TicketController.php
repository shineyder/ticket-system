<?php

namespace App\Infrastructure\Http\Controllers;

use App\Application\Commands\CreateTicket\CreateTicketCommand;
use App\Application\Commands\CreateTicket\CreateTicketHandler;
use App\Application\Commands\ResolveTicket\ResolveTicketCommand;
use App\Application\Commands\ResolveTicket\ResolveTicketHandler;
use App\Application\Query\GetAllTickets\GetAllTicketsQuery;
use App\Application\Query\GetAllTickets\GetAllTicketsHandler;
use App\Application\Query\GetTicketById\GetTicketByIdQuery;
use App\Application\Query\GetTicketById\GetTicketByIdHandler;
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

        $command = new CreateTicketCommand(
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
