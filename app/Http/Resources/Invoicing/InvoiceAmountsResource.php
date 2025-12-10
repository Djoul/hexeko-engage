<?php

declare(strict_types=1);

namespace App\Http\Resources\Invoicing;

use App\DTOs\Invoicing\InvoiceAmountsDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin InvoiceAmountsDTO
 */
class InvoiceAmountsResource extends JsonResource
{
    /**
     * @param  Request  $request
     * @return array<string, int|string>
     */
    public function toArray($request): array
    {
        if ($this->resource instanceof InvoiceAmountsDTO) {
            return $this->resource->toArray();
        }

        return [];
    }
}
