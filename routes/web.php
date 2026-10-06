<?php

use App\Http\Controllers\Public\ClinicController;
use App\Http\Controllers\Public\DoctorController;
use App\Http\Controllers\Public\PageController;
use App\Http\Controllers\Public\ScheduleController;
use Illuminate\Support\Facades\Route;

// Beranda sementara (F1-R3); HomeController dibangun di fase 4a.
Route::view('/', 'public.home')->name('home');

// Profil (#2) dan Visi dan Misi (#3): isi dari tabel `pages` (F4A-R4).
Route::get('/profil', [PageController::class, 'about'])->name('about');
Route::get('/visi-misi', [PageController::class, 'visionMission'])->name('vision-mission');

// Layanan (#4, #5). Slug tidak ada atau layanan nonaktif menjawab 404, juga bagi staf yang login (A-42).
Route::get('/layanan', [ClinicController::class, 'index'])->name('clinics.index');
Route::get('/layanan/{clinic:slug}', [ClinicController::class, 'show'])->name('clinics.show');

// Dokter (#6). Jadwal dibaca lewat DoctorAvailabilityQuery; pembangkitan sesi harian dipicu di sini (F3-R9).
Route::get('/dokter', [DoctorController::class, 'index'])->name('doctors.index');
// Detail dokter (#7): dokter nonaktif, arsip, atau di poliklinik nonaktif = 404 untuk semua orang, juga staf (F3-R17, A-42).
Route::get('/dokter/{doctor:slug}', [DoctorController::class, 'show'])->name('doctors.show');

// Jadwal dokter (#8): grid mingguan dan daftar bertumpuk dari DoctorAvailabilityQuery (F3-R9, F3-R24).
Route::get('/jadwal-dokter', [ScheduleController::class, 'index'])->name('schedules.index');

// BPJS dan asuransi (#16), panduan pendaftaran (#17), dan kebijakan privasi: isi dari tabel `pages` (F4A-R4).
Route::get('/bpjs-asuransi', [PageController::class, 'insurance'])->name('insurance');
Route::get('/panduan-pendaftaran', [PageController::class, 'registrationGuide'])->name('registration-guide');
Route::get('/kebijakan-privasi', [PageController::class, 'privacy'])->name('privacy');
