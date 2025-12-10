<?php

namespace Tests\Feature\Http\Controllers\V1\DivisionController;

use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('division')]
class FetchDivisionByIdTest extends ProtectedRouteTestCase
{
    use WithFaker;

    protected $createDivisionAction;

    protected function setUp(): void
    {
        parent::setUp();
    }

    #[Test]
    public function it_can_fetch_a_single_division(): void
    {
        $division = ModelFactory::createDivision(['name' => 'Division Test']);

        $response = $this->get('/api/v1/divisions/'.$division->id);

        $response->assertStatus(200);
    }
}
