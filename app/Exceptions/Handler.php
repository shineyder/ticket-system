<?php

namespace App\Exceptions;

use App\Domain\Exceptions\AggregateNotFoundException;
use App\Domain\Exceptions\InvalidTicketStateException;
use App\Domain\Exceptions\TicketNotFoundException;
use App\Infrastructure\Persistence\Exceptions\PersistenceOperationFailedException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
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
     * Mapa de exceções customizadas para seus respectivos status HTTP e mensagens padrão.
     *
     * @var array<class-string, array{status: int, defaultMessage: string}>
     */
    protected array $customExceptionMap = [
        PersistenceOperationFailedException::class => [
            'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
            'defaultMessage' => 'Falha de infraestrutura.',
        ],
        TicketNotFoundException::class => [
            'status' => Response::HTTP_NOT_FOUND,
            'defaultMessage' => 'Ticket não encontrado.',
        ],
        AggregateNotFoundException::class => [ // Trata da mesma forma que TicketNotFound
            'status' => Response::HTTP_NOT_FOUND,
            'defaultMessage' => 'Recurso não encontrado.', // Mensagem mais genérica
        ],
        InvalidTicketStateException::class => [
            'status' => Response::HTTP_CONFLICT,
            'defaultMessage' => 'Operação não permitida devido ao estado atual do recurso.',
        ],
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register(): void
    {
        // --- Tratamento para Exceções Customizadas via Mapa ---

        foreach ($this->customExceptionMap as $exceptionClass => $config) {
            $this->renderable(function (Throwable $e, Request $request) use ($config, $exceptionClass) {
                // Garante que estamos tratando a exceção correta (necessário devido ao $e ser Throwable)
                if ($e instanceof $exceptionClass) {
                    return $this->renderCustomJsonException($e, $request, $config['status'], $config['defaultMessage']);
                }
                // Se não for a exceção esperada, retorna null para deixar outros handlers atuarem
                return null;
            });
        }

        // --- Tratamento Genérico ---
        // Handler genérico para Throwable que rode DEPOIS dos específicos
        // para garantir que erros inesperados também retornem JSON formatado na API.
        $this->renderable(function (Throwable $e, Request $request) {
            if ($request->expectsJson()) {
                // Em produção, NÃO exponha detalhes do erro ($e->getMessage())
                $message = config('app.debug') ? $e->getMessage() : 'Ocorreu um erro interno no servidor.';
                $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR; // 500

                return response()->json(['message' => $message], $statusCode);
            }
        });
    }

    /**
     * Método auxiliar para renderizar exceções customizadas como JSON.
     *
     * @param Throwable $e A exceção capturada.
     * @param Request $request A requisição HTTP.
     * @param int $statusCode O código de status HTTP a ser retornado.
     * @param string $defaultMessage A mensagem padrão caso a exceção não tenha uma.
     * @return JsonResponse|null Retorna JsonResponse se for requisição JSON, senão null.
     */
    private function renderCustomJsonException(Throwable $e, Request $request, int $statusCode, string $defaultMessage): ?JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(
                ['message' => $e->getMessage() ?: $defaultMessage],
                $statusCode
            );
        }
        return null;
    }
}
