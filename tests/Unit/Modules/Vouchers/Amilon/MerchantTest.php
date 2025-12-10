<?php

namespace Tests\Unit\Modules\Vouchers\Amilon;

use App\Integrations\Vouchers\Amilon\DTO\MerchantDTO;
use App\Integrations\Vouchers\Amilon\Models\Merchant;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Traits\AmilonDatabaseCleanup;
use Tests\ProtectedRouteTestCase;

#[Group('amilon')]
#[Group('vouchers')]
class MerchantTest extends ProtectedRouteTestCase
{
    use AmilonDatabaseCleanup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanupAmilonDatabase();
    }

    #[Test]
    public function test_merchant_can_be_created_from_dto(): void
    {
        $dto = new MerchantDTO(
            name: 'Test Merchant',
            country: 'FR',
            merchant_id: 'TEST001',
            description: 'Test description',
            image_url: 'https://example.com/test.jpg'
        );

        $merchant = Merchant::fromDTO($dto);

        $this->assertEquals('Test Merchant', $merchant->name);
        $this->assertEquals('FR', $merchant->country);
        $this->assertEquals('TEST001', $merchant->merchant_id);
        $this->assertEquals('Test description', $merchant->description);
        $this->assertEquals('https://example.com/test.jpg', $merchant->image_url);
    }

    #[Test]
    public function test_merchant_can_be_converted_to_dto(): void
    {
        $merchant = new Merchant([
            'name' => 'Test Merchant',
            'country' => 'FR',
            'merchant_id' => 'TEST001',
            'description' => 'Test description',
            'image_url' => 'https://example.com/test.jpg',
        ]);
        $merchant->save();

        $dto = $merchant->toDTO();

        $this->assertEquals('Test Merchant', $dto->name);
        $this->assertEquals('FR', $dto->country);
        $this->assertEquals('TEST001', $dto->merchant_id);
        $this->assertEquals('Test description', $dto->description);
        $this->assertEquals('https://example.com/test.jpg', $dto->image_url);
    }

    #[Test]
    public function test_merchant_has_categories_relationship(): void
    {
        $merchant = Merchant::create([
            'name' => 'Electronics Store',
            'country' => 'FR',
            'merchant_id' => 'ELEC001',
        ]);

        // Test that the categories relationship exists
        $this->assertInstanceOf(BelongsToMany::class, $merchant->categories());
        $this->assertCount(0, $merchant->categories);
    }

    #[Test]
    public function test_merchant_scope_search_by_name(): void
    {
        Merchant::create([
            'name' => 'Fnac',
            'country' => 'FR',
            'merchant_id' => 'FNAC001',
        ]);

        Merchant::create([
            'name' => 'Decathlon',
            'country' => 'FR',
            'merchant_id' => 'DECA001',
        ]);

        $merchants = Merchant::searchByName('Fna')->get();

        $this->assertCount(1, $merchants);
        $this->assertEquals('Fnac', $merchants->first()->name);
    }

    #[Test]
    public function test_merchant_scope_by_merchant_id(): void
    {
        Merchant::create([
            'name' => 'Fnac',
            'country' => 'FR',
            'merchant_id' => 'FNAC001',
        ]);

        Merchant::create([
            'name' => 'Decathlon',
            'country' => 'FR',
            'merchant_id' => 'DECA001',
        ]);

        $merchant = Merchant::byMerchantId('FNAC001')->first();

        $this->assertNotNull($merchant);
        $this->assertEquals('Fnac', $merchant->name);
        $this->assertEquals('FNAC001', $merchant->merchant_id);
    }

    #[Test]
    public function test_merchant_scope_order_by_name(): void
    {
        // Create test merchants with specific merchant IDs to identify them
        Merchant::create([
            'name' => 'Zara',
            'country' => 'FR',
            'merchant_id' => 'TEST_NAME_ZARA',
        ]);

        Merchant::create([
            'name' => 'Amazon',
            'country' => 'FR',
            'merchant_id' => 'TEST_NAME_AMZN',
        ]);

        // Query only the merchants we just created
        $merchants = Merchant::whereIn('merchant_id', ['TEST_NAME_ZARA', 'TEST_NAME_AMZN'])
            ->orderByName()
            ->get();

        $this->assertCount(2, $merchants);
        $this->assertEquals('Amazon', $merchants->first()->name);
        $this->assertEquals('Zara', $merchants->last()->name);

        $merchantsDesc = Merchant::whereIn('merchant_id', ['TEST_NAME_ZARA', 'TEST_NAME_AMZN'])
            ->orderByName('desc')
            ->get();

        $this->assertCount(2, $merchantsDesc);
        $this->assertEquals('Zara', $merchantsDesc->first()->name);
        $this->assertEquals('Amazon', $merchantsDesc->last()->name);
    }

    #[Test]
    public function test_merchant_scope_order_by_country(): void
    {
        // Create test merchants with specific merchant IDs to identify them
        Merchant::create([
            'name' => 'Zara',
            'country' => 'ES',
            'merchant_id' => 'TEST_ZARA_001',
        ]);

        Merchant::create([
            'name' => 'Amazon',
            'country' => 'US',
            'merchant_id' => 'TEST_AMZN_001',
        ]);

        // Query only the merchants we just created
        $merchants = Merchant::whereIn('merchant_id', ['TEST_ZARA_001', 'TEST_AMZN_001'])
            ->orderBy('country')
            ->get();

        $this->assertCount(2, $merchants);
        $this->assertEquals('ES', $merchants->first()->country);
        $this->assertEquals('US', $merchants->last()->country);

        $merchantsDesc = Merchant::whereIn('merchant_id', ['TEST_ZARA_001', 'TEST_AMZN_001'])
            ->orderBy('country', 'desc')
            ->get();

        $this->assertCount(2, $merchantsDesc);
        $this->assertEquals('US', $merchantsDesc->first()->country);
        $this->assertEquals('ES', $merchantsDesc->last()->country);
    }

    #[Test]
    public function test_merchant_dto_normalizes_eur_country_code(): void
    {
        // Test that DTO accepts "EUR" as country value
        $dto = MerchantDTO::fromArray([
            'Name' => 'Eurozone Merchant',
            'CountryISOAlpha3' => 'EUR',
            'RetailerId' => 'EUR001',
            'LongDescription' => 'Available in Eurozone',
            'ImageUrl' => 'https://example.com/euro.jpg',
        ]);

        $this->assertEquals('Eurozone Merchant', $dto->name);
        $this->assertEquals('EUR', $dto->country);
        $this->assertEquals('EUR001', $dto->merchant_id);
        $this->assertEquals('Available in Eurozone', $dto->description);
        $this->assertEquals('https://example.com/euro.jpg', $dto->image_url);
    }

    #[Test]
    public function test_merchant_can_be_created_with_eur_country(): void
    {
        // Test that Merchant model accepts EUR as country
        $dto = new MerchantDTO(
            name: 'EUR Merchant',
            country: 'EUR',
            merchant_id: 'EUR002',
            description: 'Eurozone merchant',
            image_url: null
        );

        $merchant = Merchant::fromDTO($dto);

        $this->assertEquals('EUR Merchant', $merchant->name);
        $this->assertEquals('EUR', $merchant->country);
        $this->assertEquals('EUR002', $merchant->merchant_id);
    }

    #[Test]
    public function test_merchant_dto_handles_regular_country_codes(): void
    {
        // Ensure regular country codes still work
        $dto = MerchantDTO::fromArray([
            'Name' => 'French Merchant',
            'CountryISOAlpha3' => 'FRA',
            'RetailerId' => 'FRA001',
        ]);

        $this->assertEquals('FRA', $dto->country);
    }

    #[Test]
    public function test_merchant_dto_normalizes_lowercase_eur(): void
    {
        // Test that lowercase "eur" is normalized to uppercase
        $dto = MerchantDTO::fromArray([
            'Name' => 'Lowercase EUR',
            'CountryISOAlpha3' => 'eur',
            'RetailerId' => 'EUR003',
        ]);

        $this->assertEquals('EUR', $dto->country);
    }

    #[Test]
    public function test_merchant_dto_handles_null_country(): void
    {
        // Test that null country is handled properly
        $dto = MerchantDTO::fromArray([
            'Name' => 'No Country Merchant',
            'RetailerId' => 'NOCOUNTRY001',
        ]);

        $this->assertNull($dto->country);
    }
}
