<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Survey\Actions;

use App\Integrations\Survey\Actions\Survey\CreateSurveyAction;
use App\Integrations\Survey\Enums\SurveyStatusEnum;
use App\Integrations\Survey\Jobs\SyncSurveyUsersJob;
use App\Integrations\Survey\Models\Survey;
use Database\Factories\FinancerFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('survey')]
class CreateSurveyActionTest extends TestCase
{
    use DatabaseTransactions;

    private CreateSurveyAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new CreateSurveyAction;
    }

    #[Test]
    public function it_creates_a_survey_successfully(): void
    {
        Bus::fake();

        // Arrange
        $financer = resolve(FinancerFactory::class)->create();

        $data = [
            'title' => [
                'en' => 'Test Survey',
                'fr' => 'Campagne de Test',
            ],
            'description' => [
                'en' => 'Test Survey Description',
                'fr' => 'Description de la Campagne de Test',
            ],
            'welcome_message' => [
                'en' => 'Welcome to our test survey',
                'fr' => 'Bienvenue dans notre campagne de test',
            ],
            'thank_you_message' => [
                'en' => 'Thank you for participating',
                'fr' => 'Merci de votre participation',
            ],
            'status' => SurveyStatusEnum::DRAFT,
            'financer_id' => $financer->id,
            'starts_at' => now()->addDays(7)->format('Y-m-d H:i:s'),
            'ends_at' => now()->addDays(30)->format('Y-m-d H:i:s'),
            'settings' => ['theme' => 'light', 'notifications' => true],
        ];

        // Act
        $result = $this->action->execute($data);

        // Assert
        $this->assertInstanceOf(Survey::class, $result);
        $this->assertNotNull($result->id);
        $this->assertEquals($data['financer_id'], $result->financer_id);
        $this->assertEquals($data['status'], $result->status);
        $this->assertEquals($data['settings'], $result->settings);

        // Check translations
        $this->assertEquals('Test Survey', $result->getTranslation('title', 'en'));
        $this->assertEquals('Campagne de Test', $result->getTranslation('title', 'fr'));
        $this->assertEquals('Test Survey Description', $result->getTranslation('description', 'en'));
        $this->assertEquals('Welcome to our test survey', $result->getTranslation('welcome_message', 'en'));
        $this->assertEquals('Thank you for participating', $result->getTranslation('thank_you_message', 'en'));

        // Check database persistence
        $this->assertDatabaseHas('int_survey_surveys', [
            'id' => $result->id,
            'financer_id' => $financer->id,
            'status' => SurveyStatusEnum::DRAFT,
        ]);

        // Ensure no users are linked by default
        $this->assertDatabaseMissing('int_survey_survey_user', [
            'survey_id' => $result->id,
        ]);

        Bus::assertDispatched(SyncSurveyUsersJob::class);
    }

    #[Test]
    public function it_creates_a_survey_with_minimal_data(): void
    {
        // Arrange
        $financer = resolve(FinancerFactory::class)->create();

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
            'status' => SurveyStatusEnum::DRAFT,
            'financer_id' => $financer->id,
            'starts_at' => now()->addDays(1)->format('Y-m-d H:i:s'),
            'ends_at' => now()->addDays(10)->format('Y-m-d H:i:s'),
        ];

        // Act
        $result = $this->action->execute($data);

        // Assert
        $this->assertInstanceOf(Survey::class, $result);
        $this->assertEquals($data['financer_id'], $result->financer_id);
        $this->assertEquals($data['status'], $result->status);
        $this->assertNull($result->settings);

        $this->assertDatabaseHas('int_survey_surveys', [
            'id' => $result->id,
            'financer_id' => $financer->id,
            'status' => SurveyStatusEnum::DRAFT,
        ]);
    }

    #[Test]
    public function it_creates_survey_with_different_statuses(): void
    {
        // Arrange
        $financer = resolve(FinancerFactory::class)->create();

        $statuses = [
            SurveyStatusEnum::DRAFT,
            SurveyStatusEnum::PUBLISHED,
            SurveyStatusEnum::ARCHIVED,
        ];

        foreach ($statuses as $status) {
            $data = [
                'title' => [
                    'en' => "Survey with status {$status}",
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
                'status' => $status,
                'financer_id' => $financer->id,
                'starts_at' => now()->addDays(1)->format('Y-m-d H:i:s'),
                'ends_at' => now()->addDays(10)->format('Y-m-d H:i:s'),
            ];

            // Act
            $result = $this->action->execute($data);

            // Assert
            $this->assertEquals($status, $result->status);
            $this->assertDatabaseHas('int_survey_surveys', [
                'id' => $result->id,
                'status' => $status,
            ]);
        }
    }
}
