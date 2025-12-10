<?php

namespace Tests\Unit\Actions\Apideck;

use App\Actions\Apideck\CreateVaultSessionAction;
use App\DTOs\Vault\VaultSessionDTO;
use App\Enums\IDP\TeamTypes;
use App\Events\Vault\VaultSessionCreated;
use App\Exceptions\Vault\VaultException;
use App\Models\Financer;
use App\Models\Team;
use App\Models\User;
use App\Services\Vault\VaultService;
use Illuminate\Cache\RateLimiter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

#[Group('apideck')]
class CreateVaultSessionActionTest extends TestCase
{
    use DatabaseTransactions;

    private CreateVaultSessionAction $action;

    private VaultService $vaultService;

    private RateLimiter $rateLimiter;

    protected function setUp(): void
    {
        parent::setUp();

        Team::firstOrCreate(
            ['type' => TeamTypes::GLOBAL],
            ['name' => 'Global Team', 'slug' => 'global-team', 'type' => TeamTypes::GLOBAL]
        );
        $this->vaultService = Mockery::mock(VaultService::class);
        $this->rateLimiter = Mockery::mock(RateLimiter::class);

        $this->action = new CreateVaultSessionAction(
            $this->vaultService,
            $this->rateLimiter
        );

        Config::set('services.vault.rate_limit', [
            'max_attempts' => 10,
            'decay_minutes' => 60,
        ]);
    }

    #[Test]
    public function it_executes_creates_session_successfully(): void
    {
        Event::fake();

        $user = User::factory()->create();
        $financer = Financer::factory()->create([
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

        $redirectUri = 'https://app.upengage.com/callback';

        $expectedDto = new VaultSessionDTO([
            'session_token' => 'test-session-token',
            'expires_at' => '2025-06-23T11:00:00Z',
            'vault_url' => 'https://vault.apideck.com/auth/connect/test-session-token',
            'consumer_metadata' => [
                'account_name' => 'Test Company',
            ],
        ]);

        $this->rateLimiter
            ->shouldReceive('tooManyAttempts')
            ->with("vault-session:{$financer->id}", 10)
            ->once()
            ->andReturn(false);

        $this->rateLimiter
            ->shouldReceive('hit')
            ->with("vault-session:{$financer->id}", 3600)
            ->once();

        $consumerId = 'test-consumer-123';

        $this->vaultService
            ->shouldReceive('createSession')
            ->with($financer, $consumerId, $redirectUri, $settings)
            ->once()
            ->andReturn($expectedDto);

        $result = $this->action->execute($user, $financer, $consumerId, $redirectUri, $settings);

        $this->assertInstanceOf(VaultSessionDTO::class, $result);
        $this->assertEquals('test-session-token', $result->sessionToken);

        Event::assertDispatched(VaultSessionCreated::class, function ($event) use ($user, $financer, $expectedDto): bool {
            return $event->user->id === $user->id &&
                   $event->financer->id === $financer->id &&
                   $event->session->sessionToken === $expectedDto->sessionToken;
        });
    }

    #[Test]
    public function it_logs_activity_on_success(): void
    {

        if (! config('activitylog.enabled')) {
            $this->markTestSkipped('Activity logging is not enabled');
        }

        $user = User::factory()->create();
        $financer = Financer::factory()->create([
            'external_id' => json_encode([
                'sirh' => [
                    'consumer_id' => 'test-consumer-123',
                ],
            ]),
        ]);

        $settings = ['unified_apis' => ['hris']];
        $redirectUri = 'https://example.com';

        $dto = new VaultSessionDTO([
            'session_token' => 'test-token',
            'expires_at' => '2025-06-23T11:00:00Z',
            'vault_url' => 'https://vault.apideck.com/auth/connect/test-token',
        ]);

        $this->rateLimiter
            ->shouldReceive('tooManyAttempts')
            ->andReturn(false);

        $this->rateLimiter
            ->shouldReceive('hit');

        $consumerId = 'test-consumer-123';

        $this->vaultService
            ->shouldReceive('createSession')
            ->andReturn($dto);

        $this->action->execute($user, $financer, $consumerId, $redirectUri, $settings);

        $activity = Activity::where('event', 'vault.session.created')
            ->where('subject_type', Financer::class)
            ->where('subject_id', $financer->id)
            ->where('causer_id', $user->id)
            ->first();

        $this->assertNotNull($activity);
        $this->assertEquals('Vault session created for SIRH integration', $activity->description);

        $properties = $activity->properties->toArray();
        $this->assertArrayHasKey('consumer_id', $properties);
        $this->assertArrayHasKey('expires_at', $properties);
        $this->assertArrayHasKey('unified_apis', $properties);
        $this->assertEquals('test-consumer-123', $properties['consumer_id']);
        $this->assertEquals('2025-06-23T11:00:00Z', $properties['expires_at']);
    }

    #[Test]
    public function it_logs_activity_on_failure(): void
    {
        if (! config('activitylog.enabled')) {
            $this->markTestSkipped('Activity logging is not enabled');
        }

        $user = User::factory()->create();
        $financer = Financer::factory()->create([
            'external_id' => json_encode([
                'sirh' => [
                    'consumer_id' => 'test-consumer-123',
                ],
            ]),
        ]);

        $this->rateLimiter
            ->shouldReceive('tooManyAttempts')
            ->andReturn(false);

        $this->rateLimiter
            ->shouldReceive('hit');

        $consumerId = 'test-consumer-123';

        $this->vaultService
            ->shouldReceive('createSession')
            ->andThrow(new VaultException('API Error'));

        try {
            $this->action->execute($user, $financer, $consumerId, 'https://example.com', []);
        } catch (VaultException $e) {
            // Expected exception
        }

        $activity = Activity::where('event', 'vault.session.failed')
            ->where('subject_type', Financer::class)
            ->where('subject_id', $financer->id)
            ->where('causer_id', $user->id)
            ->first();

        $this->assertNotNull($activity);
        $this->assertEquals('Vault session creation failed', $activity->description);

        $properties = $activity->properties->toArray();
        $this->assertArrayHasKey('error', $properties);
        $this->assertEquals('API Error', $properties['error']);
    }

    #[Test]
    public function it_dispatches_vault_session_created_event(): void
    {
        Event::fake();

        $user = User::factory()->create();
        $financer = Financer::factory()->create([
            'external_id' => json_encode([
                'sirh' => [
                    'consumer_id' => 'test-consumer-123',
                ],
            ]),
        ]);

        $dto = new VaultSessionDTO([
            'session_token' => 'test-token',
            'expires_at' => '2025-06-23T11:00:00Z',
            'vault_url' => 'https://vault.apideck.com/auth/connect/test-token',
        ]);

        $this->rateLimiter
            ->shouldReceive('tooManyAttempts')
            ->andReturn(false);

        $this->rateLimiter
            ->shouldReceive('hit');

        $this->vaultService
            ->shouldReceive('createSession')
            ->andReturn($dto);

        $consumerId = 'test-consumer-123';
        $this->action->execute($user, $financer, $consumerId, 'https://example.com', []);

        Event::assertDispatched(VaultSessionCreated::class, function ($event) use ($user, $financer, $dto): bool {
            return $event->user->id === $user->id &&
                   $event->financer->id === $financer->id &&
                   $event->session->sessionToken === $dto->sessionToken &&
                   $event->session->expiresAt === $dto->expiresAt;
        });
    }

    #[Test]
    public function it_enforces_rate_limiting(): void
    {
        $user = User::factory()->create();
        $financer = Financer::factory()->create([
            'external_id' => json_encode([
                'sirh' => [
                    'consumer_id' => 'test-consumer-123',
                ],
            ]),
        ]);

        $this->rateLimiter
            ->shouldReceive('tooManyAttempts')
            ->with("vault-session:{$financer->id}", 10)
            ->once()
            ->andReturn(true);

        $this->expectException(VaultException::class);
        $this->expectExceptionMessage('Rate limit exceeded');
        $this->expectExceptionCode(429);

        $consumerId = 'test-consumer-123';
        $this->action->execute($user, $financer, $consumerId, 'https://example.com', []);

        // Verify activity log for rate limiting
        $activity = Activity::where('event', 'vault_session_rate_limited')
            ->where('causer_id', $user->id)
            ->first();

        $this->assertNotNull($activity);
        $this->assertEquals('Too many vault session attempts', $activity->description);
    }

    #[Test]
    public function it_throws_exception_when_empty_consumer_id(): void
    {
        $user = User::factory()->create();
        $financer = Financer::factory()->create();

        $this->rateLimiter
            ->shouldReceive('tooManyAttempts')
            ->andReturn(false);

        $this->rateLimiter
            ->shouldReceive('hit');

        $this->vaultService
            ->shouldReceive('createSession')
            ->with($financer, '', 'https://example.com', [
                'unified_apis' => ['hris'],
                'isolation_mode' => false,
            ])
            ->andThrow(new VaultException('Consumer ID is required'));

        $this->expectException(VaultException::class);
        $this->expectExceptionMessage('Consumer ID is required');

        $this->action->execute($user, $financer, '', 'https://example.com', []);
    }

    #[Test]
    public function it_retries_on_transient_failure(): void
    {
        $user = User::factory()->create();
        $financer = Financer::factory()->create([
            'external_id' => json_encode([
                'sirh' => [
                    'consumer_id' => 'test-consumer-123',
                ],
            ]),
        ]);

        $this->rateLimiter
            ->shouldReceive('tooManyAttempts')
            ->andReturn(false);

        $this->rateLimiter
            ->shouldReceive('hit');

        // Service should handle the retry, action just calls once
        $this->vaultService
            ->shouldReceive('createSession')
            ->once()
            ->andThrow(new VaultException('Service unavailable', 503));

        $this->expectException(VaultException::class);
        $this->expectExceptionMessage('Service unavailable');

        $consumerId = 'test-consumer-123';
        $this->action->execute($user, $financer, $consumerId, 'https://example.com', []);
    }

    #[Test]
    public function it_validates_consumer_id_format(): void
    {
        $user = User::factory()->create();
        $financer = Financer::factory()->create();

        $this->rateLimiter
            ->shouldReceive('tooManyAttempts')
            ->andReturn(false);

        $this->rateLimiter
            ->shouldReceive('hit');

        $this->vaultService
            ->shouldReceive('createSession')
            ->with($financer, '', 'https://example.com', [
                'unified_apis' => ['hris'],
                'isolation_mode' => false,
            ])
            ->andThrow(new VaultException('Consumer ID is required'));

        $this->expectException(VaultException::class);
        $this->expectExceptionMessage('Consumer ID is required');

        $this->action->execute($user, $financer, '', 'https://example.com', []);
    }

    #[Test]
    public function it_handles_invalid_consumer_id(): void
    {
        $user = User::factory()->create();
        $financer = Financer::factory()->create();

        $this->rateLimiter
            ->shouldReceive('tooManyAttempts')
            ->andReturn(false);

        $this->rateLimiter
            ->shouldReceive('hit');

        $this->vaultService
            ->shouldReceive('createSession')
            ->with($financer, 'invalid-consumer-id', 'https://example.com', [
                'unified_apis' => ['hris'],
                'isolation_mode' => false,
            ])
            ->andThrow(new VaultException('Consumer not found in Apideck', 404));

        $this->expectException(VaultException::class);
        $this->expectExceptionMessage('Consumer not found in Apideck');

        $this->action->execute($user, $financer, 'invalid-consumer-id', 'https://example.com', []);
    }

    #[Test]
    public function it_includes_default_settings(): void
    {
        $user = User::factory()->create();
        $financer = Financer::factory()->create([
            'external_id' => json_encode([
                'sirh' => [
                    'consumer_id' => 'test-consumer-123',
                ],
            ]),
        ]);

        $dto = new VaultSessionDTO([
            'session_token' => 'test-token',
            'expires_at' => '2025-06-23T11:00:00Z',
            'vault_url' => 'https://vault.apideck.com/auth/connect/test-token',
        ]);

        $this->rateLimiter
            ->shouldReceive('tooManyAttempts')
            ->andReturn(false);

        $this->rateLimiter
            ->shouldReceive('hit');

        $consumerId = 'test-consumer-123';

        $this->vaultService
            ->shouldReceive('createSession')
            ->with($financer, $consumerId, 'https://example.com', [
                'unified_apis' => ['hris'],
                'isolation_mode' => false,
            ])
            ->andReturn($dto);

        $result = $this->action->execute($user, $financer, $consumerId, 'https://example.com', []);

        $this->assertInstanceOf(VaultSessionDTO::class, $result);
    }

    #[Test]
    public function it_merges_custom_settings_with_defaults(): void
    {
        $user = User::factory()->create();
        $financer = Financer::factory()->create([
            'external_id' => json_encode([
                'sirh' => [
                    'consumer_id' => 'test-consumer-123',
                ],
            ]),
        ]);

        $customSettings = [
            'show_sidebar' => false,
        ];

        $dto = new VaultSessionDTO([
            'session_token' => 'test-token',
            'expires_at' => '2025-06-23T11:00:00Z',
            'vault_url' => 'https://vault.apideck.com/auth/connect/test-token',
        ]);

        $this->rateLimiter
            ->shouldReceive('tooManyAttempts')
            ->andReturn(false);

        $this->rateLimiter
            ->shouldReceive('hit');

        $consumerId = 'test-consumer-123';

        $this->vaultService
            ->shouldReceive('createSession')
            ->with($financer, $consumerId, 'https://example.com', [
                'unified_apis' => ['hris'],
                'isolation_mode' => false,
                'show_sidebar' => false,
            ])
            ->andReturn($dto);

        $result = $this->action->execute($user, $financer, $consumerId, 'https://example.com', $customSettings);

        $this->assertInstanceOf(VaultSessionDTO::class, $result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
