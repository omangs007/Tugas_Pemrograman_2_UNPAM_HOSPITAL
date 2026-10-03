<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Auth\AdminLoginController;
use App\Http\Controllers\Auth\AdminPasswordController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Route area admin
|--------------------------------------------------------------------------
|
| Dimuat dari bootstrap/app.php dengan awalan URL /admin, awalan nama `admin.`, dan middleware `web`.
| Route di luar grup G-ADMIN (login, logout, ganti password) tidak dipagari `password.changed`.
|
*/

// A. Login admin (hanya tamu) dan logout.
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AdminLoginController::class, 'create'])->name('login');
    Route::post('/login', [AdminLoginController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.store');
});

Route::post('/logout', [AdminLoginController::class, 'destroy'])
    ->middleware(['auth', 'account:staff'])
    ->name('logout');

// Ganti password: TANPA `password.changed` agar tidak berulang (00 I.4).
Route::middleware(['auth', 'account:staff', 'active'])->group(function (): void {
    Route::get('/ganti-password', [AdminPasswordController::class, 'edit'])->name('password.edit');
    Route::put('/ganti-password', [AdminPasswordController::class, 'update'])->name('password.update');
});

// G-ADMIN: semua halaman admin lain.
Route::middleware(['auth', 'account:staff', 'active', 'password.changed'])->group(function (): void {
    Route::get('/', [DashboardController::class, 'index'])
        ->middleware('permission:dashboard.view')
        ->name('dashboard');
});
