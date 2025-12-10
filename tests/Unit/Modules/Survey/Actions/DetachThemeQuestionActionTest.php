<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Survey\Actions;

use App\Integrations\Survey\Actions\Theme\DetachThemeQuestionAction;
use App\Integrations\Survey\Database\factories\QuestionFactory;
use App\Integrations\Survey\Database\factories\ThemeFactory;
use App\Integrations\Survey\Models\Theme;
use App\Models\Financer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('survey')]
#[Group('theme')]
class DetachThemeQuestionActionTest extends TestCase
{
    use DatabaseTransactions;

    private DetachThemeQuestionAction $action;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new DetachThemeQuestionAction;
        $this->financer = ModelFactory::createFinancer();

        Context::add('accessible_financers', [$this->financer->id]);
        Context::add('financer_id', $this->financer->id);
    }

    #[Test]
    public function it_detaches_questions_from_theme(): void
    {
        // Arrange
        $theme = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);

        $question1 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'theme_id' => $theme->id]);
        $question2 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'theme_id' => $theme->id]);
        $question3 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'theme_id' => $theme->id]);

        // Verify questions are attached
        $this->assertCount(3, $theme->questions);

        $data = ['questions' => [['id' => $question1->id], ['id' => $question2->id]]];

        // Act
        $result = $this->action->execute($theme, $data);

        // Assert
        $this->assertInstanceOf(Theme::class, $result);
        $this->assertEquals($theme->id, $result->id);

        // Verify questions are detached
        $question1->refresh();
        $question2->refresh();
        $question3->refresh();

        $this->assertNull($question1->theme_id);
        $this->assertNull($question2->theme_id);
        $this->assertEquals($theme->id, $question3->theme_id);

        // Verify via relationship
        $this->assertCount(1, $result->questions);
        $this->assertFalse($result->questions->contains('id', $question1->id));
        $this->assertFalse($result->questions->contains('id', $question2->id));
        $this->assertTrue($result->questions->contains('id', $question3->id));
    }

    #[Test]
    public function it_detaches_single_question_from_theme(): void
    {
        // Arrange
        $theme = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);
        $question1 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'theme_id' => $theme->id]);
        $question2 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'theme_id' => $theme->id]);

        $data = ['questions' => [['id' => $question1->id]]];

        // Act
        $result = $this->action->execute($theme, $data);

        // Assert
        $this->assertInstanceOf(Theme::class, $result);

        $question1->refresh();
        $question2->refresh();

        $this->assertNull($question1->theme_id);
        $this->assertEquals($theme->id, $question2->theme_id);
        $this->assertCount(1, $result->questions);
    }

    #[Test]
    public function it_handles_empty_questions_array_gracefully(): void
    {
        // Arrange
        $theme = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);
        $question = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'theme_id' => $theme->id]);

        $data = ['questions' => []];

        // Act
        $result = $this->action->execute($theme, $data);

        // Assert
        $this->assertInstanceOf(Theme::class, $result);
        $this->assertEquals($theme->id, $result->id);

        // Question should still be attached
        $question->refresh();
        $this->assertEquals($theme->id, $question->theme_id);
        $this->assertCount(1, $result->questions);
    }

    #[Test]
    public function it_handles_missing_questions_key_gracefully(): void
    {
        // Arrange
        $theme = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);
        $question = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'theme_id' => $theme->id]);

        $data = [];

        // Act
        $result = $this->action->execute($theme, $data);

        // Assert
        $this->assertInstanceOf(Theme::class, $result);
        $this->assertEquals($theme->id, $result->id);

        // Question should still be attached
        $question->refresh();
        $this->assertEquals($theme->id, $question->theme_id);
        $this->assertCount(1, $result->questions);
    }

    #[Test]
    public function it_handles_non_existent_question_ids_gracefully(): void
    {
        // Arrange
        $theme = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);
        $validQuestion = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'theme_id' => $theme->id]);

        $nonExistentId = '01999999-9999-7999-9999-999999999999';

        $data = ['questions' => [['id' => $validQuestion->id], ['id' => $nonExistentId]]];

        // Act
        $result = $this->action->execute($theme, $data);

        // Assert
        $this->assertInstanceOf(Theme::class, $result);

        // Valid question should be detached
        $validQuestion->refresh();
        $this->assertNull($validQuestion->theme_id);

        // Theme should have no questions
        $this->assertCount(0, $result->questions);
    }

    #[Test]
    public function it_does_not_affect_questions_from_other_themes(): void
    {
        // Arrange
        $theme1 = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);
        $theme2 = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);

        $question1 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'theme_id' => $theme1->id]);
        $question2 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'theme_id' => $theme2->id]);

        $data = ['questions' => [['id' => $question1->id]]];

        // Act - Detach question1 from theme1
        $result = $this->action->execute($theme1, $data);

        // Assert
        $this->assertInstanceOf(Theme::class, $result);

        $question1->refresh();
        $question2->refresh();

        // Question1 should be detached
        $this->assertNull($question1->theme_id);

        // Question2 should still be attached to theme2
        $this->assertEquals($theme2->id, $question2->theme_id);

        $theme2->refresh();
        $this->assertCount(1, $theme2->questions);
    }

    #[Test]
    public function it_detaches_all_questions_when_all_ids_provided(): void
    {
        // Arrange
        $theme = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);

        $question1 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'theme_id' => $theme->id]);
        $question2 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'theme_id' => $theme->id]);

        $data = ['questions' => [['id' => $question1->id], ['id' => $question2->id]]];

        // Act
        $result = $this->action->execute($theme, $data);

        // Assert
        $this->assertInstanceOf(Theme::class, $result);

        $question1->refresh();
        $question2->refresh();

        $this->assertNull($question1->theme_id);
        $this->assertNull($question2->theme_id);
        $this->assertCount(0, $result->questions);
    }

    #[Test]
    public function it_works_within_a_database_transaction(): void
    {
        // Arrange
        $theme = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);

        $question1 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'theme_id' => $theme->id]);
        $question2 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'theme_id' => $theme->id]);

        $data = ['questions' => [['id' => $question1->id], ['id' => $question2->id]]];

        // Act
        $result = $this->action->execute($theme, $data);

        // Assert - Verify the transaction completed successfully
        $this->assertInstanceOf(Theme::class, $result);

        // Verify the database state is consistent
        $this->assertDatabaseHas('int_survey_questions', [
            'id' => $question1->id,
            'theme_id' => null,
        ]);

        $this->assertDatabaseHas('int_survey_questions', [
            'id' => $question2->id,
            'theme_id' => null,
        ]);
    }
}
