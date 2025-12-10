<?php

declare(strict_types=1);

namespace App\Http\Resources\User;

use App\Enums\Languages;
use App\Http\Resources\Financer\FinancerResource;
use App\Models\Financer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for invited users (users with invitation_status != null)
 *
 * @mixin User
 */
class InvitedUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        $isExpired = $user->invitation_expires_at !== null
            && $user->invitation_expires_at->isPast();

        // Extract sirh_id from invitation_metadata
        $metadata = is_array($user->invitation_metadata) ? $user->invitation_metadata : [];
        $sirhId = $metadata['sirh_id'] ?? null;

        return [
            /**
             * The unique identifier of the invited user.
             *
             * @example "123e4567-e89b-12d3-a456-426614174000"
             */
            'id' => $user->id,

            /**
             * The first name of the invited user.
             *
             * @example "John"
             */
            'first_name' => $user->first_name,

            /**
             * The last name of the invited user.
             *
             * @example "Doe"
             */
            'last_name' => $user->last_name,

            /**
             * The email address of the invited user.
             *
             * @example "john.doe@example.com"
             */
            'email' => $user->email,

            /**
             * The phone number of the invited user.
             *
             * @example "+33612345678"
             */
            'phone' => $user->phone,

            /**
             * The SIRH identifier from the invitation metadata.
             *
             * @example "SIRH-12345"
             */
            'sirh_id' => $sirhId,

            /**
             * The extra data from the invitation metadata.
             *
             * @example {"intended_role": "financer_admin"}
             */
            'extra_data' => $metadata,

            /**
             * The current status of the invitation.
             *
             * @example "pending"
             */
            'invitation_status' => $user->invitation_status,

            /**
             * The date and time when the invitation expires.
             *
             * @example "2024-12-01T23:59:59.000000Z"
             */
            'invitation_expires_at' => $user->invitation_expires_at?->toISOString(),

            /**
             * The date and time when the invitation was accepted.
             *
             * @example "2024-11-15T14:30:00.000000Z"
             */
            'invitation_accepted_at' => $user->invitation_accepted_at?->toISOString(),

            /**
             * Whether the invitation has expired.
             *
             * @example false
             */
            'is_expired' => $isExpired,

            /**
             * The financer ID associated with the invited user.
             *
             * @example "123e4567-e89b-12d3-a456-426614174000"
             */
            'financer_id' => $user->financer?->id,

            /**
             * The financer associated with the invited user.
             *
             * @var \App\Http\Resources\Financer\FinancerResource|null
             */
            'financer' => $user->financer ? new FinancerResource($user->financer) : null,

            /**
             * The financers associated with the invited user.
             *
             * @var array<int, array{id: string, name: string, status: string, language: string|null, available_languages: array<string>}>
             */
            'financers' => $this->getFinancersSummary($user),

            /**
             * The date and time when the invitation was created.
             *
             * @example "2024-11-01T10:00:00.000000Z"
             */
            'created_at' => $user->created_at?->toISOString(),

            /**
             * The date and time when the invitation was last updated.
             *
             * @example "2024-11-05T14:22:30.000000Z"
             */
            'updated_at' => $user->updated_at?->toISOString(),
        ];
    }

    /**
     * Get financers with simplified structure (no sensitive data)
     *
     * @return array<int, array{id: string, name: string, status: string, language: string|null, available_languages: array<string>}>
     */
    private function getFinancersSummary(User $user): array
    {
        if (! $user->relationLoaded('financers')) {
            $user->load('financers');
        }

        /** @var array<int, array{id: string, name: string, status: string, language: string|null, available_languages: array<string>}> */
        return $user->financers
            ->map(fn (Financer $financer): array => [
                'id' => $financer->id,
                'name' => $financer->name,
                'status' => $financer->pivot?->active ? 'active' : 'inactive',
                /** @phpstan-ignore-next-line property.notFound */
                'language' => $financer->pivot?->language ?? null,
                'available_languages' => $financer->available_languages ?? [],
            ])
            ->toArray();
    }

    /**
     * Get additional data that should be added to the response
     *
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        // Get first financer for available_languages_as_select_array (backward compatibility)
        $financer = $user->financers->first();

        return [
            'meta' => [
                'available_languages_as_select_array' => $financer
                    ? $this->getAvailableLanguagesAsSelectArray($financer)
                    : [],
            ],
        ];
    }

    /**
     * @return list<array{value: int|string, label: string}>
     */
    private function getAvailableLanguagesAsSelectArray(Financer $financer): array
    {
        $allLanguages = Languages::asSelectObject();

        /** @var list<array{value: int|string, label: string}> */
        return array_values(array_filter(
            $allLanguages,
            fn (array $item): bool => in_array($item['value'], $financer->available_languages ?? [])
        ));
    }
}
