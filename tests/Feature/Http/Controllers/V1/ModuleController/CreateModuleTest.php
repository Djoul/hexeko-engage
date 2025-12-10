<?php

namespace Tests\Feature\Http\Controllers\V1\ModuleController;

use App\Enums\IDP\RoleDefaults;
use App\Enums\ModulesCategories;
use App\Models\Module;
use DB;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('module')]
class CreateModuleTest extends ProtectedRouteTestCase
{
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        // Create authenticated user with context
        $this->auth = $this->createAuthUser(
            role: RoleDefaults::HEXEKO_SUPER_ADMIN,
            withContext: true
        );

        DB::table('modules')->delete();
        // todo flushtable
    }

    #[Test]
    public function it_can_create_module(): void
    {
        $initialCount = Module::count();

        $moduleData = Module::factory()->make([
            'name' => [
                'fr-FR' => 'Module Test',
                'en-US' => 'Module Test',
            ],
        ])->toArray();
        $response = $this->actingAs($this->auth)->postJson('/api/v1/modules', $moduleData);

        $response->assertStatus(201);

        $this->assertDatabaseCount('modules', $initialCount + 1);

        // Verify the module was created by fetching it from the database
        $createdModule = Module::where('category', $moduleData['category'])->first();
        $this->assertNotNull($createdModule);
        $this->assertEquals('Module Test', $createdModule->getTranslation('name', 'fr-FR'));
    }

    #[Test]
    public function it_can_create_module_with_category(): void
    {
        $initialCount = Module::count();
        $category = ModulesCategories::PURCHASING_POWER;
        $moduleData = Module::factory()->make([
            'name' => [
                'fr-FR' => 'Module Cat Test',
                'en-US' => 'Module Cat Test',
            ],
            'category' => $category,
        ])->toArray();

        $response = $this->actingAs($this->auth)->postJson('/api/v1/modules', $moduleData);

        $response->assertStatus(201);
        $this->assertDatabaseCount('modules', $initialCount + 1);

        // Verify the module was created with the correct category
        $createdModule = Module::where('category', $category)->first();
        $this->assertNotNull($createdModule);
        $this->assertEquals('Module Cat Test', $createdModule->getTranslation('name', 'fr-FR'));
        $this->assertEquals($category, $createdModule->category);
    }
}
