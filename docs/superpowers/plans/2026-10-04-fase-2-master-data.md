# Fase 2 — Master Data: Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fase ini menyediakan data induk **spesialisasi, poliklinik/layanan, dan dokter**. Cakupannya:
- CRUD admin (halaman #33 dan #34), termasuk foto, status, dan arsip dokter;
- editor rich text dan endpoint media admin;
- halaman publik **Daftar Layanan** (#4) dan **Detail Layanan** (#5);
- seeder demo.

Data ini menjadi dasar penjadwalan (fase 3) dan booking (fase 6).

**Architecture:** Arsitektur fase 1 tetap berlaku: monolit Laravel 12, controller tipis, Form Request, Policy per model, aturan bisnis di `app/Services`, dan `Actor` sebagai pelaku. Perilaku lintas model ditaruh di trait model `App\Models\Concerns\{HasMedia, SanitizesRichText, HasUniqueSlug}`. Audit CRUD sederhana (spesialisasi, poliklinik) dicatat `App\Observers\AuditObserver`. Aksi domain dokter dicatat eksplisit oleh `DoctorService`. Tautan ke route fase berikutnya tetap disaring `Route::has()` (F1-R2), sehingga aplikasi hijau di akhir fase.

**Tech Stack:** PHP 8.2.12 (XAMPP `C:\xampp3`), Laravel 12.69, MariaDB 10.4.32, spatie/laravel-permission 6.25, intervention/image 3.11 (GD), mews/purifier 3.4 (HTMLPurifier 4.19), **quill 2.0.3** (sudah ada di `package.json`), Bootstrap 5.3 + Sass + Vite 7, PHPUnit 11, Laravel Pint.

**Spec:** `docs/superpowers/specs/2026-10-02-unpam-hospital-blueprint/`. Bagian yang terutama dipakai:

| Dokumen | Bagian |
|---|---|
| `00-keputusan-inti.md` | C, E, F.4, G, I, J, K |
| `14-tahapan-implementasi.md` | §5 Fase 2 |
| `09-data-dictionary.md` | §3.1–3.4, §10, §15 |
| `11-route-controller-model-service.md` | §1–§5, §8, §9 |
| `12-aturan-bisnis-validasi-keamanan.md` | §3, V-19–V-22, V-42, V-45, BR-03–BR-15, §8 |
| `04-peran-hak-akses.md` | §2, §4, §5 |
| `05-sitemap-matriks-halaman.md` | hal. 4, 5, 33, 34 |
| `07-wireframe.md` | W-06, W-15, W-16 |
| `10-struktur-folder.md` | — |
| `13-data-contoh.md` | §4–6, §9 |
| `15-pengujian-penerimaan.md` | CRD-01–CRD-04, MED-01, MED-02, MED-08, VAL-01, AKS |

Ringkasan kutipan spesifikasi untuk fase ini ada di `.superpowers/plan-fase-2/digest-{data,http,tests-carry}.md`. Berkas itu hanya bahan penulisan dan tidak ikut di-commit.

## Global Constraints

- **XAMPP dan Prelude P.** XAMPP ada di **`C:\xampp3`** (PHP 8.2.12). Folder `C:\xampp` lama **jangan** dipakai. Apache dan MySQL harus di-**Start** di `C:\xampp3\xampp-control.exe` sebelum tes. **Setiap perintah PowerShell diawali Prelude P**:
  `$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP`
- **Perintah dan paket.** PHP selalu dijalankan `& $PHP …`, Composer selalu `& $PHP $COMPOSER …`. **Tidak ada paket Composer/npm baru**; `quill` sudah terpasang.
- **Kompatibilitas PHP 8.2–8.4.** **Dilarang** memakai fitur khusus 8.3+: typed class constants, `#[\Override]`, `json_validate()`, property hooks, `new` tanpa kurung pada chaining. Berkas baru di `app/Services`, `app/Support`, `app/Observers`, dan `app/Models/Concerns` diawali `declare(strict_types=1);`.
- **Nama.** Nama tabel, kolom, enum, route, izin, Form Request, Policy, service, dan method **persis** seperti 00/09/10/11/12. Nama baru hanya yang tercantum di "Nama baru yang diperkenalkan fase 2".
- **Bahasa.** Identifier kode berbahasa Inggris. **Semua teks UI, pesan validasi, flash, dan deskripsi audit berbahasa Indonesia**; flash berupa kalimat lengkap berakhir titik.
- **Mode ketat.** `Model::shouldBeStrict()` aktif di non-produksi. Semua relasi yang dipakai view atau accessor (`media`, `specialties`, `clinic`, `doctors_count`) wajib di-*eager load*. Tes daftar memakai beberapa baris sekaligus agar *lazy loading* terdeteksi.
- **Database tes.** Tes memakai MariaDB `unpam_hospital_test` dengan `RefreshDatabase`. Tes media dan seeder memakai `Storage::fake('public')`. Peran dibuat dengan `Tests\Support\InteractsWithStaff::seedRoles()`/`createStaff()`.
- **Migration.** **Jangan** mengubah migration yang sudah ada. Migration fase 2 memakai nama 09 §10 persis: `2026_10_02_000200_create_specialties_table`, `2026_10_02_000300_create_clinics_table`, `2026_10_02_000400_create_doctors_table`, `2026_10_02_000500_create_doctor_specialties_table`.
- **Output HTML.**
  - `{!! !!}` **hanya** boleh untuk `clinics.description` di `public/clinics/show.blade.php`, karena nilainya sudah disanitasi `HtmlSanitizer` saat simpan.
  - Tidak ada atribut event inline (`onclick`, `onerror`, …).
  - Tidak ada CDN, font, atau ikon eksternal.
  - CSP `img-src 'self' data:` tidak boleh dilonggarkan (tanpa `blob:`).
- **Penulisan berkas.**
  - Pakai tool Write/Edit.
  - Repo memakai akhiran baris **LF** (`.gitattributes` `eol=lf`). Pastikan setiap `old` pada langkah Edit cocok tepat satu kali. `Edit-Text` dari rencana fase 1, Task 18 Step 1, boleh dipakai bila lewat PowerShell, tetapi tidak wajib.
  - Bila menulis lewat PowerShell: `[IO.File]::WriteAllText(path, text, (New-Object System.Text.UTF8Encoding $false))` (tanpa BOM).
  - `.env` dan `.env.testing` **tidak pernah** di-commit.
- **Cabang dan commit.**
  - Cabang kerja: **`fase-2-master-data`** dari `master` (sudah dibuat).
  - Pesan commit: `tipe(fase-2): ringkasan`, dengan tipe `feat`, `test`, `chore`, `docs`, `refactor`, atau `fix`.
  - Satu tugas = satu commit atau lebih. Di setiap commit `artisan test` hijau dan `vendor\bin\pint --test` bersih.
  - Bila menyentuh `resources/js` atau `resources/scss`, `npm run build` juga harus sukses.
- **Langkah MANUAL (pemilik).** Langkah bertanda **MANUAL (pemilik)** butuh tindakan pemilik. Implementer berhenti di langkah itu, melapor `NEEDS_CONTEXT` beserta instruksinya, lalu melanjutkan setelah dikonfirmasi.

## Review Focus

1. **Nama yang menghasilkan slug bentrok atau aneh.** Contohnya dua dokter "Andi Pratama", nama "dr. Siti Nur'aini, Sp.A", dan huruf non-ASCII. Harapan: slug unik dengan sufiks `-2`/`-3`, tidak pernah kosong, dan tidak berubah saat nama diedit. → Task 2 (`HasUniqueSlugTest`).
2. **Halaman daftar admin dan publik dengan banyak baris di mode ketat.** Harapan: dokter dengan `specialties`/`clinic`/`media` dan poliklinik dengan `doctors_count`/`media` dirender 200 tanpa `LazyLoadingViolationException`: daftar dokter untuk ketiga peran, sedangkan daftar poliklinik dan spesialisasi (#34) untuk Super Admin dan Admin Konten (Petugas mendapat 403). → Task 10 dan Task 11 (tes daftar admin di masing-masing task), Task 13 (`PublicClinicPagesTest`, `PublicClinicDetailTest`).
3. **Rich text berbahaya atau masuk lewat jalur selain form.** Contohnya `<script>`, `onerror`, gambar `data:`/eksternal, dan nilai dari seeder atau `update()` langsung. Harapan: yang tersimpan di DB selalu hasil `HtmlSanitizer::clean()`, dan halaman publik hanya merender nilai tersanitasi. → Task 2 (`SanitizesRichTextTest`) dan Task 13.
4. **URL publik untuk data nonaktif atau arsip.** Contohnya `/layanan/{slug}` poliklinik nonaktif serta dokter nonaktif atau arsip di daftar dokter poliklinik. Harapan: 404 juga untuk staf yang login (A-42); dokter nonaktif atau arsip tidak tampil. Spesialisasi nonaktif hanya dikecualikan dari data dokter dan poliklinik, karena fase 2 belum punya halaman publik spesialisasi (tidak ada URL publik untuk dites). → Task 13.
5. **Akses media lintas pemilik dan penghapusan data yang dirujuk.** Contohnya Admin Konten mengubah alt media logo situs, Petugas menghapus foto dokter lewat `admin.media.destroy`, dan Super Admin menghapus spesialisasi yang dipakai dokter. Harapan: 403 sesuai pemilik media; hapus data yang dirujuk ditolak dengan flash `error`, bukan 500 FK, termasuk untuk Super Admin. → Task 6 (`AdminMediaEndpointsTest`), Task 9, Task 10.

## Keputusan (rulings) fase 2

Spesifikasi yang bertentangan diselesaikan sebagai berikut. Butir ini juga direkonsiliasi ke dokumen spesifikasi di Task 16.

| ID | Keputusan | Alasan |
|---|---|---|
| F2-R1 | Cabang `fase-2-master-data` dibuat dari `master` setelah merge fase 1. | 14 §6 butir 5 |
| F2-R2 | **Editor Quill (`x-form.rich-text` + `resources/js/editor.js`) dan `admin.media.editor-upload` dibangun di fase 2.** Ini menimpa F1-R9, yang menundanya ke 4a. Fase 4a memakai ulang komponen ini. | 14 §Fase 2 tugas 4 (`clinics.description`) |
| F2-R3 | **Endpoint media F-ADM-29 dibangun di fase 2**: `MediaController`, `MediaPolicy`, tiga Form Request V-42, dan empat route `admin.media.*`. Ini menutup F1-R21/D-28. | 11 §2.3 J, 12 V-42 |
| F2-R4 | Model **tidak** menimpa `getRouteKeyName()`. Route publik memakai `{clinic:slug}`; route admin memakai id numerik (`->where` grup admin sudah membatasi `doctor`, `specialty`, `clinic`, `media`). | 11 §2.4 (rinci) atas 00 C (ringkas) |
| F2-R5 | **Slug otomatis** untuk spesialisasi, poliklinik, dan dokter lewat trait `HasUniqueSlug`. Aturannya: `Str::slug` dari nama tanpa gelar; sufiks `-2`, `-3`, … bila bentrok; dipotong sesuai panjang kolom; slug tidak berubah saat nama diedit. Form **tidak** punya input `slug`. | 12 V-19/V-21 + BR-15 atas 11 §9.3; 11 asumsi 15 |
| F2-R6 | Form dokter **tidak** punya `status`. Dokter baru selalu `active`, dan status diubah lewat `admin.doctors.status`. Dropdown status di W-16 tidak dipakai. | 12 V-19 atas 11 §9.3 dan 07 W-16 |
| F2-R7 | **Pemulihan dari arsip** memakai `admin.doctors.status` dengan `status=inactive`. Dari `archived` hanya `inactive` yang diterima, dan `archived_at` dikosongkan. Pesan untuk nilai lain: "Dokter yang diarsipkan hanya dapat dipulihkan menjadi nonaktif." | BR-06; 11 §9.3 "dipulihkan menjadi `inactive`" (route tidak didefinisikan) |
| F2-R8 | Aturan F.4 yang bergantung pada tabel fase 3 **belum** ditegakkan di fase 2. Dokter yang dinonaktifkan tidak memengaruhi sesi, dan arsip diizinkan tanpa pemeriksaan appointment. `DoctorService` menyediakan titik sambung `protected function futureActiveAppointmentsCount(Doctor $doctor): int` (fase 2 selalu `0`) dan method publik `archiveBlockedReason(Doctor $doctor): ?string`. Method publik itu dipakai `ArchiveDoctorRequest` dan `archive()`, sehingga berlaku juga untuk Super Admin. Fase 3 cukup mengganti isi titik sambung. | 14 §Fase 2 tugas 6 |
| F2-R9 | Halaman #5 menampilkan dokter **aktif** di poliklinik dengan `x-doctor-card` versi fase 2: foto, nama tampilan, spesialisasi utama, dan tautan ke `doctors.show` hanya bila `Route::has`. `availability-badge`, jadwal, dan tombol **Daftar Kunjungan** (`patient.appointments.create`) disaring `Route::has` dan baru muncul di fase 3/6. | 14 "tanpa daftar jadwal sampai fase 3"; F1-R2 |
| F2-R10 | Judul dan subjudul halaman #4 memakai teks statis "Layanan Kami" / "Poliklinik dan layanan penunjang UNPAM HOSPITAL." serta meta `seo.default_meta_description`. Fase 4a menggantinya dengan `pages` key `clinics-index`. | Model `Page` baru ada di 4a |
| F2-R11 | `x-module-tabs` (10 §3.2) dibangun di fase 2 untuk tab Poliklinik/Spesialisasi (#34), dengan kunci `config('navigation.modules.clinics')`. Ini menimpa F1-R9 khusus untuk `module-tabs`. | 05 hal. 34 |
| F2-R12 | `per_page` admin default **25** (`ListFilterRequest::perPage()`); halaman #4 tanpa paginasi, dikelompokkan per `ClinicType`. | 00 I.5 atas 11 P-CRUD/07 (15) |
| F2-R13 | `clinics.description` divalidasi **`max:30000`** pada input mentah. | 09 §1.2 butir 10, kolom `TEXT` 65.535 byte, atas 12 V-22 `max:50000` |
| F2-R14 | Sanitasi rich text hanya terjadi di trait `SanitizesRichText` saat event `saving`, sehingga setiap jalur simpan (form, seeder, `update()`) melewatinya. Form Request tidak membersihkan HTML. | 11 §6.12 atas 12 BR-122 |
| F2-R15 | Nama field unggahan: editor `file` + `alt_text`; ganti foto dokter `photo` + `alt_text`; form tambah/ubah dokter `photo` + `photo_alt`; poliklinik `cover` + `cover_alt` dan `gallery[]` + `gallery_alt[]` (maks. 20 berkas per poliklinik). Respons JSON editor `{url, alt, media_id}`. | 12 V-19/V-20/V-22/V-42 atas 11 §9.3 (`image`); 12 §3.2 |
| F2-R16 | `MediaPolicy`. Pemetaan per pemilik: `Doctor` → `doctors.manage`; `Clinic`/`Specialty` → `clinics.manage`. Untuk `model_type` NULL: koleksi `logo` → `settings.manage`; koleksi `editor` → pengunggah sendiri **atau** salah satu dari `content.manage`/`clinics.manage`/`facilities.manage`. `model_type` lain belum dipetakan sehingga **ditolak**; fase 4 menambahkannya. | 11 §8 + 04 §5.1 |
| F2-R17 | Media yang boleh dihapus lewat `admin.media.destroy`: foto dokter, cover, dan galeri boleh dihapus (halaman publik jatuh ke placeholder). Media yang sedang dirujuk `site.logo_media_id` ditolak dengan 422 "Gambar tidak dapat dihapus karena sedang dipakai.". | 12 V-42 |
| F2-R18 | Penolakan hapus spesialisasi/poliklinik yang dirujuk dilakukan di **controller** lewat `deletionBlockedReason()` sebelum `delete()` (berlaku juga untuk Super Admin). Di fase 2 yang diperiksa: spesialisasi ← `doctor_specialties`, `clinics.specialty_id`; poliklinik ← `doctors.clinic_id`. Fase 3 menambah jadwal, sesi, dan appointment. | 11 §2.3/§4.4 atas 04 §5 (Policy) |
| F2-R19 | `AuditObserver` diletakkan di `app/Observers/AuditObserver.php` dan didaftarkan di `AppServiceProvider` hanya untuk `Specialty` dan `Clinic` di fase 2. `Doctor` tidak diobservasi; `DoctorService` mencatat `doctor.*` sendiri. | 10 §2/§7, 11 §3.3 |
| F2-R20 | `DoctorService` (`App\Services\Doctors\DoctorService`) adalah satu-satunya jalur tulis dokter: buat, ubah, `syncSpecialties`, status, arsip/pulihkan, dan ganti foto, dalam `DB::transaction` dengan audit eksplisit. | 09 I-5/I-23 |
| F2-R21 | Setelah `store`, redirect ke `index` (P-CRUD) dengan flash pola `lang/id/messages.php`, mis. "Dokter berhasil ditambahkan.". Penolakan hapus memakai flash `error`. | 11 P-CRUD atas 06 §12 |
| F2-R22 | Seeder `SpecialtySeeder`, `ClinicSeeder`, dan `DoctorSeeder` **hanya demo**: dipanggil `DemoSeeder` setelah `DemoUserSeeder` (urutan 13 §2.1), dengan data persis 13 §4–6. Gambar dibuat `PlaceholderImageGenerator` dan disimpan lewat `MediaService`. | 13 §2.1–2.3 |
| F2-R23 | `meta_title` maksimal 70 karakter. | 12 atas 07 W-16 (60) |
| F2-R24 | `Doctor::primarySpecialty` adalah **accessor** dari relasi `specialties` yang sudah dimuat: pivot `is_primary`, dengan cadangan spesialisasi pertama. Tidak ada model pivot `DoctorSpecialty` dan tidak ada relasi media khusus (`photo()`/`cover()`). Akses media lewat `HasMedia`. | 11 §5 atas 09 |
| F2-R25 | Daftar dokter admin memakai `App\Http\Requests\Admin\DoctorIndexRequest extends ListFilterRequest`. Parameternya: `status` ∈ `active`/`inactive`/`archived`/`semua` (default `active`), `poli` dan `spesialisasi` (slug ada), serta `urut` ∈ `nama`/`terbaru`/`urutan`. Daftar spesialisasi dan poliklinik memakai `ListFilterRequest` dengan `status` ∈ `aktif`/`nonaktif` dan `urut` ∈ `nama`/`urutan`; subclass-nya `Admin\MasterDataIndexRequest`. | 07 W-15; 12 V-45 |
| F2-R26 | Carry fase 1 yang dikerjakan di fase 2:<br>• `x-form.checkbox` mendukung nama larik (`specialty_ids[]`);<br>• `x-data-table` menampilkan pesan dan tautan ke halaman 1 bila halaman di luar jangkauan, serta meneruskan `urut` lewat prop baru `preserve`;<br>• meta `csrf-token` di layout admin;<br>• poles hasil uji manual fase 1 (Task 15);<br>• butir (c) `media:prune`, yaitu baris media yang pemiliknya sudah tidak ada (Task 2). | CHECKPOINT §4 |
| F2-R27 | Carry pengaturan situs (validasi format WhatsApp `62…`, bool/int ketat di `SettingsService`) **ditunda ke fase 8**, karena form pengaturan baru ada di fase 8. | 14 §5 Fase 8 |
| F2-R28 | Asersi CRD-01/CRD-03 yang membutuhkan halaman publik dokter (#6/#7, fase 3) diganti di fase 2 dengan scope `Doctor::publiclyVisible()` dan tampilan #5. Fase 3 menambah asersi `doctors.index`/`doctors.show`. | 14 §Fase 2 |

## Nama baru yang diperkenalkan fase 2

Nama-nama ini dicatat ulang ke blueprint 17 §4 / 00 J.6 di Task 16.

| Nama | Jenis | Task |
|---|---|---|
| `App\Models\Concerns\HasUniqueSlug` (`slugSource()`, `slugMaxLength()`, `static uniqueSlug(string $source, ?int $ignoreId = null)`) | Trait slug otomatis | 2 |
| `App\Services\Doctors\DoctorService` | Service tulis dokter | 8 |
| `DoctorService::futureActiveAppointmentsCount()` (protected), `DoctorService::archiveBlockedReason(Doctor): ?string` (publik) | Titik sambung fase 3 dan penolakan arsip (F2-R8) | 8 |
| Prop `checkedValues`, `showError` pada `x-form.checkbox` (nama larik) | Prop komponen | 5 |
| Prop `minHeight` dan penanda `data-rich-text*` pada `x-form.rich-text` | Prop/kontrak JS | 7 |
| `ReorderMediaRequest::owner()`, `::collection()`, `::orderedIds()` | Method bantu Form Request | 6 |
| `ClinicRequest::clinicHasAppointments()` (fase 2 selalu `false`), `ClinicRequest::coverAlt()`, `::galleryAlt()` | Titik sambung fase 3 (kunci `code`) dan pembantu | 10 |
| `ArchiveDoctorRequest::blockedReason(Doctor): ?string` (protected), `::reason(): ?string`; `UpdateDoctorStatusRequest` setara | Pembantu Form Request | 12 |
| `StoreDoctorRequest::doctorData()`, `::photoUpload()`, `::photoAlt()`, `::currentDoctor()`; `DoctorIndexRequest::statusFilter()`, `::applyTo()`; `MasterDataIndexRequest::applyTo(Builder, array $searchColumns)`; `SpecialtyRequest::attributesForModel()` | Pembantu Form Request | 9, 11 |
| `DoctorService::MAX_SPECIALTIES` | Konstanta (5, 12 V-19) | 8 |
| View `admin.doctors._status-actions`, `admin.doctors._status-modals`; id modal `modal-hapus-spesialisasi`, `modal-hapus-poliklinik`, `modal-hapus-foto`, `modal-doctor-{deactivate,activate,restore,archive}` | Partial/penanda | 11, 12 |
| Kelas SCSS `.admin-thumb`, `.admin-column`, `.auth-brand`, `.auth-logo`, `.rich-text` | Gaya | 7, 10, 15 |
| Kunci `permission` pada `config('navigation.modules.*')`; `NavigationMenu::items('modules.clinics', $user)` | Konfigurasi tab modul | 5 |
| `Tests\Feature\Access\Phase2RoutePermissionTest` | Kelas tes | 16 |
| Accessor `Clinic::seo_description` (`meta_description` ?: `short_description`) | Accessor SEO | 1 |
| Prop `headingLevel`, `showClinic` pada `x-doctor-card`; hook `initGalleryModals` (`[data-gallery-modal]`) di `app.js` | Komponen/JS publik | 13 |
| `ClinicSeeder::shortDescription()`, `::description()`; parameter opsional `$fontPath` pada `PlaceholderImageGenerator::make()` | Pembantu seeder | 14 |
| Kunci `messages.delete_blocked`, `messages.rules.person_name`, `messages.rules.no_html` | Pesan baku | 3, 4 |
| Tes `HasUniqueSlugTest`, `SanitizesRichTextTest`, `AdminMediaEndpointsTest`, `PublicClinicPagesTest`, `PublicClinicDetailTest`, `RawOutputScanTest`, `AuthPolishTest` (F1-R20 berlaku) | Kelas tes | 2, 6, 13, 15 |
| `Doctor::scopePubliclyVisible()` | Scope publik (aktif + poliklinik aktif) | 1 |
| `App\Http\Requests\Admin\DoctorIndexRequest`, `App\Http\Requests\Admin\MasterDataIndexRequest` | Filter daftar admin | 9, 11 |
| `Specialty::deletionBlockedReason()`, `Clinic::deletionBlockedReason()` (sudah disebut 11; teks pesan baru) | Penolakan hapus | 1 |
| Prop `preserve` pada `x-data-table` | Parameter kueri yang diteruskan | 5 |
| `config('navigation.modules')` | Definisi tab modul | 5 |
| `lang/id/messages.php` (kunci `created`, `updated`, `deleted`, `delete_blocked`, `entities.*`, `audit.*`) | Pesan baku | 3 |
| `App\Services\Media\PlaceholderImageGenerator::make(string $type, string $label, int $width, int $height): UploadedFile` | Generator gambar seeder | 14 |
| `Database\Seeders\{SpecialtySeeder, ClinicSeeder, DoctorSeeder}::ROWS` | Konstanta data demo | 14 |
| `Database\Factories\{Specialty,Clinic,Doctor}Factory` (state `inactive()`, `archived()`, `support()`, `withSpecialties()`) | Factory | 1 |
| `Tests\Support\InteractsWithMasterData` | Pembantu tes (buat poliklinik/spesialisasi/dokter siap pakai) | 1 |

## Struktur berkas (peta tanggung jawab)

```
app/
  Models/Specialty.php, Clinic.php, Doctor.php                         (T1, trait T2)
  Models/Concerns/HasUniqueSlug.php, SanitizesRichText.php, HasMedia.php (T2)
  Observers/AuditObserver.php                                          (T3)
  Rules/PersonName.php, NoHtml.php                                     (T4)
  Policies/SpecialtyPolicy.php, ClinicPolicy.php, DoctorPolicy.php, MediaPolicy.php  (T4)
  Services/Doctors/DoctorService.php                                   (T8)
  Services/Media/MediaService.php (ubah: prune butir c)                (T2)
  Services/Media/PlaceholderImageGenerator.php                         (T14)
  Http/Controllers/Admin/MediaController.php                           (T6)
  Http/Controllers/Admin/SpecialtyController.php                       (T9)
  Http/Controllers/Admin/ClinicController.php                          (T10)
  Http/Controllers/Admin/DoctorController.php                          (T11, T12)
  Http/Controllers/Public/ClinicController.php                         (T13)
  Http/Requests/Admin/{EditorImageUploadRequest, UpdateMediaRequest, ReorderMediaRequest}.php   (T6)
  Http/Requests/Admin/{MasterDataIndexRequest, SpecialtyRequest}.php   (T9)
  Http/Requests/Admin/ClinicRequest.php                                (T10)
  Http/Requests/Admin/{DoctorIndexRequest, StoreDoctorRequest, UpdateDoctorRequest}.php        (T11)
  Http/Requests/Admin/{UpdateDoctorStatusRequest, ArchiveDoctorRequest, UploadDoctorPhotoRequest}.php (T12)
  Providers/AppServiceProvider.php (ubah: observer, locale Carbon)      (T3)
config/navigation.php (ubah: modules)                                   (T5)
database/migrations/2026_10_02_000200..000500_*.php                     (T1)
database/factories/{Specialty,Clinic,Doctor}Factory.php                (T1)
database/seeders/{Specialty,Clinic,Doctor}Seeder.php, DemoSeeder.php (ubah)   (T14)
lang/id/messages.php                                                    (T3)
resources/js/editor.js, admin.js (ubah)                                 (T7)
resources/scss/_admin.scss, _public.scss (ubah seperlunya)              (T7, T13, T15)
resources/views/components/form/rich-text.blade.php                     (T7)
resources/views/components/form/checkbox.blade.php (ubah)               (T5)
resources/views/components/data-table.blade.php (ubah)                  (T5)
resources/views/components/module-tabs.blade.php                        (T5)
resources/views/components/doctor-card.blade.php                        (T13)
resources/views/components/layouts/admin.blade.php (ubah: csrf meta)    (T7)
resources/views/admin/specialties/{index,create,edit,_form}.blade.php   (T9)
resources/views/admin/clinics/{index,create,edit,_form}.blade.php       (T10)
resources/views/admin/doctors/{index,show,create,edit,_form}.blade.php  (T11, T12)
resources/views/public/clinics/{index,show}.blade.php, public/partials/_clinic-card.blade.php  (T13)
routes/admin.php (ubah)                                                 (T6, T9, T10, T11, T12)
routes/web.php (ubah)                                                   (T13)
tests/Support/InteractsWithMasterData.php                               (T1)
tests/... (per task)
```

---

> **Catatan penyusunan:** rencana ini disusun dari kerangka kontrak (tabel keputusan dan nama baru di atas) oleh empat penulis (Sonnet 5.5): Task 1–4, 5–8, 9–12, dan 13–16. Setelah itu dua pemeriksa konsistensi menyelaraskan antarmuka lintas tugas dengan keputusan koordinator (`AuthorizesRequests` hanya di Task 6; penolakan arsip lewat `DoctorService::archiveBlockedReason()`; galat spesialisasi tampil sekali; spesialisasi/poliklinik nonaktif yang sudah terpasang boleh dipertahankan saat ubah dokter; akhiran baris LF; §6.17 baru di spesifikasi 11 disisipkan setelah §6.16).
>
> Penulis Task 1–4 menjalankan kodenya di salinan sandbox dengan database terpisah, dan seluruh suite di sana hijau. Penulis lain memeriksa blok PHP dengan `php -l`, mencocokkan setiap jangkar Edit dengan berkas nyata, dan memverifikasi API vendor. Kode Task 5–16 baru benar-benar dijalankan saat eksekusi.

---

### Task 1: Skema, model, dan factory master data

**Files:**
- Create: `database/migrations/2026_10_02_000200_create_specialties_table.php`
- Create: `database/migrations/2026_10_02_000300_create_clinics_table.php`
- Create: `database/migrations/2026_10_02_000400_create_doctors_table.php`
- Create: `database/migrations/2026_10_02_000500_create_doctor_specialties_table.php`
- Create: `app/Models/Specialty.php`, `app/Models/Clinic.php`, `app/Models/Doctor.php`
- Create: `database/factories/SpecialtyFactory.php`, `database/factories/ClinicFactory.php`, `database/factories/DoctorFactory.php`
- Create: `tests/Support/InteractsWithMasterData.php`
- Test: `tests/Feature/Models/MasterDataSchemaTest.php`, `SpecialtyModelTest.php`, `ClinicModelTest.php`, `DoctorModelTest.php`, `InteractsWithMasterDataTest.php`

**Interfaces:**
- Consumes: enum `DoctorStatus`, `Gender`, `ClinicType` (fase 0, tidak diubah); `MediaService`/`HasMedia` **belum** dipakai di task ini (Task 2); `Tests\Support\InteractsWithStaff` tidak dipakai.
- Produces (persis kontrak):
  - `App\Models\Specialty` — `$fillable` `name, slug, description, is_active, sort_order`; casts `is_active` bool, `sort_order` int. Relasi `doctors(): BelongsToMany` (`doctor_specialties`, `withPivot('is_primary')`), `clinics(): HasMany`. Scope `active()`, `ordered()` (`sort_order`, lalu `name`). `deletionBlockedReason(): ?string`.
  - `App\Models\Clinic` — `$fillable` per 11 §5; casts `type` → `ClinicType`, `is_bookable`/`is_active` bool, `sort_order` int; mutator `code` (huruf kapital, spasi tepi dibuang). Relasi `specialty(): BelongsTo`, `doctors(): HasMany`. Scope `active()`, `bookable()` (aktif **dan** `is_bookable`), `ordered()`. Accessor `seo_title` (`meta_title` ?: `name`) dan `seo_description` (`meta_description` ?: `short_description`; ada di 11 §5.2, dipakai Task 13). `deletionBlockedReason(): ?string`.
  - `App\Models\Doctor` — `$fillable` per 11 §5 **tanpa** `archived_at`; casts `status` → `DoctorStatus`, `gender` → `Gender`, `archived_at` datetime, `sort_order` int. Relasi `clinic(): BelongsTo`, `specialties(): BelongsToMany` (`withPivot('is_primary')`). Scope `active()`, `publiclyVisible()`, `search(?string $q)`, `inSpecialty(int|string $idOrSlug)`, `inClinic(int $id)`, `ordered()`. Accessor `display_name` dan `primary_specialty` (F2-R24).
  - Factory: `SpecialtyFactory` (`inactive()`), `ClinicFactory` (`inactive()`, `support()`), `DoctorFactory` (`inactive()`, `archived()`, `withSpecialties(int $count = 1)`).
  - `Tests\Support\InteractsWithMasterData`: `createSpecialty(array $attrs = []): Specialty`, `createClinic(array $attrs = []): Clinic`, `createDoctor(array $attrs = [], ?Clinic $clinic = null, array $specialties = []): Doctor`.
- Detail yang ditambahkan (dipakai task 5-16):
  - Nama accessor dibaca dengan snake_case: `$doctor->display_name`, `$doctor->primary_specialty`, `$clinic->seo_title`, `$clinic->seo_description`. Bentuk camelCase (`$doctor->displayName`) juga bekerja, tetapi gunakan snake_case di kode dan view.
  - `Doctor::primary_specialty` memakai relasi `specialties` yang **sudah dimuat** (cadangan: spesialisasi pertama; `null` bila kosong). Daftar wajib `with('specialties')`.
  - `Doctor::inSpecialty()`: `int` dibaca sebagai id, `string` **selalu** sebagai slug (parameter kueri `spesialisasi` berisi slug).
  - `Doctor::search()`: karakter `%` dan `_` pada input diperlakukan literal; nilai kosong/`null` tidak menyaring.
  - `createDoctor()` mengembalikan dokter dengan relasi `specialties` **sudah dimuat**; poliklinik diambil dari argumen `$clinic`, lalu `$attrs['clinic_id']`, lalu poliklinik baru. Spesialisasi pertama dari `$specialties` menjadi utama.
  - `DoctorFactory::withSpecialties($n)` membuat `$n` spesialisasi baru; yang pertama utama (invarian I-5).
  - Di task ini factory masih menurunkan `slug` dari nama (sementara); Task 2 menghapusnya.
- **Catatan konflik:** 09 §3.3 menyebut relasi `primarySpecialty()` (`wherePivot`) dan `photo()`/`cover()`/`gallery()`; kontrak (F2-R24, 11 §5) memilih accessor dari relasi yang sudah dimuat dan akses media lewat `HasMedia` (Task 2). Tidak ada model pivot `DoctorSpecialty` (11 §5.8).

**Kolom (09 §3.1-3.4, dikutip):**

| Tabel | Kolom | Tipe | Null | Default | Catatan |
|---|---|---|---|---|---|
| `specialties` | `id` | BIGINT UNSIGNED AI | tidak | - | PK |
| | `name` | VARCHAR(100) | tidak | - | keunikan nama divalidasi Form Request (UNIQUE DB hanya `slug`) |
| | `slug` | VARCHAR(120) | tidak | - | UNIQUE `specialties_slug_unique` |
| | `description` | TEXT | ya | NULL | teks biasa |
| | `is_active` | TINYINT(1) | tidak | 1 | |
| | `sort_order` | SMALLINT UNSIGNED | tidak | 0 | index `specialties_is_active_sort_order_index (is_active, sort_order)` |
| | `created_at`, `updated_at` | TIMESTAMP | ya | NULL | |
| `clinics` | `id` | BIGINT UNSIGNED AI | tidak | - | PK |
| | `name` | VARCHAR(120) | tidak | - | |
| | `slug` | VARCHAR(140) | tidak | - | UNIQUE `clinics_slug_unique` |
| | `code` | VARCHAR(5) | tidak | - | UNIQUE `clinics_code_unique` |
| | `type` | VARCHAR(20) | tidak | - | `ClinicType` |
| | `is_bookable` | TINYINT(1) | tidak | 1 | |
| | `specialty_id` | BIGINT UNSIGNED | ya | NULL | FK `specialties.id` ON DELETE SET NULL (`clinics_specialty_id_foreign`) |
| | `short_description` | VARCHAR(300) | tidak | - | |
| | `description` | TEXT | ya | NULL | rich text (disanitasi, Task 2) |
| | `location` | VARCHAR(150) | tidak | - | |
| | `is_active` | TINYINT(1) | tidak | 1 | |
| | `sort_order` | SMALLINT UNSIGNED | tidak | 0 | index `clinics_is_active_type_sort_order_index (is_active, type, sort_order)` |
| | `meta_title`, `meta_description` | VARCHAR(255) | ya | NULL | |
| | `created_at`, `updated_at` | TIMESTAMP | ya | NULL | |
| `doctors` | `id` | BIGINT UNSIGNED AI | tidak | - | PK |
| | `clinic_id` | BIGINT UNSIGNED | tidak | - | FK `clinics.id` ON DELETE RESTRICT (`doctors_clinic_id_foreign`) |
| | `name` | VARCHAR(150) | tidak | - | tanpa gelar |
| | `slug` | VARCHAR(170) | tidak | - | UNIQUE `doctors_slug_unique` |
| | `title_prefix` | VARCHAR(20) | ya | NULL | |
| | `title_suffix` | VARCHAR(50) | ya | NULL | |
| | `gender` | CHAR(1) | tidak | - | `Gender` L/P |
| | `short_bio` | VARCHAR(500) | ya | NULL | |
| | `bio` | TEXT | ya | NULL | teks biasa |
| | `status` | VARCHAR(10) | tidak | `'active'` | `DoctorStatus` |
| | `archived_at` | DATETIME | ya | NULL | |
| | `sort_order` | SMALLINT UNSIGNED | tidak | 0 | index `doctors_status_sort_order_index (status, sort_order)` |
| | `meta_title`, `meta_description` | VARCHAR(255) | ya | NULL | |
| | `created_at`, `updated_at` | TIMESTAMP | ya | NULL | |
| `doctor_specialties` | `doctor_id` | BIGINT UNSIGNED | tidak | - | FK `doctors.id` ON DELETE CASCADE; PK komposit `(doctor_id, specialty_id)` |
| | `specialty_id` | BIGINT UNSIGNED | tidak | - | FK `specialties.id` ON DELETE RESTRICT; index `doctor_specialties_specialty_id_foreign` |
| | `is_primary` | TINYINT(1) | tidak | 0 | tanpa timestamps |

**Pesan (sumber):**

| Method | Pesan | Sumber |
|---|---|---|
| `Specialty::deletionBlockedReason()` | "Spesialisasi ini masih dipakai oleh dokter atau poliklinik sehingga tidak dapat dihapus. Nonaktifkan saja." | kontrak Task 1; F2-R18; 00 C |
| `Clinic::deletionBlockedReason()` | "Poliklinik ini masih dirujuk oleh data dokter sehingga tidak dapat dihapus. Nonaktifkan saja." | kontrak Task 1; F2-R18; 00 C |

**Test IDs:** bagian skema CRD-01..CRD-04 (FK `restrict`, unik `slug`/`code`), CRD-04 (nama tampilan), invarian I-5 dan I-23 (K-23).

**Catatan teknis (diverifikasi dari source dan dijalankan di MariaDB 10.4):**
- `Schema::getColumns()` melaporkan `bigint(20) unsigned`, `smallint(5) unsigned`, `tinyint(1)`, `char(1)`; default NULL berupa string `"NULL"` dan default string berkutip (`'active'`), sehingga tes menormalkannya seperti `UserAccountTest`. `Schema::getForeignKeys()` memberi `on_delete` huruf kecil (`set null`, `restrict`, `cascade`). Nama index/FK adalah nama bawaan Laravel.
- Pencegahan *lazy loading* di `Builder::hydrate()` hanya dipasang bila hasil query berisi **lebih dari satu** model (`count($items) > 1`). Tes yang membuktikan `LazyLoadingViolationException` harus memuat minimal dua baris; satu baris dari `first()`/`firstOrFail()` tidak melempar. Ini alasan semua tes mode ketat di rencana ini memakai dua baris atau lebih.
- `Attribute::get()` yang mengembalikan objek di-*cache* oleh Eloquent pada model; `primarySpecialty()` memakai `->withoutObjectCaching()` agar hasilnya mengikuti relasi `specialties` yang dimuat ulang (dibuktikan tes).
- `withPivot('is_primary')` mengembalikan `0`/`1` (bukan bool) pada `$model->pivot->is_primary`; kode memakai `(bool)`.
- `Doctor::$fillable` tanpa `archived_at` (mode ketat melempar `MassAssignmentException` untuk atribut yang tidak ada di `$fillable`); factory berjalan *unguarded* sehingga `DoctorFactory::archived()` boleh mengisinya. `DoctorService` (Task 8) memakai `forceFill()`.
- Slug UNIQUE bersifat tidak peka huruf besar/kecil (kolasi `utf8mb4_unicode_ci`); `ANAK` dan `anak` bentrok (diuji).
- `where('status', DoctorStatus::Active->value)` dipakai eksplisit (bukan objek enum) agar tidak bergantung pada pengikatan enum.
- Semua blok PHP lolos `php -l` 8.2.

- [ ] **Step 1: Verifikasi prasyarat (cabang dan baseline)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git branch --show-current
git status --short
& $PHP artisan test
```
Expected: `fase-2-master-data`; `git status` bersih; seluruh tes PASS (baseline fase 1, 980 tes).

**Siklus A — migration dan skema**

- [ ] **Step 2: Tulis tes yang gagal (tool Write) `tests/Feature/Models/MasterDataSchemaTest.php`**

```php
<?php

namespace Tests\Feature\Models;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Skema 09-data-dictionary.md §3.1-3.4 (spesialisasi, poliklinik, dokter, pivot spesialisasi dokter).
 */
class MasterDataSchemaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Kolom: tabel, nama kolom, type_name, tipe lengkap, nullable, default (setelah normalisasi).
     *
     * @return array<string, array{0: string, 1: string, 2: string, 3: string, 4: bool, 5: ?string}>
     */
    public static function expectedColumns(): array
    {
        $rows = [
            // 09 §3.1 specialties
            ['specialties', 'id', 'bigint', 'bigint(20) unsigned', false, null],
            ['specialties', 'name', 'varchar', 'varchar(100)', false, null],
            ['specialties', 'slug', 'varchar', 'varchar(120)', false, null],
            ['specialties', 'description', 'text', 'text', true, null],
            ['specialties', 'is_active', 'tinyint', 'tinyint(1)', false, '1'],
            ['specialties', 'sort_order', 'smallint', 'smallint(5) unsigned', false, '0'],
            ['specialties', 'created_at', 'timestamp', 'timestamp', true, null],
            ['specialties', 'updated_at', 'timestamp', 'timestamp', true, null],
            // 09 §3.2 clinics
            ['clinics', 'id', 'bigint', 'bigint(20) unsigned', false, null],
            ['clinics', 'name', 'varchar', 'varchar(120)', false, null],
            ['clinics', 'slug', 'varchar', 'varchar(140)', false, null],
            ['clinics', 'code', 'varchar', 'varchar(5)', false, null],
            ['clinics', 'type', 'varchar', 'varchar(20)', false, null],
            ['clinics', 'is_bookable', 'tinyint', 'tinyint(1)', false, '1'],
            ['clinics', 'specialty_id', 'bigint', 'bigint(20) unsigned', true, null],
            ['clinics', 'short_description', 'varchar', 'varchar(300)', false, null],
            ['clinics', 'description', 'text', 'text', true, null],
            ['clinics', 'location', 'varchar', 'varchar(150)', false, null],
            ['clinics', 'is_active', 'tinyint', 'tinyint(1)', false, '1'],
            ['clinics', 'sort_order', 'smallint', 'smallint(5) unsigned', false, '0'],
            ['clinics', 'meta_title', 'varchar', 'varchar(255)', true, null],
            ['clinics', 'meta_description', 'varchar', 'varchar(255)', true, null],
            ['clinics', 'created_at', 'timestamp', 'timestamp', true, null],
            ['clinics', 'updated_at', 'timestamp', 'timestamp', true, null],
            // 09 §3.3 doctors
            ['doctors', 'id', 'bigint', 'bigint(20) unsigned', false, null],
            ['doctors', 'clinic_id', 'bigint', 'bigint(20) unsigned', false, null],
            ['doctors', 'name', 'varchar', 'varchar(150)', false, null],
            ['doctors', 'slug', 'varchar', 'varchar(170)', false, null],
            ['doctors', 'title_prefix', 'varchar', 'varchar(20)', true, null],
            ['doctors', 'title_suffix', 'varchar', 'varchar(50)', true, null],
            ['doctors', 'gender', 'char', 'char(1)', false, null],
            ['doctors', 'short_bio', 'varchar', 'varchar(500)', true, null],
            ['doctors', 'bio', 'text', 'text', true, null],
            ['doctors', 'status', 'varchar', 'varchar(10)', false, 'active'],
            ['doctors', 'archived_at', 'datetime', 'datetime', true, null],
            ['doctors', 'sort_order', 'smallint', 'smallint(5) unsigned', false, '0'],
            ['doctors', 'meta_title', 'varchar', 'varchar(255)', true, null],
            ['doctors', 'meta_description', 'varchar', 'varchar(255)', true, null],
            ['doctors', 'created_at', 'timestamp', 'timestamp', true, null],
            ['doctors', 'updated_at', 'timestamp', 'timestamp', true, null],
            // 09 §3.4 doctor_specialties
            ['doctor_specialties', 'doctor_id', 'bigint', 'bigint(20) unsigned', false, null],
            ['doctor_specialties', 'specialty_id', 'bigint', 'bigint(20) unsigned', false, null],
            ['doctor_specialties', 'is_primary', 'tinyint', 'tinyint(1)', false, '0'],
        ];

        $cases = [];

        foreach ($rows as $row) {
            $cases["{$row[0]}.{$row[1]}"] = $row;
        }

        return $cases;
    }

    /**
     * Index: tabel, nama index, kolom, unik, primer.
     *
     * @return array<string, array{0: string, 1: string, 2: list<string>, 3: bool, 4: bool}>
     */
    public static function expectedIndexes(): array
    {
        $rows = [
            ['specialties', 'primary', ['id'], true, true],
            ['specialties', 'specialties_slug_unique', ['slug'], true, false],
            ['specialties', 'specialties_is_active_sort_order_index', ['is_active', 'sort_order'], false, false],
            ['clinics', 'primary', ['id'], true, true],
            ['clinics', 'clinics_slug_unique', ['slug'], true, false],
            ['clinics', 'clinics_code_unique', ['code'], true, false],
            ['clinics', 'clinics_specialty_id_foreign', ['specialty_id'], false, false],
            ['clinics', 'clinics_is_active_type_sort_order_index', ['is_active', 'type', 'sort_order'], false, false],
            ['doctors', 'primary', ['id'], true, true],
            ['doctors', 'doctors_slug_unique', ['slug'], true, false],
            ['doctors', 'doctors_clinic_id_foreign', ['clinic_id'], false, false],
            ['doctors', 'doctors_status_sort_order_index', ['status', 'sort_order'], false, false],
            ['doctor_specialties', 'primary', ['doctor_id', 'specialty_id'], true, true],
            ['doctor_specialties', 'doctor_specialties_specialty_id_foreign', ['specialty_id'], false, false],
        ];

        $cases = [];

        foreach ($rows as $row) {
            $cases["{$row[0]}.{$row[1]}"] = $row;
        }

        return $cases;
    }

    /**
     * Foreign key: tabel, nama FK, kolom, tabel rujukan, aksi ON DELETE.
     *
     * @return array<string, array{0: string, 1: string, 2: string, 3: string, 4: string}>
     */
    public static function expectedForeignKeys(): array
    {
        return [
            'clinics.specialty_id' => ['clinics', 'clinics_specialty_id_foreign', 'specialty_id', 'specialties', 'set null'],
            'doctors.clinic_id' => ['doctors', 'doctors_clinic_id_foreign', 'clinic_id', 'clinics', 'restrict'],
            'doctor_specialties.doctor_id' => ['doctor_specialties', 'doctor_specialties_doctor_id_foreign', 'doctor_id', 'doctors', 'cascade'],
            'doctor_specialties.specialty_id' => ['doctor_specialties', 'doctor_specialties_specialty_id_foreign', 'specialty_id', 'specialties', 'restrict'],
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

    /** @return array<string, array<string, mixed>> */
    private function indexesOf(string $table): array
    {
        $indexes = [];

        foreach (Schema::getIndexes($table) as $index) {
            $indexes[$index['name']] = $index;
        }

        return $indexes;
    }

    #[DataProvider('expectedColumns')]
    public function test_column_matches_the_data_dictionary(string $table, string $name, string $typeName, string $type, bool $nullable, ?string $default): void
    {
        $columns = $this->columnsOf($table);

        $this->assertArrayHasKey($name, $columns, "Kolom {$table}.{$name} belum ada.");
        $this->assertSame($typeName, $columns[$name]['type_name'], "type_name {$table}.{$name}");
        $this->assertSame($type, $columns[$name]['type'], "type {$table}.{$name}");
        $this->assertSame($nullable, $columns[$name]['nullable'], "nullable {$table}.{$name}");
        $this->assertSame($default, $this->normalizedDefault($columns[$name]['default']), "default {$table}.{$name}");
    }

    public function test_tables_have_exactly_the_documented_columns_in_order(): void
    {
        $this->assertSame(
            ['id', 'name', 'slug', 'description', 'is_active', 'sort_order', 'created_at', 'updated_at'],
            Schema::getColumnListing('specialties'),
        );
        $this->assertSame(
            ['id', 'name', 'slug', 'code', 'type', 'is_bookable', 'specialty_id', 'short_description', 'description', 'location', 'is_active', 'sort_order', 'meta_title', 'meta_description', 'created_at', 'updated_at'],
            Schema::getColumnListing('clinics'),
        );
        $this->assertSame(
            ['id', 'clinic_id', 'name', 'slug', 'title_prefix', 'title_suffix', 'gender', 'short_bio', 'bio', 'status', 'archived_at', 'sort_order', 'meta_title', 'meta_description', 'created_at', 'updated_at'],
            Schema::getColumnListing('doctors'),
        );
        $this->assertSame(['doctor_id', 'specialty_id', 'is_primary'], Schema::getColumnListing('doctor_specialties'));
    }

    public function test_primary_keys_are_auto_increment_except_the_pivot(): void
    {
        foreach (['specialties', 'clinics', 'doctors'] as $table) {
            $this->assertTrue($this->columnsOf($table)['id']['auto_increment'], "{$table}.id auto increment");
        }
    }

    #[DataProvider('expectedIndexes')]
    public function test_index_matches_the_data_dictionary(string $table, string $name, array $columns, bool $unique, bool $primary): void
    {
        $indexes = $this->indexesOf($table);

        $this->assertArrayHasKey($name, $indexes, "Index {$table}.{$name} belum ada.");
        $this->assertSame($columns, $indexes[$name]['columns']);
        $this->assertSame($unique, $indexes[$name]['unique']);
        $this->assertSame($primary, $indexes[$name]['primary']);
    }

    #[DataProvider('expectedForeignKeys')]
    public function test_foreign_key_actions_match_the_data_dictionary(string $table, string $name, string $column, string $foreignTable, string $onDelete): void
    {
        $foreignKeys = collect(Schema::getForeignKeys($table))->keyBy('name');

        $this->assertTrue($foreignKeys->has($name), "FK {$name} belum ada.");
        $this->assertSame([$column], $foreignKeys[$name]['columns']);
        $this->assertSame($foreignTable, $foreignKeys[$name]['foreign_table']);
        $this->assertSame(['id'], $foreignKeys[$name]['foreign_columns']);
        $this->assertSame($onDelete, $foreignKeys[$name]['on_delete']);
    }

    public function test_migrations_are_registered_under_the_documented_names(): void
    {
        $names = DB::table('migrations')->pluck('migration')->all();

        foreach ([
            '2026_10_02_000200_create_specialties_table',
            '2026_10_02_000300_create_clinics_table',
            '2026_10_02_000400_create_doctors_table',
            '2026_10_02_000500_create_doctor_specialties_table',
        ] as $migration) {
            $this->assertContains($migration, $names);
        }
    }

    // --- Perilaku kendala ---

    public function test_specialty_slug_is_unique_and_case_insensitive(): void
    {
        $this->insertSpecialty(['name' => 'Anak', 'slug' => 'anak']);

        $this->expectException(QueryException::class);

        $this->insertSpecialty(['name' => 'Anak Dua', 'slug' => 'Anak']);
    }

    public function test_specialty_name_is_not_unique_at_database_level(): void
    {
        $this->insertSpecialty(['name' => 'Anak', 'slug' => 'anak']);
        $this->insertSpecialty(['name' => 'Anak', 'slug' => 'anak-2']);

        $this->assertSame(2, DB::table('specialties')->where('name', 'Anak')->count());
    }

    public function test_clinic_slug_and_code_are_unique(): void
    {
        $this->insertClinic(['slug' => 'poliklinik-anak', 'code' => 'ANK']);

        try {
            $this->insertClinic(['slug' => 'poliklinik-anak', 'code' => 'ANL']);
            $this->fail('Slug ganda seharusnya ditolak.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('clinics_slug_unique', $e->getMessage());
        }

        try {
            $this->insertClinic(['slug' => 'poliklinik-anak-2', 'code' => 'ANK']);
            $this->fail('Kode ganda seharusnya ditolak.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('clinics_code_unique', $e->getMessage());
        }
    }

    public function test_doctor_slug_is_unique(): void
    {
        $clinicId = $this->insertClinic();
        $this->insertDoctor($clinicId, ['slug' => 'andi-pratama']);

        $this->expectException(QueryException::class);

        $this->insertDoctor($clinicId, ['slug' => 'andi-pratama']);
    }

    public function test_deleting_a_clinic_that_has_doctors_is_rejected_by_the_database(): void
    {
        $clinicId = $this->insertClinic();
        $this->insertDoctor($clinicId);

        try {
            DB::table('clinics')->where('id', $clinicId)->delete();
            $this->fail('FK doctors.clinic_id RESTRICT seharusnya menolak penghapusan.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('doctors_clinic_id_foreign', $e->getMessage());
        }

        $this->assertSame(1, DB::table('clinics')->where('id', $clinicId)->count());
    }

    public function test_deleting_a_specialty_used_by_a_doctor_is_rejected_by_the_database(): void
    {
        $specialtyId = $this->insertSpecialty();
        $doctorId = $this->insertDoctor($this->insertClinic());
        DB::table('doctor_specialties')->insert(['doctor_id' => $doctorId, 'specialty_id' => $specialtyId, 'is_primary' => true]);

        try {
            DB::table('specialties')->where('id', $specialtyId)->delete();
            $this->fail('FK doctor_specialties.specialty_id RESTRICT seharusnya menolak penghapusan.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('doctor_specialties_specialty_id_foreign', $e->getMessage());
        }
    }

    public function test_deleting_a_specialty_used_by_a_clinic_sets_the_clinic_specialty_to_null(): void
    {
        $specialtyId = $this->insertSpecialty();
        $clinicId = $this->insertClinic(['specialty_id' => $specialtyId]);

        DB::table('specialties')->where('id', $specialtyId)->delete();

        $this->assertNull(DB::table('clinics')->where('id', $clinicId)->value('specialty_id'));
    }

    public function test_deleting_a_doctor_cascades_to_the_pivot_rows(): void
    {
        $specialtyId = $this->insertSpecialty();
        $doctorId = $this->insertDoctor($this->insertClinic());
        DB::table('doctor_specialties')->insert(['doctor_id' => $doctorId, 'specialty_id' => $specialtyId, 'is_primary' => true]);

        DB::table('doctors')->where('id', $doctorId)->delete();

        $this->assertSame(0, DB::table('doctor_specialties')->count());
        $this->assertSame(1, DB::table('specialties')->count());
    }

    public function test_pivot_primary_key_rejects_the_same_specialty_twice_for_a_doctor(): void
    {
        $specialtyId = $this->insertSpecialty();
        $doctorId = $this->insertDoctor($this->insertClinic());
        DB::table('doctor_specialties')->insert(['doctor_id' => $doctorId, 'specialty_id' => $specialtyId, 'is_primary' => true]);

        $this->expectException(QueryException::class);

        DB::table('doctor_specialties')->insert(['doctor_id' => $doctorId, 'specialty_id' => $specialtyId, 'is_primary' => false]);
    }

    public function test_column_defaults_are_applied_by_the_database(): void
    {
        $specialtyId = DB::table('specialties')->insertGetId(['name' => 'Umum', 'slug' => 'umum']);
        $clinicId = DB::table('clinics')->insertGetId([
            'name' => 'Poliklinik Umum', 'slug' => 'poliklinik-umum', 'code' => 'UMM', 'type' => 'poliklinik',
            'short_description' => 'Ringkasan.', 'location' => 'Gedung A Lt. 1',
        ]);
        $doctorId = DB::table('doctors')->insertGetId([
            'clinic_id' => $clinicId, 'name' => 'Rani Oktaviani', 'slug' => 'rani-oktaviani', 'gender' => 'P',
        ]);

        $specialty = DB::table('specialties')->find($specialtyId);
        $clinic = DB::table('clinics')->find($clinicId);
        $doctor = DB::table('doctors')->find($doctorId);

        $this->assertSame([1, 0], [(int) $specialty->is_active, (int) $specialty->sort_order]);
        $this->assertSame([1, 1, 0], [(int) $clinic->is_bookable, (int) $clinic->is_active, (int) $clinic->sort_order]);
        $this->assertSame('active', $doctor->status);
        $this->assertNull($doctor->archived_at);
        $this->assertSame(0, (int) $doctor->sort_order);
    }

    // --- Pembantu ---

    /** @param  array<string, mixed>  $overrides */
    private function insertSpecialty(array $overrides = []): int
    {
        static $n = 0;
        $n++;

        return DB::table('specialties')->insertGetId(array_merge([
            'name' => "Spesialisasi {$n}", 'slug' => "spesialisasi-{$n}",
        ], $overrides));
    }

    /** @param  array<string, mixed>  $overrides */
    private function insertClinic(array $overrides = []): int
    {
        static $n = 0;
        $n++;

        return DB::table('clinics')->insertGetId(array_merge([
            'name' => "Poliklinik {$n}", 'slug' => "poliklinik-{$n}", 'code' => 'K'.chr(64 + $n), 'type' => 'poliklinik',
            'short_description' => 'Ringkasan.', 'location' => 'Gedung A Lt. 1',
        ], $overrides));
    }

    /** @param  array<string, mixed>  $overrides */
    private function insertDoctor(int $clinicId, array $overrides = []): int
    {
        static $n = 0;
        $n++;

        return DB::table('doctors')->insertGetId(array_merge([
            'clinic_id' => $clinicId, 'name' => "Dokter {$n}", 'slug' => "dokter-{$n}", 'gender' => 'L',
        ], $overrides));
    }
}
```

- [ ] **Step 3: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=MasterDataSchemaTest
```
Expected: FAIL — hampir semua tes gagal karena tabel belum ada (mis. `Index clinics.clinics_code_unique belum ada.`, `FK doctors_clinic_id_foreign belum ada.`, `Failed asserting that an array contains '2026_10_02_000200_create_specialties_table'.`).

- [ ] **Step 4: Tulis empat migration (tool Write)**

`database/migrations/2026_10_02_000200_create_specialties_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('specialties', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 120)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('specialties');
    }
};
```

`database/migrations/2026_10_02_000300_create_clinics_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinics', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->string('code', 5)->unique();
            $table->string('type', 20);
            $table->boolean('is_bookable')->default(true);
            $table->foreignId('specialty_id')->nullable()->constrained('specialties')->nullOnDelete();
            $table->string('short_description', 300);
            $table->text('description')->nullable();
            $table->string('location', 150);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'type', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinics');
    }
};
```

`database/migrations/2026_10_02_000400_create_doctors_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->restrictOnDelete();
            $table->string('name', 150);
            $table->string('slug', 170)->unique();
            $table->string('title_prefix', 20)->nullable();
            $table->string('title_suffix', 50)->nullable();
            $table->char('gender', 1);
            $table->string('short_bio', 500)->nullable();
            $table->text('bio')->nullable();
            $table->string('status', 10)->default('active');
            $table->dateTime('archived_at')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();
            $table->timestamps();

            $table->index(['status', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctors');
    }
};
```

`database/migrations/2026_10_02_000500_create_doctor_specialties_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_specialties', function (Blueprint $table): void {
            $table->foreignId('doctor_id')->constrained('doctors')->cascadeOnDelete();
            $table->foreignId('specialty_id')->constrained('specialties')->restrictOnDelete();
            $table->boolean('is_primary')->default(false);

            $table->primary(['doctor_id', 'specialty_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_specialties');
    }
};
```

- [ ] **Step 5: Jalankan tes — harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=MasterDataSchemaTest
```
Expected: PASS — `Tests: 74 passed`.

- [ ] **Step 6: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint
& $PHP vendor\bin\pint --test
git add database/migrations/2026_10_02_000200_create_specialties_table.php database/migrations/2026_10_02_000300_create_clinics_table.php database/migrations/2026_10_02_000400_create_doctors_table.php database/migrations/2026_10_02_000500_create_doctor_specialties_table.php tests/Feature/Models/MasterDataSchemaTest.php
git commit -m @'
feat(fase-2): migration spesialisasi, poliklinik, dokter, dan pivot spesialisasi dokter

Skema 09 §3.1-3.4: tipe, panjang, default, index, dan aksi FK persis kamus data
(doctors.clinic_id RESTRICT, clinics.specialty_id SET NULL, pivot CASCADE/RESTRICT).
Tes memverifikasi kolom, index, FK, dan perilaku kendala langsung di MariaDB.

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
'@
```
Expected: `pint --test` → `{"result":"passed"}`; commit tercatat.

**Siklus B — `Specialty` dan `SpecialtyFactory`**

- [ ] **Step 7: Tulis tes yang gagal (tool Write) `tests/Feature/Models/SpecialtyModelTest.php`**

```php
<?php

namespace Tests\Feature\Models;

use App\Models\Specialty;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpecialtyModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_fillable_follows_the_blueprint(): void
    {
        $this->assertSame(
            ['name', 'slug', 'description', 'is_active', 'sort_order'],
            (new Specialty)->getFillable(),
        );
    }

    public function test_unknown_attributes_are_rejected_in_strict_mode(): void
    {
        $this->expectException(MassAssignmentException::class);

        Specialty::create(['name' => 'Anak', 'slug' => 'anak', 'bukan_kolom' => 'x']);
    }

    public function test_casts_and_defaults(): void
    {
        $created = Specialty::create(['name' => 'Anak', 'slug' => 'anak']);
        $specialty = Specialty::query()->findOrFail($created->id);

        $this->assertTrue($specialty->is_active);
        $this->assertSame(0, $specialty->sort_order);
        $this->assertNull($specialty->description);
    }

    public function test_factory_creates_a_valid_active_specialty_and_inactive_state(): void
    {
        $active = Specialty::factory()->create();
        $inactive = Specialty::factory()->inactive()->create();

        $this->assertTrue($active->fresh()->is_active);
        $this->assertFalse($inactive->fresh()->is_active);
        $this->assertNotSame('', $active->slug);
        $this->assertNotSame($active->slug, $inactive->slug);
    }

    public function test_active_scope_only_returns_active_rows(): void
    {
        $active = Specialty::factory()->create();
        Specialty::factory()->inactive()->count(2)->create();

        $this->assertSame([$active->id], Specialty::query()->active()->pluck('id')->all());
    }

    public function test_ordered_scope_sorts_by_sort_order_then_name(): void
    {
        $c = Specialty::factory()->create(['name' => 'Mata', 'sort_order' => 2]);
        $b = Specialty::factory()->create(['name' => 'Bedah', 'sort_order' => 1]);
        $a = Specialty::factory()->create(['name' => 'Anak', 'sort_order' => 1]);

        $this->assertSame([$a->id, $b->id, $c->id], Specialty::query()->ordered()->pluck('id')->all());
    }

    public function test_description_is_stored_as_plain_text_without_sanitizing(): void
    {
        $specialty = Specialty::factory()->create(['description' => 'tekanan <90 dan >100']);

        $this->assertSame('tekanan <90 dan >100', $specialty->fresh()->description);
    }
}
```

- [ ] **Step 8: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=SpecialtyModelTest
```
Expected: FAIL — 7 tes ERROR `Class "App\Models\Specialty" not found`.

- [ ] **Step 9: Tulis model dan factory (tool Write)**

`app/Models/Specialty.php` (versi Task 1; trait ditambahkan di Task 2):

```php
<?php

namespace App\Models;

use Database\Factories\SpecialtyFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Specialty extends Model
{
    /** @use HasFactory<SpecialtyFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Dokter yang memiliki spesialisasi ini; pivot membawa `is_primary` (tanpa model pivot, 11 §5.8).
     */
    public function doctors(): BelongsToMany
    {
        return $this->belongsToMany(Doctor::class, 'doctor_specialties')->withPivot('is_primary');
    }

    public function clinics(): HasMany
    {
        return $this->hasMany(Clinic::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Alasan penghapusan ditolak (F2-R18), atau null bila boleh dihapus. Dipanggil controller
     * sebelum delete(), sehingga berlaku juga untuk Super Admin; FK RESTRICT hanya jaring pengaman.
     */
    public function deletionBlockedReason(): ?string
    {
        if ($this->doctors()->exists() || $this->clinics()->exists()) {
            return 'Spesialisasi ini masih dipakai oleh dokter atau poliklinik sehingga tidak dapat dihapus. Nonaktifkan saja.';
        }

        return null;
    }
}
```

`database/factories/SpecialtyFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Specialty;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Specialty>
 */
class SpecialtyFactory extends Factory
{
    /**
     * Spesialisasi aktif dengan nama unik.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => Str::title(fake()->unique()->words(2, true)),
            // Slug sementara dari nama; dihapus di Task 2 saat trait HasUniqueSlug mengambil alih.
            'slug' => fn (array $attributes): string => Str::slug((string) $attributes['name']),
            'description' => fake()->sentence(),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    /** Spesialisasi nonaktif: tidak muncul di filter publik. */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
```

- [ ] **Step 10: Jalankan tes — harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=SpecialtyModelTest
```
Expected: PASS — `Tests: 7 passed`.

- [ ] **Step 11: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint
& $PHP vendor\bin\pint --test
git add app/Models/Specialty.php database/factories/SpecialtyFactory.php tests/Feature/Models/SpecialtyModelTest.php
git commit -m @'
feat(fase-2): model Specialty dan SpecialtyFactory

Fillable, casts, relasi doctors/clinics, scope active/ordered, dan deletionBlockedReason.

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
'@
```

**Siklus C — `Clinic` dan `ClinicFactory`**

- [ ] **Step 12: Tulis tes yang gagal (tool Write) `tests/Feature/Models/ClinicModelTest.php`**

Tes penolakan hapus yang melibatkan dokter ada di Siklus D (membutuhkan model `Doctor`).

```php
<?php

namespace Tests\Feature\Models;

use App\Enums\ClinicType;
use App\Models\Clinic;
use App\Models\Specialty;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClinicModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_fillable_follows_the_blueprint(): void
    {
        $this->assertSame([
            'name', 'slug', 'code', 'type', 'is_bookable', 'specialty_id', 'short_description',
            'description', 'location', 'is_active', 'sort_order', 'meta_title', 'meta_description',
        ], (new Clinic)->getFillable());
    }

    public function test_unknown_attributes_are_rejected_in_strict_mode(): void
    {
        $this->expectException(MassAssignmentException::class);

        Clinic::factory()->make()->fill(['bukan_kolom' => 'x']);
    }

    public function test_casts_and_database_defaults(): void
    {
        $created = Clinic::create([
            'name' => 'Poliklinik Anak', 'slug' => 'poliklinik-anak', 'code' => 'ANK', 'type' => 'poliklinik',
            'short_description' => 'Layanan anak.', 'location' => 'Gedung A Lt. 1',
        ]);
        $clinic = Clinic::query()->findOrFail($created->id);

        $this->assertSame(ClinicType::Poliklinik, $clinic->type);
        $this->assertTrue($clinic->is_bookable);
        $this->assertTrue($clinic->is_active);
        $this->assertSame(0, $clinic->sort_order);
        $this->assertNull($clinic->specialty_id);
        $this->assertNull($clinic->description);
    }

    public function test_code_mutator_uppercases_and_trims(): void
    {
        $clinic = Clinic::factory()->create(['code' => ' ank ']);

        $this->assertSame('ANK', $clinic->code);
        $this->assertSame('ANK', $clinic->fresh()->code);

        $clinic->update(['code' => 'pd']);
        $this->assertSame('PD', $clinic->fresh()->code);
    }

    public function test_factory_states(): void
    {
        $plain = Clinic::factory()->create();
        $inactive = Clinic::factory()->inactive()->create();
        $support = Clinic::factory()->support()->create();

        $this->assertTrue($plain->is_active && $plain->is_bookable);
        $this->assertSame(ClinicType::Poliklinik, $plain->type);
        $this->assertFalse($inactive->is_active);
        $this->assertSame(ClinicType::Penunjang, $support->type);
        $this->assertFalse($support->is_bookable);
        $this->assertTrue($support->is_active);
        $this->assertMatchesRegularExpression('/^[A-Z]{3}$/', $plain->code);
    }

    public function test_active_bookable_and_ordered_scopes(): void
    {
        $b = Clinic::factory()->create(['name' => 'Poliklinik B', 'sort_order' => 1]);
        $a = Clinic::factory()->create(['name' => 'Poliklinik A', 'sort_order' => 1]);
        $c = Clinic::factory()->create(['name' => 'Poliklinik C', 'sort_order' => 0]);
        $off = Clinic::factory()->inactive()->create();
        $support = Clinic::factory()->support()->create();
        $inactiveBookable = Clinic::factory()->inactive()->create(['is_bookable' => true]);

        $this->assertEqualsCanonicalizing(
            [$a->id, $b->id, $c->id, $support->id],
            Clinic::query()->active()->pluck('id')->all(),
        );
        $this->assertEqualsCanonicalizing(
            [$a->id, $b->id, $c->id],
            Clinic::query()->bookable()->pluck('id')->all(),
        );
        $this->assertNotContains($off->id, Clinic::query()->bookable()->pluck('id')->all());
        $this->assertNotContains($inactiveBookable->id, Clinic::query()->bookable()->pluck('id')->all());
        $this->assertSame(
            [$c->id, $a->id, $b->id],
            Clinic::query()->bookable()->ordered()->pluck('id')->all(),
        );
    }

    public function test_seo_accessors_fall_back_to_name_and_short_description(): void
    {
        $plain = Clinic::factory()->create(['name' => 'Poliklinik Anak', 'short_description' => 'Layanan anak.']);
        $custom = Clinic::factory()->create(['meta_title' => 'Judul SEO', 'meta_description' => 'Deskripsi SEO']);
        $blank = Clinic::factory()->create(['name' => 'Poliklinik Mata', 'short_description' => 'Layanan mata.', 'meta_title' => '', 'meta_description' => '']);

        $this->assertSame('Poliklinik Anak', $plain->seo_title);
        $this->assertSame('Layanan anak.', $plain->seo_description);
        $this->assertSame('Judul SEO', $custom->seo_title);
        $this->assertSame('Deskripsi SEO', $custom->seo_description);
        $this->assertSame('Poliklinik Mata', $blank->seo_title);
        $this->assertSame('Layanan mata.', $blank->seo_description);
    }

    public function test_specialty_relation_and_set_null_on_specialty_delete(): void
    {
        $specialty = Specialty::factory()->create();
        $clinic = Clinic::factory()->create(['specialty_id' => $specialty->id]);

        $this->assertTrue($clinic->fresh()->load('specialty')->specialty->is($specialty));
        $this->assertSame([$clinic->id], $specialty->clinics()->pluck('id')->all());

        $specialty->delete();

        $this->assertNull(Clinic::query()->find($clinic->id)->specialty_id);
    }

    public function test_relations_are_not_lazy_loaded_in_strict_mode(): void
    {
        $specialty = Specialty::factory()->create();
        Clinic::factory()->count(2)->create(['specialty_id' => $specialty->id]);

        $clinics = Clinic::query()->get();

        $this->expectException(LazyLoadingViolationException::class);

        $clinics->each(fn (Clinic $clinic) => $clinic->specialty);
    }
}
```

- [ ] **Step 13: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=ClinicModelTest
```
Expected: FAIL — 9 tes ERROR `Class "App\Models\Clinic" not found`.

- [ ] **Step 14: Tulis model dan factory (tool Write)**

`app/Models/Clinic.php` (versi Task 1):

```php
<?php

namespace App\Models;

use App\Enums\ClinicType;
use Database\Factories\ClinicFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Clinic extends Model
{
    /** @use HasFactory<ClinicFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'code',
        'type',
        'is_bookable',
        'specialty_id',
        'short_description',
        'description',
        'location',
        'is_active',
        'sort_order',
        'meta_title',
        'meta_description',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'type' => ClinicType::class,
            'is_bookable' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Kode antrean selalu disimpan huruf kapital tanpa spasi tepi (awalan nomor antrean, mis. ANK).
     *
     * @return Attribute<string|null, string|null>
     */
    protected function code(): Attribute
    {
        return Attribute::set(fn (?string $value): ?string => $value === null ? null : Str::upper(trim($value)));
    }

    /**
     * Judul SEO: `meta_title`, atau nama bila kosong (09 §3.2).
     *
     * @return Attribute<string, never>
     */
    protected function seoTitle(): Attribute
    {
        return Attribute::get(fn (): string => filled($this->meta_title) ? (string) $this->meta_title : (string) $this->name);
    }

    /**
     * Deskripsi SEO: `meta_description`, atau `short_description` bila kosong (09 §3.2).
     *
     * @return Attribute<string, never>
     */
    protected function seoDescription(): Attribute
    {
        return Attribute::get(fn (): string => filled($this->meta_description) ? (string) $this->meta_description : (string) $this->short_description);
    }

    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class);
    }

    public function doctors(): HasMany
    {
        return $this->hasMany(Doctor::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Aktif dan boleh dipakai untuk pendaftaran kunjungan. */
    public function scopeBookable(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('is_bookable', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Alasan penghapusan ditolak (F2-R18), atau null bila boleh dihapus. Fase 3 menambah jadwal,
     * sesi, dan appointment pada pemeriksaan ini.
     */
    public function deletionBlockedReason(): ?string
    {
        if ($this->doctors()->exists()) {
            return 'Poliklinik ini masih dirujuk oleh data dokter sehingga tidak dapat dihapus. Nonaktifkan saja.';
        }

        return null;
    }
}
```

`database/factories/ClinicFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\ClinicType;
use App\Models\Clinic;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Clinic>
 */
class ClinicFactory extends Factory
{
    /**
     * Poliklinik aktif yang dapat dipesan, dengan nama dan kode unik.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Poliklinik '.Str::title(fake()->unique()->words(2, true)),
            // Slug sementara dari nama; dihapus di Task 2 saat trait HasUniqueSlug mengambil alih.
            'slug' => fn (array $attributes): string => Str::slug((string) $attributes['name']),
            'code' => Str::upper(fake()->unique()->lexify('???')),
            'type' => ClinicType::Poliklinik,
            'is_bookable' => true,
            'specialty_id' => null,
            'short_description' => fake()->sentence(),
            'description' => null,
            'location' => 'Gedung A Lt. 1',
            'is_active' => true,
            'sort_order' => 0,
            'meta_title' => null,
            'meta_description' => null,
        ];
    }

    /** Poliklinik/layanan nonaktif: halaman publiknya 404. */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    /** Layanan penunjang (mis. Laboratorium): tidak dapat dipesan (09 §3.2). */
    public function support(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => ClinicType::Penunjang,
            'is_bookable' => false,
        ]);
    }
}
```

- [ ] **Step 15: Jalankan tes — harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=ClinicModelTest
```
Expected: PASS — `Tests: 9 passed`.

- [ ] **Step 16: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint
& $PHP vendor\bin\pint --test
git add app/Models/Clinic.php database/factories/ClinicFactory.php tests/Feature/Models/ClinicModelTest.php
git commit -m @'
feat(fase-2): model Clinic dan ClinicFactory

Casts enum, mutator code huruf kapital, scope active/bookable/ordered, accessor SEO,
dan factory dengan state inactive() dan support().

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
'@
```

**Siklus D — `Doctor`, `DoctorFactory`, `InteractsWithMasterData`**

- [ ] **Step 17: Tulis tes yang gagal (tool Write)**

`tests/Feature/Models/DoctorModelTest.php`:

```php
<?php

namespace Tests\Feature\Models;

use App\Enums\DoctorStatus;
use App\Enums\Gender;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Specialty;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithMasterData;
use Tests\TestCase;

class DoctorModelTest extends TestCase
{
    use InteractsWithMasterData;
    use RefreshDatabase;

    public function test_fillable_follows_the_blueprint_and_excludes_archived_at(): void
    {
        $this->assertSame([
            'clinic_id', 'name', 'slug', 'title_prefix', 'title_suffix', 'gender', 'short_bio', 'bio',
            'status', 'sort_order', 'meta_title', 'meta_description',
        ], (new Doctor)->getFillable());

        $this->expectException(MassAssignmentException::class);

        Doctor::factory()->make()->fill(['archived_at' => now()]);
    }

    public function test_casts(): void
    {
        $doctor = Doctor::query()->findOrFail($this->createDoctor(['gender' => 'P'])->id);

        $this->assertSame(Gender::Female, $doctor->gender);
        $this->assertSame(DoctorStatus::Active, $doctor->status);
        $this->assertNull($doctor->archived_at);
        $this->assertSame(0, $doctor->sort_order);

        $archived = Doctor::query()->findOrFail(Doctor::factory()->archived()->create()->id);

        $this->assertSame(DoctorStatus::Archived, $archived->status);
        $this->assertInstanceOf(CarbonInterface::class, $archived->archived_at);
    }

    /** @return array<string, array{0: ?string, 1: string, 2: ?string, 3: string}> CRD-04 */
    public static function displayNames(): array
    {
        return [
            'gelar depan dan belakang' => ['dr.', 'Laras Wulandari', 'Sp.A', 'dr. Laras Wulandari, Sp.A'],
            'tanpa sufiks' => ['dr.', 'Rani Oktaviani', null, 'dr. Rani Oktaviani'],
            'sufiks kosong' => ['drg.', 'Ayu Larasati Hakim', '', 'drg. Ayu Larasati Hakim'],
            'tanpa gelar depan' => [null, 'Eko Susanto', 'Sp.B', 'Eko Susanto, Sp.B'],
            'tanpa gelar sama sekali' => [null, 'Eko Susanto', null, 'Eko Susanto'],
            'spasi berlebih' => [' dr. ', ' Eko Susanto ', ' Sp.B ', 'dr. Eko Susanto, Sp.B'],
            'sufiks bertanda' => ['dr.', 'Yoga Prasetya', 'Sp.THT-KL', 'dr. Yoga Prasetya, Sp.THT-KL'],
        ];
    }

    #[DataProvider('displayNames')]
    public function test_display_name(?string $prefix, string $name, ?string $suffix, string $expected): void
    {
        $doctor = Doctor::factory()->make(['title_prefix' => $prefix, 'name' => $name, 'title_suffix' => $suffix]);

        $this->assertSame($expected, $doctor->display_name);
        $this->assertSame($expected, $doctor->displayName);
    }

    public function test_primary_specialty_uses_the_primary_pivot_flag_with_first_as_fallback(): void
    {
        $general = $this->createSpecialty(['name' => 'Penyakit Dalam']);
        $heart = $this->createSpecialty(['name' => 'Jantung']);

        $doctor = Doctor::factory()->create();
        $doctor->specialties()->attach($general->id, ['is_primary' => false]);
        $doctor->specialties()->attach($heart->id, ['is_primary' => true]);

        $this->assertTrue(Doctor::query()->with('specialties')->find($doctor->id)->primary_specialty->is($heart));

        $noPrimary = Doctor::factory()->create();
        $noPrimary->specialties()->attach([$general->id => ['is_primary' => false]]);

        $this->assertTrue(Doctor::query()->with('specialties')->find($noPrimary->id)->primary_specialty->is($general));

        $none = Doctor::factory()->create();

        $this->assertNull(Doctor::query()->with('specialties')->find($none->id)->primary_specialty);
    }

    public function test_primary_specialty_is_not_cached_across_reloads_of_the_relation(): void
    {
        $first = $this->createSpecialty();
        $second = $this->createSpecialty();
        $doctor = $this->createDoctor([], null, [$first, $second]);

        $this->assertTrue($doctor->primary_specialty->is($first));

        $doctor->specialties()->updateExistingPivot($first->id, ['is_primary' => false]);
        $doctor->specialties()->updateExistingPivot($second->id, ['is_primary' => true]);
        $doctor->load('specialties');

        $this->assertTrue($doctor->primary_specialty->is($second));
    }

    public function test_primary_specialty_requires_the_loaded_relation_in_strict_mode(): void
    {
        $this->createDoctor();
        $this->createDoctor();
        $doctors = Doctor::query()->get();

        $this->expectException(LazyLoadingViolationException::class);

        $doctors->first()->primary_specialty;
    }

    public function test_list_with_eager_loading_is_safe_for_several_rows_in_strict_mode(): void
    {
        foreach (range(1, 4) as $i) {
            $this->createDoctor([], null, [$this->createSpecialty(), $this->createSpecialty()]);
        }

        $names = Doctor::query()->with(['clinic', 'specialties'])->get()->map(
            fn (Doctor $doctor): string => $doctor->display_name.'|'.$doctor->clinic->name.'|'.$doctor->primary_specialty->name,
        );

        $this->assertCount(4, $names);
    }

    public function test_relations_without_eager_loading_are_rejected_for_several_rows(): void
    {
        $this->createDoctor();
        $this->createDoctor();
        $doctors = Doctor::query()->get();

        $this->expectException(LazyLoadingViolationException::class);

        $doctors->each(fn (Doctor $doctor) => $doctor->clinic);
    }

    public function test_active_and_publicly_visible_scopes(): void
    {
        $activeClinic = $this->createClinic();
        $inactiveClinic = $this->createClinic(['is_active' => false]);

        $visible = $this->createDoctor([], $activeClinic);
        $inactiveDoctor = $this->createDoctor(['status' => DoctorStatus::Inactive], $activeClinic);
        $archived = Doctor::factory()->archived()->create(['clinic_id' => $activeClinic->id]);
        $inInactiveClinic = $this->createDoctor([], $inactiveClinic);

        $this->assertEqualsCanonicalizing(
            [$visible->id, $inInactiveClinic->id],
            Doctor::query()->active()->pluck('id')->all(),
        );
        $this->assertSame([$visible->id], Doctor::query()->publiclyVisible()->pluck('id')->all());
        $this->assertNotContains($inactiveDoctor->id, Doctor::query()->publiclyVisible()->pluck('id')->all());
        $this->assertNotContains($archived->id, Doctor::query()->publiclyVisible()->pluck('id')->all());
    }

    public function test_search_matches_doctor_name_or_specialty_name_and_treats_wildcards_literally(): void
    {
        $urology = $this->createSpecialty(['name' => 'Urologi']);
        $pediatrics = $this->createSpecialty(['name' => 'Anak']);

        $raka = $this->createDoctor(['name' => 'Raka Pradipta'], null, [$urology]);
        $laras = $this->createDoctor(['name' => 'Laras Wulandari'], null, [$pediatrics]);
        $percent = $this->createDoctor(['name' => 'Diskon 100% Sehat'], null, [$pediatrics]);

        $this->assertSame([$raka->id], Doctor::query()->search('raka')->pluck('id')->all());
        $this->assertSame([$raka->id], Doctor::query()->search('urolog')->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$laras->id, $percent->id], Doctor::query()->search('Anak')->pluck('id')->all());
        $this->assertSame([$percent->id], Doctor::query()->search('100%')->pluck('id')->all());
        $this->assertSame([], Doctor::query()->search('%')->where('name', 'Raka Pradipta')->pluck('id')->all());
        $this->assertCount(3, Doctor::query()->search(null)->get());
        $this->assertCount(3, Doctor::query()->search('   ')->get());
    }

    public function test_in_specialty_accepts_id_or_slug_and_matches_secondary_specialties(): void
    {
        $internal = $this->createSpecialty(['name' => 'Penyakit Dalam', 'slug' => 'penyakit-dalam']);
        $heart = $this->createSpecialty(['name' => 'Jantung', 'slug' => 'jantung']);

        $sekar = $this->createDoctor(['name' => 'Sekar'], null, [$internal]);
        $maya = $this->createDoctor(['name' => 'Maya'], null, [$heart, $internal]);
        $fajar = $this->createDoctor(['name' => 'Fajar'], null, [$heart]);

        $this->assertEqualsCanonicalizing([$sekar->id, $maya->id], Doctor::query()->inSpecialty('penyakit-dalam')->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$sekar->id, $maya->id], Doctor::query()->inSpecialty($internal->id)->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$maya->id, $fajar->id], Doctor::query()->inSpecialty('jantung')->pluck('id')->all());
        $this->assertSame([], Doctor::query()->inSpecialty('tidak-ada')->pluck('id')->all());
        $this->assertSame([], Doctor::query()->inSpecialty((string) $internal->id)->pluck('id')->all(), 'String dibaca sebagai slug, bukan id.');
    }

    public function test_in_clinic_and_ordered_scopes(): void
    {
        $clinicA = $this->createClinic();
        $clinicB = $this->createClinic();

        $z = $this->createDoctor(['name' => 'Zaki', 'sort_order' => 1], $clinicA);
        $a = $this->createDoctor(['name' => 'Adi', 'sort_order' => 1], $clinicA);
        $first = $this->createDoctor(['name' => 'Yusuf', 'sort_order' => 0], $clinicB);

        $this->assertEqualsCanonicalizing([$z->id, $a->id], Doctor::query()->inClinic($clinicA->id)->pluck('id')->all());
        $this->assertSame([$first->id, $a->id, $z->id], Doctor::query()->ordered()->pluck('id')->all());
    }

    public function test_factory_with_specialties_marks_exactly_the_first_as_primary(): void
    {
        $doctor = Doctor::factory()->withSpecialties(3)->create();

        $pivot = DB::table('doctor_specialties')->where('doctor_id', $doctor->id)->orderBy('specialty_id')->get();

        $this->assertCount(3, $pivot);
        $this->assertSame(1, $pivot->where('is_primary', 1)->count());
        $this->assertSame((int) $pivot->first()->specialty_id, (int) $pivot->firstWhere('is_primary', 1)->specialty_id);
        $this->assertSame(1, Doctor::factory()->withSpecialties()->create()->specialties()->count());
    }

    public function test_factory_states_and_dictionary_invariant_i23(): void
    {
        $inactive = Doctor::factory()->inactive()->create();
        $archived = Doctor::factory()->archived()->create();
        $active = Doctor::factory()->create();

        $this->assertSame(DoctorStatus::Inactive, $inactive->fresh()->status);
        $this->assertNull($inactive->fresh()->archived_at);
        $this->assertSame(DoctorStatus::Archived, $archived->fresh()->status);
        $this->assertNotNull($archived->fresh()->archived_at);
        $this->assertSame(
            0,
            Doctor::query()->whereRaw("(status = 'archived') <> (archived_at IS NOT NULL)")->count(),
            'K-23: status archived <=> archived_at terisi.',
        );
        $this->assertSame(DoctorStatus::Active, $active->fresh()->status);
    }

    public function test_deleting_a_clinic_that_has_doctors_is_rejected_for_the_model_too(): void
    {
        $doctor = $this->createDoctor();

        $this->expectException(QueryException::class);

        $doctor->clinic()->first()->delete();
    }

    public function test_clinic_deletion_blocked_reason(): void
    {
        $empty = $this->createClinic();
        $used = $this->createDoctor()->clinic()->first();

        $this->assertNull($empty->deletionBlockedReason());
        $this->assertSame(
            'Poliklinik ini masih dirujuk oleh data dokter sehingga tidak dapat dihapus. Nonaktifkan saja.',
            $used->deletionBlockedReason(),
        );
    }

    public function test_specialty_deletion_blocked_reason_for_doctors_and_clinics(): void
    {
        $free = $this->createSpecialty();
        $usedByDoctor = $this->createSpecialty();
        $usedByClinic = $this->createSpecialty();
        $this->createDoctor([], null, [$usedByDoctor]);
        $this->createClinic(['specialty_id' => $usedByClinic->id]);

        $message = 'Spesialisasi ini masih dipakai oleh dokter atau poliklinik sehingga tidak dapat dihapus. Nonaktifkan saja.';

        $this->assertNull($free->deletionBlockedReason());
        $this->assertSame($message, $usedByDoctor->deletionBlockedReason());
        $this->assertSame($message, $usedByClinic->deletionBlockedReason());
    }

    public function test_specialty_doctors_relation_exposes_the_pivot_flag(): void
    {
        $specialty = $this->createSpecialty();
        $primary = $this->createDoctor([], null, [$specialty]);
        $secondary = $this->createDoctor([], null, [$this->createSpecialty(), $specialty]);

        $flags = $specialty->doctors()->get()->mapWithKeys(
            fn (Doctor $doctor): array => [$doctor->id => (bool) $doctor->pivot->is_primary],
        )->all();

        $this->assertSame([$primary->id => true, $secondary->id => false], $flags);
        $this->assertInstanceOf(Clinic::class, $primary->clinic()->first());
        $this->assertInstanceOf(Specialty::class, $primary->specialties->first());
    }
}
```

`tests/Feature/Models/InteractsWithMasterDataTest.php`:

```php
<?php

namespace Tests\Feature\Models;

use App\Enums\ClinicType;
use App\Models\Clinic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\InteractsWithMasterData;
use Tests\TestCase;

class InteractsWithMasterDataTest extends TestCase
{
    use InteractsWithMasterData;
    use RefreshDatabase;

    public function test_create_specialty_and_clinic_accept_overrides(): void
    {
        $specialty = $this->createSpecialty(['name' => 'Anak', 'slug' => 'anak']);
        $clinic = $this->createClinic(['specialty_id' => $specialty->id, 'code' => 'ank']);

        $this->assertSame('Anak', $specialty->name);
        $this->assertSame('ANK', $clinic->fresh()->code);
        $this->assertSame($specialty->id, $clinic->specialty_id);
    }

    public function test_create_doctor_without_arguments_builds_clinic_and_one_primary_specialty(): void
    {
        $doctor = $this->createDoctor();

        $this->assertSame(1, Clinic::query()->count());
        $this->assertCount(1, $doctor->specialties);
        $this->assertSame(1, (int) $doctor->specialties->first()->pivot->is_primary);
        $this->assertSame($doctor->specialties->first()->id, $doctor->primary_specialty->id);
        $this->assertSame(ClinicType::Poliklinik, $doctor->clinic()->first()->type);
    }

    public function test_create_doctor_uses_the_given_clinic_and_specialties_with_the_first_primary(): void
    {
        $clinic = $this->createClinic();
        $first = $this->createSpecialty();
        $second = $this->createSpecialty();

        $doctor = $this->createDoctor(['name' => 'Maya Kartika Sari'], $clinic, [$first, $second]);

        $this->assertSame($clinic->id, $doctor->clinic_id);
        $this->assertSame('Maya Kartika Sari', $doctor->name);
        $this->assertSame(1, Clinic::query()->count());
        $this->assertSame(
            [$first->id => 1, $second->id => 0],
            DB::table('doctor_specialties')->where('doctor_id', $doctor->id)->pluck('is_primary', 'specialty_id')->map(fn ($v) => (int) $v)->all(),
        );
        $this->assertTrue($doctor->primary_specialty->is($first));
    }

    public function test_create_doctor_takes_clinic_id_from_attributes_when_no_clinic_is_given(): void
    {
        $clinic = $this->createClinic();

        $doctor = $this->createDoctor(['clinic_id' => $clinic->id]);

        $this->assertSame($clinic->id, $doctor->clinic_id);
        $this->assertSame(1, Clinic::query()->count());
    }
}
```

- [ ] **Step 18: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="DoctorModelTest|InteractsWithMasterDataTest"
```
Expected: FAIL/ERROR — kedua kelas gagal dimuat: `Trait "Tests\Support\InteractsWithMasterData" not found`.

- [ ] **Step 19: Tulis model, factory, dan pembantu tes (tool Write)**

`app/Models/Doctor.php` (versi Task 1):

```php
<?php

namespace App\Models;

use App\Enums\DoctorStatus;
use App\Enums\Gender;
use Database\Factories\DoctorFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Doctor extends Model
{
    /** @use HasFactory<DoctorFactory> */
    use HasFactory;

    /**
     * `archived_at` sengaja tidak termasuk: diisi `DoctorService` (Task 8) lewat forceFill().
     *
     * @var list<string>
     */
    protected $fillable = [
        'clinic_id',
        'name',
        'slug',
        'title_prefix',
        'title_suffix',
        'gender',
        'short_bio',
        'bio',
        'status',
        'sort_order',
        'meta_title',
        'meta_description',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'status' => DoctorStatus::class,
            'archived_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Nama tampilan "{title_prefix} {name}, {title_suffix}"; bagian kosong dibuang tanpa spasi
     * atau koma yatim (mis. "dr. Rani Oktaviani" tanpa sufiks, "Rani Oktaviani" tanpa gelar).
     *
     * @return Attribute<string, never>
     */
    protected function displayName(): Attribute
    {
        return Attribute::get(function (): string {
            $base = trim(trim((string) $this->title_prefix).' '.trim((string) $this->name));
            $suffix = trim((string) $this->title_suffix);

            return $suffix === '' ? $base : $base.', '.$suffix;
        });
    }

    /**
     * Spesialisasi utama (F2-R24): pivot `is_primary` dari relasi `specialties`, dengan cadangan
     * spesialisasi pertama. Relasi `specialties` wajib sudah dimuat (mode ketat melarang lazy loading).
     * Tanpa cache objek: hasilnya mengikuti relasi yang dimuat ulang.
     *
     * @return Attribute<Specialty|null, never>
     */
    protected function primarySpecialty(): Attribute
    {
        return Attribute::get(function (): ?Specialty {
            $specialties = $this->specialties;

            return $specialties->first(fn (Specialty $specialty): bool => (bool) $specialty->pivot->is_primary)
                ?? $specialties->first();
        })->withoutObjectCaching();
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function specialties(): BelongsToMany
    {
        return $this->belongsToMany(Specialty::class, 'doctor_specialties')->withPivot('is_primary');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', DoctorStatus::Active->value);
    }

    /** Tampil di publik: dokter aktif pada poliklinik yang aktif (F.2/F.3, F2-R28). */
    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query
            ->where('status', DoctorStatus::Active->value)
            ->whereHas('clinic', fn (Builder $clinic): Builder => $clinic->where('is_active', true));
    }

    /** Cari nama dokter atau nama spesialisasi (LIKE; karakter % dan _ pada input diperlakukan literal). */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        return $query->where(function (Builder $inner) use ($like): void {
            $inner->where('doctors.name', 'like', $like)
                ->orWhereHas('specialties', fn (Builder $specialty): Builder => $specialty->where('specialties.name', 'like', $like));
        });
    }

    /**
     * Saring menurut spesialisasi mana pun (utama atau tambahan). Bilangan bulat dibaca sebagai id;
     * string selalu dibaca sebagai slug (parameter kueri `spesialisasi` berisi slug).
     */
    public function scopeInSpecialty(Builder $query, int|string $idOrSlug): Builder
    {
        return $query->whereHas('specialties', function (Builder $specialty) use ($idOrSlug): void {
            is_int($idOrSlug)
                ? $specialty->where('specialties.id', $idOrSlug)
                : $specialty->where('specialties.slug', $idOrSlug);
        });
    }

    public function scopeInClinic(Builder $query, int $id): Builder
    {
        return $query->where('doctors.clinic_id', $id);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('doctors.sort_order')->orderBy('doctors.name');
    }
}
```

`database/factories/DoctorFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\DoctorStatus;
use App\Enums\Gender;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Specialty;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Doctor>
 */
class DoctorFactory extends Factory
{
    /**
     * Dokter aktif tanpa spesialisasi (gunakan withSpecialties()). Nama sengaja tanpa gelar
     * (kolom `name` tidak memuat gelar; gelar ada di `title_prefix`/`title_suffix`).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'clinic_id' => Clinic::factory(),
            'name' => fake()->unique()->firstName().' '.fake()->lastName(),
            // Slug sementara dari nama; dihapus di Task 2 saat trait HasUniqueSlug mengambil alih.
            'slug' => fn (array $attributes): string => Str::slug((string) $attributes['name']),
            'title_prefix' => 'dr.',
            'title_suffix' => 'Sp.PD',
            'gender' => fake()->randomElement(Gender::cases()),
            'short_bio' => fake()->sentence(),
            'bio' => fake()->paragraph(),
            'status' => DoctorStatus::Active,
            'archived_at' => null,
            'sort_order' => 0,
            'meta_title' => null,
            'meta_description' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => DoctorStatus::Inactive,
        ]);
    }

    /** Diarsipkan: `archived_at` terisi (invarian I-23). Pabrik berjalan tanpa guard, jadi kolom ini boleh diisi. */
    public function archived(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => DoctorStatus::Archived,
            'archived_at' => now()->subDays(30),
        ]);
    }

    /**
     * Membuat $count spesialisasi baru lalu menautkannya; yang pertama menjadi spesialisasi utama
     * (invarian I-5: tepat satu `is_primary`).
     */
    public function withSpecialties(int $count = 1): static
    {
        return $this->afterCreating(function (Doctor $doctor) use ($count): void {
            foreach (Specialty::factory()->count(max($count, 1))->create() as $index => $specialty) {
                $doctor->specialties()->attach($specialty->id, ['is_primary' => $index === 0]);
            }
        });
    }
}
```

`tests/Support/InteractsWithMasterData.php`:

```php
<?php

namespace Tests\Support;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Specialty;

/**
 * Pembantu tes untuk data induk (spesialisasi, poliklinik, dokter) yang siap dipakai.
 */
trait InteractsWithMasterData
{
    /** @param  array<string, mixed>  $attrs */
    protected function createSpecialty(array $attrs = []): Specialty
    {
        return Specialty::factory()->create($attrs);
    }

    /** @param  array<string, mixed>  $attrs */
    protected function createClinic(array $attrs = []): Clinic
    {
        return Clinic::factory()->create($attrs);
    }

    /**
     * Membuat dokter dengan minimal satu spesialisasi; spesialisasi pertama menjadi utama.
     * Poliklinik: argumen $clinic, lalu `clinic_id` pada $attrs, lalu poliklinik baru.
     * Relasi `specialties` pada hasil sudah dimuat (aman di mode ketat).
     *
     * @param  array<string, mixed>  $attrs
     * @param  array<int, Specialty>  $specialties
     */
    protected function createDoctor(array $attrs = [], ?Clinic $clinic = null, array $specialties = []): Doctor
    {
        $clinicId = $clinic?->id ?? $attrs['clinic_id'] ?? $this->createClinic()->id;
        $doctor = Doctor::factory()->create(array_merge($attrs, ['clinic_id' => $clinicId]));

        foreach (array_values($specialties === [] ? [$this->createSpecialty()] : $specialties) as $index => $specialty) {
            $doctor->specialties()->attach($specialty->id, ['is_primary' => $index === 0]);
        }

        return $doctor->load('specialties');
    }
}
```

- [ ] **Step 20: Jalankan tes — harus lulus, lalu seluruh tes Task 1 dan suite**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="DoctorModelTest|InteractsWithMasterDataTest"
& $PHP artisan test --filter="MasterDataSchemaTest|SpecialtyModelTest|ClinicModelTest|DoctorModelTest|InteractsWithMasterDataTest"
& $PHP artisan test
```
Expected: PASS — `Tests: 28 passed`, lalu `Tests: 118 passed` untuk filter Task 1, lalu seluruh suite PASS.

- [ ] **Step 21: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint
& $PHP vendor\bin\pint --test
git add app/Models/Doctor.php database/factories/DoctorFactory.php tests/Support/InteractsWithMasterData.php tests/Feature/Models/DoctorModelTest.php tests/Feature/Models/InteractsWithMasterDataTest.php
git commit -m @'
feat(fase-2): model Doctor, DoctorFactory, dan pembantu tes data induk

Nama tampilan, spesialisasi utama (accessor dari relasi yang dimuat), scope publiclyVisible/search/
inSpecialty/inClinic, factory inactive/archived/withSpecialties, dan InteractsWithMasterData.
Tes menjaga mode ketat dengan beberapa baris sekaligus.

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
'@
```

---

### Task 2: Trait model — slug unik, sanitasi rich text, media

**Files:**
- Create: `app/Models/Concerns/HasUniqueSlug.php`, `app/Models/Concerns/SanitizesRichText.php`, `app/Models/Concerns/HasMedia.php`
- Modify: `app/Models/Specialty.php`, `app/Models/Clinic.php`, `app/Models/Doctor.php` (pasang trait, tambah accessor media)
- Modify: `database/factories/SpecialtyFactory.php`, `ClinicFactory.php`, `DoctorFactory.php` (hapus baris `slug` sementara)
- Modify: `app/Services/Media/MediaService.php` (`prune()` butir (c); `deleteFor()` dalam transaksi)
- Modify: `app/Console/Commands/PruneMedia.php` (hanya teks `$description`)
- Test: `tests/Feature/Models/HasUniqueSlugTest.php`, `SanitizesRichTextTest.php`, `HasMediaTest.php`, `tests/Feature/Media/PruneOwnerlessMediaTest.php`

**Interfaces:**
- Consumes: model `Specialty`/`Clinic`/`Doctor` + factory (Task 1), `HtmlSanitizer::clean(?string): string` (fase 1), `MediaService::{store, deleteFor, delete, reorder, prune}` (fase 1), `Media` (fase 1), `InteractsWithMasterData` (Task 1), `Tests\Support\{ImageFixtures, InteractsWithMedia}` (fase 1).
- Produces (persis kontrak):
  - `App\Models\Concerns\HasUniqueSlug`: `bootHasUniqueSlug()` (saat `creating`, bila `slug` kosong/spasi: `static::uniqueSlug($this->slugSource())`), `protected function slugSource(): string` (baku `name`), `abstract protected function slugMaxLength(): int` (diisi model: Specialty 120, Clinic 140, Doctor 170), `public static function uniqueSlug(string $source, ?int $ignoreId = null): string`. Sumber yang menghasilkan slug kosong memakai cadangan `Str::slug(class_basename)`-`Str::lower(Str::random(6))` (mis. `doctor-a1b2c3`).
  - `App\Models\Concerns\SanitizesRichText`: `abstract protected function richTextAttributes(): array`; saat `saving`, atribut di daftar itu yang `isDirty()` diganti `app(HtmlSanitizer::class)->clean($value)`; hasil `''` menjadi `null`. Dipasang hanya di `Clinic` (`['description']`).
  - `App\Models\Concerns\HasMedia`: `media(): MorphMany` (urut `sort_order`, `id`), `firstMedia(string $collection): ?Media`, `mediaIn(string $collection): Collection`, `mediaUrl(string $collection, string $variant = 'full', ?string $placeholder = null): string`; saat `deleting` memanggil `MediaService::deleteFor($this)`. Dipasang di `Doctor` dan `Clinic`.
  - Accessor: `Doctor::photo_url`, `Doctor::photo_thumb_url` (placeholder `images/placeholders/doctor.svg`), `Clinic::cover_url` (placeholder `images/placeholders/clinic.svg`).
  - `MediaService::prune()` butir (c): baris `media` dengan `model_type`/`model_id` terisi tetapi baris pemiliknya sudah tidak ada ikut dibersihkan; dihitung dalam `MediaPruneReport::$orphanRows` dan muncul di `items` saat dry-run. `MediaPruneReport` tidak berubah.
- Detail yang ditambahkan (dipakai task 6-16):
  - `mediaUrl()`: `$variant` hanya `full`/`thumb` (selain itu `InvalidArgumentException`); `$placeholder` adalah path di bawah `public/` (dipanggil lewat `asset()`), baku `images/placeholders/gallery.svg`. Bila relasi `media` sudah dimuat tidak ada query; bila belum, **satu query langsung** (bukan *lazy loading*, jadi tidak melempar tetapi menjadi N+1 pada daftar). Daftar wajib `with('media')`.
  - `mediaIn()` mengembalikan `Illuminate\Database\Eloquent\Collection` ber-indeks ulang (`values()`), urut relasi.
  - **Penghapusan pemilik atomik:** `HasMedia` menimpa `delete()` agar berjalan dalam `DB::transaction`. Bila baris pemilik gagal dihapus (mis. FK `doctors.clinic_id` RESTRICT), penghapusan baris media di event `deleting` ikut di-rollback dan berkas tidak dihapus (`MediaService::delete` menghapus berkas lewat `DB::afterCommit`). Controller tetap wajib memanggil `deletionBlockedReason()` lebih dulu (F2-R18).
  - Penyimpanan `Clinic.description` **hanya** aman lewat jalur model (`create`, `update`, `save`, `forceFill()->save()`, `updateOrCreate`, `firstOrCreate`). `Clinic::query()->update([...])` dan `upsert()` tidak memicu event model dan **dilarang** untuk kolom ini.
  - `slug` bukan `$fillable` yang wajib: factory dan `Model::create()` tanpa `slug` kini valid; slug tidak berubah saat nama diedit.
  - `MediaService::deleteFor()` kini dibungkus `DB::transaction` (carry CHECKPOINT §4).
  - Butir (c) tidak punya batas umur, melewati pemilik yang kelasnya tidak dikenal atau bukan `Model`, dan menganggap pemilik *soft-deleted* masih ada.
- **Catatan konflik:** kontrak dan 08 menyebut event `deleting` untuk `HasMedia`; event itu dipertahankan, tetapi ditambah pembungkus transaksi pada `delete()` karena `deleting` terjadi **sebelum** baris pemilik dihapus dan tanpa transaksi, kegagalan FK akan meninggalkan pemilik utuh dengan media sudah terhapus (dibuktikan tes `test_a_failed_owner_delete_keeps_rows_files_and_audit`). Kontrak juga menyebut "Modify `MediaService`" saja; `PruneMedia::$description` ikut disesuaikan satu baris.

**Test IDs:** Review Focus 1 (slug), Review Focus 3 (sanitasi semua jalur simpan), CRD-02/CRD-03 (slug), MED-07 (butir (c)), SEED-03 (dasar `media:prune --dry-run`).

**Catatan teknis (diverifikasi dari source):**
- `Str::slug()` (locale `en`): `"dr. Siti Nur'aini, Sp.A"` → `dr-siti-nuraini-spa`; `José Ñandú` → `jose-nandu`; `Ünal ß Æther` → `unal-ss-aether`; Arab `أحمد` → `ahmd`; **CJK (`東京`) dan simbol (`!!!`) → `''`** sehingga cadangan acak wajib. Kolom nama dibatasi (100/120/150), tetapi slug dapat lebih panjang karena transliterasi (`Æ` → `ae`), sehingga pemotongan ke panjang kolom dan `rtrim('-')` perlu diuji.
- Pemotongan dengan sufiks: basis dipotong ke `max - strlen('-N')` lalu `-N` ditambahkan, sehingga panjang akhir tidak pernah melebihi kolom dan tidak ada tanda hubung yatim.
- Slug dibuat hanya pada event `creating`; kolom UNIQUE tetap pengaman bila dua penyimpanan bersamaan lolos pemeriksaan yang sama (risiko terdokumentasi di 14 Risiko).
- `HtmlSanitizer::clean()` (diuji langsung): `<script>`/`onclick`/`onerror`/`style`/`iframe` dibuang; `<img>` dengan `data:` atau host luar dibuang; hanya `/storage/...` bertahan; `javascript:` pada `href` dibuang; tautan eksternal diberi `target="_blank" rel="noopener noreferrer"`; `<p></p>` dan spasi menghasilkan `''`.
- `isDirty()` pada model baru bernilai benar untuk semua atribut terisi, sehingga `create()` selalu disanitasi.
- Pada `HasMedia::delete()`, `parent::delete()` di dalam *closure* trait menunjuk `Model::delete()`. `DB::afterCommit` di dalam `RefreshDatabase` tetap berjalan saat transaksi tes bersarang dilepas, dan batal bila di-rollback (sudah dibuktikan `MediaServiceLifecycleTest::test_delete_waits_for_the_commit_and_keeps_everything_after_a_rollback`).
- `Illuminate\Database\Eloquent\Collection::concat()` menghasilkan koleksi Eloquent; `whereNotExists(Closure)` pada builder Eloquent diteruskan ke builder query (`->from($owner->getTable())->whereColumn($owner->getQualifiedKeyName(), 'media.model_id')`).
- Tanpa `Relation::morphMap`, `model_type` berisi nama kelas penuh (`App\Models\Clinic`); `Relation::getMorphedModel()` dipakai prune agar tetap benar bila morph map ditambahkan nanti.
- Semua blok PHP lolos `php -l` 8.2.

- [ ] **Step 1: Verifikasi prasyarat (Task 1 selesai)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git branch --show-current
git status --short
& $PHP artisan test --filter="MasterDataSchemaTest|DoctorModelTest|PruneMediaTest|MediaServiceLifecycleTest"
```
Expected: `fase-2-master-data`; `git status` bersih; seluruh tes PASS.

**Siklus A — `HasUniqueSlug`**

- [ ] **Step 2: Tulis tes yang gagal (tool Write) `tests/Feature/Models/HasUniqueSlugTest.php`**

```php
<?php

namespace Tests\Feature\Models;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Specialty;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\Support\InteractsWithMasterData;
use Tests\TestCase;

/**
 * Review Focus 1: nama yang menghasilkan slug bentrok atau aneh (F2-R5).
 */
class HasUniqueSlugTest extends TestCase
{
    use InteractsWithMasterData;
    use RefreshDatabase;

    /** @return array<string, array{0: string, 1: string}> */
    public static function nameToSlug(): array
    {
        return [
            'nama biasa' => ['Andi Pratama', 'andi-pratama'],
            'huruf besar dan spasi ganda' => ['  RANI   Oktaviani ', 'rani-oktaviani'],
            'apostrof dan titik' => ["Siti Nur'aini", 'siti-nuraini'],
            'apostrof tipografis' => ['Siti Nur’aini', 'siti-nuraini'],
            'gelar tertulis di nama' => ["dr. Siti Nur'aini, Sp.A", 'dr-siti-nuraini-spa'],
            'huruf beraksen' => ['José Ñandú', 'jose-nandu'],
            'umlaut dan cincin' => ['Müller Åström', 'muller-astrom'],
            'ligatur' => ['Ünal ß Æther', 'unal-ss-aether'],
            'angka' => ['Poliklinik 24 Jam', 'poliklinik-24-jam'],
            'garis bawah dan simbol' => ['Ahmad_Fauzi & Putra', 'ahmad-fauzi-putra'],
            'bahasa Arab ditransliterasi' => ['أحمد', 'ahmd'],
        ];
    }

    #[DataProvider('nameToSlug')]
    public function test_doctor_slug_is_derived_from_the_name(string $name, string $expected): void
    {
        $doctor = Doctor::factory()->create(['name' => $name, 'slug' => null]);

        $this->assertSame($expected, $doctor->slug);
        $this->assertSame($expected, $doctor->fresh()->slug);
    }

    public function test_two_doctors_with_the_same_name_get_numbered_slugs(): void
    {
        $clinic = $this->createClinic();

        $first = Doctor::factory()->create(['clinic_id' => $clinic->id, 'name' => 'Andi Pratama']);
        $second = Doctor::factory()->create(['clinic_id' => $clinic->id, 'name' => 'Andi Pratama']);
        $third = Doctor::factory()->create(['clinic_id' => $clinic->id, 'name' => 'Andi Pratama']);
        $other = Doctor::factory()->create(['clinic_id' => $clinic->id, 'name' => 'Andi Pratama Putra']);

        $this->assertSame('andi-pratama', $first->slug);
        $this->assertSame('andi-pratama-2', $second->slug);
        $this->assertSame('andi-pratama-3', $third->slug);
        $this->assertSame('andi-pratama-putra', $other->slug);
    }

    public function test_a_literal_number_suffix_in_a_name_does_not_break_numbering(): void
    {
        $clinic = $this->createClinic();

        $a = Doctor::factory()->create(['clinic_id' => $clinic->id, 'name' => 'Budi 2']);
        $b = Doctor::factory()->create(['clinic_id' => $clinic->id, 'name' => 'Budi']);
        $c = Doctor::factory()->create(['clinic_id' => $clinic->id, 'name' => 'Budi']);
        $d = Doctor::factory()->create(['clinic_id' => $clinic->id, 'name' => 'Budi']);

        $this->assertSame('budi-2', $a->slug);
        $this->assertSame('budi', $b->slug);
        $this->assertSame('budi-3', $c->slug, '"budi-2" sudah dipakai oleh nama "Budi 2", jadi penomoran lanjut ke -3.');
        $this->assertSame('budi-4', $d->slug);
    }

    public function test_slug_collision_is_case_insensitive_like_the_unique_index(): void
    {
        $specialty = Specialty::factory()->create(['name' => 'Anak', 'slug' => 'ANAK']);
        $next = Specialty::factory()->create(['name' => 'Anak', 'slug' => null]);

        $this->assertSame('ANAK', $specialty->slug);
        $this->assertSame('anak-2', $next->slug);
    }

    public function test_names_without_usable_characters_get_a_non_empty_fallback_slug(): void
    {
        foreach (['東京', '!!!', '---', '   '] as $name) {
            $doctor = Doctor::factory()->create(['name' => $name, 'slug' => null]);

            $this->assertMatchesRegularExpression('/^doctor-[a-z0-9]{6}$/', $doctor->slug, "Nama {$name}");
        }

        $this->assertMatchesRegularExpression('/^specialty-[a-z0-9]{6}$/', Specialty::factory()->create(['name' => '東京', 'slug' => null])->slug);
        $this->assertMatchesRegularExpression('/^clinic-[a-z0-9]{6}$/', Clinic::factory()->create(['name' => '!!!', 'slug' => null])->slug);
    }

    public function test_slug_is_not_changed_when_the_name_is_edited(): void
    {
        $clinic = Clinic::factory()->create(['name' => 'Poliklinik Anak', 'slug' => null]);

        $clinic->update(['name' => 'Poliklinik Anak dan Remaja']);

        $this->assertSame('poliklinik-anak', $clinic->fresh()->slug);

        $doctor = Doctor::factory()->create(['name' => 'Laras Wulandari']);
        $original = $doctor->slug;
        $doctor->update(['name' => 'Laras Wulandari Putri']);

        $this->assertSame($original, $doctor->fresh()->slug);
    }

    public function test_an_explicit_slug_is_kept_and_a_blank_slug_is_generated(): void
    {
        $explicit = Specialty::factory()->create(['name' => 'Penyakit Dalam', 'slug' => 'internis']);
        $blank = Specialty::factory()->create(['name' => 'Penyakit Dalam', 'slug' => '']);
        $spaces = Specialty::factory()->create(['name' => 'Jantung', 'slug' => '  ']);

        $this->assertSame('internis', $explicit->slug);
        $this->assertSame('penyakit-dalam', $blank->slug);
        $this->assertSame('jantung', $spaces->slug);
    }

    public function test_slug_is_generated_for_models_created_without_the_factory(): void
    {
        $specialty = Specialty::create(['name' => 'Kulit dan Kelamin']);
        $clinic = Clinic::create([
            'name' => 'Poliklinik Kulit dan Kelamin', 'code' => 'kk', 'type' => 'poliklinik',
            'short_description' => 'Layanan kulit dan kelamin.', 'location' => 'Gedung B Lt. 2',
        ]);
        $doctor = Doctor::create(['clinic_id' => $clinic->id, 'name' => 'Citra Anindya Putri', 'gender' => 'P']);

        $this->assertSame('kulit-dan-kelamin', $specialty->slug);
        $this->assertSame('poliklinik-kulit-dan-kelamin', $clinic->slug);
        $this->assertSame('citra-anindya-putri', $doctor->slug);
    }

    public function test_slug_columns_are_limited_to_the_column_length_with_numbering_inside_the_limit(): void
    {
        foreach ([[Specialty::class, 120], [Clinic::class, 140], [Doctor::class, 170]] as [$class, $max]) {
            $slug = $class::uniqueSlug(str_repeat('a', 300));

            $this->assertSame($max, strlen($slug), "{$class}: dipotong tepat pada panjang kolom.");
        }

        // 100 huruf "Æ" (batas kolom nama) menjadi 200 karakter "ae" setelah transliterasi.
        $name = str_repeat('Æ', 100);

        $first = Specialty::factory()->create(['name' => $name]);
        $second = Specialty::factory()->create(['name' => $name]);
        $third = Specialty::factory()->create(['name' => $name]);

        $this->assertSame(str_repeat('ae', 60), $first->slug);
        $this->assertSame(str_repeat('ae', 59).'-2', $second->slug);
        $this->assertSame(str_repeat('ae', 59).'-3', $third->slug);
        $this->assertSame(120, strlen($second->slug));
    }

    public function test_truncation_never_leaves_a_dangling_hyphen_before_the_suffix(): void
    {
        // Pemotongan pada 120 karakter jatuh tepat pada tanda hubung ("a" x 119, "-", "bb").
        $source = str_repeat('a', 119).' bb';

        $first = Specialty::uniqueSlug($source);
        Specialty::factory()->create(['name' => 'Lain', 'slug' => $first]);
        $second = Specialty::uniqueSlug($source);

        $this->assertSame(str_repeat('a', 119), $first);
        $this->assertSame(str_repeat('a', 118).'-2', $second);
    }

    public function test_unique_slug_can_ignore_the_row_itself(): void
    {
        $specialty = Specialty::factory()->create(['name' => 'Anak', 'slug' => null]);

        $this->assertSame('anak-2', Specialty::uniqueSlug('Anak'));
        $this->assertSame('anak', Specialty::uniqueSlug('Anak', $specialty->id));
    }

    public function test_slugs_are_unique_per_table_not_across_models(): void
    {
        $specialty = Specialty::factory()->create(['name' => 'Umum', 'slug' => null]);
        $clinic = Clinic::factory()->create(['name' => 'Umum', 'slug' => null]);

        $this->assertSame('umum', $specialty->slug);
        $this->assertSame('umum', $clinic->slug);
    }

    public function test_the_unique_index_is_still_the_last_line_of_defence(): void
    {
        Specialty::factory()->create(['name' => 'Anak', 'slug' => 'anak']);

        $this->expectException(QueryException::class);

        Specialty::factory()->create(['name' => 'Lain', 'slug' => 'anak']);
    }

    public function test_factories_no_longer_define_a_slug_so_duplicate_names_are_numbered(): void
    {
        $specialties = Specialty::factory()->count(3)->sequence(['name' => 'Saraf'])->create();

        $this->assertSame(['saraf', 'saraf-2', 'saraf-3'], $specialties->pluck('slug')->all());
    }

    public function test_slug_max_length_is_defined_per_model_from_the_data_dictionary(): void
    {
        $lengths = [];

        foreach ([Specialty::class, Clinic::class, Doctor::class] as $class) {
            $method = new ReflectionMethod($class, 'slugMaxLength');
            $lengths[$class] = $method->invoke(new $class);
        }

        $this->assertSame([Specialty::class => 120, Clinic::class => 140, Doctor::class => 170], $lengths);
    }
}
```

- [ ] **Step 3: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=HasUniqueSlugTest
```
Expected: FAIL — sebagian besar tes gagal, mis. `SQLSTATE[23000]: Integrity constraint violation: 1048 Column 'slug' cannot be null` (`slug` => null belum dihasilkan trait) dan `Method App\Models\Specialty::slugMaxLength does not exist` (`ReflectionException`).

- [ ] **Step 4: Tulis trait (tool Write) `app/Models/Concerns/HasUniqueSlug.php`**

```php
<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Slug otomatis (F2-R5): `Str::slug` dari nama, sufiks `-2`, `-3`, ... bila bentrok, dipotong sesuai
 * panjang kolom, tidak pernah kosong, dan hanya dibuat saat model dibuat (tidak berubah saat nama diedit).
 * Indeks UNIQUE pada kolom `slug` tetap menjadi pengaman terakhir untuk dua simpan yang bersamaan.
 *
 * @mixin Model
 */
trait HasUniqueSlug
{
    /** Panjang maksimum kolom `slug` model ini (09 §3.1-3.3). */
    abstract protected function slugMaxLength(): int;

    public static function bootHasUniqueSlug(): void
    {
        static::creating(function (Model $model): void {
            $slug = $model->getAttributes()['slug'] ?? null;

            if ($slug === null || trim((string) $slug) === '') {
                $model->setAttribute('slug', static::uniqueSlug($model->slugSource()));
            }
        });
    }

    /** Teks sumber slug; secara baku kolom `name`. */
    protected function slugSource(): string
    {
        return (string) $this->getAttribute('name');
    }

    /**
     * Slug unik dari $source. $ignoreId mengecualikan baris itu dari pemeriksaan bentrok.
     */
    public static function uniqueSlug(string $source, ?int $ignoreId = null): string
    {
        $max = (new static)->slugMaxLength();
        $base = self::trimSlug(Str::slug($source), $max);

        if ($base === '') {
            $base = self::trimSlug(Str::slug(class_basename(static::class)).'-'.Str::lower(Str::random(6)), $max);
        }

        $candidate = $base;

        for ($number = 2; static::slugTaken($candidate, $ignoreId); $number++) {
            $suffix = '-'.$number;
            $candidate = self::trimSlug($base, $max - strlen($suffix)).$suffix;
        }

        return $candidate;
    }

    private static function slugTaken(string $slug, ?int $ignoreId): bool
    {
        return static::query()
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }

    /** Potong ke $max karakter tanpa tanda hubung di ujung (slug dari Str::slug hanya ASCII). */
    private static function trimSlug(string $slug, int $max): string
    {
        return rtrim(substr($slug, 0, max($max, 0)), '-');
    }
}
```

- [ ] **Step 5: Pasang trait di tiga model dan hapus slug sementara di factory (tool Edit)**

Repo memakai LF (`.gitattributes` `eol=lf`, K5); tiap `old` harus cocok tepat satu kali. Bila lewat PowerShell, `Edit-Text` (rencana fase 1, Task 18 Step 1) boleh dipakai tetapi tidak wajib.

`app/Models/Specialty.php`:

old:
```text
use Database\Factories\SpecialtyFactory;
```
new:
```text
use App\Models\Concerns\HasUniqueSlug;
use Database\Factories\SpecialtyFactory;
```
old:
```text
    use HasFactory;
```
new:
```text
    use HasFactory, HasUniqueSlug;
```
old:
```text
    public function scopeActive(Builder $query): Builder
```
new:
```text
    /** Panjang kolom `specialties.slug` (09 §3.1). */
    protected function slugMaxLength(): int
    {
        return 120;
    }

    public function scopeActive(Builder $query): Builder
```

`app/Models/Clinic.php`:

old:
```text
use Database\Factories\ClinicFactory;
```
new:
```text
use App\Models\Concerns\HasUniqueSlug;
use Database\Factories\ClinicFactory;
```
old:
```text
    use HasFactory;
```
new:
```text
    use HasFactory, HasUniqueSlug;
```
old:
```text
    public function specialty(): BelongsTo
```
new:
```text
    /** Panjang kolom `clinics.slug` (09 §3.2). */
    protected function slugMaxLength(): int
    {
        return 140;
    }

    public function specialty(): BelongsTo
```

`app/Models/Doctor.php`:

old:
```text
use App\Enums\Gender;
```
new:
```text
use App\Enums\Gender;
use App\Models\Concerns\HasUniqueSlug;
```
old:
```text
    use HasFactory;
```
new:
```text
    use HasFactory, HasUniqueSlug;
```
old:
```text
    public function clinic(): BelongsTo
```
new:
```text
    /** Panjang kolom `doctors.slug` (09 §3.3). */
    protected function slugMaxLength(): int
    {
        return 170;
    }

    public function clinic(): BelongsTo
```

Di ketiga `database/factories/{Specialty,Clinic,Doctor}Factory.php` hapus **dua baris** (komentar dan `slug`):

```text
            // Slug sementara dari nama; dihapus di Task 2 saat trait HasUniqueSlug mengambil alih.
            'slug' => fn (array $attributes): string => Str::slug((string) $attributes['name']),
```
Di `DoctorFactory.php` hapus juga `use Illuminate\Support\Str;` (tidak dipakai lagi; Pint akan membuangnya bila terlewat). `SpecialtyFactory` dan `ClinicFactory` masih memakai `Str` untuk nama/kode.

- [ ] **Step 6: Jalankan tes — harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="HasUniqueSlugTest|SpecialtyModelTest|ClinicModelTest|DoctorModelTest"
```
Expected: PASS — termasuk `HasUniqueSlugTest` 25 tes; tes Task 1 tetap hijau.

- [ ] **Step 7: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint
& $PHP vendor\bin\pint --test
git add app/Models/Concerns/HasUniqueSlug.php app/Models/Specialty.php app/Models/Clinic.php app/Models/Doctor.php database/factories/SpecialtyFactory.php database/factories/ClinicFactory.php database/factories/DoctorFactory.php tests/Feature/Models/HasUniqueSlugTest.php
git commit -m @'
feat(fase-2): trait HasUniqueSlug (slug otomatis, unik, tidak berubah saat nama diedit)

Str::slug dengan sufiks -2/-3 saat bentrok, dipotong sesuai panjang kolom tanpa tanda hubung yatim,
dan cadangan acak untuk nama tanpa karakter yang dapat disluggify (Review Focus 1, F2-R5).

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
'@
```

**Siklus B — `SanitizesRichText`**

- [ ] **Step 8: Tulis tes yang gagal (tool Write) `tests/Feature/Models/SanitizesRichTextTest.php`**

```php
<?php

namespace Tests\Feature\Models;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Specialty;
use App\Services\Content\HtmlSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Review Focus 3: rich text berbahaya atau masuk lewat jalur selain form (F2-R14).
 */
class SanitizesRichTextTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{0: string, 1: list<string>, 2: string}> payload, potongan yang tidak boleh ada, potongan yang harus ada */
    public static function dangerousPayloads(): array
    {
        return [
            'script dan atribut event' => ['<script>alert(1)</script><p onclick="x()">Halo</p>', ['<script', 'alert(1)', 'onclick'], '<p>Halo</p>'],
            'onerror pada gambar' => ['<p>Halo <img src="x" onerror="alert(1)"></p>', ['onerror', 'alert(1)', '<img'], '<p>Halo'],
            'gambar data URI' => ['<img src="data:image/png;base64,AAAA" alt="a"><p>t</p>', ['data:', '<img', 'base64'], '<p>t</p>'],
            'gambar dari host luar' => ['<img src="https://evil.test/x.png" alt="a"><p>t</p>', ['evil.test', '<img'], '<p>t</p>'],
            'tautan javascript' => ['<a href="javascript:alert(1)">klik</a>', ['javascript:', 'href'], 'klik'],
            'iframe' => ['<iframe src="https://evil.test"></iframe><p>x</p>', ['<iframe', 'evil.test'], '<p>x</p>'],
            'style dan atribut style' => ['<style>p{}</style><p style="color:red">x</p>', ['<style', 'style=', 'color:red'], '<p>x</p>'],
        ];
    }

    /**
     * @param  list<string>  $forbidden
     */
    #[DataProvider('dangerousPayloads')]
    public function test_create_stores_the_sanitizer_output(string $payload, array $forbidden, string $expected): void
    {
        $clinic = Clinic::factory()->create(['description' => $payload]);

        $stored = DB::table('clinics')->where('id', $clinic->id)->value('description');

        $this->assertSame(app(HtmlSanitizer::class)->clean($payload), $stored);
        $this->assertSame($stored, $clinic->description, 'Atribut model sama dengan yang tersimpan.');
        $this->assertStringContainsString($expected, $stored);

        foreach ($forbidden as $fragment) {
            $this->assertStringNotContainsString($fragment, $stored);
        }
    }

    public function test_internal_storage_images_and_external_links_are_kept_safely(): void
    {
        $clinic = Clinic::factory()->create([
            'description' => '<p>Isi</p><img src="/storage/editor/2026/10/a.jpg" alt="Foto"><a href="https://example.org/x">luar</a>',
        ]);

        $stored = $clinic->fresh()->description;

        $this->assertStringContainsString('<img src="/storage/editor/2026/10/a.jpg" alt="Foto">', $stored);
        $this->assertStringContainsString('target="_blank" rel="noopener noreferrer"', $stored);
    }

    public function test_update_sanitizes_the_new_value(): void
    {
        $clinic = Clinic::factory()->create(['description' => '<p>Awal</p>']);

        $clinic->update(['description' => '<p>Baru</p><script>alert(2)</script>']);

        $this->assertSame('<p>Baru</p>', DB::table('clinics')->where('id', $clinic->id)->value('description'));
    }

    public function test_every_model_save_path_goes_through_the_sanitizer(): void
    {
        $payload = '<p>Aman</p><script>alert(3)</script>';

        $forced = Clinic::factory()->create();
        $forced->forceFill(['description' => $payload])->save();

        $assigned = Clinic::factory()->create();
        $assigned->description = $payload;
        $assigned->save();

        $viaUpsert = Clinic::query()->updateOrCreate(
            ['code' => 'UPS'],
            [
                'name' => 'Poliklinik Upsert', 'type' => 'poliklinik', 'short_description' => 'Ringkasan.',
                'location' => 'Gedung A Lt. 1', 'description' => $payload,
            ],
        );

        $viaFirstOrCreate = Clinic::query()->firstOrCreate(
            ['code' => 'FOC'],
            [
                'name' => 'Poliklinik Cari', 'type' => 'poliklinik', 'short_description' => 'Ringkasan.',
                'location' => 'Gedung A Lt. 1', 'description' => $payload,
            ],
        );

        $replica = Clinic::factory()->create()->replicate(['slug', 'code']);
        $replica->forceFill(['slug' => 'salinan', 'code' => 'SLN', 'name' => 'Salinan', 'description' => $payload])->save();

        foreach ([$forced, $assigned, $viaUpsert, $viaFirstOrCreate, $replica] as $clinic) {
            $this->assertSame('<p>Aman</p>', DB::table('clinics')->where('id', $clinic->id)->value('description'));
        }
    }

    public function test_empty_results_are_stored_as_null(): void
    {
        foreach (['', '   ', '<p></p>', "\n", '<script>alert(1)</script>'] as $empty) {
            $clinic = Clinic::factory()->create(['description' => $empty]);

            $this->assertNull(DB::table('clinics')->where('id', $clinic->id)->value('description'), json_encode($empty));
            $this->assertNull($clinic->description);
        }

        $clinic = Clinic::factory()->create(['description' => '<p>Isi</p>']);
        $clinic->update(['description' => '']);

        $this->assertNull(DB::table('clinics')->where('id', $clinic->id)->value('description'));
        $this->assertNull(Clinic::factory()->create(['description' => null])->description);
    }

    public function test_sanitizing_is_idempotent_and_ignores_unrelated_updates(): void
    {
        $clinic = Clinic::factory()->create(['description' => '<p>Isi <strong>tebal</strong></p>']);
        $first = $clinic->fresh()->description;

        $clinic->fresh()->update(['description' => $first]);
        $clinic->fresh()->update(['location' => 'Gedung B Lt. 2']);

        $this->assertSame($first, $clinic->fresh()->description);
        $this->assertSame('<p>Isi <strong>tebal</strong></p>', $first);
    }

    public function test_only_the_declared_rich_text_attribute_is_sanitized(): void
    {
        $clinic = Clinic::factory()->create([
            'short_description' => 'Ringkasan <b>tebal</b>',
            'meta_description' => 'Meta <i>miring</i>',
            'description' => '<p>Isi</p>',
        ]);

        $fresh = $clinic->fresh();

        $this->assertSame('Ringkasan <b>tebal</b>', $fresh->short_description);
        $this->assertSame('Meta <i>miring</i>', $fresh->meta_description);
    }

    public function test_plain_text_columns_of_other_models_are_not_touched(): void
    {
        $doctor = Doctor::factory()->create(['bio' => 'tekanan <90 dan >100', 'short_bio' => 'Singkat <b>x</b>']);
        $specialty = Specialty::factory()->create(['description' => 'Layanan <anak>']);

        $this->assertSame('tekanan <90 dan >100', $doctor->fresh()->bio);
        $this->assertSame('Singkat <b>x</b>', $doctor->fresh()->short_bio);
        $this->assertSame('Layanan <anak>', $specialty->fresh()->description);
    }
}
```

- [ ] **Step 9: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=SanitizesRichTextTest
```
Expected: FAIL — `Tests: 11 failed, 3 passed`; mis. `Failed asserting that two strings are identical.` (nilai berbahaya tersimpan mentah) dan `Failed asserting that '' is null.`

- [ ] **Step 10: Tulis trait (tool Write) `app/Models/Concerns/SanitizesRichText.php`**

```php
<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Services\Content\HtmlSanitizer;
use Illuminate\Database\Eloquent\Model;

/**
 * Sanitasi kolom rich text pada event `saving` (F2-R14): setiap jalur simpan model (form, seeder,
 * `update()`, `forceFill()->save()`, `updateOrCreate()`) melewatinya, sehingga yang tersimpan di DB selalu
 * hasil HtmlSanitizer::clean(). Hasil kosong disimpan sebagai NULL. Operasi massal di tingkat query
 * (`Model::query()->update()`, `upsert()`) tidak memicu event model dan dilarang untuk kolom ini.
 *
 * @mixin Model
 */
trait SanitizesRichText
{
    /**
     * Nama atribut rich text milik model, mis. `['description']`.
     *
     * @return list<string>
     */
    abstract protected function richTextAttributes(): array;

    public static function bootSanitizesRichText(): void
    {
        static::saving(function (Model $model): void {
            foreach ($model->richTextAttributes() as $attribute) {
                if (! $model->isDirty($attribute)) {
                    continue;
                }

                $value = $model->getAttributes()[$attribute] ?? null;
                $clean = app(HtmlSanitizer::class)->clean(is_string($value) ? $value : null);

                $model->setAttribute($attribute, $clean === '' ? null : $clean);
            }
        });
    }
}
```

- [ ] **Step 11: Pasang di `Clinic` (tool Edit `app/Models/Clinic.php`)**

old:
```text
use App\Models\Concerns\HasUniqueSlug;
```
new:
```text
use App\Models\Concerns\HasUniqueSlug;
use App\Models\Concerns\SanitizesRichText;
```
old:
```text
    use HasFactory, HasUniqueSlug;
```
new:
```text
    use HasFactory, HasUniqueSlug, SanitizesRichText;
```
old:
```text
    public function specialty(): BelongsTo
```
new:
```text
    /**
     * Kolom rich text yang disanitasi saat disimpan (F2-R14).
     *
     * @return list<string>
     */
    protected function richTextAttributes(): array
    {
        return ['description'];
    }

    public function specialty(): BelongsTo
```

- [ ] **Step 12: Jalankan tes — harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=SanitizesRichTextTest
```
Expected: PASS — `Tests: 14 passed`.

- [ ] **Step 13: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint
& $PHP vendor\bin\pint --test
git add app/Models/Concerns/SanitizesRichText.php app/Models/Clinic.php tests/Feature/Models/SanitizesRichTextTest.php
git commit -m @'
feat(fase-2): trait SanitizesRichText untuk clinics.description

Sanitasi HtmlSanitizer pada event saving sehingga setiap jalur simpan model (form, seeder,
update, forceFill, updateOrCreate) menyimpan hanya hasil clean(); hasil kosong menjadi NULL (F2-R14).

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
'@
```

**Siklus C — `HasMedia`**

- [ ] **Step 14: Tulis tes yang gagal (tool Write) `tests/Feature/Models/HasMediaTest.php`**

```php
<?php

namespace Tests\Feature\Models;

use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Media;
use App\Services\Media\MediaService;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\Support\ImageFixtures;
use Tests\Support\InteractsWithMasterData;
use Tests\TestCase;

class HasMediaTest extends TestCase
{
    use InteractsWithMasterData;
    use RefreshDatabase;

    private MediaService $media;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->media = app(MediaService::class);
    }

    private function photo(Doctor $doctor, string $alt = 'Foto dokter'): Media
    {
        return $this->media->store(ImageFixtures::jpeg(800, 800), 'photo', $doctor, ['alt_text' => $alt]);
    }

    private function gallery(Clinic $clinic, string $alt): Media
    {
        return $this->media->store(ImageFixtures::jpeg(900, 600), 'gallery', $clinic, ['alt_text' => $alt]);
    }

    private function cover(Clinic $clinic): Media
    {
        return $this->media->store(ImageFixtures::jpeg(1200, 800), 'cover', $clinic, ['alt_text' => 'Cover']);
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

    public function test_media_relation_is_a_morph_many_ordered_by_sort_order_then_id(): void
    {
        $clinic = $this->createClinic();
        $first = $this->gallery($clinic, 'Satu');
        $second = $this->gallery($clinic, 'Dua');
        $third = $this->gallery($clinic, 'Tiga');
        $this->media->reorder($clinic, 'gallery', [$third->id, $first->id, $second->id]);

        $this->assertInstanceOf(MorphMany::class, $clinic->media());
        $this->assertSame([$third->id, $first->id, $second->id], $clinic->media()->pluck('id')->all());

        $this->assertSame([$third->id, $first->id, $second->id], Clinic::query()->with('media')->find($clinic->id)->media->pluck('id')->all());
    }

    public function test_relation_uses_the_class_name_as_model_type_and_does_not_leak_across_owners(): void
    {
        $clinic = $this->createClinic();
        $doctor = $this->createDoctor();
        $clinicCover = $this->cover($clinic);
        $doctorPhoto = $this->photo($doctor);

        $this->assertSame(Clinic::class, $clinicCover->model_type);
        $this->assertSame(Doctor::class, $doctorPhoto->model_type);

        // Id yang sama pada tipe berbeda tidak boleh tertukar: baris media dokter berpura-pura milik id klinik.
        DB::table('media')->where('id', $doctorPhoto->id)->update(['model_id' => $clinic->id]);

        $this->assertSame([$clinicCover->id], $clinic->media()->pluck('id')->all());
        $this->assertSame([$doctorPhoto->id], DB::table('media')->where('model_type', Doctor::class)->pluck('id')->all());
    }

    public function test_first_media_and_media_in_use_the_loaded_relation_without_queries(): void
    {
        $clinic = $this->createClinic();
        $cover = $this->cover($clinic);
        $galleryOne = $this->gallery($clinic, 'Satu');
        $galleryTwo = $this->gallery($clinic, 'Dua');

        $loaded = Clinic::query()->with('media')->findOrFail($clinic->id);

        DB::enableQueryLog();

        $this->assertTrue($loaded->firstMedia('cover')->is($cover));
        $this->assertSame([$galleryOne->id, $galleryTwo->id], $loaded->mediaIn('gallery')->pluck('id')->all());
        $this->assertNull($loaded->firstMedia('photo'));
        $this->assertCount(0, $loaded->mediaIn('photo'));
        $this->assertSame([], DB::getQueryLog(), 'Relasi media sudah dimuat: tidak boleh ada query tambahan.');

        DB::disableQueryLog();
    }

    public function test_first_media_and_media_in_query_directly_when_the_relation_is_not_loaded(): void
    {
        $clinic = $this->createClinic();
        $other = $this->createClinic();
        $this->cover($clinic);
        $this->gallery($clinic, 'Satu');
        $this->gallery($other, 'Milik lain');

        $fresh = Clinic::query()->findOrFail($clinic->id);

        $this->assertFalse($fresh->relationLoaded('media'));
        $this->assertSame('Cover', $fresh->firstMedia('cover')->alt_text);
        $this->assertSame(['Satu'], $fresh->mediaIn('gallery')->pluck('alt_text')->all());
        $this->assertFalse($fresh->relationLoaded('media'), 'Pembacaan langsung tidak memuat relasi.');
    }

    public function test_listing_several_owners_with_eager_loaded_media_is_safe_in_strict_mode(): void
    {
        $withPhoto = $this->createDoctor(['name' => 'Berfoto']);
        $this->photo($withPhoto);
        $this->createDoctor(['name' => 'Tanpa Foto']);
        $this->createDoctor(['name' => 'Tanpa Foto Juga']);

        $doctors = Doctor::query()->with('media')->orderBy('id')->get();

        DB::enableQueryLog();
        $urls = $doctors->map(fn (Doctor $doctor): string => $doctor->photo_url)->all();
        $this->assertSame([], DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($withPhoto->photo_url, $urls[0]);
        $this->assertSame(asset('images/placeholders/doctor.svg'), $urls[1]);
        $this->assertSame(asset('images/placeholders/doctor.svg'), $urls[2]);
    }

    public function test_listing_without_eager_loading_does_not_trigger_lazy_loading_violations_but_queries_per_row(): void
    {
        $this->createDoctor();
        $this->createDoctor();
        $doctors = Doctor::query()->get();

        DB::enableQueryLog();
        $doctors->each(fn (Doctor $doctor): string => $doctor->photo_url);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(2, $queries, 'Tanpa with(media), setiap baris melakukan satu query (bukan lazy loading).');
    }

    public function test_media_url_returns_full_and_thumb_urls_of_the_first_media(): void
    {
        $doctor = $this->createDoctor();
        $photo = $this->photo($doctor);
        $doctor = Doctor::query()->with('media')->findOrFail($doctor->id);

        $this->assertSame(Storage::disk('public')->url($photo->directory.'/'.$photo->file_name), $doctor->mediaUrl('photo'));
        $this->assertSame(Storage::disk('public')->url($photo->thumb_path), $doctor->mediaUrl('photo', 'thumb'));
        $this->assertSame($doctor->mediaUrl('photo'), $doctor->photo_url);
        $this->assertSame($doctor->mediaUrl('photo', 'thumb'), $doctor->photo_thumb_url);
        $this->assertNotSame($doctor->photo_url, $doctor->photo_thumb_url);
    }

    public function test_media_url_falls_back_to_the_given_or_generic_placeholder(): void
    {
        $doctor = Doctor::query()->with('media')->findOrFail($this->createDoctor()->id);
        $clinic = Clinic::query()->with('media')->findOrFail($this->createClinic()->id);

        $this->assertSame(asset('images/placeholders/doctor.svg'), $doctor->photo_url);
        $this->assertSame(asset('images/placeholders/doctor.svg'), $doctor->photo_thumb_url);
        $this->assertSame(asset('images/placeholders/clinic.svg'), $clinic->cover_url);
        $this->assertSame(asset('images/placeholders/facility.svg'), $clinic->mediaUrl('gallery', 'full', 'images/placeholders/facility.svg'));
        $this->assertSame(asset('images/placeholders/gallery.svg'), $clinic->mediaUrl('gallery'));
    }

    public function test_media_url_rejects_an_unknown_variant(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->createClinic()->mediaUrl('cover', 'besar');
    }

    public function test_cover_url_uses_the_clinic_cover(): void
    {
        $clinic = $this->createClinic();
        $cover = $this->cover($clinic);
        $this->gallery($clinic, 'Galeri');

        $clinic = Clinic::query()->with('media')->findOrFail($clinic->id);

        $this->assertSame(Storage::disk('public')->url($cover->directory.'/'.$cover->file_name), $clinic->cover_url);
    }

    public function test_deleting_the_owner_removes_media_rows_and_files(): void
    {
        $clinic = $this->createClinic();
        $other = $this->createClinic();
        $cover = $this->cover($clinic);
        $gallery = $this->gallery($clinic, 'Galeri');
        $othersGallery = $this->gallery($other, 'Milik lain');

        $clinic->delete();

        $this->assertDatabaseMissing('clinics', ['id' => $clinic->id]);
        foreach ([$cover, $gallery] as $media) {
            $this->assertNull(Media::query()->find($media->id));
            $this->assertFilesMissing($media);
        }

        $this->assertNotNull(Media::query()->find($othersGallery->id));
        $this->assertFilesExist($othersGallery);
        $this->assertSame(2, AuditLog::query()->where('action', 'media.deleted')->count());
    }

    public function test_a_failed_owner_delete_keeps_rows_files_and_audit(): void
    {
        $clinic = $this->createClinic();
        $cover = $this->cover($clinic);
        $this->createDoctor([], $clinic);

        try {
            $clinic->delete();
            $this->fail('FK doctors.clinic_id RESTRICT seharusnya menolak penghapusan.');
        } catch (QueryException) {
            // diharapkan
        }

        $this->assertDatabaseHas('clinics', ['id' => $clinic->id]);
        $this->assertNotNull(Media::query()->find($cover->id), 'Baris media ikut di-rollback.');
        $this->assertFilesExist($cover);
        $this->assertSame(0, AuditLog::query()->where('action', 'media.deleted')->count());
    }

    public function test_has_media_on_doctor_is_available_through_relation_loading_errors_in_strict_mode(): void
    {
        $this->createDoctor();
        $this->createDoctor();
        $doctors = Doctor::query()->get();

        $this->expectException(LazyLoadingViolationException::class);

        $doctors->each(fn (Doctor $doctor) => $doctor->media);
    }
}
```

- [ ] **Step 15: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=HasMediaTest
```
Expected: FAIL — `Call to undefined relationship [media] on model [App\Models\Clinic]` / `BadMethodCallException` pada hampir semua tes.

- [ ] **Step 16: Tulis trait (tool Write) `app/Models/Concerns/HasMedia.php`**

```php
<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Media;
use App\Services\Media\MediaService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Relasi media polimorfik (`media.model_type` + `media.model_id`, tanpa FK; 08 §polimorfik).
 * Saat pemilik dihapus, baris dan berkas media-nya ikut dihapus lewat MediaService::deleteFor().
 *
 * @mixin Model
 */
trait HasMedia
{
    public static function bootHasMedia(): void
    {
        static::deleting(function (Model $model): void {
            app(MediaService::class)->deleteFor($model);
        });
    }

    /**
     * Seluruh media pemilik, urut `sort_order` lalu `id`. Muat dengan `with('media')` pada daftar.
     */
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'model')->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Media pertama koleksi, atau null. Memakai relasi `media` bila sudah dimuat (tanpa query),
     * kalau tidak satu query langsung (bukan lazy loading).
     */
    public function firstMedia(string $collection): ?Media
    {
        return $this->mediaIn($collection)->first();
    }

    /**
     * Semua media satu koleksi dalam urutan relasi.
     *
     * @return Collection<int, Media>
     */
    public function mediaIn(string $collection): Collection
    {
        if ($this->relationLoaded('media')) {
            return $this->getRelation('media')->where('collection', $collection)->values();
        }

        return $this->media()->where('collection', $collection)->get();
    }

    /**
     * URL media pertama koleksi (`full` atau `thumb`), atau URL placeholder lokal bila tidak ada media
     * atau berkasnya tidak berada di disk publik. $placeholder adalah path di bawah `public/`.
     */
    public function mediaUrl(string $collection, string $variant = 'full', ?string $placeholder = null): string
    {
        if (! in_array($variant, ['full', 'thumb'], true)) {
            throw new InvalidArgumentException("Varian gambar tidak dikenal: {$variant}.");
        }

        $media = $this->firstMedia($collection);
        $url = $media === null ? null : ($variant === 'thumb' ? $media->thumb_url : $media->url);

        return $url !== null && $url !== '' ? $url : asset($placeholder ?? 'images/placeholders/gallery.svg');
    }

    /**
     * Penghapusan pemilik bersifat atomik: bila baris pemilik gagal dihapus (mis. FK RESTRICT),
     * penghapusan baris media di event `deleting` ikut di-rollback dan berkasnya tidak disentuh
     * (MediaService::delete menghapus berkas setelah commit).
     */
    public function delete()
    {
        return DB::transaction(fn () => parent::delete());
    }
}
```

- [ ] **Step 17: Pasang di `Clinic` dan `Doctor` (tool Edit)**

`app/Models/Clinic.php`:

old:
```text
use App\Models\Concerns\HasUniqueSlug;
```
new:
```text
use App\Models\Concerns\HasMedia;
use App\Models\Concerns\HasUniqueSlug;
```
old:
```text
    use HasFactory, HasUniqueSlug, SanitizesRichText;
```
new:
```text
    use HasFactory, HasMedia, HasUniqueSlug, SanitizesRichText;
```
old:
```text
    /** Panjang kolom `clinics.slug` (09 §3.2). */
```
new:
```text
    /**
     * URL cover (koleksi `cover`), atau placeholder `clinic.svg`. Muat `media` pada daftar.
     *
     * @return Attribute<string, never>
     */
    protected function coverUrl(): Attribute
    {
        return Attribute::get(fn (): string => $this->mediaUrl('cover', 'full', 'images/placeholders/clinic.svg'));
    }

    /** Panjang kolom `clinics.slug` (09 §3.2). */
```

`app/Models/Doctor.php`:

old:
```text
use App\Models\Concerns\HasUniqueSlug;
```
new:
```text
use App\Models\Concerns\HasMedia;
use App\Models\Concerns\HasUniqueSlug;
```
old:
```text
    use HasFactory, HasUniqueSlug;
```
new:
```text
    use HasFactory, HasMedia, HasUniqueSlug;
```
old:
```text
    /** Panjang kolom `doctors.slug` (09 §3.3). */
```
new:
```text
    /**
     * URL foto (koleksi `photo`), atau placeholder `doctor.svg`. Muat `media` pada daftar.
     *
     * @return Attribute<string, never>
     */
    protected function photoUrl(): Attribute
    {
        return Attribute::get(fn (): string => $this->mediaUrl('photo', 'full', 'images/placeholders/doctor.svg'));
    }

    /**
     * URL thumbnail foto 400 px, atau placeholder `doctor.svg`.
     *
     * @return Attribute<string, never>
     */
    protected function photoThumbUrl(): Attribute
    {
        return Attribute::get(fn (): string => $this->mediaUrl('photo', 'thumb', 'images/placeholders/doctor.svg'));
    }

    /** Panjang kolom `doctors.slug` (09 §3.3). */
```

- [ ] **Step 18: Jalankan tes — harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="HasMediaTest|SanitizesRichTextTest|HasUniqueSlugTest|DoctorModelTest|ClinicModelTest"
```
Expected: PASS — `HasMediaTest` 13 tes; sisanya tetap hijau.

- [ ] **Step 19: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint
& $PHP vendor\bin\pint --test
git add app/Models/Concerns/HasMedia.php app/Models/Clinic.php app/Models/Doctor.php tests/Feature/Models/HasMediaTest.php
git commit -m @'
feat(fase-2): trait HasMedia untuk Doctor dan Clinic

Relasi media polimorfik, firstMedia/mediaIn/mediaUrl tanpa query bila relasi dimuat, accessor
photo_url/photo_thumb_url/cover_url dengan placeholder, dan penghapusan pemilik atomik yang
membersihkan media setelah commit.

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
'@
```

**Siklus D — `media:prune` butir (c) dan `deleteFor()` transaksional**

- [ ] **Step 20: Tulis tes yang gagal (tool Write) `tests/Feature/Media/PruneOwnerlessMediaTest.php`**

```php
<?php

namespace Tests\Feature\Media;

use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\Media;
use App\Models\User;
use App\Services\Media\MediaService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ImageFixtures;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithMedia;
use Tests\TestCase;

/**
 * Butir (c) media:prune (F1-R12): baris media yang pemiliknya sudah tidak ada.
 */
class PruneOwnerlessMediaTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithMedia;
    use RefreshDatabase;

    private MediaService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed(SettingsSeeder::class);
        $this->service = app(MediaService::class);
    }

    private function cover(Clinic $clinic): Media
    {
        return $this->service->store(ImageFixtures::jpeg(1200, 800), 'cover', $clinic, ['alt_text' => 'Cover']);
    }

    private function filesExist(Media $media): bool
    {
        return Storage::disk('public')->exists($media->directory.'/'.$media->file_name)
            && Storage::disk('public')->exists($media->thumb_path);
    }

    public function test_media_whose_owner_row_was_deleted_behind_the_models_back_is_pruned(): void
    {
        $gone = $this->createClinic();
        $kept = $this->createClinic();
        $goneCover = $this->cover($gone);
        $keptCover = $this->cover($kept);
        $doctor = $this->createDoctor();
        $photo = $this->service->store(ImageFixtures::jpeg(800, 800), 'photo', $doctor, ['alt_text' => 'Foto']);

        // Penghapusan tingkat query tidak memicu event `deleting` HasMedia.
        DB::table('clinics')->where('id', $gone->id)->delete();

        $dry = $this->service->prune(true);

        $this->assertSame(1, $dry->orphanRows);
        $this->assertCount(1, $dry->items);
        $this->assertStringContainsString('#'.$goneCover->id, $dry->items[0]);
        $this->assertStringContainsString('pemilik Clinic #'.$gone->id.' sudah tidak ada', $dry->items[0]);
        $this->assertSame($goneCover->size_bytes + Storage::disk('public')->size($goneCover->thumb_path), $dry->bytes);
        $this->assertNotNull(Media::query()->find($goneCover->id), 'Dry-run tidak menghapus.');

        $report = $this->service->prune();

        $this->assertSame(1, $report->orphanRows);
        $this->assertNull(Media::query()->find($goneCover->id));
        $this->assertFalse($this->filesExist($goneCover));
        $this->assertNotNull(Media::query()->find($keptCover->id));
        $this->assertTrue($this->filesExist($keptCover));
        $this->assertNotNull(Media::query()->find($photo->id));
        $this->assertTrue($this->filesExist($photo));
        $this->assertSame(1, AuditLog::query()->where('action', 'media.pruned')->count());

        $second = $this->service->prune(true);
        $this->assertSame([0, 0, 0, []], [$second->orphanRows, $second->orphanFiles, $second->bytes, $second->items]);
    }

    public function test_rows_are_pruned_regardless_of_their_age(): void
    {
        $gone = $this->createClinic();
        $cover = $this->cover($gone);
        DB::table('clinics')->where('id', $gone->id)->delete();

        $this->assertTrue($cover->fresh()->created_at->greaterThan(now()->subMinute()));
        $this->assertSame(1, $this->service->prune(true)->orphanRows);
    }

    public function test_unknown_owner_classes_and_existing_owners_of_other_types_are_left_alone(): void
    {
        $user = User::factory()->staff()->create();
        $userMedia = $this->service->store(ImageFixtures::jpeg(800, 800), 'photo', $user, ['alt_text' => 'Foto']);
        $unknown = $this->createMediaRow(['model_type' => 'App\\Models\\TidakAda', 'model_id' => 999, 'collection' => 'photo']);
        $notModel = $this->createMediaRow(['model_type' => 'stdClass', 'model_id' => 5, 'collection' => 'photo']);

        $report = $this->service->prune();

        $this->assertSame(0, $report->orphanRows);
        $this->assertNotNull(Media::query()->find($userMedia->id));
        $this->assertNotNull(Media::query()->find($unknown->id));
        $this->assertNotNull(Media::query()->find($notModel->id));
    }

    public function test_orphan_rows_count_combines_unowned_old_rows_and_ownerless_rows(): void
    {
        $stale = $this->service->store(ImageFixtures::jpeg(900, 600), 'editor', null, ['alt_text' => 'Editor']);
        DB::table('media')->where('id', $stale->id)->update(['created_at' => now()->subHours(30)]);

        $doctor = $this->createDoctor();
        $photo = $this->service->store(ImageFixtures::jpeg(800, 800), 'photo', $doctor, ['alt_text' => 'Foto']);
        DB::table('doctor_specialties')->where('doctor_id', $doctor->id)->delete();
        DB::table('doctors')->where('id', $doctor->id)->delete();

        $report = $this->service->prune();

        $this->assertSame(2, $report->orphanRows);
        $this->assertNull(Media::query()->find($stale->id));
        $this->assertNull(Media::query()->find($photo->id));
        $this->assertSame(
            ['orphan_rows' => 2, 'orphan_files' => 0, 'bytes' => $report->bytes],
            AuditLog::query()->where('action', 'media.pruned')->sole()->new_values,
        );
    }

    public function test_the_prune_command_reports_ownerless_rows(): void
    {
        $gone = $this->createClinic();
        $cover = $this->cover($gone);
        DB::table('clinics')->where('id', $gone->id)->delete();

        $this->artisan('media:prune', ['--dry-run' => true])
            ->expectsOutputToContain('Baris media yatim: 1')
            ->expectsOutputToContain('#'.$cover->id)
            ->assertExitCode(0);
    }

    public function test_delete_for_is_atomic_when_one_media_cannot_be_deleted(): void
    {
        $clinic = $this->createClinic();
        $first = $this->cover($clinic);
        $second = $this->service->store(ImageFixtures::jpeg(900, 600), 'gallery', $clinic, ['alt_text' => 'Galeri']);

        // Pemicu galat audit pada penghapusan media kedua.
        $calls = 0;
        AuditLog::creating(function () use (&$calls): void {
            if (++$calls === 2) {
                throw new \RuntimeException('Gagal menulis audit.');
            }
        });

        try {
            $this->service->deleteFor($clinic);
            $this->fail('Galat audit seharusnya diteruskan.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Gagal menulis audit.', $e->getMessage());
        }

        $this->assertNotNull(Media::query()->find($first->id), 'Penghapusan pertama ikut di-rollback.');
        $this->assertNotNull(Media::query()->find($second->id));
        $this->assertTrue($this->filesExist($first));
        $this->assertTrue($this->filesExist($second));
    }
}
```

- [ ] **Step 21: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=PruneOwnerlessMediaTest
```
Expected: FAIL — `Tests: 5 failed, 1 passed`; mis. `Failed asserting that 0 is identical to 1.` (baris tanpa pemilik belum dihitung) dan `Failed asserting that null is not null.`

- [ ] **Step 22: Ubah `MediaService` dan `PruneMedia` (tool Edit)**

`app/Services/Media/MediaService.php` — tambahkan dua import (urutan alfabet; Pint merapikan):

old:
```text
use Illuminate\Database\Eloquent\Model;
```
new:
```text
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
```

`deleteFor()` dalam transaksi

old:
```text
        Media::query()
            ->where('model_type', $owner->getMorphClass())
            ->where('model_id', $owner->getKey())
            ->orderBy('id')
            ->get()
            ->each(fn (Media $media) => $this->delete($media));
```
new:
```text
        DB::transaction(function () use ($owner): void {
            Media::query()
                ->where('model_type', $owner->getMorphClass())
                ->where('model_id', $owner->getKey())
                ->orderBy('id')
                ->get()
                ->each(fn (Media $media) => $this->delete($media));
        });
```

Docblock `prune()`

old:
```text
     * (b) berkas di folder koleksi disk public yang tidak tercatat dan berumur > 1 jam.
```
new:
```text
     * (b) berkas di folder koleksi disk public yang tidak tercatat dan berumur > 1 jam,
     * (c) baris yang `model_type`/`model_id`-nya terisi tetapi baris pemiliknya sudah tidak ada (tanpa batas umur;
     * dihitung dalam `orphanRows`). Pemilik dari kelas yang tidak dikenal dibiarkan.
```

Pengambilan baris

old:
```text
        $rows = Media::query()
            ->orphaned(self::ORPHAN_ROW_HOURS)
```
new:
```text
        $unowned = Media::query()
            ->orphaned(self::ORPHAN_ROW_HOURS)
```
old:
```text
        $files = $this->orphanFiles();

        $bytes = 0;
```
new:
```text
        $ownerless = $this->ownerlessRows();
        $rows = $unowned->concat($ownerless);
        $files = $this->orphanFiles();

        $bytes = 0;
```

Perhitungan ukuran dan daftar

old:
```text
        foreach ($rows as $media) {
            $bytes += $media->size_bytes + $this->thumbnailSize($media);
            $items[] = "Baris media #{$media->id}: {$media->directory}/{$media->file_name} ({$media->human_size})";
        }
```
new:
```text
        foreach ($unowned as $media) {
            $bytes += $media->size_bytes + $this->thumbnailSize($media);
            $items[] = "Baris media #{$media->id}: {$media->directory}/{$media->file_name} ({$media->human_size})";
        }

        foreach ($ownerless as $media) {
            $bytes += $media->size_bytes + $this->thumbnailSize($media);
            $items[] = "Baris media #{$media->id} (pemilik ".class_basename($media->model_type)." #{$media->model_id} sudah tidak ada): {$media->directory}/{$media->file_name} ({$media->human_size})";
        }
```

Method baru, sisipkan sebelum `private function logoMediaId(): int`

old:
```text
    private function logoMediaId(): int
```
new:
```text
    /**
     * Baris media yang pemiliknya (menurut `model_type` + `model_id`) sudah tidak ada. Hanya tipe pemilik
     * yang kelasnya dikenal dan berupa model Eloquent yang diperiksa; tipe lain dibiarkan (tidak dapat dipastikan).
     * Pemilik dengan soft delete dianggap masih ada selama barisnya ada.
     *
     * @return Collection<int, Media>
     */
    private function ownerlessRows(): Collection
    {
        $ids = [];
        $types = Media::query()->whereNotNull('model_type')->whereNotNull('model_id')->distinct()->pluck('model_type');

        foreach ($types as $type) {
            $class = Relation::getMorphedModel($type) ?? $type;

            if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
                continue;
            }

            $owner = new $class;

            $found = Media::query()
                ->where('model_type', $type)
                ->whereNotNull('model_id')
                ->whereNotExists(fn ($query) => $query
                    ->select(DB::raw('1'))
                    ->from($owner->getTable())
                    ->whereColumn($owner->getQualifiedKeyName(), 'media.model_id'))
                ->pluck('id')
                ->all();

            $ids = [...$ids, ...$found];
        }

        return $ids === [] ? new Collection : Media::query()->whereIn('id', $ids)->orderBy('id')->get();
    }

    private function logoMediaId(): int
```

`app/Console/Commands/PruneMedia.php`

old:
```text
Hapus media yatim: baris tanpa pemilik lebih dari 24 jam dan berkas tak tercatat lebih dari 1 jam
```
new:
```text
Hapus media yatim: baris tanpa pemilik lebih dari 24 jam, baris yang pemiliknya sudah dihapus, dan berkas tak tercatat lebih dari 1 jam
```
(`rows` yang dipurge di bawah tetap `$rows`, kini gabungan butir (a) dan (c); sisa method tidak berubah.)

- [ ] **Step 23: Jalankan tes — harus lulus, termasuk seluruh tes media fase 1**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="PruneOwnerlessMediaTest|Media"
& $PHP artisan test --filter="HasUniqueSlugTest|SanitizesRichTextTest|HasMediaTest|PruneOwnerlessMediaTest"
```
Expected: PASS — `PruneOwnerlessMediaTest` 6 tes; seluruh tes media fase 1 (`PruneMediaTest`, `PruneMediaCommandTest`, `MediaServiceLifecycleTest`, dst.) tetap hijau; filter kedua `Tests: 58 passed`.

- [ ] **Step 24: Suite penuh, Pint, commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test
& $PHP vendor\bin\pint
& $PHP vendor\bin\pint --test
git add app/Services/Media/MediaService.php app/Console/Commands/PruneMedia.php tests/Feature/Media/PruneOwnerlessMediaTest.php
git commit -m @'
feat(fase-2): media:prune membersihkan baris media yang pemiliknya sudah tidak ada

Butir (c) F1-R12 dihitung dalam orphanRows dan tampil di dry-run; deleteFor() kini atomik.

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
'@
```
Expected: seluruh suite PASS; `pint --test` → `{"result":"passed"}`.

---

### Task 3: AuditObserver, pesan baku, dan locale tanggal

**Files:**
- Create: `app/Observers/AuditObserver.php`, `lang/id/messages.php`
- Modify: `app/Providers/AppServiceProvider.php` (daftarkan observer)
- Test: `tests/Feature/Audit/AuditObserverTest.php`, `tests/Feature/Support/MessagesLangTest.php`

**Interfaces:**
- Consumes: `AuditLogger::log(string $action, Model $model, ?array $old, ?array $new, string $description, ?Actor $actor = null)` (fase 1), `Actor::staff()/system()`, `User::isStaff()`, model `Specialty`/`Clinic` (Task 1), trait `SanitizesRichText` (Task 2; deskripsi poliklinik), `Tests\Support\{InteractsWithMasterData, InteractsWithStaff}`.
- Produces (persis kontrak):
  - `App\Observers\AuditObserver` (`final`, constructor `(AuditLogger $audit)`; `created`, `updated`, `deleted`). Aksi audit `specialty.created|updated|deleted` dan `clinic.created|updated|deleted`. Deskripsi dari `__('messages.audit.{event}', ['entity' => __('messages.entities.{key}'), 'name' => $model->name])`, mis. `Spesialisasi "Anak" ditambahkan.`. Pelaku: `Auth::user()` bila staf, selain itu `Actor::system()`.
  - `Specialty::observe(AuditObserver::class)` dan `Clinic::observe(AuditObserver::class)` di `AppServiceProvider::boot()`. `Doctor` **tidak** diobservasi (F2-R19).
  - `lang/id/messages.php`: `created` ":entity berhasil ditambahkan.", `updated` ":entity berhasil diperbarui.", `deleted` ":entity berhasil dihapus.", `delete_blocked`, `entities.{specialty,clinic,doctor,media}`, `audit.{created,updated,deleted}`.
- Detail yang ditambahkan:
  - `messages.delete_blocked` = `:reason` (parameter `reason` berisi kalimat lengkap dari `deletionBlockedReason()`); controller memakai `with('error', __('messages.delete_blocked', ['reason' => $reason]))` agar semua teks flash tetap berasal dari satu berkas.
  - Label entitas: `specialty` "Spesialisasi", `clinic` "Poliklinik", `doctor` "Dokter", `media` "Gambar". Contoh flash: `__('messages.created', ['entity' => __('messages.entities.doctor')])` = "Dokter berhasil ditambahkan.".
  - `updated` hanya mencatat kolom yang berubah (selain `updated_at`) dengan nilai lama dan baru; tanpa perubahan nyata (`save()`, `touch()`) tidak ada baris audit. `created` tidak memuat `created_at`/`updated_at`; `deleted` memuat nilai lama. Kolom `description`/`short_bio`/`bio`/`body` otomatis `[konten diubah]` oleh `AuditLogger`.
  - Akun pasien yang login dicatat sebagai pelaku **sistem** (bukan `patient`) karena master data hanya diubah staf; ini juga menutup tulis lewat konsol/seeder.
  - Task 4 menambahkan kunci `messages.rules.*` ke berkas yang sama.
- **Catatan konflik:** kontrak meminta `Carbon::setLocale(config('app.locale'))` "bila belum". Tes membuktikan locale Carbon **sudah** `id` (Laravel menyinkronkannya saat boot dari `config('app.locale')`), sehingga **tidak ada perubahan** di `AppServiceProvider` untuk locale; tes hanya menjadi penjaga regresi. Peta berkas di kerangka menyebut `AppServiceProvider (ubah: observer, locale Carbon)`; hanya bagian observer yang diubah.

**Pesan (sumber):**

| Kunci | Pesan | Sumber |
|---|---|---|
| `messages.created` | ":entity berhasil ditambahkan." | kontrak Task 3; 11 §11.1; 06 §12 |
| `messages.updated` | ":entity berhasil diperbarui." | kontrak Task 3 |
| `messages.deleted` | ":entity berhasil dihapus." | kontrak Task 3 |
| `messages.audit.created/updated/deleted` | `:entity ":name" ditambahkan.` / `diperbarui.` / `dihapus.` | kontrak Task 3 (contoh: Spesialisasi "Anak" ditambahkan.) |

**Test IDs:** AUD-01 (bagian `specialty.*`, `clinic.*`), CRD-01/CRD-02 (audit), format tanggal Indonesia (CHECKPOINT §4).

**Catatan teknis (diverifikasi dari source):**
- Pada event `updated`, `Model::syncChanges()` sudah berjalan sehingga `getChanges()` terisi, sedangkan `getOriginal()` **masih** berisi nilai lama (`syncOriginal()` baru dijalankan setelahnya). Karena itu `AuditLogger::diff()` (yang memakai `getPrevious()`) **tidak** dipakai di observer.
- `getAttributes()` mengembalikan nilai mentah (mis. `type` berupa string `poliklinik`, bukan enum), cocok untuk JSON audit.
- Redaksi `AuditLogger::NAME_ONLY` mengganti `description` menjadi `[konten diubah]` baik di `old_values` maupun `new_values`.
- `AuditObserver` memakai `Model::observe(Class::class)`: Laravel meresolusi observer lewat container, sehingga `AuditLogger` ter-inject.
- `Carbon::getLocale()` bernilai `id`; `Carbon::create(2026, 10, 4)->translatedFormat('l, j F Y')` = `Minggu, 4 Oktober 2026`.
- Semua blok PHP lolos `php -l` 8.2.

- [ ] **Step 1: Verifikasi prasyarat (Task 1-2 selesai)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git branch --show-current
git status --short
& $PHP artisan test --filter="AuditLoggerTest|HasUniqueSlugTest|SanitizesRichTextTest"
```
Expected: `fase-2-master-data`; `git status` bersih; PASS.

- [ ] **Step 2: Tulis tes yang gagal (tool Write)**

`tests/Feature/Audit/AuditObserverTest.php`:

```php
<?php

namespace Tests\Feature\Audit;

use App\Enums\ActorType;
use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

class AuditObserverTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithStaff;
    use RefreshDatabase;

    private function log(string $action): AuditLog
    {
        return AuditLog::query()->where('action', $action)->latest('id')->firstOrFail();
    }

    public function test_creating_a_specialty_writes_specialty_created_with_an_indonesian_description(): void
    {
        $admin = $this->createStaff('content-admin');
        $this->actingAs($admin);

        $specialty = $this->createSpecialty(['name' => 'Anak', 'description' => 'Layanan anak.']);

        $log = $this->log('specialty.created');

        $this->assertSame('Spesialisasi "Anak" ditambahkan.', $log->description);
        $this->assertSame(ActorType::Staff, $log->actor_type);
        $this->assertSame($admin->id, (int) $log->user_id);
        $this->assertSame(Specialty::class, $log->auditable_type);
        $this->assertSame($specialty->id, (int) $log->auditable_id);
        $this->assertNull($log->old_values);
        $this->assertSame('Anak', $log->new_values['name']);
        $this->assertSame('anak', $log->new_values['slug']);
        $this->assertSame('[konten diubah]', $log->new_values['description'], 'Teks panjang hanya dicatat berubah.');
        $this->assertArrayNotHasKey('created_at', $log->new_values);
        $this->assertArrayNotHasKey('updated_at', $log->new_values);
    }

    public function test_updating_records_only_changed_columns_with_old_and_new_values(): void
    {
        $admin = $this->createStaff('content-admin');
        $clinic = $this->createClinic(['name' => 'Poliklinik Anak', 'location' => 'Gedung A Lt. 1', 'is_active' => true]);
        AuditLog::query()->delete();

        $this->actingAs($admin);
        $clinic->update(['location' => 'Gedung B Lt. 2', 'is_active' => false]);

        $log = $this->log('clinic.updated');

        $this->assertSame('Poliklinik "Poliklinik Anak" diperbarui.', $log->description);
        $this->assertEquals(['location' => 'Gedung A Lt. 1', 'is_active' => 1], $log->old_values);
        $this->assertEquals(['location' => 'Gedung B Lt. 2', 'is_active' => 0], $log->new_values);
        $this->assertSame(1, AuditLog::query()->where('action', 'clinic.updated')->count());
    }

    public function test_rich_text_changes_are_logged_as_content_changed_only(): void
    {
        $clinic = $this->createClinic(['description' => '<p>Isi lama</p>']);
        AuditLog::query()->delete();

        $clinic->update(['description' => '<p>Isi baru</p>']);

        $log = $this->log('clinic.updated');

        $this->assertSame(['description' => '[konten diubah]'], $log->new_values);
        $this->assertSame(['description' => '[konten diubah]'], $log->old_values);
        $this->assertStringNotContainsString('Isi baru', (string) json_encode($log->new_values));
    }

    public function test_saves_without_real_changes_write_nothing(): void
    {
        $specialty = $this->createSpecialty();
        AuditLog::query()->delete();

        $specialty->save();
        $specialty->touch();
        $specialty->update(['name' => $specialty->name]);

        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_deleting_writes_the_old_values(): void
    {
        $specialty = $this->createSpecialty(['name' => 'Mata']);
        AuditLog::query()->delete();

        $specialty->delete();

        $log = $this->log('specialty.deleted');

        $this->assertSame('Spesialisasi "Mata" dihapus.', $log->description);
        $this->assertSame('Mata', $log->old_values['name']);
        $this->assertNull($log->new_values);
        $this->assertSame($specialty->id, (int) $log->auditable_id);
    }

    public function test_clinic_events_use_the_clinic_prefix_and_entity_label(): void
    {
        $clinic = $this->createClinic(['name' => 'Poliklinik Jantung']);
        $clinic->delete();

        $this->assertSame('Poliklinik "Poliklinik Jantung" ditambahkan.', $this->log('clinic.created')->description);
        $this->assertSame('Poliklinik "Poliklinik Jantung" dihapus.', $this->log('clinic.deleted')->description);
    }

    public function test_without_a_logged_in_user_the_actor_is_system(): void
    {
        $this->createSpecialty();

        $log = $this->log('specialty.created');

        $this->assertSame(ActorType::System, $log->actor_type);
        $this->assertNull($log->user_id);
    }

    public function test_a_logged_in_patient_is_recorded_as_system_not_as_a_patient_actor(): void
    {
        $this->actingAs(User::factory()->create());

        $this->createSpecialty();

        $log = $this->log('specialty.created');

        $this->assertSame(ActorType::System, $log->actor_type);
        $this->assertNull($log->user_id);
    }

    public function test_doctor_is_not_observed(): void
    {
        $doctor = $this->createDoctor();
        AuditLog::query()->delete();

        $doctor->update(['name' => 'Nama Baru']);
        $doctor->specialties()->detach();

        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_the_observer_is_registered_for_exactly_specialty_and_clinic(): void
    {
        $this->createSpecialty();
        $this->createClinic();

        $this->assertSame(
            ['clinic.created', 'specialty.created'],
            AuditLog::query()->orderBy('action')->pluck('action')->all(),
        );
        $this->assertSame(1, Clinic::query()->count());
    }
}
```

`tests/Feature/Support/MessagesLangTest.php`:

```php
<?php

namespace Tests\Feature\Support;

use Carbon\Carbon;
use Tests\TestCase;

class MessagesLangTest extends TestCase
{
    public function test_standard_flash_messages_are_complete_indonesian_sentences(): void
    {
        $doctor = __('messages.entities.doctor');

        $this->assertSame('Dokter', $doctor);
        $this->assertSame('Dokter berhasil ditambahkan.', __('messages.created', ['entity' => $doctor]));
        $this->assertSame('Spesialisasi berhasil diperbarui.', __('messages.updated', ['entity' => __('messages.entities.specialty')]));
        $this->assertSame('Poliklinik berhasil dihapus.', __('messages.deleted', ['entity' => __('messages.entities.clinic')]));
        $this->assertSame('Gambar berhasil dihapus.', __('messages.deleted', ['entity' => __('messages.entities.media')]));
    }

    public function test_delete_blocked_passes_the_reason_through(): void
    {
        $this->assertSame('Alasan lengkap.', __('messages.delete_blocked', ['reason' => 'Alasan lengkap.']));
    }

    public function test_audit_descriptions(): void
    {
        $entity = __('messages.entities.specialty');

        $this->assertSame('Spesialisasi "Anak" ditambahkan.', __('messages.audit.created', ['entity' => $entity, 'name' => 'Anak']));
        $this->assertSame('Spesialisasi "Anak" diperbarui.', __('messages.audit.updated', ['entity' => $entity, 'name' => 'Anak']));
        $this->assertSame('Spesialisasi "Anak" dihapus.', __('messages.audit.deleted', ['entity' => $entity, 'name' => 'Anak']));
    }

    public function test_every_key_is_translated_and_not_returned_as_a_key(): void
    {
        $keys = ['created', 'updated', 'deleted', 'delete_blocked', 'entities.specialty', 'entities.clinic', 'entities.doctor', 'entities.media', 'audit.created', 'audit.updated', 'audit.deleted'];

        foreach ($keys as $key) {
            $this->assertNotSame("messages.{$key}", __("messages.{$key}"), $key);
        }
    }

    public function test_dates_are_formatted_in_indonesian_without_extra_setup(): void
    {
        $date = Carbon::create(2026, 10, 4, 9, 0, 0, 'Asia/Jakarta');

        $this->assertSame('id', Carbon::getLocale());
        $this->assertSame('Minggu, 4 Oktober 2026', $date->translatedFormat('l, j F Y'));
        $this->assertSame('Senin, 5 Oktober 2026', now()->setDate(2026, 10, 5)->translatedFormat('l, j F Y'));
    }
}
```

- [ ] **Step 3: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="AuditObserverTest|MessagesLangTest"
```
Expected: FAIL — `AuditObserverTest`: 8 gagal, 2 lulus (tidak ada baris audit; `ModelNotFoundException`); `MessagesLangTest`: 4 gagal (`Failed asserting that two strings are identical.` karena kunci terjemahan dikembalikan apa adanya), 1 lulus (`test_dates_are_formatted_in_indonesian_without_extra_setup` sudah hijau: locale Carbon sudah `id`, tidak perlu `Carbon::setLocale`).

- [ ] **Step 4: Tulis pesan baku dan observer (tool Write)**

`lang/id/messages.php`:

```php
<?php

return [

    /*
    | Pesan baku flash dan jejak audit (11 §11.1). Kalimat lengkap berbahasa Indonesia berakhir titik.
    | Pemakaian: __('messages.created', ['entity' => __('messages.entities.doctor')]).
    */

    'created' => ':entity berhasil ditambahkan.',
    'updated' => ':entity berhasil diperbarui.',
    'deleted' => ':entity berhasil dihapus.',

    // Penolakan hapus: :reason berisi kalimat lengkap dari deletionBlockedReason().
    'delete_blocked' => ':reason',

    'entities' => [
        'specialty' => 'Spesialisasi',
        'clinic' => 'Poliklinik',
        'doctor' => 'Dokter',
        'media' => 'Gambar',
    ],

    // Deskripsi baris audit yang ditulis AuditObserver.
    'audit' => [
        'created' => ':entity ":name" ditambahkan.',
        'updated' => ':entity ":name" diperbarui.',
        'deleted' => ':entity ":name" dihapus.',
    ],

];
```

`app/Observers/AuditObserver.php`:

```php
<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Actor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Jejak audit otomatis untuk model master sederhana (11 §5.9, F2-R19): `specialty.*` dan `clinic.*`.
 * Aksi = snake_case nama kelas + "." + peristiwa. Pelaku: staf yang login, selain itu sistem.
 * `Doctor` sengaja tidak diobservasi (DoctorService mencatat `doctor.*` sendiri).
 */
final class AuditObserver
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function created(Model $model): void
    {
        $this->write($model, 'created', [], Arr::except($model->getAttributes(), ['created_at', 'updated_at']));
    }

    public function updated(Model $model): void
    {
        // Pada event `updated`, getChanges() sudah terisi dan getOriginal() masih berisi nilai lama.
        $changes = Arr::except($model->getChanges(), ['updated_at']);

        if ($changes === []) {
            return;
        }

        $this->write($model, 'updated', Arr::only($model->getOriginal(), array_keys($changes)), $changes);
    }

    public function deleted(Model $model): void
    {
        $this->write($model, 'deleted', Arr::except($model->getOriginal(), ['created_at', 'updated_at']), []);
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    private function write(Model $model, string $event, array $old, array $new): void
    {
        $key = Str::snake(class_basename($model));

        $this->audit->log(
            "{$key}.{$event}",
            $model,
            $old,
            $new,
            __("messages.audit.{$event}", [
                'entity' => __("messages.entities.{$key}"),
                'name' => (string) $model->getAttribute('name'),
            ]),
            $this->actor(),
        );
    }

    private function actor(): Actor
    {
        $user = Auth::user();

        return $user instanceof User && $user->isStaff() ? Actor::staff($user) : Actor::system();
    }
}
```

- [ ] **Step 5: Daftarkan observer (tool Edit `app/Providers/AppServiceProvider.php`)**

Prasyarat byte. `AppServiceProvider.php` warisan fase 1 memuat satu byte Latin-1 `0xA7` (bukan UTF-8 sah) pada komentar `// URI resource berbahasa Indonesia: /buat dan /ubah (11 §3.3).` di atas `Route::resourceVerbs` (komentar lain bertanda `§` di berkas itu sudah UTF-8). Tool Edit dapat menulis ulang byte itu sebagai U+FFFD, jadi perbaiki dulu ke UTF-8 "§" (`0xC2 0xA7`) dengan PowerShell: baca byte, sisipkan `0xC2` di depan tiap `0xA7` yang BELUM didahului `0xC2`. Aman dijalankan ulang (tidak mengubah apa pun bila byte sudah benar).

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
$p = Join-Path $APP 'app\Providers\AppServiceProvider.php'
$b = [System.IO.File]::ReadAllBytes($p)
$out = New-Object 'System.Collections.Generic.List[byte]'
for ($i = 0; $i -lt $b.Length; $i++) {
    if ($b[$i] -eq 0xA7 -and ($i -eq 0 -or $b[$i - 1] -ne 0xC2)) { $out.Add(0xC2) }
    $out.Add($b[$i])
}
[System.IO.File]::WriteAllBytes($p, $out.ToArray())
& $PHP -l app\Providers\AppServiceProvider.php
git diff app/Providers/AppServiceProvider.php
```
Expected: `No syntax errors detected`; `git diff` hanya memuat SATU hunk, yaitu baris komentar `(11 §3.3)` di atas `Route::resourceVerbs` (byte `0xA7` menjadi `0xC2 0xA7`). Baru setelah itu lakukan dua Edit di bawah.

old:
```text
use App\Models\User;
```
new:
```text
use App\Models\Clinic;
use App\Models\Specialty;
use App\Models\User;
use App\Observers\AuditObserver;
```
old:
```text
        Paginator::useBootstrapFive();
```
new:
```text
        Paginator::useBootstrapFive();

        // Audit otomatis model master sederhana (F2-R19). Doctor dicatat DoctorService.
        Specialty::observe(AuditObserver::class);
        Clinic::observe(AuditObserver::class);
```

Periksa diff. `git diff app/Providers/AppServiceProvider.php` hanya boleh memuat hunk impor, hunk observer, dan perbaikan byte komentar `§`; tidak ada baris lain yang berubah (tidak ada karakter pengganti U+FFFD):

```powershell
git diff app/Providers/AppServiceProvider.php
& $PHP -l app\Providers\AppServiceProvider.php
```
Bila ada perubahan lain, pulihkan hanya baris yang salah (komentar itu harus `(11 §3.3)`) lalu ulangi pemeriksaan.

- [ ] **Step 6: Jalankan tes — harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="AuditObserverTest|MessagesLangTest"
& $PHP artisan test
```
Expected: PASS — `Tests: 15 passed`; seluruh suite PASS (tes lain yang membuat `Specialty`/`Clinic` kini menambah baris audit tanpa efek).

- [ ] **Step 7: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint
& $PHP vendor\bin\pint --test
git add app/Observers/AuditObserver.php lang/id/messages.php app/Providers/AppServiceProvider.php tests/Feature/Audit/AuditObserverTest.php tests/Feature/Support/MessagesLangTest.php
git commit -m @'
feat(fase-2): AuditObserver untuk spesialisasi dan poliklinik serta pesan baku messages.php

Aksi specialty.* dan clinic.* dengan deskripsi Indonesia dan pelaku staf atau sistem;
kunci pesan flash baku. Tes menjaga locale tanggal Indonesia (sudah aktif, tanpa perubahan kode).

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
'@
```

---

### Task 4: Aturan validasi dan Policy

**Files:**
- Create: `app/Rules/PersonName.php`, `app/Rules/NoHtml.php`
- Create: `app/Policies/SpecialtyPolicy.php`, `ClinicPolicy.php`, `DoctorPolicy.php`, `MediaPolicy.php`
- Modify: `lang/id/messages.php` (kunci `rules.*`)
- Test: `tests/Unit/Rules/PersonNameNoHtmlTest.php`, `tests/Feature/Auth/MasterDataPoliciesTest.php`, `tests/Feature/Auth/MediaPolicyTest.php`

**Interfaces:**
- Consumes: `User` + Spatie `HasRoles` (`can()`), `RolePermissionSeeder` (izin `doctors.view/manage`, `clinics.manage`, `settings.manage`, `content.manage`, `facilities.manage`), `Gate::before` super-admin (fase 1), `Media` (fase 1), model Task 1, `InteractsWithStaff`, `InteractsWithMedia`.
- Produces (persis kontrak):
  - `App\Rules\PersonName` dan `App\Rules\NoHtml` (`ValidationRule`, `final`; konstanta `PATTERN`).
  - `SpecialtyPolicy`/`ClinicPolicy`: `viewAny`, `view`, `create`, `update`, `delete` → `clinics.manage` (`view` ada di 04 §5 dan 11 §8; kontrak menyebut empat ability pertama).
  - `DoctorPolicy`: `viewAny`, `view` → `doctors.view`; `create`, `update`, `changeStatus`, `archive` → `doctors.manage`; **tanpa** `delete`.
  - `MediaPolicy` (F2-R16): `upload(User)`, `update(User, Media)`, `delete(User, Media)`, `reorder(User, Media $anyMediaOfOwner)`.
  - Policy ditemukan otomatis (`App\Policies\{Model}Policy`); tidak perlu `Gate::policy()`.
- Detail yang ditambahkan (dipakai Task 6, 9-12):
  - Gunakan `$this->authorize('viewAny', Specialty::class)`, `authorize('update', $doctor)`, `authorize('changeStatus', $doctor)`, `authorize('archive', $doctor)`; foto dokter memakai `update`.
  - Media: `Gate::authorize('upload', Media::class)` untuk unggah editor (izin: salah satu `content.manage`, `clinics.manage`, `facilities.manage`; hanya staf). `update`/`delete` memakai peta pemilik: `Doctor` → `doctors.manage`; `Clinic`/`Specialty` → `clinics.manage`; `model_type` NULL: `logo` → `settings.manage`, `editor` → pengunggah sendiri atau izin kelompok di atas, koleksi lain ditolak; `model_type` lain (mis. `App\Models\Facility`) **ditolak** (Super Admin tetap lolos lewat `Gate::before`). `reorder` pada media tanpa pemilik ditolak (urutan hanya bermakna per pemilik). Akun pasien selalu ditolak, termasuk bila `uploaded_by` miliknya.
  - Policy **tidak** memeriksa keterrujukan data (hapus spesialisasi/poliklinik): itu tugas controller lewat `deletionBlockedReason()` agar berlaku juga bagi Super Admin (F2-R18).
  - Pesan aturan: `PersonName` → ":Attribute hanya boleh berisi huruf, spasi, titik, apostrof, dan tanda hubung."; `NoHtml` → ":Attribute tidak boleh mengandung tag HTML." (kunci `messages.rules.person_name` dan `messages.rules.no_html`; `:Attribute` diganti label bidang dari `attributes()` Form Request, mis. "Nama dokter").
  - Kedua aturan tidak berjalan untuk nilai kosong (pakai `required`/`nullable` di Form Request); nilai non-string selalu ditolak.
- **Catatan konflik:** 12 §3.1 mendefinisikan `NoHtml` sebagai `not_regex:/<\s*\/?\s*[a-zA-Z!][^>]*>/` dan menyatakan teks seperti "tekanan <90 dan >100" lolos. Regex yang sama **menolak** "a < b dan c > d" (tanda `<` diikuti spasi lalu huruf, lalu `>` kemudian). Perilaku regex spesifikasi dipertahankan persis dan dicatat di tes; tidak diubah.

**Pesan (sumber):**

| Aturan | Pesan | Sumber |
|---|---|---|
| `PersonName` | "{Label} hanya boleh berisi huruf, spasi, titik, apostrof, dan tanda hubung." | 12 §3.1 |
| `NoHtml` | "{Label} tidak boleh mengandung tag HTML." | 12 §3.1 |

**Test IDs:** AKS (matriks Policy per peran), KNS (otorisasi), VAL-01 (dasar aturan nama), Review Focus 5 (akses media lintas pemilik).

**Catatan teknis (diverifikasi dari source):**
- `Gate::before` fase 1 meloloskan Super Admin untuk **setiap** ability (termasuk `delete` dokter yang tidak didefinisikan); karena itu tes `DoctorPolicy` menegaskan ketiadaan method `delete` lewat `method_exists`, sementara peran lain ditolak.
- `Gate::allows('upload', Media::class)`: Laravel membuang argumen string kelas sebelum memanggil method Policy (`callPolicyMethod`), sehingga signature `upload(User $user)` benar.
- `User::can('x')` melewati `Gate::before` fase 1 lalu pengecekan izin Spatie; izin yang tidak ada tidak melempar.
- `ValidationRule::validate` dengan `$fail('messages.rules.person_name')->translate()`: terjemahan dan penggantian `:attribute`/`:Attribute` dilakukan validator.
- Pasien dibuat `User::factory()->create()` (tanpa peran): semua `can()` bernilai false.
- Semua blok PHP lolos `php -l` 8.2.

- [ ] **Step 1: Verifikasi prasyarat (Task 1-3 selesai)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git branch --show-current
git status --short
& $PHP artisan test --filter="GateBeforeTest|RolePermissionSeederTest|AuditObserverTest"
```
Expected: `fase-2-master-data`; `git status` bersih; PASS.

**Siklus A — `PersonName` dan `NoHtml`**

- [ ] **Step 2: Tulis tes yang gagal (tool Write) `tests/Unit/Rules/PersonNameNoHtmlTest.php`**

```php
<?php

namespace Tests\Unit\Rules;

use App\Rules\NoHtml;
use App\Rules\PersonName;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PersonNameNoHtmlTest extends TestCase
{
    /** @return array<string, array{0: mixed, 1: bool}> */
    public static function personNames(): array
    {
        return [
            'nama biasa' => ['Andi Pratama', true],
            'dengan titik' => ['dr. Siti', true],
            'apostrof lurus' => ["Siti Nur'aini", true],
            'apostrof tipografis' => ['Siti Nur’aini', true],
            'tanda hubung' => ['Nur-Aini', true],
            'huruf beraksen' => ['José Ñandú', true],
            'huruf non-Latin' => ['أحمد', true],
            'angka ditolak' => ['Andi 2', false],
            'koma ditolak' => ['Sp.A, dr', false],
            'tag ditolak' => ['<b>Andi</b>', false],
            'simbol ditolak' => ['Andi@Pratama', false],
            'garis bawah ditolak' => ['Andi_Pratama', false],
            'bukan string' => [['Andi'], false],
            'angka murni' => [12345, false],
        ];
    }

    #[DataProvider('personNames')]
    public function test_person_name(mixed $value, bool $passes): void
    {
        $validator = Validator::make(['name' => $value], ['name' => [new PersonName]]);

        $this->assertSame($passes, $validator->passes());
    }

    public function test_person_name_message_uses_the_attribute_label(): void
    {
        $validator = Validator::make(['name' => 'Andi 2'], ['name' => [new PersonName]], [], ['name' => 'Nama dokter']);

        $this->assertSame(
            'Nama dokter hanya boleh berisi huruf, spasi, titik, apostrof, dan tanda hubung.',
            $validator->errors()->first('name'),
        );
    }

    public function test_empty_values_are_left_to_required(): void
    {
        $this->assertTrue(Validator::make(['name' => ''], ['name' => [new PersonName]])->passes());
        $this->assertTrue(Validator::make([], ['name' => [new PersonName]])->passes());
        $this->assertFalse(Validator::make(['name' => ''], ['name' => ['required', new PersonName]])->passes());
    }

    /** @return array<string, array{0: mixed, 1: bool}> */
    public static function htmlValues(): array
    {
        return [
            'teks biasa' => ['Layanan kesehatan anak.', true],
            'perbandingan angka' => ['tekanan <90 dan >100', true],
            'kurang dari lalu huruf dan lebih dari (mirip tag)' => ['a < b dan c > d', false],
            'kurang dari diikuti angka' => ['nilai <5 dan >2', true],
            'tag paragraf' => ['<p>Halo</p>', false],
            'tag penutup' => ['teks </div>', false],
            'tag dengan spasi' => ['< script >alert(1)', false],
            'tag dengan atribut' => ['<img src=x onerror=alert(1)>', false],
            'komentar atau doctype' => ['<!-- komentar -->', false],
            'tag huruf besar' => ['<B>tebal</B>', false],
            'tag bukan string' => [['<b>'], false],
        ];
    }

    #[DataProvider('htmlValues')]
    public function test_no_html(mixed $value, bool $passes): void
    {
        $validator = Validator::make(['bio' => $value], ['bio' => [new NoHtml]]);

        $this->assertSame($passes, $validator->passes());
    }

    public function test_no_html_message_uses_the_attribute_label(): void
    {
        $validator = Validator::make(['bio' => '<b>x</b>'], ['bio' => [new NoHtml]], [], ['bio' => 'Profil lengkap']);

        $this->assertSame('Profil lengkap tidak boleh mengandung tag HTML.', $validator->errors()->first('bio'));
    }

    public function test_the_regex_constants_match_the_spec(): void
    {
        $this->assertSame('/^[\p{L}\s.\'’-]+$/u', PersonName::PATTERN);
        $this->assertSame('/<\s*\/?\s*[a-zA-Z!][^>]*>/', NoHtml::PATTERN);
    }
}
```

- [ ] **Step 3: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=PersonNameNoHtmlTest
```
Expected: FAIL — `Tests: 29 failed` (`Class "App\Rules\PersonName" not found` / `Class "App\Rules\NoHtml" not found`).

- [ ] **Step 4: Tulis aturan (tool Write) dan pesan (tool Edit)**

`app/Rules/PersonName.php`:

```php
<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Nama orang (12 §3.1): huruf Unicode, spasi, titik, apostrof (' dan ’), dan tanda hubung. Angka ditolak.
 * Aturan tidak dijalankan untuk nilai kosong (kewajiban diisi ditentukan `required`).
 */
final class PersonName implements ValidationRule
{
    public const PATTERN = '/^[\p{L}\s.\'’-]+$/u';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match(self::PATTERN, $value) !== 1) {
            $fail('messages.rules.person_name')->translate();
        }
    }
}
```

`app/Rules/NoHtml.php`:

```php
<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Menolak pola tag HTML sungguhan (12 §3.1); teks seperti "tekanan <90 dan >100" tetap lolos.
 * Aturan tidak dijalankan untuk nilai kosong.
 */
final class NoHtml implements ValidationRule
{
    public const PATTERN = '/<\s*\/?\s*[a-zA-Z!][^>]*>/';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match(self::PATTERN, $value) === 1) {
            $fail('messages.rules.no_html')->translate();
        }
    }
}
```

`lang/id/messages.php`

old:
```text
    // Deskripsi baris audit yang ditulis AuditObserver.
```
new:
```text
    // Pesan aturan validasi kustom (12 §3.1); :attribute diganti nama bidang oleh validator.
    'rules' => [
        'person_name' => ':Attribute hanya boleh berisi huruf, spasi, titik, apostrof, dan tanda hubung.',
        'no_html' => ':Attribute tidak boleh mengandung tag HTML.',
    ],

    // Deskripsi baris audit yang ditulis AuditObserver.
```

- [ ] **Step 5: Jalankan tes — harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="PersonNameNoHtmlTest|MessagesLangTest"
```
Expected: PASS — `PersonNameNoHtmlTest` 29 tes; `MessagesLangTest` tetap hijau.

- [ ] **Step 6: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint
& $PHP vendor\bin\pint --test
git add app/Rules/PersonName.php app/Rules/NoHtml.php lang/id/messages.php tests/Unit/Rules/PersonNameNoHtmlTest.php
git commit -m @'
feat(fase-2): aturan validasi PersonName dan NoHtml

Regex persis 12 §3.1 dengan pesan Indonesia yang memakai label bidang.

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
'@
```

**Siklus B — Policy spesialisasi, poliklinik, dokter**

- [ ] **Step 7: Tulis tes yang gagal (tool Write) `tests/Feature/Auth/MasterDataPoliciesTest.php`**

```php
<?php

namespace Tests\Feature\Auth;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Specialty;
use App\Models\User;
use App\Policies\ClinicPolicy;
use App\Policies\DoctorPolicy;
use App\Policies\SpecialtyPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * 04 §5 dan 11 §8 untuk SpecialtyPolicy, ClinicPolicy, DoctorPolicy.
 * Peran: super-admin, content-admin, registration-officer, ditambah pasien.
 */
class MasterDataPoliciesTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithStaff;
    use RefreshDatabase;

    /** @return array<string, array{0: string, 1: string, 2: array<string, bool>}> */
    public static function matrix(): array
    {
        $manage = ['super-admin' => true, 'content-admin' => true, 'registration-officer' => false, 'patient' => false];
        $view = ['super-admin' => true, 'content-admin' => true, 'registration-officer' => true, 'patient' => false];
        $cases = [];

        foreach (['specialty', 'clinic'] as $model) {
            foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
                $cases["{$model}.{$ability}"] = [$model, $ability, $manage];
            }
        }

        foreach (['viewAny', 'view'] as $ability) {
            $cases["doctor.{$ability}"] = ['doctor', $ability, $view];
        }

        foreach (['create', 'update', 'changeStatus', 'archive'] as $ability) {
            $cases["doctor.{$ability}"] = ['doctor', $ability, $manage];
        }

        return $cases;
    }

    /**
     * @param  array<string, bool>  $expected
     */
    #[DataProvider('matrix')]
    public function test_ability_per_role(string $model, string $ability, array $expected): void
    {
        $subject = match ($model) {
            'specialty' => $this->createSpecialty(),
            'clinic' => $this->createClinic(),
            'doctor' => $this->createDoctor(),
        };
        $class = $subject::class;
        $argument = in_array($ability, ['viewAny', 'create'], true) ? $class : $subject;

        foreach ($expected as $role => $allowed) {
            $user = $this->userFor($role);

            $this->assertSame(
                $allowed,
                Gate::forUser($user)->allows($ability, $argument),
                "{$role} -> {$class}::{$ability}",
            );
        }
    }

    public function test_doctor_has_no_delete_ability_even_for_super_admin_through_the_policy(): void
    {
        $this->assertFalse(method_exists(DoctorPolicy::class, 'delete'));

        // Gate::before meloloskan Super Admin hanya untuk ability yang dipanggil; route hapus dokter memang tidak ada.
        $this->assertTrue(Gate::forUser($this->userFor('super-admin'))->allows('delete', $this->createDoctor()));
        $this->assertFalse(Gate::forUser($this->userFor('content-admin'))->allows('delete', $this->createDoctor()));
        $this->assertFalse(Gate::forUser($this->userFor('registration-officer'))->allows('delete', $this->createDoctor()));
    }

    public function test_policies_are_discovered_automatically(): void
    {
        $this->assertInstanceOf(SpecialtyPolicy::class, Gate::getPolicyFor(Specialty::class));
        $this->assertInstanceOf(ClinicPolicy::class, Gate::getPolicyFor(Clinic::class));
        $this->assertInstanceOf(DoctorPolicy::class, Gate::getPolicyFor(Doctor::class));
    }

    public function test_policies_ignore_deletion_blocking_data_because_the_controller_decides(): void
    {
        $clinic = $this->createDoctor()->clinic()->first();
        $specialty = $this->createDoctor()->specialties()->first();

        $this->assertNotNull($clinic->deletionBlockedReason());
        $this->assertTrue(Gate::forUser($this->userFor('content-admin'))->allows('delete', $clinic));
        $this->assertTrue(Gate::forUser($this->userFor('content-admin'))->allows('delete', $specialty));
    }

    private function userFor(string $role): User
    {
        return $role === 'patient'
            ? User::factory()->create()
            : User::query()->findOrFail($this->createStaff($role)->id);
    }
}
```

- [ ] **Step 8: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=MasterDataPoliciesTest
```
Expected: FAIL — `Tests: 18 failed, 1 passed`; mis. `Failed asserting that false is identical to true.` (tanpa Policy, Gate menolak peran konten).

- [ ] **Step 9: Tulis Policy (tool Write)**

`app/Policies/SpecialtyPolicy.php`:

```php
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Specialty;
use App\Models\User;

/**
 * 04 §5 / 11 §8: seluruh kemampuan memerlukan izin `clinics.manage`. Penolakan hapus karena data masih
 * dirujuk dilakukan controller lewat Specialty::deletionBlockedReason() (F2-R18), bukan di Policy,
 * agar berlaku juga untuk Super Admin (Gate::before melewati Policy).
 */
final class SpecialtyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('clinics.manage');
    }

    public function view(User $user, Specialty $specialty): bool
    {
        return $user->can('clinics.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('clinics.manage');
    }

    public function update(User $user, Specialty $specialty): bool
    {
        return $user->can('clinics.manage');
    }

    public function delete(User $user, Specialty $specialty): bool
    {
        return $user->can('clinics.manage');
    }
}
```

`app/Policies/ClinicPolicy.php`:

```php
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Clinic;
use App\Models\User;

/**
 * 04 §5 / 11 §8: seluruh kemampuan memerlukan izin `clinics.manage`. Penolakan hapus karena data masih
 * dirujuk dilakukan controller lewat Clinic::deletionBlockedReason() (F2-R18).
 */
final class ClinicPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('clinics.manage');
    }

    public function view(User $user, Clinic $clinic): bool
    {
        return $user->can('clinics.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('clinics.manage');
    }

    public function update(User $user, Clinic $clinic): bool
    {
        return $user->can('clinics.manage');
    }

    public function delete(User $user, Clinic $clinic): bool
    {
        return $user->can('clinics.manage');
    }
}
```

`app/Policies/DoctorPolicy.php`:

```php
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Doctor;
use App\Models\User;

/**
 * 04 §5 / 11 §8. Lihat: `doctors.view`; kelola: `doctors.manage`. Unggah/ganti foto memakai `update`.
 * Tidak ada `delete`: dokter dirujuk riwayat booking, hanya dinonaktifkan atau diarsipkan.
 * Aturan "arsip ditolak bila ada appointment aktif" ditegakkan Form Request/service (F2-R8), bukan di sini.
 */
final class DoctorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('doctors.view');
    }

    public function view(User $user, Doctor $doctor): bool
    {
        return $user->can('doctors.view');
    }

    public function create(User $user): bool
    {
        return $user->can('doctors.manage');
    }

    public function update(User $user, Doctor $doctor): bool
    {
        return $user->can('doctors.manage');
    }

    public function changeStatus(User $user, Doctor $doctor): bool
    {
        return $user->can('doctors.manage');
    }

    public function archive(User $user, Doctor $doctor): bool
    {
        return $user->can('doctors.manage');
    }
}
```

- [ ] **Step 10: Jalankan tes — harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=MasterDataPoliciesTest
```
Expected: PASS.

- [ ] **Step 11: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint
& $PHP vendor\bin\pint --test
git add app/Policies/SpecialtyPolicy.php app/Policies/ClinicPolicy.php app/Policies/DoctorPolicy.php tests/Feature/Auth/MasterDataPoliciesTest.php
git commit -m @'
feat(fase-2): SpecialtyPolicy, ClinicPolicy, dan DoctorPolicy

clinics.manage untuk spesialisasi dan poliklinik; doctors.view untuk lihat dan doctors.manage
untuk kelola/status/arsip dokter, tanpa delete. Diuji untuk tiga peran, Super Admin, dan pasien.

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
'@
```

**Siklus C — `MediaPolicy`**

- [ ] **Step 12: Tulis tes yang gagal (tool Write) `tests/Feature/Auth/MediaPolicyTest.php`**

```php
<?php

namespace Tests\Feature\Auth;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Media;
use App\Models\Specialty;
use App\Models\User;
use App\Policies\MediaPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithMedia;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * F2-R16 / Review Focus 5: akses media lintas pemilik.
 */
class MediaPolicyTest extends TestCase
{
    use InteractsWithMedia;
    use InteractsWithStaff;
    use RefreshDatabase;

    private const ROLES = ['super-admin', 'content-admin', 'registration-officer', 'patient'];

    /**
     * Pemilik: [model_type, koleksi, izin yang dibutuhkan, peran yang diizinkan].
     *
     * @return array<string, array{0: ?string, 1: string, 2: array<string, bool>}>
     */
    public static function ownerCases(): array
    {
        $contentOnly = ['super-admin' => true, 'content-admin' => true, 'registration-officer' => false, 'patient' => false];
        $superOnly = ['super-admin' => true, 'content-admin' => false, 'registration-officer' => false, 'patient' => false];

        return [
            'foto dokter' => [Doctor::class, 'photo', $contentOnly],
            'cover poliklinik' => [Clinic::class, 'cover', $contentOnly],
            'galeri poliklinik' => [Clinic::class, 'gallery', $contentOnly],
            'media spesialisasi' => [Specialty::class, 'cover', $contentOnly],
            'logo situs (tanpa pemilik)' => [null, 'logo', $superOnly],
            'tipe pemilik yang belum dipetakan' => ['App\\Models\\Facility', 'cover', $superOnly],
            'koleksi tanpa pemilik selain logo dan editor' => [null, 'photo', $superOnly],
        ];
    }

    /**
     * @param  array<string, bool>  $expected
     */
    #[DataProvider('ownerCases')]
    public function test_update_and_delete_follow_the_owner_type(?string $type, string $collection, array $expected): void
    {
        $media = $this->mediaFor($type, $collection);

        foreach ($expected as $role => $allowed) {
            $user = $this->userFor($role);

            foreach (['update', 'delete'] as $ability) {
                $this->assertSame($allowed, Gate::forUser($user)->allows($ability, $media), "{$role} -> {$ability} {$collection}");
            }
        }
    }

    public function test_reorder_is_only_for_media_that_has_an_owner(): void
    {
        $owned = $this->mediaFor(Clinic::class, 'gallery');
        $unowned = $this->mediaFor(null, 'editor');
        $content = $this->userFor('content-admin');

        $this->assertTrue(Gate::forUser($content)->allows('reorder', $owned));
        $this->assertFalse(Gate::forUser($this->userFor('registration-officer'))->allows('reorder', $owned));
        $this->assertFalse(Gate::forUser($this->userFor('patient'))->allows('reorder', $owned));
        $this->assertFalse(Gate::forUser($content)->allows('reorder', $unowned));
        $this->assertTrue(Gate::forUser($content)->allows('reorder', $this->mediaFor(Doctor::class, 'photo')));
    }

    public function test_content_admin_cannot_touch_the_site_logo_but_super_admin_can(): void
    {
        $logo = $this->mediaFor(null, 'logo');

        $this->assertFalse(Gate::forUser($this->userFor('content-admin'))->allows('update', $logo));
        $this->assertFalse(Gate::forUser($this->userFor('content-admin'))->allows('delete', $logo));
        $this->assertTrue(Gate::forUser($this->userFor('super-admin'))->allows('update', $logo));
    }

    public function test_registration_officer_cannot_delete_a_doctor_photo(): void
    {
        $photo = $this->mediaFor(Doctor::class, 'photo');

        $this->assertFalse(Gate::forUser($this->userFor('registration-officer'))->allows('delete', $photo));
        $this->assertFalse(Gate::forUser($this->userFor('registration-officer'))->allows('update', $photo));
    }

    public function test_editor_media_without_owner_can_be_managed_by_its_uploader_or_a_content_group_holder(): void
    {
        $officer = $this->userFor('registration-officer');
        $otherOfficer = $this->userFor('registration-officer');
        $content = $this->userFor('content-admin');
        $media = $this->mediaFor(null, 'editor', $officer->id);

        $this->assertTrue(Gate::forUser($officer)->allows('delete', $media), 'Pengunggah sendiri.');
        $this->assertTrue(Gate::forUser($officer)->allows('update', $media));
        $this->assertTrue(Gate::forUser($content)->allows('delete', $media), 'Pemegang content.manage.');
        $this->assertFalse(Gate::forUser($otherOfficer)->allows('delete', $media), 'Staf lain tanpa izin kelompok.');
        $this->assertFalse(Gate::forUser($otherOfficer)->allows('update', $media));
    }

    public function test_a_patient_never_manages_media_even_if_marked_as_uploader(): void
    {
        $patient = $this->userFor('patient');
        $media = $this->mediaFor(null, 'editor', $patient->id);

        $this->assertFalse(Gate::forUser($patient)->allows('delete', $media));
        $this->assertFalse(Gate::forUser($patient)->allows('update', $media));
        $this->assertFalse(Gate::forUser($patient)->allows('upload', Media::class));
    }

    public function test_upload_requires_one_of_the_content_group_permissions(): void
    {
        foreach (['super-admin' => true, 'content-admin' => true, 'registration-officer' => false, 'patient' => false] as $role => $allowed) {
            $this->assertSame($allowed, Gate::forUser($this->userFor($role))->allows('upload', Media::class), $role);
        }
    }

    public function test_policy_is_discovered_automatically(): void
    {
        $this->assertInstanceOf(MediaPolicy::class, Gate::getPolicyFor(Media::class));
    }

    private function mediaFor(?string $type, string $collection, ?int $uploadedBy = null): Media
    {
        return $this->createMediaRow([
            'model_type' => $type,
            'model_id' => $type === null ? null : 1,
            'collection' => $collection,
            'uploaded_by' => $uploadedBy,
        ]);
    }

    private function userFor(string $role): User
    {
        return $role === 'patient'
            ? User::factory()->create()
            : User::query()->findOrFail($this->createStaff($role)->id);
    }
}
```

- [ ] **Step 13: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=MediaPolicyTest
```
Expected: FAIL — `Tests: 8 failed, 6 passed`; mis. `Failed asserting that false is true.` (tanpa Policy, Admin Konten ditolak untuk foto dokter).

- [ ] **Step 14: Tulis Policy (tool Write) `app/Policies/MediaPolicy.php`**

```php
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Media;
use App\Models\Specialty;
use App\Models\User;

/**
 * 04 §5.1 / F2-R16: izin ditentukan pemilik media.
 *
 * - Doctor => `doctors.manage`; Clinic dan Specialty => `clinics.manage`.
 * - `model_type` NULL: koleksi `logo` => `settings.manage`; koleksi `editor` => pengunggah sendiri atau
 *   salah satu dari `content.manage`/`clinics.manage`/`facilities.manage`; koleksi lain ditolak.
 * - `model_type` lain belum dipetakan sehingga ditolak (fase 4 menambah Facility, RoomCategory, dst.).
 *
 * Hanya akun staf. Super Admin lolos lewat Gate::before sebelum sampai ke sini.
 */
final class MediaPolicy
{
    /** Unggah gambar editor: pemegang izin kelompok konten. */
    public function upload(User $user): bool
    {
        return $user->isStaff() && $this->canUseEditor($user);
    }

    public function update(User $user, Media $media): bool
    {
        return $this->manages($user, $media);
    }

    public function delete(User $user, Media $media): bool
    {
        return $this->manages($user, $media);
    }

    /**
     * Urutkan media satu pemilik. Media tanpa pemilik tidak punya urutan sehingga ditolak.
     */
    public function reorder(User $user, Media $anyMediaOfOwner): bool
    {
        return $anyMediaOfOwner->model_type !== null && $this->manages($user, $anyMediaOfOwner);
    }

    private function manages(User $user, Media $media): bool
    {
        if (! $user->isStaff()) {
            return false;
        }

        if ($media->model_type === null) {
            return match ($media->collection) {
                'logo' => $user->can('settings.manage'),
                'editor' => (int) $media->uploaded_by === $user->id || $this->canUseEditor($user),
                default => false,
            };
        }

        return match ($media->model_type) {
            Doctor::class => $user->can('doctors.manage'),
            Clinic::class, Specialty::class => $user->can('clinics.manage'),
            default => false,
        };
    }

    private function canUseEditor(User $user): bool
    {
        return $user->can('content.manage') || $user->can('clinics.manage') || $user->can('facilities.manage');
    }
}
```

- [ ] **Step 15: Jalankan tes — harus lulus, lalu suite penuh**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="MediaPolicyTest|MasterDataPoliciesTest|PersonNameNoHtmlTest"
& $PHP artisan test
```
Expected: PASS — `Tests: 62 passed`; seluruh suite PASS.

- [ ] **Step 16: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint
& $PHP vendor\bin\pint --test
git add app/Policies/MediaPolicy.php tests/Feature/Auth/MediaPolicyTest.php
git commit -m @'
feat(fase-2): MediaPolicy berdasarkan pemilik media

Doctor -> doctors.manage, Clinic/Specialty -> clinics.manage, logo situs -> settings.manage,
gambar editor -> pengunggah atau izin kelompok konten, tipe lain ditolak (F2-R16, Review Focus 5).

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
'@
```


---

### Task 5: Komponen — checkbox larik, data-table (`preserve`, halaman di luar jangkauan), `x-module-tabs`

**Files:**
- Modify: `resources/views/components/form/checkbox.blade.php`, `resources/views/components/data-table.blade.php`, `config/navigation.php`
- Create: `resources/views/components/module-tabs.blade.php`
- Test (baru, agar berkas tes fase 1 tidak disentuh): `tests/Feature/View/FormCheckboxArrayTest.php`, `tests/Feature/View/DataTableNavigationTest.php`, `tests/Feature/View/ModuleTabsComponentTest.php`

**Interfaces:**
- Consumes: `NavigationMenu::items(string $area, ?User $user)` (fase 1, Task 12; `$area` boleh bertitik, mis. `modules.clinics`, karena dibaca lewat `config("navigation.{$area}")`; menyaring `Route::has` dan izin `a|b`, serta menghitung `active` lewat `request()->routeIs(...)`), `x-empty-state`, `x-loading-button` (fase 1), `Tests\Support\InteractsWithFormState` (`withOldInput`), `InteractsWithStaff` (`createStaff()`).
- Produces:
  - `x-form.checkbox` mendukung **nama larik** (nama berakhiran `[]`, mis. `specialty_ids[]`):
    - prop baru `checkedValues` (larik/Collection, default `[]`) dan `showError` (bool, default `true`, hanya berarti untuk nama larik);
    - `id` = `{nama-tanpa-[]}-{nilai}` (mis. `specialty_ids-3`; karakter selain `A-Za-z0-9_` pada nilai diganti `-`), kecuali prop `id` diisi;
    - tercentang bila `(string) $value` ada di input lama (`old('specialty_ids')`) bila ada input lama, selain itu di `checkedValues` atau prop `checked`;
    - galat dibaca dari kunci `specialty_ids`, lalu `specialty_ids.*`; elemen galat berid `{nama}-error` (satu id untuk satu kelompok), jadi `aria-describedby` tiap kotak menunjuk id yang sama; `aria-invalid`/`is-invalid` dipasang pada **semua** kotak, sedangkan teks galat hanya dirender pada kotak dengan `showError` true. **Keputusan K3 (form dokter, Task 11):** setiap kotak diberi `:show-error="false"` dan form merender **satu** elemen galat sendiri di bawah `<fieldset id="specialty_ids" tabindex="-1">` dengan **id `specialty_ids-error`** (id kelompok yang sama yang ditunjuk `aria-describedby` tiap kotak); alternatifnya `:show-error="$loop->first"` tanpa elemen buatan form. Hasil akhirnya: pesan galat spesialisasi tampil tepat satu kali; kotak boleh diberi `:checked-values="$selectedIds"` (lebih disukai) atau `:checked` per kotak (dihormati bila tidak ada input lama);
    - nama non-larik (termasuk `items[0][name]`) berperilaku **persis** seperti fase 1.
  - `x-data-table`: prop `preserve` (larik nama kueri, default `['urut']`) → satu `<input type="hidden">` per nama yang nilainya string tidak kosong di URL, di dalam form pencarian. Halaman yang memakai `<select name="urut">` di slot `filters` **wajib** memberi `:preserve="[]"` agar tidak ada nama ganda. Bila `$rows->isEmpty() && $rows->total() > 0` (halaman di luar jangkauan) tampil `x-empty-state` berjudul "Halaman yang diminta tidak berisi data." beserta tautan "Kembali ke halaman 1" (`$rows->withQueryString()->url(1)`), bukan slot `empty`.
  - `config('navigation.modules.clinics')` = dua tab (Poliklinik → `admin.clinics.index`, Spesialisasi → `admin.specialties.index`) dengan `permission` `clinics.manage` (kunci `permission` **ditambahkan** ke bentuk di kontrak agar penyaringan izin berfungsi; tanpa kunci itu tab tampil untuk siapa saja yang lolos middleware).
  - `<x-module-tabs group="clinics" />`: `<nav aria-label="Tab modul"><ul class="nav nav-tabs mb-3">…`; tab aktif `class="nav-link active"` dengan `aria-current="page"`; tidak merender apa pun bila tidak ada tab yang lolos.

**Teks (sumber):**

| Teks | Sumber |
|---|---|
| "Halaman yang diminta tidak berisi data." | F2-R26 / kontrak Task 5 |
| "Kembali ke halaman 1" | F2-R26 / kontrak Task 5 |
| `aria-label="Tab modul"` | 10 §3.2 (tab modul), teks label **ASUMSI** |

**Test IDs:** carry fase 1 CHECKPOINT §4 (checkbox larik, `data-table` halaman di luar jangkauan + `urut`); prasyarat 05 hal. 34 (tab modul); AKS (tab hanya untuk pemegang `clinics.manage`).

**Catatan teknis (diverifikasi dari source):**
- `MessageBag::first($key)` (Laravel 12.69, `Illuminate\Support\MessageBag::get`) mendukung kunci berbintang: `first('specialty_ids.*')` mengembalikan pesan pertama dari `specialty_ids.0`, `specialty_ids.1`, … Komponen memakainya sebagai cadangan setelah `first('specialty_ids')`.
- Tautan ringkasan galat (`x-form.errors-summary`) untuk kunci `specialty_ids` mengarah ke `#specialty_ids`, dan untuk `specialty_ids.1` ke `#specialty_ids-1` (indeks, bukan nilai). Id kotak berbasis **nilai**, jadi form (Task 11) wajib memberi `id="specialty_ids"` + `tabindex="-1"` pada `<fieldset>` pembungkus daftar centang (pola `x-form.radio-group` fase 1) agar tautan `#specialty_ids` memfokuskan kelompok; tautan per indeks tidak dikejar (galat `.*` ditampilkan terkumpul di kelompok).
- `AbstractPaginator::url($page)` menggabungkan `$this->query` (hasil `withQueryString()`, tanpa kunci `page`) lalu menambahkan `page={n}`, sehingga tautan "Kembali ke halaman 1" membawa `q`, `status`, `urut`, dst. Tanda `&` dirender `&amp;` oleh `{{ }}`.
- Tes tab membuat route uji sendiri (`uji.tab-satu`, `uji.tab-dua`) dan memanggil `Route::getRoutes()->refreshNameLookups()` karena `->name()` dipanggil setelah route masuk koleksi; route nyata `admin.clinics.index`/`admin.specialties.index` baru ada di Task 9–10 sehingga tes tidak boleh bergantung padanya (hanya bentuk konfigurasi yang diuji).
- Repo memakai **LF** (`.gitattributes` `eol=lf`; K5): tool Edit/Write dipakai apa adanya dan setiap `old_string` harus cocok tepat satu kali.

- [ ] **Step 1: Verifikasi prasyarat**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git branch --show-current
git status --short
& $PHP artisan test --filter="FormComponentsTest|FormChoiceComponentsTest|DataTableComponentTest|NavigationMenuTest|NavigationComponentsTest"
```
Expected: `fase-2-master-data`; `git status` bersih (atau hanya perubahan Task 1–4 yang sudah di-commit); seluruh tes PASS (garis dasar hijau, termasuk tes konsistensi id lintas komponen `test_every_control_derives_the_same_id_that_errors_summary_links_to`).

**Siklus A — `x-form.checkbox` larik**

- [ ] **Step 2: Tulis tes yang gagal (tool Write) `tests/Feature/View/FormCheckboxArrayTest.php`**

```php
<?php

namespace Tests\Feature\View;

use Tests\Support\InteractsWithFormState;
use Tests\TestCase;

class FormCheckboxArrayTest extends TestCase
{
    use InteractsWithFormState;

    public function test_array_checkbox_keeps_the_bracket_name_and_derives_a_unique_id_per_value(): void
    {
        $html = (string) $this->blade(
            '<x-form.checkbox name="specialty_ids[]" label="Anak" :value="3" />'
            .'<x-form.checkbox name="specialty_ids[]" label="Bedah" :value="12" />'
        );

        $this->assertStringContainsString('<input type="checkbox" id="specialty_ids-3" name="specialty_ids[]" value="3"', $html);
        $this->assertStringContainsString('<input type="checkbox" id="specialty_ids-12" name="specialty_ids[]" value="12"', $html);
        $this->assertStringContainsString('<label class="form-check-label" for="specialty_ids-3">Anak</label>', $html);
        $this->assertStringContainsString('<label class="form-check-label" for="specialty_ids-12">Bedah</label>', $html);
        $this->assertSame(2, substr_count($html, 'type="checkbox"'));
        $this->assertStringNotContainsString('type="hidden"', $html);
        $this->assertStringNotContainsString(' checked', $html);
    }

    public function test_array_checkbox_honours_an_explicit_id(): void
    {
        $this->blade('<x-form.checkbox name="specialty_ids[]" label="Anak" :value="3" id="spesialisasi-anak" />')
            ->assertSee('id="spesialisasi-anak"', false)
            ->assertSee('for="spesialisasi-anak"', false)
            ->assertDontSee('id="specialty_ids-3"', false);
    }

    public function test_checked_values_prop_checks_matching_values_with_loose_types(): void
    {
        $html = (string) $this->blade(
            '<x-form.checkbox name="specialty_ids[]" label="Anak" :value="3" :checked-values="$checked" />'
            .'<x-form.checkbox name="specialty_ids[]" label="Bedah" :value="4" :checked-values="$checked" />'
            .'<x-form.checkbox name="specialty_ids[]" label="Gigi" value="5" :checked-values="$checked" />',
            ['checked' => collect([3, '5'])]
        );

        $this->assertStringContainsString('value="3" checked', $html);
        $this->assertStringContainsString('value="5" checked', $html);
        $this->assertStringNotContainsString('value="4" checked', $html);
    }

    public function test_old_input_overrides_checked_values(): void
    {
        $this->withOldInput(['specialty_ids' => ['5']]);

        $html = (string) $this->blade(
            '<x-form.checkbox name="specialty_ids[]" label="Anak" :value="3" :checked-values="[3]" />'
            .'<x-form.checkbox name="specialty_ids[]" label="Gigi" :value="5" :checked-values="[3]" />'
        );

        $this->assertStringContainsString('value="5" checked', $html);
        $this->assertStringNotContainsString('value="3" checked', $html);
    }

    public function test_old_input_without_the_key_means_nothing_is_checked(): void
    {
        $this->withOldInput(['name' => 'Budi']);

        $this->blade('<x-form.checkbox name="specialty_ids[]" label="Anak" :value="3" :checked-values="[3]" checked />')
            ->assertDontSee('checked', false);
    }

    public function test_group_error_is_shared_by_all_boxes_and_rendered_once_with_show_error_false(): void
    {
        $this->withViewErrors(['specialty_ids' => ['Pilih minimal satu spesialisasi.']]);

        $html = (string) $this->blade(
            '<x-form.checkbox name="specialty_ids[]" label="Anak" :value="3" />'
            .'<x-form.checkbox name="specialty_ids[]" label="Bedah" :value="12" :show-error="false" />'
        );

        $this->assertSame(2, substr_count($html, 'form-check-input is-invalid'));
        $this->assertSame(2, substr_count($html, 'aria-invalid="true"'));
        $this->assertSame(2, substr_count($html, 'aria-describedby="specialty_ids-error"'));
        $this->assertSame(1, substr_count($html, '<div id="specialty_ids-error" class="invalid-feedback d-block">Pilih minimal satu spesialisasi.</div>'));
    }

    public function test_group_error_is_not_rendered_by_any_box_when_all_use_show_error_false(): void
    {
        // Pola form dokter (K3): form merender sendiri satu elemen galat dengan id `specialty_ids-error`.
        $this->withViewErrors(['specialty_ids' => ['Pilih minimal satu spesialisasi.']]);

        $html = (string) $this->blade(
            '<x-form.checkbox name="specialty_ids[]" label="Anak" :value="3" :show-error="false" />'
            .'<x-form.checkbox name="specialty_ids[]" label="Bedah" :value="12" :show-error="false" />'
        );

        $this->assertStringNotContainsString('Pilih minimal satu spesialisasi.', $html);
        $this->assertSame(2, substr_count($html, 'aria-describedby="specialty_ids-error"'));
        $this->assertSame(2, substr_count($html, 'aria-invalid="true"'));
    }

    public function test_wildcard_error_key_is_read_for_array_names(): void
    {
        $this->withViewErrors(['specialty_ids.1' => ['Spesialisasi tidak boleh dipilih dua kali.']]);

        $this->blade('<x-form.checkbox name="specialty_ids[]" label="Anak" :value="3" />')
            ->assertSee('<div id="specialty_ids-error" class="invalid-feedback d-block">Spesialisasi tidak boleh dipilih dua kali.</div>', false)
            ->assertSee('aria-invalid="true"', false);
    }

    public function test_array_checkbox_escapes_value_and_sanitises_it_inside_the_id(): void
    {
        $html = (string) $this->blade('<x-form.checkbox name="specialty_ids[]" label="X" :value="$value" />', ['value' => '"><x>']);

        $this->assertStringContainsString('id="specialty_ids-x"', $html);
        $this->assertStringContainsString('value="&quot;&gt;&lt;x&gt;"', $html);
        $this->assertStringNotContainsString('<x>', $html);
    }

    public function test_single_checkbox_is_unchanged_by_the_array_support(): void
    {
        $html = (string) $this->blade('<x-form.checkbox name="is_active" label="Aktif" :checked-values="[1]" :show-error="false" />');

        $this->assertStringContainsString('id="is_active"', $html);
        $this->assertStringContainsString('value="1"', $html);
        $this->assertStringNotContainsString('checked', $html);

        $this->withViewErrors(['is_active' => ['Status aktif tidak valid.']]);

        $this->blade('<x-form.checkbox name="is_active" label="Aktif" :show-error="false" />')
            ->assertSee('<div id="is_active-error" class="invalid-feedback d-block">Status aktif tidak valid.</div>', false);
    }

    public function test_bracketed_non_array_names_keep_the_phase_one_id(): void
    {
        $this->withViewErrors(['items.0.active' => ['Wajib.']]);

        $this->blade('<x-form.checkbox name="items[0][active]" label="Aktif" />')
            ->assertSee('id="items-0-active"', false)
            ->assertSee('<div id="items-0-active-error" class="invalid-feedback d-block">Wajib.</div>', false);
    }
}
```

- [ ] **Step 3: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=FormCheckboxArrayTest
```
Expected: FAIL. Komponen lama membentuk id `specialty_ids` (bukan `specialty_ids-3`), tidak mengenal `checked-values`, dan tidak membaca kunci `specialty_ids.*`; tes yang memberi prop larik (`:checked-values`/`:show-error`) berstatus ERROR, bukan PASS (komponen lama menerima larik itu lewat `$attributes` sehingga `trim(): Argument #1 ($string) must be of type string, array given`). `test_single_checkbox_is_unchanged…` dan `test_bracketed_non_array_names…` PASS (itu penjaga regresi).

- [ ] **Step 4: Implementasi — ganti seluruh `resources/views/components/form/checkbox.blade.php` (tool Write)**

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
    'checkedValues' => [],
    'showError' => true,
])
@php
    // Nama larik ("specialty_ids[]"): satu kelompok kotak dengan id unik per nilai (F2-R26).
    $isArray = str_ends_with($name, '[]');
    $field = $isArray ? substr($name, 0, -2) : $name;
    $groupId = trim(str_replace(['.', '[', ']'], ['-', '-', ''], $field), '-');
    $key = trim(str_replace(['[', ']'], ['.', ''], $field), '.');
    $id = $id ?: ($isArray ? $groupId.'-'.trim((string) preg_replace('/[^A-Za-z0-9_]+/', '-', (string) $value), '-') : $groupId);

    $error = '';
    if (isset($errors)) {
        $error = (string) $errors->first($key);
        if ($error === '' && $isArray) {
            $error = (string) $errors->first($key.'.*');
        }
    }
    $errorId = $isArray ? $groupId.'-error' : $id.'-error';
    $renderError = $error !== '' && (! $isArray || $showError);

    $hasOldInput = request()->hasSession() && request()->session()->hasOldInput();
    if ($isArray) {
        $selected = $hasOldInput ? old($key, []) : $checkedValues;
        $selected = collect($selected)->filter('is_scalar')->map(fn ($item) => (string) $item)->all();
        $isChecked = in_array((string) $value, $selected, true) || (! $hasOldInput && (bool) $checked);
    } else {
        $isChecked = $hasOldInput
            ? (string) old($key, $withHidden ? '0' : null) === (string) $value
            : (bool) $checked;
    }
    $describedBy = trim(($help ? $id.'-help' : '').' '.($error !== '' && ($renderError || $isArray) ? $errorId : ''));
@endphp
<div class="mb-3">
    @if ($withHidden && ! $isArray)<input type="hidden" name="{{ $name }}" value="0">@endif
    <div class="form-check">
        <input type="checkbox" id="{{ $id }}" name="{{ $name }}" value="{{ $value }}" @checked($isChecked) @if ($required) aria-required="true"@endif @if ($error !== '') aria-invalid="true"@endif @if ($describedBy !== '') aria-describedby="{{ $describedBy }}"@endif {{ $attributes->class(['form-check-input', 'is-invalid' => $error !== '']) }}>
        <label class="form-check-label" for="{{ $id }}">{{ $label }}@if ($required) <span class="required-mark" aria-hidden="true">*</span>@endif</label>
    </div>
    @if ($help)<div id="{{ $id }}-help" class="form-text">{{ $help }}</div>@endif
    @if ($renderError)<div id="{{ $errorId }}" class="invalid-feedback d-block">{{ $error }}</div>@endif
</div>
```

- [ ] **Step 5: Jalankan tes — harus lulus (termasuk seluruh tes komponen lama)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="FormCheckboxArrayTest|FormChoiceComponentsTest|FormComponentsTest|FormFileComponentTest"
```
Expected: seluruhnya PASS, termasuk `test_every_control_derives_the_same_id_that_errors_summary_links_to` dengan dataset `checkbox` (`items[0][name]` → `id="items-0-name"`, galat `items-0-name-error`) dan semua tes checkbox tunggal fase 1 (markup `<label class="form-check-label" for="is_active">Aktif</label>`, `value="1" checked`, dst.). Bila ada yang gagal: perbaiki komponen, **jangan** mengubah tes lama.

- [ ] **Step 6: Pint dan commit siklus A**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint --test
git add resources/views/components/form/checkbox.blade.php tests/Feature/View/FormCheckboxArrayTest.php
git commit -m "feat(fase-2): x-form.checkbox mendukung nama larik (specialty_ids[])" -m "Id unik per nilai, checkedValues, galat kelompok dari kunci larik dan wildcard; checkbox tunggal tidak berubah." -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```
Expected: Pint `PASS` (bila melapor berkas, jalankan `& $PHP vendor\bin\pint <berkas>` lalu ulangi `--test`); commit sukses.

**Siklus B — `x-data-table`: `preserve` dan halaman di luar jangkauan**

- [ ] **Step 7: Tulis tes yang gagal (tool Write) `tests/Feature/View/DataTableNavigationTest.php`**

```php
<?php

namespace Tests\Feature\View;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class DataTableNavigationTest extends TestCase
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

    private function table(string $attributes = ''): string
    {
        return <<<BLADE
            <x-data-table :rows="\$rows" caption="Daftar dokter" empty-title="Belum ada dokter" empty-message="Tambahkan dokter pertama." {$attributes}>
                <x-slot:head><th scope="col">Nama</th></x-slot:head>
                @foreach (\$rows as \$row)
                    <tr><td data-label="Nama">{{ \$row['name'] }}</td></tr>
                @endforeach
            </x-data-table>
            BLADE;
    }

    public function test_sort_parameter_is_preserved_as_a_hidden_input_by_default(): void
    {
        $this->visit('/admin/dokter?urut=nama&q=andi&per_page=25');

        $html = (string) $this->blade($this->table(), ['rows' => $this->paginator(32)]);

        $this->assertSame(1, substr_count($html, '<input type="hidden" name="urut" value="nama">'));
        $this->assertSame(1, substr_count($html, '<input type="hidden" name="per_page" value="25">'));
    }

    public function test_no_hidden_input_is_rendered_when_the_parameter_is_absent_or_not_a_string(): void
    {
        $this->visit('/admin/dokter');

        $this->blade($this->table(), ['rows' => $this->paginator(32)])
            ->assertDontSee('name="urut"', false);

        $this->visit('/admin/dokter?urut[]=nama&status=');

        $this->blade($this->table(':preserve="[\'urut\', \'status\']"'), ['rows' => $this->paginator(32)])
            ->assertDontSee('name="urut"', false)
            ->assertDontSee('name="status"', false);
    }

    public function test_preserve_prop_accepts_custom_names_and_empty_list(): void
    {
        $this->visit('/admin/dokter?urut=terbaru&status=archived&poli=anak');

        $this->blade($this->table(':preserve="[\'urut\', \'status\']"'), ['rows' => $this->paginator(32)])
            ->assertSee('<input type="hidden" name="urut" value="terbaru">', false)
            ->assertSee('<input type="hidden" name="status" value="archived">', false)
            ->assertDontSee('name="poli"', false);

        $this->blade($this->table(':preserve="[]"'), ['rows' => $this->paginator(32)])
            ->assertDontSee('type="hidden"', false);
    }

    public function test_preserved_values_are_escaped(): void
    {
        $this->visit('/admin/dokter?'.http_build_query(['urut' => '"><script>alert(1)</script>']));

        $this->blade($this->table(), ['rows' => $this->paginator(32)])
            ->assertSee('value="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_page_beyond_the_last_shows_message_and_link_to_page_one(): void
    {
        $this->visit('/admin/dokter?q=andi&urut=nama&page=9');

        $this->blade($this->table(), ['rows' => $this->paginator(32, 10, 9)])
            ->assertSee('Halaman yang diminta tidak berisi data.')
            ->assertSee('Kembali ke halaman 1')
            ->assertSee('href="'.url('/admin/dokter').'?q=andi&amp;urut=nama&amp;page=1"', false)
            ->assertDontSee('<table', false)
            ->assertDontSee('Belum ada dokter')
            ->assertDontSee('Tidak ada data yang cocok');
    }

    public function test_truly_empty_result_keeps_the_regular_empty_state(): void
    {
        $this->visit('/admin/dokter');

        $this->blade($this->table(), ['rows' => $this->paginator(0)])
            ->assertSee('Belum ada dokter')
            ->assertSee('Tambahkan dokter pertama.')
            ->assertDontSee('Halaman yang diminta tidak berisi data.')
            ->assertDontSee('Kembali ke halaman 1');
    }

    public function test_page_within_range_shows_rows_without_the_out_of_range_message(): void
    {
        $this->visit('/admin/dokter?page=2');

        $this->blade($this->table(), ['rows' => $this->paginator(32, 10, 2)])
            ->assertSee('Dokter 11')
            ->assertDontSee('Halaman yang diminta tidak berisi data.');
    }
}
```

- [ ] **Step 8: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=DataTableNavigationTest
```
Expected: FAIL pada `test_sort_parameter_is_preserved…` (hidden `urut` tidak ada), `test_preserve_prop_accepts…`, `test_preserved_values_are_escaped`, dan `test_page_beyond_the_last…` (pesan belum ada; komponen lama menampilkan "Tidak ada data yang cocok…"/slot kosong). Tes yang memberi prop `:preserve` berupa larik berstatus ERROR pada komponen lama (`trim()` menerima larik), bukan PASS. Tes `no_hidden_input…`, `truly_empty…`, `within_range…` PASS (penjaga regresi).

- [ ] **Step 9: Implementasi — ganti seluruh `resources/views/components/data-table.blade.php` (tool Write)**

```blade
@props([
    'rows',
    'searchName' => 'q',
    'searchPlaceholder' => 'Cari…',
    'caption' => 'Daftar data',
    'emptyTitle' => 'Belum ada data',
    'emptyMessage' => null,
    'searchable' => true,
    'preserve' => ['urut'],
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
    // Parameter kueri yang ikut terkirim ulang oleh form pencarian (mis. urutan), bukan hasil input form.
    $preserved = collect($preserve)
        ->mapWithKeys(fn ($field) => [(string) $field => request()->query((string) $field)])
        ->filter(fn ($value) => is_string($value) && $value !== '')
        ->all();
    $outOfRange = $rows->isEmpty() && method_exists($rows, 'total') && $rows->total() > 0;
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
                @foreach ($preserved as $field => $fieldValue)<input type="hidden" name="{{ $field }}" value="{{ $fieldValue }}">@endforeach
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
        @if ($outOfRange)
            <x-empty-state title="Halaman yang diminta tidak berisi data." message="Data pada halaman ini mungkin sudah berubah." icon="bi-exclamation-circle">
                <a href="{{ $rows->withQueryString()->url(1) }}" class="btn btn-outline-primary">Kembali ke halaman 1</a>
            </x-empty-state>
        @else
            @isset($empty)
                {{ $empty }}
            @else
                <x-empty-state :title="$emptyTitle" :message="$hasQuery ? 'Tidak ada data yang cocok dengan pencarian atau filter Anda.' : $emptyMessage" :icon="$hasQuery ? 'bi-search' : 'bi-inbox'">
                    @if ($hasQuery)<a href="{{ $currentUrl }}" class="btn btn-outline-primary">Hapus filter</a>@endif
                </x-empty-state>
            @endisset
        @endif
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

- [ ] **Step 10: Jalankan tes — harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="DataTableNavigationTest|DataTableComponentTest|ConfirmModalComponentTest"
```
Expected: seluruhnya PASS (tes `DataTableComponentTest` fase 1 tidak berubah). Catatan: bila `DataTableComponentTest` memiliki kasus dengan `urut` di URL yang kini menambah hidden input, sesuaikan **komponen**, bukan tes lama (tidak ada kasus seperti itu saat rencana ini ditulis).

- [ ] **Step 11: Pint dan commit siklus B**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint --test
git add resources/views/components/data-table.blade.php tests/Feature/View/DataTableNavigationTest.php
git commit -m "feat(fase-2): x-data-table meneruskan urutan dan menangani halaman di luar jangkauan" -m "Prop preserve (default urut) menjadi hidden input form pencarian; halaman kosong dengan total > 0 menampilkan pesan dan tautan ke halaman 1." -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```
Expected: Pint `PASS`; commit sukses.

**Siklus C — konfigurasi `modules` dan `x-module-tabs`**

- [ ] **Step 12: Tulis tes yang gagal (tool Write) `tests/Feature/View/ModuleTabsComponentTest.php`**

```php
<?php

namespace Tests\Feature\View;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

class ModuleTabsComponentTest extends TestCase
{
    use InteractsWithStaff;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/uji/tab-satu', fn () => Blade::render('<x-module-tabs group="uji" />'))->name('uji.tab-satu');
        Route::get('/uji/tab-dua', fn () => Blade::render('<x-module-tabs group="uji" />'))->name('uji.tab-dua');
        Route::get('/uji/kosong', fn () => Blade::render('<x-module-tabs group="tidak-ada" />'))->name('uji.kosong');
        Route::getRoutes()->refreshNameLookups();

        config(['navigation.modules.uji' => [
            ['label' => 'Satu', 'route' => 'uji.tab-satu', 'permission' => 'clinics.manage', 'active' => ['uji.tab-satu']],
            ['label' => 'Dua', 'route' => 'uji.tab-dua', 'active' => ['uji.tab-dua']],
            ['label' => 'Hantu', 'route' => 'uji.tidak-pernah-ada', 'active' => ['uji.tidak-pernah-ada']],
        ]]);
    }

    public function test_renders_tabs_and_marks_only_the_current_one(): void
    {
        $response = $this->actingAs($this->createStaff('content-admin'))->get('/uji/tab-satu');

        $response->assertOk()
            ->assertSee('<nav aria-label="Tab modul">', false)
            ->assertSee('<ul class="nav nav-tabs mb-3">', false)
            ->assertSee('<a class="nav-link active" href="'.route('uji.tab-satu').'" aria-current="page">Satu</a>', false)
            ->assertSee('<a class="nav-link" href="'.route('uji.tab-dua').'">Dua</a>', false);

        $this->assertSame(1, substr_count($response->getContent(), 'aria-current="page"'));
    }

    public function test_second_tab_becomes_current_on_its_own_page(): void
    {
        $this->actingAs($this->createStaff('content-admin'))->get('/uji/tab-dua')
            ->assertSee('<a class="nav-link active" href="'.route('uji.tab-dua').'" aria-current="page">Dua</a>', false)
            ->assertSee('<a class="nav-link" href="'.route('uji.tab-satu').'">Satu</a>', false);
    }

    public function test_tabs_to_missing_routes_are_dropped(): void
    {
        $this->actingAs($this->createStaff('content-admin'))->get('/uji/tab-satu')
            ->assertDontSee('Hantu');
    }

    public function test_tabs_are_filtered_by_permission(): void
    {
        // Petugas Pendaftaran tidak memegang clinics.manage: hanya tab tanpa syarat izin yang tampil.
        $this->actingAs($this->createStaff('registration-officer'))->get('/uji/tab-dua')
            ->assertSee('>Dua</a>', false)
            ->assertDontSee('>Satu</a>', false);
    }

    public function test_guest_sees_only_tabs_without_a_permission_requirement(): void
    {
        $this->get('/uji/tab-dua')
            ->assertOk()
            ->assertSee('>Dua</a>', false)
            ->assertDontSee('>Satu</a>', false);
    }

    public function test_renders_nothing_when_no_tab_survives_or_the_group_is_unknown(): void
    {
        $this->actingAs($this->createStaff('content-admin'))->get('/uji/kosong')
            ->assertOk()
            ->assertDontSee('<nav', false);

        config(['navigation.modules.uji' => [
            ['label' => 'Hantu', 'route' => 'uji.tidak-pernah-ada'],
        ]]);

        $this->actingAs($this->createStaff('content-admin'))->get('/uji/tab-satu')
            ->assertOk()
            ->assertDontSee('<nav', false);
    }

    public function test_clinics_group_in_the_navigation_config_has_the_documented_shape(): void
    {
        $this->assertSame([
            ['label' => 'Poliklinik', 'route' => 'admin.clinics.index', 'permission' => 'clinics.manage', 'active' => ['admin.clinics.*']],
            ['label' => 'Spesialisasi', 'route' => 'admin.specialties.index', 'permission' => 'clinics.manage', 'active' => ['admin.specialties.*']],
        ], config('navigation.modules.clinics'));
    }
}
```

- [ ] **Step 13: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=ModuleTabsComponentTest
```
Expected: FAIL — komponen `module-tabs` belum ada (`Unable to locate a class or view for component [module-tabs]`) dan `config('navigation.modules.clinics')` bernilai `null`.

- [ ] **Step 14: Implementasi — tambah kunci `modules` di `config/navigation.php` (tool Edit)**

`old_string` (akhir berkas, tepat satu kecocokan):

```php
            ['label' => 'Audit Log', 'route' => 'admin.audit-logs.index', 'icon' => 'bi-clipboard-data', 'permission' => 'audit.view', 'active' => ['admin.audit-logs.*']],
        ]],
    ],

];
```

`new_string`:

```php
            ['label' => 'Audit Log', 'route' => 'admin.audit-logs.index', 'icon' => 'bi-clipboard-data', 'permission' => 'audit.view', 'active' => ['admin.audit-logs.*']],
        ]],
    ],

    // Tab modul (10 §3.2, F2-R11): dibaca <x-module-tabs group="...">. Disaring Route::has dan izin oleh
    // NavigationMenu::items('modules.{group}'); penegakan akses tetap di middleware/Policy.
    'modules' => [
        'clinics' => [
            ['label' => 'Poliklinik', 'route' => 'admin.clinics.index', 'permission' => 'clinics.manage', 'active' => ['admin.clinics.*']],
            ['label' => 'Spesialisasi', 'route' => 'admin.specialties.index', 'permission' => 'clinics.manage', 'active' => ['admin.specialties.*']],
        ],
    ],

];
```

- [ ] **Step 15: Implementasi — buat `resources/views/components/module-tabs.blade.php` (tool Write)**

```blade
@props(['group'])
@php
    // Pola yang sama dengan sidebar: tab ke route yang belum ada dan tab tanpa izin dibuang (F1-R2).
    $tabs = \App\Support\NavigationMenu::items('modules.'.$group, auth()->user());
@endphp
@if (count($tabs) > 0)
    <nav aria-label="Tab modul">
        <ul class="nav nav-tabs mb-3">
            @foreach ($tabs as $tab)
                <li class="nav-item">
                    <a @class(['nav-link', 'active' => $tab['active']]) href="{{ $tab['url'] }}"@if ($tab['active']) aria-current="page"@endif>{{ $tab['label'] }}</a>
                </li>
            @endforeach
        </ul>
    </nav>
@endif
```

- [ ] **Step 16: Jalankan tes — harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="ModuleTabsComponentTest|NavigationMenuTest|AdminLayoutTest|NavigationComponentsTest"
```
Expected: seluruhnya PASS. `NavigationMenuTest` fase 1 hanya membaca `navigation.admin/public/footer`, jadi kunci `modules` tidak memengaruhinya.

- [ ] **Step 17: Seluruh suite, Pint, dan commit siklus C**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test
& $PHP vendor\bin\pint --test
git add config/navigation.php resources/views/components/module-tabs.blade.php tests/Feature/View/ModuleTabsComponentTest.php
git commit -m "feat(fase-2): x-module-tabs dan konfigurasi navigation.modules.clinics" -m "Tab disaring Route::has dan izin lewat NavigationMenu (pola sidebar); tab aktif memakai aria-current." -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```
Expected: `artisan test` hijau seluruhnya (tidak ada tes fase 1 yang berubah); Pint `PASS`; commit sukses. Tidak ada perubahan `resources/js`/`resources/scss` sehingga `npm run build` tidak wajib di task ini.

---

### Task 6: Endpoint media admin (F-ADM-29): `MediaController`, tiga Form Request V-42, empat route `admin.media.*`

**Files:**
- Create: `app/Http/Controllers/Admin/MediaController.php`, `app/Http/Requests/Admin/EditorImageUploadRequest.php`, `app/Http/Requests/Admin/UpdateMediaRequest.php`, `app/Http/Requests/Admin/ReorderMediaRequest.php`
- Modify: `app/Http/Controllers/Controller.php` (tambah trait `AuthorizesRequests`), `routes/admin.php`, `bootstrap/app.php` (callback `shouldRenderJsonWhen`: galat validasi pada `admin.media.*` tidak lagi dipaksa JSON)
- Test: `tests/Feature/Admin/AdminMediaEndpointsTest.php`

**Interfaces:**
- Consumes:
  - `MediaService::store(UploadedFile $file, string $collection, ?Model $owner = null, array $meta = [], ?User $uploader = null): Media` — untuk `editor` pemilik `null`; `updateMeta(Media, ?string $altText, ?string $caption): Media`; `delete(Media): void` (audit `media.deleted`, berkas dihapus setelah commit); `reorder(Model $owner, string $collection, array $orderedIds): void` (melempar `ValidationException` kunci `ids` "Urutan gambar tidak valid." bila tidak seluruhnya milik pemilik dan koleksi yang sama).
  - `ImageRules::for('editor')` / `ImageRules::messages('file', 'editor')` (fase 1, Task 8).
  - `SettingsService::int('site.logo_media_id')` (fase 1).
  - `SafeRedirect::back(Request)` (fase 1; mengembalikan `url()->previous()`).
  - `MediaPolicy` (Task 4, F2-R16): `upload(User)`, `update(User, Media)`, `delete(User, Media)`, `reorder(User, Media $anyMediaOfOwner)`; `Gate::before` melewatkan Super Admin.
  - `Tests\Support\InteractsWithMasterData` (`createDoctor()`, `createClinic()`; Task 1), `InteractsWithStaff` (`seedRoles()`, `createStaff()`), `ImageFixtures`, `Storage::fake('public')`.
  - `lang/id/messages.php` (Task 3): kunci `messages.updated` dan `messages.deleted` (":entity berhasil diperbarui." / ":entity berhasil dihapus."); controller mengisi `entity` dengan literal "Gambar" sehingga tidak bergantung pada teks `messages.entities.media`.
- Produces:
  - Route (di grup G-ADMIN `routes/admin.php`; `editor` dan `urutkan` statis **sebelum** `{media}`; string izin dengan `|` literal):

    | Method | URI | Nama | Middleware izin |
    |---|---|---|---|
    | POST | `/admin/media/editor` | `admin.media.editor-upload` | `permission:content.manage\|clinics.manage\|facilities.manage` |
    | POST | `/admin/media/urutkan` | `admin.media.reorder` | `permission:doctors.manage\|clinics.manage\|facilities.manage\|content.manage` |
    | PATCH | `/admin/media/{media}` | `admin.media.update` | `permission:doctors.manage\|clinics.manage\|facilities.manage\|content.manage\|settings.manage` |
    | DELETE | `/admin/media/{media}` | `admin.media.destroy` | sama dengan `update` |

    (Tanda `\|` pada tabel hanya escape markdown; di kode ditulis `|`.)
  - `EditorImageUploadRequest` (field `file` + `alt_text`, F2-R15): `authorize()` = `can('upload', Media::class)`. `MediaController::editorUpload` → **201** `{"url": "/storage/editor/YYYY/MM/uuid.jpg", "alt": "…", "media_id": 12}`. `url` berupa **path** `/storage/…` (bukan URL absolut `APP_URL`): `<img src>` di editor tetap dimuat dengan CSP `img-src 'self'` walau host dev berbeda dari `APP_URL`, dan `HtmlSanitizer` hanya menerima path `/storage/…` (atau URL host aplikasi yang dinormalkan menjadi path).
  - `UpdateMediaRequest` (`alt_text` wajib ≤255, `caption` opsional ≤255): `authorize()` = `can('update', $media)`. JSON 200 `{"media_id", "alt", "caption", "message"}`; non-JSON: `redirect()` ke `SafeRedirect::back` dengan flash `success`. `caption` yang tidak dikirim **tidak** mengosongkan keterangan lama.
  - `MediaController::destroy` (tanpa Form Request): `$this->authorize('delete', $media)`; media yang dirujuk `site.logo_media_id` ditolak (F2-R17) → JSON **422** `{"message": "Gambar tidak dapat dihapus karena sedang dipakai.", "errors": {"media": ["…"]}}` atau flash `error`; selain itu `MediaService::delete()` → JSON 200 `{"message": "Gambar berhasil dihapus."}` / flash `success`. Penolakan ini berlaku juga untuk Super Admin (dijaga di controller, bukan Policy).
  - `ReorderMediaRequest` (`ids` 1–100, `ids.*` integer, distinct, ada di `media`): `authorize()` memeriksa `can('reorder', $media)` untuk **setiap** id yang ada (id yang tidak ada dibiarkan ke validasi 422). Setelah validasi dasar (`after()`), semua media harus milik **pemilik dan koleksi yang sama** dan **berpemilik** (media `editor`/`logo` tanpa pemilik tidak dapat diurutkan); pemilik dan koleksi **diturunkan dari id media**, tidak diterima dari input. Method pembantu `owner(): Model`, `collection(): string`, `orderedIds(): list<int>` (hanya valid setelah validasi lolos). JSON 200 `{"message": "Urutan gambar berhasil disimpan."}`.
  - Kontrak galat: validasi gagal pada permintaan JSON → **422** `{"message", "errors"}`; pada permintaan non-JSON → redirect kembali dengan galat sesi (perilaku bawaan Laravel). Karena callback fase 1 memaksa JSON untuk seluruh `admin.media.*`, Step 7a mengubah `shouldRenderJsonWhen` di `bootstrap/app.php`: JSON bila `expectsJson()`, atau bila route `admin.media.*` dan galatnya BUKAN `ValidationException` (galat lain pada route media tetap JSON). Tamu JSON → 401; tanpa izin JSON → 403 `{"message": "Anda tidak memiliki izin untuk melakukan tindakan ini."}`.
  - `Controller` dasar kini memakai trait `Illuminate\Foundation\Auth\Access\AuthorizesRequests` (11 §1 aturan 5), sehingga controller admin berikutnya (Task 9–12) dapat memanggil `$this->authorize()`.

**Pesan galat (sumber):**

| Kunci | Pesan | Sumber |
|---|---|---|
| `file` (kosong) | "File gambar wajib dipilih." | 12 V-42 |
| `file` (tipe/ukuran/dimensi/rusak) | pesan baku `ImageRules::messages('file', 'editor')` ("File harus berupa gambar.", "File harus berupa gambar JPG, PNG, atau WebP.", "Ukuran gambar maksimal 2 MB.", "Dimensi gambar minimal 200 x 100 piksel dan maksimal 6000 x 6000 piksel.", "File tidak dapat diproses sebagai gambar yang valid.", "Gambar gagal diunggah. Ukuran file melebihi batas server.") | 12 §3.2, V-42 |
| `alt_text` | "Teks alternatif gambar wajib diisi." / "Teks alternatif gambar maksimal 255 karakter." / "Teks alternatif gambar harus berupa teks." | 12 V-42; pesan `string` mengikuti pesan baku 12 §4 |
| `caption` | "Keterangan gambar maksimal 255 karakter." / "Keterangan gambar harus berupa teks." | 12 V-42; pesan `string` baku |
| `ids` | "Daftar gambar wajib diisi." / "Maksimal 100 gambar dapat diurutkan sekaligus." / "Urutan gambar tidak valid." | 12 V-42 |
| `ids.*` | "Urutan gambar tidak valid." (integer, distinct) / "Gambar tidak ditemukan." (exists) | 12 V-42 |
| `media` (hapus) | "Gambar tidak dapat dihapus karena sedang dipakai." | 12 V-42, F2-R17 |
| flash `success` | "Gambar berhasil diperbarui." / "Gambar berhasil dihapus." / "Urutan gambar berhasil disimpan." | 11 §11.1 (pola `messages.*`); teks urutan **ASUMSI** |

**Test IDs:** MED (endpoint editor/ubah/hapus/urut), AKS (media menurut pemilik), SEK-09 (SVG ditolak di editor), AUD (`media.uploaded`/`media.deleted`), KNS-02; **Review Focus 5** (Admin Konten mengubah alt logo situs → 403; Petugas menghapus foto dokter → 403; Super Admin menghapus media logo yang dipakai → 422).

**Catatan teknis (diverifikasi dari source):**
- `FormRequest::getValidatorInstance()` (Laravel 12.69) memanggil `withValidator()` dan **`after()`** (dipanggil lewat container; harus mengembalikan larik callable `fn (Validator $v)`), dan `authorize()` dijalankan **sebelum** validasi, jadi pengguna tanpa hak mendapat 403 walau inputnya salah. Implicit binding `{media}` sudah terselesaikan oleh `SubstituteBindings` sebelum Form Request dibuat, sehingga `$this->route('media')` berisi model `Media`.
- `Handler::convertValidationExceptionToResponse` memilih JSON lewat `shouldReturnJson($request, $e)`, yaitu callback `shouldRenderJsonWhen` (bukan hanya `expectsJson()`). Callback fase 1 menyertakan `routeIs('admin.media.*')` tanpa pengecualian, sehingga PATCH form HTML biasa yang tidak valid akan menerima 422 JSON. Step 7a mengecualikan `ValidationException`; dengan itu form HTML biasa (Task 10) mendapat redirect + `$errors`, sedangkan tes JSON tetap mengirim header `Accept: application/json` (helper `postJson/patchJson/deleteJson`) dan menerima 422 JSON.
- Spatie `PermissionMiddleware::handle` memecah parameter dengan `explode('|', …)` (v6.25), jadi string `permission:a|b|c` berarti "salah satu"; Laravel memecah parameter middleware pada koma, bukan pipa, sehingga string aman.
- `Media::model()` adalah `morphTo`: `ReorderMediaRequest` memakai `Media::query()->with('model')` (eager) agar tidak memicu `LazyLoadingViolationException` di mode ketat. Baris dengan `model_type` yang kelasnya tidak ada akan melempar galat kelas; baris seperti itu hanya dapat muncul dari data rusak, bukan dari `MediaService`.
- `Storage::disk('public')->url()` di tes memakai `APP_URL` (`http://unpam-hospital.test`); karena itu respons editor memakai `parse_url(..., PHP_URL_PATH)`. Penghapusan berkas terjadi lewat `DB::afterCommit` di `MediaService::delete()`; tes memeriksa `assertMissing` setelah respons, dan pola yang sama dipakai tes fase 1 (`MediaServiceLifecycleTest`).
- Tes memakai `Tests\Support\InteractsWithMasterData` dan `InteractsWithStaff` bersamaan. Bila trait milik Task 1 ternyata sudah `use InteractsWithStaff` dan PHP melaporkan tabrakan metode, hapus `use InteractsWithStaff;` dari kelas tes ini (metode `seedRoles()/createStaff()` tetap tersedia lewat trait Task 1).
- Pengguna tes dengan izin tertentu dibuat lewat `User::factory()->staff()->create()` + `givePermissionTo([...])` setelah `seedRoles()` (izin baku sudah ada di tabel `permissions`); ini memungkinkan menguji pemetaan per pemilik tanpa bergantung pada kombinasi izin peran bawaan.

- [ ] **Step 1: Verifikasi prasyarat (cabang, Task 1–5)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git branch --show-current
git status --short
Test-Path app\Policies\MediaPolicy.php
Test-Path tests\Support\InteractsWithMasterData.php
Test-Path lang\id\messages.php
& $PHP artisan test --filter="AdminRouteMiddlewareTest|ImageRulesTest|MediaServiceLifecycleTest"
```
Expected: `fase-2-master-data`; `git status` bersih; tiga `True`; seluruh tes PASS.

**Siklus A — rute, upload editor, dan otorisasi**

- [ ] **Step 2: Tulis tes yang gagal (tool Write) `tests/Feature/Admin/AdminMediaEndpointsTest.php`**

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\Media;
use App\Models\User;
use App\Services\Media\MediaService;
use App\Services\Settings\SettingsService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ImageFixtures;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

class AdminMediaEndpointsTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithStaff;
    use RefreshDatabase;

    private MediaService $media;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->media = app(MediaService::class);
    }

    // ------------------------------------------------------------------ pembantu

    /**
     * @param  list<string>  $permissions
     */
    private function staffWith(array $permissions): User
    {
        $this->seedRoles();

        $user = User::factory()->staff()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function doctorPhoto(): Media
    {
        return $this->media->store(ImageFixtures::jpeg(600, 600), 'photo', $this->createDoctor(), ['alt_text' => 'Alt lama']);
    }

    private function clinicCover(): Media
    {
        return $this->media->store(ImageFixtures::jpeg(1000, 600), 'cover', $this->createClinic(), ['alt_text' => 'Alt lama']);
    }

    /**
     * @return array{0: \App\Models\Clinic, 1: list<Media>}
     */
    private function clinicGallery(int $count = 3): array
    {
        $clinic = $this->createClinic();
        $items = [];

        for ($i = 1; $i <= $count; $i++) {
            $items[] = $this->media->store(ImageFixtures::jpeg(800, 600), 'gallery', $clinic, ['alt_text' => "Galeri {$i}"]);
        }

        return [$clinic, $items];
    }

    private function logo(): Media
    {
        return $this->media->store(ImageFixtures::jpeg(300, 100), 'logo', null, ['alt_text' => 'Alt lama'], User::factory()->staff()->create());
    }

    private function editorImage(?User $uploader = null): Media
    {
        return $this->media->store(ImageFixtures::jpeg(400, 300), 'editor', null, ['alt_text' => 'Alt lama'], $uploader ?? User::factory()->staff()->create());
    }

    private function mediaFor(string $target): Media
    {
        return match ($target) {
            'doctor' => $this->doctorPhoto(),
            'clinic' => $this->clinicCover(),
            'logo' => $this->logo(),
            'editor' => $this->editorImage(),
        };
    }

    private function useAsSiteLogo(Media $logo): void
    {
        $this->seed(SettingsSeeder::class);

        app(SettingsService::class)->set('site.logo_media_id', $logo->id);
        app(SettingsService::class)->forget();
    }

    // ------------------------------------------------------------------ route

    public function test_base_controller_provides_authorize_for_all_admin_controllers(): void
    {
        // K1: Task 6 adalah satu-satunya pemilik trait ini; Task 9-12 hanya memakainya.
        $this->assertContains(
            \Illuminate\Foundation\Auth\Access\AuthorizesRequests::class,
            class_uses_recursive(\App\Http\Controllers\Controller::class),
        );
        $this->assertTrue(method_exists(\App\Http\Controllers\Admin\MediaController::class, 'authorize'));
    }

    public function test_media_routes_exist_with_the_literal_permission_strings(): void
    {
        $update = 'permission:doctors.manage|clinics.manage|facilities.manage|content.manage|settings.manage';
        $expected = [
            'admin.media.editor-upload' => ['POST', 'admin/media/editor', 'permission:content.manage|clinics.manage|facilities.manage'],
            'admin.media.reorder' => ['POST', 'admin/media/urutkan', 'permission:doctors.manage|clinics.manage|facilities.manage|content.manage'],
            'admin.media.update' => ['PATCH', 'admin/media/{media}', $update],
            'admin.media.destroy' => ['DELETE', 'admin/media/{media}', $update],
        ];

        foreach ($expected as $name => [$method, $uri, $middleware]) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route, "Route {$name} belum terdaftar");
            $this->assertContains($method, $route->methods());
            $this->assertSame($uri, $route->uri());
            $this->assertContains($middleware, $route->gatherMiddleware());
        }
    }

    public function test_static_media_segments_are_not_captured_by_the_media_parameter(): void
    {
        $staff = $this->createStaff('super-admin');

        $this->actingAs($staff)->patchJson('/admin/media/urutkan', ['alt_text' => 'x'])->assertStatus(405);
        $this->actingAs($staff)->patchJson('/admin/media/abc', ['alt_text' => 'x'])->assertNotFound();
        $this->actingAs($staff)->patchJson('/admin/media/999999', ['alt_text' => 'x'])->assertNotFound();
    }

    // ------------------------------------------------------------------ editor-upload

    public function test_editor_upload_returns_201_with_a_storage_path_alt_and_media_id(): void
    {
        $user = $this->createStaff('content-admin');

        $response = $this->actingAs($user)->post(route('admin.media.editor-upload'), [
            'file' => ImageFixtures::jpeg(800, 600, 'ruang-periksa.jpg'),
            'alt_text' => '  Ruang periksa poli anak  ',
        ], ['Accept' => 'application/json']);

        $media = Media::query()->sole();

        $response->assertCreated()->assertExactJson([
            'url' => '/storage/'.$media->directory.'/'.$media->file_name,
            'alt' => 'Ruang periksa poli anak',
            'media_id' => $media->id,
        ]);

        $this->assertNull($media->model_type);
        $this->assertNull($media->model_id);
        $this->assertSame('editor', $media->collection);
        $this->assertSame($user->id, (int) $media->uploaded_by);
        $this->assertSame('Ruang periksa poli anak', $media->alt_text);
        Storage::disk('public')->assertExists($media->directory.'/'.$media->file_name);
        Storage::disk('public')->assertExists($media->thumb_path);
        $this->assertSame(1, AuditLog::query()->where('action', 'media.uploaded')->count());
    }

    /**
     * @return array<string, array{list<string>, int}>
     */
    public static function editorUploadPermissions(): array
    {
        return [
            'content.manage' => [['content.manage'], 201],
            'clinics.manage' => [['clinics.manage'], 201],
            'facilities.manage' => [['facilities.manage'], 201],
            'doctors.manage saja' => [['doctors.manage'], 403],
            'settings.manage saja' => [['settings.manage'], 403],
            'tanpa izin' => [[], 403],
        ];
    }

    /**
     * @param  list<string>  $permissions
     */
    #[DataProvider('editorUploadPermissions')]
    public function test_editor_upload_requires_one_of_the_content_permissions(array $permissions, int $status): void
    {
        $this->actingAs($this->staffWith($permissions))
            ->post(route('admin.media.editor-upload'), [
                'file' => ImageFixtures::jpeg(800, 600),
                'alt_text' => 'Gambar uji',
            ], ['Accept' => 'application/json'])
            ->assertStatus($status);

        $this->assertDatabaseCount('media', $status === 201 ? 1 : 0);
    }

    public function test_registration_officer_gets_a_json_403_and_guest_a_401(): void
    {
        $this->actingAs($this->createStaff('registration-officer'))
            ->post(route('admin.media.editor-upload'), [
                'file' => ImageFixtures::jpeg(800, 600),
                'alt_text' => 'Gambar uji',
            ], ['Accept' => 'application/json'])
            ->assertForbidden()
            ->assertExactJson(['message' => 'Anda tidak memiliki izin untuk melakukan tindakan ini.']);

        $this->postJson(route('admin.media.editor-upload'), [])->assertUnauthorized();
    }

    public function test_editor_upload_validation_errors_are_422_with_indonesian_messages(): void
    {
        $user = $this->createStaff('content-admin');
        $send = fn (array $data) => $this->actingAs($user)->post(route('admin.media.editor-upload'), $data, ['Accept' => 'application/json']);

        $send(['file' => ImageFixtures::jpeg(800, 600)])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['alt_text' => 'Teks alternatif gambar wajib diisi.']);

        $send(['file' => ImageFixtures::jpeg(800, 600), 'alt_text' => str_repeat('a', 256)])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['alt_text' => 'Teks alternatif gambar maksimal 255 karakter.']);

        $send(['alt_text' => 'Tanpa berkas'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file' => 'File gambar wajib dipilih.']);

        $send(['file' => ImageFixtures::jpeg(100, 50), 'alt_text' => 'Terlalu kecil'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file' => 'Dimensi gambar minimal 200 x 100 piksel dan maksimal 6000 x 6000 piksel.']);

        $send(['file' => ImageFixtures::svgWithScript(), 'alt_text' => 'SVG ditolak'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file' => 'File harus berupa gambar.']);

        $send(['file' => ImageFixtures::phpWithJpegHeader(), 'alt_text' => 'PHP berkepala JPEG'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file']);

        $this->assertDatabaseCount('media', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_editor_upload_of_a_corrupt_png_is_a_validation_error_not_a_500(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.media.editor-upload'), [
                'file' => ImageFixtures::corruptPng(600, 400),
                'alt_text' => 'Rusak',
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file']);

        $this->assertDatabaseCount('media', 0);
    }

    // ------------------------------------------------------------------ update (alt/keterangan)

    /**
     * @return array<string, array{list<string>, string, int}>
     */
    public static function updateMatrix(): array
    {
        return [
            'foto dokter: doctors.manage' => [['doctors.manage'], 'doctor', 200],
            'foto dokter: clinics.manage saja' => [['clinics.manage'], 'doctor', 403],
            'foto dokter: content.manage saja' => [['content.manage'], 'doctor', 403],
            'cover poliklinik: clinics.manage' => [['clinics.manage'], 'clinic', 200],
            'cover poliklinik: doctors.manage saja' => [['doctors.manage'], 'clinic', 403],
            'logo: settings.manage' => [['settings.manage'], 'logo', 200],
            'logo: Admin Konten (tanpa settings.manage)' => [['content.manage', 'clinics.manage', 'doctors.manage', 'facilities.manage'], 'logo', 403],
            'editor milik orang lain: content.manage' => [['content.manage'], 'editor', 200],
            'editor milik orang lain: facilities.manage' => [['facilities.manage'], 'editor', 200],
            'editor milik orang lain: settings.manage saja' => [['settings.manage'], 'editor', 403],
            'editor milik orang lain: doctors.manage saja' => [['doctors.manage'], 'editor', 403],
        ];
    }

    /**
     * @param  list<string>  $permissions
     */
    #[DataProvider('updateMatrix')]
    public function test_update_alt_is_authorised_by_the_owner_of_the_media(array $permissions, string $target, int $status): void
    {
        $media = $this->mediaFor($target);

        $this->actingAs($this->staffWith($permissions))
            ->patchJson(route('admin.media.update', $media), ['alt_text' => 'Alt baru'])
            ->assertStatus($status);

        $this->assertSame($status === 200 ? 'Alt baru' : 'Alt lama', $media->fresh()->alt_text);
    }

    public function test_super_admin_may_update_media_of_any_owner(): void
    {
        $super = $this->createStaff('super-admin');

        foreach (['doctor', 'clinic', 'logo', 'editor'] as $target) {
            $media = $this->mediaFor($target);

            $this->actingAs($super)->patchJson(route('admin.media.update', $media), ['alt_text' => "Alt {$target}"])->assertOk();
            $this->assertSame("Alt {$target}", $media->fresh()->alt_text);
        }
    }

    public function test_the_uploader_may_update_and_delete_their_own_editor_image_without_group_permission(): void
    {
        $uploader = $this->staffWith(['settings.manage']);
        $media = $this->editorImage($uploader);

        $this->actingAs($uploader)->patchJson(route('admin.media.update', $media), ['alt_text' => 'Alt sendiri'])->assertOk();
        $this->assertSame('Alt sendiri', $media->fresh()->alt_text);

        $this->actingAs($uploader)->deleteJson(route('admin.media.destroy', $media))->assertOk();
        $this->assertModelMissing($media);
    }

    public function test_update_json_shape_and_caption_handling(): void
    {
        $media = $this->doctorPhoto();
        $media->update(['caption' => 'Keterangan lama']);
        $user = $this->createStaff('content-admin');

        $this->actingAs($user)->patchJson(route('admin.media.update', $media), ['alt_text' => '  Dokter sedang praktik  '])
            ->assertOk()
            ->assertExactJson([
                'media_id' => $media->id,
                'alt' => 'Dokter sedang praktik',
                'caption' => 'Keterangan lama',
                'message' => 'Gambar berhasil diperbarui.',
            ]);

        $this->actingAs($user)->patchJson(route('admin.media.update', $media), ['alt_text' => 'Alt', 'caption' => ''])
            ->assertOk()
            ->assertJsonPath('caption', null);
        $this->assertNull($media->fresh()->caption);
    }

    public function test_update_validation_errors(): void
    {
        $media = $this->doctorPhoto();
        $user = $this->createStaff('content-admin');

        $this->actingAs($user)->patchJson(route('admin.media.update', $media), ['alt_text' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['alt_text' => 'Teks alternatif gambar wajib diisi.']);

        $this->actingAs($user)->patchJson(route('admin.media.update', $media), ['alt_text' => 'Alt', 'caption' => str_repeat('k', 256)])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['caption' => 'Keterangan gambar maksimal 255 karakter.']);

        $this->assertSame('Alt lama', $media->fresh()->alt_text);
    }

    public function test_update_from_a_plain_form_redirects_back_with_flash_or_errors(): void
    {
        $media = $this->doctorPhoto();
        $user = $this->createStaff('content-admin');

        $this->actingAs($user)->from('/admin/dokter')
            ->patch(route('admin.media.update', $media), ['alt_text' => 'Alt dari form'])
            ->assertRedirect('/admin/dokter')
            ->assertSessionHas('success', 'Gambar berhasil diperbarui.');

        $this->actingAs($user)->from('/admin/dokter')
            ->patch(route('admin.media.update', $media), ['alt_text' => ''])
            ->assertRedirect('/admin/dokter')
            ->assertSessionHasErrors(['alt_text' => 'Teks alternatif gambar wajib diisi.']);

        $this->assertSame('Alt dari form', $media->fresh()->alt_text);
    }

    // ------------------------------------------------------------------ Review Focus 5

    public function test_content_admin_cannot_change_the_alt_of_the_site_logo(): void
    {
        $logo = $this->logo();

        $this->actingAs($this->createStaff('content-admin'))
            ->patchJson(route('admin.media.update', $logo), ['alt_text' => 'Diubah Admin Konten'])
            ->assertForbidden();

        $this->assertSame('Alt lama', $logo->fresh()->alt_text);
    }

    public function test_registration_officer_cannot_delete_a_doctor_photo(): void
    {
        $photo = $this->doctorPhoto();

        $this->actingAs($this->createStaff('registration-officer'))
            ->deleteJson(route('admin.media.destroy', $photo))
            ->assertForbidden();

        $this->assertModelExists($photo);
        Storage::disk('public')->assertExists($photo->directory.'/'.$photo->file_name);
    }

    public function test_super_admin_cannot_delete_media_that_is_the_site_logo(): void
    {
        $logo = $this->logo();
        $this->useAsSiteLogo($logo);
        $super = $this->createStaff('super-admin');

        $this->actingAs($super)->deleteJson(route('admin.media.destroy', $logo))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Gambar tidak dapat dihapus karena sedang dipakai.')
            ->assertJsonPath('errors.media.0', 'Gambar tidak dapat dihapus karena sedang dipakai.');

        $this->actingAs($super)->from('/admin/pengaturan')
            ->delete(route('admin.media.destroy', $logo))
            ->assertRedirect('/admin/pengaturan')
            ->assertSessionHas('error', 'Gambar tidak dapat dihapus karena sedang dipakai.');

        $this->assertModelExists($logo);
        Storage::disk('public')->assertExists($logo->directory.'/'.$logo->file_name);
    }

    // ------------------------------------------------------------------ destroy

    /**
     * @return array<string, array{list<string>, string, int}>
     */
    public static function destroyMatrix(): array
    {
        return [
            'foto dokter: doctors.manage' => [['doctors.manage'], 'doctor', 200],
            'foto dokter: facilities.manage saja' => [['facilities.manage'], 'doctor', 403],
            'cover poliklinik: clinics.manage' => [['clinics.manage'], 'clinic', 200],
            'cover poliklinik: doctors.manage saja' => [['doctors.manage'], 'clinic', 403],
            'logo yang tidak dipakai: settings.manage' => [['settings.manage'], 'logo', 200],
            'logo: Admin Konten (tanpa settings.manage)' => [['content.manage', 'clinics.manage', 'doctors.manage', 'facilities.manage'], 'logo', 403],
            'editor milik orang lain: content.manage' => [['content.manage'], 'editor', 200],
            'editor milik orang lain: settings.manage saja' => [['settings.manage'], 'editor', 403],
        ];
    }

    /**
     * @param  list<string>  $permissions
     */
    #[DataProvider('destroyMatrix')]
    public function test_destroy_is_authorised_by_the_owner_and_removes_the_files(array $permissions, string $target, int $status): void
    {
        $media = $this->mediaFor($target);
        $paths = [$media->directory.'/'.$media->file_name, $media->thumb_path];

        $this->actingAs($this->staffWith($permissions))
            ->deleteJson(route('admin.media.destroy', $media))
            ->assertStatus($status);

        if ($status === 200) {
            $this->assertModelMissing($media);

            foreach ($paths as $path) {
                Storage::disk('public')->assertMissing($path);
            }
        } else {
            $this->assertModelExists($media);

            foreach ($paths as $path) {
                Storage::disk('public')->assertExists($path);
            }
        }
    }

    public function test_destroying_a_doctor_photo_keeps_the_doctor_and_writes_an_audit_row(): void
    {
        $doctor = $this->createDoctor();
        $photo = $this->media->store(ImageFixtures::jpeg(600, 600), 'photo', $doctor, ['alt_text' => 'Foto']);

        $this->actingAs($this->createStaff('content-admin'))
            ->deleteJson(route('admin.media.destroy', $photo))
            ->assertOk()
            ->assertExactJson(['message' => 'Gambar berhasil dihapus.']);

        $this->assertModelExists($doctor);
        $this->assertSame(1, AuditLog::query()->where('action', 'media.deleted')->count());
    }

    public function test_destroy_from_a_plain_form_redirects_back_with_a_flash(): void
    {
        $photo = $this->doctorPhoto();

        $this->actingAs($this->createStaff('content-admin'))->from('/admin/dokter')
            ->delete(route('admin.media.destroy', $photo))
            ->assertRedirect('/admin/dokter')
            ->assertSessionHas('success', 'Gambar berhasil dihapus.');

        $this->assertModelMissing($photo);
    }

    public function test_an_unused_logo_can_be_deleted_by_a_super_admin(): void
    {
        $logo = $this->logo();
        $this->seed(SettingsSeeder::class);

        $this->actingAs($this->createStaff('super-admin'))
            ->deleteJson(route('admin.media.destroy', $logo))
            ->assertOk();

        $this->assertModelMissing($logo);
    }

    // ------------------------------------------------------------------ reorder

    public function test_reorder_saves_the_new_positions(): void
    {
        [, $items] = $this->clinicGallery(3);
        [$first, $second, $third] = $items;

        $this->actingAs($this->createStaff('content-admin'))
            ->postJson(route('admin.media.reorder'), ['ids' => [$third->id, $first->id, $second->id]])
            ->assertOk()
            ->assertExactJson(['message' => 'Urutan gambar berhasil disimpan.']);

        $this->assertSame(1, $third->fresh()->sort_order);
        $this->assertSame(2, $first->fresh()->sort_order);
        $this->assertSame(3, $second->fresh()->sort_order);
    }

    public function test_reorder_from_a_plain_form_redirects_back_with_a_flash(): void
    {
        [, $items] = $this->clinicGallery(2);

        $this->actingAs($this->createStaff('content-admin'))->from('/admin/poliklinik/1/ubah')
            ->post(route('admin.media.reorder'), ['ids' => [$items[1]->id, $items[0]->id]])
            ->assertRedirect('/admin/poliklinik/1/ubah')
            ->assertSessionHas('success', 'Urutan gambar berhasil disimpan.');

        $this->assertSame(1, $items[1]->fresh()->sort_order);
    }

    public function test_reorder_validation_error_from_a_plain_form_redirects_back_with_errors(): void
    {
        $this->clinicGallery(2);

        $this->actingAs($this->createStaff('content-admin'))->from('/admin/poliklinik/1/ubah')
            ->post(route('admin.media.reorder'), ['ids' => []])
            ->assertRedirect('/admin/poliklinik/1/ubah')
            ->assertSessionHasErrors(['ids' => 'Daftar gambar wajib diisi.']);
    }

    public function test_reorder_rejects_media_of_different_owners_or_collections(): void
    {
        [, $galleryA] = $this->clinicGallery(2);
        [, $galleryB] = $this->clinicGallery(2);
        $user = $this->createStaff('content-admin');

        $this->actingAs($user)->postJson(route('admin.media.reorder'), ['ids' => [$galleryA[0]->id, $galleryB[0]->id]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ids' => 'Urutan gambar tidak valid.']);

        $cover = $this->media->store(ImageFixtures::jpeg(1000, 600), 'cover', $galleryA[0]->model()->first(), ['alt_text' => 'Cover']);

        $this->actingAs($user)->postJson(route('admin.media.reorder'), ['ids' => [$galleryA[0]->id, $cover->id]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ids' => 'Urutan gambar tidak valid.']);

        $this->assertSame(1, $galleryA[0]->fresh()->sort_order);
    }

    public function test_reorder_rejects_media_without_an_owner(): void
    {
        $one = $this->editorImage();
        $two = $this->editorImage();

        $this->actingAs($this->createStaff('super-admin'))
            ->postJson(route('admin.media.reorder'), ['ids' => [$one->id, $two->id]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ids' => 'Urutan gambar tidak valid.']);
    }

    public function test_reorder_validation_messages(): void
    {
        [, $items] = $this->clinicGallery(2);
        $user = $this->createStaff('content-admin');
        $send = fn (array $data) => $this->actingAs($user)->postJson(route('admin.media.reorder'), $data);

        $send([])->assertStatus(422)->assertJsonValidationErrors(['ids' => 'Daftar gambar wajib diisi.']);
        $send(['ids' => []])->assertStatus(422)->assertJsonValidationErrors(['ids' => 'Daftar gambar wajib diisi.']);
        $send(['ids' => range(1, 101)])->assertStatus(422)->assertJsonValidationErrors(['ids' => 'Maksimal 100 gambar dapat diurutkan sekaligus.']);
        $send(['ids' => [999999]])->assertStatus(422)->assertJsonValidationErrors(['ids.0' => 'Gambar tidak ditemukan.']);
        $send(['ids' => [$items[0]->id, $items[0]->id]])->assertStatus(422)->assertJsonValidationErrors(['ids.0' => 'Urutan gambar tidak valid.']);
        $send(['ids' => ['abc']])->assertStatus(422)->assertJsonValidationErrors(['ids.0' => 'Urutan gambar tidak valid.']);
    }

    public function test_reorder_is_authorised_for_every_media_in_the_list(): void
    {
        [, $gallery] = $this->clinicGallery(2);
        $photo = $this->doctorPhoto();

        // clinics.manage cukup untuk galeri poliklinik, tetapi tidak untuk foto dokter yang ikut dikirim.
        $clinicsOnly = $this->staffWith(['clinics.manage']);

        $this->actingAs($clinicsOnly)->postJson(route('admin.media.reorder'), ['ids' => [$gallery[1]->id, $gallery[0]->id]])->assertOk();
        $this->actingAs($clinicsOnly)->postJson(route('admin.media.reorder'), ['ids' => [$gallery[0]->id, $photo->id]])->assertForbidden();

        // doctors.manage meloloskan middleware tetapi bukan pemilik galeri poliklinik.
        $this->actingAs($this->staffWith(['doctors.manage']))
            ->postJson(route('admin.media.reorder'), ['ids' => [$gallery[0]->id, $gallery[1]->id]])
            ->assertForbidden();

        $this->actingAs($this->createStaff('registration-officer'))
            ->postJson(route('admin.media.reorder'), ['ids' => [$gallery[0]->id]])
            ->assertForbidden();
    }
}
```

- [ ] **Step 3: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=AdminMediaEndpointsTest
```
Expected: FAIL. Route `admin.media.*` belum ada (`Route [admin.media.editor-upload] not defined`, `assertNotNull` gagal, dan permintaan ke `/admin/media/...` mengembalikan 404/405).

- [ ] **Step 4: Implementasi — trait pada controller dasar (tool Write, ganti seluruh `app/Http/Controllers/Controller.php`)**

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use AuthorizesRequests;
}
```

- [ ] **Step 5: Implementasi — tiga Form Request (tool Write)**

`app/Http/Requests/Admin/EditorImageUploadRequest.php`:

```php
<?php

namespace App\Http\Requests\Admin;

use App\Models\Media;
use App\Rules\ImageRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Unggah gambar sisipan editor rich text (12 V-42, F2-R15): field `file` + `alt_text`.
 */
class EditorImageUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('upload', Media::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['bail', 'required', ...ImageRules::for('editor')],
            'alt_text' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(ImageRules::messages('file', 'editor'), [
            'file.required' => 'File gambar wajib dipilih.',
            'alt_text.required' => 'Teks alternatif gambar wajib diisi.',
            'alt_text.string' => 'Teks alternatif gambar harus berupa teks.',
            'alt_text.max' => 'Teks alternatif gambar maksimal 255 karakter.',
        ]);
    }

    protected function prepareForValidation(): void
    {
        $alt = $this->input('alt_text');

        if (is_string($alt)) {
            $this->merge(['alt_text' => trim($alt)]);
        }
    }
}
```

`app/Http/Requests/Admin/UpdateMediaRequest.php`:

```php
<?php

namespace App\Http\Requests\Admin;

use App\Models\Media;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Ubah teks alternatif dan keterangan satu gambar (12 V-42). Izin ditentukan MediaPolicy menurut pemilik.
 */
class UpdateMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $media = $this->route('media');

        return $media instanceof Media && $this->user()?->can('update', $media) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'alt_text' => ['required', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'alt_text.required' => 'Teks alternatif gambar wajib diisi.',
            'alt_text.string' => 'Teks alternatif gambar harus berupa teks.',
            'alt_text.max' => 'Teks alternatif gambar maksimal 255 karakter.',
            'caption.string' => 'Keterangan gambar harus berupa teks.',
            'caption.max' => 'Keterangan gambar maksimal 255 karakter.',
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['alt_text', 'caption'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $this->merge([$field => trim($value)]);
            }
        }
    }
}
```

`app/Http/Requests/Admin/ReorderMediaRequest.php`:

```php
<?php

namespace App\Http\Requests\Admin;

use App\Models\Media;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Urutkan ulang gambar (12 V-42). Pemilik dan koleksi diturunkan dari id media, bukan dari input.
 */
class ReorderMediaRequest extends FormRequest
{
    /** @var Collection<int, Media>|null */
    private ?Collection $items = null;

    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $ids = $this->input('ids');

        if (! is_array($ids)) {
            return true; // gagal di validasi (422), bukan 403
        }

        $numeric = collect($ids)
            ->filter(fn ($id) => is_int($id) || (is_string($id) && ctype_digit($id)))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->take(100)
            ->values();

        if ($numeric->isEmpty()) {
            return true;
        }

        // Setiap media yang ada harus boleh diurutkan oleh pengguna; id yang tidak ada ditolak validasi (422).
        return Media::query()->whereIn('id', $numeric)->get()->every(fn (Media $media) => $user->can('reorder', $media));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer', 'distinct', Rule::exists('media', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ids.required' => 'Daftar gambar wajib diisi.',
            'ids.array' => 'Daftar gambar wajib diisi.',
            'ids.min' => 'Daftar gambar wajib diisi.',
            'ids.max' => 'Maksimal 100 gambar dapat diurutkan sekaligus.',
            'ids.*.integer' => 'Urutan gambar tidak valid.',
            'ids.*.distinct' => 'Urutan gambar tidak valid.',
            'ids.*.exists' => 'Gambar tidak ditemukan.',
        ];
    }

    /**
     * Semua gambar harus berpemilik dan berasal dari pemilik serta koleksi yang sama.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $items = Media::query()
                ->with('model')
                ->whereIn('id', array_map('intval', (array) $this->input('ids')))
                ->get();
            $first = $items->first();

            $consistent = $first !== null
                && $first->model_type !== null
                && $first->model !== null
                && $items->every(fn (Media $media) => $media->model_type === $first->model_type
                    && (int) $media->model_id === (int) $first->model_id
                    && $media->collection === $first->collection);

            if (! $consistent) {
                $validator->errors()->add('ids', 'Urutan gambar tidak valid.');

                return;
            }

            $this->items = $items;
        }];
    }

    public function owner(): Model
    {
        return $this->resolved()->first()->model;
    }

    public function collection(): string
    {
        return $this->resolved()->first()->collection;
    }

    /**
     * @return list<int>
     */
    public function orderedIds(): array
    {
        return array_values(array_map('intval', $this->validated('ids')));
    }

    /**
     * @return Collection<int, Media>
     */
    private function resolved(): Collection
    {
        return $this->items ?? throw new \LogicException('ReorderMediaRequest dipakai sebelum validasi lolos.');
    }
}
```

- [ ] **Step 6: Implementasi — `MediaController` (tool Write) `app/Http/Controllers/Admin/MediaController.php`**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EditorImageUploadRequest;
use App\Http\Requests\Admin\ReorderMediaRequest;
use App\Http\Requests\Admin\UpdateMediaRequest;
use App\Models\Media;
use App\Services\Media\MediaService;
use App\Services\Settings\SettingsService;
use App\Support\SafeRedirect;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Endpoint media admin (11 §4.4, F-ADM-29): JSON untuk editor/AJAX, redirect + flash untuk form biasa.
 * Izin ditentukan MediaPolicy menurut pemilik media (F2-R16); tidak ada aturan bisnis lain di sini.
 */
class MediaController extends Controller
{
    public const IN_USE_MESSAGE = 'Gambar tidak dapat dihapus karena sedang dipakai.';

    public function __construct(
        private readonly MediaService $media,
        private readonly SettingsService $settings,
    ) {}

    public function editorUpload(EditorImageUploadRequest $request): JsonResponse
    {
        $media = $this->media->store(
            $request->file('file'),
            'editor',
            null,
            ['alt_text' => $request->validated('alt_text')],
            $request->user(),
        );

        return response()->json([
            'url' => $this->publicPath($media),
            'alt' => $media->alt_text,
            'media_id' => $media->id,
        ], 201);
    }

    public function update(UpdateMediaRequest $request, Media $media): JsonResponse|RedirectResponse
    {
        $validated = $request->validated();

        // Keterangan yang tidak dikirim dibiarkan; mengirim kosong menghapusnya.
        $caption = array_key_exists('caption', $validated) ? $validated['caption'] : $media->caption;

        $this->media->updateMeta($media, $validated['alt_text'], $caption);

        $message = __('messages.updated', ['entity' => 'Gambar']);

        if ($request->expectsJson()) {
            return response()->json([
                'media_id' => $media->id,
                'alt' => $media->alt_text,
                'caption' => $media->caption,
                'message' => $message,
            ]);
        }

        return redirect()->to(SafeRedirect::back($request))->with('success', $message);
    }

    public function destroy(Request $request, Media $media): JsonResponse|RedirectResponse
    {
        $this->authorize('delete', $media);

        // Logo situs dirujuk pengaturan; berlaku juga untuk Super Admin (F2-R17).
        if ($this->settings->int('site.logo_media_id') === $media->id) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => self::IN_USE_MESSAGE,
                    'errors' => ['media' => [self::IN_USE_MESSAGE]],
                ], 422);
            }

            return redirect()->to(SafeRedirect::back($request))->with('error', self::IN_USE_MESSAGE);
        }

        $this->media->delete($media);

        $message = __('messages.deleted', ['entity' => 'Gambar']);

        if ($request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return redirect()->to(SafeRedirect::back($request))->with('success', $message);
    }

    public function reorder(ReorderMediaRequest $request): JsonResponse|RedirectResponse
    {
        $this->media->reorder($request->owner(), $request->collection(), $request->orderedIds());

        $message = 'Urutan gambar berhasil disimpan.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return redirect()->to(SafeRedirect::back($request))->with('success', $message);
    }

    /**
     * Path /storage/... agar <img> editor tetap dimuat dengan CSP img-src 'self' dan lolos HtmlSanitizer.
     */
    private function publicPath(Media $media): string
    {
        $path = parse_url((string) $media->url, PHP_URL_PATH);

        return is_string($path) && $path !== ''
            ? $path
            : '/storage/'.$media->directory.'/'.$media->file_name;
    }
}
```

- [ ] **Step 7: Implementasi — route di `routes/admin.php` (tool Edit, dua penggantian)**

Penggantian 1 — impor. `old_string`:

```php
use App\Http\Controllers\Admin\DashboardController;
```

`new_string`:

```php
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\MediaController;
```

Penggantian 2 — blok media di akhir grup G-ADMIN. `old_string`:

```php
    Route::get('/', [DashboardController::class, 'index'])
        ->middleware('permission:dashboard.view')
        ->name('dashboard');
});
```

`new_string`:

```php
    Route::get('/', [DashboardController::class, 'index'])
        ->middleware('permission:dashboard.view')
        ->name('dashboard');

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
```

(Task 9–12 menambah route lain di grup yang sama, di atas blok media atau sesudahnya; urutan antar-resource tidak berpengaruh selama segmen statis mendahului parameter dalam awalan yang sama.)

- [ ] **Step 7a: Ubah `shouldRenderJsonWhen` di `bootstrap/app.php` (tes dulu, lalu tool Edit, dua penggantian)**

RED lebih dulu. Route dan controller sudah ada (Step 5-7), tetapi callback fase 1 masih memaksa JSON untuk galat validasi pada `admin.media.*`:

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="test_update_from_a_plain_form_redirects_back_with_flash_or_errors|test_reorder_validation_error_from_a_plain_form_redirects_back_with_errors"
```
Expected: FAIL pada kedua tes (respons 422 JSON, bukan redirect, sehingga `assertRedirect` gagal).

Penggantian 1 - impor (urutan alfabetis tetap terjaga). `old_string`:

```php
use Illuminate\Support\Facades\Route;
```

`new_string`:

```php
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
```

Penggantian 2 - callback. `old_string`:

```php
        $exceptions->shouldRenderJsonWhen(fn (Request $request, Throwable $e): bool => $request->expectsJson()
            || $request->routeIs('admin.dashboard.chart', 'admin.media.*'));
```

`new_string`:

```php
        // Galat validasi pada admin.media.* hanya JSON bila klien meminta JSON; form HTML biasa mendapat redirect + $errors.
        $exceptions->shouldRenderJsonWhen(fn (Request $request, Throwable $e): bool => $request->expectsJson()
            || $request->routeIs('admin.dashboard.chart')
            || ($request->routeIs('admin.media.*') && ! $e instanceof ValidationException));
```

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP -l bootstrap\app.php
& $PHP artisan test --filter=AdminMediaEndpointsTest
```
Expected: `No syntax errors detected`; seluruh tes PASS (permintaan JSON tetap 422 JSON, form HTML biasa redirect + galat sesi). Bila ada tes dashboard chart JSON (`admin.dashboard.chart`), jalankan juga: perilakunya tidak berubah.

- [ ] **Step 8: Jalankan tes — harus lulus, termasuk KNS-02**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="AdminMediaEndpointsTest|AdminRouteMiddlewareTest"
& $PHP artisan route:list --path=admin/media
```
Expected: seluruhnya PASS (`AdminRouteMiddlewareTest` tetap hijau: keempat route berada di grup G-ADMIN dan memiliki `permission:*`). `route:list` menampilkan empat baris `admin.media.editor-upload`, `admin.media.reorder`, `admin.media.update`, `admin.media.destroy` dengan urutan `editor`, `urutkan`, lalu `{media}`.
Bila `test_reorder_rejects_media_of_different_owners_or_collections` gagal karena `model()->first()`: tes memakai relasi `Media::model()` hanya untuk mengambil pemilik; boleh diganti dengan `Clinic::query()->findOrFail($galleryA[0]->model_id)`.
Bila ada tes matriks (403/200) yang gagal: periksa `MediaPolicy` Task 4 terhadap F2-R16 (kasus `editor` = pengunggah sendiri **atau** salah satu `content.manage`/`clinics.manage`/`facilities.manage`; `logo` = `settings.manage`; `Doctor` = `doctors.manage`; `Clinic` = `clinics.manage`). Perbaiki **Policy**, bukan tes.

- [ ] **Step 9: Seluruh suite, Pint, dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test
& $PHP vendor\bin\pint
& $PHP vendor\bin\pint --test
git add app/Http/Controllers/Controller.php app/Http/Controllers/Admin/MediaController.php app/Http/Requests/Admin/EditorImageUploadRequest.php app/Http/Requests/Admin/UpdateMediaRequest.php app/Http/Requests/Admin/ReorderMediaRequest.php routes/admin.php bootstrap/app.php tests/Feature/Admin/AdminMediaEndpointsTest.php
git commit -m "feat(fase-2): endpoint media admin (editor-upload, update, destroy, reorder)" -m "MediaController + tiga Form Request V-42; izin menurut pemilik lewat MediaPolicy; logo situs tidak dapat dihapus; Controller dasar memakai AuthorizesRequests." -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```
Expected: `artisan test` hijau seluruhnya; `pint` tanpa `--test` dijalankan lebih dulu (merapikan urutan impor `routes/admin.php`), lalu `pint --test` `PASS`; commit sukses. Tidak ada perubahan `resources/js`/`resources/scss`, jadi `npm run build` tidak wajib di task ini.

---

### Task 7: Editor rich text (Quill 2): `x-form.rich-text`, `editor.js`, meta CSRF, penjaga manifest

**Files:**
- Create: `resources/views/components/form/rich-text.blade.php`, `resources/js/editor.js`
- Modify: `resources/js/admin.js`, `resources/scss/_admin.scss`, `resources/views/components/layouts/admin.blade.php`, `tests/Unit/FrontendManifestTest.php`
- Test: `tests/Feature/View/RichTextComponentTest.php`, `tests/Feature/Admin/AdminCsrfMetaTest.php`, `tests/Unit/FrontendManifestTest.php`

**Interfaces:**
- Consumes: `admin.media.editor-upload` (Task 6; respons 201 `{url, alt, media_id}`, galat 422 `{message, errors}`), `window.bootstrap` (`app.js`, dimuat oleh `admin.js`), `Tests\Support\InteractsWithFormState`, kontrak markup fase 1 (`[data-errors-summary]`, `required-mark`, `invalid-feedback d-block`), `quill@2.0.3` (sudah di `package.json`; **tidak ada paket baru**).
- Produces:
  - `<x-form.rich-text name label :value help required :maxlength :min-height :upload-url />`:
    - `value` = HTML tersanitasi dari DB; `old()` menang atas `value`; `minHeight` default 240 (piksel, minimum 120); `maxlength` (karakter HTML mentah, mis. 30000 pada poliklinik, F2-R13); `uploadUrl` default `route('admin.media.editor-upload')`.
    - Markup kontrak untuk JS: pembungkus `[data-rich-text]` (atribut `data-upload-url`, `data-min-height`, `data-max-length` bila ada) berisi `textarea[data-rich-text-input]` bernama `name` (tanpa atribut `required`, hanya `aria-required`), `[data-rich-text-editor][hidden]` berisi `[data-rich-text-toolbar]` (`role="toolbar"`, `aria-label="Pemformatan teks: {label}"`) dan `[data-rich-text-surface]`, `[data-rich-text-status]` (`role="status"`, `aria-live="polite"`, `visually-hidden`), serta modal Bootstrap `[data-rich-text-image-modal]` (id `{id}-image-modal`) berisi `input[type=file][data-rich-text-image-file]`, `img[data-rich-text-image-preview]` (`alt=""`), `input[data-rich-text-image-alt]`, `[data-rich-text-image-error]` (`role="alert"`), dan tombol `[data-rich-text-image-submit]`.
    - Tombol toolbar: `ql-header` (nilai 2, 3, 4), `ql-bold`, `ql-italic`, `ql-underline`, `ql-list` (`ordered`, `bullet`), `ql-blockquote`, `ql-link`, `ql-image` (aria-label "Sisipkan gambar"), `ql-clean`. Semua `type="button"` dengan `aria-label` dan `title` berbahasa Indonesia; ini persis himpunan tag yang diizinkan purifier (`h2–h4`, `strong`, `em`, `u`, `ol`/`ul`/`li`, `blockquote`, `a`, `img`).
    - Tanpa JavaScript: `[data-rich-text-editor]` tetap `hidden`, textarea biasa tampil dan berfungsi.
  - `resources/js/editor.js` (dimuat lazy oleh `admin.js` bila ada `[data-rich-text]`): Quill 2 tema `snow` (CSS `quill/dist/quill.snow.css` ikut chunk dinamis); `formats` dibatasi; textarea disinkronkan dari `quill.getSemanticHTML()` saat `submit` (nilai `''` bila editor kosong dan tanpa gambar); unggah gambar lewat `fetch` POST `multipart` (`file`, `alt_text`) dengan header `X-CSRF-TOKEN` dari `<meta name="csrf-token">`; alt wajib lewat modal; gambar disisipkan dengan `src` = `url` dari server (`/storage/…`) dan `alt` dari server.
  - `<meta name="csrf-token" content="{{ csrf_token() }}">` di `<head>` layout admin.
  - Gaya `.rich-text` di `_admin.scss` (terjemahan tooltip tautan Quill ke Bahasa Indonesia, fokus tombol, gambar responsif, `.rich-text-preview`).

**Teks UI (sumber):**

| Teks | Sumber |
|---|---|
| Toolbar: "Judul 2/3/4", "Tebal", "Miring", "Garis bawah", "Daftar bernomor", "Daftar poin", "Kutipan", "Tautan", "Sisipkan gambar", "Bersihkan format" | 10 §3.5 (butir toolbar), label **ASUMSI** |
| Modal: "Sisipkan gambar", "Berkas gambar", "Teks alternatif", "Unggah dan sisipkan", "Batal" | 10 §3.5 / F2-R2; label **ASUMSI** |
| "Pilih berkas gambar terlebih dahulu." | **ASUMSI** (validasi sisi klien; server tetap otoritatif) |
| "File harus berupa gambar JPG, PNG, atau WebP." / "Ukuran gambar maksimal 2 MB." / "Teks alternatif gambar wajib diisi." | 12 V-42 / §3.2 |
| "Gambar gagal diunggah. Periksa koneksi Anda, lalu coba lagi." | **ASUMSI** (galat jaringan; pesan server dipakai apa adanya bila ada) |
| "Isi editor melebihi batas {n} karakter. Persingkat isi atau kurangi jumlah gambar." | **ASUMSI** (selaras F2-R13 `max:30000`) |
| "Untuk menyisipkan gambar, gunakan tombol Sisipkan gambar pada toolbar." | **ASUMSI** (tempel/seret berkas gambar diblokir) |

**Test IDs:** SEK-08 (bagian: tidak ada `blob:`/`data:`/inline handler), SEK-09 (SVG ditolak di editor, lewat Task 6), MED (editor), penjaga `FrontendManifestTest` (F1-R14), carry 12 §5.1 no. 2 (header `X-CSRF-TOKEN`).

**Catatan teknis (diverifikasi dari `node_modules/quill` 2.0.3 dan `node_modules/parchment`):**
- Impor: `import Quill from 'quill';` (`package.json` → `main: quill.js`, ESM) dan `import 'quill/dist/quill.snow.css';`. Tidak ada kunci `exports` yang membatasi subpath.
- **CSP kompatibel.** Pencarian `eval(`/`new Function` pada seluruh sumber ESM Quill (`core/`, `modules/`, `formats/`, `blots/`, `themes/`, `ui/`, `quill.js`) tidak menemukan apa pun; satu kemunculan hanya ada di bundel UMD `dist/quill.js`, yang **tidak** dipakai karena Vite memuat sumber ESM. Quill hanya menulis atribut `style` inline (mis. `min-height` yang kita set lewat CSSOM); itu sah di bawah `style-src 'self' 'unsafe-inline'`. Bundel build diperiksa lagi di Step 14. Quill tidak memakai `blob:`; satu-satunya jalur Quill yang menanamkan gambar dari berkas (modul `uploader`, tempel gambar) diganti handler yang hanya mengumumkan pesan, sehingga tidak ada gambar `data:` yang lolos (HtmlSanitizer toh membuang `img` non-`/storage/`).
- Tombol toolbar berasal dari markup Blade: `Toolbar.attach()` memakai kelas `ql-{format}` dan nilai `button.value` (`header` bernilai `'2'` diterima Parchment sebagai tingkat judul 2). Handler bawaan Quill untuk format embed tanpa handler memakai `prompt()`; handler `image` kita menggantinya dengan modal.
- Sisipan gambar memakai `quill.updateContents(new Delta().retain(i).delete(n).insert({ image: url }, { alt }), 'user')`; `Image.format('alt', …)` (`formats/image.js`) menulis atribut `alt`, dan `EmbedBlot.formatAt` meneruskannya. `getSemanticHTML()` menghasilkan `<img src="/storage/…" alt="…">` dan `<ul>/<ol>` semantik (bukan `data-list`), lalu HTMLPurifier + `HtmlSanitizer` membersihkannya saat simpan.
- Isi awal dimuat dengan `quill.clipboard.convert({ html })` (DOMParser; skrip dan atribut event tidak dieksekusi) lalu `setContents(..., 'silent')`, bukan `innerHTML`.
- `[hidden]` dipaksa `display: none !important` oleh Reboot Bootstrap, sehingga textarea yang diberi `hidden` oleh JS tidak muncul walau berkelas `form-control`. Atribut `required` HTML **tidak** dipasang pada textarea tersembunyi (browser menolak mengirim form dengan kontrol tersembunyi yang tidak dapat difokuskan).
- Modal dipindahkan ke `document.body` saat inisialisasi (praktik Bootstrap, dan agar kontrol modal tidak berada di dalam `<form>` utama). Kontrol modal tidak memiliki atribut `name`, jadi tidak pernah ikut terkirim.
- Pemuat tombol (`app.js`) mendengarkan `submit` di tingkat dokumen dan melewati event dengan `defaultPrevented`; listener `submit` kita ada pada form (lebih awal), sehingga pembatalan karena isi terlalu panjang tidak mengunci tombol Simpan.
- Penjaga fase 1 yang harus tetap hijau: tepat **satu** `addEventListener('error'` di seluruh `resources/js` (editor.js tidak memakainya), dan tidak ada kata `onerror` (huruf kecil) di `resources/js` maupun `resources/views`. Karena itu `editor.js` tidak memasang penangan galat `FileReader`; pratinjau hanya saran dan gagal diam-diam.
- Tautan ringkasan galat `#description` mengarah ke textarea yang tersembunyi; `editor.js` menangkap klik tautan itu dan memfokuskan editor.
- Perilaku JS tidak dapat diuji unit tanpa jsdom dan **tidak ada paket npm baru yang diizinkan**; JS diverifikasi dengan `node --check`, penjaga teks `FrontendManifestTest`, `npm run build`, dan uji manual di Task 10 (form poliklinik) serta gerbang Task 16.

- [ ] **Step 1: Verifikasi prasyarat**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git branch --show-current
git status --short
Test-Path node_modules\quill\dist\quill.snow.css
Test-Path app\Http\Controllers\Admin\MediaController.php
& $PHP artisan route:list --name=admin.media.editor-upload
& $PHP artisan test --filter="FrontendManifestTest|LayoutsTest|AdminLayoutTest"
```
Expected: `fase-2-master-data`; `git status` bersih; dua `True`; route `admin.media.editor-upload` tampil; seluruh tes PASS. Bila `node_modules` tidak ada: `npm ci` (tidak menambah paket).

**Siklus A — komponen Blade dan meta CSRF**

- [ ] **Step 2: Tulis tes yang gagal (tool Write) `tests/Feature/View/RichTextComponentTest.php`**

```php
<?php

namespace Tests\Feature\View;

use Tests\Support\InteractsWithFormState;
use Tests\TestCase;

class RichTextComponentTest extends TestCase
{
    use InteractsWithFormState;

    private function render(string $attributes = '', array $data = []): string
    {
        return (string) $this->blade('<x-form.rich-text name="description" label="Deskripsi" '.$attributes.' />', $data);
    }

    public function test_renders_label_textarea_and_data_contract(): void
    {
        $html = $this->render(':value="$value" help="Gunakan judul dan daftar." required :maxlength="30000"', ['value' => '<p>Halo</p>']);

        $this->assertStringContainsString('data-rich-text', $html);
        $this->assertStringContainsString('data-upload-url="'.route('admin.media.editor-upload').'"', $html);
        $this->assertStringContainsString('data-min-height="240"', $html);
        $this->assertStringContainsString('data-max-length="30000"', $html);
        $this->assertStringContainsString('<label id="description-label" for="description" class="form-label fw-semibold">Deskripsi <span class="required-mark" aria-hidden="true">*</span></label>', $html);
        $this->assertStringContainsString('<textarea id="description" name="description"', $html);
        $this->assertStringContainsString('data-rich-text-input', $html);
        $this->assertStringContainsString('aria-required="true"', $html);
        $this->assertStringContainsString('aria-describedby="description-help"', $html);
        $this->assertStringContainsString('<div id="description-help" class="form-text">Gunakan judul dan daftar.</div>', $html);
        $this->assertStringContainsString('&lt;p&gt;Halo&lt;/p&gt;</textarea>', $html);
        $this->assertDoesNotMatchRegularExpression('/<textarea[^>]*\srequired[\s>]/', $html, 'required HTML pada textarea tersembunyi memblokir submit');
    }

    public function test_editor_container_is_hidden_until_javascript_enables_it(): void
    {
        $html = $this->render();

        $this->assertMatchesRegularExpression('/<div[^>]*\shidden[^>]*data-rich-text-editor|<div[^>]*data-rich-text-editor[^>]*\shidden/', $html);
        $this->assertStringContainsString('data-rich-text-surface', $html);
        $this->assertStringContainsString('role="status"', $html);
        $this->assertStringContainsString('data-rich-text-status', $html);
    }

    public function test_toolbar_is_labelled_and_all_buttons_are_accessible_non_submit_buttons(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('role="toolbar"', $html);
        $this->assertStringContainsString('aria-label="Pemformatan teks: Deskripsi"', $html);

        foreach (['ql-header" value="2"', 'ql-header" value="3"', 'ql-header" value="4"', 'ql-bold', 'ql-italic', 'ql-underline', 'ql-list" value="ordered"', 'ql-list" value="bullet"', 'ql-blockquote', 'ql-link', 'ql-image', 'ql-clean'] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }

        foreach (['Judul 2', 'Judul 3', 'Judul 4', 'Tebal', 'Miring', 'Garis bawah', 'Daftar bernomor', 'Daftar poin', 'Kutipan', 'Tautan', 'Sisipkan gambar', 'Bersihkan format'] as $label) {
            $this->assertStringContainsString('aria-label="'.$label.'"', $html);
        }

        $this->assertSame(substr_count($html, '<button'), substr_count($html, '<button type="button"'));
    }

    public function test_alt_text_modal_is_part_of_the_component(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('id="description-image-modal"', $html);
        $this->assertStringContainsString('data-rich-text-image-modal', $html);
        $this->assertStringContainsString('aria-labelledby="description-image-modal-title"', $html);
        $this->assertStringContainsString('accept="image/jpeg,image/png,image/webp"', $html);
        $this->assertStringContainsString('data-rich-text-image-file', $html);
        $this->assertStringContainsString('data-rich-text-image-alt', $html);
        $this->assertStringContainsString('maxlength="255"', $html);
        $this->assertStringContainsString('<label for="description-image-alt" class="form-label fw-semibold">Teks alternatif', $html);
        $this->assertMatchesRegularExpression('/<img[^>]*data-rich-text-image-preview[^>]*alt=""|<img[^>]*alt=""[^>]*data-rich-text-image-preview/', $html);
        $this->assertStringContainsString('role="alert"', $html);
        $this->assertStringContainsString('data-rich-text-image-submit', $html);
        $this->assertStringContainsString('data-bs-dismiss="modal"', $html);
        $this->assertStringContainsString('Unggah dan sisipkan', $html);
    }

    public function test_has_no_inline_handlers_inline_styles_blob_urls_or_scripts(): void
    {
        $html = $this->render(':value="$value"', ['value' => 'x']);

        $this->assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=/i', $html);
        $this->assertStringNotContainsString('style=', $html);
        $this->assertStringNotContainsString('blob:', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    public function test_old_input_overrides_value_and_error_state_is_linked(): void
    {
        $this->withOldInput(['description' => '<p>Isian lama</p>']);
        $this->withViewErrors(['description' => ['Deskripsi maksimal 30.000 karakter.']]);

        $html = $this->render(':value="$value" help="Bantuan."', ['value' => '<p>Dari DB</p>']);

        $this->assertStringContainsString('&lt;p&gt;Isian lama&lt;/p&gt;</textarea>', $html);
        $this->assertStringNotContainsString('Dari DB', $html);
        $this->assertStringContainsString('form-control is-invalid', $html);
        $this->assertStringContainsString('aria-invalid="true"', $html);
        $this->assertStringContainsString('aria-describedby="description-help description-error"', $html);
        $this->assertStringContainsString('<div id="description-error" class="invalid-feedback d-block">Deskripsi maksimal 30.000 karakter.</div>', $html);
    }

    public function test_upload_url_and_min_height_are_configurable_and_escaped(): void
    {
        $html = $this->render('upload-url="/admin/media/editor?a=1&b=&quot;2&quot;" :min-height="400"');

        // Nilai literal `&quot;` di-escape ulang oleh {{ }} (double-encode): `&quot;` menjadi `&amp;quot;`.
        $this->assertStringContainsString('data-upload-url="/admin/media/editor?a=1&amp;b=&amp;quot;2&amp;quot;"', $html);
        $this->assertStringNotContainsString('b="2"', $html);
        $this->assertStringContainsString('data-min-height="400"', $html);
        $this->assertStringContainsString('data-min-height="120"', $this->render(':min-height="50"'));
    }

    public function test_id_is_derived_like_the_other_controls_for_bracketed_names(): void
    {
        $this->withViewErrors(['items.0.body' => ['Isi wajib diisi.']]);

        $html = (string) $this->blade('<x-form.rich-text name="items[0][body]" label="Isi" />');

        $this->assertStringContainsString('id="items-0-body"', $html);
        $this->assertStringContainsString('<div id="items-0-body-error" class="invalid-feedback d-block">Isi wajib diisi.</div>', $html);

        $this->blade('<x-form.errors-summary />')
            ->assertSee('<a href="#items-0-body" class="alert-link">Isi wajib diisi.</a>', false);
    }
}
```

`tests/Feature/Admin/AdminCsrfMetaTest.php`:

```php
<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

class AdminCsrfMetaTest extends TestCase
{
    use InteractsWithStaff;
    use RefreshDatabase;

    public function test_admin_layout_exposes_the_csrf_token_meta_for_ajax_uploads(): void
    {
        $html = $this->actingAs($this->createStaff('content-admin'))->get('/admin')->assertOk()->getContent();

        $this->assertSame(1, preg_match_all('/<meta name="csrf-token" content="[A-Za-z0-9]{40}">/', $html));
        $this->assertLessThan(strpos($html, '<title>'), strpos($html, '<meta name="csrf-token"'), 'meta harus berada di <head> sebelum <title>');
    }
}
```

- [ ] **Step 3: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="RichTextComponentTest|AdminCsrfMetaTest"
```
Expected: FAIL — komponen `form.rich-text` belum ada (`Unable to locate a class or view for component [form.rich-text]`) dan meta `csrf-token` belum ada.

- [ ] **Step 4: Implementasi — komponen (tool Write) `resources/views/components/form/rich-text.blade.php`**

```blade
@props([
    'name',
    'label',
    'id' => null,
    'help' => null,
    'required' => false,
    'value' => null,
    'maxlength' => null,
    'minHeight' => 240,
    'uploadUrl' => null,
])
@php
    $id = $id ?: trim(str_replace(['.', '[', ']'], ['-', '-', ''], $name), '-');
    $key = trim(str_replace(['[', ']'], ['.', ''], $name), '.');
    $error = isset($errors) ? (string) $errors->first($key) : '';
    $current = old($key, $value);
    $current = is_scalar($current) ? (string) $current : '';
    $describedBy = trim(($help ? $id.'-help' : '').' '.($error !== '' ? $id.'-error' : ''));
    $uploadUrl = $uploadUrl ?: route('admin.media.editor-upload');
    $minHeight = max(120, (int) $minHeight);
    $modalId = $id.'-image-modal';
@endphp
<div class="mb-3 rich-text" data-rich-text data-upload-url="{{ $uploadUrl }}" data-min-height="{{ $minHeight }}"@if ($maxlength) data-max-length="{{ $maxlength }}"@endif>
    <label id="{{ $id }}-label" for="{{ $id }}" class="form-label fw-semibold">{{ $label }}@if ($required) <span class="required-mark" aria-hidden="true">*</span>@endif</label>

    {{-- Tanpa JavaScript, textarea ini adalah editornya; editor.js menyembunyikannya dan menyinkronkan nilainya saat submit. --}}
    <textarea id="{{ $id }}" name="{{ $name }}" rows="10"@if ($required) aria-required="true"@endif @if ($error !== '') aria-invalid="true"@endif @if ($describedBy !== '') aria-describedby="{{ $describedBy }}"@endif data-rich-text-input {{ $attributes->class(['form-control', 'is-invalid' => $error !== '']) }}>{{ $current }}</textarea>

    <div class="rich-text-editor" data-rich-text-editor hidden>
        <div role="toolbar" aria-label="Pemformatan teks: {{ $label }}" data-rich-text-toolbar>
            <span class="ql-formats">
                <button type="button" class="ql-header" value="2" aria-label="Judul 2" title="Judul 2"></button>
                <button type="button" class="ql-header" value="3" aria-label="Judul 3" title="Judul 3"></button>
                <button type="button" class="ql-header" value="4" aria-label="Judul 4" title="Judul 4"></button>
            </span>
            <span class="ql-formats">
                <button type="button" class="ql-bold" aria-label="Tebal" title="Tebal"></button>
                <button type="button" class="ql-italic" aria-label="Miring" title="Miring"></button>
                <button type="button" class="ql-underline" aria-label="Garis bawah" title="Garis bawah"></button>
            </span>
            <span class="ql-formats">
                <button type="button" class="ql-list" value="ordered" aria-label="Daftar bernomor" title="Daftar bernomor"></button>
                <button type="button" class="ql-list" value="bullet" aria-label="Daftar poin" title="Daftar poin"></button>
                <button type="button" class="ql-blockquote" aria-label="Kutipan" title="Kutipan"></button>
            </span>
            <span class="ql-formats">
                <button type="button" class="ql-link" aria-label="Tautan" title="Tautan"></button>
                <button type="button" class="ql-image" aria-label="Sisipkan gambar" title="Sisipkan gambar"></button>
                <button type="button" class="ql-clean" aria-label="Bersihkan format" title="Bersihkan format"></button>
            </span>
        </div>
        <div data-rich-text-surface></div>
    </div>

    <div class="visually-hidden" role="status" aria-live="polite" data-rich-text-status></div>

    @if ($help)<div id="{{ $id }}-help" class="form-text">{{ $help }}</div>@endif
    @if ($error !== '')<div id="{{ $id }}-error" class="invalid-feedback d-block">{{ $error }}</div>@endif

    {{-- Modal alt text (dipindahkan ke <body> oleh editor.js). Kontrolnya tanpa atribut name: tidak ikut terkirim. --}}
    <div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}-title" aria-hidden="true" data-rich-text-image-modal>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="{{ $modalId }}-title">Sisipkan gambar</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="{{ $id }}-image-file" class="form-label fw-semibold">Berkas gambar <span class="required-mark" aria-hidden="true">*</span></label>
                        <input type="file" id="{{ $id }}-image-file" class="form-control" accept="image/jpeg,image/png,image/webp" aria-describedby="{{ $id }}-image-file-help" data-rich-text-image-file>
                        <div id="{{ $id }}-image-file-help" class="form-text">JPG, PNG, atau WebP, maksimal 2 MB, minimal 200 x 100 piksel. SVG ditolak.</div>
                    </div>
                    <img class="img-thumbnail rich-text-preview d-none mb-3" alt="" data-rich-text-image-preview>
                    <div class="mb-3">
                        <label for="{{ $id }}-image-alt" class="form-label fw-semibold">Teks alternatif <span class="required-mark" aria-hidden="true">*</span></label>
                        <input type="text" id="{{ $id }}-image-alt" class="form-control" maxlength="255" autocomplete="off" aria-describedby="{{ $id }}-image-alt-help" data-rich-text-image-alt>
                        <div id="{{ $id }}-image-alt-help" class="form-text">Jelaskan isi gambar untuk pembaca layar. Wajib diisi, maksimal 255 karakter.</div>
                    </div>
                    <div class="alert alert-danger d-none mb-0" role="alert" data-rich-text-image-error></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary" data-rich-text-image-submit>Unggah dan sisipkan</button>
                </div>
            </div>
        </div>
    </div>
</div>
```

- [ ] **Step 5: Implementasi — meta CSRF di layout admin (tool Edit `resources/views/components/layouts/admin.blade.php`)**

`old_string` (tepat satu kecocokan):

```blade
    <meta name="robots" content="noindex,nofollow">
```

`new_string`:

```blade
    <meta name="robots" content="noindex,nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
```

- [ ] **Step 6: Jalankan tes — harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="RichTextComponentTest|AdminCsrfMetaTest|LayoutsTest|AdminLayoutTest|FormComponentsTest|FrontendManifestTest"
```
Expected: seluruhnya PASS (termasuk `test_no_inline_error_handler_in_scripts_or_views`: komponen tidak memuat kata `onerror`).

- [ ] **Step 7: Pint dan commit siklus A**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint --test
git add resources/views/components/form/rich-text.blade.php resources/views/components/layouts/admin.blade.php tests/Feature/View/RichTextComponentTest.php tests/Feature/Admin/AdminCsrfMetaTest.php
git commit -m "feat(fase-2): komponen x-form.rich-text dan meta csrf-token layout admin" -m "Textarea tetap berfungsi tanpa JavaScript; toolbar, modal alt text, dan kontrak data-* untuk editor.js; tanpa handler inline." -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```
Expected: Pint `PASS`; commit sukses.

**Siklus B — `editor.js`, gaya, pemuatan lazy, dan penjaga manifest**

- [ ] **Step 8: Tulis tes yang gagal — penjaga di `tests/Unit/FrontendManifestTest.php` (tool Edit)**

Sisipkan tiga metode baru **sebelum** metode `test_shared_and_admin_styles_define_the_classes_components_rely_on`. `old_string` (satu baris, tepat satu kecocokan):

```php
    public function test_shared_and_admin_styles_define_the_classes_components_rely_on(): void
```

`new_string`:

```php
    public function test_admin_entry_loads_the_rich_text_editor_lazily(): void
    {
        $admin = $this->read('resources/js/admin.js');

        $this->assertStringContainsString('[data-rich-text]', $admin);
        $this->assertStringContainsString("import('./editor.js')", $admin);
        $this->assertDoesNotMatchRegularExpression("#^import\\s+['\"]\\./editor(\\.js)?['\"];#m", $admin, 'editor.js harus dimuat lazy, bukan impor statis');
        $this->assertFileExists($this->path('resources/js/editor.js'));
    }

    public function test_editor_script_uses_quill_snow_fetch_csrf_and_no_unsafe_apis(): void
    {
        $editor = $this->read('resources/js/editor.js');

        foreach ([
            "import Quill from 'quill';",
            "import 'quill/dist/quill.snow.css';",
            'fetch(',
            'X-CSRF-TOKEN',
            'meta[name="csrf-token"]',
            'readAsDataURL',
            'getSemanticHTML',
            'data-rich-text',
            "addEventListener('submit'",
            'window.bootstrap',
        ] as $needle) {
            $this->assertStringContainsString($needle, $editor, "editor.js harus memuat {$needle}");
        }

        // CSP produksi: script-src 'self', img-src 'self' data: (tanpa blob:), tanpa penulisan HTML mentah.
        foreach (['createObjectURL', 'blob:', 'eval(', 'new Function', 'innerHTML', 'outerHTML', 'insertAdjacentHTML', 'document.write', 'onerror', 'prompt(', 'localStorage'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $editor, "editor.js tidak boleh memuat {$forbidden}");
        }
    }

    public function test_quill_snow_stylesheet_is_installed_and_admin_styles_cover_the_editor(): void
    {
        if (! is_dir($this->path('node_modules/quill'))) {
            $this->markTestSkipped('node_modules belum terpasang (jalankan npm ci).');
        }

        $this->assertFileExists($this->path('node_modules/quill/dist/quill.snow.css'));

        $admin = $this->read('resources/scss/_admin.scss');

        foreach (['.rich-text', '.ql-toolbar', '.ql-container', '.ql-tooltip', '.rich-text-preview'] as $needle) {
            $this->assertStringContainsString($needle, $admin);
        }
    }

    public function test_built_manifest_contains_the_lazy_editor_chunk_with_its_css(): void
    {
        $file = $this->path('public/build/manifest.json');

        if (! is_file($file)) {
            $this->markTestSkipped('Belum ada build Vite (public/build/manifest.json); jalankan npm run build.');
        }

        $manifest = json_decode($this->read('public/build/manifest.json'), true, flags: JSON_THROW_ON_ERROR);

        $this->assertArrayHasKey('resources/js/editor.js', $manifest, 'Build usang: jalankan npm run build.');
        $this->assertTrue($manifest['resources/js/editor.js']['isDynamicEntry'] ?? false, 'editor.js harus chunk dinamis (lazy)');
        $this->assertNotEmpty(preg_grep('/\\.css$/', $manifest['resources/js/editor.js']['css'] ?? []), 'CSS Quill snow harus ikut chunk editor');
        $this->assertContains('resources/js/editor.js', $manifest['resources/js/admin.js']['dynamicImports'] ?? []);
        $this->assertArrayNotHasKey('resources/js/editor.js', array_filter($manifest, fn (array $entry): bool => ($entry['isEntry'] ?? false) === true), 'editor.js bukan entri Vite');
    }

    public function test_shared_and_admin_styles_define_the_classes_components_rely_on(): void
```

- [ ] **Step 9: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=FrontendManifestTest
```
Expected: FAIL pada `test_admin_entry_loads_the_rich_text_editor_lazily` (belum ada `import('./editor.js')`/berkas), `test_editor_script_uses_quill_snow…` (berkas `editor.js` tidak ada), dan `test_quill_snow_stylesheet…` (gaya `.rich-text` belum ada); `test_built_manifest…` FAIL/SKIP sesuai ada tidaknya build lama (build lama tidak memuat `editor.js`, jadi FAIL dengan "Build usang").

- [ ] **Step 10: Implementasi — `resources/js/editor.js` (tool Write)**

```js
// Editor rich text (Quill 2) untuk <x-form.rich-text> (10 §3.5, F2-R2). Dimuat lazy oleh admin.js.
// Kontrak markup: [data-rich-text] > textarea[data-rich-text-input], [data-rich-text-editor],
// [data-rich-text-toolbar], [data-rich-text-surface], [data-rich-text-status], dan modal [data-rich-text-image-modal].
// CSP produksi: script-src 'self', img-src 'self' data: (tanpa URL objek lokal). Gambar sisipan selalu memakai
// URL /storage/... dari respons server; FileReader hanya untuk pratinjau di modal (data URL).
import Quill from 'quill';
import 'quill/dist/quill.snow.css';

const Delta = Quill.import('delta');

// Himpunan tag yang diizinkan purifier: h2-h4, strong, em, u, ol/ul/li, blockquote, a, img.
const FORMATS = ['header', 'bold', 'italic', 'underline', 'list', 'blockquote', 'link', 'image'];
const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
const IMAGE_MAX_BYTES = 2048 * 1024;

const TEXT = {
    fileRequired: 'Pilih berkas gambar terlebih dahulu.',
    fileType: 'File harus berupa gambar JPG, PNG, atau WebP.',
    fileSize: 'Ukuran gambar maksimal 2 MB.',
    altRequired: 'Teks alternatif gambar wajib diisi.',
    uploadFailed: 'Gambar gagal diunggah. Periksa koneksi Anda, lalu coba lagi.',
    uploading: 'Mengunggah…',
    submit: 'Unggah dan sisipkan',
    inserted: 'Gambar disisipkan.',
    useButton: 'Untuk menyisipkan gambar, gunakan tombol Sisipkan gambar pada toolbar.',
    linkPlaceholder: 'https://',
    linkLabel: 'Alamat tautan',
};

function tooLong(max) {
    return `Isi editor melebihi batas ${max} karakter. Persingkat isi atau kurangi jumlah gambar.`;
}

function csrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');

    return meta ? meta.getAttribute('content') || '' : '';
}

function isStoragePath(source) {
    return typeof source === 'string' && source.startsWith('/storage/');
}

function firstServerMessage(payload) {
    if (payload && payload.errors && typeof payload.errors === 'object') {
        for (const messages of Object.values(payload.errors)) {
            if (Array.isArray(messages) && messages.length > 0 && typeof messages[0] === 'string') {
                return messages[0];
            }
        }
    }

    if (payload && typeof payload.message === 'string' && payload.message !== '') {
        return payload.message;
    }

    return TEXT.uploadFailed;
}

// POST multipart ke admin.media.editor-upload; respons sukses {url, alt, media_id}.
async function uploadImage(url, file, altText) {
    const body = new FormData();
    body.append('file', file);
    body.append('alt_text', altText);

    let response;

    try {
        response = await fetch(url, {
            method: 'POST',
            body,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
    } catch {
        throw new Error(TEXT.uploadFailed);
    }

    let payload = null;

    try {
        payload = await response.json();
    } catch {
        payload = null;
    }

    if (!response.ok) {
        throw new Error(firstServerMessage(payload));
    }

    if (!payload || !isStoragePath(payload.url)) {
        throw new Error(TEXT.uploadFailed);
    }

    return payload;
}

function validateFile(file) {
    if (!IMAGE_TYPES.includes(file.type)) {
        return TEXT.fileType;
    }

    if (file.size > IMAGE_MAX_BYTES) {
        return TEXT.fileSize;
    }

    return null;
}

// Dialog unggah gambar (modal Bootstrap). Mengembalikan open(onInsert, onClosed).
function createImageDialog(modal, uploadUrl) {
    const fileInput = modal.querySelector('[data-rich-text-image-file]');
    const altInput = modal.querySelector('[data-rich-text-image-alt]');
    const preview = modal.querySelector('[data-rich-text-image-preview]');
    const errorBox = modal.querySelector('[data-rich-text-image-error]');
    const submitButton = modal.querySelector('[data-rich-text-image-submit]');
    const instance = window.bootstrap.Modal.getOrCreateInstance(modal);

    let onInsert = null;
    let onClosed = null;
    let readToken = 0;

    const showError = (message, field) => {
        errorBox.textContent = message;
        errorBox.classList.remove('d-none');

        [fileInput, altInput].forEach((input) => input.removeAttribute('aria-invalid'));

        if (field) {
            field.setAttribute('aria-invalid', 'true');
            field.focus();
        }
    };

    const clearError = () => {
        errorBox.textContent = '';
        errorBox.classList.add('d-none');
        [fileInput, altInput].forEach((input) => input.removeAttribute('aria-invalid'));
    };

    const hidePreview = () => {
        readToken += 1;
        preview.classList.add('d-none');
        preview.removeAttribute('src');
    };

    const setBusy = (busy) => {
        submitButton.disabled = busy;
        fileInput.disabled = busy;
        altInput.disabled = busy;
        submitButton.textContent = busy ? TEXT.uploading : TEXT.submit;

        if (busy) {
            submitButton.setAttribute('aria-busy', 'true');
        } else {
            submitButton.removeAttribute('aria-busy');
        }
    };

    fileInput.addEventListener('change', () => {
        clearError();
        hidePreview();

        const file = fileInput.files && fileInput.files[0];

        if (!file) {
            return;
        }

        const problem = validateFile(file);

        if (problem) {
            showError(problem, fileInput);

            return;
        }

        // Pratinjau saja: data URL (diizinkan img-src 'self' data:), bukan URL objek lokal.
        const token = readToken;
        const reader = new FileReader();

        reader.addEventListener('load', () => {
            if (token === readToken && typeof reader.result === 'string') {
                preview.setAttribute('src', reader.result);
                preview.classList.remove('d-none');
            }
        });
        reader.readAsDataURL(file);
    });

    submitButton.addEventListener('click', async () => {
        clearError();

        const file = fileInput.files && fileInput.files[0];

        if (!file) {
            showError(TEXT.fileRequired, fileInput);

            return;
        }

        const problem = validateFile(file);

        if (problem) {
            showError(problem, fileInput);

            return;
        }

        const altText = altInput.value.trim();

        if (altText === '') {
            showError(TEXT.altRequired, altInput);

            return;
        }

        setBusy(true);

        try {
            const payload = await uploadImage(uploadUrl, file, altText);

            if (onInsert) {
                onInsert(payload);
            }

            instance.hide();
        } catch (error) {
            showError(error instanceof Error ? error.message : TEXT.uploadFailed, null);
        } finally {
            setBusy(false);
        }
    });

    altInput.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            submitButton.click();
        }
    });

    modal.addEventListener('shown.bs.modal', () => fileInput.focus());

    modal.addEventListener('hidden.bs.modal', () => {
        fileInput.value = '';
        altInput.value = '';
        hidePreview();
        clearError();
        setBusy(false);

        const closed = onClosed;

        onInsert = null;
        onClosed = null;

        if (closed) {
            closed();
        }
    });

    return (insertHandler, closedHandler) => {
        onInsert = insertHandler;
        onClosed = closedHandler;
        instance.show();
    };
}

function initRichText(root) {
    if (root.dataset.richTextReady === '1') {
        return;
    }

    const textarea = root.querySelector('[data-rich-text-input]');
    const editor = root.querySelector('[data-rich-text-editor]');
    const toolbar = root.querySelector('[data-rich-text-toolbar]');
    const surface = root.querySelector('[data-rich-text-surface]');
    const status = root.querySelector('[data-rich-text-status]');
    const modal = root.querySelector('[data-rich-text-image-modal]');

    if (!textarea || !editor || !toolbar || !surface || !modal || !window.bootstrap) {
        return; // marka tidak lengkap: textarea biasa tetap berfungsi
    }

    root.dataset.richTextReady = '1';

    const uploadUrl = root.dataset.uploadUrl || '';
    const minHeight = Number.parseInt(root.dataset.minHeight, 10) || 240;
    const maxLength = Number.parseInt(root.dataset.maxLength, 10) || 0;
    const labelId = `${textarea.id}-label`;

    const announce = (message) => {
        if (status) {
            status.textContent = message;
        }
    };

    // Modal dipindahkan ke <body> (praktik Bootstrap); kontrolnya tanpa atribut name sehingga tidak ikut terkirim.
    document.body.append(modal);
    const openImageDialog = createImageDialog(modal, uploadUrl);

    textarea.hidden = true;
    editor.hidden = false;

    const quill = new Quill(surface, {
        theme: 'snow',
        formats: FORMATS,
        modules: {
            toolbar: {
                container: toolbar,
                handlers: {
                    // Menggantikan dialog bawaan Quill untuk embed gambar dengan modal alt text.
                    image: () => {
                        const range = quill.getSelection(true);

                        openImageDialog(
                            (payload) => {
                                quill.updateContents(
                                    new Delta().retain(range.index).delete(range.length).insert({ image: payload.url }, { alt: payload.alt }),
                                    'user',
                                );
                                quill.setSelection(range.index + 1, 0, 'silent');
                                announce(TEXT.inserted);
                            },
                            () => quill.focus(),
                        );
                    },
                },
            },
            // Tempel/seret berkas gambar tidak boleh menanam data URL; arahkan ke tombol toolbar.
            uploader: {
                mimetypes: IMAGE_TYPES,
                handler: () => announce(TEXT.useButton),
            },
            clipboard: {
                // Gambar tempelan hanya diterima bila sudah berada di /storage/ (sisanya dibuang sanitizer server).
                matchers: [['img', (node, delta) => (isStoragePath(node.getAttribute('src')) ? delta : new Delta())]],
            },
        },
    });

    quill.root.setAttribute('role', 'textbox');
    quill.root.setAttribute('aria-multiline', 'true');
    quill.root.setAttribute('aria-labelledby', labelId);
    quill.root.style.minHeight = `${minHeight}px`;

    const linkBox = surface.querySelector('.ql-tooltip input[type="text"]');

    if (linkBox) {
        linkBox.setAttribute('placeholder', TEXT.linkPlaceholder);
        linkBox.setAttribute('aria-label', TEXT.linkLabel);
    }

    const initial = textarea.value.trim();

    if (initial !== '') {
        quill.setContents(quill.clipboard.convert({ html: initial }), 'silent');
        quill.history.clear();
    }

    const serialize = () => {
        if (quill.getText().trim() === '' && quill.root.querySelector('img') === null) {
            return '';
        }

        return quill.getSemanticHTML();
    };

    const form = textarea.form;

    if (form) {
        form.addEventListener('submit', (event) => {
            const html = serialize();

            if (maxLength > 0 && html.length > maxLength) {
                event.preventDefault();
                announce(tooLong(maxLength));
                quill.focus();

                return;
            }

            textarea.value = html;
        });
    }

    // Tautan ringkasan galat (#id textarea) tidak dapat memfokuskan textarea tersembunyi.
    document.addEventListener('click', (event) => {
        const link = event.target instanceof Element ? event.target.closest('a[href^="#"]') : null;

        if (link && link.getAttribute('href') === `#${textarea.id}`) {
            event.preventDefault();
            quill.focus();
        }
    });
}

document.querySelectorAll('[data-rich-text]').forEach(initRichText);
```

- [ ] **Step 11: Implementasi — pemuatan lazy di `resources/js/admin.js` (tool Edit)**

`old_string` (akhir berkas, tepat satu kecocokan):

```js
    if (table) {
        table.setAttribute('aria-busy', 'true');
    }
});
```

`new_string`:

```js
    if (table) {
        table.setAttribute('aria-busy', 'true');
    }
});

// ---------------------------------------------------------------- Editor rich text (x-form.rich-text), dimuat lazy
if (document.querySelector('[data-rich-text]')) {
    import('./editor.js');
}
```

- [ ] **Step 12: Implementasi — gaya editor (tambahkan di akhir `resources/scss/_admin.scss`)**

Repo memakai LF (K5); skrip PowerShell ini hanya penambah di akhir berkas dan mempertahankan akhiran baris yang ada (tool Edit dengan `old_string` akhir berkas juga boleh):

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
$path = Join-Path $APP "resources\scss\_admin.scss"
$enc = New-Object System.Text.UTF8Encoding $false
$raw = [IO.File]::ReadAllText($path, $enc)
$nl = if ($raw.Contains("`r`n")) { "`r`n" } else { "`n" }
$block = @'
// Editor rich text (Quill 2 tema snow, x-form.rich-text). Gaya Quill sendiri dimuat lazy oleh editor.js.
.rich-text {
  .ql-toolbar.ql-snow {
    background: $light;
    border-color: $border-color;
    border-radius: $border-radius $border-radius 0 0;

    button:focus-visible {
      outline: 2px solid $primary;
      outline-offset: 1px;
    }
  }

  .ql-container.ql-snow {
    background: #fff;
    border-color: $border-color;
    border-radius: 0 0 $border-radius $border-radius;
    font-family: inherit;
    font-size: 1rem;
  }

  .ql-editor {
    line-height: 1.6;

    img {
      height: auto;
      max-width: 100%;
    }

    &.ql-blank::before {
      font-style: normal;
    }
  }

  .ql-editor:focus-visible {
    outline: 2px solid $primary;
    outline-offset: -2px;
  }

  // Terjemahan tooltip tautan bawaan Quill.
  .ql-snow .ql-tooltip::before {
    content: "Buka tautan:";
  }

  .ql-snow .ql-tooltip[data-mode="link"]::before {
    content: "Alamat tautan:";
  }

  .ql-snow .ql-tooltip a.ql-action::after {
    content: "Ubah";
  }

  .ql-snow .ql-tooltip.ql-editing a.ql-action::after {
    content: "Simpan";
  }

  .ql-snow .ql-tooltip a.ql-remove::before {
    content: "Hapus";
  }
}

.rich-text-preview {
  max-height: 10rem;
  max-width: 100%;
  width: auto;
}
'@
$block = $block -replace "`r?`n", $nl
[IO.File]::WriteAllText($path, $raw.TrimEnd() + $nl + $nl + $block + $nl, $enc)
Select-String -Path $path -Pattern '^\.rich-text' | Select-Object -First 2
```
Expected: dua baris (`.rich-text {` dan `.rich-text-preview {`).

- [ ] **Step 13: Jalankan tes dan periksa sintaks JS**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
node --check resources\js\editor.js
node --check resources\js\admin.js
& $PHP artisan test --filter=FrontendManifestTest
```
Expected: `node --check` tanpa keluaran (sintaks valid); `FrontendManifestTest` PASS kecuali `test_built_manifest_contains_the_lazy_editor_chunk_with_its_css` yang masih FAIL ("Build usang") sampai Step 14 (atau SKIP bila `public/build` tidak ada). Penjaga lama tetap hijau: tepat satu `addEventListener('error'`, tanpa `onerror`, urutan impor SCSS.

- [ ] **Step 14: Build Vite dan periksa manifest serta bundel**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
npm run build
$m = Get-Content public\build\manifest.json -Raw | ConvertFrom-Json
$e = $m.'resources/js/editor.js'
"editor: file=$($e.file) dynamic=$($e.isDynamicEntry) css=$($e.css -join ',')"
"admin dynamicImports: $($m.'resources/js/admin.js'.dynamicImports -join ',')"
Select-String -Path "public\build\assets\editor-*.js" -Pattern 'new Function\(|[^a-zA-Z_.]eval\(' | Select-Object -First 3
& $PHP artisan test --filter=FrontendManifestTest
```
Expected: build sukses tanpa galat (peringatan ukuran chunk boleh); `dynamic=True`; `css=` berisi satu berkas `assets/editor-*.css`; `dynamicImports` berisi `resources/js/editor.js`; `Select-String` **tanpa keluaran** (tidak ada `eval`/`new Function` di chunk editor, sejalan dengan CSP `script-src 'self'`); `FrontendManifestTest` PASS penuh (termasuk tes manifest hasil build). Bila `Select-String` menemukan sesuatu, laporkan `NEEDS_CONTEXT` beserta potongan baris sebelum melanjutkan (jangan melonggarkan CSP). `public/build` tidak di-commit.

- [ ] **Step 15: Seluruh suite, Pint, dan commit siklus B**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test
& $PHP vendor\bin\pint --test
git add resources/js/editor.js resources/js/admin.js resources/scss/_admin.scss tests/Unit/FrontendManifestTest.php
git commit -m "feat(fase-2): editor.js (Quill 2) dengan unggah gambar ber-alt dan penjaga manifest" -m "Quill snow dimuat lazy lewat admin.js; unggah via fetch + X-CSRF-TOKEN ke admin.media.editor-upload; alt wajib lewat modal; tanpa blob:, innerHTML, atau handler inline." -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```
Expected: `artisan test` hijau; Pint `PASS`; commit sukses; `git status` tidak menampilkan `public/build`.

---

### Task 8: `DoctorService`: satu-satunya jalur tulis dokter

**Files:**
- Create: `app/Services/Doctors/DoctorService.php`
- Test: `tests/Feature/Doctors/DoctorServiceTest.php`

**Interfaces:**
- Consumes:
  - `Doctor`, `Specialty`, `Clinic` + `Tests\Support\InteractsWithMasterData` (`createClinic()`, `createSpecialty()`, `createDoctor()`; Task 1), trait `HasUniqueSlug` (slug otomatis saat `creating`, Task 2) dan `HasMedia` (Task 2), `Doctor::scopePubliclyVisible()` (Task 1).
  - `AuditLogger::log(string $action, Model $model, ?array $old, ?array $new, string $description, ?Actor $actor = null)` dan `AuditLogger::CONTENT_CHANGED` (fase 1; kunci `bio`/`short_bio`/`description` otomatis menjadi "[konten diubah]").
  - `MediaService::store(UploadedFile, string $collection, ?Model $owner, array $meta, ?User $uploader)` dan `MediaService::replace(Model $owner, string $collection, UploadedFile, array $meta, ?User $uploader): Media`. `replace()` menyimpan berkas dan baris baru lebih dulu, mencatat `media.replaced`, lalu menghapus media lama **lewat `DB::afterCommit`** (setelah transaksi terluar commit); bila transaksi pemanggil di-rollback, media lama tetap utuh dan berkas baru yang sempat tertulis menjadi yatim (dibersihkan `media:prune`). Galat unggahan berupa `ValidationException` (kunci `file`, `alt_text`).
  - `Actor` (`Actor::staff($user)`, `$actor->user(): ?User`), enum `DoctorStatus` (`Active|Inactive|Archived`), `Gender` (`L`/`P`), `lang/id/messages.php` (kunci `audit.created|updated`, `entities.doctor`; Task 3).
- Produces: `App\Services\Doctors\DoctorService` (`final`, constructor `(AuditLogger $audit, MediaService $media)`; semua method tulis berjalan dalam `DB::transaction`; pelaku **selalu** dikirim eksplisit). Kunci `$data` mengikuti V-19: `name`, `title_prefix`, `title_suffix`, `gender`, `clinic_id`, `short_bio`, `bio`, `sort_order`, `meta_title`, `meta_description`, `specialty_ids` (larik id), `primary_specialty_id`. `slug`, `status`, dan `archived_at` di `$data` **selalu diabaikan**.
  - `create(array $data, Actor $actor, ?UploadedFile $photo = null, ?string $photoAlt = null): Doctor` — status `active`; `syncSpecialties`; audit `doctor.created`; foto (bila ada) lewat `MediaService::store($photo, 'photo', $doctor, ['alt_text' => $photoAlt], $actor->user())`. Gagal di bagian mana pun (spesialisasi, foto, audit) ⇒ seluruh transaksi di-rollback.
  - `update(Doctor $doctor, array $data, Actor $actor, ?UploadedFile $photo = null, ?string $photoAlt = null): Doctor` — baris dikunci (`lockForUpdate`) dan dibaca ulang; hanya atribut profil yang diisi; `specialty_ids`/`primary_specialty_id` disinkronkan **hanya bila dikirim** (keduanya harus ada bersama); audit `doctor.updated` hanya bila ada perubahan; foto (bila ada) lewat `MediaService::replace`.
  - `syncSpecialties(Doctor $doctor, array $specialtyIds, int $primaryId): void` (09 I-5). Hanya memeriksa id **ada** (bukan `is_active`): spesialisasi nonaktif yang sudah terpasang tetap diterima saat ubah (K4); syarat "aktif atau sudah terpasang" dan pesan V-19 ditegakkan `UpdateDoctorRequest` (Task 11). Sama untuk `clinic_id` pada `update()`.
  - `changeStatus(Doctor $doctor, DoctorStatus $status, ?string $reason, Actor $actor): Doctor` — `active`↔`inactive` serta pemulihan `archived` → `inactive` (F2-R7, `archived_at` dikosongkan); audit `doctor.status_changed`; status yang sama = tanpa perubahan dan tanpa audit.
  - `archive(Doctor $doctor, ?string $reason, Actor $actor): Doctor` — `archived` + `archived_at = now()`; audit `doctor.archived`.
  - `archiveBlockedReason(Doctor $doctor): ?string` (**publik, tambahan atas kontrak**) — pesan penolakan arsip atau `null`; dipakai `ArchiveDoctorRequest` (Task 12) dan `archive()` sendiri, sehingga aturan juga berlaku bagi Super Admin.
  - `replacePhoto(Doctor $doctor, UploadedFile $photo, string $alt, Actor $actor): Media`.
  - `protected function futureActiveAppointmentsCount(Doctor $doctor): int` — selalu `0` di fase 2 (F2-R8); fase 3 mengganti isinya dengan hitungan appointment aktif pada sesi mendatang.
- **Tipe galat (untuk Task 11/12):** semua penolakan aturan berupa `Illuminate\Validation\ValidationException` berpesan Indonesia (dapat ditangkap Form Request/controller); penyalahgunaan API berupa `InvalidArgumentException`.

| Kunci galat | Pesan | Sumber |
|---|---|---|
| `specialty_ids` | "Pilih minimal satu spesialisasi." | 12 BR-02/V-19 |
| `specialty_ids` | "Spesialisasi maksimal 5." | 12 V-19 |
| `specialty_ids` | "Spesialisasi yang dipilih tidak tersedia." | 12 V-19 (id tidak ada) |
| `primary_specialty_id` | "Spesialisasi utama wajib dipilih." (kunci tidak dikirim) | 12 V-19 |
| `primary_specialty_id` | "Spesialisasi utama harus salah satu spesialisasi yang dipilih." | 12 BR-02/V-19 |
| `status` | "Dokter yang diarsipkan hanya dapat dipulihkan menjadi nonaktif." | F2-R7 |
| `status` | "Dokter sudah diarsipkan." | **ASUMSI** (arsip ulang) |
| `status` | "Dokter masih memiliki {N} pendaftaran aktif pada sesi mendatang. Selesaikan sesi terdampak terlebih dahulu, lalu arsipkan dokter." | 12 V-20/BR-05 (dipakai fase 3) |
| `file` / `alt_text` | pesan baku `MediaService` | fase 1 |
| `InvalidArgumentException` | "Gunakan archive() untuk mengarsipkan dokter." (`changeStatus` ke `Archived`); "specialty_ids dan primary_specialty_id harus dikirim bersama." (`update`) | internal |

**Aksi audit (12 §8.2, nama persis) dan bentuk payload:**

| Aksi | `old_values` → `new_values` | Catatan |
|---|---|---|
| `doctor.created` | `null` → `{"name","clinic_id","status":"active","specialty_ids":[…]}` | `specialty_ids` terurut naik; `bio` tidak dicatat |
| `doctor.updated` | atribut yang berubah (nilai lama → baru); ditambah `specialty_ids` bila himpunan berubah dan `primary_specialty_id` bila yang utama berubah | `bio`/`short_bio` → "[konten diubah]" (nama bidang saja); tanpa perubahan = tanpa baris audit |
| `doctor.status_changed` | `{"status":"active"}` → `{"status":"inactive"}`; pemulihan: `{"status":"archived","archived_at":"…"}` → `{"status":"inactive","archived_at":null}`; `reason` ikut di `new_values` bila diisi | bagian `impact` (sesi dibatalkan/`needs_review`) baru ada di fase 3 |
| `doctor.archived` | `{"status":"active","archived_at":null}` → `{"status":"archived","archived_at":"Y-m-d H:i:s"}` (+ `reason`) | jumlah templat/sesi terdampak ditambah fase 3; ditolak = **tanpa** baris audit |
| `media.uploaded` / `media.replaced` | dicatat `MediaService` | foto |

**Test IDs:** CRD-03 (bagian status/arsip/pemulihan), AUD (`doctor.*`), MED-01/MED-02 (tingkat service: simpan dan ganti foto, berkas lama terhapus setelah commit), Invarian I-5 (K-5) dan I-23 (K-23), Review Focus 1 (slug tidak berubah saat nama diedit).

**Catatan teknis (diverifikasi dari source):**
- `Model::shouldBeStrict()` melarang *lazy load*: service membaca pivot lewat `DB::table('doctor_specialties')` dan `Specialty::query()->count()`, tidak lewat properti relasi. `syncSpecialties()` memanggil `$doctor->unsetRelation('specialties')` agar relasi yang sudah dimuat tidak basi. `displayName` hanya memakai atribut, aman tanpa eager load.
- `Doctor::specialties()->sync([id => ['is_primary' => bool]])` menulis `is_primary` untuk baris baru **dan** memperbarui baris lama (pivot tanpa timestamps, PK komposit). Tepat satu baris utama dijamin karena payload dibangun dari `$primaryId`; invarian dicek tes dengan kueri K-5 (09 §12).
- Pembacaan ulang baris: `lock()` mengambil `Doctor::query()->whereKey()->lockForUpdate()->firstOrFail()` lalu `setRawAttributes($fresh->getRawOriginal(), true)` pada instance pemanggil. Dengan begitu instance basi (dimuat sebelum perubahan lain) tidak menimpa status terbaru; tes `test_stale_instance…` membuktikannya.
- Diff `update()` dihitung dari `getDirty()` sebelum `save()` dan `getRawOriginal()`, **bukan** `AuditLogger::diff()`: pada instance yang baru saja disimpan, `getChanges()` masih berisi perubahan sebelumnya dan akan menghasilkan diff yang salah.
- `DoctorService` bersifat `final` (kontrak). Konsekuensinya `futureActiveAppointmentsCount()` (protected) tidak dapat disubklaskan atau di-mock di tes; jalur "arsip ditolak karena N pendaftaran aktif" belum bisa dijalankan end-to-end di fase 2. Yang diuji sekarang: method itu bernilai `0`, `archiveBlockedReason()` bernilai `null`, dan teks penolakan (`blockedMessage()`, private static, lewat `ReflectionMethod`). Tes end-to-end (appointment nyata) ditambahkan fase 3 saat isi method diganti.
- `AuditLog::creating(fn () => throw new RuntimeException(...))` dipakai menyimulasikan kegagalan audit (kelas `AuditLogger` `final`, tidak dapat di-mock); dispatcher event model dibuat ulang per tes. Jangan memanggil `$this->fail()` di dalam `try` yang menangkap `RuntimeException` (`AssertionFailedError` adalah `RuntimeException`): tes memakai bendera boolean.
- Tes memakai `Tests\Support\InteractsWithMasterData` + `InteractsWithStaff`; bila PHP melaporkan tabrakan metode trait, hapus `use InteractsWithStaff;` (lihat catatan Task 6). Tes sengaja **tidak** memakai argumen `$specialties` milik `createDoctor()` (bentuk elemennya tidak ditetapkan kontrak); dokter uji dibuat lewat `DoctorService::create()` agar invarian I-5 terjaga.

- [ ] **Step 1: Verifikasi prasyarat**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git branch --show-current
git status --short
Test-Path app\Models\Doctor.php
Test-Path app\Models\Concerns\HasUniqueSlug.php
Test-Path tests\Support\InteractsWithMasterData.php
Test-Path lang\id\messages.php
& $PHP artisan test --filter="MediaServiceLifecycleTest|AuditLoggerTest"
```
Expected: `fase-2-master-data`; `git status` bersih; empat `True`; tes PASS. Folder `app/Services/Doctors` dan `tests/Feature/Doctors` belum ada (Write membuatnya).

**Siklus A — `syncSpecialties()` dan `create()`**

- [ ] **Step 2: Tulis tes yang gagal (tool Write) `tests/Feature/Doctors/DoctorServiceTest.php`**

Berkas ini diperluas pada siklus B dan C (setiap siklus menambah method tes sebelum `}` penutup lewat tool Edit dengan anchor `    // ---- akhir siklus` yang disediakan di sini).

```php
<?php

namespace Tests\Feature\Doctors;

use App\Enums\ActorType;
use App\Enums\DoctorStatus;
use App\Models\AuditLog;
use App\Models\Doctor;
use App\Models\Media;
use App\Models\User;
use App\Services\Doctors\DoctorService;
use App\Support\Actor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;
use Tests\Support\ImageFixtures;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

class DoctorServiceTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithStaff;
    use RefreshDatabase;

    private DoctorService $service;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->service = $this->app->make(DoctorService::class);
        $this->staff = $this->createStaff('content-admin');
    }

    // ------------------------------------------------------------------ pembantu

    private function actor(): Actor
    {
        return Actor::staff($this->staff);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        $clinic = $this->createClinic();
        $first = $this->createSpecialty();
        $second = $this->createSpecialty();

        return array_merge([
            'name' => 'Andi Pratama',
            'title_prefix' => 'dr.',
            'title_suffix' => 'Sp.A',
            'gender' => 'L',
            'clinic_id' => $clinic->id,
            'short_bio' => 'Dokter spesialis anak.',
            'bio' => 'Biodata lengkap dokter.',
            'sort_order' => 5,
            'meta_title' => null,
            'meta_description' => null,
            'specialty_ids' => [$first->id, $second->id],
            'primary_specialty_id' => $second->id,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeDoctor(array $overrides = []): Doctor
    {
        return $this->service->create($this->payload($overrides), $this->actor());
    }

    /**
     * @return list<int>
     */
    private function pivotIds(Doctor $doctor): array
    {
        return DB::table('doctor_specialties')->where('doctor_id', $doctor->id)->orderBy('specialty_id')->pluck('specialty_id')->map(fn ($id) => (int) $id)->all();
    }

    private function pivotPrimary(Doctor $doctor): ?int
    {
        $id = DB::table('doctor_specialties')->where('doctor_id', $doctor->id)->where('is_primary', 1)->value('specialty_id');

        return $id === null ? null : (int) $id;
    }

    private function assertInvariants(): void
    {
        // K-5 (I-5): tepat satu spesialisasi utama per dokter. K-23 (I-23): archived <=> archived_at terisi.
        $this->assertSame([], DB::select('SELECT d.id FROM doctors d LEFT JOIN doctor_specialties ds ON ds.doctor_id = d.id AND ds.is_primary = 1 GROUP BY d.id HAVING COUNT(ds.doctor_id) <> 1'));
        $this->assertSame([], DB::select("SELECT id FROM doctors WHERE (status = 'archived') <> (archived_at IS NOT NULL)"));
    }

    private function assertRejected(callable $callback, string $key, string $message): void
    {
        try {
            $callback();
        } catch (ValidationException $e) {
            $this->assertSame([$message], $e->errors()[$key] ?? null);

            return;
        }

        $this->fail('ValidationException tidak dilempar.');
    }

    private function auditRows(string $action): \Illuminate\Database\Eloquent\Collection
    {
        return AuditLog::query()->where('action', $action)->orderBy('id')->get();
    }

    // ------------------------------------------------------------------ bentuk kelas

    public function test_service_is_final_and_resolvable_with_the_documented_constructor(): void
    {
        $class = new ReflectionClass(DoctorService::class);

        $this->assertTrue($class->isFinal());
        $this->assertSame(['audit', 'media'], array_map(fn ($parameter) => $parameter->getName(), $class->getConstructor()->getParameters()));
    }

    // ------------------------------------------------------------------ create

    public function test_create_makes_an_active_doctor_with_automatic_slug_and_ignores_status_slug_and_archived_at(): void
    {
        $data = $this->payload(['status' => 'archived', 'slug' => 'diretas', 'archived_at' => now()]);

        $doctor = $this->service->create($data, $this->actor());
        $fresh = Doctor::query()->findOrFail($doctor->id);

        $this->assertSame(DoctorStatus::Active, $fresh->status);
        $this->assertNull($fresh->archived_at);
        $this->assertSame('andi-pratama', $fresh->slug);
        $this->assertSame('Andi Pratama', $fresh->name);
        $this->assertSame('dr. Andi Pratama, Sp.A', $fresh->displayName);
        $this->assertSame(5, $fresh->sort_order);
        $this->assertSame($data['clinic_id'], $fresh->clinic_id);
        $this->assertInvariants();
    }

    public function test_create_stores_specialties_with_exactly_the_requested_primary(): void
    {
        $data = $this->payload();

        $doctor = $this->service->create($data, $this->actor());

        $ids = $data['specialty_ids'];
        sort($ids);
        $this->assertSame($ids, $this->pivotIds($doctor));
        $this->assertSame($data['primary_specialty_id'], $this->pivotPrimary($doctor));
        $this->assertSame(1, DB::table('doctor_specialties')->where('doctor_id', $doctor->id)->where('is_primary', 1)->count());
    }

    public function test_create_defaults_sort_order_and_normalises_blank_optional_texts(): void
    {
        $doctor = $this->service->create($this->payload(['sort_order' => null, 'title_prefix' => '  ', 'short_bio' => '', 'name' => '  Budi Santoso  ']), $this->actor());
        $fresh = Doctor::query()->findOrFail($doctor->id);

        $this->assertSame(0, $fresh->sort_order);
        $this->assertNull($fresh->title_prefix);
        $this->assertNull($fresh->short_bio);
        $this->assertSame('Budi Santoso', $fresh->name);
    }

    public function test_two_doctors_with_the_same_name_get_distinct_slugs(): void
    {
        $first = $this->makeDoctor();
        $second = $this->makeDoctor();

        $this->assertSame('andi-pratama', $first->slug);
        $this->assertSame('andi-pratama-2', $second->slug);
    }

    public function test_create_writes_the_doctor_created_audit_row_with_the_documented_shape(): void
    {
        $data = $this->payload();

        $doctor = $this->service->create($data, $this->actor());

        $log = $this->auditRows('doctor.created')->sole();
        $ids = $data['specialty_ids'];
        sort($ids);

        $this->assertSame(ActorType::Staff, $log->actor_type);
        $this->assertSame($this->staff->id, (int) $log->user_id);
        $this->assertSame((new Doctor)->getMorphClass(), $log->auditable_type);
        $this->assertSame($doctor->id, (int) $log->auditable_id);
        $this->assertNull($log->old_values);
        $this->assertEquals([
            'name' => 'Andi Pratama',
            'clinic_id' => $data['clinic_id'],
            'status' => 'active',
            'specialty_ids' => $ids,
        ], $log->new_values);
        $this->assertStringContainsString('dr. Andi Pratama, Sp.A', $log->description);
    }

    public function test_create_with_a_photo_stores_it_for_the_doctor_with_alt_text_and_uploader(): void
    {
        $doctor = $this->service->create($this->payload(), $this->actor(), ImageFixtures::jpeg(800, 800), 'Foto dr. Andi');

        $media = Media::query()->where('collection', 'photo')->sole();

        $this->assertSame((new Doctor)->getMorphClass(), $media->model_type);
        $this->assertSame($doctor->id, (int) $media->model_id);
        $this->assertSame('Foto dr. Andi', $media->alt_text);
        $this->assertSame($this->staff->id, (int) $media->uploaded_by);
        Storage::disk('public')->assertExists($media->directory.'/'.$media->file_name);
        Storage::disk('public')->assertExists($media->thumb_path);
        $this->assertSame(1, $this->auditRows('doctor.created')->count());
        $this->assertSame(1, $this->auditRows('media.uploaded')->count());
    }

    public function test_create_is_rolled_back_completely_when_the_photo_is_rejected(): void
    {
        $this->assertRejected(
            fn () => $this->service->create($this->payload(), $this->actor(), ImageFixtures::corruptPng(600, 600), 'Rusak'),
            'file',
            'File tidak dapat diproses sebagai gambar yang valid.',
        );

        $this->assertRejected(
            fn () => $this->service->create($this->payload(), $this->actor(), ImageFixtures::jpeg(200, 200), 'Terlalu kecil'),
            'file',
            'Dimensi gambar minimal 300 x 300 piksel dan maksimal 6000 x 6000 piksel.',
        );

        $this->assertRejected(
            fn () => $this->service->create($this->payload(), $this->actor(), ImageFixtures::jpeg(800, 800), null),
            'alt_text',
            'Teks alternatif gambar wajib diisi.',
        );

        $this->assertSame(0, Doctor::query()->count());
        $this->assertSame(0, DB::table('doctor_specialties')->count());
        $this->assertSame(0, Media::query()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame(0, $this->auditRows('doctor.created')->count());
    }

    public function test_create_is_rolled_back_when_the_audit_row_cannot_be_written(): void
    {
        $data = $this->payload();

        AuditLog::creating(fn () => throw new RuntimeException('audit gagal'));

        $thrown = false;

        try {
            $this->service->create($data, $this->actor());
        } catch (RuntimeException) {
            $thrown = true;
        }

        $this->assertTrue($thrown);
        $this->assertSame(0, Doctor::query()->count());
        $this->assertSame(0, DB::table('doctor_specialties')->count());
    }

    public function test_create_validates_specialties_before_writing_anything(): void
    {
        $extra = array_map(fn () => $this->createSpecialty()->id, range(1, 4));
        $base = $this->payload();
        [$one, $two] = $base['specialty_ids'];

        $this->assertRejected(fn () => $this->service->create(array_merge($base, ['specialty_ids' => []]), $this->actor()), 'specialty_ids', 'Pilih minimal satu spesialisasi.');
        $this->assertRejected(fn () => $this->service->create(array_merge($base, ['specialty_ids' => [$one, $two, ...$extra]]), $this->actor()), 'specialty_ids', 'Spesialisasi maksimal 5.');
        $this->assertRejected(fn () => $this->service->create(array_merge($base, ['specialty_ids' => [$one, 999999], 'primary_specialty_id' => $one]), $this->actor()), 'specialty_ids', 'Spesialisasi yang dipilih tidak tersedia.');
        $this->assertRejected(fn () => $this->service->create(array_merge($base, ['primary_specialty_id' => $extra[0]]), $this->actor()), 'primary_specialty_id', 'Spesialisasi utama harus salah satu spesialisasi yang dipilih.');

        $withoutPrimary = $base;
        unset($withoutPrimary['primary_specialty_id']);
        $this->assertRejected(fn () => $this->service->create($withoutPrimary, $this->actor()), 'primary_specialty_id', 'Spesialisasi utama wajib dipilih.');

        $this->assertSame(0, Doctor::query()->count());
    }

    // ------------------------------------------------------------------ syncSpecialties

    public function test_sync_specialties_replaces_the_set_keeps_one_primary_and_ignores_duplicates(): void
    {
        $doctor = $this->makeDoctor();
        $a = $this->createSpecialty();
        $b = $this->createSpecialty();

        $this->service->syncSpecialties($doctor, [$a->id, $b->id, $b->id], $a->id);

        $this->assertSame([$a->id, $b->id], $this->pivotIds($doctor));
        $this->assertSame($a->id, $this->pivotPrimary($doctor));

        $this->service->syncSpecialties($doctor, [$b->id, $a->id], $b->id);

        $this->assertSame($b->id, $this->pivotPrimary($doctor));
        $this->assertSame(1, DB::table('doctor_specialties')->where('doctor_id', $doctor->id)->where('is_primary', 1)->count());

        $this->service->syncSpecialties($doctor, [$b->id], $b->id);

        $this->assertSame([$b->id], $this->pivotIds($doctor));
        $this->assertInvariants();
    }

    public function test_sync_specialties_accepts_inactive_specialties_already_attached(): void
    {
        // K4: service hanya memeriksa keberadaan; syarat "aktif atau sudah terpasang" ada di Form Request (Task 11).
        $doctor = $this->makeDoctor();
        $inactive = $this->createSpecialty(['is_active' => false]);
        $active = $this->createSpecialty();

        $this->service->syncSpecialties($doctor, [$inactive->id, $active->id], $inactive->id);

        $this->assertSame([$inactive->id, $active->id], $this->pivotIds($doctor));
        $this->assertSame($inactive->id, $this->pivotPrimary($doctor));
        $this->assertInvariants();
    }

    public function test_sync_specialties_rejects_invalid_input_and_leaves_the_pivot_untouched(): void
    {
        $doctor = $this->makeDoctor();
        $before = $this->pivotIds($doctor);
        $primary = $this->pivotPrimary($doctor);
        $other = $this->createSpecialty();

        $this->assertRejected(fn () => $this->service->syncSpecialties($doctor, [], 1), 'specialty_ids', 'Pilih minimal satu spesialisasi.');
        $this->assertRejected(fn () => $this->service->syncSpecialties($doctor, [$before[0]], $other->id), 'primary_specialty_id', 'Spesialisasi utama harus salah satu spesialisasi yang dipilih.');

        $this->assertSame($before, $this->pivotIds($doctor));
        $this->assertSame($primary, $this->pivotPrimary($doctor));
    }

    // ---- akhir siklus A
}
```

- [ ] **Step 3: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=DoctorServiceTest
```
Expected: FAIL — kelas `App\Services\Doctors\DoctorService` belum ada (`Target class [App\Services\Doctors\DoctorService] does not exist`).

- [ ] **Step 4: Implementasi (tool Write) `app/Services/Doctors/DoctorService.php`**

```php
<?php

declare(strict_types=1);

namespace App\Services\Doctors;

use App\Enums\DoctorStatus;
use App\Models\Doctor;
use App\Models\Specialty;
use App\Services\Audit\AuditLogger;
use App\Services\Media\MediaService;
use App\Support\Actor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Satu-satunya jalur tulis dokter (F2-R20, 09 I-5/I-23): buat, ubah, spesialisasi, status, arsip, dan foto,
 * masing-masing dalam DB::transaction dengan audit eksplisit (Doctor tidak diobservasi AuditObserver, F2-R19).
 */
final class DoctorService
{
    public const MAX_SPECIALTIES = 5;

    /** Atribut profil yang boleh diisi dari input; slug, status, dan archived_at dikelola sistem. */
    private const PROFILE_ATTRIBUTES = [
        'clinic_id', 'name', 'title_prefix', 'title_suffix', 'gender', 'short_bio', 'bio',
        'sort_order', 'meta_title', 'meta_description',
    ];

    /** Kolom nullable: string kosong disimpan sebagai NULL. */
    private const NULLABLE_ATTRIBUTES = ['title_prefix', 'title_suffix', 'short_bio', 'bio', 'meta_title', 'meta_description'];

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly MediaService $media,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, Actor $actor, ?UploadedFile $photo = null, ?string $photoAlt = null): Doctor
    {
        [$specialtyIds, $primaryId] = $this->requireSpecialtyInput($data);
        $this->validatedSpecialtyIds($specialtyIds, $primaryId);

        return DB::transaction(function () use ($data, $actor, $photo, $photoAlt, $specialtyIds, $primaryId): Doctor {
            $doctor = new Doctor($this->profileAttributes($data));
            $doctor->status = DoctorStatus::Active;
            $doctor->save();

            $this->syncSpecialties($doctor, $specialtyIds, $primaryId);

            $sorted = $this->validatedSpecialtyIds($specialtyIds, $primaryId);
            sort($sorted);

            $this->audit->log(
                'doctor.created',
                $doctor,
                null,
                [
                    'name' => $doctor->name,
                    'clinic_id' => $doctor->clinic_id,
                    'status' => DoctorStatus::Active->value,
                    'specialty_ids' => $sorted,
                ],
                $this->describe('created', $doctor),
                $actor,
            );

            if ($photo !== null) {
                $this->media->store($photo, 'photo', $doctor, ['alt_text' => $photoAlt], $actor->user());
            }

            return $doctor;
        });
    }

    /**
     * Ganti seluruh spesialisasi dokter dengan tepat satu yang utama (09 I-5).
     *
     * @param  array<int, int|string>  $specialtyIds
     */
    public function syncSpecialties(Doctor $doctor, array $specialtyIds, int $primaryId): void
    {
        $ids = $this->validatedSpecialtyIds($specialtyIds, $primaryId);

        DB::transaction(function () use ($doctor, $ids, $primaryId): void {
            $payload = [];

            foreach ($ids as $id) {
                $payload[$id] = ['is_primary' => $id === $primaryId];
            }

            $doctor->specialties()->sync($payload);
        });

        $doctor->unsetRelation('specialties');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function profileAttributes(array $data): array
    {
        $attributes = Arr::only($data, self::PROFILE_ATTRIBUTES);

        foreach ($attributes as $key => $value) {
            if (is_string($value)) {
                $value = trim($value);
            }

            if ($value === '' && in_array($key, self::NULLABLE_ATTRIBUTES, true)) {
                $value = null;
            }

            $attributes[$key] = $value;
        }

        // sort_order kosong = biarkan nilai bawaan kolom (0) atau nilai yang sudah ada.
        if (array_key_exists('sort_order', $attributes) && ($attributes['sort_order'] === null || $attributes['sort_order'] === '')) {
            unset($attributes['sort_order']);
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: list<int>, 1: int}
     */
    private function requireSpecialtyInput(array $data): array
    {
        $ids = array_values(array_map('intval', (array) ($data['specialty_ids'] ?? [])));

        if (! array_key_exists('primary_specialty_id', $data) || $data['primary_specialty_id'] === null || $data['primary_specialty_id'] === '') {
            if ($ids === []) {
                throw ValidationException::withMessages(['specialty_ids' => 'Pilih minimal satu spesialisasi.']);
            }

            throw ValidationException::withMessages(['primary_specialty_id' => 'Spesialisasi utama wajib dipilih.']);
        }

        return [$ids, (int) $data['primary_specialty_id']];
    }

    /**
     * Validasi aturan 09 I-5 / 12 BR-02; mengembalikan id unik dengan urutan masukan.
     *
     * @param  array<int, int|string>  $specialtyIds
     * @return list<int>
     */
    private function validatedSpecialtyIds(array $specialtyIds, int $primaryId): array
    {
        $ids = array_values(array_unique(array_map('intval', $specialtyIds)));

        if ($ids === []) {
            throw ValidationException::withMessages(['specialty_ids' => 'Pilih minimal satu spesialisasi.']);
        }

        if (count($ids) > self::MAX_SPECIALTIES) {
            throw ValidationException::withMessages(['specialty_ids' => 'Spesialisasi maksimal '.self::MAX_SPECIALTIES.'.']);
        }

        if (! in_array($primaryId, $ids, true)) {
            throw ValidationException::withMessages(['primary_specialty_id' => 'Spesialisasi utama harus salah satu spesialisasi yang dipilih.']);
        }

        if (Specialty::query()->whereIn('id', $ids)->count() !== count($ids)) {
            throw ValidationException::withMessages(['specialty_ids' => 'Spesialisasi yang dipilih tidak tersedia.']);
        }

        return $ids;
    }

    private function describe(string $event, Doctor $doctor): string
    {
        return __('messages.audit.'.$event, [
            'entity' => __('messages.entities.doctor'),
            'name' => $doctor->displayName,
        ]);
    }
}
```

- [ ] **Step 5: Jalankan tes — harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=DoctorServiceTest
```
Expected: seluruhnya PASS. Bila `test_two_doctors_with_the_same_name…` gagal, periksa `HasUniqueSlug` (Task 2), bukan service. Bila `test_create_stores_specialties…` gagal karena `Doctor::specialties()` tidak memakai `withPivot('is_primary')`, perbaiki relasi (Task 1).

- [ ] **Step 6: Pint dan commit siklus A**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint --test
git add app/Services/Doctors/DoctorService.php tests/Feature/Doctors/DoctorServiceTest.php
git commit -m "feat(fase-2): DoctorService create dan syncSpecialties" -m "Dokter baru selalu active dengan slug otomatis; tepat satu spesialisasi utama (I-5); foto lewat MediaService; audit doctor.created; seluruh transaksi di-rollback bila foto atau audit gagal." -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```
Expected: Pint `PASS` (bila melapor berkas, jalankan `& $PHP vendor\bin\pint <berkas>` lalu ulangi `--test`); commit sukses.

**Siklus B — `update()` dan `replacePhoto()`**

- [ ] **Step 7: Tambah tes yang gagal (tool Edit pada `tests/Feature/Doctors/DoctorServiceTest.php`)**

`old_string` (satu baris, tepat satu kecocokan):

```php
    // ---- akhir siklus A
```

`new_string`:

```php
    // ------------------------------------------------------------------ update

    public function test_update_changes_the_profile_but_never_slug_status_or_archived_at(): void
    {
        $doctor = $this->makeDoctor();

        $updated = $this->service->update($doctor, [
            'name' => 'Budi Santoso',
            'slug' => 'ganti-slug',
            'status' => 'inactive',
            'archived_at' => now(),
            'title_suffix' => '',
        ], $this->actor());

        $fresh = Doctor::query()->findOrFail($doctor->id);

        $this->assertSame($fresh->id, $updated->id);
        $this->assertSame('Budi Santoso', $fresh->name);
        $this->assertSame('andi-pratama', $fresh->slug, 'slug tidak berubah saat nama diedit (BR-15)');
        $this->assertSame(DoctorStatus::Active, $fresh->status);
        $this->assertNull($fresh->archived_at);
        $this->assertNull($fresh->title_suffix, 'string kosong pada kolom nullable menjadi NULL');
        $this->assertSame('Budi Santoso', $updated->name);
    }

    public function test_update_audit_contains_only_changed_attributes_and_redacts_long_texts(): void
    {
        $doctor = $this->makeDoctor();

        $this->service->update($doctor, ['name' => 'Budi Santoso', 'title_suffix' => 'Sp.PD', 'bio' => 'Biodata baru.', 'short_bio' => 'Dokter spesialis anak.'], $this->actor());

        $log = $this->auditRows('doctor.updated')->sole();

        $this->assertSame($doctor->id, (int) $log->auditable_id);
        $this->assertSame($this->staff->id, (int) $log->user_id);
        $this->assertEquals(['name' => 'Andi Pratama', 'title_suffix' => 'Sp.A', 'bio' => '[konten diubah]'], $log->old_values);
        $this->assertEquals(['name' => 'Budi Santoso', 'title_suffix' => 'Sp.PD', 'bio' => '[konten diubah]'], $log->new_values);
        $this->assertStringContainsString('Budi Santoso', $log->description);
    }

    public function test_update_without_any_change_writes_no_audit_row(): void
    {
        $doctor = $this->makeDoctor();

        $this->service->update($doctor, ['name' => 'Andi Pratama', 'sort_order' => '5', 'clinic_id' => (string) $doctor->clinic_id, 'gender' => 'L'], $this->actor());

        $this->assertSame(0, $this->auditRows('doctor.updated')->count());
    }

    public function test_update_records_a_changed_specialty_set_and_primary(): void
    {
        $doctor = $this->makeDoctor();
        $before = $this->pivotIds($doctor);
        $oldPrimary = $this->pivotPrimary($doctor);
        $third = $this->createSpecialty();

        $this->service->update($doctor, ['specialty_ids' => [$before[0], $third->id], 'primary_specialty_id' => $third->id], $this->actor());

        $expected = [$before[0], $third->id];
        sort($expected);

        $this->assertSame($expected, $this->pivotIds($doctor));
        $this->assertSame($third->id, $this->pivotPrimary($doctor));

        $log = $this->auditRows('doctor.updated')->sole();

        $this->assertEquals(['specialty_ids' => $before, 'primary_specialty_id' => $oldPrimary], $log->old_values);
        $this->assertEquals(['specialty_ids' => $expected, 'primary_specialty_id' => $third->id], $log->new_values);
        $this->assertInvariants();
    }

    public function test_update_records_only_the_primary_when_just_the_primary_changes(): void
    {
        $doctor = $this->makeDoctor();
        $ids = $this->pivotIds($doctor);
        $oldPrimary = $this->pivotPrimary($doctor);
        $newPrimary = $ids[0] === $oldPrimary ? $ids[1] : $ids[0];

        $this->service->update($doctor, ['specialty_ids' => $ids, 'primary_specialty_id' => $newPrimary], $this->actor());

        $log = $this->auditRows('doctor.updated')->sole();

        $this->assertEquals(['primary_specialty_id' => $oldPrimary], $log->old_values);
        $this->assertEquals(['primary_specialty_id' => $newPrimary], $log->new_values);
    }

    public function test_update_records_a_clinic_change(): void
    {
        $doctor = $this->makeDoctor();
        $oldClinic = $doctor->clinic_id;
        $newClinic = $this->createClinic();

        $this->service->update($doctor, ['clinic_id' => $newClinic->id], $this->actor());

        $log = $this->auditRows('doctor.updated')->sole();

        $this->assertEquals(['clinic_id' => $oldClinic], $log->old_values);
        $this->assertEquals(['clinic_id' => $newClinic->id], $log->new_values);
        $this->assertSame($newClinic->id, Doctor::query()->findOrFail($doctor->id)->clinic_id);
    }

    public function test_update_accepts_an_inactive_clinic_and_inactive_specialties_already_attached(): void
    {
        // K4: service tidak menolak yang nonaktif; syarat "aktif atau sudah terpasang" ada di UpdateDoctorRequest.
        $inactiveClinic = $this->createClinic(['is_active' => false]);
        $inactiveSpecialty = $this->createSpecialty(['is_active' => false]);
        $doctor = $this->makeDoctor(['clinic_id' => $inactiveClinic->id, 'specialty_ids' => [$inactiveSpecialty->id], 'primary_specialty_id' => $inactiveSpecialty->id]);

        $updated = $this->service->update($doctor, [
            'name' => 'Nama Baru',
            'clinic_id' => $inactiveClinic->id,
            'specialty_ids' => [$inactiveSpecialty->id],
            'primary_specialty_id' => $inactiveSpecialty->id,
        ], $this->actor());

        $this->assertSame($inactiveClinic->id, $updated->clinic_id);
        $this->assertSame([$inactiveSpecialty->id], $this->pivotIds($doctor));
        $this->assertInvariants();
    }

    public function test_update_leaves_specialties_alone_when_they_are_not_sent_and_requires_both_keys_together(): void
    {
        $doctor = $this->makeDoctor();
        $before = $this->pivotIds($doctor);

        $this->service->update($doctor, ['name' => 'Nama Baru'], $this->actor());

        $this->assertSame($before, $this->pivotIds($doctor));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('specialty_ids dan primary_specialty_id harus dikirim bersama.');

        $this->service->update($doctor, ['specialty_ids' => $before], $this->actor());
    }

    public function test_update_with_invalid_specialties_changes_nothing(): void
    {
        $doctor = $this->makeDoctor();

        $this->assertRejected(
            fn () => $this->service->update($doctor, ['name' => 'Tidak Tersimpan', 'specialty_ids' => [], 'primary_specialty_id' => 1], $this->actor()),
            'specialty_ids',
            'Pilih minimal satu spesialisasi.',
        );

        $this->assertSame('Andi Pratama', Doctor::query()->findOrFail($doctor->id)->name);
        $this->assertSame(0, $this->auditRows('doctor.updated')->count());
    }

    public function test_update_is_rolled_back_when_the_audit_row_cannot_be_written(): void
    {
        $doctor = $this->makeDoctor();

        AuditLog::creating(fn () => throw new RuntimeException('audit gagal'));

        $thrown = false;

        try {
            $this->service->update($doctor, ['name' => 'Tidak Tersimpan'], $this->actor());
        } catch (RuntimeException) {
            $thrown = true;
        }

        $this->assertTrue($thrown);
        $this->assertSame('Andi Pratama', Doctor::query()->findOrFail($doctor->id)->name);
    }

    public function test_update_replaces_the_photo_and_removes_the_old_files_after_commit(): void
    {
        $doctor = $this->service->create($this->payload(), $this->actor(), ImageFixtures::jpeg(800, 800), 'Foto lama');
        $old = Media::query()->where('collection', 'photo')->sole();
        $oldPaths = [$old->directory.'/'.$old->file_name, $old->thumb_path];

        $this->service->update($doctor, [], $this->actor(), ImageFixtures::png(700, 700), 'Foto baru');

        $new = Media::query()->where('collection', 'photo')->sole();

        $this->assertNotSame($old->id, $new->id);
        $this->assertSame('Foto baru', $new->alt_text);
        $this->assertModelMissing($old);
        Storage::disk('public')->assertExists($new->directory.'/'.$new->file_name);

        foreach ($oldPaths as $path) {
            Storage::disk('public')->assertMissing($path);
        }

        $this->assertSame(1, $this->auditRows('media.replaced')->count());
        $this->assertSame(0, $this->auditRows('doctor.updated')->count(), 'hanya foto yang berubah: tanpa baris doctor.updated');
    }

    public function test_update_with_a_rejected_photo_keeps_the_old_photo_and_the_profile(): void
    {
        $doctor = $this->service->create($this->payload(), $this->actor(), ImageFixtures::jpeg(800, 800), 'Foto lama');
        $old = Media::query()->where('collection', 'photo')->sole();

        $this->assertRejected(
            fn () => $this->service->update($doctor, ['name' => 'Tidak Tersimpan'], $this->actor(), ImageFixtures::jpeg(100, 100), 'Terlalu kecil'),
            'file',
            'Dimensi gambar minimal 300 x 300 piksel dan maksimal 6000 x 6000 piksel.',
        );

        $this->assertModelExists($old);
        Storage::disk('public')->assertExists($old->directory.'/'.$old->file_name);
        $this->assertSame('Andi Pratama', Doctor::query()->findOrFail($doctor->id)->name);
    }

    public function test_stale_instance_does_not_overwrite_newer_database_values(): void
    {
        $doctor = $this->makeDoctor();
        $stale = Doctor::query()->findOrFail($doctor->id);

        DB::table('doctors')->where('id', $doctor->id)->update(['title_suffix' => 'Sp.B']);

        $this->service->update($stale, ['name' => 'Budi Santoso'], $this->actor());

        $fresh = Doctor::query()->findOrFail($doctor->id);

        $this->assertSame('Sp.B', $fresh->title_suffix, 'kolom yang tidak dikirim tetap bernilai terbaru');
        $this->assertSame('Budi Santoso', $fresh->name);

        $log = $this->auditRows('doctor.updated')->sole();
        $this->assertEquals(['name' => 'Andi Pratama'], $log->old_values);
        $this->assertEquals(['name' => 'Budi Santoso'], $log->new_values);
    }

    // ------------------------------------------------------------------ replacePhoto

    public function test_replace_photo_returns_the_new_media_and_replaces_the_previous_one(): void
    {
        $doctor = $this->makeDoctor();

        $first = $this->service->replacePhoto($doctor, ImageFixtures::jpeg(800, 800), 'Foto pertama', $this->actor());
        $second = $this->service->replacePhoto($doctor, ImageFixtures::webp(700, 700), 'Foto kedua', $this->actor());

        $this->assertInstanceOf(Media::class, $second);
        $this->assertSame('Foto kedua', $second->alt_text);
        $this->assertSame($doctor->id, (int) $second->model_id);
        $this->assertSame($this->staff->id, (int) $second->uploaded_by);
        $this->assertModelMissing($first);
        $this->assertSame(1, Media::query()->where('collection', 'photo')->count());
        $this->assertSame(2, $this->auditRows('media.replaced')->count());
    }

    public function test_replace_photo_requires_alt_text_and_keeps_the_current_photo_on_failure(): void
    {
        $doctor = $this->makeDoctor();
        $current = $this->service->replacePhoto($doctor, ImageFixtures::jpeg(800, 800), 'Foto', $this->actor());

        $this->assertRejected(
            fn () => $this->service->replacePhoto($doctor, ImageFixtures::jpeg(800, 800), '  ', $this->actor()),
            'alt_text',
            'Teks alternatif gambar wajib diisi.',
        );

        $this->assertModelExists($current);
        Storage::disk('public')->assertExists($current->directory.'/'.$current->file_name);
        $this->assertSame(1, Media::query()->where('collection', 'photo')->count());
    }

    public function test_replace_photo_keeps_the_old_photo_when_the_outer_transaction_rolls_back(): void
    {
        $doctor = $this->makeDoctor();
        $old = $this->service->replacePhoto($doctor, ImageFixtures::jpeg(800, 800), 'Foto lama', $this->actor());

        $thrown = false;

        try {
            DB::transaction(function () use ($doctor): void {
                $this->service->replacePhoto($doctor, ImageFixtures::png(700, 700), 'Foto baru', $this->actor());

                throw new RuntimeException('transaksi pemanggil gagal');
            });
        } catch (RuntimeException) {
            $thrown = true;
        }

        $this->assertTrue($thrown);
        $this->assertModelExists($old);
        Storage::disk('public')->assertExists($old->directory.'/'.$old->file_name);
        $this->assertSame(1, Media::query()->where('collection', 'photo')->count());
    }

    // ---- akhir siklus B
```

- [ ] **Step 8: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=DoctorServiceTest
```
Expected: FAIL pada seluruh tes baru (`Call to undefined method App\Services\Doctors\DoctorService::update()` / `replacePhoto()`); tes siklus A tetap PASS.

- [ ] **Step 9: Implementasi (tool Edit pada `app/Services/Doctors/DoctorService.php`)**

Sisipkan method publik baru **sebelum** method private `profileAttributes`. `old_string` (docblock + signature, tepat satu kecocokan; docblock ikut agar tidak terpisah dari method-nya):

```php
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function profileAttributes(array $data): array
```

`new_string`:

```php
    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Doctor $doctor, array $data, Actor $actor, ?UploadedFile $photo = null, ?string $photoAlt = null): Doctor
    {
        $touchesSpecialties = array_key_exists('specialty_ids', $data) || array_key_exists('primary_specialty_id', $data);

        if ($touchesSpecialties && ! (array_key_exists('specialty_ids', $data) && array_key_exists('primary_specialty_id', $data))) {
            throw new InvalidArgumentException('specialty_ids dan primary_specialty_id harus dikirim bersama.');
        }

        $specialtyInput = $touchesSpecialties ? $this->requireSpecialtyInput($data) : null;

        if ($specialtyInput !== null) {
            $this->validatedSpecialtyIds($specialtyInput[0], $specialtyInput[1]);
        }

        return DB::transaction(function () use ($doctor, $data, $actor, $photo, $photoAlt, $specialtyInput): Doctor {
            $this->lock($doctor);

            $doctor->fill($this->profileAttributes($data));

            $new = $doctor->getDirty();
            $old = Arr::only($doctor->getRawOriginal(), array_keys($new));

            if ($new !== []) {
                $doctor->save();
            }

            if ($specialtyInput !== null) {
                [$ids, $primaryId] = $specialtyInput;
                $before = $this->currentSpecialties($doctor);

                $this->syncSpecialties($doctor, $ids, $primaryId);

                $after = $this->validatedSpecialtyIds($ids, $primaryId);
                sort($after);

                if ($before['ids'] !== $after) {
                    $old['specialty_ids'] = $before['ids'];
                    $new['specialty_ids'] = $after;
                }

                if ($before['primary'] !== $primaryId) {
                    $old['primary_specialty_id'] = $before['primary'];
                    $new['primary_specialty_id'] = $primaryId;
                }
            }

            if ($new !== []) {
                $this->audit->log('doctor.updated', $doctor, $old, $new, $this->describe('updated', $doctor), $actor);
            }

            if ($photo !== null) {
                $this->media->replace($doctor, 'photo', $photo, ['alt_text' => $photoAlt], $actor->user());
            }

            return $doctor;
        });
    }

    /**
     * Ganti foto dokter (koleksi tunggal `photo`). Berkas lama dihapus setelah transaksi commit.
     */
    public function replacePhoto(Doctor $doctor, UploadedFile $photo, string $alt, Actor $actor): Media
    {
        return DB::transaction(fn (): Media => $this->media->replace($doctor, 'photo', $photo, ['alt_text' => $alt], $actor->user()));
    }

    /**
     * Baca ulang baris dengan kunci tulis dan perbarui instance pemanggil, agar instance basi tidak menimpa
     * perubahan lain (status, arsip). Dipanggil di dalam transaksi.
     */
    private function lock(Doctor $doctor): void
    {
        $fresh = Doctor::query()->whereKey($doctor->getKey())->lockForUpdate()->firstOrFail();

        $doctor->setRawAttributes($fresh->getRawOriginal(), true);
    }

    /**
     * @return array{ids: list<int>, primary: ?int}
     */
    private function currentSpecialties(Doctor $doctor): array
    {
        $rows = DB::table('doctor_specialties')->where('doctor_id', $doctor->getKey())->get(['specialty_id', 'is_primary']);
        $ids = $rows->pluck('specialty_id')->map(fn ($id): int => (int) $id)->sort()->values()->all();
        $primary = $rows->first(fn ($row): bool => (bool) $row->is_primary);

        return ['ids' => $ids, 'primary' => $primary === null ? null : (int) $primary->specialty_id];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function profileAttributes(array $data): array
```

Lalu tambahkan impor yang dibutuhkan. `old_string` (tepat satu kecocokan):

```php
use App\Models\Doctor;
use App\Models\Specialty;
```

`new_string`:

```php
use App\Models\Doctor;
use App\Models\Media;
use App\Models\Specialty;
```

dan `old_string`:

```php
use Illuminate\Validation\ValidationException;
```

`new_string`:

```php
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
```

- [ ] **Step 10: Jalankan tes — harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=DoctorServiceTest
```
Expected: seluruhnya PASS. Catatan: pada `test_update_replaces_the_photo…` baris `media.replaced` dihitung 1 dan **tidak** ada `doctor.updated` karena data kosong; pada `test_stale_instance…` kolom `title_suffix` yang diubah di luar service tidak ikut masuk audit karena `lock()` menyegarkan nilai asli sebelum `fill()`.

- [ ] **Step 11: Pint dan commit siklus B**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint --test
git add app/Services/Doctors/DoctorService.php tests/Feature/Doctors/DoctorServiceTest.php
git commit -m "feat(fase-2): DoctorService update dan replacePhoto" -m "update mengunci dan membaca ulang baris, tidak menyentuh slug/status, mengaudit hanya perubahan (termasuk spesialisasi); foto diganti lewat MediaService::replace dengan penghapusan berkas lama setelah commit." -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```
Expected: Pint `PASS`; commit sukses.

**Siklus C — `changeStatus()`, `archive()`, dan titik sambung fase 3**

- [ ] **Step 12: Tambah tes yang gagal (tool Edit pada `tests/Feature/Doctors/DoctorServiceTest.php`)**

`old_string` (satu baris, tepat satu kecocokan):

```php
    // ---- akhir siklus B
```

`new_string`:

```php
    // ------------------------------------------------------------------ changeStatus

    public function test_change_status_deactivates_and_reactivates_with_audit(): void
    {
        $doctor = $this->makeDoctor();

        $inactive = $this->service->changeStatus($doctor, DoctorStatus::Inactive, '  Cuti panjang  ', $this->actor());

        $this->assertSame(DoctorStatus::Inactive, $inactive->status);
        $this->assertSame(DoctorStatus::Inactive, Doctor::query()->findOrFail($doctor->id)->status);

        $log = $this->auditRows('doctor.status_changed')->sole();

        $this->assertSame($this->staff->id, (int) $log->user_id);
        $this->assertSame($doctor->id, (int) $log->auditable_id);
        $this->assertEquals(['status' => 'active'], $log->old_values);
        $this->assertEquals(['status' => 'inactive', 'reason' => 'Cuti panjang'], $log->new_values);
        $this->assertStringContainsString('dr. Andi Pratama, Sp.A', $log->description);

        $this->service->changeStatus($doctor, DoctorStatus::Active, null, $this->actor());

        $this->assertSame(DoctorStatus::Active, Doctor::query()->findOrFail($doctor->id)->status);

        $second = $this->auditRows('doctor.status_changed')->last();
        $this->assertEquals(['status' => 'inactive'], $second->old_values);
        $this->assertEquals(['status' => 'active'], $second->new_values);
        $this->assertInvariants();
    }

    public function test_change_status_to_the_same_status_is_a_no_op_without_audit(): void
    {
        $doctor = $this->makeDoctor();

        $result = $this->service->changeStatus($doctor, DoctorStatus::Active, 'alasan', $this->actor());

        $this->assertSame(DoctorStatus::Active, $result->status);
        $this->assertSame(0, $this->auditRows('doctor.status_changed')->count());
    }

    public function test_change_status_to_archived_is_a_misuse(): void
    {
        $doctor = $this->makeDoctor();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Gunakan archive() untuk mengarsipkan dokter.');

        $this->service->changeStatus($doctor, DoctorStatus::Archived, null, $this->actor());
    }

    public function test_archived_doctor_can_only_be_restored_as_inactive_and_archived_at_is_cleared(): void
    {
        $doctor = $this->makeDoctor();
        $this->service->archive($doctor, null, $this->actor());
        $archivedAt = Doctor::query()->findOrFail($doctor->id)->archived_at->format('Y-m-d H:i:s');

        $this->assertRejected(
            fn () => $this->service->changeStatus($doctor, DoctorStatus::Active, null, $this->actor()),
            'status',
            'Dokter yang diarsipkan hanya dapat dipulihkan menjadi nonaktif.',
        );

        $this->assertSame(DoctorStatus::Archived, Doctor::query()->findOrFail($doctor->id)->status);
        $this->assertSame(0, $this->auditRows('doctor.status_changed')->count());

        $restored = $this->service->changeStatus($doctor, DoctorStatus::Inactive, 'Kembali bertugas', $this->actor());

        $this->assertSame(DoctorStatus::Inactive, $restored->status);
        $this->assertNull($restored->archived_at);

        $fresh = Doctor::query()->findOrFail($doctor->id);
        $this->assertSame(DoctorStatus::Inactive, $fresh->status);
        $this->assertNull($fresh->archived_at);

        $log = $this->auditRows('doctor.status_changed')->sole();
        $this->assertEquals(['status' => 'archived', 'archived_at' => $archivedAt], $log->old_values);
        $this->assertEquals(['status' => 'inactive', 'archived_at' => null, 'reason' => 'Kembali bertugas'], $log->new_values);
        $this->assertStringContainsString('dipulihkan', $log->description);
        $this->assertInvariants();
    }

    public function test_stale_instance_cannot_bypass_the_archive_restore_rule(): void
    {
        $doctor = $this->makeDoctor();
        $stale = Doctor::query()->findOrFail($doctor->id);

        $this->service->archive($doctor, null, $this->actor());

        $this->assertRejected(
            fn () => $this->service->changeStatus($stale, DoctorStatus::Active, null, $this->actor()),
            'status',
            'Dokter yang diarsipkan hanya dapat dipulihkan menjadi nonaktif.',
        );

        $this->assertSame(DoctorStatus::Archived, Doctor::query()->findOrFail($doctor->id)->status);
    }

    public function test_change_status_is_rolled_back_when_the_audit_row_cannot_be_written(): void
    {
        $doctor = $this->makeDoctor();

        AuditLog::creating(fn () => throw new RuntimeException('audit gagal'));

        $thrown = false;

        try {
            $this->service->changeStatus($doctor, DoctorStatus::Inactive, null, $this->actor());
        } catch (RuntimeException) {
            $thrown = true;
        }

        $this->assertTrue($thrown);
        $this->assertSame(DoctorStatus::Active, Doctor::query()->findOrFail($doctor->id)->status);
    }

    // ------------------------------------------------------------------ archive

    public function test_archive_sets_status_and_timestamp_and_writes_the_audit_row(): void
    {
        $doctor = $this->makeDoctor();

        $archived = $this->service->archive($doctor, '  Pensiun  ', $this->actor());

        $fresh = Doctor::query()->findOrFail($doctor->id);

        $this->assertSame(DoctorStatus::Archived, $archived->status);
        $this->assertSame(DoctorStatus::Archived, $fresh->status);
        $this->assertNotNull($fresh->archived_at);

        $log = $this->auditRows('doctor.archived')->sole();

        $this->assertSame($this->staff->id, (int) $log->user_id);
        $this->assertSame($doctor->id, (int) $log->auditable_id);
        $this->assertEquals(['status' => 'active', 'archived_at' => null], $log->old_values);
        $this->assertEquals([
            'status' => 'archived',
            'archived_at' => $fresh->archived_at->format('Y-m-d H:i:s'),
            'reason' => 'Pensiun',
        ], $log->new_values);
        $this->assertInvariants();
    }

    public function test_an_inactive_doctor_can_be_archived_and_is_no_longer_publicly_visible(): void
    {
        $doctor = $this->makeDoctor();

        $this->assertTrue(Doctor::query()->publiclyVisible()->whereKey($doctor->id)->exists());

        $this->service->changeStatus($doctor, DoctorStatus::Inactive, null, $this->actor());
        $this->assertFalse(Doctor::query()->publiclyVisible()->whereKey($doctor->id)->exists());

        $this->service->archive($doctor, null, $this->actor());

        $this->assertSame(DoctorStatus::Archived, Doctor::query()->findOrFail($doctor->id)->status);
        $this->assertFalse(Doctor::query()->publiclyVisible()->whereKey($doctor->id)->exists());
        $this->assertInvariants();
    }

    public function test_archiving_twice_is_rejected_without_a_second_audit_row(): void
    {
        $doctor = $this->makeDoctor();
        $this->service->archive($doctor, null, $this->actor());

        $this->assertRejected(fn () => $this->service->archive($doctor, null, $this->actor()), 'status', 'Dokter sudah diarsipkan.');

        $this->assertSame(1, $this->auditRows('doctor.archived')->count());
    }

    public function test_archive_is_rolled_back_when_the_audit_row_cannot_be_written(): void
    {
        $doctor = $this->makeDoctor();

        AuditLog::creating(fn () => throw new RuntimeException('audit gagal'));

        $thrown = false;

        try {
            $this->service->archive($doctor, null, $this->actor());
        } catch (RuntimeException) {
            $thrown = true;
        }

        $this->assertTrue($thrown);

        $fresh = Doctor::query()->findOrFail($doctor->id);
        $this->assertSame(DoctorStatus::Active, $fresh->status);
        $this->assertNull($fresh->archived_at);
    }

    // ------------------------------------------------------------------ titik sambung fase 3 (F2-R8)

    public function test_future_appointment_seam_is_protected_and_always_zero_in_phase_two(): void
    {
        $doctor = $this->makeDoctor();
        $method = new ReflectionMethod(DoctorService::class, 'futureActiveAppointmentsCount');

        $this->assertTrue($method->isProtected());
        $this->assertSame(0, $method->invoke($this->service, $doctor));
        $this->assertNull($this->service->archiveBlockedReason($doctor));
    }

    public function test_archive_refusal_message_matches_the_specification(): void
    {
        $method = new ReflectionMethod(DoctorService::class, 'blockedMessage');

        $this->assertTrue($method->isPrivate());
        $this->assertTrue($method->isStatic());
        $this->assertSame(
            'Dokter masih memiliki 3 pendaftaran aktif pada sesi mendatang. Selesaikan sesi terdampak terlebih dahulu, lalu arsipkan dokter.',
            $method->invoke(null, 3),
        );
    }

    // ---- akhir siklus C
```

- [ ] **Step 13: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=DoctorServiceTest
```
Expected: FAIL pada tes baru (`Call to undefined method …::changeStatus()` / `archive()` / `archiveBlockedReason()`; `ReflectionException: Method … does not exist`); tes siklus A dan B tetap PASS.

- [ ] **Step 14: Implementasi (tool Edit pada `app/Services/Doctors/DoctorService.php`)**

Sisipkan method baru **sebelum** method private `lock` (anchor tiga baris, tepat satu kecocokan). `old_string`:

```php
    /**
     * Baca ulang baris dengan kunci tulis dan perbarui instance pemanggil, agar instance basi tidak menimpa
```

`new_string`:

```php
    /**
     * Aktifkan/nonaktifkan dokter, atau pulihkan dokter arsip menjadi nonaktif (F2-R7).
     * Pengaruh ke sesi/appointment (00 F.4) baru ada di fase 3.
     */
    public function changeStatus(Doctor $doctor, DoctorStatus $status, ?string $reason, Actor $actor): Doctor
    {
        if ($status === DoctorStatus::Archived) {
            throw new InvalidArgumentException('Gunakan archive() untuk mengarsipkan dokter.');
        }

        $reason = $this->nullableTrim($reason);

        return DB::transaction(function () use ($doctor, $status, $reason, $actor): Doctor {
            $this->lock($doctor);

            $current = $doctor->status;

            if ($current === DoctorStatus::Archived && $status !== DoctorStatus::Inactive) {
                throw ValidationException::withMessages(['status' => 'Dokter yang diarsipkan hanya dapat dipulihkan menjadi nonaktif.']);
            }

            if ($current === $status) {
                return $doctor;
            }

            $old = ['status' => $current->value];
            $new = ['status' => $status->value];

            if ($current === DoctorStatus::Archived) {
                $old['archived_at'] = $doctor->archived_at;
                $new['archived_at'] = null;
                $doctor->archived_at = null;
            }

            $doctor->status = $status;
            $doctor->save();

            if ($reason !== null) {
                $new['reason'] = $reason;
            }

            $verb = match (true) {
                $current === DoctorStatus::Archived => 'dipulihkan dari arsip',
                $status === DoctorStatus::Inactive => 'dinonaktifkan',
                default => 'diaktifkan',
            };

            $this->audit->log('doctor.status_changed', $doctor, $old, $new, "Dokter \"{$doctor->displayName}\" {$verb}.", $actor);

            return $doctor;
        });
    }

    /**
     * Arsipkan dokter (BR-03/BR-06). Ditolak bila masih ada pendaftaran aktif pada sesi mendatang (00 F.4);
     * aturan ini berlaku juga untuk Super Admin karena dijaga di sini, bukan di Policy.
     */
    public function archive(Doctor $doctor, ?string $reason, Actor $actor): Doctor
    {
        $reason = $this->nullableTrim($reason);

        return DB::transaction(function () use ($doctor, $reason, $actor): Doctor {
            $this->lock($doctor);

            if ($doctor->status === DoctorStatus::Archived) {
                throw ValidationException::withMessages(['status' => 'Dokter sudah diarsipkan.']);
            }

            $blocked = $this->archiveBlockedReason($doctor);

            if ($blocked !== null) {
                throw ValidationException::withMessages(['status' => $blocked]);
            }

            $old = ['status' => $doctor->status->value, 'archived_at' => null];

            $doctor->status = DoctorStatus::Archived;
            $doctor->archived_at = now();
            $doctor->save();

            $new = ['status' => DoctorStatus::Archived->value, 'archived_at' => $doctor->archived_at];

            if ($reason !== null) {
                $new['reason'] = $reason;
            }

            $this->audit->log('doctor.archived', $doctor, $old, $new, "Dokter \"{$doctor->displayName}\" diarsipkan.", $actor);

            return $doctor;
        });
    }

    /**
     * Alasan arsip ditolak (atau null bila boleh). Dipakai ArchiveDoctorRequest dan archive().
     */
    public function archiveBlockedReason(Doctor $doctor): ?string
    {
        $count = $this->futureActiveAppointmentsCount($doctor);

        return $count > 0 ? self::blockedMessage($count) : null;
    }

    /**
     * Titik sambung fase 3 (F2-R8): jumlah appointment aktif pada sesi mendatang. Fase 2 belum memiliki
     * tabel appointment sehingga selalu 0; fase 3 cukup mengganti isi method ini.
     */
    protected function futureActiveAppointmentsCount(Doctor $doctor): int
    {
        return 0;
    }

    private static function blockedMessage(int $count): string
    {
        return "Dokter masih memiliki {$count} pendaftaran aktif pada sesi mendatang. Selesaikan sesi terdampak terlebih dahulu, lalu arsipkan dokter.";
    }

    private function nullableTrim(?string $value): ?string
    {
        $value = $value === null ? '' : trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * Baca ulang baris dengan kunci tulis dan perbarui instance pemanggil, agar instance basi tidak menimpa
```

- [ ] **Step 15: Jalankan tes — harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=DoctorServiceTest
```
Expected: seluruhnya PASS.
Catatan: pada `test_archived_doctor_can_only_be_restored…`, `archived_at` di audit lama adalah string `Y-m-d H:i:s` (normalisasi `DateTimeInterface` oleh `AuditLogger`). Bila perbandingan gagal karena detik berbeda, itu pertanda `archived_at` dibaca sebelum `save()`; periksa urutan di `archive()`.

- [ ] **Step 16: Seluruh suite, Pint, dan commit siklus C**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test
& $PHP vendor\bin\pint --test
git add app/Services/Doctors/DoctorService.php tests/Feature/Doctors/DoctorServiceTest.php
git commit -m "feat(fase-2): DoctorService changeStatus, archive, dan titik sambung fase 3" -m "Status active/inactive dan pemulihan arsip menjadi nonaktif (F2-R7); arsip mengisi archived_at (I-23) dan menolak lewat archiveBlockedReason; futureActiveAppointmentsCount selalu 0 sampai fase 3 (F2-R8); audit doctor.status_changed dan doctor.archived." -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```
Expected: `artisan test` hijau seluruhnya; Pint `PASS` (bila melapor berkas, jalankan `& $PHP vendor\bin\pint <berkas>` lalu ulangi `--test` dan `artisan test --filter=DoctorServiceTest`); commit sukses. Tidak ada perubahan `resources/js`/`resources/scss`.

---

**Catatan verifikasi penulis (Task 5-8):** Semua blok PHP lengkap (termasuk berkas tes dan `DoctorService` hasil penerapan siklus A-C, `routes/admin.php`, `config/navigation.php`, dan `FrontendManifestTest` hasil penerapan edit) lolos `php -l` 8.2 (`C:\xampp3\php\php.exe`). `editor.js` dan `admin.js` hasil edit lolos `node --check`. Setiap `old_string` pada langkah Edit dicocokkan terhadap berkas nyata di cabang `fase-2-master-data` dan ditemukan tepat satu kali; penjaga teks `FrontendManifestTest` (kata terlarang dan kebutuhan) dicocokkan terhadap `editor.js`.


---

### Task 9: Admin Spesialisasi (#34, tab Spesialisasi)

**Files:**
- Create: `app/Http/Requests/Admin/MasterDataIndexRequest.php`
- Create: `app/Http/Requests/Admin/SpecialtyRequest.php`
- Create: `app/Http/Controllers/Admin/SpecialtyController.php`
- Create: `resources/views/admin/specialties/index.blade.php`, `create.blade.php`, `edit.blade.php`, `_form.blade.php`
- Modify: `routes/admin.php` (import `SpecialtyController` + satu `Route::resource('spesialisasi', …)` di grup `permission:clinics.manage`)
- Test: `tests/Feature/Admin/AdminSpecialtyIndexTest.php`, `tests/Feature/Admin/AdminSpecialtyCrudTest.php`, `tests/Feature/Admin/AdminSpecialtyValidationTest.php`

**Interfaces:**
- Consumes:
  - Task 1: `Specialty` (`$fillable`: `name`, `slug`, `description`, `is_active`, `sort_order`; relasi `doctors()`/`clinics()` sehingga `withCount(['doctors', 'clinics'])` menghasilkan `doctors_count`/`clinics_count`; scope `active()`, `ordered()`; `deletionBlockedReason(): ?string` = "Spesialisasi ini masih dipakai oleh dokter atau poliklinik sehingga tidak dapat dihapus. Nonaktifkan saja."), `SpecialtyFactory` (`inactive()`), `Tests\Support\InteractsWithMasterData` (`createSpecialty()`, `createClinic()`, `createDoctor()`).
  - Task 2: `HasUniqueSlug` (slug dibuat saat `creating`, tidak berubah saat nama diedit). Form **tidak** punya input `slug` (F2-R5).
  - Task 3: `AuditObserver` pada `Specialty` (aksi `specialty.created|updated|deleted`) dan `lang/id/messages.php` (`messages.created|updated|deleted`, `messages.entities.specialty` = "Spesialisasi").
  - Task 4: `SpecialtyPolicy` (`viewAny`, `create`, `update`, `delete` → `clinics.manage`) dan `App\Rules\NoHtml` (kunci pesan pendek `no_html`).
  - Task 5: `x-module-tabs group="clinics"`, `x-data-table` dengan prop `preserve` dan pesan halaman di luar jangkauan.
  - Fase 1: `ListFilterRequest` (hook `statusValues()`/`sortable()`, `filters()`, `perPage()`), `x-layouts.admin` (slot `breadcrumb` dan `actions`), `x-breadcrumb`, `x-data-table`, `x-confirm-modal`, `x-form.input|textarea|checkbox|errors-summary`, `x-loading-button`, `InteractsWithStaff`.
- Produces:
  - `App\Http\Requests\Admin\MasterDataIndexRequest extends ListFilterRequest` (dipakai juga Task 10): `statusValues()` = `aktif`, `nonaktif`; `sortable()` = `nama`, `urutan` (awalan `-` = menurun, otomatis dari basis); `applyTo(Builder $query, array $searchColumns = ['name']): Builder` menerapkan `q` (LIKE ter-escape), `status`, dan `urut` (tanpa `urut` memakai scope `ordered()`).
  - `App\Http\Requests\Admin\SpecialtyRequest` (V-21; satu kelas untuk `store` dan `update`): `authorize()` memanggil Policy, `rules()`, `messages()`, `attributesForModel(): array`.
  - `App\Http\Controllers\Admin\SpecialtyController`: `index`, `create`, `store`, `edit`, `update`, `destroy` (tanpa `show`).
  - Route (di grup G-ADMIN, `permission:clinics.manage`): `admin.specialties.index|create|store|edit|update|destroy` → `/admin/spesialisasi`, `/admin/spesialisasi/buat`, `/admin/spesialisasi/{specialty}/ubah`, dst.
  - View: `admin.specialties.index|create|edit` dan partial `admin.specialties._form` (variabel `$specialty` (nullable), `$action`, `$submitLabel`).
  - Flash `success`: "Spesialisasi berhasil ditambahkan." / "Spesialisasi berhasil diperbarui." / "Spesialisasi berhasil dihapus." (dari `messages.*`). Flash `error` saat hapus ditolak = persis `Specialty::deletionBlockedReason()` (berlaku juga untuk Super Admin, F2-R18).
  - Breadcrumb: index "Dasbor › Poliklinik dan Spesialisasi › Spesialisasi"; buat "… › Spesialisasi › Tambah"; ubah "… › Spesialisasi › Ubah {nama}" (05 §8.3). Segmen "Poliklinik dan Spesialisasi" menjadi tautan hanya bila `Route::has('admin.clinics.index')` (route itu baru ada di Task 10).

**Pesan galat (sumber 12 V-21):**

| Kunci | Pesan |
|---|---|
| `name` (required) | "Nama spesialisasi wajib diisi." |
| `name` (string) | "Nama spesialisasi harus berupa teks." |
| `name` (min:3) | "Nama spesialisasi minimal 3 karakter." |
| `name` (max:100) | "Nama spesialisasi maksimal 100 karakter." |
| `name` (unique) | "Nama spesialisasi sudah digunakan." |
| `description` (string) | "Deskripsi harus berupa teks." |
| `description` (max:1000) | "Deskripsi maksimal 1000 karakter." |
| `description` (`NoHtml`) | "Deskripsi tidak boleh mengandung tag HTML." |
| `is_active` (required, boolean) | "Status aktif tidak valid." |
| `sort_order` (integer) | "Urutan harus berupa angka bulat." |
| `sort_order` (between:0,9999) | "Urutan harus antara 0 dan 9999." |

**Test IDs:** CRD-01 (tambah, ubah, nonaktifkan spesialisasi; yang nonaktif keluar dari `Specialty::active()`, dasar filter publik, F2-R28), CRD-13 (bagian `specialties`: hapus bersyarat), AKS (#34 untuk Admin Konten, Super Admin, Petugas, Pasien, Tamu), VAL (V-21), VAL-07 (parameter filter tidak valid), Review Focus 2 dan 5.

**Catatan teknis (diverifikasi dari source):**
1. `app/Http/Controllers/Controller.php` sudah memakai trait `AuthorizesRequests` sejak Task 6 (`MediaController`; tesnya ada di Task 6). Task ini tidak mengubah berkas itu. Semua controller fase 2 memanggil `$this->authorize('ability', $model|Model::class)`.
2. `Gate::before` meloloskan Super Admin dari semua Policy, sehingga penolakan hapus yang dirujuk **harus** di controller (`deletionBlockedReason()`), bukan di Policy (F2-R18, Review Focus 5). FK `doctor_specialties.specialty_id RESTRICT` hanya jaring pengaman terakhir.
3. Mode ketat: view daftar membaca `doctors_count`/`clinics_count`; keduanya hanya ada bila `withCount(['doctors', 'clinics'])` dipakai. Tes daftar membuat beberapa baris dengan hitungan berbeda agar atribut yang hilang atau *lazy loading* langsung terdeteksi.
4. Pesan kustom untuk objek aturan (`NoHtml`) memakai kunci pendek `{field}.{snake_case nama kelas}` (`description.no_html`), diverifikasi di `FormatsMessages::getFromLocalArray()`. Dengan begitu teks tepat V-21 tidak bergantung pada pesan bawaan aturan.
5. `ListFilterRequest::failedValidation()` tidak melempar: filter tidak valid diabaikan dan flash `warning` "Filter tidak valid dan diabaikan." dipasang lewat `session()->now()`.
6. `x-data-table` memakai slot bernama `filters`, `actions`, `head`. Urutan (`urut`) bukan kolom form pencarian di layar ini, melainkan tautan di slot `actions`; prop `preserve` (default `['urut']`) membawanya sebagai hidden input agar pencarian tidak menghapus urutan.
7. Tombol hapus memakai satu `x-confirm-modal` yang dipakai ulang oleh semua baris lewat `data-confirm-action` dan `data-confirm-message` (pola `resources/js/admin.js` fase 1). Tanpa atribut event inline. Baris yang masih dirujuk menampilkan tautan "Nonaktifkan" ke halaman ubah, bukan tombol hapus (05 hal. 34).
8. Route `Route::resource` didaftarkan penuh sejak Siklus A; method controller yang belum ditulis baru dipanggil bila route diakses, dan dilengkapi di Siklus B dan C.

- [ ] **Step 1: Verifikasi prasyarat (cabang dan Task 1–5)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git branch --show-current
git status --short
foreach ($p in 'app\Models\Specialty.php', 'app\Policies\SpecialtyPolicy.php', 'app\Rules\NoHtml.php', 'app\Observers\AuditObserver.php', 'lang\id\messages.php', 'resources\views\components\module-tabs.blade.php', 'tests\Support\InteractsWithMasterData.php') { "{0} => {1}" -f $p, (Test-Path $p) }
& $PHP artisan test
```
Expected: `fase-2-master-data`; `git status` bersih; ketujuh berkas `True`; seluruh suite PASS (membuktikan Task 1–5 sudah ada). Bila ada berkas `False`, berhenti dan lapor `NEEDS_CONTEXT`.

**Siklus A — daftar (`index`), `MasterDataIndexRequest`**

- [ ] **Step 2: Tulis tes yang gagal (tool Write) `tests/Feature/Admin/AdminSpecialtyIndexTest.php`**

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

class AdminSpecialtyIndexTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithStaff;
    use RefreshDatabase;

    /**
     * @return array<string, array{string, int}>
     */
    public static function staffRoles(): array
    {
        return [
            'super admin' => ['super-admin', 200],
            'admin konten' => ['content-admin', 200],
            'petugas pendaftaran' => ['registration-officer', 403],
        ];
    }

    #[DataProvider('staffRoles')]
    public function test_index_access_per_staff_role(string $role, int $status): void
    {
        $this->createSpecialty(['name' => 'Urologi']);

        $this->actingAs($this->createStaff($role))
            ->get(route('admin.specialties.index'))
            ->assertStatus($status);
    }

    public function test_patient_gets_403(): void
    {
        $this->actingAs(User::factory()->patient()->create())
            ->get(route('admin.specialties.index'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $this->get(route('admin.specialties.index'))->assertRedirect(route('admin.login'));
    }

    public function test_urls_use_the_indonesian_segments(): void
    {
        $specialty = $this->createSpecialty();

        $this->assertSame(url('/admin/spesialisasi'), route('admin.specialties.index'));
        $this->assertSame(url('/admin/spesialisasi/buat'), route('admin.specialties.create'));
        $this->assertSame(url('/admin/spesialisasi/'.$specialty->id.'/ubah'), route('admin.specialties.edit', $specialty));
        $this->assertFalse(Route::has('admin.specialties.show'));
    }

    public function test_index_renders_many_rows_with_counts_in_strict_mode(): void
    {
        $neutral = $this->createClinic(['specialty_id' => null]);
        $urologi = $this->createSpecialty(['name' => 'Urologi', 'sort_order' => 1]);
        $anak = $this->createSpecialty(['name' => 'Anak', 'sort_order' => 2]);
        $mata = $this->createSpecialty(['name' => 'Mata', 'sort_order' => 3]);
        $kulit = $this->createSpecialty(['name' => 'Kulit dan Kelamin', 'sort_order' => 4]);

        $this->createDoctor([], $neutral, [$urologi]);
        $this->createDoctor([], $neutral, [$urologi, $anak]);
        $this->createClinic(['specialty_id' => $mata->id]);

        $response = $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.specialties.index'))
            ->assertOk()
            ->assertSee('Urologi')
            ->assertSee('Anak')
            ->assertSee('Mata')
            ->assertSee('Kulit dan Kelamin')
            ->assertSee('Masih dipakai oleh 2 dokter dan 0 poliklinik.')
            ->assertSee('Masih dipakai oleh 1 dokter dan 0 poliklinik.')
            ->assertSee('Masih dipakai oleh 0 dokter dan 1 poliklinik.');

        $response->assertSee('data-confirm-action="'.route('admin.specialties.destroy', $kulit).'"', false)
            ->assertDontSee('data-confirm-action="'.route('admin.specialties.destroy', $urologi).'"', false);
    }

    public function test_index_has_breadcrumb_module_tab_and_add_button(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.specialties.index'))
            ->assertOk()
            ->assertSee('Poliklinik dan Spesialisasi')
            ->assertSee('href="'.route('admin.specialties.create').'"', false)
            ->assertSee('Tambah Spesialisasi')
            ->assertSee('href="'.route('admin.specialties.index').'"', false)
            ->assertSee('aria-current="page"', false);
    }

    public function test_search_status_and_sort_filters(): void
    {
        $this->createSpecialty(['name' => 'Urologi', 'sort_order' => 2]);
        $this->createSpecialty(['name' => 'Anak', 'sort_order' => 1]);
        $this->createSpecialty(['name' => 'Bedah Lama', 'is_active' => false, 'sort_order' => 3]);
        $admin = $this->createStaff('content-admin');

        $this->actingAs($admin)->get(route('admin.specialties.index', ['q' => 'uro']))
            ->assertOk()->assertSee('Urologi')->assertDontSee('Bedah Lama');

        $this->get(route('admin.specialties.index', ['status' => 'nonaktif']))
            ->assertOk()->assertSee('Bedah Lama')->assertDontSee('Urologi');

        $this->get(route('admin.specialties.index', ['status' => 'aktif']))
            ->assertOk()->assertSee('Urologi')->assertDontSee('Bedah Lama');

        $this->get(route('admin.specialties.index'))
            ->assertSeeInOrder(['Anak', 'Urologi', 'Bedah Lama']);

        $this->get(route('admin.specialties.index', ['urut' => '-nama']))
            ->assertSeeInOrder(['Urologi', 'Bedah Lama', 'Anak']);

        $this->get(route('admin.specialties.index', ['urut' => 'nama']))
            ->assertSeeInOrder(['Anak', 'Bedah Lama', 'Urologi']);
    }

    public function test_like_wildcards_in_search_are_escaped(): void
    {
        $this->createSpecialty(['name' => 'Urologi']);
        $this->createSpecialty(['name' => 'Anak']);

        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.specialties.index', ['q' => '%']))
            ->assertOk()
            ->assertDontSee('Urologi')
            ->assertDontSee('Anak');
    }

    public function test_invalid_filters_are_ignored_and_external_redirect_is_not_echoed(): void
    {
        $this->createSpecialty(['name' => 'Urologi']);

        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.specialties.index', ['status' => 'zzz', 'urut' => 'drop table', 'kembali' => 'https://situs-lain.test']))
            ->assertOk()
            ->assertSee('Filter tidak valid dan diabaikan.')
            ->assertSee('Urologi')
            ->assertDontSee('situs-lain.test');
    }

    public function test_data_table_preserves_the_sort_parameter_in_the_search_form(): void
    {
        $this->createSpecialty(['name' => 'Urologi']);

        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.specialties.index', ['urut' => 'urutan']))
            ->assertOk()
            ->assertSee('name="urut"', false)
            ->assertSee('value="urutan"', false);
    }

    public function test_page_beyond_the_last_page_explains_and_links_back_to_page_one(): void
    {
        foreach (range(1, 26) as $number) {
            $this->createSpecialty(['name' => 'Spesialisasi '.$number]);
        }

        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.specialties.index', ['page' => 5]))
            ->assertOk()
            ->assertSee('Halaman yang diminta tidak berisi data.')
            ->assertSee('Kembali ke halaman 1');
    }

    public function test_default_page_size_is_25_rows(): void
    {
        foreach (range(1, 26) as $number) {
            $this->createSpecialty(['name' => 'Spesialisasi '.str_pad((string) $number, 2, '0', STR_PAD_LEFT), 'sort_order' => $number]);
        }

        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.specialties.index'))
            ->assertOk()
            ->assertSee('Spesialisasi 25')
            ->assertDontSee('Spesialisasi 26')
            ->assertSee('Menampilkan 1–25 dari 26 data');
    }

    public function test_empty_list_shows_the_empty_state(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.specialties.index'))
            ->assertOk()
            ->assertSee('Belum ada spesialisasi')
            ->assertSee('Tambah Spesialisasi');
    }
}
```

- [ ] **Step 3: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=AdminSpecialtyIndexTest
```
Expected: FAIL — seluruh tes gagal dengan `Symfony\Component\Routing\Exception\RouteNotFoundException: Route [admin.specialties.index] not defined.`

- [ ] **Step 4: Tulis implementasi daftar**

Pastikan trait otorisasi sudah dipasang Task 6 (tanpa mengubah berkas): `Select-String -Path app\Http\Controllers\Controller.php -Pattern 'use AuthorizesRequests;'` harus menghasilkan satu baris. Bila kosong, berhenti dan lapor `NEEDS_CONTEXT` (Task 6 belum selesai).

Tool Write `app/Http/Requests/Admin/MasterDataIndexRequest.php`:

```php
<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ListFilterRequest;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filter daftar spesialisasi dan poliklinik (F2-R25): `q`, `status` aktif/nonaktif, `urut` nama/urutan.
 * Parameter yang tidak valid diabaikan oleh basis (12 V-45), tidak pernah menjadi galat 500.
 */
class MasterDataIndexRequest extends ListFilterRequest
{
    /**
     * @return list<string>
     */
    protected function statusValues(): array
    {
        return ['aktif', 'nonaktif'];
    }

    /**
     * @return list<string>
     */
    protected function sortable(): array
    {
        return ['nama', 'urutan'];
    }

    /**
     * Menerapkan pencarian, status, dan urutan ke kueri. Tanpa `urut` dipakai scope ordered()
     * (sort_order lalu name). Karakter `%`, `_`, dan `\` pada kata kunci di-escape.
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  list<string>  $searchColumns
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public function applyTo(Builder $query, array $searchColumns = ['name']): Builder
    {
        $filters = $this->filters();
        $model = $query->getModel();

        if (isset($filters['q'])) {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['q']).'%';

            $query->where(function (Builder $inner) use ($searchColumns, $like, $model): void {
                foreach ($searchColumns as $column) {
                    $inner->orWhere($model->qualifyColumn($column), 'like', $like);
                }
            });
        }

        $status = $filters['status'] ?? null;

        if ($status === 'aktif' || $status === 'nonaktif') {
            $query->where($model->qualifyColumn('is_active'), $status === 'aktif');
        }

        $sort = $filters['urut'] ?? null;

        if ($sort === null) {
            return $query->ordered()->orderBy($model->qualifyColumn('id'));
        }

        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';

        if (ltrim($sort, '-') === 'nama') {
            return $query->orderBy($model->qualifyColumn('name'), $direction)->orderBy($model->qualifyColumn('id'));
        }

        return $query
            ->orderBy($model->qualifyColumn('sort_order'), $direction)
            ->orderBy($model->qualifyColumn('name'))
            ->orderBy($model->qualifyColumn('id'));
    }
}
```

Tool Write `app/Http/Controllers/Admin/SpecialtyController.php` (versi Siklus A; dilengkapi di Siklus B dan C):

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MasterDataIndexRequest;
use App\Models\Specialty;
use Illuminate\View\View;

class SpecialtyController extends Controller
{
    public function index(MasterDataIndexRequest $request): View
    {
        $this->authorize('viewAny', Specialty::class);

        $specialties = $request
            ->applyTo(Specialty::query()->withCount(['doctors', 'clinics']))
            ->paginate($request->perPage());

        return view('admin.specialties.index', ['specialties' => $specialties]);
    }
}
```

Tool Edit `routes/admin.php` — dua penyisipan (setiap `old` harus cocok tepat satu kali):

1. Import. `old`:
```php
use App\Http\Controllers\Admin\DashboardController;
```
`new`:
```php
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\SpecialtyController;
```
2. Route, tepat setelah route `dashboard` di dalam grup G-ADMIN. `old`:
```php
        ->name('dashboard');
```
`new`:
```php
        ->name('dashboard');

    // Spesialisasi (halaman #34, tab Spesialisasi): izin tunggal clinics.manage, tanpa show (11 §3.4).
    Route::middleware('permission:clinics.manage')->group(function (): void {
        Route::resource('spesialisasi', SpecialtyController::class)
            ->names('specialties')
            ->parameters(['spesialisasi' => 'specialty'])
            ->except(['show']);
    });
```

Tool Write `resources/views/admin/specialties/index.blade.php`:

```blade
@php
    $currentSort = (string) request()->query('urut', '');
    $statusNow = (string) request()->query('status', '');
    $sortUrl = fn (string $key): string => request()->fullUrlWithQuery(['urut' => $key, 'kembali' => null, 'page' => null]);
    $breadcrumbItems = [
        [
            'label' => 'Poliklinik dan Spesialisasi',
            'url' => \Illuminate\Support\Facades\Route::has('admin.clinics.index') ? route('admin.clinics.index') : null,
        ],
        ['label' => 'Spesialisasi'],
    ];
@endphp
<x-layouts.admin title="Poliklinik dan Spesialisasi">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="$breadcrumbItems" />
    </x-slot:breadcrumb>

    <x-slot:actions>
        <a href="{{ route('admin.specialties.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg" aria-hidden="true"></i> Tambah Spesialisasi
        </a>
    </x-slot:actions>

    <div class="mb-3">
        <x-module-tabs group="clinics" />
    </div>

    <x-data-table
        :rows="$specialties"
        :preserve="['urut']"
        search-placeholder="Cari nama spesialisasi…"
        caption="Daftar spesialisasi"
        empty-title="Belum ada spesialisasi"
        empty-message="Tambahkan spesialisasi agar dapat dipilih pada data dokter dan poliklinik.">
        <x-slot:filters>
            <div class="col-12 col-md-3 col-lg-2">
                <label for="filter-status" class="form-label fw-semibold">Status</label>
                <select id="filter-status" name="status" class="form-select">
                    <option value="">Semua</option>
                    <option value="aktif" @selected($statusNow === 'aktif')>Aktif</option>
                    <option value="nonaktif" @selected($statusNow === 'nonaktif')>Nonaktif</option>
                </select>
            </div>
        </x-slot:filters>

        <x-slot:actions>
            <nav aria-label="Urutkan daftar" class="d-flex flex-wrap align-items-center gap-2">
                <span class="text-secondary">Urutkan:</span>
                @foreach (['nama' => 'Nama', 'urutan' => 'Urutan tampil'] as $key => $label)
                    <a href="{{ $sortUrl($key) }}" class="btn btn-sm {{ $currentSort === $key ? 'btn-primary' : 'btn-outline-secondary' }}"@if ($currentSort === $key) aria-current="true"@endif>{{ $label }}</a>
                @endforeach
            </nav>
        </x-slot:actions>

        <x-slot:head>
            <th scope="col">Nama</th>
            <th scope="col">Dokter</th>
            <th scope="col">Poliklinik</th>
            <th scope="col">Status</th>
            <th scope="col">Urutan</th>
            <th scope="col">Aksi</th>
        </x-slot:head>

        @foreach ($specialties as $specialty)
            @php($inUse = $specialty->doctors_count + $specialty->clinics_count > 0)
            <tr>
                <td data-label="Nama">
                    <span class="fw-semibold">{{ $specialty->name }}</span>
                    @if ($specialty->description)
                        <div class="small text-secondary">{{ \Illuminate\Support\Str::limit($specialty->description, 90) }}</div>
                    @endif
                </td>
                <td data-label="Dokter">{{ $specialty->doctors_count }}</td>
                <td data-label="Poliklinik">{{ $specialty->clinics_count }}</td>
                <td data-label="Status">
                    @if ($specialty->is_active)
                        <span class="badge text-bg-success">Aktif</span>
                    @else
                        <span class="badge text-bg-secondary">Nonaktif</span>
                    @endif
                </td>
                <td data-label="Urutan">{{ $specialty->sort_order }}</td>
                <td data-label="Aksi" class="table-actions">
                    <a href="{{ route('admin.specialties.edit', $specialty) }}" class="btn btn-sm btn-outline-primary" aria-label="Ubah spesialisasi {{ $specialty->name }}">Ubah</a>
                    @if ($inUse)
                        <a href="{{ route('admin.specialties.edit', $specialty) }}" class="btn btn-sm btn-outline-secondary" aria-label="Nonaktifkan spesialisasi {{ $specialty->name }} (masih dipakai)">Nonaktifkan</a>
                        <span class="small text-secondary align-self-center">Masih dipakai oleh {{ $specialty->doctors_count }} dokter dan {{ $specialty->clinics_count }} poliklinik.</span>
                    @else
                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modal-hapus-spesialisasi" data-confirm-action="{{ route('admin.specialties.destroy', $specialty) }}" data-confirm-message="Hapus spesialisasi {{ $specialty->name }}? Tindakan ini tidak dapat dibatalkan." aria-label="Hapus spesialisasi {{ $specialty->name }}">Hapus</button>
                    @endif
                </td>
            </tr>
        @endforeach
    </x-data-table>

    <x-confirm-modal
        id="modal-hapus-spesialisasi"
        title="Hapus spesialisasi?"
        message="Spesialisasi akan dihapus permanen."
        confirm-label="Ya, hapus"
        variant="danger"
        method="DELETE" />
</x-layouts.admin>
```

- [ ] **Step 5: Jalankan tes — harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=AdminSpecialtyIndexTest
& $PHP artisan route:list --path=admin/spesialisasi
```
Expected: PASS (15 tes); `route:list` menampilkan lima baris (`index`, `create`, `store`, `edit`, `update`) ditambah `destroy` (PUT|PATCH dan DELETE di `{specialty}`), tanpa `show`.

- [ ] **Step 6: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint
& $PHP vendor\bin\pint --test
git add app/Http/Controllers/Admin/SpecialtyController.php app/Http/Requests/Admin/MasterDataIndexRequest.php routes/admin.php resources/views/admin/specialties/index.blade.php tests/Feature/Admin/AdminSpecialtyIndexTest.php
git commit -m @'
feat(fase-2): daftar admin spesialisasi dengan filter, urutan, dan hitungan pemakaian

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
'@
```
Expected: `pint` tanpa `--test` dijalankan lebih dulu dan merapikan berkas yang disentuh (mis. urutan impor `routes/admin.php` atau `DoctorController` yang baru ditambah); lalu `pint --test` PASS sebelum commit.

**Siklus B — tambah dan ubah (`create`, `store`, `edit`, `update`), `SpecialtyRequest`, form**

- [ ] **Step 7: Tulis tes yang gagal (tool Write) `tests/Feature/Admin/AdminSpecialtyCrudTest.php`**

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\Specialty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

class AdminSpecialtyCrudTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithStaff;
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Contoh Baru',
            'description' => 'Spesialisasi contoh untuk pengujian.',
            'is_active' => '1',
            'sort_order' => '14',
        ], $overrides);
    }

    public function test_create_form_renders_for_content_admin_without_a_slug_field(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.specialties.create'))
            ->assertOk()
            ->assertSee('Tambah Spesialisasi')
            ->assertSee('name="name"', false)
            ->assertSee('name="description"', false)
            ->assertSee('name="is_active"', false)
            ->assertSee('name="sort_order"', false)
            ->assertDontSee('name="slug"', false)
            ->assertSee('action="'.route('admin.specialties.store').'"', false);
    }

    public function test_content_admin_adds_edits_and_deactivates_a_specialty_crd01(): void
    {
        $admin = $this->createStaff('content-admin');

        $this->actingAs($admin)
            ->post(route('admin.specialties.store'), $this->payload())
            ->assertRedirect(route('admin.specialties.index'))
            ->assertSessionHas('success', 'Spesialisasi berhasil ditambahkan.');

        $specialty = Specialty::query()->where('name', 'Contoh Baru')->sole();

        $this->assertSame('contoh-baru', $specialty->slug);
        $this->assertTrue($specialty->is_active);
        $this->assertSame(14, $specialty->sort_order);
        $this->assertSame('Spesialisasi contoh untuk pengujian.', $specialty->description);
        $this->assertContains('Contoh Baru', Specialty::query()->active()->pluck('name')->all());
        $this->assertDatabaseHas('audit_logs', ['action' => 'specialty.created']);

        $this->put(route('admin.specialties.update', $specialty), $this->payload(['name' => 'Contoh Diubah', 'sort_order' => '3']))
            ->assertRedirect(route('admin.specialties.index'))
            ->assertSessionHas('success', 'Spesialisasi berhasil diperbarui.');

        $specialty->refresh();
        $this->assertSame('Contoh Diubah', $specialty->name);
        $this->assertSame('contoh-baru', $specialty->slug, 'Slug tidak berubah saat nama diedit.');
        $this->assertSame(3, $specialty->sort_order);
        $this->assertDatabaseHas('audit_logs', ['action' => 'specialty.updated']);

        $this->put(route('admin.specialties.update', $specialty), $this->payload(['name' => 'Contoh Diubah', 'is_active' => '0']))
            ->assertRedirect(route('admin.specialties.index'));

        $this->assertFalse($specialty->fresh()->is_active);
        $this->assertNotContains('Contoh Diubah', Specialty::query()->active()->pluck('name')->all());
    }

    public function test_optional_fields_default_when_omitted(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.specialties.store'), ['name' => 'Tanpa Opsional', 'is_active' => '1'])
            ->assertRedirect(route('admin.specialties.index'));

        $specialty = Specialty::query()->where('name', 'Tanpa Opsional')->sole();

        $this->assertNull($specialty->description);
        $this->assertSame(0, $specialty->sort_order);
    }

    public function test_two_specialties_with_equal_slug_source_get_numbered_slugs(): void
    {
        $admin = $this->createStaff('content-admin');
        $this->createSpecialty(['name' => 'Anak', 'slug' => 'anak']);

        $this->actingAs($admin)->post(route('admin.specialties.store'), $this->payload(['name' => 'ANAK!']))
            ->assertRedirect(route('admin.specialties.index'));

        $this->assertSame('anak-2', Specialty::query()->where('name', 'ANAK!')->sole()->slug);
    }

    public function test_slug_and_unknown_fields_are_not_mass_assignable_from_the_form(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.specialties.store'), $this->payload(['slug' => 'dipaksa', 'id' => 999]))
            ->assertRedirect(route('admin.specialties.index'));

        $specialty = Specialty::query()->where('name', 'Contoh Baru')->sole();

        $this->assertSame('contoh-baru', $specialty->slug);
        $this->assertNotSame(999, $specialty->id);
    }

    public function test_edit_form_shows_current_values(): void
    {
        $specialty = $this->createSpecialty(['name' => 'Urologi', 'description' => 'Saluran kemih.', 'sort_order' => 7]);

        $this->actingAs($this->createStaff('super-admin'))
            ->get(route('admin.specialties.edit', $specialty))
            ->assertOk()
            ->assertSee('Ubah Spesialisasi')
            ->assertSee('value="Urologi"', false)
            ->assertSee('Saluran kemih.')
            ->assertSee('value="7"', false)
            ->assertSee('action="'.route('admin.specialties.update', $specialty).'"', false)
            ->assertSee('name="_method" value="PUT"', false);
    }

    public function test_update_may_keep_the_own_name_but_not_take_another_name(): void
    {
        $urologi = $this->createSpecialty(['name' => 'Urologi']);
        $this->createSpecialty(['name' => 'Anak']);
        $admin = $this->createStaff('content-admin');

        $this->actingAs($admin)->put(route('admin.specialties.update', $urologi), $this->payload(['name' => 'Urologi']))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.specialties.index'));

        $this->put(route('admin.specialties.update', $urologi), $this->payload(['name' => 'anak']))
            ->assertSessionHasErrors(['name' => 'Nama spesialisasi sudah digunakan.']);
    }

    public function test_non_numeric_id_is_a_404_not_a_bound_model(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get('/admin/spesialisasi/abc/ubah')
            ->assertNotFound();
    }

    public function test_registration_officer_patient_and_guest_cannot_use_mutating_routes(): void
    {
        $specialty = $this->createSpecialty(['name' => 'Urologi']);

        $requests = [
            ['get', route('admin.specialties.create'), []],
            ['post', route('admin.specialties.store'), $this->payload(['name' => 'Tidak Boleh'])],
            ['get', route('admin.specialties.edit', $specialty), []],
            ['put', route('admin.specialties.update', $specialty), $this->payload(['name' => 'Diubah Paksa'])],
            ['delete', route('admin.specialties.destroy', $specialty), []],
        ];

        $officer = $this->createStaff('registration-officer');
        $patient = User::factory()->patient()->create();

        foreach ($requests as [$method, $url, $data]) {
            $this->actingAs($officer);
            $this->call(strtoupper($method), $url, $data)->assertForbidden();

            $this->actingAs($patient);
            $this->call(strtoupper($method), $url, $data)->assertForbidden();
        }

        $this->app['auth']->forgetGuards();

        foreach ($requests as [$method, $url, $data]) {
            $this->call(strtoupper($method), $url, $data)->assertRedirect(route('admin.login'));
        }

        $this->assertSame('Urologi', $specialty->fresh()->name);
        $this->assertDatabaseMissing('specialties', ['name' => 'Tidak Boleh']);
        $this->assertDatabaseMissing('specialties', ['name' => 'Diubah Paksa']);
        $this->assertDatabaseHas('specialties', ['id' => $specialty->id]);
    }
}
```

Tool Write `tests/Feature/Admin/AdminSpecialtyValidationTest.php`:

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\Specialty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * 12 V-21: pesan Indonesia per field, nilai lama dipertahankan, ringkasan galat (VAL-01 pola yang sama).
 */
class AdminSpecialtyValidationTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithStaff;
    use RefreshDatabase;

    /**
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function invalidInputs(): array
    {
        return [
            'nama kosong' => [['name' => ''], 'name', 'Nama spesialisasi wajib diisi.'],
            'nama bukan teks' => [['name' => ['a']], 'name', 'Nama spesialisasi harus berupa teks.'],
            'nama terlalu pendek' => [['name' => 'Ab'], 'name', 'Nama spesialisasi minimal 3 karakter.'],
            'nama terlalu panjang' => [['name' => str_repeat('a', 101)], 'name', 'Nama spesialisasi maksimal 100 karakter.'],
            'nama ganda' => [['name' => 'Urologi'], 'name', 'Nama spesialisasi sudah digunakan.'],
            'nama ganda beda huruf' => [['name' => 'UROLOGI'], 'name', 'Nama spesialisasi sudah digunakan.'],
            'deskripsi terlalu panjang' => [['description' => str_repeat('a', 1001)], 'description', 'Deskripsi maksimal 1000 karakter.'],
            'status tidak valid' => [['is_active' => 'mungkin'], 'is_active', 'Status aktif tidak valid.'],
            'status hilang' => [['is_active' => null], 'is_active', 'Status aktif tidak valid.'],
            'urutan bukan angka' => [['sort_order' => 'abc'], 'sort_order', 'Urutan harus berupa angka bulat.'],
            'urutan terlalu besar' => [['sort_order' => '10000'], 'sort_order', 'Urutan harus antara 0 dan 9999.'],
            'urutan negatif' => [['sort_order' => '-1'], 'sort_order', 'Urutan harus antara 0 dan 9999.'],
        ];
    }

    /**
     * @param  array<string, mixed>  $override
     */
    #[DataProvider('invalidInputs')]
    public function test_store_rejects_invalid_input_with_indonesian_messages(array $override, string $field, string $message): void
    {
        $this->createSpecialty(['name' => 'Urologi']);

        $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.specialties.create'))
            ->post(route('admin.specialties.store'), array_merge(['name' => 'Spesialisasi Baru', 'is_active' => '1'], $override))
            ->assertRedirect(route('admin.specialties.create'))
            ->assertSessionHasErrors([$field => $message]);

        $this->assertSame(1, Specialty::query()->count());
    }

    public function test_html_in_description_is_rejected(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.specialties.store'), ['name' => 'Spesialisasi Baru', 'is_active' => '1', 'description' => '<b>tebal</b>'])
            ->assertSessionHasErrors('description');

        $this->assertStringContainsString('tag HTML', (string) session('errors')->first('description'));
        $this->assertSame(0, Specialty::query()->count());
    }

    public function test_update_applies_the_same_rules(): void
    {
        $specialty = $this->createSpecialty(['name' => 'Urologi']);

        $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.specialties.edit', $specialty))
            ->put(route('admin.specialties.update', $specialty), ['name' => 'Ab', 'is_active' => '1'])
            ->assertRedirect(route('admin.specialties.edit', $specialty))
            ->assertSessionHasErrors(['name' => 'Nama spesialisasi minimal 3 karakter.']);

        $this->assertSame('Urologi', $specialty->fresh()->name);
    }

    public function test_invalid_form_keeps_old_input_and_shows_an_error_summary_with_aria_links(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.specialties.create'))
            ->post(route('admin.specialties.store'), ['name' => '', 'description' => 'Isi lama yang dipertahankan', 'is_active' => '1', 'sort_order' => '12'])
            ->assertRedirect(route('admin.specialties.create'));

        $this->get(route('admin.specialties.create'))
            ->assertOk()
            ->assertSee('data-errors-summary', false)
            ->assertSee('Nama spesialisasi wajib diisi.')
            ->assertSee('href="#name"', false)
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('name-error', false)
            ->assertSee('Isi lama yang dipertahankan')
            ->assertSee('value="12"', false);
    }
}
```

- [ ] **Step 8: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="AdminSpecialtyCrudTest|AdminSpecialtyValidationTest"
```
Expected: FAIL — tes yang membuka form/menyimpan gagal dengan `BadMethodCallException: Method App\Http\Controllers\Admin\SpecialtyController::create does not exist.` (atau `store`/`edit`/`update`); tes otorisasi mutasi gagal pada `delete`.

- [ ] **Step 9: Tulis implementasi tambah/ubah**

Tool Write `app/Http/Requests/Admin/SpecialtyRequest.php`:

```php
<?php

namespace App\Http\Requests\Admin;

use App\Models\Specialty;
use App\Rules\NoHtml;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * V-21: satu kelas untuk tambah dan ubah (dibedakan dari parameter route `specialty`).
 * `slug` tidak ada di form: dibuat sistem saat `creating` dan tidak berubah (F2-R5, BR-15).
 */
class SpecialtyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $specialty = $this->route('specialty');

        if ($user === null) {
            return false;
        }

        return $specialty instanceof Specialty
            ? $user->can('update', $specialty)
            : $user->can('create', Specialty::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'bail',
                'required',
                'string',
                'min:3',
                'max:100',
                Rule::unique('specialties', 'name')->ignore($this->route('specialty')),
            ],
            'description' => ['nullable', 'string', 'max:1000', new NoHtml],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'between:0,9999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama spesialisasi wajib diisi.',
            'name.string' => 'Nama spesialisasi harus berupa teks.',
            'name.min' => 'Nama spesialisasi minimal 3 karakter.',
            'name.max' => 'Nama spesialisasi maksimal 100 karakter.',
            'name.unique' => 'Nama spesialisasi sudah digunakan.',
            'description.string' => 'Deskripsi harus berupa teks.',
            'description.max' => 'Deskripsi maksimal 1000 karakter.',
            'description.no_html' => 'Deskripsi tidak boleh mengandung tag HTML.',
            'is_active.required' => 'Status aktif tidak valid.',
            'is_active.boolean' => 'Status aktif tidak valid.',
            'sort_order.integer' => 'Urutan harus berupa angka bulat.',
            'sort_order.between' => 'Urutan harus antara 0 dan 9999.',
        ];
    }

    /**
     * Atribut model hasil validasi (hanya kunci yang boleh diisi form; `slug` tidak termasuk).
     *
     * @return array{name: string, description: ?string, is_active: bool, sort_order: int}
     */
    public function attributesForModel(): array
    {
        $data = $this->validated();

        return [
            'name' => (string) $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => (bool) $data['is_active'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }
}
```

Tool Write `app/Http/Controllers/Admin/SpecialtyController.php` (ganti seluruh isi):

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MasterDataIndexRequest;
use App\Http\Requests\Admin\SpecialtyRequest;
use App\Models\Specialty;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SpecialtyController extends Controller
{
    public function index(MasterDataIndexRequest $request): View
    {
        $this->authorize('viewAny', Specialty::class);

        $specialties = $request
            ->applyTo(Specialty::query()->withCount(['doctors', 'clinics']))
            ->paginate($request->perPage());

        return view('admin.specialties.index', ['specialties' => $specialties]);
    }

    public function create(): View
    {
        $this->authorize('create', Specialty::class);

        return view('admin.specialties.create');
    }

    public function store(SpecialtyRequest $request): RedirectResponse
    {
        $this->authorize('create', Specialty::class);

        DB::transaction(fn (): Specialty => Specialty::query()->create($request->attributesForModel()));

        return redirect()->route('admin.specialties.index')
            ->with('success', __('messages.created', ['entity' => __('messages.entities.specialty')]));
    }

    public function edit(Specialty $specialty): View
    {
        $this->authorize('update', $specialty);

        return view('admin.specialties.edit', ['specialty' => $specialty]);
    }

    public function update(SpecialtyRequest $request, Specialty $specialty): RedirectResponse
    {
        $this->authorize('update', $specialty);

        DB::transaction(fn (): bool => $specialty->update($request->attributesForModel()));

        return redirect()->route('admin.specialties.index')
            ->with('success', __('messages.updated', ['entity' => __('messages.entities.specialty')]));
    }
}
```

Tool Write `resources/views/admin/specialties/_form.blade.php`:

```blade
{{-- Variabel: $specialty (Specialty|null), $action (URL), $submitLabel. Tanpa input slug (F2-R5). --}}
@php($isEdit = $specialty !== null)
<form method="POST" action="{{ $action }}">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <x-form.errors-summary />

    <div class="card">
        <div class="card-body">
            <x-form.input name="name" label="Nama spesialisasi" :value="$specialty?->name" :required="true" maxlength="100" help="3 sampai 100 karakter. Alamat (slug) dibuat otomatis dari nama dan tidak berubah saat nama diubah." />
            <x-form.textarea name="description" label="Deskripsi" :value="$specialty?->description" :rows="3" maxlength="1000" help="Teks biasa tanpa tag HTML. Maksimal 1000 karakter." />
            <x-form.input name="sort_order" type="number" label="Urutan tampil" :value="$specialty?->sort_order ?? 0" inputmode="numeric" min="0" max="9999" help="Angka kecil tampil lebih dulu (0 sampai 9999)." />
            <x-form.checkbox name="is_active" label="Aktif (tampil sebagai pilihan dan filter)" :checked="$specialty?->is_active ?? true" :with-hidden="true" help="Spesialisasi yang masih dipakai dokter atau poliklinik tidak dapat dihapus; nonaktifkan saja." />
        </div>
        <div class="card-footer d-flex flex-wrap gap-2 justify-content-end">
            <a href="{{ route('admin.specialties.index') }}" class="btn btn-outline-secondary">Batal</a>
            <x-loading-button icon="bi-check-lg" loading-text="Menyimpan…">{{ $submitLabel }}</x-loading-button>
        </div>
    </div>
</form>
```

Tool Write `resources/views/admin/specialties/create.blade.php`:

```blade
@php
    $breadcrumbItems = [
        [
            'label' => 'Poliklinik dan Spesialisasi',
            'url' => \Illuminate\Support\Facades\Route::has('admin.clinics.index') ? route('admin.clinics.index') : null,
        ],
        ['label' => 'Spesialisasi', 'url' => route('admin.specialties.index')],
        ['label' => 'Tambah'],
    ];
@endphp
<x-layouts.admin title="Tambah Spesialisasi">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="$breadcrumbItems" />
    </x-slot:breadcrumb>

    @include('admin.specialties._form', [
        'specialty' => null,
        'action' => route('admin.specialties.store'),
        'submitLabel' => 'Simpan',
    ])
</x-layouts.admin>
```

Tool Write `resources/views/admin/specialties/edit.blade.php`:

```blade
@php
    $breadcrumbItems = [
        [
            'label' => 'Poliklinik dan Spesialisasi',
            'url' => \Illuminate\Support\Facades\Route::has('admin.clinics.index') ? route('admin.clinics.index') : null,
        ],
        ['label' => 'Spesialisasi', 'url' => route('admin.specialties.index')],
        ['label' => 'Ubah '.$specialty->name],
    ];
@endphp
<x-layouts.admin title="Ubah Spesialisasi">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="$breadcrumbItems" />
    </x-slot:breadcrumb>

    @include('admin.specialties._form', [
        'specialty' => $specialty,
        'action' => route('admin.specialties.update', $specialty),
        'submitLabel' => 'Simpan Perubahan',
    ])
</x-layouts.admin>
```

- [ ] **Step 10: Jalankan tes — harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="AdminSpecialtyCrudTest|AdminSpecialtyValidationTest|AdminSpecialtyIndexTest"
```
Expected: PASS (seluruh tes tiga kelas, termasuk 12 kasus data provider validasi). Tes otorisasi mutasi untuk `delete` lulus karena `permission:clinics.manage` menolak sebelum method controller dipanggil.

- [ ] **Step 11: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint
& $PHP vendor\bin\pint --test
git add app/Http/Controllers/Admin/SpecialtyController.php app/Http/Requests/Admin/SpecialtyRequest.php resources/views/admin/specialties tests/Feature/Admin/AdminSpecialtyCrudTest.php tests/Feature/Admin/AdminSpecialtyValidationTest.php
git commit -m @'
feat(fase-2): tambah dan ubah spesialisasi dengan validasi V-21

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
'@
```
Expected: `pint` tanpa `--test` dijalankan lebih dulu dan merapikan berkas yang disentuh (mis. urutan impor `routes/admin.php` atau `DoctorController` yang baru ditambah); lalu `pint --test` PASS sebelum commit.

**Siklus C — hapus bersyarat (`destroy`), termasuk Super Admin**

- [ ] **Step 12: Tulis tes yang gagal (tool Edit) — tambahkan ke `tests/Feature/Admin/AdminSpecialtyCrudTest.php`**

Pertama, tambahkan import (`old` = `use Illuminate\Foundation\Testing\RefreshDatabase;`, `new` = baris itu diikuti `use PHPUnit\Framework\Attributes\DataProvider;`). Kedua, sisipkan empat method berikut (tiga tes dan data provider `deleters`) tepat sebelum method `test_registration_officer_patient_and_guest_cannot_use_mutating_routes` (`old` = baris `    public function test_registration_officer_patient_and_guest_cannot_use_mutating_routes(): void`, `new` = keempat method di bawah diikuti baris itu):

```php
    public function test_unreferenced_specialty_is_deleted_with_a_success_flash(): void
    {
        $specialty = $this->createSpecialty(['name' => 'Bisa Dihapus']);

        $this->actingAs($this->createStaff('content-admin'))
            ->delete(route('admin.specialties.destroy', $specialty))
            ->assertRedirect(route('admin.specialties.index'))
            ->assertSessionHas('success', 'Spesialisasi berhasil dihapus.');

        $this->assertDatabaseMissing('specialties', ['id' => $specialty->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'specialty.deleted']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function deleters(): array
    {
        return ['admin konten' => ['content-admin'], 'super admin' => ['super-admin']];
    }

    #[DataProvider('deleters')]
    public function test_specialty_used_by_a_doctor_is_not_deleted_even_for_super_admin(string $role): void
    {
        $specialty = $this->createSpecialty(['name' => 'Dipakai Dokter']);
        $this->createDoctor([], null, [$specialty]);
        $reason = 'Spesialisasi ini masih dipakai oleh dokter atau poliklinik sehingga tidak dapat dihapus. Nonaktifkan saja.';

        $this->actingAs($this->createStaff($role))
            ->delete(route('admin.specialties.destroy', $specialty))
            ->assertRedirect(route('admin.specialties.index'))
            ->assertSessionHas('error', $reason);

        $this->assertDatabaseHas('specialties', ['id' => $specialty->id]);
        $this->assertDatabaseCount('doctor_specialties', 1);

        $this->followingRedirects()
            ->delete(route('admin.specialties.destroy', $specialty))
            ->assertOk()
            ->assertSee('masih dipakai oleh dokter atau poliklinik');
    }

    #[DataProvider('deleters')]
    public function test_specialty_used_by_a_clinic_is_not_deleted(string $role): void
    {
        $specialty = $this->createSpecialty(['name' => 'Dipakai Poliklinik']);
        $clinic = $this->createClinic(['specialty_id' => $specialty->id]);

        $this->actingAs($this->createStaff($role))
            ->delete(route('admin.specialties.destroy', $specialty))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('specialties', ['id' => $specialty->id]);
        $this->assertSame($specialty->id, $clinic->fresh()->specialty_id);
    }

```

- [ ] **Step 13: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=AdminSpecialtyCrudTest
```
Expected: FAIL — `BadMethodCallException: Method App\Http\Controllers\Admin\SpecialtyController::destroy does not exist.` pada kelima tes baru.

- [ ] **Step 14: Tulis `destroy` (tool Edit `app/Http/Controllers/Admin/SpecialtyController.php`)**

`old`:
```php
            ->with('success', __('messages.updated', ['entity' => __('messages.entities.specialty')]));
    }
}
```
`new`:
```php
            ->with('success', __('messages.updated', ['entity' => __('messages.entities.specialty')]));
    }

    /**
     * Penolakan di controller (bukan Policy) supaya berlaku juga untuk Super Admin (F2-R18).
     */
    public function destroy(Specialty $specialty): RedirectResponse
    {
        $this->authorize('delete', $specialty);

        $reason = $specialty->deletionBlockedReason();

        if ($reason !== null) {
            return redirect()->route('admin.specialties.index')->with('error', __('messages.delete_blocked', ['reason' => $reason]));
        }

        DB::transaction(fn (): ?bool => $specialty->delete());

        return redirect()->route('admin.specialties.index')
            ->with('success', __('messages.deleted', ['entity' => __('messages.entities.specialty')]));
    }
}
```

- [ ] **Step 15: Jalankan tes — harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="AdminSpecialty"
& $PHP artisan test --filter=AdminRouteMiddlewareTest
```
Expected: PASS untuk ketiga kelas `AdminSpecialty*`; `AdminRouteMiddlewareTest` (KNS-02) tetap PASS karena semua route `admin.specialties.*` ber-`permission:clinics.manage` di grup G-ADMIN.

- [ ] **Step 16: Seluruh suite, Pint, commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test
& $PHP vendor\bin\pint
& $PHP vendor\bin\pint --test
git add app/Http/Controllers/Admin/SpecialtyController.php tests/Feature/Admin/AdminSpecialtyCrudTest.php
git commit -m @'
feat(fase-2): hapus spesialisasi ditolak bila dirujuk, termasuk untuk Super Admin

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
'@
```
Expected: seluruh suite PASS; Pint PASS.

---

### Task 10: Admin Poliklinik (#34, tab Poliklinik)

**Files:**
- Create: `app/Http/Requests/Admin/ClinicRequest.php`
- Create: `app/Http/Controllers/Admin/ClinicController.php`
- Create: `resources/views/admin/clinics/index.blade.php`, `create.blade.php`, `edit.blade.php`, `_form.blade.php`
- Modify: `routes/admin.php` (import `ClinicController` + `Route::resource('poliklinik', …)`)
- Modify: `resources/scss/_admin.scss` (kelas `.admin-thumb`, dipakai juga Task 11)
- Test: `tests/Feature/Admin/AdminClinicIndexTest.php`, `tests/Feature/Admin/AdminClinicCrudTest.php`, `tests/Feature/Admin/AdminClinicValidationTest.php`

**Interfaces:**
- Consumes:
  - Task 1: `Clinic` (`$fillable` 11 §5, cast `type` → `ClinicType`, mutator `code` huruf kapital, relasi `specialty()`/`doctors()`, scope `active()`, `bookable()`, `ordered()`, `deletionBlockedReason()` = "Poliklinik ini masih dirujuk oleh data dokter sehingga tidak dapat dihapus. Nonaktifkan saja."), `ClinicFactory` (`support()`, `inactive()`), `InteractsWithMasterData`.
  - Task 2: `HasUniqueSlug`, `SanitizesRichText` (kolom `description` dibersihkan `HtmlSanitizer` saat `saving`, F2-R14), `HasMedia` (`media()`, `firstMedia('cover')`, `mediaIn('gallery')`, hook `deleting` → `MediaService::deleteFor`).
  - Task 3: `AuditObserver` (`clinic.created|updated|deleted`), `messages.*`, `messages.entities.clinic` = "Poliklinik".
  - Task 4: `ClinicPolicy` (`clinics.manage`).
  - Task 5: `x-module-tabs`, `x-data-table` (`preserve`).
  - Task 6: route `admin.media.update` (PATCH, `alt_text`, `caption?`), `admin.media.destroy` (DELETE), `admin.media.reorder` (POST, `ids[]`); semuanya `back()` + flash bila bukan JSON. Galat validasi pada form HTML biasa juga kembali sebagai redirect + `$errors` berkat perubahan `shouldRenderJsonWhen` di Task 6 Step 7a, sehingga form galeri di bawah tidak menampilkan JSON mentah.
  - Task 7: `x-form.rich-text` (`name`, `label`, `value`, `help`, `maxlength`; penanda `data-rich-text`).
  - Task 9: `MasterDataIndexRequest::applyTo()`.
  - Fase 1: `MediaService::store()/replace()` (final, constructor `(AuditLogger $audit)`), `ImageRules::for()/messages()`, `x-image`, `x-form.file|select|input|textarea|checkbox`, `Tests\Support\ImageFixtures`, `InteractsWithMedia`.
- Produces:
  - `App\Http\Requests\Admin\ClinicRequest` (V-22 + F2-R13/F2-R15/F2-R23; satu kelas untuk tambah dan ubah): `rules()`, `messages()`, `clinicAttributes(): array`, `coverFile(): ?UploadedFile`, `coverAlt(): string`, `galleryFiles(): array<int, UploadedFile>` (kunci = indeks input), `galleryAlt(int|string $index): string`, titik sambung `protected function clinicHasAppointments(Clinic $clinic): bool` (fase 2 selalu `false`; fase 3 mengisinya, BR-13/I-31).
  - `App\Http\Controllers\Admin\ClinicController`: `index`, `create`, `store`, `edit`, `update`, `destroy` (tanpa `show`).
  - Route (di grup `permission:clinics.manage`): `admin.clinics.index|create|store|edit|update|destroy` → `/admin/poliklinik`, `/admin/poliklinik/buat`, `/admin/poliklinik/{clinic}/ubah`, dst. Grup ini ditaruh **sebelum** grup spesialisasi.
  - View `admin.clinics.index|create|edit` dan partial `admin.clinics._form` (`$clinic` nullable, `$specialties` larik `id => nama`, `$action`, `$submitLabel`).
  - Nama field unggahan (F2-R15): `cover` + `cover_alt`; `gallery[n]` + `gallery_alt[n]` (n = indeks yang sama, maks. 20 berkas per poliklinik termasuk yang sudah ada).
  - Flash: "Poliklinik berhasil ditambahkan." / "… diperbarui." / "… dihapus."; hapus ditolak = persis `Clinic::deletionBlockedReason()` (juga untuk Super Admin).
  - Breadcrumb: "Dasbor › Poliklinik dan Spesialisasi › Poliklinik" (index), "… › Poliklinik › Tambah", "… › Poliklinik › Ubah {nama}".

**Pesan galat (sumber 12 V-22, dengan F2-R13 dan F2-R23):**

| Kunci | Pesan |
|---|---|
| `name` | "Nama poliklinik wajib diisi." / "Nama poliklinik harus berupa teks." / "Nama poliklinik minimal 3 karakter." / "Nama poliklinik maksimal 100 karakter." / "Nama poliklinik sudah digunakan." |
| `code` | "Kode poliklinik wajib diisi." / "Kode poliklinik harus berupa teks." / "Kode poliklinik harus 2 sampai 5 huruf kapital." / "Kode poliklinik sudah digunakan." / "Kode poliklinik tidak dapat diubah karena sudah dipakai pada nomor antrean." |
| `type` | "Jenis layanan wajib dipilih." / "Jenis layanan tidak valid." |
| `is_bookable` | "Status pendaftaran tidak valid." / "Layanan penunjang tidak dapat dibuka untuk pendaftaran kunjungan." |
| `specialty_id` | "Spesialisasi harus berupa angka bulat." / "Spesialisasi yang dipilih tidak tersedia." |
| `short_description` | "Deskripsi singkat wajib diisi." / "Deskripsi singkat harus berupa teks." / "Deskripsi singkat maksimal 255 karakter." |
| `description` | "Deskripsi harus berupa teks." / "Deskripsi maksimal 30.000 karakter." (F2-R13 menimpa 50.000) |
| `location` | "Lokasi wajib diisi." / "Lokasi harus berupa teks." / "Lokasi maksimal 100 karakter." |
| `is_active` | "Status aktif tidak valid." |
| `sort_order` | "Urutan harus berupa angka bulat." / "Urutan harus antara 0 dan 9999." |
| `meta_title` / `meta_description` | "Meta title maksimal 70 karakter." / "Meta description maksimal 160 karakter." (+ pesan "… harus berupa teks.") |
| `cover` | pesan `ImageRules::messages('cover', 'cover')` (format, 2 MB, "Dimensi gambar minimal 800 x 450 piksel dan maksimal 6000 x 6000 piksel.") |
| `cover_alt` | "Teks alternatif gambar wajib diisi." / "Teks alternatif gambar harus berupa teks." / "Teks alternatif gambar maksimal 255 karakter." |
| `gallery` | "Galeri tidak valid." / "Galeri maksimal 20 gambar." |
| `gallery.*` | pesan `ImageRules::messages('gallery.*', 'gallery')` (minimal 600 x 400) |
| `gallery_alt.*` | "Teks alternatif gambar wajib diisi." / "Teks alternatif gambar maksimal 255 karakter." |

**Test IDs:** CRD-02 (tambah dengan `code` unik, ubah, `is_bookable=false`, `code` ganda ditolak; bagian publik #4/#5 ada di Task 13), MED-01 (cover), CRD-13 (bagian `clinics`; file media ikut terhapus), AKS (#34), VAL (V-22), SEK-08 (bagian admin: `description` tersanitasi lewat form), Review Focus 2, 3, dan 5.

**Catatan teknis (diverifikasi dari source):**
1. `Rule::prohibitedIf` dari 12 V-22 tidak dapat dipakai bersama `required`: form selalu mengirim `is_bookable` (hidden `0` + checkbox), dan nilai `"0"` dianggap *ada* sehingga `prohibited` selalu gagal untuk penunjang. Aturan BR-14 diterapkan sebagai closure: gagal hanya bila `type = penunjang` dan nilai bertipe benar. Pesan tetap teks V-22.
2. Daftar berkas galeri dibaca dari `$this->file('gallery')`. Input kosong tidak menghasilkan entri, tetapi **kunci** indeks asli dipertahankan, sehingga `gallery_alt.{n}` selalu sejajar dengan `gallery.{n}` (form memakai indeks eksplisit `gallery[0]`, `gallery[1]`, …).
3. `MediaService::replace()` dipakai juga saat tambah (tidak ada media lama): satu jalur untuk koleksi tunggal. File lama dihapus lewat `DB::afterCommit`, jadi seluruh penyimpanan dibungkus satu `DB::transaction` di controller. Hapus poliklinik juga dibungkus transaksi karena `deleteFor()` wajib dipanggil di dalam transaksi (carry fase 1).
4. Sanitasi `description` hanya di trait (F2-R14); `max:30000` diterapkan pada input mentah. Quill menghasilkan `<p><br></p>` untuk editor kosong; normalisasi ke `null` adalah tugas `SanitizesRichText` (Task 2).
5. Form galeri di halaman ubah **tidak boleh bersarang** di form utama (HTML melarang `<form>` di dalam `<form>`), sehingga kartu "Galeri" diletakkan setelah form utama dan setiap aksi (ubah teks alternatif, naik/turun, hapus) adalah form tersendiri ke `admin.media.*`.
6. `x-image` mewajibkan `alt`: gambar kecil di tabel dibuat `decorative` karena nama poliklinik ada di sel yang sama.
7. Tes yang menyentuh media memakai `Storage::fake('public')` dan `ImageFixtures`; tes tidak membaca relasi lewat properti (mode ketat): media dicari lewat `Media::query()`.
8. Titik sambung `clinicHasAppointments()` diuji dengan subkelas anonim yang diikat ke container (`$this->app->bind(ClinicRequest::class, …)`); `FormRequestServiceProvider` mengisi data request ke instance mana pun yang di-*resolve*.

- [ ] **Step 1: Verifikasi prasyarat (Task 1–9)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git branch --show-current
git status --short
foreach ($p in 'app\Models\Clinic.php', 'app\Policies\ClinicPolicy.php', 'app\Http\Controllers\Admin\SpecialtyController.php', 'app\Http\Controllers\Admin\MediaController.php', 'app\Http\Requests\Admin\MasterDataIndexRequest.php', 'resources\views\components\form\rich-text.blade.php') { "{0} => {1}" -f $p, (Test-Path $p) }
& $PHP artisan route:list --name=admin.media
& $PHP artisan test
```
Expected: `fase-2-master-data`; bersih; semua `True`; `route:list` memuat `admin.media.editor-upload|reorder|update|destroy`; seluruh suite PASS.

**Siklus A — daftar, route, `.admin-thumb`**

- [ ] **Step 2: Tulis tes yang gagal (tool Write) `tests/Feature/Admin/AdminClinicIndexTest.php`**

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\Clinic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithMedia;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

class AdminClinicIndexTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithMedia;
    use InteractsWithStaff;
    use RefreshDatabase;

    /**
     * @return array<string, array{string, int}>
     */
    public static function staffRoles(): array
    {
        return [
            'super admin' => ['super-admin', 200],
            'admin konten' => ['content-admin', 200],
            'petugas pendaftaran' => ['registration-officer', 403],
        ];
    }

    #[DataProvider('staffRoles')]
    public function test_index_access_per_staff_role(string $role, int $status): void
    {
        $this->createClinic(['name' => 'Poliklinik Anak']);

        $this->actingAs($this->createStaff($role))
            ->get(route('admin.clinics.index'))
            ->assertStatus($status);
    }

    public function test_patient_gets_403(): void
    {
        $this->actingAs(User::factory()->patient()->create())
            ->get(route('admin.clinics.index'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $this->get(route('admin.clinics.index'))->assertRedirect(route('admin.login'));
    }

    public function test_urls_use_the_indonesian_segments(): void
    {
        $clinic = $this->createClinic();

        $this->assertSame(url('/admin/poliklinik'), route('admin.clinics.index'));
        $this->assertSame(url('/admin/poliklinik/buat'), route('admin.clinics.create'));
        $this->assertSame(url('/admin/poliklinik/'.$clinic->id.'/ubah'), route('admin.clinics.edit', $clinic));
    }

    public function test_index_renders_many_rows_with_specialty_cover_and_doctor_counts_in_strict_mode(): void
    {
        $anak = $this->createSpecialty(['name' => 'Anak']);
        $mata = $this->createSpecialty(['name' => 'Mata']);
        $clinicA = $this->createClinic(['name' => 'Poliklinik Anak', 'code' => 'ANK', 'specialty_id' => $anak->id, 'location' => 'Gedung A Lt. 1']);
        $clinicB = $this->createClinic(['name' => 'Poliklinik Mata', 'code' => 'MTA', 'specialty_id' => $mata->id]);
        $clinicC = Clinic::factory()->support()->create(['name' => 'Laboratorium', 'code' => 'LAB', 'specialty_id' => null]);
        $clinicD = $this->createClinic(['name' => 'Poliklinik Lama', 'code' => 'LMA', 'specialty_id' => null, 'is_active' => false]);

        $this->createDoctor([], $clinicA, [$anak]);
        $this->createDoctor([], $clinicA, [$anak]);
        $this->createDoctor([], $clinicB, [$mata]);

        foreach ([$clinicA, $clinicB] as $clinic) {
            $this->createMediaRow([
                'model_type' => Clinic::class,
                'model_id' => $clinic->id,
                'collection' => 'cover',
                'alt_text' => 'Foto '.$clinic->name,
            ]);
        }

        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.clinics.index'))
            ->assertOk()
            ->assertSee('Poliklinik Anak')
            ->assertSee('Poliklinik Mata')
            ->assertSee('Laboratorium')
            ->assertSee('Poliklinik Lama')
            ->assertSee('ANK')
            ->assertSee('Layanan penunjang')
            ->assertSee('Gedung A Lt. 1')
            ->assertSee('Masih dirujuk oleh 2 dokter.')
            ->assertSee('Masih dirujuk oleh 1 dokter.')
            ->assertSee('data-confirm-action="'.route('admin.clinics.destroy', $clinicC).'"', false)
            ->assertSee('data-confirm-action="'.route('admin.clinics.destroy', $clinicD).'"', false)
            ->assertDontSee('data-confirm-action="'.route('admin.clinics.destroy', $clinicA).'"', false);
    }

    public function test_index_shows_both_module_tabs_breadcrumb_and_add_button(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.clinics.index'))
            ->assertOk()
            ->assertSee('href="'.route('admin.clinics.index').'"', false)
            ->assertSee('href="'.route('admin.specialties.index').'"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('Tambah Poliklinik')
            ->assertSee('href="'.route('admin.clinics.create').'"', false)
            ->assertSee('Poliklinik dan Spesialisasi');
    }

    public function test_search_matches_name_and_code_and_filters_by_status_and_sort(): void
    {
        $this->createClinic(['name' => 'Poliklinik Anak', 'code' => 'ANK', 'sort_order' => 2]);
        $this->createClinic(['name' => 'Poliklinik Mata', 'code' => 'MTA', 'sort_order' => 1]);
        $this->createClinic(['name' => 'Poliklinik Lama', 'code' => 'LMA', 'sort_order' => 3, 'is_active' => false]);
        $this->actingAs($this->createStaff('content-admin'));

        $this->get(route('admin.clinics.index', ['q' => 'mta']))
            ->assertOk()->assertSee('Poliklinik Mata')->assertDontSee('Poliklinik Anak');

        $this->get(route('admin.clinics.index', ['q' => 'anak']))
            ->assertOk()->assertSee('Poliklinik Anak')->assertDontSee('Poliklinik Mata');

        $this->get(route('admin.clinics.index', ['status' => 'nonaktif']))
            ->assertOk()->assertSee('Poliklinik Lama')->assertDontSee('Poliklinik Anak');

        $this->get(route('admin.clinics.index'))
            ->assertSeeInOrder(['Poliklinik Mata', 'Poliklinik Anak', 'Poliklinik Lama']);

        $this->get(route('admin.clinics.index', ['urut' => 'nama']))
            ->assertSeeInOrder(['Poliklinik Anak', 'Poliklinik Lama', 'Poliklinik Mata']);
    }

    public function test_invalid_filters_are_ignored_and_the_sort_is_preserved_by_the_search_form(): void
    {
        $this->createClinic(['name' => 'Poliklinik Anak']);
        $this->actingAs($this->createStaff('content-admin'));

        $this->get(route('admin.clinics.index', ['status' => 'zzz', 'kembali' => 'https://situs-lain.test']))
            ->assertOk()
            ->assertSee('Filter tidak valid dan diabaikan.')
            ->assertSee('Poliklinik Anak')
            ->assertDontSee('situs-lain.test');

        $this->get(route('admin.clinics.index', ['urut' => 'urutan']))
            ->assertOk()
            ->assertSee('name="urut"', false)
            ->assertSee('value="urutan"', false);
    }

    public function test_empty_list_shows_the_empty_state(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.clinics.index'))
            ->assertOk()
            ->assertSee('Belum ada poliklinik');
    }
}
```

- [ ] **Step 3: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=AdminClinicIndexTest
```
Expected: FAIL — `Symfony\Component\Routing\Exception\RouteNotFoundException: Route [admin.clinics.index] not defined.`

- [ ] **Step 4: Tulis implementasi daftar**

Tool Edit `resources/scss/_admin.scss` — `old`:
```scss
.stat-card {
  height: 100%;
```
`new`:
```scss
// Gambar kecil pada baris tabel admin (dokter, poliklinik).
.admin-thumb {
  flex: 0 0 3rem;
  width: 3rem;
}

.stat-card {
  height: 100%;
```

Tool Write `app/Http/Controllers/Admin/ClinicController.php` (versi Siklus A):

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MasterDataIndexRequest;
use App\Models\Clinic;
use Illuminate\View\View;

class ClinicController extends Controller
{
    public function index(MasterDataIndexRequest $request): View
    {
        $this->authorize('viewAny', Clinic::class);

        $clinics = $request
            ->applyTo(Clinic::query()->with(['specialty', 'media'])->withCount('doctors'), ['name', 'code'])
            ->paginate($request->perPage());

        return view('admin.clinics.index', ['clinics' => $clinics]);
    }
}
```

Tool Edit `routes/admin.php` — dua penyisipan:

1. Import. `old`: `use App\Http\Controllers\Admin\DashboardController;` → `new`:
```php
use App\Http\Controllers\Admin\ClinicController;
use App\Http\Controllers\Admin\DashboardController;
```
2. Route, sebelum blok spesialisasi. `old`:
```php
    // Spesialisasi (halaman #34, tab Spesialisasi): izin tunggal clinics.manage, tanpa show (11 §3.4).
```
`new`:
```php
    // Poliklinik (halaman #34, tab Poliklinik): izin tunggal clinics.manage, tanpa show (11 §3.4).
    Route::middleware('permission:clinics.manage')->group(function (): void {
        Route::resource('poliklinik', ClinicController::class)
            ->names('clinics')
            ->parameters(['poliklinik' => 'clinic'])
            ->except(['show']);
    });

    // Spesialisasi (halaman #34, tab Spesialisasi): izin tunggal clinics.manage, tanpa show (11 §3.4).
```

Tool Write `resources/views/admin/clinics/index.blade.php`:

```blade
@php
    $currentSort = (string) request()->query('urut', '');
    $statusNow = (string) request()->query('status', '');
    $sortUrl = fn (string $key): string => request()->fullUrlWithQuery(['urut' => $key, 'kembali' => null, 'page' => null]);
    $breadcrumbItems = [
        ['label' => 'Poliklinik dan Spesialisasi', 'url' => route('admin.clinics.index')],
        ['label' => 'Poliklinik'],
    ];
@endphp
<x-layouts.admin title="Poliklinik dan Spesialisasi">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="$breadcrumbItems" />
    </x-slot:breadcrumb>

    <x-slot:actions>
        <a href="{{ route('admin.clinics.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg" aria-hidden="true"></i> Tambah Poliklinik
        </a>
    </x-slot:actions>

    <div class="mb-3">
        <x-module-tabs group="clinics" />
    </div>

    <x-data-table
        :rows="$clinics"
        :preserve="['urut']"
        search-placeholder="Cari nama atau kode…"
        caption="Daftar poliklinik dan layanan"
        empty-title="Belum ada poliklinik"
        empty-message="Tambahkan poliklinik atau layanan penunjang yang tampil di halaman Layanan.">
        <x-slot:filters>
            <div class="col-12 col-md-3 col-lg-2">
                <label for="filter-status" class="form-label fw-semibold">Status</label>
                <select id="filter-status" name="status" class="form-select">
                    <option value="">Semua</option>
                    <option value="aktif" @selected($statusNow === 'aktif')>Aktif</option>
                    <option value="nonaktif" @selected($statusNow === 'nonaktif')>Nonaktif</option>
                </select>
            </div>
        </x-slot:filters>

        <x-slot:actions>
            <nav aria-label="Urutkan daftar" class="d-flex flex-wrap align-items-center gap-2">
                <span class="text-secondary">Urutkan:</span>
                @foreach (['nama' => 'Nama', 'urutan' => 'Urutan tampil'] as $key => $label)
                    <a href="{{ $sortUrl($key) }}" class="btn btn-sm {{ $currentSort === $key ? 'btn-primary' : 'btn-outline-secondary' }}"@if ($currentSort === $key) aria-current="true"@endif>{{ $label }}</a>
                @endforeach
            </nav>
        </x-slot:actions>

        <x-slot:head>
            <th scope="col">Poliklinik</th>
            <th scope="col">Kode</th>
            <th scope="col">Jenis</th>
            <th scope="col">Dapat didaftar</th>
            <th scope="col">Spesialisasi</th>
            <th scope="col">Lokasi</th>
            <th scope="col">Dokter</th>
            <th scope="col">Status</th>
            <th scope="col">Urutan</th>
            <th scope="col">Aksi</th>
        </x-slot:head>

        @foreach ($clinics as $clinic)
            <tr>
                <td data-label="Poliklinik">
                    <div class="d-flex align-items-center gap-2">
                        <div class="admin-thumb">
                            <x-image type="clinic" :media="$clinic->firstMedia('cover')" variant="thumb" ratio="4x3" :decorative="true" />
                        </div>
                        <span class="fw-semibold">{{ $clinic->name }}</span>
                    </div>
                </td>
                <td data-label="Kode"><code>{{ $clinic->code }}</code></td>
                <td data-label="Jenis">{{ $clinic->type->label() }}</td>
                <td data-label="Dapat didaftar">{{ $clinic->is_bookable ? 'Ya' : 'Tidak' }}</td>
                <td data-label="Spesialisasi">{{ $clinic->specialty?->name ?? '—' }}</td>
                <td data-label="Lokasi">{{ $clinic->location }}</td>
                <td data-label="Dokter">{{ $clinic->doctors_count }}</td>
                <td data-label="Status">
                    @if ($clinic->is_active)
                        <span class="badge text-bg-success">Aktif</span>
                    @else
                        <span class="badge text-bg-secondary">Nonaktif</span>
                    @endif
                </td>
                <td data-label="Urutan">{{ $clinic->sort_order }}</td>
                <td data-label="Aksi" class="table-actions">
                    <a href="{{ route('admin.clinics.edit', $clinic) }}" class="btn btn-sm btn-outline-primary" aria-label="Ubah {{ $clinic->name }}">Ubah</a>
                    @if ($clinic->doctors_count > 0)
                        <a href="{{ route('admin.clinics.edit', $clinic) }}" class="btn btn-sm btn-outline-secondary" aria-label="Nonaktifkan {{ $clinic->name }} (masih dirujuk)">Nonaktifkan</a>
                        <span class="small text-secondary align-self-center">Masih dirujuk oleh {{ $clinic->doctors_count }} dokter.</span>
                    @else
                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modal-hapus-poliklinik" data-confirm-action="{{ route('admin.clinics.destroy', $clinic) }}" data-confirm-message="Hapus {{ $clinic->name }}? Foto sampul dan galerinya ikut terhapus. Tindakan ini tidak dapat dibatalkan." aria-label="Hapus {{ $clinic->name }}">Hapus</button>
                    @endif
                </td>
            </tr>
        @endforeach
    </x-data-table>

    <x-confirm-modal
        id="modal-hapus-poliklinik"
        title="Hapus poliklinik?"
        message="Poliklinik akan dihapus permanen."
        confirm-label="Ya, hapus"
        variant="danger"
        method="DELETE" />
</x-layouts.admin>
```

- [ ] **Step 5: Jalankan tes — harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="AdminClinicIndexTest|AdminSpecialtyIndexTest"
& $PHP artisan route:list --path=admin/poliklinik
npm run build
```
Expected: PASS (11 tes `AdminClinicIndexTest` ditambah tes spesialisasi yang tetap hijau; tab spesialisasi kini juga memuat tautan Poliklinik); `route:list` memuat route `admin.clinics.*` tanpa `show`; `npm run build` sukses.

- [ ] **Step 6: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint
& $PHP vendor\bin\pint --test
git add app/Http/Controllers/Admin/ClinicController.php routes/admin.php resources/scss/_admin.scss resources/views/admin/clinics/index.blade.php tests/Feature/Admin/AdminClinicIndexTest.php
git commit -m @'
feat(fase-2): daftar admin poliklinik dengan tab modul, filter, dan hitungan dokter

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
'@
```
Expected: `pint` tanpa `--test` dijalankan lebih dulu dan merapikan berkas yang disentuh (mis. urutan impor `routes/admin.php` atau `DoctorController` yang baru ditambah); lalu `pint --test` PASS sebelum commit.

**Siklus B — tambah dan ubah, `ClinicRequest`, cover, galeri, sanitasi**

- [ ] **Step 7: Tulis tes yang gagal (tool Write) `tests/Feature/Admin/AdminClinicCrudTest.php`**

```php
<?php

namespace Tests\Feature\Admin;

use App\Enums\ClinicType;
use App\Http\Requests\Admin\ClinicRequest;
use App\Models\Clinic;
use App\Models\Media;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ImageFixtures;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithMedia;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

class AdminClinicCrudTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithMedia;
    use InteractsWithStaff;
    use RefreshDatabase;

    private Specialty $specialty;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->specialty = $this->createSpecialty(['name' => 'Anak']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Poliklinik Contoh',
            'code' => 'XYZ',
            'type' => 'poliklinik',
            'is_bookable' => '1',
            'specialty_id' => (string) $this->specialty->id,
            'short_description' => 'Layanan contoh untuk pengujian.',
            'description' => '<p>Deskripsi <strong>lengkap</strong>.</p>',
            'location' => 'Gedung A Lt. 1',
            'is_active' => '1',
            'sort_order' => '3',
        ], $overrides);
    }

    public function test_create_form_renders_with_rich_text_cover_gallery_and_no_slug_field(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.clinics.create'))
            ->assertOk()
            ->assertSee('Tambah Poliklinik')
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('data-rich-text', false)
            ->assertSee('name="code"', false)
            ->assertSee('name="type"', false)
            ->assertSee('name="cover"', false)
            ->assertSee('name="cover_alt"', false)
            ->assertSee('name="gallery[0]"', false)
            ->assertSee('name="gallery_alt[0]"', false)
            ->assertDontSee('name="slug"', false);
    }

    public function test_content_admin_adds_a_clinic_with_unique_code_crd02(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.clinics.store'), $this->payload())
            ->assertRedirect(route('admin.clinics.index'))
            ->assertSessionHas('success', 'Poliklinik berhasil ditambahkan.');

        $clinic = Clinic::query()->where('code', 'XYZ')->sole();

        $this->assertSame('poliklinik-contoh', $clinic->slug);
        $this->assertSame(ClinicType::Poliklinik, $clinic->type);
        $this->assertTrue($clinic->is_bookable);
        $this->assertTrue($clinic->is_active);
        $this->assertSame($this->specialty->id, $clinic->specialty_id);
        $this->assertSame(3, $clinic->sort_order);
        $this->assertNull($clinic->meta_title);
        $this->assertStringContainsString('<strong>lengkap</strong>', (string) $clinic->description);
        $this->assertDatabaseHas('audit_logs', ['action' => 'clinic.created']);
    }

    public function test_code_is_normalized_to_uppercase_and_duplicates_are_rejected_case_insensitively(): void
    {
        $admin = $this->createStaff('content-admin');

        $this->actingAs($admin)->post(route('admin.clinics.store'), $this->payload(['code' => 'xyz']))
            ->assertRedirect(route('admin.clinics.index'));

        $this->assertDatabaseHas('clinics', ['code' => 'XYZ']);

        $this->post(route('admin.clinics.store'), $this->payload(['name' => 'Poliklinik Lain', 'code' => 'xyz']))
            ->assertSessionHasErrors(['code' => 'Kode poliklinik sudah digunakan.']);

        $this->assertSame(1, Clinic::query()->count());
    }

    public function test_description_is_sanitized_when_saved_through_the_form(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.clinics.store'), $this->payload([
                'description' => '<p>Aman</p><script>alert(1)</script><img src="x" onerror="alert(1)"><a href="javascript:alert(1)">klik</a>',
            ]))
            ->assertRedirect(route('admin.clinics.index'));

        $description = (string) Clinic::query()->where('code', 'XYZ')->value('description');

        $this->assertStringContainsString('Aman', $description);
        $this->assertStringNotContainsString('<script', $description);
        $this->assertStringNotContainsString('onerror', $description);
        $this->assertStringNotContainsString('javascript:', $description);
    }

    public function test_support_service_cannot_be_bookable_but_can_be_saved_as_not_bookable(): void
    {
        $this->actingAs($this->createStaff('content-admin'));

        $this->post(route('admin.clinics.store'), $this->payload(['type' => 'penunjang', 'is_bookable' => '1']))
            ->assertSessionHasErrors(['is_bookable' => 'Layanan penunjang tidak dapat dibuka untuk pendaftaran kunjungan.']);

        $this->post(route('admin.clinics.store'), $this->payload(['type' => 'penunjang', 'is_bookable' => '0', 'specialty_id' => null]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.clinics.index'));

        $clinic = Clinic::query()->where('code', 'XYZ')->sole();
        $this->assertSame(ClinicType::Penunjang, $clinic->type);
        $this->assertFalse($clinic->is_bookable);
        $this->assertNull($clinic->specialty_id);
    }

    public function test_update_changes_fields_keeps_the_slug_and_can_close_online_booking_crd02(): void
    {
        $clinic = $this->createClinic(['name' => 'Poliklinik Lama', 'code' => 'LMA', 'is_bookable' => true]);
        $slug = $clinic->slug;
        $this->actingAs($this->createStaff('content-admin'));

        $this->put(route('admin.clinics.update', $clinic), $this->payload(['name' => 'Poliklinik Baru', 'code' => 'LMA', 'is_bookable' => '0']))
            ->assertRedirect(route('admin.clinics.index'))
            ->assertSessionHas('success', 'Poliklinik berhasil diperbarui.');

        $clinic->refresh();
        $this->assertSame('Poliklinik Baru', $clinic->name);
        $this->assertSame($slug, $clinic->slug);
        $this->assertFalse($clinic->is_bookable);
        $this->assertNotContains('Poliklinik Baru', Clinic::query()->bookable()->pluck('name')->all());
        $this->assertDatabaseHas('audit_logs', ['action' => 'clinic.updated']);

        $this->put(route('admin.clinics.update', $clinic), $this->payload(['name' => 'Poliklinik Baru', 'code' => 'LMA', 'is_active' => '0']))
            ->assertRedirect(route('admin.clinics.index'));

        $this->assertNotContains('Poliklinik Baru', Clinic::query()->active()->pluck('name')->all());
    }

    public function test_code_cannot_change_once_the_clinic_has_appointments(): void
    {
        $clinic = $this->createClinic(['name' => 'Poliklinik Terkunci', 'code' => 'ANK']);
        $this->app->bind(ClinicRequest::class, fn () => new class extends ClinicRequest
        {
            protected function clinicHasAppointments(Clinic $clinic): bool
            {
                return true;
            }
        });
        $this->actingAs($this->createStaff('content-admin'));

        $this->put(route('admin.clinics.update', $clinic), $this->payload(['name' => 'Poliklinik Terkunci', 'code' => 'ZZZ']))
            ->assertSessionHasErrors(['code' => 'Kode poliklinik tidak dapat diubah karena sudah dipakai pada nomor antrean.']);

        $this->put(route('admin.clinics.update', $clinic), $this->payload(['name' => 'Poliklinik Terkunci', 'code' => 'ANK']))
            ->assertSessionHasNoErrors();

        $this->assertSame('ANK', $clinic->fresh()->code);
    }

    public function test_cover_upload_on_create_stores_media_for_the_clinic_med01(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.clinics.store'), $this->payload([
                'cover' => ImageFixtures::jpeg(1200, 800, 'cover.jpg'),
                'cover_alt' => 'Foto sampul poliklinik',
            ]))
            ->assertRedirect(route('admin.clinics.index'));

        $clinic = Clinic::query()->where('code', 'XYZ')->sole();
        $media = Media::query()->where('collection', 'cover')->sole();

        $this->assertSame(Clinic::class, $media->model_type);
        $this->assertSame($clinic->id, $media->model_id);
        $this->assertSame('Foto sampul poliklinik', $media->alt_text);
        $this->assertSame('public', $media->disk);
        $this->assertStringStartsWith('cover/'.now()->format('Y/m'), $media->directory);
        $this->assertNotNull($media->thumb_path);
        Storage::disk('public')->assertExists($media->directory.'/'.$media->file_name);
        Storage::disk('public')->assertExists($media->thumb_path);
    }

    public function test_replacing_the_cover_removes_the_old_file_after_commit(): void
    {
        $admin = $this->createStaff('content-admin');
        $this->actingAs($admin)->post(route('admin.clinics.store'), $this->payload([
            'cover' => ImageFixtures::jpeg(1200, 800, 'lama.jpg'),
            'cover_alt' => 'Sampul lama',
        ]));
        $clinic = Clinic::query()->where('code', 'XYZ')->sole();
        $old = Media::query()->where('collection', 'cover')->sole();
        $oldPath = $old->directory.'/'.$old->file_name;

        $this->put(route('admin.clinics.update', $clinic), $this->payload([
            'cover' => ImageFixtures::jpeg(1000, 700, 'baru.jpg'),
            'cover_alt' => 'Sampul baru',
        ]))->assertRedirect(route('admin.clinics.index'));

        $new = Media::query()->where('collection', 'cover')->sole();

        $this->assertNotSame($old->id, $new->id);
        $this->assertSame('Sampul baru', $new->alt_text);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($new->directory.'/'.$new->file_name);
    }

    public function test_update_without_a_new_cover_keeps_the_current_one(): void
    {
        $this->actingAs($this->createStaff('content-admin'))->post(route('admin.clinics.store'), $this->payload([
            'cover' => ImageFixtures::jpeg(1200, 800),
            'cover_alt' => 'Sampul',
        ]));
        $clinic = Clinic::query()->where('code', 'XYZ')->sole();
        $media = Media::query()->where('collection', 'cover')->sole();

        $this->put(route('admin.clinics.update', $clinic), $this->payload(['name' => 'Nama Baru']))
            ->assertSessionHasNoErrors();

        $this->assertSame($media->id, Media::query()->where('collection', 'cover')->sole()->id);
        Storage::disk('public')->assertExists($media->directory.'/'.$media->file_name);
    }

    public function test_gallery_images_are_added_in_input_order_with_their_own_alt_text(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.clinics.store'), $this->payload([
                'gallery' => [0 => ImageFixtures::jpeg(900, 600, 'g1.jpg'), 2 => ImageFixtures::jpeg(900, 600, 'g2.jpg')],
                'gallery_alt' => [0 => 'Ruang tunggu', 1 => '', 2 => 'Ruang periksa'],
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.clinics.index'));

        $this->assertSame(
            ['Ruang tunggu', 'Ruang periksa'],
            Media::query()->where('collection', 'gallery')->orderBy('sort_order')->orderBy('id')->pluck('alt_text')->all(),
        );
    }

    public function test_gallery_image_without_alt_text_is_rejected_and_nothing_is_stored(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.clinics.store'), $this->payload([
                'gallery' => [0 => ImageFixtures::jpeg(900, 600)],
                'gallery_alt' => [0 => ''],
            ]))
            ->assertSessionHasErrors(['gallery_alt.0' => 'Teks alternatif gambar wajib diisi.']);

        $this->assertSame(0, Clinic::query()->count());
        $this->assertSame(0, Media::query()->count());
    }

    public function test_gallery_is_limited_to_20_images_including_existing_ones(): void
    {
        $clinic = $this->createClinic(['code' => 'GAL']);

        foreach (range(1, 19) as $position) {
            $this->createMediaRow([
                'model_type' => Clinic::class,
                'model_id' => $clinic->id,
                'collection' => 'gallery',
                'sort_order' => $position,
            ]);
        }

        $this->actingAs($this->createStaff('content-admin'))
            ->put(route('admin.clinics.update', $clinic), $this->payload([
                'name' => $clinic->name,
                'code' => 'GAL',
                'gallery' => [0 => ImageFixtures::jpeg(900, 600), 1 => ImageFixtures::jpeg(900, 600)],
                'gallery_alt' => [0 => 'Satu', 1 => 'Dua'],
            ]))
            ->assertSessionHasErrors(['gallery' => 'Galeri maksimal 20 gambar.']);

        $this->assertSame(19, Media::query()->where('collection', 'gallery')->count());
    }

    public function test_edit_form_shows_values_cover_and_gallery_management_forms(): void
    {
        $clinic = $this->createClinic(['name' => 'Poliklinik Anak', 'code' => 'ANK']);
        $first = $this->createMediaRow(['model_type' => Clinic::class, 'model_id' => $clinic->id, 'collection' => 'gallery', 'alt_text' => 'Foto pertama', 'sort_order' => 1]);
        $second = $this->createMediaRow(['model_type' => Clinic::class, 'model_id' => $clinic->id, 'collection' => 'gallery', 'alt_text' => 'Foto kedua', 'sort_order' => 2]);

        $this->actingAs($this->createStaff('super-admin'))
            ->get(route('admin.clinics.edit', $clinic))
            ->assertOk()
            ->assertSee('Ubah Poliklinik')
            ->assertSee('value="Poliklinik Anak"', false)
            ->assertSee('value="ANK"', false)
            ->assertSee('action="'.route('admin.clinics.update', $clinic).'"', false)
            ->assertSee('Foto pertama')
            ->assertSee('Foto kedua')
            ->assertSee('action="'.route('admin.media.update', $first).'"', false)
            ->assertSee('action="'.route('admin.media.reorder').'"', false)
            ->assertSee('data-confirm-action="'.route('admin.media.destroy', $second).'"', false);
    }

    public function test_deleting_an_unreferenced_clinic_removes_its_media_files_crd13(): void
    {
        $admin = $this->createStaff('content-admin');
        $this->actingAs($admin)->post(route('admin.clinics.store'), $this->payload([
            'cover' => ImageFixtures::jpeg(1200, 800),
            'cover_alt' => 'Sampul',
            'gallery' => [0 => ImageFixtures::jpeg(900, 600)],
            'gallery_alt' => [0 => 'Galeri'],
        ]));
        $clinic = Clinic::query()->where('code', 'XYZ')->sole();
        $paths = Media::query()->get()->flatMap(fn (Media $media): array => [$media->directory.'/'.$media->file_name, $media->thumb_path])->all();
        $this->assertCount(4, $paths);

        $this->delete(route('admin.clinics.destroy', $clinic))
            ->assertRedirect(route('admin.clinics.index'))
            ->assertSessionHas('success', 'Poliklinik berhasil dihapus.');

        $this->assertDatabaseMissing('clinics', ['id' => $clinic->id]);
        $this->assertSame(0, Media::query()->count());

        foreach ($paths as $path) {
            Storage::disk('public')->assertMissing($path);
        }

        $this->assertDatabaseHas('audit_logs', ['action' => 'clinic.deleted']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function deleters(): array
    {
        return ['admin konten' => ['content-admin'], 'super admin' => ['super-admin']];
    }

    #[DataProvider('deleters')]
    public function test_clinic_referenced_by_a_doctor_is_not_deleted_even_for_super_admin(string $role): void
    {
        $clinic = $this->createClinic(['name' => 'Poliklinik Dirujuk']);
        $this->createDoctor([], $clinic);

        $this->actingAs($this->createStaff($role))
            ->delete(route('admin.clinics.destroy', $clinic))
            ->assertRedirect(route('admin.clinics.index'))
            ->assertSessionHas('error', 'Poliklinik ini masih dirujuk oleh data dokter sehingga tidak dapat dihapus. Nonaktifkan saja.');

        $this->assertDatabaseHas('clinics', ['id' => $clinic->id]);
        $this->assertDatabaseCount('doctors', 1);
    }

    public function test_non_numeric_id_is_a_404(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get('/admin/poliklinik/abc/ubah')
            ->assertNotFound();
    }

    public function test_registration_officer_patient_and_guest_cannot_use_clinic_routes(): void
    {
        $clinic = $this->createClinic(['name' => 'Poliklinik Aman']);

        $requests = [
            ['GET', route('admin.clinics.create'), []],
            ['POST', route('admin.clinics.store'), $this->payload(['name' => 'Tidak Boleh', 'code' => 'TBH'])],
            ['GET', route('admin.clinics.edit', $clinic), []],
            ['PUT', route('admin.clinics.update', $clinic), $this->payload(['name' => 'Diubah Paksa', 'code' => 'DPK'])],
            ['DELETE', route('admin.clinics.destroy', $clinic), []],
        ];

        foreach ([$this->createStaff('registration-officer'), User::factory()->patient()->create()] as $user) {
            $this->actingAs($user);

            foreach ($requests as [$method, $url, $data]) {
                $this->call($method, $url, $data)->assertForbidden();
            }
        }

        $this->app['auth']->forgetGuards();

        foreach ($requests as [$method, $url, $data]) {
            $this->call($method, $url, $data)->assertRedirect(route('admin.login'));
        }

        $this->assertSame('Poliklinik Aman', $clinic->fresh()->name);
        $this->assertDatabaseMissing('clinics', ['name' => 'Tidak Boleh']);
        $this->assertDatabaseHas('clinics', ['id' => $clinic->id]);
    }
}
```

Tool Write `tests/Feature/Admin/AdminClinicValidationTest.php`:

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\Clinic;
use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ImageFixtures;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * 12 V-22 (dengan F2-R13, F2-R15, F2-R23): pesan Indonesia per field.
 */
class AdminClinicValidationTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithStaff;
    use RefreshDatabase;

    /**
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function invalidInputs(): array
    {
        return [
            'nama kosong' => [['name' => ''], 'name', 'Nama poliklinik wajib diisi.'],
            'nama bukan teks' => [['name' => ['a']], 'name', 'Nama poliklinik harus berupa teks.'],
            'nama terlalu pendek' => [['name' => 'Ab'], 'name', 'Nama poliklinik minimal 3 karakter.'],
            'nama terlalu panjang' => [['name' => str_repeat('a', 101)], 'name', 'Nama poliklinik maksimal 100 karakter.'],
            'nama ganda' => [['name' => 'Poliklinik Anak'], 'name', 'Nama poliklinik sudah digunakan.'],
            'kode kosong' => [['code' => ''], 'code', 'Kode poliklinik wajib diisi.'],
            'kode satu huruf' => [['code' => 'A'], 'code', 'Kode poliklinik harus 2 sampai 5 huruf kapital.'],
            'kode enam huruf' => [['code' => 'ABCDEF'], 'code', 'Kode poliklinik harus 2 sampai 5 huruf kapital.'],
            'kode berisi angka' => [['code' => 'AB1'], 'code', 'Kode poliklinik harus 2 sampai 5 huruf kapital.'],
            'kode ganda' => [['code' => 'ANK'], 'code', 'Kode poliklinik sudah digunakan.'],
            'jenis kosong' => [['type' => ''], 'type', 'Jenis layanan wajib dipilih.'],
            'jenis tidak dikenal' => [['type' => 'klinik'], 'type', 'Jenis layanan tidak valid.'],
            'pendaftaran tidak valid' => [['is_bookable' => 'mungkin'], 'is_bookable', 'Status pendaftaran tidak valid.'],
            'penunjang tidak boleh didaftar' => [['type' => 'penunjang', 'is_bookable' => '1'], 'is_bookable', 'Layanan penunjang tidak dapat dibuka untuk pendaftaran kunjungan.'],
            'spesialisasi tidak ada' => [['specialty_id' => '999999'], 'specialty_id', 'Spesialisasi yang dipilih tidak tersedia.'],
            'spesialisasi bukan angka' => [['specialty_id' => 'abc'], 'specialty_id', 'Spesialisasi harus berupa angka bulat.'],
            'deskripsi singkat kosong' => [['short_description' => ''], 'short_description', 'Deskripsi singkat wajib diisi.'],
            'deskripsi singkat terlalu panjang' => [['short_description' => str_repeat('a', 256)], 'short_description', 'Deskripsi singkat maksimal 255 karakter.'],
            'deskripsi terlalu panjang' => [['description' => str_repeat('a', 30001)], 'description', 'Deskripsi maksimal 30.000 karakter.'],
            'lokasi kosong' => [['location' => ''], 'location', 'Lokasi wajib diisi.'],
            'lokasi terlalu panjang' => [['location' => str_repeat('a', 101)], 'location', 'Lokasi maksimal 100 karakter.'],
            'status aktif hilang' => [['is_active' => null], 'is_active', 'Status aktif tidak valid.'],
            'urutan bukan angka' => [['sort_order' => 'abc'], 'sort_order', 'Urutan harus berupa angka bulat.'],
            'urutan terlalu besar' => [['sort_order' => '10000'], 'sort_order', 'Urutan harus antara 0 dan 9999.'],
            'meta title terlalu panjang' => [['meta_title' => str_repeat('a', 71)], 'meta_title', 'Meta title maksimal 70 karakter.'],
            'meta description terlalu panjang' => [['meta_description' => str_repeat('a', 161)], 'meta_description', 'Meta description maksimal 160 karakter.'],
            'alt sampul wajib' => [['cover' => 'FILE', 'cover_alt' => ''], 'cover_alt', 'Teks alternatif gambar wajib diisi.'],
            'alt sampul terlalu panjang' => [['cover' => 'FILE', 'cover_alt' => str_repeat('a', 256)], 'cover_alt', 'Teks alternatif gambar maksimal 255 karakter.'],
            'sampul terlalu kecil' => [['cover' => 'SMALL', 'cover_alt' => 'x'], 'cover', 'Dimensi gambar minimal 800 x 450 piksel dan maksimal 6000 x 6000 piksel.'],
            'sampul terlalu besar' => [['cover' => 'BIG', 'cover_alt' => 'x'], 'cover', 'Ukuran gambar maksimal 2 MB.'],
            'galeri terlalu kecil' => [['gallery' => ['SMALL'], 'gallery_alt' => ['x']], 'gallery.0', 'Dimensi gambar minimal 600 x 400 piksel dan maksimal 6000 x 6000 piksel.'],
        ];
    }

    /**
     * @param  array<string, mixed>  $override
     */
    #[DataProvider('invalidInputs')]
    public function test_store_rejects_invalid_input_with_indonesian_messages(array $override, string $field, string $message): void
    {
        Storage::fake('public');
        $this->createClinic(['name' => 'Poliklinik Anak', 'code' => 'ANK']);
        $override = array_map(fn ($value) => $this->materialize($value), $override);

        $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.clinics.create'))
            ->post(route('admin.clinics.store'), array_merge($this->valid(), $override))
            ->assertRedirect(route('admin.clinics.create'))
            ->assertSessionHasErrors([$field => $message]);

        $this->assertSame(1, Clinic::query()->count());
        $this->assertSame(0, Media::query()->count());
    }

    public function test_svg_and_non_image_uploads_are_rejected_without_storing_anything(): void
    {
        Storage::fake('public');
        $this->actingAs($this->createStaff('content-admin'));

        $this->post(route('admin.clinics.store'), array_merge($this->valid(), ['cover' => ImageFixtures::svgWithScript(), 'cover_alt' => 'x']))
            ->assertSessionHasErrors('cover');

        $this->post(route('admin.clinics.store'), array_merge($this->valid(), ['cover' => ImageFixtures::textNamedJpg(), 'cover_alt' => 'x']))
            ->assertSessionHasErrors('cover');

        $this->assertSame(0, Clinic::query()->count());
        $this->assertSame(0, Media::query()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_invalid_form_keeps_old_input_and_shows_an_error_summary(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.clinics.create'))
            ->post(route('admin.clinics.store'), array_merge($this->valid(), ['name' => '', 'location' => 'Gedung Lama']))
            ->assertRedirect(route('admin.clinics.create'));

        $this->get(route('admin.clinics.create'))
            ->assertOk()
            ->assertSee('data-errors-summary', false)
            ->assertSee('Nama poliklinik wajib diisi.')
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('name-error', false)
            ->assertSee('value="Gedung Lama"', false);
    }

    /**
     * @return array<string, mixed>
     */
    private function valid(): array
    {
        return [
            'name' => 'Poliklinik Baru',
            'code' => 'BRU',
            'type' => 'poliklinik',
            'is_bookable' => '1',
            'short_description' => 'Ringkasan layanan.',
            'location' => 'Gedung A Lt. 1',
            'is_active' => '1',
        ];
    }

    /**
     * Penanda berkas diubah menjadi UploadedFile di dalam tes (data provider dijalankan sebelum GD siap dipakai di setUp).
     */
    private function materialize(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($item) => $this->materialize($item), $value);
        }

        return match ($value) {
            'FILE' => ImageFixtures::jpeg(1200, 800),
            'SMALL' => ImageFixtures::jpeg(400, 300),
            'BIG' => ImageFixtures::oversizedJpeg(2100, 1200, 800),
            default => $value,
        };
    }
}
```

- [ ] **Step 8: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="AdminClinicCrudTest|AdminClinicValidationTest"
```
Expected: FAIL — `BadMethodCallException: Method App\Http\Controllers\Admin\ClinicController::create does not exist.` (dan `store`/`edit`/`update`/`destroy`); beberapa tes juga gagal dengan `Class "App\Http\Requests\Admin\ClinicRequest" not found`.

- [ ] **Step 9: Tulis `ClinicRequest`, controller lengkap, dan view**

Tool Write `app/Http/Requests/Admin/ClinicRequest.php`:

```php
<?php

namespace App\Http\Requests\Admin;

use App\Enums\ClinicType;
use App\Models\Clinic;
use App\Rules\ImageRules;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * V-22 + F2-R13 (description max 30000), F2-R15 (nama field unggahan), F2-R23 (meta_title max 70).
 * Satu kelas untuk tambah dan ubah; `slug` dibuat sistem (F2-R5).
 */
class ClinicRequest extends FormRequest
{
    public const MAX_GALLERY = 20;

    public function authorize(): bool
    {
        $user = $this->user();
        $clinic = $this->route('clinic');

        if ($user === null) {
            return false;
        }

        return $clinic instanceof Clinic
            ? $user->can('update', $clinic)
            : $user->can('create', Clinic::class);
    }

    protected function prepareForValidation(): void
    {
        $code = $this->input('code');

        if (is_string($code)) {
            $this->merge(['code' => Str::upper(trim($code))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $clinic = $this->route('clinic');
        $clinic = $clinic instanceof Clinic ? $clinic : null;

        $rules = [
            'name' => ['bail', 'required', 'string', 'min:3', 'max:100', Rule::unique('clinics', 'name')->ignore($clinic)],
            'code' => [
                'bail',
                'required',
                'string',
                'regex:/^[A-Z]{2,5}$/',
                Rule::unique('clinics', 'code')->ignore($clinic),
                $this->codeLockRule($clinic),
            ],
            'type' => ['required', Rule::enum(ClinicType::class)],
            'is_bookable' => ['required', 'boolean', $this->supportNotBookableRule()],
            'specialty_id' => ['nullable', 'integer', Rule::exists('specialties', 'id')],
            'short_description' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:30000'],
            'location' => ['required', 'string', 'max:100'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'between:0,9999'],
            'meta_title' => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:160'],
            'cover' => ['nullable', ...ImageRules::for('cover')],
            'cover_alt' => ['required_with:cover', 'nullable', 'string', 'max:255'],
            'gallery' => ['nullable', 'array', 'max:'.self::MAX_GALLERY],
            'gallery.*' => ImageRules::for('gallery'),
        ];

        foreach (array_keys($this->galleryFiles()) as $index) {
            $rules['gallery_alt.'.$index] = ['required', 'string', 'max:255'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(
            ImageRules::messages('cover', 'cover'),
            ImageRules::messages('gallery.*', 'gallery'),
            [
                'name.required' => 'Nama poliklinik wajib diisi.',
                'name.string' => 'Nama poliklinik harus berupa teks.',
                'name.min' => 'Nama poliklinik minimal 3 karakter.',
                'name.max' => 'Nama poliklinik maksimal 100 karakter.',
                'name.unique' => 'Nama poliklinik sudah digunakan.',
                'code.required' => 'Kode poliklinik wajib diisi.',
                'code.string' => 'Kode poliklinik harus berupa teks.',
                'code.regex' => 'Kode poliklinik harus 2 sampai 5 huruf kapital.',
                'code.unique' => 'Kode poliklinik sudah digunakan.',
                'type.required' => 'Jenis layanan wajib dipilih.',
                'type.enum' => 'Jenis layanan tidak valid.',
                'is_bookable.required' => 'Status pendaftaran tidak valid.',
                'is_bookable.boolean' => 'Status pendaftaran tidak valid.',
                'specialty_id.integer' => 'Spesialisasi harus berupa angka bulat.',
                'specialty_id.exists' => 'Spesialisasi yang dipilih tidak tersedia.',
                'short_description.required' => 'Deskripsi singkat wajib diisi.',
                'short_description.string' => 'Deskripsi singkat harus berupa teks.',
                'short_description.max' => 'Deskripsi singkat maksimal 255 karakter.',
                'description.string' => 'Deskripsi harus berupa teks.',
                'description.max' => 'Deskripsi maksimal 30.000 karakter.',
                'location.required' => 'Lokasi wajib diisi.',
                'location.string' => 'Lokasi harus berupa teks.',
                'location.max' => 'Lokasi maksimal 100 karakter.',
                'is_active.required' => 'Status aktif tidak valid.',
                'is_active.boolean' => 'Status aktif tidak valid.',
                'sort_order.integer' => 'Urutan harus berupa angka bulat.',
                'sort_order.between' => 'Urutan harus antara 0 dan 9999.',
                'meta_title.string' => 'Meta title harus berupa teks.',
                'meta_title.max' => 'Meta title maksimal 70 karakter.',
                'meta_description.string' => 'Meta description harus berupa teks.',
                'meta_description.max' => 'Meta description maksimal 160 karakter.',
                'cover_alt.required_with' => 'Teks alternatif gambar wajib diisi.',
                'cover_alt.string' => 'Teks alternatif gambar harus berupa teks.',
                'cover_alt.max' => 'Teks alternatif gambar maksimal 255 karakter.',
                'gallery.array' => 'Galeri tidak valid.',
                'gallery.max' => 'Galeri maksimal 20 gambar.',
                'gallery_alt.*.required' => 'Teks alternatif gambar wajib diisi.',
                'gallery_alt.*.string' => 'Teks alternatif gambar harus berupa teks.',
                'gallery_alt.*.max' => 'Teks alternatif gambar maksimal 255 karakter.',
            ],
        );
    }

    /**
     * Batas 20 foto berlaku untuk jumlah yang sudah ada ditambah yang baru diunggah.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $incoming = count($this->galleryFiles());

            if ($incoming === 0 || $validator->errors()->has('gallery')) {
                return;
            }

            $clinic = $this->route('clinic');
            $existing = $clinic instanceof Clinic ? $clinic->media()->where('collection', 'gallery')->count() : 0;

            if ($existing + $incoming > self::MAX_GALLERY) {
                $validator->errors()->add('gallery', 'Galeri maksimal 20 gambar.');
            }
        });
    }

    /**
     * Titik sambung fase 3 (BR-13, I-31): setelah tabel appointments ada, method ini mengembalikan
     * true bila poliklinik sudah memiliki appointment sehingga `code` tidak boleh berubah.
     */
    protected function clinicHasAppointments(Clinic $clinic): bool
    {
        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function clinicAttributes(): array
    {
        $data = $this->validated();

        return [
            'name' => (string) $data['name'],
            'code' => (string) $data['code'],
            'type' => (string) $data['type'],
            'is_bookable' => (bool) $data['is_bookable'],
            'specialty_id' => $data['specialty_id'] ?? null,
            'short_description' => (string) $data['short_description'],
            'description' => $data['description'] ?? null,
            'location' => (string) $data['location'],
            'is_active' => (bool) $data['is_active'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
        ];
    }

    public function coverFile(): ?UploadedFile
    {
        $file = $this->file('cover');

        return $file instanceof UploadedFile ? $file : null;
    }

    public function coverAlt(): string
    {
        return trim((string) $this->input('cover_alt'));
    }

    /**
     * @return array<int|string, UploadedFile>
     */
    public function galleryFiles(): array
    {
        $files = $this->file('gallery');

        return is_array($files) ? array_filter($files, fn ($file): bool => $file instanceof UploadedFile) : [];
    }

    public function galleryAlt(int|string $index): string
    {
        return trim((string) $this->input('gallery_alt.'.$index));
    }

    private function supportNotBookableRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($this->input('type') === ClinicType::Penunjang->value && filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
                $fail('Layanan penunjang tidak dapat dibuka untuk pendaftaran kunjungan.');
            }
        };
    }

    private function codeLockRule(?Clinic $clinic): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($clinic): void {
            if ($clinic !== null && $value !== $clinic->code && $this->clinicHasAppointments($clinic)) {
                $fail('Kode poliklinik tidak dapat diubah karena sudah dipakai pada nomor antrean.');
            }
        };
    }
}
```

Tool Write `app/Http/Controllers/Admin/ClinicController.php` (ganti seluruh isi):

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ClinicRequest;
use App\Http\Requests\Admin\MasterDataIndexRequest;
use App\Models\Clinic;
use App\Models\Specialty;
use App\Services\Media\MediaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ClinicController extends Controller
{
    public function index(MasterDataIndexRequest $request): View
    {
        $this->authorize('viewAny', Clinic::class);

        $clinics = $request
            ->applyTo(Clinic::query()->with(['specialty', 'media'])->withCount('doctors'), ['name', 'code'])
            ->paginate($request->perPage());

        return view('admin.clinics.index', ['clinics' => $clinics]);
    }

    public function create(): View
    {
        $this->authorize('create', Clinic::class);

        return view('admin.clinics.create', ['specialties' => $this->specialtyOptions()]);
    }

    public function store(ClinicRequest $request, MediaService $media): RedirectResponse
    {
        $this->authorize('create', Clinic::class);

        DB::transaction(function () use ($request, $media): void {
            $clinic = Clinic::query()->create($request->clinicAttributes());
            $this->saveMedia($request, $media, $clinic);
        });

        return redirect()->route('admin.clinics.index')
            ->with('success', __('messages.created', ['entity' => __('messages.entities.clinic')]));
    }

    public function edit(Clinic $clinic): View
    {
        $this->authorize('update', $clinic);

        return view('admin.clinics.edit', [
            'clinic' => $clinic->load('media'),
            'specialties' => $this->specialtyOptions(),
        ]);
    }

    public function update(ClinicRequest $request, Clinic $clinic, MediaService $media): RedirectResponse
    {
        $this->authorize('update', $clinic);

        DB::transaction(function () use ($request, $media, $clinic): void {
            $clinic->update($request->clinicAttributes());
            $this->saveMedia($request, $media, $clinic);
        });

        return redirect()->route('admin.clinics.index')
            ->with('success', __('messages.updated', ['entity' => __('messages.entities.clinic')]));
    }

    /**
     * Penolakan di controller (bukan Policy) supaya berlaku juga untuk Super Admin (F2-R18).
     * Media ikut terhapus lewat trait HasMedia; transaksi wajib agar berkas dihapus setelah commit.
     */
    public function destroy(Clinic $clinic): RedirectResponse
    {
        $this->authorize('delete', $clinic);

        $reason = $clinic->deletionBlockedReason();

        if ($reason !== null) {
            return redirect()->route('admin.clinics.index')->with('error', __('messages.delete_blocked', ['reason' => $reason]));
        }

        DB::transaction(fn (): ?bool => $clinic->delete());

        return redirect()->route('admin.clinics.index')
            ->with('success', __('messages.deleted', ['entity' => __('messages.entities.clinic')]));
    }

    private function saveMedia(ClinicRequest $request, MediaService $media, Clinic $clinic): void
    {
        $user = $request->user();
        $cover = $request->coverFile();

        if ($cover !== null) {
            $media->replace($clinic, 'cover', $cover, ['alt_text' => $request->coverAlt()], $user);
        }

        foreach ($request->galleryFiles() as $index => $file) {
            $media->store($file, 'gallery', $clinic, ['alt_text' => $request->galleryAlt($index)], $user);
        }
    }

    /**
     * @return array<int, string>
     */
    private function specialtyOptions(): array
    {
        return Specialty::query()->ordered()->get()
            ->mapWithKeys(fn (Specialty $specialty): array => [
                $specialty->id => $specialty->name.($specialty->is_active ? '' : ' (nonaktif)'),
            ])
            ->all();
    }
}
```

Tool Write `resources/views/admin/clinics/_form.blade.php`:

```blade
{{-- Variabel: $clinic (Clinic|null), $specialties (id => nama), $action, $submitLabel. Tanpa input slug (F2-R5). --}}
@php($isEdit = $clinic !== null)
<form method="POST" action="{{ $action }}" enctype="multipart/form-data">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <x-form.errors-summary />

    <div class="row g-4">
        <div class="col-12 col-lg-8">
            <div class="card mb-4">
                <div class="card-body">
                    <x-form.input name="name" label="Nama layanan" :value="$clinic?->name" :required="true" maxlength="100" help="Alamat halaman (slug) dibuat otomatis dan tidak berubah saat nama diubah." />
                    <div class="row">
                        <div class="col-12 col-md-4">
                            <x-form.input name="code" label="Kode antrean" :value="$clinic?->code" :required="true" maxlength="5" autocomplete="off" class="text-uppercase" help="2 sampai 5 huruf kapital, mis. ANK. Menjadi awalan nomor antrean." />
                        </div>
                        <div class="col-12 col-md-8">
                            <x-form.select name="type" label="Jenis layanan" :options="\App\Enums\ClinicType::options()" :selected="$clinic?->type" placeholder="Pilih jenis layanan" :required="true" />
                        </div>
                    </div>
                    <x-form.select name="specialty_id" label="Spesialisasi" :options="['' => 'Tanpa spesialisasi'] + $specialties" :selected="$clinic?->specialty_id" help="Kosongkan untuk layanan penunjang." />
                    <x-form.textarea name="short_description" label="Deskripsi singkat" :value="$clinic?->short_description" :rows="2" maxlength="255" :required="true" help="Satu atau dua kalimat; tampil di kartu layanan." />
                    <x-form.rich-text name="description" label="Deskripsi lengkap" :value="$clinic?->description" :maxlength="30000" help="Tampil di halaman detail layanan. Gambar disisipkan lewat tombol gambar di editor dan wajib diberi teks alternatif." />
                    <x-form.input name="location" label="Lokasi" :value="$clinic?->location" :required="true" maxlength="100" help="Mis. Gedung A Lt. 1." />

                    <details class="mb-3" @if ($errors->has('meta_title') || $errors->has('meta_description')) open @endif>
                        <summary class="fw-semibold mb-2">Pengaturan SEO (opsional)</summary>
                        <x-form.input name="meta_title" label="Meta title" :value="$clinic?->meta_title" maxlength="70" help="Maksimal 70 karakter. Kosong = memakai nama layanan." />
                        <x-form.textarea name="meta_description" label="Meta description" :value="$clinic?->meta_description" :rows="2" maxlength="160" help="Maksimal 160 karakter. Kosong = memakai deskripsi singkat." />
                    </details>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card mb-4">
                <div class="card-header fw-semibold">Status</div>
                <div class="card-body">
                    <x-form.checkbox name="is_active" label="Aktif (tampil di halaman Layanan)" :checked="$clinic?->is_active ?? true" :with-hidden="true" />
                    <x-form.checkbox name="is_bookable" label="Dapat didaftar online" :checked="$clinic?->is_bookable ?? true" :with-hidden="true" help="Hanya untuk jenis Poliklinik; layanan penunjang tidak dapat dibuka untuk pendaftaran." />
                    <x-form.input name="sort_order" type="number" label="Urutan tampil" :value="$clinic?->sort_order ?? 0" inputmode="numeric" min="0" max="9999" help="Angka kecil tampil lebih dulu." />
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header fw-semibold">Foto sampul</div>
                <div class="card-body">
                    <x-form.file name="cover" label="Foto sampul" :current-media="$clinic?->firstMedia('cover')" alt-name="cover_alt" :alt-value="old('cover_alt', $clinic?->firstMedia('cover')?->alt_text)" help="Minimal 800 x 450 piksel. Tanpa foto, halaman publik memakai gambar pengganti." />
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header fw-semibold">Tambah foto galeri</div>
                <div class="card-body">
                    <p class="form-text">Maksimal 20 foto per poliklinik. JPG, PNG, atau WebP, maksimal 2 MB, minimal 600 x 400 piksel. Teks alternatif wajib untuk setiap foto. Untuk menambah lebih banyak, simpan lalu ubah lagi.</p>
                    @for ($i = 0; $i < 3; $i++)
                        <div class="mb-3">
                            <label for="gallery-{{ $i }}" class="form-label fw-semibold">Foto galeri {{ $i + 1 }}</label>
                            <input type="file" id="gallery-{{ $i }}" name="gallery[{{ $i }}]" accept="image/jpeg,image/png,image/webp" class="form-control{{ $errors->has('gallery.'.$i) ? ' is-invalid' : '' }}" data-max-kb="2048">
                            @if ($errors->has('gallery.'.$i))
                                <div class="invalid-feedback d-block">{{ $errors->first('gallery.'.$i) }}</div>
                            @endif
                            <label for="gallery-alt-{{ $i }}" class="form-label visually-hidden">Teks alternatif foto galeri {{ $i + 1 }}</label>
                            <input type="text" id="gallery-alt-{{ $i }}" name="gallery_alt[{{ $i }}]" value="{{ old('gallery_alt.'.$i) }}" maxlength="255" placeholder="Teks alternatif foto {{ $i + 1 }}" class="form-control mt-1{{ $errors->has('gallery_alt.'.$i) ? ' is-invalid' : '' }}">
                            @if ($errors->has('gallery_alt.'.$i))
                                <div class="invalid-feedback d-block">{{ $errors->first('gallery_alt.'.$i) }}</div>
                            @endif
                        </div>
                    @endfor
                    @if ($errors->has('gallery'))
                        <div id="gallery" class="invalid-feedback d-block">{{ $errors->first('gallery') }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 justify-content-end">
        <a href="{{ route('admin.clinics.index') }}" class="btn btn-outline-secondary">Batal</a>
        <x-loading-button icon="bi-check-lg" loading-text="Menyimpan…">{{ $submitLabel }}</x-loading-button>
    </div>
</form>
```

Tool Write `resources/views/admin/clinics/create.blade.php`:

```blade
@php
    $breadcrumbItems = [
        ['label' => 'Poliklinik dan Spesialisasi', 'url' => route('admin.clinics.index')],
        ['label' => 'Poliklinik', 'url' => route('admin.clinics.index')],
        ['label' => 'Tambah'],
    ];
@endphp
<x-layouts.admin title="Tambah Poliklinik">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="$breadcrumbItems" />
    </x-slot:breadcrumb>

    @include('admin.clinics._form', [
        'clinic' => null,
        'specialties' => $specialties,
        'action' => route('admin.clinics.store'),
        'submitLabel' => 'Simpan',
    ])
</x-layouts.admin>
```

Tool Write `resources/views/admin/clinics/edit.blade.php`:

```blade
@php
    $gallery = $clinic->mediaIn('gallery')->values();
    $galleryIds = $gallery->pluck('id')->all();
    $breadcrumbItems = [
        ['label' => 'Poliklinik dan Spesialisasi', 'url' => route('admin.clinics.index')],
        ['label' => 'Poliklinik', 'url' => route('admin.clinics.index')],
        ['label' => 'Ubah '.$clinic->name],
    ];
@endphp
<x-layouts.admin title="Ubah Poliklinik">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="$breadcrumbItems" />
    </x-slot:breadcrumb>

    @include('admin.clinics._form', [
        'clinic' => $clinic,
        'specialties' => $specialties,
        'action' => route('admin.clinics.update', $clinic),
        'submitLabel' => 'Simpan Perubahan',
    ])

    {{-- Kelola galeri: form tersendiri (tidak boleh bersarang di form utama) ke admin.media.* --}}
    <div class="card mt-4" id="galeri-poliklinik">
        <div class="card-header fw-semibold">Galeri ({{ $gallery->count() }} dari 20 foto)</div>
        @if ($gallery->isEmpty())
            <div class="card-body text-secondary">Belum ada foto galeri. Tambahkan lewat form di atas.</div>
        @else
            <ul class="list-group list-group-flush">
                @foreach ($gallery as $position => $image)
                    @php
                        $up = $galleryIds;
                        $down = $galleryIds;
                        if ($position > 0) {
                            [$up[$position - 1], $up[$position]] = [$up[$position], $up[$position - 1]];
                        }
                        if ($position < count($galleryIds) - 1) {
                            [$down[$position + 1], $down[$position]] = [$down[$position], $down[$position + 1]];
                        }
                    @endphp
                    <li class="list-group-item">
                        <div class="row g-3 align-items-center">
                            <div class="col-12 col-md-3 col-lg-2">
                                <x-image type="gallery" :media="$image" :alt="$image->alt_text" variant="thumb" ratio="4x3" class="rounded" />
                            </div>
                            <div class="col-12 col-md-9 col-lg-10">
                                <form method="POST" action="{{ route('admin.media.update', $image) }}" class="row g-2 align-items-end mb-2">
                                    @csrf
                                    @method('PATCH')
                                    <div class="col-12 col-md">
                                        <label for="alt-media-{{ $image->id }}" class="form-label fw-semibold">Teks alternatif</label>
                                        <input type="text" id="alt-media-{{ $image->id }}" name="alt_text" value="{{ $image->alt_text }}" maxlength="255" required class="form-control">
                                    </div>
                                    <div class="col-12 col-md-auto">
                                        <x-loading-button variant="outline-primary" loading-text="Menyimpan…">Simpan teks</x-loading-button>
                                    </div>
                                </form>
                                <div class="d-flex flex-wrap gap-2">
                                    <form method="POST" action="{{ route('admin.media.reorder') }}">
                                        @csrf
                                        @foreach ($up as $id)
                                            <input type="hidden" name="ids[]" value="{{ $id }}">
                                        @endforeach
                                        <button type="submit" class="btn btn-sm btn-outline-secondary" @disabled($position === 0) aria-label="Naikkan foto {{ $position + 1 }}">Naikkan</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.media.reorder') }}">
                                        @csrf
                                        @foreach ($down as $id)
                                            <input type="hidden" name="ids[]" value="{{ $id }}">
                                        @endforeach
                                        <button type="submit" class="btn btn-sm btn-outline-secondary" @disabled($position === count($galleryIds) - 1) aria-label="Turunkan foto {{ $position + 1 }}">Turunkan</button>
                                    </form>
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modal-hapus-foto" data-confirm-action="{{ route('admin.media.destroy', $image) }}" data-confirm-message="Hapus foto &quot;{{ $image->alt_text }}&quot; dari galeri? Tindakan ini tidak dapat dibatalkan.">Hapus</button>
                                </div>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <x-confirm-modal
        id="modal-hapus-foto"
        title="Hapus foto dari galeri?"
        message="Foto akan dihapus permanen."
        confirm-label="Ya, hapus"
        variant="danger"
        method="DELETE" />
</x-layouts.admin>
```

- [ ] **Step 10: Jalankan tes — harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="AdminClinic"
& $PHP artisan test --filter=AdminRouteMiddlewareTest
```
Expected: PASS untuk `AdminClinicIndexTest`, `AdminClinicCrudTest`, `AdminClinicValidationTest` (termasuk 31 kasus data provider validasi); `AdminRouteMiddlewareTest` (KNS-02) tetap PASS.

- [ ] **Step 11: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint
& $PHP vendor\bin\pint --test
git add app/Http/Controllers/Admin/ClinicController.php app/Http/Requests/Admin/ClinicRequest.php resources/views/admin/clinics tests/Feature/Admin/AdminClinicCrudTest.php tests/Feature/Admin/AdminClinicValidationTest.php
git commit -m @'
feat(fase-2): tambah, ubah, dan hapus poliklinik dengan cover, galeri, dan editor rich text

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
'@
```
Expected: `pint` tanpa `--test` dijalankan lebih dulu dan merapikan berkas yang disentuh (mis. urutan impor `routes/admin.php` atau `DoctorController` yang baru ditambah); lalu `pint --test` PASS sebelum commit.

- [ ] **Step 12: Seluruh suite dan build**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test
npm run build
```
Expected: seluruh suite PASS; build sukses. Tidak ada commit baru bila tidak ada perubahan.

---

### Task 11: Admin Dokter — daftar, detail, tambah, ubah (#33)

**Files:**
- Create: `app/Http/Requests/Admin/DoctorIndexRequest.php`, `StoreDoctorRequest.php`, `UpdateDoctorRequest.php`
- Create: `app/Http/Controllers/Admin/DoctorController.php` (`index`, `show`, `create`, `store`, `edit`, `update`; Task 12 menambah `status`, `archive`, `photo`)
- Create: `resources/views/admin/doctors/index.blade.php`, `show.blade.php`, `create.blade.php`, `edit.blade.php`, `_form.blade.php`
- Modify: `routes/admin.php` (import `DoctorController` + dua registrasi `Route::resource('dokter', …)` sesuai 11 §3.4)
- Test: `tests/Feature/Admin/AdminDoctorIndexTest.php`, `AdminDoctorShowTest.php`, `AdminDoctorCrudTest.php`, `AdminDoctorValidationTest.php`

**Interfaces:**
- Consumes:
  - Task 1: `Doctor` (`$fillable` tanpa `archived_at`; casts `status` → `DoctorStatus`, `gender` → `Gender`; relasi `clinic()`, `specialties()` dengan pivot `is_primary`; scope `active()`, `search(?string)`, `inSpecialty(int|string $idOrSlug)`, `inClinic(int)`, `ordered()`; accessor `display_name` dan `primary_specialty`), `Clinic`, `Specialty`, `DoctorFactory` (`inactive()`, `archived()`, `withSpecialties(int)`), `InteractsWithMasterData::createDoctor(array $attrs = [], ?Clinic $clinic = null, array $specialties = [])` (daftar `Specialty`; yang pertama menjadi utama).
  - Task 2: `HasUniqueSlug` (slug dari `name` tanpa gelar, sufiks `-2`, `-3`), `HasMedia` (`media()`, `firstMedia('photo')`).
  - Task 3: `messages.created|updated`, `messages.entities.doctor` = "Dokter".
  - Task 4: `DoctorPolicy` (`viewAny`, `view` → `doctors.view`; `create`, `update`, `changeStatus`, `archive` → `doctors.manage`; tanpa `delete`), `PersonName` (kunci pesan `person_name`), `NoHtml` (`no_html`).
  - Task 5: `x-form.checkbox` dengan nama larik `specialty_ids[]`, `x-data-table` `preserve`.
  - Task 8: `DoctorService::create(array $data, Actor $actor, ?UploadedFile $photo = null, ?string $photoAlt = null): Doctor` dan `update(Doctor $doctor, array $data, Actor $actor, ?UploadedFile $photo = null, ?string $photoAlt = null): Doctor`. **Asumsi:** `$data` memuat `specialty_ids` dan `primary_specialty_id` bersama kolom dokter; service memanggil `syncSpecialties()` darinya, tidak menyentuh `slug` dan `status` (lihat Catatan konflik).
  - Task 9/10: `.admin-thumb` (SCSS Task 10), pola `x-slot:filters|actions|head`, `x-confirm-modal`.
  - Fase 1: `ListFilterRequest`, `Actor`, `ImageRules`, `x-image`, `x-status-badge` (`DoctorStatus` punya `label()`, `color()`, `icon()`), `x-form.*`, `ImageFixtures`, `InteractsWithMedia`.
- Produces:
  - `DoctorIndexRequest extends ListFilterRequest` (F2-R25): `status` ∈ `active|inactive|archived|semua` (default `active`, lewat `statusFilter()`), `poli` dan `spesialisasi` (slug yang ada), `urut` ∈ `nama|terbaru|urutan` (awalan `-` = menurun; tautan "Terbaru" memakai `-terbaru`); `applyTo(Builder $query): Builder`.
  - `StoreDoctorRequest` (V-19) dan `UpdateDoctorRequest extends StoreDoctorRequest` (hanya beda `authorize()` dan boleh mempertahankan poliklinik/spesialisasi yang kini nonaktif): `doctorData(): array` (tanpa `photo`/`photo_alt`), `photoUpload(): ?UploadedFile`, `photoAlt(): ?string`.
  - `DoctorController` (11 §3.4; Task 12 menambah method lain). Setelah `store` → redirect `admin.doctors.index` dengan flash "Dokter berhasil ditambahkan." (F2-R21); setelah `update` → redirect `admin.doctors.show` dengan flash "Dokter berhasil diperbarui.".
  - **Route dan urutannya** (di grup G-ADMIN): grup `permission:doctors.manage` lebih dulu (`Route::resource('dokter', DoctorController::class)->names('doctors')->parameters(['dokter' => 'doctor'])->except(['index', 'show', 'destroy'])` → `admin.doctors.create|store|edit|update`), lalu grup `permission:doctors.view` (`->only(['index', 'show'])` → `admin.doctors.index|show`). **Tidak ada `destroy`**; `DELETE /admin/dokter/{id}` menjawab 405. Blok dokter ditaruh sebelum blok poliklinik. Task 12 menambah `admin.doctors.status|archive|photo` di dalam grup `doctors.manage`.
  - View `admin.doctors.index|show|create|edit` dan partial `admin.doctors._form` (`$doctor` nullable, `$clinics` larik `id => nama`, `$specialties` koleksi `Specialty`, `$action`, `$submitLabel`).
  - Field form (F2-R6, F2-R15): `name`, `title_prefix`, `title_suffix`, `gender`, `clinic_id`, `specialty_ids[]`, `primary_specialty_id`, `short_bio`, `bio`, `sort_order`, `meta_title`, `meta_description`, `photo`, `photo_alt`. **Tanpa** `status` dan `slug`.
  - Penanda tempat sisip Task 12 (komentar Blade): `{{-- Aksi status dokter --}}`, `{{-- Modal konfirmasi status dokter --}}` (index dan show) dan `{{-- Ganti foto dokter --}}` (show).
  - Breadcrumb: "Dasbor › Dokter"; "Dasbor › Dokter › Tambah Dokter"; "Dasbor › Dokter › {nama tampilan}"; "Dasbor › Dokter › {nama tampilan} › Ubah".

**Pesan galat (sumber 12 V-19, dengan F2-R23):**

| Kunci | Pesan |
|---|---|
| `name` | "Nama dokter wajib diisi." / "Nama dokter harus berupa teks." / "Nama dokter minimal 3 karakter." / "Nama dokter maksimal 100 karakter." / "Nama dokter hanya boleh berisi huruf, spasi, titik, apostrof, dan tanda hubung." |
| `title_prefix` | "Awalan gelar harus berupa teks." / "Awalan gelar maksimal 20 karakter." / "Awalan gelar hanya boleh berisi huruf, titik, dan spasi." |
| `title_suffix` | "Akhiran gelar harus berupa teks." / "Akhiran gelar maksimal 50 karakter." / "Akhiran gelar hanya boleh berisi huruf, angka, titik, koma, spasi, dan tanda hubung." |
| `gender` | "Jenis kelamin wajib dipilih." / "Jenis kelamin tidak valid." |
| `clinic_id` | "Poliklinik utama wajib dipilih." / "Poliklinik utama harus berupa angka bulat." / "Poliklinik yang dipilih tidak tersedia." |
| `specialty_ids` | "Pilih minimal satu spesialisasi." / "Spesialisasi maksimal 5." / "Spesialisasi tidak valid." |
| `specialty_ids.*` | "Spesialisasi tidak valid." / "Spesialisasi tidak boleh dipilih dua kali." / "Spesialisasi yang dipilih tidak tersedia." |
| `primary_specialty_id` | "Spesialisasi utama wajib dipilih." / "Spesialisasi utama harus berupa angka bulat." / "Spesialisasi utama harus salah satu spesialisasi yang dipilih." |
| `short_bio` | "Profil singkat harus berupa teks." / "Profil singkat maksimal 500 karakter." |
| `bio` | "Profil lengkap harus berupa teks." / "Profil lengkap maksimal 5000 karakter." / "Profil lengkap tidak boleh mengandung tag HTML." |
| `sort_order` | "Urutan harus berupa angka bulat." / "Urutan harus antara 0 dan 9999." |
| `meta_title` / `meta_description` | "Meta title maksimal 70 karakter." / "Meta description maksimal 160 karakter." (+ "… harus berupa teks.") |
| `photo` | `ImageRules::messages('photo', 'photo')`: "File harus berupa gambar JPG, PNG, atau WebP." / "Ukuran gambar maksimal 2 MB." / "Dimensi gambar minimal 300 x 300 piksel dan maksimal 6000 x 6000 piksel." |
| `photo_alt` | "Teks alternatif foto wajib diisi." / "Teks alternatif foto harus berupa teks." / "Teks alternatif foto maksimal 255 karakter." |

**Test IDs:** CRD-01 (bagian admin), CRD-04 (nama tampilan di daftar dan detail), MED-01 (foto saat tambah), VAL-01 (form dokter), SEK-09 (SVG ditolak di foto dokter), AKS (#33 daftar/detail: Admin Konten, Petugas, Super Admin 200; #33b tambah/ubah: Petugas 403, Pasien 403, Tamu → login), KNS-01 (tanpa `destroy`), VAL-07, Review Focus 1 (slug ganda lewat form) dan 2 (daftar banyak baris di mode ketat).

**Catatan teknis (diverifikasi dari source):**
1. Urutan route penting: `/admin/dokter/buat` harus didaftarkan sebelum `/admin/dokter/{doctor}`; karena `show` ada di grup `doctors.view` yang didaftarkan **kedua**, dan parameter admin dibatasi `[0-9]+` oleh grup di `bootstrap/app.php`, `buat` tidak pernah tertangkap `{doctor}`. Tes `test_create_route_is_registered_before_the_show_route` membuktikan keduanya.
2. Mode ketat: view daftar memakai `clinic`, `specialties` (dengan pivot `is_primary`), dan `media`; ketiganya di-*eager load* dengan `with()`. `primary_specialty` dan `firstMedia()` hanya membaca relasi yang sudah dimuat. Tes daftar membuat banyak dokter dengan spesialisasi, poliklinik, dan foto berbeda; tes tidak membaca relasi lewat properti (memakai `->media()->first()` atau `Media::query()`).
3. Pesan kustom untuk objek aturan memakai kunci pendek `{field}.{snake_case kelas}` (`name.person_name`, `bio.no_html`) dan untuk `Rule::enum` kunci `gender.enum`, sesuai `FormatsMessages::getFromLocalArray()`.
4. `Rule::in()` untuk `primary_specialty_id` dibangun dari `specialty_ids` yang dikirim (dipaksa menjadi string, hanya nilai skalar) sehingga masukan bukan larik tidak menimbulkan galat 500.
5. Pada ubah, poliklinik dan spesialisasi dokter yang **kini nonaktif** tetap boleh dipertahankan (dan tetap tampil di form), tetapi tidak boleh dipilih baru. Aturan `exists` memakai grup `(is_active = 1 OR id IN (milik dokter ini))`.
6. Form tidak memiliki `status` (F2-R6): status baru selalu `active` dan diubah lewat `admin.doctors.status` (Task 12). Tes memastikan `status`, `slug`, dan `archived_at` yang dikirim paksa diabaikan (`validated()` + `DoctorService` tidak menyentuhnya).
7. Bio ditampilkan sebagai paragraf `{{ }}` (dipisah baris kosong). Tidak ada `{!! !!}` di fase ini selain `clinics.description` publik (Task 13).
8. Tanggal memakai `translatedFormat()` (locale Carbon `id` dari Task 3).

- [ ] **Step 1: Verifikasi prasyarat (Task 1–10)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git branch --show-current
git status --short
foreach ($p in 'app\Models\Doctor.php', 'app\Policies\DoctorPolicy.php', 'app\Services\Doctors\DoctorService.php', 'app\Rules\PersonName.php', 'app\Rules\NoHtml.php', 'database\factories\DoctorFactory.php', 'resources\views\admin\clinics\index.blade.php') { "{0} => {1}" -f $p, (Test-Path $p) }
& $PHP artisan test
```
Expected: `fase-2-master-data`; bersih; semua `True`; seluruh suite PASS.

**Siklus A — daftar (`index`), `DoctorIndexRequest`, route**

- [ ] **Step 2: Tulis tes yang gagal (tool Write) `tests/Feature/Admin/AdminDoctorIndexTest.php`**

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\Doctor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithMedia;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

class AdminDoctorIndexTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithMedia;
    use InteractsWithStaff;
    use RefreshDatabase;

    /**
     * @return array<string, array{string}>
     */
    public static function viewers(): array
    {
        return [
            'super admin' => ['super-admin'],
            'admin konten' => ['content-admin'],
            'petugas pendaftaran' => ['registration-officer'],
        ];
    }

    #[DataProvider('viewers')]
    public function test_every_staff_role_with_doctors_view_can_open_the_list(string $role): void
    {
        $this->createDoctor(['name' => 'Laras Wulandari']);

        $this->actingAs($this->createStaff($role))
            ->get(route('admin.doctors.index'))
            ->assertOk()
            ->assertSee('Laras Wulandari');
    }

    public function test_patient_gets_403_and_guest_is_redirected_to_admin_login(): void
    {
        $this->actingAs(User::factory()->patient()->create())
            ->get(route('admin.doctors.index'))
            ->assertForbidden();

        $this->app['auth']->forgetGuards();

        $this->get(route('admin.doctors.index'))->assertRedirect(route('admin.login'));
    }

    public function test_urls_use_the_indonesian_segments_and_doctors_have_no_destroy_route(): void
    {
        $doctor = $this->createDoctor();

        $this->assertSame(url('/admin/dokter'), route('admin.doctors.index'));
        $this->assertSame(url('/admin/dokter/buat'), route('admin.doctors.create'));
        $this->assertSame(url('/admin/dokter/'.$doctor->id), route('admin.doctors.show', $doctor));
        $this->assertSame(url('/admin/dokter/'.$doctor->id.'/ubah'), route('admin.doctors.edit', $doctor));
        $this->assertFalse(Route::has('admin.doctors.destroy'));
    }

    public function test_create_route_is_registered_before_the_show_route(): void
    {
        $names = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route) => (string) $route->getName())
            ->filter(fn (string $name): bool => str_starts_with($name, 'admin.doctors.'))
            ->values()
            ->all();

        $this->assertLessThan(array_search('admin.doctors.show', $names, true), array_search('admin.doctors.create', $names, true));
    }

    public function test_delete_to_a_doctor_url_is_not_allowed(): void
    {
        $doctor = $this->createDoctor();

        $this->actingAs($this->createStaff('super-admin'))
            ->delete('/admin/dokter/'.$doctor->id)
            ->assertStatus(405);

        $this->assertDatabaseHas('doctors', ['id' => $doctor->id]);
    }

    public function test_list_renders_many_doctors_with_specialties_clinic_and_photo_in_strict_mode(): void
    {
        $urologi = $this->createSpecialty(['name' => 'Urologi']);
        $penyakitDalam = $this->createSpecialty(['name' => 'Penyakit Dalam']);
        $clinicA = $this->createClinic(['name' => 'Poliklinik Urologi']);
        $clinicB = $this->createClinic(['name' => 'Poliklinik Jantung']);

        $raka = $this->createDoctor(['name' => 'Raka Pradipta', 'title_prefix' => 'dr.', 'title_suffix' => 'Sp.U'], $clinicA, [$urologi]);
        $maya = $this->createDoctor(['name' => 'Maya Kartika', 'title_prefix' => 'dr.', 'title_suffix' => 'Sp.JP'], $clinicB, [$penyakitDalam, $urologi]);
        $this->createDoctor(['name' => 'Rani Oktaviani', 'title_prefix' => 'dr.', 'title_suffix' => null], $clinicB, [$penyakitDalam]);
        $this->createDoctor(['name' => 'Teguh Wicaksono'], $clinicA, [$urologi]);

        foreach ([$raka, $maya] as $doctor) {
            $this->createMediaRow(['model_type' => Doctor::class, 'model_id' => $doctor->id, 'collection' => 'photo', 'alt_text' => 'Foto '.$doctor->name]);
        }

        foreach (['super-admin', 'content-admin', 'registration-officer'] as $role) {
            $this->actingAs($this->createStaff($role))
                ->get(route('admin.doctors.index'))
                ->assertOk()
                ->assertSee('dr. Raka Pradipta, Sp.U')
                ->assertSee('dr. Maya Kartika, Sp.JP')
                ->assertSee('dr. Rani Oktaviani')
                ->assertDontSee('dr. Rani Oktaviani,')
                ->assertSee('Teguh Wicaksono')
                ->assertSee('Poliklinik Urologi')
                ->assertSee('Poliklinik Jantung')
                ->assertSee('Penyakit Dalam')
                ->assertSee('(utama)')
                ->assertSee('Aktif');
        }
    }

    public function test_default_status_filter_is_active_and_the_other_statuses_are_filterable(): void
    {
        $this->createDoctor(['name' => 'Dokter Aktif Satu']);
        Doctor::factory()->inactive()->withSpecialties()->create(['name' => 'Dokter Nonaktif Satu']);
        Doctor::factory()->archived()->withSpecialties()->create(['name' => 'Dokter Arsip Satu']);
        $this->actingAs($this->createStaff('content-admin'));

        $this->get(route('admin.doctors.index'))
            ->assertOk()
            ->assertSee('Dokter Aktif Satu')
            ->assertDontSee('Dokter Nonaktif Satu')
            ->assertDontSee('Dokter Arsip Satu')
            ->assertSee('value="active" selected', false);

        $this->get(route('admin.doctors.index', ['status' => 'inactive']))
            ->assertSee('Dokter Nonaktif Satu')->assertDontSee('Dokter Aktif Satu')->assertDontSee('Dokter Arsip Satu');

        $this->get(route('admin.doctors.index', ['status' => 'archived']))
            ->assertSee('Dokter Arsip Satu')->assertDontSee('Dokter Aktif Satu')->assertDontSee('Dokter Nonaktif Satu');

        $this->get(route('admin.doctors.index', ['status' => 'semua']))
            ->assertSee('Dokter Aktif Satu')->assertSee('Dokter Nonaktif Satu')->assertSee('Dokter Arsip Satu');
    }

    public function test_filters_by_clinic_and_specialty_slug_search_and_sort(): void
    {
        $anak = $this->createSpecialty(['name' => 'Anak']);
        $mata = $this->createSpecialty(['name' => 'Mata']);
        $clinicAnak = $this->createClinic(['name' => 'Poliklinik Anak']);
        $clinicMata = $this->createClinic(['name' => 'Poliklinik Mata']);
        $this->createDoctor(['name' => 'Budi Anak', 'sort_order' => 2], $clinicAnak, [$anak]);
        $this->createDoctor(['name' => 'Citra Mata', 'sort_order' => 1], $clinicMata, [$mata]);
        $this->createDoctor(['name' => 'Agus Gabungan', 'sort_order' => 3], $clinicMata, [$anak, $mata]);
        $this->actingAs($this->createStaff('content-admin'));

        $this->get(route('admin.doctors.index', ['poli' => $clinicAnak->slug]))
            ->assertSee('Budi Anak')->assertDontSee('Citra Mata')->assertDontSee('Agus Gabungan');

        $this->get(route('admin.doctors.index', ['spesialisasi' => $anak->slug]))
            ->assertSee('Budi Anak')->assertSee('Agus Gabungan')->assertDontSee('Citra Mata');

        $this->get(route('admin.doctors.index', ['q' => 'citra']))
            ->assertSee('Citra Mata')->assertDontSee('Budi Anak');

        $this->get(route('admin.doctors.index'))
            ->assertSeeInOrder(['Citra Mata', 'Budi Anak', 'Agus Gabungan']);

        $this->get(route('admin.doctors.index', ['urut' => 'nama']))
            ->assertSeeInOrder(['Agus Gabungan', 'Budi Anak', 'Citra Mata']);

        $this->get(route('admin.doctors.index', ['urut' => '-nama']))
            ->assertSeeInOrder(['Citra Mata', 'Budi Anak', 'Agus Gabungan']);

        $this->get(route('admin.doctors.index', ['urut' => '-terbaru']))
            ->assertOk()
            ->assertSeeInOrder(['Agus Gabungan', 'Citra Mata', 'Budi Anak']);
    }

    public function test_invalid_filters_fall_back_to_defaults_without_an_error(): void
    {
        $this->createDoctor(['name' => 'Dokter Aktif Satu']);

        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.doctors.index', ['status' => 'zzz', 'poli' => 'tidak-ada', 'spesialisasi' => 'tidak-ada', 'urut' => 'x', 'kembali' => 'https://situs-lain.test']))
            ->assertOk()
            ->assertSee('Filter tidak valid dan diabaikan.')
            ->assertSee('Dokter Aktif Satu')
            ->assertDontSee('situs-lain.test');
    }

    public function test_sort_parameter_is_preserved_by_the_search_form(): void
    {
        $this->createDoctor();

        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.doctors.index', ['urut' => 'nama']))
            ->assertOk()
            ->assertSee('name="urut"', false)
            ->assertSee('value="nama"', false);
    }

    public function test_manage_buttons_are_shown_only_to_roles_that_can_manage(): void
    {
        $doctor = $this->createDoctor(['name' => 'Laras Wulandari']);

        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.doctors.index'))
            ->assertSee('Tambah Dokter')
            ->assertSee('href="'.route('admin.doctors.create').'"', false)
            ->assertSee('href="'.route('admin.doctors.edit', $doctor).'"', false)
            ->assertSee('href="'.route('admin.doctors.show', $doctor).'"', false);

        $this->actingAs($this->createStaff('registration-officer'))
            ->get(route('admin.doctors.index'))
            ->assertOk()
            ->assertDontSee('Tambah Dokter')
            ->assertDontSee(route('admin.doctors.create'))
            ->assertDontSee(route('admin.doctors.edit', $doctor))
            ->assertSee('href="'.route('admin.doctors.show', $doctor).'"', false);
    }

    public function test_empty_list_shows_the_empty_state(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.doctors.index'))
            ->assertOk()
            ->assertSee('Belum ada dokter');
    }
}
```

- [ ] **Step 3: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=AdminDoctorIndexTest
```
Expected: FAIL — `Symfony\Component\Routing\Exception\RouteNotFoundException: Route [admin.doctors.index] not defined.`

- [ ] **Step 4: Tulis implementasi daftar**

Tool Write `app/Http/Requests/Admin/DoctorIndexRequest.php`:

```php
<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ListFilterRequest;
use App\Models\Clinic;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

/**
 * Filter daftar dokter admin (F2-R25, 07 W-15): `q`, `status` (default `active`), `poli`, `spesialisasi`, `urut`.
 * Nilai tidak valid diabaikan oleh basis (12 V-45).
 */
class DoctorIndexRequest extends ListFilterRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'poli' => ['nullable', 'string', 'max:150', Rule::exists('clinics', 'slug')],
            'spesialisasi' => ['nullable', 'string', 'max:150', Rule::exists('specialties', 'slug')],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'poli.string' => 'Pilihan filter tidak valid.',
            'poli.max' => 'Pilihan filter tidak valid.',
            'poli.exists' => 'Pilihan filter tidak valid.',
            'spesialisasi.string' => 'Pilihan filter tidak valid.',
            'spesialisasi.max' => 'Pilihan filter tidak valid.',
            'spesialisasi.exists' => 'Pilihan filter tidak valid.',
        ]);
    }

    /**
     * @return list<string>
     */
    protected function statusValues(): array
    {
        return ['active', 'inactive', 'archived', 'semua'];
    }

    /**
     * @return list<string>
     */
    protected function sortable(): array
    {
        return ['nama', 'terbaru', 'urutan'];
    }

    /** Status yang berlaku: `active` bila tidak ada atau tidak valid. */
    public function statusFilter(): string
    {
        return $this->filters()['status'] ?? 'active';
    }

    /**
     * @param  Builder<\App\Models\Doctor>  $query
     * @return Builder<\App\Models\Doctor>
     */
    public function applyTo(Builder $query): Builder
    {
        $filters = $this->filters();
        $model = $query->getModel();

        $status = $this->statusFilter();

        if ($status !== 'semua') {
            $query->where($model->qualifyColumn('status'), $status);
        }

        if (isset($filters['q'])) {
            $query->search($filters['q']);
        }

        if (isset($filters['poli'])) {
            $query->inClinic((int) Clinic::query()->where('slug', $filters['poli'])->value('id'));
        }

        if (isset($filters['spesialisasi'])) {
            $query->inSpecialty($filters['spesialisasi']);
        }

        $sort = $filters['urut'] ?? null;

        if ($sort === null) {
            return $query->ordered()->orderBy($model->qualifyColumn('id'));
        }

        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';

        return match (ltrim($sort, '-')) {
            'nama' => $query->orderBy($model->qualifyColumn('name'), $direction)->orderBy($model->qualifyColumn('id')),
            'terbaru' => $query->orderBy($model->qualifyColumn('created_at'), $direction)->orderBy($model->qualifyColumn('id'), $direction),
            default => $query->orderBy($model->qualifyColumn('sort_order'), $direction)->orderBy($model->qualifyColumn('name'))->orderBy($model->qualifyColumn('id')),
        };
    }
}
```

Tool Write `app/Http/Controllers/Admin/DoctorController.php` (versi Siklus A; dilengkapi di Siklus B dan Task 12):

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DoctorIndexRequest;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Specialty;
use Illuminate\View\View;

class DoctorController extends Controller
{
    public function index(DoctorIndexRequest $request): View
    {
        $this->authorize('viewAny', Doctor::class);

        $doctors = $request
            ->applyTo(Doctor::query()->with(['clinic', 'specialties', 'media']))
            ->paginate($request->perPage());

        return view('admin.doctors.index', [
            'doctors' => $doctors,
            'statusFilter' => $request->statusFilter(),
            'clinicOptions' => Clinic::query()->ordered()->pluck('name', 'slug')->all(),
            'specialtyOptions' => Specialty::query()->ordered()->pluck('name', 'slug')->all(),
        ]);
    }
}
```

Tool Edit `routes/admin.php`:

1. Import. `old`: `use App\Http\Controllers\Admin\DashboardController;` → `new`:
```php
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DoctorController;
```
2. Route, sebelum blok poliklinik. `old`:
```php
    // Poliklinik (halaman #34, tab Poliklinik): izin tunggal clinics.manage, tanpa show (11 §3.4).
```
`new`:
```php
    // Dokter (halaman #33), 11 §3.4: izin lihat ≠ kelola, jadi kelola didaftarkan DAHULU agar /dokter/buat
    // tidak tertangkap {doctor}, lalu lihat. Dokter tidak punya destroy (diarsipkan, bukan dihapus; BR-03).
    Route::middleware('permission:doctors.manage')->group(function (): void {
        Route::resource('dokter', DoctorController::class)
            ->names('doctors')
            ->parameters(['dokter' => 'doctor'])
            ->except(['index', 'show', 'destroy']);
    });
    Route::middleware('permission:doctors.view')->group(function (): void {
        Route::resource('dokter', DoctorController::class)
            ->names('doctors')
            ->parameters(['dokter' => 'doctor'])
            ->only(['index', 'show']);
    });

    // Poliklinik (halaman #34, tab Poliklinik): izin tunggal clinics.manage, tanpa show (11 §3.4).
```

Tool Write `resources/views/admin/doctors/index.blade.php`:

```blade
@php
    $currentSort = (string) request()->query('urut', '');
    $sortUrl = fn (string $key): string => request()->fullUrlWithQuery(['urut' => $key, 'kembali' => null, 'page' => null]);
@endphp
<x-layouts.admin title="Dokter">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="[['label' => 'Dokter']]" />
    </x-slot:breadcrumb>

    <x-slot:actions>
        @can('create', \App\Models\Doctor::class)
            <a href="{{ route('admin.doctors.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg" aria-hidden="true"></i> Tambah Dokter
            </a>
        @endcan
    </x-slot:actions>

    <x-data-table
        :rows="$doctors"
        :preserve="['urut']"
        search-placeholder="Cari nama dokter atau spesialisasi…"
        caption="Daftar dokter"
        empty-title="Belum ada dokter"
        empty-message="Tidak ada dokter dengan status dan filter yang dipilih.">
        <x-slot:filters>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="filter-status" class="form-label fw-semibold">Status</label>
                <select id="filter-status" name="status" class="form-select">
                    @foreach (['active' => 'Aktif', 'inactive' => 'Nonaktif', 'archived' => 'Diarsipkan', 'semua' => 'Semua'] as $value => $label)
                        <option value="{{ $value }}" @selected($statusFilter === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-3">
                <label for="filter-poli" class="form-label fw-semibold">Poliklinik</label>
                <select id="filter-poli" name="poli" class="form-select">
                    <option value="">Semua</option>
                    @foreach ($clinicOptions as $slug => $name)
                        <option value="{{ $slug }}" @selected(request()->query('poli') === $slug)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-3 col-lg-3">
                <label for="filter-spesialisasi" class="form-label fw-semibold">Spesialisasi</label>
                <select id="filter-spesialisasi" name="spesialisasi" class="form-select">
                    <option value="">Semua</option>
                    @foreach ($specialtyOptions as $slug => $name)
                        <option value="{{ $slug }}" @selected(request()->query('spesialisasi') === $slug)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        </x-slot:filters>

        <x-slot:actions>
            <nav aria-label="Urutkan daftar" class="d-flex flex-wrap align-items-center gap-2">
                <span class="text-secondary">Urutkan:</span>
                @foreach (['nama' => 'Nama', '-terbaru' => 'Terbaru', 'urutan' => 'Urutan tampil'] as $key => $label)
                    <a href="{{ $sortUrl($key) }}" class="btn btn-sm {{ $currentSort === $key ? 'btn-primary' : 'btn-outline-secondary' }}"@if ($currentSort === $key) aria-current="true"@endif>{{ $label }}</a>
                @endforeach
            </nav>
        </x-slot:actions>

        <x-slot:head>
            <th scope="col">Dokter</th>
            <th scope="col">Spesialisasi</th>
            <th scope="col">Poliklinik</th>
            <th scope="col">Status</th>
            <th scope="col">Aksi</th>
        </x-slot:head>

        @foreach ($doctors as $doctor)
            <tr>
                <td data-label="Dokter">
                    <div class="d-flex align-items-center gap-2">
                        <div class="admin-thumb">
                            <x-image type="doctor" :media="$doctor->firstMedia('photo')" variant="thumb" ratio="1x1" :decorative="true" class="rounded-circle" />
                        </div>
                        <a href="{{ route('admin.doctors.show', $doctor) }}" class="fw-semibold text-decoration-none">{{ $doctor->display_name }}</a>
                    </div>
                </td>
                <td data-label="Spesialisasi">
                    @foreach ($doctor->specialties->sortByDesc(fn ($specialty) => (int) $specialty->pivot->is_primary) as $specialty)
                        <span class="d-block">{{ $specialty->name }}@if ($specialty->pivot->is_primary) <span class="badge text-bg-primary">(utama)</span>@endif</span>
                    @endforeach
                </td>
                <td data-label="Poliklinik">{{ $doctor->clinic->name }}</td>
                <td data-label="Status"><x-status-badge :status="$doctor->status" /></td>
                <td data-label="Aksi" class="table-actions">
                    <a href="{{ route('admin.doctors.show', $doctor) }}" class="btn btn-sm btn-outline-secondary" aria-label="Lihat {{ $doctor->display_name }}">Lihat</a>
                    @can('update', $doctor)
                        <a href="{{ route('admin.doctors.edit', $doctor) }}" class="btn btn-sm btn-outline-primary" aria-label="Ubah {{ $doctor->display_name }}">Ubah</a>
                    @endcan
                    {{-- Aksi status dokter --}}
                </td>
            </tr>
        @endforeach
    </x-data-table>

    {{-- Modal konfirmasi status dokter --}}
</x-layouts.admin>
```

- [ ] **Step 5: Jalankan tes — harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=AdminDoctorIndexTest
& $PHP artisan route:list --path=admin/dokter
```
Expected: PASS (14 tes); `route:list` menampilkan `index` dan `show` (GET), `create`, `store`, `edit`, `update` (PUT|PATCH), tanpa `destroy`.

- [ ] **Step 6: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint
& $PHP vendor\bin\pint --test
git add app/Http/Controllers/Admin/DoctorController.php app/Http/Requests/Admin/DoctorIndexRequest.php routes/admin.php resources/views/admin/doctors/index.blade.php tests/Feature/Admin/AdminDoctorIndexTest.php
git commit -m @'
feat(fase-2): daftar admin dokter dengan filter status, poliklinik, spesialisasi, dan urutan

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
'@
```
Expected: `pint` tanpa `--test` dijalankan lebih dulu dan merapikan berkas yang disentuh (mis. urutan impor `routes/admin.php` atau `DoctorController` yang baru ditambah); lalu `pint --test` PASS sebelum commit.

**Siklus B — detail (`show`)**

- [ ] **Step 7: Tulis tes yang gagal (tool Write) `tests/Feature/Admin/AdminDoctorShowTest.php`**

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\Doctor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

class AdminDoctorShowTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithStaff;
    use RefreshDatabase;

    /**
     * @return array<string, array{string}>
     */
    public static function viewers(): array
    {
        return [
            'super admin' => ['super-admin'],
            'admin konten' => ['content-admin'],
            'petugas pendaftaran' => ['registration-officer'],
        ];
    }

    #[DataProvider('viewers')]
    public function test_profile_shows_display_name_clinic_specialties_and_status(string $role): void
    {
        $anak = $this->createSpecialty(['name' => 'Anak']);
        $umum = $this->createSpecialty(['name' => 'Dokter Umum']);
        $clinic = $this->createClinic(['name' => 'Poliklinik Anak']);
        $doctor = $this->createDoctor([
            'name' => 'Laras Wulandari',
            'title_prefix' => 'dr.',
            'title_suffix' => 'Sp.A',
            'gender' => 'P',
            'short_bio' => 'Melayani konsultasi anak.',
            'bio' => "Paragraf pertama.\n\nParagraf kedua.",
        ], $clinic, [$anak, $umum]);

        $this->actingAs($this->createStaff($role))
            ->get(route('admin.doctors.show', $doctor))
            ->assertOk()
            ->assertSee('dr. Laras Wulandari, Sp.A')
            ->assertSee('Poliklinik Anak')
            ->assertSee('Anak')
            ->assertSee('Dokter Umum')
            ->assertSee('Perempuan')
            ->assertSee('Melayani konsultasi anak.')
            ->assertSee('Paragraf pertama.')
            ->assertSee('Paragraf kedua.')
            ->assertSee('Aktif')
            ->assertSee('Dasbor');
    }

    public function test_display_name_has_no_dangling_comma_without_a_suffix_crd04(): void
    {
        $doctor = $this->createDoctor(['name' => 'Rani Oktaviani', 'title_prefix' => 'dr.', 'title_suffix' => null]);

        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.doctors.show', $doctor))
            ->assertOk()
            ->assertSee('dr. Rani Oktaviani')
            ->assertDontSee('dr. Rani Oktaviani,');
    }

    public function test_content_admin_sees_manage_buttons_but_registration_officer_does_not(): void
    {
        $doctor = $this->createDoctor(['name' => 'Laras Wulandari']);

        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.doctors.show', $doctor))
            ->assertSee('href="'.route('admin.doctors.edit', $doctor).'"', false);

        $this->actingAs($this->createStaff('registration-officer'))
            ->get(route('admin.doctors.show', $doctor))
            ->assertOk()
            ->assertDontSee(route('admin.doctors.edit', $doctor));
    }

    public function test_inactive_and_archived_doctors_can_still_be_viewed_by_staff(): void
    {
        $inactive = Doctor::factory()->inactive()->withSpecialties()->create(['name' => 'Dokter Nonaktif Satu']);
        $archived = Doctor::factory()->archived()->withSpecialties()->create(['name' => 'Dokter Arsip Satu']);
        $this->actingAs($this->createStaff('registration-officer'));

        $this->get(route('admin.doctors.show', $inactive))->assertOk()->assertSee('Nonaktif');
        $this->get(route('admin.doctors.show', $archived))->assertOk()->assertSee('Diarsipkan');
    }

    public function test_patient_gets_403_unknown_or_non_numeric_ids_are_404_and_guest_is_redirected(): void
    {
        $doctor = $this->createDoctor();

        $this->actingAs(User::factory()->patient()->create())
            ->get(route('admin.doctors.show', $doctor))
            ->assertForbidden();

        $this->actingAs($this->createStaff('content-admin'));
        $this->get('/admin/dokter/999999')->assertNotFound();
        $this->get('/admin/dokter/abc')->assertNotFound();

        $this->app['auth']->forgetGuards();
        $this->get(route('admin.doctors.show', $doctor))->assertRedirect(route('admin.login'));
    }
}
```

- [ ] **Step 8: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=AdminDoctorShowTest
```
Expected: FAIL — `BadMethodCallException: Method App\Http\Controllers\Admin\DoctorController::show does not exist.`

- [ ] **Step 9: Tulis `show`**

Tool Edit `app/Http/Controllers/Admin/DoctorController.php` — `old`:
```php
            'specialtyOptions' => Specialty::query()->ordered()->pluck('name', 'slug')->all(),
        ]);
    }
}
```
`new`:
```php
            'specialtyOptions' => Specialty::query()->ordered()->pluck('name', 'slug')->all(),
        ]);
    }

    public function show(Doctor $doctor): View
    {
        $this->authorize('view', $doctor);

        return view('admin.doctors.show', [
            'doctor' => $doctor->load(['clinic', 'specialties', 'media']),
        ]);
    }
}
```

Tool Write `resources/views/admin/doctors/show.blade.php`:

```blade
@php
    $photo = $doctor->firstMedia('photo');
    $specialties = $doctor->specialties->sortByDesc(fn ($specialty) => (int) $specialty->pivot->is_primary);
    $paragraphs = $doctor->bio === null ? [] : preg_split('/\R{2,}/', trim($doctor->bio));
@endphp
<x-layouts.admin :title="$doctor->display_name">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="[
            ['label' => 'Dokter', 'url' => route('admin.doctors.index')],
            ['label' => $doctor->display_name],
        ]" />
    </x-slot:breadcrumb>

    <x-slot:actions>
        <a href="{{ route('admin.doctors.index') }}" class="btn btn-outline-secondary">Kembali ke daftar</a>
        @can('update', $doctor)
            <a href="{{ route('admin.doctors.edit', $doctor) }}" class="btn btn-primary">
                <i class="bi bi-pencil" aria-hidden="true"></i> Ubah Data
            </a>
        @endcan
        {{-- Aksi status dokter --}}
    </x-slot:actions>

    <div class="row g-4">
        <div class="col-12 col-md-4 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <x-image type="doctor" :media="$photo" :alt="$photo?->alt_text ?? 'Foto '.$doctor->display_name" ratio="1x1" class="rounded" />
                    <p class="form-text mb-0 mt-2">{{ $photo ? 'Foto profil dokter.' : 'Belum ada foto; situs publik memakai gambar pengganti.' }}</p>
                </div>
            </div>
            {{-- Ganti foto dokter --}}
        </div>

        <div class="col-12 col-md-8 col-lg-9">
            <div class="card mb-4">
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Nama tampilan</dt>
                        <dd class="col-sm-8">{{ $doctor->display_name }}</dd>
                        <dt class="col-sm-4">Status</dt>
                        <dd class="col-sm-8"><x-status-badge :status="$doctor->status" />@if ($doctor->archived_at) <span class="text-secondary small">sejak {{ $doctor->archived_at->translatedFormat('j F Y') }}</span>@endif</dd>
                        <dt class="col-sm-4">Jenis kelamin</dt>
                        <dd class="col-sm-8">{{ $doctor->gender->label() }}</dd>
                        <dt class="col-sm-4">Poliklinik utama</dt>
                        <dd class="col-sm-8">{{ $doctor->clinic->name }}</dd>
                        <dt class="col-sm-4">Spesialisasi</dt>
                        <dd class="col-sm-8">
                            @foreach ($specialties as $specialty)
                                <span class="d-block">{{ $specialty->name }}@if ($specialty->pivot->is_primary) <span class="badge text-bg-primary">(utama)</span>@endif</span>
                            @endforeach
                        </dd>
                        <dt class="col-sm-4">Urutan tampil</dt>
                        <dd class="col-sm-8">{{ $doctor->sort_order }}</dd>
                        <dt class="col-sm-4">Dibuat</dt>
                        <dd class="col-sm-8">{{ $doctor->created_at->translatedFormat('j F Y H:i') }}</dd>
                        <dt class="col-sm-4">Diperbarui</dt>
                        <dd class="col-sm-8">{{ $doctor->updated_at->translatedFormat('j F Y H:i') }}</dd>
                    </dl>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header fw-semibold">Profil</div>
                <div class="card-body">
                    <h2 class="h6">Profil singkat</h2>
                    <p>{{ $doctor->short_bio ?? '—' }}</p>
                    <h2 class="h6">Profil lengkap</h2>
                    @forelse ($paragraphs as $paragraph)
                        <p>{{ $paragraph }}</p>
                    @empty
                        <p class="mb-0">—</p>
                    @endforelse
                </div>
            </div>

            <div class="card">
                <div class="card-header fw-semibold">SEO</div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Meta title</dt>
                        <dd class="col-sm-8">{{ $doctor->meta_title ?? 'Otomatis: nama tampilan' }}</dd>
                        <dt class="col-sm-4">Meta description</dt>
                        <dd class="col-sm-8">{{ $doctor->meta_description ?? 'Otomatis: profil singkat' }}</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal konfirmasi status dokter --}}
</x-layouts.admin>
```

- [ ] **Step 10: Jalankan tes — harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="AdminDoctorShowTest|AdminDoctorIndexTest"
```
Expected: PASS (`AdminDoctorShowTest` 7 tes: 3 data provider + 4 lain; `AdminDoctorIndexTest` tetap hijau).

- [ ] **Step 11: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint
& $PHP vendor\bin\pint --test
git add app/Http/Controllers/Admin/DoctorController.php resources/views/admin/doctors/show.blade.php tests/Feature/Admin/AdminDoctorShowTest.php
git commit -m @'
feat(fase-2): halaman detail dokter admin dengan nama tampilan dan profil

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
'@
```
Expected: Pint PASS.

**Siklus C — tambah dan ubah, Form Request, `DoctorService`, foto**

- [ ] **Step 12: Tulis tes yang gagal (tool Write) `tests/Feature/Admin/AdminDoctorCrudTest.php`**

```php
<?php

namespace Tests\Feature\Admin;

use App\Enums\DoctorStatus;
use App\Enums\Gender;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Media;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ImageFixtures;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

class AdminDoctorCrudTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithStaff;
    use RefreshDatabase;

    private Clinic $clinic;

    private Specialty $specialtyA;

    private Specialty $specialtyB;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->clinic = $this->createClinic(['name' => 'Poliklinik Anak']);
        $this->specialtyA = $this->createSpecialty(['name' => 'Anak']);
        $this->specialtyB = $this->createSpecialty(['name' => 'Dokter Umum']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Laras Wulandari',
            'title_prefix' => 'dr.',
            'title_suffix' => 'Sp.A',
            'gender' => 'P',
            'clinic_id' => (string) $this->clinic->id,
            'specialty_ids' => [(string) $this->specialtyA->id, (string) $this->specialtyB->id],
            'primary_specialty_id' => (string) $this->specialtyA->id,
            'short_bio' => 'Melayani konsultasi anak.',
            'bio' => 'Biodata contoh tanpa klaim.',
            'sort_order' => '4',
            'meta_title' => '',
            'meta_description' => '',
        ], $overrides);
    }

    public function test_create_form_lists_only_active_choices_and_has_no_status_or_slug_field(): void
    {
        $inactiveClinic = $this->createClinic(['name' => 'Poliklinik Tutup', 'is_active' => false]);
        $inactiveSpecialty = $this->createSpecialty(['name' => 'Spesialisasi Tutup', 'is_active' => false]);

        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.doctors.create'))
            ->assertOk()
            ->assertSee('Tambah Dokter')
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="name"', false)
            ->assertSee('name="title_prefix"', false)
            ->assertSee('name="title_suffix"', false)
            ->assertSee('name="gender"', false)
            ->assertSee('name="clinic_id"', false)
            ->assertSee('name="specialty_ids[]"', false)
            ->assertSee('name="primary_specialty_id"', false)
            ->assertSee('name="photo"', false)
            ->assertSee('name="photo_alt"', false)
            ->assertSee('Poliklinik Anak')
            ->assertSee('Dokter Umum')
            ->assertDontSee($inactiveClinic->name)
            ->assertDontSee($inactiveSpecialty->name)
            ->assertDontSee('name="status"', false)
            ->assertDontSee('name="slug"', false);
    }

    public function test_content_admin_adds_a_doctor_with_specialties_and_one_primary_crd01(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.doctors.store'), $this->payload())
            ->assertRedirect(route('admin.doctors.index'))
            ->assertSessionHas('success', 'Dokter berhasil ditambahkan.');

        $doctor = Doctor::query()->where('name', 'Laras Wulandari')->sole();

        $this->assertSame('laras-wulandari', $doctor->slug);
        $this->assertSame(DoctorStatus::Active, $doctor->status);
        $this->assertSame(Gender::Female, $doctor->gender);
        $this->assertSame($this->clinic->id, $doctor->clinic_id);
        $this->assertNull($doctor->archived_at);
        $this->assertSame(4, $doctor->sort_order);

        $pivot = DB::table('doctor_specialties')->where('doctor_id', $doctor->id)->orderBy('specialty_id')->get();
        $this->assertCount(2, $pivot);
        $this->assertSame([$this->specialtyA->id], $pivot->where('is_primary', 1)->pluck('specialty_id')->map(fn ($id) => (int) $id)->all());
        $this->assertDatabaseHas('audit_logs', ['action' => 'doctor.created']);
    }

    public function test_two_doctors_with_the_same_name_get_numbered_slugs(): void
    {
        $this->actingAs($this->createStaff('content-admin'));

        $this->post(route('admin.doctors.store'), $this->payload(['name' => 'Andi Pratama']))->assertSessionHasNoErrors();
        $this->post(route('admin.doctors.store'), $this->payload(['name' => 'Andi Pratama']))->assertSessionHasNoErrors();

        $this->assertSame(['andi-pratama', 'andi-pratama-2'], Doctor::query()->orderBy('id')->pluck('slug')->all());
    }

    public function test_status_slug_and_archived_at_cannot_be_forced_through_the_form(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.doctors.store'), $this->payload(['status' => 'archived', 'slug' => 'dipaksa', 'archived_at' => '2026-01-01']))
            ->assertRedirect(route('admin.doctors.index'));

        $doctor = Doctor::query()->where('name', 'Laras Wulandari')->sole();

        $this->assertSame(DoctorStatus::Active, $doctor->status);
        $this->assertSame('laras-wulandari', $doctor->slug);
        $this->assertNull($doctor->archived_at);
    }

    public function test_photo_uploaded_on_create_is_stored_for_the_doctor_med01(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.doctors.store'), $this->payload([
                'photo' => ImageFixtures::jpeg(800, 800, 'foto.jpg'),
                'photo_alt' => 'Foto dr. Laras Wulandari',
            ]))
            ->assertRedirect(route('admin.doctors.index'));

        $doctor = Doctor::query()->where('name', 'Laras Wulandari')->sole();
        $media = Media::query()->where('collection', 'photo')->sole();

        $this->assertSame(Doctor::class, $media->model_type);
        $this->assertSame($doctor->id, $media->model_id);
        $this->assertSame('public', $media->disk);
        $this->assertSame('Foto dr. Laras Wulandari', $media->alt_text);
        $this->assertSame('jpg', $media->extension);
        $this->assertStringStartsWith('photo/'.now()->format('Y/m'), $media->directory);
        $this->assertNotNull($media->thumb_path);
        Storage::disk('public')->assertExists($media->directory.'/'.$media->file_name);
        Storage::disk('public')->assertExists($media->thumb_path);
    }

    public function test_edit_form_shows_current_values_and_the_primary_specialty(): void
    {
        $doctor = $this->createDoctor(['name' => 'Laras Wulandari', 'title_prefix' => 'dr.', 'title_suffix' => 'Sp.A', 'short_bio' => 'Bio singkat lama.'], $this->clinic, [$this->specialtyB, $this->specialtyA]);

        $this->actingAs($this->createStaff('super-admin'))
            ->get(route('admin.doctors.edit', $doctor))
            ->assertOk()
            ->assertSee('Ubah')
            ->assertSee('value="Laras Wulandari"', false)
            ->assertSee('value="Sp.A"', false)
            ->assertSee('Bio singkat lama.')
            ->assertSee('action="'.route('admin.doctors.update', $doctor).'"', false)
            ->assertSee('name="_method" value="PUT"', false)
            ->assertSee('id="primary-specialty-'.$this->specialtyB->id.'" value="'.$this->specialtyB->id.'" checked', false);
    }

    public function test_update_changes_data_clinic_and_specialties_but_never_slug_or_status(): void
    {
        $other = $this->createClinic(['name' => 'Poliklinik Mata']);
        $doctor = Doctor::factory()->inactive()->create(['name' => 'Nama Lama', 'clinic_id' => $this->clinic->id]);
        $doctor->specialties()->attach($this->specialtyA->id, ['is_primary' => true]);
        $slug = $doctor->slug;

        $this->actingAs($this->createStaff('content-admin'))
            ->put(route('admin.doctors.update', $doctor), $this->payload([
                'name' => 'Nama Baru',
                'clinic_id' => (string) $other->id,
                'specialty_ids' => [(string) $this->specialtyB->id],
                'primary_specialty_id' => (string) $this->specialtyB->id,
                'status' => 'active',
                'slug' => 'dipaksa',
            ]))
            ->assertRedirect(route('admin.doctors.show', $doctor))
            ->assertSessionHas('success', 'Dokter berhasil diperbarui.');

        $doctor->refresh();
        $this->assertSame('Nama Baru', $doctor->name);
        $this->assertSame($other->id, $doctor->clinic_id);
        $this->assertSame($slug, $doctor->slug);
        $this->assertSame(DoctorStatus::Inactive, $doctor->status);
        $this->assertSame(
            [[$this->specialtyB->id, 1]],
            DB::table('doctor_specialties')->where('doctor_id', $doctor->id)->get()->map(fn ($row) => [(int) $row->specialty_id, (int) $row->is_primary])->all(),
        );
        $this->assertDatabaseHas('audit_logs', ['action' => 'doctor.updated']);
    }

    public function test_update_may_keep_a_clinic_and_specialty_that_became_inactive_but_cannot_pick_new_inactive_ones(): void
    {
        $doctor = $this->createDoctor([], $this->clinic, [$this->specialtyA]);
        $this->clinic->update(['is_active' => false]);
        $this->specialtyA->update(['is_active' => false]);
        $otherInactive = $this->createClinic(['name' => 'Poliklinik Tutup', 'is_active' => false]);
        $this->actingAs($this->createStaff('content-admin'));

        $keep = $this->payload([
            'specialty_ids' => [(string) $this->specialtyA->id],
            'primary_specialty_id' => (string) $this->specialtyA->id,
        ]);

        $this->put(route('admin.doctors.update', $doctor), $keep)->assertSessionHasNoErrors();

        $this->put(route('admin.doctors.update', $doctor), array_merge($keep, ['clinic_id' => (string) $otherInactive->id]))
            ->assertSessionHasErrors(['clinic_id' => 'Poliklinik yang dipilih tidak tersedia.']);

        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.doctors.edit', $doctor))
            ->assertOk()
            ->assertSee('Poliklinik Anak')
            ->assertSee('Anak');
    }

    public function test_update_with_a_new_photo_replaces_the_existing_one(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.doctors.store'), $this->payload(['photo' => ImageFixtures::jpeg(800, 800), 'photo_alt' => 'Foto lama']));
        $doctor = Doctor::query()->where('name', 'Laras Wulandari')->sole();
        $old = Media::query()->where('collection', 'photo')->sole();

        $this->put(route('admin.doctors.update', $doctor), $this->payload(['photo' => ImageFixtures::jpeg(700, 700, 'baru.jpg'), 'photo_alt' => 'Foto baru']))
            ->assertSessionHasNoErrors();

        $new = Media::query()->where('collection', 'photo')->sole();
        $this->assertNotSame($old->id, $new->id);
        $this->assertSame('Foto baru', $new->alt_text);
        Storage::disk('public')->assertMissing($old->directory.'/'.$old->file_name);
    }

    public function test_registration_officer_patient_and_guest_cannot_use_manage_routes(): void
    {
        $doctor = $this->createDoctor(['name' => 'Laras Wulandari']);

        $requests = [
            ['GET', route('admin.doctors.create'), []],
            ['POST', route('admin.doctors.store'), $this->payload(['name' => 'Tidak Boleh'])],
            ['GET', route('admin.doctors.edit', $doctor), []],
            ['PUT', route('admin.doctors.update', $doctor), $this->payload(['name' => 'Diubah Paksa'])],
        ];

        foreach ([$this->createStaff('registration-officer'), User::factory()->patient()->create()] as $user) {
            $this->actingAs($user);

            foreach ($requests as [$method, $url, $data]) {
                $this->call($method, $url, $data)->assertForbidden();
            }
        }

        $this->app['auth']->forgetGuards();

        foreach ($requests as [$method, $url, $data]) {
            $this->call($method, $url, $data)->assertRedirect(route('admin.login'));
        }

        $this->assertSame('Laras Wulandari', $doctor->fresh()->name);
        $this->assertDatabaseMissing('doctors', ['name' => 'Tidak Boleh']);
        $this->assertSame(1, Doctor::query()->count());
    }

    public function test_super_admin_can_open_the_manage_routes(): void
    {
        $doctor = $this->createDoctor();
        $this->actingAs($this->createStaff('super-admin'));

        $this->get(route('admin.doctors.create'))->assertOk();
        $this->get(route('admin.doctors.edit', $doctor))->assertOk();
    }
}
```

Tool Write `tests/Feature/Admin/AdminDoctorValidationTest.php`:

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Media;
use App\Models\Specialty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ImageFixtures;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * VAL-01 dan 12 V-19: pesan Indonesia per field, nilai lama dipertahankan, ringkasan galat.
 * Penanda '@…' pada data provider diganti id nyata di dalam tes; penanda huruf besar menjadi berkas gambar.
 */
class AdminDoctorValidationTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithStaff;
    use RefreshDatabase;

    /** @var array<string, int> */
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->ids['clinic'] = $this->createClinic(['name' => 'Poliklinik Anak'])->id;
        $this->ids['clinic_inactive'] = $this->createClinic(['name' => 'Poliklinik Tutup', 'is_active' => false])->id;
        $this->ids['specialty_a'] = $this->createSpecialty(['name' => 'Anak'])->id;
        $this->ids['specialty_b'] = $this->createSpecialty(['name' => 'Dokter Umum'])->id;
        $this->ids['specialty_inactive'] = $this->createSpecialty(['name' => 'Spesialisasi Tutup', 'is_active' => false])->id;
    }

    /**
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function invalidInputs(): array
    {
        return [
            'nama kosong' => [['name' => ''], 'name', 'Nama dokter wajib diisi.'],
            'nama bukan teks' => [['name' => ['a']], 'name', 'Nama dokter harus berupa teks.'],
            'nama terlalu pendek' => [['name' => 'Ab'], 'name', 'Nama dokter minimal 3 karakter.'],
            'nama terlalu panjang' => [['name' => str_repeat('a', 101)], 'name', 'Nama dokter maksimal 100 karakter.'],
            'nama berisi angka' => [['name' => 'Andi 123'], 'name', 'Nama dokter hanya boleh berisi huruf, spasi, titik, apostrof, dan tanda hubung.'],
            'awalan terlalu panjang' => [['title_prefix' => str_repeat('a', 21)], 'title_prefix', 'Awalan gelar maksimal 20 karakter.'],
            'awalan berisi angka' => [['title_prefix' => 'dr1'], 'title_prefix', 'Awalan gelar hanya boleh berisi huruf, titik, dan spasi.'],
            'akhiran terlalu panjang' => [['title_suffix' => str_repeat('a', 51)], 'title_suffix', 'Akhiran gelar maksimal 50 karakter.'],
            'akhiran berisi simbol' => [['title_suffix' => 'Sp.A<b>'], 'title_suffix', 'Akhiran gelar hanya boleh berisi huruf, angka, titik, koma, spasi, dan tanda hubung.'],
            'jenis kelamin kosong' => [['gender' => ''], 'gender', 'Jenis kelamin wajib dipilih.'],
            'jenis kelamin salah' => [['gender' => 'X'], 'gender', 'Jenis kelamin tidak valid.'],
            'poliklinik kosong' => [['clinic_id' => ''], 'clinic_id', 'Poliklinik utama wajib dipilih.'],
            'poliklinik tidak ada' => [['clinic_id' => '999999'], 'clinic_id', 'Poliklinik yang dipilih tidak tersedia.'],
            'poliklinik nonaktif' => [['clinic_id' => '@clinic_inactive'], 'clinic_id', 'Poliklinik yang dipilih tidak tersedia.'],
            'poliklinik bukan angka' => [['clinic_id' => 'abc'], 'clinic_id', 'Poliklinik utama harus berupa angka bulat.'],
            'spesialisasi kosong' => [['specialty_ids' => []], 'specialty_ids', 'Pilih minimal satu spesialisasi.'],
            'spesialisasi bukan larik' => [['specialty_ids' => 'x'], 'specialty_ids', 'Spesialisasi tidak valid.'],
            'spesialisasi lebih dari lima' => [['specialty_ids' => ['901', '902', '903', '904', '905', '906']], 'specialty_ids', 'Spesialisasi maksimal 5.'],
            'spesialisasi ganda' => [['specialty_ids' => ['@specialty_a', '@specialty_a']], 'specialty_ids.0', 'Spesialisasi tidak boleh dipilih dua kali.'],
            'spesialisasi nonaktif' => [['specialty_ids' => ['@specialty_inactive'], 'primary_specialty_id' => '@specialty_inactive'], 'specialty_ids.0', 'Spesialisasi yang dipilih tidak tersedia.'],
            'spesialisasi bukan angka' => [['specialty_ids' => ['abc']], 'specialty_ids.0', 'Spesialisasi tidak valid.'],
            'utama kosong' => [['primary_specialty_id' => ''], 'primary_specialty_id', 'Spesialisasi utama wajib dipilih.'],
            'utama bukan angka' => [['primary_specialty_id' => 'abc'], 'primary_specialty_id', 'Spesialisasi utama harus berupa angka bulat.'],
            'utama bukan salah satu pilihan' => [['specialty_ids' => ['@specialty_a'], 'primary_specialty_id' => '@specialty_b'], 'primary_specialty_id', 'Spesialisasi utama harus salah satu spesialisasi yang dipilih.'],
            'profil singkat terlalu panjang' => [['short_bio' => str_repeat('a', 501)], 'short_bio', 'Profil singkat maksimal 500 karakter.'],
            'profil lengkap terlalu panjang' => [['bio' => str_repeat('a', 5001)], 'bio', 'Profil lengkap maksimal 5000 karakter.'],
            'profil lengkap berisi html' => [['bio' => 'Halo <b>tebal</b>'], 'bio', 'Profil lengkap tidak boleh mengandung tag HTML.'],
            'urutan bukan angka' => [['sort_order' => 'abc'], 'sort_order', 'Urutan harus berupa angka bulat.'],
            'urutan terlalu besar' => [['sort_order' => '10000'], 'sort_order', 'Urutan harus antara 0 dan 9999.'],
            'meta title 71 karakter' => [['meta_title' => str_repeat('a', 71)], 'meta_title', 'Meta title maksimal 70 karakter.'],
            'meta description 161 karakter' => [['meta_description' => str_repeat('a', 161)], 'meta_description', 'Meta description maksimal 160 karakter.'],
            'alt foto wajib' => [['photo' => 'FOTO', 'photo_alt' => ''], 'photo_alt', 'Teks alternatif foto wajib diisi.'],
            'alt foto terlalu panjang' => [['photo' => 'FOTO', 'photo_alt' => str_repeat('a', 256)], 'photo_alt', 'Teks alternatif foto maksimal 255 karakter.'],
            'foto terlalu kecil' => [['photo' => 'KECIL', 'photo_alt' => 'x'], 'photo', 'Dimensi gambar minimal 300 x 300 piksel dan maksimal 6000 x 6000 piksel.'],
            'foto terlalu besar' => [['photo' => 'BESAR', 'photo_alt' => 'x'], 'photo', 'Ukuran gambar maksimal 2 MB.'],
        ];
    }

    /**
     * @param  array<string, mixed>  $override
     */
    #[DataProvider('invalidInputs')]
    public function test_store_rejects_invalid_input_with_indonesian_messages(array $override, string $field, string $message): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.doctors.create'))
            ->post(route('admin.doctors.store'), array_merge($this->valid(), $this->resolve($override)))
            ->assertRedirect(route('admin.doctors.create'))
            ->assertSessionHasErrors([$field => $message]);

        $this->assertSame(0, Doctor::query()->count());
        $this->assertSame(0, Media::query()->count());
    }

    public function test_missing_specialties_and_primary_report_the_required_messages(): void
    {
        $payload = $this->valid();
        unset($payload['specialty_ids'], $payload['primary_specialty_id']);

        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.doctors.store'), $payload)
            ->assertSessionHasErrors([
                'specialty_ids' => 'Pilih minimal satu spesialisasi.',
                'primary_specialty_id' => 'Spesialisasi utama wajib dipilih.',
            ]);
    }

    public function test_specialty_error_is_rendered_exactly_once_and_the_summary_links_to_the_fieldset(): void
    {
        $this->createSpecialty(['name' => 'Bedah']);
        $payload = $this->valid();
        unset($payload['specialty_ids'], $payload['primary_specialty_id']);

        $html = $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.doctors.create'))
            ->followingRedirects()
            ->post(route('admin.doctors.store'), $payload)
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($html, 'Pilih minimal satu spesialisasi.</div>'));
        $this->assertSame(1, substr_count($html, 'id="specialty_ids-error"'));
        $this->assertSame(1, substr_count($html, '<fieldset id="specialty_ids" tabindex="-1"'));
        $this->assertStringContainsString('href="#specialty_ids"', $html);
    }

    public function test_empty_and_partly_wrong_form_shows_messages_summary_and_old_input_val01(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.doctors.create'))
            ->post(route('admin.doctors.store'), [
                'name' => '',
                'gender' => 'X',
                'clinic_id' => '999999',
                'short_bio' => 'Bio lama yang dipertahankan',
                'title_prefix' => 'dr.',
                'specialty_ids' => [(string) $this->ids['specialty_a']],
                'primary_specialty_id' => (string) $this->ids['specialty_a'],
            ])
            ->assertRedirect(route('admin.doctors.create'))
            ->assertSessionHasErrors(['name', 'gender', 'clinic_id']);

        $this->get(route('admin.doctors.create'))
            ->assertOk()
            ->assertSee('data-errors-summary', false)
            ->assertSee('Nama dokter wajib diisi.')
            ->assertSee('Jenis kelamin tidak valid.')
            ->assertSee('Poliklinik yang dipilih tidak tersedia.')
            ->assertSee('href="#name"', false)
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('aria-describedby="name-help name-error"', false)
            ->assertSee('Bio lama yang dipertahankan')
            ->assertSee('value="dr."', false);

        $this->assertSame(0, Doctor::query()->count());
    }

    public function test_completely_empty_submission_lists_every_required_message(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.doctors.store'), [])
            ->assertSessionHasErrors([
                'name' => 'Nama dokter wajib diisi.',
                'gender' => 'Jenis kelamin wajib dipilih.',
                'clinic_id' => 'Poliklinik utama wajib dipilih.',
                'specialty_ids' => 'Pilih minimal satu spesialisasi.',
                'primary_specialty_id' => 'Spesialisasi utama wajib dipilih.',
            ]);
    }

    public function test_svg_and_fake_images_are_rejected_for_the_doctor_photo_sek09(): void
    {
        $this->actingAs($this->createStaff('content-admin'));

        foreach ([ImageFixtures::svgWithScript(), ImageFixtures::textNamedJpg()] as $file) {
            $this->post(route('admin.doctors.store'), array_merge($this->valid(), ['photo' => $file, 'photo_alt' => 'x']))
                ->assertSessionHasErrors('photo');
        }

        $this->assertSame(0, Doctor::query()->count());
        $this->assertSame(0, Media::query()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_update_applies_the_same_rules(): void
    {
        $doctor = $this->createDoctor(['name' => 'Laras Wulandari']);

        $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.doctors.edit', $doctor))
            ->put(route('admin.doctors.update', $doctor), array_merge($this->valid(), ['name' => 'Ab']))
            ->assertRedirect(route('admin.doctors.edit', $doctor))
            ->assertSessionHasErrors(['name' => 'Nama dokter minimal 3 karakter.']);

        $this->assertSame('Laras Wulandari', $doctor->fresh()->name);
    }

    /**
     * @return array<string, mixed>
     */
    private function valid(): array
    {
        return [
            'name' => 'Laras Wulandari',
            'gender' => 'P',
            'clinic_id' => (string) $this->ids['clinic'],
            'specialty_ids' => [(string) $this->ids['specialty_a']],
            'primary_specialty_id' => (string) $this->ids['specialty_a'],
        ];
    }

    private function resolve(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($item) => $this->resolve($item), $value);
        }

        if (! is_string($value)) {
            return $value;
        }

        return match (true) {
            str_starts_with($value, '@') => (string) $this->ids[substr($value, 1)],
            $value === 'FOTO' => ImageFixtures::jpeg(800, 800),
            $value === 'KECIL' => ImageFixtures::jpeg(200, 200),
            $value === 'BESAR' => ImageFixtures::oversizedJpeg(2100, 800, 800),
            default => $value,
        };
    }
}
```

- [ ] **Step 13: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="AdminDoctorCrudTest|AdminDoctorValidationTest"
```
Expected: FAIL — `BadMethodCallException: Method App\Http\Controllers\Admin\DoctorController::create does not exist.` (dan `store`/`edit`/`update`); tes otorisasi gagal pada route `admin.doctors.create` yang belum ada (`RouteNotFoundException` tidak muncul karena resource sudah terdaftar; gagal di method).

- [ ] **Step 14: Tulis Form Request, controller, dan view**

Tool Write `app/Http/Requests/Admin/StoreDoctorRequest.php`:

```php
<?php

namespace App\Http\Requests\Admin;

use App\Enums\Gender;
use App\Models\Doctor;
use App\Rules\ImageRules;
use App\Rules\NoHtml;
use App\Rules\PersonName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * V-19 (F2-R6, F2-R15, F2-R23). `slug` dan `status` tidak ada di form: slug dibuat sistem dan status
 * baru selalu `active` (diubah lewat admin.doctors.status).
 */
class StoreDoctorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Doctor::class);
    }

    /** Dokter yang sedang diubah; null pada tambah. */
    protected function currentDoctor(): ?Doctor
    {
        return null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $doctor = $this->currentDoctor();
        $keepClinicIds = $doctor === null ? [] : [(int) $doctor->clinic_id];
        $keepSpecialtyIds = $doctor === null ? [] : array_map('intval', $doctor->specialties()->pluck('specialties.id')->all());
        $chosen = array_map('strval', array_values(array_filter((array) $this->input('specialty_ids', []), 'is_scalar')));

        return [
            'name' => ['bail', 'required', 'string', 'min:3', 'max:100', new PersonName],
            'title_prefix' => ['nullable', 'string', 'max:20', 'regex:/^[\p{L}.\s]+$/u'],
            'title_suffix' => ['nullable', 'string', 'max:50', 'regex:/^[\p{L}0-9.,\s-]+$/u'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'clinic_id' => ['required', 'integer', $this->activeOrKept('clinics', $keepClinicIds)],
            'specialty_ids' => ['required', 'array', 'min:1', 'max:5'],
            'specialty_ids.*' => ['integer', 'distinct', $this->activeOrKept('specialties', $keepSpecialtyIds)],
            'primary_specialty_id' => ['required', 'integer', Rule::in($chosen)],
            'short_bio' => ['nullable', 'string', 'max:500'],
            'bio' => ['nullable', 'string', 'max:5000', new NoHtml],
            'sort_order' => ['nullable', 'integer', 'between:0,9999'],
            'meta_title' => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:160'],
            'photo' => ['nullable', ...ImageRules::for('photo')],
            'photo_alt' => ['required_with:photo', 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(ImageRules::messages('photo', 'photo'), [
            'name.required' => 'Nama dokter wajib diisi.',
            'name.string' => 'Nama dokter harus berupa teks.',
            'name.min' => 'Nama dokter minimal 3 karakter.',
            'name.max' => 'Nama dokter maksimal 100 karakter.',
            'name.person_name' => 'Nama dokter hanya boleh berisi huruf, spasi, titik, apostrof, dan tanda hubung.',
            'title_prefix.string' => 'Awalan gelar harus berupa teks.',
            'title_prefix.max' => 'Awalan gelar maksimal 20 karakter.',
            'title_prefix.regex' => 'Awalan gelar hanya boleh berisi huruf, titik, dan spasi.',
            'title_suffix.string' => 'Akhiran gelar harus berupa teks.',
            'title_suffix.max' => 'Akhiran gelar maksimal 50 karakter.',
            'title_suffix.regex' => 'Akhiran gelar hanya boleh berisi huruf, angka, titik, koma, spasi, dan tanda hubung.',
            'gender.required' => 'Jenis kelamin wajib dipilih.',
            'gender.enum' => 'Jenis kelamin tidak valid.',
            'clinic_id.required' => 'Poliklinik utama wajib dipilih.',
            'clinic_id.integer' => 'Poliklinik utama harus berupa angka bulat.',
            'clinic_id.exists' => 'Poliklinik yang dipilih tidak tersedia.',
            'specialty_ids.required' => 'Pilih minimal satu spesialisasi.',
            'specialty_ids.array' => 'Spesialisasi tidak valid.',
            'specialty_ids.min' => 'Pilih minimal satu spesialisasi.',
            'specialty_ids.max' => 'Spesialisasi maksimal 5.',
            'specialty_ids.*.integer' => 'Spesialisasi tidak valid.',
            'specialty_ids.*.distinct' => 'Spesialisasi tidak boleh dipilih dua kali.',
            'specialty_ids.*.exists' => 'Spesialisasi yang dipilih tidak tersedia.',
            'primary_specialty_id.required' => 'Spesialisasi utama wajib dipilih.',
            'primary_specialty_id.integer' => 'Spesialisasi utama harus berupa angka bulat.',
            'primary_specialty_id.in' => 'Spesialisasi utama harus salah satu spesialisasi yang dipilih.',
            'short_bio.string' => 'Profil singkat harus berupa teks.',
            'short_bio.max' => 'Profil singkat maksimal 500 karakter.',
            'bio.string' => 'Profil lengkap harus berupa teks.',
            'bio.max' => 'Profil lengkap maksimal 5000 karakter.',
            'bio.no_html' => 'Profil lengkap tidak boleh mengandung tag HTML.',
            'sort_order.integer' => 'Urutan harus berupa angka bulat.',
            'sort_order.between' => 'Urutan harus antara 0 dan 9999.',
            'meta_title.string' => 'Meta title harus berupa teks.',
            'meta_title.max' => 'Meta title maksimal 70 karakter.',
            'meta_description.string' => 'Meta description harus berupa teks.',
            'meta_description.max' => 'Meta description maksimal 160 karakter.',
            'photo_alt.required_with' => 'Teks alternatif foto wajib diisi.',
            'photo_alt.string' => 'Teks alternatif foto harus berupa teks.',
            'photo_alt.max' => 'Teks alternatif foto maksimal 255 karakter.',
        ]);
    }

    /**
     * Data untuk DoctorService: seluruh hasil validasi kecuali berkas foto dan teks alternatifnya.
     *
     * @return array<string, mixed>
     */
    public function doctorData(): array
    {
        $data = Arr::except($this->validated(), ['photo', 'photo_alt']);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
    }

    public function photoUpload(): ?UploadedFile
    {
        $file = $this->file('photo');

        return $file instanceof UploadedFile ? $file : null;
    }

    public function photoAlt(): ?string
    {
        $alt = $this->input('photo_alt');

        return is_string($alt) && trim($alt) !== '' ? trim($alt) : null;
    }

    /**
     * Hanya baris aktif, ditambah baris yang sudah dimiliki dokter ini (agar data lama yang kini
     * nonaktif tidak membuat form tidak dapat disimpan).
     *
     * @param  list<int>  $keepIds
     */
    private function activeOrKept(string $table, array $keepIds): Exists
    {
        return Rule::exists($table, 'id')->where(function ($query) use ($keepIds): void {
            $query->where(function ($inner) use ($keepIds): void {
                $inner->where('is_active', true);

                if ($keepIds !== []) {
                    $inner->orWhereIn('id', $keepIds);
                }
            });
        });
    }
}
```

Tool Write `app/Http/Requests/Admin/UpdateDoctorRequest.php`:

```php
<?php

namespace App\Http\Requests\Admin;

use App\Models\Doctor;

/**
 * V-19 untuk ubah: aturan sama dengan tambah; poliklinik dan spesialisasi dokter ini boleh dipertahankan
 * walau kini nonaktif. `slug` dan `status` tetap tidak dapat diubah dari form.
 */
class UpdateDoctorRequest extends StoreDoctorRequest
{
    public function authorize(): bool
    {
        $doctor = $this->route('doctor');

        return $doctor instanceof Doctor && (bool) $this->user()?->can('update', $doctor);
    }

    protected function currentDoctor(): ?Doctor
    {
        $doctor = $this->route('doctor');

        return $doctor instanceof Doctor ? $doctor : null;
    }
}
```

Tool Write `app/Http/Controllers/Admin/DoctorController.php` (ganti seluruh isi):

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DoctorIndexRequest;
use App\Http\Requests\Admin\StoreDoctorRequest;
use App\Http\Requests\Admin\UpdateDoctorRequest;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Specialty;
use App\Services\Doctors\DoctorService;
use App\Support\Actor;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DoctorController extends Controller
{
    public function index(DoctorIndexRequest $request): View
    {
        $this->authorize('viewAny', Doctor::class);

        $doctors = $request
            ->applyTo(Doctor::query()->with(['clinic', 'specialties', 'media']))
            ->paginate($request->perPage());

        return view('admin.doctors.index', [
            'doctors' => $doctors,
            'statusFilter' => $request->statusFilter(),
            'clinicOptions' => Clinic::query()->ordered()->pluck('name', 'slug')->all(),
            'specialtyOptions' => Specialty::query()->ordered()->pluck('name', 'slug')->all(),
        ]);
    }

    public function show(Doctor $doctor): View
    {
        $this->authorize('view', $doctor);

        return view('admin.doctors.show', [
            'doctor' => $doctor->load(['clinic', 'specialties', 'media']),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Doctor::class);

        return view('admin.doctors.create', $this->formOptions(null));
    }

    public function store(StoreDoctorRequest $request, DoctorService $service): RedirectResponse
    {
        $this->authorize('create', Doctor::class);

        $service->create($request->doctorData(), Actor::staff($request->user()), $request->photoUpload(), $request->photoAlt());

        return redirect()->route('admin.doctors.index')
            ->with('success', __('messages.created', ['entity' => __('messages.entities.doctor')]));
    }

    public function edit(Doctor $doctor): View
    {
        $this->authorize('update', $doctor);

        $doctor->load(['clinic', 'specialties', 'media']);

        return view('admin.doctors.edit', ['doctor' => $doctor] + $this->formOptions($doctor));
    }

    public function update(UpdateDoctorRequest $request, Doctor $doctor, DoctorService $service): RedirectResponse
    {
        $this->authorize('update', $doctor);

        $service->update($doctor, $request->doctorData(), Actor::staff($request->user()), $request->photoUpload(), $request->photoAlt());

        return redirect()->route('admin.doctors.show', $doctor)
            ->with('success', __('messages.updated', ['entity' => __('messages.entities.doctor')]));
    }

    /**
     * Pilihan form: poliklinik dan spesialisasi aktif, ditambah yang kini dimiliki dokter ini.
     *
     * @return array{clinics: array<int, string>, specialties: \Illuminate\Database\Eloquent\Collection<int, Specialty>}
     */
    private function formOptions(?Doctor $doctor): array
    {
        $clinics = Clinic::query()
            ->where(function ($query) use ($doctor): void {
                $query->where('is_active', true);

                if ($doctor !== null) {
                    $query->orWhere('id', $doctor->clinic_id);
                }
            })
            ->ordered()
            ->pluck('name', 'id')
            ->all();

        $specialties = Specialty::query()
            ->where(function ($query) use ($doctor): void {
                $query->where('is_active', true);

                if ($doctor !== null) {
                    $query->orWhereIn('id', $doctor->specialties->pluck('id')->all());
                }
            })
            ->ordered()
            ->get();

        return ['clinics' => $clinics, 'specialties' => $specialties];
    }
}
```

Tool Write `resources/views/admin/doctors/_form.blade.php`:

```blade
{{-- Variabel: $doctor (Doctor|null), $clinics (id => nama), $specialties (koleksi Specialty), $action, $submitLabel.
     Tanpa input status dan slug (F2-R5, F2-R6). --}}
@php
    $isEdit = $doctor !== null;
    $selectedIds = array_map('intval', (array) old('specialty_ids', $isEdit ? $doctor->specialties->pluck('id')->all() : []));
    $primaryId = (int) old('primary_specialty_id', $isEdit ? ($doctor->specialties->firstWhere('pivot.is_primary', true)?->id ?? 0) : 0);
    $specialtyError = (string) ($errors->first('specialty_ids') ?: $errors->first('specialty_ids.*'));
    $primaryError = (string) $errors->first('primary_specialty_id');
    $photo = $isEdit ? $doctor->firstMedia('photo') : null;
@endphp
<form method="POST" action="{{ $action }}" enctype="multipart/form-data">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <x-form.errors-summary />

    <div class="row g-4">
        <div class="col-12 col-lg-4">
            <div class="card">
                <div class="card-header fw-semibold">Foto dokter</div>
                <div class="card-body">
                    <x-form.file name="photo" label="Foto" :current-media="$photo" alt-name="photo_alt" :alt-value="old('photo_alt', $photo?->alt_text)" help="Minimal 300 x 300 piksel. Tanpa foto, situs memakai gambar pengganti. Mengunggah foto baru menggantikan foto lama." />
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-8">
            <div class="card mb-4">
                <div class="card-body">
                    <x-form.input name="name" label="Nama (tanpa gelar)" :value="$doctor?->name" :required="true" maxlength="100" help="Contoh: Laras Wulandari. Nama tampilan dibentuk dari gelar depan, nama, dan gelar belakang; alamat halaman (slug) dibuat otomatis dan tidak berubah." />
                    <div class="row">
                        <div class="col-12 col-sm-5">
                            <x-form.input name="title_prefix" label="Gelar depan" :value="$doctor?->title_prefix" maxlength="20" help="Contoh: dr., drg." />
                        </div>
                        <div class="col-12 col-sm-7">
                            <x-form.input name="title_suffix" label="Gelar belakang" :value="$doctor?->title_suffix" maxlength="50" help="Contoh: Sp.A, Sp.PD." />
                        </div>
                    </div>
                    <x-form.radio-group name="gender" legend="Jenis kelamin" :options="\App\Enums\Gender::options()" :selected="$doctor?->gender" :inline="true" :required="true" />
                    <x-form.select name="clinic_id" label="Poliklinik utama" :options="$clinics" :selected="$doctor?->clinic_id" placeholder="Pilih poliklinik" :required="true" help="Hanya poliklinik aktif." />

                    <fieldset id="specialty_ids" tabindex="-1" class="mb-3"@if ($specialtyError !== '' || $primaryError !== '') aria-describedby="{{ trim(($specialtyError !== '' ? 'specialty_ids-error ' : '').($primaryError !== '' ? 'primary_specialty_id' : '')) }}"@endif>
                        <legend class="form-label fw-semibold fs-6">Spesialisasi <span class="required-mark" aria-hidden="true">*</span></legend>
                        <p class="form-text">Pilih 1 sampai 5 spesialisasi, lalu tandai tepat satu sebagai Utama.</p>
                        <div class="border rounded p-2">
                            @foreach ($specialties as $specialty)
                                <div class="row g-2 align-items-start border-bottom">
                                    <div class="col-8">
                                        <x-form.checkbox name="specialty_ids[]" :value="$specialty->id" :label="$specialty->name.($specialty->is_active ? '' : ' (nonaktif)')" :checked-values="$selectedIds" :show-error="false" />
                                    </div>
                                    <div class="col-4">
                                        <div class="form-check mt-1">
                                            <input class="form-check-input" type="radio" name="primary_specialty_id" id="primary-specialty-{{ $specialty->id }}" value="{{ $specialty->id }}" @checked($primaryId === $specialty->id)>
                                            <label class="form-check-label" for="primary-specialty-{{ $specialty->id }}">Utama<span class="visually-hidden">: jadikan {{ $specialty->name }} spesialisasi utama</span></label>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        {{-- K3: setiap kotak :show-error="false"; galat kelompok dirender SATU kali di sini (id yang ditunjuk aria-describedby tiap kotak, Task 5). --}}
                        @if ($specialtyError !== '')
                            <div id="specialty_ids-error" class="invalid-feedback d-block">{{ $specialtyError }}</div>
                        @endif
                        @if ($primaryError !== '')
                            <div id="primary_specialty_id" tabindex="-1" class="invalid-feedback d-block">{{ $primaryError }}</div>
                        @endif
                    </fieldset>

                    <x-form.textarea name="short_bio" label="Profil singkat" :value="$doctor?->short_bio" :rows="3" maxlength="500" help="Maksimal 500 karakter; tampil di kartu dokter." />
                    <x-form.textarea name="bio" label="Profil lengkap" :value="$doctor?->bio" :rows="6" maxlength="5000" help="Teks biasa tanpa tag HTML. Pisahkan paragraf dengan satu baris kosong." />
                    <x-form.input name="sort_order" type="number" label="Urutan tampil" :value="$doctor?->sort_order ?? 0" inputmode="numeric" min="0" max="9999" help="Angka kecil tampil lebih dulu." />

                    <details class="mb-3" @if ($errors->has('meta_title') || $errors->has('meta_description')) open @endif>
                        <summary class="fw-semibold mb-2">Pengaturan SEO (opsional)</summary>
                        <x-form.input name="meta_title" label="Meta title" :value="$doctor?->meta_title" maxlength="70" help="Maksimal 70 karakter. Kosong = memakai nama tampilan." />
                        <x-form.textarea name="meta_description" label="Meta description" :value="$doctor?->meta_description" :rows="2" maxlength="160" help="Maksimal 160 karakter. Kosong = memakai profil singkat." />
                    </details>

                    @unless ($isEdit)
                        <p class="form-text mb-0">Dokter baru berstatus Aktif. Status diubah dari daftar atau halaman detail dokter.</p>
                    @endunless
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 justify-content-end">
        <a href="{{ $isEdit ? route('admin.doctors.show', $doctor) : route('admin.doctors.index') }}" class="btn btn-outline-secondary">Batal</a>
        <x-loading-button icon="bi-check-lg" loading-text="Menyimpan…">{{ $submitLabel }}</x-loading-button>
    </div>
</form>
```


Tool Write `resources/views/admin/doctors/create.blade.php`:

```blade
<x-layouts.admin title="Tambah Dokter">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="[
            ['label' => 'Dokter', 'url' => route('admin.doctors.index')],
            ['label' => 'Tambah Dokter'],
        ]" />
    </x-slot:breadcrumb>

    @include('admin.doctors._form', [
        'doctor' => null,
        'clinics' => $clinics,
        'specialties' => $specialties,
        'action' => route('admin.doctors.store'),
        'submitLabel' => 'Simpan',
    ])
</x-layouts.admin>
```

Tool Write `resources/views/admin/doctors/edit.blade.php`:

```blade
<x-layouts.admin :title="'Ubah '.$doctor->display_name">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="[
            ['label' => 'Dokter', 'url' => route('admin.doctors.index')],
            ['label' => $doctor->display_name, 'url' => route('admin.doctors.show', $doctor)],
            ['label' => 'Ubah'],
        ]" />
    </x-slot:breadcrumb>

    @include('admin.doctors._form', [
        'doctor' => $doctor,
        'clinics' => $clinics,
        'specialties' => $specialties,
        'action' => route('admin.doctors.update', $doctor),
        'submitLabel' => 'Simpan Perubahan',
    ])
</x-layouts.admin>
```

- [ ] **Step 15: Jalankan tes — harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="AdminDoctor"
& $PHP artisan test --filter=AdminRouteMiddlewareTest
npm run build
```
Expected: PASS untuk keempat kelas `AdminDoctor*` (termasuk 35 kasus data provider validasi); `AdminRouteMiddlewareTest` (KNS-02) tetap PASS; build sukses.

- [ ] **Step 16: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint
& $PHP vendor\bin\pint --test
git add app/Http/Controllers/Admin/DoctorController.php app/Http/Requests/Admin/StoreDoctorRequest.php app/Http/Requests/Admin/UpdateDoctorRequest.php resources/views/admin/doctors tests/Feature/Admin/AdminDoctorCrudTest.php tests/Feature/Admin/AdminDoctorValidationTest.php
git commit -m @'
feat(fase-2): tambah dan ubah dokter lewat DoctorService dengan validasi V-19 dan foto

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
'@
```
Expected: `pint` tanpa `--test` dijalankan lebih dulu dan merapikan berkas yang disentuh (mis. urutan impor `routes/admin.php` atau `DoctorController` yang baru ditambah); lalu `pint --test` PASS sebelum commit.

- [ ] **Step 17: Seluruh suite**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test
& $PHP vendor\bin\pint --test
```
Expected: seluruh suite PASS; Pint PASS.

**Catatan konflik (Task 11):**
- Kontrak Task 8 tidak merinci isi `$data` untuk `DoctorService::create/update`. Plan ini menganggap `$data` memuat `specialty_ids` dan `primary_specialty_id` (hasil validasi tanpa `photo`/`photo_alt`). Bila implementasi Task 8 meminta kunci lain, hanya `StoreDoctorRequest::doctorData()` yang diubah.
- `x-form.checkbox` larik (Task 5, keputusan K3) memasang `aria-invalid` pada semua kotak dan tidak merender teks galat bila `showError` false. Form ini memberi `:show-error="false"` pada setiap kotak dan merender satu elemen galat `specialty_ids-error` di bawah `<fieldset id="specialty_ids" tabindex="-1">`, sehingga galat spesialisasi tampil tepat satu kali dan tautan `#specialty_ids` dari `x-form.errors-summary` mendarat di fieldset. Galat `primary_specialty_id` dirender form sendiri.

---

### Task 12: Admin Dokter — status, arsip, pemulihan, dan ganti foto

**Files:**
- Create: `app/Http/Requests/Admin/UpdateDoctorStatusRequest.php`, `ArchiveDoctorRequest.php`, `UploadDoctorPhotoRequest.php`
- Create: `resources/views/admin/doctors/_status-actions.blade.php`, `resources/views/admin/doctors/_status-modals.blade.php`
- Modify: `app/Http/Controllers/Admin/DoctorController.php` (`status`, `archive`, `photo`)
- Modify: `routes/admin.php` (tiga route di grup `permission:doctors.manage`)
- Modify: `resources/views/admin/doctors/index.blade.php`, `resources/views/admin/doctors/show.blade.php` (tombol aksi, modal, kartu ganti foto)
- Test: `tests/Feature/Admin/AdminDoctorStatusTest.php`, `AdminDoctorArchiveTest.php`, `AdminDoctorPhotoTest.php`

**Interfaces:**
- Consumes:
  - Task 8: `DoctorService::changeStatus(Doctor $doctor, DoctorStatus $status, ?string $reason, Actor $actor): Doctor` (`active`↔`inactive` dan pemulihan `archived` → `inactive` yang mengosongkan `archived_at`, audit `doctor.status_changed`), `archive(Doctor $doctor, ?string $reason, Actor $actor): Doctor` (status `archived`, `archived_at` terisi, audit `doctor.archived`), `replacePhoto(Doctor $doctor, UploadedFile $photo, string $alt, Actor $actor): Media`, dan `archiveBlockedReason(Doctor $doctor): ?string` (publik; pesan penolakan arsip atau `null`, memakai titik sambung `protected futureActiveAppointmentsCount()` yang fase 2 selalu `0`, F2-R8). Task ini **tidak** menambah method pada service.
  - Task 4: `DoctorPolicy::changeStatus|archive|update`. Task 2: `HasMedia`. Task 11: `DoctorController`, view `index`/`show` dengan komentar penanda `{{-- Aksi status dokter --}}`, `{{-- Modal konfirmasi status dokter --}}`, `{{-- Ganti foto dokter --}}`; route grup `doctors.manage`.
  - Fase 1: `x-confirm-modal` (modal bersama lewat `data-confirm-action`/`data-confirm-message`; teks `reason` di slot akan dikosongkan `admin.js` saat modal ditutup), `ImageRules`, `x-form.file` (`alt-name`), `Doctor::scopePubliclyVisible()` (Task 1).
- Produces:
  - Route (di dalam grup `permission:doctors.manage`, setelah resource dokter): `PATCH /admin/dokter/{doctor}/status` → `admin.doctors.status`; `PATCH /admin/dokter/{doctor}/arsip` → `admin.doctors.archive`; `POST /admin/dokter/{doctor}/foto` → `admin.doctors.photo`.
  - `UpdateDoctorStatusRequest` (V-20): `status` ∈ `active|inactive`, `reason` opsional ≤ 255; dokter `archived` hanya boleh menjadi `inactive` (F2-R7).
  - `ArchiveDoctorRequest` (V-20): `reason` opsional ≤ 255; menolak bila `DoctorService::archiveBlockedReason()` bukan `null` (juga untuk Super Admin); `protected function blockedReason(Doctor): ?string` pada request membungkus pemanggilan itu agar jalur penolakan dapat diuji lewat subkelas request (service bersifat `final`).
  - `UploadDoctorPhotoRequest` (V-20): `photo` wajib (aturan `ImageRules::for('photo')`) dan `alt_text` wajib ≤ 255.
  - Flash `success`: "Dokter dinonaktifkan." / "Dokter diaktifkan." / "Dokter dipulihkan sebagai nonaktif." / "Dokter diarsipkan." / "Foto dokter berhasil diperbarui.". Flash `error`: "Dokter sudah diarsipkan." dan pesan penolakan arsip/status (lihat tabel).
  - Partial `admin.doctors._status-actions` (`$doctor`) dan `admin.doctors._status-modals` (id modal `modal-doctor-deactivate`, `modal-doctor-activate`, `modal-doctor-restore`, `modal-doctor-archive`).

**Pesan galat (sumber 12 V-20, BR-05, F2-R7):**

| Kunci | Pesan |
|---|---|
| `status` | "Status dokter wajib dipilih." / "Status dokter tidak valid." / "Dokter yang diarsipkan hanya dapat dipulihkan menjadi nonaktif." |
| `reason` | "Alasan harus berupa teks." / "Alasan maksimal 255 karakter." |
| arsip ditolak | "Dokter masih memiliki {N} pendaftaran aktif pada sesi mendatang. Selesaikan sesi terdampak terlebih dahulu, lalu arsipkan dokter." |
| `photo` | "Foto wajib dipilih." + `ImageRules::messages('photo', 'photo')` |
| `alt_text` | "Teks alternatif foto wajib diisi." / "Teks alternatif foto harus berupa teks." / "Teks alternatif foto maksimal 255 karakter." |

Penolakan status dan arsip juga dipasang sebagai flash `error` (pesan galat pertama) supaya terlihat di halaman daftar/detail tempat modal ditutup.

**Test IDs:** CRD-03 (nonaktifkan, arsipkan, pulihkan, dokter nonaktif/arsip tidak lolos `publiclyVisible()` sebagai pengganti `/dokter` publik, F2-R28; penolakan arsip dengan appointment aktif diuji lewat titik sambung F2-R8), MED-02 (ganti foto: berkas dan baris lama terhapus setelah commit; gagal validasi tidak menyentuh foto lama), MED-08 (berkas fisik hilang: halaman admin tetap 200 dengan `data-fallback`, tanpa `onerror`), SEK-09 (SVG ditolak), AKS (mutasi: Petugas dan Pasien 403, Tamu → login), KNS-02, CRD-13 (tidak ada hapus dokter).

**Catatan teknis (diverifikasi dari source):**
1. Dokter **tidak punya** route `destroy` (BR-03). "Hapus" adalah arsip; pemulihan dari arsip adalah `admin.doctors.status` dengan `status=inactive` (F2-R7), `archived_at` dikosongkan oleh service.
2. Aturan F.4 yang bergantung pada tabel fase 3 belum ditegakkan (F2-R8): menonaktifkan tidak memengaruhi sesi dan arsip tidak memeriksa appointment (`archiveBlockedReason()` = `null`). Pemeriksaan sudah **terpasang** di `ArchiveDoctorRequest` lewat `DoctorService::archiveBlockedReason()`; fase 3 cukup mengganti isi method protected `futureActiveAppointmentsCount()` di service. `DoctorService` bersifat `final`, jadi tes membuktikan jalur penolakan dengan subkelas anonim `ArchiveDoctorRequest` (yang menimpa `blockedReason()`) yang diikat ke container (`FormRequestServiceProvider` mengisi data request ke instance mana pun yang di-*resolve*); uji end-to-end dengan appointment nyata ditambahkan fase 3.
3. Tidak memakai `DoctorPolicy::changeStatus` dengan argumen kelas di Blade (`@can('changeStatus', Doctor::class)` memanggil method dengan string, bukan model, dan gagal tipe). Pemeriksaan per baris memakai model (`@can('changeStatus', $doctor)`); pembungkus modal memakai `@can('create', Doctor::class)` (ability tingkat kelas yang setara `doctors.manage`).
4. `x-confirm-modal` hanya menyediakan textarea `reason` yang `required`. Alasan di sini **opsional** (V-20), jadi `reason-required` tidak dipakai dan textarea opsional ditaruh di slot modal; admin.js mengosongkannya saat modal ditutup.
5. Tombol hanya dirender bila berizin (`@can`), tetapi penegakan tetap di tiga lapis: middleware `permission:doctors.manage`, Policy, dan `authorize()` Form Request.
6. `back()` dipakai untuk kembali ke halaman asal (daftar dengan filternya atau halaman detail); `redirect()->back(fallback: …)` memberi cadangan bila tidak ada referer.
7. `ImageFixtures::jpeg()` menulis berkas sementara per-proses; `Storage::fake('public')` dipasang di `setUp` setiap kelas tes yang menyentuh media.

- [ ] **Step 1: Verifikasi prasyarat (Task 8 dan 11)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git branch --show-current
git status --short
Select-String -Path 'app\Services\Doctors\DoctorService.php' -Pattern 'function (changeStatus|archive|archiveBlockedReason|replacePhoto|futureActiveAppointmentsCount)' | ForEach-Object { $_.Line.Trim() }
Select-String -Path 'resources\views\admin\doctors\index.blade.php','resources\views\admin\doctors\show.blade.php' -Pattern 'Aksi status dokter|Modal konfirmasi status dokter|Ganti foto dokter' | ForEach-Object { "{0}: {1}" -f $_.Filename, $_.Line.Trim() }
& $PHP artisan test
```
Expected: `fase-2-master-data`; bersih; kelima method service tercetak (`futureActiveAppointmentsCount` bertanda `protected`); lima penanda komentar tercetak (`index` memuat dua, `show` memuat tiga); seluruh suite PASS.

**Siklus A — status (nonaktifkan, aktifkan, pulihkan)**

- [ ] **Step 2: Tulis tes yang gagal (tool Write) `tests/Feature/Admin/AdminDoctorStatusTest.php`**

```php
<?php

namespace Tests\Feature\Admin;

use App\Enums\DoctorStatus;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

class AdminDoctorStatusTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithStaff;
    use RefreshDatabase;

    /**
     * @return array<string, array{string}>
     */
    public static function managers(): array
    {
        return ['admin konten' => ['content-admin'], 'super admin' => ['super-admin']];
    }

    #[DataProvider('managers')]
    public function test_deactivating_and_reactivating_a_doctor_crd03(string $role): void
    {
        $doctor = $this->createDoctor(['name' => 'Laras Wulandari']);
        $slug = $doctor->slug;
        $this->actingAs($this->createStaff($role));

        $this->from(route('admin.doctors.show', $doctor))
            ->patch(route('admin.doctors.status', $doctor), ['status' => 'inactive', 'reason' => 'Cuti panjang'])
            ->assertRedirect(route('admin.doctors.show', $doctor))
            ->assertSessionHas('success', 'Dokter dinonaktifkan.');

        $doctor->refresh();
        $this->assertSame(DoctorStatus::Inactive, $doctor->status);
        $this->assertNull($doctor->archived_at);
        $this->assertSame($slug, $doctor->slug);
        $this->assertDatabaseHas('audit_logs', ['action' => 'doctor.status_changed']);
        $this->assertNotContains('Laras Wulandari', Doctor::query()->publiclyVisible()->pluck('name')->all());

        $this->from(route('admin.doctors.index'))
            ->patch(route('admin.doctors.status', $doctor), ['status' => 'active'])
            ->assertRedirect(route('admin.doctors.index'))
            ->assertSessionHas('success', 'Dokter diaktifkan.');

        $this->assertSame(DoctorStatus::Active, $doctor->fresh()->status);
    }

    public function test_an_archived_doctor_is_restored_as_inactive_and_archived_at_is_cleared(): void
    {
        $doctor = Doctor::factory()->archived()->withSpecialties()->create(['name' => 'Lilis Marlina']);
        $this->assertNotNull($doctor->archived_at);

        $this->actingAs($this->createStaff('content-admin'))
            ->patch(route('admin.doctors.status', $doctor), ['status' => 'inactive'])
            ->assertSessionHas('success', 'Dokter dipulihkan sebagai nonaktif.');

        $doctor->refresh();
        $this->assertSame(DoctorStatus::Inactive, $doctor->status);
        $this->assertNull($doctor->archived_at);
        $this->assertNotContains('Lilis Marlina', Doctor::query()->publiclyVisible()->pluck('name')->all());
    }

    public function test_an_archived_doctor_cannot_jump_straight_to_active(): void
    {
        $doctor = Doctor::factory()->archived()->withSpecialties()->create();

        $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.doctors.show', $doctor))
            ->patch(route('admin.doctors.status', $doctor), ['status' => 'active'])
            ->assertRedirect(route('admin.doctors.show', $doctor))
            ->assertSessionHasErrors(['status' => 'Dokter yang diarsipkan hanya dapat dipulihkan menjadi nonaktif.'])
            ->assertSessionHas('error', 'Dokter yang diarsipkan hanya dapat dipulihkan menjadi nonaktif.');

        $fresh = $doctor->fresh();
        $this->assertSame(DoctorStatus::Archived, $fresh->status);
        $this->assertNotNull($fresh->archived_at);
    }

    /**
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function invalidInputs(): array
    {
        return [
            'status kosong' => [['status' => ''], 'status', 'Status dokter wajib dipilih.'],
            'status arsip tidak boleh lewat form status' => [['status' => 'archived'], 'status', 'Status dokter tidak valid.'],
            'status asing' => [['status' => 'cuti'], 'status', 'Status dokter tidak valid.'],
            'alasan bukan teks' => [['status' => 'inactive', 'reason' => ['a']], 'reason', 'Alasan harus berupa teks.'],
            'alasan terlalu panjang' => [['status' => 'inactive', 'reason' => 'x256'], 'reason', 'Alasan maksimal 255 karakter.'],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    #[DataProvider('invalidInputs')]
    public function test_invalid_status_input_is_rejected(array $input, string $field, string $message): void
    {
        $doctor = $this->createDoctor();
        $input = array_map(fn ($value) => $value === 'x256' ? str_repeat('x', 256) : $value, $input);

        $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.doctors.show', $doctor))
            ->patch(route('admin.doctors.status', $doctor), $input)
            ->assertRedirect(route('admin.doctors.show', $doctor))
            ->assertSessionHasErrors([$field => $message]);

        $this->assertSame(DoctorStatus::Active, $doctor->fresh()->status);
    }

    public function test_registration_officer_patient_and_guest_cannot_change_status(): void
    {
        $doctor = $this->createDoctor();
        $url = route('admin.doctors.status', $doctor);

        foreach ([$this->createStaff('registration-officer'), User::factory()->patient()->create()] as $user) {
            $this->actingAs($user)->patch($url, ['status' => 'inactive'])->assertForbidden();
        }

        $this->app['auth']->forgetGuards();
        $this->patch($url, ['status' => 'inactive'])->assertRedirect(route('admin.login'));

        $this->assertSame(DoctorStatus::Active, $doctor->fresh()->status);
    }

    public function test_status_routes_are_guarded_by_the_manage_permission(): void
    {
        $routes = Route::getRoutes();

        foreach (['admin.doctors.status', 'admin.doctors.archive', 'admin.doctors.photo'] as $name) {
            $middleware = $routes->getByName($name)->gatherMiddleware();

            $this->assertContains('permission:doctors.manage', $middleware, $name);
            $this->assertContains('account:staff', $middleware, $name);
        }

        $this->assertSame(url('/admin/dokter/5/status'), route('admin.doctors.status', 5));
        $this->assertSame(url('/admin/dokter/5/arsip'), route('admin.doctors.archive', 5));
        $this->assertSame(url('/admin/dokter/5/foto'), route('admin.doctors.photo', 5));
    }

    public function test_list_shows_status_buttons_per_status_to_managers_only(): void
    {
        $active = $this->createDoctor(['name' => 'Dokter Aktif Satu']);
        $inactive = Doctor::factory()->inactive()->withSpecialties()->create(['name' => 'Dokter Nonaktif Satu']);
        $archived = Doctor::factory()->archived()->withSpecialties()->create(['name' => 'Dokter Arsip Satu']);

        $page = $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.doctors.index', ['status' => 'semua']))
            ->assertOk();

        $page->assertSee('data-confirm-action="'.route('admin.doctors.status', $active).'"', false)
            ->assertSee('data-confirm-action="'.route('admin.doctors.archive', $active).'"', false)
            ->assertSee('data-confirm-action="'.route('admin.doctors.status', $inactive).'"', false)
            ->assertSee('data-confirm-action="'.route('admin.doctors.archive', $inactive).'"', false)
            ->assertSee('data-confirm-action="'.route('admin.doctors.status', $archived).'"', false)
            ->assertDontSee('data-confirm-action="'.route('admin.doctors.archive', $archived).'"', false)
            ->assertSee('Nonaktifkan')
            ->assertSee('Aktifkan')
            ->assertSee('Pulihkan')
            ->assertSee('Arsipkan')
            ->assertSee('id="modal-doctor-deactivate"', false)
            ->assertSee('id="modal-doctor-activate"', false)
            ->assertSee('id="modal-doctor-restore"', false)
            ->assertSee('id="modal-doctor-archive"', false)
            ->assertSee('name="status" value="inactive"', false)
            ->assertSee('name="reason"', false);

        $this->actingAs($this->createStaff('registration-officer'))
            ->get(route('admin.doctors.index', ['status' => 'semua']))
            ->assertOk()
            ->assertDontSee('data-confirm-action', false)
            ->assertDontSee('modal-doctor-deactivate', false)
            ->assertDontSee('Arsipkan');
    }

    public function test_detail_page_has_the_same_status_actions_for_managers(): void
    {
        $doctor = $this->createDoctor(['name' => 'Laras Wulandari']);

        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.doctors.show', $doctor))
            ->assertOk()
            ->assertSee('data-confirm-action="'.route('admin.doctors.status', $doctor).'"', false)
            ->assertSee('data-confirm-action="'.route('admin.doctors.archive', $doctor).'"', false)
            ->assertSee('id="modal-doctor-archive"', false);

        $this->actingAs($this->createStaff('registration-officer'))
            ->get(route('admin.doctors.show', $doctor))
            ->assertOk()
            ->assertDontSee('data-confirm-action', false);
    }
}
```

- [ ] **Step 3: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=AdminDoctorStatusTest
```
Expected: FAIL — `Symfony\Component\Routing\Exception\RouteNotFoundException: Route [admin.doctors.status] not defined.`

- [ ] **Step 4: Tulis implementasi status**

Tool Edit `routes/admin.php` — `old`:
```php
            ->except(['index', 'show', 'destroy']);
```
`new`:
```php
            ->except(['index', 'show', 'destroy']);
        Route::patch('dokter/{doctor}/status', [DoctorController::class, 'status'])->name('doctors.status');
        Route::patch('dokter/{doctor}/arsip', [DoctorController::class, 'archive'])->name('doctors.archive');
        Route::post('dokter/{doctor}/foto', [DoctorController::class, 'photo'])->name('doctors.photo');
```

Tool Write `app/Http/Requests/Admin/UpdateDoctorStatusRequest.php`:

```php
<?php

namespace App\Http\Requests\Admin;

use App\Enums\DoctorStatus;
use App\Models\Doctor;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * V-20 (status): hanya `active` atau `inactive`. Dokter `archived` hanya dapat dipulihkan menjadi
 * `inactive` (F2-R7). Menonaktifkan tidak pernah ditolak karena appointment aktif (BR-05).
 */
class UpdateDoctorStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $doctor = $this->route('doctor');

        return $doctor instanceof Doctor && (bool) $this->user()?->can('changeStatus', $doctor);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([DoctorStatus::Active->value, DoctorStatus::Inactive->value])],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Status dokter wajib dipilih.',
            'status.in' => 'Status dokter tidak valid.',
            'reason.string' => 'Alasan harus berupa teks.',
            'reason.max' => 'Alasan maksimal 255 karakter.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $doctor = $this->route('doctor');

            if (
                $doctor instanceof Doctor
                && $doctor->status === DoctorStatus::Archived
                && ! $validator->errors()->has('status')
                && $this->input('status') !== DoctorStatus::Inactive->value
            ) {
                $validator->errors()->add('status', 'Dokter yang diarsipkan hanya dapat dipulihkan menjadi nonaktif.');
            }
        });
    }

    public function reason(): ?string
    {
        $reason = $this->validated('reason');

        return is_string($reason) && trim($reason) !== '' ? trim($reason) : null;
    }

    public function targetStatus(): DoctorStatus
    {
        return DoctorStatus::from((string) $this->validated('status'));
    }

    /**
     * Modal ditutup setelah kirim, jadi galat juga dipasang sebagai flash `error` agar terlihat di halaman asal.
     */
    protected function failedValidation(ValidatorContract $validator): void
    {
        session()->flash('error', (string) $validator->errors()->first());

        parent::failedValidation($validator);
    }
}
```

Tool Edit `app/Http/Controllers/Admin/DoctorController.php`:

1. Import. `old`: `use App\Http\Requests\Admin\StoreDoctorRequest;` → `new`:
```php
use App\Http\Requests\Admin\StoreDoctorRequest;
use App\Http\Requests\Admin\UpdateDoctorStatusRequest;
```
Urutan abjad dipertahankan Pint; lalu `old`: `use App\Models\Clinic;` → `new`:
```php
use App\Enums\DoctorStatus;
use App\Models\Clinic;
```
(Pint mengurutkan ulang bila perlu.)

2. Method. `old`:
```php
    /**
     * Pilihan form: poliklinik dan spesialisasi aktif, ditambah yang kini dimiliki dokter ini.
```
`new`:
```php
    public function status(UpdateDoctorStatusRequest $request, Doctor $doctor, DoctorService $service): RedirectResponse
    {
        $this->authorize('changeStatus', $doctor);

        $restoring = $doctor->status === DoctorStatus::Archived;
        $target = $request->targetStatus();

        $service->changeStatus($doctor, $target, $request->reason(), Actor::staff($request->user()));

        $message = match (true) {
            $restoring => 'Dokter dipulihkan sebagai nonaktif.',
            $target === DoctorStatus::Active => 'Dokter diaktifkan.',
            default => 'Dokter dinonaktifkan.',
        };

        return back(fallback: route('admin.doctors.show', $doctor))->with('success', $message);
    }

    /**
     * Pilihan form: poliklinik dan spesialisasi aktif, ditambah yang kini dimiliki dokter ini.
```

Tool Write `resources/views/admin/doctors/_status-actions.blade.php`:

```blade
{{-- Tombol aksi status dokter. Variabel: $doctor. Satu modal per aksi dipakai ulang lewat data-confirm-*. --}}
@php($doctorLabel = $doctor->display_name)
@can('changeStatus', $doctor)
    @if ($doctor->status === \App\Enums\DoctorStatus::Active)
        <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modal-doctor-deactivate" data-confirm-action="{{ route('admin.doctors.status', $doctor) }}" data-confirm-message="Nonaktifkan {{ $doctorLabel }}? Dokter tidak akan tampil di situs publik dan tidak dapat dipesan.">Nonaktifkan<span class="visually-hidden"> {{ $doctorLabel }}</span></button>
    @elseif ($doctor->status === \App\Enums\DoctorStatus::Inactive)
        <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#modal-doctor-activate" data-confirm-action="{{ route('admin.doctors.status', $doctor) }}" data-confirm-message="Aktifkan kembali {{ $doctorLabel }}? Dokter akan tampil di situs publik.">Aktifkan<span class="visually-hidden"> {{ $doctorLabel }}</span></button>
    @else
        <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#modal-doctor-restore" data-confirm-action="{{ route('admin.doctors.status', $doctor) }}" data-confirm-message="Pulihkan {{ $doctorLabel }} dari arsip? Dokter dipulihkan sebagai nonaktif dan perlu diaktifkan kembali.">Pulihkan<span class="visually-hidden"> {{ $doctorLabel }}</span></button>
    @endif
@endcan
@can('archive', $doctor)
    @if ($doctor->status !== \App\Enums\DoctorStatus::Archived)
        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modal-doctor-archive" data-confirm-action="{{ route('admin.doctors.archive', $doctor) }}" data-confirm-message="Arsipkan {{ $doctorLabel }}? Dokter tidak tampil di situs publik dan tidak dapat diberi jadwal. Arsip hanya diizinkan bila tidak ada pendaftaran aktif pada sesi mendatang.">Arsipkan<span class="visually-hidden"> {{ $doctorLabel }}</span></button>
    @endif
@endcan
```

Tool Write `resources/views/admin/doctors/_status-modals.blade.php`:

```blade
{{-- Modal konfirmasi aksi status dokter; hanya untuk pemegang doctors.manage. Alasan bersifat opsional (V-20). --}}
@can('create', \App\Models\Doctor::class)
    <x-confirm-modal id="modal-doctor-deactivate" title="Nonaktifkan dokter?" message="Dokter akan dinonaktifkan." confirm-label="Ya, nonaktifkan" variant="danger" method="PATCH">
        <input type="hidden" name="status" value="inactive">
        <div class="mb-3">
            <label for="modal-doctor-deactivate-reason" class="form-label fw-semibold">Alasan (opsional)</label>
            <textarea class="form-control" id="modal-doctor-deactivate-reason" name="reason" rows="2" maxlength="255"></textarea>
        </div>
    </x-confirm-modal>

    <x-confirm-modal id="modal-doctor-activate" title="Aktifkan dokter?" message="Dokter akan diaktifkan." confirm-label="Ya, aktifkan" variant="primary" method="PATCH">
        <input type="hidden" name="status" value="active">
        <div class="mb-3">
            <label for="modal-doctor-activate-reason" class="form-label fw-semibold">Alasan (opsional)</label>
            <textarea class="form-control" id="modal-doctor-activate-reason" name="reason" rows="2" maxlength="255"></textarea>
        </div>
    </x-confirm-modal>

    <x-confirm-modal id="modal-doctor-restore" title="Pulihkan dokter dari arsip?" message="Dokter akan dipulihkan sebagai nonaktif." confirm-label="Ya, pulihkan" variant="primary" method="PATCH">
        <input type="hidden" name="status" value="inactive">
        <div class="mb-3">
            <label for="modal-doctor-restore-reason" class="form-label fw-semibold">Alasan (opsional)</label>
            <textarea class="form-control" id="modal-doctor-restore-reason" name="reason" rows="2" maxlength="255"></textarea>
        </div>
    </x-confirm-modal>

    <x-confirm-modal id="modal-doctor-archive" title="Arsipkan dokter?" message="Dokter akan diarsipkan." confirm-label="Ya, arsipkan" variant="danger" method="PATCH">
        <div class="mb-3">
            <label for="modal-doctor-archive-reason" class="form-label fw-semibold">Alasan (opsional)</label>
            <textarea class="form-control" id="modal-doctor-archive-reason" name="reason" rows="2" maxlength="255"></textarea>
        </div>
    </x-confirm-modal>
@endcan
```

Tool Edit `resources/views/admin/doctors/index.blade.php` (dua penyisipan, setiap `old` tepat satu kali):

1. `old`: `                    {{-- Aksi status dokter --}}` → `new`: `                    @include('admin.doctors._status-actions', ['doctor' => $doctor])`
2. `old`: `    {{-- Modal konfirmasi status dokter --}}` → `new`: `    @include('admin.doctors._status-modals')`

Tool Edit `resources/views/admin/doctors/show.blade.php`:

1. `old`: `        {{-- Aksi status dokter --}}` → `new`: `        @include('admin.doctors._status-actions', ['doctor' => $doctor])`
2. `old`: `    {{-- Modal konfirmasi status dokter --}}` → `new`: `    @include('admin.doctors._status-modals')`

- [ ] **Step 5: Jalankan tes — harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="AdminDoctorStatusTest|AdminDoctorIndexTest|AdminDoctorShowTest"
```
Expected: PASS (`AdminDoctorStatusTest` 13 tes termasuk data provider; tes daftar dan detail Task 11 tetap hijau).

Catatan: tes `test_list_shows_status_buttons_per_status_to_managers_only` mengandalkan route `admin.doctors.archive` dan `admin.doctors.photo` yang sudah didaftarkan di Step 4 (method `archive`/`photo` baru dipanggil di Siklus B dan C; route cukup ada untuk `route()`).

- [ ] **Step 6: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint
& $PHP vendor\bin\pint --test
git add app/Http/Controllers/Admin/DoctorController.php app/Http/Requests/Admin/UpdateDoctorStatusRequest.php routes/admin.php resources/views/admin/doctors tests/Feature/Admin/AdminDoctorStatusTest.php
git commit -m @'
feat(fase-2): ubah status dokter dan pulihkan dari arsip lewat modal konfirmasi

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
'@
```
Expected: `pint` tanpa `--test` dijalankan lebih dulu dan merapikan berkas yang disentuh (mis. urutan impor `routes/admin.php` atau `DoctorController` yang baru ditambah); lalu `pint --test` PASS sebelum commit.

**Siklus B — arsip dengan titik sambung F2-R8**

- [ ] **Step 7: Tulis tes yang gagal (tool Write) `tests/Feature/Admin/AdminDoctorArchiveTest.php`**

```php
<?php

namespace Tests\Feature\Admin;

use App\Enums\DoctorStatus;
use App\Http\Requests\Admin\ArchiveDoctorRequest;
use App\Models\Doctor;
use App\Models\User;
use App\Services\Doctors\DoctorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

class AdminDoctorArchiveTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithStaff;
    use RefreshDatabase;

    /**
     * @return array<string, array{string}>
     */
    public static function managers(): array
    {
        return ['admin konten' => ['content-admin'], 'super admin' => ['super-admin']];
    }

    #[DataProvider('managers')]
    public function test_archiving_sets_status_and_archived_at_and_hides_the_doctor_crd03(string $role): void
    {
        $doctor = $this->createDoctor(['name' => 'Laras Wulandari']);
        $slug = $doctor->slug;

        $this->actingAs($this->createStaff($role))
            ->from(route('admin.doctors.show', $doctor))
            ->patch(route('admin.doctors.archive', $doctor), ['reason' => 'Pindah tugas'])
            ->assertRedirect(route('admin.doctors.show', $doctor))
            ->assertSessionHas('success', 'Dokter diarsipkan.');

        $doctor->refresh();
        $this->assertSame(DoctorStatus::Archived, $doctor->status);
        $this->assertNotNull($doctor->archived_at);
        $this->assertTrue($doctor->archived_at->diffInMinutes(now(), true) < 2);
        $this->assertSame($slug, $doctor->slug);
        $this->assertDatabaseHas('audit_logs', ['action' => 'doctor.archived']);
        $this->assertNotContains('Laras Wulandari', Doctor::query()->publiclyVisible()->pluck('name')->all());
        $this->assertDatabaseHas('doctors', ['id' => $doctor->id]);
    }

    public function test_an_inactive_doctor_can_be_archived_and_then_restored_and_archived_again(): void
    {
        $doctor = Doctor::factory()->inactive()->withSpecialties()->create();
        $this->actingAs($this->createStaff('content-admin'));

        $this->patch(route('admin.doctors.archive', $doctor))->assertSessionHas('success', 'Dokter diarsipkan.');
        $this->assertSame(DoctorStatus::Archived, $doctor->fresh()->status);

        $this->patch(route('admin.doctors.status', $doctor), ['status' => 'inactive'])->assertSessionHas('success');
        $restored = $doctor->fresh();
        $this->assertSame(DoctorStatus::Inactive, $restored->status);
        $this->assertNull($restored->archived_at);

        $this->patch(route('admin.doctors.archive', $doctor))->assertSessionHas('success', 'Dokter diarsipkan.');
        $this->assertNotNull($doctor->fresh()->archived_at);
    }

    public function test_archiving_an_already_archived_doctor_is_refused_without_touching_the_date(): void
    {
        $doctor = Doctor::factory()->archived()->withSpecialties()->create();
        $archivedAt = $doctor->archived_at->toDateTimeString();

        $this->actingAs($this->createStaff('content-admin'))
            ->patch(route('admin.doctors.archive', $doctor))
            ->assertSessionHas('error', 'Dokter sudah diarsipkan.');

        $this->assertSame($archivedAt, $doctor->fresh()->archived_at->toDateTimeString());
    }

    public function test_the_phase_two_service_reports_no_blocking_reason(): void
    {
        $doctor = $this->createDoctor();

        $this->assertNull(app(DoctorService::class)->archiveBlockedReason($doctor));
    }

    #[DataProvider('managers')]
    public function test_archive_is_refused_when_future_active_appointments_exist_even_for_super_admin(string $role): void
    {
        $this->app->bind(ArchiveDoctorRequest::class, fn () => new class extends ArchiveDoctorRequest
        {
            protected function blockedReason(Doctor $doctor): ?string
            {
                return 'Dokter masih memiliki 3 pendaftaran aktif pada sesi mendatang. Selesaikan sesi terdampak terlebih dahulu, lalu arsipkan dokter.';
            }
        });
        $doctor = $this->createDoctor(['name' => 'Laras Wulandari']);
        $message = 'Dokter masih memiliki 3 pendaftaran aktif pada sesi mendatang. Selesaikan sesi terdampak terlebih dahulu, lalu arsipkan dokter.';

        $this->actingAs($this->createStaff($role))
            ->from(route('admin.doctors.show', $doctor))
            ->patch(route('admin.doctors.archive', $doctor), ['reason' => 'Coba arsip'])
            ->assertRedirect(route('admin.doctors.show', $doctor))
            ->assertSessionHasErrors(['status' => $message])
            ->assertSessionHas('error', $message);

        $this->assertSame(DoctorStatus::Active, $doctor->fresh()->status);
        $this->assertNull($doctor->fresh()->archived_at);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'doctor.archived']);
    }

    public function test_reason_must_be_text_of_at_most_255_characters(): void
    {
        $doctor = $this->createDoctor();
        $this->actingAs($this->createStaff('content-admin'))->from(route('admin.doctors.show', $doctor));

        $this->patch(route('admin.doctors.archive', $doctor), ['reason' => str_repeat('x', 256)])
            ->assertSessionHasErrors(['reason' => 'Alasan maksimal 255 karakter.']);

        $this->patch(route('admin.doctors.archive', $doctor), ['reason' => ['a']])
            ->assertSessionHasErrors(['reason' => 'Alasan harus berupa teks.']);

        $this->assertSame(DoctorStatus::Active, $doctor->fresh()->status);
    }

    public function test_registration_officer_patient_and_guest_cannot_archive(): void
    {
        $doctor = $this->createDoctor();
        $url = route('admin.doctors.archive', $doctor);

        foreach ([$this->createStaff('registration-officer'), User::factory()->patient()->create()] as $user) {
            $this->actingAs($user)->patch($url)->assertForbidden();
        }

        $this->app['auth']->forgetGuards();
        $this->patch($url)->assertRedirect(route('admin.login'));

        $this->assertSame(DoctorStatus::Active, $doctor->fresh()->status);
    }

    public function test_there_is_no_way_to_delete_a_doctor(): void
    {
        $doctor = $this->createDoctor();
        $this->actingAs($this->createStaff('super-admin'));

        $this->delete(route('admin.doctors.show', $doctor))->assertStatus(405);
        $this->delete(route('admin.doctors.archive', $doctor))->assertStatus(405);

        $this->assertDatabaseHas('doctors', ['id' => $doctor->id]);
    }
}
```

- [ ] **Step 8: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=AdminDoctorArchiveTest
```
Expected: FAIL — `Class "App\Http\Requests\Admin\ArchiveDoctorRequest" not found` (tes seam) dan `BadMethodCallException: Method App\Http\Controllers\Admin\DoctorController::archive does not exist.` (tes lain).

- [ ] **Step 9: Tulis implementasi arsip**

Tool Write `app/Http/Requests/Admin/ArchiveDoctorRequest.php`:

```php
<?php

namespace App\Http\Requests\Admin;

use App\Models\Doctor;
use App\Services\Doctors\DoctorService;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * V-20 (arsip), kelas terpisah dari UpdateDoctorStatusRequest (00 J.6). Arsip ditolak bila masih ada
 * appointment aktif pada sesi mendatang (00 F.4, BR-05); pemeriksaan di sini berlaku juga untuk Super Admin.
 */
class ArchiveDoctorRequest extends FormRequest
{
    public function authorize(): bool
    {
        $doctor = $this->route('doctor');

        return $doctor instanceof Doctor && (bool) $this->user()?->can('archive', $doctor);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return ['reason' => ['nullable', 'string', 'max:255']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.string' => 'Alasan harus berupa teks.',
            'reason.max' => 'Alasan maksimal 255 karakter.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $doctor = $this->route('doctor');

            if (! $doctor instanceof Doctor) {
                return;
            }

            $blocked = $this->blockedReason($doctor);

            if ($blocked !== null) {
                $validator->errors()->add('status', $blocked);
            }
        });
    }

    public function reason(): ?string
    {
        $reason = $this->validated('reason');

        return is_string($reason) && trim($reason) !== '' ? trim($reason) : null;
    }

    /**
     * Alasan arsip ditolak (Task 8); fase 2 selalu null (F2-R8). Tes menimpa method ini lewat subkelas
     * karena DoctorService bersifat final.
     */
    protected function blockedReason(Doctor $doctor): ?string
    {
        return app(DoctorService::class)->archiveBlockedReason($doctor);
    }

    protected function failedValidation(ValidatorContract $validator): void
    {
        session()->flash('error', (string) $validator->errors()->first());

        parent::failedValidation($validator);
    }
}
```

Tool Edit `app/Http/Controllers/Admin/DoctorController.php`:

1. Import: `old`: `use App\Http\Requests\Admin\DoctorIndexRequest;` → `new`:
```php
use App\Http\Requests\Admin\ArchiveDoctorRequest;
use App\Http\Requests\Admin\DoctorIndexRequest;
```
2. Method, tepat setelah `status()`. `old`:
```php
        return back(fallback: route('admin.doctors.show', $doctor))->with('success', $message);
    }
```
`new`:
```php
        return back(fallback: route('admin.doctors.show', $doctor))->with('success', $message);
    }

    public function archive(ArchiveDoctorRequest $request, Doctor $doctor, DoctorService $service): RedirectResponse
    {
        $this->authorize('archive', $doctor);

        if ($doctor->status === DoctorStatus::Archived) {
            return back(fallback: route('admin.doctors.show', $doctor))->with('error', 'Dokter sudah diarsipkan.');
        }

        $service->archive($doctor, $request->reason(), Actor::staff($request->user()));

        return back(fallback: route('admin.doctors.show', $doctor))->with('success', 'Dokter diarsipkan.');
    }
```

- [ ] **Step 10: Jalankan tes — harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="AdminDoctorArchiveTest|AdminDoctorStatusTest"
```
Expected: PASS (`AdminDoctorArchiveTest` 10 tes termasuk data provider; semua hijau).

- [ ] **Step 11: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint
& $PHP vendor\bin\pint --test
git add app/Http/Controllers/Admin/DoctorController.php app/Http/Requests/Admin/ArchiveDoctorRequest.php tests/Feature/Admin/AdminDoctorArchiveTest.php
git commit -m @'
feat(fase-2): arsip dokter dengan pemeriksaan appointment aktif lewat titik sambung F2-R8

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
'@
```
Expected: `pint` tanpa `--test` dijalankan lebih dulu dan merapikan berkas yang disentuh (mis. urutan impor `routes/admin.php` atau `DoctorController` yang baru ditambah); lalu `pint --test` PASS sebelum commit.

**Siklus C — ganti foto (`photo`)**

- [ ] **Step 12: Tulis tes yang gagal (tool Write) `tests/Feature/Admin/AdminDoctorPhotoTest.php`**

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\Doctor;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Support\ImageFixtures;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

class AdminDoctorPhotoTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithStaff;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function upload(Doctor $doctor, string $alt = 'Foto dokter', ?int $size = 800): TestResponse
    {
        return $this->from(route('admin.doctors.show', $doctor))->post(route('admin.doctors.photo', $doctor), [
            'photo' => ImageFixtures::jpeg($size, $size, 'foto.jpg'),
            'alt_text' => $alt,
        ]);
    }

    public function test_first_photo_is_stored_for_the_doctor_with_alt_text_med01(): void
    {
        $doctor = $this->createDoctor(['name' => 'Laras Wulandari']);

        $this->actingAs($this->createStaff('content-admin'));
        $this->upload($doctor, 'Foto dr. Laras')
            ->assertRedirect(route('admin.doctors.show', $doctor))
            ->assertSessionHas('success', 'Foto dokter berhasil diperbarui.');

        $media = Media::query()->where('collection', 'photo')->sole();

        $this->assertSame(Doctor::class, $media->model_type);
        $this->assertSame($doctor->id, $media->model_id);
        $this->assertSame('Foto dr. Laras', $media->alt_text);
        $this->assertNotNull($media->thumb_path);
        Storage::disk('public')->assertExists($media->directory.'/'.$media->file_name);
        Storage::disk('public')->assertExists($media->thumb_path);
    }

    public function test_replacing_the_photo_removes_the_old_file_and_row_after_commit_med02(): void
    {
        $doctor = $this->createDoctor();
        $this->actingAs($this->createStaff('super-admin'));

        $this->upload($doctor, 'Foto lama');
        $old = Media::query()->where('collection', 'photo')->sole();
        $oldFile = $old->directory.'/'.$old->file_name;

        $this->upload($doctor, 'Foto baru', 700)->assertSessionHas('success');

        $new = Media::query()->where('collection', 'photo')->sole();
        $this->assertNotSame($old->id, $new->id);
        $this->assertSame('Foto baru', $new->alt_text);
        Storage::disk('public')->assertMissing($oldFile);
        Storage::disk('public')->assertMissing($old->thumb_path);
        Storage::disk('public')->assertExists($new->directory.'/'.$new->file_name);
        $this->assertDatabaseMissing('media', ['id' => $old->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'media.replaced']);
    }

    public function test_a_rejected_upload_leaves_the_existing_photo_untouched(): void
    {
        $doctor = $this->createDoctor();
        $this->actingAs($this->createStaff('content-admin'));
        $this->upload($doctor, 'Foto lama');
        $old = Media::query()->where('collection', 'photo')->sole();

        $this->upload($doctor, '')
            ->assertSessionHasErrors(['alt_text' => 'Teks alternatif foto wajib diisi.']);

        $this->from(route('admin.doctors.show', $doctor))
            ->post(route('admin.doctors.photo', $doctor), ['alt_text' => 'Tanpa berkas'])
            ->assertSessionHasErrors(['photo' => 'Foto wajib dipilih.']);

        $this->assertSame($old->id, Media::query()->where('collection', 'photo')->sole()->id);
        Storage::disk('public')->assertExists($old->directory.'/'.$old->file_name);
    }

    public function test_invalid_images_are_rejected_with_indonesian_messages(): void
    {
        $doctor = $this->createDoctor();
        $this->actingAs($this->createStaff('content-admin'))->from(route('admin.doctors.show', $doctor));

        $this->post(route('admin.doctors.photo', $doctor), ['photo' => ImageFixtures::jpeg(200, 200), 'alt_text' => 'x'])
            ->assertSessionHasErrors(['photo' => 'Dimensi gambar minimal 300 x 300 piksel dan maksimal 6000 x 6000 piksel.']);

        $this->post(route('admin.doctors.photo', $doctor), ['photo' => ImageFixtures::oversizedJpeg(2100, 800, 800), 'alt_text' => 'x'])
            ->assertSessionHasErrors(['photo' => 'Ukuran gambar maksimal 2 MB.']);

        $this->post(route('admin.doctors.photo', $doctor), ['photo' => ImageFixtures::jpeg(800, 800), 'alt_text' => str_repeat('a', 256)])
            ->assertSessionHasErrors(['alt_text' => 'Teks alternatif foto maksimal 255 karakter.']);

        $this->post(route('admin.doctors.photo', $doctor), ['photo' => ImageFixtures::svgWithScript(), 'alt_text' => 'x'])
            ->assertSessionHasErrors('photo');

        $this->post(route('admin.doctors.photo', $doctor), ['photo' => ImageFixtures::textNamedJpg(), 'alt_text' => 'x'])
            ->assertSessionHasErrors('photo');

        $this->assertSame(0, Media::query()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_registration_officer_patient_and_guest_cannot_upload(): void
    {
        $doctor = $this->createDoctor();
        $url = route('admin.doctors.photo', $doctor);
        $data = ['photo' => ImageFixtures::jpeg(800, 800), 'alt_text' => 'x'];

        foreach ([$this->createStaff('registration-officer'), User::factory()->patient()->create()] as $user) {
            $this->actingAs($user)->post($url, $data)->assertForbidden();
        }

        $this->app['auth']->forgetGuards();
        $this->post($url, $data)->assertRedirect(route('admin.login'));

        $this->assertSame(0, Media::query()->count());
    }

    public function test_detail_page_shows_the_photo_form_only_to_managers(): void
    {
        $doctor = $this->createDoctor();

        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.doctors.show', $doctor))
            ->assertOk()
            ->assertSee('Ganti foto')
            ->assertSee('action="'.route('admin.doctors.photo', $doctor).'"', false)
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="photo"', false)
            ->assertSee('name="alt_text"', false);

        $this->actingAs($this->createStaff('registration-officer'))
            ->get(route('admin.doctors.show', $doctor))
            ->assertOk()
            ->assertDontSee('action="'.route('admin.doctors.photo', $doctor).'"', false);
    }

    public function test_pages_stay_ok_with_a_fallback_when_the_photo_file_is_missing_med08(): void
    {
        $doctor = $this->createDoctor(['name' => 'Laras Wulandari']);
        $this->actingAs($this->createStaff('content-admin'));
        $this->upload($doctor, 'Foto dr. Laras');
        $media = Media::query()->where('collection', 'photo')->sole();
        Storage::disk('public')->delete([$media->directory.'/'.$media->file_name, $media->thumb_path]);

        foreach ([route('admin.doctors.index'), route('admin.doctors.show', $doctor), route('admin.doctors.edit', $doctor)] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('data-fallback=', false)
                ->assertSee('images/placeholders/doctor.svg', false)
                ->assertDontSee('onerror', false);
        }
    }
}
```

- [ ] **Step 13: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=AdminDoctorPhotoTest
```
Expected: FAIL — `BadMethodCallException: Method App\Http\Controllers\Admin\DoctorController::photo does not exist.` (dan kartu "Ganti foto" belum ada di halaman detail).

- [ ] **Step 14: Tulis implementasi foto**

Tool Write `app/Http/Requests/Admin/UploadDoctorPhotoRequest.php`:

```php
<?php

namespace App\Http\Requests\Admin;

use App\Models\Doctor;
use App\Rules\ImageRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use LogicException;

/**
 * V-20 (ganti foto): `photo` wajib (aturan koleksi `photo`) dan `alt_text` wajib (BR-132).
 */
class UploadDoctorPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $doctor = $this->route('doctor');

        return $doctor instanceof Doctor && (bool) $this->user()?->can('update', $doctor);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'photo' => ['required', ...ImageRules::for('photo')],
            'alt_text' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(ImageRules::messages('photo', 'photo'), [
            'photo.required' => 'Foto wajib dipilih.',
            'alt_text.required' => 'Teks alternatif foto wajib diisi.',
            'alt_text.string' => 'Teks alternatif foto harus berupa teks.',
            'alt_text.max' => 'Teks alternatif foto maksimal 255 karakter.',
        ]);
    }

    public function photoFile(): UploadedFile
    {
        $file = $this->file('photo');

        if (! $file instanceof UploadedFile) {
            throw new LogicException('photoFile() hanya dipanggil setelah validasi lolos.');
        }

        return $file;
    }

    public function altText(): string
    {
        return trim((string) $this->validated('alt_text'));
    }
}
```

Tool Edit `app/Http/Controllers/Admin/DoctorController.php`:

1. Import: `old`: `use App\Http\Requests\Admin\UpdateDoctorRequest;` → `new`:
```php
use App\Http\Requests\Admin\UpdateDoctorRequest;
use App\Http\Requests\Admin\UploadDoctorPhotoRequest;
```
2. Method, setelah `archive()`. `old`:
```php
        return back(fallback: route('admin.doctors.show', $doctor))->with('success', 'Dokter diarsipkan.');
    }
```
`new`:
```php
        return back(fallback: route('admin.doctors.show', $doctor))->with('success', 'Dokter diarsipkan.');
    }

    public function photo(UploadDoctorPhotoRequest $request, Doctor $doctor, DoctorService $service): RedirectResponse
    {
        $this->authorize('update', $doctor);

        $service->replacePhoto($doctor, $request->photoFile(), $request->altText(), Actor::staff($request->user()));

        return redirect()->route('admin.doctors.show', $doctor)->with('success', 'Foto dokter berhasil diperbarui.');
    }
```

Tool Edit `resources/views/admin/doctors/show.blade.php` — `old`: `            {{-- Ganti foto dokter --}}` → `new`:
```blade
            @can('update', $doctor)
                <div class="card mt-3">
                    <div class="card-header fw-semibold">Ganti foto</div>
                    <form method="POST" action="{{ route('admin.doctors.photo', $doctor) }}" enctype="multipart/form-data" class="card-body">
                        @csrf
                        <x-form.file name="photo" label="Foto baru" alt-name="alt_text" :alt-value="old('alt_text', $photo?->alt_text)" :required="true" help="Foto lama dihapus setelah foto baru berhasil disimpan." />
                        <x-loading-button icon="bi-upload" loading-text="Mengunggah foto…">Simpan Foto</x-loading-button>
                    </form>
                </div>
            @endcan
```

- [ ] **Step 15: Jalankan tes — harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="AdminDoctor"
& $PHP artisan test --filter=AdminRouteMiddlewareTest
& $PHP artisan route:list --path=admin/dokter
```
Expected: PASS untuk seluruh kelas `AdminDoctor*` dan `AdminRouteMiddlewareTest` (KNS-02: ketiga route baru ber-`permission:doctors.manage`); `route:list` memuat sembilan route dokter (`index`, `show`, `create`, `store`, `edit`, `update`, `status`, `archive`, `photo`) dan tidak ada `destroy`.

- [ ] **Step 16: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint
& $PHP vendor\bin\pint --test
git add app/Http/Controllers/Admin/DoctorController.php app/Http/Requests/Admin/UploadDoctorPhotoRequest.php resources/views/admin/doctors/show.blade.php tests/Feature/Admin/AdminDoctorPhotoTest.php
git commit -m @'
feat(fase-2): ganti foto dokter dengan teks alternatif wajib dan penghapusan foto lama setelah commit

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
'@
```
Expected: `pint` tanpa `--test` dijalankan lebih dulu dan merapikan berkas yang disentuh (mis. urutan impor `routes/admin.php` atau `DoctorController` yang baru ditambah); lalu `pint --test` PASS sebelum commit.

- [ ] **Step 17: Seluruh suite, Pint, build**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test
& $PHP vendor\bin\pint --test
npm run build
```
Expected: seluruh suite PASS; Pint PASS; build sukses.

**Catatan konflik (Task 12):**
- Pesan penolakan arsip dipasang pada kunci galat `status` (sama dengan `DoctorService::archive()` Task 8; bukan field form) karena aksinya berupa modal tanpa field; flash `error` mengulang pesan yang sama.

---

**Verifikasi penulisan:** Semua blok PHP lolos `php -l` 8.2 (blok berkas utuh dilint apa adanya; potongan `old`/`new` untuk tool Edit dilint setelah dibungkus kelas/berkas sementara). Blok Blade tidak dilint. Seluruh perintah PowerShell diawali Prelude P.


---

### Task 13: Halaman publik Layanan (#4 `clinics.index`, #5 `clinics.show`)

**Files:**
- Create: `app/Http/Controllers/Public/ClinicController.php`, `resources/views/public/clinics/index.blade.php`, `resources/views/public/clinics/show.blade.php`, `resources/views/public/partials/_clinic-card.blade.php`, `resources/views/components/doctor-card.blade.php`
- Modify: `routes/web.php`, `resources/js/app.js` (modal galeri), `resources/scss/_public.scss`
- Test: `tests/Feature/Public/PublicClinicPagesTest.php`, `tests/Feature/Public/PublicClinicDetailTest.php`, `tests/Feature/View/DoctorCardComponentTest.php`, `tests/Feature/View/RawOutputScanTest.php`

**Interfaces:**
- Consumes:
  - Task 1: `Clinic::active()`, `Clinic::ordered()`, accessor `Clinic::seoTitle` (dibaca `$clinic->seo_title`), relasi `Clinic::doctors()`; `Doctor::publiclyVisible()`, `Doctor::ordered()`, accessor `Doctor::displayName` (`display_name`) dan `Doctor::primarySpecialty` (`primary_specialty`, wajib `specialties` termuat); factory + `Tests\Support\InteractsWithMasterData` (`createClinic`, `createSpecialty`, `createDoctor`).
  - Task 2: `HasMedia::firstMedia()`/`mediaIn()` (memakai relasi `media` yang sudah dimuat), `SanitizesRichText` (sanitasi saat `saving`), `HasUniqueSlug`.
  - Fase 1: `x-layouts.public` (prop `title`, `metaDescription`; slot `breadcrumb`; `@stack('head')`), `x-breadcrumb`, `x-page-header`, `x-empty-state`, `x-image` (`type`, `media`, `alt`, `variant`, `ratio`, `lazy`, `decorative`; atribut `data-fallback`), `NavigationMenu` (menu "Layanan" muncul otomatis lewat `Route::has('clinics.index')`), `ClinicType::label()/icon()`, `SettingsService::string()`.
- Produces:
  - Route `GET /layanan` → `clinics.index` dan `GET /layanan/{clinic:slug}` → `clinics.show` (grup `web`, tanpa middleware izin).
  - `App\Http\Controllers\Public\ClinicController` (`index(): View`, `show(Clinic $clinic): View`). `show` memanggil `abort_unless($clinic->is_active, 404)` tanpa Policy sehingga 404 juga berlaku bagi staf yang login (A-42).
  - View: `public.clinics.index` (variabel `$groups`: larik `['type' => ClinicType, 'clinics' => Collection]` hanya untuk jenis yang punya layanan aktif; urutan enum), `public.clinics.show` (`$clinic`, `$doctors`, `$metaDescription`), partial `public.partials._clinic-card` (`$clinic` dengan `media` termuat).
  - Komponen anonim `x-doctor-card` (F2-R9): props `doctor` (wajib; relasi `specialties` dan `media` sudah dimuat), `headingLevel` (default 3, dibatasi 2–6), `showClinic` (default `false`; bila `true` relasi `clinic` wajib dimuat). Menampilkan foto (`x-image type="doctor"`), nama tampilan, lencana spesialisasi utama; tautan ke `doctors.show` **hanya bila** `Route::has('doctors.show')`. Tidak ada tombol daftar dan `availability-badge` (fase 3/6).
  - Hook JS `[data-gallery-modal]` di `resources/js/app.js` (tanpa atribut event inline; tidak menambah listener `error`).
  - `{!! !!}` hanya satu: `{!! $clinic->description !!}` di `public/clinics/show.blade.php`.
  - Prop konteks: tombol **Daftar Kunjungan** (`patient.appointments.create?poli={slug}`) dan **Jadwal Dokter** (`schedules.index?poli={slug}`) hanya tampil bila `is_bookable` **dan** route-nya ada (F1-R2).

**Teks (sumber):**

| Teks | Sumber |
|---|---|
| Judul "Layanan Kami", subjudul "Poliklinik dan layanan penunjang UNPAM HOSPITAL." | F2-R10 (05 hal. 4 diganti teks statis sampai fase 4a) |
| Breadcrumb "Beranda › Layanan" dan "Beranda › Layanan › {nama}" | 05 §8.1 |
| Label "Dapat didaftar online" (kartu, panel) | 05 hal. 4 |
| Varian penunjang: "Layanan ini tidak dapat didaftar online. Hubungi kami di {telepon}." (cadangan telepon `(021) 0000-0000`) | 07 W-06 varian |
| Kosong: "Belum ada dokter terjadwal" (poliklinik bisa didaftar tanpa dokter publik) | 07 W-06 |
| Kosong #4: "Belum ada layanan" | 05 hal. 4 (`x-empty-state`) |
| Title `{seoTitle} — {site.name}`; deskripsi `meta_description` ?: `short_description` dipotong 160; cadangan `seo.default_meta_description` (layout); `og:image` = cover bila ada | 05 §7 |
| Heading: h1 nama, h2 "Galeri", h2 "Dokter di poliklinik ini", h2 "Informasi layanan" | 07 W-06 A11y |

**Test IDs:** `CRD-01`/`CRD-02`/`CRD-03` (bagian publik; F2-R28: scope `publiclyVisible` + tampilan #5), `MED-08` (placeholder, `data-fallback`, tanpa `onerror`), `AKS` publik baris 4/5 (matriks di Task 16), `SEK-15` (`{!!` hanya satu), `SEK-07` (tanpa atribut event inline), `EMP-01`, `A11` (heading, breadcrumb).

**Catatan teknis (diverifikasi dari source):**
- `route('clinics.show', $clinic)` menghasilkan slug karena route memakai `{clinic:slug}` (`RouteUrlGenerator::formatParameters` memakai `bindingFieldFor`), meskipun model tidak menimpa `getRouteKeyName()` (F2-R4).
- Mode ketat: `index` memuat `media` hanya koleksi `cover` (`with(['media' => fn ($q) => $q->where('collection', 'cover')])`); `show` memuat `media` penuh (cover + galeri), dokter dimuat dengan `specialties` dan `media` koleksi `photo`. `firstMedia()`/`mediaIn()` membaca relasi yang termuat sehingga tidak ada query tambahan.
- `$site` (SettingsService) hanya dibagikan composer ke `components.layouts.*`; view halaman memakai `@inject('site', 'App\Services\Settings\SettingsService')` seperti `public/home.blade.php`.
- `@push('head')` di dalam slot komponen bekerja karena slot dirender sebelum view layout dievaluasi.
- `x-image` hanya mengenal rasio `1x1`, `4x3`, `16x9`, `21x9`, `3x1`; cover dipotong `object-fit-cover` pada `16x9` (detail) dan `4x3` (kartu).
- Tes yang mendaftarkan route palsu (`doctors.show`, `patient.appointments.create`, `schedules.index`) memakai `Route::has()` agar tetap benar di fase 3/6 (route nyata sudah ada → tidak didaftarkan ulang; tes "tanpa route" di-*skip*).
- Panel samping berada di akhir DOM (urutan fokus 07 W-06); pada < 992 px ia tampil di bawah konten, bukan di bawah judul (penyimpangan kecil demi urutan fokus).
- Bila Task 7 memakai `{!!` di `form.rich-text`, `RawOutputScanTest` akan gagal: komponen itu harus memakai `{{ }}` (Global Constraints).

- [ ] **Step 1: Verifikasi prasyarat**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git branch --show-current
git status --short
Test-Path app\Models\Clinic.php, app\Models\Doctor.php, app\Models\Concerns\HasMedia.php, tests\Support\InteractsWithMasterData.php
& $PHP artisan test
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
if (-not (Test-Path "$env:TEMP\unpam-edit-text.ps1")) { [IO.File]::WriteAllText("$env:TEMP\unpam-edit-text.ps1", $helper, (New-Object System.Text.UTF8Encoding $false)) }
Test-Path "$env:TEMP\unpam-edit-text.ps1"
```
Expected: `fase-2-master-data`; `git status` bersih; `True` ×4 (model, trait, pembantu tes ada dari Task 1–2); seluruh suite PASS; `True` (pembantu `Edit-Text` siap; sama dengan fase 1 Task 18 Step 1).

**Siklus A — daftar layanan (#4)**

- [ ] **Step 2: Tulis tes yang gagal (tool Write) `tests/Feature/Public/PublicClinicPagesTest.php`**

```php
<?php

namespace Tests\Feature\Public;

use App\Enums\ClinicType;
use App\Models\Clinic;
use App\Services\Media\MediaService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ImageFixtures;
use Tests\Support\InteractsWithMasterData;
use Tests\TestCase;

/**
 * Halaman #4 `clinics.index` (05 hal. 4): hanya layanan aktif, dikelompokkan per ClinicType.
 */
class PublicClinicPagesTest extends TestCase
{
    use InteractsWithMasterData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_strict_mode_is_active_in_the_test_suite(): void
    {
        $this->assertTrue(Model::preventsLazyLoading());
    }

    public function test_index_lists_active_clinics_grouped_by_type_in_sort_order(): void
    {
        $this->createClinic(['name' => 'Poliklinik Anak', 'type' => ClinicType::Poliklinik, 'sort_order' => 2]);
        $this->createClinic(['name' => 'Poliklinik Mata', 'type' => ClinicType::Poliklinik, 'sort_order' => 1]);
        $this->createClinic(['name' => 'Laboratorium', 'type' => ClinicType::Penunjang, 'is_bookable' => false, 'sort_order' => 1]);

        $this->get(route('clinics.index'))
            ->assertOk()
            ->assertSeeInOrder([
                'id="layanan-poliklinik"',
                'Poliklinik Mata',
                'Poliklinik Anak',
                'id="layanan-penunjang"',
                'Laboratorium',
            ], false);
    }

    public function test_index_hides_inactive_clinics_and_omits_empty_groups(): void
    {
        $this->createClinic(['name' => 'Poliklinik Aktif', 'type' => ClinicType::Poliklinik]);
        $this->createClinic(['name' => 'Radiologi Nonaktif', 'type' => ClinicType::Penunjang, 'is_bookable' => false, 'is_active' => false]);

        $this->get(route('clinics.index'))
            ->assertOk()
            ->assertSee('Poliklinik Aktif')
            ->assertDontSee('Radiologi Nonaktif')
            ->assertDontSee('id="layanan-penunjang"', false);
    }

    public function test_index_shows_the_empty_state_when_no_clinic_is_active(): void
    {
        $this->createClinic(['is_active' => false]);

        $this->get(route('clinics.index'))
            ->assertOk()
            ->assertSee('Belum ada layanan')
            ->assertDontSee('id="layanan-poliklinik"', false);
    }

    public function test_bookable_label_appears_only_on_bookable_cards(): void
    {
        $this->createClinic(['name' => 'Poliklinik Gigi', 'is_bookable' => true]);
        $this->createClinic(['name' => 'Poliklinik Umum', 'is_bookable' => true]);
        $this->createClinic(['name' => 'Farmasi', 'type' => ClinicType::Penunjang, 'is_bookable' => false]);

        $html = $this->get(route('clinics.index'))->assertOk()->getContent();

        $this->assertSame(2, substr_count($html, 'Dapat didaftar online'));
    }

    public function test_cards_link_to_the_detail_page_by_slug(): void
    {
        $clinic = $this->createClinic(['name' => 'Poliklinik Saraf']);

        $this->get(route('clinics.index'))
            ->assertOk()
            ->assertSee('href="'.url('/layanan/'.$clinic->slug).'"', false);
    }

    public function test_index_has_one_h1_static_header_and_breadcrumb(): void
    {
        $this->createClinic();

        $response = $this->get(route('clinics.index'))->assertOk();

        $response->assertSee('<title>Layanan Kami — '.config('app.name').'</title>', false)
            ->assertSee('Poliklinik dan layanan penunjang UNPAM HOSPITAL.')
            ->assertSee('aria-label="Breadcrumb"', false)
            ->assertSee('<li class="breadcrumb-item active" aria-current="page">Layanan</li>', false);
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public function test_clinic_without_cover_uses_the_local_placeholder(): void
    {
        $this->createClinic(['name' => 'Poliklinik Gigi']);

        $this->get(route('clinics.index'))
            ->assertOk()
            ->assertSee('images/placeholders/clinic.svg', false)
            ->assertDontSee('onerror', false);
    }

    public function test_index_renders_many_rows_with_covers_in_strict_mode(): void
    {
        $media = $this->app->make(MediaService::class);

        foreach (range(1, 8) as $number) {
            $clinic = $this->createClinic([
                'name' => "Poliklinik Uji {$number}",
                'type' => $number <= 6 ? ClinicType::Poliklinik : ClinicType::Penunjang,
                'is_bookable' => $number <= 6,
                'sort_order' => $number,
            ]);
            $media->store(ImageFixtures::jpeg(1200, 800, 'cover.jpg'), 'cover', $clinic, ['alt_text' => "Foto contoh Poliklinik Uji {$number}"]);
        }

        $html = $this->get(route('clinics.index'))->assertOk()->getContent();

        $this->assertSame(8, substr_count($html, 'service-card'));
        $this->assertStringContainsString('/thumbs/', $html);
        $this->assertStringContainsString('alt="Foto contoh Poliklinik Uji 3"', $html);
        $this->assertSame(8, Clinic::query()->count());
    }
}
```

- [ ] **Step 3: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=PublicClinicPagesTest
```
Expected: FAIL — `Symfony\Component\Routing\Exception\RouteNotFoundException: Route [clinics.index] not defined.` pada delapan tes yang memanggil `route('clinics.index')`; `test_strict_mode_is_active_in_the_test_suite` PASS.

- [ ] **Step 4: Implementasi #4 (tool Write; untuk `routes/web.php` Read dulu lalu Write)**

`routes/web.php`:

```php
<?php

use App\Http\Controllers\Public\ClinicController;
use Illuminate\Support\Facades\Route;

// Beranda sementara (F1-R3); HomeController dibangun di fase 4a.
Route::view('/', 'public.home')->name('home');

// Layanan (#4, #5). Slug tidak ada atau layanan nonaktif menjawab 404, juga bagi staf yang login (A-42).
Route::get('/layanan', [ClinicController::class, 'index'])->name('clinics.index');
Route::get('/layanan/{clinic:slug}', [ClinicController::class, 'show'])->name('clinics.show');
```

`app/Http/Controllers/Public/ClinicController.php` (`show` dilengkapi di Siklus B; di sini sudah final agar berkas tidak ditulis dua kali, view `show` dibuat di Step 8):

```php
<?php

namespace App\Http\Controllers\Public;

use App\Enums\ClinicType;
use App\Http\Controllers\Controller;
use App\Models\Clinic;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ClinicController extends Controller
{
    public function index(): View
    {
        $clinics = Clinic::query()
            ->active()
            ->with(['media' => fn ($query) => $query->where('collection', 'cover')])
            ->ordered()
            ->get();

        $groups = [];

        foreach (ClinicType::cases() as $type) {
            $items = $clinics->filter(fn (Clinic $clinic): bool => $clinic->type === $type)->values();

            if ($items->isNotEmpty()) {
                $groups[] = ['type' => $type, 'clinics' => $items];
            }
        }

        return view('public.clinics.index', ['groups' => $groups]);
    }

    public function show(Clinic $clinic): View
    {
        // Tanpa Policy: nonaktif = 404 untuk semua orang, termasuk staf (A-42).
        abort_unless($clinic->is_active, 404);

        $clinic->load('media');

        $doctors = $clinic->doctors()
            ->publiclyVisible()
            ->with(['specialties', 'media' => fn ($query) => $query->where('collection', 'photo')])
            ->ordered()
            ->get();

        return view('public.clinics.show', [
            'clinic' => $clinic,
            'doctors' => $doctors,
            'metaDescription' => Str::limit((string) ($clinic->meta_description ?: $clinic->short_description), 160, ''),
        ]);
    }
}
```

`resources/views/public/clinics/index.blade.php`:

```blade
<x-layouts.public title="Layanan Kami">
    <x-slot:breadcrumb>
        <x-breadcrumb :items="[['label' => 'Layanan']]" />
    </x-slot:breadcrumb>

    <x-page-header title="Layanan Kami" subtitle="Poliklinik dan layanan penunjang UNPAM HOSPITAL." />

    @forelse ($groups as $group)
        <section class="mb-5" aria-labelledby="layanan-{{ $group['type']->value }}">
            <h2 class="h3 mb-3" id="layanan-{{ $group['type']->value }}">
                <i class="bi {{ $group['type']->icon() }} me-2" aria-hidden="true"></i>{{ $group['type']->label() }}
            </h2>
            <div class="row g-4">
                @foreach ($group['clinics'] as $clinic)
                    <div class="col-12 col-sm-6 col-lg-4">
                        @include('public.partials._clinic-card', ['clinic' => $clinic])
                    </div>
                @endforeach
            </div>
        </section>
    @empty
        <x-empty-state title="Belum ada layanan" message="Daftar layanan akan tampil di sini setelah data tersedia." icon="bi-hospital" />
    @endforelse
</x-layouts.public>
```

`resources/views/public/partials/_clinic-card.blade.php` (satu tautan per kartu lewat `stretched-link`):

```blade
@php
    $cover = $clinic->firstMedia('cover');
@endphp
<article class="card h-100 service-card">
    <x-image type="clinic" :media="$cover" :alt="$cover?->alt_text ?: 'Foto '.$clinic->name" variant="thumb" ratio="4x3" />
    <div class="card-body d-flex flex-column">
        <h3 class="card-title h5 mb-1">
            <a class="stretched-link" href="{{ route('clinics.show', $clinic) }}">{{ $clinic->name }}</a>
        </h3>
        <p class="small text-body-secondary mb-2"><i class="bi bi-geo-alt" aria-hidden="true"></i> {{ $clinic->location }}</p>
        <p class="card-text mb-3">{{ $clinic->short_description }}</p>
        @if ($clinic->is_bookable)
            <p class="mt-auto mb-0"><span class="badge text-bg-success"><i class="bi bi-calendar-check" aria-hidden="true"></i> Dapat didaftar online</span></p>
        @endif
    </div>
</article>
```

Tambahkan gaya (Edit-Text, berkas `_public.scss` ditambah di akhir; blok juga dipakai Siklus B):

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
$scss = @'

// ---------------------------------------------------------------- Layanan (#4, #5)
.service-card {
  .card-title a {
    color: inherit;
    text-decoration: none;

    &:hover,
    &:focus-visible {
      color: $primary;
      text-decoration: underline;
    }
  }
}

.doctor-card-photo {
  width: 5rem;
}

.clinic-side-panel {
  @include media-breakpoint-up(lg) {
    position: sticky;
    top: 5.5rem;
  }
}

// Galeri: baris geser di layar kecil, grid 3 kolom mulai sm.
.gallery-grid {
  display: grid;
  grid-auto-flow: column;
  grid-auto-columns: 70%;
  gap: .75rem;
  overflow-x: auto;
  padding-bottom: .5rem;

  @include media-breakpoint-up(sm) {
    grid-auto-flow: row;
    grid-auto-columns: auto;
    grid-template-columns: repeat(3, 1fr);
    overflow-x: visible;
  }
}

.gallery-thumb {
  display: block;
  width: 100%;
  padding: 0;
  border: 0;
  background: none;
  border-radius: $border-radius;
  overflow: hidden;

  &:focus-visible {
    outline: 3px solid $primary;
    outline-offset: 2px;
  }
}

// Rich text tersanitasi (clinics.description).
.rich-text {
  overflow-wrap: anywhere;

  img {
    max-width: 100%;
    height: auto;
  }

  table {
    width: 100%;
  }
}
'@
$path = "$APP\resources\scss\_public.scss"
$enc = New-Object System.Text.UTF8Encoding $false
$raw = [IO.File]::ReadAllText($path, $enc)
if ($raw.Contains('Layanan (#4, #5)')) { throw "Blok gaya Layanan sudah ada." }
if ($raw.Contains("`r`n")) { $scss = $scss.Replace("`r`n", "`n").Replace("`n", "`r`n") }
[IO.File]::WriteAllText($path, $raw.TrimEnd() + "`n" + $scss.TrimStart("`r","`n").Insert(0, "`n"), $enc)
Select-String -Path $path -Pattern 'Layanan \(#4, #5\)', '\.gallery-grid', '\.rich-text' | ForEach-Object { $_.Line }
```
Expected: tiga baris tercetak.

- [ ] **Step 5: Jalankan tes — harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=PublicClinicPagesTest
npm run build
```
Expected: PASS (9 tes); `npm run build` sukses (peringatan `mixed-decls` boleh muncul, R3 fase 1).

- [ ] **Step 6: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint --test
git add routes/web.php app/Http/Controllers/Public/ClinicController.php resources/views/public/clinics/index.blade.php resources/views/public/partials/_clinic-card.blade.php resources/scss/_public.scss tests/Feature/Public/PublicClinicPagesTest.php
git commit -m @'
feat(fase-2): halaman publik Daftar Layanan (#4) per jenis layanan aktif

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
'@
```
Expected: Pint `PASS` (bila gagal jalankan `& $PHP vendor\bin\pint` lalu ulangi `--test`). Catatan: `show` di controller belum punya view sampai Step 8; route tidak dipakai tes sampai Siklus B.

**Siklus B — detail layanan (#5), `x-doctor-card`, galeri, pemindai `{!!`**

- [ ] **Step 7: Tulis tes yang gagal (tool Write) `tests/Feature/View/DoctorCardComponentTest.php`**

```php
<?php

namespace Tests\Feature\View;

use App\Models\Doctor;
use App\Services\Media\MediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ImageFixtures;
use Tests\Support\InteractsWithMasterData;
use Tests\TestCase;

class DoctorCardComponentTest extends TestCase
{
    use InteractsWithMasterData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function render(Doctor $doctor, string $attributes = ''): string
    {
        $doctor = Doctor::query()->with(['specialties', 'media', 'clinic'])->findOrFail($doctor->id);

        return Blade::render('<x-doctor-card :doctor="$doctor" '.$attributes.' />', ['doctor' => $doctor]);
    }

    public function test_it_shows_display_name_and_only_the_primary_specialty(): void
    {
        $anak = $this->createSpecialty(['name' => 'Anak']);
        $umum = $this->createSpecialty(['name' => 'Dokter Umum']);
        $doctor = $this->createDoctor(['name' => 'Laras Wulandari', 'title_prefix' => 'dr.', 'title_suffix' => 'Sp.A'], null, [$anak, $umum]);

        $html = $this->render($doctor);

        $this->assertStringContainsString('dr. Laras Wulandari, Sp.A', $html);
        $this->assertStringContainsString('Anak', $html);
        $this->assertStringNotContainsString('Dokter Umum', $html);
    }

    public function test_name_without_suffix_has_no_dangling_comma(): void
    {
        $doctor = $this->createDoctor(['name' => 'Rani Oktaviani', 'title_prefix' => 'dr.', 'title_suffix' => null]);

        $html = $this->render($doctor);

        $this->assertStringContainsString('dr. Rani Oktaviani', $html);
        $this->assertStringNotContainsString('Rani Oktaviani,', $html);
    }

    public function test_link_to_the_doctor_page_appears_only_when_the_route_exists(): void
    {
        $doctor = $this->createDoctor();

        if (Route::has('doctors.show')) {
            $this->markTestSkipped('Route doctors.show sudah ada (fase 3): tautan diuji di tes fase 3.');
        }

        $this->assertStringNotContainsString('<a ', $this->render($doctor));

        Route::middleware('web')->get('/dokter/{doctor:slug}', fn () => 'x')->name('doctors.show');
        Route::getRoutes()->refreshNameLookups();

        $this->assertStringContainsString('href="'.url('/dokter/'.$doctor->slug).'"', $this->render($doctor));
    }

    public function test_heading_level_is_configurable_and_clamped(): void
    {
        $doctor = $this->createDoctor();

        $this->assertStringContainsString('<h4 class="h6 mb-1">', $this->render($doctor, ':heading-level="4"'));
        $this->assertStringContainsString('<h2 class="h6 mb-1">', $this->render($doctor, ':heading-level="1"'));
        $this->assertStringContainsString('<h3 class="h6 mb-1">', $this->render($doctor));
    }

    public function test_doctor_without_photo_uses_the_local_placeholder(): void
    {
        $doctor = $this->createDoctor();

        $html = $this->render($doctor);

        $this->assertStringContainsString('images/placeholders/doctor.svg', $html);
        $this->assertStringContainsString('alt="Foto '.e($doctor->display_name).'"', $html);
    }

    public function test_doctor_with_photo_uses_the_thumbnail_and_keeps_a_fallback(): void
    {
        $doctor = $this->createDoctor();
        $this->app->make(MediaService::class)->store(ImageFixtures::jpeg(600, 600, 'foto.jpg'), 'photo', $doctor, ['alt_text' => 'Foto contoh dokter']);

        $html = $this->render($doctor);

        $this->assertStringContainsString('/thumbs/', $html);
        $this->assertStringContainsString('data-fallback="', $html);
        $this->assertStringNotContainsString('onerror', $html);
    }

    public function test_clinic_name_is_shown_only_on_request(): void
    {
        $clinic = $this->createClinic(['name' => 'Poliklinik Uji Kartu']);
        $doctor = $this->createDoctor([], $clinic);

        $this->assertStringNotContainsString('Poliklinik Uji Kartu', $this->render($doctor));
        $this->assertStringContainsString('Poliklinik Uji Kartu', $this->render($doctor, ':show-clinic="true"'));
    }
}
```

- [ ] **Step 8: Tulis tes yang gagal (tool Write) `tests/Feature/Public/PublicClinicDetailTest.php`**

```php
<?php

namespace Tests\Feature\Public;

use App\Enums\ClinicType;
use App\Enums\DoctorStatus;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\User;
use App\Services\Media\MediaService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ImageFixtures;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * Halaman #5 `clinics.show` (05 hal. 5, 07 W-06, A-42).
 */
class PublicClinicDetailTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithStaff;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function url(Clinic $clinic): string
    {
        return route('clinics.show', $clinic->slug);
    }

    private function fakeRoute(string $name, string $uri): void
    {
        if (! Route::has($name)) {
            Route::middleware('web')->get($uri, fn () => 'x')->name($name);
            Route::getRoutes()->refreshNameLookups();
        }
    }

    // --- 404 (A-42) --------------------------------------------------------------------------

    public function test_inactive_clinic_is_404_for_guests_patients_and_every_staff_role(): void
    {
        $clinic = $this->createClinic(['name' => 'Poliklinik Tersembunyi', 'is_active' => false]);

        $this->get($this->url($clinic))->assertNotFound()->assertDontSee('Poliklinik Tersembunyi');

        foreach (['content-admin', 'registration-officer', 'super-admin'] as $role) {
            $this->actingAs($this->createStaff($role))->get($this->url($clinic))->assertNotFound();
        }

        $this->actingAs(User::factory()->patient()->create())->get($this->url($clinic))->assertNotFound();
    }

    public function test_unknown_slug_is_404(): void
    {
        $this->get('/layanan/slug-yang-tidak-ada')->assertNotFound();
    }

    // --- isi halaman -------------------------------------------------------------------------

    public function test_show_renders_the_clinic_details(): void
    {
        $clinic = $this->createClinic([
            'name' => 'Poliklinik Anak',
            'code' => 'ANK',
            'location' => 'Gedung A Lt. 1',
            'short_description' => 'Konsultasi tumbuh kembang anak.',
            'is_bookable' => true,
            'description' => '<p>Layanan <strong>ramah</strong> anak.</p><ul><li>Imunisasi</li></ul>',
        ]);

        $response = $this->get($this->url($clinic))->assertOk();

        $response->assertSee('Poliklinik Anak')
            ->assertSee('Gedung A Lt. 1')
            ->assertSee('Kode antrean: ANK')
            ->assertSee('Konsultasi tumbuh kembang anak.')
            ->assertSee('<strong>ramah</strong>', false)
            ->assertSee('Imunisasi')
            ->assertSee('Dapat didaftar online')
            ->assertSee('Informasi layanan');
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public function test_description_is_rendered_sanitized(): void
    {
        $clinic = $this->createClinic([
            'description' => '<p>Halo <strong>dunia</strong></p><script>alert(1)</script><img src="x" onerror="alert(1)"><a href="javascript:alert(1)">klik</a>',
        ]);

        $this->assertStringNotContainsString('<script', (string) $clinic->fresh()->description);

        $this->get($this->url($clinic))
            ->assertOk()
            ->assertSee('<strong>dunia</strong>', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('onerror', false)
            ->assertDontSee('javascript:', false);
    }

    public function test_description_updated_through_the_model_is_sanitized_before_rendering(): void
    {
        $clinic = $this->createClinic(['description' => '<p>Awal</p>']);
        $clinic->update(['description' => '<p onclick="x()">Baru</p><script>alert(2)</script>']);

        $this->get($this->url($clinic))
            ->assertOk()
            ->assertSee('Baru')
            ->assertDontSee('onclick', false)
            ->assertDontSee('alert(2)', false);
    }

    public function test_empty_description_renders_only_the_summary(): void
    {
        $clinic = $this->createClinic(['description' => null, 'short_description' => 'Ringkasan saja.']);

        $this->get($this->url($clinic))
            ->assertOk()
            ->assertSee('Ringkasan saja.')
            ->assertDontSee('clinic-description', false);
    }

    // --- varian penunjang (W-06) -------------------------------------------------------------

    public function test_support_clinic_shows_the_contact_note_instead_of_registration(): void
    {
        $clinic = $this->createClinic(['name' => 'Laboratorium', 'type' => ClinicType::Penunjang, 'is_bookable' => false, 'code' => 'LAB']);

        $this->get($this->url($clinic))
            ->assertOk()
            ->assertSee('Layanan ini tidak dapat didaftar online. Hubungi kami di (021) 0000-0000.')
            ->assertDontSee('Kode antrean')
            ->assertDontSee('Dapat didaftar online')
            ->assertDontSee('Dokter di poliklinik ini');
    }

    public function test_bookable_clinic_without_public_doctors_shows_the_empty_state(): void
    {
        $clinic = $this->createClinic(['is_bookable' => true]);

        $this->get($this->url($clinic))
            ->assertOk()
            ->assertSee('Dokter di poliklinik ini')
            ->assertSee('Belum ada dokter terjadwal');
    }

    public function test_registration_and_schedule_links_are_hidden_while_their_routes_do_not_exist(): void
    {
        if (Route::has('patient.appointments.create') || Route::has('schedules.index')) {
            $this->markTestSkipped('Route fase berikutnya sudah ada: tautan diuji di tes fase tersebut.');
        }

        $clinic = $this->createClinic(['is_bookable' => true]);

        $this->get($this->url($clinic))
            ->assertOk()
            ->assertDontSee('?poli=', false)
            ->assertDontSee('Daftar Kunjungan')
            ->assertDontSee('Jadwal Dokter');
    }

    public function test_registration_and_schedule_links_carry_the_clinic_slug_when_routes_exist(): void
    {
        $this->fakeRoute('patient.appointments.create', '/pasien/pendaftaran/baru');
        $this->fakeRoute('schedules.index', '/jadwal-dokter');
        $bookable = $this->createClinic(['is_bookable' => true]);
        $support = $this->createClinic(['type' => ClinicType::Penunjang, 'is_bookable' => false]);

        $this->get($this->url($bookable))
            ->assertOk()
            ->assertSee('href="'.route('patient.appointments.create', ['poli' => $bookable->slug]).'"', false)
            ->assertSee('href="'.route('schedules.index', ['poli' => $bookable->slug]).'"', false);

        $this->get($this->url($support))
            ->assertOk()
            ->assertDontSee('?poli='.$support->slug, false);
    }

    // --- dokter (F2-R9, F2-R28) --------------------------------------------------------------

    public function test_only_publicly_visible_doctors_of_the_clinic_are_listed(): void
    {
        $clinic = $this->createClinic(['name' => 'Poliklinik Uji']);
        $anak = $this->createSpecialty(['name' => 'Anak']);
        $umum = $this->createSpecialty(['name' => 'Dokter Umum']);
        $inactiveClinic = $this->createClinic(['is_active' => false]);

        $visible = $this->createDoctor(['name' => 'Laras Wulandari', 'title_prefix' => 'dr.', 'title_suffix' => 'Sp.A'], $clinic, [$anak, $umum]);
        $this->createDoctor(['name' => 'Nonaktif Satu', 'title_prefix' => 'dr.', 'title_suffix' => null, 'status' => DoctorStatus::Inactive], $clinic);
        $this->createDoctor(['name' => 'Arsip Dua', 'title_prefix' => 'dr.', 'title_suffix' => null, 'status' => DoctorStatus::Archived, 'archived_at' => now()->subDays(3)], $clinic);
        $this->createDoctor(['name' => 'Poli Lain Tiga', 'title_prefix' => 'dr.', 'title_suffix' => null], $inactiveClinic);

        $this->assertSame([$visible->id], Doctor::query()->publiclyVisible()->pluck('id')->all());

        $this->get($this->url($clinic))
            ->assertOk()
            ->assertSee('dr. Laras Wulandari, Sp.A')
            ->assertSee('Anak')
            ->assertDontSee('Dokter Umum')
            ->assertDontSee('Nonaktif Satu')
            ->assertDontSee('Arsip Dua')
            ->assertDontSee('Poli Lain Tiga');
    }

    public function test_doctor_cards_link_to_the_doctor_page_only_when_the_route_exists(): void
    {
        $clinic = $this->createClinic();
        $doctor = $this->createDoctor([], $clinic);

        if (! Route::has('doctors.show')) {
            $this->get($this->url($clinic))->assertOk()->assertDontSee('/dokter/'.$doctor->slug, false);
        }

        $this->fakeRoute('doctors.show', '/dokter/{doctor:slug}');

        $this->get($this->url($clinic))->assertOk()->assertSee('href="'.url('/dokter/'.$doctor->slug).'"', false);
    }

    // --- gambar (MED-08) ---------------------------------------------------------------------

    public function test_missing_cover_and_photo_use_local_placeholders(): void
    {
        $clinic = $this->createClinic();
        $this->createDoctor([], $clinic);

        $this->get($this->url($clinic))
            ->assertOk()
            ->assertSee('images/placeholders/clinic.svg', false)
            ->assertSee('images/placeholders/doctor.svg', false)
            ->assertDontSee('og:image', false);
    }

    public function test_cover_file_missing_on_disk_keeps_alt_and_a_delegated_fallback(): void
    {
        $clinic = $this->createClinic(['name' => 'Poliklinik Anak']);
        $media = $this->app->make(MediaService::class)->store(ImageFixtures::jpeg(1200, 800, 'cover.jpg'), 'cover', $clinic, ['alt_text' => 'Foto contoh Poliklinik Anak']);
        Storage::disk('public')->delete([$media->directory.'/'.$media->file_name, (string) $media->thumb_path]);

        $this->get($this->url($clinic))
            ->assertOk()
            ->assertSee('alt="Foto contoh Poliklinik Anak"', false)
            ->assertSee('data-fallback="'.asset('images/placeholders/clinic.svg').'"', false)
            ->assertDontSee('onerror', false);
    }

    // --- galeri ------------------------------------------------------------------------------

    public function test_gallery_renders_thumbnails_and_one_modal_carousel(): void
    {
        $clinic = $this->createClinic();
        $media = $this->app->make(MediaService::class);

        foreach (range(1, 3) as $number) {
            $media->store(ImageFixtures::jpeg(900, 600, "g{$number}.jpg"), 'gallery', $clinic, ['alt_text' => "Foto galeri {$number}"]);
        }

        $html = $this->get($this->url($clinic))->assertOk()->getContent();

        $this->assertStringContainsString('<h2 id="galeri-judul"', $html);
        $this->assertStringContainsString('id="galeri-modal"', $html);
        $this->assertStringContainsString('data-gallery-modal', $html);
        $this->assertSame(3, substr_count($html, 'data-gallery-index='));
        $this->assertSame(3, substr_count($html, 'class="carousel-item'));
        $this->assertStringContainsString('alt="Foto galeri 2"', $html);
        $this->assertStringContainsString('Perbesar foto 1 dari 3: Foto galeri 1', $html);
    }

    public function test_page_without_gallery_has_no_modal(): void
    {
        $clinic = $this->createClinic();

        $this->get($this->url($clinic))->assertOk()->assertDontSee('galeri-modal', false)->assertDontSee('Galeri');
    }

    // --- SEO (05 §7) dan breadcrumb (05 §8.1) ------------------------------------------------

    public function test_title_description_and_og_tags_follow_the_seo_rules(): void
    {
        $clinic = $this->createClinic(['name' => 'Poliklinik Anak', 'short_description' => 'Konsultasi tumbuh kembang anak.', 'meta_title' => null, 'meta_description' => null]);
        $this->app->make(MediaService::class)->store(ImageFixtures::jpeg(1200, 800, 'cover.jpg'), 'cover', $clinic, ['alt_text' => 'Foto contoh Poliklinik Anak']);

        $this->get($this->url($clinic))
            ->assertOk()
            ->assertSee('<title>Poliklinik Anak — '.config('app.name').'</title>', false)
            ->assertSee('<meta name="description" content="Konsultasi tumbuh kembang anak.">', false)
            ->assertSee('<meta property="og:title" content="Poliklinik Anak">', false)
            ->assertSee('<meta property="og:image" content="', false);
    }

    public function test_meta_fields_override_the_defaults_and_the_description_is_cut_at_160(): void
    {
        $custom = $this->createClinic(['name' => 'Poliklinik Mata', 'meta_title' => 'Dokter Mata Terdekat', 'meta_description' => 'Deskripsi SEO khusus.']);
        $long = $this->createClinic(['short_description' => str_repeat('Layanan unggulan ', 15)]);

        $this->get($this->url($custom))
            ->assertOk()
            ->assertSee('<title>Dokter Mata Terdekat — '.config('app.name').'</title>', false)
            ->assertSee('<meta name="description" content="Deskripsi SEO khusus.">', false);

        $html = $this->get($this->url($long))->assertOk()->getContent();
        preg_match('/<meta name="description" content="([^"]*)">/', $html, $match);

        $this->assertNotEmpty($match);
        $this->assertLessThanOrEqual(160, mb_strlen(html_entity_decode($match[1])));
    }

    public function test_breadcrumb_trail_ends_with_the_clinic_name_without_a_link(): void
    {
        $clinic = $this->createClinic(['name' => 'Poliklinik Saraf']);

        $this->get($this->url($clinic))
            ->assertOk()
            ->assertSeeInOrder(['aria-label="Breadcrumb"', 'Beranda', 'Layanan', 'Poliklinik Saraf'], false)
            ->assertSee('<li class="breadcrumb-item active" aria-current="page">Poliklinik Saraf</li>', false)
            ->assertSee('<li class="breadcrumb-item"><a href="'.route('clinics.index').'">Layanan</a></li>', false);
    }

    // --- mode ketat dengan banyak baris + CSP ------------------------------------------------

    public function test_show_renders_many_doctors_with_photos_and_gallery_in_strict_mode(): void
    {
        $this->assertTrue(Model::preventsLazyLoading());

        $clinic = $this->createClinic(['name' => 'Poliklinik Ramai']);
        $media = $this->app->make(MediaService::class);
        $media->store(ImageFixtures::jpeg(1200, 800, 'cover.jpg'), 'cover', $clinic, ['alt_text' => 'Foto contoh Poliklinik Ramai']);
        $media->store(ImageFixtures::jpeg(900, 600, 'g.jpg'), 'gallery', $clinic, ['alt_text' => 'Galeri satu']);
        $anak = $this->createSpecialty(['name' => 'Anak']);
        $umum = $this->createSpecialty(['name' => 'Dokter Umum']);

        foreach (range(1, 6) as $number) {
            $doctor = $this->createDoctor(['name' => "Dokter Uji {$number}", 'title_prefix' => 'dr.', 'title_suffix' => null, 'sort_order' => $number], $clinic, [$anak, $umum]);
            $media->store(ImageFixtures::jpeg(600, 600, 'p.jpg'), 'photo', $doctor, ['alt_text' => "Foto dokter {$number}"]);
        }

        $html = $this->get($this->url($clinic))->assertOk()->getContent();

        $this->assertSame(6, substr_count($html, 'doctor-card"'));
        $this->assertStringContainsString('dr. Dokter Uji 6', $html);
    }

    public function test_rendered_pages_are_compatible_with_the_strict_csp(): void
    {
        $clinic = $this->createClinic(['description' => '<p>Isi</p>']);
        $this->app->make(MediaService::class)->store(ImageFixtures::jpeg(900, 600, 'g.jpg'), 'gallery', $clinic, ['alt_text' => 'Galeri']);

        foreach ([route('clinics.index'), $this->url($clinic)] as $uri) {
            $html = (string) $this->get($uri)->assertOk()->getContent();

            $this->assertSame(0, preg_match('/\son[a-z]+\s*=/i', $html), "Atribut event inline ditemukan di {$uri}.");
            $this->assertStringNotContainsString('javascript:', $html);
            $this->assertStringNotContainsString('<script>', $html);
            $this->assertStringNotContainsString('blob:', $html);
        }
    }
}
```

- [ ] **Step 9: Tulis tes yang gagal (tool Write) `tests/Feature/View/RawOutputScanTest.php`**

```php
<?php

namespace Tests\Feature\View;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * SEK-15 (statis): `{!! !!}` hanya untuk kolom rich text tersanitasi. Di fase 2 hanya
 * `clinics.description` pada `public/clinics/show.blade.php`; fase 4a menambah kolom lain di tes ini.
 */
class RawOutputScanTest extends TestCase
{
    /** @return list<string> */
    private function viewFiles(): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views'), FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if (str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    private function relative(string $path): string
    {
        return ltrim(str_replace('\\', '/', substr($path, strlen(resource_path('views')))), '/');
    }

    public function test_raw_blade_output_is_only_used_for_the_clinic_description(): void
    {
        $found = [];

        foreach ($this->viewFiles() as $file) {
            foreach (file($file, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
                if (str_contains($line, '{!!')) {
                    $found[] = $this->relative($file).': '.trim($line);
                }
            }
        }

        $this->assertSame(['public/clinics/show.blade.php: <div class="rich-text clinic-description">{!! $clinic->description !!}</div>'], $found);
    }

    public function test_views_do_not_build_html_strings_by_hand(): void
    {
        foreach ($this->viewFiles() as $file) {
            $this->assertStringNotContainsString('HtmlString', (string) file_get_contents($file), $this->relative($file));
        }
    }

    public function test_public_views_have_no_inline_event_handlers(): void
    {
        $files = array_filter($this->viewFiles(), fn (string $file): bool => str_contains($this->relative($file), 'public/') || str_contains($this->relative($file), 'components/doctor-card'));

        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            $this->assertSame(0, preg_match('/\son[a-z]{3,}\s*=\s*["\']/i', (string) file_get_contents($file)), $this->relative($file));
        }
    }

    public function test_app_script_wires_the_gallery_modal_without_a_second_error_listener(): void
    {
        $app = (string) file_get_contents(base_path('resources/js/app.js'));

        $this->assertStringContainsString('[data-gallery-modal]', $app);
        $this->assertStringContainsString('initGalleryModals();', $app);
        $this->assertStringContainsString('data-gallery-index', $app);
        $this->assertSame(1, preg_match_all("/addEventListener\\(\\s*'error'/", $app));
    }
}
```

- [ ] **Step 10: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="DoctorCardComponentTest|PublicClinicDetailTest|RawOutputScanTest"
```
Expected: FAIL — `InvalidArgumentException: Unable to locate a class or view for component [doctor-card].` (tes komponen), `View [public.clinics.show] not found.` (tes detail; 404 inactive dan slug salah tetap PASS), serta `RawOutputScanTest`: `Failed asserting that two arrays are identical` (kosong) dan `Failed asserting that '...app.js' contains "[data-gallery-modal]"`.

- [ ] **Step 11: Implementasi `x-doctor-card`, `show`, dan JS (tool Write / Edit-Text)**

`resources/views/components/doctor-card.blade.php`:

```blade
@props(['doctor', 'headingLevel' => 3, 'showClinic' => false])
@php
    $level = min(6, max(2, (int) $headingLevel));
    $heading = 'h'.$level;
    $specialty = $doctor->primary_specialty;
    $url = \Illuminate\Support\Facades\Route::has('doctors.show') ? route('doctors.show', $doctor) : null;
@endphp
<article {{ $attributes->class(['card', 'h-100', 'doctor-card']) }}>
    <div class="card-body d-flex align-items-center gap-3">
        <div class="doctor-card-photo flex-shrink-0">
            <x-image type="doctor" :media="$doctor->firstMedia('photo')" :alt="'Foto '.$doctor->display_name" variant="thumb" ratio="1x1" />
        </div>
        <div class="flex-grow-1">
            <{{ $heading }} class="h6 mb-1">
                @if ($url)
                    <a class="stretched-link text-decoration-none" href="{{ $url }}">{{ $doctor->display_name }}</a>
                @else
                    {{ $doctor->display_name }}
                @endif
            </{{ $heading }}>
            @if ($specialty)
                <span class="badge text-bg-light border">{{ $specialty->name }}</span>
            @endif
            @if ($showClinic)
                <p class="small text-body-secondary mb-0 mt-1">{{ $doctor->clinic->name }}</p>
            @endif
        </div>
    </div>
</article>
```

`resources/views/public/clinics/show.blade.php`:

```blade
@inject('site', 'App\Services\Settings\SettingsService')
@php
    $cover = $clinic->firstMedia('cover');
    $gallery = $clinic->mediaIn('gallery')->values();
    $coverAlt = $cover?->alt_text ?: 'Foto '.$clinic->name;
    $phone = $site->string('contact.phone') ?: '(021) 0000-0000';
    $registerUrl = $clinic->is_bookable && Route::has('patient.appointments.create')
        ? route('patient.appointments.create', ['poli' => $clinic->slug])
        : null;
    $scheduleUrl = $clinic->is_bookable && Route::has('schedules.index')
        ? route('schedules.index', ['poli' => $clinic->slug])
        : null;
@endphp
<x-layouts.public :title="$clinic->seo_title" :meta-description="$metaDescription">
    <x-slot:breadcrumb>
        <x-breadcrumb :items="[['label' => 'Layanan', 'url' => route('clinics.index')], ['label' => $clinic->name]]" />
    </x-slot:breadcrumb>

    @push('head')
        <meta property="og:type" content="website">
        <meta property="og:title" content="{{ $clinic->seo_title }}">
        <meta property="og:description" content="{{ $metaDescription }}">
        @if ($cover?->url)
            <meta property="og:image" content="{{ url($cover->url) }}">
        @endif
    @endpush

    <div class="row g-4 g-lg-5">
        <div class="col-lg-8">
            <x-image type="clinic" :media="$cover" :alt="$coverAlt" ratio="16x9" :lazy="false" />

            <header class="mt-4">
                <h1 class="h2 mb-2">{{ $clinic->name }}</h1>
                <p class="text-body-secondary mb-2"><i class="bi bi-geo-alt" aria-hidden="true"></i> {{ $clinic->location }}</p>
                @if ($clinic->is_bookable)
                    <p class="mb-2"><span class="badge text-bg-primary">Kode antrean: {{ $clinic->code }}</span></p>
                @endif
            </header>

            <p class="lead">{{ $clinic->short_description }}</p>

            @if (filled($clinic->description))
                <div class="rich-text clinic-description">{!! $clinic->description !!}</div>
            @endif

            @if ($gallery->isNotEmpty())
                <section class="mt-5" aria-labelledby="galeri-judul">
                    <h2 id="galeri-judul" class="h4 mb-3">Galeri</h2>
                    <div class="gallery-grid">
                        @foreach ($gallery as $index => $photo)
                            <button type="button" class="gallery-thumb" data-bs-toggle="modal" data-bs-target="#galeri-modal" data-gallery-index="{{ $index }}" aria-label="Perbesar foto {{ $index + 1 }} dari {{ $gallery->count() }}: {{ $photo->alt_text }}">
                                <x-image type="clinic" :media="$photo" decorative variant="thumb" ratio="4x3" />
                            </button>
                        @endforeach
                    </div>
                </section>

                <div class="modal fade" id="galeri-modal" tabindex="-1" role="dialog" aria-labelledby="galeri-modal-judul" aria-hidden="true" data-gallery-modal>
                    <div class="modal-dialog modal-lg modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <div class="modal-title h5" id="galeri-modal-judul">Galeri {{ $clinic->name }}</div>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup galeri"></button>
                            </div>
                            <div class="modal-body p-0">
                                <div id="galeri-carousel" class="carousel slide" tabindex="-1" data-bs-ride="false" aria-label="Foto galeri">
                                    <div class="carousel-inner">
                                        @foreach ($gallery as $index => $photo)
                                            <div @class(['carousel-item', 'active' => $index === 0])>
                                                <x-image type="clinic" :media="$photo" :lazy="false" class="d-block w-100" />
                                            </div>
                                        @endforeach
                                    </div>
                                    @if ($gallery->count() > 1)
                                        <button class="carousel-control-prev" type="button" data-bs-target="#galeri-carousel" data-bs-slide="prev">
                                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                            <span class="visually-hidden">Foto sebelumnya</span>
                                        </button>
                                        <button class="carousel-control-next" type="button" data-bs-target="#galeri-carousel" data-bs-slide="next">
                                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                            <span class="visually-hidden">Foto berikutnya</span>
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            @if ($doctors->isNotEmpty() || $clinic->is_bookable)
                <section class="mt-5" aria-labelledby="dokter-judul">
                    <h2 id="dokter-judul" class="h4 mb-3">Dokter di poliklinik ini</h2>
                    @if ($doctors->isEmpty())
                        <x-empty-state title="Belum ada dokter terjadwal" icon="bi-person-badge" />
                    @else
                        <div class="row g-3">
                            @foreach ($doctors as $doctor)
                                <div class="col-12 col-sm-6">
                                    <x-doctor-card :doctor="$doctor" :heading-level="3" />
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>
            @endif
        </div>

        <aside class="col-lg-4" aria-label="Informasi layanan">
            <div class="card clinic-side-panel">
                <div class="card-body">
                    <h2 class="h5 mb-3">Informasi layanan</h2>
                    <dl class="mb-3">
                        <dt>Lokasi</dt>
                        <dd>{{ $clinic->location }}</dd>
                        <dt>Jenis layanan</dt>
                        <dd>{{ $clinic->type->label() }}</dd>
                        @if ($clinic->is_bookable)
                            <dt>Pendaftaran</dt>
                            <dd class="mb-0">Dapat didaftar online</dd>
                        @endif
                    </dl>

                    @if ($clinic->is_bookable)
                        @if ($registerUrl)
                            <a class="btn btn-primary w-100 mb-2" href="{{ $registerUrl }}">Daftar Kunjungan</a>
                        @endif
                        @if ($scheduleUrl)
                            <a class="btn btn-outline-primary w-100" href="{{ $scheduleUrl }}">Jadwal Dokter</a>
                        @endif
                    @else
                        <p class="mb-2">Layanan ini tidak dapat didaftar online. Hubungi kami di {{ $phone }}.</p>
                        @if (Route::has('contact'))
                            <a class="btn btn-outline-primary w-100" href="{{ route('contact') }}">Hubungi Kami</a>
                        @endif
                    @endif
                </div>
            </div>
        </aside>
    </div>
</x-layouts.public>
```

JS galeri (`resources/js/app.js`, dua `Edit-Text`):

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
. "$env:TEMP\unpam-edit-text.ps1"
$js = "$APP\resources\js\app.js"

Edit-Text $js @'
function init() {
'@ @'
// ---------------------------------------------------------------- Galeri layanan (modal + carousel Bootstrap)
// Markup: modal [data-gallery-modal] berisi .carousel; tombol pemicu membawa data-gallery-index.
function initGalleryModals() {
    document.querySelectorAll('[data-gallery-modal]').forEach((modal) => {
        const carousel = modal.querySelector('.carousel');

        if (!carousel) {
            return;
        }

        modal.addEventListener('show.bs.modal', (event) => {
            const trigger = event.relatedTarget;
            const raw = trigger && trigger.dataset ? trigger.dataset.galleryIndex : undefined;
            const parsed = Number.parseInt(raw ?? '0', 10);
            const index = Number.isNaN(parsed) ? 0 : parsed;

            carousel.querySelectorAll('.carousel-item').forEach((item, position) => {
                item.classList.toggle('active', position === index);
            });
        });

        modal.addEventListener('shown.bs.modal', () => carousel.focus());
    });
}

function init() {
'@

Edit-Text $js 'initOffcanvasToggles();' @'
initOffcanvasToggles();
    initGalleryModals();
'@
Select-String -Path $js -Pattern 'initGalleryModals', '\[data-gallery-modal\]' | ForEach-Object { $_.Line.Trim() }
```
Expected: tiga baris (definisi fungsi, pemanggilan, selektor).

- [ ] **Step 12: Jalankan tes — harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="DoctorCardComponentTest|PublicClinicDetailTest|RawOutputScanTest|PublicClinicPagesTest|FrontendManifestTest|SecurityHeadersTest"
npm run build
```
Expected: PASS (DoctorCard 7, Detail 21, RawOutputScan 4, Pages 9; `FrontendManifestTest` dan `SecurityHeadersTest` tetap hijau); build sukses. Bila `test_only_publicly_visible_doctors_of_the_clinic_are_listed` gagal pada `assertSame([$visible->id], …)`, periksa scope `publiclyVisible()` Task 1 (aktif **dan** `clinic.is_active`), bukan tes.

- [ ] **Step 13: Seluruh suite, Pint, commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test
& $PHP vendor\bin\pint --test
git add app/Http/Controllers/Public/ClinicController.php resources/views/public/clinics/show.blade.php resources/views/components/doctor-card.blade.php resources/js/app.js tests/Feature/Public/PublicClinicDetailTest.php tests/Feature/View/DoctorCardComponentTest.php tests/Feature/View/RawOutputScanTest.php
git commit -m @'
feat(fase-2): halaman Detail Layanan (#5), x-doctor-card, galeri modal, pemindai {!!

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
'@
git status --short
```
Expected: seluruh tes PASS; Pint `PASS`; `git status` bersih. `ClinicController.php` ikut di-commit di sini hanya bila Step 6 belum memasukkannya (berkas sudah final di Step 4; bila tidak ada perubahan, `git add` tidak mengubah apa pun).

**Catatan konflik (kerangka):** kerangka menyebut `PublicClinicPagesTest` untuk tes banyak-baris mode ketat #4 **dan** #5; di sini dibagi dua kelas (`PublicClinicPagesTest` = #4, `PublicClinicDetailTest` = #5) agar tiap siklus TDD berdiri sendiri. Bagian `{!!` memakai kelas baru `RawOutputScanTest` karena belum ada pemindai serupa di repo.

---

### Task 14: Seeder demo (`SpecialtySeeder`, `ClinicSeeder`, `DoctorSeeder`) dan `PlaceholderImageGenerator`

**Files:**
- Create: `app/Services/Media/PlaceholderImageGenerator.php`, `database/seeders/SpecialtySeeder.php`, `database/seeders/ClinicSeeder.php`, `database/seeders/DoctorSeeder.php`
- Modify: `database/seeders/DemoSeeder.php`, `tests/Feature/Seeding/SeederProductionGuardTest.php`, `tests/Feature/Auth/RolePermissionSeederTest.php` (keduanya memanggil `DemoSeeder` lewat `DatabaseSeeder` sehingga wajib `Storage::fake('public')`)
- Test: `tests/Feature/Media/PlaceholderImageGeneratorTest.php`, `tests/Unit/MasterDataSeedRowsTest.php`, `tests/Feature/Seeding/MasterDataSeederGuardTest.php`, `tests/Feature/Seeding/DemoSeederWiringTest.php`, `tests/Feature/Seeding/DemoSeederInvariantsTest.php`

**Interfaces:**
- Consumes: Task 1 (`Specialty`, `Clinic`, `Doctor`, accessor `display_name`/`cover_url`, scope `publiclyVisible()`/`inSpecialty()`), Task 2 (`HasUniqueSlug` menghormati `slug` yang sudah diisi; `SanitizesRichText` membersihkan `description` saat `saving`; `HasMedia`), Fase 1 (`MediaService::store()` mensyaratkan pemilik tersimpan, `alt_text` wajib, berkas `UploadedFile`; `media:prune`; `DemoSeeder`/`DemoUserSeeder` + guard lingkungan; `ClinicType`, `DoctorStatus`, `Gender`).
- Produces:
  - `App\Services\Media\PlaceholderImageGenerator` (`final`; constructor `(?string $fontPath = null)`; `make(string $type, string $label, int $width, int $height): UploadedFile`):
    - `$type` ∈ `photo|cover|gallery|photos|editor|image` (JPEG kualitas 82) atau `logo` (PNG); selain itu `InvalidArgumentException`. `$label` (inisial/kode) wajib; ukuran 16–6000 px.
    - Latar dipilih deterministik dari `crc32($label)` atas `#0A4D8C`, `#0E7C74`, `#1E7D45`, `#13A89E`; pita `#13A89E` di 70–76% tinggi; inisial besar (30% tinggi) di tengah; teks kecil "Foto contoh" (`image` → "Banner contoh", `logo` → "Logo contoh") di 84% tinggi. Teks `#08233B` bila latar `#13A89E`, selain itu `#FFFFFF` (00 M).
    - Font: `$fontPath` (bawaan: **tidak diset**; `resources/fonts/PlusJakartaSans-Bold.ttf` belum dibundel karena tanpa paket baru, 13 §9.2 [ASUMSI]) bila berkas ada dan `imagettftext` tersedia; jika tidak jatuh ke `imagestring()` bawaan GD yang diperbesar (verifikasi: GD XAMPP 8.2 memiliki FreeType dan `imagestring`).
    - Mengembalikan `UploadedFile` **mode uji** (`test = true`) di berkas sementara; **pemanggil wajib `@unlink($file->getPathname())` di blok `finally`** setelah `MediaService::store()`.
  - Konstanta data demo (13 §4–6, persis): `SpecialtySeeder::ROWS` (13 baris), `ClinicSeeder::ROWS` (17 baris), `DoctorSeeder::ROWS` (18 baris).
  - Ketiga seeder: hanya `local`/`testing` (`RuntimeException` "… DILARANG di APP_ENV=…" di luar itu), idempoten per baris (slug sudah ada ⇒ peringatan dan dilewati), `sort_order` = nomor urut tabel, **tidak** lewat `DoctorService` (seeder menulis langsung agar tidak menambah audit `doctor.*`; `archived_at` lewat `forceFill`). `Specialty`/`Clinic` tetap memicu `AuditObserver` (aktor sistem).
  - `DemoSeeder` memanggil `[DemoUserSeeder, SpecialtySeeder, ClinicSeeder, DoctorSeeder]` (urutan 13 §2.1).
  - Angka acuan database baru: `specialties` 13; `clinics` 17 (13 poliklinik bisa didaftar + 4 penunjang tidak); `doctors` 18 (16 aktif, 1 nonaktif, 1 arsip); `doctor_specialties` 20 (18 primer + 2 sekunder); `media` 32 (16 `photo` + 16 `cover`, tanpa `gallery`); tiap dokter tepat satu spesialisasi primer (K-5), `archived` ⇔ `archived_at` terisi (K-23).

**Data yang tidak ditetapkan 13 (ditulis di plan ini, netral, bertanda "contoh"):** teks `description` spesialisasi (kecuali Urologi, kutipan 13 §4), isi `description` HTML klinik, dan `bio` dokter. Tabel nama/slug/kode/jenis/lokasi/spesialisasi/status disalin persis dari 13 §4, §5, §6.

**Test IDs:** `SEED-01` (bagian master data), `SEED-02` (seeder master ikut guard), `SEED-03` (`media:prune --dry-run` = 0 yatim, gambar dari generator lokal), `MED-08` (poliklinik `GG` dan dokter #15/#16 tanpa gambar), `CRD-04` (nama tampilan seeder).

**Catatan teknis (diverifikasi dari source):**
- `PlaceholderImageGenerator` sudah dijalankan di PHP 8.2.12 XAMPP: JPEG 600×600 dan 1200×800, PNG 400×200, kedua jalur font (bitmap dan TrueType) menghasilkan gambar valid; piksel latar JPEG berbeda ≤ 3 per kanal dari warna token (toleransi tes 8).
- `MediaService::store()` mensyaratkan dimensi minimum (`photo` 300×300, `cover` 800×450) dan memperkecil lebar > 1600; gambar 600×600/1200×800 tersimpan apa adanya. Aksi `media.uploaded` dicatat dengan aktor sistem (tanpa pengguna login).
- Mode ketat: `new Doctor([...])` harus menyertakan **semua** kunci (termasuk `title_suffix => null`) agar accessor `display_name` yang membaca atribut tidak melempar `MissingAttributeException` sebelum disimpan. `archived_at` bukan `$fillable` ⇒ `forceFill()`.
- `RefreshDatabase` membungkus tiap tes dalam transaksi; penulisan berkas lewat `Storage::fake('public')` tidak ikut di-*rollback* tetapi dibuang bersama disk palsu.
- `SeederProductionGuardTest` dan `RolePermissionSeederTest::test_database_seeder_runs_the_role_permission_seeder` menjalankan `DemoSeeder` di lingkungan `testing`; tanpa `Storage::fake('public')` mereka menulis 64 berkas ke `storage/app/public` nyata.
- `INS`: `migrate:fresh --seed` di lingkungan lokal menulis 32 gambar + 32 thumbnail ke `storage/app/public` (±beberapa detik).

- [ ] **Step 1: Verifikasi prasyarat**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git branch --show-current
git status --short
& $PHP -r "print_r(array_intersect_key(gd_info(), ['FreeType Support'=>1,'JPEG Support'=>1,'PNG Support'=>1])); var_dump(function_exists('imagestring'), function_exists('imagettftext'));"
Test-Path "$env:TEMP\unpam-edit-text.ps1"
& $PHP artisan test --filter="SeederProductionGuardTest|DemoUserSeederTest|MediaServiceTest"
```
Expected: `fase-2-master-data`; bersih; `FreeType Support => 1`, `JPEG Support => 1`, `PNG Support => 1`, `bool(true)` ×2; `True` (bila `False`, buat ulang pembantu dari Task 13 Step 1); tes PASS.

**Siklus A — `PlaceholderImageGenerator`**

- [ ] **Step 2: Tulis tes yang gagal (tool Write) `tests/Feature/Media/PlaceholderImageGeneratorTest.php`**

```php
<?php

namespace Tests\Feature\Media;

use App\Services\Media\MediaService;
use App\Services\Media\PlaceholderImageGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\Support\InteractsWithMasterData;
use Tests\TestCase;

class PlaceholderImageGeneratorTest extends TestCase
{
    use InteractsWithMasterData;
    use RefreshDatabase;

    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    private function make(string $type, string $label, int $width, int $height, ?string $font = null): UploadedFile
    {
        $file = (new PlaceholderImageGenerator($font))->make($type, $label, $width, $height);
        $this->temporaryFiles[] = $file->getPathname();

        return $file;
    }

    /** @return array{int, int, int} */
    private function pixel(UploadedFile $file, int $x, int $y): array
    {
        $image = imagecreatefromstring((string) file_get_contents($file->getPathname()));
        $color = imagecolorat($image, $x, $y);

        return [($color >> 16) & 0xFF, ($color >> 8) & 0xFF, $color & 0xFF];
    }

    private function assertNearHex(string $hex, array $rgb, int $tolerance = 8): void
    {
        $hex = ltrim($hex, '#');
        $expected = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];

        foreach ($expected as $channel => $value) {
            $this->assertEqualsWithDelta($value, $rgb[$channel], $tolerance, "Kanal {$channel} jauh dari #{$hex}");
        }
    }

    /** Luminans terendah dan tertinggi di area tengah (tempat inisial digambar). */
    private function centerExtremes(UploadedFile $file): array
    {
        $image = imagecreatefromstring((string) file_get_contents($file->getPathname()));
        $width = imagesx($image);
        $height = imagesy($image);
        $min = 255.0;
        $max = 0.0;

        for ($y = (int) ($height * 0.28); $y < (int) ($height * 0.58); $y += 2) {
            for ($x = (int) ($width * 0.25); $x < (int) ($width * 0.75); $x += 2) {
                $color = imagecolorat($image, $x, $y);
                $luma = 0.299 * (($color >> 16) & 0xFF) + 0.587 * (($color >> 8) & 0xFF) + 0.114 * ($color & 0xFF);
                $min = min($min, $luma);
                $max = max($max, $luma);
            }
        }

        return [$min, $max];
    }

    public function test_photo_is_a_valid_600_square_jpeg_in_test_mode(): void
    {
        $file = $this->make('photo', 'RP', 600, 600);

        $this->assertTrue($file->isValid());
        $this->assertSame('rp.jpg', $file->getClientOriginalName());
        $info = getimagesize($file->getPathname());
        $this->assertSame([600, 600, IMAGETYPE_JPEG], [$info[0], $info[1], $info[2]]);
        $this->assertLessThan(2 * 1024 * 1024, filesize($file->getPathname()));
    }

    public function test_cover_is_a_1200_by_800_jpeg_and_logo_is_a_png(): void
    {
        $cover = getimagesize($this->make('cover', 'URO', 1200, 800)->getPathname());
        $logo = getimagesize($this->make('logo', 'UH', 400, 200)->getPathname());

        $this->assertSame([1200, 800, IMAGETYPE_JPEG], [$cover[0], $cover[1], $cover[2]]);
        $this->assertSame([400, 200, IMAGETYPE_PNG], [$logo[0], $logo[1], $logo[2]]);
    }

    public function test_background_comes_from_the_token_palette_and_is_deterministic(): void
    {
        $palette = array_map(
            fn (string $hex): array => [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))],
            PlaceholderImageGenerator::BACKGROUNDS,
        );
        $seen = [];

        foreach (['URO', 'PD', 'ANK', 'OBG', 'BDH', 'JTG', 'SRF', 'MTA', 'THT', 'KK', 'ORT', 'UMM', 'GG'] as $label) {
            $rgb = $this->pixel($this->make('cover', $label, 1200, 800), 2, 2);
            $matched = array_filter($palette, fn (array $color): bool => abs($color[0] - $rgb[0]) <= 8 && abs($color[1] - $rgb[1]) <= 8 && abs($color[2] - $rgb[2]) <= 8);
            $this->assertNotEmpty($matched, "Latar {$label} di luar palet token.");
            $seen[array_key_first($matched)] = true;
        }

        $this->assertGreaterThanOrEqual(2, count($seen), 'Latar harus bergilir, bukan satu warna.');
        $this->assertSame(
            $this->pixel($this->make('photo', 'RP', 600, 600), 2, 2),
            $this->pixel($this->make('photo', 'RP', 600, 600), 2, 2),
        );
    }

    public function test_text_is_dark_on_the_light_teal_background_and_white_elsewhere(): void
    {
        $teal = null;
        $other = null;

        foreach (range('A', 'Z') as $letter) {
            $isTeal = crc32($letter) % count(PlaceholderImageGenerator::BACKGROUNDS) === 3;
            $teal ??= $isTeal ? $letter : null;
            $other ??= $isTeal ? null : $letter;
        }

        [$tealMin] = $this->centerExtremes($this->make('cover', (string) $teal, 1200, 800));
        [, $otherMax] = $this->centerExtremes($this->make('cover', (string) $other, 1200, 800));

        $this->assertLessThan(70, $tealMin, 'Teks pada latar #13A89E harus gelap (#08233B).');
        $this->assertGreaterThan(220, $otherMax, 'Teks pada latar tua harus putih.');
    }

    public function test_ribbon_color_is_drawn_across_the_image(): void
    {
        $file = $this->make('cover', 'URO', 1200, 800);

        $this->assertNearHex(PlaceholderImageGenerator::RIBBON, $this->pixel($file, 10, 580));
    }

    public function test_it_works_without_a_font_file(): void
    {
        $file = $this->make('photo', 'RP', 600, 600, '/tidak/ada/font.ttf');

        $this->assertSame(600, getimagesize($file->getPathname())[0]);
    }

    public function test_it_uses_a_truetype_font_when_one_is_available(): void
    {
        $font = collect([
            'C:\\Windows\\Fonts\\arialbd.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/Library/Fonts/Arial Bold.ttf',
        ])->first(fn (string $path): bool => is_file($path));

        if ($font === null) {
            $this->markTestSkipped('Tidak ada berkas TTF sistem untuk menguji jalur TrueType.');
        }

        $file = $this->make('photo', 'RP', 600, 600, $font);

        $this->assertSame(600, getimagesize($file->getPathname())[0]);
    }

    public function test_invalid_arguments_are_rejected(): void
    {
        $generator = new PlaceholderImageGenerator;

        foreach ([['video', 'RP', 600, 600], ['photo', '  ', 600, 600], ['photo', 'RP', 8, 600], ['photo', 'RP', 600, 7000]] as $arguments) {
            try {
                $generator->make(...$arguments);
                $this->fail('InvalidArgumentException diharapkan untuk '.json_encode($arguments));
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_output_is_accepted_by_the_media_service_with_alt_text(): void
    {
        Storage::fake('public');
        $doctor = $this->createDoctor();
        $file = $this->make('photo', 'RP', 600, 600);

        $media = $this->app->make(MediaService::class)->store($file, 'photo', $doctor, ['alt_text' => 'Foto contoh dr. Uji']);

        $this->assertSame('photo', $media->collection);
        $this->assertSame([600, 600], [$media->width, $media->height]);
        $this->assertSame('Foto contoh dr. Uji', $media->alt_text);
        Storage::disk('public')->assertExists($media->directory.'/'.$media->file_name);
        Storage::disk('public')->assertExists((string) $media->thumb_path);
    }
}
```

- [ ] **Step 3: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=PlaceholderImageGeneratorTest
```
Expected: FAIL — `Error: Class "App\Services\Media\PlaceholderImageGenerator" not found` pada seluruh tes.

- [ ] **Step 4: Implementasi (tool Write) `app/Services/Media/PlaceholderImageGenerator.php`**

```php
<?php

declare(strict_types=1);

namespace App\Services\Media;

use GdImage;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use RuntimeException;

/**
 * Pembuat gambar bitmap contoh untuk seeder (13 §9.2): latar warna token, pita dekoratif,
 * inisial pemilik besar di tengah, dan teks kecil "Foto contoh" di bagian bawah.
 * Seluruhnya lokal (GD): tanpa unduhan dari internet.
 */
final class PlaceholderImageGenerator
{
    /** Latar bergilir (token warna 00 M); dipilih deterministik dari label. */
    public const BACKGROUNDS = ['#0A4D8C', '#0E7C74', '#1E7D45', '#13A89E'];

    public const RIBBON = '#13A89E';

    /** Teks pada latar toska muda memakai warna gelap (00 M melarang teks putih di sana). */
    public const DARK_TEXT = '#08233B';

    public const LIGHT_TEXT = '#FFFFFF';

    public const JPEG_QUALITY = 82;

    /** Jenis gambar => [format, teks kecil di bawah]. */
    private const TYPES = [
        'photo' => ['jpeg', 'Foto contoh'],
        'cover' => ['jpeg', 'Foto contoh'],
        'gallery' => ['jpeg', 'Foto contoh'],
        'photos' => ['jpeg', 'Foto contoh'],
        'editor' => ['jpeg', 'Foto contoh'],
        'image' => ['jpeg', 'Banner contoh'],
        'logo' => ['png', 'Logo contoh'],
    ];

    private const FALLBACK_FONT = 5;

    public function __construct(private readonly ?string $fontPath = null) {}

    /**
     * Buat satu gambar dan bungkus sebagai UploadedFile mode uji. Pemanggil wajib menghapus berkas
     * sementara ($file->getPathname()) di blok `finally` setelah MediaService::store().
     */
    public function make(string $type, string $label, int $width, int $height): UploadedFile
    {
        if (! isset(self::TYPES[$type])) {
            throw new InvalidArgumentException('Jenis gambar contoh tidak dikenal: '.$type);
        }

        $label = trim($label);

        if ($label === '') {
            throw new InvalidArgumentException('Label gambar contoh wajib diisi.');
        }

        if ($width < 16 || $height < 16 || $width > 6000 || $height > 6000) {
            throw new InvalidArgumentException('Ukuran gambar contoh harus antara 16 dan 6000 piksel.');
        }

        [$format, $caption] = self::TYPES[$type];
        $background = self::BACKGROUNDS[crc32($label) % count(self::BACKGROUNDS)];
        $textHex = $background === self::RIBBON ? self::DARK_TEXT : self::LIGHT_TEXT;

        $canvas = imagecreatetruecolor($width, $height);

        if ($canvas === false) {
            throw new RuntimeException('GD gagal membuat kanvas gambar contoh.');
        }

        imagefill($canvas, 0, 0, $this->allocate($canvas, $background));
        imagefilledrectangle(
            $canvas,
            0,
            (int) round($height * 0.70),
            $width - 1,
            (int) round($height * 0.76),
            $this->allocate($canvas, self::RIBBON),
        );

        $this->drawText($canvas, strtoupper($label), $textHex, (int) round($height * 0.30), (int) round($height * 0.28));
        $this->drawText($canvas, $caption, $textHex, (int) round($height * 0.07), (int) round($height * 0.84));

        $path = tempnam(sys_get_temp_dir(), 'ph');

        if ($path === false) {
            throw new RuntimeException('Tidak dapat membuat berkas sementara untuk gambar contoh.');
        }

        $written = $format === 'png' ? imagepng($canvas, $path) : imagejpeg($canvas, $path, self::JPEG_QUALITY);

        if (! $written) {
            @unlink($path);

            throw new RuntimeException('GD gagal menulis gambar contoh.');
        }

        $name = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($label)), '-').($format === 'png' ? '.png' : '.jpg');

        return new UploadedFile($path, $name, $format === 'png' ? 'image/png' : 'image/jpeg', null, true);
    }

    /**
     * Teks rata tengah horizontal. $textHeight = tinggi huruf target (piksel), $top = tepi atas teks.
     */
    private function drawText(GdImage $canvas, string $text, string $hex, int $textHeight, int $top): void
    {
        $textHeight = max(8, $textHeight);

        if ($this->fontPath !== null && is_file($this->fontPath) && function_exists('imagettftext')) {
            $this->drawTrueType($canvas, $text, $hex, $textHeight, $top);

            return;
        }

        $this->drawBitmap($canvas, $text, $hex, $textHeight, $top);
    }

    private function drawTrueType(GdImage $canvas, string $text, string $hex, int $textHeight, int $top): void
    {
        $size = $textHeight * 0.75; // titik ≈ 0,75 × piksel
        $box = imagettfbbox($size, 0, (string) $this->fontPath, $text);

        if ($box === false) {
            $this->drawBitmap($canvas, $text, $hex, $textHeight, $top);

            return;
        }

        $textWidth = $box[2] - $box[0];
        $x = (int) round((imagesx($canvas) - $textWidth) / 2) - $box[0];
        $y = $top + $textHeight;

        imagettftext($canvas, $size, 0, $x, $y, $this->allocate($canvas, $hex), (string) $this->fontPath, $text);
    }

    /**
     * Cadangan tanpa berkas font (13 §9.2 [ASUMSI]): imagestring() pada kanvas kecil, lalu diperbesar.
     */
    private function drawBitmap(GdImage $canvas, string $text, string $hex, int $textHeight, int $top): void
    {
        $text = (string) preg_replace('/[^\x20-\x7E]/', '?', $text);
        $glyphWidth = imagefontwidth(self::FALLBACK_FONT);
        $glyphHeight = imagefontheight(self::FALLBACK_FONT);
        $smallWidth = max(1, $glyphWidth * strlen($text));

        $small = imagecreatetruecolor($smallWidth, $glyphHeight);

        if ($small === false) {
            return;
        }

        imagealphablending($small, false);
        imagesavealpha($small, true);
        imagefill($small, 0, 0, (int) imagecolorallocatealpha($small, 0, 0, 0, 127));
        imagestring($small, self::FALLBACK_FONT, 0, 0, $text, $this->allocate($small, $hex));

        $scale = max(1, intdiv($textHeight, $glyphHeight));
        $targetWidth = $smallWidth * $scale;

        // Tidak boleh melebihi 90% lebar kanvas.
        while ($scale > 1 && $targetWidth > imagesx($canvas) * 0.9) {
            $scale--;
            $targetWidth = $smallWidth * $scale;
        }

        $targetHeight = $glyphHeight * $scale;
        $x = intdiv(imagesx($canvas) - $targetWidth, 2);

        imagealphablending($canvas, true);
        imagecopyresized($canvas, $small, $x, $top, 0, 0, $targetWidth, $targetHeight, $smallWidth, $glyphHeight);
    }

    private function allocate(GdImage $image, string $hex): int
    {
        $hex = ltrim($hex, '#');
        $color = imagecolorallocate(
            $image,
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        );

        if ($color === false) {
            throw new RuntimeException('GD gagal mengalokasikan warna.');
        }

        return $color;
    }
}
```

- [ ] **Step 5: Jalankan tes — harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=PlaceholderImageGeneratorTest
```
Expected: PASS (9 tes; `test_it_uses_a_truetype_font_when_one_is_available` boleh `SKIPPED` bila tidak ada TTF sistem).

- [ ] **Step 6: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint --test
git add app/Services/Media/PlaceholderImageGenerator.php tests/Feature/Media/PlaceholderImageGeneratorTest.php
git commit -m @'
feat(fase-2): PlaceholderImageGenerator membuat gambar contoh lokal dengan GD

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
'@
```
Expected: Pint `PASS`.

**Siklus B — data demo (spesialisasi, poliklinik, dokter) dan `DemoSeeder`**

- [ ] **Step 7: Tulis tes yang gagal — struktur data (tool Write) `tests/Unit/MasterDataSeedRowsTest.php`**

```php
<?php

namespace Tests\Unit;

use Database\Seeders\ClinicSeeder;
use Database\Seeders\DoctorSeeder;
use Database\Seeders\SpecialtySeeder;
use Tests\TestCase;

/**
 * Penjaga data demo 13 §4–6 pada tingkat konstanta (tanpa database dan tanpa gambar).
 */
class MasterDataSeedRowsTest extends TestCase
{
    public function test_specialty_rows_match_13_section_4(): void
    {
        $this->assertCount(13, SpecialtySeeder::ROWS);
        $this->assertSame(
            ['urologi', 'penyakit-dalam', 'anak', 'obstetri-dan-ginekologi', 'bedah-umum', 'jantung', 'saraf', 'mata', 'tht', 'kulit-dan-kelamin', 'ortopedi', 'dokter-umum', 'dokter-gigi'],
            array_column(SpecialtySeeder::ROWS, 'slug'),
        );
        $this->assertSame('Pelayanan kesehatan saluran kemih dan sistem reproduksi pria (contoh).', SpecialtySeeder::ROWS[0]['description']);

        foreach (SpecialtySeeder::ROWS as $row) {
            $this->assertStringEndsWith('(contoh).', $row['description']);
            $this->assertLessThanOrEqual(100, mb_strlen($row['name']));
        }
    }

    public function test_clinic_rows_match_13_section_5(): void
    {
        $rows = ClinicSeeder::ROWS;

        $this->assertCount(17, $rows);
        $this->assertCount(13, array_filter($rows, fn (array $row): bool => $row['type'] === 'poliklinik' && $row['bookable'] === true));
        $this->assertCount(4, array_filter($rows, fn (array $row): bool => $row['type'] === 'penunjang' && $row['bookable'] === false && $row['specialty'] === null));
        $this->assertSame(['URO', 'PD', 'ANK', 'OBG', 'BDH', 'JTG', 'SRF', 'MTA', 'THT', 'KK', 'ORT', 'UMM', 'GG', 'LAB', 'RAD', 'FAR', 'IGD'], array_column($rows, 'code'));
        $this->assertCount(17, array_unique(array_column($rows, 'slug')));

        foreach ($rows as $row) {
            $this->assertMatchesRegularExpression('/^[A-Z]{2,5}$/', $row['code']);
            $this->assertLessThanOrEqual(300, mb_strlen(ClinicSeeder::shortDescription($row)));
        }

        $this->assertCount(16, array_filter($rows, fn (array $row): bool => $row['cover']));
        $this->assertFalse(collect($rows)->firstWhere('code', 'GG')['cover']);
        $this->assertSame('Gedung A Lt. 1 (contoh)', collect($rows)->firstWhere('code', 'IGD')['location']);
        $this->assertStringContainsString('Jam layanan dan nomor darurat adalah contoh prototipe', collect($rows)->firstWhere('code', 'IGD')['note']);
    }

    public function test_every_clinic_specialty_exists_in_the_specialty_rows(): void
    {
        $names = array_column(SpecialtySeeder::ROWS, 'name');

        foreach (ClinicSeeder::ROWS as $row) {
            if ($row['specialty'] !== null) {
                $this->assertContains($row['specialty'], $names, $row['name']);
            }
        }
    }

    public function test_doctor_rows_match_13_section_6(): void
    {
        $rows = DoctorSeeder::ROWS;
        $clinicNames = array_column(ClinicSeeder::ROWS, 'name');
        $specialtyNames = array_column(SpecialtySeeder::ROWS, 'name');

        $this->assertCount(18, $rows);
        $this->assertCount(18, array_unique(array_column($rows, 'slug')));
        $this->assertCount(16, array_filter($rows, fn (array $row): bool => $row['status'] === 'active'));
        $this->assertSame(['active', 'inactive', 'archived'], array_values(array_unique(array_column($rows, 'status'))));
        $this->assertCount(16, array_filter($rows, fn (array $row): bool => $row['photo']));
        $this->assertSame([14, 15], array_keys(array_filter($rows, fn (array $row): bool => ! $row['photo'])));
        $this->assertSame(20, array_sum(array_map(fn (array $row): int => 1 + count($row['extra']), $rows)));
        $this->assertSame(['Penyakit Dalam'], collect($rows)->firstWhere('slug', 'maya-kartika-sari')['extra']);
        $this->assertSame(['Bedah Umum'], collect($rows)->firstWhere('slug', 'arif-budiman-santoso')['extra']);

        foreach ($rows as $row) {
            $this->assertContains($row['clinic'], $clinicNames, $row['name']);
            $this->assertContains($row['primary'], $specialtyNames, $row['name']);

            foreach ($row['extra'] as $extra) {
                $this->assertContains($extra, $specialtyNames, $row['name']);
            }
        }

        $this->assertSame('inactive', collect($rows)->firstWhere('slug', 'eko-susanto')['status']);
        $this->assertSame('archived', collect($rows)->firstWhere('slug', 'lilis-marlina')['status']);
        $this->assertNull(collect($rows)->firstWhere('slug', 'rani-oktaviani')['suffix']);
        $this->assertSame('drg.', collect($rows)->firstWhere('slug', 'ayu-larasati-hakim')['prefix']);
    }
}
```

- [ ] **Step 8: Tulis tes yang gagal — guard dan urutan (tool Write) `tests/Feature/Seeding/MasterDataSeederGuardTest.php` dan `tests/Feature/Seeding/DemoSeederWiringTest.php`**

```php
<?php

namespace Tests\Feature\Seeding;

use Database\Seeders\ClinicSeeder;
use Database\Seeders\DoctorSeeder;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * SEED-02 untuk seeder master: hanya `local`/`testing`.
 */
class MasterDataSeederGuardTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{class-string}> */
    public static function seeders(): array
    {
        return [
            'SpecialtySeeder' => [SpecialtySeeder::class],
            'ClinicSeeder' => [ClinicSeeder::class],
            'DoctorSeeder' => [DoctorSeeder::class],
        ];
    }

    #[DataProvider('seeders')]
    public function test_seeder_throws_in_production_and_writes_nothing(string $seeder): void
    {
        $original = $this->app['env'];
        $this->app['env'] = 'production';
        $thrown = null;

        try {
            $this->app->make($seeder)->run();
        } catch (RuntimeException $e) {
            $thrown = $e;
        } finally {
            $this->app['env'] = $original;
        }

        $this->assertInstanceOf(RuntimeException::class, $thrown);
        $this->assertStringContainsString('DILARANG di APP_ENV=production', $thrown->getMessage());
        $this->assertSame(0, DB::table('specialties')->count() + DB::table('clinics')->count() + DB::table('doctors')->count());
    }
}
```

```php
<?php

namespace Tests\Feature\Seeding;

use Tests\TestCase;

class DemoSeederWiringTest extends TestCase
{
    public function test_demo_seeder_runs_master_seeders_in_the_13_section_2_1_order(): void
    {
        $source = (string) file_get_contents(database_path('seeders/DemoSeeder.php'));
        $positions = array_map(
            fn (string $class): int|false => strpos($source, $class.'::class'),
            ['DemoUserSeeder', 'SpecialtySeeder', 'ClinicSeeder', 'DoctorSeeder'],
        );

        $this->assertNotContains(false, $positions);
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions);
    }

    public function test_database_seeder_does_not_call_master_seeders_directly(): void
    {
        $source = (string) file_get_contents(database_path('seeders/DatabaseSeeder.php'));

        foreach (['SpecialtySeeder', 'ClinicSeeder', 'DoctorSeeder'] as $class) {
            $this->assertStringNotContainsString($class, $source);
        }
    }
}
```

- [ ] **Step 9: Tulis tes yang gagal — invarian hasil seeding (tool Write) `tests/Feature/Seeding/DemoSeederInvariantsTest.php`**

```php
<?php

namespace Tests\Feature\Seeding;

use App\Enums\ClinicType;
use App\Enums\DoctorStatus;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Media;
use App\Models\Specialty;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * SEED-01 (bagian master data, fase 2), SEED-03. Kueri K-5/K-23 dari 09 §12; invarian lengkap
 * (`scripts/sql/seed-invariants.sql`) menyusul di fase 9.
 */
class DemoSeederInvariantsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function seedDemo(): void
    {
        $this->seed(DatabaseSeeder::class);
    }

    public function test_master_data_counts_match_the_reference_numbers(): void
    {
        $this->seedDemo();

        $this->assertSame(13, Specialty::query()->count());
        $this->assertSame(17, Clinic::query()->count());
        $this->assertSame(13, Clinic::query()->where('type', ClinicType::Poliklinik->value)->where('is_bookable', true)->count());
        $this->assertSame(4, Clinic::query()->where('type', ClinicType::Penunjang->value)->where('is_bookable', false)->whereNull('specialty_id')->count());
        $this->assertSame(18, Doctor::query()->count());
        $this->assertSame(16, Doctor::query()->where('status', DoctorStatus::Active->value)->count());
        $this->assertSame(1, Doctor::query()->where('status', DoctorStatus::Inactive->value)->count());
        $this->assertSame(1, Doctor::query()->where('status', DoctorStatus::Archived->value)->count());
        $this->assertSame(20, DB::table('doctor_specialties')->count());
        $this->assertSame(18, DB::table('doctor_specialties')->where('is_primary', true)->count());
        $this->assertSame(16, Media::query()->where('collection', 'photo')->count());
        $this->assertSame(16, Media::query()->where('collection', 'cover')->count());
        $this->assertSame(32, Media::query()->count());
        $this->assertSame(16, Doctor::query()->publiclyVisible()->count());
    }

    public function test_every_doctor_has_exactly_one_primary_specialty_and_archive_state_is_consistent(): void
    {
        $this->seedDemo();

        // K-5 dan K-23 (09 §12).
        $this->assertSame([], DB::select('SELECT d.id FROM doctors d LEFT JOIN doctor_specialties ds ON ds.doctor_id = d.id AND ds.is_primary = 1 GROUP BY d.id HAVING COUNT(ds.doctor_id) <> 1'));
        $this->assertSame([], DB::select("SELECT id FROM doctors WHERE (status = 'archived') <> (archived_at IS NOT NULL)"));

        $lilis = Doctor::query()->where('slug', 'lilis-marlina')->firstOrFail();
        $this->assertTrue($lilis->archived_at->isSameDay(now()->subDays(30)));
    }

    public function test_penyakit_dalam_filter_returns_sekar_bagas_and_maya(): void
    {
        $this->seedDemo();

        $this->assertEqualsCanonicalizing(
            ['sekar-ayuningtyas', 'bagas-setiawan-nugroho', 'maya-kartika-sari'],
            Doctor::query()->inSpecialty('penyakit-dalam')->pluck('slug')->all(),
        );
    }

    public function test_doctor_names_and_short_bio_follow_the_templates(): void
    {
        $this->seedDemo();

        $laras = Doctor::query()->where('slug', 'laras-wulandari')->firstOrFail();
        $rani = Doctor::query()->where('slug', 'rani-oktaviani')->firstOrFail();
        $ayu = Doctor::query()->where('slug', 'ayu-larasati-hakim')->firstOrFail();

        $this->assertSame('dr. Laras Wulandari, Sp.A', $laras->display_name);
        $this->assertSame('dr. Rani Oktaviani', $rani->display_name);
        $this->assertSame('drg. Ayu Larasati Hakim', $ayu->display_name);
        $this->assertSame('dr. Laras Wulandari, Sp.A melayani konsultasi Anak di UNPAM HOSPITAL. (Profil contoh).', $laras->short_bio);
        $this->assertSame(2, substr_count((string) $laras->bio, "\n\n") + 1);
        $this->assertSame(1, Doctor::query()->where('slug', 'raka-pradipta-wibowo')->value('sort_order'));
        $this->assertSame(15, Doctor::query()->where('slug', 'teguh-wicaksono')->value('sort_order'));
    }

    public function test_images_are_stored_through_the_media_service_with_alt_text_and_files(): void
    {
        $this->seedDemo();

        foreach (Media::query()->get() as $media) {
            Storage::disk('public')->assertExists($media->directory.'/'.$media->file_name);
            Storage::disk('public')->assertExists((string) $media->thumb_path);
            $this->assertSame('jpg', $media->extension);
            $this->assertStringStartsWith('Foto contoh ', $media->alt_text);
            $this->assertSame($media->collection === 'photo' ? [600, 600] : [1200, 800], [$media->width, $media->height]);
        }

        $raka = Doctor::query()->with('media')->where('slug', 'raka-pradipta-wibowo')->firstOrFail();
        $this->assertSame('Foto contoh dr. Raka Pradipta Wibowo, Sp.U', $raka->firstMedia('photo')->alt_text);

        $withoutPhoto = Doctor::query()->with('media')->whereIn('slug', ['teguh-wicaksono', 'ayu-larasati-hakim'])->get();
        $this->assertCount(2, $withoutPhoto);
        $withoutPhoto->each(fn (Doctor $doctor) => $this->assertNull($doctor->firstMedia('photo')));

        $gigi = Clinic::query()->with('media')->where('code', 'GG')->firstOrFail();
        $this->assertNull($gigi->firstMedia('cover'));
        $this->assertStringContainsString('images/placeholders/clinic.svg', $gigi->cover_url);
        $this->assertSame('Foto contoh Poliklinik Urologi', Clinic::query()->with('media')->where('code', 'URO')->firstOrFail()->firstMedia('cover')->alt_text);
    }

    public function test_media_prune_dry_run_reports_no_orphans_after_seeding(): void
    {
        $this->seedDemo();

        $this->artisan('media:prune', ['--dry-run' => true])
            ->expectsOutputToContain('Baris media yatim: 0')
            ->expectsOutputToContain('Berkas yatim: 0')
            ->assertExitCode(0);
    }

    public function test_seeded_clinic_descriptions_are_sanitized_html_with_no_seo_overrides(): void
    {
        $this->seedDemo();

        foreach (Clinic::query()->get() as $clinic) {
            $this->assertStringContainsString('<p>', (string) $clinic->description, $clinic->code);
            $this->assertStringContainsString('<ul>', (string) $clinic->description, $clinic->code);
            $this->assertStringNotContainsString('<script', (string) $clinic->description);
            $this->assertNull($clinic->meta_title);
            $this->assertNull($clinic->meta_description);
        }

        $this->assertStringContainsString('Jam layanan dan nomor darurat adalah contoh prototipe', (string) Clinic::query()->where('code', 'IGD')->value('description'));
    }

    public function test_demo_seeder_is_idempotent_through_its_guard(): void
    {
        $this->seedDemo();

        $this->artisan('db:seed', ['--class' => 'Database\Seeders\DemoSeeder', '--no-interaction' => true])
            ->expectsOutputToContain('Data demo sudah ada')
            ->assertExitCode(0);

        $this->assertSame([13, 17, 18, 20, 32], [
            Specialty::query()->count(),
            Clinic::query()->count(),
            Doctor::query()->count(),
            DB::table('doctor_specialties')->count(),
            Media::query()->count(),
        ]);
    }

    public function test_each_master_seeder_skips_rows_that_already_exist(): void
    {
        $this->seedDemo();

        foreach (['SpecialtySeeder', 'ClinicSeeder', 'DoctorSeeder'] as $seeder) {
            $this->artisan('db:seed', ['--class' => "Database\\Seeders\\{$seeder}", '--no-interaction' => true])->assertExitCode(0);
        }

        $this->assertSame([13, 17, 18, 20, 32], [
            Specialty::query()->count(),
            Clinic::query()->count(),
            Doctor::query()->count(),
            DB::table('doctor_specialties')->count(),
            Media::query()->count(),
        ]);
    }
}
```

- [ ] **Step 10: Perbarui dua tes lama agar memakai disk palsu (Edit-Text)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
. "$env:TEMP\unpam-edit-text.ps1"

$guard = "$APP\tests\Feature\Seeding\SeederProductionGuardTest.php"
Edit-Text $guard 'use Illuminate\Foundation\Testing\RefreshDatabase;' @'
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
'@
Edit-Text $guard @'
class SeederProductionGuardTest extends TestCase
{
    use RefreshDatabase;
'@ @'
class SeederProductionGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // DemoSeeder membuat 32 gambar contoh (fase 2): jangan menulis ke storage nyata.
        Storage::fake('public');
    }
'@
Edit-Text $guard '$this->assertSame(19, WebsiteSetting::query()->count());' @'
$this->assertSame(19, WebsiteSetting::query()->count());
        $this->assertSame(0, DB::table('specialties')->count() + DB::table('clinics')->count() + DB::table('doctors')->count() + DB::table('media')->count());
'@

$rps = "$APP\tests\Feature\Auth\RolePermissionSeederTest.php"
Edit-Text $rps 'use Illuminate\Support\Facades\Schema;' @'
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
'@
Edit-Text $rps '        $this->seed(DatabaseSeeder::class);' @'
        Storage::fake('public'); // DatabaseSeeder menjalankan DemoSeeder (gambar contoh) di lingkungan testing.
        $this->seed(DatabaseSeeder::class);
'@
Select-String -Path $guard, $rps -Pattern "Storage::fake" | ForEach-Object { "$($_.Filename): $($_.Line.Trim())" }
```
Expected: dua baris `Storage::fake` (satu per berkas). Tambahan di tes produksi membuktikan `DatabaseSeeder` tidak menyentuh data master di luar `local`/`testing`.

- [ ] **Step 11: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="MasterDataSeedRowsTest|MasterDataSeederGuardTest|DemoSeederWiringTest|DemoSeederInvariantsTest"
```
Expected: FAIL — `Class "Database\Seeders\SpecialtySeeder" not found` (tes data/guard), `Failed asserting that ... DemoSeeder` posisi `false` (wiring), dan `Failed asserting that 0 is identical to 13` (invarian).

- [ ] **Step 12: Implementasi seeder spesialisasi (tool Write) `database/seeders/SpecialtySeeder.php`**

```php
<?php

namespace Database\Seeders;

use App\Models\Specialty;
use Illuminate\Database\Seeder;
use RuntimeException;

class SpecialtySeeder extends Seeder
{
    /**
     * 13 §4: 13 spesialisasi, `is_active = true`, `sort_order` = nomor urut. Deskripsi satu kalimat netral
     * (Urologi = kutipan 13 §4; sisanya ditulis di plan ini).
     *
     * @var list<array{name: string, slug: string, description: string}>
     */
    public const ROWS = [
        ['name' => 'Urologi', 'slug' => 'urologi', 'description' => 'Pelayanan kesehatan saluran kemih dan sistem reproduksi pria (contoh).'],
        ['name' => 'Penyakit Dalam', 'slug' => 'penyakit-dalam', 'description' => 'Pelayanan pemeriksaan dan penanganan penyakit organ dalam pada orang dewasa (contoh).'],
        ['name' => 'Anak', 'slug' => 'anak', 'description' => 'Pelayanan kesehatan bayi, anak, dan remaja (contoh).'],
        ['name' => 'Obstetri dan Ginekologi', 'slug' => 'obstetri-dan-ginekologi', 'description' => 'Pelayanan kesehatan ibu hamil dan sistem reproduksi wanita (contoh).'],
        ['name' => 'Bedah Umum', 'slug' => 'bedah-umum', 'description' => 'Pelayanan tindakan bedah serta perawatan sebelum dan sesudah operasi (contoh).'],
        ['name' => 'Jantung', 'slug' => 'jantung', 'description' => 'Pelayanan kesehatan jantung dan pembuluh darah (contoh).'],
        ['name' => 'Saraf', 'slug' => 'saraf', 'description' => 'Pelayanan kesehatan otak, saraf tulang belakang, dan saraf tepi (contoh).'],
        ['name' => 'Mata', 'slug' => 'mata', 'description' => 'Pelayanan pemeriksaan dan perawatan kesehatan mata (contoh).'],
        ['name' => 'THT', 'slug' => 'tht', 'description' => 'Pelayanan kesehatan telinga, hidung, dan tenggorok (contoh).'],
        ['name' => 'Kulit dan Kelamin', 'slug' => 'kulit-dan-kelamin', 'description' => 'Pelayanan kesehatan kulit, rambut, kuku, dan kelamin (contoh).'],
        ['name' => 'Ortopedi', 'slug' => 'ortopedi', 'description' => 'Pelayanan kesehatan tulang, sendi, dan otot (contoh).'],
        ['name' => 'Dokter Umum', 'slug' => 'dokter-umum', 'description' => 'Pelayanan pemeriksaan awal dan konsultasi kesehatan umum (contoh).'],
        ['name' => 'Dokter Gigi', 'slug' => 'dokter-gigi', 'description' => 'Pelayanan kesehatan gigi dan mulut (contoh).'],
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('SpecialtySeeder DILARANG di APP_ENV='.app()->environment().'. Seeder master hanya untuk data demo.');
        }

        foreach (self::ROWS as $index => $row) {
            if (Specialty::query()->where('slug', $row['slug'])->exists()) {
                $this->command?->warn("Spesialisasi {$row['name']} sudah ada, dilewati.");

                continue;
            }

            Specialty::query()->create($row + ['is_active' => true, 'sort_order' => $index + 1]);
        }
    }
}
```

- [ ] **Step 13: Implementasi seeder poliklinik (tool Write) `database/seeders/ClinicSeeder.php`**

```php
<?php

namespace Database\Seeders;

use App\Models\Clinic;
use App\Models\Specialty;
use App\Services\Media\MediaService;
use App\Services\Media\PlaceholderImageGenerator;
use Illuminate\Database\Seeder;
use RuntimeException;

class ClinicSeeder extends Seeder
{
    /**
     * 13 §5: kolom name/slug/code/type/bookable/specialty/location disalin persis dari tabel.
     * `focus`, `items`, dan `note` adalah bahan deskripsi (ditulis di plan ini): `short_description`
     * dan `description` dirangkai oleh `shortDescription()`/`description()`.
     * `cover` false hanya untuk GG (menguji fallback placeholder SVG).
     *
     * @var list<array{name: string, slug: string, code: string, type: string, bookable: bool, specialty: ?string, location: string, focus: string, items: list<string>, note: ?string, cover: bool}>
     */
    public const ROWS = [
        ['name' => 'Poliklinik Urologi', 'slug' => 'poliklinik-urologi', 'code' => 'URO', 'type' => 'poliklinik', 'bookable' => true, 'specialty' => 'Urologi', 'location' => 'Gedung A Lt. 1', 'focus' => 'keluhan saluran kemih dan sistem reproduksi pria', 'items' => ['Konsultasi keluhan saluran kemih', 'Pemeriksaan awal dan rujukan pemeriksaan penunjang', 'Edukasi kesehatan pasien'], 'note' => null, 'cover' => true],
        ['name' => 'Poliklinik Penyakit Dalam', 'slug' => 'poliklinik-penyakit-dalam', 'code' => 'PD', 'type' => 'poliklinik', 'bookable' => true, 'specialty' => 'Penyakit Dalam', 'location' => 'Gedung A Lt. 1', 'focus' => 'keluhan penyakit organ dalam pada orang dewasa', 'items' => ['Konsultasi penyakit dalam', 'Pemantauan penyakit kronis', 'Edukasi gaya hidup sehat'], 'note' => null, 'cover' => true],
        ['name' => 'Poliklinik Anak', 'slug' => 'poliklinik-anak', 'code' => 'ANK', 'type' => 'poliklinik', 'bookable' => true, 'specialty' => 'Anak', 'location' => 'Gedung A Lt. 1', 'focus' => 'kesehatan bayi, anak, dan remaja', 'items' => ['Konsultasi tumbuh kembang anak', 'Pemeriksaan keluhan anak', 'Edukasi orang tua'], 'note' => null, 'cover' => true],
        ['name' => 'Poliklinik Kandungan', 'slug' => 'poliklinik-kandungan', 'code' => 'OBG', 'type' => 'poliklinik', 'bookable' => true, 'specialty' => 'Obstetri dan Ginekologi', 'location' => 'Gedung A Lt. 2', 'focus' => 'kesehatan ibu hamil dan sistem reproduksi wanita', 'items' => ['Konsultasi kehamilan', 'Pemeriksaan kesehatan reproduksi wanita', 'Edukasi ibu dan keluarga'], 'note' => null, 'cover' => true],
        ['name' => 'Poliklinik Bedah Umum', 'slug' => 'poliklinik-bedah-umum', 'code' => 'BDH', 'type' => 'poliklinik', 'bookable' => true, 'specialty' => 'Bedah Umum', 'location' => 'Gedung A Lt. 2', 'focus' => 'keluhan yang memerlukan penilaian bedah', 'items' => ['Konsultasi sebelum tindakan bedah', 'Kontrol setelah tindakan', 'Perawatan luka sederhana'], 'note' => null, 'cover' => true],
        ['name' => 'Poliklinik Jantung', 'slug' => 'poliklinik-jantung', 'code' => 'JTG', 'type' => 'poliklinik', 'bookable' => true, 'specialty' => 'Jantung', 'location' => 'Gedung B Lt. 1', 'focus' => 'kesehatan jantung dan pembuluh darah', 'items' => ['Konsultasi keluhan jantung', 'Pemantauan tekanan darah', 'Edukasi pencegahan penyakit jantung'], 'note' => null, 'cover' => true],
        ['name' => 'Poliklinik Saraf', 'slug' => 'poliklinik-saraf', 'code' => 'SRF', 'type' => 'poliklinik', 'bookable' => true, 'specialty' => 'Saraf', 'location' => 'Gedung B Lt. 1', 'focus' => 'keluhan otak, saraf tulang belakang, dan saraf tepi', 'items' => ['Konsultasi keluhan saraf', 'Pemeriksaan awal gangguan saraf', 'Edukasi pasien dan keluarga'], 'note' => null, 'cover' => true],
        ['name' => 'Poliklinik Mata', 'slug' => 'poliklinik-mata', 'code' => 'MTA', 'type' => 'poliklinik', 'bookable' => true, 'specialty' => 'Mata', 'location' => 'Gedung B Lt. 2', 'focus' => 'kesehatan mata', 'items' => ['Konsultasi keluhan mata', 'Pemeriksaan ketajaman penglihatan', 'Edukasi kesehatan mata'], 'note' => null, 'cover' => true],
        ['name' => 'Poliklinik THT', 'slug' => 'poliklinik-tht', 'code' => 'THT', 'type' => 'poliklinik', 'bookable' => true, 'specialty' => 'THT', 'location' => 'Gedung B Lt. 2', 'focus' => 'kesehatan telinga, hidung, dan tenggorok', 'items' => ['Konsultasi keluhan THT', 'Pemeriksaan telinga, hidung, dan tenggorok', 'Edukasi perawatan harian'], 'note' => null, 'cover' => true],
        ['name' => 'Poliklinik Kulit dan Kelamin', 'slug' => 'poliklinik-kulit-dan-kelamin', 'code' => 'KK', 'type' => 'poliklinik', 'bookable' => true, 'specialty' => 'Kulit dan Kelamin', 'location' => 'Gedung B Lt. 2', 'focus' => 'kesehatan kulit, rambut, kuku, dan kelamin', 'items' => ['Konsultasi keluhan kulit', 'Pemeriksaan awal masalah kulit dan kelamin', 'Edukasi perawatan kulit'], 'note' => null, 'cover' => true],
        ['name' => 'Poliklinik Ortopedi', 'slug' => 'poliklinik-ortopedi', 'code' => 'ORT', 'type' => 'poliklinik', 'bookable' => true, 'specialty' => 'Ortopedi', 'location' => 'Gedung C Lt. 1', 'focus' => 'kesehatan tulang, sendi, dan otot', 'items' => ['Konsultasi keluhan tulang dan sendi', 'Pemeriksaan awal cedera', 'Edukasi pemulihan'], 'note' => null, 'cover' => true],
        ['name' => 'Poliklinik Umum', 'slug' => 'poliklinik-umum', 'code' => 'UMM', 'type' => 'poliklinik', 'bookable' => true, 'specialty' => 'Dokter Umum', 'location' => 'Gedung A Lt. 1', 'focus' => 'keluhan kesehatan umum', 'items' => ['Pemeriksaan awal keluhan umum', 'Konsultasi kesehatan', 'Rujukan ke poliklinik spesialis'], 'note' => null, 'cover' => true],
        ['name' => 'Poliklinik Gigi', 'slug' => 'poliklinik-gigi', 'code' => 'GG', 'type' => 'poliklinik', 'bookable' => true, 'specialty' => 'Dokter Gigi', 'location' => 'Gedung C Lt. 1', 'focus' => 'kesehatan gigi dan mulut', 'items' => ['Konsultasi keluhan gigi dan mulut', 'Pemeriksaan gigi', 'Edukasi kebersihan mulut'], 'note' => null, 'cover' => false],
        ['name' => 'Laboratorium', 'slug' => 'laboratorium', 'code' => 'LAB', 'type' => 'penunjang', 'bookable' => false, 'specialty' => null, 'location' => 'Gedung C Lt. 1', 'focus' => 'pemeriksaan laboratorium untuk menunjang diagnosis dokter', 'items' => ['Pemeriksaan darah rutin', 'Pemeriksaan urine', 'Pengambilan sampel sesuai permintaan dokter'], 'note' => null, 'cover' => true],
        ['name' => 'Radiologi', 'slug' => 'radiologi', 'code' => 'RAD', 'type' => 'penunjang', 'bookable' => false, 'specialty' => null, 'location' => 'Gedung C Lt. 1', 'focus' => 'pemeriksaan pencitraan untuk menunjang diagnosis dokter', 'items' => ['Rontgen', 'Pemeriksaan USG', 'Pembacaan hasil oleh dokter'], 'note' => null, 'cover' => true],
        ['name' => 'Farmasi', 'slug' => 'farmasi', 'code' => 'FAR', 'type' => 'penunjang', 'bookable' => false, 'specialty' => null, 'location' => 'Gedung A Lt. 1', 'focus' => 'pelayanan obat sesuai resep dokter', 'items' => ['Penyiapan obat sesuai resep', 'Informasi cara penggunaan obat', 'Konsultasi singkat dengan apoteker'], 'note' => null, 'cover' => true],
        ['name' => 'IGD 24 Jam', 'slug' => 'igd-24-jam', 'code' => 'IGD', 'type' => 'penunjang', 'bookable' => false, 'specialty' => null, 'location' => 'Gedung A Lt. 1 (contoh)', 'focus' => 'penanganan awal kondisi gawat darurat', 'items' => ['Penilaian awal kondisi pasien', 'Penanganan awal gawat darurat', 'Rujukan ke layanan lanjutan'], 'note' => 'Jam layanan dan nomor darurat adalah contoh prototipe.', 'cover' => true],
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('ClinicSeeder DILARANG di APP_ENV='.app()->environment().'. Seeder master hanya untuk data demo.');
        }

        $specialties = Specialty::query()->get()->keyBy('name');
        $media = app(MediaService::class);
        $generator = app(PlaceholderImageGenerator::class);

        foreach (self::ROWS as $index => $row) {
            if (Clinic::query()->where('slug', $row['slug'])->exists()) {
                $this->command?->warn("Layanan {$row['name']} sudah ada, dilewati.");

                continue;
            }

            $specialty = $row['specialty'] === null ? null : $specialties->get($row['specialty']);

            if ($row['specialty'] !== null && $specialty === null) {
                throw new RuntimeException("Spesialisasi {$row['specialty']} belum ada. Jalankan SpecialtySeeder lebih dulu.");
            }

            $clinic = Clinic::query()->create([
                'name' => $row['name'],
                'slug' => $row['slug'],
                'code' => $row['code'],
                'type' => $row['type'],
                'is_bookable' => $row['bookable'],
                'specialty_id' => $specialty?->id,
                'short_description' => self::shortDescription($row),
                'description' => self::description($row),
                'location' => $row['location'],
                'is_active' => true,
                'sort_order' => $index + 1,
                'meta_title' => null,
                'meta_description' => null,
            ]);

            if ($row['cover']) {
                $file = $generator->make('cover', $row['code'], 1200, 800);

                try {
                    $media->store($file, 'cover', $clinic, ['alt_text' => 'Foto contoh '.$row['name']]);
                } finally {
                    @unlink($file->getPathname());
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function shortDescription(array $row): string
    {
        return $row['type'] === 'poliklinik'
            ? 'Konsultasi dan pemeriksaan '.$row['focus'].' (contoh).'
            : ucfirst($row['focus']).' (contoh).';
    }

    /**
     * HTML pendek `<p>` + `<ul>`; disanitasi oleh trait SanitizesRichText saat disimpan.
     *
     * @param  array<string, mixed>  $row
     */
    public static function description(array $row): string
    {
        $intro = $row['type'] === 'poliklinik'
            ? $row['name'].' melayani konsultasi dan pemeriksaan '.$row['focus'].' di UNPAM HOSPITAL.'
            : $row['name'].' menyediakan '.$row['focus'].' di UNPAM HOSPITAL.';

        $html = '<p>'.e($intro).' Keterangan pada halaman ini adalah data contoh untuk prototipe.</p><ul>';

        foreach ($row['items'] as $item) {
            $html .= '<li>'.e($item).'</li>';
        }

        $html .= '</ul>';

        if ($row['note'] !== null) {
            $html .= '<p>'.e($row['note']).'</p>';
        }

        return $html;
    }
}
```

- [ ] **Step 14: Implementasi seeder dokter (tool Write) `database/seeders/DoctorSeeder.php`**

```php
<?php

namespace Database\Seeders;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Specialty;
use App\Services\Media\MediaService;
use App\Services\Media\PlaceholderImageGenerator;
use Illuminate\Database\Seeder;
use RuntimeException;

class DoctorSeeder extends Seeder
{
    /**
     * 13 §6, 18 dokter fiktif (disalin persis). `slug` = Str::slug(name). `extra` = spesialisasi tambahan
     * (2 baris sekunder: #8 dan #13 ⇒ 20 baris `doctor_specialties`). `photo` false untuk #15 dan #16.
     * #17 `inactive`; #18 `archived` dengan `archived_at` = hari ini − 30 hari.
     *
     * @var list<array{name: string, slug: string, prefix: string, suffix: ?string, gender: string, primary: string, extra: list<string>, clinic: string, status: string, photo: bool}>
     */
    public const ROWS = [
        ['name' => 'Raka Pradipta Wibowo', 'slug' => 'raka-pradipta-wibowo', 'prefix' => 'dr.', 'suffix' => 'Sp.U', 'gender' => 'L', 'primary' => 'Urologi', 'extra' => [], 'clinic' => 'Poliklinik Urologi', 'status' => 'active', 'photo' => true],
        ['name' => 'Sekar Ayuningtyas', 'slug' => 'sekar-ayuningtyas', 'prefix' => 'dr.', 'suffix' => 'Sp.PD', 'gender' => 'P', 'primary' => 'Penyakit Dalam', 'extra' => [], 'clinic' => 'Poliklinik Penyakit Dalam', 'status' => 'active', 'photo' => true],
        ['name' => 'Bagas Setiawan Nugroho', 'slug' => 'bagas-setiawan-nugroho', 'prefix' => 'dr.', 'suffix' => 'Sp.PD', 'gender' => 'L', 'primary' => 'Penyakit Dalam', 'extra' => [], 'clinic' => 'Poliklinik Penyakit Dalam', 'status' => 'active', 'photo' => true],
        ['name' => 'Laras Wulandari', 'slug' => 'laras-wulandari', 'prefix' => 'dr.', 'suffix' => 'Sp.A', 'gender' => 'P', 'primary' => 'Anak', 'extra' => [], 'clinic' => 'Poliklinik Anak', 'status' => 'active', 'photo' => true],
        ['name' => 'Dimas Aryaputra', 'slug' => 'dimas-aryaputra', 'prefix' => 'dr.', 'suffix' => 'Sp.A', 'gender' => 'L', 'primary' => 'Anak', 'extra' => [], 'clinic' => 'Poliklinik Anak', 'status' => 'active', 'photo' => true],
        ['name' => 'Nadira Kusumawardani', 'slug' => 'nadira-kusumawardani', 'prefix' => 'dr.', 'suffix' => 'Sp.OG', 'gender' => 'P', 'primary' => 'Obstetri dan Ginekologi', 'extra' => [], 'clinic' => 'Poliklinik Kandungan', 'status' => 'active', 'photo' => true],
        ['name' => 'Hendra Mahardika', 'slug' => 'hendra-mahardika', 'prefix' => 'dr.', 'suffix' => 'Sp.B', 'gender' => 'L', 'primary' => 'Bedah Umum', 'extra' => [], 'clinic' => 'Poliklinik Bedah Umum', 'status' => 'active', 'photo' => true],
        ['name' => 'Maya Kartika Sari', 'slug' => 'maya-kartika-sari', 'prefix' => 'dr.', 'suffix' => 'Sp.JP', 'gender' => 'P', 'primary' => 'Jantung', 'extra' => ['Penyakit Dalam'], 'clinic' => 'Poliklinik Jantung', 'status' => 'active', 'photo' => true],
        ['name' => 'Fajar Ramadhan Lubis', 'slug' => 'fajar-ramadhan-lubis', 'prefix' => 'dr.', 'suffix' => 'Sp.N', 'gender' => 'L', 'primary' => 'Saraf', 'extra' => [], 'clinic' => 'Poliklinik Saraf', 'status' => 'active', 'photo' => true],
        ['name' => 'Intan Permatasari', 'slug' => 'intan-permatasari', 'prefix' => 'dr.', 'suffix' => 'Sp.M', 'gender' => 'P', 'primary' => 'Mata', 'extra' => [], 'clinic' => 'Poliklinik Mata', 'status' => 'active', 'photo' => true],
        ['name' => 'Yoga Prasetya', 'slug' => 'yoga-prasetya', 'prefix' => 'dr.', 'suffix' => 'Sp.THT-KL', 'gender' => 'L', 'primary' => 'THT', 'extra' => [], 'clinic' => 'Poliklinik THT', 'status' => 'active', 'photo' => true],
        ['name' => 'Citra Anindya Putri', 'slug' => 'citra-anindya-putri', 'prefix' => 'dr.', 'suffix' => 'Sp.DV', 'gender' => 'P', 'primary' => 'Kulit dan Kelamin', 'extra' => [], 'clinic' => 'Poliklinik Kulit dan Kelamin', 'status' => 'active', 'photo' => true],
        ['name' => 'Arif Budiman Santoso', 'slug' => 'arif-budiman-santoso', 'prefix' => 'dr.', 'suffix' => 'Sp.OT', 'gender' => 'L', 'primary' => 'Ortopedi', 'extra' => ['Bedah Umum'], 'clinic' => 'Poliklinik Ortopedi', 'status' => 'active', 'photo' => true],
        ['name' => 'Rani Oktaviani', 'slug' => 'rani-oktaviani', 'prefix' => 'dr.', 'suffix' => null, 'gender' => 'P', 'primary' => 'Dokter Umum', 'extra' => [], 'clinic' => 'Poliklinik Umum', 'status' => 'active', 'photo' => true],
        ['name' => 'Teguh Wicaksono', 'slug' => 'teguh-wicaksono', 'prefix' => 'dr.', 'suffix' => null, 'gender' => 'L', 'primary' => 'Dokter Umum', 'extra' => [], 'clinic' => 'Poliklinik Umum', 'status' => 'active', 'photo' => false],
        ['name' => 'Ayu Larasati Hakim', 'slug' => 'ayu-larasati-hakim', 'prefix' => 'drg.', 'suffix' => null, 'gender' => 'P', 'primary' => 'Dokter Gigi', 'extra' => [], 'clinic' => 'Poliklinik Gigi', 'status' => 'active', 'photo' => false],
        ['name' => 'Eko Susanto', 'slug' => 'eko-susanto', 'prefix' => 'dr.', 'suffix' => 'Sp.B', 'gender' => 'L', 'primary' => 'Bedah Umum', 'extra' => [], 'clinic' => 'Poliklinik Bedah Umum', 'status' => 'inactive', 'photo' => true],
        ['name' => 'Lilis Marlina', 'slug' => 'lilis-marlina', 'prefix' => 'dr.', 'suffix' => 'Sp.M', 'gender' => 'P', 'primary' => 'Mata', 'extra' => [], 'clinic' => 'Poliklinik Mata', 'status' => 'archived', 'photo' => true],
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('DoctorSeeder DILARANG di APP_ENV='.app()->environment().'. Seeder master hanya untuk data demo.');
        }

        $clinics = Clinic::query()->get()->keyBy('name');
        $specialties = Specialty::query()->get()->keyBy('name');
        $media = app(MediaService::class);
        $generator = app(PlaceholderImageGenerator::class);

        foreach (self::ROWS as $index => $row) {
            if (Doctor::query()->where('slug', $row['slug'])->exists()) {
                $this->command?->warn("Dokter {$row['name']} sudah ada, dilewati.");

                continue;
            }

            $clinic = $clinics->get($row['clinic']) ?? throw new RuntimeException("Poliklinik {$row['clinic']} belum ada. Jalankan ClinicSeeder lebih dulu.");
            $primary = $specialties->get($row['primary']) ?? throw new RuntimeException("Spesialisasi {$row['primary']} belum ada. Jalankan SpecialtySeeder lebih dulu.");

            // Semua kunci diisi (mode ketat): display_name membaca title_prefix/name/title_suffix sebelum disimpan.
            $doctor = new Doctor([
                'clinic_id' => $clinic->id,
                'name' => $row['name'],
                'slug' => $row['slug'],
                'title_prefix' => $row['prefix'],
                'title_suffix' => $row['suffix'],
                'gender' => $row['gender'],
                'short_bio' => null,
                'bio' => null,
                'status' => $row['status'],
                'sort_order' => $index + 1,
                'meta_title' => null,
                'meta_description' => null,
            ]);
            $displayName = $doctor->display_name;
            $doctor->short_bio = "{$displayName} melayani konsultasi {$row['primary']} di UNPAM HOSPITAL. (Profil contoh).";
            $doctor->bio = "{$displayName} adalah dokter contoh yang melayani konsultasi {$row['primary']} di UNPAM HOSPITAL. Profil ini dibuat untuk keperluan prototipe dan tidak menggambarkan orang sungguhan.\n\nInformasi pendidikan, pengalaman, dan jadwal praktik pada profil ini belum diisi. Data akan dilengkapi pada tahap pengembangan berikutnya.";
            $doctor->save();

            if ($row['status'] === 'archived') {
                $doctor->forceFill(['archived_at' => now()->subDays(30)])->save();
            }

            $pivot = [$primary->id => ['is_primary' => true]];

            foreach ($row['extra'] as $extraName) {
                $extra = $specialties->get($extraName) ?? throw new RuntimeException("Spesialisasi {$extraName} belum ada.");
                $pivot[$extra->id] = ['is_primary' => false];
            }

            $doctor->specialties()->attach($pivot);

            if ($row['photo']) {
                $file = $generator->make('photo', self::initials($row['name']), 600, 600);

                try {
                    $media->store($file, 'photo', $doctor, ['alt_text' => 'Foto contoh '.$displayName]);
                } finally {
                    @unlink($file->getPathname());
                }
            }
        }
    }

    /** Dua huruf pertama dari dua kata pertama nama, mis. "Raka Pradipta Wibowo" ⇒ "RP". */
    private static function initials(string $name): string
    {
        $words = array_values(array_filter(explode(' ', $name)));

        return strtoupper(mb_substr($words[0], 0, 1).mb_substr($words[1] ?? '', 0, 1));
    }
}
```

- [ ] **Step 15: Hubungkan ke `DemoSeeder` (Edit-Text)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
. "$env:TEMP\unpam-edit-text.ps1"
Edit-Text "$APP\database\seeders\DemoSeeder.php" '$this->call([DemoUserSeeder::class]);' @'
$this->call([
            DemoUserSeeder::class,
            SpecialtySeeder::class,
            ClinicSeeder::class,
            DoctorSeeder::class,
        ]);
'@
Select-String -Path database\seeders\DemoSeeder.php -Pattern 'SpecialtySeeder', 'DoctorSeeder' | ForEach-Object { $_.Line.Trim() }
```
Expected: `SpecialtySeeder::class,` dan `DoctorSeeder::class,`.

- [ ] **Step 16: Jalankan tes — harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="MasterDataSeedRowsTest|MasterDataSeederGuardTest|DemoSeederWiringTest|DemoSeederInvariantsTest|SeederProductionGuardTest|RolePermissionSeederTest|DemoUserSeederTest"
```
Expected: PASS. Tes invarian memakai ±10–30 detik total (tiap tes menyeed 32 gambar). Jika `test_doctor_names_and_short_bio_follow_the_templates` gagal pada kalimat akhir: template 13 §6 berakhir "(Profil contoh)." — seeder menulis titik penutup, tes memeriksa `assertStringEndsWith('(Profil contoh).')`.

- [ ] **Step 17: Seluruh suite, Pint, commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test
& $PHP vendor\bin\pint --test
git status --short
git add database/seeders/SpecialtySeeder.php database/seeders/ClinicSeeder.php database/seeders/DoctorSeeder.php database/seeders/DemoSeeder.php tests/Unit/MasterDataSeedRowsTest.php tests/Feature/Seeding/MasterDataSeederGuardTest.php tests/Feature/Seeding/DemoSeederWiringTest.php tests/Feature/Seeding/DemoSeederInvariantsTest.php tests/Feature/Seeding/SeederProductionGuardTest.php tests/Feature/Auth/RolePermissionSeederTest.php
git commit -m @'
feat(fase-2): seeder demo spesialisasi, poliklinik, dan dokter (13 §4-6) dengan gambar contoh

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
'@
```
Expected: seluruh tes PASS; Pint `PASS`; `git status` sebelum `git add` hanya memuat berkas Task 14.

**Catatan konflik / celah spesifikasi:** (1) 13 tidak menetapkan teks `description` spesialisasi/poliklinik dan `bio` dokter — ditulis di sini sebagai data contoh netral. (2) 13 §9.2 menyebut font TTF dibundel; tanpa paket baru dan tanpa sumber lisensi di repo, font **tidak** dibundel dan generator memakai jalur `imagestring()` (sudah diizinkan 13 [ASUMSI]); folder `resources/fonts` dapat ditambahkan nanti tanpa mengubah kode (`new PlaceholderImageGenerator($path)` lewat binding bila diinginkan). (3) 09 §15.1 menyebut "±22" baris `doctor_specialties`; dipakai 20 (13 §6). (4) Seeder menulis `Doctor` langsung, bukan lewat `DoctorService` (F2-R20 berlaku untuk jalur aplikasi, bukan data demo).

---

### Task 15: Poles hasil uji manual fase 1 (login, ganti password, footer admin)

**Files:**
- Modify: `resources/views/components/layouts/auth.blade.php`, `resources/views/auth/admin-login.blade.php`, `resources/views/auth/admin-change-password.blade.php`, `resources/views/components/layouts/admin.blade.php`, `resources/scss/_shared.scss`, `resources/scss/_admin.scss`
- Test: `tests/Feature/View/AuthPolishTest.php`

**Interfaces:**
- Consumes: layout `x-layouts.auth` (props `title`, `subtitle`; slot `footer`), `x-form.input` (atribut tambahan diteruskan ke `<input>`), `x-loading-button`, layout admin, `Tests\Support\InteractsWithStaff`, `UserFactory::staff()/withRole()/mustChangePassword()`.
- Produces (perilaku, tanpa nama PHP baru):
  - Layout auth: satu permukaan (`.auth-card`, tanpa `.card` bersarang); merek = **satu** tautan berisi logo besar (`<img class="auth-logo" height="64" alt="{nama situs}">`) tanpa teks nama situs terpisah (logo placeholder sudah memuat teks "UNPAM HOSPITAL"; `alt` membawa nama bagi pembaca layar).
  - Login admin: `autofocus` pada email, kecuali bila ada galat validasi (ringkasan galat menerima fokus lewat `app.js`).
  - Ganti password: pesan wajib ganti ("Anda harus mengganti password sementara sebelum melanjutkan.") tampil **sekali** (flash `warning` bila ada; subjudul hanya sebagai cadangan saat halaman dibuka langsung); field `username` tersembunyi (`type="text" name="username" autocomplete="username" readonly hidden`, nilai email akun) sebelum field password agar pengelola password peramban tidak memperingatkan.
  - Layout admin: kolom isi `.admin-column` (`min-height: 100vh`/`100dvh`) dengan footer `mt-auto` sehingga footer menempel di dasar layar pendek dan mobile.
  - Kelas SCSS baru: `.auth-brand`, `.auth-logo`, `.admin-column`.

**Temuan uji manual fase 1 dan perbaikannya (sumber: CHECKPOINT §5, F1 Hardening):**

| # | Temuan | Perbaikan | Penjaga tes |
|---|---|---|---|
| 1 | Logo login kecil (48×48 memaksa `logo.svg` rasio 4:1 menjadi ±48×12) dan teks nama situs ganda di sebelahnya | Logo tinggi 64 px lebar otomatis, satu tautan, tanpa `<span>` nama | `test_login_brand_is_one_large_logo_link_without_a_duplicated_site_name` |
| 2 | Formulir di dalam kartu yang bersarang di `.auth-card` | `<main>` tanpa `.card`/`.card-body`; `.auth-card` jadi satu-satunya permukaan | `test_auth_pages_have_a_single_card_surface` |
| 3 | Pesan "Anda harus mengganti password sementara…" muncul dua kali (subjudul + alert) | Subjudul memakai teks cadangan bila flash `warning` yang sama ada | `test_forced_message_appears_once_with_or_without_the_flash` |
| 4 | Tidak ada `autofocus` pada email login | `autofocus` pada email bila tidak ada galat validasi | `test_login_email_is_autofocused_unless_there_are_errors` |
| 5 | Chrome: form password butuh field `username` tersembunyi | `<input type="text" name="username" autocomplete="username" readonly hidden>` | `test_change_password_form_has_a_hidden_username_field` |
| 6 | Footer admin tidak menempel di dasar pada halaman pendek/mobile | Kolom isi `min-height` penuh + `mt-auto` pada footer | `test_admin_footer_is_pushed_to_the_bottom_of_short_pages` |

**Test IDs:** penjaga regresi `A11` (tidak ada kehilangan label/heading), `RSP-04` (mobile), catatan manual fase 1 #1–#2 (Hardening). Penjaga ditaruh di berkas baru `AuthPolishTest` (bukan menambah `LayoutsTest`/`AdminLoginTest`) agar suite lama tidak diubah; seluruh tes lama harus tetap hijau.

**Catatan teknis (diverifikasi dari source):**
- `LayoutsTest` mensyaratkan `auth-card`, `<main id="konten-utama" tabindex="-1"`, `images/placeholders/logo.svg`, satu `<h1>`, dan "Kembali ke beranda": semuanya dipertahankan.
- `AdminPasswordChangeTest::test_edit_page_is_reachable_while_forced_and_explains_why` memeriksa teks wajib ganti pada GET tanpa flash: subjudul cadangan tetap menampilkannya.
- `x-form.input` meneruskan atribut tak dikenal lewat `$attributes` (`:autofocus="false"` tidak dirender; `true` dirender `autofocus="autofocus"`).
- `UpdatePasswordRequest` hanya memakai `validated()` ⇒ field `username` diabaikan; `username` tidak ada di daftar `dontFlash` tetapi bukan data sensitif (email akun sendiri).
- Pengaturan `site.logo_media_id` bisa memuat logo kustom: `height="64"` + `width: auto` menjaga rasio apa pun.
- Logo di header publik (`x-layouts.public`) dan sidebar admin memiliki cacat serupa (lebar = tinggi); di luar temuan fase 1 dan tidak diubah di sini.

- [ ] **Step 1: Verifikasi prasyarat**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git branch --show-current
git status --short
Test-Path "$env:TEMP\unpam-edit-text.ps1"
& $PHP artisan test --filter="LayoutsTest|AdminLoginTest|AdminPasswordChangeTest|AdminLayoutTest"
```
Expected: `fase-2-master-data`; bersih; `True` (bila `False`, buat ulang dari Task 13 Step 1); tes PASS.

- [ ] **Step 2: Tulis tes yang gagal (tool Write) `tests/Feature/View/AuthPolishTest.php`**

```php
<?php

namespace Tests\Feature\View;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * Penjaga regresi poles hasil uji manual fase 1 (login, ganti password, footer admin).
 */
class AuthPolishTest extends TestCase
{
    use InteractsWithStaff;
    use RefreshDatabase;

    private const FORCED_MESSAGE = 'Anda harus mengganti password sementara sebelum melanjutkan.';

    private function forcedStaff(): User
    {
        $this->seedRoles();

        return User::factory()->staff()->withRole('content-admin')->mustChangePassword()->create();
    }

    // 1. Logo besar dan nama tidak ganda -------------------------------------------------------

    public function test_login_brand_is_one_large_logo_link_without_a_duplicated_site_name(): void
    {
        $html = $this->get(route('admin.login'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '#<a class="auth-brand" href="[^"]*">\s*<img class="auth-logo" src="[^"]*logo\.svg" data-fallback="[^"]*" alt="'.preg_quote(config('app.name'), '#').'" height="64">\s*</a>#',
            $html,
        );
        $this->assertStringNotContainsString('width="48" height="48"', $html);
        $this->assertStringNotContainsString('<span class="h5 mb-0 fw-bold text-primary">', $html);
    }

    // 2. Satu kartu ----------------------------------------------------------------------------

    public function test_auth_pages_have_a_single_card_surface(): void
    {
        $pages = [
            route('admin.login') => null,
            route('admin.password.edit') => $this->forcedStaff(),
        ];

        foreach ($pages as $uri => $user) {
            if ($user !== null) {
                $this->actingAs($user);
            }

            $html = $this->get($uri)->assertOk()->getContent();

            $this->assertSame(1, substr_count($html, 'auth-card'), "Harus ada tepat satu permukaan auth-card di {$uri}.");
            $this->assertSame(0, preg_match('/class="(?:[^"]* )?card(?: [^"]*)?"/', $html), "Kartu bersarang (.card) ditemukan di {$uri}.");
            $this->assertStringNotContainsString('card-body', $html);
        }
    }

    // 3. Pesan wajib ganti hanya sekali --------------------------------------------------------

    public function test_forced_message_appears_once_with_or_without_the_flash(): void
    {
        $staff = $this->forcedStaff();

        $direct = $this->actingAs($staff)->get(route('admin.password.edit'))->assertOk()->getContent();
        $this->assertSame(1, substr_count($direct, self::FORCED_MESSAGE), 'Tanpa flash, subjudul menampilkan pesan.');

        $redirected = $this->actingAs($staff)->withSession(['warning' => self::FORCED_MESSAGE])->get(route('admin.password.edit'))->assertOk()->getContent();
        $this->assertSame(1, substr_count($redirected, self::FORCED_MESSAGE), 'Dengan flash, hanya alert yang menampilkan pesan.');
        $this->assertStringContainsString('data-flash="warning"', $redirected);
        $this->assertStringContainsString('Gunakan password yang kuat', $redirected);
    }

    public function test_regular_password_change_has_no_forced_message(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.password.edit'))
            ->assertOk()
            ->assertDontSee(self::FORCED_MESSAGE);
    }

    // 4. Autofocus -----------------------------------------------------------------------------

    public function test_login_email_is_autofocused_unless_there_are_errors(): void
    {
        $html = $this->get(route('admin.login'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<input[^>]*name="email"[^>]*\sautofocus/', $html);
        $this->assertSame(1, substr_count($html, 'autofocus'));

        $failed = $this->from(route('admin.login'))->followingRedirects()
            ->post(route('admin.login.store'), ['email' => 'tidak.ada@unpam.test', 'password' => 'Salah12345'])
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-errors-summary', $failed);
        $this->assertStringNotContainsString('autofocus', $failed);
    }

    // 5. Field username tersembunyi ------------------------------------------------------------

    public function test_change_password_form_has_a_hidden_username_field(): void
    {
        $staff = $this->forcedStaff();

        $html = $this->actingAs($staff)->get(route('admin.password.edit'))->assertOk()->getContent();

        $this->assertStringContainsString('<input type="text" name="username" value="'.e($staff->email).'" autocomplete="username" readonly hidden>', $html);
        $this->assertLessThan(strpos($html, 'name="current_password"'), strpos($html, 'name="username"'));
    }

    public function test_hidden_username_does_not_break_the_password_change(): void
    {
        $this->seedRoles();
        $staff = User::factory()->staff()->withRole('content-admin')->mustChangePassword()->create(['password' => 'Sementara123']);

        $this->actingAs($staff)->put(route('admin.password.update'), [
            'username' => $staff->email,
            'current_password' => 'Sementara123',
            'password' => 'BaruAman456',
            'password_confirmation' => 'BaruAman456',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertFalse($staff->fresh()->must_change_password);
    }

    // 6. Footer admin di dasar -----------------------------------------------------------------

    public function test_admin_footer_is_pushed_to_the_bottom_of_short_pages(): void
    {
        $html = $this->actingAs($this->createStaff('content-admin'))->get(route('admin.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('<div class="admin-column flex-grow-1 d-flex flex-column">', $html);
        $this->assertMatchesRegularExpression('/<footer class="[^"]*\bmt-auto\b[^"]*"/', $html);

        $scss = (string) file_get_contents(resource_path('scss/_admin.scss'));
        $this->assertMatchesRegularExpression('/\.admin-column\s*\{[^}]*min-height:\s*100vh;[^}]*min-height:\s*100dvh;/s', $scss);
    }

    public function test_auth_logo_styles_exist_in_the_shared_stylesheet(): void
    {
        $scss = (string) file_get_contents(resource_path('scss/_shared.scss'));

        $this->assertMatchesRegularExpression('/\.auth-logo\s*\{[^}]*height:\s*4rem;[^}]*width:\s*auto;/s', $scss);
        $this->assertStringContainsString('.auth-brand', $scss);
    }
}
```

- [ ] **Step 3: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=AuthPolishTest
```
Expected: FAIL pada `test_login_brand_is_one_large_logo_link_without_a_duplicated_site_name`, `test_auth_pages_have_a_single_card_surface` (kartu bersarang), `test_forced_message_appears_once_with_or_without_the_flash` (2 ≠ 1), `test_login_email_is_autofocused_unless_there_are_errors`, `test_change_password_form_has_a_hidden_username_field`, `test_admin_footer_is_pushed_to_the_bottom_of_short_pages`, `test_auth_logo_styles_exist_in_the_shared_stylesheet`. `test_regular_password_change_has_no_forced_message` dan `test_hidden_username_does_not_break_the_password_change` PASS (karakterisasi).

- [ ] **Step 4: Implementasi layout auth (tool Read lalu Write) `resources/views/components/layouts/auth.blade.php`**

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
                <a class="auth-brand" href="{{ route('home') }}">
                    <img class="auth-logo" src="{{ $logoUrl }}" data-fallback="{{ $logoFallback }}" alt="{{ $siteName }}" height="64">
                </a>
            </div>

            <main id="konten-utama" tabindex="-1">
                <h1 class="h3 mb-1">{{ $title }}</h1>
                @if (filled($subtitle))
                    <p class="text-body-secondary">{{ $subtitle }}</p>
                @endif

                <x-flash-alert />
                <x-form.errors-summary />

                {{ $slot }}
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

- [ ] **Step 5: Implementasi halaman login dan ganti password (tool Write)**

`resources/views/auth/admin-login.blade.php`:

```blade
<x-layouts.auth title="Masuk Panel Admin" subtitle="Khusus staf rumah sakit. Masuk dengan akun staf Anda.">
    <form method="POST" action="{{ route('admin.login.store') }}" novalidate>
        @csrf

        <x-form.input name="email" label="Email" type="email" required autocomplete="username" inputmode="email" :autofocus="! $errors->any()" />
        <x-form.input name="password" label="Password" type="password" required autocomplete="current-password" />

        <x-loading-button class="w-100 mt-2" loading-text="Memproses…">Masuk</x-loading-button>
    </form>

    <x-slot:footer>
        <p class="mb-0 text-body-secondary">Lupa password? Hubungi Super Admin.</p>
    </x-slot:footer>
</x-layouts.auth>
```

`resources/views/auth/admin-change-password.blade.php`:

```blade
@php
    $forcedMessage = 'Anda harus mengganti password sementara sebelum melanjutkan.';
    // Flash `warning` dari middleware sudah memuat pesan wajib ganti: subjudul tidak boleh mengulanginya.
    $subtitle = $forced && session('warning') !== $forcedMessage
        ? $forcedMessage
        : 'Gunakan password yang kuat dan belum pernah Anda pakai di tempat lain.';
@endphp
<x-layouts.auth title="Ganti Password" :subtitle="$subtitle">
    <form method="POST" action="{{ route('admin.password.update') }}" novalidate>
        @csrf
        @method('PUT')

        <input type="text" name="username" value="{{ auth()->user()->email }}" autocomplete="username" readonly hidden>

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

- [ ] **Step 6: Layout admin dan SCSS (Edit-Text)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
. "$env:TEMP\unpam-edit-text.ps1"

$admin = "$APP\resources\views\components\layouts\admin.blade.php"
Edit-Text $admin '<div class="flex-grow-1 d-flex flex-column">' '<div class="admin-column flex-grow-1 d-flex flex-column">'
Edit-Text $admin '<footer class="border-top bg-white px-3 px-lg-4 py-3 small text-body-secondary">' '<footer class="border-top bg-white px-3 px-lg-4 py-3 small text-body-secondary mt-auto">'

Edit-Text "$APP\resources\scss\_admin.scss" '.admin-main {' @'
// Kolom isi admin: setinggi layar (dvh mengikuti bilah alamat peramban mobile) agar footer menempel di dasar.
.admin-column {
  min-width: 0;
  min-height: 100vh;
  min-height: 100dvh;
}

.admin-main {
'@

Edit-Text "$APP\resources\scss\_shared.scss" '.image-frame {' @'
.auth-brand {
  display: inline-block;
}

// Logo besar satu-satunya identitas di halaman auth (logo.svg berrasio 4:1 sudah memuat nama situs).
.auth-logo {
  height: 4rem;
  width: auto;
  max-width: 100%;
}

.image-frame {
'@
Select-String -Path resources\scss\_admin.scss, resources\scss\_shared.scss -Pattern '\.admin-column', '\.auth-logo', '\.auth-brand' | ForEach-Object { "$($_.Filename): $($_.Line.Trim())" }
```
Expected: tiga baris. (Komentar bagian "Gambar" di `_shared.scss` kini berada di atas blok `.auth-*`; tidak berdampak. Bila `Edit-Text` melaporkan ≠ 1 kecocokan untuk `.admin-main {`, periksa bahwa Task 7 tidak menambah selektor serupa.)

- [ ] **Step 7: Jalankan tes — harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="AuthPolishTest|LayoutsTest|AdminLoginTest|AdminPasswordChangeTest|AdminLayoutTest|FrontendManifestTest"
npm run build
```
Expected: PASS (AuthPolishTest 9 tes; tes lama tetap hijau); `npm run build` sukses.

- [ ] **Step 8: Seluruh suite, Pint, commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test
& $PHP vendor\bin\pint --test
git add resources/views/components/layouts/auth.blade.php resources/views/auth/admin-login.blade.php resources/views/auth/admin-change-password.blade.php resources/views/components/layouts/admin.blade.php resources/scss/_shared.scss resources/scss/_admin.scss tests/Feature/View/AuthPolishTest.php
git commit -m @'
fix(fase-2): poles hasil uji manual fase 1 (logo, kartu tunggal, pesan ganda, autofocus, username, footer admin)

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
'@
```
Expected: seluruh tes PASS; Pint `PASS`.

- [ ] **Step 9: Pemeriksaan visual cepat (opsional, tanpa menghentikan implementer)**

Dengan Apache aktif: buka `http://unpam-hospital.test/admin/login` (logo besar tunggal, email terfokus, satu kartu) dan `/admin/ganti-password` setelah login akun sementara (pesan sekali; konsol Chrome tanpa peringatan "username"); resize jendela ke 360×500 px pada dasbor admin (footer menempel di dasar). Hasil dicatat di MANUAL Task 16.

---

### Task 16: Matriks akses fase 2, rekonsiliasi dokumen, README, dan gerbang fase 2

**Files:**
- Modify: `tests/Feature/Access/AdminPageMatrixTest.php` (ditulis ulang, baris fase 1 dipertahankan), `README.md`, `docs/superpowers/specs/2026-10-02-unpam-hospital-blueprint/{00-keputusan-inti,05-sitemap-matriks-halaman,07-wireframe,09-data-dictionary,10-struktur-folder,11-route-controller-model-service,12-aturan-bisnis-validasi-keamanan,14-tahapan-implementasi,15-pengujian-penerimaan,17-keputusan-konfirmasi}.md`
- Create: `tests/Feature/Access/Phase2RoutePermissionTest.php`
- Tidak diubah: `tests/Feature/Access/AdminRouteMiddlewareTest.php` (KNS-02; hanya dijalankan), `docs/superpowers/CHECKPOINT.md` dan "Catatan Eksekusi" rencana ini (diisi koordinator setelah final review).

**Interfaces:**
- Consumes: semua Task 1–15 (route `admin.doctors.*`, `admin.specialties.*`, `admin.clinics.*`, `admin.media.*`, `clinics.index|show`; `InteractsWithMasterData`; `InteractsWithMedia`; `InteractsWithStaff`; pembantu `Edit-Text`).
- Produces: data provider matriks 15 §3.1 baris 4/5 dan 15 §3.3 baris 33/33b/34 × lima aktor; matriks mutasi non-GET (15 §3: "penolakan 403 berlaku juga pada metode non-GET"); `Phase2RoutePermissionTest` (nama route, izin, URI, tanpa `destroy`/`show` terlarang). Rekonsiliasi dokumen untuk F2-R2..F2-R28 dan nama baru. README "Fase 2". Verifikasi gerbang fase 2 + langkah **MANUAL (pemilik)**.

**Matriks yang diverifikasi (kutipan sumber):**

15 §3.1 (publik): "| 4 | Daftar layanan | `clinics.index` | 200 | 200 | 200 | 200 | 200 |" dan "| 5 | Detail layanan | `clinics.show` | 200 | 200 | 200 | 200 | 200 |". 04 §4.2: "Publik; hanya `is_active`" dan "Publik; nonaktif → 404" (404 untuk layanan nonaktif diuji di Task 13).

15 §3.3 (admin; urutan kolom Tamu, Pasien, Admin Konten, Petugas, Super Admin):

| # | Route | Izin | Tamu | Pasien | Admin Konten | Petugas | Super Admin |
|---|---|---|:-:|:-:|:-:|:-:|:-:|
| 33 | `admin.doctors.index` (+ `admin.doctors.show`) | `doctors.view` | →L | 403 | 200 | 200 | 200 |
| 33b | `admin.doctors.create` (+ `.edit`) | `doctors.manage` | →L | 403 | 200 | 403 | 200 |
| 34 | `admin.clinics.index`, `admin.specialties.index` (+ `create`, `edit`) | `clinics.manage` | →L | 403 | 200 | 403 | 200 |

04 §4.5: Petugas hanya `doctors.view` ("33 Dokter: daftar dan detail … L L L"), semua aksi kelola dan `clinics.manage` "—". Admin Konten `K` pada 33 dan 34. Catatan 15 §3: "untuk halaman yang sama, penolakan 403 berlaku juga pada metode non-GET (POST/PUT/PATCH/DELETE) — tiap rute mutasi diuji dengan aktor tanpa izin" ⇒ matriks mutasi di bawah (tamu → redirect login atau 401 untuk `admin.media.*` yang berespons JSON; pasien dan Petugas → 403; Admin Konten dan Super Admin → lolos otorisasi, hasil 200/201/204/302/422 bergantung validasi).

**Test IDs:** `AKS-01`–`AKS-05` (baris 4, 5, 33, 33b, 34, serta mutasi), `AKS-06` (pasien ke `/admin/dokter`), `KNS-02` (tetap hijau, mencakup 25 route baru), bagian `KNS-01` fase 2 (hanya `admin.doctors.show` yang punya `show`; tanpa `destroy` dokter), `INS` (`migrate:fresh --seed`), `SEED-03`.

**Catatan teknis (diverifikasi dari source):**
- `AdminPageMatrixTest` fase 1 memanggil `route($routeName)` tanpa parameter; rute berparameter (`show`, `edit`, `update`, …) memerlukan model: provider membawa **jenis parameter** simbolik (`doctor`, `clinic`, `specialty`, `public-clinic`, `doctor-media`) yang diselesaikan di dalam tes karena provider berjalan sebelum aplikasi menyala.
- Parameter admin berupa id numerik (F2-R4); parameter publik `clinics.show` berupa slug.
- Tes mutasi tidak mengunci kode status aktor yang berhak (validasi kosong ⇒ 302/422; hapus berhasil ⇒ 302): yang dibuktikan hanya "lolos otorisasi" (bukan 403/401/login) agar tidak rapuh terhadap detail controller.
- Respons `admin.media.*` dipaksa JSON oleh `shouldRenderJsonWhen` (`bootstrap/app.php`), sehingga tamu menerima 401 (bukan redirect) di route itu; tes menerima keduanya (`G`).
- `AdminRouteMiddlewareTest` tidak boleh menambah nama ke `EXEMPT`; semua route baru berada di grup G-ADMIN dan memakai `permission:*`.
- Edit dokumen memakai `Edit-Text` (teks lama harus cocok tepat satu kali; bila gagal, baca baris sumbernya dan perbaiki anchor, bukan memperlebar). Direktori blueprint dipakai sebagai `$SPEC`.

- [ ] **Step 1: Verifikasi prasyarat**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git branch --show-current
git status --short
& $PHP artisan test
Test-Path "$env:TEMP\unpam-edit-text.ps1"
& $PHP artisan route:list --path=admin --except-vendor --json | ConvertFrom-Json | Measure-Object | ForEach-Object { "route admin: $($_.Count)" }
```
Expected: `fase-2-master-data`; bersih; seluruh tes PASS; `True` (bila `False`, buat ulang dari Task 13 Step 1); `route admin: 31` (6 fase 1 + 25 fase 2; bila berbeda, bandingkan dengan daftar di Step 17 sebelum melanjutkan).

**(a) Matriks akses**

- [ ] **Step 2: Tulis matriks (tool Read lalu Write, timpa) `tests/Feature/Access/AdminPageMatrixTest.php`**

```php
<?php

namespace Tests\Feature\Access;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithMedia;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * 15 §3.1 baris 4/5 dan §3.3 baris 31, 31b, 32, 33, 33b, 34 × lima aktor (AKS-01 sampai AKS-05),
 * ditambah matriks mutasi non-GET (15 §3 catatan) untuk modul master data dan media.
 * Hasil: 200 = tampil, 403 = ditolak, L = redirect ke admin.login, D = redirect dasbor sesuai tipe akun,
 * OK = lolos otorisasi (200/201/204/302/422, bukan 403/login), G = tamu (redirect login atau 401 JSON).
 */
class AdminPageMatrixTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithMedia;
    use InteractsWithStaff;
    use RefreshDatabase;

    private const ACTORS = ['guest', 'patient', 'content-admin', 'registration-officer', 'super-admin'];

    /** @return array<string, array{string, ?string, string, string}> */
    public static function matrix(): array
    {
        // Urutan hasil mengikuti ACTORS: Tamu, Pasien, Admin Konten, Petugas, Super Admin.
        $rows = [
            '31 Login admin' => ['admin.login', null, ['200', 'D', 'D', 'D', 'D']],
            '31b Ganti password staf' => ['admin.password.edit', null, ['L', '403', '200', '200', '200']],
            '32 Dashboard' => ['admin.dashboard', null, ['L', '403', '200', '200', '200']],
            '33 Dokter (daftar)' => ['admin.doctors.index', null, ['L', '403', '200', '200', '200']],
            '33 Dokter (detail)' => ['admin.doctors.show', 'doctor', ['L', '403', '200', '200', '200']],
            '33b Dokter (tambah)' => ['admin.doctors.create', null, ['L', '403', '200', '403', '200']],
            '33b Dokter (ubah)' => ['admin.doctors.edit', 'doctor', ['L', '403', '200', '403', '200']],
            '34 Poliklinik (daftar)' => ['admin.clinics.index', null, ['L', '403', '200', '403', '200']],
            '34 Poliklinik (tambah)' => ['admin.clinics.create', null, ['L', '403', '200', '403', '200']],
            '34 Poliklinik (ubah)' => ['admin.clinics.edit', 'clinic', ['L', '403', '200', '403', '200']],
            '34 Spesialisasi (daftar)' => ['admin.specialties.index', null, ['L', '403', '200', '403', '200']],
            '34 Spesialisasi (tambah)' => ['admin.specialties.create', null, ['L', '403', '200', '403', '200']],
            '34 Spesialisasi (ubah)' => ['admin.specialties.edit', 'specialty', ['L', '403', '200', '403', '200']],
        ];

        $cases = [];

        foreach ($rows as $label => [$routeName, $kind, $expected]) {
            foreach (self::ACTORS as $index => $actor) {
                $cases["{$label} / {$actor}"] = [$routeName, $kind, $actor, $expected[$index]];
            }
        }

        return $cases;
    }

    /** @return array<string, array{string, ?string, string}> */
    public static function publicMatrix(): array
    {
        $cases = [];

        foreach (['4 Daftar layanan' => ['clinics.index', null], '5 Detail layanan' => ['clinics.show', 'public-clinic']] as $label => [$routeName, $kind]) {
            foreach (self::ACTORS as $actor) {
                $cases["{$label} / {$actor}"] = [$routeName, $kind, $actor];
            }
        }

        return $cases;
    }

    /** @return array<string, array{string, string, ?string, string, string}> */
    public static function mutations(): array
    {
        // [metode, route, jenis parameter]; hasil: Tamu G, Pasien 403, Admin Konten OK, Petugas 403, Super Admin OK.
        $rows = [
            'POST dokter baru' => ['post', 'admin.doctors.store', null],
            'PUT ubah dokter' => ['put', 'admin.doctors.update', 'doctor'],
            'PATCH status dokter' => ['patch', 'admin.doctors.status', 'doctor'],
            'PATCH arsip dokter' => ['patch', 'admin.doctors.archive', 'doctor'],
            'POST foto dokter' => ['post', 'admin.doctors.photo', 'doctor'],
            'POST spesialisasi baru' => ['post', 'admin.specialties.store', null],
            'PUT ubah spesialisasi' => ['put', 'admin.specialties.update', 'specialty'],
            'DELETE spesialisasi' => ['delete', 'admin.specialties.destroy', 'specialty'],
            'POST poliklinik baru' => ['post', 'admin.clinics.store', null],
            'PUT ubah poliklinik' => ['put', 'admin.clinics.update', 'clinic'],
            'DELETE poliklinik' => ['delete', 'admin.clinics.destroy', 'clinic'],
            'POST unggah gambar editor' => ['post', 'admin.media.editor-upload', null],
            'POST urutkan media' => ['post', 'admin.media.reorder', null],
            'PATCH ubah media dokter' => ['patch', 'admin.media.update', 'doctor-media'],
            'DELETE media dokter' => ['delete', 'admin.media.destroy', 'doctor-media'],
        ];
        $expected = ['G', '403', 'OK', '403', 'OK'];
        $cases = [];

        foreach ($rows as $label => [$method, $routeName, $kind]) {
            foreach (self::ACTORS as $index => $actor) {
                $cases["{$label} / {$actor}"] = [$method, $routeName, $kind, $actor, $expected[$index]];
            }
        }

        return $cases;
    }

    #[DataProvider('matrix')]
    public function test_admin_page_matrix(string $routeName, ?string $kind, string $actor, string $expected): void
    {
        $user = $this->userFor($actor);

        if ($user !== null) {
            $this->actingAs($user);
        }

        $response = $this->get(route($routeName, $this->parameters($kind)));

        match ($expected) {
            '200' => $response->assertOk(),
            '403' => $response->assertForbidden(),
            'L' => $response->assertRedirect(route('admin.login')),
            'D' => $response->assertRedirect($this->dashboardFor($actor)),
        };
    }

    #[DataProvider('publicMatrix')]
    public function test_public_page_matrix_is_open_to_every_actor(string $routeName, ?string $kind, string $actor): void
    {
        $user = $this->userFor($actor);

        if ($user !== null) {
            $this->actingAs($user);
        }

        $this->get(route($routeName, $this->parameters($kind)))->assertOk();
    }

    #[DataProvider('mutations')]
    public function test_mutation_matrix_denies_actors_without_permission(string $method, string $routeName, ?string $kind, string $actor, string $expected): void
    {
        $user = $this->userFor($actor);

        if ($user !== null) {
            $this->actingAs($user);
        }

        $response = $this->{$method}(route($routeName, $this->parameters($kind)));

        match ($expected) {
            '403' => $response->assertForbidden(),
            'G' => $this->assertGuestRejected($response->getStatusCode(), (string) $response->headers->get('Location')),
            'OK' => $this->assertAuthorized($response->getStatusCode(), (string) $response->headers->get('Location')),
        };
    }

    public function test_patient_is_forbidden_from_admin_doctor_urls_aks_06(): void
    {
        $patient = User::factory()->patient()->create();

        $this->actingAs($patient)->get('/admin/dokter')->assertForbidden();
        $this->actingAs($patient)->post('/admin/dokter')->assertForbidden();
    }

    private function assertGuestRejected(int $status, string $location): void
    {
        $this->assertContains($status, [302, 401], "Tamu harus dialihkan ke login atau menerima 401, bukan {$status}.");

        if ($status === 302) {
            $this->assertSame(route('admin.login'), $location);
        }
    }

    private function assertAuthorized(int $status, string $location): void
    {
        $this->assertContains($status, [200, 201, 204, 302, 422], "Aktor berhak tidak boleh menerima {$status}.");
        $this->assertNotSame(route('admin.login'), $location);
    }

    /** @return array<string, int|string> */
    private function parameters(?string $kind): array
    {
        return match ($kind) {
            null => [],
            'doctor' => ['doctor' => $this->createDoctor()->id],
            'clinic' => ['clinic' => $this->createClinic()->id],
            'specialty' => ['specialty' => $this->createSpecialty()->id],
            'public-clinic' => ['clinic' => $this->createClinic()->slug],
            'doctor-media' => ['media' => $this->doctorPhotoRow()->id],
        };
    }

    private function doctorPhotoRow(): \App\Models\Media
    {
        $doctor = $this->createDoctor();

        return $this->createMediaRow([
            'model_type' => $doctor->getMorphClass(),
            'model_id' => $doctor->id,
            'collection' => 'photo',
            'directory' => 'photo/2026/10',
        ]);
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

- [ ] **Step 3: Tulis tes route (tool Write) `tests/Feature/Access/Phase2RoutePermissionTest.php`**

```php
<?php

namespace Tests\Feature\Access;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * KNS-01 (bagian fase 2) dan pelengkap KNS-02: nama route, izin, URI, dan route yang sengaja tidak ada.
 */
class Phase2RoutePermissionTest extends TestCase
{
    private const MANAGE_DOCTORS = 'permission:doctors.manage';

    private const MANAGE_CLINICS = 'permission:clinics.manage';

    /** @return array<string, array{string, string}> */
    public static function permissions(): array
    {
        $rows = [
            'admin.doctors.index' => 'permission:doctors.view',
            'admin.doctors.show' => 'permission:doctors.view',
            'admin.doctors.create' => self::MANAGE_DOCTORS,
            'admin.doctors.store' => self::MANAGE_DOCTORS,
            'admin.doctors.edit' => self::MANAGE_DOCTORS,
            'admin.doctors.update' => self::MANAGE_DOCTORS,
            'admin.doctors.status' => self::MANAGE_DOCTORS,
            'admin.doctors.archive' => self::MANAGE_DOCTORS,
            'admin.doctors.photo' => self::MANAGE_DOCTORS,
            'admin.media.editor-upload' => 'permission:content.manage|clinics.manage|facilities.manage',
            'admin.media.reorder' => 'permission:doctors.manage|clinics.manage|facilities.manage|content.manage',
            'admin.media.update' => 'permission:doctors.manage|clinics.manage|facilities.manage|content.manage|settings.manage',
            'admin.media.destroy' => 'permission:doctors.manage|clinics.manage|facilities.manage|content.manage|settings.manage',
        ];

        foreach (['specialties', 'clinics'] as $resource) {
            foreach (['index', 'create', 'store', 'edit', 'update', 'destroy'] as $action) {
                $rows["admin.{$resource}.{$action}"] = self::MANAGE_CLINICS;
            }
        }

        $cases = [];

        foreach ($rows as $name => $permission) {
            $cases[$name] = [$name, $permission];
        }

        return $cases;
    }

    #[DataProvider('permissions')]
    public function test_route_exists_with_the_documented_permission_middleware(string $name, string $permission): void
    {
        $route = Route::getRoutes()->getByName($name);

        $this->assertNotNull($route, "Route {$name} belum terdaftar.");
        $this->assertContains($permission, array_filter($route->gatherMiddleware(), 'is_string'), "Route {$name} harus memakai {$permission}.");
    }

    public function test_routes_that_must_not_exist_are_absent(): void
    {
        foreach (['admin.doctors.destroy', 'admin.specialties.show', 'admin.clinics.show'] as $name) {
            $this->assertNull(Route::getRoutes()->getByName($name), "{$name} tidak boleh ada (00 I.6, BR-03).");
        }

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (str_starts_with($route->uri(), 'admin/dokter')) {
                $this->assertNotContains('DELETE', $route->methods(), "Dokter tidak boleh punya route DELETE ({$route->uri()}).");
            }
        }
    }

    public function test_only_the_doctor_resource_has_an_admin_show_screen_among_phase_two_resources(): void
    {
        $this->assertNotNull(Route::getRoutes()->getByName('admin.doctors.show'));
    }

    public function test_uris_use_indonesian_segments(): void
    {
        $this->assertSame('/admin/dokter/buat', route('admin.doctors.create', [], false));
        $this->assertSame('/admin/dokter/5/ubah', route('admin.doctors.edit', 5, false));
        $this->assertSame('/admin/dokter/5/status', route('admin.doctors.status', 5, false));
        $this->assertSame('/admin/dokter/5/arsip', route('admin.doctors.archive', 5, false));
        $this->assertSame('/admin/dokter/5/foto', route('admin.doctors.photo', 5, false));
        $this->assertSame('/admin/spesialisasi', route('admin.specialties.index', [], false));
        $this->assertSame('/admin/poliklinik/3/ubah', route('admin.clinics.edit', 3, false));
        $this->assertSame('/admin/media/editor', route('admin.media.editor-upload', [], false));
        $this->assertSame('/admin/media/urutkan', route('admin.media.reorder', [], false));
        $this->assertSame('/layanan', route('clinics.index', [], false));
        $this->assertSame('/layanan/poliklinik-anak', route('clinics.show', 'poliklinik-anak', false));
    }

    public function test_static_segments_are_matched_before_parameterised_routes(): void
    {
        $routes = Route::getRoutes();

        $this->assertSame('admin.doctors.create', $routes->match(Request::create('/admin/dokter/buat', 'GET'))->getName());
        $this->assertSame('admin.media.reorder', $routes->match(Request::create('/admin/media/urutkan', 'POST'))->getName());
        $this->assertSame('admin.media.editor-upload', $routes->match(Request::create('/admin/media/editor', 'POST'))->getName());
    }
}
```

- [ ] **Step 4: Jalankan tes matriks**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="AdminPageMatrixTest|Phase2RoutePermissionTest|AdminRouteMiddlewareTest"
```
Expected: PASS — `AdminPageMatrixTest`: 151 tes (65 kasus matriks admin = 13 baris × 5 aktor, 10 kasus publik, 75 kasus mutasi, 1 kasus AKS-06); `Phase2RoutePermissionTest`: 29 tes; `AdminRouteMiddlewareTest`: 4 tes (KNS-02 hijau). Ini tes karakterisasi: perilaku dibangun di Task 4–12. Sel yang gagal adalah **temuan bug** pada middleware/Policy/route task tersebut (mis. Petugas 200 di `admin.doctors.create` ⇒ route `create` salah masuk grup `doctors.view`): perbaiki sumbernya, jangan mengubah tabel hasil. Yang wajib: tanpa gagal/`RISKY`; jumlah tes hanya acuan.

- [ ] **Step 5: Pint dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint --test
git add tests/Feature/Access/AdminPageMatrixTest.php tests/Feature/Access/Phase2RoutePermissionTest.php
git commit -m @'
test(fase-2): matriks akses baris 4, 5, 33, 33b, 34 dan mutasi non-GET untuk lima aktor

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
'@
```
Expected: Pint `PASS`.

**(b) Rekonsiliasi blueprint**

Daftar edit per berkas (ID = keputusan fase 2; "Edit" = langkah di bawah). Rulings tanpa konflik teks langsung (F2-R1 cabang, F2-R22 seeder 13 §2.1, F2-R26 butir proses) hanya dicatat sebagai baris D-nn di 17.

| Berkas dan bagian | Ruling | Isi perubahan |
|---|---|---|
| 00 §C (baris "Entitas publik memakai `slug`") | F2-R4 | Binding slug eksplisit di route publik; model tanpa `getRouteKeyName()` |
| 00 §J.6 | F2-R19, nama baru | `Audit\AuditObserver` → `Observers\AuditObserver`; bullet "Nama tambahan fase 2" |
| 05 hal. 4, hal. 5 | F2-R10, F2-R9 | Teks statis sementara; `x-doctor-card` fase 2 tanpa jadwal |
| 05 §6.3 (Tambah dokter, spesialisasi) | F2-R5, F2-R6 | Slug otomatis; form dokter tanpa status |
| 07 W-06 butir 4 | F2-R9 | Kartu dokter fase 2 |
| 07 W-16 butir 6 | F2-R6, F2-R23 | Tanpa dropdown status; `meta_title` ≤ 70 |
| 09 §3.3 relasi Dokter, §3.2 relasi Poliklinik, relasi Media, I-5, §15.1 | F2-R24, F2-R20 | `primarySpecialty` accessor; media lewat `HasMedia`; `DoctorService` jalur tulis; 20 baris pivot |
| 10 §2 (tabel `app/Models/Concerns`) | F2-R5 | Tambah `HasUniqueSlug` |
| 11 §5.1 trait, §5.9, P-CRUD `index`, §9.3 (`StoreDoctorRequest`, `UpdateDoctorStatusRequest`, `SpecialtyRequest`, `EditorImageUploadRequest`), §8 `MediaPolicy`, §6 (bagian baru 6.17) | F2-R5, R6, R7, R12, R15, R16, R19, R20 | Lihat Step 8 |
| 12 BR-15, BR-122, V-20, V-22, V-42 | F2-R5, R14, R7, R13, R16, R17 | Lihat Step 9 |
| 14 §Fase 1 keluaran, §Fase 2 | F2-R2, R3, R4, R7, R8, R26, R27 | Lihat Step 10 |
| 15 CRD-01, CRD-03 | F2-R28 | Asersi publik dokter pindah ke fase 3 |
| 17 §4 | semua | Baris D-29 s.d. D-54 |

- [ ] **Step 6: 00 §C, §J.6 (nama baru fase 2)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
. "$env:TEMP\unpam-edit-text.ps1"
$SPEC = "$APP\docs\superpowers\specs\2026-10-02-unpam-hospital-blueprint"
$f = "$SPEC\00-keputusan-inti.md"

Edit-Text $f 'route model binding berdasarkan slug.' 'route publik memakai binding slug yang ditulis eksplisit (`{clinic:slug}`, `{doctor:slug}`); model tidak menimpa `getRouteKeyName()` dan route admin memakai id numerik (F2-R4, 11 §2.4).'

Edit-Text $f '`Audit\AuditObserver`.' '`Observers\AuditObserver` (`app/Observers/AuditObserver.php`, didaftarkan di `AppServiceProvider`; F2-R19).'

Edit-Text $f '- **Pengujian:** folder' @'
- **Nama tambahan fase 2:** `Models\Concerns\HasUniqueSlug` (`slugSource()`, `slugMaxLength()`, `static uniqueSlug(string $source, ?int $ignoreId = null)`), `Services\Doctors\DoctorService` (jalur tulis dokter; titik sambung `futureActiveAppointmentsCount()`), `Doctor::scopePubliclyVisible()`, `Http\Requests\Admin\DoctorIndexRequest` dan `MasterDataIndexRequest`, `Specialty::deletionBlockedReason()`/`Clinic::deletionBlockedReason()` (teks pesan baru), prop `preserve` pada `x-data-table`, `config('navigation.modules')`, `lang/id/messages.php` (kunci `created`, `updated`, `deleted`, `delete_blocked`, `entities.*`, `audit.*`), `Services\Media\PlaceholderImageGenerator::make(string $type, string $label, int $width, int $height): UploadedFile`, konstanta `SpecialtySeeder::ROWS`/`ClinicSeeder::ROWS`/`DoctorSeeder::ROWS`, factory `Specialty`/`Clinic`/`Doctor` (state `inactive()`, `archived()`, `support()`, `withSpecialties()`), pembantu tes `Tests\Support\InteractsWithMasterData`, komponen `x-doctor-card` dan `x-module-tabs`, serta `x-form.rich-text` + `resources/js/editor.js` (dibangun di fase 2, F2-R2).
- **Pengujian:** folder
'@

Select-String -Path $f -Pattern 'Nama tambahan fase 2', 'F2-R4', 'Observers\\AuditObserver' | ForEach-Object { $_.LineNumber }
```
Expected: tiga nomor baris tercetak; pencarian `Audit\AuditObserver` tanpa awalan `Observers\` tidak ada lagi.

- [ ] **Step 7: 05 (hal. 4, 5, §6.3) dan 07 (W-06, W-16)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
. "$env:TEMP\unpam-edit-text.ps1"
$SPEC = "$APP\docs\superpowers\specs\2026-10-02-unpam-hospital-blueprint"
$f5 = "$SPEC\05-sitemap-matriks-halaman.md"
$f7 = "$SPEC\07-wireframe.md"

# hal. 4 (F2-R10)
Edit-Text $f5 '; `pages` (key `clinics-index`) | Membuka detail layanan' '; `pages` (key `clinics-index`; fase 2 memakai judul statis "Layanan Kami" dan subjudul "Poliklinik dan layanan penunjang UNPAM HOSPITAL." sampai model `Page` ada di fase 4a, F2-R10) | Membuka detail layanan'

# hal. 5 (F2-R9)
Edit-Text $f5 '`doctors` pada poliklinik; `DoctorAvailabilityQuery` |' '`doctors` aktif pada poliklinik (`Doctor::publiclyVisible()`); `DoctorAvailabilityQuery` (fase 3; fase 2 hanya daftar dokter tanpa jadwal, F2-R9) |'
Edit-Text $f5 '**Daftar Kunjungan** (`patient.appointments.create?poli={clinics.slug}`); membuka detail dokter | Slug tidak ada' '**Daftar Kunjungan** (`patient.appointments.create?poli={clinics.slug}`; tautan dan tombol Jadwal Dokter hanya tampil bila route-nya ada, F1-R2/F2-R9); membuka detail dokter | Slug tidak ada'

# §6.3 (F2-R5, F2-R6)
Edit-Text $f5 'Membuat spesialisasi (nama, slug, deskripsi, urutan, aktif).' 'Membuat spesialisasi (nama, deskripsi, urutan, aktif; slug dibuat otomatis oleh sistem, F2-R5).'
Edit-Text $f5 'spesialisasi (multi-pilih + primer), profil, foto, status.' 'spesialisasi (multi-pilih + primer), profil, foto (tanpa status: dokter baru selalu aktif, status diubah lewat `admin.doctors.status`, F2-R6).'

# W-06 butir 4 (F2-R9)
Edit-Text $f7 '`x-doctor-card` ringkas dengan jadwal praktik terdekat dari `DoctorAvailabilityQuery`' '`x-doctor-card` ringkas (fase 2: foto, nama tampilan, spesialisasi utama, dan tautan `doctors.show` bila route ada; jadwal praktik terdekat dari `DoctorAvailabilityQuery` ditambahkan di fase 3, F2-R9)'

# W-16 butir 6 (F2-R6, F2-R23)
Edit-Text $f7 '**Status** (`status`: Aktif/Nonaktif; arsip dilakukan dari daftar/detail dengan konfirmasi), **urutan tampil** (`sort_order`), bagian lipat **SEO** (`meta_title` ≤ 60,' '**urutan tampil** (`sort_order`); **tidak ada field status** (dokter baru selalu `active`; aktif/nonaktif dan pemulihan lewat `admin.doctors.status`, arsip dari daftar/detail dengan konfirmasi, F2-R6/F2-R7); bagian lipat **SEO** (`meta_title` ≤ 70 (F2-R23),'

Select-String -Path $f5, $f7 -Pattern 'F2-R' | ForEach-Object { "$($_.Filename):$($_.LineNumber)" }
```
Expected: tanpa galat `Edit-Text`; tujuh baris bertanda `F2-R` (05: lima, 07: dua).

- [ ] **Step 8: 09, 10, 11**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
. "$env:TEMP\unpam-edit-text.ps1"
$SPEC = "$APP\docs\superpowers\specs\2026-10-02-unpam-hospital-blueprint"
$f9 = "$SPEC\09-data-dictionary.md"
$f10 = "$SPEC\10-struktur-folder.md"
$f11 = "$SPEC\11-route-controller-model-service.md"

# 09: relasi Dokter, Poliklinik, Media, I-5, volume (F2-R24, F2-R20)
Edit-Text $f9 '`Doctor::primarySpecialty()` = `specialties()->wherePivot(''is_primary'', true)` (diambil `->first()`);' '`Doctor::primarySpecialty` = accessor (bukan relasi) dari relasi `specialties` yang sudah dimuat: baris pivot `is_primary`, cadangan spesialisasi pertama (11 §5.2, F2-R24);'
Edit-Text $f9 '`Doctor::photo()` morphOne `Media` (koleksi `photo`).' 'foto lewat trait `HasMedia` (`firstMedia(''photo'')`, accessor `photoUrl`/`photoThumbUrl`); tidak ada relasi `photo()` khusus (F2-R24).'
Edit-Text $f9 '`Clinic::cover()` morphOne `Media` (koleksi `cover`); `Clinic::gallery()` morphMany `Media` (koleksi `gallery`, urut `sort_order`).' 'cover dan galeri lewat trait `HasMedia` (`firstMedia(''cover'')`, `mediaIn(''gallery'')`, urut `sort_order`, `id`); tidak ada relasi `cover()`/`gallery()` khusus (F2-R24).'
Edit-Text $f9 'relasi per koleksi (`Doctor::photo()`, `Clinic::gallery()`, …).' 'akses per koleksi lewat `firstMedia()`/`mediaIn()` (tanpa relasi khusus per koleksi pada `Doctor`/`Clinic`, F2-R24).'
Edit-Text $f9 'Service `DoctorService::syncSpecialties()` (transaksi: nolkan semua, lalu set satu)' 'Service `Doctors\DoctorService::syncSpecialties()` (satu-satunya jalur tulis dokter, F2-R20; transaksi: nolkan semua, lalu set satu)'
Edit-Text $f9 '| `doctor_specialties` | ±22 |' '| `doctor_specialties` | 20 (18 primer + 2 sekunder, 13 §6) |'

# 10: trait model
Edit-Text $f10 '`HasMedia` (relasi `media` + `firstMedia()`), `SanitizesRichText`' '`HasMedia` (relasi `media` + `firstMedia()`), `HasUniqueSlug` (slug otomatis unik dan stabil, F2-R5), `SanitizesRichText`'

# 11: trait, observer, index, request, policy, DoctorService
Edit-Text $f11 '- **Trait kustom** (`App\Models\Concerns`):' '- **Trait kustom** (`App\Models\Concerns`; fase 2 menambah `HasUniqueSlug`: `bootHasUniqueSlug()` mengisi `slug` saat `creating` bila kosong, `slugSource()`, `slugMaxLength()`, `static uniqueSlug(string $source, ?int $ignoreId = null): string`, sufiks `-2`, `-3`, tidak berubah saat nama diedit; F2-R5):'
Edit-Text $f11 '### 5.9 `AuditObserver`' '### 5.9 `AuditObserver` (`App\Observers\AuditObserver`, didaftarkan di `AppServiceProvider`; fase 2: hanya `Specialty` dan `Clinic`, `Doctor` dicatat `DoctorService`; F2-R19)'
Edit-Text $f11 '->paginate(15)->withQueryString()' '->paginate($request->perPage())->withQueryString()'
Edit-Text $f11 '`clinic_id` ada; `name`, `slug` unik; gelar; `gender`; `short_bio` ≤ 500; `bio`; `status`; `specialty_ids[]`' '`clinic_id` ada dan aktif; `name`; gelar; `gender`; `short_bio` ≤ 500; `bio`; `specialty_ids[]`'
Edit-Text $f11 '`name`, `slug` unik, `description?`, `is_active`, `sort_order`.' '`name` unik, `description?`, `is_active`, `sort_order` (slug dibuat sistem oleh `HasUniqueSlug`, F2-R5).'
Edit-Text $f11 'Dokter `archived` tidak dapat diubah lewat form ini (dipulihkan menjadi `inactive` lewat aksi tersendiri).' 'Dokter `archived` hanya dapat dipulihkan dengan `status = inactive` lewat route ini (`archived_at` dikosongkan); nilai lain ditolak dengan "Dokter yang diarsipkan hanya dapat dipulihkan menjadi nonaktif." (F2-R7).'
Edit-Text $f11 '`image` (aturan unggah 00 K; `editor`' '`file` (nama field F2-R15; aturan unggah 00 K; `editor`'
Edit-Text $f11 'koleksi `editor`→ pengunggah sendiri atau `content.manage` |' 'koleksi `editor`→ pengunggah sendiri **atau** salah satu dari `content.manage`/`clinics.manage`/`facilities.manage`; `model_type` yang belum dipetakan ditolak sampai fasenya (F2-R16) |'

Edit-Text $f11 'Otorisasi (`AppointmentPolicy::view`) dilakukan **sebelum** service dipanggil; service tidak memeriksa hak akses.' @'
Otorisasi (`AppointmentPolicy::view`) dilakukan **sebelum** service dipanggil; service tidak memeriksa hak akses.

### 6.17 `Doctors\DoctorService` (fase 2, F2-R8, F2-R20)

`App\Services\Doctors\DoctorService` (`final`, constructor `(AuditLogger $audit, MediaService $media)`) adalah satu-satunya jalur tulis dokter; semua method berjalan dalam `DB::transaction` dengan audit eksplisit (12 §8.2):

```php
public function create(array $data, Actor $actor, ?UploadedFile $photo = null, ?string $photoAlt = null): Doctor;   // status active; syncSpecialties; foto lewat MediaService::store; audit doctor.created
public function update(Doctor $doctor, array $data, Actor $actor, ?UploadedFile $photo = null, ?string $photoAlt = null): Doctor; // slug dan status tidak disentuh; audit doctor.updated
public function syncSpecialties(Doctor $doctor, array $specialtyIds, int $primaryId): void;                        // I-5
public function changeStatus(Doctor $doctor, DoctorStatus $status, ?string $reason, Actor $actor): Doctor;         // active/inactive; archived -> inactive (pemulihan, F2-R7); audit doctor.status_changed
public function archive(Doctor $doctor, ?string $reason, Actor $actor): Doctor;                                    // archived + archived_at; audit doctor.archived
public function replacePhoto(Doctor $doctor, UploadedFile $photo, string $alt, Actor $actor): Media;               // MediaService::replace
public function archiveBlockedReason(Doctor $doctor): ?string;                                                     // pesan penolakan arsip (BR-05) atau null; dipakai archive() dan ArchiveDoctorRequest
protected function futureActiveAppointmentsCount(Doctor $doctor): int;                                             // titik sambung F.4: fase 2 selalu 0, fase 3 mengganti isinya (F2-R8)
```
'@

Select-String -Path $f9, $f10, $f11 -Pattern 'F2-R' | Measure-Object | ForEach-Object { "baris bertanda F2-R: $($_.Count)" }
```
Expected: tidak ada galat `Edit-Text`; `baris bertanda F2-R: 15` atau lebih. (Bagian 6.17 disisipkan sesudah 6.16, sebelum pemisah `---` dan bagian 7.)

- [ ] **Step 9: 12 (BR-15, BR-122, V-20, V-22, V-42)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
. "$env:TEMP\unpam-edit-text.ps1"
$SPEC = "$APP\docs\superpowers\specs\2026-10-02-unpam-hospital-blueprint"
$f = "$SPEC\12-aturan-bisnis-validasi-keamanan.md"

# BR-15 (F2-R5)
Edit-Text $f 'Observer `creating` + UNIQUE `slug`.' 'Trait `HasUniqueSlug` (event `creating`; sumber nama tanpa gelar, dipotong sesuai panjang kolom) + UNIQUE `slug` (F2-R5).'

# BR-122 (F2-R14)
Edit-Text $f '`HtmlSanitizer` (profil `rich_text` `mews/purifier`) dipanggil di Form Request/`prepareForValidation`;' '`HtmlSanitizer` (profil `rich_text` `mews/purifier`) dipanggil hanya oleh trait `SanitizesRichText` pada event `saving` sehingga setiap jalur simpan (form, seeder, `update()`) tersanitasi; Form Request tidak membersihkan HTML (F2-R14);'

# V-20 (F2-R7)
Edit-Text $f '"Status dokter tidak valid." |' '"Status dokter tidak valid."<br>Dari `archived` hanya `inactive` yang diterima (pemulihan; `archived_at` dikosongkan): "Dokter yang diarsipkan hanya dapat dipulihkan menjadi nonaktif." (F2-R7) |'

# V-22 (F2-R13)
Edit-Text $f '`nullable` `string` `max:50000`; disanitasi `HtmlSanitizer` sebelum disimpan | "Deskripsi maksimal 50.000 karakter." |' '`nullable` `string` `max:30000` (dihitung pada input mentah; kolom `TEXT` 65.535 byte, 09 §1.2 butir 10, F2-R13); disanitasi `HtmlSanitizer` saat simpan lewat trait `SanitizesRichText` | "Deskripsi maksimal 30.000 karakter." |'

# V-42 (F2-R16, F2-R17)
Edit-Text $f 'logo situs → `settings.manage`)' 'logo situs → `settings.manage`; media `editor` tanpa pemilik → pengunggah atau salah satu dari `content.manage`/`clinics.manage`/`facilities.manage`; `model_type` yang belum dipetakan ditolak, F2-R16)'
Edit-Text $f '`MediaPolicy::delete`; koleksi tunggal wajib tetap berisi bila halaman publik memerlukannya |' '`MediaPolicy::delete`; ditolak (422, atau flash `error` pada permintaan non-JSON) hanya bila media dirujuk `site.logo_media_id`; foto dokter, cover, dan galeri boleh dihapus (halaman publik jatuh ke placeholder, F2-R17) |'

Select-String -Path $f -Pattern 'F2-R' | ForEach-Object { $_.LineNumber }
```
Expected: tujuh nomor baris tercetak, tanpa galat `Edit-Text`. Bila anchor `"Status dokter tidak valid." |` cocok ≠ 1 kali, tambahkan awalan baris V-20 (`UpdateDoctorStatusRequest`) pada anchor.

- [ ] **Step 10: 14 (fase 1/2) dan 15 (CRD-01, CRD-03)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
. "$env:TEMP\unpam-edit-text.ps1"
$SPEC = "$APP\docs\superpowers\specs\2026-10-02-unpam-hospital-blueprint"
$f14 = "$SPEC\14-tahapan-implementasi.md"
$f15 = "$SPEC\15-pengujian-penerimaan.md"

# F-ADM-29 dipindah ke fase 2 (D-28, F2-R3)
Edit-Text $f14 'F-PUB-19, F-PUB-20, F-ADM-29 |' 'F-PUB-19, F-PUB-20 |'
Edit-Text $f14 'F-PUB-19, F-PUB-20, F-ADM-29 (endpoint media).' 'F-PUB-19, F-PUB-20 (F-ADM-29, endpoint media, dikerjakan di fase 2: D-28, F2-R3).'
Edit-Text $f14 '| 2 | F-ADM-02, F-ADM-03, F-ADM-04, F-PUB-04 (dan F-SYS-14 bagian data master) |' '| 2 | F-ADM-02, F-ADM-03, F-ADM-04, F-ADM-29 (endpoint media + editor, D-28), F-PUB-04 (dan F-SYS-14 bagian data master) |'
Edit-Text $f14 '- Fitur: F-ADM-02, F-ADM-03, F-ADM-04, F-PUB-04 (dan F-SYS-14 bagian data master).' '- Fitur: F-ADM-02, F-ADM-03, F-ADM-04, F-ADM-29, F-PUB-04 (dan F-SYS-14 bagian data master).'

# Tugas fase 2
Edit-Text $f14 '`slug` (route model binding) untuk empat tabel;' '`slug` otomatis lewat trait `HasUniqueSlug` (route publik `{clinic:slug}` ditulis eksplisit, model tanpa `getRouteKeyName()`; F2-R4, F2-R5) untuk tabel yang ber-slug;'
Edit-Text $f14 'dilengkapi di fase 3 setelah tabel `appointments` dan `ScheduleChangeService` ada;' 'dilengkapi di fase 3 setelah tabel `appointments` dan `ScheduleChangeService` ada (fase 2: titik sambung `DoctorService::futureActiveAppointmentsCount()` selalu 0, arsip diizinkan, F2-R8; pemulihan arsip lewat `admin.doctors.status` dengan `inactive`, F2-R7);'
Edit-Text $f14 '8. Seeder dan factory demo; perluas matriks `AKS`.' '8. Seeder dan factory demo; perluas matriks `AKS`. Carry fase 1 yang dikerjakan di sini: `x-form.checkbox` larik, `x-data-table` (halaman di luar jangkauan, `preserve`), meta `csrf-token`, poles hasil uji manual, butir (c) `media:prune` (F2-R26); carry pengaturan situs (WhatsApp `62…`, bool/int ketat) ditunda ke fase 8 (F2-R27).'
Edit-Text $f14 '(4a) `PageSeeder` + `admin.pages.*` + Quill;' '(4a) `PageSeeder` + `admin.pages.*` + editor Quill yang sudah dibangun di fase 2 (dipakai ulang untuk `pages.body`/`articles.body`, F2-R2);'

# 15 CRD (F2-R28)
Edit-Text $f15 'Tersimpan; muncul pada filter `doctors.index`; nonaktif menghilang dari filter |' 'Tersimpan; muncul pada filter `doctors.index` (fase 2: pada pilihan spesialisasi admin dan scope `Specialty::active()`; asersi `doctors.index` publik ditambah di fase 3, F2-R28); nonaktif menghilang dari filter |'
Edit-Text $f15 'Tampil di `/dokter` dan `/dokter/{slug}`;' 'Tampil di `/dokter` dan `/dokter/{slug}` (fase 2: scope `Doctor::publiclyVisible()` dan tampilan #5; asersi URL dokter ditambah di fase 3, F2-R28);'

Select-String -Path $f14, $f15 -Pattern 'F2-R' | ForEach-Object { "$($_.Filename):$($_.LineNumber)" }
```
Expected: tanpa galat; baris bertanda F2-R muncul di 14 (8) dan 15 (2).

- [ ] **Step 11: 17 §4 (baris D-29 s.d. D-54)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
. "$env:TEMP\unpam-edit-text.ps1"
$SPEC = "$APP\docs\superpowers\specs\2026-10-02-unpam-hospital-blueprint"

Edit-Text "$SPEC\17-keputusan-konfirmasi.md" '`MediaService` sudah tersedia sejak fase 1. | 14 §4 (F1-R21) |' @'
`MediaService` sudah tersedia sejak fase 1. | 14 §4 (F1-R21) |
| D-29 | Editor rich text dan endpoint media | Dibangun di fase 2: `x-form.rich-text`, `resources/js/editor.js`, `MediaController`, `MediaPolicy`, tiga Form Request V-42, empat route `admin.media.*`. Fase 4a memakai ulang editor. Menutup D-28. | 14 §Fase 2 tugas 4, 11 §2.3 J (F2-R2, F2-R3) |
| D-30 | Route key | Model tidak menimpa `getRouteKeyName()`; route publik `{clinic:slug}`; route admin memakai id numerik. | 11 §2.4 (F2-R4) |
| D-31 | Slug | Otomatis lewat trait `HasUniqueSlug` (dari nama tanpa gelar; sufiks `-2`, `-3`; dipotong sesuai kolom; tidak berubah saat nama diedit); form tanpa input slug. | 12 V-19/V-21, BR-15 (F2-R5) |
| D-32 | Form dokter tanpa status | Dokter baru selalu `active`; status lewat `admin.doctors.status`. | 12 V-19 (F2-R6) |
| D-33 | Pemulihan dari arsip | Lewat `admin.doctors.status` dengan `inactive`; `archived_at` dikosongkan; nilai lain ditolak. | BR-06 (F2-R7) |
| D-34 | Aturan F.4 pada fase 2 | Belum ditegakkan; titik sambung `DoctorService::futureActiveAppointmentsCount()` (selalu 0) diganti di fase 3. | 14 §Fase 2 tugas 6 (F2-R8) |
| D-35 | Halaman #5 fase 2 | `x-doctor-card` tanpa jadwal dan tanpa tombol daftar; tautan hanya bila route ada. | 14, F1-R2 (F2-R9) |
| D-36 | Halaman #4 fase 2 | Judul statis "Layanan Kami" sampai `pages` key `clinics-index` (fase 4a). | 05 hal. 4 (F2-R10) |
| D-37 | `x-module-tabs` | Dibangun di fase 2 untuk tab Poliklinik/Spesialisasi. | 05 hal. 34 (F2-R11) |
| D-38 | Paginasi | `per_page` admin default 25; halaman #4 tanpa paginasi, dikelompokkan per `ClinicType`. | 00 I.5 (F2-R12) |
| D-39 | Batas `clinics.description` | `max:30000` pada input mentah. | 09 §1.2 butir 10 (F2-R13) |
| D-40 | Sanitasi rich text | Hanya di trait `SanitizesRichText` saat `saving`. | 11 §6.12 (F2-R14) |
| D-41 | Nama field unggahan | Editor `file` + `alt_text`; foto dokter `photo` + `alt_text`/`photo_alt`; poliklinik `cover`/`cover_alt`, `gallery[]`/`gallery_alt[]` (maks. 20). | 12 V-19/V-20/V-22/V-42 (F2-R15) |
| D-42 | `MediaPolicy` | Pemetaan per pemilik; `model_type` NULL: `logo` → `settings.manage`, `editor` → pengunggah atau salah satu izin kelompok; tipe lain ditolak sampai fasenya. | 11 §8, 04 §5.1 (F2-R16) |
| D-43 | Hapus media | Foto, cover, galeri boleh dihapus; yang dirujuk `site.logo_media_id` ditolak. | 12 V-42 (F2-R17) |
| D-44 | Penolakan hapus | Di controller lewat `deletionBlockedReason()` (berlaku juga bagi Super Admin). | 11 §2.3/§4.4 (F2-R18) |
| D-45 | `AuditObserver` | `App\Observers\AuditObserver`; fase 2 hanya `Specialty` dan `Clinic`. | 10 §2, 11 §5.9 (F2-R19) |
| D-46 | `DoctorService` | Satu-satunya jalur tulis dokter, dengan audit eksplisit. | 09 I-5/I-23 (F2-R20) |
| D-47 | Setelah simpan | Redirect ke `index` dengan flash dari `lang/id/messages.php`. | 11 P-CRUD (F2-R21) |
| D-48 | Seeder master | `SpecialtySeeder`, `ClinicSeeder`, `DoctorSeeder` hanya demo, dipanggil `DemoSeeder`; gambar dari `PlaceholderImageGenerator`. | 13 §2.1–2.3 (F2-R22) |
| D-49 | `meta_title` | Maksimal 70 karakter. | 12 BR-123 (F2-R23) |
| D-50 | `Doctor::primarySpecialty` | Accessor dari relasi `specialties` yang dimuat; tanpa model pivot dan tanpa relasi media khusus. | 11 §5.2 (F2-R24) |
| D-51 | Filter daftar admin | `DoctorIndexRequest` dan `MasterDataIndexRequest` (turunan `ListFilterRequest`). | 07 W-15, 12 V-45 (F2-R25) |
| D-52 | Carry fase 1 | Dikerjakan di fase 2: checkbox larik, `x-data-table`, meta CSRF, poles uji manual, `media:prune` butir (c). | CHECKPOINT §4 (F2-R26) |
| D-53 | Carry pengaturan situs | WhatsApp `62…` dan bool/int ketat ditunda ke fase 8 (form pengaturan). | 14 §Fase 8 (F2-R27) |
| D-54 | Asersi CRD fase 2 | CRD-01/CRD-03 memakai scope `publiclyVisible` dan tampilan #5; asersi URL dokter di fase 3. | 14 §Fase 2 (F2-R28) |
'@

Select-String -Path "$SPEC\17-keputusan-konfirmasi.md" -Pattern '^\| D-(29|54) \|' | ForEach-Object { $_.Line.Substring(0, 40) }
```
Expected: dua baris (`| D-29 |` dan `| D-54 |`).

- [ ] **Step 12: Verifikasi seluruh pembaruan dokumen, lalu commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
$SPEC = "docs\superpowers\specs\2026-10-02-unpam-hospital-blueprint"
git diff --stat -- $SPEC
git status --short
Select-String -Path "$SPEC\*.md" -Pattern 'route model binding berdasarkan slug', 'max:50000`; disanitasi `HtmlSanitizer` sebelum disimpan', '±22' -SimpleMatch | ForEach-Object { "$($_.Filename):$($_.LineNumber)" }
git add "$SPEC\00-keputusan-inti.md" "$SPEC\05-sitemap-matriks-halaman.md" "$SPEC\07-wireframe.md" "$SPEC\09-data-dictionary.md" "$SPEC\10-struktur-folder.md" "$SPEC\11-route-controller-model-service.md" "$SPEC\12-aturan-bisnis-validasi-keamanan.md" "$SPEC\14-tahapan-implementasi.md" "$SPEC\15-pengujian-penerimaan.md" "$SPEC\17-keputusan-konfirmasi.md"
git commit -m @'
docs(fase-2): rekonsiliasi blueprint dengan keputusan F2-R2..R28 dan nama baru fase 2

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
'@
```
Expected: `git diff --stat` menampilkan sepuluh berkas dokumen berubah; pencarian tiga teks lama **tidak** mencetak apa pun; commit dibuat; `git status` tidak memuat berkas lain.

**(c) README bagian "Fase 2"**

- [ ] **Step 13: Tambahkan bagian Fase 2 ke `README.md` (isi lama dipertahankan, bagian ditambahkan setelah "Pemeriksaan cepat" fase 1)**

````powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
. "$env:TEMP\unpam-edit-text.ps1"
Edit-Text "$APP\README.md" @'
& $PHP artisan test                      # seluruh suite
```
'@ @'
& $PHP artisan test                      # seluruh suite
```

## Fase 2 — Master Data (spesialisasi, poliklinik, dokter)

Yang tersedia: pengelolaan **Dokter** (tambah, ubah, foto, aktif/nonaktif, arsip, pemulihan dari arsip) di <http://unpam-hospital.test/admin/dokter>; **Poliklinik dan Spesialisasi** (CRUD, cover, galeri, editor rich text dengan unggah gambar) di `/admin/poliklinik` dan `/admin/spesialisasi`; halaman publik **Layanan Kami** di `/layanan` dan **Detail Layanan** di `/layanan/{slug}`. Halaman publik dokter dan jadwal menyusul di fase 3.

### Hak akses

| Peran | Dokter (lihat) | Dokter (kelola) | Poliklinik dan Spesialisasi |
|---|:-:|:-:|:-:|
| Super Admin | ya | ya | ya |
| Admin Konten | ya | ya | ya |
| Petugas Pendaftaran | ya | tidak | tidak |

Layanan yang dinonaktifkan menjawab 404 di URL publik, juga untuk staf yang login. Dokter nonaktif atau arsip tidak tampil di halaman publik. Spesialisasi atau poliklinik yang masih dirujuk tidak dapat dihapus (nonaktifkan saja).

### Data demo

```powershell
& $PHP artisan migrate:fresh --seed   # LOKAL SAJA: menghapus semua tabel dan isi database unpam_hospital
```

Selain data fase 1, hasilnya: 13 spesialisasi, 17 layanan (13 poliklinik + 4 penunjang), 18 dokter (16 aktif, 1 nonaktif, 1 arsip), dan 32 gambar contoh (foto 16 dokter, cover 16 layanan; poliklinik Gigi dan dua dokter sengaja tanpa gambar untuk menguji placeholder). Gambar dibuat lokal oleh GD tanpa unduhan dari internet dan disimpan di `storage/app/public` (jalankan `& $PHP artisan storage:link` sekali). Seeder master hanya berjalan di `APP_ENV=local`/`testing`.

### Pemeriksaan cepat

```powershell
& $PHP artisan route:list --path=admin   # 31 route admin (6 fase 1 + 25 fase 2)
& $PHP artisan media:prune --dry-run     # setelah seeding: 0 baris dan 0 berkas yatim
& $PHP artisan test                      # seluruh suite
npm run build                            # editor Quill ikut dibundel (tanpa CDN)
```
'@
Select-String -Path README.md -Pattern '## Fase 2', 'media:prune --dry-run', '31 route admin' -SimpleMatch | ForEach-Object { $_.Line }
````
Expected: tiga baris tercetak (bagian "Fase 2" kini ada di akhir README). Bila `Edit-Text` melaporkan ≠ 1 kecocokan, baca ekor `README.md` dan sesuaikan jumlah spasi pada baris `artisan test`.

- [ ] **Step 14: Verifikasi README dan commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="RepositoryHygieneTest|ComposerManifestTest"
git add README.md
git commit -m @'
docs(fase-2): README bagian Fase 2 (hak akses, data demo, pemeriksaan cepat)

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
'@
```
Expected: tes PASS; commit dibuat.

**(d) Verifikasi gerbang fase 2**

- [ ] **Step 15: Suite penuh, Pint, build aset**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test
& $PHP vendor\bin\pint --test
npm run build
Select-String -Path public\build\manifest.json -Pattern 'resources/scss/app.scss', 'resources/js/app.js', 'resources/js/admin.js', 'resources/js/editor.js' -SimpleMatch | ForEach-Object { $_.Line.Trim() }
git status --short
```
Expected: semua tes PASS tanpa `RISKY`/`WARNING` (catat `Tests: N passed`; fase 1 berakhir di 980, fase 2 menambah tes sehingga N > 980); Pint `PASS`; `npm run build` sukses (peringatan `mixed-decls` boleh); manifest memuat entri `app.scss`, `app.js`, `admin.js` (dan `editor.js` bila Task 7 mendaftarkannya sebagai chunk dinamis; periksa terhadap `FrontendManifestTest`); `git status` bersih (tanpa `.env`, `vendor`, `node_modules`, `public/build`, `public/storage`).

- [ ] **Step 16: `migrate:fresh --seed` pada database pengembangan (INS, SEED-01/03)**

Peringatan: menghapus seluruh tabel dan isi database **pengembangan** `unpam_hospital` dan menulis 32 gambar ke `storage/app/public`.

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan migrate:fresh --seed --ansi
& $PHP artisan tinker --execute="echo json_encode(['roles' => Spatie\Permission\Models\Role::count(), 'permissions' => Spatie\Permission\Models\Permission::count(), 'settings' => App\Models\WebsiteSetting::count(), 'staff' => App\Models\User::where('account_type', 'staff')->count(), 'specialties' => App\Models\Specialty::count(), 'clinics' => App\Models\Clinic::count(), 'support' => App\Models\Clinic::where('type', 'penunjang')->count(), 'doctors' => App\Models\Doctor::count(), 'active_doctors' => App\Models\Doctor::where('status', 'active')->count(), 'pivot' => DB::table('doctor_specialties')->count(), 'media' => App\Models\Media::count(), 'photos' => App\Models\Media::where('collection', 'photo')->count(), 'covers' => App\Models\Media::where('collection', 'cover')->count()]);"
& $PHP artisan media:prune --dry-run; "exit=$LASTEXITCODE"
```
Expected: semua migration `DONE`; seeder `RolePermissionSeeder`, `SettingsSeeder`, `DemoSeeder`, `DemoUserSeeder`, `SpecialtySeeder`, `ClinicSeeder`, `DoctorSeeder` `DONE`; keluaran tinker `{"roles":3,"permissions":19,"settings":19,"staff":3,"specialties":13,"clinics":17,"support":4,"doctors":18,"active_doctors":16,"pivot":20,"media":32,"photos":16,"covers":16}`; `media:prune --dry-run` mencetak `Baris media yatim: 0`, `Berkas yatim: 0`, `Mode uji: tidak ada yang dihapus.`, dan `exit=0`.

- [ ] **Step 17: Daftar route admin dan publik**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
$expected = @(
  'admin.dashboard', 'admin.login', 'admin.login.store', 'admin.logout', 'admin.password.edit', 'admin.password.update',
  'admin.doctors.index', 'admin.doctors.show', 'admin.doctors.create', 'admin.doctors.store', 'admin.doctors.edit', 'admin.doctors.update', 'admin.doctors.status', 'admin.doctors.archive', 'admin.doctors.photo',
  'admin.specialties.index', 'admin.specialties.create', 'admin.specialties.store', 'admin.specialties.edit', 'admin.specialties.update', 'admin.specialties.destroy',
  'admin.clinics.index', 'admin.clinics.create', 'admin.clinics.store', 'admin.clinics.edit', 'admin.clinics.update', 'admin.clinics.destroy',
  'admin.media.editor-upload', 'admin.media.reorder', 'admin.media.update', 'admin.media.destroy'
)
$actual = (& $PHP artisan route:list --path=admin --except-vendor --json | ConvertFrom-Json) | ForEach-Object { $_.name } | Where-Object { $_ }
"admin: $($actual.Count) route"
"hilang: " + (($expected | Where-Object { $_ -notin $actual }) -join ', ')
"berlebih: " + (($actual | Where-Object { $_ -notin $expected }) -join ', ')
& $PHP artisan route:list --name=clinics --except-vendor
```
Expected: `admin: 31 route`; `hilang:` dan `berlebih:` kosong; `route:list --name=clinics` memuat `GET|HEAD layanan` (`clinics.index`) dan `GET|HEAD layanan/{clinic:slug}` (`clinics.show`) (nama `admin.clinics.*` juga ikut tercetak oleh filter nama; yang diperiksa hanya dua route publik).

- [ ] **Step 18: Smoke publik (opsional bila Apache aktif)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
foreach ($u in '/layanan', '/layanan/poliklinik-anak', '/layanan/poliklinik-gigi', '/layanan/igd-24-jam') {
  curl.exe -s -o NUL -w "$u => %{http_code}`n" "http://unpam-hospital.test$u"
}
```
Expected: keempatnya `200`.

- [ ] **Step 19: MANUAL (pemilik) — CRUD dokter/poliklinik/spesialisasi, editor dengan unggah gambar, halaman publik #4/#5**

Implementer **berhenti di langkah ini**, melapor `NEEDS_CONTEXT` beserta instruksi berikut kepada pemilik, lalu melanjutkan setelah pemilik mengonfirmasi hasilnya.

Prasyarat: `C:\xampp3\xampp-control.exe` → Apache dan MySQL **Start**; Step 16 sudah dijalankan; `php artisan storage:link` sudah ada (`public\storage`); siapkan satu foto JPG/PNG ≥ 800×800 px (< 2 MB) dan satu berkas `.svg` apa pun.

1. **Login dan menu.** Login `konten@unpamhospital.test` (`Demo12345`) di `http://unpam-hospital.test/admin/login` (logo login besar tunggal, email terfokus). Sidebar menampilkan **Dokter** dan **Poliklinik dan Spesialisasi**. Login `pendaftaran@unpamhospital.test`: hanya **Dokter** terlihat; `/admin/poliklinik` dan `/admin/dokter/buat` memberi halaman 403.
2. **Spesialisasi** (`/admin/spesialisasi`): tab Poliklinik/Spesialisasi tampil. Tambah "Contoh Baru" (tanpa kolom slug) ⇒ flash "Spesialisasi berhasil ditambahkan."; ubah deskripsi; nonaktifkan lewat form ubah. Coba hapus "Urologi" (dipakai dokter dan poliklinik) ⇒ flash `error` dengan saran "Nonaktifkan saja." dan data tetap. Hapus "Contoh Baru" ⇒ berhasil.
3. **Poliklinik** (`/admin/poliklinik`): tambah poliklinik dengan `code` `XYZ`, tipe Poliklinik, bisa didaftar; isi deskripsi di editor: ketik teks, tebalkan, buat daftar berpoin, lalu **sisipkan gambar** (pilih foto; modal meminta teks alternatif — kosongkan dulu dan pastikan ditolak; isi lalu simpan). Coba unggah `.svg` di editor ⇒ ditolak dengan pesan jelas. Unggah cover dan dua foto galeri. Simpan. Coba `code` `XYZ` lagi pada poliklinik lain ⇒ galat "Kode poliklinik sudah digunakan.". Ubah ke tipe Penunjang ⇒ `is_bookable` ditolak/otomatis tidak aktif. Hapus poliklinik yang berisi dokter (mis. Poliklinik Anak) ⇒ ditolak dengan flash `error`; hapus `XYZ` ⇒ berhasil dan gambarnya ikut terhapus.
4. **Dokter** (`/admin/dokter`): filter bawaan "Aktif" (16 baris); coba chip Nonaktif (Eko Susanto), Diarsipkan (Lilis Marlina), Semua (18). Tambah dokter dengan nama sama seperti dokter lain (mis. "Laras Wulandari") ⇒ tersimpan dengan slug berakhiran `-2`; pilih 2 spesialisasi dan satu **Utama**; unggah foto dengan alt. Ubah dokter: slug **tidak** berubah. Di halaman detail: ganti foto ⇒ foto lama hilang dari tampilan; nonaktifkan (modal konfirmasi), aktifkan lagi; arsipkan lalu pulihkan (hasil: Nonaktif). Dokter dengan form kosong ⇒ pesan galat Indonesia per field, ringkasan galat di atas, isian lama bertahan.
5. **Halaman publik #4** (`/layanan`, tanpa login): dua bagian "Poliklinik" (13) dan "Layanan penunjang" (4); kartu poliklinik berlabel "Dapat didaftar online"; Poliklinik Gigi memakai gambar placeholder (tanpa cover). Menu "Layanan" aktif. Ubah lebar jendela ke 360 px: kartu satu kolom.
6. **Halaman publik #5**: buka `/layanan/poliklinik-penyakit-dalam` (breadcrumb Beranda › Layanan › …, cover, deskripsi dengan daftar, kartu dokter hanya yang aktif: dr. Sekar dan dr. Bagas; dr. Maya tampil di Poliklinik Jantung dengan spesialisasi utama Jantung). Poliklinik dengan galeri (yang Anda buat di butir 3): klik foto ⇒ modal; panah kiri/kanan dan Esc bekerja. `/layanan/igd-24-jam`: catatan "Layanan ini tidak dapat didaftar online. Hubungi kami di …", tanpa tombol daftar. Nonaktifkan sebuah poliklinik dari admin lalu buka URL-nya (login sebagai staf **dan** sebagai tamu) ⇒ keduanya 404.
7. **Keluar dari akun Admin Konten dan ulangi butir 5–6 sebagai tamu**; periksa tidak ada permintaan ke domain luar di tab Network (font dan ikon lokal).
8. Pemulihan (opsional): `& $PHP artisan migrate:fresh --seed` agar database pengembangan kembali ke data demo murni.

Expected: butir 1–7 sesuai; lapor hasil per butir. Kasus manual yang tertutup: `CRD-01`–`CRD-04`, `MED-01`/`MED-02`/`MED-08` (bagian manual OFF-03), `VAL-01`, `RSP-04` (360 px), `A11-04`/`A11-06`, `AKS-04`/`AKS-05` secara manual, `SEED-01`/`SEED-03` (data demo).

Laporan gerbang fase 2 ke koordinator: keluaran Step 15–18, hasil MANUAL Step 19, dan status kasus:
- `AKS-01`–`AKS-05`: lulus bila `AdminPageMatrixTest` hijau (baris 4, 5, 33, 33b, 34 + mutasi); `KNS-02`: `AdminRouteMiddlewareTest` hijau.
- `CRD-01`–`CRD-04`, `MED-01`, `MED-02`, `MED-08`, `VAL-01`: lulus bila suite dan MANUAL hijau.
- `SEED-01` (bagian master data), `SEED-02`, `SEED-03`: `DemoSeederInvariantsTest`, `SeederProductionGuardTest`, `media:prune --dry-run` = 0.

"Catatan Eksekusi" dan `docs/superpowers/CHECKPOINT.md` tidak diisi di tugas ini: koordinator mengerjakannya setelah final review (commit akhir dan tag `checkpoint/<tanggal>-fase-2`).


---


## Catatan Eksekusi (2026-10-05)

Dieksekusi dengan `superpowers:subagent-driven-development`. Semua peran memakai subagent **Sonnet 5.5** (preferensi pemilik): implementer, reviewer per tugas, re-reviewer, final reviewer, dan implementer gelombang perbaikan final. Eksekusi berlangsung 2026-10-04/05 dalam satu sesi panjang. Beberapa subagent sempat terputus karena galat jaringan API (ENOTFOUND), lalu dilanjutkan tanpa kehilangan pekerjaan.

Cabang `fase-2-master-data` berisi 47 commit kerja di atas `master` (`f1ecb15`..`fb4ab7d`), ditambah commit catatan `d69ea9a`, perbaikan hasil uji manual `929c13a`, dan commit pembaruan catatan ini.

**Hasil akhir:**

| Pemeriksaan | Hasil |
|---|---|
| `artisan test` | **1845 tes lulus (8479 asersi)** setelah perbaikan uji manual; sebelumnya 1842 di `fb4ab7d` |
| `pint --test` | PASS |
| `npm run build` | Sukses; manifest memuat `app.scss`, `app.js`, `admin.js`, `editor.js` |
| `route:list --path=admin` | 31 route (6 fase 1 + 25 fase 2); daftar nama persis sama dengan Step 17 |
| `AdminPageMatrixTest` + `Phase2RoutePermissionTest` + `AdminRouteMiddlewareTest` | 184 tes hijau (AKS-01–AKS-06, KNS-02) |
| `migrate:fresh --seed` (DB pengembangan, Step 16) | OK setelah izin pemilik (percobaan pertama ditolak pengaman auto mode). Hitungan persis: 3 peran, 19 izin, 19 pengaturan, 3 staf, 13 spesialisasi, 17 layanan (4 penunjang), 18 dokter (16 aktif), 20 pivot, 32 media (16 foto, 16 cover). `media:prune --dry-run`: 0 baris, 0 berkas, exit 0. |
| Smoke publik (Step 18) | `/layanan`, `/layanan/poliklinik-anak`, `/layanan/poliklinik-gigi`, `/layanan/igd-24-jam`, `/admin/login` semuanya 200. |
| MANUAL (Step 19) | Dijalankan koordinator lewat Playwright; laporan bergambar ada di `Documents\UNPAM-Uji-Manual-2026-10-05\index.html`. Butir 1–7 sesuai setelah dua cacat diperbaiki (`929c13a`): (M1) `getSemanticHTML()` Quill 2 mengubah setiap spasi menjadi `&nbsp;` sehingga teks #5 patah di tengah kata di ponsel, kini dinormalkan di `editor.js` dan `SanitizesRichText`; (M2) tombol panah galeri modal tidak berfungsi karena instance Carousel belum dibuat, kini dibuat di `initGalleryModals()`. Keduanya diverifikasi ulang lewat Playwright. |

**Putaran perbaikan per tugas** (masing-masing satu putaran, semuanya lulus re-review):

| Tugas | Perbaikan |
|---|---|
| Task 2 | `mediaIn` N+1 senyap diganti `getRelationValue` |
| Task 7 | Umpan balik editor tampil, inisialisasi aman, pesan HTTP Indonesia, ARIA |
| Task 11 | Tautan ringkasan galat `specialty_ids` |
| Task 16 | Kalimat slug/status basi di blueprint 05/07/09/11 |

**Final review** (seluruh cabang): tidak ada temuan Critical. Ada tiga temuan Important dan dua Minor murah, dan semuanya ditutup dalam satu gelombang perbaikan (`fb4ab7d`, F1–F5). Re-review menyatakan kelimanya ADDRESSED tanpa kerusakan baru.

### Keputusan (rulings) selama eksekusi

| ID | Keputusan | Biaya bila keliru |
|---|---|---|
| X1 | Tanpa git worktree; bekerja di checkout utama pada cabang `fase-2-master-data` (sama dengan R1 fase 1). | Cabang aktif checkout utama berpindah selama eksekusi. |
| X2 | Semua subagent, termasuk final review, memakai Sonnet 5.5 (preferensi pemilik). | Final review sedikit kurang tajam; dimitigasi review per tugas. |
| X3 | Scan pra-eksekusi S1–S13 ditambal ke rencana (`539ffbb`). Isinya: `sortUrl` membuang `kembali`/`page`; callback render JSON (S2); `ClinicSeeder::shortDescription`; `&amp;quot;`; teks dan jumlah RED; pint sebelum `--test`; byte Latin-1 di `AppServiceProvider`; `messages.delete_blocked`; teks Review Focus. | Kecil. |
| X4 | `HasMedia::mediaIn()` memakai `getRelationValue('media')` agar lazy load terdeteksi strict mode (Task 2). | Tidak ada. |
| X5 | `x-form.checkbox` bernama larik menyaring `old()` dengan `is_scalar` (Task 5). | Tidak ada. |
| X6 | Tes tamu memakai `forgetGuards()` (Task 6). `shouldRenderJsonWhen` mengembalikan JSON bila `expectsJson()`, untuk `admin.dashboard.chart`, atau untuk `admin.media.*` bila exception bukan `ValidationException` (S2). | Tamu di `admin.media.*` menerima 401 JSON, bukan redirect. |
| X7 | `StoreDoctorRequest::after()` memindahkan pesan `specialty_ids.*` ke kunci tunggal `specialty_ids` (Task 11). | Galat spesialisasi tampil di akhir ringkasan. |
| X8 | Asersi autofocus memakai `preg_match_all('/\sautofocus(?=[\s=>])/')`, karena `x-form.input` merender `autofocus="autofocus"` (Task 15). | Tidak ada. |
| X9 | Task 16 dipecah: Step 1–15 dan 17 oleh implementer; Step 16, 18, dan 19 oleh koordinator. DB pengembangan diperiksa dulu (3 akun demo, 29 audit login, 0 media). `migrate:fresh --seed` sempat ditolak pengaman auto mode, lalu dijalankan setelah pemilik mengizinkan ("izinkan seed ulang dan lanjutkan uji manual"). | Pemilik harus login ulang (sesi lama tidak valid). |
| X10 | Final F1: `MediaService::syncEditorImages()` per 08 §5.2, ditambah `Media::orphaned()` berbasis `updated_at` dan guard `EDITOR_REFERENCE_COLUMNS` di `prune` (D-55). Tanpa ini, `media:prune` menghapus gambar editor yang dipakai di deskripsi poliklinik. Rencana melewatkan 08 §5.2. | Tes prune lama kini memundurkan `created_at` dan `updated_at`. |
| X11 | Final F2: data dokter publik hanya memuat spesialisasi aktif (D-56, Review Focus 4). | Lencana kartu publik berubah bila spesialisasi utama nonaktif. |
| X12 | Final F3: `cover_alt`/`photo_alt` tanpa berkas baru memperbarui alt media yang ada; alt kosong ditolak (D-57). | Kecil. |
| X13 | Final F4/F5: `.admin-shell` `100dvh`; rich text yang kosong secara visual disimpan NULL (D-58). | Tidak ada. |
| X14 | Uji manual M1/M2 diperbaiki sebelum merge (`929c13a`). U+00A0/`&nbsp;`/`&#160;` dinormalkan menjadi spasi di klien dan di `SanitizesRichText`. Data yang telanjur tersimpan dengan `&nbsp;` ikut terperbaiki saat disimpan ulang. Carousel galeri dibuat sejak awal (`keyboard: true`, tanpa putar otomatis). Perubahan produksi hanya 11 baris: dibaca utuh oleh koordinator dan diverifikasi lewat Playwright tanpa reviewer subagen terpisah. | `&nbsp;` yang disengaja (jarang) ikut menjadi spasi biasa. |

### Bawaan (carry) untuk rencana fase berikutnya

**Fase 3 (Jadwal dokter):**
- Isi titik sambung `DoctorService::futureActiveAppointmentsCount()` dan `ClinicRequest::clinicHasAppointments()`.
- Uji end-to-end penolakan arsip dokter yang memiliki janji mendatang.
- Asersi URL publik dokter (D-54).
- `applyDoctorInactive()`.
- `x-doctor-card` dengan jadwal terdekat (F2-R9).
- Tombol "Daftar Kunjungan" dan "Jadwal Dokter".

**Fase 4a (Halaman/Artikel):**
- Tambahkan kolom rich text baru ke `MediaService::EDITOR_REFERENCE_COLUMNS` dan `richTextAttributes()` model (D-55).
- `Page` untuk judul halaman #4 (F2-R10).

**Minor yang ditunda (tidak memblokir):**
- Suite ±150 s karena ±11 tes menjalankan `DemoSeeder` penuh. Opsi: seed sekali per kelas di `DemoSeederInvariantsTest`.
- Galat `alt_text` galeri belum memakai `errorBag` per media.
- Seeder tanpa transaksi per baris.
- Gambar editor yang dirujuk dua poliklinik ikut terhapus bila poliklinik pertama dihapus.
- Ganti status yang sama memberi flash sukses tanpa perubahan.
- `ValidationException` balapan arsip tanpa flash.
- Bagian "Dokter di poliklinik ini" tampil untuk layanan penunjang.
- `aria-label` galeri berakhir ": " bila alt kosong.
- `og:image` tetap dipasang walau berkas hilang.
- Tes `PlaceholderImageGenerator` jalur TrueType/fallback lemah.
- Redaksi 05 "nonaktif memicu di fase 3".

**Catatan uji manual:**
- Logo kecil di header publik dan sidebar admin masih tampak sempit (cacat fase 1 yang diketahui, sengaja tidak diubah di Task 15).
- DB pengembangan kini memuat sedikit data uji: dokter "Laras Wulandari Putri" (Nonaktif), serta dua foto galeri dan satu kalimat tambahan di Poliklinik Penyakit Dalam. Untuk data demo murni, jalankan `migrate:fresh --seed`.
