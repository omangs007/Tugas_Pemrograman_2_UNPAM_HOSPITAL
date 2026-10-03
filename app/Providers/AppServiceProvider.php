<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Settings\SettingsService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(SettingsService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Mode ketat hanya di non-produksi: mencegah lazy loading (N+1), atribut yang hilang,
        // dan atribut yang diam-diam dibuang saat mass assignment (11 §3.3).
        Model::shouldBeStrict(! $this->app->isProduction());

        // Super Admin melewati semua Gate/Policy (00 G). Hanya untuk pengguna login;
        // aturan mutlak (peran terkunci, minimal satu Super Admin aktif) ditegakkan di StaffAccountService.
        Gate::before(fn (User $user, string $ability) => $user->hasRole('super-admin') ? true : null);
    }
}
