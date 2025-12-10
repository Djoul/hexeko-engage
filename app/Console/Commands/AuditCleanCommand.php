<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OwenIt\Auditing\Models\Audit;

class AuditCleanCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audit:clean {--days=90 : Number of days to keep audit records} {--model= : Specific model to clean audits for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old audit records';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $model = $this->option('model');

        if ($days <= 0) {
            $this->error('The number of days must be greater than 0.');

            return 1;
        }

        $cutoffDate = now()->subDays($days)->toDateTimeString();
        $query = Audit::where('created_at', '<', $cutoffDate);

        if ($model) {
            $modelClass = 'App\\Models\\'.$model;
            if (! class_exists($modelClass)) {
                $this->error("Model {$model} not found!");

                return 1;
            }
            $query->where('auditable_type', $modelClass);
            $this->info("Cleaning audit records older than {$days} days for model {$model}...");
        } else {
            $this->info("Cleaning all audit records older than {$days} days...");
        }

        $count = $query->count();

        if ($count === 0) {
            $this->info('No audit records to clean.');

            return 0;
        }

        // Confirm deletion if not in test environment
        if (! app()->environment('testing') && ! $this->confirm("This will delete {$count} audit records. Continue?")) {
            $this->info('Operation cancelled.');

            return 0;
        }

        // Delete in chunks to avoid memory issues
        $deleted = 0;
        $query->chunkById(1000, function ($audits) use (&$deleted): void {
            foreach ($audits as $audit) {
                $audit->delete();
                $deleted++;
            }
            $this->output->write('.');
        });

        $this->newLine();
        $this->info("Successfully deleted {$deleted} audit records.");

        return 0;
    }
}
