<?php

namespace Tests\Feature\Http\Controllers\V1\User\UserUpdateController;

use App\Enums\IDP\RoleDefaults;
use App\Models\Financer;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Attributes\FlushTables;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[FlushTables(tables: ['users', 'financers'], scope: 'test')]
#[Group('user')]
class UpdateUserTest extends ProtectedRouteTestCase
{
    public $financer;

    use WithFaker;

    const URI = '/api/v1/users/';

    protected function setUp(): void
    {
        parent::setUp();

        // Configure local disk for media library to avoid S3 dependencies in CI
        config(['media-library.disk_name' => 'local']);
        Storage::fake('local');

        $this->auth = $this->createAuthUser(RoleDefaults::FINANCER_SUPER_ADMIN);
        $this->financer = ModelFactory::createFinancer();
    }

    #[Test]
    public function it_can_update_user(): void
    {
        $initialUserCount = User::count();

        // Get authUser's financer for multi-tenant isolation
        $authFinancer = $this->auth->financers->first();

        // Create user attached to same financer as authUser
        $user = ModelFactory::createUser([
            'first_name' => 'User Test',
            'financers' => [['financer' => $authFinancer, 'active' => true]],
        ]);

        $image = UploadedFile::fake()->image('avatar2.jpg');
        $base64 = 'data:image/jpeg;base64,'.base64_encode(file_get_contents($image->getPathname()));
        $updatedData = [
            ...$user->toArray(),
            'first_name' => 'Updated User Test',
            'description' => 'updated description',
            'profile_image' => $base64,
        ];

        $this->assertDatabaseCount('users', $initialUserCount + 1);

        $response = $this->actingAs($this->auth)->put(self::URI."{$user->id}", $updatedData, ['Accept' => 'application/json']);

        $response->assertStatus(200);

        $this->assertDatabaseCount('users', $initialUserCount + 1);

        $user->refresh();
        $this->assertTrue($user->hasMedia('profile_image'));

        $this->assertDatabaseHas('users', ['id' => $user['id'], 'first_name' => $updatedData['first_name'], 'description' => $updatedData['description']]);

    }

    #[Test]
    public function it_can_add_financers_to_user(): void
    {

        $initialUserCount = User::count();
        $user = ModelFactory::createUser(
            ['financers' => [
                ['financer' => $this->financer], // active by default
            ]]
        );

        $this->assertDatabaseCount('users', $initialUserCount + 1);

        $this->assertDatabaseHas('financer_user', [
            'user_id' => $user->id,
            'financer_id' => $this->financer->id,
            'active' => true,
        ]);

        $dataFinancer['division_id'] = $this->financer->division_id;

        $newFinancer = ModelFactory::createFinancer(data: $dataFinancer);

        $updatedData = [
            ...$user->toArray(),
            'financers' => [
                ['id' => $newFinancer->id, 'pivot' => ['active' => true, 'role' => RoleDefaults::BENEFICIARY]],
            ],
        ];
        $this->assertCount(1, $user->financers);

        $response = $this->putJson("/api/v1/users/{$user->id}", $updatedData);

        $response->assertStatus(200);

        $user->refresh();

        $this->assertCount(2, $user->financers);

        $this->assertContains($newFinancer->id, $user->financers->pluck('id'));

    }

    #[Test]
    public function it_can_disable_a_financer_to_user(): void
    {
        $initialFinancerCount = Financer::count();

        /*This method creates a new user and associates it with two financers.
         The first financer is an existing one (`$this->financer`).
         The second financer is newly created with the same division ID as the first financer.
         The user is then linked to both financers, with financers being active by default in the relationship.*/
        $financer1 = $this->financer;

        $dataFinancer['division_id'] = $this->financer->division_id;

        $financer2 = ModelFactory::createFinancer(data: $dataFinancer);

        $user = ModelFactory::createUser(
            ['financers' => [
                ['financer' => $this->financer], // active by default
                ['financer' => $financer2],
            ]]
        );

        $this->assertCount(2, $user->financers);

        $this->assertCount($initialFinancerCount + 1, Financer::get());

        $this->assertDatabaseHas('financer_user', [
            'user_id' => $user->id,
            'financer_id' => $financer1->id,
            'active' => true,
        ]);

        $this->assertDatabaseHas('financer_user', [
            'user_id' => $user->id,
            'financer_id' => $financer2->id,
            'active' => true,
        ]);
        $updatedData = [
            ...$user->toArray(),
            'financers' => [
                ['id' => $financer1->id, 'pivot' => ['active' => false]], // Disable this financer
                ['id' => $financer2->id, 'pivot' => ['active' => true]],  // Keep this financer active
            ],
        ];

        $this->assertCount(2, $user->financers);

        $response = $this->putJson("/api/v1/users/{$user->id}", $updatedData);

        $response->assertStatus(200);

        $user->refresh();

        // Assert the correct financer was disabled
        $this->assertDatabaseHas('financer_user', [
            'user_id' => $user->id,
            'financer_id' => $financer1->id,
            'active' => false, // This financer should now be inactive
        ]);

        // Assert the other financer is still active
        $this->assertDatabaseHas('financer_user', [
            'user_id' => $user->id,
            'financer_id' => $financer2->id,
            'active' => true,
        ]);

        $this->assertCount(2, $user->financers);
    }

    #[Test]
    public function it_can_update_user_without_lazy_loading_violation(): void
    {
        // Get authUser's financer for multi-tenant isolation
        $authFinancer = $this->auth->financers->first();

        // Create user attached to same financer as authUser
        $user = ModelFactory::createUser(['financers' => [['financer' => $authFinancer, 'active' => true]]]);

        $updatedData = [
            ...$user->toArray(),
            'first_name' => 'Updated Name',
        ];

        // Enable strict mode to catch lazy loading violations
        Model::preventLazyLoading(true);

        try {
            $response = $this->putJson("/api/v1/users/{$user->id}", $updatedData);

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

            // Verify that financers data is present
            $responseData = $response->json('data');
            $this->assertNotNull($responseData['financers']);

        } finally {
            // Always reset to prevent affecting other tests
            Model::preventLazyLoading(false);
        }
    }
}
