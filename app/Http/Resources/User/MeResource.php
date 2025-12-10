<?php

namespace App\Http\Resources\User;

use App\Enums\CreditTypes;
use App\Enums\IDP\RoleDefaults;
use App\Http\Resources\ContractType\ContractTypeResource;
use App\Http\Resources\Department\DepartmentResource;
use App\Http\Resources\Financer\FinancerWithPivotResource;
use App\Http\Resources\JobLevel\JobLevelResource;
use App\Http\Resources\JobTitle\JobTitleResource;
use App\Http\Resources\Manager\ManagerResource;
use App\Http\Resources\Site\SiteResource;
use App\Http\Resources\Tag\TagResource;
use App\Http\Resources\WorkMode\WorkModeResource;
use App\Integrations\HRTools\Http\Resources\LinkResource;
use App\Models\Module;
use App\Models\User;
use App\Services\RoleManagementService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/** @mixin User */
class MeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if ($this->resource instanceof Model) {
            loadRelationIfNotLoaded($this->resource, ['roles', 'financers', 'currentFinancerPivot']);
        }

        // Get assignable roles for the current user
        $authUser = auth()->user();
        $assignableRoles = [];

        if ($authUser) {
            $rolesCanAssign = resolve(RoleManagementService::class)->getRolesUserCanAssign($authUser);

            foreach ($rolesCanAssign as $role) {
                if (! array_key_exists($role, $assignableRoles)) {
                    $assignableRoles[$role] = [
                        'value' => $role,
                        'label' => $this->getRoleLabel($role),
                    ];
                }
            }
        }

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
             * The financers associated with the user with active status only.
             *
             * @var \Illuminate\Http\Resources\Json\AnonymousResourceCollection
             */
            'financers' => FinancerWithPivotResource::collection($this->getAccessibleFinancers()),

            /**
             * The role names assigned to the user.
             *
             * @example ["financer_admin", "beneficiary"]
             */
            'roles' => $this->getUserRoles(),

            /**
             * The permission names available to the user through their roles.
             *
             * @var \Illuminate\Support\Collection
             */
            'permissions' => $this->getUserPermissions(),

            /**
             * The user's credit balance grouped by credit type.
             *
             * @example {"cash": 10000, "aiToken": 500}
             */
            'credit_balance' => $this->getCreditBalance(),

            /**
             * The modules associated with the user's active financers.
             *
             * @var \Illuminate\Support\Collection
             */
            'my_modules' => $this->getActiveModules(),

            /**
             * The collection of pinned HRTools links for the user.
             *
             * @var \Illuminate\Http\Resources\Json\AnonymousResourceCollection
             */
            'pinned_HRTools_links' => $this->resource instanceof User ? LinkResource::collection(
                ($this->pinnedHRToolsLinks ?? collect())->filter(fn ($link) => $link->pivot->pinned ?? false)
            ) : [],

            /**
             * An array of financer IDs accessible by the user.
             *
             * @example ["f47ac10b-58cc-4372-a567-0e02b2c3d479", "a48ac10b-58cc-4372-a567-0e02b2c3d480"]
             */
            'my_financers' => authorizationContext()->financerIds(),

            /**
             * An array of division IDs accessible by the user.
             *
             * @example ["d12bc10b-58cc-4372-a567-0e02b2c3d481", "e13cd10b-58cc-4372-a567-0e02b2c3d482"]
             */
            'my_divisions' => authorizationContext()->divisionIds(),

            /**
             * The roles that can be assigned to this user.
             *
             * @example [{"value": "financer_admin", "label": "Financer Admin"}]
             */
            'assignable_roles' => array_values($assignableRoles),

            /**
             * The roles that can be removed from this user.
             *
             * @example [{"value": "beneficiary", "label": "Beneficiary"}]
             */
            'removable_roles' => array_values($assignableRoles),

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
             * The managers of the user.
             *
             * @var \Illuminate\Http\Resources\Json\AnonymousResourceCollection
             */
            'managers' => ManagerResource::collection(
                collect($this->whenLoaded('managers'))
                    ->sortBy(fn ($manager) => strtolower($manager->name ?? ''))
                    ->values()
            ),

            /**
             * The contract types of the user.
             *
             * @var \Illuminate\Http\Resources\Json\AnonymousResourceCollection
             */
            'contractTypes' => ContractTypeResource::collection(
                collect($this->whenLoaded('contractTypes'))
                    ->sortBy(fn ($contractType) => strtolower($contractType->name ?? ''))
                    ->values()
            ),

            /**
             * The tags of the user.
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
             * The date and time when the user started working for the financer.
             *
             * @example "2023-06-01T00:00:00.000000Z"
             */
            'started_at' => $this->whenLoaded('currentFinancerPivot')?->started_at,

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
     * For /me endpoint, only active financers are shown.
     */
    private function getAccessibleFinancers(): Collection
    {
        return collect($this->financers ?? [])
            ->filter(fn ($financer): bool => $financer->pivot?->active === true)
            ->values();
    }

    /**
     * Get a human-readable label for a role
     */
    private function getRoleLabel(string $role): string
    {
        $labels = [
            RoleDefaults::HEXEKO_SUPER_ADMIN => 'Hexeko Super Admin',
            RoleDefaults::HEXEKO_ADMIN => 'Hexeko Admin',
            RoleDefaults::DIVISION_SUPER_ADMIN => 'Division Super Admin',
            RoleDefaults::DIVISION_ADMIN => 'Division Admin',
            RoleDefaults::FINANCER_SUPER_ADMIN => 'Financer Super Admin',
            RoleDefaults::FINANCER_ADMIN => 'Financer Admin',
            RoleDefaults::BENEFICIARY => 'Beneficiary',
        ];

        return $labels[$role] ?? ucfirst(str_replace('_', ' ', $role));
    }

    /**
     * @return array<string>
     */
    private function getUserRoles(): array
    {
        // For tests, if roles is already set as a property
        if ($this->roles !== null && is_object($this->roles)) {
            /** @var array<string> */
            $result = collect($this->roles)->pluck('name')->toArray();

            return $result;
        }

        // For normal use, use the getRoleNames method
        /** @var array<string> */
        $result = $this->getRoleNames()->toArray();

        return $result;
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
     * @return array|int[]
     */
    protected function getCreditBalance(): array
    {
        // Ensure credits are loaded to avoid N+1 queries
        if ($this->resource instanceof Model) {
            loadRelationIfNotLoaded($this->resource, ['credits']);
        }

        // Index credits by type for O(1) lookup instead of O(n) per iteration
        $creditsByType = $this->credits->keyBy('type');

        $resp = [];
        foreach (CreditTypes::asArray() as $type) {
            // Use indexed lookup instead of where()->first() to avoid potential queries
            $credit = $creditsByType->get($type);
            $balance = $credit !== null ? $credit->balance : 0;

            // Cash credits are stored in cents, so we divide by 100
            $resp[$type] = $balance;
        }

        return $resp;
    }

    private function getActiveModules(): Collection
    {
        if (empty($this->financers) || authorizationContext()->financerIds() === []) {
            return collect();
        }

        $financerId = activeFinancerID();

        $myModules = $this->financers
            ->where('id', $financerId)
            ->pluck('modules')
            ->flatten();

        if (empty($myModules)) {
            return collect();
        }

        return $myModules
            ->filter(fn (Module $module): bool => $module->is_core || ($module->pivot !== null && $module->pivot?->active));
    }
}
