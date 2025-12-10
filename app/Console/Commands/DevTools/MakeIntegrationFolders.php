<?php

namespace App\Console\Commands\DevTools;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class MakeIntegrationFolders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:integration {name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new integration structure in app/Integrations/{name}';

    /**
     * Filesystem instance.
     */
    protected Filesystem $files;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = $this->argument('name');
        $basePath = app_path("Integrations/{$name}");

        $directories = [
            'Actions',
            'Database/migrations',
            'Database/factories',
            'Http/Controllers',
            'Http/Resources',
            'Routes',
            'Models',
            'Services',
            'Tests',
        ];

        foreach ($directories as $dir) {
            $path = "$basePath/$dir";
            if (! $this->files->isDirectory($path)) {
                $this->files->makeDirectory($path, 0755, true);
                $this->info("Created: $path");
            } else {
                $this->warn("Already exists: $path");
            }
        }

        return Command::SUCCESS;
    }
}
