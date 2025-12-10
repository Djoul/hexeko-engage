<?php

namespace Database\Seeders;

use App\Models\User;
use Exception;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class AssignUserRolesFromFinancerUser extends Seeder
{
    /**
     * Script à usage unique pour assigner les rôles aux utilisateurs
     * basé sur les données de la table financer_user (single role system)
     */
    public function run(): void
    {
        $this->command->info('🚀 Starting role assignment from financer_user table (single role system)...');

        // Récupérer tous les rôles uniques possibles
        $this->ensureRolesExist();

        // Récupérer toutes les relations financer_user avec leurs rôles
        $financerUserRelations = DB::table('financer_user')
            ->whereNotNull('role')
            ->where('role', '!=', '')
            ->get();

        $this->command->info("Found {$financerUserRelations->count()} financer_user relations with roles");

        // Grouper par user_id pour obtenir le rôle de leur financer actif
        $userRoleMap = [];

        foreach ($financerUserRelations as $relation) {
            $userId = $relation->user_id;
            $role = $relation->role;

            // Priorité au financer actif
            if ($relation->active) {
                $userRoleMap[$userId] = $role;
            } elseif (! isset($userRoleMap[$userId])) {
                // Utiliser le premier rôle trouvé si pas de financer actif
                $userRoleMap[$userId] = $role;
            }
        }

        $this->command->info('Processing roles for '.count($userRoleMap).' unique users');

        $successCount = 0;
        $errorCount = 0;
        $skippedCount = 0;

        // Traiter chaque utilisateur
        foreach ($userRoleMap as $userId => $roleName) {
            try {
                $user = User::find($userId);

                if (! $user) {
                    $this->command->warn("User not found: {$userId}");
                    $skippedCount++;

                    continue;
                }

                // Détacher tous les rôles existants
                $user->roles()->detach();

                // Nettoyer le nom du rôle
                $roleName = trim($roleName);

                // Vérifier si le rôle existe
                $role = Role::where('name', $roleName)->first();

                if ($role) {
                    $user->assignRole($role);
                    $this->command->line("  ✓ Assigned role '{$roleName}' to user {$user->email}");
                    $successCount++;
                } else {
                    $this->command->warn("  ⚠ Role '{$roleName}' not found for user {$user->email}");
                    $skippedCount++;
                }

            } catch (Exception $e) {
                $this->command->error("Error processing user {$userId}: ".$e->getMessage());
                $errorCount++;
            }
        }

        // Rapport final
        $this->command->newLine();
        $this->command->info('📊 Assignment Complete (Single Role System):');
        $this->command->info("  ✅ Success: {$successCount} users");
        $this->command->warn("  ⚠️  Skipped: {$skippedCount} users");
        $this->command->error("  ❌ Errors: {$errorCount} users");

        // Afficher les statistiques des rôles
        $this->displayRoleStatistics();
    }

    /**
     * S'assurer que tous les rôles nécessaires existent
     */
    private function ensureRolesExist(): void
    {
        $this->command->info('Ensuring all required roles exist...');

        // Liste des rôles possibles basée sur les données
        $requiredRoles = [
            'god',
            'beneficiary',
            'financer_admin',
            'financer_super_admin',
            'division_admin',
            'division_super_admin',
            'hexeko_admin',
            'hexeko_super_admin',
        ];

        foreach ($requiredRoles as $roleName) {
            if (! Role::where('name', $roleName)->exists()) {
                Role::create(['name' => $roleName, 'guard_name' => 'web']);
                $this->command->info("  Created role: {$roleName}");
            }
        }
    }

    /**
     * Afficher les statistiques des rôles assignés
     */
    private function displayRoleStatistics(): void
    {
        $this->command->newLine();
        $this->command->info('📈 Role Distribution:');

        $roles = Role::withCount('users')->get();

        foreach ($roles as $role) {
            $this->command->line("  • {$role->name}: {$role->users_count} users");
        }

        // Utilisateurs sans rôles
        $usersWithoutRoles = User::doesntHave('roles')->count();
        if ($usersWithoutRoles > 0) {
            $this->command->warn("  • Users without roles: {$usersWithoutRoles}");
        }
    }
}
