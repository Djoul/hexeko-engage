<?php

namespace Tests\Helpers\Providers;

use Illuminate\Support\ServiceProvider;
use Tests\Helpers\ModelFactoryHelper;

class TestsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            'modelfactory',
            function (): ModelFactoryHelper {
                return new ModelFactoryHelper;
            }
        );
    }
}
