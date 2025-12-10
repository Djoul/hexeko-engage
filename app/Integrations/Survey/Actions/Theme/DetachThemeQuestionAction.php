<?php

declare(strict_types=1);

namespace App\Integrations\Survey\Actions\Theme;

use App\Integrations\Survey\Models\Question;
use App\Integrations\Survey\Models\Theme;
use Illuminate\Support\Facades\DB;

class DetachThemeQuestionAction
{
    /**
     * Detach questions from a theme.
     *
     * Updates the theme_id field to null on the specified questions to unlink them from the theme.
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
            // Support both formats: simple IDs array or array of objects with 'id' key
            $questionIds = is_array($questions[0] ?? null)
                ? array_column($questions, 'id')
                : $questions;

            Question::whereIn('id', $questionIds)->update(['theme_id' => null]);

            return $theme->refresh();
        });
    }
}
