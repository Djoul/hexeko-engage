<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Survey\Exceptions;

use App\Exceptions\ApplicationException;
use App\Integrations\Survey\Exceptions\SurveyException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('survey')]
#[Group('exception')]
class SurveyExceptionTest extends TestCase
{
    #[Test]
    public function it_creates_cannot_modify_exception(): void
    {
        $status = 'active';

        $exception = SurveyException::cannotModify($status);

        $this->assertEquals('Survey cannot be modified in its current state', $exception->getMessage());
        $this->assertEquals(422, $exception->getHttpStatusCode());

        $context = $exception->getContext();
        $this->assertEquals('SurveyCannotBeModified', $context['error']);
        $this->assertEquals($status, $context['status']);
    }

    #[Test]
    public function it_creates_already_archived_exception(): void
    {
        $exception = SurveyException::alreadyArchived();

        $this->assertEquals('Survey is already archived', $exception->getMessage());
        $this->assertEquals(422, $exception->getHttpStatusCode());

        $context = $exception->getContext();
        $this->assertEquals('SurveyAlreadyArchived', $context['error']);
    }

    #[Test]
    public function it_creates_no_questions_exception(): void
    {
        $exception = SurveyException::noQuestions();

        $this->assertEquals('Survey must have at least one question', $exception->getMessage());
        $this->assertEquals(422, $exception->getHttpStatusCode());

        $context = $exception->getContext();
        $this->assertEquals('SurveyNoQuestions', $context['error']);
    }

    #[Test]
    public function it_creates_invalid_date_range_exception(): void
    {
        $exception = SurveyException::invalidDateRange();

        $this->assertEquals('End date must be after start date', $exception->getMessage());
        $this->assertEquals(422, $exception->getHttpStatusCode());

        $context = $exception->getContext();
        $this->assertEquals('InvalidDateRange', $context['error']);
    }

    #[Test]
    public function it_creates_has_submissions_exception(): void
    {
        $exception = SurveyException::hasSubmissions();

        $this->assertEquals('Cannot delete survey with existing submissions', $exception->getMessage());
        $this->assertEquals(422, $exception->getHttpStatusCode());

        $context = $exception->getContext();
        $this->assertEquals('SurveyHasSubmissions', $context['error']);
    }

    #[Test]
    public function it_creates_not_active_exception(): void
    {
        $exception = SurveyException::notActive();

        $this->assertEquals('Survey is not active', $exception->getMessage());
        $this->assertEquals(422, $exception->getHttpStatusCode());

        $context = $exception->getContext();
        $this->assertEquals('SurveyNotActive', $context['error']);
    }

    #[Test]
    public function it_creates_not_draft_exception(): void
    {
        $exception = SurveyException::notDraft();

        $this->assertEquals('Survey must be in draft status to perform this action', $exception->getMessage());
        $this->assertEquals(422, $exception->getHttpStatusCode());

        $context = $exception->getContext();
        $this->assertEquals('SurveyNotDraft', $context['error']);
    }

    #[Test]
    public function it_extends_application_exception(): void
    {
        $exception = SurveyException::notActive();

        $this->assertInstanceOf(ApplicationException::class, $exception);
    }
}
