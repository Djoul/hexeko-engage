<?php

namespace Tests\Feature\Http\Controllers\V1\IntegrationController;

use App\Models\Integration;
use DB;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('integration')]
class CreateIntegrationTest extends ProtectedRouteTestCase
{
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('integrations')->delete();
    }

    #[Test]
    public function it_can_create_integration(): void
    {

        $this->assertDatabaseCount('integrations', 0);

        $integrationData = Integration::factory()->make(['name' => 'Integration Test'])->toArray();

        $response = $this->postJson('/api/v1/integrations', $integrationData);

        $response->assertStatus(201);

        $this->assertDatabaseCount('integrations', 1);

        $this->assertDatabaseHas('integrations', ['name' => $integrationData['name']]);
    }
}
