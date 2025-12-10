<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Models;

use App\Enums\Languages;
use App\Models\User;
use App\Services\Models\UserService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('user')]
class UserServiceLanguageTest extends TestCase
{
    use DatabaseTransactions;

    private UserService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new UserService;
    }

    #[Test]
    public function it_updates_user_language_with_context(): void
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

        // Set context
        Context::add('financer_id', $financer->id);

        // Act
        $this->service->updateUserLanguage($user, Languages::SPANISH);

        // Assert - Both pivot and locale should be updated
        $pivot = DB::table('financer_user')
            ->where('user_id', $user->id)
            ->where('financer_id', $financer->id)
            ->first();

        $this->assertEquals(Languages::SPANISH, $pivot->language);

        $user->refresh();
        $this->assertEquals(Languages::SPANISH, $user->getRawOriginal('locale'));
    }

    #[Test]
    public function it_updates_only_locale_without_context(): void
    {
        // Arrange
        $user = ModelFactory::createUser([
            'locale' => Languages::ENGLISH,
        ]); // No financer

        // Ensure no context
        Context::flush();

        // Act
        $this->service->updateUserLanguage($user, Languages::GERMAN);

        // Assert
        $user->refresh();
        $this->assertEquals(Languages::GERMAN, $user->getRawOriginal('locale'));
    }

    #[Test]
    public function it_updates_settings_including_language(): void
    {
        // Arrange
        $division = ModelFactory::createDivision();
        $financer = ModelFactory::createFinancer(['division_id' => $division->id]);
        $user = ModelFactory::createUser([
            'locale' => Languages::ENGLISH,
            'first_name' => 'John',
            'financers' => [
                ['financer' => $financer, 'active' => true],
            ],
        ]);

        Context::add('financer_id', $financer->id);

        // Act
        $result = $this->service->updateSettings($user, [
            'locale' => Languages::FRENCH,
            'first_name' => 'Jane',
        ]);

        // Assert
        $pivot = DB::table('financer_user')
            ->where('user_id', $user->id)
            ->where('financer_id', $financer->id)
            ->first();

        $this->assertEquals(Languages::FRENCH, $pivot->language);
        $this->assertInstanceOf(User::class, $result);

        // Verify first_name was updated
        $user->refresh();
        $this->assertEquals('Jane', $user->first_name);
    }

    #[Test]
    public function it_handles_language_only_in_settings_update(): void
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

        Context::add('financer_id', $financer->id);

        // Act
        $result = $this->service->updateSettings($user, [
            'locale' => Languages::ITALIAN,
        ]);

        // Assert - Only language should be updated
        $pivot = DB::table('financer_user')
            ->where('user_id', $user->id)
            ->where('financer_id', $financer->id)
            ->first();

        $this->assertEquals(Languages::ITALIAN, $pivot->language);
        $this->assertInstanceOf(User::class, $result);
    }

    #[Test]
    public function it_gets_current_financer_from_context(): void
    {
        // Arrange
        $division = ModelFactory::createDivision();
        $financer1 = ModelFactory::createFinancer(['division_id' => $division->id]);
        $financer2 = ModelFactory::createFinancer(['division_id' => $division->id]);

        $user = ModelFactory::createUser([
            'financers' => [
                ['financer' => $financer1, 'active' => true],
                ['financer' => $financer2, 'active' => true],
            ],
        ]);

        // Set context to financer2
        Context::add('financer_id', $financer2->id);

        // Act
        $this->service->updateUserLanguage($user, Languages::SPANISH);

        // Assert - Should update financer2 from context
        $pivot2 = DB::table('financer_user')
            ->where('user_id', $user->id)
            ->where('financer_id', $financer2->id)
            ->first();

        $this->assertEquals(Languages::SPANISH, $pivot2->language);

        // Financer1 should remain unchanged
        $pivot1 = DB::table('financer_user')
            ->where('user_id', $user->id)
            ->where('financer_id', $financer1->id)
            ->first();

        $this->assertNotEquals(Languages::SPANISH, $pivot1->language);
    }

    protected function tearDown(): void
    {
        Context::flush();
        parent::tearDown();
    }
}
