<?php

namespace App\Http\Resources\Division;

use App\Models\Division;
use App\Models\Module;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Division */
class DivisionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status,
            'remarks' => $this->remarks,
            'country' => $this->country,
            'currency' => $this->currency,
            'timezone' => $this->timezone,
            'language' => $this->language,
            'core_package_price' => $this->core_package_price,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'modules' => null, // Default to null, will be overridden if relationship is loaded
        ];

        // Include all non-core modules with pivot data when relationship is loaded
        if ($this->relationLoaded('modules')) {
            $data['modules'] = $this->getModulesArray();
        }

        return $data;
    }

    /**
     * Get modules array for the division
     *
     * @return array<int, array{
     *     id: string,
     *     name: mixed,
     *     description: mixed,
     *     category: string,
     *     is_core: bool,
     *     active: bool,
     *     price_per_beneficiary: ?int
     * }>
     */
    protected function getModulesArray(): array
    {
        $modules = Module::all();
        $divisionModules = $this->modules()->withPivot('active', 'price_per_beneficiary')->get();

        return $modules
            ->filter(fn ($module): bool => ! $module->is_core) // All non-core modules (active and inactive)
            ->map(function ($module) use ($divisionModules): array {
                $divisionModule = $divisionModules->where('id', $module->id)->first();

                return [
                    'id' => $module->id,
                    'name' => $module->name,
                    'description' => $module->description,
                    'category' => $module->category,
                    'is_core' => $module->is_core,
                    'active' => $divisionModule->pivot->active ?? false,
                    'price_per_beneficiary' => $divisionModule->pivot->price_per_beneficiary ?? null,
                ];
            })->values()
            ->toArray();
    }
}
