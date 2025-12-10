<?php

declare(strict_types=1);

namespace App\Exceptions;

class FinancerIdRequiredException extends ApplicationException
{
    /** @param array<string, mixed>|null $context */
    public function __construct(?array $context = null)
    {
        parent::__construct(
            'Financer ID is required',
            $context ?? [],
            400
        );
    }
}
