<?php

return [
    App\Providers\ApideckServiceProvider::class,
    App\Providers\AppServiceBindingProvider::class,
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthorizationServiceProvider::class,
    App\Providers\AdminPanelServiceProvider::class,
    App\Providers\EventServiceProvider::class,
    App\Providers\HorizonServiceProvider::class,
    App\Providers\IntegrationMigrationServiceProvider::class,
    App\Providers\LLMServiceProvider::class,
    App\Providers\MinioFilesystemServiceProvider::class,
    App\Providers\StripeServiceProvider::class,
    OwenIt\Auditing\AuditingServiceProvider::class,
];
