<?php

namespace Tests\Unit\Services\ActivityLogs;

use App\Models\Division;
use App\Models\Financer;
use App\Models\Integration;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\ProtectedRouteTestCase;

#[Group('integration')]
class IntegrationActivityLogTest extends ProtectedRouteTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! config('activitylog.enabled')) {
            $this->markTestSkipped('Activity logging is not enabled');
        }
    }

    #[Test]
    public function it_logs_when_an_integration_is_created(): void
    {
        $integration = Integration::create([
            'module_id' => Uuid::uuid4()->toString(),
            'name' => 'CRM Integration',
            'type' => 'crm',
            'description' => 'Integration with CRM system',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'integration',
            'description' => 'created',
            'subject_id' => $integration->id,
            'subject_type' => Integration::class,
        ]);
    }

    #[Test]
    public function it_logs_when_an_integration_is_updated(): void
    {
        $integration = Integration::factory()->create();

        $integration->update(['name' => 'Updated Integration']);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'integration',
            'description' => 'updated',
            'subject_id' => $integration->id,
            'subject_type' => Integration::class,
        ]);
    }

    #[Test]
    public function it_logs_when_an_integration_is_deleted(): void
    {
        $integration = Integration::factory()->create();

        $integration->delete();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'integration',
            'description' => 'deleted',
            'subject_id' => $integration->id,
            'subject_type' => Integration::class,
        ]);
    }

    #[Test]
    public function it_logs_when_a_division_is_attached_to_integration(): void
    {
        $integration = Integration::factory()->create();
        $division = Division::factory()->create();

        $integration->attachDivision($division->id, ['active' => true]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'integration',
            'description' => "Division ID {$division->id} attachée à l'intégration {$integration->name}",
            'subject_id' => $integration->id,
            'subject_type' => Integration::class,
        ]);
    }

    #[Test]
    public function it_logs_when_a_division_is_detached_from_integration(): void
    {
        $integration = Integration::factory()->create();
        $division = Division::factory()->create();

        $integration->attachDivision($division->id, ['active' => true]);
        $integration->detachDivision($division->id);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'integration',
            'description' => "Division ID {$division->id} détachée de l'intégration {$integration->name}",
            'subject_id' => $integration->id,
            'subject_type' => Integration::class,
        ]);
    }

    #[Test]
    public function it_logs_when_a_financer_is_attached_to_integration(): void
    {
        $integration = Integration::factory()->create();
        $financer = Financer::factory()->create();

        $integration->attachFinancer($financer->id, ['active' => true]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'integration',
            'description' => "Financer ID {$financer->id} attaché à l'intégration {$integration->name}",
            'subject_id' => $integration->id,
            'subject_type' => Integration::class,
        ]);
    }

    #[Test]
    public function it_logs_when_a_financer_is_detached_from_integration(): void
    {
        $integration = Integration::factory()->create();
        $financer = Financer::factory()->create();

        $integration->attachFinancer($financer->id, ['active' => true]);
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'integration',
            'description' => "Financer ID {$financer->id} attaché à l'intégration {$integration->name}",
            'subject_id' => $integration->id,
            'subject_type' => Integration::class,
        ]);

        $integration->detachFinancer($financer->id);
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'integration',
            'description' => "Financer ID {$financer->id} détaché de l'intégration {$integration->name}",
            'subject_id' => $integration->id,
            'subject_type' => Integration::class,
        ]);
    }
}
