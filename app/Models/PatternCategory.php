<?php

namespace App\Models;

use App\Models\Traits\Translate;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Guarded(['id', 'created_at', 'updated_at'])]

class PatternCategory extends Model
{
    use Translate;
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public static function getCategoryTree($categories = null, $parentId = null): array
    {
        if (is_null($categories)) {
            $categories = self::with('translate')->get();
        }
        $tree = [];
        foreach ($categories as $category) {
            if ($category->parent_id === $parentId) {
                $childTree = self::getCategoryTree($categories, $category->id);
                if (!empty($childTree)) {
                    $category->children_tree = $childTree;
                }
                $tree[] = $category;
            }
        }

        return $tree;
    }

    public function patterns(): BelongsToMany
    {
        return $this->belongsToMany(Pattern::class, 'pattern_categories_patterns', 'pattern_category_id', 'pattern_id');
    }
}
