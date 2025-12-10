<?php

namespace Tests\Unit\Services\ActivityLogs;

use App\Models\Division;
use App\Models\Integration;
use App\Models\Module;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('division')]
#[Group('audit')]
class DivisionActivityLogTest extends ProtectedRouteTestCase
{
    #[Test]
    public function it_logs_when_a_division_is_created(): void
    {
        $division = Division::create([
            'name' => 'Europe',
            'country' => 'FR',
            'currency' => 'EUR',
            'timezone' => 'Europe/Paris',
            'language' => 'fr-FR',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'division',
            'description' => 'created',
            'subject_id' => $division->id,
            'subject_type' => Division::class,
        ]);
    }

    #[Test]
    public function it_logs_when_a_division_is_updated(): void
    {
        $division = Division::factory()->create();

        $division->update(['name' => 'Europe Updated']);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'division',
            'description' => 'updated',
            'subject_id' => $division->id,
            'subject_type' => Division::class,
        ]);
    }

    #[Test]
    public function it_logs_when_a_division_is_deleted(): void
    {
        $division = Division::factory()->create();

        $division->delete();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'division',
            'description' => 'deleted',
            'subject_id' => $division->id,
            'subject_type' => Division::class,
        ]);
    }

    #[Test]
    public function it_logs_when_a_module_is_attached_to_division(): void
    {
        $division = Division::factory()->create();

        $module = Module::factory()->create();

        $division->attachModule($module->id, ['active' => true]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'division',
            'description' => "Module ID {$module->id} attaché à la division {$division->name}",
            'subject_id' => $division->id,
            'subject_type' => Division::class,
        ]);
    }

    #[Test]
    public function it_logs_when_a_module_is_detached_from_division(): void
    {
        $division = Division::factory()->create();

        $module = Module::factory()->create();
        $division->attachModule($module->id, ['active' => true]);
        $division->detachModule($module->id);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'division',
            'description' => "Module ID {$module->id} détaché de la division {$division->name}",
            'subject_id' => $division->id,
            'subject_type' => Division::class,
        ]);
    }

    #[Test]
    public function it_logs_when_an_integration_is_attached_to_division(): void
    {
        $division = Division::factory()->create();

        $integration = Integration::factory()->create();

        $division->attachIntegration($integration->id, ['active' => true]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'division',
            'description' => "Integration ID {$integration->id} attachée à la division {$division->name}",
            'subject_id' => $division->id,
            'subject_type' => Division::class,
        ]);
    }

    #[Test]
    public function it_logs_when_an_integration_is_detached_from_division(): void
    {
        $division = Division::factory()->create();

        $integration = Integration::factory()->create();
        $division->attachIntegration($integration->id, ['active' => true]);
        $division->detachIntegration($integration->id);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'division',
            'description' => "Integration ID {$integration->id} détachée de la division {$division->name}",
            'subject_id' => $division->id,
            'subject_type' => Division::class,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (! config('activitylog.enabled')) {
            $this->markTestSkipped('Activity logging is not enabled');
        }
    }
}
