<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition()
    {
        $teamId = Team::firstOr(function () {
            return Team::factory()->create();
        })->id;

        return [
            'id' => Uuid::uuid4()->toString(),
            'team_id' => $teamId,
            'email' => Uuid::uuid4()->toString().'@test.be',
            'cognito_id' => Uuid::uuid4()->toString(),
            'temp_password' => null,
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'description' => $this->faker->boolean(70) ? $this->faker->paragraph(2) : null,
            'force_change_email' => $this->faker->boolean(10),
            'birthdate' => $this->faker->date('Y-m-d', '2005-01-01'),
            'terms_confirmed' => $this->faker->boolean(90),
            'enabled' => $this->faker->boolean(95),
            'locale' => $this->faker->randomElement(['fr-FR', 'en-GB', 'de-DE']),
            'currency' => $this->faker->randomElement(['EUR', 'USD', 'GBP']),
            'timezone' => $this->faker->timezone,
            'stripe_id' => Str::random(20),
            'sirh_id' => json_encode(['platform' => 'aws', 'id' => Uuid::uuid4()->toString()]),
            'last_login' => $this->faker->boolean(70) ? Carbon::now()->subDays(rand(1, 365)) : null,
            'opt_in' => $this->faker->boolean(50),
            'phone' => $this->faker->phoneNumber,
            'remember_token' => Str::random(10),
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ];
    }

    public function disabled()
    {
        return $this->state(fn (array $attributes): array => [
            'enabled' => false,
        ]);
    }

    /**
     * Create a user with pending invitation status.
     * Equivalent to legacy InvitedUser model.
     */
    public function invited(?User $inviter = null)
    {
        return $this->state(fn (array $attributes): array => [
            'invitation_status' => 'pending',
            'invitation_token' => Str::random(44), // 44 chars token
            'invitation_expires_at' => now()->addDays(7), // Default 7 days expiration
            'invited_by' => $inviter?->id,
            'invited_at' => now(),
            'invitation_accepted_at' => null,
            'invitation_metadata' => [],
            'enabled' => false, // Invited users are disabled until they accept
            'cognito_id' => null, // No Cognito ID until invitation accepted
        ]);
    }

    /**
     * Create a user with expired invitation.
     */
    public function invitedExpired(?User $inviter = null)
    {
        return $this->state(fn (array $attributes): array => [
            'invitation_status' => 'pending',
            'invitation_token' => Str::random(44),
            'invitation_expires_at' => now()->subDays(1), // Expired yesterday
            'invited_by' => $inviter?->id,
            'invited_at' => now()->subDays(8),
            'invitation_accepted_at' => null,
            'invitation_metadata' => [],
            'enabled' => false,
            'cognito_id' => null,
        ]);
    }

    /**
     * Create a user with accepted invitation (active user).
     */
    public function invitedAccepted(?User $inviter = null)
    {
        return $this->state(fn (array $attributes): array => [
            'invitation_status' => 'accepted',
            'invitation_token' => null, // Token cleared after acceptance
            'invitation_expires_at' => null,
            'invited_by' => $inviter?->id,
            'invited_at' => now()->subDays(7),
            'invitation_accepted_at' => now()->subDays(1),
            'invitation_metadata' => [],
            'enabled' => true, // Active user
            'cognito_id' => Uuid::uuid4()->toString(), // Cognito ID created on acceptance
        ]);
    }

    /**
     * Create a user with revoked invitation.
     */
    public function invitedRevoked(?User $inviter = null)
    {
        return $this->state(fn (array $attributes): array => [
            'invitation_status' => 'revoked',
            'invitation_token' => null,
            'invitation_expires_at' => null,
            'invited_by' => $inviter?->id,
            'invited_at' => now()->subDays(5),
            'invitation_accepted_at' => null,
            'invitation_metadata' => ['revoked_reason' => 'Manual revocation'],
            'enabled' => false,
            'cognito_id' => null,
        ]);
    }

    /**
     * Create a user with custom invitation metadata.
     */
    public function withInvitationMetadata(array $metadata)
    {
        return $this->state(fn (array $attributes): array => [
            'invitation_metadata' => $metadata,
        ]);
    }
}
