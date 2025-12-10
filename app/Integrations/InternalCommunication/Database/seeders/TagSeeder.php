<?php

namespace App\Integrations\InternalCommunication\Database\seeders;

use App\Enums\Security\AuthorizationMode;
use App\Integrations\InternalCommunication\Actions\CreateDefaultTagsAction;
use App\Models\Financer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Context;

/**
 * Seeds default internal communication tags for all financers.
 * Uses the CreateDefaultTagsAction for consistency with automatic tag creation.
 */
class TagSeeder extends Seeder
{
    public function __construct(
        protected CreateDefaultTagsAction $createDefaultTagsAction
    ) {}

    /**
     * Run the database seeds.
     * Creates default tags for all existing financers using the centralized action.
     */
    public function run(): void
    {
        $financers = Financer::all();

        foreach ($financers as $financer) {
            // Hydrate authorization context for each financer (required by HasFinancerScope)
            authorizationContext()->hydrate(
                AuthorizationMode::SELF,
                [$financer->id],
                [$financer->division_id],
                [],
                $financer->id
            );

            // Set Context for global scopes
            Context::add('financer_id', $financer->id);
            Context::add('accessible_financers', [$financer->id]);
            Context::add('accessible_divisions', [$financer->division_id]);

            $this->createDefaultTagsAction->handle($financer);
        }
    }
}
