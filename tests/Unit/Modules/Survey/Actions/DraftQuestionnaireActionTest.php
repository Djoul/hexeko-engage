<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Survey\Actions;

use App\Integrations\Survey\Actions\Questionnaire\DraftQuestionnaireAction;
use App\Integrations\Survey\Enums\QuestionnaireTypeEnum;
use App\Integrations\Survey\Models\Questionnaire;
use App\Models\Financer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('survey')]
#[Group('questionnaire')]
class DraftQuestionnaireActionTest extends TestCase
{
    use DatabaseTransactions;

    private DraftQuestionnaireAction $action;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new DraftQuestionnaireAction;
        $this->financer = ModelFactory::createFinancer();

        Context::add('accessible_financers', [$this->financer->id]);
        Context::add('financer_id', $this->financer->id);
    }

    #[Test]
    public function it_creates_a_draft_questionnaire_successfully(): void
    {
        // Arrange
        $data = [
            'name' => [
                'en' => 'Draft Questionnaire',
                'fr' => 'Questionnaire Brouillon',
            ],
            'description' => [
                'en' => 'Draft Questionnaire Description',
                'fr' => 'Description du Questionnaire Brouillon',
            ],
            'instructions' => [
                'en' => 'Please answer all questions',
                'fr' => 'Veuillez répondre à toutes les questions',
            ],
            'type' => QuestionnaireTypeEnum::CUSTOM,
            'financer_id' => $this->financer->id,
        ];

        $questionnaire = new Questionnaire;

        // Act
        $result = $this->action->execute($questionnaire, $data);

        // Assert
        $this->assertInstanceOf(Questionnaire::class, $result);
        $this->assertNotNull($result->id);
        $this->assertEquals(QuestionnaireTypeEnum::CUSTOM, $result->type);
        $this->assertEquals($this->financer->id, $result->financer_id);

        // Check translations
        $this->assertEquals('Draft Questionnaire', $result->getTranslation('name', 'en'));
        $this->assertEquals('Questionnaire Brouillon', $result->getTranslation('name', 'fr'));
        $this->assertEquals('Draft Questionnaire Description', $result->getTranslation('description', 'en'));
        $this->assertEquals('Please answer all questions', $result->getTranslation('instructions', 'en'));

        // Check database persistence
        $this->assertDatabaseHas('int_survey_questionnaires', [
            'id' => $result->id,
            'financer_id' => $this->financer->id,
            'type' => QuestionnaireTypeEnum::CUSTOM,
        ]);
    }

    #[Test]
    public function it_creates_questionnaire_with_nps_type(): void
    {
        // Arrange
        $data = [
            'name' => [
                'en' => 'NPS Questionnaire',
            ],
            'description' => [
                'en' => 'Net Promoter Score',
            ],
            'instructions' => [
                'en' => 'Rate us',
            ],
            'type' => QuestionnaireTypeEnum::NPS,
            'financer_id' => $this->financer->id,
        ];

        $questionnaire = new Questionnaire;

        // Act
        $result = $this->action->execute($questionnaire, $data);

        // Assert
        $this->assertEquals(QuestionnaireTypeEnum::NPS, $result->type);
        $this->assertDatabaseHas('int_survey_questionnaires', [
            'id' => $result->id,
            'type' => QuestionnaireTypeEnum::NPS,
        ]);
    }

    #[Test]
    public function it_creates_questionnaire_with_satisfaction_type(): void
    {
        // Arrange
        $data = [
            'name' => [
                'en' => 'Satisfaction Questionnaire',
            ],
            'description' => [
                'en' => 'Customer Satisfaction',
            ],
            'instructions' => [
                'en' => 'Tell us about your experience',
            ],
            'type' => QuestionnaireTypeEnum::SATISFACTION,
            'financer_id' => $this->financer->id,
        ];

        $questionnaire = new Questionnaire;

        // Act
        $result = $this->action->execute($questionnaire, $data);

        // Assert
        $this->assertEquals(QuestionnaireTypeEnum::SATISFACTION, $result->type);
        $this->assertDatabaseHas('int_survey_questionnaires', [
            'id' => $result->id,
            'type' => QuestionnaireTypeEnum::SATISFACTION,
        ]);
    }

    #[Test]
    public function it_creates_questionnaire_with_settings(): void
    {
        // Arrange
        $settings = [
            'theme' => 'light',
            'show_progress' => true,
            'allow_back' => false,
        ];

        $data = [
            'name' => [
                'en' => 'Questionnaire',
            ],
            'description' => [
                'en' => 'Description',
            ],
            'instructions' => [
                'en' => 'Instructions',
            ],
            'type' => QuestionnaireTypeEnum::CUSTOM,
            'financer_id' => $this->financer->id,
            'settings' => $settings,
        ];

        $questionnaire = new Questionnaire;

        // Act
        $result = $this->action->execute($questionnaire, $data);

        // Assert
        $this->assertEquals($settings, $result->settings);
    }

    #[Test]
    public function it_creates_questionnaire_with_minimal_data(): void
    {
        // Arrange
        $data = [
            'name' => [
                'en' => 'Minimal Questionnaire',
            ],
            'description' => [
                'en' => 'Minimal Description',
            ],
            'instructions' => [
                'en' => 'Minimal Instructions',
            ],
            'type' => QuestionnaireTypeEnum::CUSTOM,
            'financer_id' => $this->financer->id,
        ];

        $questionnaire = new Questionnaire;

        // Act
        $result = $this->action->execute($questionnaire, $data);

        // Assert
        $this->assertInstanceOf(Questionnaire::class, $result);
        $this->assertEquals(QuestionnaireTypeEnum::CUSTOM, $result->type);
        $this->assertNull($result->settings);
    }

    #[Test]
    public function it_creates_questionnaire_with_multiple_languages(): void
    {
        // Arrange
        $data = [
            'name' => [
                'en' => 'English Name',
                'fr' => 'Nom Français',
                'nl' => 'Nederlandse Naam',
            ],
            'description' => [
                'en' => 'English Description',
                'fr' => 'Description Française',
                'nl' => 'Nederlandse Beschrijving',
            ],
            'instructions' => [
                'en' => 'English Instructions',
                'fr' => 'Instructions Françaises',
                'nl' => 'Nederlandse Instructies',
            ],
            'type' => QuestionnaireTypeEnum::CUSTOM,
            'financer_id' => $this->financer->id,
        ];

        $questionnaire = new Questionnaire;

        // Act
        $result = $this->action->execute($questionnaire, $data);

        // Assert
        $this->assertEquals('English Name', $result->getTranslation('name', 'en'));
        $this->assertEquals('Nom Français', $result->getTranslation('name', 'fr'));
        $this->assertEquals('Nederlandse Naam', $result->getTranslation('name', 'nl'));

        $this->assertEquals('English Description', $result->getTranslation('description', 'en'));
        $this->assertEquals('Description Française', $result->getTranslation('description', 'fr'));

        $this->assertEquals('English Instructions', $result->getTranslation('instructions', 'en'));
        $this->assertEquals('Instructions Françaises', $result->getTranslation('instructions', 'fr'));
    }

    #[Test]
    public function it_returns_refreshed_questionnaire_instance(): void
    {
        // Arrange
        $data = [
            'name' => [
                'en' => 'Questionnaire',
            ],
            'description' => [
                'en' => 'Description',
            ],
            'instructions' => [
                'en' => 'Instructions',
            ],
            'type' => QuestionnaireTypeEnum::CUSTOM,
            'financer_id' => $this->financer->id,
        ];

        $questionnaire = new Questionnaire;

        // Act
        $result = $this->action->execute($questionnaire, $data);

        // Assert
        $this->assertTrue($result->exists);
        $this->assertNotNull($result->created_at);
        $this->assertNotNull($result->updated_at);
    }

    #[Test]
    public function it_creates_questionnaire_with_all_types(): void
    {
        // Arrange
        $types = [
            QuestionnaireTypeEnum::NPS,
            QuestionnaireTypeEnum::SATISFACTION,
            QuestionnaireTypeEnum::CUSTOM,
        ];

        foreach ($types as $type) {
            $data = [
                'name' => [
                    'en' => "Questionnaire {$type}",
                ],
                'description' => [
                    'en' => 'Description',
                ],
                'instructions' => [
                    'en' => 'Instructions',
                ],
                'type' => $type,
                'financer_id' => $this->financer->id,
            ];

            $questionnaire = new Questionnaire;

            // Act
            $result = $this->action->execute($questionnaire, $data);

            // Assert
            $this->assertEquals($type, $result->type);
            $this->assertDatabaseHas('int_survey_questionnaires', [
                'id' => $result->id,
                'type' => $type,
            ]);
        }
    }

    #[Test]
    public function it_sets_is_default_to_false_by_default(): void
    {
        // Arrange
        $data = [
            'name' => [
                'en' => 'Questionnaire',
            ],
            'description' => [
                'en' => 'Description',
            ],
            'instructions' => [
                'en' => 'Instructions',
            ],
            'type' => QuestionnaireTypeEnum::CUSTOM,
            'financer_id' => $this->financer->id,
        ];

        $questionnaire = new Questionnaire;

        // Act
        $result = $this->action->execute($questionnaire, $data);

        // Assert
        $this->assertFalse($result->is_default);
    }

    #[Test]
    public function it_can_create_default_questionnaire(): void
    {
        // Arrange
        $data = [
            'name' => [
                'en' => 'Default Questionnaire',
            ],
            'description' => [
                'en' => 'Description',
            ],
            'instructions' => [
                'en' => 'Instructions',
            ],
            'type' => QuestionnaireTypeEnum::CUSTOM,
            'financer_id' => $this->financer->id,
            'is_default' => true,
        ];

        $questionnaire = new Questionnaire;

        // Act
        $result = $this->action->execute($questionnaire, $data);

        // Assert
        $this->assertTrue($result->is_default);
        $this->assertDatabaseHas('int_survey_questionnaires', [
            'id' => $result->id,
            'is_default' => true,
        ]);
    }
}
