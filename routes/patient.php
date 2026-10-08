<?php

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
| `web`, `auth`, `account:patient`, `active` (G-PASIEN, 11 §1, §3.1). Tidak ada route wildcard di bawah /pasien:
| /pasien/pendaftaran/* dan /pasien/notifikasi/* baru ada di fase 6 (InternalLinkTest dan BannerButtonTest
| mengunci bahwa /pasien/pendaftaran/baru belum ada).
|
*/

// Dashboard pasien (#27): sapaan, nomor pasien, kunjungan mendatang (kosong sampai fase 6).
Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

// Profil dan akun (#28): satu halaman, dua formulir (F5-R18, F5-R21). Tanpa id profil di URL (BR-150); nama
// `patient.profile.*` menandai item menu "Profil dan Akun" sebagai aktif, `patient.password.update` tidak.
Route::get('/profil', [ProfileController::class, 'edit'])->name('profile.edit');
Route::put('/profil', [ProfileController::class, 'update'])->name('profile.update');
Route::put('/password', [PasswordController::class, 'update'])->name('password.update');
