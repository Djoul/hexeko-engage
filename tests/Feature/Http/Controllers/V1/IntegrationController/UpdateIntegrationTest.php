<?php

namespace Tests\Feature\Http\Controllers\V1\IntegrationController;

use App\Models\Integration;
use DB;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('integration')]
class UpdateIntegrationTest extends ProtectedRouteTestCase
{
    use WithFaker;

    const URI = '/api/v1/integrations/';

    #[Test]
    public function it_can_update_integration(): void
    {
        DB::table('integrations')->delete();
        $integration = Integration::factory()
            ->create(['name' => 'Integration Test']);

        $updatedData = [
            // data
            ...$integration->toArray(),
            'name' => 'Integration Test Updated',
        ];

        $this->assertDatabaseCount('integrations', 1);
        $response = $this->put(self::URI."{$integration->id}", $updatedData);

        $response->assertStatus(200);

        $this->assertDatabaseCount('integrations', 1);
        $this->assertDatabaseHas('integrations', ['id' => $integration['id'], 'name' => $updatedData['name']]);

    }
}
