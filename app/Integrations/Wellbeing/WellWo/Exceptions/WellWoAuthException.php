<?php

declare(strict_types=1);

namespace App\Integrations\Wellbeing\WellWo\Exceptions;

use Exception;

class WellWoAuthException extends Exception
{
    public static function tokenNotConfigured(): self
    {
        return new self('WellWo authentication token is not configured');
    }

    public static function tokenInvalid(): self
    {
        return new self('WellWo authentication token is invalid');
    }

    public static function tokenExpired(): self
    {
        return new self('WellWo authentication token has expired');
    }
}
