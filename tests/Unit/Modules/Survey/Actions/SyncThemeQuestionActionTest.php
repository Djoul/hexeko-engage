<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Survey\Actions;

use App\Integrations\Survey\Actions\Theme\SyncThemeQuestionAction;
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
class SyncThemeQuestionActionTest extends TestCase
{
    use DatabaseTransactions;

    private SyncThemeQuestionAction $action;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new SyncThemeQuestionAction;
        $this->financer = ModelFactory::createFinancer();

        Context::add('accessible_financers', [$this->financer->id]);
        Context::add('financer_id', $this->financer->id);
    }

    #[Test]
    public function it_syncs_questions_to_theme(): void
    {
        // Arrange
        $theme = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);

        // Create existing questions attached to the theme
        $existingQuestion1 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'theme_id' => $theme->id]);
        $existingQuestion2 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'theme_id' => $theme->id]);

        // Create new questions to sync
        $newQuestion1 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'theme_id' => null]);
        $newQuestion2 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'theme_id' => null]);

        // Verify existing questions are attached
        $this->assertCount(2, $theme->questions);

        $data = ['questions' => [['id' => $newQuestion1->id], ['id' => $newQuestion2->id]]];

        // Act
        $result = $this->action->execute($theme, $data);

        // Assert
        $this->assertInstanceOf(Theme::class, $result);
        $this->assertEquals($theme->id, $result->id);

        // Verify old questions are detached
        $existingQuestion1->refresh();
        $existingQuestion2->refresh();
        $this->assertNull($existingQuestion1->theme_id);
        $this->assertNull($existingQuestion2->theme_id);

        // Verify new questions are attached
        $newQuestion1->refresh();
        $newQuestion2->refresh();
        $this->assertEquals($theme->id, $newQuestion1->theme_id);
        $this->assertEquals($theme->id, $newQuestion2->theme_id);

        // Verify via relationship - only new questions should be there
        $this->assertCount(2, $result->questions);
        $this->assertFalse($result->questions->contains('id', $existingQuestion1->id));
        $this->assertFalse($result->questions->contains('id', $existingQuestion2->id));
        $this->assertTrue($result->questions->contains('id', $newQuestion1->id));
        $this->assertTrue($result->questions->contains('id', $newQuestion2->id));
    }

    #[Test]
    public function it_removes_all_questions_when_syncing_empty_array(): void
    {
        // Arrange
        $theme = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);

        $question1 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'theme_id' => $theme->id]);
        $question2 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'theme_id' => $theme->id]);

        // Verify questions are attached
        $this->assertCount(2, $theme->questions);

        $data = ['questions' => []];

        // Act
        $result = $this->action->execute($theme, $data);

        // Assert
        $this->assertInstanceOf(Theme::class, $result);

        // All questions should be detached
        $question1->refresh();
        $question2->refresh();
        $this->assertNull($question1->theme_id);
        $this->assertNull($question2->theme_id);

        // Theme should have no questions
        $this->assertCount(0, $result->questions);
    }

    #[Test]
    public function it_handles_missing_questions_key_by_removing_all_questions(): void
    {
        // Arrange
        $theme = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);

        $question1 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'theme_id' => $theme->id]);
        $question2 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'theme_id' => $theme->id]);

        // Verify questions are attached
        $this->assertCount(2, $theme->questions);

        $data = [];

        // Act
        $result = $this->action->execute($theme, $data);

        // Assert
        $this->assertInstanceOf(Theme::class, $result);

        // All questions should be detached
        $question1->refresh();
        $question2->refresh();
        $this->assertNull($question1->theme_id);
        $this->assertNull($question2->theme_id);

        // Theme should have no questions
        $this->assertCount(0, $result->questions);
    }

    #[Test]
    public function it_syncs_single_question_to_theme(): void
    {
        // Arrange
        $theme = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);

        $existingQuestion = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'theme_id' => $theme->id]);
        $newQuestion = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'theme_id' => null]);

        $data = ['questions' => [['id' => $newQuestion->id]]];

        // Act
        $result = $this->action->execute($theme, $data);

        // Assert
        $this->assertInstanceOf(Theme::class, $result);

        $existingQuestion->refresh();
        $newQuestion->refresh();

        $this->assertNull($existingQuestion->theme_id);
        $this->assertEquals($theme->id, $newQuestion->theme_id);
        $this->assertCount(1, $result->questions);
        $this->assertTrue($result->questions->contains('id', $newQuestion->id));
    }

    #[Test]
    public function it_handles_non_existent_question_ids_gracefully(): void
    {
        // Arrange
        $theme = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);

        $existingQuestion = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'theme_id' => $theme->id]);
        $validQuestion = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'theme_id' => null]);

        $nonExistentId = '01999999-9999-7999-9999-999999999999';

        $data = ['questions' => [['id' => $validQuestion->id], ['id' => $nonExistentId]]];

        // Act
        $result = $this->action->execute($theme, $data);

        // Assert
        $this->assertInstanceOf(Theme::class, $result);

        // Existing question should be detached
        $existingQuestion->refresh();
        $this->assertNull($existingQuestion->theme_id);

        // Valid question should be attached
        $validQuestion->refresh();
        $this->assertEquals($theme->id, $validQuestion->theme_id);

        // Only the valid question should be in the theme
        $this->assertCount(1, $result->questions);
        $this->assertTrue($result->questions->contains('id', $validQuestion->id));
    }

    #[Test]
    public function it_can_sync_question_from_another_theme(): void
    {
        // Arrange
        $theme1 = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);
        $theme2 = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);

        $question1 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'theme_id' => $theme1->id]);
        $question2 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'theme_id' => $theme2->id]);

        $data = ['questions' => [['id' => $question2->id]]];

        // Act - Sync theme1 with question from theme2
        $result = $this->action->execute($theme1, $data);

        // Assert
        $this->assertInstanceOf(Theme::class, $result);

        // Question1 should be detached from theme1
        $question1->refresh();
        $this->assertNull($question1->theme_id);

        // Question2 should now be attached to theme1
        $question2->refresh();
        $this->assertEquals($theme1->id, $question2->theme_id);

        // Theme1 should have only question2
        $this->assertCount(1, $result->questions);
        $this->assertTrue($result->questions->contains('id', $question2->id));

        // Theme2 should have no questions
        $theme2->refresh();
        $this->assertCount(0, $theme2->questions);
    }

    #[Test]
    public function it_does_not_affect_questions_from_other_themes_when_syncing(): void
    {
        // Arrange
        $theme1 = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);
        $theme2 = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);

        resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'theme_id' => $theme1->id]);
        $question2 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'theme_id' => $theme2->id]);
        $question3 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'theme_id' => null]);

        $data = ['questions' => [['id' => $question3->id]]];

        // Act - Sync theme1
        $result = $this->action->execute($theme1, $data);

        // Assert
        $this->assertInstanceOf(Theme::class, $result);

        // Question2 should still be attached to theme2
        $question2->refresh();
        $this->assertEquals($theme2->id, $question2->theme_id);

        $theme2->refresh();
        $this->assertCount(1, $theme2->questions);
        $this->assertTrue($theme2->questions->contains('id', $question2->id));
    }

    #[Test]
    public function it_works_within_a_database_transaction(): void
    {
        // Arrange
        $theme = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);

        $existingQuestion = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'theme_id' => $theme->id]);
        $newQuestion = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'theme_id' => null]);

        $data = ['questions' => [['id' => $newQuestion->id]]];

        // Act
        $result = $this->action->execute($theme, $data);

        // Assert - Verify the transaction completed successfully
        $this->assertInstanceOf(Theme::class, $result);

        // Verify the database state is consistent
        $this->assertDatabaseHas('int_survey_questions', [
            'id' => $existingQuestion->id,
            'theme_id' => null,
        ]);

        $this->assertDatabaseHas('int_survey_questions', [
            'id' => $newQuestion->id,
            'theme_id' => $theme->id,
        ]);
    }

    #[Test]
    public function it_syncs_multiple_questions_replacing_all_existing_ones(): void
    {
        // Arrange
        $theme = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);

        // Create 3 existing questions
        $existingQuestion1 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'theme_id' => $theme->id]);
        $existingQuestion2 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'theme_id' => $theme->id]);
        $existingQuestion3 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'theme_id' => $theme->id]);

        // Create 2 new questions
        $newQuestion1 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'theme_id' => null]);
        $newQuestion2 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'theme_id' => null]);

        $this->assertCount(3, $theme->questions);

        $data = ['questions' => [['id' => $newQuestion1->id], ['id' => $newQuestion2->id]]];

        // Act
        $result = $this->action->execute($theme, $data);

        // Assert
        $this->assertInstanceOf(Theme::class, $result);

        // All existing questions should be detached
        $existingQuestion1->refresh();
        $existingQuestion2->refresh();
        $existingQuestion3->refresh();
        $this->assertNull($existingQuestion1->theme_id);
        $this->assertNull($existingQuestion2->theme_id);
        $this->assertNull($existingQuestion3->theme_id);

        // New questions should be attached
        $newQuestion1->refresh();
        $newQuestion2->refresh();
        $this->assertEquals($theme->id, $newQuestion1->theme_id);
        $this->assertEquals($theme->id, $newQuestion2->theme_id);

        // Theme should have exactly 2 questions (the new ones)
        $this->assertCount(2, $result->questions);
        $this->assertTrue($result->questions->contains('id', $newQuestion1->id));
        $this->assertTrue($result->questions->contains('id', $newQuestion2->id));
    }
}
