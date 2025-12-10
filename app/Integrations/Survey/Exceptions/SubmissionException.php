<?php

declare(strict_types=1);

namespace App\Integrations\Survey\Exceptions;

use App\Exceptions\ApplicationException;

class SubmissionException extends ApplicationException
{
    public static function alreadyCompleted(): self
    {
        return new self(
            'Submission is already completed',
            ['error' => 'SubmissionAlreadyCompleted'],
            422
        );
    }

    public static function incompleteAnswers(int $answered, int $required): self
    {
        return new self(
            'All questions must be answered before completing submission',
            [
                'error' => 'IncompleteAnswers',
                'answered' => $answered,
                'required' => $required,
            ],
            422
        );
    }

    public static function surveyNotActive(): self
    {
        return new self(
            'Survey is not active',
            ['error' => 'SurveyNotActive'],
            422
        );
    }

    public static function alreadyExists(string $userId, string $surveyId): self
    {
        return new self(
            'User already has a submission for this survey',
            [
                'error' => 'SubmissionAlreadyExists',
                'user_id' => $userId,
                'survey_id' => $surveyId,
            ],
            409
        );
    }

    public static function notStarted(): self
    {
        return new self(
            'Submission has not been started',
            ['error' => 'SubmissionNotStarted'],
            422
        );
    }
}
