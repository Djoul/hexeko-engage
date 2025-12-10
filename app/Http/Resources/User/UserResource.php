<?php

namespace App\Http\Resources\User;

use App\Enums\IDP\RoleDefaults;
use App\Http\Resources\ContractType\ContractTypeResource;
use App\Http\Resources\Department\DepartmentResource;
use App\Http\Resources\JobLevel\JobLevelResource;
use App\Http\Resources\JobTitle\JobTitleResource;
use App\Http\Resources\Manager\ManagerResource;
use App\Http\Resources\Site\SiteResource;
use App\Http\Resources\Tag\TagResource;
use App\Http\Resources\WorkMode\WorkModeResource;
use App\Models\Financer;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    private ?User $authUser = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Store auth user for use in private methods
        $this->authUser = $request->user() ?? auth()->user();

        if ($this->resource instanceof Model) {
            loadRelationIfNotLoaded($this->resource, ['roles', 'financers', 'currentFinancerPivot']);
        }

        /** @var User $user */
        $user = $this->resource;

        $isExpired = $user->invitation_expires_at !== null
            && $user->invitation_expires_at->isPast();

        return [
            /**
             * The unique identifier of the user.
             *
             * @example "123e4567-e89b-12d3-a456-426614174000"
             */
            'id' => $this->id,

            /**
             * The email address of the user.
             *
             * @example "john.doe@example.com"
             */
            'email' => $this->email,

            /**
             * The first name of the user.
             *
             * @example "John"
             */
            'first_name' => $this->first_name,

            /**
             * The last name of the user.
             *
             * @example "Doe"
             */
            'last_name' => $this->last_name,

            /**
             * The gender of the user.
             *
             * @example "male"
             */
            'gender' => $this->gender,

            /**
             * The description of the user.
             *
             * @example "Senior Developer at ACME Corp"
             */
            'description' => $this->description,

            /**
             * The birthdate of the user.
             *
             * @example "1990-05-15"
             */
            'birthdate' => $this->birthdate,

            /**
             * The locale preference of the user.
             *
             * @example "en-US"
             */
            'locale' => $this->locale,

            /**
             * The currency preference of the user.
             *
             * @example "EUR"
             */
            'currency' => $this->currency,

            /**
             * The timezone preference of the user.
             *
             * @example "Europe/Paris"
             */
            'timezone' => $this->timezone,

            /**
             * The phone number of the user.
             *
             * @example "+33612345678"
             */
            'phone' => $this->phone,

            /**
             * Whether the user account is enabled.
             *
             * @example true
             */
            'enabled' => $this->enabled,

            /**
             * The URL to the user's profile image.
             *
             * @example "https://s3.amazonaws.com/bucket/profile-image-temp-url"
             */
            'profile_image' => $this->getProfileImageUrl(),

            /**
             * The Sirh identifiant of the user.
             *
             * @example "SIRH12345"
             */
            'sirh_id' => $this->sirh_id,

            /**
             * The financers associated with the user.
             *
             * @example [{"id": "f47ac10b-58cc-4372-a567-0e02b2c3d479", "name": "ACME Corp"}]
             */
            'financers' => $this->getAccessibleFinancers(),

            /**
             * The role name assigned to the user.
             *
             * @example "financer_admin"
             */
            'role' => $user->getRole(),

            /**
             * The permission names available to the user through their roles.
             *
             * @var \Illuminate\Support\Collection
             */
            'permissions' => $this->getUserPermissions(),

            /**
             * The departments associated with the user.
             *
             * @var \Illuminate\Http\Resources\Json\AnonymousResourceCollection
             */
            'departments' => DepartmentResource::collection(
                collect($this->whenLoaded('departments'))
                    ->sortBy(fn ($department) => strtolower($department->name ?? ''))
                    ->values()
            ),

            /**
             * The sites associated with the user.
             *
             * @var \Illuminate\Http\Resources\Json\AnonymousResourceCollection
             */
            'sites' => SiteResource::collection(
                collect($this->whenLoaded('sites'))
                    ->sortBy(fn ($site) => strtolower($site->name ?? ''))
                    ->values()
            ),

            /**
             * The managers associated with the user.
             *
             * @var \Illuminate\Http\Resources\Json\AnonymousResourceCollection
             */
            'managers' => ManagerResource::collection(
                collect($this->whenLoaded('managers'))
                    ->sortBy(fn ($manager) => strtolower($manager->name ?? ''))
                    ->values()
            ),

            /**
             * The contract types associated with the user.
             *
             * @var \Illuminate\Http\Resources\Json\AnonymousResourceCollection
             */
            'contract_types' => ContractTypeResource::collection(
                collect($this->whenLoaded('contractTypes'))
                    ->sortBy(fn ($contractType) => strtolower($contractType->name ?? ''))
                    ->values()
            ),

            /**
             * The tags associated with the user.
             *
             * @var \Illuminate\Http\Resources\Json\AnonymousResourceCollection
             */
            'tags' => TagResource::collection(
                collect($this->whenLoaded('tags'))
                    ->sortBy(fn ($tag) => strtolower($tag->name ?? ''))
                    ->values()
            ),

            /**
             * The work mode of the user.
             *
             * @var \App\Http\Resources\WorkMode\WorkModeResource|null
             */
            'work_mode' => new WorkModeResource($this->whenLoaded('workMode')),

            /**
             * The job title of the user.
             *
             * @var \App\Http\Resources\JobTitle\JobTitleResource|null
             */
            'job_title' => new JobTitleResource($this->whenLoaded('jobTitle')),

            /**
             * The job level of the user.
             *
             * @var \App\Http\Resources\JobLevel\JobLevelResource|null
             */
            'job_level' => new JobLevelResource($this->whenLoaded('jobLevel')),

            /**
             * The current status of the user's invitation (null for regular users).
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
             * The date and time when the user started working for the financer.
             *
             * @example "2023-06-01T00:00:00.000000Z"
             */
            'started_at' => optional($this->whenLoaded('currentFinancerPivot'))->started_at,

            /**
             * The date and time when the user was created.
             *
             * @example "2024-01-15T10:30:45.000000Z"
             */
            'created_at' => $this->created_at,

            /**
             * The date and time when the user was last updated.
             *
             * @example "2024-11-05T14:22:30.000000Z"
             */
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Get financers accessible to the authenticated user.
     * Filters financers based on authentication context to prevent information disclosure.
     *
     * @return array<int, Financer>
     */
    private function getAccessibleFinancers(): array
    {
        $authUser = $this->authUser;

        // No auth = no financers exposed
        if (! $authUser instanceof User) {
            return [];
        }

        // GOD role can see all financers
        if ($authUser->hasRole(RoleDefaults::GOD)) {
            return collect($this->financers ?? [])->all();
        }

        // User viewing their own profile sees only their active financers
        if ($authUser->id === $this->id) {
            return collect($this->financers ?? [])
                ->filter(fn ($financer): bool => $financer->pivot?->active === true)
                ->values()
                ->all();
        }

        // Other users only see shared financers (where auth user has active access)
        // Ensure financers relation is loaded
        if (! $authUser->relationLoaded('financers')) {
            $authUser->load('financers');
        }

        // Use loaded collection instead of query builder
        $authFinancerIds = collect($authUser->financers)
            ->filter(fn ($f): bool => $f->pivot?->active === true)
            ->pluck('id')
            ->toArray();

        return collect($this->financers ?? [])
            ->filter(function ($financer) use ($authFinancerIds): bool {
                // Filter by shared financer (auth user must have active access)
                // But show target user's financer regardless of their pivot status
                return in_array($financer->id, $authFinancerIds);
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function getUserPermissions(): array
    {
        // Ensure permissions are loaded before accessing them
        if ($this->resource instanceof Model) {
            loadRelationIfNotLoaded($this->resource, ['permissions', 'roles.permissions']);
        }

        // For normal use, use the getPermissionNames method
        /** @var array<int, string> */
        return $this->getAllPermissions()->pluck('name')->toArray();
    }

    /**
     * Set HTTP status code to 201 when used for creation.
     */
    public function withResponse($request, $response): void
    {
        if ($request->isMethod('post')) {
            $response->setStatusCode(201);
        }
    }
}
