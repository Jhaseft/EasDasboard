<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientFundsException extends RuntimeException
{
    public function __construct(string $message = 'Saldo insuficiente en la billetera.')
    {
        parent::__construct($message);
    }
}
