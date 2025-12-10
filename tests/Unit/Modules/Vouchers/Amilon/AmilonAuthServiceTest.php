<?php

namespace Tests\Unit\Modules\Vouchers\Amilon;

use App\Integrations\Vouchers\Amilon\Services\AmilonAuthService;
use Exception;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('amilon')]
#[Group('vouchers')]
class AmilonAuthServiceTest extends TestCase
{
    private AmilonAuthService $amilonAuthService;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up config values for testing
        config([
            'services.amilon.token_url' => 'https://test-api.amilon.eu/oauth/token',
            'services.amilon.client_id' => 'test-client-id',
            'services.amilon.client_secret' => 'test-client-secret',
            'services.amilon.username' => 'test-username',
            'services.amilon.password' => 'test-password',
        ]);

        $this->amilonAuthService = new AmilonAuthService;
    }

    #[Test]
    public function test_get_access_token_returns_valid_token(): void
    {
        // Mock HTTP response for token request
        Http::fake([
            '*' => Http::response([
                'access_token' => 'mock-token-123',
                'expires_in' => 300,
                'token_type' => 'Bearer',
            ], 200),
        ]);

        // Call the service method
        $token = $this->amilonAuthService->getAccessToken();

        // Assert the token is returned correctly
        $this->assertEquals('mock-token-123', $token);

        // Verify that the API was called with correct parameters
        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://test-api.amilon.eu/oauth/token' &&
                   $request->data()['grant_type'] === 'password' &&
                   $request->data()['client_id'] === 'test-client-id' &&
                   $request->data()['client_secret'] === 'test-client-secret' &&
                   $request->data()['username'] === 'test-username' &&
                   $request->data()['password'] === 'test-password';
        });
    }

    #[Test]
    public function test_get_access_token_throws_exception_on_error(): void
    {
        // Mock HTTP response for failed token request
        Http::fake([
            '*' => Http::response([
                'error' => 'invalid_client',
                'error_description' => 'Invalid client credentials',
            ], 401),
        ]);

        // Expect an exception
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Failed to authenticate with Amilon');

        // Call the service method
        $this->amilonAuthService->getAccessToken();
    }
}
