<?php

use App\Http\Controllers\Patient\AppointmentController;
use App\Http\Controllers\Patient\DashboardController;
use App\Http\Controllers\Patient\PasswordController;
use App\Http\Controllers\Patient\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Route portal pasien
|--------------------------------------------------------------------------
|
| Dimuat dari bootstrap/app.php dengan awalan URL /pasien, awalan nama `patient.`, dan middleware
| `web`, `auth`, `account:patient`, `active` (G-PASIEN, 11 §1, §3.1). Route statis /pendaftaran/baru, /tinjau, dan
| / didaftarkan SEBELUM `{appointment:booking_code}` (kerangka 11 §3.4); tidak ada route wildcard lain di bawah /pasien.
|
*/

// Dashboard pasien (#27): sapaan, nomor pasien, kunjungan mendatang (kosong sampai fase 6).
Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

// Profil dan akun (#28): satu halaman, dua formulir (F5-R18, F5-R21). Tanpa id profil di URL (BR-150); nama
// `patient.profile.*` menandai item menu "Profil dan Akun" sebagai aktif, `patient.password.update` tidak.
Route::get('/profil', [ProfileController::class, 'edit'])->name('profile.edit');
Route::put('/profil', [ProfileController::class, 'update'])->name('profile.update');
Route::put('/password', [PasswordController::class, 'update'])->name('password.update');

// Pendaftaran kunjungan (#29, F6-R3): langkah 1 sampai 4 = GET /baru (query poli, dokter, tanggal, sesi).
Route::prefix('pendaftaran')->name('appointments.')->controller(AppointmentController::class)->group(function (): void {
    Route::get('/baru', 'create')->name('create');
    Route::post('/tinjau', 'review')->name('review');
    Route::post('/', 'store')->middleware('throttle:booking')->name('store');

    // Route berparameter SESUDAH route statis; kode booking berpola Crockford, selain itu 404 (BR-54, kerangka 11 ?3.4).
    Route::prefix('{appointment:booking_code}')
        ->where(['appointment' => 'UPH-[0-9A-HJKMNP-TV-Z]{4}-[0-9A-HJKMNP-TV-Z]{4}'])
        ->group(function (): void {
            Route::get('/', 'show')->name('show');
        });
});
