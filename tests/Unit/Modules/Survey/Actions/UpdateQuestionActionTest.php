<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Survey\Actions;

use App\Integrations\Survey\Actions\Question\UpdateQuestionAction;
use App\Integrations\Survey\Database\factories\ThemeFactory;
use App\Integrations\Survey\Enums\QuestionTypeEnum;
use App\Integrations\Survey\Models\Question;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('survey')]
#[Group('question')]
class UpdateQuestionActionTest extends TestCase
{
    use DatabaseTransactions;

    private UpdateQuestionAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new UpdateQuestionAction;
    }

    #[Test]
    public function it_creates_a_question_successfully(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();
        $theme = resolve(ThemeFactory::class)->create(['financer_id' => $financer->id]);

        $data = [
            'text' => [
                'en-GB' => 'New Question',
                'fr-FR' => 'Nouvelle Question',
                'nl-BE' => 'Nieuwe Vraag',
            ],
            'help_text' => [
                'en-GB' => 'Help Text',
                'fr-FR' => 'Texte d\'aide',
                'nl-BE' => 'Hulptekst',
            ],
            'type' => QuestionTypeEnum::MULTIPLE_CHOICE,
            'theme_id' => $theme->id,
            'financer_id' => $financer->id,
            'metadata' => ['category' => 'test'],
            'is_default' => false,
        ];

        $question = new Question;

        // Act
        $result = $this->action->execute($question, $data);

        // Assert
        $this->assertInstanceOf(Question::class, $result);
        $this->assertTrue($result->exists);

        // Test text translations
        $this->assertEquals($data['text']['en-GB'], $result->getTranslation('text', 'en-GB'));
        $this->assertEquals($data['text']['fr-FR'], $result->getTranslation('text', 'fr-FR'));
        $this->assertEquals($data['text']['nl-BE'], $result->getTranslation('text', 'nl-BE'));

        // Test help_text translations
        $this->assertEquals($data['help_text']['en-GB'], $result->getTranslation('help_text', 'en-GB'));
        $this->assertEquals($data['help_text']['fr-FR'], $result->getTranslation('help_text', 'fr-FR'));
        $this->assertEquals($data['help_text']['nl-BE'], $result->getTranslation('help_text', 'nl-BE'));

        // Test other fields
        $this->assertEquals($data['type'], $result->type);
        $this->assertEquals($data['theme_id'], $result->theme_id);
        $this->assertEquals($data['financer_id'], $result->financer_id);
        $this->assertEquals($data['metadata'], $result->metadata);
        $this->assertEquals($data['is_default'], $result->is_default);
    }

    #[Test]
    public function it_updates_an_existing_question_successfully(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();
        $theme = resolve(ThemeFactory::class)->create(['financer_id' => $financer->id]);

        $question = Question::factory()->create([
            'text' => ['en-GB' => 'Original Question', 'fr-FR' => 'Question Originale'],
            'help_text' => ['en-GB' => 'Original Help Text'],
            'type' => QuestionTypeEnum::TEXT,
            'theme_id' => $theme->id,
            'financer_id' => $financer->id,
            'metadata' => ['category' => 'original'],
            'is_default' => false,
        ]);

        $updateData = [
            'text' => [
                'en-GB' => 'Updated Question',
                'fr-FR' => 'Question Mise à Jour',
                'nl-BE' => 'Bijgewerkte Vraag',
            ],
            'help_text' => [
                'en-GB' => 'Updated Help Text',
                'fr-FR' => 'Texte d\'aide Mis à Jour',
            ],
            'type' => QuestionTypeEnum::MULTIPLE_CHOICE,
            'metadata' => ['category' => 'updated', 'difficulty' => 'hard'],
        ];

        // Act
        $result = $this->action->execute($question, $updateData);

        // Assert
        $this->assertInstanceOf(Question::class, $result);

        // Test updated translations
        $this->assertEquals($updateData['text']['en-GB'], $result->getTranslation('text', 'en-GB'));
        $this->assertEquals($updateData['text']['fr-FR'], $result->getTranslation('text', 'fr-FR'));
        $this->assertEquals($updateData['text']['nl-BE'], $result->getTranslation('text', 'nl-BE'));

        $this->assertEquals($updateData['help_text']['en-GB'], $result->getTranslation('help_text', 'en-GB'));
        $this->assertEquals($updateData['help_text']['fr-FR'], $result->getTranslation('help_text', 'fr-FR'));

        // Test updated values
        $this->assertEquals($updateData['type'], $result->type);
        $this->assertEquals($updateData['metadata'], $result->metadata);

        // Test unchanged values
        $this->assertEquals($theme->id, $result->theme_id);
        $this->assertEquals($financer->id, $result->financer_id);
        $this->assertFalse($result->is_default);
    }

    #[Test]
    public function it_handles_partial_data_updates(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();
        $theme = resolve(ThemeFactory::class)->create(['financer_id' => $financer->id]);

        $question = Question::factory()->create([
            'text' => ['en-GB' => 'Original Question', 'fr-FR' => 'Question Originale'],
            'help_text' => ['en-GB' => 'Original Help Text'],
            'type' => QuestionTypeEnum::TEXT,
            'theme_id' => $theme->id,
            'financer_id' => $financer->id,
            'metadata' => ['category' => 'original'],
            'is_default' => false,
        ]);

        $updateData = [
            'text' => ['en-GB' => 'Updated Question Only'],
        ];

        // Act
        $result = $this->action->execute($question, $updateData);

        // Assert
        $this->assertInstanceOf(Question::class, $result);

        // Test updated fields
        $this->assertEquals($updateData['text']['en-GB'], $result->getTranslation('text', 'en-GB'));

        // Test that other fields remain unchanged
        $this->assertEquals('Original Help Text', $result->getTranslation('help_text', 'en-GB'));
        $this->assertEquals(QuestionTypeEnum::TEXT, $result->type);
        $this->assertEquals(['category' => 'original'], $result->metadata);
        $this->assertFalse($result->is_default);
    }
}
