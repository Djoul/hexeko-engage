<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Vouchers\Amilon;

use App\Integrations\Vouchers\Amilon\DTO\ProductDTO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('amilon')]
class ProductDTOTest extends TestCase
{
    #[Test]
    public function it_calculates_discount_percentage_correctly(): void
    {
        // Arrange - Product with 20% discount (100€ -> 80€)
        $data = [
            'Name' => 'Test Product',
            'ProductCode' => 'TEST_PROD',
            'Price' => 100.00, // Original price in euros
            'NetPrice' => 80.00, // Discounted price in euros
            'Currency' => 'EUR',
            'MerchantCountry' => 'Portugal',
            'MerchantCode' => 'TEST_MERCHANT',
        ];

        // Act
        $dto = ProductDTO::fromArray($data);

        // Assert - Should calculate 20% discount
        $this->assertEquals(20.0, $dto->discount);
    }

    #[Test]
    #[DataProvider('discountCalculationProvider')]
    public function it_calculates_various_discount_percentages(
        float $originalPrice,
        float $discountedPrice,
        float $expectedPercentage
    ): void {
        // Arrange
        $data = [
            'Name' => 'Test Product',
            'ProductCode' => 'TEST_PROD',
            'Price' => $originalPrice,
            'NetPrice' => $discountedPrice,
            'Currency' => 'EUR',
            'MerchantCountry' => 'Portugal',
            'MerchantCode' => 'TEST_MERCHANT',
        ];

        // Act
        $dto = ProductDTO::fromArray($data);

        // Assert
        $this->assertEquals($expectedPercentage, $dto->discount);
    }

    public static function discountCalculationProvider(): array
    {
        return [
            'No discount' => [100.00, 100.00, 0.0],
            '10% discount' => [100.00, 90.00, 10.0],
            '25% discount' => [100.00, 75.00, 25.0],
            '50% discount' => [100.00, 50.00, 50.0],
            '33.33% discount' => [150.00, 100.00, 33.33],
            '15% discount on 80€' => [80.00, 68.00, 15.0],
            '5% discount on 20€' => [20.00, 19.00, 5.0],
        ];
    }

    #[Test]
    public function it_handles_null_prices_gracefully(): void
    {
        // Arrange - Product without prices
        $data = [
            'Name' => 'Test Product',
            'ProductCode' => 'TEST_PROD',
            'Price' => null,
            'NetPrice' => null,
            'Currency' => 'EUR',
            'MerchantCountry' => 'Portugal',
            'MerchantCode' => 'TEST_MERCHANT',
        ];

        // Act
        $dto = ProductDTO::fromArray($data);

        // Assert - Discount should be null when prices are null
        $this->assertNull($dto->discount);
    }

    #[Test]
    public function it_handles_missing_net_price(): void
    {
        // Arrange - Product with only original price
        $data = [
            'Name' => 'Test Product',
            'ProductCode' => 'TEST_PROD',
            'Price' => 100.00,
            'NetPrice' => null,
            'Currency' => 'EUR',
            'MerchantCountry' => 'Portugal',
            'MerchantCode' => 'TEST_MERCHANT',
        ];

        // Act
        $dto = ProductDTO::fromArray($data);

        // Assert - Discount should be null when net price is missing
        $this->assertNull($dto->discount);
    }

    #[Test]
    public function it_handles_missing_original_price(): void
    {
        // Arrange - Product with only net price
        $data = [
            'Name' => 'Test Product',
            'ProductCode' => 'TEST_PROD',
            'Price' => null,
            'NetPrice' => 80.00,
            'Currency' => 'EUR',
            'MerchantCountry' => 'Portugal',
            'MerchantCode' => 'TEST_MERCHANT',
        ];

        // Act
        $dto = ProductDTO::fromArray($data);

        // Assert - Discount should be null when original price is missing
        $this->assertNull($dto->discount);
    }

    #[Test]
    public function it_handles_zero_original_price(): void
    {
        // Arrange - Product with zero original price (free product)
        $data = [
            'Name' => 'Free Product',
            'ProductCode' => 'FREE_PROD',
            'Price' => 0.00,
            'NetPrice' => 0.00,
            'Currency' => 'EUR',
            'MerchantCountry' => 'Portugal',
            'MerchantCode' => 'TEST_MERCHANT',
        ];

        // Act
        $dto = ProductDTO::fromArray($data);

        // Assert - Discount should be 0% for free products
        $this->assertEquals(0.0, $dto->discount);
    }

    #[Test]
    public function it_rounds_discount_percentage_to_two_decimals(): void
    {
        // Arrange - Price that would result in repeating decimal
        $data = [
            'Name' => 'Test Product',
            'ProductCode' => 'TEST_PROD',
            'Price' => 100.00,
            'NetPrice' => 66.67, // Should be 33.33% discount
            'Currency' => 'EUR',
            'MerchantCountry' => 'Portugal',
            'MerchantCode' => 'TEST_MERCHANT',
        ];

        // Act
        $dto = ProductDTO::fromArray($data);

        // Assert - Should round to 2 decimal places
        $this->assertEquals(33.33, $dto->discount);
    }

    #[Test]
    public function it_handles_net_price_higher_than_original_price(): void
    {
        // Arrange - Edge case where net price is higher (negative discount)
        $data = [
            'Name' => 'Test Product',
            'ProductCode' => 'TEST_PROD',
            'Price' => 100.00,
            'NetPrice' => 120.00, // Net price higher than original
            'Currency' => 'EUR',
            'MerchantCountry' => 'Portugal',
            'MerchantCode' => 'TEST_MERCHANT',
        ];

        // Act
        $dto = ProductDTO::fromArray($data);

        // Assert - Should handle negative discount (surcharge)
        $this->assertEquals(-20.0, $dto->discount);
    }

    #[Test]
    public function it_stores_prices_in_cents_but_discount_as_percentage(): void
    {
        // Arrange
        $data = [
            'Name' => 'Test Product',
            'ProductCode' => 'TEST_PROD',
            'Price' => 100.00, // 100€
            'NetPrice' => 75.00, // 75€ (25% discount)
            'Currency' => 'EUR',
            'MerchantCountry' => 'Portugal',
            'MerchantCode' => 'TEST_MERCHANT',
        ];

        // Act
        $dto = ProductDTO::fromArray($data);

        // Assert
        $this->assertEquals(10000, $dto->price); // Price stored in cents
        $this->assertEquals(7500, $dto->netPrice); // Net price stored in cents
        $this->assertEquals(25.0, $dto->discount); // Discount as percentage
    }
}
