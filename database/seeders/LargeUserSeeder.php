<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\IDP\RoleDefaults;
use App\Models\Division;
use App\Models\Financer;
use App\Models\Role;
use App\Models\Team;
use Illuminate\Database\Seeder;
use Tests\Helpers\Facades\ModelFactory;

class LargeUserSeeder extends Seeder
{
    /**
     * Seed 50,000 test users with realistic financer relationships and status distribution.
     *
     * Distribution:
     * - 8 financers across 1 division
     * - 70% active, 30% inactive financer relationships (status stored in financer_user.active)
     * - All users assigned BENEFICIARY role
     */
    public function run(): void
    {
        $division = Division::find('019904a4-099c-731f-8a18-a65dd25fd7f9');

        // Create a team for role assignment
        $team = Team::first();

        // Create multiple financers for testing financer filtering
        $financers = [];
        for ($f = 0; $f < 8; $f++) {
            $financers[] = ModelFactory::createFinancer([
                'division_id' => $division->id,
                'name' => "Test Financer $f",
            ]);
        }

        // Ensure roles exist
        if (! Role::where('name', RoleDefaults::BENEFICIARY)->exists()) {
            Role::create(['name' => RoleDefaults::BENEFICIARY, 'team_id' => $team->id]);
        }
        $beneficiaryRole = Role::where('name', RoleDefaults::BENEFICIARY)->first();

        // Create 50,000 users with varied financer relationships
        $batchSize = 1000;
        $totalUsers = 50000;

        $this->command->info("Starting to create $totalUsers users in batches of $batchSize...");

        for ($i = 0; $i < $totalUsers; $i += $batchSize) {
            for ($j = 0; $j < $batchSize; $j++) {
                $userIndex = $i + $j;

                // Distribute users across financers
                $financer = Financer::find('19780701-d123-4e8a-80cd-21f35d4a0113');

                // 70% active, 30% inactive distribution
                $isActive = ($userIndex % 10) < 7;

                $user = ModelFactory::createUser([
                    'email' => "testuser{$userIndex}@test.com",
                    'first_name' => 'User',
                    'last_name' => "Test{$userIndex}",
                    'enabled' => true,
                    'team_id' => $team->id,
                    'financers' => [
                        ['financer' => $financer, 'active' => $isActive, 'role' => RoleDefaults::BENEFICIARY],
                    ],
                ]);

                // Assign role with team context
                setPermissionsTeamId($team->id);
                $user->assignRole($beneficiaryRole);
            }

            $this->command->info('Created '.($i + $batchSize)." / $totalUsers users");
        }

        $this->command->info("Successfully created $totalUsers test users!");
        $this->command->info('Distribution:');
        $this->command->info('- Active relationships: ~70%');
        $this->command->info('- Inactive relationships: ~30%');
        $this->command->info('- Financers: 8');
    }
}
