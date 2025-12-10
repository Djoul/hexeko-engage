<?php

namespace Tests\Feature\Http\Controllers\V1;

use App\Enums\IDP\RoleDefaults;
use App\Enums\Languages;
use App\Models\Financer;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

#[Group('auth')]
#[Group('webhook')]
#[Group('cognito')]
#[Group('user')]
class WebhookCognitoControllerTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Seed the database before each test
     */
    protected bool $seed = true;

    private Team $globalTeam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
    }

    protected function tearDown(): void
    {
        // Clear Spatie permission cache to prevent stale role references
        app()['cache']->forget('spatie.permission.cache');

        // Reset team context to prevent interference between tests
        setPermissionsTeamId(null);

        parent::tearDown();
    }

    /**
     * Seed roles for tests
     */
    private function seedRoles(): void
    {
        // Clear cache first to prevent stale references
        app()['cache']->forget('spatie.permission.cache');

        // Ensure a team exists for roles
        $this->globalTeam = Team::firstOrCreate(
            ['name' => 'Global Team'],
            ['slug' => 'global-team']
        );

        // Set permissions team ID for this test
        setPermissionsTeamId($this->globalTeam->id);

        // Force recreation of roles to ensure they exist in current transaction
        // Delete existing roles if they exist (handles rollback scenarios)
        Role::where('name', RoleDefaults::BENEFICIARY)
            ->where('guard_name', 'api')
            ->where('team_id', $this->globalTeam->id)
            ->delete();

        Role::where('name', RoleDefaults::FINANCER_SUPER_ADMIN)
            ->where('guard_name', 'api')
            ->where('team_id', $this->globalTeam->id)
            ->delete();

        // Create roles fresh for this test
        Role::create([
            'name' => RoleDefaults::BENEFICIARY,
            'guard_name' => 'api',
            'team_id' => $this->globalTeam->id,
        ]);

        Role::create([
            'name' => RoleDefaults::FINANCER_SUPER_ADMIN,
            'guard_name' => 'api',
            'team_id' => $this->globalTeam->id,
        ]);

        // Clear cache again to ensure fresh data
        app()['cache']->forget('spatie.permission.cache');
    }

    #[Test]
    public function it_converts_invited_user_to_user(): void
    {
        $this->withoutExceptionHandling();
        // Arrange
        $testEmail = 'test.webhook.'.uniqid().'@example.com';
        $financer = Financer::factory()->create(['available_languages' => [Languages::PORTUGUESE, Languages::FRENCH_BELGIUM]]);
        $this->assertNotNull($financer, 'Financer should be created');

        $invitedUser = User::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => $testEmail,
            'team_id' => $this->globalTeam->id,
            'invitation_status' => 'pending',
            'invitation_metadata' => ['financer_id' => $financer->id],
            'enabled' => false,
            'cognito_id' => null,
        ]);

        // Verify the financer exists in database
        $this->assertDatabaseHas('financers', [
            'id' => $financer->id,
        ]);

        // Act
        $response = $this->postJson('/api/v1/webhook/cognito/post-signup', [
            'sub' => $invitedUser->id,
            'email' => $testEmail,
            'invited_user_id' => $invitedUser->id,
            'custom:reg_language' => Languages::PORTUGUESE,
        ]);

        // Assert
        $response->assertStatus(Response::HTTP_OK);

        $response->assertJsonStructure([
            'message',
            'data',
        ]);

        // Assert that a new user was created
        $this->assertDatabaseHas('users', [
            'email' => $testEmail,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'cognito_id' => $invitedUser->id,
            'locale' => Languages::PORTUGUESE,
        ]);

        // Assert that the financer is attached to the user
        $user = User::where('email', $testEmail)->first();

        // Check financer attachment without the JSON column
        $this->assertDatabaseHas('financer_user', [
            'user_id' => $user->id,
            'financer_id' => $financer->id,
            'active' => 1,
            'language' => Languages::PORTUGUESE,
        ]);

        // Verify the role column (single role system)
        $financerUser = DB::table('financer_user')
            ->where('user_id', $user->id)
            ->where('financer_id', $financer->id)
            ->first();

        $this->assertEquals(RoleDefaults::BENEFICIARY, $financerUser->role);
        $this->assertTrue($user->hasRole(RoleDefaults::BENEFICIARY));
        // Assert that the invitation status is now 'accepted'
        $this->assertDatabaseHas('users', [
            'id' => $invitedUser->id,
            'invitation_status' => 'accepted',
        ]);

    }

    #[Test]
    public function it_converts_invited_user_to_admin(): void
    {
        $this->withoutExceptionHandling();
        // Arrange
        $testEmail = 'test.webhook.'.uniqid().'@example.com';
        $financer = Financer::factory()->create(['available_languages' => [Languages::FRENCH, Languages::ENGLISH]]);
        $this->assertNotNull($financer, 'Financer should be created');

        $invitedUser = User::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => $testEmail,
            'team_id' => $this->globalTeam->id,
            'invitation_status' => 'pending',
            'invitation_metadata' => [
                'financer_id' => $financer->id,
                'expires_at' => '2025-09-15T08:02:19+00:00',
                'invited_by' => '01980901-474d-7049-8fe2-006626d870ca',
                'intended_role' => 'financer_super_admin',
                'invitation_token' => 'bL3HHJVC50M8V8PioZlFmUpYTaR3exe89gutc469pFY=',
            ],
            'enabled' => false,
            'cognito_id' => null,
        ]);

        // Verify the financer exists in database
        $this->assertDatabaseHas('financers', [
            'id' => $financer->id,
        ]);

        // Act
        $response = $this->postJson('/api/v1/webhook/cognito/post-signup', [
            'sub' => $invitedUser->id,
            'email' => $testEmail,
            'invited_user_id' => $invitedUser->id,
        ]);

        // Assert
        $response->assertStatus(Response::HTTP_OK);

        $response->assertJsonStructure([
            'message',
            'data',
        ]);

        // Assert that a new user was created
        $this->assertDatabaseHas('users', [
            'email' => $testEmail,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'cognito_id' => $invitedUser->id,
        ]);

        // Assert that the financer is attached to the user
        $user = User::where('email', $testEmail)->first();

        // Check financer attachment without the JSON column
        $this->assertDatabaseHas('financer_user', [
            'user_id' => $user->id,
            'financer_id' => $financer->id,
            'active' => 1,
        ]);

        // Verify the role column (single role system)
        $financerUser = DB::table('financer_user')
            ->where('user_id', $user->id)
            ->where('financer_id', $financer->id)
            ->first();

        // Single role system: user has only the intended role (highest level)
        $this->assertEquals(RoleDefaults::FINANCER_SUPER_ADMIN, $financerUser->role);

        // Assert that the user has the correct role assigned via Spatie Permissions
        $this->assertTrue($user->hasRole(RoleDefaults::FINANCER_SUPER_ADMIN));
        $this->assertEquals(1, $user->roles()->count());

        // Assert that the invitation status is now 'accepted'
        $this->assertDatabaseHas('users', [
            'id' => $invitedUser->id,
            'invitation_status' => 'accepted',
        ]);

    }

    #[Test]
    public function it_creates_financer_association(): void
    {
        // Arrange
        $testEmail = 'test.financer.'.uniqid().'@example.com';
        $financer = Financer::factory()->create(['available_languages' => [Languages::FRENCH, Languages::ENGLISH]]);
        $this->assertNotNull($financer, 'Financer should be created');

        $invitedUser = User::factory()->create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => $testEmail,
            'team_id' => $this->globalTeam->id,
            'invitation_status' => 'pending',
            'invitation_metadata' => ['financer_id' => $financer->id],
            'enabled' => false,
            'cognito_id' => null,
        ]);

        // Verify the financer exists in database
        $this->assertDatabaseHas('financers', [
            'id' => $financer->id,
        ]);

        // Act
        $response = $this->postJson('/api/v1/webhook/cognito/post-signup', [
            'sub' => $invitedUser->id,
            'email' => $testEmail,
            'invited_user_id' => $invitedUser->id,
        ]);

        // Assert
        $response->assertStatus(Response::HTTP_OK);

        // Get the newly created user
        $user = User::where('email', $testEmail)->first();

        // Assert that the financer association was created
        $this->assertDatabaseHas('financer_user', [
            'user_id' => $user->id,
            'financer_id' => $financer->id,
            'active' => 1,
        ]);

        // Verify the role column (single role system)
        $financerUserRecord = DB::table('financer_user')
            ->where('user_id', $user->id)
            ->where('financer_id', $financer->id)
            ->first();

        $this->assertEquals(RoleDefaults::BENEFICIARY, $financerUserRecord->role);

        // Assert that the from field is set and to field is null
        $financerUser = $user->financers()->first()->pivot;
        $this->assertNotNull($financerUser->from);
        $this->assertNull($financerUser->to);
    }

    #[Test]
    public function it_handles_invalid_webhook_payload(): void
    {
        // Act - Missing sub
        $response1 = $this->postJson('/api/v1/webhook/cognito/post-signup', [
            'email' => 'test@example.com',
        ]);

        // Assert
        $response1->assertStatus(Response::HTTP_BAD_REQUEST);

        // Act - Missing email
        $response2 = $this->postJson('/api/v1/webhook/cognito/post-signup', [
            'sub' => 'some-uuid',
        ]);

        // Assert
        $response2->assertStatus(Response::HTTP_BAD_REQUEST);
    }

    #[Test]
    public function it_returns_404_when_invited_user_not_found(): void
    {
        // Act
        $response = $this->postJson('/api/v1/webhook/cognito/post-signup', [
            'sub' => '00000000-0000-0000-0000-000000000000',
            'email' => 'test@example.com',
            'invited_user_id' => Uuid::uuid4()->toString(),
        ]);

        // Assert
        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    #[Test]
    public function it_preserves_regional_language_variant_from_webhook(): void
    {
        if (! config('activitylog.enabled')) {
            $this->markTestSkipped('Activity logging is not enabled');
        }

        // Arrange
        $testEmail = 'test.regional.'.uniqid().'@example.com';
        $financer = Financer::factory()->create([
            'available_languages' => [Languages::FRENCH_BELGIUM, Languages::FRENCH, Languages::ENGLISH],
        ]);

        $invitedUser = User::factory()->create([
            'first_name' => 'Fred',
            'last_name' => 'Test',
            'email' => $testEmail,
            'phone' => '+32471321102',
            'team_id' => $this->globalTeam->id,
            'invitation_status' => 'pending',
            'invitation_metadata' => ['financer_id' => $financer->id],
            'enabled' => false,
            'cognito_id' => null,
        ]);

        // Act - Simulate exact payload from your log
        $response = $this->postJson('/api/v1/webhook/cognito/post-signup', [
            'email' => $testEmail,
            'financer_id' => $financer->id,
            'invited_user_id' => $invitedUser->id,
            'reg_language' => Languages::FRENCH_BELGIUM, // Note: WITHOUT "custom:" prefix
            'sub' => Uuid::uuid4()->toString(),
        ]);

        // Assert
        $response->assertStatus(Response::HTTP_OK);

        // Get created user
        $user = User::where('email', $testEmail)->first();
        $this->assertNotNull($user, 'User should be created');

        // CRITICAL: User locale should be fr-BE (or mapped to fr-FR if needed)
        // NOT en-GB (English default)
        $this->assertEquals(Languages::FRENCH_BELGIUM, $user->locale, 'User locale should match webhook reg_language');

        // Verify financer_user.language matches as well
        $financerUser = DB::table('financer_user')
            ->where('user_id', $user->id)
            ->where('financer_id', $financer->id)
            ->first();

        $this->assertEquals(Languages::FRENCH_BELGIUM, $financerUser->language, 'Financer language should match reg_language');

        // Verify locale was set exactly once and not changed afterward
        $activityLogs = DB::table('activity_log')
            ->where('subject_id', $user->id)
            ->where('subject_type', 'App\Models\User')
            ->where('description', 'updated')
            ->orderBy('created_at', 'asc')
            ->get();

        // Count how many update events touched the locale field
        $localeUpdateCount = 0;
        $firstLocaleValue = null;

        foreach ($activityLogs as $log) {
            $properties = json_decode($log->properties, true);
            if (array_key_exists('locale', $properties['attributes'] ?? [])) {
                $localeUpdateCount++;
                if ($firstLocaleValue === null) {
                    $firstLocaleValue = $properties['attributes']['locale'];
                }
                // If locale was set multiple times, they should all be the same value
                $this->assertEquals($firstLocaleValue, $properties['attributes']['locale'], 'Locale should not change after initial acceptance');
            }
        }

        // Locale should be set at least once (during invitation acceptance)
        $this->assertGreaterThanOrEqual(1, $localeUpdateCount, 'Locale should be set during invitation acceptance');
        $this->assertEquals(Languages::FRENCH_BELGIUM, $firstLocaleValue, 'First locale set should match webhook language');
    }

    #[Test]
    public function it_maps_regional_variants_to_base_language_when_not_in_financer_languages(): void
    {
        // Arrange
        $testEmail = 'test.mapping.'.uniqid().'@example.com';
        $financer = Financer::factory()->create([
            // Only base French available, NOT regional variant
            'available_languages' => [Languages::FRENCH, Languages::ENGLISH],
        ]);

        $invitedUser = User::factory()->create([
            'first_name' => 'Marie',
            'last_name' => 'Dupont',
            'email' => $testEmail,
            'team_id' => $this->globalTeam->id,
            'invitation_status' => 'pending',
            'invitation_metadata' => ['financer_id' => $financer->id],
            'enabled' => false,
            'cognito_id' => null,
        ]);

        // Act - User requests fr-BE but financer only supports fr-FR
        $response = $this->postJson('/api/v1/webhook/cognito/post-signup', [
            'email' => $testEmail,
            'invited_user_id' => $invitedUser->id,
            'reg_language' => Languages::FRENCH_BELGIUM,
            'sub' => Uuid::uuid4()->toString(),
        ]);

        // Assert
        $response->assertStatus(Response::HTTP_OK);

        $user = User::where('email', $testEmail)->first();

        // Should map fr-BE → fr-FR (NOT fallback to en-GB)
        $this->assertEquals(Languages::FRENCH, $user->locale, 'Should map fr-BE to fr-FR base language');

        $financerUser = DB::table('financer_user')
            ->where('user_id', $user->id)
            ->where('financer_id', $financer->id)
            ->first();

        $this->assertEquals(Languages::FRENCH, $financerUser->language);
    }
}
