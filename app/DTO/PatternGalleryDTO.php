<?php

namespace App\DTO;

use App\Models\Pattern;
use DateTime;
use Illuminate\Support\Facades\Auth;

final readonly class PatternGalleryDTO
{
    public function __construct(
        public string $id,
        public ?string $title,
        public ?string $description,
        public ?string $photo,
        public ?string $preview,
        public ?string $link,
        public array $author,
        public array $comments,
        public array $likes,
        public ?string $pngLink,
        public ?string $jpgLink,
        public ?DateTime $created_at,
        public ?DateTime $updated_at,
        public ?string $deleteUrl = null,
        public ?string $editPublishedUrl = null,
        public ?string $editPrivateCopyUrl = null,
        public ?string $copyUrl = null,
        public ?string $privateCopyLabel = null,
        public ?array $download = null,

    ) {
    }

    public static function fromModel(Pattern $pattern, bool $ownGallery = false, bool $includeCopyAction = false): self
    {
        $authUser = Auth::user();
        $owner = $authUser?->id;
        $privateCopy = $pattern->isPrivateCopy();

        $encodedId = encodeModelId($pattern->id);

        $galleryUrl = $pattern->user?->id
            ? route('gallery.author', ['userId' => encodeModelId($pattern->user->id)], absolute: false)
            : route('page', ['slug' => 'gallery'], absolute: false);

        if ($authUser && $authUser->id === $pattern->user?->id) {
            $galleryUrl = route('profile.index', absolute: false);
        }

        $itemLink = $authUser && $authUser->id === $pattern->user?->id
            ? 'editor/' . $encodedId
            : 'gallery/' . $encodedId;
        $draftPreview = $pattern->isEditorDraftFor($owner)
            ? route('editor-draft.preview', ['pattern' => $pattern], absolute: false)
            : null;
        $inPersonalGallery = $pattern->isInPersonalGalleryOf($owner);
        $canViewPrivatePreview = $inPersonalGallery && $pattern->hasEditorDrawing();
        $preview = $draftPreview ?? $pattern->galleryPreviewUrl()
            ?? ($canViewPrivatePreview ? route('gallery.mine.preview', ['pattern' => $pattern], absolute: false) : null);
        $deleteUrl = $ownGallery && $inPersonalGallery
            ? route('gallery.mine.destroy', ['pattern' => $pattern], absolute: false)
            : ($draftPreview ? route('editor-draft.destroy', ['pattern' => $pattern], absolute: false) : null);
        $exportRoute = match (true) {
            $ownGallery && $canViewPrivatePreview => 'gallery.mine.preview',
            $pattern->isPubliclyShared() && $pattern->hasEditorDrawing() => 'gallery.pattern.preview',
            default => null,
        };
        $downloadRoute = match ($exportRoute) {
            'gallery.mine.preview' => 'gallery.mine.download',
            'gallery.pattern.preview' => 'gallery.pattern.download',
            default => null,
        };
        $view = $pattern->pattern_data['view'] ?? [];
        $commentsCount = $pattern->comments->where('approved', true)->count();
        $originalAuthor = $pattern->pattern_data['originalAuthor'] ?? null;

        $liked = $authUser
            ? $pattern->likes->contains(
                fn ($like) => $like->user_id === $authUser->id
            )
            : false;

        return new self(
            id: $encodedId,
            title: $pattern->title,
            description: $pattern->description,
            photo: $pattern->photo,
            preview: $preview,
            link: match (true) {
                $privateCopy => null,
                $draftPreview !== null => route('editor-draft.edit', ['pattern' => $pattern], absolute: false),
                default => $itemLink,
            },

            author: [
                'name' => $pattern->user?->login,
                'url' => $galleryUrl,
            ],

            comments: [
                'count' => $commentsCount,
                'link' => $pattern->isPubliclyShared() ? route('patterns.comments.show', $encodedId, absolute: false) : null,
                'label' => trans_choice('main.author_gallery.comment_count', $commentsCount),
            ],

            likes: [
                'liked' => $liked,
                'count' => $pattern->likes->unique('user_id')->count(),
                'updateUrl' => $owner !== null && (int) $pattern->user_id !== $owner && $pattern->isPubliclyShared()
                    ? route('gallery.pattern.likes.update', $pattern, absolute: false) : null,
                'listUrl' => $owner !== null && (int) $pattern->user_id === $owner && !$privateCopy && ($pattern->published || $pattern->shared)
                    ? route('gallery.pattern.likes.index', $pattern, absolute: false) : null,
            ],

            pngLink: $pattern->pngLink ?? ($exportRoute
                ? route($exportRoute, ['pattern' => $pattern, 'format' => 'png'], absolute: false) : null),
            jpgLink: $pattern->jpgLink ?? ($exportRoute
                ? route($exportRoute, ['pattern' => $pattern, 'format' => 'jpg'], absolute: false) : null),
            created_at: $pattern->created_at,
            updated_at: $pattern->updated_at,
            deleteUrl: $deleteUrl,
            editPublishedUrl: $pattern->canRevise($owner)
                ? route('editor-draft.revision', ['pattern' => $pattern], absolute: false) : null,
            editPrivateCopyUrl: $privateCopy && $pattern->canEditSavedDrawing($owner)
                ? route('editor-draft.edit', ['pattern' => $pattern], absolute: false) : null,
            copyUrl: $includeCopyAction && $pattern->isPubliclyShared() && (int) $pattern->user_id !== $owner
                ? route('gallery.pattern.copy', ['pattern' => $pattern], absolute: false) : null,
            privateCopyLabel: $privateCopy
                ? ($originalAuthor ? __('main.own_gallery.private_copy_by', ['author' => $originalAuthor])
                    : __('main.own_gallery.private_copy'))
                : null,
            download: $downloadRoute ? [
                'png' => route($downloadRoute, ['pattern' => $pattern, 'format' => 'png'], absolute: false),
                'jpg' => route($downloadRoute, ['pattern' => $pattern, 'format' => 'jpg'], absolute: false),
                'rows' => ($view['bottomUp'] ?? true) ? 'bottom_up' : 'top_down',
                'columns' => ($view['leftToRight'] ?? true) ? 'left_to_right' : 'right_to_left',
            ] : null,
        );
    }

    public static function collection(iterable $patterns, bool $ownGallery = false, bool $includeCopyAction = false): array
    {
        $result = [];

        foreach ($patterns as $pattern) {
            $result[] = self::fromModel($pattern, $ownGallery, $includeCopyAction)->toArray();
        }

        return $result;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'photo' => $this->photo,
            'preview' => $this->preview,
            'link' => $this->link,
            'author' => $this->author,
            'comments' => $this->comments,
            'likes' => $this->likes,
            'pngLink' => $this->pngLink,
            'jpgLink' => $this->jpgLink,
            'created_at' => $this->created_at . '/' . $this->updated_at,
            'deleteUrl' => $this->deleteUrl,
            'editPublishedUrl' => $this->editPublishedUrl,
            'editPrivateCopyUrl' => $this->editPrivateCopyUrl,
            'copyUrl' => $this->copyUrl,
            'privateCopyLabel' => $this->privateCopyLabel,
            'download' => $this->download,
            'createdAt' => $this->created_at?->format(DATE_ATOM),
            'updatedAt' => $this->updated_at?->format(DATE_ATOM),
        ];
    }
}
