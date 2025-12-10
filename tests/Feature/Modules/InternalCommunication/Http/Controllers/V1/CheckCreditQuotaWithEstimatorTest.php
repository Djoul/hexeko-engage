<?php

namespace Tests\Feature\Modules\InternalCommunication\Http\Controllers\V1;

use App\Estimators\AiTokenEstimator;
use App\Models\CreditBalance;
use App\Models\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('credit')]
#[Group('middleware')]
#[Group('quota')]
#[Group('internal-communication')]
class CheckCreditQuotaWithEstimatorTest extends ProtectedRouteTestCase
{
    #[Test]
    public function it_allows_access_if_estimated_tokens_are_within_user_credit(): void
    {
        $user = $this->createAuthUser(withContext: true);

        // Simulate enough AI credits
        CreditBalance::create([
            'owner_type' => User::class,
            'owner_id' => $user->id,
            'type' => 'ai_token',
            'balance' => 200,
        ]);
        $this->mock(AiTokenEstimator::class, function ($mock): void {
            $mock->shouldReceive('estimate')
                ->andReturn(100); // valeur fixe souhaitée
        });
        $prompt = str_repeat('hello ', 10); // 50 chars → ~13 tokens

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/ai/generate', ['prompt' => $prompt]);

        $response->assertOk();
        $response->assertJson([
            'status' => 'AI request accepted',
        ]);
    }

    #[Test]
    public function it_blocks_access_if_estimated_tokens_exceed_credit(): void
    {
        $user = $this->createAuthUser(withContext: true);

        $this->mock(AiTokenEstimator::class, function ($mock): void {
            $mock->shouldReceive('estimate')
                ->andReturn(900); // valeur fixe souhaitée
        });
        // Not enough credits
        CreditBalance::create([
            'owner_type' => 'user',
            'owner_id' => $user->id,
            'type' => 'ai_token',
            'balance' => 5,
        ]);

        $prompt = str_repeat('This prompt is very long and complex. ', 10); // ~350 chars → ~88 tokens

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/ai/generate', ['prompt' => $prompt]);

        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'Not enough credits to perform this action.',
            'required' => 900,
            'type' => 'ai_token',
            'division_id' => $user->divisions->first()->id,
        ]);
    }
}
