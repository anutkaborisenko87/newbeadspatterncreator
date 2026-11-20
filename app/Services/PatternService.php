<?php

namespace App\Services;

use App\Contracts\Services\PatternServiceInterface;
use App\DTO\PatternGalleryDTO;
use App\Models\Pattern;
use App\QueryFilters\DraftPatternsSortBy;
use App\QueryFilters\LikedPatternsSortBy;
use App\QueryFilters\MyPatternsSortBy;
use App\QueryFilters\PatternCategoryFilter;
use App\QueryFilters\PatternsSearch;
use App\QueryFilters\PatternsSortBy;
use Illuminate\Pipeline\Pipeline;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PatternService implements PatternServiceInterface
{
    public function __construct(
        private Pattern $model
    ) {}

    public function getTopRatedPreviews(): array
    {
        return $this->model::query()
            ->where('published', true)->publiclyShared()
            ->where(fn ($query) => $query->whereNotNull('pattern_data->galleryPublicationId')
                ->orWhere(fn ($query) => $query->whereNotNull('preview_image')->where('preview_image', '!=', '')))
            ->withCount('likes')->orderByDesc('likes_count')->orderByDesc('id')
            ->limit(4)->get()
            ->map(fn (Pattern $pattern) => [
                'id' => $pattern->getKey(),
                'title' => $pattern->title,
                'preview' => $pattern->galleryPreviewUrl(),
            ])->all();
    }

    public function getFilteredPatternsForCommonGallery(): array
    {
        $patterns = $this->model::query()
            ->publiclyShared()
            ->with('user:id,login', 'likes', 'comments');
        return self::getPatternsList($patterns, 'page', PatternsSortBy::class);
    }

    public function getFilteredPatternsForAuthorGalery(string $userId): array
    {
        $decodedUserId = decodeModelId($userId);
        if (!$decodedUserId) throw new NotFoundHttpException(__('main.wrong_user_id'));
        $patterns = $this->model::query()->publiclyShared()->where('user_id', $decodedUserId)
            ->with('user:id,login', 'likes', 'comments');
        return self::getPatternsList($patterns, 'page', PatternsSortBy::class, authorGallery: true);
    }

    public function getAuthorPatterns(int $authUserId, bool $draft): array
    {
        $patterns = $this->model::query()
            ->where('user_id', $authUserId)
            ->with('user:id,login');
        if ($draft) {
            $patterns->where('published', false)->whereNull('copied_from_id');
        } else {
            $patterns->inPersonalGallery()->with('likes', 'comments');
        }
        return self::getPatternsList($patterns, $draft ? 'drafts_page' : 'my_patterns_page', $draft ? DraftPatternsSortBy::class : MyPatternsSortBy::class);
    }

    public function getOwnerGalleryPatterns(int $userId): array
    {
        $patterns = $this->model::query()->where('user_id', $userId)->inPersonalGallery()
            ->with('user:id,login', 'likes', 'comments');

        return self::getPatternsList($patterns, 'page', PatternsSortBy::class, ownGallery: true);
    }

    public function getLikedPatterns(int $authUserId): array
    {
        $patterns = $this->model::query()
            ->where('published', true)->whereNull('copied_from_id')
            ->whereHas('likes', function ($query) use ($authUserId) {
            $query->where('user_id', $authUserId);
        })->with('user:id,login', 'likes', 'comments');

        return self::getPatternsList($patterns, 'likes_page', LikedPatternsSortBy::class);
    }

    private static function getPatternsList(
        $queryBuilder, string $pageName, string $sortByClass, bool $ownGallery = false, bool $authorGallery = false
    ): array
    {
        $patterns = app(Pipeline::class)
            ->send($queryBuilder)
            ->through([
                PatternCategoryFilter::class,
                ...($pageName === 'page' ? [PatternsSearch::class] : []),
                $sortByClass
            ])->thenReturn();
        $patterns = $patterns->paginate($ownGallery || $authorGallery ? 3 : 4, ['*'], $pageName)->withQueryString();
        $data = PatternGalleryDTO::collection($patterns->items(), $ownGallery, $authorGallery);
        $response = $patterns->toArray();
        $response['data'] = $data;
        return $response;
    }
}
