<?php

namespace Tests\Feature;

use App\Estimators\AiTokenEstimator;
use App\Models\CreditBalance;
use App\Models\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('credit')]
#[Group('middleware')]
#[Group('quota')]
class CheckCreditQuotaTest extends ProtectedRouteTestCase
{
    #[Test]
    public function it_allows_access_when_user_has_sufficient_credit(): void
    {
        $user = $this->createAuthUser(withContext: true);

        CreditBalance::create([
            'owner_type' => User::class,
            'owner_id' => $user->id,
            'type' => 'ai_token',
            'balance' => 1000,
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/ai/generate');

        $response->assertOk();
        $response->assertJson(['status' => 'AI request accepted']);
    }

    #[Test]
    public function it_blocks_access_when_user_has_insufficient_credit(): void
    {
        $user = $this->createAuthUser(withContext: true);

        $this->mock(AiTokenEstimator::class, function ($mock): void {
            $mock->shouldReceive('estimate')
                ->andReturn(123); // valeur fixe souhaitée
        });
        $this->assertTrue($user->credits->where('type', 'sms')->isEmpty());

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/sms/send');

        $response->assertStatus(403);

        $response->assertJson([
            'message' => 'Not enough credits to perform this action.',
            'required' => 123,
            'type' => 'sms',
        ]);
    }
}
