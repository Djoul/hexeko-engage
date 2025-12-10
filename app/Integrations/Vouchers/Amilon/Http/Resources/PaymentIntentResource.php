<?php

declare(strict_types=1);

namespace App\Integrations\Vouchers\Amilon\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentIntentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $resource */
        $resource = is_array($this->resource) ? $this->resource : [];

        return [
            'payment_intent_id' => $resource['payment_intent_id'] ?? null,
            'client_secret' => $resource['client_secret'] ?? null,
            'amount' => $resource['amount'] ?? 0,
            'original_amount' => $resource['original_amount'] ?? 0,
            'discount_percentage' => $resource['discount_percentage'] ?? 0,
            'currency' => 'EUR',
            'payment_id' => $resource['payment_id'] ?? null,
            'status' => 'requires_payment_method',
            'created_at' => now()->toIso8601String(),
        ];
    }
}
