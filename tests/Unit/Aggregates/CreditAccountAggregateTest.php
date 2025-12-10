<?php

namespace Tests\Unit\Aggregates;

use App\Models\User;
use App\Services\CreditAccountService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

#[Group('credit')]
#[Group('aggregate')]
#[Group('event-sourcing')]
class CreditAccountAggregateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    #[Test]
    public function it_adds_credit_via_static_service(): void
    {
        $user = User::factory()->create(['id' => Uuid::uuid4()]);

        CreditAccountService::addCredit(
            User::class,
            $user->id,
            'ai_token',
            100
        );

        $this->assertDatabaseHas('credit_balances', [
            'owner_type' => User::class,
            'owner_id' => $user->id,
            'type' => 'ai_token',
            'balance' => 100,
        ]);
    }

    #[Test]
    public function it_consumes_credit_via_static_service(): void
    {
        $user = User::factory()->create(['id' => Uuid::uuid4()]);

        CreditAccountService::addCredit(User::class, $user->id, 'sms', 100);
        CreditAccountService::consumeCredit(User::class, $user->id, 'sms', 40);

        $this->assertDatabaseHas('credit_balances', [
            'owner_type' => User::class,
            'owner_id' => $user->id,
            'type' => 'sms',
            'balance' => 60,
        ]);
    }

    #[Test]
    public function it_does_not_consume_if_not_enough_credit(): void
    {
        $user = User::factory()->create(['id' => Uuid::uuid4()]);

        CreditAccountService::addCredit(User::class, $user->id, 'email', 10);
        CreditAccountService::consumeCredit(User::class, $user->id, 'email', 50);

        $this->assertDatabaseHas('credit_balances', [
            'owner_type' => User::class,
            'owner_id' => $user->id,
            'type' => 'email',
            'balance' => 10,
        ]);
    }
}
