<?php

declare(strict_types=1);

namespace Tests\Unit\AdminPanel\Manager;

use App\Livewire\AdminPanel\Manager\Translation\TranslationMigrationManager;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('admin-panel')]
#[Group('translation')]
#[Group('translation-migrations')]
class TranslationMigrationManagerUnitTest extends TestCase
{
    #[Test]
    public function test_properties_initialization(): void
    {
        $component = new TranslationMigrationManager;

        // Test default property values
        $this->assertEquals('', $component->selectedInterface);
        $this->assertEquals('', $component->selectedStatus);
        $this->assertEquals('', $component->search);
        $this->assertNull($component->dateFrom);
        $this->assertEquals(20, $component->perPage);
        $this->assertFalse($component->showSyncModal);
        $this->assertFalse($component->showApplyModal);
        $this->assertFalse($component->showRollbackModal);
        $this->assertFalse($component->showPreviewDrawer);
        $this->assertNull($component->selectedMigration);
        $this->assertSame([], $component->selectedMigrations);
        $this->assertFalse($component->selectAll);
        $this->assertSame('custom', $component->activePreset);
        $this->assertSame([
            'pending' => 0,
            'failed_recent' => 0,
            'processing' => 0,
        ], $component->counters);
        $this->assertEquals([], $component->previewData);
        $this->assertEquals('', $component->syncInterface);
        $this->assertFalse($component->autoProcess);
        $this->assertTrue($component->createBackup);
        $this->assertTrue($component->validateChecksum);
    }

    #[Test]
    public function test_computed_properties(): void
    {
        $component = new TranslationMigrationManager;

        // Test interfaces computed property
        $interfaces = $component->getInterfacesProperty();
        $this->assertIsArray($interfaces);
        $this->assertArrayHasKey('mobile', $interfaces);
        $this->assertArrayHasKey('web_financer', $interfaces);
        $this->assertArrayHasKey('web_beneficiary', $interfaces);

        // Test statuses computed property
        $statuses = $component->getStatusesProperty();
        $this->assertIsArray($statuses);
        $this->assertArrayHasKey('pending', $statuses);
        $this->assertArrayHasKey('processing', $statuses);
        $this->assertArrayHasKey('completed', $statuses);
        $this->assertArrayHasKey('failed', $statuses);
        $this->assertArrayHasKey('rolled_back', $statuses);

        // Each status should have label and color
        foreach ($statuses as $status) {
            $this->assertArrayHasKey('label', $status);
            $this->assertArrayHasKey('color', $status);
        }
    }

    #[Test]
    public function test_validation_rules(): void
    {
        $component = new TranslationMigrationManager;

        $rules = $component->rules();

        // Test sync validation rules
        $this->assertArrayHasKey('syncInterface', $rules);
        $this->assertStringContainsString('required', $rules['syncInterface']);
        $this->assertStringContainsString('in:mobile,web_financer,web_beneficiary', $rules['syncInterface']);

        // Test migration ID validation rules
        $this->assertArrayHasKey('selectedMigration.id', $rules);
        $this->assertStringContainsString('required', $rules['selectedMigration.id']);
        $this->assertStringContainsString('exists:translation_migrations,id', $rules['selectedMigration.id']);

        // Test backup and checksum validation rules
        $this->assertArrayHasKey('createBackup', $rules);
        $this->assertEquals('boolean', $rules['createBackup']);

        $this->assertArrayHasKey('validateChecksum', $rules);
        $this->assertEquals('boolean', $rules['validateChecksum']);
    }
}
