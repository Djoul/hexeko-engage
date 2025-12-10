<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Payments\Stripe\Documentation;

use App\Documentation\ThirdPartyApis\BaseApiDoc;
use App\Integrations\Payments\Stripe\Documentation\StripeApiDoc;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('third-party-api-doc')]
#[Group('stripe')]
class StripeApiDocTest extends TestCase
{
    #[Test]
    public function it_extends_base_api_doc(): void
    {
        $this->assertTrue(is_subclass_of(StripeApiDoc::class, BaseApiDoc::class));
    }

    #[Test]
    public function it_provides_stripe_api_information(): void
    {
        $this->assertEquals('stripe', StripeApiDoc::getProviderName());
        $this->assertEquals('2023-10-16', StripeApiDoc::getApiVersion());
        $this->assertEquals('2025-08-08', StripeApiDoc::getLastVerified());
    }

    #[Test]
    public function it_documents_payment_intent_create_endpoint(): void
    {
        $doc = StripeApiDoc::createPaymentIntent();

        $this->assertIsArray($doc);
        $this->assertEquals('Créer une intention de paiement', $doc['description']);
        $this->assertEquals('POST /v1/payment_intents', $doc['endpoint']);
        $this->assertArrayHasKey('parameters', $doc);
        $this->assertArrayHasKey('amount', $doc['parameters']);
        $this->assertArrayHasKey('currency', $doc['parameters']);
        $this->assertTrue($doc['parameters']['amount']['required']);
        $this->assertTrue($doc['parameters']['currency']['required']);
        $this->assertArrayHasKey('responses', $doc);
        $this->assertArrayHasKey('200', $doc['responses']);
        $this->assertArrayHasKey('400', $doc['responses']);
    }

    #[Test]
    public function it_documents_checkout_session_create_endpoint(): void
    {
        $doc = StripeApiDoc::createCheckoutSession();

        $this->assertIsArray($doc);
        $this->assertEquals('Créer une session de paiement Checkout', $doc['description']);
        $this->assertEquals('POST /v1/checkout/sessions', $doc['endpoint']);
        $this->assertArrayHasKey('parameters', $doc);
        $this->assertArrayHasKey('payment_method_types', $doc['parameters']);
        $this->assertArrayHasKey('line_items', $doc['parameters']);
        $this->assertArrayHasKey('mode', $doc['parameters']);
        $this->assertArrayHasKey('success_url', $doc['parameters']);
        $this->assertArrayHasKey('cancel_url', $doc['parameters']);
        $this->assertTrue($doc['parameters']['line_items']['required']);
        $this->assertTrue($doc['parameters']['success_url']['required']);
    }

    #[Test]
    public function it_documents_customer_create_endpoint(): void
    {
        $doc = StripeApiDoc::createCustomer();

        $this->assertIsArray($doc);
        $this->assertEquals('Créer un client dans Stripe', $doc['description']);
        $this->assertEquals('POST /v1/customers', $doc['endpoint']);
        $this->assertArrayHasKey('parameters', $doc);
        $this->assertArrayHasKey('email', $doc['parameters']);
        $this->assertArrayHasKey('name', $doc['parameters']);
        $this->assertArrayHasKey('metadata', $doc['parameters']);
    }

    #[Test]
    public function it_documents_refund_create_endpoint(): void
    {
        $doc = StripeApiDoc::createRefund();

        $this->assertIsArray($doc);
        $this->assertEquals('Créer un remboursement', $doc['description']);
        $this->assertEquals('POST /v1/refunds', $doc['endpoint']);
        $this->assertArrayHasKey('parameters', $doc);
        $this->assertArrayHasKey('payment_intent', $doc['parameters']);
        $this->assertArrayHasKey('amount', $doc['parameters']);
        $this->assertArrayHasKey('reason', $doc['parameters']);
    }

    #[Test]
    public function it_documents_webhook_events(): void
    {
        $doc = StripeApiDoc::webhookEvents();

        $this->assertIsArray($doc);
        $this->assertEquals('Événements webhook envoyés par Stripe', $doc['description']);
        $this->assertArrayHasKey('events', $doc);
        $this->assertArrayHasKey('payment_intent.succeeded', $doc['events']);
        $this->assertArrayHasKey('payment_intent.payment_failed', $doc['events']);
        $this->assertArrayHasKey('checkout.session.completed', $doc['events']);
        $this->assertArrayHasKey('charge.refunded', $doc['events']);
    }

    #[Test]
    public function it_lists_all_documented_endpoints(): void
    {
        $endpoints = StripeApiDoc::getAllEndpoints();

        $this->assertIsArray($endpoints);
        $this->assertContains('createPaymentIntent', $endpoints);
        $this->assertContains('retrievePaymentIntent', $endpoints);
        $this->assertContains('confirmPaymentIntent', $endpoints);
        $this->assertContains('createCheckoutSession', $endpoints);
        $this->assertContains('retrieveCheckoutSession', $endpoints);
        $this->assertContains('createCustomer', $endpoints);
        $this->assertContains('createRefund', $endpoints);
        $this->assertContains('webhookEvents', $endpoints);
        $this->assertGreaterThanOrEqual(8, count($endpoints));
    }

    #[Test]
    public function it_includes_example_calls_in_documentation(): void
    {
        $doc = StripeApiDoc::createPaymentIntent();

        $this->assertArrayHasKey('example_call', $doc);
        $this->assertIsArray($doc['example_call']);
        $this->assertArrayHasKey('amount', $doc['example_call']);
        $this->assertArrayHasKey('currency', $doc['example_call']);
        $this->assertArrayHasKey('automatic_payment_methods', $doc['example_call']);
    }

    #[Test]
    public function it_includes_notes_for_important_information(): void
    {
        $doc = StripeApiDoc::createPaymentIntent();

        $this->assertArrayHasKey('notes', $doc);
        $this->assertIsArray($doc['notes']);
        $this->assertNotEmpty($doc['notes']);
        $this->assertStringContainsString('centimes', $doc['notes'][0]);
    }

    #[Test]
    public function it_provides_documentation_urls(): void
    {
        $doc = StripeApiDoc::createPaymentIntent();

        $this->assertArrayHasKey('documentation_url', $doc);
        $this->assertStringStartsWith('https://docs.stripe.com/', $doc['documentation_url']);
    }

    #[Test]
    public function it_handles_missing_response_files_gracefully(): void
    {
        // Test that loadResponse returns empty array when file doesn't exist
        $doc = StripeApiDoc::createPaymentIntent();

        // Should not throw exception and should have default responses
        $this->assertArrayHasKey('responses', $doc);
        $this->assertArrayHasKey('200', $doc['responses']);
        $this->assertNotEmpty($doc['responses']['200']);
    }
}
