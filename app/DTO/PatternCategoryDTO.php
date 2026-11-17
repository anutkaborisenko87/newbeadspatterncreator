<?php

namespace App\DTO;

use App\Models\PatternCategory;

final readonly class PatternCategoryDTO
{
    public function __construct(
        public string $id,
        public ?string $name,
        public bool $selected,
        public array $subcategories,
    ) {}

    public static function fromModel(
        PatternCategory $category,
        ?string $requestedCategory = null,
    ): self {
        $categoryId = encodeModelId($category->id);

        return new self(
            id: $categoryId,
            name: $category->translate?->title,
            selected: $requestedCategory === $categoryId,
            subcategories: $category->relationLoaded('children_tree')
                ? $category->children_tree
                    ->map(
                        fn (PatternCategory $child) =>
                        self::fromModel($child, $requestedCategory)->toArray()
                    )
                    ->values()
                    ->toArray()
                : [],
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'selected' => $this->selected,
            'subcategories' => $this->subcategories,
        ];
    }

}
