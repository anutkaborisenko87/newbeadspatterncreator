<?php

namespace App\Providers;

use App\Contracts\Services\PageSectionsServiceInterface;
use App\Contracts\Services\PageServiceInterface;
use App\Contracts\Services\PasswordServiceInterface;
use App\Contracts\Services\PatternServiceInterface;
use App\Contracts\Services\ProfileServiceInterface;
use App\Services\PageSectionsService;
use App\Services\PageService;
use App\Services\PasswordService;
use App\Services\PatternService;
use App\Services\ProfileService;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PageServiceInterface::class, PageService::class);
        $this->app->bind(PageSectionsServiceInterface::class, PageSectionsService::class);
        $this->app->bind(ProfileServiceInterface::class, ProfileService::class);
        $this->app->bind(PasswordServiceInterface::class, PasswordService::class);
        $this->app->bind(PatternServiceInterface::class, PatternService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
    }
}
