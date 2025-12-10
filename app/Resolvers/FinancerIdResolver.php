<?php

namespace App\Resolvers;

use App\Attributes\GlobalScopedModel;
use Context;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Contracts\Resolver;
use ReflectionClass;

class FinancerIdResolver implements Resolver
{
    /**
     * Resolve the financer ID.
     */
    public static function resolve(Auditable $auditable): ?string
    {
        // Check if the model is marked as global scoped
        $reflection = new ReflectionClass($auditable);
        $attributes = $reflection->getAttributes(GlobalScopedModel::class);

        // If the model has the GlobalScopedModel attribute, it doesn't need financer_id
        if (count($attributes) > 0) {
            return null;
        }

        if (Context::get('is_stripe_webhook')) {
            return null;
        }

        return activeFinancerID();
    }
}
