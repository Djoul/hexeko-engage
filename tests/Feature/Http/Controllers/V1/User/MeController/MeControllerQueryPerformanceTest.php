<?php

namespace Tests\Feature\Http\Controllers\V1\User\MeController;

use App\Models\CreditBalance;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Attributes\FlushTables;
use Tests\ProtectedRouteTestCase;

#[FlushTables(tables: ['users', 'credit_balances', 'financers', 'financer_user', 'teams', 'roles', 'model_has_roles', 'model_has_permissions'], scope: 'class')]
#[Group('auth')]
#[Group('performance')]
class MeControllerQueryPerformanceTest extends ProtectedRouteTestCase
{
    const ME_ENDPOINT = '/api/v1/me';

    #[Test]
    public function test_me_endpoint_queries_do_not_scale_with_number_of_credits(): void
    {
        // Create a user
        $user = User::factory()->create();

        // Test with 1 credit
        $this->clearCredits($user);
        CreditBalance::factory()->create([
            'owner_type' => User::class,
            'owner_id' => $user->id,
            'type' => 'cash',
            'balance' => 1000,
        ]);

        $queriesWithOneCredit = $this->getQueryCountForMeEndpoint($user);

        // Test with 10 credits (with unique types)
        $this->clearCredits($user);
        $types = ['cash', 'aiToken', 'voucher', 'bonus', 'reward', 'gift', 'refund', 'credit', 'promo', 'cashback'];
        for ($i = 0; $i < 10; $i++) {
            CreditBalance::factory()->create([
                'owner_type' => User::class,
                'owner_id' => $user->id,
                'type' => $types[$i],
                'balance' => rand(1000, 50000),
            ]);
        }

        $queriesWithTenCredits = $this->getQueryCountForMeEndpoint($user);

        // Assert that query count doesn't scale significantly with number of credits
        // A small decrease is fine (due to caching), but we don't want it to increase
        $this->assertLessThanOrEqual(
            $queriesWithOneCredit,
            $queriesWithTenCredits,
            "Query count increased from {$queriesWithOneCredit} to {$queriesWithTenCredits} when credits went from 1 to 10. This indicates N+1 query issue."
        );

        // Also ensure the difference is not too large (should be similar)
        $difference = abs($queriesWithOneCredit - $queriesWithTenCredits);
        $this->assertLessThanOrEqual(6, $difference, 'Query count difference should be minimal between 1 and 10 credits');
    }

    #[Test]
    public function test_credits_relationship_is_properly_eager_loaded(): void
    {
        // Create a user
        $user = User::factory()->create();

        // Create multiple credits (with unique types) matching CreditTypes values
        $types = ['cash', 'ai_token', 'email'];
        for ($i = 0; $i < 3; $i++) {
            CreditBalance::factory()->create([
                'owner_type' => User::class,
                'owner_id' => $user->id,
                'type' => $types[$i],
                'balance' => ($i + 1) * 1000,
            ]);
        }

        // Clear query log and make request
        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($user)->getJson(self::ME_ENDPOINT);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Assert response is successful
        $response->assertStatus(200);

        // Check that credits are returned correctly
        $creditBalance = $response->json('data.credit_balance');
        // Expect keys defined by CreditTypes enum with numeric balances
        $this->assertArrayHasKey('cash', $creditBalance);
        $this->assertArrayHasKey('ai_token', $creditBalance);
        $this->assertArrayHasKey('email', $creditBalance);
        $this->assertIsNumeric($creditBalance['cash']);
        $this->assertIsNumeric($creditBalance['ai_token']);
        $this->assertIsNumeric($creditBalance['email']);

        // Verify that credits were loaded in a single query
        $creditQueries = array_filter($queries, function (array $query): bool {
            return str_contains($query['query'], 'credit_balances');
        });

        $this->assertCount(1, $creditQueries, 'Credits should be loaded in exactly one query (eager loaded)');
    }

    #[Test]
    public function test_me_endpoint_handles_user_with_no_credits(): void
    {
        // Create a user with minimal data
        $user = User::factory()->create();

        // Clear query log and make request
        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($user)->getJson(self::ME_ENDPOINT);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Assert response is successful
        $response->assertStatus(200);

        // Should still have reasonable query count even with no relationships
        $this->assertLessThan(20, count($queries), 'Query count should be reasonable even for users with no relationships');

        // Verify credit balance keys exist with zeroed values (not empty array)
        $this->assertArrayHasKey('credit_balance', $response->json('data'));
        $balance = $response->json('data.credit_balance');
        $this->assertArrayHasKey('cash', $balance);
        $this->assertArrayHasKey('ai_token', $balance);
        $this->assertArrayHasKey('email', $balance);
        $this->assertArrayHasKey('sms', $balance);
        $this->assertEquals(0, $balance['cash']);
        $this->assertEquals(0, $balance['ai_token']);
    }

    /**
     * Helper method to get query count for me endpoint
     */
    private function getQueryCountForMeEndpoint(User $user): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($user)->getJson(self::ME_ENDPOINT);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertStatus(200);

        return count($queries);
    }

    /**
     * Helper method to clear all credits for a user
     */
    private function clearCredits(User $user): void
    {
        CreditBalance::where('owner_type', User::class)
            ->where('owner_id', $user->id)
            ->delete();
    }
}
