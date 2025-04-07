<?php

namespace App\Domain\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    // ...

    public function render($request, Throwable $exception)
    {
        if ($exception instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
            return new JsonResponse(['error' => 'Recurso não encontrado.'], 404);
        }

        if ($exception instanceof \Illuminate\Validation\ValidationException) {
            return new JsonResponse(['error' => 'Erro de validação.', 'errors' => $exception->errors()], 422);
        }

        return parent::render($request, $exception);
    }
}
