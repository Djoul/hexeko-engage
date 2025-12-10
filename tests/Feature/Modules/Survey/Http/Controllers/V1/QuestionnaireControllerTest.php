<?php

namespace Tests\Feature\Modules\Survey\Http\Controllers\V1;

use App\Helpers\LanguageHelper;
use App\Integrations\Survey\Database\factories\QuestionnaireFactory;
use App\Integrations\Survey\Enums\QuestionnaireTypeEnum;
use App\Integrations\Survey\Models\Questionnaire;
use Database\Factories\FinancerFactory;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('survey')]
#[Group('questionnaire')]
class QuestionnaireControllerTest extends SurveyTestCase
{
    #[Test]
    public function it_can_list_questionnaires(): void
    {
        resolve(QuestionnaireFactory::class)->count(3)->create(['financer_id' => $this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('survey.questionnaires.index', ['financer_id' => $this->financer->id]));

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'name_raw',
                        'description',
                        'description_raw',
                        'financer_id',
                        'settings',
                        'is_default',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);
    }

    #[Test]
    public function it_filters_questionnaires_by_type(): void
    {
        resolve(QuestionnaireFactory::class)->create(['financer_id' => $this->financer->id, 'type' => QuestionnaireTypeEnum::NPS]);
        resolve(QuestionnaireFactory::class)->create(['financer_id' => $this->financer->id, 'type' => QuestionnaireTypeEnum::SATISFACTION]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('survey.questionnaires.index', ['type' => QuestionnaireTypeEnum::NPS, 'financer_id' => $this->financer->id]));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'name_raw',
                        'description',
                        'description_raw',
                        'financer_id',
                        'settings',
                        'is_default',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);
    }

    #[Test]
    public function it_filters_questionnaires_by_is_default(): void
    {
        resolve(QuestionnaireFactory::class)->create(['financer_id' => $this->financer->id, 'is_default' => true]);
        resolve(QuestionnaireFactory::class)->create(['financer_id' => $this->financer->id, 'is_default' => false]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('survey.questionnaires.index', ['is_default' => true, 'financer_id' => $this->financer->id]));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'name_raw',
                        'description',
                        'description_raw',
                        'financer_id',
                        'settings',
                        'is_default',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);
    }

    #[Test]
    public function it_filters_questionnaires_by_financer_id(): void
    {
        $otherFinancer = resolve(FinancerFactory::class)->create();

        resolve(QuestionnaireFactory::class)->create(['financer_id' => $this->financer->id]);
        resolve(QuestionnaireFactory::class)->create(['financer_id' => $otherFinancer->id]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('survey.questionnaires.index', ['financer_id' => $this->financer->id]));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'name_raw',
                        'description',
                        'description_raw',
                        'financer_id',
                        'settings',
                        'is_default',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);
    }

    #[Test]
    public function it_filters_questionnaires_by_created_at_date(): void
    {
        resolve(QuestionnaireFactory::class)->create(['financer_id' => $this->financer->id, 'created_at' => '2023-01-01']);
        resolve(QuestionnaireFactory::class)->create(['financer_id' => $this->financer->id, 'created_at' => '2024-01-02']);
        resolve(QuestionnaireFactory::class)->create(['financer_id' => $this->financer->id, 'created_at' => '2024-01-03']);
        resolve(QuestionnaireFactory::class)->create(['financer_id' => $this->financer->id, 'created_at' => '2024-01-04']);

        $response = $this->actingAs($this->auth)->getJson(route('survey.questionnaires.index', [
            'financer_id' => $this->financer->id,
            'date_from' => '2024-01-01',
            'date_from_fields' => ['created_at'],
        ]));

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    #[Test]
    public function it_filters_questionnaires_by_date_from(): void
    {
        resolve(QuestionnaireFactory::class)->create(['financer_id' => $this->financer->id, 'created_at' => '2024-01-01']);
        resolve(QuestionnaireFactory::class)->create(['financer_id' => $this->financer->id, 'created_at' => '2024-01-02']);
        resolve(QuestionnaireFactory::class)->create(['financer_id' => $this->financer->id, 'created_at' => '2024-01-03']);
        resolve(QuestionnaireFactory::class)->create(['financer_id' => $this->financer->id, 'created_at' => '2024-01-04']);

        // Filter by created_at field (default)
        $response = $this->actingAs($this->auth)->getJson(route('survey.questionnaires.index', [
            'date_from' => '2024-01-01',
            'financer_id' => $this->financer->id,
        ]));
        $response->assertOk()->assertJsonCount(4, 'data');

        // Filter by created_at field with later date
        $response = $this->actingAs($this->auth)->getJson(route('survey.questionnaires.index', [
            'financer_id' => $this->financer->id,
            'date_from' => '2024-01-03',
            'date_from_fields' => 'created_at',
        ]));
        $response->assertOk()->assertJsonCount(2, 'data');

        // Filter by multiple date fields
        resolve(QuestionnaireFactory::class)->create(['financer_id' => $this->financer->id, 'updated_at' => '2023-03-01']);
        $response = $this->actingAs($this->auth)->getJson(route('survey.questionnaires.index', [
            'financer_id' => $this->financer->id,
            'date_from' => '2023-03-01',
            'date_from_fields' => ['created_at', 'updated_at'],
        ]));
        $response->assertOk()->assertJsonCount(5, 'data');
    }

    #[Test]
    public function it_filters_questionnaires_by_date_to(): void
    {
        resolve(QuestionnaireFactory::class)->create(['financer_id' => $this->financer->id, 'updated_at' => '2023-01-31']);
        resolve(QuestionnaireFactory::class)->create(['financer_id' => $this->financer->id, 'updated_at' => '2023-02-28']);
        resolve(QuestionnaireFactory::class)->create(['financer_id' => $this->financer->id, 'updated_at' => '2023-02-01']);
        resolve(QuestionnaireFactory::class)->create(['financer_id' => $this->financer->id, 'updated_at' => '2023-01-01']);

        // Filter by updated_at field specifically
        $response = $this->actingAs($this->auth)->getJson(route('survey.questionnaires.index', [
            'financer_id' => $this->financer->id,
            'date_to' => '2023-01-31',
            'date_to_fields' => 'updated_at',
        ]));
        $response->assertOk()->assertJsonCount(2, 'data');

        $response = $this->actingAs($this->auth)->getJson(route('survey.questionnaires.index', [
            'financer_id' => $this->financer->id,
            'date_to' => '2023-02-28',
            'date_to_fields' => 'updated_at',
        ]));
        $response->assertOk()->assertJsonCount(4, 'data');

        $response = $this->actingAs($this->auth)->getJson(route('survey.questionnaires.index', [
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
            ->postJson(route('survey.questionnaires.store', ['financer_id' => $this->financer->id]), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    #[Test]
    public function it_persists_and_redirects_when_storing(): void
    {

        $languages = LanguageHelper::getLanguages($this->financer->id);
        $name = [];
        $description = [];
        $instructions = [];
        foreach ($languages as $language) {
            $name[$language] = 'New Questionnaire';
            $description[$language] = 'New Questionnaire Description';
            $instructions[$language] = 'New Questionnaire Instructions';
        }

        $payload = [
            'name' => $name,
            'description' => $description,
            'financer_id' => $this->financer->id,
            'settings' => ['allow_multiple_responses' => true],
            'is_default' => false,
        ];

        $response = $this->actingAs($this->auth)
            ->postJson(route('survey.questionnaires.store', ['financer_id' => $this->financer->id]), $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('int_survey_questionnaires', [
            'financer_id' => $this->financer->id,
            'is_default' => false,
        ]);

        $createdQuestionnaireId = $response->json('data.id');
        $createdQuestionnaire = Questionnaire::query()->where('id', $createdQuestionnaireId)->first();

        foreach ($languages as $language) {
            $this->assertEquals('New Questionnaire', $createdQuestionnaire->getTranslation('name', $language));
            $this->assertEquals('New Questionnaire Description', $createdQuestionnaire->getTranslation('description', $language));
        }
    }

    #[Test]
    public function it_displays_a_questionnaire(): void
    {
        $questionnaire = resolve(QuestionnaireFactory::class)->create(['financer_id' => $this->financer->id]);

        $this->actingAs($this->auth)
            ->getJson(route('survey.questionnaires.show', ['questionnaire' => $questionnaire, 'financer_id' => $this->financer->id]))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'name_raw',
                    'description',
                    'description_raw',
                    'financer_id',
                    'settings',
                    'is_default',
                    'created_at',
                    'updated_at',
                ],
            ]);
    }

    #[Test]
    public function it_validates_input_when_updating(): void
    {
        $questionnaire = resolve(QuestionnaireFactory::class)->create([
            'name' => ['en-GB' => 'Old Questionnaire'],
            'financer_id' => $this->financer->id,
        ]);

        $languages = LanguageHelper::getLanguages($this->financer->id);
        $description = [];
        $instructions = [];
        foreach ($languages as $language) {
            $description[$language] = 'Valid Description';
            $instructions[$language] = 'Valid Instructions';
        }

        $this->actingAs($this->auth)
            ->putJson(route('survey.questionnaires.update', ['questionnaire' => $questionnaire, 'financer_id' => $this->financer->id]), [
                'name' => [],
                'description' => $description,
                'financer_id' => $this->financer->id,
                'settings' => $questionnaire->settings,
                'is_default' => $questionnaire->is_default,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        $this->assertDatabaseHas('int_survey_questionnaires', ['id' => $questionnaire->id]);

        $questionnaire->refresh();
        $this->assertEquals('Old Questionnaire', $questionnaire->getTranslation('name', 'en-GB'));
    }

    #[Test]
    public function it_persists_changes_and_redirects_when_updating(): void
    {

        Context::add('accessible_financers', [$this->financer->id]);

        $languages = LanguageHelper::getLanguages($this->financer->id);
        $name = [];
        $description = [];
        foreach ($languages as $language) {
            $name[$language] = 'Old Questionnaire';
            $description[$language] = 'Old Description';
        }

        $questionnaire = resolve(QuestionnaireFactory::class)->create([
            'name' => $name,
            'description' => $description,
            'financer_id' => $this->financer->id,
        ]);

        $name = [];
        $description = [];
        foreach ($languages as $language) {
            $name[$language] = 'New Questionnaire';
            $description[$language] = 'New Description';
        }

        $payload = [
            'name' => $name,
            'description' => $description,
            'financer_id' => $this->financer->id,
            'settings' => ['allow_multiple_responses' => false],
        ];

        $response = $this->actingAs($this->auth)
            ->putJson(route('survey.questionnaires.update', ['questionnaire' => $questionnaire, 'financer_id' => $this->financer->id]), $payload);

        $response->assertOk();

        $this->assertDatabaseHas('int_survey_questionnaires', [
            'id' => $questionnaire->id,
        ]);

        $updatedQuestionnaire = Questionnaire::query()->where('id', $questionnaire->id)->first();

        foreach ($languages as $language) {
            $this->assertEquals('New Questionnaire', $updatedQuestionnaire->getTranslation('name', $language));
            $this->assertEquals('New Description', $updatedQuestionnaire->getTranslation('description', $language));
        }
    }

    #[Test]
    public function it_deletes_and_redirects(): void
    {
        $questionnaire = resolve(QuestionnaireFactory::class)->create(['financer_id' => $this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->deleteJson(route('survey.questionnaires.destroy', ['questionnaire' => $questionnaire, 'financer_id' => $this->financer->id]));

        $response->assertStatus(204);
        $this->assertSoftDeleted('int_survey_questionnaires', ['id' => $questionnaire->id]);
    }

    #[Test]
    public function it_can_archive_a_questionnaire(): void
    {
        $questionnaire = resolve(QuestionnaireFactory::class)->create(['financer_id' => $this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->postJson(route('survey.questionnaires.archive', ['questionnaire' => $questionnaire, 'financer_id' => $this->financer->id]));

        $response->assertStatus(200);
        $this->assertNotNull($questionnaire->refresh()->archived_at);
    }

    #[Test]
    public function it_can_unarchive_a_questionnaire(): void
    {
        $questionnaire = resolve(QuestionnaireFactory::class)->create(['financer_id' => $this->financer->id, 'archived_at' => now()]);

        $response = $this->actingAs($this->auth)
            ->postJson(route('survey.questionnaires.unarchive', ['questionnaire' => $questionnaire, 'financer_id' => $this->financer->id]));

        $response->assertStatus(200);
        $this->assertNull($questionnaire->refresh()->archived_at);
    }

    #[Test]
    public function it_can_create_a_draft_questionnaire(): void
    {
        $response = $this->actingAs($this->auth)
            ->postJson(route('survey.questionnaires.draft', ['financer_id' => $this->financer->id]));

        $response->assertStatus(201);

        $this->assertDatabaseHas('int_survey_questionnaires', [
            'financer_id' => $this->financer->id,
        ]);

        $response->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'description',
                'financer_id',
                'questions_count',
            ],
        ]);
    }

    #[Test]
    public function it_validates_draft_questionnaire_input(): void
    {
        $this->actingAs($this->auth)
            ->postJson(route('survey.questionnaires.draft', ['financer_id' => $this->financer->id]))
            ->assertStatus(201); // nothing to validate
    }
}
