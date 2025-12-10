<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Survey\Models;

use App\Integrations\Survey\Database\factories\QuestionFactory;
use App\Integrations\Survey\Models\Question;
use App\Integrations\Survey\Models\QuestionOption;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('survey')]
#[Group('question')]
class QuestionOptionTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_uses_uuid_as_primary_key(): void
    {
        $questionOption = new QuestionOption;

        $this->assertTrue($questionOption->getIncrementing() === false);
        $this->assertEquals('string', $questionOption->getKeyType());
    }

    #[Test]
    public function it_can_create_a_question_option(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();
        $question = resolve(QuestionFactory::class)->create(['financer_id' => $financer->id]);

        // Act
        $questionOption = QuestionOption::factory()->create([
            'text' => [
                'en-GB' => 'Test Option',
                'fr-FR' => 'Option de Test',
                'nl-BE' => 'Test Optie',
            ],
            'position' => 1,
            'question_id' => $question->id,
        ]);

        // Assert
        $this->assertInstanceOf(QuestionOption::class, $questionOption);
        $this->assertDatabaseHas('int_survey_question_options', [
            'id' => $questionOption->id,
            'question_id' => $question->id,
            'position' => 1,
        ]);

        // Test translations
        $this->assertEquals('Test Option', $questionOption->getTranslation('text', 'en-GB'));
        $this->assertEquals('Option de Test', $questionOption->getTranslation('text', 'fr-FR'));
        $this->assertEquals('Test Optie', $questionOption->getTranslation('text', 'nl-BE'));
    }

    #[Test]
    public function it_can_create_multiple_question_options_with_different_orders(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();
        $question = resolve(QuestionFactory::class)->create(['financer_id' => $financer->id]);

        // Act
        $option1 = QuestionOption::factory()->create([
            'text' => ['en-GB' => 'First Option'],
            'position' => 1,
            'question_id' => $question->id,
        ]);

        $option2 = QuestionOption::factory()->create([
            'text' => ['en-GB' => 'Second Option'],
            'position' => 2,
            'question_id' => $question->id,
        ]);

        $option3 = QuestionOption::factory()->create([
            'text' => ['en-GB' => 'Third Option'],
            'position' => 3,
            'question_id' => $question->id,
        ]);

        // Assert
        $this->assertEquals(1, $option1->position);
        $this->assertEquals(2, $option2->position);
        $this->assertEquals(3, $option3->position);

        $this->assertDatabaseHas('int_survey_question_options', [
            'id' => $option1->id,
            'position' => 1,
        ]);
        $this->assertDatabaseHas('int_survey_question_options', [
            'id' => $option2->id,
            'position' => 2,
        ]);
        $this->assertDatabaseHas('int_survey_question_options', [
            'id' => $option3->id,
            'position' => 3,
        ]);
    }

    #[Test]
    public function it_belongs_to_a_question(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();
        $question = resolve(QuestionFactory::class)->create(['financer_id' => $financer->id]);
        $questionOption = QuestionOption::factory()->create([
            'text' => ['en-GB' => 'Option with question'],
            'question_id' => $question->id,
        ]);

        Context::add('accessible_financers', [$financer->id]);
        Context::add('financer_id', $financer->id);

        // Act & Assert
        $this->assertInstanceOf(Question::class, $questionOption->question);
        $this->assertEquals($question->id, $questionOption->question->id);
    }

    #[Test]
    public function it_can_handle_different_positions(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();
        $question = resolve(QuestionFactory::class)->create(['financer_id' => $financer->id]);

        $orders = [0, 1, 5, 10, 100];

        foreach ($orders as $order) {
            // Act
            $questionOption = QuestionOption::factory()->create([
                'text' => ['en-GB' => "Option with order {$order}"],
                'position' => $order,
                'question_id' => $question->id,
            ]);

            // Assert
            $this->assertEquals($order, $questionOption->position);
            $this->assertDatabaseHas('int_survey_question_options', [
                'id' => $questionOption->id,
                'position' => $order,
            ]);
        }
    }

    #[Test]
    public function it_requires_question_id(): void
    {
        // Act & Assert
        $this->expectException(QueryException::class);

        QuestionOption::factory()->create([
            'text' => ['en-GB' => 'Option without question'],
            'question_id' => null,
        ]);
    }

    #[Test]
    public function it_uses_soft_deletes(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();
        $question = resolve(QuestionFactory::class)->create(['financer_id' => $financer->id]);
        $questionOption = QuestionOption::factory()->create([
            'text' => ['en-GB' => 'Option to delete'],
            'question_id' => $question->id,
        ]);

        // Act
        $questionOption->delete();

        // Assert
        $this->assertSoftDeleted('int_survey_question_options', ['id' => $questionOption->id]);
        $this->assertNull(QuestionOption::find($questionOption->id));
        $this->assertNotNull(QuestionOption::withTrashed()->find($questionOption->id));
    }

    #[Test]
    public function it_can_scope_options_by_question(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();
        $question1 = resolve(QuestionFactory::class)->create(['financer_id' => $financer->id]);
        $question2 = resolve(QuestionFactory::class)->create(['financer_id' => $financer->id]);

        QuestionOption::factory()->create([
            'text' => ['en-GB' => 'Option for Question 1'],
            'question_id' => $question1->id,
        ]);

        QuestionOption::factory()->create([
            'text' => ['en-GB' => 'Option for Question 2'],
            'question_id' => $question2->id,
        ]);

        // Act
        $question1Options = QuestionOption::query()->where('question_id', $question1->id)->get();

        // Assert
        $this->assertCount(1, $question1Options);
        $this->assertEquals($question1->id, $question1Options->first()->question_id);
    }

    #[Test]
    public function it_can_scope_options_by_position(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();
        $question = resolve(QuestionFactory::class)->create(['financer_id' => $financer->id]);

        QuestionOption::factory()->create([
            'text' => ['en-GB' => 'First Option'],
            'position' => 1,
            'question_id' => $question->id,
        ]);

        QuestionOption::factory()->create([
            'text' => ['en-GB' => 'Second Option'],
            'position' => 2,
            'question_id' => $question->id,
        ]);

        QuestionOption::factory()->create([
            'text' => ['en-GB' => 'Third Option'],
            'position' => 3,
            'question_id' => $question->id,
        ]);

        // Act
        $orderedOptions = QuestionOption::query()
            ->where('question_id', $question->id)
            ->orderBy('position')
            ->get();

        // Assert
        $this->assertCount(3, $orderedOptions);
        $this->assertEquals(1, $orderedOptions->first()->position);
        $this->assertEquals(3, $orderedOptions->last()->position);
    }

    #[Test]
    public function it_can_handle_complex_translations(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();
        $question = resolve(QuestionFactory::class)->create(['financer_id' => $financer->id]);
        $complexText = [
            'en-GB' => 'Very long option text with special characters: @#$%^&*()',
            'fr-FR' => 'Texte d\'option très long avec caractères spéciaux: @#$%^&*()',
            'nl-BE' => 'Zeer lange optietekst met speciale tekens: @#$%^&*()',
        ];

        // Act
        $questionOption = QuestionOption::factory()->create([
            'text' => $complexText,
            'question_id' => $question->id,
        ]);

        // Assert
        $this->assertEquals($complexText[app()->getLocale()], $questionOption->text);
        $this->assertEquals('Very long option text with special characters: @#$%^&*()', $questionOption->getTranslation('text', 'en-GB'));
        $this->assertEquals('Texte d\'option très long avec caractères spéciaux: @#$%^&*()', $questionOption->getTranslation('text', 'fr-FR'));
        $this->assertEquals('Zeer lange optietekst met speciale tekens: @#$%^&*()', $questionOption->getTranslation('text', 'nl-BE'));
    }
}
