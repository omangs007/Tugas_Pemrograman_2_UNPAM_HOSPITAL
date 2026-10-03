# Fase 1 — Fondasi: Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Membangun fondasi lintas fitur UNPAM HOSPITAL — enum, akun & peran, audit log, media, sanitasi HTML, pengaturan situs, tema & komponen Blade, layout, login/ganti password staf, header keamanan, aturan mutlak Super Admin, dan perintah `app:create-super-admin` — sehingga fase 2–9 tinggal menambah modul bisnis di atasnya.

**Architecture:** Monolit Laravel 12 (Blade + Bootstrap 5 via Vite). Aturan bisnis di `app/Services` (tanpa membaca `request()`, pelaku dikirim sebagai `App\Support\Actor`), controller tipis, Form Request untuk validasi, middleware untuk tipe akun/aktif/ganti password, Spatie Permission untuk peran & izin dengan `Gate::before` untuk Super Admin. Menu dan tautan ke route fase berikutnya disaring `Route::has()` sehingga aplikasi selalu hijau di akhir setiap fase.

**Tech Stack:** PHP 8.2.12 (XAMPP `C:\xampp3`), Laravel 12.69, MariaDB 10.4.32, spatie/laravel-permission 6.25, intervention/image 3.11 (GD), mews/purifier 3.4 (HTMLPurifier 4.19), Bootstrap 5.3 + Sass + Vite 7, bootstrap-icons, @fontsource/plus-jakarta-sans, PHPUnit 11, Laravel Pint.

**Spec:** `docs/superpowers/specs/2026-10-02-unpam-hospital-blueprint/` — terutama `00-keputusan-inti.md` (C, D, E.1, E.6, E.7, G, H, I.4, I.5, J, K, M), `14-tahapan-implementasi.md` (§5 Fase 1), `09-data-dictionary.md` (§2.1–2.10, §7, §10), `10-struktur-folder.md` (§1, §3, §4, §6, §7), `11-route-controller-model-service.md` (§1, §2.3.A, §3, §4.2, §5.1, §5.7, §6.1, §6.9–6.12, §6.15, §7, §9, §10.2–10.3, §11), `12-aturan-bisnis-validasi-keamanan.md` (§3, V-03, V-08, V-40, V-45, §5.3–5.6, §8), `13-data-contoh.md` (§2.3–2.4, §3, §8.9, §9.1), `15-pengujian-penerimaan.md` (§3.3, §4.1, §4.3, §4.6, §4.9, §4.10), `05-sitemap-matriks-halaman.md` (§3.1–3.5), `07-wireframe.md` (W-01, W-09, W-13). Bila ada perbedaan, **00 yang berlaku**; penyimpangan yang disengaja dicatat di bagian "Keputusan (rulings) fase 1".

## Global Constraints

- XAMPP: **`C:\xampp3`** (PHP 8.2.12). Folder `C:\xampp` lama **jangan** dipakai. Apache + MySQL harus **Start** di `C:\xampp3\xampp-control.exe` sebelum menjalankan tes.
- **Setiap perintah PowerShell diawali Prelude P** (state shell tidak bertahan antar-perintah):
  `$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP`
- PHP selalu `& $PHP …` (bukan `php` di PATH = 8.4). Composer selalu `& $PHP $COMPOSER …`. **Tidak ada paket Composer/npm baru** di fase ini.
- Kode kompatibel PHP 8.2–8.4: **dilarang** fitur khusus 8.3+ (typed class constants, `#[\Override]`, `json_validate()`, property hooks, `new` tanpa kurung pada chaining). Berkas baru di `app/Services` dan `app/Support` diawali `declare(strict_types=1);`.
- Nama tabel, kolom, enum, route, izin, kunci pengaturan, service, dan method **persis** seperti 00/09/10/11. Nama baru hanya yang tercantum di bagian "Nama baru yang diperkenalkan fase 1".
- Identifier kode berbahasa Inggris; **semua teks UI, pesan validasi, flash, dan deskripsi audit berbahasa Indonesia** (kalimat lengkap berakhir titik untuk flash).
- `Model::shouldBeStrict()` aktif di non-produksi sejak Task 2: semua relasi yang dipakai wajib di-*eager load*; tes harus tetap hijau dengan mode ketat.
- Tes memakai MariaDB `unpam_hospital_test` (pengaman `Tests\TestCase::setUpTraits` tetap utuh). Tes yang menyentuh DB memakai `Illuminate\Foundation\Testing\RefreshDatabase`. Tes media memakai `Storage::fake('public')`. Jalankan tes dengan `& $PHP artisan test` (opsi `--filter=`).
- **Jangan** mengubah migration skeleton `0001_01_01_*`. Migration proyek memakai nama 09 §10 (awalan `2026_10_02_` + 6 digit).
- Tidak ada `{!! !!}` di view fase 1 (belum ada kolom rich text yang dirender). Tidak ada atribut event inline (`onerror`, `onclick`, …) di HTML. Tidak ada CDN/font/ikon eksternal.
- Penulisan berkas: pakai tool Write/Edit. Bila lewat PowerShell: `[IO.File]::WriteAllText(path, text, (New-Object System.Text.UTF8Encoding $false))` (tanpa BOM).
- `.env` dan `.env.testing` **tidak pernah** di-commit.
- Cabang kerja: **`fase-1-fondasi`** dari `master` (setelah `fase-0-lingkungan` di-merge). Pesan commit: `tipe(fase-1): ringkasan` (tipe: `feat`, `test`, `chore`, `docs`, `refactor`, `fix`). Satu tugas = satu commit atau lebih; setiap commit `artisan test` hijau dan `vendor\bin\pint --test` bersih.
- Langkah bertanda **MANUAL (pemilik)** butuh tindakan pemilik (browser, XAMPP Control Panel). Implementer berhenti di langkah itu, melapor `NEEDS_CONTEXT` beserta instruksi, lalu melanjutkan setelah dikonfirmasi.

## Review Focus

1. **Mode ketat + Spatie/menu memicu `LazyLoadingViolationException`** di halaman admin untuk peran tertentu (mis. `roleLabel`, `@can` pada menu). Harapan: dashboard admin dirender 200 untuk ketiga peran dengan mode ketat aktif. → Task 14 (`AdminLayoutTest`).
2. **Pengalihan ke route yang belum ada di fase 1** (`login`, `patient.dashboard`, `doctors.index`, …) menghasilkan `RouteNotFoundException` (500). Harapan: tamu di route ber-`auth` non-admin diarahkan ke `home`; pasien di halaman tamu diarahkan ke `home`; menu hanya menampilkan route yang ada. → Task 13 (`NavigationMenuTest`) dan Task 14 (`GuestRedirectTest`).
3. **Unggahan dengan ekstensi menipu** (PNG bernama `foto.jpg`, ekstensi huruf besar `.JPG`, PNG transparan, WebP). Harapan: tipe ditentukan dari isi berkas (finfo), ekstensi tersimpan huruf kecil sesuai MIME nyata, PNG tetap PNG (transparansi tidak menjadi hitam), tidak ada berkas yang memakai nama asli. → Task 5 (`MediaServiceTest`).
4. **Email login berhuruf besar/berspasi** (`  Admin@UnpamHospital.TEST `) gagal login atau lolos dari throttle karena kunci limiter berbeda. Harapan: email dinormalkan (trim + huruf kecil) sebelum autentikasi dan sebelum kunci limiter dihitung. → Task 14 (`AdminLoginTest`).
5. **Nilai pengaturan kosong/bertipe** (`site.logo_media_id` kosong, bool `"0"`, logo yang medianya sudah dihapus). Harapan: `int()` mengembalikan default, `bool('booking.auto_confirm')` = `false`, `logoUrl()` jatuh ke `/images/placeholders/logo.svg`. → Task 7 (`SettingsServiceTest`).

## Keputusan (rulings) fase 1

| ID | Keputusan | Alasan |
|---|---|---|
| F1-R1 | Cabang `fase-1-fondasi` dibuat dari `master` setelah merge `fase-0-lingkungan` (keputusan pemilik 2026-10-03; K-10 = (a)). | 14 §6 butir 5 |
| F1-R2 | Menu (`config/navigation.php`) dan tombol layout yang menuju route fase berikutnya disaring `Route::has()`; `redirectGuestsTo`/`redirectUsersTo`/`EnsureAccountIsActive` memakai `Route::has('login')`/`Route::has('patient.dashboard')` dengan cadangan `route('home')`. Cadangan ini dihapus di fase 5. | Aplikasi tetap hijau per fase |
| F1-R3 | Route `/` (`home`) sementara `Route::view('/', 'public.home')` memakai `x-layouts.public` (halaman sambutan sederhana); `HomeController` dibangun di fase 4a. `welcome.blade.php` dihapus. | Butuh nama route `home` (SafeRedirect, halaman galat) |
| F1-R4 | `admin.dashboard` sementara berisi sapaan (`Admin\DashboardController@index`); widget/grafik di fase 8. | 14 Fase 1 |
| F1-R5 | Login staf gagal karena akun nonaktif memakai pesan **generik** "Email atau password salah." (00 H, 11 §7.2, AKS-08), bukan pesan khusus V-03. Dokumen 12 V-03 diperbarui di Task 19. | 00 berlaku |
| F1-R6 | Throttle pada **POST** → redirect 302 kembali ke form + flash `error` "Terlalu banyak percobaan. Silakan coba lagi dalam {n} detik." + header `Retry-After`; pada GET → halaman 429 (11 §11.3, W-09). Kasus 15 SEK-03 diperbarui di Task 19. | 11 §11.3 rinci + wireframe |
| F1-R7 | `AuditLogger::log()` menerima `Model $model` **wajib** (bukan `?Model`): aksi tanpa satu baris mengirim instance kosong (mis. `new User`, `new Media`) sehingga `auditable_type` terisi (kolom NOT NULL, 09 §7.3) dan `auditable_id` NULL. `redact()` bersignature `redact(array $values, string $action, ?Model $model = null)` karena redaksi tingkat 2 bergantung pada awalan aksi (12 §8.1). | Konsistensi skema |
| F1-R8 | Konteks audit: `ip_address`/`user_agent` diisi hanya bila ada permintaan HTTP yang ter-*route* (`request()->route() !== null`); CLI/tes unit → NULL (09 §7.3 [ASUMSI]). | 09 berlaku atas 11 (`'cli'`) |
| F1-R9 | Ditunda ke fase pemakai: `HasMedia`/`SanitizesRichText` (fase 2), `AuditObserver` (fase 2), `StaffAccountService::syncRolePermissions` + `RolePolicy` + `Route::bind('user')` (fase 8), `SettingsService::bookingRules()` (fase 6), `module-tabs`/`availability-badge`/`doctor-card`/`schedule-table` (fase 3), `map-embed` (fase 4b), `form.rich-text` + `editor.js` (fase 4a), `wizard-steps`/`proof-card` (fase 6), `lang/id/messages.php` (fase 2), `lang/id.json` + `routes/patient.php` (fase 5), handler `BookingException` (fase 6). | YAGNI per fase |
| F1-R10 | `DemoSeeder` (guard 13 §2.3) + `DemoUserSeeder` **hanya 3 akun staf** dibuat di fase 1 agar login per peran dapat diuji manual; akun pasien demo ditambahkan di fase 5. Password dari `config('app.demo_password')` (`DEMO_PASSWORD`, default `Demo12345`, K-11 (a)). | Penerimaan manual fase 1 |
| F1-R11 | Vite mengikuti 10 §6.1: input `resources/scss/app.scss`, `resources/js/app.js`, `resources/js/admin.js`; `admin.js` mengimpor `../scss/admin.scss` (menutup catatan fase 0 tentang manifest yang menggabungkan dua SCSS identik). | 10 §6 |
| F1-R12 | `media:prune` membersihkan (a) baris `model_id` NULL > 24 jam kecuali id `site.logo_media_id`, (b) berkas di folder koleksi disk `public` yang tak tercatat dan berumur > 1 jam. Butir (c) 09 [ASUMSI] (pemilik hilang) ditunda sampai ada model pemilik (fase 2). | 11 §6.9 |
| F1-R13 | Kelas `.table-cards` (tabel → kartu < 768 px) dipakai publik & admin sehingga ditaruh di partial bersama `resources/scss/_shared.scss` yang diimpor `app.scss` dan `admin.scss`. | DRY |
| F1-R14 | `Tests\TestCase::setUp()` memanggil `$this->withoutVite()` untuk semua tes; build aset diverifikasi terpisah dengan `npm run build` (Task 9, Task 19). | Tes tidak bergantung build |
| F1-R15 | Halaman 403 tidak pernah menampilkan pesan `UnauthorizedException` Spatie atau pesan bawaan framework (berbahasa Inggris) sebagai `$detail` (menyimpang dari sketsa 11 §11.3 demi ERR-02; dicatat D-27). | 15 ERR-02 |
| F1-R16 | Login staf yang sukses membersihkan penghitung limiter `login` untuk email + IP itu (`RateLimiter::clear(md5('login'.$kunci))`, Task 14 Siklus 5b). | 12 §5.1 butir 3, 06 §14.A |
| F1-R17 | `storage/app/public/.htaccess` menolak eksekusi skrip dan daftar folder di disk publik (Task 5 Siklus I); `storage/app/public/.gitignore` ditambah `!.htaccess`, `storage/app/.gitignore` ditambah `!purifier/` (Task 6). | 12 §5.1 butir 9 |
| F1-R18 | `x-layouts.patient` membungkus `x-layouts.public` lewat prop opsional `noindex` (tanpa menggandakan header/footer); menu mobile publik memakai dropdown Bootstrap di dalam offcanvas. Lencana angka sidebar (Sesi Terdampak, Pesan Kontak) ditunda ke fase pemilik route-nya. | DRY, YAGNI |
| F1-R19 | `env('DEMO_PASSWORD') ?: 'Demo12345'` (bukan default kedua `env()`), karena `.env.example` memuat `DEMO_PASSWORD=` kosong. | Bug kerangka ditemukan saat penulisan |
| F1-R20 | Berkas tes boleh dipecah lebih banyak daripada daftar kontrak (satu berkas per siklus TDD); nama kelas tes di setiap task adalah yang berlaku. | Rencana per siklus |
| F1-R21 | **F-ADM-29 (endpoint media admin `admin.media.*`: unggah gambar editor, ubah alt/caption, hapus, urutkan; `MediaController`, `MediaPolicy`, Form Request V-42) ditunda ke fase 2.** Otorisasinya mengikuti izin model pemilik (dokter ⇒ `doctors.manage`, poliklinik ⇒ `clinics.manage`, …) yang baru ada di fase 2; fase 1 menyediakan seluruh lapisan `MediaService` yang dipakai endpoint tersebut. Dicatat D-28. | Policy tidak dapat diuji tanpa model pemilik |

## Nama baru yang diperkenalkan fase 1

Dicatat ulang ke blueprint 17 §4 / 00 J.6 pada Task 19.

| Nama | Jenis | Task |
|---|---|---|
| `App\Support\NavigationMenu::items(string $area, ?User $user = null): array` | Kelas pendukung menu (saring `Route::has` + izin) | 13 |
| `App\Support\SafeRedirect::internalPath(?string $value): ?string` | Method validasi path internal (`kembali`) | 12 |
| `App\Http\Requests\ListFilterRequest::perPage(int $default = 25): int` | Method bantu paginasi | 12 |
| `App\Rules\ImageRules::messages(string $field, string $collection): array` | Pesan galat Indonesia per koleksi | 5 |
| `App\Services\Media\MediaPruneReport::$items` (`list<string>`, parameter ke-5 opsional) | Daftar yang dicetak `--dry-run` | 8 |
| `lang/id/roles.php` | Label peran UI | 3 |
| `resources/scss/_shared.scss` | Partial gaya bersama | 9 |
| `config('app.demo_password')` | Kunci konfigurasi `DEMO_PASSWORD` | 18 |
| `Database\Seeders\RolePermissionSeeder::PERMISSIONS`, `::ROLES` | Konstanta katalog izin | 3 |
| `Database\Seeders\SettingsSeeder::DEFAULTS` | Konstanta 19 kunci pengaturan | 7 |
| `Database\Seeders\DemoUserSeeder` (3 staf) | Seeder demo staf | 18 |
| `UserFactory::staff()`, `::patient()`, `::inactive()`, `::mustChangePassword()`, `::withRole()` | State factory | 2, 3 |
| `Database\Seeders\DemoUserSeeder::ACCOUNTS` | Konstanta akun demo staf | 18 |
| `App\Rules\ImageRules::INVALID_IMAGE`, `::NOT_IMAGE`, `::WRONG_TYPE`, `::UPLOAD_FAILED` | Konstanta pesan unggahan (12 §3.2) | 5 |
| `resources/scss/_bootstrap-theme.scss` | Partial tumpukan impor Bootstrap bersama | 9 |
| Prop `noindex` pada `x-layouts.public` | Prop opsional layout | 13 |
| `storage/app/public/.htaccess` | Penolak skrip disk publik | 5 |
| `Tests\Support\InteractsWithStaff`, `InteractsWithMedia`, `InteractsWithFormState`, `ImageFixtures`, `Fixtures\{PlainEnum,HostileStatus}`, `Requests\StatusSortFilterRequest` | Pembantu tes | 3, 5, 10, 11, 12 |

## Struktur berkas (peta tanggung jawab)

```
app/
  Console/Commands/PruneMedia.php                      (T8)  media:prune {--dry-run}
  Console/Commands/CreateSuperAdmin.php                (T18) app:create-super-admin
  Enums/Concerns/HasOptions.php + 15 enum              (T1)
  Http/Controllers/Admin/DashboardController.php       (T14) sapaan sementara
  Http/Controllers/Auth/AdminLoginController.php       (T14)
  Http/Controllers/Auth/AdminPasswordController.php    (T15)
  Http/Middleware/EnsureAccountType.php                (T14) alias account
  Http/Middleware/EnsureAccountIsActive.php            (T14) alias active
  Http/Middleware/EnsurePasswordChanged.php            (T15) alias password.changed
  Http/Middleware/SecurityHeaders.php                  (T16) global
  Http/Requests/ListFilterRequest.php                  (T12)
  Http/Requests/Auth/AdminLoginRequest.php             (T14)
  Http/Requests/Admin/UpdatePasswordRequest.php        (T15)
  Models/User.php (ubah)                               (T2, T3)
  Models/AuditLog.php                                  (T4)
  Models/Media.php                                     (T5)
  Models/WebsiteSetting.php                            (T7)
  Providers/AppServiceProvider.php (ubah)              (T2, T3, T7, T11, T13, T14)
  Rules/ImageRules.php                                 (T5)
  Rules/PasswordRules.php                              (T15)
  Services/Audit/AuditLogger.php                       (T4)
  Services/Content/HtmlSanitizer.php                   (T6)
  Services/Media/MediaService.php                      (T5, T8)
  Services/Media/MediaPruneReport.php                  (T8)
  Services/Settings/SettingsService.php                (T7)
  Services/Staff/StaffAccountService.php               (T17)
  Support/Actor.php                                    (T4)
  Support/SafeRedirect.php                             (T12)
  Support/NavigationMenu.php                           (T13)
  View/Components/StatusBadge.php                      (T10)
  View/Components/Image.php                            (T10)
bootstrap/app.php (ubah)                               (T3, T14, T15, T16)
config/permission.php                                  (T3)  publish Spatie
config/purifier.php                                    (T6)  publish + profil rich_text
config/navigation.php                                  (T13)
config/app.php (ubah: demo_password)                   (T18)
database/migrations/2026_10_02_000010_add_account_fields_to_users_table.php   (T2)
database/migrations/2026_10_02_000020_create_permission_tables.php            (T3)
database/migrations/2026_10_02_000030_create_notifications_table.php          (T2)
database/migrations/2026_10_02_002100_create_media_table.php                  (T5)
database/migrations/2026_10_02_002200_create_website_settings_table.php       (T7)
database/migrations/2026_10_02_002300_create_audit_logs_table.php             (T4)
database/factories/UserFactory.php (ubah)              (T2, T3)
database/seeders/DatabaseSeeder.php (ubah)             (T3, T7, T18)
database/seeders/RolePermissionSeeder.php              (T3)
database/seeders/SettingsSeeder.php                    (T7)
database/seeders/DemoSeeder.php, DemoUserSeeder.php    (T18)
lang/id/roles.php                                      (T3)
public/images/placeholders/{doctor,clinic,facility,room,article,banner,gallery,logo}.svg  (T5)
resources/scss/{_variables,_bootstrap-theme,_shared,_public,_admin,_print}.scss, app.scss, admin.scss       (T9)
resources/js/app.js, admin.js, image-preview.js        (T9)
resources/views/components/{breadcrumb,page-header,empty-state,flash-alert,loading-button,stat-card,status-badge,image}.blade.php  (T10)
resources/views/components/form/{input,select,textarea,checkbox,radio-group,file,errors-summary}.blade.php                         (T11)
resources/views/components/{data-table,confirm-modal}.blade.php                                                                    (T11)
resources/views/vendor/pagination/{bootstrap-5,simple-bootstrap-5}.blade.php                                                       (T11)
resources/views/components/layouts/{public,patient,auth}.blade.php                                                                 (T13)
resources/views/components/layouts/admin.blade.php                                                                                 (T14, T15)
resources/views/public/home.blade.php                  (T13)
resources/views/errors/{layout,403,404,419,429,500,503}.blade.php                    (T13)
resources/views/auth/admin-login.blade.php             (T14)
resources/views/auth/admin-change-password.blade.php   (T15)
resources/views/admin/dashboard.blade.php              (T14)
routes/web.php (ubah)                                  (T13)
routes/admin.php                                       (T14, T15)
storage/app/purifier/.gitignore                        (T6)
storage/app/public/.htaccess, storage/app/public/.gitignore (ubah)   (T5)
tests/Support/{InteractsWithStaff,InteractsWithMedia,ImageFixtures}.php (T3, T5); tests/Support/Fixtures/{PlainEnum,HostileStatus}.php (T10); tests/Support/InteractsWithFormState.php (T11); tests/Support/Requests/StatusSortFilterRequest.php (T12)
tests/... (per task)
```

---

> **Catatan penyusunan:** rincian Task 1–19 disusun dari kerangka kontrak di atas oleh lima penulis (Sonnet), lalu diperiksa koordinator: berkas bersama (`AppServiceProvider`, `bootstrap/app.php`, `DatabaseSeeder`, `routes/admin.php`, `tests/TestCase.php`) konsisten lintas task; props komponen yang dipakai layout cocok dengan definisinya; dua celah spesifikasi ditambahkan koordinator (Task 5 Siklus I, Task 14 Siklus 5b). Penulis Task 5–12 menjalankan kode rencananya di salinan terisolasi (SQLite/stub untuk bagian DB); bagian khusus MariaDB (CHECK `chk_media_owner`, nama FK) baru terbukti saat eksekusi.

---

### Task 1: Katalog enum (`App\Enums`)

**Files:**
- Create: `app/Enums/Concerns/HasOptions.php`
- Create: `app/Enums/AccountType.php`, `AppointmentStatus.php`, `PaymentType.php`, `BookingChannel.php`, `SessionStatus.php`, `ExceptionType.php`, `DoctorStatus.php`, `Gender.php`, `ClinicType.php`, `FacilityCategory.php`, `ArticleStatus.php`, `ActorType.php`, `ContactMessageStatus.php`, `SessionResolution.php`, `Availability.php` (semua di `app/Enums/`)
- Test: `tests/Unit/Enums/EnumCatalogTest.php`, `tests/Unit/Enums/AppointmentStatusTest.php`, `tests/Unit/Enums/SessionResolutionTest.php`

**Interfaces:**
- Consumes: —
- Produces: semua enum `string`-backed, case PascalCase, nilai & label **persis** 00 D / 10 §4. Trait `HasOptions`: `public static function values(): array` (list<string>), `public static function options(): array` (value ⇒ label). Semua enum: `label(): string`. Enum badge (`AppointmentStatus`, `PaymentType`, `BookingChannel`, `SessionStatus`, `ExceptionType`, `DoctorStatus`, `ArticleStatus`, `ActorType`, `ContactMessageStatus`, `SessionResolution`, `Availability`): `color(): string`, `icon(): string` per 10 §4.2. Enum label-saja (`AccountType`, `Gender`, `ClinicType`, `FacilityCategory`): `icon(): string`. Method tambahan persis 10 §4.1–4.3: `AppointmentStatus::ACTIVE/QUOTA_CONSUMING/FINAL`, `active()`, `quotaConsuming()`, `finals()`, `isActive()`, `consumesQuota()`, `isFinal()`; `PaymentType::requiresBpjsNumber()/requiresInsurancePartner()/allowsPolicyNumber()`; `SessionStatus::isOpen()/blocksNewBookings()`; `ExceptionType::requiresDoctor()/forbidsDoctor()`; `DoctorStatus::isActive()`; `ActorType::fromUser(User $user): self` (membandingkan `$user->account_type` dengan `AccountType::Staff`; diuji di Task 2); `SessionResolution::allowedFor(string $source): array`, `requiresReason(): bool`, `description(): string`; `Availability::isBookable(): bool`, `Availability::forException(?ExceptionType $type): self`; `AccountType::isStaff()/isPatient()`.
- Penjelasan tambahan (tidak mengubah kontrak): `SessionResolution::allowedFor()` mengembalikan `list<SessionResolution>` dan melempar `InvalidArgumentException` untuk sumber yang tidak dikenal (daftar sumber = 10 §4.2 / 00 F.4). Tes enum bersifat murni (`PHPUnit\Framework\TestCase`, tanpa aplikasi/database).

**Test IDs:** — (dasar untuk `status-badge`, cast model, validasi `Rule::enum`).

- [ ] **Step 1: Pindah ke cabang kerja `fase-1-fondasi`**

Koordinator sudah me-merge `fase-0-lingkungan` ke `master` sebelum tugas ini. Buat cabang dari `master`; bila cabang sudah ada, pindah saja ke cabang itu.

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git status --short
if (git branch --list fase-1-fondasi) { git switch fase-1-fondasi } else { git switch master; git switch -c fase-1-fondasi }
git branch --show-current
```
Expected: `git status --short` kosong (bersih); baris terakhir `fase-1-fondasi`. Bila `git switch master` gagal karena berkas belum ter-commit, hentikan dan laporkan `NEEDS_CONTEXT`.

- [ ] **Step 2: Tulis tes yang gagal `tests/Unit/Enums/EnumCatalogTest.php` (tabel literal 10 §4)**

```php
<?php

namespace Tests\Unit\Enums;

use App\Enums\AccountType;
use App\Enums\AppointmentStatus;
use App\Enums\ArticleStatus;
use App\Enums\ActorType;
use App\Enums\Availability;
use App\Enums\BookingChannel;
use App\Enums\ClinicType;
use App\Enums\Concerns\HasOptions;
use App\Enums\ContactMessageStatus;
use App\Enums\DoctorStatus;
use App\Enums\ExceptionType;
use App\Enums\FacilityCategory;
use App\Enums\Gender;
use App\Enums\PaymentType;
use App\Enums\SessionResolution;
use App\Enums\SessionStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionEnum;

class EnumCatalogTest extends TestCase
{
    /**
     * Salinan literal 10-struktur-folder.md §4.1-4.3 (dan 00 D).
     * Kolom: enum, nama case, nilai, label, color (null = enum label-saja, tidak punya color()), icon.
     *
     * @var list<array{0: class-string, 1: string, 2: string, 3: string, 4: ?string, 5: string}>
     */
    private const CATALOG = [
        [AppointmentStatus::class, 'Pending', 'pending', 'Menunggu konfirmasi', 'warning', 'bi-hourglass-split'],
        [AppointmentStatus::class, 'Confirmed', 'confirmed', 'Dikonfirmasi', 'primary', 'bi-check-circle'],
        [AppointmentStatus::class, 'CheckedIn', 'checked_in', 'Hadir/check-in', 'info', 'bi-person-check'],
        [AppointmentStatus::class, 'Completed', 'completed', 'Selesai', 'success', 'bi-check2-all'],
        [AppointmentStatus::class, 'Cancelled', 'cancelled', 'Dibatalkan', 'secondary', 'bi-x-circle'],
        [AppointmentStatus::class, 'NoShow', 'no_show', 'Tidak hadir', 'danger', 'bi-person-x'],

        [PaymentType::class, 'Umum', 'umum', 'Umum/Pribadi', 'secondary', 'bi-wallet2'],
        [PaymentType::class, 'Bpjs', 'bpjs', 'BPJS Kesehatan', 'success', 'bi-shield-plus'],
        [PaymentType::class, 'Asuransi', 'asuransi', 'Asuransi', 'primary', 'bi-shield-check'],

        [BookingChannel::class, 'Online', 'online', 'Online', 'info', 'bi-globe2'],
        [BookingChannel::class, 'Loket', 'loket', 'Loket', 'secondary', 'bi-building'],

        [SessionStatus::class, 'Open', 'open', 'Dibuka', 'success', 'bi-door-open'],
        [SessionStatus::class, 'Closed', 'closed', 'Ditutup', 'secondary', 'bi-door-closed'],
        [SessionStatus::class, 'NeedsReview', 'needs_review', 'Perlu tindak lanjut', 'warning', 'bi-exclamation-triangle'],
        [SessionStatus::class, 'Cancelled', 'cancelled', 'Dibatalkan', 'danger', 'bi-x-octagon'],

        [ExceptionType::class, 'DoctorLeave', 'doctor_leave', 'Cuti dokter', 'warning', 'bi-person-dash'],
        [ExceptionType::class, 'HospitalHoliday', 'hospital_holiday', 'Libur rumah sakit', 'danger', 'bi-calendar-x'],
        [ExceptionType::class, 'NationalHoliday', 'national_holiday', 'Libur nasional', 'info', 'bi-flag'],
        [ExceptionType::class, 'Other', 'other', 'Lainnya', 'secondary', 'bi-three-dots'],

        [DoctorStatus::class, 'Active', 'active', 'Aktif', 'success', 'bi-check-circle'],
        [DoctorStatus::class, 'Inactive', 'inactive', 'Nonaktif', 'secondary', 'bi-pause-circle'],
        [DoctorStatus::class, 'Archived', 'archived', 'Diarsipkan', 'dark', 'bi-archive'],

        [ArticleStatus::class, 'Draft', 'draft', 'Draf', 'secondary', 'bi-pencil'],
        [ArticleStatus::class, 'Published', 'published', 'Terbit', 'success', 'bi-globe'],

        [ActorType::class, 'Patient', 'patient', 'Pasien', 'info', 'bi-person'],
        [ActorType::class, 'Staff', 'staff', 'Staf', 'primary', 'bi-person-badge'],
        [ActorType::class, 'System', 'system', 'Sistem', 'secondary', 'bi-gear'],

        [ContactMessageStatus::class, 'New', 'new', 'Baru', 'primary', 'bi-envelope'],
        [ContactMessageStatus::class, 'Read', 'read', 'Dibaca', 'secondary', 'bi-envelope-open'],
        [ContactMessageStatus::class, 'Replied', 'replied', 'Dibalas', 'success', 'bi-reply'],
        [ContactMessageStatus::class, 'Archived', 'archived', 'Diarsipkan', 'dark', 'bi-archive'],

        [SessionResolution::class, 'Keep', 'keep', 'Pertahankan sesi lama', 'secondary', 'bi-arrow-counterclockwise'],
        [SessionResolution::class, 'Apply', 'apply', 'Terapkan perubahan jam', 'primary', 'bi-clock-history'],
        [SessionResolution::class, 'Cancel', 'cancel', 'Batalkan sesi', 'danger', 'bi-x-circle'],

        [Availability::class, 'Available', 'available', 'Tersedia', 'success', 'bi-check-circle'],
        [Availability::class, 'Full', 'full', 'Penuh', 'danger', 'bi-people-fill'],
        [Availability::class, 'BookingClosed', 'booking_closed', 'Pendaftaran online ditutup', 'secondary', 'bi-lock'],
        [Availability::class, 'Closed', 'closed', 'Ditutup', 'secondary', 'bi-slash-circle'],
        [Availability::class, 'Leave', 'leave', 'Cuti', 'warning', 'bi-person-dash'],
        [Availability::class, 'Holiday', 'holiday', 'Libur', 'info', 'bi-calendar-event'],
        [Availability::class, 'Cancelled', 'cancelled', 'Dibatalkan', 'secondary', 'bi-x-circle'],
        [Availability::class, 'UnderReview', 'under_review', 'Sedang diperbarui', 'warning', 'bi-arrow-repeat'],
        [Availability::class, 'Past', 'past', 'Sudah lewat', 'secondary', 'bi-clock-history'],

        [AccountType::class, 'Patient', 'patient', 'Pasien', null, 'bi-person'],
        [AccountType::class, 'Staff', 'staff', 'Staf', null, 'bi-person-badge'],

        [Gender::class, 'Male', 'L', 'Laki-laki', null, 'bi-gender-male'],
        [Gender::class, 'Female', 'P', 'Perempuan', null, 'bi-gender-female'],

        [ClinicType::class, 'Poliklinik', 'poliklinik', 'Poliklinik', null, 'bi-hospital'],
        [ClinicType::class, 'Penunjang', 'penunjang', 'Layanan penunjang', null, 'bi-clipboard2-pulse'],

        [FacilityCategory::class, 'Medis', 'medis', 'Fasilitas medis', null, 'bi-heart-pulse'],
        [FacilityCategory::class, 'Penunjang', 'penunjang', 'Penunjang', null, 'bi-tools'],
        [FacilityCategory::class, 'Umum', 'umum', 'Fasilitas umum', null, 'bi-building'],
    ];

    private static function shortName(string $class): string
    {
        return substr($class, (int) strrpos($class, '\\') + 1);
    }

    /** @return array<string, array{0: class-string, 1: string, 2: string, 3: string, 4: ?string, 5: string}> */
    public static function catalog(): array
    {
        $rows = [];

        foreach (self::CATALOG as $row) {
            $rows[self::shortName($row[0]).'::'.$row[1]] = $row;
        }

        return $rows;
    }

    /** @return array<string, array{0: class-string}> */
    public static function enums(): array
    {
        $rows = [];

        foreach (array_unique(array_column(self::CATALOG, 0)) as $class) {
            $rows[self::shortName($class)] = [$class];
        }

        return $rows;
    }

    #[DataProvider('catalog')]
    public function test_case_matches_the_blueprint_table(string $enum, string $case, string $value, string $label, ?string $color, string $icon): void
    {
        $this->assertTrue(enum_exists($enum), "Enum {$enum} belum ada.");

        $instance = constant($enum.'::'.$case);

        $this->assertSame($value, $instance->value);
        $this->assertSame($label, $instance->label());
        $this->assertSame($icon, $instance->icon());
        $this->assertSame($instance, $enum::from($value));

        if ($color === null) {
            $this->assertFalse(method_exists($instance, 'color'), "{$enum} adalah enum label-saja dan tidak boleh punya color().");
        } else {
            $this->assertSame($color, $instance->color());
        }
    }

    #[DataProvider('enums')]
    public function test_enum_has_exactly_the_blueprint_cases(string $enum): void
    {
        $expected = array_values(array_map(
            static fn (array $row): string => $row[1],
            array_filter(self::CATALOG, static fn (array $row): bool => $row[0] === $enum),
        ));

        $actual = array_map(static fn ($case): string => $case->name, $enum::cases());

        $this->assertSame($expected, $actual);
    }

    #[DataProvider('enums')]
    public function test_values_and_options_follow_case_order(string $enum): void
    {
        $rows = array_values(array_filter(self::CATALOG, static fn (array $row): bool => $row[0] === $enum));

        $this->assertSame(array_column($rows, 2), $enum::values());
        $this->assertSame(array_combine(array_column($rows, 2), array_column($rows, 3)), $enum::options());
    }

    #[DataProvider('enums')]
    public function test_enum_is_string_backed_and_uses_has_options(string $enum): void
    {
        $this->assertSame('string', (new ReflectionEnum($enum))->getBackingType()?->getName());
        $this->assertContains(HasOptions::class, class_uses($enum));
    }

    public function test_catalog_covers_exactly_the_fifteen_blueprint_enums(): void
    {
        $this->assertCount(15, self::enums());

        $files = glob(dirname(__DIR__, 3).'/app/Enums/*.php');

        $this->assertCount(15, $files, 'app/Enums harus berisi tepat 15 enum.');
    }

    public function test_gender_uses_the_single_letter_codes(): void
    {
        $this->assertSame(['L', 'P'], Gender::values());
    }

    public function test_helper_methods_follow_the_blueprint(): void
    {
        $this->assertTrue(PaymentType::Bpjs->requiresBpjsNumber());
        $this->assertFalse(PaymentType::Umum->requiresBpjsNumber());
        $this->assertFalse(PaymentType::Asuransi->requiresBpjsNumber());
        $this->assertTrue(PaymentType::Asuransi->requiresInsurancePartner());
        $this->assertFalse(PaymentType::Bpjs->requiresInsurancePartner());
        $this->assertTrue(PaymentType::Asuransi->allowsPolicyNumber());
        $this->assertFalse(PaymentType::Umum->allowsPolicyNumber());

        $this->assertTrue(SessionStatus::Open->isOpen());
        $this->assertFalse(SessionStatus::Open->blocksNewBookings());

        foreach ([SessionStatus::Closed, SessionStatus::NeedsReview, SessionStatus::Cancelled] as $status) {
            $this->assertFalse($status->isOpen());
            $this->assertTrue($status->blocksNewBookings());
        }

        $this->assertTrue(ExceptionType::DoctorLeave->requiresDoctor());
        $this->assertFalse(ExceptionType::HospitalHoliday->requiresDoctor());
        $this->assertFalse(ExceptionType::NationalHoliday->requiresDoctor());
        $this->assertFalse(ExceptionType::Other->requiresDoctor());
        $this->assertTrue(ExceptionType::HospitalHoliday->forbidsDoctor());
        $this->assertTrue(ExceptionType::NationalHoliday->forbidsDoctor());
        $this->assertFalse(ExceptionType::DoctorLeave->forbidsDoctor());
        $this->assertFalse(ExceptionType::Other->forbidsDoctor());

        $this->assertTrue(DoctorStatus::Active->isActive());
        $this->assertFalse(DoctorStatus::Inactive->isActive());
        $this->assertFalse(DoctorStatus::Archived->isActive());

        $this->assertTrue(AccountType::Staff->isStaff());
        $this->assertFalse(AccountType::Staff->isPatient());
        $this->assertTrue(AccountType::Patient->isPatient());
        $this->assertFalse(AccountType::Patient->isStaff());
    }

    public function test_availability_helpers_follow_the_blueprint(): void
    {
        foreach (Availability::cases() as $availability) {
            $this->assertSame($availability === Availability::Available, $availability->isBookable(), $availability->name);
        }

        $this->assertSame(Availability::Leave, Availability::forException(ExceptionType::DoctorLeave));
        $this->assertSame(Availability::Holiday, Availability::forException(ExceptionType::HospitalHoliday));
        $this->assertSame(Availability::Holiday, Availability::forException(ExceptionType::NationalHoliday));
        $this->assertSame(Availability::Cancelled, Availability::forException(ExceptionType::Other));
        $this->assertSame(Availability::Cancelled, Availability::forException(null));
    }
}
```

- [ ] **Step 3: Tulis tes yang gagal `tests/Unit/Enums/AppointmentStatusTest.php`**

```php
<?php

namespace Tests\Unit\Enums;

use App\Enums\AppointmentStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AppointmentStatusTest extends TestCase
{
    /** @return array<string, array{0: AppointmentStatus, 1: bool, 2: bool, 3: bool}> */
    public static function classification(): array
    {
        // status, isActive, consumesQuota, isFinal  (00 D / 10 §4.1)
        return [
            'pending' => [AppointmentStatus::Pending, true, true, false],
            'confirmed' => [AppointmentStatus::Confirmed, true, true, false],
            'checked_in' => [AppointmentStatus::CheckedIn, true, true, false],
            'completed' => [AppointmentStatus::Completed, false, true, true],
            'cancelled' => [AppointmentStatus::Cancelled, false, false, true],
            'no_show' => [AppointmentStatus::NoShow, false, true, true],
        ];
    }

    #[DataProvider('classification')]
    public function test_status_classification(AppointmentStatus $status, bool $active, bool $consumesQuota, bool $final): void
    {
        $this->assertSame($active, $status->isActive());
        $this->assertSame($consumesQuota, $status->consumesQuota());
        $this->assertSame($final, $status->isFinal());
    }

    public function test_constant_sets_follow_the_blueprint(): void
    {
        $this->assertSame(
            [AppointmentStatus::Pending, AppointmentStatus::Confirmed, AppointmentStatus::CheckedIn],
            AppointmentStatus::ACTIVE,
        );
        $this->assertSame(
            [
                AppointmentStatus::Pending,
                AppointmentStatus::Confirmed,
                AppointmentStatus::CheckedIn,
                AppointmentStatus::Completed,
                AppointmentStatus::NoShow,
            ],
            AppointmentStatus::QUOTA_CONSUMING,
        );
        $this->assertSame(
            [AppointmentStatus::Completed, AppointmentStatus::Cancelled, AppointmentStatus::NoShow],
            AppointmentStatus::FINAL,
        );
    }

    public function test_static_helpers_return_the_constant_sets(): void
    {
        $this->assertSame(AppointmentStatus::ACTIVE, AppointmentStatus::active());
        $this->assertSame(AppointmentStatus::QUOTA_CONSUMING, AppointmentStatus::quotaConsuming());
        $this->assertSame(AppointmentStatus::FINAL, AppointmentStatus::finals());
    }

    public function test_quota_consuming_is_every_status_except_cancelled(): void
    {
        $expected = array_values(array_filter(
            AppointmentStatus::cases(),
            static fn (AppointmentStatus $status): bool => $status !== AppointmentStatus::Cancelled,
        ));

        $this->assertSame($expected, AppointmentStatus::QUOTA_CONSUMING);
    }

    public function test_active_and_final_sets_never_overlap_and_cover_every_status(): void
    {
        $this->assertSame([], array_values(array_filter(
            AppointmentStatus::ACTIVE,
            static fn (AppointmentStatus $status): bool => in_array($status, AppointmentStatus::FINAL, true),
        )));

        $this->assertCount(
            count(AppointmentStatus::cases()),
            [...AppointmentStatus::ACTIVE, ...AppointmentStatus::FINAL],
        );
    }
}
```

- [ ] **Step 4: Tulis tes yang gagal `tests/Unit/Enums/SessionResolutionTest.php`**

```php
<?php

namespace Tests\Unit\Enums;

use App\Enums\SessionResolution;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SessionResolutionTest extends TestCase
{
    /** @return array<string, array{0: string, 1: list<SessionResolution>}> */
    public static function allowedBySource(): array
    {
        // Peta statis 00 F.4 / 10 §4.2
        return [
            'exception' => ['exception', [SessionResolution::Keep, SessionResolution::Cancel]],
            'template_time' => ['template_time', [SessionResolution::Keep, SessionResolution::Apply, SessionResolution::Cancel]],
            'template_removed' => ['template_removed', [SessionResolution::Keep, SessionResolution::Cancel]],
            'manual_time' => ['manual_time', [SessionResolution::Keep, SessionResolution::Apply, SessionResolution::Cancel]],
            'manual_cancel' => ['manual_cancel', [SessionResolution::Keep, SessionResolution::Cancel]],
            'doctor_inactive' => ['doctor_inactive', [SessionResolution::Keep, SessionResolution::Cancel]],
        ];
    }

    /** @param  list<SessionResolution>  $expected */
    #[DataProvider('allowedBySource')]
    public function test_allowed_resolutions_per_source(string $source, array $expected): void
    {
        $this->assertSame($expected, SessionResolution::allowedFor($source));
    }

    public function test_unknown_source_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SessionResolution::allowedFor('sumber_ngawur');
    }

    public function test_only_cancel_requires_a_reason(): void
    {
        $this->assertTrue(SessionResolution::Cancel->requiresReason());
        $this->assertFalse(SessionResolution::Keep->requiresReason());
        $this->assertFalse(SessionResolution::Apply->requiresReason());
    }

    public function test_every_resolution_has_a_distinct_indonesian_description(): void
    {
        $descriptions = array_map(
            static fn (SessionResolution $resolution): string => $resolution->description(),
            SessionResolution::cases(),
        );

        foreach ($descriptions as $description) {
            $this->assertNotSame('', trim($description));
        }

        $this->assertCount(count(SessionResolution::cases()), array_unique($descriptions));
    }
}
```

- [ ] **Step 5: Jalankan tes — harus GAGAL**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=Enums
```
Expected: FAIL — `EnumCatalogTest` gagal `Enum App\Enums\AppointmentStatus belum ada.` (`Failed asserting that false is true`) untuk setiap baris katalog; `AppointmentStatusTest` dan `SessionResolutionTest` gagal `Error: Class "App\Enums\AppointmentStatus" not found` / `Class "App\Enums\SessionResolution" not found`. Tidak ada tes yang lolos selain mungkin `test_catalog_covers_exactly_the_fifteen_blueprint_enums` (gagal pada `glob`: 0 berkas).

- [ ] **Step 6: Buat trait `app/Enums/Concerns/HasOptions.php`**

```php
<?php

namespace App\Enums\Concerns;

/**
 * Pembantu untuk enum string-backed: nilai mentah (Rule::enum, whereIn) dan opsi <select>.
 * Enum pemakai wajib punya method label(): string.
 */
trait HasOptions
{
    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    /**
     * @return array<string, string> nilai => label
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
```

- [ ] **Step 7: Buat empat enum label-saja (`AccountType`, `Gender`, `ClinicType`, `FacilityCategory`)**

`app/Enums/AccountType.php`:

```php
<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum AccountType: string
{
    use HasOptions;

    case Patient = 'patient';
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::Patient => 'Pasien',
            self::Staff => 'Staf',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Patient => 'bi-person',
            self::Staff => 'bi-person-badge',
        };
    }

    public function isStaff(): bool
    {
        return $this === self::Staff;
    }

    public function isPatient(): bool
    {
        return $this === self::Patient;
    }
}
```

`app/Enums/Gender.php`:

```php
<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum Gender: string
{
    use HasOptions;

    case Male = 'L';
    case Female = 'P';

    public function label(): string
    {
        return match ($this) {
            self::Male => 'Laki-laki',
            self::Female => 'Perempuan',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Male => 'bi-gender-male',
            self::Female => 'bi-gender-female',
        };
    }
}
```

`app/Enums/ClinicType.php`:

```php
<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ClinicType: string
{
    use HasOptions;

    case Poliklinik = 'poliklinik';
    case Penunjang = 'penunjang';

    public function label(): string
    {
        return match ($this) {
            self::Poliklinik => 'Poliklinik',
            self::Penunjang => 'Layanan penunjang',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Poliklinik => 'bi-hospital',
            self::Penunjang => 'bi-clipboard2-pulse',
        };
    }
}
```

`app/Enums/FacilityCategory.php`:

```php
<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum FacilityCategory: string
{
    use HasOptions;

    case Medis = 'medis';
    case Penunjang = 'penunjang';
    case Umum = 'umum';

    public function label(): string
    {
        return match ($this) {
            self::Medis => 'Fasilitas medis',
            self::Penunjang => 'Penunjang',
            self::Umum => 'Fasilitas umum',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Medis => 'bi-heart-pulse',
            self::Penunjang => 'bi-tools',
            self::Umum => 'bi-building',
        };
    }
}
```

- [ ] **Step 8: Buat `app/Enums/AppointmentStatus.php`**

```php
<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum AppointmentStatus: string
{
    use HasOptions;

    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case CheckedIn = 'checked_in';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    /** Status "aktif": pendaftaran masih berjalan (00 D). Wajib selaras dengan kolom generated active_flag. */
    public const ACTIVE = [self::Pending, self::Confirmed, self::CheckedIn];

    /** Semua status kecuali Cancelled: masih memakai kuota sesi. */
    public const QUOTA_CONSUMING = [self::Pending, self::Confirmed, self::CheckedIn, self::Completed, self::NoShow];

    /** Status akhir: tidak ada transisi lanjutan biasa. */
    public const FINAL = [self::Completed, self::Cancelled, self::NoShow];

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu konfirmasi',
            self::Confirmed => 'Dikonfirmasi',
            self::CheckedIn => 'Hadir/check-in',
            self::Completed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
            self::NoShow => 'Tidak hadir',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Confirmed => 'primary',
            self::CheckedIn => 'info',
            self::Completed => 'success',
            self::Cancelled => 'secondary',
            self::NoShow => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'bi-hourglass-split',
            self::Confirmed => 'bi-check-circle',
            self::CheckedIn => 'bi-person-check',
            self::Completed => 'bi-check2-all',
            self::Cancelled => 'bi-x-circle',
            self::NoShow => 'bi-person-x',
        };
    }

    /** @return list<self> */
    public static function active(): array
    {
        return self::ACTIVE;
    }

    /** @return list<self> */
    public static function quotaConsuming(): array
    {
        return self::QUOTA_CONSUMING;
    }

    /** @return list<self> */
    public static function finals(): array
    {
        return self::FINAL;
    }

    public function isActive(): bool
    {
        return in_array($this, self::ACTIVE, true);
    }

    public function consumesQuota(): bool
    {
        return in_array($this, self::QUOTA_CONSUMING, true);
    }

    public function isFinal(): bool
    {
        return in_array($this, self::FINAL, true);
    }
}
```

- [ ] **Step 9: Buat `PaymentType` dan `BookingChannel`**

`app/Enums/PaymentType.php`:

```php
<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum PaymentType: string
{
    use HasOptions;

    case Umum = 'umum';
    case Bpjs = 'bpjs';
    case Asuransi = 'asuransi';

    public function label(): string
    {
        return match ($this) {
            self::Umum => 'Umum/Pribadi',
            self::Bpjs => 'BPJS Kesehatan',
            self::Asuransi => 'Asuransi',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Umum => 'secondary',
            self::Bpjs => 'success',
            self::Asuransi => 'primary',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Umum => 'bi-wallet2',
            self::Bpjs => 'bi-shield-plus',
            self::Asuransi => 'bi-shield-check',
        };
    }

    public function requiresBpjsNumber(): bool
    {
        return $this === self::Bpjs;
    }

    public function requiresInsurancePartner(): bool
    {
        return $this === self::Asuransi;
    }

    public function allowsPolicyNumber(): bool
    {
        return $this === self::Asuransi;
    }
}
```

`app/Enums/BookingChannel.php`:

```php
<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum BookingChannel: string
{
    use HasOptions;

    case Online = 'online';
    case Loket = 'loket';

    public function label(): string
    {
        return match ($this) {
            self::Online => 'Online',
            self::Loket => 'Loket',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Online => 'info',
            self::Loket => 'secondary',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Online => 'bi-globe2',
            self::Loket => 'bi-building',
        };
    }
}
```

- [ ] **Step 10: Buat `SessionStatus` dan `ExceptionType`**

`app/Enums/SessionStatus.php`:

```php
<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum SessionStatus: string
{
    use HasOptions;

    case Open = 'open';
    case Closed = 'closed';
    case NeedsReview = 'needs_review';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Dibuka',
            self::Closed => 'Ditutup',
            self::NeedsReview => 'Perlu tindak lanjut',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'success',
            self::Closed => 'secondary',
            self::NeedsReview => 'warning',
            self::Cancelled => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Open => 'bi-door-open',
            self::Closed => 'bi-door-closed',
            self::NeedsReview => 'bi-exclamation-triangle',
            self::Cancelled => 'bi-x-octagon',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Open;
    }

    /** Semua status selain Open menolak pendaftaran baru. */
    public function blocksNewBookings(): bool
    {
        return $this !== self::Open;
    }
}
```

`app/Enums/ExceptionType.php`:

```php
<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ExceptionType: string
{
    use HasOptions;

    case DoctorLeave = 'doctor_leave';
    case HospitalHoliday = 'hospital_holiday';
    case NationalHoliday = 'national_holiday';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::DoctorLeave => 'Cuti dokter',
            self::HospitalHoliday => 'Libur rumah sakit',
            self::NationalHoliday => 'Libur nasional',
            self::Other => 'Lainnya',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DoctorLeave => 'warning',
            self::HospitalHoliday => 'danger',
            self::NationalHoliday => 'info',
            self::Other => 'secondary',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::DoctorLeave => 'bi-person-dash',
            self::HospitalHoliday => 'bi-calendar-x',
            self::NationalHoliday => 'bi-flag',
            self::Other => 'bi-three-dots',
        };
    }

    /** Cuti dokter wajib menyebut dokter (00 E.2, CHECK chk_schedule_exceptions_scope). */
    public function requiresDoctor(): bool
    {
        return $this === self::DoctorLeave;
    }

    /** Libur rumah sakit/nasional berlaku untuk semua dokter; "Lainnya" bebas. */
    public function forbidsDoctor(): bool
    {
        return $this === self::HospitalHoliday || $this === self::NationalHoliday;
    }
}
```

- [ ] **Step 11: Buat `DoctorStatus`, `ArticleStatus`, `ActorType`, `ContactMessageStatus`**

`app/Enums/DoctorStatus.php`:

```php
<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum DoctorStatus: string
{
    use HasOptions;

    case Active = 'active';
    case Inactive = 'inactive';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Inactive => 'Nonaktif',
            self::Archived => 'Diarsipkan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Inactive => 'secondary',
            self::Archived => 'dark',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Active => 'bi-check-circle',
            self::Inactive => 'bi-pause-circle',
            self::Archived => 'bi-archive',
        };
    }

    public function isActive(): bool
    {
        return $this === self::Active;
    }
}
```

`app/Enums/ArticleStatus.php`:

```php
<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ArticleStatus: string
{
    use HasOptions;

    case Draft = 'draft';
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Published => 'Terbit',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'secondary',
            self::Published => 'success',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Draft => 'bi-pencil',
            self::Published => 'bi-globe',
        };
    }
}
```

`app/Enums/ActorType.php`:

```php
<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use App\Models\User;

enum ActorType: string
{
    use HasOptions;

    case Patient = 'patient';
    case Staff = 'staff';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Patient => 'Pasien',
            self::Staff => 'Staf',
            self::System => 'Sistem',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Patient => 'info',
            self::Staff => 'primary',
            self::System => 'secondary',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Patient => 'bi-person',
            self::Staff => 'bi-person-badge',
            self::System => 'bi-gear',
        };
    }

    /** Pelaku menurut jenis akun: staf ⇒ Staff, selain itu Patient. Pelaku sistem tidak berasal dari User. */
    public static function fromUser(User $user): self
    {
        return $user->account_type === AccountType::Staff ? self::Staff : self::Patient;
    }
}
```

`app/Enums/ContactMessageStatus.php`:

```php
<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ContactMessageStatus: string
{
    use HasOptions;

    case New = 'new';
    case Read = 'read';
    case Replied = 'replied';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Baru',
            self::Read => 'Dibaca',
            self::Replied => 'Dibalas',
            self::Archived => 'Diarsipkan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::New => 'primary',
            self::Read => 'secondary',
            self::Replied => 'success',
            self::Archived => 'dark',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::New => 'bi-envelope',
            self::Read => 'bi-envelope-open',
            self::Replied => 'bi-reply',
            self::Archived => 'bi-archive',
        };
    }
}
```

- [ ] **Step 12: Buat `SessionResolution` dan `Availability`**

`app/Enums/SessionResolution.php`:

```php
<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use InvalidArgumentException;

enum SessionResolution: string
{
    use HasOptions;

    case Keep = 'keep';
    case Apply = 'apply';
    case Cancel = 'cancel';

    public function label(): string
    {
        return match ($this) {
            self::Keep => 'Pertahankan sesi lama',
            self::Apply => 'Terapkan perubahan jam',
            self::Cancel => 'Batalkan sesi',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Keep => 'secondary',
            self::Apply => 'primary',
            self::Cancel => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Keep => 'bi-arrow-counterclockwise',
            self::Apply => 'bi-clock-history',
            self::Cancel => 'bi-x-circle',
        };
    }

    /**
     * Resolusi yang diizinkan per sumber perubahan sesi (00 F.4).
     * Penyaringan lanjutan (sesi lampau, dokter nonaktif) dilakukan ScheduleChangeService di fase 7.
     *
     * @return list<self>
     */
    public static function allowedFor(string $source): array
    {
        return match ($source) {
            'exception', 'template_removed', 'manual_cancel', 'doctor_inactive' => [self::Keep, self::Cancel],
            'template_time', 'manual_time' => [self::Keep, self::Apply, self::Cancel],
            default => throw new InvalidArgumentException("Sumber perubahan sesi tidak dikenal: {$source}."),
        };
    }

    /** Hanya pembatalan sesi yang wajib disertai alasan. */
    public function requiresReason(): bool
    {
        return $this === self::Cancel;
    }

    /** Penjelasan singkat untuk layar penyelesaian sesi "perlu tindak lanjut". */
    public function description(): string
    {
        return match ($this) {
            self::Keep => 'Sesi tetap berjalan seperti semula. Perubahan jadwal tidak diterapkan pada sesi ini dan pendaftaran yang ada tidak berubah.',
            self::Apply => 'Jam sesi diubah mengikuti jadwal baru. Pasien yang sudah mendaftar diberi tahu tentang perubahan jam.',
            self::Cancel => 'Sesi dibatalkan dan seluruh pendaftaran aktif pada sesi ini ikut dibatalkan. Alasan pembatalan wajib diisi.',
        };
    }
}
```

`app/Enums/Availability.php`:

```php
<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Hasil DoctorAvailabilityQuery per sesi. Tidak disimpan di database (00 D).
 */
enum Availability: string
{
    use HasOptions;

    case Available = 'available';
    case Full = 'full';
    case BookingClosed = 'booking_closed';
    case Closed = 'closed';
    case Leave = 'leave';
    case Holiday = 'holiday';
    case Cancelled = 'cancelled';
    case UnderReview = 'under_review';
    case Past = 'past';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Tersedia',
            self::Full => 'Penuh',
            self::BookingClosed => 'Pendaftaran online ditutup',
            self::Closed => 'Ditutup',
            self::Leave => 'Cuti',
            self::Holiday => 'Libur',
            self::Cancelled => 'Dibatalkan',
            self::UnderReview => 'Sedang diperbarui',
            self::Past => 'Sudah lewat',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Available => 'success',
            self::Full => 'danger',
            self::BookingClosed => 'secondary',
            self::Closed => 'secondary',
            self::Leave => 'warning',
            self::Holiday => 'info',
            self::Cancelled => 'secondary',
            self::UnderReview => 'warning',
            self::Past => 'secondary',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Available => 'bi-check-circle',
            self::Full => 'bi-people-fill',
            self::BookingClosed => 'bi-lock',
            self::Closed => 'bi-slash-circle',
            self::Leave => 'bi-person-dash',
            self::Holiday => 'bi-calendar-event',
            self::Cancelled => 'bi-x-circle',
            self::UnderReview => 'bi-arrow-repeat',
            self::Past => 'bi-clock-history',
        };
    }

    public function isBookable(): bool
    {
        return $this === self::Available;
    }

    /** Ketersediaan untuk sesi yang ditutup karena pengecualian jadwal (00 F.3: selain cuti/libur ⇒ cancelled). */
    public static function forException(?ExceptionType $type): self
    {
        return match ($type) {
            ExceptionType::DoctorLeave => self::Leave,
            ExceptionType::HospitalHoliday, ExceptionType::NationalHoliday => self::Holiday,
            ExceptionType::Other, null => self::Cancelled,
        };
    }
}
```

- [ ] **Step 13: Jalankan tes — harus LULUS**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=Enums
```
Expected: PASS — seluruh tes di `EnumCatalogTest` (52 baris katalog + tes struktur), `AppointmentStatusTest`, dan `SessionResolutionTest` lulus; `Tests: N passed`, tanpa failure/error. Bila ada baris katalog gagal, bandingkan label/color/icon enum dengan 10 §4 — jangan mengubah tes.

- [ ] **Step 14: Jalankan seluruh suite dan Pint**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test
& $PHP vendor\bin\pint --test
```
Expected: `artisan test` hijau (MySQL harus menyala untuk tes fase 0 yang menyentuh DB). `pint --test` berakhir `PASS`. Bila `FAIL` (urutan `use`, spasi), jalankan `& $PHP vendor\bin\pint` lalu ulangi `--test` sampai `PASS`.

- [ ] **Step 15: Commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git add app/Enums/Concerns/HasOptions.php app/Enums/AccountType.php app/Enums/AppointmentStatus.php app/Enums/PaymentType.php app/Enums/BookingChannel.php app/Enums/SessionStatus.php app/Enums/ExceptionType.php app/Enums/DoctorStatus.php app/Enums/Gender.php app/Enums/ClinicType.php app/Enums/FacilityCategory.php app/Enums/ArticleStatus.php app/Enums/ActorType.php app/Enums/ContactMessageStatus.php app/Enums/SessionResolution.php app/Enums/Availability.php tests/Unit/Enums/EnumCatalogTest.php tests/Unit/Enums/AppointmentStatusTest.php tests/Unit/Enums/SessionResolutionTest.php
git commit -m "feat(fase-1): katalog 15 enum dengan label, warna, ikon, dan trait HasOptions"
```

---

### Task 2: Skema akun — kolom `users`, tabel `notifications`, model `User`, factory

**Files:**
- Create: `database/migrations/2026_10_02_000010_add_account_fields_to_users_table.php`
- Create: `database/migrations/2026_10_02_000030_create_notifications_table.php` (hasil `artisan make:notifications-table` lalu diganti nama)
- Modify: `app/Models/User.php`, `database/factories/UserFactory.php`, `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/Models/UserAccountTest.php`

**Interfaces:**
- Consumes: `AccountType`, `ActorType` (Task 1).
- Produces: kolom `users` 09 §2.1 (`phone` VARCHAR(20) NULL, `account_type` VARCHAR(10) NOT NULL tanpa default, `is_active` TINYINT(1) default 1, `must_change_password` TINYINT(1) default 0, `last_login_at` DATETIME NULL); tabel `notifications` bawaan. `User`: `$fillable = ['name','email','phone','password']`; `$hidden = ['password','remember_token']`; casts `email_verified_at`⇒datetime, `password`⇒hashed, `account_type`⇒`AccountType::class`, `is_active`⇒boolean, `must_change_password`⇒boolean, `last_login_at`⇒datetime; scope `staff()`, `patients()`, `active()`; `isStaff(): bool`, `isPatient(): bool`. `UserFactory` default = pasien aktif (`account_type` Patient, `is_active` true, `must_change_password` false, `phone` null) + state `staff()`, `patient()`, `inactive()`, `mustChangePassword()` (dan `unverified()` bawaan). `AppServiceProvider::boot()`: `Model::shouldBeStrict(! $this->app->isProduction());`.
- Catatan verifikasi API (Laravel 12.69): `Schema::getColumns($table)` mengembalikan list array dengan kunci `name`, `type_name` (mis. `varchar`), `type` (tipe lengkap mis. `varchar(20)`), `collation`, `nullable` (bool), `default` (string mentah dari `information_schema`; pada MariaDB 10.4 default NULL dapat berupa string `NULL` dan default string diberi kutip, sehingga tes menormalkannya), `auto_increment`, `comment`, `generation`. `Schema::getIndexes($table)` → `name`, `columns`, `type`, `unique`, `primary`. `MariaDbGrammar::typeUuid()` memberi `char(36)` untuk server < 10.7, jadi `notifications.id` = `char(36)` di MariaDB 10.4.
- Catatan mode ketat: `preventLazyLoading` hanya menandai model yang dimuat dalam hasil **lebih dari satu baris** (`Builder::hydrate`), sehingga pelanggaran di tes terlihat pada koleksi ≥ 2 model. `preventSilentlyDiscardingAttributes` membuat isian massal ke kolom non-`$fillable` melempar `MassAssignmentException`; factory memakai `Model::unguarded()` sehingga tetap dapat mengisi `account_type`.

**Test IDs:** `SEK-01` (bagian: password tersimpan hash bcrypt `$2y$`, lolos `Hash::check`, tidak ada di `toArray()`), `KNS-04` (bagian: kolom `users` sesuai 09 via `Schema::getColumns`).

- [ ] **Step 1: Tulis tes yang gagal `tests/Feature/Models/UserAccountTest.php`**

```php
<?php

namespace Tests\Feature\Models;

use App\Enums\AccountType;
use App\Enums\ActorType;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\MissingAttributeException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserAccountTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 09-data-dictionary.md §2.1.
     * Kolom: nama kolom, type_name, tipe lengkap, nullable, default (setelah normalisasi).
     *
     * @return array<string, array{0: string, 1: string, 2: string, 3: bool, 4: ?string}>
     */
    public static function expectedUserColumns(): array
    {
        return [
            'name' => ['name', 'varchar', 'varchar(255)', false, null],
            'email' => ['email', 'varchar', 'varchar(255)', false, null],
            'phone' => ['phone', 'varchar', 'varchar(20)', true, null],
            'email_verified_at' => ['email_verified_at', 'timestamp', 'timestamp', true, null],
            'password' => ['password', 'varchar', 'varchar(255)', false, null],
            'account_type' => ['account_type', 'varchar', 'varchar(10)', false, null],
            'is_active' => ['is_active', 'tinyint', 'tinyint(1)', false, '1'],
            'must_change_password' => ['must_change_password', 'tinyint', 'tinyint(1)', false, '0'],
            'last_login_at' => ['last_login_at', 'datetime', 'datetime', true, null],
            'remember_token' => ['remember_token', 'varchar', 'varchar(100)', true, null],
            'created_at' => ['created_at', 'timestamp', 'timestamp', true, null],
            'updated_at' => ['updated_at', 'timestamp', 'timestamp', true, null],
        ];
    }

    /** @return array<string, array{0: string, 1: mixed}> */
    public static function guardedAccountFields(): array
    {
        return [
            'account_type' => ['account_type', 'staff'],
            'is_active' => ['is_active', false],
            'must_change_password' => ['must_change_password', true],
            'last_login_at' => ['last_login_at', '2026-10-03 08:00:00'],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function columnsOf(string $table): array
    {
        $columns = [];

        foreach (Schema::getColumns($table) as $column) {
            $columns[$column['name']] = $column;
        }

        return $columns;
    }

    /** MariaDB 10.4 melaporkan default NULL sebagai string "NULL" dan default string dengan kutip. */
    private function normalizedDefault(?string $default): ?string
    {
        if ($default === null || strtoupper($default) === 'NULL') {
            return null;
        }

        return trim($default, "'");
    }

    #[DataProvider('expectedUserColumns')]
    public function test_schema_users_column_matches_the_data_dictionary(string $name, string $typeName, string $type, bool $nullable, ?string $default): void
    {
        $columns = $this->columnsOf('users');

        $this->assertArrayHasKey($name, $columns, "Kolom users.{$name} belum ada.");
        $this->assertSame($typeName, $columns[$name]['type_name'], "type_name users.{$name}");
        $this->assertSame($type, $columns[$name]['type'], "type users.{$name}");
        $this->assertSame($nullable, $columns[$name]['nullable'], "nullable users.{$name}");
        $this->assertSame($default, $this->normalizedDefault($columns[$name]['default']), "default users.{$name}");
    }

    public function test_schema_users_primary_key_and_new_columns_exist(): void
    {
        $columns = $this->columnsOf('users');

        $this->assertSame('bigint', $columns['id']['type_name']);
        $this->assertStringContainsString('unsigned', $columns['id']['type']);
        $this->assertTrue($columns['id']['auto_increment']);

        $this->assertTrue(Schema::hasColumns('users', ['phone', 'account_type', 'is_active', 'must_change_password', 'last_login_at']));
    }

    public function test_schema_users_email_stays_unique(): void
    {
        $indexes = [];

        foreach (Schema::getIndexes('users') as $index) {
            $indexes[$index['name']] = $index;
        }

        $this->assertArrayHasKey('users_email_unique', $indexes);
        $this->assertTrue($indexes['users_email_unique']['unique']);
        $this->assertSame(['email'], $indexes['users_email_unique']['columns']);
    }

    public function test_schema_notifications_table_is_the_laravel_default(): void
    {
        $columns = $this->columnsOf('notifications');

        $this->assertSame(
            ['id', 'type', 'notifiable_type', 'notifiable_id', 'data', 'read_at', 'created_at', 'updated_at'],
            array_keys($columns),
        );

        $this->assertSame('char(36)', $columns['id']['type']);
        $this->assertSame('varchar(255)', $columns['type']['type']);
        $this->assertSame('varchar(255)', $columns['notifiable_type']['type']);
        $this->assertSame('bigint', $columns['notifiable_id']['type_name']);
        $this->assertStringContainsString('unsigned', $columns['notifiable_id']['type']);
        $this->assertSame('text', $columns['data']['type_name']);
        $this->assertFalse($columns['data']['nullable']);
        $this->assertTrue($columns['read_at']['nullable']);

        $indexes = [];

        foreach (Schema::getIndexes('notifications') as $index) {
            $indexes[$index['name']] = $index;
        }

        $this->assertTrue($indexes['primary']['primary']);
        $this->assertSame(['id'], $indexes['primary']['columns']);
        $this->assertSame(
            ['notifiable_type', 'notifiable_id'],
            $indexes['notifications_notifiable_type_notifiable_id_index']['columns'] ?? null,
        );
    }

    public function test_password_is_stored_as_a_bcrypt_hash_and_never_serialized(): void
    {
        $user = User::factory()->create(['password' => 'Rahasia123']);

        $stored = DB::table('users')->where('id', $user->id)->value('password');

        $this->assertNotSame('Rahasia123', $stored);
        $this->assertStringStartsWith('$2y$', $stored);
        $this->assertTrue(Hash::check('Rahasia123', $stored));
        $this->assertFalse(Hash::check('Rahasia124', $stored));

        $array = $user->toArray();

        $this->assertArrayNotHasKey('password', $array);
        $this->assertArrayNotHasKey('remember_token', $array);
        $this->assertStringNotContainsString('Rahasia123', $user->toJson());
        $this->assertStringNotContainsString($stored, $user->toJson());
    }

    #[DataProvider('guardedAccountFields')]
    public function test_account_fields_are_not_mass_assignable(string $field, mixed $value): void
    {
        $this->expectException(MassAssignmentException::class);

        User::create([
            'name' => 'Uji Massal',
            'email' => 'massal@unpamhospital.test',
            'password' => 'Rahasia123',
            $field => $value,
        ]);
    }

    public function test_fillable_and_hidden_follow_the_blueprint(): void
    {
        $user = new User;

        $this->assertSame(['name', 'email', 'phone', 'password'], $user->getFillable());
        $this->assertSame(['password', 'remember_token'], $user->getHidden());
    }

    public function test_casts_follow_the_blueprint(): void
    {
        $user = User::factory()->staff()->mustChangePassword()->create();
        $user->forceFill(['last_login_at' => '2026-10-03 08:15:00'])->save();

        $fresh = User::query()->findOrFail($user->id);

        $this->assertSame(AccountType::Staff, $fresh->account_type);
        $this->assertSame(true, $fresh->is_active);
        $this->assertSame(true, $fresh->must_change_password);
        $this->assertInstanceOf(CarbonInterface::class, $fresh->email_verified_at);
        $this->assertInstanceOf(CarbonInterface::class, $fresh->last_login_at);
        $this->assertSame('2026-10-03 08:15:00', $fresh->last_login_at->format('Y-m-d H:i:s'));
    }

    public function test_factory_defaults_to_an_active_patient(): void
    {
        $user = User::factory()->create();

        $this->assertSame(AccountType::Patient, $user->account_type);
        $this->assertTrue($user->is_active);
        $this->assertFalse($user->must_change_password);
        $this->assertNull($user->phone);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_factory_states(): void
    {
        $this->assertSame(AccountType::Staff, User::factory()->staff()->create()->account_type);
        $this->assertSame(AccountType::Patient, User::factory()->staff()->patient()->create()->account_type);
        $this->assertFalse(User::factory()->inactive()->create()->is_active);
        $this->assertTrue(User::factory()->mustChangePassword()->create()->must_change_password);
        $this->assertNull(User::factory()->unverified()->create()->email_verified_at);

        $combined = User::factory()->staff()->inactive()->mustChangePassword()->create();

        $this->assertSame(AccountType::Staff, $combined->account_type);
        $this->assertFalse($combined->is_active);
        $this->assertTrue($combined->must_change_password);
    }

    public function test_scopes_filter_by_account_type_and_activity(): void
    {
        User::factory()->staff()->count(2)->create();
        User::factory()->count(3)->create();
        User::factory()->staff()->inactive()->create();

        $this->assertSame(3, User::query()->staff()->count());
        $this->assertSame(3, User::query()->patients()->count());
        $this->assertSame(5, User::query()->active()->count());
        $this->assertSame(2, User::query()->staff()->active()->count());
    }

    public function test_is_staff_and_is_patient_helpers(): void
    {
        $staff = User::factory()->staff()->create();
        $patient = User::factory()->patient()->create();

        $this->assertTrue($staff->isStaff());
        $this->assertFalse($staff->isPatient());
        $this->assertTrue($patient->isPatient());
        $this->assertFalse($patient->isStaff());
    }

    public function test_actor_type_from_user_follows_account_type(): void
    {
        $this->assertSame(ActorType::Staff, ActorType::fromUser(User::factory()->staff()->create()));
        $this->assertSame(ActorType::Patient, ActorType::fromUser(User::factory()->create()));
        $this->assertSame(ActorType::Patient, ActorType::fromUser(User::factory()->patient()->inactive()->create()));
    }

    public function test_strict_mode_is_enabled_outside_production(): void
    {
        $this->assertFalse($this->app->isProduction());
        $this->assertTrue(Model::preventsLazyLoading());
        $this->assertTrue(Model::preventsSilentlyDiscardingAttributes());
        $this->assertTrue(Model::preventsAccessingMissingAttributes());
    }

    public function test_lazy_loading_a_relation_on_a_collection_is_rejected(): void
    {
        User::factory()->count(2)->create();

        $this->expectException(LazyLoadingViolationException::class);

        User::query()->get()->each(fn (User $user) => $user->notifications);
    }

    public function test_reading_a_column_that_was_not_selected_is_rejected(): void
    {
        $user = User::factory()->create();

        $partial = User::query()->select('id')->findOrFail($user->id);

        $this->expectException(MissingAttributeException::class);

        $partial->email;
    }
}
```

- [ ] **Step 2: Jalankan tes — harus GAGAL**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=UserAccountTest
```
Expected: FAIL — (a) tes skema untuk `phone`, `account_type`, `is_active`, `must_change_password`, `last_login_at` gagal `Kolom users.<nama> belum ada.`; `test_schema_notifications_table_is_the_laravel_default` gagal karena tabel belum ada (daftar kolom kosong); (b) tes factory/scope gagal `BadMethodCallException: Call to undefined method Database\Factories\UserFactory::staff()` (atau `Unknown column 'account_type'`); (c) `test_account_fields_are_not_mass_assignable` gagal `Failed asserting that exception of type "Illuminate\Database\Eloquent\MassAssignmentException" is thrown`; (d) `test_strict_mode_is_enabled_outside_production` gagal `Failed asserting that false is true`. Kolom `name`, `email`, `password`, `email_verified_at`, `remember_token`, `created_at`, `updated_at` sudah lulus.

- [ ] **Step 3: Buat migration `notifications` dengan perintah resmi lalu ganti namanya**

Perintah menghasilkan berkas bertimestamp hari ini; ganti nama agar urut sesuai 09 §10 (nomor 6). Isi berkas **tidak diubah** (`uuid('id')` menjadi `CHAR(36)` di MariaDB 10.4).

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan make:notifications-table
$target = "2026_10_02_000030_create_notifications_table.php"
$generated = @(Get-ChildItem -LiteralPath "database\migrations" -Filter "*_create_notifications_table.php" | Where-Object { $_.Name -ne $target })
if ($generated.Count -ne 1) { throw "Harus ada tepat satu berkas hasil make:notifications-table, ditemukan: $($generated.Count)" }
Move-Item -LiteralPath $generated[0].FullName -Destination (Join-Path "database\migrations" $target)
Get-ChildItem -LiteralPath "database\migrations" | ForEach-Object { $_.Name }
```
Expected: `INFO  Migration [...\database\migrations\2026_10_0x_xxxxxx_create_notifications_table.php] created successfully.`, lalu daftar berkas memuat `0001_01_01_000000_create_users_table.php`, `0001_01_01_000001_create_cache_table.php`, `0001_01_01_000002_create_jobs_table.php`, `2026_10_02_000030_create_notifications_table.php` dan **tidak** ada `*_create_notifications_table.php` lain.

- [ ] **Step 4: Tulis migration kolom akun `database/migrations/2026_10_02_000010_add_account_fields_to_users_table.php`**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Kolom akun 09 §2.1. `account_type` sengaja tanpa default agar tidak ada akun
     * yang menjadi staf/pasien secara tidak sengaja.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('email');
            $table->string('account_type', 10)->after('password');
            $table->boolean('is_active')->default(true)->after('account_type');
            $table->boolean('must_change_password')->default(false)->after('is_active');
            $table->dateTime('last_login_at')->nullable()->after('must_change_password');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'account_type', 'is_active', 'must_change_password', 'last_login_at']);
        });
    }
};
```

- [ ] **Step 5: Jalankan tes skema — harus LULUS**

`RefreshDatabase` menjalankan `migrate:fresh` pada database uji secara otomatis.

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=test_schema_
```
Expected: PASS — semua tes berawalan `test_schema_` (12 kolom `users`, kunci primer + kolom baru, unique email, tabel `notifications`) lulus. Bila `is_active` gagal pada default, cetak `Schema::getColumns('users')` dan pastikan hanya format default MariaDB yang berbeda (perbaiki normalisasi di tes, bukan migration).

- [ ] **Step 6: Perbarui model `app/Models/User.php`**

Ganti seluruh isi berkas:

```php
<?php

namespace App\Models;

use App\Enums\AccountType;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Atribut yang boleh diisi massal. `account_type`, `is_active`, `must_change_password`,
     * dan `last_login_at` sengaja tidak termasuk: diubah lewat forceFill() oleh service/controller login.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'account_type' => AccountType::class,
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /** Hanya akun staf. */
    public function scopeStaff(Builder $query): Builder
    {
        return $query->where('account_type', AccountType::Staff->value);
    }

    /** Hanya akun pasien. */
    public function scopePatients(Builder $query): Builder
    {
        return $query->where('account_type', AccountType::Patient->value);
    }

    /** Hanya akun aktif (boleh login). */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isStaff(): bool
    {
        return $this->account_type === AccountType::Staff;
    }

    public function isPatient(): bool
    {
        return $this->account_type === AccountType::Patient;
    }
}
```

- [ ] **Step 7: Perbarui `database/factories/UserFactory.php`**

Ganti seluruh isi berkas:

```php
<?php

namespace Database\Factories;

use App\Enums\AccountType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state: pasien aktif, tanpa kewajiban ganti password.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => null,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'account_type' => AccountType::Patient,
            'is_active' => true,
            'must_change_password' => false,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /** Akun staf. Peran diberikan lewat withRole() (Task 3). */
    public function staff(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => AccountType::Staff,
        ]);
    }

    /** Akun pasien (default factory; berguna untuk menimpa staff()). */
    public function patient(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => AccountType::Patient,
        ]);
    }

    /** Akun nonaktif: tidak boleh login. */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /** Akun dengan password sementara: wajib ganti password saat login berikutnya. */
    public function mustChangePassword(): static
    {
        return $this->state(fn (array $attributes) => [
            'must_change_password' => true,
        ]);
    }
}
```

- [ ] **Step 8: Aktifkan mode ketat di `app/Providers/AppServiceProvider.php`**

Ganti seluruh isi berkas:

```php
<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Mode ketat hanya di non-produksi: mencegah lazy loading (N+1), atribut yang hilang,
        // dan atribut yang diam-diam dibuang saat mass assignment (11 §3.3).
        Model::shouldBeStrict(! $this->app->isProduction());
    }
}
```

- [ ] **Step 9: Jalankan tes akun — harus LULUS**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=UserAccountTest
```
Expected: PASS — seluruh tes `UserAccountTest` lulus, termasuk `test_password_is_stored_as_a_bcrypt_hash_and_never_serialized` (SEK-01), `test_account_fields_are_not_mass_assignable` (4 dataset), `test_lazy_loading_a_relation_on_a_collection_is_rejected`, dan `test_strict_mode_is_enabled_outside_production`.

- [ ] **Step 10: Pastikan seluruh suite tetap hijau dengan mode ketat**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test
```
Expected: semua tes hijau (`SmokeTest`, `EnvironmentTest`, `LocalizationTest`, `DatabaseGuardWiringTest`, tes Unit fase 0, tes Task 1, dan `UserAccountTest`). `SmokeTest` tidak menyentuh model sehingga tidak terpengaruh mode ketat. Bila ada tes lama gagal karena `LazyLoadingViolationException`/`MassAssignmentException`, perbaiki pemanggilnya (eager load / `forceFill`), jangan menonaktifkan mode ketat.

- [ ] **Step 11: Pint**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint --test
```
Expected: `PASS`. Bila `FAIL`, jalankan `& $PHP vendor\bin\pint` lalu ulangi `--test`.

- [ ] **Step 12: Commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git add database/migrations/2026_10_02_000010_add_account_fields_to_users_table.php database/migrations/2026_10_02_000030_create_notifications_table.php app/Models/User.php database/factories/UserFactory.php app/Providers/AppServiceProvider.php tests/Feature/Models/UserAccountTest.php
git commit -m "feat(fase-1): kolom akun users, tabel notifications, model User, factory, dan mode ketat Eloquent"
```

---

### Task 3: Peran dan izin (Spatie) + `Gate::before`

**Files:**
- Create: `config/permission.php` (publish), `database/migrations/2026_10_02_000020_create_permission_tables.php` (publish + ganti nama), `database/seeders/RolePermissionSeeder.php`, `lang/id/roles.php`, `tests/Support/InteractsWithStaff.php`
- Modify: `database/seeders/DatabaseSeeder.php`, `app/Models/User.php`, `database/factories/UserFactory.php`, `app/Providers/AppServiceProvider.php`, `bootstrap/app.php`
- Test: `tests/Feature/Auth/RolePermissionSeederTest.php`, `tests/Feature/Auth/GateBeforeTest.php`

**Interfaces:**
- Consumes: `User` (Task 2).
- Produces: `RolePermissionSeeder::PERMISSIONS` (19 nama izin urutan 00 G) dan `RolePermissionSeeder::ROLES` (`'super-admin' => self::PERMISSIONS`, `'content-admin'` 9 izin, `'registration-officer'` 11 izin, persis matriks 00 G); `run()` = `Permission::firstOrCreate` + `Role::firstOrCreate` (guard `web`) + `syncPermissions` + `forgetCachedPermissions()`; idempoten. `DatabaseSeeder::run()` memanggil `RolePermissionSeeder`. `User` memakai trait `HasRoles` + accessor `roleLabel` (`Attribute`; `__('roles.'.$this->getRoleNames()->first())`, string kosong bila tanpa peran). `lang/id/roles.php` = `['super-admin' => 'Super Admin', 'content-admin' => 'Admin Konten', 'registration-officer' => 'Petugas Pendaftaran']`. `UserFactory::withRole(string $role): static` (afterCreating `assignRole`). `AppServiceProvider::boot()`: `Gate::before(fn (User $user, string $ability) => $user->hasRole('super-admin') ? true : null);`. `bootstrap/app.php` → alias `role`, `permission`, `role_or_permission` (Spatie `Middleware\*`). `config/permission.php`: `teams=false`, `register_permission_check_method=true`, `display_permission_in_exception=false`, `display_role_in_exception=false`, cache 24 jam store `default` (10 §7.4). Trait tes `Tests\Support\InteractsWithStaff` (dipakai Task 3, 14, 15, 17, 18, 19): `protected function seedRoles(): void` (menjalankan `RolePermissionSeeder` bila tabel `roles` kosong) dan `protected function createStaff(string $role, array $attributes = []): User` (memanggil `seedRoles()`, lalu `User::factory()->staff()->withRole($role)->create($attributes)`).
- Catatan verifikasi API (Spatie 6.25, dibaca dari `vendor/spatie/laravel-permission`): tag publish = `permission-config` (→ `config/permission.php`) dan `permission-migrations` (→ `database/migrations/{Y_m_d_His}_create_permission_tables.php`; bila berkas `*_create_permission_tables.php` sudah ada, paket memakainya). Isi bawaan `config/permission.php` **sudah** memenuhi 10 §7.4 (`teams=false`, `register_permission_check_method=true`, `enable_wildcard_permission=false`, `display_permission_in_exception=false`, `display_role_in_exception=false`, `cache.expiration_time` = `DateInterval::createFromDateString('24 hours')`, `cache.store='default'`, nama tabel bawaan), jadi berkas hasil publish **tidak diubah**; tes mengunci kunci-kunci itu. Middleware: `Spatie\Permission\Middleware\{RoleMiddleware, PermissionMiddleware, RoleOrPermissionMiddleware}`; `permission:a|b` memakai `$user->canAny()`; pengecualian akses = `UnauthorizedException` (HttpException 403, pesan tidak membuka nama izin karena `display_*=false`).
- Catatan mode ketat: `hasRole()`, `getRoleNames()`, `hasDirectPermission()`, dan `getPermissionsViaRoles()` memakai `loadMissing(...)` (pemuatan eksplisit, bukan lazy loading), dan relasi `roles` pada objek `Permission` dari cache registrar diisi lewat `setRelation`; karena itu `Gate::before`, `can()`, dan `roleLabel` aman di mode ketat untuk model dari hasil banyak baris. **Pengecualian:** `getAllPermissions()` dan `$user->permissions` mengakses relasi langsung dan melempar `LazyLoadingViolationException` pada koleksi ≥ 2 pengguna — kode fase berikutnya memakai `can()`/`hasAnyPermission()`, bukan `getAllPermissions()`. Seeder harus menyelesaikan seluruh `Permission::firstOrCreate` sebelum `syncPermissions` (cache registrar diperbarui otomatis oleh event `saved` model Permission).

**Test IDs:** `AKS-11` (bagian: Super Admin lolos ability apa pun lewat `Gate::before`), `KNS-03` (bagian: 19 izin persis), plus matriks 19/9/11.

**Requirements:** `model_has_permissions` kosong setelah seeding. Alias `permission:a|b` berfungsi (uji dengan route uji di dalam tes).

- [ ] **Step 1: Tulis tes yang gagal `tests/Feature/Auth/RolePermissionSeederTest.php`**

```php
<?php

namespace Tests\Feature\Auth;

use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolePermissionSeeder;
use DateInterval;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RolePermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    /** 00-keputusan-inti.md G: 19 izin baku, urutan tabel. */
    private const ALL_PERMISSIONS = [
        'dashboard.view',
        'doctors.view',
        'doctors.manage',
        'clinics.manage',
        'schedules.view',
        'schedules.manage',
        'sessions.review',
        'facilities.manage',
        'rooms.update-availability',
        'content.manage',
        'patients.view',
        'patients.manage',
        'appointments.view',
        'appointments.manage',
        'reports.export',
        'messages.manage',
        'settings.manage',
        'staff.manage',
        'audit.view',
    ];

    /** 00 G: kolom content-admin (9 izin). */
    private const CONTENT_ADMIN = [
        'dashboard.view',
        'doctors.view',
        'doctors.manage',
        'clinics.manage',
        'schedules.view',
        'schedules.manage',
        'facilities.manage',
        'rooms.update-availability',
        'content.manage',
    ];

    /** 00 G: kolom registration-officer (11 izin). */
    private const REGISTRATION_OFFICER = [
        'dashboard.view',
        'doctors.view',
        'schedules.view',
        'sessions.review',
        'rooms.update-availability',
        'patients.view',
        'patients.manage',
        'appointments.view',
        'appointments.manage',
        'reports.export',
        'messages.manage',
    ];

    /** @return array<string, list<string>> nama peran => nama izin */
    private function rolePermissionMap(): array
    {
        $map = [];

        foreach (Role::query()->with('permissions')->get() as $role) {
            $names = $role->permissions->pluck('name')->all();
            sort($names);
            $map[$role->name] = $names;
        }

        ksort($map);

        return $map;
    }

    /**
     * @param  list<string>  $names
     * @return list<string>
     */
    private function sorted(array $names): array
    {
        sort($names);

        return $names;
    }

    public function test_permission_catalog_has_the_19_blueprint_permissions_in_order(): void
    {
        $this->assertCount(19, self::ALL_PERMISSIONS);
        $this->assertSame(self::ALL_PERMISSIONS, RolePermissionSeeder::PERMISSIONS);
        $this->assertCount(19, array_unique(RolePermissionSeeder::PERMISSIONS));
    }

    public function test_roles_constant_matches_the_blueprint_matrix(): void
    {
        $this->assertSame(['super-admin', 'content-admin', 'registration-officer'], array_keys(RolePermissionSeeder::ROLES));

        $this->assertSame(RolePermissionSeeder::PERMISSIONS, RolePermissionSeeder::ROLES['super-admin']);

        $this->assertCount(9, RolePermissionSeeder::ROLES['content-admin']);
        $this->assertSame($this->sorted(self::CONTENT_ADMIN), $this->sorted(RolePermissionSeeder::ROLES['content-admin']));

        $this->assertCount(11, RolePermissionSeeder::ROLES['registration-officer']);
        $this->assertSame($this->sorted(self::REGISTRATION_OFFICER), $this->sorted(RolePermissionSeeder::ROLES['registration-officer']));
    }

    public function test_permission_tables_and_published_files_exist(): void
    {
        $this->assertTrue(Schema::hasTable('roles'));
        $this->assertTrue(Schema::hasTable('permissions'));
        $this->assertTrue(Schema::hasTable('model_has_roles'));
        $this->assertTrue(Schema::hasTable('model_has_permissions'));
        $this->assertTrue(Schema::hasTable('role_has_permissions'));

        $this->assertFileExists(config_path('permission.php'));
        $this->assertFileExists(database_path('migrations/2026_10_02_000020_create_permission_tables.php'));
        $this->assertCount(1, glob(database_path('migrations/*_create_permission_tables.php')));
    }

    public function test_seeder_creates_19_permissions_and_3_roles_for_the_web_guard(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $this->assertSame(19, Permission::query()->count());
        $this->assertSame(3, Role::query()->count());
        $this->assertSame(self::ALL_PERMISSIONS, Permission::query()->orderBy('id')->pluck('name')->all());
        $this->assertSame(0, Permission::query()->where('guard_name', '!=', 'web')->count());
        $this->assertSame(0, Role::query()->where('guard_name', '!=', 'web')->count());
    }

    public function test_each_role_gets_exactly_the_blueprint_permissions(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $this->assertSame([
            'content-admin' => $this->sorted(self::CONTENT_ADMIN),
            'registration-officer' => $this->sorted(self::REGISTRATION_OFFICER),
            'super-admin' => $this->sorted(self::ALL_PERMISSIONS),
        ], $this->rolePermissionMap());
    }

    public function test_no_direct_user_permissions_or_roles_exist_after_seeding(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $this->assertSame(0, DB::table('model_has_permissions')->count());
        $this->assertSame(0, DB::table('model_has_roles')->count());
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->assertSame(19, Permission::query()->count());
        $this->assertSame(3, Role::query()->count());
        $this->assertSame(19 + 9 + 11, DB::table('role_has_permissions')->count());
    }

    public function test_seeder_resyncs_permissions_that_drifted_from_the_matrix(): void
    {
        $this->seed(RolePermissionSeeder::class);

        Role::findByName('content-admin')->givePermissionTo('audit.view');
        Role::findByName('registration-officer')->revokePermissionTo('reports.export');

        $this->seed(RolePermissionSeeder::class);

        $map = $this->rolePermissionMap();

        $this->assertSame($this->sorted(self::CONTENT_ADMIN), $map['content-admin']);
        $this->assertSame($this->sorted(self::REGISTRATION_OFFICER), $map['registration-officer']);
    }

    public function test_seeder_resets_the_permission_cache(): void
    {
        $this->seed(RolePermissionSeeder::class);

        app(PermissionRegistrar::class)->getPermissions();
        $this->assertTrue(Cache::has(config('permission.cache.key')));

        $this->seed(RolePermissionSeeder::class);

        $this->assertFalse(Cache::has(config('permission.cache.key')));
    }

    public function test_permission_config_follows_the_blueprint(): void
    {
        $this->assertFalse(config('permission.teams'));
        $this->assertTrue(config('permission.register_permission_check_method'));
        $this->assertFalse(config('permission.enable_wildcard_permission'));
        $this->assertFalse(config('permission.display_permission_in_exception'));
        $this->assertFalse(config('permission.display_role_in_exception'));
        $this->assertSame('default', config('permission.cache.store'));
        $this->assertSame(Permission::class, config('permission.models.permission'));
        $this->assertSame(Role::class, config('permission.models.role'));

        $interval = config('permission.cache.expiration_time');

        $this->assertInstanceOf(DateInterval::class, $interval);
        $this->assertSame(24, $interval->h + ($interval->d * 24));

        $this->assertSame([
            'roles' => 'roles',
            'permissions' => 'permissions',
            'model_has_permissions' => 'model_has_permissions',
            'model_has_roles' => 'model_has_roles',
            'role_has_permissions' => 'role_has_permissions',
        ], config('permission.table_names'));
    }

    public function test_role_labels_are_indonesian(): void
    {
        $this->assertSame('Super Admin', __('roles.super-admin'));
        $this->assertSame('Admin Konten', __('roles.content-admin'));
        $this->assertSame('Petugas Pendaftaran', __('roles.registration-officer'));
    }

    public function test_database_seeder_runs_the_role_permission_seeder(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(3, Role::query()->count());
        $this->assertSame(19, Permission::query()->count());
    }
}
```

- [ ] **Step 2: Jalankan tes — harus GAGAL**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=RolePermissionSeederTest
```
Expected: FAIL — `test_permission_tables_and_published_files_exist` gagal (tabel `roles` belum ada, `config/permission.php` belum dipublikasikan); semua tes yang memanggil `RolePermissionSeeder` gagal `Class "Database\Seeders\RolePermissionSeeder" not found`; `test_role_labels_are_indonesian` gagal (`__('roles.super-admin')` mengembalikan kunci mentah `roles.super-admin`). `test_permission_config_follows_the_blueprint` sudah lulus karena Spatie menggabungkan konfigurasi bawaannya lewat `mergeConfigFrom` (tes ini mengunci nilai, bukan keberadaan berkas).

- [ ] **Step 3: Publikasikan konfigurasi dan migration Spatie, lalu ganti nama migration**

Tag diverifikasi dari `PermissionServiceProvider::offerPublishing()`: `permission-config` dan `permission-migrations`.

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan vendor:publish --tag=permission-config
& $PHP artisan vendor:publish --tag=permission-migrations
$target = "2026_10_02_000020_create_permission_tables.php"
$published = @(Get-ChildItem -LiteralPath "database\migrations" -Filter "*_create_permission_tables.php" | Where-Object { $_.Name -ne $target })
if ($published.Count -ne 1) { throw "Harus ada tepat satu berkas hasil publish, ditemukan: $($published.Count)" }
Move-Item -LiteralPath $published[0].FullName -Destination (Join-Path "database\migrations" $target)
Get-ChildItem -LiteralPath "database\migrations" | ForEach-Object { $_.Name }
Select-String -Path "config\permission.php" -Pattern "'teams' =>", "'register_permission_check_method' =>", "'enable_wildcard_permission' =>", "'display_permission_in_exception' =>", "'display_role_in_exception' =>", "'expiration_time' =>", "'store' =>" | ForEach-Object { $_.Line.Trim() }
```
Expected: dua blok `INFO  Publishing [permission-config] assets.` / `[permission-migrations]` dengan `DONE`; daftar migration berisi `0001_01_01_000000…`, `0001_01_01_000001…`, `0001_01_01_000002…`, `2026_10_02_000010_add_account_fields_to_users_table.php`, `2026_10_02_000020_create_permission_tables.php`, `2026_10_02_000030_create_notifications_table.php` (tanpa duplikat `*_create_permission_tables.php`); tujuh baris konfigurasi: `'register_permission_check_method' => true,`, `'teams' => false,`, `'display_permission_in_exception' => false,`, `'display_role_in_exception' => false,`, `'enable_wildcard_permission' => false,`, `'expiration_time' => DateInterval::createFromDateString('24 hours'),`, `'store' => 'default',`. Tidak ada yang perlu diubah di `config/permission.php` (sudah sesuai 10 §7.4). Migration urut setelah `0001_01_01_000001_create_cache_table.php` sehingga `cache` ada saat migration Spatie memanggil `cache()->forget(...)`.

- [ ] **Step 4: Buat label peran `lang/id/roles.php`**

```php
<?php

return [
    'super-admin' => 'Super Admin',
    'content-admin' => 'Admin Konten',
    'registration-officer' => 'Petugas Pendaftaran',
];
```

- [ ] **Step 5: Buat `database/seeders/RolePermissionSeeder.php`**

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeder inti (13 §2.4): 19 izin baku dan 3 peran (00 G). Idempoten: aman dijalankan ulang
 * (produksi dan pengembangan); izin peran selalu disinkronkan ke matriks di bawah.
 */
class RolePermissionSeeder extends Seeder
{
    /** @var list<string> Urutan = tabel 00 G. */
    public const PERMISSIONS = [
        'dashboard.view',
        'doctors.view',
        'doctors.manage',
        'clinics.manage',
        'schedules.view',
        'schedules.manage',
        'sessions.review',
        'facilities.manage',
        'rooms.update-availability',
        'content.manage',
        'patients.view',
        'patients.manage',
        'appointments.view',
        'appointments.manage',
        'reports.export',
        'messages.manage',
        'settings.manage',
        'staff.manage',
        'audit.view',
    ];

    /**
     * Matriks peran ⇒ izin (00 G). `super-admin` memperoleh semua izin untuk tampilan UI;
     * pemeriksaan sebenarnya tetap lewat Gate::before.
     *
     * @var array<string, list<string>>
     */
    public const ROLES = [
        'super-admin' => self::PERMISSIONS,
        'content-admin' => [
            'dashboard.view',
            'doctors.view',
            'doctors.manage',
            'clinics.manage',
            'schedules.view',
            'schedules.manage',
            'facilities.manage',
            'rooms.update-availability',
            'content.manage',
        ],
        'registration-officer' => [
            'dashboard.view',
            'doctors.view',
            'schedules.view',
            'sessions.review',
            'rooms.update-availability',
            'patients.view',
            'patients.manage',
            'appointments.view',
            'appointments.manage',
            'reports.export',
            'messages.manage',
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name) {
            Permission::query()->firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach (self::ROLES as $roleName => $permissions) {
            $role = Role::query()->firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
```

- [ ] **Step 6: Perbarui `database/seeders/DatabaseSeeder.php`**

Ganti seluruh isi berkas:

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seeder inti. Seeder pengaturan ditambahkan di Task 7 dan guard demo di Task 18
     * (DemoSeeder hanya dijalankan di lingkungan local/testing; blueprint bagian 13).
     */
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);
    }
}
```

- [ ] **Step 7: Jalankan tes seeder — harus LULUS**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=RolePermissionSeederTest
```
Expected: PASS — 12 tes `RolePermissionSeederTest` lulus (19 izin urut, matriks 19/9/11, idempoten, resync, reset cache, konfigurasi, label peran, `DatabaseSeeder`).

- [ ] **Step 8: Pint dan commit siklus seeder**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint --test
```
Expected: `PASS` (bila `FAIL`, jalankan `& $PHP vendor\bin\pint` dan ulangi `--test`; `database/migrations/2026_10_02_000020_create_permission_tables.php` hasil publish ikut dirapikan bila perlu).

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git add config/permission.php database/migrations/2026_10_02_000020_create_permission_tables.php database/seeders/RolePermissionSeeder.php database/seeders/DatabaseSeeder.php lang/id/roles.php tests/Feature/Auth/RolePermissionSeederTest.php
git commit -m "feat(fase-1): tabel Spatie, RolePermissionSeeder 19 izin dan 3 peran, label peran"
```

- [ ] **Step 9: Buat trait tes `tests/Support/InteractsWithStaff.php`**

```php
<?php

namespace Tests\Support;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\Models\Role;

/**
 * Pembantu tes untuk akun staf berperan. Dipakai bersama RefreshDatabase
 * (tabel `roles` kosong di awal setiap tes).
 */
trait InteractsWithStaff
{
    /** Menjalankan RolePermissionSeeder bila tabel roles masih kosong. */
    protected function seedRoles(): void
    {
        if (Role::query()->doesntExist()) {
            $this->seed(RolePermissionSeeder::class);
        }
    }

    /**
     * Membuat akun staf aktif dengan satu peran.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function createStaff(string $role, array $attributes = []): User
    {
        $this->seedRoles();

        return User::factory()->staff()->withRole($role)->create($attributes);
    }
}
```

- [ ] **Step 10: Tulis tes yang gagal `tests/Feature/Auth/GateBeforeTest.php`**

```php
<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Exceptions\RoleDoesNotExist;
use Spatie\Permission\Models\Permission;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

class GateBeforeTest extends TestCase
{
    use InteractsWithStaff;
    use RefreshDatabase;

    private function registerProbeRoutes(): void
    {
        Route::get('/_uji/izin-pipa', fn () => 'ok')->middleware('permission:doctors.manage|settings.manage');
        Route::get('/_uji/peran-pipa', fn () => 'ok')->middleware('role:super-admin|content-admin');
        Route::get('/_uji/peran-atau-izin', fn () => 'ok')->middleware('role_or_permission:super-admin|appointments.manage');
    }

    public function test_super_admin_passes_every_ability(): void
    {
        $superAdmin = $this->createStaff('super-admin');
        Gate::define('uji.selalu-tolak', fn (User $user) => false);

        $fresh = User::query()->findOrFail($superAdmin->id);

        $this->assertTrue(Gate::forUser($fresh)->allows('uji.selalu-tolak'));
        $this->assertTrue(Gate::forUser($fresh)->allows('kemampuan.yang.tidak.terdaftar'));
        $this->assertTrue($fresh->can('settings.manage'));
        $this->assertTrue($fresh->can('apa.saja'));
        $this->assertFalse($fresh->cannot('uji.selalu-tolak'));
    }

    public function test_other_staff_only_get_the_permissions_of_their_role(): void
    {
        $content = User::query()->findOrFail($this->createStaff('content-admin')->id);
        $officer = User::query()->findOrFail($this->createStaff('registration-officer')->id);

        $this->assertTrue($content->can('doctors.manage'));
        $this->assertTrue($content->can('content.manage'));
        $this->assertFalse($content->can('settings.manage'));
        $this->assertFalse($content->can('staff.manage'));
        $this->assertFalse($content->can('audit.view'));
        $this->assertFalse($content->can('appointments.view'));
        $this->assertFalse($content->can('apa.saja'));

        $this->assertTrue($officer->can('appointments.manage'));
        $this->assertTrue($officer->can('sessions.review'));
        $this->assertFalse($officer->can('doctors.manage'));
        $this->assertFalse($officer->can('content.manage'));
        $this->assertFalse($officer->can('settings.manage'));
    }

    public function test_user_without_role_and_guest_are_denied(): void
    {
        $this->seedRoles();
        Gate::define('uji.selalu-tolak', fn (User $user) => false);

        $patient = User::query()->findOrFail(User::factory()->create()->id);

        $this->assertFalse($patient->can('dashboard.view'));
        $this->assertFalse($patient->can('uji.selalu-tolak'));
        $this->assertFalse(Gate::allows('dashboard.view'));
        $this->assertFalse(Gate::allows('uji.selalu-tolak'));
    }

    public function test_gate_before_and_role_label_do_not_trigger_lazy_loading_in_strict_mode(): void
    {
        $this->assertTrue(Model::preventsLazyLoading());

        $ids = [
            $this->createStaff('super-admin')->id,
            $this->createStaff('content-admin')->id,
            $this->createStaff('registration-officer')->id,
            User::factory()->create()->id,
        ];

        // Hasil banyak baris: model ditandai "dilarang lazy loading" oleh mode ketat.
        $users = User::query()->whereIn('id', $ids)->orderBy('id')->get();

        $this->assertCount(4, $users);

        $labels = [];

        foreach ($users as $user) {
            $user->can('dashboard.view');
            $user->can('doctors.manage');
            $user->hasAnyPermission('appointments.view', 'audit.view');
            Gate::forUser($user)->allows('settings.manage');
            $labels[] = $user->roleLabel;
        }

        $this->assertSame(['Super Admin', 'Admin Konten', 'Petugas Pendaftaran', ''], $labels);
    }

    public function test_role_label_is_empty_without_a_role(): void
    {
        $this->assertSame('', User::factory()->create()->roleLabel);
    }

    /** @return array<string, array{0: string, 1: int, 2: int, 3: int}> */
    public static function probeMatrix(): array
    {
        // peran => status untuk /_uji/izin-pipa, /_uji/peran-pipa, /_uji/peran-atau-izin
        return [
            'super-admin' => ['super-admin', 200, 200, 200],
            'content-admin' => ['content-admin', 200, 200, 403],
            'registration-officer' => ['registration-officer', 403, 403, 200],
        ];
    }

    #[DataProvider('probeMatrix')]
    public function test_spatie_middleware_aliases_work(string $role, int $permissionPipe, int $rolePipe, int $roleOrPermission): void
    {
        $this->registerProbeRoutes();
        $staff = $this->createStaff($role);

        $this->actingAs($staff)->get('/_uji/izin-pipa')->assertStatus($permissionPipe);
        $this->actingAs($staff)->get('/_uji/peran-pipa')->assertStatus($rolePipe);
        $this->actingAs($staff)->get('/_uji/peran-atau-izin')->assertStatus($roleOrPermission);
    }

    public function test_spatie_middleware_rejects_guests_with_403(): void
    {
        $this->registerProbeRoutes();

        $this->get('/_uji/izin-pipa')->assertForbidden();
        $this->get('/_uji/peran-pipa')->assertForbidden();
        $this->get('/_uji/peran-atau-izin')->assertForbidden();
    }

    public function test_forbidden_response_does_not_reveal_permission_names(): void
    {
        $this->registerProbeRoutes();
        $officer = $this->createStaff('registration-officer');

        $response = $this->actingAs($officer)->get('/_uji/izin-pipa');

        $response->assertForbidden();
        $this->assertStringNotContainsString('doctors.manage', $response->getContent());
        $this->assertStringNotContainsString('settings.manage', $response->getContent());
    }

    public function test_factory_with_role_assigns_exactly_one_role_and_no_direct_permissions(): void
    {
        $staff = $this->createStaff('content-admin');

        $this->assertSame(['content-admin'], User::query()->findOrFail($staff->id)->getRoleNames()->all());
        $this->assertSame(0, DB::table('model_has_permissions')->count());
        $this->assertSame(1, DB::table('model_has_roles')->count());
    }

    public function test_factory_with_unknown_role_fails(): void
    {
        $this->seedRoles();

        $this->expectException(RoleDoesNotExist::class);

        User::factory()->staff()->withRole('peran-ngawur')->create();
    }

    public function test_create_staff_helper_creates_an_active_staff_and_seeds_roles_once(): void
    {
        $first = $this->createStaff('content-admin', ['name' => 'Staf Uji Satu']);
        $second = $this->createStaff('registration-officer');

        $this->assertSame('Staf Uji Satu', $first->name);
        $this->assertTrue($first->isStaff());
        $this->assertTrue($first->is_active);
        $this->assertTrue($second->isStaff());
        $this->assertSame(3, DB::table('roles')->count());
        $this->assertSame(19, Permission::query()->count());
    }
}
```

- [ ] **Step 11: Jalankan tes — harus GAGAL**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=GateBeforeTest
```
Expected: FAIL — `BadMethodCallException: Call to undefined method Database\Factories\UserFactory::withRole()` pada setiap tes yang memanggil `createStaff()`; `test_role_label_is_empty_without_a_role` gagal `Undefined property`/`MissingAttributeException` untuk `roleLabel`; `test_spatie_middleware_rejects_guests_with_403` gagal karena alias `permission`/`role`/`role_or_permission` belum terdaftar (`Target class [permission] does not exist`).

- [ ] **Step 12: Perbarui model `app/Models/User.php` (trait `HasRoles` + accessor `roleLabel`)**

Ganti seluruh isi berkas:

```php
<?php

namespace App\Models;

use App\Enums\AccountType;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Atribut yang boleh diisi massal. `account_type`, `is_active`, `must_change_password`,
     * dan `last_login_at` sengaja tidak termasuk: diubah lewat forceFill() oleh service/controller login.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'account_type' => AccountType::class,
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Label peran untuk UI ("Super Admin", "Admin Konten", "Petugas Pendaftaran"); kosong bila tanpa peran.
     * Memakai getRoleNames() (loadMissing), sehingga aman di mode ketat.
     *
     * @return Attribute<string, never>
     */
    protected function roleLabel(): Attribute
    {
        return Attribute::get(function (): string {
            $role = $this->getRoleNames()->first();

            return $role === null ? '' : __('roles.'.$role);
        });
    }

    /** Hanya akun staf. */
    public function scopeStaff(Builder $query): Builder
    {
        return $query->where('account_type', AccountType::Staff->value);
    }

    /** Hanya akun pasien. */
    public function scopePatients(Builder $query): Builder
    {
        return $query->where('account_type', AccountType::Patient->value);
    }

    /** Hanya akun aktif (boleh login). */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isStaff(): bool
    {
        return $this->account_type === AccountType::Staff;
    }

    public function isPatient(): bool
    {
        return $this->account_type === AccountType::Patient;
    }
}
```

- [ ] **Step 13: Tambahkan `withRole()` di `database/factories/UserFactory.php`**

Ganti seluruh isi berkas:

```php
<?php

namespace Database\Factories;

use App\Enums\AccountType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state: pasien aktif, tanpa kewajiban ganti password.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => null,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'account_type' => AccountType::Patient,
            'is_active' => true,
            'must_change_password' => false,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /** Akun staf. Peran diberikan lewat withRole(). */
    public function staff(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => AccountType::Staff,
        ]);
    }

    /** Akun pasien (default factory; berguna untuk menimpa staff()). */
    public function patient(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => AccountType::Patient,
        ]);
    }

    /** Akun nonaktif: tidak boleh login. */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /** Akun dengan password sementara: wajib ganti password saat login berikutnya. */
    public function mustChangePassword(): static
    {
        return $this->state(fn (array $attributes) => [
            'must_change_password' => true,
        ]);
    }

    /**
     * Memberi satu peran Spatie setelah akun dibuat. Peran harus sudah ada
     * (jalankan RolePermissionSeeder lebih dulu, mis. lewat InteractsWithStaff::seedRoles()).
     */
    public function withRole(string $role): static
    {
        return $this->afterCreating(function (User $user) use ($role): void {
            $user->assignRole($role);
        });
    }
}
```

- [ ] **Step 14: Daftarkan `Gate::before` di `app/Providers/AppServiceProvider.php`**

Ganti seluruh isi berkas:

```php
<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Mode ketat hanya di non-produksi: mencegah lazy loading (N+1), atribut yang hilang,
        // dan atribut yang diam-diam dibuang saat mass assignment (11 §3.3).
        Model::shouldBeStrict(! $this->app->isProduction());

        // Super Admin melewati semua Gate/Policy (00 G). Hanya untuk pengguna login;
        // aturan mutlak (peran terkunci, minimal satu Super Admin aktif) ditegakkan di StaffAccountService.
        Gate::before(fn (User $user, string $ability) => $user->hasRole('super-admin') ? true : null);
    }
}
```

- [ ] **Step 15: Daftarkan alias middleware Spatie di `bootstrap/app.php`**

Ganti seluruh isi berkas:

```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
```

- [ ] **Step 16: Jalankan tes — harus LULUS**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=GateBeforeTest
```
Expected: PASS — seluruh tes `GateBeforeTest` lulus, termasuk `test_gate_before_and_role_label_do_not_trigger_lazy_loading_in_strict_mode` (empat pengguna dimuat sekaligus dengan mode ketat aktif) dan tiga dataset `test_spatie_middleware_aliases_work`. Bila muncul `LazyLoadingViolationException`, cari pemanggil yang mengakses `$user->roles`/`$user->permissions` langsung (mis. `getAllPermissions()`) dan ganti dengan `can()`/`hasRole()`; jangan menonaktifkan mode ketat.

- [ ] **Step 17: Jalankan seluruh suite dan Pint**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test
& $PHP vendor\bin\pint --test
```
Expected: seluruh suite hijau (`UserAccountTest` tetap lulus dengan `User` baru; `SmokeTest` tetap lulus). `pint --test` = `PASS` (bila `FAIL`: `& $PHP vendor\bin\pint`, ulangi `--test`).

- [ ] **Step 18: Commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git add tests/Support/InteractsWithStaff.php tests/Feature/Auth/GateBeforeTest.php app/Models/User.php database/factories/UserFactory.php app/Providers/AppServiceProvider.php bootstrap/app.php
git commit -m "feat(fase-1): HasRoles, roleLabel, factory withRole, Gate::before Super Admin, alias middleware Spatie"
```

---

### Task 4: Audit log — `audit_logs`, `AuditLog`, `Actor`, `AuditLogger`

**Files:**
- Create: `database/migrations/2026_10_02_002300_create_audit_logs_table.php`, `app/Models/AuditLog.php`, `app/Support/Actor.php`, `app/Services/Audit/AuditLogger.php`
- Test: `tests/Feature/Audit/AuditLoggerTest.php`, `tests/Feature/Support/ActorTest.php`

**Interfaces:**
- Consumes: `User`, `ActorType`, `BookingChannel`.
- Produces:
  - Tabel `audit_logs` persis 09 §7.3 (index bernama sesuai 09; `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP; JSON `old_values`/`new_values`).
  - `App\Models\AuditLog`: `const UPDATED_AT = null`; `$fillable` 10 kolom 11 §5.7; casts `actor_type`⇒`ActorType`, `old_values`/`new_values`⇒array, `created_at`⇒datetime; `user(): BelongsTo`, `auditable(): MorphTo`; scope `forAction(string $prefix)`, `between($from, $to)`; `booted()`: `updating` dan `deleting` melempar `LogicException('Audit log tidak dapat diubah atau dihapus.')`.
  - `App\Support\Actor` (`final readonly class`, constructor privat `(public ActorType $type, public ?int $userId)`): `patient(User)`, `staff(User)`, `system()`, `isPatient()`, `isStaff()`, `isSystem()`, `user(): ?User`, `channel(): BookingChannel` (staf ⇒ `Loket`, lainnya ⇒ `Online`).
  - `App\Services\Audit\AuditLogger` (`final`): konstanta publik `REDACTED = '[disamarkan]'`, `CONTENT_CHANGED = '[konten diubah]'`, `TRUNCATED_SUFFIX = '…[dipotong]'`; `log(string $action, Model $model, ?array $old, ?array $new, string $description, ?Actor $actor = null): AuditLog`; `diff(Model $model): array` (`['old' => …, 'new' => …]` dari `getChanges()`/`getOriginal()` tanpa `updated_at`); `redact(array $values, string $action, ?Model $model = null): array`.
- Perilaku `log()`: pelaku = `$actor` ⇒ bila null `Auth::user()` (staf ⇒ `Actor::staff`, pasien ⇒ `Actor::patient`) ⇒ bila tidak ada `Actor::system()`; `auditable_type = $model->getMorphClass()`, `auditable_id = $model->getKey()` (NULL untuk instance kosong); `description` dipotong 255; konteks HTTP per F1-R8 (`user_agent` tanpa karakter kontrol, ≤ 255); `old`/`new` setelah `redact()`, array kosong ⇒ NULL; galat tulis tidak ditelan.
- Perilaku `redact()`: rekursif, pencocokan kunci tanpa membedakan huruf besar/kecil; `REDACT_ALWAYS = ['password','password_confirmation','current_password','remember_token','token','bpjs_number','payment_reference']` ⇒ `REDACTED`; `REDACT_PII = ['address','phone','email','birth_date','complaint','message']` ⇒ `REDACTED` **hanya** bila `$action` berawalan `patient.`, `appointment.`, atau `contact_message.`; `NAME_ONLY = ['body','bio','short_bio','description']` ⇒ `CONTENT_CHANGED`; atribut `$model->getHidden()` ⇒ `REDACTED`; string > 500 karakter ⇒ 500 karakter pertama + `TRUNCATED_SUFFIX`; `BackedEnum` ⇒ `->value`; `DateTimeInterface` ⇒ `Y-m-d H:i:s`.
- Penegasan dan penyimpangan kecil dari teks kontrak (hasil verifikasi `vendor/`; tidak mengubah nama/signature):
  - `diff()`: pada Eloquent `getChanges()` hanya terisi setelah `update` (event `updated` atau setelah `save()`), dan setelah `save()` nilai `getOriginal()` sudah sama dengan nilai baru. Karena itu **nilai lama diambil dari `getPrevious()`** (tersedia di Laravel 12; berisi nilai mentah sebelum simpan terakhir, juga di dalam event `updated`). Sebelum `save()` (perubahan belum disimpan) `diff()` memakai `getDirty()` + `getRawOriginal()`. Model baru yang baru di-`create()` menghasilkan `['old' => [], 'new' => []]` (insert tidak mengisi `getChanges()`); untuk aksi `*.created` kirim `$model->getAttributes()` sendiri sebagai `$new`.
  - Redaksi `$hidden` juga mencakup atribut ber-cast `encrypted*` pada model (09 §7.3: "setiap atribut `$hidden`/`encrypted`").
  - `Actor::patient()/staff()` menolak `User` yang belum tersimpan (`InvalidArgumentException`), agar `userId` tidak pernah NULL pada pelaku manusia.
  - Konteks HTTP: `request()->route() !== null` ⇒ `ip_address = request()->ip()`, `user_agent` = header bersih (karakter kontrol `\x00-\x1F\x7F` dibuang, dipotong 255, kosong ⇒ NULL); selain itu keduanya NULL (F1-R8; 09 §7.3 [ASUMSI]).
  - Redaksi `staff.updated` untuk `phone` (12 §8.2) **tidak** ditangani logger (hanya awalan `patient.`/`appointment.`/`contact_message.` per kontrak): `StaffAccountService` (Task 17) wajib mengirim `phone` sebagai penanda `AuditLogger::REDACTED`/tanpa nilai.
  - Catatan skema: kolom `json` MariaDB menjadi `longtext` + CHECK `json_valid` otomatis (tes memverifikasi). Query Builder massal (`AuditLog::query()->where(...)->update/delete`) tidak memicu event model; tidak ada kode aplikasi yang boleh melakukannya (UI hanya `index`/`show`).

**Test IDs:** `SEK-11` (unit redaksi: nilai asli tidak pernah tersimpan — cari teks asli di `audit_logs` = 0 baris), `AUD-03` (bagian: model tidak dapat diubah/dihapus).

- [ ] **Step 1: Tulis tes yang gagal `tests/Feature/Support/ActorTest.php`**

```php
<?php

namespace Tests\Feature\Support;

use App\Enums\ActorType;
use App\Enums\BookingChannel;
use App\Models\User;
use App\Support\Actor;
use Error;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use ReflectionClass;
use Tests\TestCase;

class ActorTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_actor_has_no_user(): void
    {
        $actor = Actor::system();

        $this->assertSame(ActorType::System, $actor->type);
        $this->assertNull($actor->userId);
        $this->assertTrue($actor->isSystem());
        $this->assertFalse($actor->isStaff());
        $this->assertFalse($actor->isPatient());
        $this->assertNull($actor->user());
        $this->assertSame(BookingChannel::Online, $actor->channel());
    }

    public function test_staff_actor_wraps_the_staff_account(): void
    {
        $staff = User::factory()->staff()->create();

        $actor = Actor::staff($staff);

        $this->assertSame(ActorType::Staff, $actor->type);
        $this->assertSame($staff->id, $actor->userId);
        $this->assertTrue($actor->isStaff());
        $this->assertFalse($actor->isPatient());
        $this->assertFalse($actor->isSystem());
        $this->assertTrue($actor->user()->is($staff));
        $this->assertSame(BookingChannel::Loket, $actor->channel());
    }

    public function test_patient_actor_wraps_the_patient_account(): void
    {
        $patient = User::factory()->create();

        $actor = Actor::patient($patient);

        $this->assertSame(ActorType::Patient, $actor->type);
        $this->assertSame($patient->id, $actor->userId);
        $this->assertTrue($actor->isPatient());
        $this->assertFalse($actor->isStaff());
        $this->assertFalse($actor->isSystem());
        $this->assertTrue($actor->user()->is($patient));
        $this->assertSame(BookingChannel::Online, $actor->channel());
    }

    public function test_user_is_null_when_the_account_row_no_longer_exists(): void
    {
        $staff = User::factory()->staff()->create();
        $actor = Actor::staff($staff);

        DB::table('users')->where('id', $staff->id)->delete();

        $this->assertNull($actor->user());
    }

    public function test_human_actors_require_a_persisted_user(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Actor::staff(new User);
    }

    public function test_class_is_final_readonly_with_a_private_constructor(): void
    {
        $reflection = new ReflectionClass(Actor::class);

        $this->assertTrue($reflection->isFinal());
        $this->assertTrue($reflection->isReadOnly());
        $this->assertTrue($reflection->getConstructor()->isPrivate());
    }

    public function test_actor_cannot_be_modified_after_creation(): void
    {
        $actor = Actor::system();

        $this->expectException(Error::class);

        $actor->userId = 5;
    }
}
```

- [ ] **Step 2: Jalankan tes — harus GAGAL**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=ActorTest
```
Expected: FAIL — semua tes `Error: Class "App\Support\Actor" not found`.

- [ ] **Step 3: Buat `app/Support/Actor.php`**

```php
<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ActorType;
use App\Enums\BookingChannel;
use App\Models\User;
use InvalidArgumentException;

/**
 * Pelaku sebuah operasi (11 §6.1). Service tidak membaca request(): pelaku selalu dikirim eksplisit.
 */
final readonly class Actor
{
    private function __construct(public ActorType $type, public ?int $userId) {}

    public static function patient(User $user): self
    {
        return new self(ActorType::Patient, self::persistedId($user));
    }

    public static function staff(User $user): self
    {
        return new self(ActorType::Staff, self::persistedId($user));
    }

    /** Pelaku otomatis/perintah konsol: tanpa pengguna. */
    public static function system(): self
    {
        return new self(ActorType::System, null);
    }

    public function isPatient(): bool
    {
        return $this->type === ActorType::Patient;
    }

    public function isStaff(): bool
    {
        return $this->type === ActorType::Staff;
    }

    public function isSystem(): bool
    {
        return $this->type === ActorType::System;
    }

    /** Akun pelaku, atau NULL untuk pelaku sistem / akun yang barisnya sudah tidak ada. */
    public function user(): ?User
    {
        return $this->userId === null ? null : User::query()->find($this->userId);
    }

    /** Kanal pendaftaran menurut pelaku: staf ⇒ loket, selain itu online. */
    public function channel(): BookingChannel
    {
        return $this->isStaff() ? BookingChannel::Loket : BookingChannel::Online;
    }

    private static function persistedId(User $user): int
    {
        if (! $user->exists) {
            throw new InvalidArgumentException('Pelaku harus berupa akun yang sudah tersimpan.');
        }

        return (int) $user->getKey();
    }
}
```

- [ ] **Step 4: Jalankan tes `ActorTest` — harus LULUS, lalu commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=ActorTest
& $PHP vendor\bin\pint --test
```
Expected: 7 tes `ActorTest` PASS; `pint --test` = `PASS` (bila `FAIL`, `& $PHP vendor\bin\pint` lalu ulangi).

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git add app/Support/Actor.php tests/Feature/Support/ActorTest.php
git commit -m "feat(fase-1): value object Actor (pasien, staf, sistem) dan kanal pendaftaran"
```

- [ ] **Step 5: Tulis tes yang gagal `tests/Feature/Audit/AuditLoggerTest.php`**

```php
<?php

namespace Tests\Feature\Audit;

use App\Enums\ActorType;
use App\Enums\AppointmentStatus;
use App\Enums\PaymentType;
use App\Enums\SessionStatus;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Actor;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuditLoggerTest extends TestCase
{
    use RefreshDatabase;

    private function logger(): AuditLogger
    {
        return app(AuditLogger::class);
    }

    /** @return array<string, array<string, mixed>> */
    private function columnsOf(string $table): array
    {
        $columns = [];

        foreach (Schema::getColumns($table) as $column) {
            $columns[$column['name']] = $column;
        }

        return $columns;
    }

    /**
     * Membuat baris audit langsung (tanpa AuditLogger) untuk tes skema/model.
     *
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    private function makeLog(string $action = 'uji.aksi', ?int $userId = null, ?array $old = null, ?array $new = null): AuditLog
    {
        return AuditLog::query()->create([
            'actor_type' => ActorType::System,
            'user_id' => $userId,
            'action' => $action,
            'auditable_type' => User::class,
            'auditable_id' => null,
            'description' => 'Uji.',
            'old_values' => $old,
            'new_values' => $new,
        ]);
    }

    // ---------------------------------------------------------------- skema (09 §7.3)

    public function test_schema_audit_logs_table_matches_the_data_dictionary(): void
    {
        // nama => [type_name, tipe lengkap (null = tidak diperiksa), unsigned, nullable]
        $expected = [
            'id' => ['bigint', null, true, false],
            'actor_type' => ['varchar', 'varchar(10)', false, false],
            'user_id' => ['bigint', null, true, true],
            'action' => ['varchar', 'varchar(100)', false, false],
            'auditable_type' => ['varchar', 'varchar(255)', false, false],
            'auditable_id' => ['bigint', null, true, true],
            'description' => ['varchar', 'varchar(255)', false, false],
            'old_values' => ['longtext', null, false, true],
            'new_values' => ['longtext', null, false, true],
            'ip_address' => ['varchar', 'varchar(45)', false, true],
            'user_agent' => ['varchar', 'varchar(255)', false, true],
            'created_at' => ['timestamp', 'timestamp', false, false],
        ];

        $columns = $this->columnsOf('audit_logs');

        $this->assertSame(array_keys($expected), array_keys($columns));

        foreach ($expected as $name => [$typeName, $type, $unsigned, $nullable]) {
            $this->assertSame($typeName, $columns[$name]['type_name'], "type_name audit_logs.{$name}");

            if ($type !== null) {
                $this->assertSame($type, $columns[$name]['type'], "type audit_logs.{$name}");
            }

            $this->assertSame($unsigned, str_contains($columns[$name]['type'], 'unsigned'), "unsigned audit_logs.{$name}");
            $this->assertSame($nullable, $columns[$name]['nullable'], "nullable audit_logs.{$name}");
        }

        $this->assertTrue($columns['id']['auto_increment']);
        $this->assertStringStartsWith('current_timestamp', strtolower((string) $columns['created_at']['default']));
    }

    public function test_schema_audit_logs_indexes_use_the_dictionary_names(): void
    {
        $indexes = [];

        foreach (Schema::getIndexes('audit_logs') as $index) {
            $indexes[$index['name']] = $index;
        }

        $this->assertSame(['id'], $indexes['primary']['columns']);
        $this->assertTrue($indexes['primary']['primary']);

        $expected = [
            'audit_logs_auditable_type_auditable_id_index' => ['auditable_type', 'auditable_id'],
            'audit_logs_user_id_created_at_index' => ['user_id', 'created_at'],
            'audit_logs_action_created_at_index' => ['action', 'created_at'],
            'audit_logs_created_at_index' => ['created_at'],
        ];

        foreach ($expected as $name => $columns) {
            $this->assertArrayHasKey($name, $indexes, "Index {$name} belum ada.");
            $this->assertSame($columns, $indexes[$name]['columns'], $name);
            $this->assertFalse($indexes[$name]['unique'], $name);
        }
    }

    public function test_schema_user_foreign_key_nulls_the_user_on_delete(): void
    {
        $foreignKeys = Schema::getForeignKeys('audit_logs');

        $this->assertCount(1, $foreignKeys);
        $this->assertSame('audit_logs_user_id_foreign', $foreignKeys[0]['name']);
        $this->assertSame(['user_id'], $foreignKeys[0]['columns']);
        $this->assertSame('users', $foreignKeys[0]['foreign_table']);
        $this->assertSame(['id'], $foreignKeys[0]['foreign_columns']);
        $this->assertSame('set null', $foreignKeys[0]['on_delete']);
    }

    public function test_schema_deleting_the_user_keeps_the_audit_row_with_a_null_user(): void
    {
        $staff = User::factory()->staff()->create();
        $log = $this->makeLog('uji.fk', $staff->id);

        $this->assertSame($staff->id, AuditLog::query()->findOrFail($log->id)->user_id);

        DB::table('users')->where('id', $staff->id)->delete();

        $this->assertNull(AuditLog::query()->findOrFail($log->id)->user_id);
    }

    public function test_schema_json_columns_reject_invalid_json(): void
    {
        $this->expectException(QueryException::class);

        DB::table('audit_logs')->insert([
            'actor_type' => 'system',
            'action' => 'uji.json',
            'auditable_type' => User::class,
            'description' => 'Uji JSON tidak valid.',
            'old_values' => 'ini bukan json',
        ]);
    }

    public function test_schema_created_at_defaults_to_the_current_time(): void
    {
        DB::table('audit_logs')->insert([
            'actor_type' => 'system',
            'action' => 'uji.default',
            'auditable_type' => User::class,
            'description' => 'Uji default created_at.',
        ]);

        $createdAt = DB::table('audit_logs')->where('action', 'uji.default')->value('created_at');

        $this->assertNotNull($createdAt);
        $this->assertLessThan(120, abs(now()->diffInSeconds(CarbonImmutable::parse($createdAt), false)));
    }

    // ---------------------------------------------------------------- model AuditLog

    public function test_model_fillable_casts_and_timestamps_follow_the_blueprint(): void
    {
        $this->assertNull(AuditLog::UPDATED_AT);

        $model = new AuditLog;

        $this->assertNull($model->getUpdatedAtColumn());
        $this->assertSame('created_at', $model->getCreatedAtColumn());
        $this->assertSame([
            'actor_type',
            'user_id',
            'action',
            'auditable_type',
            'auditable_id',
            'description',
            'old_values',
            'new_values',
            'ip_address',
            'user_agent',
        ], $model->getFillable());
    }

    public function test_model_casts_and_relations_resolve_without_lazy_loading(): void
    {
        $staff = User::factory()->staff()->create();
        $log = AuditLog::query()->create([
            'actor_type' => ActorType::Staff,
            'user_id' => $staff->id,
            'action' => 'staff.created',
            'auditable_type' => $staff->getMorphClass(),
            'auditable_id' => $staff->getKey(),
            'description' => 'Akun staf dibuat.',
            'old_values' => null,
            'new_values' => ['name' => 'Budi'],
        ]);

        $loaded = AuditLog::query()->with(['user', 'auditable'])->findOrFail($log->id);

        $this->assertSame(ActorType::Staff, $loaded->actor_type);
        $this->assertNull($loaded->old_values);
        $this->assertSame(['name' => 'Budi'], $loaded->new_values);
        $this->assertInstanceOf(CarbonInterface::class, $loaded->created_at);
        $this->assertTrue($loaded->user->is($staff));
        $this->assertTrue($loaded->auditable->is($staff));
    }

    public function test_model_cannot_be_updated(): void
    {
        $log = $this->makeLog('uji.immutable');

        try {
            $log->update(['description' => 'Diubah.']);
            $this->fail('Pembaruan audit log seharusnya ditolak.');
        } catch (LogicException $e) {
            $this->assertSame('Audit log tidak dapat diubah atau dihapus.', $e->getMessage());
        }

        $this->assertSame('Uji.', AuditLog::query()->findOrFail($log->id)->description);
    }

    public function test_model_cannot_be_deleted(): void
    {
        $log = $this->makeLog('uji.immutable');

        try {
            $log->delete();
            $this->fail('Penghapusan audit log seharusnya ditolak.');
        } catch (LogicException $e) {
            $this->assertSame('Audit log tidak dapat diubah atau dihapus.', $e->getMessage());
        }

        $this->assertSame(1, AuditLog::query()->count());
    }

    public function test_model_scope_for_action_matches_the_prefix_literally(): void
    {
        foreach (['staff.created', 'staff.updated', 'auth.staff_login', 'auth.staff_login_failed', 'auth.staffXlogin', 'settings.updated'] as $action) {
            $this->makeLog($action);
        }

        $actions = static fn (string $prefix): array => AuditLog::query()->forAction($prefix)->orderBy('id')->pluck('action')->all();

        $this->assertSame(['staff.created', 'staff.updated'], $actions('staff.'));
        $this->assertSame(['auth.staff_login', 'auth.staff_login_failed'], $actions('auth.staff_login'));
        $this->assertSame(['auth.staff_login_failed'], $actions('auth.staff_login_failed'));
        $this->assertSame(['settings.updated'], $actions('settings.'));
        $this->assertSame([], $actions('staff%'));
    }

    public function test_model_scope_between_filters_by_created_at_inclusively(): void
    {
        $first = $this->makeLog('uji.satu');
        $second = $this->makeLog('uji.dua');
        $third = $this->makeLog('uji.tiga');

        foreach ([[$first, '2026-09-01 10:00:00'], [$second, '2026-09-15 12:00:00'], [$third, '2026-09-30 23:59:59']] as [$log, $at]) {
            AuditLog::query()->whereKey($log->id)->update(['created_at' => $at]);
        }

        $ids = static fn ($from, $to): array => AuditLog::query()->between($from, $to)->orderBy('id')->pluck('id')->all();

        $this->assertSame([$second->id, $third->id], $ids('2026-09-10', '2026-09-30'));
        $this->assertSame([$first->id], $ids(null, '2026-09-01'));
        $this->assertSame([$second->id, $third->id], $ids('2026-09-15 12:00:00', null));
        $this->assertSame(
            [$second->id],
            $ids(CarbonImmutable::parse('2026-09-15 12:00:00'), CarbonImmutable::parse('2026-09-15 12:00:00')),
        );
        $this->assertSame([$first->id, $second->id, $third->id], $ids(null, null));
    }

    // ---------------------------------------------------------------- log()

    public function test_log_constants_match_the_contract(): void
    {
        $this->assertSame('[disamarkan]', AuditLogger::REDACTED);
        $this->assertSame('[konten diubah]', AuditLogger::CONTENT_CHANGED);
        $this->assertSame('…[dipotong]', AuditLogger::TRUNCATED_SUFFIX);
    }

    public function test_log_records_the_authenticated_staff_as_actor(): void
    {
        $staff = User::factory()->staff()->create();
        $subject = User::factory()->staff()->create();

        $this->actingAs($staff);

        $log = $this->logger()->log('staff.deactivated', $subject, ['is_active' => true], ['is_active' => false], 'Akun staf dinonaktifkan.');

        $fresh = AuditLog::query()->findOrFail($log->id);

        $this->assertSame(ActorType::Staff, $fresh->actor_type);
        $this->assertSame($staff->id, $fresh->user_id);
        $this->assertSame('staff.deactivated', $fresh->action);
        $this->assertSame(User::class, $fresh->auditable_type);
        $this->assertSame($subject->id, $fresh->auditable_id);
        $this->assertSame('Akun staf dinonaktifkan.', $fresh->description);
        $this->assertSame(['is_active' => true], $fresh->old_values);
        $this->assertSame(['is_active' => false], $fresh->new_values);
        $this->assertNotNull($fresh->created_at);
    }

    public function test_log_records_the_authenticated_patient_as_actor(): void
    {
        $patient = User::factory()->create();

        $this->actingAs($patient);

        $log = $this->logger()->log('auth.password_changed', $patient, null, null, 'Pasien mengganti password.');

        $fresh = AuditLog::query()->findOrFail($log->id);

        $this->assertSame(ActorType::Patient, $fresh->actor_type);
        $this->assertSame($patient->id, $fresh->user_id);
    }

    public function test_log_without_a_logged_in_user_records_the_system_actor(): void
    {
        $log = $this->logger()->log('media.pruned', new User, null, ['rows' => 2], 'Pembersihan media.');

        $fresh = AuditLog::query()->findOrFail($log->id);

        $this->assertSame(ActorType::System, $fresh->actor_type);
        $this->assertNull($fresh->user_id);
    }

    public function test_log_prefers_the_explicit_actor_over_the_session_user(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff);

        $log = $this->logger()->log('staff.created', new User, null, ['name' => 'Baru'], 'Akun staf dibuat.', Actor::system());

        $fresh = AuditLog::query()->findOrFail($log->id);

        $this->assertSame(ActorType::System, $fresh->actor_type);
        $this->assertNull($fresh->user_id);
    }

    public function test_log_uses_the_model_morph_class_and_key(): void
    {
        $user = User::factory()->staff()->create();

        $log = $this->logger()->log('staff.updated', $user, null, ['name' => 'X'], 'Uji.', Actor::system());

        $this->assertSame($user->getMorphClass(), $log->auditable_type);
        $this->assertSame($user->getKey(), $log->auditable_id);
    }

    public function test_log_with_an_empty_model_stores_the_type_and_a_null_id(): void
    {
        $log = $this->logger()->log('report.exported', new User, null, ['row_count' => 3], 'Ekspor CSV.', Actor::system());

        $fresh = AuditLog::query()->findOrFail($log->id);

        $this->assertSame(User::class, $fresh->auditable_type);
        $this->assertNull($fresh->auditable_id);
    }

    public function test_log_stores_null_for_missing_or_empty_value_arrays(): void
    {
        $first = $this->logger()->log('uji.kosong', new User, null, null, 'Uji.', Actor::system());
        $second = $this->logger()->log('uji.kosong', new User, [], [], 'Uji.', Actor::system());

        foreach ([$first, $second] as $log) {
            $this->assertNull(DB::table('audit_logs')->where('id', $log->id)->value('old_values'));
            $this->assertNull(DB::table('audit_logs')->where('id', $log->id)->value('new_values'));
        }
    }

    public function test_log_redacts_before_storing_old_and_new_values(): void
    {
        $user = User::factory()->staff()->create();

        $log = $this->logger()->log(
            'patient.updated',
            $user,
            ['phone' => '081200000001', 'full_name' => 'Budi Lama'],
            ['phone' => '081200000002', 'full_name' => 'Budi Baru'],
            'Profil pasien diubah.',
            Actor::system(),
        );

        $fresh = AuditLog::query()->findOrFail($log->id);

        $this->assertSame(['phone' => '[disamarkan]', 'full_name' => 'Budi Lama'], $fresh->old_values);
        $this->assertSame(['phone' => '[disamarkan]', 'full_name' => 'Budi Baru'], $fresh->new_values);
    }

    public function test_log_truncates_the_description_to_255_characters(): void
    {
        $log = $this->logger()->log('uji.panjang', new User, null, null, str_repeat('é', 300), Actor::system());

        $stored = DB::table('audit_logs')->where('id', $log->id)->value('description');

        $this->assertSame(255, mb_strlen($stored));
        $this->assertSame(str_repeat('é', 255), $stored);
    }

    /** @return array<string, array{0: string}> */
    public static function invalidActions(): array
    {
        return [
            'kosong' => [''],
            'lebih dari 100 karakter' => [str_repeat('a', 101)],
        ];
    }

    #[DataProvider('invalidActions')]
    public function test_log_rejects_invalid_action_names(string $action): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->logger()->log($action, new User, null, null, 'Uji.', Actor::system());
    }

    public function test_log_does_not_swallow_write_errors(): void
    {
        $staff = User::factory()->staff()->create();
        $actor = Actor::staff($staff);

        DB::table('users')->where('id', $staff->id)->delete();

        $before = AuditLog::query()->count();

        try {
            $this->logger()->log('uji.gagal', new User, null, ['a' => 1], 'Pelaku sudah tidak ada.', $actor);
            $this->fail('Galat tulis (pelanggaran FK) seharusnya dilempar, bukan ditelan.');
        } catch (QueryException) {
            $this->assertSame($before, AuditLog::query()->count());
        }
    }

    // ---------------------------------------------------------------- konteks HTTP vs CLI (F1-R8)

    public function test_context_is_null_outside_routed_http_requests(): void
    {
        $log = $this->logger()->log('uji.cli', new User, null, ['a' => 1], 'Uji konteks CLI.');

        $fresh = AuditLog::query()->findOrFail($log->id);

        $this->assertNull($fresh->ip_address);
        $this->assertNull($fresh->user_agent);
    }

    public function test_context_records_ip_and_user_agent_inside_a_routed_request(): void
    {
        Route::get('/_uji/audit-http', function () {
            $log = app(AuditLogger::class)->log('uji.http', new User, null, ['a' => 1], 'Uji konteks HTTP.');

            return response()->json(['id' => $log->id]);
        });

        $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->withHeader('User-Agent', 'UjiAgen/1.0 (Windows)')
            ->getJson('/_uji/audit-http');

        $response->assertOk();

        $fresh = AuditLog::query()->findOrFail($response->json('id'));

        $this->assertSame('203.0.113.9', $fresh->ip_address);
        $this->assertSame('UjiAgen/1.0 (Windows)', $fresh->user_agent);
        $this->assertSame(ActorType::System, $fresh->actor_type);
    }

    public function test_context_takes_the_actor_from_the_session_inside_a_routed_request(): void
    {
        Route::get('/_uji/audit-http-staf', function () {
            $log = app(AuditLogger::class)->log('uji.http', new User, null, ['a' => 1], 'Uji pelaku dari sesi.');

            return response()->json(['id' => $log->id]);
        });

        $staff = User::factory()->staff()->create();

        $response = $this->actingAs($staff)->getJson('/_uji/audit-http-staf');

        $fresh = AuditLog::query()->findOrFail($response->json('id'));

        $this->assertSame(ActorType::Staff, $fresh->actor_type);
        $this->assertSame($staff->id, $fresh->user_id);
        $this->assertNotNull($fresh->ip_address);
    }

    public function test_context_strips_control_characters_and_limits_the_user_agent(): void
    {
        Route::get('/_uji/audit-ua', function () {
            request()->headers->set('User-Agent', "Uji\x07Agen\x1B[31m".str_repeat('a', 300));

            $log = app(AuditLogger::class)->log('uji.http', new User, null, ['a' => 1], 'Uji user agent.');

            return response()->json(['id' => $log->id]);
        });

        $response = $this->getJson('/_uji/audit-ua');

        $stored = AuditLog::query()->findOrFail($response->json('id'))->user_agent;
        $prefix = 'UjiAgen[31m';

        $this->assertSame(255, strlen($stored));
        $this->assertSame($prefix.str_repeat('a', 255 - strlen($prefix)), $stored);
        $this->assertDoesNotMatchRegularExpression('/[\x00-\x1F\x7F]/', $stored);
    }

    public function test_context_stores_a_null_user_agent_when_the_header_is_missing(): void
    {
        Route::get('/_uji/audit-tanpa-ua', function () {
            request()->headers->remove('User-Agent');

            $log = app(AuditLogger::class)->log('uji.http', new User, null, ['a' => 1], 'Uji tanpa user agent.');

            return response()->json(['id' => $log->id]);
        });

        $response = $this->getJson('/_uji/audit-tanpa-ua');

        $fresh = AuditLog::query()->findOrFail($response->json('id'));

        $this->assertNull($fresh->user_agent);
        $this->assertNotNull($fresh->ip_address);
    }

    // ---------------------------------------------------------------- redact()

    public function test_redact_always_masks_secret_keys_case_insensitively_and_recursively(): void
    {
        $result = $this->logger()->redact([
            'Password' => 'Rahasia123',
            'password_confirmation' => 'Rahasia123',
            'CURRENT_PASSWORD' => 'Lama123',
            'remember_token' => 'abc',
            'token' => 'xyz',
            'bpjs_number' => '0009900000011',
            'payment_reference' => 'PAY-1',
            'name' => 'Budi',
            'nested' => ['TOKEN' => 'abc', 'deeper' => ['Bpjs_Number' => '0009900000011', 'ok' => 1]],
            'list' => [['password' => 'x', 'label' => 'a']],
        ], 'staff.updated');

        $this->assertSame([
            'Password' => '[disamarkan]',
            'password_confirmation' => '[disamarkan]',
            'CURRENT_PASSWORD' => '[disamarkan]',
            'remember_token' => '[disamarkan]',
            'token' => '[disamarkan]',
            'bpjs_number' => '[disamarkan]',
            'payment_reference' => '[disamarkan]',
            'name' => 'Budi',
            'nested' => ['TOKEN' => '[disamarkan]', 'deeper' => ['Bpjs_Number' => '[disamarkan]', 'ok' => 1]],
            'list' => [['password' => '[disamarkan]', 'label' => 'a']],
        ], $result);
    }

    /** @return array<string, array{0: string, 1: bool}> */
    public static function piiActions(): array
    {
        return [
            'patient.updated' => ['patient.updated', true],
            'patient.status_changed' => ['patient.status_changed', true],
            'appointment.created' => ['appointment.created', true],
            'contact_message.status_changed' => ['contact_message.status_changed', true],
            'staff.updated' => ['staff.updated', false],
            'auth.staff_login_failed' => ['auth.staff_login_failed', false],
            'settings.updated' => ['settings.updated', false],
            'patients.exported (awalan mirip)' => ['patients.exported', false],
            'appointments.exported (awalan mirip)' => ['appointments.exported', false],
        ];
    }

    #[DataProvider('piiActions')]
    public function test_redact_masks_pii_only_for_patient_appointment_and_contact_message_actions(string $action, bool $masked): void
    {
        $values = [
            'address' => 'Jl. Melati No. 1',
            'phone' => '081200000001',
            'email' => 'budi@unpamhospital.test',
            'birth_date' => '1990-01-31',
            'complaint' => 'Sakit kepala',
            'message' => 'Mohon info jadwal',
            'full_name' => 'Budi Santoso',
        ];

        $result = $this->logger()->redact($values, $action);

        foreach (['address', 'phone', 'email', 'birth_date', 'complaint', 'message'] as $key) {
            $this->assertSame($masked ? '[disamarkan]' : $values[$key], $result[$key], "{$action} / {$key}");
        }

        $this->assertSame('Budi Santoso', $result['full_name']);
    }

    public function test_redact_records_long_text_fields_as_changed_content_only(): void
    {
        $result = $this->logger()->redact([
            'body' => '<p>Isi halaman yang panjang</p>',
            'BIO' => 'Riwayat dokter',
            'short_bio' => 'Ringkas',
            'description' => 'Deskripsi poliklinik',
            'title' => 'Judul tetap tampil',
        ], 'page.updated');

        $this->assertSame([
            'body' => '[konten diubah]',
            'BIO' => '[konten diubah]',
            'short_bio' => '[konten diubah]',
            'description' => '[konten diubah]',
            'title' => 'Judul tetap tampil',
        ], $result);
    }

    public function test_redact_masks_hidden_and_encrypted_attributes_of_the_model(): void
    {
        $model = new class extends Model
        {
            protected $hidden = ['Secret_Note'];

            protected $casts = ['api_key' => 'encrypted'];
        };

        $values = ['secret_note' => 'rahasia', 'api_key' => 'kunci-api', 'ok' => 'ya'];

        $this->assertSame(
            ['secret_note' => '[disamarkan]', 'api_key' => '[disamarkan]', 'ok' => 'ya'],
            $this->logger()->redact($values, 'uji.aksi', $model),
        );

        $this->assertSame($values, $this->logger()->redact($values, 'uji.aksi'));
    }

    public function test_redact_masks_the_hidden_attributes_of_user(): void
    {
        $result = $this->logger()->redact(['password' => 'hash', 'remember_token' => 'abc', 'name' => 'Budi'], 'staff.created', new User);

        $this->assertSame(['password' => '[disamarkan]', 'remember_token' => '[disamarkan]', 'name' => 'Budi'], $result);
    }

    public function test_redact_truncates_strings_longer_than_500_characters(): void
    {
        $result = $this->logger()->redact([
            'panjang' => str_repeat('a', 501),
            'pas' => str_repeat('b', 500),
            'multibyte' => str_repeat('é', 600),
            'nested' => ['catatan' => str_repeat('c', 700)],
        ], 'uji.aksi');

        $this->assertSame(str_repeat('a', 500).'…[dipotong]', $result['panjang']);
        $this->assertSame(str_repeat('b', 500), $result['pas']);
        $this->assertSame(str_repeat('é', 500).'…[dipotong]', $result['multibyte']);
        $this->assertSame(str_repeat('c', 500).'…[dipotong]', $result['nested']['catatan']);
    }

    public function test_redact_normalizes_enums_and_dates_and_keeps_scalars(): void
    {
        $result = $this->logger()->redact([
            'status' => AppointmentStatus::Confirmed,
            'at' => CarbonImmutable::parse('2026-10-03 08:15:30'),
            'native' => new DateTimeImmutable('2026-10-03 09:00:00'),
            'ids' => [1, 2],
            'active' => false,
            'count' => 0,
            'ratio' => 1.5,
            'nothing' => null,
            'list' => [PaymentType::Bpjs, ['x' => SessionStatus::Open]],
        ], 'session.updated');

        $this->assertSame([
            'status' => 'confirmed',
            'at' => '2026-10-03 08:15:30',
            'native' => '2026-10-03 09:00:00',
            'ids' => [1, 2],
            'active' => false,
            'count' => 0,
            'ratio' => 1.5,
            'nothing' => null,
            'list' => ['bpjs', ['x' => 'open']],
        ], $result);
    }

    public function test_redact_of_an_empty_array_is_empty(): void
    {
        $this->assertSame([], $this->logger()->redact([], 'uji.aksi'));
    }

    // ---------------------------------------------------------------- diff()

    public function test_diff_after_an_update_returns_previous_and_new_values_without_updated_at(): void
    {
        $user = User::factory()->create(['name' => 'Nama Lama']);

        $user->name = 'Nama Baru';
        $user->save();

        $this->assertSame(
            ['old' => ['name' => 'Nama Lama'], 'new' => ['name' => 'Nama Baru']],
            $this->logger()->diff($user),
        );
    }

    public function test_diff_before_saving_uses_the_dirty_attributes(): void
    {
        $user = User::query()->findOrFail(User::factory()->create(['name' => 'Nama Lama'])->id);

        $user->name = 'Nama Baru';
        $user->phone = '081200000001';

        $this->assertSame(
            ['old' => ['name' => 'Nama Lama', 'phone' => null], 'new' => ['name' => 'Nama Baru', 'phone' => '081200000001']],
            $this->logger()->diff($user),
        );
    }

    public function test_diff_is_empty_when_nothing_changed(): void
    {
        $user = User::query()->findOrFail(User::factory()->create()->id);

        $this->assertSame(['old' => [], 'new' => []], $this->logger()->diff($user));

        $user->save();

        $this->assertSame(['old' => [], 'new' => []], $this->logger()->diff($user));
    }

    // ---------------------------------------------------------------- SEK-11

    public function test_sek11_sensitive_values_never_reach_the_audit_logs_table(): void
    {
        $staff = User::factory()->staff()->create(['password' => 'Rahasia123']);
        $logger = $this->logger();

        $logger->log(
            'staff.password_reset',
            $staff,
            ['password' => 'Rahasia123', 'remember_token' => 'TokenRahasia777'],
            [
                'password' => 'Rahasia456',
                'password_confirmation' => 'Rahasia456',
                'current_password' => 'Rahasia123',
                'nested' => ['token' => 'Rahasia123', 'items' => [['bpjs_number' => '0009900000011']]],
            ],
            'Super Admin mengatur ulang password staf.',
            Actor::staff($staff),
        );

        $logger->log(
            'patient.updated',
            $staff,
            null,
            [
                'bpjs_number' => '0009900000011',
                'payment_reference' => 'PAY-0009900000011',
                'address' => 'Jl. Rahasia123 No. 1',
                'phone' => '081200000099',
                'email' => 'pasien.rahasia@unpamhospital.test',
                'complaint' => 'Keluhan Rahasia123',
            ],
            'Profil pasien diubah.',
            Actor::system(),
        );

        $secrets = ['Rahasia123', 'Rahasia456', 'TokenRahasia777', '0009900000011', 'PAY-0009900000011', '081200000099', 'pasien.rahasia@'];
        $everything = json_encode(DB::table('audit_logs')->get()->all(), JSON_UNESCAPED_UNICODE);

        foreach ($secrets as $secret) {
            $count = AuditLog::query()
                ->where('old_values', 'like', "%{$secret}%")
                ->orWhere('new_values', 'like', "%{$secret}%")
                ->count();

            $this->assertSame(0, $count, "Nilai sensitif '{$secret}' ditemukan di audit_logs.");
            $this->assertStringNotContainsString($secret, $everything, "Nilai sensitif '{$secret}' bocor ke baris audit_logs.");
        }

        $first = AuditLog::query()->where('action', 'staff.password_reset')->firstOrFail();
        $second = AuditLog::query()->where('action', 'patient.updated')->firstOrFail();

        $this->assertSame('[disamarkan]', $first->old_values['password']);
        $this->assertSame('[disamarkan]', $first->old_values['remember_token']);
        $this->assertSame('[disamarkan]', $first->new_values['password']);
        $this->assertSame('[disamarkan]', $first->new_values['password_confirmation']);
        $this->assertSame('[disamarkan]', $first->new_values['current_password']);
        $this->assertSame('[disamarkan]', $first->new_values['nested']['token']);
        $this->assertSame('[disamarkan]', $first->new_values['nested']['items'][0]['bpjs_number']);

        foreach (['bpjs_number', 'payment_reference', 'address', 'phone', 'email', 'complaint'] as $key) {
            $this->assertSame('[disamarkan]', $second->new_values[$key], $key);
        }
    }
}
```

- [ ] **Step 6: Jalankan tes — harus GAGAL**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=AuditLoggerTest
```
Expected: FAIL — tes `test_schema_*` gagal (tabel `audit_logs` belum ada: daftar kolom kosong, `Failed asserting that two arrays are identical`); tes `test_model_*` gagal `Error: Class "App\Models\AuditLog" not found`; tes `log`/`context`/`redact`/`diff`/`sek11` gagal `Class "App\Services\Audit\AuditLogger" not found` (via `app(AuditLogger::class)` ⇒ `Target class [App\Services\Audit\AuditLogger] does not exist`).

- [ ] **Step 7: Buat migration `database/migrations/2026_10_02_002300_create_audit_logs_table.php`**

Nama index ditulis eksplisit (sama dengan nama default Laravel; seluruhnya ≤ 64 karakter) karena nama itu bagian dari kontrak 09 §7.3. Kunci asing dipasang **setelah** semua index agar `user_id` dilayani index komposit `audit_logs_user_id_created_at_index`.

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabel append-only (09 §7.3): hanya `created_at`. Kolom `json` MariaDB = LONGTEXT
     * dengan CHECK json_valid otomatis.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('actor_type', 10);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action', 100);
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->string('description');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id'], 'audit_logs_auditable_type_auditable_id_index');
            $table->index(['user_id', 'created_at'], 'audit_logs_user_id_created_at_index');
            $table->index(['action', 'created_at'], 'audit_logs_action_created_at_index');
            $table->index('created_at', 'audit_logs_created_at_index');

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
```

- [ ] **Step 8: Buat model `app/Models/AuditLog.php`**

```php
<?php

namespace App\Models;

use App\Enums\ActorType;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * Jejak audit append-only (09 §7.3). Ditulis hanya lewat AuditLogger; tidak dapat diubah/dihapus.
 */
class AuditLog extends Model
{
    /** Tabel append-only: tidak ada updated_at. */
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'actor_type',
        'user_id',
        'action',
        'auditable_type',
        'auditable_id',
        'description',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'actor_type' => ActorType::class,
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Audit log tidak dapat diubah atau dihapus.');
        });

        static::deleting(function (): void {
            throw new LogicException('Audit log tidak dapat diubah atau dihapus.');
        });
    }

    /** Pelaku (NULL untuk pelaku sistem atau akun yang sudah dihapus). */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Model yang diaudit (`auditable_id` NULL untuk aksi tanpa satu baris). */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Aksi berawalan tertentu, mis. `staff.` atau `auth.staff_login`. Awalan dicocokkan harfiah
     * (karakter LIKE `%`, `_`, `\` di-escape).
     */
    public function scopeForAction(Builder $query, string $prefix): Builder
    {
        return $query->where('action', 'like', addcslashes($prefix, '\\%_').'%');
    }

    /**
     * Rentang `created_at` inklusif. Batas bernilai teks `Y-m-d` (tanpa jam): `$from` = awal hari,
     * `$to` = akhir hari. NULL = tanpa batas.
     */
    public function scopeBetween(Builder $query, CarbonInterface|string|null $from = null, CarbonInterface|string|null $to = null): Builder
    {
        if ($from !== null) {
            $query->where('created_at', '>=', Carbon::parse($from));
        }

        if ($to !== null) {
            $end = Carbon::parse($to);

            if (is_string($to) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) === 1) {
                $end = $end->endOfDay();
            }

            $query->where('created_at', '<=', $end);
        }

        return $query;
    }
}
```

- [ ] **Step 9: Jalankan tes skema dan model — harus LULUS**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=test_schema_
& $PHP artisan test --filter=test_model_
```
Expected: kedua perintah PASS (6 tes `test_schema_*` dan 6 tes `test_model_*`). Bila `test_schema_audit_logs_indexes_use_the_dictionary_names` gagal pada kolom indeks FK, jangan mengubah tes: pastikan `foreign()` dideklarasikan setelah semua `index()` di migration.

- [ ] **Step 10: Buat `app/Services/Audit/AuditLogger.php`**

```php
<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Actor;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Stringable;
use UnitEnum;

/**
 * Penulis jejak audit (11 §6.10, 12 §8). Dipanggil di dalam transaksi operasi yang diaudit;
 * galat tulis tidak ditelan agar jejak audit tidak pernah hilang diam-diam.
 */
final class AuditLogger
{
    public const REDACTED = '[disamarkan]';

    public const CONTENT_CHANGED = '[konten diubah]';

    public const TRUNCATED_SUFFIX = '…[dipotong]';

    /** Selalu disamarkan (00 E.6). */
    private const REDACT_ALWAYS = [
        'password',
        'password_confirmation',
        'current_password',
        'remember_token',
        'token',
        'bpjs_number',
        'payment_reference',
    ];

    /** Redaksi tingkat 2 (12 §8.1): hanya untuk aksi berawalan PII_ACTION_PREFIXES. */
    private const REDACT_PII = ['address', 'phone', 'email', 'birth_date', 'complaint', 'message'];

    private const PII_ACTION_PREFIXES = ['patient.', 'appointment.', 'contact_message.'];

    /** Teks panjang: hanya dicatat bahwa isinya berubah. */
    private const NAME_ONLY = ['body', 'bio', 'short_bio', 'description'];

    private const MAX_STRING_LENGTH = 500;

    private const MAX_DESCRIPTION_LENGTH = 255;

    private const MAX_ACTION_LENGTH = 100;

    private const MAX_USER_AGENT_LENGTH = 255;

    /**
     * Mencatat satu baris audit. `$model` wajib: untuk aksi tanpa satu baris kirim instance kosong
     * (mis. `new User`) sehingga `auditable_type` terisi dan `auditable_id` NULL (F1-R7).
     *
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public function log(string $action, Model $model, ?array $old, ?array $new, string $description, ?Actor $actor = null): AuditLog
    {
        if ($action === '' || mb_strlen($action) > self::MAX_ACTION_LENGTH) {
            throw new InvalidArgumentException('Nama aksi audit harus berisi 1 sampai 100 karakter.');
        }

        $actor ??= $this->resolveActor();
        $oldValues = $this->redact($old ?? [], $action, $model);
        $newValues = $this->redact($new ?? [], $action, $model);
        [$ipAddress, $userAgent] = $this->httpContext();

        return AuditLog::query()->create([
            'actor_type' => $actor->type,
            'user_id' => $actor->userId,
            'action' => $action,
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => $model->getKey(),
            'description' => mb_substr($description, 0, self::MAX_DESCRIPTION_LENGTH),
            'old_values' => $oldValues === [] ? null : $oldValues,
            'new_values' => $newValues === [] ? null : $newValues,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }

    /**
     * Atribut yang berubah, tanpa `updated_at`. Setelah update tersimpan: nilai baru dari `getChanges()`
     * dan nilai lama dari `getPrevious()` (setelah save(), `getOriginal()` sudah berisi nilai baru).
     * Sebelum disimpan: `getDirty()` dibandingkan dengan `getRawOriginal()`.
     *
     * @return array{old: array<string, mixed>, new: array<string, mixed>}
     */
    public function diff(Model $model): array
    {
        $changes = $model->getChanges();
        $previous = $model->getPrevious();

        if ($changes === []) {
            $changes = $model->getDirty();
            $previous = $model->getRawOriginal();
        }

        $changes = Arr::except($changes, ['updated_at']);

        return [
            'old' => Arr::only($previous, array_keys($changes)),
            'new' => $changes,
        ];
    }

    /**
     * Redaksi tingkat 1 dan 2 (12 §8.1) serta normalisasi nilai (enum, tanggal, string panjang).
     * Rekursif; kunci dicocokkan tanpa membedakan huruf besar/kecil.
     *
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    public function redact(array $values, string $action, ?Model $model = null): array
    {
        return $this->redactArray($values, $this->redactsPii($action), $this->protectedAttributes($model));
    }

    private function resolveActor(): Actor
    {
        $user = Auth::user();

        if ($user instanceof User) {
            return $user->isStaff() ? Actor::staff($user) : Actor::patient($user);
        }

        return Actor::system();
    }

    /**
     * Konteks hanya untuk permintaan HTTP yang ter-route (F1-R8); CLI/tes unit ⇒ NULL.
     *
     * @return array{0: ?string, 1: ?string} IP dan user agent
     */
    private function httpContext(): array
    {
        $request = request();

        if ($request->route() === null) {
            return [null, null];
        }

        $agent = $request->userAgent();

        if ($agent !== null) {
            $clean = preg_replace('/[\x00-\x1F\x7F]/', '', mb_scrub($agent)) ?? '';
            $agent = mb_substr($clean, 0, self::MAX_USER_AGENT_LENGTH);
        }

        return [$request->ip(), $agent === '' ? null : $agent];
    }

    private function redactsPii(string $action): bool
    {
        foreach (self::PII_ACTION_PREFIXES as $prefix) {
            if (str_starts_with($action, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Atribut `$hidden` dan atribut ber-cast `encrypted*` milik model (huruf kecil).
     *
     * @return list<string>
     */
    private function protectedAttributes(?Model $model): array
    {
        if ($model === null) {
            return [];
        }

        $attributes = $model->getHidden();

        foreach ($model->getCasts() as $attribute => $cast) {
            if (is_string($cast) && str_starts_with($cast, 'encrypted')) {
                $attributes[] = $attribute;
            }
        }

        return array_map(static fn (string $attribute): string => mb_strtolower($attribute), $attributes);
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @param  list<string>  $protected
     * @return array<array-key, mixed>
     */
    private function redactArray(array $values, bool $redactPii, array $protected): array
    {
        $result = [];

        foreach ($values as $key => $value) {
            if (is_string($key)) {
                $name = mb_strtolower($key);

                if (in_array($name, self::REDACT_ALWAYS, true) || in_array($name, $protected, true)) {
                    $result[$key] = self::REDACTED;

                    continue;
                }

                if ($redactPii && in_array($name, self::REDACT_PII, true)) {
                    $result[$key] = self::REDACTED;

                    continue;
                }

                if (in_array($name, self::NAME_ONLY, true)) {
                    $result[$key] = self::CONTENT_CHANGED;

                    continue;
                }
            }

            $result[$key] = $this->normalize($value, $redactPii, $protected);
        }

        return $result;
    }

    /**
     * @param  list<string>  $protected
     */
    private function normalize(mixed $value, bool $redactPii, array $protected): mixed
    {
        return match (true) {
            is_array($value) => $this->redactArray($value, $redactPii, $protected),
            $value instanceof BackedEnum => $this->normalize($value->value, $redactPii, $protected),
            $value instanceof UnitEnum => $value->name,
            $value instanceof DateTimeInterface => $value->format('Y-m-d H:i:s'),
            $value instanceof Arrayable => $this->redactArray($value->toArray(), $redactPii, $protected),
            $value instanceof Stringable => $this->limitString((string) $value),
            is_string($value) => $this->limitString($value),
            default => $value,
        };
    }

    private function limitString(string $value): string
    {
        $value = mb_scrub($value);

        if (mb_strlen($value) <= self::MAX_STRING_LENGTH) {
            return $value;
        }

        return mb_substr($value, 0, self::MAX_STRING_LENGTH).self::TRUNCATED_SUFFIX;
    }
}
```

- [ ] **Step 11: Jalankan seluruh `AuditLoggerTest` — harus LULUS**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=AuditLoggerTest
```
Expected: PASS — seluruh tes `AuditLoggerTest` lulus, termasuk `test_sek11_sensitive_values_never_reach_the_audit_logs_table` (pencarian `Rahasia123`, `0009900000011`, dan nilai sensitif lain = 0 baris), `test_context_records_ip_and_user_agent_inside_a_routed_request` (IP `203.0.113.9`), `test_context_is_null_outside_routed_http_requests`, `test_model_cannot_be_updated`/`test_model_cannot_be_deleted` (AUD-03), dan sembilan dataset `test_redact_masks_pii_only_for_patient_appointment_and_contact_message_actions`. Bila `test_context_strips_control_characters_and_limits_the_user_agent` gagal karena header diubah klien uji, pastikan `request()->headers->set(...)` dipanggil di dalam route (instance request yang sama dengan yang dibaca `AuditLogger`).

- [ ] **Step 12: Jalankan seluruh suite dan Pint**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test
& $PHP vendor\bin\pint --test
```
Expected: seluruh suite hijau; `pint --test` = `PASS`. Bila `FAIL`, jalankan `& $PHP vendor\bin\pint` lalu ulangi `--test`; setelah Pint, jalankan ulang `& $PHP artisan test --filter=AuditLoggerTest`.

- [ ] **Step 13: Commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git add database/migrations/2026_10_02_002300_create_audit_logs_table.php app/Models/AuditLog.php app/Services/Audit/AuditLogger.php tests/Feature/Audit/AuditLoggerTest.php
git commit -m "feat(fase-1): tabel audit_logs, model AuditLog immutable, AuditLogger dengan redaksi dan konteks HTTP"
```

---

### Task 5: Media — tabel `media`, model `Media`, `MediaService` (simpan/ganti/hapus/meta/urut), `ImageRules`, placeholder SVG

**Files:**
- Create: `database/migrations/2026_10_02_002100_create_media_table.php` (+ `CHECK chk_media_owner` via `DB::statement`)
- Create: `app/Models/Media.php`
- Create: `app/Services/Media/MediaService.php`
- Create: `app/Rules/ImageRules.php`
- Create: `public/images/placeholders/{doctor,clinic,facility,room,article,banner,gallery,logo}.svg`
- Create: `tests/Support/ImageFixtures.php`, `tests/Support/InteractsWithMedia.php`
- Create: `storage/app/public/.htaccess`; Modify: `storage/app/public/.gitignore` (Siklus I, F1-R17)
- Test: `tests/Unit/PublicStorageHardeningTest.php`, `tests/Unit/Support/ImageFixturesTest.php`, `tests/Feature/Media/MediaModelTest.php`, `tests/Feature/Media/MediaServiceTest.php`, `tests/Feature/Media/MediaServiceDefenseTest.php`, `tests/Feature/Media/MediaServiceLifecycleTest.php`, `tests/Feature/Media/MediaServiceMetaTest.php`, `tests/Feature/Media/ImageRulesTest.php`, `tests/Unit/PlaceholderSvgTest.php`

(Berkas tes dipecah per siklus TDD; kontrak hanya mewajibkan `MediaServiceTest`, `ImageRulesTest`, `PlaceholderSvgTest`. Berkas tambahan: `ImageFixturesTest`, `MediaModelTest`, `MediaServiceDefenseTest`, `MediaServiceLifecycleTest`, `MediaServiceMetaTest`, dan trait `InteractsWithMedia`.)

**Interfaces:**
- Consumes: `AuditLogger::log(string $action, Model $model, ?array $old, ?array $new, string $description, ?Actor $actor = null): AuditLog` dan `Actor::staff(User)` (Task 4), `User` + `UserFactory::staff()` (Task 2), `AuditLog` + `ActorType` (Task 1, 4).
- Produces:
  - Tabel `media` persis 09 §7.1 (UNIQUE `file_name`, index morph 4 kolom, FK `uploaded_by` SET NULL, CHECK `chk_media_owner`).
  - `App\Models\Media`: `$fillable` 17 kolom 11 §5.7; casts `size_bytes`,`width`,`height`,`sort_order` ⇒ integer; `model(): MorphTo`; `uploader(): BelongsTo` (`uploaded_by`); scope `inCollection(string $c)`, `orphaned(int $hours = 24)`; accessor `url` (disk `public` ⇒ `Storage::disk('public')->url("{directory}/{file_name}")`, disk `local` ⇒ `null`), `thumb_url` (thumb ⇒ URL thumbnail, tanpa thumb ⇒ `url`), `human_size` (mis. `"245 KB"`); `isPublic(): bool`.
  - `App\Services\Media\MediaService` (`final`, constructor `(AuditLogger $audit)`): `public const COLLECTIONS` persis 11 §6.9; konstanta `MAX_DIMENSION = 6000`, `RESIZE_WIDTH = 1600`, `THUMB_WIDTH = 400`, `JPEG_QUALITY = 82`, `WEBP_QUALITY = 82`; `store(UploadedFile $file, string $collection, ?Model $owner = null, array $meta = [], ?User $uploader = null): Media`; `replace(Model $owner, string $collection, UploadedFile $file, array $meta = [], ?User $uploader = null): Media`; `delete(Media $media): void`; `deleteFor(Model $owner): void`; `updateMeta(Media $media, ?string $altText, ?string $caption): Media`; `reorder(Model $owner, string $collection, array $orderedIds): void`. (`prune()` ditambahkan Task 8.)
  - `App\Rules\ImageRules` (`final`): `for(string $collection): array`, `messages(string $field, string $collection): array`; konstanta bantu `INVALID_IMAGE`, `NOT_IMAGE`, `WRONG_TYPE`, `UPLOAD_FAILED` (teks 12 §3.2) dipakai bersama `MediaService`.
  - Placeholder SVG per 13 §9.1.
- Perilaku `store()`, `replace()`, `delete()`, `updateMeta()`, `reorder()`: persis kerangka (11 §6.9, F1-R7). Catatan perilaku yang diputuskan penulis rencana: (1) `replace()` tidak menulis audit `media.uploaded`, hanya `media.replaced` (subjek = `Media` baru, old = `{media_id, file_name}` media lama atau NULL bila belum ada); (2) penghapusan media lama pada `replace()` memakai jalur internal tanpa audit `media.deleted` tambahan; (3) pemeriksaan MIME memakai `finfo` pada isi berkas, **bukan** ekstensi/`getClientMimeType()`; (4) pemeriksaan dimensi memakai `getimagesize()` **sebelum** dekode sehingga gambar bom piksel ditolak tanpa memuat bitmap; (5) berkas baru yang sempat tertulis ketika transaksi pemanggil di-rollback menjadi yatim dan dibersihkan `media:prune` (Task 8).
- Verifikasi API terhadap `vendor/` (dilakukan penulis rencana, versi terpasang Intervention Image 3.11.9, Laravel 12.69): `ImageManager::gd()->read($binary, BinaryImageDecoder::class)` (parameter ke-2 membatasi dekoder agar string seperti `"red"` tidak dibaca sebagai warna); kegagalan dekode ⇒ `Intervention\Image\Exceptions\DecoderException` (turunan `Intervention\Image\Exceptions\RuntimeException`); `scaleDown(width: 1600)` memodifikasi gambar **di tempat**, tidak memperbesar, rasio dijaga; thumbnail = `clone $image` lalu `scaleDown(width: 400)`; `toJpeg(quality: 82)`, `toPng()`, `toWebp(quality: 82)` mengembalikan `EncodedImageInterface` (`toString()`, `size()`, `mediaType()`); encoder GD membuang EXIF, PNG/WebP mempertahankan alfa; `Storage::fake()` mempertahankan konfigurasi `url` disk; `DB::afterCommit` pada tes `RefreshDatabase` langsung dijalankan bila tidak ada transaksi aplikasi, ditunda sampai commit bila di dalam `DB::transaction`, dan dibuang bila rollback (`Illuminate\Foundation\Testing\DatabaseTransactionsManager`); `Validator` memberi kegagalan `uploaded` untuk berkas dengan `UPLOAD_ERR_*`; `exif` aktif di `C:\xampp3\php\php.ini` (`exif_read_data` tersedia).

**Test IDs:** `MED-03`, `MED-04`, `MED-05` (tingkat service & `ImageRules`), `MED-06`, `SEK-09`, `SEK-10`, `MED-02` (bagian: ganti + rollback), Review Focus #3.

- [ ] **Step 1: Verifikasi prasyarat**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git branch --show-current
Test-Path app\Services\Audit\AuditLogger.php, app\Support\Actor.php, app\Models\AuditLog.php, tests\Support\InteractsWithStaff.php
& $PHP -r "echo (int) function_exists('exif_read_data'), (int) gd_info()['WebP Support'], PHP_EOL;"
& $PHP artisan test
```
Expected: `fase-1-fondasi`; empat baris `True`; `11`; seluruh tes (Task 1–4) hijau. (MySQL di `C:\xampp3` harus sudah **Start**; bila tidak, `artisan test` gagal dengan `SQLSTATE[HY000] [2002]`.)

#### Siklus A — fixture gambar (`tests/Support/ImageFixtures.php`)

- [ ] **Step 2: Tulis tes fixture `tests/Unit/Support/ImageFixturesTest.php`**

```php
<?php

namespace Tests\Unit\Support;

use finfo;
use PHPUnit\Framework\TestCase;
use Tests\Support\ImageFixtures;

class ImageFixturesTest extends TestCase
{
    private function mime(string $path): string
    {
        return (string) (new finfo(FILEINFO_MIME_TYPE))->file($path);
    }

    public function test_jpeg_has_the_requested_size_and_a_real_jpeg_mime(): void
    {
        $file = ImageFixtures::jpeg(640, 480, 'a.jpg');
        $path = $file->getRealPath();

        $this->assertSame('image/jpeg', $this->mime($path));
        $this->assertSame([640, 480], array_slice(getimagesize($path), 0, 2));
        $this->assertSame('a.jpg', $file->getClientOriginalName());
        $this->assertFalse(ImageFixtures::hasExifMarker($path));
    }

    public function test_gps_jpeg_carries_exif_and_gps_before_upload_and_is_still_a_valid_jpeg(): void
    {
        $file = ImageFixtures::jpegWithGps(3000, 2000);
        $path = $file->getRealPath();

        $this->assertTrue(ImageFixtures::hasExifMarker($path));
        $this->assertTrue(ImageFixtures::hasGpsTag($path));
        $this->assertSame([3000, 2000], array_slice(getimagesize($path), 0, 2));
        $this->assertSame("\xFF\xD8\xFF\xE1", substr((string) file_get_contents($path), 0, 4));
    }

    public function test_oversized_jpeg_reaches_the_requested_size_and_stays_a_valid_jpeg(): void
    {
        $file = ImageFixtures::oversizedJpeg(2100);

        $this->assertGreaterThan(2048 * 1024, filesize($file->getRealPath()));
        $this->assertSame('image/jpeg', $this->mime($file->getRealPath()));
        $this->assertSame(800, getimagesize($file->getRealPath())[0]);
    }

    public function test_transparent_png_has_a_transparent_corner_and_an_opaque_centre(): void
    {
        $file = ImageFixtures::transparentPng(600, 400);
        $bytes = (string) file_get_contents($file->getRealPath());

        $this->assertSame('image/png', $this->mime($file->getRealPath()));
        $this->assertSame(127, ImageFixtures::alphaAt($bytes, 2, 2));
        $this->assertSame(0, ImageFixtures::alphaAt($bytes, 300, 200));
    }

    public function test_png_and_webp_have_their_real_mime_types(): void
    {
        $this->assertSame('image/png', $this->mime(ImageFixtures::png(300, 200)->getRealPath()));
        $this->assertSame('image/webp', $this->mime(ImageFixtures::webp(300, 200)->getRealPath()));
    }

    public function test_png_named_jpg_keeps_a_jpg_client_name_but_png_content(): void
    {
        $file = ImageFixtures::pngNamedJpg(300, 200, 'foto.jpg');

        $this->assertSame('foto.jpg', $file->getClientOriginalName());
        $this->assertSame('image/png', $this->mime($file->getRealPath()));
    }

    public function test_text_named_jpg_is_plain_text(): void
    {
        $file = ImageFixtures::textNamedJpg();

        $this->assertSame('bukan-gambar.jpg', $file->getClientOriginalName());
        $this->assertSame('text/plain', $this->mime($file->getRealPath()));
    }

    public function test_svg_fixture_is_svg_and_contains_a_script(): void
    {
        $file = ImageFixtures::svgWithScript();

        $this->assertSame('image/svg+xml', $this->mime($file->getRealPath()));
        $this->assertStringContainsString('<script>', (string) file_get_contents($file->getRealPath()));
    }

    public function test_php_with_jpeg_header_passes_finfo_but_is_not_a_decodable_image(): void
    {
        $file = ImageFixtures::phpWithJpegHeader();
        $path = $file->getRealPath();

        $this->assertSame('image/jpeg', $this->mime($path));
        $this->assertFalse(@getimagesize($path));
        $this->assertStringContainsString('<?php', (string) file_get_contents($path));
    }

    public function test_php_script_fixture_is_not_an_image_mime(): void
    {
        $this->assertStringStartsNotWith('image/', $this->mime(ImageFixtures::phpScript()->getRealPath()));
    }

    public function test_corrupt_png_has_a_valid_header_but_cannot_be_decoded(): void
    {
        $path = ImageFixtures::corruptPng(600, 400)->getRealPath();

        $this->assertSame('image/png', $this->mime($path));
        $this->assertSame([600, 400], array_slice(getimagesize($path), 0, 2));

        set_error_handler(static fn (): bool => true);

        try {
            $decoded = imagecreatefromstring((string) file_get_contents($path));
        } finally {
            restore_error_handler();
        }

        $this->assertFalse($decoded);
    }
}
```

- [ ] **Step 3: Jalankan tes — harus GAGAL**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=ImageFixturesTest
```
Expected: FAIL — semua tes `Error: Class "Tests\Support\ImageFixtures" not found`.

- [ ] **Step 4: Implementasi `tests/Support/ImageFixtures.php`**

```php
<?php

namespace Tests\Support;

use GdImage;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Pabrik berkas gambar uji (dan berkas berbahaya) berbasis GD.
 *
 * Semua metode mengembalikan Illuminate\Http\UploadedFile mode uji. Berkas fisik berada di
 * direktori sementara per-proses dan dihapus saat proses PHP berakhir. Nama klien (parameter
 * $name) hanya dipakai sebagai nama asli unggahan; berkas fisik memakai nama acak.
 */
final class ImageFixtures
{
    private static ?string $directory = null;

    public static function jpeg(int $width = 800, int $height = 800, string $name = 'foto.jpg'): UploadedFile
    {
        return self::upload(self::jpegBytes($width, $height), $name, 'image/jpeg');
    }

    /**
     * JPEG yang membawa segmen EXIF (APP1) berisi koordinat GPS, disisipkan tepat setelah SOI (FFD8).
     */
    public static function jpegWithGps(int $width = 3000, int $height = 2000, string $name = 'gps.jpg'): UploadedFile
    {
        $bytes = self::jpegBytes($width, $height);

        return self::upload(substr($bytes, 0, 2).self::gpsExifSegment().substr($bytes, 2), $name, 'image/jpeg');
    }

    /**
     * JPEG valid yang diberi padding nol setelah penanda akhir sehingga ukuran berkas >= $kilobytes.
     */
    public static function oversizedJpeg(int $kilobytes, int $width = 800, int $height = 800, string $name = 'besar.jpg'): UploadedFile
    {
        $bytes = self::jpegBytes($width, $height);
        $padding = max(0, $kilobytes * 1024 - strlen($bytes));

        return self::upload($bytes.str_repeat("\0", $padding), $name, 'image/jpeg');
    }

    public static function png(int $width = 800, int $height = 600, string $name = 'gambar.png'): UploadedFile
    {
        return self::upload(self::pngBytes(self::canvas($width, $height)), $name, 'image/png');
    }

    /**
     * PNG dengan latar transparan penuh dan elips merah opak di tengah.
     */
    public static function transparentPng(int $width = 600, int $height = 400, string $name = 'transparan.png'): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagefilledellipse(
            $image,
            intdiv($width, 2),
            intdiv($height, 2),
            intdiv($width, 2),
            intdiv($height, 2),
            imagecolorallocatealpha($image, 200, 30, 30, 0),
        );

        return self::upload(self::pngBytes($image), $name, 'image/png');
    }

    public static function webp(int $width = 900, int $height = 600, string $name = 'gambar.webp'): UploadedFile
    {
        ob_start();
        imagewebp(self::canvas($width, $height), null, 80);

        return self::upload((string) ob_get_clean(), $name, 'image/webp');
    }

    /**
     * Isi PNG sungguhan dengan nama klien .jpg (ekstensi menipu).
     */
    public static function pngNamedJpg(int $width = 800, int $height = 600, string $name = 'foto.jpg'): UploadedFile
    {
        return self::upload(self::pngBytes(self::canvas($width, $height)), $name, 'image/jpeg');
    }

    /**
     * Header PNG lengkap (IHDR valid) diikuti data rusak: getimagesize() berhasil, dekode gagal.
     */
    public static function corruptPng(int $width = 600, int $height = 400, string $name = 'rusak.png'): UploadedFile
    {
        $png = self::pngBytes(self::canvas($width, $height));

        return self::upload(substr($png, 0, 33).str_repeat("\0", 64), $name, 'image/png');
    }

    public static function textNamedJpg(string $name = 'bukan-gambar.jpg'): UploadedFile
    {
        return self::upload("Ini hanya teks biasa, bukan gambar.\n", $name, 'image/jpeg');
    }

    public static function svgWithScript(string $name = 'logo.svg'): UploadedFile
    {
        return self::upload(
            '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="100"><script>alert(1)</script><rect width="200" height="100" fill="#0A4D8C"/></svg>',
            $name,
            'image/svg+xml',
        );
    }

    /**
     * Berkas "PHP berkepala JPEG": FFD8FFE0 diikuti kode PHP.
     */
    public static function phpWithJpegHeader(string $name = 'shell.php.jpg'): UploadedFile
    {
        return self::upload("\xFF\xD8\xFF\xE0<?php echo 'x'; ?>".str_repeat('A', 128), $name, 'image/jpeg');
    }

    public static function phpScript(string $name = 'x.phtml'): UploadedFile
    {
        return self::upload("<?php echo 'x';\n", $name, 'application/x-httpd-php');
    }

    /**
     * Penanda EXIF ("Exif\0\0") ada di byte berkas. Tidak bergantung pada ekstensi exif.
     */
    public static function hasExifMarker(string $path): bool
    {
        return str_contains((string) file_get_contents($path), "Exif\0\0");
    }

    /**
     * Tag GPS terbaca. Memakai exif_read_data() bila ekstensi aktif, selain itu jatuh ke penanda byte.
     */
    public static function hasGpsTag(string $path): bool
    {
        if (! function_exists('exif_read_data')) {
            return self::hasExifMarker($path);
        }

        $data = @exif_read_data($path);

        return is_array($data) && isset($data['GPSLatitudeRef']);
    }

    /**
     * Nilai alfa GD (0 = opak, 127 = transparan penuh) pada titik tertentu.
     */
    public static function alphaAt(string $binary, int $x, int $y): int
    {
        $image = imagecreatefromstring($binary);

        if ($image === false) {
            throw new RuntimeException('Gambar uji tidak dapat didekode.');
        }

        return (imagecolorat($image, $x, $y) >> 24) & 0x7F;
    }

    private static function canvas(int $width, int $height): GdImage
    {
        $image = imagecreatetruecolor($width, $height);
        $stripe = max(1, intdiv($height, 48));

        for ($y = 0, $i = 0; $y < $height; $y += $stripe, $i++) {
            $color = imagecolorallocate($image, 20 + ($i * 5) % 200, 80 + ($i * 3) % 120, 160);
            imagefilledrectangle($image, 0, $y, $width, min($height, $y + $stripe), $color);
        }

        return $image;
    }

    private static function jpegBytes(int $width, int $height): string
    {
        ob_start();
        imagejpeg(self::canvas($width, $height), null, 85);

        return (string) ob_get_clean();
    }

    private static function pngBytes(GdImage $image): string
    {
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    /**
     * Segmen APP1 "Exif" minimal: IFD0 menunjuk ke GPS IFD berisi LatitudeRef/Latitude/LongitudeRef/Longitude.
     */
    private static function gpsExifSegment(): string
    {
        $gps = pack('n', 4)
            .pack('nnN', 1, 2, 2)."S\0\0\0"
            .pack('nnN', 2, 5, 3).pack('N', 80)
            .pack('nnN', 3, 2, 2)."E\0\0\0"
            .pack('nnN', 4, 5, 3).pack('N', 104)
            .pack('N', 0);

        $tiff = 'MM'.pack('n', 42).pack('N', 8)
            .pack('n', 1).pack('nnNN', 0x8825, 4, 1, 26).pack('N', 0)
            .$gps
            .pack('NNNNNN', 6, 1, 21, 1, 0, 1)
            .pack('NNNNNN', 106, 1, 42, 1, 0, 1);

        $payload = "Exif\0\0".$tiff;

        return "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;
    }

    private static function upload(string $bytes, string $clientName, string $mimeType): UploadedFile
    {
        $path = self::directory().DIRECTORY_SEPARATOR.uniqid('fx_', true).'.bin';

        if (file_put_contents($path, $bytes) === false) {
            throw new RuntimeException("Gagal menulis berkas uji: {$path}");
        }

        return new UploadedFile($path, $clientName, $mimeType, null, true);
    }

    private static function directory(): string
    {
        if (self::$directory === null) {
            $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'unpam-hospital-fixtures-'.getmypid();

            if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
                throw new RuntimeException("Gagal membuat direktori fixture: {$directory}");
            }

            self::$directory = $directory;

            register_shutdown_function(static function () use ($directory): void {
                foreach (glob($directory.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
                    @unlink($file);
                }

                @rmdir($directory);
            });
        }

        return self::$directory;
    }
}
```

- [ ] **Step 5: Jalankan tes — harus LULUS**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=ImageFixturesTest
```
Expected: PASS (11 tes). Bukti EXIF/GPS: `hasExifMarker` dan `hasGpsTag` bernilai `true` sebelum unggah (ekstensi `exif` aktif di `C:\xampp3\php\php.ini`, sehingga `exif_read_data` membaca `GPSLatitudeRef`; bila ekstensi dimatikan, `hasGpsTag` jatuh ke penanda byte `Exif\0\0`).

- [ ] **Step 6: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint --test
git add tests/Support/ImageFixtures.php tests/Unit/Support/ImageFixturesTest.php
git commit -m "test(fase-1): fixture gambar GD (EXIF GPS, PNG transparan, WebP, berkas berbahaya)"
```
Expected: Pint `PASS` (bila melapor berkas, jalankan `& $PHP vendor\bin\pint <berkas>` lalu ulangi `--test`).

#### Siklus B — tabel `media`, model `Media`, trait tes

- [ ] **Step 7: Tulis trait `tests/Support/InteractsWithMedia.php` dan tes `tests/Feature/Media/MediaModelTest.php`**

`tests/Support/InteractsWithMedia.php`:

```php
<?php

namespace Tests\Support;

use App\Models\Media;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pembuat baris `media` langsung (tanpa MediaService) untuk tes model, prune, dan pengaturan.
 */
trait InteractsWithMedia
{
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function mediaAttributes(array $overrides = []): array
    {
        $uuid = (string) Str::uuid();

        return array_merge([
            'model_type' => null,
            'model_id' => null,
            'collection' => 'editor',
            'disk' => 'public',
            'directory' => 'editor/2026/10',
            'file_name' => $uuid.'.jpg',
            'original_name' => 'contoh.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size_bytes' => 250880,
            'width' => 800,
            'height' => 600,
            'thumb_path' => 'editor/2026/10/thumbs/'.$uuid.'.jpg',
            'alt_text' => 'Gambar contoh',
            'caption' => null,
            'sort_order' => 1,
            'uploaded_by' => null,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createMediaRow(array $overrides = [], ?CarbonInterface $createdAt = null): Media
    {
        $media = Media::query()->create($this->mediaAttributes($overrides));

        if ($createdAt !== null) {
            DB::table('media')->where('id', $media->id)->update(['created_at' => $createdAt]);
            $media->refresh();
        }

        return $media;
    }
}
```

`tests/Feature/Media/MediaModelTest.php`:

```php
<?php

namespace Tests\Feature\Media;

use App\Models\Media;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Support\InteractsWithMedia;
use Tests\TestCase;

class MediaModelTest extends TestCase
{
    use InteractsWithMedia;
    use RefreshDatabase;

    public function test_media_table_has_the_columns_from_the_data_dictionary(): void
    {
        $this->assertSame([
            'id', 'model_type', 'model_id', 'collection', 'disk', 'directory', 'file_name',
            'original_name', 'mime_type', 'extension', 'size_bytes', 'width', 'height',
            'thumb_path', 'alt_text', 'caption', 'sort_order', 'uploaded_by', 'created_at', 'updated_at',
        ], Schema::getColumnListing('media'));
    }

    public function test_disk_defaults_to_public_and_sort_order_to_zero(): void
    {
        $attributes = $this->mediaAttributes();
        unset($attributes['disk'], $attributes['sort_order']);
        $id = DB::table('media')->insertGetId($attributes);

        $row = DB::table('media')->where('id', $id)->first();

        $this->assertSame('public', $row->disk);
        $this->assertSame(0, (int) $row->sort_order);
    }

    public function test_indexes_and_foreign_key_follow_the_data_dictionary(): void
    {
        $indexes = collect(Schema::getIndexes('media'))->keyBy('name');

        $this->assertSame(
            ['model_type', 'model_id', 'collection', 'sort_order'],
            $indexes['media_model_type_model_id_collection_sort_order_index']['columns'],
        );
        $this->assertTrue($indexes['media_file_name_unique']['unique']);

        $foreignKeys = collect(Schema::getForeignKeys('media'))->keyBy('name');

        $this->assertSame(['uploaded_by'], $foreignKeys['media_uploaded_by_foreign']['columns']);
        $this->assertSame('users', $foreignKeys['media_uploaded_by_foreign']['foreign_table']);
        $this->assertSame('set null', strtolower($foreignKeys['media_uploaded_by_foreign']['on_delete']));
    }

    public function test_file_name_must_be_unique(): void
    {
        $this->createMediaRow(['file_name' => 'sama.jpg']);

        $this->expectException(QueryException::class);

        $this->createMediaRow(['file_name' => 'sama.jpg']);
    }

    public function test_deleting_the_uploader_sets_uploaded_by_to_null(): void
    {
        $user = User::factory()->staff()->create();
        $media = $this->createMediaRow(['uploaded_by' => $user->id]);

        $user->delete();

        $this->assertNull($media->fresh()->uploaded_by);
    }

    public function test_owner_check_constraint_rejects_a_half_filled_morph_pair(): void
    {
        $this->expectException(QueryException::class);

        DB::table('media')->insert($this->mediaAttributes(['model_type' => 'App\Models\User', 'model_id' => null]));
    }

    public function test_owner_check_constraint_rejects_a_model_id_without_a_type(): void
    {
        $this->expectException(QueryException::class);

        DB::table('media')->insert($this->mediaAttributes(['model_type' => null, 'model_id' => 5]));
    }

    public function test_owner_check_constraint_accepts_both_empty_or_both_filled(): void
    {
        $orphan = $this->createMediaRow();
        $owned = $this->createMediaRow(['model_type' => 'App\Models\User', 'model_id' => 7]);

        $this->assertNull($orphan->model_id);
        $this->assertSame(7, (int) $owned->model_id);
    }

    public function test_fillable_columns_match_the_blueprint(): void
    {
        $this->assertSame([
            'model_type', 'model_id', 'collection', 'disk', 'directory', 'file_name', 'original_name',
            'mime_type', 'extension', 'size_bytes', 'width', 'height', 'thumb_path', 'alt_text',
            'caption', 'sort_order', 'uploaded_by',
        ], (new Media)->getFillable());
    }

    public function test_numeric_columns_are_cast_to_integers(): void
    {
        $media = Media::query()->find($this->createMediaRow()->id);

        $this->assertIsInt($media->size_bytes);
        $this->assertIsInt($media->width);
        $this->assertIsInt($media->height);
        $this->assertIsInt($media->sort_order);
    }

    public function test_url_and_thumb_url_use_the_public_disk(): void
    {
        Storage::fake('public');
        $media = $this->createMediaRow([
            'directory' => 'photo/2026/10',
            'file_name' => 'abc.jpg',
            'thumb_path' => 'photo/2026/10/thumbs/abc.jpg',
        ]);

        $this->assertSame(Storage::disk('public')->url('photo/2026/10/abc.jpg'), $media->url);
        $this->assertSame(Storage::disk('public')->url('photo/2026/10/thumbs/abc.jpg'), $media->thumb_url);
        $this->assertStringEndsWith('/storage/photo/2026/10/abc.jpg', $media->url);
        $this->assertTrue($media->isPublic());
    }

    public function test_thumb_url_falls_back_to_url_when_there_is_no_thumbnail(): void
    {
        Storage::fake('public');
        $media = $this->createMediaRow(['directory' => 'photo/2026/10', 'file_name' => 'abc.jpg', 'thumb_path' => null]);

        $this->assertSame($media->url, $media->thumb_url);
    }

    public function test_private_disk_has_no_public_url(): void
    {
        $media = $this->createMediaRow(['disk' => 'local']);

        $this->assertFalse($media->isPublic());
        $this->assertNull($media->url);
        $this->assertNull($media->thumb_url);
    }

    public function test_human_size_is_formatted_for_bytes_kilobytes_and_megabytes(): void
    {
        $this->assertSame('500 B', $this->createMediaRow(['size_bytes' => 500])->human_size);
        $this->assertSame('245 KB', $this->createMediaRow(['size_bytes' => 250880])->human_size);
        $this->assertSame('1,5 MB', $this->createMediaRow(['size_bytes' => 1572864])->human_size);
    }

    public function test_in_collection_scope_filters_by_collection(): void
    {
        $photo = $this->createMediaRow(['collection' => 'photo']);
        $this->createMediaRow(['collection' => 'cover']);

        $this->assertSame([$photo->id], Media::query()->inCollection('photo')->pluck('id')->all());
    }

    public function test_orphaned_scope_selects_ownerless_rows_older_than_the_given_hours(): void
    {
        $stale = $this->createMediaRow([], now()->subHours(25));
        $this->createMediaRow([], now()->subMinutes(30));
        $this->createMediaRow(['model_type' => 'App\Models\User', 'model_id' => 1], now()->subHours(72));

        $this->assertSame([$stale->id], Media::query()->orphaned()->pluck('id')->all());
        $this->assertSame([$stale->id], Media::query()->orphaned(1)->pluck('id')->all());
        $this->assertCount(2, Media::query()->orphaned(0)->get());
    }

    public function test_relations_resolve_the_uploader_and_the_owner(): void
    {
        $user = User::factory()->staff()->create();
        $media = $this->createMediaRow([
            'uploaded_by' => $user->id,
            'model_type' => $user->getMorphClass(),
            'model_id' => $user->id,
        ]);

        $media = Media::query()->with(['uploader', 'model'])->find($media->id);

        $this->assertTrue($media->uploader->is($user));
        $this->assertTrue($media->model->is($user));
    }
}
```

- [ ] **Step 8: Jalankan tes — harus GAGAL**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=MediaModelTest
```
Expected: FAIL — `Class "App\Models\Media" not found` pada tes yang memakai model dan `Table 'unpam_hospital_test.media' doesn't exist` / kolom tidak ada pada tes skema.

- [ ] **Step 9: Tulis migration `database/migrations/2026_10_02_002100_create_media_table.php`**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table): void {
            $table->id();
            $table->string('model_type')->nullable();
            $table->unsignedBigInteger('model_id')->nullable();
            $table->string('collection', 40);
            $table->string('disk', 10)->default('public');
            $table->string('directory');
            $table->string('file_name', 100)->unique();
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->string('extension', 10);
            $table->unsignedInteger('size_bytes');
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('height');
            $table->string('thumb_path')->nullable();
            $table->string('alt_text');
            $table->string('caption')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['model_type', 'model_id', 'collection', 'sort_order']);
        });

        DB::statement('ALTER TABLE media ADD CONSTRAINT chk_media_owner CHECK ((model_type IS NULL AND model_id IS NULL) OR (model_type IS NOT NULL AND model_id IS NOT NULL))');
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
```

- [ ] **Step 10: Tulis model `app/Models/Media.php`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    protected $table = 'media';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'model_type',
        'model_id',
        'collection',
        'disk',
        'directory',
        'file_name',
        'original_name',
        'mime_type',
        'extension',
        'size_bytes',
        'width',
        'height',
        'thumb_path',
        'alt_text',
        'caption',
        'sort_order',
        'uploaded_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function scopeInCollection(Builder $query, string $collection): Builder
    {
        return $query->where('collection', $collection);
    }

    /**
     * Baris tanpa pemilik (unggahan editor yang belum terikat) yang lebih tua dari $hours jam.
     */
    public function scopeOrphaned(Builder $query, int $hours = 24): Builder
    {
        return $query->whereNull('model_id')->where('created_at', '<', now()->subHours($hours));
    }

    public function isPublic(): bool
    {
        return $this->disk === 'public';
    }

    protected function url(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->isPublic()
            ? Storage::disk('public')->url($this->directory.'/'.$this->file_name)
            : null);
    }

    protected function thumbUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            if (! $this->isPublic()) {
                return null;
            }

            return $this->thumb_path !== null
                ? Storage::disk('public')->url($this->thumb_path)
                : $this->url;
        });
    }

    protected function humanSize(): Attribute
    {
        return Attribute::get(function (): string {
            $bytes = (int) $this->size_bytes;

            return match (true) {
                $bytes < 1024 => $bytes.' B',
                $bytes < 1048576 => (int) round($bytes / 1024).' KB',
                default => number_format($bytes / 1048576, 1, ',', '.').' MB',
            };
        });
    }
}
```

- [ ] **Step 11: Jalankan tes — harus LULUS**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=MediaModelTest
```
Expected: PASS (17 tes), termasuk pelanggaran `chk_media_owner` (MariaDB galat 4025) dan nilai default `disk`/`sort_order`.

- [ ] **Step 12: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint --test
git add database/migrations/2026_10_02_002100_create_media_table.php app/Models/Media.php tests/Support/InteractsWithMedia.php tests/Feature/Media/MediaModelTest.php
git commit -m "feat(fase-1): tabel media dengan CHECK chk_media_owner dan model Media"
```
Expected: Pint `PASS`.

#### Siklus C — `MediaService::store()` jalur sukses (MED-03, MED-04, MED-06, Review Focus #3)

- [ ] **Step 13: Tulis tes `tests/Feature/Media/MediaServiceTest.php`**

```php
<?php

namespace Tests\Feature\Media;

use App\Enums\ActorType;
use App\Models\AuditLog;
use App\Models\Media;
use App\Models\User;
use App\Services\Media\MediaService;
use finfo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\ImageFixtures;
use Tests\TestCase;

class MediaServiceTest extends TestCase
{
    use RefreshDatabase;

    private MediaService $service;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->service = app(MediaService::class);
        $this->owner = User::factory()->staff()->create();
    }

    /**
     * @return array{alt_text: string}
     */
    private function meta(): array
    {
        return ['alt_text' => 'Foto contoh'];
    }

    private function path(Media $media): string
    {
        return $media->directory.'/'.$media->file_name;
    }

    private function stored(Media $media): string
    {
        return Storage::disk('public')->path($this->path($media));
    }

    private function mime(string $path): string
    {
        return (string) (new finfo(FILEINFO_MIME_TYPE))->file($path);
    }

    public function test_store_saves_a_reencoded_jpeg_with_uuid_name_thumbnail_and_row(): void
    {
        $media = $this->service->store(
            ImageFixtures::jpeg(800, 800, 'Foto Dokter.JPG'),
            'photo',
            $this->owner,
            ['alt_text' => 'Foto contoh dokter', 'caption' => 'Dokter jaga pagi'],
            $this->owner,
        );

        $this->assertSame($this->owner->getMorphClass(), $media->model_type);
        $this->assertSame($this->owner->id, $media->model_id);
        $this->assertSame('photo', $media->collection);
        $this->assertSame('public', $media->disk);
        $this->assertSame('photo/'.now()->format('Y/m'), $media->directory);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}(-[0-9a-f]{4}){3}-[0-9a-f]{12}\.jpg$/', $media->file_name);
        $this->assertSame('Foto Dokter.JPG', $media->original_name);
        $this->assertSame('image/jpeg', $media->mime_type);
        $this->assertSame('jpg', $media->extension);
        $this->assertSame(800, $media->width);
        $this->assertSame(800, $media->height);
        $this->assertSame('Foto contoh dokter', $media->alt_text);
        $this->assertSame('Dokter jaga pagi', $media->caption);
        $this->assertSame(1, $media->sort_order);
        $this->assertSame($this->owner->id, $media->uploaded_by);

        $disk = Storage::disk('public');
        $disk->assertExists($this->path($media));
        $this->assertSame($media->directory.'/thumbs/'.$media->file_name, $media->thumb_path);
        $disk->assertExists($media->thumb_path);
        $this->assertSame($disk->size($this->path($media)), $media->size_bytes);
        $this->assertSame('image/jpeg', $this->mime($this->stored($media)));
    }

    public function test_thumbnail_is_400_px_wide_and_never_upscaled(): void
    {
        $disk = Storage::disk('public');

        $large = $this->service->store(ImageFixtures::jpeg(2400, 1600), 'cover', $this->owner, $this->meta());
        $thumb = getimagesizefromstring($disk->get($large->thumb_path));

        $this->assertSame(1600, $large->width);
        $this->assertSame(400, $thumb[0]);
        $this->assertEqualsWithDelta(267, $thumb[1], 1);

        $small = $this->service->store(ImageFixtures::jpeg(300, 300), 'photo', $this->owner, $this->meta());
        $smallThumb = getimagesizefromstring($disk->get($small->thumb_path));

        $this->assertSame(300, $small->width);
        $this->assertSame(300, $smallThumb[0]);
    }

    public function test_large_jpeg_is_resized_and_exif_gps_is_removed(): void
    {
        $upload = ImageFixtures::jpegWithGps(3000, 2000);

        $this->assertTrue(ImageFixtures::hasExifMarker($upload->getRealPath()));
        $this->assertTrue(ImageFixtures::hasGpsTag($upload->getRealPath()));

        $media = $this->service->store($upload, 'cover', $this->owner, $this->meta());

        $this->assertLessThanOrEqual(1600, $media->width);
        $this->assertSame(1600, $media->width);
        $this->assertEqualsWithDelta(1067, $media->height, 1);
        $this->assertSame(1600, getimagesize($this->stored($media))[0]);
        $this->assertFalse(ImageFixtures::hasExifMarker($this->stored($media)));
        $this->assertFalse(ImageFixtures::hasGpsTag($this->stored($media)));
        $this->assertFalse(ImageFixtures::hasExifMarker(Storage::disk('public')->path($media->thumb_path)));
    }

    public function test_original_name_is_sanitized_and_file_name_is_a_uuid(): void
    {
        $media = $this->service->store(
            ImageFixtures::jpeg(800, 800, '../../x<script>.jpg'),
            'photo',
            $this->owner,
            $this->meta(),
        );

        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}(-[0-9a-f]{4}){3}-[0-9a-f]{12}\.jpg$/', $media->file_name);
        $this->assertSame('xscript.jpg', $media->original_name);
        $this->assertStringNotContainsString('..', $media->original_name);
        $this->assertStringNotContainsString('/', $media->original_name);
        $this->assertStringNotContainsString('<', $media->original_name);

        $files = Storage::disk('public')->allFiles();
        $this->assertCount(2, $files);

        foreach ($files as $file) {
            $this->assertStringStartsWith('photo/'.now()->format('Y/m').'/', $file);
            $this->assertStringNotContainsString('script', $file);
            $this->assertStringNotContainsString('..', $file);
        }
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function originalNames(): array
    {
        return [
            'nama biasa dipertahankan' => ['Foto Dokter.JPG', 'Foto Dokter.JPG'],
            'path traversal dan tag dibuang' => ['../../x<script>.jpg', 'xscript.jpg'],
            'backslash windows' => ['C:\\Users\\admin\\c.png', 'c.png'],
            'hanya simbol memakai cadangan' => ['???', 'gambar'],
            'titik berulang dirapatkan' => ['..jpg', 'jpg'],
            'dipotong 150 karakter' => [str_repeat('a', 200).'.jpg', str_repeat('a', 150)],
            'karakter kontrol dibuang' => ["foto\x07nama.jpg", 'fotonama.jpg'],
            'spasi berulang dirapatkan' => ['Foto   Dokter  (1).jpg', 'Foto Dokter (1).jpg'],
            'huruf unicode dipertahankan' => ['Dokter Ünal.jpg', 'Dokter Ünal.jpg'],
        ];
    }

    #[DataProvider('originalNames')]
    public function test_original_name_sanitizing_rules(string $clientName, string $expected): void
    {
        $media = $this->service->store(ImageFixtures::jpeg(800, 800, $clientName), 'photo', $this->owner, $this->meta());

        $this->assertSame($expected, $media->original_name);
    }

    public function test_png_named_jpg_is_stored_as_png_after_its_real_content(): void
    {
        $media = $this->service->store(ImageFixtures::pngNamedJpg(900, 600, 'foto.jpg'), 'gallery', $this->owner, $this->meta());

        $this->assertSame('png', $media->extension);
        $this->assertSame('image/png', $media->mime_type);
        $this->assertStringEndsWith('.png', $media->file_name);
        $this->assertSame('foto.jpg', $media->original_name);
        $this->assertSame('image/png', $this->mime($this->stored($media)));
        $this->assertSame('image/png', $this->mime(Storage::disk('public')->path($media->thumb_path)));
    }

    public function test_uppercase_jpg_extension_is_stored_in_lowercase(): void
    {
        $media = $this->service->store(ImageFixtures::jpeg(800, 800, 'FOTO.JPG'), 'photo', $this->owner, $this->meta());

        $this->assertSame('jpg', $media->extension);
        $this->assertStringEndsWith('.jpg', $media->file_name);
        $this->assertSame('FOTO.JPG', $media->original_name);
    }

    public function test_transparent_png_stays_png_with_its_transparency(): void
    {
        $media = $this->service->store(ImageFixtures::transparentPng(600, 400), 'logo', null, $this->meta());
        $disk = Storage::disk('public');

        $this->assertSame('png', $media->extension);
        $this->assertSame('image/png', $media->mime_type);

        $main = $disk->get($this->path($media));
        $this->assertSame(127, ImageFixtures::alphaAt($main, 2, 2));
        $this->assertSame(0, ImageFixtures::alphaAt($main, 300, 200));

        $this->assertSame(127, ImageFixtures::alphaAt($disk->get($media->thumb_path), 1, 1));
    }

    public function test_webp_is_stored_as_webp(): void
    {
        $media = $this->service->store(ImageFixtures::webp(900, 600), 'gallery', $this->owner, $this->meta());

        $this->assertSame('webp', $media->extension);
        $this->assertSame('image/webp', $media->mime_type);
        $this->assertStringEndsWith('.webp', $media->file_name);
        $this->assertSame('image/webp', $this->mime($this->stored($media)));
        $this->assertSame(900, $media->width);
    }

    public function test_double_extension_names_never_reach_the_disk(): void
    {
        $media = $this->service->store(ImageFixtures::jpeg(800, 800, 'shell.php.jpg'), 'photo', $this->owner, $this->meta());

        $this->assertSame('shell.php.jpg', $media->original_name);
        $this->assertDoesNotMatchRegularExpression('/\.php/i', $media->file_name);

        foreach (Storage::disk('public')->allFiles() as $file) {
            $this->assertSame('jpg', pathinfo($file, PATHINFO_EXTENSION));
            $this->assertDoesNotMatchRegularExpression('/php|phtml/i', $file);
        }
    }

    public function test_no_stored_file_uses_the_original_name(): void
    {
        $this->service->store(ImageFixtures::jpeg(800, 800, 'rahasia-asli.jpg'), 'photo', $this->owner, $this->meta());

        foreach (Storage::disk('public')->allFiles() as $file) {
            $this->assertStringNotContainsString('rahasia', $file);
        }
    }

    public function test_sort_order_increments_per_owner_and_collection(): void
    {
        $first = $this->service->store(ImageFixtures::jpeg(900, 600), 'gallery', $this->owner, $this->meta());
        $second = $this->service->store(ImageFixtures::jpeg(900, 600), 'gallery', $this->owner, $this->meta());
        $third = $this->service->store(ImageFixtures::jpeg(900, 600), 'gallery', $this->owner, $this->meta());

        $this->assertSame([1, 2, 3], [$first->sort_order, $second->sort_order, $third->sort_order]);

        $otherOwner = User::factory()->staff()->create();
        $this->assertSame(1, $this->service->store(ImageFixtures::jpeg(900, 600), 'gallery', $otherOwner, $this->meta())->sort_order);
        $this->assertSame(1, $this->service->store(ImageFixtures::jpeg(900, 600), 'cover', $this->owner, $this->meta())->sort_order);
    }

    public function test_store_without_owner_leaves_the_morph_columns_null(): void
    {
        $first = $this->service->store(ImageFixtures::jpeg(400, 300), 'editor', null, $this->meta());
        $second = $this->service->store(ImageFixtures::jpeg(400, 300), 'editor', null, $this->meta());

        $this->assertNull($first->model_type);
        $this->assertNull($first->model_id);
        $this->assertNull($first->uploaded_by);
        $this->assertSame([1, 2], [$first->sort_order, $second->sort_order]);
    }

    public function test_store_writes_one_uploaded_audit_row(): void
    {
        $media = $this->service->store(ImageFixtures::jpeg(800, 800), 'photo', $this->owner, $this->meta(), $this->owner);

        $logs = AuditLog::query()->where('action', 'media.uploaded')->get();

        $this->assertCount(1, $logs);
        $log = $logs->first();
        $this->assertSame(Media::class, $log->auditable_type);
        $this->assertSame($media->id, $log->auditable_id);
        $this->assertSame(ActorType::Staff, $log->actor_type);
        $this->assertSame($this->owner->id, $log->user_id);
        $this->assertNull($log->old_values);
        $this->assertSame($media->id, $log->new_values['media_id']);
        $this->assertSame($media->file_name, $log->new_values['file_name']);
        $this->assertSame('photo', $log->new_values['collection']);
    }

    public function test_store_without_uploader_audits_as_system(): void
    {
        $this->service->store(ImageFixtures::jpeg(400, 300), 'editor', null, $this->meta());

        $log = AuditLog::query()->where('action', 'media.uploaded')->firstOrFail();

        $this->assertSame(ActorType::System, $log->actor_type);
        $this->assertNull($log->user_id);
    }

    public function test_unknown_collection_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Koleksi media tidak dikenal: avatar.');

        $this->service->store(ImageFixtures::jpeg(800, 800), 'avatar', $this->owner, $this->meta());
    }

    public function test_collection_catalogue_matches_the_blueprint(): void
    {
        $this->assertSame([
            'photo' => ['single' => true, 'disk' => 'public', 'max_kb' => 2048, 'min' => [300, 300]],
            'cover' => ['single' => true, 'disk' => 'public', 'max_kb' => 2048, 'min' => [800, 450]],
            'gallery' => ['single' => false, 'disk' => 'public', 'max_kb' => 2048, 'min' => [600, 400]],
            'image' => ['single' => true, 'disk' => 'public', 'max_kb' => 4096, 'min' => [1200, 400]],
            'photos' => ['single' => false, 'disk' => 'public', 'max_kb' => 2048, 'min' => [600, 400]],
            'logo' => ['single' => true, 'disk' => 'public', 'max_kb' => 2048, 'min' => [120, 40]],
            'editor' => ['single' => false, 'disk' => 'public', 'max_kb' => 2048, 'min' => [200, 100]],
        ], MediaService::COLLECTIONS);

        $this->assertSame(6000, MediaService::MAX_DIMENSION);
        $this->assertSame(1600, MediaService::RESIZE_WIDTH);
        $this->assertSame(400, MediaService::THUMB_WIDTH);
        $this->assertSame(82, MediaService::JPEG_QUALITY);
        $this->assertSame(82, MediaService::WEBP_QUALITY);
    }

    public function test_files_are_removed_when_the_database_write_fails(): void
    {
        Media::creating(function (): void {
            throw new RuntimeException('simulasi galat tulis');
        });

        $thrown = null;

        try {
            $this->service->store(ImageFixtures::jpeg(800, 800), 'photo', $this->owner, $this->meta());
        } catch (RuntimeException $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown);
        $this->assertSame('simulasi galat tulis', $thrown->getMessage());
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame(0, Media::query()->count());
    }
}
```

- [ ] **Step 14: Jalankan tes — harus GAGAL**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=MediaServiceTest
```
Expected: FAIL — setiap tes `Illuminate\Contracts\Container\BindingResolutionException: Target class [App\Services\Media\MediaService] does not exist.` (galat pada `setUp`).

- [ ] **Step 15: Implementasi `app/Services/Media/MediaService.php` (versi jalur sukses)**

```php
<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Models\Media;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Actor;
use finfo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Decoders\BinaryImageDecoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\EncodedImageInterface;
use Intervention\Image\Interfaces\ImageInterface;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class MediaService
{
    /**
     * Koleksi baku 00 E.6; dimensi minimum 00 K; maksimum 6000 x 6000 untuk semua.
     */
    public const COLLECTIONS = [
        'photo' => ['single' => true, 'disk' => 'public', 'max_kb' => 2048, 'min' => [300, 300]],
        'cover' => ['single' => true, 'disk' => 'public', 'max_kb' => 2048, 'min' => [800, 450]],
        'gallery' => ['single' => false, 'disk' => 'public', 'max_kb' => 2048, 'min' => [600, 400]],
        'image' => ['single' => true, 'disk' => 'public', 'max_kb' => 4096, 'min' => [1200, 400]],
        'photos' => ['single' => false, 'disk' => 'public', 'max_kb' => 2048, 'min' => [600, 400]],
        'logo' => ['single' => true, 'disk' => 'public', 'max_kb' => 2048, 'min' => [120, 40]],
        'editor' => ['single' => false, 'disk' => 'public', 'max_kb' => 2048, 'min' => [200, 100]],
    ];

    public const MAX_DIMENSION = 6000;

    public const RESIZE_WIDTH = 1600;

    public const THUMB_WIDTH = 400;

    public const JPEG_QUALITY = 82;

    public const WEBP_QUALITY = 82;

    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Simpan satu gambar: dekode, perkecil (lebar maks 1600), encode ulang sesuai MIME nyata,
     * simpan dengan nama UUID beserta thumbnail 400 px, catat baris `media` dan audit.
     *
     * @param  array<string, mixed>  $meta  `alt_text` (wajib), `caption` (opsional)
     */
    public function store(UploadedFile $file, string $collection, ?Model $owner = null, array $meta = [], ?User $uploader = null): Media
    {
        $config = $this->configFor($collection);

        return $this->createMedia($file, $collection, $config, $owner, $meta, $uploader, function (Media $media) use ($uploader): void {
            $this->audit->log(
                'media.uploaded',
                $media,
                null,
                ['media_id' => $media->id, 'collection' => $media->collection, 'file_name' => $media->file_name],
                "Gambar diunggah ke koleksi {$media->collection}.",
                $uploader !== null ? Actor::staff($uploader) : null,
            );
        });
    }

    private function createMedia(UploadedFile $file, string $collection, array $config, ?Model $owner, array $meta, ?User $uploader, callable $audit): Media
    {
        // $audit dipanggil di dalam transaksi tepat setelah baris media dibuat.
        $altText = trim((string) ($meta['alt_text'] ?? ''));
        $caption = $this->nullableTrim($meta['caption'] ?? null);
        $binary = (string) file_get_contents((string) $file->getRealPath());
        $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->buffer($binary);

        $image = ImageManager::gd()->read($binary, BinaryImageDecoder::class);
        $image->scaleDown(width: self::RESIZE_WIDTH);
        $thumbnail = clone $image;
        $thumbnail->scaleDown(width: self::THUMB_WIDTH);
        $encoded = $this->encode($image, $mime);
        $thumbnailEncoded = $this->encode($thumbnail, $mime);
        $width = $image->width();
        $height = $image->height();

        $extension = self::EXTENSIONS[$mime];
        $name = Str::uuid()->toString().'.'.$extension;
        $directory = $collection.'/'.now()->format('Y/m');
        $thumbPath = $directory.'/thumbs/'.$name;
        $disk = Storage::disk($config['disk']);
        $written = [];

        try {
            foreach ([$directory.'/'.$name => $encoded, $thumbPath => $thumbnailEncoded] as $path => $content) {
                if (! $disk->put($path, $content->toString())) {
                    throw new RuntimeException("Gagal menulis berkas gambar: {$path}");
                }

                $written[] = $path;
            }

            return DB::transaction(function () use ($file, $collection, $config, $owner, $uploader, $audit, $altText, $caption, $directory, $name, $extension, $mime, $encoded, $width, $height, $thumbPath): Media {
                $media = Media::query()->create([
                    'model_type' => $owner?->getMorphClass(),
                    'model_id' => $owner?->getKey(),
                    'collection' => $collection,
                    'disk' => $config['disk'],
                    'directory' => $directory,
                    'file_name' => $name,
                    'original_name' => $this->sanitizeOriginalName($file->getClientOriginalName()),
                    'mime_type' => $mime,
                    'extension' => $extension,
                    'size_bytes' => $encoded->size(),
                    'width' => $width,
                    'height' => $height,
                    'thumb_path' => $thumbPath,
                    'alt_text' => $altText,
                    'caption' => $caption,
                    'sort_order' => $this->nextSortOrder($owner, $collection),
                    'uploaded_by' => $uploader?->getKey(),
                ]);

                $audit($media);

                return $media;
            });
        } catch (Throwable $e) {
            $disk->delete($written);

            throw $e;
        }
    }

    private function encode(ImageInterface $image, string $mime): EncodedImageInterface
    {
        return match ($mime) {
            'image/jpeg' => $image->toJpeg(quality: self::JPEG_QUALITY),
            'image/png' => $image->toPng(),
            'image/webp' => $image->toWebp(quality: self::WEBP_QUALITY),
            default => throw new InvalidArgumentException("Tipe gambar tidak didukung: {$mime}."),
        };
    }

    /**
     * Nama asli hanya metadata: basename, huruf/angka/spasi/._()- saja, maks 150 karakter.
     */
    private function sanitizeOriginalName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[^\p{L}\p{N} ._()\-]/u', '', $name) ?? '';
        $name = preg_replace('/\.{2,}/', '.', $name) ?? '';
        $name = preg_replace('/\s+/u', ' ', $name) ?? '';
        $name = trim(mb_substr(trim($name, ' .'), 0, 150));

        return $name === '' ? 'gambar' : $name;
    }

    private function nextSortOrder(?Model $owner, string $collection): int
    {
        $query = Media::query()->where('collection', $collection);

        if ($owner === null) {
            $query->whereNull('model_type')->whereNull('model_id');
        } else {
            $query->where('model_type', $owner->getMorphClass())->where('model_id', $owner->getKey());
        }

        return ((int) $query->max('sort_order')) + 1;
    }

    private function nullableTrim(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @return array{single: bool, disk: string, max_kb: int, min: array{0: int, 1: int}}
     */
    private function configFor(string $collection): array
    {
        return self::COLLECTIONS[$collection]
            ?? throw new InvalidArgumentException("Koleksi media tidak dikenal: {$collection}.");
    }
}
```

- [ ] **Step 16: Jalankan tes — harus LULUS**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=MediaServiceTest
```
Expected: PASS (26 tes: 17 tes biasa + 9 kasus `originalNames`). Bukti MED-03: lebar tersimpan 1600 ≤ 1600 dan penanda `Exif\0\0` serta tag GPS hilang di berkas utama dan thumbnail; MED-04: `file_name` UUID, `original_name` = `xscript.jpg`, tidak ada berkas di luar `photo/{Y}/{m}/`.

- [ ] **Step 17: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint --test
git add app/Services/Media/MediaService.php tests/Feature/Media/MediaServiceTest.php
git commit -m "feat(fase-1): MediaService::store dengan re-encode GD, thumbnail, nama UUID, dan audit"
```
Expected: Pint `PASS`.

#### Siklus D — `ImageRules` (MED-05 tingkat aturan Validator)

- [ ] **Step 18: Tulis tes `tests/Feature/Media/ImageRulesTest.php`**

```php
<?php

namespace Tests\Feature\Media;

use App\Rules\ImageRules;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ImageFixtures;
use Tests\TestCase;

class ImageRulesTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: int, 2: int, 3: int, 4: string, 5: string}>
     */
    public static function collections(): array
    {
        return [
            'photo' => ['photo', 2048, 300, 300, 'gambar', 'gambar'],
            'cover' => ['cover', 2048, 800, 450, 'gambar', 'gambar'],
            'gallery' => ['gallery', 2048, 600, 400, 'gambar', 'gambar'],
            'image' => ['image', 4096, 1200, 400, 'banner', 'banner'],
            'photos' => ['photos', 2048, 600, 400, 'gambar', 'gambar'],
            'logo' => ['logo', 2048, 120, 40, 'gambar', 'logo'],
            'editor' => ['editor', 2048, 200, 100, 'gambar', 'gambar'],
        ];
    }

    /**
     * @param  string  $maxSubject  kata benda pada pesan ukuran ("gambar" atau "banner")
     * @param  string  $dimensionSubject  kata benda pada pesan dimensi ("gambar", "banner", atau "logo")
     */
    #[DataProvider('collections')]
    public function test_rules_follow_blueprint_section_3_2(string $collection, int $maxKb, int $minWidth, int $minHeight, string $maxSubject, string $dimensionSubject): void
    {
        $this->assertSame([
            'file',
            'image',
            'mimes:jpg,jpeg,png,webp',
            'mimetypes:image/jpeg,image/png,image/webp',
            'max:'.$maxKb,
            "dimensions:min_width={$minWidth},min_height={$minHeight},max_width=6000,max_height=6000",
        ], ImageRules::for($collection));
    }

    #[DataProvider('collections')]
    public function test_messages_are_the_indonesian_texts_from_blueprint_section_3_2(string $collection, int $maxKb, int $minWidth, int $minHeight, string $maxSubject, string $dimensionSubject): void
    {
        $messages = ImageRules::messages('foto', $collection);

        $this->assertSame([
            'foto.file' => 'File harus berupa gambar.',
            'foto.image' => 'File harus berupa gambar.',
            'foto.mimes' => 'File harus berupa gambar JPG, PNG, atau WebP.',
            'foto.mimetypes' => 'File harus berupa gambar JPG, PNG, atau WebP.',
            'foto.max' => 'Ukuran '.$maxSubject.' maksimal '.($maxKb / 1024).' MB.',
            'foto.dimensions' => "Dimensi {$dimensionSubject} minimal {$minWidth} x {$minHeight} piksel dan maksimal 6000 x 6000 piksel.",
            'foto.uploaded' => 'Gambar gagal diunggah. Ukuran file melebihi batas server.',
        ], $messages);
    }

    public function test_helper_constants_hold_the_shared_texts(): void
    {
        $this->assertSame('File tidak dapat diproses sebagai gambar yang valid.', ImageRules::INVALID_IMAGE);
        $this->assertSame('File harus berupa gambar.', ImageRules::NOT_IMAGE);
        $this->assertSame('File harus berupa gambar JPG, PNG, atau WebP.', ImageRules::WRONG_TYPE);
        $this->assertSame('Gambar gagal diunggah. Ukuran file melebihi batas server.', ImageRules::UPLOAD_FAILED);
    }

    public function test_unknown_collection_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ImageRules::for('avatar');
    }

    public function test_unknown_collection_is_rejected_for_messages(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ImageRules::messages('file', 'avatar');
    }

    /**
     * @return list<string>
     */
    private function errorsFor(UploadedFile $file, string $collection): array
    {
        $validator = Validator::make(
            ['file' => $file],
            ['file' => ImageRules::for($collection)],
            ImageRules::messages('file', $collection),
        );

        return $validator->errors()->get('file');
    }

    public function test_valid_jpeg_passes(): void
    {
        $this->assertSame([], $this->errorsFor(ImageFixtures::jpeg(800, 800), 'photo'));
    }

    public function test_png_named_jpg_passes_because_the_real_mime_is_allowed(): void
    {
        $this->assertSame([], $this->errorsFor(ImageFixtures::pngNamedJpg(800, 600), 'photo'));
    }

    public function test_webp_and_transparent_png_pass(): void
    {
        $this->assertSame([], $this->errorsFor(ImageFixtures::webp(900, 600), 'gallery'));
        $this->assertSame([], $this->errorsFor(ImageFixtures::transparentPng(600, 400), 'logo'));
    }

    public function test_svg_is_rejected_with_the_wrong_type_message(): void
    {
        $errors = $this->errorsFor(ImageFixtures::svgWithScript(), 'photo');

        $this->assertContains('File harus berupa gambar JPG, PNG, atau WebP.', $errors);
    }

    public function test_text_file_named_jpg_is_rejected_with_the_wrong_type_message(): void
    {
        $errors = $this->errorsFor(ImageFixtures::textNamedJpg(), 'photo');

        $this->assertContains('File harus berupa gambar JPG, PNG, atau WebP.', $errors);
    }

    public function test_php_files_are_rejected(): void
    {
        $this->assertContains('File harus berupa gambar JPG, PNG, atau WebP.', $this->errorsFor(ImageFixtures::phpScript('x.phtml'), 'photo'));
        $this->assertNotSame([], $this->errorsFor(ImageFixtures::phpWithJpegHeader(), 'photo'));
    }

    public function test_file_over_the_size_limit_is_rejected(): void
    {
        $this->assertContains('Ukuran gambar maksimal 2 MB.', $this->errorsFor(ImageFixtures::oversizedJpeg(2100), 'photo'));
        $this->assertContains('Ukuran banner maksimal 4 MB.', $this->errorsFor(ImageFixtures::oversizedJpeg(4200, 1600, 600), 'image'));
        $this->assertSame([], $this->errorsFor(ImageFixtures::oversizedJpeg(3000, 1600, 600), 'image'));
    }

    public function test_image_below_the_minimum_dimensions_is_rejected(): void
    {
        $this->assertContains(
            'Dimensi gambar minimal 300 x 300 piksel dan maksimal 6000 x 6000 piksel.',
            $this->errorsFor(ImageFixtures::jpeg(299, 299), 'photo'),
        );
        $this->assertContains(
            'Dimensi banner minimal 1200 x 400 piksel dan maksimal 6000 x 6000 piksel.',
            $this->errorsFor(ImageFixtures::jpeg(1199, 400), 'image'),
        );
        $this->assertContains(
            'Dimensi logo minimal 120 x 40 piksel dan maksimal 6000 x 6000 piksel.',
            $this->errorsFor(ImageFixtures::jpeg(119, 40), 'logo'),
        );
    }

    public function test_image_above_the_maximum_dimensions_is_rejected(): void
    {
        $this->assertContains(
            'Dimensi gambar minimal 200 x 100 piksel dan maksimal 6000 x 6000 piksel.',
            $this->errorsFor(ImageFixtures::jpeg(6001, 100), 'editor'),
        );
        $this->assertSame([], $this->errorsFor(ImageFixtures::jpeg(6000, 100), 'editor'));
    }

    public function test_failed_php_upload_reports_the_server_limit_message(): void
    {
        $valid = ImageFixtures::jpeg(800, 800);
        $failed = new UploadedFile($valid->getRealPath(), 'foto.jpg', 'image/jpeg', UPLOAD_ERR_INI_SIZE, true);

        $this->assertSame(
            ['Gambar gagal diunggah. Ukuran file melebihi batas server.'],
            $this->errorsFor($failed, 'photo'),
        );
    }
}
```

- [ ] **Step 19: Jalankan tes — harus GAGAL**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=ImageRulesTest
```
Expected: FAIL — `Error: Class "App\Rules\ImageRules" not found` pada semua tes.

- [ ] **Step 20: Implementasi `app/Rules/ImageRules.php`**

```php
<?php

namespace App\Rules;

use App\Services\Media\MediaService;
use InvalidArgumentException;

/**
 * Aturan dan pesan unggahan gambar per koleksi (12 §3.2). Sumber angka: MediaService::COLLECTIONS.
 */
final class ImageRules
{
    public const INVALID_IMAGE = 'File tidak dapat diproses sebagai gambar yang valid.';

    public const NOT_IMAGE = 'File harus berupa gambar.';

    public const WRONG_TYPE = 'File harus berupa gambar JPG, PNG, atau WebP.';

    public const UPLOAD_FAILED = 'Gambar gagal diunggah. Ukuran file melebihi batas server.';

    /**
     * @return list<string>
     */
    public static function for(string $collection): array
    {
        $config = self::config($collection);
        [$minWidth, $minHeight] = $config['min'];
        $max = MediaService::MAX_DIMENSION;

        return [
            'file',
            'image',
            'mimes:jpg,jpeg,png,webp',
            'mimetypes:image/jpeg,image/png,image/webp',
            'max:'.$config['max_kb'],
            "dimensions:min_width={$minWidth},min_height={$minHeight},max_width={$max},max_height={$max}",
        ];
    }

    /**
     * Pesan untuk `$validator`/`Form Request::messages()`; kunci memakai nama field ($field).
     *
     * @return array<string, string>
     */
    public static function messages(string $field, string $collection): array
    {
        $config = self::config($collection);
        [$minWidth, $minHeight] = $config['min'];
        $max = MediaService::MAX_DIMENSION;
        $maxMb = intdiv($config['max_kb'], 1024);
        $sizeSubject = $collection === 'image' ? 'banner' : 'gambar';
        $dimensionSubject = match ($collection) {
            'image' => 'banner',
            'logo' => 'logo',
            default => 'gambar',
        };

        return [
            "{$field}.file" => self::NOT_IMAGE,
            "{$field}.image" => self::NOT_IMAGE,
            "{$field}.mimes" => self::WRONG_TYPE,
            "{$field}.mimetypes" => self::WRONG_TYPE,
            "{$field}.max" => "Ukuran {$sizeSubject} maksimal {$maxMb} MB.",
            "{$field}.dimensions" => "Dimensi {$dimensionSubject} minimal {$minWidth} x {$minHeight} piksel dan maksimal {$max} x {$max} piksel.",
            "{$field}.uploaded" => self::UPLOAD_FAILED,
        ];
    }

    /**
     * @return array{single: bool, disk: string, max_kb: int, min: array{0: int, 1: int}}
     */
    private static function config(string $collection): array
    {
        return MediaService::COLLECTIONS[$collection]
            ?? throw new InvalidArgumentException("Koleksi media tidak dikenal: {$collection}.");
    }
}
```

- [ ] **Step 21: Jalankan tes — harus LULUS**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=ImageRulesTest
```
Expected: PASS (14 kasus provider + 13 tes biasa; tidak menyentuh database). Pesan galat Validator = teks 12 §3.2 persis (kunci `file.mimes`, `file.mimetypes`, `file.max`, `file.dimensions`, `file.uploaded`).

- [ ] **Step 22: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint --test
git add app/Rules/ImageRules.php tests/Feature/Media/ImageRulesTest.php
git commit -m "feat(fase-1): ImageRules dengan aturan dan pesan unggahan per koleksi"
```
Expected: Pint `PASS`.

#### Siklus E — pertahanan berlapis dan penolakan (MED-05, SEK-09, SEK-10)

- [ ] **Step 23: Tulis tes `tests/Feature/Media/MediaServiceDefenseTest.php`**

```php
<?php

namespace Tests\Feature\Media;

use App\Models\AuditLog;
use App\Models\Media;
use App\Models\User;
use App\Rules\ImageRules;
use App\Services\Media\MediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ImageFixtures;
use Tests\TestCase;

class MediaServiceDefenseTest extends TestCase
{
    use RefreshDatabase;

    private MediaService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->service = app(MediaService::class);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function assertRejected(UploadedFile $file, string $collection, string $key, string $message, array $meta = ['alt_text' => 'Teks alternatif']): void
    {
        try {
            $this->service->store($file, $collection, null, $meta);
            $this->fail('ValidationException tidak dilempar.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($key, $e->errors());
            $this->assertContains($message, $e->errors()[$key]);
        }

        $this->assertSame(0, Media::query()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_file_over_the_size_limit_is_rejected_per_collection(): void
    {
        $this->assertRejected(ImageFixtures::oversizedJpeg(2100), 'photo', 'file', 'Ukuran gambar maksimal 2 MB.');
        $this->assertRejected(ImageFixtures::oversizedJpeg(4200, 1600, 600), 'image', 'file', 'Ukuran banner maksimal 4 MB.');
    }

    public function test_banner_between_2_and_4_mb_is_accepted(): void
    {
        $media = $this->service->store(ImageFixtures::oversizedJpeg(3000, 1600, 600), 'image', null, ['alt_text' => 'Banner']);

        $this->assertSame('image', $media->collection);
        $this->assertSame(1600, $media->width);
    }

    /**
     * @return array<string, array{0: string, 1: int, 2: int, 3: string}>
     */
    public static function undersizedUploads(): array
    {
        return [
            'photo 299x299' => ['photo', 299, 299, 'Dimensi gambar minimal 300 x 300 piksel dan maksimal 6000 x 6000 piksel.'],
            'cover 799x450' => ['cover', 799, 450, 'Dimensi gambar minimal 800 x 450 piksel dan maksimal 6000 x 6000 piksel.'],
            'gallery 599x400' => ['gallery', 599, 400, 'Dimensi gambar minimal 600 x 400 piksel dan maksimal 6000 x 6000 piksel.'],
            'photos 600x399' => ['photos', 600, 399, 'Dimensi gambar minimal 600 x 400 piksel dan maksimal 6000 x 6000 piksel.'],
            'image 1199x400' => ['image', 1199, 400, 'Dimensi banner minimal 1200 x 400 piksel dan maksimal 6000 x 6000 piksel.'],
            'logo 119x40' => ['logo', 119, 40, 'Dimensi logo minimal 120 x 40 piksel dan maksimal 6000 x 6000 piksel.'],
            'editor 199x100' => ['editor', 199, 100, 'Dimensi gambar minimal 200 x 100 piksel dan maksimal 6000 x 6000 piksel.'],
        ];
    }

    #[DataProvider('undersizedUploads')]
    public function test_image_below_the_collection_minimum_is_rejected(string $collection, int $width, int $height, string $message): void
    {
        $this->assertRejected(ImageFixtures::jpeg($width, $height), $collection, 'file', $message);
    }

    /**
     * @return array<string, array{0: string, 1: int, 2: int}>
     */
    public static function minimumUploads(): array
    {
        return [
            'photo 300x300' => ['photo', 300, 300],
            'cover 800x450' => ['cover', 800, 450],
            'gallery 600x400' => ['gallery', 600, 400],
            'photos 600x400' => ['photos', 600, 400],
            'image 1200x400' => ['image', 1200, 400],
            'logo 120x40' => ['logo', 120, 40],
            'editor 200x100' => ['editor', 200, 100],
        ];
    }

    #[DataProvider('minimumUploads')]
    public function test_image_exactly_at_the_collection_minimum_is_accepted(string $collection, int $width, int $height): void
    {
        $media = $this->service->store(ImageFixtures::jpeg($width, $height), $collection, null, ['alt_text' => 'Teks alternatif']);

        $this->assertSame($width, $media->width);
        $this->assertSame($height, $media->height);
    }

    public function test_image_above_6000_px_is_rejected_and_6000_px_is_accepted(): void
    {
        $this->assertRejected(
            ImageFixtures::jpeg(6001, 100),
            'editor',
            'file',
            'Dimensi gambar minimal 200 x 100 piksel dan maksimal 6000 x 6000 piksel.',
        );

        $media = $this->service->store(ImageFixtures::jpeg(6000, 100), 'editor', null, ['alt_text' => 'Panorama']);

        $this->assertSame(1600, $media->width);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function svgCollections(): array
    {
        return [
            'foto dokter' => ['photo'],
            'banner' => ['image'],
            'galeri' => ['gallery'],
            'logo' => ['logo'],
            'editor' => ['editor'],
        ];
    }

    #[DataProvider('svgCollections')]
    public function test_svg_is_rejected_for_every_public_collection(string $collection): void
    {
        $this->assertRejected(ImageFixtures::svgWithScript(), $collection, 'file', ImageRules::WRONG_TYPE);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function unsafeFiles(): array
    {
        return [
            'PHP berkepala JPEG (shell.php.jpg)' => ['phpWithJpegHeader', 'photo', ImageRules::INVALID_IMAGE],
            'skrip x.phtml' => ['phpScript', 'photo', ImageRules::WRONG_TYPE],
            'teks bernama .jpg' => ['textNamedJpg', 'photo', ImageRules::WRONG_TYPE],
            'PNG dengan data rusak' => ['corruptPng', 'gallery', ImageRules::INVALID_IMAGE],
        ];
    }

    #[DataProvider('unsafeFiles')]
    public function test_disguised_and_corrupt_files_are_rejected_without_leaving_anything(string $fixture, string $collection, string $message): void
    {
        $this->assertRejected(ImageFixtures::{$fixture}(), $collection, 'file', $message);
    }

    public function test_failed_php_upload_is_rejected_with_the_server_limit_message(): void
    {
        $valid = ImageFixtures::jpeg(800, 800);
        $failed = new UploadedFile($valid->getRealPath(), 'foto.jpg', 'image/jpeg', UPLOAD_ERR_INI_SIZE, true);

        $this->assertRejected($failed, 'photo', 'file', ImageRules::UPLOAD_FAILED);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidAltTexts(): array
    {
        return [
            'kosong' => [['alt_text' => ''], 'Teks alternatif gambar wajib diisi.'],
            'hanya spasi' => [['alt_text' => '   '], 'Teks alternatif gambar wajib diisi.'],
            'tidak dikirim' => [[], 'Teks alternatif gambar wajib diisi.'],
            '256 karakter' => [['alt_text' => str_repeat('a', 256)], 'Teks alternatif gambar maksimal 255 karakter.'],
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    #[DataProvider('invalidAltTexts')]
    public function test_alt_text_is_required_and_limited_to_255_characters(array $meta, string $message): void
    {
        $this->assertRejected(ImageFixtures::jpeg(800, 800), 'photo', 'alt_text', $message, $meta);
    }

    public function test_caption_longer_than_255_characters_is_rejected(): void
    {
        $this->assertRejected(
            ImageFixtures::jpeg(800, 800),
            'photo',
            'caption',
            'Keterangan gambar maksimal 255 karakter.',
            ['alt_text' => 'Foto', 'caption' => str_repeat('k', 256)],
        );
    }

    public function test_alt_text_of_255_characters_is_accepted_and_the_caption_is_trimmed(): void
    {
        $media = $this->service->store(
            ImageFixtures::jpeg(800, 800),
            'photo',
            null,
            ['alt_text' => str_repeat('a', 255), 'caption' => '  Keterangan  '],
        );

        $this->assertSame(255, mb_strlen($media->alt_text));
        $this->assertSame('Keterangan', $media->caption);

        $blank = $this->service->store(ImageFixtures::jpeg(800, 800), 'photo', null, ['alt_text' => 'Foto', 'caption' => '   ']);

        $this->assertNull($blank->caption);
    }

    public function test_file_and_alt_text_errors_are_reported_together(): void
    {
        try {
            $this->service->store(ImageFixtures::svgWithScript(), 'photo', null, ['alt_text' => '']);
            $this->fail('ValidationException tidak dilempar.');
        } catch (ValidationException $e) {
            $this->assertSame(['file', 'alt_text'], array_keys($e->errors()));
        }
    }

    public function test_owner_must_already_be_persisted(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Pemilik media harus sudah tersimpan.');

        try {
            $this->service->store(ImageFixtures::jpeg(800, 800), 'photo', new User, ['alt_text' => 'Foto']);
        } finally {
            $this->assertSame([], Storage::disk('public')->allFiles());
        }
    }
}
```

- [ ] **Step 24: Jalankan tes — harus GAGAL**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=MediaServiceDefenseTest
```
Expected: FAIL — berkas SVG/teks/PHP/PNG rusak melempar `Intervention\Image\Exceptions\DecoderException: Unable to decode input` (bukan `ValidationException`); berkas terlalu besar/kecil, `alt_text` kosong, dan galat unggah PHP **tidak** melempar apa pun sehingga `ValidationException tidak dilempar.`; pemilik belum tersimpan memicu galat basis data, bukan `InvalidArgumentException`. Tes "diterima persis di batas minimum" sudah lulus.

- [ ] **Step 25: Implementasi pertahanan di `app/Services/Media/MediaService.php`**

Lakukan enam `Edit` berikut (jangan mengubah bagian lain berkas).

Edit 1 — impor aturan: ganti baris `use App\Models\User;` dengan:

```php
use App\Models\User;
use App\Rules\ImageRules;
```

Edit 2 — impor validasi: ganti baris `use Illuminate\Support\Str;` dengan:

```php
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
```

Edit 3 — impor galat gambar: ganti baris `use Intervention\Image\Decoders\BinaryImageDecoder;` dengan:

```php
use Intervention\Image\Decoders\BinaryImageDecoder;
use Intervention\Image\Exceptions\RuntimeException as ImageException;
```

Edit 4 — panggil pertahanan sebelum berkas dibaca: ganti baris

```php
        $binary = (string) file_get_contents((string) $file->getRealPath());
```

dengan:

```php
        $this->assertOwnerPersisted($owner);
        $this->assertAcceptable($file, $collection, $config, $altText, $caption);

        $binary = (string) file_get_contents((string) $file->getRealPath());
```

Edit 5 — dekode/encode gagal dipetakan ke `ValidationException`: ganti blok

```php
        $image = ImageManager::gd()->read($binary, BinaryImageDecoder::class);
        $image->scaleDown(width: self::RESIZE_WIDTH);
        $thumbnail = clone $image;
        $thumbnail->scaleDown(width: self::THUMB_WIDTH);
        $encoded = $this->encode($image, $mime);
        $thumbnailEncoded = $this->encode($thumbnail, $mime);
        $width = $image->width();
        $height = $image->height();
```

dengan:

```php
        try {
            $image = ImageManager::gd()->read($binary, BinaryImageDecoder::class);
            $image->scaleDown(width: self::RESIZE_WIDTH);
            $thumbnail = clone $image;
            $thumbnail->scaleDown(width: self::THUMB_WIDTH);
            $encoded = $this->encode($image, $mime);
            $thumbnailEncoded = $this->encode($thumbnail, $mime);
        } catch (ImageException) {
            throw ValidationException::withMessages(['file' => ImageRules::INVALID_IMAGE]);
        }

        $width = $image->width();
        $height = $image->height();
```

Edit 6 — tambah metode pertahanan: ganti awalan baris `    private function encode(` (satu-satunya kemunculan) dengan blok berikut diikuti awalan yang sama:

```php
    private function assertOwnerPersisted(?Model $owner): void
    {
        if ($owner !== null && ! $owner->exists) {
            throw new InvalidArgumentException('Pemilik media harus sudah tersimpan.');
        }
    }

    /**
     * Pertahanan berlapis di luar Form Request (12 §3.2, §5.6): teks alternatif, MIME nyata,
     * ukuran, dan dimensi. Semua galat dikumpulkan lalu dilempar sekali sebagai ValidationException.
     *
     * @param  array{single: bool, disk: string, max_kb: int, min: array{0: int, 1: int}}  $config
     */
    private function assertAcceptable(UploadedFile $file, string $collection, array $config, string $altText, ?string $caption): void
    {
        $errors = [];
        $fileError = $this->fileError($file, $collection, $config);

        if ($fileError !== null) {
            $errors['file'] = [$fileError];
        }

        $errors = array_merge($errors, $this->metaErrors($altText, $caption));

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @return array<string, list<string>>
     */
    private function metaErrors(string $altText, ?string $caption): array
    {
        $errors = [];

        if ($altText === '') {
            $errors['alt_text'] = ['Teks alternatif gambar wajib diisi.'];
        } elseif (mb_strlen($altText) > 255) {
            $errors['alt_text'] = ['Teks alternatif gambar maksimal 255 karakter.'];
        }

        if ($caption !== null && mb_strlen($caption) > 255) {
            $errors['caption'] = ['Keterangan gambar maksimal 255 karakter.'];
        }

        return $errors;
    }

    /**
     * MIME dari isi berkas (finfo), ukuran, lalu dimensi lewat getimagesize() sebelum dekode.
     *
     * @param  array{single: bool, disk: string, max_kb: int, min: array{0: int, 1: int}}  $config
     */
    private function fileError(UploadedFile $file, string $collection, array $config): ?string
    {
        $messages = ImageRules::messages('file', $collection);

        if (! $file->isValid()) {
            return $messages['file.uploaded'];
        }

        $path = $file->getRealPath();

        if ($path === false || ! is_readable($path)) {
            return ImageRules::INVALID_IMAGE;
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);

        if ($mime === false || ! isset(self::EXTENSIONS[$mime])) {
            return $messages['file.mimetypes'];
        }

        if ((int) filesize($path) > $config['max_kb'] * 1024) {
            return $messages['file.max'];
        }

        $info = @getimagesize($path);

        if ($info === false) {
            return ImageRules::INVALID_IMAGE;
        }

        [$width, $height] = $info;

        if ($width < $config['min'][0] || $height < $config['min'][1] || $width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION) {
            return $messages['file.dimensions'];
        }

        return null;
    }

    private function encode(
```

- [ ] **Step 26: Jalankan tes — harus LULUS**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="MediaServiceDefenseTest|MediaServiceTest"
```
Expected: PASS (`MediaServiceDefenseTest` 35 tes + `MediaServiceTest` 26 tes). Tidak ada berkas tersimpan dan tidak ada baris `media` maupun `audit_logs` pada setiap penolakan (MED-05, SEK-09, SEK-10).

- [ ] **Step 27: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint --test
git add app/Services/Media/MediaService.php tests/Feature/Media/MediaServiceDefenseTest.php
git commit -m "feat(fase-1): pertahanan unggahan (MIME nyata, ukuran, dimensi, alt text) dan penolakan SVG/PHP"
```
Expected: Pint `PASS`.

#### Siklus F — `replace()`, `delete()`, `deleteFor()` (MED-02)

- [ ] **Step 28: Tulis tes `tests/Feature/Media/MediaServiceLifecycleTest.php`**

```php
<?php

namespace Tests\Feature\Media;

use App\Models\AuditLog;
use App\Models\Media;
use App\Models\User;
use App\Services\Media\MediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;
use Tests\Support\ImageFixtures;
use Tests\TestCase;

class MediaServiceLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private MediaService $service;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->service = app(MediaService::class);
        $this->owner = User::factory()->staff()->create();
    }

    private function storePhoto(?User $owner = null): Media
    {
        return $this->service->store(ImageFixtures::jpeg(600, 600), 'photo', $owner ?? $this->owner, ['alt_text' => 'Foto']);
    }

    private function replacePhoto(): Media
    {
        return $this->service->replace($this->owner, 'photo', ImageFixtures::jpeg(700, 700), ['alt_text' => 'Foto baru'], $this->owner);
    }

    private function assertFilesExist(Media $media): void
    {
        Storage::disk('public')->assertExists($media->directory.'/'.$media->file_name);
        Storage::disk('public')->assertExists($media->thumb_path);
    }

    private function assertFilesMissing(Media $media): void
    {
        Storage::disk('public')->assertMissing($media->directory.'/'.$media->file_name);
        Storage::disk('public')->assertMissing($media->thumb_path);
    }

    public function test_replace_swaps_the_single_media_and_deletes_the_old_files(): void
    {
        $old = $this->storePhoto();

        $new = $this->replacePhoto();

        $this->assertNotSame($old->id, $new->id);
        $this->assertFilesMissing($old);
        $this->assertFilesExist($new);
        $this->assertNull(Media::query()->find($old->id));
        $this->assertSame('Foto baru', $new->alt_text);
        $this->assertSame(
            1,
            Media::query()->where('collection', 'photo')->where('model_type', $this->owner->getMorphClass())->where('model_id', $this->owner->id)->count(),
        );
    }

    public function test_replace_writes_a_replaced_audit_and_no_second_uploaded_or_deleted_audit(): void
    {
        $old = $this->storePhoto();
        $new = $this->replacePhoto();

        $this->assertSame(1, AuditLog::query()->where('action', 'media.uploaded')->count());
        $this->assertSame(0, AuditLog::query()->where('action', 'media.deleted')->count());

        $log = AuditLog::query()->where('action', 'media.replaced')->sole();
        $this->assertSame($new->id, $log->auditable_id);
        $this->assertSame($this->owner->id, $log->user_id);
        $this->assertSame(['media_id' => $old->id, 'file_name' => $old->file_name], $log->old_values);
        $this->assertSame(['media_id' => $new->id, 'file_name' => $new->file_name], $log->new_values);
    }

    public function test_replace_without_a_previous_media_stores_the_first_one(): void
    {
        $new = $this->replacePhoto();

        $this->assertFilesExist($new);
        $this->assertSame(1, $new->sort_order);

        $log = AuditLog::query()->where('action', 'media.replaced')->sole();
        $this->assertNull($log->old_values);
        $this->assertSame(0, AuditLog::query()->where('action', 'media.uploaded')->count());
    }

    public function test_replace_rejects_collections_that_allow_many_images(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Koleksi gallery bukan koleksi tunggal dan tidak dapat diganti.');

        $this->service->replace($this->owner, 'gallery', ImageFixtures::jpeg(900, 600), ['alt_text' => 'Foto']);
    }

    public function test_replace_requires_a_persisted_owner(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Pemilik media harus sudah tersimpan.');

        $this->service->replace(new User, 'photo', ImageFixtures::jpeg(700, 700), ['alt_text' => 'Foto']);
    }

    public function test_replace_keeps_the_old_media_when_the_new_upload_is_invalid(): void
    {
        $old = $this->storePhoto();

        $thrown = null;

        try {
            $this->service->replace($this->owner, 'photo', ImageFixtures::svgWithScript(), ['alt_text' => 'Foto baru']);
        } catch (ValidationException $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown);
        $this->assertFilesExist($old);
        $this->assertNotNull(Media::query()->find($old->id));
        $this->assertSame(1, Media::query()->count());
    }

    public function test_replace_leaves_other_owners_media_untouched(): void
    {
        $other = User::factory()->staff()->create();
        $othersPhoto = $this->storePhoto($other);
        $this->storePhoto();

        $this->replacePhoto();

        $this->assertFilesExist($othersPhoto);
        $this->assertNotNull(Media::query()->find($othersPhoto->id));
    }

    public function test_replace_deletes_old_files_only_after_the_surrounding_transaction_commits(): void
    {
        $old = $this->storePhoto();

        DB::transaction(function () use ($old): void {
            $new = $this->replacePhoto();

            $this->assertFilesExist($old);
            $this->assertNotNull(Media::query()->find($old->id));
            $this->assertFilesExist($new);
        });

        $this->assertFilesMissing($old);
        $this->assertNull(Media::query()->find($old->id));
    }

    public function test_replace_inside_a_rolled_back_transaction_keeps_the_old_media(): void
    {
        $old = $this->storePhoto();

        $thrown = null;

        try {
            DB::transaction(function (): void {
                $this->replacePhoto();

                throw new RuntimeException('simulasi galat');
            });
        } catch (RuntimeException $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown);
        $this->assertSame('simulasi galat', $thrown->getMessage());
        $this->assertFilesExist($old);
        $this->assertNotNull(Media::query()->find($old->id));
        $this->assertSame(1, Media::query()->count());
        $this->assertSame(0, AuditLog::query()->where('action', 'media.replaced')->count());
    }

    public function test_delete_removes_the_row_both_files_and_writes_an_audit(): void
    {
        $media = $this->storePhoto();

        $this->service->delete($media);

        $this->assertFilesMissing($media);
        $this->assertNull(Media::query()->find($media->id));

        $log = AuditLog::query()->where('action', 'media.deleted')->sole();
        $this->assertSame($media->id, $log->auditable_id);
        $this->assertSame($media->id, $log->old_values['media_id']);
        $this->assertSame('photo', $log->old_values['collection']);
        $this->assertNull($log->new_values);
    }

    public function test_delete_waits_for_the_commit_and_keeps_everything_after_a_rollback(): void
    {
        $media = $this->storePhoto();

        $thrown = null;

        try {
            DB::transaction(function () use ($media): void {
                $this->service->delete($media);

                $this->assertFilesExist($media);

                throw new RuntimeException('simulasi galat');
            });
        } catch (RuntimeException $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown);
        $this->assertFilesExist($media);
        $this->assertNotNull(Media::query()->find($media->id));
        $this->assertSame(0, AuditLog::query()->where('action', 'media.deleted')->count());

        $this->service->delete(Media::query()->findOrFail($media->id));

        $this->assertFilesMissing($media);
    }

    public function test_delete_for_removes_only_the_media_of_that_owner(): void
    {
        $other = User::factory()->staff()->create();
        $othersPhoto = $this->storePhoto($other);
        $photo = $this->storePhoto();
        $galleryOne = $this->service->store(ImageFixtures::jpeg(900, 600), 'gallery', $this->owner, ['alt_text' => 'Satu']);
        $galleryTwo = $this->service->store(ImageFixtures::jpeg(900, 600), 'gallery', $this->owner, ['alt_text' => 'Dua']);

        $this->service->deleteFor($this->owner);

        foreach ([$photo, $galleryOne, $galleryTwo] as $media) {
            $this->assertFilesMissing($media);
            $this->assertNull(Media::query()->find($media->id));
        }

        $this->assertFilesExist($othersPhoto);
        $this->assertNotNull(Media::query()->find($othersPhoto->id));
        $this->assertSame(3, AuditLog::query()->where('action', 'media.deleted')->count());
    }
}
```

- [ ] **Step 29: Jalankan tes — harus GAGAL**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=MediaServiceLifecycleTest
```
Expected: FAIL — `Error: Call to undefined method App\Services\Media\MediaService::replace()` (dan `::delete()`, `::deleteFor()`) pada ke-12 tes.

- [ ] **Step 30: Implementasi `replace()`, `delete()`, `deleteFor()` di `app/Services/Media/MediaService.php`**

Edit 1 — impor `Builder`: ganti baris `use Illuminate\Database\Eloquent\Model;` dengan:

```php
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
```

Edit 2 — metode publik: ganti awalan baris `    private function createMedia(` (satu-satunya kemunculan) dengan blok berikut diikuti awalan yang sama:

```php
    /**
     * Ganti media koleksi tunggal: berkas + baris baru disimpan dulu, media lama dihapus
     * SETELAH transaksi commit (DB::afterCommit). Bila transaksi pemanggil di-rollback, media
     * lama tetap utuh (berkas baru yang sempat tertulis menjadi yatim dan dibersihkan media:prune).
     *
     * @param  array<string, mixed>  $meta
     */
    public function replace(Model $owner, string $collection, UploadedFile $file, array $meta = [], ?User $uploader = null): Media
    {
        $config = $this->configFor($collection);

        if (! $config['single']) {
            throw new InvalidArgumentException("Koleksi {$collection} bukan koleksi tunggal dan tidak dapat diganti.");
        }

        $this->assertOwnerPersisted($owner);

        $olds = $this->ownedBy($owner, $collection)->get();

        $media = $this->createMedia($file, $collection, $config, $owner, $meta, $uploader, function (Media $new) use ($olds, $uploader): void {
            $previous = $olds->first();

            $this->audit->log(
                'media.replaced',
                $new,
                $previous === null ? null : ['media_id' => $previous->id, 'file_name' => $previous->file_name],
                ['media_id' => $new->id, 'file_name' => $new->file_name],
                "Gambar koleksi {$new->collection} diganti.",
                $uploader !== null ? Actor::staff($uploader) : null,
            );
        });

        DB::afterCommit(function () use ($olds): void {
            foreach ($olds as $old) {
                $this->purge($old);
            }
        });

        return $media;
    }

    /**
     * Hapus baris media; berkas asli dan thumbnail dihapus setelah transaksi commit.
     */
    public function delete(Media $media): void
    {
        $disk = $media->disk;
        $paths = $this->pathsOf($media);

        DB::transaction(function () use ($media, $disk, $paths): void {
            $this->audit->log(
                'media.deleted',
                $media,
                ['media_id' => $media->id, 'collection' => $media->collection, 'file_name' => $media->file_name],
                null,
                "Gambar dihapus dari koleksi {$media->collection}.",
            );

            $media->delete();

            DB::afterCommit(fn () => Storage::disk($disk)->delete($paths));
        });
    }

    /**
     * Hapus seluruh media milik satu pemilik (dipanggil saat model pemilik dihapus).
     */
    public function deleteFor(Model $owner): void
    {
        Media::query()
            ->where('model_type', $owner->getMorphClass())
            ->where('model_id', $owner->getKey())
            ->orderBy('id')
            ->get()
            ->each(fn (Media $media) => $this->delete($media));
    }

    private function createMedia(
```

Edit 3 — pembantu privat: ganti awalan baris `    private function encode(` (satu-satunya kemunculan) dengan blok berikut diikuti awalan yang sama:

```php
    /**
     * @return array<int, string>
     */
    private function pathsOf(Media $media): array
    {
        return array_values(array_filter([
            $media->directory.'/'.$media->file_name,
            $media->thumb_path,
        ]));
    }

    /**
     * Hapus baris lalu berkas tanpa audit (jalur internal: audit sudah dicatat pemanggil).
     */
    private function purge(Media $media): void
    {
        $paths = $this->pathsOf($media);
        $disk = $media->disk;

        $media->delete();
        Storage::disk($disk)->delete($paths);
    }

    /**
     * @return Builder<Media>
     */
    private function ownedBy(Model $owner, string $collection): Builder
    {
        return Media::query()
            ->where('model_type', $owner->getMorphClass())
            ->where('model_id', $owner->getKey())
            ->where('collection', $collection)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    private function encode(
```

- [ ] **Step 31: Jalankan tes — harus LULUS**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=MediaServiceLifecycleTest
```
Expected: PASS (12 tes). MED-02: setelah commit berkas dan baris lama terhapus (`Storage::assertMissing`); di dalam `DB::transaction` yang di-rollback berkas lama tetap ada. Bila dua tes transaksi gagal karena callback `afterCommit` berjalan pada saat yang tidak diharapkan, baca `vendor/laravel/framework/src/Illuminate/Foundation/Testing/DatabaseTransactionsManager.php` (transaksi pembungkus tes dilewati: callback langsung jalan bila tidak ada transaksi aplikasi, ditunda sampai commit bila ada) sebelum mengubah tes.

- [ ] **Step 32: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint --test
git add app/Services/Media/MediaService.php tests/Feature/Media/MediaServiceLifecycleTest.php
git commit -m "feat(fase-1): MediaService replace/delete/deleteFor dengan penghapusan berkas setelah commit"
```
Expected: Pint `PASS`.

#### Siklus G — `updateMeta()` dan `reorder()`

- [ ] **Step 33: Tulis tes `tests/Feature/Media/MediaServiceMetaTest.php`**

```php
<?php

namespace Tests\Feature\Media;

use App\Models\Media;
use App\Models\User;
use App\Services\Media\MediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ImageFixtures;
use Tests\TestCase;

class MediaServiceMetaTest extends TestCase
{
    use RefreshDatabase;

    private MediaService $service;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->service = app(MediaService::class);
        $this->owner = User::factory()->staff()->create();
    }

    private function image(?User $owner = null, string $collection = 'gallery'): Media
    {
        return $this->service->store(ImageFixtures::jpeg(900, 600), $collection, $owner ?? $this->owner, ['alt_text' => 'Foto']);
    }

    /**
     * @return list<int>
     */
    private function orderOf(string $collection = 'gallery'): array
    {
        return Media::query()
            ->where('model_id', $this->owner->id)
            ->where('collection', $collection)
            ->orderBy('sort_order')
            ->pluck('id')
            ->all();
    }

    public function test_update_meta_trims_and_saves_alt_text_and_caption_without_touching_files(): void
    {
        $media = $this->image();
        $files = Storage::disk('public')->allFiles();

        $updated = $this->service->updateMeta($media, '  Alt baru  ', '  Keterangan baru  ');

        $this->assertSame('Alt baru', $updated->alt_text);
        $this->assertSame('Keterangan baru', $updated->caption);
        $this->assertSame('Alt baru', Media::query()->findOrFail($media->id)->alt_text);
        $this->assertSame($files, Storage::disk('public')->allFiles());
    }

    public function test_update_meta_stores_a_blank_caption_as_null(): void
    {
        $media = $this->service->store(ImageFixtures::jpeg(900, 600), 'gallery', $this->owner, ['alt_text' => 'Foto', 'caption' => 'Lama']);

        $updated = $this->service->updateMeta($media, 'Foto', '   ');

        $this->assertNull($updated->caption);
        $this->assertNull(Media::query()->findOrFail($media->id)->caption);
    }

    /**
     * @return array<string, array{0: ?string, 1: ?string, 2: string, 3: string}>
     */
    public static function invalidMeta(): array
    {
        return [
            'alt kosong' => ['', null, 'alt_text', 'Teks alternatif gambar wajib diisi.'],
            'alt hanya spasi' => ['   ', null, 'alt_text', 'Teks alternatif gambar wajib diisi.'],
            'alt null' => [null, null, 'alt_text', 'Teks alternatif gambar wajib diisi.'],
            'alt 256 karakter' => [str_repeat('a', 256), null, 'alt_text', 'Teks alternatif gambar maksimal 255 karakter.'],
            'caption 256 karakter' => ['Foto', str_repeat('k', 256), 'caption', 'Keterangan gambar maksimal 255 karakter.'],
        ];
    }

    #[DataProvider('invalidMeta')]
    public function test_update_meta_rejects_invalid_values_and_keeps_the_stored_ones(?string $alt, ?string $caption, string $key, string $message): void
    {
        $media = $this->image();

        $thrown = null;

        try {
            $this->service->updateMeta($media, $alt, $caption);
        } catch (ValidationException $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown);
        $this->assertContains($message, $thrown->errors()[$key]);
        $this->assertSame('Foto', Media::query()->findOrFail($media->id)->alt_text);
    }

    public function test_reorder_sets_one_based_positions_in_the_given_order(): void
    {
        $a = $this->image();
        $b = $this->image();
        $c = $this->image();

        $this->service->reorder($this->owner, 'gallery', [$c->id, $a->id, $b->id]);

        $this->assertSame([$c->id, $a->id, $b->id], $this->orderOf());
        $this->assertSame(
            [1, 2, 3],
            Media::query()->whereIn('id', [$c->id, $a->id, $b->id])->orderBy('sort_order')->pluck('sort_order')->all(),
        );
    }

    public function test_reorder_accepts_numeric_strings_and_a_subset_of_the_collection(): void
    {
        $a = $this->image();
        $b = $this->image();
        $this->image();

        $this->service->reorder($this->owner, 'gallery', [(string) $b->id, (string) $a->id]);

        $this->assertSame(1, Media::query()->findOrFail($b->id)->sort_order);
        $this->assertSame(2, Media::query()->findOrFail($a->id)->sort_order);
    }

    public function test_reorder_rejects_ids_that_do_not_belong_to_the_owner_and_collection(): void
    {
        $a = $this->image();
        $b = $this->image();
        $foreign = $this->image(User::factory()->staff()->create());
        $otherCollection = $this->image(null, 'photos');
        $before = $this->orderOf();

        $invalid = [
            'milik pemilik lain' => [$a->id, $foreign->id],
            'koleksi lain' => [$a->id, $otherCollection->id],
            'id tidak ada' => [$a->id, 999999],
            'id ganda' => [$a->id, $a->id],
            'bukan angka' => [$a->id, 'abc'],
            'kosong' => [],
        ];

        foreach ($invalid as $label => $ids) {
            $thrown = null;

            try {
                $this->service->reorder($this->owner, 'gallery', $ids);
            } catch (ValidationException $e) {
                $thrown = $e;
            }

            $this->assertNotNull($thrown, "Tidak ditolak: {$label}");
            $this->assertSame(['ids' => ['Urutan gambar tidak valid.']], $thrown->errors(), $label);
        }

        $this->assertSame($before, $this->orderOf());
        $this->assertSame([1, 2], Media::query()->whereIn('id', [$a->id, $b->id])->orderBy('sort_order')->pluck('sort_order')->all());
    }

    public function test_reorder_rejects_an_unknown_collection(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->reorder($this->owner, 'avatar', [1]);
    }
}
```

- [ ] **Step 34: Jalankan tes — harus GAGAL**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=MediaServiceMetaTest
```
Expected: FAIL — `Error: Call to undefined method App\Services\Media\MediaService::updateMeta()` / `::reorder()`.

- [ ] **Step 35: Implementasi `updateMeta()` dan `reorder()` di `app/Services/Media/MediaService.php`**

Ganti awalan baris `    private function createMedia(` (satu-satunya kemunculan) dengan blok berikut diikuti awalan yang sama:

```php
    /**
     * Ubah teks alternatif (wajib, maks 255) dan keterangan (opsional, maks 255). Berkas tidak disentuh.
     */
    public function updateMeta(Media $media, ?string $altText, ?string $caption): Media
    {
        $altText = trim((string) $altText);
        $caption = $this->nullableTrim($caption);
        $errors = $this->metaErrors($altText, $caption);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $media->fill(['alt_text' => $altText, 'caption' => $caption])->save();

        return $media;
    }

    /**
     * Atur urutan: sort_order = posisi 1-based sesuai $orderedIds. Semua id harus milik pemilik
     * dan koleksi yang sama dan tidak boleh ganda.
     *
     * @param  array<int, int|string>  $orderedIds
     */
    public function reorder(Model $owner, string $collection, array $orderedIds): void
    {
        $this->configFor($collection);
        $this->assertOwnerPersisted($owner);

        $ids = array_map('intval', array_values($orderedIds));
        $owned = $ids === [] ? [] : $this->ownedBy($owner, $collection)->whereIn('id', $ids)->pluck('id')->all();

        if ($ids === [] || count($ids) !== count(array_unique($ids)) || count($owned) !== count($ids)) {
            throw ValidationException::withMessages(['ids' => 'Urutan gambar tidak valid.']);
        }

        DB::transaction(function () use ($ids): void {
            foreach ($ids as $position => $id) {
                Media::query()->whereKey($id)->update(['sort_order' => $position + 1]);
            }
        });
    }

    private function createMedia(
```

- [ ] **Step 36: Jalankan tes — harus LULUS**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=MediaServiceMetaTest
```
Expected: PASS (5 kasus `invalidMeta` + 6 tes biasa).

- [ ] **Step 37: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint --test
git add app/Services/Media/MediaService.php tests/Feature/Media/MediaServiceMetaTest.php
git commit -m "feat(fase-1): MediaService updateMeta dan reorder"
```
Expected: Pint `PASS`.

#### Siklus H — placeholder SVG (13 §9.1)

- [ ] **Step 38: Tulis tes `tests/Unit/PlaceholderSvgTest.php`**

```php
<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PlaceholderSvgTest extends TestCase
{
    private const NAMES = ['article', 'banner', 'clinic', 'doctor', 'facility', 'gallery', 'logo', 'room'];

    private const COLOR_TOKENS = ['#F3F7FB', '#0A4D8C', '#0E7C74', '#13A89E', '#1E7D45', '#FFFFFF'];

    /**
     * @return array<string, array{0: string, 1: float, 2: string}>
     */
    public static function placeholders(): array
    {
        return [
            'doctor 1:1' => ['doctor', 1.0, 'Foto contoh'],
            'clinic 3:2' => ['clinic', 3 / 2, 'Foto contoh'],
            'facility 3:2' => ['facility', 3 / 2, 'Foto contoh'],
            'room 3:2' => ['room', 3 / 2, 'Foto contoh'],
            'gallery 3:2' => ['gallery', 3 / 2, 'Foto contoh'],
            'article 16:9' => ['article', 16 / 9, 'Foto contoh'],
            'banner 8:3' => ['banner', 8 / 3, 'Foto contoh'],
            'logo 4:1' => ['logo', 4.0, 'UNPAM HOSPITAL'],
        ];
    }

    private function read(string $name): string
    {
        $path = dirname(__DIR__, 2).'/public/images/placeholders/'.$name.'.svg';

        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    public function test_exactly_the_eight_placeholders_exist(): void
    {
        $found = array_map(
            static fn (string $file): string => basename($file, '.svg'),
            glob(dirname(__DIR__, 2).'/public/images/placeholders/*.svg') ?: [],
        );
        sort($found);

        $this->assertSame(self::NAMES, $found);
    }

    #[DataProvider('placeholders')]
    public function test_svg_is_well_formed_with_title_role_and_the_expected_ratio(string $name, float $ratio, string $text): void
    {
        $content = $this->read($name);

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $this->assertNotFalse($xml, "{$name}.svg bukan XML yang valid");

        $xml->registerXPathNamespace('s', 'http://www.w3.org/2000/svg');
        $titles = $xml->xpath('//s:title');

        $this->assertNotEmpty($titles);
        $this->assertNotSame('', trim((string) $titles[0]));
        $this->assertStringContainsString('role="img"', $content);
        $this->assertStringContainsString('aria-labelledby="t"', $content);
        $this->assertStringContainsString('<title id="t">', $content);

        $this->assertSame(1, preg_match('/viewBox="0 0 (\d+) (\d+)"/', $content, $box));
        $this->assertEqualsWithDelta($ratio, (int) $box[1] / (int) $box[2], 0.01);
    }

    #[DataProvider('placeholders')]
    public function test_svg_has_the_label_text_and_the_system_font(string $name, float $ratio, string $text): void
    {
        $content = $this->read($name);

        $this->assertStringContainsString('>'.$text.'<', $content);
        $this->assertStringContainsString('font-family="system-ui, sans-serif"', $content);

        if ($name === 'logo') {
            $this->assertStringContainsString('>UH<', $content);
        }
    }

    #[DataProvider('placeholders')]
    public function test_svg_has_no_active_content_or_external_references(string $name): void
    {
        $content = $this->read($name);
        $withoutNamespace = str_replace('http://www.w3.org/2000/svg', '', $content);

        $this->assertStringNotContainsString('http', $withoutNamespace);
        $this->assertStringNotContainsString('href', $content);
        $this->assertStringNotContainsString('@import', $content);
        $this->assertStringNotContainsString('url(', $content);
        $this->assertStringNotContainsString('data:', $content);
        $this->assertDoesNotMatchRegularExpression('/<\s*(script|image|style|foreignObject|use|a|iframe|object|embed)\b/i', $content);
        $this->assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=/i', $content);
    }

    #[DataProvider('placeholders')]
    public function test_svg_uses_only_the_brand_colour_tokens(string $name): void
    {
        $content = $this->read($name);

        preg_match_all('/(?:fill|stroke)="([^"]+)"/', $content, $matches);

        $this->assertNotEmpty($matches[1]);

        foreach ($matches[1] as $color) {
            $this->assertContains(strtoupper($color), self::COLOR_TOKENS, "{$name}.svg memakai warna di luar token: {$color}");
        }
    }
}
```

- [ ] **Step 39: Jalankan tes — harus GAGAL**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=PlaceholderSvgTest
```
Expected: FAIL — `Failed asserting that file ".../public/images/placeholders/doctor.svg" exists.` (dan tujuh lainnya); `test_exactly_the_eight_placeholders_exist` gagal karena daftar kosong.

- [ ] **Step 40: Buat delapan berkas SVG di `public/images/placeholders/`**

`public/images/placeholders/doctor.svg`:

```xml
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 600" role="img" aria-labelledby="t">
  <title id="t">Foto contoh dokter</title>
  <rect width="600" height="600" fill="#F3F7FB"/>
  <circle cx="300" cy="230" r="90" fill="#0A4D8C"/>
  <path d="M120 520c0-110 80-170 180-170s180 60 180 170z" fill="#0E7C74"/>
  <text x="300" y="575" text-anchor="middle" font-family="system-ui, sans-serif" font-size="28" fill="#0A4D8C">Foto contoh</text>
</svg>
```

`public/images/placeholders/clinic.svg`:

```xml
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 400" role="img" aria-labelledby="t">
  <title id="t">Foto contoh poliklinik</title>
  <rect width="600" height="400" fill="#F3F7FB"/>
  <rect x="180" y="90" width="240" height="210" rx="8" fill="#0A4D8C"/>
  <rect x="285" y="120" width="30" height="80" fill="#F3F7FB"/>
  <rect x="260" y="145" width="80" height="30" fill="#F3F7FB"/>
  <rect x="270" y="235" width="60" height="65" fill="#13A89E"/>
  <rect x="120" y="300" width="360" height="12" rx="6" fill="#0E7C74"/>
  <text x="300" y="360" text-anchor="middle" font-family="system-ui, sans-serif" font-size="24" fill="#0A4D8C">Foto contoh</text>
</svg>
```

`public/images/placeholders/facility.svg`:

```xml
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 400" role="img" aria-labelledby="t">
  <title id="t">Foto contoh fasilitas</title>
  <rect width="600" height="400" fill="#F3F7FB"/>
  <rect x="150" y="190" width="300" height="40" rx="8" fill="#0E7C74"/>
  <rect x="150" y="150" width="90" height="40" rx="12" fill="#0A4D8C"/>
  <rect x="165" y="230" width="14" height="60" fill="#0A4D8C"/>
  <rect x="421" y="230" width="14" height="60" fill="#0A4D8C"/>
  <rect x="440" y="110" width="80" height="60" rx="6" fill="#13A89E"/>
  <text x="300" y="360" text-anchor="middle" font-family="system-ui, sans-serif" font-size="24" fill="#0A4D8C">Foto contoh</text>
</svg>
```

`public/images/placeholders/room.svg`:

```xml
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 400" role="img" aria-labelledby="t">
  <title id="t">Foto contoh kamar rawat inap</title>
  <rect width="600" height="400" fill="#F3F7FB"/>
  <rect x="200" y="70" width="200" height="200" rx="6" fill="#0A4D8C"/>
  <rect x="220" y="90" width="160" height="160" fill="#F3F7FB"/>
  <rect x="297" y="90" width="6" height="160" fill="#0A4D8C"/>
  <rect x="220" y="167" width="160" height="6" fill="#0A4D8C"/>
  <rect x="160" y="290" width="280" height="14" rx="7" fill="#0E7C74"/>
  <text x="300" y="360" text-anchor="middle" font-family="system-ui, sans-serif" font-size="24" fill="#0A4D8C">Foto contoh</text>
</svg>
```

`public/images/placeholders/gallery.svg`:

```xml
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 400" role="img" aria-labelledby="t">
  <title id="t">Foto contoh galeri</title>
  <rect width="600" height="400" fill="#F3F7FB"/>
  <rect x="170" y="70" width="260" height="200" rx="8" fill="#0A4D8C"/>
  <rect x="185" y="85" width="230" height="170" fill="#F3F7FB"/>
  <path d="M185 255l65-80 50 55 35-35 80 60z" fill="#0E7C74"/>
  <circle cx="360" cy="125" r="20" fill="#13A89E"/>
  <text x="300" y="360" text-anchor="middle" font-family="system-ui, sans-serif" font-size="24" fill="#0A4D8C">Foto contoh</text>
</svg>
```

`public/images/placeholders/article.svg`:

```xml
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 360" role="img" aria-labelledby="t">
  <title id="t">Foto contoh artikel</title>
  <rect width="640" height="360" fill="#F3F7FB"/>
  <rect x="210" y="40" width="220" height="230" rx="8" fill="#FFFFFF" stroke="#0A4D8C" stroke-width="6"/>
  <rect x="235" y="70" width="170" height="16" fill="#0A4D8C"/>
  <rect x="235" y="105" width="170" height="10" fill="#0E7C74"/>
  <rect x="235" y="130" width="170" height="10" fill="#0E7C74"/>
  <rect x="235" y="155" width="120" height="10" fill="#0E7C74"/>
  <rect x="235" y="190" width="170" height="50" fill="#13A89E"/>
  <text x="320" y="330" text-anchor="middle" font-family="system-ui, sans-serif" font-size="24" fill="#0A4D8C">Foto contoh</text>
</svg>
```

`public/images/placeholders/banner.svg`:

```xml
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 300" role="img" aria-labelledby="t">
  <title id="t">Banner contoh</title>
  <rect width="800" height="300" fill="#F3F7FB"/>
  <circle cx="650" cy="140" r="100" fill="#0E7C74"/>
  <rect x="637" y="95" width="26" height="90" fill="#F3F7FB"/>
  <rect x="605" y="127" width="90" height="26" fill="#F3F7FB"/>
  <rect x="80" y="90" width="360" height="22" fill="#0A4D8C"/>
  <rect x="80" y="130" width="260" height="14" fill="#13A89E"/>
  <text x="400" y="275" text-anchor="middle" font-family="system-ui, sans-serif" font-size="26" fill="#0A4D8C">Foto contoh</text>
</svg>
```

`public/images/placeholders/logo.svg`:

```xml
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 100" role="img" aria-labelledby="t">
  <title id="t">Logo UNPAM HOSPITAL</title>
  <rect width="400" height="100" fill="#FFFFFF"/>
  <circle cx="50" cy="50" r="38" fill="#0A4D8C"/>
  <text x="50" y="62" text-anchor="middle" font-family="system-ui, sans-serif" font-size="32" font-weight="700" fill="#FFFFFF">UH</text>
  <text x="104" y="60" font-family="system-ui, sans-serif" font-size="30" font-weight="700" fill="#0A4D8C">UNPAM HOSPITAL</text>
</svg>
```

- [ ] **Step 41: Jalankan tes — harus LULUS**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=PlaceholderSvgTest
```
Expected: PASS (1 tes + 4 × 8 kasus provider = 33 tes).

- [ ] **Step 42: Commit placeholder**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint --test
git add public/images/placeholders tests/Unit/PlaceholderSvgTest.php
git commit -m "feat(fase-1): delapan placeholder SVG lokal dengan tes keamanan dan token warna"
```
Expected: Pint `PASS`; `git status --short` bersih.

#### Siklus I — `.htaccess` penolak skrip di disk publik (12 §5.1 butir 9)

Pertahanan berlapis: walau nama berkas selalu `uuid.{jpg,png,webp}`, Apache tidak boleh pernah mengeksekusi skrip atau menampilkan daftar isi folder `public/storage` (junction ke `storage/app/public`).

- [ ] **Step 42a: Tulis tes `tests/Unit/PublicStorageHardeningTest.php` (gagal dulu)**

```php
<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class PublicStorageHardeningTest extends TestCase
{
    private function path(string $file): string
    {
        return dirname(__DIR__, 2).'/'.$file;
    }

    public function test_public_disk_denies_script_execution_and_directory_listing(): void
    {
        $file = $this->path('storage/app/public/.htaccess');

        $this->assertFileExists($file);

        $content = (string) file_get_contents($file);

        $this->assertStringContainsString('Options -Indexes', $content);
        $this->assertStringContainsString('Require all denied', $content);

        foreach (['php', 'phtml', 'phar'] as $extension) {
            $this->assertStringContainsString($extension, $content, "Ekstensi {$extension} harus ditolak");
        }
    }

    public function test_htaccess_is_tracked_by_git_while_uploads_stay_ignored(): void
    {
        $lines = array_map('trim', file($this->path('storage/app/public/.gitignore')));

        $this->assertContains('*', $lines);
        $this->assertContains('!.gitignore', $lines);
        $this->assertContains('!.htaccess', $lines);
    }
}
```

- [ ] **Step 42b: Jalankan tes — harus GAGAL**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=PublicStorageHardeningTest
```
Expected: FAIL — `Failed asserting that file "…/storage/app/public/.htaccess" exists.` dan `.gitignore` belum memuat `!.htaccess`.

- [ ] **Step 42c: Tulis `storage/app/public/.htaccess` dan perbarui `storage/app/public/.gitignore`**

`storage/app/public/.htaccess` (tool Write):

```apache
# UNPAM HOSPITAL — disk media publik (dilayani lewat public/storage).
# Pertahanan berlapis 12 §5.1 butir 9: tidak ada daftar isi folder dan tidak ada skrip yang dieksekusi.
Options -Indexes

<FilesMatch "\.(?i:php[0-9]?|phtml|phar|pht|phps|cgi|pl|py|sh|asp|aspx|jsp)$">
    Require all denied
</FilesMatch>

<IfModule php_module>
    php_flag engine off
</IfModule>
```

`storage/app/public/.gitignore` (isi lengkap):

```gitignore
*
!.gitignore
!.htaccess
```

- [ ] **Step 42d: Jalankan tes — harus LULUS, lalu periksa Apache**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=PublicStorageHardeningTest
[IO.File]::WriteAllText("$APP\storage\app\public\uji-skrip.php", '<?php echo "TIDAK BOLEH JALAN";', (New-Object System.Text.UTF8Encoding $false))
curl.exe -s -o NUL -w "php=%{http_code}`n" http://unpam-hospital.test/storage/uji-skrip.php
curl.exe -s -o NUL -w "folder=%{http_code}`n" http://unpam-hospital.test/storage/
Remove-Item "$APP\storage\app\public\uji-skrip.php"
git status --short storage/app/public
```
Expected: tes PASS; `php=403`; `folder=403`; berkas uji terhapus; `git status` hanya menampilkan `.gitignore` (diubah) dan `.htaccess` (baru). Bila `php=200` atau `php=500`, hentikan dan laporkan `NEEDS_CONTEXT` (periksa `AllowOverride All` di vhost — blueprint 16 §7.2). Bila `000`, Apache belum Start (Global Constraints) — hentikan dan laporkan `NEEDS_CONTEXT`.

- [ ] **Step 42e: Commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git add storage/app/public/.htaccess storage/app/public/.gitignore tests/Unit/PublicStorageHardeningTest.php
git commit -m "feat(fase-1): tolak eksekusi skrip dan daftar folder di disk media publik"
```

- [ ] **Step 43: Verifikasi gerbang Task 5**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test
& $PHP vendor\bin\pint --test
git status --short
git log --oneline -9
```
Expected: seluruh tes hijau (Task 1–4 + semua tes media); Pint `PASS`; `git status` kosong; sembilan commit Task 5 teratas (fixture, model, store, ImageRules, pertahanan, lifecycle, meta, placeholder, htaccess).

---

### Task 6: Sanitasi rich text — `config/purifier.php` + `HtmlSanitizer`

**Files:**
- Modify: `storage/app/.gitignore` (tambah `!purifier/`)
- Create: `config/purifier.php` (publish + profil `rich_text` 10 §7.2), `storage/app/purifier/.gitignore`
- Create: `app/Services/Content/HtmlSanitizer.php`
- Test: `tests/Feature/Content/PurifierConfigTest.php`, `tests/Feature/Content/HtmlSanitizerTest.php`, `tests/Feature/Content/HtmlSanitizerDomTest.php`, `tests/Feature/Content/HtmlSanitizerPlainTextTest.php`

(Kontrak hanya menyebut `HtmlSanitizerTest`; tes dipecah per siklus TDD. `storage/app/.gitignore` bawaan skeleton berisi `*` + `!private/` + `!public/` + `!.gitignore`, sehingga `storage/app/purifier/.gitignore` akan **ter-ignore** tanpa baris `!purifier/` — temuan penulis rencana.)

**Interfaces:**
- Consumes: `mews/purifier` 3.4 (`Mews\Purifier\Facades\Purifier::clean($html, 'rich_text')`), `ezyang/htmlpurifier` 4.19.1.
- Produces: `App\Services\Content\HtmlSanitizer` (`final`): `clean(?string $html): string` ('' bila kosong); `toPlainText(?string $html, int $limit = 160): string`. Pasca-proses DOM 10 §7.2 butir 1–3 (img hanya `/storage/…`, URL absolut ke host `APP_URL` dinormalkan ke path; tautan eksternal `target="_blank" rel="noopener noreferrer"`; skema selain http/https/mailto/tel dibuang).
- Verifikasi API terhadap `vendor/` (dilakukan penulis rencana; seluruh perilaku di bawah dicoba pada HTMLPurifier 4.19.1 dan PHP 8.2.12): **semua 15 kunci profil `rich_text` valid** (`HTML.Doctype`, `HTML.Allowed`, `Attr.AllowedFrameTargets`, `Attr.AllowedRel`, `HTML.TargetNoopener`, `HTML.TargetNoreferrer`, `URI.AllowedSchemes`, `URI.Host`, `URI.DisableExternalResources`, `CSS.AllowedProperties`, `Attr.EnableID`, `AutoFormat.RemoveEmpty`, `AutoFormat.AutoParagraph`, `Core.RemoveInvalidImg`, `HTML.MaxImgLength` ada di `HTMLPurifier_ConfigSchema::instance()->info`; tidak ada yang perlu diperbaiki). Catatan perilaku: (1) `mergeConfigFrom` milik mews hanya menggabung kunci level atas, jadi `settings` di `config/purifier.php` proyek **menggantikan** seluruh `settings` bawaan paket (profil `test`, `youtube`, `custom_*` hilang — sengaja); (2) HTMLPurifier menambahkan `rel="noreferrer noopener"` pada tautan ber-`target`; pasca-proses DOM menimpa menjadi persis `noopener noreferrer`; (3) `<img>` tanpa `alt` diberi `alt` dari nama berkas; (4) `<b>`/`<font>` dilepas (isi teks dipertahankan); (5) `HTML.MaxImgLength` memotong `width`/`height` > 1600 menjadi 1600; (6) kunci profil berisi titik (`'URI.Host'`) tidak bisa di-set lewat `config('a.b.URI.Host', …)` — tes mengganti seluruh array `purifier.settings.rich_text` dengan `array_replace`; (7) mengubah `config('app.url')` tidak mengubah `URI.Host` (dihitung saat berkas konfigurasi dimuat), sehingga tes mengaturnya eksplisit; (8) `DOMDocument::loadHTML('<?xml encoding="UTF-8"><div>…</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD)` mempertahankan karakter UTF-8 (— “ ” é) tanpa entitas numerik.

**Test IDs:** `SEK-08` (tingkat unit: `<script>`, `onerror`, `javascript:`, `style`, `<iframe>`, `<svg>`, `data:` dibuang; `p, ul, strong, a, img` ber-alt dipertahankan). Penerapan ke 5 kolom rich text di fase 2/4.

- [ ] **Step 1: Verifikasi prasyarat**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git branch --show-current
& $PHP $COMPOSER show mews/purifier ezyang/htmlpurifier | Select-String -Pattern '^(name|versions)'
Test-Path config\purifier.php
Get-Content storage\app\.gitignore
```
Expected: `fase-1-fondasi`; `mews/purifier` versi `3.4.x` dan `ezyang/htmlpurifier` versi `4.19.x`; `False`; isi `storage\app\.gitignore` = `*`, `!private/`, `!public/`, `!.gitignore`.

#### Siklus A — konfigurasi `config/purifier.php`

- [ ] **Step 2: Publikasikan konfigurasi paket**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan vendor:publish --provider="Mews\Purifier\PurifierServiceProvider"
Test-Path config\purifier.php
```
Expected: `Copying file [vendor\mews\purifier\config\purifier.php] to [config\purifier.php]` lalu `DONE`; `True`.

- [ ] **Step 3: Izinkan folder `storage/app/purifier` dilacak git**

Edit `storage/app/.gitignore`: ganti baris `!public/` dengan dua baris berikut.

```gitignore
!public/
!purifier/
```

Hasil akhir berkas:

```gitignore
*
!private/
!public/
!purifier/
!.gitignore
```

- [ ] **Step 4: Tulis tes `tests/Feature/Content/PurifierConfigTest.php`**

```php
<?php

namespace Tests\Feature\Content;

use HTMLPurifier_Config;
use HTMLPurifier_ConfigSchema;
use Tests\TestCase;

class PurifierConfigTest extends TestCase
{
    public function test_global_settings_follow_the_blueprint(): void
    {
        $this->assertSame('UTF-8', config('purifier.encoding'));
        $this->assertTrue(config('purifier.finalize'));
        $this->assertFalse(config('purifier.ignoreNonStrings'));
        $this->assertSame(storage_path('app/purifier'), config('purifier.cachePath'));
        $this->assertSame(0755, config('purifier.cacheFileMode'));
    }

    public function test_only_the_default_and_rich_text_profiles_exist(): void
    {
        $this->assertSame(['default', 'rich_text'], array_keys(config('purifier.settings')));
    }

    public function test_default_profile_allows_only_basic_inline_markup(): void
    {
        $this->assertSame([
            'HTML.Doctype' => 'HTML 4.01 Transitional',
            'HTML.Allowed' => 'p,br,strong,em',
            'AutoFormat.RemoveEmpty' => true,
        ], config('purifier.settings.default'));
    }

    public function test_rich_text_profile_matches_blueprint_section_7_2(): void
    {
        $profile = config('purifier.settings.rich_text');
        $host = $profile['URI.Host'];
        unset($profile['URI.Host']);

        $this->assertSame([
            'HTML.Doctype' => 'HTML 4.01 Transitional',
            'HTML.Allowed' => 'p,br,h2,h3,h4,strong,em,u,ul,ol,li,a[href|title|target|rel],img[src|alt|width|height],blockquote,table,thead,tbody,tr,th,td',
            'Attr.AllowedFrameTargets' => ['_blank'],
            'Attr.AllowedRel' => ['noopener', 'noreferrer'],
            'HTML.TargetNoopener' => true,
            'HTML.TargetNoreferrer' => true,
            'URI.AllowedSchemes' => ['http' => true, 'https' => true, 'mailto' => true, 'tel' => true],
            'URI.DisableExternalResources' => true,
            'CSS.AllowedProperties' => [],
            'Attr.EnableID' => false,
            'AutoFormat.RemoveEmpty' => true,
            'AutoFormat.AutoParagraph' => false,
            'Core.RemoveInvalidImg' => true,
            'HTML.MaxImgLength' => 1600,
        ], $profile);

        $this->assertTrue($host === null || (is_string($host) && $host !== ''));
    }

    public function test_every_rich_text_key_is_a_valid_htmlpurifier_directive(): void
    {
        $directives = HTMLPurifier_ConfigSchema::instance()->info;

        foreach (array_keys(config('purifier.settings.rich_text')) as $key) {
            $this->assertArrayHasKey($key, $directives, "Kunci HTMLPurifier tidak valid: {$key}");
        }
    }

    public function test_rich_text_profile_loads_into_htmlpurifier_without_warnings(): void
    {
        $config = HTMLPurifier_Config::createDefault();
        $config->loadArray(config('purifier.settings.rich_text'));

        $this->assertSame('HTML 4.01 Transitional', $config->get('HTML.Doctype'));
        $this->assertSame(1600, $config->get('HTML.MaxImgLength'));
        $this->assertTrue($config->get('URI.DisableExternalResources'));
    }

    public function test_cache_directory_keeps_only_a_gitignore(): void
    {
        $path = base_path('storage/app/purifier/.gitignore');

        $this->assertFileExists($path);
        $this->assertSame("*\n!.gitignore\n", str_replace("\r\n", "\n", (string) file_get_contents($path)));
    }

    public function test_storage_app_gitignore_tracks_the_purifier_folder(): void
    {
        $lines = array_map('trim', file(base_path('storage/app/.gitignore')));

        $this->assertContains('!purifier/', $lines);
    }
}
```

- [ ] **Step 5: Jalankan tes — harus GAGAL**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=PurifierConfigTest
```
Expected: FAIL pada enam tes: profil bawaan paket (`default` berisi `div,b,strong,…`; ada profil `test`, `youtube`, `custom_*`; tidak ada `rich_text`) sehingga `only_the_default_and_rich_text_profiles_exist`, `default_profile…`, `rich_text_profile_matches…` gagal dengan "Failed asserting that two arrays are identical"; `every_rich_text_key…` dan `rich_text_profile_loads…` gagal karena `purifier.settings.rich_text` bernilai `null` (`TypeError`/`ArgumentCountError`); `cache_directory_keeps_only_a_gitignore` gagal karena berkas belum ada. Dua tes lain (`global_settings…`, `storage_app_gitignore…`) sudah lulus.

- [ ] **Step 6: Timpa `config/purifier.php` dengan profil proyek**

Baca berkas hasil publish, lalu ganti **seluruh isinya** dengan:

```php
<?php

/**
 * Konfigurasi HTMLPurifier (mews/purifier) untuk UNPAM HOSPITAL.
 *
 * Profil `rich_text` dipakai oleh App\Services\Content\HtmlSanitizer untuk lima kolom rich text
 * (pages.body, articles.body, clinics.description, facilities.description, room_categories.description).
 * Profil `default` hanya untuk teks pendek (p, br, strong, em).
 *
 * Tiga aturan yang tidak dapat diekspresikan lewat konfigurasi HTMLPurifier (img hanya /storage/...,
 * URL absolut ke host aplikasi dinormalkan ke path, tautan eksternal target _blank + rel noopener
 * noreferrer) ditegakkan oleh HtmlSanitizer sebagai pasca-proses DOM.
 *
 * Catatan: `settings` di sini menggantikan seluruh `settings` bawaan paket (mergeConfigFrom hanya
 * menggabung kunci level atas).
 *
 * @link http://htmlpurifier.org/live/configdoc/plain.html
 */

return [
    'encoding' => 'UTF-8',
    'finalize' => true,
    'ignoreNonStrings' => false,
    'cachePath' => storage_path('app/purifier'),
    'cacheFileMode' => 0755,
    'settings' => [
        'default' => [
            'HTML.Doctype' => 'HTML 4.01 Transitional',
            'HTML.Allowed' => 'p,br,strong,em',
            'AutoFormat.RemoveEmpty' => true,
        ],
        'rich_text' => [
            'HTML.Doctype' => 'HTML 4.01 Transitional',
            'HTML.Allowed' => 'p,br,h2,h3,h4,strong,em,u,ul,ol,li,'
                .'a[href|title|target|rel],img[src|alt|width|height],blockquote,'
                .'table,thead,tbody,tr,th,td',
            'Attr.AllowedFrameTargets' => ['_blank'],
            'Attr.AllowedRel' => ['noopener', 'noreferrer'],
            'HTML.TargetNoopener' => true,
            'HTML.TargetNoreferrer' => true,
            'URI.AllowedSchemes' => ['http' => true, 'https' => true, 'mailto' => true, 'tel' => true],
            'URI.Host' => parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: null,
            'URI.DisableExternalResources' => true,
            'CSS.AllowedProperties' => [],
            'Attr.EnableID' => false,
            'AutoFormat.RemoveEmpty' => true,
            'AutoFormat.AutoParagraph' => false,
            'Core.RemoveInvalidImg' => true,
            'HTML.MaxImgLength' => 1600,
        ],
    ],
];
```

- [ ] **Step 7: Buat `storage/app/purifier/.gitignore`**

Isi berkas (dua baris, akhiri dengan baris baru):

```gitignore
*
!.gitignore
```

- [ ] **Step 8: Jalankan tes — harus LULUS**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=PurifierConfigTest
```
Expected: PASS (8 tes).

- [ ] **Step 9: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint --test
git check-ignore -v storage/app/purifier/.gitignore; "exit=$LASTEXITCODE"
git add storage/app/.gitignore storage/app/purifier/.gitignore config/purifier.php tests/Feature/Content/PurifierConfigTest.php
git commit -m "feat(fase-1): konfigurasi purifier dengan profil rich_text dan folder cache ter-ignore"
```
Expected: Pint `PASS`; `git check-ignore` tidak mencetak apa pun dan `exit=1` (berkas tidak di-ignore, sehingga `git add` tanpa `-f` berhasil).

#### Siklus B — `HtmlSanitizer::clean()` lapis HTMLPurifier (SEK-08 tingkat unit)

- [ ] **Step 10: Tulis tes `tests/Feature/Content/HtmlSanitizerTest.php`**

```php
<?php

namespace Tests\Feature\Content;

use App\Services\Content\HtmlSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HtmlSanitizerTest extends TestCase
{
    private HtmlSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'http://unpam-hospital.test']);
        config(['purifier.settings.rich_text' => array_replace(
            config('purifier.settings.rich_text'),
            ['URI.Host' => 'unpam-hospital.test'],
        )]);

        $this->sanitizer = new HtmlSanitizer;
    }

    public function test_empty_input_returns_an_empty_string(): void
    {
        $this->assertSame('', $this->sanitizer->clean(null));
        $this->assertSame('', $this->sanitizer->clean(''));
        $this->assertSame('', $this->sanitizer->clean("  \n\t "));
    }

    /**
     * @return array<string, array{0: string, 1: list<string>}>
     */
    public static function dangerousPayloads(): array
    {
        return [
            'tag script' => ['<p>Halo</p><script>alert(1)</script>', ['<script', 'alert']],
            'atribut onerror' => ['<img src="/storage/photo/a.jpg" alt="x" onerror="alert(1)">', ['onerror', 'alert']],
            'atribut onclick' => ['<a href="/x" onclick="alert(1)">x</a>', ['onclick', 'alert']],
            'href javascript' => ['<a href="javascript:alert(1)">klik</a>', ['javascript', 'alert']],
            'href javascript campur huruf' => ['<a href="JaVaScRiPt:alert(1)">x</a>', ['javascript', 'alert']],
            'href javascript berawalan spasi' => ['<a href=" javascript:alert(1)">x</a>', ['javascript', 'alert']],
            'href javascript entitas html' => ['<a href="&#106;avascript:alert(1)">x</a>', ['javascript', 'alert']],
            'href vbscript' => ['<a href="vbscript:msgbox(1)">x</a>', ['vbscript', 'msgbox']],
            'href data' => ['<a href="data:text/html;base64,PHNjcmlwdD4=">x</a>', ['data:', 'base64']],
            'img data uri' => ['<img src="data:image/png;base64,AAAA" alt="x">', ['data:', 'base64']],
            'atribut style' => ['<p style="color:red;background:url(javascript:alert(1))">x</p>', ['style', 'javascript']],
            'tag style' => ['<style>body{display:none}</style><p>ok</p>', ['<style', 'display']],
            'iframe' => ['<iframe src="https://evil.test"></iframe>', ['iframe', 'evil.test']],
            'svg onload' => ['<svg onload="alert(1)"><circle/></svg>', ['svg', 'onload', 'alert']],
            'object embed form input' => ['<object data="x"></object><embed src="x"><form action="/x"><input name="a"></form>', ['<object', '<embed', '<form', '<input']],
            'video audio' => ['<video src="x"></video><audio src="y"></audio>', ['<video', '<audio']],
            'class id dan kelas Quill' => ['<p class="ql-align-center" id="a">x</p>', ['class', 'id=', 'ql-']],
        ];
    }

    /**
     * @param  list<string>  $forbidden
     */
    #[DataProvider('dangerousPayloads')]
    public function test_dangerous_markup_is_removed(string $html, array $forbidden): void
    {
        $clean = $this->sanitizer->clean($html);

        foreach ($forbidden as $fragment) {
            $this->assertStringNotContainsStringIgnoringCase($fragment, $clean, "Muatan lolos: {$fragment}");
        }
    }

    public function test_script_content_is_removed_but_the_surrounding_text_stays(): void
    {
        $this->assertSame('<p>Halo dunia</p>', $this->sanitizer->clean('<p>Halo <script>alert(1)</script>dunia</p>'));
    }

    public function test_style_class_and_id_attributes_are_stripped_from_allowed_tags(): void
    {
        $this->assertSame('<p>Teks</p>', $this->sanitizer->clean('<p style="color:red" class="ql-align-center" id="x">Teks</p>'));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function safeMarkup(): array
    {
        return [
            'paragraf, daftar, judul, kutipan' => ['<ul><li>satu</li></ul><h2>Judul</h2><p>Dokter — “anak” &amp; remaja <br>baris</p>'],
            'daftar berurut, kutipan, h3, h4, u, em' => ['<ol><li>a</li></ol><blockquote>kutip</blockquote><h3>H3</h3><h4>H4</h4><p><u>garis</u> <em>miring</em></p>'],
            'strong, em, tautan internal bertitle' => ['<p><strong>tebal</strong> <em>miring</em> <a href="/dokter" title="Daftar dokter">dokter</a></p>'],
            'gambar ber-alt dengan ukuran' => ['<p>Satu</p><p><img src="/storage/photo/2026/10/a.jpg" alt="Foto dokter" width="300" height="200"></p>'],
            'tabel sederhana' => ['<table><thead><tr><th>A</th></tr></thead><tbody><tr><td>1</td></tr></tbody></table>'],
            'entitas teks' => ['<p>1 &lt; 2 &amp;&amp; 3 &gt; 2</p>'],
        ];
    }

    #[DataProvider('safeMarkup')]
    public function test_safe_markup_is_preserved_unchanged(string $html): void
    {
        $this->assertSame($html, $this->sanitizer->clean($html));
    }

    public function test_image_without_alt_gets_an_alt_from_the_file_name(): void
    {
        $this->assertStringContainsString('alt="a.jpg"', $this->sanitizer->clean('<img src="/storage/photo/a.jpg">'));
    }

    public function test_image_dimensions_are_capped_at_1600(): void
    {
        $clean = $this->sanitizer->clean('<img src="/storage/photo/a.jpg" alt="x" width="9999" height="20">');

        $this->assertStringContainsString('width="1600"', $clean);
        $this->assertStringContainsString('height="20"', $clean);
    }

    public function test_unsupported_formatting_tags_are_unwrapped(): void
    {
        $this->assertSame('tebal x', $this->sanitizer->clean('<b>tebal</b> <font color="red">x</font>'));
    }

    public function test_plain_text_without_tags_is_returned_as_is(): void
    {
        $this->assertSame('teks polos', $this->sanitizer->clean('teks polos'));
    }
}
```

- [ ] **Step 11: Jalankan tes — harus GAGAL**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=HtmlSanitizerTest
```
Expected: FAIL — semua tes `Error: Class "App\Services\Content\HtmlSanitizer" not found`.

- [ ] **Step 12: Implementasi `app/Services/Content/HtmlSanitizer.php` (lapis HTMLPurifier)**

```php
<?php

declare(strict_types=1);

namespace App\Services\Content;

use Mews\Purifier\Facades\Purifier;

final class HtmlSanitizer
{
    /**
     * Bersihkan HTML rich text dengan HTMLPurifier (profil rich_text, 10 §7.2).
     */
    public function clean(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        $purified = Purifier::clean($html, 'rich_text');

        return is_string($purified) ? $purified : '';
    }
}
```

- [ ] **Step 13: Jalankan tes — harus LULUS**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=HtmlSanitizerTest
```
Expected: PASS (17 kasus `dangerousPayloads` + 6 kasus `safeMarkup` + 7 tes biasa = 30 tes). Berkas cache HTMLPurifier muncul di `storage\app\purifier\`.

- [ ] **Step 14: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint --test
git status --short
git add app/Services/Content/HtmlSanitizer.php tests/Feature/Content/HtmlSanitizerTest.php
git commit -m "feat(fase-1): HtmlSanitizer lapis HTMLPurifier dengan tes muatan XSS"
```
Expected: Pint `PASS`; `git status --short` hanya memuat dua berkas di atas (berkas cache di `storage/app/purifier/` tidak muncul karena di-ignore oleh `.gitignore` folder itu).

#### Siklus C — pasca-proses DOM (img `/storage`, normalisasi URL, tautan eksternal)

- [ ] **Step 15: Tulis tes `tests/Feature/Content/HtmlSanitizerDomTest.php`**

```php
<?php

namespace Tests\Feature\Content;

use App\Services\Content\HtmlSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HtmlSanitizerDomTest extends TestCase
{
    private HtmlSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'http://unpam-hospital.test']);
        $this->useProfile([]);

        $this->sanitizer = new HtmlSanitizer;
    }

    /**
     * Ganti profil rich_text untuk tes ini (kunci berisi titik tidak bisa di-set lewat notasi titik config()).
     *
     * @param  array<string, mixed>  $overrides
     */
    private function useProfile(array $overrides): void
    {
        config(['purifier.settings.rich_text' => array_replace(
            config('purifier.settings.rich_text'),
            ['URI.Host' => 'unpam-hospital.test'],
            $overrides,
        )]);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function foreignImages(): array
    {
        return [
            'path bukan /storage' => ['<img src="/images/x.jpg" alt="x">'],
            'path relatif tanpa garis miring' => ['<img src="images/x.jpg" alt="x">'],
            'host lain' => ['<img src="https://evil.test/storage/a.jpg" alt="x">'],
            'protocol-relative' => ['<img src="//unpam-hospital.test/storage/a.jpg" alt="x">'],
            'traversal' => ['<img src="/storage/../../etc/passwd" alt="x">'],
            'host sendiri tetapi bukan /storage' => ['<img src="http://unpam-hospital.test/uploads/a.jpg" alt="x">'],
        ];
    }

    #[DataProvider('foreignImages')]
    public function test_images_outside_storage_are_removed(string $html): void
    {
        $this->assertSame('', $this->sanitizer->clean($html));
    }

    public function test_removing_an_image_keeps_the_surrounding_text(): void
    {
        $clean = $this->sanitizer->clean('<p>Teks <img src="/images/x.jpg" alt="x"> lagi</p>');

        $this->assertStringContainsString('Teks', $clean);
        $this->assertStringContainsString('lagi', $clean);
        $this->assertStringNotContainsString('<img', $clean);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function absoluteStorageImages(): array
    {
        return [
            'http' => ['http://unpam-hospital.test/storage/photo/a.jpg', '/storage/photo/a.jpg'],
            'https dengan query' => ['https://unpam-hospital.test/storage/photo/a.jpg?v=2', '/storage/photo/a.jpg?v=2'],
            'dengan port' => ['http://unpam-hospital.test:8000/storage/photo/a.jpg', '/storage/photo/a.jpg'],
            'sudah relatif' => ['/storage/photo/a.jpg', '/storage/photo/a.jpg'],
        ];
    }

    #[DataProvider('absoluteStorageImages')]
    public function test_absolute_urls_to_the_app_host_are_normalized_to_a_relative_path(string $source, string $expected): void
    {
        $clean = $this->sanitizer->clean('<img src="'.$source.'" alt="Foto">');

        $this->assertSame('<img src="'.$expected.'" alt="Foto">', $clean);
    }

    public function test_second_layer_removes_external_images_even_when_purifier_allows_them(): void
    {
        $this->useProfile(['URI.DisableExternalResources' => false]);

        $clean = $this->sanitizer->clean('<img src="https://evil.test/a.jpg" alt="x"><img src="http://unpam-hospital.test/storage/a.jpg" alt="ok">');

        $this->assertSame('<img src="/storage/a.jpg" alt="ok">', $clean);
    }

    public function test_external_links_get_target_blank_and_noopener_noreferrer(): void
    {
        $clean = $this->sanitizer->clean('<a href="https://example.org/halaman" title="Judul">ext</a>');

        $this->assertStringContainsString('href="https://example.org/halaman"', $clean);
        $this->assertStringContainsString('target="_blank"', $clean);
        $this->assertStringContainsString('rel="noopener noreferrer"', $clean);
    }

    public function test_protocol_relative_links_are_treated_as_external(): void
    {
        $clean = $this->sanitizer->clean('<a href="//evil.test/x">x</a>');

        $this->assertStringContainsString('target="_blank"', $clean);
        $this->assertStringContainsString('rel="noopener noreferrer"', $clean);
    }

    public function test_rel_is_forced_to_exactly_noopener_noreferrer(): void
    {
        $clean = $this->sanitizer->clean('<a href="https://example.org/x" target="_blank" rel="noopener">x</a>');

        $this->assertSame('<a href="https://example.org/x" target="_blank" rel="noopener noreferrer">x</a>', $clean);
    }

    public function test_internal_links_are_left_alone(): void
    {
        $this->assertSame('<a href="/dokter">d</a>', $this->sanitizer->clean('<a href="/dokter">d</a>'));

        $absolute = $this->sanitizer->clean('<a href="http://unpam-hospital.test/dokter">d</a>');
        $this->assertStringNotContainsString('target=', $absolute);
        $this->assertStringNotContainsString('rel=', $absolute);

        $shouty = $this->sanitizer->clean('<a href="HTTPS://UNPAM-HOSPITAL.TEST/dokter">d</a>');
        $this->assertStringNotContainsString('target=', $shouty);
    }

    public function test_mailto_and_tel_links_are_kept(): void
    {
        $html = '<a href="mailto:a@b.test">m</a> <a href="tel:+62211234">t</a>';

        $this->assertSame($html, $this->sanitizer->clean($html));
    }

    public function test_second_layer_removes_disallowed_schemes_even_when_purifier_allows_them(): void
    {
        $this->useProfile(['URI.AllowedSchemes' => ['http' => true, 'https' => true, 'mailto' => true, 'tel' => true, 'ftp' => true]]);

        $this->assertSame('<a>f</a>', $this->sanitizer->clean('<a href="ftp://x.test/f">f</a>'));
    }

    public function test_utf8_text_survives_the_dom_round_trip(): void
    {
        $html = '<p>Dokter spesialis — anak “ya” é ü</p>';

        $this->assertSame($html, $this->sanitizer->clean($html));
    }
}
```

- [ ] **Step 16: Jalankan tes — harus GAGAL**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=HtmlSanitizerDomTest
```
Expected: FAIL — gambar `/images/x.jpg`, `images/x.jpg`, `//unpam-hospital.test/…`, `/storage/../…`, dan `…/uploads/…` tetap ada (kini HTMLPurifier meloloskan path relatif); URL absolut tidak dinormalkan (`src="http://unpam-hospital.test/storage/…"` tetap absolut); `rel` bernilai `noreferrer noopener` (urutan HTMLPurifier) dan tautan eksternal tanpa `target` tidak diberi `target="_blank"`; skema `ftp` lolos. Tes `mailto_and_tel`, `internal_links_are_left_alone` (sebagian), dan `utf8_text_survives…` sudah lulus.

- [ ] **Step 17: Tambah pasca-proses DOM di `app/Services/Content/HtmlSanitizer.php`**

Edit 1 — impor DOM: ganti baris `use Mews\Purifier\Facades\Purifier;` dengan:

```php
use DOMDocument;
use DOMElement;
use Mews\Purifier\Facades\Purifier;
```

Edit 2 — konstanta: ganti tiga baris berurutan

```php
final class HtmlSanitizer
{
    /**
```

dengan:

```php
final class HtmlSanitizer
{
    private const ALLOWED_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    /**
```

Edit 3 — hubungkan `clean()` dan tambah metode privat: ganti tiga baris penutup

```php
        return is_string($purified) ? $purified : '';
    }
}
```

dengan:

```php
        return $this->postProcess(is_string($purified) ? $purified : '');
    }

    private function postProcess(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument('1.0', 'UTF-8');
        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8"><div>'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $loaded ? $document->documentElement : null;

        if ($root === null) {
            return '';
        }

        $host = $this->appHost();

        foreach (iterator_to_array($root->getElementsByTagName('img')) as $image) {
            $source = $this->normalizeImageSource($image->getAttribute('src'), $host);

            if ($source === null) {
                $image->parentNode?->removeChild($image);

                continue;
            }

            $image->setAttribute('src', $source);
        }

        foreach (iterator_to_array($root->getElementsByTagName('a')) as $link) {
            $this->normalizeLink($link, $host);
        }

        $output = '';

        foreach ($root->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return trim($output);
    }

    /**
     * Hanya path /storage/... yang lolos; URL absolut ke host aplikasi dinormalkan menjadi path.
     */
    private function normalizeImageSource(string $source, string $host): ?string
    {
        $source = trim($source);

        if ($source === '' || preg_match('/[\x00-\x1F\x7F]/', $source) === 1 || str_contains($source, '\\')) {
            return null;
        }

        if (str_starts_with($source, '/storage/')) {
            return $this->isSafeStoragePath($source) ? $source : null;
        }

        $parts = parse_url($source);

        if ($parts === false || ! isset($parts['scheme'], $parts['host']) || $host === '') {
            return null;
        }

        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true) || strtolower($parts['host']) !== $host) {
            return null;
        }

        $path = ($parts['path'] ?? '').(isset($parts['query']) ? '?'.$parts['query'] : '');

        return $this->isSafeStoragePath($path) ? $path : null;
    }

    private function isSafeStoragePath(string $path): bool
    {
        return str_starts_with($path, '/storage/')
            && strlen($path) > strlen('/storage/')
            && ! str_contains($path, '..');
    }

    /**
     * Lapis kedua setelah HTMLPurifier: skema selain http/https/mailto/tel dibuang,
     * tautan eksternal dipaksa target _blank + rel noopener noreferrer.
     */
    private function normalizeLink(DOMElement $link, string $host): void
    {
        if (! $link->hasAttribute('href')) {
            return;
        }

        $compact = preg_replace('/[\x00-\x20\x7F]+/', '', $link->getAttribute('href')) ?? '';

        if ($compact === '') {
            $link->removeAttribute('href');

            return;
        }

        $scheme = preg_match('/^([a-z][a-z0-9+.\-]*):/i', $compact, $match) === 1 ? strtolower($match[1]) : null;

        if ($scheme !== null && ! in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            $link->removeAttribute('href');

            return;
        }

        $protocolRelative = str_starts_with($compact, '//');

        if (! in_array($scheme, ['http', 'https'], true) && ! $protocolRelative) {
            return;
        }

        $linkHost = strtolower((string) parse_url($protocolRelative ? 'http:'.$compact : $compact, PHP_URL_HOST));

        if ($linkHost === '' || $linkHost !== $host) {
            $link->setAttribute('target', '_blank');
            $link->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private function appHost(): string
    {
        return strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
    }
}
```

- [ ] **Step 18: Jalankan tes — harus LULUS**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="HtmlSanitizerDomTest|HtmlSanitizerTest"
```
Expected: PASS (`HtmlSanitizerDomTest`: 6 + 4 kasus provider + 9 tes biasa = 19 tes; `HtmlSanitizerTest` tetap 30 tes).

- [ ] **Step 19: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint --test
git add app/Services/Content/HtmlSanitizer.php tests/Feature/Content/HtmlSanitizerDomTest.php
git commit -m "feat(fase-1): pasca-proses DOM HtmlSanitizer (img /storage, normalisasi URL, tautan eksternal)"
```
Expected: Pint `PASS`.

#### Siklus D — `toPlainText()`

- [ ] **Step 20: Tulis tes `tests/Feature/Content/HtmlSanitizerPlainTextTest.php`**

```php
<?php

namespace Tests\Feature\Content;

use App\Services\Content\HtmlSanitizer;
use Tests\TestCase;

class HtmlSanitizerPlainTextTest extends TestCase
{
    private HtmlSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'http://unpam-hospital.test']);
        config(['purifier.settings.rich_text' => array_replace(
            config('purifier.settings.rich_text'),
            ['URI.Host' => 'unpam-hospital.test'],
        )]);

        $this->sanitizer = new HtmlSanitizer;
    }

    public function test_tags_are_stripped_blocks_are_separated_and_entities_decoded(): void
    {
        $html = '<p>Satu&nbsp;dua</p><p>Tiga &amp; <strong>empat</strong></p><script>x()</script><ul><li>lima</li><li>enam</li></ul>';

        $this->assertSame('Satu dua Tiga & empat lima enam', $this->sanitizer->toPlainText($html));
    }

    public function test_escaped_angle_brackets_become_plain_characters(): void
    {
        $this->assertSame('1 < 2', $this->sanitizer->toPlainText('<p>1 &lt; 2</p>'));
    }

    public function test_dangerous_markup_never_reaches_the_plain_text(): void
    {
        $this->assertSame('Aman', $this->sanitizer->toPlainText('<p>Aman</p><script>alert(1)</script>'));
    }

    public function test_short_text_is_not_truncated(): void
    {
        $this->assertSame('pendek', $this->sanitizer->toPlainText('<p>pendek</p>'));
    }

    public function test_long_text_is_cut_at_the_limit_with_an_ellipsis(): void
    {
        $text = $this->sanitizer->toPlainText('<p>'.str_repeat('kata ', 100).'</p>', 20);

        $this->assertSame('kata kata kata kata…', $text);
    }

    public function test_default_limit_is_160_characters(): void
    {
        $text = $this->sanitizer->toPlainText(str_repeat('<p>'.str_repeat('abcde ', 40).'</p>', 3));

        $this->assertSame(161, mb_strlen($text));
        $this->assertStringEndsWith('…', $text);
    }

    public function test_empty_input_returns_an_empty_string(): void
    {
        $this->assertSame('', $this->sanitizer->toPlainText(null));
        $this->assertSame('', $this->sanitizer->toPlainText(''));
        $this->assertSame('', $this->sanitizer->toPlainText('<p>   </p>'));
    }
}
```

- [ ] **Step 21: Jalankan tes — harus GAGAL**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=HtmlSanitizerPlainTextTest
```
Expected: FAIL — ketujuh tes `Error: Call to undefined method App\Services\Content\HtmlSanitizer::toPlainText()`.

- [ ] **Step 22: Tambah `toPlainText()` di `app/Services/Content/HtmlSanitizer.php`**

Edit 1 — impor `Str`: ganti baris `use Mews\Purifier\Facades\Purifier;` dengan:

```php
use Illuminate\Support\Str;
use Mews\Purifier\Facades\Purifier;
```

Edit 2 — sisipkan metode publik: ganti awalan baris `    private function postProcess(` (satu-satunya kemunculan) dengan blok berikut diikuti awalan yang sama:

```php
    /**
     * Teks polos untuk meta description atau ringkasan: HTML dibersihkan dulu, tag dibuang,
     * entitas didekode, spasi dirapikan, lalu dipotong pada $limit karakter (+ "…" bila terpotong).
     */
    public function toPlainText(?string $html, int $limit = 160): string
    {
        $clean = $this->clean($html);

        if ($clean === '') {
            return '';
        }

        $spaced = preg_replace('~</(?:p|li|h[2-4]|blockquote|tr|td|th)>|<br\s*/?>~i', ' ', $clean) ?? $clean;
        $text = html_entity_decode(strip_tags($spaced), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        return Str::limit($text, max($limit, 1), '…');
    }

    private function postProcess(
```

- [ ] **Step 23: Jalankan tes — harus LULUS**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="HtmlSanitizer"
```
Expected: PASS (`HtmlSanitizerTest` 30 + `HtmlSanitizerDomTest` 19 + `HtmlSanitizerPlainTextTest` 7 = 56 tes).

- [ ] **Step 24: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint --test
git add app/Services/Content/HtmlSanitizer.php tests/Feature/Content/HtmlSanitizerPlainTextTest.php
git commit -m "feat(fase-1): HtmlSanitizer::toPlainText untuk meta description dan ringkasan"
```
Expected: Pint `PASS`.

- [ ] **Step 25: Verifikasi gerbang Task 6**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test
& $PHP vendor\bin\pint --test
git status --short
```
Expected: seluruh tes hijau; Pint `PASS`; `git status` kosong (cache HTMLPurifier di `storage/app/purifier/` tidak muncul).

---

### Task 7: Pengaturan situs — `website_settings`, `WebsiteSetting`, `SettingsService`, `SettingsSeeder`

**Files:**
- Create: `database/migrations/2026_10_02_002200_create_website_settings_table.php`
- Create: `app/Models/WebsiteSetting.php`
- Create: `app/Services/Settings/SettingsService.php`
- Create: `database/seeders/SettingsSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`, `app/Providers/AppServiceProvider.php` (`singleton(SettingsService::class)`)
- Test: `tests/Feature/Settings/SettingsSeederTest.php`, `tests/Feature/Settings/SettingsSeederCacheTest.php`, `tests/Feature/Settings/SettingsServiceTest.php`, `tests/Feature/Settings/SettingsServiceWriteTest.php`, `tests/Feature/Settings/DatabaseSeederWiringTest.php`

(Kontrak menyebut `SettingsServiceTest` dan `SettingsSeederTest`; sisi tulis, cache seeder, dan kait `DatabaseSeeder` dipisah per siklus TDD.)

**Interfaces:**
- Consumes: `AuditLogger::log(...)`, `Actor::staff(User)` (Task 4), `Media` + `MediaService::store()` (Task 5, hanya untuk tes `logoUrl()`), `User` + `UserFactory::staff()` (Task 2).
- Produces:
  - Tabel `website_settings` persis 09 §7.2 (UNIQUE `key`, index `group`, FK `updated_by` SET NULL). `WebsiteSetting`: `$fillable = ['key','value','type','group','label','updated_by']`, `updater(): BelongsTo`.
  - `SettingsSeeder::DEFAULTS` (19 kunci ⇒ `['type','group','label','value']` persis 00 E.7 + 13 §8.9); `run()` = `firstOrCreate` per `key` (nilai yang sudah diubah admin **tidak** ditimpa) lalu `SettingsService::forget()`. `DatabaseSeeder` memanggil `RolePermissionSeeder`, `SettingsSeeder`.
  - `App\Services\Settings\SettingsService` (`final`, constructor `(AuditLogger $audit)`, singleton): `CACHE_KEY = 'settings:all'`; `get`, `string`, `int`, `bool`, `set`, `setMany`, `group`, `logoUrl`, `whatsappUrl`, `forget` persis kerangka.
- Perilaku (kerangka + keputusan penulis rencana): (1) cache `Cache::rememberForever('settings:all', …)` berisi peta `key ⇒ ['type','value']` + memo instance; (2) nilai NULL atau `''` ⇒ `$default`; `'0'` **bukan** kosong (`int()` mengembalikan 0, `bool()` mengembalikan `false` meskipun default `true`); (3) `group()` mengembalikan nilai **bertipe** (`null` bila kosong) urut `id`; (4) `setMany()` memvalidasi tipe sebelum menulis (int non-angka, array pada tipe non-json, string pada tipe json ⇒ `InvalidArgumentException`), menormalkan `''` menjadi NULL, memangkas spasi string, dan menyimpan bool sebagai `'1'`/`'0'`; (5) `old`/`new` pada audit = nilai tersimpan (string/NULL) hanya untuk kunci yang berubah; (6) `forget()` dipanggil lewat `DB::afterCommit` sehingga rollback tidak membuang cache; (7) `logoUrl()` tidak memeriksa keberadaan berkas fisik (fallback gambar hilang ditangani `x-image`).
- Verifikasi API terhadap `vendor/` (Laravel 12.69): `Cache::rememberForever` (store `array` pada tes), `DB::afterCommit` (perilaku pada tes `RefreshDatabase` seperti di Task 5), `Model::shouldBeStrict` (semua kolom yang dibaca ikut di-`select`; `get(['key','type','value'])` aman karena hanya itu yang dibaca), `Schema`/`firstOrCreate` dengan `$fillable` penuh, `lockForUpdate()` hanya di dalam transaksi.

**Test IDs:** `SET-01`, Review Focus #5.

- [ ] **Step 1: Verifikasi prasyarat**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git branch --show-current
Test-Path app\Services\Media\MediaService.php, app\Services\Audit\AuditLogger.php, database\seeders\DatabaseSeeder.php
& $PHP artisan test
```
Expected: `fase-1-fondasi`; tiga baris `True`; seluruh tes (Task 1–6) hijau.

#### Siklus A — tabel, model, dan seeder

- [ ] **Step 2: Tulis tes `tests/Feature/Settings/SettingsSeederTest.php`**

```php
<?php

namespace Tests\Feature\Settings;

use App\Models\WebsiteSetting;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SettingsSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Salinan literal 00 E.7 (nilai) + 13 §8.9 (type, group, label); urutan = urutan 00 E.7.
     *
     * @return array<string, array{0: string, 1: string, 2: string, 3: string, 4: ?string}>
     */
    public static function defaults(): array
    {
        return [
            'site.name' => ['site.name', 'string', 'identitas', 'Nama situs', 'UNPAM HOSPITAL'],
            'site.tagline' => ['site.tagline', 'string', 'identitas', 'Tagline', 'Layanan kesehatan yang ramah dan terpercaya'],
            'site.logo_media_id' => ['site.logo_media_id', 'int', 'identitas', 'Logo situs (kosong = placeholder bawaan)', null],
            'contact.address' => ['contact.address', 'text', 'kontak', 'Alamat', 'Universitas Pamulang Kampus 2 Viktor, Jl. Raya Puspitek, Buaran, Kecamatan Pamulang, Kota Tangerang Selatan, Banten 15310'],
            'contact.phone' => ['contact.phone', 'string', 'kontak', 'Telepon (placeholder)', '(021) 0000-0000'],
            'contact.emergency_phone' => ['contact.emergency_phone', 'string', 'kontak', 'Telepon darurat (placeholder)', '(021) 0000-0119'],
            'contact.whatsapp' => ['contact.whatsapp', 'string', 'kontak', 'Nomor WhatsApp (format 62…, placeholder)', '6280000000000'],
            'contact.email' => ['contact.email', 'email', 'kontak', 'E-mail kontak (placeholder)', 'info@unpamhospital.test'],
            'contact.operational_hours' => ['contact.operational_hours', 'string', 'kontak', 'Jam operasional', 'Senin–Sabtu 08.00–20.00 (contoh)'],
            'map.directions_url' => ['map.directions_url', 'url', 'kontak', 'Tautan Petunjuk Arah', 'https://www.google.com/maps/search/?api=1&query=Universitas+Pamulang+Kampus+2+Viktor'],
            'map.embed_url' => ['map.embed_url', 'url', 'kontak', 'Tautan peta tersemat', 'https://www.google.com/maps?q=Universitas+Pamulang+Kampus+2+Viktor&output=embed'],
            'booking.window_days' => ['booking.window_days', 'int', 'booking', 'Jendela pendaftaran (hari)', '30'],
            'booking.online_cutoff_minutes' => ['booking.online_cutoff_minutes', 'int', 'booking', 'Batas pendaftaran online (menit sebelum sesi)', '60'],
            'booking.cancel_cutoff_minutes' => ['booking.cancel_cutoff_minutes', 'int', 'booking', 'Batas pembatalan pasien (menit sebelum sesi)', '120'],
            'booking.checkin_open_minutes' => ['booking.checkin_open_minutes', 'int', 'booking', 'Check-in dibuka (menit sebelum sesi)', '60'],
            'booking.max_active_per_patient' => ['booking.max_active_per_patient', 'int', 'booking', 'Maks. pendaftaran aktif per pasien', '3'],
            'booking.auto_confirm' => ['booking.auto_confirm', 'bool', 'booking', 'Konfirmasi otomatis', '0'],
            'seo.default_meta_description' => ['seo.default_meta_description', 'text', 'seo', 'Meta description bawaan', 'UNPAM HOSPITAL — informasi layanan, dokter, jadwal praktik, dan pendaftaran kunjungan rawat jalan (situs prototipe).'],
            'privacy.consent_version' => ['privacy.consent_version', 'string', 'privasi', 'Versi persetujuan privasi', '1.0'],
        ];
    }

    #[DataProvider('defaults')]
    public function test_each_default_matches_the_blueprint(string $key, string $type, string $group, string $label, ?string $value): void
    {
        $this->seed(SettingsSeeder::class);

        $setting = WebsiteSetting::query()->where('key', $key)->firstOrFail();

        $this->assertSame($type, $setting->type);
        $this->assertSame($group, $setting->group);
        $this->assertSame($label, $setting->label);
        $this->assertSame($value, $setting->value);
        $this->assertNull($setting->updated_by);
    }

    public function test_exactly_the_19_blueprint_keys_are_seeded_in_blueprint_order(): void
    {
        $this->seed(SettingsSeeder::class);

        $this->assertCount(19, SettingsSeeder::DEFAULTS);
        $this->assertSame(array_keys(self::defaults()), array_keys(SettingsSeeder::DEFAULTS));
        $this->assertSame(array_keys(self::defaults()), WebsiteSetting::query()->orderBy('id')->pluck('key')->all());
    }

    public function test_defaults_constant_has_the_four_fields_with_known_types_and_groups(): void
    {
        foreach (SettingsSeeder::DEFAULTS as $key => $setting) {
            $this->assertSame(['type', 'group', 'label', 'value'], array_keys($setting), $key);
            $this->assertContains($setting['type'], ['string', 'text', 'int', 'bool', 'url', 'email', 'json'], $key);
            $this->assertContains($setting['group'], ['identitas', 'kontak', 'booking', 'seo', 'privasi'], $key);
        }
    }

    public function test_groups_have_the_blueprint_sizes(): void
    {
        $this->seed(SettingsSeeder::class);

        $counts = WebsiteSetting::query()->selectRaw('`group`, count(*) as total')->groupBy('group')->pluck('total', 'group')->map(fn ($total): int => (int) $total)->all();
        ksort($counts);

        $this->assertSame(['booking' => 6, 'identitas' => 3, 'kontak' => 8, 'privasi' => 1, 'seo' => 1], $counts);
    }

    public function test_seeding_twice_is_idempotent(): void
    {
        $this->seed(SettingsSeeder::class);
        $this->seed(SettingsSeeder::class);

        $this->assertSame(19, WebsiteSetting::query()->count());
    }

    public function test_seeding_does_not_overwrite_values_changed_by_an_admin_but_restores_deleted_keys(): void
    {
        $this->seed(SettingsSeeder::class);

        WebsiteSetting::query()->where('key', 'site.name')->update(['value' => 'RS Pamulang']);
        WebsiteSetting::query()->where('key', 'booking.window_days')->delete();

        $this->seed(SettingsSeeder::class);

        $this->assertSame('RS Pamulang', WebsiteSetting::query()->where('key', 'site.name')->value('value'));
        $this->assertSame('30', WebsiteSetting::query()->where('key', 'booking.window_days')->value('value'));
        $this->assertSame(19, WebsiteSetting::query()->count());
    }
}
```

- [ ] **Step 3: Jalankan tes — harus GAGAL**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=SettingsSeederTest
```
Expected: FAIL — semua tes `Error: Class "Database\Seeders\SettingsSeeder" not found`.

- [ ] **Step 4: Tulis migration `database/migrations/2026_10_02_002200_create_website_settings_table.php`**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->string('type', 10);
            $table->string('group', 20)->index();
            $table->string('label', 150);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_settings');
    }
};
```

- [ ] **Step 5: Tulis model `app/Models/WebsiteSetting.php`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tidak dibaca langsung oleh view: seluruh akses lewat App\Services\Settings\SettingsService.
 */
class WebsiteSetting extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'label',
        'updated_by',
    ];

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
```

- [ ] **Step 6: Tulis seeder `database/seeders/SettingsSeeder.php`**

```php
<?php

namespace Database\Seeders;

use App\Models\WebsiteSetting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * 19 kunci baku: nilai default 00 E.7; type, group, dan label 13 §8.9.
     * Nilai disimpan sebagai string (bool false = '0'); NULL = kosong (pakai default aplikasi).
     *
     * @var array<string, array{type: string, group: string, label: string, value: ?string}>
     */
    public const DEFAULTS = [
        'site.name' => ['type' => 'string', 'group' => 'identitas', 'label' => 'Nama situs', 'value' => 'UNPAM HOSPITAL'],
        'site.tagline' => ['type' => 'string', 'group' => 'identitas', 'label' => 'Tagline', 'value' => 'Layanan kesehatan yang ramah dan terpercaya'],
        'site.logo_media_id' => ['type' => 'int', 'group' => 'identitas', 'label' => 'Logo situs (kosong = placeholder bawaan)', 'value' => null],
        'contact.address' => ['type' => 'text', 'group' => 'kontak', 'label' => 'Alamat', 'value' => 'Universitas Pamulang Kampus 2 Viktor, Jl. Raya Puspitek, Buaran, Kecamatan Pamulang, Kota Tangerang Selatan, Banten 15310'],
        'contact.phone' => ['type' => 'string', 'group' => 'kontak', 'label' => 'Telepon (placeholder)', 'value' => '(021) 0000-0000'],
        'contact.emergency_phone' => ['type' => 'string', 'group' => 'kontak', 'label' => 'Telepon darurat (placeholder)', 'value' => '(021) 0000-0119'],
        'contact.whatsapp' => ['type' => 'string', 'group' => 'kontak', 'label' => 'Nomor WhatsApp (format 62…, placeholder)', 'value' => '6280000000000'],
        'contact.email' => ['type' => 'email', 'group' => 'kontak', 'label' => 'E-mail kontak (placeholder)', 'value' => 'info@unpamhospital.test'],
        'contact.operational_hours' => ['type' => 'string', 'group' => 'kontak', 'label' => 'Jam operasional', 'value' => 'Senin–Sabtu 08.00–20.00 (contoh)'],
        'map.directions_url' => ['type' => 'url', 'group' => 'kontak', 'label' => 'Tautan Petunjuk Arah', 'value' => 'https://www.google.com/maps/search/?api=1&query=Universitas+Pamulang+Kampus+2+Viktor'],
        'map.embed_url' => ['type' => 'url', 'group' => 'kontak', 'label' => 'Tautan peta tersemat', 'value' => 'https://www.google.com/maps?q=Universitas+Pamulang+Kampus+2+Viktor&output=embed'],
        'booking.window_days' => ['type' => 'int', 'group' => 'booking', 'label' => 'Jendela pendaftaran (hari)', 'value' => '30'],
        'booking.online_cutoff_minutes' => ['type' => 'int', 'group' => 'booking', 'label' => 'Batas pendaftaran online (menit sebelum sesi)', 'value' => '60'],
        'booking.cancel_cutoff_minutes' => ['type' => 'int', 'group' => 'booking', 'label' => 'Batas pembatalan pasien (menit sebelum sesi)', 'value' => '120'],
        'booking.checkin_open_minutes' => ['type' => 'int', 'group' => 'booking', 'label' => 'Check-in dibuka (menit sebelum sesi)', 'value' => '60'],
        'booking.max_active_per_patient' => ['type' => 'int', 'group' => 'booking', 'label' => 'Maks. pendaftaran aktif per pasien', 'value' => '3'],
        'booking.auto_confirm' => ['type' => 'bool', 'group' => 'booking', 'label' => 'Konfirmasi otomatis', 'value' => '0'],
        'seo.default_meta_description' => ['type' => 'text', 'group' => 'seo', 'label' => 'Meta description bawaan', 'value' => 'UNPAM HOSPITAL — informasi layanan, dokter, jadwal praktik, dan pendaftaran kunjungan rawat jalan (situs prototipe).'],
        'privacy.consent_version' => ['type' => 'string', 'group' => 'privasi', 'label' => 'Versi persetujuan privasi', 'value' => '1.0'],
    ];

    public function run(): void
    {
        foreach (self::DEFAULTS as $key => $setting) {
            WebsiteSetting::query()->firstOrCreate(['key' => $key], $setting);
        }
    }
}
```

- [ ] **Step 7: Jalankan tes — harus LULUS**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=SettingsSeederTest
```
Expected: PASS (19 kasus provider + 5 tes biasa = 24 tes).

- [ ] **Step 8: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint --test
git add database/migrations/2026_10_02_002200_create_website_settings_table.php app/Models/WebsiteSetting.php database/seeders/SettingsSeeder.php tests/Feature/Settings/SettingsSeederTest.php
git commit -m "feat(fase-1): tabel website_settings, model, dan SettingsSeeder 19 kunci baku"
```
Expected: Pint `PASS`.

#### Siklus B — `SettingsService` sisi baca (Review Focus #5)

- [ ] **Step 9: Tulis tes `tests/Feature/Settings/SettingsServiceTest.php`**

```php
<?php

namespace Tests\Feature\Settings;

use App\Models\WebsiteSetting;
use App\Services\Audit\AuditLogger;
use App\Services\Media\MediaService;
use App\Services\Settings\SettingsService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ImageFixtures;
use Tests\TestCase;

class SettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    private SettingsService $settings;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed(SettingsSeeder::class);

        $this->settings = app(SettingsService::class);
    }

    /**
     * Ubah baris langsung di database (melewati service) lalu buang cache.
     */
    private function rawUpdate(string $key, ?string $value): void
    {
        WebsiteSetting::query()->where('key', $key)->update(['value' => $value]);

        $this->settings->forget();
    }

    public function test_service_is_a_singleton(): void
    {
        $this->assertSame($this->settings, app(SettingsService::class));
    }

    public function test_typed_getters_read_the_seeded_defaults(): void
    {
        $this->assertSame('UNPAM HOSPITAL', $this->settings->string('site.name'));
        $this->assertSame('UNPAM HOSPITAL', $this->settings->get('site.name'));
        $this->assertSame(30, $this->settings->int('booking.window_days'));
        $this->assertSame(3, $this->settings->get('booking.max_active_per_patient'));
        $this->assertFalse($this->settings->bool('booking.auto_confirm'));
        $this->assertSame('(021) 0000-0000', $this->settings->string('contact.phone'));
    }

    public function test_unknown_keys_return_the_default(): void
    {
        $this->assertNull($this->settings->get('tidak.ada'));
        $this->assertSame('x', $this->settings->get('tidak.ada', 'x'));
        $this->assertSame('d', $this->settings->string('tidak.ada', 'd'));
        $this->assertSame(7, $this->settings->int('tidak.ada', 7));
        $this->assertTrue($this->settings->bool('tidak.ada', true));
    }

    public function test_empty_values_fall_back_to_the_default(): void
    {
        $this->assertSame(7, $this->settings->int('site.logo_media_id', 7));
        $this->assertSame('x', $this->settings->string('site.logo_media_id', 'x'));
        $this->assertNull($this->settings->get('site.logo_media_id'));

        $this->rawUpdate('site.tagline', '');

        $this->assertSame('Cadangan', $this->settings->string('site.tagline', 'Cadangan'));
    }

    public function test_bool_zero_is_false_even_when_the_default_is_true(): void
    {
        $this->assertFalse($this->settings->bool('booking.auto_confirm', true));

        $this->rawUpdate('booking.auto_confirm', '1');
        $this->assertTrue($this->settings->bool('booking.auto_confirm'));

        $this->rawUpdate('booking.auto_confirm', 'true');
        $this->assertTrue($this->settings->bool('booking.auto_confirm'));

        $this->rawUpdate('booking.auto_confirm', 'false');
        $this->assertFalse($this->settings->bool('booking.auto_confirm', true));
    }

    public function test_int_zero_is_a_real_value_not_the_default(): void
    {
        $this->rawUpdate('booking.online_cutoff_minutes', '0');

        $this->assertSame(0, $this->settings->int('booking.online_cutoff_minutes', 60));
    }

    public function test_json_type_is_decoded_and_invalid_json_falls_back_to_the_default(): void
    {
        WebsiteSetting::query()->create(['key' => 'uji.json', 'value' => '{"a":1,"b":[2,3]}', 'type' => 'json', 'group' => 'seo', 'label' => 'Uji JSON']);
        $this->settings->forget();

        $this->assertSame(['a' => 1, 'b' => [2, 3]], $this->settings->get('uji.json'));

        $this->rawUpdate('uji.json', '{rusak');

        $this->assertSame('cadangan', $this->settings->get('uji.json', 'cadangan'));
    }

    public function test_group_returns_items_in_id_order_with_typed_values(): void
    {
        $kontak = $this->settings->group('kontak');

        $this->assertSame(
            ['contact.address', 'contact.phone', 'contact.emergency_phone', 'contact.whatsapp', 'contact.email', 'contact.operational_hours', 'map.directions_url', 'map.embed_url'],
            $kontak->pluck('key')->all(),
        );
        $this->assertSame(['key', 'label', 'type', 'value'], array_keys($kontak->first()));
        $this->assertSame('Alamat', $kontak->first()['label']);
        $this->assertSame('text', $kontak->first()['type']);

        $booking = $this->settings->group('booking')->keyBy('key');
        $this->assertCount(6, $booking);
        $this->assertSame(30, $booking['booking.window_days']['value']);
        $this->assertFalse($booking['booking.auto_confirm']['value']);

        $this->assertNull($this->settings->group('identitas')->firstWhere('key', 'site.logo_media_id')['value']);
        $this->assertCount(3, $this->settings->group('identitas'));
        $this->assertCount(1, $this->settings->group('seo'));
        $this->assertCount(1, $this->settings->group('privasi'));
        $this->assertCount(0, $this->settings->group('tidak-ada'));
    }

    public function test_settings_are_cached_after_the_first_read(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->settings->get('site.name');
        $this->settings->int('booking.window_days');
        $this->settings->bool('booking.auto_confirm');

        $queries = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'website_settings'));

        $this->assertCount(1, $queries);
    }

    public function test_another_instance_reads_from_the_shared_cache_without_querying(): void
    {
        $this->settings->get('site.name');

        DB::flushQueryLog();
        DB::enableQueryLog();

        $other = new SettingsService(app(AuditLogger::class));

        $this->assertSame('UNPAM HOSPITAL', $other->string('site.name'));
        $this->assertCount(0, DB::getQueryLog());
    }

    public function test_logo_url_falls_back_to_the_placeholder_when_unset(): void
    {
        $this->assertSame(asset('images/placeholders/logo.svg'), $this->settings->logoUrl());
        $this->assertStringEndsWith('/images/placeholders/logo.svg', $this->settings->logoUrl());
    }

    public function test_logo_url_uses_the_logo_media_when_set(): void
    {
        $media = app(MediaService::class)->store(ImageFixtures::transparentPng(300, 100), 'logo', null, ['alt_text' => 'Logo']);
        $this->rawUpdate('site.logo_media_id', (string) $media->id);

        $this->assertSame($media->url, $this->settings->logoUrl());
        $this->assertStringContainsString('/storage/logo/', $this->settings->logoUrl());
    }

    public function test_logo_url_falls_back_when_the_media_was_deleted_or_is_private(): void
    {
        $media = app(MediaService::class)->store(ImageFixtures::transparentPng(300, 100), 'logo', null, ['alt_text' => 'Logo']);
        $this->rawUpdate('site.logo_media_id', (string) $media->id);

        $media->forceFill(['disk' => 'local'])->save();
        $this->assertSame(asset('images/placeholders/logo.svg'), $this->settings->logoUrl());

        $media->forceFill(['disk' => 'public'])->save();
        app(MediaService::class)->delete($media);
        $this->assertSame(asset('images/placeholders/logo.svg'), $this->settings->logoUrl());
    }

    public function test_whatsapp_url_uses_the_configured_number(): void
    {
        $this->assertSame('https://wa.me/6280000000000', $this->settings->whatsappUrl());
        $this->assertSame('https://wa.me/6280000000000?text=Halo%2C%20saya%20ingin%20bertanya', $this->settings->whatsappUrl('Halo, saya ingin bertanya'));
        $this->assertSame('https://wa.me/6280000000000', $this->settings->whatsappUrl('   '));
    }

    public function test_whatsapp_url_keeps_digits_only_and_is_null_when_empty(): void
    {
        $this->rawUpdate('contact.whatsapp', '+62 811-2222-3333');
        $this->assertSame('https://wa.me/6281122223333', $this->settings->whatsappUrl());

        $this->rawUpdate('contact.whatsapp', null);
        $this->assertNull($this->settings->whatsappUrl());
        $this->assertNull($this->settings->whatsappUrl('Halo'));
    }
}
```

- [ ] **Step 10: Jalankan tes — harus GAGAL**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=SettingsServiceTest
```
Expected: FAIL — semua tes `Illuminate\Contracts\Container\BindingResolutionException: Target class [App\Services\Settings\SettingsService] does not exist.` (galat di `setUp`).

- [ ] **Step 11: Implementasi `app/Services/Settings/SettingsService.php` (sisi baca) dan daftarkan singleton**

`app/Services/Settings/SettingsService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Models\Media;
use App\Models\WebsiteSetting;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class SettingsService
{
    public const CACHE_KEY = 'settings:all';

    /**
     * Memo per instance atas peta key => [type, value] (sumber: cache bersama).
     *
     * @var array<string, array{type: string, value: ?string}>|null
     */
    private ?array $memo = null;

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Nilai bertipe menurut kolom `type`; kunci tak dikenal, NULL, atau '' ⇒ $default.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $row = $this->all()[$key] ?? null;

        if ($row === null || $row['value'] === null || $row['value'] === '') {
            return $default;
        }

        return $this->cast($row['type'], $row['value'], $default);
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->get($key);

        return is_scalar($value) ? (string) $value : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->get($key);

        return is_numeric($value) ? (int) $value : $default;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->get($key);

        if ($value === null) {
            return $default;
        }

        return is_bool($value) ? $value : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Item grup (identitas|kontak|booking|seo|privasi) untuk layar pengaturan, urut id; `value` bertipe (null bila kosong).
     *
     * @return Collection<int, array{key: string, label: string, type: string, value: mixed}>
     */
    public function group(string $group): Collection
    {
        return WebsiteSetting::query()
            ->where('group', $group)
            ->orderBy('id')
            ->get()
            ->map(fn (WebsiteSetting $setting): array => [
                'key' => $setting->key,
                'label' => $setting->label,
                'type' => $setting->type,
                'value' => $setting->value === null || $setting->value === ''
                    ? null
                    : $this->cast($setting->type, $setting->value, null),
            ])
            ->values();
    }

    public function logoUrl(): string
    {
        $mediaId = $this->int('site.logo_media_id');

        if ($mediaId > 0) {
            $media = Media::query()->find($mediaId);

            if ($media !== null && $media->isPublic()) {
                return (string) $media->url;
            }
        }

        return asset('images/placeholders/logo.svg');
    }

    public function whatsappUrl(?string $text = null): ?string
    {
        $number = preg_replace('/\D+/', '', $this->string('contact.whatsapp')) ?? '';

        if ($number === '') {
            return null;
        }

        $url = 'https://wa.me/'.$number;
        $text = $text === null ? '' : trim($text);

        return $text === '' ? $url : $url.'?text='.rawurlencode($text);
    }

    public function forget(): void
    {
        $this->memo = null;
        Cache::forget(self::CACHE_KEY);
    }

    private function all(): array
    {
        return $this->memo ??= Cache::rememberForever(self::CACHE_KEY, static function (): array {
            $map = [];

            foreach (WebsiteSetting::query()->get(['key', 'type', 'value']) as $setting) {
                $map[$setting->key] = ['type' => $setting->type, 'value' => $setting->value];
            }

            return $map;
        });
    }

    private function cast(string $type, string $value, mixed $default): mixed
    {
        return match ($type) {
            'int' => (int) $value,
            'bool' => in_array(strtolower($value), ['1', 'true'], true),
            'json' => $this->decodeJson($value, $default),
            default => $value,
        };
    }

    private function decodeJson(string $value, mixed $default): mixed
    {
        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $default;
    }
}
```

`app/Providers/AppServiceProvider.php` — dua `Edit`:

Edit 1 — impor: ganti baris `use Illuminate\Support\ServiceProvider;` dengan:

```php
use App\Services\Settings\SettingsService;
use Illuminate\Support\ServiceProvider;
```

Edit 2 — singleton: ganti isi `register()`

```php
    public function register(): void
    {
        //
    }
```

dengan:

```php
    public function register(): void
    {
        $this->app->singleton(SettingsService::class);
    }
```

Lalu urutkan impor (Task 2/3 sudah menambah impor lain di berkas ini):

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint app\Providers\AppServiceProvider.php
```

- [ ] **Step 12: Jalankan tes — harus LULUS**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=SettingsServiceTest
```
Expected: PASS (15 tes). Review Focus #5: `int()` kosong ⇒ default, `bool('booking.auto_confirm', true)` = `false` untuk `'0'`, `logoUrl()` memakai placeholder bila media dihapus atau privat.

- [ ] **Step 13: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint --test
git add app/Services/Settings/SettingsService.php app/Providers/AppServiceProvider.php tests/Feature/Settings/SettingsServiceTest.php
git commit -m "feat(fase-1): SettingsService sisi baca dengan cache, tipe, logoUrl, dan whatsappUrl"
```
Expected: Pint `PASS`.

#### Siklus C — seeder membuang cache

- [ ] **Step 14: Tulis tes `tests/Feature/Settings/SettingsSeederCacheTest.php`**

```php
<?php

namespace Tests\Feature\Settings;

use App\Services\Settings\SettingsService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsSeederCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeding_after_a_warm_cache_makes_the_defaults_visible(): void
    {
        $settings = app(SettingsService::class);

        $this->assertNull($settings->get('site.name'));

        $this->seed(SettingsSeeder::class);

        $this->assertSame('UNPAM HOSPITAL', $settings->get('site.name'));
        $this->assertSame(30, $settings->int('booking.window_days'));
    }
}
```

- [ ] **Step 15: Jalankan tes — harus GAGAL**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=SettingsSeederCacheTest
```
Expected: FAIL — `Failed asserting that null is identical to 'UNPAM HOSPITAL'.` (cache peta kosong masih dipakai setelah seeding).

- [ ] **Step 16: Tambah `forget()` ke `database/seeders/SettingsSeeder.php`**

Edit 1 — impor: ganti baris `use Illuminate\Database\Seeder;` dengan:

```php
use App\Services\Settings\SettingsService;
use Illuminate\Database\Seeder;
```

Edit 2 — ganti tiga baris

```php
            WebsiteSetting::query()->firstOrCreate(['key' => $key], $setting);
        }
    }
```

dengan:

```php
            WebsiteSetting::query()->firstOrCreate(['key' => $key], $setting);
        }

        app(SettingsService::class)->forget();
    }
```

- [ ] **Step 17: Jalankan tes — harus LULUS**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test tests\Feature\Settings
```
Expected: PASS (`SettingsSeederCacheTest` 1 + `SettingsSeederTest` 24 + `SettingsServiceTest` 15 = 40 tes).

- [ ] **Step 18: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint --test
git add database/seeders/SettingsSeeder.php tests/Feature/Settings/SettingsSeederCacheTest.php
git commit -m "fix(fase-1): SettingsSeeder membuang cache pengaturan setelah seeding"
```
Expected: Pint `PASS`.

#### Siklus D — sisi tulis: `set()`, `setMany()`, audit, cache (SET-01)

- [ ] **Step 19: Tulis tes `tests/Feature/Settings/SettingsServiceWriteTest.php`**

```php
<?php

namespace Tests\Feature\Settings;

use App\Enums\ActorType;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Services\Settings\SettingsService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class SettingsServiceWriteTest extends TestCase
{
    use RefreshDatabase;

    private SettingsService $settings;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);

        $this->settings = app(SettingsService::class);
        $this->staff = User::factory()->staff()->create();
    }

    private function stored(string $key): ?string
    {
        return WebsiteSetting::query()->where('key', $key)->value('value');
    }

    public function test_set_saves_the_value_and_it_is_readable_immediately(): void
    {
        $this->assertSame('UNPAM HOSPITAL', $this->settings->get('site.name'));

        $this->settings->set('site.name', 'RS Pamulang', $this->staff);

        $this->assertSame('RS Pamulang', $this->settings->get('site.name'));
        $this->assertSame('RS Pamulang', $this->stored('site.name'));
        $this->assertSame($this->staff->id, WebsiteSetting::query()->where('key', 'site.name')->value('updated_by'));
    }

    public function test_set_writes_one_settings_updated_audit_row_with_old_and_new_values(): void
    {
        $this->settings->set('site.name', 'RS Pamulang', $this->staff);

        $log = AuditLog::query()->where('action', 'settings.updated')->sole();

        $this->assertSame(WebsiteSetting::class, $log->auditable_type);
        $this->assertNull($log->auditable_id);
        $this->assertSame(ActorType::Staff, $log->actor_type);
        $this->assertSame($this->staff->id, $log->user_id);
        $this->assertSame(['site.name' => 'UNPAM HOSPITAL'], $log->old_values);
        $this->assertSame(['site.name' => 'RS Pamulang'], $log->new_values);
        $this->assertSame('Pengaturan situs diubah: site.name.', $log->description);
    }

    public function test_set_many_audits_only_the_keys_that_changed_in_one_row(): void
    {
        $this->settings->setMany([
            'site.name' => 'UNPAM HOSPITAL',
            'site.tagline' => 'Sehat bersama',
            'booking.window_days' => 45,
        ], $this->staff);

        $log = AuditLog::query()->where('action', 'settings.updated')->sole();

        $this->assertSame([
            'site.tagline' => 'Layanan kesehatan yang ramah dan terpercaya',
            'booking.window_days' => '30',
        ], $log->old_values);
        $this->assertSame(['site.tagline' => 'Sehat bersama', 'booking.window_days' => '45'], $log->new_values);

        $this->assertNull(WebsiteSetting::query()->where('key', 'site.name')->value('updated_by'));
        $this->assertSame($this->staff->id, WebsiteSetting::query()->where('key', 'booking.window_days')->value('updated_by'));
    }

    public function test_nothing_changed_writes_no_audit_and_touches_nothing(): void
    {
        $this->settings->setMany(['site.name' => 'UNPAM HOSPITAL', 'booking.window_days' => 30, 'booking.auto_confirm' => false], $this->staff);

        $this->assertSame(0, AuditLog::query()->where('action', 'settings.updated')->count());
        $this->assertNull(WebsiteSetting::query()->where('key', 'site.name')->value('updated_by'));
    }

    public function test_empty_set_many_is_a_no_op(): void
    {
        $this->settings->setMany([], $this->staff);

        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_set_without_a_user_audits_as_system(): void
    {
        $this->settings->set('site.tagline', 'Baru');

        $log = AuditLog::query()->where('action', 'settings.updated')->sole();

        $this->assertSame(ActorType::System, $log->actor_type);
        $this->assertNull($log->user_id);
        $this->assertNull(WebsiteSetting::query()->where('key', 'site.tagline')->value('updated_by'));
    }

    public function test_unknown_key_throws_and_saves_nothing_from_the_same_call(): void
    {
        $thrown = null;

        try {
            $this->settings->setMany(['site.name' => 'Tidak boleh tersimpan', 'tidak.ada' => 'x'], $this->staff);
        } catch (InvalidArgumentException $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown);
        $this->assertSame('Kunci pengaturan tidak dikenal: tidak.ada.', $thrown->getMessage());
        $this->assertSame('UNPAM HOSPITAL', $this->stored('site.name'));
        $this->assertSame(0, AuditLog::query()->where('action', 'settings.updated')->count());
        $this->assertNull(WebsiteSetting::query()->where('key', 'tidak.ada')->first());
    }

    public function test_values_are_serialized_by_type(): void
    {
        WebsiteSetting::query()->create(['key' => 'uji.json', 'value' => null, 'type' => 'json', 'group' => 'seo', 'label' => 'Uji JSON']);

        $this->settings->setMany([
            'booking.auto_confirm' => true,
            'booking.window_days' => '45',
            'booking.online_cutoff_minutes' => 90,
            'site.tagline' => '  Sehat bersama  ',
            'uji.json' => ['a' => 1, 'teks' => 'é/ü'],
        ]);

        $this->assertSame('1', $this->stored('booking.auto_confirm'));
        $this->assertSame('45', $this->stored('booking.window_days'));
        $this->assertSame('90', $this->stored('booking.online_cutoff_minutes'));
        $this->assertSame('Sehat bersama', $this->stored('site.tagline'));
        $this->assertSame('{"a":1,"teks":"é/ü"}', $this->stored('uji.json'));

        $this->assertTrue($this->settings->bool('booking.auto_confirm'));
        $this->assertSame(['a' => 1, 'teks' => 'é/ü'], $this->settings->get('uji.json'));

        $this->settings->set('booking.auto_confirm', false);
        $this->assertSame('0', $this->stored('booking.auto_confirm'));
        $this->assertFalse($this->settings->bool('booking.auto_confirm', true));
    }

    public function test_null_and_empty_strings_are_stored_as_null(): void
    {
        $this->settings->setMany(['site.tagline' => '', 'contact.emergency_phone' => null, 'site.logo_media_id' => null]);

        $this->assertNull($this->stored('site.tagline'));
        $this->assertNull($this->stored('contact.emergency_phone'));
        $this->assertSame('Cadangan', $this->settings->string('site.tagline', 'Cadangan'));
    }

    /**
     * @return array<string, array{0: string, 1: mixed, 2: string}>
     */
    public static function invalidValues(): array
    {
        return [
            'int bukan angka' => ['booking.window_days', 'abc', 'Nilai pengaturan booking.window_days harus berupa angka bulat.'],
            'int berupa larik' => ['booking.window_days', [1], 'Nilai pengaturan booking.window_days harus berupa angka bulat.'],
            'string berupa larik' => ['site.name', ['x'], 'Nilai pengaturan site.name harus berupa teks.'],
            'json berupa teks' => ['uji.json', '{"a":1}', 'Nilai pengaturan uji.json harus berupa larik.'],
        ];
    }

    #[DataProvider('invalidValues')]
    public function test_values_of_the_wrong_type_are_rejected_without_saving(string $key, mixed $value, string $message): void
    {
        WebsiteSetting::query()->create(['key' => 'uji.json', 'value' => null, 'type' => 'json', 'group' => 'seo', 'label' => 'Uji JSON']);
        $before = $this->stored($key);

        $thrown = null;

        try {
            $this->settings->setMany([$key => $value, 'site.tagline' => 'Tidak boleh tersimpan']);
        } catch (InvalidArgumentException $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown);
        $this->assertSame($message, $thrown->getMessage());
        $this->assertSame($before, $this->stored($key));
        $this->assertSame('Layanan kesehatan yang ramah dan terpercaya', $this->stored('site.tagline'));
    }

    public function test_cache_is_forgotten_right_away_outside_a_transaction(): void
    {
        $this->settings->get('site.name');
        $this->assertTrue(Cache::has(SettingsService::CACHE_KEY));

        $this->settings->set('site.name', 'RS Pamulang');

        $this->assertFalse(Cache::has(SettingsService::CACHE_KEY));
    }

    public function test_cache_is_forgotten_only_after_the_surrounding_transaction_commits(): void
    {
        $this->settings->get('site.name');

        DB::transaction(function (): void {
            $this->settings->set('site.name', 'RS Pamulang');

            $this->assertTrue(Cache::has(SettingsService::CACHE_KEY));
        });

        $this->assertFalse(Cache::has(SettingsService::CACHE_KEY));
        $this->assertSame('RS Pamulang', $this->settings->get('site.name'));
    }

    public function test_cache_and_database_are_untouched_when_the_surrounding_transaction_rolls_back(): void
    {
        $this->settings->get('site.name');

        $thrown = null;

        try {
            DB::transaction(function (): void {
                $this->settings->set('site.name', 'RS Pamulang', $this->staff);

                throw new RuntimeException('simulasi galat');
            });
        } catch (RuntimeException $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown);
        $this->assertTrue(Cache::has(SettingsService::CACHE_KEY));
        $this->assertSame('UNPAM HOSPITAL', $this->stored('site.name'));
        $this->assertSame('UNPAM HOSPITAL', $this->settings->get('site.name'));
        $this->assertSame(0, AuditLog::query()->where('action', 'settings.updated')->count());
    }
}
```

- [ ] **Step 20: Jalankan tes — harus GAGAL**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=SettingsServiceWriteTest
```
Expected: FAIL — `Error: Call to undefined method App\Services\Settings\SettingsService::set()` / `::setMany()` pada semua tes.

- [ ] **Step 21: Implementasi `set()`, `setMany()`, dan serialisasi di `app/Services/Settings/SettingsService.php`**

Edit 1 — impor `User`: ganti baris `use App\Models\WebsiteSetting;` dengan:

```php
use App\Models\User;
use App\Models\WebsiteSetting;
```

Edit 2 — impor `Actor`: ganti baris `use App\Services\Audit\AuditLogger;` dengan:

```php
use App\Services\Audit\AuditLogger;
use App\Support\Actor;
```

Edit 3 — impor `DB` dan `InvalidArgumentException`: ganti baris `use Illuminate\Support\Facades\Cache;` dengan:

```php
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
```

Edit 4 — metode tulis: ganti awalan baris `    public function logoUrl(` (satu-satunya kemunculan) dengan blok berikut diikuti awalan yang sama:

```php
    public function set(string $key, mixed $value, ?User $by = null): void
    {
        $this->setMany([$key => $value], $by);
    }

    /**
     * Simpan beberapa pengaturan dalam satu transaksi. Kunci tak dikenal ⇒ InvalidArgumentException
     * (tidak ada yang tersimpan). Hanya kunci yang nilainya berubah ditulis dan dicatat dalam satu
     * baris audit `settings.updated`; cache dibuang setelah commit.
     *
     * @param  array<string, mixed>  $values
     */
    public function setMany(array $values, ?User $by = null): void
    {
        if ($values === []) {
            return;
        }

        DB::transaction(function () use ($values, $by): void {
            $keys = array_map('strval', array_keys($values));
            $rows = WebsiteSetting::query()->whereIn('key', $keys)->lockForUpdate()->get()->keyBy('key');
            $unknown = array_values(array_diff($keys, $rows->keys()->all()));

            if ($unknown !== []) {
                throw new InvalidArgumentException('Kunci pengaturan tidak dikenal: '.implode(', ', $unknown).'.');
            }

            $changes = [];
            $old = [];
            $new = [];

            foreach ($values as $key => $value) {
                $setting = $rows->get((string) $key);
                $serialized = $this->serialize($setting->key, $setting->type, $value);
                $current = $setting->value === '' ? null : $setting->value;

                if ($serialized === $current) {
                    continue;
                }

                $changes[] = [$setting, $serialized];
                $old[$setting->key] = $current;
                $new[$setting->key] = $serialized;
            }

            if ($changes === []) {
                return;
            }

            foreach ($changes as [$setting, $serialized]) {
                $setting->fill(['value' => $serialized, 'updated_by' => $by?->getKey()])->save();
            }

            $this->audit->log(
                'settings.updated',
                new WebsiteSetting,
                $old,
                $new,
                'Pengaturan situs diubah: '.implode(', ', array_keys($new)).'.',
                $by !== null ? Actor::staff($by) : null,
            );

            DB::afterCommit(fn () => $this->forget());
        });
    }

    public function logoUrl(
```

Edit 5 — serialisasi: ganti awalan baris `    private function all(` (satu-satunya kemunculan) dengan blok berikut diikuti awalan yang sama:

```php
    /**
     * Nilai ⇒ string tersimpan menurut tipe; null dan '' ⇒ NULL. Tipe tidak cocok ⇒ InvalidArgumentException.
     */
    private function serialize(string $key, string $type, mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $serialized = match ($type) {
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0',
            'int' => is_numeric($value)
                ? (string) (int) $value
                : throw new InvalidArgumentException("Nilai pengaturan {$key} harus berupa angka bulat."),
            'json' => is_array($value)
                ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
                : throw new InvalidArgumentException("Nilai pengaturan {$key} harus berupa larik."),
            default => is_scalar($value)
                ? trim((string) $value)
                : throw new InvalidArgumentException("Nilai pengaturan {$key} harus berupa teks."),
        };

        return $serialized === '' ? null : $serialized;
    }

    private function all(
```

- [ ] **Step 22: Jalankan tes — harus LULUS**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test tests\Feature\Settings
```
Expected: PASS (`SettingsServiceWriteTest`: 4 kasus `invalidValues` + 12 tes biasa = 16 tes; ditambah 40 tes siklus sebelumnya = 56 tes). SET-01: nilai baru terbaca segera (cache dibuang) dan tercatat di audit; hanya kunci berubah yang diaudit.

- [ ] **Step 23: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint --test
git add app/Services/Settings/SettingsService.php tests/Feature/Settings/SettingsServiceWriteTest.php
git commit -m "feat(fase-1): SettingsService set/setMany dengan audit kunci berubah dan cache setelah commit"
```
Expected: Pint `PASS`.

#### Siklus E — kait `DatabaseSeeder`

- [ ] **Step 24: Tulis tes `tests/Feature/Settings/DatabaseSeederWiringTest.php`**

```php
<?php

namespace Tests\Feature\Settings;

use Tests\TestCase;

class DatabaseSeederWiringTest extends TestCase
{
    public function test_database_seeder_runs_the_role_and_settings_seeders_in_that_order(): void
    {
        $source = (string) file_get_contents(database_path('seeders/DatabaseSeeder.php'));

        $this->assertStringContainsString('RolePermissionSeeder::class', $source);
        $this->assertStringContainsString('SettingsSeeder::class', $source);
        $this->assertLessThan(
            strpos($source, 'SettingsSeeder::class'),
            strpos($source, 'RolePermissionSeeder::class'),
        );
    }
}
```

- [ ] **Step 25: Jalankan tes — harus GAGAL**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=DatabaseSeederWiringTest
```
Expected: FAIL — `Failed asserting that '...DatabaseSeeder...' contains "SettingsSeeder::class".`

- [ ] **Step 26: Hubungkan `SettingsSeeder` di `database/seeders/DatabaseSeeder.php`**

Baca berkas (hasil Task 3), lalu pastikan `run()` memanggil kedua seeder inti berurutan. Isi akhir berkas:

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seeder inti (semua lingkungan): peran/izin lalu pengaturan. Halaman ditambahkan di fase 4a.
     * DemoSeeder hanya dijalankan di lingkungan local/testing (blueprint bagian 13).
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            SettingsSeeder::class,
        ]);
    }
}
```

(Bila hasil Task 3 memuat tambahan lain di `run()`, pertahankan dan hanya pastikan urutan `RolePermissionSeeder::class` lalu `SettingsSeeder::class`.)

- [ ] **Step 27: Jalankan tes — harus LULUS**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=DatabaseSeederWiringTest
```
Expected: PASS (1 tes).

- [ ] **Step 28: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint --test
git add database/seeders/DatabaseSeeder.php tests/Feature/Settings/DatabaseSeederWiringTest.php
git commit -m "feat(fase-1): DatabaseSeeder menjalankan RolePermissionSeeder dan SettingsSeeder"
```
Expected: Pint `PASS`.

- [ ] **Step 29: Verifikasi gerbang Task 7**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test
& $PHP vendor\bin\pint --test
& $PHP artisan migrate:fresh --seed --env=testing
git status --short
```
Expected: seluruh tes hijau; Pint `PASS`; `migrate:fresh --seed` pada database uji selesai tanpa galat (`SettingsSeeder` dan `RolePermissionSeeder` berjalan); `git status` kosong.

---

### Task 8: Pembersihan media — `MediaService::prune()` + `media:prune`

**Files:**
- Create: `app/Services/Media/MediaPruneReport.php`, `app/Console/Commands/PruneMedia.php`
- Modify: `app/Services/Media/MediaService.php`
- Test: `tests/Feature/Media/PruneMediaTest.php`, `tests/Feature/Media/PruneMediaCommandTest.php`

(Kontrak hanya menyebut `PruneMediaTest`; tes perintah Artisan dipisah ke `PruneMediaCommandTest` agar setiap siklus TDD berdiri sendiri.)

**Interfaces:**
- Consumes: `MediaService` (Task 5), `SettingsService::int('site.logo_media_id')` (Task 7), `AuditLogger::log(...)` dan `Actor::system()` (Task 4).
- Produces: `final readonly class MediaPruneReport { __construct(public int $orphanRows, public int $orphanFiles, public int $bytes, public bool $dryRun, public array $items = []) }` (`$items` = `list<string>`); `MediaService::prune(bool $dryRun = false): MediaPruneReport` (F1-R12; nyata ⇒ satu audit `media.pruned` pelaku sistem dengan jumlah); perintah `media:prune {--dry-run}` (keluaran Indonesia: jumlah baris yatim, berkas yatim, total ukuran; `--dry-run` mencetak daftar `items` dan kalimat "Mode uji: tidak ada yang dihapus."; exit 0).
- Perilaku (F1-R12 + keputusan penulis rencana): (a) baris `model_id` NULL berumur **> 24 jam** kecuali baris yang id-nya = `site.logo_media_id`; (b) berkas di folder koleksi (`photo`, `cover`, …) disk `public` yang tidak tercatat di `media` (berkas utama maupun `thumb_path`), bukan berkas tersembunyi (diawali titik), dan `lastModified` **lebih tua dari 1 jam**; berkas di luar folder koleksi tidak disentuh; (c) butir (c) 09 (pemilik hilang) ditunda ke fase 2; (d) `prune()` nyata **selalu** menulis satu audit `media.pruned` (juga bila jumlahnya nol) sehingga setiap eksekusi terlacak; (e) `bytes` = jumlah `size_bytes` baris yatim + ukuran thumbnail fisiknya + ukuran berkas yatim; (f) `MediaService` mengambil `SettingsService` secara malas lewat `app()` agar signature constructor `(AuditLogger $audit)` dari Task 5 tidak berubah.
- Verifikasi API terhadap `vendor/` (Laravel 12.69): `Storage::disk()->allFiles($dir)`, `lastModified()`, `size()`, `delete(array)`; `Illuminate\Support\Number::fileSize()` (butuh ekstensi `intl`, aktif di XAMPP); `PendingCommand::expectsOutputToContain()`/`doesntExpectOutputToContain()`; perintah di `app/Console/Commands` ditemukan otomatis oleh `bootstrap/app.php`.

**Test IDs:** `MED-07`.

- [ ] **Step 1: Verifikasi prasyarat**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git branch --show-current
Test-Path app\Services\Media\MediaService.php, app\Services\Settings\SettingsService.php
& $PHP artisan test
```
Expected: `fase-1-fondasi`; dua baris `True`; seluruh tes (Task 1–7) hijau.

#### Siklus A — `MediaPruneReport` dan `MediaService::prune()` (MED-07)

- [ ] **Step 2: Tulis tes `tests/Feature/Media/PruneMediaTest.php`**

```php
<?php

namespace Tests\Feature\Media;

use App\Enums\ActorType;
use App\Models\AuditLog;
use App\Models\Media;
use App\Models\User;
use App\Services\Media\MediaPruneReport;
use App\Services\Media\MediaService;
use App\Services\Settings\SettingsService;
use Database\Seeders\SettingsSeeder;
use Error;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ImageFixtures;
use Tests\TestCase;

class PruneMediaTest extends TestCase
{
    use RefreshDatabase;

    private MediaService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed(SettingsSeeder::class);

        $this->service = app(MediaService::class);
    }

    private function store(string $collection, ?User $owner): Media
    {
        return $this->service->store(ImageFixtures::jpeg(900, 600), $collection, $owner, ['alt_text' => 'Contoh']);
    }

    private function age(Media $media, int $hours): void
    {
        DB::table('media')->where('id', $media->id)->update(['created_at' => now()->subHours($hours)]);
    }

    /**
     * Berkas 10 byte tanpa baris `media`, dengan umur diatur lewat touch().
     */
    private function stray(string $path, int $ageMinutes): void
    {
        $disk = Storage::disk('public');
        $disk->put($path, str_repeat('x', 10));
        touch($disk->path($path), time() - $ageMinutes * 60);
    }

    private function assertFilesExist(Media $media): void
    {
        Storage::disk('public')->assertExists($media->directory.'/'.$media->file_name);
        Storage::disk('public')->assertExists($media->thumb_path);
    }

    private function assertFilesMissing(Media $media): void
    {
        Storage::disk('public')->assertMissing($media->directory.'/'.$media->file_name);
        Storage::disk('public')->assertMissing($media->thumb_path);
    }

    /**
     * Skenario MED-07: satu baris terpakai, satu yatim lama, satu yatim baru, logo situs yatim lama,
     * dua berkas yatim lama, satu berkas yatim baru, satu berkas di luar folder koleksi, satu berkas tersembunyi.
     *
     * @return array{owned: Media, stale: Media, fresh: Media, logo: Media}
     */
    private function scenario(): array
    {
        $owner = User::factory()->staff()->create();

        $owned = $this->store('photo', $owner);
        $this->age($owned, 72);

        $stale = $this->store('editor', null);
        $this->age($stale, 25);

        $fresh = $this->store('editor', null);
        $this->age($fresh, 23);

        $logo = $this->store('logo', null);
        $this->age($logo, 72);
        app(SettingsService::class)->set('site.logo_media_id', $logo->id);

        $this->stray('editor/2026/10/stray-old.jpg', 120);
        $this->stray('photo/2026/10/thumbs/stray-thumb-old.jpg', 180);
        $this->stray('editor/2026/10/stray-new.jpg', 10);
        $this->stray('other/2026/10/not-ours.jpg', 600);
        $this->stray('editor/.gitkeep', 600);

        return compact('owned', 'stale', 'fresh', 'logo');
    }

    private function expectedBytes(Media $stale): int
    {
        return $stale->size_bytes + Storage::disk('public')->size($stale->thumb_path) + 10 + 10;
    }

    public function test_report_is_an_immutable_value_object_with_empty_items_by_default(): void
    {
        $report = new MediaPruneReport(1, 2, 3, true);

        $this->assertSame([1, 2, 3, true, []], [$report->orphanRows, $report->orphanFiles, $report->bytes, $report->dryRun, $report->items]);

        $this->expectException(Error::class);

        $report->orphanRows = 9;
    }

    public function test_dry_run_reports_orphans_but_deletes_nothing(): void
    {
        $media = $this->scenario();
        $expectedBytes = $this->expectedBytes($media['stale']);

        $report = $this->service->prune(true);

        $this->assertTrue($report->dryRun);
        $this->assertSame(1, $report->orphanRows);
        $this->assertSame(2, $report->orphanFiles);
        $this->assertSame($expectedBytes, $report->bytes);

        $this->assertCount(3, $report->items);
        $listing = implode("\n", $report->items);
        $this->assertStringContainsString('#'.$media['stale']->id, $listing);
        $this->assertStringContainsString('editor/2026/10/stray-old.jpg', $listing);
        $this->assertStringContainsString('photo/2026/10/thumbs/stray-thumb-old.jpg', $listing);

        $this->assertSame(4, Media::query()->count());
        foreach ($media as $row) {
            $this->assertFilesExist($row);
        }
        foreach (['editor/2026/10/stray-old.jpg', 'photo/2026/10/thumbs/stray-thumb-old.jpg', 'editor/2026/10/stray-new.jpg', 'other/2026/10/not-ours.jpg', 'editor/.gitkeep'] as $path) {
            Storage::disk('public')->assertExists($path);
        }
        $this->assertSame(0, AuditLog::query()->where('action', 'media.pruned')->count());
    }

    public function test_real_run_deletes_only_orphans_and_writes_a_system_audit(): void
    {
        $media = $this->scenario();
        $expectedBytes = $this->expectedBytes($media['stale']);

        $report = $this->service->prune();

        $this->assertFalse($report->dryRun);
        $this->assertSame(1, $report->orphanRows);
        $this->assertSame(2, $report->orphanFiles);
        $this->assertSame($expectedBytes, $report->bytes);

        $this->assertNull(Media::query()->find($media['stale']->id));
        $this->assertFilesMissing($media['stale']);
        Storage::disk('public')->assertMissing('editor/2026/10/stray-old.jpg');
        Storage::disk('public')->assertMissing('photo/2026/10/thumbs/stray-thumb-old.jpg');

        foreach (['owned', 'fresh', 'logo'] as $name) {
            $this->assertNotNull(Media::query()->find($media[$name]->id), $name);
            $this->assertFilesExist($media[$name]);
        }
        Storage::disk('public')->assertExists('editor/2026/10/stray-new.jpg');
        Storage::disk('public')->assertExists('other/2026/10/not-ours.jpg');
        Storage::disk('public')->assertExists('editor/.gitkeep');

        $log = AuditLog::query()->where('action', 'media.pruned')->sole();
        $this->assertSame(ActorType::System, $log->actor_type);
        $this->assertNull($log->user_id);
        $this->assertSame(Media::class, $log->auditable_type);
        $this->assertNull($log->auditable_id);
        $this->assertSame(['orphan_rows' => 1, 'orphan_files' => 2, 'bytes' => $expectedBytes], $log->new_values);
    }

    public function test_a_second_run_finds_nothing_but_is_still_audited(): void
    {
        $this->scenario();
        $this->service->prune();

        $report = $this->service->prune();

        $this->assertSame([0, 0, 0, []], [$report->orphanRows, $report->orphanFiles, $report->bytes, $report->items]);
        $this->assertSame(2, AuditLog::query()->where('action', 'media.pruned')->count());
    }

    public function test_the_site_logo_is_only_protected_while_it_is_the_configured_logo(): void
    {
        $media = $this->scenario();
        app(SettingsService::class)->set('site.logo_media_id', null);

        $report = $this->service->prune();

        $this->assertSame(2, $report->orphanRows);
        $this->assertNull(Media::query()->find($media['logo']->id));
        $this->assertFilesMissing($media['logo']);
    }

    public function test_an_old_orphan_row_whose_files_are_already_gone_is_still_removed(): void
    {
        $stale = $this->store('editor', null);
        $this->age($stale, 30);
        Storage::disk('public')->delete([$stale->directory.'/'.$stale->file_name, $stale->thumb_path]);

        $report = $this->service->prune();

        $this->assertSame(1, $report->orphanRows);
        $this->assertSame(0, $report->orphanFiles);
        $this->assertSame($stale->size_bytes, $report->bytes);
        $this->assertNull(Media::query()->find($stale->id));
    }

    public function test_file_age_threshold_is_one_hour(): void
    {
        $this->stray('editor/2026/10/almost.jpg', 59);
        $this->stray('editor/2026/10/over.jpg', 61);

        $report = $this->service->prune();

        $this->assertSame(1, $report->orphanFiles);
        Storage::disk('public')->assertExists('editor/2026/10/almost.jpg');
        Storage::disk('public')->assertMissing('editor/2026/10/over.jpg');
    }

    public function test_row_age_threshold_is_24_hours(): void
    {
        $almost = $this->store('editor', null);
        $this->age($almost, 23);
        $over = $this->store('editor', null);
        $this->age($over, 25);

        $this->service->prune();

        $this->assertNotNull(Media::query()->find($almost->id));
        $this->assertNull(Media::query()->find($over->id));
    }

    public function test_pruning_an_empty_system_reports_zeroes(): void
    {
        $report = $this->service->prune(true);

        $this->assertSame([0, 0, 0, true, []], [$report->orphanRows, $report->orphanFiles, $report->bytes, $report->dryRun, $report->items]);
    }
}
```

- [ ] **Step 3: Jalankan tes — harus GAGAL**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=PruneMediaTest
```
Expected: FAIL — `Error: Class "App\Services\Media\MediaPruneReport" not found` (tes laporan) dan `Error: Call to undefined method App\Services\Media\MediaService::prune()` (tes lain).

- [ ] **Step 4: Tulis `app/Services/Media/MediaPruneReport.php`**

```php
<?php

declare(strict_types=1);

namespace App\Services\Media;

final readonly class MediaPruneReport
{
    /**
     * @param  list<string>  $items  daftar yang dicetak `media:prune --dry-run`
     */
    public function __construct(
        public int $orphanRows,
        public int $orphanFiles,
        public int $bytes,
        public bool $dryRun,
        public array $items = [],
    ) {}
}
```

- [ ] **Step 5: Tambah `prune()` ke `app/Services/Media/MediaService.php`**

Edit 1 — impor `SettingsService`: ganti baris `use App\Services\Audit\AuditLogger;` dengan:

```php
use App\Services\Audit\AuditLogger;
use App\Services\Settings\SettingsService;
```

Edit 2 — impor `Number`: ganti baris `use Illuminate\Support\Str;` dengan:

```php
use Illuminate\Support\Number;
use Illuminate\Support\Str;
```

Edit 3 — konstanta: ganti awalan baris `    private const EXTENSIONS = [` (satu-satunya kemunculan) dengan blok berikut diikuti awalan yang sama:

```php
    private const ORPHAN_ROW_HOURS = 24;

    private const ORPHAN_FILE_MINUTES = 60;

    private const EXTENSIONS = [
```

Edit 4 — metode publik: ganti awalan baris `    private function createMedia(` (satu-satunya kemunculan) dengan blok berikut diikuti awalan yang sama:

```php
    /**
     * Bersihkan media yatim (F1-R12): (a) baris tanpa pemilik berumur > 24 jam kecuali logo situs,
     * (b) berkas di folder koleksi disk public yang tidak tercatat dan berumur > 1 jam.
     * Pada $dryRun hanya menghitung dan menyusun daftar; eksekusi nyata menulis audit `media.pruned`.
     */
    public function prune(bool $dryRun = false): MediaPruneReport
    {
        $logoId = $this->logoMediaId();
        $rows = Media::query()
            ->orphaned(self::ORPHAN_ROW_HOURS)
            ->when($logoId > 0, fn (Builder $query): Builder => $query->whereKeyNot($logoId))
            ->orderBy('id')
            ->get();
        $files = $this->orphanFiles();

        $bytes = 0;
        $items = [];

        foreach ($rows as $media) {
            $bytes += $media->size_bytes + $this->thumbnailSize($media);
            $items[] = "Baris media #{$media->id}: {$media->directory}/{$media->file_name} ({$media->human_size})";
        }

        foreach ($files as $file) {
            $bytes += $file['size'];
            $items[] = "Berkas yatim: {$file['path']} (".Number::fileSize($file['size']).')';
        }

        $report = new MediaPruneReport($rows->count(), count($files), $bytes, $dryRun, $items);

        if ($dryRun) {
            return $report;
        }

        foreach ($rows as $media) {
            $this->purge($media);
        }

        Storage::disk('public')->delete(array_column($files, 'path'));

        $this->audit->log(
            'media.pruned',
            new Media,
            null,
            ['orphan_rows' => $report->orphanRows, 'orphan_files' => $report->orphanFiles, 'bytes' => $report->bytes],
            "Pembersihan media: {$report->orphanRows} baris dan {$report->orphanFiles} berkas yatim dihapus.",
            Actor::system(),
        );

        return $report;
    }

    private function createMedia(
```

Edit 5 — pembantu privat: ganti awalan baris `    private function encode(` (satu-satunya kemunculan) dengan blok berikut diikuti awalan yang sama:

```php
    /**
     * Berkas di folder koleksi disk public yang tidak tercatat di `media` (berkas utama maupun thumbnail),
     * bukan berkas tersembunyi, dan berumur lebih dari 1 jam (menghindari balapan dengan unggahan berjalan).
     *
     * @return list<array{path: string, size: int}>
     */
    private function orphanFiles(): array
    {
        $tracked = [];

        foreach (Media::query()->where('disk', 'public')->get(['directory', 'file_name', 'thumb_path']) as $media) {
            $tracked[$media->directory.'/'.$media->file_name] = true;

            if ($media->thumb_path !== null) {
                $tracked[$media->thumb_path] = true;
            }
        }

        $disk = Storage::disk('public');
        $threshold = now()->subMinutes(self::ORPHAN_FILE_MINUTES)->getTimestamp();
        $orphans = [];

        foreach (self::COLLECTIONS as $collection => $config) {
            if ($config['disk'] !== 'public') {
                continue;
            }

            foreach ($disk->allFiles($collection) as $path) {
                if (isset($tracked[$path]) || str_starts_with(basename($path), '.') || $disk->lastModified($path) >= $threshold) {
                    continue;
                }

                $orphans[] = ['path' => $path, 'size' => $disk->size($path)];
            }
        }

        return $orphans;
    }

    private function logoMediaId(): int
    {
        return app(SettingsService::class)->int('site.logo_media_id');
    }

    private function thumbnailSize(Media $media): int
    {
        if ($media->thumb_path === null) {
            return 0;
        }

        $disk = Storage::disk($media->disk);

        return $disk->exists($media->thumb_path) ? $disk->size($media->thumb_path) : 0;
    }

    private function encode(
```

- [ ] **Step 6: Jalankan tes — harus LULUS**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="PruneMediaTest|MediaService"
```
Expected: PASS (`PruneMediaTest` 9 tes; tes `MediaService*` Task 5 tetap hijau). MED-07: dry-run hanya melaporkan; eksekusi menghapus baris yatim > 24 jam (kecuali logo situs) dan berkas yatim > 1 jam; media terpakai, berkas baru < 1 jam, berkas di luar folder koleksi, dan berkas tersembunyi aman.

- [ ] **Step 7: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint --test
git add app/Services/Media/MediaPruneReport.php app/Services/Media/MediaService.php tests/Feature/Media/PruneMediaTest.php
git commit -m "feat(fase-1): MediaService::prune untuk baris dan berkas media yatim dengan mode uji"
```
Expected: Pint `PASS`.

#### Siklus B — perintah `media:prune`

- [ ] **Step 8: Tulis tes `tests/Feature/Media/PruneMediaCommandTest.php`**

```php
<?php

namespace Tests\Feature\Media;

use App\Models\AuditLog;
use App\Models\Media;
use App\Services\Media\MediaService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ImageFixtures;
use Tests\TestCase;

class PruneMediaCommandTest extends TestCase
{
    use RefreshDatabase;

    private Media $stale;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed(SettingsSeeder::class);

        $this->stale = app(MediaService::class)->store(ImageFixtures::jpeg(900, 600), 'editor', null, ['alt_text' => 'Contoh']);
        DB::table('media')->where('id', $this->stale->id)->update(['created_at' => now()->subHours(25)]);

        $disk = Storage::disk('public');
        $disk->put('editor/2026/10/stray-old.jpg', str_repeat('x', 10));
        touch($disk->path('editor/2026/10/stray-old.jpg'), time() - 7200);
    }

    public function test_dry_run_prints_the_counts_the_list_and_the_test_mode_sentence_without_deleting(): void
    {
        $this->artisan('media:prune', ['--dry-run' => true])
            ->expectsOutputToContain('Baris media yatim: 1')
            ->expectsOutputToContain('Berkas yatim: 1')
            ->expectsOutputToContain('Total ukuran: ')
            ->expectsOutputToContain('#'.$this->stale->id)
            ->expectsOutputToContain('editor/2026/10/stray-old.jpg')
            ->expectsOutputToContain('Mode uji: tidak ada yang dihapus.')
            ->assertExitCode(0);

        $this->assertNotNull(Media::query()->find($this->stale->id));
        Storage::disk('public')->assertExists('editor/2026/10/stray-old.jpg');
        $this->assertSame(0, AuditLog::query()->where('action', 'media.pruned')->count());
    }

    public function test_real_run_prints_the_counts_deletes_the_orphans_and_exits_zero(): void
    {
        $this->artisan('media:prune')
            ->expectsOutputToContain('Baris media yatim: 1')
            ->expectsOutputToContain('Berkas yatim: 1')
            ->expectsOutputToContain('Total ukuran: ')
            ->expectsOutputToContain('Pembersihan selesai.')
            ->doesntExpectOutputToContain('Mode uji')
            ->assertExitCode(0);

        $this->assertNull(Media::query()->find($this->stale->id));
        Storage::disk('public')->assertMissing('editor/2026/10/stray-old.jpg');
        $this->assertSame(1, AuditLog::query()->where('action', 'media.pruned')->count());
    }

    public function test_nothing_to_prune_still_exits_zero(): void
    {
        $this->artisan('media:prune')->run();

        $this->artisan('media:prune')
            ->expectsOutputToContain('Baris media yatim: 0')
            ->expectsOutputToContain('Berkas yatim: 0')
            ->assertExitCode(0);
    }
}
```

- [ ] **Step 9: Jalankan tes — harus GAGAL**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=PruneMediaCommandTest
```
Expected: FAIL — `Symfony\Component\Console\Exception\CommandNotFoundException: Command "media:prune" is not defined.`

- [ ] **Step 10: Tulis `app/Console/Commands/PruneMedia.php`**

```php
<?php

namespace App\Console\Commands;

use App\Services\Media\MediaService;
use Illuminate\Console\Command;
use Illuminate\Support\Number;

class PruneMedia extends Command
{
    protected $signature = 'media:prune {--dry-run : Hanya melaporkan, tidak ada yang dihapus}';

    protected $description = 'Hapus media yatim: baris tanpa pemilik lebih dari 24 jam dan berkas tak tercatat lebih dari 1 jam';

    public function handle(MediaService $media): int
    {
        $report = $media->prune((bool) $this->option('dry-run'));

        $this->line("Baris media yatim: {$report->orphanRows}");
        $this->line("Berkas yatim: {$report->orphanFiles}");
        $this->line('Total ukuran: '.Number::fileSize($report->bytes));

        if ($report->dryRun) {
            foreach ($report->items as $item) {
                $this->line(' - '.$item);
            }

            $this->info('Mode uji: tidak ada yang dihapus.');

            return self::SUCCESS;
        }

        $this->info('Pembersihan selesai.');

        return self::SUCCESS;
    }
}
```

- [ ] **Step 11: Jalankan tes — harus LULUS**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="PruneMedia"
```
Expected: PASS (`PruneMediaTest` 9 + `PruneMediaCommandTest` 3 = 12 tes).

- [ ] **Step 12: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint --test
git add app/Console/Commands/PruneMedia.php tests/Feature/Media/PruneMediaCommandTest.php
git commit -m "feat(fase-1): perintah media:prune dengan opsi --dry-run"
```
Expected: Pint `PASS`.

- [ ] **Step 13: Uji asap pada database pengembangan (tidak merusak)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan list media
& $PHP artisan media:prune --dry-run
```
Expected: `media:prune` muncul pada daftar dengan deskripsi Indonesia; keluaran `Baris media yatim: 0`, `Berkas yatim: 0`, `Total ukuran: 0 B`, `Mode uji: tidak ada yang dihapus.`; exit 0. (Bila database pengembangan belum dimigrasi, jalankan `& $PHP artisan migrate` terlebih dahulu.)

- [ ] **Step 14: Verifikasi gerbang Task 8**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test
& $PHP vendor\bin\pint --test
git status --short
```
Expected: seluruh tes hijau; Pint `PASS`; `git status` kosong.

---

### Task 9: Tema SCSS dan aset Vite

**Files:**
- Create: `resources/scss/_variables.scss`, `resources/scss/_bootstrap-theme.scss`, `resources/scss/_shared.scss`, `resources/scss/_public.scss`, `resources/scss/_admin.scss`, `resources/scss/_print.scss`, `resources/js/image-preview.js`
- Modify: `resources/scss/app.scss`, `resources/scss/admin.scss`, `resources/js/app.js`, `resources/js/admin.js`, `vite.config.js`, `tests/Unit/FrontendManifestTest.php`

**Interfaces:**
- Consumes: —
- Produces: token 00 M / 10 §6.5 (`$primary #0A4D8C`, `$teal-dark #0E7C74`, `$teal-light #13A89E`, `$success #1E7D45`, `$light #F3F7FB`, tema `accent`, font Plus Jakarta Sans, 16 px, line-height 1.6); `app.scss` = `_variables` → Bootstrap → `_shared` → `_public` → `_print`; `admin.scss` = `_variables` → Bootstrap → `_shared` → `_admin` → `_print`. Vite input 3 entri (F1-R11) dengan `silenceDeprecations: ['import','mixed-decls','color-functions','global-builtin']`. `app.js` memuat Bootstrap (`window.bootstrap`), ikon, font 400/500/600/700, perilaku `[data-loading-button]`, fokus `[data-errors-summary]`, fallback gambar `img[data-fallback]` lewat **satu** `document.addEventListener('error', …, true)`, impor dinamis `./image-preview.js` bila ada `[data-image-input]`. `admin.js` = `import './app.js'` + `import '../scss/admin.scss'` + konfirmasi modal + `aria-busy` pada `[data-table]`. Kelas CSS yang dipakai komponen: `.table-cards`, `.admin-sidebar`, `.skip-link`, `.auth-card` (+ `.image-frame`, `.image-preview`, `.required-mark`, `.empty-state`, `.stat-card`).
- Penambahan kecil di luar daftar berkas kerangka: **`resources/scss/_bootstrap-theme.scss`** (tumpukan impor Bootstrap yang dipakai bersama `app.scss` dan `admin.scss`). Alasan: `$theme-colors` harus digabung dengan `$custom-theme-colors` **setelah** `bootstrap/scss/variables` dan **sebelum** `bootstrap/scss/maps`, jadi tumpukan impor Bootstrap tidak bisa lagi satu baris `@import "bootstrap/scss/bootstrap"`; satu partial bersama mencegah duplikasi ±55 baris di dua entri (DRY, F1-R13).
- Kontrak markup untuk Task 10/11/13/14 (dipakai JS ini): tombol = `<button data-loading-button data-loading-text="…">` berisi `[data-loading-spinner]` (kelas `d-none`), opsional `[data-loading-icon]`, dan `[data-loading-label]`; `[data-errors-summary]` fokus saat dimuat; modal konfirmasi = elemen `[data-confirm-modal]` berisi `form[data-confirm-form]` dan `[data-confirm-text]`, pemicu = tombol `data-bs-toggle="modal" data-bs-target="#…"` dengan `data-confirm-action` dan `data-confirm-message`; tabel = pembungkus `[data-table]` berisi `form[role="search"]`; unggah gambar = `[data-image-input]` (+ `data-max-kb`) di dalam `[data-image-field]` berisi `[data-image-preview]` dan `[data-image-warning]`; penghitung karakter = `[data-char-counter data-counter-for data-counter-max]` + `[data-counter-announce]`.

**Catatan teknis (diverifikasi terhadap `node_modules/bootstrap` 5.3.8 dan Sass 1.105.1):**
- `$theme-colors` didefinisikan di `bootstrap/scss/_variables.scss`; `$theme-colors-rgb` (basis `.bg-*`, `.text-*`, `.link-*`) dibangun di `bootstrap/scss/_maps.scss` dari `$theme-colors`. Karena itu penggabungan `map-merge($theme-colors, $custom-theme-colors)` wajib ada di antara keduanya. Peta `$theme-colors-text`, `$theme-colors-bg-subtle`, `$theme-colors-border-subtle` juga diberi entri `accent` agar `.alert-accent` dan `.list-group-item-accent` memakai variabel CSS yang ada (`--bs-accent-text-emphasis`, dst.).
- `.offcanvas-lg` Bootstrap memaksa `background-color: transparent !important` pada ≥ 992 px; `_admin.scss` menimpanya dengan `!important` yang sama pada `.admin-sidebar` (urutan sumber lebih akhir menang).
- Sass 1.105 mencetak `WARNING: mixed-decls deprecation is obsolete` karena `mixed-decls` ikut dibungkam sesuai kontrak 10 §6.1. Peringatan itu tidak berbahaya dan boleh diabaikan.

- [ ] **Step 1: Tulis tes yang gagal (ganti seluruh `tests/Unit/FrontendManifestTest.php`)**

```php
<?php

namespace Tests\Unit;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class FrontendManifestTest extends TestCase
{
    private function path(string $file): string
    {
        return dirname(__DIR__, 2).'/'.$file;
    }

    private function read(string $file): string
    {
        return file_get_contents($this->path($file));
    }

    /** @return list<string> */
    private function filesUnder(string $directory, string $suffix): array
    {
        $found = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->path($directory), FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), $suffix)) {
                $found[] = $file->getPathname();
            }
        }

        return $found;
    }

    /** @param list<string> $names */
    private function assertImportOrder(string $source, array $names): void
    {
        $previous = -1;

        foreach ($names as $name) {
            $position = strpos($source, '@import "'.$name.'";');
            $this->assertNotFalse($position, "@import \"{$name}\"; tidak ditemukan");
            $this->assertGreaterThan($previous, $position, "@import \"{$name}\"; berada di urutan yang salah");
            $previous = $position;
        }
    }

    public function test_package_json_uses_bootstrap_stack_without_tailwind(): void
    {
        $package = json_decode($this->read('package.json'), true, flags: JSON_THROW_ON_ERROR);
        $all = array_merge($package['dependencies'] ?? [], $package['devDependencies'] ?? []);

        foreach (['bootstrap', '@popperjs/core', 'sass', 'bootstrap-icons', 'chart.js', 'quill', '@fontsource/plus-jakarta-sans', 'vite', 'laravel-vite-plugin', 'concurrently'] as $name) {
            $this->assertArrayHasKey($name, $all, "{$name} belum dipasang");
        }

        foreach (['tailwindcss', '@tailwindcss/vite', 'axios'] as $name) {
            $this->assertArrayNotHasKey($name, $all, "{$name} harus dihapus");
        }

        $this->assertStringStartsWith('^5.', $all['bootstrap']);
        $this->assertStringStartsWith('^4.', $all['chart.js']);
        $this->assertStringStartsWith('^2.', $all['quill']);
    }

    public function test_vite_builds_three_entries_and_admin_scss_is_not_an_entry(): void
    {
        $config = $this->read('vite.config.js');

        foreach (['resources/scss/app.scss', 'resources/js/app.js', 'resources/js/admin.js'] as $entry) {
            $this->assertStringContainsString("'{$entry}'", $config);
            $this->assertFileExists($this->path($entry));
        }

        $this->assertStringNotContainsString("'resources/scss/admin.scss'", $config, 'admin.scss masuk lewat admin.js (F1-R11), bukan entri sendiri');
        $this->assertStringContainsString('quietDeps: true', $config);

        foreach (['import', 'mixed-decls', 'color-functions', 'global-builtin'] as $deprecation) {
            $this->assertStringContainsString("'{$deprecation}'", $config);
        }

        $this->assertStringNotContainsString('tailwind', $config);
        $this->assertFileDoesNotExist($this->path('resources/css/app.css'));
        $this->assertFileDoesNotExist($this->path('resources/js/bootstrap.js'));
    }

    public function test_admin_entry_imports_shared_script_and_admin_styles(): void
    {
        $admin = $this->read('resources/js/admin.js');

        $this->assertMatchesRegularExpression("#import\\s+'\\./app(\\.js)?';#", $admin);
        $this->assertStringContainsString("import '../scss/admin.scss';", $admin);
        $this->assertFileExists($this->path('resources/scss/admin.scss'));
        $this->assertStringContainsString('[data-confirm-modal]', $admin);
        $this->assertStringContainsString('[data-table]', $admin);
    }

    public function test_app_script_bundles_fonts_icons_bootstrap_and_shared_behaviours(): void
    {
        $app = $this->read('resources/js/app.js');

        foreach (['400', '500', '600', '700'] as $weight) {
            $this->assertStringContainsString("import '@fontsource/plus-jakarta-sans/{$weight}.css';", $app);
        }

        $this->assertStringContainsString("import 'bootstrap-icons/font/bootstrap-icons.css';", $app);
        $this->assertStringContainsString('window.bootstrap = bootstrap;', $app);

        foreach (['[data-loading-button]', '[data-errors-summary]', '[data-image-input]', "import('./image-preview.js')", 'pageshow'] as $needle) {
            $this->assertStringContainsString($needle, $app);
        }

        $this->assertFileExists($this->path('resources/js/image-preview.js'));
    }

    public function test_image_fallback_uses_one_delegated_capture_listener(): void
    {
        $app = $this->read('resources/js/app.js');

        $this->assertMatchesRegularExpression("/document\\.addEventListener\\(\\s*'error'\\s*,\\s*[A-Za-z_\$][\\w\$]*\\s*,\\s*true\\s*\\)/", $app);
        $this->assertStringContainsString('img.complete', $app);
        $this->assertStringContainsString('img.naturalWidth === 0', $app);
        $this->assertStringContainsString('DOMContentLoaded', $app);

        $listeners = 0;

        foreach ($this->filesUnder('resources/js', '.js') as $file) {
            $listeners += preg_match_all("/addEventListener\\(\\s*'error'/", file_get_contents($file));
        }

        $this->assertSame(1, $listeners, 'Harus ada tepat satu listener error terdelegasi di seluruh JS');
    }

    public function test_no_inline_error_handler_in_scripts_or_views(): void
    {
        $files = array_merge(
            $this->filesUnder('resources/js', '.js'),
            $this->filesUnder('resources/views', '.blade.php'),
        );

        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            $this->assertStringNotContainsString('onerror', strtolower(file_get_contents($file)), basename($file).' memuat atribut event inline (CSP produksi memblokirnya)');
        }
    }

    public function test_scss_entries_follow_the_documented_import_order(): void
    {
        $this->assertImportOrder($this->read('resources/scss/app.scss'), ['variables', 'bootstrap-theme', 'shared', 'public', 'print']);
        $this->assertImportOrder($this->read('resources/scss/admin.scss'), ['variables', 'bootstrap-theme', 'shared', '_admin', 'print']);

        $this->assertStringNotContainsString('@import "public";', $this->read('resources/scss/admin.scss'));
        $this->assertStringNotContainsString('@import "_admin";', $this->read('resources/scss/app.scss'));
    }

    public function test_theme_tokens_follow_the_blueprint(): void
    {
        $variables = $this->read('resources/scss/_variables.scss');

        foreach (['#0A4D8C', '#0E7C74', '#13A89E', '#1E7D45', '#F3F7FB'] as $hex) {
            $this->assertStringContainsStringIgnoringCase($hex, $variables);
        }

        $this->assertStringContainsString("'Plus Jakarta Sans'", $variables);
        $this->assertMatchesRegularExpression('/\$font-size-base:\s*1rem/', $variables);
        $this->assertMatchesRegularExpression('/\$line-height-base:\s*1\.6/', $variables);
        $this->assertStringContainsString("'accent'", $variables);
        $this->assertDoesNotMatchRegularExpression('/^\$secondary\s*:/m', $variables, '$secondary dibiarkan abu-abu (00 D: cancelled = secondary)');
    }

    public function test_bootstrap_theme_merges_custom_colors_between_variables_and_maps(): void
    {
        $theme = $this->read('resources/scss/_bootstrap-theme.scss');
        $needles = [
            '@import "bootstrap/scss/functions";',
            '@import "bootstrap/scss/variables";',
            '$theme-colors: map-merge($theme-colors, $custom-theme-colors);',
            '@import "bootstrap/scss/maps";',
            '@import "bootstrap/scss/mixins";',
            '@import "bootstrap/scss/utilities/api";',
        ];

        $previous = -1;

        foreach ($needles as $needle) {
            $position = strpos($theme, $needle);
            $this->assertNotFalse($position, "{$needle} tidak ditemukan");
            $this->assertGreaterThan($previous, $position, "{$needle} berada di urutan yang salah");
            $previous = $position;
        }
    }

    public function test_shared_and_admin_styles_define_the_classes_components_rely_on(): void
    {
        $shared = $this->read('resources/scss/_shared.scss');

        foreach (['.table-cards', 'td[data-label]::before', '.skip-link', ':focus-visible', '2.75rem', '.auth-card', 'prefers-reduced-motion'] as $needle) {
            $this->assertStringContainsString($needle, $shared);
        }

        $admin = $this->read('resources/scss/_admin.scss');

        foreach (['.admin-sidebar', '260px', '$light', '$primary', 'media-breakpoint-down(lg)'] as $needle) {
            $this->assertStringContainsString($needle, $admin);
        }

        $this->assertStringContainsString('@media print', $this->read('resources/scss/_print.scss'));
    }
}
```

- [ ] **Step 2: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=FrontendManifestTest
```
Expected: FAIL. `test_package_json_uses_bootstrap_stack_without_tailwind` lulus; yang lain gagal: `vite.config.js` masih memuat `'resources/scss/admin.scss'` sebagai input, `admin.js` belum mengimpor `admin.scss`, `app.js` belum mengimpor font 500, belum ada listener `error`, `_variables.scss`/`_bootstrap-theme.scss` belum ada (`file_get_contents(...): Failed to open stream`).

- [ ] **Step 3: Ganti `vite.config.js` (tool Write)**

```js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/scss/app.scss', // CSS publik + pasien + auth
                'resources/js/app.js', // JS publik + pasien + auth (dimuat juga oleh admin.js)
                'resources/js/admin.js', // JS admin; mengimpor resources/scss/admin.scss
            ],
            refresh: true,
        }),
    ],
    css: {
        preprocessorOptions: {
            scss: {
                // Bootstrap 5.3 masih memakai @import; sembunyikan peringatan deprecation dari dependensi.
                quietDeps: true,
                silenceDeprecations: ['import', 'mixed-decls', 'color-functions', 'global-builtin'],
            },
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
```

- [ ] **Step 4: Tulis token, tumpukan Bootstrap, dan dua entri SCSS (tool Write)**

`resources/scss/_variables.scss`:

```scss
// Token desain UNPAM HOSPITAL (blueprint 00 bagian M, 10 §6.5).
// Diimpor SEBELUM Bootstrap: semua variabel Bootstrap berflag !default, sehingga nilai di sini menang.

// Warna merek
$primary:          #0A4D8C;   // biru: navbar, sidebar admin, tombol utama, judul (±8,6:1 vs putih)
$teal-dark:        #0E7C74;   // toska tua (±5,0:1 vs putih)
$secondary-accent: $teal-dark;
$teal-light:       #13A89E;   // DEKORASI saja, bukan latar teks putih (±3,0:1)
$success:          #1E7D45;
$body-bg:          #FFFFFF;
$light:            #F3F7FB;
// $secondary Bootstrap sengaja dibiarkan abu-abu (#6C757D): 00 D memetakan status "cancelled" ke secondary.

// Tipografi
$font-family-sans-serif: 'Plus Jakarta Sans', system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
$font-size-base:       1rem;   // 16 px
$line-height-base:     1.6;
$headings-font-weight: 700;
$headings-line-height: 1.25;

// Bentuk
$border-radius: .5rem;         // kartu, tombol, input (07 bagian 3)
$link-color:    $primary;

// Mode gelap di luar ruang lingkup; hindari CSS tak terpakai.
$enable-dark-mode: false;

// Rasio gambar: bawaan Bootstrap + 3x1 (banner 1200x400) untuk <x-image ratio="3x1">.
$aspect-ratios: (
  '1x1':  100%,
  '4x3':  calc(3 / 4 * 100%),
  '16x9': calc(9 / 16 * 100%),
  '21x9': calc(9 / 21 * 100%),
  '3x1':  calc(1 / 3 * 100%)
);

// Warna tema tambahan "accent" -> .btn-accent, .text-bg-accent, .bg-accent, .alert-accent.
// Digabung ke $theme-colors di _bootstrap-theme.scss (setelah variabel Bootstrap dimuat).
$custom-theme-colors: (
  'accent': $secondary-accent
);
```

`resources/scss/_bootstrap-theme.scss`:

```scss
// Tumpukan impor Bootstrap 5.3.8 dengan tema UNPAM HOSPITAL.
// Urutan WAJIB: functions -> variables -> (gabung warna tema) -> maps -> (gabung peta teks/latar) -> mixins -> sisanya.
// Prasyarat: _variables.scss sudah diimpor oleh berkas pemanggil.

@import "bootstrap/scss/functions";
@import "bootstrap/scss/variables";
@import "bootstrap/scss/variables-dark";

// Tambahkan tema "accent" ke $theme-colors SEBELUM maps (maps membangun $theme-colors-rgb dari sini).
$theme-colors: map-merge($theme-colors, $custom-theme-colors);

@import "bootstrap/scss/maps";

// Peta teks/latar-halus/garis-halus dipakai .alert-*, .list-group-item-*, dan utilitas *-emphasis.
// Tanpa entri "accent", var(--bs-accent-text-emphasis) tidak terdefinisi.
$accent-text-emphasis:       shade-color($secondary-accent, 60%);
$accent-bg-subtle:           tint-color($secondary-accent, 80%);
$accent-border-subtle:       tint-color($secondary-accent, 60%);
$theme-colors-text:          map-merge($theme-colors-text, ('accent': $accent-text-emphasis));
$theme-colors-bg-subtle:     map-merge($theme-colors-bg-subtle, ('accent': $accent-bg-subtle));
$theme-colors-border-subtle: map-merge($theme-colors-border-subtle, ('accent': $accent-border-subtle));

@import "bootstrap/scss/mixins";
@import "bootstrap/scss/utilities";

// Layout dan komponen
@import "bootstrap/scss/root";
@import "bootstrap/scss/reboot";
@import "bootstrap/scss/type";
@import "bootstrap/scss/images";
@import "bootstrap/scss/containers";
@import "bootstrap/scss/grid";
@import "bootstrap/scss/tables";
@import "bootstrap/scss/forms";
@import "bootstrap/scss/buttons";
@import "bootstrap/scss/transitions";
@import "bootstrap/scss/dropdown";
@import "bootstrap/scss/button-group";
@import "bootstrap/scss/nav";
@import "bootstrap/scss/navbar";
@import "bootstrap/scss/card";
@import "bootstrap/scss/accordion";
@import "bootstrap/scss/breadcrumb";
@import "bootstrap/scss/pagination";
@import "bootstrap/scss/badge";
@import "bootstrap/scss/alert";
@import "bootstrap/scss/progress";
@import "bootstrap/scss/list-group";
@import "bootstrap/scss/close";
@import "bootstrap/scss/toasts";
@import "bootstrap/scss/modal";
@import "bootstrap/scss/tooltip";
@import "bootstrap/scss/popover";
@import "bootstrap/scss/carousel";
@import "bootstrap/scss/spinners";
@import "bootstrap/scss/offcanvas";
@import "bootstrap/scss/placeholders";

// Helper dan utilitas
@import "bootstrap/scss/helpers";
@import "bootstrap/scss/utilities/api";
```

`resources/scss/app.scss` (ganti isi):

```scss
// Entri gaya situs publik, portal pasien, dan halaman auth.
@import "variables";
@import "bootstrap-theme";
@import "shared";
@import "public";
@import "print";
```

`resources/scss/admin.scss` (ganti isi):

```scss
// Entri gaya dashboard admin. Dimuat lewat resources/js/admin.js (bukan entri Vite sendiri).
// "_admin" memakai garis bawah eksplisit: "admin" sendiri ambigu antara admin.scss dan _admin.scss.
@import "variables";
@import "bootstrap-theme";
@import "shared";
@import "_admin";
@import "print";
```

- [ ] **Step 5: Tulis `resources/scss/_shared.scss` (tool Write)**

```scss
// Gaya bersama situs publik, portal pasien, auth, dan admin (F1-R13).
// Diimpor SETELAH Bootstrap sehingga variabel dan mixin Bootstrap tersedia.

// ---------------------------------------------------------------- Dasar
html {
  scroll-padding-top: 1rem;
}

h1 { font-size: 1.625rem; }
h2 { font-size: 1.375rem; font-weight: 600; line-height: 1.3; }
h3 { font-size: 1.125rem; font-weight: 600; line-height: 1.35; }

@include media-breakpoint-up(sm) {
  h1 { font-size: 2rem; }
  h2 { font-size: 1.5rem; }
  h3 { font-size: 1.25rem; }
}

// Tautan di dalam teks isi selalu bergaris bawah (bukan warna saja).
p a:not(.btn),
li a:not(.btn):not(.nav-link):not(.dropdown-item):not(.page-link) {
  text-decoration: underline;
}

// Target skip link (main tabindex="-1") tidak perlu cincin fokus.
main:focus {
  outline: 0;
}

// ---------------------------------------------------------------- Skip link
.skip-link {
  position: absolute;
  top: 0;
  left: 0;
  z-index: $zindex-tooltip + 10;
  padding: .75rem 1rem;
  background: $primary;
  color: #fff;
  font-weight: 600;
  transform: translateY(-120%);

  &:focus,
  &:focus-visible {
    transform: translateY(0);
    color: #fff;
    outline: 3px solid #fff;
    outline-offset: -6px;
  }
}

// ---------------------------------------------------------------- Fokus terlihat (00 M)
:focus-visible {
  outline: 3px solid $primary;
  outline-offset: 2px;
}

// Pada latar gelap/primary cincin fokus berwarna putih.
.bg-primary,
.text-bg-primary,
.bg-dark,
.text-bg-dark {
  :focus-visible {
    outline-color: #fff;
  }
}

.btn:focus-visible,
.form-control:focus-visible,
.form-select:focus-visible {
  outline: 3px solid $primary;
  outline-offset: 2px;
  box-shadow: none;
}

// ---------------------------------------------------------------- Tinggi sentuh 44 px (2,75 rem)
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: .375rem;
  min-height: 2.75rem;
}

.btn-sm {
  min-height: 2.75rem;
}

.btn-close {
  box-sizing: border-box;
  min-width: 2.75rem;
  min-height: 2.75rem;
}

.form-control,
.form-select {
  min-height: 2.75rem;
}

textarea.form-control {
  min-height: 6rem;
}

.nav-link,
.dropdown-item {
  display: flex;
  align-items: center;
  min-height: 2.75rem;
}

.page-link {
  display: flex;
  align-items: center;
  justify-content: center;
  min-width: 2.75rem;
  min-height: 2.75rem;
}

.form-check:not(.form-switch) {
  display: flex;
  align-items: center;
  gap: .5rem;
  min-height: 2.75rem;
  padding-left: 0;

  .form-check-input {
    float: none;
    flex-shrink: 0;
    margin: 0;
  }

  .form-check-label {
    flex: 1;
    padding-block: .5rem;
  }
}

// ---------------------------------------------------------------- Tanda wajib
.required-mark {
  color: $danger;
  font-weight: 700;
}

// ---------------------------------------------------------------- Kartu auth
.auth-card {
  width: 100%;
  max-width: 28rem;
  margin-inline: auto;
  padding: 2rem 1.5rem;
  background: #fff;
  border: 1px solid var(--bs-border-color);
  border-radius: $border-radius;
  box-shadow: $box-shadow-sm;

  @include media-breakpoint-up(sm) {
    padding: 2.5rem;
  }
}

// ---------------------------------------------------------------- Gambar
.image-frame {
  background: $light;
  border-radius: $border-radius;
  overflow: hidden;
}

.image-preview {
  max-width: 100%;
  max-height: 12rem;
  border-radius: $border-radius;
  border: 1px solid var(--bs-border-color);
}

// ---------------------------------------------------------------- Status memuat
[data-table] {
  transition: opacity .15s ease;

  &[aria-busy="true"] {
    opacity: .55;
    pointer-events: none;
  }
}

@media (prefers-reduced-motion: reduce) {
  *,
  *::before,
  *::after {
    animation-duration: .01ms !important;
    animation-iteration-count: 1 !important;
    scroll-behavior: auto !important;
    transition-duration: .01ms !important;
  }
}

// ---------------------------------------------------------------- Tabel -> kartu < 768 px (F1-R13)
// Setiap <td> wajib memiliki data-label; labelnya ditulis ulang di dalam kartu.
@include media-breakpoint-down(md) {
  .table-cards {
    thead {
      position: absolute;
      width: 1px;
      height: 1px;
      overflow: hidden;
      clip: rect(0, 0, 0, 0);
      white-space: nowrap;
    }

    &,
    tbody,
    tr,
    th,
    td {
      display: block;
      width: 100%;
    }

    tr {
      margin-bottom: 1rem;
      padding: .75rem 1rem;
      background: #fff;
      border: 1px solid var(--bs-border-color);
      border-radius: $border-radius;
      box-shadow: $box-shadow-sm;
    }

    th,
    td {
      padding: .375rem 0;
      border: 0;
      box-shadow: none;
      background: transparent;
    }

    td[data-label]::before {
      content: attr(data-label);
      display: block;
      font-size: .875rem;
      font-weight: 600;
      color: var(--bs-secondary-color);
    }

    // Sel aksi: tombol berdampingan, label kolom di baris sendiri.
    td.table-actions {
      display: flex;
      flex-wrap: wrap;
      gap: .5rem;
      padding-top: .75rem;

      &::before {
        flex-basis: 100%;
      }
    }
  }
}
```

- [ ] **Step 6: Tulis `_public.scss`, `_admin.scss`, `_print.scss` (tool Write)**

`resources/scss/_public.scss`:

```scss
// Gaya situs publik dan portal pasien (app.scss).

.site-topbar {
  background: $primary;
  color: #fff;
  font-size: .875rem;

  a {
    color: #fff;
    text-decoration: underline;
  }
}

.site-header {
  background: #fff;
  box-shadow: $box-shadow-sm;

  .navbar-brand {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    color: $primary;
    font-weight: 700;

    img {
      height: 2.5rem;
      width: auto;
    }
  }

  .nav-link {
    color: var(--bs-body-color);
    font-weight: 500;
    border-bottom: 3px solid transparent;

    &:hover {
      color: $primary;
    }

    // Penanda halaman aktif: teks tebal + garis bawah (garis toska muda hanya dekoratif).
    &[aria-current="page"] {
      color: $primary;
      font-weight: 700;
      border-bottom-color: $teal-light;
    }
  }
}

.site-main {
  min-height: 50vh;
}

.section {
  padding-block: 1.5rem;

  @include media-breakpoint-up(md) {
    padding-block: 3rem;
  }

  &.section-alt {
    background: $light;
  }
}

.accent-line {
  display: inline-block;
  width: 4rem;
  height: .25rem;
  margin-bottom: .75rem;
  background: $teal-light;
  border-radius: 1rem;
}

.cta-block {
  padding: 2rem 1.5rem;
  background: $teal-dark;
  color: #fff;
  border-radius: $border-radius;
}

.site-footer {
  background: $primary;
  color: #fff;

  a {
    color: #fff;
    text-decoration: underline;
  }

  h2,
  h3 {
    color: #fff;
    font-size: 1.125rem;
  }
}

.empty-state {
  padding: 2.5rem 1rem;
  text-align: center;
  color: var(--bs-secondary-color);

  .empty-state-icon {
    font-size: 2.5rem;
    color: $teal-dark;
  }
}
```

`resources/scss/_admin.scss`:

```scss
// Gaya dashboard admin (admin.scss). Sidebar mengikuti W-13: lebar 260 px, latar $primary.
//
// Struktur markup yang diharapkan (dibangun di Task 14):
//   <aside id="admin-sidebar" class="admin-sidebar offcanvas-lg offcanvas-start" tabindex="-1" aria-labelledby="admin-sidebar-title">
//     <div class="offcanvas-header"> ... <button class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#admin-sidebar"> </div>
//     <div class="offcanvas-body flex-column"> <nav aria-label="Menu admin" class="w-100"> ... </nav> </div>
//   </aside>

$admin-sidebar-width: 260px;

.admin-shell {
  display: flex;
  min-height: 100vh;
  background: $light;
}

.admin-main {
  flex: 1 1 auto;
  min-width: 0;
}

.admin-topbar {
  position: sticky;
  top: 0;
  z-index: $zindex-sticky;
  background: #fff;
  box-shadow: $box-shadow-sm;
}

.admin-content {
  padding: 1rem;

  @include media-breakpoint-up(lg) {
    padding: 1.5rem;
  }
}

.admin-footer {
  padding: 1rem;
  color: var(--bs-secondary-color);
  font-size: .875rem;
}

// Sidebar: offcanvas di < 992 px (Bootstrap .offcanvas-lg), tetap di >= 992 px.
.admin-sidebar {
  --bs-offcanvas-color: #fff;
  // .offcanvas-lg memaksa latar transparan pada >= lg; timpa dengan !important yang sama (sumber lebih akhir menang).
  background-color: $primary !important;
  color: #fff;

  @include media-breakpoint-down(lg) {
    --bs-offcanvas-width: min(85vw, #{$admin-sidebar-width});
  }

  @include media-breakpoint-up(lg) {
    position: sticky;
    top: 0;
    flex: 0 0 $admin-sidebar-width;
    width: $admin-sidebar-width;
    height: 100vh;
    overflow-y: auto;
  }

  .admin-brand {
    display: flex;
    align-items: center;
    gap: .5rem;
    padding: 1rem;
    color: #fff;
    font-weight: 700;
    text-decoration: none;
  }

  .nav-group-label {
    padding: 1rem 1rem .25rem;
    color: rgba(#fff, .75);
    font-size: .75rem;
    font-weight: 600;
    letter-spacing: .05em;
    text-transform: uppercase;
  }

  .nav-link {
    gap: .625rem;
    margin: .125rem .5rem;
    padding: .625rem .75rem;
    color: rgba(#fff, .92);
    border-left: 4px solid transparent;
    border-radius: $border-radius;

    &:hover,
    &:focus-visible {
      background: rgba(#fff, .12);
      color: #fff;
    }

    &:focus-visible {
      outline-color: #fff;
    }

    // Item aktif: latar $light, teks $primary; garis toska muda hanya penanda dekoratif.
    &.active,
    &[aria-current="page"] {
      background: $light;
      color: $primary;
      font-weight: 600;
      border-left-color: $teal-light;
    }

    .bi {
      flex-shrink: 0;
    }
  }
}

.stat-card {
  height: 100%;
  border-radius: $border-radius;

  .stat-card-value {
    font-size: 2rem;
    font-weight: 700;
    line-height: 1.2;
  }
}
```

`resources/scss/_print.scss`:

```scss
// Gaya cetak (bukti pendaftaran, laporan). Navigasi dan kontrol disembunyikan.

@media print {
  .no-print,
  .skip-link,
  .site-topbar,
  .site-header,
  .site-footer,
  .admin-sidebar,
  .admin-topbar,
  .admin-footer,
  .alert .btn-close,
  .btn,
  nav,
  form[role="search"],
  .pagination {
    display: none !important;
  }

  body {
    background: #fff !important;
    color: #000 !important;
    font-size: 12pt;
  }

  .admin-shell,
  .admin-main {
    display: block;
  }

  .admin-content,
  .container,
  .container-fluid {
    max-width: 100%;
    padding: 0;
  }

  .card {
    border: 1px solid #000;
    box-shadow: none;
  }

  a {
    color: #000;
    text-decoration: none;
  }

  tr,
  .card {
    break-inside: avoid;
  }
}
```

- [ ] **Step 7: Tulis `resources/js/app.js` dan `resources/js/image-preview.js` (tool Write)**

`resources/js/app.js`:

```js
import * as bootstrap from 'bootstrap';
import 'bootstrap-icons/font/bootstrap-icons.css';
import '@fontsource/plus-jakarta-sans/400.css';
import '@fontsource/plus-jakarta-sans/500.css';
import '@fontsource/plus-jakarta-sans/600.css';
import '@fontsource/plus-jakarta-sans/700.css';

window.bootstrap = bootstrap;

// ---------------------------------------------------------------- Tombol memuat (x-loading-button)
// Markup: <button data-loading-button data-loading-text="Memproses…"> berisi
// [data-loading-spinner] (kelas d-none), opsional [data-loading-icon], dan [data-loading-label].
function setLoading(button) {
    if (button.dataset.loadingActive === '1') {
        return;
    }

    button.dataset.loadingActive = '1';

    const label = button.querySelector('[data-loading-label]');
    const spinner = button.querySelector('[data-loading-spinner]');
    const icon = button.querySelector('[data-loading-icon]');

    if (label) {
        button.dataset.originalLabel = label.textContent;
        label.textContent = button.dataset.loadingText || 'Memproses…';
    }

    if (spinner) {
        spinner.classList.remove('d-none');
    }

    if (icon) {
        icon.classList.add('d-none');
    }

    button.setAttribute('aria-busy', 'true');
    button.disabled = true;
}

function resetLoading(button) {
    if (button.dataset.loadingActive !== '1') {
        return;
    }

    delete button.dataset.loadingActive;

    const label = button.querySelector('[data-loading-label]');
    const spinner = button.querySelector('[data-loading-spinner]');
    const icon = button.querySelector('[data-loading-icon]');

    if (label && button.dataset.originalLabel !== undefined) {
        label.textContent = button.dataset.originalLabel;
    }

    if (spinner) {
        spinner.classList.add('d-none');
    }

    if (icon) {
        icon.classList.remove('d-none');
    }

    button.removeAttribute('aria-busy');
    button.disabled = false;
}

document.addEventListener('submit', (event) => {
    const form = event.target;

    if (event.defaultPrevented || !(form instanceof HTMLFormElement)) {
        return;
    }

    // Formulir yang tidak memuat ulang halaman (unduhan, tab baru) tidak boleh mengunci tombol.
    if (form.hasAttribute('data-no-loading') || (form.target && form.target !== '_self')) {
        return;
    }

    const buttons = form.querySelectorAll('[data-loading-button]');

    if (buttons.length === 0) {
        return;
    }

    // Ditunda satu tick: tombol pengirim yang langsung dinonaktifkan tidak ikut terkirim oleh browser.
    window.setTimeout(() => {
        buttons.forEach(setLoading);
        form.setAttribute('aria-busy', 'true');
    }, 0);
});

// Tombol Back/Forward memulihkan halaman dari cache: kembalikan semua status memuat.
window.addEventListener('pageshow', () => {
    document.querySelectorAll('[data-loading-button]').forEach(resetLoading);
    document.querySelectorAll('form[aria-busy]').forEach((form) => form.removeAttribute('aria-busy'));
    document.querySelectorAll('[data-table][aria-busy]').forEach((table) => table.removeAttribute('aria-busy'));
});

// ---------------------------------------------------------------- Fallback gambar (x-image)
// Satu listener terdelegasi pada fase capture (00 K); event error pada <img> tidak bubbling.
function applyImageFallback(img) {
    const fallback = img.dataset.fallback;

    if (!fallback || img.dataset.fallbackApplied === '1') {
        return;
    }

    img.dataset.fallbackApplied = '1';
    img.removeAttribute('srcset');
    img.src = fallback;
}

function handleImageError(event) {
    const target = event.target;

    if (target instanceof HTMLImageElement && target.matches('img[data-fallback]')) {
        applyImageFallback(target);
    }
}

document.addEventListener('error', handleImageError, true);

// Gambar yang sudah gagal sebelum skrip ini aktif tidak akan memicu error lagi: periksa ulang.
function recheckBrokenImages() {
    document.querySelectorAll('img[data-fallback]').forEach((img) => {
        if (img.complete && img.naturalWidth === 0) {
            applyImageFallback(img);
        }
    });
}

// ---------------------------------------------------------------- Ringkasan galat formulir
function focusErrorsSummary() {
    const summary = document.querySelector('[data-errors-summary]');

    if (summary) {
        summary.focus();
    }
}

// ---------------------------------------------------------------- Penghitung karakter (x-form.textarea)
function initCharCounters() {
    document.querySelectorAll('[data-char-counter]').forEach((counter) => {
        const input = document.getElementById(counter.dataset.counterFor);
        const max = Number.parseInt(counter.dataset.counterMax, 10);

        if (!input || Number.isNaN(max)) {
            return;
        }

        const announce = counter.parentElement ? counter.parentElement.querySelector('[data-counter-announce]') : null;
        let lastMessage = '';

        const update = () => {
            const used = input.value.length;
            const remaining = max - used;

            counter.textContent = `${used} / ${max}`;

            if (!announce) {
                return;
            }

            // Pembaca layar hanya diberi tahu saat mendekati batas (10% terakhir), bukan tiap ketikan.
            let message = '';

            if (remaining <= 0) {
                message = 'Batas karakter tercapai.';
            } else if (remaining <= Math.ceil(max * 0.1)) {
                message = `Sisa ${remaining} karakter.`;
            }

            if (message !== lastMessage) {
                announce.textContent = message;
                lastMessage = message;
            }
        };

        input.addEventListener('input', update);
        update();
    });
}

// ---------------------------------------------------------------- aria-expanded pada pemicu offcanvas
function initOffcanvasToggles() {
    document.querySelectorAll('.offcanvas').forEach((panel) => {
        if (!panel.id) {
            return;
        }

        const triggers = document.querySelectorAll(`[data-bs-toggle="offcanvas"][data-bs-target="#${CSS.escape(panel.id)}"]`);

        panel.addEventListener('show.bs.offcanvas', () => {
            triggers.forEach((trigger) => trigger.setAttribute('aria-expanded', 'true'));
        });

        panel.addEventListener('hidden.bs.offcanvas', () => {
            triggers.forEach((trigger) => trigger.setAttribute('aria-expanded', 'false'));
        });
    });
}

function init() {
    recheckBrokenImages();
    focusErrorsSummary();
    initCharCounters();
    initOffcanvasToggles();

    if (document.querySelector('[data-image-input]')) {
        import('./image-preview.js');
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
```

`resources/js/image-preview.js`:

```js
// Pratinjau gambar lokal sebelum unggah. Hanya saran: validasi final dilakukan server (00 K).
// Markup: [data-image-field] berisi input[data-image-input][data-max-kb], img[data-image-preview], [data-image-warning].

const DEFAULT_MAX_KB = 2048;

function formatSize(kb) {
    return kb >= 1024 ? `${(kb / 1024).toFixed(1).replace('.', ',')} MB` : `${Math.round(kb)} KB`;
}

document.querySelectorAll('[data-image-input]').forEach((input) => {
    const field = input.closest('[data-image-field]') || input.parentElement;
    const preview = field.querySelector('[data-image-preview]');
    const warning = field.querySelector('[data-image-warning]');
    const maxKb = Number.parseInt(input.dataset.maxKb, 10) || DEFAULT_MAX_KB;
    let objectUrl = null;

    const clear = () => {
        if (objectUrl) {
            URL.revokeObjectURL(objectUrl);
            objectUrl = null;
        }

        if (preview) {
            preview.classList.add('d-none');
            preview.removeAttribute('src');
        }

        if (warning) {
            warning.classList.add('d-none');
            warning.textContent = '';
        }
    };

    input.addEventListener('change', () => {
        clear();

        const files = Array.from(input.files || []);

        if (files.length === 0) {
            return;
        }

        const first = files[0];

        if (preview && first.type.startsWith('image/')) {
            objectUrl = URL.createObjectURL(first);
            preview.src = objectUrl;
            preview.classList.remove('d-none');
        }

        const oversized = files.filter((file) => file.size / 1024 > maxKb);

        if (warning && oversized.length > 0) {
            const names = oversized.map((file) => file.name).join(', ');
            warning.textContent = `Ukuran berkas melebihi ${formatSize(maxKb)} (${names}). Server akan menolaknya; pilih gambar yang lebih kecil.`;
            warning.classList.remove('d-none');
        }
    });
});
```

- [ ] **Step 8: Tulis `resources/js/admin.js` (tool Write)**

```js
// Entri skrip dashboard admin: perilaku bersama (Bootstrap, font, fallback gambar, tombol memuat) + gaya admin.
import './app.js';
import '../scss/admin.scss';

// ---------------------------------------------------------------- Modal konfirmasi (x-confirm-modal)
// Satu modal melayani banyak baris: tombol pemicu membawa data-confirm-action dan data-confirm-message.
document.querySelectorAll('[data-confirm-modal]').forEach((modal) => {
    const form = modal.querySelector('form[data-confirm-form]');
    const text = modal.querySelector('[data-confirm-text]');

    if (!form) {
        return;
    }

    const defaultAction = form.getAttribute('action');
    const defaultText = text ? text.textContent : '';

    modal.addEventListener('show.bs.modal', (event) => {
        const trigger = event.relatedTarget;

        if (trigger && trigger.dataset.confirmAction) {
            form.setAttribute('action', trigger.dataset.confirmAction);
        }

        if (trigger && trigger.dataset.confirmMessage && text) {
            text.textContent = trigger.dataset.confirmMessage;
        }
    });

    modal.addEventListener('hidden.bs.modal', () => {
        form.setAttribute('action', defaultAction ?? '');

        if (text) {
            text.textContent = defaultText;
        }

        const reason = form.querySelector('textarea[name="reason"]');

        if (reason) {
            reason.value = '';
        }
    });
});

// ---------------------------------------------------------------- Pencarian/filter data-table: status memuat
document.addEventListener('submit', (event) => {
    const form = event.target;

    if (event.defaultPrevented || !(form instanceof HTMLFormElement) || !form.matches('form[role="search"]')) {
        return;
    }

    const table = form.closest('[data-table]');

    if (table) {
        table.setAttribute('aria-busy', 'true');
    }
});
```

- [ ] **Step 9: Jalankan tes — harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=FrontendManifestTest
```
Expected: PASS (10 tes). Bila `test_no_inline_error_handler_in_scripts_or_views` gagal, cari kata `onerror` (termasuk di komentar) di berkas yang disebut pesan galat dan hapus.

- [ ] **Step 10: Build aset dan periksa manifest**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
npm run build
$m = Get-Content -Raw -LiteralPath "public\build\manifest.json" | ConvertFrom-Json
$m.PSObject.Properties.Name | Where-Object { $_ -like 'resources/*' } | Sort-Object
"admin.js css: " + ($m.'resources/js/admin.js'.css -join ', ')
"app.scss file: " + $m.'resources/scss/app.scss'.file
```
Expected: build selesai tanpa galat (`✓ built in …`; peringatan Sass `mixed-decls deprecation is obsolete` boleh muncul). Daftar entri manifest:

```text
resources/js/admin.js
resources/js/app.js
resources/js/image-preview.js
resources/scss/app.scss
```

`resources/js/image-preview.js` muncul karena impor dinamis; `resources/scss/admin.scss` **tidak** muncul sebagai kunci sendiri (CSS-nya menempel pada `admin.js`: baris `admin.js css:` menampilkan minimal satu berkas `assets/admin-….css`). Bila `app.scss` atau `app.js`/`admin.js` hilang dari daftar, periksa `input` di `vite.config.js`. `public/build` di-ignore git (tidak di-commit).

- [ ] **Step 11: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint --test
& $PHP artisan test --filter=FrontendManifestTest
git add vite.config.js resources/scss/_variables.scss resources/scss/_bootstrap-theme.scss resources/scss/_shared.scss resources/scss/_public.scss resources/scss/_admin.scss resources/scss/_print.scss resources/scss/app.scss resources/scss/admin.scss resources/js/app.js resources/js/admin.js resources/js/image-preview.js tests/Unit/FrontendManifestTest.php
git status --short
git commit -m "feat(fase-1): tema SCSS UNPAM HOSPITAL dan aset Vite dengan tiga entri"
```
Expected: pint `PASS`; tes hijau; `git status --short` tidak memuat `public/build` maupun `node_modules`.

---

### Task 10: Komponen Blade — navigasi, umpan balik, status, gambar

**Files:**
- Create: `resources/views/components/breadcrumb.blade.php`, `page-header.blade.php`, `empty-state.blade.php`, `flash-alert.blade.php`, `loading-button.blade.php`, `stat-card.blade.php`, `status-badge.blade.php`, `image.blade.php` (semua di `resources/views/components/`); `app/View/Components/StatusBadge.php`, `app/View/Components/Image.php`; `tests/Support/Fixtures/HostileStatus.php`, `tests/Support/Fixtures/PlainEnum.php`
- Test: `tests/Feature/View/NavigationComponentsTest.php`, `tests/Feature/View/FeedbackComponentsTest.php`, `tests/Feature/View/StatusBadgeComponentTest.php`, `tests/Feature/View/ImageComponentTest.php` (kerangka menyebut dua berkas; dipecah per kelompok komponen agar setiap siklus TDD menulis berkasnya sendiri)

**Interfaces:**
- Consumes: enum badge (Task 1: `label()`, `color()`, `icon()`), `Media` (Task 5: `url`, `thumb_url`, `alt_text`, `width`, `height`, `thumb_path`), `MediaService::THUMB_WIDTH` (Task 5), placeholder SVG `public/images/placeholders/*.svg` (Task 5), kontrak `data-*` JS (Task 9).
- Produces (props persis 10 §3.2/§3.4): `<x-breadcrumb :items home>`, `<x-page-header title subtitle icon>` + slot `actions`, `<x-empty-state title message icon>`, `<x-flash-alert :keys>`, `<x-loading-button type variant loadingText icon>`, `<x-stat-card label value icon variant hint href>`, `<x-status-badge :status>` (kelas `App\View\Components\StatusBadge`, konstruktor `(UnitEnum $status)`), `<x-image type :media src alt variant ratio lazy decorative>` (kelas `App\View\Components\Image`, `TYPES`, `RATIOS`).
- Aturan yang berlaku untuk semua komponen: seluruh `{{ }}` ter-escape (SEK-15); atribut tambahan lewat `$attributes->merge()`; ikon `aria-hidden="true"`; tidak ada `{!! !!}`; tidak ada atribut event inline.

**Keputusan teknis (diverifikasi pada `vendor/laravel/framework` 12.69):**
- Komponen anonim = berkas `resources/views/components/*.blade.php` dengan `@props`; komponen kelas = `App\View\Components\*` (penemuan otomatis `<x-status-badge>` → `StatusBadge`); bila kelas dan view sama-sama ada, kelas yang dipakai. Variabel view komponen kelas = properti `public` dan method `public`.
- `$this->blade($template, $data)` (trait `InteractsWithViews`) merender langsung dan mengembalikan `TestView` (`assertSee($value, $escape = true)`, `assertSeeInOrder`, `assertDontSee`, `(string) $view`). `$this->component(Kelas::class, $data)` memanggil `$this->app->make()` sehingga galat konstruktor dilempar langsung.
- Pada tag yang memakai `{{ $attributes }}` (tanpa `merge`) bag kosong menyisakan spasi sebelum `>`; karena itu tes untuk `<nav>`/`<div>` menguji fragmen atribut, bukan tag utuh.
- Tautan "beranda" breadcrumb memakai `Route::has($home) ? route($home) : url('/')` (`url('/admin')` bila `admin.dashboard`) karena route `home` baru ada di Task 13 dan `admin.dashboard` di Task 14 (F1-R2).

#### Siklus A — navigasi dan kepala halaman: `breadcrumb`, `page-header`, `empty-state`

- [ ] **Step 1: Tulis tes yang gagal — `tests/Feature/View/NavigationComponentsTest.php`**

```php
<?php

namespace Tests\Feature\View;

use Tests\TestCase;

class NavigationComponentsTest extends TestCase
{
    public function test_breadcrumb_renders_home_then_items_and_marks_the_last_as_current(): void
    {
        $this->blade('<x-breadcrumb :items="$items" />', [
            'items' => [
                ['label' => 'Dokter', 'url' => '/dokter'],
                ['label' => 'dr. Andi'],
            ],
        ])
            ->assertSee('aria-label="Breadcrumb"', false)
            ->assertSee('<ol class="breadcrumb">', false)
            ->assertSeeInOrder(['Beranda', 'Dokter', 'dr. Andi'])
            ->assertSee('<li class="breadcrumb-item"><a href="'.url('/').'">Beranda</a></li>', false)
            ->assertSee('<li class="breadcrumb-item"><a href="/dokter">Dokter</a></li>', false)
            ->assertSee('<li class="breadcrumb-item active" aria-current="page">dr. Andi</li>', false);
    }

    public function test_breadcrumb_for_admin_starts_with_dasbor(): void
    {
        $this->blade('<x-breadcrumb home="admin.dashboard" :items="$items" />', [
            'items' => [['label' => 'Dokter']],
        ])
            ->assertSee('<li class="breadcrumb-item"><a href="'.url('/admin').'">Dasbor</a></li>', false)
            ->assertSee('<li class="breadcrumb-item active" aria-current="page">Dokter</li>', false)
            ->assertDontSee('Beranda');
    }

    public function test_breadcrumb_without_items_shows_home_as_the_current_page(): void
    {
        $this->blade('<x-breadcrumb />')
            ->assertSee('<li class="breadcrumb-item active" aria-current="page">Beranda</li>', false)
            ->assertDontSee('<a href', false);
    }

    public function test_breadcrumb_escapes_labels_and_urls(): void
    {
        $this->blade('<x-breadcrumb :items="$items" />', [
            'items' => [
                ['label' => '<script>alert(1)</script>', 'url' => '/x?a=1&b="2"'],
                ['label' => 'Akhir'],
            ],
        ])
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertSee('href="/x?a=1&amp;b=&quot;2&quot;"', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_page_header_renders_single_h1_with_subtitle_icon_and_actions(): void
    {
        $view = $this->blade(<<<'BLADE'
            <x-page-header title="Daftar Dokter" subtitle="Kelola data dokter." icon="bi-person-badge">
                <x-slot:actions>
                    <a href="/admin/dokter/baru" class="btn btn-primary">Tambah Dokter</a>
                </x-slot:actions>
                <span class="badge text-bg-secondary">Info tambahan</span>
            </x-page-header>
            BLADE);

        $view->assertSee('Daftar Dokter')
            ->assertSee('Kelola data dokter.')
            ->assertSee('<i class="bi bi-person-badge me-2" aria-hidden="true"></i>', false)
            ->assertSee('page-header-actions', false)
            ->assertSee('Tambah Dokter')
            ->assertSee('Info tambahan');

        $this->assertSame(1, substr_count((string) $view, '<h1'));
    }

    public function test_page_header_without_optional_parts_renders_only_the_title(): void
    {
        $this->blade('<x-page-header title="Dasbor" />')
            ->assertSee('Dasbor')
            ->assertDontSee('page-header-actions', false)
            ->assertDontSee('text-secondary', false)
            ->assertDontSee('<i class="bi', false);
    }

    public function test_page_header_escapes_title_and_subtitle(): void
    {
        $this->blade('<x-page-header :title="$title" :subtitle="$title" />', ['title' => '<script>alert(1)</script>'])
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_empty_state_renders_status_role_title_message_icon_and_action(): void
    {
        $this->blade(<<<'BLADE'
            <x-empty-state title="Belum ada dokter" message="Tambahkan dokter pertama Anda." icon="bi-person">
                <a href="/admin/dokter/baru" class="btn btn-primary">Tambah Dokter</a>
            </x-empty-state>
            BLADE)
            ->assertSee('role="status"', false)
            ->assertSee('<i class="bi bi-person empty-state-icon d-block mb-2" aria-hidden="true"></i>', false)
            ->assertSeeInOrder(['Belum ada dokter', 'Tambahkan dokter pertama Anda.', 'Tambah Dokter']);
    }

    public function test_empty_state_defaults_to_inbox_icon_and_hides_missing_parts(): void
    {
        $this->blade('<x-empty-state title="Kosong" />')
            ->assertSee('bi bi-inbox empty-state-icon', false)
            ->assertSee('Kosong')
            ->assertDontSee('<div class="d-flex flex-wrap justify-content-center gap-2">', false)
            ->assertDontSee('mb-3', false);
    }

    public function test_empty_state_escapes_title_and_message(): void
    {
        $this->blade('<x-empty-state :title="$payload" :message="$payload" />', ['payload' => '<script>alert(1)</script>'])
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }
}
```

- [ ] **Step 2: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=NavigationComponentsTest
```
Expected: FAIL — semua tes gagal dengan `InvalidArgumentException: Unable to locate a class or view for component [breadcrumb].` (juga `[page-header]`, `[empty-state]`).

- [ ] **Step 3: Implementasi tiga komponen (tool Write)**

`resources/views/components/breadcrumb.blade.php`:

```blade
@props(['items' => [], 'home' => 'home'])
@php
    $isAdmin = $home === 'admin.dashboard';
    $homeUrl = \Illuminate\Support\Facades\Route::has($home) ? route($home) : url($isAdmin ? '/admin' : '/');
    $trail = array_merge([['label' => $isAdmin ? 'Dasbor' : 'Beranda', 'url' => $homeUrl]], array_values($items));
    $last = count($trail) - 1;
@endphp
<nav aria-label="Breadcrumb" {{ $attributes }}>
    <ol class="breadcrumb">
        @foreach ($trail as $index => $item)
            @if ($index === $last)
                <li class="breadcrumb-item active" aria-current="page">{{ $item['label'] }}</li>
            @elseif (! empty($item['url']))
                <li class="breadcrumb-item"><a href="{{ $item['url'] }}">{{ $item['label'] }}</a></li>
            @else
                <li class="breadcrumb-item">{{ $item['label'] }}</li>
            @endif
        @endforeach
    </ol>
</nav>
```

`resources/views/components/page-header.blade.php`:

```blade
@props(['title', 'subtitle' => null, 'icon' => null])
<div {{ $attributes->merge(['class' => 'page-header d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4']) }}>
    <div>
        <h1 class="mb-1">@if ($icon)<i class="bi {{ $icon }} me-2" aria-hidden="true"></i>@endif{{ $title }}</h1>
        @if ($subtitle)
            <p class="text-secondary mb-0">{{ $subtitle }}</p>
        @endif
        {{ $slot }}
    </div>
    @isset($actions)
        <div class="page-header-actions d-flex flex-wrap gap-2">{{ $actions }}</div>
    @endisset
</div>
```

`resources/views/components/empty-state.blade.php`:

```blade
@props(['title', 'message' => null, 'icon' => 'bi-inbox'])
<div {{ $attributes->merge(['class' => 'empty-state', 'role' => 'status']) }}>
    <i class="bi {{ $icon }} empty-state-icon d-block mb-2" aria-hidden="true"></i>
    <p class="h5 fw-semibold mb-1">{{ $title }}</p>
    @if ($message)
        <p class="mb-3">{{ $message }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="d-flex flex-wrap justify-content-center gap-2">{{ $slot }}</div>
    @endif
</div>
```

- [ ] **Step 4: Jalankan tes — harus lulus; pint; commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=NavigationComponentsTest
& $PHP vendor\bin\pint --test
git add resources/views/components/breadcrumb.blade.php resources/views/components/page-header.blade.php resources/views/components/empty-state.blade.php tests/Feature/View/NavigationComponentsTest.php
git commit -m "feat(fase-1): komponen breadcrumb, page-header, dan empty-state"
```
Expected: PASS (10 tes); pint `PASS`.

#### Siklus B — umpan balik: `flash-alert`, `loading-button`, `stat-card`

- [ ] **Step 5: Tulis tes yang gagal — `tests/Feature/View/FeedbackComponentsTest.php`**

```php
<?php

namespace Tests\Feature\View;

use Tests\TestCase;

class FeedbackComponentsTest extends TestCase
{
    public function test_flash_alert_renders_nothing_without_flash_messages(): void
    {
        $this->blade('<x-flash-alert />')
            ->assertDontSee('alert', false);
    }

    public function test_flash_alert_success_is_green_with_status_role(): void
    {
        session()->flash('success', 'Dokter berhasil ditambahkan.');

        $this->blade('<x-flash-alert />')
            ->assertSee('alert alert-success', false)
            ->assertSee('role="status"', false)
            ->assertSee('<div class="flex-grow-1">Dokter berhasil ditambahkan.</div>', false)
            ->assertSee('data-bs-dismiss="alert"', false)
            ->assertSee('aria-label="Tutup"', false)
            ->assertDontSee('role="alert"', false);
    }

    public function test_flash_alert_error_and_warning_use_alert_role(): void
    {
        session()->flash('error', 'Terlalu banyak percobaan.');
        session()->flash('warning', '3 sesi perlu ditindaklanjuti.');

        $this->blade('<x-flash-alert />')
            ->assertSee('alert alert-danger', false)
            ->assertSee('alert alert-warning', false)
            ->assertSee('role="alert"', false)
            ->assertSeeInOrder(['Terlalu banyak percobaan.', '3 sesi perlu ditindaklanjuti.'])
            ->assertDontSee('role="status"', false);
    }

    public function test_flash_alert_only_renders_requested_keys(): void
    {
        session()->flash('success', 'Berhasil.');
        session()->flash('error', 'Gagal.');

        $this->blade('<x-flash-alert :keys="[\'error\']" />')
            ->assertSee('Gagal.')
            ->assertDontSee('Berhasil.');
    }

    public function test_flash_alert_ignores_unknown_keys_and_non_string_values(): void
    {
        session()->flash('info', 'Tidak dikenal.');
        session()->flash('success', ['bukan', 'string']);

        $this->blade('<x-flash-alert :keys="[\'info\', \'success\']" />')
            ->assertDontSee('Tidak dikenal.')
            ->assertDontSee('alert alert-success', false);
    }

    public function test_flash_alert_escapes_messages(): void
    {
        session()->flash('error', '<script>alert(1)</script>');

        $this->blade('<x-flash-alert />')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_loading_button_exposes_attributes_used_by_app_js(): void
    {
        $this->blade('<x-loading-button>Simpan</x-loading-button>')
            ->assertSee('type="submit"', false)
            ->assertSee('class="btn btn-primary"', false)
            ->assertSee('data-loading-button', false)
            ->assertSee('data-loading-text="Memproses…"', false)
            ->assertSee('class="spinner-border spinner-border-sm d-none"', false)
            ->assertSee('role="status"', false)
            ->assertSee('<span class="visually-hidden">Memproses…</span>', false)
            ->assertSee('<span data-loading-label>Simpan</span>', false)
            ->assertDontSee('data-loading-icon', false);
    }

    public function test_loading_button_supports_variant_text_icon_type_and_extra_attributes(): void
    {
        $this->blade('<x-loading-button type="button" variant="danger" loading-text="Menghapus…" icon="bi-trash" class="w-100" id="hapus">Hapus</x-loading-button>')
            ->assertSee('type="button"', false)
            ->assertSee('class="btn btn-danger w-100"', false)
            ->assertSee('id="hapus"', false)
            ->assertSee('data-loading-text="Menghapus…"', false)
            ->assertSee('<i class="bi bi-trash" aria-hidden="true" data-loading-icon></i>', false)
            ->assertSee('<span data-loading-label>Hapus</span>', false);
    }

    public function test_loading_button_escapes_loading_text(): void
    {
        $this->blade('<x-loading-button :loading-text="$text">Kirim</x-loading-button>', ['text' => '"><script>alert(1)</script>'])
            ->assertSee('data-loading-text="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_stat_card_renders_label_value_hint_and_icon(): void
    {
        $this->blade('<x-stat-card label="Dokter Aktif" :value="42" icon="bi-person-badge" variant="success" hint="Per hari ini" />')
            ->assertSee('id="stat-dokter-aktif-label"', false)
            ->assertSee('aria-labelledby="stat-dokter-aktif-label"', false)
            ->assertSee('Dokter Aktif')
            ->assertSee('>42</p>', false)
            ->assertSee('Per hari ini')
            ->assertSee('text-bg-success', false)
            ->assertSee('<i class="bi bi-person-badge" aria-hidden="true"></i>', false)
            ->assertDontSee('stretched-link', false);
    }

    public function test_stat_card_with_href_makes_the_label_a_link(): void
    {
        $this->blade('<x-stat-card label="Pendaftaran Hari Ini" value="7" href="/admin/pendaftaran" />')
            ->assertSee('<a href="/admin/pendaftaran" class="stretched-link text-decoration-none text-reset">Pendaftaran Hari Ini</a>', false)
            ->assertDontSee('text-bg-', false);
    }

    public function test_stat_card_falls_back_to_primary_for_unknown_variant(): void
    {
        $this->blade('<x-stat-card label="Pasien" value="1" icon="bi-people" variant="neon" />')
            ->assertSee('text-bg-primary', false)
            ->assertDontSee('text-bg-neon', false);
    }

    public function test_stat_card_escapes_all_props(): void
    {
        $this->blade('<x-stat-card :label="$p" :value="$p" :hint="$p" href="/x" />', ['p' => '<script>alert(1)</script>'])
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }
}
```

- [ ] **Step 6: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=FeedbackComponentsTest
```
Expected: FAIL — `InvalidArgumentException: Unable to locate a class or view for component [flash-alert].` (juga `[loading-button]` dan `[stat-card]`).

- [ ] **Step 7: Implementasi tiga komponen (tool Write)**

`resources/views/components/flash-alert.blade.php`:

```blade
@props(['keys' => ['success', 'error', 'warning']])
@php
    $variants = [
        'success' => ['class' => 'alert-success', 'role' => 'status', 'icon' => 'bi-check-circle-fill'],
        'error' => ['class' => 'alert-danger', 'role' => 'alert', 'icon' => 'bi-x-octagon-fill'],
        'warning' => ['class' => 'alert-warning', 'role' => 'alert', 'icon' => 'bi-exclamation-triangle-fill'],
    ];
@endphp
@foreach ($keys as $key)
    @if (isset($variants[$key]) && is_string($message = session($key)) && $message !== '')
        <div class="alert {{ $variants[$key]['class'] }} d-flex align-items-start gap-2 fade show" role="{{ $variants[$key]['role'] }}" data-flash="{{ $key }}">
            <i class="bi {{ $variants[$key]['icon'] }} flex-shrink-0" aria-hidden="true"></i>
            <div class="flex-grow-1">{{ $message }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
    @endif
@endforeach
```

`resources/views/components/loading-button.blade.php`:

```blade
@props(['type' => 'submit', 'variant' => 'primary', 'loadingText' => 'Memproses…', 'icon' => null])
<button type="{{ $type }}" {{ $attributes->merge(['class' => 'btn btn-'.$variant]) }} data-loading-button data-loading-text="{{ $loadingText }}">
    <span class="spinner-border spinner-border-sm d-none" role="status" data-loading-spinner><span class="visually-hidden">Memproses…</span></span>
    @if ($icon)<i class="bi {{ $icon }}" aria-hidden="true" data-loading-icon></i>@endif
    <span data-loading-label>{{ $slot }}</span>
</button>
```

`resources/views/components/stat-card.blade.php`:

```blade
@props(['label', 'value', 'icon' => null, 'variant' => 'primary', 'hint' => null, 'href' => null])
@php
    $variant = in_array($variant, ['primary', 'success', 'info', 'warning'], true) ? $variant : 'primary';
    $labelId = 'stat-'.\Illuminate\Support\Str::slug((string) $label);
@endphp
<div {{ $attributes->merge(['class' => 'card stat-card border-0 shadow-sm position-relative']) }}>
    <div class="card-body d-flex align-items-start gap-3">
        @if ($icon)
            <span class="text-bg-{{ $variant }} rounded-3 d-inline-flex align-items-center justify-content-center flex-shrink-0 p-3 fs-4"><i class="bi {{ $icon }}" aria-hidden="true"></i></span>
        @endif
        <div class="flex-grow-1">
            <p id="{{ $labelId }}-label" class="mb-1 text-secondary fw-semibold">@if ($href)<a href="{{ $href }}" class="stretched-link text-decoration-none text-reset">{{ $label }}</a>@else{{ $label }}@endif</p>
            <p class="stat-card-value mb-0" aria-labelledby="{{ $labelId }}-label">{{ $value }}</p>
            @if ($hint)
                <p class="small text-secondary mb-0 mt-1">{{ $hint }}</p>
            @endif
        </div>
    </div>
</div>
```

- [ ] **Step 8: Jalankan tes — harus lulus; pint; commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=FeedbackComponentsTest
& $PHP vendor\bin\pint --test
git add resources/views/components/flash-alert.blade.php resources/views/components/loading-button.blade.php resources/views/components/stat-card.blade.php tests/Feature/View/FeedbackComponentsTest.php
git commit -m "feat(fase-1): komponen flash-alert, loading-button, dan stat-card"
```
Expected: PASS (13 tes); pint `PASS`.

#### Siklus C — `status-badge` (komponen kelas)

- [ ] **Step 9: Tulis fixture enum dan tes yang gagal**

`tests/Support/Fixtures/PlainEnum.php`:

```php
<?php

namespace Tests\Support\Fixtures;

enum PlainEnum: string
{
    case Plain = 'plain';
}
```

`tests/Support/Fixtures/HostileStatus.php`:

```php
<?php

namespace Tests\Support\Fixtures;

enum HostileStatus: string
{
    case Bad = 'bad';

    public function label(): string
    {
        return '<script>alert(1)</script>';
    }

    public function color(): string
    {
        return 'danger';
    }

    public function icon(): string
    {
        return 'bi-x-circle';
    }
}
```

`tests/Feature/View/StatusBadgeComponentTest.php`:

```php
<?php

namespace Tests\Feature\View;

use App\Enums\ActorType;
use App\Enums\AppointmentStatus;
use App\Enums\ArticleStatus;
use App\Enums\Availability;
use App\Enums\BookingChannel;
use App\Enums\ContactMessageStatus;
use App\Enums\DoctorStatus;
use App\Enums\ExceptionType;
use App\Enums\PaymentType;
use App\Enums\SessionResolution;
use App\Enums\SessionStatus;
use App\View\Components\StatusBadge;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Fixtures\HostileStatus;
use Tests\Support\Fixtures\PlainEnum;
use Tests\TestCase;

class StatusBadgeComponentTest extends TestCase
{
    /** @return array<string, array{AppointmentStatus, string, string, string}> */
    public static function appointmentBadges(): array
    {
        return [
            'pending' => [AppointmentStatus::Pending, 'warning', 'bi-hourglass-split', 'Menunggu konfirmasi'],
            'confirmed' => [AppointmentStatus::Confirmed, 'primary', 'bi-check-circle', 'Dikonfirmasi'],
            'checked_in' => [AppointmentStatus::CheckedIn, 'info', 'bi-person-check', 'Hadir/check-in'],
            'completed' => [AppointmentStatus::Completed, 'success', 'bi-check2-all', 'Selesai'],
            'cancelled' => [AppointmentStatus::Cancelled, 'secondary', 'bi-x-circle', 'Dibatalkan'],
            'no_show' => [AppointmentStatus::NoShow, 'danger', 'bi-person-x', 'Tidak hadir'],
        ];
    }

    #[DataProvider('appointmentBadges')]
    public function test_appointment_status_badge_shows_color_icon_and_text(AppointmentStatus $status, string $color, string $icon, string $label): void
    {
        $this->blade('<x-status-badge :status="$status" />', ['status' => $status])
            ->assertSee('<span class="badge text-bg-'.$color.'">', false)
            ->assertSee('<i class="bi '.$icon.'" aria-hidden="true"></i> '.$label.'</span>', false);
    }

    public function test_every_badge_enum_case_renders_its_own_label_color_and_icon(): void
    {
        $enums = [
            AppointmentStatus::class, PaymentType::class, BookingChannel::class, SessionStatus::class,
            ExceptionType::class, DoctorStatus::class, ArticleStatus::class, ActorType::class,
            ContactMessageStatus::class, SessionResolution::class, Availability::class,
        ];

        foreach ($enums as $enum) {
            foreach ($enum::cases() as $case) {
                $this->blade('<x-status-badge :status="$status" />', ['status' => $case])
                    ->assertSee('text-bg-'.$case->color(), false)
                    ->assertSee('<i class="bi '.$case->icon().'" aria-hidden="true"></i>', false)
                    ->assertSee($case->label());
            }
        }
    }

    public function test_extra_attributes_are_merged_after_the_badge_classes(): void
    {
        $this->blade('<x-status-badge :status="$status" class="ms-2" id="status-1" />', ['status' => DoctorStatus::Active])
            ->assertSee('class="badge text-bg-success ms-2"', false)
            ->assertSee('id="status-1"', false);
    }

    public function test_labels_are_escaped(): void
    {
        $this->blade('<x-status-badge :status="$status" />', ['status' => HostileStatus::Bad])
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_enum_without_label_color_and_icon_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('label()');

        $this->component(StatusBadge::class, ['status' => PlainEnum::Plain]);
    }
}
```

- [ ] **Step 10: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=StatusBadgeComponentTest
```
Expected: FAIL — `Class "App\View\Components\StatusBadge" not found` pada tes penolakan, dan `Unable to locate a class or view for component [status-badge]` pada tes render.

- [ ] **Step 11: Implementasi `StatusBadge` (tool Write)**

`app/View/Components/StatusBadge.php`:

```php
<?php

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use InvalidArgumentException;
use UnitEnum;

class StatusBadge extends Component
{
    public string $label;

    public string $color;

    public string $icon;

    public function __construct(public UnitEnum $status)
    {
        foreach (['label', 'color', 'icon'] as $method) {
            if (! method_exists($status, $method)) {
                throw new InvalidArgumentException(sprintf(
                    'Enum %s tidak dapat dipakai pada status-badge: method %s() tidak ada.',
                    $status::class,
                    $method,
                ));
            }
        }

        $this->label = $status->label();
        $this->color = $status->color();
        $this->icon = $status->icon();
    }

    public function render(): View
    {
        return view('components.status-badge');
    }
}
```

`resources/views/components/status-badge.blade.php` (satu baris agar spasi tepat):

```blade
<span {{ $attributes->merge(['class' => 'badge text-bg-'.$color]) }}><i class="bi {{ $icon }}" aria-hidden="true"></i> {{ $label }}</span>
```

- [ ] **Step 12: Jalankan tes — harus lulus; pint; commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=StatusBadgeComponentTest
& $PHP vendor\bin\pint --test
git add app/View/Components/StatusBadge.php resources/views/components/status-badge.blade.php tests/Support/Fixtures/PlainEnum.php tests/Support/Fixtures/HostileStatus.php tests/Feature/View/StatusBadgeComponentTest.php
git commit -m "feat(fase-1): komponen status-badge untuk enum badge"
```
Expected: PASS (10 tes: 6 data provider + 4); pint `PASS`. Bila pint melaporkan urutan `use`, jalankan `& $PHP vendor\bin\pint tests/Feature/View/StatusBadgeComponentTest.php` lalu ulangi `--test`.

#### Siklus D — `image` (komponen kelas, MED-08 otomatis)

- [ ] **Step 13: Tulis tes yang gagal — `tests/Feature/View/ImageComponentTest.php`**

```php
<?php

namespace Tests\Feature\View;

use App\Models\Media;
use App\View\Components\Image;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ImageComponentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    /** @param array<string, mixed> $overrides */
    private function makeMedia(array $overrides = []): Media
    {
        return Media::create(array_merge([
            'model_type' => null,
            'model_id' => null,
            'collection' => 'photo',
            'disk' => 'public',
            'directory' => 'photo/2026/10',
            'file_name' => Str::uuid()->toString().'.jpg',
            'original_name' => 'foto-dokter.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size_bytes' => 150000,
            'width' => 1200,
            'height' => 800,
            'thumb_path' => 'photo/2026/10/thumbs/foto.jpg',
            'alt_text' => 'Foto dr. Andi',
            'caption' => null,
            'sort_order' => 1,
            'uploaded_by' => null,
        ], $overrides))->fresh();
    }

    /** @return array<string, array{string}> */
    public static function imageTypes(): array
    {
        return array_combine(Image::TYPES, array_map(fn (string $type): array => [$type], Image::TYPES));
    }

    #[DataProvider('imageTypes')]
    public function test_missing_media_renders_the_local_placeholder_of_the_type(string $type): void
    {
        $placeholder = asset('images/placeholders/'.$type.'.svg');

        $this->blade('<x-image :type="$type" alt="Gambar contoh" />', ['type' => $type])
            ->assertSee('src="'.$placeholder.'"', false)
            ->assertSee('alt="Gambar contoh"', false)
            ->assertSee('loading="lazy"', false)
            ->assertSee('decoding="async"', false)
            ->assertDontSee('data-fallback', false)
            ->assertDontSee('onerror', false);
    }

    public function test_media_is_rendered_with_data_fallback_dimensions_and_default_alt(): void
    {
        $media = $this->makeMedia();

        $this->blade('<x-image :media="$media" type="doctor" />', ['media' => $media])
            ->assertSee('src="'.$media->url.'"', false)
            ->assertSee('data-fallback="'.asset('images/placeholders/doctor.svg').'"', false)
            ->assertSee('alt="Foto dr. Andi"', false)
            ->assertSee('width="1200"', false)
            ->assertSee('height="800"', false)
            ->assertSee('loading="lazy"', false)
            ->assertSee('decoding="async"', false)
            ->assertDontSee('onerror', false);
    }

    public function test_explicit_alt_overrides_the_media_alt_text(): void
    {
        $media = $this->makeMedia();

        $this->blade('<x-image :media="$media" type="doctor" alt="dr. Andi Wijaya, Sp.PD" />', ['media' => $media])
            ->assertSee('alt="dr. Andi Wijaya, Sp.PD"', false)
            ->assertDontSee('alt="Foto dr. Andi"', false);
    }

    public function test_thumb_variant_uses_the_thumbnail_url_and_scaled_dimensions(): void
    {
        $media = $this->makeMedia();

        $this->blade('<x-image :media="$media" type="doctor" variant="thumb" />', ['media' => $media])
            ->assertSee('src="'.$media->thumb_url.'"', false)
            ->assertSee('width="400"', false)
            ->assertSee('height="267"', false);
    }

    public function test_thumb_variant_without_thumbnail_falls_back_to_the_full_image(): void
    {
        $media = $this->makeMedia(['thumb_path' => null]);

        $this->blade('<x-image :media="$media" type="doctor" variant="thumb" />', ['media' => $media])
            ->assertSee('src="'.$media->url.'"', false)
            ->assertSee('width="1200"', false)
            ->assertSee('height="800"', false);
    }

    public function test_private_disk_media_has_no_public_url_so_the_placeholder_is_used(): void
    {
        $media = $this->makeMedia(['disk' => 'local']);

        $this->blade('<x-image :media="$media" type="clinic" alt="Poliklinik" />', ['media' => $media])
            ->assertSee('src="'.asset('images/placeholders/clinic.svg').'"', false)
            ->assertDontSee('data-fallback', false);
    }

    public function test_src_without_media_gets_the_placeholder_as_fallback(): void
    {
        $this->blade('<x-image type="banner" src="/storage/banner/lama.jpg" alt="Banner promo" />')
            ->assertSee('src="/storage/banner/lama.jpg"', false)
            ->assertSee('data-fallback="'.asset('images/placeholders/banner.svg').'"', false);
    }

    public function test_media_takes_precedence_over_src(): void
    {
        $media = $this->makeMedia();

        $this->blade('<x-image :media="$media" src="/lain.jpg" type="doctor" />', ['media' => $media])
            ->assertSee('src="'.$media->url.'"', false)
            ->assertDontSee('/lain.jpg', false);
    }

    public function test_decorative_image_has_empty_alt_and_presentation_role(): void
    {
        $this->blade('<x-image type="gallery" decorative />')
            ->assertSee('alt=""', false)
            ->assertSee('role="presentation"', false);
    }

    public function test_ratio_wraps_the_image_in_a_bootstrap_ratio_frame(): void
    {
        $this->blade('<x-image type="article" alt="Sampul artikel" ratio="16x9" />')
            ->assertSee('class="image-frame ratio ratio-16x9"', false)
            ->assertSee('object-fit-cover', false)
            ->assertDontSee('img-fluid', false);
    }

    public function test_without_ratio_the_image_is_fluid_and_unwrapped(): void
    {
        $this->blade('<x-image type="article" alt="Sampul artikel" />')
            ->assertSee('class="img-fluid"', false)
            ->assertDontSee('image-frame', false);
    }

    public function test_lazy_can_be_disabled(): void
    {
        $this->blade('<x-image type="logo" alt="Logo" :lazy="false" />')
            ->assertDontSee('loading="lazy"', false)
            ->assertSee('decoding="async"', false);
    }

    public function test_extra_attributes_are_merged_into_the_image(): void
    {
        $this->blade('<x-image type="doctor" alt="Foto" class="rounded-circle" id="foto-1" data-x="1" />')
            ->assertSee('class="img-fluid rounded-circle"', false)
            ->assertSee('id="foto-1"', false)
            ->assertSee('data-x="1"', false);
    }

    public function test_alt_and_src_are_escaped(): void
    {
        $this->blade('<x-image type="doctor" :alt="$payload" :src="$payload" />', ['payload' => '"><script>alert(1)</script>'])
            ->assertSee('alt="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"', false)
            ->assertSee('src="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_unknown_type_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->component(Image::class, ['type' => 'avatar', 'alt' => 'x']);
    }

    public function test_unknown_variant_and_ratio_are_rejected(): void
    {
        try {
            $this->component(Image::class, ['type' => 'doctor', 'alt' => 'x', 'variant' => 'huge']);
            $this->fail('variant tidak dikenal harus ditolak');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(InvalidArgumentException::class);

        $this->component(Image::class, ['type' => 'doctor', 'alt' => 'x', 'ratio' => '5x7']);
    }

    public function test_non_decorative_image_requires_alt_text(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('alt');

        $this->component(Image::class, ['type' => 'doctor']);
    }

    public function test_component_sources_contain_no_inline_event_handlers(): void
    {
        foreach (['resources/views/components/image.blade.php', 'app/View/Components/Image.php'] as $file) {
            $this->assertStringNotContainsString('onerror', strtolower(file_get_contents(base_path($file))), $file);
        }
    }
}
```

- [ ] **Step 14: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=ImageComponentTest
```
Expected: FAIL — `Class "App\View\Components\Image" not found` (provider `imageTypes` dan tes penolakan) serta `Unable to locate a class or view for component [image]`; `test_component_sources_contain_no_inline_event_handlers` gagal `Failed to open stream` karena berkas belum ada.

- [ ] **Step 15: Implementasi kelas `Image` (tool Write)**

`app/View/Components/Image.php`:

```php
<?php

namespace App\View\Components;

use App\Models\Media;
use App\Services\Media\MediaService;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use InvalidArgumentException;

class Image extends Component
{
    public const TYPES = ['doctor', 'clinic', 'facility', 'room', 'article', 'banner', 'gallery', 'logo'];

    public const VARIANTS = ['full', 'thumb'];

    /** Rasio yang tersedia di SCSS ($aspect-ratios pada _variables.scss). */
    public const RATIOS = ['1x1', '4x3', '16x9', '21x9', '3x1'];

    public string $imageSrc;

    public ?string $fallbackSrc = null;

    public string $altText;

    public ?int $imageWidth = null;

    public ?int $imageHeight = null;

    public function __construct(
        public string $type,
        public ?Media $media = null,
        public ?string $src = null,
        string $alt = '',
        public string $variant = 'full',
        public ?string $ratio = null,
        public bool $lazy = true,
        public bool $decorative = false,
    ) {
        if (! in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException('Tipe gambar tidak dikenal: '.$type);
        }

        if (! in_array($variant, self::VARIANTS, true)) {
            throw new InvalidArgumentException('Varian gambar tidak dikenal: '.$variant);
        }

        if ($ratio !== null && ! in_array($ratio, self::RATIOS, true)) {
            throw new InvalidArgumentException('Rasio gambar tidak dikenal: '.$ratio);
        }

        $this->altText = $decorative ? '' : ($alt !== '' ? $alt : (string) ($media?->alt_text ?? ''));

        if (! $decorative && $this->altText === '') {
            throw new InvalidArgumentException('Atribut alt wajib diisi pada x-image (atau gunakan decorative).');
        }

        $placeholder = asset('images/placeholders/'.$type.'.svg');
        $mediaUrl = $media === null ? null : ($variant === 'thumb' ? $media->thumb_url : $media->url);

        if ($mediaUrl !== null && $mediaUrl !== '') {
            $this->imageSrc = $mediaUrl;
            $this->fallbackSrc = $placeholder;
            $this->resolveDimensions($media, $variant);
        } elseif ($src !== null && $src !== '') {
            $this->imageSrc = $src;
            $this->fallbackSrc = $placeholder;
        } else {
            $this->imageSrc = $placeholder;
        }
    }

    public function render(): View
    {
        return view('components.image');
    }

    private function resolveDimensions(Media $media, string $variant): void
    {
        if (! $media->width || ! $media->height) {
            return;
        }

        if ($variant === 'thumb' && $media->thumb_path !== null) {
            $width = min(MediaService::THUMB_WIDTH, $media->width);
            $this->imageWidth = $width;
            $this->imageHeight = (int) round($media->height * $width / $media->width);

            return;
        }

        $this->imageWidth = $media->width;
        $this->imageHeight = $media->height;
    }
}
```

`resources/views/components/image.blade.php`:

```blade
@if ($ratio)<div class="image-frame ratio ratio-{{ $ratio }}">@endif
<img {{ $attributes->merge(['class' => $ratio ? 'object-fit-cover' : 'img-fluid']) }} src="{{ $imageSrc }}" alt="{{ $altText }}"@if ($decorative) role="presentation"@endif @if ($fallbackSrc) data-fallback="{{ $fallbackSrc }}"@endif @if ($lazy) loading="lazy"@endif decoding="async"@if ($imageWidth && $imageHeight) width="{{ $imageWidth }}" height="{{ $imageHeight }}"@endif>
@if ($ratio)</div>@endif
```

- [ ] **Step 16: Jalankan tes — harus lulus; pint; commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=ImageComponentTest
& $PHP vendor\bin\pint --test
git add app/View/Components/Image.php resources/views/components/image.blade.php tests/Feature/View/ImageComponentTest.php
git commit -m "feat(fase-1): komponen image dengan placeholder dan data-fallback tanpa atribut inline"
```
Expected: PASS (25 tes: 8 data provider + 17); pint `PASS`.

- [ ] **Step 17: Jalankan seluruh tes tampilan dan suite penuh**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="NavigationComponentsTest|FeedbackComponentsTest|StatusBadgeComponentTest|ImageComponentTest"
& $PHP artisan test
```
Expected: semua PASS (butuh Apache + MySQL menyala di `C:\xampp3\xampp-control.exe` karena `ImageComponentTest` memakai `RefreshDatabase`).

---

### Task 11: Komponen form, `data-table`, `confirm-modal`, paginasi Indonesia

**Files:**
- Create: `resources/views/components/form/{input,select,textarea,checkbox,radio-group,file,errors-summary}.blade.php`, `resources/views/components/data-table.blade.php`, `resources/views/components/confirm-modal.blade.php`, `resources/views/vendor/pagination/bootstrap-5.blade.php`, `resources/views/vendor/pagination/simple-bootstrap-5.blade.php`, `tests/Support/InteractsWithFormState.php`
- Modify: `app/Providers/AppServiceProvider.php` (`Paginator::useBootstrapFive()`)
- Test: `tests/Feature/View/FormComponentsTest.php`, `tests/Feature/View/FormChoiceComponentsTest.php`, `tests/Feature/View/FormFileComponentTest.php`, `tests/Feature/View/DataTableComponentTest.php`, `tests/Feature/View/ConfirmModalComponentTest.php` (kerangka menyebut dua berkas; dipecah per kelompok komponen agar tiap siklus TDD menulis berkasnya sendiri)

**Interfaces:**
- Consumes: kontrak `data-*` JS (Task 9), `x-empty-state` + `x-loading-button` + `x-image` (Task 10), `PaymentType::options()` (Task 1), `Media` (Task 5, hanya untuk `form.file` dengan `currentMedia`).
- Produces (props persis 10 §3.5–3.6): `x-form.input|select|textarea|checkbox|radio-group|file|errors-summary`, `x-data-table`, `x-confirm-modal`, view paginasi `pagination::bootstrap-5` dan `pagination::simple-bootstrap-5` berbahasa Indonesia, `Paginator::useBootstrapFive()` aktif sebagai default.
- Aturan bersama semua `form.*`: `name`, `label`, `id`, `help`, `required`. **`id` default** dari `name`: titik dan kurung siku menjadi `-` (`items[0][name]` → `items-0-name`, `schedule.start` → `schedule-start`) — satu aturan sehingga tautan `form.errors-summary` (`#items-0-name`, dari kunci galat `items.0.name`) selalu cocok dengan `id` kontrol. Nilai = `old(kunci)` lalu `value`; galat = `$errors->first(kunci)` di `#{id}-error.invalid-feedback.d-block`, kontrol `is-invalid`, `aria-invalid="true"`, `aria-describedby="{id}-help {id}-error"` (hanya id yang ada); wajib = `*` (`aria-hidden`) + `aria-required="true"` (tanpa atribut `required` HTML agar validasi server yang berbicara); `type=password` tidak pernah diisi ulang. Komponen toleran bila `$errors` tidak dibagikan (mis. halaman galat).

**Keputusan teknis (diverifikasi pada `vendor/laravel/framework` 12.69):**
- Tes memakai `$this->blade()` dan `$this->withViewErrors([...])` (`InteractsWithViews`: `ViewFacade::share('errors', ViewErrorBag)`), jadi `$errors` tersedia sebagai variabel bersama. `old()` membaca `request()->session()`; pada tes tanpa HTTP request sesi belum terpasang sehingga trait tes `InteractsWithFormState::withOldInput()` memasang `session.store` ke request lalu mengisi `_old_input`.
- Direktif Blade dikenali regex `\B@`: `@endif`/`@if` **tidak boleh menempel langsung setelah huruf/angka** (mis. `checked@endif` diam-diam tidak terkompilasi dan berujung `ParseError ... endforeach`). Karena itu atribut boolean memakai `@checked(...)`/`@selected(...)`, dan `multiple` ditulis `@if ($multiple) multiple @endif` (diapit spasi). `@php(...)` satu baris dengan tanda kurung bersarang di dalam `@foreach` juga dihindari; dipakai blok `@php … @endphp`.
- `ComponentAttributeBag::class([...])` (= `merge(['class' => Arr::toCssClasses(...)])`) dipakai agar `is-invalid` bersyarat tergabung dengan `class` dari pemakai.
- `Paginator::useBootstrapFive()` mengatur `Paginator::$defaultView = 'pagination::bootstrap-5'`; view yang dipublikasikan di `resources/views/vendor/pagination/` menimpa view bawaan (namespace `pagination` mendaftarkan folder `vendor/pagination` aplikasi). Berkas ini disalin dari `vendor/laravel/framework/src/Illuminate/Pagination/resources/views/{bootstrap-5,simple-bootstrap-5}.blade.php` (sama dengan hasil `php artisan vendor:publish --tag=laravel-pagination`, tetapi hanya dua berkas yang dipakai) lalu diterjemahkan; `{!! __('…') !!}` bawaan diganti teks Indonesia ber-`{{ }}`/statis karena aturan "tanpa `{!! !!}`" dan karena `lang/id/pagination.php` memuat entitas HTML (`&laquo;`).
- Pada tes: `route()` hanya dipakai di komponen lewat `url()->current()`; request dapat diganti dengan `$this->app->instance('request', Request::create('/admin/dokter?q=andi'))` (menimbulkan *rebinding* ke `UrlGenerator`), dan `LengthAwarePaginator` dibuat manual tanpa DB.

#### Siklus A — `form.input` dan `form.errors-summary`

- [ ] **Step 1: Tulis trait tes `tests/Support/InteractsWithFormState.php`**

```php
<?php

namespace Tests\Support;

trait InteractsWithFormState
{
    /**
     * Mensimulasikan input lama (hasil flash) agar helper old() di komponen Blade membacanya.
     * old() hanya membaca sesi bila request memiliki sesi; pada tes view tanpa HTTP, sesi dipasang manual.
     *
     * @param  array<string, mixed>  $input
     */
    protected function withOldInput(array $input): void
    {
        $session = $this->app['session.store'];

        $this->app['request']->setLaravelSession($session);
        $session->put('_old_input', $input);
    }
}
```

- [ ] **Step 2: Tulis tes yang gagal — `tests/Feature/View/FormComponentsTest.php`**

```php
<?php

namespace Tests\Feature\View;

use Tests\Support\InteractsWithFormState;
use Tests\TestCase;

class FormComponentsTest extends TestCase
{
    use InteractsWithFormState;

    public function test_input_renders_label_control_and_required_marker(): void
    {
        $this->blade('<x-form.input name="email" label="Email" type="email" required autocomplete="email" inputmode="email" maxlength="150" placeholder="nama@contoh.test" />')
            ->assertSee('<label for="email" class="form-label fw-semibold">Email <span class="required-mark" aria-hidden="true">*</span></label>', false)
            ->assertSee('type="email"', false)
            ->assertSee('id="email"', false)
            ->assertSee('name="email"', false)
            ->assertSee('autocomplete="email"', false)
            ->assertSee('inputmode="email"', false)
            ->assertSee('maxlength="150"', false)
            ->assertSee('placeholder="nama@contoh.test"', false)
            ->assertSee('aria-required="true"', false)
            ->assertSee('class="form-control"', false)
            ->assertDontSee('aria-invalid', false)
            ->assertDontSee('aria-describedby', false)
            ->assertDontSee('is-invalid', false)
            ->assertDontSee('value=', false);
    }

    public function test_input_without_required_has_no_marker_or_aria_required(): void
    {
        $this->blade('<x-form.input name="phone" label="Telepon" />')
            ->assertSee('Telepon</label>', false)
            ->assertDontSee('required-mark', false)
            ->assertDontSee('aria-required', false)
            ->assertSee('type="text"', false);
    }

    public function test_input_with_error_exposes_invalid_state_and_links_help_and_error(): void
    {
        $this->withViewErrors(['email' => ['Format email tidak valid.']]);

        $this->blade('<x-form.input name="email" label="Email" help="Gunakan email aktif." />')
            ->assertSee('class="form-control is-invalid"', false)
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('aria-describedby="email-help email-error"', false)
            ->assertSee('<div id="email-help" class="form-text">Gunakan email aktif.</div>', false)
            ->assertSee('<div id="email-error" class="invalid-feedback d-block">Format email tidak valid.</div>', false);
    }

    public function test_input_describedby_lists_only_existing_parts(): void
    {
        $this->blade('<x-form.input name="email" label="Email" help="Bantuan." />')
            ->assertSee('aria-describedby="email-help"', false)
            ->assertDontSee('email-error', false);

        $this->withViewErrors(['email' => ['Wajib diisi.']]);

        $this->blade('<x-form.input name="email" label="Email" />')
            ->assertSee('aria-describedby="email-error"', false)
            ->assertDontSee('email-help', false);
    }

    public function test_input_only_shows_the_error_of_its_own_field(): void
    {
        $this->withViewErrors(['password' => ['Password wajib diisi.']]);

        $this->blade('<x-form.input name="email" label="Email" />')
            ->assertDontSee('Password wajib diisi.')
            ->assertDontSee('is-invalid', false);
    }

    public function test_input_uses_value_prop_and_old_input_takes_precedence(): void
    {
        $this->blade('<x-form.input name="email" label="Email" value="asli@contoh.test" />')
            ->assertSee('value="asli@contoh.test"', false);

        $this->withOldInput(['email' => 'lama@contoh.test']);

        $this->blade('<x-form.input name="email" label="Email" value="asli@contoh.test" />')
            ->assertSee('value="lama@contoh.test"', false)
            ->assertDontSee('asli@contoh.test');
    }

    public function test_password_input_is_never_refilled(): void
    {
        $this->withOldInput(['password' => 'rahasia123']);

        $this->blade('<x-form.input name="password" label="Password" type="password" value="nilai-prop" autocomplete="current-password" />')
            ->assertSee('type="password"', false)
            ->assertSee('autocomplete="current-password"', false)
            ->assertDontSee('rahasia123')
            ->assertDontSee('nilai-prop')
            ->assertDontSee('value=', false);
    }

    public function test_input_escapes_label_help_value_placeholder_and_error(): void
    {
        $this->withViewErrors(['nama' => ['<script>alert(1)</script>']]);

        $this->blade(
            '<x-form.input name="nama" :label="$p" :help="$p" :value="$p" :placeholder="$p" />',
            ['p' => '"><script>alert(1)</script>'],
        )
            ->assertSee('&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_input_derives_id_from_dotted_and_bracketed_names(): void
    {
        $this->withViewErrors(['items.0.name' => ['Nama wajib diisi.']]);

        $this->blade('<x-form.input name="items[0][name]" label="Nama item" />')
            ->assertSee('id="items-0-name"', false)
            ->assertSee('for="items-0-name"', false)
            ->assertSee('name="items[0][name]"', false)
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('<div id="items-0-name-error" class="invalid-feedback d-block">Nama wajib diisi.</div>', false);

        $this->blade('<x-form.input name="schedule.start" label="Mulai" />')
            ->assertSee('id="schedule-start"', false);

        $this->blade('<x-form.input name="email" label="Email" id="kustom" />')
            ->assertSee('for="kustom"', false)
            ->assertSee('id="kustom"', false);
    }

    public function test_input_reads_old_input_for_bracketed_names(): void
    {
        $this->withOldInput(['items' => [['name' => 'Budi']]]);

        $this->blade('<x-form.input name="items[0][name]" label="Nama item" />')
            ->assertSee('value="Budi"', false);
    }

    public function test_input_supports_prepend_and_append_slots(): void
    {
        $this->blade(<<<'BLADE'
            <x-form.input name="phone" label="Telepon">
                <x-slot:prepend><span class="input-group-text">+62</span></x-slot:prepend>
                <x-slot:append><span class="input-group-text">WA</span></x-slot:append>
            </x-form.input>
            BLADE)
            ->assertSee('input-group has-validation', false)
            ->assertSeeInOrder(['+62', '<input', 'WA'], false);

        $this->blade('<x-form.input name="phone" label="Telepon" />')
            ->assertDontSee('input-group', false);
    }

    public function test_input_merges_extra_attributes(): void
    {
        $this->blade('<x-form.input name="email" label="Email" class="text-lowercase" data-x="1" readonly />')
            ->assertSee('class="form-control text-lowercase"', false)
            ->assertSee('data-x="1"', false)
            ->assertSee('readonly', false);
    }

    public function test_errors_summary_lists_errors_as_links_and_is_focusable(): void
    {
        $this->withViewErrors([
            'name' => ['Nama wajib diisi.'],
            'items.0.name' => ['Nama item wajib diisi.', 'Nama item terlalu pendek.'],
        ]);

        $this->blade('<x-form.errors-summary />')
            ->assertSee('role="alert"', false)
            ->assertSee('tabindex="-1"', false)
            ->assertSee('data-errors-summary', false)
            ->assertSee('Periksa kembali isian Anda')
            ->assertSee('<a href="#name" class="alert-link">Nama wajib diisi.</a>', false)
            ->assertSee('<a href="#items-0-name" class="alert-link">Nama item wajib diisi.</a>', false)
            ->assertSee('<a href="#items-0-name" class="alert-link">Nama item terlalu pendek.</a>', false);
    }

    public function test_errors_summary_accepts_a_custom_title_and_escapes_messages(): void
    {
        $this->withViewErrors(['name' => ['<script>alert(1)</script>']]);

        $this->blade('<x-form.errors-summary title="Ada kesalahan" />')
            ->assertSee('Ada kesalahan')
            ->assertDontSee('Periksa kembali isian Anda')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_errors_summary_renders_nothing_without_errors(): void
    {
        $this->blade('<x-form.errors-summary />')
            ->assertDontSee('data-errors-summary', false)
            ->assertDontSee('alert', false);

        $this->withViewErrors([]);

        $this->blade('<x-form.errors-summary />')
            ->assertDontSee('data-errors-summary', false);
    }
}
```

- [ ] **Step 3: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=FormComponentsTest
```
Expected: FAIL — `InvalidArgumentException: Unable to locate a class or view for component [form.input]` (dan `[form.errors-summary]`).

- [ ] **Step 4: Implementasi `form.input` dan `form.errors-summary` (tool Write)**

`resources/views/components/form/input.blade.php`:

```blade
@props([
    'name',
    'label',
    'id' => null,
    'help' => null,
    'required' => false,
    'type' => 'text',
    'value' => null,
    'autocomplete' => null,
    'inputmode' => null,
    'maxlength' => null,
    'placeholder' => null,
])
@php
    $id = $id ?: trim(str_replace(['.', '[', ']'], ['-', '-', ''], $name), '-');
    $key = trim(str_replace(['[', ']'], ['.', ''], $name), '.');
    $error = isset($errors) ? (string) $errors->first($key) : '';
    $current = $type === 'password' ? '' : old($key, $value);
    $current = is_scalar($current) ? (string) $current : '';
    $describedBy = trim(($help ? $id.'-help' : '').' '.($error !== '' ? $id.'-error' : ''));
    $grouped = isset($prepend) || isset($append);
@endphp
<div class="mb-3">
    <label for="{{ $id }}" class="form-label fw-semibold">{{ $label }}@if ($required) <span class="required-mark" aria-hidden="true">*</span>@endif</label>
    @if ($grouped)<div class="input-group has-validation">@endif
    {{ $prepend ?? '' }}
    <input type="{{ $type }}" id="{{ $id }}" name="{{ $name }}"@if ($current !== '') value="{{ $current }}"@endif @if ($autocomplete) autocomplete="{{ $autocomplete }}"@endif @if ($inputmode) inputmode="{{ $inputmode }}"@endif @if ($maxlength) maxlength="{{ $maxlength }}"@endif @if ($placeholder) placeholder="{{ $placeholder }}"@endif @if ($required) aria-required="true"@endif @if ($error !== '') aria-invalid="true"@endif @if ($describedBy !== '') aria-describedby="{{ $describedBy }}"@endif {{ $attributes->class(['form-control', 'is-invalid' => $error !== '']) }}>
    {{ $append ?? '' }}
    @if ($grouped)</div>@endif
    @if ($help)<div id="{{ $id }}-help" class="form-text">{{ $help }}</div>@endif
    @if ($error !== '')<div id="{{ $id }}-error" class="invalid-feedback d-block">{{ $error }}</div>@endif
</div>
```

`resources/views/components/form/errors-summary.blade.php`:

```blade
@props(['title' => 'Periksa kembali isian Anda'])
@if (isset($errors) && $errors->any())
    <div {{ $attributes->merge(['class' => 'alert alert-danger', 'role' => 'alert', 'tabindex' => '-1']) }} data-errors-summary>
        <h2 class="h6 alert-heading">{{ $title }}</h2>
        <ul class="mb-0">
            @foreach ($errors->messages() as $field => $messages)
                @foreach ($messages as $message)
                    <li><a href="#{{ trim(str_replace(['.', '[', ']'], ['-', '-', ''], $field), '-') }}" class="alert-link">{{ $message }}</a></li>
                @endforeach
            @endforeach
        </ul>
    </div>
@endif
```

- [ ] **Step 5: Jalankan tes — harus lulus; pint; commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=FormComponentsTest
& $PHP vendor\bin\pint --test
git add resources/views/components/form/input.blade.php resources/views/components/form/errors-summary.blade.php tests/Support/InteractsWithFormState.php tests/Feature/View/FormComponentsTest.php
git commit -m "feat(fase-1): komponen form.input dan form.errors-summary"
```
Expected: PASS (15 tes); pint `PASS`.

#### Siklus B — `form.select`, `form.textarea`, `form.checkbox`, `form.radio-group`

- [ ] **Step 6: Tulis tes yang gagal — `tests/Feature/View/FormChoiceComponentsTest.php`**

```php
<?php

namespace Tests\Feature\View;

use App\Enums\PaymentType;
use Illuminate\View\ViewException;
use Tests\Support\InteractsWithFormState;
use Tests\TestCase;

class FormChoiceComponentsTest extends TestCase
{
    use InteractsWithFormState;

    // ------------------------------------------------------------------ select

    public function test_select_renders_enum_options_with_disabled_placeholder(): void
    {
        $this->blade('<x-form.select name="payment_type" label="Jenis pembayaran" :options="$options" placeholder="Pilih jenis pembayaran" required />', [
            'options' => PaymentType::options(),
        ])
            ->assertSee('<label for="payment_type" class="form-label fw-semibold">Jenis pembayaran <span class="required-mark" aria-hidden="true">*</span></label>', false)
            ->assertSee('id="payment_type"', false)
            ->assertSee('name="payment_type"', false)
            ->assertSee('class="form-select"', false)
            ->assertSee('aria-required="true"', false)
            ->assertSee('<option value="" disabled selected>Pilih jenis pembayaran</option>', false)
            ->assertSeeInOrder(['Umum/Pribadi', 'BPJS Kesehatan', 'Asuransi']);
    }

    public function test_select_marks_the_selected_value_from_prop_or_backed_enum(): void
    {
        $this->blade('<x-form.select name="payment_type" label="Jenis pembayaran" :options="$options" selected="bpjs" placeholder="Pilih" />', [
            'options' => PaymentType::options(),
        ])
            ->assertSee('<option value="bpjs" selected>BPJS Kesehatan</option>', false)
            ->assertDontSee('<option value="" disabled selected>', false)
            ->assertDontSee('<option value="umum" selected', false);

        $this->blade('<x-form.select name="payment_type" label="Jenis pembayaran" :options="$options" :selected="$selected" />', [
            'options' => PaymentType::options(),
            'selected' => PaymentType::Asuransi,
        ])
            ->assertSee('<option value="asuransi" selected>Asuransi</option>', false);
    }

    public function test_select_old_input_takes_precedence_over_selected(): void
    {
        $this->withOldInput(['payment_type' => 'asuransi']);

        $this->blade('<x-form.select name="payment_type" label="Jenis pembayaran" :options="$options" selected="umum" />', [
            'options' => PaymentType::options(),
        ])
            ->assertSee('<option value="asuransi" selected>Asuransi</option>', false)
            ->assertDontSee('<option value="umum" selected', false);
    }

    public function test_select_accepts_collections(): void
    {
        $this->blade('<x-form.select name="clinic_id" label="Poliklinik" :options="$options" :selected="2" />', [
            'options' => collect([1 => 'Poli Anak', 2 => 'Poli Gigi']),
        ])
            ->assertSee('<option value="1" >Poli Anak</option>', false)
            ->assertSee('<option value="2" selected>Poli Gigi</option>', false);
    }

    public function test_select_multiple_appends_brackets_and_selects_many(): void
    {
        $this->blade('<x-form.select name="tags" label="Label" :options="$options" :selected="$selected" multiple placeholder="Abaikan" />', [
            'options' => [1 => 'A', 2 => 'B', 3 => 'C'],
            'selected' => [1, 3],
        ])
            ->assertSee('name="tags[]"', false)
            ->assertSee('multiple', false)
            ->assertSee('<option value="1" selected>A</option>', false)
            ->assertSee('<option value="3" selected>C</option>', false)
            ->assertDontSee('<option value="2" selected', false)
            ->assertDontSee('Abaikan');
    }

    public function test_select_error_state_and_escaping(): void
    {
        $this->withViewErrors(['clinic_id' => ['Pilihan tidak valid.']]);

        $this->blade('<x-form.select name="clinic_id" label="Poliklinik" :options="$options" help="Pilih satu." />', [
            'options' => ['<x>' => '<script>alert(1)</script>'],
        ])
            ->assertSee('class="form-select is-invalid"', false)
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('aria-describedby="clinic_id-help clinic_id-error"', false)
            ->assertSee('<div id="clinic_id-error" class="invalid-feedback d-block">Pilihan tidak valid.</div>', false)
            ->assertSee('value="&lt;x&gt;"', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    // ------------------------------------------------------------------ textarea

    public function test_textarea_renders_value_rows_and_character_counter(): void
    {
        $this->blade('<x-form.textarea name="complaint" label="Keluhan" value="Halo!" :rows="6" maxlength="500" required />')
            ->assertSee('rows="6"', false)
            ->assertSee('maxlength="500"', false)
            ->assertSee('aria-required="true"', false)
            ->assertSee('class="form-control"', false)
            ->assertSee('>Halo!</textarea>', false)
            ->assertSee('data-char-counter', false)
            ->assertSee('data-counter-for="complaint"', false)
            ->assertSee('data-counter-max="500"', false)
            ->assertSee('>5 / 500</span>', false)
            ->assertSee('aria-live="polite" data-counter-announce', false);
    }

    public function test_textarea_defaults_to_four_rows_and_has_no_counter_without_maxlength(): void
    {
        $this->blade('<x-form.textarea name="notes" label="Catatan" />')
            ->assertSee('rows="4"', false)
            ->assertSee('></textarea>', false)
            ->assertDontSee('data-char-counter', false);
    }

    public function test_textarea_old_input_error_and_escaping(): void
    {
        $this->withOldInput(['notes' => '</textarea><script>alert(1)</script>']);
        $this->withViewErrors(['notes' => ['Catatan terlalu panjang.']]);

        $this->blade('<x-form.textarea name="notes" label="Catatan" value="abaikan" />')
            ->assertSee('&lt;/textarea&gt;&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('abaikan')
            ->assertSee('class="form-control is-invalid"', false)
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('aria-describedby="notes-error"', false);
    }

    // ------------------------------------------------------------------ checkbox

    public function test_checkbox_defaults_to_unchecked_with_value_one(): void
    {
        $this->blade('<x-form.checkbox name="is_active" label="Aktif" />')
            ->assertSee('<div class="form-check">', false)
            ->assertSee('type="checkbox"', false)
            ->assertSee('value="1"', false)
            ->assertSee('class="form-check-input"', false)
            ->assertSee('<label class="form-check-label" for="is_active">Aktif</label>', false)
            ->assertDontSee('checked', false)
            ->assertDontSee('type="hidden"', false);
    }

    public function test_checkbox_with_hidden_sends_zero_and_respects_checked_prop(): void
    {
        $this->blade('<x-form.checkbox name="is_active" label="Aktif" with-hidden checked />')
            ->assertSee('<input type="hidden" name="is_active" value="0">', false)
            ->assertSee('value="1" checked', false);
    }

    public function test_checkbox_old_input_overrides_the_checked_prop(): void
    {
        $this->withOldInput(['is_active' => '0']);

        $this->blade('<x-form.checkbox name="is_active" label="Aktif" with-hidden checked />')
            ->assertDontSee('checked', false);

        $this->withOldInput(['is_active' => '1']);

        $this->blade('<x-form.checkbox name="is_active" label="Aktif" with-hidden />')
            ->assertSee('value="1" checked', false);
    }

    public function test_checkbox_without_hidden_is_unchecked_when_old_input_exists_without_its_key(): void
    {
        $this->withOldInput(['name' => 'Budi']);

        $this->blade('<x-form.checkbox name="agree" label="Setuju" checked />')
            ->assertDontSee('checked', false);
    }

    public function test_checkbox_required_error_and_escaping(): void
    {
        $this->withViewErrors(['agree' => ['Anda harus menyetujui ketentuan.']]);

        $this->blade('<x-form.checkbox name="agree" :label="$p" help="Wajib dicentang." required />', ['p' => '<script>alert(1)</script>'])
            ->assertSee('aria-required="true"', false)
            ->assertSee('<span class="required-mark" aria-hidden="true">*</span>', false)
            ->assertSee('class="form-check-input is-invalid"', false)
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('aria-describedby="agree-help agree-error"', false)
            ->assertSee('<div id="agree-error" class="invalid-feedback d-block">Anda harus menyetujui ketentuan.</div>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    // ------------------------------------------------------------------ radio-group

    public function test_radio_group_renders_fieldset_legend_and_options(): void
    {
        $this->blade('<x-form.radio-group name="payment_type" legend="Jenis pembayaran" :options="$options" selected="bpjs" required />', [
            'options' => PaymentType::options(),
        ])
            ->assertSee('<fieldset id="payment_type" role="radiogroup" tabindex="-1"', false)
            ->assertSee('aria-required="true"', false)
            ->assertSee('class="mb-3"', false)
            ->assertSee('<legend class="form-label fw-semibold fs-6">Jenis pembayaran <span class="required-mark" aria-hidden="true">*</span></legend>', false)
            ->assertSee('id="payment_type-umum"', false)
            ->assertSee('id="payment_type-bpjs"', false)
            ->assertSee('<label class="form-check-label" for="payment_type-bpjs">BPJS Kesehatan</label>', false)
            ->assertSee('value="bpjs" checked', false)
            ->assertDontSee('value="umum" checked', false)
            ->assertDontSee('form-check-inline', false);
    }

    public function test_radio_group_inline_old_input_and_backed_enum_selection(): void
    {
        $this->withOldInput(['payment_type' => 'asuransi']);

        $this->blade('<x-form.radio-group name="payment_type" legend="Jenis pembayaran" :options="$options" :selected="$selected" inline />', [
            'options' => PaymentType::options(),
            'selected' => PaymentType::Umum,
        ])
            ->assertSee('form-check form-check-inline', false)
            ->assertSee('value="asuransi" checked', false)
            ->assertDontSee('value="umum" checked', false);
    }

    public function test_radio_group_error_is_linked_to_the_fieldset(): void
    {
        $this->withViewErrors(['payment_type' => ['Jenis pembayaran wajib dipilih.']]);

        $this->blade('<x-form.radio-group name="payment_type" legend="Jenis pembayaran" :options="$options" help="Pilih salah satu." />', [
            'options' => PaymentType::options(),
        ])
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('aria-describedby="payment_type-help payment_type-error"', false)
            ->assertSee('<div id="payment_type-help" class="form-text">Pilih salah satu.</div>', false)
            ->assertSee('<div id="payment_type-error" class="invalid-feedback d-block">Jenis pembayaran wajib dipilih.</div>', false)
            ->assertSee('form-check-input is-invalid', false);
    }

    public function test_radio_group_escapes_legend_and_option_values(): void
    {
        $this->blade('<x-form.radio-group name="x" :legend="$p" :options="$options" />', [
            'p' => '<script>alert(1)</script>',
            'options' => ['"><script>' => '<b>bold</b>'],
        ])
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertSee('&lt;b&gt;bold&lt;/b&gt;', false)
            ->assertDontSee('<script>', false);
    }

    public function test_radio_group_requires_a_legend(): void
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessage('legend');

        $this->blade('<x-form.radio-group name="x" :options="[]" />');
    }
}
```

- [ ] **Step 7: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=FormChoiceComponentsTest
```
Expected: FAIL — `InvalidArgumentException: Unable to locate a class or view for component [form.select]` (juga `[form.textarea]`, `[form.checkbox]`, `[form.radio-group]`).

- [ ] **Step 8: Implementasi empat komponen (tool Write)**

`resources/views/components/form/select.blade.php`:

```blade
@props([
    'name',
    'label',
    'id' => null,
    'help' => null,
    'required' => false,
    'options' => [],
    'selected' => null,
    'placeholder' => null,
    'multiple' => false,
])
@php
    $id = $id ?: trim(str_replace(['.', '[', ']'], ['-', '-', ''], $name), '-');
    $key = trim(str_replace(['[', ']'], ['.', ''], $name), '.');
    $error = '';
    if (isset($errors)) {
        $error = (string) ($errors->first($key) ?: ($multiple ? $errors->first($key.'.*') : ''));
    }
    $options = $options instanceof \Illuminate\Support\Collection ? $options->all() : (array) $options;
    $selected = $selected instanceof \BackedEnum ? $selected->value : $selected;
    $chosen = array_map(
        fn ($item) => (string) ($item instanceof \BackedEnum ? $item->value : $item),
        \Illuminate\Support\Arr::wrap(old($key, $selected)),
    );
    $describedBy = trim(($help ? $id.'-help' : '').' '.($error !== '' ? $id.'-error' : ''));
@endphp
<div class="mb-3">
    <label for="{{ $id }}" class="form-label fw-semibold">{{ $label }}@if ($required) <span class="required-mark" aria-hidden="true">*</span>@endif</label>
    <select id="{{ $id }}" name="{{ $name }}@if ($multiple)[]@endif"@if ($multiple) multiple @endif @if ($required) aria-required="true"@endif @if ($error !== '') aria-invalid="true"@endif @if ($describedBy !== '') aria-describedby="{{ $describedBy }}"@endif {{ $attributes->class(['form-select', 'is-invalid' => $error !== '']) }}>
        @if ($placeholder !== null && ! $multiple)
            <option value="" disabled @selected($chosen === [] || $chosen === [''])>{{ $placeholder }}</option>
        @endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected(in_array((string) $optionValue, $chosen, true))>{{ $optionLabel }}</option>
        @endforeach
    </select>
    @if ($help)<div id="{{ $id }}-help" class="form-text">{{ $help }}</div>@endif
    @if ($error !== '')<div id="{{ $id }}-error" class="invalid-feedback d-block">{{ $error }}</div>@endif
</div>
```

`resources/views/components/form/textarea.blade.php`:

```blade
@props([
    'name',
    'label',
    'id' => null,
    'help' => null,
    'required' => false,
    'rows' => 4,
    'maxlength' => null,
    'value' => null,
])
@php
    $id = $id ?: trim(str_replace(['.', '[', ']'], ['-', '-', ''], $name), '-');
    $key = trim(str_replace(['[', ']'], ['.', ''], $name), '.');
    $error = isset($errors) ? (string) $errors->first($key) : '';
    $current = old($key, $value);
    $current = is_scalar($current) ? (string) $current : '';
    $describedBy = trim(($help ? $id.'-help' : '').' '.($error !== '' ? $id.'-error' : ''));
@endphp
<div class="mb-3">
    <label for="{{ $id }}" class="form-label fw-semibold">{{ $label }}@if ($required) <span class="required-mark" aria-hidden="true">*</span>@endif</label>
    <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}"@if ($maxlength) maxlength="{{ $maxlength }}"@endif @if ($required) aria-required="true"@endif @if ($error !== '') aria-invalid="true"@endif @if ($describedBy !== '') aria-describedby="{{ $describedBy }}"@endif {{ $attributes->class(['form-control', 'is-invalid' => $error !== '']) }}>{{ $current }}</textarea>
    @if ($maxlength)
        <div class="form-text text-end"><span data-char-counter data-counter-for="{{ $id }}" data-counter-max="{{ $maxlength }}" aria-hidden="true">{{ mb_strlen($current) }} / {{ $maxlength }}</span><span class="visually-hidden" aria-live="polite" data-counter-announce></span></div>
    @endif
    @if ($help)<div id="{{ $id }}-help" class="form-text">{{ $help }}</div>@endif
    @if ($error !== '')<div id="{{ $id }}-error" class="invalid-feedback d-block">{{ $error }}</div>@endif
</div>
```

`resources/views/components/form/checkbox.blade.php`:

```blade
@props([
    'name',
    'label',
    'id' => null,
    'help' => null,
    'required' => false,
    'value' => 1,
    'checked' => false,
    'withHidden' => false,
])
@php
    $id = $id ?: trim(str_replace(['.', '[', ']'], ['-', '-', ''], $name), '-');
    $key = trim(str_replace(['[', ']'], ['.', ''], $name), '.');
    $error = isset($errors) ? (string) $errors->first($key) : '';
    $hasOldInput = request()->hasSession() && request()->session()->hasOldInput();
    $isChecked = $hasOldInput
        ? (string) old($key, $withHidden ? '0' : null) === (string) $value
        : (bool) $checked;
    $describedBy = trim(($help ? $id.'-help' : '').' '.($error !== '' ? $id.'-error' : ''));
@endphp
<div class="mb-3">
    @if ($withHidden)<input type="hidden" name="{{ $name }}" value="0">@endif
    <div class="form-check">
        <input type="checkbox" id="{{ $id }}" name="{{ $name }}" value="{{ $value }}" @checked($isChecked) @if ($required) aria-required="true"@endif @if ($error !== '') aria-invalid="true"@endif @if ($describedBy !== '') aria-describedby="{{ $describedBy }}"@endif {{ $attributes->class(['form-check-input', 'is-invalid' => $error !== '']) }}>
        <label class="form-check-label" for="{{ $id }}">{{ $label }}@if ($required) <span class="required-mark" aria-hidden="true">*</span>@endif</label>
    </div>
    @if ($help)<div id="{{ $id }}-help" class="form-text">{{ $help }}</div>@endif
    @if ($error !== '')<div id="{{ $id }}-error" class="invalid-feedback d-block">{{ $error }}</div>@endif
</div>
```

`resources/views/components/form/radio-group.blade.php`:

```blade
@props([
    'name',
    'legend' => null,
    'label' => null,
    'id' => null,
    'help' => null,
    'required' => false,
    'options' => [],
    'selected' => null,
    'inline' => false,
])
@php
    $legendText = $legend ?? $label;

    if ($legendText === null) {
        throw new \InvalidArgumentException('x-form.radio-group membutuhkan prop legend.');
    }

    $id = $id ?: trim(str_replace(['.', '[', ']'], ['-', '-', ''], $name), '-');
    $key = trim(str_replace(['[', ']'], ['.', ''], $name), '.');
    $error = isset($errors) ? (string) $errors->first($key) : '';
    $options = $options instanceof \Illuminate\Support\Collection ? $options->all() : (array) $options;
    $selected = $selected instanceof \BackedEnum ? $selected->value : $selected;
    $current = old($key, $selected);
    $chosen = is_scalar($current) ? (string) $current : '';
    $describedBy = trim(($help ? $id.'-help' : '').' '.($error !== '' ? $id.'-error' : ''));
@endphp
<fieldset id="{{ $id }}" role="radiogroup" tabindex="-1"@if ($required) aria-required="true"@endif @if ($error !== '') aria-invalid="true"@endif @if ($describedBy !== '') aria-describedby="{{ $describedBy }}"@endif {{ $attributes->class(['mb-3']) }}>
    <legend class="form-label fw-semibold fs-6">{{ $legendText }}@if ($required) <span class="required-mark" aria-hidden="true">*</span>@endif</legend>
    @foreach ($options as $optionValue => $optionLabel)
        @php
            $optionId = $id.'-'.preg_replace('/[^A-Za-z0-9_-]/', '-', (string) $optionValue);
        @endphp
        <div class="form-check{{ $inline ? ' form-check-inline' : '' }}">
            <input type="radio" id="{{ $optionId }}" name="{{ $name }}" value="{{ $optionValue }}" @checked($chosen !== '' && $chosen === (string) $optionValue) class="form-check-input{{ $error !== '' ? ' is-invalid' : '' }}">
            <label class="form-check-label" for="{{ $optionId }}">{{ $optionLabel }}</label>
        </div>
    @endforeach
    @if ($help)<div id="{{ $id }}-help" class="form-text">{{ $help }}</div>@endif
    @if ($error !== '')<div id="{{ $id }}-error" class="invalid-feedback d-block">{{ $error }}</div>@endif
</fieldset>
```

- [ ] **Step 9: Jalankan tes — harus lulus; pint; commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=FormChoiceComponentsTest
& $PHP vendor\bin\pint --test
git add resources/views/components/form/select.blade.php resources/views/components/form/textarea.blade.php resources/views/components/form/checkbox.blade.php resources/views/components/form/radio-group.blade.php tests/Feature/View/FormChoiceComponentsTest.php
git commit -m "feat(fase-1): komponen form.select, textarea, checkbox, dan radio-group"
```
Expected: PASS (19 tes); pint `PASS`.

#### Siklus C — `form.file`

- [ ] **Step 10: Tulis tes yang gagal — `tests/Feature/View/FormFileComponentTest.php`**

```php
<?php

namespace Tests\Feature\View;

use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\InteractsWithFormState;
use Tests\TestCase;

class FormFileComponentTest extends TestCase
{
    use InteractsWithFormState;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function makeMedia(): Media
    {
        return Media::create([
            'model_type' => null,
            'model_id' => null,
            'collection' => 'photo',
            'disk' => 'public',
            'directory' => 'photo/2026/10',
            'file_name' => Str::uuid()->toString().'.jpg',
            'original_name' => 'dokter.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size_bytes' => 120000,
            'width' => 800,
            'height' => 800,
            'thumb_path' => 'photo/2026/10/thumbs/dokter.jpg',
            'alt_text' => 'Foto dr. Andi tersenyum',
            'caption' => null,
            'sort_order' => 1,
            'uploaded_by' => null,
        ])->fresh();
    }

    public function test_file_input_exposes_preview_hooks_limits_and_required_alt_field(): void
    {
        $this->blade('<x-form.file name="photo" label="Foto dokter" required help="Minimal 300 × 300 piksel." />')
            ->assertSee('data-image-field', false)
            ->assertSee('type="file"', false)
            ->assertSee('id="photo"', false)
            ->assertSee('name="photo"', false)
            ->assertSee('accept="image/jpeg,image/png,image/webp"', false)
            ->assertSee('data-image-input', false)
            ->assertSee('data-max-kb="2048"', false)
            ->assertSee('aria-required="true"', false)
            ->assertSee('class="form-control"', false)
            ->assertSee('<img data-image-preview class="image-preview mt-2 d-none" alt="Pratinjau gambar yang dipilih">', false)
            ->assertSee('data-image-warning', false)
            ->assertSee('Format JPG, PNG, atau WebP (SVG ditolak). Ukuran maksimal 2 MB.')
            ->assertSee('Minimal 300 × 300 piksel.')
            ->assertSee('aria-describedby="photo-hint photo-help"', false)
            ->assertSee('name="photo_alt"', false)
            ->assertSee('id="photo_alt"', false)
            ->assertSee('Teks alternatif gambar');
    }

    public function test_file_input_formats_the_size_limit(): void
    {
        $this->blade('<x-form.file name="banner" label="Banner" :max-kb="4096" />')
            ->assertSee('data-max-kb="4096"', false)
            ->assertSee('Ukuran maksimal 4 MB.');

        $this->blade('<x-form.file name="banner" label="Banner" :max-kb="512" />')
            ->assertSee('Ukuran maksimal 512 KB.');

        $this->blade('<x-form.file name="banner" label="Banner" :max-kb="1536" />')
            ->assertSee('Ukuran maksimal 1,5 MB.');
    }

    public function test_file_alt_field_is_required_and_accepts_custom_name_and_value(): void
    {
        $this->blade('<x-form.file name="photo" label="Foto" alt-name="foto_alt" alt-value="Foto resepsionis" />')
            ->assertSee('name="foto_alt"', false)
            ->assertSee('id="foto_alt"', false)
            ->assertSee('value="Foto resepsionis"', false)
            ->assertSee('aria-required="true"', false)
            ->assertSee('maxlength="255"', false);
    }

    public function test_file_alt_field_reads_old_input(): void
    {
        $this->withOldInput(['photo_alt' => 'Alt lama']);

        $this->blade('<x-form.file name="photo" label="Foto" alt-value="Alt asli" />')
            ->assertSee('value="Alt lama"', false)
            ->assertDontSee('Alt asli');
    }

    public function test_file_shows_the_current_media_thumbnail_and_prefills_alt(): void
    {
        $media = $this->makeMedia();

        $this->blade('<x-form.file name="photo" label="Foto dokter" :current-media="$media" />', ['media' => $media])
            ->assertSee('data-current-media', false)
            ->assertSee('Gambar saat ini')
            ->assertSee('src="'.$media->thumb_url.'"', false)
            ->assertSee('data-fallback="'.asset('images/placeholders/doctor.svg').'"', false)
            ->assertSee('alt="Foto dr. Andi tersenyum"', false)
            ->assertSee('value="Foto dr. Andi tersenyum"', false);
    }

    public function test_file_without_current_media_has_no_current_block(): void
    {
        $this->blade('<x-form.file name="photo" label="Foto" />')
            ->assertDontSee('data-current-media', false)
            ->assertDontSee('Gambar saat ini');
    }

    public function test_file_multiple_uses_array_name_and_multiple_attribute(): void
    {
        $this->blade('<x-form.file name="photos" label="Foto galeri" multiple />')
            ->assertSee('name="photos[]"', false)
            ->assertSee('id="photos"', false)
            ->assertSee('multiple', false)
            ->assertSee('name="photos_alt"', false);
    }

    public function test_file_error_is_linked_and_escaped(): void
    {
        $this->withViewErrors(['photo' => ['<script>alert(1)</script>']]);

        $this->blade('<x-form.file name="photo" label="Foto" />')
            ->assertSee('class="form-control is-invalid"', false)
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('aria-describedby="photo-hint photo-error"', false)
            ->assertSee('<div id="photo-error" class="invalid-feedback d-block">&lt;script&gt;alert(1)&lt;/script&gt;</div>', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_file_accept_can_be_overridden(): void
    {
        $this->blade('<x-form.file name="logo" label="Logo" accept="image/png" />')
            ->assertSee('accept="image/png"', false);
    }
}
```

- [ ] **Step 11: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=FormFileComponentTest
```
Expected: FAIL — `InvalidArgumentException: Unable to locate a class or view for component [form.file]`.

- [ ] **Step 12: Implementasi `form.file` (tool Write)**

`resources/views/components/form/file.blade.php`:

```blade
@props([
    'name',
    'label',
    'id' => null,
    'help' => null,
    'required' => false,
    'accept' => 'image/jpeg,image/png,image/webp',
    'maxKb' => 2048,
    'multiple' => false,
    'currentMedia' => null,
    'altName' => null,
    'altValue' => null,
])
@php
    $id = $id ?: trim(str_replace(['.', '[', ']'], ['-', '-', ''], $name), '-');
    $key = trim(str_replace(['[', ']'], ['.', ''], $name), '.');
    $error = '';
    if (isset($errors)) {
        $error = (string) ($errors->first($key) ?: ($multiple ? $errors->first($key.'.*') : ''));
    }
    $altName = $altName ?: $name.'_alt';
    $maxLabel = $maxKb >= 1024
        ? rtrim(rtrim(number_format($maxKb / 1024, 1, ',', '.'), '0'), ',').' MB'
        : $maxKb.' KB';
    $placeholderTypes = ['photo' => 'doctor', 'cover' => 'clinic', 'gallery' => 'gallery', 'photos' => 'gallery', 'image' => 'banner', 'logo' => 'logo', 'editor' => 'article'];
    $describedBy = trim($id.'-hint '.($help ? $id.'-help ' : '').($error !== '' ? $id.'-error' : ''));
@endphp
<div class="mb-3" data-image-field>
    <label for="{{ $id }}" class="form-label fw-semibold">{{ $label }}@if ($required) <span class="required-mark" aria-hidden="true">*</span>@endif</label>
    @if ($currentMedia)
        <div class="mb-2" data-current-media>
            <x-image :media="$currentMedia" :type="$placeholderTypes[$currentMedia->collection] ?? 'gallery'" variant="thumb" :alt="$currentMedia->alt_text" class="image-preview" />
            <p class="form-text mb-0">Gambar saat ini</p>
        </div>
    @endif
    <input type="file" id="{{ $id }}" name="{{ $name }}@if ($multiple)[]@endif" accept="{{ $accept }}" data-image-input data-max-kb="{{ $maxKb }}"@if ($multiple) multiple @endif @if ($required) aria-required="true"@endif @if ($error !== '') aria-invalid="true"@endif aria-describedby="{{ $describedBy }}" {{ $attributes->class(['form-control', 'is-invalid' => $error !== '']) }}>
    <img data-image-preview class="image-preview mt-2 d-none" alt="Pratinjau gambar yang dipilih">
    <div data-image-warning class="form-text text-warning-emphasis d-none" role="status"></div>
    <div id="{{ $id }}-hint" class="form-text">Format JPG, PNG, atau WebP (SVG ditolak). Ukuran maksimal {{ $maxLabel }}.</div>
    @if ($help)<div id="{{ $id }}-help" class="form-text">{{ $help }}</div>@endif
    @if ($error !== '')<div id="{{ $id }}-error" class="invalid-feedback d-block">{{ $error }}</div>@endif
    <div class="mt-3">
        <x-form.input
            :name="$altName"
            label="Teks alternatif gambar"
            :value="$altValue ?? $currentMedia?->alt_text"
            :required="true"
            maxlength="255"
            help="Jelaskan isi gambar dengan singkat untuk pembaca layar, mis. Foto dr. Andi tersenyum. Wajib diisi bila memilih gambar."
        />
    </div>
</div>
```

Catatan: `help` pada kolom alt text tidak mengandung tanda kutip agar tidak merusak atribut Blade.

- [ ] **Step 13: Jalankan tes — harus lulus; pint; commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=FormFileComponentTest
& $PHP vendor\bin\pint --test
git add resources/views/components/form/file.blade.php tests/Feature/View/FormFileComponentTest.php
git commit -m "feat(fase-1): komponen form.file dengan pratinjau dan alt text wajib"
```
Expected: PASS (9 tes); pint `PASS`. (Butuh MySQL menyala: `RefreshDatabase`.)

#### Siklus D — paginasi Indonesia dan `data-table`

- [ ] **Step 14: Tulis tes yang gagal — `tests/Feature/View/DataTableComponentTest.php`**

```php
<?php

namespace Tests\Feature\View;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Tests\TestCase;

class DataTableComponentTest extends TestCase
{
    private function visit(string $uri): void
    {
        $this->app->instance('request', Request::create($uri));
    }

    private function paginator(int $total, int $perPage = 10, int $page = 1): LengthAwarePaginator
    {
        $items = collect($total > 0 ? range(1, $total) : [])
            ->forPage($page, $perPage)
            ->values()
            ->map(fn (int $number): array => ['id' => $number, 'name' => "Dokter {$number}"]);

        return new LengthAwarePaginator($items, $total, $perPage, $page, ['path' => url('/admin/dokter')]);
    }

    private function table(): string
    {
        return <<<'BLADE'
            <x-data-table :rows="$rows" caption="Daftar dokter" search-placeholder="Cari dokter…" empty-title="Belum ada dokter" empty-message="Tambahkan dokter pertama.">
                <x-slot:filters>
                    <div class="col-12 col-md-3"><label for="status" class="form-label">Status</label><select id="status" name="status" class="form-select"></select></div>
                </x-slot:filters>
                <x-slot:actions><a href="/admin/dokter/baru" class="btn btn-primary">Tambah Dokter</a></x-slot:actions>
                <x-slot:head><th scope="col">Nama</th><th scope="col">Aksi</th></x-slot:head>
                @foreach ($rows as $row)
                    <tr><td data-label="Nama">{{ $row['name'] }}</td><td data-label="Aksi" class="table-actions"><a href="#">Ubah</a></td></tr>
                @endforeach
            </x-data-table>
            BLADE;
    }

    public function test_bootstrap_five_is_the_default_pagination_view(): void
    {
        $this->assertSame('pagination::bootstrap-5', Paginator::$defaultView);
    }

    public function test_table_renders_search_form_slots_caption_and_card_markup(): void
    {
        $this->visit('/admin/dokter');

        $this->blade($this->table(), ['rows' => $this->paginator(32)])
            ->assertSee('data-table', false)
            ->assertSee('method="GET"', false)
            ->assertSee('role="search"', false)
            ->assertSee('aria-label="Cari dan saring data"', false)
            ->assertSee('type="search"', false)
            ->assertSee('name="q"', false)
            ->assertSee('placeholder="Cari dokter…"', false)
            ->assertSee('id="status"', false)
            ->assertSee('Tambah Dokter')
            ->assertSee('<caption class="visually-hidden">Daftar dokter</caption>', false)
            ->assertSee('table-cards', false)
            ->assertSee('<th scope="col">Nama</th>', false)
            ->assertSee('<td data-label="Nama">Dokter 1</td>', false)
            ->assertSee('data-loading-button', false)
            ->assertSee('Terapkan')
            ->assertDontSee('Hapus filter')
            ->assertDontSee('>Reset</a>', false);
    }

    public function test_summary_shows_visible_range_and_total(): void
    {
        $this->visit('/admin/dokter');

        $this->blade($this->table(), ['rows' => $this->paginator(32)])
            ->assertSee('Menampilkan 1–10 dari 32 data')
            ->assertSee('aria-live="polite"', false);

        $this->blade($this->table(), ['rows' => $this->paginator(32, 10, 2)])
            ->assertSee('Menampilkan 11–20 dari 32 data');

        $this->blade($this->table(), ['rows' => $this->paginator(32, 10, 4)])
            ->assertSee('Menampilkan 31–32 dari 32 data');
    }

    public function test_pagination_is_indonesian_accessible_and_keeps_the_query_string(): void
    {
        $this->visit('/admin/dokter?q=andi&status=active');

        $this->blade($this->table(), ['rows' => $this->paginator(32)])
            ->assertSee('aria-label="Navigasi halaman"', false)
            ->assertSee('Sebelumnya')
            ->assertSee('Berikutnya')
            ->assertSee('rel="next"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('Menampilkan <span class="fw-semibold">1</span> sampai <span class="fw-semibold">10</span> dari <span class="fw-semibold">32</span> hasil', false)
            ->assertSee('/admin/dokter?q=andi&amp;status=active&amp;page=2', false)
            ->assertDontSee('Previous')
            ->assertDontSee('Next')
            ->assertDontSee('Pagination Navigation');
    }

    public function test_single_page_has_no_pagination_navigation(): void
    {
        $this->visit('/admin/dokter');

        $this->blade($this->table(), ['rows' => $this->paginator(5)])
            ->assertSee('Menampilkan 1–5 dari 5 data')
            ->assertDontSee('Navigasi halaman', false);
    }

    public function test_simple_pagination_view_is_indonesian(): void
    {
        $paginator = new Paginator(collect(range(1, 11)), 10, 1, ['path' => url('/admin/dokter')]);

        $html = (string) $paginator->links('pagination::simple-bootstrap-5');

        $this->assertStringContainsString('aria-label="Navigasi halaman"', $html);
        $this->assertStringContainsString('Sebelumnya', $html);
        $this->assertStringContainsString('Berikutnya', $html);
        $this->assertStringNotContainsString('Pagination Navigation', $html);
    }

    public function test_keyword_is_kept_in_the_search_input_and_escaped(): void
    {
        $this->visit('/admin/dokter?q='.urlencode('"><script>alert(1)</script>'));

        $this->blade($this->table(), ['rows' => $this->paginator(3)])
            ->assertSee('value="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_array_query_values_do_not_break_the_table(): void
    {
        $this->visit('/admin/dokter?q[]=x&status[a]=b');

        $this->blade($this->table(), ['rows' => $this->paginator(3)])
            ->assertSee('type="search"', false)
            ->assertDontSee('value="Array"', false);
    }

    public function test_empty_state_for_a_table_without_data_emp_03(): void
    {
        $this->visit('/admin/dokter');

        $this->blade($this->table(), ['rows' => $this->paginator(0)])
            ->assertSee('role="status"', false)
            ->assertSee('Belum ada dokter')
            ->assertSee('Tambahkan dokter pertama.')
            ->assertSee('Tidak ada data untuk ditampilkan.')
            ->assertDontSee('<table', false)
            ->assertDontSee('Hapus filter')
            ->assertDontSee('>Reset</a>', false)
            ->assertDontSee('Navigasi halaman', false);
    }

    public function test_empty_state_with_a_query_offers_to_clear_filters(): void
    {
        $this->visit('/admin/dokter?q=zzz&per_page=25&page=1');

        $this->blade($this->table(), ['rows' => $this->paginator(0)])
            ->assertSee('Belum ada dokter')
            ->assertSee('Tidak ada data yang cocok dengan pencarian atau filter Anda.')
            ->assertDontSee('Tambahkan dokter pertama.')
            ->assertSee('<a href="'.url('/admin/dokter').'" class="btn btn-outline-primary">Hapus filter</a>', false)
            ->assertSee('>Reset</a>', false);
    }

    public function test_per_page_and_page_alone_do_not_count_as_a_filter(): void
    {
        $this->visit('/admin/dokter?per_page=50&page=3');

        $this->blade($this->table(), ['rows' => $this->paginator(0)])
            ->assertDontSee('Hapus filter')
            ->assertSee('<input type="hidden" name="per_page" value="50">', false);
    }

    public function test_empty_slot_replaces_the_default_empty_state(): void
    {
        $this->visit('/admin/dokter');

        $this->blade(<<<'BLADE'
            <x-data-table :rows="$rows" caption="Daftar">
                <x-slot:empty><p>Kustom kosong</p></x-slot:empty>
            </x-data-table>
            BLADE, ['rows' => $this->paginator(0)])
            ->assertSee('Kustom kosong')
            ->assertDontSee('Belum ada data');
    }

    public function test_search_can_be_disabled_and_renamed(): void
    {
        $this->visit('/admin/dokter');

        $this->blade('<x-data-table :rows="$rows" caption="Daftar" :searchable="false"><tr><td data-label="Nama">x</td></tr></x-data-table>', ['rows' => $this->paginator(1)])
            ->assertSee('role="search"', false)
            ->assertDontSee('type="search"', false);

        $this->blade('<x-data-table :rows="$rows" caption="Daftar" search-name="cari"><tr><td data-label="Nama">x</td></tr></x-data-table>', ['rows' => $this->paginator(1)])
            ->assertSee('name="cari"', false)
            ->assertDontSee('name="q"', false);
    }

    public function test_caption_is_escaped(): void
    {
        $this->visit('/admin/dokter');

        $this->blade('<x-data-table :rows="$rows" :caption="$caption"><tr><td data-label="Nama">x</td></tr></x-data-table>', [
            'rows' => $this->paginator(1),
            'caption' => '<script>alert(1)</script>',
        ])
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }
}
```

- [ ] **Step 15: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=DataTableComponentTest
```
Expected: FAIL — `test_bootstrap_five_is_the_default_pagination_view` gagal (`'pagination::bootstrap-5'` ≠ `'pagination::tailwind'`); tes `data-table` gagal dengan `Unable to locate a class or view for component [data-table]`; `test_simple_pagination_view_is_indonesian` gagal karena teks masih bawaan (`Previous`/`Next`).

- [ ] **Step 16: Aktifkan Bootstrap 5 sebagai tampilan paginasi bawaan**

Edit `app/Providers/AppServiceProvider.php` (tool Edit; dua perubahan). Perubahan 1 — tambahkan impor tepat setelah `use Illuminate\Database\Eloquent\Model;`:

```php
use Illuminate\Pagination\Paginator;
```

Perubahan 2 — tambahkan satu baris di akhir `boot()`, tepat setelah `Gate::before(...)`:

```php
        Paginator::useBootstrapFive();
```

Isi `AppServiceProvider.php` lengkap setelah perubahan (urutan `use` mengikuti Pint; `register()` memuat singleton Task 7, `boot()` memuat mode ketat Task 2 dan `Gate::before` Task 3):

```php
<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Settings\SettingsService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SettingsService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        Gate::before(fn (User $user, string $ability) => $user->hasRole('super-admin') ? true : null);

        Paginator::useBootstrapFive();
    }
}
```

Bila isi berkas Anda berbeda dari kerangka di atas selain dua perubahan ini (mis. Task 13/14 sudah menambah `View::composer`/`configureRateLimiting()`), pertahankan tambahan itu dan hanya terapkan dua perubahan di atas.

- [ ] **Step 17: Tulis view paginasi berbahasa Indonesia (tool Write)**

`resources/views/vendor/pagination/bootstrap-5.blade.php`:

```blade
@if ($paginator->hasPages())
    <nav class="d-flex justify-items-center justify-content-between" aria-label="Navigasi halaman">
        <div class="d-flex justify-content-between flex-fill d-sm-none">
            <ul class="pagination">
                {{-- Tautan halaman sebelumnya --}}
                @if ($paginator->onFirstPage())
                    <li class="page-item disabled" aria-disabled="true">
                        <span class="page-link">Sebelumnya</span>
                    </li>
                @else
                    <li class="page-item">
                        <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">Sebelumnya</a>
                    </li>
                @endif

                {{-- Tautan halaman berikutnya --}}
                @if ($paginator->hasMorePages())
                    <li class="page-item">
                        <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next">Berikutnya</a>
                    </li>
                @else
                    <li class="page-item disabled" aria-disabled="true">
                        <span class="page-link">Berikutnya</span>
                    </li>
                @endif
            </ul>
        </div>

        <div class="d-none flex-sm-fill d-sm-flex align-items-sm-center justify-content-sm-between">
            <div>
                <p class="small text-muted mb-0">Menampilkan <span class="fw-semibold">{{ $paginator->firstItem() }}</span> sampai <span class="fw-semibold">{{ $paginator->lastItem() }}</span> dari <span class="fw-semibold">{{ $paginator->total() }}</span> hasil</p>
            </div>

            <div>
                <ul class="pagination mb-0">
                    {{-- Tautan halaman sebelumnya --}}
                    @if ($paginator->onFirstPage())
                        <li class="page-item disabled" aria-disabled="true" aria-label="Sebelumnya">
                            <span class="page-link" aria-hidden="true">&lsaquo;</span>
                        </li>
                    @else
                        <li class="page-item">
                            <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Sebelumnya">&lsaquo;</a>
                        </li>
                    @endif

                    {{-- Elemen paginasi --}}
                    @foreach ($elements as $element)
                        {{-- Pemisah titik-titik --}}
                        @if (is_string($element))
                            <li class="page-item disabled" aria-disabled="true"><span class="page-link">{{ $element }}</span></li>
                        @endif

                        {{-- Daftar tautan halaman --}}
                        @if (is_array($element))
                            @foreach ($element as $page => $url)
                                @if ($page == $paginator->currentPage())
                                    <li class="page-item active" aria-current="page"><span class="page-link">{{ $page }}</span></li>
                                @else
                                    <li class="page-item"><a class="page-link" href="{{ $url }}" aria-label="Halaman {{ $page }}">{{ $page }}</a></li>
                                @endif
                            @endforeach
                        @endif
                    @endforeach

                    {{-- Tautan halaman berikutnya --}}
                    @if ($paginator->hasMorePages())
                        <li class="page-item">
                            <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Berikutnya">&rsaquo;</a>
                        </li>
                    @else
                        <li class="page-item disabled" aria-disabled="true" aria-label="Berikutnya">
                            <span class="page-link" aria-hidden="true">&rsaquo;</span>
                        </li>
                    @endif
                </ul>
            </div>
        </div>
    </nav>
@endif
```

`resources/views/vendor/pagination/simple-bootstrap-5.blade.php`:

```blade
@if ($paginator->hasPages())
    <nav aria-label="Navigasi halaman">
        <ul class="pagination">
            {{-- Tautan halaman sebelumnya --}}
            @if ($paginator->onFirstPage())
                <li class="page-item disabled" aria-disabled="true">
                    <span class="page-link">Sebelumnya</span>
                </li>
            @else
                <li class="page-item">
                    <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">Sebelumnya</a>
                </li>
            @endif

            {{-- Tautan halaman berikutnya --}}
            @if ($paginator->hasMorePages())
                <li class="page-item">
                    <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next">Berikutnya</a>
                </li>
            @else
                <li class="page-item disabled" aria-disabled="true">
                    <span class="page-link">Berikutnya</span>
                </li>
            @endif
        </ul>
    </nav>
@endif
```

- [ ] **Step 18: Implementasi `data-table` (tool Write)**

`resources/views/components/data-table.blade.php`:

```blade
@props([
    'rows',
    'searchName' => 'q',
    'searchPlaceholder' => 'Cari…',
    'caption' => 'Daftar data',
    'emptyTitle' => 'Belum ada data',
    'emptyMessage' => null,
    'searchable' => true,
])
@php
    $keyword = request()->query($searchName);
    $keyword = is_string($keyword) ? $keyword : '';
    $hasQuery = collect(request()->query())
        ->except(['page', 'per_page'])
        ->filter(fn ($value) => is_array($value) ? $value !== [] : trim((string) $value) !== '')
        ->isNotEmpty();
    $perPage = request()->query('per_page');
    $perPage = is_string($perPage) ? $perPage : '';
    $searchId = $searchName.'-search';
    $currentUrl = url()->current();
@endphp
<div data-table {{ $attributes }}>
    <form method="GET" action="{{ $currentUrl }}" role="search" aria-label="Cari dan saring data" class="card card-body bg-light border-0 mb-3">
        <div class="row g-3 align-items-end">
            @if ($searchable)
                <div class="col-12 col-md-5 col-lg-4">
                    <label for="{{ $searchId }}" class="form-label fw-semibold">Cari</label>
                    <input type="search" id="{{ $searchId }}" name="{{ $searchName }}" value="{{ $keyword }}" class="form-control" placeholder="{{ $searchPlaceholder }}" maxlength="100" autocomplete="off">
                </div>
            @endif
            {{ $filters ?? '' }}
            <div class="col-12 col-md-auto d-flex gap-2">
                @if ($perPage !== '')<input type="hidden" name="per_page" value="{{ $perPage }}">@endif
                <x-loading-button icon="bi-search">Terapkan</x-loading-button>
                @if ($hasQuery)<a href="{{ $currentUrl }}" class="btn btn-outline-secondary">Reset</a>@endif
            </div>
        </div>
    </form>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
        <p class="mb-0 text-secondary" aria-live="polite" data-table-summary>@if ($rows->isNotEmpty())Menampilkan {{ $rows->firstItem() }}–{{ $rows->lastItem() }} dari {{ $rows->total() }} data @else Tidak ada data untuk ditampilkan. @endif</p>
        @isset($actions)
            <div class="d-flex flex-wrap gap-2">{{ $actions }}</div>
        @endisset
    </div>

    @if ($rows->isEmpty())
        @isset($empty)
            {{ $empty }}
        @else
            <x-empty-state :title="$emptyTitle" :message="$hasQuery ? 'Tidak ada data yang cocok dengan pencarian atau filter Anda.' : $emptyMessage" :icon="$hasQuery ? 'bi-search' : 'bi-inbox'">
                @if ($hasQuery)<a href="{{ $currentUrl }}" class="btn btn-outline-primary">Hapus filter</a>@endif
            </x-empty-state>
        @endisset
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle table-cards bg-white">
                <caption class="visually-hidden">{{ $caption }}</caption>
                <thead class="table-light">
                    <tr>{{ $head ?? '' }}</tr>
                </thead>
                <tbody>
                    {{ $slot }}
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $rows->withQueryString()->links() }}</div>
    @endif
</div>
```

- [ ] **Step 19: Jalankan tes — harus lulus; pint; commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=DataTableComponentTest
& $PHP vendor\bin\pint --test
git add app/Providers/AppServiceProvider.php resources/views/components/data-table.blade.php resources/views/vendor/pagination/bootstrap-5.blade.php resources/views/vendor/pagination/simple-bootstrap-5.blade.php tests/Feature/View/DataTableComponentTest.php
git commit -m "feat(fase-1): komponen data-table dan paginasi Bootstrap 5 berbahasa Indonesia"
```
Expected: PASS (14 tes); pint `PASS`.

#### Siklus E — `confirm-modal`

- [ ] **Step 20: Tulis tes yang gagal — `tests/Feature/View/ConfirmModalComponentTest.php`**

```php
<?php

namespace Tests\Feature\View;

use Tests\TestCase;

class ConfirmModalComponentTest extends TestCase
{
    public function test_modal_has_accessible_structure_and_script_hooks(): void
    {
        $this->blade('<x-confirm-modal id="hapus-dokter" title="Hapus dokter?" message="Data dokter akan dihapus." action="/admin/dokter/1" method="DELETE" variant="danger" confirm-label="Ya, hapus" />')
            ->assertSee('class="modal fade"', false)
            ->assertSee('id="hapus-dokter"', false)
            ->assertSee('tabindex="-1"', false)
            ->assertSee('aria-labelledby="hapus-dokter-title"', false)
            ->assertSee('data-confirm-modal', false)
            ->assertSee('<h2 class="modal-title h5" id="hapus-dokter-title">Hapus dokter?</h2>', false)
            ->assertSee('<p data-confirm-text>Data dokter akan dihapus.</p>', false)
            ->assertSee('<form method="POST" action="/admin/dokter/1" data-confirm-form>', false)
            ->assertSee('name="_token"', false)
            ->assertSee('<input type="hidden" name="_method" value="DELETE">', false)
            ->assertSee('data-bs-dismiss="modal"', false)
            ->assertSee('aria-label="Tutup"', false)
            ->assertSee('Batal')
            ->assertSee('class="btn btn-danger"', false)
            ->assertSee('<span data-loading-label>Ya, hapus</span>', false)
            ->assertDontSee('name="reason"', false);
    }

    public function test_modal_defaults(): void
    {
        $this->blade('<x-confirm-modal id="konfirmasi" title="Lanjutkan?" />')
            ->assertSee('<form method="POST" action="#" data-confirm-form>', false)
            ->assertSee('class="btn btn-primary"', false)
            ->assertSee('<span data-loading-label>Ya, lanjutkan</span>', false)
            ->assertSee('Batal')
            ->assertSee('name="_token"', false)
            ->assertDontSee('name="_method"', false);
    }

    public function test_modal_supports_patch_and_falls_back_for_unknown_method_and_variant(): void
    {
        $this->blade('<x-confirm-modal id="a" title="Ubah?" method="PATCH" />')
            ->assertSee('<input type="hidden" name="_method" value="PATCH">', false);

        $this->blade('<x-confirm-modal id="b" title="Ubah?" method="GET" variant="neon" />')
            ->assertDontSee('name="_method"', false)
            ->assertSee('class="btn btn-primary"', false)
            ->assertDontSee('btn-neon', false);
    }

    public function test_modal_with_required_reason_adds_a_labelled_textarea(): void
    {
        $this->blade('<x-confirm-modal id="batalkan" title="Batalkan pendaftaran?" :reason-required="true" />')
            ->assertSee('<label for="batalkan-reason" class="form-label fw-semibold">Alasan <span class="required-mark" aria-hidden="true">*</span></label>', false)
            ->assertSee('<textarea class="form-control" id="batalkan-reason" name="reason" rows="3" required aria-required="true"></textarea>', false);
    }

    public function test_modal_renders_extra_content_slot_and_custom_labels(): void
    {
        $this->blade('<x-confirm-modal id="c" title="Arsipkan?" cancel-label="Jangan" confirm-label="Arsipkan"><p class="text-danger">Peringatan tambahan.</p></x-confirm-modal>')
            ->assertSee('Peringatan tambahan.')
            ->assertSee('Jangan')
            ->assertSee('<span data-loading-label>Arsipkan</span>', false);
    }

    public function test_modal_escapes_title_message_action_and_labels(): void
    {
        $this->blade('<x-confirm-modal id="d" :title="$p" :message="$p" :action="$p" :confirm-label="$p" :cancel-label="$p" />', ['p' => '"><script>alert(1)</script>'])
            ->assertSee('&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }
}
```

- [ ] **Step 21: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=ConfirmModalComponentTest
```
Expected: FAIL — `InvalidArgumentException: Unable to locate a class or view for component [confirm-modal]`.

- [ ] **Step 22: Implementasi `confirm-modal` (tool Write)**

`resources/views/components/confirm-modal.blade.php`:

```blade
@props([
    'id',
    'title',
    'message' => '',
    'confirmLabel' => 'Ya, lanjutkan',
    'cancelLabel' => 'Batal',
    'variant' => 'primary',
    'method' => 'POST',
    'reasonRequired' => false,
    'action' => '#',
])
@php
    $verb = strtoupper((string) $method);
    $verb = in_array($verb, ['POST', 'PATCH', 'DELETE'], true) ? $verb : 'POST';
    $variant = in_array($variant, ['danger', 'primary'], true) ? $variant : 'primary';
@endphp
<div class="modal fade" id="{{ $id }}" tabindex="-1" aria-labelledby="{{ $id }}-title" aria-hidden="true" data-confirm-modal>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ $action }}" data-confirm-form>
                @csrf
                @if ($verb !== 'POST')
                    @method($verb)
                @endif
                <div class="modal-header">
                    <h2 class="modal-title h5" id="{{ $id }}-title">{{ $title }}</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <p data-confirm-text>{{ $message }}</p>
                    {{ $slot }}
                    @if ($reasonRequired)
                        <div class="mb-0">
                            <label for="{{ $id }}-reason" class="form-label fw-semibold">Alasan <span class="required-mark" aria-hidden="true">*</span></label>
                            <textarea class="form-control" id="{{ $id }}-reason" name="reason" rows="3" required aria-required="true"></textarea>
                        </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ $cancelLabel }}</button>
                    <x-loading-button :variant="$variant">{{ $confirmLabel }}</x-loading-button>
                </div>
            </form>
        </div>
    </div>
</div>
```

- [ ] **Step 23: Jalankan tes — harus lulus; pint; commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=ConfirmModalComponentTest
& $PHP vendor\bin\pint --test
git add resources/views/components/confirm-modal.blade.php tests/Feature/View/ConfirmModalComponentTest.php
git commit -m "feat(fase-1): komponen confirm-modal dengan alasan wajib opsional"
```
Expected: PASS (6 tes); pint `PASS`.

- [ ] **Step 24: Jalankan seluruh tes komponen dan suite penuh**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test tests\Feature\View
& $PHP artisan test
& $PHP vendor\bin\pint --test
```
Expected: semua PASS; pint `PASS`.

---

### Task 12: `SafeRedirect` dan `ListFilterRequest`

**Files:**
- Create: `app/Support/SafeRedirect.php`, `app/Http/Requests/ListFilterRequest.php`, `tests/Support/Requests/StatusSortFilterRequest.php`
- Test: `tests/Unit/Support/SafeRedirectTest.php`, `tests/Feature/Http/ListFilterRequestTest.php`

**Interfaces:**
- Consumes: — (hanya framework; `route('home')` dan `route('patient.appointments.create')` dievaluasi saat dipanggil, jadi tes mendaftarkan route uji).
- Produces: `App\Support\SafeRedirect` (`final`): `back(Request $request): string` persis 11 §11.2; `internalPath(?string $value): ?string` (lolos hanya bila diawali `/`, bukan `//`, tanpa `\`, tanpa skema/host, tanpa karakter kontrol, ≤ 2000 byte; selain itu `null`). `App\Http\Requests\ListFilterRequest extends FormRequest`: `PER_PAGE_OPTIONS = [10, 25, 50]`; `authorize(): true`; `rules()` = parameter baku V-45; hook `protected statusValues(): array` dan `protected sortable(): array` (default `[]` ⇒ parameter `status`/`urut` tidak punya aturan sehingga diabaikan); `failedValidation()` tidak melempar, hanya `session()->now('warning', 'Filter tidak valid dan diabaikan.')`; `filters(): array`; `perPage(int $default = 25): int`.

**Keputusan teknis (diverifikasi pada `vendor/laravel/framework` 12.69):**
- `Validator::valid()` mengembalikan `array_diff_key($this->data, $this->attributesThatHaveMessages())`, yaitu **seluruh** input minus yang gagal — termasuk kunci yang tidak punya aturan (mis. `foo=bar`, `status=zzz` pada kelas dasar). Karena itu `filters()` mengiris hasil `valid()` dengan `array_keys($this->rules())` dan membuang nilai kosong/`array`. (`validated()` tidak dipakai: melempar `ValidationException` bila ada galat.)
- `ValidatesWhenResolvedTrait::validateResolved()` memanggil `failedValidation()` bila `fails()`; karena ditimpa tanpa `throw`, eksekusi lanjut ke `passedValidation()` dan controller tetap berjalan (HTTP 200). `getValidatorInstance()` memoize validator sehingga `valid()` tidak mengulang validasi.
- `session()->now($key, $value)` = `put` + menandai kunci pada `_flash.old`, sehingga tersedia pada request yang sama (dirender `<x-flash-alert>` di layout) dan tidak bertahan ke request berikutnya.
- Rule `in` menolak nilai `array` tanpa galat PHP (`! is_array($value) && in_array(...)`), dan `string`/`integer` pada `q[]=x`/`page[]=1` gagal bersih ⇒ tidak ada 500.
- Route yang diberi nama setelah aplikasi *boot* harus didaftarkan ulang ke tabel nama: `Route::get(...)->name(...)` lalu `$this->app['router']->getRoutes()->refreshNameLookups()` (nama di-set secara fluent setelah `RouteCollection::add`).

#### Siklus A — `SafeRedirect`

- [ ] **Step 1: Tulis tes yang gagal — `tests/Unit/Support/SafeRedirectTest.php`**

```php
<?php

namespace Tests\Unit\Support;

use App\Support\SafeRedirect;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SafeRedirectTest extends TestCase
{
    /** @return array<string, array{?string, ?string}> */
    public static function internalPaths(): array
    {
        return [
            'path sederhana' => ['/admin/dokter', '/admin/dokter'],
            'dengan query' => ['/admin/x?y=1', '/admin/x?y=1'],
            'akar situs' => ['/', '/'],
            'dengan fragmen' => ['/admin/dokter?page=2#tabel', '/admin/dokter?page=2#tabel'],
            'dengan persen-encoding' => ['/admin/dokter?q=dr%20andi', '/admin/dokter?q=dr%20andi'],
            'dengan titik dua di query' => ['/admin?next=/a:b', '/admin?next=/a:b'],
            'tepat 2000 karakter' => ['/'.str_repeat('a', 1999), '/'.str_repeat('a', 1999)],
            'null' => [null, null],
            'kosong' => ['', null],
            'url absolut https' => ['https://situs-lain.test', null],
            'url absolut http' => ['http://situs-lain.test/admin', null],
            'protokol relatif' => ['//evil.test', null],
            'tiga garis miring' => ['///evil.test', null],
            'backslash setelah garis miring' => ['/\\evil.test', null],
            'backslash di tengah' => ['/admin\\x', null],
            'skema javascript' => ['javascript:alert(1)', null],
            'skema data' => ['data:text/html,x', null],
            'tanpa garis miring awal' => ['admin/dokter', null],
            'spasi di depan' => [' /admin', null],
            'tab menyamarkan protokol relatif' => ["/\t/evil.test", null],
            'newline (header injection)' => ["/admin\nSet-Cookie: a=b", null],
            'carriage return' => ["/admin\rx", null],
            'null byte' => ["/admin\0x", null],
            'karakter DEL' => ["/admin\x7Fx", null],
            'lebih dari 2000 karakter' => ['/'.str_repeat('a', 2000), null],
        ];
    }

    #[DataProvider('internalPaths')]
    public function test_internal_path_only_accepts_safe_local_paths(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, SafeRedirect::internalPath($input));
    }

    private function registerRoute(string $name, string $uri): void
    {
        Route::get($uri, fn () => 'ok')->name($name);
        $this->app['router']->getRoutes()->refreshNameLookups();
    }

    private function requestOnRoute(string $routeName, array $input = []): Request
    {
        $route = (new RoutingRoute(['POST'], '/uji', fn () => 'ok'))->name($routeName);
        $request = Request::create('/uji', 'POST', $input);
        $request->setRouteResolver(fn () => $route);
        $this->app->instance('request', $request);

        return $request;
    }

    public function test_back_returns_the_previous_url_when_there_is_one(): void
    {
        $this->registerRoute('home', '/_uji/beranda');

        $request = Request::create('/admin/dokter/1', 'POST');
        $request->headers->set('referer', 'http://localhost/admin/dokter?page=2');
        $this->app->instance('request', $request);

        $this->assertSame('http://localhost/admin/dokter?page=2', SafeRedirect::back($request));
    }

    public function test_back_falls_back_to_home_without_a_previous_url(): void
    {
        $this->registerRoute('home', '/_uji/beranda');

        $request = Request::create('/admin/dokter/1', 'POST');
        $this->app->instance('request', $request);

        $this->assertSame(route('home'), SafeRedirect::back($request));
    }

    /** @return array<string, array{string}> */
    public static function wizardRoutes(): array
    {
        return [
            'tinjau' => ['patient.appointments.review'],
            'simpan' => ['patient.appointments.store'],
        ];
    }

    #[DataProvider('wizardRoutes')]
    public function test_back_from_post_only_wizard_routes_returns_to_the_wizard_step(string $routeName): void
    {
        $this->registerRoute('patient.appointments.create', '/_uji/pasien/pendaftaran/baru');

        $request = $this->requestOnRoute($routeName, ['practice_session_id' => '7']);

        $this->assertSame(route('patient.appointments.create', ['sesi' => '7']), SafeRedirect::back($request));
    }

    public function test_back_from_wizard_without_session_id_omits_the_parameter(): void
    {
        $this->registerRoute('patient.appointments.create', '/_uji/pasien/pendaftaran/baru');

        $request = $this->requestOnRoute('patient.appointments.store');

        $this->assertSame(route('patient.appointments.create'), SafeRedirect::back($request));
    }

    public function test_back_does_not_need_the_home_route_for_wizard_routes(): void
    {
        $this->registerRoute('patient.appointments.create', '/_uji/pasien/pendaftaran/baru');

        $request = $this->requestOnRoute('patient.appointments.review', ['practice_session_id' => '3']);

        $this->assertStringContainsString('sesi=3', SafeRedirect::back($request));
    }
}
```

- [ ] **Step 2: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=SafeRedirectTest
```
Expected: FAIL — `Error: Class "App\Support\SafeRedirect" not found` pada semua tes.

- [ ] **Step 3: Implementasi `SafeRedirect` (tool Write)**

`app/Support/SafeRedirect.php`:

```php
<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

final class SafeRedirect
{
    private const MAX_LENGTH = 2000;

    /**
     * Tujuan kembali setelah galat/penolakan. Halaman "tinjau" dan "simpan" wizard adalah hasil POST
     * (tidak bisa di-GET), sehingga kembali ke langkah wizard, bukan ke back().
     */
    public static function back(Request $request): string
    {
        if ($request->routeIs('patient.appointments.review', 'patient.appointments.store')) {
            return route('patient.appointments.create', array_filter(['sesi' => $request->input('practice_session_id')]));
        }

        return url()->previous(route('home'));
    }

    /**
     * Mengembalikan $value hanya bila berupa path internal yang aman (parameter `kembali`), selain itu null.
     */
    public static function internalPath(?string $value): ?string
    {
        if ($value === null || $value === '' || strlen($value) > self::MAX_LENGTH) {
            return null;
        }

        // Harus path absolut di situs ini: diawali "/", bukan "//" (protokol relatif).
        if (! str_starts_with($value, '/') || str_starts_with($value, '//')) {
            return null;
        }

        // Backslash dan karakter kontrol (tab/CR/LF dibuang browser sehingga "/\t/evil" menjadi "//evil").
        if (str_contains($value, '\\') || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            return null;
        }

        $parts = parse_url($value);

        if ($parts === false || isset($parts['scheme']) || isset($parts['host'])) {
            return null;
        }

        return $value;
    }
}
```

- [ ] **Step 4: Jalankan tes — harus lulus; pint; commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=SafeRedirectTest
& $PHP vendor\bin\pint --test
git add app/Support/SafeRedirect.php tests/Unit/Support/SafeRedirectTest.php
git commit -m "feat(fase-1): SafeRedirect untuk tujuan kembali dan path internal"
```
Expected: PASS (31 tes: 25 data provider path + 2 `back()` dasar + 2 wizard + 2 lainnya); pint `PASS`.

#### Siklus B — `ListFilterRequest`

- [ ] **Step 5: Tulis fixture dan tes yang gagal**

`tests/Support/Requests/StatusSortFilterRequest.php`:

```php
<?php

namespace Tests\Support\Requests;

use App\Http\Requests\ListFilterRequest;

class StatusSortFilterRequest extends ListFilterRequest
{
    protected function statusValues(): array
    {
        return ['active', 'inactive'];
    }

    protected function sortable(): array
    {
        return ['name', 'created_at'];
    }
}
```

`tests/Feature/Http/ListFilterRequestTest.php`:

```php
<?php

namespace Tests\Feature\Http;

use App\Http\Requests\ListFilterRequest;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Requests\StatusSortFilterRequest;
use Tests\TestCase;

class ListFilterRequestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->get('/_uji/filter', fn (ListFilterRequest $request) => response()->json([
            'filters' => $request->filters(),
            'per_page' => $request->perPage(),
            'warning' => session('warning'),
        ]));

        Route::middleware('web')->get('/_uji/filter-status', fn (StatusSortFilterRequest $request) => response()->json([
            'filters' => $request->filters(),
            'per_page' => $request->perPage(),
            'warning' => session('warning'),
        ]));
    }

    private function resolve(array $query, string $class = ListFilterRequest::class): ListFilterRequest
    {
        $request = $class::create('/_uji/filter', 'GET', $query);
        $request->setContainer($this->app);
        $request->setRedirector($this->app->make('redirect'));
        $request->validateResolved();

        return $request;
    }

    public function test_valid_parameters_are_returned_as_filters(): void
    {
        $this->get('/_uji/filter?q=jantung&kategori=dokter-anak&per_page=50&page=2')
            ->assertOk()
            ->assertJsonCount(4, 'filters')
            ->assertJsonPath('filters.q', 'jantung')
            ->assertJsonPath('filters.kategori', 'dokter-anak')
            ->assertJsonPath('filters.per_page', '50')
            ->assertJsonPath('filters.page', '2')
            ->assertJsonPath('per_page', 50)
            ->assertJsonPath('warning', null);
    }

    public function test_val_07_invalid_parameters_are_ignored_without_server_error(): void
    {
        $this->get('/_uji/filter?status=zzz&kembali='.urlencode('https://situs-lain.test').'&q[]=x&per_page=7')
            ->assertOk()
            ->assertJsonPath('filters', [])
            ->assertJsonMissingPath('filters.kembali')
            ->assertJsonPath('per_page', 25)
            ->assertJsonPath('warning', 'Filter tidak valid dan diabaikan.');
    }

    public function test_val_07_internal_return_path_is_kept(): void
    {
        $this->get('/_uji/filter?kembali='.urlencode('/admin/x?y=1'))
            ->assertOk()
            ->assertJsonPath('filters.kembali', '/admin/x?y=1')
            ->assertJsonPath('warning', null);
    }

    /** @return array<string, array{string}> */
    public static function unsafeReturnTargets(): array
    {
        return [
            'url absolut' => ['https://evil.test'],
            'protokol relatif' => ['//evil.test'],
            'backslash' => ['/\\evil.test'],
            'javascript' => ['javascript:alert(1)'],
            'tab' => ["/\t/evil.test"],
            'header injection' => ["/x\r\nSet-Cookie: a=b"],
        ];
    }

    #[DataProvider('unsafeReturnTargets')]
    public function test_val_07_unsafe_return_targets_are_dropped(string $target): void
    {
        $this->get('/_uji/filter?kembali='.urlencode($target))
            ->assertOk()
            ->assertJsonMissingPath('filters.kembali')
            ->assertJsonPath('warning', null);
    }

    public function test_unknown_parameters_are_never_returned(): void
    {
        $this->get('/_uji/filter?foo=bar&q=a')
            ->assertOk()
            ->assertJsonCount(1, 'filters')
            ->assertJsonPath('filters.q', 'a');
    }

    public function test_status_and_sort_have_no_rules_in_the_base_request(): void
    {
        $this->get('/_uji/filter?status=active&urut=name')
            ->assertOk()
            ->assertJsonPath('filters', [])
            ->assertJsonPath('warning', null);
    }

    public function test_subclass_hooks_whitelist_status_and_sort_including_descending(): void
    {
        $this->get('/_uji/filter-status?status=active&urut=-name&q=a')
            ->assertOk()
            ->assertJsonPath('filters.status', 'active')
            ->assertJsonPath('filters.urut', '-name')
            ->assertJsonPath('filters.q', 'a')
            ->assertJsonPath('warning', null);

        $this->get('/_uji/filter-status?urut=created_at')
            ->assertOk()
            ->assertJsonPath('filters.urut', 'created_at');
    }

    public function test_subclass_ignores_values_outside_the_whitelist(): void
    {
        $this->get('/_uji/filter-status?status=zzz&urut=password')
            ->assertOk()
            ->assertJsonPath('filters', [])
            ->assertJsonPath('warning', 'Filter tidak valid dan diabaikan.');

        $this->get('/_uji/filter-status?urut=--name&status=ACTIVE')
            ->assertOk()
            ->assertJsonPath('filters', []);
    }

    public function test_array_parameters_do_not_cause_server_errors(): void
    {
        foreach (['q[]=x', 'page[]=1', 'per_page[]=10', 'kategori[]=a', 'kembali[]=/x'] as $query) {
            $this->get('/_uji/filter?'.$query)
                ->assertOk()
                ->assertJsonPath('filters', []);
        }

        foreach (['status[]=active', 'urut[]=name', 'status[a]=b'] as $query) {
            $this->get('/_uji/filter-status?'.$query)
                ->assertOk()
                ->assertJsonPath('filters', []);
        }
    }

    public function test_page_must_be_a_positive_integer(): void
    {
        foreach (['page=0', 'page=-1', 'page=abc', 'page=1.5'] as $query) {
            $this->get('/_uji/filter?'.$query)
                ->assertOk()
                ->assertJsonMissingPath('filters.page');
        }

        $this->get('/_uji/filter?page=1')->assertJsonPath('filters.page', '1');
    }

    public function test_keyword_is_limited_to_one_hundred_characters(): void
    {
        $this->get('/_uji/filter?q='.str_repeat('a', 100))
            ->assertJsonPath('filters.q', str_repeat('a', 100))
            ->assertJsonPath('warning', null);

        $this->get('/_uji/filter?q='.str_repeat('a', 101))
            ->assertOk()
            ->assertJsonMissingPath('filters.q')
            ->assertJsonPath('warning', 'Filter tidak valid dan diabaikan.');
    }

    public function test_empty_values_are_not_filters(): void
    {
        $this->get('/_uji/filter?q=&kategori=&per_page=&page=&kembali=')
            ->assertOk()
            ->assertJsonPath('filters', [])
            ->assertJsonPath('warning', null);
    }

    public function test_per_page_accepts_only_the_documented_options(): void
    {
        $this->assertSame([10, 25, 50], ListFilterRequest::PER_PAGE_OPTIONS);

        foreach ([10, 25, 50] as $option) {
            $this->assertSame($option, $this->resolve(['per_page' => (string) $option])->perPage());
        }

        $this->assertSame(25, $this->resolve([])->perPage());
        $this->assertSame(25, $this->resolve(['per_page' => '7'])->perPage());
        $this->assertSame(10, $this->resolve(['per_page' => '7'])->perPage(10));
        $this->assertSame(10, $this->resolve([])->perPage(10));
        $this->assertSame(50, $this->resolve(['per_page' => '50'])->perPage(10));
    }

    public function test_failed_validation_does_not_throw_and_flashes_a_warning_for_the_current_request(): void
    {
        $request = $this->resolve(['q' => ['x'], 'page' => 'abc']);

        $this->assertSame([], $request->filters());
        $this->assertSame('Filter tidak valid dan diabaikan.', session('warning'));
    }

    public function test_valid_input_does_not_flash_a_warning(): void
    {
        $this->resolve(['q' => 'andi']);

        $this->assertNull(session('warning'));
    }

    public function test_request_is_always_authorized_and_declares_the_documented_rules(): void
    {
        $request = new ListFilterRequest;

        $this->assertTrue($request->authorize());
        $this->assertSame(['q', 'kategori', 'per_page', 'page', 'kembali'], array_keys($request->rules()));

        $rules = (new StatusSortFilterRequest)->rules();

        $this->assertSame(['q', 'kategori', 'per_page', 'page', 'kembali', 'status', 'urut'], array_keys($rules));
    }
}
```

- [ ] **Step 6: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=ListFilterRequestTest
```
Expected: FAIL — tes yang memanggil `resolve()` atau `new ListFilterRequest` gagal dengan `Error: Class "App\Http\Requests\ListFilterRequest" not found`; tes berbasis route gagal dengan `BindingResolutionException: Target class [App\Http\Requests\ListFilterRequest] does not exist.` (500 dari route uji).

- [ ] **Step 7: Implementasi `ListFilterRequest` (tool Write)**

`app/Http/Requests/ListFilterRequest.php`:

```php
<?php

namespace App\Http\Requests;

use App\Support\SafeRedirect;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Basis permintaan GET-filter longgar (12 V-45): parameter yang tidak valid diabaikan,
 * tidak pernah menimbulkan galat 500 atau pengalihan galat validasi (00 I.5).
 */
class ListFilterRequest extends FormRequest
{
    public const PER_PAGE_OPTIONS = [10, 25, 50];

    /** @var array<string, string>|null */
    private ?array $resolvedFilters = null;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'q' => ['nullable', 'string', 'max:100'],
            'kategori' => ['nullable', 'string', 'max:150'],
            'per_page' => ['nullable', Rule::in(self::PER_PAGE_OPTIONS)],
            'page' => ['nullable', 'integer', 'min:1'],
            'kembali' => ['nullable', 'string', 'max:2000'],
        ];

        $statuses = $this->statusValues();

        if ($statuses !== []) {
            $rules['status'] = ['nullable', Rule::in($statuses)];
        }

        $sortable = $this->sortable();

        if ($sortable !== []) {
            $rules['urut'] = ['nullable', Rule::in(array_merge(
                $sortable,
                array_map(fn (string $column): string => '-'.$column, $sortable),
            ))];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'q.string' => 'Kata kunci tidak valid.',
            'q.max' => 'Kata kunci maksimal 100 karakter.',
            'status.in' => 'Status tidak valid.',
            'kategori.string' => 'Pilihan filter tidak valid.',
            'kategori.max' => 'Pilihan filter tidak valid.',
            'urut.in' => 'Urutan tidak valid.',
            'per_page.in' => 'Jumlah baris per halaman tidak valid.',
            'page.integer' => 'Nomor halaman tidak valid.',
            'page.min' => 'Nomor halaman tidak valid.',
        ];
    }

    /**
     * Nilai `status` yang sah untuk entitas layar ini. Kosong = parameter `status` diabaikan.
     *
     * @return list<string>
     */
    protected function statusValues(): array
    {
        return [];
    }

    /**
     * Kolom yang boleh diurutkan lewat `urut` (awalan "-" = menurun). Kosong = parameter `urut` diabaikan.
     *
     * @return list<string>
     */
    protected function sortable(): array
    {
        return [];
    }

    /**
     * Tidak melempar: parameter yang gagal hanya diabaikan dan pengguna diberi tahu lewat flash
     * `warning` pada request yang sama (tidak bertahan ke request berikutnya).
     */
    protected function failedValidation(Validator $validator): void
    {
        session()->now('warning', 'Filter tidak valid dan diabaikan.');
    }

    /**
     * Hanya parameter yang lolos aturan. Validator::valid() mengembalikan SELURUH input minus yang gagal,
     * sehingga hasilnya diiris ke kunci yang memang memiliki aturan.
     *
     * @return array<string, string>
     */
    public function filters(): array
    {
        if ($this->resolvedFilters !== null) {
            return $this->resolvedFilters;
        }

        $valid = $this->getValidatorInstance()->valid();
        $filters = [];

        foreach (array_keys($this->rules()) as $key) {
            if (! array_key_exists($key, $valid)) {
                continue;
            }

            $value = $valid[$key];

            if ($value === null || $value === '' || is_array($value)) {
                continue;
            }

            $filters[$key] = (string) $value;
        }

        if (isset($filters['kembali'])) {
            $path = SafeRedirect::internalPath($filters['kembali']);

            if ($path === null) {
                unset($filters['kembali']);
            } else {
                $filters['kembali'] = $path;
            }
        }

        return $this->resolvedFilters = $filters;
    }

    public function perPage(int $default = 25): int
    {
        $value = $this->filters()['per_page'] ?? null;

        return $value === null ? $default : (int) $value;
    }
}
```

- [ ] **Step 8: Jalankan tes — harus lulus; pint; commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=ListFilterRequestTest
& $PHP vendor\bin\pint --test
git add app/Http/Requests/ListFilterRequest.php tests/Support/Requests/StatusSortFilterRequest.php tests/Feature/Http/ListFilterRequestTest.php
git commit -m "feat(fase-1): ListFilterRequest longgar untuk filter GET dengan peringatan flash"
```
Expected: PASS (21 tes); pint `PASS`.

- [ ] **Step 9: Verifikasi gerbang Task 9–12**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test
& $PHP vendor\bin\pint --test
npm run build
git status --short
git log --oneline -n 12
```
Expected: seluruh tes PASS; pint `PASS`; `npm run build` sukses (manifest memuat `resources/scss/app.scss`, `resources/js/app.js`, `resources/js/admin.js`); `git status --short` kosong; `git log` memuat commit Task 9 (1), Task 10 (4), Task 11 (5), Task 12 (2).

---

### Task 13: Layout publik, pasien, auth; menu navigasi; halaman galat

**Files:**
- Create: `config/navigation.php`
- Create: `app/Support/NavigationMenu.php`
- Create: `resources/views/components/layouts/auth.blade.php`
- Create: `resources/views/components/layouts/public.blade.php`
- Create: `resources/views/components/layouts/patient.blade.php`
- Create: `resources/views/public/home.blade.php`
- Create: `resources/views/errors/layout.blade.php`, `resources/views/errors/403.blade.php`, `404.blade.php`, `419.blade.php`, `429.blade.php`, `500.blade.php`, `503.blade.php`
- Modify: `routes/web.php`
- Modify: `app/Providers/AppServiceProvider.php` (`View::composer` ⇒ `$site`)
- Modify: `tests/TestCase.php` (F1-R14: `withoutVite()` di `setUp()`)
- Modify: `tests/Feature/SmokeTest.php`
- Delete: `resources/views/welcome.blade.php`
- Test: `tests/Feature/Support/NavigationMenuTest.php`, `tests/Feature/View/LayoutsTest.php`, `tests/Feature/Errors/ErrorPagesTest.php`

**Interfaces:**
- Consumes: `SettingsService` (Task 7: `string()`, `logoUrl()`, `whatsappUrl()`), `RolePermissionSeeder::PERMISSIONS` (Task 3), `Tests\Support\InteractsWithStaff` (Task 3), `x-breadcrumb`, `x-flash-alert`, `x-form.errors-summary` (Task 10–11), kelas CSS `.skip-link`, `.auth-card` dan skrip `app.js` (Task 9).
- Produces:
  - `config('navigation.public' | 'patient' | 'footer' | 'admin')`. Tiga bentuk entri: **tautan** `['label', 'route', 'icon'?, 'permission'?, 'active'?, 'emphasis'?]`; **induk dropdown** `['label', 'children' => [tautan...]]`; **grup** (hanya `admin`) `['group', 'items' => [tautan...]]`. `permission` memakai sintaks `a|b` (salah satu cukup). `active` = daftar pola `routeIs` (bawaan: nama route item itu sendiri). Semua route item **harus tanpa parameter wajib** (dibuat dengan `route($name)`).
  - `App\Support\NavigationMenu::items(string $area, ?User $user = null): array` — membuang tautan yang `! Route::has(route)` atau (bila `permission`) pengguna tidak punya **salah satu** izin (`$user->canAny(explode('|', ...))`; `null` ⇒ ditolak); membuang induk/grup yang kosong; menambah `url` dan `active` (bool) pada tautan; induk mendapat `active` = salah satu anak aktif. Area tak dikenal ⇒ `[]`.
  - `x-layouts.public` (`title`, `metaDescription`, `noindex` = false) — **`noindex` adalah prop internal baru** agar `x-layouts.patient` dapat membungkusnya tanpa menggandakan header/footer; slot default dan `breadcrumb`. `x-layouts.patient` (`title`; slot default dan `breadcrumb`). `x-layouts.auth` (`title`, `subtitle`; slot default dan `footer`). Semua membaca `$site` (`SettingsService`) dari `View::composer(['components.layouts.*'])`.
  - Route `home` = `Route::view('/', 'public.home')->name('home')` (F1-R3).
  - Halaman galat 10 §2.5 / 11 §11.4: `errors/layout` mandiri (tanpa query DB, memakai `config('app.name')`); `errors.403` menerima `$detail` opsional; `errors.429` menerima `$retryAfter`.
  - `Tests\TestCase::setUp()` memanggil `withoutVite()` (F1-R14), pengaman `setUpTraits()` tetap utuh.

**Catatan penting untuk implementer Task 13:**
- Route uji di dalam tes didaftarkan lewat `Route::middleware('web')->get(...)` (grup `web` wajib agar `$errors` dan sesi tersedia) lalu `Route::getRoutes()->refreshNameLookups()` (nama route yang diberikan lewat `->name()` setelah `get()` baru masuk tabel nama setelah di-*refresh*; terverifikasi di `RouteCollection::addLookups()`).
- Tes yang merender layout memakai `RefreshDatabase` karena `$site` membaca tabel `website_settings`. `ErrorPagesTest` **sengaja tanpa** `RefreshDatabase` (halaman galat tidak boleh menyentuh DB).

---

#### Siklus A — Menu navigasi (`config/navigation.php` + `NavigationMenu`)

- [ ] **Step 1: Tulis tes `NavigationMenuTest` (gagal dulu)**

Buat `tests/Feature/Support/NavigationMenuTest.php`:

```php
<?php

namespace Tests\Feature\Support;

use App\Support\NavigationMenu;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

class NavigationMenuTest extends TestCase
{
    use InteractsWithStaff;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->get('/_uji/satu', fn () => 'satu')->name('uji.satu');
        Route::middleware('web')->get('/_uji/dua', fn () => 'dua')->name('uji.dua');
        Route::middleware('web')->get('/_uji/aktif', fn () => response()->json(NavigationMenu::items('uji')))->name('uji.aktif');
        Route::getRoutes()->refreshNameLookups();
    }

    public function test_items_without_a_registered_route_are_dropped(): void
    {
        config(['navigation.uji' => [
            ['label' => 'Ada', 'route' => 'uji.satu'],
            ['label' => 'Tidak Ada', 'route' => 'rute.belum.ada'],
        ]]);

        $items = NavigationMenu::items('uji');

        $this->assertCount(1, $items);
        $this->assertSame('Ada', $items[0]['label']);
        $this->assertSame(route('uji.satu'), $items[0]['url']);
        $this->assertFalse($items[0]['active']);
    }

    public function test_unknown_area_returns_an_empty_list(): void
    {
        $this->assertSame([], NavigationMenu::items('area-yang-tidak-ada'));
    }

    public function test_empty_groups_and_dropdowns_are_removed_but_partial_ones_are_kept(): void
    {
        config(['navigation.uji' => [
            ['group' => 'Grup Kosong', 'items' => [
                ['label' => 'Belum Ada', 'route' => 'rute.belum.ada'],
            ]],
            ['group' => 'Grup Terisi', 'items' => [
                ['label' => 'Ada', 'route' => 'uji.satu'],
                ['label' => 'Belum Ada', 'route' => 'rute.belum.ada'],
            ]],
            ['label' => 'Induk Kosong', 'children' => [
                ['label' => 'Belum Ada', 'route' => 'rute.belum.ada'],
            ]],
            ['label' => 'Induk Terisi', 'children' => [
                ['label' => 'Anak Ada', 'route' => 'uji.dua'],
                ['label' => 'Anak Belum Ada', 'route' => 'rute.belum.ada'],
            ]],
        ]]);

        $items = NavigationMenu::items('uji');

        $this->assertCount(2, $items);
        $this->assertSame('Grup Terisi', $items[0]['group']);
        $this->assertCount(1, $items[0]['items']);
        $this->assertSame('Induk Terisi', $items[1]['label']);
        $this->assertCount(1, $items[1]['children']);
        $this->assertSame('Anak Ada', $items[1]['children'][0]['label']);
    }

    public function test_permission_alternatives_keep_item_when_user_has_any_of_them(): void
    {
        config(['navigation.uji' => [
            ['label' => 'Kelola Dokter atau Staf', 'route' => 'uji.satu', 'permission' => 'staff.manage|doctors.manage'],
            ['label' => 'Lihat Pendaftaran atau Staf', 'route' => 'uji.dua', 'permission' => 'staff.manage|appointments.view'],
        ]]);
        $konten = $this->createStaff('content-admin');
        $petugas = $this->createStaff('registration-officer');
        $super = $this->createStaff('super-admin');

        $this->assertSame(['Kelola Dokter atau Staf'], array_column(NavigationMenu::items('uji', $konten), 'label'));
        $this->assertSame(['Lihat Pendaftaran atau Staf'], array_column(NavigationMenu::items('uji', $petugas), 'label'));
        $this->assertCount(2, NavigationMenu::items('uji', $super));
    }

    public function test_items_with_permission_are_hidden_from_guests_but_plain_items_stay(): void
    {
        config(['navigation.uji' => [
            ['label' => 'Tanpa Izin', 'route' => 'uji.satu'],
            ['label' => 'Butuh Izin', 'route' => 'uji.dua', 'permission' => 'dashboard.view'],
        ]]);

        $this->assertSame(['Tanpa Izin'], array_column(NavigationMenu::items('uji', null), 'label'));
    }

    public function test_active_flag_follows_the_current_route_and_custom_patterns(): void
    {
        config(['navigation.uji' => [
            ['label' => 'Aktif', 'route' => 'uji.aktif'],
            ['label' => 'Pola', 'route' => 'uji.satu', 'active' => ['uji.aktif', 'uji.lain.*']],
            ['label' => 'Tidak', 'route' => 'uji.dua'],
            ['label' => 'Induk', 'children' => [
                ['label' => 'Anak', 'route' => 'uji.aktif'],
                ['label' => 'Anak Dua', 'route' => 'uji.dua'],
            ]],
        ]]);

        $items = $this->getJson('/_uji/aktif')->assertOk()->json();

        $this->assertTrue($items[0]['active']);
        $this->assertTrue($items[1]['active']);
        $this->assertFalse($items[2]['active']);
        $this->assertTrue($items[3]['active']);
        $this->assertTrue($items[3]['children'][0]['active']);
        $this->assertFalse($items[3]['children'][1]['active']);
    }

    public function test_real_admin_config_only_uses_permissions_from_the_catalogue_and_bootstrap_icons(): void
    {
        $entries = config('navigation.admin');
        $this->assertIsArray($entries);

        $links = [];
        foreach ($entries as $entry) {
            foreach ($entry['items'] ?? [$entry] as $link) {
                $links[] = $link;
            }
        }

        $this->assertNotEmpty($links);
        foreach ($links as $link) {
            $this->assertArrayHasKey('route', $link);
            $this->assertStringStartsWith('admin.', $link['route']);
            $this->assertStringStartsWith('bi-', $link['icon']);
            foreach (explode('|', $link['permission']) as $permission) {
                $this->assertContains($permission, RolePermissionSeeder::PERMISSIONS, "Izin {$permission} tidak ada di katalog.");
            }
        }
    }

    public function test_real_public_and_footer_config_keep_the_canonical_route_names(): void
    {
        $names = [];
        array_walk_recursive(config('navigation.public'), function ($value, $key) use (&$names): void {
            if ($key === 'route') {
                $names[] = $value;
            }
        });
        array_walk_recursive(config('navigation.footer'), function ($value, $key) use (&$names): void {
            if ($key === 'route') {
                $names[] = $value;
            }
        });

        $this->assertEqualsCanonicalizing([
            'about', 'vision-mission', 'clinics.index', 'doctors.index', 'schedules.index', 'facilities.index',
            'rooms.index', 'insurance', 'registration-guide', 'articles.index', 'galleries.index', 'faq', 'contact',
        ], array_values(array_unique($names)));
    }
}
```

- [ ] **Step 2: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=NavigationMenuTest
```
Expected: FAIL — `Class "App\Support\NavigationMenu" not found` pada semua tes (kelasnya belum ada).

- [ ] **Step 3: Tulis `config/navigation.php`**

Salin item, route, ikon, dan izin dari 05 §3.1, §3.3, §3.4, §3.5. Lencana angka ("Sesi Terdampak", "Pesan Kontak") belum dibuat di fase 1 (ditambahkan fase 8 bersama `module-tabs`).

```php
<?php

/*
|--------------------------------------------------------------------------
| Definisi menu (blueprint 05 §3, 10 §7.6)
|--------------------------------------------------------------------------
|
| Dibaca oleh App\Support\NavigationMenu. Entri bisa berupa:
|   - tautan : label, route, icon?, permission? ("a|b" = salah satu izin), active? (pola routeIs), emphasis?
|   - dropdown: label, children (daftar tautan)
|   - grup    : group, items (daftar tautan), hanya untuk area admin
| Tautan ke route yang belum ada (fase berikutnya) dibuang otomatis oleh NavigationMenu.
| Semua route di sini harus tanpa parameter wajib.
|
*/

return [

    // Menu utama situs publik (05 §3.1). Tombol aksi "Daftar Kunjungan"/"Masuk" ada di layout.
    'public' => [
        ['label' => 'Tentang Kami', 'children' => [
            ['label' => 'Profil', 'route' => 'about'],
            ['label' => 'Visi & Misi', 'route' => 'vision-mission'],
        ]],
        ['label' => 'Layanan', 'route' => 'clinics.index', 'active' => ['clinics.*']],
        ['label' => 'Dokter', 'children' => [
            ['label' => 'Daftar Dokter', 'route' => 'doctors.index', 'active' => ['doctors.*']],
            ['label' => 'Jadwal Dokter', 'route' => 'schedules.index'],
        ]],
        ['label' => 'Fasilitas', 'children' => [
            ['label' => 'Fasilitas', 'route' => 'facilities.index', 'active' => ['facilities.*']],
            ['label' => 'Kamar Rawat Inap', 'route' => 'rooms.index', 'active' => ['rooms.*']],
        ]],
        ['label' => 'Informasi', 'children' => [
            ['label' => 'BPJS & Asuransi', 'route' => 'insurance'],
            ['label' => 'Panduan Pendaftaran', 'route' => 'registration-guide'],
            ['label' => 'Artikel', 'route' => 'articles.index', 'active' => ['articles.*']],
            ['label' => 'Galeri', 'route' => 'galleries.index', 'active' => ['galleries.*']],
            ['label' => 'FAQ', 'route' => 'faq'],
        ]],
        ['label' => 'Kontak', 'route' => 'contact'],
    ],

    // Navigasi portal pasien (05 §3.4). "Keluar" (POST logout) dirender langsung oleh layout.
    'patient' => [
        ['label' => 'Dashboard', 'route' => 'patient.dashboard', 'icon' => 'bi-house-door'],
        ['label' => 'Daftar Kunjungan', 'route' => 'patient.appointments.create', 'icon' => 'bi-calendar-plus', 'emphasis' => true],
        ['label' => 'Riwayat Pendaftaran', 'route' => 'patient.appointments.index', 'icon' => 'bi-clock-history', 'active' => ['patient.appointments.index', 'patient.appointments.show', 'patient.appointments.proof']],
        ['label' => 'Profil dan Akun', 'route' => 'patient.profile.edit', 'icon' => 'bi-person-gear', 'active' => ['patient.profile.*']],
    ],

    // Tautan cepat footer (05 §3.3). "Kebijakan Privasi" dirender langsung oleh layout (Route::has('privacy')).
    'footer' => [
        ['label' => 'Cari Dokter', 'route' => 'doctors.index', 'active' => ['doctors.*']],
        ['label' => 'Jadwal Dokter', 'route' => 'schedules.index'],
        ['label' => 'Layanan', 'route' => 'clinics.index', 'active' => ['clinics.*']],
        ['label' => 'Kamar Rawat Inap', 'route' => 'rooms.index', 'active' => ['rooms.*']],
        ['label' => 'BPJS & Asuransi', 'route' => 'insurance'],
        ['label' => 'Panduan Pendaftaran', 'route' => 'registration-guide'],
        ['label' => 'Artikel', 'route' => 'articles.index', 'active' => ['articles.*']],
        ['label' => 'Galeri', 'route' => 'galleries.index', 'active' => ['galleries.*']],
        ['label' => 'FAQ', 'route' => 'faq'],
        ['label' => 'Kontak', 'route' => 'contact'],
    ],

    // Sidebar admin (05 §3.5). Penyaringan izin hanya kenyamanan; penegakan di middleware/Policy.
    'admin' => [
        ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'icon' => 'bi-speedometer2', 'permission' => 'dashboard.view', 'active' => ['admin.dashboard', 'admin.dashboard.*']],

        ['group' => 'Pelayanan Pasien', 'items' => [
            ['label' => 'Pendaftaran', 'route' => 'admin.appointments.index', 'icon' => 'bi-journal-check', 'permission' => 'appointments.view', 'active' => ['admin.appointments.*']],
            ['label' => 'Antrean Hari Ini', 'route' => 'admin.queue.index', 'icon' => 'bi-people', 'permission' => 'appointments.view', 'active' => ['admin.queue.*']],
            ['label' => 'Pasien', 'route' => 'admin.patients.index', 'icon' => 'bi-person-lines-fill', 'permission' => 'patients.view', 'active' => ['admin.patients.*']],
            ['label' => 'Pesan Kontak', 'route' => 'admin.messages.index', 'icon' => 'bi-envelope', 'permission' => 'messages.manage', 'active' => ['admin.messages.*']],
        ]],

        ['group' => 'Dokter dan Jadwal', 'items' => [
            ['label' => 'Dokter', 'route' => 'admin.doctors.index', 'icon' => 'bi-person-badge', 'permission' => 'doctors.view', 'active' => ['admin.doctors.*']],
            ['label' => 'Poliklinik dan Spesialisasi', 'route' => 'admin.clinics.index', 'icon' => 'bi-hospital', 'permission' => 'clinics.manage', 'active' => ['admin.clinics.*', 'admin.specialties.*']],
            ['label' => 'Jadwal Praktik', 'route' => 'admin.schedules.index', 'icon' => 'bi-calendar-week', 'permission' => 'schedules.view', 'active' => ['admin.schedules.*', 'admin.exceptions.*', 'admin.sessions.index', 'admin.sessions.create', 'admin.sessions.store', 'admin.sessions.edit', 'admin.sessions.update', 'admin.sessions.status', 'admin.sessions.destroy']],
            ['label' => 'Sesi Terdampak', 'route' => 'admin.sessions.review', 'icon' => 'bi-exclamation-triangle', 'permission' => 'sessions.review', 'active' => ['admin.sessions.review', 'admin.sessions.resolve']],
        ]],

        ['group' => 'Fasilitas dan Kamar', 'items' => [
            ['label' => 'Kamar dan Kapasitas', 'route' => 'admin.room-categories.index', 'icon' => 'bi-building', 'permission' => 'facilities.manage|rooms.update-availability', 'active' => ['admin.room-categories.*']],
            ['label' => 'Fasilitas', 'route' => 'admin.facilities.index', 'icon' => 'bi-heart-pulse', 'permission' => 'facilities.manage', 'active' => ['admin.facilities.*']],
        ]],

        ['group' => 'Konten Website', 'items' => [
            ['label' => 'Halaman', 'route' => 'admin.pages.index', 'icon' => 'bi-file-earmark-text', 'permission' => 'content.manage', 'active' => ['admin.pages.*']],
            ['label' => 'Banner', 'route' => 'admin.banners.index', 'icon' => 'bi-images', 'permission' => 'content.manage', 'active' => ['admin.banners.*']],
            ['label' => 'Artikel', 'route' => 'admin.articles.index', 'icon' => 'bi-newspaper', 'permission' => 'content.manage', 'active' => ['admin.articles.*']],
            ['label' => 'Kategori Artikel', 'route' => 'admin.article-categories.index', 'icon' => 'bi-tags', 'permission' => 'content.manage', 'active' => ['admin.article-categories.*']],
            ['label' => 'Galeri', 'route' => 'admin.galleries.index', 'icon' => 'bi-image', 'permission' => 'content.manage', 'active' => ['admin.galleries.*']],
            ['label' => 'FAQ', 'route' => 'admin.faqs.index', 'icon' => 'bi-question-circle', 'permission' => 'content.manage', 'active' => ['admin.faqs.*']],
            ['label' => 'Mitra Asuransi', 'route' => 'admin.insurance-partners.index', 'icon' => 'bi-shield-check', 'permission' => 'content.manage', 'active' => ['admin.insurance-partners.*']],
        ]],

        ['group' => 'Sistem', 'items' => [
            ['label' => 'Pengaturan Website', 'route' => 'admin.settings.edit', 'icon' => 'bi-gear', 'permission' => 'settings.manage', 'active' => ['admin.settings.*']],
            ['label' => 'Akun Staf', 'route' => 'admin.staff.index', 'icon' => 'bi-people-fill', 'permission' => 'staff.manage', 'active' => ['admin.staff.*']],
            ['label' => 'Hak Akses', 'route' => 'admin.roles.index', 'icon' => 'bi-key', 'permission' => 'staff.manage', 'active' => ['admin.roles.*']],
            ['label' => 'Audit Log', 'route' => 'admin.audit-logs.index', 'icon' => 'bi-clipboard-data', 'permission' => 'audit.view', 'active' => ['admin.audit-logs.*']],
        ]],
    ],

];
```

- [ ] **Step 4: Tulis `app/Support/NavigationMenu.php`**

```php
<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Membangun menu dari config/navigation.php.
 *
 * Menyaring tautan menuju route yang belum ada (F1-R2) dan tautan yang izinnya tidak dimiliki
 * pengguna. Penyaringan hanya kenyamanan tampilan; penegakan akses tetap di middleware/Policy.
 */
final class NavigationMenu
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function items(string $area, ?User $user = null): array
    {
        $entries = config("navigation.{$area}");

        if (! is_array($entries)) {
            return [];
        }

        $resolved = [];

        foreach ($entries as $entry) {
            $item = self::resolveEntry($entry, $user);

            if ($item !== null) {
                $resolved[] = $item;
            }
        }

        return $resolved;
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>|null
     */
    private static function resolveEntry(array $entry, ?User $user): ?array
    {
        if (isset($entry['items'])) {
            $items = self::resolveLinks($entry['items'], $user);

            return $items === [] ? null : ['group' => $entry['group'] ?? '', 'items' => $items];
        }

        if (isset($entry['children'])) {
            $children = self::resolveLinks($entry['children'], $user);

            if ($children === []) {
                return null;
            }

            return [
                'label' => $entry['label'] ?? '',
                'icon' => $entry['icon'] ?? null,
                'children' => $children,
                'active' => in_array(true, array_column($children, 'active'), true),
            ];
        }

        return self::resolveLink($entry, $user);
    }

    /**
     * @param  list<array<string, mixed>>  $links
     * @return list<array<string, mixed>>
     */
    private static function resolveLinks(array $links, ?User $user): array
    {
        $resolved = [];

        foreach ($links as $link) {
            $item = self::resolveLink($link, $user);

            if ($item !== null) {
                $resolved[] = $item;
            }
        }

        return $resolved;
    }

    /**
     * @param  array<string, mixed>  $link
     * @return array<string, mixed>|null
     */
    private static function resolveLink(array $link, ?User $user): ?array
    {
        $route = $link['route'] ?? null;

        if (! is_string($route) || ! Route::has($route)) {
            return null;
        }

        if (isset($link['permission']) && ! self::allows($user, (string) $link['permission'])) {
            return null;
        }

        $patterns = (array) ($link['active'] ?? [$route]);

        return $link + [
            'url' => route($route),
            'active' => (bool) request()->routeIs(...$patterns),
        ];
    }

    private static function allows(?User $user, string $permission): bool
    {
        return $user !== null && $user->canAny(explode('|', $permission));
    }
}
```

- [ ] **Step 5: Jalankan tes — harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=NavigationMenuTest
```
Expected: PASS (8 tes hijau; tes "menu admin selalu memuat Dashboard" baru ditambahkan di Task 14 karena route `admin.dashboard` belum ada). Bila `test_real_public_and_footer_config_keep_the_canonical_route_names` gagal, periksa ejaan nama route di `config/navigation.php` terhadap 00 I.1.

- [ ] **Step 6: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint --test
git add config/navigation.php app/Support/NavigationMenu.php tests/Feature/Support/NavigationMenuTest.php
git commit -m "feat(fase-1): config menu navigasi dan NavigationMenu yang menyaring route serta izin"
```
Expected: `pint --test` bersih (bila ada pelanggaran gaya: jalankan `& $PHP vendor\bin\pint` pada berkas yang disebut, ulangi `--test`, lalu `git add` berkas yang sama).

---

#### Siklus B — Layout auth + `$site` + `withoutVite()`

- [ ] **Step 7: Ubah `tests/TestCase.php` (F1-R14)**

Ganti isi berkas dengan (pengaman `setUpTraits()` tetap utuh; hanya `setUp()` ditambahkan):

```php
<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Tests\Support\DatabaseGuard;

abstract class TestCase extends BaseTestCase
{
    /**
     * Semua tes berjalan tanpa manifest Vite (F1-R14); build aset diverifikasi terpisah
     * lewat `npm run build` dan FrontendManifestTest.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * Dipanggil sebelum trait (mis. RefreshDatabase) menyentuh database,
     * sehingga suite tes tidak pernah berjalan pada database pengembangan.
     * Yang diperiksa adalah nama database hasil resolusi koneksi (termasuk DB_URL),
     * bukan nilai mentah konfigurasi; membaca nama ini tidak membuka koneksi.
     */
    protected function setUpTraits()
    {
        DatabaseGuard::assertIsTestDatabase((string) DB::connection()->getDatabaseName());

        return parent::setUpTraits();
    }
}
```

- [ ] **Step 8: Tulis `LayoutsTest` (bagian layout auth; gagal dulu)**

Buat `tests/Feature/View/LayoutsTest.php`:

```php
<?php

namespace Tests\Feature\View;

use App\Services\Settings\SettingsService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

class LayoutsTest extends TestCase
{
    use InteractsWithStaff;
    use RefreshDatabase;

    /** Mendaftarkan halaman uji yang merender potongan Blade di dalam grup middleware `web`. */
    private function page(string $uri, string $blade): void
    {
        Route::middleware('web')->get($uri, fn () => Blade::render($blade));
        Route::getRoutes()->refreshNameLookups();
    }

    private function h1Count(string $html): int
    {
        return substr_count($html, '<h1');
    }

    // --- layout auth -------------------------------------------------------------------------

    public function test_auth_layout_renders_card_with_skip_link_single_h1_and_noindex(): void
    {
        $this->page('/_uji/auth', '<x-layouts.auth title="Masuk Uji" subtitle="Subjudul uji"><p>Isi formulir uji</p><x-slot:footer><a href="#bantuan">Bantuan uji</a></x-slot:footer></x-layouts.auth>');

        $response = $this->get('/_uji/auth')->assertOk();

        $response->assertSee('<html lang="id">', false)
            ->assertSee('<title>Masuk Uji — '.config('app.name').'</title>', false)
            ->assertSee('<meta name="robots" content="noindex,nofollow">', false)
            ->assertSee('class="skip-link" href="#konten-utama"', false)
            ->assertSee('<main id="konten-utama" tabindex="-1"', false)
            ->assertSee('auth-card', false)
            ->assertSee('Subjudul uji')
            ->assertSee('Isi formulir uji')
            ->assertSee('Bantuan uji')
            ->assertSee('Kembali ke beranda')
            ->assertSee('images/placeholders/logo.svg', false);
        $this->assertSame(1, $this->h1Count($response->getContent()));
    }

    public function test_auth_layout_shows_flash_messages_and_validation_summary(): void
    {
        $this->page('/_uji/auth', '<x-layouts.auth title="Masuk Uji"><p>Isi</p></x-layouts.auth>');
        Route::middleware('web')->post('/_uji/validasi', function (Request $request) {
            $request->validate(['email' => 'required']);
        });

        $this->withSession(['error' => 'Gagal uji.'])->get('/_uji/auth')->assertSee('Gagal uji.');

        $this->from('/_uji/auth')->followingRedirects()->post('/_uji/validasi', [])
            ->assertSee('data-errors-summary', false)
            ->assertSee('Kolom email wajib diisi.');
    }

    public function test_layouts_read_site_settings_through_the_view_composer(): void
    {
        $this->seed(SettingsSeeder::class);
        app(SettingsService::class)->set('site.name', 'RS Uji Coba');
        $this->page('/_uji/auth', '<x-layouts.auth title="Masuk Uji"><p>Isi</p></x-layouts.auth>');

        $this->get('/_uji/auth')
            ->assertSee('<title>Masuk Uji — RS Uji Coba</title>', false)
            ->assertSee('RS Uji Coba');
    }
}
```

- [ ] **Step 9: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=LayoutsTest
```
Expected: FAIL — respons 500 (bukan 200) dengan galat `Unable to locate a class or view for component [layouts.auth]`; `test_layouts_read_site_settings_through_the_view_composer` gagal karena komponen yang sama.

- [ ] **Step 10: `AppServiceProvider` (composer `$site`), nama route `home`, dan layout auth**

Layout auth memanggil `route('home')`, padahal route `/` fase 0 belum bernama (Siklus C nanti menggantinya dengan `Route::view('/', 'public.home')->name('home')`). Beri nama sekarang — `routes/web.php` (isi lengkap sementara):

```php
<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');
```

Tulis ulang `app/Providers/AppServiceProvider.php` (gabungan Task 2, 3, 7, 11 + composer Task 13). Baca berkas dulu, lalu timpa:

```php
<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Settings\SettingsService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Satu instance per permintaan (cache memori + cache aplikasi), 11 §3.3.
        $this->app->singleton(SettingsService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Mode ketat hanya di non-produksi: lazy loading, atribut hilang, dan atribut yang
        // diam-diam dibuang langsung melempar galat.
        Model::shouldBeStrict(! $this->app->isProduction());

        // Paginasi Bootstrap 5 (view terbit di resources/views/vendor/pagination).
        Paginator::useBootstrapFive();

        // Super Admin melewati semua Gate/Policy (00 G). Hanya untuk pengguna login.
        Gate::before(fn (User $user, string $ability) => $user->hasRole('super-admin') ? true : null);

        // Pengaturan situs tersedia di semua layout sebagai $site (SettingsService).
        View::composer(['components.layouts.*'], function ($view): void {
            $view->with('site', $this->app->make(SettingsService::class));
        });
    }
}
```

Buat `resources/views/components/layouts/auth.blade.php`:

```blade
@props(['title', 'subtitle' => null])
@php
    $siteName = $site->string('site.name', config('app.name'));
    $logoUrl = $site->logoUrl();
    $logoFallback = asset('images/placeholders/logo.svg');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $title }} — {{ $siteName }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
<body class="bg-light">
    <a class="skip-link" href="#konten-utama">Lewati ke konten utama</a>

    <div class="container py-4 py-md-5">
        <div class="auth-card mx-auto">
            <div class="text-center mb-4">
                <a class="d-inline-flex align-items-center gap-2 text-decoration-none" href="{{ route('home') }}">
                    <img src="{{ $logoUrl }}" data-fallback="{{ $logoFallback }}" alt="" width="48" height="48">
                    <span class="h5 mb-0 fw-bold text-primary">{{ $siteName }}</span>
                </a>
            </div>

            <main id="konten-utama" tabindex="-1" class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <h1 class="h3 mb-1">{{ $title }}</h1>
                    @if (filled($subtitle))
                        <p class="text-body-secondary">{{ $subtitle }}</p>
                    @endif

                    <x-flash-alert />
                    <x-form.errors-summary />

                    {{ $slot }}
                </div>
            </main>

            @isset($footer)
                <div class="mt-3 text-center small">{{ $footer }}</div>
            @endisset

            <p class="mt-3 text-center small mb-0">
                <a href="{{ route('home') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Kembali ke beranda</a>
            </p>
        </div>
    </div>
</body>
</html>
```

- [ ] **Step 11: Jalankan tes — harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="LayoutsTest|SmokeTest|NavigationMenuTest"
```
Expected: PASS. `SmokeTest` lama masih hijau (ia memanggil `withoutVite()` sendiri; pemanggilan ganda tidak berbahaya).

- [ ] **Step 12: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint --test
git add tests/TestCase.php tests/Feature/View/LayoutsTest.php app/Providers/AppServiceProvider.php resources/views/components/layouts/auth.blade.php routes/web.php
git commit -m "feat(fase-1): layout auth, view composer site, dan withoutVite di TestCase"
```

---

#### Siklus C — Layout publik, beranda sementara, `routes/web.php`, SmokeTest

- [ ] **Step 13: Tambah tes layout publik ke `LayoutsTest` dan perbarui `SmokeTest` (gagal dulu)**

Di `tests/Feature/View/LayoutsTest.php`, tempel method berikut **di akhir kelas** (sebelum `}` penutup; blok import tidak berubah):

```php

    // --- layout publik -----------------------------------------------------------------------

    public function test_public_layout_has_landmarks_skip_link_meta_and_no_noindex(): void
    {
        $this->page('/_uji/publik', '<x-layouts.public title="Halaman Uji" meta-description="Deskripsi uji"><h1>Judul Uji</h1><p>Isi uji</p></x-layouts.public>');

        $response = $this->get('/_uji/publik')->assertOk();

        $response->assertSee('<html lang="id">', false)
            ->assertSee('<title>Halaman Uji — '.config('app.name').'</title>', false)
            ->assertSee('<meta name="description" content="Deskripsi uji">', false)
            ->assertSee('class="skip-link" href="#konten-utama"', false)
            ->assertSee('aria-label="Menu utama"', false)
            ->assertSee('aria-label="Buka menu"', false)
            ->assertSee('<main id="konten-utama" tabindex="-1"', false)
            ->assertSee('<footer', false)
            ->assertSee('Situs prototipe: nama dokter, foto, tarif, kontak, dan layanan adalah data contoh.')
            ->assertSee('Isi uji')
            ->assertDontSee('noindex');
        $this->assertSame(1, $this->h1Count($response->getContent()), 'Layout tidak boleh menambah <h1>; halaman yang memberinya.');
    }

    public function test_public_layout_escapes_the_title_and_renders_the_breadcrumb_slot(): void
    {
        $this->page('/_uji/publik', '<x-layouts.public :title="\'<script>alert(1)</script>\'"><x-slot:breadcrumb><span>Jejak uji</span></x-slot:breadcrumb><p>Isi</p></x-layouts.public>');

        $this->get('/_uji/publik')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertSee('Jejak uji');
    }

    public function test_public_footer_shows_address_and_contacts_from_settings_with_a_fallback_address(): void
    {
        $this->page('/_uji/publik', '<x-layouts.public title="Halaman Uji"><p>Isi</p></x-layouts.public>');

        $this->get('/_uji/publik')->assertSee('Alamat belum diisi.');

        $this->seed(SettingsSeeder::class);
        app(SettingsService::class)->forget(); // singleton + cache masih memegang peta kosong dari permintaan pertama

        $this->get('/_uji/publik')
            ->assertDontSee('Alamat belum diisi.')
            ->assertSee('Universitas Pamulang Kampus 2 Viktor')
            ->assertSee('https://wa.me/6280000000000', false)
            ->assertSee('info@unpamhospital.test');
    }

    public function test_public_layout_hides_links_to_routes_that_do_not_exist_yet_and_shows_them_when_they_do(): void
    {
        $this->page('/_uji/publik', '<x-layouts.public title="Halaman Uji"><p>Isi</p></x-layouts.public>');

        $this->get('/_uji/publik')->assertSee(route('home'), false);

        // Cadangan F1-R2: berlaku selama route fase berikutnya belum ada (dihapus bersama cadangan di fase 5).
        if (! Route::has('login') && ! Route::has('patient.appointments.create')) {
            $this->get('/_uji/publik')
                ->assertDontSee('>Masuk<', false)
                ->assertDontSee('Daftar Kunjungan');
        }

        Route::middleware('web')->get('/_uji/masuk', fn () => 'masuk')->name('login');
        Route::middleware('web')->get('/_uji/daftar-kunjungan', fn () => 'daftar')->name('patient.appointments.create');
        Route::middleware('web')->get('/_uji/dokter', fn () => 'dokter')->name('doctors.index');
        Route::getRoutes()->refreshNameLookups();

        $this->get('/_uji/publik')
            ->assertSee('>Masuk<', false)
            ->assertSee('Daftar Kunjungan')
            ->assertSee('Daftar Dokter');
    }

    public function test_public_layout_offers_the_admin_panel_and_logout_to_logged_in_staff(): void
    {
        $this->page('/_uji/publik', '<x-layouts.public title="Halaman Uji"><p>Isi</p></x-layouts.public>');

        $this->get('/_uji/publik')->assertDontSee('Buka Panel Admin');

        Route::middleware('web')->get('/_uji/admin-dashboard', fn () => 'dasbor')->name('admin.dashboard');
        Route::middleware('web')->post('/_uji/admin-keluar', fn () => 'keluar')->name('admin.logout');
        Route::getRoutes()->refreshNameLookups();

        $this->actingAs($this->createStaff('content-admin'))
            ->get('/_uji/publik')
            ->assertSee('Buka Panel Admin')
            ->assertSee('action="'.route('admin.logout').'"', false);
    }

    public function test_public_layout_can_be_marked_noindex(): void
    {
        $this->page('/_uji/publik', '<x-layouts.public title="Halaman Uji" :noindex="true"><p>Isi</p></x-layouts.public>');

        $this->get('/_uji/publik')->assertSee('<meta name="robots" content="noindex,nofollow">', false);
    }
```

Ganti seluruh isi `tests/Feature/SmokeTest.php`:

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_renders_indonesian_public_layout(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<html lang="id">', false)
            ->assertSee('class="skip-link" href="#konten-utama"', false)
            ->assertSee('<main id="konten-utama" tabindex="-1"', false)
            ->assertSee('UNPAM HOSPITAL')
            ->assertSee('Situs ini masih dalam tahap pembangunan.');
    }

    public function test_home_page_has_exactly_one_h1(): void
    {
        $this->assertSame(1, substr_count($this->get('/')->getContent(), '<h1'));
    }

    public function test_welcome_skeleton_view_is_removed(): void
    {
        $this->assertFileDoesNotExist(resource_path('views/welcome.blade.php'));
    }

    public function test_health_endpoint_is_up(): void
    {
        $this->get('/up')->assertOk();
    }
}
```

- [ ] **Step 14: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="LayoutsTest|SmokeTest"
```
Expected: FAIL — tes layout publik: `Unable to locate a class or view for component [layouts.public]` (500); `SmokeTest`: `<main id="konten-utama" ...` tidak ditemukan dan `welcome.blade.php` masih ada. Tes layout auth tetap hijau.

- [ ] **Step 15: Tulis `resources/views/components/layouts/public.blade.php`**

```blade
@props(['title', 'metaDescription' => null, 'noindex' => false])
@php
    $siteName = $site->string('site.name', config('app.name'));
    $tagline = $site->string('site.tagline');
    $description = filled($metaDescription)
        ? $metaDescription
        : $site->string('seo.default_meta_description', 'Informasi layanan, dokter, jadwal, dan pendaftaran kunjungan rawat jalan di '.$siteName.'.');
    $account = auth()->user();
    $isStaff = $account instanceof \App\Models\User && $account->isStaff();
    $isPatient = $account instanceof \App\Models\User && $account->isPatient();
    $menu = \App\Support\NavigationMenu::items('public', $account);
    $quickLinks = \App\Support\NavigationMenu::items('footer', $account);

    $logoUrl = $site->logoUrl();
    $logoFallback = asset('images/placeholders/logo.svg');
    $address = $site->string('contact.address');
    $phone = $site->string('contact.phone');
    $emergency = $site->string('contact.emergency_phone');
    $email = $site->string('contact.email');
    $hours = $site->string('contact.operational_hours');
    $whatsappUrl = $site->whatsappUrl();
    $telHref = fn (string $number): string => 'tel:'.preg_replace('/[^0-9+]/', '', $number);

    // Tombol aksi header. Tautan ke route fase berikutnya hanya tampil bila route-nya sudah ada (F1-R2).
    // 'header' = true ⇒ juga ditampilkan di bilah ringkas mobile.
    $actions = [];
    if ($isStaff) {
        if (Route::has('admin.dashboard')) {
            $actions[] = ['label' => 'Buka Panel Admin', 'url' => route('admin.dashboard'), 'class' => 'btn btn-primary', 'header' => true];
        }
    } else {
        if (Route::has('patient.appointments.create')) {
            $actions[] = ['label' => 'Daftar Kunjungan', 'url' => route('patient.appointments.create'), 'class' => 'btn btn-primary', 'header' => false];
        }
        if ($isPatient && Route::has('patient.dashboard')) {
            $actions[] = ['label' => 'Akun Saya', 'url' => route('patient.dashboard'), 'class' => 'btn btn-outline-primary', 'header' => true];
        } elseif (! $isPatient && Route::has('login')) {
            $actions[] = ['label' => 'Masuk', 'url' => route('login'), 'class' => 'btn btn-outline-primary', 'header' => true];
        }
    }
    $logoutUrl = null;
    if ($isStaff && Route::has('admin.logout')) {
        $logoutUrl = route('admin.logout');
    } elseif ($isPatient && Route::has('logout')) {
        $logoutUrl = route('logout');
    }
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — {{ $siteName }}</title>
    <meta name="description" content="{{ $description }}">
    @if ($noindex)
        <meta name="robots" content="noindex,nofollow">
    @endif
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="bg-white">
    <a class="skip-link" href="#konten-utama">Lewati ke konten utama</a>

    <header>
        {{-- Baris atas (desktop): identitas, nomor IGD, tombol aksi --}}
        <div class="bg-white border-bottom d-none d-lg-block">
            <div class="container py-3 d-flex align-items-center justify-content-between gap-3">
                <a class="d-inline-flex align-items-center gap-3 text-decoration-none" href="{{ route('home') }}">
                    <img src="{{ $logoUrl }}" data-fallback="{{ $logoFallback }}" alt="" width="48" height="48">
                    <span>
                        <span class="d-block h5 mb-0 fw-bold text-primary">{{ $siteName }}</span>
                        @if ($tagline !== '')
                            <span class="d-block small text-body-secondary">{{ $tagline }}</span>
                        @endif
                    </span>
                </a>
                <div class="d-flex align-items-center gap-3">
                    @if ($emergency !== '')
                        <a class="text-decoration-none fw-semibold" href="{{ $telHref($emergency) }}"><i class="bi bi-telephone-fill" aria-hidden="true"></i> IGD: {{ $emergency }}</a>
                    @endif
                    @foreach ($actions as $action)
                        <a class="{{ $action['class'] }}" href="{{ $action['url'] }}">{{ $action['label'] }}</a>
                    @endforeach
                    @if ($logoutUrl)
                        <form method="POST" action="{{ $logoutUrl }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary">Keluar</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        {{-- Bar menu: di bawah lg menjadi offcanvas kanan --}}
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top" aria-label="Menu utama">
            <div class="container">
                <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#menu-utama" aria-controls="menu-utama" aria-label="Buka menu">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <a class="navbar-brand d-lg-none d-inline-flex align-items-center gap-2 me-auto ms-2" href="{{ route('home') }}">
                    <img src="{{ $logoUrl }}" data-fallback="{{ $logoFallback }}" alt="" width="32" height="32">
                    <span>{{ $siteName }}</span>
                </a>
                <div class="d-lg-none d-flex gap-2">
                    @foreach ($actions as $action)
                        @if ($action['header'])
                            <a class="btn btn-outline-light btn-sm" href="{{ $action['url'] }}">{{ $action['label'] }}</a>
                        @endif
                    @endforeach
                </div>

                <div class="offcanvas offcanvas-end" tabindex="-1" id="menu-utama" aria-labelledby="menu-utama-judul">
                    <div class="offcanvas-header">
                        <div class="offcanvas-title h5" id="menu-utama-judul">Menu</div>
                        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Tutup menu"></button>
                    </div>
                    <div class="offcanvas-body">
                        <ul class="navbar-nav me-auto mb-3 mb-lg-0">
                            <li class="nav-item d-lg-none">
                                <a class="nav-link" href="{{ route('home') }}">Beranda</a>
                            </li>
                            @foreach ($menu as $entry)
                                @if (isset($entry['children']))
                                    <li class="nav-item dropdown">
                                        <button @class(['nav-link dropdown-toggle border-0 bg-transparent', 'active fw-bold' => $entry['active']]) type="button" data-bs-toggle="dropdown" aria-expanded="false">{{ $entry['label'] }}</button>
                                        <ul class="dropdown-menu">
                                            @foreach ($entry['children'] as $child)
                                                <li>
                                                    <a @class(['dropdown-item', 'active' => $child['active']]) href="{{ $child['url'] }}" @if ($child['active']) aria-current="page" @endif>{{ $child['label'] }}</a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </li>
                                @else
                                    <li class="nav-item">
                                        <a @class(['nav-link', 'active fw-bold' => $entry['active']]) href="{{ $entry['url'] }}" @if ($entry['active']) aria-current="page" @endif>{{ $entry['label'] }}</a>
                                    </li>
                                @endif
                            @endforeach
                        </ul>

                        <div class="d-lg-none d-grid gap-2">
                            @foreach ($actions as $action)
                                <a class="{{ $action['class'] }}" href="{{ $action['url'] }}">{{ $action['label'] }}</a>
                            @endforeach
                            @if ($logoutUrl)
                                <form method="POST" action="{{ $logoutUrl }}" class="d-grid">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-secondary">Keluar</button>
                                </form>
                            @endif
                            @if ($emergency !== '')
                                <a class="text-decoration-none fw-semibold" href="{{ $telHref($emergency) }}"><i class="bi bi-telephone-fill" aria-hidden="true"></i> IGD: {{ $emergency }}</a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </nav>
    </header>

    @if (isset($breadcrumb) && trim((string) $breadcrumb) !== '')
        <div class="container pt-3">{{ $breadcrumb }}</div>
    @endif

    <main id="konten-utama" tabindex="-1" class="container py-4 py-lg-5">
        <x-flash-alert />
        {{ $slot }}
    </main>

    <footer class="bg-light border-top mt-5">
        <div class="container py-5">
            <div class="row g-4">
                <div class="col-md-6 col-lg-4">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <img src="{{ $logoUrl }}" data-fallback="{{ $logoFallback }}" alt="" width="40" height="40" loading="lazy">
                        <span class="h5 mb-0 fw-bold text-primary">{{ $siteName }}</span>
                    </div>
                    @if ($tagline !== '')
                        <p class="mb-2">{{ $tagline }}</p>
                    @endif
                    <address class="mb-0">{{ $address !== '' ? $address : 'Alamat belum diisi.' }}</address>
                </div>

                <div class="col-md-6 col-lg-4">
                    <h2 class="h6 text-uppercase fw-bold">Kontak</h2>
                    <ul class="list-unstyled mb-0">
                        @if ($phone !== '')
                            <li>Telepon: <a href="{{ $telHref($phone) }}">{{ $phone }}</a></li>
                        @endif
                        @if ($emergency !== '')
                            <li>IGD: <a href="{{ $telHref($emergency) }}">{{ $emergency }}</a></li>
                        @endif
                        @if ($whatsappUrl)
                            <li>WhatsApp: <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer">Hubungi via WhatsApp (dibuka di aplikasi WhatsApp)</a></li>
                        @endif
                        @if ($email !== '')
                            <li>Email: <a href="mailto:{{ $email }}">{{ $email }}</a></li>
                        @endif
                        @if ($hours !== '')
                            <li>Jam operasional: {{ $hours }}</li>
                        @endif
                    </ul>
                </div>

                @if ($quickLinks !== [])
                    <div class="col-lg-4">
                        <h2 class="h6 text-uppercase fw-bold">Tautan cepat</h2>
                        <ul class="list-unstyled mb-0">
                            @foreach ($quickLinks as $link)
                                <li><a href="{{ $link['url'] }}">{{ $link['label'] }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </div>
        <div class="border-top">
            <div class="container py-3 small">
                <p class="mb-1">
                    © {{ now()->year }} {{ $siteName }}
                    @if (Route::has('privacy'))
                        · <a href="{{ route('privacy') }}">Kebijakan Privasi</a>
                    @endif
                </p>
                <p class="mb-0 text-body-secondary">Situs prototipe: nama dokter, foto, tarif, kontak, dan layanan adalah data contoh.</p>
            </div>
        </div>
    </footer>

    @stack('scripts')
</body>
</html>
```

- [ ] **Step 16: Beranda sementara, `routes/web.php`, hapus `welcome`**

Buat `resources/views/public/home.blade.php`:

```blade
@inject('site', 'App\Services\Settings\SettingsService')
<x-layouts.public title="Beranda">
    <section class="py-4 py-lg-5 text-center">
        <h1 class="display-5 fw-bold text-primary">{{ $site->string('site.name', config('app.name')) }}</h1>
        <p class="lead mb-4">{{ $site->string('site.tagline', 'Layanan kesehatan yang ramah dan terpercaya') }}</p>

        <div class="d-flex flex-wrap justify-content-center gap-2">
            @if (Route::has('doctors.index'))
                <a class="btn btn-primary btn-lg" href="{{ route('doctors.index') }}">Cari Dokter</a>
            @endif
            @if (Route::has('schedules.index'))
                <a class="btn btn-outline-primary btn-lg" href="{{ route('schedules.index') }}">Jadwal Dokter</a>
            @endif
            @if (Route::has('patient.appointments.create'))
                <a class="btn btn-primary btn-lg" href="{{ route('patient.appointments.create') }}">Daftar Kunjungan</a>
            @endif
        </div>

        <p class="text-body-secondary mt-4 mb-0">Situs ini masih dalam tahap pembangunan. Halaman dokter, jadwal, dan pendaftaran kunjungan akan tersedia pada tahap berikutnya.</p>
    </section>
</x-layouts.public>
```

Tulis ulang `routes/web.php`:

```php
<?php

use Illuminate\Support\Facades\Route;

// Beranda sementara (F1-R3); HomeController dibangun di fase 4a.
Route::view('/', 'public.home')->name('home');
```

Hapus view skeleton:

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git rm resources/views/welcome.blade.php
```

- [ ] **Step 17: Jalankan tes — harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="LayoutsTest|SmokeTest|NavigationMenuTest"
```
Expected: PASS (semua hijau).

- [ ] **Step 18: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint --test
git add routes/web.php tests/Feature/SmokeTest.php tests/Feature/View/LayoutsTest.php resources/views/components/layouts/public.blade.php resources/views/public/home.blade.php
git commit -m "feat(fase-1): layout publik, beranda sementara, dan hapus view welcome"
```
(`git rm` sudah men-stage penghapusan `welcome.blade.php`; ikut ter-commit.)

---

#### Siklus D — Layout portal pasien

- [ ] **Step 19: Tambah tes layout pasien ke `LayoutsTest` (gagal dulu)**

Tambahkan `use App\Models\User;` di blok import `LayoutsTest` (paling atas, sebelum `App\Services\Settings\SettingsService`), lalu tempel method berikut di akhir kelas:

```php

    // --- layout pasien -----------------------------------------------------------------------

    public function test_patient_layout_wraps_the_public_layout_with_noindex_and_single_h1(): void
    {
        $this->page('/_uji/pasien', '<x-layouts.patient title="Portal Uji"><x-slot:breadcrumb><span>Jejak pasien</span></x-slot:breadcrumb><h1>Judul Pasien</h1><p>Isi portal</p></x-layouts.patient>');

        $response = $this->get('/_uji/pasien')->assertOk();

        $response->assertSee('<html lang="id">', false)
            ->assertSee('<title>Portal Uji — '.config('app.name').'</title>', false)
            ->assertSee('<meta name="robots" content="noindex,nofollow">', false)
            ->assertSee('class="skip-link" href="#konten-utama"', false)
            ->assertSee('<main id="konten-utama" tabindex="-1"', false)
            ->assertSee('Jejak pasien')
            ->assertSee('Isi portal');
        $this->assertSame(1, $this->h1Count($response->getContent()));
    }

    public function test_patient_layout_omits_portal_navigation_until_patient_routes_exist(): void
    {
        $this->page('/_uji/pasien', '<x-layouts.patient title="Portal Uji"><p>Isi portal</p></x-layouts.patient>');

        // Berlaku selama route portal pasien belum ada (fase 5 menghapus cabang ini).
        if (! Route::has('patient.dashboard') && ! Route::has('logout')) {
            $this->get('/_uji/pasien')
                ->assertOk()
                ->assertDontSee('aria-label="Menu portal pasien"', false)
                ->assertDontSee('Riwayat Pendaftaran');
        }
    }

    public function test_patient_layout_shows_portal_navigation_with_current_item_and_logout_when_routes_exist(): void
    {
        Route::middleware('web')->get('/_uji/pasien-dashboard', fn () => Blade::render('<x-layouts.patient title="Dashboard Uji"><p>Dasbor</p></x-layouts.patient>'))->name('patient.dashboard');
        Route::middleware('web')->get('/_uji/pasien-riwayat', fn () => 'riwayat')->name('patient.appointments.index');
        Route::middleware('web')->get('/_uji/pasien-baru', fn () => 'baru')->name('patient.appointments.create');
        Route::middleware('web')->post('/_uji/keluar', fn () => 'keluar')->name('logout');
        Route::getRoutes()->refreshNameLookups();

        $this->get('/_uji/pasien-dashboard')
            ->assertOk()
            ->assertSee('aria-label="Menu portal pasien"', false)
            ->assertSee('Dashboard')
            ->assertSee('Riwayat Pendaftaran')
            ->assertSee('Daftar Kunjungan')
            ->assertSee('aria-current="page"', false)
            ->assertDontSee('action="'.route('logout').'"', false);

        $this->actingAs(User::factory()->patient()->create())
            ->get('/_uji/pasien-dashboard')
            ->assertSee('action="'.route('logout').'"', false);
    }
```

- [ ] **Step 20: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=LayoutsTest
```
Expected: FAIL — tiga tes baru: `Unable to locate a class or view for component [layouts.patient]` (500).

- [ ] **Step 21: Tulis `resources/views/components/layouts/patient.blade.php`**

```blade
@props(['title'])
@php
    $account = auth()->user();
    $portal = \App\Support\NavigationMenu::items('patient', $account);
    $logoutUrl = ($account instanceof \App\Models\User && Route::has('logout')) ? route('logout') : null;
    $hasSidebar = $portal !== [] || $logoutUrl !== null;
@endphp
<x-layouts.public :title="$title" :noindex="true">
    <x-slot:breadcrumb>{{ $breadcrumb ?? '' }}</x-slot:breadcrumb>

    <div class="row g-4">
        @if ($hasSidebar)
            <aside class="col-lg-3">
                <nav aria-label="Menu portal pasien">
                    <ul class="nav nav-pills flex-nowrap flex-lg-column overflow-auto gap-1 pb-2">
                        @foreach ($portal as $item)
                            <li class="nav-item">
                                <a @class([
                                    'nav-link d-flex align-items-center gap-2',
                                    'active' => $item['active'],
                                    'btn btn-primary text-white' => ($item['emphasis'] ?? false) && ! $item['active'],
                                ]) href="{{ $item['url'] }}" @if ($item['active']) aria-current="page" @endif>
                                    @if (! empty($item['icon']))
                                        <i class="bi {{ $item['icon'] }}" aria-hidden="true"></i>
                                    @endif
                                    <span>{{ $item['label'] }}</span>
                                </a>
                            </li>
                        @endforeach
                        @if ($logoutUrl)
                            <li class="nav-item">
                                <form method="POST" action="{{ $logoutUrl }}">
                                    @csrf
                                    <button type="submit" class="nav-link d-flex align-items-center gap-2 w-100 border-0 bg-transparent text-start">
                                        <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
                                        <span>Keluar</span>
                                    </button>
                                </form>
                            </li>
                        @endif
                    </ul>
                </nav>
            </aside>
        @endif

        <div @class(['col-lg-9' => $hasSidebar, 'col-12' => ! $hasSidebar])>
            {{ $slot }}
        </div>
    </div>
</x-layouts.public>
```

- [ ] **Step 22: Jalankan tes — harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=LayoutsTest
```
Expected: PASS. Bila `assertDontSee('Dashboard')`-semacam gagal karena teks lain di footer, cek bahwa tes hanya mencari `Riwayat Pendaftaran` pada kasus tanpa navigasi.

- [ ] **Step 23: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint --test
git add tests/Feature/View/LayoutsTest.php resources/views/components/layouts/patient.blade.php
git commit -m "feat(fase-1): layout portal pasien membungkus layout publik"
```

---

#### Siklus E — Halaman galat Indonesia (ERR-01, ERR-04)

- [ ] **Step 24: Tulis `ErrorPagesTest` (gagal dulu)**

Buat `tests/Feature/Errors/ErrorPagesTest.php` (tanpa `RefreshDatabase`: halaman galat tidak boleh menyentuh DB):

```php
<?php

namespace Tests\Feature\Errors;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    public function test_unknown_url_renders_indonesian_404_without_stack_trace_or_queries(): void
    {
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $response = $this->get('/tidak-ada');

        $response->assertNotFound()
            ->assertSee('<html lang="id">', false)
            ->assertSee('Halaman tidak ditemukan')
            ->assertSee('Alamat yang Anda buka tidak tersedia atau sudah dipindahkan.')
            ->assertSee('Ke Beranda')
            ->assertDontSee('NotFoundHttpException')
            ->assertDontSee('Stack trace');
        $this->assertSame(0, $queries, 'Halaman galat tidak boleh membaca database.');
    }

    public function test_unknown_admin_url_also_renders_the_indonesian_404(): void
    {
        $this->get('/admin/tidak-ada')
            ->assertNotFound()
            ->assertSee('Halaman tidak ditemukan');
    }

    public function test_404_links_to_doctor_pages_only_when_those_routes_exist(): void
    {
        // Berlaku sampai fase 3/4 menambah route doctors.index.
        if (! Route::has('doctors.index')) {
            $this->get('/tidak-ada')->assertDontSee('Cari Dokter');
        }

        Route::middleware('web')->get('/_uji/dokter', fn () => 'dokter')->name('doctors.index');
        Route::getRoutes()->refreshNameLookups();

        $this->get('/tidak-ada')->assertSee('Cari Dokter');
    }

    public function test_unhandled_exception_with_debug_off_renders_generic_500_without_internal_message(): void
    {
        config(['app.debug' => false]);
        Route::middleware('web')->get('/_uji/meledak', fn () => throw new RuntimeException('Rahasia internal: kata sandi database'));

        $this->get('/_uji/meledak')
            ->assertStatus(500)
            ->assertSee('Terjadi kesalahan pada server')
            ->assertSee('Kami mencatat masalah ini.')
            ->assertDontSee('Rahasia internal')
            ->assertDontSee('RuntimeException')
            ->assertDontSee('Whoops');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function errorViews(): array
    {
        return [
            '403' => ['403', 'Akses ditolak'],
            '404' => ['404', 'Halaman tidak ditemukan'],
            '419' => ['419', 'Sesi berakhir'],
            '429' => ['429', 'Terlalu banyak permintaan'],
            '500' => ['500', 'Terjadi kesalahan pada server'],
            '503' => ['503', 'Sedang dalam pemeliharaan'],
        ];
    }

    #[DataProvider('errorViews')]
    public function test_every_error_view_is_standalone_indonesian_with_single_h1_and_skip_link(string $code, string $heading): void
    {
        $html = view("errors.{$code}", ['retryAfter' => 45, 'detail' => null])->render();

        $this->assertStringContainsString('<html lang="id">', $html);
        $this->assertStringContainsString('<meta name="robots" content="noindex,nofollow">', $html);
        $this->assertStringContainsString('class="skip-link" href="#konten-utama"', $html);
        $this->assertStringContainsString($heading, $html);
        $this->assertSame(1, substr_count($html, '<h1'));
    }

    public function test_429_view_mentions_the_retry_delay_and_403_view_shows_an_optional_detail(): void
    {
        $this->assertStringContainsString('Coba lagi dalam 45 detik.', view('errors.429', ['retryAfter' => 45])->render());
        $this->assertStringContainsString('Pesan khusus uji.', view('errors.403', ['detail' => 'Pesan khusus uji.'])->render());
        $this->assertStringNotContainsString('Pesan khusus uji.', view('errors.403', ['detail' => null])->render());
    }
}
```

- [ ] **Step 25: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=ErrorPagesTest
```
Expected: FAIL — 404 masih halaman bawaan Laravel (`Not Found`, tanpa "Halaman tidak ditemukan"), halaman 500 bawaan, dan `view("errors.403")` dst. `View [errors.403] not found`.

- [ ] **Step 26: Tulis `errors/layout` dan enam halaman galat**

`resources/views/errors/layout.blade.php` (mandiri; tidak memakai `$site`/DB; memakai `config('app.name')`):

```blade
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>@yield('title') — {{ config('app.name') }}</title>
    {{-- Halaman galat tetap tampil walau aset belum di-build (mesin baru / mode pemeliharaan). --}}
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    @endif
</head>
<body class="bg-light">
    <a class="skip-link" href="#konten-utama">Lewati ke konten utama</a>

    <main id="konten-utama" tabindex="-1" class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6 text-center">
                <p class="display-4 fw-bold text-primary mb-2" aria-hidden="true">@yield('code')</p>
                <h1 class="h2 mb-3">@yield('heading')</h1>
                <p class="lead">@yield('message')</p>
                @yield('extra')
                <div class="d-flex flex-wrap justify-content-center gap-2 mt-4">
                    @yield('actions')
                </div>
                <p class="small text-body-secondary mt-5 mb-0">{{ config('app.name') }}</p>
            </div>
        </div>
    </main>
</body>
</html>
```

`resources/views/errors/403.blade.php`:

```blade
@extends('errors.layout')

@section('title', 'Akses ditolak')
@section('code', '403')
@section('heading', 'Akses ditolak')
@section('message', 'Anda tidak memiliki izin untuk mengakses halaman atau melakukan tindakan ini.')

@section('extra')
    @if (! empty($detail))
        <p class="text-body-secondary">{{ $detail }}</p>
    @endif
@endsection

@section('actions')
    @php
        // Tombol dasbor menurut tipe akun. Dibungkus try/catch agar halaman galat tetap tampil walau DB bermasalah.
        $dashboardUrl = null;
        try {
            $viewer = auth()->user();
            if ($viewer instanceof \App\Models\User) {
                if ($viewer->isStaff() && Route::has('admin.dashboard')) {
                    $dashboardUrl = route('admin.dashboard');
                } elseif ($viewer->isPatient() && Route::has('patient.dashboard')) {
                    $dashboardUrl = route('patient.dashboard');
                }
            }
        } catch (\Throwable) {
            $dashboardUrl = null;
        }
    @endphp
    <a class="btn btn-outline-secondary" href="{{ url()->previous(url('/')) }}">Kembali</a>
    @if ($dashboardUrl)
        <a class="btn btn-primary" href="{{ $dashboardUrl }}">Ke Dasbor</a>
    @else
        <a class="btn btn-primary" href="{{ url('/') }}">Ke Beranda</a>
    @endif
@endsection
```

`resources/views/errors/404.blade.php`:

```blade
@extends('errors.layout')

@section('title', 'Halaman tidak ditemukan')
@section('code', '404')
@section('heading', 'Halaman tidak ditemukan')
@section('message', 'Alamat yang Anda buka tidak tersedia atau sudah dipindahkan.')

@section('actions')
    <a class="btn btn-primary" href="{{ url('/') }}">Ke Beranda</a>
    @if (Route::has('doctors.index'))
        <a class="btn btn-outline-primary" href="{{ route('doctors.index') }}">Cari Dokter</a>
    @endif
    @if (Route::has('schedules.index'))
        <a class="btn btn-outline-primary" href="{{ route('schedules.index') }}">Jadwal Dokter</a>
    @endif
@endsection
```

`resources/views/errors/419.blade.php`:

```blade
@extends('errors.layout')

@section('title', 'Sesi berakhir')
@section('code', '419')
@section('heading', 'Sesi berakhir')
@section('message', 'Sesi Anda telah berakhir. Muat ulang halaman lalu coba lagi.')

@section('actions')
    <a class="btn btn-primary" href="{{ url()->previous(url('/')) }}">Muat Ulang</a>
    <a class="btn btn-outline-secondary" href="{{ url('/') }}">Ke Beranda</a>
@endsection
```

`resources/views/errors/429.blade.php`:

```blade
@extends('errors.layout')

@section('title', 'Terlalu banyak permintaan')
@section('code', '429')
@section('heading', 'Terlalu banyak permintaan')
@section('message', 'Anda terlalu sering mengirim permintaan. Coba lagi dalam '.($retryAfter ?? 60).' detik.')

@section('actions')
    <a class="btn btn-outline-secondary" href="{{ url()->previous(url('/')) }}">Kembali</a>
    <a class="btn btn-primary" href="{{ url('/') }}">Ke Beranda</a>
@endsection
```

`resources/views/errors/500.blade.php`:

```blade
@extends('errors.layout')

@section('title', 'Terjadi kesalahan pada server')
@section('code', '500')
@section('heading', 'Terjadi kesalahan pada server')
@section('message', 'Kami mencatat masalah ini. Silakan coba lagi beberapa saat lagi.')

@section('actions')
    <a class="btn btn-primary" href="{{ url('/') }}">Ke Beranda</a>
@endsection
```

`resources/views/errors/503.blade.php`:

```blade
@extends('errors.layout')

@section('title', 'Sedang dalam pemeliharaan')
@section('code', '503')
@section('heading', 'Sedang dalam pemeliharaan')
@section('message', 'Layanan akan segera kembali. Terima kasih atas kesabaran Anda.')
```

- [ ] **Step 27: Jalankan tes — harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=ErrorPagesTest
```
Expected: PASS (ERR-01 dan ERR-04). Catatan: `Illuminate\Foundation\Exceptions\RegisterErrorViewPaths` menaruh `resources/views/errors` lebih dulu daripada view bawaan framework, sehingga `errors::404`/`errors::500` memakai berkas proyek.

- [ ] **Step 28: Seluruh suite, pint, dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test
& $PHP vendor\bin\pint --test
git add resources/views/errors tests/Feature/Errors/ErrorPagesTest.php
git commit -m "feat(fase-1): halaman galat Indonesia 403/404/419/429/500/503 yang mandiri"
```
Expected: seluruh suite hijau (termasuk tes fase 0 dan task 1–12), `pint --test` bersih.

---

### Task 14: Login & logout staf, middleware akun, kerangka & layout admin, dashboard sementara

**Files:**
- Create: `routes/admin.php`
- Create: `app/Http/Middleware/EnsureAccountType.php`, `app/Http/Middleware/EnsureAccountIsActive.php`
- Create: `app/Http/Controllers/Auth/AdminLoginController.php`, `app/Http/Controllers/Admin/DashboardController.php`
- Create: `app/Http/Requests/Auth/AdminLoginRequest.php`
- Create: `resources/views/auth/admin-login.blade.php`, `resources/views/admin/dashboard.blade.php`, `resources/views/components/layouts/admin.blade.php`
- Modify: `bootstrap/app.php`, `app/Providers/AppServiceProvider.php` (`configureRateLimiting()`)
- Test: `tests/Feature/Http/GuestRedirectTest.php`, `tests/Feature/Auth/AdminAreaAccessTest.php`, `tests/Feature/Auth/AdminLoginTest.php`, `tests/Feature/Errors/ExceptionRenderingTest.php`, `tests/Feature/Admin/AdminLayoutTest.php`, `tests/Feature/Access/AdminRouteMiddlewareTest.php`

**Interfaces:**
- Consumes: `User` + state factory, peran, `Tests\Support\InteractsWithStaff` (Task 2–3); `AuditLogger`, `Actor`, `AuditLog` (Task 4); `SettingsService` (Task 7); `x-layouts.auth`, `NavigationMenu`, halaman galat (Task 13); `x-breadcrumb`/`x-page-header`/`x-flash-alert`/`x-loading-button`/`x-form.input` (Task 10–11); `SafeRedirect::back()` (Task 12); alias Spatie `permission` (Task 3).
- Produces:
  - `bootstrap/app.php`: grup admin lewat `withRouting(then: …)` = `Route::middleware('web')->prefix('admin')->name('admin.')->where(array_fill_keys([22 parameter 11 §3.1], '[0-9]+'))->group(base_path('routes/admin.php'))` (grup pasien belum, fase 5); alias `account`, `active` (+ `role`, `permission`, `role_or_permission` dari Task 3); `redirectGuestsTo` (`admin`/`admin/*` ⇒ `admin.login`; lainnya ⇒ `login` bila ada, selain itu `home`); `redirectUsersTo` (staf ⇒ `admin.dashboard`; lainnya ⇒ `patient.dashboard` bila ada, selain itu `home`); `withExceptions`: `dontFlash([...5 field...])`, `shouldRenderJsonWhen(...)`, render `HttpExceptionInterface` 403/419/429 (11 §11.3 + F1-R6).
  - `routes/admin.php`: `admin.login` (GET, `guest`), `admin.login.store` (POST, `guest`, `throttle:login`), `admin.logout` (POST, `auth`, `account:staff`), grup G-ADMIN sementara `['auth','account:staff','active']` berisi `admin.dashboard` (GET `/`, `permission:dashboard.view`). Task 15 menambah `password.changed` dan route ganti password.
  - `EnsureAccountType::handle(Request, Closure, string $type)`, `EnsureAccountIsActive::handle(Request, Closure)` (11 §7.1–7.2 + F1-R2).
  - `AppServiceProvider::configureRateLimiting()` (protected): lima limiter `login`, `password-reset`, `register`, `booking`, `contact` (11 §10.3); kunci email = `Str::lower(trim(email))` + `|` + IP.
  - `AdminLoginRequest`: `authorize(): true`; `prepareForValidation()` (email trim + huruf kecil); `rules()`/`messages()` V-03; `authenticate(AuditLogger $audit): User` (`Auth::attempt` dengan `account_type=staff`, `is_active=true`, tanpa *remember*; gagal ⇒ audit `auth.staff_login_failed` lalu `ValidationException` `email` ⇒ `__('auth.failed')`).
  - `AdminLoginController`: `create`, `store`, `destroy` (audit `auth.staff_login` / `auth.staff_logout`; `last_login_at`; `redirect()->intended(route('admin.dashboard'))`; logout ⇒ invalidate + regenerateToken ⇒ `admin.login` + flash `success` "Anda telah keluar.").
  - `Admin\DashboardController::index()`; `x-layouts.admin` (`title`; slot default, `breadcrumb`, `actions`).

**Catatan teknis (diverifikasi pada source Laravel 12.69):**
- `RouteRegistrar` mengizinkan `where` sebagai atribut (daftar `$allowedAttributes`) dan `RouteGroup::formatWhere()` menggabungkannya, jadi `->where([...])` **sebelum** `->group()` valid; `Router::addWhereClausesToRoute()` menerapkannya ke setiap route di grup. Fase 1 belum punya route admin berparameter; pembatas ini mulai berlaku di fase 2.
- `TokenMismatchException` dan `AuthorizationException` diubah menjadi `HttpException` (419/403) oleh `Handler::prepareException()` **sebelum** callback `render()`, sehingga callback dipasang pada `HttpExceptionInterface`. `ThrottleRequestsException` adalah `HttpException` 429 dengan header `Retry-After`.
- Exception yang dilempar di dalam grup route dirender oleh `Illuminate\Routing\Pipeline::handleException()` **di dalam** tumpukan middleware, sehingga `->with('error', …)` tersimpan oleh `StartSession` dan `SecurityHeaders` tetap berlaku pada respons galat.
- `ValidateCsrfToken` (alias `VerifyCsrfToken`) dilewati bila `app()->runningUnitTests()` (`$app['env'] === 'testing'`). Untuk menguji 419 sungguhan, tes menyetel `$this->app['env'] = 'local'` (container `ArrayAccess`); middleware lalu memeriksa token dan melempar `TokenMismatchException` pada POST tanpa `_token`.
- Spatie `PermissionMiddleware` melempar `UnauthorizedException` (HttpException 403) berpesan Inggris "User does not have the right permissions." — handler 403 **tidak boleh** menampilkannya sebagai `$detail` (ERR-02: tanpa detail izin); karena itu pengecualian `UnauthorizedException` ditambahkan ke daftar pesan generik (penyimpangan kecil dari sketsa 11 §11.3).
- Mode ketat: `preventLazyLoading` hanya memicu pada model hasil query berbaris > 1; pengguna login dimuat lewat `first()` sehingga aman, tetapi tes Review Focus #1 tetap memeriksa jalur login sungguhan.

---

#### Siklus 1 — Kerangka routing admin, middleware akun, dan pengalihan tamu/pengguna

- [ ] **Step 1: Tulis `GuestRedirectTest` (Review Focus #2; gagal dulu)**

Buat `tests/Feature/Http/GuestRedirectTest.php`:

```php
<?php

namespace Tests\Feature\Http;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

class GuestRedirectTest extends TestCase
{
    use InteractsWithStaff;
    use RefreshDatabase;

    private function protectedRoute(): void
    {
        Route::middleware(['web', 'auth'])->get('/_uji/terlindungi', fn () => 'terlindungi')->name('uji.terlindungi');
        Route::getRoutes()->refreshNameLookups();
    }

    public function test_guest_on_the_admin_area_is_redirected_to_admin_login(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.login'));
    }

    public function test_guest_on_a_protected_non_admin_route_goes_home_while_login_route_does_not_exist(): void
    {
        $this->protectedRoute();

        // Cadangan F1-R2: hapus tes ini bersama cadangan di fase 5 (route `login` tersedia).
        if (! Route::has('login')) {
            $this->get('/_uji/terlindungi')->assertRedirect(route('home'));
        } else {
            $this->get('/_uji/terlindungi')->assertRedirect(route('login'));
        }
    }

    public function test_guest_on_a_protected_non_admin_route_goes_to_login_once_that_route_exists(): void
    {
        $this->protectedRoute();
        Route::middleware('web')->get('/_uji/masuk', fn () => 'masuk')->name('login');
        Route::getRoutes()->refreshNameLookups();

        $this->get('/_uji/terlindungi')->assertRedirect(route('login'));
    }

    public function test_staff_on_the_guest_only_admin_login_page_goes_to_the_admin_dashboard(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get('/admin/login')
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_patient_on_the_guest_only_admin_login_page_goes_home_while_patient_dashboard_does_not_exist(): void
    {
        // Cadangan F1-R2: hapus cabang ini di fase 5.
        $expected = Route::has('patient.dashboard') ? route('patient.dashboard') : route('home');

        $this->actingAs(User::factory()->patient()->create())
            ->get('/admin/login')
            ->assertRedirect($expected);
    }

    public function test_patient_on_a_guest_only_page_goes_to_the_patient_dashboard_once_it_exists(): void
    {
        Route::middleware('web')->get('/_uji/pasien', fn () => 'pasien')->name('patient.dashboard');
        Route::getRoutes()->refreshNameLookups();

        $this->actingAs(User::factory()->patient()->create())
            ->get('/admin/login')
            ->assertRedirect(route('patient.dashboard'));
    }
}
```

- [ ] **Step 2: Tulis `AdminAreaAccessTest` (tipe akun dan akun nonaktif; gagal dulu)**

Buat `tests/Feature/Auth/AdminAreaAccessTest.php`:

```php
<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

class AdminAreaAccessTest extends TestCase
{
    use InteractsWithStaff;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', 'account:staff', 'active'])->get('/_uji/area-staf', fn () => 'area staf')->name('uji.area-staf');
        Route::middleware(['web', 'auth', 'account:patient', 'active'])->get('/_uji/area-pasien', fn () => 'area pasien')->name('uji.area-pasien');
        Route::getRoutes()->refreshNameLookups();
    }

    public function test_staff_account_passes_the_staff_gate_and_patient_account_passes_the_patient_gate(): void
    {
        $this->actingAs($this->createStaff('content-admin'))->get('/_uji/area-staf')->assertOk()->assertSee('area staf');
        $this->actingAs(User::factory()->patient()->create())->get('/_uji/area-pasien')->assertOk()->assertSee('area pasien');
    }

    public function test_patient_on_a_staff_area_is_forbidden(): void
    {
        $this->actingAs(User::factory()->patient()->create())
            ->get('/_uji/area-staf')
            ->assertForbidden();
    }

    public function test_staff_on_a_patient_area_is_redirected_to_the_admin_dashboard_or_forbidden_for_json(): void
    {
        $staff = $this->createStaff('content-admin');

        $this->actingAs($staff)->get('/_uji/area-pasien')->assertRedirect(route('admin.dashboard'));
        $this->actingAs($staff)->getJson('/_uji/area-pasien')->assertForbidden();
    }

    public function test_staff_deactivated_during_a_running_session_is_logged_out_with_a_flash_message(): void
    {
        $staff = $this->createStaff('content-admin');
        $this->actingAs($staff)->get('/_uji/area-staf')->assertOk();

        $staff->forceFill(['is_active' => false])->save();

        $this->get('/_uji/area-staf')
            ->assertRedirect(route('admin.login'))
            ->assertSessionHas('error', 'Akun Anda dinonaktifkan. Silakan hubungi administrator rumah sakit.');
        $this->assertGuest();
    }

    public function test_patient_deactivated_during_a_running_session_is_logged_out_and_sent_to_login(): void
    {
        Route::middleware('web')->get('/_uji/masuk', fn () => 'masuk')->name('login');
        Route::getRoutes()->refreshNameLookups();
        $patient = User::factory()->patient()->create();
        $this->actingAs($patient)->get('/_uji/area-pasien')->assertOk();

        $patient->forceFill(['is_active' => false])->save();

        $this->get('/_uji/area-pasien')
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Akun Anda dinonaktifkan. Silakan hubungi administrator rumah sakit.');
        $this->assertGuest();
    }
}
```

- [ ] **Step 3: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="GuestRedirectTest|AdminAreaAccessTest"
```
Expected: FAIL — `GuestRedirectTest`: `Route [admin.login] not defined` / `Route [login] not defined` (belum ada grup admin dan pengalihan); `AdminAreaAccessTest`: `Target class [account] does not exist` (alias middleware belum ada).

- [ ] **Step 4: Tulis dua middleware**

`app/Http/Middleware/EnsureAccountType.php`:

```php
<?php

namespace App\Http\Middleware;

use App\Enums\AccountType;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Alias `account` (11 §7.1): `account:patient` atau `account:staff`.
 *
 * Pasien yang membuka URL admin ⇒ 403; staf yang membuka portal pasien ⇒ dialihkan ke dasbor admin.
 */
class EnsureAccountType
{
    public function handle(Request $request, Closure $next, string $type): Response
    {
        $required = AccountType::from($type);
        $user = $request->user();

        if ($user !== null && $user->account_type === $required) {
            return $next($request);
        }

        if ($required === AccountType::Staff) {
            Log::notice('Akses area admin oleh akun non-staf ditolak', [
                'user_id' => $user?->id,
                'url' => $request->fullUrl(),
                'ip' => $request->ip(),
            ]);

            abort(403, 'Anda tidak memiliki izin untuk mengakses halaman ini.');
        }

        if ($request->expectsJson()) {
            abort(403);
        }

        return redirect()->route('admin.dashboard');
    }
}
```

`app/Http/Middleware/EnsureAccountIsActive.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * Alias `active` (11 §7.2): memutus sesi akun yang dinonaktifkan saat sesi masih berjalan.
 * Login baru oleh akun nonaktif sudah gagal generik di Auth::attempt (kondisi is_active = true).
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->is_active) {
            return $next($request);
        }

        $isStaff = $user->isStaff();

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Cadangan F1-R2: `login` belum ada sampai fase 5.
        $loginRoute = $isStaff ? 'admin.login' : (Route::has('login') ? 'login' : 'home');

        return redirect()->route($loginRoute)
            ->with('error', 'Akun Anda dinonaktifkan. Silakan hubungi administrator rumah sakit.');
    }
}
```

- [ ] **Step 5: Tulis `bootstrap/app.php` (bentuk awal Task 14)**

Baca berkas (berisi alias Spatie dari Task 3), lalu timpa seluruh isinya. Render `HttpExceptionInterface` ditambahkan di Siklus 4.

```php
<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureAccountType;
use App\Models\User;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Grup pasien (routes/patient.php) ditambahkan pada fase 5.

            // Parameter admin berbasis id harus numerik (11 §1 aturan 2). Tidak memakai Route::pattern global
            // karena nama {doctor}, {clinic}, dst. juga dipakai route publik berbasis slug.
            Route::middleware('web')
                ->prefix('admin')
                ->name('admin.')
                ->where(array_fill_keys([
                    'doctor', 'specialty', 'clinic', 'schedule', 'exception', 'session', 'facility',
                    'roomCategory', 'patient', 'appointment', 'page', 'banner', 'article',
                    'articleCategory', 'gallery', 'faq', 'insurancePartner', 'user', 'role',
                    'auditLog', 'message', 'media',
                ], '[0-9]+'))
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'account' => EnsureAccountType::class,
            'active' => EnsureAccountIsActive::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);

        // Tamu yang membuka halaman ber-`auth`: /admin* ⇒ login admin; selain itu ⇒ login pasien.
        // Cadangan F1-R2: `login` belum ada sampai fase 5, jadi arahkan ke beranda.
        $middleware->redirectGuestsTo(function (Request $request): string {
            if ($request->is('admin', 'admin/*')) {
                return route('admin.login');
            }

            return Route::has('login') ? route('login') : route('home');
        });

        // Pengguna login yang membuka halaman ber-`guest`: arahkan sesuai tipe akun (cadangan F1-R2).
        $middleware->redirectUsersTo(function (Request $request): string {
            $user = $request->user();

            if ($user instanceof User && $user->isStaff()) {
                return route('admin.dashboard');
            }

            return Route::has('patient.dashboard') ? route('patient.dashboard') : route('home');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['password', 'password_confirmation', 'current_password', 'bpjs_number', 'payment_reference']);
        $exceptions->shouldRenderJsonWhen(fn (Request $request, Throwable $e): bool => $request->expectsJson()
            || $request->routeIs('admin.dashboard.chart', 'admin.media.*'));
    })->create();
```

- [ ] **Step 6: Tulis `routes/admin.php` (bentuk awal; throttle login menyusul di Siklus 5)**

```php
<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Auth\AdminLoginController;
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
    Route::post('/login', [AdminLoginController::class, 'store'])->name('login.store');
});

Route::post('/logout', [AdminLoginController::class, 'destroy'])
    ->middleware(['auth', 'account:staff'])
    ->name('logout');

// G-ADMIN sementara (Task 15 menambah `password.changed`).
Route::middleware(['auth', 'account:staff', 'active'])->group(function (): void {
    Route::get('/', [DashboardController::class, 'index'])
        ->middleware('permission:dashboard.view')
        ->name('dashboard');
});
```

- [ ] **Step 7: Jalankan tes — harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="GuestRedirectTest|AdminAreaAccessTest"
```
Expected: PASS. (Tes ini tidak menjalankan controller admin, jadi controller yang belum ada tidak masalah; `php artisan route:list` boleh dicoba untuk memastikan nama `admin.login`, `admin.login.store`, `admin.logout`, `admin.dashboard` terdaftar.)

- [ ] **Step 8: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint --test
git add bootstrap/app.php routes/admin.php app/Http/Middleware/EnsureAccountType.php app/Http/Middleware/EnsureAccountIsActive.php tests/Feature/Http/GuestRedirectTest.php tests/Feature/Auth/AdminAreaAccessTest.php
git commit -m "feat(fase-1): grup route admin, middleware account/active, dan pengalihan tamu/pengguna"
```

---

#### Siklus 2 — Login staf (`AdminLoginRequest`, `AdminLoginController@create/store`, view)

- [ ] **Step 9: Tulis `AdminLoginTest` (gagal dulu)**

Buat `tests/Feature/Auth/AdminLoginTest.php`:

```php
<?php

namespace Tests\Feature\Auth;

use App\Enums\ActorType;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

class AdminLoginTest extends TestCase
{
    use InteractsWithStaff;
    use RefreshDatabase;

    private const PASSWORD = 'Rahasia123';

    private function postLogin(string $email, string $password = self::PASSWORD): TestResponse
    {
        return $this->from(route('admin.login'))
            ->post(route('admin.login.store'), ['email' => $email, 'password' => $password]);
    }

    /** @param  array<string, mixed>  $attributes */
    private function staffAccount(string $role = 'content-admin', array $attributes = []): User
    {
        return $this->createStaff($role, $attributes + ['email' => 'konten@unpam.test', 'password' => self::PASSWORD]);
    }

    private function lastFailure(): AuditLog
    {
        return AuditLog::query()->where('action', 'auth.staff_login_failed')->latest('id')->firstOrFail();
    }

    public function test_login_page_renders_the_form_for_guests(): void
    {
        $response = $this->get(route('admin.login'));

        $response->assertOk()
            ->assertSee('Masuk Panel Admin')
            ->assertSee('action="'.route('admin.login.store').'"', false)
            ->assertSee('name="email"', false)
            ->assertSee('name="password"', false)
            ->assertSee('<meta name="robots" content="noindex,nofollow">', false)
            ->assertDontSee('Ingat saya');
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public function test_staff_can_log_in_and_it_is_audited_with_last_login_timestamp(): void
    {
        $staff = $this->staffAccount();

        $this->postLogin('konten@unpam.test')->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($staff);
        $this->assertNotNull($staff->fresh()->last_login_at);

        $log = AuditLog::query()->where('action', 'auth.staff_login')->sole();
        $this->assertSame(ActorType::Staff, $log->actor_type);
        $this->assertEquals($staff->id, $log->user_id);
        $this->assertEquals($staff->id, $log->auditable_id);
        $this->assertSame($staff->getMorphClass(), $log->auditable_type);
    }

    public function test_login_redirects_to_the_intended_admin_url_after_a_guest_redirect(): void
    {
        Route::middleware(['web', 'auth', 'account:staff', 'active'])->get('/admin/_uji-intended', fn () => 'ok')->name('uji.intended');
        Route::getRoutes()->refreshNameLookups();
        $this->staffAccount();

        $this->get('/admin/_uji-intended')->assertRedirect(route('admin.login'));
        $this->postLogin('konten@unpam.test')->assertRedirect(url('/admin/_uji-intended'));
    }

    public function test_session_id_changes_after_login(): void
    {
        $this->staffAccount();

        $this->get(route('admin.login'));
        $before = session()->getId();

        $this->postLogin('konten@unpam.test')->assertRedirect(route('admin.dashboard'));

        $this->assertNotSame($before, session()->getId());
    }

    public function test_email_with_spaces_and_uppercase_is_normalised_before_authentication(): void
    {
        $staff = $this->staffAccount('content-admin', ['email' => 'admin@x.test']);

        $this->postLogin('  Admin@X.TEST ')->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($staff);
    }

    public function test_unknown_email_and_wrong_password_give_identical_generic_responses(): void
    {
        $this->staffAccount();

        $unknown = $this->postLogin('tidak-ada@unpam.test', 'SalahSekali1');
        $unknownErrors = session('errors')->get('email');
        $wrong = $this->postLogin('konten@unpam.test', 'SalahSekali1');
        $wrongErrors = session('errors')->get('email');

        $this->assertSame($unknown->getStatusCode(), $wrong->getStatusCode());
        $this->assertSame($unknown->headers->get('Location'), $wrong->headers->get('Location'));
        $this->assertSame(['Email atau password salah.'], $unknownErrors);
        $this->assertSame($unknownErrors, $wrongErrors);
        $this->assertGuest();
    }

    public function test_failed_login_is_audited_with_a_reason_and_never_stores_the_password(): void
    {
        $staff = $this->staffAccount();

        $this->postLogin('konten@unpam.test', 'SalahSekali1');
        $bad = $this->lastFailure();
        $this->postLogin('tidak-ada@unpam.test', 'SalahSekali1');
        $unknown = $this->lastFailure();

        $this->assertSame(ActorType::Staff, $bad->actor_type);
        $this->assertEquals($staff->id, $bad->user_id);
        $this->assertEquals($staff->id, $bad->auditable_id);
        $this->assertEquals(['email' => 'konten@unpam.test', 'reason' => 'bad_credentials'], $bad->new_values);

        $this->assertSame(ActorType::System, $unknown->actor_type);
        $this->assertNull($unknown->user_id);
        $this->assertNull($unknown->auditable_id);
        $this->assertSame((new User)->getMorphClass(), $unknown->auditable_type);
        $this->assertEquals(['email' => 'tidak-ada@unpam.test', 'reason' => 'bad_credentials'], $unknown->new_values);

        $this->assertSame(0, DB::table('audit_logs')
            ->where('new_values', 'like', '%SalahSekali1%')
            ->orWhere('old_values', 'like', '%SalahSekali1%')
            ->orWhere('description', 'like', '%SalahSekali1%')
            ->count());
    }

    public function test_patient_account_cannot_log_in_through_the_admin_form(): void
    {
        User::factory()->patient()->create(['email' => 'pasien@unpam.test', 'password' => self::PASSWORD]);

        $this->postLogin('pasien@unpam.test')
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors(['email' => 'Email atau password salah.']);

        $this->assertGuest();
        $failure = $this->lastFailure();
        $this->assertSame('not_staff', $failure->new_values['reason']);
        $this->assertSame(ActorType::System, $failure->actor_type);
    }

    public function test_inactive_staff_cannot_log_in_and_gets_the_generic_message(): void
    {
        $staff = $this->staffAccount('content-admin', ['is_active' => false]);

        $this->postLogin('konten@unpam.test')
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors(['email' => 'Email atau password salah.']);

        $this->assertGuest();
        $this->get(route('admin.login'))->assertDontSee('Akun Anda tidak aktif');
        $failure = $this->lastFailure();
        $this->assertSame('inactive', $failure->new_values['reason']);
        $this->assertEquals($staff->id, $failure->user_id);
        $this->assertSame(ActorType::Staff, $failure->actor_type);
    }

    public function test_required_fields_and_email_format_are_validated_with_indonesian_messages(): void
    {
        $this->from(route('admin.login'))
            ->post(route('admin.login.store'), [])
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors(['email' => 'Email wajib diisi.', 'password' => 'Password wajib diisi.']);

        $this->postLogin('bukan-email')
            ->assertSessionHasErrors(['email' => 'Format email tidak valid.']);
        $this->assertGuest();
    }

    public function test_password_is_not_flashed_back_into_the_form(): void
    {
        $this->postLogin('konten@unpam.test', 'SalahSekali1');

        $this->assertNull(session('_old_input.password'));
    }
}
```

- [ ] **Step 10: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=AdminLoginTest
```
Expected: FAIL — semua tes: `Target class [App\Http\Controllers\Auth\AdminLoginController] does not exist` (respons 500).

- [ ] **Step 11: Tulis `AdminLoginRequest`**

`app/Http/Requests/Auth/AdminLoginRequest.php`:

```php
<?php

namespace App\Http\Requests\Auth;

use App\Enums\AccountType;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Actor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Email dinormalkan (trim + huruf kecil) sebelum validasi dan autentikasi (Review Focus #4). */
    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (is_string($email)) {
            $this->merge(['email' => Str::lower(trim($email))]);
        }
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.max' => 'Email maksimal 255 karakter.',
            'password.required' => 'Password wajib diisi.',
            'password.string' => 'Password tidak valid.',
            'password.max' => 'Password maksimal 255 karakter.',
        ];
    }

    /**
     * Autentikasi hanya untuk akun staf aktif, tanpa "ingat saya". Semua kegagalan memakai pesan generik
     * (akun tak terdaftar, password salah, akun pasien, akun nonaktif); alasan sebenarnya hanya masuk audit.
     */
    public function authenticate(AuditLogger $audit): User
    {
        $email = (string) $this->validated('email');

        $credentials = [
            'email' => $email,
            'password' => (string) $this->input('password'),
            'account_type' => AccountType::Staff->value,
            'is_active' => true,
        ];

        if (Auth::attempt($credentials)) {
            /** @var User $user */
            $user = Auth::user();

            return $user;
        }

        $this->recordFailure($audit, $email);

        throw ValidationException::withMessages(['email' => __('auth.failed')]);
    }

    private function recordFailure(AuditLogger $audit, string $email): void
    {
        $account = User::query()->where('email', $email)->first();

        $reason = match (true) {
            $account === null => 'bad_credentials',
            $account->account_type !== AccountType::Staff => 'not_staff',
            ! $account->is_active => 'inactive',
            default => 'bad_credentials',
        };

        $staff = $account !== null && $account->isStaff() ? $account : null;

        $audit->log(
            'auth.staff_login_failed',
            $staff ?? new User,
            null,
            ['email' => $email, 'reason' => $reason],
            'Percobaan masuk ke panel admin gagal.',
            $staff !== null ? Actor::staff($staff) : Actor::system(),
        );
    }
}
```

- [ ] **Step 12: Tulis `AdminLoginController` (`create`, `store`; `destroy` di Siklus 3) dan view login**

`app/Http/Controllers/Auth/AdminLoginController.php`:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AdminLoginRequest;
use App\Services\Audit\AuditLogger;
use App\Support\Actor;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AdminLoginController extends Controller
{
    public function create(): View
    {
        return view('auth.admin-login');
    }

    public function store(AdminLoginRequest $request, AuditLogger $audit): RedirectResponse
    {
        $user = $request->authenticate($audit);

        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();

        $audit->log(
            'auth.staff_login',
            $user,
            null,
            null,
            "Staf {$user->name} masuk ke panel admin.",
            Actor::staff($user),
        );

        return redirect()->intended(route('admin.dashboard'));
    }
}
```

`resources/views/auth/admin-login.blade.php`:

```blade
<x-layouts.auth title="Masuk Panel Admin" subtitle="Khusus staf rumah sakit. Masuk dengan akun staf Anda.">
    <form method="POST" action="{{ route('admin.login.store') }}" novalidate>
        @csrf

        <x-form.input name="email" label="Email" type="email" required autocomplete="username" inputmode="email" />
        <x-form.input name="password" label="Password" type="password" required autocomplete="current-password" />

        <x-loading-button class="w-100 mt-2" loading-text="Memproses…">Masuk</x-loading-button>
    </form>

    <x-slot:footer>
        <p class="mb-0 text-body-secondary">Lupa password? Hubungi Super Admin.</p>
    </x-slot:footer>
</x-layouts.auth>
```

- [ ] **Step 13: Jalankan tes — harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=AdminLoginTest
```
Expected: PASS (AKS-10 dan AKS-08 sisi login, SEK-16, SEK-02 sisi login, audit `auth.staff_login`/`auth.staff_login_failed`, Review Focus #4 sisi autentikasi). Bila `test_password_is_not_flashed_back_into_the_form` gagal, pastikan `password` ada di `dontFlash` bawaan `Handler` (`password`, `password_confirmation`, `current_password`) — daftar proyek di `bootstrap/app.php` melengkapinya.

- [ ] **Step 14: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint --test
git add app/Http/Requests/Auth/AdminLoginRequest.php app/Http/Controllers/Auth/AdminLoginController.php resources/views/auth/admin-login.blade.php tests/Feature/Auth/AdminLoginTest.php
git commit -m "feat(fase-1): login staf dengan pesan generik, normalisasi email, dan audit"
```

---

#### Siklus 3 — Logout staf

- [ ] **Step 15: Tambah tes logout ke `AdminLoginTest` (gagal dulu)**

Tempel di akhir kelas `AdminLoginTest`:

```php

    // --- logout ------------------------------------------------------------------------------

    public function test_logout_audits_invalidates_the_session_and_issues_a_new_csrf_token(): void
    {
        $staff = $this->staffAccount();
        $this->postLogin('konten@unpam.test');
        $idBefore = session()->getId();
        $tokenBefore = session()->token();

        $this->post(route('admin.logout'))
            ->assertRedirect(route('admin.login'))
            ->assertSessionHas('success', 'Anda telah keluar.');

        $this->assertGuest();
        $this->assertNotSame($idBefore, session()->getId());
        $this->assertNotSame($tokenBefore, session()->token());

        $log = AuditLog::query()->where('action', 'auth.staff_logout')->sole();
        $this->assertSame(ActorType::Staff, $log->actor_type);
        $this->assertEquals($staff->id, $log->user_id);
    }

    public function test_guest_logout_goes_to_admin_login_and_patient_logout_is_forbidden(): void
    {
        $this->post(route('admin.logout'))->assertRedirect(route('admin.login'));

        $this->actingAs(User::factory()->patient()->create())
            ->post(route('admin.logout'))
            ->assertForbidden();
    }
```

- [ ] **Step 16: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=AdminLoginTest
```
Expected: FAIL — `test_logout_audits…`: `Method App\Http\Controllers\Auth\AdminLoginController::destroy does not exist` (500 saat staf login POST logout); `test_guest_logout…` bagian pasien juga 500? Pasien ditolak di middleware (403) sehingga bagian itu lulus; tes pertama yang gagal.

- [ ] **Step 17: Tambahkan `destroy` ke `AdminLoginController`**

Ganti seluruh `app/Http/Controllers/Auth/AdminLoginController.php`:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AdminLoginRequest;
use App\Services\Audit\AuditLogger;
use App\Support\Actor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminLoginController extends Controller
{
    public function create(): View
    {
        return view('auth.admin-login');
    }

    public function store(AdminLoginRequest $request, AuditLogger $audit): RedirectResponse
    {
        $user = $request->authenticate($audit);

        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();

        $audit->log(
            'auth.staff_login',
            $user,
            null,
            null,
            "Staf {$user->name} masuk ke panel admin.",
            Actor::staff($user),
        );

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user();

        $audit->log(
            'auth.staff_logout',
            $user,
            null,
            null,
            "Staf {$user->name} keluar dari panel admin.",
            Actor::staff($user),
        );

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')->with('success', 'Anda telah keluar.');
    }
}
```

- [ ] **Step 18: Jalankan tes — harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=AdminLoginTest
```
Expected: PASS (SEK-02 sisi logout, audit `auth.staff_logout`).

- [ ] **Step 19: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint --test
git add app/Http/Controllers/Auth/AdminLoginController.php tests/Feature/Auth/AdminLoginTest.php
git commit -m "feat(fase-1): logout staf dengan invalidate sesi, token baru, dan audit"
```

---

#### Siklus 4 — Penanganan 403/419/429 (F1-R6), ERR-02, ERR-03

- [ ] **Step 20: Tulis `ExceptionRenderingTest` dan tes CSRF di `AdminLoginTest` (gagal dulu)**

Buat `tests/Feature/Errors/ExceptionRenderingTest.php`:

```php
<?php

namespace Tests\Feature\Errors;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

class ExceptionRenderingTest extends TestCase
{
    use InteractsWithStaff;
    use RefreshDatabase;

    public function test_403_with_a_custom_indonesian_message_shows_it_on_the_error_page(): void
    {
        Route::middleware('web')->get('/_uji/dilarang', fn () => abort(403, 'Pesan khusus uji.'));

        $this->get('/_uji/dilarang')
            ->assertForbidden()
            ->assertSee('Akses ditolak')
            ->assertSee('Pesan khusus uji.');
    }

    public function test_403_from_a_missing_permission_never_leaks_the_permission_library_message(): void
    {
        Route::middleware(['web', 'auth', 'permission:settings.manage'])->get('/_uji/pengaturan', fn () => 'rahasia');
        $staff = $this->createStaff('content-admin');

        $this->actingAs($staff)->get('/_uji/pengaturan')
            ->assertForbidden()
            ->assertSee('Akses ditolak')
            ->assertSee('Ke Dasbor')
            ->assertDontSee('right permissions')
            ->assertDontSee('settings.manage')
            ->assertDontSee('rahasia');

        $this->actingAs($staff)->getJson('/_uji/pengaturan')
            ->assertForbidden()
            ->assertExactJson(['message' => 'Anda tidak memiliki izin untuk melakukan tindakan ini.']);
    }

    public function test_403_from_the_gate_is_generic_for_html_and_json(): void
    {
        Route::middleware(['web', 'auth', 'can:settings.manage'])->get('/_uji/gerbang', fn () => 'rahasia');
        $staff = $this->createStaff('content-admin');

        $this->actingAs($staff)->get('/_uji/gerbang')
            ->assertForbidden()
            ->assertSee('Akses ditolak')
            ->assertDontSee('This action is unauthorized');

        $this->actingAs($staff)->getJson('/_uji/gerbang')
            ->assertForbidden()
            ->assertExactJson(['message' => 'Anda tidak memiliki izin untuk melakukan tindakan ini.']);
    }

    public function test_419_json_response_has_an_indonesian_message(): void
    {
        $this->app['env'] = 'local'; // ValidateCsrfToken hanya dilewati saat env `testing`
        Route::middleware('web')->post('/_uji/kirim', fn () => 'ok');

        $this->postJson('/_uji/kirim', [])
            ->assertStatus(419)
            ->assertExactJson(['message' => 'Sesi Anda telah berakhir. Muat ulang halaman.']);
    }

    public function test_429_on_get_renders_the_error_page_with_the_retry_after_header(): void
    {
        Route::middleware(['web', 'throttle:2,1'])->get('/_uji/padat', fn () => 'ok');

        $this->get('/_uji/padat')->assertOk();
        $this->get('/_uji/padat')->assertOk();
        $response = $this->get('/_uji/padat');

        $response->assertStatus(429)
            ->assertSee('Terlalu banyak permintaan')
            ->assertHeader('Retry-After');
        $this->assertMatchesRegularExpression('/Coba lagi dalam \d+ detik\./', $response->getContent());
    }

    public function test_429_json_response_has_an_indonesian_message_and_keeps_the_headers(): void
    {
        Route::middleware(['web', 'throttle:1,1'])->get('/_uji/padat-json', fn () => 'ok');

        $this->getJson('/_uji/padat-json')->assertOk();
        $response = $this->getJson('/_uji/padat-json');

        $response->assertStatus(429)->assertHeader('Retry-After');
        $this->assertMatchesRegularExpression('/^Terlalu banyak percobaan\. Silakan coba lagi dalam \d+ detik\.$/', $response->json('message'));
    }

    public function test_429_on_post_redirects_back_with_a_flash_message_and_the_retry_after_header(): void
    {
        Route::middleware(['web', 'throttle:1,1'])->post('/_uji/kirim-padat', fn () => 'ok');

        $this->from('/_uji/form')->post('/_uji/kirim-padat', ['nama' => 'Budi', 'password' => 'RahasiaUji1'])->assertOk();
        $response = $this->from('/_uji/form')->post('/_uji/kirim-padat', ['nama' => 'Budi', 'password' => 'RahasiaUji1']);

        $response->assertRedirect('/_uji/form')
            ->assertHeader('Retry-After')
            ->assertSessionHas('error', fn ($message) => preg_match('/^Terlalu banyak percobaan\. Silakan coba lagi dalam \d+ detik\.$/', $message) === 1)
            ->assertSessionHas('_old_input.nama', 'Budi')
            ->assertSessionMissing('_old_input.password');
    }
}
```

Tempel di akhir kelas `AdminLoginTest` (ERR-03):

```php

    // --- CSRF (ERR-03) -----------------------------------------------------------------------

    public function test_missing_csrf_token_on_login_redirects_back_with_a_session_expired_message(): void
    {
        $this->app['env'] = 'local'; // mengaktifkan kembali ValidateCsrfToken (dilewati saat env `testing`)

        $this->from(route('admin.login'))
            ->post(route('admin.login.store'), ['email' => 'konten@unpam.test', 'password' => 'RahasiaUji1'])
            ->assertRedirect(route('admin.login'))
            ->assertSessionHas('error', 'Sesi formulir Anda telah berakhir. Silakan periksa isian Anda lalu kirim ulang.')
            ->assertSessionMissing('_old_input.password');

        $this->assertGuest();
    }

    public function test_a_valid_csrf_token_passes_the_csrf_check(): void
    {
        $this->app['env'] = 'local';

        $this->withSession(['_token' => 'token-uji'])
            ->from(route('admin.login'))
            ->post(route('admin.login.store'), ['_token' => 'token-uji', 'email' => '', 'password' => ''])
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors(['email', 'password'])
            ->assertSessionMissing('error');
    }
```

- [ ] **Step 21: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="ExceptionRenderingTest|AdminLoginTest"
```
Expected: FAIL — 403 menampilkan "User does not have the right permissions."; JSON 403 berpesan Inggris; 419 menghasilkan halaman `errors::419` (bukan redirect + flash) dan JSON bawaan Laravel; 429 POST bukan redirect.

- [ ] **Step 22: Tambahkan handler 403/419/429 ke `bootstrap/app.php`**

Tambahkan dua impor di blok `use` (urut alfabet: `App\Support\SafeRedirect` setelah `App\Models\User`; `Spatie\Permission\Exceptions\UnauthorizedException` sebelum `Spatie\Permission\Middleware\PermissionMiddleware`; `Symfony\Component\HttpKernel\Exception\HttpExceptionInterface` paling bawah):

```php
use App\Support\SafeRedirect;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
```

Lalu ganti blok `withExceptions` dengan:

```php
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['password', 'password_confirmation', 'current_password', 'bpjs_number', 'payment_reference']);
        $exceptions->shouldRenderJsonWhen(fn (Request $request, Throwable $e): bool => $request->expectsJson()
            || $request->routeIs('admin.dashboard.chart', 'admin.media.*'));

        // 403 / 419 / 429 berbahasa Indonesia (11 §11.3 + F1-R6). AuthorizationException sudah menjadi 403 dan
        // TokenMismatchException menjadi 419 sebelum callback ini dipanggil; ThrottleRequestsException adalah 429.
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            $status = $e->getStatusCode();

            if ($status === 403) {
                $message = $e->getMessage();
                // Pesan bawaan framework/Spatie berbahasa Inggris tidak ditampilkan (ERR-02: tanpa detail izin).
                $detail = ($e instanceof UnauthorizedException || in_array($message, ['', 'This action is unauthorized.', 'Forbidden'], true))
                    ? null
                    : $message;

                return $request->expectsJson()
                    ? response()->json(['message' => $detail ?? 'Anda tidak memiliki izin untuk melakukan tindakan ini.'], 403)
                    : response()->view('errors.403', ['detail' => $detail], 403);
            }

            if ($status === 419) {
                if ($request->expectsJson()) {
                    return response()->json(['message' => 'Sesi Anda telah berakhir. Muat ulang halaman.'], 419);
                }

                return redirect()->to(SafeRedirect::back($request))
                    ->withInput($request->except(['password', 'password_confirmation', 'current_password', 'payment_reference', 'bpjs_number']))
                    ->with('error', 'Sesi formulir Anda telah berakhir. Silakan periksa isian Anda lalu kirim ulang.');
            }

            if ($status === 429) {
                $retry = (int) ($e->getHeaders()['Retry-After'] ?? 60);
                $text = "Terlalu banyak percobaan. Silakan coba lagi dalam {$retry} detik.";

                if ($request->expectsJson()) {
                    return response()->json(['message' => $text], 429, $e->getHeaders());
                }

                if ($request->isMethod('POST')) {
                    return redirect()->to(SafeRedirect::back($request))
                        ->withInput($request->except(['password', 'password_confirmation']))
                        ->with('error', $text)
                        ->header('Retry-After', (string) $retry);
                }

                return response()->view('errors.429', ['retryAfter' => $retry], 429, $e->getHeaders());
            }

            return null; // 404/500/503 ⇒ view errors::{status}
        });
    })->create();
```

- [ ] **Step 23: Jalankan tes — harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="ExceptionRenderingTest|AdminLoginTest|AdminAreaAccessTest|GuestRedirectTest|ErrorPagesTest"
```
Expected: PASS (ERR-03, F1-R6 untuk GET/POST/JSON, ERR-02 sisi handler). Bila `test_429_on_post…` gagal pada `_old_input.nama`, pastikan `from('/_uji/form')` dipakai (`SafeRedirect::back()` memakai `url()->previous(route('home'))`).

- [ ] **Step 24: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint --test
git add bootstrap/app.php tests/Feature/Errors/ExceptionRenderingTest.php tests/Feature/Auth/AdminLoginTest.php
git commit -m "feat(fase-1): handler 403/419/429 Indonesia, throttle POST redirect dengan Retry-After"
```

---

#### Siklus 5 — Rate limiter dan throttle login (SEK-03)

- [ ] **Step 25: Tambah tes throttle ke `AdminLoginTest` (gagal dulu)**

Tempel di akhir kelas:

```php

    // --- throttle ----------------------------------------------------------------------------

    public function test_sixth_failed_attempt_is_throttled_with_a_flash_message_and_retry_after_header(): void
    {
        $this->staffAccount();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postLogin('konten@unpam.test', 'SalahSekali1')
                ->assertRedirect(route('admin.login'))
                ->assertSessionHasErrors('email');
        }

        $sixth = $this->postLogin('konten@unpam.test', 'SalahSekali1');

        $sixth->assertRedirect(route('admin.login'))
            ->assertHeader('Retry-After')
            ->assertSessionHas('error', fn ($message) => preg_match('/^Terlalu banyak percobaan\. Silakan coba lagi dalam \d+ detik\.$/', $message) === 1);

        // Email lain tidak terkena batas email yang sama.
        $this->postLogin('lain@unpam.test', 'SalahSekali1')->assertSessionHasErrors('email');
    }

    public function test_throttle_key_is_shared_across_email_case_and_spacing(): void
    {
        $this->staffAccount('content-admin', ['email' => 'admin@x.test']);

        foreach (['  Admin@X.TEST ', 'admin@x.test', 'ADMIN@x.test ', 'admin@X.test', 'Admin@x.test'] as $variant) {
            $this->postLogin($variant, 'SalahSekali1')->assertSessionHasErrors('email');
        }

        $this->postLogin('admin@x.test', 'SalahSekali1')
            ->assertHeader('Retry-After')
            ->assertSessionHas('error');
    }
```

- [ ] **Step 26: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=AdminLoginTest
```
Expected: FAIL — percobaan ke-6 tidak dibatasi (respons tetap redirect galat validasi tanpa header `Retry-After`; `assertHeader` gagal) karena route `admin.login.store` belum memakai `throttle:login` dan limiter belum ada.

- [ ] **Step 27: Limiter di `AppServiceProvider` dan `throttle:login` di route**

Tulis ulang `app/Providers/AppServiceProvider.php` (isi akhir fase 1; gabungan Task 2, 3, 7, 11, 13, 14):

```php
<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Settings\SettingsService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Satu instance per permintaan (cache memori + cache aplikasi), 11 §3.3.
        $this->app->singleton(SettingsService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Mode ketat hanya di non-produksi: lazy loading, atribut hilang, dan atribut yang
        // diam-diam dibuang langsung melempar galat.
        Model::shouldBeStrict(! $this->app->isProduction());

        // Paginasi Bootstrap 5 (view terbit di resources/views/vendor/pagination).
        Paginator::useBootstrapFive();

        // Super Admin melewati semua Gate/Policy (00 G). Hanya untuk pengguna login.
        Gate::before(fn (User $user, string $ability) => $user->hasRole('super-admin') ? true : null);

        // Pengaturan situs tersedia di semua layout sebagai $site (SettingsService).
        View::composer(['components.layouts.*'], function ($view): void {
            $view->with('site', $this->app->make(SettingsService::class));
        });

        // Rate limiter 00 H (rinci 11 §10.3).
        $this->configureRateLimiting();
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by($this->emailKey($request)));
        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinutes(10, 3)->by($this->emailKey($request)));
        RateLimiter::for('register', fn (Request $request) => Limit::perHour(5)->by((string) $request->ip()));
        RateLimiter::for('booking', fn (Request $request) => Limit::perMinute(10)->by((string) ($request->user()?->id ?: $request->ip())));
        RateLimiter::for('contact', fn (Request $request) => Limit::perMinutes(10, 3)->by((string) $request->ip()));
    }

    /**
     * Kunci limiter berbasis email dinormalkan (trim + huruf kecil) agar variasi penulisan berbagi satu
     * penghitung (Review Focus #4); nilai bukan string (mis. `email[]=x`) dianggap kosong.
     */
    protected function emailKey(Request $request): string
    {
        $email = $request->input('email');

        return Str::lower(trim(is_string($email) ? $email : '')).'|'.$request->ip();
    }
}
```

Di `routes/admin.php` ubah baris route `login.store`:

```php
    Route::post('/login', [AdminLoginController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.store');
```

- [ ] **Step 28: Jalankan tes — harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="AdminLoginTest|ExceptionRenderingTest"
```
Expected: PASS (SEK-03: percobaan ke-6 ⇒ redirect + flash berisi detik + `Retry-After`, email lain tidak terkena; Review Focus #4: kunci limiter bersama untuk variasi huruf/spasi).

- [ ] **Step 29: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint --test
git add app/Providers/AppServiceProvider.php routes/admin.php tests/Feature/Auth/AdminLoginTest.php
git commit -m "feat(fase-1): rate limiter bernama dengan kunci email ternormalisasi dan throttle login admin"
```

---

#### Siklus 5b — Login sukses membersihkan penghitung limiter `login` (12 §5.1 butir 3, 06 §14.A langkah 5)

Middleware `throttle:login` menghitung **setiap** permintaan (termasuk yang sukses). Tanpa pembersihan, staf yang salah ketik empat kali lalu berhasil masuk akan langsung terkunci begitu salah ketik lagi setelah keluar. Kunci yang dipakai `ThrottleRequests::handleRequestUsingNamedLimiter()` (Laravel 12.69, `$shouldHashKeys = true`) adalah `md5('login'.$limit->key)` dengan `$limit->key` = `emailKey()` di `AppServiceProvider` (`Str::lower(trim($email)).'|'.$ip`).

- [ ] **Step 29a: Tambah tes ke `AdminLoginTest` (gagal dulu)**

Tempel di akhir kelas (setelah tes throttle):

```php

    public function test_successful_login_clears_the_login_throttle_counter(): void
    {
        $this->staffAccount();

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $this->postLogin('konten@unpam.test', 'SalahSekali1')->assertSessionHasErrors('email');
        }

        // Permintaan ke-5 (sukses) ikut dihitung middleware; setelah itu penghitung harus kembali nol.
        $this->postLogin('  Konten@Unpam.TEST ')->assertRedirect(route('admin.dashboard'));
        $this->app['auth']->guard('web')->logout();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postLogin('konten@unpam.test', 'SalahSekali1')
                ->assertSessionHasErrors('email')
                ->assertHeaderMissing('Retry-After');
        }

        $this->postLogin('konten@unpam.test', 'SalahSekali1')->assertHeader('Retry-After');
    }
```

- [ ] **Step 29b: Jalankan tes — harus GAGAL**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=test_successful_login_clears_the_login_throttle_counter
```
Expected: FAIL — percobaan gagal pertama setelah login sukses sudah dibatasi (`Response has unexpected [Retry-After] header`), karena penghitung masih bernilai 5.

- [ ] **Step 29c: Bersihkan penghitung di `AdminLoginController::store()`**

Tambahkan impor di `app/Http/Controllers/Auth/AdminLoginController.php`:

```php
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
```

dan ganti method `store()` menjadi:

```php
    public function store(AdminLoginRequest $request, AuditLogger $audit): RedirectResponse
    {
        $user = $request->authenticate($audit);

        $request->session()->regenerate();

        // 12 §5.1 butir 3: penghitung limiter `login` (email + IP) dibersihkan setelah login sukses.
        // Kunci sama dengan ThrottleRequests: md5(nama limiter . kunci Limit) — lihat AppServiceProvider::emailKey().
        RateLimiter::clear(md5('login'.Str::lower(trim((string) $request->input('email'))).'|'.$request->ip()));

        $user->forceFill(['last_login_at' => now()])->save();

        $audit->log(
            'auth.staff_login',
            $user,
            null,
            null,
            "Staf {$user->name} masuk ke panel admin.",
            Actor::staff($user),
        );

        return redirect()->intended(route('admin.dashboard'));
    }
```

- [ ] **Step 29d: Jalankan tes — harus LULUS**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=AdminLoginTest
```
Expected: PASS (seluruh `AdminLoginTest`, termasuk SEK-03 dan tes baru).

- [ ] **Step 29e: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint --test
git add app/Http/Controllers/Auth/AdminLoginController.php tests/Feature/Auth/AdminLoginTest.php
git commit -m "feat(fase-1): bersihkan penghitung limiter login setelah login staf berhasil"
```

---

#### Siklus 6 — Layout admin, dasbor sementara, akses area admin

- [ ] **Step 30: Tulis `AdminLayoutTest` (Review Focus #1; gagal dulu)**

Buat `tests/Feature/Admin/AdminLayoutTest.php`:

```php
<?php

namespace Tests\Feature\Admin;

use App\Support\NavigationMenu;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

class AdminLayoutTest extends TestCase
{
    use InteractsWithStaff;
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string}>
     */
    public static function roles(): array
    {
        return [
            'super admin' => ['super-admin', 'Super Admin'],
            'admin konten' => ['content-admin', 'Admin Konten'],
            'petugas pendaftaran' => ['registration-officer', 'Petugas Pendaftaran'],
        ];
    }

    public function test_strict_mode_is_active_in_the_test_suite(): void
    {
        $this->assertTrue(Model::preventsLazyLoading());
    }

    #[DataProvider('roles')]
    public function test_dashboard_renders_for_each_role_with_strict_mode_using_acting_as(string $role, string $label): void
    {
        $staff = $this->createStaff($role, ['name' => 'Andi Uji']);

        $response = $this->actingAs($staff)->get('/admin');

        $response->assertOk()
            ->assertSee('Selamat datang, Andi Uji.')
            ->assertSee($label)
            ->assertSee('aria-label="Menu admin"', false)
            ->assertSee('UNPAM HOSPITAL Admin — prototipe');
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    #[DataProvider('roles')]
    public function test_dashboard_renders_for_each_role_after_a_real_login_with_strict_mode(string $role, string $label): void
    {
        $this->createStaff($role, ['email' => 'uji@unpam.test', 'password' => 'Rahasia123', 'name' => 'Budi Uji']);

        $this->post(route('admin.login.store'), ['email' => 'uji@unpam.test', 'password' => 'Rahasia123'])
            ->assertRedirect(route('admin.dashboard'));

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Selamat datang, Budi Uji.')
            ->assertSee($label);
    }

    public function test_layout_has_sidebar_topbar_user_menu_noindex_and_skip_link(): void
    {
        $response = $this->actingAs($this->createStaff('super-admin', ['name' => 'Andi Uji']))->get('/admin')->assertOk();

        $response->assertSee('<html lang="id">', false)
            ->assertSee('<meta name="robots" content="noindex,nofollow">', false)
            ->assertSee('class="skip-link" href="#konten-utama"', false)
            ->assertSee('<main id="konten-utama" tabindex="-1"', false)
            ->assertSee('offcanvas-lg', false)
            ->assertSee('aria-label="Buka menu admin"', false)
            ->assertSee('Lihat Situs')
            ->assertSee('target="_blank" rel="noopener"', false)
            ->assertSee('action="'.route('admin.logout').'"', false)
            ->assertSee('Keluar')
            ->assertSee('aria-current="page"', false);
    }

    public function test_user_name_is_escaped_in_the_greeting(): void
    {
        $staff = $this->createStaff('content-admin', ['name' => '<script>alert(1)</script>']);

        $this->actingAs($staff)->get('/admin')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_sidebar_hides_items_without_permission_and_groups_left_empty(): void
    {
        Route::middleware('web')->get('/_uji/boleh', fn () => 'ok')->name('uji.boleh');
        Route::middleware('web')->get('/_uji/dilarang', fn () => 'ok')->name('uji.dilarang');
        Route::getRoutes()->refreshNameLookups();
        config(['navigation.admin' => [
            ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'icon' => 'bi-speedometer2', 'permission' => 'dashboard.view'],
            ['group' => 'Grup Terlihat', 'items' => [
                ['label' => 'Item Boleh', 'route' => 'uji.boleh', 'icon' => 'bi-gear', 'permission' => 'doctors.manage'],
                ['label' => 'Item Dilarang', 'route' => 'uji.dilarang', 'icon' => 'bi-gear', 'permission' => 'staff.manage'],
            ]],
            ['group' => 'Grup Tersembunyi', 'items' => [
                ['label' => 'Hanya Dilarang', 'route' => 'uji.dilarang', 'icon' => 'bi-gear', 'permission' => 'audit.view'],
            ]],
        ]]);

        $this->actingAs($this->createStaff('content-admin'))->get('/admin')
            ->assertOk()
            ->assertSee('Grup Terlihat')
            ->assertSee('Item Boleh')
            ->assertDontSee('Item Dilarang')
            ->assertDontSee('Grup Tersembunyi')
            ->assertDontSee('Hanya Dilarang');

        $this->actingAs($this->createStaff('super-admin'))->get('/admin')
            ->assertSee('Item Dilarang')
            ->assertSee('Grup Tersembunyi');
    }

    public function test_real_admin_menu_always_starts_with_the_dashboard_for_every_role(): void
    {
        foreach (['super-admin', 'content-admin', 'registration-officer'] as $role) {
            $items = NavigationMenu::items('admin', $this->createStaff($role));

            $this->assertSame('admin.dashboard', $items[0]['route'] ?? null, "Peran {$role} harus melihat Dashboard.");
        }
    }
}
```

Tempel di akhir kelas `AdminAreaAccessTest` (tambahkan impor `use Spatie\Permission\Models\Role;` di blok import, urut alfabet di bawah `Illuminate\Support\Facades\Route`):

```php

    // --- dasbor admin: tamu, pasien, izin ----------------------------------------------------

    public function test_guest_on_the_dashboard_is_redirected_to_admin_login(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.login'));
    }

    public function test_patient_gets_the_indonesian_403_page_without_any_admin_content(): void
    {
        $response = $this->actingAs(User::factory()->patient()->create())->get('/admin');

        $response->assertForbidden()
            ->assertSee('<html lang="id">', false)
            ->assertSee('Akses ditolak')
            ->assertSee('Anda tidak memiliki izin untuk mengakses halaman ini.')
            ->assertDontSee('Selamat datang')
            ->assertDontSee('Menu admin')
            ->assertDontSee('Lihat Situs');
    }

    public function test_patient_mutation_requests_to_the_admin_area_are_forbidden(): void
    {
        $this->actingAs(User::factory()->patient()->create())
            ->post(route('admin.logout'))
            ->assertForbidden();
    }

    public function test_patient_json_request_to_the_admin_area_gets_a_json_403(): void
    {
        $this->actingAs(User::factory()->patient()->create())
            ->getJson('/admin')
            ->assertForbidden()
            ->assertExactJson(['message' => 'Anda tidak memiliki izin untuk mengakses halaman ini.']);
    }

    public function test_staff_without_the_dashboard_permission_gets_403_without_the_permission_library_message(): void
    {
        $this->seedRoles();
        Role::findByName('registration-officer')->revokePermissionTo('dashboard.view');
        $staff = $this->createStaff('registration-officer');

        $this->actingAs($staff)->get('/admin')
            ->assertForbidden()
            ->assertSee('Akses ditolak')
            ->assertDontSee('right permissions')
            ->assertDontSee('dashboard.view');
    }

    public function test_staff_with_the_dashboard_permission_can_open_it(): void
    {
        $this->actingAs($this->createStaff('content-admin'))->get('/admin')->assertOk();
    }
```

- [ ] **Step 31: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="AdminLayoutTest|AdminAreaAccessTest"
```
Expected: FAIL — `Target class [App\Http\Controllers\Admin\DashboardController] does not exist` (500 pada `/admin` untuk staf); tes pasien/tamu/izin yang berhenti di middleware sudah hijau.

- [ ] **Step 32: Tulis controller, view dasbor, dan layout admin**

`app/Http/Controllers/Admin/DashboardController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /** Dasbor sementara (F1-R4); widget dan grafik dibangun di fase 8. */
    public function index(Request $request): View
    {
        return view('admin.dashboard', ['user' => $request->user()]);
    }
}
```

`resources/views/admin/dashboard.blade.php`:

```blade
<x-layouts.admin title="Dasbor">
    <p class="lead mb-1">Selamat datang, {{ $user->name }}.</p>
    <p class="mb-4">Peran Anda: <strong>{{ $user->roleLabel }}</strong>.</p>
    <p class="text-body-secondary mb-0">Ringkasan angka dan grafik akan ditambahkan pada fase berikutnya.</p>
</x-layouts.admin>
```

`resources/views/components/layouts/admin.blade.php`:

```blade
@props(['title'])
@php
    $user = auth()->user();
    $siteName = $site->string('site.name', config('app.name'));
    $logoUrl = $site->logoUrl();
    $logoFallback = asset('images/placeholders/logo.svg');

    // Sidebar diratakan: judul grup menjadi baris tersendiri agar markup tautan tidak digandakan.
    $rows = [];
    foreach (\App\Support\NavigationMenu::items('admin', $user) as $entry) {
        if (isset($entry['group'])) {
            $rows[] = ['heading' => $entry['group']];
            foreach ($entry['items'] as $item) {
                $rows[] = $item;
            }
        } else {
            $rows[] = $entry;
        }
    }
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $title }} — {{ $siteName }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    @vite(['resources/js/admin.js'])
    @stack('head')
</head>
<body class="bg-light">
    <a class="skip-link" href="#konten-utama">Lewati ke konten utama</a>

    <div class="d-lg-flex min-vh-100">
        {{-- Sidebar: tetap di ≥ lg, offcanvas kiri di bawahnya --}}
        <aside class="offcanvas-lg offcanvas-start admin-sidebar" tabindex="-1" id="admin-sidebar" aria-labelledby="admin-sidebar-judul">
            <div class="offcanvas-header">
                <div class="d-flex align-items-center gap-2 text-white fw-bold" id="admin-sidebar-judul">
                    <img src="{{ $logoUrl }}" data-fallback="{{ $logoFallback }}" alt="" width="32" height="32">
                    <span>UNPAM Admin</span>
                </div>
                <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#admin-sidebar" aria-label="Tutup menu admin"></button>
            </div>
            <div class="offcanvas-body d-block p-2 overflow-auto">
                <nav aria-label="Menu admin">
                    <ul class="nav flex-column gap-1">
                        @foreach ($rows as $row)
                            @if (isset($row['heading']))
                                <li class="px-3 pt-3 pb-1 small text-uppercase fw-semibold text-white">{{ $row['heading'] }}</li>
                            @else
                                <li class="nav-item">
                                    <a @class([
                                        'nav-link d-flex align-items-center gap-2 rounded',
                                        'text-white' => ! $row['active'],
                                        'active bg-light text-primary fw-semibold' => $row['active'],
                                    ]) href="{{ $row['url'] }}" @if ($row['active']) aria-current="page" @endif>
                                        @if (! empty($row['icon']))
                                            <i class="bi {{ $row['icon'] }}" aria-hidden="true"></i>
                                        @endif
                                        <span>{{ $row['label'] }}</span>
                                    </a>
                                </li>
                            @endif
                        @endforeach
                    </ul>
                </nav>
            </div>
        </aside>

        <div class="flex-grow-1 d-flex flex-column">
            {{-- Topbar --}}
            <header class="bg-white border-bottom shadow-sm sticky-top">
                <div class="d-flex align-items-center gap-3 px-3 px-lg-4 py-2">
                    <button class="btn btn-outline-primary d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#admin-sidebar" aria-controls="admin-sidebar" aria-label="Buka menu admin">
                        <i class="bi bi-list" aria-hidden="true"></i>
                    </button>

                    <div class="flex-grow-1">{{ $breadcrumb ?? '' }}</div>

                    <a class="btn btn-link text-decoration-none d-none d-sm-inline-flex align-items-center gap-1" href="{{ route('home') }}" target="_blank" rel="noopener">
                        <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> Lihat Situs
                    </a>

                    <div class="dropdown">
                        <button class="btn btn-outline-primary dropdown-toggle d-inline-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle" aria-hidden="true"></i>
                            <span class="d-none d-sm-inline">{{ $user->name }}</span>
                            <span class="visually-hidden d-sm-none">Menu pengguna {{ $user->name }}</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <div class="dropdown-item-text">
                                    <span class="d-block fw-semibold">{{ $user->name }}</span>
                                    <span class="d-block small text-body-secondary">{{ $user->roleLabel }}</span>
                                </div>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ route('admin.logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item">
                                        <i class="bi bi-box-arrow-right" aria-hidden="true"></i> Keluar
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            </header>

            <main id="konten-utama" tabindex="-1" class="flex-grow-1 px-3 px-lg-4 py-4">
                @isset($actions)
                    <x-page-header :title="$title">
                        <x-slot:actions>{{ $actions }}</x-slot:actions>
                    </x-page-header>
                @else
                    <x-page-header :title="$title" />
                @endisset

                <x-flash-alert />

                {{ $slot }}
            </main>

            <footer class="border-top bg-white px-3 px-lg-4 py-3 small text-body-secondary">
                {{ $siteName }} Admin — prototipe
            </footer>
        </div>
    </div>
</body>
</html>
```

- [ ] **Step 33: Jalankan tes — harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="AdminLayoutTest|AdminAreaAccessTest|ExceptionRenderingTest"
```
Expected: PASS (Review Focus #1: dasbor 200 untuk ketiga peran dengan mode ketat, lewat `actingAs` **dan** login sungguhan; AKS-06 bagian `/admin`; ERR-02). Bila `LazyLoadingViolationException` muncul, catat relasi yang dipicu dan muat lebih awal di layout (`$user->loadMissing('roles')`) — jangan mematikan mode ketat.

- [ ] **Step 34: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint --test
git add app/Http/Controllers/Admin/DashboardController.php resources/views/admin/dashboard.blade.php resources/views/components/layouts/admin.blade.php tests/Feature/Admin/AdminLayoutTest.php tests/Feature/Auth/AdminAreaAccessTest.php
git commit -m "feat(fase-1): layout admin, dasbor sementara, dan tes akses area admin"
```

---

#### Siklus 7 — Penjaga KNS-02

- [ ] **Step 35: Tulis `AdminRouteMiddlewareTest` (penjaga + bukti bahwa penjaga bekerja)**

Buat `tests/Feature/Access/AdminRouteMiddlewareTest.php`:

```php
<?php

namespace Tests\Feature\Access;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * KNS-02: setiap route `admin.*` (selain login/logout/ganti password) wajib dipagari `auth`, `account:staff`,
 * `active`, dan `permission:*`. Tes ini otomatis mencakup route yang ditambahkan di fase berikutnya.
 */
class AdminRouteMiddlewareTest extends TestCase
{
    /** Route yang sengaja berada di luar G-ADMIN penuh. */
    private const EXEMPT = ['admin.login', 'admin.login.store', 'admin.logout', 'admin.password.edit', 'admin.password.update'];

    private const REQUIRED = ['auth', 'account:staff', 'active'];

    /**
     * @return list<string>
     */
    private function violations(): array
    {
        $violations = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $name = (string) $route->getName();

            if (! str_starts_with($name, 'admin.') || in_array($name, self::EXEMPT, true)) {
                continue;
            }

            $middleware = array_filter($route->gatherMiddleware(), 'is_string');

            foreach (self::REQUIRED as $required) {
                if (! in_array($required, $middleware, true)) {
                    $violations[] = "{$name}: tanpa middleware {$required}";
                }
            }

            if (collect($middleware)->doesntContain(fn (string $item) => str_starts_with($item, 'permission:'))) {
                $violations[] = "{$name}: tanpa middleware permission:*";
            }
        }

        return $violations;
    }

    public function test_every_admin_route_has_the_account_active_and_permission_middleware(): void
    {
        $this->assertSame([], $this->violations());
    }

    public function test_the_guard_detects_an_unprotected_admin_route(): void
    {
        Route::middleware('web')->get('/admin/_uji-bocor', fn () => 'bocor')->name('admin.uji.bocor');
        Route::middleware(['web', 'auth', 'account:staff', 'active'])->get('/admin/_uji-tanpa-izin', fn () => 'x')->name('admin.uji.tanpa-izin');

        $violations = $this->violations();

        $this->assertContains('admin.uji.bocor: tanpa middleware auth', $violations);
        $this->assertContains('admin.uji.bocor: tanpa middleware permission:*', $violations);
        $this->assertContains('admin.uji.tanpa-izin: tanpa middleware permission:*', $violations);
        $this->assertNotContains('admin.uji.tanpa-izin: tanpa middleware auth', $violations);
    }

    public function test_login_and_logout_routes_are_guarded_as_documented(): void
    {
        $routes = Route::getRoutes();

        $login = $routes->getByName('admin.login');
        $store = $routes->getByName('admin.login.store');
        $logout = $routes->getByName('admin.logout');

        $this->assertContains('guest', $login->gatherMiddleware());
        $this->assertContains('guest', $store->gatherMiddleware());
        $this->assertContains('throttle:login', $store->gatherMiddleware());
        $this->assertContains('auth', $logout->gatherMiddleware());
        $this->assertContains('account:staff', $logout->gatherMiddleware());
    }

    public function test_every_admin_route_lives_under_the_admin_url_prefix(): void
    {
        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (str_starts_with((string) $route->getName(), 'admin.')) {
                $this->assertTrue(
                    $route->uri() === 'admin' || str_starts_with($route->uri(), 'admin/'),
                    "Route {$route->getName()} berada di luar /admin ({$route->uri()})."
                );
            }
        }
    }
}
```

- [ ] **Step 36: Jalankan tes**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=AdminRouteMiddlewareTest
```
Expected: PASS (penjaga hijau karena routes/admin.php sudah benar; `test_the_guard_detects_an_unprotected_admin_route` membuktikan penjaga benar-benar menangkap pelanggaran — bila Anda menghapus `permission:dashboard.view` sementara dari route dasbor, `test_every_admin_route_…` harus gagal dengan `admin.dashboard: tanpa middleware permission:*`; kembalikan setelah dicoba).

- [ ] **Step 37: Seluruh suite, pint, dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test
& $PHP vendor\bin\pint --test
git add tests/Feature/Access/AdminRouteMiddlewareTest.php
git commit -m "test(fase-1): penjaga KNS-02 untuk middleware setiap route admin"
```
Expected: seluruh suite hijau, termasuk dua tes throttle `AdminLoginTest` (sudah lulus sejak Step 28).

---

#### Referensi — isi akhir berkas setelah Task 14

`routes/admin.php`:

```php
<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Auth\AdminLoginController;
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

// G-ADMIN sementara (Task 15 menambah `password.changed`).
Route::middleware(['auth', 'account:staff', 'active'])->group(function (): void {
    Route::get('/', [DashboardController::class, 'index'])
        ->middleware('permission:dashboard.view')
        ->name('dashboard');
});
```

`bootstrap/app.php`:

```php
<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureAccountType;
use App\Models\User;
use App\Support\SafeRedirect;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Grup pasien (routes/patient.php) ditambahkan pada fase 5.

            // Parameter admin berbasis id harus numerik (11 §1 aturan 2). Tidak memakai Route::pattern global
            // karena nama {doctor}, {clinic}, dst. juga dipakai route publik berbasis slug.
            Route::middleware('web')
                ->prefix('admin')
                ->name('admin.')
                ->where(array_fill_keys([
                    'doctor', 'specialty', 'clinic', 'schedule', 'exception', 'session', 'facility',
                    'roomCategory', 'patient', 'appointment', 'page', 'banner', 'article',
                    'articleCategory', 'gallery', 'faq', 'insurancePartner', 'user', 'role',
                    'auditLog', 'message', 'media',
                ], '[0-9]+'))
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'account' => EnsureAccountType::class,
            'active' => EnsureAccountIsActive::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);

        // Tamu yang membuka halaman ber-`auth`: /admin* ⇒ login admin; selain itu ⇒ login pasien.
        // Cadangan F1-R2: `login` belum ada sampai fase 5, jadi arahkan ke beranda.
        $middleware->redirectGuestsTo(function (Request $request): string {
            if ($request->is('admin', 'admin/*')) {
                return route('admin.login');
            }

            return Route::has('login') ? route('login') : route('home');
        });

        // Pengguna login yang membuka halaman ber-`guest`: arahkan sesuai tipe akun (cadangan F1-R2).
        $middleware->redirectUsersTo(function (Request $request): string {
            $user = $request->user();

            if ($user instanceof User && $user->isStaff()) {
                return route('admin.dashboard');
            }

            return Route::has('patient.dashboard') ? route('patient.dashboard') : route('home');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['password', 'password_confirmation', 'current_password', 'bpjs_number', 'payment_reference']);
        $exceptions->shouldRenderJsonWhen(fn (Request $request, Throwable $e): bool => $request->expectsJson()
            || $request->routeIs('admin.dashboard.chart', 'admin.media.*'));

        // 403 / 419 / 429 berbahasa Indonesia (11 §11.3 + F1-R6). AuthorizationException sudah menjadi 403 dan
        // TokenMismatchException menjadi 419 sebelum callback ini dipanggil; ThrottleRequestsException adalah 429.
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            $status = $e->getStatusCode();

            if ($status === 403) {
                $message = $e->getMessage();
                // Pesan bawaan framework/Spatie berbahasa Inggris tidak ditampilkan (ERR-02: tanpa detail izin).
                $detail = ($e instanceof UnauthorizedException || in_array($message, ['', 'This action is unauthorized.', 'Forbidden'], true))
                    ? null
                    : $message;

                return $request->expectsJson()
                    ? response()->json(['message' => $detail ?? 'Anda tidak memiliki izin untuk melakukan tindakan ini.'], 403)
                    : response()->view('errors.403', ['detail' => $detail], 403);
            }

            if ($status === 419) {
                if ($request->expectsJson()) {
                    return response()->json(['message' => 'Sesi Anda telah berakhir. Muat ulang halaman.'], 419);
                }

                return redirect()->to(SafeRedirect::back($request))
                    ->withInput($request->except(['password', 'password_confirmation', 'current_password', 'payment_reference', 'bpjs_number']))
                    ->with('error', 'Sesi formulir Anda telah berakhir. Silakan periksa isian Anda lalu kirim ulang.');
            }

            if ($status === 429) {
                $retry = (int) ($e->getHeaders()['Retry-After'] ?? 60);
                $text = "Terlalu banyak percobaan. Silakan coba lagi dalam {$retry} detik.";

                if ($request->expectsJson()) {
                    return response()->json(['message' => $text], 429, $e->getHeaders());
                }

                if ($request->isMethod('POST')) {
                    return redirect()->to(SafeRedirect::back($request))
                        ->withInput($request->except(['password', 'password_confirmation']))
                        ->with('error', $text)
                        ->header('Retry-After', (string) $retry);
                }

                return response()->view('errors.429', ['retryAfter' => $retry], 429, $e->getHeaders());
            }

            return null; // 404/500/503 ⇒ view errors::{status}
        });
    })->create();
```

`app/Providers/AppServiceProvider.php`: isi akhir persis seperti pada Step 27.

---

### Task 15: Ganti password staf & middleware `password.changed`

**Files:**
- Create: `app/Rules/PasswordRules.php`
- Create: `app/Http/Requests/Admin/UpdatePasswordRequest.php`
- Create: `app/Http/Controllers/Auth/AdminPasswordController.php`
- Create: `app/Http/Middleware/EnsurePasswordChanged.php`
- Create: `resources/views/auth/admin-change-password.blade.php`
- Modify: `routes/admin.php`, `bootstrap/app.php` (alias `password.changed`), `resources/views/components/layouts/admin.blade.php` (menu "Ganti Password"), `tests/Feature/Access/AdminRouteMiddlewareTest.php` (penjaga ikut memeriksa `password.changed`)
- Test: `tests/Unit/Rules/PasswordRulesTest.php`, `tests/Feature/Auth/AdminPasswordChangeTest.php`

**Interfaces:**
- Consumes: route, layout, dan middleware Task 14; `AuditLogger` + `Actor` (Task 4); `x-layouts.auth`, `x-form.input`, `x-loading-button` (Task 10–13).
- Produces:
  - `App\Rules\PasswordRules::default(): array` = `['required', 'confirmed', 'max:64', Password::min(8)->letters()->numbers()]` (12 §3.1; tanpa `uncompromised()` karena butuh internet — 00 H).
  - `App\Http\Requests\Admin\UpdatePasswordRequest` (V-08): `current_password` = `required|current_password`; `password` = `PasswordRules::default()` + `different:current_password`; `password_confirmation` = `required`; `messages()` Indonesia (V-07/V-08).
  - `AdminPasswordController::edit(Request): View` (`auth.admin-change-password`, variabel `$forced`) dan `update(UpdatePasswordRequest, AuditLogger): RedirectResponse` (`forceFill(['password' => …, 'must_change_password' => false])->save()`, `session()->regenerate()`, audit `auth.password_changed` old `{"must_change_password": <lama>}` ⇒ new `{"must_change_password": false}`, redirect `admin.dashboard` + flash `success` "Password berhasil diganti.").
  - `EnsurePasswordChanged::handle(Request, Closure)` (11 §7.3; alias `password.changed`).
  - Route `admin.password.edit` (GET `/admin/ganti-password`) dan `admin.password.update` (PUT) dengan middleware `auth`, `account:staff`, `active` (**tanpa** `password.changed`); grup G-ADMIN menjadi `['auth','account:staff','active','password.changed']`.

**Catatan teknis (diverifikasi pada source Laravel 12.69):**
- Kegagalan aturan `Illuminate\Validation\Rules\Password` dihasilkan oleh validator bersarang (`Password::passes()`), yang meneruskan `customMessages` induk. Pesan kustom dicari dengan kunci `"{attribute}.{rule}"`, `"{rule}"`, `"{attribute}"` (`Validator::getFromLocalArray()`); untuk `letters`/`numbers` nama aturannya `password.letters`/`password.numbers`, sehingga kunci pesan di `UpdatePasswordRequest::messages()` adalah `password.password.letters` dan `password.password.numbers`, sedangkan `min` memakai `password.min`.
- `password` ber-cast `hashed` pada `User` (Task 2): `forceFill(['password' => $plain])` otomatis di-hash bcrypt (`$2y$`); jangan memanggil `Hash::make()` lagi.

---

#### Siklus A — `PasswordRules`

- [ ] **Step 1: Tulis `PasswordRulesTest` (gagal dulu)**

Buat `tests/Unit/Rules/PasswordRulesTest.php`:

```php
<?php

namespace Tests\Unit\Rules;

use App\Rules\PasswordRules;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PasswordRulesTest extends TestCase
{
    public function test_default_bundle_matches_the_documented_rules(): void
    {
        $rules = PasswordRules::default();

        $this->assertCount(4, $rules);
        $this->assertSame('required', $rules[0]);
        $this->assertSame('confirmed', $rules[1]);
        $this->assertSame('max:64', $rules[2]);
        $this->assertInstanceOf(Password::class, $rules[3]);
    }

    /**
     * @return array<string, array{string, string|null, bool}>
     */
    public static function cases(): array
    {
        return [
            'valid' => ['Abcdef12', 'Abcdef12', true],
            'valid dengan simbol' => ['Rahasia123!', 'Rahasia123!', true],
            'password umum diterima karena tanpa pemeriksaan kebocoran (tanpa jaringan)' => ['Password123', 'Password123', true],
            'tepat 64 karakter' => [str_repeat('Aa1', 21).'A', str_repeat('Aa1', 21).'A', true],
            '65 karakter' => [str_repeat('Aa1', 21).'AB', str_repeat('Aa1', 21).'AB', false],
            'tanpa angka' => ['Abcdefgh', 'Abcdefgh', false],
            'tanpa huruf' => ['12345678', '12345678', false],
            'kurang dari 8 karakter' => ['Ab1', 'Ab1', false],
            'konfirmasi berbeda' => ['Abcdef12', 'Abcdef13', false],
            'tanpa konfirmasi' => ['Abcdef12', null, false],
            'kosong' => ['', '', false],
        ];
    }

    #[DataProvider('cases')]
    public function test_default_bundle_accepts_and_rejects_the_expected_values(string $password, ?string $confirmation, bool $valid): void
    {
        $data = ['password' => $password];

        if ($confirmation !== null) {
            $data['password_confirmation'] = $confirmation;
        }

        $this->assertSame($valid, Validator::make($data, ['password' => PasswordRules::default()])->passes());
    }
}
```

- [ ] **Step 2: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=PasswordRulesTest
```
Expected: FAIL — `Class "App\Rules\PasswordRules" not found`.

- [ ] **Step 3: Tulis `app/Rules/PasswordRules.php`**

```php
<?php

declare(strict_types=1);

namespace App\Rules;

use Illuminate\Validation\Rules\Password;

/**
 * Bundel aturan password baku (12 §3.1): wajib, terkonfirmasi, maksimal 64, minimal 8 berisi huruf dan angka.
 * `uncompromised()` sengaja tidak dipakai karena memerlukan koneksi internet (00 H).
 */
final class PasswordRules
{
    /**
     * @return list<mixed>
     */
    public static function default(): array
    {
        return ['required', 'confirmed', 'max:64', Password::min(8)->letters()->numbers()];
    }
}
```

- [ ] **Step 4: Jalankan tes — harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=PasswordRulesTest
```
Expected: PASS (12 tes: 1 + 11 kasus).

- [ ] **Step 5: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint --test
git add app/Rules/PasswordRules.php tests/Unit/Rules/PasswordRulesTest.php
git commit -m "feat(fase-1): bundel aturan PasswordRules"
```

---

#### Siklus B — Middleware `password.changed` dan kerangka route (AKS-09, bagian pengalihan)

- [ ] **Step 6: Tulis tes pengalihan di `AdminPasswordChangeTest` (gagal dulu)**

Buat `tests/Feature/Auth/AdminPasswordChangeTest.php`:

```php
<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

class AdminPasswordChangeTest extends TestCase
{
    use InteractsWithStaff;
    use RefreshDatabase;

    private const TEMPORARY_PASSWORD = 'SementaraAbc1';

    protected function setUp(): void
    {
        parent::setUp();

        // Route admin lain yang dipagari G-ADMIN penuh (fase 2+ menambah yang sungguhan).
        Route::middleware(['web', 'auth', 'account:staff', 'active', 'password.changed'])
            ->get('/admin/_uji-lain', fn () => 'halaman lain')
            ->name('uji.admin-lain');
        Route::getRoutes()->refreshNameLookups();
    }

    /** @param  array<string, mixed>  $attributes */
    private function forcedStaff(array $attributes = []): User
    {
        return $this->createStaff('content-admin', $attributes + [
            'email' => 'baru@unpam.test',
            'password' => self::TEMPORARY_PASSWORD,
            'must_change_password' => true,
        ]);
    }

    // --- AKS-09: pengalihan ------------------------------------------------------------------

    public function test_staff_who_must_change_password_is_redirected_from_every_admin_page(): void
    {
        $staff = $this->forcedStaff();

        $this->actingAs($staff)->get('/admin')
            ->assertRedirect(route('admin.password.edit'))
            ->assertSessionHas('warning', 'Anda harus mengganti password sementara sebelum melanjutkan.');

        $this->actingAs($staff)->get('/admin/_uji-lain')
            ->assertRedirect(route('admin.password.edit'));
    }

    public function test_json_requests_get_403_instead_of_a_redirect_while_the_password_is_temporary(): void
    {
        $this->actingAs($this->forcedStaff())->getJson('/admin/_uji-lain')
            ->assertForbidden()
            ->assertExactJson(['message' => 'Ganti password sementara terlebih dahulu.']);
    }

    public function test_staff_with_a_permanent_password_is_not_redirected(): void
    {
        $this->actingAs($this->createStaff('content-admin'))->get('/admin/_uji-lain')->assertOk()->assertSee('halaman lain');
    }

    public function test_logout_is_never_redirected_while_the_password_is_temporary(): void
    {
        $this->actingAs($this->forcedStaff())->post(route('admin.logout'))
            ->assertRedirect(route('admin.login'));

        $this->assertGuest();
    }

    public function test_login_of_forced_staff_leads_to_the_change_password_page(): void
    {
        $this->forcedStaff();

        $this->post(route('admin.login.store'), ['email' => 'baru@unpam.test', 'password' => self::TEMPORARY_PASSWORD])
            ->assertRedirect(route('admin.dashboard'));

        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.password.edit'));
    }
}
```

- [ ] **Step 7: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=AdminPasswordChangeTest
```
Expected: FAIL — `Target class [password.changed] does not exist` (alias belum ada) dan `Route [admin.password.edit] not defined`.

- [ ] **Step 8: Middleware, alias, dan route**

`app/Http/Middleware/EnsurePasswordChanged.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Alias `password.changed` (11 §7.3): staf dengan password sementara hanya boleh membuka halaman ganti
 * password dan keluar. Daftar pengecualian bersifat pertahanan berlapis; route tersebut juga berada
 * di luar grup G-ADMIN penuh sehingga tidak menimbulkan pengalihan berulang (00 I.4).
 */
class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->must_change_password
            && ! $request->routeIs('admin.password.edit', 'admin.password.update', 'admin.logout')) {
            if ($request->expectsJson()) {
                abort(403, 'Ganti password sementara terlebih dahulu.');
            }

            return redirect()->route('admin.password.edit')
                ->with('warning', 'Anda harus mengganti password sementara sebelum melanjutkan.');
        }

        return $next($request);
    }
}
```

Tulis ulang `routes/admin.php` (isi akhir setelah Task 15):

```php
<?php

use App\Http\Controllers\Admin\DashboardController;
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
});
```

Tulis ulang `bootstrap/app.php` (isi setelah Task 15; hanya alias dan impor `EnsurePasswordChanged` yang bertambah):

```php
<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureAccountType;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Models\User;
use App\Support\SafeRedirect;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Grup pasien (routes/patient.php) ditambahkan pada fase 5.

            // Parameter admin berbasis id harus numerik (11 §1 aturan 2). Tidak memakai Route::pattern global
            // karena nama {doctor}, {clinic}, dst. juga dipakai route publik berbasis slug.
            Route::middleware('web')
                ->prefix('admin')
                ->name('admin.')
                ->where(array_fill_keys([
                    'doctor', 'specialty', 'clinic', 'schedule', 'exception', 'session', 'facility',
                    'roomCategory', 'patient', 'appointment', 'page', 'banner', 'article',
                    'articleCategory', 'gallery', 'faq', 'insurancePartner', 'user', 'role',
                    'auditLog', 'message', 'media',
                ], '[0-9]+'))
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'account' => EnsureAccountType::class,
            'active' => EnsureAccountIsActive::class,
            'password.changed' => EnsurePasswordChanged::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);

        // Tamu yang membuka halaman ber-`auth`: /admin* ⇒ login admin; selain itu ⇒ login pasien.
        // Cadangan F1-R2: `login` belum ada sampai fase 5, jadi arahkan ke beranda.
        $middleware->redirectGuestsTo(function (Request $request): string {
            if ($request->is('admin', 'admin/*')) {
                return route('admin.login');
            }

            return Route::has('login') ? route('login') : route('home');
        });

        // Pengguna login yang membuka halaman ber-`guest`: arahkan sesuai tipe akun (cadangan F1-R2).
        $middleware->redirectUsersTo(function (Request $request): string {
            $user = $request->user();

            if ($user instanceof User && $user->isStaff()) {
                return route('admin.dashboard');
            }

            return Route::has('patient.dashboard') ? route('patient.dashboard') : route('home');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['password', 'password_confirmation', 'current_password', 'bpjs_number', 'payment_reference']);
        $exceptions->shouldRenderJsonWhen(fn (Request $request, Throwable $e): bool => $request->expectsJson()
            || $request->routeIs('admin.dashboard.chart', 'admin.media.*'));

        // 403 / 419 / 429 berbahasa Indonesia (11 §11.3 + F1-R6). AuthorizationException sudah menjadi 403 dan
        // TokenMismatchException menjadi 419 sebelum callback ini dipanggil; ThrottleRequestsException adalah 429.
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            $status = $e->getStatusCode();

            if ($status === 403) {
                $message = $e->getMessage();
                // Pesan bawaan framework/Spatie berbahasa Inggris tidak ditampilkan (ERR-02: tanpa detail izin).
                $detail = ($e instanceof UnauthorizedException || in_array($message, ['', 'This action is unauthorized.', 'Forbidden'], true))
                    ? null
                    : $message;

                return $request->expectsJson()
                    ? response()->json(['message' => $detail ?? 'Anda tidak memiliki izin untuk melakukan tindakan ini.'], 403)
                    : response()->view('errors.403', ['detail' => $detail], 403);
            }

            if ($status === 419) {
                if ($request->expectsJson()) {
                    return response()->json(['message' => 'Sesi Anda telah berakhir. Muat ulang halaman.'], 419);
                }

                return redirect()->to(SafeRedirect::back($request))
                    ->withInput($request->except(['password', 'password_confirmation', 'current_password', 'payment_reference', 'bpjs_number']))
                    ->with('error', 'Sesi formulir Anda telah berakhir. Silakan periksa isian Anda lalu kirim ulang.');
            }

            if ($status === 429) {
                $retry = (int) ($e->getHeaders()['Retry-After'] ?? 60);
                $text = "Terlalu banyak percobaan. Silakan coba lagi dalam {$retry} detik.";

                if ($request->expectsJson()) {
                    return response()->json(['message' => $text], 429, $e->getHeaders());
                }

                if ($request->isMethod('POST')) {
                    return redirect()->to(SafeRedirect::back($request))
                        ->withInput($request->except(['password', 'password_confirmation']))
                        ->with('error', $text)
                        ->header('Retry-After', (string) $retry);
                }

                return response()->view('errors.429', ['retryAfter' => $retry], 429, $e->getHeaders());
            }

            return null; // 404/500/503 ⇒ view errors::{status}
        });
    })->create();
```

- [ ] **Step 9: Jalankan tes — harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="AdminPasswordChangeTest|AdminRouteMiddlewareTest|AdminLayoutTest|AdminAreaAccessTest"
```
Expected: PASS. Catatan: `AdminRouteMiddlewareTest::test_every_admin_route…` masih hijau karena `admin.password.*` dikecualikan; controller `AdminPasswordController` belum dibuat tetapi tidak ada tes yang menjalankannya pada siklus ini.

- [ ] **Step 10: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint --test
git add app/Http/Middleware/EnsurePasswordChanged.php routes/admin.php bootstrap/app.php tests/Feature/Auth/AdminPasswordChangeTest.php
git commit -m "feat(fase-1): middleware password.changed dan kerangka route ganti password"
```

---

#### Siklus C — Halaman dan aksi ganti password (V-08, SEK-01, audit)

- [ ] **Step 11: Tambah tes ganti password ke `AdminPasswordChangeTest` (gagal dulu)**

Ganti blok import di bagian atas `AdminPasswordChangeTest` dengan versi final berikut (urutan alfabet, sesuai pint):

```php
use App\Enums\ActorType;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;
```

Tambahkan konstanta `private const NEW_PASSWORD = 'PasswordBaru123';` di bawah `TEMPORARY_PASSWORD`, lalu tempel method berikut di akhir kelas:

```php

    // --- halaman ganti password --------------------------------------------------------------

    /** @param  array<string, string>  $data */
    private function putPassword(array $data): TestResponse
    {
        return $this->from(route('admin.password.edit'))->put(route('admin.password.update'), $data);
    }

    /** @return array<string, string> */
    private function validPayload(): array
    {
        return [
            'current_password' => self::TEMPORARY_PASSWORD,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ];
    }

    public function test_edit_page_is_reachable_while_forced_and_explains_why(): void
    {
        $response = $this->actingAs($this->forcedStaff())->get(route('admin.password.edit'));

        $response->assertOk()
            ->assertSee('Ganti Password')
            ->assertSee('Anda harus mengganti password sementara sebelum melanjutkan.')
            ->assertSee('action="'.route('admin.password.update').'"', false)
            ->assertSee('name="_method" value="PUT"', false)
            ->assertSee('name="current_password"', false)
            ->assertSee('name="password_confirmation"', false)
            ->assertSee('<meta name="robots" content="noindex,nofollow">', false)
            ->assertDontSee('Kembali ke dasbor');
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public function test_edit_page_for_a_regular_change_offers_a_link_back_to_the_dashboard(): void
    {
        $this->actingAs($this->createStaff('content-admin'))->get(route('admin.password.edit'))
            ->assertOk()
            ->assertSee('Kembali ke dasbor')
            ->assertDontSee('Anda harus mengganti password sementara');
    }

    public function test_forced_staff_can_change_the_password_and_then_reach_the_dashboard(): void
    {
        $staff = $this->forcedStaff();

        $this->actingAs($staff);
        $this->putPassword($this->validPayload())
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('success', 'Password berhasil diganti.');

        $fresh = $staff->fresh();
        $this->assertFalse($fresh->must_change_password);
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $fresh->password));
        $this->assertFalse(Hash::check(self::TEMPORARY_PASSWORD, $fresh->password));

        $this->get('/admin')->assertOk()->assertSee('Selamat datang');
        $this->get('/admin/_uji-lain')->assertOk();
    }

    public function test_new_password_is_stored_as_a_bcrypt_hash_and_never_appears_in_the_audit_log(): void
    {
        $staff = $this->forcedStaff();

        $this->actingAs($staff);
        $this->putPassword($this->validPayload())->assertRedirect(route('admin.dashboard'));

        $stored = DB::table('users')->where('id', $staff->id)->value('password');
        $this->assertStringStartsWith('$2y$', $stored);
        $this->assertNotSame(self::NEW_PASSWORD, $stored);

        foreach ([self::NEW_PASSWORD, self::TEMPORARY_PASSWORD] as $secret) {
            $this->assertSame(0, DB::table('audit_logs')
                ->where('old_values', 'like', "%{$secret}%")
                ->orWhere('new_values', 'like', "%{$secret}%")
                ->orWhere('description', 'like', "%{$secret}%")
                ->count(), 'Password asli tidak boleh ada di audit_logs.');
        }
    }

    public function test_password_change_is_audited_with_the_previous_flag(): void
    {
        $forced = $this->forcedStaff();
        $this->actingAs($forced);
        $this->putPassword($this->validPayload())->assertRedirect(route('admin.dashboard'));

        $log = AuditLog::query()->where('action', 'auth.password_changed')->sole();
        $this->assertSame(ActorType::Staff, $log->actor_type);
        $this->assertEquals($forced->id, $log->user_id);
        $this->assertEquals($forced->id, $log->auditable_id);
        $this->assertSame(['must_change_password' => true], $log->old_values);
        $this->assertSame(['must_change_password' => false], $log->new_values);

        $regular = $this->createStaff('registration-officer', ['password' => self::TEMPORARY_PASSWORD]);
        $this->actingAs($regular);
        $this->putPassword($this->validPayload())->assertRedirect(route('admin.dashboard'));

        $second = AuditLog::query()->where('action', 'auth.password_changed')->latest('id')->first();
        $this->assertSame(['must_change_password' => false], $second->old_values);
    }

    public function test_session_id_is_regenerated_after_the_password_change(): void
    {
        $this->actingAs($this->forcedStaff());
        $this->get(route('admin.password.edit'));
        $before = session()->getId();

        $this->putPassword($this->validPayload())->assertRedirect(route('admin.dashboard'));

        $this->assertNotSame($before, session()->getId());
    }

    /**
     * @return array<string, array{array<string, string>, string, string}>
     */
    public static function invalidPayloads(): array
    {
        $ok = ['current_password' => self::TEMPORARY_PASSWORD, 'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD];

        return [
            'password saat ini salah' => [['current_password' => 'SalahSekali1'] + $ok, 'current_password', 'Password saat ini tidak sesuai.'],
            'password saat ini kosong' => [['current_password' => ''] + $ok, 'current_password', 'Password saat ini wajib diisi.'],
            'sama dengan password lama' => [['password' => self::TEMPORARY_PASSWORD, 'password_confirmation' => self::TEMPORARY_PASSWORD] + $ok, 'password', 'Password baru harus berbeda dari password saat ini.'],
            'tanpa angka' => [['password' => 'Abcdefghij', 'password_confirmation' => 'Abcdefghij'] + $ok, 'password', 'Password harus mengandung angka.'],
            'tanpa huruf' => [['password' => '1234567890', 'password_confirmation' => '1234567890'] + $ok, 'password', 'Password harus mengandung huruf.'],
            'terlalu pendek' => [['password' => 'Ab1', 'password_confirmation' => 'Ab1'] + $ok, 'password', 'Password minimal 8 karakter.'],
            'terlalu panjang' => [['password' => str_repeat('Aa1', 21).'AB', 'password_confirmation' => str_repeat('Aa1', 21).'AB'] + $ok, 'password', 'Password maksimal 64 karakter.'],
            'konfirmasi tidak cocok' => [['password_confirmation' => 'Lain12345'] + $ok, 'password', 'Konfirmasi password tidak cocok.'],
            'password baru kosong' => [['password' => ''] + $ok, 'password', 'Password baru wajib diisi.'],
            'konfirmasi kosong' => [['password_confirmation' => ''] + $ok, 'password_confirmation', 'Konfirmasi password wajib diisi.'],
        ];
    }

    /** @param  array<string, string>  $payload */
    #[DataProvider('invalidPayloads')]
    public function test_invalid_input_is_rejected_with_the_documented_messages_and_changes_nothing(array $payload, string $field, string $message): void
    {
        $staff = $this->forcedStaff();
        $originalHash = $staff->password;

        $this->actingAs($staff);
        $this->putPassword($payload)
            ->assertRedirect(route('admin.password.edit'))
            ->assertSessionHasErrors([$field => $message]);

        $fresh = $staff->fresh();
        $this->assertSame($originalHash, $fresh->password);
        $this->assertTrue($fresh->must_change_password);
        $this->assertSame(0, AuditLog::query()->where('action', 'auth.password_changed')->count());
    }

    public function test_password_fields_are_never_flashed_back_into_the_form(): void
    {
        $this->actingAs($this->forcedStaff());
        $this->putPassword(['current_password' => 'SalahSekali1', 'password' => 'Ab1', 'password_confirmation' => 'Ab1']);

        $this->assertNull(session('_old_input.current_password'));
        $this->assertNull(session('_old_input.password'));
        $this->assertNull(session('_old_input.password_confirmation'));
    }

    public function test_change_password_routes_reject_guests_patients_and_deactivated_staff(): void
    {
        $this->get(route('admin.password.edit'))->assertRedirect(route('admin.login'));
        $this->put(route('admin.password.update'), $this->validPayload())->assertRedirect(route('admin.login'));

        $this->actingAs(User::factory()->patient()->create())
            ->get(route('admin.password.edit'))
            ->assertForbidden();

        $staff = $this->forcedStaff();
        $this->actingAs($staff)->get(route('admin.password.edit'))->assertOk();
        $staff->forceFill(['is_active' => false])->save();

        $this->get(route('admin.password.edit'))
            ->assertRedirect(route('admin.login'))
            ->assertSessionHas('error', 'Akun Anda dinonaktifkan. Silakan hubungi administrator rumah sakit.');
    }

    public function test_user_menu_links_to_the_change_password_page(): void
    {
        $this->actingAs($this->createStaff('content-admin'))->get('/admin')
            ->assertOk()
            ->assertSee('Ganti Password')
            ->assertSee('href="'.route('admin.password.edit').'"', false);
    }
```

- [ ] **Step 12: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=AdminPasswordChangeTest
```
Expected: FAIL — `Target class [App\Http\Controllers\Auth\AdminPasswordController] does not exist` (500) pada semua tes halaman/aksi ganti password; tes pengalihan Siklus B tetap hijau; `test_user_menu_links…` gagal karena menu belum memuat "Ganti Password".

- [ ] **Step 13: Tulis `UpdatePasswordRequest`**

`app/Http/Requests/Admin/UpdatePasswordRequest.php`:

```php
<?php

namespace App\Http\Requests\Admin;

use App\Rules\PasswordRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isStaff();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
            'password' => [...PasswordRules::default(), 'different:current_password'],
            'password_confirmation' => ['required'],
        ];
    }

    /**
     * Pesan V-07/V-08. Kunci `password.password.letters`/`numbers` dipakai oleh validator bersarang aturan Password.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.required' => 'Password saat ini wajib diisi.',
            'current_password.current_password' => 'Password saat ini tidak sesuai.',
            'password.required' => 'Password baru wajib diisi.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
            'password.max' => 'Password maksimal 64 karakter.',
            'password.min' => 'Password minimal 8 karakter.',
            'password.string' => 'Password tidak valid.',
            'password.password.letters' => 'Password harus mengandung huruf.',
            'password.password.numbers' => 'Password harus mengandung angka.',
            'password.different' => 'Password baru harus berbeda dari password saat ini.',
            'password_confirmation.required' => 'Konfirmasi password wajib diisi.',
        ];
    }
}
```

- [ ] **Step 14: Tulis `AdminPasswordController`**

`app/Http/Controllers/Auth/AdminPasswordController.php`:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePasswordRequest;
use App\Services\Audit\AuditLogger;
use App\Support\Actor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminPasswordController extends Controller
{
    public function edit(Request $request): View
    {
        return view('auth.admin-change-password', [
            'forced' => (bool) $request->user()->must_change_password,
        ]);
    }

    public function update(UpdatePasswordRequest $request, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user();
        $wasForced = (bool) $user->must_change_password;

        // Cast `hashed` pada User meng-hash password; jangan memanggil Hash::make() lagi.
        $user->forceFill([
            'password' => $request->validated('password'),
            'must_change_password' => false,
        ])->save();

        $request->session()->regenerate();

        $audit->log(
            'auth.password_changed',
            $user,
            ['must_change_password' => $wasForced],
            ['must_change_password' => false],
            "Staf {$user->name} mengganti password akunnya.",
            Actor::staff($user),
        );

        return redirect()->route('admin.dashboard')->with('success', 'Password berhasil diganti.');
    }
}
```

- [ ] **Step 15: Tulis view ganti password**

`resources/views/auth/admin-change-password.blade.php`:

```blade
<x-layouts.auth
    title="Ganti Password"
    :subtitle="$forced ? 'Anda harus mengganti password sementara sebelum melanjutkan.' : 'Gunakan password yang kuat dan belum pernah Anda pakai di tempat lain.'">
    <form method="POST" action="{{ route('admin.password.update') }}" novalidate>
        @csrf
        @method('PUT')

        <x-form.input name="current_password" label="Password saat ini" type="password" required autocomplete="current-password" />
        <x-form.input name="password" label="Password baru" type="password" required autocomplete="new-password" help="Minimal 8 karakter, berisi huruf dan angka." />
        <x-form.input name="password_confirmation" label="Konfirmasi password baru" type="password" required autocomplete="new-password" />

        <x-loading-button class="w-100 mt-2" loading-text="Menyimpan…">Simpan Password</x-loading-button>
    </form>

    <x-slot:footer>
        @unless ($forced)
            <p class="mb-1"><a href="{{ route('admin.dashboard') }}">Kembali ke dasbor</a></p>
        @endunless
        <form method="POST" action="{{ route('admin.logout') }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-link p-0 align-baseline">Keluar</button>
        </form>
    </x-slot:footer>
</x-layouts.auth>
```

- [ ] **Step 16: Tambahkan menu "Ganti Password" ke layout admin**

Di `resources/views/components/layouts/admin.blade.php` ganti baris pemisah dropdown (satu-satunya kemunculan):

```blade
                            <li><hr class="dropdown-divider"></li>
```

dengan:

```blade
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="{{ route('admin.password.edit') }}">
                                    <i class="bi bi-key" aria-hidden="true"></i> Ganti Password
                                </a>
                            </li>
```

- [ ] **Step 17: Jalankan tes — harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="AdminPasswordChangeTest|AdminLayoutTest|AdminLoginTest"
```
Expected: PASS (AKS-09 lengkap, V-08, SEK-01 bagian ganti password, AUD-01 bagian ganti password). Bila pesan `password.password.letters`/`numbers` tidak tampil (muncul teks bawaan "Kolom password harus memuat setidaknya satu huruf."), periksa kunci pada `messages()` terhadap `Validator::getFromLocalArray()`; kunci alternatif yang valid adalah `password.letters` / `password.numbers`.

- [ ] **Step 18: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint --test
git add app/Http/Requests/Admin/UpdatePasswordRequest.php app/Http/Controllers/Auth/AdminPasswordController.php resources/views/auth/admin-change-password.blade.php resources/views/components/layouts/admin.blade.php tests/Feature/Auth/AdminPasswordChangeTest.php
git commit -m "feat(fase-1): halaman dan aksi ganti password staf dengan audit tanpa password"
```

---

#### Siklus D — Penjaga KNS-02 ikut memeriksa `password.changed`

- [ ] **Step 19: Perketat penjaga dan tambah tes route ganti password (gagal dulu)**

Di `tests/Feature/Access/AdminRouteMiddlewareTest.php` ganti:

```php
    private const REQUIRED = ['auth', 'account:staff', 'active'];
```

dengan:

```php
    private const REQUIRED = ['auth', 'account:staff', 'active', 'password.changed'];
```

dan pada tes `test_the_guard_detects_an_unprotected_admin_route` ganti baris route kedua dengan versi yang sudah memuat `password.changed` (agar tetap hanya kekurangan `permission:*`):

```php
        Route::middleware(['web', 'auth', 'account:staff', 'active', 'password.changed'])->get('/admin/_uji-tanpa-izin', fn () => 'x')->name('admin.uji.tanpa-izin');
```

Tambahkan juga method berikut di akhir kelas `AdminPasswordChangeTest` (impor tidak berubah):

```php

    public function test_password_routes_are_guarded_without_password_changed_and_the_dashboard_with_it(): void
    {
        $routes = Route::getRoutes();

        foreach (['admin.password.edit', 'admin.password.update'] as $name) {
            $middleware = $routes->getByName($name)->gatherMiddleware();

            foreach (['auth', 'account:staff', 'active'] as $required) {
                $this->assertContains($required, $middleware, "{$name} harus memakai {$required}.");
            }
            $this->assertNotContains('password.changed', $middleware, "{$name} tidak boleh memakai password.changed (pengalihan berulang).");
        }

        $this->assertNotContains('password.changed', $routes->getByName('admin.logout')->gatherMiddleware());
        $this->assertContains('password.changed', $routes->getByName('admin.dashboard')->gatherMiddleware());
    }
```

- [ ] **Step 20: Jalankan tes**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="AdminRouteMiddlewareTest|AdminPasswordChangeTest"
```
Expected: PASS (penjaga hijau karena route dasbor sudah memakai `password.changed`). Untuk membuktikan penjaga bekerja: hapus sementara `'password.changed'` dari grup G-ADMIN di `routes/admin.php` ⇒ `test_every_admin_route_has_the_account_active_and_permission_middleware` harus gagal dengan `admin.dashboard: tanpa middleware password.changed`; kembalikan setelah dicoba.

- [ ] **Step 21: Seluruh suite, pint, dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test
& $PHP vendor\bin\pint --test
git add tests/Feature/Access/AdminRouteMiddlewareTest.php tests/Feature/Auth/AdminPasswordChangeTest.php
git commit -m "test(fase-1): penjaga KNS-02 mewajibkan password.changed dan route ganti password tanpa middleware itu"
```
Expected: seluruh suite hijau.

---

### Task 16: Header keamanan global (`SecurityHeaders`)

**Files:**
- Create: `app/Http/Middleware/SecurityHeaders.php`
- Modify: `bootstrap/app.php` (`$middleware->append(SecurityHeaders::class)`)
- Test: `tests/Feature/Security/SecurityHeadersTest.php`

**Interfaces:**
- Consumes: layout dan halaman galat Task 13–14 (untuk pemeriksaan HTML tanpa atribut event inline).
- Produces: `App\Http\Middleware\SecurityHeaders::handle(Request, Closure): Response` — tabel 11 §7.4 persis: empat header selalu (`X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()`); `Cache-Control: no-store, private` untuk path `admin`, `admin/*`, `pasien`, `pasien/*`, `masuk`, `daftar`, `lupa-password`, `reset-password/*` **kecuali** respons sudah memuat direktif `no-store`, `max-age`, atau `s-maxage`; `Content-Security-Policy` (konstanta publik `SecurityHeaders::CSP`) hanya bila `app()->isProduction()`; `Strict-Transport-Security: max-age=31536000; includeSubDomains` hanya produksi **dan** `$request->isSecure()`.

**Catatan teknis (diverifikasi pada source):**
- Lingkungan "produksi" di tes disimulasikan dengan `$this->app['env'] = 'production'` (container `ArrayAccess`; `Application::isProduction()` membaca `$this['env']`). `Model::shouldBeStrict` sudah dihitung saat boot sehingga tidak ikut berubah.
- `Symfony\Component\HttpFoundation\ResponseHeaderBag` mengurutkan direktif `Cache-Control` secara alfabet dan menyediakan `hasCacheControlDirective()`; tes memakai `assertStringContainsString('no-store', …)` agar tidak bergantung pada urutan.
- Exception yang dirender di dalam `Illuminate\Routing\Pipeline` tetap melewati middleware global, sehingga halaman 404/403/500 juga mendapat header.
- `$this->get('https://localhost/')` membuat request ber-`HTTPS=on` (`Request::create`), sehingga `isSecure()` bernilai true.

---

#### Siklus A — Empat header dasar dan `Cache-Control` halaman sensitif

- [ ] **Step 1: Tulis `SecurityHeadersTest` bagian dasar (gagal dulu)**

Buat `tests/Feature/Security/SecurityHeadersTest.php`:

```php
<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    private const PERMISSIONS_POLICY = 'camera=(), microphone=(), geolocation=(), payment=(), usb=()';

    // --- empat header dasar (SEK-07, lingkungan testing) -------------------------------------

    public function test_four_headers_are_always_present_and_csp_and_hsts_are_absent_outside_production(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', self::PERMISSIONS_POLICY)
            ->assertHeaderMissing('Content-Security-Policy')
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_headers_are_also_added_to_error_and_redirect_responses(): void
    {
        foreach (['/tidak-ada' => 404, '/admin' => 302] as $uri => $status) {
            $this->get($uri)
                ->assertStatus($status)
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
                ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
                ->assertHeader('Permissions-Policy', self::PERMISSIONS_POLICY);
        }
    }

    // --- Cache-Control halaman sensitif ------------------------------------------------------

    /**
     * @return array<string, array{string}>
     */
    public static function sensitivePaths(): array
    {
        return [
            'login admin' => ['/admin/login'],
            'dasbor admin (redirect)' => ['/admin'],
            'subpath admin' => ['/admin/apa-saja'],
            'portal pasien' => ['/pasien'],
            'subpath pasien' => ['/pasien/pendaftaran/baru'],
            'login pasien' => ['/masuk'],
            'registrasi' => ['/daftar'],
            'lupa password' => ['/lupa-password'],
            'reset password' => ['/reset-password/abc123'],
        ];
    }

    #[DataProvider('sensitivePaths')]
    public function test_sensitive_paths_are_never_stored_by_caches(string $uri): void
    {
        $this->assertStringContainsString('no-store', (string) $this->get($uri)->headers->get('Cache-Control'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function publicPaths(): array
    {
        return [
            'beranda' => ['/'],
            'halaman publik lain' => ['/dokter'],
            'awalan mirip admin' => ['/administrasi'],
            'awalan mirip pasien' => ['/pasienx'],
        ];
    }

    #[DataProvider('publicPaths')]
    public function test_public_paths_are_not_marked_no_store(string $uri): void
    {
        $this->assertStringNotContainsString('no-store', (string) $this->get($uri)->headers->get('Cache-Control'));
    }

    public function test_explicit_cache_directives_set_by_a_route_are_preserved(): void
    {
        Route::middleware('web')->get('/admin/_uji-cache-umur', fn () => response('x')->header('Cache-Control', 'public, max-age=60'));
        Route::middleware('web')->get('/admin/_uji-cache-tegas', fn () => response('x')->header('Cache-Control', 'no-cache, no-store, must-revalidate'));

        $aged = (string) $this->get('/admin/_uji-cache-umur')->headers->get('Cache-Control');
        $this->assertStringContainsString('max-age=60', $aged);
        $this->assertStringNotContainsString('no-store', $aged);

        $this->assertStringContainsString('must-revalidate', (string) $this->get('/admin/_uji-cache-tegas')->headers->get('Cache-Control'));
    }
}
```

- [ ] **Step 2: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=SecurityHeadersTest
```
Expected: FAIL — `Header [X-Content-Type-Options] not present on response.` dan `Cache-Control` halaman sensitif tidak memuat `no-store` (middleware belum ada).

- [ ] **Step 3: Tulis `SecurityHeaders` (versi dasar) dan daftarkan di `bootstrap/app.php`**

`app/Http/Middleware/SecurityHeaders.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan global (00 J.3, 11 §7.4, 12 §5.4).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');

        if ($this->isSensitivePath($request) && ! $this->hasExplicitCaching($response)) {
            $headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }

    /** Halaman berisi data pribadi/token tidak boleh disimpan cache bersama atau tombol Back. */
    private function isSensitivePath(Request $request): bool
    {
        return $request->is(
            'admin', 'admin/*',
            'pasien', 'pasien/*',
            'masuk', 'daftar', 'lupa-password', 'reset-password/*',
        );
    }

    /** Respons yang sudah menetapkan kebijakan cache sendiri (mis. PDF) tidak ditimpa. */
    private function hasExplicitCaching(Response $response): bool
    {
        $headers = $response->headers;

        return $headers->hasCacheControlDirective('no-store')
            || $headers->hasCacheControlDirective('max-age')
            || $headers->hasCacheControlDirective('s-maxage');
    }
}
```

Di `bootstrap/app.php` lakukan dua perubahan. Tambahkan impor (urut alfabet, setelah `EnsurePasswordChanged`):

```php
use App\Http\Middleware\SecurityHeaders;
```

dan ganti bagian

```php
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
```

dengan:

```php
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);

        // Header keamanan global (00 J.3, 11 §7.4).
        $middleware->append(SecurityHeaders::class);
```

- [ ] **Step 4: Jalankan tes — harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=SecurityHeadersTest
```
Expected: PASS (empat header, `Cache-Control: no-store` di `/admin/login` tetapi tidak di `/`, direktif eksplisit dipertahankan).

- [ ] **Step 5: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint --test
git add app/Http/Middleware/SecurityHeaders.php bootstrap/app.php tests/Feature/Security/SecurityHeadersTest.php
git commit -m "feat(fase-1): middleware SecurityHeaders global dengan empat header dan Cache-Control halaman sensitif"
```

---

#### Siklus B — CSP dan HSTS produksi, HTML tanpa atribut event inline

- [ ] **Step 6: Tambah tes produksi dan tes HTML ke `SecurityHeadersTest` (gagal dulu)**

Tambahkan konstanta berikut di bawah `PERMISSIONS_POLICY`:

```php
    private const CSP = "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-src https://www.google.com https://maps.google.com; frame-ancestors 'self'; base-uri 'self'; form-action 'self'; object-src 'none'";
```

Lalu tempel method berikut di akhir kelas:

```php

    // --- produksi (SEK-07) -------------------------------------------------------------------

    public function test_production_adds_the_exact_csp_with_google_maps_frame_source(): void
    {
        $this->app['env'] = 'production';

        $response = $this->get('/')->assertOk();

        $this->assertSame(self::CSP, $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('frame-src https://www.google.com https://maps.google.com', $response->headers->get('Content-Security-Policy'));
        $response->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', self::PERMISSIONS_POLICY);
    }

    public function test_csp_is_also_sent_on_error_pages_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->get('/tidak-ada')->assertNotFound()->assertHeader('Content-Security-Policy', self::CSP);
    }

    public function test_hsts_is_sent_only_in_production_over_https(): void
    {
        $this->app['env'] = 'production';

        $this->get('http://localhost/')->assertHeaderMissing('Strict-Transport-Security');
        $this->get('https://localhost/')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_hsts_is_not_sent_over_https_outside_production(): void
    {
        $this->get('https://localhost/')
            ->assertOk()
            ->assertHeaderMissing('Strict-Transport-Security')
            ->assertHeaderMissing('Content-Security-Policy');
    }

    // --- HTML kompatibel dengan CSP ketat ----------------------------------------------------

    /**
     * @return array<string, array{string, int}>
     */
    public static function htmlPages(): array
    {
        return [
            'beranda' => ['/', 200],
            'login admin' => ['/admin/login', 200],
            'halaman 404' => ['/tidak-ada', 404],
        ];
    }

    #[DataProvider('htmlPages')]
    public function test_rendered_html_has_no_inline_event_handler_attributes_or_javascript_urls(string $uri, int $status): void
    {
        $html = (string) $this->get($uri)->assertStatus($status)->getContent();

        $this->assertSame(0, preg_match('/\son[a-z]+\s*=/i', $html), "Atribut event inline ditemukan di {$uri}.");
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('<script>', $html, 'Skrip inline (tanpa src) diblokir CSP produksi.');
    }
```

- [ ] **Step 7: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=SecurityHeadersTest
```
Expected: FAIL — `test_production_adds_the_exact_csp…`, `test_csp_is_also_sent…`, `test_hsts_is_sent_only…` (`Header [Content-Security-Policy]`/`[Strict-Transport-Security] not present`); tes HTML dan `test_hsts_is_not_sent_over_https_outside_production` sudah hijau.

- [ ] **Step 8: Tambahkan CSP dan HSTS produksi ke `SecurityHeaders`**

Ganti seluruh `app/Http/Middleware/SecurityHeaders.php` (isi akhir Task 16):

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan global (00 J.3, 11 §7.4, 12 §5.4).
 *
 * `style-src 'unsafe-inline'` dipertahankan karena Bootstrap, Chart.js, dan Quill menulis atribut `style`
 * (11 §7.4 [ASUMSI]); skrip inline dan atribut event inline dilarang oleh `script-src 'self'`, sehingga
 * fallback gambar memakai `data-fallback` + listener terdelegasi di app.js (00 K).
 */
class SecurityHeaders
{
    public const CSP = "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-src https://www.google.com https://maps.google.com; frame-ancestors 'self'; base-uri 'self'; form-action 'self'; object-src 'none'";

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');

        if ($this->isSensitivePath($request) && ! $this->hasExplicitCaching($response)) {
            $headers->set('Cache-Control', 'no-store, private');
        }

        // CSP hanya di produksi: di lokal dinonaktifkan agar dev server Vite (HMR) berjalan (00 B.2).
        if (app()->isProduction()) {
            $headers->set('Content-Security-Policy', self::CSP);

            if ($request->isSecure()) {
                $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
            }
        }

        return $response;
    }

    /** Halaman berisi data pribadi/token tidak boleh disimpan cache bersama atau tombol Back. */
    private function isSensitivePath(Request $request): bool
    {
        return $request->is(
            'admin', 'admin/*',
            'pasien', 'pasien/*',
            'masuk', 'daftar', 'lupa-password', 'reset-password/*',
        );
    }

    /** Respons yang sudah menetapkan kebijakan cache sendiri (mis. PDF) tidak ditimpa. */
    private function hasExplicitCaching(Response $response): bool
    {
        $headers = $response->headers;

        return $headers->hasCacheControlDirective('no-store')
            || $headers->hasCacheControlDirective('max-age')
            || $headers->hasCacheControlDirective('s-maxage');
    }
}
```

- [ ] **Step 9: Jalankan tes — harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=SecurityHeadersTest
```
Expected: PASS (SEK-07). Bila `test_rendered_html_has_no_inline…` gagal pada `<script>`, cari tag `<script>` tanpa `src` pada layout/komponen dan pindahkan datanya ke `<script type="application/json">` (12 §5.4) — jangan melonggarkan CSP.

- [ ] **Step 10: Seluruh suite, pint, dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test
& $PHP vendor\bin\pint --test
git add app/Http/Middleware/SecurityHeaders.php tests/Feature/Security/SecurityHeadersTest.php
git commit -m "feat(fase-1): CSP produksi dengan frame-src Google Maps, HSTS, dan tes HTML tanpa event inline"
```
Expected: seluruh suite hijau.

---

#### Referensi — isi akhir `bootstrap/app.php` setelah Task 16

```php
<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureAccountType;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\SecurityHeaders;
use App\Models\User;
use App\Support\SafeRedirect;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Grup pasien (routes/patient.php) ditambahkan pada fase 5.

            // Parameter admin berbasis id harus numerik (11 §1 aturan 2). Tidak memakai Route::pattern global
            // karena nama {doctor}, {clinic}, dst. juga dipakai route publik berbasis slug.
            Route::middleware('web')
                ->prefix('admin')
                ->name('admin.')
                ->where(array_fill_keys([
                    'doctor', 'specialty', 'clinic', 'schedule', 'exception', 'session', 'facility',
                    'roomCategory', 'patient', 'appointment', 'page', 'banner', 'article',
                    'articleCategory', 'gallery', 'faq', 'insurancePartner', 'user', 'role',
                    'auditLog', 'message', 'media',
                ], '[0-9]+'))
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'account' => EnsureAccountType::class,
            'active' => EnsureAccountIsActive::class,
            'password.changed' => EnsurePasswordChanged::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);

        // Header keamanan global (00 J.3, 11 §7.4).
        $middleware->append(SecurityHeaders::class);

        // Tamu yang membuka halaman ber-`auth`: /admin* ⇒ login admin; selain itu ⇒ login pasien.
        // Cadangan F1-R2: `login` belum ada sampai fase 5, jadi arahkan ke beranda.
        $middleware->redirectGuestsTo(function (Request $request): string {
            if ($request->is('admin', 'admin/*')) {
                return route('admin.login');
            }

            return Route::has('login') ? route('login') : route('home');
        });

        // Pengguna login yang membuka halaman ber-`guest`: arahkan sesuai tipe akun (cadangan F1-R2).
        $middleware->redirectUsersTo(function (Request $request): string {
            $user = $request->user();

            if ($user instanceof User && $user->isStaff()) {
                return route('admin.dashboard');
            }

            return Route::has('patient.dashboard') ? route('patient.dashboard') : route('home');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['password', 'password_confirmation', 'current_password', 'bpjs_number', 'payment_reference']);
        $exceptions->shouldRenderJsonWhen(fn (Request $request, Throwable $e): bool => $request->expectsJson()
            || $request->routeIs('admin.dashboard.chart', 'admin.media.*'));

        // 403 / 419 / 429 berbahasa Indonesia (11 §11.3 + F1-R6). AuthorizationException sudah menjadi 403 dan
        // TokenMismatchException menjadi 419 sebelum callback ini dipanggil; ThrottleRequestsException adalah 429.
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            $status = $e->getStatusCode();

            if ($status === 403) {
                $message = $e->getMessage();
                // Pesan bawaan framework/Spatie berbahasa Inggris tidak ditampilkan (ERR-02: tanpa detail izin).
                $detail = ($e instanceof UnauthorizedException || in_array($message, ['', 'This action is unauthorized.', 'Forbidden'], true))
                    ? null
                    : $message;

                return $request->expectsJson()
                    ? response()->json(['message' => $detail ?? 'Anda tidak memiliki izin untuk melakukan tindakan ini.'], 403)
                    : response()->view('errors.403', ['detail' => $detail], 403);
            }

            if ($status === 419) {
                if ($request->expectsJson()) {
                    return response()->json(['message' => 'Sesi Anda telah berakhir. Muat ulang halaman.'], 419);
                }

                return redirect()->to(SafeRedirect::back($request))
                    ->withInput($request->except(['password', 'password_confirmation', 'current_password', 'payment_reference', 'bpjs_number']))
                    ->with('error', 'Sesi formulir Anda telah berakhir. Silakan periksa isian Anda lalu kirim ulang.');
            }

            if ($status === 429) {
                $retry = (int) ($e->getHeaders()['Retry-After'] ?? 60);
                $text = "Terlalu banyak percobaan. Silakan coba lagi dalam {$retry} detik.";

                if ($request->expectsJson()) {
                    return response()->json(['message' => $text], 429, $e->getHeaders());
                }

                if ($request->isMethod('POST')) {
                    return redirect()->to(SafeRedirect::back($request))
                        ->withInput($request->except(['password', 'password_confirmation']))
                        ->with('error', $text)
                        ->header('Retry-After', (string) $retry);
                }

                return response()->view('errors.429', ['retryAfter' => $retry], 429, $e->getHeaders());
            }

            return null; // 404/500/503 ⇒ view errors::{status}
        });
    })->create();
```

Catatan untuk fase berikutnya: fase 5 menambah grup pasien di `then:` (`routes/patient.php`) dan menghapus cadangan F1-R2 (`Route::has('login')`/`Route::has('patient.dashboard')`); fase 6 menambah `dontReport([BookingException::class])` dan handler `BookingException`.

---

### Task 17: `StaffAccountService` — aturan mutlak Super Admin

**Files:**
- Create: `app/Services/Staff/StaffAccountService.php`
- Test: `tests/Feature/Staff/StaffAccountServiceTest.php`

**Interfaces:**
- Consumes: `User` + `UserFactory::staff()/withRole()` (Task 2–3), `RolePermissionSeeder::ROLES` (nama peran sah, Task 3), trait `Tests\Support\InteractsWithStaff` (`seedRoles()`, `createStaff()`, Task 3), `AuditLogger::log(string $action, Model $model, ?array $old, ?array $new, string $description, ?Actor $actor = null)` + `AuditLogger::REDACTED` + `AuditLog` + `Actor` (Task 4), `PasswordRules::default()` (Task 15, hanya dipakai tes).
- Produces: `App\Services\Staff\StaffAccountService` (`final`, constructor `(AuditLogger $audit)`):
  - `create(array $validated, Actor $actor): array` → `[User $staff, string $temporaryPassword]`; `forceFill(account_type=staff, is_active=true, must_change_password=true)`, `assignRole($validated['role'])`, audit `staff.created` (old NULL, new `{"name","email","role"}`, tanpa password).
  - `update(User $staff, array $validated, Actor $actor): User` (kunci `name`, `email`, `phone`, `role`); menolak mengubah peran akun sendiri; menolak bila meninggalkan nol Super Admin aktif; audit `staff.updated` hanya berisi kolom yang berubah, `phone` diganti `AuditLogger::REDACTED` oleh service sendiri (`staff.` bukan awalan PII di `AuditLogger::redact()`); tanpa perubahan ⇒ tanpa baris audit.
  - `changeStatus(User $staff, bool $active, Actor $actor): User`; menolak menonaktifkan diri sendiri; menolak Super Admin aktif terakhir (`lockForUpdate()` dalam `DB::transaction`); nonaktif ⇒ hapus baris `sessions` milik pengguna; audit `staff.deactivated` / `staff.activated` (`{"is_active":…}`); status sama ⇒ tanpa perubahan dan tanpa audit.
  - `resetPassword(User $staff, Actor $actor): string`; menolak akun sendiri; password sementara baru + `must_change_password=true` + hapus sesi + audit `staff.password_reset` (new `{"must_change_password":true}`, tanpa password).
  - `generateTemporaryPassword(): string` (12 karakter huruf + angka, minimal satu huruf dan satu angka, `random_int`, lolos `PasswordRules::default()`).
  - Semua penolakan aturan = `ValidationException` berpesan Indonesia (kunci `role`, `is_active`, `password`); akun bukan `account_type=staff` ⇒ `InvalidArgumentException`.

**Pesan galat (sumber):**

| Kunci | Pesan | Sumber |
|---|---|---|
| `role` | "Peran tidak valid." | 12 V-40 |
| `role` | "Anda tidak dapat mengubah peran akun Anda sendiri." | 12 V-40, BR-154 |
| `role` / `is_active` | "Tidak dapat menonaktifkan atau mengubah Super Admin aktif terakhir. Harus selalu ada minimal satu Super Admin aktif." | 12 V-40, BR-154 |
| `is_active` | "Anda tidak dapat menonaktifkan akun Anda sendiri." | 12 V-40, BR-154 |
| `password` | "Anda tidak dapat mengatur ulang password akun Anda sendiri. Gunakan menu Ganti Password." | **ASUMSI** (12 tidak punya pesan untuk reset akun sendiri; aturan ada di STF-07/11 §6.15) |

**Test IDs:** `STF-03` (aturan; dua Super Admin berurutan), `STF-07`, `STF-01`/`STF-02` (tingkat service), `SEK-01` (bagian staf baru), `AUD-01` (bagian `staff.*`).

**Catatan teknis (diverifikasi dari source):**
- `Model::shouldBeStrict()` melarang *lazy load*: service memakai `$staff->loadMissing('roles')`; tes membaca peran lewat `->roles()->pluck('name')->all()` (query, bukan properti relasi), bukan `->hasRole()` pada `fresh()` tanpa eager load.
- `User::role('super-admin')` adalah scope Spatie `scopeRole` (v6.25) dan melempar `RoleDoesNotExist` bila peran belum ada; service hanya memanggilnya setelah memastikan target memang Super Admin, tes selalu memanggil `seedRoles()` lewat `createStaff()`.
- `PHPUnit\Framework\AssertionFailedError` adalah turunan `RuntimeException`: jangan memanggil `$this->fail()` di dalam `try` yang menangkap `RuntimeException`.
- `AuditLogger` bersifat `final` (tidak dapat di-mock Mockery); kegagalan audit disimulasikan dengan `AuditLog::creating(fn () => throw new RuntimeException(...))` (dispatcher event model dibuat ulang per tes oleh `Model::setEventDispatcher` di `setUp`).

- [ ] **Step 1: Verifikasi prasyarat (cabang dan tugas 3–4)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git branch --show-current
git status --short
& $PHP artisan test --filter="RolePermissionSeederTest|AuditLoggerTest|ActorTest"
```
Expected: `fase-1-fondasi`; `git status` bersih; seluruh tes PASS (membuktikan `RolePermissionSeeder`, `AuditLogger`, `Actor` sudah ada).

**Siklus A — `generateTemporaryPassword()` dan `create()`**

- [ ] **Step 2: Tulis tes yang gagal (tool Write) `tests/Feature/Staff/StaffAccountServiceTest.php`**

```php
<?php

namespace Tests\Feature\Staff;

use App\Enums\AccountType;
use App\Enums\ActorType;
use App\Models\AuditLog;
use App\Models\User;
use App\Rules\PasswordRules;
use App\Services\Staff\StaffAccountService;
use App\Support\Actor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

class StaffAccountServiceTest extends TestCase
{
    use InteractsWithStaff;
    use RefreshDatabase;

    private StaffAccountService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = $this->app->make(StaffAccountService::class);
    }

    public function test_generated_temporary_password_has_12_letters_and_digits_and_passes_password_rules(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $password = $this->service->generateTemporaryPassword();

            $this->assertMatchesRegularExpression('/\A[A-Za-z0-9]{12}\z/', $password);
            $this->assertMatchesRegularExpression('/[A-Za-z]/', $password);
            $this->assertMatchesRegularExpression('/[0-9]/', $password);

            $validator = Validator::make(
                ['password' => $password, 'password_confirmation' => $password],
                ['password' => PasswordRules::default()],
            );

            $this->assertTrue($validator->passes(), "Password sementara {$password} harus lolos PasswordRules.");
        }

        $this->assertNotSame($this->service->generateTemporaryPassword(), $this->service->generateTemporaryPassword());
    }

    public function test_create_makes_active_staff_with_hashed_temporary_password_and_role(): void
    {
        $this->seedRoles();
        $admin = $this->createStaff('super-admin');

        [$staff, $temporaryPassword] = $this->service->create([
            'name' => 'Rina Wulandari',
            'email' => 'rina@unpamhospital.test',
            'phone' => '081234567890',
            'role' => 'content-admin',
        ], Actor::staff($admin));

        $fresh = $staff->fresh();

        $this->assertSame(AccountType::Staff, $fresh->account_type);
        $this->assertTrue($fresh->is_active);
        $this->assertTrue($fresh->must_change_password);
        $this->assertSame('Rina Wulandari', $fresh->name);
        $this->assertSame('081234567890', $fresh->phone);
        $this->assertSame(12, strlen($temporaryPassword));
        $this->assertStringStartsWith('$2y$', $fresh->password);
        $this->assertNotSame($temporaryPassword, $fresh->password);
        $this->assertTrue(Hash::check($temporaryPassword, $fresh->password));
        $this->assertSame(['content-admin'], $fresh->roles()->pluck('name')->all());
    }

    public function test_create_without_phone_stores_null(): void
    {
        $this->seedRoles();

        [$staff] = $this->service->create([
            'name' => 'Tanpa Telepon',
            'email' => 'tanpa.telepon@unpamhospital.test',
            'role' => 'registration-officer',
        ], Actor::system());

        $this->assertNull($staff->fresh()->phone);
    }

    public function test_create_writes_audit_without_the_temporary_password(): void
    {
        $admin = $this->createStaff('super-admin');

        [$staff, $temporaryPassword] = $this->service->create([
            'name' => 'Petugas Baru',
            'email' => 'petugas.baru@unpamhospital.test',
            'phone' => '081200000000',
            'role' => 'registration-officer',
        ], Actor::staff($admin));

        $log = AuditLog::query()->where('action', 'staff.created')->sole();

        $this->assertSame(ActorType::Staff, $log->actor_type);
        $this->assertSame($admin->id, (int) $log->user_id);
        $this->assertSame((new User)->getMorphClass(), $log->auditable_type);
        $this->assertSame($staff->id, (int) $log->auditable_id);
        $this->assertNull($log->old_values);
        $this->assertEquals([
            'name' => 'Petugas Baru',
            'email' => 'petugas.baru@unpamhospital.test',
            'role' => 'registration-officer',
        ], $log->new_values);
        $this->assertStringNotContainsString($temporaryPassword, DB::table('audit_logs')->get()->toJson());
    }

    public function test_create_rejects_unknown_role_without_creating_anything(): void
    {
        $this->seedRoles();

        $this->assertRejected(
            fn () => $this->service->create([
                'name' => 'Peran Salah',
                'email' => 'peran.salah@unpamhospital.test',
                'role' => 'dokter',
            ], Actor::system()),
            'role',
            'Peran tidak valid.',
        );

        $this->assertDatabaseMissing('users', ['email' => 'peran.salah@unpamhospital.test']);
        $this->assertSame(0, AuditLog::query()->where('action', 'staff.created')->count());
    }

    public function test_create_is_rolled_back_when_the_audit_row_cannot_be_written(): void
    {
        $this->seedRoles();
        AuditLog::creating(fn () => throw new RuntimeException('Gagal menulis audit.'));

        $thrown = null;

        try {
            $this->service->create([
                'name' => 'Batal Dibuat',
                'email' => 'batal@unpamhospital.test',
                'role' => 'content-admin',
            ], Actor::system());
        } catch (RuntimeException $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown, 'Galat audit tidak boleh ditelan.');
        $this->assertSame('Gagal menulis audit.', $thrown->getMessage());
        $this->assertDatabaseMissing('users', ['email' => 'batal@unpamhospital.test']);
    }

    // --- Pembantu ---

    private function assertRejected(callable $action, string $field, string $message): void
    {
        try {
            $action();
        } catch (ValidationException $e) {
            $this->assertSame([$field => [$message]], $e->errors());

            return;
        }

        $this->fail('ValidationException tidak dilempar.');
    }
}
```

- [ ] **Step 3: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=StaffAccountServiceTest
```
Expected: FAIL — semua 6 tes ERROR di `setUp` dengan `Illuminate\Contracts\Container\BindingResolutionException: Target class [App\Services\Staff\StaffAccountService] does not exist.`

- [ ] **Step 4: Tulis `app/Services/Staff/StaffAccountService.php` (tool Write; baru bagian create + pembangkit password)**

```php
<?php

declare(strict_types=1);

namespace App\Services\Staff;

use App\Enums\AccountType;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Actor;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Menegakkan aturan mutlak 00 G / 11 §6.15 yang tidak boleh bergantung pada Policy
 * karena Gate::before melewati Policy bagi Super Admin: peran super-admin terkunci,
 * minimal satu Super Admin aktif, dan staf tidak dapat menonaktifkan, mengubah peran,
 * atau mengatur ulang password akunnya sendiri.
 */
final class StaffAccountService
{
    private const SUPER_ADMIN = 'super-admin';

    private const TEMPORARY_PASSWORD_LENGTH = 12;

    // Tanpa karakter yang mudah tertukar (0/O, 1/l/I) agar password sementara mudah dibacakan.
    private const PASSWORD_LETTERS = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz';

    private const PASSWORD_DIGITS = '23456789';

    private const MSG_INVALID_ROLE = 'Peran tidak valid.';

    private const MSG_SELF_ROLE = 'Anda tidak dapat mengubah peran akun Anda sendiri.';

    private const MSG_SELF_DEACTIVATE = 'Anda tidak dapat menonaktifkan akun Anda sendiri.';

    private const MSG_SELF_RESET = 'Anda tidak dapat mengatur ulang password akun Anda sendiri. Gunakan menu Ganti Password.';

    private const MSG_LAST_SUPER_ADMIN = 'Tidak dapat menonaktifkan atau mengubah Super Admin aktif terakhir. Harus selalu ada minimal satu Super Admin aktif.';

    public function __construct(private readonly AuditLogger $audit)
    {
    }

    /**
     * @param  array<string, mixed>  $validated  name, email, phone (opsional), role
     * @return array{0: User, 1: string} [akun staf, password sementara (ditampilkan sekali, tidak disimpan/diaudit)]
     */
    public function create(array $validated, Actor $actor): array
    {
        $role = $this->validRole($validated['role'] ?? null);
        $temporaryPassword = $this->generateTemporaryPassword();

        $staff = DB::transaction(function () use ($validated, $role, $temporaryPassword, $actor): User {
            $phone = $validated['phone'] ?? null;

            $staff = new User;
            $staff->forceFill([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $phone === '' ? null : $phone,
                'password' => $temporaryPassword,
                'account_type' => AccountType::Staff,
                'is_active' => true,
                'must_change_password' => true,
            ])->save();

            $staff->assignRole($role);

            $this->audit->log(
                'staff.created',
                $staff,
                null,
                ['name' => $staff->name, 'email' => $staff->email, 'role' => $role],
                "Akun staf {$staff->name} dibuat dengan peran ".__('roles.'.$role).'.',
                $actor,
            );

            return $staff;
        });

        return [$staff, $temporaryPassword];
    }

    public function generateTemporaryPassword(): string
    {
        $letters = self::PASSWORD_LETTERS;
        $digits = self::PASSWORD_DIGITS;
        $alphabet = $letters.$digits;

        $characters = [
            $letters[random_int(0, strlen($letters) - 1)],
            $digits[random_int(0, strlen($digits) - 1)],
        ];

        while (count($characters) < self::TEMPORARY_PASSWORD_LENGTH) {
            $characters[] = $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        // Fisher–Yates dengan random_int agar posisi huruf/angka wajib tidak dapat ditebak.
        for ($i = count($characters) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$characters[$i], $characters[$j]] = [$characters[$j], $characters[$i]];
        }

        return implode('', $characters);
    }

    // --- Pembantu privat ---

    private function validRole(mixed $role): string
    {
        if (! is_string($role) || ! array_key_exists($role, RolePermissionSeeder::ROLES)) {
            throw ValidationException::withMessages(['role' => self::MSG_INVALID_ROLE]);
        }

        return $role;
    }
}
```

- [ ] **Step 5: Jalankan tes — harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=StaffAccountServiceTest
```
Expected: PASS (6 tes, mode ketat aktif tanpa `LazyLoadingViolationException`).

- [ ] **Step 6: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint --test
git add app/Services/Staff/StaffAccountService.php tests/Feature/Staff/StaffAccountServiceTest.php
git commit -m "feat(fase-1): StaffAccountService membuat akun staf dengan password sementara"
```
Expected: Pint `PASS` (bila gagal, jalankan `& $PHP vendor\bin\pint` untuk merapikan lalu ulangi `--test` sebelum commit).

**Siklus B — `changeStatus()` dan aturan Super Admin terakhir (STF-03, STF-07)**

- [ ] **Step 7: Tambahkan tes status (tool Edit pada `tests/Feature/Staff/StaffAccountServiceTest.php`)**

Edit impor: ganti `use Illuminate\Validation\ValidationException;` menjadi:

```php
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
```

Lalu ganti baris penanda `    // --- Pembantu ---` (satu baris) dengan teks berikut (tes baru di atas penanda, dua pembantu baru di bawahnya; `assertRejected` yang sudah ada tetap di bawah):

```php
    public function test_deactivating_sets_inactive_deletes_only_that_users_sessions_and_audits(): void
    {
        $admin = $this->createStaff('super-admin');
        $target = $this->createStaff('content-admin');
        $other = $this->createStaff('registration-officer');
        $this->insertSession('sesi-target-1', $target->id);
        $this->insertSession('sesi-target-2', $target->id);
        $this->insertSession('sesi-lain', $other->id);

        $result = $this->service->changeStatus($target, false, Actor::staff($admin));

        $this->assertFalse($result->is_active);
        $this->assertFalse($target->fresh()->is_active);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $target->id)->count());
        $this->assertSame(1, DB::table('sessions')->where('user_id', $other->id)->count());

        $log = $this->latestAudit('staff.deactivated');
        $this->assertSame(ActorType::Staff, $log->actor_type);
        $this->assertSame($admin->id, (int) $log->user_id);
        $this->assertSame($target->id, (int) $log->auditable_id);
        $this->assertEquals(['is_active' => true], $log->old_values);
        $this->assertEquals(['is_active' => false], $log->new_values);
    }

    public function test_activating_sets_active_and_audits(): void
    {
        $admin = $this->createStaff('super-admin');
        $target = $this->createStaff('content-admin', ['is_active' => false]);

        $result = $this->service->changeStatus($target, true, Actor::staff($admin));

        $this->assertTrue($result->is_active);
        $this->assertTrue($target->fresh()->is_active);

        $log = $this->latestAudit('staff.activated');
        $this->assertSame($target->id, (int) $log->auditable_id);
        $this->assertEquals(['is_active' => false], $log->old_values);
        $this->assertEquals(['is_active' => true], $log->new_values);
    }

    public function test_changing_to_the_same_status_is_a_no_op_without_audit(): void
    {
        $admin = $this->createStaff('super-admin');
        $target = $this->createStaff('content-admin');

        $this->service->changeStatus($target, true, Actor::staff($admin));

        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_staff_cannot_deactivate_their_own_account_even_as_super_admin(): void
    {
        $admin = $this->createStaff('super-admin');
        $this->createStaff('super-admin');

        $this->assertRejected(
            fn () => $this->service->changeStatus($admin, false, Actor::staff($admin)),
            'is_active',
            'Anda tidak dapat menonaktifkan akun Anda sendiri.',
        );

        $this->assertTrue($admin->fresh()->is_active);
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_with_two_super_admins_the_first_deactivation_succeeds_and_the_second_is_rejected(): void
    {
        $first = $this->createStaff('super-admin');
        $second = $this->createStaff('super-admin');

        $this->service->changeStatus($second, false, Actor::staff($first));
        $this->assertFalse($second->fresh()->is_active);

        $this->assertRejected(
            fn () => $this->service->changeStatus($first, false, Actor::staff($second)),
            'is_active',
            'Tidak dapat menonaktifkan atau mengubah Super Admin aktif terakhir. Harus selalu ada minimal satu Super Admin aktif.',
        );

        $this->assertTrue($first->fresh()->is_active);
        $this->assertSame(1, AuditLog::query()->where('action', 'staff.deactivated')->count());
    }

    public function test_sole_super_admin_cannot_be_deactivated_by_another_staff(): void
    {
        $only = $this->createStaff('super-admin');
        $officer = $this->createStaff('registration-officer');

        $this->assertRejected(
            fn () => $this->service->changeStatus($only, false, Actor::staff($officer)),
            'is_active',
            'Tidak dapat menonaktifkan atau mengubah Super Admin aktif terakhir. Harus selalu ada minimal satu Super Admin aktif.',
        );

        $this->assertTrue($only->fresh()->is_active);
    }

    public function test_last_super_admin_check_runs_inside_a_row_lock(): void
    {
        $first = $this->createStaff('super-admin');
        $second = $this->createStaff('super-admin');

        DB::enableQueryLog();
        $this->service->changeStatus($second, false, Actor::staff($first));
        $queries = collect(DB::getQueryLog())->pluck('query')->map(fn (string $sql): string => strtolower($sql));
        DB::disableQueryLog();

        $this->assertTrue(
            $queries->contains(fn (string $sql): bool => str_contains($sql, 'for update')),
            'Pemeriksaan Super Admin aktif terakhir harus memakai lockForUpdate().',
        );
    }

    public function test_change_status_rejects_patient_accounts(): void
    {
        $patient = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        $this->service->changeStatus($patient, false, Actor::system());
    }

    // --- Pembantu ---

    private function latestAudit(string $action): AuditLog
    {
        return AuditLog::query()->where('action', $action)->latest('id')->firstOrFail();
    }

    private function insertSession(string $id, int $userId): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $userId,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'payload' => base64_encode('payload'),
            'last_activity' => time(),
        ]);
    }
```

- [ ] **Step 8: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=StaffAccountServiceTest
```
Expected: FAIL — 6 tes siklus A tetap lulus; 8 tes siklus B ERROR `Error: Call to undefined method App\Services\Staff\StaffAccountService::changeStatus()`.

- [ ] **Step 9: Implementasi `changeStatus()` + pembantu privat (tool Edit pada `app/Services/Staff/StaffAccountService.php`)**

Edit impor: ganti `use Illuminate\Validation\ValidationException;` menjadi:

```php
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
```

Ganti baris penanda `    // --- Pembantu privat ---` (satu baris) dengan:

```php
    public function changeStatus(User $staff, bool $active, Actor $actor): User
    {
        $this->assertStaff($staff);

        return DB::transaction(function () use ($staff, $active, $actor): User {
            if ($staff->is_active === $active) {
                return $staff;
            }

            if (! $active) {
                if ($this->isSelf($staff, $actor)) {
                    throw ValidationException::withMessages(['is_active' => self::MSG_SELF_DEACTIVATE]);
                }

                if ($this->isSuperAdmin($staff)) {
                    $this->assertNotLastActiveSuperAdmin($staff, 'is_active');
                }
            }

            $staff->forceFill(['is_active' => $active])->save();

            if (! $active) {
                DB::table('sessions')->where('user_id', $staff->getKey())->delete();
            }

            $this->audit->log(
                $active ? 'staff.activated' : 'staff.deactivated',
                $staff,
                ['is_active' => ! $active],
                ['is_active' => $active],
                $active ? "Akun staf {$staff->name} diaktifkan kembali." : "Akun staf {$staff->name} dinonaktifkan.",
                $actor,
            );

            return $staff;
        });
    }

    // --- Pembantu privat ---

    private function assertStaff(User $staff): void
    {
        if ($staff->account_type !== AccountType::Staff) {
            throw new InvalidArgumentException('Hanya akun staf yang dapat dikelola oleh StaffAccountService.');
        }
    }

    private function isSelf(User $staff, Actor $actor): bool
    {
        return $actor->userId !== null && $actor->userId === $staff->getKey();
    }

    private function isSuperAdmin(User $staff): bool
    {
        $staff->loadMissing('roles');

        return $staff->roles->contains('name', self::SUPER_ADMIN);
    }

    /**
     * Harus dipanggil di dalam DB::transaction: baris Super Admin aktif dikunci
     * (lockForUpdate) sehingga dua permintaan serentak tidak dapat sama-sama menghabisinya.
     */
    private function assertNotLastActiveSuperAdmin(User $staff, string $field): void
    {
        $activeIds = User::role(self::SUPER_ADMIN)->where('is_active', true)->lockForUpdate()->pluck('id');

        if ($activeIds->count() <= 1 && $activeIds->contains($staff->getKey())) {
            throw ValidationException::withMessages([$field => self::MSG_LAST_SUPER_ADMIN]);
        }
    }
```

- [ ] **Step 10: Jalankan tes — harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=StaffAccountServiceTest
```
Expected: PASS (14 tes).

- [ ] **Step 11: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint --test
git add app/Services/Staff/StaffAccountService.php tests/Feature/Staff/StaffAccountServiceTest.php
git commit -m "feat(fase-1): StaffAccountService mengubah status akun dengan penjagaan Super Admin aktif terakhir"
```
Expected: `PASS`.

**Siklus C — `update()`, `resetPassword()`, dan penolakan akun pasien**

- [ ] **Step 12: Tambahkan tes ubah data / reset password (tool Edit pada berkas tes)**

Edit impor (tiga penggantian satu baris):
- ganti `use App\Services\Staff\StaffAccountService;` menjadi dua baris `use App\Services\Audit\AuditLogger;` lalu `use App\Services\Staff\StaffAccountService;`
- ganti `use App\Support\Actor;` menjadi `use App\Support\Actor;` lalu `use Closure;`
- ganti `use InvalidArgumentException;` menjadi `use InvalidArgumentException;` lalu `use PHPUnit\Framework\Attributes\DataProvider;`

Lalu ganti baris penanda `    // --- Pembantu ---` (satu baris) dengan teks berikut (tes baru di atas penanda; penanda ditulis ulang di akhir teks):

```php
    public function test_update_changes_profile_and_role_and_audits_with_redacted_phone(): void
    {
        $admin = $this->createStaff('super-admin');
        $target = $this->createStaff('content-admin', [
            'name' => 'Nama Lama',
            'email' => 'lama@unpamhospital.test',
            'phone' => '081111111111',
        ]);

        $updated = $this->service->update($target, [
            'name' => 'Nama Baru',
            'email' => 'baru@unpamhospital.test',
            'phone' => '082222222222',
            'role' => 'registration-officer',
        ], Actor::staff($admin));

        $fresh = $target->fresh();
        $this->assertSame('Nama Baru', $fresh->name);
        $this->assertSame('baru@unpamhospital.test', $fresh->email);
        $this->assertSame('082222222222', $fresh->phone);
        $this->assertSame(['registration-officer'], $fresh->roles()->pluck('name')->all());
        $this->assertSame(['registration-officer'], $updated->roles->pluck('name')->all());

        $log = $this->latestAudit('staff.updated');
        $this->assertSame(ActorType::Staff, $log->actor_type);
        $this->assertSame($target->id, (int) $log->auditable_id);
        $this->assertEquals([
            'name' => 'Nama Lama',
            'email' => 'lama@unpamhospital.test',
            'phone' => AuditLogger::REDACTED,
            'role' => 'content-admin',
        ], $log->old_values);
        $this->assertEquals([
            'name' => 'Nama Baru',
            'email' => 'baru@unpamhospital.test',
            'phone' => AuditLogger::REDACTED,
            'role' => 'registration-officer',
        ], $log->new_values);

        $audits = DB::table('audit_logs')->get()->toJson();
        $this->assertStringNotContainsString('081111111111', $audits);
        $this->assertStringNotContainsString('082222222222', $audits);
    }

    public function test_update_without_any_change_writes_no_audit(): void
    {
        $admin = $this->createStaff('super-admin');
        $target = $this->createStaff('content-admin');

        $this->service->update($target, [
            'name' => $target->name,
            'email' => $target->email,
            'phone' => $target->phone,
            'role' => 'content-admin',
        ], Actor::staff($admin));

        $this->assertSame(0, AuditLog::query()->where('action', 'staff.updated')->count());
    }

    public function test_staff_can_edit_their_own_profile_when_the_role_stays_the_same(): void
    {
        $admin = $this->createStaff('super-admin', ['name' => 'Admin Lama']);

        $this->service->update($admin, [
            'name' => 'Admin Baru',
            'email' => $admin->email,
            'phone' => null,
            'role' => 'super-admin',
        ], Actor::staff($admin));

        $this->assertSame('Admin Baru', $admin->fresh()->name);
        $this->assertSame(['super-admin'], $admin->roles()->pluck('name')->all());
    }

    public function test_staff_cannot_change_their_own_role_even_as_super_admin(): void
    {
        $admin = $this->createStaff('super-admin');
        $this->createStaff('super-admin');

        $this->assertRejected(
            fn () => $this->service->update($admin, [
                'name' => $admin->name,
                'email' => $admin->email,
                'phone' => null,
                'role' => 'content-admin',
            ], Actor::staff($admin)),
            'role',
            'Anda tidak dapat mengubah peran akun Anda sendiri.',
        );

        $this->assertSame(['super-admin'], $admin->roles()->pluck('name')->all());
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_staff_cannot_promote_themselves_to_super_admin(): void
    {
        $staff = $this->createStaff('content-admin');

        $this->assertRejected(
            fn () => $this->service->update($staff, [
                'name' => $staff->name,
                'email' => $staff->email,
                'phone' => null,
                'role' => 'super-admin',
            ], Actor::staff($staff)),
            'role',
            'Anda tidak dapat mengubah peran akun Anda sendiri.',
        );

        $this->assertSame(['content-admin'], $staff->roles()->pluck('name')->all());
    }

    public function test_with_two_super_admins_demoting_the_first_succeeds_and_the_second_is_rejected(): void
    {
        $first = $this->createStaff('super-admin');
        $second = $this->createStaff('super-admin');

        $this->service->update($second, [
            'name' => $second->name,
            'email' => $second->email,
            'phone' => null,
            'role' => 'content-admin',
        ], Actor::staff($first));
        $this->assertSame(['content-admin'], $second->roles()->pluck('name')->all());

        $this->assertRejected(
            fn () => $this->service->update($first, [
                'name' => $first->name,
                'email' => $first->email,
                'phone' => null,
                'role' => 'content-admin',
            ], Actor::staff($second)),
            'role',
            'Tidak dapat menonaktifkan atau mengubah Super Admin aktif terakhir. Harus selalu ada minimal satu Super Admin aktif.',
        );

        $this->assertSame(['super-admin'], $first->roles()->pluck('name')->all());
    }

    public function test_inactive_super_admin_does_not_count_as_an_active_one(): void
    {
        $active = $this->createStaff('super-admin');
        $inactive = $this->createStaff('super-admin', ['is_active' => false]);

        $this->assertRejected(
            fn () => $this->service->update($active, [
                'name' => $active->name,
                'email' => $active->email,
                'phone' => null,
                'role' => 'content-admin',
            ], Actor::staff($inactive)),
            'role',
            'Tidak dapat menonaktifkan atau mengubah Super Admin aktif terakhir. Harus selalu ada minimal satu Super Admin aktif.',
        );

        $this->service->update($inactive, [
            'name' => $inactive->name,
            'email' => $inactive->email,
            'phone' => null,
            'role' => 'content-admin',
        ], Actor::staff($active));

        $this->assertSame(['content-admin'], $inactive->roles()->pluck('name')->all());
    }

    public function test_reset_password_sets_new_temporary_password_forces_change_and_ends_sessions(): void
    {
        $admin = $this->createStaff('super-admin');
        $target = $this->createStaff('registration-officer', ['must_change_password' => false]);
        $other = $this->createStaff('content-admin');
        $oldHash = $target->password;
        $this->insertSession('reset-target-1', $target->id);
        $this->insertSession('reset-target-2', $target->id);
        $this->insertSession('reset-lain', $other->id);

        $temporaryPassword = $this->service->resetPassword($target, Actor::staff($admin));

        $fresh = $target->fresh();
        $this->assertSame(12, strlen($temporaryPassword));
        $this->assertNotSame($oldHash, $fresh->password);
        $this->assertTrue(Hash::check($temporaryPassword, $fresh->password));
        $this->assertTrue($fresh->must_change_password);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $target->id)->count());
        $this->assertSame(1, DB::table('sessions')->where('user_id', $other->id)->count());

        $log = $this->latestAudit('staff.password_reset');
        $this->assertSame($admin->id, (int) $log->user_id);
        $this->assertSame($target->id, (int) $log->auditable_id);
        $this->assertNull($log->old_values);
        $this->assertEquals(['must_change_password' => true], $log->new_values);
        $this->assertStringNotContainsString($temporaryPassword, DB::table('audit_logs')->get()->toJson());
    }

    public function test_staff_cannot_reset_their_own_password(): void
    {
        $admin = $this->createStaff('super-admin');
        $oldHash = $admin->password;

        $this->assertRejected(
            fn () => $this->service->resetPassword($admin, Actor::staff($admin)),
            'password',
            'Anda tidak dapat mengatur ulang password akun Anda sendiri. Gunakan menu Ganti Password.',
        );

        $this->assertSame($oldHash, $admin->fresh()->password);
        $this->assertSame(0, AuditLog::query()->count());
    }

    /** @return array<string, array{Closure}> */
    public static function patientOperations(): array
    {
        return [
            'update' => [fn (StaffAccountService $service, User $user) => $service->update($user, [
                'name' => 'Nama Pasien',
                'email' => 'pasien.ubah@unpamhospital.test',
                'role' => 'content-admin',
            ], Actor::system())],
            'resetPassword' => [fn (StaffAccountService $service, User $user) => $service->resetPassword($user, Actor::system())],
        ];
    }

    #[DataProvider('patientOperations')]
    public function test_update_and_reset_password_reject_patient_accounts(Closure $operation): void
    {
        $this->seedRoles();
        $patient = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        $operation($this->service, $patient);
    }

    // --- Pembantu ---
```

Teks pengganti berakhir dengan penanda yang sama; pembantu `latestAudit`, `insertSession` (Step 7) dan `assertRejected` (Step 2) tetap berada di bawah penanda dan **tidak** ditulis ulang. Setelah penggantian berkas hanya memuat satu `latestAudit`, satu `insertSession`, dan satu `assertRejected`.

- [ ] **Step 13: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=StaffAccountServiceTest
```
Expected: FAIL — 14 tes siklus A/B tetap lulus; 11 tes baru ERROR `Error: Call to undefined method App\Services\Staff\StaffAccountService::update()` / `::resetPassword()`.

- [ ] **Step 14: Implementasi `update()` dan `resetPassword()` (tool Edit pada `app/Services/Staff/StaffAccountService.php`)**

Ganti baris penanda `    // --- Pembantu privat ---` (satu baris) dengan:

```php
    /**
     * @param  array<string, mixed>  $validated  name, email, phone (opsional), role
     */
    public function update(User $staff, array $validated, Actor $actor): User
    {
        $this->assertStaff($staff);
        $newRole = $this->validRole($validated['role'] ?? null);

        return DB::transaction(function () use ($staff, $validated, $newRole, $actor): User {
            $staff->loadMissing('roles');
            $oldRole = $staff->roles->pluck('name')->first();
            $roleChanged = $oldRole !== $newRole;

            if ($roleChanged) {
                if ($this->isSelf($staff, $actor)) {
                    throw ValidationException::withMessages(['role' => self::MSG_SELF_ROLE]);
                }

                if ($oldRole === self::SUPER_ADMIN) {
                    $this->assertNotLastActiveSuperAdmin($staff, 'role');
                }
            }

            $before = [
                'name' => $staff->name,
                'email' => $staff->email,
                'phone' => $staff->phone,
                'role' => $oldRole,
            ];

            $phone = array_key_exists('phone', $validated) ? $validated['phone'] : $staff->phone;

            $staff->fill([
                'name' => $validated['name'] ?? $staff->name,
                'email' => $validated['email'] ?? $staff->email,
                'phone' => $phone === '' ? null : $phone,
            ])->save();

            if ($roleChanged) {
                $staff->syncRoles([$newRole]);
            }

            $after = [
                'name' => $staff->name,
                'email' => $staff->email,
                'phone' => $staff->phone,
                'role' => $newRole,
            ];

            $old = [];
            $new = [];

            foreach ($after as $key => $value) {
                if ($before[$key] !== $value) {
                    $old[$key] = $before[$key];
                    $new[$key] = $value;
                }
            }

            if ($old !== []) {
                // 'staff.*' bukan awalan PII di AuditLogger::redact(), jadi telepon disamarkan di sini.
                if (array_key_exists('phone', $old)) {
                    $old['phone'] = AuditLogger::REDACTED;
                    $new['phone'] = AuditLogger::REDACTED;
                }

                $this->audit->log(
                    'staff.updated',
                    $staff,
                    $old,
                    $new,
                    "Data akun staf {$staff->name} diperbarui.",
                    $actor,
                );
            }

            return $staff->load('roles');
        });
    }

    public function resetPassword(User $staff, Actor $actor): string
    {
        $this->assertStaff($staff);

        if ($this->isSelf($staff, $actor)) {
            throw ValidationException::withMessages(['password' => self::MSG_SELF_RESET]);
        }

        $temporaryPassword = $this->generateTemporaryPassword();

        DB::transaction(function () use ($staff, $temporaryPassword, $actor): void {
            $staff->forceFill([
                'password' => $temporaryPassword,
                'must_change_password' => true,
            ])->save();

            DB::table('sessions')->where('user_id', $staff->getKey())->delete();

            $this->audit->log(
                'staff.password_reset',
                $staff,
                null,
                ['must_change_password' => true],
                "Password akun staf {$staff->name} diatur ulang.",
                $actor,
            );
        });

        return $temporaryPassword;
    }

    // --- Pembantu privat ---
```

- [ ] **Step 15: Jalankan tes — harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=StaffAccountServiceTest
```
Expected: PASS (25 tes: 6 + 8 + 11; satu tes ber-*data provider* dihitung dua kali).

- [ ] **Step 16: Seluruh suite, Pint, commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test
& $PHP vendor\bin\pint --test
git add app/Services/Staff/StaffAccountService.php tests/Feature/Staff/StaffAccountServiceTest.php
git commit -m "feat(fase-1): StaffAccountService mengubah data, peran, dan reset password dengan aturan mutlak Super Admin"
```
Expected: seluruh tes PASS; Pint `PASS`.

---

### Task 18: `app:create-super-admin` dan seeder demo staf

**Files:**
- Create: `app/Console/Commands/CreateSuperAdmin.php`, `database/seeders/DemoSeeder.php`, `database/seeders/DemoUserSeeder.php`
- Modify: `config/app.php` (`demo_password`), `database/seeders/DatabaseSeeder.php` (guard 13 §2.3, tanpa `PageSeeder` — fase 4a), `.env.example` (`DEMO_PASSWORD=`)
- Test: `tests/Feature/Console/CreateSuperAdminTest.php`, `tests/Feature/Seeding/SeederProductionGuardTest.php`, `tests/Feature/Seeding/DemoUserSeederTest.php`

**Interfaces:**
- Consumes: `RolePermissionSeeder`, `SettingsSeeder` (Task 3, 7), `PasswordRules::default()` (Task 15), `AuditLogger` + `Actor::system()` (Task 4), trait `InteractsWithStaff` (Task 3), `User` (Task 2).
- Produces:
  - perintah `app:create-super-admin {--name=} {--email=} {--password=}` persis 11 §10.2: prompt `ask`/`secret` (`Nama lengkap`, `Email`, `Password`, `Konfirmasi password`); validasi email unik + `PasswordRules::default()` **tanpa** `confirmed`; peran `super-admin` wajib ada (bila tidak: "Peran super-admin belum ada. Jalankan: php artisan db:seed --class=RolePermissionSeeder", exit 1); membuat staf `is_active=true`, `must_change_password=true` + `assignRole('super-admin')` dalam satu transaksi + audit `staff.created` (pelaku `Actor::system()`); peringatan bila `--password` dipakai; mode non-interaktif butuh ketiga opsi.
  - `config('app.demo_password')` = `env('DEMO_PASSWORD') ?: 'Demo12345'` (**bukan** `env('DEMO_PASSWORD', 'Demo12345')`: `.env.example` memuat `DEMO_PASSWORD=` kosong, dan `env()` mengembalikan string kosong untuk nilai kosong, bukan default; 13 §3: "bila kosong dipakai `Demo12345`").
  - `DatabaseSeeder` = 13 §2.3 (inti: `RolePermissionSeeder`, `SettingsSeeder`; `DemoSeeder` hanya `local`/`testing`, selain itu `warn`); `DemoSeeder` = guard `RuntimeException` + cek `superadmin@unpamhospital.test` + `config(['mail.default' => 'array'])` + `call([DemoUserSeeder::class])` + tabel akun di konsol; `DemoUserSeeder` (konstanta `ACCOUNTS`, tiga akun staf 13 §3, `must_change_password=false`, password `config('app.demo_password')`, melempar `RuntimeException` di produksi, idempoten dengan peringatan).

**Pesan keluaran perintah (sumber):**

| Pesan | Sumber |
|---|---|
| "Peran super-admin belum ada. Jalankan: php artisan db:seed --class=RolePermissionSeeder" | 11 §10.2 |
| "Nama wajib diisi." / "Nama minimal 3 karakter." / "Nama maksimal 100 karakter." / "Email wajib diisi." / "Format email tidak valid." / "Email maksimal 255 karakter." / "Email ini sudah terdaftar. Gunakan email lain." / "Password wajib diisi." / "Password minimal 8 karakter." / "Password harus mengandung huruf." / "Password harus mengandung angka." / "Password maksimal 64 karakter." / "Konfirmasi password tidak cocok." | 12 V-40, V-07 |
| "Mode non-interaktif membutuhkan opsi --name, --email, dan --password." | **ASUMSI** |
| "Password pada opsi --password terekam di riwayat shell. Gunakan prompt interaktif untuk lingkungan produksi." | **ASUMSI** (11 §10.2: peringatan terekam di riwayat shell) |
| "Akun Super Admin dibuat: {email}." dan "Saat login pertama di /admin/login, Anda diminta mengganti password." | **ASUMSI** |
| Seeder: lihat kode 13 §2.3 (peringatan `DemoSeeder dilewati …`, `DemoSeeder DILARANG …`, `Data demo sudah ada …`, `MODE DEMO …`); "Akun demo {email} sudah ada, dilewati." | 13 §2.3 / **ASUMSI** |

**Test IDs:** `SEED-02`, `STF-01` (bagian CLI), `AUD-01` (bagian `staff.created` pelaku sistem).

**Catatan teknis (diverifikasi dari source Laravel 12.69):**
- Simulasi `APP_ENV=production` di tes: `$this->app['env'] = 'production';` (`Application::environment()` dan `isProduction()` membaca `$this['env']`; `detectEnvironment(fn () => 'production')` menulis ke tempat yang sama), selalu dipulihkan di `finally`. Aplikasi dibuat ulang untuk tiap tes.
- `$this->seed(...)` dan `db:seed` di lingkungan `production` meminta konfirmasi kecuali `--force`; tes memakai `['--force' => true]`.
- `$this->command?->warn()` aman di seeder yang dipanggil langsung (properti `$command` tidak terisi ⇒ null); `Seeder::call` memakai `isset($this->command)`.
- `PendingCommand::expectsQuestion()` memakai `->once()->ordered()`: urutan pertanyaan di tes harus sama dengan urutan di perintah (Nama lengkap, Email, Password, Konfirmasi password); `secret()` juga melewati `askQuestion` sehingga memakai `expectsQuestion`. Pada `--no-interaction`, `$this->input->isInteractive()` bernilai `false`.
- Pesan galat aturan `Password::min(8)->letters()->numbers()` berasal dari validator dalam milik rule `Password` yang memakai `customMessages` validator luar: kunci pesan `password.min`, `password.max`, `password.letters`, `password.numbers`, `password.required` (dicocokkan oleh `FormatsMessages::getFromLocalArray`).

- [ ] **Step 1: Verifikasi prasyarat dan buat pembantu `Edit-Text`**

Pembantu ini dipakai di Task 18–19 untuk mengedit berkas ber-CRLF (checkout Windows dengan `autocrlf`) secara pasti: teks lama harus cocok **tepat satu kali**, akhiran baris dipertahankan.

````powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git branch --show-current
& $PHP artisan test --filter="StaffAccountServiceTest|SettingsSeederTest|RolePermissionSeederTest"
$helper = @'
function Edit-Text {
    param([string]$Path, [string]$Old, [string]$New)
    $enc = New-Object System.Text.UTF8Encoding $false
    $raw = [IO.File]::ReadAllText($Path, $enc)
    $crlf = $raw.Contains("`r`n")
    $text = $raw.Replace("`r`n", "`n")
    $o = $Old.Replace("`r`n", "`n").TrimEnd("`n")
    $n = $New.Replace("`r`n", "`n").TrimEnd("`n")
    $count = [regex]::Matches($text, [regex]::Escape($o)).Count
    if ($count -ne 1) { throw "Edit-Text: $count kecocokan (harus tepat 1) di $Path" }
    $text = $text.Replace($o, $n)
    if ($crlf) { $text = $text.Replace("`n", "`r`n") }
    [IO.File]::WriteAllText($Path, $text, $enc)
}
'@
[IO.File]::WriteAllText("$env:TEMP\unpam-edit-text.ps1", $helper, (New-Object System.Text.UTF8Encoding $false))
Test-Path "$env:TEMP\unpam-edit-text.ps1"
````
Expected: `fase-1-fondasi`; tes PASS; `True`.

**Siklus A — konfigurasi, seeder, guard produksi (SEED-02)**

- [ ] **Step 2: Tulis tes yang gagal — guard produksi (tool Write) `tests/Feature/Seeding/SeederProductionGuardTest.php`**

```php
<?php

namespace Tests\Feature\Seeding;

use App\Models\User;
use App\Models\WebsiteSetting;
use Closure;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SeederProductionGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_seeds_only_core_data_and_warns_in_production(): void
    {
        $this->inEnvironment('production', function (): void {
            $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true])
                ->expectsOutputToContain('DemoSeeder dilewati (APP_ENV=production)')
                ->expectsOutputToContain('php artisan app:create-super-admin')
                ->assertExitCode(0);
        });

        $this->assertSame(3, Role::query()->count());
        $this->assertSame(19, Permission::query()->count());
        $this->assertSame(19, WebsiteSetting::query()->count());
        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, User::query()->where('email', 'like', '%.test')->count());
    }

    public function test_demo_seeder_throws_runtime_exception_in_production_even_when_called_directly(): void
    {
        $thrown = null;

        $this->inEnvironment('production', function () use (&$thrown): void {
            try {
                $this->app->make(DemoSeeder::class)->run();
            } catch (RuntimeException $e) {
                $thrown = $e;
            }
        });

        $this->assertInstanceOf(RuntimeException::class, $thrown);
        $this->assertStringContainsString('DemoSeeder DILARANG di APP_ENV=production', $thrown->getMessage());
        $this->assertSame(0, User::query()->count());
    }

    public function test_db_seed_class_demo_seeder_fails_in_production(): void
    {
        $thrown = null;

        $this->inEnvironment('production', function () use (&$thrown): void {
            try {
                $this->artisan('db:seed', ['--class' => DemoSeeder::class, '--force' => true])->run();
            } catch (RuntimeException $e) {
                $thrown = $e;
            }
        });

        $this->assertInstanceOf(RuntimeException::class, $thrown);
        $this->assertSame(0, User::query()->where('email', 'like', '%.test')->count());
    }

    public function test_database_seeder_runs_the_demo_seeder_in_testing_environment(): void
    {
        $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--no-interaction' => true])
            ->expectsOutputToContain('MODE DEMO')
            ->assertExitCode(0);

        $this->assertSame(3, User::query()->count());
        $this->assertTrue(User::query()->where('email', 'superadmin@unpamhospital.test')->exists());
        $this->assertSame(3, Role::query()->count());
    }

    public function test_demo_seeder_warns_and_skips_when_demo_data_already_exists(): void
    {
        $this->seed(DemoSeeder::class);

        $this->artisan('db:seed', ['--class' => DemoSeeder::class, '--no-interaction' => true])
            ->expectsOutputToContain('Data demo sudah ada')
            ->assertExitCode(0);

        $this->assertSame(3, User::query()->count());
    }

    private function inEnvironment(string $environment, Closure $callback): void
    {
        $original = $this->app['env'];
        $this->app['env'] = $environment;

        try {
            $callback();
        } finally {
            $this->app['env'] = $original;
        }
    }
}
```

- [ ] **Step 3: Tulis tes yang gagal — `DemoUserSeeder` (tool Write) `tests/Feature/Seeding/DemoUserSeederTest.php`**

```php
<?php

namespace Tests\Feature\Seeding;

use App\Enums\AccountType;
use App\Models\User;
use Database\Seeders\DemoUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class DemoUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_three_staff_accounts_with_roles_and_the_demo_password(): void
    {
        $this->seed(DemoUserSeeder::class);

        $expected = [
            'superadmin@unpamhospital.test' => ['Super Admin Demo', 'super-admin'],
            'konten@unpamhospital.test' => ['Admin Konten Demo', 'content-admin'],
            'pendaftaran@unpamhospital.test' => ['Petugas Pendaftaran Demo', 'registration-officer'],
        ];

        $this->assertSame(3, User::query()->count());

        foreach ($expected as $email => [$name, $role]) {
            $user = User::query()->where('email', $email)->firstOrFail();

            $this->assertSame($name, $user->name);
            $this->assertSame(AccountType::Staff, $user->account_type);
            $this->assertTrue($user->is_active);
            $this->assertFalse($user->must_change_password);
            $this->assertSame([$role], $user->roles()->pluck('name')->all());
            $this->assertTrue(Hash::check('Demo12345', $user->password), "{$email} harus memakai password demo bawaan.");
        }
    }

    public function test_it_uses_the_configured_demo_password(): void
    {
        config(['app.demo_password' => 'Rahasia987']);

        $this->seed(DemoUserSeeder::class);

        $user = User::query()->where('email', 'konten@unpamhospital.test')->firstOrFail();
        $this->assertTrue(Hash::check('Rahasia987', $user->password));
        $this->assertFalse(Hash::check('Demo12345', $user->password));
    }

    public function test_running_twice_is_idempotent_and_warns_about_existing_accounts(): void
    {
        $this->seed(DemoUserSeeder::class);

        $this->artisan('db:seed', ['--class' => DemoUserSeeder::class, '--no-interaction' => true])
            ->expectsOutputToContain('sudah ada, dilewati')
            ->assertExitCode(0);

        $this->assertSame(3, User::query()->count());
    }

    public function test_it_refuses_to_run_in_production(): void
    {
        $thrown = null;
        $original = $this->app['env'];
        $this->app['env'] = 'production';

        try {
            $this->app->make(DemoUserSeeder::class)->run();
        } catch (RuntimeException $e) {
            $thrown = $e;
        } finally {
            $this->app['env'] = $original;
        }

        $this->assertInstanceOf(RuntimeException::class, $thrown);
        $this->assertStringContainsString('DemoUserSeeder DILARANG di APP_ENV=production', $thrown->getMessage());
        $this->assertSame(0, User::query()->count());
    }

    public function test_empty_demo_password_in_env_falls_back_to_the_default(): void
    {
        $this->assertSame('Demo12345', $this->demoPasswordConfigWithEnv(''));
        $this->assertSame('Rahasia987', $this->demoPasswordConfigWithEnv('Rahasia987'));
    }

    private function demoPasswordConfigWithEnv(string $value): string
    {
        $previous = [
            'env' => $_ENV['DEMO_PASSWORD'] ?? null,
            'server' => $_SERVER['DEMO_PASSWORD'] ?? null,
        ];

        $_ENV['DEMO_PASSWORD'] = $value;
        $_SERVER['DEMO_PASSWORD'] = $value;

        try {
            $config = require config_path('app.php');

            return $config['demo_password'];
        } finally {
            $this->restoreEnvValue($_ENV, $previous['env']);
            $this->restoreEnvValue($_SERVER, $previous['server']);
        }
    }

    private function restoreEnvValue(array &$bag, ?string $previous): void
    {
        if ($previous === null) {
            unset($bag['DEMO_PASSWORD']);

            return;
        }

        $bag['DEMO_PASSWORD'] = $previous;
    }
}
```

- [ ] **Step 4: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="SeederProductionGuardTest|DemoUserSeederTest"
```
Expected: FAIL — `Target class [Database\Seeders\DemoUserSeeder] does not exist` / `DemoSeeder` tidak ditemukan; tes produksi `DatabaseSeeder` gagal karena peringatan `DemoSeeder dilewati (APP_ENV=production)` tidak pernah dicetak; tes konfigurasi gagal dengan `Failed asserting that '' is identical to 'Demo12345'` (atau `null`) karena kunci `demo_password` belum ada.

- [ ] **Step 5: Konfigurasi `demo_password` dan `DEMO_PASSWORD=` di `.env.example`**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
. "$env:TEMP\unpam-edit-text.ps1"

Edit-Text "$APP\config\app.php" @'
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

];
'@ @'
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Akun Demo
    |--------------------------------------------------------------------------
    |
    | Hanya dipakai DemoUserSeeder di lingkungan local/testing (13 §3, K-11).
    | Nilai kosong di .env berarti memakai Demo12345, karena env('X', 'default')
    | mengembalikan string kosong (bukan default) untuk "DEMO_PASSWORD=".
    |
    */

    'demo_password' => env('DEMO_PASSWORD') ?: 'Demo12345',

];
'@

Edit-Text "$APP\.env.example" 'VITE_APP_NAME="${APP_NAME}"' @'
VITE_APP_NAME="${APP_NAME}"

# Opsional (hanya local/testing): password akun demo. Kosong = Demo12345. DILARANG di produksi.
DEMO_PASSWORD=
'@

Select-String -Path config\app.php -Pattern "demo_password" -SimpleMatch | ForEach-Object { $_.Line }
Select-String -Path .env.example -Pattern '^DEMO_PASSWORD=' | ForEach-Object { $_.Line }
```
Expected: baris `'demo_password' => env('DEMO_PASSWORD') ?: 'Demo12345',` dan `DEMO_PASSWORD=` tercetak (tanpa galat `Edit-Text`).

- [ ] **Step 6: Tulis `database/seeders/DemoUserSeeder.php` (tool Write)**

```php
<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class DemoUserSeeder extends Seeder
{
    /**
     * Tiga akun staf demo (13 §3). Akun pasien demo ditambahkan di fase 5 (F1-R10).
     *
     * @var list<array{name: string, email: string, role: string}>
     */
    public const ACCOUNTS = [
        ['name' => 'Super Admin Demo', 'email' => 'superadmin@unpamhospital.test', 'role' => 'super-admin'],
        ['name' => 'Admin Konten Demo', 'email' => 'konten@unpamhospital.test', 'role' => 'content-admin'],
        ['name' => 'Petugas Pendaftaran Demo', 'email' => 'pendaftaran@unpamhospital.test', 'role' => 'registration-officer'],
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('DemoUserSeeder DILARANG di APP_ENV=production. Akun demo berpassword umum.');
        }

        // Idempoten (firstOrCreate): membuat DemoUserSeeder dapat dijalankan sendiri pada database kosong.
        $this->call(RolePermissionSeeder::class, true);

        $password = (string) config('app.demo_password');

        foreach (self::ACCOUNTS as $account) {
            if (User::query()->where('email', $account['email'])->exists()) {
                $this->command?->warn("Akun demo {$account['email']} sudah ada, dilewati.");

                continue;
            }

            $user = new User;
            $user->forceFill([
                'name' => $account['name'],
                'email' => $account['email'],
                'password' => $password,
                'account_type' => AccountType::Staff,
                'is_active' => true,
                'must_change_password' => false,
            ])->save();

            $user->assignRole($account['role']);
        }
    }
}
```

- [ ] **Step 7: Tulis `database/seeders/DemoSeeder.php` (tool Write)**

```php
<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('DemoSeeder DILARANG di APP_ENV='.app()->environment().'. Data demo memuat akun berpassword umum.');
        }

        if (User::query()->where('email', 'superadmin@unpamhospital.test')->exists()) {
            $this->command?->warn('Data demo sudah ada. Jalankan: php artisan migrate:fresh --seed');

            return;
        }

        $this->command?->warn('MODE DEMO: akun & data contoh dibuat. Jangan gunakan di produksi.');
        config(['mail.default' => 'array']); // surat notifikasi seeding dibuang, bukan ditumpuk di log

        // Seeder data master (fase 2 dst.) ditambahkan ke daftar ini mengikuti urutan 13 §2.1.
        $this->call([DemoUserSeeder::class]);

        $password = (string) config('app.demo_password');

        $this->command?->table(
            ['Nama', 'Email', 'Peran', 'Password'],
            array_map(
                fn (array $account): array => [$account['name'], $account['email'], $account['role'], $password],
                DemoUserSeeder::ACCOUNTS,
            ),
        );
    }
}
```

- [ ] **Step 8: Ganti isi `database/seeders/DatabaseSeeder.php` (baca berkas lebih dulu dengan Read, lalu tool Write)**

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seeder inti selalu berjalan (13 §2.3). PageSeeder ditambahkan di fase 4a.
     * DemoSeeder hanya untuk lingkungan local/testing; di produksi Super Admin dibuat
     * dengan: php artisan app:create-super-admin.
     */
    public function run(): void
    {
        $this->call([RolePermissionSeeder::class, SettingsSeeder::class]);

        if (app()->environment(['local', 'testing'])) {
            $this->call(DemoSeeder::class);

            return;
        }

        $this->command?->warn('DemoSeeder dilewati (APP_ENV='.app()->environment().'). Buat Super Admin dengan: php artisan app:create-super-admin');
    }
}
```

- [ ] **Step 9: Jalankan tes — harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="SeederProductionGuardTest|DemoUserSeederTest|SettingsSeederTest|RolePermissionSeederTest"
```
Expected: PASS (tes seeder lama tetap hijau meski `DatabaseSeeder` kini juga membuat akun demo di `testing`).

- [ ] **Step 10: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint --test
git add config/app.php .env.example database/seeders/DatabaseSeeder.php database/seeders/DemoSeeder.php database/seeders/DemoUserSeeder.php tests/Feature/Seeding/SeederProductionGuardTest.php tests/Feature/Seeding/DemoUserSeederTest.php
git commit -m "feat(fase-1): seeder demo staf dengan guard produksi dan password demo dari konfigurasi"
```
Expected: Pint `PASS` (bila gagal: `& $PHP vendor\bin\pint`, lalu ulangi `--test`).

**Siklus B — perintah `app:create-super-admin`**

- [ ] **Step 11: Tulis tes yang gagal (tool Write) `tests/Feature/Console/CreateSuperAdminTest.php`**

```php
<?php

namespace Tests\Feature\Console;

use App\Enums\AccountType;
use App\Enums\ActorType;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

class CreateSuperAdminTest extends TestCase
{
    use InteractsWithStaff;
    use RefreshDatabase;

    public function test_interactive_run_creates_super_admin_that_must_change_password(): void
    {
        $this->seedRoles();

        $this->artisan('app:create-super-admin')
            ->expectsQuestion('Nama lengkap', 'Budi Santoso')
            ->expectsQuestion('Email', '  Budi.Santoso@UnpamHospital.test ')
            ->expectsQuestion('Password', 'Rahasia123')
            ->expectsQuestion('Konfirmasi password', 'Rahasia123')
            ->expectsOutputToContain('Akun Super Admin dibuat: budi.santoso@unpamhospital.test')
            ->doesntExpectOutputToContain('Rahasia123')
            ->assertExitCode(0);

        $user = User::query()->where('email', 'budi.santoso@unpamhospital.test')->sole();

        $this->assertSame('Budi Santoso', $user->name);
        $this->assertSame(AccountType::Staff, $user->account_type);
        $this->assertTrue($user->is_active);
        $this->assertTrue($user->must_change_password);
        $this->assertSame(['super-admin'], $user->roles()->pluck('name')->all());
        $this->assertTrue(Hash::check('Rahasia123', $user->password));
        $this->assertStringStartsWith('$2y$', $user->password);
    }

    public function test_options_for_name_and_email_still_prompt_for_password_twice(): void
    {
        $this->seedRoles();

        $this->artisan('app:create-super-admin', ['--name' => 'Dewi Lestari', '--email' => 'dewi@unpamhospital.test'])
            ->expectsQuestion('Password', 'Rahasia123')
            ->expectsQuestion('Konfirmasi password', 'Rahasia123')
            ->expectsOutputToContain('Akun Super Admin dibuat: dewi@unpamhospital.test')
            ->assertExitCode(0);

        $this->assertTrue(User::query()->where('email', 'dewi@unpamhospital.test')->exists());
    }

    public function test_non_interactive_run_with_all_options_works_and_warns_about_shell_history(): void
    {
        $this->seedRoles();

        $this->artisan('app:create-super-admin', [
            '--name' => 'Sari Dewi',
            '--email' => 'sari@unpamhospital.test',
            '--password' => 'Rahasia123',
            '--no-interaction' => true,
        ])
            ->expectsOutputToContain('terekam di riwayat shell')
            ->expectsOutputToContain('Akun Super Admin dibuat: sari@unpamhospital.test')
            ->assertExitCode(0);

        $this->assertSame(1, User::query()->count());
    }

    public function test_non_interactive_run_without_all_options_fails_before_creating_anything(): void
    {
        $this->seedRoles();

        $this->artisan('app:create-super-admin', ['--name' => 'Sari Dewi', '--no-interaction' => true])
            ->expectsOutputToContain('Mode non-interaktif membutuhkan opsi --name, --email, dan --password.')
            ->assertExitCode(1);

        $this->assertSame(0, User::query()->count());
    }

    public function test_duplicate_email_is_rejected_case_insensitively(): void
    {
        $this->seedRoles();
        User::factory()->create(['email' => 'ada@unpamhospital.test']);

        $this->artisan('app:create-super-admin', [
            '--name' => 'Orang Lain',
            '--email' => 'ADA@unpamhospital.test',
            '--password' => 'Rahasia123',
            '--no-interaction' => true,
        ])
            ->expectsOutputToContain('Email ini sudah terdaftar. Gunakan email lain.')
            ->assertExitCode(1);

        $this->assertSame(1, User::query()->count());
    }

    #[DataProvider('weakPasswords')]
    public function test_weak_password_is_rejected(string $password, string $message): void
    {
        $this->seedRoles();

        $this->artisan('app:create-super-admin', [
            '--name' => 'Eka Putri',
            '--email' => 'eka@unpamhospital.test',
            '--password' => $password,
            '--no-interaction' => true,
        ])
            ->expectsOutputToContain($message)
            ->assertExitCode(1);

        $this->assertSame(0, User::query()->count());
    }

    /** @return array<string, array{string, string}> */
    public static function weakPasswords(): array
    {
        return [
            'tanpa angka' => ['abcdefgh', 'Password harus mengandung angka.'],
            'tanpa huruf' => ['12345678', 'Password harus mengandung huruf.'],
            'terlalu pendek' => ['a1b2c3', 'Password minimal 8 karakter.'],
            'terlalu panjang' => ['a1'.str_repeat('x', 63), 'Password maksimal 64 karakter.'],
        ];
    }

    public function test_password_confirmation_mismatch_aborts(): void
    {
        $this->seedRoles();

        $this->artisan('app:create-super-admin')
            ->expectsQuestion('Nama lengkap', 'Gita Permata')
            ->expectsQuestion('Email', 'gita@unpamhospital.test')
            ->expectsQuestion('Password', 'Rahasia123')
            ->expectsQuestion('Konfirmasi password', 'Berbeda123')
            ->expectsOutputToContain('Konfirmasi password tidak cocok.')
            ->assertExitCode(1);

        $this->assertSame(0, User::query()->count());
    }

    public function test_missing_super_admin_role_aborts_with_instruction(): void
    {
        $this->artisan('app:create-super-admin', [
            '--name' => 'Hadi Pranoto',
            '--email' => 'hadi@unpamhospital.test',
            '--password' => 'Rahasia123',
            '--no-interaction' => true,
        ])
            ->expectsOutputToContain('Peran super-admin belum ada.')
            ->expectsOutputToContain('php artisan db:seed --class=RolePermissionSeeder')
            ->assertExitCode(1);

        $this->assertSame(0, User::query()->count());
    }

    public function test_it_writes_staff_created_audit_by_the_system_actor_without_the_password(): void
    {
        $this->seedRoles();

        $this->artisan('app:create-super-admin', [
            '--name' => 'Indra Wijaya',
            '--email' => 'indra@unpamhospital.test',
            '--password' => 'Rahasia123',
            '--no-interaction' => true,
        ])->assertExitCode(0);

        $user = User::query()->where('email', 'indra@unpamhospital.test')->sole();
        $log = AuditLog::query()->where('action', 'staff.created')->sole();

        $this->assertSame(ActorType::System, $log->actor_type);
        $this->assertNull($log->user_id);
        $this->assertSame((new User)->getMorphClass(), $log->auditable_type);
        $this->assertSame($user->id, (int) $log->auditable_id);
        $this->assertNull($log->old_values);
        $this->assertEquals([
            'name' => 'Indra Wijaya',
            'email' => 'indra@unpamhospital.test',
            'role' => 'super-admin',
        ], $log->new_values);
        $this->assertStringNotContainsString('Rahasia123', DB::table('audit_logs')->get()->toJson());
    }

    public function test_user_is_not_created_when_the_audit_row_cannot_be_written(): void
    {
        $this->seedRoles();
        AuditLog::creating(fn () => throw new RuntimeException('Gagal menulis audit.'));

        $thrown = null;

        try {
            Artisan::call('app:create-super-admin', [
                '--name' => 'Joko Susilo',
                '--email' => 'joko@unpamhospital.test',
                '--password' => 'Rahasia123',
                '--no-interaction' => true,
            ]);
        } catch (RuntimeException $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown, 'Galat audit tidak boleh ditelan.');
        $this->assertSame(0, User::query()->count());
    }
}
```

- [ ] **Step 12: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=CreateSuperAdminTest
```
Expected: FAIL — semua tes ERROR `Symfony\Component\Console\Exception\CommandNotFoundException: Command "app:create-super-admin" is not defined.`

- [ ] **Step 13: Tulis `app/Console/Commands/CreateSuperAdmin.php` (tool Write)**

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\AccountType;
use App\Models\User;
use App\Rules\PasswordRules;
use App\Services\Audit\AuditLogger;
use App\Support\Actor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class CreateSuperAdmin extends Command
{
    private const ROLE = 'super-admin';

    protected $signature = 'app:create-super-admin
        {--name= : Nama lengkap}
        {--email= : Alamat email untuk login}
        {--password= : Password (hindari: terekam di riwayat shell)}';

    protected $description = 'Membuat akun Super Admin (instalasi produksi pertama; tanpa seeder demo)';

    public function handle(AuditLogger $audit): int
    {
        if (! Role::query()->where('name', self::ROLE)->where('guard_name', 'web')->exists()) {
            $this->components->error('Peran super-admin belum ada. Jalankan: php artisan db:seed --class=RolePermissionSeeder');

            return self::FAILURE;
        }

        $name = $this->option('name');
        $email = $this->option('email');
        $password = $this->option('password');

        if (! $this->input->isInteractive() && (blank($name) || blank($email) || blank($password))) {
            $this->components->error('Mode non-interaktif membutuhkan opsi --name, --email, dan --password.');

            return self::FAILURE;
        }

        $passwordFromOption = filled($password);

        if ($passwordFromOption) {
            $this->components->warn('Password pada opsi --password terekam di riwayat shell. Gunakan prompt interaktif untuk lingkungan produksi.');
        }

        $name = trim((string) (filled($name) ? $name : $this->ask('Nama lengkap')));
        $email = Str::lower(trim((string) (filled($email) ? $email : $this->ask('Email'))));

        if (! $passwordFromOption) {
            $password = (string) $this->secret('Password');
            $confirmation = (string) $this->secret('Konfirmasi password');

            if ($password !== $confirmation) {
                $this->components->error('Konfirmasi password tidak cocok.');

                return self::FAILURE;
            }
        }

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => (string) $password],
            [
                'name' => ['required', 'string', 'min:3', 'max:100'],
                'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
                // Konfirmasi sudah diminta lewat prompt, jadi aturan 'confirmed' dibuang.
                'password' => array_values(array_filter(
                    PasswordRules::default(),
                    fn (mixed $rule): bool => $rule !== 'confirmed',
                )),
            ],
            $this->validationMessages(),
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->components->error($message);
            }

            return self::FAILURE;
        }

        $user = DB::transaction(function () use ($audit, $name, $email, $password): User {
            $user = new User;
            $user->forceFill([
                'name' => $name,
                'email' => $email,
                'password' => (string) $password,
                'account_type' => AccountType::Staff,
                'is_active' => true,
                'must_change_password' => true,
            ])->save();

            $user->assignRole(self::ROLE);

            $audit->log(
                'staff.created',
                $user,
                null,
                ['name' => $user->name, 'email' => $user->email, 'role' => self::ROLE],
                "Akun Super Admin {$user->name} dibuat lewat perintah konsol.",
                Actor::system(),
            );

            return $user;
        });

        $this->components->info("Akun Super Admin dibuat: {$user->email}.");
        $this->line('Saat login pertama di /admin/login, Anda diminta mengganti password.');

        return self::SUCCESS;
    }

    /**
     * @return array<string, string>
     */
    private function validationMessages(): array
    {
        return [
            'name.required' => 'Nama wajib diisi.',
            'name.min' => 'Nama minimal 3 karakter.',
            'name.max' => 'Nama maksimal 100 karakter.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.max' => 'Email maksimal 255 karakter.',
            'email.unique' => 'Email ini sudah terdaftar. Gunakan email lain.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal 8 karakter.',
            'password.max' => 'Password maksimal 64 karakter.',
            'password.letters' => 'Password harus mengandung huruf.',
            'password.numbers' => 'Password harus mengandung angka.',
        ];
    }
}
```

- [ ] **Step 14: Jalankan tes — harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=CreateSuperAdminTest
```
Expected: PASS (13 tes: 9 tes biasa + 4 kasus *data provider*). Bila satu kasus password lemah gagal karena teks pesan berbeda, **jangan** melonggarkan tes: periksa kunci pesan di `validationMessages()` terhadap `FormatsMessages::getFromLocalArray` (lihat catatan teknis) dan perbaiki kuncinya.

- [ ] **Step 15: Pemeriksaan perintah terdaftar, suite penuh, Pint, commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan list app | Select-String "create-super-admin"
& $PHP artisan app:create-super-admin --help | Select-String "--name|--email|--password"
& $PHP artisan test
& $PHP vendor\bin\pint --test
git add app/Console/Commands/CreateSuperAdmin.php tests/Feature/Console/CreateSuperAdminTest.php
git commit -m "feat(fase-1): perintah app:create-super-admin interaktif dan non-interaktif dengan audit pelaku sistem"
```
Expected: `app:create-super-admin` muncul di daftar dan bantuan memuat tiga opsi; seluruh tes PASS; Pint `PASS`.

---

### Task 19: Matriks akses fase 1, rekonsiliasi dokumen/konfigurasi, verifikasi gerbang fase

**Files:**
- Create: `tests/Feature/Access/AdminPageMatrixTest.php`
- Modify: `.env.example`, `.env.testing.example`, `composer.json`, `tests/Unit/ComposerManifestTest.php`, `tests/Unit/RepositoryHygieneTest.php`, `README.md`, `docs/superpowers/specs/2026-10-02-unpam-hospital-blueprint/{00,06,11,12,15,16,17}-*.md` (bagian terkait; `10-struktur-folder.md` diperiksa dan tidak berselisih dengan hasil fase 1, jadi tidak diubah; 06 ikut diubah karena memuat teks V-03 yang sama), `docs/superpowers/plans/2026-10-03-fase-1-fondasi.md` (Catatan Eksekusi), `docs/superpowers/CHECKPOINT.md`

**Interfaces:**
- Consumes: semua task sebelumnya (route `admin.login`, `admin.password.edit`, `admin.dashboard`, redirect admin, middleware, `InteractsWithStaff`, pembantu `Edit-Text` dari Task 18 Step 1).
- Produces: data provider matriks 15 §3.3 baris 31, 31b, 32 × aktor (Tamu, Pasien, Admin Konten, Petugas, Super Admin) dengan hasil persis (**→L** = redirect ke `admin.login`; **→D** = redirect dasbor sesuai tipe akun: staf ⇒ `admin.dashboard`, pasien ⇒ `patient.dashboard` bila ada, selain itu `home` (F1-R2)). Rekonsiliasi M-3 (`.env.example`: `PHP_CLI_SERVER_WORKERS=4`, `LOG_STACK=single`, `MAIL_FROM_ADDRESS="info@unpamhospital.test"`, `DEMO_PASSWORD=`; `.env.testing.example`: `LOG_CHANNEL=single`; blueprint 16 §5.1/§5.2/§7.2/§7.3 disamakan dengan berkas contoh dan vhost nyata, tanpa `ServerAlias`, log `common`) dan M-6 (`composer.json`: hapus `post-root-package-install` dan `post-create-project-cmd`; `setup` tanpa `migrate`/sqlite). Dokumen: 12 V-03 (F1-R5), 15 SEK-03 (F1-R6), 11 §6.10 (F1-R7, F1-R8), 17 §4 D-21–D-28, 00 J.6. README "Fase 1". Verifikasi gerbang fase + langkah **MANUAL (pemilik)**. "Catatan Eksekusi" dan `CHECKPOINT.md`.

**Test IDs:** `AKS-01`–`AKS-05` (baris 31/31b/32), `INS` (bagian `migrate:fresh --seed`).

**Catatan:** berkas kerja berakhiran CRLF; semua pengeditan berkas yang sudah ada memakai `Edit-Text` (cocok tepat satu kali, akhiran baris dipertahankan). `DEMO_PASSWORD=` sudah ditambahkan ke `.env.example` pada Task 18 Step 5, sehingga asersi `DEMO_PASSWORD` di tes M-3 tidak akan merah (tetap dipertahankan sebagai penjaga regresi); asersi lain merah dulu.

- [ ] **Step 1: Verifikasi prasyarat**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git branch --show-current
git status --short
& $PHP artisan test
Test-Path "$env:TEMP\unpam-edit-text.ps1"
```
Expected: `fase-1-fondasi`; `git status` bersih; seluruh tes PASS; `True` (bila `False`, ulangi pembuatan pembantu dari Task 18 Step 1).

**(a) Matriks akses `AdminPageMatrixTest`**

- [ ] **Step 2: Tulis tes matriks (tool Write) `tests/Feature/Access/AdminPageMatrixTest.php`**

```php
<?php

namespace Tests\Feature\Access;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * 15 §3.3 baris 31, 31b, 32 × lima aktor (AKS-01 sampai AKS-05, bagian /admin).
 * Hasil: 200 = tampil, 403 = ditolak, L = redirect ke admin.login, D = redirect dasbor sesuai tipe akun.
 */
class AdminPageMatrixTest extends TestCase
{
    use InteractsWithStaff;
    use RefreshDatabase;

    private const ACTORS = ['guest', 'patient', 'content-admin', 'registration-officer', 'super-admin'];

    /** @return array<string, array{string, string, string}> */
    public static function matrix(): array
    {
        // Urutan hasil mengikuti ACTORS: Tamu, Pasien, Admin Konten, Petugas, Super Admin.
        $rows = [
            '31 Login admin' => ['admin.login', ['200', 'D', 'D', 'D', 'D']],
            '31b Ganti password staf' => ['admin.password.edit', ['L', '403', '200', '200', '200']],
            '32 Dashboard' => ['admin.dashboard', ['L', '403', '200', '200', '200']],
        ];

        $cases = [];

        foreach ($rows as $label => [$routeName, $expected]) {
            foreach (self::ACTORS as $index => $actor) {
                $cases["{$label} / {$actor}"] = [$routeName, $actor, $expected[$index]];
            }
        }

        return $cases;
    }

    #[DataProvider('matrix')]
    public function test_admin_page_matrix(string $routeName, string $actor, string $expected): void
    {
        $user = $this->userFor($actor);

        if ($user !== null) {
            $this->actingAs($user);
        }

        $response = $this->get(route($routeName));

        match ($expected) {
            '200' => $response->assertOk(),
            '403' => $response->assertForbidden(),
            'L' => $response->assertRedirect(route('admin.login')),
            'D' => $response->assertRedirect($this->dashboardFor($actor)),
        };
    }

    private function userFor(string $actor): ?User
    {
        return match ($actor) {
            'guest' => null,
            'patient' => User::factory()->patient()->create(),
            default => $this->createStaff($actor),
        };
    }

    private function dashboardFor(string $actor): string
    {
        if ($actor === 'patient') {
            return Route::has('patient.dashboard') ? route('patient.dashboard') : route('home');
        }

        return route('admin.dashboard');
    }
}
```

- [ ] **Step 3: Jalankan tes matriks**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=AdminPageMatrixTest
```
Expected: PASS, 15 kasus (3 baris × 5 aktor). Ini tes karakterisasi: perilaku sudah dibangun di Task 14–15. Bila ada sel yang gagal, itu **temuan bug** pada middleware/route/redirect Task 14–15 (mis. Pasien di `admin.login` harus →`home`, bukan 500 `RouteNotFoundException`; tamu di `admin.password.edit` harus →`admin.login`): perbaiki sumbernya, jangan mengubah tabel hasil di atas.

- [ ] **Step 4: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint --test
git add tests/Feature/Access/AdminPageMatrixTest.php
git commit -m "test(fase-1): matriks akses admin baris 31, 31b, 32 untuk lima aktor"
```
Expected: `PASS`.

**(b) Rekonsiliasi M-3 dan M-6 (tes diperbarui terlebih dahulu)**

- [ ] **Step 5: Perbarui tes agar merah (tool Edit)**

Pada `tests/Unit/ComposerManifestTest.php`, ganti baris penanda penutup method terakhir. Cara paling mudah: ganti teks

```php
        $this->assertNull($this->lockedVersion('laravel/pail'));
    }
}
```

dengan

```php
        $this->assertNull($this->lockedVersion('laravel/pail'));
    }

    public function test_scripts_do_not_reference_sqlite_or_create_project_hooks(): void
    {
        $scripts = $this->json('composer.json')['scripts'];

        $this->assertArrayNotHasKey('post-create-project-cmd', $scripts);
        $this->assertArrayNotHasKey('post-root-package-install', $scripts);
        $this->assertStringNotContainsStringIgnoringCase('sqlite', json_encode($scripts, JSON_THROW_ON_ERROR));
    }

    public function test_setup_script_does_not_migrate_before_the_database_exists(): void
    {
        $setup = implode(' ', (array) ($this->json('composer.json')['scripts']['setup'] ?? []));

        $this->assertStringNotContainsString('migrate', $setup);
    }
}
```

Pada `tests/Unit/RepositoryHygieneTest.php`, ganti

```php
            $this->assertMatchesRegularExpression('/^DB_CONNECTION=mariadb\r?$/m', $content);
        }
    }
}
```

dengan

```php
            $this->assertMatchesRegularExpression('/^DB_CONNECTION=mariadb\r?$/m', $content);
        }
    }

    public function test_example_env_files_follow_the_blueprint_reconciliation(): void
    {
        $env = file_get_contents($this->path('.env.example'));

        foreach ([
            '/^PHP_CLI_SERVER_WORKERS=4\r?$/m',
            '/^LOG_STACK=single\r?$/m',
            '/^MAIL_FROM_ADDRESS="info@unpamhospital\.test"\r?$/m',
            '/^DEMO_PASSWORD=\r?$/m',
        ] as $pattern) {
            $this->assertMatchesRegularExpression($pattern, $env, ".env.example harus cocok dengan {$pattern}");
        }

        $testing = file_get_contents($this->path('.env.testing.example'));

        $this->assertMatchesRegularExpression('/^LOG_CHANNEL=single\r?$/m', $testing);
    }
}
```

- [ ] **Step 6: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="ComposerManifestTest|RepositoryHygieneTest"
```
Expected: FAIL — `test_scripts_do_not_reference_sqlite_or_create_project_hooks` (`Failed asserting that an array does not have the key 'post-create-project-cmd'`), `test_setup_script_does_not_migrate_before_the_database_exists` (skrip `setup` memuat `migrate`), dan `test_example_env_files_follow_the_blueprint_reconciliation` (`.env.example harus cocok dengan /^PHP_CLI_SERVER_WORKERS=4\r?$/m`). Tes lain pada dua berkas itu tetap lulus.

- [ ] **Step 7: Rapikan `composer.json` (M-6)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
. "$env:TEMP\unpam-edit-text.ps1"

Edit-Text "$APP\composer.json" @'
            "@php artisan key:generate",
            "@php artisan migrate --force",
            "npm install",
'@ @'
            "@php artisan key:generate",
            "npm install",
'@

Edit-Text "$APP\composer.json" @'
        "post-root-package-install": [
            "@php -r \"file_exists('.env') || copy('.env.example', '.env');\""
        ],
        "post-create-project-cmd": [
            "@php artisan key:generate --ansi",
            "@php -r \"file_exists('database/database.sqlite') || touch('database/database.sqlite');\"",
            "@php artisan migrate --graceful --ansi"
        ],
        "pre-package-uninstall": [
'@ @'
        "pre-package-uninstall": [
'@

& $PHP $COMPOSER validate --no-check-publish
Select-String -Path composer.json -Pattern 'sqlite|post-create-project-cmd|post-root-package-install|migrate' | ForEach-Object { $_.Line }
```
Expected: `composer validate` melaporkan `./composer.json is valid` (lock file tidak berubah karena `scripts` tidak masuk `content-hash`); `Select-String` tidak mencetak baris apa pun.

- [ ] **Step 8: Samakan berkas contoh environment (M-3)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
. "$env:TEMP\unpam-edit-text.ps1"

Edit-Text "$APP\.env.example" 'APP_MAINTENANCE_DRIVER=file' @'
APP_MAINTENANCE_DRIVER=file
PHP_CLI_SERVER_WORKERS=4
'@
Edit-Text "$APP\.env.example" 'LOG_CHANNEL=daily' @'
LOG_CHANNEL=daily
LOG_STACK=single
'@
Edit-Text "$APP\.env.example" 'MAIL_FROM_ADDRESS="no-reply@unpamhospital.test"' 'MAIL_FROM_ADDRESS="info@unpamhospital.test"'
Edit-Text "$APP\.env.testing.example" 'LOG_CHANNEL=daily' 'LOG_CHANNEL=single'

Select-String -Path .env.example -Pattern '^(PHP_CLI_SERVER_WORKERS|LOG_CHANNEL|LOG_STACK|MAIL_FROM_ADDRESS|DEMO_PASSWORD)=' | ForEach-Object { $_.Line }
Select-String -Path .env.testing.example -Pattern '^LOG_CHANNEL=' | ForEach-Object { $_.Line }
```
Expected: `PHP_CLI_SERVER_WORKERS=4`, `LOG_CHANNEL=daily`, `LOG_STACK=single`, `MAIL_FROM_ADDRESS="info@unpamhospital.test"`, `DEMO_PASSWORD=`; lalu `LOG_CHANNEL=single`. (`.env` dan `.env.testing` milik mesin ini tidak di-commit; menyamakan `LOG_CHANNEL=single` di `.env.testing` bersifat opsional.)

- [ ] **Step 9: Jalankan tes — harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="ComposerManifestTest|RepositoryHygieneTest"
```
Expected: PASS (ComposerManifest 5, RepositoryHygiene 3).

- [ ] **Step 10: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint --test
git add composer.json .env.example .env.testing.example tests/Unit/ComposerManifestTest.php tests/Unit/RepositoryHygieneTest.php
git commit -m "chore(fase-1): rekonsiliasi env contoh dan skrip composer (M-3, M-6)"
```
Expected: `PASS`; `git status` hanya menyisakan `composer.lock` tanpa perubahan (tidak ikut commit).

**(c) Pembaruan dokumen blueprint**

Direktori blueprint dipakai sebagai `$SPEC` pada langkah di bawah: `D:\Project Web RS\docs\superpowers\specs\2026-10-02-unpam-hospital-blueprint`.

- [ ] **Step 11: 12 V-03 (F1-R5) dan 06 §14.A**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
. "$env:TEMP\unpam-edit-text.ps1"
$SPEC = "$APP\docs\superpowers\specs\2026-10-02-unpam-hospital-blueprint"

Edit-Text "$SPEC\12-aturan-bisnis-validasi-keamanan.md" @'
Gagal autentikasi (termasuk akun pasien): "Email atau password salah."<br>Akun nonaktif: "Akun Anda tidak aktif. Hubungi Super Admin." |
'@ @'
Gagal autentikasi (termasuk akun pasien dan akun nonaktif): "Email atau password salah." (pesan generik, 00 H dan AKS-08; alasan `inactive` hanya tercatat di audit `auth.staff_login_failed`)<br>Akun yang dinonaktifkan saat sesinya berjalan (middleware `active`, BR-147): "Akun Anda tidak aktif. Silakan hubungi rumah sakit." |
'@

Edit-Text "$SPEC\06-alur.md" @'
(juga untuk akun pasien); akun nonaktif: "Akun Anda tidak aktif. Hubungi Super Admin." Sukses:
'@ @'
(juga untuk akun pasien dan akun nonaktif; alasan `inactive` hanya di audit). Sukses:
'@

Select-String -Path "$SPEC\12-aturan-bisnis-validasi-keamanan.md" -Pattern 'termasuk akun pasien dan akun nonaktif' -SimpleMatch | ForEach-Object { $_.LineNumber }
Select-String -Path "$SPEC\12-aturan-bisnis-validasi-keamanan.md", "$SPEC\06-alur.md" -Pattern 'Hubungi Super Admin' -SimpleMatch | ForEach-Object { $_.Path }
```
Expected: nomor baris V-03 tercetak (satu baris); pencarian `Hubungi Super Admin` **tidak** mencetak apa pun (teks lama hilang dari 12 dan 06).

- [ ] **Step 12: 15 SEK-03 (F1-R6)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
. "$env:TEMP\unpam-edit-text.ps1"
$SPEC = "$APP\docs\superpowers\specs\2026-10-02-unpam-hospital-blueprint"

Edit-Text "$SPEC\15-pengujian-penerimaan.md" @'
Percobaan ke-6 → 429 dengan pesan Indonesia dan `Retry-After`; email lain tidak terkena; setelah 1 menit boleh lagi
'@ @'
Percobaan ke-6: pada POST → redirect 302 kembali ke form dengan flash `error` "Terlalu banyak percobaan. Silakan coba lagi dalam {n} detik." dan header `Retry-After`; pada GET → halaman 429 (11 §11.3); kunci limiter tidak membedakan huruf besar/kecil dan spasi pada email; email lain tidak terkena; setelah 1 menit boleh lagi
'@

Select-String -Path "$SPEC\15-pengujian-penerimaan.md" -Pattern 'SEK-03' | ForEach-Object { $_.Line }
```
Expected: baris SEK-03 memuat `pada POST → redirect 302 kembali ke form` dan tidak lagi memuat `Percobaan ke-6 → 429`.

- [ ] **Step 13: 11 §6.10 (F1-R7, F1-R8)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
. "$env:TEMP\unpam-edit-text.ps1"
$SPEC = "$APP\docs\superpowers\specs\2026-10-02-unpam-hospital-blueprint"
$f = "$SPEC\11-route-controller-model-service.md"

Edit-Text $f @'
public function log(string $action, ?Model $model, ?array $old, ?array $new, string $description, ?Actor $actor = null): AuditLog;
'@ @'
public function log(string $action, Model $model, ?array $old, ?array $new, string $description, ?Actor $actor = null): AuditLog;   // $model wajib: aksi tanpa satu baris mengirim instance kosong (mis. new User) ⇒ auditable_id NULL (F1-R7)
'@

Edit-Text $f @'
public function redact(array $values, string $auditableType): array;   // redaksi tingkat 1 dan 2 (12 §8.1); dipanggil log()
'@ @'
public function redact(array $values, string $action, ?Model $model = null): array;   // redaksi tingkat 1 dan 2 (12 §8.1) menurut awalan $action; atribut $model->getHidden() ikut disamarkan; dipanggil log() (F1-R7)
'@

Edit-Text $f @'
- **Konteks:** `ip_address = request()?->ip() ?? 'cli'`, `user_agent = Str::limit(request()?->userAgent() ?? 'cli', 255, '')`; `auditable_type = $model->getMorphClass()`, `auditable_id = $model->getKey()` (NULL bila `$model` NULL).
'@ @'
- **Konteks (F1-R8):** `ip_address` dan `user_agent` diisi hanya bila ada permintaan HTTP yang ter-*route* (`request()->route() !== null`; `user_agent` tanpa karakter kontrol, ≤ 255 karakter); CLI dan tes unit ⇒ NULL (09 §7.3 **[ASUMSI]** berlaku atas nilai `'cli'`); `auditable_type = $model->getMorphClass()`, `auditable_id = $model->getKey()` (NULL untuk instance kosong).
'@

Select-String -Path $f -Pattern 'Model \$model, \?array \$old', 'string \$action, \?Model \$model = null', 'Konteks \(F1-R8\)' | ForEach-Object { $_.Line.Substring(0, [Math]::Min(80, $_.Line.Length)) }
Select-String -Path $f -Pattern "?? 'cli'" -SimpleMatch | ForEach-Object { $_.LineNumber }
```
Expected: tiga baris baru tercetak; pencarian `?? 'cli'` tidak mencetak apa pun.

- [ ] **Step 14: 16 §5.1, §5.2, §7.2, §7.3 (samakan dengan berkas contoh dan vhost nyata)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
. "$env:TEMP\unpam-edit-text.ps1"
$SPEC = "$APP\docs\superpowers\specs\2026-10-02-unpam-hospital-blueprint"
$f = "$SPEC\16-instalasi-operasional.md"

# §5.1: tambah DB_TIMEZONE (ada di .env.example)
Edit-Text $f @'
DB_PASSWORD=__DB_PASSWORD__

SESSION_DRIVER=database
'@ @'
DB_PASSWORD=__DB_PASSWORD__
DB_TIMEZONE=+07:00

SESSION_DRIVER=database
'@

# §5.2: tambah DB_TIMEZONE dan FILESYSTEM_DISK (ada di .env.testing.example)
Edit-Text $f @'
DB_PASSWORD=__DB_PASSWORD__

SESSION_DRIVER=array
CACHE_STORE=array
QUEUE_CONNECTION=sync
MAIL_MAILER=array
'@ @'
DB_PASSWORD=__DB_PASSWORD__
DB_TIMEZONE=+07:00

SESSION_DRIVER=array
CACHE_STORE=array
QUEUE_CONNECTION=sync
MAIL_MAILER=array
FILESYSTEM_DISK=local
'@

# §7.2: vhost localhost + vhost proyek persis seperti httpd-vhosts.conf nyata (tanpa ServerAlias, log common)
Edit-Text $f @'
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>

<VirtualHost *:80>
    ServerName unpam-hospital.test
    ServerAlias www.unpam-hospital.test
'@ @'
        Require all granted
    </Directory>
</VirtualHost>

<VirtualHost *:80>
    ServerName unpam-hospital.test
'@
Edit-Text $f '        Options -Indexes +FollowSymLinks' '        Options FollowSymLinks'
Edit-Text $f 'CustomLog "logs/unpam-hospital-access.log" combined' 'CustomLog "logs/unpam-hospital-access.log" common'

# §7.3: berkas hosts tanpa alias www
Edit-Text $f '127.0.0.1`tunpam-hospital.test www.unpam-hospital.test' '127.0.0.1`tunpam-hospital.test'

Select-String -Path $f -Pattern 'ServerAlias', 'www.unpam-hospital', 'Options -Indexes', 'combined' -SimpleMatch | ForEach-Object { $_.Line }

# Samakan himpunan kunci: templat §5.1/§5.2 vs berkas contoh
$doc = [IO.File]::ReadAllText($f)
$keys = { param($text) [regex]::Matches($text, '(?m)^([A-Z][A-Z0-9_]*)=') | ForEach-Object { $_.Groups[1].Value } | Sort-Object }
$docEnv = [regex]::Match($doc, '(?s)\$envTemplate = @.\r?\n(.*?)\r?\n.@').Groups[1].Value
$docTest = [regex]::Match($doc, '(?s)\$testTemplate = @.\r?\n(.*?)\r?\n.@').Groups[1].Value
"selisih .env.example:"; Compare-Object (& $keys $docEnv) (& $keys ([IO.File]::ReadAllText("$APP\.env.example")))
"selisih .env.testing.example:"; Compare-Object (& $keys $docTest) (& $keys ([IO.File]::ReadAllText("$APP\.env.testing.example")))
```
Expected: `Select-String` pertama **tidak** mencetak apa pun; dua `Compare-Object` tidak mencetak selisih sama sekali setelah judulnya.

- [ ] **Step 15: 17 §4 (D-21 sampai D-28) dan 00 J.6**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
. "$env:TEMP\unpam-edit-text.ps1"
$SPEC = "$APP\docs\superpowers\specs\2026-10-02-unpam-hospital-blueprint"

Edit-Text "$SPEC\17-keputusan-konfirmasi.md" 'sebelum dipertahankan. | 09, 14 |' @'
sebelum dipertahankan. | 09, 14 |
| D-21 | Menu dan tautan layout ke route fase berikutnya | Disaring `Route::has()`; pengalihan tamu/pengguna memakai `Route::has('login')`/`Route::has('patient.dashboard')` dengan cadangan `route('home')`. Cadangan dihapus di fase 5. | Rencana fase 1 (F1-R2) |
| D-22 | Login staf dengan akun nonaktif | Pesan generik "Email atau password salah." (bukan pesan khusus); alasan `inactive` hanya di audit `auth.staff_login_failed`. | 00 H, 12 V-03 (F1-R5) |
| D-23 | Throttle pada permintaan POST | Redirect 302 kembali ke form + flash `error` "Terlalu banyak percobaan. Silakan coba lagi dalam {n} detik." + header `Retry-After`; pada GET halaman 429. Berlaku untuk semua route POST ber-throttle (`login`, `register`, `password-reset`, `booking`, `contact`); baris "429" untuk POST di 06 dibaca sebagai respons ini. | 11 §11.3, 15 SEK-03 (F1-R6) |
| D-24 | Signature `AuditLogger` | `log()` menerima `Model $model` wajib (instance kosong untuk aksi tanpa baris); `redact(array $values, string $action, ?Model $model = null)`; `ip_address`/`user_agent` NULL di luar permintaan HTTP ter-*route*. | 11 §6.10 (F1-R7, F1-R8) |
| D-25 | Seeder demo fase 1 | `DemoSeeder` + `DemoUserSeeder` hanya tiga akun staf; akun pasien demo menyusul di fase 5; password dari `config('app.demo_password')` (`DEMO_PASSWORD`, kosong = `Demo12345`). | 13 §2.3, §3 (F1-R10) |
| D-26 | Cakupan `media:prune` fase 1 | (a) baris `model_id` NULL > 24 jam kecuali logo situs, (b) berkas tak tercatat > 1 jam; butir pemilik hilang menunggu model pemilik di fase 2. | 11 §6.9 (F1-R12) |
| D-27 | Detail pesan halaman 403 | Pesan `UnauthorizedException` Spatie dan pesan bawaan framework (berbahasa Inggris) tidak pernah ditampilkan sebagai `$detail`; halaman 403 memakai teks Indonesia generik. | 11 §11.3, 15 ERR-02 |
| D-28 | Endpoint media admin (F-ADM-29) | Ditunda dari fase 1 ke fase 2 (bersama model pemilik pertama); `MediaService` sudah tersedia sejak fase 1. | 14 §4 (F1-R21) |
'@

Edit-Text "$SPEC\00-keputusan-inti.md" '- **Pengujian:** folder' @'
- **Nama tambahan fase 1:** `Support\NavigationMenu::items(string $area, ?User $user = null): array`, `Support\SafeRedirect::internalPath(?string $value): ?string`, `Http\Requests\ListFilterRequest::perPage(int $default = 25): int`, `Rules\ImageRules::messages(string $field, string $collection): array`, `Media\MediaPruneReport::$items` (parameter ke-5 opsional), `lang/id/roles.php` (label peran), `resources/scss/_shared.scss` (partial gaya bersama), `config('app.demo_password')` (kunci `DEMO_PASSWORD`), `RolePermissionSeeder::PERMISSIONS`/`::ROLES`, `SettingsSeeder::DEFAULTS`, `DemoUserSeeder` (tiga akun staf) beserta konstanta `DemoUserSeeder::ACCOUNTS`, state `UserFactory::staff()/patient()/inactive()/mustChangePassword()/withRole()`, konstanta `Rules\ImageRules::INVALID_IMAGE/NOT_IMAGE/WRONG_TYPE/UPLOAD_FAILED`, partial `resources/scss/_bootstrap-theme.scss` (tumpukan impor Bootstrap bersama), prop opsional `noindex` pada `x-layouts.public`, `storage/app/public/.htaccess` (penolak skrip), serta pembantu tes `Tests\Support\InteractsWithStaff`, `Tests\Support\InteractsWithMedia`, `Tests\Support\InteractsWithFormState`, dan `Tests\Support\ImageFixtures`.
- **Pengujian:** folder
'@

Select-String -Path "$SPEC\17-keputusan-konfirmasi.md" -Pattern '^\| D-2[1-8] \|' | ForEach-Object { $_.Line.Substring(0, 40) }
Select-String -Path "$SPEC\00-keputusan-inti.md" -Pattern 'Nama tambahan fase 1' -SimpleMatch | ForEach-Object { $_.LineNumber }
```
Expected: delapan baris `| D-21 |` … `| D-28 |` tercetak; satu nomor baris untuk "Nama tambahan fase 1".

- [ ] **Step 16: Verifikasi seluruh pembaruan dokumen, lalu commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
$SPEC = "docs\superpowers\specs\2026-10-02-unpam-hospital-blueprint"
git diff --stat -- $SPEC
git status --short
git add "$SPEC\00-keputusan-inti.md" "$SPEC\06-alur.md" "$SPEC\11-route-controller-model-service.md" "$SPEC\12-aturan-bisnis-validasi-keamanan.md" "$SPEC\15-pengujian-penerimaan.md" "$SPEC\16-instalasi-operasional.md" "$SPEC\17-keputusan-konfirmasi.md"
git commit -m "docs(fase-1): rekonsiliasi blueprint (V-03, SEK-03, AuditLogger, env/vhost, D-21..D-28, nama baru)"
```
Expected: `git diff --stat` menampilkan tujuh berkas dokumen berubah (perubahan kecil per berkas); `git status` tidak memuat berkas lain yang belum di-stage selain yang disengaja; commit dibuat.

**(d) README bagian "Fase 1"**

- [ ] **Step 17: Perbarui `README.md` (tool Write; isi lama dipertahankan, bagian "Fase 1" ditambahkan di akhir)**

Isi berkas = seluruh teks di antara pagar enam-backtick berikut (pagar luar tidak ikut ditulis). Baca `README.md` lebih dulu dengan Read.

``````markdown
# UNPAM HOSPITAL

Prototipe website rumah sakit: situs publik, portal pasien (pendaftaran kunjungan rawat jalan), dan dashboard admin.
Laravel 12 + Blade + Bootstrap 5, MariaDB 10.4 melalui XAMPP 8.2.12 (Windows, localhost). Seluruh data adalah data contoh fiktif.

- Blueprint (sumber kebenaran): [`docs/superpowers/specs/2026-10-02-unpam-hospital-blueprint/`](docs/superpowers/specs/2026-10-02-unpam-hospital-blueprint/README.md)
- Rencana implementasi per fase: [`docs/superpowers/plans/`](docs/superpowers/plans/)
- Panduan instalasi lokal: blueprint bagian 16.

## Setup pertama (mesin baru)

Rincian lengkap ada di blueprint bagian 16; ringkasnya, jalankan dari PowerShell (variabel `$PHP`/`$COMPOSER` seperti pada "Perintah harian"):

1. `& $PHP scripts\check-env.php` harus berakhir OK (blueprint 16 §2).
2. `& $PHP $COMPOSER install`, lalu `npm install` dan `npm run build` (16 §3).
3. Buat database + user `unpam_app`, lalu salin `.env.example` → `.env` dan `.env.testing.example` → `.env.testing` dengan `DB_PASSWORD` yang sama dengan password user DB (16 §4–§5).
4. `& $PHP artisan key:generate` dan `& $PHP artisan key:generate --env=testing` (16 §5).
5. `& $PHP artisan migrate` dan `& $PHP artisan migrate --env=testing`, lalu `& $PHP artisan storage:link` (16 §6). Mulai fase 1, gunakan `& $PHP artisan migrate --seed` untuk data awal (lihat "Fase 1" di bawah).
6. Pasang VirtualHost Apache dan entri `hosts` untuk `unpam-hospital.test` (16 §7).

## Perintah harian (PowerShell)

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"
$env:Path = "$XAMPP\php;$env:Path"; Set-Location "D:\Project Web RS"

& $PHP scripts\check-env.php        # cek PHP & ekstensi XAMPP
& $PHP artisan test                 # suite tes (database unpam_hospital_test)
& $PHP vendor/bin/pint --test       # format kode
npm run build                       # build aset Vite
& $PHP $COMPOSER install            # jangan memakai composer.bat (karakter ^ hilang)
```

URL lokal: <http://unpam-hospital.test> (alternatif `php artisan serve` → <http://127.0.0.1:8000>).

## Fase 1 — Fondasi (akun, peran, audit, media, pengaturan, area admin)

Yang tersedia: login/logout staf di <http://unpam-hospital.test/admin/login>, ganti password wajib untuk akun sementara, dasbor admin sementara (sapaan), peran & izin (Super Admin, Admin Konten, Petugas Pendaftaran), audit log, media, pengaturan situs, dan komponen tampilan dasar. Halaman admin lain menyusul di fase berikutnya.

### Data awal (seeding)

```powershell
& $PHP artisan migrate:fresh --seed   # LOKAL SAJA: menghapus semua tabel dan isi database unpam_hospital
```

Hasil: 3 peran, 19 izin, 19 pengaturan situs, dan 3 akun staf demo. Seeder demo hanya berjalan di `APP_ENV=local`/`testing`; di produksi `db:seed` hanya menjalankan seeder inti dan mencetak peringatan.

| Peran | Email | Password |
|---|---|---|
| Super Admin | `superadmin@unpamhospital.test` | `Demo12345` |
| Admin Konten | `konten@unpamhospital.test` | `Demo12345` |
| Petugas Pendaftaran | `pendaftaran@unpamhospital.test` | `Demo12345` |

Password demo dapat diganti lewat `DEMO_PASSWORD` di `.env` (kosong = `Demo12345`). Akun ini adalah kredensial publik: **jangan** dipakai di produksi. Akun pasien demo menyusul di fase 5.

### Super Admin pertama di produksi

Tanpa seeder demo. Setelah `migrate --force` dan `db:seed --force`:

```powershell
& $PHP artisan app:create-super-admin   # interaktif: nama, email, password (tersembunyi) + konfirmasi
```

Akun yang dibuat wajib mengganti password pada login pertama dan tercatat di audit (`staff.created`, pelaku sistem). Mode non-interaktif: `--name=`, `--email=`, `--password=` (ketiganya wajib; opsi `--password` terekam di riwayat shell, hindari di produksi).

### Pembersihan media

```powershell
& $PHP artisan media:prune --dry-run   # hanya melaporkan baris/berkas yatim; tidak ada yang dihapus
& $PHP artisan media:prune             # menghapus baris yatim > 24 jam dan berkas tak tercatat > 1 jam
```

### Pemeriksaan cepat

```powershell
& $PHP artisan route:list --path=admin   # route admin fase 1
& $PHP artisan test                      # seluruh suite
```
``````

- [ ] **Step 18: Verifikasi README dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
Select-String -Path README.md -Pattern '## Fase 1', 'app:create-super-admin', 'media:prune --dry-run', 'superadmin@unpamhospital.test' -SimpleMatch | ForEach-Object { $_.Line }
& $PHP artisan test --filter="RepositoryHygieneTest|ComposerManifestTest"
git add README.md
git commit -m "docs(fase-1): README bagian Fase 1 (seeding, akun demo, create-super-admin, media:prune)"
```
Expected: empat pola tercetak; tes PASS; commit dibuat.

**(e) Verifikasi gerbang fase 1**

- [ ] **Step 19: Suite penuh, Pint, build aset**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test
& $PHP vendor\bin\pint --test
npm run build
Select-String -Path public\build\manifest.json -Pattern 'resources/scss/app.scss', 'resources/js/app.js', 'resources/js/admin.js' -SimpleMatch | ForEach-Object { $_.Line.Trim() }
git status --short
```
Expected: semua tes PASS tanpa `RISKY`/`WARNING` (catat jumlah tes dari baris `Tests: N passed`); Pint `PASS`; `npm run build` sukses tanpa galat (peringatan `mixed-decls deprecation is obsolete` boleh muncul, lihat Task 9 Step 10); manifest memuat tiga entri (F1-R11; jangan mengandalkan field `src` untuk SCSS, lihat carry fase 0); `git status` bersih (tanpa `.env`, `vendor`, `node_modules`, `public/build`, `public/storage`).

- [ ] **Step 20: `migrate:fresh --seed` pada database pengembangan (kasus INS bagian seeding)**

Peringatan: perintah ini menghapus seluruh tabel dan isi database **pengembangan** `unpam_hospital` (bukan database uji).

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan migrate:fresh --seed --ansi
& $PHP artisan tinker --execute="echo json_encode(['roles' => Spatie\Permission\Models\Role::count(), 'permissions' => Spatie\Permission\Models\Permission::count(), 'settings' => App\Models\WebsiteSetting::count(), 'staff' => App\Models\User::where('account_type', 'staff')->count(), 'patients' => App\Models\User::where('account_type', 'patient')->count()]);"
```
Expected: semua migration `DONE`; seeder `RolePermissionSeeder`, `SettingsSeeder`, `DemoSeeder`, `DemoUserSeeder` `DONE`; konsol mencetak `MODE DEMO: akun & data contoh dibuat…` dan tabel tiga akun demo; keluaran tinker memuat `{"roles":3,"permissions":19,"settings":19,"staff":3,"patients":0}`.

- [ ] **Step 21: Daftar route admin dan `media:prune --dry-run`**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan route:list --path=admin --except-vendor
& $PHP artisan media:prune --dry-run; "exit=$LASTEXITCODE"
```
Expected: `Showing [6] routes` — `GET|HEAD admin` (`admin.dashboard`), `GET|HEAD admin/ganti-password` (`admin.password.edit`), `PUT admin/ganti-password` (`admin.password.update`), `GET|HEAD admin/login` (`admin.login`), `POST admin/login` (`admin.login.store`), `POST admin/logout` (`admin.logout`); `media:prune --dry-run` mencetak jumlah baris/berkas yatim (0 pada database baru), kalimat `Mode uji: tidak ada yang dihapus.`, dan `exit=0`.

- [ ] **Step 22: MANUAL (pemilik) — login per peran, ganti password wajib, logout**

Implementer **berhenti di langkah ini**, melapor `NEEDS_CONTEXT` beserta instruksi berikut kepada pemilik, lalu melanjutkan setelah pemilik mengonfirmasi hasilnya.

Prasyarat: `C:\xampp3\xampp-control.exe` → Apache dan MySQL **Start**; Step 20 sudah dijalankan (database berisi akun demo).

1. Buka `http://unpam-hospital.test/admin/login` (halaman login tampil, ada skip link, fokus awal pada email).
2. Login sebagai ketiga akun (password `Demo12345`), satu per satu (keluar dulu antar-akun):
   - `superadmin@unpamhospital.test` → dasbor memuat "Selamat datang, Super Admin Demo." dan label peran "Super Admin".
   - `konten@unpamhospital.test` → "Selamat datang, Admin Konten Demo." dan "Admin Konten".
   - `pendaftaran@unpamhospital.test` → "Selamat datang, Petugas Pendaftaran Demo." dan "Petugas Pendaftaran".
3. Cek sidebar: pada fase 1 hanya item **Dasbor** yang muncul untuk ketiga peran (item lain tersaring `Route::has()` sampai routenya ada); menu pengguna (pojok kanan atas) memuat nama, peran, "Ganti Password", dan "Keluar"; tombol "Lihat Situs" membuka tab baru; < 992 px sidebar menjadi offcanvas.
4. Salah password sekali → pesan "Email atau password salah." (sama untuk email tak terdaftar). Enam percobaan gagal beruntun dalam satu menit → flash "Terlalu banyak percobaan. Silakan coba lagi dalam {n} detik."
5. Buat akun uji `must_change_password`: di PowerShell (Prelude P) jalankan `& $PHP artisan app:create-super-admin` dan isi nama `Uji Manual`, email `uji.manual@unpamhospital.test`, password `Sementara123` (dua kali). Login dengan akun itu → langsung diarahkan ke halaman **Ganti Password** dengan pesan "Anda harus mengganti password sementara sebelum melanjutkan." (membuka `/admin` langsung juga kembali ke sana). Ganti password menjadi `BaruAman456` → dasbor dengan flash "Password berhasil diganti."
6. Klik **Keluar** → kembali ke login dengan flash "Anda telah keluar." Buka `/admin` → diarahkan ke login.
7. Pemulihan (opsional): `& $PHP artisan migrate:fresh --seed` agar database pengembangan hanya berisi akun demo.

Expected: semua butir 1–6 sesuai; lapor hasil per butir. Kasus manual yang tertutup: INS-03 (`/admin/login` tampil lewat VirtualHost), AKS-09/AKS-10 secara manual.

**(f) Catatan Eksekusi dan CHECKPOINT**

- [ ] **Step 23: Tambahkan templat "Catatan Eksekusi" ke rencana fase 1 (diisi koordinator)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
$plan = "$APP\docs\superpowers\plans\2026-10-03-fase-1-fondasi.md"
if (-not (Test-Path $plan)) { throw "Rencana fase 1 belum ada di $plan" }
if (Select-String -Path $plan -Pattern '^## Catatan Eksekusi' -Quiet) { throw "Catatan Eksekusi sudah ada, jangan digandakan." }
$tgl = Get-Date -Format 'yyyy-MM-dd'
$template = @'


---

## Catatan Eksekusi (@@TANGGAL@@)

Dieksekusi dengan: _(koordinator: metode, mis. subagent-driven-development, model implementer/reviewer)_. Cabang `fase-1-fondasi`: _(jumlah)_ commit (`<hash awal>`..`<hash akhir>`). Hasil akhir: _(jumlah tes lulus, status `pint --test`, `npm run build`, `migrate:fresh --seed`, hasil langkah MANUAL Task 19 Step 22)_.

### Keputusan (rulings) selama eksekusi

| ID | Keputusan | Biaya bila keliru |
|---|---|---|
| R1 | _(isi koordinator)_ | _(isi)_ |

### Bawaan (carry) untuk rencana fase berikutnya

- **Fase 2:** _(isi koordinator; usulan dari rencana: endpoint media F-ADM-29 (`MediaController`, `MediaPolicy`, `admin.media.*`, V-42; F1-R21), format tanggal Indonesia (`translatedFormat`, locale Carbon `id`), `HasMedia`/`SanitizesRichText`, `AuditObserver`, butir (c) `media:prune` setelah ada model pemilik, `lang/id/messages.php`, advisori npm `quill@2.0.3` GHSA-v3m3-f69x-jf25)_
- **Fase 3–4:** _(isi koordinator; usulan: `module-tabs`/`availability-badge`/`doctor-card`/`schedule-table`, `HomeController` menggantikan `Route::view('/')`, `PageSeeder` ke `DatabaseSeeder`, `form.rich-text` + `editor.js`, `map-embed`)_
- **Fase 5:** _(isi koordinator; usulan: hapus cadangan `Route::has('login')`/`Route::has('patient.dashboard')` (F1-R2), `passwords.throttled` ke pesan generik, `lang/id.json`, `routes/patient.php`, akun pasien demo di `DemoUserSeeder`)_
- **Fase 6 dan 8:** _(isi koordinator; usulan: handler `BookingException`, `SettingsService::bookingRules()`, `syncRolePermissions` + `RolePolicy` + `Route::bind('user')`)_
- **Lain-lain:** _(isi koordinator; mis. temuan tinjauan, baris "429" untuk POST di 06 dibaca sesuai D-23)_
'@
$template = $template.Replace('@@TANGGAL@@', $tgl)
[IO.File]::AppendAllText($plan, $template, (New-Object System.Text.UTF8Encoding $false))
Select-String -Path $plan -Pattern '^## Catatan Eksekusi', '^### Keputusan \(rulings\) selama eksekusi', '^### Bawaan \(carry\)' | ForEach-Object { $_.Line }
```
Expected: tiga judul tercetak (`## Catatan Eksekusi (tanggal)`, `### Keputusan (rulings) selama eksekusi`, `### Bawaan (carry) untuk rencana fase berikutnya`). Koordinator mengisi bagian bertanda `_(isi …)_` sebelum commit akhir (Step 25).

- [ ] **Step 24: Perbarui `docs/superpowers/CHECKPOINT.md` (tool Write, timpa; baca berkas lama dengan Read lebih dulu)**

Isi berkas = seluruh teks di antara pagar enam-backtick berikut. Token `@@TANGGAL@@` diganti tanggal hari ini oleh perintah setelahnya.

``````markdown
# Checkpoint — UNPAM HOSPITAL

**Terakhir diperbarui:** @@TANGGAL@@ (akhir fase 1) · **Tag git:** `checkpoint/@@TANGGAL@@-fase-1`

Dokumen ini adalah titik lanjut untuk sesi berikutnya. Baca dari atas ke bawah sebelum bekerja.

## 1. Posisi saat ini

| Hal | Status |
|---|---|
| Blueprint (spesifikasi) | Selesai & disetujui — `docs/superpowers/specs/2026-10-02-unpam-hospital-blueprint/` (mulai dari `README.md`, lalu `00-keputusan-inti.md`). Diperbarui di fase 1: 00 J.6, 06 §14.A, 11 §6.10, 12 V-03, 15 SEK-03, 16 §5/§7, 17 §4 (D-21–D-28). |
| Fase 0 — Lingkungan & skeleton | **Selesai.** Rencana + catatan eksekusi: `docs/superpowers/plans/2026-10-02-fase-0-lingkungan-skeleton.md`. |
| Fase 1 — Fondasi | **Selesai**, gerbang fase terpenuhi (tes, Pint, `npm run build`, `migrate:fresh --seed`, login manual tiga akun demo). Rencana + catatan eksekusi: `docs/superpowers/plans/2026-10-03-fase-1-fondasi.md`. |
| Cabang git | `fase-1-fondasi` (dibuat dari `master` setelah merge fase 0), **belum di-merge**. Repo tanpa remote. |
| Keputusan tertunda pemilik | **Integrasi cabang fase 1**: (1) merge ke `master` lokal, (2) push + PR (butuh remote), atau (3) biarkan dulu. Lalu konfirmasi butir K di `17-keputusan-konfirmasi.md` (semua punya default; terpenting sebelum fase 5: K-07). |
| Fase berikutnya | **Fase 2 — Master Data** (blueprint `14-tahapan-implementasi.md` §Fase 2); fase 4a (CMS statis) dan fase 5 (akun pasien) juga hanya membutuhkan fondasi. Belum ada rencana implementasinya. |

Yang sudah ada di aplikasi: enum, akun & peran (Spatie), audit log, media + sanitasi HTML, pengaturan situs, tema & komponen Blade, layout publik/pasien/auth/admin, login/logout staf, ganti password wajib, header keamanan, `StaffAccountService` (aturan mutlak Super Admin), `app:create-super-admin`, `media:prune`, seeder demo staf. Area admin fase 1 = login, ganti password, dasbor sapaan (`route:list --path=admin` = 6 route).

## 2. Lingkungan (mesin pemilik)

- XAMPP 8.2.12 di **`C:\xampp3`** (bukan `C:\xampp`, itu sisa lama). Apache + MySQL harus **Start** di `C:\xampp3\xampp-control.exe` sebelum bekerja.
- PHP runtime 8.2.12 (`C:\xampp3\php\php.exe`); PHP 8.4 di PATH **jangan** dipakai untuk proyek. Composer selalu `& $PHP C:\composer\composer.phar …` (bukan `composer.bat`, karakter `^` hilang).
- Database `unpam_hospital` (dev) dan `unpam_hospital_test` (uji), user `unpam_app` — password hanya di `.env`/`.env.testing` (git-ignored, jangan dihapus).
- URL: <http://unpam-hospital.test> (VirtualHost; cadangan konfigurasi `httpd-vhosts.conf.bak-fase0`), alternatif `php artisan serve` → <http://127.0.0.1:8000>. Login admin: <http://unpam-hospital.test/admin/login>.
- Akun demo (hanya lokal, password `Demo12345` atau `DEMO_PASSWORD`): `superadmin@`, `konten@`, `pendaftaran@unpamhospital.test`. Dibuat oleh `php artisan migrate:fresh --seed` (menghapus seluruh isi database pengembangan).
- Catatan: tabel sistem MariaDB XAMPP pernah rusak (`mysql.db` + log Aria); sudah diperbaiki. Bila hak akses DB hilang lagi → blueprint 16 §13 baris "`GRANT` tidak tersimpan".

## 3. Cara melanjutkan (PowerShell)

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"
$env:Path = "$XAMPP\php;$env:Path"; Set-Location "D:\Project Web RS"

git status; git branch --show-current          # harus bersih, di fase-1-fondasi
& $PHP scripts\check-env.php                    # "status": "OK"
& $PHP artisan test                             # semua hijau
& $PHP vendor\bin\pint --test                   # PASS
& $PHP artisan route:list --path=admin          # 6 route
curl.exe -s -o NUL -w '%{http_code}' http://unpam-hospital.test/admin/login   # 200
```

Bila semua hijau: putuskan integrasi cabang fase 1, lalu minta Claude menulis rencana **Fase 2** dengan skill `superpowers:writing-plans`, dieksekusi dengan `superpowers:subagent-driven-development` memakai subagent **Sonnet 5.5**.

## 4. Bawaan (carry) untuk fase berikutnya

Ringkas — rincian di bagian "Catatan Eksekusi" rencana fase 0 dan fase 1:
- **Fase 2:** endpoint media F-ADM-29 (`MediaController`, `MediaPolicy`, `admin.media.*`, V-42; ditunda F1-R21/D-28); format tanggal Indonesia (`translatedFormat`); `HasMedia`/`SanitizesRichText`, `AuditObserver`, butir (c) `media:prune` (pemilik hilang), `lang/id/messages.php`; advisori `quill@2.0.3` (GHSA-v3m3-f69x-jf25) — wajib sanitasi `HtmlSanitizer` saat simpan.
- **Fase 3–4:** `module-tabs`, `availability-badge`, `doctor-card`, `schedule-table` (3); `HomeController` menggantikan `Route::view('/', 'public.home')` (F1-R3), `PageSeeder` ditambahkan ke `DatabaseSeeder`, `form.rich-text` + `editor.js` (4a); `map-embed` (4b).
- **Fase 5:** hapus cadangan `Route::has('login')`/`Route::has('patient.dashboard')` pada `redirectGuestsTo`/`redirectUsersTo`/`EnsureAccountIsActive`/menu (F1-R2); petakan `passwords.throttled` ke pesan generik `passwords.sent` (cegah enumerasi email); `lang/id.json`, `routes/patient.php`; akun pasien demo `pasien@unpamhospital.test` ke `DemoUserSeeder`.
- **Fase 6:** handler `BookingException`, `SettingsService::bookingRules()`.
- **Fase 8:** `StaffAccountService::syncRolePermissions`, `RolePolicy`, `Route::bind('user')`; widget dasbor menggantikan sapaan sementara (F1-R4).
- **Lain-lain:** `.env.testing` milik mesin ini boleh disamakan dengan `.env.testing.example` (`LOG_CHANNEL=single`); baris "429" untuk POST di dokumen 06 dibaca sesuai D-23 (tidak diubah satu per satu).

## 5. Riwayat sesi

1. 2026-10-02 — Brainstorming → blueprint 17 bagian + `00-keputusan-inti.md`; rencana fase 0 → eksekusi → 26 tes hijau; insiden `mysql.db`/Aria XAMPP diperbaiki.
2. @@TANGGAL@@ — Rencana fase 1 (19 tugas) → eksekusi → gerbang fase 1 terpenuhi; blueprint direkonsiliasi (D-21–D-28); M-3/M-6 dari fase 0 ditutup.
``````

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
$cp = "$APP\docs\superpowers\CHECKPOINT.md"
$tgl = Get-Date -Format 'yyyy-MM-dd'
$enc = New-Object System.Text.UTF8Encoding $false
[IO.File]::WriteAllText($cp, [IO.File]::ReadAllText($cp, $enc).Replace('@@TANGGAL@@', $tgl), $enc)
Select-String -Path $cp -Pattern '@@' -SimpleMatch
Select-String -Path $cp -Pattern 'Terakhir diperbarui', 'akhir fase 1', 'fase-1-fondasi' | ForEach-Object { $_.Line }
```
Expected: pencarian `@@` tidak mencetak apa pun; tiga pola tercetak dengan tanggal hari ini.

- [ ] **Step 25: Isi Catatan Eksekusi, commit akhir, tag**

Koordinator mengisi templat Step 23 (keputusan selama eksekusi, jumlah commit/tes, carry) terlebih dahulu. Lalu:

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
Select-String -Path docs\superpowers\plans\2026-10-03-fase-1-fondasi.md -Pattern '_\(isi' | Measure-Object | ForEach-Object { "templat belum terisi: $($_.Count) baris" }
& $PHP artisan test
& $PHP vendor\bin\pint --test
git add docs/superpowers/plans/2026-10-03-fase-1-fondasi.md docs/superpowers/CHECKPOINT.md
git status --short
git commit -m "docs(fase-1): catatan eksekusi, keputusan, dan checkpoint akhir fase 1"
$tgl = Get-Date -Format 'yyyy-MM-dd'
git tag "checkpoint/$tgl-fase-1"
git log --oneline -30
git tag --list "checkpoint/*"
```
Expected: `templat belum terisi: 0 baris` (bila masih ada, isi dulu); seluruh tes PASS; Pint `PASS`; `git status` hanya memuat dua berkas dokumen; commit dibuat; tag `checkpoint/<tanggal>-fase-1` muncul di samping `checkpoint/2026-10-02-fase-0`; `git log` menampilkan commit fase 1 (Task 1–19) di atas commit fase 0.

Laporan gerbang fase 1 ke koordinator: keluaran Step 19–21, hasil langkah MANUAL Step 22, dan status kasus:
- `AKS-01`–`AKS-05` (baris 31/31b/32): lulus bila `AdminPageMatrixTest` hijau (15 kasus).
- `INS-02`: lulus dengan `migrate --seed` (tiga akun demo) tanpa unggah gambar; `INS-03`: `/admin/login` tampil lewat VirtualHost (Step 22 butir 1).
- `STF-03`, `STF-07`, `SEED-02`: lulus bila `StaffAccountServiceTest`, `SeederProductionGuardTest`, `CreateSuperAdminTest` hijau.
