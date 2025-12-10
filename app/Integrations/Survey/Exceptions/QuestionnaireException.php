<?php

declare(strict_types=1);

namespace App\Integrations\Survey\Exceptions;

use App\Exceptions\ApplicationException;

class QuestionnaireException extends ApplicationException
{
    public static function alreadyArchived(): self
    {
        return new self(
            'Questionnaire is already archived',
            ['error' => 'QuestionnaireAlreadyArchived'],
            422
        );
    }

    public static function notArchived(): self
    {
        return new self(
            'Questionnaire is not archived',
            ['error' => 'QuestionnaireNotArchived'],
            422
        );
    }

    public static function noQuestions(): self
    {
        return new self(
            'Questionnaire must have at least one question',
            ['error' => 'QuestionnaireNoQuestions'],
            422
        );
    }

    public static function invalidType(string $type): self
    {
        return new self(
            'Invalid questionnaire type',
            ['error' => 'InvalidQuestionnaireType', 'type' => $type],
            422
        );
    }

    public static function isDefault(): self
    {
        return new self(
            'Default questionnaires cannot be modified',
            ['error' => 'QuestionnaireIsDefault'],
            422
        );
    }

    public static function cannotDelete(): self
    {
        return new self(
            'Questionnaire cannot be deleted',
            ['error' => 'QuestionnaireCannotDelete'],
            422
        );
    }
}
