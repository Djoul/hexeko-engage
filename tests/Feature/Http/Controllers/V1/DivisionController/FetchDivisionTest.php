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
class FetchDivisionTest extends ProtectedRouteTestCase
{
    use WithFaker;

    const URI = '/api/v1/divisions';

    protected function setUp(): void
    {
        parent::setUp();

    }

    #[Test]
    public function it_can_fetch_all_division(): void
    {

        ModelFactory::createDivision(count: 10);

        $response = $this->get(self::URI);

        $response->assertStatus(200);

        $this->assertDatabaseCount('divisions', 10);

    }
}
