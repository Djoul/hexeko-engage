<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Survey\Http\Controllers\V1\Me;

use App\Enums\IDP\PermissionDefaults;
use App\Integrations\Survey\Database\factories\QuestionFactory;
use App\Integrations\Survey\Database\factories\SurveyFactory;
use App\Integrations\Survey\Database\factories\ThemeFactory;
use App\Integrations\Survey\Models\Question;
use App\Integrations\Survey\Models\Survey;
use App\Integrations\Survey\Models\Theme;
use App\Models\Permission;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Modules\Survey\Http\Controllers\V1\SurveyTestCase;

#[Group('survey')]
#[Group('theme')]
class ThemeControllerTest extends SurveyTestCase
{
    #[Test]
    public function it_can_list_themes_with_active_survey_questions(): void
    {
        // Create READ_THEME permission and assign to user
        Permission::firstOrCreate([
            'name' => PermissionDefaults::READ_THEME,
            'guard_name' => 'api',
        ]);
        $this->auth->givePermissionTo(PermissionDefaults::READ_THEME);

        // Create an active survey for the user
        /** @var Survey $activeSurvey */
        $activeSurvey = resolve(SurveyFactory::class)->active()->create([
            'financer_id' => $this->financer->id,
        ]);

        // Attach the survey to the user
        $this->auth->surveys()->attach($activeSurvey->id);

        // Create a theme with a question
        /** @var Theme $theme */
        $theme = resolve(ThemeFactory::class)->create([
            'financer_id' => $this->financer->id,
        ]);

        /** @var Question $question */
        $question = resolve(QuestionFactory::class)->create([
            'financer_id' => $this->financer->id,
            'theme_id' => $theme->id,
        ]);

        // Attach the question to the active survey
        $activeSurvey->questions()->attach($question->id, ['position' => 1]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('me.survey.themes.index', ['financer_id' => $this->financer->id]));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'description',
                        'financer_id',
                        'is_default',
                        'position',
                        'created_at',
                        'updated_at',
                    ],
                ],
                'links',
                'meta',
            ]);
    }

    #[Test]
    public function it_does_not_list_themes_without_active_survey_questions(): void
    {
        // Create READ_THEME permission and assign to user
        Permission::firstOrCreate([
            'name' => PermissionDefaults::READ_THEME,
            'guard_name' => 'api',
        ]);
        $this->auth->givePermissionTo(PermissionDefaults::READ_THEME);

        // Create a theme with a question but no active survey
        /** @var Theme $theme */
        $theme = resolve(ThemeFactory::class)->create([
            'financer_id' => $this->financer->id,
        ]);

        resolve(QuestionFactory::class)->create([
            'financer_id' => $this->financer->id,
            'theme_id' => $theme->id,
        ]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('me.survey.themes.index', ['financer_id' => $this->financer->id]));

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function it_does_not_list_themes_with_inactive_survey_questions(): void
    {
        // Create READ_THEME permission and assign to user
        Permission::firstOrCreate([
            'name' => PermissionDefaults::READ_THEME,
            'guard_name' => 'api',
        ]);
        $this->auth->givePermissionTo(PermissionDefaults::READ_THEME);

        // Create a draft (inactive) survey
        /** @var Survey $draftSurvey */
        $draftSurvey = resolve(SurveyFactory::class)->draft()->create([
            'financer_id' => $this->financer->id,
        ]);

        $this->auth->surveys()->attach($draftSurvey->id);

        // Create a theme with a question
        /** @var Theme $theme */
        $theme = resolve(ThemeFactory::class)->create([
            'financer_id' => $this->financer->id,
        ]);

        /** @var Question $question */
        $question = resolve(QuestionFactory::class)->create([
            'financer_id' => $this->financer->id,
            'theme_id' => $theme->id,
        ]);

        // Attach the question to the draft survey
        $draftSurvey->questions()->attach($question->id, ['position' => 1]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('me.survey.themes.index', ['financer_id' => $this->financer->id]));

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function it_only_shows_themes_from_users_active_surveys(): void
    {
        // Create READ_THEME permission and assign to user
        Permission::firstOrCreate([
            'name' => PermissionDefaults::READ_THEME,
            'guard_name' => 'api',
        ]);
        $this->auth->givePermissionTo(PermissionDefaults::READ_THEME);

        // Create two active surveys
        /** @var Survey $userSurvey */
        $userSurvey = resolve(SurveyFactory::class)->active()->create([
            'financer_id' => $this->financer->id,
        ]);

        /** @var Survey $otherSurvey */
        $otherSurvey = resolve(SurveyFactory::class)->active()->create([
            'financer_id' => $this->financer->id,
        ]);

        // Only attach the first survey to the user
        $this->auth->surveys()->attach($userSurvey->id);

        // Create two themes with questions
        /** @var Theme $userTheme */
        $userTheme = resolve(ThemeFactory::class)->create([
            'financer_id' => $this->financer->id,
        ]);

        /** @var Theme $otherTheme */
        $otherTheme = resolve(ThemeFactory::class)->create([
            'financer_id' => $this->financer->id,
        ]);

        // Create questions for each theme
        /** @var Question $userQuestion */
        $userQuestion = resolve(QuestionFactory::class)->create([
            'financer_id' => $this->financer->id,
            'theme_id' => $userTheme->id,
        ]);

        /** @var Question $otherQuestion */
        $otherQuestion = resolve(QuestionFactory::class)->create([
            'financer_id' => $this->financer->id,
            'theme_id' => $otherTheme->id,
        ]);

        // Attach questions to surveys
        $userSurvey->questions()->attach($userQuestion->id, ['position' => 1]);
        $otherSurvey->questions()->attach($otherQuestion->id, ['position' => 1]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('me.survey.themes.index', ['financer_id' => $this->financer->id]));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['id' => $userTheme->id])
            ->assertJsonMissing(['id' => $otherTheme->id]);
    }

    #[Test]
    public function it_filters_themes_by_financer_id(): void
    {
        // Create READ_THEME permission and assign to user
        Permission::firstOrCreate([
            'name' => PermissionDefaults::READ_THEME,
            'guard_name' => 'api',
        ]);
        $this->auth->givePermissionTo(PermissionDefaults::READ_THEME);

        // Create an active survey for the user
        /** @var Survey $activeSurvey */
        $activeSurvey = resolve(SurveyFactory::class)->active()->create([
            'financer_id' => $this->financer->id,
        ]);

        $this->auth->surveys()->attach($activeSurvey->id);

        // Create themes with both financer_id and null (system themes)
        /** @var Theme $financerTheme */
        $financerTheme = resolve(ThemeFactory::class)->create([
            'financer_id' => $this->financer->id,
        ]);

        /** @var Theme $systemTheme */
        $systemTheme = resolve(ThemeFactory::class)->create([
            'financer_id' => null,
        ]);

        // Create questions for each theme
        /** @var Question $financerQuestion */
        $financerQuestion = resolve(QuestionFactory::class)->create([
            'financer_id' => $this->financer->id,
            'theme_id' => $financerTheme->id,
        ]);

        /** @var Question $systemQuestion */
        $systemQuestion = resolve(QuestionFactory::class)->create([
            'financer_id' => $this->financer->id,
            'theme_id' => $systemTheme->id,
        ]);

        // Attach questions to the active survey
        $activeSurvey->questions()->attach($financerQuestion->id, ['position' => 1]);
        $activeSurvey->questions()->attach($systemQuestion->id, ['position' => 2]);

        // Filter by financer_id
        $response = $this->actingAs($this->auth)
            ->getJson(route('me.survey.themes.index', ['financer_id' => $this->financer->id]));

        $response->assertStatus(200);

        // Should show both financer and system themes when filtered by financer_id
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    #[Test]
    public function it_returns_paginated_results(): void
    {
        // Create READ_THEME permission and assign to user
        Permission::firstOrCreate([
            'name' => PermissionDefaults::READ_THEME,
            'guard_name' => 'api',
        ]);
        $this->auth->givePermissionTo(PermissionDefaults::READ_THEME);

        // Create an active survey for the user
        /** @var Survey $activeSurvey */
        $activeSurvey = resolve(SurveyFactory::class)->active()->create([
            'financer_id' => $this->financer->id,
        ]);

        $this->auth->surveys()->attach($activeSurvey->id);

        // Create multiple themes with questions
        for ($i = 0; $i < 5; $i++) {
            /** @var Theme $theme */
            $theme = resolve(ThemeFactory::class)->create([
                'financer_id' => $this->financer->id,
            ]);

            /** @var Question $question */
            $question = resolve(QuestionFactory::class)->create([
                'financer_id' => $this->financer->id,
                'theme_id' => $theme->id,
            ]);

            $activeSurvey->questions()->attach($question->id, ['position' => $i + 1]);
        }

        $response = $this->actingAs($this->auth)
            ->getJson(route('me.survey.themes.index', [
                'financer_id' => $this->financer->id,
                'per_page' => 3,
            ]));

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data',
                'links' => [
                    'first',
                    'last',
                    'prev',
                    'next',
                ],
                'meta' => [
                    'current_page',
                    'from',
                    'last_page',
                    'per_page',
                    'to',
                    'total',
                ],
            ]);
    }

    #[Test]
    public function it_shows_theme_with_multiple_questions_in_active_survey(): void
    {
        // Create READ_THEME permission and assign to user
        Permission::firstOrCreate([
            'name' => PermissionDefaults::READ_THEME,
            'guard_name' => 'api',
        ]);
        $this->auth->givePermissionTo(PermissionDefaults::READ_THEME);

        // Create an active survey for the user
        /** @var Survey $activeSurvey */
        $activeSurvey = resolve(SurveyFactory::class)->active()->create([
            'financer_id' => $this->financer->id,
        ]);

        $this->auth->surveys()->attach($activeSurvey->id);

        // Create a theme with multiple questions
        /** @var Theme $theme */
        $theme = resolve(ThemeFactory::class)->create([
            'financer_id' => $this->financer->id,
        ]);

        for ($i = 0; $i < 3; $i++) {
            /** @var Question $question */
            $question = resolve(QuestionFactory::class)->create([
                'financer_id' => $this->financer->id,
                'theme_id' => $theme->id,
            ]);

            $activeSurvey->questions()->attach($question->id, ['position' => $i + 1]);
        }

        Context::add('accessible_financers', [$this->financer->id]);

        $response = $this->actingAs($this->auth)
            ->getJson(route('me.survey.themes.index', ['financer_id' => $this->financer->id]));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['id' => $theme->id]);
    }
}
