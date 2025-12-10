<?php

declare(strict_types=1);

namespace App\Integrations\Survey\Exceptions;

use App\Exceptions\ApplicationException;

class QuestionException extends ApplicationException
{
    public static function cannotModify(string $status): self
    {
        return new self(
            'Question cannot be modified in its current state',
            ['error' => 'QuestionCannotModify', 'status' => $status],
            422
        );
    }

    public static function hasAnswers(): self
    {
        return new self(
            'Question has answers and cannot be modified',
            ['error' => 'QuestionHasAnswers'],
            422
        );
    }

    public static function alreadyArchived(): self
    {
        return new self(
            'Question is already archived',
            ['error' => 'QuestionAlreadyArchived'],
            422
        );
    }

    public static function notArchived(): self
    {
        return new self(
            'Question is not archived',
            ['error' => 'QuestionNotArchived'],
            422
        );
    }

    public static function invalidType(string $type): self
    {
        return new self(
            'Invalid question type',
            ['error' => 'InvalidQuestionType', 'type' => $type],
            422
        );
    }

    public static function missingOptions(): self
    {
        return new self(
            'Question of this type requires options',
            ['error' => 'MissingOptions'],
            422
        );
    }

    public static function cannotDelete(): self
    {
        return new self(
            'Question cannot be deleted',
            ['error' => 'QuestionCannotDelete'],
            422
        );
    }

    public static function isDefault(): self
    {
        return new self(
            'Default questions cannot be modified',
            ['error' => 'QuestionIsDefault'],
            422
        );
    }
}
