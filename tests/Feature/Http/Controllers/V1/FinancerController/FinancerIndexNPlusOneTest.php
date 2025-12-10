<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\V1\FinancerController;

use App\Enums\IDP\RoleDefaults;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('performance')]
#[Group('financer')]
class FinancerIndexNPlusOneTest extends ProtectedRouteTestCase
{
    use DatabaseTransactions;

    protected bool $checkAuth = false;

    protected bool $checkPermissions = false;

    #[Test]
    public function it_does_not_have_n_plus_one_query_on_financers_index_for_division_relation(): void
    {
        // Arrange - Create test data with ModelFactory
        $division = ModelFactory::createDivision([
            'name' => 'Test Division',
            'status' => 'active',
        ]);

        // Create 5 financers with the same division
        $financers = [];
        for ($i = 0; $i < 5; $i++) {
            $financers[] = ModelFactory::createFinancer([
                'division_id' => $division->id,
                'name' => "Test Financer {$i}",
                'status' => 'active',
            ]);
        }

        // Create authenticated user with GOD role to bypass division filters
        $user = $this->createAuthUser(RoleDefaults::GOD);

        // Act - Enable query log
        DB::enableQueryLog();
        $response = $this->actingAs($user)
            ->getJson('/api/v1/financers');
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Assert - Should have only 1 division query with eager loading, not N+1
        $divisionQueries = collect($queries)->filter(function (array $query): bool {
            return str_contains($query['query'], 'from "divisions"') &&
                   str_contains($query['query'], 'where "divisions"."id" in');
        });

        // Maximum 1 query for divisions (eager loading)
        $this->assertLessThanOrEqual(1, $divisionQueries->count(),
            'Expected maximum 1 division query with eager loading, found '.$divisionQueries->count()
        );

        $response->assertOk();
    }

    #[Test]
    public function it_does_not_have_n_plus_one_query_on_financers_index_for_modules_relation(): void
    {
        // Arrange - Create test data with ModelFactory
        $division = ModelFactory::createDivision([
            'name' => 'Test Division',
            'status' => 'active',
        ]);

        // Create 5 financers with the same division
        $financers = [];
        for ($i = 0; $i < 5; $i++) {
            $financers[] = ModelFactory::createFinancer([
                'division_id' => $division->id,
                'name' => "Test Financer {$i}",
                'status' => 'active',
            ]);
        }

        // Create authenticated user with GOD role to bypass division filters
        $user = $this->createAuthUser(RoleDefaults::GOD);

        // Act - Enable query log
        DB::enableQueryLog();
        $response = $this->actingAs($user)
            ->getJson('/api/v1/financers');
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Assert - Should have only 1 modules query with eager loading, not N+1
        $moduleQueries = collect($queries)->filter(function (array $query): bool {
            return str_contains($query['query'], 'from "modules"');
        });

        // Maximum 2 queries for modules (1 for division.modules + 1 for financer.modules)
        $this->assertLessThanOrEqual(2, $moduleQueries->count(),
            'Expected maximum 2 module queries with eager loading, found '.$moduleQueries->count()
        );

        $response->assertOk();
    }
}
