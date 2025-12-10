<?php

use App\Exceptions\DeprecatedFeatureException;
use App\Models\User;
use App\Security\AuthorizationContext;

if (! function_exists('generateSecurePassword')) {
    function generateSecurePassword(int $length = 8): string
    {
        // Define character sets
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';
        $specialChars = '!@#$%^&*()-_+=<>?';

        // Ensure at least one character from each required set
        $password = [
            $uppercase[random_int(0, strlen($uppercase) - 1)],
            $lowercase[random_int(0, strlen($lowercase) - 1)],
            $numbers[random_int(0, strlen($numbers) - 1)],
            $specialChars[random_int(0, strlen($specialChars) - 1)],
        ];

        // Fill the remaining length with random characters
        $allCharacters = $uppercase.$lowercase.$numbers.$specialChars;
        for ($i = 4; $i < $length; $i++) {
            $password[] = $allCharacters[random_int(0, strlen($allCharacters) - 1)];
        }

        // Shuffle to avoid predictable positions
        shuffle($password);

        // Convert array to string
        return implode('', $password);
    }
}

if (! function_exists('activeFinancerID')) {
    /**
     * Get the active financer ID from request or user
     *
     * @deprecated Use authorizationContext()->currentFinancerId() for single value
     *             or authorizationContext()->financerIds() for array
     *
     * @return array<int, string>|string|null
     */
    #[Deprecated(
        message: 'Use authorizationContext()->currentFinancerId() or financerIds() instead',
        since: '2025-01-10'
    )]
    function activeFinancerID(?User $user = null): null|string|array
    {

        // Check deprecated header
        if (filled(request()->header('x-financer-id'))) {
            throw new DeprecatedFeatureException(
                'x-financer-id header is deprecated.',
                'Please use financer_id query parameter instead.',
                '28-08-2025'
            );
        }

        // Check cache first (performance)
        if (Context::has('financer_id')) {
            return Context::get('financer_id');
        }

        // If financer_id query param present, return it (already validated by middleware)
        if (filled(request()->input('financer_id'))) {
            // use authorizationContext()->financerIds() to get array of financer_id
            $financerId = request()->input('financer_id');
        } elseif (filled(request()->input('division_id'))) {
            // division_id set, financer_id remains null
            $financerId = null;
        } else {
            // Delegate to AuthorizationContext
            $financerId = authorizationContext()->currentFinancerId();
        }

        // Validate required outside console
        if (! App::runningInConsole() && is_null($financerId) && ! filled(request()->input('division_id'))) {
            abort(422, 'Missing financer_id query params');
        }

        // Cache the result
        Context::add('financer_id', $financerId);

        return $financerId;
    }

}
if (! function_exists('isInFinancerIdQueryParam')) {
    function isInFinancerIdQueryParam(string $financer_id): bool
    {
        if (request()->has('financer_id')) {
            $financerIds = request()->input('financer_id');

            // Handle both array and string formats
            if (is_array($financerIds)) {
                if (in_array($financer_id, $financerIds)) {
                    return true;
                }
            } elseif (is_string($financerIds)) {
                // Fallback for direct string input (not through FormRequest)
                if (in_array($financer_id, explode(',', $financerIds))) {
                    return true;
                }
            }
        }

        return activeFinancerID() === $financer_id;
    }
}

if (! function_exists('authorizationContext')) {
    /**
     * Get the current authorization context singleton
     */
    function authorizationContext(): AuthorizationContext
    {
        return app(AuthorizationContext::class);
    }
}
