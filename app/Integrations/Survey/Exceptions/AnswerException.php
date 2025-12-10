<?php

declare(strict_types=1);

namespace App\Integrations\Survey\Exceptions;

use App\Exceptions\ApplicationException;

class AnswerException extends ApplicationException
{
    public static function alreadyAnswered(string $questionId, string $submissionId): self
    {
        return new self(
            'You have already answered this question',
            [
                'error' => 'AlreadyAnswered',
                'question_id' => $questionId,
                'submission_id' => $submissionId,
            ],
            409 // 409 Conflict
        );
    }

    public static function submissionCompleted(): self
    {
        return new self(
            'Cannot modify answer for a completed submission',
            ['error' => 'SubmissionCompleted'],
            422
        );
    }

    public static function invalidQuestionType(string $questionType, string $answerType): self
    {
        return new self(
            'Answer type does not match question type',
            [
                'error' => 'InvalidQuestionType',
                'question_type' => $questionType,
                'answer_type' => $answerType,
            ],
            422
        );
    }

    public static function questionNotInSurvey(string $questionId, string $surveyId): self
    {
        return new self(
            'Question does not belong to this survey',
            [
                'error' => 'QuestionNotInSurvey',
                'question_id' => $questionId,
                'survey_id' => $surveyId,
            ],
            422
        );
    }

    public static function requiredAnswer(): self
    {
        return new self(
            'This question requires an answer',
            ['error' => 'RequiredAnswer'],
            422
        );
    }
}
