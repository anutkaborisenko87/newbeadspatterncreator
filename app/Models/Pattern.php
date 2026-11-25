<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Guarded(['id', 'created_at', 'updated_at', 'copied_from_id'])]
class Pattern extends Model
{
    public function blockedCommenters(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'pattern_comment_blocks');
    }

    protected $casts = [
        'pattern_data' => 'array'
    ];

    public function galleryPreviewUrl(): ?string
    {
        return $this->published && $this->isPubliclyShared() && isset($this->pattern_data['galleryPublicationId'])
            ? route('gallery.pattern.preview', ['pattern' => $this], absolute: false)
            : $this->preview_image;
    }

    public function isEditorDraftFor(?int $userId): bool
    {
        return $userId !== null && (int) $this->user_id === $userId
            && !$this->published && !$this->shared && !$this->isPrivateCopy()
            && isset($this->pattern_data['editorDraftVersion']);
    }

    public function canEditSavedDrawing(?int $owner): bool
    {
        return $this->isEditorDraftFor($owner)
            || ($this->isPrivateCopy() && $this->isInPersonalGalleryOf($owner) && $this->hasEditorDrawing());
    }

    public function canRevise(?int $owner): bool
    {
        return $this->isInPersonalGalleryOf($owner) && $this->published
            && $this->isPubliclyShared() && $this->hasEditorDrawing();
    }

    public function isInPersonalGalleryOf(?int $owner): bool
    {
        return $owner !== null && (int) $this->user_id === $owner && ($this->published || $this->isPrivateCopy());
    }

    public function isPrivateCopy(): bool
    {
        return $this->copied_from_id !== null;
    }

    public function isPubliclyShared(): bool
    {
        return $this->shared && !$this->isPrivateCopy();
    }

    public function scopePubliclyShared(Builder $query): Builder
    {
        return $query->where('shared', true)->whereNull('copied_from_id');
    }

    public function scopeInPersonalGallery(Builder $query): Builder
    {
        return $query->where(fn (Builder $patterns) => $patterns
            ->where('published', true)->orWhereNotNull('copied_from_id'));
    }

    public function hasEditorDrawing(): bool
    {
        return isset($this->pattern_data['gridWidth'], $this->pattern_data['gridHeight'], $this->pattern_data['drawnCells']);
    }

    public function editorSnapshot(): array
    {
        $data = $this->pattern_data;

        return [
            'version' => (int) ($data['editorEditVersion'] ?? $data['editorDraftVersion'] ?? $data['editorPreviewVersion'] ?? 1),
            'drawing' => array_intersect_key($data, array_flip(['gridWidth', 'gridHeight', 'drawnCells', 'view'])),
            'previewVersion' => $data['editorPreviewVersion'] ?? null,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsToMany
    {
        return $this->belongsToMany(PatternCategory::class, 'pattern_categories_patterns', 'pattern_id', 'pattern_category_id');
    }
    public function likes(): HasMany
    {
        return $this->hasMany(Like::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
}
