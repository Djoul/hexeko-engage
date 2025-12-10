<?php

namespace Tests\Unit\Services\ActivityLogs;

use App\Models\Division;
use App\Models\Financer;
use App\Models\Module;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('module')]

class ModuleActivityLogTest extends ProtectedRouteTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! config('activitylog.enabled')) {
            $this->markTestSkipped('Activity logging is not enabled');
        }
    }

    #[Test]
    public function it_logs_when_a_module_is_created(): void
    {
        $module = Module::create([
            'name' => 'HR Management',
            'description' => 'Module for managing HR activities',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'module',
            'description' => 'created',
            'subject_id' => $module->id,
            'subject_type' => Module::class,
        ]);
    }

    #[Test]
    public function it_logs_when_a_module_is_updated(): void
    {
        $module = Module::factory()->create();

        $module->update(['name' => 'Updated Module']);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'module',
            'description' => 'updated',
            'subject_id' => $module->id,
            'subject_type' => Module::class,
        ]);
    }

    #[Test]
    public function it_logs_when_a_module_is_deleted(): void
    {
        $module = Module::factory()->create();

        $module->delete();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'module',
            'description' => 'deleted',
            'subject_id' => $module->id,
            'subject_type' => Module::class,
        ]);
    }

    #[Test]
    public function it_logs_when_a_division_is_attached_to_module(): void
    {
        $module = Module::factory()->create();
        $division = Division::factory()->create();

        $module->attachDivision($division->id, ['active' => true]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'module',
            'description' => "Division ID {$division->id} attaché au module {$module->name}",
            'subject_id' => $module->id,
            'subject_type' => Module::class,
        ]);
    }

    #[Test]
    public function it_logs_when_a_division_is_detached_from_module(): void
    {
        $module = Module::factory()->create();
        $division = Division::factory()->create();

        $module->attachDivision($division->id, ['active' => true]);
        $module->detachDivision($division->id, ['active' => true]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'module',
            'description' => "Division ID {$division->id} détaché du module {$module->name}",
            'subject_id' => $module->id,
            'subject_type' => Module::class,
        ]);
    }

    #[Test]
    public function it_logs_when_a_financer_is_attached_to_module(): void
    {
        $module = Module::factory()->create();
        $financer = Financer::factory()->create();

        $module->attachFinancer($financer->id, ['active' => true]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'module',
            'description' => "Financer ID {$financer->id} attaché au module {$module->name}",
            'subject_id' => $module->id,
            'subject_type' => Module::class,
        ]);
    }

    #[Test]
    public function it_logs_when_a_financer_is_detached_from_module(): void
    {
        $module = Module::factory()->create();
        $financer = Financer::factory()->create();

        $module->attachFinancer($financer->id, ['active' => true]);
        $module->detachFinancer($financer->id);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'module',
            'description' => "Financer ID {$financer->id} détaché du module {$module->name}",
            'subject_id' => $module->id,
            'subject_type' => Module::class,
        ]);
    }
}
