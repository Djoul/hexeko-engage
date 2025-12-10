<?php

namespace Tests\Feature\Http\Controllers\V1\User\UserProfileImageController;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\ProtectedRouteTestCase;

#[Group('user')]
class UpdateProfileImageTest extends ProtectedRouteTestCase
{
    #[Test]
    public function an_authenticated_user_can_update_their_profile_image(): void
    {
        // Configure local disk for media library to avoid S3 dependencies in CI
        config(['media-library.disk_name' => 'local']);
        Storage::fake('local');

        $user = User::factory()->create();
        $this->actingAs($user);

        // Create a base64 image string
        $image = UploadedFile::fake()->image('avatar.jpg');
        $base64 = 'data:image/jpeg;base64,'.base64_encode(file_get_contents($image->getPathname()));

        $response = $this->postJson('/api/v1/user/profile-image', [
            'profile_image' => $base64,
        ]);

        $response->assertOk();
        $user->refresh();
        $this->assertTrue($user->hasMedia('profile_image'));
        $this->assertTrue($user->getFirstMedia('profile_image') instanceof Media);
    }

    #[Test]
    public function unauthenticated_users_cannot_update_profile_image(): void
    {
        // Create a base64 image string
        $image = UploadedFile::fake()->image('avatar.jpg');
        $base64 = 'data:image/jpeg;base64,'.base64_encode(file_get_contents($image->getPathname()));
        $response = $this->postJson('/api/v1/user/profile-image', [
            'profile_image' => $base64,
        ]);
        $response->assertUnauthorized();
    }

    #[Test]
    public function image_is_required(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $response = $this->postJson('/api/v1/user/profile-image', []);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('profile_image');
    }
}
