<?php

namespace App\Domain\Exceptions;

use Exception;

/**
 * Exceção lançada quando um agregado não pode ser encontrado no Event Store.
 */
class AggregateNotFoundException extends Exception
{
    public function __construct(
        string $aggregateId,
        string $aggregateType = 'Agregado',
        int $code = 0,
        ?Exception $previous = null
    )
    {
        $message = "$aggregateType com ID $aggregateId não encontrado.";
        parent::__construct($message, $code, $previous);
    }
}

