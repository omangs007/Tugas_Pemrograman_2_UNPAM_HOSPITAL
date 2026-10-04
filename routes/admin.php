<?php

use App\Http\Controllers\Admin\ClinicController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\SpecialtyController;
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

    // Poliklinik (halaman #34, tab Poliklinik): izin tunggal clinics.manage, tanpa show (11 §3.4).
    Route::middleware('permission:clinics.manage')->group(function (): void {
        Route::resource('poliklinik', ClinicController::class)
            ->names('clinics')
            ->parameters(['poliklinik' => 'clinic'])
            ->except(['show']);
    });

    // Spesialisasi (halaman #34, tab Spesialisasi): izin tunggal clinics.manage, tanpa show (11 §3.4).
    Route::middleware('permission:clinics.manage')->group(function (): void {
        Route::resource('spesialisasi', SpecialtyController::class)
            ->names('specialties')
            ->parameters(['spesialisasi' => 'specialty'])
            ->except(['show']);
    });

    // J. Media (11 §2.3 J, F2-R3). JSON; izin akhir ditentukan MediaPolicy menurut pemilik media.
    // `editor` dan `urutkan` bersifat statis dan harus didaftarkan SEBELUM `{media}`.
    Route::post('/media/editor', [MediaController::class, 'editorUpload'])
        ->middleware('permission:content.manage|clinics.manage|facilities.manage')
        ->name('media.editor-upload');
    Route::post('/media/urutkan', [MediaController::class, 'reorder'])
        ->middleware('permission:doctors.manage|clinics.manage|facilities.manage|content.manage')
        ->name('media.reorder');
    Route::middleware('permission:doctors.manage|clinics.manage|facilities.manage|content.manage|settings.manage')->group(function (): void {
        Route::patch('/media/{media}', [MediaController::class, 'update'])->name('media.update');
        Route::delete('/media/{media}', [MediaController::class, 'destroy'])->name('media.destroy');
    });
});
