<?php

namespace App\Repositories\Contracts;

use App\Models\Integration;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

interface IntegrationRepositoryInterface
{
    /**
     * @param  array<string>  $relations
     * @return Collection<int, Integration>
     */
    public function all(array $relations = []);

    /**
     * @param  string  $id
     * @param  array<string>  $relations
     * @return Integration
     *
     * @throws ModelNotFoundException
     */
    public function find($id, array $relations = []);

    /**
     * @param  array<string,mixed>  $data
     * @return Integration
     */
    public function create(array $data);

    /**
     * @param  array<string,mixed>  $data
     * @return Integration
     */
    public function update(Integration $integration, array $data);

    /**
     * @return bool
     */
    public function delete(Integration $integration);
}
