<?php

declare(strict_types=1);

namespace App\Integrations\Wellbeing\WellWo\Exceptions;

use Exception;
use Throwable;

class WellWoApiException extends Exception
{
    public static function apiError(string $message, int $statusCode = 0, ?Throwable $previous = null): self
    {
        return new self(
            "WellWo API error: {$message}",
            $statusCode,
            $previous
        );
    }

    public static function connectionFailed(string $url, ?Throwable $previous = null): self
    {
        return new self(
            "Failed to connect to WellWo API at: {$url}",
            0,
            $previous
        );
    }

    public static function invalidResponse(string $reason): self
    {
        return new self("Invalid response from WellWo API: {$reason}");
    }

    public static function rateLimitExceeded(): self
    {
        return new self('WellWo API rate limit exceeded', 429);
    }

    public static function timeout(string $url): self
    {
        return new self("Request timeout while calling WellWo API: {$url}", 408);
    }
}
