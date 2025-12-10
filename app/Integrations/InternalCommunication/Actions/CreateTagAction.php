<?php

declare(strict_types=1);

namespace App\Integrations\InternalCommunication\Actions;

use App\Integrations\InternalCommunication\Models\Tag;
use App\Integrations\InternalCommunication\Services\TagService;

class CreateTagAction
{
    /**
     * Constructor.
     */
    public function __construct(
        protected TagService $tagService,
    ) {}

    /**
     * Handle the action.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): Tag
    {
        return $this->tagService->create($data);
    }
}
