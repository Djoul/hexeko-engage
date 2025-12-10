<?php

namespace App\Console\Commands;

use App\Models\Financer;
use App\Models\FinancerUser;
use App\Models\User;
use Illuminate\Console\Command;

class AnalyzeUserFinancerRelationships extends Command
{
    protected $signature = 'users:analyze-financers';

    protected $description = 'Analyze user-financer relationships and detect inconsistencies';

    /**
     * @var array{
     *     total_users: int,
     *     users_without_financer: array<int, array<string, mixed>>,
     *     users_with_multiple_financers: array<int, array<string, mixed>>,
     *     language_inconsistencies: array<int, array<string, mixed>>,
     *     users_missing_language: array<int, array<string, mixed>>,
     *     summary: array<string, mixed>
     * }
     */
    private array $report = [
        'total_users' => 0,
        'users_without_financer' => [],
        'users_with_multiple_financers' => [],
        'language_inconsistencies' => [],
        'users_missing_language' => [],
        'summary' => [],
    ];

    public function handle(): int
    {
        $this->info('🔍 Starting User-Financer Relationship Analysis...');
        $this->newLine();

        // Get all users with their financers
        $users = User::with(['financers' => function ($query): void {
            $query->withPivot(['role', 'active', 'sirh_id', 'from', 'to']);
        }])->get();

        $this->report['total_users'] = $users->count();
        $progressBar = $this->output->createProgressBar($users->count());
        $progressBar->start();

        foreach ($users as $user) {
            $this->analyzeUser($user);
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->generateReport();

        return Command::SUCCESS;
    }

    private function analyzeUser(User $user): void
    {
        // Check 1: Users without financers
        if ($user->financers->isEmpty()) {
            /** @var array<string, mixed> */
            $userWithoutFinancer = [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->first_name.' '.$user->last_name,
                'created_at' => $user->created_at,
            ];
            $this->report['users_without_financer'][] = $userWithoutFinancer;

            return;
        }

        // Check 2: Users with multiple financers
        if ($user->financers->count() > 1) {
            /** @var array<string, mixed> */
            $userWithMultipleFinancers = [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->first_name.' '.$user->last_name,
                'financer_count' => $user->financers->count(),
                'financers' => $user->financers->map(function ($financer): array {
                    /** @var FinancerUser|null */
                    $pivot = $financer->pivot ?? null;

                    return [
                        'id' => $financer->id,
                        'name' => $financer->name,
                        'active' => $pivot && $pivot->active,
                        'role' => $pivot ? $pivot->role : '',
                    ];
                })->toArray(),
            ];
            $this->report['users_with_multiple_financers'][] = $userWithMultipleFinancers;
        }

        // Check 3: Language coherence
        $this->checkLanguageCoherence($user);
    }

    private function checkLanguageCoherence(User $user): void
    {
        // Check if user has a language set
        if (empty($user->locale)) {
            /** @var array<string, mixed> */
            $userMissingLang = [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->first_name.' '.$user->last_name,
            ];
            $this->report['users_missing_language'][] = $userMissingLang;

            return;
        }

        // Get all available languages from user's financers
        $availableLanguages = [];
        foreach ($user->financers as $financer) {
            // Get financer's available languages
            $financerLanguages = $this->getFinancerAvailableLanguages($financer);
            $availableLanguages = array_merge($availableLanguages, $financerLanguages);
        }

        $availableLanguages = array_unique($availableLanguages);

        // Extract language code from locale (e.g., fr-FR -> fr, en-US -> en)
        $userLang = strtolower(substr($user->locale, 0, 2));

        // Check if user's language is in any financer's available languages
        $languageFound = false;
        foreach ($availableLanguages as $lang) {
            if (strpos(strtolower($lang), $userLang) === 0) {
                $languageFound = true;
                break;
            }
        }

        if (! $languageFound && $availableLanguages !== []) {
            /** @var array<string, mixed> */
            $langInconsistency = [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->first_name.' '.$user->last_name,
                'user_locale' => $user->locale,
                'financer_languages' => $availableLanguages,
                'financers' => $user->financers->pluck('name')->toArray(),
            ];
            $this->report['language_inconsistencies'][] = $langInconsistency;
        }
    }

    /**
     * @return array<int, string>
     */
    private function getFinancerAvailableLanguages(Financer $financer): array
    {
        // Check if financer has available_languages field
        if (! empty($financer->available_languages)) {
            if (is_array($financer->available_languages)) {
                /** @var array<int, string> */
                $result = array_values(array_filter($financer->available_languages, 'is_string'));

                return $result;
            }
            if (is_string($financer->available_languages)) {
                $decoded = json_decode($financer->available_languages, true);
                if (is_array($decoded)) {
                    /** @var array<int, string> */
                    $result = array_values(array_filter($decoded, 'is_string'));

                    return $result;
                }

                return [];
            }
        }

        // Fallback: Get language from division if financer doesn't have languages
        if ($financer->division) {
            $divisionLang = $financer->division->language ?? null;
            if ($divisionLang) {
                return [$divisionLang];
            }
        }

        // Default fallback based on country
        $countryLanguageMap = [
            'FR' => ['fr-FR'],
            'BE' => ['fr-BE', 'nl-BE'],
            'PT' => ['pt-PT'],
            'ES' => ['es-ES'],
            'IT' => ['it-IT'],
            'DE' => ['de-DE'],
            'GB' => ['en-GB'],
            'US' => ['en-US'],
        ];

        $country = $financer->registration_country ?? ($financer->division->country ?? null);

        return $countryLanguageMap[$country] ?? ['en-US'];
    }

    private function generateReport(): void
    {
        $this->info('═══════════════════════════════════════════════════════════════');
        $this->info('                    ANALYSIS REPORT');
        $this->info('═══════════════════════════════════════════════════════════════');
        $this->newLine();

        // Summary
        $this->info('📊 SUMMARY');
        $this->info('─────────────────────────────────────────────');
        $this->table(
            ['Metric', 'Count', 'Percentage'],
            [
                ['Total Users', $this->report['total_users'], '100%'],
                ['Users without Financer', count((array) $this->report['users_without_financer']), $this->calculatePercentage(count((array) $this->report['users_without_financer']))],
                ['Users with Multiple Financers', count((array) $this->report['users_with_multiple_financers']), $this->calculatePercentage(count((array) $this->report['users_with_multiple_financers']))],
                ['Users with Language Issues', count((array) $this->report['language_inconsistencies']), $this->calculatePercentage(count((array) $this->report['language_inconsistencies']))],
                ['Users without Language Set', count((array) $this->report['users_missing_language']), $this->calculatePercentage(count((array) $this->report['users_missing_language']))],
            ]
        );

        // Users without financers
        if (! empty($this->report['users_without_financer'])) {
            $this->newLine();
            $usersWithoutFinancer = (array) $this->report['users_without_financer'];
            $this->error('❌ USERS WITHOUT FINANCER ('.count($usersWithoutFinancer).')');
            $this->info('─────────────────────────────────────────────');
            foreach (array_slice($usersWithoutFinancer, 0, 10) as $user) {
                if (! is_array($user)) {
                    continue;
                }
                $email = (array_key_exists('email', $user) && (is_string($user['email']) || is_numeric($user['email']))) ? (string) $user['email'] : '';
                $id = (array_key_exists('id', $user) && (is_string($user['id']) || is_numeric($user['id']))) ? (string) $user['id'] : '';
                $this->warn("  • {$email} (ID: {$id})");
            }
            if (count($usersWithoutFinancer) > 10) {
                $this->info('  ... and '.(count($usersWithoutFinancer) - 10).' more');
            }
        }

        // Users with multiple financers
        if (! empty($this->report['users_with_multiple_financers'])) {
            $this->newLine();
            $usersWithMultiple = (array) $this->report['users_with_multiple_financers'];
            $this->info('👥 USERS WITH MULTIPLE FINANCERS ('.count($usersWithMultiple).')');
            $this->info('─────────────────────────────────────────────');
            foreach (array_slice($usersWithMultiple, 0, 5) as $user) {
                if (! is_array($user)) {
                    continue;
                }
                $email = (array_key_exists('email', $user) && (is_string($user['email']) || is_numeric($user['email']))) ? (string) $user['email'] : '';
                $financerCount = (array_key_exists('financer_count', $user) && (is_string($user['financer_count']) || is_numeric($user['financer_count']))) ? (string) $user['financer_count'] : '0';
                $this->warn("  • {$email} - {$financerCount} financers");
                $financers = array_key_exists('financers', $user) && is_array($user['financers']) ? $user['financers'] : [];
                foreach ($financers as $financer) {
                    if (! is_array($financer)) {
                        continue;
                    }
                    $active = array_key_exists('active', $financer) && $financer['active'] ? '✓' : '✗';
                    $role = array_key_exists('role', $financer) ? (string) $financer['role'] : '';
                    $name = array_key_exists('name', $financer) ? (string) $financer['name'] : '';
                    $this->line("    └─ {$name} [{$active}] Role: {$role}");
                }
            }
            if (count($usersWithMultiple) > 5) {
                $this->info('  ... and '.(count($usersWithMultiple) - 5).' more');
            }
        }

        // Language inconsistencies
        if (! empty($this->report['language_inconsistencies'])) {
            $this->newLine();
            $langInconsistencies = (array) $this->report['language_inconsistencies'];
            $this->error('🌍 LANGUAGE INCONSISTENCIES ('.count($langInconsistencies).')');
            $this->info('─────────────────────────────────────────────');
            foreach (array_slice($langInconsistencies, 0, 5) as $user) {
                if (! is_array($user)) {
                    continue;
                }
                $email = (array_key_exists('email', $user) && (is_string($user['email']) || is_numeric($user['email']))) ? (string) $user['email'] : '';
                $userLocale = (array_key_exists('user_locale', $user) && (is_string($user['user_locale']) || is_numeric($user['user_locale']))) ? (string) $user['user_locale'] : '';
                $this->warn("  • {$email}");
                $this->line("    User locale: {$userLocale}");
                $financerLangs = array_key_exists('financer_languages', $user) && is_array($user['financer_languages']) ? $user['financer_languages'] : [];
                $this->line('    Financer languages: '.implode(', ', $financerLangs));
                $financerNames = array_key_exists('financers', $user) && is_array($user['financers']) ? $user['financers'] : [];
                $this->line('    Financers: '.implode(', ', $financerNames));
            }
            if (count($langInconsistencies) > 5) {
                $this->info('  ... and '.(count($langInconsistencies) - 5).' more');
            }
        }

        // Users without language
        if (! empty($this->report['users_missing_language'])) {
            $this->newLine();
            $usersMissingLang = (array) $this->report['users_missing_language'];
            $this->warn('⚠️ USERS WITHOUT LANGUAGE SET ('.count($usersMissingLang).')');
            $this->info('─────────────────────────────────────────────');
            foreach (array_slice($usersMissingLang, 0, 10) as $user) {
                if (! is_array($user)) {
                    continue;
                }
                $email = (array_key_exists('email', $user) && (is_string($user['email']) || is_numeric($user['email']))) ? (string) $user['email'] : '';
                $id = (array_key_exists('id', $user) && (is_string($user['id']) || is_numeric($user['id']))) ? (string) $user['id'] : '';
                $this->line("  • {$email} (ID: {$id})");
            }
            if (count($usersMissingLang) > 10) {
                $this->info('  ... and '.(count($usersMissingLang) - 10).' more');
            }
        }

        // Export option
        $this->newLine();
        if ($this->confirm('Would you like to export the full report to a JSON file?')) {
            $filename = 'user_financer_analysis_'.date('Y-m-d_His').'.json';
            $path = storage_path('app/reports/'.$filename);

            if (! is_dir(storage_path('app/reports'))) {
                mkdir(storage_path('app/reports'), 0755, true);
            }

            file_put_contents($path, json_encode($this->report, JSON_PRETTY_PRINT));
            $this->info("✅ Report exported to: {$path}");
        }

        $this->newLine();
        $this->info('═══════════════════════════════════════════════════════════════');
    }

    private function calculatePercentage(int $count): string
    {
        $totalUsers = is_int($this->report['total_users']) ? $this->report['total_users'] : 0;
        if ($totalUsers === 0) {
            return '0%';
        }

        return round(($count / $totalUsers) * 100, 2).'%';
    }
}
