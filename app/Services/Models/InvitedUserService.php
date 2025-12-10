<?php

declare(strict_types=1);

namespace App\Services\Models;

use App\Exceptions\DeprecatedFeatureException;
use App\Models\User;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Service for managing Users with invitation_status='pending'
 * This replaces the old InvitedUser model approach
 */
class InvitedUserService
{
    private const TOKEN_LENGTH = 32;

    private const EXPIRATION_DAYS = 7;

    private const CACHE_TTL = 300; // 5 minutes

    /**
     * @deprecated
     *
     * @param  array<string>  $relations
     * @return Collection<int, User>
     */
    public function all(int $perPage = 20, int $page = 1, array $relations = ['financer'], bool $paginationRequired = true, bool $applySorting = true): Collection
    {
        throw new DeprecatedFeatureException(
            'GET api/v1/invited-users endpoint',
            'GET api/v1/users endpoint with appropriate query parameters',
            '2025-12-03'
        );
    }

    /**
     * @deprecated
     *
     * @param  array<string>  $relations
     */
    public function find(string $id, array $relations = []): User
    {
        throw new DeprecatedFeatureException(
            'GET api/v1/invited-users/{uuid} endpoint',
            'POST api/v1/users/{uuid} endpoint',
            '2025-12-03'
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): User
    {

        try {
            // Ensure invitation_status is set to pending
            $data['invitation_status'] = $data['invitation_status'] ?? 'pending';
            $data['enabled'] = $data['enabled'] ?? false;
            $data['cognito_id'] = $data['cognito_id'] ?? null;

            return User::create($data);

        } catch (Throwable $e) {
            throw new Exception('Failed to create invited user: '.$e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * @deprecated
     *
     * @param  array<string, mixed>  $data
     */
    public function update(User $invitedUser, array $data): User
    {
        throw new DeprecatedFeatureException(
            'PUT api/v1/users/{uuid} endpoint',
            'PUT api/v1/users/{uuid} endpoint',
            '2025-12-03'
        );
    }

    /*
     * @deprecated
     * */
    public function delete(User $invitedUser): bool
    {
        throw new DeprecatedFeatureException(
            'DELETE api/v1/invited-users/{uuid} endpoint',
            'DELETE api/v1/users/{uuid} endpoint with appropriate payment method',
            '2025-12-03'
        );
    }

    /**
     * Generate a secure invitation token
     */
    public function generateToken(): string
    {
        return base64_encode(random_bytes(self::TOKEN_LENGTH));
    }

    /**
     * Create an invitation with a specific role
     *
     * @param  array<string, mixed>  $data
     */
    public function createWithRole(array $data, string $role, string $invitedBy): User
    {
        // Extract fields that don't belong directly in users table
        $financerId = $data['financer_id'] ?? null;
        $sirhId = $data['sirh_id'] ?? null;
        $externalId = $data['external_id'] ?? null;
        $language = $data['language'] ?? null;

        // Remove fields that go to either pivot or metadata
        unset($data['financer_id'], $data['sirh_id'], $data['external_id'], $data['language']);

        // Prepare invitation_metadata with role, financer_id, and external_id
        $data['invitation_metadata'] = array_merge(
            $data['invitation_metadata'] ?? [],
            [
                'intended_role' => $role,
            ]
        );

        // Add financer_id to metadata if provided
        if (! in_array($financerId, [null, '', '0'], true)) {
            $data['invitation_metadata']['financer_id'] = $financerId;
        }

        // Add external_id to metadata if provided (no longer a column in users table)
        if (! in_array($externalId, [null, '', '0'], true)) {
            $data['invitation_metadata']['external_id'] = $externalId;
        }

        $data['invited_by'] = $invitedBy;
        $data['invitation_token'] = $this->generateToken();
        $data['invitation_expires_at'] = Carbon::now()->addDays(self::EXPIRATION_DAYS);
        $data['invited_at'] = Carbon::now();
        $data['invitation_status'] = 'pending';
        $data['enabled'] = false;
        $data['cognito_id'] = null;

        // Set locale from language if provided
        if ($language !== null) {
            $data['locale'] = $language;
        }

        $user = User::create($data);

        // Attach financer if provided with pivot data
        if (! in_array($financerId, [null, '', '0'], true)) {
            $pivotData = [
                'active' => false, // Invited users have inactive financer relationship
                'from' => Carbon::now(),
                'role' => $role, // REQUIRED: Single role in pivot table
            ];

            // Add sirh_id to pivot if provided (external_id is in users table, not pivot)
            if (! in_array($sirhId, [null, '', '0'], true)) {
                $pivotData['sirh_id'] = $sirhId;
            }

            // Add language to pivot if provided
            if ($language !== null) {
                $pivotData['language'] = $language;
            }

            $user->financers()->attach($financerId, $pivotData);
        }

        return $user;
    }

    /**
     * Check if an invitation is expired
     */
    public function isExpired(User $invitation): bool
    {
        if (! $invitation->invitation_expires_at) {
            return false;
        }

        return Carbon::parse($invitation->invitation_expires_at)->isPast();
    }

    /**
     * Find an invitation by token
     */
    public function findByToken(string $token): ?User
    {
        return User::where('invitation_status', 'pending')
            ->where('invitation_token', $token)
            ->first();
    }

    /**
     * Find an invitation by token with caching
     */
    public function findByTokenCached(string $token): ?User
    {
        $cacheKey = "invitation:token:{$token}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($token): ?User {
            return $this->findByToken($token);
        });
    }

    /**
     * Invalidate invitation cache
     */
    public function invalidateCache(string $token): void
    {
        $cacheKey = "invitation:token:{$token}";
        Cache::forget($cacheKey);
    }
}
