<?php

namespace Tests\Feature\Http\Controllers\V1\IntegrationController;

use App\Models\Integration;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('integration')]
class DeleteIntegrationTest extends ProtectedRouteTestCase
{
    #[Test]
    public function it_can_delete_integration(): void
    {
        $integration = Integration::factory()->create();

        $this->assertDatabasehas('integrations', ['id' => $integration['id'], 'deleted_at' => null]);

        $response = $this->delete("/api/v1/integrations/{$integration->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('integrations', ['id' => $integration['id'], 'deleted_at' => null]);
    }
}
