<?php

declare(strict_types=1);

namespace App\Integrations\Survey\Actions\Theme;

use App\Integrations\Survey\Models\Question;
use App\Integrations\Survey\Models\Theme;
use Illuminate\Support\Facades\DB;

class UpdateThemeAction
{
    /** @param array<string, mixed> $data */
    public function execute(Theme $theme, array $data): Theme
    {
        return DB::transaction(function () use ($theme, $data) {
            $questions = $data['questions'] ?? null;
            unset($data['questions']);

            if (($data['position'] ?? null) === null && $theme->exists === false) {
                /** @var int|null $maxPosition */
                $maxPosition = Theme::where('financer_id', $theme->financer_id)->max('position');
                $data['position'] = ($maxPosition ?? 0) + 1;
            }

            $theme->fill($data);
            $theme->save();

            if ($questions !== null) {
                /** @var array<int> $questions */
                $questionIds = Question::whereIn('id', $questions)->pluck('id');

                $theme->questions()->update(['theme_id' => null]);

                Question::whereIn('id', $questionIds)->update(['theme_id' => $theme->id]);
            }

            return $theme->refresh();
        });
    }
}
