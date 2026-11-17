<?php

namespace App\Http\Middleware;

use App\Models\Language;
use App\Models\Layout;
use App\Models\Layouts;
use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $locale = LocaleMiddleware::getLocale();
        $currentLang = Schema::hasTable('languages') ? array_filter(Language::getDropdownList(), function ($item) {
            return $item['current'] === true;
        }) : [];
        $navMenu = Schema::hasTable('languages') ? Page::getNavbarMenu() : [];
        $user = !is_null(auth()->user()) ? auth()->user() : null;
       $layouts =  Schema::hasTable('languages') ? Layout::with('translate')->get() : [];
        if (!empty($layouts)) {
            $layouts = $layouts->mapWithKeys(function ($item) {
                $data['title'] = $item->translate->title;
                if (!is_null($item->link)) {
                    $data['link'] = $item->link;
                }
                return [$item->slug => $data];

            });
        }

        $result = array_merge(parent::share($request), [
            'flash' => [
                'success' => $request->session()->get('success'),
                'error'   => $request->session()->get('error'),
            ],
            'langs' => Language::getDropdownList(),
            'user' => $user,
            'url' => is_null($locale) ? $request->getPathInfo() : str_replace("/$locale", "", $request->getPathInfo()),
            'locale' => $locale ?? '',
            'bead_preview_label' => fn () => __('main.bead_preview'),
            'current_lang' => !empty($currentLang) ? array_values($currentLang)[0] : [],
            'nav_menu' => $navMenu,
            'routes' => [
                'logoLink' => !is_null($user) && $user?->is_admin ? route('filament.adminpannel.pages.dashboard') : '/',
            ],
        ], !empty($layouts) ? $layouts->toArray() : []);

        return $result;
    }
}
