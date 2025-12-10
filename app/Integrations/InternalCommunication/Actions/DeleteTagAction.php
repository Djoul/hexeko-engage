<?php

declare(strict_types=1);

namespace App\Integrations\InternalCommunication\Actions;

use App\Integrations\InternalCommunication\Models\Tag;
use App\Integrations\InternalCommunication\Services\TagService;

class DeleteTagAction
{
    /**
     * Constructor.
     */
    public function __construct(
        protected TagService $tagService,
    ) {}

    /**
     * Handle the action.
     */
    public function handle(Tag $tag): bool
    {
        return $this->tagService->delete($tag);
    }
}
