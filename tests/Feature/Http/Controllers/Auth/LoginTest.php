<?php

namespace Tests\Feature\Http\Controllers\Auth;

use App\Actions\Auth\LoginUserAction;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('auth')]
#[Group('login')]

class LoginTest extends TestCase
{
    const LOGIN_URI = '/api/v1/login';

    protected $loginUserAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginUserAction = Mockery::mock(LoginUserAction::class);
        $this->app->instance(LoginUserAction::class, $this->loginUserAction);
    }

    #[Test]
    public function it_logs_in_a_user_successfully(): void
    {
        $this->loginUserAction
            ->shouldReceive('handle')
            ->once()
            ->with(['email' => 'test@example.com', 'password' => 'password'])
            ->andReturn(['AuthenticationResult' => [
                'AccessToken' => 'fake-access-token',
                'ExpiresIn' => 3600,
                'TokenType' => 'Bearer',
                'RefreshToken' => 'fake-refresh-token',
                'IdToken' => 'fake-id-token',
            ],
            ]);

        $response = $this->postJson(self::LOGIN_URI, [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'response' => 'Login successfull',
                'authentication_result' => [
                    'AccessToken' => 'fake-access-token',
                    'ExpiresIn' => 3600,
                    'TokenType' => 'Bearer',
                    'RefreshToken' => 'fake-refresh-token',
                    'IdToken' => 'fake-id-token',
                ],
            ]);
    }

    #[Test]
    public function it_fails_login_with_invalid_credentials(): void
    {
        $this->loginUserAction
            ->shouldReceive('handle')
            ->once()
            ->with(['email' => 'test@example.com', 'password' => 'wrongpassword'])
            ->andReturn(['error' => 'Invalid credentials']);

        $response = $this->postJson(self::LOGIN_URI, [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);
        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Invalid credentials',
                'errors' => [
                    [
                        'Invalid credentials',
                    ],
                ],
            ]);
    }

    #[Test]
    public function it_requires_email_and_password(): void
    {
        $response = $this->postJson(self::LOGIN_URI, []);

        $response->assertStatus(422)
            ->assertJson([
                'errors' => [
                    'email' => [
                        'The email field is required.',
                    ],
                    'password' => [
                        'The password field is required.',
                    ],
                ],
            ]);
    }

    #[Test]
    public function it_handles_challenge_name_from_cognito(): void
    {
        $this->loginUserAction
            ->shouldReceive('handle')
            ->once()
            ->with(['email' => 'test@example.com', 'password' => 'password'])
            ->andReturn([
                'ChallengeName' => 'NEW_PASSWORD_REQUIRED',
            ]);

        $response = $this->postJson(self::LOGIN_URI, [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJson(['response' => 'NEW_PASSWORD_REQUIRED']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
