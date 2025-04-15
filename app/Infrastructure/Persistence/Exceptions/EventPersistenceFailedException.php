<?php

namespace App\Infrastructure\Persistence\Exceptions;

use Throwable;

/**
 * Exceção lançada quando ocorre uma falha ao persistir eventos no Event Store.
 */
class EventPersistenceFailedException extends \RuntimeException // Usar RuntimeException é comum para erros operacionais/infra
{
    public function __construct(string $aggregateId, ?Throwable $previous = null)
    {
        $message = "Falha ao salvar eventos para o agregado {$aggregateId}.";
        // Chama o construtor pai, passando a mensagem, código (0) e a exceção original
        parent::__construct($message, 0, $previous);
    }
}
