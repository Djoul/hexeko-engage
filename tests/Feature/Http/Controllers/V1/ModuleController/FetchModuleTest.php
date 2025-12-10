<?php

namespace Tests\Feature\Http\Controllers\V1\ModuleController;

use App\Enums\IDP\RoleDefaults;
use App\Models\Module;
use DB;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('module')]

class FetchModuleTest extends ProtectedRouteTestCase
{
    use WithFaker;

    const URI = '/api/v1/modules';

    protected function setUp(): void
    {
        parent::setUp();

        // Create authenticated user with context
        $this->auth = $this->createAuthUser(
            role: RoleDefaults::HEXEKO_SUPER_ADMIN,
            withContext: true
        );

        DB::table('modules')->delete();
    }

    #[Test]
    public function it_can_fetch_all_module(): void
    {
        $initialCount = Module::count();
        $createdCount = 10;

        Module::factory()->count($createdCount)->create();

        $response = $this->actingAs($this->auth)->get(self::URI);

        $response->assertStatus(200);

        $this->assertDatabaseCount('modules', $initialCount + $createdCount);
    }
}
