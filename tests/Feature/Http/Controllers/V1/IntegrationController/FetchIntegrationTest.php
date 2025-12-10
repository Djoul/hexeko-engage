<?php

namespace Tests\Feature\Http\Controllers\V1\IntegrationController;

use App\Models\Integration;
use DB;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('integration')]
class FetchIntegrationTest extends ProtectedRouteTestCase
{
    use WithFaker;

    const URI = '/api/v1/integrations';

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('integrations')->delete();
    }

    #[Test]
    public function it_can_fetch_all_integration(): void
    {
        Integration::factory()->count(10)->create();

        $response = $this->get(self::URI);

        $response->assertStatus(200);

        $this->assertDatabaseCount('integrations', 10);

    }
}
