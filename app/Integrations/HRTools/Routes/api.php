<?php

use App\Integrations\HRTools\Http\Controllers\LinkController;
use App\Integrations\HRTools\Http\Controllers\LinkPinController;
use App\Integrations\HRTools\Http\Controllers\LinkReorderController;

/**
 * @group Modules/HRTools
 *
 * @authenticated
 *
 * Routes de l'intégration HRTools
 *
 * Ce module permet de gérer les liens vers des outils et ressources externes
 * dans le cadre de l'intégration HRTools.
 */
Route::middleware(['auth.cognito', 'check.permission', 'tenant.guard:financer,division'])->group(function (): void {
    Route::group(['prefix' => 'api/v1/hr-tools'], function (): void {
        Route::resource('links', LinkController::class);
        Route::post('links/reorder', [LinkReorderController::class, 'reorder'])->name('links.reorder');
        Route::post('links/{id}/toggle-pin', [LinkPinController::class, 'toggle'])->name('links.toggle-pin');
    });
});
