<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Survey\Exceptions;

use App\Exceptions\ApplicationException;
use App\Integrations\Survey\Exceptions\AnswerException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('survey')]
#[Group('answer')]
#[Group('exception')]
class AnswerExceptionTest extends TestCase
{
    #[Test]
    public function it_creates_already_answered_exception(): void
    {
        $questionId = '123e4567-e89b-12d3-a456-426614174000';
        $submissionId = '987fcdeb-51a2-43e1-b123-654321098765';

        $exception = AnswerException::alreadyAnswered($questionId, $submissionId);

        $this->assertEquals('You have already answered this question', $exception->getMessage());
        $this->assertEquals(409, $exception->getHttpStatusCode());

        $context = $exception->getContext();
        $this->assertEquals('AlreadyAnswered', $context['error']);
        $this->assertEquals($questionId, $context['question_id']);
        $this->assertEquals($submissionId, $context['submission_id']);
    }

    #[Test]
    public function it_creates_submission_completed_exception(): void
    {
        $exception = AnswerException::submissionCompleted();

        $this->assertEquals('Cannot modify answer for a completed submission', $exception->getMessage());
        $this->assertEquals(422, $exception->getHttpStatusCode());

        $context = $exception->getContext();
        $this->assertEquals('SubmissionCompleted', $context['error']);
    }

    #[Test]
    public function it_creates_invalid_question_type_exception(): void
    {
        $questionType = 'scale';
        $answerType = 'text';

        $exception = AnswerException::invalidQuestionType($questionType, $answerType);

        $this->assertEquals('Answer type does not match question type', $exception->getMessage());
        $this->assertEquals(422, $exception->getHttpStatusCode());

        $context = $exception->getContext();
        $this->assertEquals('InvalidQuestionType', $context['error']);
        $this->assertEquals($questionType, $context['question_type']);
        $this->assertEquals($answerType, $context['answer_type']);
    }

    #[Test]
    public function it_creates_question_not_in_survey_exception(): void
    {
        $questionId = '123e4567-e89b-12d3-a456-426614174000';
        $surveyId = '987fcdeb-51a2-43e1-b123-654321098765';

        $exception = AnswerException::questionNotInSurvey($questionId, $surveyId);

        $this->assertEquals('Question does not belong to this survey', $exception->getMessage());
        $this->assertEquals(422, $exception->getHttpStatusCode());

        $context = $exception->getContext();
        $this->assertEquals('QuestionNotInSurvey', $context['error']);
        $this->assertEquals($questionId, $context['question_id']);
        $this->assertEquals($surveyId, $context['survey_id']);
    }

    #[Test]
    public function it_creates_required_answer_exception(): void
    {
        $exception = AnswerException::requiredAnswer();

        $this->assertEquals('This question requires an answer', $exception->getMessage());
        $this->assertEquals(422, $exception->getHttpStatusCode());

        $context = $exception->getContext();
        $this->assertEquals('RequiredAnswer', $context['error']);
    }

    #[Test]
    public function it_extends_application_exception(): void
    {
        $exception = AnswerException::requiredAnswer();

        $this->assertInstanceOf(ApplicationException::class, $exception);
    }
}
