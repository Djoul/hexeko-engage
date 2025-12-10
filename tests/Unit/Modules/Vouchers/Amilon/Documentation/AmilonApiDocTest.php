<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Vouchers\Amilon\Documentation;

use App\Documentation\ThirdPartyApis\BaseApiDoc;
use App\Integrations\Vouchers\Amilon\Documentation\AmilonApiDoc;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('third-party-api-doc')]
class AmilonApiDocTest extends TestCase
{
    #[Test]
    public function it_extends_base_api_doc(): void
    {
        $this->assertTrue(is_subclass_of(AmilonApiDoc::class, BaseApiDoc::class));
    }

    #[Test]
    public function it_provides_amilon_api_information(): void
    {
        $this->assertEquals('v1', AmilonApiDoc::getApiVersion());
        $this->assertEquals('amilon', AmilonApiDoc::getProviderName());
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', AmilonApiDoc::getLastVerified());
    }

    #[Test]
    public function it_documents_authentication_endpoint(): void
    {
        $doc = AmilonApiDoc::authenticate();

        $this->assertIsArray($doc);
        $this->assertEquals('Authenticate with Amilon OAuth 2.0 server', $doc['description']);
        $this->assertEquals('POST /connect/token', $doc['endpoint']);
        $this->assertArrayHasKey('parameters', $doc);
        $this->assertArrayHasKey('grant_type', $doc['parameters']);
        $this->assertArrayHasKey('client_id', $doc['parameters']);
        $this->assertArrayHasKey('client_secret', $doc['parameters']);
        $this->assertArrayHasKey('responses', $doc);
    }

    #[Test]
    public function it_documents_products_list_endpoint(): void
    {
        $doc = AmilonApiDoc::listProducts();

        $this->assertIsArray($doc);
        $this->assertEquals('Get complete list of products for a contract and culture', $doc['description']);
        $this->assertEquals('GET /contracts/{contractId}/{culture}/products/complete', $doc['endpoint']);
        $this->assertArrayHasKey('parameters', $doc);
        $this->assertArrayHasKey('contractId', $doc['parameters']);
        $this->assertArrayHasKey('culture', $doc['parameters']);
        $this->assertArrayHasKey('responses', $doc);
    }

    #[Test]
    public function it_documents_create_order_endpoint(): void
    {
        $doc = AmilonApiDoc::createOrder();

        $this->assertIsArray($doc);
        $this->assertEquals('Create a new voucher order', $doc['description']);
        $this->assertEquals('POST /Orders/create/{contractId}', $doc['endpoint']);
        $this->assertArrayHasKey('parameters', $doc);
        $this->assertArrayHasKey('contractId', $doc['parameters']);
        $this->assertArrayHasKey('body', $doc);
        $this->assertTrue($doc['parameters']['contractId']['required']);
        $this->assertArrayHasKey('responses', $doc);
    }

    #[Test]
    public function it_documents_merchants_endpoint(): void
    {
        $doc = AmilonApiDoc::listRetailers();

        $this->assertIsArray($doc);
        $this->assertEquals('Get list of retailers for a specific contract and culture', $doc['description']);
        $this->assertEquals('GET /contracts/{contractId}/{culture}/retailers', $doc['endpoint']);
        $this->assertArrayHasKey('parameters', $doc);
        $this->assertArrayHasKey('contractId', $doc['parameters']);
        $this->assertArrayHasKey('culture', $doc['parameters']);
        $this->assertArrayHasKey('responses', $doc);
    }

    #[Test]
    public function it_lists_all_documented_endpoints(): void
    {
        $endpoints = AmilonApiDoc::getAllEndpoints();

        $this->assertIsArray($endpoints);
        $this->assertContains('authenticate', $endpoints);
        $this->assertContains('listProducts', $endpoints);
        $this->assertContains('createOrder', $endpoints);
        $this->assertContains('listRetailers', $endpoints);
        $this->assertGreaterThanOrEqual(4, count($endpoints));
    }

    #[Test]
    public function it_includes_example_calls_in_documentation(): void
    {
        $doc = AmilonApiDoc::createOrder();

        $this->assertArrayHasKey('example_call', $doc);
        $this->assertIsArray($doc['example_call']);
        $this->assertArrayHasKey('externalOrderId', $doc['example_call']);
        $this->assertArrayHasKey('orderRows', $doc['example_call']);
    }
}
