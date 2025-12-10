<?php

namespace App\Integrations\Vouchers\Amilon\Database\factories;

use App\Integrations\Vouchers\Amilon\Models\Order;
use App\Integrations\Vouchers\Amilon\Models\OrderItem;
use App\Integrations\Vouchers\Amilon\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends Factory<OrderItem> */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'quantity' => $this->faker->numberBetween(1, 10),
            'price' => $this->faker->randomFloat(2, 10, 500),
            'vouchers' => $this->faker->words(3),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),

            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
        ];
    }
}
