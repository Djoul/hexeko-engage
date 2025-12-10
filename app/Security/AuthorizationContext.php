<?php

declare(strict_types=1);

namespace App\Security;

use App\Enums\IDP\RoleDefaults;
use App\Enums\Security\AuthorizationMode;
use App\Models\Division;
use App\Models\Financer;
use App\Models\User;
use Context;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Log;

final class AuthorizationContext
{
    /**
     * @var AuthorizationMode
     */
    public $mode;

    /**
     * @param  array<int, string>  $financerIds
     * @param  array<int, string>  $divisionIds
     * @param  array<int, string>  $actorRoles
     */
    public function __construct(
        string $mode = AuthorizationMode::SELF,
        private array $financerIds = [],
        private array $divisionIds = [],
        private array $actorRoles = [],
        private ?string $currentFinancerId = null,
    ) {
        $this->mode = new AuthorizationMode($mode);
    }

    /**
     * Get the current authorization mode
     */
    public function mode(): AuthorizationMode
    {
        return $this->mode;
    }

    /**
     * Check if current mode is SELF
     */
    public function isSelfMode(): bool
    {
        return $this->mode->value === AuthorizationMode::SELF;
    }

    /**
     * Check if current mode is GLOBAL
     */
    public function isGlobalMode(): bool
    {
        return $this->mode->value === AuthorizationMode::GLOBAL;
    }

    /**
     * Check if current mode is TAKE_CONTROL
     */
    public function isTakeControlMode(): bool
    {
        return $this->mode->value === AuthorizationMode::TAKE_CONTROL;
    }

    /**
     * Get all accessible financer IDs
     * Falls back to Context::get('accessible_financers') for backward compatibility
     *
     * @return list<string>
     */
    public function financerIds(): array
    {
        // If not hydrated, try to use Context for backward compatibility
        if (! $this->isHydrated()) {
            $contextFinancers = Context::get('accessible_financers', []);
            if (is_array($contextFinancers) && $contextFinancers !== []) {
                Log::warning('AuthorizationContext: Using deprecated Context fallback for financerIds. Please hydrate AuthorizationContext via middleware.', [
                    'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3),
                ]);

                return array_values($contextFinancers);
            }
        }

        return array_values($this->financerIds);
    }

    /**
     * Get the current active financer ID
     */
    public function currentFinancerId(): ?string
    {
        return $this->currentFinancerId;
    }

    /**
     * Get all accessible division IDs
     * Falls back to Context::get('accessible_divisions') for backward compatibility
     *
     * @return list<string>
     */
    public function divisionIds(): array
    {
        // If not hydrated, try to use Context for backward compatibility
        if (! $this->isHydrated()) {
            $contextDivisions = Context::get('accessible_divisions', []);
            if (is_array($contextDivisions) && $contextDivisions !== []) {
                Log::warning('AuthorizationContext: Using deprecated Context fallback for divisionIds. Please hydrate AuthorizationContext via middleware.', [
                    'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3),
                ]);

                return array_values($contextDivisions);
            }
        }

        return array_values($this->divisionIds);
    }

    /**
     * Get actor roles
     *
     * @return list<string>
     */
    public function actorRoles(): array
    {
        return array_values($this->actorRoles);
    }

    /**
     * Check if user can access a specific financer
     */
    public function canAccessFinancer(string $id): bool
    {
        return in_array($id, $this->financerIds(), true);
    }

    /**
     * Check if user can access a specific division
     */
    public function canAccessDivision(string $id): bool
    {
        return in_array($id, $this->divisionIds(), true);
    }

    /**
     * Apply the division scope on a query builder when available.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<int, string>|string|null  $divisionIds
     * @return Builder<TModel>
     */
    public function scopeForDivision(Builder $query, array|string|null $divisionIds = null): Builder
    {
        $ids = Arr::wrap($divisionIds ?? $this->divisionIds());
        if ($ids === []) {
            return $query;
        }

        $model = $query->getModel();
        if ($model === null || ! method_exists($model, 'scopeForDivision')) {
            Log::warning('AuthorizationContext: scopeForDivision called on model without HasDivisionScopes trait.', [
                'model' => $model ? $model::class : 'unknown',
                'ids' => $ids,
            ]);

            return $query;
        }

        return $query->forDivision($ids);
    }

    /**
     * Assert that user can access a financer, throw exception if not
     *
     * @throws AuthorizationException
     */
    public function assertFinancer(string $id): void
    {
        if (! $this->canAccessFinancer($id)) {
            throw new AuthorizationException("Financer {$id} is outside your authorization scope");
        }
    }

    /**
     * Assert that user can access a division, throw exception if not
     *
     * @throws AuthorizationException
     */
    public function assertDivision(string $id): void
    {
        if (! $this->canAccessDivision($id)) {
            throw new AuthorizationException("Division {$id} is outside your authorization scope");
        }
    }

    /**
     * Initialize authorization context from authenticated user and request
     * Determines mode, accessible financers, and divisions based on user roles and query params
     */
    public function hydrateFromRequest(User $user): void
    {
        $user->loadMissing(['financers', 'roles']);

        $requestedFinancerIds = $this->normalizeFinancerIds(request()->input('financer_id'));

        $canTakeControlGlobally = $user->hasAnyRole([
            RoleDefaults::GOD,
            RoleDefaults::HEXEKO_ADMIN,
            RoleDefaults::HEXEKO_SUPER_ADMIN,
        ]);
        $canTakeControlForDivision = $user->hasAnyRole([
            RoleDefaults::DIVISION_SUPER_ADMIN,
            RoleDefaults::DIVISION_ADMIN,
        ]);
        $divisionScopeIds = $this->stringifyIds($user->financers->pluck('division_id')->unique()->toArray());

        if ($requestedFinancerIds !== []) {
            $this->hydrateWithRequestedFinancers(
                user: $user,
                requestedFinancerIds: $requestedFinancerIds,
                canTakeControlGlobally: $canTakeControlGlobally,
                canTakeControlForDivision: $canTakeControlForDivision,
                divisionScopeIds: $divisionScopeIds
            );

            return;
        }
        $accessibleFinancers = $canTakeControlGlobally
            ? $this->stringifyIds(Financer::pluck('id')->toArray())
            : $this->stringifyIds($user->financers->pluck('id')->toArray());

        $accessibleDivisions = $canTakeControlGlobally
            ? $this->stringifyIds(Division::pluck('id')->toArray())
            : $divisionScopeIds;

        // Use the first financer from the authenticated user when no financer_id is provided
        $userFirstFinancerId = $user->financers->first()?->id;

        $this->hydrate(
            mode: $canTakeControlGlobally ? AuthorizationMode::GLOBAL : AuthorizationMode::SELF,
            financerIds: $accessibleFinancers,
            divisionIds: $accessibleDivisions,
            actorRoles: $user->roles->pluck('name')->toArray(),
            currentFinancer: $userFirstFinancerId
        );
    }

    /**
     * Hydrate the context with authorization data
     * Called by middleware after authentication
     *
     * @param  array<int, string>  $financerIds
     * @param  array<int, string>  $divisionIds
     * @param  array<int, string>  $actorRoles
     */
    public function hydrate(
        AuthorizationMode|string $mode,
        array $financerIds,
        array $divisionIds,
        array $actorRoles,
        ?string $currentFinancer = null,
    ): void {
        $this->mode = is_string($mode) ? AuthorizationMode::fromValue($mode) : $mode;
        $this->financerIds = array_values($financerIds);
        $this->divisionIds = array_values($divisionIds);
        $this->actorRoles = array_values($actorRoles);
        $this->setCurrentFinancerId($currentFinancer);
    }

    /**
     * Get financers accessible to viewer for a target user
     * Applies authorization scope AND access rules (active, shared)
     *
     * @return array<int, Financer>
     */
    public function getAccessibleFinancersFor(User $target, User $viewer): array
    {
        // Base filter: target's financers within authorization scope
        $scopedFinancers = collect($target->financers ?? [])
            ->filter(fn ($f): bool => in_array($f->id, $this->financerIds()));

        // Viewing own profile: show only active financers
        if ($viewer->id === $target->id) {
            return $scopedFinancers
                ->filter(fn ($f): bool => $f->pivot?->active === true)
                ->values()
                ->all();
        }

        if ($this->isGlobalMode() || $this->isTakeControlMode()) {
            return $scopedFinancers
                ->values()
                ->all();
        }

        // Viewing other user: show only shared active financers
        // Shared = viewer has active access to the same financer
        $viewerActiveFinancerIds = collect($viewer->financers ?? [])
            ->filter(fn ($f): bool => $f->pivot?->active === true)
            ->pluck('id')
            ->toArray();

        return $scopedFinancers
            ->filter(fn ($f): bool => in_array($f->id, $viewerActiveFinancerIds))
            ->values()
            ->all();
    }

    /**
     * Check if context has been hydrated (has data)
     */
    public function isHydrated(): bool
    {
        return $this->financerIds !== [] || $this->divisionIds !== [];
    }

    /**
     * Limit authorization scope when financer_id query string is present
     *
     * @param  array<int, string>  $requestedFinancerIds
     */
    private function hydrateWithRequestedFinancers(
        User $user,
        array $requestedFinancerIds,
        bool $canTakeControlGlobally,
        bool $canTakeControlForDivision,
        array $divisionScopeIds,
    ): void {
        // Use first financer from user or first from requested list as fallback
        $userFirstFinancerId = $user->financers->first()?->id;

        if ($canTakeControlGlobally) {
            [$financerIds, $divisionIds] = $this->scopeFromFinancers($requestedFinancerIds);

            if ($financerIds === []) {
                throw new AuthorizationException('Requested financer_id is not valid.');
            }

            $this->hydrate(
                mode: AuthorizationMode::TAKE_CONTROL,
                financerIds: $financerIds,
                divisionIds: $divisionIds,
                actorRoles: $user->roles->pluck('name')->toArray(),
                currentFinancer: count($financerIds) === 1 ? $financerIds[0] : $userFirstFinancerId
            );

            return;
        }

        if ($canTakeControlForDivision) {
            [$financerIds, $divisionIds] = $this->scopeFromFinancers($requestedFinancerIds);

            if ($financerIds === []) {
                throw new AuthorizationException('Requested financer_id is not valid.');
            }

            $unauthorizedDivisions = array_diff($divisionIds, $divisionScopeIds);
            if ($unauthorizedDivisions !== []) {
                throw new AuthorizationException('You are not allowed to access the requested financer_id.');
            }

            $this->hydrate(
                mode: AuthorizationMode::TAKE_CONTROL,
                financerIds: $financerIds,
                divisionIds: $divisionIds,
                actorRoles: $user->roles->pluck('name')->toArray(),
                currentFinancer: count($financerIds) === 1 ? $financerIds[0] : $userFirstFinancerId
            );

            return;
        }

        $ownedFinancers = $this->stringifyIds($user->financers->pluck('id')->toArray());
        $requestedWithinScope = array_values(array_unique(array_intersect($requestedFinancerIds, $ownedFinancers)));

        if ($requestedWithinScope === []) {
            throw new AuthorizationException('You are not allowed to access the requested financer_id.');
        }

        [$financerIds, $divisionIds] = $this->scopeFromFinancers($requestedWithinScope);

        if ($financerIds === []) {
            throw new AuthorizationException('Requested financer_id is not valid.');
        }

        $this->hydrate(
            mode: AuthorizationMode::SELF,
            financerIds: $financerIds,
            divisionIds: $divisionIds,
            actorRoles: $user->roles->pluck('name')->toArray(),
            currentFinancer: count($financerIds) === 1 ? $financerIds[0] : $userFirstFinancerId
        );
    }

    /**
     * Normalize financer_id query value to a list of string IDs
     *
     * @param  null|string|array<int, string>  $value
     * @return array<int, string>
     */
    private function normalizeFinancerIds(null|string|array $value): array
    {
        if (is_array($value)) {
            $values = $value;
        } elseif (is_string($value) && $value !== '') {
            $values = explode(',', $value);
        } else {
            return [];
        }

        return $this->stringifyIds($values);
    }

    /**
     * @param  array<int|string|null>  $values
     * @return array<int, string>
     */
    private function stringifyIds(array $values): array
    {
        $normalized = array_map(
            fn ($id): string => (string) $id,
            array_filter($values, static fn (int|string|null $id): bool => $id !== null && $id !== '')
        );

        return array_values(array_unique($normalized));
    }

    /**
     * Resolve authorization scope from a list of financer ids
     *
     * @param  array<int, string>  $financerIds
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private function scopeFromFinancers(array $financerIds): array
    {
        if ($financerIds === []) {
            return [[], []];
        }

        /** @var Collection<int, Financer> $financers */
        $financers = Financer::query()
            ->select(['id', 'division_id'])
            ->whereIn('id', $financerIds)
            ->get();

        $resolvedFinancers = $this->stringifyIds($financers->pluck('id')->toArray());
        $resolvedDivisions = $this->stringifyIds($financers->pluck('division_id')->unique()->toArray());

        if (count($resolvedFinancers) !== count(array_unique($financerIds))) {
            return [[], []];
        }

        return [$resolvedFinancers, $resolvedDivisions];
    }

    private function setCurrentFinancerId(?string $currentFinancerId): void
    {
        $this->currentFinancerId = $currentFinancerId;
        Context::add('financer_id', $this->currentFinancerId);
    }
}
