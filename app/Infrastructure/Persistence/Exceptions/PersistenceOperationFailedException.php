<?php

namespace App\Infrastructure\Persistence\Exceptions;

use RuntimeException;
use Throwable;

class PersistenceOperationFailedException extends RuntimeException
{
    public function __construct(
        string $message = "Falha na operação de persistência.",
        int $code = 0,
        ?Throwable $previous = null
    )
    {
        parent::__construct($message, $code, $previous);
    }
}
