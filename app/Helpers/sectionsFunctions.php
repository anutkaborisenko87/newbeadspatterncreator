<?php


use App\Contracts\Services\PatternServiceInterface;
use App\DTO\PatternCategoryDTO;
use App\Models\PatternCategory;
use Illuminate\Support\Facades\Auth;

if (!function_exists('getPatternCategoryList')) {
    function getPatternCategoryList(): array
    {
        $categories = PatternCategory::getCategoryTree();
        $requestedCategory = request()->query('pattern_category_filter');

        return collect($categories)
            ->map(
                fn (PatternCategory $category) =>
                PatternCategoryDTO::fromModel(
                    $category,
                    $requestedCategory
                )->toArray()
            )
            ->values()
            ->toArray();
    }
}

if (!function_exists('getMainRatedPicturesList')) {
    function getMainRatedPicturesList(): array
    {
        return app(PatternServiceInterface::class)->getTopRatedPreviews();
    }
}

if (!function_exists('getMainGalleryItemsList')) {
    function getMainGalleryItemsList(): array
    {
        $userId = request()->route('userId');
        $patternService = app(PatternServiceInterface::class);

        if ($userId) return $patternService->getFilteredPatternsForAuthorGalery($userId);
        if (request()->routeIs('gallery.mine')) {
            return $patternService->getOwnerGalleryPatterns(Auth::id());
        }
        if (request()->routeIs('profile.liked')) {
            return $patternService->getLikedPatterns(Auth::id(), standalone: true);
        }
        if (request()->routeIs('profile.index')) {
            $userId = Auth::user()->id;
            return [
                'my_patterns' => $patternService->getAuthorPatterns($userId, false),
                'drafts' => $patternService->getAuthorPatterns($userId, true),
                'liked' => $patternService->getLikedPatterns($userId),
            ];
        }
        return $patternService->getFilteredPatternsForCommonGallery();
    }
}

if (!function_exists('getSortByList')) {
    function getSortByList(): array
    {
        $orderByList = \App\Models\Layout::where('slug', 'LIKE', 'order_by_%')->with('translate')->get();
        $orderByList = $orderByList->map(function ($item) {
            return [
                'id' => str_replace('order_by_', '', $item->slug),
                'name' => $item->translate->title
            ];
        });
        $requestedSortBy = request()->query('patterns_sort_by');
        $orderByList = $orderByList->map(function ($item) use ($requestedSortBy) {

            return [
                ...$item,
                'selected' => $item['id'] === $requestedSortBy,
            ];
        });
        return $orderByList->toArray();
    }
}
