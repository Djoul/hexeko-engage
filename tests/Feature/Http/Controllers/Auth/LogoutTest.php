<?php

namespace Tests\Feature\Http\Controllers\Auth;

use App\Actions\Auth\LogoutUserAction;
use App\Models\User;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('auth')]
#[Group('logout')]

class LogoutTest extends ProtectedRouteTestCase
{
    const LOGOUT_URI = '/api/v1/logout';

    protected $logoutUserAction;

    protected User $authUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authUser = User::factory()->create();

        $this->logoutUserAction = Mockery::mock(LogoutUserAction::class);
        $this->app->instance(LogoutUserAction::class, $this->logoutUserAction);

    }

    #[Test]
    public function it_logs_out_a_user_with_a_valid_token(): void
    {

        $this->logoutUserAction
            ->shouldReceive('handle')
            ->once()
            ->andReturn(['message' => 'Logout successful.']);

        $accessToken = 'valid_test_access_token';

        $response = $this->withHeaders([
            'Authorization' => "Bearer $accessToken",
        ])->postJson(self::LOGOUT_URI);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Logout successful.']);

    }

    #[Test]
    public function it_returns_error_if_no_token_is_provided(): void
    {
        $response = $this->postJson(self::LOGOUT_URI);

        $response->assertStatus(401)
            ->assertJson(['error' => 'Access token is required.']);
    }
}
