<?php

namespace App\Http\Middleware;

use App\Enums\IDP\PermissionDefaults;
use App\Exceptions\FinancerAccessDeniedException;
use App\Exceptions\FinancerIdRequiredException;
use App\Exceptions\InvalidFinancerIdException;
use App\Models\Financer;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

class CheckAllowedFinancerMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request):Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user) {
            abort(401, 'Unauthorized');
        }

        $financerId = $request->financer_id;

        if (! $financerId) {
            throw new FinancerIdRequiredException;
        }

        if (! Uuid::isValid($financerId)) {
            throw new InvalidFinancerIdException($financerId);
        }

        $hasAccess = $user->hasPermissionTo(PermissionDefaults::MANAGE_ANY_FINANCER) &&
            Financer::query()->where('id', $financerId)->exists();

        if (! $hasAccess) {
            $hasAccess = $user->hasAccessToFinancer($financerId);
        }

        if (! $hasAccess) {
            throw new FinancerAccessDeniedException($financerId, $user->id);
        }

        Context::add('financer_id', $financerId);

        return $next($request);
    }
}
