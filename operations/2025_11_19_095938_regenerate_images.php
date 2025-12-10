<?php

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
        Artisan::call('media-library:regenerate');
    }
};
