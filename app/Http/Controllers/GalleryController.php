<?php

namespace App\Http\Controllers;

use App\Contracts\Services\PageSectionsServiceInterface;
use App\Contracts\Services\PageServiceInterface;
use App\Services\PageSectionsService;
use Inertia\Inertia;

class GalleryController extends Controller
{
    public function __construct(
        private PageServiceInterface $pageService,
        private PageSectionsServiceInterface $pageSectionsService,

    )
    {}

    public function indexAuthorGallery(string $userId): \Inertia\Response
    {
        $pageData = $this->pageService->getPageData('gallery')->toArray();
        $pageData['data']['sections'] = [
            $this->pageSectionsService->getGalleryAuthorSection($userId),
            ...$pageData['data']['sections']
        ];
        return Inertia::render('Page', $pageData);
    }
}
