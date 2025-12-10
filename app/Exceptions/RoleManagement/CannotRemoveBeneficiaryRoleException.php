<?php

namespace App\Exceptions\RoleManagement;

use Exception;

class CannotRemoveBeneficiaryRoleException extends Exception
{
    public function __construct(string $message = 'Cannot remove beneficiary role from user')
    {
        parent::__construct($message);
    }
}
