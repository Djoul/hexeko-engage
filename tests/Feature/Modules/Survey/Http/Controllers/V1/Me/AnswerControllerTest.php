<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Survey\Http\Controllers\V1\Me;

use App\Enums\IDP\PermissionDefaults;
use App\Integrations\Survey\Database\factories\AnswerFactory;
use App\Integrations\Survey\Database\factories\QuestionFactory;
use App\Integrations\Survey\Database\factories\SubmissionFactory;
use App\Integrations\Survey\Database\factories\SurveyFactory;
use App\Integrations\Survey\Enums\QuestionTypeEnum;
use App\Integrations\Survey\Enums\SurveyStatusEnum;
use App\Models\Permission;
use App\Models\User;
use Database\Factories\FinancerFactory;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Modules\Survey\Http\Controllers\V1\SurveyTestCase;

#[Group('survey')]
#[Group('answer')]
class AnswerControllerTest extends SurveyTestCase
{
    #[Test]
    public function it_can_create_an_answer(): void
    {
        $survey = resolve(SurveyFactory::class)->create([
            'financer_id' => $this->financer->id,
            'status' => SurveyStatusEnum::PUBLISHED,
        ]);

        $question = resolve(QuestionFactory::class)->create([
            'financer_id' => $this->financer->id,
            'type' => QuestionTypeEnum::TEXT,
        ]);

        // Attach question to survey
        $survey->questions()->attach([$question->id]);

        $submission = resolve(SubmissionFactory::class)->create([
            'user_id' => $this->auth->id,
            'financer_id' => $this->financer->id,
            'survey_id' => $survey->id,
        ]);

        $payload = [
            'submission_id' => $submission->id,
            'question_id' => $question->id,
            'answer' => ['value' => 'My answer'],
        ];

        Context::add('accessible_financers', [$this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->postJson(route('me.survey.answers.store', ['financer_id' => $this->financer->id]), $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('int_survey_answers', [
            'user_id' => $this->auth->id,
            'submission_id' => $submission->id,
            'question_id' => $question->id,
        ]);

        $response->assertJsonStructure([
            'data' => [
                'id',
                'submission_id',
                'question_id',
                'answer',
                'created_at',
                'updated_at',
            ],
        ]);
    }

    #[Test]
    public function it_cannot_create_duplicate_answer_for_same_question(): void
    {
        $survey = resolve(SurveyFactory::class)->create([
            'financer_id' => $this->financer->id,
            'status' => SurveyStatusEnum::PUBLISHED,
        ]);

        $question = resolve(QuestionFactory::class)->create([
            'financer_id' => $this->financer->id,
            'type' => QuestionTypeEnum::TEXT,
        ]);

        // Attach question to survey
        $survey->questions()->attach([$question->id]);

        $submission = resolve(SubmissionFactory::class)->create([
            'user_id' => $this->auth->id,
            'financer_id' => $this->financer->id,
            'survey_id' => $survey->id,
        ]);

        // Create first answer
        resolve(AnswerFactory::class)->create([
            'user_id' => $this->auth->id,
            'submission_id' => $submission->id,
            'question_id' => $question->id,
        ]);

        $payload = [
            'submission_id' => $submission->id,
            'question_id' => $question->id,
            'answer' => ['value' => 'Duplicate answer'],
        ];

        Context::add('accessible_financers', [$this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->postJson(route('me.survey.answers.store', ['financer_id' => $this->financer->id]), $payload);

        $response->assertStatus(409);
    }

    #[Test]
    public function it_can_show_an_answer(): void
    {
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);
        $question = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);
        $submission = resolve(SubmissionFactory::class)->create([
            'user_id' => $this->auth->id,
            'financer_id' => $this->financer->id,
            'survey_id' => $survey->id,
        ]);

        $answer = resolve(AnswerFactory::class)->create([
            'user_id' => $this->auth->id,
            'submission_id' => $submission->id,
            'question_id' => $question->id,
            'answer' => ['value' => 'Test answer'],
        ]);

        Context::add('accessible_financers', [$this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('me.survey.answers.show', ['answer' => $answer, 'financer_id' => $this->financer->id]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'submission_id',
                    'question_id',
                    'answer',
                    'created_at',
                    'updated_at',
                ],
            ])
            ->assertJsonFragment([
                'id' => $answer->id,
            ]);
    }

    #[Test]
    public function it_cannot_show_other_users_answer(): void
    {
        $otherFinancer = resolve(FinancerFactory::class)->create();
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $otherFinancer->id]);
        $question = resolve(QuestionFactory::class)->create(['financer_id' => $otherFinancer->id]);
        $otherUser = User::factory()->create();
        $otherUser->financers()->attach($otherFinancer->id, ['active' => true]);

        $otherSubmission = resolve(SubmissionFactory::class)->create([
            'user_id' => $otherUser->id,
            'financer_id' => $otherFinancer->id,
            'survey_id' => $survey->id,
        ]);

        $otherAnswer = resolve(AnswerFactory::class)->create([
            'user_id' => $otherUser->id,
            'submission_id' => $otherSubmission->id,
            'question_id' => $question->id,
        ]);

        Context::add('accessible_financers', [$this->financer->id, $otherFinancer->id]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('me.survey.answers.show', ['answer' => $otherAnswer, 'financer_id' => $this->financer->id]));

        $response->assertStatus(403);
    }

    #[Test]
    public function it_can_update_an_answer(): void
    {
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);
        $question = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);
        $submission = resolve(SubmissionFactory::class)->create([
            'user_id' => $this->auth->id,
            'financer_id' => $this->financer->id,
            'survey_id' => $survey->id,
        ]);

        $answer = resolve(AnswerFactory::class)->create([
            'user_id' => $this->auth->id,
            'submission_id' => $submission->id,
            'question_id' => $question->id,
            'answer' => ['value' => 'Original answer'],
        ]);

        $payload = [
            'answer' => ['value' => 'Updated answer'],
        ];

        Context::add('accessible_financers', [$this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->putJson(route('me.survey.answers.update', ['answer' => $answer, 'financer_id' => $this->financer->id]), $payload);

        $response->assertOk();

        $this->assertDatabaseHas('int_survey_answers', [
            'id' => $answer->id,
        ]);

        $response->assertJsonStructure([
            'data' => [
                'id',
                'submission_id',
                'question_id',
                'answer',
                'created_at',
                'updated_at',
            ],
        ]);
    }

    #[Test]
    public function it_cannot_update_other_users_answer(): void
    {
        $otherFinancer = resolve(FinancerFactory::class)->create();
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $otherFinancer->id]);
        $question = resolve(QuestionFactory::class)->create(['financer_id' => $otherFinancer->id]);
        $otherUser = User::factory()->create();
        $otherUser->financers()->attach($otherFinancer->id, ['active' => true]);

        $otherSubmission = resolve(SubmissionFactory::class)->create([
            'user_id' => $otherUser->id,
            'financer_id' => $otherFinancer->id,
            'survey_id' => $survey->id,
        ]);

        $otherAnswer = resolve(AnswerFactory::class)->create([
            'user_id' => $otherUser->id,
            'submission_id' => $otherSubmission->id,
            'question_id' => $question->id,
        ]);

        $updateData = [
            'answer' => ['value' => 'Hacked answer'],
        ];

        Context::add('accessible_financers', [$this->financer->id, $otherFinancer->id]);

        $response = $this->actingAs($this->auth)
            ->putJson(route('me.survey.answers.update', ['answer' => $otherAnswer, 'financer_id' => $this->financer->id]), $updateData);

        $response->assertStatus(403);
    }

    #[Test]
    public function it_can_delete_an_answer(): void
    {
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);
        $question = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);
        $submission = resolve(SubmissionFactory::class)->create([
            'user_id' => $this->auth->id,
            'financer_id' => $this->financer->id,
            'survey_id' => $survey->id,
        ]);

        $answer = resolve(AnswerFactory::class)->create([
            'user_id' => $this->auth->id,
            'submission_id' => $submission->id,
            'question_id' => $question->id,
        ]);

        Context::add('accessible_financers', [$this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->deleteJson(route('me.survey.answers.destroy', ['answer' => $answer, 'financer_id' => $this->financer->id]));

        $response->assertStatus(204);
        $this->assertSoftDeleted('int_survey_answers', ['id' => $answer->id]);
    }

    #[Test]
    public function it_cannot_delete_other_users_answer(): void
    {
        $otherFinancer = resolve(FinancerFactory::class)->create();
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $otherFinancer->id]);
        $question = resolve(QuestionFactory::class)->create(['financer_id' => $otherFinancer->id]);
        $otherUser = User::factory()->create();
        $otherUser->financers()->attach($otherFinancer->id, ['active' => true]);

        $otherSubmission = resolve(SubmissionFactory::class)->create([
            'user_id' => $otherUser->id,
            'financer_id' => $otherFinancer->id,
            'survey_id' => $survey->id,
        ]);

        $otherAnswer = resolve(AnswerFactory::class)->create([
            'user_id' => $otherUser->id,
            'submission_id' => $otherSubmission->id,
            'question_id' => $question->id,
        ]);

        Context::add('accessible_financers', [$this->financer->id, $otherFinancer->id]);

        $response = $this->actingAs($this->auth)
            ->deleteJson(route('me.survey.answers.destroy', ['answer' => $otherAnswer, 'financer_id' => $this->financer->id]));

        $response->assertStatus(403);
    }

    #[Test]
    public function user_with_permission_can_update_answer_from_same_financer(): void
    {
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);
        $question = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);
        $submission = resolve(SubmissionFactory::class)->create([
            'user_id' => $this->auth->id,
            'financer_id' => $this->financer->id,
            'survey_id' => $survey->id,
        ]);

        $answer = resolve(AnswerFactory::class)->create([
            'user_id' => $this->auth->id,
            'submission_id' => $submission->id,
            'question_id' => $question->id,
            'answer' => ['value' => 'Original'],
        ]);

        // Create permission and assign to user
        Permission::firstOrCreate([
            'name' => PermissionDefaults::MANAGE_FINANCER_ANSWERS,
            'guard_name' => 'api',
        ]);
        $this->auth->givePermissionTo(PermissionDefaults::MANAGE_FINANCER_ANSWERS);

        $payload = [
            'answer' => ['value' => 'Updated by permission'],
        ];

        $response = $this->actingAs($this->auth)
            ->putJson(route('me.survey.answers.update', ['answer' => $answer, 'financer_id' => $this->financer->id]), $payload);

        $response->assertOk();
    }

    #[Test]
    public function it_returns_answer_with_loaded_relationships(): void
    {
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);
        $question = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);
        $submission = resolve(SubmissionFactory::class)->create([
            'user_id' => $this->auth->id,
            'financer_id' => $this->financer->id,
            'survey_id' => $survey->id,
        ]);

        $answer = resolve(AnswerFactory::class)->create([
            'user_id' => $this->auth->id,
            'submission_id' => $submission->id,
            'question_id' => $question->id,
        ]);

        Context::add('accessible_financers', [$this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('me.survey.answers.show', ['answer' => $answer, 'financer_id' => $this->financer->id]));

        $response->assertStatus(200)
            ->assertJsonFragment([
                'id' => $answer->id,
                'submission_id' => $submission->id,
                'question_id' => $question->id,
            ]);
    }

    #[Test]
    public function it_validates_required_fields_on_create(): void
    {
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);
        $question = resolve(QuestionFactory::class)->create([
            'financer_id' => $this->financer->id,
            'type' => QuestionTypeEnum::TEXT,
        ]);

        $survey->questions()->attach([$question->id]);

        $submission = resolve(SubmissionFactory::class)->create([
            'user_id' => $this->auth->id,
            'financer_id' => $this->financer->id,
            'survey_id' => $survey->id,
        ]);

        // Missing answer field
        $payload = [
            'submission_id' => $submission->id,
            'question_id' => $question->id,
        ];

        Context::add('accessible_financers', [$this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->postJson(route('me.survey.answers.store', ['financer_id' => $this->financer->id]), $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['answer']);
    }

    #[Test]
    public function it_validates_required_fields_on_update(): void
    {
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);
        $question = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);
        $submission = resolve(SubmissionFactory::class)->create([
            'user_id' => $this->auth->id,
            'financer_id' => $this->financer->id,
            'survey_id' => $survey->id,
        ]);

        $answer = resolve(AnswerFactory::class)->create([
            'user_id' => $this->auth->id,
            'submission_id' => $submission->id,
            'question_id' => $question->id,
        ]);

        $payload = [];

        Context::add('accessible_financers', [$this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->putJson(route('me.survey.answers.update', ['answer' => $answer, 'financer_id' => $this->financer->id]), $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['answer']);
    }

    #[Test]
    public function it_creates_answer_with_array_value(): void
    {
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);
        $question = resolve(QuestionFactory::class)->create([
            'financer_id' => $this->financer->id,
            'type' => QuestionTypeEnum::MULTIPLE_CHOICE,
        ]);

        // Attach question to survey
        $survey->questions()->attach([$question->id]);

        $submission = resolve(SubmissionFactory::class)->create([
            'user_id' => $this->auth->id,
            'financer_id' => $this->financer->id,
            'survey_id' => $survey->id,
        ]);

        $payload = [
            'submission_id' => $submission->id,
            'question_id' => $question->id,
            'answer' => ['value' => ['option1', 'option2']],
        ];

        Context::add('accessible_financers', [$this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->postJson(route('me.survey.answers.store', ['financer_id' => $this->financer->id]), $payload);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'answer' => ['value' => ['option1', 'option2']],
            ]);
    }

    #[Test]
    public function it_creates_answer_with_numeric_value(): void
    {
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);
        $question = resolve(QuestionFactory::class)->create([
            'financer_id' => $this->financer->id,
            'type' => QuestionTypeEnum::SCALE,
        ]);

        // Attach question to survey
        $survey->questions()->attach([$question->id]);

        $submission = resolve(SubmissionFactory::class)->create([
            'user_id' => $this->auth->id,
            'financer_id' => $this->financer->id,
            'survey_id' => $survey->id,
        ]);

        $payload = [
            'submission_id' => $submission->id,
            'question_id' => $question->id,
            'answer' => ['value' => '8'],
        ];

        Context::add('accessible_financers', [$this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->postJson(route('me.survey.answers.store', ['financer_id' => $this->financer->id]), $payload);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'answer' => ['value' => '8'],
            ]);
    }
}
