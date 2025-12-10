<?php

namespace Tests\Unit\Http\Resources;

use App\Enums\IDP\RoleDefaults;
use App\Http\Resources\User\UserResource;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('user')]
class UserResourceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
    }

    #[Test]
    public function it_transforms_basic_user_data_correctly(): void
    {
        $id = Uuid::uuid4()->toString();

        $user = ModelFactory::createUser([
            'id' => $id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'description' => 'Test description',
            'birthdate' => '1990-01-01',
            'locale' => 'en',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'phone' => '1234567890',
            'enabled' => true,
            'gender' => 'male',
        ]);

        // Find or create role and assign to user
        $role = Role::where('name', RoleDefaults::FINANCER_SUPER_ADMIN)->first()
            ?? ModelFactory::createRole(['name' => RoleDefaults::FINANCER_SUPER_ADMIN]);

        // Set the permissions team ID to match the user's team
        setPermissionsTeamId($user->team_id);
        $user->assignRole($role);

        // Ensure roles are properly loaded before creating the resource
        $user->load('roles', 'financers');

        // Create the resource
        $resource = new UserResource($user);

        // Convert to array
        $array = $resource->toArray(new Request);

        // Assert basic user data is correctly transformed
        $this->assertEquals($id, $array['id']);
        $this->assertEquals($user->email, $array['email']);
        $this->assertEquals('John', $array['first_name']);
        $this->assertEquals('Doe', $array['last_name']);
        $this->assertEquals('male', $array['gender']);
        $this->assertEquals('Test description', $array['description']);
        $this->assertEquals(Carbon::create('1990', '01', '01'), $array['birthdate']);
        $this->assertEquals('en', $array['locale']);
        $this->assertEquals('USD', $array['currency']);
        $this->assertEquals('UTC', $array['timezone']);
        $this->assertEquals('1234567890', $array['phone']);
        $this->assertEquals(true, $array['enabled']);
        $this->assertIsArray($array['financers']);
        $this->assertCount(0, $array['financers']);
        //        $this->assertEquals([RoleDefaults::FINANCER_SUPER_ADMIN], $array['roles']);
    }

    #[Test]
    public function it_includes_additional_data_for_me_route(): void
    {
        // Create a real User instance
        $userId = Uuid::uuid4()->toString();
        $user = ModelFactory::createUser([
            'id' => $userId,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'enabled' => true,
        ]);

        // Find or create role and assign to user
        $role = Role::where('name', RoleDefaults::FINANCER_SUPER_ADMIN)->first()
            ?? ModelFactory::createRole(['name' => RoleDefaults::FINANCER_SUPER_ADMIN]);

        // Set the permissions team ID to match the user's team
        setPermissionsTeamId($user->team_id);
        $user->assignRole($role);

        // Create a request with a route named 'me'
        $request = Request::create('/api/me', 'GET');
        $request->setRouteResolver(function (): Route {
            return new Route('GET', '/api/me', ['as' => 'me']);
        });

        // Create the resource
        $resource = new UserResource($user);

        // Convert to array
        $array = $resource->toArray($request);

        // Assert basic user data structure
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('email', $array);
        $this->assertArrayHasKey('first_name', $array);
        $this->assertArrayHasKey('last_name', $array);
        $this->assertArrayHasKey('description', $array);
        $this->assertArrayHasKey('birthdate', $array);
        $this->assertArrayHasKey('locale', $array);
        $this->assertArrayHasKey('currency', $array);
        $this->assertArrayHasKey('timezone', $array);
        $this->assertArrayHasKey('phone', $array);
        $this->assertArrayHasKey('enabled', $array);
        $this->assertArrayHasKey('profile_image', $array);
        $this->assertArrayHasKey('description', $array);
        $this->assertArrayHasKey('sirh_id', $array);
        $this->assertArrayHasKey('financers', $array);
        $this->assertArrayHasKey('role', $array); // Single role instead of array
        $this->assertArrayHasKey('permissions', $array);
        $this->assertArrayHasKey('created_at', $array);
        $this->assertArrayHasKey('updated_at', $array);
        $this->assertArrayHasKey('gender', $array);
    }

    #[Test]
    public function it_sets_status_code_201_for_post_requests(): void
    {
        // Create a mock User
        $user = $this->createPartialMock(User::class, ['getRoleNames', 'financers', 'roles', 'getFirstMediaUrl']);

        // Set up the mock properties and methods
        $user->id = 'test-id';
        $user->email = 'test@example.com';

        // Create a POST request
        $request = Request::create('/api/users', 'POST');

        // Create the resource
        $resource = new UserResource($user);

        // Create a mock response
        $response = new Response;

        // Call withResponse method
        $resource->withResponse($request, $response);

        // Assert status code is set to 201
        $this->assertEquals(201, $response->getStatusCode());
    }

    #[Test]
    public function it_does_not_change_status_code_for_non_post_requests(): void
    {
        // Create a mock User
        $user = $this->createPartialMock(User::class, ['getRoleNames', 'financers', 'roles', 'getFirstMediaUrl']);

        // Set up the mock properties and methods
        $user->id = 'test-id';
        $user->email = 'test@example.com';

        // Create a GET request
        $request = Request::create('/api/users', 'GET');

        // Create the resource
        $resource = new UserResource($user);

        // Create a mock response with default status code 200
        $response = new Response;

        // Call withResponse method
        $resource->withResponse($request, $response);

        // Assert status code remains 200
        $this->assertEquals(200, $response->getStatusCode());
    }
}
