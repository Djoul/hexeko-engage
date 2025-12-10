<?php

namespace Tests\Unit\Modules\Vouchers\Amilon\DTO;

use App\Integrations\Vouchers\Amilon\DTO\OrderDTO;
use App\Integrations\Vouchers\Amilon\Models\Product;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('vouchers')]
#[Group('amilon')]
class OrderDTOMappingTest extends TestCase
{
    #[Test]
    public function it_maps_api_create_response_correctly(): void
    {
        // Arrange - API response from create order endpoint
        $apiResponse = [
            'Vouchers' => [
                [
                    'VoucherLink' => 'https://stg-web.my-gate.eu/v?c=28562341239E2E593204B288D1DBF750',
                    'ValidityStartDate' => '2025-08-07T00:00:00',
                    'ValidityEndDate' => '2026-02-07T00:00:00',
                    'ProductId' => '6af60678-6cdf-4909-a785-07fa421d9239',
                    'CardCode' => '28562341239E2E593204B288D1DBF750',
                    'Pin' => 'B03DCDC47D87C5F584588CBA7A5A7E38',
                    'RetailerId' => '875196f7-5e79-4e6d-8f8f-5e27f8fa2146',
                    'RetailerName' => 'IdeaShopping',
                    'RetailerCountry' => 'Italy',
                    'RetailerCountryISOAlpha3' => 'ITA',
                    'Name' => null,
                    'Surname' => null,
                    'Email' => null,
                    'Dedication' => null,
                    'OrderFrom' => null,
                    'OrderTo' => null,
                    'Amount' => 20.0,
                    'Deleted' => false,
                ],
            ],
            'ExternalOrderId' => 'ENGAGE-2025-fab4a83e-4744-4fe6-9dfc-3741d3554b9d',
            'OrderDate' => '2025-08-07T17:47:55.517',
        ];

        $product = new Product;
        $product->merchant_id = '875196f7-5e79-4e6d-8f8f-5e27f8fa2146';
        $product->product_code = '6af60678-6cdf-4909-a785-07fa421d9239';

        // Act
        $dto = OrderDTO::fromApiResponse(
            $apiResponse,
            $product,
            20.00,
            'ENGAGE-2025-fab4a83e-4744-4fe6-9dfc-3741d3554b9d',
            'payment_123'
        );

        // Assert
        $this->assertEquals('875196f7-5e79-4e6d-8f8f-5e27f8fa2146', $dto->merchant_id);
        $this->assertEquals(2000, $dto->amount);
        $this->assertEquals('ENGAGE-2025-fab4a83e-4744-4fe6-9dfc-3741d3554b9d', $dto->external_order_id);
        $this->assertNull($dto->order_id);
        $this->assertNull($dto->status);
        $this->assertEquals(null, $dto->price_paid);
        $this->assertEquals('https://stg-web.my-gate.eu/v?c=28562341239E2E593204B288D1DBF750', $dto->voucher_url);
        $this->assertEquals('payment_123', $dto->payment_id);
        $this->assertEquals('2025-08-07T17:47:55.517', $dto->order_date);
        $this->assertEquals(1, $dto->total_requested_codes);
        $this->assertEquals('28562341239E2E593204B288D1DBF750', $dto->voucher_code);
        $this->assertEquals('B03DCDC47D87C5F584588CBA7A5A7E38', $dto->voucher_pin);
        $this->assertEquals('IdeaShopping', $dto->product_name);
        $this->assertEquals('EUR', $dto->currency);
    }

    #[Test]
    public function it_maps_purchase_response_with_null_values(): void
    {
        // Arrange - Purchase response with many null values
        $purchaseData = [
            'merchant_id' => '875196f7-5e79-4e6d-8f8f-5e27f8fa2146',
            'amount' => 1,
            'external_order_id' => 'ENGAGE-2025-fab4a83e-4744-4fe6-9dfc-3741d3554b9d',
            'order_id' => null,
            'status' => null,
            'price_paid' => 1,
            'voucher_url' => null,
            'created_at' => '2025-08-07 17:47:55',
            'payment_id' => null,
            'items' => null,
            'order_date' => null,
            'gross_amount' => null,
            'net_amount' => null,
            'total_requested_codes' => null,
            'order_status' => null,
        ];

        // Act
        $dto = OrderDTO::fromArray($purchaseData);

        // Assert
        $this->assertEquals('875196f7-5e79-4e6d-8f8f-5e27f8fa2146', $dto->merchant_id);
        $this->assertEquals(1.0, $dto->amount);
        $this->assertEquals('ENGAGE-2025-fab4a83e-4744-4fe6-9dfc-3741d3554b9d', $dto->external_order_id);
        $this->assertNull($dto->order_id);
        $this->assertNull($dto->status);
        $this->assertEquals(1.0, $dto->price_paid);
        $this->assertNull($dto->voucher_url);
        $this->assertNull($dto->payment_id);
        $this->assertNull($dto->items);
        $this->assertNull($dto->order_date);
        $this->assertNull($dto->gross_amount);
        $this->assertNull($dto->net_amount);
        $this->assertNull($dto->total_requested_codes);
        $this->assertNull($dto->order_status);
        $this->assertNull($dto->voucher_code);
        $this->assertNull($dto->voucher_pin);
        $this->assertNull($dto->product_name);
        $this->assertEquals('EUR', $dto->currency);
    }

    #[Test]
    public function it_handles_multiple_vouchers_in_api_response(): void
    {
        // Arrange - API response with multiple vouchers
        $apiResponse = [
            'Vouchers' => [
                [
                    'VoucherLink' => 'https://link1.com',
                    'ProductId' => 'product1',
                    'CardCode' => 'CODE1',
                    'Pin' => 'PIN1',
                    'RetailerName' => 'Retailer1',
                    'Amount' => 10.0,
                ],
                [
                    'VoucherLink' => 'https://link2.com',
                    'ProductId' => 'product1',
                    'CardCode' => 'CODE2',
                    'Pin' => 'PIN2',
                    'RetailerName' => 'Retailer1',
                    'Amount' => 10.0,
                ],
            ],
            'ExternalOrderId' => 'ENGAGE-2025-test',
            'OrderDate' => '2025-08-07T12:00:00',
        ];

        $product = new Product;
        $product->merchant_id = 'merchant123';
        $product->product_code = 'product1';

        // Act
        $dto = OrderDTO::fromApiResponse(
            $apiResponse,
            $product,
            20.0,
            'ENGAGE-2025-test',
            null
        );

        // Assert
        $this->assertEquals(2, $dto->total_requested_codes);
        // Should use first voucher for order-level fields
        $this->assertEquals('CODE1', $dto->voucher_code);
        $this->assertEquals('PIN1', $dto->voucher_pin);
        $this->assertEquals('https://link1.com', $dto->voucher_url);
        $this->assertEquals('Retailer1', $dto->product_name);

        // Check items are created
        $this->assertNotNull($dto->items);
        $this->assertCount(1, $dto->items); // All vouchers for same product grouped in one item
    }

    #[Test]
    public function it_handles_case_variations_in_api_keys(): void
    {
        // Arrange - API response with lowercase keys
        $apiResponse = [
            'vouchers' => [
                [
                    'voucherLink' => 'https://lowercase.com',
                    'productId' => 'product1',
                    'cardCode' => 'LOWER_CODE',
                    'pin' => 'LOWER_PIN',
                    'retailerName' => 'LowerRetailer',
                ],
            ],
            'orderDate' => '2025-08-07T15:00:00',
            'orderStatus' => 'pending',
        ];

        $product = new Product;
        $product->merchant_id = 'merchant456';

        // Act
        $dto = OrderDTO::fromApiResponse(
            $apiResponse,
            $product,
            15.0,
            'ENGAGE-2025-lower',
            null
        );

        // Assert
        $this->assertEquals('LOWER_CODE', $dto->voucher_code);
        $this->assertEquals('LOWER_PIN', $dto->voucher_pin);
        $this->assertEquals('https://lowercase.com', $dto->voucher_url);
        $this->assertEquals('LowerRetailer', $dto->product_name);
        $this->assertEquals('2025-08-07T15:00:00', $dto->order_date);
        $this->assertEquals('pending', $dto->order_status);
    }

    #[Test]
    public function it_converts_dto_to_array_with_all_fields(): void
    {
        // Arrange
        $dto = new OrderDTO(
            merchant_id: 'merchant789',
            amount: 50.0,
            external_order_id: 'ENGAGE-2025-array',
            order_id: 'order123',
            status: 'completed',
            price_paid: 45.0,
            voucher_url: 'https://download.com',
            created_at: '2025-08-07 18:00:00',
            payment_id: 'payment456',
            items: [['product_id' => 'prod1', 'quantity' => 2]],
            order_date: '2025-08-07T18:00:00',
            gross_amount: 50.0,
            net_amount: 45.0,
            total_requested_codes: 2,
            order_status: 'confirmed',
            voucher_code: 'VOUCHER123',
            voucher_pin: 'PIN123',
            product_name: 'Test Product',
            currency: 'USD'
        );

        // Act
        $array = $dto->toArray();

        // Assert
        $this->assertArrayHasKey('voucher_code', $array);
        $this->assertArrayHasKey('voucher_pin', $array);
        $this->assertArrayHasKey('product_name', $array);
        $this->assertArrayHasKey('currency', $array);
        $this->assertEquals('VOUCHER123', $array['voucher_code']);
        $this->assertEquals('PIN123', $array['voucher_pin']);
        $this->assertEquals('Test Product', $array['product_name']);
        $this->assertEquals('USD', $array['currency']);
    }

    #[Test]
    public function it_defaults_currency_to_eur_when_not_provided(): void
    {
        // Arrange
        $data = [
            'merchant_id' => 'merchant123',
            'amount' => 10.0,
            'external_order_id' => 'ENGAGE-2025-currency',
            // currency not provided
        ];

        // Act
        $dto = OrderDTO::fromArray($data);

        // Assert
        $this->assertEquals('EUR', $dto->currency);
    }
}
