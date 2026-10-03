<?php

namespace App\Exceptions;

class InsufficientBalanceException extends CheckoutException
{
    public function __construct(string $message = 'Saldo tidak cukup. Silakan top-up terlebih dahulu.')
    {
        parent::__construct($message);
    }
}
