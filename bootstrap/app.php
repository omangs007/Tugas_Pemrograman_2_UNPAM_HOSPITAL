<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureAccountType;
use App\Models\User;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Grup pasien (routes/patient.php) ditambahkan pada fase 5.

            // Parameter admin berbasis id harus numerik (11 §1 aturan 2). Tidak memakai Route::pattern global
            // karena nama {doctor}, {clinic}, dst. juga dipakai route publik berbasis slug.
            Route::middleware('web')
                ->prefix('admin')
                ->name('admin.')
                ->where(array_fill_keys([
                    'doctor', 'specialty', 'clinic', 'schedule', 'exception', 'session', 'facility',
                    'roomCategory', 'patient', 'appointment', 'page', 'banner', 'article',
                    'articleCategory', 'gallery', 'faq', 'insurancePartner', 'user', 'role',
                    'auditLog', 'message', 'media',
                ], '[0-9]+'))
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'account' => EnsureAccountType::class,
            'active' => EnsureAccountIsActive::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);

        // Tamu yang membuka halaman ber-`auth`: /admin* ⇒ login admin; selain itu ⇒ login pasien.
        // Cadangan F1-R2: `login` belum ada sampai fase 5, jadi arahkan ke beranda.
        $middleware->redirectGuestsTo(function (Request $request): string {
            if ($request->is('admin', 'admin/*')) {
                return route('admin.login');
            }

            return Route::has('login') ? route('login') : route('home');
        });

        // Pengguna login yang membuka halaman ber-`guest`: arahkan sesuai tipe akun (cadangan F1-R2).
        $middleware->redirectUsersTo(function (Request $request): string {
            $user = $request->user();

            if ($user instanceof User && $user->isStaff()) {
                return route('admin.dashboard');
            }

            return Route::has('patient.dashboard') ? route('patient.dashboard') : route('home');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['password', 'password_confirmation', 'current_password', 'bpjs_number', 'payment_reference']);
        $exceptions->shouldRenderJsonWhen(fn (Request $request, Throwable $e): bool => $request->expectsJson()
            || $request->routeIs('admin.dashboard.chart', 'admin.media.*'));
    })->create();
