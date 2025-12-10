<?php

declare(strict_types=1);

namespace App\Integrations\HRTools\Actions;

use App\Integrations\HRTools\Services\HRToolsLinkService;

class ReorderLinksAction
{
    public function __construct(
        private readonly HRToolsLinkService $linkService
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $links
     */
    public function execute(array $links): bool
    {
        return $this->linkService->reorderLinks($links);
    }
}
