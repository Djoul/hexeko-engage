<?php

namespace Tests\Feature\Http\Controllers\V1\ModuleController;

use App\Enums\IDP\RoleDefaults;
use App\Models\Division;
use App\Models\Financer;
use App\Models\Module;
use App\Models\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('module')]

class ModulePromotionTest extends ProtectedRouteTestCase
{
    private User $admin;

    private Financer $financer;

    private Division $division;

    private Module $module;

    #[Test]
    public function admin_can_promote_a_module_for_a_financer(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson(route('financer.module.promote'), [
                'financer_id' => $this->financer->id,
                'module_id' => $this->module->id,
            ]);
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Module promu pour le financeur avec succès',
            ]);
        $this->assertDatabaseHas('financer_module', [
            'financer_id' => $this->financer->id,
            'module_id' => $this->module->id,
            'promoted' => true,
        ]);
    }

    #[Test]
    public function cannot_promote_if_module_not_active_for_financer(): void
    {
        // Désactive le module pour le financeur
        $this->financer->modules()->updateExistingPivot($this->module->id, ['active' => false]);
        $response = $this->actingAs($this->admin)
            ->postJson(route('financer.module.promote'), [
                'financer_id' => $this->financer->id,
                'module_id' => $this->module->id,
            ]);
        $response->assertStatus(422);
        $this->assertDatabaseMissing('financer_module', [
            'financer_id' => $this->financer->id,
            'module_id' => $this->module->id,
            'promoted' => true,
        ]);
    }

    #[Test]
    public function admin_can_unpromote_a_module_for_a_financer(): void
    {
        $this->withoutExceptionHandling();
        // Promote d'abord
        $this->financer->modules()->updateExistingPivot($this->module->id, ['promoted' => true]);
        $response = $this->actingAs($this->admin)
            ->postJson(route('financer.module.unpromote'), [
                'financer_id' => $this->financer->id,
                'module_id' => $this->module->id,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Promotion annulée pour ce module et ce financeur',
            ]);
        $this->assertDatabaseHas('financer_module', [
            'financer_id' => $this->financer->id,
            'module_id' => $this->module->id,
            'promoted' => false,
        ]);
    }

    #[Test]
    public function cannot_unpromote_if_not_promoted(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson(route('financer.module.unpromote'), [
                'financer_id' => $this->financer->id,
                'module_id' => $this->module->id,
            ]);
        $response->assertStatus(422);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Create authenticated user with context
        $this->admin = $this->createAuthUser(
            role: RoleDefaults::HEXEKO_SUPER_ADMIN,
            withContext: true,
            returnDetails: true
        );

        // Get division and financer from parent class properties
        $this->division = $this->currentDivision;
        $this->financer = $this->currentFinancer;

        $this->module = Module::factory()->create();
        // Active le module pour la division et le financeur
        $this->division->modules()->attach($this->module->id, ['active' => true]);
        $this->financer->modules()->attach($this->module->id, ['active' => true, 'promoted' => false]);
        $this->financer->modules()->where('module_id', $this->module->id)->first();
    }
}
