<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Guarded(['id', 'created_at', 'updated_at'])]
class Comment extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id');
    }

    public static function getCommentsTree($comments = null, $parentId = null): array
    {
        $tree = [];
        foreach ($comments as $comment) {
            if ($comment->parent_id === $parentId) {
                $childTree = self::getCommentsTree($comments, $comment->id);
                if (!empty($childTree)) {
                    $comment->replies = $childTree;
                }
                $tree[] = $comment;
            }
        }

        return $tree;
    }
}
