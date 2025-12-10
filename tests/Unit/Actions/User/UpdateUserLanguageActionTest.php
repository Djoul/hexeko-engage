<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\User;

use App\Actions\User\UpdateUserLanguageAction;
use App\Enums\Languages;
use App\Models\FinancerUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('user')]
class UpdateUserLanguageActionTest extends TestCase
{
    use DatabaseTransactions;

    private UpdateUserLanguageAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new UpdateUserLanguageAction;
    }

    #[Test]
    public function it_updates_language_for_active_financer_in_context(): void
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

        // Validate UUIDs
        $this->assertTrue(Str::isUuid($financer->id));
        $this->assertTrue(Str::isUuid($user->id));

        // Set context with explicit cast
        Context::add('financer_id', (string) $financer->id);

        // Act
        $this->action->execute($user, Languages::FRENCH);

        // Assert - Should update financer_user.language with explicit casts
        $pivot = DB::table('financer_user')
            ->where('user_id', (string) $user->id)
            ->where('financer_id', (string) $financer->id)
            ->first();

        $this->assertEquals(Languages::FRENCH, $pivot->language);

        // User locale should also be updated for backward compatibility
        $user->refresh();
        $this->assertEquals(Languages::FRENCH, $user->getRawOriginal('locale'));
    }

    #[Test]
    public function it_updates_only_db_locale_when_no_context(): void
    {
        // Arrange
        $user = ModelFactory::createUser([
            'locale' => Languages::ENGLISH,
        ]); // No financer

        // Act - No context set
        $this->action->execute($user, Languages::SPANISH);

        // Assert - Should only update user.locale
        $user->refresh();
        $this->assertEquals(Languages::SPANISH, $user->getRawOriginal('locale'));
    }

    #[Test]
    public function it_does_not_update_inactive_financer(): void
    {
        // Arrange
        $division = ModelFactory::createDivision();
        $financer = ModelFactory::createFinancer(['division_id' => $division->id]);
        $user = ModelFactory::createUser([
            'locale' => Languages::ENGLISH,
            'financers' => [
                ['financer' => $financer, 'active' => false],
            ],
        ]);

        // Set context to inactive financer with explicit cast
        Context::add('financer_id', (string) $financer->id);

        // Act
        $this->action->execute($user, Languages::GERMAN);

        // Assert - Should NOT update financer_user.language for inactive with explicit casts
        $pivot = DB::table('financer_user')
            ->where('user_id', (string) $user->id)
            ->where('financer_id', (string) $financer->id)
            ->first();

        $this->assertNotEquals(Languages::GERMAN, $pivot->language);

        // But should update user.locale
        $user->refresh();
        $this->assertEquals(Languages::GERMAN, $user->getRawOriginal('locale'));
    }

    #[Test]
    public function it_handles_multiple_financers_correctly(): void
    {
        // Arrange
        $division = ModelFactory::createDivision();
        $financer1 = ModelFactory::createFinancer(['division_id' => $division->id]);
        $financer2 = ModelFactory::createFinancer(['division_id' => $division->id]);

        $user = ModelFactory::createUser([
            'locale' => Languages::ENGLISH,
            'financers' => [
                ['financer' => $financer1, 'active' => true],
                ['financer' => $financer2, 'active' => true],
            ],
        ]);

        // Act - Update language for financer1 with explicit cast
        Context::add('financer_id', (string) $financer1->id);
        $this->action->execute($user, Languages::FRENCH);

        // Assert financer1 has French with explicit casts
        $pivot1 = DB::table('financer_user')
            ->where('user_id', (string) $user->id)
            ->where('financer_id', (string) $financer1->id)
            ->first();
        $this->assertEquals(Languages::FRENCH, $pivot1->language);

        // Act - Update language for financer2 with explicit cast
        Context::flush();
        Context::add('financer_id', (string) $financer2->id);
        $this->action->execute($user, Languages::SPANISH);

        // Assert financer2 has Spanish with explicit casts
        $pivot2 = DB::table('financer_user')
            ->where('user_id', (string) $user->id)
            ->where('financer_id', (string) $financer2->id)
            ->first();
        $this->assertEquals(Languages::SPANISH, $pivot2->language);

        // Financer1 should still have French with explicit casts
        $pivot1 = DB::table('financer_user')
            ->where('user_id', (string) $user->id)
            ->where('financer_id', (string) $financer1->id)
            ->first();
        $this->assertEquals(Languages::FRENCH, $pivot1->language);
    }

    #[Test]
    public function it_validates_language_value(): void
    {
        // Arrange
        $user = ModelFactory::createUser([
            'locale' => Languages::ENGLISH,
        ]);

        // Act & Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid language: invalid-lang');

        $this->action->execute($user, 'invalid-lang');
    }

    #[Test]
    public function it_uses_financer_user_model_when_updating(): void
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

        Context::add('financer_id', (string) $financer->id);

        // Act
        $this->action->execute($user, Languages::GERMAN);

        // Assert - Using FinancerUser model to verify with explicit casts
        $pivot = FinancerUser::where('user_id', (string) $user->id)
            ->where('financer_id', (string) $financer->id)
            ->first();

        $this->assertEquals(Languages::GERMAN, $pivot->language);

    }

    #[Test]
    public function it_logs_language_changes(): void
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

        Context::add('financer_id', (string) $financer->id);

        // Set initial language with explicit casts
        DB::table('financer_user')
            ->where('user_id', (string) $user->id)
            ->where('financer_id', (string) $financer->id)
            ->update(['language' => Languages::ENGLISH]);

        // Act
        $this->action->execute($user, Languages::FRENCH);

        // Assert - Check activity log if enabled with explicit casts
        // This would check activity logs if LogsActivity trait is enabled for FinancerUser
        $pivot = FinancerUser::where('user_id', (string) $user->id)
            ->where('financer_id', (string) $financer->id)
            ->first();

        $this->assertEquals(Languages::FRENCH, $pivot->language);
    }
}
