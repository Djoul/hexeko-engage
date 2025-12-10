<?php

namespace App\Integrations\Vouchers\Amilon\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;

class ProductResourceCollection extends ResourceCollection
{
    /**
     * The resource that this resource collects.
     *
     * @var string
     */
    public $collects = ProductResource::class;

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
        return [
            'data' => $this->collection->values(),
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
