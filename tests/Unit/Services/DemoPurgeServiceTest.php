<?php

namespace Tests\Unit\Services;

use App\Models\DemoEntity;
use App\Models\Division;
use App\Models\Financer;
use App\Models\Team;
use App\Models\User;
use App\Services\DemoPurgeService;
use Exception;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('demo')]
class DemoPurgeServiceTest extends TestCase
{
    use DatabaseTransactions;

    private DemoPurgeService $purgeService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->purgeService = new DemoPurgeService;

        // Ensure required data exists
        if (Team::count() === 0) {
            Artisan::call('db:seed', ['--class' => 'TeamSeeder']);
        }
        if (Division::count() === 0) {
            Artisan::call('db:seed', ['--class' => 'DivisionSeeder']);
        }
    }

    #[Test]
    public function it_purges_all_demo_data(): void
    {
        // Clear any existing demo entities first
        DemoEntity::query()->delete();

        // Create demo data
        $division = Division::first();
        $financer = ModelFactory::createFinancer([
            'name' => 'Test Demo Financer',
            'division_id' => $division->id,
        ]);
        $financer->markAsDemo();

        $user = ModelFactory::createUser([
            'email' => 'demo-'.uniqid().'@test.com',
            'financers' => [
                ['financer' => $financer, 'active' => true],
            ],
        ], );
        $user->markAsDemo();

        // Get counts before purge
        $initialUserCount = User::count();
        $initialFinancerCount = Financer::count();
        $demoEntityCount = DemoEntity::count();

        $this->assertEquals(2, $demoEntityCount); // User and Financer

        // Purge demo data
        $statistics = $this->purgeService->purge();

        // Assert statistics
        $this->assertEquals(1, $statistics['users_deleted']);
        $this->assertEquals(1, $statistics['financers_deleted']);
        $this->assertEquals(2, $statistics['demo_entities_deleted']);

        // Assert data was deleted
        $this->assertEquals($initialUserCount - 1, User::count());
        $this->assertEquals($initialFinancerCount - 1, Financer::count());
        $this->assertEquals(0, DemoEntity::count());

        // Verify specific entities were deleted
        $this->assertNull(User::find($user->id));
        $this->assertNull(Financer::find($financer->id));
    }

    #[Test]
    public function it_performs_dry_run_without_deleting_data(): void
    {
        // Clear any existing demo entities first
        DemoEntity::query()->delete();

        // Create demo data
        $division = Division::first();
        $financer = ModelFactory::createFinancer([
            'name' => 'Test Demo Financer Dry',
            'division_id' => $division->id,
        ]);
        $financer->markAsDemo();

        $user = ModelFactory::createUser([
            'email' => 'demo-dry-'.uniqid().'@test.com',
            'financers' => [
                ['financer' => $financer, 'active' => true],
            ],
        ]);
        $user->markAsDemo();

        // Get counts before dry run
        $initialUserCount = User::count();
        $initialFinancerCount = Financer::count();
        $initialDemoEntityCount = DemoEntity::count();

        // Perform dry run
        $statistics = $this->purgeService->purge(dryRun: true);

        // Assert statistics show what would be deleted
        $this->assertEquals(1, $statistics['users_deleted']);
        $this->assertEquals(1, $statistics['financers_deleted']);
        $this->assertEquals(2, $statistics['demo_entities_deleted']);

        // Assert no data was actually deleted
        $this->assertEquals($initialUserCount, User::count());
        $this->assertEquals($initialFinancerCount, Financer::count());
        $this->assertEquals($initialDemoEntityCount, DemoEntity::count());

        // Verify entities still exist
        $this->assertNotNull(User::find($user->id));
        $this->assertNotNull(Financer::find($financer->id));
    }

    #[Test]
    public function it_soft_deletes_demo_data(): void
    {
        // Count existing demo entities before creating new ones
        DemoEntity::where('entity_type', User::class)->count();
        DemoEntity::where('entity_type', Financer::class)->count();

        // Create demo data
        $division = Division::first();
        $financer = ModelFactory::createFinancer([
            'name' => 'Test Demo Financer Soft',
            'division_id' => $division->id,
        ]);
        $financer->markAsDemo();

        $user = ModelFactory::createUser([
            'email' => 'demo-soft-'.uniqid().'@test.com',
            'financers' => [
                ['financer' => $financer, 'active' => true],
            ],
        ]);
        $user->markAsDemo();

        // Perform soft delete
        $statistics = $this->purgeService->softDelete();

        // Assert statistics - should include all demo entities, not just the ones we created
        $this->assertGreaterThanOrEqual(1, $statistics['users_soft_deleted']);
        $this->assertGreaterThanOrEqual(1, $statistics['financers_soft_deleted']);

        // Assert data is soft deleted
        $this->assertNull(User::find($user->id));
        $this->assertNull(Financer::find($financer->id));

        // But still exists when including trashed
        $this->assertNotNull(User::withTrashed()->find($user->id));
        $this->assertNotNull(Financer::withTrashed()->find($financer->id));

        // Demo entities should still exist
        $this->assertGreaterThan(0, DemoEntity::count());
    }

    #[Test]
    public function it_gets_soft_delete_candidates(): void
    {
        // Create demo data
        $division = Division::first();
        $financer = ModelFactory::createFinancer([
            'name' => 'Test Candidate Financer',
            'division_id' => $division->id,
        ]);
        $financer->markAsDemo();

        $user = ModelFactory::createUser([
            'email' => 'candidate-'.uniqid().'@test.com',
            'financers' => [
                ['financer' => $financer, 'active' => true],
            ],
        ]);
        $user->markAsDemo();

        // Get candidates
        $candidates = $this->purgeService->getSoftDeleteCandidates();

        // Assert we have the right candidates
        $userCandidates = collect($candidates)->where('type', 'User');
        $financerCandidates = collect($candidates)->where('type', 'Financer');

        $this->assertGreaterThan(0, $userCandidates->count());
        $this->assertGreaterThan(0, $financerCandidates->count());

        // Check our specific entities are in the list
        $this->assertTrue($userCandidates->contains('id', $user->id));
        $this->assertTrue($financerCandidates->contains('id', $financer->id));
    }

    #[Test]
    public function it_only_purges_demo_marked_entities(): void
    {
        // Create regular (non-demo) data
        $division = Division::first();
        $regularFinancer = ModelFactory::createFinancer([
            'name' => 'Regular Financer',
            'division_id' => $division->id,
        ]);
        // NOT marking as demo

        $regularUser = ModelFactory::createUser([
            'email' => 'regular-'.uniqid().'@test.com',
            'financers' => [
                ['financer' => $regularFinancer, 'active' => true],
            ],
        ]);
        // NOT marking as demo

        // Create demo data
        $demoFinancer = ModelFactory::createFinancer([
            'name' => 'Demo Financer',
            'division_id' => $division->id,
        ]);
        $demoFinancer->markAsDemo();

        $demoUser = ModelFactory::createUser([
            'email' => 'demo-marked-'.uniqid().'@test.com',
            'financers' => [
                ['financer' => $demoFinancer, 'active' => true],
            ],
        ]);
        $demoUser->markAsDemo();

        // Purge demo data
        $this->purgeService->purge();

        // Regular entities should still exist
        $this->assertNotNull(User::find($regularUser->id));
        $this->assertNotNull(Financer::find($regularFinancer->id));

        // Demo entities should be deleted
        $this->assertNull(User::find($demoUser->id));
        $this->assertNull(Financer::find($demoFinancer->id));
    }

    #[Test]
    public function it_throws_exception_in_production_without_config(): void
    {
        // Mock production environment
        app()->detectEnvironment(fn (): string => 'production');
        config(['demo.allowed' => false]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Demo purge is not allowed in production!');

        $this->purgeService->purge();
    }
}
