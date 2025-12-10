<?php

namespace Tests\Unit\Services\Apideck;

use App\Services\Apideck\ApideckService;
use Http;
use Illuminate\Http\Client\Response;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('apideck')]
class ApideckUnsupportedFilterTest extends TestCase
{
    private ApideckService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.apideck.base_url' => 'https://unify.apideck.test']);
        config(['services.apideck.key' => 'test-api-key']);
        config(['services.apideck.app_id' => 'test-app-id']);
        config(['services.apideck.consumer_id' => 'test-consumer-id']);

        $this->service = new ApideckService;
        $this->service->initializeConsumerId();
    }

    #[Test]
    public function it_handles_unsupported_filter_error_gracefully(): void
    {
        // Simulate Officient-io connector response sequence
        $unsupportedFilterErrorResponse = [
            'status_code' => 400,
            'error' => 'Bad Request',
            'type_name' => 'UnsupportedFiltersError',
            'message' => 'Unsupported Filters Error',
            'detail' => [
                'context' => 'Filters are not supported on the Employees resource for the Officient-io Connector',
                'service_id' => 'officient-io',
                'supported_filters' => [],
            ],
            'ref' => 'https://developers.apideck.com/errors#unsupportedfilterserror',
        ];

        $successResponseWithoutFilter = [
            'data' => [
                [
                    'id' => 'emp_001',
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'email' => 'john@test.com',
                    'employment_status' => 'active',
                ],
                [
                    'id' => 'emp_002',
                    'first_name' => 'Jane',
                    'last_name' => 'Inactive',
                    'email' => 'jane@test.com',
                    'employment_status' => 'terminated',
                ],
                [
                    'id' => 'emp_003',
                    'first_name' => 'Bob',
                    'last_name' => 'Active',
                    'email' => 'bob@test.com',
                    'employment_status' => 'active',
                ],
            ],
            'meta' => [
                'items_on_page' => 3,
                'cursors' => ['next' => null],
            ],
        ];

        // Mock HTTP calls in sequence:
        // 1. First call WITH filter => 400 UnsupportedFiltersError
        // 2. Second call WITHOUT filter => 200 success with all employees
        Http::fake([
            // First call with filter (will fail)
            'https://unify.apideck.test/hris/employees?limit=200&filter%5Bemployment_status%5D=active' => Http::response($unsupportedFilterErrorResponse, 400),

            // Second call without filter (will succeed)
            'https://unify.apideck.test/hris/employees?limit=200' => Http::response($successResponseWithoutFilter, 200),
        ]);

        // Execute the method that should handle the fallback
        $result = $this->service->fetchAllEmployees();

        // Assertions
        $this->assertArrayHasKey('employees', $result);
        $this->assertArrayHasKey('meta', $result);

        // Should have 2 active employees (client-side filtered)
        $this->assertCount(2, $result['employees']);

        // Verify only active employees are returned
        foreach ($result['employees'] as $employee) {
            $this->assertTrue(
                ! isset($employee['employment_status']) || $employee['employment_status'] === 'active',
                'Only active employees should be returned'
            );
        }

        // Verify the active employees are correct ones
        $employeeIds = array_column($result['employees'], 'id');
        $this->assertContains('emp_001', $employeeIds);
        $this->assertContains('emp_003', $employeeIds);
        $this->assertNotContains('emp_002', $employeeIds); // Terminated employee excluded
    }

    #[Test]
    public function it_applies_client_side_filter_when_api_filter_unavailable(): void
    {
        // This test verifies that even if API filtering fails,
        // client-side filtering ensures only active employees are returned

        $allEmployeesResponse = [
            'data' => [
                ['id' => '1', 'first_name' => 'Active', 'employment_status' => 'active'],
                ['id' => '2', 'first_name' => 'Inactive', 'employment_status' => 'inactive'],
                ['id' => '3', 'first_name' => 'Terminated', 'employment_status' => 'terminated'],
                ['id' => '4', 'first_name' => 'Active2', 'employment_status' => 'active'],
            ],
            'meta' => ['cursors' => ['next' => null]],
        ];

        // Fake all possible HTTP calls (with and without filter)
        Http::fake([
            'https://unify.apideck.test/hris/employees*' => Http::response($allEmployeesResponse, 200),
        ]);

        $result = $this->service->fetchAllEmployees();

        // Should have only 2 active employees after client-side filtering
        $this->assertCount(2, $result['employees']);

        // Verify all returned employees are active
        foreach ($result['employees'] as $employee) {
            $this->assertEquals('active', $employee['employment_status']);
        }
    }
}
