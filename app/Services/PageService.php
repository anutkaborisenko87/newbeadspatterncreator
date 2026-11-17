<?php

namespace App\Services;

use App\Contracts\Services\PageServiceInterface;
use App\DTO\PageDataDTO;
use App\Helpers\SEO;
use App\Models\Page;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PageService implements PageServiceInterface
{
    public function __construct(
        private Page $model
    ) {}
    public function getPageData(string $slug): ?PageDataDTO
    {
        $page = $this->model::where('status', true)->with(['translate', 'sections']);

        if ($slug === 'home') {
            $page = $page->where('home', true);
        } else {
            $page = $page->where('slug', $slug);
        }
        $page = $page->first();

        if (!is_null($page)) {
            $page->sections = (new PageSectionsService($page->sections))->getPageSections();
            SEO::generate($page);
        }
        if (is_null($page)) {
            throw new NotFoundHttpException(__('main.page_not_found_title') );
        }
        return PageDataDTO::fromPage($page);
    }

}
