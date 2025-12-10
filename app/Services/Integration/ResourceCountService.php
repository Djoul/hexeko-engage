<?php

namespace App\Services\Integration;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ResourceCountService
{
    /**
     * Execute a count query with dynamic parameters
     *
     * @param  string|null  $query  The SQL query to execute
     * @param  array<string, mixed>  $parameters  Query parameters
     * @return int The count result
     */
    public function executeCountQuery(?string $query, array $parameters = []): int
    {
        if (empty($query)) {
            return 0;
        }

        try {
            // Extract all parameter placeholders from the query
            preg_match_all('/:(\w+)/', $query, $matches);
            $requiredParams = $matches[1] ?? [];

            // Build final parameters array with only the required ones
            $finalParameters = [];
            foreach ($requiredParams as $param) {
                if (array_key_exists($param, $parameters)) {
                    $finalParameters[$param] = $parameters[$param];
                } else {
                    // If a required parameter is missing, return 0
                    // This prevents SQL errors for missing parameters
                    Log::debug('Missing required parameter for resource count query', [
                        'parameter' => $param,
                        'query' => $query,
                        'available_parameters' => array_keys($parameters),
                        'all_parameters' => $parameters,
                    ]);

                    return 0;
                }
            }

            // Execute the raw query with parameter binding
            $result = DB::select($query, $finalParameters);

            // Extract count from result
            if (! empty($result) && is_array($result) && ! empty($result[0])) {
                $firstResult = $result[0];
                if (is_object($firstResult) && property_exists($firstResult, 'count')) {
                    $count = $firstResult->count;

                    return is_numeric($count) ? (int) $count : 0;
                }
            }

            return 0;
        } catch (Exception $e) {
            // Log error but don't throw - return 0 for graceful degradation
            Log::warning('Failed to execute resource count query', [
                'query' => $query,
                'parameters' => $parameters,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Get count with context parameters (financer, user, etc.)
     *
     * @param  array<string, mixed>  $context
     */
    public function getCountWithContext(?string $query, array $context = []): int
    {
        if (empty($query)) {
            return 0;
        }

        // Build parameters from context
        $parameters = $this->buildParametersFromContext($context);

        return $this->executeCountQuery($query, $parameters);
    }

    /**
     * Build query parameters from context
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function buildParametersFromContext(array $context): array
    {
        $parameters = [];

        // Add financer_id if available
        if (array_key_exists('financer_id', $context)) {
            $parameters['financer_id'] = $context['financer_id'];
        }

        // Add user language if available
        // First check if language is directly in context
        if (array_key_exists('language', $context)) {
            $parameters['language'] = $context['language'];
        } elseif (array_key_exists('user', $context)) {
            // If not, try to get from user object
            $user = $context['user'];
            if (is_object($user)) {
                // Check for locale field (Laravel standard)
                if (property_exists($user, 'locale') && ! empty($user->locale)) {
                    $parameters['language'] = $user->locale;
                } elseif (property_exists($user, 'language') && ! empty($user->language)) {
                    $parameters['language'] = $user->language;
                }
            }
        }

        // Add country if available
        if (array_key_exists('country', $context)) {
            $parameters['country'] = $context['country'];
        }

        // Add any additional parameters passed directly
        foreach ($context as $key => $value) {
            if (! in_array($key, ['user', 'financer_id', 'language', 'country'], true)) {
                $parameters[$key] = $value;
            }
        }

        return $parameters;
    }
}
