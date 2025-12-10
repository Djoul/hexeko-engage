<?php

declare(strict_types=1);

namespace App\Exceptions;

class FinancerAccessDeniedException extends ApplicationException
{
    /** @param array<string, mixed>|null $context */
    public function __construct(string $financerId, string $userId, ?array $context = null)
    {
        parent::__construct(
            "User {$userId} does not have access to financer {$financerId}",
            array_merge($context ?? [], [
                'financer_id' => $financerId,
                'user_id' => $userId,
            ]),
            403
        );
    }
}
