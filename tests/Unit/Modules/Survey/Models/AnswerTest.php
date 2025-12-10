<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Survey\Models;

use App\Integrations\Survey\Models\Answer;
use App\Integrations\Survey\Models\Question;
use App\Integrations\Survey\Models\Submission;
use App\Integrations\Survey\Models\Survey;
use App\Models\Financer;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('survey')]
#[Group('answer')]
class AnswerTest extends TestCase
{
    use DatabaseTransactions;

    private Financer $financer;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->financer = ModelFactory::createFinancer();
        $this->user = ModelFactory::createUser();

        Context::add('accessible_financers', [$this->financer->id]);
        Context::add('financer_id', $this->financer->id);
    }

    #[Test]
    public function it_uses_uuid_as_primary_key(): void
    {
        $answer = new Answer;

        $this->assertTrue($answer->getIncrementing() === false);
        $this->assertEquals('string', $answer->getKeyType());
    }

    #[Test]
    public function it_can_create_an_answer(): void
    {
        // Arrange
        $survey = Survey::factory()->create([
            'title' => ['en-GB' => 'Test Survey'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
        ]);

        $submission = Submission::factory()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $this->user->id,
            'survey_id' => $survey->id,
        ]);

        $question = Question::factory()->create([
            'text' => ['en-GB' => 'Test Question'],
            'financer_id' => $this->financer->id,
        ]);

        // Act
        $answer = Answer::factory()->create([
            'user_id' => $this->user->id,
            'submission_id' => $submission->id,
            'question_id' => $question->id,
            'answer' => [
                'value' => 'Test Answer',
                'additional_info' => 'Some additional information',
            ],
        ]);

        // Assert
        $this->assertInstanceOf(Answer::class, $answer);
        $this->assertDatabaseHas('int_survey_answers', [
            'id' => $answer->id,
            'submission_id' => $submission->id,
            'question_id' => $question->id,
        ]);
    }

    #[Test]
    public function it_belongs_to_a_submission(): void
    {
        // Arrange
        $survey = Survey::factory()->create([
            'title' => ['en-GB' => 'Test Survey'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
        ]);

        $submission = Submission::factory()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $this->user->id,
            'survey_id' => $survey->id,
        ]);

        $answer = Answer::factory()->create([
            'user_id' => $this->user->id,
            'submission_id' => $submission->id,
        ]);

        // Act & Assert
        $this->assertInstanceOf(Submission::class, $answer->submission);
        $this->assertEquals($submission->id, $answer->submission->id);
    }

    #[Test]
    public function it_belongs_to_a_question(): void
    {
        // Arrange
        $question = Question::factory()->create([
            'text' => ['en-GB' => 'Test Question'],
            'financer_id' => $this->financer->id,
        ]);

        $answer = Answer::factory()->create([
            'user_id' => $this->user->id,
            'question_id' => $question->id,
        ]);

        // Act & Assert
        $this->assertInstanceOf(Question::class, $answer->question);
        $this->assertEquals($question->id, $answer->question->id);
    }

    #[Test]
    public function it_casts_answer_as_array(): void
    {
        // Arrange
        $survey = Survey::factory()->create([
            'title' => ['en-GB' => 'Test Survey'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
        ]);

        $submission = Submission::factory()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $this->user->id,
            'survey_id' => $survey->id,
        ]);

        $answerData = [
            'value' => 'Selected option',
            'score' => 8,
            'text_response' => 'This is a great product',
            'metadata' => [
                'ip_address' => '192.168.1.1',
                'user_agent' => 'Mozilla/5.0',
            ],
        ];

        // Act
        $answer = Answer::factory()->create([
            'user_id' => $this->user->id,
            'submission_id' => $submission->id,
            'answer' => $answerData,
        ]);

        // Assert
        $this->assertIsArray($answer->answer);
        $this->assertEquals($answerData, $answer->answer);
        $this->assertEquals('Selected option', $answer->answer['value']);
        $this->assertEquals(8, $answer->answer['score']);
        $this->assertEquals('This is a great product', $answer->answer['text_response']);
        $this->assertIsArray($answer->answer['metadata']);
    }

    #[Test]
    public function it_can_store_simple_answer(): void
    {
        // Arrange
        $survey = Survey::factory()->create([
            'title' => ['en-GB' => 'Test Survey'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
        ]);

        $submission = Submission::factory()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $this->user->id,
            'survey_id' => $survey->id,
        ]);

        // Act
        $answer = Answer::factory()->create([
            'user_id' => $this->user->id,
            'submission_id' => $submission->id,
            'answer' => ['value' => 'Yes'],
        ]);

        // Assert
        $this->assertEquals(['value' => 'Yes'], $answer->answer);
    }

    #[Test]
    public function it_can_store_complex_answer_with_multiple_values(): void
    {
        // Arrange
        $survey = Survey::factory()->create([
            'title' => ['en-GB' => 'Test Survey'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
        ]);

        $submission = Submission::factory()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $this->user->id,
            'survey_id' => $survey->id,
        ]);

        $complexAnswer = [
            'selected_options' => ['option1', 'option2', 'option3'],
            'rankings' => [
                'quality' => 9,
                'price' => 7,
                'service' => 8,
            ],
            'free_text' => 'Overall, I am very satisfied with the service.',
            'timestamp' => now()->toIso8601String(),
        ];

        // Act
        $answer = Answer::factory()->create([
            'user_id' => $this->user->id,
            'submission_id' => $submission->id,
            'answer' => $complexAnswer,
        ]);

        // Assert
        $this->assertEquals($complexAnswer, $answer->answer);
        $this->assertContains('option1', $answer->answer['selected_options']);
        $this->assertEquals(9, $answer->answer['rankings']['quality']);
    }

    #[Test]
    public function it_uses_soft_deletes(): void
    {
        // Arrange
        $survey = Survey::factory()->create([
            'title' => ['en-GB' => 'Test Survey'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
        ]);

        $submission = Submission::factory()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $this->user->id,
            'survey_id' => $survey->id,
        ]);

        $answer = Answer::factory()->create([
            'user_id' => $this->user->id,
            'submission_id' => $submission->id,
        ]);

        // Act
        $answer->delete();

        // Assert
        $this->assertSoftDeleted('int_survey_answers', ['id' => $answer->id]);
        $this->assertNull(Answer::find($answer->id));
        $this->assertNotNull(Answer::withTrashed()->find($answer->id));
    }

    #[Test]
    public function it_has_auditable_trait(): void
    {
        // Arrange
        $survey = Survey::factory()->create([
            'title' => ['en-GB' => 'Test Survey'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
        ]);

        $submission = Submission::factory()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $this->user->id,
            'survey_id' => $survey->id,
        ]);

        // Act
        $answer = Answer::factory()->create([
            'user_id' => $this->user->id,
            'submission_id' => $submission->id,
        ]);

        // Assert
        $this->assertTrue(method_exists($answer, 'audits'));
        $this->assertTrue(method_exists($answer, 'getAuditEvents'));
    }

    #[Test]
    public function it_can_scope_answers_by_submission(): void
    {
        // Arrange
        $survey = Survey::factory()->create([
            'title' => ['en-GB' => 'Test Survey'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
        ]);

        $submission1 = Submission::factory()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $this->user->id,
            'survey_id' => $survey->id,
        ]);

        $submission2 = Submission::factory()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $this->user->id,
            'survey_id' => $survey->id,
        ]);

        Answer::factory()->create(['user_id' => $this->user->id, 'submission_id' => $submission1->id]);
        Answer::factory()->create(['user_id' => $this->user->id, 'submission_id' => $submission1->id]);
        Answer::factory()->create(['user_id' => $this->user->id, 'submission_id' => $submission2->id]);

        // Act
        $submission1Answers = Answer::query()->where('submission_id', $submission1->id)->get();

        // Assert
        $this->assertCount(2, $submission1Answers);
        $this->assertTrue($submission1Answers->every(fn ($answer): bool => $answer->submission_id === $submission1->id));
    }

    #[Test]
    public function it_can_scope_answers_by_question(): void
    {
        // Arrange
        $question1 = Question::factory()->create([
            'text' => ['en-GB' => 'Question 1'],
            'financer_id' => $this->financer->id,
        ]);

        $question2 = Question::factory()->create([
            'text' => ['en-GB' => 'Question 2'],
            'financer_id' => $this->financer->id,
        ]);

        Answer::factory()->create(['user_id' => $this->user->id, 'question_id' => $question1->id]);
        Answer::factory()->create(['user_id' => $this->user->id, 'question_id' => $question1->id]);
        Answer::factory()->create(['user_id' => $this->user->id, 'question_id' => $question2->id]);

        // Act
        $question1Answers = Answer::query()->where('question_id', $question1->id)->get();

        // Assert
        $this->assertCount(2, $question1Answers);
        $this->assertTrue($question1Answers->every(fn ($answer): bool => $answer->question_id === $question1->id));
    }

    #[Test]
    public function it_can_update_answer_data(): void
    {
        // Arrange
        $survey = Survey::factory()->create([
            'title' => ['en-GB' => 'Test Survey'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
        ]);

        Context::add('accessible_financers', [$this->financer->id]);

        $submission = Submission::factory()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $this->user->id,
            'survey_id' => $survey->id,
        ]);

        $answer = Answer::factory()->create([
            'user_id' => $this->user->id,
            'submission_id' => $submission->id,
            'answer' => ['value' => 'Initial answer'],
        ]);

        // Act
        $answer->update([
            'answer' => ['value' => 'Updated answer', 'revised' => true],
        ]);

        // Assert
        $this->assertEquals('Updated answer', $answer->fresh()->answer['value']);
        $this->assertTrue($answer->fresh()->answer['revised']);
    }

    // ==================== HasCreator Trait Tests ====================

    #[Test]
    public function it_automatically_sets_created_by_when_authenticated(): void
    {
        // Arrange
        $survey = Survey::factory()->create(['financer_id' => $this->financer->id]);
        $question = Question::factory()->create(['financer_id' => $this->financer->id]);
        $submission = Submission::factory()->create([
            'user_id' => $this->user->id,
            'financer_id' => $this->financer->id,
            'survey_id' => $survey->id,
        ]);

        // Act
        Auth::login($this->user);
        $answer = Answer::factory()->create([
            'user_id' => $this->user->id,
            'submission_id' => $submission->id,
            'question_id' => $question->id,
            'answer' => ['value' => 'Test answer'],
        ]);

        // Assert
        $this->assertEquals($this->user->id, $answer->created_by);
        $this->assertDatabaseHas('int_survey_answers', [
            'id' => $answer->id,
            'created_by' => $this->user->id,
        ]);
    }

    #[Test]
    public function it_does_not_set_created_by_when_not_authenticated(): void
    {
        // Arrange
        $survey = Survey::factory()->create(['financer_id' => $this->financer->id]);
        $question = Question::factory()->create(['financer_id' => $this->financer->id]);
        $submission = Submission::factory()->create([
            'user_id' => $this->user->id,
            'financer_id' => $this->financer->id,
            'survey_id' => $survey->id,
        ]);

        // Act
        Auth::logout();
        $answer = Answer::factory()->create([
            'user_id' => $this->user->id,
            'submission_id' => $submission->id,
            'question_id' => $question->id,
            'answer' => ['value' => 'Test answer'],
        ]);

        // Assert
        $this->assertNull($answer->created_by);
        $this->assertDatabaseHas('int_survey_answers', [
            'id' => $answer->id,
            'created_by' => null,
        ]);
    }

    #[Test]
    public function it_sets_updated_by_when_updating(): void
    {
        // Arrange
        $updater = ModelFactory::createUser();

        $survey = Survey::factory()->create(['financer_id' => $this->financer->id]);
        $question = Question::factory()->create(['financer_id' => $this->financer->id]);
        $submission = Submission::factory()->create([
            'user_id' => $this->user->id,
            'financer_id' => $this->financer->id,
            'survey_id' => $survey->id,
        ]);

        Auth::login($this->user);
        $answer = Answer::factory()->create([
            'user_id' => $this->user->id,
            'submission_id' => $submission->id,
            'question_id' => $question->id,
            'answer' => ['value' => 'Original answer'],
        ]);

        // Act
        Auth::login($updater);
        $answer->update([
            'answer' => ['value' => 'Updated answer'],
        ]);

        // Assert
        $this->assertEquals($this->user->id, $answer->created_by);
        $this->assertEquals($updater->id, $answer->updated_by);
        $this->assertDatabaseHas('int_survey_answers', [
            'id' => $answer->id,
            'created_by' => $this->user->id,
            'updated_by' => $updater->id,
        ]);
    }

    #[Test]
    public function it_has_creator_relationship(): void
    {
        // Arrange
        $survey = Survey::factory()->create(['financer_id' => $this->financer->id]);
        $question = Question::factory()->create(['financer_id' => $this->financer->id]);
        $submission = Submission::factory()->create([
            'user_id' => $this->user->id,
            'financer_id' => $this->financer->id,
            'survey_id' => $survey->id,
        ]);

        Auth::login($this->user);
        $answer = Answer::factory()->create([
            'user_id' => $this->user->id,
            'submission_id' => $submission->id,
            'question_id' => $question->id,
            'answer' => ['value' => 'Test answer'],
        ]);

        // Act & Assert
        $this->assertInstanceOf(User::class, $answer->creator);
        $this->assertEquals($this->user->id, $answer->creator->id);
        $this->assertEquals($this->user->name, $answer->creator->name);
    }

    #[Test]
    public function it_has_updater_relationship(): void
    {
        // Arrange
        $survey = Survey::factory()->create(['financer_id' => $this->financer->id]);
        $question = Question::factory()->create(['financer_id' => $this->financer->id]);
        $submission = Submission::factory()->create([
            'user_id' => $this->user->id,
            'financer_id' => $this->financer->id,
            'survey_id' => $survey->id,
        ]);

        $submission = Submission::factory()->create([
            'user_id' => $this->user->id,
            'financer_id' => $this->financer->id,
            'survey_id' => $survey->id,
        ]);

        Auth::login($this->user);
        $answer = Answer::factory()->create([
            'user_id' => $this->user->id,
            'submission_id' => $submission->id,
            'question_id' => $question->id,
            'answer' => ['value' => 'Original answer'],
        ]);

        // Act
        Auth::login($this->user);
        $answer->update([
            'answer' => ['value' => 'Updated answer'],
        ]);

        // Assert
        $this->assertInstanceOf(User::class, $answer->updater);
        $this->assertEquals($this->user->id, $answer->updater->id);
        $this->assertEquals($this->user->name, $answer->updater->name);
    }

    #[Test]
    public function it_can_check_if_was_created_by_user(): void
    {
        // Arrange
        $survey = Survey::factory()->create(['financer_id' => $this->financer->id]);
        $question = Question::factory()->create(['financer_id' => $this->financer->id]);
        $otherUser = ModelFactory::createUser();

        $submission = Submission::factory()->create([
            'user_id' => $this->user->id,
            'financer_id' => $this->financer->id,
            'survey_id' => $survey->id,
        ]);

        Auth::login($this->user);
        $answer = Answer::factory()->create([
            'user_id' => $this->user->id,
            'submission_id' => $submission->id,
            'question_id' => $question->id,
            'answer' => ['value' => 'Test answer'],
        ]);

        // Act & Assert
        $this->assertTrue($answer->wasCreatedBy($this->user));
        $this->assertFalse($answer->wasCreatedBy($otherUser));
        $this->assertFalse($answer->wasCreatedBy(null));
    }

    #[Test]
    public function it_can_check_if_was_updated_by_user(): void
    {
        // Arrange
        $updater = ModelFactory::createUser();
        $otherUser = ModelFactory::createUser();
        $survey = Survey::factory()->create(['financer_id' => $this->financer->id]);
        $question = Question::factory()->create(['financer_id' => $this->financer->id]);

        $submission = Submission::factory()->create([
            'user_id' => $this->user->id,
            'financer_id' => $this->financer->id,
            'survey_id' => $survey->id,
        ]);

        Auth::login($this->user);
        $answer = Answer::factory()->create([
            'user_id' => $this->user->id,
            'submission_id' => $submission->id,
            'question_id' => $question->id,
            'answer' => ['value' => 'Original answer'],
        ]);

        // Act
        Auth::login($updater);
        $answer->update([
            'answer' => ['value' => 'Updated answer'],
        ]);

        // Assert
        $this->assertTrue($answer->wasUpdatedBy($updater));
        $this->assertFalse($answer->wasUpdatedBy($this->user));
        $this->assertFalse($answer->wasUpdatedBy($otherUser));
        $this->assertFalse($answer->wasUpdatedBy(null));
    }
}
