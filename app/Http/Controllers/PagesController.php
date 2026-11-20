<?php

namespace App\Http\Controllers;

use App\Contracts\Services\PageServiceInterface;
use App\Models\EditorDraft;
use App\Models\Pattern;
use App\Models\PatternCategory;
use Inertia\Inertia;

class PagesController extends Controller
{
    public function __construct(private PageServiceInterface $pageService)
    {}

    public function view($slug = false)
    {
        if ($slug === false) {
            $slug = 'home';
        }
        if (is_string($slug) &&
            preg_match("/^[a-z0-9\-\/_]+$/i", $slug)
        ) {
            return $this->renderPage($slug);
        }
        abort(404);
    }

    public function editDraft(Pattern $pattern)
    {
        abort_unless($pattern->isEditorDraftFor(auth()->id()), 404);

        return $this->renderPage('editor', $pattern);
    }

    private function renderPage(string $slug, ?Pattern $savedDraft = null)
    {
        $data = $this->pageService->getPageData($slug);
        abort_if($data === null, 404);
        $props = $data->toArray();

        $editor = in_array('editor_page_section', array_column($data->sections, 'slug'), true);
        if ($editor) {
            $props['galleryPublication'] = ['labels' => __('main.gallery_publication')];
        }
        if ($editor && auth()->check()) {
            $props['galleryPublication'] += [
                'url' => route('editor-draft.publish', $savedDraft ? ['pattern' => $savedDraft] : [], absolute: false),
                'categories' => PatternCategory::with('translate')->get()
                    ->filter(fn ($category) => $category->translate)
                    ->map(fn ($category) => ['id' => $category->id, 'title' => $category->translate->title])
                    ->sortBy('title')->values(),
            ];
            if ($savedDraft) {
                $original = isset($savedDraft->pattern_data['revisionOf'])
                    ? Pattern::find($savedDraft->pattern_data['revisionOf']) : null;
                if ($original?->canRevise(auth()->id())) {
                    $props['galleryPublication']['revision'] = [
                        'title' => $original->title,
                        'description' => $original->description,
                        'category' => $original->category()->first()?->getKey(),
                        'discardUrl' => route('editor-draft.destroy', ['pattern' => $savedDraft, 'returnToEditor' => 1], absolute: false),
                    ];
                }
                $props['editorDraft'] = $savedDraft->editorSnapshot();
                $props['editorDraftUrl'] = route('editor-draft.saved.update', ['pattern' => $savedDraft], absolute: false);
                $props['editorSavedDraftId'] = $savedDraft->getKey();
                $props['editorWorkingDraftUrl'] = route('editor-draft.update', absolute: false);
            } else {
                $props['editorDraft'] = EditorDraft::find(auth()->id())?->snapshot();
                $props['editorDraftUrl'] = route('editor-draft.update', absolute: false);
            }

            return Inertia::render('Page', $props)->toResponse(request())
                ->header('Cache-Control', 'private, no-store');
        }

        return Inertia::render('Page', $props);
    }
}
