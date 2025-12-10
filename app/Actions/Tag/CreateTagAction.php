<?php

declare(strict_types=1);

namespace App\Actions\Tag;

use App\Models\Tag;

class CreateTagAction
{
    /** @param array<string, mixed> $data */
    public function execute(array $data): Tag
    {
        $tag = new Tag;
        $tag->fill($data);
        $tag->save();

        return $tag->refresh();
    }
}
