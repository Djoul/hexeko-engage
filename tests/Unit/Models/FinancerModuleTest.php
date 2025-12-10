<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Financer;
use App\Models\FinancerModule;
use App\Models\Module;
use Illuminate\Database\Eloquent\Relations\Pivot;
use OwenIt\Auditing\Contracts\Auditable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('financer')]
#[Group('module')]
#[Group('financer-models')]
#[Group('module-models')]
class FinancerModuleTest extends TestCase
{
    #[Test]
    public function it_is_a_pivot_model(): void
    {
        $financerModule = new FinancerModule;

        $this->assertInstanceOf(Pivot::class, $financerModule);
    }

    #[Test]
    public function it_uses_uuid_as_primary_key(): void
    {
        $financerModule = new FinancerModule;

        $this->assertTrue($financerModule->getIncrementing() === false);
        $this->assertEquals('string', $financerModule->getKeyType());
    }

    #[Test]
    public function it_has_correct_table_name(): void
    {
        $financerModule = new FinancerModule;

        $this->assertEquals('financer_module', $financerModule->getTable());
    }

    #[Test]
    public function it_implements_auditable_interface(): void
    {
        $financerModule = new FinancerModule;

        $this->assertInstanceOf(Auditable::class, $financerModule);
    }

    #[Test]
    public function it_uses_cachable_trait(): void
    {
        $financerModule = new FinancerModule;

        $this->assertTrue(in_array('App\Traits\Cachable', class_uses($financerModule)));
    }

    #[Test]
    public function it_has_correct_casts(): void
    {
        $financerModule = new FinancerModule;
        $casts = $financerModule->getCasts();

        $this->assertArrayHasKey('active', $casts);
        $this->assertEquals('bool', $casts['active']);
        $this->assertArrayHasKey('promoted', $casts);
        $this->assertEquals('bool', $casts['promoted']);
    }

    #[Test]
    public function it_can_create_pivot_record_with_attributes(): void
    {
        $financer = Financer::factory()->create();
        $module = Module::factory()->create();

        $financerModule = new FinancerModule;
        $financerModule->financer_id = $financer->id;
        $financerModule->module_id = $module->id;
        $financerModule->active = true;
        $financerModule->promoted = false;
        $financerModule->save();

        $this->assertDatabaseHas('financer_module', [
            'financer_id' => $financer->id,
            'module_id' => $module->id,
            'active' => true,
            'promoted' => false,
        ]);
    }

    #[Test]
    public function it_generates_uuid_on_creation(): void
    {
        $financer = Financer::factory()->create();
        $module = Module::factory()->create();

        $financerModule = FinancerModule::create([
            'financer_id' => $financer->id,
            'module_id' => $module->id,
            'active' => true,
            'promoted' => true,
        ]);

        $this->assertNotNull($financerModule->id);
        $this->assertIsString($financerModule->id);
        $this->assertEquals(36, strlen($financerModule->id)); // UUID length
    }

    #[Test]
    public function it_casts_boolean_fields_correctly(): void
    {
        $financer = Financer::factory()->create();
        $module = Module::factory()->create();

        $financerModule = FinancerModule::create([
            'financer_id' => $financer->id,
            'module_id' => $module->id,
            'active' => 1,
            'promoted' => 0,
        ]);

        $this->assertTrue($financerModule->active);
        $this->assertFalse($financerModule->promoted);
        $this->assertIsBool($financerModule->active);
        $this->assertIsBool($financerModule->promoted);
    }
}
