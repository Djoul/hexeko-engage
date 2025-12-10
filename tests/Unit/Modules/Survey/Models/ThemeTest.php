<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Survey\Models;

use App\Integrations\Survey\Database\factories\ThemeFactory;
use App\Integrations\Survey\Models\Question;
use App\Integrations\Survey\Models\Theme;
use App\Models\Financer;
use App\Models\User;
use App\Scopes\HasNullableFinancerScope;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('survey')]
#[Group('theme')]
class ThemeTest extends TestCase
{
    use DatabaseTransactions;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->financer = ModelFactory::createFinancer();
        Context::add('financer_id', $this->financer->id);
    }

    #[Test]
    public function it_uses_uuid_as_primary_key(): void
    {
        $theme = new Theme;

        $this->assertTrue($theme->getIncrementing() === false);
        $this->assertEquals('string', $theme->getKeyType());
    }

    #[Test]
    public function it_can_create_a_theme(): void
    {
        // Arrange

        // Act
        $theme = Theme::factory()->create([
            'name' => [
                'en' => 'Test Theme',
                'fr' => 'Thème de Test',
                'nl' => 'Test Thema',
            ],
            'description' => [
                'en' => 'Test Description',
                'fr' => 'Description de Test',
                'nl' => 'Test Beschrijving',
            ],
            'financer_id' => $this->financer->id,
            'is_default' => false,
            'position' => 1,
        ]);

        // Assert
        $this->assertInstanceOf(Theme::class, $theme);
        $this->assertEquals('Test Theme', $theme->getTranslation('name', 'en'));
        $this->assertEquals('Thème de Test', $theme->getTranslation('name', 'fr'));
        $this->assertEquals('Test Thema', $theme->getTranslation('name', 'nl'));
        $this->assertEquals($this->financer->id, $theme->financer_id);
        $this->assertFalse($theme->is_default);
        $this->assertEquals(1, $theme->position);
    }

    #[Test]
    public function it_can_update_a_theme(): void
    {
        // Arrange
        $theme = Theme::create([
            'name' => ['en' => 'Original Theme'],
            'description' => ['en' => 'Original Description'],
            'financer_id' => $this->financer->id,
            'is_default' => false,
            'position' => 1,
        ]);

        $updatedData = [
            'name' => [
                'en' => 'Updated Theme',
                'fr' => 'Thème Mis à Jour',
            ],
            'description' => [
                'en' => 'Updated Description',
                'fr' => 'Description Mis à Jour',
            ],
            'is_default' => true,
            'position' => 5,
        ];

        // Act
        $theme->update($updatedData);

        // Assert
        $this->assertEquals('Updated Theme', $theme->getTranslation('name', 'en'));
        $this->assertEquals('Thème Mis à Jour', $theme->getTranslation('name', 'fr'));
        $this->assertEquals('Updated Description', $theme->getTranslation('description', 'en'));
        $this->assertTrue($theme->is_default);
        $this->assertEquals(5, $theme->position);
    }

    #[Test]
    public function it_can_soft_delete_a_theme(): void
    {
        // Arrange
        $theme = Theme::create([
            'name' => ['en' => 'Theme to Delete'],
            'description' => ['en' => 'Description'],
            'financer_id' => $this->financer->id,
        ]);

        // Act
        $theme->delete();

        // Assert
        $this->assertSoftDeleted('int_survey_themes', ['id' => $theme->id]);
        $this->assertTrue($theme->trashed());
    }

    #[Test]
    public function it_can_restore_a_soft_deleted_theme(): void
    {
        // Arrange
        $theme = Theme::create([
            'name' => ['en' => 'Theme to Restore'],
            'description' => ['en' => 'Description'],
            'financer_id' => $this->financer->id,
        ]);
        $theme->delete();

        // Act
        $theme->restore();

        // Assert
        $this->assertFalse($theme->trashed());
        $this->assertDatabaseHas('int_survey_themes', [
            'id' => $theme->id,
            'deleted_at' => null,
        ]);
    }

    #[Test]
    public function it_belongs_to_financer(): void
    {
        // Arrange
        $theme = Theme::create([
            'name' => ['en' => 'Theme with Financer'],
            'description' => ['en' => 'Description'],
            'financer_id' => $this->financer->id,
        ]);

        // Act
        $relatedFinancer = $theme->financer;

        // Assert
        $this->assertInstanceOf(Financer::class, $relatedFinancer);
        $this->assertEquals($this->financer->id, $relatedFinancer->id);
    }

    #[Test]
    public function it_can_scope_default_themes(): void
    {
        Theme::create([
            'name' => ['en' => 'Default Theme'],
            'description' => ['en' => 'Default Description'],
            'financer_id' => null,
            'is_default' => true,
        ]);

        Theme::create([
            'name' => ['en' => 'Non-Default Theme'],
            'description' => ['en' => 'Non-Default Description'],
            'financer_id' => null,
            'is_default' => false,
        ]);

        // Act
        $defaultThemes = Theme::query()->default()->get();

        // Assert
        $this->assertCount(1, $defaultThemes);
        $this->assertTrue($defaultThemes->first()->is_default);
    }

    #[Test]
    public function it_can_scope_themes_for_financer(): void
    {
        // Arrange
        $financer1 = ModelFactory::createFinancer();
        $financer2 = ModelFactory::createFinancer();

        // System theme (available to all)
        Theme::create([
            'name' => ['en' => 'System Theme'],
            'description' => ['en' => 'System Description'],
            'financer_id' => null,
        ]);

        // Custom theme for financer1
        Theme::create([
            'name' => ['en' => 'Financer1 Theme'],
            'description' => ['en' => 'Financer1 Description'],
            'financer_id' => $financer1->id,
        ]);

        // Custom theme for financer2
        Theme::create([
            'name' => ['en' => 'Financer2 Theme'],
            'description' => ['en' => 'Financer2 Description'],
            'financer_id' => $financer2->id,
        ]);

        // Act
        $financer1Themes = Theme::query()->withoutGlobalScope(HasNullableFinancerScope::class)->forFinancer($financer1->id)->get();
        $financer2Themes = Theme::query()->withoutGlobalScope(HasNullableFinancerScope::class)->forFinancer($financer2->id)->get();

        // Assert
        $this->assertCount(2, $financer1Themes); // System + Financer1
        $this->assertCount(2, $financer2Themes); // System + Financer2
    }

    #[Test]
    public function it_can_scope_ordered_themes(): void
    {
        Theme::create([
            'name' => ['en' => 'Zebra Theme'],
            'description' => ['en' => 'Zebra Description'],
            'financer_id' => null,
            'position' => 3,
        ]);

        Theme::create([
            'name' => ['en' => 'Apple Theme'],
            'description' => ['en' => 'Apple Description'],
            'financer_id' => null,
            'position' => 1,
        ]);

        Theme::create([
            'name' => ['en' => 'Banana Theme'],
            'description' => ['en' => 'Banana Description'],
            'financer_id' => null,
            'position' => 2,
        ]);

        // Act
        $orderedThemes = Theme::query()->ordered()->get();

        // Assert
        $this->assertCount(3, $orderedThemes);
    }

    #[Test]
    public function it_has_translatable_fields(): void
    {
        // Arrange
        $theme = new Theme;

        // Act & Assert
        $this->assertContains('name', $theme->translatable);
        $this->assertContains('description', $theme->translatable);
    }

    #[Test]
    public function it_casts_boolean_and_integer_fields(): void
    {
        // Arrange
        $theme = Theme::create([
            'name' => ['en' => 'Test Theme'],
            'description' => ['en' => 'Test Description'],
            'financer_id' => $this->financer->id,
            'is_default' => '1', // String that should be cast to boolean
            'position' => '5', // String that should be cast to integer
        ]);

        // Act & Assert
        $this->assertIsBool($theme->is_default);
        $this->assertIsInt($theme->position);
        $this->assertTrue($theme->is_default);
        $this->assertEquals(5, $theme->position);
    }

    #[Test]
    public function it_has_unique_id_constraint(): void
    {
        // Arrange

        $theme1 = Theme::create([
            'name' => ['en' => 'First Theme'],
            'description' => ['en' => 'First Description'],
            'financer_id' => $this->financer->id,
        ]);

        // Act & Assert - Try to create another theme with same ID (should fail)
        $this->expectException(QueryException::class);

        // This should fail because we're trying to use the same primary key
        DB::table('int_survey_themes')->insert([
            'id' => $theme1->id, // Same ID - should fail
            'name' => json_encode(['en' => 'Second Theme']),
            'description' => json_encode(['en' => 'Second Description']),
            'financer_id' => $this->financer->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    #[Test]
    public function it_has_many_questions(): void
    {
        // Arrange

        $theme = resolve(ThemeFactory::class)->create([
            'name' => ['en' => 'Test Theme'],
            'description' => ['en' => 'Test Description'],
            'financer_id' => $this->financer->id,
        ]);

        Question::factory(10)->create([
            'theme_id' => $theme->id,
            'financer_id' => $this->financer->id,
        ]);

        // Act
        $retrievedQuestions = $theme->questions;

        // Assert
        $this->assertCount(10, $retrievedQuestions);
    }

    // ==================== HasCreator Trait Tests ====================

    #[Test]
    public function it_automatically_sets_created_by_when_authenticated(): void
    {
        // Arrange
        $user = ModelFactory::createUser();

        // Act
        Auth::login($user);
        $theme = resolve(ThemeFactory::class)->create([
            'name' => ['en-GB' => 'Theme with creator'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
        ]);

        // Assert
        $this->assertEquals($user->id, $theme->created_by);
        $this->assertDatabaseHas('int_survey_themes', [
            'id' => $theme->id,
            'created_by' => $user->id,
        ]);
    }

    #[Test]
    public function it_does_not_set_created_by_when_not_authenticated(): void
    {
        // Arrange

        // Act
        Auth::logout();
        $theme = resolve(ThemeFactory::class)->create([
            'name' => ['en-GB' => 'Theme without creator'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
        ]);

        // Assert
        $this->assertNull($theme->created_by);
        $this->assertDatabaseHas('int_survey_themes', [
            'id' => $theme->id,
            'created_by' => null,
        ]);
    }

    #[Test]
    public function it_sets_updated_by_when_updating(): void
    {
        // Arrange
        $creator = ModelFactory::createUser();
        $updater = ModelFactory::createUser();

        Auth::login($creator);
        $theme = resolve(ThemeFactory::class)->create([
            'name' => ['en-GB' => 'Theme to update'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
        ]);

        // Act
        Auth::login($updater);
        $theme->update([
            'name' => ['en-GB' => 'Updated Theme Name'],
        ]);

        // Assert
        $this->assertEquals($creator->id, $theme->created_by);
        $this->assertEquals($updater->id, $theme->updated_by);
        $this->assertDatabaseHas('int_survey_themes', [
            'id' => $theme->id,
            'created_by' => $creator->id,
            'updated_by' => $updater->id,
        ]);
    }

    #[Test]
    public function it_has_creator_relationship(): void
    {
        // Arrange
        $creator = ModelFactory::createUser();

        Auth::login($creator);
        $theme = resolve(ThemeFactory::class)->create([
            'name' => ['en-GB' => 'Theme with creator relationship'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
        ]);

        // Act & Assert
        $this->assertInstanceOf(User::class, $theme->creator);
        $this->assertEquals($creator->id, $theme->creator->id);
        $this->assertEquals($creator->name, $theme->creator->name);
    }

    #[Test]
    public function it_has_updater_relationship(): void
    {
        // Arrange
        $creator = ModelFactory::createUser();
        $updater = ModelFactory::createUser();

        Auth::login($creator);
        $theme = resolve(ThemeFactory::class)->create([
            'name' => ['en-GB' => 'Theme with updater relationship'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
        ]);

        // Act
        Auth::login($updater);
        $theme->update([
            'name' => ['en-GB' => 'Updated Theme'],
        ]);

        // Assert
        $this->assertInstanceOf(User::class, $theme->updater);
        $this->assertEquals($updater->id, $theme->updater->id);
        $this->assertEquals($updater->name, $theme->updater->name);
    }

    #[Test]
    public function it_can_check_if_was_created_by_user(): void
    {
        // Arrange
        $creator = ModelFactory::createUser();
        $otherUser = ModelFactory::createUser();

        Auth::login($creator);
        $theme = resolve(ThemeFactory::class)->create([
            'name' => ['en-GB' => 'Theme to check creator'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
        ]);

        // Act & Assert
        $this->assertTrue($theme->wasCreatedBy($creator));
        $this->assertFalse($theme->wasCreatedBy($otherUser));
        $this->assertFalse($theme->wasCreatedBy(null));
    }

    #[Test]
    public function it_can_check_if_was_updated_by_user(): void
    {
        // Arrange
        $creator = ModelFactory::createUser();
        $updater = ModelFactory::createUser();
        $otherUser = ModelFactory::createUser();

        Auth::login($creator);
        $theme = resolve(ThemeFactory::class)->create([
            'name' => ['en-GB' => 'Theme to check updater'],
            'description' => ['en-GB' => 'Description'],
            'financer_id' => $this->financer->id,
        ]);

        // Act
        Auth::login($updater);
        $theme->update([
            'name' => ['en-GB' => 'Updated Theme'],
        ]);

        // Assert
        $this->assertTrue($theme->wasUpdatedBy($updater));
        $this->assertFalse($theme->wasUpdatedBy($creator));
        $this->assertFalse($theme->wasUpdatedBy($otherUser));
        $this->assertFalse($theme->wasUpdatedBy(null));
    }
}
