<?php

declare(strict_types=1);

namespace App\Integrations\Survey\Actions\Theme;

use App\Integrations\Survey\Models\Theme;

class DeleteThemeAction
{
    public function execute(Theme $theme): bool
    {
        return $theme->delete() ?? false;
    }
}
