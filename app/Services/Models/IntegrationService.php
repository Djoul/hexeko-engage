<?php

declare(strict_types=1);

namespace App\Services\Models;

use App\Models\Division;
use App\Models\Financer;
use App\Models\Integration;
use App\Repositories\Models\IntegrationRepository;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class IntegrationService
{
    public function __construct(protected IntegrationRepository $integrationRepository) {}

    /**
     * @param  array<string>  $relations
     * @return Collection<int, Integration>
     */
    public function all(array $relations = []): Collection
    {
        /** @var Collection<int, Integration> */
        return $this->integrationRepository->all($relations);

    }

    /**
     * @param  array<string>  $relations
     */
    public function find(string $id, array $relations = []): Integration
    {
        return $this->integrationRepository->find($id, $relations);
    }

    /**
     * @param  array<string,mixed>  $data
     * @return Integration
     */
    public function create(array $data)
    {
        return $this->integrationRepository->create($data);
    }

    /**
     * @param  array<string,mixed>  $data
     */
    public function update(Integration $integration, array $data): Integration
    {
        return $this->integrationRepository->update($integration, $data);
    }

    public function delete(Integration $integration): bool
    {
        return $this->integrationRepository->delete($integration);
    }

    public function activateForDivision(Integration $integration, Division $division): void
    {
        // First, detach to avoid duplicates
        $division->integrations()->detach($integration->id);
        // Then attach with active set to true
        $division->integrations()->attach($integration, ['active' => true]);
    }

    public function deactivateForDivision(Integration $integration, Division $division): void
    {
        // Check if the integration is attached to the division
        if ($division->integrations()->where('integration_id', $integration->id)->exists()) {
            // Update the pivot to set active to false
            $division->integrations()->updateExistingPivot($integration->id, ['active' => false]);
        } else {
            // If not attached, attach with active set to false
            $division->integrations()->attach($integration, ['active' => false]);
        }

        // Deactivate this integration for all financers of this division
        foreach ($division->financers as $financer) {
            if ($financer->integrations()->where('integration_id', $integration->id)->exists()) {
                $financer->integrations()->updateExistingPivot($integration->id, ['active' => false]);
            }
        }
    }

    public function activateForFinancer(Integration $integration, Financer $financer): void
    {
        $activated = false;

        // Check if the integration is active in the financer's division
        $divisionIntegration = $financer->division->integrations()
            ->where('integration_id', $integration->id)
            ->first();

        if ($divisionIntegration !== null && $divisionIntegration->pivot !== null) {
            $activated = (bool) $divisionIntegration->pivot->active;
        }

        if (! $activated) {
            throw new UnprocessableEntityHttpException('Integration must be active in at least one division before activating it for a financer');
        }

        // First, detach to avoid duplicates
        $financer->integrations()->detach($integration->id);
        // Then attach with active set to true
        $financer->integrations()->attach($integration, ['active' => true]);
    }

    public function deactivateForFinancer(Integration $integration, Financer $financer): void
    {
        if ($financer->integrations()->where('integration_id', $integration->id)->exists()) {
            $financer->integrations()->updateExistingPivot($integration->id, ['active' => false]);
        } else {
            $financer->integrations()->attach($integration, ['active' => false]);
        }
    }

    public function toggleForDivision(Integration $integration, Division $division): bool
    {
        $isActive = false;

        // Check if the integration is active in the division
        $divisionIntegration = $division->integrations()
            ->where('integration_id', $integration->id)
            ->first();

        if ($divisionIntegration !== null && $divisionIntegration->pivot !== null) {
            $isActive = (bool) $divisionIntegration->pivot->active;
        }

        // If the integration is active, deactivate it
        if ($isActive) {
            $this->deactivateForDivision($integration, $division);

            return false;
        }

        // Otherwise, activate it
        $this->activateForDivision($integration, $division);

        return true;
    }

    /**
     * @param  array<string>  $integrationIds
     * @return array<string, bool>
     */
    public function bulkToggleForDivision(array $integrationIds, Division $division): array
    {
        $results = [];

        foreach ($integrationIds as $integrationId) {
            $integration = $this->find($integrationId);
            $results[$integrationId] = $this->toggleForDivision($integration, $division);
        }

        return $results;
    }
}
