<?php

use App\Enums\FinancerMetricType;
use App\Enums\Languages;
use App\Http\Controllers\Auth\LoginApiController;
use App\Http\Controllers\Auth\LogoutApiController;
use App\Http\Controllers\Auth\MeApiController;
use App\Http\Controllers\Auth\RolesAndPermissionsApiController;
use App\Http\Controllers\TestReverbController;
use App\Http\Controllers\TranslationMigrationController;
use App\Http\Controllers\V1\AiController;
use App\Http\Controllers\V1\Apideck\SIRHEmployeeController;
use App\Http\Controllers\V1\Apideck\VaultSessionController;
use App\Http\Controllers\V1\Apideck\WebhookController;
use App\Http\Controllers\V1\CognitoNotificationController;
use App\Http\Controllers\V1\ContractTypeController;
use App\Http\Controllers\V1\DepartmentController;
use App\Http\Controllers\V1\DivisionController;
use App\Http\Controllers\V1\FinancerController;
use App\Http\Controllers\V1\FinancerMetricsController;
use App\Http\Controllers\V1\GenderController;
use App\Http\Controllers\V1\IntegrationController;
use App\Http\Controllers\V1\InvitedUserController;
use App\Http\Controllers\V1\InvoiceController;
use App\Http\Controllers\V1\InvoiceExportController;
use App\Http\Controllers\V1\InvoiceItemController;
use App\Http\Controllers\V1\JobLevelController;
use App\Http\Controllers\V1\JobTitleController;
use App\Http\Controllers\V1\Me\DashboardController;
use App\Http\Controllers\V1\MergeUserController;
use App\Http\Controllers\V1\MobileVersionController;
use App\Http\Controllers\V1\ModuleController;
use App\Http\Controllers\V1\PermissionController;
use App\Http\Controllers\V1\Push\NotificationTopicController;
use App\Http\Controllers\V1\Push\OneSignalWebhookController;
use App\Http\Controllers\V1\Push\RegisterDeviceController;
use App\Http\Controllers\V1\Push\UnregisterDeviceController;
use App\Http\Controllers\V1\Push\UpdatePreferencesController;
use App\Http\Controllers\V1\RoleController;
use App\Http\Controllers\V1\SegmentController;
use App\Http\Controllers\V1\SegmentFilterController;
use App\Http\Controllers\V1\SegmentUserController;
use App\Http\Controllers\V1\SiteController;
use App\Http\Controllers\V1\SmsController;
use App\Http\Controllers\V1\TagController;
use App\Http\Controllers\V1\TeamController;
use App\Http\Controllers\V1\TestMailController;
use App\Http\Controllers\V1\TrackMetricsEventController;
use App\Http\Controllers\V1\TranslationController;
use App\Http\Controllers\V1\TranslationsJsonController;
use App\Http\Controllers\V1\User\StoreUserController;
use App\Http\Controllers\V1\User\ToggleUserActivationController;
use App\Http\Controllers\V1\User\UserAttributeController;
use App\Http\Controllers\V1\User\UserImageController;
use App\Http\Controllers\V1\User\UserIndexController;
use App\Http\Controllers\V1\User\UserProfileImageController;
use App\Http\Controllers\V1\User\UserRolesController;
use App\Http\Controllers\V1\User\UserSettingsController;
use App\Http\Controllers\V1\User\UserShowController;
use App\Http\Controllers\V1\User\UserSoftDeleteController;
use App\Http\Controllers\V1\User\UserUpdateController;
use App\Http\Controllers\V1\User\UserWelcomeEmailController;
use App\Http\Controllers\V1\WebhookCognitoController;
use App\Http\Controllers\V1\WorkModeController;
use App\Http\Middleware\CognitoThrottleMiddleware;
use App\Http\Middleware\HmacAuthMiddleware;
use App\Integrations\Payments\Stripe\Controllers\StripeController;
use App\Integrations\Payments\Stripe\Http\Controllers\StripeWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'v1'], function (): void {

    Route::post('/login', LoginApiController::class);

    // Public routes for getting available languages in login screen
    Route::get('/available-languages', function () {
        return response()->json(Languages::asSelectObjectFromSettings());
    });

    Route::get('/opened', function () {
        return response()->json(['message' => 'opened route']);
    });

    // Broadcasting authentication route with Cognito middleware
    Route::post('/broadcasting/auth', function (Request $request) {
        return Broadcast::auth($request);
    })->middleware('auth.cognito');

    // Public routes for registration and user merging

    // todo check for route auth.
    Route::get('/invited-users', [InvitedUserController::class, 'index'])->name('invited-users.index');
    Route::get('/invited-users/{uuid}', [InvitedUserController::class, 'show'])->name('invited-users.show');
    Route::put('/invited-users/{uuid}', [InvitedUserController::class, 'update'])->name('invited-users.update');
    Route::delete('/invited-users/{uuid}', [InvitedUserController::class, 'destroy'])->name('invited-users.destroy');
    Route::post('/merge-user', [MergeUserController::class, 'merge'])->name('merge-user');

    Route::post('/webhook/cognito/post-signup', [WebhookCognitoController::class, 'handle'])->name('webhook.cognito');
    Route::post('/webhooks/apideck', [WebhookController::class, 'handle'])->name('webhooks.apideck');

    // Cognito Notification Webhooks (AWS Lambda proxies)
    Route::post('/cognito-notifications/send-sms', [CognitoNotificationController::class, 'sendSms'])
        ->middleware([HmacAuthMiddleware::class, CognitoThrottleMiddleware::class.':sms'])
        ->name('cognito.notifications.sms');

    Route::post('/cognito-notifications/send-email', [CognitoNotificationController::class, 'sendEmail'])
        ->middleware([HmacAuthMiddleware::class, CognitoThrottleMiddleware::class.':email'])
        ->name('cognito.notifications.email');

    // Stripe webhook (public - no auth required)
    // Note: Using Http\Controllers namespace
    Route::post('/payments/stripe/webhook', [StripeWebhookController::class, 'handleWebhook'])->name('stripe.webhook');

    // Test Reverb routes (for debugging)
    Route::post('/test/reverb/send', [TestReverbController::class, 'sendMessage'])->name('test.reverb.send');
    Route::get('/test/reverb/connection', [TestReverbController::class, 'testConnection'])->name('test.reverb.connection');

    // protected route group
    Route::middleware(['auth.cognito', 'check.active.financer', 'check.permission'])->group(function (): void {
        Route::post('/metrics/track', [TrackMetricsEventController::class, 'handle']);
        // region Auth & Permissions -->

        Route::get('me', MeApiController::class)
            ->withoutMiddleware('check.permission')->name('me');

        Route::get('me/dashboard', [DashboardController::class, 'index'])->name('me.dashboard.index');

        Route::get('roles-and-permissions', RolesAndPermissionsApiController::class)
            ->withoutMiddleware('check.permission')->name('roles-and-permissions');

        Route::resource('teams', TeamController::class)
            ->except(['edit', 'create']);

        Route::resource('roles', RoleController::class)
            ->except(['edit', 'create']);

        Route::resource('permissions', PermissionController::class)
            ->except(['edit', 'create']);

        Route::post('/roles/{role}/permissions/{permission}', [RoleController::class, 'addPermissionToRole'])
            ->name('roles.add_permission');

        Route::delete('/roles/{role}/permissions/{permission}', [RoleController::class, 'removePermissionFromRole'])
            ->name('roles.remove_permission');

        Route::post('/logout', LogoutApiController::class);
        // endregion--

        // region Division -->
        Route::resource('divisions', DivisionController::class)
            ->except(['edit', 'create']);
        // endregion

        // region Financer -->
        Route::resource('financers', FinancerController::class)
            ->except(['edit', 'create']);
        Route::put('/financers/{id}/toggle-active', [FinancerController::class, 'toggleActive'])
            ->name('financers.toggle_active');
        // endregion

        // region Financer Metrics -->
        Route::prefix('financers/metrics')->group(function (): void {

            // All metrics endpoint - returns all available metrics
            Route::get('/all', [FinancerMetricsController::class, 'getAllMetrics'])
                ->name('financer.metrics.all');

            // Dashboard endpoint - returns 4 key metrics as AllMetrics object
            Route::get('/dashboard', [FinancerMetricsController::class, 'dashboard'])
                ->name('financer.metrics.dashboard');

            // Individual metric endpoints - returns single IMetric object
            Route::get('/{metricType}', [FinancerMetricsController::class, 'getMetric'])
                ->name('financer.metrics.individual')
                ->where('metricType', FinancerMetricType::getRoutePattern(onlyActive: false));

        });
        // endregion

        // region Users ->
        Route::get('/users', UserIndexController::class)->name('users.index');
        Route::get('/users/attributes', UserAttributeController::class)->name('users.attributes');
        Route::get('/users/images', UserImageController::class)->name('users.images');
        Route::get('/users/{id}', UserShowController::class)->name('users.show');
        Route::post('/users', StoreUserController::class)->name('users.store');
        Route::put('/users/{id}', UserUpdateController::class)->name('users.update');
        Route::delete('/users/{id}', UserSoftDeleteController::class)->name('users.destroy');
        Route::put('/user/{id}/toggle-activation/{financer_id}', ToggleUserActivationController::class)
            ->name('users.toggle-activation');

        Route::post('/users/{user}/assign-role/{role}', [UserRolesController::class, 'assignRole'])
            ->name('user.assign_role');

        Route::delete('/users/{user}/remove-role/{role}', [UserRolesController::class, 'removeRole'])
            ->name('user.remove_role');

        Route::post('/users/{id}/roles/sync', [UserRolesController::class, 'syncRoles'])
            ->name('user.sync_roles');

        Route::post('/users/{user}/resend-welcome-email', [UserWelcomeEmailController::class, 'resendWelcomeEmail'])
            ->name('user.resend_welcome_email');

        Route::put('/users/{user}/settings', [UserSettingsController::class, '__invoke'])
            ->name('user.update_settings');

        Route::post('/user/profile-image', [UserProfileImageController::class, 'update'])
            ->middleware('auth.cognito')
            ->name('user.profile-image.update');

        Route::post('/invited-users', [InvitedUserController::class, 'store'])
            ->name('invited-users.store');

        // Primary import endpoint (supports CSV, XLS, XLSX)
        Route::post('/invited-users/import', [InvitedUserController::class, 'import'])
            ->name('invited-users.import');

        // Deprecated: Use /invited-users/import instead may be delete on 1 decembre 2025
        Route::post('/invited-users/import-csv', [InvitedUserController::class, 'import'])
            ->name('invited-users.import-csv');
        //  endregion

        // region ApiDeck -->

        Route::group(['prefix' => '/SIRH/employees'], function (): void {
            Route::get('/', [SIRHEmployeeController::class, 'index'])->name('employees.index');
            Route::get('/{id}', [SIRHEmployeeController::class, 'show'])->name('employees.show');
            Route::post('/sync', [SIRHEmployeeController::class, 'sync'])->name('employees.sync');
        });

        // endregion

        // region Vault -->

        Route::group(['prefix' => '/vault'], function (): void {
            Route::post('/sessions', [VaultSessionController::class, 'store'])->name('vault.sessions.store');
        });

        // endregion

        // region Module ->
        Route::resource('modules', ModuleController::class)
            ->except(['edit', 'create']);
        // endregion

        Route::middleware('tenant.guard:financer,division')->group(function (): void {

            Route::post('/ai/generate', [AiController::class, 'generate'])
                ->middleware(['check.credit:ai_token,App\\Estimators\\AiTokenEstimator']);

            Route::post('/sms/send', [SmsController::class, 'send'])
                ->middleware(['check.credit:sms,10']);

            // region Invoicing

            Route::apiResource('invoices', InvoiceController::class);
            Route::post('invoices/{invoice}/confirm', [InvoiceController::class, 'confirm'])->name('invoices.confirm');
            Route::post('invoices/{invoice}/mark-sent', [InvoiceController::class, 'markSent'])->name('invoices.mark-sent');
            Route::post('invoices/{invoice}/mark-paid', [InvoiceController::class, 'markPaid'])->name('invoices.mark-paid');
            Route::post('invoices/bulk/update-status', [InvoiceController::class, 'bulkUpdateStatus'])->name('invoices.bulk-update-status');

            Route::get('invoices/{invoice}/items', [InvoiceItemController::class, 'index'])->name('invoice-items.index');
            Route::post('invoices/{invoice}/items', [InvoiceItemController::class, 'store'])->name('invoice-items.store');
            Route::put('invoices/{invoice}/items/{item}', [InvoiceItemController::class, 'update'])->name('invoice-items.update');
            Route::delete('invoices/{invoice}/items/{item}', [InvoiceItemController::class, 'destroy'])->name('invoice-items.destroy');

            Route::get('invoices/export/excel', [InvoiceExportController::class, 'excel'])->name('invoices.export.excel');
            Route::get('invoices/{invoice}/pdf', [InvoiceExportController::class, 'pdf'])->name('invoices.export.pdf');
            Route::get('invoices/{invoice}/export/user-billing', [InvoiceExportController::class, 'userBilling'])->name('invoices.export.user-billing');
            Route::post('invoices/{invoice}/send-email', [InvoiceExportController::class, 'sendEmail'])->name('invoices.export.send-email');
            // endregion

            // region Modules activation and pricing

            // region Division
            Route::post('/modules/division/activate', [ModuleController::class, 'activateForDivision'])
                ->name('division.module.activate');
            Route::post('/modules/division/deactivate', [ModuleController::class, 'deactivateForDivision'])
                ->name('division.module.deactivate');
            Route::post('/modules/division/toggle', [ModuleController::class, 'toggleForDivision'])
                ->name('division.module.toggle');
            Route::post('/modules/division/bulk-toggle', [ModuleController::class, 'bulkToggleForDivision'])
                ->name('division.module.bulk-toggle');

            Route::get('/divisions/{division}/modules', [ModuleController::class, 'listDivisionModules'])
                ->name('divisions.modules.index');
            Route::put('/divisions/{division}/modules', [ModuleController::class, 'updateDivisionModules'])
                ->name('divisions.modules.update');
            Route::put('/divisions/{division}/core-price', [ModuleController::class, 'updateDivisionCorePrice'])
                ->name('divisions.core-price.update');

            // endregion

            // region Financer

            Route::post('/modules/financer/activate', [ModuleController::class, 'activateForFinancer'])
                ->name('financer.module.activate');
            Route::post('/modules/financer/deactivate', [ModuleController::class, 'deactivateForFinancer'])
                ->name('financer.module.deactivate');
            Route::post('/modules/financer/bulk-toggle', [ModuleController::class, 'bulkToggleForFinancer'])
                ->name('financer.module.bulk-toggle');
            Route::post('/modules/financer/promote', [ModuleController::class, 'promoteForFinancer'])
                ->name('financer.module.promote');
            Route::post('/modules/financer/unpromote', [ModuleController::class, 'unpromoteForFinancer'])
                ->name('financer.module.unpromote');

            Route::get('/financers/{financer}/modules', [ModuleController::class, 'listFinancerModules'])
                ->name('financers.modules.index');
            Route::put('/financers/{financer}/modules', [ModuleController::class, 'updateFinancerModules'])
                ->name('financers.modules.update');
            Route::put('/financers/{financer}/core-price', [ModuleController::class, 'updateFinancerCorePrice'])
                ->name('financers.core-price.update');

            // endregion
            Route::post('/modules/pin', [ModuleController::class, 'pinForUser'])
                ->name('user.module.pin');

            Route::post('/modules/unpin', [ModuleController::class, 'unpinForUser'])
                ->name('user.module.unpin');
            // endregion

        });

        // region Integration ->
        Route::resource('integrations', IntegrationController::class)
            ->except(['edit', 'create']);

        Route::post('/integrations/division/activate', [IntegrationController::class, 'activateForDivision'])
            ->name('division.integration.activate');
        Route::post('/integrations/division/deactivate', [IntegrationController::class, 'deactivateForDivision'])
            ->name('division.integration.deactivate');
        Route::post('/integrations/division/toggle', [IntegrationController::class, 'toggleForDivision'])
            ->name('division.integration.toggle');
        Route::post('/integrations/division/bulk-toggle', [IntegrationController::class, 'bulkToggleForDivision'])
            ->name('division.integration.bulk-toggle');

        Route::post('/integrations/financer/activate', [IntegrationController::class, 'activateForFinancer'])
            ->name('financer.integration.activate');
        Route::post('/integrations/financer/deactivate', [IntegrationController::class, 'deactivateForFinancer'])
            ->name('financer.integration.deactivate');
        // endregion

        Route::post('/test-mail', [TestMailController::class, 'send']);

        // region Translation Migrations -->
        Route::prefix('translation-migrations')->group(function (): void {
            Route::get('/', [TranslationMigrationController::class, 'index'])->name('translation-migrations.index');
            Route::get('/{translationMigration}', [TranslationMigrationController::class, 'show'])->name('translation-migrations.show');
            Route::post('/{translationMigration}/apply', [TranslationMigrationController::class, 'apply'])->name('translation-migrations.apply');
            Route::post('/{translationMigration}/rollback', [TranslationMigrationController::class, 'rollback'])->name('translation-migrations.rollback');
            Route::post('/sync', [TranslationMigrationController::class, 'sync'])->name('translation-migrations.sync');
        });
        // endregion

        // region Payments -->
        Route::prefix('payments/stripe')->group(function (): void {
            Route::post('/checkout', [StripeController::class, 'createCheckoutSession'])->name('stripe.checkout');
        });
        // endregion

        // region Push Notifications -->
        Route::prefix('push')->group(function (): void {
            Route::get('/devices', [RegisterDeviceController::class, 'index'])
                ->name('push.devices.index');
            Route::post('/devices/register', [RegisterDeviceController::class, 'store'])
                ->name('push.devices.register');
            Route::delete('/devices/{subscription_id}', [UnregisterDeviceController::class, 'destroy'])
                ->name('push.devices.unregister');
            Route::post('/devices/batch-unregister', [UnregisterDeviceController::class, 'batchUnregister'])
                ->name('push.devices.batch-unregister');
            Route::get('/preferences/{subscription_id}', [UpdatePreferencesController::class, 'show'])
                ->name('push.preferences.show');
            Route::put('/preferences', [UpdatePreferencesController::class, 'update'])
                ->name('push.preferences.update');
            Route::put('/preferences/{subscription_id}', [UpdatePreferencesController::class, 'update'])
                ->name('push.preferences.update.device');

            // Notification Topics
            Route::get('/topics', [NotificationTopicController::class, 'index'])
                ->name('push.topics.index');
            Route::get('/topics/subscriptions', [NotificationTopicController::class, 'userSubscriptions'])
                ->name('push.topics.subscriptions');
            Route::post('/topics/{topic}/subscribe', [NotificationTopicController::class, 'subscribe'])
                ->name('push.topics.subscribe');
            Route::post('/topics/{topic}/unsubscribe', [NotificationTopicController::class, 'unsubscribe'])
                ->name('push.topics.unsubscribe');
            Route::post('/topics/bulk-subscribe', [NotificationTopicController::class, 'bulkSubscribe'])
                ->name('push.topics.bulk-subscribe');
        });
        // endregion

        // region Departments -->
        Route::apiResource('departments', DepartmentController::class)->middleware('check.allowed.financer');
        Route::apiResource('sites', SiteController::class)->middleware('check.allowed.financer');
        Route::apiResource('contract-types', ContractTypeController::class)->middleware('check.allowed.financer');
        Route::apiResource('tags', TagController::class)->middleware('check.allowed.financer');
        Route::apiResource('work-modes', WorkModeController::class)->middleware('check.allowed.financer');
        Route::apiResource('job-titles', JobTitleController::class)->middleware('check.allowed.financer');
        Route::apiResource('job-levels', JobLevelController::class)->middleware('check.allowed.financer');
        // endregion

        // region Segments -->
        Route::apiResource('segments', SegmentController::class)->middleware('check.allowed.financer');
        Route::get('segments/{segment}/users', [SegmentUserController::class, 'index'])->name('segments.users.index')->middleware('check.allowed.financer');
        Route::get('segments/{segment}/users/computed', [SegmentUserController::class, 'computed'])->name('segments.users.computed')->middleware('check.allowed.financer');
        Route::get('segment-filters', [SegmentFilterController::class, 'index'])->name('segment-filters.index')->middleware('check.allowed.financer');
        // endregion

        // region Gender -->
        Route::get('genders', [GenderController::class, 'index'])->name('genders.index')->middleware('check.allowed.financer');
        // endregion
    });

    // OneSignal Webhook (outside auth middleware, uses its own validation)
    Route::post('/push/webhooks/onesignal', [OneSignalWebhookController::class, '__invoke'])
        ->name('push.webhook.onesignal');

    // always accessible unAuth...
    Route::get('translations/json', [TranslationsJsonController::class, 'allLocales']);
    Route::get('translations/json/{locale}', [TranslationsJsonController::class, 'forLocale']);

    // will be accessible only for admin users later
    Route::get('translations/export', [TranslationController::class, 'export']);
    Route::post('translations/import', [TranslationController::class, 'import']);
    Route::apiResource('translations', TranslationController::class);
    Route::post('translations/{translation_key_id}/values', [TranslationController::class, 'createTranslationValue']);
    Route::put('translations/{translation_key_id}/values', [TranslationController::class, 'updateTranslationValue']);

    Route::post('mobile-version/check', [MobileVersionController::class, 'checkUpdateStatus'])->name('mobile-version.check-update-status');
});
