<?php

namespace App\Http\Resources\User;

use App\Models\Financer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @mixin User
 */
class UserIndexResource extends JsonResource
{
    private ?User $authUser = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Store auth user for use in private methods
        $this->authUser = $request->user() ?? auth()->user();

        /** @var User $user */
        $user = $this->resource;

        // Get accessible financers (Financer objects with pivot)
        $accessibleFinancers = $this->getAccessibleFinancers($user);
        $financers = $this->getFinancersWithStatus($user, $accessibleFinancers);

        return [
            /**
             * The unique identifier of the user.
             *
             * @example "123e4567-e89b-12d3-a456-426614174000"
             */
            'id' => $user->id,

            /**
             * The first name of the user.
             *
             * @example "John"
             */
            'first_name' => $user->first_name,

            /**
             * The last name of the user.
             *
             * @example "Doe"
             */
            'last_name' => $user->last_name,

            /**
             * The email address of the user.
             *
             * @example "john.doe@example.com"
             */
            'email' => $user->email,

            /**
             * The phone number of the user.
             *
             * @example "+33612345678"
             */
            'phone' => $user->phone,

            /**
             * The financers associated with the user.
             *
             * @example [{"id": "f47ac10b-58cc-4372-a567-0e02b2c3d479", "name": "ACME Corp", "status": "active","language": "fr-FR"}]
             */
            'financers' => $financers,

            /**
             * The URL of the user's profile image (temporary S3 link).
             *
             * @example "https://s3.amazonaws.com/bucket/profile-image-temp-url"
             */
            'profile_image' => $this->getUserProfileImage($user),

            /**
             * The entry date of the user (started_at > from > created_at) in ISO 8601 format.
             *
             * @example "2024-01-15T10:30:45.000000Z"
             */
            'entry_date' => $this->getEntryDate($user, $accessibleFinancers)?->toISOString(),

            /**
             * The description or biography of the user.
             *
             * @example "Senior Developer at ACME Corp"
             */
            'description' => $user->description,

            /**
             * The primary role assigned to the user.
             *
             * @example "financer_admin"
             */
            'role' => $user->getRole(),
        ];
    }

    /**
     * Get financers with proper status (handles pending invitations).
     *
     * @param  array<int, Financer>  $accessibleFinancers
     * @return array<int, array<string, mixed>>
     */
    private function getFinancersWithStatus(User $user, array $accessibleFinancers): array
    {
        // Determine status based on invitation
        $isPending = $user->invitation_status === 'pending';

        /** @var array<int, array<string, mixed>> */
        return collect($accessibleFinancers)
            ->map(fn (array|Financer $financer): array => $this->mapFinancerToArray($financer, $isPending))
            ->toArray();
    }

    /**
     * Map financer object or array to standardized array format.
     *
     * @param  array<string, mixed>|Financer  $financer
     * @return array<string, mixed>
     */
    private function mapFinancerToArray(array|Financer $financer, bool $isPending): array
    {
        $financerId = is_object($financer) ? $financer->id : $financer['id'];
        /** @phpstan-ignore nullCoalesce.offset */
        $financerName = is_object($financer) ? $financer->name : ($financer['name'] ?? '');
        $pivot = is_object($financer) ? $financer->pivot : null;

        return [
            'id' => $financerId,
            'name' => $financerName,
            'status' => $isPending ? 'invited' : ($pivot?->active ? 'active' : 'inactive'),
            'language' => $pivot->language ?? null,
        ];
    }

    /**
     * Get financers accessible to the authenticated user.
     * Delegates to AuthorizationContext for consistent authorization logic.
     *
     * @return array<int, Financer>
     */
    private function getAccessibleFinancers(User $user): array
    {
        if (! $this->authUser instanceof User) {
            return [];
        }

        return authorizationContext()->getAccessibleFinancersFor($user, $this->authUser);
    }

    /**
     * Get user profile image URL.
     */
    private function getUserProfileImage(User $user): ?string
    {
        if ($user->relationLoaded('media')) {
            $profileImage = $user->media->where('collection_name', 'profile_image')->first();
            if ($profileImage) {
                // Generate temporary URL for S3 media
                if (in_array($profileImage->disk, ['s3', 's3-local'])) {
                    return $profileImage->getTemporaryUrl(now()->addHour());
                }

                return $profileImage->getUrl();
            }
        }

        return null;
    }

    /**
     * Get user entry date based on pivot data.
     * Priority: started_at > from > created_at
     *
     * @param  array<int, Financer>  $accessibleFinancers
     */
    private function getEntryDate(User $user, array $accessibleFinancers): ?Carbon
    {
        // Get the first accessible financer with pivot data
        $financer = collect($accessibleFinancers)->first();

        if (! $financer instanceof Financer || $financer->pivot === null) {
            return $user->created_at;
        }

        $pivot = $financer->pivot;

        // Priority: started_at (SIRH/import) > from (invitation date) > created_at (fallback)
        return $pivot->started_at ?? $pivot->from ?? $user->created_at;
    }
}
