<?php

namespace Tests\Feature\Http\Controllers\V1\IntegrationController;

use App\Models\Integration;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('integration')]
class FetchIntegrationByIdTest extends ProtectedRouteTestCase
{
    use WithFaker;

    protected $createIntegrationAction;

    protected function setUp(): void
    {
        parent::setUp();
    }

    #[Test]
    public function it_can_fetch_a_single_integration(): void
    {
        $integration = Integration::factory()->create(['name' => 'Integration Test']);

        $response = $this->get('/api/v1/integrations/'.$integration->id);

        $response->assertStatus(200);
    }
}
