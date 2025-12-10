<?php

namespace App\Console\Commands;

use App\Enums\IDP\RoleDefaults;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;

class AssignGodRole extends Command
{
    protected $signature = 'user:assign-god {email}';

    protected $description = 'Assign GOD role to a user for both api and web guards';

    public function handle(): int
    {
        $email = $this->argument('email');
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("User with email {$email} not found.");

            return 1;
        }

        $this->info("Found user: {$user->email} (ID: {$user->id})");
        $this->info("User team ID: {$user->team_id}");

        // Set team context for Spatie permissions
        setPermissionsTeamId($user->team_id);

        // Find or create GOD roles for both guards
        $guards = ['api', 'web'];

        foreach ($guards as $guard) {
            $role = Role::where('name', RoleDefaults::GOD)
                ->where('guard_name', $guard)
                ->where('team_id', $user->team_id)
                ->first();

            if (! $role) {
                $this->info("Creating GOD role for guard: {$guard}");
                $role = Role::create([
                    'name' => RoleDefaults::GOD,
                    'guard_name' => $guard,
                    'team_id' => $user->team_id,
                    'is_protected' => true,
                ]);
            }

            // Clear any existing role assignments for this guard
            $user->removeRole(RoleDefaults::GOD);

            // Assign the role
            $user->assignRole($role);

            $this->info("Assigned GOD role for guard: {$guard}");
        }

        // Verify the assignments
        $user->refresh();
        $user->load('roles');

        $this->info("\nVerification:");
        $this->info('User has '.$user->roles()->count().' roles');

        foreach ($guards as $guard) {
            $hasRole = $user->hasRole(RoleDefaults::GOD, $guard);
            $this->info("Has GOD role for {$guard} guard: ".($hasRole ? 'Yes' : 'No'));
        }

        // Also check default
        $hasRoleDefault = $user->hasRole(RoleDefaults::GOD);
        $this->info('Has GOD role (default check): '.($hasRoleDefault ? 'Yes' : 'No'));

        return 0;
    }
}
