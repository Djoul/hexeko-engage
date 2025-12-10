<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Wellbeing\WellWo\Http\V1;

use App\Enums\Languages;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('wellwo')]
class WellWoLanguageFormatTest extends ProtectedRouteTestCase
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
    public function it_accepts_languages_enum_format_for_french(): void
    {
        $this->auth = $this->createAuthUser();

        $wellWoResponse = json_encode([
            'status' => 'OK',
            '0' => [
                'id' => 'test-program',
                'name' => 'Programme en français',
                'image' => 'https://example.com/image.jpg',
            ],
        ]);

        Http::fake([
            'https://my.wellwo.net/api/v1/*' => Http::response($wellWoResponse, 200),
        ]);

        // Use Languages enum format
        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/wellbeing/wellwo/programs?lang='.Languages::FRENCH);

        $response->assertOk();

        // Verify the request was made with 'fr' (mapped from fr-FR)
        Http::assertSent(function ($request): bool {
            $raw = $request->body();
            $body = json_decode($raw, true);
            if (! is_array($body)) {
                parse_str(is_string($raw) ? $raw : '', $body);
            }

            return is_array($body) && isset($body['lang']) && $body['lang'] === 'fr';
        });
    }

    #[Test]
    public function it_accepts_wellwo_format_directly(): void
    {
        $this->auth = $this->createAuthUser();

        $wellWoResponse = json_encode([
            'status' => 'OK',
            '0' => [
                'id' => 'test-program',
                'name' => 'Programme en français',
                'image' => 'https://example.com/image.jpg',
            ],
        ]);

        Http::fake([
            'https://my.wellwo.net/api/v1/*' => Http::response($wellWoResponse, 200),
        ]);

        // Use WellWo format directly
        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/wellbeing/wellwo/programs?lang=fr');

        $response->assertOk();

        // Verify the request was made with 'fr' directly
        Http::assertSent(function ($request): bool {
            $raw = $request->body();
            $body = json_decode($raw, true);
            if (! is_array($body)) {
                parse_str(is_string($raw) ? $raw : '', $body);
            }

            return is_array($body) && isset($body['lang']) && $body['lang'] === 'fr';
        });
    }

    #[Test]
    public function it_maps_spanish_mexico_correctly(): void
    {
        $this->auth = $this->createAuthUser();

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

        // Use Languages enum format for Mexican Spanish
        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/wellbeing/wellwo/programs?lang='.Languages::SPANISH_MEXICO);

        $response->assertOk();

        // Verify es-MX is mapped to 'mx'
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

            return is_array($body) && isset($body['lang']) && $body['lang'] === 'mx';
        });
    }

    #[Test]
    public function it_rejects_invalid_language_format(): void
    {
        $this->auth = $this->createAuthUser();

        // Test with invalid language code
        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/wellbeing/wellwo/programs?lang=invalid-code');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['lang']);
    }

    #[Test]
    public function it_accepts_all_languages_enum_values_that_have_mapping(): void
    {
        $this->auth = $this->createAuthUser();

        $wellWoResponse = json_encode([
            'status' => 'OK',
            '0' => ['id' => 'test', 'name' => 'Test'],
        ]);

        // Test a few key mapped Languages enum values
        $testCases = [
            ['enum' => Languages::SPANISH, 'expected' => 'es'],
            ['enum' => Languages::SPANISH_MEXICO, 'expected' => 'mx'],
            ['enum' => Languages::FRENCH, 'expected' => 'fr'],
            ['enum' => Languages::ITALIAN, 'expected' => 'it'],
            ['enum' => Languages::PORTUGUESE, 'expected' => 'pt'],
        ];

        foreach ($testCases as $testCase) {
            Http::fake([
                'https://my.wellwo.net/api/v1/*' => Http::response($wellWoResponse, 200),
            ]);

            $response = $this->actingAs($this->auth)
                ->getJson("/api/v1/wellbeing/wellwo/programs?lang={$testCase['enum']}");

            $response->assertOk();

            // Verify the correct WellWo code was used
            Http::assertSent(function ($request) use ($testCase): bool {
                $raw = $request->body();
                $body = json_decode($raw, true);
                if (! is_array($body)) {
                    parse_str(is_string($raw) ? $raw : '', $body);
                }

                return is_array($body) && isset($body['lang']) && $body['lang'] === $testCase['expected'];
            });

            // Clear for next iteration
            Http::clearResolvedInstances();
        }
    }

    #[Test]
    public function it_rejects_unmapped_languages_enum_values(): void
    {
        $this->auth = $this->createAuthUser();

        // Test with Languages enum values that don't have WellWo mapping
        $unmappedLanguages = [
            Languages::GERMAN,
            Languages::DUTCH,
            Languages::POLISH,
            Languages::ROMANIAN,
            Languages::RUSSIAN,
            Languages::UKRAINIAN,
            Languages::GERMAN_AUSTRIA,
            Languages::GERMAN_SWITZERLAND,
            Languages::DUTCH_BELGIUM,
        ];

        foreach ($unmappedLanguages as $enumValue) {
            $response = $this->actingAs($this->auth)
                ->getJson("/api/v1/wellbeing/wellwo/programs?lang={$enumValue}");

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['lang']);
        }
    }

    #[Test]
    public function it_works_with_all_endpoints_using_both_formats(): void
    {
        $this->auth = $this->createAuthUser();

        $genericResponse = json_encode([
            'status' => 'OK',
            '0' => ['id' => 'test', 'name' => 'Test'],
            'mediaItems' => [['name' => 'Test Video', 'url' => 'http://test.mp4']],
        ]);

        Http::fake([
            'https://my.wellwo.net/api/v1/*' => Http::response($genericResponse, 200),
        ]);

        $endpoints = [
            '/api/v1/wellbeing/wellwo/programs',
            '/api/v1/wellbeing/wellwo/programs/test-id/videos',
            '/api/v1/wellbeing/wellwo/classes/disciplines',
            '/api/v1/wellbeing/wellwo/classes/test-id/videos',
        ];

        foreach ($endpoints as $endpoint) {
            // Test with Languages enum format
            $response = $this->actingAs($this->auth)
                ->getJson($endpoint.'?lang='.Languages::FRENCH);
            $response->assertOk();

            // Test with WellWo format
            $response = $this->actingAs($this->auth)
                ->getJson($endpoint.'?lang=fr');
            $response->assertOk();
        }
    }
}
