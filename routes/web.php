<?php

use App\Http\Controllers\MetaPreviewController;
use App\Http\Controllers\OpenAppLinkController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

/*
|--------------------------------------------------------------------------
| Broadcast Auth - Pusher calls /broadcasting/auth (not /api/broadcasting/auth)
|--------------------------------------------------------------------------
*/
Route::post('/broadcasting/auth', function () {
    return Broadcast::auth(request());
})->middleware('auth:api');

/*
|--------------------------------------------------------------------------
| Link Card Preview (og:meta) for crawlers - must be before catch-all
|--------------------------------------------------------------------------
*/
Route::middleware('crawler')->group(function () {
    Route::get('/', [MetaPreviewController::class, 'home']);
    Route::get('/clubs/{id}', [MetaPreviewController::class, 'club'])->where('id', '[0-9]+');
    Route::get('/tournament-detail/{id}', [MetaPreviewController::class, 'tournament'])->where('id', '[0-9]+');
    Route::get('/mini-tournament-detail/{id}', [MetaPreviewController::class, 'miniTournament'])->where('id', '[0-9]+');
    Route::get('/mini-match/{id}/verify', [MetaPreviewController::class, 'miniMatch'])->where('id', '[0-9]+');
    Route::get('/clubs/{clubId}/activities/{activityId}', [MetaPreviewController::class, 'clubActivity'])
        ->where(['clubId' => '[0-9]+', 'activityId' => '[0-9]+']);
    // Open App Link OG meta for Zalo / social media link previews
    Route::get('/l/tournament/{id}', [OpenAppLinkController::class, 'tournament']);
    Route::get('/l/mini_tournament/{id}', [OpenAppLinkController::class, 'miniTournament']);
    Route::get('/l/club/{id}', [OpenAppLinkController::class, 'club']);
    Route::get('/l/profile/{id}', [OpenAppLinkController::class, 'profile']);
});

/*
|--------------------------------------------------------------------------
| SPA catch-all
|--------------------------------------------------------------------------
*/
Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');
