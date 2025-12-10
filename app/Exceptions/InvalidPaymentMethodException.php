<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class InvalidPaymentMethodException extends Exception
{
    public function __construct(string $message = 'Invalid payment method')
    {
        parent::__construct($message);
    }
}
