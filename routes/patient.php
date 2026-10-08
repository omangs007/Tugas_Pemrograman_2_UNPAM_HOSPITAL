<?php

use App\Http\Controllers\Patient\DashboardController;
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
