<?php

declare(strict_types=1);

namespace App\Integrations\Survey\Actions\Theme;

use App\Integrations\Survey\Models\Theme;
use App\Integrations\Survey\Services\SurveyDefaultDataService;
use App\Models\Financer;

/**
 * Creates default themes for a financer from configuration.
 */
class CreateDefaultThemesAction
{
    public function __construct(
        protected SurveyDefaultDataService $surveyDefaultDataService
    ) {}

    /**
     * Execute the action and return a mapping of theme slugs to theme IDs.
     *
     * @return array<string, string> Mapping of theme slug to theme ID
     */
    public function execute(Financer $financer): array
    {
        $themes = $this->surveyDefaultDataService->getThemes();
        $slugToThemeId = [];

        collect($themes)
            ->each(function (array $theme, int $position) use ($financer, &$slugToThemeId): void {
                $createdTheme = Theme::create([
                    'financer_id' => $financer->id,
                    'name' => $theme['name'],
                    'description' => $theme['description'],
                    'is_default' => true,
                    'position' => $position,
                ]);

                $slugToThemeId[$theme['slug']] = $createdTheme->id;
            });

        return $slugToThemeId;
    }
}
