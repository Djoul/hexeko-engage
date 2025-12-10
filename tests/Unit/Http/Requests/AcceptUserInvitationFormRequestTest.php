<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\AcceptUserInvitationFormRequest;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * TDD Tests for AcceptUserInvitationFormRequest.
 * Sprint 3 - API Integration: Validates invitation acceptance request data.
 */
#[Group('user')]
#[Group('invited-user')]
#[Group('form-requests')]
class AcceptUserInvitationFormRequestTest extends TestCase
{
    use DatabaseTransactions;

    private AcceptUserInvitationFormRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new AcceptUserInvitationFormRequest;
    }

    #[Test]
    public function it_passes_validation_with_valid_data(): void
    {
        $data = [
            'token' => bin2hex(random_bytes(22)),
            'cognito_id' => 'cognito-user-123',
        ];

        $validator = Validator::make($data, $this->request->rules());

        $this->assertFalse($validator->fails());
    }

    #[Test]
    public function it_requires_token_field(): void
    {
        $data = [
            'cognito_id' => 'cognito-user-123',
        ];

        $validator = Validator::make($data, $this->request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('token', $validator->errors()->messages());
    }

    #[Test]
    public function it_requires_cognito_id_field(): void
    {
        $data = [
            'token' => bin2hex(random_bytes(22)),
        ];

        $validator = Validator::make($data, $this->request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('cognito_id', $validator->errors()->messages());
    }

    #[Test]
    public function it_validates_token_is_string(): void
    {
        $data = [
            'token' => 12345,
            'cognito_id' => 'cognito-user-123',
        ];

        $validator = Validator::make($data, $this->request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('token', $validator->errors()->messages());
    }

    #[Test]
    public function it_validates_token_minimum_length(): void
    {
        $data = [
            'token' => 'short',
            'cognito_id' => 'cognito-user-123',
        ];

        $validator = Validator::make($data, $this->request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('token', $validator->errors()->messages());
    }

    #[Test]
    public function it_validates_cognito_id_is_string(): void
    {
        $data = [
            'token' => bin2hex(random_bytes(22)),
            'cognito_id' => 12345,
        ];

        $validator = Validator::make($data, $this->request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('cognito_id', $validator->errors()->messages());
    }

    #[Test]
    public function it_allows_optional_password(): void
    {
        $data = [
            'token' => bin2hex(random_bytes(22)),
            'cognito_id' => 'cognito-user-123',
            'password' => 'SecurePassword123!',
        ];

        $validator = Validator::make($data, $this->request->rules());

        $this->assertFalse($validator->fails());
    }

    #[Test]
    public function it_validates_password_minimum_length(): void
    {
        $data = [
            'token' => bin2hex(random_bytes(22)),
            'cognito_id' => 'cognito-user-123',
            'password' => 'short',
        ];

        $validator = Validator::make($data, $this->request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('password', $validator->errors()->messages());
    }

    #[Test]
    public function it_authorizes_all_requests(): void
    {
        $this->assertTrue($this->request->authorize());
    }
}
