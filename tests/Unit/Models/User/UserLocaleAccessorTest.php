<?php

declare(strict_types=1);

namespace Tests\Unit\Models\User;

use App\Enums\Languages;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('user')]
class UserLocaleAccessorTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_gets_locale_from_active_financer_language(): void
    {
        // Arrange
        $division = ModelFactory::createDivision();
        $financer = ModelFactory::createFinancer(['division_id' => $division->id]);

        // Create user with default locale
        $user = ModelFactory::createUser([
            'locale' => Languages::ENGLISH,
            'financers' => [
                ['financer' => $financer, 'active' => true],
            ],
        ]);

        // Update financer_user.language to different value
        DB::table('financer_user')
            ->where('user_id', $user->id)
            ->where('financer_id', $financer->id)
            ->update(['language' => Languages::FRENCH]);

        // Set the Context with the financer
        Context::add('financer_id', $financer->id);

        // Act - Reload user to test accessor (with financers relation for optimization)
        $user = User::with('financers')->find($user->id);

        // Assert - Should return financer's language, not DB locale
        $this->assertEquals(Languages::FRENCH, $user->locale);
    }

    #[Test]
    public function it_returns_db_locale_when_no_active_financer(): void
    {
        // Arrange - User without financer
        $user = ModelFactory::createUser([
            'locale' => Languages::SPANISH,
        ]); // false = no financer attachment

        // Act
        $locale = $user->locale;

        // Assert
        $this->assertEquals(Languages::SPANISH, $locale);
    }

    #[Test]
    public function it_returns_default_locale_when_no_context(): void
    {
        // Arrange - User with English locale and no financer context
        $user = ModelFactory::createUser([
            'locale' => Languages::ENGLISH,
        ]);

        // Act - No context set
        $locale = $user->locale;

        // Assert - Should return the DB locale
        $this->assertEquals(Languages::ENGLISH, $locale);
    }

    #[Test]
    public function it_updates_only_db_locale_on_set(): void
    {
        // Arrange
        $division = ModelFactory::createDivision();
        $financer = ModelFactory::createFinancer(['division_id' => $division->id]);
        $user = ModelFactory::createUser([
            'locale' => Languages::ENGLISH,
            'financers' => [
                ['financer' => $financer, 'active' => true],
            ],
        ]);

        // Act - Set locale via accessor
        $user->locale = Languages::GERMAN;
        $user->save();

        // Assert - Only DB should be updated, not pivot (that's done via Action)
        $this->assertEquals(Languages::GERMAN, $user->getRawOriginal('locale'));

        $pivotLanguage = DB::table('financer_user')
            ->where('user_id', $user->id)
            ->where('financer_id', $financer->id)
            ->value('language');

        // Pivot should NOT be updated by the accessor
        $this->assertNotEquals(Languages::GERMAN, $pivotLanguage);
    }

    #[Test]
    public function it_syncs_locale_from_context_financer(): void
    {
        // Arrange - User with multiple financers
        $division = ModelFactory::createDivision();
        $financer1 = ModelFactory::createFinancer(['division_id' => $division->id]);
        $financer2 = ModelFactory::createFinancer(['division_id' => $division->id]);

        $user = ModelFactory::createUser([
            'locale' => Languages::ENGLISH,
            'financers' => [
                ['financer' => $financer1, 'active' => true, 'from' => now()->subDays(10)],
                ['financer' => $financer2, 'active' => true, 'from' => now()->subDays(5)],
            ],
        ]);

        // Update languages differently
        DB::table('financer_user')
            ->where('user_id', $user->id)
            ->where('financer_id', $financer1->id)
            ->update(['language' => Languages::FRENCH]);

        DB::table('financer_user')
            ->where('user_id', $user->id)
            ->where('financer_id', $financer2->id)
            ->update(['language' => Languages::SPANISH]);

        // Set Context to financer1
        Context::add('financer_id', $financer1->id);

        // Act - Reload and check (with financers relation)
        $user = User::with('financers')->find($user->id);

        // Assert - Should use context financer's language
        $this->assertEquals(Languages::FRENCH, $user->locale);

        // Change Context to financer2
        Context::flush();
        Context::add('financer_id', $financer2->id);

        // Assert - Should now use financer2's language
        $this->assertEquals(Languages::SPANISH, $user->locale);
    }

    #[Test]
    public function it_handles_inactive_financers_correctly(): void
    {
        // Arrange
        $division = ModelFactory::createDivision();
        $financer1 = ModelFactory::createFinancer(['division_id' => $division->id]);
        $financer2 = ModelFactory::createFinancer(['division_id' => $division->id]);

        $user = ModelFactory::createUser([
            'locale' => Languages::ENGLISH,
            'financers' => [
                ['financer' => $financer1, 'active' => false], // Inactive
                ['financer' => $financer2, 'active' => true],   // Active
            ],
        ]);

        // Update languages
        DB::table('financer_user')
            ->where('user_id', $user->id)
            ->where('financer_id', $financer1->id)
            ->update(['language' => Languages::FRENCH]);

        DB::table('financer_user')
            ->where('user_id', $user->id)
            ->where('financer_id', $financer2->id)
            ->update(['language' => Languages::SPANISH]);

        // Set Context to the active financer
        Context::add('financer_id', $financer2->id);

        // Act (with financers relation)
        $user = User::with('financers')->find($user->id);

        // Assert - Should use active financer's language
        $this->assertEquals(Languages::SPANISH, $user->locale);

        // If we set context to inactive financer, should fallback to DB
        Context::flush();
        Context::add('financer_id', $financer1->id);

        // Should fallback to DB locale when financer is inactive
        $this->assertEquals(Languages::ENGLISH, $user->locale);
    }

    #[Test]
    public function it_updates_only_db_locale_when_no_active_financer(): void
    {
        // Arrange - User without active financer
        $division = ModelFactory::createDivision();
        $financer = ModelFactory::createFinancer(['division_id' => $division->id]);
        $user = ModelFactory::createUser([
            'locale' => Languages::ENGLISH,
            'financers' => [
                ['financer' => $financer, 'active' => false],
            ],
        ]);

        // Act
        $user->locale = Languages::GERMAN;
        $user->save();

        // Assert - Only DB should be updated, not inactive financer
        $this->assertEquals(Languages::GERMAN, $user->getRawOriginal('locale'));

        $pivotLanguage = DB::table('financer_user')
            ->where('user_id', $user->id)
            ->where('financer_id', $financer->id)
            ->value('language');

        // Pivot should remain unchanged (null or previous value)
        $this->assertNotEquals(Languages::GERMAN, $pivotLanguage);
    }
}
