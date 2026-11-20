<?php

use App\Http\Controllers\EditorDraftController;
use App\Http\Controllers\BeadSpeechController;
use App\Http\Controllers\PagesController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

require __DIR__.'/auth.php';

Route::get('auth/google/redirect', [\App\Http\Controllers\Auth\GoogleAuthController::class, 'redirect'])->name('google-auth');
Route::get('auth/google/callback', [\App\Http\Controllers\Auth\GoogleAuthController::class, 'callbackGoogle']);
Route::get('/gallery/{userId}', [\App\Http\Controllers\GalleryController::class, 'indexAuthorGallery'])
    ->where('userId', '[A-Za-z0-9\-_]+')->name('gallery.author');

Route::middleware(['auth', 'verified'])
    ->name('profile.')
    ->prefix('my-profile')
    ->group(function () {
    Route::get('/', [ProfileController::class, 'index'])->name('index');
    Route::get('/edit', [ProfileController::class, 'edit'])->name('edit');
    Route::put('/update', [ProfileController::class, 'update'])->name('update');
    Route::delete('/', [ProfileController::class, 'destroy'])->name('destroy');
});

Route::put('/editor-draft', [EditorDraftController::class, 'update'])
    ->middleware('auth')
    ->name('editor-draft.update');
Route::post('/editor-draft', [EditorDraftController::class, 'archive'])->middleware('auth');
Route::get('/editor-draft/preview/{pattern}', [EditorDraftController::class, 'preview'])
    ->middleware('auth')->whereNumber('pattern')->name('editor-draft.preview');
Route::get('/editor/drafts/{pattern}', [PagesController::class, 'editDraft'])
    ->middleware('auth')->whereNumber('pattern')->name('editor-draft.edit');
Route::put('/editor/drafts/{pattern}', [EditorDraftController::class, 'updateSaved'])
    ->middleware('auth')->whereNumber('pattern')->name('editor-draft.saved.update');
Route::delete('/editor/drafts/{pattern}', [EditorDraftController::class, 'destroy'])
    ->middleware('auth')->whereNumber('pattern')->name('editor-draft.destroy');
Route::post('/editor/drafts/{pattern}', [EditorDraftController::class, 'startFromSaved'])
    ->middleware('auth')->whereNumber('pattern');
Route::post('/editor/publish/{pattern?}', [EditorDraftController::class, 'publish'])
    ->middleware('auth')->whereNumber('pattern')->name('editor-draft.publish');
Route::post('/editor/revisions/{pattern}', [EditorDraftController::class, 'startRevision'])
    ->middleware('auth')->whereNumber('pattern')->name('editor-draft.revision');
Route::get('/gallery/patterns/{pattern}/preview', [EditorDraftController::class, 'publishedPreview'])
    ->whereNumber('pattern')->name('gallery.pattern.preview');

Route::get('/bead-speech/{language}/{number}/{count?}', BeadSpeechController::class)
    ->whereIn('language', ['uk', 'en', 'ru'])
    ->where(['number' => '[1-9][0-9]{0,9}', 'count' => '[1-9][0-9]{0,9}']);

Route::get('/{slug?}', [PagesController::class, 'view'])
    ->where('slug', '[A-Za-z0-9\-/_]+')
    ->name('page');


