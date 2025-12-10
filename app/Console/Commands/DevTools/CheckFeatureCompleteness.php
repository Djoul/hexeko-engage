<?php

namespace App\Console\Commands\DevTools;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use SimpleXMLElement;
use Symfony\Component\Finder\Finder;

class CheckFeatureCompleteness extends Command
{
    protected $signature = 'check:feature {--path= : Optional path to limit the check to a namespace}';

    protected $description = 'Check if a feature implementation is complete and production-ready';

    public function handle(): int
    {
        $basePath = base_path('app');
        $targetPath = $this->option('path') ? base_path($this->option('path')) : $basePath;

        if (! is_dir($targetPath)) {
            $this->error("Invalid path: {$targetPath}");

            return self::FAILURE;
        }

        $this->info('🧪 Starting feature completeness check...');
        $this->line('Target: '.($this->option('path') ?: 'Entire app'));

        $this->checkForDebugStatements($targetPath);
        $this->checkForMissingFormRequests($targetPath);
        $this->checkTestCoverage();
        $this->checkPhpStan();
        $this->checkPint();

        $this->info('✅ Feature check complete.');

        return self::SUCCESS;
    }

    protected function checkForDebugStatements(string $path): void
    {
        $this->info('🔍 Checking for debug statements...');

        $patterns = [
            '\bdd\s*\(',
            '\bdump\s*\(',
            '\bvar_dump\s*\(',
            '\bray\s*\(',
        ];

        $matches = [];

        foreach ($patterns as $pattern) {
            // Use grep with extended regex (-E), recursive (-r), with line numbers (-n)
            exec("grep -rEn \"$pattern\" $path", $lines);
            $matches = array_merge($matches, $lines);
        }

        if (count($matches) > 0) {
            $this->error('❌ Debug statements found:');
            foreach ($matches as $line) {
                $this->line("  $line");
            }
        } else {
            $this->info('✅ No debug statements found.');
        }
    }

    protected function checkForMissingFormRequests(string $path): void
    {
        $this->info('📋 Checking for missing FormRequest in controllers...');
        $finder = new Finder;
        $finder->files()->in($path)->name('*Controller.php');

        $missing = [];

        foreach ($finder as $file) {
            $content = $file->getContents();
            if (preg_match_all('/function\s+(store|update)\s*\([^\)]*\)/', $content, $matches, PREG_OFFSET_CAPTURE) && ! Str::contains($content, 'FormRequest')) {
                $missing[] = $file->getRealPath();
            }
        }

        if (count($missing) !== 0) {
            $this->warn('⚠️ Some controller methods may lack FormRequest validation:');
            foreach ($missing as $file) {
                $this->line("  $file");
            }
        } else {
            $this->info('✅ All controllers use FormRequest.');
        }
    }

    protected function checkTestCoverage(): void
    {
        $this->info('📊 Checking PHPUnit test coverage (≥ 80%)...');
        $coverageFile = base_path('coverage.xml');

        exec('./vendor/bin/phpunit --coverage-clover=coverage.xml', $output, $exitCode);

        if (! file_exists($coverageFile)) {
            $this->error('❌ Could not generate coverage report.');

            return;
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_file($coverageFile);

        if ($xml === false) {
            $this->error('❌ Failed to parse coverage XML.');
            foreach (libxml_get_errors() as $error) {
                $this->line('  '.trim($error->message));
            }
            libxml_clear_errors();
            unlink($coverageFile);

            return;
        }

        /** @var SimpleXMLElement $xml */
        $lineRate = $xml->attributes()->{'line-rate'} ?? null;

        if ($lineRate === null) {
            $this->error('❌ line-rate attribute not found in coverage report.');
            unlink($coverageFile);

            return;
        }

        $percentage = (float) $lineRate * 100;

        if ($percentage < 80) {
            $this->error("❌ Test coverage too low: {$percentage}%");
        } else {
            $this->info("✅ Coverage OK: {$percentage}%");
        }

        unlink($coverageFile);
    }

    protected function checkPhpStan(): void
    {
        $this->info('📦 Running PHPStan...');
        exec('./vendor/bin/phpstan analyse --no-progress --error-format=table', $output, $exitCode);
        if ($exitCode !== 0) {
            $this->error('❌ PHPStan errors found:');
            foreach ($output as $line) {
                $this->line("  $line");
            }
        } else {
            $this->info('✅ PHPStan passed at max level.');
        }
    }

    protected function checkPint(): void
    {
        $this->info('🎨 Running Laravel Pint...');
        exec('./vendor/bin/pint --test', $output, $exitCode);
        if ($exitCode !== 0) {
            $this->error('❌ Pint found formatting issues:');
            foreach ($output as $line) {
                $this->line("  $line");
            }
        } else {
            $this->info('✅ Pint formatting OK.');
        }
    }
}
