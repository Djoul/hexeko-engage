<?php

namespace Tests\Helpers\Facades;

use Illuminate\Support\Facades\Facade;
use Tests\Helpers\ModelFactoryHelper;

class ModelFactory extends Facade
{
    protected static function getFacadeAccessor()
    {
        return ModelFactoryHelper::class;
    }
}
