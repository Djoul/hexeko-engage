<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Survey\Exceptions;

use App\Exceptions\ApplicationException;
use App\Integrations\Survey\Exceptions\QuestionnaireException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('survey')]
#[Group('questionnaire')]
#[Group('exception')]
class QuestionnaireExceptionTest extends TestCase
{
    #[Test]
    public function it_creates_already_archived_exception(): void
    {
        $exception = QuestionnaireException::alreadyArchived();

        $this->assertEquals('Questionnaire is already archived', $exception->getMessage());
        $this->assertEquals(422, $exception->getHttpStatusCode());

        $context = $exception->getContext();
        $this->assertEquals('QuestionnaireAlreadyArchived', $context['error']);
    }

    #[Test]
    public function it_creates_not_archived_exception(): void
    {
        $exception = QuestionnaireException::notArchived();

        $this->assertEquals('Questionnaire is not archived', $exception->getMessage());
        $this->assertEquals(422, $exception->getHttpStatusCode());

        $context = $exception->getContext();
        $this->assertEquals('QuestionnaireNotArchived', $context['error']);
    }

    #[Test]
    public function it_creates_no_questions_exception(): void
    {
        $exception = QuestionnaireException::noQuestions();

        $this->assertEquals('Questionnaire must have at least one question', $exception->getMessage());
        $this->assertEquals(422, $exception->getHttpStatusCode());

        $context = $exception->getContext();
        $this->assertEquals('QuestionnaireNoQuestions', $context['error']);
    }

    #[Test]
    public function it_creates_invalid_type_exception(): void
    {
        $type = 'invalid_type';

        $exception = QuestionnaireException::invalidType($type);

        $this->assertEquals('Invalid questionnaire type', $exception->getMessage());
        $this->assertEquals(422, $exception->getHttpStatusCode());

        $context = $exception->getContext();
        $this->assertEquals('InvalidQuestionnaireType', $context['error']);
        $this->assertEquals($type, $context['type']);
    }

    #[Test]
    public function it_creates_is_default_exception(): void
    {
        $exception = QuestionnaireException::isDefault();

        $this->assertEquals('Default questionnaires cannot be modified', $exception->getMessage());
        $this->assertEquals(422, $exception->getHttpStatusCode());

        $context = $exception->getContext();
        $this->assertEquals('QuestionnaireIsDefault', $context['error']);
    }

    #[Test]
    public function it_creates_cannot_delete_exception(): void
    {
        $exception = QuestionnaireException::cannotDelete();

        $this->assertEquals('Questionnaire cannot be deleted', $exception->getMessage());
        $this->assertEquals(422, $exception->getHttpStatusCode());

        $context = $exception->getContext();
        $this->assertEquals('QuestionnaireCannotDelete', $context['error']);
    }

    #[Test]
    public function it_extends_application_exception(): void
    {
        $exception = QuestionnaireException::alreadyArchived();

        $this->assertInstanceOf(ApplicationException::class, $exception);
    }
}
