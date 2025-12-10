<?php

declare(strict_types=1);

namespace App\Integrations\Survey\Exceptions;

use App\Exceptions\ApplicationException;

class QuestionNotFoundException extends ApplicationException
{
    /** @param array<string, mixed>|null $context */
    public function __construct(string $questionId, ?array $context = null)
    {
        parent::__construct(
            "Question with ID {$questionId} not found",
            array_merge($context ?? [], ['question_id' => $questionId]),
            404
        );
    }
}
