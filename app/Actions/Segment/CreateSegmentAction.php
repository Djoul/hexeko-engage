<?php

declare(strict_types=1);

namespace App\Actions\Segment;

use App\Jobs\SyncSegmentUsersJob;
use App\Models\Segment;

class CreateSegmentAction
{
    /** @param array<string, mixed> $data */
    public function execute(array $data): Segment
    {
        $segment = new Segment;
        $segment->fill($data);
        $segment->save();

        if ($segment->computedUsers()->count() > 0) {
            SyncSegmentUsersJob::dispatch($segment);
        }

        return $segment->refresh();
    }
}
