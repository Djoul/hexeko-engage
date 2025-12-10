<?php

declare(strict_types=1);

namespace App\Repositories\Models;

use App\Models\Integration;
use App\Repositories\Contracts\IntegrationRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

class IntegrationRepository implements IntegrationRepositoryInterface
{
    /**
     * @param  array<string>  $relations
     * @return Collection<int, Integration>
     */
    public function all(array $relations = []): Collection
    {
        // Direct database access, no caching logic here
        /** @var Collection<int, Integration> */
        return Integration::with($relations)->get();
    }

    /**
     * @param  string  $id
     * @param  array<string>  $relations
     *
     * @throws ModelNotFoundException
     */
    public function find($id, array $relations = []): Integration
    {
        // Direct database access, no caching logic here
        $integration = Integration::with($relations)
            ->where('id', $id)
            ->first();

        if (! $integration instanceof Integration) {
            throw new ModelNotFoundException('Integration not found');
        }

        return $integration;
    }

    /**
     * @param  array<string,mixed>  $data
     * @return Integration
     */
    public function create(array $data)
    {
        // Create a new integration in the database
        return Integration::create($data);
    }

    /**
     * @param  array<string,mixed>  $data
     */
    public function update(Integration $integration, array $data): Integration
    {
        // Update the integration with the provided data
        $integration->update($data);

        return $integration;
    }

    public function delete(Integration $integration): bool
    {
        // Delete the integration from the database
        return (bool) $integration->delete();
    }
}
