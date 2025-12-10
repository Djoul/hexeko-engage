<?php

declare(strict_types=1);

namespace App\Integrations\Survey\Exceptions;

use App\Exceptions\ApplicationException;

class SurveyException extends ApplicationException
{
    public static function cannotModify(string $status): self
    {
        return new self(
            'Survey cannot be modified in its current state',
            ['error' => 'SurveyCannotBeModified', 'status' => $status],
            422
        );
    }

    public static function alreadyArchived(): self
    {
        return new self(
            'Survey is already archived',
            ['error' => 'SurveyAlreadyArchived'],
            422
        );
    }

    public static function noQuestions(): self
    {
        return new self(
            'Survey must have at least one question',
            ['error' => 'SurveyNoQuestions'],
            422
        );
    }

    public static function invalidDateRange(): self
    {
        return new self(
            'End date must be after start date',
            ['error' => 'InvalidDateRange'],
            422
        );
    }

    public static function hasSubmissions(): self
    {
        return new self(
            'Cannot delete survey with existing submissions',
            ['error' => 'SurveyHasSubmissions'],
            422
        );
    }

    public static function notActive(): self
    {
        return new self(
            'Survey is not active',
            ['error' => 'SurveyNotActive'],
            422
        );
    }

    public static function notDraft(): self
    {
        return new self(
            'Survey must be in draft status to perform this action',
            ['error' => 'SurveyNotDraft'],
            422
        );
    }
}
