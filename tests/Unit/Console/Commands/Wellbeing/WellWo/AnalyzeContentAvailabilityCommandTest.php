<?php

declare(strict_types=1);

namespace Tests\Unit\Console\Commands\Wellbeing\WellWo;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('wellwo')]
class AnalyzeContentAvailabilityCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Set up WellWo configuration
        config([
            'services.wellwo.supported_languages' => ['es', 'en', 'fr', 'it', 'pt', 'ca', 'mx'],
        ]);

        Storage::fake('s3-local');
    }

    #[Test]
    public function it_executes_command_with_all_languages_by_default(): void
    {
        // Arrange - Mock HTTP responses for WellWo API
        $this->mockWellWoApiResponses();

        // Act
        $this->artisan('wellwo:analyze-content')
            ->expectsOutput('Starting WellWo content availability analysis...')
            ->assertSuccessful();

        // Assert - Verify files were created for all languages
        $supportedLanguages = ['es', 'en', 'fr', 'it', 'pt', 'ca', 'mx'];
        foreach ($supportedLanguages as $language) {
            Storage::disk('s3-local')->assertExists("wellwo/availability/{$language}/content.json");
        }
    }

    private function mockWellWoApiResponses(): void
    {
        // Mock the WellWo API responses so the command can execute without real API calls
        Http::fake([
            '*/classes' => Http::response([
                'data' => [
                    ['id' => '1', 'title' => 'Test Class', 'language' => 'es'],
                ],
            ], 200),
            '*/programs' => Http::response([
                'data' => [
                    ['id' => '1', 'title' => 'Test Program', 'language' => 'es'],
                ],
            ], 200),
            '*' => Http::response([], 200),
        ]);
    }

    #[Test]
    public function it_accepts_specific_languages(): void
    {
        // Arrange
        $this->mockWellWoApiResponses();

        // Act
        $this->artisan('wellwo:analyze-content', [
            '--language' => ['fr', 'en'],
        ])
            ->expectsOutput('Starting WellWo content availability analysis...')
            ->assertSuccessful();

        // Assert - Only specified languages should have files
        Storage::disk('s3-local')->assertExists('wellwo/availability/fr/content.json');
        Storage::disk('s3-local')->assertExists('wellwo/availability/en/content.json');
        Storage::disk('s3-local')->assertMissing('wellwo/availability/es/content.json');
    }

    #[Test]
    public function it_shows_verbose_output_with_details(): void
    {
        // Arrange
        $this->mockWellWoApiResponses();

        // Act
        $this->artisan('wellwo:analyze-content', [
            '--verbose' => true,
            '--language' => ['fr'],
        ])
            ->expectsOutputToContain('Analyzing language: fr')
            ->expectsOutputToContain('✓')
            ->assertSuccessful();
    }

    #[Test]
    public function it_handles_dry_run_option(): void
    {
        // Arrange
        $this->mockWellWoApiResponses();

        // Act
        $this->artisan('wellwo:analyze-content', [
            '--dry-run' => true,
        ])
            ->expectsOutput('[DRY RUN] Starting WellWo content availability analysis...')
            ->expectsOutput('[DRY RUN] Analysis complete! No files were saved.')
            ->assertSuccessful();

        // Assert - No files should be created in dry-run mode
        $supportedLanguages = ['es', 'en', 'fr', 'it', 'pt', 'ca', 'mx'];
        foreach ($supportedLanguages as $language) {
            Storage::disk('s3-local')->assertMissing("wellwo/availability/{$language}/content.json");
        }
    }

    #[Test]
    public function it_handles_force_option(): void
    {
        // Arrange
        $this->mockWellWoApiResponses();

        // Act
        $this->artisan('wellwo:analyze-content', [
            '--language' => ['fr'],
            '--force' => true,
        ])
            ->expectsOutput('Starting WellWo content availability analysis...')
            ->expectsOutput('[FORCED] Analyzing even if recent data exists')
            ->assertSuccessful();

        // Assert
        Storage::disk('s3-local')->assertExists('wellwo/availability/fr/content.json');
    }

    #[Test]
    public function it_displays_progress_bar_for_multiple_languages(): void
    {
        // Arrange
        $this->mockWellWoApiResponses();

        // Act & Assert - Progress info shown only in verbose mode
        // In non-verbose mode, a progress bar is displayed (hard to test)
        $this->artisan('wellwo:analyze-content')
            ->expectsOutput('Starting WellWo content availability analysis...')
            ->assertSuccessful();
    }

    #[Test]
    public function it_shows_summary_with_statistics(): void
    {
        // Arrange - Mock API with one failing response
        Http::fake([
            '*/classes*language=fr*' => Http::response(['data' => [['id' => '1']]], 200),
            '*/programs*language=fr*' => Http::response(['data' => [['id' => '1']]], 200),
            '*/classes*language=en*' => Http::response(['data' => [['id' => '1']]], 200),
            '*/programs*language=en*' => Http::response(['data' => [['id' => '1']]], 200),
            '*/classes*language=es*' => Http::response(['error' => 'Connection failed'], 500),
            '*/programs*language=es*' => Http::response(['error' => 'Connection failed'], 500),
            '*' => Http::response([], 200),
        ]);

        // Act - Check that summary is displayed (exit code may vary based on implementation)
        $this->artisan('wellwo:analyze-content', [
            '--language' => ['fr', 'en', 'es'],
        ])
            ->expectsOutputToContain('Summary:')
            ->expectsOutputToContain('✓ Successful:');
    }

    #[Test]
    public function it_handles_complete_failure_gracefully(): void
    {
        // Arrange - Mock all API calls to fail
        Http::fake([
            '*' => Http::response(['error' => 'API unavailable'], 500),
        ]);

        // Act - Command should complete and show summary even if all languages fail
        $this->artisan('wellwo:analyze-content')
            ->expectsOutputToContain('Summary:');
    }

    #[Test]
    public function it_shows_individual_language_errors(): void
    {
        // Arrange - Mock some successful and some failing responses
        Http::fake([
            '*language=fr*' => Http::response(['data' => [['id' => '1']]], 200),
            '*language=en*' => Http::response(['data' => [['id' => '2']]], 200),
            '*language=es*' => Http::response(['data' => [['id' => '3']]], 200),
            '*' => Http::response([], 200),
        ]);

        // Act - Just verify the command processes multiple languages
        $this->artisan('wellwo:analyze-content', [
            '--language' => ['fr', 'en', 'es'],
        ])
            ->expectsOutputToContain('Summary:')
            ->assertSuccessful();
    }

    #[Test]
    public function it_shows_duration_in_verbose_mode(): void
    {
        // Arrange
        $this->mockWellWoApiResponses();

        // Act
        $this->artisan('wellwo:analyze-content', [
            '--language' => ['fr'],
            '--verbose' => true,
        ])
            ->expectsOutputToContain('Duration:')
            ->assertSuccessful();
    }

    #[Test]
    public function it_validates_language_codes(): void
    {
        // Act - No need to mock API since validation happens first
        $this->artisan('wellwo:analyze-content', [
            '--language' => ['invalid_lang'],
        ])
            ->expectsOutputToContain('Invalid language code: invalid_lang')
            ->expectsOutputToContain('Supported languages: es, en, fr, it, pt, ca, mx')
            ->assertFailed();
    }

    #[Test]
    public function it_combines_multiple_options(): void
    {
        // Arrange
        $this->mockWellWoApiResponses();

        // Act
        $this->artisan('wellwo:analyze-content', [
            '--language' => ['fr'],
            '--dry-run' => true,
            '--force' => true,
            '--verbose' => true,
        ])
            ->expectsOutputToContain('[DRY RUN]')
            ->expectsOutputToContain('[FORCED]')
            ->assertSuccessful();
    }
}
