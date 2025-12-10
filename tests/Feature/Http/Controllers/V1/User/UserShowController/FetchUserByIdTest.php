<?php

namespace Tests\Feature\Http\Controllers\V1\User\UserShowController;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('user')]
class FetchUserByIdTest extends ProtectedRouteTestCase
{
    use WithFaker;

    protected $createUserAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->auth = $this->createAuthUser();
    }

    #[Test]
    public function it_can_fetch_a_single_user(): void
    {
        // Get authUser's financer to ensure multi-tenant isolation works
        $authFinancer = $this->auth->financers->first();

        // Create user attached to the same financer as authUser
        $user = ModelFactory::createUser([
            'financers' => [['financer' => $authFinancer, 'active' => true]],
        ]);

        $response = $this->actingAs($this->auth)->get('/api/v1/users/'.$user->id);

        $response->assertStatus(200);
    }

    #[Test]
    public function it_can_fetch_user_with_financers_without_lazy_loading(): void
    {
        // Get authUser's financer to ensure multi-tenant isolation works
        $authFinancer = $this->auth->financers->first();

        // Create user with same financer as authUser
        $user = ModelFactory::createUser(['financers' => [['financer' => $authFinancer, 'active' => true]]]);

        // Enable strict mode to catch lazy loading violations
        Model::preventLazyLoading(true);

        try {
            $response = $this->actingAs($this->auth)->get('/api/v1/users/'.$user->id);

            $response->assertStatus(200);
            $response->assertJsonStructure([
                'data' => [
                    'id',
                    'email',
                    'financers',
                    'role',
                    'permissions',
                ],
            ]);

            // Verify that financers data is actually present
            $responseData = $response->json('data');
            $this->assertNotNull($responseData['financers']);

        } finally {
            // Always reset to prevent affecting other tests
            Model::preventLazyLoading(false);
        }
    }
}
