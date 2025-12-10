<?php

use App\Http\Controllers\ReverbTestController;
use App\Http\Controllers\V1\TestMailController;
use App\Integrations\InternalCommunication\Http\Controllers\ArticleChatController;
use App\Models\InvitedUser;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;

Route::get('/', function (): Factory|View {
    return view('welcome');
});
// Debug helper rendering the article generator stream directly; requires manual token setup to avoid 401.
Route::get('articles', [ArticleChatController::class, 'generate'])
    ->middleware('admin.enabled')
    ->name('articles.chat');

// Mail smoke-test endpoint kept for ops troubleshooting; guard behind admin.enabled toggle for staging/prod parity.
Route::get('/test-mail', [TestMailController::class, 'send'])
    ->middleware('admin.enabled');

Route::get('/register/{invitedUser}', function (InvitedUser $invitedUser): Factory|View {
    return view('custom-registration', ['invitedUser' => $invitedUser]);
})->name('custom.registration');

// Reverb test routes
Route::post('/test-reverb-public-message', [ReverbTestController::class, 'testPublicMessage']);
Route::post('/test-reverb-stats', [ReverbTestController::class, 'testStats']);
Route::post('/test-reverb-apideck', [ReverbTestController::class, 'testApideck']);
