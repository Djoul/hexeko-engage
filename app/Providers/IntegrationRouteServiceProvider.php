<?php

namespace App\Providers;

use File;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

/**
 * @deprecated
 */
class IntegrationRouteServiceProvider extends ServiceProvider
{
    const VERSION_NUMBER = 'v1';

    public function boot(): void
    {
        parent::boot();

        $integrationsPath = base_path('app/Integrations');

        if (! File::exists($integrationsPath)) {
            return;
        }
        $directories = File::allFiles($integrationsPath);
        $apiRoutes = [];

        foreach ($directories as $file) {
            if ($file->getFilename() === 'api.php' && str_contains($file->getPath(), 'Routes')) {
                $apiRoutes[] = $file->getPathname();
            }
        }
        foreach ($apiRoutes as $integrationRoutes) {
            $parts = explode(DIRECTORY_SEPARATOR, $integrationRoutes);
            $integrationName = $parts[count($parts) - 3] ?? null;
            if (! in_array($integrationName, [null, '', '0'], true)) {
                Route::middleware(['auth.cognito', 'check.permission'])
                    ->group($integrationRoutes);
            }
        }

    }
}
