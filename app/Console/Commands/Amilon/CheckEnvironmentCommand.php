<?php

namespace App\Console\Commands\Amilon;

use Illuminate\Console\Command;

class CheckEnvironmentCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'amilon:check-env';

    /**
     * The console command description.
     */
    protected $description = 'Check Amilon environment variables configuration (masked for security)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('=== Amilon Environment Variables Check ===');
        $this->newLine();

        // Check each required environment variable
        $checks = [
            'AMILON_CLIENT_ID' => config('services.amilon.client_id'),
            'AMILON_CLIENT_SECRET' => config('services.amilon.client_secret'),
            'AMILON_USERNAME' => config('services.amilon.username'),
            'AMILON_PASSWORD' => config('services.amilon.password'),
            'AMILON_TOKEN_URL' => config('services.amilon.token_url'),
            'AMILON_API_URL' => config('services.amilon.api_url'),
            'AMILON_CONTRAT_ID' => config('services.amilon.contrat_id'),
        ];

        $allValid = true;

        foreach ($checks as $name => $value) {
            $this->checkVariable($name, $value, $allValid);
        }

        $this->newLine();
        $this->info('Environment: '.app()->environment());
        $this->newLine();

        if ($allValid) {
            $this->info('✅ All environment variables are configured correctly');

            return self::SUCCESS;
        }

        $this->error('❌ Some environment variables are missing or invalid');

        return self::FAILURE;
    }

    /**
     * Check a single environment variable.
     */
    private function checkVariable(string $name, mixed $value, bool &$allValid): void
    {
        if (empty($value) || ! is_string($value)) {
            $this->error("❌ {$name}: NOT SET");
            $allValid = false;

            return;
        }

        // Check for whitespace issues
        $hasLeadingWhitespace = $value !== ltrim($value);
        $hasTrailingWhitespace = $value !== rtrim($value);
        $hasWhitespace = $hasLeadingWhitespace || $hasTrailingWhitespace;

        // Mask sensitive values
        $masked = $this->maskValue($name, $value);
        $length = strlen($value);

        $status = "✓ {$name}: [SET] Length: {$length}, Preview: {$masked}";

        if ($hasWhitespace) {
            $this->warn($status);
            $this->warn('  ⚠️  WARNING: Value contains leading or trailing whitespace!');
            if ($hasLeadingWhitespace) {
                $this->warn('  → Leading whitespace detected');
            }
            if ($hasTrailingWhitespace) {
                $this->warn('  → Trailing whitespace detected');
            }
            $allValid = false;
        } else {
            $this->line($status);
        }
    }

    /**
     * Mask sensitive values for display.
     */
    private function maskValue(string $name, string $value): string
    {
        // Don't mask URLs
        if (str_ends_with($name, '_URL') || str_ends_with($name, '_ID')) {
            return $value;
        }

        // For sensitive credentials, show only first few characters
        if (strlen($value) <= 3) {
            return '***';
        }

        if ($name === 'AMILON_USERNAME') {
            // Show first 3 characters of username
            return substr($value, 0, 3).str_repeat('*', min(strlen($value) - 3, 10));
        }

        // For other secrets, show first 4 characters
        return substr($value, 0, 4).str_repeat('*', min(strlen($value) - 4, 10));
    }
}
