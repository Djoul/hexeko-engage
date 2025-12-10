<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\TranslationActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('translation')]
#[Group('audit')]
#[Group('translation-crud')]
class TranslationActivityLogTest extends TestCase
{
    #[Test]
    public function it_is_a_model(): void
    {
        $activityLog = new TranslationActivityLog;

        $this->assertInstanceOf(Model::class, $activityLog);
    }

    #[Test]
    public function it_implements_auditable_interface(): void
    {
        $activityLog = new TranslationActivityLog;

        $this->assertInstanceOf(Auditable::class, $activityLog);
    }

    #[Test]
    public function it_uses_auditable_model_trait(): void
    {
        $activityLog = new TranslationActivityLog;

        $this->assertTrue(method_exists($activityLog, 'audits'));
        $this->assertTrue(method_exists($activityLog, 'getAuditInclude'));
        $this->assertTrue(method_exists($activityLog, 'getAuditExclude'));
    }

    #[Test]
    public function it_belongs_to_user(): void
    {
        $activityLog = new TranslationActivityLog;
        $relation = $activityLog->user();

        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertEquals(User::class, $relation->getRelated()::class);
    }

    #[Test]
    public function it_can_create_activity_log(): void
    {
        $user = User::factory()->create();

        TranslationActivityLog::create([
            'user_id' => $user->id,
            'action' => 'created',
            'target_type' => 'key',
            'target_id' => 1,
            'locale' => 'en',
            'before' => null,
            'after' => ['key' => 'test.key'],
        ]);

        $this->assertDatabaseHas('translation_activity_logs', [
            'user_id' => $user->id,
            'action' => 'created',
            'target_type' => 'key',
            'target_id' => 1,
        ]);
    }

    #[Test]
    public function it_can_log_different_actions(): void
    {
        $user = User::factory()->create();

        // Create action
        $createLog = TranslationActivityLog::create([
            'user_id' => $user->id,
            'action' => 'created',
            'target_type' => 'key',
            'target_id' => 1,
            'before' => null,
            'after' => ['key' => 'new.key'],
        ]);

        // Update action
        $updateLog = TranslationActivityLog::create([
            'user_id' => $user->id,
            'action' => 'updated',
            'target_type' => 'value',
            'target_id' => 2,
            'locale' => 'fr',
            'before' => ['value' => 'Old text'],
            'after' => ['value' => 'New text'],
        ]);

        // Delete action
        $deleteLog = TranslationActivityLog::create([
            'user_id' => $user->id,
            'action' => 'deleted',
            'target_type' => 'key',
            'target_id' => 3,
            'before' => ['key' => 'deleted.key'],
            'after' => null,
        ]);

        $this->assertEquals('created', $createLog->action);
        $this->assertEquals('updated', $updateLog->action);
        $this->assertEquals('deleted', $deleteLog->action);

        $this->assertEquals(['value' => 'Old text'], $updateLog->before);
        $this->assertEquals(['value' => 'New text'], $updateLog->after);
    }

    #[Test]
    public function it_can_track_different_target_types(): void
    {
        $user = User::factory()->create();

        // Key target
        $keyLog = TranslationActivityLog::create([
            'user_id' => $user->id,
            'action' => 'created',
            'target_type' => 'key',
            'target_id' => 10,
            'after' => ['key' => 'test.key'],
        ]);

        // Value target
        $valueLog = TranslationActivityLog::create([
            'user_id' => $user->id,
            'action' => 'updated',
            'target_type' => 'value',
            'target_id' => 20,
            'locale' => 'en',
            'after' => ['value' => 'Test value'],
        ]);

        $this->assertEquals('key', $keyLog->target_type);
        $this->assertEquals('value', $valueLog->target_type);
    }

    #[Test]
    public function it_can_retrieve_user_relationship(): void
    {
        $user = User::factory()->create();

        $activityLog = TranslationActivityLog::create([
            'user_id' => $user->id,
            'action' => 'created',
            'target_type' => 'key',
            'target_id' => 100,
        ]);

        $retrievedUser = $activityLog->user;

        $this->assertInstanceOf(User::class, $retrievedUser);
        $this->assertEquals($user->id, $retrievedUser->id);
    }
}
