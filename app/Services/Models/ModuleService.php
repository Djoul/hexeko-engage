<?php

declare(strict_types=1);

namespace App\Services\Models;

use App\Models\Division;
use App\Models\Financer;
use App\Models\Module;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class ModuleService
{
    /**
     * @param  array<string>  $relations
     * @return Collection<int, Module>
     */
    public function all(array $relations = []): Collection
    {
        /** @var Collection<int, Module> */
        return Module::with($relations)->get();
    }

    /**
     * @param  array<string>  $relations
     * @return Module
     */
    public function find(string $id, array $relations = [])
    {
        $module = Module::with($relations)
            ->where('id', $id)
            ->first();

        if (! $module instanceof Module) {
            throw new ModelNotFoundException('Module not found');
        }

        return $module;
    }

    /**
     * @param  array<string,mixed>  $data
     * @return Module
     */
    public function create(array $data)
    {
        return Module::create($data);
    }

    /**
     * @param  array<string,mixed>  $data
     */
    public function update(Module $module, array $data): Module
    {
        $module->update($data);

        return $module;
    }

    public function delete(Module $module): bool
    {
        return (bool) $module->delete();
    }

    public function activateForDivision(Module $module, Division $division): void
    {
        // Detach first to avoid duplicates
        $division->modules()->detach($module->id);
        // Then attach with active = true
        $division->modules()->attach($module, ['active' => true]);
    }

    public function deactivateForDivision(Module $module, Division $division): void
    {
        // Check if the module is attached to the division
        if ($division->modules()->where('module_id', $module->id)->exists()) {
            // Update the pivot to set active to false
            $division->modules()->updateExistingPivot($module->id, ['active' => false]);
        } else {
            // If not attached, attach with active set to false
            $division->modules()->attach($module, ['active' => false]);
        }

        // Deactivate this module for all financers of this division
        foreach ($division->financers as $financer) {
            if ($financer->modules()->where('module_id', $module->id)->exists()) {
                $financer->modules()->updateExistingPivot($module->id, ['active' => false]);
            }
        }
    }

    public function activateForFinancer(Module $module, Financer $financer): void
    {
        $activated = false;

        // Check if the module is active in the financer division
        $divisionModule = $financer->division->modules()
            ->where('module_id', $module->id)
            ->first();

        if ($divisionModule !== null && $divisionModule->pivot !== null) {
            $activated = (bool) $divisionModule->pivot->active;
        }

        if (! $activated) {
            throw new UnprocessableEntityHttpException('Module must be active in the financer\'s division before activating it for a financer');
        }

        // Detach first to avoid duplicates
        $financer->modules()->detach($module->id);
        // Then attach with active = true
        $financer->modules()->attach($module, ['active' => true]);

        $moduleName = json_encode($module->name) ?: 'unknown';
        activity('financer')
            ->performedOn($financer)
            ->log("Module {$moduleName} attached to financer {$financer->name}");
    }

    public function deactivateForFinancer(Module $module, Financer $financer): void
    {
        if ($financer->modules()->where('module_id', $module->id)->exists()) {
            $financer->modules()->updateExistingPivot($module->id, ['active' => false]);
        } else {
            $financer->modules()->attach($module, ['active' => false]);
        }

        $moduleName = json_encode($module->name) ?: 'unknown';
        activity('financer')
            ->performedOn($financer)
            ->log("Module {$moduleName} detached from financer {$financer->name}");
    }

    public function toggleForDivision(Module $module, Division $division): bool
    {
        $isActive = false;

        // Check if the module is active in the division
        $divisionModule = $division->modules()
            ->where('module_id', $module->id)
            ->first();

        if ($divisionModule !== null && $divisionModule->pivot !== null) {
            $isActive = (bool) $divisionModule->pivot->active;
        }

        // If the module is active, deactivate it
        if ($isActive) {
            $this->deactivateForDivision($module, $division);

            return false;
        }

        // Otherwise, activate it
        $this->activateForDivision($module, $division);

        return true;
    }

    /**
     * @param  array<string>  $financerIds
     */
    public function bulkActivateForDivision(Module $module, Division $division, array $financerIds = []): void
    {
        // Activate for division
        $this->activateForDivision($module, $division);

        // Activate for specified financers
        foreach ($financerIds as $financerId) {
            $financer = $division->financers()->find($financerId);
            if ($financer) {
                $this->activateForFinancer($module, $financer);
            }
        }
    }

    /**
     * @param  array<string>  $moduleIds
     * @return array<string, bool>
     */
    public function bulkToggleForDivision(array $moduleIds, Division $division): array
    {
        $results = [];

        foreach ($moduleIds as $moduleId) {
            $module = $this->find($moduleId);
            $results[$moduleId] = $this->toggleForDivision($module, $division);
        }

        return $results;
    }

    /**
     * @param  array<string>  $moduleIds
     * @return array<string, bool>
     */
    public function bulkToggleForFinancer(array $moduleIds, Financer $financer): array
    {
        $results = [];

        foreach ($moduleIds as $moduleId) {
            $module = $this->find($moduleId);
            $results[$moduleId] = $this->toggleForFinancer($module, $financer);
        }

        return $results;
    }

    /**
     * Toggle module status for a financer
     */
    public function toggleForFinancer(Module $module, Financer $financer): bool
    {
        $isActive = false;

        // Check if the module is active for the financer
        $financerModule = $financer->modules()
            ->where('module_id', $module->id)
            ->first();

        if ($financerModule !== null && $financerModule->pivot !== null) {
            $isActive = (bool) $financerModule->pivot->active;
        }

        // If the module is active, deactivate it
        if ($isActive) {
            $this->deactivateForFinancer($module, $financer);

            return false;
        }

        // Otherwise, activate it
        $this->activateForFinancer($module, $financer);

        return true;
    }

    /**
     * Promote a module for a financer (only if already active).
     */
    public function promoteForFinancer(Module $module, Financer $financer): void
    {
        // Check if module is active for this financer
        $pivot = $financer->modules()->where('module_id', $module->id)->first()?->pivot;
        if (! $pivot || ! $pivot->active) {
            throw new UnprocessableEntityHttpException('The module must be active for this financer before it can be promoted.');
        }
        // Promote
        $financer->modules()->updateExistingPivot($module->id, ['promoted' => true]);
    }

    /**
     * Unpromote a module for a financer.
     */
    public function unpromoteForFinancer(Module $module, Financer $financer): void
    {
        $pivot = $financer->modules()->where('module_id', $module->id)->first()?->pivot;

        if (! $pivot || ! $pivot->promoted) {
            throw new UnprocessableEntityHttpException('This module is not promoted for this financer.');
        }
        $financer->modules()->updateExistingPivot($module->id, ['promoted' => false]);
    }
}
