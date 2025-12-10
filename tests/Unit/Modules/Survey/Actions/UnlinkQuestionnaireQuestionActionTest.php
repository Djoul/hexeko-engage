<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Survey\Actions;

use App\Integrations\Survey\Actions\Questionnaire\UnlinkQuestionnaireQuestionAction;
use App\Integrations\Survey\Database\factories\QuestionFactory;
use App\Integrations\Survey\Database\factories\QuestionnaireFactory;
use App\Integrations\Survey\Models\Questionnaire;
use App\Models\Financer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('survey')]
class UnlinkQuestionnaireQuestionActionTest extends TestCase
{
    use DatabaseTransactions;

    private UnlinkQuestionnaireQuestionAction $action;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new UnlinkQuestionnaireQuestionAction;
        $this->financer = ModelFactory::createFinancer();

        Context::add('accessible_financers', [$this->financer->id]);
        Context::add('financer_id', $this->financer->id);
    }

    #[Test]
    public function it_unlinks_questions_with_simple_ids_array(): void
    {
        // Arrange
        $questionnaire = resolve(QuestionnaireFactory::class)->create(['financer_id' => $this->financer->id]);

        $question1 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);
        $question2 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);
        $question3 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);

        // First attach all questions
        $questionnaire->questions()->attach([$question1->id, $question2->id, $question3->id]);

        $data = [$question1->id, $question2->id];

        // Act
        $result = $this->action->execute($questionnaire, ['questions' => $data]);

        // Assert
        $this->assertInstanceOf(Questionnaire::class, $result);
        $this->assertEquals($questionnaire->id, $result->id);

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
        $questionnaire = resolve(QuestionnaireFactory::class)->create(['financer_id' => $this->financer->id]);

        $question1 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);
        $question2 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);
        $question3 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);

        // First attach all questions with display order
        $questionnaire->questions()->attach([
            $question1->id => ['position' => 10],
            $question2->id => ['position' => 20],
            $question3->id => ['position' => 30],
        ]);

        $data = [$question1->id, $question2->id];

        // Act
        $result = $this->action->execute($questionnaire, ['questions' => $data]);

        // Assert
        $this->assertInstanceOf(Questionnaire::class, $result);
        $this->assertEquals($questionnaire->id, $result->id);

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
        $questionnaire = resolve(QuestionnaireFactory::class)->create(['financer_id' => $this->financer->id]);

        $question1 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);

        // First attach a question
        $questionnaire->questions()->attach([$question1->id]);

        $data = [];

        // Act
        $result = $this->action->execute($questionnaire, $data);

        // Assert
        $this->assertInstanceOf(Questionnaire::class, $result);
        $this->assertEquals($questionnaire->id, $result->id);

        // Verify no questions are unlinked (question still attached)
        $this->assertCount(1, $result->questions);
        $this->assertTrue($result->questions->contains('id', $question1->id));
    }

    #[Test]
    public function it_unlinks_already_attached_questions(): void
    {
        // Arrange
        $questionnaire = resolve(QuestionnaireFactory::class)->create(['financer_id' => $this->financer->id]);

        $question1 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);
        $question2 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);

        // First attach questions
        $questionnaire->questions()->attach([$question1->id, $question2->id]);

        // Verify they are attached
        $this->assertCount(2, $questionnaire->questions);

        $data = [$question1->id];

        // Act
        $result = $this->action->execute($questionnaire, ['questions' => $data]);

        // Assert
        $this->assertInstanceOf(Questionnaire::class, $result);
        $this->assertEquals($questionnaire->id, $result->id);

        // Verify only one question is unlinked
        $this->assertCount(1, $result->questions);
        $this->assertFalse($result->questions->contains('id', $question1->id));
        $this->assertTrue($result->questions->contains('id', $question2->id));
    }
}
