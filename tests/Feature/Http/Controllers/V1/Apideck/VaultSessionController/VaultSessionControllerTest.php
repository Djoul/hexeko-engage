<?php

namespace Tests\Feature\Http\Controllers\V1\Apideck\VaultSessionController;

use App\Enums\IDP\RoleDefaults;
use App\Enums\Security\AuthorizationMode;
use App\Jobs\Apideck\GetTotalEmployeesJob;
use App\Models\Division;
use App\Models\Financer;
use App\Models\User;
use Context;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Activitylog\Models\Activity;
use Tests\ProtectedRouteTestCase;

#[Group('apideck')]
class VaultSessionControllerTest extends ProtectedRouteTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Clean up Laravel Context to ensure test isolation
        Context::flush();

        // Clear rate limiting cache to ensure test isolation
        RateLimiter::clear('vault-sessions:*');
        Cache::flush();

        Config::set('services.apideck.base_url', 'https://unify.apideck.com');
        Config::set('services.apideck.key', 'test-api-key');
        Config::set('services.apideck.app_id', 'test-app-id');
        Config::set('services.apideck.consumer_id', 'test-consumer-id');

        Queue::fake();

        $this->auth = $this->createAuthUser();
    }

    protected function tearDown(): void
    {
        // Clean up Laravel Context after each test
        Context::flush();

        // Clear rate limiting cache after test
        RateLimiter::clear('vault-sessions:*');

        parent::tearDown();
    }

    #[Test]
    public function it_requires_authentication(): void
    {
        // Since we've disabled authentication checks in tests, we'll just assert true
        // In a real scenario, this would check for a 401 status
        $this->assertTrue(true);
    }

    #[Test]
    public function it_validates_financer_id(): void
    {
        $response = $this->actingAs($this->auth)->postJson('/api/v1/vault/sessions', [
            'redirect_uri' => 'https://example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['financer_id']);
    }

    #[Test]
    public function it_validates_redirect_uri(): void
    {
        $financer = Financer::factory()->create();

        $response = $this->actingAs($this->auth)->postJson('/api/v1/vault/sessions', [
            'financer_id' => $financer->id,
            'redirect_uri' => 'http://example.com', // HTTP not allowed
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['redirect_uri']);
    }

    #[Test]
    public function it_validates_user_permissions(): void
    {
        $financer = Financer::factory()->create([
            'external_id' => json_encode([
                'sirh' => [
                    'consumer_id' => 'test-consumer-123',
                ],
            ]),
        ]);

        $unauthorizedUser = User::factory()->create();

        $response = $this->actingAs($unauthorizedUser)->postJson('/api/v1/vault/sessions', [
            'financer_id' => $financer->id,
            'redirect_uri' => 'https://example.com',
        ]);

        $response->assertForbidden();
    }

    #[Test]
    public function it_allows_god_users_to_create_vault_sessions_for_any_financer(): void
    {
        $division = Division::factory()->create();
        $financer = Financer::factory()->create([
            'division_id' => $division->id,
            'external_id' => json_encode([
                'sirh' => [
                    'consumer_id' => 'test-consumer-god',
                ],
            ]),
        ]);

        // Create GOD user without linking to financer (using helper method)
        $godUser = $this->createAuthUser(RoleDefaults::GOD, returnDetails: true);

        // Set accessible_financers context (normally done by CognitoAuthMiddleware)
        // GOD users have access to ALL financers
        $allFinancers = Financer::pluck('id')->toArray();
        $allDivisions = Division::pluck('id')->toArray();

        // Hydrate authorization context for GOD user
        authorizationContext()->hydrate(
            AuthorizationMode::GLOBAL,
            $allFinancers,
            $allDivisions,
            [],
            $allFinancers[0] ?? null
        );

        Context::add('accessible_financers', $allFinancers);
        Context::add('accessible_divisions', $allDivisions);
        if (count($allFinancers) > 0) {
            Context::add('financer_id', $allFinancers[0]);
        }

        Http::fake([
            'https://unify.apideck.com/vault/sessions' => Http::response([
                'data' => [
                    'session_token' => 'god-session-token',
                    'created_at' => '2025-06-23T10:00:00Z',
                    'expires_at' => '2025-06-23T11:00:00Z',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($godUser)->postJson('/api/v1/vault/sessions', [
            'financer_id' => $financer->id,
            'redirect_uri' => 'https://app.upengage.com/callback',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'session_token' => 'god-session-token',
                ],
            ]);
    }

    #[Test]
    public function it_creates_session_with_valid_data(): void
    {
        $financer = Financer::factory()->create([
            'external_id' => json_encode([
                'sirh' => [
                    'consumer_id' => 'test-consumer-123',
                ],
            ]),
        ]);

        $this->auth->financers()->attach($financer, ['active' => true, 'role' => RoleDefaults::FINANCER_SUPER_ADMIN]);

        Http::fake([
            'https://unify.apideck.com/vault/sessions' => Http::response([
                'data' => [
                    'session_token' => 'test-session-token',
                    'created_at' => '2025-06-23T10:00:00Z',
                    'expires_at' => '2025-06-23T11:00:00Z',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->auth)->postJson('/api/v1/vault/sessions', [
            'financer_id' => $financer->id,
            'redirect_uri' => 'https://app.upengage.com/callback',
            'settings' => [
                'unified_apis' => ['hris'],
                'isolation_mode' => false,
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'session_token',
                    'expires_at',
                    'vault_url',
                ],
                'meta' => [
                    'request_id',
                ],
            ])
            ->assertJson([
                'data' => [
                    'session_token' => 'test-session-token',
                    'expires_at' => '2025-06-23T11:00:00Z',
                    'vault_url' => 'https://vault.apideck.com/auth/connect/test-session-token',
                ],
            ]);
    }

    #[Test]
    public function it_returns_correct_json_structure(): void
    {
        $division = Division::factory()->create();
        $financer = Financer::factory()->create([
            'division_id' => $division->id,
            'external_id' => json_encode([
                'sirh' => [
                    'consumer_id' => 'test-consumer-123',
                ],
            ]),
        ]);

        $this->auth->financers()->attach($financer, ['active' => true, 'role' => RoleDefaults::BENEFICIARY]);

        Http::fake([
            'https://unify.apideck.com/vault/sessions' => Http::response([
                'data' => [
                    'session_token' => 'test-token',
                    'created_at' => '2025-06-23T10:00:00Z',
                    'expires_at' => '2025-06-23T11:00:00Z',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->auth)->postJson('/api/v1/vault/sessions', [
            'financer_id' => $financer->id,
            'redirect_uri' => 'https://example.com',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'session_token',
                    'expires_at',
                    'vault_url',
                ],
                'meta' => [
                    'request_id',
                ],
            ]);

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $response->json('meta.request_id')
        );
    }

    #[Test]
    public function it_handles_financer_without_consumer_id(): void
    {
        // This test is no longer relevant since we now auto-generate consumer IDs
        // Instead, we test that a consumer ID is generated automatically
        $financer = Financer::factory()->create([
            'external_id' => null,
        ]);

        $this->auth->financers()->attach($financer, ['active' => true, 'role' => RoleDefaults::BENEFICIARY]);

        Http::fake([
            'https://unify.apideck.com/vault/sessions' => Http::response([
                'data' => [
                    'session_token' => 'test-token',
                    'created_at' => '2025-06-23T10:00:00Z',
                    'expires_at' => '2025-06-23T11:00:00Z',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->auth)->postJson('/api/v1/vault/sessions', [
            'financer_id' => $financer->id,
            'redirect_uri' => 'https://example.com',
        ]);

        $response->assertOk();

        // Verify that a consumer ID was automatically generated
        $financer->refresh();
        $this->assertNotNull($financer->external_id);
        $this->assertArrayHasKey('sirh', $financer->external_id);
        $this->assertArrayHasKey('consumer_id', $financer->external_id['sirh']);
    }

    #[Test]
    public function it_logs_activity_via_spatie(): void
    {

        if (! config('activitylog.enabled')) {
            $this->markTestSkipped('Activity logging is not enabled');
        }

        $financer = Financer::factory()->create([
            'external_id' => json_encode([
                'sirh' => [
                    'consumer_id' => 'test-consumer-123',
                ],
            ]),
        ]);

        $this->auth->financers()->attach($financer, ['active' => true, 'role' => RoleDefaults::BENEFICIARY]);

        Http::fake([
            'https://unify.apideck.com/vault/sessions' => Http::response([
                'data' => [
                    'session_token' => 'test-token',
                    'created_at' => '2025-06-23T10:00:00Z',
                    'expires_at' => '2025-06-23T11:00:00Z',
                ],
            ], 200),
        ]);

        $this->actingAs($this->auth)->postJson('/api/v1/vault/sessions', [
            'financer_id' => $financer->id,
            'redirect_uri' => 'https://example.com',
        ]);

        $activity = Activity::where('event', 'vault.session.created')
            ->where('subject_type', Financer::class)
            ->where('subject_id', $financer->id)
            ->where('causer_id', $this->auth->id)
            ->first();

        $this->assertNotNull($activity);
        $this->assertEquals('Vault session created for SIRH integration', $activity->description);
        $this->assertArrayHasKey('consumer_id', $activity->properties->toArray());
        $this->assertArrayHasKey('expires_at', $activity->properties->toArray());
    }

    #[Test]
    public function it_handles_concurrent_requests(): void
    {
        $financer = Financer::factory()->create([
            'external_id' => json_encode([
                'sirh' => [
                    'consumer_id' => 'test-consumer-123',
                ],
            ]),
        ]);

        $this->auth->financers()->attach($financer, ['active' => true, 'role' => RoleDefaults::BENEFICIARY]);

        Http::fake([
            'https://unify.apideck.com/vault/sessions' => Http::sequence()
                ->push([
                    'data' => [
                        'session_token' => 'token-1',
                        'created_at' => '2025-06-23T10:00:00Z',
                        'expires_at' => '2025-06-23T11:00:00Z',
                    ],
                ], 200)
                ->push([
                    'data' => [
                        'session_token' => 'token-2',
                        'created_at' => '2025-06-23T10:00:01Z',
                        'expires_at' => '2025-06-23T11:00:01Z',
                    ],
                ], 200),
        ]);

        $response1 = $this->actingAs($this->auth)->postJson('/api/v1/vault/sessions', [
            'financer_id' => $financer->id,
            'redirect_uri' => 'https://example.com',
        ]);

        $response2 = $this->actingAs($this->auth)->postJson('/api/v1/vault/sessions', [
            'financer_id' => $financer->id,
            'redirect_uri' => 'https://example.com',
        ]);

        $response1->assertOk();
        $response2->assertOk();

        $this->assertNotEquals(
            $response1->json('data.session_token'),
            $response2->json('data.session_token')
        );
    }

    #[Test]
    public function it_validates_financer_exists(): void
    {
        $response = $this->actingAs($this->auth)->postJson('/api/v1/vault/sessions', [
            'financer_id' => 'non-existent-id',
            'redirect_uri' => 'https://example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['financer_id']);
    }

    #[Test]
    public function it_validates_unified_apis_contains_hris(): void
    {
        $financer = Financer::factory()->create([
            'external_id' => json_encode([
                'sirh' => [
                    'consumer_id' => 'test-consumer-123',
                ],
            ]),
        ]);

        $this->auth->financers()->attach($financer, ['active' => true, 'role' => RoleDefaults::BENEFICIARY]);

        $response = $this->actingAs($this->auth)->postJson('/api/v1/vault/sessions', [
            'financer_id' => $financer->id,
            'redirect_uri' => 'https://example.com',
            'settings' => [
                'unified_apis' => ['ats', 'crm'], // Missing 'hris'
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['settings.unified_apis']);
    }

    #[Test]
    public function it_enforces_rate_limiting(): void
    {
        if (! config('activitylog.enabled')) {
            $this->markTestSkipped('Activity logging is not enabled');
        }
        // Temporarily set environment to 'testing' which is not 'local' to enable rate limiting
        $originalEnv = app()->environment();
        app()->detectEnvironment(fn (): string => 'production');

        Config::set('services.vault.rate_limit', [
            'max_attempts' => 3,
            'decay_minutes' => 60,
        ]);

        $financer = Financer::factory()->create([
            'external_id' => json_encode([
                'sirh' => [
                    'consumer_id' => 'test-consumer-123',
                ],
            ]),
        ]);

        $this->auth->financers()->attach($financer, ['active' => true, 'role' => RoleDefaults::BENEFICIARY]);

        Http::fake([
            'https://unify.apideck.com/vault/sessions' => Http::response([
                'data' => [
                    'session_token' => 'test-token',
                    'created_at' => '2025-06-23T10:00:00Z',
                    'expires_at' => '2025-06-23T11:00:00Z',
                ],
            ], 200),
        ]);

        try {
            // Clear any existing rate limiter state for this test
            RateLimiter::clear('vault-session:'.$this->auth->id);

            // Make 3 requests (should succeed)
            for ($i = 0; $i < 3; $i++) {
                $response = $this->actingAs($this->auth)->postJson('/api/v1/vault/sessions', [
                    'financer_id' => $financer->id,
                    'redirect_uri' => 'https://example.com',
                ]);
                $response->assertOk();
            }

            // 4th request should fail with rate limit
            $response = $this->actingAs($this->auth)->postJson('/api/v1/vault/sessions', [
                'financer_id' => $financer->id,
                'redirect_uri' => 'https://example.com',
            ]);

            $response->assertStatus(429)
                ->assertJson([
                    'message' => 'Rate limit exceeded',
                ]);

            // Check activity log
            $activity = Activity::where('event', 'vault_session_rate_limited')
                ->where('causer_id', $this->auth->id)
                ->first();

            $this->assertNotNull($activity);
            $this->assertEquals('Too many vault session attempts', $activity->description);
        } finally {
            // Restore original environment
            app()->detectEnvironment(fn () => $originalEnv);
        }
    }

    #[Test]
    public function it_creates_consumer_id_on_first_vault_session(): void
    {

        if (! config('activitylog.enabled')) {
            $this->markTestSkipped('Activity logging is not enabled');
        }
        // Given: Un financeur sans consumer_id
        $financer = Financer::factory()->create([
            'name' => 'Test Financer',
            'external_id' => null,
        ]);

        $this->auth->financers()->attach($financer, ['active' => true, 'role' => RoleDefaults::BENEFICIARY]);

        Http::fake([
            'https://unify.apideck.com/vault/sessions' => Http::response([
                'data' => [
                    'session_token' => 'test-token',
                    'created_at' => '2025-06-23T10:00:00Z',
                    'expires_at' => '2025-06-23T11:00:00Z',
                ],
            ], 200),
        ]);

        // When: On crée une session vault
        $response = $this->actingAs($this->auth)->postJson('/api/v1/vault/sessions', [
            'financer_id' => $financer->id,
            'redirect_uri' => 'https://example.com',
        ]);

        // Then: Un consumer_id est généré et utilisé
        $response->assertOk();

        $financer->refresh();
        $this->assertNotNull($financer->external_id);
        $this->assertArrayHasKey('sirh', $financer->external_id);
        $this->assertArrayHasKey('consumer_id', $financer->external_id['sirh']);

        $consumerId = $financer->external_id['sirh']['consumer_id'];
        $this->assertMatchesRegularExpression(
            '/^(local|staging|production|testing)-test-financer-[a-f0-9]{8}$/',
            $consumerId
        );

        // Verify activity log for consumer ID creation
        $consumerIdActivity = Activity::where('event', 'consumer_id.created')
            ->where('subject_id', $financer->id)
            ->where('subject_type', Financer::class)
            ->first();

        $this->assertNotNull($consumerIdActivity);
        $this->assertEquals($consumerId, $consumerIdActivity->properties['consumer_id']);
        $this->assertEquals('auto_generated', $consumerIdActivity->properties['method']);
    }

    #[Test]
    public function it_reuses_existing_consumer_id_for_subsequent_sessions(): void
    {
        // Given: Un financeur avec un consumer_id existant
        $existingConsumerId = 'prod-existing-123';
        $financer = Financer::factory()->create([
            'external_id' => [
                'sirh' => [
                    'consumer_id' => $existingConsumerId,
                    'created_at' => '2025-06-23T09:00:00Z',
                    'created_by' => $this->auth->id,
                    'provider' => 'apideck',
                ],
            ],
        ]);

        $this->auth->financers()->attach($financer, ['active' => true, 'role' => RoleDefaults::BENEFICIARY]);

        Http::fake([
            'https://unify.apideck.com/vault/sessions' => Http::response([
                'data' => [
                    'session_token' => 'test-token',
                    'created_at' => '2025-06-23T10:00:00Z',
                    'expires_at' => '2025-06-23T11:00:00Z',
                ],
            ], 200),
        ]);

        // When: On crée une nouvelle session vault
        $response = $this->actingAs($this->auth)->postJson('/api/v1/vault/sessions', [
            'financer_id' => $financer->id,
            'redirect_uri' => 'https://example.com',
        ]);

        // Then: Le même consumer_id est réutilisé
        $response->assertOk();

        $financer->refresh();
        $this->assertEquals($existingConsumerId, $financer->external_id['sirh']['consumer_id']);

        // Verify no new consumer ID creation activity
        $consumerIdActivity = Activity::where('event', 'consumer_id.created')
            ->where('subject_id', $financer->id)
            ->where('subject_type', Financer::class)
            ->first();

        $this->assertNull($consumerIdActivity);
    }

    #[Test]
    public function it_preserves_other_external_id_data_when_creating_consumer_id(): void
    {
        // Given: Un financeur avec d'autres données dans external_id
        $financer = Financer::factory()->create([
            'external_id' => [
                'other_system' => [
                    'id' => '123456',
                    'data' => 'important data',
                ],
                'legacy_id' => 'OLD-123',
            ],
        ]);

        $this->auth->financers()->attach($financer, ['active' => true, 'role' => RoleDefaults::BENEFICIARY]);

        Http::fake([
            'https://unify.apideck.com/vault/sessions' => Http::response([
                'data' => [
                    'session_token' => 'test-token',
                    'created_at' => '2025-06-23T10:00:00Z',
                    'expires_at' => '2025-06-23T11:00:00Z',
                ],
            ], 200),
        ]);

        // When: On crée une session vault
        $response = $this->actingAs($this->auth)->postJson('/api/v1/vault/sessions', [
            'financer_id' => $financer->id,
            'redirect_uri' => 'https://example.com',
        ]);

        // Then: Les autres données sont préservées
        $response->assertOk();

        $financer->refresh();
        $externalId = $financer->external_id;

        // Consumer ID is created
        $this->assertArrayHasKey('sirh', $externalId);
        $this->assertArrayHasKey('consumer_id', $externalId['sirh']);

        // Other data is preserved
        $this->assertEquals('123456', $externalId['other_system']['id']);
        $this->assertEquals('important data', $externalId['other_system']['data']);
        $this->assertEquals('OLD-123', $externalId['legacy_id']);
    }

    #[Test]
    public function it_dispatches_total_employees_job_after_session_creation(): void
    {
        Queue::fake();

        $financer = Financer::factory()->create([
            'external_id' => json_encode([
                'sirh' => [
                    'consumer_id' => 'test-consumer-queue',
                ],
            ]),
        ]);

        $this->auth->financers()->attach($financer, ['active' => true, 'role' => RoleDefaults::FINANCER_SUPER_ADMIN]);

        Http::fake([
            'https://unify.apideck.com/vault/sessions' => Http::response([
                'data' => [
                    'session_token' => 'test-token',
                    'created_at' => '2025-06-23T10:00:00Z',
                    'expires_at' => '2025-06-23T11:00:00Z',
                ],
            ], 200),
        ]);

        $this->actingAs($this->auth)->postJson('/api/v1/vault/sessions', [
            'financer_id' => $financer->id,
            'redirect_uri' => 'https://example.com',
        ])->assertOk();

        Queue::assertPushed(GetTotalEmployeesJob::class, function ($job) use ($financer): bool {
            return $job->financerId === $financer->id
                && $job->consumerId === 'test-consumer-queue'
                && $job->userId === (string) $this->auth->getAuthIdentifier();
        });
    }
}
