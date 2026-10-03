<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureAccountType;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\SecurityHeaders;
use App\Models\User;
use App\Support\SafeRedirect;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

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
            'password.changed' => EnsurePasswordChanged::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);

        // Header keamanan global (00 J.3, 11 §7.4).
        $middleware->append(SecurityHeaders::class);

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

        // 403 / 419 / 429 berbahasa Indonesia (11 §11.3 + F1-R6). AuthorizationException sudah menjadi 403 dan
        // TokenMismatchException menjadi 419 sebelum callback ini dipanggil; ThrottleRequestsException adalah 429.
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            $status = $e->getStatusCode();

            if ($status === 403) {
                $message = $e->getMessage();
                // Pesan bawaan framework/Spatie berbahasa Inggris tidak ditampilkan (ERR-02: tanpa detail izin).
                $detail = ($e instanceof UnauthorizedException || in_array($message, ['', 'This action is unauthorized.', 'Forbidden'], true))
                    ? null
                    : $message;

                return $request->expectsJson()
                    ? response()->json(['message' => $detail ?? 'Anda tidak memiliki izin untuk melakukan tindakan ini.'], 403)
                    : response()->view('errors.403', ['detail' => $detail], 403);
            }

            if ($status === 419) {
                if ($request->expectsJson()) {
                    return response()->json(['message' => 'Sesi Anda telah berakhir. Muat ulang halaman.'], 419);
                }

                return redirect()->to(SafeRedirect::back($request))
                    ->withInput($request->except(['password', 'password_confirmation', 'current_password', 'payment_reference', 'bpjs_number']))
                    ->with('error', 'Sesi formulir Anda telah berakhir. Silakan periksa isian Anda lalu kirim ulang.');
            }

            if ($status === 429) {
                $retry = (int) ($e->getHeaders()['Retry-After'] ?? 60);
                $text = "Terlalu banyak percobaan. Silakan coba lagi dalam {$retry} detik.";

                if ($request->expectsJson()) {
                    return response()->json(['message' => $text], 429, $e->getHeaders());
                }

                if ($request->isMethod('POST')) {
                    return redirect()->to(SafeRedirect::back($request))
                        ->withInput($request->except(['password', 'password_confirmation']))
                        ->with('error', $text)
                        ->header('Retry-After', (string) $retry);
                }

                return response()->view('errors.429', ['retryAfter' => $retry], 429, $e->getHeaders());
            }

            return null; // 404/500/503 ⇒ view errors::{status}
        });
    })->create();
