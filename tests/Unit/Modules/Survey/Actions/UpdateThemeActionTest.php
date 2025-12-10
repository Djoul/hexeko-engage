<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Survey\Actions;

use App\Integrations\Survey\Actions\Theme\UpdateThemeAction;
use App\Integrations\Survey\Models\Question;
use App\Integrations\Survey\Models\Theme;
use App\Models\Financer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('survey')]
#[Group('theme')]
class UpdateThemeActionTest extends TestCase
{
    use DatabaseTransactions;

    private UpdateThemeAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new UpdateThemeAction;
    }

    #[Test]
    public function it_create_a_theme_successfully(): void
    {
        $data = [
            'name' => ['en-GB' => 'Updated Theme', 'fr-FR' => 'Thème Mis à Jour'],
            'description' => ['en-GB' => 'Updated Description', 'fr-FR' => 'Description Mise à Jour'],
        ];

        $theme = new Theme;

        $result = $this->action->execute($theme, $data);

        $this->assertInstanceOf(Theme::class, $result);
        $this->assertTrue($result->exists);

        // Test name translations using getTranslation method
        $this->assertEquals($data['name']['en-GB'], $result->getTranslation('name', 'en-GB'));
        $this->assertEquals($data['name']['fr-FR'], $result->getTranslation('name', 'fr-FR'));

        // Test description translations using getTranslation method
        $this->assertEquals($data['description']['en-GB'], $result->getTranslation('description', 'en-GB'));
        $this->assertEquals($data['description']['fr-FR'], $result->getTranslation('description', 'fr-FR'));

        // Test default values
        $this->assertNull($result->financer_id);
        $this->assertFalse($result->is_default);
        $this->assertEquals(1, $result->position);
    }

    #[Test]
    public function it_create_a_theme_with_questions_successfully(): void
    {
        // Create a financer first for the context
        $financer = Financer::factory()->create();
        Context::add('accessible_financers', [$financer->id]);
        Context::add('financer_id', $financer->id);

        // Create some questions first
        $question1 = Question::factory()->create(['financer_id' => $financer->id]);
        $question2 = Question::factory()->create(['financer_id' => $financer->id]);

        $data = [
            'name' => ['en-GB' => 'Updated Theme', 'fr-FR' => 'Thème Mis à Jour'],
            'description' => ['en-GB' => 'Updated Description', 'fr-FR' => 'Description Mise à Jour'],
            'questions' => [$question1->id, $question2->id],
        ];

        $theme = new Theme;

        $result = $this->action->execute($theme, $data);

        $this->assertInstanceOf(Theme::class, $result);
        $this->assertTrue($result->exists);

        // Test name translations using getTranslation method
        $this->assertEquals($data['name']['en-GB'], $result->getTranslation('name', 'en-GB'));
        $this->assertEquals($data['name']['fr-FR'], $result->getTranslation('name', 'fr-FR'));

        // Test description translations using getTranslation method
        $this->assertEquals($data['description']['en-GB'], $result->getTranslation('description', 'en-GB'));
        $this->assertEquals($data['description']['fr-FR'], $result->getTranslation('description', 'fr-FR'));

        // Test default values
        $this->assertNull($result->financer_id);
        $this->assertFalse($result->is_default);
        $this->assertEquals(1, $result->position);

        // Test questions - should have 2 questions attached
        $this->assertCount(2, $result->questions);

        // Verify the questions are correctly attached
        $questionIds = $result->questions->pluck('id')->toArray();
        $this->assertContains($question1->id, $questionIds);
        $this->assertContains($question2->id, $questionIds);
    }

    #[Test]
    public function it_updates_an_existing_theme_successfully(): void
    {
        // Create a theme first
        $theme = Theme::create([
            'name' => ['en-GB' => 'Original Theme', 'fr-FR' => 'Thème Original'],
            'description' => ['en-GB' => 'Original Description'],
            'is_default' => false,
            'position' => 1,
        ]);

        $updateData = [
            'name' => ['en-GB' => 'Updated Theme', 'fr-FR' => 'Thème Mis à Jour'],
            'description' => ['en-GB' => 'Updated Description', 'fr-FR' => 'Description Mise à Jour'],
            'is_default' => true,
            'position' => 5,
        ];

        $result = $this->action->execute($theme, $updateData);

        $this->assertInstanceOf(Theme::class, $result);

        // Test updated translations
        $this->assertEquals($updateData['name']['en-GB'], $result->getTranslation('name', 'en-GB'));
        $this->assertEquals($updateData['name']['fr-FR'], $result->getTranslation('name', 'fr-FR'));
        $this->assertEquals($updateData['description']['en-GB'], $result->getTranslation('description', 'en-GB'));
        $this->assertEquals($updateData['description']['fr-FR'], $result->getTranslation('description', 'fr-FR'));

        // Test updated values
        $this->assertTrue($result->is_default);
        $this->assertEquals(5, $result->position);
    }

    #[Test]
    public function it_updates_an_existing_theme_with_questions_successfully(): void
    {
        $financer = Financer::factory()->create();
        Context::add('accessible_financers', [$financer->id]);
        Context::add('financer_id', $financer->id);

        $theme = Theme::create([
            'name' => ['en-GB' => 'Original Theme', 'fr-FR' => 'Thème Original'],
            'description' => ['en-GB' => 'Original Description'],
            'financer_id' => $financer->id,
        ]);

        $question1 = Question::factory()->create(['financer_id' => $theme->financer_id]);
        $question2 = Question::factory()->create(['financer_id' => $theme->financer_id]);

        $updateData = [
            'name' => ['en-GB' => 'Updated Theme', 'fr-FR' => 'Thème Mis à Jour'],
            'description' => ['en-GB' => 'Updated Description', 'fr-FR' => 'Description Mise à Jour'],
            'questions' => [$question1->id, $question2->id],
        ];

        $result = $this->action->execute($theme, $updateData);

        $this->assertInstanceOf(Theme::class, $result);

        $this->assertCount(2, $result->questions);

        $questionIds = $result->questions->pluck('id')->toArray();
        $this->assertContains($question1->id, $questionIds);
        $this->assertContains($question2->id, $questionIds);
    }

    #[Test]
    public function it_handles_partial_data_updates(): void
    {
        $theme = Theme::create([
            'name' => ['en-GB' => 'Original Theme', 'fr-FR' => 'Thème Original'],
            'description' => ['en-GB' => 'Original Description'],
            'is_default' => false,
            'position' => 1,
        ]);

        $updateData = [
            'name' => ['en-GB' => 'Updated Theme Only'],
            'is_default' => true,
        ];

        $result = $this->action->execute($theme, $updateData);

        $this->assertInstanceOf(Theme::class, $result);

        // Test updated name
        $this->assertEquals($updateData['name']['en-GB'], $result->getTranslation('name', 'en-GB'));

        // Test that other fields remain unchanged
        $this->assertEquals('Original Description', $result->getTranslation('description', 'en-GB'));
        $this->assertEquals(1, $result->position); // Should remain 1
        $this->assertTrue($result->is_default); // Should be updated to true
    }
}
