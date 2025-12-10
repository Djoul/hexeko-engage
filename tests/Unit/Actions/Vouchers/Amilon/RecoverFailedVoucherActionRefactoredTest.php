<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Vouchers\Amilon;

use App\Actions\Vouchers\Amilon\RecoverFailedVoucherAction;
use App\Enums\IDP\RoleDefaults;
use App\Integrations\Vouchers\Amilon\Database\factories\OrderFactory;
use App\Integrations\Vouchers\Amilon\DTO\RecoveryResult;
use App\Integrations\Vouchers\Amilon\Enums\OrderStatus;
use App\Integrations\Vouchers\Amilon\Models\Order;
use App\Integrations\Vouchers\Amilon\Services\VoucherRecoveryService;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

#[Group('amilon')]
#[Group('vouchers')]
class RecoverFailedVoucherActionRefactoredTest extends TestCase
{
    use DatabaseTransactions;

    private RecoverFailedVoucherAction $action;

    private VoucherRecoveryService $recoveryService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->recoveryService = Mockery::mock(VoucherRecoveryService::class);
        $this->action = new RecoverFailedVoucherAction($this->recoveryService);

        // Create a team and set permissions context
        $team = Team::factory()->create();
        setPermissionsTeamId($team->id);

        // Create the FINANCER_SUPER_ADMIN role for tests
        Role::firstOrCreate(
            ['name' => RoleDefaults::FINANCER_SUPER_ADMIN, 'guard_name' => 'api', 'id' => Uuid::uuid4()->toString(), 'team_id' => $team->id]
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_successfully_recovers_a_failed_order(): void
    {
        // Arrange
        $user = User::factory()->create();
        Auth::loginUsingId($user->id);

        /** @var Order $order */
        $order = resolve(OrderFactory::class)->create([
            'user_id' => $user->id,
            'status' => OrderStatus::ERROR,
            'recovery_attempts' => 1,
        ]);

        $this->recoveryService->shouldReceive('canRetry')
            ->with(Mockery::on(fn ($arg): bool => $arg->id === $order->id))
            ->once()
            ->andReturn(true);

        $recoveryResult = new RecoveryResult(
            success: true,
            message: 'Order successfully recovered',
            newStatus: OrderStatus::CONFIRMED
        );

        $this->recoveryService->shouldReceive('attemptRecovery')
            ->with(Mockery::on(fn ($arg): bool => $arg->id === $order->id))
            ->once()
            ->andReturn($recoveryResult);

        // Act
        $result = $this->action->execute($order->id);

        // Assert
        $this->assertEquals($order->id, $result->id);
    }

    #[Test]
    public function it_throws_exception_when_order_not_found(): void
    {
        // Arrange
        $user = User::factory()->create();
        Auth::loginUsingId($user->id);

        $nonExistentId = '00000000-0000-0000-0000-000000000000';

        // Assert
        $this->expectException(ModelNotFoundException::class);

        // Act
        $this->action->execute($nonExistentId);
    }

    #[Test]
    public function it_throws_exception_when_order_cannot_be_retried(): void
    {
        // Arrange
        $user = User::factory()->create();
        Auth::loginUsingId($user->id);

        /** @var Order $order */
        $order = resolve(OrderFactory::class)->create([
            'user_id' => $user->id,
            'status' => OrderStatus::CONFIRMED,
        ]);

        $this->recoveryService->shouldReceive('canRetry')
            ->with(Mockery::on(fn ($arg): bool => $arg->id === $order->id))
            ->once()
            ->andReturn(false);

        // Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Order cannot be retried');

        // Act
        $this->action->execute($order->id);
    }

    #[Test]
    public function it_handles_recovery_failure_without_scheduling(): void
    {
        // Arrange
        $user = User::factory()->create();
        Auth::loginUsingId($user->id);

        /** @var Order $order */
        $order = resolve(OrderFactory::class)->create([
            'user_id' => $user->id,
            'status' => OrderStatus::ERROR,
            'recovery_attempts' => 1,
        ]);

        $this->recoveryService->shouldReceive('canRetry')
            ->with(Mockery::on(fn ($arg): bool => $arg->id === $order->id))
            ->once()
            ->andReturn(true);

        $recoveryResult = new RecoveryResult(
            success: false,
            message: 'Recovery failed: API error',
            newStatus: OrderStatus::ERROR,
            error: 'Connection timeout'
        );

        $this->recoveryService->shouldReceive('attemptRecovery')
            ->with(Mockery::on(fn ($arg): bool => $arg->id === $order->id))
            ->once()
            ->andReturn($recoveryResult);

        // No more automatic scheduling - removed getRetryDelay and scheduleRetry

        // Act
        $result = $this->action->execute($order->id);

        // Assert
        $this->assertEquals($order->id, $result->id);
    }

    #[Test]
    public function it_handles_permanent_cancellation_after_max_attempts(): void
    {
        // Arrange
        $user = User::factory()->create();
        Auth::loginUsingId($user->id);

        /** @var Order $order */
        $order = resolve(OrderFactory::class)->create([
            'user_id' => $user->id,
            'status' => OrderStatus::ERROR,
            'recovery_attempts' => 2,
        ]);

        $this->recoveryService->shouldReceive('canRetry')
            ->with(Mockery::on(fn ($arg): bool => $arg->id === $order->id))
            ->once()
            ->andReturn(true);

        // After 3rd attempt, status becomes CANCELLED
        $recoveryResult = new RecoveryResult(
            success: false,
            message: 'Recovery failed: Maximum attempts reached',
            newStatus: OrderStatus::CANCELLED,
            error: 'Order permanently cancelled'
        );

        $this->recoveryService->shouldReceive('attemptRecovery')
            ->with(Mockery::on(fn ($arg): bool => $arg->id === $order->id))
            ->once()
            ->andReturn($recoveryResult);

        // Act
        $result = $this->action->execute($order->id);

        // Assert
        $this->assertEquals($order->id, $result->id);
    }

    #[Test]
    public function it_works_with_admin_users_for_any_order(): void
    {
        // Arrange
        $admin = User::factory()->create();
        $admin->assignRole(RoleDefaults::FINANCER_SUPER_ADMIN);
        Auth::loginUsingId($admin->id);

        $otherUser = User::factory()->create();

        /** @var Order $order */
        $order = resolve(OrderFactory::class)->create([
            'user_id' => $otherUser->id,
            'status' => OrderStatus::ERROR,
        ]);

        $this->recoveryService->shouldReceive('canRetry')
            ->with(Mockery::on(fn ($arg): bool => $arg->id === $order->id))
            ->once()
            ->andReturn(true);

        $recoveryResult = new RecoveryResult(
            success: true,
            message: 'Order successfully recovered',
            newStatus: OrderStatus::CONFIRMED
        );

        $this->recoveryService->shouldReceive('attemptRecovery')
            ->with(Mockery::on(fn ($arg): bool => $arg->id === $order->id))
            ->once()
            ->andReturn($recoveryResult);

        // Act
        $result = $this->action->execute($order->id);

        // Assert
        $this->assertEquals($order->id, $result->id);
    }

    #[Test]
    public function it_throws_exception_when_non_admin_tries_to_recover_other_users_order(): void
    {
        // Arrange
        $user = User::factory()->create();
        Auth::loginUsingId($user->id);

        $otherUser = User::factory()->create();

        /** @var Order $order */
        $order = resolve(OrderFactory::class)->create([
            'user_id' => $otherUser->id,
            'status' => OrderStatus::ERROR,
        ]);

        // Assert
        $this->expectException(ModelNotFoundException::class);

        // Act
        $this->action->execute($order->id);
    }
}
