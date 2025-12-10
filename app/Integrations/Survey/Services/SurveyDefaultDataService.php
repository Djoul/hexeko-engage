<?php

declare(strict_types=1);

namespace App\Integrations\Survey\Services;

use Illuminate\Support\Facades\File;

class SurveyDefaultDataService
{
    private function loadJsonData(string $filename): array
    {
        $path = app_path('Integrations/Survey/Database/defaults/'.$filename);

        return json_decode(File::get($path), true);
    }

    public function getThemes(): array
    {
        return $this->loadJsonData('financer_themes.json');
    }

    public function getQuestions(): array
    {
        return $this->loadJsonData('financer_questions.json');
    }

    public function getQuestionBySlug(string $slug): ?array
    {
        $jsonData = $this->loadJsonData('financer_questions.json');
        $question = collect($jsonData)->firstWhere('slug', $slug);

        return $question ?: null;
    }

    public function getQuestionnaires(): array
    {
        return $this->loadJsonData('financer_questionnaires.json');
    }
}
