<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Settings\SettingsService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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

        // URI resource berbahasa Indonesia: /buat dan /ubah (11 �3.3).
        Route::resourceVerbs(['create' => 'buat', 'edit' => 'ubah']);

        Paginator::useBootstrapFive();

        // Pengaturan situs tersedia di semua layout sebagai $site (SettingsService).
        View::composer(['components.layouts.*'], function ($view): void {
            $view->with('site', $this->app->make(SettingsService::class));
        });

        // Rate limiter 00 H (rinci 11 §10.3).
        $this->configureRateLimiting();
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by($this->emailKey($request)));
        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinutes(10, 3)->by($this->emailKey($request)));
        RateLimiter::for('register', fn (Request $request) => Limit::perHour(5)->by((string) $request->ip()));
        RateLimiter::for('booking', fn (Request $request) => Limit::perMinute(10)->by((string) ($request->user()?->id ?: $request->ip())));
        RateLimiter::for('contact', fn (Request $request) => Limit::perMinutes(10, 3)->by((string) $request->ip()));
    }

    /**
     * Kunci limiter berbasis email dinormalkan (trim + huruf kecil) agar variasi penulisan berbagi satu
     * penghitung (Review Focus #4); nilai bukan string (mis. `email[]=x`) dianggap kosong.
     */
    protected function emailKey(Request $request): string
    {
        $email = $request->input('email');

        return Str::lower(trim(is_string($email) ? $email : '')).'|'.$request->ip();
    }
}
