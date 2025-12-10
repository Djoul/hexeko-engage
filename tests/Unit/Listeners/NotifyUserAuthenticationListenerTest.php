<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Events\UserAuthenticated;
use App\Listeners\NotifyUserAuthenticationListener;
use App\Models\User;
use App\Services\SlackService;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('notification')]
class NotifyUserAuthenticationListenerTest extends TestCase
{
    private NotifyUserAuthenticationListener $listener;

    private MockInterface $slackService;

    private const MONITORED_USER_ID = '0199a425-d226-709d-a3b3-feb52e7f7393';

    protected function setUp(): void
    {
        parent::setUp();

        $this->slackService = $this->mock(SlackService::class);
        $this->listener = new NotifyUserAuthenticationListener($this->slackService);

        Cache::flush();

        // Enable monitoring for tests
        config(['monitoring.user_login.enabled' => true]);
        config(['monitoring.user_login.user_ids' => [self::MONITORED_USER_ID]]);
        config(['monitoring.user_login.slack_channel' => 'up-engage-tech']);
        config(['monitoring.user_login.throttle_minutes' => 5]);
    }

    #[Test]
    public function it_sends_slack_notification_for_monitored_user_on_first_login(): void
    {
        // Arrange
        $user = User::factory()->make([
            'cognito_id' => self::MONITORED_USER_ID,
            'email' => 'monitored@test.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $event = new UserAuthenticated($user);

        $this->slackService
            ->shouldReceive('sendToPublicChannel')
            ->once()
            ->with(
                Mockery::on(function (string $message) use ($user): bool {
                    return str_contains($message, $user->email)
                        && str_contains($message, $user->cognito_id)
                        && str_contains($message, '🔐');
                }),
                'up-engage-tech'
            )
            ->andReturn(['ok' => true]);

        // Act
        $this->listener->handle($event);

        // Assert
        $this->assertTrue(
            Cache::has("user_login_notified:{$user->cognito_id}")
        );
    }

    #[Test]
    public function it_does_not_send_notification_for_non_monitored_users(): void
    {
        // Arrange
        $user = User::factory()->make([
            'cognito_id' => 'other-user-id',
            'email' => 'other@test.com',
        ]);

        $event = new UserAuthenticated($user);

        $this->slackService->shouldNotReceive('sendToPublicChannel');

        // Act
        $this->listener->handle($event);

        // Assert
        $this->assertFalse(
            Cache::has("user_login_notified:{$user->cognito_id}")
        );
    }

    #[Test]
    public function it_throttles_notifications_within_5_minutes(): void
    {
        // Arrange
        $user = User::factory()->make([
            'cognito_id' => self::MONITORED_USER_ID,
            'email' => 'monitored@test.com',
        ]);

        $event = new UserAuthenticated($user);

        // First login - should send
        $this->slackService
            ->shouldReceive('sendToPublicChannel')
            ->once()
            ->andReturn(['ok' => true]);

        $this->listener->handle($event);

        // Second login within 5 minutes - should NOT send
        $this->slackService->shouldNotReceive('sendToPublicChannel');

        $this->listener->handle($event);
    }

    #[Test]
    public function it_sends_notification_after_throttle_period_expires(): void
    {
        // Arrange
        $user = User::factory()->make([
            'cognito_id' => self::MONITORED_USER_ID,
            'email' => 'monitored@test.com',
        ]);

        $event = new UserAuthenticated($user);

        // Set cache as if last notification was 6 minutes ago (expired)
        Cache::put(
            "user_login_notified:{$user->cognito_id}",
            now()->subMinutes(6)->timestamp,
            now()->addMinutes(5)
        );

        $this->slackService
            ->shouldReceive('sendToPublicChannel')
            ->once()
            ->andReturn(['ok' => true]);

        // Act
        $this->listener->handle($event);

        // Assert - cache should be updated
        $this->assertTrue(
            Cache::has("user_login_notified:{$user->cognito_id}")
        );
    }

    #[Test]
    public function it_logs_authentication_for_monitored_user(): void
    {
        // Arrange
        Log::shouldReceive('info')
            ->once()
            ->with(
                'Monitored user authenticated',
                Mockery::on(function (array $context): bool {
                    return $context['cognito_id'] === self::MONITORED_USER_ID
                        && isset($context['email'])
                        && isset($context['timestamp']);
                })
            );

        $user = User::factory()->make([
            'cognito_id' => self::MONITORED_USER_ID,
            'email' => 'monitored@test.com',
        ]);

        $event = new UserAuthenticated($user);

        $this->slackService
            ->shouldReceive('sendToPublicChannel')
            ->once()
            ->andReturn(['ok' => true]);

        // Act
        $this->listener->handle($event);
    }

    #[Test]
    public function it_handles_slack_service_failure_gracefully(): void
    {
        // Arrange
        $user = User::factory()->make([
            'cognito_id' => self::MONITORED_USER_ID,
            'email' => 'monitored@test.com',
        ]);

        $event = new UserAuthenticated($user);

        $this->slackService
            ->shouldReceive('sendToPublicChannel')
            ->once()
            ->andThrow(new Exception('Slack API error'));

        // Expect both info log (for authentication) and error log (for failure)
        Log::shouldReceive('info')
            ->once()
            ->with(
                'Monitored user authenticated',
                Mockery::type('array')
            );

        Log::shouldReceive('error')
            ->once()
            ->with(
                'Failed to send Slack notification for user authentication',
                Mockery::type('array')
            );

        // Act - should not throw exception
        $this->listener->handle($event);

        // Assert - cache should still be set to prevent retry spam
        $this->assertTrue(
            Cache::has("user_login_notified:{$user->cognito_id}")
        );
    }

    #[Test]
    public function it_does_not_send_notification_when_monitoring_is_disabled(): void
    {
        // Arrange
        config(['monitoring.user_login.enabled' => false]);

        $user = User::factory()->make([
            'cognito_id' => self::MONITORED_USER_ID,
            'email' => 'monitored@test.com',
        ]);

        $event = new UserAuthenticated($user);

        $this->slackService->shouldNotReceive('sendToPublicChannel');

        // Act
        $this->listener->handle($event);

        // Assert
        $this->assertFalse(
            Cache::has("user_login_notified:{$user->cognito_id}")
        );
    }
}
