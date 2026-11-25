<?php

namespace App\Services;

use App\Contracts\Services\PatternServiceInterface;
use App\DTO\PatternGalleryDTO;
use App\Models\Pattern;
use App\Models\User;
use App\QueryFilters\DraftPatternsSortBy;
use App\QueryFilters\LikedPatternsSortBy;
use App\QueryFilters\MyPatternsSortBy;
use App\QueryFilters\PatternCategoryFilter;
use App\QueryFilters\PatternsSearch;
use App\QueryFilters\PatternsSortBy;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PatternService implements PatternServiceInterface
{
    public function __construct(
        private Pattern $model
    ) {}

    /**
     * @inheritDoc
     */
    public function getOrCreateRevisionDraft(Pattern $pattern, int $owner): Pattern
    {
        abort_unless($pattern->canRevise($owner), 404);
        return DB::transaction(function () use ($owner, $pattern) {
            User::query()->lockForUpdate()->findOrFail($owner);
            $original = Pattern::query()->lockForUpdate()->findOrFail($pattern->getKey());
            abort_unless($original->canRevise($owner), 404);

            return Pattern::where('user_id', $owner)->where('published', false)->where('shared', false)
                ->where('pattern_data->revisionOf', (string) $original->getKey())->first()
                ?? Pattern::create([
                    'user_id' => $owner,
                    'title' => $original->title,
                    'description' => $original->description,
                    'photo' => $original->photo,
                    'pattern_data' => [
                        ...array_intersect_key($original->pattern_data, array_flip(['gridWidth', 'gridHeight', 'drawnCells', 'view'])),
                        'revisionOf' => (string) $original->getKey(),
                        'editorDraftVersion' => (string) max(1, (int) $original->updated_at?->getTimestampMs() * 1000),
                    ],
                ]);
        });
    }

    /**
     * @inheritDoc
     */
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

    /**
     * @inheritDoc
     */
    public function getFilteredPatternsForCommonGallery(): array
    {
        $patterns = $this->model::query()
            ->publiclyShared()
            ->with('user:id,login', 'likes', 'comments');
        return self::getPatternsList($patterns, 'page', PatternsSortBy::class);
    }

    /**
     * @inheritDoc
     */
    public function getFilteredPatternsForAuthorGalery(string $userId): array
    {
        $decodedUserId = decodeModelId($userId);
        if (!$decodedUserId) throw new NotFoundHttpException(__('main.wrong_user_id'));
        $patterns = $this->model::query()->publiclyShared()->where('user_id', $decodedUserId)
            ->with('user:id,login', 'likes', 'comments');
        return self::getPatternsList($patterns, 'page', PatternsSortBy::class, includeCopyAction: true);
    }

    /**
     * @inheritDoc
     */
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

    /**
     * @inheritDoc
     */
    public function getOwnerGalleryPatterns(int $userId): array
    {
        $patterns = $this->model::query()->where('user_id', $userId)->inPersonalGallery()
            ->with('user:id,login', 'likes', 'comments');

        return self::getPatternsList($patterns, 'page', PatternsSortBy::class, ownGallery: true);
    }

    /**
     * @inheritDoc
     */
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

    /**
     * @inheritDoc
     */
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
