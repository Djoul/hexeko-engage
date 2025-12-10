<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Division;
use App\Models\DivisionModule;
use App\Models\Module;
use Illuminate\Database\Eloquent\Relations\Pivot;
use OwenIt\Auditing\Contracts\Auditable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('division')]
#[Group('module')]
#[Group('module-models')]
class DivisionModuleTest extends TestCase
{
    #[Test]
    public function it_is_a_pivot_model(): void
    {
        $divisionModule = new DivisionModule;

        $this->assertInstanceOf(Pivot::class, $divisionModule);
    }

    #[Test]
    public function it_uses_uuid_as_primary_key(): void
    {
        $divisionModule = new DivisionModule;

        $this->assertTrue($divisionModule->getIncrementing() === false);
        $this->assertEquals('string', $divisionModule->getKeyType());
    }

    #[Test]
    public function it_has_correct_table_name(): void
    {
        $divisionModule = new DivisionModule;

        $this->assertEquals('division_module', $divisionModule->getTable());
    }

    #[Test]
    public function it_implements_auditable_interface(): void
    {
        $divisionModule = new DivisionModule;

        $this->assertInstanceOf(Auditable::class, $divisionModule);
    }

    #[Test]
    public function it_uses_cachable_trait(): void
    {
        $divisionModule = new DivisionModule;

        $this->assertTrue(in_array('App\Traits\Cachable', class_uses($divisionModule)));
    }

    #[Test]
    public function it_can_create_pivot_record(): void
    {
        $division = Division::factory()->create();
        $module = Module::factory()->create();

        $divisionModule = new DivisionModule;
        $divisionModule->division_id = $division->id;
        $divisionModule->module_id = $module->id;
        $divisionModule->active = true;
        $divisionModule->save();

        $this->assertDatabaseHas('division_module', [
            'division_id' => $division->id,
            'module_id' => $module->id,
            'active' => true,
        ]);
    }

    #[Test]
    public function it_generates_uuid_on_creation(): void
    {
        $division = Division::factory()->create();
        $module = Module::factory()->create();

        $divisionModule = DivisionModule::create([
            'division_id' => $division->id,
            'module_id' => $module->id,
            'active' => true,
        ]);

        $this->assertNotNull($divisionModule->id);
        $this->assertIsString($divisionModule->id);
        $this->assertEquals(36, strlen($divisionModule->id)); // UUID length
    }
}
