<?php

namespace App\Http\Resources\Module;

use App\Models\Financer;
use App\Models\Module;
use App\Models\User;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Module */
class ModuleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $financer = $this->getFinancer();

        $user = Auth::user();

        if ($user instanceof User) {
            loadRelationIfNotLoaded($user, 'pinnedModules');
        }

        return [
            'id' => $this->id,
            'name_raw' => $this->getTranslations('name'),
            'name' => $this->name,
            'description_raw' => $this->getTranslations('description'),
            'description' => $this->description,
            'active' => $this->active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'category' => $this->category,
            // if the user auth has pinned this module
            'pinned' => $user && in_array(
                $this->id,
                $user->pinnedModules()->pluck('module_id')->toArray()
            ),
            // if the concerned financer has promoted this module
            'promoted' => $financer && in_array(
                $this->id,
                $financer->modules()->where('financer_module.promoted', true)->pluck('module_id')->toArray()
            ),
        ];
    }

    protected function getFinancer(): ?Financer
    {
        $financerId = request()->header('x-financer-id');
        if ($financerId) {
            return Financer::where('id', $financerId)->first();
        }
        $user = Auth::user();
        if ($user instanceof User) {
            if ($user->relationLoaded('financers') && $user->financers->count() === 1) {
                return $user->financers->first();
            }
            if (! $user->relationLoaded('financers') && $user->financers()->count() === 1) {
                $financer = $user->financers()->first();

                return $financer instanceof Financer ? $financer : null;
            }
        }

        return null;
    }
}
