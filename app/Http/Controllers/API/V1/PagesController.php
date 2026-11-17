<?php

namespace App\Http\Controllers\API\V1;


use App\Contracts\Services\PageServiceInterface;

class PagesController extends BaseApiController
{
    public function __construct(private PageServiceInterface $pageService)
    { }

    public function getPageData(?string $slug = null)
    {
        if (!$slug) {
            $slug = 'home';
        }
        if (is_string($slug) &&
            preg_match("/^[a-z0-9\-\/_]+$/i", $slug)
        ) {
            return $this->handleServiceCall(function () use ($slug) { return $this->pageService->getPageData($slug)->toArray(); }, 'Page data retrieved successfully');
        }
        return $this->errorResponse(['error' => __('main.page_not_found_title') ], 404);
    }
}
