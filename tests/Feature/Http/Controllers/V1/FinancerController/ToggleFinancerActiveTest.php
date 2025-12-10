<?php

namespace Tests\Feature\Http\Controllers\V1\FinancerController;

use App\Models\Financer;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Attributes\FlushTables;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[FlushTables(tables: ['financers'], scope: 'test')]
#[Group('financer')]
class ToggleFinancerActiveTest extends ProtectedRouteTestCase
{
    use WithFaker;

    const URI = '/api/v1/financers/';

    protected function setUp(): void
    {
        parent::setUp();
    }

    #[Test]
    public function it_can_toggle_financer_active_status(): void
    {
        // Créer un financeur actif par défaut
        $financer = ModelFactory::createFinancer(['name' => 'Financer Test', 'active' => true]);

        $this->assertDatabaseCount('financers', 1);
        $this->assertDatabaseHas('financers', ['id' => $financer->id, 'active' => true]);

        // Désactiver le financeur
        $response = $this->put(self::URI."{$financer->id}/toggle-active", ['active' => false]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.active', false);

        $this->assertDatabaseHas('financers', ['id' => $financer->id, 'active' => false]);

        // Activer le financeur
        $response = $this->put(self::URI."{$financer->id}/toggle-active", ['active' => true]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.active', true);

        $financer->fresh();

        $this->assertCount(1, Financer::get());
        $this->assertDatabaseHas('financers', ['active' => true]);
    }

    #[Test]
    public function it_can_toggle_financer_active_status_without_providing_value(): void
    {
        // Créer un financeur actif par défaut
        $financer = ModelFactory::createFinancer(['name' => 'Financer Test', 'active' => true]);

        $this->assertDatabaseCount('financers', 1);
        $this->assertDatabaseHas('financers', ['id' => $financer->id, 'active' => true]);

        // Inverser l'état (sans fournir de valeur)
        $response = $this->put(self::URI."{$financer->id}/toggle-active");

        $response->assertStatus(200);
        $response->assertJsonPath('data.active', false);

        $this->assertDatabaseHas('financers', ['id' => $financer->id, 'active' => false]);

        // Inverser l'état à nouveau (sans fournir de valeur)
        $response = $this->put(self::URI."{$financer->id}/toggle-active");

        $response->assertStatus(200);
        $response->assertJsonPath('data.active', true);

        $this->assertDatabaseHas('financers', ['id' => $financer->id, 'active' => true]);
    }
}
