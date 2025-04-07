<?php

namespace App\Domain\Exceptions;

use Exception;

class TicketCreationException extends Exception
{
    protected $message = 'Erro ao criar o ticket.';
}
