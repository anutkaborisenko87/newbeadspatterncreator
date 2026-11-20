<?php

namespace App\Http\Controllers;

use App\Contracts\Services\PageSectionsServiceInterface;
use App\Contracts\Services\PageServiceInterface;
use App\Models\Pattern;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class GalleryController extends Controller
{
    public function __construct(
        private PageServiceInterface $pageService,
        private PageSectionsServiceInterface $pageSectionsService,

    )
    {}

    public function indexAuthorGallery(string $userId): \Inertia\Response
    {
        $pageData = $this->pageService->getPageData('gallery')->toArray();
        $pageData['data']['sections'] = [
            $this->pageSectionsService->getGalleryAuthorSection($userId),
            ...$pageData['data']['sections']
        ];
        $pageData['authorGallery'] = [
            ...Arr::only(__('main.own_gallery'), ['photo', 'preview', 'comments', 'likes', 'empty']),
            'copy' => __('main.author_gallery.copy'),
            'search' => __('main.author_gallery.search'),
        ];
        return Inertia::render('Page', $pageData);
    }

    public function mine(Request $request): Response
    {
        $pageData = $this->pageService->getPageData('gallery')->toArray();
        $pageData['data']['title'] = __('main.profile_my_patterns_title');
        $pageData['ownGallery'] = [
            ...__('main.own_gallery'),
            'title' => $pageData['data']['title'],
            'edit' => __('main.gallery_publication.edit'),
            'edit_private_copy' => __('main.private_copy_editor.edit'),
        ];

        return Inertia::render('Page', $pageData)->toResponse($request)
            ->header('Cache-Control', 'private, no-store');
    }

    public function copy(Request $request, Pattern $pattern): RedirectResponse
    {
        $owner = $request->user()->getKey();
        abort_unless($pattern->isPubliclyShared() && (int) $pattern->user_id !== $owner, 404);

        $saved = DB::transaction(function () use ($owner, $pattern) {
            User::query()->lockForUpdate()->findOrFail($owner);
            $source = Pattern::query()->publiclyShared()->lockForUpdate()->findOrFail($pattern->getKey());
            abort_if((int) $source->user_id === $owner, 404);

            if (Pattern::where('user_id', $owner)->where('copied_from_id', $source->getKey())->exists()) {
                return false;
            }

            $copy = new Pattern([
                ...$source->only(['title', 'description', 'photo', 'preview_image', 'pngLink', 'jpgLink']),
                'user_id' => $owner,
                'published' => false,
                'shared' => false,
                'pattern_data' => [
                    ...Arr::only($source->pattern_data ?? [], [
                        'gridWidth', 'gridHeight', 'drawnCells', 'view',
                        'editorPreview', 'editorPreviewFormat', 'editorPreviewVersion', 'editorEditVersion',
                    ]),
                    'originalAuthor' => $source->user?->login,
                ],
            ]);
            $copy->copied_from_id = $source->getKey();
            $copy->save();
            $copy->category()->sync($source->category()->allRelatedIds());

            return true;
        });

        $authorGallery = route('gallery.author', ['userId' => encodeModelId($pattern->user_id)]);
        return back(303, [], $authorGallery)->with('success', __($saved
            ? 'main.author_gallery.saved' : 'main.author_gallery.already_saved'));
    }

    public function destroy(Request $request, Pattern $pattern): RedirectResponse
    {
        $owner = $request->user()->getKey();
        abort_unless($pattern->isInPersonalGalleryOf($owner), 404);

        DB::transaction(function () use ($owner, $pattern) {
            User::query()->lockForUpdate()->findOrFail($owner);
            abort_unless(Pattern::whereKey($pattern->getKey())
                ->where('user_id', $owner)->inPersonalGallery()->delete(), 404);
        });

        return back(303, [], route('gallery.mine'));
    }
}
