<?php

declare(strict_types=1);

namespace App\Integrations\Survey\Actions\Survey;

use App\Integrations\Survey\Models\Favorite;
use App\Integrations\Survey\Models\Survey;
use Exception;
use Illuminate\Support\Facades\Auth;

class ToggleFavoriteSurveyAction
{
    public function execute(Survey $survey): Survey
    {
        $user = Auth::user();

        if ($user === null) {
            throw new Exception('User not found');
        }

        $favorite = Favorite::where('user_id', $user->id)->where('survey_id', $survey->id)->first();

        if ($favorite) {
            $favorite->delete();
        } else {
            Favorite::create([
                'user_id' => $user->id,
                'survey_id' => $survey->id,
            ]);
        }

        return $survey->refresh();
    }
}
