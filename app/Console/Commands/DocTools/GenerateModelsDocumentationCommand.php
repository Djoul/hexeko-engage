<?php

namespace App\Console\Commands\DocTools;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateModelsDocumentationCommand extends Command
{
    protected $signature = 'models-doc:generate';

    protected $description = 'Generates detailed documentation for Laravel models suitable for Notion import';

    public function handle(): void
    {
        $helperFiles = [
            base_path('_ide_helper_models.php'),
            base_path('_ide_helper.php'),
        ];

        $content = '';

        foreach ($helperFiles as $file) {
            if (! File::exists($file)) {
                continue;
            }

            $content .= File::get($file);
        }

        preg_match_all('/@mixin\\s+(\\\\[a-zA-Z0-9\\\\]+)/', $content, $models);

        $uniqueModels = array_unique($models[1]);

        $documentation = '';

        foreach ($uniqueModels as $model) {
            $documentation .= "{$model}\n";
            $documentation .= "Traits:\n";

            preg_match_all('/use ([\\\\a-zA-Z0-9]+);/', $content, $traits);
            $documentation .= implode(', ', array_unique($traits[1]))."\n";

            preg_match_all('/@property ([^\n]+)\n/', $content, $properties);
            $documentation .= "Properties:\n";
            $documentation .= implode("\n", array_unique($properties[1]))."\n";

            preg_match_all('/@method ([^\n]+)\n/', $content, $methods);
            $documentation .= "Methods:\n";
            $documentation .= implode("\n", array_unique($methods[1]))."\n";

            $documentation .= "------------------------------\n\n";
        }

        File::put(base_path('MODELS_DOCUMENTATION_FOR_NOTION.txt'), $documentation);

        $this->info('Detailed documentation generated successfully at MODELS_DOCUMENTATION_FOR_NOTION.txt');
    }
}
