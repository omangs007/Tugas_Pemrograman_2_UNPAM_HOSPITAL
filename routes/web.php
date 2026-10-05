<?php

use App\Http\Controllers\Public\ClinicController;
use App\Http\Controllers\Public\DoctorController;
use Illuminate\Support\Facades\Route;

// Beranda sementara (F1-R3); HomeController dibangun di fase 4a.
Route::view('/', 'public.home')->name('home');

// Layanan (#4, #5). Slug tidak ada atau layanan nonaktif menjawab 404, juga bagi staf yang login (A-42).
Route::get('/layanan', [ClinicController::class, 'index'])->name('clinics.index');
Route::get('/layanan/{clinic:slug}', [ClinicController::class, 'show'])->name('clinics.show');

// Dokter (#6). Jadwal dibaca lewat DoctorAvailabilityQuery; pembangkitan sesi harian dipicu di sini (F3-R9).
Route::get('/dokter', [DoctorController::class, 'index'])->name('doctors.index');
