<?php

namespace Tests\Feature\Http\Controllers\V1\User\MeController;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('me')]
#[Group('user')]
#[Group('media')]
class MeControllerProfileImageTest extends ProtectedRouteTestCase
{
    const ME_ENDPOINT = '/api/v1/me';

    #[Test]
    public function me_endpoint_returns_null_profile_image_when_user_has_no_image(): void
    {
        // Create a user without profile image
        $user = User::factory()->create();

        // Make request to the me endpoint
        $response = $this->actingAs($user)
            ->getJson(self::ME_ENDPOINT);

        // Assert response
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'profile_image' => null,
                ],
            ]);
    }

    #[Test]
    public function me_endpoint_returns_correct_profile_image_url_when_user_has_image(): void
    {
        // Use local disk for testing instead of s3-local
        Storage::fake('local');

        // Temporarily change the media disk to local for this test
        config(['media-library.disk_name' => 'local']);

        // Create a user
        $user = User::factory()->create();

        // Add a profile image to the user
        $image = UploadedFile::fake()->image('profile.jpg');
        $user->addMedia($image)
            ->toMediaCollection('profile_image');

        // Make request to the me endpoint
        $response = $this->actingAs($user)
            ->getJson(self::ME_ENDPOINT);

        // Assert response
        $response->assertStatus(200);

        $profileImageUrl = $response->json('data.profile_image');

        // Assert that profile_image is not null
        $this->assertNotNull($profileImageUrl);

        // Since we're using local storage in tests, just verify it's a valid URL
        $this->assertNotFalse(filter_var($profileImageUrl, FILTER_VALIDATE_URL), "Profile image URL is not valid: {$profileImageUrl}");

        // Assert basic structure
        $this->assertStringContainsString($user->id, $profileImageUrl);
    }

    #[Test]
    public function me_endpoint_returns_properly_formatted_url_with_media_library_path(): void
    {
        Storage::fake('local');

        // Temporarily change the media disk to local for this test
        config(['media-library.disk_name' => 'local']);

        // Create a user
        $user = User::factory()->create();

        // Add a profile image
        $image = UploadedFile::fake()->image('test-image.jpg');
        $user->addMedia($image)
            ->toMediaCollection('profile_image');

        // Make request to the me endpoint
        $response = $this->actingAs($user)
            ->getJson(self::ME_ENDPOINT);

        $response->assertStatus(200);

        $profileImageUrl = $response->json('data.profile_image');

        // Parse the URL to check its components
        $parsedUrl = parse_url($profileImageUrl);

        // Assert URL has valid scheme and host
        $this->assertArrayHasKey('scheme', $parsedUrl);
        $this->assertArrayHasKey('host', $parsedUrl);
        $this->assertArrayHasKey('path', $parsedUrl);

        // Assert the path doesn't contain concatenation issues
        $pathSegments = explode('/', trim($parsedUrl['path'], '/'));
        foreach ($pathSegments as $segment) {
            // Each segment should not contain "media-library" as part of a longer string
            if (str_contains($segment, 'media-library')) {
                $this->assertEquals('media-library', $segment, "Path segment '{$segment}' contains malformed 'media-library' string");
            }
        }
    }
}
