<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Survey\Actions;

use App\Integrations\Survey\Actions\Survey\ReorderSurveyQuestionAction;
use App\Integrations\Survey\Database\factories\QuestionFactory;
use App\Integrations\Survey\Database\factories\SurveyFactory;
use App\Integrations\Survey\Models\Question;
use App\Integrations\Survey\Models\Survey;
use Database\Factories\FinancerFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('survey')]
#[Group('question')]
class ReorderSurveyQuestionActionTest extends TestCase
{
    use DatabaseTransactions;

    private ReorderSurveyQuestionAction $action;

    private Survey $survey;

    private Question $question1;

    private Question $question2;

    private Question $question3;

    protected function setUp(): void
    {
        parent::setUp();

        $financer = resolve(FinancerFactory::class)->create();
        $this->survey = resolve(SurveyFactory::class)->create(['financer_id' => $financer->id]);

        $this->question1 = resolve(QuestionFactory::class)->create(['financer_id' => $financer->id]);
        $this->question2 = resolve(QuestionFactory::class)->create(['financer_id' => $financer->id]);
        $this->question3 = resolve(QuestionFactory::class)->create(['financer_id' => $financer->id]);

        // Link questions to survey
        $this->survey->questions()->attach([
            $this->question1->id => ['position' => 1],
            $this->question2->id => ['position' => 2],
            $this->question3->id => ['position' => 3],
        ]);

        $this->action = new ReorderSurveyQuestionAction;

        Context::add('accessible_financers', [$financer->id]);
        Context::add('financer_id', $financer->id);
    }

    #[Test]
    public function it_reorders_survey_questions_successfully(): void
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
        $result = $this->action->execute($this->survey, $data);

        // Assert
        $this->assertInstanceOf(Survey::class, $result);

        // Verify the new order in the pivot table
        $this->assertDatabaseHas('int_survey_questionables', [
            'questionable_id' => $this->survey->id,
            'questionable_type' => Survey::class,
            'question_id' => $this->question3->id,
            'position' => 1,
        ]);

        $this->assertDatabaseHas('int_survey_questionables', [
            'questionable_id' => $this->survey->id,
            'questionable_type' => Survey::class,
            'question_id' => $this->question2->id,
            'position' => 2,
        ]);

        $this->assertDatabaseHas('int_survey_questionables', [
            'questionable_id' => $this->survey->id,
            'questionable_type' => Survey::class,
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
        $result = $this->action->execute($this->survey, $data);

        // Assert
        $this->assertInstanceOf(Survey::class, $result);

        // Verify only question1 position was updated
        $this->assertDatabaseHas('int_survey_questionables', [
            'questionable_id' => $this->survey->id,
            'questionable_type' => Survey::class,
            'question_id' => $this->question1->id,
            'position' => 5,
        ]);

        // Other questions should keep their original positions
        $this->assertDatabaseHas('int_survey_questionables', [
            'questionable_id' => $this->survey->id,
            'questionable_type' => Survey::class,
            'question_id' => $this->question2->id,
            'position' => 2,
        ]);

        $this->assertDatabaseHas('int_survey_questionables', [
            'questionable_id' => $this->survey->id,
            'questionable_type' => Survey::class,
            'question_id' => $this->question3->id,
            'position' => 3,
        ]);
    }

    #[Test]
    public function it_returns_same_survey_when_no_questions_provided(): void
    {
        // Arrange
        $data = [
            'questions' => [],
        ];

        // Act
        $result = $this->action->execute($this->survey, $data);

        // Assert
        $this->assertInstanceOf(Survey::class, $result);
        $this->assertEquals($this->survey->id, $result->id);

        // Positions should remain unchanged
        $this->assertDatabaseHas('int_survey_questionables', [
            'questionable_id' => $this->survey->id,
            'questionable_type' => Survey::class,
            'question_id' => $this->question1->id,
            'position' => 1,
        ]);
    }

    #[Test]
    public function it_throws_exception_when_question_not_linked_to_survey(): void
    {
        // Arrange
        $unlinkedQuestion = resolve(QuestionFactory::class)->create(['financer_id' => $this->survey->financer_id]);

        $data = [
            'questions' => [
                ['id' => $this->question1->id, 'position' => 1],
                ['id' => $unlinkedQuestion->id, 'position' => 2],
            ],
        ];

        // Act & Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The following questions are not linked to this survey');

        $this->action->execute($this->survey, $data);
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
        $result = $this->action->execute($this->survey, $data);

        // Assert
        $this->assertInstanceOf(Survey::class, $result);

        $this->assertDatabaseHas('int_survey_questionables', [
            'questionable_id' => $this->survey->id,
            'questionable_type' => Survey::class,
            'question_id' => $this->question1->id,
            'position' => 10,
        ]);

        $this->assertDatabaseHas('int_survey_questionables', [
            'questionable_id' => $this->survey->id,
            'questionable_type' => Survey::class,
            'question_id' => $this->question2->id,
            'position' => 20,
        ]);

        $this->assertDatabaseHas('int_survey_questionables', [
            'questionable_id' => $this->survey->id,
            'questionable_type' => Survey::class,
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
        $this->action->execute($this->survey, $data);

        // Assert - All questions should still be linked
        $this->survey->refresh();
        $this->assertCount(3, $this->survey->questions);
        $this->assertTrue($this->survey->questions->contains($this->question1));
        $this->assertTrue($this->survey->questions->contains($this->question2));
        $this->assertTrue($this->survey->questions->contains($this->question3));
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
        $result = $this->action->execute($this->survey, $data);

        // Assert
        $this->assertInstanceOf(Survey::class, $result);

        // Updated positions
        $this->assertDatabaseHas('int_survey_questionables', [
            'questionable_id' => $this->survey->id,
            'questionable_type' => Survey::class,
            'question_id' => $this->question2->id,
            'position' => 10,
        ]);

        $this->assertDatabaseHas('int_survey_questionables', [
            'questionable_id' => $this->survey->id,
            'questionable_type' => Survey::class,
            'question_id' => $this->question3->id,
            'position' => 5,
        ]);

        // Unchanged position
        $this->assertDatabaseHas('int_survey_questionables', [
            'questionable_id' => $this->survey->id,
            'questionable_type' => Survey::class,
            'question_id' => $this->question1->id,
            'position' => 1,
        ]);
    }

    #[Test]
    public function it_returns_refreshed_survey_instance(): void
    {
        // Arrange
        $data = [
            'questions' => [
                ['id' => $this->question1->id, 'position' => 2],
                ['id' => $this->question2->id, 'position' => 1],
            ],
        ];

        // Act
        $result = $this->action->execute($this->survey, $data);

        // Assert
        $this->assertTrue($result->exists);
        $this->assertEquals($this->survey->id, $result->id);
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
        $result = $this->action->execute($this->survey, $data);

        // Assert - Should not throw exception
        $this->assertInstanceOf(Survey::class, $result);

        $this->assertDatabaseHas('int_survey_questionables', [
            'questionable_id' => $this->survey->id,
            'questionable_type' => Survey::class,
            'question_id' => $this->question1->id,
            'position' => 1,
        ]);

        $this->assertDatabaseHas('int_survey_questionables', [
            'questionable_id' => $this->survey->id,
            'questionable_type' => Survey::class,
            'question_id' => $this->question2->id,
            'position' => 1,
        ]);

        $this->assertDatabaseHas('int_survey_questionables', [
            'questionable_id' => $this->survey->id,
            'questionable_type' => Survey::class,
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
        $result = $this->action->execute($this->survey, $data);

        // Assert
        $this->assertInstanceOf(Survey::class, $result);

        $this->assertDatabaseHas('int_survey_questionables', [
            'questionable_id' => $this->survey->id,
            'questionable_type' => Survey::class,
            'question_id' => $this->question1->id,
            'position' => 1000,
        ]);
    }
}
