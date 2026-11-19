<?php

namespace App\Http\Controllers;

use App\Contracts\Services\PageServiceInterface;
use App\Models\EditorDraft;
use App\Models\Pattern;
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

        if (in_array('editor_page_section', array_column($data->sections, 'slug'), true) && auth()->check()) {
            if ($savedDraft) {
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
