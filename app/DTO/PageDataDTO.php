<?php

namespace App\DTO;

use App\Models\Page;

final readonly class PageDataDTO
{
    public function __construct(
        public int $id,
        public string $title,
        public ?string $intro,
        public mixed $date,
        public array $sections,
        public array $meta,
        public array $links,
    ) {}

    public function toArray(): array
    {
        return [
            'data' => [
                'id' => $this->id,
                'title' => $this->title,
                'intro' => $this->intro,
                'date' => $this->date,
                'sections' => $this->sections,
            ],
            'meta' => $this->meta,
            'links' => $this->links,
        ];
    }

    public static function fromPage(Page $page): self
    {
        return new self(
            id: $page->id,

            title: config('app.name') . ' - ' . $page->translate->title,

            intro: $page->translate->intro,

            date: $page->publish_up,

            sections: $page->sections->toArray(),

            meta: [
                'seo' => [
                    'title' => $page->translate->seo_title ?? false,
                    'description' => $page->translate->meta_description ?? false,
                    'robots' => $page->translate->meta_robots ?? false,
                ],
            ],

            links: [
                'self' => '/' . $page->slug,
                'slug' => $page->slug,
            ],
        );
    }
}
