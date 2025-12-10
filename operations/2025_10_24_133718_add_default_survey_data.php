<?php

use App\Integrations\Survey\Actions\CreateDefaultSurveyDataAction;
use App\Integrations\Survey\Models\Theme;
use App\Models\Financer;
use TimoKoerber\LaravelOneTimeOperations\OneTimeOperation;

return new class extends OneTimeOperation
{
    /**
     * Determine if the operation is being processed asynchronously.
     */
    protected bool $async = true;

    /**
     * The queue that the job will be dispatched to.
     */
    protected string $queue = 'default';

    /**
     * A tag name, that this operation can be filtered by.
     */
    protected ?string $tag = null;

    public function __construct()
    {
        $this->queue = config('queue.connections.sqs.queue');
    }

    /**
     * Process the operation.
     */
    public function process(): void
    {
        Financer::query()
            ->each(function (Financer $financer): void {
                $hasThemes = Theme::withoutGlobalScopes()
                    ->forFinancer($financer->id)
                    ->exists();

                if (! $hasThemes) {
                    app(CreateDefaultSurveyDataAction::class)->execute($financer);
                }
            });
    }
};
