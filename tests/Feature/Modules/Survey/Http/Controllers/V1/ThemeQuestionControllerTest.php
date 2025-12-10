<?php

namespace Tests\Feature\Modules\Survey\Http\Controllers\V1;

use App\Integrations\Survey\Database\factories\QuestionFactory;
use App\Integrations\Survey\Database\factories\ThemeFactory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('survey')]
#[Group('theme')]
#[Group('question')]
class ThemeQuestionControllerTest extends SurveyTestCase
{
    #[Test]
    public function it_can_list_questions_for_a_theme(): void
    {
        $theme = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);
        resolve(QuestionFactory::class)->create(['theme_id' => $theme->id, 'financer_id' => $this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('survey.themes.questions.index', ['theme' => $theme, 'financer_id' => $this->financer->id]));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'text',
                        'text_raw',
                        'help_text',
                        'help_text_raw',
                        'options',
                        'type',
                        'theme_id',
                        'financer_id',
                        'metadata',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);
    }

    #[Test]
    public function it_can_attach_questions_to_a_theme(): void
    {
        $theme = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);
        $question = resolve(QuestionFactory::class)->create(['theme_id' => $theme->id, 'financer_id' => $this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->postJson(route('survey.themes.questions.attach', ['theme' => $theme, 'financer_id' => $this->financer->id]), ['questions' => [$question->id]]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('int_survey_questions', ['id' => $question->id, 'theme_id' => $theme->id]);
    }

    #[Test]
    public function it_can_detach_questions_from_a_theme(): void
    {
        $theme = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);
        $question = resolve(QuestionFactory::class)->create(['theme_id' => $theme->id, 'financer_id' => $this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->postJson(route('survey.themes.questions.detach', ['theme' => $theme, 'financer_id' => $this->financer->id]), ['questions' => [$question->id]]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('int_survey_questions', ['id' => $question->id, 'theme_id' => null]);
    }

    #[Test]
    public function it_can_sync_questions_to_a_theme(): void
    {
        $theme = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);
        $question = resolve(QuestionFactory::class)->create(['theme_id' => $theme->id, 'financer_id' => $this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->postJson(route('survey.themes.questions.sync', ['theme' => $theme, 'financer_id' => $this->financer->id]), ['questions' => [$question->id]]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('int_survey_questions', ['id' => $question->id, 'theme_id' => $theme->id]);
    }
}
