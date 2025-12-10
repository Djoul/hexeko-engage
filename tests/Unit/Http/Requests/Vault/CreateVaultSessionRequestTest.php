<?php

namespace Tests\Unit\Http\Requests\Vault;

use App\Http\Requests\Vault\CreateVaultSessionRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('apideck')]
class CreateVaultSessionRequestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.vault.allowed_services', ['factorialhr', 'bamboohr', 'personio', 'workday']);
    }

    #[Test]
    public function it_requires_financer_id(): void
    {
        $request = new CreateVaultSessionRequest;
        $validator = Validator::make([], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('financer_id', $validator->errors()->toArray());
    }

    #[Test]
    public function it_validates_financer_id_is_uuid(): void
    {
        $request = new CreateVaultSessionRequest;
        $validator = Validator::make([
            'financer_id' => 'not-a-uuid',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('financer_id', $validator->errors()->toArray());
    }

    #[Test]
    public function it_validates_financer_id_exists_in_database(): void
    {
        $request = new CreateVaultSessionRequest;
        $validator = Validator::make([
            'financer_id' => '550e8400-e29b-41d4-a716-446655440000',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('financer_id', $validator->errors()->toArray());
    }

    #[Test]
    public function it_passes_with_valid_financer_id(): void
    {
        $financer = ModelFactory::createFinancer();

        $request = new CreateVaultSessionRequest;
        $validator = Validator::make([
            'financer_id' => $financer->id,
            'redirect_uri' => 'https://example.com',
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    #[Test]
    public function it_requires_redirect_uri(): void
    {
        $request = new CreateVaultSessionRequest;
        $validator = Validator::make([
            'financer_id' => '550e8400-e29b-41d4-a716-446655440000',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('redirect_uri', $validator->errors()->toArray());
    }

    #[Test]
    public function it_validates_redirect_uri_is_url(): void
    {
        $request = new CreateVaultSessionRequest;
        $validator = Validator::make([
            'financer_id' => '550e8400-e29b-41d4-a716-446655440000',
            'redirect_uri' => 'not-a-url',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('redirect_uri', $validator->errors()->toArray());
    }

    #[Test]
    public function it_validates_redirect_uri_is_https(): void
    {
        $request = new CreateVaultSessionRequest;
        $validator = Validator::make([
            'financer_id' => '550e8400-e29b-41d4-a716-446655440000',
            'redirect_uri' => 'http://example.com',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('redirect_uri', $validator->errors()->toArray());
    }

    #[Test]
    public function it_passes_with_valid_https_redirect_uri(): void
    {
        $financer = ModelFactory::createFinancer();

        $request = new CreateVaultSessionRequest;
        $validator = Validator::make([
            'financer_id' => $financer->id,
            'redirect_uri' => 'https://app.upengage.com/callback',
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    #[Test]
    public function it_validates_unified_apis_contains_hris(): void
    {
        $financer = ModelFactory::createFinancer();

        $request = new CreateVaultSessionRequest;
        $validator = Validator::make([
            'financer_id' => $financer->id,
            'redirect_uri' => 'https://example.com',
            'settings' => [
                'unified_apis' => ['ats', 'crm'],
            ],
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('settings.unified_apis', $validator->errors()->toArray());
    }

    #[Test]
    public function it_passes_when_unified_apis_contains_hris(): void
    {
        $financer = ModelFactory::createFinancer();

        $request = new CreateVaultSessionRequest;
        $validator = Validator::make([
            'financer_id' => $financer->id,
            'redirect_uri' => 'https://example.com',
            'settings' => [
                'unified_apis' => ['hris', 'ats'],
            ],
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    #[Test]
    public function it_validates_isolation_mode_is_boolean(): void
    {
        $financer = ModelFactory::createFinancer();

        $request = new CreateVaultSessionRequest;
        $validator = Validator::make([
            'financer_id' => $financer->id,
            'redirect_uri' => 'https://example.com',
            'settings' => [
                'unified_apis' => ['hris'],
                'isolation_mode' => 'not-boolean',
            ],
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('settings.isolation_mode', $validator->errors()->toArray());
    }

    #[Test]
    public function it_passes_with_boolean_isolation_mode(): void
    {
        $financer = ModelFactory::createFinancer();

        $request = new CreateVaultSessionRequest;
        $validator = Validator::make([
            'financer_id' => $financer->id,
            'redirect_uri' => 'https://example.com',
            'settings' => [
                'unified_apis' => ['hris'],
                'isolation_mode' => true,
            ],
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    #[Test]
    public function it_validates_show_sidebar_is_boolean(): void
    {
        $financer = ModelFactory::createFinancer();

        $request = new CreateVaultSessionRequest;
        $validator = Validator::make([
            'financer_id' => $financer->id,
            'redirect_uri' => 'https://example.com',
            'settings' => [
                'unified_apis' => ['hris'],
                'show_sidebar' => 'not-boolean',
            ],
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('settings.show_sidebar', $validator->errors()->toArray());
    }

    #[Test]
    public function it_allows_optional_settings(): void
    {
        $financer = ModelFactory::createFinancer();

        $request = new CreateVaultSessionRequest;
        $validator = Validator::make([
            'financer_id' => $financer->id,
            'redirect_uri' => 'https://example.com',
            // No settings provided
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    #[Test]
    public function it_validates_unified_apis_is_array(): void
    {
        $financer = ModelFactory::createFinancer();

        $request = new CreateVaultSessionRequest;
        $validator = Validator::make([
            'financer_id' => $financer->id,
            'redirect_uri' => 'https://example.com',
            'settings' => [
                'unified_apis' => 'not-an-array',
            ],
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('settings.unified_apis', $validator->errors()->toArray());
    }

    #[Test]
    public function it_passes_with_complete_valid_data(): void
    {
        $financer = ModelFactory::createFinancer();

        $request = new CreateVaultSessionRequest;
        $validator = Validator::make([
            'financer_id' => $financer->id,
            'redirect_uri' => 'https://app.upengage.com/settings/integrations/callback',
            'settings' => [
                'unified_apis' => ['hris', 'ats'],
                'isolation_mode' => false,
                'show_sidebar' => true,
                'show_suggestions' => false,
            ],
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    #[Test]
    public function it_provides_custom_error_messages(): void
    {
        $request = new CreateVaultSessionRequest;
        $messages = $request->messages();

        $this->assertArrayHasKey('financer_id.required', $messages);
        $this->assertArrayHasKey('financer_id.uuid', $messages);
        $this->assertArrayHasKey('financer_id.exists', $messages);
        $this->assertArrayHasKey('redirect_uri.required', $messages);
        $this->assertArrayHasKey('redirect_uri.url', $messages);
        $this->assertArrayHasKey('redirect_uri.regex', $messages);
        $this->assertArrayHasKey('settings.unified_apis.required', $messages);
    }

    #[Test]
    public function it_provides_attribute_names(): void
    {
        $request = new CreateVaultSessionRequest;
        $attributes = $request->attributes();

        $this->assertArrayHasKey('financer_id', $attributes);
        $this->assertArrayHasKey('redirect_uri', $attributes);
        $this->assertArrayHasKey('settings.unified_apis', $attributes);
        $this->assertArrayHasKey('settings.show_sidebar', $attributes);
        $this->assertArrayHasKey('settings.isolation_mode', $attributes);
    }

    #[Test]
    public function it_validates_service_id_is_in_allowed_list(): void
    {
        $financer = ModelFactory::createFinancer();

        $request = new CreateVaultSessionRequest;
        $validator = Validator::make([
            'financer_id' => $financer->id,
            'redirect_uri' => 'https://example.com',
            'settings' => [
                'service_id' => 'invalid-service',
            ],
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('settings.service_id', $validator->errors()->toArray());
    }

    #[Test]
    public function it_accepts_valid_service_ids(): void
    {
        $financer = ModelFactory::createFinancer();
        $allowedServices = ['bamboohr', 'personio', 'workday', 'hibob', 'namely', 'sage-hr', 'adp-workforce-now'];

        foreach ($allowedServices as $serviceId) {
            $request = new CreateVaultSessionRequest;
            $validator = Validator::make([
                'financer_id' => $financer->id,
                'redirect_uri' => 'https://example.com',
                'settings' => [
                    'service_id' => $serviceId,
                ],
            ], $request->rules());

            $this->assertFalse($validator->fails(), "Service ID '{$serviceId}' should be valid");
        }
    }

    #[Test]
    public function it_allows_service_id_to_be_optional(): void
    {
        $financer = ModelFactory::createFinancer();

        $request = new CreateVaultSessionRequest;
        $validator = Validator::make([
            'financer_id' => $financer->id,
            'redirect_uri' => 'https://example.com',
            'settings' => [
                'unified_apis' => ['hris'],
                // No service_id provided - should pass
            ],
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    #[Test]
    public function it_validates_service_id_is_string(): void
    {
        $financer = ModelFactory::createFinancer();

        $request = new CreateVaultSessionRequest;
        $validator = Validator::make([
            'financer_id' => $financer->id,
            'redirect_uri' => 'https://example.com',
            'settings' => [
                'service_id' => 123, // Should be string
            ],
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('settings.service_id', $validator->errors()->toArray());
    }

    #[Test]
    public function it_provides_service_id_custom_error_message(): void
    {
        $request = new CreateVaultSessionRequest;
        $messages = $request->messages();

        $this->assertArrayHasKey('settings.service_id.in', $messages);
        $this->assertStringContainsString('bamboohr', $messages['settings.service_id.in']);
        $this->assertStringContainsString('personio', $messages['settings.service_id.in']);
    }
}
