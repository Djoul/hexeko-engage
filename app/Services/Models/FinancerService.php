<?php

declare(strict_types=1);

namespace App\Services\Models;

use App\Models\Financer;
use App\Pipelines\FilterPipelines\FinancerPipeline;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class FinancerService
{
    /**
     * @param  array<string>  $relations
     * @return array{items: Collection<int, Financer>, meta: array{total_items: int}}
     */
    public function all(int $page = 1, int $perPage = 20, array $relations = []): array
    {
        /** @var Collection<int, Financer> */
        $items = Financer::query()
            ->with($relations)
            ->pipeFiltered()
            ->get();

        $total = $items->count();
        if (paginated()) {
            $items = $items->forPage($page, $perPage)->values();
        }

        return [
            'items' => $items,
            'meta' => [
                'total_items' => $total,
            ],
        ];
    }

    /**
     * @param  array<string>  $relations
     */
    public function find(string $id, array $relations = []): Financer
    {
        $financer = Financer::with($relations)
            ->where('id', $id)
            ->first();

        if (! $financer instanceof Financer) {
            throw new ModelNotFoundException('Financer not found');
        }

        return $financer;
    }

    /**
     * @param  array<string,mixed>  $data
     * @return Financer
     */
    public function create(array $data)
    {
        return Financer::create($data);
    }

    /**
     * @param  array<string,mixed>  $data
     */
    public function update(Financer $financer, array $data): Financer
    {
        $financer->update($data);

        return $financer;
    }

    public function delete(Financer $financer): bool
    {
        return (bool) $financer->delete();
    }

    /**
     * @param  array<string>  $divisionIds
     * @param  array<string>  $relations
     * @return Collection<int, Financer>
     */
    public function allByDivisions(array $divisionIds, array $relations = []): Collection
    {
        // If no division IDs provided, return empty collection
        if ($divisionIds === []) {
            return collect();
        }

        // Use caching at the service layer for financers by divisions
        $model = new Financer;
        $cacheKey = $model->getCacheKey(
            'divisions_'.md5((string) json_encode($divisionIds)).
            ($relations === [] ? '' : '_'.md5((string) json_encode($relations)))
        );
        $cacheTag = $model->getCacheTag();

        /** @var Collection<int, Financer> */
        return Cache::tags([$cacheTag])->remember(
            $cacheKey,
            $model::getCacheTtl(),
            fn (): Collection => resolve(FinancerPipeline::class)
                ->apply(Financer::with($relations)->whereIn('division_id', $divisionIds))
                ->get()
        );
    }
}
