<?php

use App\Http\Controllers\MetaPreviewController;
use Illuminate\Support\Facades\Route;

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
});

/*
|--------------------------------------------------------------------------
| SPA catch-all
|--------------------------------------------------------------------------
*/
Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');
