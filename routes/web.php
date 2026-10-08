<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Public\ArticleController;
use App\Http\Controllers\Public\ClinicController;
use App\Http\Controllers\Public\ContactController;
use App\Http\Controllers\Public\DoctorController;
use App\Http\Controllers\Public\FacilityController;
use App\Http\Controllers\Public\FaqController;
use App\Http\Controllers\Public\GalleryController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\PageController;
use App\Http\Controllers\Public\RoomCategoryController;
use App\Http\Controllers\Public\ScheduleController;
use Illuminate\Support\Facades\Route;

// Beranda (#1): banner, layanan, dokter, artikel, dan CTA; memicu pembangkitan sesi harian (D-66, F4A-R17).
Route::get('/', [HomeController::class, 'index'])->name('home');

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

// Fasilitas (#9, #10): hanya fasilitas aktif; yang nonaktif 404 untuk semua orang, termasuk staf (F4B-R14, A-42).
Route::get('/fasilitas', [FacilityController::class, 'index'])->name('facilities.index');
Route::get('/fasilitas/{facility:slug}', [FacilityController::class, 'show'])->name('facilities.show');

// Kamar rawat inap (#11, #12-#15): hanya kategori aktif; satu route detail untuk keempat slug seed; nonaktif 404
// untuk semua orang, termasuk staf (F4B-R14, F4B-R30, A-42).
Route::get('/kamar-rawat-inap', [RoomCategoryController::class, 'index'])->name('rooms.index');
Route::get('/kamar-rawat-inap/{roomCategory:slug}', [RoomCategoryController::class, 'show'])->name('rooms.show');

// BPJS dan asuransi (#16), panduan pendaftaran (#17), dan kebijakan privasi: isi dari tabel `pages` (F4A-R4).
Route::get('/bpjs-asuransi', [PageController::class, 'insurance'])->name('insurance');
Route::get('/panduan-pendaftaran', [PageController::class, 'registrationGuide'])->name('registration-guide');

// Artikel kesehatan (#18, #19): hanya yang tayang; draf, terjadwal, dan terhapus menjawab 404 untuk semua orang.
Route::get('/artikel', [ArticleController::class, 'index'])->name('articles.index');
Route::get('/artikel/{article:slug}', [ArticleController::class, 'show'])->name('articles.show');

// Galeri (#20) dan detail album: hanya album terbit; album draf 404 untuk semua orang, termasuk staf.
Route::get('/galeri', [GalleryController::class, 'index'])->name('galleries.index');
Route::get('/galeri/{gallery:slug}', [GalleryController::class, 'show'])->name('galleries.show');

// FAQ (#21): hanya FAQ terbit, dikelompokkan per kategori.
Route::get('/faq', [FaqController::class, 'index'])->name('faq');

// Kontak dan Lokasi (#22) dan kiriman pesan: throttle `contact` = 3 kiriman per 10 menit per IP (F4B-R6, D-23).
Route::get('/kontak', [ContactController::class, 'show'])->name('contact');
Route::post('/kontak', [ContactController::class, 'store'])->middleware('throttle:contact')->name('contact.store');
Route::get('/kebijakan-privasi', [PageController::class, 'privacy'])->name('privacy');

// Auth pasien (#23–#26, 11 §2.1)
Route::middleware('guest')->group(function (): void {
    Route::get('/masuk', [LoginController::class, 'create'])->name('login');
    Route::post('/masuk', [LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');
    Route::get('/daftar', [RegisterController::class, 'create'])->name('register');
    Route::post('/daftar', [RegisterController::class, 'store'])->middleware('throttle:register')->name('register.store');
});
// Logout di luar grup `guest` supaya akun yang sedang login bisa keluar (F5-R8).
Route::post('/keluar', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');
