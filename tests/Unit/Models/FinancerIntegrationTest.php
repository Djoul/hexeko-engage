<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Financer;
use App\Models\FinancerIntegration;
use App\Models\Integration;
use Illuminate\Database\Eloquent\Relations\Pivot;
use OwenIt\Auditing\Contracts\Auditable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('financer')]
#[Group('integration')]
class FinancerIntegrationTest extends TestCase
{
    #[Test]
    public function it_is_a_pivot_model(): void
    {
        $financerIntegration = new FinancerIntegration;

        $this->assertInstanceOf(Pivot::class, $financerIntegration);
    }

    #[Test]
    public function it_uses_uuid_as_primary_key(): void
    {
        $financerIntegration = new FinancerIntegration;

        $this->assertTrue($financerIntegration->getIncrementing() === false);
        $this->assertEquals('string', $financerIntegration->getKeyType());
    }

    #[Test]
    public function it_has_correct_table_name(): void
    {
        $financerIntegration = new FinancerIntegration;

        $this->assertEquals('financer_integration', $financerIntegration->getTable());
    }

    #[Test]
    public function it_implements_auditable_interface(): void
    {
        $financerIntegration = new FinancerIntegration;

        $this->assertInstanceOf(Auditable::class, $financerIntegration);
    }

    #[Test]
    public function it_uses_cachable_trait(): void
    {
        $financerIntegration = new FinancerIntegration;

        $this->assertTrue(in_array('App\Traits\Cachable', class_uses($financerIntegration)));
    }

    #[Test]
    public function it_can_create_pivot_record(): void
    {
        $financer = Financer::factory()->create();
        $integration = Integration::factory()->create();

        $financerIntegration = new FinancerIntegration;
        $financerIntegration->financer_id = $financer->id;
        $financerIntegration->integration_id = $integration->id;
        $financerIntegration->active = true;
        $financerIntegration->save();

        $this->assertDatabaseHas('financer_integration', [
            'financer_id' => $financer->id,
            'integration_id' => $integration->id,
            'active' => true,
        ]);
    }

    #[Test]
    public function it_generates_uuid_on_creation(): void
    {
        $financer = Financer::factory()->create();
        $integration = Integration::factory()->create();

        $financerIntegration = FinancerIntegration::create([
            'financer_id' => $financer->id,
            'integration_id' => $integration->id,
            'active' => true,
        ]);

        $this->assertNotNull($financerIntegration->id);
        $this->assertIsString($financerIntegration->id);
        $this->assertEquals(36, strlen($financerIntegration->id)); // UUID length
    }
}
