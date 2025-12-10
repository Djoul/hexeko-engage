<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentOptionsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array{payment_methods: array<int, array{type: string, available: bool, balance?: float, currency?: string}>, user_balance: float, recommended_method: string} $data */
        $data = is_array($this->resource) ? $this->resource : [];

        return [
            'payment_methods' => $data['payment_methods'] ?? [],
            'recommended_method' => $data['recommended_method'] ?? null,
            'user_balance' => [
                'amount' => $data['user_balance'] ?? 0.0,
                'currency' => 'EUR',
                'formatted' => number_format($data['user_balance'] ?? 0.0, 2, ',', ' ').' €',
            ],
        ];
    }
}
