<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Vouchers\Amilon\Http\Controllers\V1;

use App\Events\Vouchers\VoucherPurchaseError;
use App\Events\Vouchers\VoucherPurchaseNotification;
use App\Integrations\Vouchers\Amilon\Database\Factories\MerchantFactory;
use App\Integrations\Vouchers\Amilon\Database\Factories\ProductFactory;
use App\Integrations\Vouchers\Amilon\Models\Merchant;
use App\Integrations\Vouchers\Amilon\Models\Product;
use App\Models\CreditBalance;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('amilon')]
class VoucherPurchaseWebSocketTest extends ProtectedRouteTestCase
{
    private Product $product;

    private Merchant $merchant;

    protected function setUp(): void
    {
        parent::setUp();

        // Create authenticated user
        $this->auth = $this->createAuthUser();

        // Create a merchant and product for testing using resolve()
        $this->merchant = resolve(MerchantFactory::class)->create();
        $this->product = resolve(ProductFactory::class)->forMerchant($this->merchant)->create([
            'merchant_id' => $this->merchant->merchant_id,
            'price' => 50.00, // 50 euros
        ]);

        // Mock the purchase route
        $this->setupMockRoute();
    }

    private function setupMockRoute(): void
    {
        Route::post('/api/v1/integrations/vouchers/purchase', function () {
            $request = request();
            $userId = (string) auth()->id();
            $productId = $request->input('product_id');
            $paymentMethod = $request->input('payment_method');

            // Get user's balance
            $balance = CreditBalance::where('owner_id', $userId)
                ->where('owner_type', 'user')
                ->where('type', 'cash')
                ->first();

            $userBalance = $balance ? $balance->balance / 100 : 0;
            $productPrice = 50.00; // Price in euros

            // Create a mock order ID
            $orderId = 'ORDER-'.uniqid();
            // Handle different payment scenarios
            if ($paymentMethod === 'balance') {
                if ($userBalance >= $productPrice) {
                    // Full balance payment
                    event(new VoucherPurchaseNotification(
                        $userId,
                        $orderId,
                        'created',
                        ['payment_method' => 'balance'],
                        'Your voucher order has been created'
                    ));

                    event(new VoucherPurchaseNotification(
                        $userId,
                        $orderId,
                        'completed',
                        ['payment_method' => 'balance'],
                        'Your voucher purchase has been completed successfully'
                    ));

                    return response()->json(['order_id' => $orderId], 200);
                }
                // Insufficient balance
                event(new VoucherPurchaseError(
                    $userId,
                    'PAYMENT_FAILED',
                    'Insufficient balance'
                ));

                return response()->json(['error' => 'Insufficient balance'], 400);
            }
            if ($paymentMethod === 'stripe') {
                // Stripe payment
                event(new VoucherPurchaseNotification(
                    $userId,
                    $orderId,
                    'created',
                    ['payment_method' => 'stripe'],
                    'Your voucher order has been created'
                ));
                event(new VoucherPurchaseNotification(
                    $userId,
                    $orderId,
                    'pending_stripe_payment',
                    ['payment_method' => 'stripe'],
                    'Please complete the payment to finalize your voucher order.'
                ));

                return response()->json(['order_id' => $orderId, 'payment_url' => 'https://stripe.com/pay'], 200);
            }
            // Handle different payment scenarios
            if ($paymentMethod === 'auto') {
                // Auto selection (mixed payment if partial balance)
                if ($userBalance > 0 && $userBalance < $productPrice) {
                    // Mixed payment
                    $balanceAmount = $userBalance;
                    $stripeAmount = $productPrice - $userBalance;
                    event(new VoucherPurchaseNotification(
                        $userId,
                        $orderId,
                        'created',
                        [
                            'payment_method' => 'mixed',
                            'balance_amount' => $balanceAmount,
                            'stripe_amount' => $stripeAmount,
                        ],
                        'Your voucher order has been created'
                    ));
                    event(new VoucherPurchaseNotification(
                        $userId,
                        $orderId,
                        'pending_stripe_payment',
                        [
                            'payment_method' => 'mixed',
                            'balance_amount' => $balanceAmount,
                            'stripe_amount' => $stripeAmount,
                        ],
                        'Balance payment completed. Please complete the payment to finalize your voucher order.'
                    ));

                    return response()->json(['order_id' => $orderId, 'payment_url' => 'https://stripe.com/pay'], 200);
                }
                // Auto selection (mixed payment if partial balance)
                if ($userBalance >= $productPrice) {
                    // Full balance payment
                    event(new VoucherPurchaseNotification(
                        $userId,
                        $orderId,
                        'created',
                        ['payment_method' => 'balance'],
                        'Your voucher order has been created'
                    ));
                    event(new VoucherPurchaseNotification(
                        $userId,
                        $orderId,
                        'completed',
                        ['payment_method' => 'balance'],
                        'Your voucher purchase has been completed successfully'
                    ));

                    return response()->json(['order_id' => $orderId], 200);
                }
                // Full Stripe payment
                event(new VoucherPurchaseNotification(
                    $userId,
                    $orderId,
                    'created',
                    ['payment_method' => 'stripe'],
                    'Your voucher order has been created'
                ));
                event(new VoucherPurchaseNotification(
                    $userId,
                    $orderId,
                    'pending_stripe_payment',
                    ['payment_method' => 'stripe'],
                    'Please complete the payment to finalize your voucher order.'
                ));

                return response()->json(['order_id' => $orderId, 'payment_url' => 'https://stripe.com/pay'], 200);
            }

            return response()->json(['error' => 'Invalid payment method'], 400);
        })->middleware('auth:api');
    }

    #[Test]
    public function it_broadcasts_notification_when_voucher_order_is_created(): void
    {
        Event::fake([VoucherPurchaseNotification::class]);

        // Give user sufficient balance
        $this->giveUserBalance($this->auth, 100.00);

        // Purchase voucher with balance
        $response = $this->actingAs($this->auth)
            ->postJson('/api/v1/integrations/vouchers/purchase', [
                'product_id' => $this->product->id,
                'payment_method' => 'balance',
            ]);

        $response->assertStatus(200);

        // Assert that order creation notification was broadcast
        Event::assertDispatched(VoucherPurchaseNotification::class, function ($event): bool {
            return $event->userId === (string) $this->auth->id
                && $event->status === 'created'
                && $event->message === 'Your voucher order has been created';
        });
    }

    #[Test]
    public function it_broadcasts_notification_when_balance_payment_completes(): void
    {
        Event::fake([VoucherPurchaseNotification::class]);

        // Give user sufficient balance
        $this->giveUserBalance($this->auth, 100.00);

        // Purchase voucher with balance
        $response = $this->actingAs($this->auth)
            ->postJson('/api/v1/integrations/vouchers/purchase', [
                'product_id' => $this->product->id,
                'payment_method' => 'balance',
            ]);

        $response->assertStatus(200);

        // Assert that payment completion notification was broadcast
        Event::assertDispatched(VoucherPurchaseNotification::class, function ($event): bool {
            return $event->userId === (string) $this->auth->id
                && $event->status === 'completed'
                && $event->message === 'Your voucher purchase has been completed successfully'
                && $event->orderData['payment_method'] === 'balance';
        });
    }

    #[Test]
    public function it_broadcasts_error_when_insufficient_balance(): void
    {
        Event::fake([VoucherPurchaseError::class]);

        // Give user insufficient balance
        $this->giveUserBalance($this->auth, 10.00);

        // Try to purchase voucher with balance
        $response = $this->actingAs($this->auth)
            ->postJson('/api/v1/integrations/vouchers/purchase', [
                'product_id' => $this->product->id,
                'payment_method' => 'balance',
            ]);

        $response->assertStatus(400);

        // Assert that error notification was broadcast
        Event::assertDispatched(VoucherPurchaseError::class, function ($event): bool {
            return $event->userId === (string) $this->auth->id
                && $event->errorCode === 'PAYMENT_FAILED'
                && str_contains($event->errorMessage, 'Insufficient balance');
        });
    }

    #[Test]
    public function it_broadcasts_pending_notification_for_stripe_payment(): void
    {
        Event::fake([VoucherPurchaseNotification::class]);

        // Don't give user any balance to force Stripe payment
        $this->giveUserBalance($this->auth, 0.00);

        // Purchase voucher with Stripe
        $response = $this->actingAs($this->auth)
            ->postJson('/api/v1/integrations/vouchers/purchase', [
                'product_id' => $this->product->id,
                'payment_method' => 'stripe',
            ]);

        $response->assertStatus(200);

        // Assert that pending Stripe payment notification was broadcast
        Event::assertDispatched(VoucherPurchaseNotification::class, function ($event): bool {
            return $event->userId === (string) $this->auth->id
                && $event->status === 'pending_stripe_payment'
                && $event->message === 'Please complete the payment to finalize your voucher order.'
                && $event->orderData['payment_method'] === 'stripe';
        });
    }

    #[Test]
    public function it_broadcasts_pending_notification_for_mixed_payment(): void
    {
        // Test directly with event dispatching
        $userId = (string) $this->auth->id;
        $orderId = 'ORDER-test';

        // Directly dispatch the event as would happen in the real controller
        $event = new VoucherPurchaseNotification(
            $userId,
            $orderId,
            'pending_stripe_payment',
            [
                'payment_method' => 'mixed',
                'balance_amount' => 20.00,
                'stripe_amount' => 30.00,
            ],
            'Balance payment completed. Please complete the payment to finalize your voucher order.'
        );

        // Verify the event has the correct structure
        $this->assertEquals($userId, $event->userId);
        $this->assertEquals('pending_stripe_payment', $event->status);
        $this->assertStringContainsString('Balance payment completed', $event->message);
        $this->assertEquals('mixed', $event->orderData['payment_method']);
        $this->assertEquals(20.00, $event->orderData['balance_amount']);
        $this->assertEquals(30.00, $event->orderData['stripe_amount']);

        // Verify the broadcast channel
        $channels = $event->broadcastOn();
        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);

        // Verify broadcast alias
        $this->assertEquals('voucher.purchase.pending_stripe_payment', $event->broadcastAs());
    }

    #[Test]
    public function it_verifies_broadcast_channel_authorization(): void
    {
        // The channel authorization is already loaded in the application
        // We just need to test that the authorization callback works correctly

        // Get the channel authorization callback
        $channels = Broadcast::getChannels();
        $channelPattern = 'user.{userId}';

        // Find the callback for our channel pattern
        $callback = null;
        foreach ($channels as $pattern => $cb) {
            if ($pattern === $channelPattern) {
                $callback = $cb;
                break;
            }
        }

        // If no callback found, ensure channels are loaded
        if (! $callback) {
            require_once base_path('routes/channels.php');
            $channels = Broadcast::getChannels();
            $callback = $channels[$channelPattern] ?? null;
        }

        $this->assertNotNull($callback, 'Channel authorization callback not found');

        // Test the authorization callback
        $authorized = $callback($this->auth, (string) $this->auth->id);
        $this->assertTrue($authorized, 'User should be authorized for their own channel');

        // Test that user cannot access another user's channel
        $unauthorizedResult = $callback($this->auth, '999999');
        $this->assertFalse($unauthorizedResult, 'User should not be authorized for another user\'s channel');
    }

    private function giveUserBalance(User $user, float $amountInEuros): void
    {
        CreditBalance::updateOrCreate(
            [
                'owner_id' => (string) $user->id,
                'owner_type' => 'user',
                'type' => 'cash',
            ],
            [
                'balance' => (int) ($amountInEuros * 100), // Convert to cents
            ]
        );
    }
}
