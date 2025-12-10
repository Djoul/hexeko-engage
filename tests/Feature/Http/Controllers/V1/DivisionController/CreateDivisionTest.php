<?php

namespace Tests\Feature\Http\Controllers\V1\DivisionController;

use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Attributes\FlushTables;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[FlushTables(tables: ['divisions'], scope: 'class')]
#[Group('division')]
class CreateDivisionTest extends ProtectedRouteTestCase
{
    use WithFaker;

    protected $createDivisionAction;

    protected function setUp(): void
    {
        parent::setUp();

    }

    #[Test]
    public function it_can_create_division(): void
    {

        $this->assertDatabaseCount('divisions', 0);

        $divisionData = ModelFactory::makeDivision(data: ['name' => 'Division Test'])->toArray();
        $response = $this->post('/api/v1/divisions', $divisionData);

        $response->assertStatus(201);

        $this->assertDatabaseCount('divisions', 1);

        $this->assertDatabaseHas('divisions', ['name' => $divisionData['name']]);
    }
}
