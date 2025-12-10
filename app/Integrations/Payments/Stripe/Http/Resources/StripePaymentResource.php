<?php

declare(strict_types=1);

namespace App\Integrations\Payments\Stripe\Http\Resources;

use App\Integrations\Payments\Stripe\Models\StripePayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StripePayment
 */
class StripePaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'formatted_amount' => $this->formatted_amount,
            'credit_amount' => $this->credit_amount,
            'credit_type' => $this->credit_type,
            'product_name' => $this->product_name,
            'stripe_payment_id' => $this->stripe_payment_id,
            'stripe_checkout_id' => $this->stripe_checkout_id,
            'processed_at' => $this->processed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
