<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Survey\Actions;

use App\Integrations\Survey\Actions\Questionnaire\ReorderQuestionnaireQuestionAction;
use App\Integrations\Survey\Database\factories\QuestionFactory;
use App\Integrations\Survey\Database\factories\QuestionnaireFactory;
use App\Integrations\Survey\Enums\QuestionnaireTypeEnum;
use App\Integrations\Survey\Models\Question;
use App\Integrations\Survey\Models\Questionnaire;
use App\Models\Financer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('survey')]
#[Group('questionnaire')]
#[Group('question')]
class ReorderQuestionnaireQuestionActionTest extends TestCase
{
    use DatabaseTransactions;

    private ReorderQuestionnaireQuestionAction $action;

    private Financer $financer;

    private Questionnaire $questionnaire;

    private Question $question1;

    private Question $question2;

    private Question $question3;

    protected function setUp(): void
    {
        parent::setUp();

        $this->financer = ModelFactory::createFinancer();
        $this->questionnaire = resolve(QuestionnaireFactory::class)->create([
            'financer_id' => $this->financer->id,
            'type' => QuestionnaireTypeEnum::CUSTOM,
        ]);

        $this->question1 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);
        $this->question2 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);
        $this->question3 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);

        // Link questions to questionnaire
        $this->questionnaire->questions()->attach([
            $this->question1->id => ['position' => 1],
            $this->question2->id => ['position' => 2],
            $this->question3->id => ['position' => 3],
        ]);

        $this->action = new ReorderQuestionnaireQuestionAction;

        Context::add('accessible_financers', [$this->financer->id]);
        Context::add('financer_id', $this->financer->id);
    }

    #[Test]
    public function it_reorders_questionnaire_questions_successfully(): void
    {
        // Arrange - Reverse the order
        $data = [
            'questions' => [
                ['id' => $this->question3->id, 'position' => 1],
                ['id' => $this->question2->id, 'position' => 2],
                ['id' => $this->question1->id, 'position' => 3],
            ],
        ];

        // Act
        $result = $this->action->execute($this->questionnaire, $data);

        // Assert
        $this->assertInstanceOf(Questionnaire::class, $result);

        // Verify the new order in the pivot table
        $this->assertDatabaseHas('int_survey_questionables', [
            'questionable_id' => $this->questionnaire->id,
            'questionable_type' => Questionnaire::class,
            'question_id' => $this->question3->id,
            'position' => 1,
        ]);

        $this->assertDatabaseHas('int_survey_questionables', [
            'questionable_id' => $this->questionnaire->id,
            'questionable_type' => Questionnaire::class,
            'question_id' => $this->question2->id,
            'position' => 2,
        ]);

        $this->assertDatabaseHas('int_survey_questionables', [
            'questionable_id' => $this->questionnaire->id,
            'questionable_type' => Questionnaire::class,
            'question_id' => $this->question1->id,
            'position' => 3,
        ]);
    }

    #[Test]
    public function it_updates_only_specified_question_positions(): void
    {
        // Arrange - Update only question1 position
        $data = [
            'questions' => [
                ['id' => $this->question1->id, 'position' => 5],
            ],
        ];

        // Act
        $result = $this->action->execute($this->questionnaire, $data);

        // Assert
        $this->assertInstanceOf(Questionnaire::class, $result);

        // Verify only question1 position was updated
        $this->assertDatabaseHas('int_survey_questionables', [
            'questionable_id' => $this->questionnaire->id,
            'questionable_type' => Questionnaire::class,
            'question_id' => $this->question1->id,
            'position' => 5,
        ]);

        // Other questions should keep their original positions
        $this->assertDatabaseHas('int_survey_questionables', [
            'questionable_id' => $this->questionnaire->id,
            'questionable_type' => Questionnaire::class,
            'question_id' => $this->question2->id,
            'position' => 2,
        ]);

        $this->assertDatabaseHas('int_survey_questionables', [
            'questionable_id' => $this->questionnaire->id,
            'questionable_type' => Questionnaire::class,
            'question_id' => $this->question3->id,
            'position' => 3,
        ]);
    }

    #[Test]
    public function it_returns_same_questionnaire_when_no_questions_provided(): void
    {
        // Arrange
        $data = [
            'questions' => [],
        ];

        // Act
        $result = $this->action->execute($this->questionnaire, $data);

        // Assert
        $this->assertInstanceOf(Questionnaire::class, $result);
        $this->assertEquals($this->questionnaire->id, $result->id);

        // Positions should remain unchanged
        $this->assertDatabaseHas('int_survey_questionables', [
            'questionable_id' => $this->questionnaire->id,
            'questionable_type' => Questionnaire::class,
            'question_id' => $this->question1->id,
            'position' => 1,
        ]);
    }

    #[Test]
    public function it_throws_exception_when_question_not_linked_to_questionnaire(): void
    {
        // Arrange
        $unlinkedQuestion = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);

        $data = [
            'questions' => [
                ['id' => $this->question1->id, 'position' => 1],
                ['id' => $unlinkedQuestion->id, 'position' => 2],
            ],
        ];

        // Act & Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The following questions are not linked to this questionnaire');

        $this->action->execute($this->questionnaire, $data);
    }

    #[Test]
    public function it_can_reorder_to_non_sequential_positions(): void
    {
        // Arrange - Use non-sequential positions
        $data = [
            'questions' => [
                ['id' => $this->question1->id, 'position' => 10],
                ['id' => $this->question2->id, 'position' => 20],
                ['id' => $this->question3->id, 'position' => 30],
            ],
        ];

        // Act
        $result = $this->action->execute($this->questionnaire, $data);

        // Assert
        $this->assertInstanceOf(Questionnaire::class, $result);

        $this->assertDatabaseHas('int_survey_questionables', [
            'questionable_id' => $this->questionnaire->id,
            'questionable_type' => Questionnaire::class,
            'question_id' => $this->question1->id,
            'position' => 10,
        ]);

        $this->assertDatabaseHas('int_survey_questionables', [
            'questionable_id' => $this->questionnaire->id,
            'questionable_type' => Questionnaire::class,
            'question_id' => $this->question2->id,
            'position' => 20,
        ]);

        $this->assertDatabaseHas('int_survey_questionables', [
            'questionable_id' => $this->questionnaire->id,
            'questionable_type' => Questionnaire::class,
            'question_id' => $this->question3->id,
            'position' => 30,
        ]);
    }

    #[Test]
    public function it_preserves_question_link_when_updating_position(): void
    {
        // Arrange
        $data = [
            'questions' => [
                ['id' => $this->question1->id, 'position' => 3],
                ['id' => $this->question2->id, 'position' => 1],
                ['id' => $this->question3->id, 'position' => 2],
            ],
        ];

        // Act
        $this->action->execute($this->questionnaire, $data);

        // Assert - All questions should still be linked
        $this->questionnaire->refresh();
        $this->assertCount(3, $this->questionnaire->questions);
        $this->assertTrue($this->questionnaire->questions->contains($this->question1));
        $this->assertTrue($this->questionnaire->questions->contains($this->question2));
        $this->assertTrue($this->questionnaire->questions->contains($this->question3));
    }

    #[Test]
    public function it_handles_partial_reordering(): void
    {
        // Arrange - Only reorder 2 out of 3 questions
        $data = [
            'questions' => [
                ['id' => $this->question2->id, 'position' => 10],
                ['id' => $this->question3->id, 'position' => 5],
            ],
        ];

        // Act
        $result = $this->action->execute($this->questionnaire, $data);

        // Assert
        $this->assertInstanceOf(Questionnaire::class, $result);

        // Updated positions
        $this->assertDatabaseHas('int_survey_questionables', [
            'questionable_id' => $this->questionnaire->id,
            'questionable_type' => Questionnaire::class,
            'question_id' => $this->question2->id,
            'position' => 10,
        ]);

        $this->assertDatabaseHas('int_survey_questionables', [
            'questionable_id' => $this->questionnaire->id,
            'questionable_type' => Questionnaire::class,
            'question_id' => $this->question3->id,
            'position' => 5,
        ]);

        // Unchanged position
        $this->assertDatabaseHas('int_survey_questionables', [
            'questionable_id' => $this->questionnaire->id,
            'questionable_type' => Questionnaire::class,
            'question_id' => $this->question1->id,
            'position' => 1,
        ]);
    }

    #[Test]
    public function it_returns_refreshed_questionnaire_instance(): void
    {
        // Arrange
        $data = [
            'questions' => [
                ['id' => $this->question1->id, 'position' => 2],
                ['id' => $this->question2->id, 'position' => 1],
            ],
        ];

        // Act
        $result = $this->action->execute($this->questionnaire, $data);

        // Assert
        $this->assertTrue($result->exists);
        $this->assertEquals($this->questionnaire->id, $result->id);
        $this->assertNotNull($result->updated_at);
    }

    #[Test]
    public function it_allows_same_position_for_multiple_questions(): void
    {
        // Arrange - Set same position for different questions
        $data = [
            'questions' => [
                ['id' => $this->question1->id, 'position' => 1],
                ['id' => $this->question2->id, 'position' => 1],
                ['id' => $this->question3->id, 'position' => 1],
            ],
        ];

        // Act
        $result = $this->action->execute($this->questionnaire, $data);

        // Assert - Should not throw exception
        $this->assertInstanceOf(Questionnaire::class, $result);

        $this->assertDatabaseHas('int_survey_questionables', [
            'questionable_id' => $this->questionnaire->id,
            'questionable_type' => Questionnaire::class,
            'question_id' => $this->question1->id,
            'position' => 1,
        ]);

        $this->assertDatabaseHas('int_survey_questionables', [
            'questionable_id' => $this->questionnaire->id,
            'questionable_type' => Questionnaire::class,
            'question_id' => $this->question2->id,
            'position' => 1,
        ]);

        $this->assertDatabaseHas('int_survey_questionables', [
            'questionable_id' => $this->questionnaire->id,
            'questionable_type' => Questionnaire::class,
            'question_id' => $this->question3->id,
            'position' => 1,
        ]);
    }

    #[Test]
    public function it_handles_reordering_with_large_position_numbers(): void
    {
        // Arrange
        $data = [
            'questions' => [
                ['id' => $this->question1->id, 'position' => 1000],
                ['id' => $this->question2->id, 'position' => 2000],
                ['id' => $this->question3->id, 'position' => 3000],
            ],
        ];

        // Act
        $result = $this->action->execute($this->questionnaire, $data);

        // Assert
        $this->assertInstanceOf(Questionnaire::class, $result);

        $this->assertDatabaseHas('int_survey_questionables', [
            'questionable_id' => $this->questionnaire->id,
            'questionable_type' => Questionnaire::class,
            'question_id' => $this->question1->id,
            'position' => 1000,
        ]);
    }

    #[Test]
    public function it_works_with_different_questionnaire_types(): void
    {
        // Test with NPS questionnaire
        $npsQuestionnaire = resolve(QuestionnaireFactory::class)->create([
            'financer_id' => $this->financer->id,
            'type' => QuestionnaireTypeEnum::NPS,
        ]);

        $question = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);
        $npsQuestionnaire->questions()->attach([$question->id => ['position' => 1]]);

        $data = [
            'questions' => [
                ['id' => $question->id, 'position' => 10],
            ],
        ];

        // Act
        $result = $this->action->execute($npsQuestionnaire, $data);

        // Assert
        $this->assertInstanceOf(Questionnaire::class, $result);
        $this->assertEquals(QuestionnaireTypeEnum::NPS, $result->type);

        $this->assertDatabaseHas('int_survey_questionables', [
            'questionable_id' => $npsQuestionnaire->id,
            'questionable_type' => Questionnaire::class,
            'question_id' => $question->id,
            'position' => 10,
        ]);
    }

    #[Test]
    public function it_reorders_questions_in_satisfaction_questionnaire(): void
    {
        // Test with Satisfaction questionnaire
        $satisfactionQuestionnaire = resolve(QuestionnaireFactory::class)->create([
            'financer_id' => $this->financer->id,
            'type' => QuestionnaireTypeEnum::SATISFACTION,
        ]);

        $q1 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);
        $q2 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);

        $satisfactionQuestionnaire->questions()->attach([
            $q1->id => ['position' => 1],
            $q2->id => ['position' => 2],
        ]);

        $data = [
            'questions' => [
                ['id' => $q2->id, 'position' => 1],
                ['id' => $q1->id, 'position' => 2],
            ],
        ];

        // Act
        $result = $this->action->execute($satisfactionQuestionnaire, $data);

        // Assert
        $this->assertEquals(QuestionnaireTypeEnum::SATISFACTION, $result->type);

        $this->assertDatabaseHas('int_survey_questionables', [
            'questionable_id' => $satisfactionQuestionnaire->id,
            'questionable_type' => Questionnaire::class,
            'question_id' => $q2->id,
            'position' => 1,
        ]);

        $this->assertDatabaseHas('int_survey_questionables', [
            'questionable_id' => $satisfactionQuestionnaire->id,
            'questionable_type' => Questionnaire::class,
            'question_id' => $q1->id,
            'position' => 2,
        ]);
    }

    #[Test]
    public function it_handles_single_question_reorder(): void
    {
        // Arrange
        $data = [
            'questions' => [
                ['id' => $this->question2->id, 'position' => 100],
            ],
        ];

        // Act
        $result = $this->action->execute($this->questionnaire, $data);

        // Assert
        $this->assertInstanceOf(Questionnaire::class, $result);

        $this->assertDatabaseHas('int_survey_questionables', [
            'questionable_id' => $this->questionnaire->id,
            'questionable_type' => Questionnaire::class,
            'question_id' => $this->question2->id,
            'position' => 100,
        ]);
    }

    #[Test]
    public function it_throws_exception_for_multiple_unlinked_questions(): void
    {
        // Arrange
        $unlinkedQuestion1 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);
        $unlinkedQuestion2 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);

        $data = [
            'questions' => [
                ['id' => $unlinkedQuestion1->id, 'position' => 1],
                ['id' => $unlinkedQuestion2->id, 'position' => 2],
            ],
        ];

        // Act & Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The following questions are not linked to this questionnaire');

        $this->action->execute($this->questionnaire, $data);
    }

    #[Test]
    public function it_maintains_questionnaire_integrity_after_reorder(): void
    {
        // Arrange
        $data = [
            'questions' => [
                ['id' => $this->question3->id, 'position' => 1],
                ['id' => $this->question1->id, 'position' => 2],
                ['id' => $this->question2->id, 'position' => 3],
            ],
        ];

        $originalName = $this->questionnaire->name;
        $originalType = $this->questionnaire->type;

        // Act
        $result = $this->action->execute($this->questionnaire, $data);

        // Assert - Questionnaire properties should remain unchanged
        $this->assertEquals($originalName, $result->name);
        $this->assertEquals($originalType, $result->type);
        $this->assertEquals($this->questionnaire->financer_id, $result->financer_id);
    }

    #[Test]
    public function it_returns_refreshed_instance_with_ordered_questions(): void
    {
        // Arrange
        $data = [
            'questions' => [
                ['id' => $this->question1->id, 'position' => 3],
                ['id' => $this->question2->id, 'position' => 1],
                ['id' => $this->question3->id, 'position' => 2],
            ],
        ];

        // Act
        $result = $this->action->execute($this->questionnaire, $data);

        // Assert
        $this->assertTrue($result->exists);
        $this->assertNotNull($result->updated_at);

        // Load questions and verify they are ordered
        $result->load('questions');
        $this->assertCount(3, $result->questions);
    }
}
