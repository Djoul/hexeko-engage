<?php

namespace Tests\Feature\Http\Controllers\V1\FinancerController;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('financer')]

class DeleteFinancerTest extends ProtectedRouteTestCase
{
    #[Test]
    public function it_can_delete_financer(): void
    {
        $financer = ModelFactory::createFinancer();

        $this->assertDatabasehas('financers', ['id' => $financer['id'], 'deleted_at' => null]);

        $response = $this->delete("/api/v1/financers/{$financer->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('financers', ['id' => $financer['id'], 'deleted_at' => null]);
    }
}
