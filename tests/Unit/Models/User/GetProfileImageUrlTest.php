<?php

namespace Tests\Unit\Models\User;

use App\Models\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

#[Group('user')]
#[Group('media')]
class GetProfileImageUrlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    #[Test]
    public function it_returns_null_when_user_has_no_profile_image(): void
    {
        $user = User::factory()->make();

        $result = $user->getProfileImageUrl();

        $this->assertNull($result);
    }

    #[Test]
    public function it_returns_properly_formatted_url_when_user_has_profile_image(): void
    {
        // Create a mock media object
        $media = $this->createMock(Media::class);
        $media->method('getUrl')->willReturn('https://example-cdn.com/media/1/profile.jpg');

        // Mock the user methods
        $user = $this->getMockBuilder(User::class)
            ->onlyMethods(['hasMedia', 'getFirstMedia'])
            ->setConstructorArgs([])
            ->getMock();

        $user->method('hasMedia')->with('profile_image')->willReturn(true);
        $user->method('getFirstMedia')->with('profile_image')->willReturn($media);

        $result = $user->getProfileImageUrl();

        $this->assertNotNull($result);
        $this->assertEquals('https://example-cdn.com/media/1/profile.jpg', $result);
        $this->assertNotFalse(filter_var($result, FILTER_VALIDATE_URL));
    }

    #[Test]
    public function it_handles_media_with_full_url(): void
    {
        // Create a mock media object with a full URL
        $media = $this->createMock(Media::class);
        $media->method('getUrl')->willReturn('https://example.com/storage/media/profile.jpg');

        // Mock the user methods
        $user = $this->getMockBuilder(User::class)
            ->onlyMethods(['hasMedia', 'getFirstMedia'])
            ->setConstructorArgs([])
            ->getMock();

        $user->method('hasMedia')->with('profile_image')->willReturn(true);
        $user->method('getFirstMedia')->with('profile_image')->willReturn($media);

        $result = $user->getProfileImageUrl();

        $this->assertEquals('https://example.com/storage/media/profile.jpg', $result);
    }

    #[Test]
    public function it_properly_concatenates_app_url_with_relative_path(): void
    {
        // Create a mock media object with a relative URL
        $media = $this->createMock(Media::class);
        $media->method('getUrl')->willReturn('/storage/1/profile.jpg');

        // Mock the user methods
        $user = $this->getMockBuilder(User::class)
            ->onlyMethods(['hasMedia', 'getFirstMedia'])
            ->setConstructorArgs([])
            ->getMock();

        $user->method('hasMedia')->with('profile_image')->willReturn(true);
        $user->method('getFirstMedia')->with('profile_image')->willReturn($media);

        $result = $user->getProfileImageUrl();

        $expectedUrl = rtrim(config('app.url'), '/').'/storage/1/profile.jpg';
        $this->assertEquals($expectedUrl, $result);
    }

    #[Test]
    public function it_handles_trailing_slashes_correctly(): void
    {
        // Temporarily set app.url with trailing slash
        config(['app.url' => 'http://localhost:1310/']);

        // Create a mock media object with a relative URL starting with slash
        $media = $this->createMock(Media::class);
        $media->method('getUrl')->willReturn('/storage/1/profile.jpg');

        // Mock the user methods
        $user = $this->getMockBuilder(User::class)
            ->onlyMethods(['hasMedia', 'getFirstMedia'])
            ->setConstructorArgs([])
            ->getMock();

        $user->method('hasMedia')->with('profile_image')->willReturn(true);
        $user->method('getFirstMedia')->with('profile_image')->willReturn($media);

        $result = $user->getProfileImageUrl();

        // Should not have double slashes
        $this->assertEquals('http://localhost:1310/storage/1/profile.jpg', $result);
        $this->assertStringNotContainsString('//', str_replace('http://', '', $result));
    }
}
