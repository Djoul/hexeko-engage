<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Localization;

use App\Enums\Languages;
use App\Models\User;
use App\Services\Localization\LocaleManager;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('localization')]
#[Group('cognito')]
class LocaleManagerTest extends TestCase
{
    use DatabaseTransactions;

    private LocaleManager $localeManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->localeManager = app(LocaleManager::class);
        Cache::flush();
        Context::flush();
    }

    #[Test]
    public function it_determines_locale_from_cognito_custom_reg_language(): void
    {
        // Arrange
        $division = ModelFactory::createDivision();
        $financer = ModelFactory::createFinancer(['division_id' => $division->id]);
        $user = ModelFactory::createUser([
            'locale' => Languages::ENGLISH,
            'email' => 'user@example.com',
            'financers' => [
                ['financer' => $financer, 'active' => true],
            ],
        ]);

        $cognitoData = [
            'email' => 'user@example.com',
            'custom:reg_language' => Languages::PORTUGUESE,
        ];

        // Act
        $locale = $this->localeManager->determineFromCognito($cognitoData, $user->id);

        // Assert - Should prioritize Cognito custom attribute
        $this->assertEquals(Languages::PORTUGUESE, $locale);
    }

    #[Test]
    public function it_falls_back_to_user_db_locale(): void
    {
        // Arrange - User without Cognito custom:reg_language
        $division = ModelFactory::createDivision();
        $financer = ModelFactory::createFinancer(['division_id' => $division->id]);
        $user = ModelFactory::createUser([
            'locale' => Languages::SPANISH,
            'email' => 'user@example.com',
            'financers' => [
                ['financer' => $financer, 'active' => true],
            ],
        ]);

        Context::add('financer_id', $financer->id);

        $cognitoData = [
            'email' => 'user@example.com',
            // No custom:reg_language
        ];

        // Act
        $locale = $this->localeManager->determineFromCognito($cognitoData, $user->id);

        // Assert - Should fallback to User.locale accessor (which uses financer pivot)
        $this->assertEquals(Languages::SPANISH, $locale);
    }

    #[Test]
    public function it_uses_financer_pivot_language_when_available(): void
    {
        // Arrange - User with financer_user.language set
        $division = ModelFactory::createDivision();
        $financer = ModelFactory::createFinancer(['division_id' => $division->id]);
        $user = ModelFactory::createUser([
            'locale' => Languages::ENGLISH,
            'email' => 'user@example.com',
            'financers' => [
                ['financer' => $financer, 'active' => true],
            ],
        ]);

        // Update financer_user.language to different value (like existing tests do)
        DB::table('financer_user')
            ->where('user_id', $user->id)
            ->where('financer_id', $financer->id)
            ->update(['language' => Languages::GERMAN]);

        Context::add('financer_id', $financer->id);

        $cognitoData = [
            'email' => 'user@example.com',
            // No custom:reg_language
        ];

        // Act
        $locale = $this->localeManager->determineFromCognito($cognitoData, $user->id);

        // Assert - Should use financer_user.language via User.locale accessor
        $this->assertEquals(Languages::GERMAN, $locale);
    }

    #[Test]
    public function it_defaults_to_french_when_all_sources_empty(): void
    {
        // Arrange - User with email but minimal data, no Cognito custom attribute
        // Note: User will have DB locale from factory, but we test French override for Cognito
        $user = ModelFactory::createUser([
            'email' => 'orphan@example.com',
        ]);

        // Clear the user's locale to simulate edge case
        $user->update(['locale' => Languages::ENGLISH]); // User has EN
        Cache::flush(); // Ensure no cache

        $cognitoData = [
            'email' => 'orphan@example.com',
            // No custom:reg_language
        ];

        // Act - Should use User.locale (which exists) NOT French
        $localeWithUser = $this->localeManager->determineFromCognito($cognitoData, $user->id);

        // Assert - With user locale, should return that (not FR)
        $this->assertEquals(Languages::ENGLISH, $localeWithUser);

        // Now test actual French fallback when user not found
        // (This tests the edge case where user doesn't exist)
        Cache::flush();
        $localeWithoutUser = $this->localeManager->determineFromCognito(
            ['email' => 'nonexistent@example.com'],
            '00000000-0000-0000-0000-000000000000' // Invalid UUID
        );

        // Assert - Should default to French when user not found
        $this->assertEquals(Languages::FRENCH, $localeWithoutUser);
    }

    #[Test]
    public function it_hashes_identifiers_before_cache(): void
    {
        // Arrange
        $division = ModelFactory::createDivision();
        $financer = ModelFactory::createFinancer(['division_id' => $division->id]);
        $user = ModelFactory::createUser([
            'locale' => Languages::ITALIAN,
            'email' => 'user@example.com',
            'financers' => [
                ['financer' => $financer, 'active' => true],
            ],
        ]);

        $cognitoData = [
            'email' => 'user@example.com',
            'custom:reg_language' => Languages::ITALIAN,
        ];

        // Act
        $locale = $this->localeManager->determineFromCognito($cognitoData, $user->id);

        // Assert - Verify result is correct
        $this->assertEquals(Languages::ITALIAN, $locale);

        // Assert - Cache key should use SHA256 hash, not plain email
        $hashedEmail = hash('sha256', strtolower(trim('user@example.com')));
        $cacheKey = "locale:cognito:{$hashedEmail}";

        $this->assertTrue(Cache::has($cacheKey), 'Cache should use hashed identifier');
        $this->assertEquals(Languages::ITALIAN, Cache::get($cacheKey));
    }

    #[Test]
    public function it_caches_locale_lookups(): void
    {
        // Arrange
        $division = ModelFactory::createDivision();
        $financer = ModelFactory::createFinancer(['division_id' => $division->id]);
        $user = ModelFactory::createUser([
            'locale' => Languages::DUTCH,
            'email' => 'user@example.com',
            'financers' => [
                ['financer' => $financer, 'active' => true],
            ],
        ]);

        $cognitoData = [
            'email' => 'user@example.com',
            'custom:reg_language' => Languages::DUTCH,
        ];

        // Act - First call should hit DB
        $locale1 = $this->localeManager->determineFromCognito($cognitoData, $user->id);

        // Change user locale in DB (should not affect cached result)
        $user->update(['locale' => Languages::POLISH]);

        // Act - Second call should use cache
        $locale2 = $this->localeManager->determineFromCognito($cognitoData, $user->id);

        // Assert - Both should return cached value
        $this->assertEquals(Languages::DUTCH, $locale1);
        $this->assertEquals(Languages::DUTCH, $locale2); // Still cached, not POLISH
    }

    #[Test]
    public function it_invalidates_cache_on_user_update(): void
    {
        // Arrange
        $division = ModelFactory::createDivision();
        $financer = ModelFactory::createFinancer(['division_id' => $division->id]);
        $user = ModelFactory::createUser([
            'locale' => Languages::ENGLISH,
            'email' => 'user@example.com',
            'financers' => [
                ['financer' => $financer, 'active' => true],
            ],
        ]);

        $cognitoData = [
            'email' => 'user@example.com',
        ];

        // Act - First call should cache
        $locale1 = $this->localeManager->determineFromCognito($cognitoData, $user->id);
        $this->assertEquals(Languages::ENGLISH, $locale1);

        // Update user - should invalidate cache
        $this->localeManager->invalidateCache($user->email);

        // Change locale
        $user->update(['locale' => Languages::RUSSIAN]);

        // Act - Second call should fetch fresh data
        $locale2 = $this->localeManager->determineFromCognito($cognitoData, $user->id);

        // Assert - Should reflect new locale
        $this->assertEquals(Languages::RUSSIAN, $locale2);
    }

    #[Test]
    public function it_uses_scoped_locale_pattern(): void
    {
        // Arrange
        $division = ModelFactory::createDivision();
        $financer = ModelFactory::createFinancer(['division_id' => $division->id]);
        ModelFactory::createUser([
            'locale' => Languages::FRENCH,
            'email' => 'user@example.com',
            'financers' => [
                ['financer' => $financer, 'active' => true],
            ],
        ]);

        $originalLocale = app()->getLocale();

        // Act - Use withLocale callback pattern
        $result = $this->localeManager->withLocale(Languages::GERMAN, function () {
            return app()->getLocale();
        });

        // Assert - Callback should execute with scoped locale
        $this->assertEquals(Languages::GERMAN, $result);

        // Assert - Locale should be restored
        $this->assertEquals($originalLocale, app()->getLocale());
    }

    #[Test]
    public function it_normalizes_identifiers_before_hashing(): void
    {
        // Arrange - Different email formats should produce same hash
        $emails = [
            'User@Example.Com',
            'user@example.com',
            ' user@example.com ',
            'USER@EXAMPLE.COM',
        ];

        // Act - Hash each variant
        $hashes = array_map(
            fn (string $email): string => $this->localeManager->hashIdentifier($email),
            $emails
        );

        // Assert - All should produce same hash
        $this->assertCount(1, array_unique($hashes), 'All email variants should produce same hash');
    }
}
