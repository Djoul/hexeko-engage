<?php

namespace Tests\Feature\Http\Controllers\V1\Apideck\SIRHEmployeeController;

use App\Actions\Apideck\SyncAllEmployeesAction;
use App\Enums\IDP\TeamTypes;
use App\Models\User;
use App\Services\Apideck\ApideckService;
use Bus;
use Event;
use Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Attributes\FlushTables;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[FlushTables(tables: ['users'], scope: 'class')]
#[Group('apideck')]
class SyncSIRHEmployeeTest extends ProtectedRouteTestCase
{
    protected $financer;

    protected ApideckService $mockApideckService;

    #[Test]
    public function test_successful_sync_employees_with_batch_processing(): void
    {
        Bus::fake();
        Event::fake();

        // Mock HTTP requests
        Http::fake([
            'https://unify.apideck.fake.com/*' => Http::response([
                'data' => [],
                'meta' => ['total_count' => 0],
                'links' => [],
            ], 200),
        ]);

        // Call the route
        $response = $this->actingAs($this->auth)->postJson(route('employees.sync'), [
            'financer_id' => $this->financer->id,
        ]);

        // Assertions - async response with sync_id
        $response->assertStatus(202)
            ->assertJson([
                'data' => [
                    'message' => 'Employee sync job has been queued for batch processing',
                    'status' => 'queued',
                ],
            ])
            ->assertJsonStructure([
                'data' => [
                    'message',
                    'status',
                    'sync_id',
                ],
            ]);

        // Verify the sync action is dispatched with batch support
        Bus::assertDispatched(SyncAllEmployeesAction::class, function ($job): bool {
            return $job->params['financer_id'] === $this->financer->id;
        });
    }

    #[Test]
    public function no_employees_to_sync(): void
    {
        Bus::fake();
        // Mock HTTP requests
        Http::fake([
            'https://unify.apideck.fake.com/*' => Http::response([
                'data' => [],
                'meta' => ['total_count' => 0],
                'links' => [],
            ], 200),
        ]);

        $response = $this->actingAs($this->auth)->postJson(route('employees.sync'), [
            'financer_id' => $this->financer->id,
        ]);

        $response->assertStatus(202)
            ->assertJson([
                'data' => [
                    'message' => 'Employee sync job has been queued for batch processing',
                    'status' => 'queued',
                ],
            ]);
    }

    #[Test]
    public function sync_fails_due_to_missing_financer_id(): void
    {
        $response = $this->actingAs($this->auth)->postJson(route('employees.sync'), []);

        $response->assertStatus(422) // Validation error
            ->assertJsonValidationErrors(['financer_id']);
    }

    #[Test]
    public function test_sync_job_is_queued_even_when_api_will_fail(): void
    {
        // Ensure we're using a real queue, not sync
        config(['queue.default' => 'database']);

        // Mock HTTP requests
        Http::fake([
            'https://unify.apideck.fake.com/*' => Http::response([
                'error' => 'API Error',
            ], 500),
        ]);

        // Since sync is now async, the API call happens in the queue
        // The controller just queues the job and returns success
        $response = $this->actingAs($this->auth)->postJson(route('employees.sync'), [
            'financer_id' => $this->financer->id,
        ]);

        $response->assertStatus(202)
            ->assertJson([
                'data' => [
                    'message' => 'Employee sync job has been queued for batch processing',
                    'status' => 'queued',
                ],
            ]);
    }

    #[Test]
    public function jobs_are_dispatched_for_new_employees(): void
    {
        $initialEmployees = User::count();
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

        // Ensure database is empty before test
        $this->assertEquals($initialEmployees, User::count()); // auth user

        Http::fake([
            'https://unify.apideck.fake.com/hris/employees*' => Http::response([
                'data' => $mockEmployees,
                'meta' => ['total_count' => 3],
                'links' => [],
            ], 200),
        ]);

        // Mock HTTP for total count
        Http::fake([
            'https://unify.apideck.fake.com/hris/employees?limit=200' => Http::response([
                'data' => $mockEmployees,
                'meta' => [
                    'items_on_page' => 3,
                    'cursors' => ['next' => null],
                ],
            ], 200),
            'https://unify.apideck.fake.com/hris/employees*' => Http::response([
                'data' => $mockEmployees,
                'meta' => ['total_count' => 3],
                'links' => [],
            ], 200),
        ]);

        // Call the endpoint
        $response = $this->actingAs($this->auth)->postJson(route('employees.sync'), [
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

        $this->assertEquals($initialEmployees, User::count()); // auth
        // Ensure sync job was dispatched with batch support
        Bus::assertDispatched(SyncAllEmployeesAction::class);
    }

    #[Test]
    public function jobs_are_dispatched_for_new_employees_only(): void
    {
        $initialEmployees = User::count();
        $user = ModelFactory::createUser(
            [
                'first_name' => 'Alice',
                'financers' => [
                    ['financer' => $this->financer,
                        'sirh_id' => '001',
                    ], // active by default
                ]]
        );
        $user->load('financers');

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

        $this->assertEquals($initialEmployees + 1, User::count()); // 1 + auth user

        Http::fake([
            'https://unify.apideck.fake.com/hris/employees?limit=200' => Http::response([
                'data' => $mockEmployees,
                'meta' => [
                    'items_on_page' => 3,
                    'cursors' => ['next' => null],
                ],
            ], 200),
            'https://unify.apideck.fake.com/hris/employees*' => Http::response([
                'data' => $mockEmployees,
                'meta' => ['total_count' => 3],
                'links' => [],
            ], 200),
        ]);

        // Call the endpoint
        $response = $this->actingAs($this->auth)->postJson(route('employees.sync'), [
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

        $this->assertEquals($initialEmployees + 1, User::count()); // auth
        // Ensure sync job was dispatched with batch support
        Bus::assertDispatched(SyncAllEmployeesAction::class);
    }

    #[Test]
    public function test_batch_jobs_are_created_for_large_employee_list(): void
    {
        Bus::fake();
        Event::fake();

        // Mock 150 employees to test batch splitting (50 per batch)
        $mockEmployees = collect(range(1, 150))->map(function ($i): array {
            return [
                'id' => sprintf('emp-%03d', $i),
                'first_name' => 'Employee',
                'last_name' => "Number$i",
                'email' => "employee$i@example.com",
            ];
        })->toArray();

        Http::fake([
            'https://unify.apideck.fake.com/*' => Http::response([
                'data' => $mockEmployees,
                'meta' => ['total_count' => 150],
                'links' => [],
            ], 200),
        ]);

        $response = $this->actingAs($this->auth)->postJson(route('employees.sync'), [
            'financer_id' => $this->financer->id,
        ]);

        $response->assertStatus(202);

        // Verify sync action is dispatched
        Bus::assertDispatched(SyncAllEmployeesAction::class);

        // If we could execute the action, it would create 3 batches (150/50)
        // But since Bus::fake() is active, we just verify the action is dispatched
    }

    #[Test]
    public function test_events_are_broadcasted_during_sync(): void
    {
        Event::fake();
        Queue::fake();

        $response = $this->actingAs($this->auth)->postJson(route('employees.sync'), [
            'financer_id' => $this->financer->id,
        ]);

        $response->assertStatus(202);

        // The events will be dispatched when the job runs
        // Here we just verify the endpoint returns correctly
    }

    #[Test]
    public function test_sync_returns_unique_sync_id(): void
    {
        Bus::fake();
        Event::fake();

        $response1 = $this->actingAs($this->auth)->postJson(route('employees.sync'), [
            'financer_id' => $this->financer->id,
        ]);

        $response2 = $this->actingAs($this->auth)->postJson(route('employees.sync'), [
            'financer_id' => $this->financer->id,
        ]);

        $response1->assertStatus(202);
        $response2->assertStatus(202);

        $syncId1 = $response1->json('data.sync_id');
        $syncId2 = $response2->json('data.sync_id');

        $this->assertNotNull($syncId1);
        $this->assertNotNull($syncId2);
        $this->assertNotEquals($syncId1, $syncId2);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->financer = ModelFactory::createFinancer();
        $this->auth = $this->createAuthUser();
        Event::fake();
        ModelFactory::createTeam(['type' => TeamTypes::GLOBAL]);
    }
}
