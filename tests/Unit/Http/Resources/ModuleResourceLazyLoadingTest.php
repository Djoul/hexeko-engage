<?php

namespace Tests\Unit\Http\Resources;

use App\Enums\IDP\RoleDefaults;
use App\Http\Resources\Module\ModuleResource;
use App\Models\Financer;
use App\Models\Module;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('resources')]
class ModuleResourceLazyLoadingTest extends ProtectedRouteTestCase
{
    #[Test]
    public function it_handles_lazy_loading_properly_in_get_financer_method(): void
    {
        $user = User::factory()->create();
        $financer = Financer::factory()->create();
        $module = Module::factory()->create();

        // Attach financer to user
        $user->financers()->attach($financer->id, [
            'active' => true,
            'role' => RoleDefaults::BENEFICIARY,
        ]);

        // Login as user
        Auth::login($user);

        // Clear request headers
        request()->headers->remove('x-financer-id');

        // Enable query log
        DB::enableQueryLog();

        // Create resource
        $resource = new ModuleResource($module);

        // Convert to array (this should not throw lazy loading exception)
        $result = $resource->toArray(request());

        // Verify the result
        $this->assertIsArray($result);
        $this->assertArrayHasKey('promoted', $result);

        // Verify queries made
        $queries = DB::getQueryLog();

        // Should have made a query to check count and get first financer
        $this->assertGreaterThan(0, count($queries));
    }

    #[Test]
    public function it_uses_header_financer_id_when_available(): void
    {
        $user = User::factory()->create();
        $financer = Financer::factory()->create();
        $module = Module::factory()->create();

        // Attach module to financer as promoted
        $financer->modules()->attach($module->id, ['promoted' => true, 'active' => true]);

        // Login as user
        Auth::login($user);

        // Set financer ID in header
        request()->headers->set('x-financer-id', $financer->id);

        // Create resource
        $resource = new ModuleResource($module);

        // Convert to array
        $result = $resource->toArray(request());

        // Verify the result
        $this->assertIsArray($result);
        $this->assertArrayHasKey('promoted', $result);
        $this->assertTrue($result['promoted']);
    }

    #[Test]
    public function it_returns_null_financer_when_user_has_multiple_financers(): void
    {
        $user = User::factory()->create();
        $financer1 = Financer::factory()->create();
        $financer2 = Financer::factory()->create();
        $module = Module::factory()->create();

        // Attach multiple financers to user
        $user->financers()->attach($financer1->id, [
            'active' => true,
            'role' => RoleDefaults::BENEFICIARY,
        ]);
        $user->financers()->attach($financer2->id, [
            'active' => true,
            'role' => RoleDefaults::BENEFICIARY,
        ]);

        // Login as user with preloaded financers
        $userWithRelations = User::with('financers')->find($user->id);
        Auth::login($userWithRelations);

        // Clear request headers
        request()->headers->remove('x-financer-id');

        // Create resource
        $resource = new ModuleResource($module);

        // Convert to array
        $result = $resource->toArray(request());

        // Verify the result
        $this->assertIsArray($result);
        $this->assertArrayHasKey('promoted', $result);
        $this->assertFalse($result['promoted']); // No financer, so not promoted
    }

    #[Test]
    public function it_includes_all_expected_fields(): void
    {
        $user = User::factory()->create();
        $module = Module::factory()->create([
            'name' => 'Test Module',
            'description' => 'Test Description',
            'active' => true,
            'category' => 'test_category',
        ]);

        // Pin the module for the user
        $user->pinnedModules()->attach($module->id);

        // Login as user with preloaded relations
        $userWithRelations = User::with(['financers', 'pinnedModules'])->find($user->id);
        Auth::login($userWithRelations);

        // Create resource
        $resource = new ModuleResource($module);

        // Convert to array
        $result = $resource->toArray(request());

        // Verify all expected fields are present
        $expectedFields = [
            'id', 'name', 'description', 'active', 'created_at',
            'updated_at', 'category', 'pinned', 'promoted',
        ];

        foreach ($expectedFields as $field) {
            $this->assertArrayHasKey($field, $result);
        }

        // Verify specific values
        $this->assertEquals('Test Module', $result['name']);
        $this->assertEquals('Test Description', $result['description']);
        $this->assertTrue($result['active']);
        $this->assertEquals('test_category', $result['category']);
        $this->assertTrue($result['pinned']);
    }
}
