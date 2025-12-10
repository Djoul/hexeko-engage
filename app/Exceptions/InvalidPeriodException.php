<?php

namespace App\Exceptions;

use Exception;

class InvalidPeriodException extends Exception
{
    public function __construct(string $message = 'Invalid period provided', int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
