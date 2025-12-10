<?php

declare(strict_types=1);

namespace App\Http\Resources\Invoicing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

class InvoiceCollection extends ResourceCollection
{
    public $collects = InvoiceResource::class;

    /**
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function with($request): array
    {
        $resource = $this->resource;

        if ($resource instanceof LengthAwarePaginator) {
            return [
                'meta' => [
                    'current_page' => $resource->currentPage(),
                    'last_page' => $resource->lastPage(),
                    'per_page' => $resource->perPage(),
                    'total' => $resource->total(),
                    'from' => $resource->firstItem(),
                    'to' => $resource->lastItem(),
                ],
            ];
        }

        return [
            'meta' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => null,
                'total' => 0,
                'from' => null,
                'to' => null,
            ],
        ];
    }
}
