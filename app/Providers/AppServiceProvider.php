<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
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
        // Force HTTPS URL scheme when behind Cloudflare Tunnel, aaPanel reverse proxy, or production
        if (
            request()->header('x-forwarded-proto') === 'https' ||
            request()->header('cf-visitor') !== null ||
            request()->header('cf-ray') !== null ||
            env('FORCE_HTTPS', false) === true ||
            app()->environment('production')
        ) {
            URL::forceScheme('https');
        }
    }
}
