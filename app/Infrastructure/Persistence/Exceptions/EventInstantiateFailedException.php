<?php

namespace App\Infrastructure\Persistence\Exceptions;

use Throwable;

/**
 * Exceção lançada quando ocorre uma falha ao instanciar eventos no Event Store.
 */
class EventInstantiateFailedException extends \RuntimeException // Usar RuntimeException é comum para erros operacionais/infra
{
    public function __construct(
        string $eventType,
        ?Throwable $previous = null
    )
    {
        $message = "Falha ao instanciar evento {$eventType}.";
        // Chama o construtor pai, passando a mensagem, código (0) e a exceção original
        parent::__construct($message, 0, $previous);
    }
}
