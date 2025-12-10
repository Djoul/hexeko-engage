<?php

namespace Tests\Unit\Services\Apideck;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('apideck')]
abstract class ApideckServiceTestCase extends BaseTestCase
{
    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        $app = require __DIR__.'/../../../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        // Disable Redis for testing
        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('queue.default', 'sync');

        return $app;
    }
}
