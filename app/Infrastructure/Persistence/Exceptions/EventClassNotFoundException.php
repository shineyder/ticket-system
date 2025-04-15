<?php

namespace App\Infrastructure\Persistence\Exceptions;

use Throwable;

/**
 * Exceção lançada quando a classe de evento não é encontrada.
 */
class EventClassNotFoundException extends \RuntimeException // Usar RuntimeException é comum para erros operacionais/infra
{
    public function __construct(string $eventClass)
    {
        $message = "Falha ao salvar eventos para o agregado {$eventClass}.";
        // Chama o construtor pai, passando a mensagem, código (0) e a exceção original
        parent::__construct($message, 0);
    }
}
