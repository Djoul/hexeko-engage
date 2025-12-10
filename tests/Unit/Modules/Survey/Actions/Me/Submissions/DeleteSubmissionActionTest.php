<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Survey\Actions\Me\Submissions;

use App\Integrations\Survey\Actions\Me\Submissions\DeleteSubmissionAction;
use App\Integrations\Survey\Database\factories\AnswerFactory;
use App\Integrations\Survey\Database\factories\QuestionFactory;
use App\Integrations\Survey\Database\factories\SubmissionFactory;
use App\Integrations\Survey\Database\factories\SurveyFactory;
use App\Integrations\Survey\Models\Answer;
use App\Integrations\Survey\Models\Submission;
use App\Models\Financer;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('survey')]
#[Group('submission')]
#[Group('action')]
class DeleteSubmissionActionTest extends TestCase
{
    use DatabaseTransactions;

    private DeleteSubmissionAction $action;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new DeleteSubmissionAction;
        $this->financer = ModelFactory::createFinancer();

        Context::add('accessible_financers', [$this->financer->id]);
        Context::add('financer_id', $this->financer->id);
    }

    #[Test]
    public function it_deletes_a_submission_successfully(): void
    {
        // Arrange

        $user = User::factory()->create();
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);

        $submission = resolve(SubmissionFactory::class)->create([
            'user_id' => $user->id,
            'survey_id' => $survey->id,
            'financer_id' => $this->financer->id,
        ]);

        $submissionId = $submission->id;

        // Verify submission exists
        $this->assertDatabaseHas('int_survey_submissions', [
            'id' => $submissionId,
        ]);

        // Act
        $result = $this->action->execute($submission);

        // Assert
        $this->assertTrue($result);

        // Check that submission is soft deleted
        $this->assertSoftDeleted('int_survey_submissions', [
            'id' => $submissionId,
        ]);
    }

    #[Test]
    public function it_deletes_all_associated_answers(): void
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

        $answer1 = resolve(AnswerFactory::class)->create([
            'user_id' => $user->id,
            'submission_id' => $submission->id,
            'question_id' => $question1->id,
        ]);

        $answer2 = resolve(AnswerFactory::class)->create([
            'user_id' => $user->id,
            'submission_id' => $submission->id,
            'question_id' => $question2->id,
        ]);

        $answer3 = resolve(AnswerFactory::class)->create([
            'user_id' => $user->id,
            'submission_id' => $submission->id,
            'question_id' => $question3->id,
        ]);

        // Act
        $this->action->execute($submission);

        // Assert - All answers should be soft deleted
        $this->assertSoftDeleted('int_survey_answers', ['id' => $answer1->id]);
        $this->assertSoftDeleted('int_survey_answers', ['id' => $answer2->id]);
        $this->assertSoftDeleted('int_survey_answers', ['id' => $answer3->id]);
    }

    #[Test]
    public function it_soft_deletes_submission(): void
    {
        // Arrange

        $user = User::factory()->create();
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);

        $submission = resolve(SubmissionFactory::class)->create([
            'user_id' => $user->id,
            'survey_id' => $survey->id,
            'financer_id' => $this->financer->id,
        ]);

        $submissionId = $submission->id;

        // Act
        $this->action->execute($submission);

        // Assert - Check using withTrashed
        $deletedSubmission = Submission::withTrashed()->find($submissionId);
        $this->assertNotNull($deletedSubmission);
        $this->assertNotNull($deletedSubmission->deleted_at);
    }

    #[Test]
    public function it_returns_true_on_successful_deletion(): void
    {
        // Arrange

        $user = User::factory()->create();
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);

        $submission = resolve(SubmissionFactory::class)->create([
            'user_id' => $user->id,
            'survey_id' => $survey->id,
            'financer_id' => $this->financer->id,
        ]);

        // Act
        $result = $this->action->execute($submission);

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function it_removes_submission_from_active_queries(): void
    {
        // Arrange

        $user = User::factory()->create();
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);

        $submission = resolve(SubmissionFactory::class)->create([
            'user_id' => $user->id,
            'survey_id' => $survey->id,
            'financer_id' => $this->financer->id,
        ]);

        $submissionId = $submission->id;

        // Act
        $this->action->execute($submission);

        // Assert - Regular query should not find the deleted submission
        $activeSubmission = Submission::find($submissionId);
        $this->assertNull($activeSubmission);
    }

    #[Test]
    public function it_preserves_submission_data_after_soft_delete(): void
    {
        // Arrange

        $user = User::factory()->create();
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);

        $startedAt = now()->subHours(2);
        $submission = resolve(SubmissionFactory::class)->create([
            'user_id' => $user->id,
            'survey_id' => $survey->id,
            'financer_id' => $this->financer->id,
            'started_at' => $startedAt,
        ]);

        $submissionId = $submission->id;

        // Act
        $this->action->execute($submission);

        // Assert - Data should still be in database
        $deletedSubmission = Submission::withTrashed()->find($submissionId);
        $this->assertNotNull($deletedSubmission);
        $this->assertEquals($user->id, $deletedSubmission->user_id);
        $this->assertEquals($survey->id, $deletedSubmission->survey_id);
        $this->assertEquals($this->financer->id, $deletedSubmission->financer_id);
        $this->assertEquals($startedAt->format('Y-m-d H:i:s'), $deletedSubmission->started_at->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function it_deletes_submission_without_answers(): void
    {
        // Arrange

        $user = User::factory()->create();
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);

        $submission = resolve(SubmissionFactory::class)->create([
            'user_id' => $user->id,
            'survey_id' => $survey->id,
            'financer_id' => $this->financer->id,
        ]);

        $submissionId = $submission->id;

        // Act
        $result = $this->action->execute($submission);

        // Assert
        $this->assertTrue($result);
        $this->assertSoftDeleted('int_survey_submissions', ['id' => $submissionId]);
    }

    #[Test]
    public function it_deletes_completed_submission(): void
    {
        // Arrange

        $user = User::factory()->create();
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);

        $completedAt = now()->subHour();
        $submission = resolve(SubmissionFactory::class)->create([
            'user_id' => $user->id,
            'survey_id' => $survey->id,
            'financer_id' => $this->financer->id,
            'completed_at' => $completedAt,
        ]);

        $submissionId = $submission->id;

        // Act
        $result = $this->action->execute($submission);

        // Assert
        $this->assertTrue($result);
        $this->assertSoftDeleted('int_survey_submissions', ['id' => $submissionId]);

        // Verify completed_at is preserved
        $deletedSubmission = Submission::withTrashed()->find($submissionId);
        $this->assertEquals($completedAt->format('Y-m-d H:i:s'), $deletedSubmission->completed_at->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function it_removes_answers_from_active_queries_after_deletion(): void
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
        $this->action->execute($submission);

        // Assert - Regular query should not find deleted answers
        $activeAnswer = Answer::find($answerId);
        $this->assertNull($activeAnswer);

        // But with trashed should find it
        $deletedAnswer = Answer::withTrashed()->find($answerId);
        $this->assertNotNull($deletedAnswer);
    }

    #[Test]
    public function it_preserves_answer_data_after_cascading_delete(): void
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

        $answerData = ['text' => 'Important answer'];
        $answer = resolve(AnswerFactory::class)->create([
            'user_id' => $user->id,
            'submission_id' => $submission->id,
            'question_id' => $question->id,
            'answer' => $answerData,
        ]);

        $answerId = $answer->id;

        // Act
        $this->action->execute($submission);

        // Assert - Answer data should be preserved
        $deletedAnswer = Answer::withTrashed()->find($answerId);
        $this->assertNotNull($deletedAnswer);
        $this->assertEquals($answerData, $deletedAnswer->answer);
        $this->assertEquals($submission->id, $deletedAnswer->submission_id);
    }
}
