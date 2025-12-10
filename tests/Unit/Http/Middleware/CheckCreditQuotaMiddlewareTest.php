<?php

namespace Tests\Unit\Http\Middleware;

use App\Enums\IDP\RoleDefaults;
use App\Http\Middleware\CheckCreditQuotaMiddleware;
use App\Models\CreditBalance;
use App\Models\Financer;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('middleware')]
class CheckCreditQuotaMiddlewareTest extends ProtectedRouteTestCase
{
    private CheckCreditQuotaMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new CheckCreditQuotaMiddleware;
    }

    #[Test]
    public function it_handles_lazy_loading_properly_when_relation_is_not_loaded(): void
    {
        // Enable strict mode to catch lazy loading violations
        DB::enableQueryLog();

        // Create team first
        $team = Team::factory()->create();

        $user = User::factory()->create(['team_id' => $team->id]);
        $financer = Financer::factory()->create()->load('division');

        // Attach financer to user
        $user->financers()->attach($financer->id, ['active' => true, 'role' => RoleDefaults::BENEFICIARY]);

        // Create credit balance for user (insufficient)
        CreditBalance::factory()->create([
            'owner_type' => User::class,
            'owner_id' => $user->id,
            'type' => 'ai_token',
            'balance' => 0,
        ]);

        // Create credit balance for financer (sufficient)
        CreditBalance::factory()->create([
            'owner_type' => Financer::class,
            'owner_id' => $financer->id,
            'type' => 'ai_token',
            'balance' => 1000,
        ]);

        Auth::login($user);

        // Create request
        $request = Request::create('/test', 'POST', ['prompt' => 'test']);

        // Clear query log
        DB::flushQueryLog();

        // Execute middleware
        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        }, 'ai_token');

        // Check that the middleware didn't throw a lazy loading violation
        $this->assertEquals(200, $response->getStatusCode());

        // Verify the queries made
        $queries = DB::getQueryLog();
        $financerQueries = array_filter($queries, function (array $query): bool {
            return str_contains($query['query'], 'financers');
        });

        // Should have exactly one query to financers

        $this->assertCount(1, $financerQueries);
    }

    #[Test]
    public function it_uses_preloaded_relation_when_available(): void
    {
        DB::enableQueryLog();

        // Create team first
        $team = Team::factory()->create();

        $user = User::factory()->create(['team_id' => $team->id]);
        $financer = Financer::factory()->create()->load('division');

        // Attach financer to user
        $user->financers()->attach($financer->id, ['active' => true, 'role' => RoleDefaults::BENEFICIARY]);

        // Load user with financers relation
        $user = User::with('financers')->find($user->id);

        // Create credit balance for user (insufficient)
        CreditBalance::factory()->create([
            'owner_type' => User::class,
            'owner_id' => $user->id,
            'type' => 'ai_token',
            'balance' => 0,
        ]);

        // Create credit balance for financer (sufficient)
        CreditBalance::factory()->create([
            'owner_type' => Financer::class,
            'owner_id' => $financer->id,
            'type' => 'ai_token',
            'balance' => 1000,
        ]);

        Auth::login($user);

        // Create request
        $request = Request::create('/test', 'POST', ['prompt' => 'test']);

        // Clear query log
        DB::flushQueryLog();

        // Execute middleware
        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        }, 'ai_token');

        // Check that the middleware succeeded
        $this->assertEquals(200, $response->getStatusCode());

        // Verify no additional queries to financers were made
        $queries = DB::getQueryLog();
        $financerQueries = array_filter($queries, function (array $query): bool {
            return str_contains($query['query'], 'financers');
        });

        // Should have no queries to financers since relation was preloaded
        $this->assertCount(0, $financerQueries);
    }

    #[Test]
    public function it_sets_credit_division_attributes_on_request(): void
    {
        $team = Team::factory()->create();
        $user = User::factory()->create(['team_id' => $team->id]);
        $financer = Financer::factory()->create()->load('division');

        $user->financers()->attach($financer->id, ['active' => true, 'role' => RoleDefaults::BENEFICIARY]);

        CreditBalance::factory()->create([
            'owner_type' => Financer::class,
            'owner_id' => $financer->id,
            'type' => 'ai_token',
            'balance' => 1000,
        ]);

        Auth::login($user);

        $request = Request::create('/test', 'POST', ['prompt' => 'test']);

        $capturedDivisionId = null;
        $capturedDivisionName = null;
        $this->middleware->handle($request, function ($req) use (&$capturedDivisionId, &$capturedDivisionName) {
            $capturedDivisionId = $req->get('credit_division_id');
            $capturedDivisionName = $req->get('credit_division_name');

            return response()->json(['success' => true]);
        }, 'ai_token');

        $this->assertEquals($financer->division_id, $capturedDivisionId);
        $this->assertEquals($financer->division->name, $capturedDivisionName);
    }

    #[Test]
    public function it_returns_403_when_no_sufficient_credit(): void
    {
        // Create team first
        $team = Team::factory()->create();

        $user = User::factory()->create(['team_id' => $team->id]);

        // Create insufficient credit balance for user
        CreditBalance::factory()->create([
            'owner_type' => User::class,
            'owner_id' => $user->id,
            'type' => 'ai_token',
            'balance' => 0,
        ]);

        Auth::login($user);

        // Create request
        $request = Request::create('/test', 'POST', ['prompt' => 'test']);

        // Execute middleware
        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true]);
        }, 'ai_token');

        // Check that the middleware returns 403
        $this->assertEquals(403, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('message', $responseData);
        $this->assertArrayHasKey('required', $responseData);
        $this->assertArrayHasKey('type', $responseData);
        $this->assertArrayHasKey('division_id', $responseData);
        $this->assertNull($responseData['division_id']);
    }
}
