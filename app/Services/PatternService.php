<?php

namespace App\Services;

use App\Contracts\Services\PatternServiceInterface;
use App\DTO\PatternGalleryDTO;
use App\Http\Resources\PatternGalleryResource;
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
            ->where('published', true)->where('shared', true)
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
            ->where('shared', true)
            ->with('user:id,login', 'likes', 'comments');
        return self::getPatternsList($patterns, 'page', PatternsSortBy::class);
    }

    public function getFilteredPatternsForAuthorGalery(string $userId): array
    {
        $decodedUserId = decodeModelId($userId);
        if (!$decodedUserId) throw new NotFoundHttpException(__('main.wrong_user_id'));
        $patterns = $this->model::query()->where('shared', true)->where('user_id', $decodedUserId)
            ->with('user:id,login', 'likes', 'comments');
        return self::getPatternsList($patterns, 'page', PatternsSortBy::class);
    }

    public function getAuthorPatterns(int $authUserId, bool $draft): array
    {
        $patterns = $this->model::query()
            ->where('user_id', $authUserId)
            ->where('published', !$draft)
            ->with('user:id,login');
        if (!$draft) $patterns = $patterns->with('likes', 'comments');
        return self::getPatternsList($patterns, $draft ? 'drafts_page' : 'my_patterns_page', $draft ? DraftPatternsSortBy::class : MyPatternsSortBy::class);
    }

    public function getLikedPatterns(int $authUserId): array
    {
        $patterns = $this->model::query()
            ->where('published', true)
            ->whereHas('likes', function ($query) use ($authUserId) {
            $query->where('user_id', $authUserId);
        })->with('user:id,login', 'likes', 'comments');

        return self::getPatternsList($patterns, 'likes_page', LikedPatternsSortBy::class);
    }

    private static function getPatternsList($queryBuilder, string $pageName, string $sortByClass): array
    {
        $patterns = app(Pipeline::class)
            ->send($queryBuilder)
            ->through([
                PatternCategoryFilter::class,
                ...($pageName === 'page' ? [PatternsSearch::class] : []),
                $sortByClass
            ])->thenReturn();
        $patterns = $patterns->paginate(4, ['*'], $pageName)->withQueryString();
        $data = PatternGalleryDTO::collection($patterns->items());
        $response = $patterns->toArray();
        $response['data'] = $data;
        return $response;
    }
}
