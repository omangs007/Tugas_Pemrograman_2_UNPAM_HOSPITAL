<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Auth\AdminLoginController;
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
    Route::post('/login', [AdminLoginController::class, 'store'])->name('login.store');
});

Route::post('/logout', [AdminLoginController::class, 'destroy'])
    ->middleware(['auth', 'account:staff'])
    ->name('logout');

// G-ADMIN sementara (Task 15 menambah `password.changed`).
Route::middleware(['auth', 'account:staff', 'active'])->group(function (): void {
    Route::get('/', [DashboardController::class, 'index'])
        ->middleware('permission:dashboard.view')
        ->name('dashboard');
});
