<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
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
        // Mode ketat hanya di non-produksi: mencegah lazy loading (N+1), atribut yang hilang,
        // dan atribut yang diam-diam dibuang saat mass assignment (11 §3.3).
        Model::shouldBeStrict(! $this->app->isProduction());
    }
}
