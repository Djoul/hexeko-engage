<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Survey\Actions\Me\Answer;

use App\Integrations\Survey\Actions\Me\Answer\DeleteAnswerAction;
use App\Integrations\Survey\Database\factories\AnswerFactory;
use App\Integrations\Survey\Database\factories\QuestionFactory;
use App\Integrations\Survey\Database\factories\SubmissionFactory;
use App\Integrations\Survey\Database\factories\SurveyFactory;
use App\Integrations\Survey\Models\Answer;
use App\Models\Financer;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('survey')]
#[Group('answer')]
#[Group('action')]
class DeleteAnswerActionTest extends TestCase
{
    use DatabaseTransactions;

    private DeleteAnswerAction $action;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new DeleteAnswerAction;
        $this->financer = ModelFactory::createFinancer();

        Context::add('accessible_financers', [$this->financer->id]);
        Context::add('financer_id', $this->financer->id);
    }

    #[Test]
    public function it_deletes_an_answer_successfully(): void
    {
        // Arrange

        $user = User::factory()->create();
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);
        $question = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);
        $submission = resolve(SubmissionFactory::class)->create([
            'user_id' => $user->id,
            'survey_id' => $survey->id,
            'financer_id' => $this->financer->id,
        ]);

        $answer = resolve(AnswerFactory::class)->create([
            'user_id' => $user->id,
            'submission_id' => $submission->id,
            'question_id' => $question->id,
            'answer' => ['value' => 'Test answer'],
        ]);

        $answerId = $answer->id;

        // Verify answer exists
        $this->assertDatabaseHas('int_survey_answers', [
            'id' => $answerId,
        ]);

        // Act
        $result = $this->action->execute($answer);

        // Assert
        $this->assertTrue($result);

        // Check that answer is soft deleted
        $this->assertSoftDeleted('int_survey_answers', [
            'id' => $answerId,
        ]);
    }

    #[Test]
    public function it_soft_deletes_answer(): void
    {
        // Arrange

        $user = User::factory()->create();
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);
        $question = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);
        $submission = resolve(SubmissionFactory::class)->create([
            'user_id' => $user->id,
            'survey_id' => $survey->id,
            'financer_id' => $this->financer->id,
        ]);

        $answer = resolve(AnswerFactory::class)->create([
            'user_id' => $user->id,
            'submission_id' => $submission->id,
            'question_id' => $question->id,
        ]);

        $answerId = $answer->id;

        // Act
        $this->action->execute($answer);

        // Assert - Check using withTrashed
        $deletedAnswer = Answer::withTrashed()->find($answerId);
        $this->assertNotNull($deletedAnswer);
        $this->assertNotNull($deletedAnswer->deleted_at);
    }

    #[Test]
    public function it_returns_true_on_successful_deletion(): void
    {
        // Arrange

        $user = User::factory()->create();
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);
        $question = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);
        $submission = resolve(SubmissionFactory::class)->create([
            'user_id' => $user->id,
            'survey_id' => $survey->id,
            'financer_id' => $this->financer->id,
        ]);

        $answer = resolve(AnswerFactory::class)->create([
            'user_id' => $user->id,
            'submission_id' => $submission->id,
            'question_id' => $question->id,
        ]);

        // Act
        $result = $this->action->execute($answer);

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function it_removes_answer_from_active_queries(): void
    {
        // Arrange

        $user = User::factory()->create();
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);
        $question = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);
        $submission = resolve(SubmissionFactory::class)->create([
            'user_id' => $user->id,
            'survey_id' => $survey->id,
            'financer_id' => $this->financer->id,
        ]);

        $answer = resolve(AnswerFactory::class)->create([
            'user_id' => $user->id,
            'submission_id' => $submission->id,
            'question_id' => $question->id,
        ]);

        $answerId = $answer->id;

        // Act
        $this->action->execute($answer);

        // Assert - Regular query should not find the deleted answer
        $activeAnswer = Answer::find($answerId);
        $this->assertNull($activeAnswer);
    }

    #[Test]
    public function it_preserves_answer_data_in_database_after_soft_delete(): void
    {
        // Arrange

        $user = User::factory()->create();
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);
        $question = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);
        $submission = resolve(SubmissionFactory::class)->create([
            'user_id' => $user->id,
            'survey_id' => $survey->id,
            'financer_id' => $this->financer->id,
        ]);

        $answerData = ['value' => 'Important answer'];
        $answer = resolve(AnswerFactory::class)->create([
            'user_id' => $user->id,
            'submission_id' => $submission->id,
            'question_id' => $question->id,
            'answer' => $answerData,
        ]);

        $answerId = $answer->id;

        // Act
        $this->action->execute($answer);

        // Assert - Data should still be in database
        $deletedAnswer = Answer::withTrashed()->find($answerId);
        $this->assertNotNull($deletedAnswer);
        $this->assertEquals($answerData, $deletedAnswer->answer);
        $this->assertEquals($user->id, $deletedAnswer->user_id);
        $this->assertEquals($submission->id, $deletedAnswer->submission_id);
        $this->assertEquals($question->id, $deletedAnswer->question_id);
    }

    #[Test]
    public function it_can_delete_answers_with_different_answer_types(): void
    {
        // Arrange

        $user = User::factory()->create();
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);
        $question1 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);
        $question2 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);
        $question3 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);

        $submission = resolve(SubmissionFactory::class)->create([
            'user_id' => $user->id,
            'survey_id' => $survey->id,
            'financer_id' => $this->financer->id,
        ]);

        // Create answers with different types
        $textAnswer = resolve(AnswerFactory::class)->create([
            'user_id' => $user->id,
            'submission_id' => $submission->id,
            'question_id' => $question1->id,
            'answer' => ['text' => 'Text answer'],
        ]);

        $scaleAnswer = resolve(AnswerFactory::class)->create([
            'user_id' => $user->id,
            'submission_id' => $submission->id,
            'question_id' => $question2->id,
            'answer' => ['scale' => 8],
        ]);

        $multipleChoiceAnswer = resolve(AnswerFactory::class)->create([
            'user_id' => $user->id,
            'submission_id' => $submission->id,
            'question_id' => $question3->id,
            'answer' => ['choices' => ['option1', 'option2']],
        ]);

        // Act
        $result1 = $this->action->execute($textAnswer);
        $result2 = $this->action->execute($scaleAnswer);
        $result3 = $this->action->execute($multipleChoiceAnswer);

        // Assert
        $this->assertTrue($result1);
        $this->assertTrue($result2);
        $this->assertTrue($result3);

        $this->assertSoftDeleted('int_survey_answers', ['id' => $textAnswer->id]);
        $this->assertSoftDeleted('int_survey_answers', ['id' => $scaleAnswer->id]);
        $this->assertSoftDeleted('int_survey_answers', ['id' => $multipleChoiceAnswer->id]);
    }
}
