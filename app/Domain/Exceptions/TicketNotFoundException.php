<?php

namespace App\Domain\Exceptions;

use Exception;

class TicketNotFoundException extends Exception
{
    protected $message = 'Ticket não encontrado.';
}
