<?php

namespace App\Providers;

use App\Services\NavigationData;
use App\Services\TenantManager;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer(['layouts.app', 'work._tabs'], function ($view): void {
            if (auth()->check()) {
                $view->with(app(NavigationData::class)->for(auth()->user()))
                    ->with('tenant', app(TenantManager::class)->current());
            }
        });
    }
}
