<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Survey\Actions\Me\Answer;

use App\Integrations\Survey\Actions\Me\Answer\CreateAnswerAction;
use App\Integrations\Survey\Database\factories\AnswerFactory;
use App\Integrations\Survey\Database\factories\QuestionFactory;
use App\Integrations\Survey\Database\factories\SubmissionFactory;
use App\Integrations\Survey\Database\factories\SurveyFactory;
use App\Integrations\Survey\Exceptions\AnswerException;
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
class CreateAnswerActionTest extends TestCase
{
    use DatabaseTransactions;

    private CreateAnswerAction $action;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new CreateAnswerAction;
        $this->financer = ModelFactory::createFinancer();

        Context::add('accessible_financers', [$this->financer->id]);
        Context::add('financer_id', $this->financer->id);
    }

    #[Test]
    public function it_creates_an_answer_successfully(): void
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

        $this->actingAs($user);

        $data = [
            'submission_id' => $submission->id,
            'question_id' => $question->id,
            'answer' => ['value' => 'Test answer'],
        ];

        $answer = new Answer;

        // Act
        $result = $this->action->execute($answer, $data);

        // Assert
        $this->assertInstanceOf(Answer::class, $result);
        $this->assertNotNull($result->id);
        $this->assertEquals($submission->id, $result->submission_id);
        $this->assertEquals($question->id, $result->question_id);
        $this->assertEquals($user->id, $result->user_id);
        $this->assertEquals(['value' => 'Test answer'], $result->answer);

        // Check database persistence
        $this->assertDatabaseHas('int_survey_answers', [
            'id' => $result->id,
            'user_id' => $user->id,
            'submission_id' => $submission->id,
            'question_id' => $question->id,
        ]);
    }

    #[Test]
    public function it_throws_exception_when_question_already_answered(): void
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

        // Create existing answer
        resolve(AnswerFactory::class)->create([
            'user_id' => $user->id,
            'submission_id' => $submission->id,
            'question_id' => $question->id,
        ]);

        $this->actingAs($user);

        $data = [
            'submission_id' => $submission->id,
            'question_id' => $question->id,
            'answer' => ['value' => 'Another answer'],
        ];

        $answer = new Answer;

        // Act & Assert
        $this->expectException(AnswerException::class);
        $this->expectExceptionMessage('You have already answered this question');

        $this->action->execute($answer, $data);
    }

    #[Test]
    public function it_creates_answer_with_array_data(): void
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

        $this->actingAs($user);

        $data = [
            'submission_id' => $submission->id,
            'question_id' => $question->id,
            'answer' => ['options' => ['option1', 'option2', 'option3']],
        ];

        $answer = new Answer;

        // Act
        $result = $this->action->execute($answer, $data);

        // Assert
        $this->assertInstanceOf(Answer::class, $result);
        $this->assertEquals(['options' => ['option1', 'option2', 'option3']], $result->answer);
    }

    #[Test]
    public function it_creates_answer_with_numeric_value(): void
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

        $this->actingAs($user);

        $data = [
            'submission_id' => $submission->id,
            'question_id' => $question->id,
            'answer' => ['scale' => 8],
        ];

        $answer = new Answer;

        // Act
        $result = $this->action->execute($answer, $data);

        // Assert
        $this->assertInstanceOf(Answer::class, $result);
        $this->assertEquals(['scale' => 8], $result->answer);
    }

    #[Test]
    public function it_sets_user_id_from_authenticated_user(): void
    {
        // Arrange

        $authenticatedUser = User::factory()->create();
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);
        $question = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);
        $submission = resolve(SubmissionFactory::class)->create([
            'user_id' => $authenticatedUser->id,
            'survey_id' => $survey->id,
            'financer_id' => $this->financer->id,
        ]);

        $this->actingAs($authenticatedUser);

        $data = [
            'submission_id' => $submission->id,
            'question_id' => $question->id,
            'answer' => ['value' => 'Test'],
        ];

        $answer = new Answer;

        // Act
        $result = $this->action->execute($answer, $data);

        // Assert
        $this->assertEquals($authenticatedUser->id, $result->user_id);
    }
}
