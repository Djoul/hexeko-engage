<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use stdClass;

class ValidateLanguageSynchronization extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'language:validate-sync
                            {--fix : Automatically fix mismatched languages}
                            {--report : Generate detailed report}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Validate that user.locale is synchronized with primary financer language';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔍 Starting language synchronization validation...');

        $mismatches = $this->findMismatches();

        if ($mismatches->isEmpty()) {
            $this->info('✅ All users have synchronized languages!');

            return self::SUCCESS;
        }

        $this->warn("⚠️  Found {$mismatches->count()} users with mismatched languages");

        if ($this->option('report')) {
            $this->generateReport($mismatches);
        }

        if ($this->option('fix')) {
            $this->fixMismatches($mismatches);
        } else {
            $this->table(
                ['User ID', 'Email', 'Current Locale', 'Expected Language', 'Primary Financer'],
                $mismatches->map(function ($user): array {
                    return [
                        $user->user_id,
                        $user->email,
                        $user->user_locale ?? 'null',
                        $user->primary_language ?? 'null',
                        $user->financer_name ?? 'N/A',
                    ];
                })->toArray()
            );

            $this->info('Run with --fix option to automatically synchronize');
        }

        return self::SUCCESS;
    }

    /**
     * Find users where locale doesn't match their primary financer's language.
     *
     * @return Collection<int, stdClass>
     */
    private function findMismatches(): Collection
    {
        return DB::table('users as u')
            ->leftJoinSub(
                // Subquery to get primary financer for each user
                DB::table('financer_user as fu1')
                    ->select([
                        'fu1.user_id',
                        'fu1.language as primary_language',
                        'f.name as financer_name',
                        'fu1.financer_id',
                    ])
                    ->join('financers as f', 'fu1.financer_id', '=', 'f.id')
                    ->where('fu1.active', true)
                    ->whereRaw('fu1."from" = (
                        SELECT MIN(fu2."from")
                        FROM financer_user fu2
                        WHERE fu2.user_id = fu1.user_id
                        AND fu2.active = true
                    )'),
                'primary_financer',
                'u.id',
                '=',
                'primary_financer.user_id'
            )
            ->select([
                'u.id as user_id',
                'u.email',
                'u.locale as user_locale',
                'primary_financer.primary_language',
                'primary_financer.financer_name',
                'primary_financer.financer_id',
            ])
            ->whereRaw('COALESCE(u.locale, \'\') != COALESCE(primary_financer.primary_language, \'\')')
            ->orWhere(function ($query): void {
                // Include users with active financers but null language
                $query->whereNotNull('primary_financer.financer_id')
                    ->whereNull('primary_financer.primary_language');
            })
            ->get();
    }

    /**
     * Generate detailed report of mismatches.
     *
     * @param  Collection<int, stdClass>  $mismatches
     */
    private function generateReport(Collection $mismatches): void
    {
        $reportPath = storage_path('logs/language-sync-report-'.now()->format('Y-m-d-His').'.json');

        $report = [
            'timestamp' => now()->toIso8601String(),
            'total_mismatches' => $mismatches->count(),
            'details' => $mismatches->map(function (stdClass $user): array {
                return [
                    'user_id' => $user->user_id,
                    'email' => $user->email,
                    'current_locale' => $user->user_locale,
                    'expected_language' => $user->primary_language,
                    'primary_financer' => $user->financer_name,
                    'action_needed' => $this->determineAction($user),
                ];
            })->toArray(),
            'summary' => [
                'null_locales' => $mismatches->whereNull('user_locale')->count(),
                'null_languages' => $mismatches->whereNull('primary_language')->count(),
                'actual_mismatches' => $mismatches
                    ->whereNotNull('user_locale')
                    ->whereNotNull('primary_language')
                    ->count(),
            ],
        ];

        file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT));
        $this->info("📊 Report saved to: {$reportPath}");

        // Log summary
        Log::channel('daily')->info('Language synchronization validation report', [
            'total_mismatches' => $report['total_mismatches'],
            'summary' => $report['summary'],
        ]);
    }

    /**
     * Fix language mismatches.
     *
     * @param  Collection<int, stdClass>  $mismatches
     */
    private function fixMismatches(Collection $mismatches): void
    {
        $this->info('🔧 Fixing language mismatches...');

        $bar = $this->output->createProgressBar($mismatches->count());
        $bar->start();

        $fixed = 0;
        $failed = 0;

        foreach ($mismatches as $mismatch) {
            try {
                if ($mismatch->primary_language) {
                    // Update user locale to match primary financer
                    DB::table('users')
                        ->where('id', $mismatch->user_id)
                        ->update([
                            'locale' => $mismatch->primary_language,
                            'updated_at' => now(),
                        ]);
                    Log::info('Fixed language mismatch', [
                        'user_id' => $mismatch->user_id,
                        'old_locale' => $mismatch->user_locale,
                        'new_locale' => $mismatch->primary_language,
                    ]);
                    $fixed++;
                } elseif ($mismatch->user_locale && $mismatch->financer_id) {
                    // Set financer language from user locale
                    DB::table('financer_user')
                        ->where('user_id', $mismatch->user_id)
                        ->where('financer_id', $mismatch->financer_id)
                        ->where('active', true)
                        ->update([
                            'language' => $mismatch->user_locale,
                            'updated_at' => now(),
                        ]);
                    Log::info('Set financer language from user locale', [
                        'user_id' => $mismatch->user_id,
                        'financer_id' => $mismatch->financer_id,
                        'language' => $mismatch->user_locale,
                    ]);
                    $fixed++;
                }
            } catch (Exception $e) {
                Log::error('Failed to fix language mismatch', [
                    'user_id' => $mismatch->user_id,
                    'error' => $e->getMessage(),
                ]);
                $failed++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info("✅ Fixed {$fixed} mismatches");
        if ($failed > 0) {
            $this->error("❌ Failed to fix {$failed} mismatches");
        }
    }

    /**
     * Determine what action is needed for a mismatch.
     */
    private function determineAction(stdClass $user): string
    {
        if (! $user->primary_language) {
            return 'Set financer language from user locale';
        }

        if (! $user->user_locale) {
            return 'Set user locale from financer language';
        }

        return 'Update user locale to match financer language';
    }
}
