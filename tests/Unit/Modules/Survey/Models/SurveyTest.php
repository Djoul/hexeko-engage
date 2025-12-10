<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Survey\Models;

use App\Enums\Security\AuthorizationMode;
use App\Integrations\Survey\Enums\SurveyStatusEnum;
use App\Integrations\Survey\Models\Answer;
use App\Integrations\Survey\Models\Question;
use App\Integrations\Survey\Models\Submission;
use App\Integrations\Survey\Models\Survey;
use App\Models\Financer;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('survey')]
class SurveyTest extends TestCase
{
    use DatabaseTransactions;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->financer = ModelFactory::createFinancer();

        authorizationContext()->hydrate(
            AuthorizationMode::SELF,
            [$this->financer->id],
            [$this->financer->division_id],
            [],
            $this->financer->id  // Set current financer for global scopes
        );

    }

    #[Test]
    public function it_uses_uuid_as_primary_key(): void
    {
        $survey = new Survey;

        $this->assertTrue($survey->getIncrementing() === false);
        $this->assertEquals('string', $survey->getKeyType());
    }

    #[Test]
    public function it_can_create_a_survey(): void
    {
        // Act
        $survey = Survey::factory()->create([
            'title' => [
                'en-GB' => 'Test Survey',
                'fr-FR' => 'Campagne de Test',
                'nl-BE' => 'Test Campagne',
            ],
            'description' => [
                'en-GB' => 'Test Description',
                'fr-FR' => 'Description de Test',
                'nl-BE' => 'Test Beschrijving',
            ],
            'welcome_message' => [
                'en-GB' => 'Welcome to our survey',
                'fr-FR' => 'Bienvenue à notre campagne',
                'nl-BE' => 'Welkom bij onze campagne',
            ],
            'thank_you_message' => [
                'en-GB' => 'Thank you for participating',
                'fr-FR' => 'Merci de votre participation',
                'nl-BE' => 'Bedankt voor uw deelname',
            ],
            'status' => SurveyStatusEnum::PUBLISHED,
            'financer_id' => $this->financer->id,
            'starts_at' => now()->subDays(1),
            'ends_at' => now()->addDays(30),
            'settings' => [
                'theme' => 'light',
                'notifications' => true,
                'email_alerts' => false,
            ],
        ]);

        // Assert
        $this->assertInstanceOf(Survey::class, $survey);
        $this->assertDatabaseHas('int_survey_surveys', [
            'id' => $survey->id,
            'financer_id' => $this->financer->id,
            'status' => SurveyStatusEnum::PUBLISHED,
        ]);

        // Test translations
        $this->assertEquals('Test Survey', $survey->getTranslation('title', 'en-GB'));
        $this->assertEquals('Campagne de Test', $survey->getTranslation('title', 'fr-FR'));
        $this->assertEquals('Test Campagne', $survey->getTranslation('title', 'nl-BE'));

        $this->assertEquals('Welcome to our survey', $survey->getTranslation('welcome_message', 'en-GB'));
        $this->assertEquals('Thank you for participating', $survey->getTranslation('thank_you_message', 'en-GB'));
    }

    #[Test]
    public function it_can_handle_different_survey_statuses(): void
    {
        $statuses = [
            SurveyStatusEnum::DRAFT,
            SurveyStatusEnum::PUBLISHED,
            SurveyStatusEnum::ARCHIVED,
        ];

        foreach ($statuses as $status) {
            // Act
            $survey = Survey::factory()->create([
                'title' => ['en-GB' => "Survey with status {$status}"],
                'description' => ['en-GB' => "Description for {$status}"],
                'status' => $status,
                'financer_id' => $this->financer->id,
            ]);

            // Assert
            $this->assertEquals($status, $survey->status);
            $this->assertDatabaseHas('int_survey_surveys', [
                'id' => $survey->id,
                'status' => $status,
            ]);
        }
    }

    #[Test]
    public function it_can_store_settings_as_json(): void
    {
        // Arrange
        $settings = [
            'theme' => 'dark',
            'notifications' => true,
            'email_alerts' => true,
            'auto_close' => false,
            'max_responses' => 1000,
        ];

        // Act
        $survey = Survey::factory()->create([
            'title' => ['en-GB' => 'Survey with settings'],
            'description' => ['en-GB' => 'Description'],
            'status' => SurveyStatusEnum::DRAFT,
            'financer_id' => $this->financer->id,
            'settings' => $settings,
        ]);

        // Assert
        $this->assertEquals($settings, $survey->settings);
        $this->assertEquals('dark', $survey->settings['theme']);
        $this->assertTrue($survey->settings['notifications']);
        $this->assertTrue($survey->settings['email_alerts']);
        $this->assertFalse($survey->settings['auto_close']);
        $this->assertEquals(1000, $survey->settings['max_responses']);
    }

    #[Test]
    public function it_belongs_to_a_financer(): void
    {
        // Arrange
        $survey = Survey::factory()->create([
            'title' => ['en-GB' => 'Survey with financer'],
            'description' => ['en-GB' => 'Description'],
            'status' => SurveyStatusEnum::PUBLISHED,
            'financer_id' => $this->financer->id,
        ]);

        // Act & Assert
        $this->assertInstanceOf(Financer::class, $survey->financer);
        $this->assertEquals($this->financer->id, $survey->financer->id);
    }

    #[Test]
    public function it_can_scope_active_surveys(): void
    {
        // Arrange
        Survey::factory()->active()->create([
            'title' => ['en-GB' => 'Active Survey'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
        ]);

        Survey::factory()->draft()->create([
            'title' => ['en-GB' => 'Draft Survey'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
        ]);

        // Act
        $activeSurveys = Survey::query()->active()->get();

        // Assert
        $this->assertCount(1, $activeSurveys);
        $this->assertEquals(SurveyStatusEnum::PUBLISHED, $activeSurveys->first()->status);
    }

    #[Test]
    public function it_can_scope_draft_surveys(): void
    {
        // Arrange
        Survey::factory()->draft()->create([
            'title' => ['en-GB' => 'Draft Survey'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
        ]);

        Survey::factory()->active()->create([
            'title' => ['en-GB' => 'Active Survey'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
        ]);

        // Act
        $draftSurveys = Survey::query()->draft()->get();

        // Assert
        $this->assertCount(1, $draftSurveys);
        $this->assertEquals(SurveyStatusEnum::DRAFT, $draftSurveys->first()->status);
    }

    #[Test]
    public function it_can_scope_scheduled_surveys(): void
    {
        // Arrange
        Survey::factory()->scheduled()->create([
            'title' => ['en-GB' => 'Scheduled Survey'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
        ]);

        Survey::factory()->active()->create([
            'title' => ['en-GB' => 'Active Survey'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
        ]);

        // Act
        $scheduledSurveys = Survey::query()->scheduled()->get();

        // Assert
        $this->assertCount(1, $scheduledSurveys);
        $this->assertEquals(SurveyStatusEnum::SCHEDULED, $scheduledSurveys->first()->getStatus());
    }

    #[Test]
    public function it_can_scope_surveys_by_financer(): void
    {
        // Arrange
        $financer1 = ModelFactory::createFinancer();
        $financer2 = ModelFactory::createFinancer();

        Context::add('accessible_financers', [$financer1->id, $financer2->id]);

        Survey::factory()->create([
            'title' => ['en-GB' => 'Survey for Financer 1'],
            'description' => ['en-GB' => 'Description'],
            'status' => SurveyStatusEnum::PUBLISHED,
            'financer_id' => $financer1->id,
        ]);

        Survey::factory()->create([
            'title' => ['en-GB' => 'Survey for Financer 2'],
            'description' => ['en-GB' => 'Description'],
            'status' => SurveyStatusEnum::DRAFT,
            'financer_id' => $financer2->id,
        ]);

        // Act
        $financer1Surveys = Survey::query()->withoutGlobalScopes()->forFinancer($financer1->id)->get();

        // Assert
        $this->assertCount(1, $financer1Surveys);
        $this->assertTrue($financer1Surveys->contains('financer_id', $financer1->id));
    }

    #[Test]
    public function it_can_scope_surveys_within_period(): void
    {
        // Arrange
        $startDate = now()->subDays(10);
        $endDate = now()->addDays(10);

        Survey::factory()->create([
            'title' => ['en-GB' => 'Survey within period'],
            'description' => ['en-GB' => 'Description'],
            'status' => SurveyStatusEnum::PUBLISHED,
            'financer_id' => $this->financer->id,
            'starts_at' => now()->subDays(5),
        ]);

        Survey::factory()->create([
            'title' => ['en-GB' => 'Survey outside period'],
            'description' => ['en-GB' => 'Description'],
            'status' => SurveyStatusEnum::PUBLISHED,
            'financer_id' => $this->financer->id,
            'starts_at' => now()->subDays(20),
        ]);

        // Act
        $periodSurveys = Survey::query()->withinPeriod($startDate, $endDate)->get();

        // Assert
        $this->assertCount(1, $periodSurveys);
        $this->assertEquals('Survey within period', $periodSurveys->first()->getTranslation('title', 'en-GB'));
    }

    #[Test]
    public function it_uses_soft_deletes(): void
    {
        // Arrange
        $survey = Survey::factory()->create([
            'title' => ['en-GB' => 'Survey to delete'],
            'description' => ['en-GB' => 'Description'],
            'status' => SurveyStatusEnum::DRAFT,
            'financer_id' => $this->financer->id,
        ]);

        // Act
        $survey->delete();

        // Assert
        $this->assertSoftDeleted('int_survey_surveys', ['id' => $survey->id]);
        $this->assertNull(Survey::find($survey->id));
        $this->assertNotNull(Survey::withTrashed()->find($survey->id));
    }

    #[Test]
    public function it_has_auditable_trait(): void
    {
        // Act
        $survey = Survey::factory()->create([
            'title' => ['en-GB' => 'Auditable Survey'],
            'description' => ['en-GB' => 'Description'],
            'status' => SurveyStatusEnum::PUBLISHED,
            'financer_id' => $this->financer->id,
        ]);

        // Assert
        $this->assertTrue(method_exists($survey, 'audits'));
        $this->assertTrue(method_exists($survey, 'getAuditEvents'));
    }

    #[Test]
    public function it_can_determine_if_survey_is_active(): void
    {
        // Act & Assert - Active survey
        $activeSurvey = Survey::factory()->active()->create([
            'title' => ['en-GB' => 'Active Survey'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
            'status' => SurveyStatusEnum::PUBLISHED,
            'starts_at' => now()->subDays(5),
            'ends_at' => now()->addDays(25),
        ]);

        $this->assertTrue($activeSurvey->isActive());

        // Act & Assert - Draft survey
        $draftSurvey = Survey::factory()->draft()->create([
            'title' => ['en-GB' => 'Draft Survey'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
            'status' => SurveyStatusEnum::DRAFT,
            'starts_at' => now()->subDays(5),
            'ends_at' => now()->addDays(25),
        ]);

        $this->assertFalse($draftSurvey->isActive());
    }

    #[Test]
    public function it_can_determine_if_survey_is_draft(): void
    {
        // Act & Assert - Draft survey
        $draftSurvey = Survey::factory()->draft()->create([
            'title' => ['en-GB' => 'Draft Survey'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
            'status' => SurveyStatusEnum::DRAFT,
        ]);

        $this->assertTrue($draftSurvey->isDraft());

        // Act & Assert - Active survey
        $activeSurvey = Survey::factory()->active()->create([
            'title' => ['en-GB' => 'Active Survey'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
            'status' => SurveyStatusEnum::PUBLISHED,
            'starts_at' => now()->subDays(5),
            'ends_at' => now()->addDays(25),
        ]);

        $this->assertFalse($activeSurvey->isDraft());
    }

    #[Test]
    public function it_can_determine_if_survey_is_closed(): void
    {
        // Act & Assert - Closed survey
        $closedSurvey = Survey::factory()->closed()->create([
            'title' => ['en-GB' => 'Closed Survey'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
        ]);

        $this->assertTrue($closedSurvey->isClosed());

        // Act & Assert - Active survey with past end date
        $expiredSurvey = Survey::factory()->create([
            'title' => ['en-GB' => 'Expired Survey'],
            'description' => ['en-GB' => 'Description'],
            'status' => SurveyStatusEnum::PUBLISHED,
            'financer_id' => $this->financer->id,
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->subDays(1),
        ]);

        $this->assertTrue($expiredSurvey->isClosed());

        // Act & Assert - Active survey
        $activeSurvey = Survey::factory()->active()->create([
            'title' => ['en-GB' => 'Active Survey'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
            'status' => SurveyStatusEnum::PUBLISHED,
            'starts_at' => now()->subDays(5),
            'ends_at' => now()->addDays(25),
        ]);

        $this->assertFalse($activeSurvey->isClosed());
    }

    #[Test]
    public function it_can_determine_if_survey_can_be_modified(): void
    {
        // Act & Assert - Draft survey can be modified
        $draftSurvey = Survey::factory()->draft()->create([
            'title' => ['en-GB' => 'Draft Survey'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
            'status' => SurveyStatusEnum::DRAFT,
        ]);

        $this->assertTrue($draftSurvey->canBeModified());

        // Act & Assert - Active survey cannot be modified
        $activeSurvey = Survey::factory()->active()->create([
            'title' => ['en-GB' => 'Active Survey'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
            'status' => SurveyStatusEnum::PUBLISHED,
        ]);

        // TODO: update test when we have the logic to determine if a survey can be modified
        $this->assertTrue($activeSurvey->canBeModified());
    }

    #[Test]
    public function it_can_calculate_days_remaining(): void
    {
        // Act & Assert - Active survey
        $activeSurvey = Survey::factory()->create([
            'title' => ['en-GB' => 'Active Survey'],
            'description' => ['en-GB' => 'Description'],
            'status' => SurveyStatusEnum::PUBLISHED,
            'financer_id' => $this->financer->id,
            'starts_at' => now()->subDays(5),
            'ends_at' => now()->addDays(25),
        ]);

        $daysRemaining = $activeSurvey->getDaysRemaining();
        $this->assertIsInt($daysRemaining);
        $this->assertGreaterThan(0, $daysRemaining);

        // Act & Assert - Draft survey returns null
        $draftSurvey = Survey::factory()->draft()->create([
            'title' => ['en-GB' => 'Draft Survey'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
        ]);

        $this->assertNull($draftSurvey->getDaysRemaining());
    }

    #[Test]
    public function it_can_have_empty_settings(): void
    {
        // Act
        $survey = Survey::factory()->create([
            'title' => ['en-GB' => 'Survey with empty settings'],
            'description' => ['en-GB' => 'Description'],
            'status' => SurveyStatusEnum::DRAFT,
            'financer_id' => $this->financer->id,
            'settings' => null,
        ]);

        // Assert
        $this->assertNull($survey->settings);
    }

    // ==================== HasCreator Trait Tests ====================

    #[Test]
    public function it_automatically_sets_created_by_when_authenticated(): void
    {
        // Arrange
        $user = ModelFactory::createUser();
        // Act
        Auth::login($user);
        $survey = Survey::factory()->create([
            'title' => ['en-GB' => 'Survey with creator'],
            'description' => ['en-GB' => 'Description'],
            'status' => SurveyStatusEnum::DRAFT,
            'financer_id' => $this->financer->id,
        ]);

        // Assert
        $this->assertEquals($user->id, $survey->created_by);
        $this->assertDatabaseHas('int_survey_surveys', [
            'id' => $survey->id,
            'created_by' => $user->id,
        ]);
    }

    #[Test]
    public function it_does_not_set_created_by_when_not_authenticated(): void
    {
        // Act
        Auth::logout();
        $survey = Survey::factory()->create([
            'title' => ['en-GB' => 'Survey without creator'],
            'description' => ['en-GB' => 'Description'],
            'status' => SurveyStatusEnum::DRAFT,
            'financer_id' => $this->financer->id,
        ]);

        // Assert
        $this->assertNull($survey->created_by);
        $this->assertDatabaseHas('int_survey_surveys', [
            'id' => $survey->id,
            'created_by' => null,
        ]);
    }

    #[Test]
    public function it_does_not_override_existing_created_by(): void
    {
        // Arrange
        $creator = ModelFactory::createUser();
        $currentUser = ModelFactory::createUser();

        // Act
        Auth::login($currentUser);
        $survey = Survey::factory()->create([
            'title' => ['en-GB' => 'Survey with existing creator'],
            'description' => ['en-GB' => 'Description'],
            'status' => SurveyStatusEnum::DRAFT,
            'financer_id' => $this->financer->id,
            'created_by' => $creator->id, // Explicitly set creator
        ]);

        // Assert
        $this->assertEquals($creator->id, $survey->created_by);
        $this->assertNotEquals($currentUser->id, $survey->created_by);
        $this->assertDatabaseHas('int_survey_surveys', [
            'id' => $survey->id,
            'created_by' => $creator->id,
        ]);
    }

    #[Test]
    public function it_sets_updated_by_when_updating(): void
    {
        // Arrange
        $creator = ModelFactory::createUser();
        $updater = ModelFactory::createUser();

        Auth::login($creator);
        $survey = Survey::factory()->create([
            'title' => ['en-GB' => 'Survey to update'],
            'description' => ['en-GB' => 'Description'],
            'status' => SurveyStatusEnum::DRAFT,
            'financer_id' => $this->financer->id,
        ]);

        // Act
        Auth::login($updater);
        $survey->update([
            'title' => ['en-GB' => 'Updated Survey Title'],
        ]);

        // Assert
        $this->assertEquals($creator->id, $survey->created_by);
        $this->assertEquals($updater->id, $survey->updated_by);
        $this->assertDatabaseHas('int_survey_surveys', [
            'id' => $survey->id,
            'created_by' => $creator->id,
            'updated_by' => $updater->id,
        ]);
    }

    #[Test]
    public function it_has_creator_relationship(): void
    {
        // Arrange
        $creator = ModelFactory::createUser();

        Auth::login($creator);
        $survey = Survey::factory()->create([
            'title' => ['en-GB' => 'Survey with creator relationship'],
            'description' => ['en-GB' => 'Description'],
            'status' => SurveyStatusEnum::DRAFT,
            'financer_id' => $this->financer->id,
        ]);

        // Act & Assert
        $this->assertInstanceOf(User::class, $survey->creator);
        $this->assertEquals($creator->id, $survey->creator->id);
        $this->assertEquals($creator->name, $survey->creator->name);
    }

    #[Test]
    public function it_has_updater_relationship(): void
    {
        // Arrange
        $creator = ModelFactory::createUser();
        $updater = ModelFactory::createUser();

        Auth::login($creator);
        $survey = Survey::factory()->create([
            'title' => ['en-GB' => 'Survey with updater relationship'],
            'description' => ['en-GB' => 'Description'],
            'status' => SurveyStatusEnum::DRAFT,
            'financer_id' => $this->financer->id,
        ]);

        // Act
        Auth::login($updater);
        $survey->update([
            'title' => ['en-GB' => 'Updated Survey'],
        ]);

        // Assert
        $this->assertInstanceOf(User::class, $survey->updater);
        $this->assertEquals($updater->id, $survey->updater->id);
        $this->assertEquals($updater->name, $survey->updater->name);
    }

    #[Test]
    public function it_can_check_if_was_created_by_user(): void
    {
        // Arrange
        $creator = ModelFactory::createUser();
        $otherUser = ModelFactory::createUser();

        Auth::login($creator);
        $survey = Survey::factory()->create([
            'title' => ['en-GB' => 'Survey to check creator'],
            'description' => ['en-GB' => 'Description'],
            'status' => SurveyStatusEnum::DRAFT,
            'financer_id' => $this->financer->id,
        ]);

        // Act & Assert
        $this->assertTrue($survey->wasCreatedBy($creator));
        $this->assertFalse($survey->wasCreatedBy($otherUser));
        $this->assertFalse($survey->wasCreatedBy(null));
    }

    #[Test]
    public function it_can_check_if_was_updated_by_user(): void
    {
        // Arrange
        $creator = ModelFactory::createUser();
        $updater = ModelFactory::createUser();
        $otherUser = ModelFactory::createUser();

        Auth::login($creator);
        $survey = Survey::factory()->create([
            'title' => ['en-GB' => 'Survey to check updater'],
            'description' => ['en-GB' => 'Description'],
            'status' => SurveyStatusEnum::DRAFT,
            'financer_id' => $this->financer->id,
        ]);

        // Act
        Auth::login($updater);
        $survey->update([
            'title' => ['en-GB' => 'Updated Survey'],
        ]);

        // Assert
        $this->assertTrue($survey->wasUpdatedBy($updater));
        $this->assertFalse($survey->wasUpdatedBy($creator));
        $this->assertFalse($survey->wasUpdatedBy($otherUser));
        $this->assertFalse($survey->wasUpdatedBy(null));
    }

    #[Test]
    public function it_handles_null_creator_and_updater_gracefully(): void
    {
        // Arrange
        $user = ModelFactory::createUser();

        // Act - Create without authentication
        Auth::logout();
        $survey = Survey::factory()->create([
            'title' => ['en-GB' => 'Survey without creator'],
            'description' => ['en-GB' => 'Description'],
            'status' => SurveyStatusEnum::DRAFT,
            'financer_id' => $this->financer->id,
        ]);

        // Assert
        $this->assertNull($survey->creator);
        $this->assertNull($survey->updater);
        $this->assertFalse($survey->wasCreatedBy($user));
        $this->assertFalse($survey->wasUpdatedBy($user));
    }

    #[Test]
    public function it_can_load_creator_and_updater_relationships(): void
    {
        // Arrange
        $creator = ModelFactory::createUser();
        $updater = ModelFactory::createUser();

        Auth::login($creator);
        $survey = Survey::factory()->create([
            'title' => ['en-GB' => 'Survey with relationships'],
            'description' => ['en-GB' => 'Description'],
            'status' => SurveyStatusEnum::DRAFT,
            'financer_id' => $this->financer->id,
        ]);

        Auth::login($updater);
        $survey->update([
            'title' => ['en-GB' => 'Updated Survey'],
        ]);

        // Act
        $surveyWithRelations = Survey::with(['creator', 'updater'])->find($survey->id);

        // Assert
        $this->assertTrue($surveyWithRelations->relationLoaded('creator'));
        $this->assertTrue($surveyWithRelations->relationLoaded('updater'));
        $this->assertEquals($creator->id, $surveyWithRelations->creator->id);
        $this->assertEquals($updater->id, $surveyWithRelations->updater->id);
    }

    // ==================== Response Rate Tests ====================

    #[Test]
    public function it_calculates_response_rate_with_some_responses(): void
    {
        // Arrange
        $user1 = ModelFactory::createUser();
        $user2 = ModelFactory::createUser();
        $user3 = ModelFactory::createUser();
        $user4 = ModelFactory::createUser();

        $survey = Survey::factory()->create([
            'title' => ['en-GB' => 'Test Survey'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
            'users_count' => 4,
        ]);

        // Attach 4 users to survey
        $survey->users()->attach([$user1->id, $user2->id, $user3->id, $user4->id]);

        // Create submissions for 2 users (50% response rate)
        Submission::factory()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $user1->id,
            'survey_id' => $survey->id,
        ]);
        Submission::factory()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $user2->id,
            'survey_id' => $survey->id,
        ]);

        // Act
        $responseRate = $survey->response_rate;

        // Assert
        // 2 users responded out of 4 users = 50%
        $this->assertEquals(50, $responseRate);
    }

    #[Test]
    public function it_calculates_response_rate_correctly_when_user_has_multiple_submissions(): void
    {
        // Arrange
        $user1 = ModelFactory::createUser();
        $user2 = ModelFactory::createUser();
        $user3 = ModelFactory::createUser();

        $survey = Survey::factory()->create([
            'title' => ['en-GB' => 'Test Survey'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
            'users_count' => 3,
        ]);

        // Attach 3 users to survey
        $survey->users()->attach([$user1->id, $user2->id, $user3->id]);

        // Create multiple submissions for user1 (should still count as 1 respondent)
        Submission::factory()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $user1->id,
            'survey_id' => $survey->id,
        ]);
        Submission::factory()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $user1->id,
            'survey_id' => $survey->id,
        ]);
        Submission::factory()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $user1->id,
            'survey_id' => $survey->id,
        ]);

        // Create one submission for user2
        Submission::factory()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $user2->id,
            'survey_id' => $survey->id,
        ]);

        // Act
        $responseRate = $survey->response_rate;

        // Assert
        // 2 distinct users responded out of 3 users = 66.67% rounded to 67%
        // (user1 with 3 submissions should count as 1, user2 with 1 submission should count as 1)
        $this->assertEquals(67, $responseRate);
    }

    #[Test]
    public function it_returns_zero_response_rate_when_no_users_attached(): void
    {
        // Arrange
        $survey = Survey::factory()->create([
            'title' => ['en-GB' => 'Test Survey'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
            'users_count' => 0,
        ]);

        // Act
        $responseRate = $survey->response_rate;

        // Assert
        $this->assertEquals(0, $responseRate);
    }

    #[Test]
    public function it_returns_zero_response_rate_when_no_submissions(): void
    {
        // Arrange
        $user1 = ModelFactory::createUser();
        $user2 = ModelFactory::createUser();

        $survey = Survey::factory()->create([
            'title' => ['en-GB' => 'Test Survey'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
            'users_count' => 2,
        ]);

        // Attach users but create no submissions
        $survey->users()->attach([$user1->id, $user2->id]);

        // Act
        $responseRate = $survey->response_rate;

        // Assert
        $this->assertEquals(0, $responseRate);
    }

    #[Test]
    public function it_returns_100_percent_response_rate_when_all_users_responded(): void
    {
        // Arrange
        $user1 = ModelFactory::createUser();
        $user2 = ModelFactory::createUser();
        $user3 = ModelFactory::createUser();

        $survey = Survey::factory()->create([
            'title' => ['en-GB' => 'Test Survey'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
            'users_count' => 3,
        ]);

        // Attach users
        $survey->users()->attach([$user1->id, $user2->id, $user3->id]);

        // Create submissions for all users
        Submission::factory()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $user1->id,
            'survey_id' => $survey->id,
        ]);
        Submission::factory()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $user2->id,
            'survey_id' => $survey->id,
        ]);
        Submission::factory()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $user3->id,
            'survey_id' => $survey->id,
        ]);

        // Act
        $responseRate = $survey->response_rate;

        // Assert
        $this->assertEquals(100, $responseRate);
    }

    // ==================== Participation Rate Tests ====================

    #[Test]
    public function it_calculates_completion_rate_with_complete_submissions(): void
    {
        // Arrange
        $user1 = ModelFactory::createUser();
        $user2 = ModelFactory::createUser();
        $user3 = ModelFactory::createUser();

        $survey = Survey::factory()->create([
            'title' => ['en-GB' => 'Test Survey'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
            'users_count' => 3,
        ]);

        // Create 2 questions for the survey
        $question1 = Question::factory()->create([
            'text' => ['en-GB' => 'Question 1'],
            'financer_id' => $this->financer->id,
        ]);
        $question2 = Question::factory()->create([
            'text' => ['en-GB' => 'Question 2'],
            'financer_id' => $this->financer->id,
        ]);

        // Attach questions to survey
        $survey->questions()->attach($question1->id, ['position' => 1]);
        $survey->questions()->attach($question2->id, ['position' => 2]);

        // Attach users to survey
        $survey->users()->attach([$user1->id, $user2->id, $user3->id]);

        // Create completed submissions
        $submission1 = Submission::factory()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $user1->id,
            'survey_id' => $survey->id,
            'completed_at' => now(), // Mark as completed
        ]);
        $submission2 = Submission::factory()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $user2->id,
            'survey_id' => $survey->id,
            'completed_at' => now(), // Mark as completed
        ]);

        // Create complete answers for submission1 (2 answers = 2 questions)
        Answer::factory()->create([
            'user_id' => $user1->id,
            'submission_id' => $submission1->id,
            'question_id' => $question1->id,
            'answer' => ['value' => 'Answer 1'],
        ]);
        Answer::factory()->create([
            'user_id' => $user1->id,
            'submission_id' => $submission1->id,
            'question_id' => $question2->id,
            'answer' => ['value' => 'Answer 2'],
        ]);

        // Create complete answers for submission2 (2 answers = 2 questions)
        Answer::factory()->create([
            'user_id' => $user2->id,
            'submission_id' => $submission2->id,
            'question_id' => $question1->id,
            'answer' => ['value' => 'Answer 3'],
        ]);
        Answer::factory()->create([
            'user_id' => $user2->id,
            'submission_id' => $submission2->id,
            'question_id' => $question2->id,
            'answer' => ['value' => 'Answer 4'],
        ]);

        // Act
        $completionRate = $survey->completion_rate;

        // Assert
        // 2 completed users out of 3 users = 66.67% rounded to 67%
        $this->assertEquals(67, $completionRate);
    }

    #[Test]
    public function it_calculates_completion_rate_with_incomplete_submissions(): void
    {
        // Arrange
        $user1 = ModelFactory::createUser();
        $user2 = ModelFactory::createUser();
        $user3 = ModelFactory::createUser();

        $survey = Survey::factory()->create([
            'title' => ['en-GB' => 'Test Survey'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
            'users_count' => 3,
        ]);

        // Create 3 questions for the survey
        $question1 = Question::factory()->create([
            'text' => ['en-GB' => 'Question 1'],
            'financer_id' => $this->financer->id,
        ]);
        $question2 = Question::factory()->create([
            'text' => ['en-GB' => 'Question 2'],
            'financer_id' => $this->financer->id,
        ]);
        $question3 = Question::factory()->create([
            'text' => ['en-GB' => 'Question 3'],
            'financer_id' => $this->financer->id,
        ]);

        // Attach questions to survey
        $survey->questions()->attach($question1->id, ['position' => 1]);
        $survey->questions()->attach($question2->id, ['position' => 2]);
        $survey->questions()->attach($question3->id, ['position' => 3]);

        // Attach users to survey
        $survey->users()->attach([$user1->id, $user2->id, $user3->id]);

        // Create submissions
        // submission1 is completed (has all 3 answers)
        $submission1 = Submission::factory()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $user1->id,
            'survey_id' => $survey->id,
            'completed_at' => now(), // Mark as completed
        ]);
        // submission2 is not completed (missing one answer)
        $submission2 = Submission::factory()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $user2->id,
            'survey_id' => $survey->id,
            'completed_at' => null, // Not completed
        ]);

        // Create complete answers for submission1 (3 answers = 3 questions)
        Answer::factory()->create([
            'user_id' => $user1->id,
            'submission_id' => $submission1->id,
            'question_id' => $question1->id,
            'answer' => ['value' => 'Answer 1'],
        ]);
        Answer::factory()->create([
            'user_id' => $user1->id,
            'submission_id' => $submission1->id,
            'question_id' => $question2->id,
            'answer' => ['value' => 'Answer 2'],
        ]);
        Answer::factory()->create([
            'user_id' => $user1->id,
            'submission_id' => $submission1->id,
            'question_id' => $question3->id,
            'answer' => ['value' => 'Answer 3'],
        ]);

        // Create incomplete answers for submission2 (only 2 answers out of 3 questions)
        Answer::factory()->create([
            'user_id' => $user2->id,
            'submission_id' => $submission2->id,
            'question_id' => $question1->id,
            'answer' => ['value' => 'Answer 4'],
        ]);
        Answer::factory()->create([
            'user_id' => $user2->id,
            'submission_id' => $submission2->id,
            'question_id' => $question2->id,
            'answer' => ['value' => 'Answer 5'],
        ]);

        // Act
        $completionRate = $survey->completion_rate;

        // Assert
        // Only 1 completed user out of 3 users = 33.33% rounded to 33%
        $this->assertEquals(33, $completionRate);
    }

    #[Test]
    public function it_calculates_completion_rate_correctly_when_user_has_multiple_complete_submissions(): void
    {
        // Arrange
        $user1 = ModelFactory::createUser();
        $user2 = ModelFactory::createUser();
        $user3 = ModelFactory::createUser();

        $survey = Survey::factory()->create([
            'title' => ['en-GB' => 'Test Survey'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
            'users_count' => 3,
        ]);

        // Create 2 questions for the survey
        $question1 = Question::factory()->create([
            'text' => ['en-GB' => 'Question 1'],
            'financer_id' => $this->financer->id,
        ]);
        $question2 = Question::factory()->create([
            'text' => ['en-GB' => 'Question 2'],
            'financer_id' => $this->financer->id,
        ]);

        // Attach questions to survey
        $survey->questions()->attach($question1->id, ['position' => 1]);
        $survey->questions()->attach($question2->id, ['position' => 2]);

        // Attach users to survey
        $survey->users()->attach([$user1->id, $user2->id, $user3->id]);

        // Create MULTIPLE complete submissions for user1 (should count as 1 completed user)
        $submission1a = Submission::factory()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $user1->id,
            'survey_id' => $survey->id,
            'completed_at' => now(), // Mark as completed
        ]);
        Answer::factory()->create([
            'user_id' => $user1->id,
            'submission_id' => $submission1a->id,
            'question_id' => $question1->id,
            'answer' => ['value' => 'Answer 1a'],
        ]);
        Answer::factory()->create([
            'user_id' => $user1->id,
            'submission_id' => $submission1a->id,
            'question_id' => $question2->id,
            'answer' => ['value' => 'Answer 2a'],
        ]);

        $submission1b = Submission::factory()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $user1->id,
            'survey_id' => $survey->id,
            'completed_at' => now(), // Mark as completed
        ]);
        Answer::factory()->create([
            'user_id' => $user1->id,
            'submission_id' => $submission1b->id,
            'question_id' => $question1->id,
            'answer' => ['value' => 'Answer 1b'],
        ]);
        Answer::factory()->create([
            'user_id' => $user1->id,
            'submission_id' => $submission1b->id,
            'question_id' => $question2->id,
            'answer' => ['value' => 'Answer 2b'],
        ]);

        // Create one complete submission for user2
        $submission2 = Submission::factory()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $user2->id,
            'survey_id' => $survey->id,
            'completed_at' => now(), // Mark as completed
        ]);
        Answer::factory()->create([
            'user_id' => $user2->id,
            'submission_id' => $submission2->id,
            'question_id' => $question1->id,
            'answer' => ['value' => 'Answer 3'],
        ]);
        Answer::factory()->create([
            'user_id' => $user2->id,
            'submission_id' => $submission2->id,
            'question_id' => $question2->id,
            'answer' => ['value' => 'Answer 4'],
        ]);

        // Act
        $completionRate = $survey->completion_rate;

        // Assert
        // 2 distinct users completed (user1 with 2 complete submissions + user2 with 1) out of 3 users
        // = 66.67% rounded to 67%
        $this->assertEquals(67, $completionRate);
    }

    #[Test]
    public function it_returns_zero_completion_rate_when_no_users(): void
    {
        // Arrange
        $survey = Survey::factory()->create([
            'title' => ['en-GB' => 'Test Survey'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
        ]);

        // Create questions but no users
        $question1 = Question::factory()->create([
            'text' => ['en-GB' => 'Question 1'],
            'financer_id' => $this->financer->id,
        ]);
        $survey->questions()->attach($question1->id, ['position' => 1]);

        // Act
        $completionRate = $survey->completion_rate;

        // Assert
        $this->assertEquals(0, $completionRate);
    }

    #[Test]
    public function it_returns_zero_completion_rate_when_no_questions(): void
    {
        // Arrange
        $user1 = ModelFactory::createUser();
        $user2 = ModelFactory::createUser();

        $survey = Survey::factory()->create([
            'title' => ['en-GB' => 'Test Survey'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
        ]);

        // Attach users but no questions
        $survey->users()->attach([$user1->id, $user2->id]);

        // Act
        $completionRate = $survey->completion_rate;

        // Assert
        $this->assertEquals(0, $completionRate);
    }

    #[Test]
    public function it_returns_zero_completion_rate_when_no_submissions(): void
    {
        // Arrange
        $user1 = ModelFactory::createUser();
        $user2 = ModelFactory::createUser();

        $survey = Survey::factory()->create([
            'title' => ['en-GB' => 'Test Survey'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
        ]);

        // Create questions and attach users but no submissions
        $question1 = Question::factory()->create([
            'text' => ['en-GB' => 'Question 1'],
            'financer_id' => $this->financer->id,
        ]);
        $survey->questions()->attach($question1->id, ['position' => 1]);
        $survey->users()->attach([$user1->id, $user2->id]);

        // Act
        $completionRate = $survey->completion_rate;

        // Assert
        $this->assertEquals(0, $completionRate);
    }

    #[Test]
    public function it_returns_100_percent_completion_rate_when_all_users_completed(): void
    {
        // Arrange
        $user1 = ModelFactory::createUser();
        $user2 = ModelFactory::createUser();

        $survey = Survey::factory()->create([
            'title' => ['en-GB' => 'Test Survey'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
            'users_count' => 2,
        ]);

        // Create 1 question for the survey
        $question1 = Question::factory()->create([
            'text' => ['en-GB' => 'Question 1'],
            'financer_id' => $this->financer->id,
        ]);
        $survey->questions()->attach($question1->id, ['position' => 1]);
        $survey->users()->attach([$user1->id, $user2->id]);

        // Create completed submissions for both users
        $submission1 = Submission::factory()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $user1->id,
            'survey_id' => $survey->id,
            'completed_at' => now(), // Mark as completed
        ]);
        $submission2 = Submission::factory()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $user2->id,
            'survey_id' => $survey->id,
            'completed_at' => now(), // Mark as completed
        ]);

        // Create complete answers for both submissions
        Answer::factory()->create([
            'user_id' => $user1->id,
            'submission_id' => $submission1->id,
            'question_id' => $question1->id,
            'answer' => ['value' => 'Answer 1'],
        ]);
        Answer::factory()->create([
            'user_id' => $user2->id,
            'submission_id' => $submission2->id,
            'question_id' => $question1->id,
            'answer' => ['value' => 'Answer 2'],
        ]);

        // Act
        $completionRate = $survey->completion_rate;

        // Assert
        // Both users completed the survey (completed_at is set)
        $this->assertEquals(100, $completionRate);
    }

    #[Test]
    public function it_ignores_soft_deleted_answers_in_participation_calculation(): void
    {
        // Arrange
        $user1 = ModelFactory::createUser();
        $user2 = ModelFactory::createUser();

        $survey = Survey::factory()->create([
            'title' => ['en-GB' => 'Test Survey'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
            'users_count' => 2,
        ]);

        // Create 2 questions for the survey
        $question1 = Question::factory()->create([
            'text' => ['en-GB' => 'Question 1'],
            'financer_id' => $this->financer->id,
        ]);
        $question2 = Question::factory()->create([
            'text' => ['en-GB' => 'Question 2'],
            'financer_id' => $this->financer->id,
        ]);

        $survey->questions()->attach($question1->id, ['position' => 1]);
        $survey->questions()->attach($question2->id, ['position' => 2]);
        $survey->users()->attach([$user1->id, $user2->id]);

        // Create submissions
        // submission1 will be completed (has all answers)
        $submission1 = Submission::factory()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $user1->id,
            'survey_id' => $survey->id,
            'completed_at' => now(), // Mark as completed
        ]);
        // submission2 will not be completed (will have a deleted answer)
        $submission2 = Submission::factory()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $user2->id,
            'survey_id' => $survey->id,
            'completed_at' => null, // Not completed
        ]);

        // Create complete answers for submission1
        Answer::factory()->create([
            'user_id' => $user1->id,
            'submission_id' => $submission1->id,
            'question_id' => $question1->id,
            'answer' => ['value' => 'Answer 1'],
        ]);
        Answer::factory()->create([
            'user_id' => $user1->id,
            'submission_id' => $submission1->id,
            'question_id' => $question2->id,
            'answer' => ['value' => 'Answer 2'],
        ]);

        // Create answers for submission2, but soft delete one
        $answer1 = Answer::factory()->create([
            'user_id' => $user2->id,
            'submission_id' => $submission2->id,
            'question_id' => $question1->id,
            'answer' => ['value' => 'Answer 3'],
        ]);
        Answer::factory()->create([
            'user_id' => $user2->id,
            'submission_id' => $submission2->id,
            'question_id' => $question2->id,
            'answer' => ['value' => 'Answer 4'],
        ]);

        // Soft delete one answer (simulating incomplete submission)
        $answer1->delete();

        // Act
        $completionRate = $survey->completion_rate;

        // Assert
        // Only submission1 is marked as completed (completed_at is set)
        // submission2 is not completed (completed_at is null) because it has a deleted answer
        // So only 1 completed user out of 2 users = 50%
        $this->assertEquals(50, $completionRate);
    }

    // ==================== Performance / Caching Tests ====================

    #[Test]
    public function it_caches_response_rate_to_avoid_multiple_queries(): void
    {
        // Arrange
        $user1 = ModelFactory::createUser();
        $user2 = ModelFactory::createUser();

        $survey = Survey::factory()->create([
            'title' => ['en-GB' => 'Test Survey'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
            'users_count' => 2,
        ]);

        $survey->users()->attach([$user1->id, $user2->id]);

        Submission::factory()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $user1->id,
            'survey_id' => $survey->id,
        ]);

        // Act - Access response_rate multiple times
        DB::enableQueryLog();

        $rate1 = $survey->response_rate;
        $queriesAfterFirstAccess = count((array) DB::getQueryLog());

        $rate2 = $survey->response_rate;
        $queriesAfterSecondAccess = count((array) DB::getQueryLog());

        $rate3 = $survey->response_rate;
        $queriesAfterThirdAccess = count((array) DB::getQueryLog());

        DB::disableQueryLog();

        // Assert
        $this->assertEquals(50, $rate1);
        $this->assertEquals(50, $rate2);
        $this->assertEquals(50, $rate3);

        // First access should execute queries
        $this->assertGreaterThan(0, $queriesAfterFirstAccess);

        // Second and third access should not execute new queries (cached)
        $this->assertEquals($queriesAfterFirstAccess, $queriesAfterSecondAccess, 'Second access should use cached value');
        $this->assertEquals($queriesAfterFirstAccess, $queriesAfterThirdAccess, 'Third access should use cached value');
    }

    #[Test]
    public function it_caches_completion_rate_to_avoid_multiple_queries(): void
    {
        // Arrange
        $user1 = ModelFactory::createUser();
        $user2 = ModelFactory::createUser();

        $survey = Survey::factory()->create([
            'title' => ['en-GB' => 'Test Survey'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
            'users_count' => 2,
        ]);

        $survey->users()->attach([$user1->id, $user2->id]);

        Submission::factory()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $user1->id,
            'survey_id' => $survey->id,
            'completed_at' => now(),
        ]);

        // Act - Access completion_rate multiple times
        DB::enableQueryLog();

        $rate1 = $survey->completion_rate;
        $queriesAfterFirstAccess = count((array) DB::getQueryLog());

        $rate2 = $survey->completion_rate;
        $queriesAfterSecondAccess = count((array) DB::getQueryLog());

        $rate3 = $survey->completion_rate;
        $queriesAfterThirdAccess = count((array) DB::getQueryLog());

        DB::disableQueryLog();

        // Assert
        $this->assertEquals(50, $rate1);
        $this->assertEquals(50, $rate2);
        $this->assertEquals(50, $rate3);

        // First access should execute queries
        $this->assertGreaterThan(0, $queriesAfterFirstAccess);

        // Second and third access should not execute new queries (cached)
        $this->assertEquals($queriesAfterFirstAccess, $queriesAfterSecondAccess, 'Second access should use cached value');
        $this->assertEquals($queriesAfterFirstAccess, $queriesAfterThirdAccess, 'Third access should use cached value');
    }

    #[Test]
    public function it_does_not_share_cache_between_different_survey_instances(): void
    {
        // Arrange
        $user1 = ModelFactory::createUser();
        $user2 = ModelFactory::createUser();

        $survey1 = Survey::factory()->create([
            'title' => ['en-GB' => 'Survey 1'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
            'users_count' => 2,
        ]);

        $survey2 = Survey::factory()->create([
            'title' => ['en-GB' => 'Survey 2'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
            'users_count' => 2,
        ]);

        // Survey 1: 1 user out of 2 responded (50%)
        $survey1->users()->attach([$user1->id, $user2->id]);
        Submission::factory()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $user1->id,
            'survey_id' => $survey1->id,
        ]);

        // Survey 2: 2 users out of 2 responded (100%)
        $survey2->users()->attach([$user1->id, $user2->id]);
        Submission::factory()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $user1->id,
            'survey_id' => $survey2->id,
        ]);
        Submission::factory()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $user2->id,
            'survey_id' => $survey2->id,
        ]);

        // Act
        $rate1 = $survey1->response_rate;
        $rate2 = $survey2->response_rate;

        // Assert - Each survey should have its own cached value
        $this->assertEquals(50, $rate1);
        $this->assertEquals(100, $rate2);
    }

    #[Test]
    public function it_recalculates_rates_when_model_is_reloaded_from_database(): void
    {
        // Arrange
        $user1 = ModelFactory::createUser();
        $user2 = ModelFactory::createUser();

        $survey = Survey::factory()->create([
            'title' => ['en-GB' => 'Test Survey'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
            'users_count' => 2,
        ]);

        $survey->users()->attach([$user1->id, $user2->id]);

        Submission::factory()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $user1->id,
            'survey_id' => $survey->id,
        ]);

        // Act - Get initial rate
        $initialRate = $survey->response_rate;

        // Add another submission
        Submission::factory()->create([
            'financer_id' => $this->financer->id,
            'user_id' => $user2->id,
            'survey_id' => $survey->id,
        ]);

        // Get rate before reload (should still be cached)
        $cachedRate = $survey->response_rate;

        // Reload the model from database (creates new instance with fresh cache)
        $reloadedSurvey = Survey::find($survey->id);

        // Get rate from reloaded instance (should recalculate with new data)
        $reloadedRate = $reloadedSurvey->response_rate;

        // Assert
        $this->assertEquals(50, $initialRate, 'Initial rate should be 50% (1/2 users)');
        $this->assertEquals(50, $cachedRate, 'Cached rate should still be 50% on same instance');
        $this->assertEquals(100, $reloadedRate, 'Reloaded instance should calculate 100% (2/2 users)');
    }
}
