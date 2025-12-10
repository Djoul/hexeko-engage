<?php

namespace App\Integrations\Vouchers\Amilon\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;

class MerchantResourceCollection extends ResourceCollection
{
    /**
     * The resource that this resource collects.
     *
     * @var string
     */
    public $collects = MerchantResource::class;

    /**
     * Create a new resource instance.
     *
     * @return void
     */
    public function __construct($resource)
    {
        parent::__construct($resource);
    }

    /**
     * Transform the resource collection into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $perPageParam = request()->per_page;
        $pageParam = request()->page;

        // Ensure proper type handling before casting
        $perPage = is_numeric($perPageParam) && (int) $perPageParam !== 0 ? (int) $perPageParam : 20;
        $page = is_numeric($pageParam) && (int) $pageParam !== 0 ? (int) $pageParam : 1;

        $total = $this->collection->count();
        $lastPage = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        return [
            'data' => $this->collection->values(),
            'meta' => [
                'total' => $total,
                'current_page' => $page,
                'per_page' => $perPage,
                'last_page' => $lastPage,
            ],
        ];
    }

    /**
     * Check if the collection should be paginated.
     */
    protected function paginated(): bool
    {
        if (request()->has('page')) {
            return true;
        }

        return request()->has('per_page');
    }

    /**
     * Apply pagination to the collection.
     */
    protected function forPage(int $page, int $perPage): Collection
    {
        return $this->collection->forPage($page, $perPage);
    }
}
