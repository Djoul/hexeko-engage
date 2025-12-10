<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Survey\Actions;

use App\Integrations\Survey\Actions\Survey\LinkSurveyQuestionAction;
use App\Integrations\Survey\Database\factories\QuestionFactory;
use App\Integrations\Survey\Database\factories\QuestionnaireFactory;
use App\Integrations\Survey\Database\factories\SurveyFactory;
use App\Integrations\Survey\Exceptions\QuestionNotFoundException;
use App\Integrations\Survey\Models\Question;
use App\Integrations\Survey\Models\Questionnaire;
use App\Integrations\Survey\Models\Survey;
use Database\Factories\FinancerFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('survey')]
#[Group('question')]
class LinkSurveyQuestionActionTest extends TestCase
{
    use DatabaseTransactions;

    private LinkSurveyQuestionAction $action;

    private Survey $survey;

    private Question $question;

    protected function setUp(): void
    {
        parent::setUp();

        $financer = resolve(FinancerFactory::class)->create();
        $this->survey = resolve(SurveyFactory::class)->create(['financer_id' => $financer->id]);
        $this->question = resolve(QuestionFactory::class)->create(['financer_id' => $financer->id]);
        $this->action = new LinkSurveyQuestionAction;

        Context::add('accessible_financers', [$financer->id]);
        Context::add('financer_id', $financer->id);
    }

    #[Test]
    public function it_can_link_new_questions_by_duplicating_them(): void
    {
        // Act
        $question = ['id' => $this->question->id];
        $result = $this->action->execute($this->survey, ['questions' => [$question]]);

        // Assert
        $this->assertInstanceOf(Survey::class, $result);

        // Verify the question was duplicated and linked
        $this->survey->refresh();
        $linkedQuestions = $this->survey->questions;

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
        $question2 = resolve(QuestionFactory::class)->create(['financer_id' => $this->survey->financer_id]);
        $question3 = resolve(QuestionFactory::class)->create(['financer_id' => $this->survey->financer_id]);

        // Act
        $questions = [
            ['id' => $this->question->id],
            ['id' => $question2->id],
            ['id' => $question3->id],
        ];
        $result = $this->action->execute($this->survey, [
            'questions' => $questions,
        ]);

        // Assert
        $this->assertInstanceOf(Survey::class, $result);

        // Verify all questions were duplicated and linked
        $this->survey->refresh();
        $linkedQuestions = $this->survey->questions;

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
        $this->action->execute($this->survey, ['questions' => [$question]]);

        $this->survey->refresh();
        $firstLinkedQuestion = $this->survey->questions->first();

        // Act - Link the same original question again
        $result = $this->action->execute($this->survey, ['questions' => [$question]]);

        // Assert
        $this->assertInstanceOf(Survey::class, $result);

        // Verify we now have 2 questions linked (both duplicates of the original)
        $this->survey->refresh();
        $linkedQuestions = $this->survey->questions;

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
        $result = $this->action->execute($this->survey, [
            'questions' => [$question],
        ]);

        // Assert
        $this->assertInstanceOf(Survey::class, $result);

        // Verify the question was linked with correct position
        $this->survey->refresh();
        $linkedQuestion = $this->survey->questions->first();

        $this->assertEquals(5, $linkedQuestion->pivot->position);
    }

    #[Test]
    public function it_can_link_multiple_questions_with_different_positions(): void
    {
        // Arrange
        $question2 = resolve(QuestionFactory::class)->create(['financer_id' => $this->survey->financer_id]);

        // Act
        $questions = [
            ['id' => $this->question->id, 'position' => 3],
            ['id' => $question2->id, 'position' => 7],
        ];
        $result = $this->action->execute($this->survey, [
            'questions' => $questions,
        ]);

        // Assert
        $this->assertInstanceOf(Survey::class, $result);

        // Verify both questions were linked with correct positions
        $this->survey->refresh();
        $linkedQuestions = $this->survey->questions;

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
        $result = $this->action->execute($this->survey, ['questions' => []]);

        // Assert
        $this->assertInstanceOf(Survey::class, $result);

        // Verify no questions were linked
        $this->survey->refresh();
        $this->assertCount(0, $this->survey->questions);
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
        $this->action->execute($this->survey, ['questions' => [$question]]);
    }

    #[Test]
    public function it_throws_exception_for_mixed_valid_and_invalid_question_ids(): void
    {
        // Arrange
        $question2 = resolve(QuestionFactory::class)->create(['financer_id' => $this->survey->financer_id]);
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
        $this->action->execute($this->survey, [
            'questions' => $questions,
        ]);
    }

    #[Test]
    public function it_uses_sync_without_detaching_to_preserve_existing_questions(): void
    {
        // Arrange - Link first question
        $question2 = resolve(QuestionFactory::class)->create(['financer_id' => $this->survey->financer_id]);
        $question = ['id' => $this->question->id];
        $this->action->execute($this->survey, ['questions' => [$question]]);

        // Act - Link second question
        $question = ['id' => $question2->id];
        $result = $this->action->execute($this->survey, ['questions' => [$question]]);

        // Assert
        $this->assertInstanceOf(Survey::class, $result);

        // Verify both questions are linked (syncWithoutDetaching preserved the first one)
        $this->survey->refresh();
        $linkedQuestions = $this->survey->questions;

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
        $question2 = resolve(QuestionFactory::class)->create(['financer_id' => $this->survey->financer_id]);

        // Act
        $questions = [
            ['id' => $this->question->id, 'position' => 1],
            ['id' => $question2->id, 'position' => 2],
        ];
        $result = $this->action->execute($this->survey, [
            'questions' => $questions,
        ]);

        // Assert - Verify the transaction completed successfully
        $this->assertInstanceOf(Survey::class, $result);

        $this->survey->refresh();
        $this->assertCount(2, $this->survey->questions);

        // Verify the database state is consistent
        $this->assertDatabaseHas('int_survey_questionables', [
            'questionable_type' => 'App\\Integrations\\Survey\\Models\\Survey',
            'questionable_id' => $this->survey->id,
        ]);
    }

    #[Test]
    public function it_can_link_questions_from_questionnaire(): void
    {
        // Arrange - Create a questionnaire with questions
        $questionnaire = resolve(QuestionnaireFactory::class)->create(['financer_id' => $this->survey->financer_id]);
        $questionnaireQuestion1 = resolve(QuestionFactory::class)->create(['financer_id' => $this->survey->financer_id]);
        $questionnaireQuestion2 = resolve(QuestionFactory::class)->create(['financer_id' => $this->survey->financer_id]);

        // Link questions to questionnaire with positions
        $questionnaire->questions()->attach($questionnaireQuestion1->id, ['position' => 1]);
        $questionnaire->questions()->attach($questionnaireQuestion2->id, ['position' => 2]);
        $questionnaire->refresh();

        // Act - Link questions from questionnaire to survey
        $result = $this->action->execute($this->survey, ['questionnaires' => [$questionnaire->id]]);

        // Assert
        $this->assertInstanceOf(Survey::class, $result);

        // Verify questions from questionnaire were duplicated and linked to survey
        $this->survey->refresh();
        $linkedQuestions = $this->survey->questions;

        $this->assertCount(2, $linkedQuestions);

        // Questions should be duplicated (different IDs but same content)
        $this->assertFalse($linkedQuestions->contains('id', $questionnaireQuestion1->id));
        $this->assertFalse($linkedQuestions->contains('id', $questionnaireQuestion2->id));

        $this->assertTrue($linkedQuestions->contains('text', $questionnaireQuestion1->text));
        $this->assertTrue($linkedQuestions->contains('text', $questionnaireQuestion2->text));

        // Verify positions are preserved
        $linkedQuestion1 = $linkedQuestions->firstWhere('text', $questionnaireQuestion1->text);
        $linkedQuestion2 = $linkedQuestions->firstWhere('text', $questionnaireQuestion2->text);

        $this->assertEquals(1, $linkedQuestion1->pivot->position);
        $this->assertEquals(2, $linkedQuestion2->pivot->position);
    }

    #[Test]
    public function it_can_link_questions_from_multiple_questionnaires(): void
    {
        // Arrange - Create two questionnaires with questions
        $questionnaire1 = resolve(QuestionnaireFactory::class)->create(['financer_id' => $this->survey->financer_id]);
        $questionnaire2 = resolve(QuestionnaireFactory::class)->create(['financer_id' => $this->survey->financer_id]);

        $question1 = resolve(QuestionFactory::class)->create(['financer_id' => $this->survey->financer_id]);
        $question2 = resolve(QuestionFactory::class)->create(['financer_id' => $this->survey->financer_id]);
        $question3 = resolve(QuestionFactory::class)->create(['financer_id' => $this->survey->financer_id]);

        // Link questions to questionnaires
        $questionnaire1->questions()->attach($question1->id, ['position' => 1]);
        $questionnaire1->questions()->attach($question2->id, ['position' => 2]);
        $questionnaire2->questions()->attach($question3->id, ['position' => 1]);

        // Act - Link questions from both questionnaires to survey
        $result = $this->action->execute($this->survey, [
            'questionnaires' => [$questionnaire1->id, $questionnaire2->id],
        ]);

        // Assert
        $this->assertInstanceOf(Survey::class, $result);

        // Verify all questions from both questionnaires were duplicated and linked
        $this->survey->refresh();
        $linkedQuestions = $this->survey->questions;

        $this->assertCount(3, $linkedQuestions);

        $this->assertTrue($linkedQuestions->contains('text', $question1->text));
        $this->assertTrue($linkedQuestions->contains('text', $question2->text));
        $this->assertTrue($linkedQuestions->contains('text', $question3->text));
    }

    #[Test]
    public function it_can_link_both_direct_questions_and_questions_from_questionnaire(): void
    {
        // Arrange - Create a questionnaire with a question
        $questionnaire = resolve(QuestionnaireFactory::class)->create(['financer_id' => $this->survey->financer_id]);
        $questionnaireQuestion = resolve(QuestionFactory::class)->create(['financer_id' => $this->survey->financer_id]);
        $questionnaire->questions()->attach($questionnaireQuestion->id, ['position' => 1]);

        // Create a direct question
        $directQuestion = resolve(QuestionFactory::class)->create(['financer_id' => $this->survey->financer_id]);

        // Act - Link both direct question and questions from questionnaire
        $result = $this->action->execute($this->survey, [
            'questions' => [['id' => $directQuestion->id, 'position' => 2]],
            'questionnaires' => [$questionnaire->id],
        ]);

        // Assert
        $this->assertInstanceOf(Survey::class, $result);

        // Verify both types of questions were linked
        $this->survey->refresh();
        $linkedQuestions = $this->survey->questions;

        $this->assertCount(2, $linkedQuestions);

        $this->assertTrue($linkedQuestions->contains('text', $questionnaireQuestion->text));
        $this->assertTrue($linkedQuestions->contains('text', $directQuestion->text));
    }

    #[Test]
    public function it_handles_empty_questionnaire_ids_gracefully(): void
    {
        // Act
        $result = $this->action->execute($this->survey, ['questionnaires' => []]);

        // Assert
        $this->assertInstanceOf(Survey::class, $result);

        // Verify no questions were linked
        $this->survey->refresh();
        $this->assertCount(0, $this->survey->questions);
    }

    #[Test]
    public function it_handles_non_existent_questionnaire_ids_gracefully(): void
    {
        // Arrange - Use a valid UUID format but non-existent ID
        $nonExistentId = '01999999-9999-7999-9999-999999999999';

        // Act
        $result = $this->action->execute($this->survey, ['questionnaires' => [$nonExistentId]]);

        // Assert
        $this->assertInstanceOf(Survey::class, $result);

        // Verify no questions were linked
        $this->survey->refresh();
        $this->assertCount(0, $this->survey->questions);
    }

    #[Test]
    public function it_handles_questionnaire_with_no_questions(): void
    {
        // Arrange - Create a questionnaire without questions
        $emptyQuestionnaire = resolve(QuestionnaireFactory::class)->create(['financer_id' => $this->survey->financer_id]);

        // Act
        $result = $this->action->execute($this->survey, ['questionnaires' => [$emptyQuestionnaire->id]]);

        // Assert
        $this->assertInstanceOf(Survey::class, $result);

        // Verify no questions were linked
        $this->survey->refresh();
        $this->assertCount(0, $this->survey->questions);
    }
}
