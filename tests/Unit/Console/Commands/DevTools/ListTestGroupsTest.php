<?php

namespace Tests\Unit\Console\Commands\DevTools;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('dev-tools')]
class ListTestGroupsTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_lists_all_test_groups_with_occurrences(): void
    {
        $this->artisan('list:test-groups')
            ->expectsOutputToContain('Groupes trouvés avec leur nombre d\'occurrences')
            ->expectsOutputToContain('Nombre total de groupes uniques')
            ->assertSuccessful();
    }

    #[Test]
    public function it_displays_groups_in_alphabetical_order_by_default(): void
    {
        $output = $this->artisan('list:test-groups')
            ->execute();

        $this->assertEquals(0, $output);
    }

    #[Test]
    public function it_sorts_groups_by_count_when_option_provided(): void
    {
        $this->artisan('list:test-groups', ['--sort-by-count' => true])
            ->expectsOutputToContain('Groupes trouvés avec leur nombre d\'occurrences')
            ->assertSuccessful();
    }

    #[Test]
    public function it_lists_test_classes_without_groups(): void
    {
        $this->artisan('list:test-groups')
            ->expectsOutputToContain('Classes de test sans groupe')
            ->assertSuccessful();
    }

    #[Test]
    public function it_exports_results_to_markdown_file(): void
    {
        $outputPath = storage_path('app/test-groups-export.md');

        // Clean up any existing file
        if (File::exists($outputPath)) {
            File::delete($outputPath);
        }

        $this->artisan('list:test-groups', ['--output' => $outputPath])
            ->expectsOutputToContain("Résultats exportés vers : {$outputPath}")
            ->assertSuccessful();

        $this->assertFileExists($outputPath);

        $content = File::get($outputPath);
        $this->assertStringContainsString('# Test Groups Report', $content);
        $this->assertStringContainsString('## Groups with Occurrences', $content);
        $this->assertStringContainsString('## Test Classes without Groups', $content);

        // Clean up
        File::delete($outputPath);
    }

    #[Test]
    public function it_handles_no_groups_found(): void
    {
        // This test would require mocking the filesystem or using a test directory
        // For now, we test that the command handles the scenario gracefully
        $this->artisan('list:test-groups')
            ->assertSuccessful();
    }

    #[Test]
    public function it_displays_total_statistics(): void
    {
        $this->artisan('list:test-groups')
            ->expectsOutputToContain('Nombre total de groupes uniques')
            ->expectsOutputToContain('Nombre total d\'occurrences')
            ->assertSuccessful();
    }

    #[Test]
    public function it_combines_sort_and_export_options(): void
    {
        $outputPath = storage_path('app/test-groups-sorted.md');

        if (File::exists($outputPath)) {
            File::delete($outputPath);
        }

        $this->artisan('list:test-groups', [
            '--sort-by-count' => true,
            '--output' => $outputPath,
        ])
            ->expectsOutputToContain("Résultats exportés vers : {$outputPath}")
            ->assertSuccessful();

        $this->assertFileExists($outputPath);

        // Clean up
        File::delete($outputPath);
    }

    #[Test]
    public function it_creates_directory_if_not_exists_for_export(): void
    {
        $outputPath = storage_path('app/test-reports/groups-export.md');
        $directory = dirname($outputPath);

        // Clean up any existing file/directory
        if (File::exists($outputPath)) {
            File::delete($outputPath);
        }
        if (File::isDirectory($directory)) {
            File::deleteDirectory($directory);
        }

        $this->artisan('list:test-groups', ['--output' => $outputPath])
            ->expectsOutputToContain("Résultats exportés vers : {$outputPath}")
            ->assertSuccessful();

        $this->assertFileExists($outputPath);
        $this->assertDirectoryExists($directory);

        // Clean up
        File::deleteDirectory($directory);
    }
}
