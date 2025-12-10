<?php

declare(strict_types=1);

namespace App\Exceptions;

class InvalidFinancerIdException extends ApplicationException
{
    /** @param array<string, mixed>|null $context */
    public function __construct(string $financerId, ?array $context = null)
    {
        parent::__construct(
            "Invalid financer ID: {$financerId}",
            array_merge($context ?? [], ['financer_id' => $financerId]),
            400
        );
    }
}
