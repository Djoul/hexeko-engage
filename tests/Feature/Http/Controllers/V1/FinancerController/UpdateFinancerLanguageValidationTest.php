<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\V1\FinancerController;

use App\Enums\IDP\RoleDefaults;
use App\Models\Financer;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

/**
 * Tests for financer language update validation
 */
#[Group('financer')]
class UpdateFinancerLanguageValidationTest extends ProtectedRouteTestCase
{
    use DatabaseTransactions;

    protected Financer $financer;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Create financer with multiple languages
        $division = ModelFactory::createDivision([
            'name' => 'Test Division',
            'language' => 'fr-FR',
        ]);

        $this->financer = ModelFactory::createFinancer([
            'name' => 'Test Financer',
            'division_id' => $division->id,
            'available_languages' => ['fr-FR', 'en-GB', 'nl-BE'],
        ]);

        // Create admin user (it will have its own financer)
        $this->admin = $this->createAuthUser(RoleDefaults::FINANCER_SUPER_ADMIN);

        // Attach admin to the test financer so it can modify it
        $this->admin->financers()->attach($this->financer->id, [
            'active' => true,
            'language' => 'fr-FR',
            'role' => RoleDefaults::FINANCER_SUPER_ADMIN,
        ]);
        $this->admin->load('financers');
    }

    #[Test]
    public function it_prevents_removing_language_used_by_users(): void
    {
        // Create user with nl-BE language preference
        $user = ModelFactory::createUser([
            'email' => 'user@test.com',
            'first_name' => 'Test',
            'last_name' => 'User',
        ]);

        // Attach user to financer with specific language
        $user->financers()->attach($this->financer->id, [
            'active' => true,
            'language' => 'nl-BE',
            'role' => RoleDefaults::BENEFICIARY,
        ]);

        // Try to remove nl-BE from available languages
        $response = $this->actingAs($this->admin)->putJson("/api/v1/financers/{$this->financer->id}", [
            'name' => $this->financer->name,
            'division_id' => $this->financer->division_id,
            'available_languages' => ['fr-FR', 'en-GB'], // Removing nl-BE
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['available_languages']);

        $response->assertJsonFragment([
            'available_languages' => [
                'Cannot remove a language that is currently used by users. Please update user language preferences first.',
            ],
        ]);
    }

    #[Test]
    public function it_allows_removing_unused_language(): void
    {
        // No user is using nl-BE

        $response = $this->actingAs($this->admin)->putJson("/api/v1/financers/{$this->financer->id}", [
            'name' => $this->financer->name,
            'division_id' => $this->financer->division_id,
            'available_languages' => ['fr-FR', 'en-GB'], // Removing nl-BE (not used)
        ]);

        $response->assertOk();

        // Verify languages were updated
        $this->financer->refresh();
        $this->assertEquals(['fr-FR', 'en-GB'], $this->financer->available_languages);
    }

    #[Test]
    public function it_allows_adding_new_languages(): void
    {
        $response = $this->actingAs($this->admin)->putJson("/api/v1/financers/{$this->financer->id}", [
            'name' => $this->financer->name,
            'division_id' => $this->financer->division_id,
            'available_languages' => ['fr-FR', 'en-GB', 'nl-BE', 'de-DE'], // Adding de-DE
        ]);

        $response->assertOk();

        $this->financer->refresh();
        $this->assertEquals(['fr-FR', 'en-GB', 'nl-BE', 'de-DE'], $this->financer->available_languages);
    }

    #[Test]
    public function it_prevents_removing_multiple_languages_when_one_is_in_use(): void
    {
        // Create users with different language preferences
        $userFr = ModelFactory::createUser([
            'email' => 'user-fr@test.com',
        ]);
        $userFr->financers()->attach($this->financer->id, [
            'active' => true,
            'language' => 'fr-FR',
            'role' => RoleDefaults::BENEFICIARY,
        ]);

        $userNl = ModelFactory::createUser([
            'email' => 'user-nl@test.com',
        ]);
        $userNl->financers()->attach($this->financer->id, [
            'active' => true,
            'language' => 'nl-BE',
            'role' => RoleDefaults::BENEFICIARY,
        ]);

        // Try to keep only en-GB (removing fr-FR and nl-BE which are in use)
        $response = $this->actingAs($this->admin)->putJson("/api/v1/financers/{$this->financer->id}", [
            'name' => $this->financer->name,
            'division_id' => $this->financer->division_id,
            'available_languages' => ['en-GB'],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['available_languages']);
    }

    #[Test]
    public function it_allows_keeping_all_languages_when_updating_other_fields(): void
    {
        $user = ModelFactory::createUser([
            'email' => 'user@test.com',
        ]);
        $user->financers()->attach($this->financer->id, [
            'active' => true,
            'language' => 'nl-BE',
            'role' => RoleDefaults::BENEFICIARY,
        ]);

        // Update other fields but keep all languages
        $response = $this->actingAs($this->admin)->putJson("/api/v1/financers/{$this->financer->id}", [
            'name' => 'Updated Financer Name',
            'division_id' => $this->financer->division_id,
            'available_languages' => ['fr-FR', 'en-GB', 'nl-BE'], // Keep all
            'timezone' => 'Europe/Brussels',
        ]);

        $response->assertOk();

        $this->financer->refresh();
        $this->assertEquals('Updated Financer Name', $this->financer->name);
        $this->assertEquals('Europe/Brussels', $this->financer->timezone);
    }

    #[Test]
    public function it_requires_minimum_one_language(): void
    {
        // Try to remove all languages
        $response = $this->actingAs($this->admin)->putJson("/api/v1/financers/{$this->financer->id}", [
            'name' => $this->financer->name,
            'division_id' => $this->financer->division_id,
            'available_languages' => [], // Empty array
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['available_languages']);
    }

    #[Test]
    public function it_validates_language_format(): void
    {
        $response = $this->actingAs($this->admin)->putJson("/api/v1/financers/{$this->financer->id}", [
            'name' => $this->financer->name,
            'division_id' => $this->financer->division_id,
            'available_languages' => ['fr-FR', 123], // Invalid: number instead of string
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['available_languages.1']);
    }

    /**
     * Regression test for UE-824: Updating financer without available_languages
     * should NOT trigger language validation error.
     */
    #[Test]
    public function it_allows_partial_update_without_available_languages_field(): void
    {
        // Create user with language preference (to ensure validation would fail if triggered)
        $user = ModelFactory::createUser([
            'email' => 'user@test.com',
        ]);
        $user->financers()->attach($this->financer->id, [
            'active' => true,
            'language' => 'nl-BE',
            'role' => RoleDefaults::BENEFICIARY,
        ]);

        // Update only status and other fields WITHOUT available_languages
        // This should NOT trigger language validation
        $response = $this->actingAs($this->admin)->putJson("/api/v1/financers/{$this->financer->id}", [
            'name' => $this->financer->name,
            'division_id' => $this->financer->division_id,
            'status' => 'active',
            'core_package_price' => 100,
        ]);

        $response->assertOk();

        // Verify languages were NOT changed
        $this->financer->refresh();
        $this->assertEquals(['fr-FR', 'en-GB', 'nl-BE'], $this->financer->available_languages);
    }

    /**
     * Regression test for UE-824: Updating financer without available_languages
     * should NOT trigger language validation error, even when updating other fields.
     */
    #[Test]
    public function it_allows_financer_name_update_without_available_languages_field(): void
    {
        // Create user with language preference (to ensure validation would fail if triggered)
        $user = ModelFactory::createUser([
            'email' => 'user2@test.com',
        ]);
        $user->financers()->attach($this->financer->id, [
            'active' => true,
            'language' => 'nl-BE',
            'role' => RoleDefaults::BENEFICIARY,
        ]);

        // Update financer name WITHOUT available_languages field
        // This should NOT trigger language validation
        $response = $this->actingAs($this->admin)->putJson(
            "/api/v1/financers/{$this->financer->id}?financer_id={$this->financer->id}",
            [
                'name' => 'Updated Test Financer',
                'division_id' => $this->financer->division_id,
            ]
        );

        $response->assertOk();

        // Languages should remain unchanged
        $this->financer->refresh();
        $this->assertEquals('Updated Test Financer', $this->financer->name);
        $this->assertEquals(['fr-FR', 'en-GB', 'nl-BE'], $this->financer->available_languages);
    }
}
