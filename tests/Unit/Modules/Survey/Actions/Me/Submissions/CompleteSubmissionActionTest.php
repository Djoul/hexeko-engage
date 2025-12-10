<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Survey\Actions\Me\Submissions;

use App\Integrations\Survey\Actions\Me\Submissions\CompleteSubmissionAction;
use App\Integrations\Survey\Database\factories\AnswerFactory;
use App\Integrations\Survey\Database\factories\QuestionFactory;
use App\Integrations\Survey\Database\factories\SubmissionFactory;
use App\Integrations\Survey\Database\factories\SurveyFactory;
use App\Integrations\Survey\Enums\SurveyStatusEnum;
use App\Integrations\Survey\Exceptions\SubmissionException;
use App\Integrations\Survey\Models\Submission;
use App\Models\Financer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('survey')]
#[Group('submission')]
#[Group('action')]
class CompleteSubmissionActionTest extends TestCase
{
    use DatabaseTransactions;

    private CompleteSubmissionAction $action;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new CompleteSubmissionAction;
        $this->financer = ModelFactory::createFinancer();

        Context::add('accessible_financers', [$this->financer->id]);
        Context::add('financer_id', $this->financer->id);
    }

    #[Test]
    public function it_completes_a_submission_successfully(): void
    {
        // Arrange
        Context::add('accessible_financers', [$this->financer->id]);
        Context::add('financer_id', $this->financer->id);

        $user = User::factory()->create();
        $survey = resolve(SurveyFactory::class)->create([
            'financer_id' => $this->financer->id,
            'status' => SurveyStatusEnum::PUBLISHED,
        ]);

        $question1 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);
        $question2 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);

        $survey->questions()->attach([$question1->id, $question2->id]);

        $submission = resolve(SubmissionFactory::class)->create([
            'user_id' => $user->id,
            'survey_id' => $survey->id,
            'financer_id' => $this->financer->id,
            'completed_at' => null,
        ]);

        // Create answers for all questions
        resolve(AnswerFactory::class)->create([
            'user_id' => $user->id,
            'submission_id' => $submission->id,
            'question_id' => $question1->id,
        ]);

        resolve(AnswerFactory::class)->create([
            'user_id' => $user->id,
            'submission_id' => $submission->id,
            'question_id' => $question2->id,
        ]);

        // Reload submission with survey
        $submission->refresh();
        $submission->load('survey.questions');

        // Act
        $result = $this->action->execute($submission);

        // Assert
        $this->assertInstanceOf(Submission::class, $result);
        $this->assertNotNull($result->completed_at);
        $this->assertInstanceOf(Carbon::class, $result->completed_at);

        // Check database persistence
        $this->assertDatabaseHas('int_survey_submissions', [
            'id' => $submission->id,
        ]);

        $this->assertNotNull(Submission::find($submission->id)->completed_at);
    }

    #[Test]
    public function it_throws_exception_when_submission_already_completed(): void
    {
        // Arrange
        $user = User::factory()->create();
        $survey = resolve(SurveyFactory::class)->create([
            'financer_id' => $this->financer->id,
            'status' => SurveyStatusEnum::PUBLISHED,
        ]);

        $submission = resolve(SubmissionFactory::class)->create([
            'user_id' => $user->id,
            'survey_id' => $survey->id,
            'financer_id' => $this->financer->id,
            'completed_at' => now()->subHour(),
        ]);

        // Act & Assert
        $this->expectException(SubmissionException::class);
        $this->expectExceptionMessage('Submission is already completed');

        $this->action->execute($submission);
    }

    #[Test]
    public function it_throws_exception_when_survey_is_not_active(): void
    {
        // Arrange
        $user = User::factory()->create();
        $survey = resolve(SurveyFactory::class)->create([
            'financer_id' => $this->financer->id,
            'status' => SurveyStatusEnum::DRAFT,
        ]);

        $submission = resolve(SubmissionFactory::class)->create([
            'user_id' => $user->id,
            'survey_id' => $survey->id,
            'financer_id' => $this->financer->id,
            'completed_at' => null,
        ]);

        // Refresh and load survey relation
        $submission = $submission->fresh(['survey']);

        // Act & Assert
        $this->expectException(SubmissionException::class);
        $this->expectExceptionMessage('Survey is not active');

        $this->action->execute($submission);
    }

    #[Test]
    public function it_throws_exception_when_not_all_questions_answered(): void
    {
        // Arrange
        $user = User::factory()->create();
        $survey = resolve(SurveyFactory::class)->create([
            'financer_id' => $this->financer->id,
            'status' => SurveyStatusEnum::PUBLISHED,
        ]);

        $question1 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);
        $question2 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);
        $question3 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);

        $survey->questions()->attach([$question1->id, $question2->id, $question3->id]);

        $submission = resolve(SubmissionFactory::class)->create([
            'user_id' => $user->id,
            'survey_id' => $survey->id,
            'financer_id' => $this->financer->id,
            'completed_at' => null,
        ]);

        // Only answer 2 out of 3 questions
        resolve(AnswerFactory::class)->create([
            'user_id' => $user->id,
            'submission_id' => $submission->id,
            'question_id' => $question1->id,
        ]);

        resolve(AnswerFactory::class)->create([
            'user_id' => $user->id,
            'submission_id' => $submission->id,
            'question_id' => $question2->id,
        ]);

        // Refresh and load survey relation
        $submission = $submission->fresh(['survey']);

        // Act & Assert
        $this->expectException(SubmissionException::class);
        $this->expectExceptionMessage('All questions must be answered before completing submission');

        $this->action->execute($submission);
    }

    #[Test]
    public function it_validates_exact_number_of_answers_matches_questions(): void
    {
        // Arrange
        $user = User::factory()->create();
        $survey = resolve(SurveyFactory::class)->create([
            'financer_id' => $this->financer->id,
            'status' => SurveyStatusEnum::PUBLISHED,
        ]);

        $question = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);
        $survey->questions()->attach([$question->id]);

        $submission = resolve(SubmissionFactory::class)->create([
            'user_id' => $user->id,
            'survey_id' => $survey->id,
            'financer_id' => $this->financer->id,
            'completed_at' => null,
        ]);

        // Refresh and load survey relation
        $submission = $submission->fresh(['survey']);

        // No answers provided
        $this->expectException(SubmissionException::class);

        $this->action->execute($submission);
    }

    #[Test]
    public function it_completes_submission_with_single_question(): void
    {
        // Arrange
        $user = User::factory()->create();
        $survey = resolve(SurveyFactory::class)->create([
            'financer_id' => $this->financer->id,
            'status' => SurveyStatusEnum::PUBLISHED,
        ]);

        $question = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);
        $survey->questions()->attach([$question->id]);

        $submission = resolve(SubmissionFactory::class)->create([
            'user_id' => $user->id,
            'survey_id' => $survey->id,
            'financer_id' => $this->financer->id,
            'completed_at' => null,
        ]);

        resolve(AnswerFactory::class)->create([
            'user_id' => $user->id,
            'submission_id' => $submission->id,
            'question_id' => $question->id,
        ]);

        // Refresh and load survey relation
        $submission = $submission->fresh(['survey']);

        // Act
        $result = $this->action->execute($submission);

        // Assert
        $this->assertNotNull($result->completed_at);
    }

    #[Test]
    public function it_completes_submission_with_many_questions(): void
    {
        // Arrange
        $user = User::factory()->create();
        $survey = resolve(SurveyFactory::class)->create([
            'financer_id' => $this->financer->id,
            'status' => SurveyStatusEnum::PUBLISHED,
        ]);

        $questionIds = [];
        for ($i = 0; $i < 10; $i++) {
            $question = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);
            $questionIds[] = $question->id;
        }

        $survey->questions()->attach($questionIds);

        $submission = resolve(SubmissionFactory::class)->create([
            'user_id' => $user->id,
            'survey_id' => $survey->id,
            'financer_id' => $this->financer->id,
            'completed_at' => null,
        ]);

        foreach ($questionIds as $questionId) {
            resolve(AnswerFactory::class)->create([
                'user_id' => $user->id,
                'submission_id' => $submission->id,
                'question_id' => $questionId,
            ]);
        }

        // Refresh and load survey relation
        $submission = $submission->fresh(['survey']);

        // Act
        $result = $this->action->execute($submission);

        // Assert
        $this->assertNotNull($result->completed_at);
        $this->assertEquals(10, $result->answers()->count());
    }

    #[Test]
    public function it_returns_refreshed_submission_with_completed_at_set(): void
    {
        // Arrange
        $user = User::factory()->create();
        $survey = resolve(SurveyFactory::class)->create([
            'financer_id' => $this->financer->id,
            'status' => SurveyStatusEnum::PUBLISHED,
        ]);

        $question = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);
        $survey->questions()->attach([$question->id]);

        $submission = resolve(SubmissionFactory::class)->create([
            'user_id' => $user->id,
            'survey_id' => $survey->id,
            'financer_id' => $this->financer->id,
            'completed_at' => null,
        ]);

        resolve(AnswerFactory::class)->create([
            'user_id' => $user->id,
            'submission_id' => $submission->id,
            'question_id' => $question->id,
        ]);

        // Refresh and load survey relation
        $submission = $submission->fresh(['survey']);

        // Act
        $result = $this->action->execute($submission);

        // Assert
        $this->assertTrue($result->exists);
        $this->assertNotNull($result->completed_at);
        $this->assertNotNull($result->updated_at);
    }
}
