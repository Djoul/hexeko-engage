<?php

namespace App\Exceptions;

use Exception;

class PermissionDeniedException extends Exception
{
    /*
        * Create a new instance.
        *
        * @param  string  $message
        * @param  int  $code
        * @return void
        */
    public function __construct(string $message = 'You do not have permission', int $code = 403)
    {
        parent::__construct($message, $code);
    }
}
