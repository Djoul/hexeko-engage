<?php

use App\Integrations\Vouchers\Amilon\Http\Controllers\CategoryController;
use App\Integrations\Vouchers\Amilon\Http\Controllers\ContractController;
use App\Integrations\Vouchers\Amilon\Http\Controllers\MerchantController;
use App\Integrations\Vouchers\Amilon\Http\Controllers\MetricsController;
use App\Integrations\Vouchers\Amilon\Http\Controllers\OrderController;
use App\Integrations\Vouchers\Amilon\Http\Controllers\ProductController;

/**
 * @group Modules/Vouchers/Amilon
 *
 * @authenticated
 *
 * Routes de l'intégration Amilon pour les bons d'achat
 *
 * Ce module permet d'acheter et de gérer des bons d'achat multi-enseignes
 * via le fournisseur Amilon.
 */
Route::middleware(['auth.cognito', 'check.permission'])->group(function (): void {
    Route::group(['prefix' => 'api/v1/vouchers/amilon'], function (): void {
        // Merchants routes
        Route::get('merchants', [MerchantController::class, 'index']);
        Route::get('merchants/by-category', [MerchantController::class, 'byCategory']);
        Route::get('merchants/{id}', [MerchantController::class, 'show']);

        // Categories routes
        Route::get('retailers/categories', [CategoryController::class, 'index']);

        // products routes
        Route::get('merchants/{merchantId}/products', [ProductController::class, 'index']);

        // Orders routes
        Route::get('orders', [OrderController::class, 'index'])->name('vouchers.amilon.orders.index');
        Route::post('orders', [OrderController::class, 'store'])->name('vouchers.amilon.orders.store'); // Legacy endpoint
        Route::get('orders/{orderIdentifier}', [OrderController::class, 'show'])->name('vouchers.amilon.orders.show');
        Route::post('orders/{orderId}/retry', [OrderController::class, 'retry'])->name('vouchers.amilon.orders.retry');

        // Payment routes
        // @deprecated since 2025-08-06 This route is deprecated and will be removed in a future version
        Route::get('payment-options', [OrderController::class, 'paymentOptions'])->name('vouchers.amilon.payment-options');
        Route::post('purchase', [OrderController::class, 'purchase'])->name('vouchers.amilon.purchase');

        // Metrics routes
        Route::get('metrics', [MetricsController::class, 'index']);
        Route::get('metrics/total-purchase-volume', [MetricsController::class, 'totalPurchaseVolume']);
        Route::get('metrics/adoption-rate', [MetricsController::class, 'adoptionRate']);
        Route::get('metrics/average-amount-per-employee', [MetricsController::class, 'averageAmountPerEmployee']);
        Route::get('metrics/vouchers-purchased', [MetricsController::class, 'vouchersPurchased']);
        Route::get('metrics/top-merchants', [MetricsController::class, 'topMerchants']);

        // Contract routes
        Route::get('contract-info', [ContractController::class, 'show']);
    });
});

// Voucher routes (simplified path for easier access - redirect to main routes)
Route::middleware(['auth.cognito', 'check.permission'])->group(function (): void {
    Route::group(['prefix' => 'api/v1/vouchers'], function (): void {
        // Redirect to main Amilon routes for consistency
        Route::get('payment-options', fn () => redirect()->route('vouchers.amilon.payment-options'));
        Route::post('purchase', fn () => redirect()->route('vouchers.amilon.purchase'));
    });
});
