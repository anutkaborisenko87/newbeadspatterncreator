<?php

use App\Http\Controllers\EditorDraftController;
use App\Http\Controllers\BeadSpeechController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\PagesController;
use App\Http\Controllers\PatternDownloadController;
use App\Http\Controllers\PatternCommentController;
use App\Http\Controllers\CommentReportController;
use App\Http\Controllers\PatternLikeController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

require __DIR__.'/auth.php';

Route::get('/report-evidence/{report}/{index}', [CommentReportController::class, 'evidence'])
    ->middleware('auth')->whereNumber('index')->name('reports.evidence');

Route::get('auth/google/redirect', [\App\Http\Controllers\Auth\GoogleAuthController::class, 'redirect'])->name('google-auth');
Route::get('auth/google/callback', [\App\Http\Controllers\Auth\GoogleAuthController::class, 'callbackGoogle'])->name('google.callback');
Route::get('/gallery/{userId}', [GalleryController::class, 'indexAuthorGallery'])
    ->where('userId', '[A-Za-z0-9\-_]+')->name('gallery.author');
Route::prefix('gallery/patterns/{discussionPattern}/comments')->where(['discussionPattern' => '[0-9a-z]+[0-9a-f]{12}'])->name('patterns.comments.')->group(function () {
    Route::get('/', [PatternCommentController::class, 'show'])->name('show');
    Route::middleware(['auth', 'throttle:30,1,discussion-write:'])->group(function () {
        Route::post('/', [PatternCommentController::class, 'store'])->name('store');
        Route::put('/blocks/{user}', [PatternCommentController::class, 'setBlock'])->whereNumber('user')->name('block');
        Route::post('/{commentId}/reports', [CommentReportController::class, 'store'])->whereNumber('commentId')->name('report');
        Route::patch('/{commentId}', [PatternCommentController::class, 'update'])->whereNumber('commentId')->name('update');
        Route::delete('/{commentId}', [PatternCommentController::class, 'destroy'])->whereNumber('commentId')->name('destroy');
    });
    Route::get('/diagram', [PatternCommentController::class, 'diagram'])->name('diagram');
});
Route::get('/gallery/patterns/{pattern}/comments', function (\App\Models\Pattern $pattern) {
    abort_unless($pattern->isPubliclyShared(), 404);
    return redirect()->route('patterns.comments.show', encodeModelId($pattern->id));
})->whereNumber('pattern')->name('patterns.comments.legacy');
Route::post('/gallery/patterns/{pattern}/copy', [GalleryController::class, 'copy'])
    ->middleware('auth')->whereNumber('pattern')->name('gallery.pattern.copy');
Route::middleware('auth')->prefix('gallery/patterns/{pattern}/likes')->whereNumber('pattern')->group(function () {
    Route::put('/', [PatternLikeController::class, 'update'])->name('gallery.pattern.likes.update');
    Route::get('/', [PatternLikeController::class, 'index'])->name('gallery.pattern.likes.index');
});
Route::get('/gallery/patterns/{pattern}/download/{format}', [PatternDownloadController::class, 'published'])
    ->whereNumber('pattern')->whereIn('format', ['png', 'jpg'])->name('gallery.pattern.download');

Route::middleware('auth')->prefix('my-gallery')->name('gallery.mine')->group(function () {
    Route::get('/', [GalleryController::class, 'mine']);
    Route::delete('/{pattern}', [GalleryController::class, 'destroy'])->whereNumber('pattern')->name('.destroy');
    Route::get('/{pattern}/preview/{format?}', [EditorDraftController::class, 'ownedPreview'])
        ->whereNumber('pattern')->whereIn('format', ['png', 'jpg'])->name('.preview');
    Route::get('/{pattern}/download/{format}', [PatternDownloadController::class, 'owned'])
        ->whereNumber('pattern')->whereIn('format', ['png', 'jpg'])->name('.download');
});

Route::middleware(['auth', 'verified'])
    ->name('profile.')
    ->prefix('my-profile')
    ->group(function () {
    Route::get('/', [ProfileController::class, 'index'])->name('index');
    Route::get('/drafts', [ProfileController::class, 'drafts'])->name('drafts');
    Route::get('/liked', [GalleryController::class, 'liked'])->name('liked');
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
Route::get('/gallery/patterns/{pattern}/preview/{format?}', [EditorDraftController::class, 'publishedPreview'])
    ->whereNumber('pattern')->whereIn('format', ['png', 'jpg'])->name('gallery.pattern.preview');

Route::get('/bead-speech/{language}/{number}/{count?}', BeadSpeechController::class)
    ->whereIn('language', ['uk', 'en', 'ru'])
    ->where(['number' => '[1-9][0-9]{0,9}', 'count' => '[1-9][0-9]{0,9}']);

Route::get('/{slug?}', [PagesController::class, 'view'])
    ->where('slug', '[A-Za-z0-9\-/_]+')
    ->name('page');


