<?php

declare(strict_types=1);

namespace App\Console\Commands\Integrations\Wellbeing\WellWo;

use App\Integrations\Wellbeing\WellWo\Actions\AnalyzeContentAvailabilityAction;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

class AnalyzeContentAvailabilityCommand extends Command
{
    protected $signature = 'wellwo:analyze-content
                            {--language=* : Language codes to analyze (es, en, fr, it, pt, ca, mx)}
                            {--dry-run : Run analysis without saving results}
                            {--force : Force analysis even if recent data exists}';

    protected $description = 'Analyze WellWo content availability per language and save results to S3';

    public function __construct(
        private readonly AnalyzeContentAvailabilityAction $action
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $languages = $this->option('language');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        $verbose = $this->option('verbose') ?? false;

        // Get supported languages from config
        $supportedLanguages = Config::get('services.wellwo.supported_languages', []);

        // Validate languages
        foreach ($languages as $language) {
            if (! in_array($language, $supportedLanguages)) {
                $this->error("Invalid language code: {$language}");
                $this->info('Supported languages: '.implode(', ', $supportedLanguages));

                return self::FAILURE;
            }
        }

        // Start analysis
        $prefix = $dryRun ? '[DRY RUN] ' : '';
        $this->info($prefix.'Starting WellWo content availability analysis...');

        if ($force) {
            $this->info('[FORCED] Analyzing even if recent data exists');
        }

        $targetLanguages = empty($languages) ? $supportedLanguages : $languages;

        if ($verbose) {
            $this->info('Analyzing '.count($targetLanguages).' languages: '.implode(', ', $targetLanguages));
        }

        // Progress bar for multiple languages
        $progress = null;
        if (count($targetLanguages) > 1 && ! $verbose) {
            $progress = $this->output->createProgressBar(count($targetLanguages));
            $progress->start();
        }

        try {
            // Execute analysis
            $results = $this->action->execute($targetLanguages, $dryRun, $force);

            // Process results
            $successCount = 0;
            $failureCount = 0;
            $totalItemsAnalyzed = 0;
            $totalItemsAvailable = 0;

            foreach ($results as $result) {
                if ($progress) {
                    $progress->advance();
                }

                if ($verbose) {
                    $this->newLine();
                    $this->info("Analyzing language: {$result->language}");

                    if ($result->success) {
                        $this->info('  ✓ Analysis successful');
                        $this->info("  ✓ Items analyzed: {$result->itemsAnalyzed}");
                        $this->info("  ✓ Items available: {$result->itemsAvailable}");
                        $percentage = $result->itemsAnalyzed > 0
                            ? round(($result->itemsAvailable / $result->itemsAnalyzed) * 100, 1)
                            : 0;
                        $this->info("  ✓ Availability: {$percentage}%");
                        $this->info("  Duration: {$result->duration}s");
                    } else {
                        $this->error("  ✗ Analysis failed: {$result->error}");
                    }
                }

                if ($result->success) {
                    $successCount++;
                    $totalItemsAnalyzed += $result->itemsAnalyzed;
                    $totalItemsAvailable += $result->itemsAvailable;
                } else {
                    $failureCount++;
                }
            }

            if ($progress) {
                $progress->finish();
                $this->newLine();
            }

            // Display summary
            $this->newLine();
            $this->info('Summary:');
            $this->info("  ✓ Successful: {$successCount}");
            if ($failureCount > 0) {
                $this->warn("  ✗ Failed: {$failureCount}");
            }
            $this->info("  Total items analyzed: {$totalItemsAnalyzed}");
            $this->info("  Total items available: {$totalItemsAvailable}");

            if (! $verbose && $failureCount > 0) {
                $this->newLine();
                $this->info('Failed languages:');
                foreach ($results as $result) {
                    if (! $result->success) {
                        $this->error("  {$result->language}: ✗ Failed - {$result->error}");
                    }
                }
            }

            if (! $verbose && $successCount > 0) {
                $this->newLine();
                $this->info('Successful languages:');
                foreach ($results as $result) {
                    if ($result->success) {
                        $percentage = $result->itemsAnalyzed > 0
                            ? round(($result->itemsAvailable / $result->itemsAnalyzed) * 100, 1)
                            : 0;
                        $this->info("  {$result->language}: ✓ Success - {$result->itemsAvailable} of {$result->itemsAnalyzed} items ({$percentage}%)");
                    }
                }
            }

            // Final message
            $this->newLine();
            if ($dryRun) {
                $this->info($prefix.'Analysis complete! No files were saved.');
            } else {
                $this->info('Analysis complete! Results saved for '.$successCount.' languages.');
            }
            // Return appropriate exit code
            if ($failureCount > 0 && $successCount === 0) {
                return self::FAILURE;
            }

            // Return appropriate exit code
            if ($failureCount > 0) {
                return 1;
                // Warning - partial failure
            }

            return self::SUCCESS;

        } catch (Exception $e) {
            $this->error('Error: '.$e->getMessage());

            if ($verbose) {
                $this->error('Stack trace:');
                $this->error($e->getTraceAsString());
            }

            return self::FAILURE;
        }
    }
}
