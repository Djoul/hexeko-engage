<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Survey\Actions;

use App\Integrations\Survey\Actions\Survey\UnlinkSurveyQuestionAction;
use App\Integrations\Survey\Database\factories\QuestionFactory;
use App\Integrations\Survey\Database\factories\SurveyFactory;
use App\Integrations\Survey\Models\Survey;
use App\Models\Financer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('survey')]
class UnlinkSurveyQuestionActionTest extends TestCase
{
    use DatabaseTransactions;

    private UnlinkSurveyQuestionAction $action;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new UnlinkSurveyQuestionAction;
        $this->financer = ModelFactory::createFinancer();

        Context::add('accessible_financers', [$this->financer->id]);
        Context::add('financer_id', $this->financer->id);
    }

    #[Test]
    public function it_unlinks_questions_with_simple_ids_array(): void
    {
        // Arrange
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);

        $question1 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);
        $question2 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);
        $question3 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);

        // First attach all questions
        $survey->questions()->attach([$question1->id, $question2->id, $question3->id]);

        $data = [$question1->id, $question2->id];

        // Act
        $result = $this->action->execute($survey, ['questions' => $data]);

        // Assert
        $this->assertInstanceOf(Survey::class, $result);
        $this->assertEquals($survey->id, $result->id);

        // Verify questions are unlinked
        $this->assertCount(1, $result->questions);
        $this->assertFalse($result->questions->contains('id', $question1->id));
        $this->assertFalse($result->questions->contains('id', $question2->id));
        $this->assertTrue($result->questions->contains('id', $question3->id));
    }

    #[Test]
    public function it_unlinks_questions_with_position_format(): void
    {
        // Arrange
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);

        $question1 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);
        $question2 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);
        $question3 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);

        // First attach all questions with display order
        $survey->questions()->attach([
            $question1->id => ['position' => 10],
            $question2->id => ['position' => 20],
            $question3->id => ['position' => 30],
        ]);

        $data = [$question1->id, $question2->id];

        // Act
        $result = $this->action->execute($survey, ['questions' => $data]);

        // Assert
        $this->assertInstanceOf(Survey::class, $result);
        $this->assertEquals($survey->id, $result->id);

        // Verify questions are unlinked
        $this->assertCount(1, $result->questions);
        $this->assertFalse($result->questions->contains('id', $question1->id));
        $this->assertFalse($result->questions->contains('id', $question2->id));
        $this->assertTrue($result->questions->contains('id', $question3->id));
    }

    #[Test]
    public function it_handles_empty_data_array(): void
    {
        // Arrange
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);

        $question1 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);

        // First attach a question
        $survey->questions()->attach([$question1->id]);

        $data = [];

        // Act
        $result = $this->action->execute($survey, $data);

        // Assert
        $this->assertInstanceOf(Survey::class, $result);
        $this->assertEquals($survey->id, $result->id);

        // Verify no questions are unlinked (question still attached)
        $this->assertCount(1, $result->questions);
        $this->assertTrue($result->questions->contains('id', $question1->id));
    }

    #[Test]
    public function it_unlinks_already_attached_questions(): void
    {
        // Arrange
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);

        $question1 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);
        $question2 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);

        // First attach questions
        $survey->questions()->attach([$question1->id, $question2->id]);

        // Verify they are attached
        $this->assertCount(2, $survey->questions);

        $data = [$question1->id];

        // Act
        $result = $this->action->execute($survey, ['questions' => $data]);

        // Assert
        $this->assertInstanceOf(Survey::class, $result);
        $this->assertEquals($survey->id, $result->id);

        // Verify only one question is unlinked
        $this->assertCount(1, $result->questions);
        $this->assertFalse($result->questions->contains('id', $question1->id));
        $this->assertTrue($result->questions->contains('id', $question2->id));
    }
}
