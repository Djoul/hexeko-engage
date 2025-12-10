<?php

namespace Tests\Feature\Modules\Survey;

use App\Enums\IDP\RoleDefaults;
use App\Http\Middleware\CheckPermissionAttribute;
use App\Http\Middleware\CognitoAuthMiddleware;
use App\Models\Financer;
use App\Models\User;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('middleware')]
#[Group('auth')]
#[Group('feature')]
class CheckAllowedFinancerMiddlewareTest extends ProtectedRouteTestCase
{
    protected bool $checkAuth = true;

    protected bool $checkPermissions = false;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();

        // Disable CognitoAuthMiddleware and CheckPermissionAttribute to avoid having to manage Cognito tokens
        // Keep CheckAllowedFinancerMiddleware enabled to test it
        $this->withoutMiddleware([CognitoAuthMiddleware::class, CheckPermissionAttribute::class]);

        $this->auth = $this->createAuthUser(RoleDefaults::FINANCER_ADMIN);
        $this->financer = Financer::factory()->create();

        // Attach financer to user with active status
        $this->auth->financers()->attach($this->financer->id, ['active' => true]);

        // Initialize contextual access arrays
        Context::add('accessible_financers', [$this->financer->id]);
        Context::add('accessible_divisions', []);
    }

    #[Test]
    public function it_blocks_unauthenticated_users(): void
    {
        // Don't authenticate the user
        $response = $this->getJson(route('survey.surveys.index', ['financer_id' => $this->financer->id]));

        // Should return 401 Unauthorized
        $response->assertStatus(401);
    }

    #[Test]
    public function it_blocks_route_without_financer_id_parameter(): void
    {
        $response = $this->actingAs($this->auth)
            ->getJson(route('survey.surveys.index'));

        $response->assertStatus(400);
        $response->assertJsonFragment(['message' => 'Financer ID is required']);
    }

    #[Test]
    public function it_allows_route_with_valid_financer_id(): void
    {
        $response = $this->actingAs($this->auth)
            ->getJson(route('survey.surveys.index', ['financer_id' => $this->financer->id]));

        $response->assertStatus(200);
    }

    #[Test]
    public function it_blocks_route_with_invalid_financer_id(): void
    {
        $otherFinancer = Financer::factory()->create();

        // Don't attach the other financer to user

        $response = $this->actingAs($this->auth)
            ->getJson(route('survey.surveys.index', ['financer_id' => $otherFinancer->id]));

        $response->assertStatus(403);
        $response->assertJsonStructure([
            'error',
            'message',
            'context' => [
                'financer_id',
                'user_id',
            ],
        ]);
        $response->assertJsonFragment(['error' => 'FinancerAccessDeniedException']);
    }

    /*#[Test]
    public function it_allows_user_with_manage_any_financer_permission(): void
    {
        $role = ModelFactory::createRole(['name' => RoleDefaults::HEXEKO_SUPER_ADMIN, 'team_id' => $this->auth->team_id]);
        $permission = ModelFactory::createPermission(['name' => PermissionDefaults::MANAGE_ANY_FINANCER]);
        $role->givePermissionTo($permission);
        $this->auth->assignRole(RoleDefaults::HEXEKO_SUPER_ADMIN);

        $otherFinancer = Financer::factory()->create();

        $response = $this->actingAs($this->auth)
            ->getJson(route('survey.surveys.index', ['financer_id' => $otherFinancer->id]));

        // Should not be blocked by the middleware
        $this->assertEquals(200, $response->getStatusCode());
    }*/

    #[Test]
    public function it_stores_financer_id_in_context_when_allowed(): void
    {
        $response = $this->actingAs($this->auth)
            ->getJson(route('survey.surveys.index', ['financer_id' => $this->financer->id]));

        $response->assertStatus(200);
        // Context should be set (though we can't easily verify this in a feature test)
        // This test demonstrates the integration point
        $this->assertEquals($this->financer->id, Context::get('financer_id'));
    }

    #[Test]
    public function it_blocks_user_without_financer_access(): void
    {
        // Create a new user without financer access
        $userWithoutFinancer = User::factory()->create(['team_id' => $this->auth->team_id]);

        $response = $this->actingAs($userWithoutFinancer)
            ->getJson(route('survey.surveys.index', ['financer_id' => $this->financer->id]));

        $response->assertStatus(403);
    }

    #[Test]
    public function it_allows_user_with_multiple_financer_accesses(): void
    {
        $financer1 = Financer::factory()->create();
        $financer2 = Financer::factory()->create();

        $this->auth->financers()->attach([
            $financer1->id => ['active' => true],
            $financer2->id => ['active' => true],
        ]);

        // First financer
        $response1 = $this->actingAs($this->auth)
            ->getJson(route('survey.surveys.index', ['financer_id' => $financer1->id]));
        $this->assertEquals(200, $response1->getStatusCode());

        // Second financer
        $response2 = $this->actingAs($this->auth)
            ->getJson(route('survey.surveys.index', ['financer_id' => $financer2->id]));
        $this->assertEquals(200, $response2->getStatusCode());
    }

    #[Test]
    public function it_blocks_user_accessing_financer_not_in_their_list(): void
    {
        $financer1 = Financer::factory()->create();
        $financer2 = Financer::factory()->create();

        // Only attach financer1
        $this->auth->financers()->sync([$financer1->id => ['active' => true]]);

        // Try to access financer2
        $response = $this->actingAs($this->auth)
            ->getJson(route('survey.surveys.index', ['financer_id' => $financer2->id]));

        $response->assertStatus(403);
    }

    #[Test]
    public function it_handles_empty_financer_id_parameter(): void
    {
        $response = $this->actingAs($this->auth)
            ->getJson(route('survey.surveys.index', ['financer_id' => '']));

        $response->assertStatus(400);
    }

    #[Test]
    public function it_handles_malformed_financer_id_parameter(): void
    {
        $response = $this->actingAs($this->auth)
            ->getJson(route('survey.surveys.index', ['financer_id' => 'invalid-uuid']));

        $response->assertStatus(400);
    }
}
