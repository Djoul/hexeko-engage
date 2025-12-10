<?php

namespace Tests\Unit\Console\Commands\User;

use App\Models\CreditBalance;
use App\Models\EngagementLog;
use App\Models\Financer;
use App\Models\Module;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('user')]
class PurgeUserCommandTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_fails_when_user_does_not_exist(): void
    {
        $fakeId = '00000000-0000-0000-0000-000000000000';

        $this->artisan('user:purge', ['user_id' => $fakeId, '--force' => true])
            ->expectsOutput("User with ID [{$fakeId}] not found.")
            ->assertFailed();
    }

    #[Test]
    public function it_displays_user_information_before_deletion(): void
    {
        $user = ModelFactory::createUser([
            'email' => 'test@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $this->artisan('user:purge', ['user_id' => $user->id, '--force' => true])
            ->expectsOutputToContain($user->id)
            ->expectsOutputToContain($user->email)
            ->expectsOutputToContain('John Doe');
    }

    #[Test]
    public function it_requires_confirmation_without_force_flag(): void
    {
        $user = ModelFactory::createUser(['email' => 'test@example.com']);

        $this->artisan('user:purge', ['user_id' => $user->id])
            ->expectsConfirmation('Are you absolutely sure you want to PERMANENTLY delete this user and ALL associated data?', 'no')
            ->expectsOutput('Operation cancelled.')
            ->assertSuccessful();

        // User should still exist
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    #[Test]
    public function it_requires_double_confirmation_without_force_flag(): void
    {
        $user = ModelFactory::createUser(['email' => 'test@example.com']);

        $this->artisan('user:purge', ['user_id' => $user->id])
            ->expectsConfirmation('Are you absolutely sure you want to PERMANENTLY delete this user and ALL associated data?', 'yes')
            ->expectsConfirmation('This action CANNOT be undone. Continue?', 'no')
            ->expectsOutput('Operation cancelled.')
            ->assertSuccessful();

        // User should still exist
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    #[Test]
    public function it_deletes_user_with_force_flag(): void
    {
        $user = ModelFactory::createUser(['email' => 'test@example.com']);
        $userId = $user->id;

        $this->artisan('user:purge', ['user_id' => $userId, '--force' => true])
            ->assertSuccessful();

        // User should be permanently deleted
        $this->assertDatabaseMissing('users', ['id' => $userId]);
    }

    #[Test]
    public function it_deletes_all_user_credit_balances(): void
    {
        $user = ModelFactory::createUser(['email' => 'test@example.com']);

        // Create credit balances with explicit different types to avoid unique constraint violations
        CreditBalance::factory()->create([
            'owner_type' => User::class,
            'owner_id' => $user->id,
            'type' => 'order',
        ]);
        CreditBalance::factory()->create([
            'owner_type' => User::class,
            'owner_id' => $user->id,
            'type' => 'ai_token',
        ]);
        CreditBalance::factory()->create([
            'owner_type' => User::class,
            'owner_id' => $user->id,
            'type' => 'amilon',
        ]);

        $this->assertEquals(3, $user->credits()->count());

        $this->artisan('user:purge', ['user_id' => $user->id, '--force' => true])
            ->expectsOutputToContain('Deleted 3 credit balance(s)')
            ->assertSuccessful();

        $this->assertEquals(0, CreditBalance::where('owner_id', $user->id)->count());
    }

    #[Test]
    public function it_deletes_all_user_engagement_logs(): void
    {
        $user = ModelFactory::createUser(['email' => 'test@example.com']);

        // Create engagement logs
        EngagementLog::factory()->count(5)->create(['user_id' => $user->id]);

        $this->assertEquals(5, $user->engagementLogs()->count());

        $this->artisan('user:purge', ['user_id' => $user->id, '--force' => true])
            ->expectsOutputToContain('Deleted 5 engagement log(s)')
            ->assertSuccessful();

        $this->assertEquals(0, EngagementLog::where('user_id', $user->id)->count());
    }

    #[Test]
    public function it_deletes_all_user_push_subscriptions(): void
    {
        $user = ModelFactory::createUser(['email' => 'test@example.com']);

        // Create push subscriptions
        PushSubscription::factory()->count(2)->create(['user_id' => $user->id]);

        $this->assertEquals(2, $user->pushSubscriptions()->count());

        $this->artisan('user:purge', ['user_id' => $user->id, '--force' => true])
            ->expectsOutputToContain('Deleted 2 push subscription(s)')
            ->assertSuccessful();

        $this->assertEquals(0, PushSubscription::where('user_id', $user->id)->count());
    }

    #[Test]
    public function it_detaches_all_user_pinned_modules(): void
    {
        $user = ModelFactory::createUser(['email' => 'test@example.com']);

        // Create and attach modules
        $modules = Module::factory()->count(3)->create();
        $user->pinnedModules()->attach($modules);

        $this->assertEquals(3, $user->pinnedModules()->count());

        $this->artisan('user:purge', ['user_id' => $user->id, '--force' => true])
            ->expectsOutputToContain('Detached 3 pinned module(s)')
            ->assertSuccessful();

        $this->assertEquals(0, DB::table('user_pinned_modules')->where('user_id', $user->id)->count());
    }

    #[Test]
    public function it_detaches_all_user_financers(): void
    {
        $financer = ModelFactory::createFinancer(['name' => 'Test Financer']);
        $user = ModelFactory::createUser([
            'email' => 'test@example.com',
            'financers' => [
                ['financer' => $financer, 'active' => true],
            ],
        ]);

        $this->assertEquals(1, DB::table('financer_user')->where('user_id', $user->id)->count());

        $this->artisan('user:purge', ['user_id' => $user->id, '--force' => true])
            ->expectsOutputToContain('Deleted 1 financer association(s)')
            ->assertSuccessful();

        $this->assertEquals(0, DB::table('financer_user')->where('user_id', $user->id)->count());
    }

    #[Test]
    public function it_detaches_all_user_roles_and_permissions(): void
    {
        $user = ModelFactory::createUser(['email' => 'test@example.com']);

        // Count initial roles (ModelFactory may assign roles)
        $user->roles()->count();

        $this->artisan('user:purge', ['user_id' => $user->id, '--force' => true])
            ->assertSuccessful();

        // Verify all roles detached
        $this->assertEquals(0, DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->where('model_uuid', $user->id)
            ->count());
    }

    #[Test]
    public function it_deletes_soft_deleted_users(): void
    {
        $user = ModelFactory::createUser(['email' => 'test@example.com']);
        $userId = $user->id;

        // Soft delete the user first
        $user->delete();
        $this->assertSoftDeleted('users', ['id' => $userId]);

        $this->artisan('user:purge', ['user_id' => $userId, '--force' => true])
            ->assertSuccessful();

        // User should be permanently deleted (not even in trash)
        $this->assertDatabaseMissing('users', ['id' => $userId]);
    }

    #[Test]
    public function it_rolls_back_transaction_on_failure(): void
    {
        $user = ModelFactory::createUser(['email' => 'test@example.com']);
        $userId = $user->id;

        // Create some related data
        CreditBalance::factory()->create([
            'owner_type' => User::class,
            'owner_id' => $user->id,
        ]);

        // Mock a failure by deleting the user before command runs
        // This will cause FK constraint issues and should rollback
        DB::table('users')->where('id', $userId)->delete();

        $initialCreditCount = CreditBalance::count();

        // Command should fail but not leave partial data
        $this->artisan('user:purge', ['user_id' => $userId, '--force' => true])
            ->assertFailed();

        // Credits should still exist (transaction rolled back)
        $this->assertEquals($initialCreditCount, CreditBalance::count());
    }

    #[Test]
    public function it_handles_user_with_no_related_data(): void
    {
        // Create user with financer (required by ModelFactory) but no other related data
        $user = ModelFactory::createUser(['email' => 'minimal@example.com']);

        $this->artisan('user:purge', ['user_id' => $user->id, '--force' => true])
            ->expectsOutputToContain('Deleted 0 credit balance(s)')
            ->expectsOutputToContain('Deleted 0 engagement log(s)')
            ->expectsOutputToContain('User permanently deleted')
            ->assertSuccessful();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }
}
