<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class TenantGuard
{
    /**
     * @param  Closure(Request):Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$scopes): Response
    {
        $requiredScopes = $this->normalizeScopes($scopes);
        $routeName = $request->route()?->getName() ?? $request->path();

        $missing = [];

        foreach ($requiredScopes as $scope) {
            $applied = $this->scopeApplied($scope);

            if ($applied) {
                Log::info('tenant_scope_applied', [
                    'scope' => $scope,
                    'route' => $routeName,
                    'timestamp' => now()->toIso8601String(),
                ]);

                continue;
            }

            $missing[] = $scope;
            Log::warning('tenant_scope_missing', [
                'scope' => $scope,
                'route' => $routeName,
                'timestamp' => now()->toIso8601String(),
            ]);
        }

        if ($missing !== []) {
            Log::warning('TenantGuard blocked request due to missing scopes', [
                'route' => $routeName,
                'missing_scopes' => $missing,
            ]);

            abort(403, 'Tenant guard missing scopes: '.implode(', ', $missing));
        }

        return $next($request);
    }

    private function normalizeScopes(array $scopes): array
    {
        if ($scopes === []) {
            return ['financer', 'division'];
        }

        return array_values(array_filter(array_map(static function (string $scope): string {
            return strtolower(trim($scope));
        }, $scopes)));
    }

    private function scopeApplied(string $scope): bool
    {
        return match ($scope) {
            'financer' => authorizationContext()->financerIds() !== [],
            'division' => authorizationContext()->divisionIds() !== [],
            default => false,
        };
    }
}
