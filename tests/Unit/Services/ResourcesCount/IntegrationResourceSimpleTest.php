<?php

namespace Tests\Unit\Services\ResourcesCount;

use App\Http\Resources\Integration\IntegrationResource;
use App\Models\Integration;
use App\Services\Integration\ResourceCountService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('integration')]
class IntegrationResourceSimpleTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_service_executes_count_queries_correctly(): void
    {
        // Create a financer
        $division = ModelFactory::createDivision();
        ModelFactory::createFinancer([
            'division_id' => $division->id,
            'name' => 'Test Financer',
        ]);

        // Create integration with query
        $integration = Integration::factory()->create([
            'name' => 'Test Integration',
            'resources_count_query' => 'SELECT 42 as count',
        ]);

        // Test the service directly
        $service = new ResourceCountService;
        $count = $service->executeCountQuery($integration->resources_count_query, []);

        $this->assertEquals(42, $count);
    }

    #[Test]
    public function it_service_handles_parameterized_queries(): void
    {
        // Create a financer
        $division = ModelFactory::createDivision();
        $financer = ModelFactory::createFinancer([
            'division_id' => $division->id,
            'name' => 'Test Financer',
        ]);

        // Create integration with parameterized query
        $integration = Integration::factory()->create([
            'name' => 'Test Integration',
            'resources_count_query' => '
                SELECT CASE 
                    WHEN :financer_id = :financer_id THEN 100
                    ELSE 0
                END as count
            ',
        ]);

        // Test the service with parameters
        $service = new ResourceCountService;
        $count = $service->executeCountQuery(
            $integration->resources_count_query,
            ['financer_id' => $financer->id]
        );

        $this->assertEquals(100, $count);
    }

    #[Test]
    public function it_service_handles_null_queries(): void
    {
        $service = new ResourceCountService;
        $count = $service->executeCountQuery(null, []);

        $this->assertEquals(0, $count);
    }

    #[Test]
    public function it_service_handles_invalid_queries(): void
    {
        $service = new ResourceCountService;
        $count = $service->executeCountQuery('INVALID SQL', []);

        $this->assertEquals(0, $count);
    }

    #[Test]
    public function it_returns_resources_count_unit_with_mobile_prefix(): void
    {
        $integration = Integration::factory()->create([
            'name' => 'Test Integration',
            'resources_count_unit' => 'count_raw_unit.comm_rh',
        ]);

        $request = Request::create('/test', 'GET');
        $request->headers->set('x-origin-interface', 'mobile');
        app()->instance('request', $request);

        $resource = new IntegrationResource($integration);
        $array = $resource->toArray($request);

        $this->assertArrayHasKey('resources_count_unit', $array);
        $this->assertEquals('mobile.count_raw_unit.comm_rh', $array['resources_count_unit']);
    }

    #[Test]
    public function it_returns_resources_count_unit_with_web_prefix(): void
    {
        $integration = Integration::factory()->create([
            'name' => 'Test Integration',
            'resources_count_unit' => 'count_raw_unit.comm_rh',
        ]);

        $request = Request::create('/test', 'GET');
        $request->headers->set('x-origin-interface', 'web');
        app()->instance('request', $request);

        $resource = new IntegrationResource($integration);
        $array = $resource->toArray($request);

        $this->assertArrayHasKey('resources_count_unit', $array);
        $this->assertEquals('web_beneficiary.count_raw_unit.comm_rh', $array['resources_count_unit']);
    }

    #[Test]
    public function it_returns_interface_prefix_when_resources_count_unit_is_null(): void
    {
        $integration = Integration::factory()->create([
            'name' => 'Test Integration',
            'resources_count_unit' => null,
        ]);

        $request = Request::create('/test', 'GET');
        $request->headers->set('x-origin-interface', 'mobile');
        app()->instance('request', $request);

        $resource = new IntegrationResource($integration);
        $array = $resource->toArray($request);

        $this->assertArrayHasKey('resources_count_unit', $array);
        $this->assertEquals('mobile.resources_count_unit', $array['resources_count_unit']);
    }

    #[Test]
    public function it_stores_translation_key_in_resources_count_unit(): void
    {
        $translationKey = 'Mobile.count_raw_unit.another_key';

        $integration = Integration::factory()->create([
            'name' => 'Test Integration',
            'resources_count_unit' => $translationKey,
        ]);

        $this->assertDatabaseHas('integrations', [
            'id' => $integration->id,
            'resources_count_unit' => $translationKey,
        ]);

        $integration->refresh();
        $this->assertEquals($translationKey, $integration->resources_count_unit);
    }
}
