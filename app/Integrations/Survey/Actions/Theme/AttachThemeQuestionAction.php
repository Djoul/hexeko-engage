<?php

declare(strict_types=1);

namespace App\Integrations\Survey\Actions\Theme;

use App\Integrations\Survey\Models\Question;
use App\Integrations\Survey\Models\Theme;
use Illuminate\Support\Facades\DB;

class AttachThemeQuestionAction
{
    /**
     * Attach questions to a theme.
     *
     * Updates the theme_id field on the specified questions to link them to the theme.
     *
     * @param  array<string, array<int|string, array{id: string}|string>>  $data
     */
    public function execute(Theme $theme, array $data): Theme
    {
        $questions = $data['questions'] ?? [];

        if (empty($questions)) {
            return $theme->refresh();
        }

        return DB::transaction(function () use ($theme, $questions): Theme {
            $questionIds = is_array($questions[0] ?? null)
                ? array_column($questions, 'id')
                : $questions;

            Question::whereIn('id', $questionIds)->update(['theme_id' => $theme->id]);

            return $theme->refresh();
        });
    }
}
