<?php

namespace App\Console\Commands;

use App\Jobs\SyncSegmentUsersJob;
use App\Models\Financer;
use App\Models\Segment;
use Illuminate\Console\Command;

class SyncSegmentUsersCommand extends Command
{
    protected $signature = 'segment:sync-users {--financer=}';

    protected $description = 'Sync users to a segment';

    public function handle(): int
    {
        $financerId = $this->option('financer');

        if ($financerId !== null) {
            $financer = Financer::find($financerId);

            if (! $financer) {
                $this->error('Financer not found');

                return self::FAILURE;
            }

            $this->dispatchSyncJobsForSegments(
                $financer,
                Segment::withoutGlobalScopes()->where('financer_id', $financer->id)->get()
            );

            return self::SUCCESS;
        }

        Financer::query()
            ->where('active', true)
            ->each(function (Financer $financer): void {
                $segments = Segment::withoutGlobalScopes()
                    ->where('financer_id', $financer->id)
                    ->get();

                $this->dispatchSyncJobsForSegments($financer, $segments);
            });

        return self::SUCCESS;
    }

    /**
     * Dispatch the SyncSegmentUsersJob for each given segment.
     *
     * @param  \Illuminate\Support\Iterable|array<Segment>  $segments
     */
    protected function dispatchSyncJobsForSegments(Financer $financer, iterable $segments): void
    {
        activity('segment:sync-users')
            ->performedOn($financer)
            ->withProperties([
                'segments' => $segments,
            ])
            ->log('Dispatching SyncSegmentUsersJob for segments');

        foreach ($segments as $segment) {
            SyncSegmentUsersJob::dispatch($segment);
        }
    }
}
