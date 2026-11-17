<?php

use App\Http\Controllers\PagesController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

require __DIR__.'/auth.php';

Route::get('auth/google/redirect', [\App\Http\Controllers\Auth\GoogleAuthController::class, 'redirect'])->name('google-auth');
Route::get('auth/google/callback', [\App\Http\Controllers\Auth\GoogleAuthController::class, 'callbackGoogle']);
Route::get('/gallery/{userId}', [\App\Http\Controllers\GalleryController::class, 'indexAuthorGallery'])
    ->where('userId', '[A-Za-z0-9\-_]+');

Route::middleware(['auth', 'verified'])
    ->name('profile.')
    ->prefix('my-profile')
    ->group(function () {
    Route::get('/', [ProfileController::class, 'index'])->name('index');
    Route::get('/edit', [ProfileController::class, 'edit'])->name('edit');
    Route::put('/update', [ProfileController::class, 'update'])->name('update');
    Route::delete('/', [ProfileController::class, 'destroy'])->name('destroy');
});

Route::get('/{slug?}', [PagesController::class, 'view'])
    ->where('slug', '[A-Za-z0-9\-/_]+')
    ->name('page');


