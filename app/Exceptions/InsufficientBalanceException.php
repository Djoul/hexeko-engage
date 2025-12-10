<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class InsufficientBalanceException extends Exception
{
    public function __construct(string $message = 'Insufficient balance to complete this transaction')
    {
        parent::__construct($message);
    }
}
