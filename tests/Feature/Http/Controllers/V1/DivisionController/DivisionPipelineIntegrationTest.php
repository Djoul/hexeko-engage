<?php

namespace Tests\Feature\Http\Controllers\V1\DivisionController;

use App\Models\Division;
use App\Models\User;
use Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Attributes\FlushTables;
use Tests\ProtectedRouteTestCase;

#[FlushTables(tables: ['divisions'], scope: 'class')]
#[Group('division')]
class DivisionPipelineIntegrationTest extends ProtectedRouteTestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Clean up Laravel Context to ensure test isolation
        Context::flush();
        $this->auth = $this->createAuthUser();
        $this->actingAs($this->auth);
    }

    protected function tearDown(): void
    {
        // Clean up Laravel Context after each test
        Context::flush();

        parent::tearDown();
    }

    #[Test]
    public function test_index_endpoint_with_name_filter(): void
    {
        // Get initial count of divisions with 'Test' in their name
        $initialCount = Division::where('name', 'like', '%Test%')->count();

        // Arrange
        Division::factory()->create(['name' => 'Division Test']);
        Division::factory()->create(['name' => 'Another Division']);

        // Act
        $response = $this->getJson('/api/v1/divisions?name=Test');

        // Assert
        $response->assertStatus(200);
        $responseData = $response->json('data');

        // Check that we have exactly one more division with 'Test' in name than initially
        $this->assertEquals($initialCount + 1, count($responseData));

        // Verify our created division is in the results
        $divisionNames = collect($responseData)->pluck('name')->toArray();
        $this->assertContains('Division Test', $divisionNames);
    }

    #[Test]
    public function test_index_endpoint_with_country_filter(): void
    {
        // Get initial count of divisions with country FR
        $initialCount = Division::where('country', 'FR')->count();

        // Arrange
        Division::factory()->create(['country' => 'FR', 'name' => 'Division France']);
        Division::factory()->create(['country' => 'US', 'name' => 'Division USA']);

        // Act
        $response = $this->getJson('/api/v1/divisions?country=FR');

        // Assert
        $response->assertStatus(200);
        $responseData = $response->json('data');

        // Check that we have exactly one more FR division than initially
        $this->assertEquals($initialCount + 1, count($responseData));

        // Verify our created division is in the results
        $divisionNames = collect($responseData)->pluck('name')->toArray();
        $this->assertContains('Division France', $divisionNames);
    }

    #[Test]
    public function index_endpoint_with_multiple_filters(): void
    {
        // Get initial count of divisions matching criteria
        Division::where('name', 'like', '%Division%')
            ->where('country', 'FR')
            ->count();

        // Arrange
        Division::factory()->create([
            'name' => 'Test Division France',
            'country' => 'FR',
            'currency' => 'EUR',
        ]);
        Division::factory()->create([
            'name' => 'Another Test France Division',
            'country' => 'FR',
            'currency' => 'EUR',
        ]);
        Division::factory()->create([
            'name' => 'Test Division USA',
            'country' => 'US',
            'currency' => 'USD',
        ]);

        // Act - filter for 'Division' in name and country FR
        $response = $this->getJson('/api/v1/divisions?name=Division&country=FR');

        // Assert
        $response->assertStatus(200);
        $responseData = $response->json('data');

        // We created 2 FR divisions with 'Division' in name
        $this->assertGreaterThanOrEqual(2, count($responseData));

        // Verify our created divisions are in the results
        $divisionNames = collect($responseData)->pluck('name')->toArray();
        $this->assertContains('Test Division France', $divisionNames);
        $this->assertContains('Another Test France Division', $divisionNames);
    }

    #[Test]
    public function index_endpoint_with_invalid_filter_values(): void
    {
        // Arrange
        Division::factory()->create(['name' => 'Division A']);
        Division::factory()->create(['name' => 'Division B']);

        // Act - Test with non-existent filter value
        $response = $this->getJson('/api/v1/divisions?name=NonExistent');

        // Assert - Should return empty results
        $response->assertStatus(200);
        $this->assertEquals(0, count($response->json('data')));
    }

    #[Test]
    public function division_pipe_class_exist(): void
    {
        $division = new Division;
        $modelName = class_basename($division);
        $pipelineClass = "\\App\\Pipelines\\FilterPipelines\\{$modelName}Pipeline";

        $this->assertTrue(class_exists($pipelineClass), "Pipeline class {$pipelineClass} does not exist");

    }
}
