<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\AdminPanel;

use App\Http\Middleware\AdminCognitoMiddleware;
use App\Jobs\TranslationMigrations\ProcessTranslationMigrationJob;
use App\Models\TranslationMigration;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Date;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('admin-panel')]
#[Group('translation')]
class TranslationMigrationWebControllerTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_dispatches_job_when_applying_migration_from_web(): void
    {
        config(['admin-panel.enabled' => true]);
        $this->withoutMiddleware(AdminCognitoMiddleware::class);

        Bus::fake();
        Date::setTestNow(Date::now());

        $migration = TranslationMigration::factory()->create([
            'status' => 'pending',
            'metadata' => [],
        ]);

        $response = $this->post(
            "/admin-panel/translation-migrations/{$migration->id}/apply",
            [
                'create_backup' => true,
                'validate_checksum' => false,
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        Bus::assertDispatched(ProcessTranslationMigrationJob::class, function (ProcessTranslationMigrationJob $job) use ($migration): bool {
            return $job->migrationId === $migration->id
                && $job->createBackup
                && $job->validateChecksum === false;
        });

        $migration->refresh();

        $this->assertEquals('processing', $migration->status);
        $this->assertTrue($migration->metadata['create_backup_requested']);
        $this->assertFalse($migration->metadata['validate_checksum_requested']);
        $this->assertArrayHasKey('apply_requested_at', $migration->metadata);

        Date::setTestNow();
    }

    #[Test]
    public function guest_is_redirected_when_accessing_translation_migrations(): void
    {
        config(['admin-panel.enabled' => true]);

        $response = $this->get('/admin-panel/translation-migrations');

        $response->assertRedirect(route('admin.auth.login'));
    }
}
