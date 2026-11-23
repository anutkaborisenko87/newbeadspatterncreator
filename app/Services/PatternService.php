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
        return self::getPatternsList($patterns, 'page', PatternsSortBy::class, includeCopyAction: true);
    }

    public function getAuthorPatterns(int $authUserId, bool $draft, int $perPage = 4): array
    {
        $patterns = $this->model::query()
            ->where('user_id', $authUserId)
            ->with('user:id,login');
        if ($draft) {
            $patterns->where('published', false)->whereNull('copied_from_id');
        } else {
            $patterns->inPersonalGallery()->with('likes', 'comments');
        }
        return self::getPatternsList($patterns, $draft ? 'drafts_page' : 'my_patterns_page', $draft ? DraftPatternsSortBy::class : MyPatternsSortBy::class, perPage: $perPage);
    }

    public function getOwnerGalleryPatterns(int $userId): array
    {
        $patterns = $this->model::query()->where('user_id', $userId)->inPersonalGallery()
            ->with('user:id,login', 'likes', 'comments');

        return self::getPatternsList($patterns, 'page', PatternsSortBy::class, ownGallery: true);
    }

    public function getLikedPatterns(int $authUserId, bool $standalone = false): array
    {
        $patterns = $this->model::query()->publiclyShared()
            ->whereHas('likes', fn ($query) => $query->where('user_id', $authUserId))
            ->with('user:id,login', 'likes', 'comments');

        if ($standalone) {
            return self::getPatternsList($patterns, 'page', PatternsSortBy::class, includeCopyAction: true);
        }

        return self::getPatternsList($patterns, 'likes_page', LikedPatternsSortBy::class, perPage: 4);
    }

    private static function getPatternsList(
        $queryBuilder, string $pageName, string $sortByClass, bool $ownGallery = false, bool $includeCopyAction = false,
        int $perPage = 3
    ): array
    {
        $patterns = app(Pipeline::class)
            ->send($queryBuilder)
            ->through([
                PatternCategoryFilter::class,
                ...($pageName === 'page' ? [PatternsSearch::class] : []),
                $sortByClass
            ])->thenReturn();
        if ($pageName === 'drafts_page' || $includeCopyAction || $pageName === 'likes_page') {
            $patterns->orderByDesc('id');
        }
        $patterns = $patterns->paginate($perPage, ['*'], $pageName)->withQueryString();
        $data = PatternGalleryDTO::collection($patterns->items(), $ownGallery, $includeCopyAction);
        $response = $patterns->toArray();
        $response['data'] = $data;
        return $response;
    }
}
