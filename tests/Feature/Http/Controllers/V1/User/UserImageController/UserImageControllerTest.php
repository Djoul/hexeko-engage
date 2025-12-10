<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\V1\User\UserImageController;

use App\Enums\IDP\RoleDefaults;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('user')]
#[Group('media')]
final class UserImageControllerTest extends ProtectedRouteTestCase
{
    use DatabaseTransactions;

    private const URI = '/api/v1/users/images';

    protected function setUp(): void
    {
        parent::setUp();

        // Configure local disk for media library to avoid S3 dependencies in CI
        config(['media-library.disk_name' => 'local']);
        Storage::fake('local');

        // Create authenticated user with READ_USER permission
        $this->auth = $this->createAuthUser(RoleDefaults::GOD, withContext: true, returnDetails: true);

    }

    protected function tearDown(): void
    {
        Context::flush();
        parent::tearDown();
    }

    #[Test]
    public function it_orders_users_with_profile_image_first(): void
    {
        $financer = $this->currentFinancer;

        // Create users without profile image
        $userWithoutImage1 = ModelFactory::createUser([
            'email' => 'noimage1@test.com',
            'financers' => [
                ['financer' => $financer, 'active' => true],
            ],
        ]);

        $userWithoutImage2 = ModelFactory::createUser([
            'email' => 'noimage2@test.com',
            'financers' => [
                ['financer' => $financer, 'active' => true],
            ],
        ]);

        // Create users with profile image
        $userWithImage1 = ModelFactory::createUser([
            'email' => 'withimage1@test.com',
            'financers' => [
                ['financer' => $financer, 'active' => true],
            ],
        ]);

        $image1 = UploadedFile::fake()->image('profile1.jpg');
        $userWithImage1->addMedia($image1)
            ->toMediaCollection('profile_image');

        $userWithImage2 = ModelFactory::createUser([
            'email' => 'withimage2@test.com',
            'financers' => [
                ['financer' => $financer, 'active' => true],
            ],
        ]);

        $image2 = UploadedFile::fake()->image('profile2.jpg');
        $userWithImage2->addMedia($image2)
            ->toMediaCollection('profile_image');

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson(self::URI);

        // Assert
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'profile_image',
                    ],
                ],
            ]);

        $data = $response->json('data');
        $userIds = collect($data)->pluck('id')->toArray();

        // Users with images should appear first
        $indexWithImage1 = array_search($userWithImage1->id, $userIds, true);
        $indexWithImage2 = array_search($userWithImage2->id, $userIds, true);
        $indexWithoutImage1 = array_search($userWithoutImage1->id, $userIds, true);
        $indexWithoutImage2 = array_search($userWithoutImage2->id, $userIds, true);

        $this->assertNotFalse($indexWithImage1, 'User with image 1 should be in results');
        $this->assertNotFalse($indexWithImage2, 'User with image 2 should be in results');
        $this->assertNotFalse($indexWithoutImage1, 'User without image 1 should be in results');
        $this->assertNotFalse($indexWithoutImage2, 'User without image 2 should be in results');

        // Users with images should come before users without images
        $this->assertLessThan($indexWithoutImage1, $indexWithImage1, 'User with image should come before user without image');
        $this->assertLessThan($indexWithoutImage1, $indexWithImage2, 'User with image should come before user without image');
        $this->assertLessThan($indexWithoutImage2, $indexWithImage1, 'User with image should come before user without image');
        $this->assertLessThan($indexWithoutImage2, $indexWithImage2, 'User with image should come before user without image');
    }
}
