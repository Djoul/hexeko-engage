<?php

namespace App\Models\Traits\User;

use App\Enums\IDP\RoleDefaults;
use App\Enums\IDP\TeamTypes;
use App\Enums\User\UserStatus;
use App\Exceptions\PermissionDeniedException;
use App\Models\Financer;
use App\Models\Team;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use RuntimeException;
use Spatie\Activitylog\LogOptions;

trait UserAccessorsAndHelpers
{
    protected static function logName(): string
    {
        return 'User';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logAll()
            ->useLogName(static::logName());
    }

    // region Attributes -->

    // @phpstan-ignore-next-line
    protected function fullName(): Attribute
    {
        return Attribute::make(
            fn (): string => $this->first_name.' '.$this->last_name,
            null
        );
    }

    // @phpstan-ignore-next-line
    protected function teamId(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => $value ?? Team::whereType(TeamTypes::GLOBAL)->first()?->id,
            set: fn (?string $value): ?string => in_array($value, [null, '', '0'], true) ? Team::whereType(TeamTypes::GLOBAL)->first()?->id : $value
        );
    }

    protected function currentFinancerId(): Attribute
    {
        return Attribute::make(
            get: function () {
                return Context::get('financer_id') ?? null;
            },
        );
    }

    // endregion -->

    // region helpers -->
    /**
     * @throws PermissionDeniedException
     */
    public function checkUserBelongsToAuthOrganisation(): void
    {
        $authFinancerIds = Auth::user()?->financers()->pluck('financers.id')->toArray() ?? [];

        $userFinancerIds = $this->financers()->pluck('financers.id')->toArray();

        // Find common financers between authUser and targetUser
        $commonFinancers = array_intersect($authFinancerIds, $userFinancerIds);

        if (! Auth::user()?->hasRole(RoleDefaults::GOD) && $commonFinancers === []) {
            throw new PermissionDeniedException('You do not belong to the same financer as this user.');
        }

        $authDivisions = Auth::user()?->financers()->with('division')->get()->pluck('division.id')->unique()->toArray() ?? [];

        $targetDivisions = $this->financers()->with('division')->get()->pluck('division.id')->unique()->toArray();

        // Check if they share at least one common Division
        if (! Auth::user()?->hasRole(RoleDefaults::GOD) && array_intersect($authDivisions, $targetDivisions) === []) {
            throw new PermissionDeniedException('You do not belong to the same division as this user.');
        }
    }

    public function getProfileImageUrl(): ?string
    {
        if (! $this->hasMedia('profile_image')) {
            return null;
        }

        $media = $this->getFirstMedia('profile_image');
        if (! $media) {
            return null;
        }

        // For S3 disk, generate a temporary URL
        if (in_array($media->disk, ['s3', 's3-local'])) {
            return $media->getTemporaryUrl(now()->addHour());
        }

        // For local disk, use the standard URL
        $appUrl = config('app.url');

        if (! is_string($appUrl) || $appUrl === '') {
            throw new RuntimeException('APP_URL environment variable is not defined');
        }

        $relativePath = $media->getUrl();

        // If the relative path already contains the full URL, return it as is
        if (filter_var($relativePath, FILTER_VALIDATE_URL)) {
            return $relativePath;
        }

        // Ensure the URL is properly formatted
        return rtrim($appUrl, '/').'/'.ltrim($relativePath, '/');
    }

    /**
     * Get the user's single role
     */
    public function getRole(): string
    {
        // Check pivot table first (role per financer context)
        if ($this->relationLoaded('financers')) {
            $financer = $this->financers->where('id', activeFinancerID())->first();
            if ($financer !== null && $financer->pivot !== null && $financer->pivot->role) {
                return $financer->pivot->role;
            }
        }

        // Fallback to Spatie roles (take the first one)
        if ($this->roles !== null && is_object($this->roles)) {
            $firstRole = collect($this->roles)->first();

            return $firstRole?->name ?? RoleDefaults::BENEFICIARY;
        }

        // Use Spatie getRoleNames method and take first role
        $roleNames = $this->getRoleNames();

        return $roleNames->first() ?? RoleDefaults::BENEFICIARY;
    }

    /**
     * @deprecated Use getRole() instead for single role system
     *
     * @return array<int, string>
     */
    public function getRolesArray(): string
    {
        // Backward compatibility: return role as array
        return $this->getRole();
    }

    /**
     * Get user status based on financer relationship.
     *
     * @return string|array<int, array{id: int, name: string, status: string}>
     */
    public function getFinancersStatus(): string|array
    {
        // Check if financers relation is loaded to avoid lazy loading
        if (! $this->relationLoaded('financers')) {
            return [];
        }

        $statuses = [];
        foreach ($this->financers as $financer) {
            /** @var Financer $financer */
            if ($financer->id === activeFinancerID() || isInFinancerIdQueryParam($financer->id)) {
                $statuses[] = [
                    'id' => $financer->id,
                    'name' => $financer->name,
                    'status' => ($financer->pivot !== null && $financer->pivot->active) ? UserStatus::ACTIVE : UserStatus::INACTIVE,
                ];
            }
        }

        return $statuses;
    }

    /**
     * Check if this user is an invited user (pending invitation).
     */
    public function isInvitedUser(): bool
    {
        return $this->invitation_status === 'pending';
    }

    /**
     * Check if the invitation has expired.
     */
    public function isInvitationExpired(): bool
    {
        if ($this->invitation_status !== 'pending') {
            return false;
        }

        return $this->invitation_expires_at !== null
            && $this->invitation_expires_at->isPast();
    }

    /**
     * Check if the invitation is still valid (pending and not expired).
     */
    public function hasValidInvitation(): bool
    {
        return $this->invitation_status === 'pending'
            && ! $this->isInvitationExpired();
    }

    /**
     * Validate invitation status transition.
     * State machine for invitation status.
     *
     * @param  string  $newStatus  The target status
     * @return bool Whether the transition is valid
     */
    public function canTransitionTo(string $newStatus): bool
    {
        $currentStatus = $this->invitation_status;

        // Valid transitions
        $validTransitions = [
            'pending' => ['accepted', 'expired', 'revoked'],
            'accepted' => [],
            'expired' => ['revoked'],
            'revoked' => [],
            null => ['pending'],
        ];

        return in_array($newStatus, $validTransitions[$currentStatus] ?? [], true);
    }

    /**
     * Convert User to InvitedUser format for API compatibility.
     * Used to maintain zero breaking change for fronts.
     *
     * @return array<string, mixed>
     */
    public function toInvitedUserArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'financer_id' => $this->financers->first()?->id,
            'sirh_id' => $this->sirh_id,
            'invitation_token' => $this->invitation_token,
            'expires_at' => $this->invitation_expires_at,
            'extra_data' => $this->invitation_metadata,
            'created_at' => $this->invited_at ?? $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
    // endregion -->

    public function hasAccessToFinancer(string $financerId): bool
    {
        return $this->financers()->where('financers.id', $financerId)->exists();
    }
}
