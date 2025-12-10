<?php

declare(strict_types=1);

use App\Integrations\Wellbeing\WellWo\Http\Controllers\WellWoProxyController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/wellbeing/wellwo')
    ->middleware(['auth.cognito', 'check.permission'])
    ->group(function (): void {
        Route::get('/programs', [WellWoProxyController::class, 'programs']);
        Route::get('/programs/{id}/videos', [WellWoProxyController::class, 'programVideos']);
        Route::get('/classes/disciplines', [WellWoProxyController::class, 'classes']);
        Route::get('classes/{id}/videos', [WellWoProxyController::class, 'classVideos']);
    });
