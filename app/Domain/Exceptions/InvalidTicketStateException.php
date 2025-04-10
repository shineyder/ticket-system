<?php

namespace App\Domain\Exceptions;

use Exception;

class InvalidTicketStateException extends Exception
{
    protected $message = 'Status do ticket inválido para essa operação.';
}
