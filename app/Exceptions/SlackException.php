<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class SlackException extends Exception
{
    public static function invalidChannel(string $channel): self
    {
        return new self("Invalid Slack channel: {$channel}");
    }

    public static function fileNotFound(string $path): self
    {
        return new self("File not found: {$path}");
    }

    public static function apiError(string $error): self
    {
        return new self("Slack API error: {$error}");
    }

    public static function connectionFailed(string $reason): self
    {
        return new self("Failed to connect to Slack: {$reason}");
    }

    public static function missingConfiguration(string $key): self
    {
        return new self("Missing Slack configuration: {$key}");
    }
}
