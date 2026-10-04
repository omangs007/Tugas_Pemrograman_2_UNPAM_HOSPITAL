# 10 — Struktur Folder dan Komponen Laravel

> **Tujuan bagian.** Menetapkan pohon folder proyek, pemetaan file view Blade ke 40 halaman (+ layar tambahan), katalog komponen Blade, daftar enum dengan helper-nya, konvensi penamaan/Vite, dan konfigurasi penting, sehingga developer dapat membuat kerangka proyek tanpa menebak letak file. Seluruh nama route, izin, tabel, kolom, enum, dan service mengikuti [00-keputusan-inti.md](00-keputusan-inti.md).
>
> **Bagian terkait:** [03-arsitektur.md](03-arsitektur.md) (lapisan & alur), [05-sitemap-matriks-halaman.md](05-sitemap-matriks-halaman.md) (matriks 40 halaman), [07-wireframe.md](07-wireframe.md) (tata letak), [08-erd.md](08-erd.md) & [09-data-dictionary.md](09-data-dictionary.md) (tabel), [11-route-controller-model-service.md](11-route-controller-model-service.md) (isi route/controller/model/service), [12-aturan-bisnis-validasi-keamanan.md](12-aturan-bisnis-validasi-keamanan.md) (aturan field), [13-data-contoh.md](13-data-contoh.md) (seeder), [14-tahapan-implementasi.md](14-tahapan-implementasi.md), [16-instalasi-operasional.md](16-instalasi-operasional.md).
>
> **Penanda:** **[ASUMSI]** = asumsi baru di bagian ini; **[KONFIRMASI]** = perlu keputusan pemilik. Nama yang tidak ada di 00 dikumpulkan di akhir pada subjudul *Nama baru yang diusulkan*.

---

## 1. Pohon Folder Proyek

Root repo = root Laravel (`D:\Project Web RS`). Folder `docs/` berdampingan dengan folder aplikasi. Hanya folder/file yang bermakna bagi blueprint yang ditulis; folder bawaan skeleton Laravel 12 yang tidak diubah (mis. `vendor/`, `node_modules/`) tidak diuraikan.

```
D:\Project Web RS\                      ← root repo = root Laravel
├── app/
│   ├── Console/
│   │   └── Commands/
│   │       ├── GenerateSessions.php            # sessions:generate {--days=}
│   │       ├── MarkNoShowAppointments.php      # appointments:mark-no-show {--date=}
│   │       ├── PruneMedia.php                  # media:prune {--dry-run}
│   │       └── CreateSuperAdmin.php            # app:create-super-admin (produksi)
│   ├── Enums/                                  # PHP backed enum (00 D) + helper label()/color()/icon()
│   │   ├── Concerns/HasOptions.php             # values(), options() untuk <select>
│   │   ├── AccountType.php   AppointmentStatus.php   PaymentType.php   BookingChannel.php
│   │   ├── SessionStatus.php ExceptionType.php       DoctorStatus.php  Gender.php
│   │   ├── ClinicType.php    FacilityCategory.php    ArticleStatus.php ActorType.php
│   │   └── ContactMessageStatus.php  SessionResolution.php  Availability.php
│   ├── Exceptions/
│   │   ├── BookingException.php                # galat bisnis booking (kode F.5), pesan Indonesia
│   │   └── InvalidTransitionException.php      # turunan BookingException untuk transisi status (F.8)
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Controller.php                  # abstract; memakai AuthorizesRequests
│   │   │   ├── Public/                         # website publik (tanpa login)
│   │   │   ├── Auth/                           # login/registrasi/reset (pasien) + login & ganti password admin
│   │   │   ├── Patient/                        # portal pasien (/pasien)
│   │   │   └── Admin/                          # dashboard admin (/admin)
│   │   ├── Middleware/
│   │   │   ├── EnsureAccountType.php           # alias `account`
│   │   │   ├── EnsureAccountIsActive.php       # alias `active`
│   │   │   ├── EnsurePasswordChanged.php       # alias `password.changed`
│   │   │   └── SecurityHeaders.php             # global
│   │   └── Requests/
│   │       ├── ListFilterRequest.php                 # basis permintaan GET-filter yang longgar (parameter tak valid diabaikan, 00 I.5)
│   │       ├── Concerns/HasBookingRules.php          # trait aturan pembayaran/keluhan bersama Review/Store/StoreWalkIn (12 V-10, V-11, V-14)
│   │       ├── Auth/  Public/  Patient/  Admin/   # satu Form Request per aksi tulis/filter (bagian 11 §9)
│   ├── Models/
│   │   ├── Concerns/
│   │   │   ├── HasMedia.php                    # relasi `media` morphMany + firstMedia($collection)
│   │   │   └── SanitizesRichText.php           # sanitasi kolom rich text saat `saving`
│   │   └── User.php PatientProfile.php Specialty.php Clinic.php Doctor.php DoctorSchedule.php
│   │       ScheduleException.php PracticeSession.php Appointment.php AppointmentStatusHistory.php
│   │       RoomCategory.php Facility.php Page.php ArticleCategory.php Article.php Banner.php
│   │       Gallery.php Faq.php InsurancePartner.php ContactMessage.php Media.php
│   │       WebsiteSetting.php AuditLog.php
│   ├── Notifications/
│   │   ├── AppointmentStatusChangedNotification.php    # via(): database untuk setiap transisi; + mail hanya untuk confirmed/cancelled (00 F.8, K-13)
│   │   ├── AppointmentScheduleChangedNotification.php  # database + mail (perubahan jadwal, schedule_changed_at)
│   │   └── PatientWelcomeNotification.php              # mail saja, setelah registrasi akun (K-22 default a; tanpa verifikasi wajib)
│   ├── Observers/
│   │   └── AuditObserver.php                   # audit generik create/update/delete model CRUD
│   ├── Policies/                               # 22 policy (00 J.4), satu per model/entitas
│   ├── Providers/
│   │   └── AppServiceProvider.php              # Gate::before, RateLimiter, Paginator, strict mode, binding, composer
│   ├── Rules/                                  # nama mengikuti 00 J.6; rinci di 12 §3.1
│   │   └── PersonName.php  IndonesianPhone.php  NoHtml.php  InternalOrHttpsUrl.php
│   │       NoOverlappingSchedule.php  NoOverlappingSession.php
│   │       ImageRules.php  PasswordRules.php   # helper: ImageRules::for($collection), PasswordRules::default() (mengembalikan larik aturan)
│   ├── Services/
│   │   ├── Scheduling/   PracticeSessionGenerator.php  DoctorAvailabilityQuery.php  ScheduleChangeService.php
│   │   │                 AvailabilityFilter.php  SessionAvailability.php  ScheduleChangeReport.php   # DTO
│   │   ├── Booking/      BookingService.php  AppointmentStatusService.php  BookingCodeGenerator.php
│   │   │                 QueueNumberFormatter.php  AppointmentProofService.php
│   │   │                 BookingData.php  BookingRules.php                                       # DTO input; pemeriksaan F.5 (00 J.6)
│   │   ├── Patients/     PatientNumberGenerator.php  PatientAccountLinker.php  PatientRegistrationService.php
│   │   ├── Media/        MediaService.php  MediaPruneReport.php  PlaceholderImageGenerator.php   # generator gambar contoh lokal untuk seeder (00 J.6)
│   │   ├── Content/      HtmlSanitizer.php
│   │   ├── Audit/        AuditLogger.php
│   │   ├── Settings/     SettingsService.php
│   │   ├── Reports/      AppointmentCsvExporter.php
│   │   ├── Dashboard/    DashboardMetrics.php
│   │   └── Staff/        StaffAccountService.php
│   ├── Support/
│   │   ├── Actor.php                           # value object pelaku (patient | staff | system)
│   │   └── SafeRedirect.php                    # URL kembali yang aman untuk handler exception (wizard booking)
│   └── View/
│       └── Components/                         # komponen berbasis kelas (yang punya logika)
│           ├── StatusBadge.php  AvailabilityBadge.php  Image.php  MapEmbed.php
├── bootstrap/
│   ├── app.php                                 # withRouting(then:), alias middleware, penanganan exception
│   └── providers.php                           # [AppServiceProvider::class]
├── config/
│   ├── app.php                                 # timezone Asia/Jakarta, locale id, fallback en, faker id_ID
│   ├── filesystems.php                         # disk `public` & `local` (privat)
│   ├── purifier.php                            # profil `rich_text`
│   ├── permission.php                          # Spatie (guard web, tanpa teams)
│   ├── dompdf.php                              # font lokal, options.enable_remote=false
│   └── navigation.php                          # definisi menu publik/pasien/admin (label, route, ikon, izin)
├── database/
│   ├── migrations/                             # urutan dependensi: lihat §1.1
│   ├── seeders/                                # bagian 13 (DemoSeeder hanya local/testing)
│   └── factories/                              # untuk pengujian & seeder demo
├── docs/
│   └── superpowers/specs/2026-10-02-unpam-hospital-blueprint/   # dokumen blueprint ini (00–17)
├── lang/
│   ├── id/                                     # auth.php pagination.php passwords.php validation.php
│   │                                           # + booking.php (pesan BookingException) + messages.php (flash)
│   └── id.json                                 # teks bawaan framework (email reset password, dll.) dalam Bahasa Indonesia
├── public/
│   ├── images/placeholders/                    # doctor|clinic|facility|room|article|banner|gallery|logo .svg
│   ├── build/                                  # keluaran Vite (di-ignore git)
│   ├── storage → ../storage/app/public         # symlink `php artisan storage:link` (di-ignore git)
│   ├── .htaccess  index.php  robots.txt  favicon.svg
├── resources/
│   ├── scss/
│   │   ├── app.scss                            # entry CSS publik + pasien + auth
│   │   ├── admin.scss                          # entry CSS admin (diimpor oleh admin.js)
│   │   ├── _variables.scss                     # token warna 00 M + override variabel Bootstrap
│   │   ├── _public.scss                        # gaya situs publik (hero, kartu dokter, footer)
│   │   ├── _admin.scss                         # gaya admin (sidebar, data-table, stat-card)
│   │   └── _print.scss                         # @media print bukti pendaftaran
│   ├── js/
│   │   ├── app.js                              # entry JS publik/pasien/auth: Bootstrap, font, loading-button
│   │   ├── admin.js                            # entry JS admin: mengimpor app.js + admin.scss, impor lazy editor/charts
│   │   ├── editor.js                           # Quill 2 (rich text), dimuat lazy oleh admin.js
│   │   ├── charts.js                           # Chart.js (dashboard), dimuat lazy oleh admin.js
│   │   ├── booking.js                          # wizard: tampil/sembunyi field BPJS/asuransi, loading
│   │   └── image-preview.js                    # pratinjau gambar sebelum unggah (form.file)
│   └── views/
│       ├── components/                         # komponen Blade (§3)
│       ├── public/  auth/  patient/  admin/    # halaman per area (§2)
│       ├── pdf/                                # PDF dompdf
│       ├── errors/                             # 403 404 419 429 500 503 (Bahasa Indonesia)
│       └── vendor/pagination/                  # bootstrap-5.blade.php (label Indonesia, aria-label)
├── routes/
│   ├── web.php                                 # publik + auth pasien
│   ├── patient.php                             # prefix /pasien, nama patient.
│   ├── admin.php                               # prefix /admin, nama admin.
│   └── console.php                             # (opsional) jadwal otomatis; fitur inti tidak bergantung padanya
├── scripts/
│   ├── backup.ps1                              # mysqldump (C:\xampp3\mysql\bin; mesin lain: C:\xampp) + zip storage/app/public & private
│   └── restore.ps1
├── storage/
│   ├── app/
│   │   ├── public/                             # media publik: {collection}/{YYYY}/{MM}/{uuid}.{ext} (+ thumb)
│   │   ├── private/                            # disk `local`: dokumen pasien masa depan (via controller+policy)
│   │   └── purifier/                           # cache definisi HTMLPurifier
│   ├── fonts/                                  # cache/font dompdf
│   └── logs/                                   # laravel-YYYY-MM-DD.log (mail reset password lokal)
├── tests/
│   ├── Feature/                                # Auth/ Public/ Patient/ Admin/ Booking/ Scheduling/ Security/ Access/ Seeding/ (00 J.6)
│   ├── Unit/                                   # Enums/ Services/ Models/
│   ├── Concurrency/                            # KON-*: tes proses paralel (grup `concurrency`; 15 §6)
│   ├── Support/Concurrency/book-session.php    # skrip pembantu proses anak (bukan perintah artisan)
│   └── TestCase.php
├── .env.example  .env.testing.example  .gitignore  composer.json  package.json  vite.config.js
├── phpunit.xml  pint.json  artisan
```

### 1.1 Keterangan folder dan file penting

| Lokasi | Isi / aturan |
|---|---|
| `app/Enums` | Satu file per enum di 00 D. Nilai tersimpan = string snake_case (`no_show`), case = PascalCase (`NoShow`). Semua enum punya `label()`; yang tampil sebagai badge juga `color()` dan `icon()` (§4). |
| `app/Http/Controllers/Controller.php` | Kelas abstrak yang memakai trait `AuthorizesRequests` (skeleton Laravel 12 tidak lagi menyertakannya). Controller memanggil `$this->authorize(...)` secara eksplisit; `authorizeResource()` **tidak dipakai** karena bergantung pada `$this->middleware()` yang sudah dihapus dari base controller. |
| `app/Http/Controllers/{Public,Auth,Patient,Admin}` | Controller tipis (validasi di Form Request, aturan bisnis di Service). `Public` sah sebagai segmen namespace di PHP ≥ 8.0 (sudah diuji `php -l`). |
| `app/Http/Requests/{Auth,Public,Patient,Admin}` | Satu kelas per aksi; `authorize()` memanggil policy/izin. Daftar lengkap di 11 §9, aturan field di 12. |
| `app/Models/Concerns` | `HasMedia` (relasi `media` + `firstMedia()`), `HasUniqueSlug` (slug otomatis unik dan stabil, F2-R5), `SanitizesRichText` (daftar kolom rich text per model, disanitasi saat `saving` sehingga seeder pun aman). |
| `app/Observers/AuditObserver.php` | Dipasang di `AppServiceProvider` untuk model CRUD sederhana; peristiwa domain (booking, transisi, jadwal) dicatat eksplisit oleh service lewat `AuditLogger`. |
| `app/Policies` | `{Model}Policy` (auto-discovery untuk `App\Models\*`); `RolePolicy` didaftarkan manual via `Gate::policy(Role::class, RolePolicy::class)` karena `Role` milik Spatie. |
| `app/Providers/AppServiceProvider.php` | `Gate::before` (Super Admin), `RateLimiter::for` (5 limiter 00 H), `Paginator::useBootstrapFive()`, `Model::shouldBeStrict(! app()->isProduction())` (mencakup `preventLazyLoading`), `Route::resourceVerbs`, `Route::bind('user')`, `Gate::policy(Role)`, `View::composer` untuk `$site`, pendaftaran `AuditObserver`. Rinci di 11 §3.3. |
| `app/Rules` | Aturan validasi kustom yang dipakai Form Request; definisi rinci di 12. |
| `app/Services` | Satu-satunya tempat aturan bisnis (bagian 11 §6). DTO/value object diletakkan di folder service pemakainya; `Actor` di `app/Support` karena dipakai lintas service. |
| `app/View/Components` | Hanya komponen yang punya logika PHP (`StatusBadge`, `AvailabilityBadge`, `Image`, `MapEmbed`). Sisanya komponen anonim di `resources/views/components`. |
| `bootstrap/app.php` | Satu-satunya tempat routing, alias middleware, middleware global, dan penanganan exception (Laravel 12 tidak memakai `Http/Kernel.php`). |
| `config/navigation.php` | Menu sebagai data (label, route, ikon, `permission`) agar layout tidak berisi daftar menu keras; `@can` hanya penyaring tampilan. |
| `database/migrations` | Urutan: lihat §1.2. `practice_sessions` dan `appointments` memakai `DB::statement` untuk CHECK dan kolom `active_flag` *stored generated*. |
| `lang/id` | Terjemahan Indonesia lengkap; wajib karena skeleton Laravel 12 tidak menyertakan folder `lang` (jalankan `php artisan lang:publish` lalu terjemahkan `validation.php`, termasuk blok `attributes` untuk nama field Indonesia). |
| `public/images/placeholders` | Delapan SVG lokal (00 K). Satu-satunya SVG yang boleh ada; unggahan SVG ditolak. |
| `resources/scss/admin.scss` | Entry CSS khusus admin; mengimpor `_variables`, Bootstrap, `_admin`, `_print`. Tidak dimuat di halaman publik. |
| `routes/console.php` | Jadwal opsional (`Schedule::command(...)`) untuk `appointments:mark-no-show`, `sessions:generate`, `media:prune`. **[ASUMSI]** tidak dibutuhkan fitur inti (00 F.2). |
| `scripts/` | Skrip PowerShell backup/restore (00 K); `APP_KEY` disimpan terpisah. |
| `storage/app/public` | Subfolder per koleksi media: `photo/`, `cover/`, `gallery/`, `image/`, `photos/`, `logo/`, `editor/` lalu `{YYYY}/{MM}/`. |
| `tests/` | Basis data uji: MariaDB `unpam_hospital_test` (`.env.testing`), **bukan SQLite**, karena `active_flag` generated, CHECK, dan `lockForUpdate()` hanya bermakna di MariaDB. |

### 1.2 Urutan migration (dependensi FK)

Daftar berkas migration **kanonik** (29 berkas, awalan `2026_10_02_` + nomor 6 digit, beserta dependensinya) ada di [09-data-dictionary.md](09-data-dictionary.md) bagian 10; bagian ini tidak mengulanginya agar tidak ada dua urutan yang berbeda. Ringkasnya: tiga migration bawaan skeleton (`0001_01_01_000000` users + `password_reset_tokens` + `sessions`, `…000001` cache, `…000002` jobs) dijalankan lebih dulu (tabel `cache` harus ada sebelum migration Spatie karena Spatie menghapus cache izin pada akhir `up()`); disusul penambahan kolom `users`, tabel Spatie, `notifications`, lalu `patient_profiles`, master data (`specialties` → `clinics` → `doctors` → `doctor_specialties`), jadwal (`doctor_schedules`, `schedule_exceptions`, `practice_sessions`), `insurance_partners`, `appointments`, `appointment_status_histories`, tabel fasilitas dan konten, `media`, `website_settings`, `audit_logs`. CHECK dan kolom `active_flag` memakai `DB::statement()`/`storedAs()` pada migration tabelnya (09 bagian 11).

Tipe, default, index, dan aturan hapus tiap kolom: [09-data-dictionary.md](09-data-dictionary.md).

---
## 2. Peta View Blade per Area dan Nomor Halaman

Konvensi: nama file `kebab-case.blade.php`; folder = area/sumber daya jamak; partial lokal diawali `_`; view dirender dengan notasi titik (`view('public.clinics.show')`). Semua halaman membungkus isinya dengan layout komponen (`<x-layouts.public>` dst.) yang menerima `title`, `metaDescription` (opsional), dan slot `breadcrumb`. Kolom **No.** merujuk nomor halaman 1–40 di [00 §I](00-keputusan-inti.md) dan [_kebutuhan-asli.md](_kebutuhan-asli.md); tanda **+** = layar tambahan di luar 40 halaman utama (tidak menambah hitungan halaman).

### 2.1 Publik — `resources/views/public/` (layout `x-layouts.public`)

| No. | Halaman | Route | File view | Controller@method |
|:-:|---|---|---|---|
| 1 | Beranda | `home` | `public/home.blade.php` (+ partial `public/partials/_banner-carousel`, `_quick-actions`, `_featured-doctors`, `_clinic-card`, `_article-card`) | `HomeController@index` |
| 2 | Profil rumah sakit | `about` | `public/about.blade.php` | `PageController@about` |
| 3 | Visi dan misi | `vision-mission` | `public/vision-mission.blade.php` | `PageController@visionMission` |
| 4 | Daftar layanan/poliklinik | `clinics.index` | `public/clinics/index.blade.php` | `ClinicController@index` |
| 5 | Detail layanan/poliklinik | `clinics.show` | `public/clinics/show.blade.php` | `ClinicController@show` |
| 6 | Daftar dokter (filter) | `doctors.index` | `public/doctors/index.blade.php` | `DoctorController@index` |
| 7 | Detail dokter | `doctors.show` | `public/doctors/show.blade.php` | `DoctorController@show` |
| 8 | Jadwal dokter | `schedules.index` | `public/schedules/index.blade.php` | `ScheduleController@index` |
| 9 | Daftar fasilitas | `facilities.index` | `public/facilities/index.blade.php` | `FacilityController@index` |
| 10 | Detail fasilitas | `facilities.show` | `public/facilities/show.blade.php` | `FacilityController@show` |
| 11 | Ringkasan kamar rawat inap | `rooms.index` | `public/rooms/index.blade.php` | `RoomCategoryController@index` |
| 12–15 | Kamar BPJS Kelas 1/2/3 dan VIP | `rooms.show` | `public/rooms/show.blade.php` (satu view untuk keempat slug seed) | `RoomCategoryController@show` |
| 16 | Informasi BPJS dan asuransi | `insurance` | `public/insurance.blade.php` | `PageController@insurance` |
| 17 | Panduan pendaftaran pasien | `registration-guide` | `public/registration-guide.blade.php` | `PageController@registrationGuide` |
| 18 | Daftar artikel kesehatan | `articles.index` | `public/articles/index.blade.php` | `ArticleController@index` |
| 19 | Detail artikel | `articles.show` | `public/articles/show.blade.php` | `ArticleController@show` |
| 20 | Galeri | `galleries.index` | `public/galleries/index.blade.php` | `GalleryController@index` |
| + | Detail album galeri | `galleries.show` | `public/galleries/show.blade.php` | `GalleryController@show` |
| 21 | FAQ | `faq` | `public/faq.blade.php` | `FaqController@index` |
| 22 | Kontak dan lokasi | `contact` (+ `contact.store`) | `public/contact.blade.php` | `ContactController@show` / `store` |
| + | Kebijakan privasi | `privacy` | `public/privacy.blade.php` | `PageController@privacy` |

Partial bersama publik: `public/partials/_header.blade.php` (topbar telepon darurat + WhatsApp, navbar + offcanvas mobile), `_footer.blade.php`, `_page-intro.blade.php` (judul/subjudul dari `pages.title`/`pages.subtitle`).

### 2.2 Autentikasi — `resources/views/auth/` (layout `x-layouts.auth`)

| No. | Halaman | Route | File view | Controller@method |
|:-:|---|---|---|---|
| 23 | Registrasi pasien | `register` / `register.store` | `auth/register.blade.php` | `RegisterController@create` / `store` |
| 24 | Login pasien | `login` / `login.store` | `auth/login.blade.php` | `LoginController@create` / `store` |
| 25 | Permintaan reset password | `password.request` / `password.email` | `auth/forgot-password.blade.php` | `PasswordResetLinkController@create` / `store` |
| 26 | Form reset password | `password.reset` / `password.update` | `auth/reset-password.blade.php` | `NewPasswordController@create` / `store` |
| 31 | Login admin | `admin.login` / `admin.login.store` | `auth/admin-login.blade.php` | `AdminLoginController@create` / `store` |
| + | Ganti password wajib (staf) | `admin.password.edit` / `admin.password.update` | `auth/admin-change-password.blade.php` | `AdminPasswordController@edit` / `update` |

Logout (`logout`, `admin.logout`) tidak punya view (POST lalu redirect). Email reset password memakai notifikasi bawaan Laravel; teksnya diterjemahkan lewat `lang/id.json` (kunci bawaan seperti `Reset Password Notification`, `Reset Password`, `Regards`). Lokal: email masuk log harian `storage/logs/laravel-YYYY-MM-DD.log` (`MAIL_MAILER=log`, `LOG_CHANNEL=daily`; isi pesan berupa quoted-printable, lihat 16 §10.1) atau Mailpit.

### 2.3 Portal pasien — `resources/views/patient/` (layout `x-layouts.patient`)

| No. | Halaman | Route | File view | Controller@method |
|:-:|---|---|---|---|
| 27 | Dashboard pasien | `patient.dashboard` | `patient/dashboard.blade.php` | `DashboardController@index` |
| 28 | Profil & pengaturan akun | `patient.profile.edit` | `patient/profile/edit.blade.php` (dua form: profil, ganti password) | `ProfileController@edit` |
| 29 | Pendaftaran kunjungan — langkah 1–4 | `patient.appointments.create` | `patient/appointments/create.blade.php` + partial `_step-clinic`, `_step-doctor`, `_step-session`, `_step-details` (langkah ditentukan query `poli`, `dokter`, `sesi`) | `AppointmentController@create` |
| 29 | Pendaftaran kunjungan — tinjau & konfirmasi | `patient.appointments.review` (POST) | `patient/appointments/review.blade.php` | `AppointmentController@review` |
| 30 | Riwayat pendaftaran | `patient.appointments.index` (query `jadwal_berubah=1` menyaring appointment yang jadwalnya berubah) | `patient/appointments/index.blade.php` | `AppointmentController@index` |
| 30 | Detail pendaftaran | `patient.appointments.show` | `patient/appointments/show.blade.php` | `AppointmentController@show` |
| 30 | Bukti pendaftaran (cetak) | `patient.appointments.proof` | `patient/appointments/proof.blade.php` (memakai `<x-proof-card>`) | `AppointmentController@proof` |
| 30 | Bukti pendaftaran (PDF) | `patient.appointments.proof-pdf` | `pdf/appointment-proof.blade.php` | `AppointmentController@proofPdf` |

Rute tanpa view: `patient.profile.update`, `patient.password.update`, `patient.appointments.store`, `patient.appointments.cancel`, `patient.notifications.read` (semua redirect dengan flash).

### 2.4 Admin — `resources/views/admin/` (layout `x-layouts.admin`)

Kolom **File** menyebut folder di bawah `admin/`; setiap sumber daya CRUD memakai pola `index`, `create`, `edit`, `_form` (form dipakai bersama create/edit), kecuali dinyatakan lain.

| No. | Area | Route (nama) | File view |
|:-:|---|---|---|
| 32 | Dashboard | `admin.dashboard` | `dashboard.blade.php` (kartu `x-stat-card`, grafik canvas `data-chart`, tabel antrean hari ini bila `appointments.view`) |
| 33 | Dokter | `admin.doctors.index/create/store/show/edit/update` (+ `.status`, `.archive`, `.photo` tanpa view; tidak ada `destroy`) | `doctors/index`, `create`, `edit`, `show` (profil + jadwal + foto), `_form` |
| 34 | Spesialisasi | `admin.specialties.*` | `specialties/index`, `create`, `edit`, `_form` |
| 34 | Poliklinik/layanan | `admin.clinics.*` | `clinics/index`, `create`, `edit`, `_form` (cover, galeri, rich text) |
| 35 | Templat jadwal mingguan | `admin.schedules.*` | `schedules/index`, `create`, `edit`, `_form` |
| 35 | Sesi praktik | `admin.sessions.index/create/store/edit/update/status` (+ `admin.sessions.destroy`, hanya sesi tambahan manual tanpa appointment; 00 I.4 dan 00 C) | `sessions/index` (filter tanggal/dokter/status), `create`, `edit`, `_form` |
| 35 | Sesi terdampak | `admin.sessions.review` / `admin.sessions.resolve` | `sessions/review.blade.php` (daftar + form resolusi per sesi; enam sumber `pending_change`: `exception`, `template_time`, `template_removed`, `manual_time`, `manual_cancel`, `doctor_inactive`, dengan pilihan resolusi menurut sumber, 00 F.4) |
| 35 | Pengecualian/cuti/libur | `admin.exceptions.*` | `exceptions/index`, `create`, `edit`, `_form` |
| 36 | Fasilitas | `admin.facilities.*` | `facilities/index`, `create`, `edit`, `_form` |
| 36 | Kategori kamar & kapasitas | `admin.room-categories.*` (+ `.availability`) | `room-categories/index` (form ketersediaan inline per baris), `create`, `edit`, `_form` |
| 37 | Pasien | `admin.patients.index/create/store/show/edit/update/status/link-account` | `patients/index`, `create`, `edit`, `show` (profil, riwayat booking, form hubungkan akun), `_form` |
| 38 | Pendaftaran | `admin.appointments.index/create/store/show/transition/export` | `appointments/index` (filter + tombol ekspor), `create` (walk-in: cari/buat profil → pilih sesi), `show` (riwayat status + tombol transisi) |
| 38 | Bukti (admin) | `admin.appointments.proof` | `appointments/proof.blade.php` (memakai `<x-proof-card>`) |
| 38 | Antrean | `admin.queue.index` / `admin.queue.mark-no-show` | `queue/index.blade.php` (pencarian kode booking/QR scanner, aksi check-in/selesai per baris) |
| 39 | Halaman statis | `admin.pages.index/edit/update` | `pages/index`, `edit` |
| 39 | Banner | `admin.banners.*` | `banners/index`, `create`, `edit`, `_form` |
| 39 | Artikel | `admin.articles.*` | `articles/index`, `create`, `edit`, `_form` |
| 39 | Kategori artikel | `admin.article-categories.*` | `article-categories/index`, `create`, `edit`, `_form` |
| 39 | Galeri | `admin.galleries.*` (+ `.photos.store`) | `galleries/index`, `create`, `edit` (daftar foto, urut, alt), `_form` |
| 39 | FAQ | `admin.faqs.*` | `faqs/index`, `create`, `edit`, `_form` |
| 39 | Mitra asuransi | `admin.insurance-partners.*` | `insurance-partners/index`, `create`, `edit`, `_form` |
| 40 | Pengaturan website | `admin.settings.edit/update` | `settings/edit.blade.php` (tab: identitas, kontak, booking, seo, privasi) |
| 40 | Akun staf | `admin.staff.*` (+ `.status`, `.reset-password`) | `staff/index`, `create`, `edit`, `_form` |
| 40 | Hak akses peran | `admin.roles.index/edit/update` | `roles/index`, `edit` (matriks izin; peran `super-admin` hanya-baca) |
| 40 | Audit log | `admin.audit-logs.index/show` | `audit-logs/index`, `show` |
| + | Kotak pesan kontak | `admin.messages.index/show/status` | `messages/index`, `show` |

Endpoint media (`admin.media.*`) hanya JSON/redirect — tanpa view. Partial admin bersama: `admin/partials/_sidebar.blade.php` (dibangun dari `config/navigation.php`, disaring `@can`), `_topbar.blade.php` (nama pengguna, peran, logout), `_page-actions.blade.php`.

### 2.5 PDF, galat, paginasi

| Lokasi | File | Catatan |
|---|---|---|
| `resources/views/pdf/` | `appointment-proof.blade.php` | Layout mandiri (tanpa Bootstrap; CSS inline sederhana, font DejaVu Sans), logo dari `public/images/placeholders/logo.svg` atau media logo via path file lokal, QR sebagai `data:image/svg+xml;base64`. |
| `resources/views/errors/` | `layout.blade.php`, `403`, `404`, `419`, `429`, `500`, `503` `.blade.php` | `layout` **tidak membaca database** (memakai `config('app.name')`) agar halaman 500/503 tetap tampil saat DB mati. Teks Indonesia, tombol "Kembali ke Beranda". |
| `resources/views/vendor/pagination/` | `bootstrap-5.blade.php`, `simple-bootstrap-5.blade.php` | Hasil `php artisan vendor:publish --tag=laravel-pagination`, label "Sebelumnya"/"Berikutnya", `aria-label="Navigasi halaman"`. |

### 2.6 Ringkasan cakupan 40 halaman

| Kelompok | Nomor | Jumlah view utama | Jumlah route halaman (GET) |
|---|---|:-:|:-:|
| Publik | 1–22 | 19 file untuk nomor 1–22 (nomor 12–15 berbagi `rooms/show`) + 2 layar tambahan (`galleries/show`, `privacy`) = 21 file | 21 route GET (+ 1 POST `contact.store`) |
| Autentikasi & portal pasien | 23–30 | 4 auth + 7 patient + 1 PDF = 12 file | 11 route GET (4 auth + 7 patient) |
| Admin | 31–40 | 1 auth (+1 ganti password) + dashboard + CRUD §2.4 | lihat 11 §2.3 |

Seluruh 40 halaman memiliki minimal satu view, satu route, satu controller, dan izin/guard yang dipetakan di [11-route-controller-model-service.md](11-route-controller-model-service.md) §2.

---
## 3. Katalog Komponen Blade

Lokasi: `resources/views/components/` (notasi `<x-nama>`; subfolder memakai titik: `<x-form.input>`, `<x-layouts.public>`). Komponen anonim kecuali disebut "kelas" (kelas di `app/View/Components`). Daftar baku berasal dari 00 J.5 (27 komponen, termasuk `wizard-steps` dan `module-tabs`); dua komponen tambahan (`form.rich-text`, `proof-card`) dibutuhkan oleh editor rich text dan bukti pendaftaran dan sudah diterima di 00 J.6. Aturan umum semua komponen:

- Teks UI Bahasa Indonesia; semua `{{ }}` ter-escape; hanya `form.rich-text` (input) dan bagian view yang merender kolom rich text 00 C yang boleh `{!! !!}`.
- Atribut tambahan (`class`, `id`, `data-*`, `aria-*`) diteruskan lewat `$attributes->merge()`.
- Ikon: Bootstrap Icons (font lokal), selalu `aria-hidden="true"` bila dekoratif.
- Status/kondisi tidak pernah disampaikan dengan warna saja (teks + ikon + warna, 00 D/M).

### 3.1 Layout

| Komponen | Props / atribut | Slot | Perilaku | Aksesibilitas |
|---|---|---|---|---|
| `layouts.public` | `title` (string, wajib), `metaDescription` (?string; fallback `seo.default_meta_description`) | default (isi); `breadcrumb` (opsional); `@stack('scripts')` | `<title>{title} — {site.name}</title>`, meta description; header (topbar telepon darurat + tautan WhatsApp `wa.me`, navbar desktop, **offcanvas** mobile), tiga tombol utama "Cari Dokter" (`doctors.index`), "Jadwal Dokter" (`schedules.index`), "Daftar Kunjungan" (`patient.appointments.create`); `<x-flash-alert>`; footer (alamat, kontak, jam operasional dari `website_settings`); `@vite(['resources/scss/app.scss','resources/js/app.js'])`. Menu menandai halaman aktif dengan `request()->routeIs()`. | Skip link "Lewati ke konten utama" sebagai elemen fokus pertama; landmark `header/nav/main#main-content/footer`; `aria-current="page"` pada menu aktif; tautan WhatsApp diberi label "Hubungi via WhatsApp (dibuka di aplikasi WhatsApp, bukan pesan otomatis)". |
| `layouts.patient` | `title` | default; `breadcrumb` | Navbar portal (Dasbor, Daftar Kunjungan, Riwayat, Profil, Keluar = form POST `logout`); lencana jumlah notifikasi belum dibaca; `<meta name="robots" content="noindex,nofollow">`; memuat `app.scss`/`app.js`. | Sama dengan publik; tombol Keluar berupa `<button>` dalam form (bukan tautan). |
| `layouts.admin` | `title` | default; `breadcrumb`; `actions` (tombol di kanan judul halaman) | Sidebar dari `config/navigation.php` disaring `@can` (hanya kenyamanan; penegakan di server), topbar (nama, peran, Keluar `admin.logout`), sidebar menjadi offcanvas di < 992 px; `noindex`; `@vite(['resources/js/admin.js'])` (admin.js mengimpor `admin.scss`). | Sidebar `<nav aria-label="Menu admin">`; tombol buka menu mobile `aria-controls`/`aria-expanded`; fokus terlihat. |
| `layouts.auth` | `title`, `subtitle` (?string) | default (form); `footer` (tautan bantuan) | Kartu tengah dengan logo, tautan "Kembali ke beranda"; dipakai login/registrasi/reset pasien dan login/ganti password admin; `noindex`. | Satu `<h1>` (judul formulir); `<x-form.errors-summary>` selalu di atas form. |

### 3.2 Navigasi dan kepala halaman

| Komponen | Props / atribut | Slot | Perilaku | Aksesibilitas |
|---|---|---|---|---|
| `breadcrumb` | `items` (array `[['label'=>'Dokter','url'=>route('doctors.index')], ['label'=>'dr. Andi']]`; item terakhir tanpa `url` = halaman kini), `home` (nama route awal; default `home`, admin `admin.dashboard`) | — | Merender item awal otomatis ("Beranda" / "Dasbor") lalu `items`. | `<nav aria-label="Breadcrumb"><ol class="breadcrumb">`; item terakhir `aria-current="page"`. |
| `page-header` | `title` (wajib), `subtitle` (?), `icon` (?) | default (info tambahan); `actions` | Satu-satunya `<h1>` halaman; subjudul dari `pages.subtitle` untuk halaman publik. | Hierarki heading dijaga (konten memakai `h2`+). |
| `wizard-steps` | `steps` (array label berurutan, mis. `['Poliklinik','Dokter','Tanggal & Sesi','Data Pendaftaran','Konfirmasi']`), `current` (int 1-based) | — | Indikator langkah wizard pendaftaran pasien (`patient.appointments.create`/`review`) dan walk-in admin (`Pasien → Sesi → Pembayaran`). Langkah selesai diberi ikon centang, langkah aktif ditebalkan; langkah sebelumnya berupa tautan ke langkah itu (mempertahankan query `poli`, `dokter`, `sesi`). Di < 576 px diringkas menjadi teks "Langkah 2 dari 5: Dokter". | `<ol>` bernomor; langkah aktif `aria-current="step"`; status "selesai" dibaca lewat teks tersembunyi (bukan warna saja). |
| `module-tabs` | `group` (kunci grup di `config/navigation.php` → `modules`, mis. `schedules`, `clinics`, `facilities`, `content`, `account`, `appointments`) | — | Tab sub-modul admin: `schedules` = Templat (`admin.schedules.index`) · Sesi (`admin.sessions.index`) · Pengecualian (`admin.exceptions.index`) · Sesi terdampak (`admin.sessions.review`, lencana jumlah sesi `needs_review`, hanya tampil bagi pemegang `sessions.review`). Tab difilter `@can` dan aktif menurut `request()->routeIs()`. | `<nav aria-label="Submodul">` berisi tautan bergaya `nav-tabs` (bukan peran ARIA `tab`, karena berpindah halaman); `aria-current="page"` pada tab aktif; lencana punya teks tersembunyi "N sesi perlu tindak lanjut". |

### 3.3 Komponen domain

| Komponen | Props / atribut | Slot | Perilaku | Aksesibilitas |
|---|---|---|---|---|
| `doctor-card` | `doctor` (`Doctor` dengan `clinic`, `specialties`, `media` sudah di-eager-load), `nextSession` (?`SessionAvailability`), `headingLevel` (default 3) | `footer` (opsional) | Foto via `<x-image type="doctor">`, `displayName`, badge spesialisasi, nama poliklinik; tombol "Lihat Jadwal" → `doctors.show`; bila `nextSession` ada: `availability-badge` + tombol "Daftar" → `patient.appointments.create?sesi={id}` (hanya bila `isBookableOnline`). | `<article>` dengan heading `h{n}`; `alt` foto = nama dokter; tombol bertulis jelas (bukan "klik di sini"); tidak ada tautan bersarang. |
| `schedule-table` | `sessions` (Collection `SessionAvailability`), `showDoctor` (bool, default true), `showAction` (bool, default true), `caption` (string) | — | Tabel jadwal: hari/tanggal, jam (`start–end`), poliklinik, dokter, sisa kuota, `availability-badge`, tombol "Daftar" (hanya `isBookableOnline`; selain itu teks alasan dari label `Availability`). Kosong → `<x-empty-state>`. Di < 768 px baris menjadi kartu (CSS `.table-cards`, `data-label` pada `<td>`). Sumber data **sama** dengan wizard booking (`DoctorAvailabilityQuery`). | `<caption class="visually-hidden">`; `<th scope="col">` dan `<th scope="row">`; label mobile via `data-label` tetap dibaca pembaca layar. |
| `proof-card` | `appointment` (`Appointment` dengan `patientProfile`, `doctor`, `clinic`, `practiceSession`), `qrSvg` (string SVG QR), `printedAt` (Carbon) | — | Isi bukti sesuai 00 F.10: kode booking + QR, nama & no. pasien, poliklinik, dokter, tanggal & jam, nomor antrean (`queueLabel`), jenis pembayaran (nomor BPJS/polis 4 digit terakhir), status, instruksi check-in, waktu cetak. Dipakai halaman cetak pasien dan admin; PDF memakai view terpisah `pdf/appointment-proof`. | QR `role="img"` + `aria-label="QR code kode booking UPH-…"`; kode booking juga ditulis sebagai teks; `_print.scss` menyembunyikan navigasi. |

### 3.4 Status dan umpan balik

| Komponen | Props / atribut | Slot | Perilaku | Aksesibilitas |
|---|---|---|---|---|
| `status-badge` (kelas `StatusBadge`) | `status` (enum apa pun yang punya `label()`, `color()`, `icon()` — mis. `AppointmentStatus`, `SessionStatus`, `DoctorStatus`, `ArticleStatus`, `ContactMessageStatus`, `ExceptionType`, `PaymentType`) | — | `<span class="badge text-bg-{color}"><i class="bi {icon}" aria-hidden="true"></i> {label}</span>`. | Teks + ikon + warna; kontras dijamin kelas `text-bg-*` Bootstrap 5.3. |
| `availability-badge` (kelas `AvailabilityBadge`) | `availability` (`Availability`), `remaining` (?int), `compact` (bool) | — | Seperti `status-badge`; bila `available` dan `remaining` ada → "Tersedia · sisa {n}". | Sama; sisa kuota ditulis sebagai teks. |
| `empty-state` | `title`, `message` (?), `icon` (default `bi-inbox`) | default (tombol aksi) | Kondisi data kosong dengan ajakan tindakan (mis. "Hapus filter", "Tambah dokter"). | `role="status"`; ikon dekoratif. |
| `flash-alert` | `keys` (default `['success','error','warning']`) | — | Membaca flash session `success` (hijau), `error` (merah, `alert-danger`), `warning` (kuning); dismissible. Dipanggil sekali per layout. | Error/warning `role="alert"`, success `role="status"`; tombol tutup berlabel "Tutup". |
| `loading-button` | `type` (default `submit`), `variant` (default `primary`), `loadingText` (default "Memproses…"), `icon` (?) | default (label) | Tombol dengan spinner tersembunyi; `app.js` pada event `submit` menyetel `aria-busy="true"`, menonaktifkan tombol, menampilkan spinner + `loadingText`; dipulihkan pada `pageshow` (tombol Back). Tanpa JS tetap tombol submit biasa; kirim ganda tetap dicegah server (00 F.6). | Spinner `role="status"` dengan teks tersembunyi; tombol nonaktif memakai atribut `disabled`. |
| `image` (kelas `Image`) | `media` (?`Media`), `src` (?string), `alt` (string; wajib kecuali `decorative`), `type` (`doctor`\|`clinic`\|`facility`\|`room`\|`article`\|`banner`\|`gallery`\|`logo`), `variant` (`full`\|`thumb`), `ratio` (mis. `4x3`, `1x1`), `lazy` (default true), `decorative` (bool) | — | URL dari media; bila `media` NULL → `public/images/placeholders/{type}.svg`; untuk file fisik hilang dipasang atribut `data-fallback="{url placeholder}"` dan **satu listener terdelegasi** (`document.addEventListener('error', …, true)`) di `resources/js/app.js` (00 K; dimuat juga oleh `admin.js` karena `admin.js` mengimpor `app.js`) yang mengganti `src` satu kali (tanpa atribut `onerror` inline agar kompatibel dengan CSP produksi `script-src 'self'`; gambar yang sudah gagal sebelum JS aktif dicek ulang lewat `img.complete && img.naturalWidth === 0` saat `DOMContentLoaded`); `loading="lazy" decoding="async"`, atribut `width`/`height` dari metadata (mencegah layout shift). | `alt` selalu terisi (default `media.alt_text`); `decorative` → `alt="" role="presentation"`. |
| `stat-card` | `label`, `value`, `icon`, `variant` (`primary`\|`success`\|`info`\|`warning`), `hint` (?), `href` (?) | — | Kartu angka dashboard; bila `href` ada seluruh label menjadi tautan. | Nilai di `<p>` dengan `aria-labelledby` ke label; angka tidak bergantung warna. |
| `map-embed` (kelas `MapEmbed`) | `height` (default 360), `title` (default "Peta lokasi UNPAM HOSPITAL") | — | Selalu menampilkan `<address>` (`contact.address`) dan tombol **"Petunjuk Arah"** (`map.directions_url`, `target="_blank" rel="noopener noreferrer"`). Iframe `map.embed_url` (`loading="lazy"`) ditambahkan; `app.js` menyembunyikannya dan menampilkan teks cadangan bila `navigator.onLine === false` atau iframe tidak `load` dalam 8 detik. Tetap berfungsi tanpa internet. | Iframe punya `title`; teks cadangan `role="status"`; tombol bertulis jelas. |

### 3.5 Form

Semua komponen form: `name` (wajib), `label` (wajib), `id` (default dari `name`), `help` (?string), `required` (bool). Nilai default = `old(name)` lalu nilai model (`value`). Galat dari `$errors->first(name)` ditampilkan di `<div id="{id}-error" class="invalid-feedback d-block">`; kontrol diberi `is-invalid`, `aria-invalid="true"`, dan `aria-describedby="{id}-help {id}-error"`. Tanda wajib: `*` + `aria-required="true"` (bukan hanya warna).

| Komponen | Props khusus | Slot | Perilaku | Aksesibilitas |
|---|---|---|---|---|
| `form.input` | `type` (default `text`), `value`, `autocomplete`, `inputmode`, `maxlength`, `placeholder` | `prepend`/`append` (opsional) | `<input class="form-control">`; password tidak pernah diisi ulang (`old()` diabaikan untuk `type=password`). | `<label for>` selalu ada; `autocomplete` tepat (`email`, `tel`, `new-password`, `current-password`). |
| `form.select` | `options` (array `value=>label` atau Collection), `selected`, `placeholder` (?), `multiple` (bool) | — | Mendukung enum via `Enum::options()` (`HasOptions`). | Opsi placeholder `disabled` + `value=""`. |
| `form.textarea` | `rows` (default 4), `maxlength`, `value` | — | Penghitung karakter `x / maxlength` (JS; tanpa JS tetap divalidasi server). | Penghitung `aria-live="polite"`, tidak mengumumkan tiap ketikan (hanya saat mendekati batas). |
| `form.checkbox` | `value` (default 1), `checked`, `withHidden` (bool; menyertakan `<input type=hidden value=0>` untuk boolean) | — | `form-check` dengan label klik. | Label terhubung `for`. |
| `form.radio-group` | `legend`, `options`, `selected`, `inline` (bool) | — | `<fieldset><legend>`; dipakai untuk jenis pembayaran (`PaymentType`). | Grup berlabel `legend`; galat dihubungkan ke fieldset (`aria-describedby`). |
| `form.file` | `accept` (default `image/jpeg,image/png,image/webp`), `maxKb` (default 2048), `multiple` (bool), `currentMedia` (?`Media`), `altName` (default `{name}_alt`), `altValue` | — | Input file + pratinjau (`image-preview.js`, `FileReader.readAsDataURL`) + kolom **alt text wajib** (`form.input`) bila ada file/`currentMedia`; menampilkan batas ukuran/dimensi sebagai teks bantuan. Validasi final di server (00 K). | Pratinjau punya `alt` "Pratinjau gambar yang dipilih"; kolom alt text diberi petunjuk cara menulis. |
| `form.rich-text` *(baru)* | `value` (HTML tersanitasi dari DB), `minHeight` (default 240) | — | Textarea tersembunyi + kontainer Quill 2 (`editor.js`, dimuat lazy). Toolbar dibatasi pada tag yang diizinkan purifier: judul (h2–h4), tebal, miring, garis bawah, daftar bernomor/berpoin, tautan, kutipan, gambar, bersihkan format. Saat submit `quill.getSemanticHTML()` ditulis ke textarea (agar daftar menjadi `<ul>/<ol>` semantik, bukan `data-list`). Unggah gambar → `POST admin.media.editor-upload` lalu menyisipkan `<img src="/storage/…" alt="…">` dengan alt wajib. Tanpa JS: textarea biasa tampil. | Toolbar `role="toolbar"` berlabel; editor `aria-labelledby` ke label; tombol toolbar dapat difokus keyboard (bawaan Quill). |
| `form.errors-summary` | `title` (default "Periksa kembali isian Anda") | — | Muncul bila `$errors->any()`: daftar galat berupa tautan `#id-field`; `app.js` memindahkan fokus ke ringkasan saat halaman dimuat. | `role="alert"`, `tabindex="-1"`; tautan melompat ke field bermasalah. |

### 3.6 Data dan dialog

| Komponen | Props / atribut | Slot | Perilaku | Aksesibilitas |
|---|---|---|---|---|
| `data-table` | `rows` (`LengthAwarePaginator`), `searchName` (default `q`), `searchPlaceholder`, `caption` (string), `emptyTitle`, `emptyMessage`, `searchable` (bool, default true) | `filters` (kontrol filter GET); `actions` (toolbar kanan); `head` (`<th>`); default (`<tr>` isi — tiap `<td>` memakai `data-label`); `empty` (opsional) | Form pencarian GET (`q`) + slot filter; tabel Bootstrap responsif; `{{ $rows->withQueryString()->links() }}` (view pagination Bootstrap 5 Indonesia); `rows->isEmpty()` → `<x-empty-state>` (+ tombol "Hapus filter" bila ada query). Di < 768 px tabel menjadi kartu. Saat form disubmit `aria-busy="true"` + tabel redup (state loading). | `<form role="search" aria-label="Cari dan saring data">`; `<caption>` visually-hidden; ringkasan "Menampilkan x–y dari z" `aria-live="polite"`. |
| `confirm-modal` | `id`, `title`, `message`, `confirmLabel` (default "Ya, lanjutkan"), `cancelLabel` (default "Batal"), `variant` (`danger`\|`primary`), `method` (`POST`\|`PATCH`\|`DELETE`), `reasonRequired` (bool; menambah textarea `reason` wajib untuk pembatalan/koreksi), `action` (URL; dapat ditimpa tombol pemicu via `data-confirm-action`/`data-confirm-message` agar satu modal melayani banyak baris) | default (isi tambahan) | Modal Bootstrap; berisi `<form>` dengan `@csrf` + `@method`. Aksi destruktif juga tersedia sebagai form biasa di halaman detail/ubah sehingga tidak hanya bergantung JS. | Bootstrap modal native: fokus terjebak, `Esc` menutup, fokus kembali ke pemicu; `aria-labelledby` ke judul; tombol berbahaya berwarna **dan** bertulis jelas. |

### 3.7 Catatan perilaku lintas komponen

- **Empat kondisi wajib** (00 M): *loading* (`loading-button`, `aria-busy` pada `data-table`), *kosong* (`empty-state`), *berhasil* (`flash-alert` hijau), *gagal* (`flash-alert` merah + `form.errors-summary`).
- **Tampilan mobile:** `data-table`/`schedule-table` berubah menjadi kartu di < 768 px; navigasi menjadi offcanvas; tombol minimal 44 px tinggi sentuh.
- **Tanpa internet:** tidak ada komponen yang memuat CDN, font, atau ikon eksternal; satu-satunya sumber eksternal adalah iframe `map-embed` dan itu opsional.

---
## 4. Enum (`App\Enums`) dan Method Helper

Semua enum adalah PHP 8.2 *backed enum* bertipe `string` (nilai = kode di 00 D, tersimpan `VARCHAR` di DB). Nama *case* PascalCase, nilai snake_case. Setiap enum memakai trait `App\Enums\Concerns\HasOptions` yang menyediakan:

```php
trait HasOptions {
    public static function values(): array;   // ['pending','confirmed',...]  → untuk rule Rule::enum / whereIn
    public static function options(): array;  // ['pending' => 'Menunggu konfirmasi', ...] → untuk <x-form.select>
}
```

Method wajib per enum: `label(): string` (teks UI Indonesia). Enum yang tampil sebagai badge (`<x-status-badge>`) juga punya `color(): string` (nama warna kontekstual Bootstrap → kelas `text-bg-{color}`) dan `icon(): string` (kelas Bootstrap Icons). Label/warna/ikon didefinisikan dengan `match($this)` di dalam enum (satu tempat, tanpa file terjemahan terpisah). Kolom **Label** di bawah = persis teks 00 D.

### 4.1 `AppointmentStatus` (00 D, F.8)

| Case | Nilai | Label | `color()` | `icon()` |
|---|---|---|---|---|
| `Pending` | `pending` | Menunggu konfirmasi | `warning` | `bi-hourglass-split` |
| `Confirmed` | `confirmed` | Dikonfirmasi | `primary` | `bi-check-circle` |
| `CheckedIn` | `checked_in` | Hadir/check-in | `info` | `bi-person-check` |
| `Completed` | `completed` | Selesai | `success` | `bi-check2-all` |
| `Cancelled` | `cancelled` | Dibatalkan | `secondary` | `bi-x-circle` |
| `NoShow` | `no_show` | Tidak hadir | `danger` | `bi-person-x` |

```php
public const ACTIVE          = [self::Pending, self::Confirmed, self::CheckedIn];
public const QUOTA_CONSUMING = [self::Pending, self::Confirmed, self::CheckedIn, self::Completed, self::NoShow]; // semua kecuali Cancelled
public const FINAL           = [self::Completed, self::Cancelled, self::NoShow];

public static function active(): array;          // = ACTIVE
public static function quotaConsuming(): array;  // = QUOTA_CONSUMING
public static function finals(): array;          // = FINAL
public function isActive(): bool;                // in ACTIVE
public function consumesQuota(): bool;           // in QUOTA_CONSUMING
public function isFinal(): bool;                 // in FINAL
```
`Appointment::scopeActive()` memakai `AppointmentStatus::active()`; kolom generated `active_flag` di DB mengulang himpunan `ACTIVE` dengan SQL (sinkronisasi diuji di 15: perubahan `ACTIVE` wajib disertai migration baru).

### 4.2 Enum berstatus/badge lainnya

| Enum | Case → nilai → label | `color()` per case | `icon()` per case | Method tambahan |
|---|---|---|---|---|
| `PaymentType` | `Umum`→`umum`→Umum/Pribadi · `Bpjs`→`bpjs`→BPJS Kesehatan · `Asuransi`→`asuransi`→Asuransi | secondary · success · primary | `bi-wallet2` · `bi-shield-plus` · `bi-shield-check` | `requiresBpjsNumber(): bool` (hanya `Bpjs`); `requiresInsurancePartner(): bool` (hanya `Asuransi`); `allowsPolicyNumber(): bool` (`Asuransi`) |
| `BookingChannel` | `Online`→`online`→Online · `Loket`→`loket`→Loket | info · secondary | `bi-globe2` · `bi-building` | — |
| `SessionStatus` | `Open`→`open`→Dibuka · `Closed`→`closed`→Ditutup · `NeedsReview`→`needs_review`→Perlu tindak lanjut · `Cancelled`→`cancelled`→Dibatalkan | success · secondary · warning · danger | `bi-door-open` · `bi-door-closed` · `bi-exclamation-triangle` · `bi-x-octagon` | `isOpen(): bool`; `blocksNewBookings(): bool` (semua selain `Open`) |
| `ExceptionType` | `DoctorLeave`→`doctor_leave`→Cuti dokter · `HospitalHoliday`→`hospital_holiday`→Libur rumah sakit · `NationalHoliday`→`national_holiday`→Libur nasional · `Other`→`other`→Lainnya | warning · danger · info · secondary | `bi-person-dash` · `bi-calendar-x` · `bi-flag` · `bi-three-dots` | `requiresDoctor(): bool` (hanya `DoctorLeave`) dan `forbidsDoctor(): bool` (`HospitalHoliday`, `NationalHoliday`; `Other` bebas) — kombinasi wajib 00 E.2, juga ditegakkan CHECK `chk_schedule_exceptions_scope` (09) |
| `DoctorStatus` | `Active`→`active`→Aktif · `Inactive`→`inactive`→Nonaktif · `Archived`→`archived`→Diarsipkan | success · secondary · dark | `bi-check-circle` · `bi-pause-circle` · `bi-archive` | `isActive(): bool` |
| `ArticleStatus` | `Draft`→`draft`→Draf · `Published`→`published`→Terbit | secondary · success | `bi-pencil` · `bi-globe` | — |
| `ActorType` | `Patient`→`patient`→Pasien · `Staff`→`staff`→Staf · `System`→`system`→Sistem | info · primary · secondary | `bi-person` · `bi-person-badge` · `bi-gear` | `static fromUser(User $u): self` (menurut `account_type`) |
| `ContactMessageStatus` | `New`→`new`→Baru · `Read`→`read`→Dibaca · `Replied`→`replied`→Dibalas · `Archived`→`archived`→Diarsipkan | primary · secondary · success · dark | `bi-envelope` · `bi-envelope-open` · `bi-reply` · `bi-archive` | — |
| `SessionResolution` | `Keep`→`keep`→Pertahankan sesi lama · `Apply`→`apply`→Terapkan perubahan jam · `Cancel`→`cancel`→Batalkan sesi | secondary · primary · danger | `bi-arrow-counterclockwise` · `bi-clock-history` · `bi-x-circle` | `static allowedFor(string $source): array` (peta statis 00 F.4) — `exception`→[`Keep`,`Cancel`]; `template_time`→[`Keep`,`Apply`,`Cancel`]; `template_removed`→[`Keep`,`Cancel`]; `manual_time`→[`Keep`,`Apply`,`Cancel`]; `manual_cancel`→[`Keep`,`Cancel`]; `doctor_inactive`→[`Keep`,`Cancel`] (`Keep` hanya bila dokter sudah aktif kembali, selain itu wajib `Cancel` — disaring `ScheduleChangeService::allowedResolutions()` yang diterima di 00 J.6; sesi lampau hanya boleh `Keep`, 12 BR-96); `requiresReason(): bool` (hanya `Cancel`); `description(): string` (penjelasan di layar resolusi) |
| `Availability` (tidak disimpan di DB) | `Available`→`available`→Tersedia · `Full`→`full`→Penuh · `BookingClosed`→`booking_closed`→Pendaftaran online ditutup · `Closed`→`closed`→Ditutup · `Leave`→`leave`→Cuti · `Holiday`→`holiday`→Libur · `Cancelled`→`cancelled`→Dibatalkan · `UnderReview`→`under_review`→Sedang diperbarui · `Past`→`past`→Sudah lewat | success · danger · secondary · secondary · warning · info · secondary · warning · secondary | `bi-check-circle` · `bi-people-fill` · `bi-lock` · `bi-slash-circle` · `bi-person-dash` · `bi-calendar-event` · `bi-x-circle` · `bi-arrow-repeat` · `bi-clock-history` | `isBookable(): bool` (hanya `Available`); `static forException(?ExceptionType $t): self` — `DoctorLeave`→`Leave`, `HospitalHoliday`/`NationalHoliday`→`Holiday`, `Other`/`null`→`Cancelled` (00 F.3: "selain itu `cancelled`") |

### 4.3 Enum label-saja (tanpa badge)

| Enum | Case → nilai → label | `icon()` (opsional) |
|---|---|---|
| `AccountType` | `Patient`→`patient`→Pasien · `Staff`→`staff`→Staf | `bi-person` · `bi-person-badge`; `isStaff(): bool`, `isPatient(): bool` |
| `Gender` | `Male`→`L`→Laki-laki · `Female`→`P`→Perempuan | `bi-gender-male` · `bi-gender-female` |
| `ClinicType` | `Poliklinik`→`poliklinik`→Poliklinik · `Penunjang`→`penunjang`→Layanan penunjang | `bi-hospital` · `bi-clipboard2-pulse` |
| `FacilityCategory` | `Medis`→`medis`→Fasilitas medis · `Penunjang`→`penunjang`→Penunjang · `Umum`→`umum`→Fasilitas umum | `bi-heart-pulse` · `bi-tools` · `bi-building` |

### 4.4 Pemakaian di kode

- **Cast Eloquent:** `'status' => AppointmentStatus::class` (dst. untuk semua kolom enum di 00 E).
- **Validasi:** `Rule::enum(PaymentType::class)`; opsi form `PaymentType::options()`.
- **Query:** `whereIn('status', AppointmentStatus::active())` (Eloquent mengonversi enum ke nilai string).
- **Tampilan:** `<x-status-badge :status="$appointment->status" />`; `AppointmentStatus` tidak pernah dirender sebagai kode mentah.

---

## 5. Konvensi Penamaan

| Artefak | Pola | Contoh |
|---|---|---|
| Controller | `{Entitas}Controller` (singular), di namespace area; method resource standar (`index`, `create`, `store`, `show`, `edit`, `update`, `destroy`) + verba camelCase untuk aksi khusus | `Admin\DoctorController@archive`, `Patient\AppointmentController@proofPdf` |
| Form Request | `{Aksi}{Entitas}Request` (`Store`, `Update`, atau aksi khusus); filter daftar: `{Entitas}IndexRequest` | `Admin\StoreDoctorRequest`, `Patient\CancelAppointmentRequest` |
| Policy | `{Model}Policy`; method = nama aksi Laravel (`viewAny`, `view`, `create`, `update`, `delete`) + aksi domain (`archive`, `cancel`, `transition`, `resolve`) | `AppointmentPolicy@transition` |
| Service | `{Peran}Service` / kata benda peran (`Generator`, `Formatter`, `Query`, `Exporter`, `Linker`); method berupa kata kerja | `BookingService::book()` |
| Model | Singular PascalCase = tabel plural snake_case | `PracticeSession` → `practice_sessions` |
| Enum | Nama tunggal PascalCase di `App\Enums`; case PascalCase | `AppointmentStatus::NoShow` |
| Migration | `{timestamp}_create_{tabel}_table`, `{timestamp}_add_{kolom}_to_{tabel}_table` | `create_practice_sessions_table` |
| Route (nama) | Persis 00 §I: huruf kecil, titik, kebab-case untuk kata majemuk | `patient.appointments.proof-pdf` |
| Route (URL) | Bahasa Indonesia, kebab-case; segmen aksi resource memakai `Route::resourceVerbs(['create'=>'buat','edit'=>'ubah'])` **[ASUMSI]** | `/admin/dokter/buat`, `/admin/dokter/{doctor}/ubah` |
| Parameter route | Nama parameter = nama model camelCase (`{doctor}`, `{roomCategory}`, `{session}`) lewat `->parameters([...])` karena segmen URL Indonesia | `/admin/kamar/{roomCategory}/ketersediaan` |
| View | `kebab-case.blade.php`; folder = area/sumber daya jamak; partial lokal `_nama` | `admin/room-categories/_form.blade.php` |
| Komponen Blade | `kebab-case`, subfolder untuk grup (`form.input`); kelas `PascalCase` | `<x-status-badge>` ↔ `StatusBadge` |
| Izin Spatie | `{sumber-daya}.{aksi}` persis 00 G | `appointments.manage` |
| Kunci pengaturan | `{grup}.{nama_snake}` persis 00 E.7 | `booking.online_cutoff_minutes` |
| Aksi audit | `{entitas_snake}.{peristiwa}` | `appointment.status_changed` |
| Flash | kunci `success`, `error`, `warning` (11 §11) | `->with('success', '…')` |
| Test | `{Kelas}Test`; method memakai atribut PHPUnit 11 `#[Test]` dengan nama deskriptif Inggris snake_case | `#[Test] public function patient_cannot_open_admin_dashboard()` |
| Direktori test | `Feature/{Area}/…` mencerminkan area controller; `Unit/` untuk enum, service murni, model | `tests/Feature/Booking/BookingServiceTest.php` |
| Identifier | Inggris untuk kode; Indonesia untuk URL dan teks UI (00 C) | — |

---

## 6. Vite, Aset, dan Pemisahan Admin dari Publik

### 6.1 Entry Vite

```js
// vite.config.js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
  plugins: [
    laravel({
      input: [
        'resources/scss/app.scss',   // CSS publik + pasien + auth
        'resources/js/app.js',       // JS publik + pasien + auth
        'resources/js/admin.js',     // JS admin (mengimpor resources/scss/admin.scss → CSS admin)
      ],
      refresh: true,
    }),
  ],
  css: { preprocessorOptions: { scss: {
    quietDeps: true,
    silenceDeprecations: ['import', 'mixed-decls', 'color-functions', 'global-builtin'], // Bootstrap 5.3 masih memakai @import
  } } },
});
```

`tailwindcss` dan `@tailwindcss/vite` bawaan skeleton **dihapus** (00 B.1); tidak ada `resources/css/app.css`.

### 6.2 Pemisahan aset

| Aspek | Publik/pasien/auth | Admin |
|---|---|---|
| Layout | `layouts.public`, `layouts.patient`, `layouts.auth` | `layouts.admin` |
| Direktif | `@vite(['resources/scss/app.scss','resources/js/app.js'])` | `@vite(['resources/js/admin.js'])` |
| SCSS | `app.scss` = `_variables` → Bootstrap → `_public` → `_print` | `admin.scss` = `_variables` → Bootstrap → `_admin` → `_print` (diimpor oleh `admin.js`) |
| JS | Bootstrap (`import 'bootstrap'`), font Plus Jakarta Sans (400/500/600/700), perilaku bersama `loading-button`, `errors-summary`, fallback gambar, `map-embed`; impor dinamis `booking.js` bila ada `[data-booking-wizard]` dan `image-preview.js` bila ada `[data-image-input]` | `admin.js` **mengimpor `app.js`** (perilaku bersama di atas, termasuk satu-satunya listener fallback gambar, 00 K) lalu menambah `confirm-modal`, offcanvas sidebar; impor dinamis `editor.js` (`[data-rich-text]`), `charts.js` (`[data-chart]`) |
| Dampak | Chart.js dan Quill (± ratusan KB) **tidak pernah** diunduh pengunjung publik | Tidak memuat gaya situs publik |

Pola impor dinamis (hemat unduhan, tanpa entry tambahan):

```js
// resources/js/admin.js (potongan)
import './app.js';                  // Bootstrap, font Plus Jakarta Sans, loading-button, errors-summary, fallback gambar, map-embed
import '../scss/admin.scss';        // CSS admin (app.scss TIDAK dimuat di layout admin)
if (document.querySelector('[data-rich-text]')) import('./editor.js');
if (document.querySelector('[data-chart]'))     import('./charts.js');
if (document.querySelector('[data-image-input]')) import('./image-preview.js');
```

### 6.3 Peran file JS

| File | Tanggung jawab |
|---|---|
| `app.js` | Inisialisasi Bootstrap; `loading-button` (submit → `aria-busy`, nonaktifkan, spinner; pulihkan pada `pageshow`); fokus ke `form.errors-summary`; fallback gambar `x-image` (`img[data-fallback]`); `map-embed` fallback (offline/timeout 8 dtk); tahun footer dinamis tidak diperlukan. |
| `admin.js` | Mengimpor `app.js` (perilaku bersama) + `admin.scss`, offcanvas sidebar, `confirm-modal` (mengisi `action`/pesan dari `data-confirm-*`), pencarian/filter `data-table` (indikator loading). |
| `booking.js` | Wizard: tampilkan/sembunyikan field nomor BPJS / mitra asuransi / nomor polis sesuai `payment_type`; pencegah kirim ganda tambahan; **tidak** mengubah data kuota (server yang menentukan). Wizard tetap berfungsi penuh tanpa file ini (00 I.3). |
| `editor.js` | Quill 2 untuk `form.rich-text`: toolbar terbatas, unggah gambar ke `admin.media.editor-upload`, tulis `getSemanticHTML()` ke textarea saat submit, wajibkan alt text gambar. |
| `charts.js` | Chart.js 4: ambil data JSON dari `admin.dashboard.chart` (filter 7/30/90 hari/rentang kustom), render grafik batang/garis `canvas[data-chart]` dengan `<table class="visually-hidden">` pendamping sebagai alternatif teks. |
| `image-preview.js` | Pratinjau gambar lokal (`FileReader.readAsDataURL`; CSP produksi `img-src 'self' data:` tidak mengizinkan `blob:`, Task 9) dan peringatan ukuran >2 MB (saran saja; validasi final server). |

### 6.4 Skrip dan build

| Perintah | Fungsi |
|---|---|
| `npm install` | Pasang dependensi (tanpa Tailwind). |
| `npm run build` | Build produksi ke `public/build` (dipakai di XAMPP/Apache; tidak perlu dev server). |
| `npm run dev` | Dev server Vite (HMR) saat mengembangkan; CSP dinonaktifkan di lokal agar HMR jalan (00 B.2). |
| `composer dev` | `npx concurrently "php artisan serve" "npm run dev"` — **tanpa** `pail` (butuh `pcntl`, tidak ada di Windows; 00 B.1). Jalankan `artisan` memakai `C:\xampp3\php\php.exe` (di mesin pengembang ini XAMPP terpasang di `C:\xampp3`; di mesin lain bisa `C:\xampp`). |

### 6.5 Struktur SCSS dan token

```scss
// resources/scss/_variables.scss  (token 00 M)
$primary:          #0A4D8C;   // biru: navbar, tombol utama, judul (±8,6:1)
$teal-dark:        #0E7C74;   // aksen toska tua (±5,0:1)
$secondary-accent: $teal-dark;
$teal-light:       #13A89E;   // dekorasi saja — BUKAN latar teks putih (±3,0:1)
$success:          #1E7D45;
$body-bg:          #FFFFFF;
$light:            #F3F7FB;
$font-family-sans-serif: 'Plus Jakarta Sans', system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
$font-size-base:   1rem;      // 16 px
$line-height-base: 1.6;
$link-color:       $primary;

// Warna tema tambahan "accent" → .btn-accent, .text-bg-accent, .bg-accent (tombol sekunder & badge aksen)
$custom-theme-colors: ('accent': $secondary-accent);  // di-merge ke $theme-colors setelah functions Bootstrap
```
`$secondary` Bootstrap **dibiarkan abu-abu** (`#6c757d`, kontras ±4,7:1) karena 00 D memetakan status `cancelled` ke `secondary`; warna toska tua dikeluarkan sebagai tema `accent` agar makna "dibatalkan" tidak berubah. **[ASUMSI]**

---
## 7. Konfigurasi Penting

Nilai lingkungan lokal vs produksi (`APP_ENV`, `DB_*`, `SESSION_*`, `QUEUE_CONNECTION`, `MAIL_*`) mengikuti [00 §B.2](00-keputusan-inti.md); langkah pengisian `.env` ada di [16-instalasi-operasional.md](16-instalasi-operasional.md).

### 7.1 `config/app.php`

```php
'timezone'       => 'Asia/Jakarta',
'locale'         => env('APP_LOCALE', 'id'),
'fallback_locale'=> env('APP_FALLBACK_LOCALE', 'en'),
'faker_locale'   => env('APP_FAKER_LOCALE', 'id_ID'),
```
`composer.json` → `"config": {"platform": {"php": "8.2.12"}}` agar dependensi cocok dengan PHP Apache XAMPP (00 B). `config/database.php`: koneksi `mariadb` dengan `charset=utf8mb4`, `collation=utf8mb4_unicode_ci`, `engine=InnoDB`, `strict=true`. `config/session.php`: `driver=database`, `lifetime=120`, `http_only=true`, `same_site=lax`, `secure=env('SESSION_SECURE_COOKIE')`. `config/cache.php`: `default=database` (dibutuhkan `Cache::lock` untuk `booking-submit:*` dan `sessions:generate`). `config/auth.php`: `passwords.users.expire=60`, `throttle=60` (jeda minimal antar-pengiriman tautan reset per akun). `config/logging.php`: kanal `daily`, `days=14`.

### 7.2 `config/purifier.php` — profil `rich_text`

Dipakai oleh `Content\HtmlSanitizer` (lihat 11 §6.12) untuk lima kolom rich text 00 C (`pages.body`, `articles.body`, `clinics.description`, `facilities.description`, `room_categories.description`).

```php
return [
    'encoding'         => 'UTF-8',
    'finalize'         => true,
    'ignoreNonStrings' => false,
    'cachePath'        => storage_path('app/purifier'),
    'cacheFileMode'    => 0755,
    'settings' => [
        'default' => [
            'HTML.Doctype' => 'HTML 4.01 Transitional',
            'HTML.Allowed' => 'p,br,strong,em',
            'AutoFormat.RemoveEmpty' => true,
        ],
        'rich_text' => [
            'HTML.Doctype' => 'HTML 4.01 Transitional',
            // tag & atribut yang diizinkan (00 K / tugas bagian 10):
            'HTML.Allowed' => 'p,br,h2,h3,h4,strong,em,u,ul,ol,li,'
                            . 'a[href|title|target|rel],img[src|alt|width|height],blockquote,'
                            . 'table,thead,tbody,tr,th,td',
            'Attr.AllowedFrameTargets' => ['_blank'],
            'Attr.AllowedRel'          => ['noopener', 'noreferrer'],
            'HTML.TargetNoopener'      => true,
            'HTML.TargetNoreferrer'    => true,
            'URI.AllowedSchemes'       => ['http' => true, 'https' => true, 'mailto' => true, 'tel' => true],
            'URI.Host'                 => parse_url(env('APP_URL'), PHP_URL_HOST),
            'URI.DisableExternalResources' => true,   // <img src> dari host lain dibuang
            'CSS.AllowedProperties'    => [],         // tanpa atribut style
            'Attr.EnableID'            => false,
            'AutoFormat.RemoveEmpty'   => true,
            'AutoFormat.AutoParagraph' => false,
            'Core.RemoveInvalidImg'    => true,
            'HTML.MaxImgLength'        => 1600,
        ],
    ],
];
```

Tiga aturan yang **tidak dapat** diekspresikan murni lewat konfigurasi HTMLPurifier ditegakkan oleh `HtmlSanitizer::clean()` sebagai pasca-proses DOM (`DOMDocument`) dan diuji di 15:

1. `img[src]` hanya boleh berawalan `/storage/` (URL absolut ke host aplikasi dinormalkan ke path relatif; selainnya → elemen `<img>` dibuang).
2. Tautan eksternal (`href` `http(s)://` dengan host ≠ host aplikasi) dipaksa `target="_blank"` dan `rel="noopener noreferrer"`.
3. Tautan ber-skema selain `http`, `https`, `mailto`, `tel` dibuang (sudah ditangani `URI.AllowedSchemes`; DOM memeriksa ulang sebagai lapis kedua).

Catatan: tabel diizinkan agar konten seeder/tempelan sederhana tetap utuh, namun toolbar Quill tidak menyediakan pembuatan tabel. Atribut kelas/gaya Quill (`ql-*`, `style`) dibuang — penyelarasan teks tidak didukung **[ASUMSI]**.

### 7.3 `config/filesystems.php`

```php
'default' => env('FILESYSTEM_DISK', 'local'),
'disks' => [
    'local'  => ['driver' => 'local', 'root' => storage_path('app/private'), 'serve' => false, 'throw' => false, 'report' => false],
    'public' => ['driver' => 'local', 'root' => storage_path('app/public'),
                 'url' => '/storage',          // path relatif → jalan di unpam-hospital.test maupun 127.0.0.1:8000 [ASUMSI]
                 'visibility' => 'public', 'throw' => false, 'report' => false],
],
'links' => [public_path('storage') => storage_path('app/public')],
```
- `public` = media publik (`storage/app/public/{collection}/{YYYY}/{MM}/{uuid}.{ext}`), dilayani Apache lewat symlink `public/storage`. Pada Windows, `php artisan storage:link` memerlukan terminal Administrator atau Developer Mode aktif (troubleshooting di 16).
- `local` = privat (`storage/app/private`), **tidak** punya URL publik (`serve=false`); isi hanya boleh dilayani controller + policy (00 K).
- `MediaService` memilih disk dari kolom `media.disk`; seluruh koleksi bawaan 00 E.6 memakai `public`.

### 7.4 `config/permission.php` (Spatie)

| Kunci | Nilai | Alasan |
|---|---|---|
| `models.permission` / `models.role` | bawaan Spatie | — |
| `table_names` / `column_names` | bawaan (`roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`) | 00 E.1 |
| `teams` | `false` | Tidak ada multi-tenant |
| `register_permission_check_method` | `true` | `$user->can('settings.manage')` bekerja lewat Gate |
| `enable_wildcard_permission` | `false` | Izin eksplisit |
| `display_permission_in_exception` / `display_role_in_exception` | `false` | Pesan 403 tidak membocorkan nama izin |
| `cache.expiration_time` | 24 jam; `cache.store` = `default` (database) | Seeder wajib menjalankan `php artisan permission:cache-reset` |

Guard default `web` (satu-satunya guard, 00 H).

### 7.5 `config/dompdf.php` (barryvdh/laravel-dompdf)

```php
'options' => [
    'font_dir'        => storage_path('fonts'),
    'font_cache'      => storage_path('fonts'),
    'chroot'          => realpath(base_path()),
    'default_font'    => 'DejaVu Sans',          // font bawaan dompdf, lokal, mendukung Latin lengkap [ASUMSI]
    'default_paper_size' => 'a4',
    'dpi'             => 96,
    'enable_remote'   => false,                  // = isRemoteEnabled=false: tidak mengambil sumber dari internet
    'enable_php'      => false,
    'enable_javascript' => false,
    'enable_html5_parser' => true,
    'allowed_protocols' => [                     // hanya aset lokal & QR inline
        'data://' => ['rules' => []],
        'file://' => ['rules' => []],
    ],
],
```
Nama kunci mengikuti `barryvdh/laravel-dompdf` ^3 (setara opsi Dompdf `isRemoteEnabled`); verifikasi terhadap `config/dompdf.php` hasil `vendor:publish` saat implementasi. QR code disisipkan sebagai `data:image/svg+xml;base64,…` (tanpa permintaan jaringan). Bila rendering SVG di dompdf bermasalah, cadangan: keluarkan QR sebagai PNG `data:image/png;base64` (GD aktif). Font identitas Plus Jakarta Sans tidak dipakai di PDF karena paket `@fontsource` hanya menyediakan woff/woff2 (dompdf butuh TTF) — **[KONFIRMASI]** apakah pemilik menginginkan font identitas di PDF (butuh satu kali mengunduh berkas TTF berlisensi OFL dan mendaftarkannya).

### 7.6 Konfigurasi lain yang disentuh

| File | Perubahan |
|---|---|
| `config/navigation.php` | Larik `public`, `patient`, `admin` (item: `label`, `route`, `icon`, `permission` opsional, `children` opsional) dibaca layout untuk membangun menu, dan larik `modules` (grup `schedules`, `clinics`, `facilities`, `appointments`, `content`, `account`; item: `label`, `route`, `permission`, `badge` opsional) dibaca `<x-module-tabs>`. |
| `config/mail.php` | `default=env('MAIL_MAILER','log')`; `from.name` = nama situs, `from.address` = `info@unpamhospital.test` (placeholder). |
| `bootstrap/providers.php` | Hanya `App\Providers\AppServiceProvider`. |
| `.env.testing.example` | `APP_ENV=testing`, `DB_CONNECTION=mariadb`, `DB_DATABASE=unpam_hospital_test`, `CACHE_STORE=array`, `SESSION_DRIVER=array`, `QUEUE_CONNECTION=sync`, `MAIL_MAILER=array`. |

---

## Asumsi di bagian ini

1. **[ASUMSI]** Awalan nama file migration `2026_10_02_` + nomor berurutan (daftar kanonik di 09 bagian 10); yang mengikat hanya urutan dependensi (§1.2).
2. **[ASUMSI]** `Public` dipakai sebagai segmen namespace (`App\Http\Controllers\Public`); sudah diverifikasi sah pada PHP ≥ 8.0 dengan `php -l` dan pemanggilan `Public\HomeController::class`.
3. **[ASUMSI]** Entry CSS admin dipisah (`resources/scss/admin.scss`, diimpor oleh `admin.js`) agar CSS admin tidak dimuat di halaman publik; `admin.js` mengimpor `app.js` untuk perilaku bersama (satu listener fallback gambar sesuai 00 K); entry Vite tetap tiga (`app.scss`, `app.js`, `admin.js`). Modul `editor.js`, `charts.js`, `booking.js`, `image-preview.js` dimuat lewat `import()` dinamis, bukan entry terpisah.
4. **[ASUMSI]** `$secondary` Bootstrap dipertahankan abu-abu; toska tua dipakai sebagai warna tema tambahan `accent` (`.btn-accent`, `.text-bg-accent`) karena 00 D memetakan `cancelled` → `secondary`.
5. **[ASUMSI]** Komponen kelas hanya `StatusBadge`, `AvailabilityBadge`, `Image`, `MapEmbed`; sisanya anonim (termasuk `wizard-steps` dan `module-tabs` dari 00 J.5). Dua komponen di luar 00 J.5, diterima di 00 J.6: `form.rich-text`, `proof-card`.
6. **[ASUMSI]** Navigasi layout dibangun dari `config/navigation.php`, bukan ditulis keras di Blade.
7. **[ASUMSI]** Disk `public` memakai `url = '/storage'` (relatif) sehingga gambar tampil di `unpam-hospital.test` dan `127.0.0.1:8000`; konsisten dengan aturan `img[src]` rich text yang hanya boleh `/storage/…`.
8. **[ASUMSI]** Daftar bertanda Quill dihasilkan lewat `getSemanticHTML()` (bukan `root.innerHTML`) agar tidak kehilangan semantik `<ul>` setelah dimurnikan; penyelarasan teks/gaya inline tidak didukung.
9. **[ASUMSI]** Font PDF = DejaVu Sans bawaan dompdf; kunci `dompdf.options.enable_remote=false` setara `isRemoteEnabled=false`.
10. **[ASUMSI]** `AuditObserver` memberi audit generik untuk model CRUD sederhana; peristiwa domain dicatat eksplisit oleh service. Detail model yang diobservasi di 11 §5.
11. **[ASUMSI]** `routes/console.php` hanya memuat jadwal opsional; tidak ada fitur inti yang bergantung pada Task Scheduler/cron (00 B.2, F.2).
12. **[ASUMSI]** Pengujian otomatis memakai MariaDB (`unpam_hospital_test`), bukan SQLite.
13. **[ASUMSI]** `Route::resourceVerbs(['create' => 'buat', 'edit' => 'ubah'])` untuk URL Indonesia; nama route tidak berubah.
14. **[ASUMSI]** `errors/layout.blade.php` tidak membaca database sehingga halaman 500/503 tetap tampil saat DB bermasalah.
15. **[ASUMSI]** Peran `RolePolicy` didaftarkan manual (`Gate::policy`) karena `Role` bukan di `App\Models`.
16. **[ASUMSI]** Path instalasi XAMPP pada mesin pengembang ini adalah `C:\xampp3` (PHP: `C:\xampp3\php\php.exe`; MariaDB: `C:\xampp3\mysql\bin`); `scripts/backup.ps1` dan `restore.ps1` menerima path XAMPP sebagai parameter (default `C:\xampp3`) agar di mesin lain dapat diganti `C:\xampp`.
17. Font identitas di PDF bukti pendaftaran (lihat §7.5) = keputusan K-17 di 17; default (a): DejaVu Sans bawaan dompdf (opsi (b): memasang TTF Plus Jakarta Sans hanya mengubah konfigurasi dompdf dan berkas font lokal).
18. Notifikasi pasien mengikuti 00 F.8/K-13: kanal `database` untuk setiap transisi, kanal `mail` hanya untuk `confirmed`, `cancelled`, dan perubahan jadwal; `PatientWelcomeNotification` (mail saja) mengikuti K-22 default (a).

## Nama baru yang diusulkan

Nama berikut tidak didefinisikan di 00 dan perlu direkonsiliasi ke dokumen itu. Nama controller, Form Request, dan model-level method dicatat di akhir [11-route-controller-model-service.md](11-route-controller-model-service.md).

| Jenis | Nama | Keterangan |
|---|---|---|
| Trait enum | `App\Enums\Concerns\HasOptions` (`values()`, `options()`) | Helper opsi/rule enum. |
| Method enum | `Availability::isBookable()`, `Availability::forException()`; `AppointmentStatus::finals()`, `isActive()`, `consumesQuota()`, `isFinal()`; `SessionStatus::isOpen()`, `blocksNewBookings()`; `SessionResolution::allowedFor()`, `requiresReason()`, `description()`; `ExceptionType::requiresDoctor()`; `PaymentType::requiresBpjsNumber()`, `requiresInsurancePartner()`, `allowsPolicyNumber()`; `ActorType::fromUser()`; `DoctorStatus::isActive()`; `AccountType::isStaff()`/`isPatient()` | Helper turunan dari 00 D/F. |
| Exception | `App\Exceptions\InvalidTransitionException` (turunan `BookingException`) | Galat transisi status (di luar 9 kode F.5). **Diterima (00 J.6).** |
| Trait model | `App\Models\Concerns\HasMedia`, `App\Models\Concerns\SanitizesRichText` | Relasi `media` + sanitasi rich text. |
| Observer | `App\Observers\AuditObserver` | Audit generik CRUD model konten. **Diterima (00 J.6).** |
| Form Request (basis, trait) | `App\Http\Requests\ListFilterRequest`; trait `App\Http\Requests\Concerns\HasBookingRules` | Basis GET-filter longgar (parameter tidak valid diabaikan, 00 I.5; `filters()`); trait aturan pembayaran/keluhan bersama tiga Form Request booking. Belum ada di 00 J.6. |
| Aturan validasi | `App\Rules\PersonName`, `IndonesianPhone`, `NoHtml`, `InternalOrHttpsUrl`, `NoOverlappingSchedule`, `NoOverlappingSession`; helper `ImageRules`, `PasswordRules` | Rinci di 12 §3.1. **Diterima (00 J.6).** |
| DTO / VO | `App\Support\Actor`, `App\Support\SafeRedirect`; `Services\Scheduling\AvailabilityFilter`, `SessionAvailability`, `ScheduleChangeReport`; `Services\Booking\BookingData`, `Services\Booking\BookingRules`; `Services\Media\MediaPruneReport` | Lihat 11 §6. **Diterima (00 J.6)** kecuali `MediaPruneReport` (usulan). |
| Service | `Services\Booking\AppointmentProofService`, `Services\Media\PlaceholderImageGenerator`; metode `PatientRegistrationService::createWalkIn()` | QR dan PDF bukti pendaftaran (**diterima, 00 J.6**); gambar contoh seeder (**diterima, 00 J.6**); pembuatan profil walk-in. (`PatientRegistrationService` dan `Staff\StaffAccountService` sudah ada di 00 J.1.) |
| Notifikasi | `App\Notifications\PatientWelcomeNotification` | Mail selamat datang setelah registrasi akun (K-22 default (a)); belum ada di 00 L. |
| Komponen Blade | `form.rich-text`, `proof-card` | Di luar 27 komponen 00 J.5. **Diterima (00 J.6).** |
| Konfigurasi | `config/navigation.php` | Menu sebagai data. **Diterima (00 J.6).** |
| SCSS | `resources/scss/admin.scss` | Entry CSS admin. **Diterima (00 J.6).** |
| Terjemahan | `lang/id.json`, `lang/id/booking.php`, `lang/id/messages.php` | Teks email bawaan, pesan `BookingException` (9 kode), flash message. |
| Policy | `PracticeSessionPolicy::delete` (ability baru) | Untuk route `admin.sessions.destroy` yang sudah tercantum di 00 I.4 (aturan 00 C: hanya sesi tambahan manual tanpa appointment). |
| View | `errors/layout.blade.php`, `pdf/appointment-proof.blade.php`, `admin/partials/*`, `public/partials/*`, semua nama file view §2 | Penamaan file. |
| Berkas lingkungan | `.env.testing.example`, `pint.json` | Pengujian & format kode. |
| Migration | Penamaan file di §1.2 | Urutan dependensi. |
