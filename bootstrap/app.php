<?php

use App\Domain\Exceptions\AggregateNotFoundException;
use App\Domain\Exceptions\InvalidTicketStateException;
use App\Domain\Exceptions\TicketNotFoundException;
use App\Infrastructure\Persistence\Exceptions\PersistenceOperationFailedException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Mapeia exceções específicas para status HTTP e mensagens padrão
        $exceptionMap = [
            PersistenceOperationFailedException::class => [
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'message' => 'Falha de infraestrutura.',
            ],
            TicketNotFoundException::class => [
                'status' => Response::HTTP_NOT_FOUND,
                'message' => 'Ticket não encontrado.',
            ],
            AggregateNotFoundException::class => [
                'status' => Response::HTTP_NOT_FOUND,
                'message' => 'Recurso não encontrado.',
            ],
            InvalidTicketStateException::class => [
                'status' => Response::HTTP_CONFLICT,
                'message' => 'Operação não permitida devido ao estado atual do recurso.',
            ],
            ValidationException::class => [
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'message' => 'Erro de validação.',
            ],
        ];

        $exceptions->renderable(function (Throwable $e, Request $request) use ($exceptionMap) {
            // Se não for uma requisição JSON ou for ValidationException, deixa o Laravel lidar
            if (!$request->expectsJson() || $e instanceof ValidationException) {
                return null; // Retornar null permite que o handler padrão do Laravel atue
            }

            // Tenta encontrar a exceção no mapa específico
            foreach ($exceptionMap as $exceptionClass => $config) {
                if ($e instanceof $exceptionClass) {
                    return response()->json(
                        ['message' => $e->getMessage() ?: $config['message']],
                        $config['status']
                    );
                }
            }

            // --- Tratamento Genérico (Fallback para JSON) ---
            // Se chegou até aqui, a exceção não estava no mapa específico.
            Log::error('Erro não tratado capturado pelo handler genérico (JSON):', ['exception' => $e]);

            // Em produção, NÃO exponha detalhes do erro ($e->getMessage())
            $message = config('app.debug') ? $e->getMessage() : 'Ocorreu um erro interno no servidor.';
            $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR; // 500

            return response()->json(['message' => $message], $statusCode);
        });
    })->create();
