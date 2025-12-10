<?php

declare(strict_types=1);

namespace App\Integrations\Vouchers\Amilon\Exceptions;

use Exception;

class AmilonOrderErrorException extends Exception
{
    public function __construct(
        string $message = 'Amilon order error',
        int $code = 0,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
