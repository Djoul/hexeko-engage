<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Survey\Models;

use App\Integrations\Survey\Database\factories\QuestionFactory;
use App\Integrations\Survey\Enums\QuestionnaireTypeEnum;
use App\Integrations\Survey\Models\Questionnaire;
use App\Models\Financer;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('survey')]
#[Group('questionnaire')]
class QuestionnaireTest extends TestCase
{
    use DatabaseTransactions;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->financer = ModelFactory::createFinancer();

        Context::add('accessible_financers', [$this->financer->id]);
        Context::add('financer_id', $this->financer->id);
    }

    #[Test]
    public function it_uses_uuid_as_primary_key(): void
    {
        $questionnaire = new Questionnaire;

        $this->assertTrue($questionnaire->getIncrementing() === false);
        $this->assertEquals('string', $questionnaire->getKeyType());
    }

    #[Test]
    public function it_can_create_a_questionnaire(): void
    {
        // Act
        $questionnaire = Questionnaire::factory()->create([
            'name' => [
                'en-GB' => 'Test Questionnaire',
                'fr-FR' => 'Questionnaire de Test',
                'nl-BE' => 'Test Vragenlijst',
            ],
            'description' => [
                'en-GB' => 'Test Description',
                'fr-FR' => 'Description de Test',
                'nl-BE' => 'Test Beschrijving',
            ],
            'instructions' => [
                'en-GB' => 'Test Instructions',
                'fr-FR' => 'Instructions de Test',
                'nl-BE' => 'Test Instructies',
            ],
            'type' => QuestionnaireTypeEnum::CUSTOM,
            'financer_id' => $this->financer->id,
            'settings' => ['allow_multiple_responses' => true, 'show_progress' => false],
            'is_default' => false,
        ]);

        // Assert
        $this->assertInstanceOf(Questionnaire::class, $questionnaire);
        $this->assertDatabaseHas('int_survey_questionnaires', [
            'id' => $questionnaire->id,
            'financer_id' => $this->financer->id,
            'type' => QuestionnaireTypeEnum::CUSTOM,
            'is_default' => false,
        ]);

        // Test translations
        $this->assertEquals('Test Questionnaire', $questionnaire->getTranslation('name', 'en-GB'));
        $this->assertEquals('Questionnaire de Test', $questionnaire->getTranslation('name', 'fr-FR'));
        $this->assertEquals('Test Vragenlijst', $questionnaire->getTranslation('name', 'nl-BE'));

        $this->assertEquals('Test Description', $questionnaire->getTranslation('description', 'en-GB'));
        $this->assertEquals('Test Instructions', $questionnaire->getTranslation('instructions', 'en-GB'));
    }

    #[Test]
    public function it_can_create_an_archived_questionnaire(): void
    {
        // Act
        $questionnaire = Questionnaire::factory()->create([
            'name' => ['en-GB' => 'Archived Questionnaire'],
            'description' => ['en-GB' => 'Archived Description'],
            'instructions' => ['en-GB' => 'Archived Instructions'],
            'type' => QuestionnaireTypeEnum::NPS,
            'financer_id' => $this->financer->id,
            'is_default' => false,
        ]);

        // Assert
        $this->assertDatabaseHas('int_survey_questionnaires', [
            'id' => $questionnaire->id,
        ]);
    }

    #[Test]
    public function it_can_handle_different_questionnaire_types(): void
    {
        // Arrange
        $types = [
            QuestionnaireTypeEnum::NPS,
            QuestionnaireTypeEnum::SATISFACTION,
            QuestionnaireTypeEnum::CUSTOM,
        ];

        foreach ($types as $type) {
            // Act
            $questionnaire = Questionnaire::factory()->create([
                'name' => ['en-GB' => "Questionnaire of type {$type}"],
                'description' => ['en-GB' => "Description for {$type}"],
                'instructions' => ['en-GB' => "Instructions for {$type}"],
                'type' => $type,
                'financer_id' => $this->financer->id,
            ]);

            // Assert
            $this->assertEquals($type, $questionnaire->type);
            $this->assertDatabaseHas('int_survey_questionnaires', [
                'id' => $questionnaire->id,
                'type' => $type,
            ]);
        }
    }

    #[Test]
    public function it_can_store_settings_as_json(): void
    {
        // Arrange
        $settings = [
            'allow_multiple_responses' => true,
            'show_progress' => true,
            'randomize_questions' => false,
            'time_limit' => 300, // seconds
            'required_fields' => ['name', 'email'],
        ];

        // Act
        $questionnaire = Questionnaire::factory()->create([
            'name' => ['en-GB' => 'Questionnaire with settings'],
            'description' => ['en-GB' => 'Description'],
            'instructions' => ['en-GB' => 'Instructions'],
            'type' => QuestionnaireTypeEnum::CUSTOM,
            'financer_id' => $this->financer->id,
            'settings' => $settings,
        ]);

        // Assert
        $this->assertEquals($settings, $questionnaire->settings);
        $this->assertTrue($questionnaire->settings['allow_multiple_responses']);
        $this->assertTrue($questionnaire->settings['show_progress']);
        $this->assertFalse($questionnaire->settings['randomize_questions']);
        $this->assertContains('name', $questionnaire->settings['required_fields']);
    }

    #[Test]
    public function it_belongs_to_a_financer(): void
    {
        // Arrange
        $questionnaire = Questionnaire::factory()->create([
            'name' => ['en-GB' => 'Questionnaire with financer'],
            'description' => ['en-GB' => 'Description'],
            'instructions' => ['en-GB' => 'Instructions'],
            'type' => QuestionnaireTypeEnum::NPS,
            'financer_id' => $this->financer->id,
        ]);

        // Act & Assert
        $this->assertInstanceOf(Financer::class, $questionnaire->financer);
        $this->assertEquals($this->financer->id, $questionnaire->financer->id);
    }

    #[Test]
    public function it_can_have_many_questions(): void
    {
        // Arrange
        $questionnaire = Questionnaire::factory()->create([
            'name' => ['en-GB' => 'Questionnaire with questions'],
            'description' => ['en-GB' => 'Description'],
            'instructions' => ['en-GB' => 'Instructions'],
            'type' => QuestionnaireTypeEnum::CUSTOM,
            'financer_id' => $this->financer->id,
        ]);

        $question1 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);
        $question2 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);

        // Act
        $questionnaire->questions()->attach([$question1->id, $question2->id]);

        // Assert
        $this->assertCount(2, $questionnaire->questions);
        $this->assertTrue($questionnaire->questions->contains('id', $question1->id));
        $this->assertTrue($questionnaire->questions->contains('id', $question2->id));
    }

    #[Test]
    public function it_can_scope_archived_questionnaires(): void
    {
        // Arrange
        Questionnaire::factory()->create([
            'name' => ['en-GB' => 'Active Questionnaire'],
            'description' => ['en-GB' => 'Description'],
            'instructions' => ['en-GB' => 'Instructions'],
            'type' => QuestionnaireTypeEnum::NPS,
            'financer_id' => $this->financer->id,
        ]);

        Questionnaire::factory()->create([
            'name' => ['en-GB' => 'Archived Questionnaire'],
            'description' => ['en-GB' => 'Description'],
            'instructions' => ['en-GB' => 'Instructions'],
            'type' => QuestionnaireTypeEnum::SATISFACTION,
            'financer_id' => $this->financer->id,
            'archived_at' => now(),
        ]);

        $activeQuestionnaires = Questionnaire::query()->withoutArchived()->get();

        // Assert
        $this->assertCount(1, $activeQuestionnaires);
        $this->assertNull($activeQuestionnaires->first()->archived_at);

        $allQuestionnaires = Questionnaire::query()->withArchived()->get();

        // Assert
        $this->assertCount(2, $allQuestionnaires);

        $archivedQuestionnaires = Questionnaire::query()->onlyArchived()->get();

        // Assert
        $this->assertCount(1, $archivedQuestionnaires);
        $this->assertNotNull($archivedQuestionnaires->first()->archived_at);
    }

    #[Test]
    public function it_can_scope_questionnaires_by_type(): void
    {
        // Arrange
        Questionnaire::factory()->create([
            'name' => ['en-GB' => 'NPS Questionnaire'],
            'description' => ['en-GB' => 'Description'],
            'instructions' => ['en-GB' => 'Instructions'],
            'type' => QuestionnaireTypeEnum::NPS,
            'financer_id' => $this->financer->id,
        ]);

        Questionnaire::factory()->create([
            'name' => ['en-GB' => 'Satisfaction Questionnaire'],
            'description' => ['en-GB' => 'Description'],
            'instructions' => ['en-GB' => 'Instructions'],
            'type' => QuestionnaireTypeEnum::SATISFACTION,
            'financer_id' => $this->financer->id,
        ]);

        // Act
        $npsQuestionnaires = Questionnaire::query()->byType(QuestionnaireTypeEnum::NPS)->get();

        // Assert
        $this->assertCount(1, $npsQuestionnaires);
        $this->assertEquals(QuestionnaireTypeEnum::NPS, $npsQuestionnaires->first()->type);
    }

    #[Test]
    public function it_can_scope_questionnaires_by_financer(): void
    {
        // Arrange
        $financer2 = ModelFactory::createFinancer();
        $financer3 = ModelFactory::createFinancer();

        Context::add('accessible_financers', [$this->financer->id, $financer2->id]);

        Questionnaire::factory()->create([
            'name' => ['en-GB' => 'Questionnaire for Financer 1'],
            'description' => ['en-GB' => 'Description'],
            'instructions' => ['en-GB' => 'Instructions'],
            'type' => QuestionnaireTypeEnum::NPS,
            'financer_id' => $this->financer->id,
        ]);

        Questionnaire::factory()->create([
            'name' => ['en-GB' => 'Questionnaire for Financer 2'],
            'description' => ['en-GB' => 'Description'],
            'instructions' => ['en-GB' => 'Instructions'],
            'type' => QuestionnaireTypeEnum::SATISFACTION,
            'financer_id' => $financer2->id,
        ]);

        Questionnaire::factory()->create([
            'name' => ['en-GB' => 'Questionnaire for Financer 3'],
            'description' => ['en-GB' => 'Description'],
            'instructions' => ['en-GB' => 'Instructions'],
            'type' => QuestionnaireTypeEnum::CUSTOM,
            'financer_id' => $financer3->id,
        ]);

        // Act
        $financer1Questionnaires = Questionnaire::query()->where('financer_id', $this->financer->id)->get();

        // Assert
        $this->assertCount(1, $financer1Questionnaires);
        $this->assertTrue($financer1Questionnaires->contains('financer_id', $this->financer->id));
    }

    #[Test]
    public function it_can_scope_questionnaires_by_is_default(): void
    {
        // Arrange
        Questionnaire::factory()->create([
            'name' => ['en-GB' => 'Default Questionnaire'],
            'description' => ['en-GB' => 'Description'],
            'instructions' => ['en-GB' => 'Instructions'],
            'type' => QuestionnaireTypeEnum::NPS,
            'financer_id' => $this->financer->id,
            'is_default' => true,
        ]);

        Questionnaire::factory()->create([
            'name' => ['en-GB' => 'Non-default Questionnaire'],
            'description' => ['en-GB' => 'Description'],
            'instructions' => ['en-GB' => 'Instructions'],
            'type' => QuestionnaireTypeEnum::SATISFACTION,
            'financer_id' => $this->financer->id,
            'is_default' => false,
        ]);

        // Act
        $defaultQuestionnaires = Questionnaire::query()->where('is_default', true)->get();

        // Assert
        $this->assertCount(1, $defaultQuestionnaires);
        $this->assertTrue($defaultQuestionnaires->first()->is_default);
    }

    #[Test]
    public function it_uses_soft_deletes(): void
    {
        // Arrange
        $questionnaire = Questionnaire::factory()->create([
            'name' => ['en-GB' => 'Questionnaire to delete'],
            'description' => ['en-GB' => 'Description'],
            'instructions' => ['en-GB' => 'Instructions'],
            'type' => QuestionnaireTypeEnum::NPS,
            'financer_id' => $this->financer->id,
        ]);

        // Act
        $questionnaire->delete();

        // Assert
        $this->assertSoftDeleted('int_survey_questionnaires', ['id' => $questionnaire->id]);
        $this->assertNull(Questionnaire::find($questionnaire->id));
        $this->assertNotNull(Questionnaire::withTrashed()->find($questionnaire->id));
    }

    #[Test]
    public function it_has_auditable_trait(): void
    {
        // Act
        $questionnaire = Questionnaire::factory()->create([
            'name' => ['en-GB' => 'Auditable Questionnaire'],
            'description' => ['en-GB' => 'Description'],
            'instructions' => ['en-GB' => 'Instructions'],
            'type' => QuestionnaireTypeEnum::NPS,
            'financer_id' => $this->financer->id,
        ]);

        // Assert
        $this->assertTrue(method_exists($questionnaire, 'audits'));
        $this->assertTrue(method_exists($questionnaire, 'getAuditEvents'));
    }

    #[Test]
    public function it_can_be_marked_as_default(): void
    {
        // Act
        $questionnaire = Questionnaire::factory()->create([
            'name' => ['en-GB' => 'Default Questionnaire'],
            'description' => ['en-GB' => 'Description'],
            'instructions' => ['en-GB' => 'Instructions'],
            'type' => QuestionnaireTypeEnum::NPS,
            'financer_id' => $this->financer->id,
            'is_default' => true,
        ]);

        // Assert
        $this->assertTrue($questionnaire->is_default);
        $this->assertDatabaseHas('int_survey_questionnaires', [
            'id' => $questionnaire->id,
            'is_default' => true,
        ]);
    }

    #[Test]
    public function it_can_have_empty_settings(): void
    {
        // Act
        $questionnaire = Questionnaire::factory()->create([
            'name' => ['en-GB' => 'Questionnaire with empty settings'],
            'description' => ['en-GB' => 'Description'],
            'instructions' => ['en-GB' => 'Instructions'],
            'type' => QuestionnaireTypeEnum::NPS,
            'financer_id' => $this->financer->id,
            'settings' => null,
        ]);

        // Assert
        $this->assertNull($questionnaire->settings);
    }

    // ==================== HasCreator Trait Tests ====================

    #[Test]
    public function it_automatically_sets_created_by_when_authenticated(): void
    {
        // Arrange
        $user = ModelFactory::createUser();

        // Act
        Auth::login($user);
        $questionnaire = Questionnaire::factory()->create([
            'name' => ['en-GB' => 'Questionnaire with creator'],
            'description' => ['en-GB' => 'Description'],
            'instructions' => ['en-GB' => 'Instructions'],
            'type' => QuestionnaireTypeEnum::NPS,
            'financer_id' => $this->financer->id,
        ]);

        // Assert
        $this->assertEquals($user->id, $questionnaire->created_by);
        $this->assertDatabaseHas('int_survey_questionnaires', [
            'id' => $questionnaire->id,
            'created_by' => $user->id,
        ]);
    }

    #[Test]
    public function it_does_not_set_created_by_when_not_authenticated(): void
    {
        // Act
        Auth::logout();
        $questionnaire = Questionnaire::factory()->create([
            'name' => ['en-GB' => 'Questionnaire without creator'],
            'description' => ['en-GB' => 'Description'],
            'instructions' => ['en-GB' => 'Instructions'],
            'type' => QuestionnaireTypeEnum::NPS,
            'financer_id' => $this->financer->id,
        ]);

        // Assert
        $this->assertNull($questionnaire->created_by);
        $this->assertDatabaseHas('int_survey_questionnaires', [
            'id' => $questionnaire->id,
            'created_by' => null,
        ]);
    }

    #[Test]
    public function it_sets_updated_by_when_updating(): void
    {
        // Arrange
        $creator = ModelFactory::createUser();
        $updater = ModelFactory::createUser();

        Auth::login($creator);
        $questionnaire = Questionnaire::factory()->create([
            'name' => ['en-GB' => 'Questionnaire to update'],
            'description' => ['en-GB' => 'Description'],
            'instructions' => ['en-GB' => 'Instructions'],
            'type' => QuestionnaireTypeEnum::NPS,
            'financer_id' => $this->financer->id,
        ]);

        // Act
        Auth::login($updater);
        $questionnaire->update([
            'name' => ['en-GB' => 'Updated Questionnaire Title'],
        ]);

        // Assert
        $this->assertEquals($creator->id, $questionnaire->created_by);
        $this->assertEquals($updater->id, $questionnaire->updated_by);
        $this->assertDatabaseHas('int_survey_questionnaires', [
            'id' => $questionnaire->id,
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
        $questionnaire = Questionnaire::factory()->create([
            'name' => ['en-GB' => 'Questionnaire with creator relationship'],
            'description' => ['en-GB' => 'Description'],
            'instructions' => ['en-GB' => 'Instructions'],
            'type' => QuestionnaireTypeEnum::NPS,
            'financer_id' => $this->financer->id,
        ]);

        // Act & Assert
        $this->assertInstanceOf(User::class, $questionnaire->creator);
        $this->assertEquals($creator->id, $questionnaire->creator->id);
        $this->assertEquals($creator->name, $questionnaire->creator->name);
    }

    #[Test]
    public function it_has_updater_relationship(): void
    {
        // Arrange
        $creator = ModelFactory::createUser();
        $updater = ModelFactory::createUser();

        Auth::login($creator);
        $questionnaire = Questionnaire::factory()->create([
            'name' => ['en-GB' => 'Questionnaire with updater relationship'],
            'description' => ['en-GB' => 'Description'],
            'instructions' => ['en-GB' => 'Instructions'],
            'type' => QuestionnaireTypeEnum::NPS,
            'financer_id' => $this->financer->id,
        ]);

        // Act
        Auth::login($updater);
        $questionnaire->update([
            'name' => ['en-GB' => 'Updated Questionnaire'],
        ]);

        // Assert
        $this->assertInstanceOf(User::class, $questionnaire->updater);
        $this->assertEquals($updater->id, $questionnaire->updater->id);
        $this->assertEquals($updater->name, $questionnaire->updater->name);
    }

    #[Test]
    public function it_can_check_if_was_created_by_user(): void
    {
        // Arrange
        $creator = ModelFactory::createUser();
        $otherUser = ModelFactory::createUser();

        Auth::login($creator);
        $questionnaire = Questionnaire::factory()->create([
            'name' => ['en-GB' => 'Questionnaire to check creator'],
            'description' => ['en-GB' => 'Description'],
            'instructions' => ['en-GB' => 'Instructions'],
            'type' => QuestionnaireTypeEnum::NPS,
            'financer_id' => $this->financer->id,
        ]);

        // Act & Assert
        $this->assertTrue($questionnaire->wasCreatedBy($creator));
        $this->assertFalse($questionnaire->wasCreatedBy($otherUser));
        $this->assertFalse($questionnaire->wasCreatedBy(null));
    }

    #[Test]
    public function it_can_check_if_was_updated_by_user(): void
    {
        // Arrange
        $creator = ModelFactory::createUser();
        $updater = ModelFactory::createUser();
        $otherUser = ModelFactory::createUser();

        Auth::login($creator);
        $questionnaire = Questionnaire::factory()->create([
            'name' => ['en-GB' => 'Questionnaire to check updater'],
            'description' => ['en-GB' => 'Description'],
            'instructions' => ['en-GB' => 'Instructions'],
            'type' => QuestionnaireTypeEnum::NPS,
            'financer_id' => $this->financer->id,
        ]);

        // Act
        Auth::login($updater);
        $questionnaire->update([
            'name' => ['en-GB' => 'Updated Questionnaire'],
        ]);

        // Assert
        $this->assertTrue($questionnaire->wasUpdatedBy($updater));
        $this->assertFalse($questionnaire->wasUpdatedBy($creator));
        $this->assertFalse($questionnaire->wasUpdatedBy($otherUser));
        $this->assertFalse($questionnaire->wasUpdatedBy(null));
    }
}
