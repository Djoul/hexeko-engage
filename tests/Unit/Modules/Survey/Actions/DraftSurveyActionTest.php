<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Survey\Actions;

use App\Integrations\Survey\Actions\Survey\DraftSurveyAction;
use App\Integrations\Survey\Enums\SurveyStatusEnum;
use App\Integrations\Survey\Models\Survey;
use App\Models\Financer;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('survey')]
class DraftSurveyActionTest extends TestCase
{
    use DatabaseTransactions;

    private DraftSurveyAction $action;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new DraftSurveyAction;
        $this->financer = ModelFactory::createFinancer();

        Context::add('accessible_financers', [$this->financer->id]);
        Context::add('financer_id', $this->financer->id);
    }

    #[Test]
    public function it_creates_a_draft_survey_successfully(): void
    {
        // Arrange
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $this->financer->users()->attach([
            $user1->id => ['active' => true, 'from' => now()],
            $user2->id => ['active' => true, 'from' => now()],
        ]);

        $data = [
            'title' => [
                'en' => 'Draft Survey',
                'fr' => 'Campagne Brouillon',
            ],
            'description' => [
                'en' => 'Draft Survey Description',
                'fr' => 'Description de Campagne Brouillon',
            ],
            'welcome_message' => [
                'en' => 'Welcome',
                'fr' => 'Bienvenue',
            ],
            'thank_you_message' => [
                'en' => 'Thank you',
                'fr' => 'Merci',
            ],
            'financer_id' => $this->financer->id,
            'starts_at' => now()->addDays(7)->format('Y-m-d H:i:s'),
            'ends_at' => now()->addDays(30)->format('Y-m-d H:i:s'),
        ];

        // Act
        $result = $this->action->execute($data);

        // Assert
        $this->assertInstanceOf(Survey::class, $result);
        $this->assertNotNull($result->id);
        $this->assertEquals(SurveyStatusEnum::DRAFT, $result->status);
        $this->assertEquals($data['financer_id'], $result->financer_id);

        // Check translations
        $this->assertEquals('Draft Survey', $result->getTranslation('title', 'en'));
        $this->assertEquals('Campagne Brouillon', $result->getTranslation('title', 'fr'));

        // Users are not automatically attached when no segment is provided
        $result->load('users');
        $this->assertCount(0, $result->users);
        $this->assertDatabaseMissing('int_survey_survey_user', [
            'survey_id' => $result->id,
        ]);

        // Check database persistence
        $this->assertDatabaseHas('int_survey_surveys', [
            'id' => $result->id,
            'financer_id' => $this->financer->id,
            'status' => SurveyStatusEnum::DRAFT,
        ]);
    }

    #[Test]
    public function it_always_sets_status_to_draft(): void
    {
        // Arrange
        $data = [
            'title' => [
                'en' => 'Survey',
            ],
            'description' => [
                'en' => 'Description',
            ],
            'welcome_message' => [
                'en' => 'Welcome',
            ],
            'thank_you_message' => [
                'en' => 'Thank you',
            ],
            'status' => SurveyStatusEnum::PUBLISHED, // Try to set active
            'financer_id' => $this->financer->id,
            'starts_at' => now()->addDays(1)->format('Y-m-d H:i:s'),
            'ends_at' => now()->addDays(10)->format('Y-m-d H:i:s'),
        ];

        // Act
        $result = $this->action->execute($data);

        // Assert - Should still be DRAFT
        $this->assertEquals(SurveyStatusEnum::DRAFT, $result->status);
        $this->assertDatabaseHas('int_survey_surveys', [
            'id' => $result->id,
            'status' => SurveyStatusEnum::DRAFT,
        ]);
    }

    #[Test]
    public function it_does_not_attach_financer_users_without_segment(): void
    {
        // Arrange
        $users = User::factory()->count(5)->create();

        foreach ($users as $user) {
            $this->financer->users()->attach($user->id, ['active' => true, 'from' => now()]);
        }

        $data = [
            'title' => [
                'en' => 'Survey',
            ],
            'description' => [
                'en' => 'Description',
            ],
            'welcome_message' => [
                'en' => 'Welcome',
            ],
            'thank_you_message' => [
                'en' => 'Thank you',
            ],
            'financer_id' => $this->financer->id,
            'starts_at' => now()->addDays(1)->format('Y-m-d H:i:s'),
            'ends_at' => now()->addDays(10)->format('Y-m-d H:i:s'),
        ];

        // Act
        $result = $this->action->execute($data);

        // Assert
        $result->load('users');
        $this->assertCount(0, $result->users);
        $this->assertDatabaseMissing('int_survey_survey_user', [
            'survey_id' => $result->id,
        ]);
    }

    #[Test]
    public function it_creates_survey_with_no_users_if_financer_has_none(): void
    {
        // Arrange
        $data = [
            'title' => [
                'en' => 'Survey Without Users',
            ],
            'description' => [
                'en' => 'Description',
            ],
            'welcome_message' => [
                'en' => 'Welcome',
            ],
            'thank_you_message' => [
                'en' => 'Thank you',
            ],
            'financer_id' => $this->financer->id,
            'starts_at' => now()->addDays(1)->format('Y-m-d H:i:s'),
            'ends_at' => now()->addDays(10)->format('Y-m-d H:i:s'),
        ];

        // Act
        $result = $this->action->execute($data);

        // Assert
        $this->assertCount(0, $result->users);
        $this->assertDatabaseMissing('int_survey_survey_user', [
            'survey_id' => $result->id,
        ]);
    }

    #[Test]
    public function it_creates_survey_with_settings(): void
    {
        // Arrange
        $settings = [
            'theme' => 'dark',
            'notifications' => true,
            'anonymous' => false,
        ];

        $data = [
            'title' => [
                'en' => 'Survey',
            ],
            'description' => [
                'en' => 'Description',
            ],
            'welcome_message' => [
                'en' => 'Welcome',
            ],
            'thank_you_message' => [
                'en' => 'Thank you',
            ],
            'financer_id' => $this->financer->id,
            'starts_at' => now()->addDays(1)->format('Y-m-d H:i:s'),
            'ends_at' => now()->addDays(10)->format('Y-m-d H:i:s'),
            'settings' => $settings,
        ];

        // Act
        $result = $this->action->execute($data);

        // Assert
        $this->assertEquals($settings, $result->settings);
    }

    #[Test]
    public function it_creates_survey_with_minimal_data(): void
    {
        // Arrange
        $data = [
            'title' => [
                'en' => 'Minimal Survey',
            ],
            'description' => [
                'en' => 'Minimal Description',
            ],
            'welcome_message' => [
                'en' => 'Welcome',
            ],
            'thank_you_message' => [
                'en' => 'Thank you',
            ],
            'financer_id' => $this->financer->id,
            'starts_at' => now()->addDays(1)->format('Y-m-d H:i:s'),
            'ends_at' => now()->addDays(10)->format('Y-m-d H:i:s'),
        ];

        // Act
        $result = $this->action->execute($data);

        // Assert
        $this->assertInstanceOf(Survey::class, $result);
        $this->assertEquals(SurveyStatusEnum::DRAFT, $result->status);
        $this->assertNull($result->settings);
    }

    #[Test]
    public function it_creates_survey_with_multiple_languages(): void
    {
        // Arrange
        $data = [
            'title' => [
                'en' => 'English Title',
                'fr' => 'Titre Français',
                'nl' => 'Nederlandse Titel',
            ],
            'description' => [
                'en' => 'English Description',
                'fr' => 'Description Française',
                'nl' => 'Nederlandse Beschrijving',
            ],
            'welcome_message' => [
                'en' => 'Welcome',
                'fr' => 'Bienvenue',
                'nl' => 'Welkom',
            ],
            'thank_you_message' => [
                'en' => 'Thank you',
                'fr' => 'Merci',
                'nl' => 'Dank u',
            ],
            'financer_id' => $this->financer->id,
            'starts_at' => now()->addDays(1)->format('Y-m-d H:i:s'),
            'ends_at' => now()->addDays(10)->format('Y-m-d H:i:s'),
        ];

        // Act
        $result = $this->action->execute($data);

        // Assert
        $this->assertEquals('English Title', $result->getTranslation('title', 'en'));
        $this->assertEquals('Titre Français', $result->getTranslation('title', 'fr'));
        $this->assertEquals('Nederlandse Titel', $result->getTranslation('title', 'nl'));
    }

    #[Test]
    public function it_executes_in_database_transaction(): void
    {
        // Arrange
        $data = [
            'title' => [
                'en' => 'Survey',
            ],
            'description' => [
                'en' => 'Description',
            ],
            'welcome_message' => [
                'en' => 'Welcome',
            ],
            'thank_you_message' => [
                'en' => 'Thank you',
            ],
            'financer_id' => $this->financer->id,
            'starts_at' => now()->addDays(1)->format('Y-m-d H:i:s'),
            'ends_at' => now()->addDays(10)->format('Y-m-d H:i:s'),
        ];

        // Act
        $result = $this->action->execute($data);

        // Assert - If transaction worked properly, survey and users should both be persisted
        $this->assertDatabaseHas('int_survey_surveys', [
            'id' => $result->id,
        ]);

        // If survey has users, they should be in the pivot table
        if ($result->users->isNotEmpty()) {
            foreach ($result->users as $user) {
                $this->assertDatabaseHas('int_survey_survey_user', [
                    'survey_id' => $result->id,
                    'user_id' => $user->id,
                ]);
            }
        }
    }
}
