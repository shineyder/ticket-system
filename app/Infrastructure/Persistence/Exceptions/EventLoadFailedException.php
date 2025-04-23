<?php

namespace App\Infrastructure\Persistence\Exceptions;

use Throwable;

/**
 * Exceção lançada quando ocorre uma falha ao carregar eventos no Event Store.
 */
class EventLoadFailedException extends \RuntimeException // Usar RuntimeException é comum para erros operacionais/infra
{
    public function __construct(
        string $aggregateId,
        string $errorMessage,
        ?Throwable $previous = null
    )
    {
        $message = "Falha ao carregar agregado {$aggregateId}: ". $errorMessage;
        // Chama o construtor pai, passando a mensagem, código (0) e a exceção original
        parent::__construct($message, 0, $previous);
    }
}
