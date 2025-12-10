<?php

namespace Tests\Feature\Modules\Survey\Http\Controllers\V1;

use App\Integrations\Survey\Database\factories\QuestionFactory;
use App\Integrations\Survey\Database\factories\SurveyFactory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('survey')]
#[Group('question')]
class SurveyQuestionControllerTest extends SurveyTestCase
{
    #[Test]
    public function it_can_list_questions_for_a_survey(): void
    {
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);
        $question = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);

        $survey->questions()->attach($question->id, ['position' => 1]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('survey.surveys.questions.index', $survey).'?financer_id='.$this->financer->id);

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
    public function it_can_link_questions_to_a_survey(): void
    {
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);
        $question = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->postJson(route('survey.surveys.questions.link', ['survey' => $survey, 'financer_id' => $this->financer->id]), ['questions' => [['id' => $question->id]]]);

        $response->assertStatus(200);

        // Refresh the survey to get the updated questions
        $survey->refresh();
        $linkedQuestions = $survey->questions;

        // Verify the question is linked (duplicated since it wasn't linked before)
        $this->assertCount(1, $linkedQuestions);

        // The question should be duplicated (different ID but same content)
        $this->assertFalse($linkedQuestions->contains('id', $question->id));
        $this->assertTrue($linkedQuestions->contains('text', $question->text));
    }

    #[Test]
    public function it_can_unlink_questions_from_a_survey(): void
    {
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);
        $question = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);
        $survey->questions()->attach($question->id, ['position' => 1]);

        $response = $this->actingAs($this->auth)
            ->postJson(route('survey.surveys.questions.unlink', ['survey' => $survey, 'financer_id' => $this->financer->id]), ['questions' => [['id' => $question->id]]]);

        $response->assertStatus(200);

        // Verify the question is unlinked (removed from the survey)
        $this->assertDatabaseMissing('int_survey_questionables', [
            'questionable_type' => 'App\\Integrations\\Survey\\Models\\Survey',
            'questionable_id' => $survey->id,
            'question_id' => $question->id,
        ]);
    }

    #[Test]
    public function it_can_reorder_survey_questions(): void
    {
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);
        $question1 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);
        $question2 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);
        $question3 = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);

        // Link questions with initial positions
        $survey->questions()->attach([
            $question1->id => ['position' => 1],
            $question2->id => ['position' => 2],
            $question3->id => ['position' => 3],
        ]);

        // Reorder: reverse the order
        $payload = [
            'questions' => [
                ['id' => $question3->id, 'position' => 1],
                ['id' => $question2->id, 'position' => 2],
                ['id' => $question1->id, 'position' => 3],
            ],
        ];

        $response = $this->actingAs($this->auth)
            ->postJson(route('survey.surveys.questions.reorder', ['survey' => $survey, 'financer_id' => $this->financer->id]), $payload);

        $response->assertStatus(200);

        // Verify the new order
        $this->assertDatabaseHas('int_survey_questionables', [
            'questionable_type' => 'App\\Integrations\\Survey\\Models\\Survey',
            'questionable_id' => $survey->id,
            'question_id' => $question3->id,
            'position' => 1,
        ]);

        $this->assertDatabaseHas('int_survey_questionables', [
            'questionable_type' => 'App\\Integrations\\Survey\\Models\\Survey',
            'questionable_id' => $survey->id,
            'question_id' => $question2->id,
            'position' => 2,
        ]);

        $this->assertDatabaseHas('int_survey_questionables', [
            'questionable_type' => 'App\\Integrations\\Survey\\Models\\Survey',
            'questionable_id' => $survey->id,
            'question_id' => $question1->id,
            'position' => 3,
        ]);

        $response->assertJsonStructure([
            'data' => [
                'id',
                'questions',
            ],
        ]);
    }

    #[Test]
    public function it_validates_reorder_survey_questions_input(): void
    {
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->postJson(route('survey.surveys.questions.reorder', ['survey' => $survey, 'financer_id' => $this->financer->id]), []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['questions']);
    }

    #[Test]
    public function it_returns_questions_with_theme_and_options(): void
    {
        $survey = resolve(SurveyFactory::class)->create(['financer_id' => $this->financer->id]);
        $question = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);

        $survey->questions()->attach($question->id, ['position' => 1]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('survey.surveys.questions.index', ['survey' => $survey, 'financer_id' => $this->financer->id]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'theme',
                        'options',
                        'answers_count',
                    ],
                ],
            ]);
    }
}
