<?php

namespace App\Exceptions;

use App\Domain\Exceptions\AggregateNotFoundException;
use App\Domain\Exceptions\InvalidTicketStateException;
use App\Domain\Exceptions\TicketNotFoundException;
use App\Infrastructure\Persistence\Exceptions\PersistenceOperationFailedException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        // Exceções que NÃO serão logadas automaticamente.
        TicketNotFoundException::class,
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions. Empty in this project.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        //'current_password',
        //'password',
        //'password_confirmation',
    ];
    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register(): void
    {
        // --- Tratamento para Exceções Customizadas ---

        // Quando uma PersistenceOperationFailedException ocorrer...
        $this->renderable(function (PersistenceOperationFailedException $e, Request $request) {
            // Verifica se a requisição espera JSON
            if ($request->expectsJson()) {
                return response()->json(
                    ['message' => $e->getMessage() ?: 'Falha de infraestrutura.'], // Usa a msg da exceção ou uma padrão
                    Response::HTTP_INTERNAL_SERVER_ERROR // Retorna 500 Internal Server Error
                );
            }
        });

        // Quando uma TicketNotFoundException ocorrer...
        $this->renderable(function (TicketNotFoundException $e, Request $request) {
            // Verifica se a requisição espera JSON
            if ($request->expectsJson()) {
                return response()->json(
                    ['message' => $e->getMessage() ?: 'Ticket não encontrado.'],
                    Response::HTTP_NOT_FOUND // Retorna 404 Not Found
                );
            }
        });

        // Quando uma InvalidTicketStateException ocorrer...
        $this->renderable(function (InvalidTicketStateException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(
                    ['message' => $e->getMessage() ?: 'Operação não permitida devido ao estado atual do recurso.'],
                    Response::HTTP_CONFLICT // Retorna 409 Conflict
                );
            }
        });

        // Quando uma AggregateNotFoundException ocorrer...
        $this->renderable(function (AggregateNotFoundException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(
                    ['message' => $e->getMessage() ?: 'Ticket não encontrado.'],
                    Response::HTTP_NOT_FOUND // Retorna 404 Not Found
                );
            }
        });

        // --- Tratamento Genérico ---
        // Handler genérico para Throwable que rode DEPOIS dos específicos
        // para garantir que erros inesperados também retornem JSON formatado na API.
        $this->renderable(function (Throwable $e, Request $request) {
            if ($request->expectsJson()) {
                // Em produção, NÃO exponha detalhes do erro ($e->getMessage())
                $message = config('app.debug') ? $e->getMessage() : 'Ocorreu um erro interno no servidor.';
                $statusCode = $this->isHttpException($e) ? $e->getStatusCode() : Response::HTTP_INTERNAL_SERVER_ERROR; // 500

                return response()->json(['message' => $message], $statusCode);
            }
        });
    }
}
