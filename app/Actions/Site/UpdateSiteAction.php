<?php

declare(strict_types=1);

namespace App\Actions\Site;

use App\Models\Site;

class UpdateSiteAction
{
    /** @param array<string, mixed> $data */
    public function execute(Site $site, array $data): Site
    {
        $site->fill($data);
        $site->save();

        return $site->refresh();
    }
}
