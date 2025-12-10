<?php

namespace Tests\Feature\Modules\Survey\Http\Controllers\V1;

use App\Helpers\LanguageHelper;
use App\Integrations\Survey\Database\factories\ThemeFactory;
use App\Integrations\Survey\Models\Theme;
use Database\Factories\FinancerFactory;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('survey')]
class ThemeControllerTest extends SurveyTestCase
{
    #[Test]
    public function it_can_list_themes(): void
    {
        resolve(ThemeFactory::class)->count(3)->create(['financer_id' => $this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('survey.themes.index', ['financer_id' => $this->financer->id]));

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
                        'is_default',
                        'position',
                        'question_count',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);
    }

    #[Test]
    public function it_filters_themes_by_is_default(): void
    {

        resolve(ThemeFactory::class)->create(['is_default' => true, 'financer_id' => $this->financer->id]);
        resolve(ThemeFactory::class)->create(['is_default' => false, 'financer_id' => $this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('survey.themes.index', [
                'financer_id' => $this->financer->id,
                'is_default' => true,
            ]));

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
                        'is_default',
                        'position',
                        'question_count',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);
    }

    #[Test]
    public function it_filters_themes_by_name(): void
    {

        resolve(ThemeFactory::class)->create([
            'name' => ['en-GB' => 'Theme 1 hello here', 'fr-FR' => 'Thème 1 hello ici'],
            'financer_id' => $this->financer->id,
        ]);
        resolve(ThemeFactory::class)->create([
            'name' => ['en-GB' => 'Theme 2', 'fr-FR' => 'Thème 2'],
            'financer_id' => $this->financer->id,
        ]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('survey.themes.index', [
                'financer_id' => $this->financer->id,
                'name' => 'hello',
            ]));

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
                        'is_default',
                        'position',
                        'question_count',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);
    }

    #[Test]
    public function it_filters_themes_by_description(): void
    {

        resolve(ThemeFactory::class)->create([
            'description' => ['en-GB' => 'Theme 1 hello here', 'fr-FR' => 'Thème 1 hello ici'],
            'financer_id' => $this->financer->id,
        ]);
        resolve(ThemeFactory::class)->create([
            'description' => ['en-GB' => 'Theme 2', 'fr-FR' => 'Thème 2'],
            'financer_id' => $this->financer->id,
        ]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('survey.themes.index', [
                'financer_id' => $this->financer->id,
                'description' => 'ici',
            ]));

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
                        'is_default',
                        'position',
                        'question_count',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);
    }

    #[Test]
    public function it_filters_themes_by_financer_id(): void
    {
        $otherFinancer = resolve(FinancerFactory::class)->create();
        $systemFinancer = resolve(FinancerFactory::class)->create(['id' => null]);

        Context::add('accessible_financers', [$this->financer->id, $otherFinancer->id, $systemFinancer->id]);

        resolve(ThemeFactory::class)->create(['financer_id' => $this->financer->id]);
        resolve(ThemeFactory::class)->create(['financer_id' => $otherFinancer->id]);
        resolve(ThemeFactory::class)->create(['financer_id' => $systemFinancer->id]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('survey.themes.index', ['financer_id' => $this->financer->id]));

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
                        'is_default',
                        'position',
                        'question_count',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);
    }

    #[Test]
    public function it_filters_themes_by_created_at_date(): void
    {

        resolve(ThemeFactory::class)->create(['created_at' => '2023-01-01', 'financer_id' => $this->financer->id]);
        resolve(ThemeFactory::class)->create(['created_at' => '2024-01-02', 'financer_id' => $this->financer->id]);
        resolve(ThemeFactory::class)->create(['created_at' => '2024-01-03', 'financer_id' => $this->financer->id]);
        resolve(ThemeFactory::class)->create(['created_at' => '2024-01-04', 'financer_id' => $this->financer->id]);

        $response = $this->actingAs($this->auth)->getJson(route('survey.themes.index', [
            'financer_id' => $this->financer->id,
            'date_from' => '2024-01-01',
            'date_from_fields' => ['created_at'],
        ]));

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    #[Test]
    public function it_filters_themes_by_date_from(): void
    {

        resolve(ThemeFactory::class)->create(['created_at' => '2024-01-01', 'financer_id' => $this->financer->id]);
        resolve(ThemeFactory::class)->create(['created_at' => '2024-01-02', 'financer_id' => $this->financer->id]);
        resolve(ThemeFactory::class)->create(['created_at' => '2024-01-03', 'financer_id' => $this->financer->id]);
        resolve(ThemeFactory::class)->create(['created_at' => '2024-01-04', 'financer_id' => $this->financer->id]);

        // Filter by created_at field (default)
        $response = $this->actingAs($this->auth)->getJson(route('survey.themes.index', [
            'financer_id' => $this->financer->id,
            'date_from' => '2024-01-01',
        ]));
        $response->assertOk()->assertJsonCount(4, 'data');

        // Filter by created_at field with later date
        $response = $this->actingAs($this->auth)->getJson(route('survey.themes.index', [
            'financer_id' => $this->financer->id,
            'date_from' => '2024-01-03',
            'date_from_fields' => 'created_at',
        ]));
        $response->assertOk()->assertJsonCount(2, 'data');

        // Filter by multiple date fields
        resolve(ThemeFactory::class)->create(['updated_at' => '2023-03-01', 'financer_id' => $this->financer->id]);
        $response = $this->actingAs($this->auth)->getJson(route('survey.themes.index', [
            'financer_id' => $this->financer->id,
            'date_from' => '2023-03-01',
            'date_from_fields' => ['created_at', 'updated_at'],
        ]));
        $response->assertOk()->assertJsonCount(5, 'data');
    }

    #[Test]
    public function it_filters_themes_by_date_to(): void
    {

        resolve(ThemeFactory::class)->create(['updated_at' => '2023-01-31', 'financer_id' => $this->financer->id]);
        resolve(ThemeFactory::class)->create(['updated_at' => '2023-02-28', 'financer_id' => $this->financer->id]);
        resolve(ThemeFactory::class)->create(['updated_at' => '2023-02-01', 'financer_id' => $this->financer->id]);
        resolve(ThemeFactory::class)->create(['updated_at' => '2023-01-01', 'financer_id' => $this->financer->id]);

        // Filter by updated_at field specifically
        $response = $this->actingAs($this->auth)->getJson(route('survey.themes.index', [
            'financer_id' => $this->financer->id,
            'date_to' => '2023-01-31',
            'date_to_fields' => 'updated_at',
        ]));
        $response->assertOk()->assertJsonCount(2, 'data');

        $response = $this->actingAs($this->auth)->getJson(route('survey.themes.index', [
            'financer_id' => $this->financer->id,
            'date_to' => '2023-02-28',
            'date_to_fields' => 'updated_at',
        ]));
        $response->assertOk()->assertJsonCount(4, 'data');

        $response = $this->actingAs($this->auth)->getJson(route('survey.themes.index', [
            'financer_id' => $this->financer->id,
            'date_to' => '2023-02-01',
            'date_to_fields' => ['created_at', 'updated_at'],
        ]));
        $response->assertOk()->assertJsonCount(3, 'data');
    }

    #[Test]
    public function it_returns_no_themes_if_filters_do_not_match(): void
    {

        $response = $this->actingAs($this->auth)->getJson(route('survey.themes.index', [
            'financer_id' => $this->financer->id,
            'name' => 'Symfony',
        ]));

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function it_store_validates_input(): void
    {
        $this->actingAs($this->auth)
            ->postJson(route('survey.themes.store', ['financer_id' => $this->financer->id]), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'description']);
    }

    #[Test]
    public function it_persists_and_redirects_when_storing(): void
    {

        $languages = LanguageHelper::getLanguages($this->financer->id);
        $name = [];
        $description = [];
        foreach ($languages as $language) {
            $name[$language] = 'Dark';
            $description[$language] = 'Theme sombre';
        }

        $payload = [
            'name' => $name,
            'description' => $description,
            'financer_id' => $this->financer->id,
        ];

        $response = $this->actingAs($this->auth)
            ->postJson(route('survey.themes.store', ['financer_id' => $this->financer->id]), $payload);

        $response->assertStatus(201);

        // Vérifier que le thème a été créé avec les bonnes données
        $this->assertDatabaseHas('int_survey_themes', [
            'financer_id' => $this->financer->id,
        ]);

        // Vérifier les champs traduits
        $createdTheme = Theme::where('financer_id', $this->financer->id)->first();

        // Vérifier les traductions individuelles
        foreach ($languages as $language) {
            $this->assertEquals('Dark', $createdTheme->getTranslation('name', $language));
            $this->assertEquals('Theme sombre', $createdTheme->getTranslation('description', $language));
        }
    }

    #[Test]
    public function it_displays_a_theme(): void
    {
        $theme = resolve(ThemeFactory::class)->create(['financer_id' => $this->auth->financers()->first()->id]);

        $this->actingAs($this->auth)
            ->getJson(route('survey.themes.show', ['theme' => $theme, 'financer_id' => $this->financer->id]))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'description',
                    'financer_id',
                ],
            ]);
    }

    #[Test]
    public function it_validates_input_when_updating(): void
    {
        $theme = resolve(ThemeFactory::class)->create(['name' => 'Old', 'financer_id' => $this->auth->financers()->first()->id]);

        $this->actingAs($this->auth)
            ->putJson(route('survey.themes.update', ['theme' => $theme, 'financer_id' => $this->financer->id]), ['name' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        $this->assertDatabaseHas('int_survey_themes', ['id' => $theme->id]);

        $theme->refresh();
        $this->assertEquals('Old', $theme->name);
    }

    #[Test]
    public function it_persists_changes_and_redirects_when_updating(): void
    {

        $languages = LanguageHelper::getLanguages($this->financer->id);
        $name = [];
        $description = [];
        foreach ($languages as $language) {
            $name[$language] = 'Old';
            $description[$language] = 'Ancienne';
        }

        $theme = resolve(ThemeFactory::class)->create([
            'name' => $name,
            'description' => $description,
            'financer_id' => $this->financer->id,
            'position' => 9,
        ]);

        $name = [];
        $description = [];
        foreach ($languages as $language) {
            $name[$language] = 'New';
            $description[$language] = 'Nouveau';
        }

        $payload = [
            'name' => $name,
            'description' => $description,
            'financer_id' => $this->financer->id,
        ];

        $response = $this->actingAs($this->auth)
            ->putJson(route('survey.themes.update', ['theme' => $theme, 'financer_id' => $this->financer->id]), $payload);

        $response->assertOk();

        $this->assertDatabaseHas('int_survey_themes', [
            'id' => $theme->id,
            'position' => 9,
        ]);

        $updatedTheme = Theme::query()->where('id', $theme->id)->first();

        foreach ($languages as $language) {
            $this->assertEquals('New', $updatedTheme->getTranslation('name', $language));
            $this->assertEquals('Nouveau', $updatedTheme->getTranslation('description', $language));
        }
    }

    #[Test]
    public function it_deletes_and_redirects(): void
    {
        $theme = resolve(ThemeFactory::class)->create(['financer_id' => $this->auth->financers()->first()->id]);

        $response = $this->actingAs($this->auth)
            ->deleteJson(route('survey.themes.destroy', ['theme' => $theme, 'financer_id' => $this->financer->id]));

        $response->assertStatus(204);
        $this->assertSoftDeleted('int_survey_themes', ['id' => $theme->id]);
    }
}
