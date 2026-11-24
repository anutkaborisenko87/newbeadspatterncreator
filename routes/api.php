<?php

use App\Http\Controllers\API\V1\PagesController;
use App\Http\Controllers\PatternCommentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/', [PagesController::class, 'getPageData'])
    ->name('home-page-api-data');

Route::get('/patterns/{discussionPattern}/comments', [PatternCommentController::class, 'feed'])
    ->middleware('throttle:120,1,discussion-feed:')->where('discussionPattern', '[0-9a-z]+[0-9a-f]{12}')->name('patterns.comments.feed');

Route::get('/{slug?}', [PagesController::class, 'getPageData'])
    ->where('slug', '[A-Za-z0-9\-/_]+')
    ->name('page-api-data');
