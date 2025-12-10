<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Survey\Actions;

use App\Enums\Security\AuthorizationMode;
use App\Integrations\Survey\Actions\Theme\AttachThemeQuestionAction;
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
class AttachThemeQuestionActionTest extends TestCase
{
    use DatabaseTransactions;

    private AttachThemeQuestionAction $action;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new AttachThemeQuestionAction;
        $this->financer = ModelFactory::createFinancer();

        // Configure authorizationContext for global scopes
        authorizationContext()->hydrate(
            AuthorizationMode::SELF,
            [$this->financer->id],
            [$this->financer->division_id],
            [],
            $this->financer->id
        );

        Context::add('accessible_financers', [$this->financer->id]);
        Context::add('financer_id', $this->financer->id);
    }

    #[Test]
    public function it_attaches_questions_to_theme(): void
    {
        // Arrange
        $theme = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);

        $question1 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'theme_id' => null]);
        $question2 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'theme_id' => null]);

        // Verify questions are not attached
        $this->assertNull($question1->theme_id);
        $this->assertNull($question2->theme_id);

        $data = ['questions' => [['id' => $question1->id], ['id' => $question2->id]]];

        // Act
        $result = $this->action->execute($theme, $data);

        // Assert
        $this->assertInstanceOf(Theme::class, $result);
        $this->assertEquals($theme->id, $result->id);

        // Verify questions are attached
        $question1->refresh();
        $question2->refresh();

        $this->assertEquals($theme->id, $question1->theme_id);
        $this->assertEquals($theme->id, $question2->theme_id);

        // Verify via relationship
        $this->assertCount(2, $result->questions);
        $this->assertTrue($result->questions->contains('id', $question1->id));
        $this->assertTrue($result->questions->contains('id', $question2->id));
    }

    #[Test]
    public function it_attaches_single_question_to_theme(): void
    {
        // Arrange
        $theme = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);
        $question = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'theme_id' => null]);

        $data = ['questions' => [['id' => $question->id]]];

        // Act
        $result = $this->action->execute($theme, $data);

        // Assert
        $this->assertInstanceOf(Theme::class, $result);

        $question->refresh();
        $this->assertEquals($theme->id, $question->theme_id);
        $this->assertCount(1, $result->questions);
    }

    #[Test]
    public function it_handles_empty_questions_array_gracefully(): void
    {
        // Arrange
        $theme = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);

        $data = ['questions' => []];

        // Act
        $result = $this->action->execute($theme, $data);

        // Assert
        $this->assertInstanceOf(Theme::class, $result);
        $this->assertEquals($theme->id, $result->id);
        $this->assertCount(0, $result->questions);
    }

    #[Test]
    public function it_handles_missing_questions_key_gracefully(): void
    {
        // Arrange
        $theme = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);

        $data = [];

        // Act
        $result = $this->action->execute($theme, $data);

        // Assert
        $this->assertInstanceOf(Theme::class, $result);
        $this->assertEquals($theme->id, $result->id);
        $this->assertCount(0, $result->questions);
    }

    #[Test]
    public function it_can_attach_question_already_attached_to_another_theme(): void
    {
        // Arrange
        $theme1 = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);
        $theme2 = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);

        $question = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'theme_id' => $theme1->id]);

        // Verify question is initially attached to theme1
        $this->assertEquals($theme1->id, $question->theme_id);

        $data = ['questions' => [['id' => $question->id]]];

        // Act - Attach to theme2
        $result = $this->action->execute($theme2, $data);

        // Assert
        $this->assertInstanceOf(Theme::class, $result);

        $question->refresh();
        // Question should now be attached to theme2
        $this->assertEquals($theme2->id, $question->theme_id);

        // Theme1 should no longer have this question
        $theme1->refresh();
        $this->assertCount(0, $theme1->questions);

        // Theme2 should have this question
        $this->assertCount(1, $result->questions);
        $this->assertTrue($result->questions->contains('id', $question->id));
    }

    #[Test]
    public function it_preserves_existing_theme_questions_when_attaching_new_ones(): void
    {
        // Arrange
        $theme = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);

        // Create a question already attached to the theme
        $existingQuestion = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'theme_id' => $theme->id]);

        // Create new questions to attach
        $newQuestion1 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'theme_id' => null]);
        $newQuestion2 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'theme_id' => null]);

        $data = ['questions' => [['id' => $newQuestion1->id], ['id' => $newQuestion2->id]]];

        // Act
        $result = $this->action->execute($theme, $data);

        // Assert
        $this->assertInstanceOf(Theme::class, $result);

        // All 3 questions should be attached to the theme
        $this->assertCount(3, $result->questions);
        $this->assertTrue($result->questions->contains('id', $existingQuestion->id));
        $this->assertTrue($result->questions->contains('id', $newQuestion1->id));
        $this->assertTrue($result->questions->contains('id', $newQuestion2->id));
    }

    #[Test]
    public function it_handles_non_existent_question_ids_gracefully(): void
    {
        // Arrange
        $theme = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);
        $validQuestion = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'theme_id' => null]);

        $nonExistentId = '01999999-9999-7999-9999-999999999999';

        $data = ['questions' => [['id' => $validQuestion->id], ['id' => $nonExistentId]]];

        // Act
        $result = $this->action->execute($theme, $data);

        // Assert
        $this->assertInstanceOf(Theme::class, $result);

        // Valid question should be attached
        $validQuestion->refresh();
        $this->assertEquals($theme->id, $validQuestion->theme_id);

        // Only the valid question should be in the theme
        $this->assertCount(1, $result->questions);
        $this->assertTrue($result->questions->contains('id', $validQuestion->id));
    }

    #[Test]
    public function it_works_within_a_database_transaction(): void
    {
        // Arrange
        $theme = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);

        $question1 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'theme_id' => null]);
        $question2 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'theme_id' => null]);

        $data = ['questions' => [['id' => $question1->id], ['id' => $question2->id]]];

        // Act
        $result = $this->action->execute($theme, $data);

        // Assert - Verify the transaction completed successfully
        $this->assertInstanceOf(Theme::class, $result);

        // Verify the database state is consistent
        $this->assertDatabaseHas('int_survey_questions', [
            'id' => $question1->id,
            'theme_id' => $theme->id,
        ]);

        $this->assertDatabaseHas('int_survey_questions', [
            'id' => $question2->id,
            'theme_id' => $theme->id,
        ]);
    }
}
