<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Survey\Exceptions;

use App\Exceptions\ApplicationException;
use App\Integrations\Survey\Exceptions\SubmissionException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('survey')]
#[Group('submission')]
#[Group('exception')]
class SubmissionExceptionTest extends TestCase
{
    #[Test]
    public function it_creates_already_completed_exception(): void
    {
        $exception = SubmissionException::alreadyCompleted();

        $this->assertEquals('Submission is already completed', $exception->getMessage());
        $this->assertEquals(422, $exception->getHttpStatusCode());

        $context = $exception->getContext();
        $this->assertEquals('SubmissionAlreadyCompleted', $context['error']);
    }

    #[Test]
    public function it_creates_incomplete_answers_exception(): void
    {
        $answered = 5;
        $required = 10;

        $exception = SubmissionException::incompleteAnswers($answered, $required);

        $this->assertEquals('All questions must be answered before completing submission', $exception->getMessage());
        $this->assertEquals(422, $exception->getHttpStatusCode());

        $context = $exception->getContext();
        $this->assertEquals('IncompleteAnswers', $context['error']);
        $this->assertEquals($answered, $context['answered']);
        $this->assertEquals($required, $context['required']);
    }

    #[Test]
    public function it_creates_survey_not_active_exception(): void
    {
        $exception = SubmissionException::surveyNotActive();

        $this->assertEquals('Survey is not active', $exception->getMessage());
        $this->assertEquals(422, $exception->getHttpStatusCode());

        $context = $exception->getContext();
        $this->assertEquals('SurveyNotActive', $context['error']);
    }

    #[Test]
    public function it_creates_already_exists_exception(): void
    {
        $userId = 'user-123';
        $surveyId = 'survey-456';

        $exception = SubmissionException::alreadyExists($userId, $surveyId);

        $this->assertEquals('User already has a submission for this survey', $exception->getMessage());
        $this->assertEquals(409, $exception->getHttpStatusCode());

        $context = $exception->getContext();
        $this->assertEquals('SubmissionAlreadyExists', $context['error']);
        $this->assertEquals($userId, $context['user_id']);
        $this->assertEquals($surveyId, $context['survey_id']);
    }

    #[Test]
    public function it_creates_not_started_exception(): void
    {
        $exception = SubmissionException::notStarted();

        $this->assertEquals('Submission has not been started', $exception->getMessage());
        $this->assertEquals(422, $exception->getHttpStatusCode());

        $context = $exception->getContext();
        $this->assertEquals('SubmissionNotStarted', $context['error']);
    }

    #[Test]
    public function it_extends_application_exception(): void
    {
        $exception = SubmissionException::alreadyCompleted();

        $this->assertInstanceOf(ApplicationException::class, $exception);
    }
}
