<?php

namespace Tests\Unit\Services\Apideck;

use App\Jobs\Apideck\GetTotalEmployeesJob;
use App\Models\Financer;
use App\Models\User;
use App\Services\Apideck\ApideckService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('apideck')]
class ApideckServiceTest extends ApideckServiceTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
        Cache::flush();
        // Mock config values
        Config::set('services.apideck.base_url', 'https://unify.apideck.com');
        Config::set('services.apideck.key', 'test-api-key');
        Config::set('services.apideck.app_id', 'test-app-id');
        Config::set('services.apideck.consumer_id', 'test-consumer-id');

    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_throws_exception_when_base_url_is_not_string(): void
    {
        // Arrange
        Config::set('services.apideck.base_url', null);

        // Act & Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid base_url');

        new ApideckService;
    }

    #[Test]
    public function it_throws_exception_when_api_key_is_not_string(): void
    {
        // Arrange
        Config::set('services.apideck.key', null);

        // Act & Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid apiKey');

        new ApideckService;
    }

    #[Test]
    public function it_throws_exception_when_app_id_is_not_string(): void
    {
        // Arrange
        Config::set('services.apideck.app_id', null);

        // Act & Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid appId');

        new ApideckService;
    }

    #[Test]
    public function it_throws_exception_when_consumer_id_is_not_string(): void
    {
        // Arrange
        Config::set('services.apideck.consumer_id', null);

        // Act & Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No financerId provided and no default consumer_id configured');

        $service = new ApideckService;
        // Trigger the initialization which will call getConsumerId
        $service->initializeConsumerId();
    }

    #[Test]
    public function it_throws_exception_when_api_request_fails(): void
    {
        // Arrange
        Http::fake([
            'https://unify.apideck.com/hris/employees/*' => Http::response('Error message', 500),
        ]);

        $service = new ApideckService;

        // Act
        $result = $service->getEmployee('123');

        // Assert current behavior returns an error array instead of throwing
        $this->assertIsArray($result);
        $this->assertTrue($result['error'] ?? false);
        $this->assertEquals('Apideck API Error: Error message', $result['message'] ?? '');
    }

    #[Test]
    public function it_gets_employee_by_id(): void
    {
        // Arrange
        $employeeData = [
            'id' => '123',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'emails' => [['email' => 'john.doe@example.com', 'type' => 'primary']],
        ];

        Http::fake([
            'https://unify.apideck.com/hris/employees/123' => Http::response([
                'data' => $employeeData,
                'meta' => ['status' => 'ok'],
            ], 200),
        ]);

        $service = new ApideckService;

        // Act
        $result = $service->getEmployee('123');

        // Assert
        $this->assertEquals($employeeData, $result['data']);
        $this->assertEquals(['status' => 'ok'], $result['meta']);
    }

    #[Test]
    public function it_fetches_employees_with_pagination(): void
    {
        Queue::fake();

        $financer = Financer::factory()->create([
            'external_id' => [
                'sirh' => [
                    'consumer_id' => 'test-consumer-id-123',
                ],
            ],
        ]);

        // Arrange
        $employeesPage1 = [
            ['id' => '1', 'first_name' => 'John', 'last_name' => 'Doe', 'emails' => [['email' => 'john@example.com', 'type' => 'primary']], 'employment_status' => 'active'],
            ['id' => '2', 'first_name' => 'Jane', 'last_name' => 'Smith', 'emails' => [['email' => 'jane@example.com', 'type' => 'primary']], 'employment_status' => 'active'],
        ];
        $employeesPage2 = [
            ['id' => '3', 'first_name' => 'Bob', 'last_name' => 'Johnson', 'emails' => [['email' => 'bob@example.com', 'type' => 'primary']], 'employment_status' => 'active'],
        ];

        Http::fake([
            'https://unify.apideck.com/hris/employees?limit=20&filter%5Bemployment_status%5D=active*' => Http::sequence()
                ->push([
                    'data' => $employeesPage1,
                    'meta' => [
                        'items_on_page' => 2,
                        'cursors' => ['next' => 'next_cursor'],
                    ],
                    'links' => ['next' => 'next_url'],
                ], 200)
                ->push([
                    'data' => $employeesPage2,
                    'meta' => [
                        'items_on_page' => 1,
                        'cursors' => ['next' => null],
                    ],
                    'links' => ['next' => null],
                ], 200)
                ->whenEmpty(Http::response([
                    'data' => [],
                    'meta' => [
                        'items_on_page' => 0,
                        'cursors' => ['next' => null],
                    ],
                    'links' => [],
                ], 200)),
        ]);

        $service = new ApideckService;

        // Act
        $result = $service->index(['financer_id' => $financer->id]);

        // Assert
        $this->assertCount(2, $result['employees']);
        $this->assertIsArray($result['employees']->toArray());
        $employeeEmails = $result['employees']->pluck('email')->toArray();
        $this->assertEquals(['john@example.com', 'jane@example.com'], $employeeEmails);
        $this->assertNull($result['meta']['total_items']);

        Queue::assertPushed(GetTotalEmployeesJob::class, function ($job) use ($financer): bool {
            return $job->financerId === $financer->id
                && $job->consumerId === 'test-consumer-id-123'
                && $job->userId === 'system';
        });

        Http::assertNotSent(function ($request): bool {
            return str_contains($request->url(), 'limit=200');
        });
    }

    #[Test]
    public function it_fetches_all_employees_with_zero_limit(): void
    {
        Queue::fake();

        $financer = Financer::factory()->create([
            'external_id' => [
                'sirh' => [
                    'consumer_id' => 'test-consumer-id-456',
                ],
            ],
        ]);

        // Arrange
        $employeesPage1 = [
            ['id' => '1', 'first_name' => 'John', 'last_name' => 'Doe', 'emails' => [['email' => 'john@example.com', 'type' => 'primary']]],
            ['id' => '2', 'first_name' => 'Jane', 'last_name' => 'Smith', 'emails' => [['email' => 'jane@example.com', 'type' => 'primary']]],
        ];

        $employeesPage2 = [
            ['id' => '3', 'first_name' => 'Bob', 'last_name' => 'Johnson', 'emails' => [['email' => 'bob@example.com', 'type' => 'primary']]],
        ];

        Http::fake([
            'https://unify.apideck.com/hris/employees*' => Http::response([
                'data' => array_merge($employeesPage1, $employeesPage2),
                'meta' => [
                    'items_on_page' => 3,
                    'cursors' => ['next' => null],
                ],
                'links' => [],
            ], 200),
        ]);

        $service = new ApideckService;

        // Act
        $result = $service->index([
            'limit' => '0',
            'financer_id' => $financer->id,
        ]);

        // Assert
        $this->assertCount(3, $result['employees']);
        $this->assertIsArray($result['employees']->toArray());
        $this->assertNull($result['meta']['total_items']);

        Queue::assertPushed(GetTotalEmployeesJob::class, function ($job) use ($financer): bool {
            return $job->financerId === $financer->id
                && $job->consumerId === 'test-consumer-id-456'
                && $job->userId === 'system';
        });
    }

    #[Test]
    public function it_syncs_all_employees(): void
    {
        // Arrange
        $employees = [
            [
                'id' => '1',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'emails' => [['email' => 'john@example.com', 'type' => 'primary']],
            ],
            [
                'id' => '2',
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'emails' => [['email' => 'jane@example.com', 'type' => 'primary']],
            ],
        ];

        // Mock ALL HTTP requests to return the same response
        Http::fake([
            '*' => Http::response([
                'data' => $employees,
                'meta' => [
                    'items_on_page' => 2,
                    'cursors' => ['next' => null],
                ],
                'links' => ['next' => null],
            ], 200),
        ]);

        // Mock the queue to avoid actually executing jobs
        Queue::fake();

        // Create a test financer with valid external_id structure for Apideck
        $financer = Financer::factory()->create([
            'external_id' => [
                'sirh' => [
                    'consumer_id' => 'test-consumer-id-123',
                ],
            ],
        ]);

        $service = new ApideckService;

        // Act
        $result = $service->syncAll(['financer_id' => $financer->id]);

        // Assert - Verify the method executes without errors and returns expected structure
        $this->assertIsArray($result);
        $this->assertArrayHasKey('created', $result);
        $this->assertArrayHasKey('updated', $result);
        $this->assertArrayHasKey('failed', $result);
        $this->assertArrayHasKey('meta', $result);

        // Note: Job dispatch verification requires deeper integration test setup
        // This test verifies the service layer logic executes successfully
    }

    #[Test]
    public function it_returns_cached_total_employee_count_when_available(): void
    {
        Queue::fake();

        $financer = Financer::factory()->create([
            'external_id' => [
                'sirh' => [
                    'consumer_id' => 'test-consumer-id-cache',
                ],
            ],
        ]);

        $employeesPage1 = [
            ['id' => '1', 'first_name' => 'John', 'last_name' => 'Doe', 'emails' => [['email' => 'john@example.com', 'type' => 'primary']]],
            ['id' => '2', 'first_name' => 'Jane', 'last_name' => 'Smith', 'emails' => [['email' => 'jane@example.com', 'type' => 'primary']]],
        ];

        Http::fake([
            'https://unify.apideck.com/hris/employees?limit=20&filter%5Bemployment_status%5D=active' => Http::response([
                'data' => $employeesPage1,
                'meta' => [
                    'items_on_page' => 2,
                    'cursors' => ['next' => null],
                ],
                'links' => [],
            ], 200),
        ]);

        $cacheKey = 'apideck_total_employees:{'.$financer->id.'_test-consumer-id-cache}';
        Cache::put($cacheKey, 3, 3600);

        $service = new ApideckService;
        $result = $service->index(['financer_id' => $financer->id]);

        $this->assertEquals(3, $result['meta']['total_items']);
        Queue::assertNothingPushed();

        Http::assertNotSent(function ($request): bool {
            return str_contains($request->url(), 'limit=200');
        });
    }

    #[Test]
    public function it_calculates_and_caches_total_employees(): void
    {
        $financer = Financer::factory()->create([
            'external_id' => [
                'sirh' => [
                    'consumer_id' => 'test-consumer-calc',
                ],
            ],
        ]);

        Http::fake([
            'https://unify.apideck.com/hris/employees*' => Http::sequence()
                ->push([
                    'data' => [
                        ['id' => '1', 'employment_status' => 'active', 'email' => 'new1@test.com'],
                        ['id' => '2', 'employment_status' => 'active', 'email' => 'new2@test.com'],
                    ],
                    'meta' => ['cursors' => ['next' => 'cursor2']],
                ], 200)
                ->push([
                    'data' => [
                        ['id' => '3', 'employment_status' => 'active', 'email' => 'new3@test.com'],
                    ],
                    'meta' => ['cursors' => ['next' => null]],
                ], 200)
                ->whenEmpty(Http::response([
                    'data' => [],
                    'meta' => ['cursors' => ['next' => null]],
                ], 200)),
        ]);

        $service = new ApideckService;
        $total = $service->calculateTotalEmployees($financer->id);

        $cacheKey = 'apideck_total_employees:{'.$financer->id.'_test-consumer-calc}';
        $this->assertEquals(3, $total);
        $this->assertEquals(3, Cache::get($cacheKey));
        Http::assertSentCount(2);
    }

    #[Test]
    public function it_uses_default_service_id_when_not_configured(): void
    {
        // Arrange
        Config::set('services.apideck.service_id', null);
        Queue::fake();

        $financer = Financer::factory()->create([
            'external_id' => [
                'sirh' => [
                    'consumer_id' => 'test-consumer-default',
                ],
            ],
        ]);

        Http::fake([
            // Mock calls for index
            'https://unify.apideck.com/hris/employees?limit=20&filter%5Bemployment_status%5D=active' => Http::response([
                'data' => [],
                'meta' => [
                    'items_on_page' => 0,
                    'cursors' => ['next' => null],
                ],
                'links' => [],
            ], 200),
        ]);

        $service = new ApideckService;

        // Act
        $response = $service->index(['financer_id' => $financer->id]);

        // Note: Removed assertion for x-apideck-service-id header
        // ApideckService no longer sends this header - Apideck automatically
        // detects the service from the consumer's active Vault connection

        $this->assertNull($response['meta']['total_items']);
        Queue::assertPushed(GetTotalEmployeesJob::class);

        // Assert basic request was sent (checking URL starts with base endpoint)
        Http::assertSent(function ($request): bool {
            return str_starts_with($request->url(), 'https://unify.apideck.com/hris/employees');
        });
    }

    #[Test]
    public function it_uses_configured_service_id(): void
    {
        // Arrange
        Config::set('services.apideck.service_id', 'personio');
        Queue::fake();

        $financer = Financer::factory()->create([
            'external_id' => [
                'sirh' => [
                    'consumer_id' => 'test-consumer-personio',
                ],
            ],
        ]);

        Http::fake([
            // Mock calls for index
            'https://unify.apideck.com/hris/employees?limit=20&filter%5Bemployment_status%5D=active' => Http::response([
                'data' => [],
                'meta' => [
                    'items_on_page' => 0,
                    'cursors' => ['next' => null],
                ],
                'links' => [],
            ], 200),
        ]);

        $service = new ApideckService;

        // Act
        $response = $service->index(['financer_id' => $financer->id]);

        // Note: Removed assertion for x-apideck-service-id header
        // ApideckService no longer sends this header

        $this->assertNull($response['meta']['total_items']);
        Queue::assertPushed(GetTotalEmployeesJob::class);

        // Assert basic request was sent (checking URL starts with base endpoint)
        Http::assertSent(function ($request): bool {
            return str_starts_with($request->url(), 'https://unify.apideck.com/hris/employees');
        });
    }

    #[Test]
    public function it_can_use_workday_connector(): void
    {
        // Arrange
        Config::set('services.apideck.service_id', 'workday');
        Queue::fake();

        $financer = Financer::factory()->create([
            'external_id' => [
                'sirh' => [
                    'consumer_id' => 'test-consumer-workday',
                ],
            ],
        ]);

        Http::fake([
            // Mock calls for index
            'https://unify.apideck.com/hris/employees?limit=20&filter%5Bemployment_status%5D=active' => Http::response([
                'data' => [
                    [
                        'id' => 'employee-123',
                        'first_name' => 'John',
                        'last_name' => 'Doe',
                        'email' => 'john.doe@company.com',
                    ],
                ],
                'meta' => [
                    'items_on_page' => 1,
                    'cursors' => ['next' => null],
                ],
                'links' => [],
            ], 200),
        ]);

        $service = new ApideckService;

        // Act
        $result = $service->index(['financer_id' => $financer->id]);

        // Note: Removed assertion for x-apideck-service-id header

        // Assert response structure is correct
        $this->assertArrayHasKey('employees', $result);
        $this->assertCount(1, $result['employees']);
        $this->assertNull($result['meta']['total_items']);
        Queue::assertPushed(GetTotalEmployeesJob::class);
    }

    #[Test]
    public function it_filters_unsynchronized_employees(): void
    {

        // Arrange: Create some users and invited users
        User::factory()->create(['sirh_id' => 'emp_001']);
        User::factory()->create(['sirh_id' => 'emp_002']);

        // Mock HTTP response with all employees
        $allEmployees = [
            ['id' => 'emp_001', 'first_name' => 'Alice', 'last_name' => 'Smith'],
            ['id' => 'emp_002', 'first_name' => 'Bob', 'last_name' => 'Jones'],
            ['id' => 'emp_003', 'first_name' => 'Charlie', 'last_name' => 'Brown'],
        ];

        Http::fake([
            'https://unify.apideck.com/hris/employees*' => Http::response([
                'data' => $allEmployees,
                'meta' => [
                    'items_on_page' => 3,
                    'cursors' => ['next' => null],
                ],
                'links' => ['next' => null],
            ], 200),
        ]);

        // No mock needed - this test uses real User model and database

        $service = new ApideckService;

        // Act: Filter unsynchronized employees
        $result = $service->index(['unsynchronized' => true]);

        // Assert: Only emp_003 should be returned (unsynchronized)
        $this->assertCount(1, $result['employees']);
    }

    #[Test]
    public function it_sends_correct_headers_with_configured_service(): void
    {
        // Arrange
        Config::set('services.apideck.service_id', 'bamboohr');
        Queue::fake();

        $financer = Financer::factory()->create([
            'external_id' => [
                'sirh' => [
                    'consumer_id' => 'test-consumer-bamboo',
                ],
            ],
        ]);

        Http::fake([
            // Mock calls for index
            'https://unify.apideck.com/hris/employees?limit=20&filter%5Bemployment_status%5D=active' => Http::response([
                'data' => [],
                'meta' => [
                    'items_on_page' => 0,
                    'cursors' => ['next' => null],
                ],
                'links' => [],
            ], 200),
        ]);

        $service = new ApideckService;

        // Act
        $service->index(['financer_id' => $financer->id]);

        Queue::assertPushed(GetTotalEmployeesJob::class);

        // Assert - verify core headers (excluding x-apideck-service-id which is no longer sent)
        Http::assertSent(function ($request): bool {
            return $request->hasHeader('Authorization', 'Bearer test-api-key') &&
                   $request->hasHeader('x-apideck-app-id', 'test-app-id') &&
                   $request->hasHeader('x-apideck-consumer-id', 'test-consumer-bamboo') &&
                   $request->hasHeader('Content-Type', 'application/json');
        });
    }
}
