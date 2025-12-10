<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\IDP\RoleDefaults;
use App\Models\Financer;
use App\Models\User;
use App\Services\Models\InvitedUserService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class InvitedUsersSeeder extends Seeder
{
    /**
     * Seed invited users with both active and expired invitations.
     * Creates 3 active invitations and 2 expired invitations.
     */
    public function run(): void
    {
        // Use the first available financer or create one if none exists
        $financer = Financer::where('status', 'active')->first();

        if (! $financer) {
            $this->command->warn('No active financer found. Creating a test financer...');
            $financer = Financer::factory()->create([
                'name' => 'Test Financer for Invitations',
                'status' => 'active',
            ]);
            $this->command->info("Created test financer: {$financer->name} ({$financer->id})");
        }

        $financerId = $financer->id;
        $this->command->info("Using financer: {$financer->name} ({$financerId})");

        // Find or create an admin user to be the inviter
        $inviter = User::where('email', 'god.user@hexeko.com')->first();

        if (! $inviter) {
            $this->command->warn('No GOD user found. Creating a test inviter...');
            $inviter = User::factory()->create([
                'email' => 'inviter.admin@test.com',
                'first_name' => 'Inviter',
                'last_name' => 'Admin',
                'enabled' => true,
            ]);
            $this->command->info("Created test inviter: {$inviter->email} ({$inviter->id})");
        }

        $inviterId = (string) $inviter->id;
        $invitedUserService = app(InvitedUserService::class);

        // Create 3 ACTIVE invited users (valid invitations)
        $this->command->info('Creating 3 active invitations...');
        for ($i = 1; $i <= 3; $i++) {
            $userData = [
                'email' => "invited.active{$i}@test.com",
                'first_name' => "Active{$i}",
                'last_name' => 'Invited',
                'financer_id' => $financerId,
                'phone' => "+3361234567{$i}",
            ];

            $user = $invitedUserService->createWithRole(
                $userData,
                RoleDefaults::BENEFICIARY,
                $inviterId
            );

            $this->command->info("  ✓ Created active invitation: {$user->email} (expires: {$user->invitation_expires_at})");
        }

        // Create 2 EXPIRED invited users (expired invitations)
        $this->command->info('Creating 2 expired invitations...');
        for ($i = 1; $i <= 2; $i++) {
            $userData = [
                'email' => "invited.expired{$i}@test.com",
                'first_name' => "Expired{$i}",
                'last_name' => 'Invited',
                'financer_id' => $financerId,
                'phone' => "+3361234568{$i}",
            ];

            $user = $invitedUserService->createWithRole(
                $userData,
                RoleDefaults::BENEFICIARY,
                $inviterId
            );

            // Manually update the expiration date to the past
            $user->invitation_expires_at = Carbon::now()->subDays(10 + $i);
            $user->save();

            $this->command->info("  ✓ Created expired invitation: {$user->email} (expired: {$user->invitation_expires_at})");
        }

        $this->command->info('');
        $this->command->info('Successfully created 5 invited users!');
        $this->command->info("Financer: {$financer->name} ({$financerId})");
        $this->command->info('Active invitations: invited.active[1-3]@test.com');
        $this->command->info('Expired invitations: invited.expired[1-2]@test.com');
    }
}
