<?php

namespace App\Exceptions\RoleManagement;

use Exception;

class MaxRolesExceededException extends Exception
{
    public function __construct(string $message = 'User cannot have more than 2 roles')
    {
        parent::__construct($message);
    }
}
