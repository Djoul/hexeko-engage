<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use OwenIt\Auditing\Contracts\Auditable;
use Symfony\Component\Finder\SplFileInfo;

class MakeAuditableCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:auditable
                            {--model= : Specific model to make auditable}
                            {--integration= : Specific integration (e.g., InternalCommunication, HRTools)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Make Eloquent models auditable by adding the AuditableModel trait';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $specificModel = $this->option('model');
        $integration = $this->option('integration');

        if ($specificModel) {
            if ($integration) {
                $modelPath = app_path("Integrations/{$integration}/Models/{$specificModel}.php");
                if (File::exists($modelPath)) {
                    $this->processModel($modelPath);
                } else {
                    $this->error("Model {$specificModel} not found in integration {$integration}!");

                    return 1;
                }
            } else {
                $modelPath = app_path('Models/'.$specificModel.'.php');
                if (File::exists($modelPath)) {
                    $this->processModel($modelPath);
                } else {
                    $this->error("Model {$specificModel} not found!");

                    return 1;
                }
            }
        } else {
            $this->processAllModels();
        }

        return 0;
    }

    /**
     * Process all models in the Models directory and Integrations directories.
     */
    protected function processAllModels(): void
    {
        $totalModifiedCount = 0;

        // Process main models
        $this->info('Processing models in app/Models...');
        $mainModelFiles = $this->getModelFilesFromDirectory(app_path('Models'));
        $totalModifiedCount += $this->processModelsInDirectory($mainModelFiles);

        // Process integration models
        $integrationDirs = File::directories(app_path('Integrations'));
        foreach ($integrationDirs as $integrationDir) {
            $integrationName = basename($integrationDir);
            $modelsDir = $integrationDir.'/Models';

            if (File::isDirectory($modelsDir)) {
                $this->info("Processing models in app/Integrations/{$integrationName}/Models...");
                $integrationModelFiles = $this->getModelFilesFromDirectory($modelsDir);
                $totalModifiedCount += $this->processModelsInDirectory($integrationModelFiles);
            }
        }

        $this->newLine();
        $this->info("Total: {$totalModifiedCount} models have been made auditable.");
    }

    /**
     * Get model files from a directory.
     *
     * @return array<int, SplFileInfo>
     */
    protected function getModelFilesFromDirectory(string $directory): array
    {
        if (! File::isDirectory($directory)) {
            return [];
        }

        $files = File::files($directory);

        return array_filter($files, function (SplFileInfo $file): bool {
            return $file->getExtension() === 'php' && $file->getFilename() !== 'Traits';
        });
    }

    /**
     * Process models in a directory.
     *
     * @param  array<int, SplFileInfo>  $modelFiles
     */
    protected function processModelsInDirectory(array $modelFiles): int
    {
        if ($modelFiles === []) {
            $this->line('No models found in this directory.');

            return 0;
        }

        $this->line('Found '.count($modelFiles).' models.');
        $bar = $this->output->createProgressBar(count($modelFiles));
        $bar->start();

        $modifiedCount = 0;
        foreach ($modelFiles as $file) {
            if ($this->processModel($file->getPathname())) {
                $modifiedCount++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("{$modifiedCount} models have been made auditable in this directory.");

        return $modifiedCount;
    }

    /**
     * Process a single model file.
     */
    protected function processModel(string $filePath): bool
    {
        $content = File::get($filePath);
        $filename = basename($filePath);
        $modelName = pathinfo($filename, PATHINFO_FILENAME);

        // Skip if already auditable
        if (Str::contains($content, 'use App\Traits\AuditableModel;') ||
            Str::contains($content, 'use AuditableModel;')) {
            $this->line("Model {$modelName} is already auditable. Skipping.");

            return false;
        }

        // Parse the file to get namespace and class definition
        preg_match('/namespace\s+([^;]+);/', $content, $namespaceMatches);
        preg_match('/class\s+(\w+)(?:\s+extends\s+(\w+))?(?:\s+implements\s+([^{]+))?/', $content, $classMatches);

        if ($namespaceMatches === [] || $classMatches === []) {
            $this->error("Could not parse model {$modelName}. Skipping.");

            return false;
        }
        $className = $classMatches[1];
        $implements = $classMatches[3] ?? '';

        // Add Auditable interface if not already implemented
        if (! Str::contains($implements, 'Auditable')) {
            if (empty($implements)) {
                $newImplements = ' implements '.Auditable::class;
            } else {
                $newImplements = str_replace('implements ', 'implements '.Auditable::class.', ', $implements);
            }
            $content = preg_replace(
                '/class\s+'.$className.'(?:\s+extends\s+(\w+))?(?:\s+implements\s+([^{]+))?/',
                'class '.$className.($classMatches[2] ?? false ? ' extends '.$classMatches[2] : '').$newImplements,
                $content
            );
        }

        // Add use statement for AuditableModel trait
        $useStatement = 'use App\Traits\AuditableModel;';
        if ($content !== null && ! Str::contains($content, $useStatement)) {
            $lastUsePos = strrpos($content, 'use ');
            if ($lastUsePos !== false) {
                $endOfLastUse = strpos($content, ';', $lastUsePos);
                if ($endOfLastUse !== false) {
                    $content = substr_replace($content, ";\n".$useStatement, $endOfLastUse + 1, 1);
                }
            } else {
                $namespacePos = strpos($content, 'namespace');
                if ($namespacePos !== false) {
                    $namespaceEnd = strpos($content, ';', $namespacePos);
                    if ($namespaceEnd !== false) {
                        $content = substr_replace($content, ";\n\n".$useStatement, $namespaceEnd + 1, 1);
                    }
                }
            }
        }

        // Add use statement for Auditable interface
        $useAuditableStatement = 'use OwenIt\Auditing\Contracts\Auditable;';
        if ($content !== null && ! Str::contains($content, $useAuditableStatement)) {
            $lastUsePos = strrpos($content, 'use ');
            if ($lastUsePos !== false) {
                $endOfLastUse = strpos($content, ';', $lastUsePos);
                if ($endOfLastUse !== false) {
                    $content = substr_replace($content, ";\n".$useAuditableStatement, $endOfLastUse + 1, 1);
                }
            }
        }

        // Add the trait to the class
        if ($content !== null) {
            $classPos = strpos($content, 'class '.$className);
            if ($classPos !== false) {
                $openBracePos = strpos($content, '{', $classPos);
                if ($openBracePos !== false) {
                    $traitPos = $openBracePos + 1;
                    $indentation = $this->getIndentation($content, $traitPos);

                    // Check if there are already traits being used
                    if (preg_match('/\{(\s*use\s+[^;]+;)/', $content, $traitMatches)) {
                        // Add our trait to the existing use statement
                        $existingTraits = $traitMatches[1];
                        $newTraits = str_replace('use ', 'use AuditableModel, ', $existingTraits);
                        $content = str_replace($existingTraits, $newTraits, $content);
                    } else {
                        // Add a new use statement for our trait
                        $newTraitStatement = "\n{$indentation}use AuditableModel;";
                        $content = substr_replace($content, $newTraitStatement, $traitPos, 0);
                    }
                }
            }
        }

        // Save the modified file
        if ($content !== null) {
            File::put($filePath, $content);
            $this->line("Model {$modelName} is now auditable.");

            return true;
        }

        $this->error("Failed to modify model {$modelName}.");

        return false;
    }

    /**
     * Get the indentation at a specific position in the content.
     */
    protected function getIndentation(string $content, int $position): string
    {
        $lineStart = strrpos(substr($content, 0, $position), "\n") + 1;
        $indentation = substr($content, $lineStart, $position - $lineStart);

        return $indentation.'    '; // Add one level of indentation
    }
}
