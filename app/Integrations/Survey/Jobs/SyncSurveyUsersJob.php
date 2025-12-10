<?php

declare(strict_types=1);

namespace App\Integrations\Survey\Jobs;

use App\Integrations\Survey\Models\Survey;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncSurveyUsersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private Survey $survey;

    /**
     * Create a new job instance.
     */
    public function __construct(Survey $survey, public int $chunkSize = 1000)
    {
        $this->survey = $survey;
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->survey->financer_id.'-sync_survey_users-'.$this->survey->id))->dontRelease()];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $survey = $this->survey;

        if (! $survey) {
            return;
        }

        if ($survey->segment === null) {
            $dynamicUserIds = User::query()->whereHas('financers', function ($query) use ($survey): void {
                $query->where('financer_id', $survey->financer_id);
            })->pluck('id');
        } else {
            $dynamicUserIds = $survey->segment->users()->pluck('id');
        }

        $savedUserIds = DB::table('int_survey_survey_user')
            ->where('survey_id', $survey->id)
            ->pluck('user_id');

        $toAdd = $dynamicUserIds->diff($savedUserIds);
        $toRemove = $savedUserIds->diff($dynamicUserIds);

        DB::transaction(function () use ($survey, $toAdd, $toRemove): void {
            if ($toRemove->isNotEmpty()) {
                $toRemove->chunk(1000)->each(function ($chunk) use ($survey): void {
                    DB::table('int_survey_survey_user')
                        ->where('survey_id', $survey->id)
                        ->whereIn('user_id', $chunk->toArray())
                        ->delete();
                });
            }

            if ($toAdd->isNotEmpty()) {
                $now = now();
                $toAdd->chunk(1000)->each(function ($chunk) use ($survey, $now): void {
                    $data = $chunk->map(fn ($id): array => [
                        'survey_id' => $survey->id,
                        'user_id' => $id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->toArray();

                    DB::table('int_survey_survey_user')->insert($data);
                });
            }

            $survey->update([
                'users_count' => $survey->users()->count(),
            ]);
        });
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Failed to attach users to survey', [
            'survey_id' => $this->survey->id,
            'error' => $exception?->getMessage(),
            'trace' => $exception?->getTraceAsString(),
        ]);
    }
}
