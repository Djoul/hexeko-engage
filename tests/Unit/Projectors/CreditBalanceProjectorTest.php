<?php

namespace Tests\Unit\Projectors;

use App\Events\CreditAdded;
use App\Events\CreditAdjusted;
use App\Events\CreditConsumed;
use App\Events\CreditExpired;
use App\Models\CreditBalance;
use App\Models\User;
use App\Projectors\CreditBalanceProjector;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

#[Group('credit')]
#[Group('projector')]
#[Group('event-sourcing')]
class CreditBalanceProjectorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    #[Test]
    public function it_projects_credit_added_event(): void
    {
        $user = User::factory()->create(['id' => Uuid::uuid4()]);
        $projector = new CreditBalanceProjector;

        $projector->onCreditAdded(new CreditAdded('user', $user->id, 'ai_token', 100, 'initial allocation'));

        $balance = CreditBalance::where([
            'owner_type' => 'user',
            'owner_id' => $user->id,
            'type' => 'ai_token',
        ])->first();

        $this->assertNotNull($balance);
        $this->assertEquals(100, $balance->balance);
        $this->assertEquals('credit_added', $balance->context['event']);
        $this->assertEquals('initial allocation', $balance->context['reason']);
        $this->assertArrayHasKey('timestamp', $balance->context);
    }

    #[Test]
    public function it_increments_existing_balance_on_multiple_events(): void
    {
        $user = User::factory()->create(['id' => Uuid::uuid4()]);
        $projector = new CreditBalanceProjector;

        $projector->onCreditAdded(new CreditAdded('user', $user->id, 'sms', 50, 'first batch'));
        $projector->onCreditAdded(new CreditAdded('user', $user->id, 'sms', 25, 'second batch'));

        $balance = CreditBalance::where([
            'owner_type' => 'user',
            'owner_id' => $user->id,
            'type' => 'sms',
        ])->first();

        $this->assertNotNull($balance);
        $this->assertEquals(75, $balance->balance);
        $this->assertEquals('credit_added', $balance->context['event']);
        $this->assertEquals('second batch', $balance->context['reason']);
        $this->assertArrayHasKey('timestamp', $balance->context);
    }

    #[Test]
    public function it_decrements_balance_when_credit_is_consumed(): void
    {
        $user = User::factory()->create(['id' => Uuid::uuid4()]);
        $adminUser = User::factory()->create(['id' => Uuid::uuid4()]);
        $projector = new CreditBalanceProjector;

        $projector->onCreditAdded(new CreditAdded('user', $user->id, 'email', 100, 'initial allocation'));
        $projector->onCreditConsumed(new CreditConsumed('user', $user->id, 'email', 30, $adminUser->id, 'campaign send'));

        $balance = CreditBalance::where([
            'owner_type' => 'user',
            'owner_id' => $user->id,
            'type' => 'email',
        ])->first();

        $this->assertNotNull($balance);
        $this->assertEquals(70, $balance->balance);
        $this->assertEquals('credit_consumed', $balance->context['event']);
        $this->assertEquals($adminUser->id, $balance->context['by_user']);
        $this->assertEquals('campaign send', $balance->context['reason']);
        $this->assertArrayHasKey('timestamp', $balance->context);
    }

    #[Test]
    public function it_does_not_decrement_if_balance_is_insufficient(): void
    {
        $user = User::factory()->create(['id' => Uuid::uuid4()]);
        $projector = new CreditBalanceProjector;

        $projector->onCreditAdded(new CreditAdded('user', $user->id, 'sms', 10, 'initial allocation'));
        $projector->onCreditConsumed(new CreditConsumed('user', $user->id, 'sms', 50, null, 'attempted usage'));

        $balance = CreditBalance::where([
            'owner_type' => 'user',
            'owner_id' => $user->id,
            'type' => 'sms',
        ])->first();

        $this->assertNotNull($balance);
        $this->assertEquals(10, $balance->balance);
        $this->assertEquals('credit_added', $balance->context['event']);
        $this->assertEquals('initial allocation', $balance->context['reason']);
    }

    #[Test]
    public function it_expires_credit_correctly(): void
    {
        $user = User::factory()->create(['id' => Uuid::uuid4()]);
        $projector = new CreditBalanceProjector;

        $projector->onCreditAdded(new CreditAdded('user', $user->id, 'sms', 80, 'initial allocation'));
        $projector->onCreditExpired(new CreditExpired('user', $user->id, 'sms', 30, 'monthly expiration'));

        $balance = CreditBalance::where([
            'owner_type' => 'user',
            'owner_id' => $user->id,
            'type' => 'sms',
        ])->first();

        $this->assertNotNull($balance);
        $this->assertEquals(50, $balance->balance);
        $this->assertEquals('credit_expired', $balance->context['event']);
        $this->assertEquals('monthly expiration', $balance->context['reason']);
        $this->assertArrayHasKey('timestamp', $balance->context);
    }

    #[Test]
    public function it_adjusts_credit_balance_directly(): void
    {
        $user = User::factory()->create(['id' => Uuid::uuid4()]);
        $adminId = Uuid::uuid4()->toString();
        $projector = new CreditBalanceProjector;

        $projector->onCreditAdded(new CreditAdded('user', $user->id, 'ai_token', 120, 'initial allocation'));
        $projector->onCreditAdjusted(
            new CreditAdjusted(
                ownerType: 'user',
                ownerId: $user->id,
                type: 'ai_token',
                oldAmount: 120,
                newAmount: 200,
                byAdminId: $adminId,
                context: 'manual correction'
            )
        );

        $balance = CreditBalance::where([
            'owner_type' => 'user',
            'owner_id' => $user->id,
            'type' => 'ai_token',
        ])->first();

        $this->assertNotNull($balance);
        $this->assertEquals(200, $balance->balance);
        $this->assertEquals('credit_adjusted', $balance->context['event']);
        $this->assertEquals($adminId, $balance->context['by_user']);
        $this->assertEquals('manual correction', $balance->context['reason']);
        $this->assertEquals(120, $balance->context['old_amount']);
        $this->assertEquals(200, $balance->context['new_amount']);
        $this->assertArrayHasKey('timestamp', $balance->context);
    }
}
