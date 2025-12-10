<?php

namespace Tests\Feature\Http\Controllers\V1\Apideck\SIRHEmployeeController;

use App\Actions\Apideck\SyncAllEmployeesAction;
use App\Enums\IDP\TeamTypes;
use App\Models\Team;
use App\Models\User;
use App\Services\Apideck\ApideckService;
use Bus;
use Http;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('apideck')]
class FetchSIRHEmployeeTest extends ProtectedRouteTestCase
{
    protected $financer;

    protected bool $checkAuth = false;

    protected bool $checkPermissions = false;

    protected ApideckService $mockApideckService;

    #[Test]
    public function it_can_fetch_employees_from_apideck(): void
    {
        $countUsers = 50;
        $users = User::factory()->count($countUsers)->make();
        $this->mockApideckService = Mockery::mock(ApideckService::class);
        $this->app->instance(ApideckService::class, $this->mockApideckService);

        $this->mockApideckService
            ->shouldReceive('index')
            ->once()
            ->with(
                Mockery::type('array'),
                20, // per_page default
                1   // page default
            )
            ->andReturn([
                'employees' => $users,
                'meta' => [
                    'cursors' => [
                        'previous' => null,
                        'current' => null,
                        'next' => 'MTAz',
                    ],
                    'items_on_page' => $countUsers,
                ],
                'links' => [
                    'previous' => null,
                    'current' => 'https://unify.apideck.com/hris/employees?limit=5',
                    'next' => 'https://unify.apideck.com/hris/employees?limit=5&cursor=MTAz',
                ],
            ]);

        $response = $this->actingAs($this->auth)->getJson(route('employees.index'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [ // '*' ensures it applies to all objects in `data`
                        'id',
                        'email',
                        'first_name',
                        'last_name',
                        'phone',
                        'financers',
                        'profile_image',
                        'entry_date',
                        'role',
                        'description',
                    ],
                ],
                'meta' => [
                    'cursors' => [
                        'previous',
                        'current',
                        'next',
                    ],
                    'items_on_page',
                ],
                'links' => [
                    'previous',
                    'current',
                    'next',
                ],
            ]);
    }

    #[Test]
    public function it_can_filter_unsynchronized_employees(): void
    {
        // Create test users with sirh_id in users table (JSON format)
        User::factory()->create([
            'sirh_id' => json_encode(['platform' => 'aws', 'id' => 'emp_001']),
        ]);
        User::factory()->create([
            'sirh_id' => json_encode(['platform' => 'aws', 'id' => 'emp_002']),
        ]);

        // Create invited users with sirh_id in pivot financer_user table
        ModelFactory::createUser([
            'email' => 'invited1@test.com',
            'invitation_status' => 'pending',
            'financers' => [
                [
                    'financer' => $this->financer,
                    'active' => false,
                    'sirh_id' => 'emp_003', // sirh_id in pivot
                ],
            ],
        ]);
        ModelFactory::createUser([
            'email' => 'invited2@test.com',
            'invitation_status' => 'pending',
            'financers' => [
                [
                    'financer' => $this->financer,
                    'active' => false,
                    'sirh_id' => 'emp_004', // sirh_id in pivot
                ],
            ],
        ]);

        // Mock Apideck to return 5 employees (3 should be filtered as unsynchronized)
        collect([
            ['id' => 'emp_001', 'first_name' => 'Alice', 'last_name' => 'Smith'],
            ['id' => 'emp_002', 'first_name' => 'Bob', 'last_name' => 'Jones'],
            ['id' => 'emp_003', 'first_name' => 'Charlie', 'last_name' => 'Brown'],
            ['id' => 'emp_004', 'first_name' => 'Diana', 'last_name' => 'Wilson'],
            ['id' => 'emp_005', 'first_name' => 'Eve', 'last_name' => 'Davis'],
        ]);

        // Mock ApideckService to return filtered employees
        $this->mockApideckService = Mockery::mock(ApideckService::class);
        $this->app->instance(ApideckService::class, $this->mockApideckService);

        // Expected filtered employees (only emp_005 should be returned as unsynchronized)
        $filteredEmployees = collect([
            User::factory()->make(['first_name' => 'Eve', 'last_name' => 'Davis']),
        ]);

        $this->mockApideckService
            ->shouldReceive('index')
            ->once()
            ->with(
                Mockery::on(function (array $params): bool {
                    return isset($params['unsynchronized']) && $params['unsynchronized'] === '1';
                }),
                20, // per_page default
                1   // page default
            )
            ->andReturn([
                'employees' => $filteredEmployees,
                'meta' => [
                    'cursors' => ['previous' => null, 'current' => null, 'next' => null],
                    'items_on_page' => 1,
                    'total_count' => 1,
                ],
                'links' => ['previous' => null, 'current' => null, 'next' => null],
            ]);

        // Make request with unsynchronized filter
        $response = $this->actingAs($this->auth)->getJson(route('employees.index', ['unsynchronized' => true]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'first_name',
                        'last_name',
                        'email',
                    ],
                ],
                'meta' => [
                    'items_on_page',
                    'total_count',
                ],
            ])
            ->assertJsonPath('meta.items_on_page', 1)
            ->assertJsonPath('meta.total_count', 1);
    }

    #[Test]
    public function jobs_are_dispatched_for_new_employees(): void
    {
        // Set fake base URL
        config(['services.apideck.base_url' => 'https://unify.apideck.fake.com']);
        // Fake the job dispatcher
        Bus::fake();

        // Mock API response with 3 employees
        $mockEmployees = collect([
            ['id' => '001', 'first_name' => 'Alice'],
            ['id' => '002', 'first_name' => 'Bob'],
            ['id' => '003', 'first_name' => 'Charlie'],
        ]);
        config(['queue.default' => 'database']);

        // Get initial user count
        $initialUserCount = User::count();

        Http::fake([
            'https://unify.apideck.fake.com/hris/employees*' => Http::response([
                'data' => $mockEmployees,
                'meta' => ['total_count' => 3],
                'links' => [],
            ], 200),
        ]);

        // Call the endpoint
        $response = $this->actingAs($this->auth)
            ->postJson(route('employees.sync'), [
                'financer_id' => $this->financer->id,
            ]);

        // Assertions - now expecting async response
        $response->assertStatus(202)
            ->assertJson([
                'data' => [
                    'message' => 'Employee sync job has been queued for batch processing',
                    'status' => 'queued',
                ],
            ]);

        // No new users should be created when dispatching job
        $this->assertEquals($initialUserCount, User::count());
        // Ensure sync job was dispatched
        Bus::assertDispatched(SyncAllEmployeesAction::class, function ($job): bool {
            return $job->params['financer_id'] === $this->financer->id;
        });
    }

    /*    #[Test]
        public function it_can_sync_employees_from_apideck()
        {
            $this->withoutExceptionHandling();
            $countEmployees = 2;
            $employees = ApideckEmployeeFactory::makeMany($countEmployees);

            // Fake the bus to track job dispatching
            Bus::fake();

            // Mock ApideckService
            $mockService = Mockery::mock(ApideckService::class);

            // Mock fetchEmployees() to return fake employees
            $mockService->shouldAllowMockingProtectedMethods();
            $mockService->shouldReceive('fetchEmployees')
                ->once()
                ->withAnyArgs()
                ->andReturn($employees);

            // Bind the mock service to the container
            $this->app->instance(ApideckService::class, $mockService);

            $financer = \App\Models\Financer::factory()->create(); // Create a financer for test

            $response = $this->postJson(route('employees.sync'), [
                'financer_id' => $financer->id,
            ]);

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'created'
                ],
            );

            // Assert the job was dispatched exactly **twice**
            Bus::assertDispatchedTimes(CreateInvitedUserAction::class, $countEmployees);
        }*/

    protected function setUp(): void
    {
        parent::setUp();

        // Create global team first
        Team::firstOrCreate(
            ['type' => TeamTypes::GLOBAL],
            ['name' => 'Global Team', 'slug' => 'global-team', 'type' => TeamTypes::GLOBAL]
        );

        // Create auth user with financer
        $this->auth = $this->createAuthUser();
        $this->financer = $this->auth->financers()->first();
    }
}
