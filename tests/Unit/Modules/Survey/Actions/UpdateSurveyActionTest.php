<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Survey\Actions;

use App\Integrations\Survey\Actions\Survey\UpdateSurveyAction;
use App\Integrations\Survey\Database\factories\SurveyFactory;
use App\Integrations\Survey\Enums\SurveyStatusEnum;
use App\Integrations\Survey\Models\Survey;
use Database\Factories\DivisionFactory;
use Database\Factories\FinancerFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('survey')]
class UpdateSurveyActionTest extends TestCase
{
    use DatabaseTransactions;

    private UpdateSurveyAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new UpdateSurveyAction;
    }

    #[Test]
    public function it_updates_a_survey_successfully(): void
    {
        // Arrange
        $financer = resolve(FinancerFactory::class)->create();
        resolve(DivisionFactory::class)->create();
        $survey = resolve(SurveyFactory::class)->create([
            'financer_id' => $financer->id,
            'status' => SurveyStatusEnum::DRAFT,
        ]);

        $updateData = [
            'title' => [
                'en' => 'Updated Survey Title',
                'fr' => 'Titre de Campagne Mis à Jour',
            ],
            'description' => [
                'en' => 'Updated Survey Description',
                'fr' => 'Description de Campagne Mis à Jour',
            ],
            'welcome_message' => [
                'en' => 'Updated Welcome Message',
                'fr' => 'Message de Bienvenue Mis à Jour',
            ],
            'thank_you_message' => [
                'en' => 'Updated Thank You Message',
                'fr' => 'Message de Remerciement Mis à Jour',
            ],
            'status' => SurveyStatusEnum::PUBLISHED,
            'financer_id' => $financer->id,
            'starts_at' => now()->addDays(1)->format('Y-m-d H:i:s'),
            'ends_at' => now()->addDays(30)->format('Y-m-d H:i:s'),
            'settings' => ['theme' => 'dark', 'notifications' => false],
        ];

        // Act
        $result = $this->action->execute($survey, $updateData);

        // Assert
        $this->assertInstanceOf(Survey::class, $result);
        $this->assertEquals($survey->id, $result->id);
        $this->assertEquals($updateData['financer_id'], $result->financer_id);
        $this->assertEquals($updateData['status'], $result->status);
        $this->assertEquals($updateData['settings'], $result->settings);

        // Check translations
        $this->assertEquals('Updated Survey Title', $result->getTranslation('title', 'en'));
        $this->assertEquals('Titre de Campagne Mis à Jour', $result->getTranslation('title', 'fr'));
        $this->assertEquals('Updated Survey Description', $result->getTranslation('description', 'en'));
        $this->assertEquals('Updated Welcome Message', $result->getTranslation('welcome_message', 'en'));
        $this->assertEquals('Updated Thank You Message', $result->getTranslation('thank_you_message', 'en'));

        // Check database persistence
        $this->assertDatabaseHas('int_survey_surveys', [
            'id' => $survey->id,
            'financer_id' => $financer->id,
            'status' => SurveyStatusEnum::PUBLISHED,
        ]);
    }

    #[Test]
    public function it_updates_only_provided_fields(): void
    {
        // Arrange
        $financer = resolve(FinancerFactory::class)->create();
        $originalTitle = [
            'en' => 'Original Title',
            'fr' => 'Titre Original',
        ];
        $originalDescription = [
            'en' => 'Original Description',
        ];

        $survey = resolve(SurveyFactory::class)->create([
            'financer_id' => $financer->id,
            'title' => $originalTitle,
            'description' => $originalDescription,
            'status' => SurveyStatusEnum::DRAFT,
        ]);

        $originalStartsAt = $survey->starts_at;
        $originalEndsAt = $survey->ends_at;

        // Only update status and welcome_message
        $updateData = [
            'welcome_message' => [
                'en' => 'New Welcome Message',
            ],
            'status' => SurveyStatusEnum::PUBLISHED,
            'financer_id' => $financer->id,
            'starts_at' => $originalStartsAt->format('Y-m-d H:i:s'),
            'ends_at' => $originalEndsAt->format('Y-m-d H:i:s'),
        ];

        // Act
        $result = $this->action->execute($survey, $updateData);

        // Assert
        $this->assertEquals('New Welcome Message', $result->getTranslation('welcome_message', 'en'));
        $this->assertEquals(SurveyStatusEnum::PUBLISHED, $result->status);

        // These should remain unchanged
        $this->assertEquals('Original Title', $result->getTranslation('title', 'en'));
        $this->assertEquals('Titre Original', $result->getTranslation('title', 'fr'));
        $this->assertEquals('Original Description', $result->getTranslation('description', 'en'));
        $this->assertEquals($originalStartsAt->format('Y-m-d H:i:s'), $result->starts_at->format('Y-m-d H:i:s'));
        $this->assertEquals($originalEndsAt->format('Y-m-d H:i:s'), $result->ends_at->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function it_updates_survey_status_transitions(): void
    {
        // Arrange
        $financer = resolve(FinancerFactory::class)->create();
        $survey = resolve(SurveyFactory::class)->create([
            'financer_id' => $financer->id,
            'status' => SurveyStatusEnum::DRAFT,
        ]);

        $statusTransitions = [
            SurveyStatusEnum::PUBLISHED,
            SurveyStatusEnum::ARCHIVED,
        ];

        foreach ($statusTransitions as $newStatus) {
            $updateData = [
                'status' => $newStatus,
                'financer_id' => $financer->id,
                'starts_at' => $survey->starts_at->format('Y-m-d H:i:s'),
                'ends_at' => $survey->ends_at->format('Y-m-d H:i:s'),
            ];

            // Act
            $result = $this->action->execute($survey, $updateData);

            // Assert
            $this->assertEquals($newStatus, $result->status);
            $this->assertDatabaseHas('int_survey_surveys', [
                'id' => $survey->id,
                'status' => $newStatus,
            ]);

            // Update survey reference for next iteration
            $survey = $result;
        }
    }

    #[Test]
    public function it_handles_empty_update_data(): void
    {
        // Arrange
        $financer = resolve(FinancerFactory::class)->create();
        $survey = resolve(SurveyFactory::class)->create([
            'financer_id' => $financer->id,
            'status' => SurveyStatusEnum::DRAFT,
        ]);

        $originalData = [
            'title' => $survey->title,
            'description' => $survey->description,
            'status' => $survey->status,
        ];

        // Act
        $result = $this->action->execute($survey, []);

        // Assert
        $this->assertEquals($originalData['title'], $result->title);
        $this->assertEquals($originalData['description'], $result->description);
        $this->assertEquals($originalData['status'], $result->status);
    }
}
