<?php

declare(strict_types=1);

namespace App\Integrations\HRTools\Actions;

use App\Integrations\HRTools\Models\Link;
use App\Integrations\HRTools\Services\HRToolsLinkService;

class CreateLinkAction
{
    public function __construct(
        private readonly HRToolsLinkService $linkService
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): Link
    {
        return $this->linkService->storeLink($data);
    }
}
