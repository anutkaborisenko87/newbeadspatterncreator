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
use Throwable;

class GalleryController extends Controller
{
    public function __construct(
        private PageServiceInterface $pageService,
        private PageSectionsServiceInterface $pageSectionsService,

    ) {}

    /**
     * Displays the author's gallery page.
     *
     * @param string $userId The ID of the author whose gallery is to be displayed.
     * @return \Inertia\Response The Inertia response containing the gallery page data.
     */
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

    /**
     * Displays the current user's gallery page.
     *
     * @param Request $request The current HTTP request.
     * @return Response The Inertia response containing the gallery page data.
     */
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

    /**
     * Displays the current user's liked patterns page.
     *
     * @param Request $request The current HTTP request.
     * @return Response The Inertia response containing the liked patterns page data.
     */
    public function liked(Request $request): Response
    {
        $pageData = $this->pageService->getPageData('gallery')->toArray();
        $gallery = collect($pageData['data']['sections'])->firstWhere('slug', 'gallery_page_section')['gallery_items'] ?? null;
        if ($gallery && $gallery['current_page'] > $gallery['last_page']) {
            return redirect()->to($gallery['last_page_url']);
        }

        $pageData['data']['title'] = __('main.profile_liked_title');
        $pageData['likedGallery'] = [
            'title' => $pageData['data']['title'],
            'copy' => __('main.author_gallery.copy'),
        ];

        return Inertia::render('Page', $pageData)->toResponse($request)
            ->header('Cache-Control', 'private, no-store');
    }

    /**
     * Displays the current user's drafts page.
     *
     * @param Request $request The current HTTP request.
     * @param Pattern $pattern
     * @return RedirectResponse The Inertia response containing the drafts page data.
     * @throws Throwable
     */
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

    /**
     * Displays the current user's drafts page.
     *
     * @param Request $request The current HTTP request.
     * @param Pattern $pattern
     * @return RedirectResponse The Inertia response containing the drafts page data.
     * @throws Throwable
     */
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

    /**
     * Unpublishes a pattern from the current user's gallery.
     *
     * @param Request $request The current HTTP request.
     * @param Pattern $pattern The pattern to be unpublished.
     * @return RedirectResponse A redirect response back to the gallery page.
     */
    public function unpublish(Request $request, Pattern $pattern): RedirectResponse
    {
        abort_unless((int) $pattern->user_id === (int) $request->user()->id && !$pattern->isPrivateCopy(), 403);
        Pattern::whereKey($pattern->id)->publiclyShared()->update(['published' => true, 'shared' => false]);
        return back(303, [], route('gallery.mine'));
    }

    /**
     * Publishes a pattern to the current user's gallery.
     *
     * @param Request $request The current HTTP request.
     * @param Pattern $pattern The pattern to be published.
     * @return RedirectResponse A redirect response back to the gallery page.
     */
    public function publish(Request $request, Pattern $pattern): RedirectResponse
    {
        abort_unless((int) $pattern->user_id === (int) $request->user()->id && !$pattern->isPrivateCopy(), 403);
        Pattern::whereKey($pattern->id)->publiclyUnShared()->update(['published' => true, 'shared' => true]);
        return back(303, [], route('gallery.mine'));
    }
}
