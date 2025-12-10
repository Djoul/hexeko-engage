<?php

namespace App\Console\Commands\DevTools;

use Illuminate\Console\Command;
use PHPUnit\Framework\Attributes\Group;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use SplFileInfo;

class ListTestGroups extends Command
{
    protected $signature = 'list:test-groups
                            {--sort-by-count : Trier par nombre d\'occurrences décroissant}
                            {--output= : Exporter le résultat dans un fichier Markdown}';

    protected $description = 'Liste les groupes des tests dans le namespace Tests avec leur nombre d\'occurrences';

    private array $classesWithoutGroups = [];

    public function handle(): void
    {
        $testDir = base_path('tests');
        $groups = $this->extractGroupsFromTests($testDir);

        if ($groups === [] && $this->classesWithoutGroups === []) {
            $this->info('Aucun groupe trouvé dans les tests.');

            return;
        }

        // Trier les groupes
        if ($this->option('sort-by-count')) {
            arsort($groups);
        } else {
            ksort($groups);
        }

        // Check if output to file is requested
        $outputPath = $this->option('output');
        if ($outputPath) {
            $this->exportToMarkdown($groups, $outputPath);

            return;
        }

        // Display to console
        $this->displayConsoleOutput($groups);
    }

    private function displayConsoleOutput(array $groups): void
    {
        if ($groups !== []) {
            $this->info('Groupes trouvés avec leur nombre d\'occurrences :');
            $this->newLine();
            foreach ($groups as $group => $count) {
                $this->line("- {$group} ({$count} occurrence".($count > 1 ? 's' : '').')');
            }

            $this->newLine();
            $this->info('Nombre total de groupes uniques : '.count($groups));
            $this->info('Nombre total d\'occurrences : '.array_sum($groups));
        }

        if ($this->classesWithoutGroups !== []) {
            $this->newLine();
            $this->newLine();
            $this->info('Classes de test sans groupe :');
            $this->newLine();
            foreach ($this->classesWithoutGroups as $className) {
                $this->line("- {$className}");
            }
            $this->newLine();
            $this->info('Nombre total de classes sans groupe : '.count($this->classesWithoutGroups));
        }
    }

    private function exportToMarkdown(array $groups, string $outputPath): void
    {
        $content = $this->generateMarkdownContent($groups);

        // Ensure directory exists
        $directory = dirname($outputPath);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($outputPath, $content);

        $this->info("Résultats exportés vers : {$outputPath}");
    }

    private function generateMarkdownContent(array $groups): string
    {
        $content = "# Test Groups Report\n\n";
        $content .= 'Generated on: '.now()->format('Y-m-d H:i:s')."\n\n";

        if ($groups !== []) {
            $content .= "## Groups with Occurrences\n\n";
            $content .= "| Group Name | Occurrences |\n";
            $content .= "|------------|-------------|\n";

            foreach ($groups as $group => $count) {
                $content .= "| `{$group}` | {$count} |\n";
            }

            $content .= "\n";
            $content .= '**Total unique groups:** '.count($groups)."\n";
            $content .= '**Total occurrences:** '.array_sum($groups)."\n\n";
        }

        if ($this->classesWithoutGroups !== []) {
            $content .= "## Test Classes without Groups\n\n";

            foreach ($this->classesWithoutGroups as $className) {
                $content .= "- `{$className}`\n";
            }

            $content .= "\n";
            $content .= '**Total classes without groups:** '.count($this->classesWithoutGroups)."\n";
        }

        return $content;
    }

    /**
     * @return array<string, int>
     */
    private function extractGroupsFromTests(string $dir): array
    {
        $groups = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo) {
                continue;
            }
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $filePath = $file->getRealPath();
            if ($filePath === false) {
                continue;
            }
            $namespace = $this->getNamespaceFromFile($filePath);

            // Vérifie si le fichier appartient au namespace Tests
            if (strpos($namespace, 'Tests') !== 0) {
                continue;
            }

            // Charge la classe dynamiquement
            require_once $filePath;
            $className = $this->getClassNameFromFile($filePath, $namespace);
            if (! class_exists($className)) {
                continue;
            }

            $reflector = new ReflectionClass($className);
            $foundGroups = $this->extractGroupsFromReflector($reflector);

            if ($foundGroups === []) {
                $this->classesWithoutGroups[] = $className;
            } else {
                foreach ($foundGroups as $group => $count) {
                    $groups[$group] = ($groups[$group] ?? 0) + $count;
                }
            }
        }

        return $groups;
    }

    private function getNamespaceFromFile(string $filePath): string
    {
        $content = file_get_contents($filePath);
        if (! is_string($content)) {
            return '';
        }
        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            return trim($matches[1]);
        }

        return '';
    }

    private function getClassNameFromFile(string $filePath, string $namespace): string
    {
        $content = file_get_contents($filePath);
        if (! is_string($content)) {
            return '';
        }
        if (preg_match('/class\s+(\w+)/', $content, $matches)) {
            return $namespace.'\\'.$matches[1];
        }

        return '';
    }

    /**
     * @param  ReflectionClass<object>  $reflector
     * @return array<string, int>
     */
    private function extractGroupsFromReflector(ReflectionClass $reflector): array
    {
        $groups = [];

        // Attributs sur la classe entière
        $classAttributes = $reflector->getAttributes(Group::class);
        foreach ($classAttributes as $attr) {
            $args = $attr->getArguments();
            if (! empty($args)) {
                $group = $args[0]; // La valeur du groupe est le premier argument (string)
                if (is_string($group)) {
                    $groups[$group] = ($groups[$group] ?? 0) + 1;
                }
            }
        }

        // Attributs sur les méthodes de test (commençant par 'test')
        foreach ($reflector->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (strpos($method->getName(), 'test') !== 0) {
                continue;
            }

            $methodAttributes = $method->getAttributes(Group::class);
            foreach ($methodAttributes as $attr) {
                $args = $attr->getArguments();
                if (! empty($args)) {
                    $group = $args[0];
                    if (is_string($group)) {
                        $groups[$group] = ($groups[$group] ?? 0) + 1;
                    }
                }
            }
        }

        return $groups;
    }
}
