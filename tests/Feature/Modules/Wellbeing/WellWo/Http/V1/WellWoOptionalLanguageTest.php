<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Wellbeing\WellWo\Http\V1;

use App\Enums\Languages;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('wellwo')]
class WellWoOptionalLanguageTest extends ProtectedRouteTestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear cache
        Cache::flush();

        // Configure WellWo settings
        Config::set('services.wellwo.api_url', 'https://my.wellwo.net/api/v1');
        Config::set('services.wellwo.auth_token', 'test-token');
    }

    #[Test]
    public function it_uses_app_locale_when_no_language_param_provided(): void
    {
        // Set app locale to French
        App::setLocale('fr-FR');

        // Create user with French locale
        $this->auth = ModelFactory::createUser([
            'email' => 'test@example.com',
            'locale' => Languages::FRENCH,
        ]);

        $wellWoResponse = json_encode([
            'status' => 'OK',
            '0' => [
                'id' => 'test-program',
                'name' => 'Programme en français',
                'image' => 'https://example.com/image.jpg',
            ],
        ]);

        // Expect the API to be called with 'fr' (mapped from fr-FR)
        Http::fake([
            'https://my.wellwo.net/api/v1/*' => Http::sequence()
                ->push($wellWoResponse, 200),
        ]);

        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/wellbeing/wellwo/programs'); // No lang param

        $response->assertOk();

        // Verify the request was made with the correct language
        Http::assertSent(function ($request): bool {
            $body = json_decode($request->body(), true);

            return isset($body['lang']) && $body['lang'] === 'fr';
        });
    }

    #[Test]
    public function it_uses_provided_language_param_over_app_locale(): void
    {
        // Set app locale to French
        App::setLocale('fr-FR');

        $this->auth = ModelFactory::createUser([
            'email' => 'test@example.com',
            'locale' => Languages::FRENCH,
        ]);

        $wellWoResponse = json_encode([
            'status' => 'OK',
            '0' => [
                'id' => 'test-program',
                'name' => 'Program in English',
                'image' => 'https://example.com/image.jpg',
            ],
        ]);

        Http::fake([
            'https://my.wellwo.net/api/v1/*' => Http::response($wellWoResponse, 200),
        ]);

        // Explicitly request English content
        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/wellbeing/wellwo/programs?lang=en');

        $response->assertOk();

        // Verify the request was made with English despite French locale
        Http::assertSent(function ($request): bool {
            $raw = $request->body();

            // Handle different request body formats
            if (is_string($raw)) {
                // Try JSON decode first
                $body = json_decode($raw, true);

                // If not JSON, try form-encoded
                if (! is_array($body)) {
                    parse_str($raw, $body);
                }
            } else {
                $body = $raw;
            }

            return isset($body['lang']) && $body['lang'] === 'en';
        });
    }

    #[Test]
    public function it_maps_user_locale_to_wellwo_supported_language(): void
    {
        // Create user with Mexican Spanish locale
        $this->auth = ModelFactory::createUser([
            'email' => 'test@example.com',
            'locale' => Languages::SPANISH_MEXICO,
        ]);

        App::setLocale(Languages::SPANISH_MEXICO);

        $wellWoResponse = json_encode([
            'status' => 'OK',
            '0' => [
                'id' => 'test-program',
                'name' => 'Programa en español mexicano',
                'image' => 'https://example.com/image.jpg',
            ],
        ]);

        Http::fake([
            'https://my.wellwo.net/api/v1/*' => Http::response($wellWoResponse, 200),
        ]);

        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/wellbeing/wellwo/programs');

        $response->assertOk();

        // Verify Mexican Spanish is mapped to 'mx'
        Http::assertSent(function ($request): bool {
            $body = json_decode($request->body(), true);

            return isset($body['lang']) && $body['lang'] === 'mx';
        });
    }

    #[Test]
    public function it_defaults_unsupported_locale_to_english(): void
    {
        // Create user with German locale (not supported by WellWo)
        $this->auth = ModelFactory::createUser([
            'email' => 'test@example.com',
            'locale' => Languages::GERMAN,
        ]);

        App::setLocale(Languages::GERMAN);

        $wellWoResponse = json_encode([
            'status' => 'OK',
            '0' => [
                'id' => 'test-program',
                'name' => 'Default program',
                'image' => 'https://example.com/image.jpg',
            ],
        ]);

        Http::fake([
            'https://my.wellwo.net/api/v1/*' => Http::response($wellWoResponse, 200),
        ]);

        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/wellbeing/wellwo/programs');

        $response->assertOk();

        // Verify German defaults to English
        Http::assertSent(function ($request): bool {
            $body = json_decode($request->body(), true);

            return isset($body['lang']) && $body['lang'] === 'en';
        });
    }

    #[Test]
    public function it_works_with_all_endpoints_without_language_param(): void
    {
        App::setLocale(Languages::ITALIAN);

        $this->auth = ModelFactory::createUser([
            'email' => 'test@example.com',
            'locale' => Languages::ITALIAN,
        ]);

        $programsResponse = json_encode([
            'status' => 'OK',
            '0' => ['id' => 'prog1', 'name' => 'Programma 1'],
        ]);

        $classesResponse = json_encode([
            'status' => 'OK',
            '0' => ['id' => 'class1', 'name' => 'Classe 1'],
        ]);

        $videosResponse = json_encode([
            'status' => 'OK',
            '0' => ['id' => 'video1', 'name' => 'Video 1', 'url' => 'http://test.mp4'],
        ]);

        $classVideosResponse = json_encode([
            'status' => 'OK',
            'mediaItems' => [
                ['name' => 'Class Video 1', 'url' => 'http://test.mp4'],
            ],
        ]);

        Http::fake([
            'https://my.wellwo.net/api/v1/*' => Http::sequence()
                ->push($programsResponse, 200)  // First request: programs
                ->push($classesResponse, 200)   // Second request: classes
                ->push($videosResponse, 200)    // Third request: program videos
                ->push($classVideosResponse, 200), // Fourth request: class videos
        ]);

        // Test programs endpoint
        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/wellbeing/wellwo/programs');
        $response->assertOk();

        // Test classes endpoint
        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/wellbeing/wellwo/classes/disciplines');
        $response->assertOk();

        // Test program videos endpoint
        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/wellbeing/wellwo/programs/prog1/videos');
        $response->assertOk();

        // Test class videos endpoint
        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/wellbeing/wellwo/classes/class1/videos');
        $response->assertOk();

        // Verify all requests used Italian
        Http::assertSentCount(4);
    }

    #[Test]
    public function it_validates_provided_language_parameter(): void
    {
        $this->auth = $this->createAuthUser();

        // Test with invalid language
        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/wellbeing/wellwo/programs?lang=invalid');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['lang']);
    }

    #[Test]
    public function it_accepts_all_supported_wellwo_languages(): void
    {
        $this->auth = $this->createAuthUser();

        $wellWoResponse = json_encode([
            'status' => 'OK',
            '0' => ['id' => 'test', 'name' => 'Test'],
        ]);

        Http::fake([
            'https://my.wellwo.net/api/v1/*' => Http::response($wellWoResponse, 200),
        ]);

        $supportedLanguages = ['es', 'en', 'fr', 'it', 'pt', 'ca', 'mx'];

        foreach ($supportedLanguages as $lang) {
            $response = $this->actingAs($this->auth)
                ->getJson("/api/v1/wellbeing/wellwo/programs?lang={$lang}");

            $response->assertOk();
        }
    }
}
