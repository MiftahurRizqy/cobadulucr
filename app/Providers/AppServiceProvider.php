<?php

namespace App\Providers;

use App\Services\NavigationData;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('layouts.app', function ($view): void {
            if (auth()->check()) {
                $view->with(app(NavigationData::class)->for(auth()->user()));
            }
        });
    }
}
