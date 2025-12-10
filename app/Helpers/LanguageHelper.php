<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Enums\Languages;
use App\Models\Financer;

class LanguageHelper
{
    public static function getLanguages($financerId = null): array
    {
        $financerId = $financerId ?? request()->input('financer_id') ?? request()->header('x-financer-id');

        if ($financerId) {
            $financer = Financer::find($financerId);
            if ($financer && ! empty($financer->available_languages)) {
                return $financer->available_languages;
            }
        }

        return Languages::getValues();
    }
}
