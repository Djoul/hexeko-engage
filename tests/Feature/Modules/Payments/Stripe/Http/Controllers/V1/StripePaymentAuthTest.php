<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Payments\Stripe\Http\Controllers\V1;

use App\Integrations\Vouchers\Amilon\Database\factories\MerchantFactory;
use App\Integrations\Vouchers\Amilon\Database\factories\ProductFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('stripe')]
#[Group('payments')]
class StripePaymentAuthTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function test_payment_endpoints_require_authentication(): void
    {
        // Arrange
        $merchant = resolve(MerchantFactory::class)->create();
        $product = resolve(ProductFactory::class)
            ->forMerchant($merchant)
            ->create([
                'name' => 'Test Product',
            ]);

        // Act & Assert - Create payment intent
        $this->postJson(route('stripe.payment-intent.create'), [
            'product_id' => $product->id,
            'amount' => 50,
        ])->assertUnauthorized();

    }
}
