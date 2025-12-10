<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Survey\Actions\Me\Answer;

use App\Integrations\Survey\Actions\Me\Answer\UpdateAnswerAction;
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
class UpdateAnswerActionTest extends TestCase
{
    use DatabaseTransactions;

    private UpdateAnswerAction $action;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new UpdateAnswerAction;
        $this->financer = ModelFactory::createFinancer();

        Context::add('accessible_financers', [$this->financer->id]);
        Context::add('financer_id', $this->financer->id);
    }

    #[Test]
    public function it_updates_an_answer_successfully(): void
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
            'answer' => ['value' => 'Original answer'],
        ]);

        $updateData = [
            'answer' => ['value' => 'Updated answer'],
        ];

        // Act
        $result = $this->action->execute($answer, $updateData);

        // Assert
        $this->assertInstanceOf(Answer::class, $result);
        $this->assertEquals($answer->id, $result->id);
        $this->assertEquals(['value' => 'Updated answer'], $result->answer);

        // Check database persistence
        $this->assertDatabaseHas('int_survey_answers', [
            'id' => $answer->id,
        ]);

        // Verify old answer is no longer in database
        $this->assertDatabaseMissing('int_survey_answers', [
            'id' => $answer->id,
            'answer' => json_encode(['value' => 'Original answer']),
        ]);
    }

    #[Test]
    public function it_updates_answer_with_array_value(): void
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
            'answer' => ['options' => ['option1']],
        ]);

        $updateData = [
            'answer' => ['options' => ['option1', 'option2', 'option3']],
        ];

        // Act
        $result = $this->action->execute($answer, $updateData);

        // Assert
        $this->assertEquals(['options' => ['option1', 'option2', 'option3']], $result->answer);
    }

    #[Test]
    public function it_updates_answer_with_numeric_value(): void
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
            'answer' => ['scale' => 5],
        ]);

        $updateData = [
            'answer' => ['scale' => 9],
        ];

        // Act
        $result = $this->action->execute($answer, $updateData);

        // Assert
        $this->assertEquals(['scale' => 9], $result->answer);
    }

    #[Test]
    public function it_maintains_answer_id_after_update(): void
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

        $originalId = $answer->id;
        $updateData = [
            'answer' => ['value' => 'New value'],
        ];

        // Act
        $result = $this->action->execute($answer, $updateData);

        // Assert
        $this->assertEquals($originalId, $result->id);
    }

    #[Test]
    public function it_preserves_relationships_after_update(): void
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

        $updateData = [
            'answer' => ['value' => 'Updated value'],
        ];

        // Act
        $result = $this->action->execute($answer, $updateData);

        // Assert
        $this->assertEquals($user->id, $result->user_id);
        $this->assertEquals($submission->id, $result->submission_id);
        $this->assertEquals($question->id, $result->question_id);
    }

    #[Test]
    public function it_handles_empty_update_data(): void
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

        $originalAnswer = ['value' => 'Original answer'];
        $answer = resolve(AnswerFactory::class)->create([
            'user_id' => $user->id,
            'submission_id' => $submission->id,
            'question_id' => $question->id,
            'answer' => $originalAnswer,
        ]);

        // Act
        $result = $this->action->execute($answer, []);

        // Assert
        $this->assertEquals($originalAnswer, $result->answer);
    }
}
