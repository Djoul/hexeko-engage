<?php

namespace Tests\Feature\Modules\Survey\Http\Controllers\V1;

use App\Integrations\Survey\Database\factories\SubmissionFactory;
use App\Integrations\Survey\Database\factories\SurveyFactory;
use App\Integrations\Survey\Enums\SurveyStatusEnum;
use App\Models\User;
use Database\Factories\FinancerFactory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('survey')]
class SurveyMetricControllerTest extends SurveyTestCase
{
    #[Test]
    public function it_validates_date_format_for_index(): void
    {
        $response = $this->actingAs($this->auth)
            ->getJson(route('survey.surveys.metrics.index', [
                'financer_id' => $this->financer->id,
                'start_date' => 'invalid-date',
                'end_date' => '2024-12-31',
            ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['start_date']);
    }

    #[Test]
    public function it_validates_end_date_is_after_start_date_for_index(): void
    {
        $response = $this->actingAs($this->auth)
            ->getJson(route('survey.surveys.metrics.index', [
                'financer_id' => $this->financer->id,
                'start_date' => '2024-12-31',
                'end_date' => '2024-01-01',
            ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['end_date']);
    }

    #[Test]
    public function it_returns_global_metrics_without_date_range(): void
    {
        $survey = resolve(SurveyFactory::class)->create([
            'financer_id' => $this->financer->id,
            'status' => SurveyStatusEnum::PUBLISHED,
        ]);

        $user = User::factory()->create();
        $this->attachUsersToSurvey($survey, [$user->id]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('survey.surveys.metrics.index', [
                'financer_id' => $this->financer->id,
            ]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'surveys_count',
                    'draft_surveys_count',
                    'scheduled_surveys_count',
                    'active_surveys_count',
                    'closed_surveys_count',
                    'archived_surveys_count',
                    'submissions_count',
                    'completed_submissions_count',
                    'users_count',
                    'completion_rate',
                    'response_rate',
                ],
            ]);
    }

    #[Test]
    public function it_returns_global_metrics_with_date_range(): void
    {
        $survey = resolve(SurveyFactory::class)->create([
            'financer_id' => $this->financer->id,
            'status' => SurveyStatusEnum::PUBLISHED,
            'starts_at' => '2024-01-01',
            'ends_at' => '2024-12-31',
        ]);

        $user = User::factory()->create();
        $this->attachUsersToSurvey($survey, [$user->id]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('survey.surveys.metrics.index', [
                'financer_id' => $this->financer->id,
                'start_date' => '2024-01-01',
                'end_date' => '2024-12-31',
            ]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'surveys_count',
                    'draft_surveys_count',
                    'scheduled_surveys_count',
                    'active_surveys_count',
                    'closed_surveys_count',
                    'archived_surveys_count',
                    'submissions_count',
                    'completed_submissions_count',
                    'users_count',
                    'completion_rate',
                    'response_rate',
                    'previous_period' => [
                        'response_rate',
                        'completion_rate',
                        'submissions_count',
                        'completed_submissions_count',
                        'users_count',
                    ],
                    'progression' => [
                        'response_rate',
                        'completion_rate',
                        'submissions_count',
                        'completed_submissions_count',
                    ],
                ],
            ]);
    }

    #[Test]
    public function it_calculates_metrics_correctly_for_multiple_surveys(): void
    {
        // Create 2 surveys with different statuses
        $activeSurvey = resolve(SurveyFactory::class)->create([
            'financer_id' => $this->financer->id,
            'status' => SurveyStatusEnum::PUBLISHED,
        ]);

        $draftSurvey = resolve(SurveyFactory::class)->create([
            'financer_id' => $this->financer->id,
            'status' => SurveyStatusEnum::DRAFT,
        ]);

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $this->attachUsersToSurvey($activeSurvey, [$user1->id, $user2->id]);
        $this->attachUsersToSurvey($draftSurvey, [$user1->id]);

        // Create submissions
        resolve(SubmissionFactory::class)->create([
            'survey_id' => $activeSurvey->id,
            'user_id' => $user1->id,
            'financer_id' => $this->financer->id,
            'completed_at' => now(),
        ]);

        resolve(SubmissionFactory::class)->create([
            'survey_id' => $activeSurvey->id,
            'user_id' => $user2->id,
            'financer_id' => $this->financer->id,
            'completed_at' => null,
        ]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('survey.surveys.metrics.index', [
                'financer_id' => $this->financer->id,
            ]));

        $response->assertStatus(200);

        $data = $response->json('data');

        $this->assertEquals(2, $data['surveys_count']);
        $this->assertEquals(1, $data['draft_surveys_count']);
        $this->assertEquals(1, $data['active_surveys_count']);
        $this->assertEquals(3, $data['users_count']); // 2 users in active + 1 in draft
        $this->assertEquals(2, $data['submissions_count']);
        $this->assertEquals(1, $data['completed_submissions_count']);
    }

    #[Test]
    public function it_filters_metrics_by_financer_id(): void
    {
        $otherFinancer = resolve(FinancerFactory::class)->create();

        resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);
        resolve(SurveyFactory::class)->create(['financer_id' => $otherFinancer->id]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('survey.surveys.metrics.index', [
                'financer_id' => $this->financer->id,
            ]));

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertEquals(1, $data['surveys_count']);
    }

    #[Test]
    public function it_returns_zero_metrics_when_no_surveys_exist(): void
    {
        $response = $this->actingAs($this->auth)
            ->getJson(route('survey.surveys.metrics.index', [
                'financer_id' => $this->financer->id,
            ]));

        $response->assertStatus(200);

        $data = $response->json('data');

        $this->assertEquals(0, $data['surveys_count']);
        $this->assertEquals(0, $data['submissions_count']);
        $this->assertEquals(0, $data['completed_submissions_count']);
        $this->assertEquals(0, $data['users_count']);
        $this->assertEquals(0.0, $data['completion_rate']);
        $this->assertEquals(0.0, $data['response_rate']);
    }

    #[Test]
    public function it_validates_date_format_for_show(): void
    {
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('survey.surveys.metrics.show', [
                'survey' => $survey,
                'financer_id' => $this->financer->id,
                'start_date' => 'invalid-date',
                'end_date' => '2024-12-31',
            ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['start_date']);
    }

    #[Test]
    public function it_validates_end_date_is_after_start_date_for_show(): void
    {
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('survey.surveys.metrics.show', [
                'survey' => $survey,
                'financer_id' => $this->financer->id,
                'start_date' => '2024-12-31',
                'end_date' => '2024-01-01',
            ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['end_date']);
    }

    #[Test]
    public function it_returns_survey_metrics_without_date_range(): void
    {
        $survey = resolve(SurveyFactory::class)->create([
            'financer_id' => $this->financer->id,
            'status' => SurveyStatusEnum::PUBLISHED,
        ]);

        $user = User::factory()->create();
        $this->attachUsersToSurvey($survey, [$user->id]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('survey.surveys.metrics.show', [
                'survey' => $survey,
                'financer_id' => $this->financer->id,
            ]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'submissions_count',
                    'completed_submissions_count',
                    'users_count',
                    'completion_rate',
                    'response_rate',
                ],
            ])
            ->assertJsonMissing(['surveys_count'])
            ->assertJsonMissing(['draft_surveys_count']);
    }

    #[Test]
    public function it_returns_survey_metrics_with_date_range(): void
    {
        $survey = resolve(SurveyFactory::class)->create([
            'financer_id' => $this->financer->id,
            'status' => SurveyStatusEnum::PUBLISHED,
            'starts_at' => '2024-01-01',
            'ends_at' => '2024-12-31',
        ]);

        $user = User::factory()->create();
        $this->attachUsersToSurvey($survey, [$user->id]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('survey.surveys.metrics.show', [
                'survey' => $survey,
                'financer_id' => $this->financer->id,
                'start_date' => '2024-01-01',
                'end_date' => '2024-12-31',
            ]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'submissions_count',
                    'completed_submissions_count',
                    'users_count',
                    'completion_rate',
                    'response_rate',
                    'previous_period' => [
                        'response_rate',
                        'completion_rate',
                        'submissions_count',
                        'completed_submissions_count',
                        'users_count',
                    ],
                    'progression' => [
                        'response_rate',
                        'completion_rate',
                        'submissions_count',
                        'completed_submissions_count',
                    ],
                ],
            ]);
    }

    #[Test]
    public function it_calculates_survey_metrics_correctly(): void
    {
        $survey = resolve(SurveyFactory::class)->create([
            'financer_id' => $this->financer->id,
            'status' => SurveyStatusEnum::PUBLISHED,
        ]);

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        $this->attachUsersToSurvey($survey, [$user1->id, $user2->id, $user3->id]);

        // User 1: completed
        resolve(SubmissionFactory::class)->create([
            'survey_id' => $survey->id,
            'user_id' => $user1->id,
            'financer_id' => $this->financer->id,
            'completed_at' => now(),
        ]);

        // User 2: started but not completed
        resolve(SubmissionFactory::class)->create([
            'survey_id' => $survey->id,
            'user_id' => $user2->id,
            'financer_id' => $this->financer->id,
            'completed_at' => null,
        ]);

        // User 3: no submission

        $response = $this->actingAs($this->auth)
            ->getJson(route('survey.surveys.metrics.show', [
                'survey' => $survey,
                'financer_id' => $this->financer->id,
            ]));

        $response->assertStatus(200);

        $data = $response->json('data');

        $this->assertEquals(3, $data['users_count']);
        $this->assertEquals(2, $data['submissions_count']);
        $this->assertEquals(1, $data['completed_submissions_count']);
        $this->assertEquals(33.33, $data['completion_rate']); // 1/3 * 100 = 33.33
        $this->assertEquals(66.67, $data['response_rate']); // 2/3 * 100 = 66.67
    }

    #[Test]
    public function it_returns_zero_metrics_for_survey_without_users(): void
    {
        $survey = resolve(SurveyFactory::class)->create([
            'financer_id' => $this->financer->id,
            'status' => SurveyStatusEnum::DRAFT,
        ]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('survey.surveys.metrics.show', [
                'survey' => $survey,
                'financer_id' => $this->financer->id,
            ]));

        $response->assertStatus(200);

        $data = $response->json('data');

        $this->assertEquals(0, $data['submissions_count']);
        $this->assertEquals(0, $data['completed_submissions_count']);
        $this->assertEquals(0, $data['users_count']);
        $this->assertEquals(0.0, $data['completion_rate']);
        $this->assertEquals(0.0, $data['response_rate']);
    }

    #[Test]
    public function it_requires_authorization_to_view_global_metrics(): void
    {
        $unauthorizedUser = User::factory()->create();

        $response = $this->actingAs($unauthorizedUser)
            ->getJson(route('survey.surveys.metrics.index', [
                'financer_id' => $this->financer->id,
            ]));

        $response->assertStatus(403);
    }

    #[Test]
    public function it_requires_authorization_to_view_survey_metrics(): void
    {
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);
        $unauthorizedUser = User::factory()->create();

        $response = $this->actingAs($unauthorizedUser)
            ->getJson(route('survey.surveys.metrics.show', [
                'survey' => $survey,
                'financer_id' => $this->financer->id,
            ]));

        $response->assertStatus(403);
    }

    #[Test]
    public function it_returns_metrics_only_for_surveys_within_date_range(): void
    {
        // Survey within date range
        $surveyInRange = resolve(SurveyFactory::class)->create([
            'financer_id' => $this->financer->id,
            'starts_at' => '2024-06-01',
            'ends_at' => '2024-06-30',
        ]);

        // Survey outside date range
        $surveyOutOfRange = resolve(SurveyFactory::class)->create([
            'financer_id' => $this->financer->id,
            'starts_at' => '2024-01-01',
            'ends_at' => '2024-01-31',
        ]);

        $user = User::factory()->create();
        $this->attachUsersToSurvey($surveyInRange, [$user->id]);
        $this->attachUsersToSurvey($surveyOutOfRange, [$user->id]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('survey.surveys.metrics.index', [
                'financer_id' => $this->financer->id,
                'start_date' => '2024-06-01',
                'end_date' => '2024-06-30',
            ]));

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertEquals(1, $data['surveys_count']);
        $this->assertEquals(1, $data['users_count']);
    }
}
