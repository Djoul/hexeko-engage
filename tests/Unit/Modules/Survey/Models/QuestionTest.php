<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Survey\Models;

use App\Integrations\Survey\Database\factories\ThemeFactory;
use App\Integrations\Survey\Enums\QuestionTypeEnum;
use App\Integrations\Survey\Models\Question;
use App\Integrations\Survey\Models\Theme;
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
#[Group('question')]
class QuestionTest extends TestCase
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
        $question = new Question;

        $this->assertTrue($question->getIncrementing() === false);
        $this->assertEquals('string', $question->getKeyType());
    }

    #[Test]
    public function it_can_create_a_question(): void
    {
        // Arrange
        $theme = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);

        // Act
        $question = Question::factory()->create([
            'text' => [
                'en-GB' => 'Test Question',
                'fr-FR' => 'Question de Test',
                'nl-BE' => 'Test Vraag',
            ],
            'help_text' => [
                'en-GB' => 'Test Help Text',
                'fr-FR' => 'Texte d\'aide de Test',
                'nl-BE' => 'Test Hulptekst',
            ],
            'type' => QuestionTypeEnum::MULTIPLE_CHOICE,
            'theme_id' => $theme->id,
            'financer_id' => $this->financer->id,
            'metadata' => ['category' => 'survey', 'difficulty' => 'medium'],
            'is_default' => false,
        ]);

        // Assert
        $this->assertInstanceOf(Question::class, $question);
        $this->assertDatabaseHas('int_survey_questions', [
            'id' => $question->id,
            'theme_id' => $theme->id,
            'financer_id' => $this->financer->id,
            'type' => QuestionTypeEnum::MULTIPLE_CHOICE,
            'is_default' => false,
        ]);

        // Test translations
        $this->assertEquals('Test Question', $question->getTranslation('text', 'en-GB'));
        $this->assertEquals('Question de Test', $question->getTranslation('text', 'fr-FR'));
        $this->assertEquals('Test Vraag', $question->getTranslation('text', 'nl-BE'));
    }

    #[Test]
    public function it_can_create_an_archived_question(): void
    {
        // Arrange
        $theme = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);

        // Act
        $question = Question::factory()->create([
            'text' => ['en-GB' => 'Archived Question'],
            'help_text' => ['en-GB' => 'Archived Help Text'],
            'type' => QuestionTypeEnum::TEXT,
            'theme_id' => $theme->id,
            'financer_id' => $this->financer->id,
            'is_default' => false,
        ]);

        // Assert
        $this->assertDatabaseHas('int_survey_questions', [
            'id' => $question->id,
        ]);
    }

    #[Test]
    public function it_can_handle_different_question_types(): void
    {
        // Arrange
        $theme = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);

        $types = [
            QuestionTypeEnum::TEXT,
            QuestionTypeEnum::MULTIPLE_CHOICE,
            QuestionTypeEnum::SINGLE_CHOICE,
            QuestionTypeEnum::SCALE,
        ];

        foreach ($types as $type) {
            // Act
            $question = Question::factory()->create([
                'text' => ['en-GB' => "Question of type {$type}"],
                'help_text' => ['en-GB' => "Help text for {$type}"],
                'type' => $type,
                'theme_id' => $theme->id,
                'financer_id' => $this->financer->id,
            ]);

            // Assert
            $this->assertEquals($type, $question->type);
            $this->assertDatabaseHas('int_survey_questions', [
                'id' => $question->id,
                'type' => $type,
            ]);
        }
    }

    #[Test]
    public function it_can_store_metadata_as_json(): void
    {
        // Arrange
        $theme = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);
        $metadata = [
            'category' => 'survey',
            'difficulty' => 'hard',
            'tags' => ['important', 'feedback'],
            'estimated_time' => 120, // seconds
        ];

        // Act
        $question = Question::factory()->create([
            'text' => ['en-GB' => 'Question with metadata'],
            'help_text' => ['en-GB' => 'Help text'],
            'type' => QuestionTypeEnum::MULTIPLE_CHOICE,
            'theme_id' => $theme->id,
            'financer_id' => $this->financer->id,
            'metadata' => $metadata,
        ]);

        // Assert
        $this->assertEquals($metadata, $question->metadata);
        $this->assertEquals('survey', $question->metadata['category']);
        $this->assertEquals('hard', $question->metadata['difficulty']);
        $this->assertContains('important', $question->metadata['tags']);
    }

    #[Test]
    public function it_belongs_to_a_theme(): void
    {
        // Arrange
        $theme = resolve(ThemeFactory::class)->create(['financer_id' => null]);
        $question = Question::factory()->create([
            'text' => ['en-GB' => 'Question with theme'],
            'help_text' => ['en-GB' => 'Help text'],
            'type' => QuestionTypeEnum::TEXT,
            'theme_id' => $theme->id,
            'financer_id' => $this->financer->id,
        ]);

        // Act & Assert
        $this->assertInstanceOf(Theme::class, $question->theme);
        $this->assertEquals($theme->id, $question->theme->id);
    }

    #[Test]
    public function it_belongs_to_a_financer(): void
    {
        // Arrange
        $theme = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);
        $question = Question::factory()->create([
            'text' => ['en-GB' => 'Question with financer'],
            'help_text' => ['en-GB' => 'Help text'],
            'type' => QuestionTypeEnum::TEXT,
            'theme_id' => $theme->id,
            'financer_id' => $this->financer->id,
        ]);

        // Act & Assert
        $this->assertInstanceOf(Financer::class, $question->financer);
        $this->assertEquals($this->financer->id, $question->financer->id);
    }

    #[Test]
    public function it_can_scope_archived_questions(): void
    {
        // Arrange
        $theme = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);

        Question::factory()->create([
            'text' => ['en-GB' => 'Active Question'],
            'help_text' => ['en-GB' => 'Help text'],
            'type' => QuestionTypeEnum::TEXT,
            'theme_id' => $theme->id,
            'financer_id' => $this->financer->id,
        ]);

        Question::factory()->create([
            'text' => ['en-GB' => 'Archived Question'],
            'help_text' => ['en-GB' => 'Help text'],
            'type' => QuestionTypeEnum::TEXT,
            'theme_id' => $theme->id,
            'financer_id' => $this->financer->id,
            'archived_at' => now(),
        ]);

        $archivedQuestions = Question::query()->withoutArchived()->get();

        // Assert
        $this->assertCount(1, $archivedQuestions);
        $this->assertNull($archivedQuestions->first()->archived_at);

        $archivedQuestions = Question::query()->withArchived()->get();

        // Assert
        $this->assertCount(2, $archivedQuestions);

        $archivedQuestions = Question::query()->onlyArchived()->get();

        // Assert
        $this->assertCount(1, $archivedQuestions);
        $this->assertNotNull($archivedQuestions->first()->archived_at);
    }

    #[Test]
    public function it_can_scope_questions_by_theme(): void
    {
        // Arrange
        $theme1 = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);
        $theme2 = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);

        Question::factory()->create([
            'text' => ['en-GB' => 'Question for Theme 1'],
            'help_text' => ['en-GB' => 'Help text'],
            'type' => QuestionTypeEnum::TEXT,
            'theme_id' => $theme1->id,
            'financer_id' => $this->financer->id,
            'is_default' => false,
        ]);

        Question::factory()->create([
            'text' => ['en-GB' => 'Question for Theme 2'],
            'help_text' => ['en-GB' => 'Help text'],
            'type' => QuestionTypeEnum::TEXT,
            'theme_id' => $theme2->id,
            'financer_id' => $this->financer->id,
        ]);

        // Act
        $theme1Questions = Question::query()->byTheme($theme1->id)->get();

        // Assert
        $this->assertCount(1, $theme1Questions);
        $this->assertEquals($theme1->id, $theme1Questions->first()->theme_id);
    }

    #[Test]
    public function it_can_scope_questions_by_type(): void
    {
        // Arrange
        $theme = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);

        Question::factory()->create([
            'text' => ['en-GB' => 'Text Question'],
            'help_text' => ['en-GB' => 'Help text'],
            'type' => QuestionTypeEnum::TEXT,
            'theme_id' => $theme->id,
            'financer_id' => $this->financer->id,
        ]);

        Question::factory()->create([
            'text' => ['en-GB' => 'Multiple Choice Question'],
            'help_text' => ['en-GB' => 'Help text'],
            'type' => QuestionTypeEnum::MULTIPLE_CHOICE,
            'theme_id' => $theme->id,
            'financer_id' => $this->financer->id,
        ]);

        // Act
        $textQuestions = Question::query()->byType(QuestionTypeEnum::MULTIPLE_CHOICE)->get();

        // Assert
        $this->assertCount(1, $textQuestions);
        $this->assertEquals(QuestionTypeEnum::MULTIPLE_CHOICE, $textQuestions->first()->type);
    }

    #[Test]
    public function it_can_scope_questions_by_financer(): void
    {
        // Arrange
        $financer2 = ModelFactory::createFinancer();
        $financer3 = ModelFactory::createFinancer();
        $theme1 = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);
        $theme2 = resolve(ThemeFactory::class)->create(['financer_id' => $financer2->id]);
        $theme3 = resolve(ThemeFactory::class)->create(['financer_id' => $financer3->id]);

        Context::add('accessible_financers', [$this->financer->id, $financer2->id]);

        Question::factory()->create([
            'text' => ['en-GB' => 'Question for Financer 1'],
            'help_text' => ['en-GB' => 'Help text'],
            'type' => QuestionTypeEnum::TEXT,
            'theme_id' => $theme1->id,
            'financer_id' => $this->financer->id,
        ]);

        Question::factory()->create([
            'text' => ['en-GB' => 'Question for Financer 2'],
            'help_text' => ['en-GB' => 'Help text'],
            'type' => QuestionTypeEnum::TEXT,
            'theme_id' => $theme2->id,
            'financer_id' => $financer2->id,
        ]);

        Question::factory()->create([
            'text' => ['en-GB' => 'Question for Financer 2'],
            'help_text' => ['en-GB' => 'Help text'],
            'type' => QuestionTypeEnum::TEXT,
            'theme_id' => $theme3->id,
            'financer_id' => $financer3->id,
        ]);

        // System question (no financer)
        Question::factory()->create([
            'text' => ['en-GB' => 'System Question'],
            'help_text' => ['en-GB' => 'Help text'],
            'type' => QuestionTypeEnum::TEXT,
            'theme_id' => $theme1->id,
            'financer_id' => null,
        ]);

        // Act
        $financer1Questions = Question::query()->forFinancer($this->financer->id)->get();

        // Assert
        $this->assertCount(1, $financer1Questions);
        $this->assertTrue($financer1Questions->contains('financer_id', $this->financer->id));
    }

    #[Test]
    public function it_uses_soft_deletes(): void
    {
        // Arrange
        $theme = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);

        $question = Question::factory()->create([
            'text' => ['en-GB' => 'Question to delete'],
            'help_text' => ['en-GB' => 'Help text'],
            'type' => QuestionTypeEnum::TEXT,
            'theme_id' => $theme->id,
            'financer_id' => $this->financer->id,
        ]);

        // Act
        $question->delete();

        // Assert
        $this->assertSoftDeleted('int_survey_questions', ['id' => $question->id]);
        $this->assertNull(Question::find($question->id));
        $this->assertNotNull(Question::withTrashed()->find($question->id));
    }

    #[Test]
    public function it_has_auditable_trait(): void
    {
        // Arrange
        $theme = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);

        // Act
        $question = Question::factory()->create([
            'text' => ['en-GB' => 'Auditable Question'],
            'help_text' => ['en-GB' => 'Help text'],
            'type' => QuestionTypeEnum::TEXT,
            'theme_id' => $theme->id,
            'financer_id' => $this->financer->id,
        ]);

        // Assert
        $this->assertTrue(method_exists($question, 'audits'));
        $this->assertTrue(method_exists($question, 'getAuditEvents'));
    }

    #[Test]
    public function it_can_duplicate_a_question_without_options(): void
    {
        // Arrange
        $theme = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);

        $originalQuestion = Question::factory()->create([
            'text' => ['en-GB' => 'Original Question'],
            'help_text' => ['en-GB' => 'Original Help Text'],
            'type' => QuestionTypeEnum::TEXT,
            'theme_id' => $theme->id,
            'financer_id' => $this->financer->id,
            'metadata' => ['category' => 'test', 'difficulty' => 'easy'],
            'is_default' => false,
        ]);

        $newFinancer = ModelFactory::createFinancer();

        // Act
        $duplicatedQuestion = $originalQuestion->duplicate($newFinancer->id);

        // Assert
        $this->assertInstanceOf(Question::class, $duplicatedQuestion);
        $this->assertNotEquals($originalQuestion->id, $duplicatedQuestion->id);
        $this->assertEquals($newFinancer->id, $duplicatedQuestion->financer_id);
        $this->assertEquals($originalQuestion->id, $duplicatedQuestion->original_question_id);

        // Verify content is duplicated
        $this->assertEquals($originalQuestion->text, $duplicatedQuestion->text);
        $this->assertEquals($originalQuestion->help_text, $duplicatedQuestion->help_text);
        $this->assertEquals($originalQuestion->type, $duplicatedQuestion->type);
        $this->assertEquals($originalQuestion->theme_id, $duplicatedQuestion->theme_id);
        $this->assertEquals($originalQuestion->metadata, $duplicatedQuestion->metadata);
        $this->assertEquals($originalQuestion->is_default, $duplicatedQuestion->is_default);

        // Verify database records
        $this->assertDatabaseHas('int_survey_questions', [
            'id' => $duplicatedQuestion->id,
            'financer_id' => $newFinancer->id,
            'original_question_id' => $originalQuestion->id,
        ]);
    }

    #[Test]
    public function it_can_duplicate_a_question_with_options(): void
    {
        // Arrange
        $theme = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);

        $originalQuestion = Question::factory()->create([
            'text' => ['en-GB' => 'Multiple Choice Question'],
            'help_text' => ['en-GB' => 'Help Text'],
            'type' => QuestionTypeEnum::MULTIPLE_CHOICE,
            'theme_id' => $theme->id,
            'financer_id' => $this->financer->id,
        ]);

        // Create options for the original question
        $option1 = $originalQuestion->options()->create([
            'text' => ['en-GB' => 'Option 1'],
            'position' => 1,
        ]);

        $option2 = $originalQuestion->options()->create([
            'text' => ['en-GB' => 'Option 2'],
            'position' => 2,
        ]);

        $newFinancer = ModelFactory::createFinancer();

        // Act
        $duplicatedQuestion = $originalQuestion->duplicate($newFinancer->id);

        // Assert
        $this->assertInstanceOf(Question::class, $duplicatedQuestion);
        $this->assertNotEquals($originalQuestion->id, $duplicatedQuestion->id);
        $this->assertEquals($originalQuestion->id, $duplicatedQuestion->original_question_id);

        // Verify options are duplicated
        $duplicatedOptions = $duplicatedQuestion->options;
        $this->assertCount(2, $duplicatedOptions);

        // Verify option content is duplicated but IDs are different
        $duplicatedOption1 = $duplicatedOptions->where('original_question_option_id', $option1->id)->first();
        $duplicatedOption2 = $duplicatedOptions->where('original_question_option_id', $option2->id)->first();

        $this->assertNotNull($duplicatedOption1);
        $this->assertNotNull($duplicatedOption2);
        $this->assertNotEquals($option1->id, $duplicatedOption1->id);
        $this->assertNotEquals($option2->id, $duplicatedOption2->id);
        $this->assertEquals($duplicatedQuestion->id, $duplicatedOption1->question_id);
        $this->assertEquals($duplicatedQuestion->id, $duplicatedOption2->question_id);
        $this->assertEquals($option1->text, $duplicatedOption1->text);
        $this->assertEquals($option2->text, $duplicatedOption2->text);
        $this->assertEquals($option1->position, $duplicatedOption1->position);
        $this->assertEquals($option2->position, $duplicatedOption2->position);
    }

    #[Test]
    public function it_can_duplicate_a_question_with_different_financer(): void
    {
        // Arrange
        $financer1 = ModelFactory::createFinancer();
        $financer2 = ModelFactory::createFinancer();
        $theme = resolve(ThemeFactory::class)->create(['financer_id' => $financer1->id]);

        $originalQuestion = Question::factory()->create([
            'text' => ['en-GB' => 'Question for Financer 1'],
            'help_text' => ['en-GB' => 'Help Text'],
            'type' => QuestionTypeEnum::TEXT,
            'theme_id' => $theme->id,
            'financer_id' => $financer1->id,
        ]);

        // Act
        $duplicatedQuestion = $originalQuestion->duplicate($financer2->id);

        // Assert
        $this->assertEquals($financer2->id, $duplicatedQuestion->financer_id);
        $this->assertNotEquals($originalQuestion->financer_id, $duplicatedQuestion->financer_id);
        $this->assertEquals($originalQuestion->id, $duplicatedQuestion->original_question_id);

        // Verify both questions exist in database
        $this->assertDatabaseHas('int_survey_questions', [
            'id' => $originalQuestion->id,
            'financer_id' => $financer1->id,
            'original_question_id' => null,
        ]);

        $this->assertDatabaseHas('int_survey_questions', [
            'id' => $duplicatedQuestion->id,
            'financer_id' => $financer2->id,
            'original_question_id' => $originalQuestion->id,
        ]);
    }

    #[Test]
    public function it_can_duplicate_a_question_with_archived_status(): void
    {
        // Arrange
        $newFinancer = ModelFactory::createFinancer();
        $theme = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);

        $originalQuestion = Question::factory()->create([
            'text' => ['en-GB' => 'Archived Question'],
            'help_text' => ['en-GB' => 'Help Text'],
            'type' => QuestionTypeEnum::TEXT,
            'theme_id' => $theme->id,
            'financer_id' => $this->financer->id,
            'archived_at' => now(),
        ]);

        // Act
        $duplicatedQuestion = $originalQuestion->duplicate($newFinancer->id);

        // Assert
        $this->assertNotNull($originalQuestion->archived_at);
        $this->assertNull($duplicatedQuestion->archived_at); // Duplicated question should not be archived
        $this->assertEquals($originalQuestion->id, $duplicatedQuestion->original_question_id);
    }

    #[Test]
    public function it_can_duplicate_a_question_with_soft_deleted_status(): void
    {
        // Arrange
        $newFinancer = ModelFactory::createFinancer();
        $theme = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);

        $originalQuestion = Question::factory()->create([
            'text' => ['en-GB' => 'Soft Deleted Question'],
            'help_text' => ['en-GB' => 'Help Text'],
            'type' => QuestionTypeEnum::TEXT,
            'theme_id' => $theme->id,
            'financer_id' => $this->financer->id,
        ]);

        // Soft delete the original question
        $originalQuestion->delete();

        // Act
        $duplicatedQuestion = $originalQuestion->duplicate($newFinancer->id);

        // Assert
        $this->assertSoftDeleted('int_survey_questions', ['id' => $originalQuestion->id]);
        $this->assertNull($duplicatedQuestion->deleted_at); // Duplicated question should not be soft deleted
        $this->assertEquals($originalQuestion->id, $duplicatedQuestion->original_question_id);
    }

    // ==================== HasCreator Trait Tests ====================

    #[Test]
    public function it_automatically_sets_created_by_when_authenticated(): void
    {
        // Arrange
        $user = ModelFactory::createUser();
        $theme = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);

        // Act
        Auth::login($user);
        $question = Question::factory()->create([
            'text' => ['en-GB' => 'Question with creator'],
            'help_text' => ['en-GB' => 'Help Text'],
            'type' => QuestionTypeEnum::TEXT,
            'theme_id' => $theme->id,
            'financer_id' => $this->financer->id,
        ]);

        // Assert
        $this->assertEquals($user->id, $question->created_by);
        $this->assertDatabaseHas('int_survey_questions', [
            'id' => $question->id,
            'created_by' => $user->id,
        ]);
    }

    #[Test]
    public function it_does_not_set_created_by_when_not_authenticated(): void
    {
        // Arrange
        $theme = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);

        // Act
        Auth::logout();
        $question = Question::factory()->create([
            'text' => ['en-GB' => 'Question without creator'],
            'help_text' => ['en-GB' => 'Help Text'],
            'type' => QuestionTypeEnum::TEXT,
            'theme_id' => $theme->id,
            'financer_id' => $this->financer->id,
        ]);

        // Assert
        $this->assertNull($question->created_by);
        $this->assertDatabaseHas('int_survey_questions', [
            'id' => $question->id,
            'created_by' => null,
        ]);
    }

    #[Test]
    public function it_sets_updated_by_when_updating(): void
    {
        // Arrange
        $creator = ModelFactory::createUser();
        $updater = ModelFactory::createUser();
        $theme = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);

        Auth::login($creator);
        $question = Question::factory()->create([
            'text' => ['en-GB' => 'Question to update'],
            'help_text' => ['en-GB' => 'Help Text'],
            'type' => QuestionTypeEnum::TEXT,
            'theme_id' => $theme->id,
            'financer_id' => $this->financer->id,
        ]);

        // Act
        Auth::login($updater);
        $question->update([
            'text' => ['en-GB' => 'Updated Question Text'],
        ]);

        // Assert
        $this->assertEquals($creator->id, $question->created_by);
        $this->assertEquals($updater->id, $question->updated_by);
        $this->assertDatabaseHas('int_survey_questions', [
            'id' => $question->id,
            'created_by' => $creator->id,
            'updated_by' => $updater->id,
        ]);
    }

    #[Test]
    public function it_has_creator_relationship(): void
    {
        // Arrange
        $creator = ModelFactory::createUser();
        $theme = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);

        Auth::login($creator);
        $question = Question::factory()->create([
            'text' => ['en-GB' => 'Question with creator relationship'],
            'help_text' => ['en-GB' => 'Help Text'],
            'type' => QuestionTypeEnum::TEXT,
            'theme_id' => $theme->id,
            'financer_id' => $this->financer->id,
        ]);

        // Act & Assert
        $this->assertInstanceOf(User::class, $question->creator);
        $this->assertEquals($creator->id, $question->creator->id);
        $this->assertEquals($creator->name, $question->creator->name);
    }

    #[Test]
    public function it_has_updater_relationship(): void
    {
        // Arrange
        $creator = ModelFactory::createUser();
        $updater = ModelFactory::createUser();
        $theme = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);

        Auth::login($creator);
        $question = Question::factory()->create([
            'text' => ['en-GB' => 'Question with updater relationship'],
            'help_text' => ['en-GB' => 'Help Text'],
            'type' => QuestionTypeEnum::TEXT,
            'theme_id' => $theme->id,
            'financer_id' => $this->financer->id,
        ]);

        // Act
        Auth::login($updater);
        $question->update([
            'text' => ['en-GB' => 'Updated Question'],
        ]);

        // Assert
        $this->assertInstanceOf(User::class, $question->updater);
        $this->assertEquals($updater->id, $question->updater->id);
        $this->assertEquals($updater->name, $question->updater->name);
    }

    #[Test]
    public function it_can_check_if_was_created_by_user(): void
    {
        // Arrange
        $creator = ModelFactory::createUser();
        $otherUser = ModelFactory::createUser();
        $theme = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);

        Auth::login($creator);
        $question = Question::factory()->create([
            'text' => ['en-GB' => 'Question to check creator'],
            'help_text' => ['en-GB' => 'Help Text'],
            'type' => QuestionTypeEnum::TEXT,
            'theme_id' => $theme->id,
            'financer_id' => $this->financer->id,
        ]);

        // Act & Assert
        $this->assertTrue($question->wasCreatedBy($creator));
        $this->assertFalse($question->wasCreatedBy($otherUser));
        $this->assertFalse($question->wasCreatedBy(null));
    }

    #[Test]
    public function it_can_check_if_was_updated_by_user(): void
    {
        // Arrange
        $creator = ModelFactory::createUser();
        $updater = ModelFactory::createUser();
        $otherUser = ModelFactory::createUser();
        $theme = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);

        Auth::login($creator);
        $question = Question::factory()->create([
            'text' => ['en-GB' => 'Question to check updater'],
            'help_text' => ['en-GB' => 'Help Text'],
            'type' => QuestionTypeEnum::TEXT,
            'theme_id' => $theme->id,
            'financer_id' => $this->financer->id,
        ]);

        // Act
        Auth::login($updater);
        $question->update([
            'text' => ['en-GB' => 'Updated Question'],
        ]);

        // Assert
        $this->assertTrue($question->wasUpdatedBy($updater));
        $this->assertFalse($question->wasUpdatedBy($creator));
        $this->assertFalse($question->wasUpdatedBy($otherUser));
        $this->assertFalse($question->wasUpdatedBy(null));
    }
}
