<?php

declare(strict_types=1);

namespace App\Integrations\HRTools\Actions;

use App\Integrations\HRTools\Models\Link;
use App\Integrations\HRTools\Services\HRToolsLinkService;

class UpdateLinkAction
{
    public function __construct(
        private readonly HRToolsLinkService $linkService
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, string $id): Link
    {
        return $this->linkService->updateLink($data, $id);
    }
}
