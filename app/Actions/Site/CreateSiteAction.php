<?php

declare(strict_types=1);

namespace App\Actions\Site;

use App\Models\Site;

class CreateSiteAction
{
    /** @param array<string, mixed> $data */
    public function execute(array $data): Site
    {
        $site = new Site;
        $site->fill($data);
        $site->save();

        return $site->refresh();
    }
}
