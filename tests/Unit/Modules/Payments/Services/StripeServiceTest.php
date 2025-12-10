<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Payments\Services;

use App\Integrations\Payments\Stripe\Contracts\StripeClientInterface;
use App\Integrations\Payments\Stripe\DTO\CheckoutSessionDTO;
use App\Integrations\Payments\Stripe\Exceptions\StripePaymentException;
use App\Integrations\Payments\Stripe\Services\StripeService;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('stripe')]
class StripeServiceTest extends TestCase
{
    private StripeService $service;

    private StripeClientInterface|MockInterface $stripeClient;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a mock of the interface
        $this->stripeClient = Mockery::mock(StripeClientInterface::class);

        // Inject the mock into the service
        $this->service = new StripeService($this->stripeClient);
    }

    #[Test]
    public function can_create_checkout_session_with_valid_dto(): void
    {
        // Arrange
        $expectedSession = [
            'id' => 'cs_test_123',
            'url' => 'https://checkout.stripe.com/123',
            'amount_total' => 5000,
            'currency' => 'eur',
            'status' => 'unpaid',
            'metadata' => [
                'user_id' => 'user-123',
                'credit_type' => 'voucher_credit',
                'credit_amount' => '500',
            ],
        ];

        $this->stripeClient->shouldReceive('createCheckoutSession')
            ->once()
            ->with(Mockery::on(function (array $params): bool {
                return $params['payment_method_types'] === ['card']
                    && $params['mode'] === 'payment'
                    && $params['line_items'][0]['price_data']['unit_amount'] === 5000
                    && $params['line_items'][0]['price_data']['currency'] === 'eur'
                    && $params['line_items'][0]['quantity'] === 1
                    && $params['metadata']['user_id'] === 'user-123'
                    && $params['metadata']['credit_type'] === 'voucher_credit'
                    && $params['metadata']['credit_amount'] === '500';
            }))
            ->andReturn($expectedSession);

        $dto = new CheckoutSessionDTO(
            userId: 'user-123',
            amount: 50.00,
            currency: 'eur',
            creditType: 'voucher_credit',
            creditAmount: 500,
            successUrl: 'https://app.test/success',
            cancelUrl: 'https://app.test/cancel',
            productName: 'Test Credits'
        );

        // Act
        $result = $this->service->createCheckoutSession($dto);

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals('cs_test_123', $result['id']);
        $this->assertEquals('https://checkout.stripe.com/123', $result['url']);
    }

    #[Test]
    public function throws_exception_when_stripe_api_fails(): void
    {
        // Arrange
        $stripeException = new StripePaymentException('Stripe API error: API error occurred');

        $this->stripeClient->shouldReceive('createCheckoutSession')
            ->once()
            ->andThrow($stripeException);

        $dto = new CheckoutSessionDTO(
            userId: 'user-123',
            amount: 50.00,
            currency: 'eur',
            creditType: 'voucher_credit',
            creditAmount: 500,
            successUrl: 'https://app.test/success',
            cancelUrl: 'https://app.test/cancel',
            productName: 'Test Credits'
        );

        // Act & Assert
        $this->expectException(StripePaymentException::class);
        $this->expectExceptionMessage('Stripe API error: API error occurred');

        $this->service->createCheckoutSession($dto);
    }

    #[Test]
    public function session_expires_after_30_minutes(): void
    {
        // Arrange
        $expectedSession = [
            'id' => 'cs_test_123',
            'url' => 'https://checkout.stripe.com/123',
        ];

        $this->stripeClient->shouldReceive('createCheckoutSession')
            ->once()
            ->with(Mockery::on(function (array $params): bool {
                $currentTime = time();
                $expiresAt = $params['expires_at'];

                // Check that expires_at is approximately 30 minutes from now (allow 5 seconds variance)
                return $expiresAt >= ($currentTime + 1795) && $expiresAt <= ($currentTime + 1805);
            }))
            ->andReturn($expectedSession);

        $dto = new CheckoutSessionDTO(
            userId: 'user-123',
            amount: 50.00,
            currency: 'eur',
            creditType: 'voucher_credit',
            creditAmount: 500,
            successUrl: 'https://app.test/success',
            cancelUrl: 'https://app.test/cancel',
            productName: 'Test Credits'
        );

        // Act
        $result = $this->service->createCheckoutSession($dto);

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals($expectedSession['id'], $result['id']);
        $this->assertEquals($expectedSession['url'], $result['url']);
    }

    #[Test]
    public function converts_amount_to_cents_correctly(): void
    {
        // Arrange
        $expectedSession = [
            'id' => 'cs_test_123',
            'url' => 'https://checkout.stripe.com/123',
        ];

        $this->stripeClient->shouldReceive('createCheckoutSession')
            ->once()
            ->with(Mockery::on(function (array $params): bool {
                // Test various amounts
                return $params['line_items'][0]['price_data']['unit_amount'] === 9999;
            }))
            ->andReturn($expectedSession);

        $dto = new CheckoutSessionDTO(
            userId: 'user-123',
            amount: 99.99,
            currency: 'eur',
            creditType: 'voucher_credit',
            creditAmount: 1000,
            successUrl: 'https://app.test/success',
            cancelUrl: 'https://app.test/cancel',
            productName: 'Test Credits'
        );

        // Act
        $result = $this->service->createCheckoutSession($dto);

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals($expectedSession['id'], $result['id']);
        $this->assertEquals($expectedSession['url'], $result['url']);
    }

    #[Test]
    public function includes_session_id_in_success_url(): void
    {
        // Arrange
        $expectedSession = [
            'id' => 'cs_test_123',
            'url' => 'https://checkout.stripe.com/123',
        ];

        $this->stripeClient->shouldReceive('createCheckoutSession')
            ->once()
            ->with(Mockery::on(function (array $params): bool {
                return $params['success_url'] === 'https://app.test/success?session_id={CHECKOUT_SESSION_ID}';
            }))
            ->andReturn($expectedSession);

        $dto = new CheckoutSessionDTO(
            userId: 'user-123',
            amount: 50.00,
            currency: 'eur',
            creditType: 'voucher_credit',
            creditAmount: 500,
            successUrl: 'https://app.test/success',
            cancelUrl: 'https://app.test/cancel',
            productName: 'Test Credits'
        );

        // Act
        $result = $this->service->createCheckoutSession($dto);

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals($expectedSession['id'], $result['id']);
        $this->assertEquals($expectedSession['url'], $result['url']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
