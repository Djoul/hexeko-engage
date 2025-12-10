<?php

declare(strict_types=1);

namespace App\Integrations\Survey\Actions\Theme;

use App\Integrations\Survey\Models\Question;
use App\Integrations\Survey\Models\Theme;
use Illuminate\Support\Facades\DB;

class SyncThemeQuestionAction
{
    /**
     * Sync questions to a theme.
     *
     * Removes all existing questions from the theme and attaches the specified questions.
     * This ensures the theme has only the specified questions.
     *
     * @param  array<string, array<int|string, array{id: string}|string>>  $data
     */
    public function execute(Theme $theme, array $data): Theme
    {
        $questions = $data['questions'] ?? [];

        return DB::transaction(function () use ($theme, $questions): Theme {
            // First, detach all existing questions from this theme
            $theme->questions()->update(['theme_id' => null]);

            // Then, attach the new questions if any
            if (! empty($questions)) {
                // Support both formats: simple IDs array or array of objects with 'id' key
                $questionIds = is_array($questions[0] ?? null)
                    ? array_column($questions, 'id')
                    : $questions;

                Question::whereIn('id', $questionIds)->update(['theme_id' => $theme->id]);
            }

            return $theme->refresh();
        });
    }
}
