<?php

declare(strict_types=1);

namespace App\Integrations\HRTools\Actions;

use App\Integrations\HRTools\Services\HRToolsLinkService;

class DeleteLinkAction
{
    public function __construct(
        private readonly HRToolsLinkService $linkService
    ) {}

    public function execute(string $id): bool
    {
        return $this->linkService->deleteLink($id);
    }
}
