<?php

namespace App\Integrations\Vouchers\Amilon\Traits;

use App\Integrations\Vouchers\Amilon\DTO\OrderDTO;
use App\Integrations\Vouchers\Amilon\Models\OrderItem;

trait OrderAccessorsAndHelpers
{
    /**
     * Convert the model to an OrderDTO.
     */
    public function toDTO(): OrderDTO
    {
        // Get items data if available
        $items = $this->items()->get()->map(function ($item): array {
            /** @var OrderItem $item */
            $vouchersData = $item->vouchers ?? [];
            if (is_string($vouchersData)) {
                $vouchersData = json_decode($vouchersData, true) ?? [];
            }

            return [
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'price' => $item->price,
                'vouchers' => is_array($vouchersData) ? $vouchersData : [],
            ];
        })->toArray();

        return new OrderDTO(
            merchant_id: $this->merchant_id,
            amount: $this->amount,
            external_order_id: $this->external_order_id,
            order_id: $this->order_id,
            status: $this->status,
            price_paid: $this->price_paid,
            voucher_url: $this->voucher_url,
            created_at: $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            payment_id: $this->payment_id,
            items: $items,
            order_date: $this->order_date,
            gross_amount: $this->gross_amount,
            net_amount: $this->net_amount,
            total_requested_codes: $this->total_requested_codes,
            order_status: $this->order_status,
            voucher_code: $this->voucher_code,
            voucher_pin: $this->voucher_pin,
            product_name: $this->product_name,
            currency: $this->currency,
        );
    }

    /**
     * Create a new order from an OrderDTO.
     */
    public static function fromDTO(OrderDTO $dto): self
    {
        // Create the order
        $order = self::create([
            'merchant_id' => $dto->merchant_id,
            'amount' => $dto->amount,
            'external_order_id' => $dto->external_order_id,
            'order_id' => $dto->order_id,
            'status' => $dto->status,
            'price_paid' => $dto->price_paid,
            'voucher_url' => $dto->voucher_url,
            'payment_id' => $dto->payment_id,
            'order_date' => $dto->order_date,
            'gross_amount' => $dto->gross_amount,
            'net_amount' => $dto->net_amount,
            'total_requested_codes' => $dto->total_requested_codes,
            'order_status' => $dto->order_status,
            'voucher_code' => $dto->voucher_code,
            'voucher_pin' => $dto->voucher_pin,
            'product_name' => $dto->product_name,
            'currency' => $dto->currency,
        ]);

        // Create order items if available
        if (! empty($dto->items)) {
            foreach ($dto->items as $itemData) {
                if (! is_array($itemData)) {
                    continue;
                }
                $order->items()->create([
                    'product_id' => $itemData['product_id'] ?? null,
                    'quantity' => $itemData['quantity'] ?? 0,
                    'price' => $itemData['price'] ?? null,
                    'vouchers' => $itemData['vouchers'] ?? null,
                ]);
            }
        }

        return $order;
    }

    /**
     * Update or create an order from an OrderDTO.
     */
    public static function updateOrCreateFromDTO(OrderDTO $dto): self
    {
        // Update or create the order
        $order = self::updateOrCreate(
            ['external_order_id' => $dto->external_order_id],
            [
                'merchant_id' => $dto->merchant_id,
                'amount' => $dto->amount,
                'order_id' => $dto->order_id,
                'status' => $dto->status,
                'price_paid' => $dto->price_paid,
                'voucher_url' => $dto->voucher_url,
                'payment_id' => $dto->payment_id,
                'order_date' => $dto->order_date,
                'gross_amount' => $dto->gross_amount,
                'net_amount' => $dto->net_amount,
                'total_requested_codes' => $dto->total_requested_codes,
                'order_status' => $dto->order_status,
                'voucher_code' => $dto->voucher_code,
                'voucher_pin' => $dto->voucher_pin,
                'product_name' => $dto->product_name,
                'currency' => $dto->currency,
            ]
        );

        // Update or create order items if available
        if (! empty($dto->items)) {
            // Get existing items
            $existingItems = $order->items()->get()->keyBy('product_id');

            foreach ($dto->items as $itemData) {
                if (! is_array($itemData)) {
                    continue;
                }
                $productId = $itemData['product_id'] ?? null;
                if ($productId === null) {
                    continue;
                }
                if (! is_string($productId) && ! is_int($productId)) {
                    continue;
                }

                if ($existingItems->has($productId)) {
                    // Update existing item
                    $existingItem = $existingItems[$productId];
                    if ($existingItem !== null) {
                        $existingItem->update([
                            'quantity' => $itemData['quantity'] ?? 0,
                            'price' => $itemData['price'] ?? null,
                            'vouchers' => $itemData['vouchers'] ?? null,
                        ]);
                    }
                } else {
                    // Create new item
                    $order->items()->create([
                        'product_id' => $productId,
                        'quantity' => $itemData['quantity'] ?? 0,
                        'price' => $itemData['price'] ?? null,
                        'vouchers' => $itemData['vouchers'] ?? null,
                    ]);
                }
            }
        }

        return $order;
    }
}
