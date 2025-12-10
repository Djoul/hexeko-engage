<?php

namespace App\Integrations\Vouchers\Amilon\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/** @see \App\Integrations\Vouchers\Amilon\Models\Order */
class OrderCollection extends ResourceCollection
{
    /**
     * The resource that this resource collects.
     *
     * @var string
     */
    public $collects = OrderResource::class;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $result = parent::toArray($request);

        return is_array($result) ? $result : ['data' => []];
    }
}
