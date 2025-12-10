<?php

declare(strict_types=1);

namespace App\Integrations\Vouchers\Amilon\DTO;

final class RecoveryResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $message,
        public readonly string $newStatus,
        public readonly ?string $error = null,
    ) {}

    public static function success(string $message, string $newStatus): self
    {
        return new self(
            success: true,
            message: $message,
            newStatus: $newStatus,
            error: null
        );
    }

    public static function failure(string $message, string $newStatus, ?string $error = null): self
    {
        return new self(
            success: false,
            message: $message,
            newStatus: $newStatus,
            error: $error
        );
    }
}
