<?php

declare(strict_types=1);

namespace App\Http\Resources\Financer;

use App\Enums\Languages;
use App\Models\Financer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for financer entities.
 *
 * @mixin Financer
 */
class FinancerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            /**
             * The unique identifier of the financer.
             *
             * @example "123e4567-e89b-12d3-a456-426614174000"
             */
            'id' => $this->id,

            /**
             * The name of the financer.
             *
             * @example "ACME Corporation"
             */
            'name' => $this->name,

            /**
             * The URL to the financer's logo image.
             *
             * @example "https://s3.amazonaws.com/bucket/logo.png"
             */
            'logo_url' => $this->getLogoUrl(),

            /**
             * External identifiers for the financer (e.g., SIRH, ERP).
             *
             * @example {"sirh": "EXT-12345", "erp": "ERP-67890"}
             */
            'external_id' => $this->external_id,

            /**
             * The timezone of the financer.
             *
             * @example "Europe/Paris"
             */
            'timezone' => $this->timezone,

            /**
             * The registration number of the financer (e.g., RCS number).
             *
             * @example "RCS Paris B 123 456 789"
             */
            'registration_number' => $this->registration_number,

            /**
             * The country where the financer is registered.
             *
             * @example "FR"
             */
            'registration_country' => $this->registration_country,

            /**
             * The website URL of the financer.
             *
             * @example "https://www.acme-corp.com"
             */
            'website' => $this->website,

            /**
             * The IBAN of the financer for banking operations.
             *
             * @example "FR7612345678901234567890123"
             */
            'iban' => $this->iban,

            /**
             * The BIC/SWIFT code of the financer's bank.
             *
             * @example "BNPAFRPP"
             */
            'bic' => $this->bic ?? null,

            /**
             * The VAT number of the financer.
             *
             * @example "FR12345678901"
             */
            'vat_number' => $this->vat_number,

            /**
             * The UUID of the representative user for this financer.
             *
             * @example "f47ac10b-58cc-4372-a567-0e02b2c3d479"
             */
            'representative_id' => $this->representative_id,

            /**
             * The UUID of the division this financer belongs to.
             *
             * @example "a1b2c3d4-e5f6-7890-abcd-ef1234567890"
             */
            'division_id' => $this->division_id,

            /**
             * Whether the financer account is active.
             *
             * @example true
             */
            'active' => $this->active,

            /**
             * The current status of the financer.
             *
             * @example "active"
             */
            'status' => $this->status ?? null,

            /**
             * The company number (SIREN/SIRET) of the financer.
             *
             * @example "123456789"
             */
            'company_number' => $this->company_number ?? null,

            /**
             * The list of available language codes for this financer.
             *
             * @example ["fr", "en", "de"]
             */
            'available_languages' => $this->available_languages ?? [],

            /**
             * The available languages formatted as a select array for UI dropdowns.
             *
             * @example [{"value": "fr", "label": "Français"}, {"value": "en", "label": "English"}]
             */
            'available_languages_as_select_array' => $this->getAsSelectedArray(),

            /**
             * The price of the core package per beneficiary.
             *
             * @example 9.99
             */
            'core_package_price' => $this->core_package_price,

            /**
             * The date and time when the financer was created.
             *
             * @example "2024-01-15T10:30:45.000000Z"
             */
            'created_at' => $this->created_at,

            /**
             * The date and time when the financer was last updated.
             *
             * @example "2024-11-05T14:22:30.000000Z"
             */
            'updated_at' => $this->updated_at,

            /**
             * The non-core modules associated with the financer.
             * Null if modules relationship is not loaded.
             */
            'modules' => null,
        ];

        // Include all non-core modules with pivot data when relationship is loaded
        if ($this->relationLoaded('modules')) {
            $data['modules'] = $this->getModulesArray();
        }

        return $data;
    }

    /**
     * Get available languages formatted as a select array.
     *
     * @return list<array{value: int|string, label: string}>
     */
    private function getAsSelectedArray(): array
    {
        $allValues = array_filter(Languages::asSelectObject(), function (array $item): bool {
            return in_array($item['value'], $this->available_languages ?? []);
        });

        return array_values($allValues);
    }

    /**
     * Get modules array for the financer.
     *
     * Returns only non-core modules that are active in the division,
     * with their financer-specific pivot data (active, promoted, price).
     *
     * @return array<int, array{
     *     id: string,
     *     name: string,
     *     description: string|null,
     *     category: string,
     *     is_core: bool,
     *     active: bool,
     *     promoted: bool,
     *     price_per_beneficiary: int|null
     * }>
     */
    protected function getModulesArray(): array
    {
        // Get IDs of modules active in the division (checking pivot->active)
        $activeDivisionModuleIds = $this->division->modules
            ->filter(function ($module): bool {
                // @phpstan-ignore-next-line
                return $module->pivot?->active === true;
            })
            ->pluck('id')
            ->toArray();

        $modules = $this->division->modules;

        /** @var array<int, array{id: string, name: string, description: string|null, category: string, is_core: bool, active: bool, promoted: bool, price_per_beneficiary: int|null}> $result */
        $result = $modules
            ->filter(fn ($module): bool => ! $module->is_core)
            ->filter(fn ($module): bool => in_array($module->id, $activeDivisionModuleIds))
            ->map(function ($module): array {
                $financerModule = $this->modules->where('id', $module->id)->first();

                return [
                    /**
                     * The unique identifier of the module.
                     *
                     * @example "mod-123e4567-e89b-12d3-a456-426614174000"
                     */
                    'id' => $module->id,

                    /**
                     * The name of the module.
                     *
                     * @example "Survey Module"
                     */
                    'name' => $module->name,

                    /**
                     * The description of the module.
                     *
                     * @example "Create and manage employee surveys"
                     */
                    'description' => $module->description,

                    /**
                     * The category of the module.
                     *
                     * @example "engagement"
                     */
                    'category' => $module->category,

                    /**
                     * Whether this is a core module.
                     *
                     * @example false
                     */
                    'is_core' => $module->is_core,

                    /**
                     * Whether the module is active for this financer.
                     *
                     * @example true
                     */
                    'active' => $financerModule->pivot->active ?? false,

                    /**
                     * Whether the module is promoted for this financer.
                     *
                     * @example false
                     */
                    'promoted' => $financerModule->pivot->promoted ?? false,

                    /**
                     * The price per beneficiary for this module.
                     *
                     * @example 250
                     */
                    'price_per_beneficiary' => $financerModule->pivot->price_per_beneficiary ?? null,
                ];
            })->values()
            ->toArray();

        return $result;
    }
}
