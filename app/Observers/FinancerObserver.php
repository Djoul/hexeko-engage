<?php

declare(strict_types=1);

namespace App\Observers;

use App\Integrations\InternalCommunication\Actions\CreateDefaultTagsAction;
use App\Integrations\Survey\Actions\CreateDefaultSurveyDataAction;
use App\Models\Financer;

/**
 * Observer for Financer model events.
 * Automatically creates default data (tags and survey data) when a new financer is created.
 */
class FinancerObserver
{
    public function __construct(
        protected CreateDefaultTagsAction $createDefaultTagsAction,
        protected CreateDefaultSurveyDataAction $createDefaultSurveyDataAction
    ) {}

    /**
     * Handle the Financer "created" event.
     * Automatically creates default internal communication tags and survey data for the new financer.
     */
    public function created(Financer $financer): void
    {
        $this->createDefaultTagsAction->handle($financer);

        // HACK : we don't want to create default survey data in tests
        // TODO : find a better way to handle this
        if (app()->environment() !== 'testing') {
            $this->createDefaultSurveyDataAction->execute($financer);
        }
    }
}
