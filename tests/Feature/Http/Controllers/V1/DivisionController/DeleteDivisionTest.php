<?php

namespace Tests\Feature\Http\Controllers\V1\DivisionController;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('division')]
class DeleteDivisionTest extends ProtectedRouteTestCase
{
    #[Test]
    public function it_can_delete_division(): void
    {
        $division = ModelFactory::createDivision();

        $this->assertDatabasehas('divisions', ['id' => $division['id'], 'deleted_at' => null]);

        $response = $this->delete("/api/v1/divisions/{$division->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('divisions', ['id' => $division['id'], 'deleted_at' => null]);
    }
}
