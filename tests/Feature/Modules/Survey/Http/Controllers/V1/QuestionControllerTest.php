<?php

namespace Tests\Feature\Modules\Survey\Http\Controllers\V1;

use App\Helpers\LanguageHelper;
use App\Integrations\Survey\Database\factories\QuestionFactory;
use App\Integrations\Survey\Database\factories\ThemeFactory;
use App\Integrations\Survey\Enums\QuestionTypeEnum;
use App\Integrations\Survey\Models\Question;
use Database\Factories\FinancerFactory;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('survey')]
#[Group('question')]
class QuestionControllerTest extends SurveyTestCase
{
    #[Test]
    public function it_can_list_questions(): void
    {
        resolve(QuestionFactory::class)->count(3)->create(['financer_id' => $this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('survey.questions.index', ['financer_id' => $this->financer->id]));

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data')
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
    public function it_filters_questions_by_type(): void
    {
        resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'type' => QuestionTypeEnum::TEXT]);
        resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'type' => QuestionTypeEnum::MULTIPLE_CHOICE]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('survey.questions.index', ['type' => QuestionTypeEnum::TEXT, 'financer_id' => $this->financer->id]));

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
    public function it_filters_questions_by_theme_id(): void
    {
        $theme1 = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);
        $theme2 = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);

        resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'theme_id' => $theme1->id]);
        resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'theme_id' => $theme2->id]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('survey.questions.index', ['theme_id' => $theme1->id, 'financer_id' => $this->financer->id]));

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
    public function it_filters_questions_by_financer_id(): void
    {
        $otherFinancer = resolve(FinancerFactory::class)->create();

        resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);
        resolve(QuestionFactory::class)->create(['financer_id' => $otherFinancer->id]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('survey.questions.index', ['financer_id' => $this->financer->id]));

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
    public function it_filters_questions_by_created_at_date(): void
    {
        resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'created_at' => '2023-01-01']);
        resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'created_at' => '2024-01-02']);
        resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'created_at' => '2024-01-03']);
        resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'created_at' => '2024-01-04']);

        $response = $this->actingAs($this->auth)->getJson(route('survey.questions.index', [
            'financer_id' => $this->financer->id,
            'date_from' => '2024-01-01',
            'date_from_fields' => ['created_at'],
        ]));

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    #[Test]
    public function it_filters_questions_by_date_from(): void
    {
        resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'created_at' => '2024-01-01']);
        resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'created_at' => '2024-01-02']);
        resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'created_at' => '2024-01-03']);
        resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'created_at' => '2024-01-04']);

        // Filter by created_at field (default)
        $response = $this->actingAs($this->auth)->getJson(route('survey.questions.index', [
            'date_from' => '2024-01-01',
            'financer_id' => $this->financer->id,
        ]));
        $response->assertOk()->assertJsonCount(4, 'data');

        // Filter by created_at field with later date
        $response = $this->actingAs($this->auth)->getJson(route('survey.questions.index', [
            'financer_id' => $this->financer->id,
            'date_from' => '2024-01-03',
            'date_from_fields' => 'created_at',
        ]));
        $response->assertOk()->assertJsonCount(2, 'data');

        // Filter by multiple date fields
        resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'updated_at' => '2023-03-01']);
        $response = $this->actingAs($this->auth)->getJson(route('survey.questions.index', [
            'financer_id' => $this->financer->id,
            'date_from' => '2023-03-01',
            'date_from_fields' => ['created_at', 'updated_at'],
        ]));
        $response->assertOk()->assertJsonCount(5, 'data');
    }

    #[Test]
    public function it_filters_questions_by_date_to(): void
    {
        resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'updated_at' => '2023-01-31']);
        resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'updated_at' => '2023-02-28']);
        resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'updated_at' => '2023-02-01']);
        resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'updated_at' => '2023-01-01']);

        // Filter by updated_at field specifically
        $response = $this->actingAs($this->auth)->getJson(route('survey.questions.index', [
            'financer_id' => $this->financer->id,
            'date_to' => '2023-01-31',
            'date_to_fields' => 'updated_at',
        ]));
        $response->assertOk()->assertJsonCount(2, 'data');

        $response = $this->actingAs($this->auth)->getJson(route('survey.questions.index', [
            'financer_id' => $this->financer->id,
            'date_to' => '2023-02-28',
            'date_to_fields' => 'updated_at',
        ]));
        $response->assertOk()->assertJsonCount(4, 'data');

        $response = $this->actingAs($this->auth)->getJson(route('survey.questions.index', [
            'financer_id' => $this->financer->id,
            'date_to' => '2023-02-01',
            'date_to_fields' => ['created_at', 'updated_at'],
        ]));
        $response->assertOk()->assertJsonCount(3, 'data');
    }

    #[Test]
    public function it_store_validates_input(): void
    {
        $this->actingAs($this->auth)
            ->postJson(route('survey.questions.store', ['financer_id' => $this->financer->id]), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['text', 'type', 'theme_id']);
    }

    #[Test]
    public function it_persists_and_redirects_when_storing(): void
    {
        $theme = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);
        $question = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'theme_id' => $theme->id]);

        $languages = LanguageHelper::getLanguages($this->financer->id);
        $text = [];
        $helpText = [];
        foreach ($languages as $language) {
            $text[$language] = 'New Question';
            $helpText[$language] = 'Help Text';
        }

        $payload = [
            'text' => $text,
            'help_text' => $helpText,
            'type' => QuestionTypeEnum::MULTIPLE_CHOICE,
            'theme_id' => $question->theme->id,
            'financer_id' => $question->financer_id,
            'metadata' => ['category' => 'test'],
        ];

        $response = $this->actingAs($this->auth)
            ->postJson(route('survey.questions.store', ['financer_id' => $this->financer->id]), $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('int_survey_questions', [
            'theme_id' => $question->theme_id,
            'financer_id' => $question->financer_id,
            'type' => QuestionTypeEnum::MULTIPLE_CHOICE,
        ]);

        $createdQuestionId = $response->json('data.id');
        $createdQuestion = Question::query()->where('id', $createdQuestionId)->first();

        foreach ($languages as $language) {
            $this->assertEquals('New Question', $createdQuestion->getTranslation('text', $language));
            $this->assertEquals('Help Text', $createdQuestion->getTranslation('help_text', $language));
        }
    }

    #[Test]
    public function it_displays_a_question(): void
    {
        $question = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);

        $this->actingAs($this->auth)
            ->getJson(route('survey.questions.show', ['question' => $question, 'financer_id' => $this->financer->id]))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'text',
                    'help_text',
                    'options',
                    'type',
                    'theme_id',
                    'financer_id',
                    'metadata',
                ],
            ]);
    }

    #[Test]
    public function it_validates_input_when_updating(): void
    {
        $theme = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);
        $question = resolve(QuestionFactory::class)->create([
            'text' => ['en-GB' => 'Old Question'],
            'financer_id' => $this->financer->id,
            'theme_id' => $theme->id,
        ]);

        $this->actingAs($this->auth)
            ->putJson(route('survey.questions.update', ['question' => $question, 'financer_id' => $this->financer->id]), [
                'text' => 'wrong text',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['text']);

        $this->assertDatabaseHas('int_survey_questions', ['id' => $question->id]);

        $question->refresh();
        $this->assertEquals('Old Question', $question->getTranslation('text', 'en-GB'));
    }

    #[Test]
    public function it_persists_changes_and_redirects_when_updating(): void
    {
        $theme = resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);

        Context::add('accessible_financers', [$this->financer->id]);

        $languages = LanguageHelper::getLanguages($this->financer->id);
        $text = [];
        $helpText = [];
        foreach ($languages as $language) {
            $text[$language] = 'Old Question';
            $helpText[$language] = 'Old Help Text';
        }

        $question = resolve(QuestionFactory::class)->create([
            'text' => $text,
            'help_text' => $helpText,
            'type' => QuestionTypeEnum::TEXT,
            'theme_id' => $theme->id,
            'financer_id' => $this->financer->id,
        ]);

        $text = [];
        $helpText = [];
        foreach ($languages as $language) {
            $text[$language] = 'New Question';
            $helpText[$language] = 'New Help Text';
        }

        $payload = [
            'text' => $text,
            'help_text' => $helpText,
            'type' => QuestionTypeEnum::MULTIPLE_CHOICE,
            'theme_id' => $theme->id,
            'financer_id' => $this->financer->id,
            'metadata' => ['category' => 'updated'],
        ];

        $response = $this->actingAs($this->auth)
            ->putJson(route('survey.questions.update', ['question' => $question, 'financer_id' => $this->financer->id]), $payload);

        $response->assertOk();

        $this->assertDatabaseHas('int_survey_questions', [
            'id' => $question->id,
            'type' => QuestionTypeEnum::MULTIPLE_CHOICE,
        ]);

        $updatedQuestion = Question::query()->where('id', $question->id)->first();

        foreach ($languages as $language) {
            $this->assertEquals('New Question', $updatedQuestion->getTranslation('text', $language));
            $this->assertEquals('New Help Text', $updatedQuestion->getTranslation('help_text', $language));
        }
    }

    #[Test]
    public function it_deletes_and_redirects(): void
    {
        $question = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->deleteJson(route('survey.questions.destroy', ['question' => $question, 'financer_id' => $this->financer->id]));

        $response->assertStatus(204);
        $this->assertSoftDeleted('int_survey_questions', ['id' => $question->id]);
    }

    #[Test]
    public function it_can_archive_a_question(): void
    {
        $question = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->postJson(route('survey.questions.archive', ['question' => $question, 'financer_id' => $this->financer->id]));

        $response->assertStatus(200);
        $this->assertNotNull($question->refresh()->archived_at);
    }

    #[Test]
    public function it_can_unarchive_a_question(): void
    {
        $question = resolve(QuestionFactory::class)->create(['financer_id' => $this->financer->id, 'archived_at' => now()]);

        $response = $this->actingAs($this->auth)
            ->postJson(route('survey.questions.unarchive', ['question' => $question, 'financer_id' => $this->financer->id]));

        $response->assertStatus(200);
        $this->assertNull($question->refresh()->archived_at);
    }
}
