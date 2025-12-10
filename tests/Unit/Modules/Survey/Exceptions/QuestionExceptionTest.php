<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Survey\Exceptions;

use App\Exceptions\ApplicationException;
use App\Integrations\Survey\Exceptions\QuestionException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('survey')]
#[Group('question')]
#[Group('exception')]
class QuestionExceptionTest extends TestCase
{
    #[Test]
    public function it_creates_has_answers_exception(): void
    {
        $exception = QuestionException::hasAnswers();

        $this->assertEquals('Question has answers and cannot be modified', $exception->getMessage());
        $this->assertEquals(422, $exception->getHttpStatusCode());

        $context = $exception->getContext();
        $this->assertEquals('QuestionHasAnswers', $context['error']);
    }

    #[Test]
    public function it_creates_already_archived_exception(): void
    {
        $exception = QuestionException::alreadyArchived();

        $this->assertEquals('Question is already archived', $exception->getMessage());
        $this->assertEquals(422, $exception->getHttpStatusCode());

        $context = $exception->getContext();
        $this->assertEquals('QuestionAlreadyArchived', $context['error']);
    }

    #[Test]
    public function it_creates_not_archived_exception(): void
    {
        $exception = QuestionException::notArchived();

        $this->assertEquals('Question is not archived', $exception->getMessage());
        $this->assertEquals(422, $exception->getHttpStatusCode());

        $context = $exception->getContext();
        $this->assertEquals('QuestionNotArchived', $context['error']);
    }

    #[Test]
    public function it_creates_invalid_type_exception(): void
    {
        $type = 'invalid_type';

        $exception = QuestionException::invalidType($type);

        $this->assertEquals('Invalid question type', $exception->getMessage());
        $this->assertEquals(422, $exception->getHttpStatusCode());

        $context = $exception->getContext();
        $this->assertEquals('InvalidQuestionType', $context['error']);
        $this->assertEquals($type, $context['type']);
    }

    #[Test]
    public function it_creates_missing_options_exception(): void
    {
        $exception = QuestionException::missingOptions();

        $this->assertEquals('Question of this type requires options', $exception->getMessage());
        $this->assertEquals(422, $exception->getHttpStatusCode());

        $context = $exception->getContext();
        $this->assertEquals('MissingOptions', $context['error']);
    }

    #[Test]
    public function it_creates_cannot_delete_exception(): void
    {
        $exception = QuestionException::cannotDelete();

        $this->assertEquals('Question cannot be deleted', $exception->getMessage());
        $this->assertEquals(422, $exception->getHttpStatusCode());

        $context = $exception->getContext();
        $this->assertEquals('QuestionCannotDelete', $context['error']);
    }

    #[Test]
    public function it_creates_is_default_exception(): void
    {
        $exception = QuestionException::isDefault();

        $this->assertEquals('Default questions cannot be modified', $exception->getMessage());
        $this->assertEquals(422, $exception->getHttpStatusCode());

        $context = $exception->getContext();
        $this->assertEquals('QuestionIsDefault', $context['error']);
    }

    #[Test]
    public function it_extends_application_exception(): void
    {
        $exception = QuestionException::hasAnswers();

        $this->assertInstanceOf(ApplicationException::class, $exception);
    }
}
