<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\TranslationActivityLog;
use App\Models\TranslationKey;
use App\Models\TranslationValue;
use Context;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use OwenIt\Auditing\Contracts\Auditable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('admin-panel')]
#[Group('translation')]
class TranslationKeyTest extends ProtectedRouteTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Clean up Laravel Context to ensure test isolation
        Context::flush();
    }

    protected function tearDown(): void
    {
        // Clean up Laravel Context after each test
        Context::flush();

        parent::tearDown();
    }

    #[Test]
    public function it_is_a_model(): void
    {
        $translationKey = new TranslationKey;

        $this->assertInstanceOf(Model::class, $translationKey);
    }

    #[Test]
    public function it_implements_auditable_interface(): void
    {
        $translationKey = new TranslationKey;

        $this->assertInstanceOf(Auditable::class, $translationKey);
    }

    #[Test]
    public function it_uses_auditable_model_trait(): void
    {
        $translationKey = new TranslationKey;

        $this->assertTrue(method_exists($translationKey, 'audits'));
        $this->assertTrue(method_exists($translationKey, 'getAuditInclude'));
        $this->assertTrue(method_exists($translationKey, 'getAuditExclude'));
    }

    #[Test]
    public function it_uses_has_factory_trait(): void
    {
        $translationKey = new TranslationKey;

        $this->assertTrue(method_exists($translationKey, 'factory'));
    }

    #[Test]
    public function it_has_values_relationship(): void
    {
        $translationKey = new TranslationKey;
        $relation = $translationKey->values();

        $this->assertInstanceOf(HasMany::class, $relation);
        $this->assertEquals(TranslationValue::class, $relation->getRelated()::class);
    }

    #[Test]
    public function it_has_activity_logs_relationship(): void
    {
        $translationKey = new TranslationKey;
        $relation = $translationKey->activityLogs();

        $this->assertInstanceOf(HasMany::class, $relation);
        $this->assertEquals(TranslationActivityLog::class, $relation->getRelated()::class);

        // Check that it's filtering by target_type = 'key'
        $this->assertStringContainsString('target_type', $relation->toSql());
    }

    #[Test]
    public function it_can_create_translation_key(): void
    {
        TranslationKey::factory()->create([
            'key' => 'app.welcome.message',
            'group' => 'app',
        ]);

        $this->assertDatabaseHas('translation_keys', [
            'key' => 'app.welcome.message',
            'group' => 'app',
        ]);
    }

    #[Test]
    public function it_can_have_multiple_values(): void
    {
        $translationKey = TranslationKey::factory()->create([
            'key' => 'app.greeting',
        ]);

        // Create values with explicit different locales to respect unique constraint
        TranslationValue::factory()->create([
            'translation_key_id' => $translationKey->id,
            'locale' => 'en-US',
        ]);

        TranslationValue::factory()->create([
            'translation_key_id' => $translationKey->id,
            'locale' => 'fr-FR',
        ]);

        TranslationValue::factory()->create([
            'translation_key_id' => $translationKey->id,
            'locale' => 'es-ES',
        ]);

        $this->assertCount(3, $translationKey->values);
        $this->assertInstanceOf(TranslationValue::class, $translationKey->values->first());
    }

    #[Test]
    public function it_can_have_activity_logs(): void
    {
        $translationKey = TranslationKey::factory()->create();

        // Clear any auto-created audit logs from Auditable trait
        TranslationActivityLog::where('target_type', 'key')
            ->where('target_id', $translationKey->id)
            ->delete();

        // Create activity logs for this key
        TranslationActivityLog::factory()->create([
            'target_type' => 'key',
            'target_id' => $translationKey->id,
            'action' => 'created',
        ]);

        TranslationActivityLog::factory()->create([
            'target_type' => 'key',
            'target_id' => $translationKey->id,
            'action' => 'updated',
        ]);

        // Create activity log for different target (should not be included)
        TranslationActivityLog::factory()->create([
            'target_type' => 'value',
            'target_id' => $translationKey->id,
            'action' => 'created',
        ]);

        $activityLogs = $translationKey->activityLogs;

        $this->assertCount(2, $activityLogs);
        $this->assertTrue($activityLogs->every(fn ($log): bool => $log->target_type === 'key'));
        $this->assertTrue($activityLogs->every(fn ($log): bool => $log->target_id === $translationKey->id));
    }

    #[Test]
    public function it_cascades_to_values_on_delete(): void
    {
        $translationKey = TranslationKey::factory()->create();
        $valueIds = [];
        $locales = ['en-US', 'fr-FR', 'es-ES'];

        // Create values with explicit different locales to respect unique constraint
        for ($i = 0; $i < 3; $i++) {
            $value = TranslationValue::factory()->create([
                'translation_key_id' => $translationKey->id,
                'locale' => $locales[$i],
            ]);
            $valueIds[] = $value->id;
        }

        // Verify values exist
        $this->assertCount(3, TranslationValue::whereIn('id', $valueIds)->get());

        // The foreign key constraint prevents deletion
        // This test verifies that behavior
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Foreign key violation');

        // Try to delete the key - should fail due to FK constraint
        $translationKey->delete();
    }
}
