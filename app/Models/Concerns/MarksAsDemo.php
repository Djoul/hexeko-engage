<?php

namespace App\Models\Concerns;

use App\Models\DemoEntity;

trait MarksAsDemo
{
    /**
     * Tag the current model instance as demo (idempotent).
     */
    public function markAsDemo(): void
    {
        DemoEntity::firstOrCreate([
            'entity_type' => $this->getMorphClass(),
            'entity_id' => $this->getKey(),
        ]);
    }

    /**
     * Remove the demo tag (if present).
     */
    public function unmarkDemo(): void
    {
        DemoEntity::where([
            'entity_type' => $this->getMorphClass(),
            'entity_id' => $this->getKey(),
        ])->delete();
    }

    /**
     * Quick checker.
     */
    public function isDemo(): bool
    {
        return DemoEntity::where([
            'entity_type' => $this->getMorphClass(),
            'entity_id' => $this->getKey(),
        ])->exists();
    }
}
