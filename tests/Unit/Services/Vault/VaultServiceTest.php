<?php

namespace Tests\Unit\Services\Vault;

use App\DTOs\Vault\VaultSessionDTO;
use App\Exceptions\Vault\VaultException;
use App\Models\Financer;
use App\Services\Vault\VaultService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('apideck')]
class VaultServiceTest extends TestCase
{
    private VaultService $vaultService;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.apideck.base_url', 'https://unify.apideck.com');
        Config::set('services.apideck.key', 'test-api-key');
        Config::set('services.apideck.app_id', 'test-app-id');
        Config::set('services.apideck.consumer_id', 'test-consumer-id');

        $this->vaultService = new VaultService;
    }

    #[Test]
    public function it_creates_session_with_valid_financer(): void
    {
        $financer = new Financer([
            'id' => 'test-financer-id',
            'external_id' => json_encode([
                'sirh' => [
                    'consumer_id' => 'test-consumer-123',
                ],
            ]),
        ]);

        $settings = [
            'unified_apis' => ['hris'],
            'isolation_mode' => false,
        ];

        $redirectUri = 'https://app.upengage.com/settings/integrations/callback';

        Http::fake([
            'https://unify.apideck.com/vault/sessions' => Http::response([
                'data' => [
                    'session_token' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.test',
                    'consumer_metadata' => [
                        'account_name' => 'Test Company',
                        'user_name' => 'Test User',
                        'image' => 'https://example.com/logo.png',
                    ],
                    'settings' => $settings,
                    'created_at' => '2025-06-23T10:00:00Z',
                    'expires_at' => '2025-06-23T11:00:00Z',
                ],
            ], 200),
        ]);

        $result = $this->vaultService->createSession($financer, 'test-consumer-123', $redirectUri, $settings);

        $this->assertInstanceOf(VaultSessionDTO::class, $result);
        $this->assertEquals('eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.test', $result->sessionToken);
        $this->assertEquals('2025-06-23T11:00:00Z', $result->expiresAt);
        $this->assertEquals('https://vault.apideck.com/auth/connect/eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.test', $result->vaultUrl);

        Http::assertSent(function (Request $request): bool {
            return $request->hasHeader('Authorization', 'Bearer test-api-key') &&
                   $request->hasHeader('x-apideck-app-id', 'test-app-id') &&
                   $request->hasHeader('x-apideck-consumer-id', 'test-consumer-123') &&
                   $request->hasHeader('Content-Type', 'application/json') &&
                   $request->url() === 'https://unify.apideck.com/vault/sessions' &&
                   $request['redirect_uri'] === 'https://app.upengage.com/settings/integrations/callback' &&
                   $request['settings']['unified_apis'] === ['hris'];
        });
    }

    #[Test]
    public function it_throws_exception_with_invalid_consumer_id(): void
    {
        $financer = new Financer([
            'id' => 'test-financer-id',
            'external_id' => json_encode([
                'sirh' => [],
            ]),
        ]);

        $this->expectException(VaultException::class);
        $this->expectExceptionMessage('Consumer ID is required');

        // The service should extract consumer ID from financer
        $externalId = json_decode($financer->external_id, true);
        $consumerId = $externalId['sirh']['consumer_id'] ?? '';

        $this->vaultService->createSession($financer, $consumerId, 'https://example.com', []);
    }

    #[Test]
    public function it_handles_apideck_401_error(): void
    {
        $financer = new Financer([
            'id' => 'test-financer-id',
            'external_id' => json_encode([
                'sirh' => [
                    'consumer_id' => 'test-consumer-123',
                ],
            ]),
        ]);

        Http::fake([
            'https://unify.apideck.com/vault/sessions' => Http::response([
                'error' => [
                    'message' => 'Unauthorized',
                    'code' => 'UNAUTHORIZED',
                ],
            ], 401),
        ]);

        $this->expectException(VaultException::class);
        $this->expectExceptionMessage('Apideck API authentication failed');
        $this->expectExceptionCode(401);

        $this->vaultService->createSession($financer, 'test-consumer-123', 'https://example.com', []);
    }

    #[Test]
    public function it_handles_apideck_404_error(): void
    {
        $financer = new Financer([
            'id' => 'test-financer-id',
            'external_id' => json_encode([
                'sirh' => [
                    'consumer_id' => 'test-consumer-123',
                ],
            ]),
        ]);

        Http::fake([
            'https://unify.apideck.com/vault/sessions' => Http::response([
                'error' => [
                    'message' => 'Consumer not found',
                    'code' => 'NOT_FOUND',
                ],
            ], 404),
        ]);

        $this->expectException(VaultException::class);
        $this->expectExceptionMessage('Consumer not found in Apideck');
        $this->expectExceptionCode(404);

        $this->vaultService->createSession($financer, 'test-consumer-123', 'https://example.com', []);
    }

    #[Test]
    public function it_handles_apideck_500_error(): void
    {
        $financer = new Financer([
            'id' => 'test-financer-id',
            'external_id' => json_encode([
                'sirh' => [
                    'consumer_id' => 'test-consumer-123',
                ],
            ]),
        ]);

        Http::fake([
            'https://unify.apideck.com/vault/sessions' => Http::response([
                'error' => [
                    'message' => 'Internal server error',
                    'code' => 'INTERNAL_ERROR',
                ],
            ], 500),
        ]);

        $this->expectException(VaultException::class);
        $this->expectExceptionMessage('Apideck API Error: Internal server error');
        $this->expectExceptionCode(500);

        $this->vaultService->createSession($financer, 'test-consumer-123', 'https://example.com', []);
    }

    #[Test]
    public function it_handles_network_timeout(): void
    {
        $financer = new Financer([
            'id' => 'test-financer-id',
            'external_id' => json_encode([
                'sirh' => [
                    'consumer_id' => 'test-consumer-123',
                ],
            ]),
        ]);

        Http::fake(function (): void {
            throw new ConnectionException('Connection timed out');
        });

        $this->expectException(VaultException::class);
        $this->expectExceptionMessage('Network error while connecting to Apideck');

        $this->vaultService->createSession($financer, 'test-consumer-123', 'https://example.com', []);
    }

    #[Test]
    public function it_retries_on_503_error(): void
    {
        $financer = new Financer([
            'id' => 'test-financer-id',
            'external_id' => json_encode([
                'sirh' => [
                    'consumer_id' => 'test-consumer-123',
                ],
            ]),
        ]);

        $callCount = 0;
        Http::fake(function () use (&$callCount) {
            $callCount++;
            if ($callCount < 3) {
                return Http::response(['error' => 'Service unavailable'], 503);
            }

            return Http::response([
                'data' => [
                    'session_token' => 'success-token',
                    'created_at' => '2025-06-23T10:00:00Z',
                    'expires_at' => '2025-06-23T11:00:00Z',
                ],
            ], 200);
        });

        $result = $this->vaultService->createSession($financer, 'test-consumer-123', 'https://example.com', []);

        $this->assertEquals('success-token', $result->sessionToken);
        $this->assertEquals(3, $callCount);
    }

    #[Test]
    public function it_validates_consumer_id_format(): void
    {
        $financer = new Financer([
            'id' => 'test-financer-id',
            'external_id' => json_encode([
                'sirh' => [
                    'consumer_id' => '',
                ],
            ]),
        ]);

        $this->expectException(VaultException::class);
        $this->expectExceptionMessage('Consumer ID is required');

        // The service should extract consumer ID from financer
        $externalId = json_decode($financer->external_id, true);
        $consumerId = $externalId['sirh']['consumer_id'] ?? '';

        $this->vaultService->createSession($financer, $consumerId, 'https://example.com', []);
    }

    #[Test]
    public function it_includes_optional_settings_in_request(): void
    {
        $financer = new Financer([
            'id' => 'test-financer-id',
            'external_id' => json_encode([
                'sirh' => [
                    'consumer_id' => 'test-consumer-123',
                ],
            ]),
        ]);

        $settings = [
            'unified_apis' => ['hris', 'ats'],
            'isolation_mode' => true,
            'show_sidebar' => false,
            'show_suggestions' => false,
        ];

        Http::fake([
            'https://unify.apideck.com/vault/sessions' => Http::response([
                'data' => [
                    'session_token' => 'test-token',
                    'created_at' => '2025-06-23T10:00:00Z',
                    'expires_at' => '2025-06-23T11:00:00Z',
                ],
            ], 200),
        ]);

        $this->vaultService->createSession($financer, 'test-consumer-123', 'https://example.com', $settings);

        Http::assertSent(function (Request $request) use ($settings): bool {
            return isset($request['settings']) &&
                   $request['settings'] === $settings;
        });
    }

    #[Test]
    public function it_handles_malformed_json_response(): void
    {
        $financer = new Financer([
            'id' => 'test-financer-id',
            'external_id' => json_encode([
                'sirh' => [
                    'consumer_id' => 'test-consumer-123',
                ],
            ]),
        ]);

        Http::fake([
            'https://unify.apideck.com/vault/sessions' => Http::response('Invalid JSON', 200),
        ]);

        $this->expectException(VaultException::class);
        $this->expectExceptionMessage('Invalid response from Apideck API');

        $this->vaultService->createSession($financer, 'test-consumer-123', 'https://example.com', []);
    }

    #[Test]
    public function it_sends_service_id_header_when_provided_in_settings(): void
    {
        $financer = new Financer([
            'id' => 'test-financer-id',
            'external_id' => json_encode([
                'sirh' => [
                    'consumer_id' => 'test-consumer-123',
                ],
            ]),
        ]);

        $settings = [
            'unified_apis' => ['hris'],
            'service_id' => 'personio',
        ];

        Http::fake([
            'https://unify.apideck.com/vault/sessions' => Http::response([
                'data' => [
                    'session_token' => 'test-token',
                    'created_at' => '2025-06-23T10:00:00Z',
                    'expires_at' => '2025-06-23T11:00:00Z',
                ],
            ], 200),
        ]);

        $this->vaultService->createSession($financer, 'test-consumer-123', 'https://example.com', $settings);

        Http::assertSent(function (Request $request): bool {
            return $request->hasHeader('x-apideck-service-id', 'personio') &&
                   ! isset($request['settings']['service_id']); // service_id should be removed from settings
        });
    }

    #[Test]
    public function it_does_not_send_service_id_header_when_not_provided(): void
    {
        $financer = new Financer([
            'id' => 'test-financer-id',
            'external_id' => json_encode([
                'sirh' => [
                    'consumer_id' => 'test-consumer-123',
                ],
            ]),
        ]);

        $settings = [
            'unified_apis' => ['hris'],
        ];

        Http::fake([
            'https://unify.apideck.com/vault/sessions' => Http::response([
                'data' => [
                    'session_token' => 'test-token',
                    'created_at' => '2025-06-23T10:00:00Z',
                    'expires_at' => '2025-06-23T11:00:00Z',
                ],
            ], 200),
        ]);

        $this->vaultService->createSession($financer, 'test-consumer-123', 'https://example.com', $settings);

        Http::assertSent(function (Request $request): bool {
            return ! $request->hasHeader('x-apideck-service-id');
        });
    }

    #[Test]
    public function it_supports_all_allowed_service_ids(): void
    {
        $financer = new Financer([
            'id' => 'test-financer-id',
            'external_id' => json_encode([
                'sirh' => [
                    'consumer_id' => 'test-consumer-123',
                ],
            ]),
        ]);

        $allowedServices = ['bamboohr', 'personio', 'workday', 'hibob', 'namely', 'sage-hr', 'adp-workforce-now'];

        foreach ($allowedServices as $serviceId) {
            Http::fake([
                'https://unify.apideck.com/vault/sessions' => Http::response([
                    'data' => [
                        'session_token' => "token-{$serviceId}",
                        'created_at' => '2025-06-23T10:00:00Z',
                        'expires_at' => '2025-06-23T11:00:00Z',
                    ],
                ], 200),
            ]);

            $settings = [
                'unified_apis' => ['hris'],
                'service_id' => $serviceId,
            ];

            $this->vaultService->createSession($financer, 'test-consumer-123', 'https://example.com', $settings);

            Http::assertSent(function (Request $request) use ($serviceId): bool {
                return $request->hasHeader('x-apideck-service-id', $serviceId);
            });
        }
    }
}
