<?php

namespace Database\Seeders;

use App\Enums\Countries;
use App\Enums\Currencies;
use App\Enums\DivisionStatus;
use App\Enums\IDP\RoleDefaults;
use App\Enums\Languages;
use App\Models\Division;
use App\Models\Financer;
use App\Models\Integration;
use App\Models\Module;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MonizzeSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Creating Monizze test data...');

        // Get or create Global Team for users
        $globalTeam = Team::firstOr(fn () => Team::factory()->create());

        // 1. Create Monizze Division
        $division = Division::firstOrCreate(
            ['name' => 'Monizze'],
            [
                'id' => Str::uuid()->toString(),
                'remarks' => 'Division Monizze - Belgique',
                'country' => Countries::BELGIUM,
                'currency' => Currencies::EUR,
                'timezone' => 'Europe/Brussels',
                'language' => Languages::FRENCH_BELGIUM,
                'status' => DivisionStatus::ACTIVE,
            ]
        );

        $this->command->info("✓ Division created: {$division->name} (ID: {$division->id})");

        // 2. Create Monizze Financer
        $financerMonizze = Financer::firstOrCreate(
            [
                'name' => 'Monizze',
                'division_id' => $division->id,
            ],
            [
                'id' => Str::uuid()->toString(),
                'timezone' => 'Europe/Brussels',
                'registration_country' => Countries::BELGIUM,
                'available_languages' => [Languages::FRENCH_BELGIUM, Languages::DUTCH_BELGIUM],
                'status' => 'active',
                'company_number' => 'BE123456789',
            ]
        );

        $this->command->info("✓ Financer created: {$financerMonizze->name} (ID: {$financerMonizze->id})");

        // 3. Create ClientMonizze Financer
        $financerClientMonizze = Financer::firstOrCreate(
            [
                'name' => 'ClientMonizze',
                'division_id' => $division->id,
            ],
            [
                'id' => Str::uuid()->toString(),
                'timezone' => 'Europe/Brussels',
                'registration_country' => Countries::BELGIUM,
                'available_languages' => [Languages::FRENCH_BELGIUM, Languages::DUTCH_BELGIUM],
                'status' => 'active',
                'company_number' => 'BE987654321',
            ]
        );

        $this->command->info("✓ Financer created: {$financerClientMonizze->name} (ID: {$financerClientMonizze->id})");

        // 4. Create Division Super Admin User (super_admin@monizze.be)
        $divisionSuperAdmin = User::firstOrCreate(
            ['email' => 'super_admin@monizze.be'],
            [
                'id' => Str::uuid()->toString(),
                'team_id' => $globalTeam->id,
                'first_name' => 'Division Super',
                'last_name' => 'Admin',
                'locale' => Languages::FRENCH_BELGIUM,
                'currency' => Currencies::EUR,
                'timezone' => 'Europe/Brussels',
                'cognito_id' => Str::uuid()->toString(),
                'enabled' => true,
                'terms_confirmed' => true,
                'birthdate' => '1990-01-01',
            ]
        );

        if ($divisionSuperAdmin->wasRecentlyCreated) {
            $divisionSuperAdmin->financers()->attach($financerMonizze->id, [
                'active' => true,
                'role' => RoleDefaults::DIVISION_SUPER_ADMIN,
                'from' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->command->info("✓ User created: {$divisionSuperAdmin->email} with role division_super_admin");
        } else {
            $this->command->warn("⚠ User already exists: {$divisionSuperAdmin->email}");
        }

        // 5. Create Financer Super Admin User (admin@monizze.be)
        $financerSuperAdmin = User::firstOrCreate(
            ['email' => 'admin@monizze.be'],
            [
                'id' => Str::uuid()->toString(),
                'team_id' => $globalTeam->id,
                'first_name' => 'Financer Super',
                'last_name' => 'Admin',
                'locale' => Languages::FRENCH_BELGIUM,
                'currency' => Currencies::EUR,
                'timezone' => 'Europe/Brussels',
                'cognito_id' => Str::uuid()->toString(),
                'enabled' => true,
                'terms_confirmed' => true,
                'birthdate' => '1990-01-01',
            ]
        );

        if ($financerSuperAdmin->wasRecentlyCreated) {
            $financerSuperAdmin->financers()->attach($financerMonizze->id, [
                'active' => true,
                'role' => RoleDefaults::FINANCER_SUPER_ADMIN,
                'from' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->command->info("✓ User created: {$financerSuperAdmin->email} with role financer_super_admin");
        } else {
            $this->command->warn("⚠ User already exists: {$financerSuperAdmin->email}");
        }

        // 6. Create Beneficiary User (benef@client-monizze.be)
        $beneficiary = User::firstOrCreate(
            ['email' => 'benef@client-monizze.be'],
            [
                'id' => Str::uuid()->toString(),
                'team_id' => $globalTeam->id,
                'first_name' => 'Beneficiary',
                'last_name' => 'ClientMonizze',
                'locale' => Languages::FRENCH_BELGIUM,
                'currency' => Currencies::EUR,
                'timezone' => 'Europe/Brussels',
                'cognito_id' => Str::uuid()->toString(),
                'enabled' => true,
                'terms_confirmed' => true,
                'birthdate' => '1990-01-01',
            ]
        );

        if ($beneficiary->wasRecentlyCreated) {
            $beneficiary->financers()->attach($financerClientMonizze->id, [
                'active' => true,
                'role' => RoleDefaults::BENEFICIARY,
                'from' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->command->info("✓ User created: {$beneficiary->email} with role beneficiary");
        } else {
            $this->command->warn("⚠ User already exists: {$beneficiary->email}");
        }

        // Link modules to division and financers
        $this->linkModulesAndIntegrations($division, $financerMonizze, $financerClientMonizze);

        $this->command->newLine();
        $this->command->info('✓ Monizze test data created successfully!');
        $this->command->newLine();
        $this->command->info('Summary:');
        $this->command->info("  Division: {$division->name}");
        $this->command->info("  Financer 1: {$financerMonizze->name}");
        $this->command->info("  Financer 2: {$financerClientMonizze->name}");
        $this->command->info("  User 1: {$divisionSuperAdmin->email} (division_super_admin)");
        $this->command->info("  User 2: {$financerSuperAdmin->email} (financer_super_admin)");
        $this->command->info("  User 3: {$beneficiary->email} (beneficiary)");
    }

    private function linkModulesAndIntegrations(Division $division, Financer $financerMonizze, Financer $financerClientMonizze): void
    {
        $this->command->info('Linking modules and integrations...');

        $modules = Module::all();
        $integrations = Integration::all();

        if ($modules->isEmpty()) {
            $this->command->warn('⚠ No modules found. Run ModuleSeeder first.');

            return;
        }

        if ($integrations->isEmpty()) {
            $this->command->warn('⚠ No integrations found. Run IntegrationSeeder first.');

            return;
        }

        // Link modules to division
        $modules->each(function (Module $module) use ($division): void {
            if (! $division->modules()->wherePivot('module_id', $module->id)->exists()) {
                $division->modules()->attach($module->id, [
                    'active' => true,
                    'price_per_beneficiary' => $module->is_core ? null : 100,
                ]);
            }
        });

        // Link modules to financers
        foreach ([$financerMonizze, $financerClientMonizze] as $financer) {
            $modules->each(function (Module $module) use ($financer): void {
                if (! $financer->modules()->wherePivot('module_id', $module->id)->exists()) {
                    $financer->modules()->attach($module->id, [
                        'active' => true,
                        'price_per_beneficiary' => $module->is_core ? null : 200,
                    ]);
                }
            });

            // Link integrations to financers
            $integrations->each(function (Integration $integration) use ($financer): void {
                if (! $financer->integrations()->wherePivot('integration_id', $integration->id)->exists()) {
                    $financer->integrations()->attach($integration->id, ['active' => true]);
                }
            });
        }

        $this->command->info("✓ Linked {$modules->count()} modules to division");
        $this->command->info("✓ Linked {$modules->count()} modules to each financer");
        $this->command->info("✓ Linked {$integrations->count()} integrations to each financer");
    }
}
