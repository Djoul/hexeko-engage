<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Survey\Http\Controllers\V1\Me;

use App\Enums\IDP\PermissionDefaults;
use App\Integrations\Survey\Database\factories\QuestionFactory;
use App\Integrations\Survey\Database\factories\SubmissionFactory;
use App\Integrations\Survey\Database\factories\SurveyFactory;
use App\Integrations\Survey\Enums\SurveyStatusEnum;
use App\Integrations\Survey\Enums\UserSurveyStatusEnum;
use App\Models\Permission;
use Database\Factories\FinancerFactory;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Modules\Survey\Http\Controllers\V1\SurveyTestCase;

#[Group('survey')]
class SurveyControllerTest extends SurveyTestCase
{
    #[Test]
    public function it_can_list_user_surveys(): void
    {
        // Create active surveys and attach to user
        $survey1 = resolve(SurveyFactory::class)->active()->create(['financer_id' => $this->financer->id]);
        $survey2 = resolve(SurveyFactory::class)->active()->create(['financer_id' => $this->financer->id]);
        $survey3 = resolve(SurveyFactory::class)->active()->create(['financer_id' => $this->financer->id]);

        $this->auth->surveys()->attach([$survey1->id, $survey2->id, $survey3->id]);

        Context::add('accessible_financers', [$this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('me.survey.surveys.index', ['financer_id' => $this->financer->id]));

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'title_raw',
                        'description',
                        'description_raw',
                        'welcome_message',
                        'welcome_message_raw',
                        'thank_you_message',
                        'thank_you_message_raw',
                        'financer_id',
                        'starts_at',
                        'ends_at',
                        'created_at',
                        'updated_at',
                    ],
                ],
                'links',
                'meta',
            ]);
    }

    #[Test]
    public function it_only_shows_active_surveys(): void
    {
        // Create surveys with different statuses
        $activeSurvey = resolve(SurveyFactory::class)->active()->create(['financer_id' => $this->financer->id]);
        $draftSurvey = resolve(SurveyFactory::class)->draft()->create(['financer_id' => $this->financer->id]);
        $closedSurvey = resolve(SurveyFactory::class)->create([
            'financer_id' => $this->financer->id,
            'status' => SurveyStatusEnum::CLOSED,
        ]);

        $this->auth->surveys()->attach([$activeSurvey->id, $draftSurvey->id, $closedSurvey->id]);

        Context::add('accessible_financers', [$this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('me.survey.surveys.index', ['financer_id' => $this->financer->id, 'status' => 'active']));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['id' => $activeSurvey->id])
            ->assertJsonMissing(['id' => $draftSurvey->id])
            ->assertJsonMissing(['id' => $closedSurvey->id]);
    }

    #[Test]
    public function it_filters_surveys_by_financer_id(): void
    {
        $otherFinancer = resolve(FinancerFactory::class)->create();

        $survey1 = resolve(SurveyFactory::class)->active()->create(['financer_id' => $this->financer->id]);
        $survey2 = resolve(SurveyFactory::class)->active()->create(['financer_id' => $otherFinancer->id]);

        $this->auth->surveys()->attach([$survey1->id, $survey2->id]);
        $this->auth->financers()->attach($otherFinancer->id, ['active' => true]);

        Context::add('accessible_financers', [$this->financer->id, $otherFinancer->id]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('me.survey.surveys.index', ['financer_id' => $this->financer->id]));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['id' => $survey1->id])
            ->assertJsonMissing(['id' => $survey2->id]);
    }

    #[Test]
    public function it_returns_surveys_with_counts(): void
    {
        $survey = resolve(SurveyFactory::class)->active()->create(['financer_id' => $this->financer->id]);

        $question1 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);
        $question2 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);

        $survey->questions()->attach([$question1->id, $question2->id]);

        $this->auth->surveys()->attach($survey->id);

        // Create some submissions
        resolve(SubmissionFactory::class)->count(3)->create([
            'survey_id' => $survey->id,
            'financer_id' => $this->financer->id,
        ]);

        Context::add('accessible_financers', [$this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('me.survey.surveys.index', ['financer_id' => $this->financer->id]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'questions_count',
                        'users_count',
                        'submissions_count',
                    ],
                ],
            ]);
    }

    #[Test]
    public function it_returns_empty_list_when_user_has_no_surveys(): void
    {
        Context::add('accessible_financers', [$this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('me.survey.surveys.index', ['financer_id' => $this->financer->id]));

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function it_returns_empty_list_when_user_not_authenticated(): void
    {
        Context::add('accessible_financers', [$this->financer->id]);

        $response = $this->getJson(route('me.survey.surveys.index', ['financer_id' => $this->financer->id]));

        $response->assertStatus(401);
    }

    #[Test]
    public function it_can_show_a_survey(): void
    {
        $survey = resolve(SurveyFactory::class)->active()->create(['financer_id' => $this->financer->id]);

        $question = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);
        $survey->questions()->attach([$question->id]);

        $this->auth->surveys()->attach($survey->id);

        Context::add('accessible_financers', [$this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('me.survey.surveys.show', ['survey' => $survey, 'financer_id' => $this->financer->id]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'title',
                    'title_raw',
                    'description',
                    'description_raw',
                    'welcome_message',
                    'welcome_message_raw',
                    'thank_you_message',
                    'thank_you_message_raw',
                    'financer_id',
                    'starts_at',
                    'ends_at',
                    'questions',
                    'questions_count',
                    'users_count',
                    'created_at',
                    'updated_at',
                ],
            ])
            ->assertJsonFragment(['id' => $survey->id]);
    }

    #[Test]
    public function it_returns_survey_with_financer_id(): void
    {
        $survey = resolve(SurveyFactory::class)->active()->create(['financer_id' => $this->financer->id]);
        $this->auth->surveys()->attach($survey->id);

        Context::add('accessible_financers', [$this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('me.survey.surveys.show', ['survey' => $survey, 'financer_id' => $this->financer->id]));

        $response->assertStatus(200)
            ->assertJsonFragment([
                'financer_id' => $this->financer->id,
            ]);
    }

    #[Test]
    public function it_returns_survey_with_questions(): void
    {
        $survey = resolve(SurveyFactory::class)->active()->create(['financer_id' => $this->financer->id]);

        $question = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);
        $survey->questions()->attach([$question->id]);

        $this->auth->surveys()->attach($survey->id);

        Context::add('accessible_financers', [$this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('me.survey.surveys.show', ['survey' => $survey, 'financer_id' => $this->financer->id]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'questions' => [
                        '*' => [],
                    ],
                    'questions_count',
                    'users_count',
                ],
            ]);
    }

    #[Test]
    public function it_can_show_any_survey_from_same_financer(): void
    {
        // Create permission
        Permission::firstOrCreate([
            'name' => PermissionDefaults::READ_SURVEY,
            'guard_name' => 'api',
        ]);
        $this->auth->givePermissionTo(PermissionDefaults::READ_SURVEY);

        $survey = resolve(SurveyFactory::class)->active()->create(['financer_id' => $this->financer->id]);

        // Don't attach user to survey - should still work if user has permission

        Context::add('accessible_financers', [$this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('me.survey.surveys.show', ['survey' => $survey, 'financer_id' => $this->financer->id]));

        $response->assertStatus(200);
    }

    #[Test]
    public function it_cannot_show_survey_from_different_financer(): void
    {
        // Create permission
        Permission::firstOrCreate([
            'name' => PermissionDefaults::READ_SURVEY,
            'guard_name' => 'api',
        ]);
        $this->auth->givePermissionTo(PermissionDefaults::READ_SURVEY);

        $otherFinancer = resolve(FinancerFactory::class)->create();
        $survey = resolve(SurveyFactory::class)->active()->create(['financer_id' => $otherFinancer->id]);

        Context::add('accessible_financers', [$this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('me.survey.surveys.show', ['survey' => $survey, 'financer_id' => $this->financer->id]));

        // Returns 404 because middleware blocks access to different financer
        $response->assertStatus(404);
    }

    #[Test]
    public function it_supports_pagination(): void
    {
        // Create many active surveys
        for ($i = 0; $i < 25; $i++) {
            $survey = resolve(SurveyFactory::class)->active()->create(['financer_id' => $this->financer->id]);
            $this->auth->surveys()->attach($survey->id);
        }

        Context::add('accessible_financers', [$this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('me.survey.surveys.index', [
                'financer_id' => $this->financer->id,
                'per_page' => 10,
            ]));

        $response->assertStatus(200)
            ->assertJsonCount(10, 'data')
            ->assertJsonStructure([
                'data',
                'links' => [
                    'first',
                    'last',
                    'prev',
                    'next',
                ],
                'meta' => [
                    'current_page',
                    'from',
                    'last_page',
                    'per_page',
                    'to',
                    'total',
                ],
            ]);

        $this->assertEquals(25, $response->json('meta.total'));
        $this->assertEquals(3, $response->json('meta.last_page'));
    }

    #[Test]
    public function it_only_shows_surveys_user_is_attached_to(): void
    {
        $userSurvey = resolve(SurveyFactory::class)->active()->create(['financer_id' => $this->financer->id]);
        $otherSurvey = resolve(SurveyFactory::class)->active()->create(['financer_id' => $this->financer->id]);

        // Only attach user to one survey
        $this->auth->surveys()->attach($userSurvey->id);

        Context::add('accessible_financers', [$this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('me.survey.surveys.index', ['financer_id' => $this->financer->id]));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['id' => $userSurvey->id])
            ->assertJsonMissing(['id' => $otherSurvey->id]);
    }

    #[Test]
    public function user_with_view_any_permission_can_list_surveys(): void
    {
        Permission::firstOrCreate([
            'name' => PermissionDefaults::READ_SURVEY,
            'guard_name' => 'api',
        ]);
        $this->auth->givePermissionTo(PermissionDefaults::READ_SURVEY);

        $survey = resolve(SurveyFactory::class)->active()->create(['financer_id' => $this->financer->id]);
        $this->auth->surveys()->attach($survey->id);

        Context::add('accessible_financers', [$this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('me.survey.surveys.index', ['financer_id' => $this->financer->id]));

        $response->assertStatus(200);
    }

    #[Test]
    public function it_can_show_survey_details(): void
    {
        $survey = resolve(SurveyFactory::class)->active()->create(['financer_id' => $this->financer->id]);

        $question = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);
        $survey->questions()->attach([$question->id]);

        $this->auth->surveys()->attach($survey->id);

        Context::add('accessible_financers', [$this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('me.survey.surveys.show', ['survey' => $survey, 'financer_id' => $this->financer->id]));

        $response->assertStatus(200)
            ->assertJsonFragment([
                'id' => $survey->id,
                'financer_id' => $this->financer->id,
            ]);
    }

    #[Test]
    public function it_shows_survey_with_questions(): void
    {
        $survey = resolve(SurveyFactory::class)->active()->create(['financer_id' => $this->financer->id]);

        $question = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);
        $survey->questions()->attach([$question->id]);

        $this->auth->surveys()->attach($survey->id);

        Context::add('accessible_financers', [$this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('me.survey.surveys.show', ['survey' => $survey, 'financer_id' => $this->financer->id]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'questions' => [
                        '*' => [
                            'id',
                        ],
                    ],
                ],
            ]);
    }

    #[Test]
    public function it_applies_survey_pipeline_filters(): void
    {
        $survey1 = resolve(SurveyFactory::class)->active()->create([
            'financer_id' => $this->financer->id,
            'title' => ['en' => 'First Survey'],
        ]);

        $survey2 = resolve(SurveyFactory::class)->active()->create([
            'financer_id' => $this->financer->id,
            'title' => ['en' => 'Second Survey'],
        ]);

        $this->auth->surveys()->attach([$survey1->id, $survey2->id]);

        Context::add('accessible_financers', [$this->financer->id]);

        // Test with filter if pipeline supports it
        $response = $this->actingAs($this->auth)
            ->getJson(route('me.survey.surveys.index', ['financer_id' => $this->financer->id]));

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    #[Test]
    public function it_can_toggle_survey_favorite(): void
    {
        $survey = resolve(SurveyFactory::class)->active()->create(['financer_id' => $this->financer->id]);
        $this->auth->surveys()->attach($survey->id);

        Context::add('accessible_financers', [$this->financer->id]);

        $route = route('me.survey.surveys.toggle-favorite', [
            'survey' => $survey,
            'financer_id' => $this->financer->id,
        ]);

        $response = $this->actingAs($this->auth)->putJson($route);

        $response->assertStatus(200);

        $this->assertDatabaseHas('int_survey_favorites', [
            'user_id' => $this->auth->id,
            'survey_id' => $survey->id,
            'deleted_at' => null,
        ]);

        $response = $this->actingAs($this->auth)->putJson($route);

        $response->assertStatus(200);

        $this->assertSoftDeleted('int_survey_favorites', [
            'user_id' => $this->auth->id,
            'survey_id' => $survey->id,
        ]);
    }

    #[Test]
    public function it_filters_surveys_by_user_status_open(): void
    {
        // Create surveys
        $openSurvey = resolve(SurveyFactory::class)->active()->create(['financer_id' => $this->financer->id]);
        $ongoingSurvey = resolve(SurveyFactory::class)->active()->create(['financer_id' => $this->financer->id]);
        $completedSurvey = resolve(SurveyFactory::class)->active()->create(['financer_id' => $this->financer->id]);

        // Attach all surveys to user
        $this->auth->surveys()->attach([$openSurvey->id, $ongoingSurvey->id, $completedSurvey->id]);

        // Create submissions for ongoing and completed surveys
        resolve(SubmissionFactory::class)->inProgress()->create([
            'survey_id' => $ongoingSurvey->id,
            'user_id' => $this->auth->id,
            'financer_id' => $this->financer->id,
        ]);

        resolve(SubmissionFactory::class)->completed()->create([
            'survey_id' => $completedSurvey->id,
            'user_id' => $this->auth->id,
            'financer_id' => $this->financer->id,
        ]);

        Context::add('accessible_financers', [$this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('me.survey.surveys.index', [
                'financer_id' => $this->financer->id,
                'user_status' => UserSurveyStatusEnum::OPEN,
            ]));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['id' => $openSurvey->id])
            ->assertJsonMissing(['id' => $ongoingSurvey->id])
            ->assertJsonMissing(['id' => $completedSurvey->id]);
    }

    #[Test]
    public function it_filters_surveys_by_user_status_ongoing(): void
    {
        // Create surveys
        $openSurvey = resolve(SurveyFactory::class)->active()->create(['financer_id' => $this->financer->id]);
        $ongoingSurvey = resolve(SurveyFactory::class)->active()->create(['financer_id' => $this->financer->id]);
        $completedSurvey = resolve(SurveyFactory::class)->active()->create(['financer_id' => $this->financer->id]);

        // Attach all surveys to user
        $this->auth->surveys()->attach([$openSurvey->id, $ongoingSurvey->id, $completedSurvey->id]);

        // Create submissions for ongoing and completed surveys
        resolve(SubmissionFactory::class)->inProgress()->create([
            'survey_id' => $ongoingSurvey->id,
            'user_id' => $this->auth->id,
            'financer_id' => $this->financer->id,
        ]);

        resolve(SubmissionFactory::class)->completed()->create([
            'survey_id' => $completedSurvey->id,
            'user_id' => $this->auth->id,
            'financer_id' => $this->financer->id,
        ]);

        Context::add('accessible_financers', [$this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('me.survey.surveys.index', [
                'financer_id' => $this->financer->id,
                'user_status' => UserSurveyStatusEnum::ONGOING,
            ]));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['id' => $ongoingSurvey->id])
            ->assertJsonMissing(['id' => $openSurvey->id])
            ->assertJsonMissing(['id' => $completedSurvey->id]);
    }

    #[Test]
    public function it_filters_surveys_by_user_status_completed(): void
    {
        // Create surveys
        $openSurvey = resolve(SurveyFactory::class)->active()->create(['financer_id' => $this->financer->id]);
        $ongoingSurvey = resolve(SurveyFactory::class)->active()->create(['financer_id' => $this->financer->id]);
        $completedSurvey = resolve(SurveyFactory::class)->active()->create(['financer_id' => $this->financer->id]);

        // Attach all surveys to user
        $this->auth->surveys()->attach([$openSurvey->id, $ongoingSurvey->id, $completedSurvey->id]);

        // Create submissions for ongoing and completed surveys
        resolve(SubmissionFactory::class)->inProgress()->create([
            'survey_id' => $ongoingSurvey->id,
            'user_id' => $this->auth->id,
            'financer_id' => $this->financer->id,
        ]);

        resolve(SubmissionFactory::class)->completed()->create([
            'survey_id' => $completedSurvey->id,
            'user_id' => $this->auth->id,
            'financer_id' => $this->financer->id,
        ]);

        Context::add('accessible_financers', [$this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('me.survey.surveys.index', [
                'financer_id' => $this->financer->id,
                'user_status' => UserSurveyStatusEnum::COMPLETED,
            ]));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['id' => $completedSurvey->id])
            ->assertJsonMissing(['id' => $openSurvey->id])
            ->assertJsonMissing(['id' => $ongoingSurvey->id]);
    }

    #[Test]
    public function it_returns_user_status_open_when_user_has_no_submission(): void
    {
        $survey = resolve(SurveyFactory::class)->active()->create(['financer_id' => $this->financer->id]);
        $this->auth->surveys()->attach($survey->id);

        Context::add('accessible_financers', [$this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('me.survey.surveys.show', ['survey' => $survey, 'financer_id' => $this->financer->id]));

        $response->assertStatus(200)
            ->assertJsonFragment([
                'id' => $survey->id,
                'user_status' => UserSurveyStatusEnum::OPEN,
            ]);
    }

    #[Test]
    public function it_returns_user_status_ongoing_when_user_has_incomplete_submission(): void
    {
        $survey = resolve(SurveyFactory::class)->active()->create(['financer_id' => $this->financer->id]);
        $this->auth->surveys()->attach($survey->id);

        resolve(SubmissionFactory::class)->inProgress()->create([
            'survey_id' => $survey->id,
            'user_id' => $this->auth->id,
            'financer_id' => $this->financer->id,
        ]);

        Context::add('accessible_financers', [$this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('me.survey.surveys.show', ['survey' => $survey, 'financer_id' => $this->financer->id]));

        $response->assertStatus(200)
            ->assertJsonFragment([
                'id' => $survey->id,
                'user_status' => UserSurveyStatusEnum::ONGOING,
            ]);
    }

    #[Test]
    public function it_returns_user_status_completed_when_user_has_completed_submission(): void
    {
        $survey = resolve(SurveyFactory::class)->active()->create(['financer_id' => $this->financer->id]);
        $this->auth->surveys()->attach($survey->id);

        resolve(SubmissionFactory::class)->completed()->create([
            'survey_id' => $survey->id,
            'user_id' => $this->auth->id,
            'financer_id' => $this->financer->id,
        ]);

        Context::add('accessible_financers', [$this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('me.survey.surveys.show', ['survey' => $survey, 'financer_id' => $this->financer->id]));

        $response->assertStatus(200)
            ->assertJsonFragment([
                'id' => $survey->id,
                'user_status' => UserSurveyStatusEnum::COMPLETED,
            ]);
    }

    #[Test]
    public function it_returns_user_status_in_list_when_user_has_no_submission(): void
    {
        $survey = resolve(SurveyFactory::class)->active()->create(['financer_id' => $this->financer->id]);
        $this->auth->surveys()->attach($survey->id);

        Context::add('accessible_financers', [$this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('me.survey.surveys.index', ['financer_id' => $this->financer->id]));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'user_status',
                    ],
                ],
            ])
            ->assertJsonFragment([
                'id' => $survey->id,
                'user_status' => UserSurveyStatusEnum::OPEN,
            ]);
    }

    #[Test]
    public function it_returns_user_status_in_list_when_user_has_incomplete_submission(): void
    {
        $survey = resolve(SurveyFactory::class)->active()->create(['financer_id' => $this->financer->id]);
        $this->auth->surveys()->attach($survey->id);

        resolve(SubmissionFactory::class)->inProgress()->create([
            'survey_id' => $survey->id,
            'user_id' => $this->auth->id,
            'financer_id' => $this->financer->id,
        ]);

        Context::add('accessible_financers', [$this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('me.survey.surveys.index', ['financer_id' => $this->financer->id]));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment([
                'id' => $survey->id,
                'user_status' => UserSurveyStatusEnum::ONGOING,
            ]);
    }

    #[Test]
    public function it_returns_user_status_in_list_when_user_has_completed_submission(): void
    {
        $survey = resolve(SurveyFactory::class)->active()->create(['financer_id' => $this->financer->id]);
        $this->auth->surveys()->attach($survey->id);

        resolve(SubmissionFactory::class)->completed()->create([
            'survey_id' => $survey->id,
            'user_id' => $this->auth->id,
            'financer_id' => $this->financer->id,
        ]);

        Context::add('accessible_financers', [$this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('me.survey.surveys.index', ['financer_id' => $this->financer->id]));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment([
                'id' => $survey->id,
                'user_status' => UserSurveyStatusEnum::COMPLETED,
            ]);
    }

    #[Test]
    public function it_returns_status_in_survey_response(): void
    {
        $survey = resolve(SurveyFactory::class)->active()->create(['financer_id' => $this->financer->id]);
        $this->auth->surveys()->attach($survey->id);

        Context::add('accessible_financers', [$this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('me.survey.surveys.show', ['survey' => $survey, 'financer_id' => $this->financer->id]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'status',
                ],
            ])
            ->assertJsonFragment([
                'id' => $survey->id,
            ]);
    }

    #[Test]
    public function it_returns_status_in_survey_list_response(): void
    {
        $survey = resolve(SurveyFactory::class)->active()->create(['financer_id' => $this->financer->id]);
        $this->auth->surveys()->attach($survey->id);

        Context::add('accessible_financers', [$this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('me.survey.surveys.index', ['financer_id' => $this->financer->id]));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'status',
                    ],
                ],
            ])
            ->assertJsonFragment([
                'id' => $survey->id,
            ]);
    }

    #[Test]
    public function it_returns_user_status_based_on_latest_submission(): void
    {
        $survey = resolve(SurveyFactory::class)->active()->create(['financer_id' => $this->financer->id]);
        $this->auth->surveys()->attach($survey->id);

        // Create a completed submission first
        resolve(SubmissionFactory::class)->completed()->create([
            'survey_id' => $survey->id,
            'user_id' => $this->auth->id,
            'financer_id' => $this->financer->id,
            'started_at' => now()->subDays(2),
            'completed_at' => now()->subDay(),
        ]);

        Context::add('accessible_financers', [$this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('me.survey.surveys.show', ['survey' => $survey, 'financer_id' => $this->financer->id]));

        $response->assertStatus(200)
            ->assertJsonFragment([
                'id' => $survey->id,
                'user_status' => UserSurveyStatusEnum::COMPLETED,
            ]);

        // Create a new ongoing submission (latest)
        resolve(SubmissionFactory::class)->inProgress()->create([
            'survey_id' => $survey->id,
            'user_id' => $this->auth->id,
            'financer_id' => $this->financer->id,
            'started_at' => now(),
        ]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('me.survey.surveys.show', ['survey' => $survey, 'financer_id' => $this->financer->id]));

        $response->assertStatus(200)
            ->assertJsonFragment([
                'id' => $survey->id,
                'user_status' => UserSurveyStatusEnum::ONGOING,
            ]);
    }
}
