<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Survey\Actions;

use App\Integrations\Survey\Actions\Questionnaire\LinkQuestionnaireQuestionAction;
use App\Integrations\Survey\Database\factories\QuestionFactory;
use App\Integrations\Survey\Database\factories\QuestionnaireFactory;
use App\Integrations\Survey\Exceptions\QuestionNotFoundException;
use App\Integrations\Survey\Models\Question;
use App\Integrations\Survey\Models\Questionnaire;
use Database\Factories\FinancerFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('survey')]
#[Group('question')]
class LinkQuestionnaireQuestionActionTest extends TestCase
{
    use DatabaseTransactions;

    private LinkQuestionnaireQuestionAction $action;

    private Questionnaire $questionnaire;

    private Question $question;

    protected function setUp(): void
    {
        parent::setUp();

        $financer = resolve(FinancerFactory::class)->create();
        $this->questionnaire = resolve(QuestionnaireFactory::class)->create(['financer_id' => $financer->id]);
        $this->question = resolve(QuestionFactory::class)->create(['financer_id' => $financer->id]);
        $this->action = new LinkQuestionnaireQuestionAction;

        Context::add('accessible_financers', [$financer->id]);
        Context::add('financer_id', $financer->id);
    }

    #[Test]
    public function it_can_link_new_questions_by_duplicating_them(): void
    {
        // Act
        $question = ['id' => $this->question->id];
        $result = $this->action->execute($this->questionnaire, ['questions' => [$question]]);

        // Assert
        $this->assertInstanceOf(Questionnaire::class, $result);

        // Verify the question was duplicated and linked
        $this->questionnaire->refresh();
        $linkedQuestions = $this->questionnaire->questions;

        $this->assertCount(1, $linkedQuestions);

        // The question should be duplicated (different ID but same content)
        $this->assertFalse($linkedQuestions->contains('id', $this->question->id));
        $this->assertTrue($linkedQuestions->contains('text', $this->question->text));

        // Verify the original question still exists
        $this->assertDatabaseHas('int_survey_questions', [
            'id' => $this->question->id,
        ]);
    }

    #[Test]
    public function it_can_link_multiple_new_questions_by_duplicating_them(): void
    {
        // Arrange
        $question2 = resolve(QuestionFactory::class)->create(['financer_id' => $this->questionnaire->financer_id]);
        $question3 = resolve(QuestionFactory::class)->create(['financer_id' => $this->questionnaire->financer_id]);

        // Act
        $questions = [
            ['id' => $this->question->id],
            ['id' => $question2->id],
            ['id' => $question3->id],
        ];
        $result = $this->action->execute($this->questionnaire, [
            'questions' => $questions,
        ]);

        // Assert
        $this->assertInstanceOf(Questionnaire::class, $result);

        // Verify all questions were duplicated and linked
        $this->questionnaire->refresh();
        $linkedQuestions = $this->questionnaire->questions;

        $this->assertCount(3, $linkedQuestions);

        // All questions should be duplicated (different IDs but same content)
        $this->assertFalse($linkedQuestions->contains('id', $this->question->id));
        $this->assertFalse($linkedQuestions->contains('id', $question2->id));
        $this->assertFalse($linkedQuestions->contains('id', $question3->id));

        $this->assertTrue($linkedQuestions->contains('text', $this->question->text));
        $this->assertTrue($linkedQuestions->contains('text', $question2->text));
        $this->assertTrue($linkedQuestions->contains('text', $question3->text));
    }

    #[Test]
    public function it_creates_new_duplicates_when_linking_same_original_question_multiple_times(): void
    {
        // This test verifies that linking the same original question multiple times
        // creates new duplicates each time (which is the current behavior)

        // Arrange - First link the question (this will duplicate it)
        $question = ['id' => $this->question->id];
        $this->action->execute($this->questionnaire, ['questions' => [$question]]);

        $this->questionnaire->refresh();
        $firstLinkedQuestion = $this->questionnaire->questions->first();

        // Act - Link the same original question again
        $result = $this->action->execute($this->questionnaire, ['questions' => [$question]]);

        // Assert
        $this->assertInstanceOf(Questionnaire::class, $result);

        // Verify we now have 2 questions linked (both duplicates of the original)
        $this->questionnaire->refresh();
        $linkedQuestions = $this->questionnaire->questions;

        $this->assertCount(2, $linkedQuestions);

        // Both questions should be duplicates (different IDs but same content)
        $this->assertFalse($linkedQuestions->contains('id', $this->question->id));
        $this->assertTrue($linkedQuestions->contains('text', $this->question->text));

        // The first duplicate should still be there
        $this->assertTrue($linkedQuestions->contains('id', $firstLinkedQuestion->id));

        // There should be exactly 2 questions with the same text
        $questionsWithSameText = $linkedQuestions->filter(fn ($q): bool => $q->text === $this->question->text);
        $this->assertCount(2, $questionsWithSameText);
    }

    #[Test]
    public function it_can_link_questions_with_position(): void
    {
        // Act
        $question = ['id' => $this->question->id, 'position' => 5];
        $result = $this->action->execute($this->questionnaire, [
            'questions' => [$question],
        ]);

        // Assert
        $this->assertInstanceOf(Questionnaire::class, $result);

        // Verify the question was linked with correct position
        $this->questionnaire->refresh();
        $linkedQuestion = $this->questionnaire->questions->first();

        $this->assertEquals(5, $linkedQuestion->pivot->position);
    }

    #[Test]
    public function it_can_link_multiple_questions_with_different_positions(): void
    {
        // Arrange
        $question2 = resolve(QuestionFactory::class)->create(['financer_id' => $this->questionnaire->financer_id]);

        // Act
        $questions = [
            ['id' => $this->question->id, 'position' => 3],
            ['id' => $question2->id, 'position' => 7],
        ];
        $result = $this->action->execute($this->questionnaire, [
            'questions' => $questions,
        ]);

        // Assert
        $this->assertInstanceOf(Questionnaire::class, $result);

        // Verify both questions were linked with correct positions
        $this->questionnaire->refresh();
        $linkedQuestions = $this->questionnaire->questions;

        $this->assertCount(2, $linkedQuestions);

        $question1Pivot = $linkedQuestions->firstWhere('text', $this->question->text)->pivot;
        $question2Pivot = $linkedQuestions->firstWhere('text', $question2->text)->pivot;

        $this->assertEquals(3, $question1Pivot->position);
        $this->assertEquals(7, $question2Pivot->position);
    }

    #[Test]
    public function it_handles_empty_question_ids_gracefully(): void
    {
        // Act
        $result = $this->action->execute($this->questionnaire, ['questions' => []]);

        // Assert
        $this->assertInstanceOf(Questionnaire::class, $result);

        // Verify no questions were linked
        $this->questionnaire->refresh();
        $this->assertCount(0, $this->questionnaire->questions);
    }

    #[Test]
    public function it_throws_exception_for_non_existent_question_ids(): void
    {
        // Arrange - Use a valid UUID format but non-existent ID
        $nonExistentId = '01999999-9999-7999-9999-999999999999';

        // Assert
        $this->expectException(QuestionNotFoundException::class);
        $this->expectExceptionMessage("Question with ID {$nonExistentId} not found");

        // Act
        $question = ['id' => $nonExistentId];
        $this->action->execute($this->questionnaire, ['questions' => [$question]]);
    }

    #[Test]
    public function it_throws_exception_for_mixed_valid_and_invalid_question_ids(): void
    {
        // Arrange
        $question2 = resolve(QuestionFactory::class)->create(['financer_id' => $this->questionnaire->financer_id]);
        $nonExistentId = '01999999-9999-7999-9999-999999999999';

        // Assert
        $this->expectException(QuestionNotFoundException::class);
        $this->expectExceptionMessage("Question with ID {$nonExistentId} not found");

        // Act - Should fail when encountering the non-existent ID
        $questions = [
            ['id' => $this->question->id, 'position' => 1],
            ['id' => $nonExistentId, 'position' => 2],
            ['id' => $question2->id, 'position' => 3],
        ];
        $this->action->execute($this->questionnaire, [
            'questions' => $questions,
        ]);
    }

    #[Test]
    public function it_uses_sync_without_detaching_to_preserve_existing_questions(): void
    {
        // Arrange - Link first question
        $question2 = resolve(QuestionFactory::class)->create(['financer_id' => $this->questionnaire->financer_id]);
        $question = ['id' => $this->question->id];
        $this->action->execute($this->questionnaire, ['questions' => [$question]]);

        // Act - Link second question
        $question = ['id' => $question2->id];
        $result = $this->action->execute($this->questionnaire, ['questions' => [$question]]);

        // Assert
        $this->assertInstanceOf(Questionnaire::class, $result);

        // Verify both questions are linked (syncWithoutDetaching preserved the first one)
        $this->questionnaire->refresh();
        $linkedQuestions = $this->questionnaire->questions;

        $this->assertCount(2, $linkedQuestions);

        $this->assertTrue($linkedQuestions->contains('text', $this->question->text));
        $this->assertTrue($linkedQuestions->contains('text', $question2->text));
    }

    #[Test]
    public function it_works_within_a_database_transaction(): void
    {
        // This test verifies that the action works within a transaction
        // If there's an error during duplication or linking, everything should be rolled back

        // Arrange
        $question2 = resolve(QuestionFactory::class)->create(['financer_id' => $this->questionnaire->financer_id]);

        // Act
        $questions = [
            ['id' => $this->question->id, 'position' => 1],
            ['id' => $question2->id, 'position' => 2],
        ];
        $result = $this->action->execute($this->questionnaire, [
            'questions' => $questions,
        ]);

        // Assert - Verify the transaction completed successfully
        $this->assertInstanceOf(Questionnaire::class, $result);

        $this->questionnaire->refresh();
        $this->assertCount(2, $this->questionnaire->questions);

        // Verify the database state is consistent
        $this->assertDatabaseHas('int_survey_questionables', [
            'questionable_type' => 'App\\Integrations\\Survey\\Models\\Questionnaire',
            'questionable_id' => $this->questionnaire->id,
        ]);
    }
}
