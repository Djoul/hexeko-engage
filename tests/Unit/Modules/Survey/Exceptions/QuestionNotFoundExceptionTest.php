<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Survey\Exceptions;

use App\Exceptions\ApplicationException;
use App\Integrations\Survey\Exceptions\QuestionNotFoundException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('survey')]
#[Group('question')]
#[Group('exception')]
class QuestionNotFoundExceptionTest extends TestCase
{
    #[Test]
    public function it_creates_exception_with_question_id(): void
    {
        $questionId = '123e4567-e89b-12d3-a456-426614174000';

        $exception = new QuestionNotFoundException($questionId);

        $this->assertEquals("Question with ID {$questionId} not found", $exception->getMessage());
        $this->assertEquals(404, $exception->getHttpStatusCode());

        $context = $exception->getContext();
        $this->assertEquals($questionId, $context['question_id']);
    }

    #[Test]
    public function it_creates_exception_with_additional_context(): void
    {
        $questionId = '123e4567-e89b-12d3-a456-426614174000';
        $additionalContext = [
            'survey_id' => '987fcdeb-51a2-43e1-b123-654321098765',
            'user_id' => 'user-123',
        ];

        $exception = new QuestionNotFoundException($questionId, $additionalContext);

        $this->assertEquals("Question with ID {$questionId} not found", $exception->getMessage());
        $this->assertEquals(404, $exception->getHttpStatusCode());

        $context = $exception->getContext();
        $this->assertEquals($questionId, $context['question_id']);
        $this->assertEquals($additionalContext['survey_id'], $context['survey_id']);
        $this->assertEquals($additionalContext['user_id'], $context['user_id']);
    }

    #[Test]
    public function it_merges_context_with_question_id(): void
    {
        $questionId = 'test-question-id';
        $additionalContext = ['extra' => 'data'];

        $exception = new QuestionNotFoundException($questionId, $additionalContext);

        $context = $exception->getContext();

        // Should have both question_id and additional context
        $this->assertArrayHasKey('question_id', $context);
        $this->assertArrayHasKey('extra', $context);
        $this->assertEquals($questionId, $context['question_id']);
        $this->assertEquals('data', $context['extra']);
    }

    #[Test]
    public function it_handles_null_context(): void
    {
        $questionId = '123e4567-e89b-12d3-a456-426614174000';

        $exception = new QuestionNotFoundException($questionId, null);

        $context = $exception->getContext();
        $this->assertIsArray($context);
        $this->assertEquals($questionId, $context['question_id']);
    }

    #[Test]
    public function it_extends_application_exception(): void
    {
        $exception = new QuestionNotFoundException('test-id');

        $this->assertInstanceOf(ApplicationException::class, $exception);
    }
}
