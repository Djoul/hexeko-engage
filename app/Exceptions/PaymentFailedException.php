<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class PaymentFailedException extends Exception
{
    public function __construct(string $message = 'Payment processing failed')
    {
        parent::__construct($message);
    }
}
