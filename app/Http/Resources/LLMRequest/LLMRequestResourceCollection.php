<?php

namespace App\Http\Resources\LLMRequest;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/** @see \App\Models\LoggableModel */
class LLMRequestResourceCollection extends ResourceCollection
{
    /**
     * Create a new resource instance.
     *
     * @return void
     */
    public function __construct($resource)
    {
        // Handle null resource by converting to empty collection
        parent::__construct($resource ?? collect());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection->sortBy('created_at'),
        ];
    }
}
