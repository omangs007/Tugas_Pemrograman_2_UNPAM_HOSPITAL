<?php

use App\Http\Controllers\Admin\ClinicController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DoctorController;
use App\Http\Controllers\Admin\DoctorScheduleController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\ScheduleExceptionController;
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

    // Dokter (halaman #33), 11 §3.4: izin lihat ≠ kelola, jadi kelola didaftarkan DAHULU agar /dokter/buat
    // tidak tertangkap {doctor}, lalu lihat. Dokter tidak punya destroy (diarsipkan, bukan dihapus; BR-03).
    Route::middleware('permission:doctors.manage')->group(function (): void {
        Route::resource('dokter', DoctorController::class)
            ->names('doctors')
            ->parameters(['dokter' => 'doctor'])
            ->except(['index', 'show', 'destroy']);
        Route::patch('dokter/{doctor}/status', [DoctorController::class, 'status'])->name('doctors.status');
        Route::patch('dokter/{doctor}/arsip', [DoctorController::class, 'archive'])->name('doctors.archive');
        Route::post('dokter/{doctor}/foto', [DoctorController::class, 'photo'])->name('doctors.photo');
    });
    Route::middleware('permission:doctors.view')->group(function (): void {
        Route::resource('dokter', DoctorController::class)
            ->names('doctors')
            ->parameters(['dokter' => 'doctor'])
            ->only(['index', 'show']);
    });

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

    // Jadwal praktik (halaman #35, F3-R19/R20, 11 §3.4). Urutan pendaftaran di berkas MENGIKAT:
    //   1. rute sesi `jadwal/sesi*` (T12), 2. rute pengecualian `jadwal/pengecualian*` (T11), 3. resource `jadwal` (T10).
    // Segmen statis didaftarkan sebelum resource berparameter; di tiap modul kelola lebih dulu, baru lihat.
    // Tidak ada rute `show`, dan tidak ada rute apa pun di bawah `admin/dokter*`.
    // [FASE-3 T12] Rute sesi per tanggal didaftarkan di sini.
    // Pengecualian (cuti dan libur), F3-R19: kelola dahulu, lalu lihat. Tanpa show.
    Route::middleware('permission:schedules.manage')->group(function (): void {
        Route::resource('jadwal/pengecualian', ScheduleExceptionController::class)
            ->names('exceptions')
            ->parameters(['pengecualian' => 'exception'])
            ->except(['index', 'show']);
    });
    Route::middleware('permission:schedules.view')->group(function (): void {
        Route::resource('jadwal/pengecualian', ScheduleExceptionController::class)
            ->names('exceptions')
            ->parameters(['pengecualian' => 'exception'])
            ->only(['index']);
    });
    Route::middleware('permission:schedules.manage')->group(function (): void {
        Route::resource('jadwal', DoctorScheduleController::class)
            ->names('schedules')
            ->parameters(['jadwal' => 'schedule'])
            ->except(['index', 'show']);
    });
    Route::middleware('permission:schedules.view')->group(function (): void {
        Route::resource('jadwal', DoctorScheduleController::class)
            ->names('schedules')
            ->parameters(['jadwal' => 'schedule'])
            ->only(['index']);
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
