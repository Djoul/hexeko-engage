<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class AssignRolesFromPivotSeeder extends Seeder
{
    public function run(): void
    {
        $assignedCount = 0;
        $usersProcessed = 0;

        // Get total count for progress reporting
        $totalUsers = User::count();
        $this->command->info("    👉 Processing {$totalUsers} users in chunks...");

        setPermissionsTeamId(Team::first()->id);

        // Process users in chunks to avoid memory exhaustion
        User::with('financers')
            ->chunkById(500, function ($users) use (&$assignedCount, &$usersProcessed, $totalUsers): void {
                foreach ($users as $user) {
                    $usersProcessed++;

                    // Single role system: get role from first active financer's pivot
                    $activeFinancer = $user->financers->where('pivot.active', true)->first();

                    if ($activeFinancer && $activeFinancer->pivot && $activeFinancer->pivot->role) {
                        $roleName = $activeFinancer->pivot->role;

                        // Assign the single role if not already assigned
                        if (! $user->hasRole($roleName)) {
                            $user->assignRole($roleName);
                            $assignedCount++;
                        }
                    }
                }

                // Progress feedback every chunk
                $this->command->comment("    ⏳ Processed {$usersProcessed}/{$totalUsers} users...");
            });

        $this->command->info("    ✅ Completed! Processed {$usersProcessed} users and created {$assignedCount} role assignments.");
    }
}
