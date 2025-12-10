<?php

namespace Tests\Unit\Http\Resources\User;

use App\Http\Resources\User\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('user')]
class UserResourceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    #[Test]
    public function test_user_resource_basic_transformation(): void
    {
        // Create a user
        $user = User::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'enabled' => true,
        ]);

        // Create request
        $request = Request::create('/api/v1/users');

        // Transform user to resource
        $resource = new UserResource($user);
        $transformed = $resource->toArray($request);

        // Assert basic fields are present
        $this->assertEquals($user->id, $transformed['id']);
        $this->assertEquals($user->email, $transformed['email']);
        $this->assertEquals($user->first_name, $transformed['first_name']);
        $this->assertEquals($user->last_name, $transformed['last_name']);
        $this->assertEquals($user->enabled, $transformed['enabled']);

        // Credit balance should not be included for non-me routes
        $this->assertArrayNotHasKey('credit_balance', $transformed);
    }
}
