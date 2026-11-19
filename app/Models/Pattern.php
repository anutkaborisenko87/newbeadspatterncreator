<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Guarded(['id', 'created_at', 'updated_at'])]
class Pattern extends Model
{
    protected $casts = [
        'pattern_data' => 'array'
    ];

    public function isEditorDraftFor(?int $userId): bool
    {
        return $userId !== null && (int) $this->user_id === $userId
            && !$this->published && !$this->shared && isset($this->pattern_data['editorDraftVersion']);
    }

    public function editorSnapshot(): array
    {
        $data = $this->pattern_data;

        return [
            'version' => (int) ($data['editorEditVersion'] ?? $data['editorDraftVersion']),
            'drawing' => array_intersect_key($data, array_flip(['gridWidth', 'gridHeight', 'drawnCells'])),
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
