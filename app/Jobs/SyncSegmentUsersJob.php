<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Segment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncSegmentUsersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private Segment $segment;

    /**
     * Create a new job instance.
     */
    public function __construct(Segment $segment, public int $chunkSize = 1000)
    {
        $this->segment = $segment;
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->segment->financer_id.'-sync_segment_users-'.$this->segment->id))->dontRelease()];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $segment = $this->segment;

        if (! $segment) {
            return;
        }

        $dynamicUserIds = $segment->computedUsers()->pluck('id');
        $savedUserIds = DB::table('segment_user')
            ->where('segment_id', $segment->id)
            ->pluck('user_id');

        $toAdd = $dynamicUserIds->diff($savedUserIds);
        $toRemove = $savedUserIds->diff($dynamicUserIds);

        DB::transaction(function () use ($segment, $toAdd, $toRemove): void {
            if ($toRemove->isNotEmpty()) {
                $toRemove->chunk(10000)->each(function ($chunk) use ($segment): void {
                    DB::table('segment_user')
                        ->where('segment_id', $segment->id)
                        ->whereIn('user_id', $chunk->toArray())
                        ->delete();
                });
            }

            if ($toAdd->isNotEmpty()) {
                $now = now();
                $toAdd->chunk(10000)->each(function ($chunk) use ($segment, $now): void {
                    $data = $chunk->map(fn ($id): array => [
                        'segment_id' => $segment->id,
                        'user_id' => $id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->toArray();

                    DB::table('segment_user')->insert($data);
                });
            }
        });
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Failed to attach users to segment', [
            'segment_id' => $this->segment->id,
            'error' => $exception?->getMessage(),
            'trace' => $exception?->getTraceAsString(),
        ]);
    }
}
