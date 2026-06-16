<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Forzar URLs con HTTPS en producción
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Tu configuración de Vite (prefetch)
        Vite::prefetch(concurrency: 3);
    }
}
