<?php

namespace Database\Seeders;

use App\Enums\IDP\RoleDefaults;
use App\Models\ContractType;
use App\Models\Department;
use App\Models\Financer;
use App\Models\JobLevel;
use App\Models\JobTitle;
use App\Models\Site;
use App\Models\Tag;
use App\Models\User;
use App\Models\WorkMode;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $numberOfUsers = (int) $this->command->ask('Number of users to create', 500);
        $financerId = (string) $this->command->ask('Financer ID (leave blank to create users for all financers)');
        $shouldCreateAttribute = $this->command->ask('Should create attributes for the users? (true/false)', 'false');

        $financers = $financerId !== '' && $financerId !== '0' ? Financer::query()->where('id', $financerId)->get() : Financer::query()->get();

        $financers->each(function (Financer $financer) use ($numberOfUsers, $shouldCreateAttribute): void {
            $this->createUsers($numberOfUsers, $financer, $shouldCreateAttribute);
        });
    }

    private function createUsers(int $numberOfUsers, Financer $financer, bool $shouldCreateAttribute): void
    {
        $chunkSize = 100;
        $this->command->info("Creating {$numberOfUsers} demo users... for financer: {$financer->name}");

        User::unsetEventDispatcher();

        $totalChunks = (int) ceil($numberOfUsers / $chunkSize);
        $progressBar = $this->command->getOutput()->createProgressBar($totalChunks);
        $progressBar->start();

        $departments = Department::withoutGlobalScopes()->where('financer_id', $financer->id)->get();
        $sites = Site::withoutGlobalScopes()->where('financer_id', $financer->id)->get();
        $managers = User::query()->whereHas('financers', function ($query) use ($financer): void {
            $query->where('financer_id', $financer->id);
        })->limit(5)->get();
        $contractTypes = ContractType::withoutGlobalScopes()->where('financer_id', $financer->id)->get();
        $tags = Tag::withoutGlobalScopes()->where('financer_id', $financer->id)->get();
        $workModes = WorkMode::withoutGlobalScopes()->where('financer_id', $financer->id)->get();
        $jobTitles = JobTitle::withoutGlobalScopes()->where('financer_id', $financer->id)->get();
        $jobLevels = JobLevel::withoutGlobalScopes()->where('financer_id', $financer->id)->get();

        for ($chunk = 0; $chunk < $totalChunks; $chunk++) {
            $currentChunkSize = min($chunkSize, $numberOfUsers - ($chunk * $chunkSize));

            DB::transaction(function () use ($currentChunkSize, $chunk, $chunkSize, $financer, $departments, $sites, $managers, $contractTypes, $tags, $workModes, $jobTitles, $jobLevels, $shouldCreateAttribute): void {
                $users = User::factory()
                    ->count($currentChunkSize)
                    ->create()
                    ->each(function (User $user, $index) use ($chunk, $chunkSize): void {
                        $globalIndex = ($chunk * $chunkSize) + $index;
                        $user->update([
                            'first_name' => 'User '.$globalIndex,
                            'last_name' => 'Test '.$globalIndex,
                            'email' => 'demo-'.$globalIndex.'-'.fake()->uuid().'@test.com',
                        ]);
                    });

                if ($financer) {
                    $attachData = [];
                    foreach ($users as $user) {
                        $startedAt = rand(0, 1) !== 0 ? now()->subDays(rand(1, 365)) : null;
                        $attachData[$user->id] = [
                            'active' => true,
                            'role' => RoleDefaults::BENEFICIARY,
                            'started_at' => $startedAt,
                            'work_mode_id' => $workModes->random()->id,
                            'job_title_id' => $jobTitles->random()->id,
                            'job_level_id' => $jobLevels->random()->id,
                            'from' => now(),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                    $financer->users()->attach($attachData);
                }

                if ($shouldCreateAttribute === 'true') {
                    if ($departments->count() > 0) {
                        $departments->each(function (Department $department) use ($users): void {
                            $this->command->info("Attaching {$users->count()} users to department: {$department->name}");
                            $users->each(function (User $user) use ($department): void {
                                $user->departments()->attach($department->id);
                            });
                        });
                    }

                    if ($sites->count() > 0) {
                        $sites->each(function (Site $site) use ($users): void {
                            $this->command->info("Attaching {$users->count()} users to site: {$site->name}");
                            $users->each(function (User $user) use ($site): void {
                                $user->sites()->attach($site->id);
                            });
                        });
                    }

                    if ($managers->count() > 0) {
                        $managers->each(function (User $manager) use ($users): void {
                            $this->command->info("Attaching {$users->count()} users to manager: {$manager->first_name} {$manager->last_name}");
                            $users->each(function (User $user) use ($manager): void {
                                $user->managers()->attach($manager->id);
                            });
                        });
                    }

                    if ($contractTypes->count() > 0) {
                        $contractTypes->each(function (ContractType $contractType) use ($users): void {
                            $this->command->info("Attaching {$users->count()} users to contract type: {$contractType->name}");
                            $users->each(function (User $user) use ($contractType): void {
                                $user->contractTypes()->attach($contractType->id);
                            });
                        });
                    }

                    if ($tags->count() > 0) {
                        $tags->each(function (Tag $tag) use ($users): void {
                            $this->command->info("Attaching {$users->count()} users to tag: {$tag->name}");
                            $users->each(function (User $user) use ($tag): void {
                                $user->tags()->attach($tag->id);
                            });
                        });
                    }
                }
            });

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->command->newLine();
        $this->command->info("Successfully created {$numberOfUsers} demo users!");

        if ($financer) {
            $this->command->info("All users have been associated with financer: {$financer->name}");
        }
    }
}
