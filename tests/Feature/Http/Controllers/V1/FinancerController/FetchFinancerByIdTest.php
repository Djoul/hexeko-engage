<?php

namespace Tests\Feature\Http\Controllers\V1\FinancerController;

use App\Enums\IDP\PermissionDefaults;
use App\Models\Permission;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('financer')]
class FetchFinancerByIdTest extends ProtectedRouteTestCase
{
    use WithFaker;

    protected $createFinancerAction;

    protected function setUp(): void
    {
        parent::setUp();
    }

    #[Test]
    public function it_can_fetch_a_single_financer(): void
    {
        $user = $this->createAuthUser(returnDetails: true);
        $financer = $this->currentFinancer;
        $financer->update(['name' => 'Financer Test']);

        // Give user permission to read own financer
        $readOwnFinancerPermission = Permission::where('name', PermissionDefaults::READ_OWN_FINANCER)->first();
        $user->givePermissionTo($readOwnFinancerPermission);

        $response = $this->actingAs($user)->get('/api/v1/financers/'.$financer->id);

        $response->assertStatus(200);
    }
}
