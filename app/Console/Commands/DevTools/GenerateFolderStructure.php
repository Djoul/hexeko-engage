<?php

namespace App\Console\Commands\DevTools;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateFolderStructure extends Command
{
    protected $signature = 'generate:folder-structure {path?}';

    protected $description = 'Génère un fichier JSON de la structure des dossiers pour Mermaid.js';

    public function handle(): void
    {
        $path = $this->argument('path') ?? base_path();
        $structure = $this->getFolderStructure($path);

        $jsonFilePath = storage_path('app/'.Now()->toDateString().'-folder_structure.json');
        $content = json_encode($structure, JSON_PRETTY_PRINT) ?: '';

        File::put($jsonFilePath, $content);

        $this->info("Structure de dossiers générée : $jsonFilePath");
    }

    /**
     * @return mixed[]
     */
    private function getFolderStructure(string $path, string $prefix = ''): array
    {
        $result = [];
        $items = scandir($path) ?: [];

        foreach ($items as $item) {
            if ($item === '.') {
                continue;
            }
            if ($item === '..') {
                continue;
            }
            $fullPath = $path.DIRECTORY_SEPARATOR.$item;
            $relativePath = $prefix.$item;

            if (is_dir($fullPath)) {
                $result[$relativePath] = $this->getFolderStructure($fullPath, $relativePath.'/');
            } else {
                $result[] = $relativePath;
            }
        }

        return $result;
    }
}
