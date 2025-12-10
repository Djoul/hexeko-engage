<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Survey\Http\Controllers\V1\Me;

use App\Enums\IDP\PermissionDefaults;
use App\Integrations\Survey\Database\factories\SubmissionFactory;
use App\Integrations\Survey\Database\factories\SurveyFactory;
use App\Integrations\Survey\Enums\SurveyStatusEnum;
use App\Models\Permission;
use App\Models\User;
use Database\Factories\FinancerFactory;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Modules\Survey\Http\Controllers\V1\SurveyTestCase;

#[Group('survey')]
#[Group('submission')]
class SubmissionControllerTest extends SurveyTestCase
{
    #[Test]
    public function it_can_list_user_submissions(): void
    {
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);

        resolve(SubmissionFactory::class)->count(3)->create([
            'user_id' => $this->auth->id,
            'financer_id' => $this->financer->id,
            'survey_id' => $survey->id,
        ]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('me.survey.submissions.index', ['financer_id' => $this->financer->id]));

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'financer_id',
                        'user_id',
                        'survey_id',
                        'started_at',
                        'completed_at',
                        'created_at',
                        'updated_at',
                    ],
                ],
                'links',
                'meta',
            ]);
    }

    #[Test]
    public function it_filters_submissions_by_financer_id(): void
    {
        $otherFinancer = resolve(FinancerFactory::class)->create();
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);
        $otherSurvey = resolve(SurveyFactory::class)->create(['financer_id' => $otherFinancer->id]);

        resolve(SubmissionFactory::class)->create([
            'user_id' => $this->auth->id,
            'financer_id' => $this->financer->id,
            'survey_id' => $survey->id,
        ]);
        resolve(SubmissionFactory::class)->create([
            'user_id' => $this->auth->id,
            'financer_id' => $otherFinancer->id,
            'survey_id' => $otherSurvey->id,
        ]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('me.survey.submissions.index', ['financer_id' => $this->financer->id]));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'financer_id',
                        'user_id',
                        'survey_id',
                        'started_at',
                        'completed_at',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);
    }

    #[Test]
    public function it_filters_submissions_by_survey_id(): void
    {
        $survey1 = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);
        $survey2 = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);

        resolve(SubmissionFactory::class)->create([
            'user_id' => $this->auth->id,
            'financer_id' => $this->financer->id,
            'survey_id' => $survey1->id,
        ]);
        resolve(SubmissionFactory::class)->create([
            'user_id' => $this->auth->id,
            'financer_id' => $this->financer->id,
            'survey_id' => $survey2->id,
        ]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('me.survey.submissions.index', ['financer_id' => $this->financer->id, 'survey_id' => $survey1->id]));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function it_filters_submissions_by_created_at_date(): void
    {
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);

        resolve(SubmissionFactory::class)->create([
            'user_id' => $this->auth->id,
            'financer_id' => $this->financer->id,
            'survey_id' => $survey->id,
            'created_at' => '2023-01-01',
        ]);
        resolve(SubmissionFactory::class)->create([
            'user_id' => $this->auth->id,
            'financer_id' => $this->financer->id,
            'survey_id' => $survey->id,
            'created_at' => '2024-01-02',
        ]);
        resolve(SubmissionFactory::class)->create([
            'user_id' => $this->auth->id,
            'financer_id' => $this->financer->id,
            'survey_id' => $survey->id,
            'created_at' => '2024-01-03',
        ]);

        $response = $this->actingAs($this->auth)->getJson(route('me.survey.submissions.index', [
            'financer_id' => $this->financer->id,
            'created_at' => '2024-01-02',
        ]));

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function it_persists_and_redirects_when_storing(): void
    {
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);

        $payload = [
            'financer_id' => $this->financer->id,
            'survey_id' => $survey->id,
        ];

        $response = $this->actingAs($this->auth)
            ->postJson(route('me.survey.submissions.store', ['financer_id' => $this->financer->id]), $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('int_survey_submissions', [
            'financer_id' => $this->financer->id,
            'survey_id' => $survey->id,
            'user_id' => $this->auth->id,
        ]);

        $response->assertJsonStructure([
            'data' => [
                'id',
                'financer_id',
                'user_id',
                'survey_id',
                'started_at',
                'completed_at',
                'created_at',
                'updated_at',
            ],
        ]);
    }

    #[Test]
    public function it_cannot_show_other_users_submission(): void
    {
        $otherFinancer = resolve(FinancerFactory::class)->create();
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $otherFinancer->id]);
        $otherUser = User::factory()->create();
        $otherUser->financers()->attach($otherFinancer->id, ['active' => true]);

        $otherSubmission = resolve(SubmissionFactory::class)->create([
            'user_id' => $otherUser->id,
            'financer_id' => $otherFinancer->id,
            'survey_id' => $survey->id,
        ]);

        Context::add('accessible_financers', [$this->financer->id, $otherFinancer->id]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('me.survey.submissions.show', ['submission' => $otherSubmission, 'financer_id' => $this->financer->id]));

        $response->assertStatus(404);
    }

    #[Test]
    public function user_with_permission_can_show_submission_from_same_financer(): void
    {
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);
        $submission = resolve(SubmissionFactory::class)->create([
            'user_id' => $this->auth->id,
            'financer_id' => $this->financer->id,
            'survey_id' => $survey->id,
        ]);

        // Create permission and assign to user
        Permission::firstOrCreate([
            'name' => PermissionDefaults::MANAGE_FINANCER_SUBMISSIONS,
            'guard_name' => 'api',
        ]);
        $this->auth->givePermissionTo(PermissionDefaults::MANAGE_FINANCER_SUBMISSIONS);

        $response = $this->actingAs($this->auth)
            ->getJson(route('me.survey.submissions.show', ['submission' => $submission, 'financer_id' => $this->financer->id]));

        $response->assertStatus(200);
    }

    #[Test]
    public function it_persists_changes_and_redirects_when_updating(): void
    {
        $otherFinancer = resolve(FinancerFactory::class)->create();
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);

        Context::add('accessible_financers', [$this->financer->id, $otherFinancer->id]);

        $submission = resolve(SubmissionFactory::class)->create([
            'user_id' => $this->auth->id,
            'financer_id' => $this->financer->id,
            'survey_id' => $survey->id,
        ]);

        $payload = [
            'financer_id' => $this->financer->id,
        ];

        $response = $this->actingAs($this->auth)
            ->putJson(route('me.survey.submissions.update', ['submission' => $submission, 'financer_id' => $this->financer->id]), $payload);

        $response->assertOk();

        $this->assertDatabaseHas('int_survey_submissions', [
            'id' => $submission->id,
            'financer_id' => $this->financer->id,
        ]);

        $response->assertJsonStructure([
            'data' => [
                'id',
                'financer_id',
                'user_id',
                'survey_id',
                'started_at',
                'completed_at',
                'created_at',
                'updated_at',
            ],
        ]);
    }

    #[Test]
    public function it_cannot_update_other_users_submission(): void
    {
        $otherFinancer = resolve(FinancerFactory::class)->create();
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $otherFinancer->id]);
        $otherUser = User::factory()->create();
        $otherUser->financers()->attach($otherFinancer->id, ['active' => true]);

        $otherSubmission = resolve(SubmissionFactory::class)->create([
            'user_id' => $otherUser->id,
            'financer_id' => $otherFinancer->id,
            'survey_id' => $survey->id,
        ]);

        $updateData = [
            'financer_id' => $this->financer->id,
        ];

        Context::add('accessible_financers', [$this->financer->id, $otherFinancer->id]);

        $response = $this->actingAs($this->auth)
            ->putJson(route('me.survey.submissions.update', ['submission' => $otherSubmission, 'financer_id' => $this->financer->id]), $updateData);

        $response->assertStatus(404);
    }

    #[Test]
    public function it_deletes_and_redirects(): void
    {
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);
        $submission = resolve(SubmissionFactory::class)->create([
            'user_id' => $this->auth->id,
            'financer_id' => $this->financer->id,
            'survey_id' => $survey->id,
        ]);

        Context::add('accessible_financers', [$this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->deleteJson(route('me.survey.submissions.destroy', ['submission' => $submission, 'financer_id' => $this->financer->id]));

        $response->assertStatus(204);
        $this->assertSoftDeleted('int_survey_submissions', ['id' => $submission->id]);
    }

    #[Test]
    public function it_cannot_delete_other_users_submission(): void
    {
        $otherFinancer = resolve(FinancerFactory::class)->create();
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $otherFinancer->id]);
        $otherUser = User::factory()->create();
        $otherUser->financers()->attach($otherFinancer->id, ['active' => true]);

        Context::add('accessible_financers', [$this->financer->id, $otherFinancer->id]);

        $otherSubmission = resolve(SubmissionFactory::class)->create([
            'user_id' => $otherUser->id,
            'financer_id' => $otherFinancer->id,
            'survey_id' => $survey->id,
        ]);

        $response = $this->actingAs($this->auth)
            ->deleteJson(route('me.survey.submissions.destroy', ['submission' => $otherSubmission, 'financer_id' => $this->financer->id]));

        $response->assertStatus(404);
    }

    #[Test]
    public function user_with_permission_can_delete_submission_from_same_financer(): void
    {
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);
        $submission = resolve(SubmissionFactory::class)->create([
            'user_id' => $this->auth->id,
            'financer_id' => $this->financer->id,
            'survey_id' => $survey->id,
        ]);

        Context::add('accessible_financers', [$this->financer->id]);

        // Create permission and assign to user
        Permission::firstOrCreate([
            'name' => PermissionDefaults::MANAGE_FINANCER_SUBMISSIONS,
            'guard_name' => 'api',
        ]);
        $this->auth->givePermissionTo(PermissionDefaults::MANAGE_FINANCER_SUBMISSIONS);

        $response = $this->actingAs($this->auth)
            ->deleteJson(route('me.survey.submissions.destroy', ['submission' => $submission, 'financer_id' => $this->financer->id]));

        $response->assertStatus(204);

        $this->assertSoftDeleted('int_survey_submissions', [
            'id' => $submission->id,
        ]);
    }

    #[Test]
    public function it_can_complete_a_submission(): void
    {
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id, 'status' => SurveyStatusEnum::PUBLISHED]);
        $submission = resolve(SubmissionFactory::class)->create([
            'user_id' => $this->auth->id,
            'financer_id' => $this->financer->id,
            'survey_id' => $survey->id,
        ]);

        Context::add('accessible_financers', [$this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->postJson(route('me.survey.submissions.complete', ['submission' => $submission, 'financer_id' => $this->financer->id]));

        $response->assertStatus(200);
        $this->assertNotNull($submission->refresh()->completed_at);
    }

    #[Test]
    public function it_cannot_complete_other_users_submission(): void
    {
        $otherFinancer = resolve(FinancerFactory::class)->create();
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $otherFinancer->id, 'status' => SurveyStatusEnum::PUBLISHED]);
        $otherUser = User::factory()->create();
        $otherUser->financers()->attach($otherFinancer->id, ['active' => true]);

        $otherSubmission = resolve(SubmissionFactory::class)->create([
            'user_id' => $otherUser->id,
            'financer_id' => $otherFinancer->id,
            'survey_id' => $survey->id,
        ]);

        Context::add('accessible_financers', [$this->financer->id, $otherFinancer->id]);

        $response = $this->actingAs($this->auth)
            ->postJson(route('me.survey.submissions.complete', ['submission' => $otherSubmission, 'financer_id' => $this->financer->id]));

        $response->assertStatus(404);
    }

    #[Test]
    public function user_with_permission_can_complete_submission_from_same_financer(): void
    {
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id, 'status' => SurveyStatusEnum::PUBLISHED]);
        $submission = resolve(SubmissionFactory::class)->create([
            'user_id' => $this->auth->id,
            'financer_id' => $this->financer->id,
            'survey_id' => $survey->id,
        ]);

        // Create permission and assign to user
        Permission::firstOrCreate([
            'name' => PermissionDefaults::MANAGE_FINANCER_SUBMISSIONS,
            'guard_name' => 'api',
        ]);
        $this->auth->givePermissionTo(PermissionDefaults::MANAGE_FINANCER_SUBMISSIONS);

        $response = $this->actingAs($this->auth)
            ->postJson(route('me.survey.submissions.complete', ['submission' => $submission, 'financer_id' => $this->financer->id]));

        $response->assertStatus(200);

        $this->assertNotNull($submission->refresh()->completed_at);
    }
}
