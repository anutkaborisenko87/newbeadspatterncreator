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
        public string $link,
        public array $author,
        public array $comments,
        public array $likes,
        public ?string $pngLink,
        public ?string $jpgLink,
        public ?DateTime $created_at,
        public ?DateTime $updated_at

    ) {
    }

    public static function fromModel(Pattern $pattern): self
    {
        $authUser = Auth::user();

        $encodedId = encodeModelId($pattern->id);

        $galleryUrl = $pattern->user?->id
            ? 'gallery/' . encodeModelId($pattern->user->id)
            : 'gallery';

        if ($authUser && $authUser->id === $pattern->user?->id) {
            $galleryUrl = 'my-gallery';
        }

        $itemLink = $authUser && $authUser->id === $pattern->user?->id
            ? 'editor/' . $encodedId
            : 'gallery/' . $encodedId;
        $draftPreview = $pattern->isEditorDraftFor($authUser?->id)
            ? route('editor-draft.preview', ['pattern' => $pattern], absolute: false)
            : null;

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
            preview: $draftPreview ?? $pattern->preview_image,
            link: $draftPreview ? route('editor-draft.edit', ['pattern' => $pattern], absolute: false) : $itemLink,

            author: [
                'name' => $pattern->user?->login,
                'url' => $galleryUrl,
            ],

            comments: [
                'count' => $pattern->comments->count(),
                'link' => 'editor/' . $encodedId . '/comments',
            ],

            likes: [
                'liked' => $liked,
                'count' => $pattern->likes->count(),
            ],

            pngLink: $pattern->pngLink,
            jpgLink: $pattern->jpgLink,
            created_at: $pattern->created_at,
            updated_at: $pattern->updated_at
        );
    }

    public static function collection(iterable $patterns): array
    {
        $result = [];

        foreach ($patterns as $pattern) {
            $result[] = self::fromModel($pattern)->toArray();
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
        ];
    }
}
