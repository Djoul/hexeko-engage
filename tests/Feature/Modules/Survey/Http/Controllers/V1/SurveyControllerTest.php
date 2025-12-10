<?php

namespace Tests\Feature\Modules\Survey\Http\Controllers\V1;

use App\Helpers\LanguageHelper;
use App\Integrations\Survey\Database\factories\SurveyFactory;
use App\Integrations\Survey\Enums\SurveyStatusEnum;
use App\Integrations\Survey\Models\Survey;
use Database\Factories\FinancerFactory;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('survey')]
class SurveyControllerTest extends SurveyTestCase
{
    #[Test]
    public function it_can_list_surveys(): void
    {
        resolve(SurveyFactory::class)->count(3)->create(['financer_id' => $this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('survey.surveys.index', ['financer_id' => $this->financer->id]));

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
                        'status',
                        'financer_id',
                        'starts_at',
                        'ends_at',
                        'settings',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);
    }

    #[Test]
    public function it_filters_surveys_by_status(): void
    {
        resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id, 'status' => SurveyStatusEnum::PUBLISHED]);
        resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id, 'status' => SurveyStatusEnum::DRAFT]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('survey.surveys.index', ['status' => SurveyStatusEnum::PUBLISHED, 'financer_id' => $this->financer->id]));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
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
                        'status',
                        'financer_id',
                        'starts_at',
                        'ends_at',
                        'settings',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);
    }

    #[Test]
    public function it_filters_surveys_by_financer_id(): void
    {
        $otherFinancer = resolve(FinancerFactory::class)->create();

        resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);
        resolve(SurveyFactory::class)->create(['financer_id' => $otherFinancer->id]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('survey.surveys.index', ['financer_id' => $this->financer->id]));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
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
                        'status',
                        'financer_id',
                        'starts_at',
                        'ends_at',
                        'settings',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);
    }

    #[Test]
    public function it_filters_surveys_by_created_at_date(): void
    {
        resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id, 'created_at' => '2023-01-01']);
        resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id, 'created_at' => '2024-01-02']);
        resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id, 'created_at' => '2024-01-03']);
        resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id, 'created_at' => '2024-01-04']);

        $response = $this->actingAs($this->auth)->getJson(route('survey.surveys.index', [
            'financer_id' => $this->financer->id,
            'created_at' => '2024-01-02',
        ]));

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function it_returns_an_api_exception_if_status_filters_do_not_match(): void
    {
        $response = $this->actingAs($this->auth)->getJson(route('survey.surveys.index', [
            'status' => 'nonexistent',
            'financer_id' => $this->financer->id,
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    #[Test]
    public function it_can_search_surveys_by_title(): void
    {
        $languages = LanguageHelper::getLanguages($this->financer->id);

        // Create survey with unique term in title only
        $titleWithUniqueTerm = [];
        $descriptionWithoutUniqueTerm = [];
        foreach ($languages as $language) {
            $titleWithUniqueTerm[$language] = 'Employee Engagement Survey 2024';
            $descriptionWithoutUniqueTerm[$language] = 'This is a standard feedback form';
        }

        resolve(SurveyFactory::class)->create([
            'financer_id' => $this->financer->id,
            'title' => $titleWithUniqueTerm,
            'description' => $descriptionWithoutUniqueTerm,
        ]);

        // Create survey without the unique term
        $otherTitle = [];
        $otherDescription = [];
        foreach ($languages as $language) {
            $otherTitle[$language] = 'Annual Feedback Form';
            $otherDescription[$language] = 'Please complete this form';
        }

        resolve(SurveyFactory::class)->create([
            'financer_id' => $this->financer->id,
            'title' => $otherTitle,
            'description' => $otherDescription,
        ]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('survey.surveys.index', [
                'financer_id' => $this->financer->id,
                'search' => 'Engagement',
            ]));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');

        // Verify the title contains the expected text in title_raw
        $titleRaw = $response->json('data.0.title_raw');
        $this->assertContains('Employee Engagement Survey 2024', $titleRaw);
    }

    #[Test]
    public function it_can_search_surveys_by_description(): void
    {
        $languages = LanguageHelper::getLanguages($this->financer->id);

        // Create survey with unique term in description only
        $titleWithoutUniqueTerm = [];
        $descriptionWithUniqueTerm = [];
        foreach ($languages as $language) {
            $titleWithoutUniqueTerm[$language] = 'Customer Feedback Survey';
            $descriptionWithUniqueTerm[$language] = 'This survey focuses on workplace satisfaction metrics';
        }

        resolve(SurveyFactory::class)->create([
            'financer_id' => $this->financer->id,
            'title' => $titleWithoutUniqueTerm,
            'description' => $descriptionWithUniqueTerm,
        ]);

        // Create survey without the unique term
        $otherTitle = [];
        $otherDescription = [];
        foreach ($languages as $language) {
            $otherTitle[$language] = 'Employee Survey';
            $otherDescription[$language] = 'Annual review questionnaire';
        }

        resolve(SurveyFactory::class)->create([
            'financer_id' => $this->financer->id,
            'title' => $otherTitle,
            'description' => $otherDescription,
        ]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('survey.surveys.index', [
                'financer_id' => $this->financer->id,
                'search' => 'workplace',
            ]));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');

        // Verify the description contains the expected text in description_raw
        $descriptionRaw = $response->json('data.0.description_raw');
        $this->assertContains('This survey focuses on workplace satisfaction metrics', $descriptionRaw);
    }

    #[Test]
    public function it_store_validates_input(): void
    {
        $this->actingAs($this->auth)
            ->postJson(route('survey.surveys.store', ['financer_id' => $this->financer->id]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'description', 'status', 'starts_at', 'ends_at']);
    }

    #[Test]
    public function it_persists_and_redirects_when_storing(): void
    {
        $languages = LanguageHelper::getLanguages($this->financer->id);
        $title = [];
        $description = [];
        $welcomeMessage = [];
        $thankYouMessage = [];
        foreach ($languages as $language) {
            $title[$language] = 'New Survey';
            $description[$language] = 'New Survey Description';
            $welcomeMessage[$language] = 'Welcome to our survey';
            $thankYouMessage[$language] = 'Thank you for participating';
        }

        $payload = [
            'title' => $title,
            'description' => $description,
            'welcome_message' => $welcomeMessage,
            'thank_you_message' => $thankYouMessage,
            'status' => SurveyStatusEnum::DRAFT,
            'financer_id' => $this->financer->id,
            'starts_at' => now()->addDays(7)->format('Y-m-d H:i:s'),
            'ends_at' => now()->addDays(30)->format('Y-m-d H:i:s'),
            'settings' => ['theme' => 'light', 'notifications' => true],
        ];

        $response = $this->actingAs($this->auth)
            ->postJson(route('survey.surveys.store', ['financer_id' => $this->financer->id]), $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('int_survey_surveys', [
            'financer_id' => $this->financer->id,
            'status' => SurveyStatusEnum::DRAFT,
        ]);

        $createdSurveyId = $response->json('data.id');
        $createdSurvey = Survey::query()->where('id', $createdSurveyId)->first();

        foreach ($languages as $language) {
            $this->assertEquals('New Survey', $createdSurvey->getTranslation('title', $language));
            $this->assertEquals('New Survey Description', $createdSurvey->getTranslation('description', $language));
            $this->assertEquals('Welcome to our survey', $createdSurvey->getTranslation('welcome_message', $language));
            $this->assertEquals('Thank you for participating', $createdSurvey->getTranslation('thank_you_message', $language));
        }
    }

    #[Test]
    public function it_displays_a_survey(): void
    {
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);

        $this->actingAs($this->auth)->getJson(route('survey.surveys.show', ['survey' => $survey, 'financer_id' => $this->financer->id]))
            ->assertOk()
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
                    'status',
                    'financer_id',
                    'starts_at',
                    'ends_at',
                    'settings',
                    'created_at',
                    'updated_at',
                ],
            ]);
    }

    #[Test]
    public function it_validates_input_when_updating(): void
    {
        $survey = resolve(SurveyFactory::class)->create([
            'title' => ['en-GB' => 'Old Survey'],
            'financer_id' => $this->financer->id,
        ]);

        $languages = LanguageHelper::getLanguages($this->financer->id);
        $description = [];
        $welcomeMessage = [];
        $thankYouMessage = [];
        foreach ($languages as $language) {
            $description[$language] = 'Valid Description';
            $welcomeMessage[$language] = 'Valid Welcome Message';
            $thankYouMessage[$language] = 'Valid Thank You Message';
        }

        $this->actingAs($this->auth)
            ->putJson(route('survey.surveys.update', ['survey' => $survey, 'financer_id' => $this->financer->id]), [
                'title' => 'aa',
                'description' => $description,
                'welcome_message' => $welcomeMessage,
                'thank_you_message' => $thankYouMessage,
                'status' => $survey->status,
                'financer_id' => $this->financer->id,
                'starts_at' => $survey->starts_at->format('Y-m-d H:i:s'),
                'ends_at' => $survey->ends_at->format('Y-m-d H:i:s'),
                'settings' => $survey->settings,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title']);

        $this->assertDatabaseHas('int_survey_surveys', ['id' => $survey->id]);

        $survey->refresh();
        $this->assertEquals('Old Survey', $survey->getTranslation('title', 'en-GB'));
    }

    #[Test]
    public function it_persists_changes_and_redirects_when_updating(): void
    {
        Context::add('accessible_financers', [$this->financer->id]);

        $languages = LanguageHelper::getLanguages($this->financer->id);
        $title = [];
        $description = [];
        $welcomeMessage = [];
        $thankYouMessage = [];
        foreach ($languages as $language) {
            $title[$language] = 'Old Survey';
            $description[$language] = 'Old Description';
            $welcomeMessage[$language] = 'Old Welcome Message';
            $thankYouMessage[$language] = 'Old Thank You Message';
        }

        $survey = resolve(SurveyFactory::class)->create([
            'title' => $title,
            'description' => $description,
            'welcome_message' => $welcomeMessage,
            'thank_you_message' => $thankYouMessage,
            'status' => SurveyStatusEnum::DRAFT,
            'financer_id' => $this->financer->id,
        ]);

        $title = [];
        $description = [];
        $welcomeMessage = [];
        $thankYouMessage = [];
        foreach ($languages as $language) {
            $title[$language] = 'New Survey';
            $description[$language] = 'New Description';
            $welcomeMessage[$language] = 'New Welcome Message';
            $thankYouMessage[$language] = 'New Thank You Message';
        }

        $payload = [
            'title' => $title,
            'description' => $description,
            'welcome_message' => $welcomeMessage,
            'thank_you_message' => $thankYouMessage,
            'status' => SurveyStatusEnum::PUBLISHED,
            'financer_id' => $this->financer->id,
            'starts_at' => now()->addDays(1)->format('Y-m-d H:i:s'),
            'ends_at' => now()->addDays(30)->format('Y-m-d H:i:s'),
            'settings' => ['theme' => 'dark', 'notifications' => false],
        ];

        $response = $this->actingAs($this->auth)
            ->putJson(route('survey.surveys.update', ['survey' => $survey, 'financer_id' => $this->financer->id]), $payload);

        $response->assertOk();

        $this->assertDatabaseHas('int_survey_surveys', [
            'id' => $survey->id,
            'status' => SurveyStatusEnum::PUBLISHED,
        ]);

        $updatedSurvey = Survey::query()->where('id', $survey->id)->first();

        foreach ($languages as $language) {
            $this->assertEquals('New Survey', $updatedSurvey->getTranslation('title', $language));
            $this->assertEquals('New Description', $updatedSurvey->getTranslation('description', $language));
            $this->assertEquals('New Welcome Message', $updatedSurvey->getTranslation('welcome_message', $language));
            $this->assertEquals('New Thank You Message', $updatedSurvey->getTranslation('thank_you_message', $language));
        }
    }

    #[Test]
    public function it_deletes_and_redirects(): void
    {
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->deleteJson(route('survey.surveys.destroy', ['survey' => $survey, 'financer_id' => $this->financer->id]));

        $response->assertStatus(204);
        $this->assertSoftDeleted('int_survey_surveys', ['id' => $survey->id]);
    }

    #[Test]
    public function it_can_archive_a_survey(): void
    {
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->postJson(route('survey.surveys.archive', ['survey' => $survey, 'financer_id' => $this->financer->id]));

        $response->assertStatus(200);
        $this->assertNotNull($survey->refresh()->archived_at);
    }

    #[Test]
    public function it_can_unarchive_a_survey(): void
    {
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id, 'archived_at' => now()]);

        $response = $this->actingAs($this->auth)
            ->postJson(route('survey.surveys.unarchive', ['survey' => $survey, 'financer_id' => $this->financer->id]));

        $response->assertStatus(200);
        $this->assertNull($survey->refresh()->archived_at);
    }

    #[Test]
    public function it_can_create_a_draft_survey(): void
    {
        $response = $this->actingAs($this->auth)
            ->postJson(route('survey.surveys.draft', ['financer_id' => $this->financer->id]));

        $response->assertStatus(201);

        $this->assertDatabaseHas('int_survey_surveys', [
            'financer_id' => $this->financer->id,
            'status' => SurveyStatusEnum::DRAFT,
        ]);

        $response->assertJsonStructure([
            'data' => [
                'id',
                'title',
                'description',
                'status',
                'financer_id',
                'starts_at',
                'ends_at',
                'questions_count',
            ],
        ]);
    }

    #[Test]
    public function it_validates_draft_survey_input(): void
    {
        $this->actingAs($this->auth)
            ->postJson(route('survey.surveys.draft', ['financer_id' => $this->financer->id]))
            ->assertStatus(201); // nothing to validate
    }

    #[Test]
    public function it_returns_calculated_status_draft(): void
    {
        $survey = resolve(SurveyFactory::class)->create([
            'financer_id' => $this->financer->id,
            'status' => SurveyStatusEnum::DRAFT,
        ]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('survey.surveys.show', ['survey' => $survey, 'financer_id' => $this->financer->id]));

        $response->assertOk()
            ->assertJsonPath('data.status', SurveyStatusEnum::DRAFT);
    }

    #[Test]
    public function it_returns_calculated_status_scheduled(): void
    {
        $survey = resolve(SurveyFactory::class)->create([
            'financer_id' => $this->financer->id,
            'status' => SurveyStatusEnum::PUBLISHED,
            'starts_at' => now()->addDays(7),
            'ends_at' => now()->addDays(30),
        ]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('survey.surveys.show', ['survey' => $survey, 'financer_id' => $this->financer->id]));

        $response->assertOk()
            ->assertJsonPath('data.status', SurveyStatusEnum::SCHEDULED);
    }

    #[Test]
    public function it_returns_calculated_status_active(): void
    {
        // ACTIVE status requires updated_at to be more than 3 days ago (otherwise it would be NEW)
        $survey = resolve(SurveyFactory::class)->create([
            'financer_id' => $this->financer->id,
            'status' => SurveyStatusEnum::PUBLISHED,
            'starts_at' => now()->subDays(1),
            'ends_at' => now()->addDays(7),
            'updated_at' => now()->subDays(4),
        ]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('survey.surveys.show', ['survey' => $survey, 'financer_id' => $this->financer->id]));

        $response->assertOk()
            ->assertJsonPath('data.status', SurveyStatusEnum::ACTIVE);
    }

    #[Test]
    public function it_returns_calculated_status_closed(): void
    {
        $survey = resolve(SurveyFactory::class)->create([
            'financer_id' => $this->financer->id,
            'status' => SurveyStatusEnum::PUBLISHED,
            'starts_at' => now()->subDays(30),
            'ends_at' => now()->subDays(1),
        ]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('survey.surveys.show', ['survey' => $survey, 'financer_id' => $this->financer->id]));

        $response->assertOk()
            ->assertJsonPath('data.status', SurveyStatusEnum::CLOSED);
    }

    #[Test]
    public function it_returns_calculated_status_new(): void
    {
        // NEW status is calculated based on updated_at being within 3 days and being active
        $survey = resolve(SurveyFactory::class)->create([
            'financer_id' => $this->financer->id,
            'status' => SurveyStatusEnum::PUBLISHED,
            'starts_at' => now()->subDays(1),
            'ends_at' => now()->addDays(7),
            'updated_at' => now()->subDays(2),
        ]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('survey.surveys.show', ['survey' => $survey, 'financer_id' => $this->financer->id]));

        $response->assertOk()
            ->assertJsonPath('data.status', SurveyStatusEnum::NEW);
    }

    #[Test]
    public function it_filters_surveys_by_all_static_statuses(): void
    {
        resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id, 'status' => SurveyStatusEnum::DRAFT]);
        resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id, 'status' => SurveyStatusEnum::PUBLISHED]);
        resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id, 'status' => SurveyStatusEnum::PUBLISHED]);

        // Test DRAFT filter
        $response = $this->actingAs($this->auth)
            ->getJson(route('survey.surveys.index', ['status' => SurveyStatusEnum::DRAFT, 'financer_id' => $this->financer->id]));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', SurveyStatusEnum::DRAFT);

        // Test PUBLISHED filter
        $response = $this->actingAs($this->auth)
            ->getJson(route('survey.surveys.index', ['status' => SurveyStatusEnum::PUBLISHED, 'financer_id' => $this->financer->id]));

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    #[Test]
    public function it_returns_correct_status_in_list_response(): void
    {
        resolve(SurveyFactory::class)->create([
            'financer_id' => $this->financer->id,
            'status' => SurveyStatusEnum::DRAFT,
        ]);

        resolve(SurveyFactory::class)->create([
            'financer_id' => $this->financer->id,
            'status' => SurveyStatusEnum::PUBLISHED,
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->addDays(70),
            'updated_at' => now()->subDays(4),
        ]);

        resolve(SurveyFactory::class)->create([
            'financer_id' => $this->financer->id,
            'status' => SurveyStatusEnum::PUBLISHED,
            'starts_at' => now()->subDays(30),
            'ends_at' => now()->subDays(1),
        ]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('survey.surveys.index', ['financer_id' => $this->financer->id]));

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');

        $data = $response->json('data');
        $statuses = array_column($data, 'status');

        $this->assertContains(SurveyStatusEnum::DRAFT, $statuses);
        $this->assertContains(SurveyStatusEnum::ACTIVE, $statuses);
        $this->assertContains(SurveyStatusEnum::CLOSED, $statuses);
    }

    #[Test]
    public function it_returns_correct_status_after_status_transition(): void
    {
        // Create draft survey
        $survey = resolve(SurveyFactory::class)->create([
            'financer_id' => $this->financer->id,
            'status' => SurveyStatusEnum::DRAFT,
        ]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('survey.surveys.show', ['survey' => $survey, 'financer_id' => $this->financer->id]));

        $response->assertOk()
            ->assertJsonPath('data.status', SurveyStatusEnum::DRAFT);

        // Update to PUBLISHED with future dates (should be SCHEDULED)
        $languages = LanguageHelper::getLanguages($this->financer->id);
        $title = [];
        foreach ($languages as $language) {
            $title[$language] = 'Updated Survey';
        }

        $payload = [
            'title' => $title,
            'description' => $title,
            'welcome_message' => $title,
            'thank_you_message' => $title,
            'status' => SurveyStatusEnum::PUBLISHED,
            'financer_id' => $this->financer->id,
            'starts_at' => now()->addDays(7)->format('Y-m-d H:i:s'),
            'ends_at' => now()->addDays(30)->format('Y-m-d H:i:s'),
            'settings' => [],
        ];

        $this->actingAs($this->auth)
            ->putJson(route('survey.surveys.update', ['survey' => $survey, 'financer_id' => $this->financer->id]), $payload)
            ->assertOk();

        // Check status is now SCHEDULED
        $response = $this->actingAs($this->auth)
            ->getJson(route('survey.surveys.show', ['survey' => $survey, 'financer_id' => $this->financer->id]));

        $response->assertOk()
            ->assertJsonPath('data.status', SurveyStatusEnum::SCHEDULED);
    }

    #[Test]
    public function it_returns_draft_status_when_published_survey_has_no_dates(): void
    {
        // PUBLISHED without dates should default to DRAFT
        $survey = resolve(SurveyFactory::class)->create([
            'financer_id' => $this->financer->id,
            'status' => SurveyStatusEnum::PUBLISHED,
            'starts_at' => null,
            'ends_at' => null,
        ]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('survey.surveys.show', ['survey' => $survey, 'financer_id' => $this->financer->id]));

        $response->assertOk()
            ->assertJsonPath('data.status', SurveyStatusEnum::DRAFT);
    }

    #[Test]
    public function it_returns_closed_status_when_ends_at_is_in_past_even_if_starts_at_is_null(): void
    {
        $survey = resolve(SurveyFactory::class)->create([
            'financer_id' => $this->financer->id,
            'status' => SurveyStatusEnum::PUBLISHED,
            'starts_at' => null,
            'ends_at' => now()->subDays(1),
        ]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('survey.surveys.show', ['survey' => $survey, 'financer_id' => $this->financer->id]));

        $response->assertOk()
            ->assertJsonPath('data.status', SurveyStatusEnum::CLOSED);
    }

    #[Test]
    public function it_returns_scheduled_status_when_starts_at_is_in_future_even_if_ends_at_is_null(): void
    {
        $survey = resolve(SurveyFactory::class)->create([
            'financer_id' => $this->financer->id,
            'status' => SurveyStatusEnum::PUBLISHED,
            'starts_at' => now()->addDays(7),
            'ends_at' => null,
        ]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('survey.surveys.show', ['survey' => $survey, 'financer_id' => $this->financer->id]));

        $response->assertOk()
            ->assertJsonPath('data.status', SurveyStatusEnum::SCHEDULED);
    }

    #[Test]
    public function it_returns_all_statuses_correctly_in_list_response(): void
    {
        resolve(SurveyFactory::class)->create([
            'financer_id' => $this->financer->id,
            'status' => SurveyStatusEnum::DRAFT,
        ]);

        resolve(SurveyFactory::class)->create([
            'financer_id' => $this->financer->id,
            'status' => SurveyStatusEnum::PUBLISHED,
            'starts_at' => now()->addDays(7),
            'ends_at' => now()->addDays(30),
        ]);

        resolve(SurveyFactory::class)->create([
            'financer_id' => $this->financer->id,
            'status' => SurveyStatusEnum::PUBLISHED,
            'starts_at' => now()->subDays(1),
            'ends_at' => now()->addDays(7),
            'updated_at' => now()->subDays(4),
        ]);

        resolve(SurveyFactory::class)->create([
            'financer_id' => $this->financer->id,
            'status' => SurveyStatusEnum::PUBLISHED,
            'starts_at' => now()->subDays(30),
            'ends_at' => now()->subDays(1),
        ]);

        resolve(SurveyFactory::class)->create([
            'financer_id' => $this->financer->id,
            'status' => SurveyStatusEnum::PUBLISHED,
            'starts_at' => now()->subDays(1),
            'ends_at' => now()->addDays(7),
            'updated_at' => now()->subDays(2),
        ]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('survey.surveys.index', ['financer_id' => $this->financer->id]));

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data');

        $data = $response->json('data');
        $statuses = array_column($data, 'status');

        $this->assertContains(SurveyStatusEnum::DRAFT, $statuses);
        $this->assertContains(SurveyStatusEnum::SCHEDULED, $statuses);
        $this->assertContains(SurveyStatusEnum::ACTIVE, $statuses);
        $this->assertContains(SurveyStatusEnum::CLOSED, $statuses);
        $this->assertContains(SurveyStatusEnum::NEW, $statuses);
    }

    #[Test]
    public function it_returns_new_status_only_when_updated_within_3_days(): void
    {
        // Survey updated 2 days ago (should be NEW)
        $newSurvey = resolve(SurveyFactory::class)->create([
            'financer_id' => $this->financer->id,
            'status' => SurveyStatusEnum::PUBLISHED,
            'starts_at' => now()->subDays(1),
            'ends_at' => now()->addDays(7),
            'updated_at' => now()->subDays(2),
        ]);

        // Survey updated 4 days ago (should be ACTIVE, not NEW)
        $activeSurvey = resolve(SurveyFactory::class)->create([
            'financer_id' => $this->financer->id,
            'status' => SurveyStatusEnum::PUBLISHED,
            'starts_at' => now()->subDays(1),
            'ends_at' => now()->addDays(7),
            'updated_at' => now()->subDays(4),
        ]);

        $response1 = $this->actingAs($this->auth)
            ->getJson(route('survey.surveys.show', ['survey' => $newSurvey, 'financer_id' => $this->financer->id]));

        $response1->assertOk()
            ->assertJsonPath('data.status', SurveyStatusEnum::NEW);

        $response2 = $this->actingAs($this->auth)
            ->getJson(route('survey.surveys.show', ['survey' => $activeSurvey, 'financer_id' => $this->financer->id]));

        $response2->assertOk()
            ->assertJsonPath('data.status', SurveyStatusEnum::ACTIVE);
    }
}
