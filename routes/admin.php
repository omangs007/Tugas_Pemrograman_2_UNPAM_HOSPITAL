<?php

use App\Http\Controllers\Admin\ArticleCategoryController;
use App\Http\Controllers\Admin\ArticleController;
use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\ClinicController;
use App\Http\Controllers\Admin\ContactMessageController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DoctorController;
use App\Http\Controllers\Admin\DoctorScheduleController;
use App\Http\Controllers\Admin\FacilityController;
use App\Http\Controllers\Admin\FaqController;
use App\Http\Controllers\Admin\GalleryController;
use App\Http\Controllers\Admin\InsurancePartnerController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\PracticeSessionController;
use App\Http\Controllers\Admin\RoomCategoryController;
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
    // Sesi per tanggal (F3-R20): kelola dahulu, lalu lihat. Tanpa show; `destroy` ada (00 I.4).
    Route::middleware('permission:schedules.manage')->group(function (): void {
        Route::patch('jadwal/sesi/{session}/status', [PracticeSessionController::class, 'status'])->name('sessions.status');
        Route::resource('jadwal/sesi', PracticeSessionController::class)
            ->names('sessions')
            ->parameters(['sesi' => 'session'])
            ->except(['index', 'show']);
    });
    Route::middleware('permission:schedules.view')->group(function (): void {
        Route::resource('jadwal/sesi', PracticeSessionController::class)
            ->names('sessions')
            ->parameters(['sesi' => 'session'])
            ->only(['index']);
    });
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

    // Konten website #39 (fase 4a, F4A-R28): semua route memakai permission:content.manage.
    Route::middleware('permission:content.manage')->prefix('konten')->group(function (): void {
        Route::get('halaman', [PageController::class, 'index'])->name('pages.index');
        Route::get('halaman/{page}/ubah', [PageController::class, 'edit'])->name('pages.edit');
        Route::match(['put', 'patch'], 'halaman/{page}', [PageController::class, 'update'])->name('pages.update');
        Route::resource('banner', BannerController::class)
            ->names('banners')
            ->parameters(['banner' => 'banner'])
            ->except(['show']);
        Route::resource('kategori-artikel', ArticleCategoryController::class)
            ->names('article-categories')
            ->parameters(['kategori-artikel' => 'articleCategory'])
            ->except(['show']);
        Route::resource('artikel', ArticleController::class)
            ->names('articles')
            ->parameters(['artikel' => 'article'])
            ->except(['show']);
        // Galeri (halaman #39, F-ADM-21; F4A-R23): album dibuat tanpa foto, foto diunggah lewat route terpisah.
        Route::resource('galeri', GalleryController::class)
            ->names('galleries')
            ->parameters(['galeri' => 'gallery'])
            ->except(['show']);
        Route::post('galeri/{gallery}/foto', [GalleryController::class, 'storePhotos'])->name('galleries.photos.store');
        // FAQ (halaman #39, F-ADM-22; F4A-R22): urutan = field sort_order, tanpa route reorder.
        Route::resource('faq', FaqController::class)
            ->names('faqs')
            ->parameters(['faq' => 'faq'])
            ->except(['show']);
        // Mitra asuransi (halaman #39, F-ADM-23; F4A-R21): hapus ditolak bila dirujuk appointment (BR-180).
        Route::resource('asuransi', InsurancePartnerController::class)
            ->names('insurance-partners')
            ->parameters(['asuransi' => 'insurancePartner'])
            ->except(['show']);
    });

    // Fasilitas dan kamar (halaman #36) serta kotak pesan kontak, fase 4b (F4B-R13). Semua di dalam G-ADMIN; tanpa show (00 I.6).
    // Fasilitas (tab Fasilitas): izin tunggal facilities.manage.
    Route::middleware('permission:facilities.manage')->group(function (): void {
        Route::resource('fasilitas', FacilityController::class)
            ->names('facilities')
            ->parameters(['fasilitas' => 'facility'])
            ->except(['show']);
    });
    // Kategori kamar (tab Kamar dan Kapasitas, F-ADM-10/11): struktur memakai facilities.manage; daftar terbuka juga bagi
    // rooms.update-availability (mode ketersediaan Petugas, D-12); aksi ketersediaan memakai rooms.update-availability. Tanpa show.
    Route::middleware('permission:facilities.manage')->group(function (): void {
        Route::resource('kamar', RoomCategoryController::class)
            ->names('room-categories')
            ->parameters(['kamar' => 'roomCategory'])
            ->except(['index', 'show']);
    });
    Route::get('kamar', [RoomCategoryController::class, 'index'])
        ->middleware('permission:facilities.manage|rooms.update-availability')
        ->name('room-categories.index');
    Route::patch('kamar/{roomCategory}/ketersediaan', [RoomCategoryController::class, 'availability'])
        ->middleware('permission:rooms.update-availability')
        ->name('room-categories.availability');
    // Kotak pesan kontak (F-ADM-24): izin tunggal messages.manage; tanpa destroy (00 C, KNS-01).
    Route::middleware('permission:messages.manage')->group(function (): void {
        Route::get('pesan', [ContactMessageController::class, 'index'])->name('messages.index');
        Route::get('pesan/{message}', [ContactMessageController::class, 'show'])->name('messages.show');
        Route::patch('pesan/{message}/status', [ContactMessageController::class, 'status'])->name('messages.status');
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
