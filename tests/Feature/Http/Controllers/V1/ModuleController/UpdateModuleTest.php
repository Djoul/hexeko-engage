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

class UpdateModuleTest extends ProtectedRouteTestCase
{
    use WithFaker;

    const URI = '/api/v1/modules/';

    protected function setUp(): void
    {
        parent::setUp();

        // Create authenticated user with context
        $this->auth = $this->createAuthUser(
            role: RoleDefaults::HEXEKO_SUPER_ADMIN,
            withContext: true
        );

        DB::table('modules')->delete();
    }

    #[Test]
    public function it_can_update_module(): void
    {
        $initialCount = Module::count();

        $module = Module::factory()
            ->create([
                'name' => [
                    'fr-FR' => 'Module Test',
                    'en-US' => 'Module Test',
                ],
            ]);

        $updatedData = [
            'id' => $module->id,
            'name' => [
                'fr-FR' => 'Module Test Updated',
                'en-US' => 'Module Test Updated',
            ],
            'description' => [
                'fr-FR' => 'Module Test Updated Description',
                'en-US' => 'Module Test Updated Description',
            ],
            'category' => $module->category,
            'active' => $module->active,
            'settings' => $module->settings,
        ];

        $this->assertDatabaseCount('modules', $initialCount + 1);
        $response = $this->actingAs($this->auth)->putJson(route('modules.update', ['module' => $module]), $updatedData);

        $response->assertStatus(200);

        $this->assertDatabaseCount('modules', $initialCount + 1);
    }

    #[Test]
    public function it_can_update_module_category(): void
    {
        $module = Module::factory()->create([
            'category' => ModulesCategories::PURCHASING_POWER,
        ]);
        $updatedCategory = ModulesCategories::WELLBEING;
        $updatedData = [
            'id' => $module->id,
            'category' => $updatedCategory,
            'name' => [
                'fr-FR' => 'Module Test Updated',
                'en-US' => 'Module Test Updated',
            ],
            'description' => [
                'fr-FR' => 'Module Test Updated Description',
                'en-US' => 'Module Test Updated Description',
            ],
            'active' => $module->active,
            'settings' => $module->settings,
        ];
        $response = $this->actingAs($this->auth)->putJson(route('modules.update', ['module' => $module]), $updatedData);
        $response->assertStatus(200);

        // Verify the module was updated by fetching it from the database
        $updatedModule = Module::find($module->id);
        $this->assertNotNull($updatedModule);
        $this->assertEquals($updatedCategory, $updatedModule->category);
        // Check if translations were properly set for the current locale
        $this->assertEquals('Module Test Updated', $updatedModule->getTranslation('name', 'fr-FR'));
        $this->assertEquals('Module Test Updated Description', $updatedModule->getTranslation('description', 'fr-FR'));
    }
}
