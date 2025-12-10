<?php

namespace Tests\Unit;

use App;
use App\Models\User;
use PHPUnit\Framework\Attributes\Group;
use Tests\ProtectedRouteTestCase;

#[Group('environment')]
#[Group('system')]
class CheckEnvironmentTest extends ProtectedRouteTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_environment_is_testing(): void
    {

        $this->assertTrue(App::environment() === 'testing');
        $this->assertTrue(env('DB_CONNECTION') === 'pgsql');

        $this->assertTrue((new User)->getConnection()->getDatabaseName() === 'db_engage_testing', 'DB_DATABASE is not set to db_engage_testing');
        $this->assertTrue(config('database.default') === 'pgsql');
        $this->assertTrue(config('database.connections.pgsql.database') === 'db_engage_testing');
    }
}
