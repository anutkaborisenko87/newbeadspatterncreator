<?php

namespace App\Http\Controllers;

use App\Contracts\Services\PageServiceInterface;
use Inertia\Inertia;

class PagesController extends Controller
{
    public function __construct(private PageServiceInterface $pageService)
    {}

    public function view($slug = false)
    {
        if ($slug === false) {
            $slug = 'home';
        }
        if (is_string($slug) &&
            preg_match("/^[a-z0-9\-\/_]+$/i", $slug)
        ) {
            $data = $this->pageService->getPageData($slug);
            if (!empty($data)) {
                return Inertia::render('Page', $data->toArray());
            }

        }
        abort(404);

    }
}
