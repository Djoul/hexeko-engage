<?php

namespace App\Exceptions\RoleManagement;

use Exception;

class UnauthorizedRoleAssignmentException extends Exception
{
    public function __construct(string $message = 'Unauthorized to assign this role')
    {
        parent::__construct($message);
    }
}
