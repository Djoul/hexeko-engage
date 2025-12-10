<?php

declare(strict_types=1);

namespace App\Services\Models;

use App\Models\Division;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

class DivisionService
{
    /**
     * @param  array<string>  $relations
     * @return Collection<int, Division>
     */
    public function all(array $relations = []): Collection
    {
        /** @var Collection<int, Division> */
        return Division::with($relations)
            ->pipeFiltered()
            ->get();
    }

    /**
     * @param  array<string>  $relations
     */
    public function find(string $id, array $relations = []): Division
    {
        $division = Division::with($relations)
            ->where('id', $id)
            ->first();

        if (! $division instanceof Division) {
            throw new ModelNotFoundException('Division not found');
        }

        return $division;
    }

    /**
     * @param  array<string,mixed>  $data
     * @return Division
     */
    public function create(array $data)
    {
        return Division::create($data);
    }

    /**
     * @param  array<string,mixed>  $data
     */
    public function update(Division $division, array $data): Division
    {
        $division->update($data);

        return $division;
    }

    public function delete(Division $division): bool
    {
        return (bool) $division->delete();
    }
}
