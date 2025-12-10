<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Division;
use App\Models\DivisionIntegration;
use App\Models\Integration;
use Illuminate\Database\Eloquent\Relations\Pivot;
use OwenIt\Auditing\Contracts\Auditable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('division')]
#[Group('integration')]
class DivisionIntegrationTest extends TestCase
{
    #[Test]
    public function it_is_a_pivot_model(): void
    {
        $divisionIntegration = new DivisionIntegration;

        $this->assertInstanceOf(Pivot::class, $divisionIntegration);
    }

    #[Test]
    public function it_uses_uuid_as_primary_key(): void
    {
        $divisionIntegration = new DivisionIntegration;

        $this->assertTrue($divisionIntegration->getIncrementing() === false);
        $this->assertEquals('string', $divisionIntegration->getKeyType());
    }

    #[Test]
    public function it_has_correct_table_name(): void
    {
        $divisionIntegration = new DivisionIntegration;

        $this->assertEquals('division_integration', $divisionIntegration->getTable());
    }

    #[Test]
    public function it_implements_auditable_interface(): void
    {
        $divisionIntegration = new DivisionIntegration;

        $this->assertInstanceOf(Auditable::class, $divisionIntegration);
    }

    #[Test]
    public function it_uses_cachable_trait(): void
    {
        $divisionIntegration = new DivisionIntegration;

        $this->assertTrue(in_array('App\Traits\Cachable', class_uses($divisionIntegration)));
    }

    #[Test]
    public function it_can_create_pivot_record(): void
    {
        $division = Division::factory()->create();
        $integration = Integration::factory()->create();

        $divisionIntegration = new DivisionIntegration;
        $divisionIntegration->division_id = $division->id;
        $divisionIntegration->integration_id = $integration->id;
        $divisionIntegration->active = true;
        $divisionIntegration->save();

        $this->assertDatabaseHas('division_integration', [
            'division_id' => $division->id,
            'integration_id' => $integration->id,
        ]);
    }

    #[Test]
    public function it_generates_uuid_on_creation(): void
    {
        $division = Division::factory()->create();
        $integration = Integration::factory()->create();

        $divisionIntegration = DivisionIntegration::create([
            'division_id' => $division->id,
            'integration_id' => $integration->id,
            'active' => true,
        ]);

        $this->assertNotNull($divisionIntegration->id);
        $this->assertIsString($divisionIntegration->id);
        $this->assertEquals(36, strlen($divisionIntegration->id)); // UUID length
    }
}
