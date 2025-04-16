<?php

namespace App\Infrastructure\Persistence\Exceptions;

use Exception;
use Throwable;

class MongoConnectionException extends Exception
{
    public function __construct(
        string $errorMessage,
        int $code = 0,
        ?Throwable $previous = null
    )
    {
        $message = "Erro ao conectar ao banco de dados MongoDB: $errorMessage";
        parent::__construct($message, $code, $previous);
    }
}
