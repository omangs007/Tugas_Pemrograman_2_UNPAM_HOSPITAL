# Fase 4b — Fasilitas, Kamar Rawat Inap, dan Kontak: Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fase ini melengkapi situs publik dan CMS bagian 4b:
- skema dan model `facilities`, `room_categories`, `contact_messages`;
- admin #36 (Fasilitas, Kamar dan Kapasitas, termasuk pembaruan ketersediaan manual oleh Petugas) dan kotak pesan kontak;
- halaman publik #9 Daftar Fasilitas, #10 Detail Fasilitas, #11 Ringkasan Kamar, #12–#15 Detail Kamar, dan #22 Kontak dan Lokasi dengan komponen `x-map-embed` dan formulir pesan;
- blok ketersediaan kamar di beranda;
- seeder demo `RoomCategorySeeder`, `FacilitySeeder`, `ContactMessageSeeder`.

Dengan fase ini kedua puluh dua halaman publik lengkap.

**Architecture:** Arsitektur fase 1–4a tetap berlaku: monolit Laravel 12, controller tipis, Form Request, Policy per model, pelaku `Actor`, dan audit lewat `AuditObserver`.
- **Tanpa service baru.** CRUD fasilitas dan kategori kamar mengikuti pola P-CRUD poliklinik fase 2 (`ClinicController`/`ClinicRequest`: `cover` + `gallery`, rich text, `MediaService`).
- **Ketersediaan kamar** diubah lewat satu aksi terpisah (`admin.room-categories.availability`), dalam transaksi dengan `lockForUpdate`, `saveQuietly()`, dan audit eksplisit `room.availability_updated`. Mengubah deskripsi atau tarif tidak menyentuh waktu pembaruan.
- **Pesan kontak** disimpan tanpa IP mentah (`ip_hash`). Status diubah oleh staf `messages.manage`, dengan audit eksplisit `contact_message.status_changed` yang tidak memuat data pribadi pengirim.
- **`x-map-embed`** adalah komponen kelas yang selalu merender alamat teks dan tombol Petunjuk Arah. Iframe Google Maps opsional; fallback-nya dikerjakan `initMapEmbeds()` di `app.js`, tanpa listener `error` baru.

**Tech Stack:** sama dengan fase 4a. PHP 8.2.12 (XAMPP `C:\xampp3`), Laravel 12.69, MariaDB 10.4.32, spatie/laravel-permission 6.25, mews/purifier, Intervention Image (GD), Bootstrap 5.3 + Sass + Vite 7, Quill 2, PHPUnit 11, Pint. **Tanpa paket baru.**

**Spec:** `docs/superpowers/specs/2026-10-02-unpam-hospital-blueprint/`. Bagian utama yang dipakai:

| Dokumen | Bagian |
|---|---|
| 00 | C (aturan hapus), D (enum), E.4/E.5 (kamar agregat, `pages` key 4b), E.6/K (media), E.7 (pengaturan), G (izin), I.1/I.4 (route), J |
| 14 | §5 Fase 4 (bagian 4b) |
| 02 | F-PUB-08, F-PUB-09, F-PUB-15, F-PUB-16, F-ADM-09, F-ADM-10, F-ADM-11, F-ADM-24 |
| 04 | §2, §4 (layar 36 dan kotak pesan), §5 Policy |
| 05 | §3 (navigasi), §4.1 halaman 9–15 dan 22, §4.3/§5.2 (#36), §5.1, §6.3, §7 (SEO), §8 (breadcrumb) |
| 06 | §12, §13 (ketersediaan kamar), §17 (form kontak dan status pesan) |
| 07 | W-07 (kamar), W-08 (kontak), W-15 (daftar admin), §7–§8 |
| 08 | §3 domain 3, §5.1 media |
| 09 | §5.1 `room_categories`, §5.2 `facilities`, §6.8 `contact_messages`, §10, §13 |
| 11 | §2.1, §2.3 F, §4.1, §4.4, §5.5, §8, §9, §10.3, §11.3 |
| 12 | BR-111–BR-120, BR-122, BR-123, BR-133, BR-141, BR-143, BR-146, BR-175, BR-180, V-27, V-28, V-29, V-37, V-38, §3.2, §8 |
| 13 | §2.1, §8.1, §8.2, §9.2, §10.4, §11.2 |
| 15 | AKS-01–AKS-05, CRD-06, CRD-07, CRD-12, CRD-13, SEK-06–SEK-10, SEK-14, SEK-15, EMP-01, EMP-03, EMP-05, VAL-04, VAL-07, OFF-04, RSP-01, A11-05 |
| 17 | D-12, D-16, D-21, D-23, D-30, D-31, D-38, D-40–D-47, D-55, D-58, D-104–D-123 |

Ringkasan kutipan spesifikasi dan kode untuk fase ini ada di `.superpowers/plan-fase-4b/digest-{data,http,tests-carry,kode}.md`, beserta kerangka kontrak (`kerangka.md`). Berkas-berkas itu hanya bahan penulisan dan tidak ikut di-commit. Isi kerangka yang mengikat sudah disalin ke rencana ini. Rujukan "kerangka B", "kerangka C" (C.1–C.6), "kerangka E", dan "kerangka F" di teks tugas menunjuk ke bagian berikut di bawah:
- **Keputusan (rulings) fase 4b**;
- **Nama baru yang diperkenalkan fase 4b**;
- **Susunan halaman**;
- **Review Focus**.

## Global Constraints

- **XAMPP dan Prelude P.**
  - XAMPP ada di **`C:\xampp3`** (PHP 8.2.12). Apache dan MySQL harus di-**Start** sebelum tes.
  - Setiap blok PowerShell diawali **Prelude P**:
    `$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP`
- **Perintah dan paket.** PHP dijalankan dengan `& $PHP …`. Tidak ada paket Composer/npm baru.
- **Suite penuh** kini ±4350 tes dan ±11–14 menit, melebihi batas 10 menit sebuah perintah tool. Jalankan di latar belakang dengan keluaran ke berkas, lalu tunggu hasilnya.
- **Kompatibilitas PHP 8.2–8.4.**
  - Dilarang memakai fitur khusus 8.3+: typed class constants, `#[\Override]`, `json_validate()`, property hooks, `new` tanpa kurung pada chaining.
  - Berkas baru di `app/Services`, `app/Support`, `app/Rules`, `app/Policies`, dan `app/View/Components` diawali `declare(strict_types=1);`.
- **Nama.**
  - Nama tabel, kolom, route, URI, izin, Form Request, Policy, method, view, dan teks UI harus **persis** seperti 00/05/09/11/12/13 dan bagian "Nama baru" di bawah.
  - Nama baru hanya yang tercantum di bagian "Nama baru yang diperkenalkan fase 4b".
  - **Enum di `app/Enums` tidak boleh ditambah atau diubah**, karena `EnumCatalogTest` mengunci 15 berkas. `FacilityCategory` dan `ContactMessageStatus` sudah ada; ketersediaan kamar bukan enum.
  - **Izin tidak boleh ditambah.** Tetap 19 izin; `facilities.manage`, `rooms.update-availability`, dan `messages.manage` sudah ada di `RolePermissionSeeder`.
- **Bahasa.**
  - Identifier kode berbahasa Inggris.
  - Teks UI, pesan validasi, flash, deskripsi audit, dan komentar berbahasa Indonesia.
  - Flash berupa kalimat lengkap yang berakhir titik.
- **Ketersediaan kamar.** Kata **"real-time"**, "langsung", dan "live" tidak dipakai di UI ketersediaan kamar (01, 02 F-NXT-08). Label waktu persis: "Diperbarui manual oleh petugas pada {tanggal} {jam} WIB" (F4B-R10).
- **Waktu.**
  - Zona aplikasi `Asia/Jakarta`.
  - "Sekarang" (`availability_updated_at`, `consent_at`, `handled_at`, label kedaluwarsa 24 jam) selalu diambil dari `now()` aplikasi, **tidak pernah** dari `NOW()` SQL.
  - Tes waktu memakai `$this->travelTo(...)`.
- **Mode ketat.**
  - `Model::shouldBeStrict()` aktif di non-produksi. Relasi yang dipakai view, accessor, atau komponen (`media`, `availabilityUpdater`, `handler`) wajib di-eager-load.
  - Tes daftar memakai beberapa baris sekaligus.
  - Kolom yang tidak *fillable* (`contact_messages.handled_by`, `handled_at`) diisi lewat `forceFill`.
- **Database tes.** Tes memakai MariaDB `unpam_hospital_test` dengan `RefreshDatabase`. Peran dibuat lewat `InteractsWithStaff`, dan data 4b lewat `InteractsWithFacilities` (T2). Tes yang memakai `MediaService` memanggil `Storage::fake('public')`.
- **Migration.**
  - Migration yang sudah ada **tidak boleh** diubah.
  - Migration fase 4b memakai nama 09 §10 persis:
    - `2026_10_02_001200_create_room_categories_table` (+ CHECK `chk_room_categories_beds` lewat `DB::statement`)
    - `2026_10_02_001300_create_facilities_table`
    - `2026_10_02_002000_create_contact_messages_table`
- **Output HTML.**
  - `{!! !!}` **hanya** untuk lima kolom rich text, dan `RawOutputScanTest` mengunci lima baris itu:
    - `clinics.description`;
    - `pages.body`, di partial `public/partials/_page-body.blade.php`;
    - `articles.body`, di `public/articles/show.blade.php`;
    - `facilities.description`, di `public/facilities/show.blade.php`;
    - `room_categories.description`, di `public/rooms/show.blade.php`.
  - Isi pesan kontak (`name`, `email`, `phone`, `subject`, `message`) dan semua kolom lain memakai `{{ }}`.
  - Tidak boleh ada atribut event inline, tidak ada string `onerror`, dan tidak ada CDN.
  - CSP tidak diubah (`frame-src https://www.google.com https://maps.google.com` sudah ada; `SecurityHeadersTest` menguncinya).
  - Tidak ada entri Vite baru dan tidak ada listener `error` kedua (`FrontendManifestTest`, `RawOutputScanTest`).
  - Tautan eksternal hanya `wa.me`, `map.directions_url`, iframe `map.embed_url`, dan `website_url` mitra, dengan `target="_blank" rel="noopener noreferrer"` untuk tautan.
- **Route.**
  - Route admin 4b berada di dalam grup G-ADMIN `routes/admin.php`, disisipkan sebelum komentar `// J. Media`, dengan middleware `permission:*` persis seperti F4B-R13.
  - Parameter bernama `{facility}`, `{roomCategory}`, dan `{message}`; batas numeriknya sudah ada di `bootstrap/app.php`.
  - Route publik ditulis di `routes/web.php` dengan binding slug eksplisit `{facility:slug}` dan `{roomCategory:slug}`.
  - Tidak ada route `admin.messages.destroy` (00 C; `Phase3RoutePermissionTest`).
  - Total route admin menjadi **106** (90 + 16).
- **Super Admin.** `Gate::before` meloloskan Super Admin dari semua Policy. Aturan mutlak berikut ditegakkan di **Form Request** atau controller:
  - `beds_available` tidak boleh melebihi `bed_capacity`;
  - `bed_capacity` tidak boleh di bawah `beds_available` saat ini;
  - pesan tidak dapat kembali ke `new`;
  - galeri maksimal 20 gambar.
- **Penulisan berkas.**
  - Pakai tool Write/Edit. Repo memakai akhiran baris **LF**, dan setiap `old` pada Edit harus cocok tepat satu kali.
  - Bila menulis lewat PowerShell, pakai `[IO.File]::WriteAllText(path, text, (New-Object System.Text.UTF8Encoding $false))`.
  - Jangan menyalin baris pagar Markdown (```` ``` ````) dari rencana ke berkas kode.
  - `.env*` tidak pernah di-commit.
- **Cabang dan commit.**
  - Cabang kerja `fase-4b-fasilitas-kamar-kontak` (sudah dibuat dari `master`).
  - Format pesan commit: `tipe(fase-4b): ringkasan`, diakhiri `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`. Implementer subagen boleh memakai atribusi harness-nya.
  - Di setiap commit `artisan test` hijau dan `vendor\bin\pint --test` bersih.
  - Bila menyentuh `resources/js` atau `resources/scss`, `npm run build` harus sukses dan dijalankan sebelum suite.
- **Langkah MANUAL.** Langkah bertanda **MANUAL (koordinator)** dijalankan koordinator lewat Playwright di akhir fase. Implementer melewatinya.
- **Database pengembangan.** Implementer **tidak** menjalankan `migrate`, `migrate:fresh`, `db:seed`, atau `sessions:generate` pada database pengembangan `unpam_hospital`. Langkah itu dijalankan koordinator dengan izin eksplisit pemilik di gerbang Task 11.

## Review Focus

Setiap butir punya tes di tugas pemiliknya (ditunjuk dengan →).

1. **Batas ketersediaan dan izin kamar.**
   - Kasus:
     - `beds_available` = 0 (badge "Penuh"), = kapasitas, kapasitas + 1, −1, `"abc"`, larik;
     - kapasitas diturunkan di bawah ketersediaan saat ubah;
     - Petugas Pendaftaran membuka `admin.room-categories.index` dan memperbarui ketersediaan, tetapi tidak dapat membuat/mengubah/menghapus (403) atau mengubah gambar kamar;
     - pembaruan tepat 24 jam versus 24 jam + 1 menit.
   - Harapan: galat field berbahasa Indonesia, CHECK tidak pernah memicu 500, `availability_updated_*` terisi, satu audit `room.availability_updated`.
   - → T2 (accessor), T5, T8.
2. **Penyalahgunaan form kontak.**
   - Kasus:
     - kiriman ke-4 dalam 10 menit;
     - honeypot terisi;
     - `<script>` di nama/subjek/pesan;
     - tanpa persetujuan;
     - telepon `0812-3456-7890`, `+62 812 3456 7890`, dan `021555`;
     - `message[]=x`;
     - CSRF hilang.
   - Harapan:
     - throttle → 302 + flash + `Retry-After` tanpa baris;
     - honeypot → sukses palsu tanpa baris;
     - teks tersimpan apa adanya lalu ter-escape di admin;
     - telepon tersimpan ternormalisasi atau ditolak;
     - `ip_hash` 64 heksadesimal ≠ IP;
     - tidak pernah 500.
   - → T9, T6.
3. **Slug tidak dikenal, nonaktif, dan staf.**
   - Kasus:
     - `/fasilitas/{nonaktif}`, `/kamar-rawat-inap/{nonaktif}`, slug acak, juga saat staf login;
     - kategori fasilitas tanpa isi;
     - DB kosong.
   - Harapan: 404 untuk tak tampil; kategori kosong disembunyikan; `x-empty-state` (EMP-01); tanpa `LazyLoadingViolationException`.
   - → T7, T8.
4. **Unggahan berbahaya di form fasilitas dan kamar.**
   - Kasus: SVG berskrip, `shell.php.jpg`, teks `.jpg`, cover < 800×450, galeri 21 gambar (yang ada + baru), alt kosong.
   - Harapan: galat field, tanpa baris `media` dan tanpa berkas.
   - → T4, T5.
5. **Status pesan dan privasi.**
   - Kasus:
     - membuka pesan `new` dua kali;
     - `status=new`;
     - arsip → pulihkan;
     - `replied` mengisi `handled_*`;
     - Admin Konten mengakses `admin.messages.*`;
     - deskripsi audit.
   - Harapan: hanya satu audit buka; `new` ditolak; Admin Konten 403; audit tanpa nama, email, subjek, atau isi.
   - → T6.

## Keputusan (rulings) fase 4b

Keputusan ini mengikat semua tugas. Butir yang menyimpang dari blueprint direkonsiliasi ke dokumen di Task 11 (D-124 dan seterusnya).

- **F4B-R1 Cakupan.** Fase 4b mencakup F-PUB-08, F-PUB-09, F-PUB-15, F-PUB-16, F-ADM-09, F-ADM-10, F-ADM-11, dan F-ADM-24.
  - Halaman publik #9–#15 dan #22, admin #36 (dua tab), dan kotak pesan.
  - Blok kamar beranda (ditunda dari 4a, D-112).
  - Halaman pengaturan situs (`settings.manage`, F-ADM-25) tetap fase 8.
  - Lencana angka "Pesan Kontak" di sidebar dan widget dasbor (ringkasan kamar, pesan baru) juga tetap fase 8.
- **F4B-R2 Migration.** Tiga berkas memakai nama 09 §10 persis: `2026_10_02_001200_create_room_categories_table`, `…001300_create_facilities_table`, `…002000_create_contact_messages_table`.
  - Kolom, tipe, default, index, dan FK mengikuti 09 §5.1, §5.2, §6.8 persis (`digest-data.md` §1).
  - CHECK `chk_room_categories_beds (beds_available >= 0 AND beds_available <= bed_capacity)` ditulis lewat `DB::statement` setelah `Schema::create`.
  - `amenities` memakai `$table->json()`, sehingga CHECK `json_valid` dibuat MariaDB otomatis.
  - Pada DB yang sudah memigrasikan 1–4a, ketiganya berjalan sebagai migration tertunda (pola D-59).
- **F4B-R3 Batas rich text.** `description` fasilitas dan kategori kamar memakai `['nullable', 'string', 'max:30000']` dengan pesan "Deskripsi maksimal 30.000 karakter.", sama dengan `ClinicRequest` fase 2 dan 09 §1 butir 10.
  - Ini menyimpang dari 12 V-27/V-28 (`max:50000`), karena 50.000 karakter multibyte dapat melampaui `TEXT` 65.535 byte.
- **F4B-R4 Panjang field.** Kolom mengikuti 09 dan aturan form mengikuti 12, yang lebih ketat:
  - `name` maks 100;
  - `short_description` maks 255;
  - `slugMaxLength()`: fasilitas 170, kamar 120.
- **F4B-R5 Slug.** Form admin tidak punya input slug (D-31, F2-R5, F4A-R7). `HasUniqueSlug` membuat slug dari `name` dan tidak mengubahnya saat nama diedit (BR-117). Seeder mengisi slug eksplisit (13 §8.1–§8.2). Isi 11 tentang `slug` di `FacilityRequest`/`RoomCategoryRequest` tidak dipakai.
- **F4B-R6 Throttle kontak.** Ikuti D-23 dan handler fase 1 di `bootstrap/app.php`, tanpa teks baru:
  - kiriman ke-4 dalam 10 menit dari IP yang sama → redirect 302 kembali ke `/kontak` dengan flash `error` "Terlalu banyak percobaan. Silakan coba lagi dalam {n} detik.", header `Retry-After`, dan input lama dipertahankan;
  - tidak ada baris tersimpan.

  SEK-14 "429" dibaca sebagai respons ini. Teks 05/06/07 lain tidak dipakai.
- **F4B-R7 Honeypot.** Field tersembunyi `website` di dalam `<div class="d-none" aria-hidden="true">` (input `type="text"`, `tabindex="-1"`, `autocomplete="off"`, label "Situs web").
  - Field ini **tidak** ada di `rules()`.
  - Controller memeriksa `$request->isHoneypotFilled()`. Bila terisi, controller mengalihkan ke `contact` dengan flash sukses yang sama, tanpa menyimpan apa pun (06 §17).
- **F4B-R8 Teks flash kontak.**
  - Sukses: "Terima kasih. Pesan Anda telah kami terima dan akan ditindaklanjuti oleh petugas kami." (06 §17; teks W-08 tidak dipakai).
  - Persetujuan: "Anda harus menyetujui pengolahan data untuk mengirim pesan."
- **F4B-R9 Badge ketersediaan kamar.** Hanya dua badge (05/07):
  - `beds_available > 0` → "Tersedia" (kelas `text-bg-success`, ikon `bi-check-circle`);
  - `beds_available = 0` → "Penuh" (kelas `text-bg-danger`, ikon `bi-x-circle`).

  Badge selalu berisi teks + ikon + warna. Status "Terbatas" (06 [ASUMSI]) tidak dibuat. Badge dirender partial `public/rooms/_availability-badge.blade.php` (variabel `$room`).
- **F4B-R10 Teks ketersediaan.** Accessor `RoomCategory`:
  - `occupancy_label` = "{beds_available} dari {bed_capacity} tempat tidur tersedia" (11 §5.5);
  - `availability_updated_label` = "Diperbarui manual oleh petugas pada {j M Y}, pukul {H.i} WIB" (`translatedFormat('j M Y')` dan `format('H.i')`, 02 F-NXT-08), atau "Belum diperbarui" bila `availability_updated_at` NULL;
  - `is_availability_stale` bernilai benar bila NULL atau lebih tua dari `RoomCategory::STALE_AFTER_HOURS` (24) jam (BR-114). Saat benar, halaman menampilkan kalimat "Data mungkin tidak terkini. Hubungi rumah sakit untuk memastikan ketersediaan." (`text-warning-emphasis`).
- **F4B-R11 Kalimat peringatan kamar** (#11, #12–#15, dan blok beranda versi pendek). Kata "real-time" tidak dipakai (01, 02, 09 menang atas 07 W-07):
  - #11 dan #12–#15, kotak `alert alert-info` dengan ikon `bi-info-circle`: "Ketersediaan tempat tidur diperbarui manual oleh petugas dan dapat berubah sewaktu-waktu. Hubungi rumah sakit untuk memastikan. Nama dan klasifikasi kategori adalah kebutuhan prototipe, bukan ketentuan BPJS yang sudah diverifikasi. Tarif adalah contoh."
  - Blok beranda: "Diperbarui manual oleh petugas. Hubungi rumah sakit untuk memastikan ketersediaan."
- **F4B-R12 Tarif contoh.** Accessor `sample_rate_label`:
  - bila `sample_rate` terisi: `'Rp '.number_format((float) $this->sample_rate, 0, ',', '.').' '.$this->rate_unit`, misalnya "Rp 400.000 per malam" (tanpa `Number::currency`, agar tidak ada spasi tak putus);
  - bila NULL: "Hubungi kami".

  View publik menulis "Tarif contoh: {label}". Bila `rate_note` terisi, view menampilkannya sebagai teks kecil.
- **F4B-R13 Route admin** (16 route baru, total 106). Semuanya di dalam G-ADMIN, sebelum komentar `// J. Media`:
  - **T4 Fasilitas:** `Route::middleware('permission:facilities.manage')->group(...)` berisi `Route::resource('fasilitas', FacilityController::class)->names('facilities')->parameters(['fasilitas' => 'facility'])->except(['show'])` (6 route).
  - **T5 Kamar:**
    - `Route::middleware('permission:facilities.manage')->group(...)` berisi `Route::resource('kamar', RoomCategoryController::class)->names('room-categories')->parameters(['kamar' => 'roomCategory'])->except(['index', 'show'])` (5 route);
    - `Route::get('kamar', …)->middleware('permission:facilities.manage|rooms.update-availability')->name('room-categories.index')`;
    - `Route::patch('kamar/{roomCategory}/ketersediaan', [RoomCategoryController::class, 'availability'])->middleware('permission:rooms.update-availability')->name('room-categories.availability')` (total 7).
  - **T6 Pesan:** `Route::middleware('permission:messages.manage')->group(...)` berisi `GET pesan` `messages.index`, `GET pesan/{message}` `messages.show`, dan `PATCH pesan/{message}/status` `messages.status` (3 route, tanpa `destroy`).
- **F4B-R14 Route publik.** Di `routes/web.php`:
  - setelah baris `/jadwal-dokter`: `/fasilitas` `facilities.index`, `/fasilitas/{facility:slug}` `facilities.show` (T7), lalu `/kamar-rawat-inap` `rooms.index`, `/kamar-rawat-inap/{roomCategory:slug}` `rooms.show` (T8);
  - setelah baris `/faq`: `GET /kontak` `contact` dan `POST /kontak` `contact.store` dengan `->middleware('throttle:contact')` (T9).

  Controller publik di namespace `App\Http\Controllers\Public`. Detail nonaktif → `abort_unless($model->is_active, 404)` untuk semua aktor, termasuk staf (A-42).
- **F4B-R15 Policy.**
  - `FacilityPolicy`: semua method → `facilities.manage`.
  - `RoomCategoryPolicy`:
    - `viewAny` → `$user->canAny(['facilities.manage', 'rooms.update-availability'])`;
    - `view`/`create`/`update`/`delete` → `facilities.manage`;
    - `updateAvailability(User, RoomCategory)` → `rooms.update-availability`.
  - `ContactMessagePolicy`: `viewAny`, `view`, `updateStatus` → `messages.manage`; `delete` → `false`.
  - Ketiganya `final`, `declare(strict_types=1)`.
- **F4B-R16 Admin daftar kamar** (`admin.room-categories.index`):
  - **Tampilan:** tanpa paginasi dan tanpa filter (biasanya ≤ 10 baris; menyimpang dari 11 `ListFilterRequest`, pola F4A-R13). Semua kategori aktif/nonaktif diurutkan `ordered()`, dengan `with(['availabilityUpdater:id,name', 'media'])`.
  - **Kolom:** Kategori (cover thumb + nama + lencana Aktif/Nonaktif), Kapasitas, Tersedia, Diperbarui (label + nama petugas), Tarif contoh, Aksi.
  - **Kolom Tersedia untuk pemegang `rooms.update-availability`:** form inline `PATCH` dengan input angka `beds_available` (`min=0`, `max=bed_capacity`), hidden `availability_for` = id kategori, dan tombol "Simpan".
  - **Galat per baris:** galat validasi ditampilkan hanya di baris dengan `old('availability_for') == $room->id`.
  - **Tanpa `rooms.update-availability`:** baris menampilkan angka saja.
  - **Tombol:** Tambah, Ubah, dan Hapus hanya dirender bila `@can` (`facilities.manage`). Petugas Pendaftaran melihat mode ketersediaan saja (D-12).
- **F4B-R17 Aksi ketersediaan.** `availability(UpdateRoomAvailabilityRequest $request, RoomCategory $roomCategory)` menjalankan `$this->authorize('updateAvailability', $roomCategory)`, lalu dalam `DB::transaction`:
  - baca ulang baris dengan `lockForUpdate()`;
  - set `beds_available`, `availability_updated_at = now()`, `availability_updated_by = user id`;
  - `saveQuietly()`;
  - `AuditLogger::log('room.availability_updated', $model, ['beds_available' => $old], ['beds_available' => $new], "Ketersediaan {nama} diperbarui dari {old} menjadi {new} tempat tidur.", Actor::staff($user))`.

  Hasilnya redirect ke `admin.room-categories.index` dengan flash `success` "Ketersediaan {nama} berhasil diperbarui."
  - `UpdateRoomAvailabilityRequest`: `beds_available` `['bail', 'required', 'integer', 'min:0', 'max:{bed_capacity}']`, `availability_for` `['nullable', 'integer']`.
  - Pesan V-29 persis, termasuk "Jumlah tempat tidur tersedia tidak boleh melebihi kapasitas ({kapasitas} tempat tidur)."
  - Nilai yang sama dengan yang tersimpan tetap memperbarui waktu (konfirmasi petugas).
- **F4B-R18 Kapasitas dan buat kategori.** `RoomCategoryRequest`:
  - **Saat buat:** `beds_available` `['bail', 'required', 'integer', 'min:0', 'lte:bed_capacity']`. Controller `store` mengisi `availability_updated_at = now()` dan `availability_updated_by = user`.
  - **Saat ubah:** `beds_available` tidak ada di `rules()`; ketersediaan hanya lewat F4B-R17. `bed_capacity` `['bail', 'required', 'integer', 'between:1,500']` ditambah pemeriksaan `after()`: bila `bed_capacity < $roomCategory->beds_available`, tambah galat "Kapasitas tidak boleh lebih kecil dari tempat tidur tersedia saat ini ({n}). Perbarui ketersediaan terlebih dahulu." (BR-115).
  - **Mengubah kapasitas tidak mengubah `availability_updated_*`** (06 §13 dan 11 menang atas 09 §5.1). Perubahannya tercatat lewat `room_category.updated` dari observer.
- **F4B-R19 `amenities`.** Form memakai satu `textarea` bernama `amenities` dengan label "Fasilitas kamar (satu per baris)".
  - `prepareForValidation()` mengubahnya menjadi larik: pecah per baris, `trim`, buang baris kosong.
  - Aturan V-28: `amenities` `['nullable', 'array', 'max:30']`, `amenities.*` `['string', 'min:2', 'max:80', 'distinct']`.
  - Pesan: "Fasilitas kamar maksimal 30 butir." / "Setiap butir fasilitas harus 2 sampai 80 karakter." / "Butir fasilitas tidak boleh sama."
  - Larik kosong disimpan NULL.
  - Saat render ulang, nilai lama berupa larik digabung dengan `"\n"`.
- **F4B-R20 Hapus kategori kamar.** Selalu boleh, karena tidak dirujuk tabel lain (CRD-13; 11). Tidak ada `deletionBlockedReason()`.
  - Modal: judul "Hapus kategori kamar?", pesan "Kategori kamar beserta gambarnya akan dihapus permanen dan halaman publiknya tidak dapat dibuka lagi. Bila hanya ingin menyembunyikannya, nonaktifkan saja.", tombol "Ya, hapus".
  - Fasilitas dihapus permanen dengan modal "Hapus fasilitas?" / "Fasilitas beserta gambarnya akan dihapus permanen."
- **F4B-R21 Media fasilitas dan kamar.** Pola poliklinik fase 2 (D-41):
  - field `cover`/`cover_alt` dan `gallery[]`/`gallery_alt[]`, galeri maksimal 20 gambar termasuk yang sudah ada (`MAX_GALLERY = 20`, pesan "Galeri maksimal 20 gambar.");
  - koleksi `cover` (tunggal, `MediaService::replace`) dan `gallery` (banyak, `MediaService::store`);
  - alt kosong pada cover yang sudah ada ditolak (BR-132);
  - di halaman ubah: alt/urutan/hapus gambar galeri memakai `admin.media.update|reorder|destroy` lewat form tersendiri, seperti `admin/clinics/edit.blade.php`.

  `MediaPolicy` memetakan `Facility` dan `RoomCategory` → `facilities.manage`, sehingga Petugas Pendaftaran tidak dapat mengelola gambar kamar.
- **F4B-R22 Admin daftar fasilitas.** `Admin\FacilityIndexRequest extends ListFilterRequest`:
  - `statusValues()` `['aktif', 'nonaktif']`;
  - `kategori` ∈ `FacilityCategory::values()` (nilai tidak valid diabaikan);
  - `sortable()` `['urutan', 'nama']`;
  - `q` pada `name` lewat `LikePattern::contains()`;
  - bawaan `ordered()` lalu `id`;
  - paginasi `per_page` 25.

  `applyTo(Builder $query): Builder`. Daftar: `with('media')`.
- **F4B-R23 Admin daftar pesan.** `Admin\ContactMessageIndexRequest extends ListFilterRequest`:
  - `statusValues()` `['baru', 'dibaca', 'dibalas', 'diarsipkan']` dipetakan ke enum lewat konstanta `STATUS_MAP`;
  - `q` pada `name`, `email`, dan `subject`;
  - tanpa `urut`; urutan tetap `created_at` desc, `id` desc;
  - paginasi 25.

  Baris berstatus `new` ditulis tebal. Kolom: Status (`x-status-badge`), Pengirim (nama + email), Subjek, Diterima (`j M Y, H.i`), dan Aksi "Buka".
- **F4B-R24 Status pesan.**
  - **Membuka** `admin.messages.show` untuk pesan berstatus `new` mengubahnya menjadi `read` satu kali, dengan audit `contact_message.status_changed`, tanpa mengisi `handled_*`.
  - **Mengubah status** lewat `PATCH admin.messages.status` (`UpdateContactMessageStatusRequest`: `status` `['required', Rule::in(['read', 'replied', 'archived'])]`, pesan "Status pesan wajib dipilih." / "Status pesan tidak valid.").
    - Status sama dengan yang tersimpan → tanpa perubahan dan tanpa audit, tetapi flash tetap tampil.
    - Target `replied` atau `archived` mengisi `handled_by` dan `handled_at = now()` lewat `forceFill`.
    - Kembali ke `read` (pulihkan dari arsip) membiarkan `handled_*`.
  - **Flash:** "Status pesan berhasil diperbarui."
  - **Audit:** old/new `['status' => …]`, deskripsi "Status pesan kontak #{id} diubah dari {label lama} menjadi {label baru}." tanpa nama, email, subjek, atau isi pesan.
  - **Tombol di `show`:**
    - `new`/`read` → "Tandai Dibalas" (`replied`) dan "Arsipkan" (`archived`);
    - `replied` → "Arsipkan";
    - `archived` → "Pulihkan" (`read`).
- **F4B-R25 Audit.**
  - `AuditObserver` didaftarkan untuk `Facility` dan `RoomCategory` (aksi `facility.*`, `room_category.*`).
  - `ContactMessage` **tidak** diobservasi, agar nama pengirim tidak masuk deskripsi audit; pembuatan pesan oleh tamu tidak diaudit.
  - Kunci `messages.entities` baru: `facility` "Fasilitas", `room_category` "Kategori kamar", `contact_message` "Pesan kontak".
- **F4B-R26 `LikePattern`.** Kelas baru `App\Support\LikePattern` (`final`, `declare(strict_types=1)`) dengan `public static function contains(string $term): string`, yang mengembalikan `'%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%'`.
  - T3 mengganti sembilan salinan yang ada: `ArticleCategoryController`, `Admin\ArticleIndexRequest`, `BannerIndexRequest`, `FaqIndexRequest`, `GalleryIndexRequest`, `MasterDataIndexRequest`, `ScheduleExceptionIndexRequest`, `Article::scopeSearch`, dan `Doctor` (`addcslashes`).
  - `AuditLog` (prefiks) dan `MediaService` (needle) tidak disentuh.
  - Kode 4b memakai helper ini.
- **F4B-R27 `x-form.file`.** Prop baru `placeholder` (bawaan `null`). Bila diisi, nilainya menimpa peta koleksi → tipe placeholder. Form fasilitas mengirim `placeholder="facility"` dan form kamar `placeholder="room"`.
- **F4B-R28 Tab modul #36.** `config/navigation.php` `modules.facilities` berisi:
  - "Kamar dan Kapasitas": `admin.room-categories.index`, `permission` `facilities.manage|rooms.update-availability`, `active` `['admin.room-categories.*']`;
  - "Fasilitas": `admin.facilities.index`, `permission` `facilities.manage`, `active` `['admin.facilities.*']`.

  View #36 memakai `<x-module-tabs group="facilities" />`. Kotak pesan tidak memakai tab.
- **F4B-R29 Halaman publik fasilitas.**
  - **#9:** fasilitas `active()` dikelompokkan per `FacilityCategory::cases()` (urutan medis, penunjang, umum). Kategori tanpa fasilitas dilewati. Tanpa paginasi. `with('media')`.
  - **#10:** detail dengan cover, galeri modal (pola klinik), deskripsi, dan tautan terkait.
  - **SEO detail:** judul `seo_title` (= `meta_title` → `name`), deskripsi `seo_description` (= `meta_description` → `short_description`), `og:image` = `url(cover)` bila ada.
- **F4B-R30 Halaman publik kamar.**
  - **#11:** kartu kategori `active()->ordered()` dengan `with('media')` (tanpa paginasi).
  - **#12–#15:** satu route `rooms.show` dan satu view untuk keempat slug seed.
  - **Tombol detail:**
    - "Tanya Ketersediaan" → `route('contact')` (bila `Route::has('contact')`);
    - "Chat via WhatsApp (membuka WhatsApp)" → `$site->whatsappUrl()` bila tidak null, `target="_blank" rel="noopener noreferrer"`.
  - **SEO:** pola F4B-R29.
- **F4B-R31 Blok kamar beranda.**
  - Partial `public/partials/_room-summary.blade.php` disisipkan di `public/home.blade.php` antara `_featured-doctors` dan `_latest-articles`.
  - Isi: kategori `active()->ordered()` maksimal `HomeController::FEATURED_ROOMS = 4`. Tiap kartu berisi nama (tautan `rooms.show`), `occupancy_label`, badge F4B-R9, dan `availability_updated_label`, ditambah kalimat F4B-R11 versi pendek dan tautan "Lihat detail kamar" ke `rooms.index`.
  - Disembunyikan bila tidak ada kategori aktif.
  - Judul `<h2 id="judul-kamar">` "Ketersediaan Kamar Rawat Inap".
- **F4B-R32 Halaman kontak.** Susunan W-08 (kerangka E).
  - Alamat `<address>` selalu tampil.
  - Daftar kontak memakai nilai `contact.*` apa adanya, seperti footer layout tanpa label "(placeholder)" tambahan; nilai seed sudah memuat "(contoh)" bila perlu.
  - Baris kontak yang kosong disembunyikan.
  - Tombol "Petunjuk Arah" (`map.directions_url`) dan "Chat via WhatsApp (membuka WhatsApp)".
  - `x-map-embed`, lalu form.
  - Di bawah textarea pesan: "Jangan menuliskan data medis atau nomor identitas pada pesan ini."
  - Checkbox `consent` dengan label "Saya menyetujui data saya diproses sesuai Kebijakan Privasi." ("Kebijakan Privasi" ditaut ke `route('privacy')` bila `Route::has('privacy')`).
  - Tombol `x-loading-button` "Kirim Pesan" dengan `loading-text="Mengirim…"`.
- **F4B-R33 `x-map-embed`.** Kelas `App\View\Components\MapEmbed` (`final`, `declare(strict_types=1)`, menerima `SettingsService` lewat konstruktor) dengan props `int $height = 360` dan `string $title = 'Peta lokasi UNPAM HOSPITAL'`. View `components/map-embed.blade.php`:
  - **Wadah:** `<div class="map-embed" data-map-embed style="--map-height: {h}px">`. Atribut `style` di sini hanya variabel CSS (diizinkan karena CSP `style-src 'unsafe-inline'`). Bila ingin menghindari inline style, pakai kelas tinggi tetap `map-embed`.
  - **Fallback:** `<div class="map-embed-fallback" role="status" data-map-fallback>` berisi "Memuat peta…" (diganti JS menjadi "Peta tidak dapat dimuat. Alamat dan tombol Petunjuk Arah tetap dapat dipakai.") dan tombol "Petunjuk Arah" bila `map.directions_url` terisi.
  - **Iframe** (hanya bila `map.embed_url` terisi): `<iframe src="{embed_url}" title="{title}" loading="lazy" referrerpolicy="no-referrer-when-downgrade" data-map-frame>`. Bila kosong: fallback berisi "Peta tidak tersedia." tanpa iframe.
  - **JS `initMapEmbeds()`** di `resources/js/app.js` (dipanggil dari `init()`, ditulis sebelum `initGalleryModals()`):
    - bila `navigator.onLine === false` → tambah kelas `is-unavailable` dan ganti teks fallback;
    - selain itu pasang listener **`load`** pada iframe (bukan `error`) yang menambah `is-loaded`;
    - `setTimeout` 8000 ms menambah `is-unavailable` bila belum `is-loaded`.
  - **CSS:** `.map-embed.is-unavailable iframe { display: none; }`. Fallback berada di belakang iframe (posisi absolut), dan `.is-loaded .map-embed-fallback { visibility: hidden; }`.
  - **Tanpa JS:** iframe tampil di atas fallback.
- **F4B-R34 Telepon.** `App\Rules\IndonesianPhone implements ValidationRule` (`final`, `declare(strict_types=1)`):
  - `public static function normalize(string $value): string` membuang spasi, tanda hubung, titik, dan kurung;
  - nilai lolos bila hasil normalisasi cocok `/^(\+62|62|0)8[0-9]{7,12}$/`;
  - pesan "Nomor telepon tidak valid. Gunakan nomor seluler Indonesia, mis. 081234567890.";
  - `StoreContactMessageRequest` menyimpan nomor yang sudah dinormalisasi (maks 16 karakter, muat di `VARCHAR(20)`).
- **F4B-R35 Simpan pesan.** `ContactController@store(StoreContactMessageRequest $request)`:
  - honeypot (F4B-R7);
  - `ContactMessage::query()->create($request->messageAttributes() + ['status' => ContactMessageStatus::New, 'consent_at' => now(), 'ip_hash' => hash('sha256', $request->ip().config('app.key'))])`;
  - redirect `route('contact')` dengan flash `success` F4B-R8.

  Aturan V-37 persis (`name` `PersonName`, `email:rfc`, `phone` nullable `IndonesianPhone`, `subject` 5–150, `message` 10–2000, `consent` `accepted`). Field teks memakai `bail`. Field larik tidak menyebabkan 500.
- **F4B-R36 Seeder demo.** `RoomCategorySeeder`, `FacilitySeeder`, dan `ContactMessageSeeder` hanya `local`/`testing` (pola `ContentSeeder`: melempar `RuntimeException('… DILARANG di APP_ENV=…')`) dan idempoten per slug atau per email+subjek.
  - `DemoSeeder` memanggil `… ScheduleExceptionSeeder, RoomCategorySeeder, FacilitySeeder, ContentSeeder, ContactMessageSeeder`.
  - Data persis 13 §8.1, §8.2, §10.4.
  - `sort_order` = nomor urut 1…n. `is_active` = true.
  - `description` HTML netral satu paragraf berakhiran "(deskripsi contoh)".
  - Kamar: `availability_updated_at` = sekarang − 2 jam, `availability_updated_by` = akun `pendaftaran@unpamhospital.test`.
  - Pesan: `message` 2–3 kalimat netral (≥ 10 karakter), `phone` NULL kecuali #2 (`081234567890`), `created_at` sekarang − (6 − n) hari, `consent_at` = `created_at`, `ip_hash = hash('sha256', '127.0.0.1'.config('app.key'))`. Pesan #4 dan #5 punya `handled_by` = pendaftaran dan `handled_at` = `created_at` + 1 jam.
  - Gambar 13 §9.2 (1200×800 JPEG lewat `PlaceholderImageGenerator`):
    - fasilitas: 10 cover + 4 galeri (#6, #7 masing-masing 2);
    - kamar: 4 cover + 8 galeri.

    Alt cover "Foto contoh {nama}", alt galeri "Foto contoh {nama} {n}".
  - Media demo menjadi **83** (cover 36, gallery 12).
- **F4B-R37 Tes lama yang diperbarui** (pemilik tugas dalam kurung):
  - `ContentSchemaTest::test_the_phase_4b_migrations_are_not_created_yet` dihapus (T1);
  - `MediaPolicyTest` kasus `Facility` "belum dipetakan" diganti `App\Models\PatientProfile`, dan kasus `Facility`/`RoomCategory` ditambah (T3);
  - `ContentEditorImagesTest::test_the_reference_columns_include_pages_and_articles` (T3);
  - `Phase4aRoutePermissionTest::test_the_admin_area_has_ninety_routes_after_phase_4a` diganti tes yang menghitung 40 route `admin/konten` (T4);
  - `RawOutputScanTest` daftar 3 → 4 (T7) → 5 baris (T8), dan filter komponen `map-embed` (T9);
  - `PublicDoctorPagesTest:324-337` skip "fase 4b" diganti tes positif (T9);
  - `DemoSeederInvariantsTest`, `DemoSeederWiringTest`, `MasterDataSeederGuardTest`, `SeederProductionGuardTest` (T10);
  - `AdminPageMatrixTest` (T11).
- **F4B-R38 Trailer commit.** Teks rencana memakai `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`. Implementer subagen boleh memakai atribusi harness-nya.
- **F4B-R39 Admin Konten dan data pribadi.** Admin Konten tidak punya `messages.manage`. Layar #36 tidak mengueri `contact_messages`, `patient_profiles`, atau `appointments` (AKS-04). Layar `admin.messages.*` memang mengueri `contact_messages` dan tidak dimasukkan ke `test_content_screens_never_query_patient_data`.
- **F4B-R40 Susunan halaman tanpa wireframe** (#9, #10, admin #36, kotak pesan) diambil dari 05 §4.1/§4.3/§5.2, 06 §17, dan pola 07 §8/W-15 (kerangka E).

## Keterbatasan yang diketahui

Butir berikut disengaja dan tidak dikerjakan di fase 4b:
- Lencana angka "Pesan Kontak" di sidebar, widget dasbor pesan baru, dan ringkasan kamar di dasbor menyusul di fase 8, bersama halaman pengaturan situs (F4B-R1).
- Tidak ada status "Terbatas" untuk kamar (06 [ASUMSI]); hanya "Tersedia"/"Penuh" dengan label waktu dan kalimat kedaluwarsa > 24 jam (F4B-R9, F4B-R10).
- Pesan kontak tidak dikirim lewat email, dan balasan dilakukan manual di luar aplikasi. Data pesan tidak dihapus otomatis (K-09).
- Fallback peta bergantung pada JS (`initMapEmbeds()`). Tanpa JS, iframe tetap tampil, dan alamat teks serta tombol Petunjuk Arah selalu ada di halaman (F4B-R33).
- Seeder demo fasilitas dan kamar membuat baris lalu gambar tanpa transaksi (pola `ContentSeeder` 4a). Kegagalan GD di tengah meninggalkan baris tanpa gambar yang dilewati saat seeding berikutnya.

## Nama baru yang diperkenalkan fase 4b

Hanya nama di bagian ini (ditambah nama privat/tes kecil yang dipakai satu tugas, dicatat di 00 J.6 oleh Task 11) yang boleh diperkenalkan. Kode lengkapnya ada di tugas pembuatnya.

### Migration (T1)
Lihat F4B-R2. Kolom, tipe, default, index, FK, dan CHECK mengikuti 09 §5.1, §5.2, §6.8 persis (kode lengkap di Task 1).

### Model (T2) — `app/Models`, gaya `Clinic`, dengan `HasFactory`

```php
// Facility (facilities) — use HasFactory, HasMedia, HasUniqueSlug, SanitizesRichText;
fillable: name, slug, category, short_description, description, is_active, sort_order, meta_title, meta_description
casts: category => FacilityCategory::class, is_active => 'boolean', sort_order => 'integer'
public function scopeActive(Builder $q): void           // is_active = true
public function scopeOrdered(Builder $q): void          // sort_order, name, id (qualifyColumn)
public function scopeOfCategory(Builder $q, FacilityCategory $category): void
public function richTextAttributes(): array             // ['description']
protected function coverUrl(): Attribute                // mediaUrl('cover','full','images/placeholders/facility.svg')
protected function seoTitle(): Attribute                // meta_title → name
protected function seoDescription(): Attribute          // meta_description → short_description
protected function slugMaxLength(): int                 // 170

// RoomCategory (room_categories) — HasFactory, HasMedia, HasUniqueSlug, SanitizesRichText
public const STALE_AFTER_HOURS = 24;
fillable: name, slug, short_description, description, amenities, bed_capacity, beds_available, sample_rate, rate_unit, rate_note, is_active, sort_order, availability_updated_at, availability_updated_by, meta_title, meta_description
casts: amenities => 'array', bed_capacity => 'integer', beds_available => 'integer', sample_rate => 'decimal:2', is_active => 'boolean', sort_order => 'integer', availability_updated_at => 'datetime'
public function availabilityUpdater(): BelongsTo        // User, 'availability_updated_by'
scopeActive, scopeOrdered (sort_order, name, id)
public function richTextAttributes(): array             // ['description']
protected function coverUrl(): Attribute                // mediaUrl('cover','full','images/placeholders/room.svg')
protected function seoTitle(): Attribute ; protected function seoDescription(): Attribute   // pola Facility
protected function occupancyLabel(): Attribute          // F4B-R10
protected function availabilityUpdatedLabel(): Attribute // F4B-R10
protected function isAvailabilityStale(): Attribute     // F4B-R10 (bool)
protected function isFull(): Attribute                  // beds_available === 0 (bool)
protected function sampleRateLabel(): Attribute         // F4B-R12
protected function slugMaxLength(): int                 // 120

// ContactMessage (contact_messages) — HasFactory
fillable: name, email, phone, subject, message, status, consent_at, ip_hash
hidden: ip_hash
casts: status => ContactMessageStatus::class, consent_at => 'datetime', handled_at => 'datetime'
public function handler(): BelongsTo                    // User, 'handled_by'
public function scopeUnread(Builder $q): void           // status = new
```

Factory (`database/factories`):
- `FacilityFactory`, dengan state `category(FacilityCategory $category)` dan `inactive()`;
- `RoomCategoryFactory`, dengan state `full()`, `inactive()`, `withoutRate()`, dan `availability_updated_at` = now − 1 jam;
- `ContactMessageFactory`, dengan state `read()`, `replied()`, `archived()`; `consent_at`/`ip_hash` terisi; `handled_*` diisi state `replied()`/`archived()` lewat `afterMaking` + `forceFill`, atau dengan user dari `User::factory()->staff()`.

Pembantu tes baru `tests/Support/InteractsWithFacilities.php` (trait):

```php
protected function createFacility(array $attributes = []): Facility
protected function createRoomCategory(array $attributes = []): RoomCategory
protected function createContactMessage(array $attributes = []): ContactMessage   // handled_by/handled_at boleh di $attributes (forceFill)
```

### Integrasi (T3)
- `App\Support\LikePattern::contains(string $term): string` (F4B-R26).
- `AuditObserver` didaftarkan untuk `Facility`, `RoomCategory`. Kunci `entities` baru (F4B-R25).
- `MediaPolicy::manages()`: `Facility::class, RoomCategory::class => 'facilities.manage'`.
- `MediaService::EDITOR_REFERENCE_COLUMNS` menjadi `['clinics' => ['description'], 'pages' => ['body'], 'articles' => ['body'], 'facilities' => ['description'], 'room_categories' => ['description']]`.
- `x-form.file` prop `placeholder` (F4B-R27).
- `config('navigation.modules.facilities')` (F4B-R28).

### Seeder (T10)
- `database/seeders/RoomCategorySeeder.php` (`public const ROOMS`), `FacilitySeeder.php` (`public const FACILITIES`), dan `ContactMessageSeeder.php` (`public const MESSAGES`, `public const HANDLER_EMAIL = 'pendaftaran@unpamhospital.test'`).
- `RoomCategorySeeder::UPDATER_EMAIL = 'pendaftaran@unpamhospital.test'`.

### Admin (T4–T6) — namespace `App\Http\Controllers\Admin`, `App\Http\Requests\Admin`, `App\Policies`

| Resource | Controller | Form Request | Policy | Filter index | View `resources/views/admin/…` |
|---|---|---|---|---|---|
| Fasilitas | `FacilityController` | `FacilityRequest` | `FacilityPolicy` | `FacilityIndexRequest` | `facilities/index`, `create`, `edit`, `_form` |
| Kamar | `RoomCategoryController` (+ `availability`) | `RoomCategoryRequest`, `UpdateRoomAvailabilityRequest` | `RoomCategoryPolicy` | — (F4B-R16) | `room-categories/index`, `create`, `edit`, `_form` |
| Pesan | `ContactMessageController` (`index`, `show`, `status`) | `UpdateContactMessageStatusRequest` | `ContactMessagePolicy` | `ContactMessageIndexRequest` | `messages/index`, `show` |

Form Request berpola `ClinicRequest`:
- `authorize()` berdasarkan route model;
- `messages()` dengan `ImageRules::messages(...)`;
- method atribut `facilityAttributes()`, `roomCategoryAttributes()`;
- helper berkas `coverFile()`, `coverAlt()`, `galleryFiles()`, `galleryAlt(int|string $index)`;
- konstanta `MAX_GALLERY = 20`.

Judul layout (05 §8):

| Layar | Judul | Breadcrumb |
|---|---|---|
| `admin.facilities.index` | "Fasilitas" | Dasbor › Fasilitas dan Kamar › Fasilitas |
| `admin.facilities.create` / `edit` | "Tambah Fasilitas" / "Ubah {nama}" | … › Fasilitas › Tambah / Ubah {nama} |
| `admin.room-categories.index` | "Kamar dan Kapasitas" | Dasbor › Fasilitas dan Kamar › Kamar dan Kapasitas |
| `admin.room-categories.create` / `edit` | "Tambah Kategori Kamar" / "Ubah {nama}" | … › Kamar dan Kapasitas › Tambah / Ubah {nama} |
| `admin.messages.index` | "Pesan Kontak" | Dasbor › Pesan Kontak |
| `admin.messages.show` | "Pesan: {subjek}" | Dasbor › Pesan Kontak › {subjek} |

Breadcrumb "Fasilitas dan Kamar" ditaut ke `admin.room-categories.index`. Flash CRUD: `__('messages.created'|'updated'|'deleted', ['entity' => __('messages.entities.facility'|'room_category')])`.

### Publik (T7–T9) — namespace `App\Http\Controllers\Public`, `App\Http\Requests\Public`

```php
// routes/web.php (F4B-R14)
Route::get('/fasilitas', [FacilityController::class, 'index'])->name('facilities.index');                      // T7
Route::get('/fasilitas/{facility:slug}', [FacilityController::class, 'show'])->name('facilities.show');
Route::get('/kamar-rawat-inap', [RoomCategoryController::class, 'index'])->name('rooms.index');                // T8
Route::get('/kamar-rawat-inap/{roomCategory:slug}', [RoomCategoryController::class, 'show'])->name('rooms.show');
Route::get('/kontak', [ContactController::class, 'show'])->name('contact');                                    // T9
Route::post('/kontak', [ContactController::class, 'store'])->middleware('throttle:contact')->name('contact.store');
```

- **`Public\FacilityController`:** `index(): View` (view `public/facilities/index`, data `page` = `Page::forKey('facilities-index')`, `groups` = koleksi `[FacilityCategory, Collection<Facility>]` yang tidak kosong) dan `show(Facility $facility): View` (view `public/facilities/show`, data `facility`, `gallery` = `$facility->mediaIn('gallery')`).
- **`Public\RoomCategoryController`:** `index(): View` (view `public/rooms/index`, data `page` = `Page::forKey('rooms-index')`, `rooms`) dan `show(RoomCategory $roomCategory): View` (view `public/rooms/show`, data `room`, `gallery`).
- **`Public\ContactController`:** `show(): View` (view `public/contact`, data `page` = `Page::forKey('contact')`) dan `store(StoreContactMessageRequest $request): RedirectResponse`.
- **`Public\StoreContactMessageRequest`:** `authorize()` true, `rules()` V-37, `messages()`, `prepareForValidation()` (`trim`), `isHoneypotFilled(): bool`, dan `messageAttributes(): array{name, email, phone, subject, message}` (telepon dinormalisasi).
- **`App\Rules\IndonesianPhone`** (F4B-R34).
- **`App\View\Components\MapEmbed`** + `resources/views/components/map-embed.blade.php` (F4B-R33).
- **Partial:**
  - `public/rooms/_availability-badge.blade.php` (`$room`);
  - `public/partials/_room-summary.blade.php` (`$rooms`);
  - `public/facilities/_card.blade.php` (`$facility`, `$headingLevel` bawaan 3).
- **`HomeController::FEATURED_ROOMS = 4`.** Variabel view `rooms`.
- **JS:** `initMapEmbeds()`.
- **Breadcrumb publik** (05 §8, label statis F4A-R31):
  - "Beranda › Fasilitas", "› Fasilitas › {nama}";
  - "› Kamar Rawat Inap", "› Kamar Rawat Inap › {nama}";
  - "› Kontak dan Lokasi".

## Susunan halaman (F4B-R40)

- **#9 Fasilitas:**
  - breadcrumb → `x-page-header` (`page.title`, `page.subtitle`);
  - per kategori `<h2>` label `FacilityCategory` dengan ikon `icon()`, lalu grid kartu `row-cols-1 row-cols-sm-2 row-cols-lg-3`. Partial `_card`: `x-image type="facility"` cover `thumb` rasio `4x3`, judul `stretched-link` ke `facilities.show`, `short_description`;
  - tautan bawah "Lihat Kamar Rawat Inap" (`rooms.index`) dan "Kontak" (bila route ada);
  - kosong: `x-empty-state` "Belum ada fasilitas" + "Informasi fasilitas akan tampil di sini setelah ditambahkan." dengan ikon `bi-building`.
- **#10 Detail fasilitas:**
  - breadcrumb → `x-page-header` (judul `name`, subjudul label kategori);
  - cover `x-image` 16x9 (alt dari media, atau placeholder dengan alt `name`);
  - deskripsi `<div class="rich-text facility-body">{!! $facility->description !!}</div>`, hanya bila `filled`;
  - galeri (grid + modal, id `galeri-modal`/`galeri-carousel`, pola klinik), hanya bila ada;
  - tautan "Kembali ke daftar fasilitas" dan "Lihat Kamar Rawat Inap".
- **#11 Kamar Rawat Inap:**
  - breadcrumb → header → kotak peringatan F4B-R11;
  - grid kartu `row-cols-1 row-cols-lg-2`. Isi kartu: cover 4x3, judul `<h2 class="h5">` ber-`stretched-link`, `short_description`, "Kapasitas: {n} tempat tidur", `occupancy_label` + badge, "Tarif contoh: {label}", `availability_updated_label`, kalimat kedaluwarsa bila perlu, dan tombol non-tautan "Lihat Detail" (`aria-hidden`; seluruh kartu tertaut);
  - kosong: `x-empty-state` "Belum ada kategori kamar" + "Informasi kamar rawat inap akan tampil di sini setelah ditambahkan." dengan ikon `bi-building`.
- **#12–#15 Detail kamar:**
  - breadcrumb → header (judul `name`) → peringatan F4B-R11;
  - kolom kiri `col-lg-7`: cover 4x3, thumbnail galeri + modal, deskripsi `<div class="rich-text room-body">{!! $room->description !!}</div>` (bila `filled`), `<h2>` "Fasilitas Kamar" + `<ul class="amenity-list">` (ikon `bi-check2`; disembunyikan bila kosong);
  - kolom kanan `col-lg-5` (`position-sticky` ≥ lg): panel "Ringkasan" berisi Kapasitas, Tersedia + badge, Tarif contoh + `rate_note`, `<time datetime>` label pembaruan, kalimat kedaluwarsa, tombol "Tanya Ketersediaan" dan "Chat via WhatsApp (membuka WhatsApp)".
- **#22 Kontak dan Lokasi:**
  - breadcrumb → header;
  - ≥ lg dua kolom. Kiri: kartu "Alamat" (`<address>` + daftar Telepon, IGD, WhatsApp, Email, Jam operasional; tombol "Petunjuk Arah" dan "Chat via WhatsApp (membuka WhatsApp)"), lalu kartu "Peta" berisi `x-map-embed`. Kanan: kartu "Kirim Pesan" dengan form (nama, email, telepon opsional, subjek, pesan + catatan, honeypot, persetujuan, tombol);
  - < lg satu kolom dengan urutan sama;
  - galat: `x-form.errors-summary` di atas form.
- **Admin #36 Kamar dan Kapasitas:** pola `admin/banners/index` tanpa `x-data-table` (tabel `table-cards` biasa, seperti `admin/pages/index` 4a), `x-module-tabs group="facilities"`, tombol "Tambah Kategori Kamar" (`@can('create', RoomCategory::class)`), dan satu `x-confirm-modal id="modal-hapus-kamar"`.
- **Admin #36 Fasilitas:** pola `admin/banners/index` dengan `x-data-table`. Filter: status, kategori, urut. Satu `x-confirm-modal id="modal-hapus-fasilitas"`. Form `_form` meniru `admin/clinics/_form` (rich text, cover, galeri, SEO `<details>`), dan `edit` meniru kartu kelola galeri `admin/clinics/edit`.
- **Kotak pesan:**
  - `index`: `x-data-table` (cari "Cari nama, email, atau subjek…", filter status). Kosong: "Belum ada pesan" / "Pesan dari formulir kontak akan tampil di sini.";
  - `show`: kartu berisi metadata (`<dl>`: Pengirim, Email `mailto:`, Telepon `tel:` bila ada, Diterima, Status `x-status-badge`, Ditangani oleh {nama} pada {waktu} bila ada, Persetujuan {waktu}), isi pesan (`<div class="message-body">{{ $message->message }}</div>` dengan `white-space: pre-line`), tombol status F4B-R24, dan tautan "Kembali ke daftar pesan". Kalimat bantuan: "Balas pesan ini secara manual lewat email atau telepon. Aplikasi tidak mengirim balasan otomatis."

## Struktur berkas (peta tanggung jawab)

Dihimpun dari daftar **Files** tiap tugas. "baru" = berkas dibuat, "ubah" = berkas yang sudah ada diubah.

```
./
  README.md                                                              (T11 ubah)
app/Http/
  Controllers/Admin/ArticleCategoryController.php                        (T3 ubah)
  Controllers/Admin/ContactMessageController.php                         (T6 baru)
  Controllers/Admin/FacilityController.php                               (T4 baru)
  Controllers/Admin/RoomCategoryController.php                           (T5 baru)
  Controllers/Public/ContactController.php                               (T9 baru)
  Controllers/Public/FacilityController.php                              (T7 baru)
  Controllers/Public/HomeController.php                                  (T8 ubah)
  Controllers/Public/RoomCategoryController.php                          (T8 baru)
  Requests/Admin/ArticleIndexRequest.php                                 (T3 ubah)
  Requests/Admin/BannerIndexRequest.php                                  (T3 ubah)
  Requests/Admin/ContactMessageIndexRequest.php                          (T6 baru)
  Requests/Admin/FacilityIndexRequest.php                                (T4 baru)
  Requests/Admin/FacilityRequest.php                                     (T4 baru)
  Requests/Admin/FaqIndexRequest.php                                     (T3 ubah)
  Requests/Admin/GalleryIndexRequest.php                                 (T3 ubah)
  Requests/Admin/MasterDataIndexRequest.php                              (T3 ubah)
  Requests/Admin/RoomCategoryRequest.php                                 (T5 baru)
  Requests/Admin/ScheduleExceptionIndexRequest.php                       (T3 ubah)
  Requests/Admin/UpdateContactMessageStatusRequest.php                   (T6 baru)
  Requests/Admin/UpdateRoomAvailabilityRequest.php                       (T5 baru)
  Requests/Public/StoreContactMessageRequest.php                         (T9 baru)
app/Models/
  Article.php                                                            (T3 ubah)
  ContactMessage.php                                                     (T2 baru)
  Doctor.php                                                             (T3 ubah)
  Facility.php                                                           (T2 baru)
  RoomCategory.php                                                       (T2 baru)
app/Observers/
  AuditObserver.php                                                      (T3 ubah)
app/Policies/
  ContactMessagePolicy.php                                               (T6 baru)
  FacilityPolicy.php                                                     (T4 baru)
  MediaPolicy.php                                                        (T3 ubah)
  RoomCategoryPolicy.php                                                 (T5 baru)
app/Providers/
  AppServiceProvider.php                                                 (T3 ubah)
app/Rules/
  IndonesianPhone.php                                                    (T9 baru)
app/Services/
  Media/MediaService.php                                                 (T3 ubah)
app/Support/
  LikePattern.php                                                        (T3 baru)
app/View/
  Components/MapEmbed.php                                                (T9 baru)
config/
  navigation.php                                                         (T3 ubah)
database/factories/
  ContactMessageFactory.php                                              (T2 baru)
  FacilityFactory.php                                                    (T2 baru)
  RoomCategoryFactory.php                                                (T2 baru)
database/migrations/
  2026_10_02_001200_create_room_categories_table.php                     (T1 baru)
  2026_10_02_001300_create_facilities_table.php                          (T1 baru)
  2026_10_02_002000_create_contact_messages_table.php                    (T1 baru)
database/seeders/
  ContactMessageSeeder.php                                               (T10 baru)
  DemoSeeder.php                                                         (T10 ubah)
  FacilitySeeder.php                                                     (T10 baru)
  RoomCategorySeeder.php                                                 (T10 baru)
docs/superpowers/specs/2026-10-02-unpam-hospital-blueprint/
  {00-keputusan-inti,05-sitemap-matriks-halaman,06-alur,07-wireframe,11-route-controller-model-service,12-aturan-bisnis-validasi-keamanan,13-data-contoh,17-keputusan-konfirmasi}.md (T11 ubah)
lang/
  id/messages.php                                                        (T3 ubah)
resources/js/
  app.js                                                                 (T9 ubah)
resources/scss/
  _public.scss                                                           (T7 ubah, T8 ubah, T9 ubah)
resources/views/
  admin/facilities/_form.blade.php                                       (T4 baru)
  admin/facilities/create.blade.php                                      (T4 baru)
  admin/facilities/edit.blade.php                                        (T4 baru)
  admin/facilities/index.blade.php                                       (T4 baru)
  admin/messages/index.blade.php                                         (T6 baru)
  admin/messages/show.blade.php                                          (T6 baru)
  admin/room-categories/_form.blade.php                                  (T5 baru)
  admin/room-categories/create.blade.php                                 (T5 baru)
  admin/room-categories/edit.blade.php                                   (T5 baru)
  admin/room-categories/index.blade.php                                  (T5 baru)
  components/form/file.blade.php                                         (T3 ubah)
  components/map-embed.blade.php                                         (T9 baru)
  public/contact.blade.php                                               (T9 baru)
  public/facilities/_card.blade.php                                      (T7 baru)
  public/facilities/index.blade.php                                      (T7 baru)
  public/facilities/show.blade.php                                       (T7 baru)
  public/home.blade.php                                                  (T8 ubah)
  public/partials/_room-summary.blade.php                                (T8 baru)
  public/rooms/_availability-badge.blade.php                             (T8 baru)
  public/rooms/_notice.blade.php                                         (T8 baru)
  public/rooms/index.blade.php                                           (T8 baru)
  public/rooms/show.blade.php                                            (T8 baru)
routes/
  admin.php                                                              (T4 ubah, T5 ubah, T6 ubah)
  web.php                                                                (T7 ubah, T8 ubah, T9 ubah)
tests/Feature/
  Access/AdminPageMatrixTest.php                                         (T11 ubah)
  Access/Phase4aRoutePermissionTest.php                                  (T4 ubah)
  Access/Phase4bRoutePermissionTest.php                                  (T11 baru)
  Admin/AdminContactMessagesTest.php                                     (T6 baru)
  Admin/AdminFacilitiesTest.php                                          (T4 baru)
  Admin/AdminRoomCategoriesTest.php                                      (T5 baru)
  Admin/FacilityRequestTest.php                                          (T4 baru)
  Admin/RoomAvailabilityTest.php                                         (T5 baru)
  Admin/RoomCategoryRequestTest.php                                      (T5 baru)
  Auth/MediaPolicyTest.php                                               (T3 ubah)
  Database/ContentSchemaTest.php                                         (T1 ubah)
  Database/FacilitySchemaTest.php                                        (T1 baru)
  Facilities/ContactMessageModelTest.php                                 (T2 baru)
  Facilities/FacilityAuditTest.php                                       (T3 baru)
  Facilities/FacilityModelTest.php                                       (T2 baru)
  Facilities/InteractsWithFacilitiesTest.php                             (T2 baru)
  Facilities/RoomCategoryModelTest.php                                   (T2 baru)
  Media/ContentEditorImagesTest.php                                      (T3 ubah)
  Media/FacilityEditorImagesTest.php                                     (T3 baru)
  Public/ContactFormTest.php                                             (T9 baru)
  Public/PublicContactTest.php                                           (T9 baru)
  Public/PublicDoctorPagesTest.php                                       (T9 ubah)
  Public/PublicFacilitiesTest.php                                        (T7 baru)
  Public/PublicHomeTest.php                                              (T8 ubah, T9 ubah)
  Public/PublicRoomsTest.php                                             (T8 baru)
  Seeding/DemoSeederInvariantsTest.php                                   (T10 ubah)
  Seeding/DemoSeederWiringTest.php                                       (T10 ubah)
  Seeding/FacilitySeedersTest.php                                        (T10 baru)
  Seeding/MasterDataSeederGuardTest.php                                  (T10 ubah)
  Seeding/SeederProductionGuardTest.php                                  (T10 ubah)
  Support/FacilitiesModuleTabsTest.php                                   (T3 baru)
  Support/LikePatternDatabaseTest.php                                    (T3 baru)
  View/FormFilePlaceholderTest.php                                       (T3 baru)
  View/MapEmbedComponentTest.php                                         (T9 baru)
  View/RawOutputScanTest.php                                             (T7 ubah, T8 ubah, T9 ubah)
tests/Support/
  InteractsWithFacilities.php                                            (T2 baru)
tests/Unit/
  FrontendManifestTest.php                                               (T9 ubah)
  Rules/IndonesianPhoneTest.php                                          (T9 baru)
  Support/LikePatternTest.php                                            (T3 baru)
```

---

### Task 1: Skema 4b (3 migration) dan tes skema

**Files:**
- Create: `database/migrations/2026_10_02_001200_create_room_categories_table.php`
- Create: `database/migrations/2026_10_02_001300_create_facilities_table.php`
- Create: `database/migrations/2026_10_02_002000_create_contact_messages_table.php`
- Test: `tests/Feature/Database/FacilitySchemaTest.php`
- Modify (tes): `tests/Feature/Database/ContentSchemaTest.php` (hapus `test_the_phase_4b_migrations_are_not_created_yet`, F4B-R37)
- Tidak diubah: seluruh migration yang sudah ada (`000100` sampai `001100`, `001400` sampai `001900`, `002100` sampai `002300`). Ketiga migration baru mengisi celah nomor `001200`, `001300`, dan `002000`; pada DB yang sudah memigrasikan fase 1 sampai 4a, ketiganya berjalan sebagai migration tertunda (F4B-R2, pola D-59).

**Interfaces:**
- Consumes: tabel `users` (FK `room_categories.availability_updated_by`, `contact_messages.handled_by`); `User::factory()` untuk baris induk di tes. Model 4b **belum ada** (Task 2), sehingga seluruh insert tes memakai `DB::table()`.
- Produces (persis kamus data 09 §5.1, §5.2, §6.8, urutan kolom = urutan tabel 09):
  - Tiga tabel: `room_categories`, `facilities`, `contact_messages`.
  - Satu CHECK bernama `chk_room_categories_beds` (`beds_available >= 0 AND beds_available <= bed_capacity`) lewat `DB::statement('ALTER TABLE room_categories ADD CONSTRAINT …')` setelah `Schema::create`. CHECK `json_valid` pada `amenities` dibuat MariaDB otomatis dari tipe `$table->json()` (namanya = nama kolom, tidak berawalan `chk_`).
  - FK baru, keduanya `SET NULL`: `room_categories.availability_updated_by` dan `contact_messages.handled_by` (ke `users.id`). `facilities` tanpa FK.
  - UNIQUE: `room_categories_slug_unique`, `facilities_slug_unique`.
  - Index: `room_categories_is_active_sort_order_index`, `facilities_is_active_category_sort_order_index`, `contact_messages_status_created_at_index`, ditambah index FK `room_categories_availability_updated_by_foreign` dan `contact_messages_handled_by_foreign`.
  - `down()` setiap migration = `Schema::dropIfExists('<tabel>')`.

**Test IDs:** KNS-04 (tabel ↔ migration ↔ kolom, sisi skema 4b), CRD-07 (dasar: batas kapasitas pada DB), CRD-13 (sisi DB: hapus akun staf mengosongkan `availability_updated_by` dan `handled_by`), SEK-14 (dasar: `ip_hash` bertipe `CHAR(64)`), Review Focus F.1 (CHECK tidak pernah menjadi 500 karena Form Request lebih dulu; di sini batas CHECK dibuktikan pada INSERT dan UPDATE), invarian I7 13 §11.1 (`beds_available` dalam [0, `bed_capacity`]).

**Catatan teknis (diverifikasi dari source):**
- **Pola yang ditiru:** `tests/Feature/Database/ContentSchemaTest.php` (provider kolom/index/FK, pembantu `columnsOf`/`indexesOf`/`normalizedDefault`, daftar CHECK lewat `information_schema.CHECK_CONSTRAINTS`, pelanggaran CHECK memberi SQLSTATE `23000`, errno `4025`, dan nama constraint berbacktick pada pesan) dan `tests/Feature/Database/ScheduleSchemaTest.php:102,307,639-645` (kolom `pending_change` bertipe `$table->json()` dilaporkan `longtext`, JSON tidak valid ditolak dengan errno 4025 dan nama constraint `pending_change`).
- **Urutan migration.** Berkas terakhir di repo bertipe `…001100_create_appointment_status_histories_table.php`, lalu `…001400_create_pages_table.php` sampai `…001900`, `…002100`, `…002200`, `…002300`. Nomor `001200`, `001300`, dan `002000` kosong sampai Task ini. `migrate:fresh` (dipakai `RefreshDatabase`) mengikuti urutan nama berkas dan aman: `users` selalu lebih dulu (`0001_01_01_000000`), dan tidak ada tabel lain yang merujuk tabel 4b (`media` memakai relasi polimorfik tanpa FK).
- **Tipe yang dilaporkan MariaDB 10.4:** `bigint(20) unsigned`, `smallint(5) unsigned`, `tinyint(1)`, `varchar(n)`, `char(64)`, `text`, `longtext` (untuk `json`), `decimal(12,2)`, `datetime`, `timestamp`. Default NULL dilaporkan sebagai string `"NULL"` dan default string diberi kutip; `normalizedDefault()` menormalkannya.
- **Panjang kolom mengikuti 09, bukan aturan form 12** (F4B-R4): `facilities.name` `VARCHAR(150)`, `room_categories.name` `VARCHAR(100)`, `short_description` `VARCHAR(300)` pada keduanya. Batas yang lebih ketat (nama 100, deskripsi singkat 255) hanya berlaku di Form Request (Task 4 dan 5).
- **`bed_capacity` dan `beds_available` bertipe `SMALLINT UNSIGNED`, bawaan `0`.** CHECK `beds_available <= bed_capacity` otomatis juga melarang kapasitas diturunkan di bawah ketersediaan lewat UPDATE. Bagian `beds_available >= 0` pada CHECK selalu benar untuk kolom unsigned; nilai negatif sudah ditolak tipe kolom (error rentang, bukan errno 4025). Tes hanya menegaskan bahwa nilai `-1` ditolak (`QueryException`).
- **Index FK.** `room_categories_availability_updated_by_foreign` dan `contact_messages_handled_by_foreign` tercantum di daftar index karena tidak tertutup index lain (`(is_active, sort_order)` dan `(status, created_at)` tidak mengawali kolom FK). `facilities` tidak punya FK sehingga tidak punya index `_foreign`. Jangan menambah index manual.
- **`facilities.slug` dan `room_categories.slug` UNIQUE tidak peka huruf besar-kecil** (kolasi `utf8mb4_unicode_ci`, sama dengan `pages.key` di tes 4a).
- **`ip_hash` `CHAR(64)`** menampung SHA-256 heksadesimal tepat 64 karakter. Pada mode SQL strict (`config/database.php` `'strict' => true`), 65 karakter ditolak.
- **DB pengembangan.** Implementer **tidak** menjalankan `migrate` atau `migrate:fresh` pada `unpam_hospital`. Tes memigrasi `unpam_hospital_test` sendiri (`RefreshDatabase`). Migrasi DB pengembangan dilakukan koordinator dengan izin pemilik di gerbang Task 11.
- **Tes lama yang patah:** `ContentSchemaTest::test_the_phase_4b_migrations_are_not_created_yet` (`tests/Feature/Database/ContentSchemaTest.php:388`) menegaskan berkas migration 4b belum ada, jadi harus dihapus (F4B-R37). Tes lain di berkas itu tidak terdampak: `namedChecks()` hanya menyaring enam tabel konten, sehingga `chk_room_categories_beds` tidak ikut terhitung; `ScheduleSchemaTest::namedChecks()` hanya menyaring empat tabel jadwal.
- Jumlah tes ±75 (provider kolom 41, index 10, FK 2, dan ±22 tes perilaku).

- [ ] **Step 1: Verifikasi prasyarat (cabang dan keadaan awal)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git branch --show-current
git status --short
Get-ChildItem database\migrations | Select-Object -ExpandProperty Name
& $PHP artisan test --filter=ContentSchemaTest
```
Expected: `fase-4b-fasilitas-kamar-kontak`; `git status` hanya menampilkan `?? .superpowers/` (atau kosong); daftar migration **tidak** memuat `001200`, `001300`, dan `002000`; `ContentSchemaTest` PASS (baseline master: 4348 tes lulus, 90 route admin). Apache dan MySQL harus sudah di-Start di `C:\xampp3\xampp-control.exe`.

- [ ] **Step 2: Tulis tes yang gagal (tool Write) `tests/Feature/Database/FacilitySchemaTest.php`**

`tests/Feature/Database/FacilitySchemaTest.php`:

```php
<?php

namespace Tests\Feature\Database;

use App\Models\User;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Skema fasilitas, kamar rawat inap, dan pesan kontak fase 4b: 09 §5.1, §5.2, §6.8, §10, §11. Seluruh insert
 * memakai DB::table() agar tes tidak bergantung pada model 4b (Task 2).
 */
class FacilitySchemaTest extends TestCase
{
    use RefreshDatabase;

    private int $counter = 0;

    // --- Data kamus ---

    /**
     * Kolom: tabel, nama kolom, type_name, tipe lengkap, nullable, default (setelah normalisasi).
     *
     * @return array<string, array{0: string, 1: string, 2: string, 3: string, 4: bool, 5: ?string}>
     */
    public static function expectedColumns(): array
    {
        $rows = [
            // 09 §5.1 room_categories
            ['room_categories', 'id', 'bigint', 'bigint(20) unsigned', false, null],
            ['room_categories', 'name', 'varchar', 'varchar(100)', false, null],
            ['room_categories', 'slug', 'varchar', 'varchar(120)', false, null],
            ['room_categories', 'short_description', 'varchar', 'varchar(300)', false, null],
            ['room_categories', 'description', 'text', 'text', true, null],
            ['room_categories', 'amenities', 'longtext', 'longtext', true, null],
            ['room_categories', 'bed_capacity', 'smallint', 'smallint(5) unsigned', false, '0'],
            ['room_categories', 'beds_available', 'smallint', 'smallint(5) unsigned', false, '0'],
            ['room_categories', 'sample_rate', 'decimal', 'decimal(12,2)', true, null],
            ['room_categories', 'rate_unit', 'varchar', 'varchar(30)', false, 'per malam'],
            ['room_categories', 'rate_note', 'varchar', 'varchar(255)', true, null],
            ['room_categories', 'is_active', 'tinyint', 'tinyint(1)', false, '1'],
            ['room_categories', 'sort_order', 'smallint', 'smallint(5) unsigned', false, '0'],
            ['room_categories', 'availability_updated_at', 'datetime', 'datetime', true, null],
            ['room_categories', 'availability_updated_by', 'bigint', 'bigint(20) unsigned', true, null],
            ['room_categories', 'meta_title', 'varchar', 'varchar(255)', true, null],
            ['room_categories', 'meta_description', 'varchar', 'varchar(255)', true, null],
            ['room_categories', 'created_at', 'timestamp', 'timestamp', true, null],
            ['room_categories', 'updated_at', 'timestamp', 'timestamp', true, null],
            // 09 §5.2 facilities
            ['facilities', 'id', 'bigint', 'bigint(20) unsigned', false, null],
            ['facilities', 'name', 'varchar', 'varchar(150)', false, null],
            ['facilities', 'slug', 'varchar', 'varchar(170)', false, null],
            ['facilities', 'category', 'varchar', 'varchar(20)', false, null],
            ['facilities', 'short_description', 'varchar', 'varchar(300)', false, null],
            ['facilities', 'description', 'text', 'text', true, null],
            ['facilities', 'is_active', 'tinyint', 'tinyint(1)', false, '1'],
            ['facilities', 'sort_order', 'smallint', 'smallint(5) unsigned', false, '0'],
            ['facilities', 'meta_title', 'varchar', 'varchar(255)', true, null],
            ['facilities', 'meta_description', 'varchar', 'varchar(255)', true, null],
            ['facilities', 'created_at', 'timestamp', 'timestamp', true, null],
            ['facilities', 'updated_at', 'timestamp', 'timestamp', true, null],
            // 09 §6.8 contact_messages
            ['contact_messages', 'id', 'bigint', 'bigint(20) unsigned', false, null],
            ['contact_messages', 'name', 'varchar', 'varchar(100)', false, null],
            ['contact_messages', 'email', 'varchar', 'varchar(255)', false, null],
            ['contact_messages', 'phone', 'varchar', 'varchar(20)', true, null],
            ['contact_messages', 'subject', 'varchar', 'varchar(150)', false, null],
            ['contact_messages', 'message', 'text', 'text', false, null],
            ['contact_messages', 'status', 'varchar', 'varchar(10)', false, 'new'],
            ['contact_messages', 'consent_at', 'datetime', 'datetime', false, null],
            ['contact_messages', 'ip_hash', 'char', 'char(64)', false, null],
            ['contact_messages', 'handled_by', 'bigint', 'bigint(20) unsigned', true, null],
            ['contact_messages', 'handled_at', 'datetime', 'datetime', true, null],
            ['contact_messages', 'created_at', 'timestamp', 'timestamp', true, null],
            ['contact_messages', 'updated_at', 'timestamp', 'timestamp', true, null],
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
            ['room_categories', 'primary', ['id'], true, true],
            ['room_categories', 'room_categories_slug_unique', ['slug'], true, false],
            ['room_categories', 'room_categories_availability_updated_by_foreign', ['availability_updated_by'], false, false],
            ['room_categories', 'room_categories_is_active_sort_order_index', ['is_active', 'sort_order'], false, false],
            ['facilities', 'primary', ['id'], true, true],
            ['facilities', 'facilities_slug_unique', ['slug'], true, false],
            ['facilities', 'facilities_is_active_category_sort_order_index', ['is_active', 'category', 'sort_order'], false, false],
            ['contact_messages', 'primary', ['id'], true, true],
            ['contact_messages', 'contact_messages_handled_by_foreign', ['handled_by'], false, false],
            ['contact_messages', 'contact_messages_status_created_at_index', ['status', 'created_at'], false, false],
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
            'room_categories.availability_updated_by' => ['room_categories', 'room_categories_availability_updated_by_foreign', 'availability_updated_by', 'users', 'set null'],
            'contact_messages.handled_by' => ['contact_messages', 'contact_messages_handled_by_foreign', 'handled_by', 'users', 'set null'],
        ];
    }

    /**
     * Pelanggaran CHECK: nama constraint dan penyisipan yang harus ditolak.
     *
     * @return array<string, array{0: string, 1: Closure}>
     */
    public static function checkViolations(): array
    {
        return [
            'chk_room_categories_beds: tersedia melebihi kapasitas' => ['chk_room_categories_beds', fn (self $t) => $t->insertRoom(['bed_capacity' => 10, 'beds_available' => 11])],
            'chk_room_categories_beds: kapasitas 0 tersedia 1' => ['chk_room_categories_beds', fn (self $t) => $t->insertRoom(['bed_capacity' => 0, 'beds_available' => 1])],
            'chk_room_categories_beds: tersedia ada, kapasitas bawaan 0' => ['chk_room_categories_beds', fn (self $t) => $t->insertRoom(['beds_available' => 3])],
            'amenities: bukan JSON' => ['amenities', fn (self $t) => $t->insertRoom(['amenities' => 'bukan json'])],
        ];
    }

    // --- Pembantu skema ---

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

    /** @return list<string> */
    private function namedChecks(): array
    {
        $rows = DB::select(
            'SELECT TABLE_NAME AS table_name, CONSTRAINT_NAME AS constraint_name
             FROM information_schema.CHECK_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE()
               AND CONSTRAINT_NAME LIKE ?
               AND TABLE_NAME IN (?, ?, ?)',
            ['chk\\_%', 'room_categories', 'facilities', 'contact_messages'],
        );

        $names = array_map(fn ($row): string => $row->table_name.'.'.$row->constraint_name, $rows);
        sort($names);

        return $names;
    }

    // --- Pembantu insert ---

    /** @param  array<string, mixed>  $overrides */
    public function insertRoom(array $overrides = []): int
    {
        $n = ++$this->counter;

        return DB::table('room_categories')->insertGetId(array_merge([
            'name' => "Kamar {$n}", 'slug' => "kamar-{$n}", 'short_description' => 'Ringkasan kamar contoh.',
        ], $overrides));
    }

    /** @param  array<string, mixed>  $overrides */
    public function insertFacility(array $overrides = []): int
    {
        $n = ++$this->counter;

        return DB::table('facilities')->insertGetId(array_merge([
            'name' => "Fasilitas {$n}", 'slug' => "fasilitas-{$n}", 'category' => 'umum', 'short_description' => 'Ringkasan fasilitas contoh.',
        ], $overrides));
    }

    /** @param  array<string, mixed>  $overrides */
    public function insertMessage(array $overrides = []): int
    {
        $n = ++$this->counter;

        return DB::table('contact_messages')->insertGetId(array_merge([
            'name' => "Pengirim {$n}",
            'email' => "pengirim{$n}@contoh.test",
            'subject' => "Subjek contoh {$n}",
            'message' => 'Isi pesan contoh yang cukup panjang.',
            'consent_at' => '2026-10-05 10:00:00',
            'ip_hash' => hash('sha256', '127.0.0.1'.'kunci-uji'),
        ], $overrides));
    }

    // --- Kamus data ---

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
            ['id', 'name', 'slug', 'short_description', 'description', 'amenities', 'bed_capacity', 'beds_available', 'sample_rate', 'rate_unit', 'rate_note', 'is_active', 'sort_order', 'availability_updated_at', 'availability_updated_by', 'meta_title', 'meta_description', 'created_at', 'updated_at'],
            Schema::getColumnListing('room_categories'),
        );
        $this->assertSame(
            ['id', 'name', 'slug', 'category', 'short_description', 'description', 'is_active', 'sort_order', 'meta_title', 'meta_description', 'created_at', 'updated_at'],
            Schema::getColumnListing('facilities'),
        );
        $this->assertSame(
            ['id', 'name', 'email', 'phone', 'subject', 'message', 'status', 'consent_at', 'ip_hash', 'handled_by', 'handled_at', 'created_at', 'updated_at'],
            Schema::getColumnListing('contact_messages'),
        );
    }

    public function test_primary_keys_are_auto_increment(): void
    {
        foreach (['room_categories', 'facilities', 'contact_messages'] as $table) {
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

    public function test_only_the_documented_foreign_keys_exist_on_the_phase_4b_tables(): void
    {
        $this->assertCount(1, Schema::getForeignKeys('room_categories'));
        $this->assertCount(1, Schema::getForeignKeys('contact_messages'));
        $this->assertSame([], Schema::getForeignKeys('facilities'), 'facilities tidak punya FK.');
    }

    public function test_migrations_are_registered_under_the_documented_names(): void
    {
        $names = DB::table('migrations')->pluck('migration')->all();

        foreach ([
            '2026_10_02_001200_create_room_categories_table',
            '2026_10_02_001300_create_facilities_table',
            '2026_10_02_002000_create_contact_messages_table',
        ] as $migration) {
            $this->assertContains($migration, $names);
            $this->assertFileExists(database_path("migrations/{$migration}.php"));
        }
    }

    public function test_every_down_method_drops_its_table(): void
    {
        foreach ([
            '001200_create_room_categories_table' => 'room_categories',
            '001300_create_facilities_table' => 'facilities',
            '002000_create_contact_messages_table' => 'contact_messages',
        ] as $suffix => $table) {
            $source = (string) file_get_contents(database_path("migrations/2026_10_02_{$suffix}.php"));

            $this->assertStringContainsString("Schema::dropIfExists('{$table}');", $source);
        }
    }

    // --- CHECK ---

    public function test_only_chk_room_categories_beds_exists_as_a_named_check_on_the_phase_4b_tables(): void
    {
        $this->assertSame(['room_categories.chk_room_categories_beds'], $this->namedChecks());
    }

    #[DataProvider('checkViolations')]
    public function test_check_constraint_rejects_the_value_with_errno_4025(string $constraint, Closure $insert): void
    {
        try {
            $insert($this);
            $this->fail("Constraint {$constraint} seharusnya menolak penyisipan.");
        } catch (QueryException $e) {
            $this->assertSame('23000', $e->errorInfo[0], 'SQLSTATE');
            $this->assertSame(4025, $e->errorInfo[1], 'errno');
            $this->assertStringContainsString("`{$constraint}`", $e->getMessage());
        }

        $this->assertSame(0, DB::table('room_categories')->count());
    }

    public function test_bed_check_accepts_the_boundary_values(): void
    {
        $this->insertRoom(['bed_capacity' => 12, 'beds_available' => 12]);
        $this->insertRoom(['bed_capacity' => 12, 'beds_available' => 0]);
        $this->insertRoom(['bed_capacity' => 0, 'beds_available' => 0]);
        $this->insertRoom(['bed_capacity' => 1, 'beds_available' => 1]);
        $this->insertRoom();

        $this->assertSame(5, DB::table('room_categories')->count());
    }

    public function test_bed_check_is_also_enforced_when_availability_is_raised_above_capacity_on_update(): void
    {
        $id = $this->insertRoom(['bed_capacity' => 10, 'beds_available' => 4]);

        try {
            DB::table('room_categories')->where('id', $id)->update(['beds_available' => 11]);
            $this->fail('Pembaruan yang melebihi kapasitas seharusnya ditolak.');
        } catch (QueryException $e) {
            $this->assertSame(4025, $e->errorInfo[1]);
            $this->assertStringContainsString('`chk_room_categories_beds`', $e->getMessage());
        }

        DB::table('room_categories')->where('id', $id)->update(['beds_available' => 10]);

        $this->assertSame(10, (int) DB::table('room_categories')->where('id', $id)->value('beds_available'));
    }

    public function test_bed_check_is_also_enforced_when_capacity_is_lowered_below_availability_on_update(): void
    {
        $id = $this->insertRoom(['bed_capacity' => 10, 'beds_available' => 6]);

        try {
            DB::table('room_categories')->where('id', $id)->update(['bed_capacity' => 5]);
            $this->fail('Kapasitas di bawah ketersediaan seharusnya ditolak.');
        } catch (QueryException $e) {
            $this->assertSame(4025, $e->errorInfo[1]);
            $this->assertStringContainsString('`chk_room_categories_beds`', $e->getMessage());
        }

        $this->assertSame(10, (int) DB::table('room_categories')->where('id', $id)->value('bed_capacity'));
    }

    public function test_a_negative_availability_is_rejected_by_the_unsigned_column(): void
    {
        $this->expectException(QueryException::class);

        $this->insertRoom(['bed_capacity' => 10, 'beds_available' => -1]);
    }

    public function test_amenities_accepts_null_and_a_valid_json_array(): void
    {
        $withJson = $this->insertRoom(['amenities' => json_encode(['AC', 'Televisi', 'Kamar mandi dalam'])]);
        $withNull = $this->insertRoom(['amenities' => null]);

        $this->assertSame(['AC', 'Televisi', 'Kamar mandi dalam'], json_decode((string) DB::table('room_categories')->where('id', $withJson)->value('amenities'), true));
        $this->assertNull(DB::table('room_categories')->where('id', $withNull)->value('amenities'));
    }

    // --- Perilaku kendala dan default ---

    public function test_room_category_defaults(): void
    {
        $row = DB::table('room_categories')->find($this->insertRoom());

        $this->assertSame(0, (int) $row->bed_capacity);
        $this->assertSame(0, (int) $row->beds_available);
        $this->assertSame('per malam', $row->rate_unit);
        $this->assertSame(1, (int) $row->is_active);
        $this->assertSame(0, (int) $row->sort_order);
        $this->assertNull($row->description);
        $this->assertNull($row->amenities);
        $this->assertNull($row->sample_rate);
        $this->assertNull($row->rate_note);
        $this->assertNull($row->availability_updated_at);
        $this->assertNull($row->availability_updated_by);
        $this->assertNull($row->meta_title);
        $this->assertNull($row->meta_description);
    }

    public function test_sample_rate_keeps_two_decimals(): void
    {
        $id = $this->insertRoom(['sample_rate' => 1200000]);

        $this->assertSame('1200000.00', (string) DB::table('room_categories')->where('id', $id)->value('sample_rate'));
    }

    public function test_room_category_slug_is_unique_and_case_insensitive_but_name_is_not_unique(): void
    {
        $this->insertRoom(['name' => 'Kamar VIP', 'slug' => 'vip']);
        $this->insertRoom(['name' => 'Kamar VIP', 'slug' => 'vip-2']);

        $this->assertSame(2, DB::table('room_categories')->where('name', 'Kamar VIP')->count());

        try {
            $this->insertRoom(['slug' => 'VIP']);
            $this->fail('Slug kamar ganda seharusnya ditolak.');
        } catch (QueryException $e) {
            $this->assertSame(1062, $e->errorInfo[1]);
            $this->assertStringContainsString('room_categories_slug_unique', $e->getMessage());
        }
    }

    public function test_room_category_long_text_columns_hold_their_documented_lengths(): void
    {
        $id = $this->insertRoom([
            'name' => str_repeat('n', 100),
            'slug' => str_repeat('s', 120),
            'short_description' => str_repeat('d', 300),
            'description' => str_repeat('x', 30000),
            'rate_note' => str_repeat('r', 255),
        ]);

        $row = DB::table('room_categories')->find($id);

        $this->assertSame(100, strlen($row->name));
        $this->assertSame(120, strlen($row->slug));
        $this->assertSame(300, strlen($row->short_description));
        $this->assertSame(30000, strlen((string) $row->description));
    }

    public function test_deleting_a_user_sets_availability_updated_by_to_null(): void
    {
        $user = User::factory()->create();
        $roomId = $this->insertRoom(['availability_updated_by' => $user->id, 'availability_updated_at' => '2026-10-05 08:00:00']);

        DB::table('users')->where('id', $user->id)->delete();

        $row = DB::table('room_categories')->find($roomId);

        $this->assertNull($row->availability_updated_by);
        $this->assertSame('2026-10-05 08:00:00', $row->availability_updated_at, 'Waktu pembaruan tidak ikut terhapus.');
    }

    public function test_availability_updated_by_rejects_an_unknown_user(): void
    {
        $this->expectException(QueryException::class);

        $this->insertRoom(['availability_updated_by' => 999999]);
    }

    public function test_facility_defaults(): void
    {
        $row = DB::table('facilities')->find($this->insertFacility());

        $this->assertSame(1, (int) $row->is_active);
        $this->assertSame(0, (int) $row->sort_order);
        $this->assertNull($row->description);
        $this->assertNull($row->meta_title);
        $this->assertNull($row->meta_description);
    }

    public function test_facility_slug_is_unique_and_case_insensitive_but_name_is_not_unique(): void
    {
        $this->insertFacility(['name' => 'Laboratorium', 'slug' => 'fasilitas-laboratorium']);
        $this->insertFacility(['name' => 'Laboratorium', 'slug' => 'fasilitas-laboratorium-2']);

        $this->assertSame(2, DB::table('facilities')->where('name', 'Laboratorium')->count());

        try {
            $this->insertFacility(['slug' => 'Fasilitas-Laboratorium']);
            $this->fail('Slug fasilitas ganda seharusnya ditolak.');
        } catch (QueryException $e) {
            $this->assertSame(1062, $e->errorInfo[1]);
            $this->assertStringContainsString('facilities_slug_unique', $e->getMessage());
        }
    }

    public function test_facility_long_text_columns_hold_their_documented_lengths(): void
    {
        $id = $this->insertFacility([
            'name' => str_repeat('n', 150),
            'slug' => str_repeat('s', 170),
            'short_description' => str_repeat('d', 300),
            'description' => str_repeat('x', 30000),
        ]);

        $row = DB::table('facilities')->find($id);

        $this->assertSame(150, strlen($row->name));
        $this->assertSame(170, strlen($row->slug));
        $this->assertSame(300, strlen($row->short_description));
    }

    public function test_contact_message_defaults(): void
    {
        $row = DB::table('contact_messages')->find($this->insertMessage());

        $this->assertSame('new', $row->status);
        $this->assertNull($row->phone);
        $this->assertNull($row->handled_by);
        $this->assertNull($row->handled_at);
        $this->assertSame(64, strlen($row->ip_hash));
        $this->assertSame('2026-10-05 10:00:00', $row->consent_at);
    }

    public function test_contact_message_ip_hash_holds_exactly_a_sha256_hex_digest(): void
    {
        $hash = hash('sha256', '203.0.113.7'.'kunci-uji');

        $id = $this->insertMessage(['ip_hash' => $hash]);

        $this->assertSame($hash, DB::table('contact_messages')->where('id', $id)->value('ip_hash'));

        $this->expectException(QueryException::class);

        $this->insertMessage(['ip_hash' => $hash.'0']);
    }

    public function test_contact_message_requires_consent_and_ip_hash(): void
    {
        foreach (['consent_at', 'ip_hash'] as $column) {
            try {
                $row = [
                    'name' => 'Pengirim', 'email' => 'pengirim@contoh.test', 'subject' => 'Subjek', 'message' => 'Isi pesan contoh.',
                    'consent_at' => '2026-10-05 10:00:00', 'ip_hash' => hash('sha256', 'x'),
                ];
                unset($row[$column]);

                DB::table('contact_messages')->insert($row);
                $this->fail("Kolom {$column} wajib diisi.");
            } catch (QueryException $e) {
                $this->assertSame(1364, $e->errorInfo[1], "{$column}: kolom NOT NULL tanpa default ditolak pada mode strict.");
            }
        }

        $this->assertSame(0, DB::table('contact_messages')->count());
    }

    public function test_contact_message_holds_a_two_thousand_character_message(): void
    {
        $id = $this->insertMessage(['message' => str_repeat('p', 2000), 'phone' => '081234567890', 'name' => str_repeat('n', 100), 'subject' => str_repeat('s', 150)]);

        $row = DB::table('contact_messages')->find($id);

        $this->assertSame(2000, strlen($row->message));
        $this->assertSame('081234567890', $row->phone);
    }

    public function test_deleting_a_staff_user_sets_handled_by_to_null_and_keeps_the_message(): void
    {
        $user = User::factory()->staff()->create();
        $messageId = $this->insertMessage(['status' => 'replied', 'handled_by' => $user->id, 'handled_at' => '2026-10-05 11:00:00']);

        DB::table('users')->where('id', $user->id)->delete();

        $row = DB::table('contact_messages')->find($messageId);

        $this->assertNull($row->handled_by);
        $this->assertSame('replied', $row->status);
        $this->assertSame('2026-10-05 11:00:00', $row->handled_at);
    }

    public function test_handled_by_rejects_an_unknown_user(): void
    {
        $this->expectException(QueryException::class);

        $this->insertMessage(['handled_by' => 999999]);
    }

    public function test_datetime_columns_keep_the_wall_clock_value_without_conversion(): void
    {
        $roomId = $this->insertRoom(['bed_capacity' => 6, 'beds_available' => 2, 'availability_updated_at' => '2026-10-05 23:30:00']);
        $messageId = $this->insertMessage(['consent_at' => '2026-10-05 00:00:00', 'handled_at' => '2026-10-31 23:59:59']);

        $this->assertSame('2026-10-05 23:30:00', DB::table('room_categories')->where('id', $roomId)->value('availability_updated_at'));
        $this->assertSame('2026-10-05 00:00:00', DB::table('contact_messages')->where('id', $messageId)->value('consent_at'));
        $this->assertSame('2026-10-31 23:59:59', DB::table('contact_messages')->where('id', $messageId)->value('handled_at'));
    }
}
```

Catatan: `test_deleting_a_user_sets_availability_updated_by_to_null` dan `test_deleting_a_staff_user_sets_handled_by_to_null_and_keeps_the_message` menghapus baris `users` langsung lewat `DB::table()`; tidak ada FK lain yang merujuk pengguna baru ini, sehingga penghapusan tidak ditolak.

- [ ] **Step 3: Edit tes lama `tests/Feature/Database/ContentSchemaTest.php` (hapus tes penjaga "migration 4b belum ada", F4B-R37)**

Edit `tests/Feature/Database/ContentSchemaTest.php`: ganti teks berikut (persis)

```php
    public function test_the_phase_4b_migrations_are_not_created_yet(): void
    {
        foreach (['001200_create_room_categories_table', '001300_create_facilities_table', '002000_create_contact_messages_table'] as $suffix) {
            $this->assertFileDoesNotExist(database_path("migrations/2026_10_02_{$suffix}.php"));
        }
    }

    public function test_every_down_method_drops_its_table(): void
```

dengan

```php
    public function test_every_down_method_drops_its_table(): void
```

- [ ] **Step 4: Jalankan, harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=FacilitySchemaTest
```
Expected: FAIL. Provider kolom/index/FK gagal dengan "Kolom room_categories.id belum ada." / "Index room_categories.primary belum ada." / "FK room_categories_availability_updated_by_foreign belum ada."; tes perilaku gagal dengan `QueryException` "Base table or view not found: 1146 Table '…room_categories' doesn't exist" (atau `facilities`, `contact_messages`); tes berkas migration gagal pada `assertFileExists`; `test_only_chk_room_categories_beds_exists_…` gagal karena daftar CHECK kosong.

- [ ] **Step 5: Implementasi migration `room_categories` dengan CHECK (tool Write) `database/migrations/2026_10_02_001200_create_room_categories_table.php`**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 09 §5.1 dan §11. Model kapasitas agregat per kategori (00 E.4); ketersediaan diperbarui manual oleh staf.
     * `amenities` bertipe JSON (array teks datar) sehingga MariaDB membuat CHECK `json_valid` otomatis dengan nama
     * kolom. CHECK `chk_room_categories_beds` menjadi pagar terakhir setelah Form Request. Pada DB yang sudah
     * memigrasikan fase 1 sampai 4a, migration ini berjalan sebagai migration tertunda (F4B-R2).
     */
    public function up(): void
    {
        Schema::create('room_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 120)->unique();
            $table->string('short_description', 300);
            $table->text('description')->nullable();
            $table->json('amenities')->nullable();
            $table->unsignedSmallInteger('bed_capacity')->default(0);
            $table->unsignedSmallInteger('beds_available')->default(0);
            $table->decimal('sample_rate', 12, 2)->nullable();
            $table->string('rate_unit', 30)->default('per malam');
            $table->string('rate_note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->dateTime('availability_updated_at')->nullable();
            $table->foreignId('availability_updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        DB::statement('ALTER TABLE room_categories ADD CONSTRAINT chk_room_categories_beds CHECK (beds_available >= 0 AND beds_available <= bed_capacity)');
    }

    public function down(): void
    {
        Schema::dropIfExists('room_categories');
    }
};
```

- [ ] **Step 6: Implementasi migration `facilities` (tool Write) `database/migrations/2026_10_02_001300_create_facilities_table.php`**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 09 §5.2. `category` bernilai `FacilityCategory` (`medis`, `penunjang`, `umum`) tanpa CHECK nilai
     * (konvensi 09 §1.2 butir 5); daftar nilai dijaga enum dan FacilityRequest. Tanpa FK: fasilitas tidak
     * dirujuk tabel lain dan tidak merujuk tabel lain.
     */
    public function up(): void
    {
        Schema::create('facilities', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->string('slug', 170)->unique();
            $table->string('category', 20);
            $table->string('short_description', 300);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'category', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facilities');
    }
};
```

- [ ] **Step 7: Implementasi migration `contact_messages` (tool Write) `database/migrations/2026_10_02_002000_create_contact_messages_table.php`**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 09 §6.8. Pesan dari formulir kontak publik. IP mentah tidak disimpan: `ip_hash` berisi SHA-256 heksadesimal
     * (64 karakter) dari IP dan APP_KEY. `status` bernilai `ContactMessageStatus` (`new` bawaan), `handled_by` dan
     * `handled_at` diisi staf saat pesan dibalas atau diarsipkan. Tidak ada route hapus (00 C); pesan hanya diarsipkan.
     */
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('email');
            $table->string('phone', 20)->nullable();
            $table->string('subject', 150);
            $table->text('message');
            $table->string('status', 10)->default('new');
            $table->dateTime('consent_at');
            $table->char('ip_hash', 64);
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('handled_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
```

- [ ] **Step 8: Jalankan tes tugas, harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=FacilitySchemaTest
& $PHP artisan test --filter="ContentSchemaTest|ScheduleSchemaTest|MasterDataSchemaTest"
```
Expected: seluruh tes PASS (±75 di `FacilitySchemaTest`; `ContentSchemaTest` kini satu tes lebih sedikit). Bila `test_index_matches_the_data_dictionary` gagal untuk `room_categories_availability_updated_by_foreign` atau `contact_messages_handled_by_foreign`, periksa bahwa `foreignId(...)->constrained()` memakai nama default; jangan menambah index manual. Bila `test_check_constraint_rejects_the_value_with_errno_4025` gagal untuk `amenities` karena pesan tidak memuat `` `amenities` ``, bandingkan dengan `ScheduleSchemaTest` kasus `pending_change` (perilaku identik).

- [ ] **Step 9: Pint**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint
& $PHP vendor\bin\pint --test
```
Expected: `--test` PASS (0 berkas bermasalah).

- [ ] **Step 10: Suite penuh (latar belakang)**

Suite ±11–14 menit melebihi batas 10 menit sebuah perintah tool. Jalankan perintah berikut sebagai tugas latar belakang (tool PowerShell dengan `run_in_background`), tunggu sampai selesai, lalu baca ringkasannya.

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
$LOG = Join-Path $env:TEMP "suite-fase4b-t01.txt"
& $PHP artisan test | Out-File -FilePath $LOG -Encoding utf8
Select-String -Path $LOG -Pattern 'Tests:|FAIL|Duration' | Select-Object -Last 20
```
Expected: seluruh tes PASS (±4420 tes: baseline 4348 − 1 tes yang dihapus + ±75 tes baru). Tidak ada tes lain yang berubah, karena tabel baru belum dipakai kode mana pun.

- [ ] **Step 11: Commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git add database/migrations/2026_10_02_001200_create_room_categories_table.php database/migrations/2026_10_02_001300_create_facilities_table.php database/migrations/2026_10_02_002000_create_contact_messages_table.php tests/Feature/Database/FacilitySchemaTest.php tests/Feature/Database/ContentSchemaTest.php
git commit -m @'
feat(fase-4b): skema fasilitas, kamar rawat inap, dan pesan kontak beserta tes skema

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
'@
```

---

### Task 2: Model, factory, dan pembantu tes `InteractsWithFacilities`

**Files:**
- Create: `app/Models/Facility.php`
- Create: `app/Models/RoomCategory.php`
- Create: `app/Models/ContactMessage.php`
- Create: `database/factories/FacilityFactory.php`, `database/factories/RoomCategoryFactory.php`, `database/factories/ContactMessageFactory.php`
- Create: `tests/Support/InteractsWithFacilities.php`
- Test: `tests/Feature/Facilities/FacilityModelTest.php`, `tests/Feature/Facilities/RoomCategoryModelTest.php`, `tests/Feature/Facilities/ContactMessageModelTest.php`, `tests/Feature/Facilities/InteractsWithFacilitiesTest.php`
- Tidak diubah: `HasUniqueSlug`, `HasMedia`, `SanitizesRichText`, `AuditObserver`, `AppServiceProvider`, `MediaPolicy`, `MediaService`, `lang/id/messages.php` (seluruhnya Task 3); enum `FacilityCategory` dan `ContactMessageStatus` (sudah ada; `EnumCatalogTest` mengunci 15 berkas enum, jadi enum tidak boleh ditambah atau diubah).

**Interfaces:**
- Consumes: tiga tabel migration Task 1; enum `FacilityCategory` dan `ContactMessageStatus` (fase 1); trait `HasMedia`, `HasUniqueSlug`, `SanitizesRichText`; `MediaService`; `ImageFixtures`; `InteractsWithMedia::createMediaRow()`; `User::factory()->staff()`.
- Produces (persis kerangka C.2):
  - `App\Models\Facility`: scope `active`, `ordered`, `ofCategory(FacilityCategory)`; `richTextAttributes()` = `['description']`; accessor `cover_url`, `seo_title`, `seo_description`; `slugMaxLength()` = 170.
  - `App\Models\RoomCategory`: konstanta `STALE_AFTER_HOURS = 24`; relasi `availabilityUpdater()`; scope `active`, `ordered`; `richTextAttributes()` = `['description']`; accessor `cover_url`, `seo_title`, `seo_description`, `occupancy_label`, `availability_updated_label`, `is_availability_stale`, `is_full`, `sample_rate_label`; `slugMaxLength()` = 120.
  - `App\Models\ContactMessage`: relasi `handler()`; scope `unread()`; `$hidden = ['ip_hash']`; `handled_by` dan `handled_at` tidak fillable (diisi lewat `forceFill`).
  - `FacilityFactory` (state `category(FacilityCategory)`, `inactive()`), `RoomCategoryFactory` (state `full()`, `inactive()`, `withoutRate()`; bawaan `availability_updated_at` = sekarang − 1 jam), `ContactMessageFactory` (state `read()`, `replied()`, `archived()`).
  - Trait tes `Tests\Support\InteractsWithFacilities`: `createFacility(array $attributes = []): Facility`, `createRoomCategory(array $attributes = []): RoomCategory`, `createContactMessage(array $attributes = []): ContactMessage` (`handled_by`/`handled_at` boleh ada di `$attributes`; diisi lewat `forceFill`).

**Test IDs:** CRD-06 dan CRD-07 (sisi model: scope tayang, label ketersediaan, tarif contoh), BR-114 (batas 24 jam kedaluwarsa), BR-115/BR-112 (CHECK DB tetap menolak nilai di luar batas), BR-117 (slug tidak berubah saat nama diedit), BR-122 dan SEK-08 (sanitasi `description`), BR-123 (fallback SEO), invarian D-58 (rich text kosong disimpan NULL), Review Focus F.1 (accessor kamar dan batas 24 jam), Review Focus F.2 (teks pesan tersimpan apa adanya dan `ip_hash` tidak ikut serialisasi), Review Focus F.3 (mode ketat: relasi wajib di-eager-load).

**Catatan teknis (diverifikasi dari source):**
- **Gaya model** meniru `app/Models/Clinic.php`: `use HasFactory, HasMedia, HasUniqueSlug, SanitizesRichText;`, `$fillable` eksplisit, `casts()` berupa method, accessor `Attribute::get(...)`, `slugMaxLength()` protected, scope `void` (pola `Banner` dan `InsurancePartner`) dengan `qualifyColumn`. Model tidak memakai `declare(strict_types=1)` (konsisten dengan `Clinic`).
- **Mode ketat** (`AppServiceProvider.php:45` `Model::shouldBeStrict(! $this->app->isProduction())`): lazy loading hanya dilarang bila model berasal dari kueri dengan lebih dari satu baris, sehingga tes pelanggaran memakai ≥ 3 baris. Accessor media memakai `HasMedia::mediaUrl()` yang bertumpu pada `getRelationValue('media')`; `RoomCategory::availabilityUpdater` dan `ContactMessage::handler` juga wajib di-eager-load oleh pemakainya.
- **Slug** (`app/Models/Concerns/HasUniqueSlug.php`): dibuat saat `creating` bila kosong dari kolom `name` (bawaan `slugSource()`), sufiks `-2`, `-3`, dipotong sesuai `slugMaxLength()`, dan tidak berubah saat nama diedit (BR-117). Form admin tidak punya input slug (F4B-R5); seeder mengisi slug eksplisit (Task 10).
- **Sanitasi rich text** (`SanitizesRichText`): pada `saving` kolom `description` yang dirty disanitasi `HtmlSanitizer`; `&nbsp;` menjadi spasi; hasil kosong secara visual (`<p><br></p>`, `<p>&nbsp;</p>`, `''`) disimpan `NULL`. Pengikatan gambar editor ke pemilik (`syncEditorImages`) baru aktif untuk `facilities` dan `room_categories` setelah Task 3 menambah `EDITOR_REFERENCE_COLUMNS`; Task 2 hanya bergantung pada hook `saving`.
- **Waktu.** Tabel `room_categories` menyimpan `availability_updated_at` sebagai `DATETIME` waktu dinding aplikasi (`Asia/Jakarta`). Accessor memakai `now()` aplikasi, bukan `NOW()` SQL (Global Constraints). Tes memakai `$this->travelTo(Carbon::parse('2026-10-05 10:00:00'))` agar batas 24 jam deterministik; nilai tersimpan selalu bulat detik, sehingga perbandingan `lt()` dengan `now()->subHours(24)` pada waktu beku tepat di batas.
- **Label tanggal** (F4B-R10): `translatedFormat('j M Y')` memakai lokal `id` (`APP_LOCALE=id`, juga di `.env.testing`), sehingga Oktober menjadi "Okt", Agustus "Agt", Desember "Des" (`vendor/nesbot/carbon/src/Carbon/Lang/id.php`). Jam memakai `format('H.i')` (titik, bukan titik dua). Kata "real-time" tidak boleh muncul pada label mana pun.
- **Tarif contoh** (F4B-R12): `'Rp '.number_format((float) $this->sample_rate, 0, ',', '.').' '.$this->rate_unit` (tanpa `Number::currency`, agar tidak ada spasi tak putus). Cast `decimal:2` menghasilkan string `"400000.00"`; `(float)` menormalkannya.
- **Factory.** Laravel memakai `Model::unguarded` saat factory mengisi atribut (`Factory::makeInstance`), sehingga `handled_by` dan `handled_at` pada state `replied()`/`archived()` terisi walau tidak fillable. Atribut berupa `Factory` (`'handled_by' => User::factory()->staff()`) diperluas dengan membuat baris `users` baru, juga pada `make()`; state `replied()`/`archived()` selalu mengisi `handled_by` dengan akun staf baru. `ContactMessageFactory::definition()` memakai `config('app.key')` untuk `ip_hash`, sehingga APP_KEY tes tidak pernah bocor ke tes lain.
- **`ContactMessage` tidak diobservasi** `AuditObserver` (F4B-R25, Task 3), dan tidak memakai `HasMedia`, `HasUniqueSlug`, maupun `SanitizesRichText`. Isi pesan adalah teks biasa yang dirender dengan `{{ }}`; model menyimpannya apa adanya (tes memastikan `<script>` tidak diubah).
- **Pembantu tes** `createContactMessage()` memisahkan `handled_by`/`handled_at` dari `$attributes` dan mengisinya lewat `forceFill()->save()` setelah baris dibuat. Dengan begitu pembantu aman walau factory suatu saat tidak lagi tanpa guard.
- Jumlah tes ±75.

- [ ] **Step 1: Verifikasi prasyarat (Task 1 selesai)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git branch --show-current
git status --short
Test-Path database\migrations\2026_10_02_002000_create_contact_messages_table.php
Get-ChildItem app\Models | Select-Object -ExpandProperty Name
& $PHP artisan test --filter=FacilitySchemaTest
```
Expected: `fase-4b-fasilitas-kamar-kontak`; `git status` bersih (atau hanya `?? .superpowers/`); `True`; daftar `app\Models` **belum** memuat `Facility.php`, `RoomCategory.php`, dan `ContactMessage.php`; `FacilitySchemaTest` PASS.

- [ ] **Step 2: Tulis pembantu tes (tool Write) `tests/Support/InteractsWithFacilities.php`**

```php
<?php

namespace Tests\Support;

use App\Models\ContactMessage;
use App\Models\Facility;
use App\Models\RoomCategory;
use Illuminate\Support\Arr;

/**
 * Pembantu tes untuk fasilitas, kategori kamar rawat inap, dan pesan kontak fase 4b. Dipakai bersama
 * RefreshDatabase. Semua pembantu memakai factory sehingga sanitasi, slug, dan observer audit ikut berjalan.
 */
trait InteractsWithFacilities
{
    /**
     * Fasilitas aktif kategori `umum` (slug dibuat otomatis dari nama).
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function createFacility(array $attributes = []): Facility
    {
        return Facility::factory()->create($attributes);
    }

    /**
     * Kategori kamar aktif, kapasitas 12, tersedia 5, tarif contoh 400000 per malam, diperbarui satu jam lalu
     * oleh siapa pun (`availability_updated_by` NULL). Nilai lain lewat $attributes atau state factory.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function createRoomCategory(array $attributes = []): RoomCategory
    {
        return RoomCategory::factory()->create($attributes);
    }

    /**
     * Pesan kontak baru (`new`) dengan persetujuan dan `ip_hash` terisi. `handled_by` dan `handled_at` boleh ada
     * di $attributes: keduanya tidak fillable sehingga diisi lewat forceFill() setelah baris dibuat.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function createContactMessage(array $attributes = []): ContactMessage
    {
        $handled = Arr::only($attributes, ['handled_by', 'handled_at']);
        $message = ContactMessage::factory()->create(Arr::except($attributes, ['handled_by', 'handled_at']));

        if ($handled !== []) {
            $message->forceFill($handled)->save();
        }

        return $message;
    }
}
```

- [ ] **Step 3: Tulis tes yang gagal (tool Write) `tests/Feature/Facilities/FacilityModelTest.php`**

```php
<?php

namespace Tests\Feature\Facilities;

use App\Enums\FacilityCategory;
use App\Models\Facility;
use App\Models\Media;
use App\Services\Media\MediaService;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ImageFixtures;
use Tests\Support\InteractsWithFacilities;
use Tests\Support\InteractsWithMedia;
use Tests\TestCase;

/**
 * Model Facility (09 §5.2, F4B-R4, F4B-R5): scope tayang, slug otomatis, sanitasi `description`, fallback SEO.
 */
class FacilityModelTest extends TestCase
{
    use InteractsWithFacilities;
    use InteractsWithMedia;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_fillable_and_casts_follow_the_blueprint(): void
    {
        $this->assertSame(
            ['name', 'slug', 'category', 'short_description', 'description', 'is_active', 'sort_order', 'meta_title', 'meta_description'],
            (new Facility)->getFillable(),
        );

        $casts = (new Facility)->getCasts();

        $this->assertSame(FacilityCategory::class, $casts['category']);
        $this->assertSame('boolean', $casts['is_active']);
        $this->assertSame('integer', $casts['sort_order']);
    }

    public function test_unknown_attributes_are_rejected_in_strict_mode(): void
    {
        $this->expectException(MassAssignmentException::class);

        Facility::factory()->make()->fill(['bukan_kolom' => 'x']);
    }

    public function test_casts_and_database_defaults(): void
    {
        $created = Facility::query()->create(['name' => 'Apotek', 'category' => 'penunjang', 'short_description' => 'Layanan penyiapan obat.']);
        $facility = Facility::query()->findOrFail($created->id);

        $this->assertSame(FacilityCategory::Penunjang, $facility->category);
        $this->assertTrue($facility->is_active);
        $this->assertSame(0, $facility->sort_order);
        $this->assertNull($facility->description);
        $this->assertNull($facility->meta_title);
        $this->assertNull($facility->meta_description);
        $this->assertSame('apotek', $facility->slug);
    }

    public function test_factory_states(): void
    {
        $plain = $this->createFacility();
        $medis = Facility::factory()->category(FacilityCategory::Medis)->create();
        $inactive = Facility::factory()->inactive()->create();

        $this->assertSame(FacilityCategory::Umum, $plain->category);
        $this->assertTrue($plain->is_active);
        $this->assertSame(FacilityCategory::Medis, $medis->category);
        $this->assertFalse($inactive->is_active);
        $this->assertNotSame($plain->name, $medis->name);
    }

    public function test_active_and_ordered_scopes_sort_by_order_then_name_then_id(): void
    {
        $b = $this->createFacility(['name' => 'B Ruang', 'sort_order' => 1]);
        $a = $this->createFacility(['name' => 'A Ruang', 'sort_order' => 1]);
        $c = $this->createFacility(['name' => 'C Ruang', 'sort_order' => 0]);
        $off = Facility::factory()->inactive()->create(['name' => 'Z Ruang', 'sort_order' => 0]);
        $same1 = $this->createFacility(['name' => 'Sama', 'sort_order' => 2]);
        $same2 = $this->createFacility(['name' => 'Sama', 'sort_order' => 2]);

        $this->assertSame([$c->id, $off->id, $a->id, $b->id, $same1->id, $same2->id], Facility::query()->ordered()->pluck('id')->all());
        $this->assertSame([$c->id, $a->id, $b->id, $same1->id, $same2->id], Facility::query()->active()->ordered()->pluck('id')->all());
        $this->assertSame([$off->id], Facility::query()->where('is_active', false)->pluck('id')->all());
    }

    public function test_of_category_scope(): void
    {
        $medis = Facility::factory()->category(FacilityCategory::Medis)->create();
        $penunjangA = Facility::factory()->category(FacilityCategory::Penunjang)->create();
        $penunjangB = Facility::factory()->category(FacilityCategory::Penunjang)->create();
        $umum = Facility::factory()->category(FacilityCategory::Umum)->create();

        $this->assertSame([$medis->id], Facility::query()->ofCategory(FacilityCategory::Medis)->pluck('id')->all());
        $this->assertSame([$penunjangA->id, $penunjangB->id], Facility::query()->ofCategory(FacilityCategory::Penunjang)->orderBy('id')->pluck('id')->all());
        $this->assertSame([$umum->id], Facility::query()->ofCategory(FacilityCategory::Umum)->pluck('id')->all());
    }

    public function test_slug_is_derived_from_the_name_and_numbered_on_collision(): void
    {
        $first = $this->createFacility(['name' => 'Instalasi Gawat Darurat']);
        $second = $this->createFacility(['name' => 'Instalasi Gawat Darurat']);
        $third = $this->createFacility(['name' => 'Instalasi Gawat Darurat']);
        $explicit = $this->createFacility(['name' => 'Laboratorium', 'slug' => 'fasilitas-laboratorium']);

        $this->assertSame('instalasi-gawat-darurat', $first->slug);
        $this->assertSame('instalasi-gawat-darurat-2', $second->slug);
        $this->assertSame('instalasi-gawat-darurat-3', $third->slug);
        $this->assertSame('fasilitas-laboratorium', $explicit->slug);
        $this->assertSame(170, strlen(Facility::uniqueSlug(str_repeat('a', 400))));
    }

    public function test_slug_does_not_change_when_the_name_is_edited(): void
    {
        $facility = $this->createFacility(['name' => 'Musala']);

        $facility->update(['name' => 'Musala dan Ruang Wudu']);

        $this->assertSame('Musala dan Ruang Wudu', $facility->fresh()->name);
        $this->assertSame('musala', $facility->fresh()->slug);
    }

    public function test_description_is_sanitized_on_save(): void
    {
        $facility = $this->createFacility([
            'description' => '<p>Ruang <strong>tunggu</strong> nyaman.</p><script>alert(1)</script><a href="javascript:alert(2)" onclick="x()">Klik</a>',
        ]);

        $stored = (string) $facility->fresh()->description;

        $this->assertStringContainsString('<p>Ruang <strong>tunggu</strong> nyaman.</p>', $stored);
        $this->assertStringNotContainsString('<script', $stored);
        $this->assertStringNotContainsString('javascript:', $stored);
        $this->assertStringNotContainsString('onclick', $stored);
        $this->assertSame(['description'], $facility->richTextAttributes());
    }

    public function test_blank_rich_text_is_stored_as_null(): void
    {
        foreach (['<p><br></p>', '<p>&nbsp;</p>', '', '   '] as $blank) {
            $facility = $this->createFacility(['description' => $blank]);

            $this->assertNull($facility->fresh()->description, 'Kosong visual: '.var_export($blank, true));
        }

        $filled = $this->createFacility(['description' => '<p>Isi.</p>']);
        $filled->update(['description' => '<p><br></p>']);

        $this->assertNull($filled->fresh()->description);
    }

    public function test_seo_title_falls_back_from_meta_title_to_name(): void
    {
        $this->assertSame('Judul SEO Khusus', $this->createFacility(['name' => 'Apotek', 'meta_title' => 'Judul SEO Khusus'])->seo_title);
        $this->assertSame('Apotek', $this->createFacility(['name' => 'Apotek', 'meta_title' => ''])->seo_title);
        $this->assertSame('Apotek', $this->createFacility(['name' => 'Apotek', 'meta_title' => null])->seo_title);
    }

    public function test_seo_description_falls_back_from_meta_description_to_short_description(): void
    {
        $this->assertSame('Deskripsi meta.', $this->createFacility(['short_description' => 'Ringkas.', 'meta_description' => 'Deskripsi meta.'])->seo_description);
        $this->assertSame('Ringkas.', $this->createFacility(['short_description' => 'Ringkas.', 'meta_description' => ''])->seo_description);
        $this->assertSame('Ringkas.', $this->createFacility(['short_description' => 'Ringkas.', 'meta_description' => null])->seo_description);
    }

    public function test_cover_url_uses_the_placeholder_without_a_cover_and_the_media_url_with_one(): void
    {
        $withCover = $this->createFacility(['name' => 'Dengan Cover']);
        $withoutCover = $this->createFacility(['name' => 'Tanpa Cover']);
        $this->createFacility(['name' => 'Pengisi']);

        $media = $this->createMediaRow([
            'model_type' => Facility::class,
            'model_id' => $withCover->id,
            'collection' => 'cover',
            'alt_text' => 'Foto contoh Dengan Cover',
        ]);

        $loaded = Facility::query()->with('media')->orderBy('id')->get();

        $this->assertSame($media->url, $loaded[0]->cover_url);
        $this->assertSame(asset('images/placeholders/facility.svg'), $loaded[1]->cover_url);
        $this->assertSame($withCover->id, $loaded[0]->id);
        $this->assertSame($withoutCover->id, $loaded[1]->id);
    }

    public function test_listing_without_eager_loading_media_violates_strict_mode(): void
    {
        $this->createFacility();
        $this->createFacility();
        $this->createFacility();

        $facilities = Facility::query()->orderBy('id')->get();

        $this->expectException(LazyLoadingViolationException::class);

        $facilities->first()->cover_url;
    }

    public function test_deleting_a_facility_removes_its_media_rows_and_files(): void
    {
        $facility = $this->createFacility();
        $media = app(MediaService::class)->store(ImageFixtures::jpeg(900, 600, 'cover.jpg'), 'cover', $facility, ['alt_text' => 'Foto contoh']);

        Storage::disk('public')->assertExists($media->directory.'/'.$media->file_name);

        $facility->delete();

        $this->assertSame(0, Media::query()->count());
        Storage::disk('public')->assertMissing($media->directory.'/'.$media->file_name);
        $this->assertDatabaseMissing('facilities', ['id' => $facility->id]);
    }
}
```

- [ ] **Step 4: Tulis tes yang gagal (tool Write) `tests/Feature/Facilities/RoomCategoryModelTest.php`**

```php
<?php

namespace Tests\Feature\Facilities;

use App\Models\RoomCategory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithFacilities;
use Tests\Support\InteractsWithMedia;
use Tests\TestCase;

/**
 * Model RoomCategory (09 §5.1, F4B-R9 sampai F4B-R12, BR-114): label ketersediaan, batas 24 jam, tarif contoh.
 */
class RoomCategoryModelTest extends TestCase
{
    use InteractsWithFacilities;
    use InteractsWithMedia;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->travelTo(Carbon::parse('2026-10-05 10:00:00'));
    }

    public function test_fillable_constant_and_casts_follow_the_blueprint(): void
    {
        $this->assertSame(24, RoomCategory::STALE_AFTER_HOURS);
        $this->assertSame([
            'name', 'slug', 'short_description', 'description', 'amenities', 'bed_capacity', 'beds_available', 'sample_rate',
            'rate_unit', 'rate_note', 'is_active', 'sort_order', 'availability_updated_at', 'availability_updated_by',
            'meta_title', 'meta_description',
        ], (new RoomCategory)->getFillable());

        $casts = (new RoomCategory)->getCasts();

        $this->assertSame('array', $casts['amenities']);
        $this->assertSame('integer', $casts['bed_capacity']);
        $this->assertSame('integer', $casts['beds_available']);
        $this->assertSame('decimal:2', $casts['sample_rate']);
        $this->assertSame('boolean', $casts['is_active']);
        $this->assertSame('integer', $casts['sort_order']);
        $this->assertSame('datetime', $casts['availability_updated_at']);
    }

    public function test_unknown_attributes_are_rejected_in_strict_mode(): void
    {
        $this->expectException(MassAssignmentException::class);

        RoomCategory::factory()->make()->fill(['bukan_kolom' => 'x']);
    }

    public function test_database_defaults_and_casts(): void
    {
        $created = RoomCategory::query()->create(['name' => 'Kamar Contoh', 'short_description' => 'Ringkas.']);
        $room = RoomCategory::query()->findOrFail($created->id);

        $this->assertSame('kamar-contoh', $room->slug);
        $this->assertSame(0, $room->bed_capacity);
        $this->assertSame(0, $room->beds_available);
        $this->assertSame('per malam', $room->rate_unit);
        $this->assertTrue($room->is_active);
        $this->assertSame(0, $room->sort_order);
        $this->assertNull($room->amenities);
        $this->assertNull($room->sample_rate);
        $this->assertNull($room->availability_updated_at);
        $this->assertNull($room->availability_updated_by);
    }

    public function test_factory_defaults_and_states(): void
    {
        $plain = $this->createRoomCategory();
        $full = RoomCategory::factory()->full()->create();
        $inactive = RoomCategory::factory()->inactive()->create();
        $withoutRate = RoomCategory::factory()->withoutRate()->create();

        $this->assertSame(12, $plain->bed_capacity);
        $this->assertSame(5, $plain->beds_available);
        $this->assertTrue($plain->is_active);
        $this->assertSame('400000.00', $plain->fresh()->sample_rate);
        $this->assertSame('per malam', $plain->rate_unit);
        $this->assertSame('2026-10-05 09:00:00', $plain->fresh()->availability_updated_at->toDateTimeString(), 'Bawaan: satu jam yang lalu.');
        $this->assertNull($plain->availability_updated_by);
        $this->assertSame(0, $full->beds_available);
        $this->assertSame(12, $full->bed_capacity);
        $this->assertFalse($inactive->is_active);
        $this->assertNull($withoutRate->fresh()->sample_rate);
        $this->assertNull($withoutRate->fresh()->rate_note);
    }

    public function test_the_database_check_still_rejects_availability_above_capacity(): void
    {
        $this->expectException(QueryException::class);

        $this->createRoomCategory(['bed_capacity' => 5, 'beds_available' => 6]);
    }

    public function test_active_and_ordered_scopes(): void
    {
        $b = $this->createRoomCategory(['name' => 'Kamar B', 'sort_order' => 1]);
        $a = $this->createRoomCategory(['name' => 'Kamar A', 'sort_order' => 1]);
        $c = $this->createRoomCategory(['name' => 'Kamar C', 'sort_order' => 0]);
        $off = RoomCategory::factory()->inactive()->create(['name' => 'Kamar Z', 'sort_order' => 0]);

        $this->assertSame([$c->id, $off->id, $a->id, $b->id], RoomCategory::query()->ordered()->pluck('id')->all());
        $this->assertSame([$c->id, $a->id, $b->id], RoomCategory::query()->active()->ordered()->pluck('id')->all());
    }

    public function test_slug_is_derived_from_the_name_numbered_on_collision_and_stable_on_rename(): void
    {
        $first = $this->createRoomCategory(['name' => 'Kamar VIP/Pribadi']);
        $second = $this->createRoomCategory(['name' => 'Kamar VIP/Pribadi']);
        $explicit = $this->createRoomCategory(['name' => 'Kamar BPJS Kelas 1', 'slug' => 'bpjs-kelas-1']);

        $this->assertSame('kamar-vippribadi', $first->slug);
        $this->assertSame('kamar-vippribadi-2', $second->slug);
        $this->assertSame('bpjs-kelas-1', $explicit->slug);

        $explicit->update(['name' => 'Kamar Kelas 1 (nama baru)']);

        $this->assertSame('bpjs-kelas-1', $explicit->fresh()->slug);
        $this->assertSame(120, strlen(RoomCategory::uniqueSlug(str_repeat('a', 400))));
    }

    public function test_description_is_sanitized_and_blank_becomes_null(): void
    {
        $room = $this->createRoomCategory(['description' => '<p>Kamar <em>nyaman</em>.</p><script>alert(1)</script><img src="x" onerror="alert(2)">']);

        $stored = (string) $room->fresh()->description;

        $this->assertStringContainsString('<p>Kamar <em>nyaman</em>.</p>', $stored);
        $this->assertStringNotContainsString('<script', $stored);
        $this->assertStringNotContainsString('onerror', $stored);
        $this->assertSame(['description'], $room->richTextAttributes());

        $room->update(['description' => '<p><br></p>']);

        $this->assertNull($room->fresh()->description);
    }

    public function test_seo_fallbacks(): void
    {
        $this->assertSame('Judul Khusus', $this->createRoomCategory(['name' => 'Kamar A', 'meta_title' => 'Judul Khusus'])->seo_title);
        $this->assertSame('Kamar B', $this->createRoomCategory(['name' => 'Kamar B', 'meta_title' => null])->seo_title);
        $this->assertSame('Meta khusus.', $this->createRoomCategory(['short_description' => 'Ringkas.', 'meta_description' => 'Meta khusus.'])->seo_description);
        $this->assertSame('Ringkas.', $this->createRoomCategory(['short_description' => 'Ringkas.', 'meta_description' => ''])->seo_description);
    }

    public function test_occupancy_label_follows_blueprint_11_section_5_5(): void
    {
        $this->assertSame('5 dari 12 tempat tidur tersedia', $this->createRoomCategory(['bed_capacity' => 12, 'beds_available' => 5])->occupancy_label);
        $this->assertSame('0 dari 16 tempat tidur tersedia', $this->createRoomCategory(['bed_capacity' => 16, 'beds_available' => 0])->occupancy_label);
        $this->assertSame('6 dari 6 tempat tidur tersedia', $this->createRoomCategory(['bed_capacity' => 6, 'beds_available' => 6])->occupancy_label);
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function updatedLabels(): array
    {
        return [
            'oktober pagi' => ['2026-10-05 08:07:00', 'Diperbarui manual oleh petugas pada 5 Okt 2026, pukul 08.07 WIB'],
            'agustus malam' => ['2026-08-17 21:05:00', 'Diperbarui manual oleh petugas pada 17 Agt 2026, pukul 21.05 WIB'],
            'desember tengah malam' => ['2026-12-01 00:00:00', 'Diperbarui manual oleh petugas pada 1 Des 2026, pukul 00.00 WIB'],
        ];
    }

    #[DataProvider('updatedLabels')]
    public function test_availability_updated_label_is_exact_and_never_says_real_time(string $at, string $expected): void
    {
        $room = $this->createRoomCategory(['availability_updated_at' => $at]);

        $this->assertSame($expected, $room->availability_updated_label);
        $this->assertStringNotContainsStringIgnoringCase('real-time', $room->availability_updated_label);
    }

    public function test_availability_updated_label_without_a_timestamp(): void
    {
        $room = $this->createRoomCategory(['availability_updated_at' => null]);

        $this->assertSame('Belum diperbarui', $room->availability_updated_label);
    }

    /** @return array<string, array{0: ?string, 1: bool}> */
    public static function staleCases(): array
    {
        return [
            'baru satu jam' => ['2026-10-05 09:00:00', false],
            'tepat 24 jam' => ['2026-10-04 10:00:00', false],
            '24 jam kurang satu menit' => ['2026-10-04 10:01:00', false],
            '24 jam lebih satu menit' => ['2026-10-04 09:59:00', true],
            'tiga hari' => ['2026-10-02 10:00:00', true],
            'belum pernah diperbarui' => [null, true],
        ];
    }

    #[DataProvider('staleCases')]
    public function test_is_availability_stale_at_the_24_hour_boundary(?string $at, bool $stale): void
    {
        $room = $this->createRoomCategory(['availability_updated_at' => $at]);

        $this->assertSame($stale, $room->is_availability_stale);
        $this->assertSame($stale, $room->fresh()->is_availability_stale);
    }

    public function test_is_full_is_true_only_when_no_bed_is_available(): void
    {
        $this->assertTrue($this->createRoomCategory(['bed_capacity' => 16, 'beds_available' => 0])->is_full);
        $this->assertFalse($this->createRoomCategory(['bed_capacity' => 16, 'beds_available' => 1])->is_full);
        $this->assertFalse($this->createRoomCategory(['bed_capacity' => 16, 'beds_available' => 16])->is_full);
        $this->assertTrue($this->createRoomCategory(['bed_capacity' => 0, 'beds_available' => 0])->is_full);
    }

    /** @return array<string, array{0: string|int|null, 1: string, 2: string}> */
    public static function rateLabels(): array
    {
        return [
            'empat ratus ribu' => [400000, 'per malam', 'Rp 400.000 per malam'],
            'satu koma dua juta' => [1200000, 'per malam', 'Rp 1.200.000 per malam'],
            'seratus lima puluh ribu' => ['150000.00', 'per malam', 'Rp 150.000 per malam'],
            'satuan lain' => [250000, 'per hari', 'Rp 250.000 per hari'],
            'tanpa tarif' => [null, 'per malam', 'Hubungi kami'],
        ];
    }

    #[DataProvider('rateLabels')]
    public function test_sample_rate_label(string|int|null $rate, string $unit, string $expected): void
    {
        $room = $this->createRoomCategory(['sample_rate' => $rate, 'rate_unit' => $unit]);

        $this->assertSame($expected, $room->sample_rate_label);
        $this->assertSame($expected, $room->fresh()->sample_rate_label);
    }

    public function test_amenities_are_cast_to_an_array(): void
    {
        $room = $this->createRoomCategory(['amenities' => ['AC', 'Televisi', 'Kamar mandi dalam']]);
        $none = $this->createRoomCategory(['amenities' => null]);

        $this->assertSame(['AC', 'Televisi', 'Kamar mandi dalam'], $room->fresh()->amenities);
        $this->assertNull($none->fresh()->amenities);
    }

    public function test_the_availability_updater_relation_and_set_null_on_user_delete(): void
    {
        $officer = User::factory()->staff()->create();
        $room = $this->createRoomCategory(['availability_updated_by' => $officer->id]);
        $this->createRoomCategory();
        $this->createRoomCategory();

        $loaded = RoomCategory::query()->with('availabilityUpdater')->orderBy('id')->get();

        $this->assertTrue($loaded[0]->availabilityUpdater->is($officer));
        $this->assertNull($loaded[1]->availabilityUpdater);

        DB::table('users')->where('id', $officer->id)->delete();

        $this->assertNull($room->fresh()->availability_updated_by);
        $this->assertNotNull($room->fresh()->availability_updated_at, 'Waktu pembaruan tetap tersimpan.');
    }

    public function test_cover_url_uses_the_room_placeholder_or_the_media_url(): void
    {
        $withCover = $this->createRoomCategory(['name' => 'Dengan Cover']);
        $this->createRoomCategory(['name' => 'Tanpa Cover']);
        $this->createRoomCategory(['name' => 'Pengisi']);

        $media = $this->createMediaRow([
            'model_type' => RoomCategory::class,
            'model_id' => $withCover->id,
            'collection' => 'cover',
            'alt_text' => 'Foto contoh Dengan Cover',
        ]);

        $loaded = RoomCategory::query()->with('media')->orderBy('id')->get();

        $this->assertSame($media->url, $loaded[0]->cover_url);
        $this->assertSame(asset('images/placeholders/room.svg'), $loaded[1]->cover_url);
    }

    public function test_listing_without_eager_loading_violates_strict_mode(): void
    {
        $this->createRoomCategory();
        $this->createRoomCategory();
        $this->createRoomCategory();

        $rooms = RoomCategory::query()->orderBy('id')->get();

        try {
            $rooms->first()->cover_url;
            $this->fail('Media harus di-eager-load pada daftar.');
        } catch (LazyLoadingViolationException) {
            $this->assertTrue(true);
        }

        $this->expectException(LazyLoadingViolationException::class);

        $rooms->first()->availabilityUpdater;
    }

    public function test_a_list_with_both_relations_eager_loaded_renders_every_accessor_without_violations(): void
    {
        $this->createRoomCategory(['name' => 'Kamar 1']);
        $this->createRoomCategory(['name' => 'Kamar 2', 'sort_order' => 1]);
        $this->createRoomCategory(['name' => 'Kamar 3', 'sort_order' => 2]);

        $rooms = RoomCategory::query()->with(['media', 'availabilityUpdater'])->ordered()->get();

        $this->assertCount(3, $rooms);

        foreach ($rooms as $room) {
            $this->assertNotSame('', $room->cover_url);
            $this->assertNotSame('', $room->occupancy_label);
            $this->assertNotSame('', $room->availability_updated_label);
            $this->assertNotSame('', $room->sample_rate_label);
            $this->assertIsBool($room->is_full);
            $this->assertIsBool($room->is_availability_stale);
            $this->assertNull($room->availabilityUpdater);
        }
    }
}
```

- [ ] **Step 5: Tulis tes yang gagal (tool Write) `tests/Feature/Facilities/ContactMessageModelTest.php`**

```php
<?php

namespace Tests\Feature\Facilities;

use App\Enums\ContactMessageStatus;
use App\Models\ContactMessage;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithFacilities;
use Tests\TestCase;

/**
 * Model ContactMessage (09 §6.8, F4B-R24, F4B-R35): cast status, `ip_hash` tersembunyi, `handled_*` tidak fillable.
 */
class ContactMessageModelTest extends TestCase
{
    use InteractsWithFacilities;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-05 10:00:00'));
    }

    public function test_fillable_hidden_and_casts_follow_the_blueprint(): void
    {
        $message = new ContactMessage;

        $this->assertSame(['name', 'email', 'phone', 'subject', 'message', 'status', 'consent_at', 'ip_hash'], $message->getFillable());
        $this->assertSame(['ip_hash'], $message->getHidden());
        $this->assertSame(ContactMessageStatus::class, $message->getCasts()['status']);
        $this->assertSame('datetime', $message->getCasts()['consent_at']);
        $this->assertSame('datetime', $message->getCasts()['handled_at']);
        $this->assertNotContains('handled_by', $message->getFillable());
        $this->assertNotContains('handled_at', $message->getFillable());
    }

    public function test_handled_columns_cannot_be_mass_assigned_but_can_be_force_filled(): void
    {
        $staff = User::factory()->staff()->create();
        $message = $this->createContactMessage();

        try {
            $message->fill(['handled_by' => $staff->id]);
            $this->fail('handled_by tidak boleh diisi lewat fill().');
        } catch (MassAssignmentException) {
            $this->assertNull($message->fresh()->handled_by);
        }

        $message->forceFill(['handled_by' => $staff->id, 'handled_at' => '2026-10-05 11:00:00'])->save();

        $fresh = $message->fresh();

        $this->assertSame($staff->id, $fresh->handled_by);
        $this->assertSame('2026-10-05 11:00:00', $fresh->handled_at->toDateTimeString());
    }

    public function test_unknown_attributes_are_rejected_in_strict_mode(): void
    {
        $this->expectException(MassAssignmentException::class);

        ContactMessage::factory()->make()->fill(['bukan_kolom' => 'x']);
    }

    public function test_status_defaults_to_new_and_is_cast_to_the_enum(): void
    {
        $created = ContactMessage::query()->create([
            'name' => 'Rudi Hartono', 'email' => 'rudi@contoh.test', 'subject' => 'Pertanyaan jam operasional',
            'message' => 'Jam operasional poliklinik hari Sabtu?', 'consent_at' => now(), 'ip_hash' => str_repeat('a', 64),
        ]);
        $message = ContactMessage::query()->findOrFail($created->id);

        $this->assertSame(ContactMessageStatus::New, $message->status);
        $this->assertNull($message->phone);
        $this->assertNull($message->handled_by);
        $this->assertNull($message->handled_at);
        $this->assertSame('2026-10-05 10:00:00', $message->consent_at->toDateTimeString());
    }

    public function test_factory_defaults_and_states(): void
    {
        $new = $this->createContactMessage();
        $read = ContactMessage::factory()->read()->create();
        $replied = ContactMessage::factory()->replied()->create();
        $archived = ContactMessage::factory()->archived()->create();

        $this->assertSame(ContactMessageStatus::New, $new->fresh()->status);
        $this->assertNull($new->fresh()->handled_by);
        $this->assertSame(ContactMessageStatus::Read, $read->fresh()->status);
        $this->assertNull($read->fresh()->handled_by);
        $this->assertSame(ContactMessageStatus::Replied, $replied->fresh()->status);
        $this->assertNotNull($replied->fresh()->handled_by);
        $this->assertNotNull($replied->fresh()->handled_at);
        $this->assertSame(ContactMessageStatus::Archived, $archived->fresh()->status);
        $this->assertNotNull($archived->fresh()->handled_by);
        $this->assertGreaterThanOrEqual(10, mb_strlen($new->message));
        $this->assertNotNull($new->consent_at);
    }

    public function test_ip_hash_is_a_sha256_digest_and_is_hidden_from_serialization(): void
    {
        $message = $this->createContactMessage();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $message->ip_hash);
        $this->assertNotSame('127.0.0.1', $message->ip_hash);
        $this->assertArrayNotHasKey('ip_hash', $message->toArray());
        $this->assertArrayNotHasKey('ip_hash', json_decode($message->toJson(), true));
        $this->assertArrayHasKey('ip_hash', $message->makeVisible('ip_hash')->toArray());
    }

    public function test_unread_scope_returns_only_new_messages(): void
    {
        $new1 = $this->createContactMessage();
        ContactMessage::factory()->read()->create();
        ContactMessage::factory()->replied()->create();
        ContactMessage::factory()->archived()->create();
        $new2 = $this->createContactMessage();

        $this->assertSame([$new1->id, $new2->id], ContactMessage::query()->unread()->orderBy('id')->pluck('id')->all());
        $this->assertSame(5, ContactMessage::query()->count());
    }

    public function test_handler_relation_needs_eager_loading_in_strict_mode(): void
    {
        $staff = User::factory()->staff()->create();
        $this->createContactMessage(['status' => ContactMessageStatus::Replied, 'handled_by' => $staff->id, 'handled_at' => '2026-10-05 11:00:00']);
        $this->createContactMessage();
        $this->createContactMessage();

        $loaded = ContactMessage::query()->with('handler')->orderBy('id')->get();

        $this->assertTrue($loaded[0]->handler->is($staff));
        $this->assertNull($loaded[1]->handler);

        $lazy = ContactMessage::query()->orderBy('id')->get();

        $this->expectException(LazyLoadingViolationException::class);

        $lazy->first()->handler;
    }

    public function test_text_is_stored_verbatim_without_sanitization(): void
    {
        $message = $this->createContactMessage([
            'name' => '<script>alert(1)</script>',
            'subject' => '<b>Subjek</b> & "kutip"',
            'message' => "Baris satu.\n<img src=x onerror=alert(1)>\nBaris tiga.",
        ]);

        $fresh = $message->fresh();

        $this->assertSame('<script>alert(1)</script>', $fresh->name);
        $this->assertSame('<b>Subjek</b> & "kutip"', $fresh->subject);
        $this->assertSame("Baris satu.\n<img src=x onerror=alert(1)>\nBaris tiga.", $fresh->message);
    }
}
```

- [ ] **Step 6: Tulis tes yang gagal (tool Write) `tests/Feature/Facilities/InteractsWithFacilitiesTest.php`**

```php
<?php

namespace Tests\Feature\Facilities;

use App\Enums\ContactMessageStatus;
use App\Enums\FacilityCategory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithFacilities;
use Tests\TestCase;

/**
 * Menjaga pembantu tes fasilitas, kamar, dan pesan kontak, karena dipakai hampir semua tes fase 4b.
 */
class InteractsWithFacilitiesTest extends TestCase
{
    use InteractsWithFacilities;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-05 10:00:00'));
    }

    public function test_create_facility_builds_an_active_general_facility_and_applies_overrides(): void
    {
        $plain = $this->createFacility();
        $custom = $this->createFacility(['name' => 'Musala', 'category' => FacilityCategory::Umum, 'is_active' => false, 'sort_order' => 4]);

        $this->assertTrue($plain->is_active);
        $this->assertSame(FacilityCategory::Umum, $plain->category);
        $this->assertNotSame('', $plain->slug);
        $this->assertSame('Musala', $custom->name);
        $this->assertSame('musala', $custom->slug);
        $this->assertFalse($custom->is_active);
        $this->assertSame(4, $custom->sort_order);
    }

    public function test_create_room_category_builds_a_fresh_active_category_and_applies_overrides(): void
    {
        $plain = $this->createRoomCategory();
        $explicit = $this->createRoomCategory(['name' => 'Kamar VIP', 'slug' => 'vip', 'bed_capacity' => 6, 'beds_available' => 2, 'availability_updated_at' => null]);

        $this->assertTrue($plain->is_active);
        $this->assertSame(5, $plain->beds_available);
        $this->assertSame(12, $plain->bed_capacity);
        $this->assertFalse($plain->is_availability_stale);
        $this->assertSame('vip', $explicit->slug);
        $this->assertSame(2, $explicit->beds_available);
        $this->assertNull($explicit->availability_updated_at);
        $this->assertTrue($explicit->is_availability_stale);
    }

    public function test_create_contact_message_builds_a_new_message_with_consent_and_ip_hash(): void
    {
        $message = $this->createContactMessage(['subject' => 'Pertanyaan jam operasional']);

        $this->assertSame(ContactMessageStatus::New, $message->fresh()->status);
        $this->assertSame('Pertanyaan jam operasional', $message->subject);
        $this->assertNotNull($message->fresh()->consent_at);
        $this->assertSame(64, strlen($message->ip_hash));
        $this->assertNull($message->fresh()->handled_by);
    }

    public function test_create_contact_message_accepts_handled_columns_through_force_fill(): void
    {
        $staff = User::factory()->staff()->create();

        $message = $this->createContactMessage([
            'status' => ContactMessageStatus::Replied,
            'handled_by' => $staff->id,
            'handled_at' => '2026-10-05 09:30:00',
        ]);

        $fresh = $message->fresh();

        $this->assertSame(ContactMessageStatus::Replied, $fresh->status);
        $this->assertSame($staff->id, $fresh->handled_by);
        $this->assertSame('2026-10-05 09:30:00', $fresh->handled_at->toDateTimeString());
    }

    public function test_create_contact_message_leaves_handled_columns_empty_when_not_given(): void
    {
        $message = $this->createContactMessage(['status' => ContactMessageStatus::Read]);

        $this->assertSame(ContactMessageStatus::Read, $message->fresh()->status);
        $this->assertNull($message->fresh()->handled_by);
        $this->assertNull($message->fresh()->handled_at);
    }
}
```

- [ ] **Step 7: Jalankan, harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test tests\Feature\Facilities
```
Expected: FAIL. Setiap kelas baru gagal dengan `Error: Class "App\Models\Facility" not found` (atau `RoomCategory`, `ContactMessage`, `Database\Factories\FacilityFactory`); `InteractsWithFacilitiesTest` gagal pada pemanggilan pembantu pertama. Tes lama di folder lain tidak terdampak.

- [ ] **Step 8: Implementasi `app/Models/Facility.php` (tool Write)**

```php
<?php

namespace App\Models;

use App\Enums\FacilityCategory;
use App\Models\Concerns\HasMedia;
use App\Models\Concerns\HasUniqueSlug;
use App\Models\Concerns\SanitizesRichText;
use Database\Factories\FacilityFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Fasilitas rumah sakit: medis, penunjang, dan umum (09 §5.2). Tidak dirujuk tabel lain, sehingga selalu dapat
 * dihapus permanen (media ikut dihapus). Slug dibuat dari `name` dan tidak berubah saat nama diedit (BR-117).
 * Daftar yang memakai `cover_url` wajib memuat `media` (mode ketat).
 */
class Facility extends Model
{
    /** @use HasFactory<FacilityFactory> */
    use HasFactory, HasMedia, HasUniqueSlug, SanitizesRichText;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'category',
        'short_description',
        'description',
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
            'category' => FacilityCategory::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where($this->qualifyColumn('is_active'), true);
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy($this->qualifyColumn('sort_order'))->orderBy($this->qualifyColumn('name'))->orderBy($this->qualifyColumn('id'));
    }

    public function scopeOfCategory(Builder $query, FacilityCategory $category): void
    {
        $query->where($this->qualifyColumn('category'), $category->value);
    }

    /**
     * Kolom rich text yang disanitasi saat disimpan (BR-122).
     *
     * @return list<string>
     */
    public function richTextAttributes(): array
    {
        return ['description'];
    }

    /**
     * URL cover (koleksi `cover`), atau placeholder `facility.svg`. Muat `media` pada daftar.
     *
     * @return Attribute<string, never>
     */
    protected function coverUrl(): Attribute
    {
        return Attribute::get(fn (): string => $this->mediaUrl('cover', 'full', 'images/placeholders/facility.svg'));
    }

    /**
     * Judul SEO: `meta_title`, atau nama bila kosong (BR-123).
     *
     * @return Attribute<string, never>
     */
    protected function seoTitle(): Attribute
    {
        return Attribute::get(fn (): string => filled($this->meta_title) ? (string) $this->meta_title : (string) $this->name);
    }

    /**
     * Deskripsi SEO: `meta_description`, atau `short_description` bila kosong (BR-123).
     *
     * @return Attribute<string, never>
     */
    protected function seoDescription(): Attribute
    {
        return Attribute::get(fn (): string => filled($this->meta_description) ? (string) $this->meta_description : (string) $this->short_description);
    }

    /** Panjang kolom `facilities.slug` (09 §5.2, F4B-R4). */
    protected function slugMaxLength(): int
    {
        return 170;
    }
}
```

- [ ] **Step 9: Implementasi `app/Models/RoomCategory.php` (tool Write)**

```php
<?php

namespace App\Models;

use App\Models\Concerns\HasMedia;
use App\Models\Concerns\HasUniqueSlug;
use App\Models\Concerns\SanitizesRichText;
use Database\Factories\RoomCategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kategori kamar rawat inap dengan model kapasitas agregat (09 §5.1, 00 E.4). Ketersediaan diperbarui manual oleh
 * staf: `availability_updated_at`/`availability_updated_by` hanya diubah aksi ketersediaan (Task 5), bukan edit
 * deskripsi, tarif, atau kapasitas (F4B-R18). Tidak dirujuk tabel lain, sehingga selalu dapat dihapus (F4B-R20).
 * Daftar yang memakai `cover_url` atau `availabilityUpdater` wajib memuat `media` dan relasinya (mode ketat).
 * Label tidak pernah memakai kata "real-time" (01, 02 F-NXT-08).
 */
class RoomCategory extends Model
{
    /** @use HasFactory<RoomCategoryFactory> */
    use HasFactory, HasMedia, HasUniqueSlug, SanitizesRichText;

    /** Data ketersediaan lebih tua dari ini (jam) dianggap mungkin tidak terkini (BR-114, F4B-R10). */
    public const STALE_AFTER_HOURS = 24;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'short_description',
        'description',
        'amenities',
        'bed_capacity',
        'beds_available',
        'sample_rate',
        'rate_unit',
        'rate_note',
        'is_active',
        'sort_order',
        'availability_updated_at',
        'availability_updated_by',
        'meta_title',
        'meta_description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amenities' => 'array',
            'bed_capacity' => 'integer',
            'beds_available' => 'integer',
            'sample_rate' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'availability_updated_at' => 'datetime',
        ];
    }

    /** Petugas yang terakhir memperbarui ketersediaan (NULL bila belum pernah atau akunnya dihapus). */
    public function availabilityUpdater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'availability_updated_by');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where($this->qualifyColumn('is_active'), true);
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy($this->qualifyColumn('sort_order'))->orderBy($this->qualifyColumn('name'))->orderBy($this->qualifyColumn('id'));
    }

    /**
     * Kolom rich text yang disanitasi saat disimpan (BR-122).
     *
     * @return list<string>
     */
    public function richTextAttributes(): array
    {
        return ['description'];
    }

    /**
     * URL cover (koleksi `cover`), atau placeholder `room.svg`. Muat `media` pada daftar.
     *
     * @return Attribute<string, never>
     */
    protected function coverUrl(): Attribute
    {
        return Attribute::get(fn (): string => $this->mediaUrl('cover', 'full', 'images/placeholders/room.svg'));
    }

    /**
     * Judul SEO: `meta_title`, atau nama bila kosong (BR-123).
     *
     * @return Attribute<string, never>
     */
    protected function seoTitle(): Attribute
    {
        return Attribute::get(fn (): string => filled($this->meta_title) ? (string) $this->meta_title : (string) $this->name);
    }

    /**
     * Deskripsi SEO: `meta_description`, atau `short_description` bila kosong (BR-123).
     *
     * @return Attribute<string, never>
     */
    protected function seoDescription(): Attribute
    {
        return Attribute::get(fn (): string => filled($this->meta_description) ? (string) $this->meta_description : (string) $this->short_description);
    }

    /**
     * "{beds_available} dari {bed_capacity} tempat tidur tersedia" (11 §5.5, F4B-R10).
     *
     * @return Attribute<string, never>
     */
    protected function occupancyLabel(): Attribute
    {
        return Attribute::get(fn (): string => $this->beds_available.' dari '.$this->bed_capacity.' tempat tidur tersedia');
    }

    /**
     * "Diperbarui manual oleh petugas pada {j M Y}, pukul {H.i} WIB", atau "Belum diperbarui" (F4B-R10, 02 F-NXT-08).
     *
     * @return Attribute<string, never>
     */
    protected function availabilityUpdatedLabel(): Attribute
    {
        return Attribute::get(function (): string {
            $at = $this->availability_updated_at;

            if ($at === null) {
                return 'Belum diperbarui';
            }

            return 'Diperbarui manual oleh petugas pada '.$at->translatedFormat('j M Y').', pukul '.$at->format('H.i').' WIB';
        });
    }

    /**
     * Benar bila belum pernah diperbarui atau pembaruan terakhir lebih tua dari `STALE_AFTER_HOURS` jam (BR-114).
     * Tepat 24 jam belum dianggap kedaluwarsa.
     *
     * @return Attribute<bool, never>
     */
    protected function isAvailabilityStale(): Attribute
    {
        return Attribute::get(function (): bool {
            $at = $this->availability_updated_at;

            return $at === null || $at->lt(now()->subHours(self::STALE_AFTER_HOURS));
        });
    }

    /**
     * Penuh bila tidak ada tempat tidur tersedia (badge "Penuh", F4B-R9).
     *
     * @return Attribute<bool, never>
     */
    protected function isFull(): Attribute
    {
        return Attribute::get(fn (): bool => (int) $this->beds_available === 0);
    }

    /**
     * "Rp 400.000 per malam", atau "Hubungi kami" bila tarif contoh kosong (F4B-R12). Tanpa Number::currency agar
     * tidak ada spasi tak putus.
     *
     * @return Attribute<string, never>
     */
    protected function sampleRateLabel(): Attribute
    {
        return Attribute::get(function (): string {
            if ($this->sample_rate === null) {
                return 'Hubungi kami';
            }

            return 'Rp '.number_format((float) $this->sample_rate, 0, ',', '.').' '.$this->rate_unit;
        });
    }

    /** Panjang kolom `room_categories.slug` (09 §5.1, F4B-R4). */
    protected function slugMaxLength(): int
    {
        return 120;
    }
}
```

- [ ] **Step 10: Implementasi `app/Models/ContactMessage.php` (tool Write)**

```php
<?php

namespace App\Models;

use App\Enums\ContactMessageStatus;
use Database\Factories\ContactMessageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pesan dari formulir kontak publik (09 §6.8). Teks biasa yang disimpan apa adanya dan selalu dirender dengan
 * `{{ }}`. IP mentah tidak disimpan: `ip_hash` (tersembunyi dari serialisasi) berisi SHA-256 dari IP dan APP_KEY.
 * `handled_by` dan `handled_at` tidak fillable; diisi lewat forceFill() saat status berubah ke `replied`/`archived`.
 * Tidak diobservasi AuditObserver (F4B-R25) dan tidak ada route hapus (00 C).
 */
class ContactMessage extends Model
{
    /** @use HasFactory<ContactMessageFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'subject',
        'message',
        'status',
        'consent_at',
        'ip_hash',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'ip_hash',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'status' => ContactMessageStatus::class,
            'consent_at' => 'datetime',
            'handled_at' => 'datetime',
        ];
    }

    /** Staf yang membalas atau mengarsipkan pesan (NULL bila belum ditangani atau akunnya dihapus). */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function scopeUnread(Builder $query): void
    {
        $query->where($this->qualifyColumn('status'), ContactMessageStatus::New->value);
    }
}
```

- [ ] **Step 11: Implementasi factory (tool Write)**

`database/factories/FacilityFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\FacilityCategory;
use App\Models\Facility;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Facility>
 */
class FacilityFactory extends Factory
{
    /**
     * Fasilitas aktif kategori `umum` tanpa deskripsi dan cover; slug dibuat otomatis dari nama.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Fasilitas '.Str::title(fake()->unique()->words(2, true)),
            'category' => FacilityCategory::Umum,
            'short_description' => fake()->sentence(),
            'description' => null,
            'is_active' => true,
            'sort_order' => 0,
            'meta_title' => null,
            'meta_description' => null,
        ];
    }

    public function category(FacilityCategory $category): static
    {
        return $this->state(fn (array $attributes): array => [
            'category' => $category,
        ]);
    }

    /** Fasilitas nonaktif: halaman publiknya 404. */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
```

`database/factories/RoomCategoryFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\RoomCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RoomCategory>
 */
class RoomCategoryFactory extends Factory
{
    /**
     * Kategori kamar aktif: kapasitas 12, tersedia 5, tarif contoh 400000 per malam, diperbarui satu jam yang lalu.
     * Slug dibuat otomatis dari nama; `availability_updated_by` kosong.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Kamar '.Str::title(fake()->unique()->words(2, true)),
            'short_description' => fake()->sentence(),
            'description' => null,
            'amenities' => ['AC', 'Kamar mandi dalam'],
            'bed_capacity' => 12,
            'beds_available' => 5,
            'sample_rate' => 400000,
            'rate_unit' => 'per malam',
            'rate_note' => 'Tarif contoh untuk prototipe; bukan tarif resmi.',
            'is_active' => true,
            'sort_order' => 0,
            'availability_updated_at' => now()->subHour(),
            'availability_updated_by' => null,
            'meta_title' => null,
            'meta_description' => null,
        ];
    }

    /** Penuh: tidak ada tempat tidur tersedia (badge "Penuh"). */
    public function full(): static
    {
        return $this->state(fn (array $attributes): array => [
            'beds_available' => 0,
        ]);
    }

    /** Kategori nonaktif: halaman publiknya 404. */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    /** Tanpa tarif contoh: label "Hubungi kami". */
    public function withoutRate(): static
    {
        return $this->state(fn (array $attributes): array => [
            'sample_rate' => null,
            'rate_note' => null,
        ]);
    }
}
```

`database/factories/ContactMessageFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\ContactMessageStatus;
use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactMessage>
 */
class ContactMessageFactory extends Factory
{
    /**
     * Pesan baru (`new`) tanpa telepon, dengan persetujuan sekarang dan `ip_hash` SHA-256 dari 127.0.0.1 + APP_KEY.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => null,
            'subject' => rtrim(fake()->sentence(4), '.'),
            'message' => fake()->paragraph(2),
            'status' => ContactMessageStatus::New,
            'consent_at' => now(),
            'ip_hash' => hash('sha256', '127.0.0.1'.config('app.key')),
        ];
    }

    /** Sudah dibuka staf tetapi belum ditangani (`handled_*` kosong). */
    public function read(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ContactMessageStatus::Read,
        ]);
    }

    /** Sudah dibalas manual oleh seorang staf. */
    public function replied(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ContactMessageStatus::Replied,
            'handled_by' => User::factory()->staff(),
            'handled_at' => now(),
        ]);
    }

    /** Diarsipkan oleh seorang staf. */
    public function archived(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ContactMessageStatus::Archived,
            'handled_by' => User::factory()->staff(),
            'handled_at' => now(),
        ]);
    }
}
```

- [ ] **Step 12: Jalankan tes tugas, harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test tests\Feature\Facilities
& $PHP artisan test --filter="FacilitySchemaTest|EnumCatalogTest|ClinicModelTest|HasUniqueSlugTest|SanitizesRichTextTest"
```
Expected: seluruh tes PASS (±75 tes baru di `tests\Feature\Facilities`). Bila `test_listing_without_eager_loading_…` tidak melempar, pastikan tes memuat ≥ 3 baris dan `AppServiceProvider::boot()` tetap memanggil `Model::shouldBeStrict`. Bila label tanggal menghasilkan "Oct" (bahasa Inggris), periksa `APP_LOCALE=id` pada `.env.testing`; jangan mengubah kode model.

- [ ] **Step 13: Pint**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint
& $PHP vendor\bin\pint --test
```
Expected: `--test` PASS.

- [ ] **Step 14: Suite penuh (latar belakang)**

Jalankan sebagai tugas latar belakang (tool PowerShell dengan `run_in_background`), tunggu sampai selesai, lalu baca ringkasannya.

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
$LOG = Join-Path $env:TEMP "suite-fase4b-t02.txt"
& $PHP artisan test | Out-File -FilePath $LOG -Encoding utf8
Select-String -Path $LOG -Pattern 'Tests:|FAIL|Duration' | Select-Object -Last 20
```
Expected: seluruh tes PASS (±4495 tes: hasil Task 1 + ±75 tes Task 2). Tidak ada tes lama yang berubah.

- [ ] **Step 15: Commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git add app/Models/Facility.php app/Models/RoomCategory.php app/Models/ContactMessage.php database/factories/FacilityFactory.php database/factories/RoomCategoryFactory.php database/factories/ContactMessageFactory.php tests/Support/InteractsWithFacilities.php tests/Feature/Facilities/FacilityModelTest.php tests/Feature/Facilities/RoomCategoryModelTest.php tests/Feature/Facilities/ContactMessageModelTest.php tests/Feature/Facilities/InteractsWithFacilitiesTest.php
git commit -m @'
feat(fase-4b): model, factory, dan pembantu tes fasilitas, kamar, dan pesan kontak

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
'@
```

---

### Task 3: Integrasi lintas modul (audit, `MediaPolicy`, gambar editor, `LikePattern`, `x-form.file`, tab modul)

**Files:**
- Create: `app/Support/LikePattern.php`
- Modify: `app/Http/Controllers/Admin/ArticleCategoryController.php` (pakai `LikePattern`; hapus method privat `like()`)
- Modify: `app/Http/Requests/Admin/ArticleIndexRequest.php`, `BannerIndexRequest.php`, `FaqIndexRequest.php`, `GalleryIndexRequest.php`, `MasterDataIndexRequest.php`, `ScheduleExceptionIndexRequest.php` (pakai `LikePattern`)
- Modify: `app/Models/Article.php` (`scopeSearch`) dan `app/Models/Doctor.php` (`scopeSearch`, sebelumnya `addcslashes`) (pakai `LikePattern`)
- Modify: `app/Providers/AppServiceProvider.php` (observer untuk `Facility` dan `RoomCategory`; impor)
- Modify: `app/Observers/AuditObserver.php` (hanya docblock)
- Modify: `lang/id/messages.php` (`entities`: `facility`, `room_category`, `contact_message`)
- Modify: `app/Policies/MediaPolicy.php` (`Facility::class, RoomCategory::class => facilities.manage`; impor dan docblock)
- Modify: `app/Services/Media/MediaService.php` (`EDITOR_REFERENCE_COLUMNS`)
- Modify: `resources/views/components/form/file.blade.php` (prop `placeholder`)
- Modify: `config/navigation.php` (`modules.facilities`)
- Modify (tes): `tests/Feature/Auth/MediaPolicyTest.php`, `tests/Feature/Media/ContentEditorImagesTest.php` (F4B-R37)
- Test: `tests/Unit/Support/LikePatternTest.php`, `tests/Feature/Support/LikePatternDatabaseTest.php`, `tests/Feature/Facilities/FacilityAuditTest.php`, `tests/Feature/Media/FacilityEditorImagesTest.php`, `tests/Feature/View/FormFilePlaceholderTest.php`, `tests/Feature/Support/FacilitiesModuleTabsTest.php`
- Tidak diubah: `AuditLogger` (aksi `contact_message.*` sudah termasuk redaksi PII), `AuditLog` (prefiks) dan `MediaService` bagian `needle` (keduanya memakai `addcslashes` untuk keperluan lain, bukan pencarian "mengandung"); `HasUniqueSlug` (sudah sadar soft delete sejak 4a); `HasMedia`; enum; `routes/*` (Task 4 dan seterusnya); `RolePermissionSeeder` (tidak ada izin baru, tetap 19).

**Interfaces:**
- Consumes: model, factory, dan `InteractsWithFacilities` Task 2; `InteractsWithStaff`, `InteractsWithMedia`, `InteractsWithContent`, `InteractsWithMasterData`; `MediaService`, `ImageFixtures`; `AuditLog`; `NavigationMenu`; `RolePermissionSeeder::PERMISSIONS`.
- Produces (persis kerangka C.3):
  - `App\Support\LikePattern::contains(string $term): string` (`final`, `declare(strict_types=1)`) = `'%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%'`. Sembilan tempat memakainya menggantikan salinan lama (F4B-R26); kode 4b memakainya.
  - `AuditObserver` didaftarkan untuk `Facility` dan `RoomCategory`: aksi `facility.created|updated|deleted` dan `room_category.created|updated|deleted`, deskripsi mis. `Fasilitas "Laboratorium" ditambahkan.`. `ContactMessage` **tidak** diobservasi (F4B-R25).
  - Kunci `messages.entities`: `facility` "Fasilitas", `room_category` "Kategori kamar", `contact_message` "Pesan kontak".
  - `MediaPolicy::manages()`: `Facility::class, RoomCategory::class => facilities.manage` (Admin Konten dan Super Admin boleh; Petugas Pendaftaran, yang hanya memegang `rooms.update-availability`, ditolak).
  - `MediaService::EDITOR_REFERENCE_COLUMNS` = `['clinics' => ['description'], 'pages' => ['body'], 'articles' => ['body'], 'facilities' => ['description'], 'room_categories' => ['description']]` (urutan ini).
  - `x-form.file`: prop `placeholder` (bawaan `null`). Bila diisi, nilainya (mis. `facility`, `room`) menimpa peta koleksi → tipe placeholder pada pratinjau "Gambar saat ini".
  - `config('navigation.modules.facilities')`: tab "Kamar dan Kapasitas" (`admin.room-categories.index`, izin `facilities.manage|rooms.update-availability`, aktif `admin.room-categories.*`) dan "Fasilitas" (`admin.facilities.index`, izin `facilities.manage`, aktif `admin.facilities.*`).

**Test IDs:** F4B-R25 (audit), F4B-R26 (`LikePattern`), F4B-R27 (`x-form.file`), F4B-R28 (tab modul), F4B-R37 (tes lama diperbarui), D-42 (`MediaPolicy` per pemilik), D-55 (`EDITOR_REFERENCE_COLUMNS`), D-47 (kunci flash CRUD), SEK-08 (gambar editor `facilities.description` dan `room_categories.description` terikat lalu tidak di-prune), MED-07 (`media:prune` melewati gambar yang dirujuk), CRD-13 (hapus fasilitas dan kamar menghapus media), Review Focus F.1 (Petugas Pendaftaran tidak dapat mengubah gambar kamar), Review Focus F.2 (pembuatan `ContactMessage` oleh tamu tidak menghasilkan audit sehingga nama pengirim tidak masuk deskripsi audit), VAL-07 (pencarian daftar admin memperlakukan `%`, `_`, `\` sebagai teks).

**Catatan teknis (diverifikasi dari source):**
- **Sembilan, bukan delapan, tempat** menyalin escape LIKE (`rg "'%'"` pada `app/`): `ArticleCategoryController.php:84` (method privat `like()`, dipanggil di `:28`), `ArticleIndexRequest.php:72`, `BannerIndexRequest.php:43`, `FaqIndexRequest.php:66`, `GalleryIndexRequest.php:44`, `MasterDataIndexRequest.php:45`, `ScheduleExceptionIndexRequest.php:70`, `Article.php:92` (`scopeSearch`), dan `Doctor.php:169` (`addcslashes($term, '%_\\')`, hasilnya identik dengan `str_replace`). `AuditLog.php:80` (prefiks aksi, tanpa `%` di awal) dan `MediaService.php:354` (needle `%/storage/editor/%…`) bukan salinan dan tidak disentuh. Tes pemindai di `LikePatternTest` memastikan tidak ada salinan ke sepuluh.
- **Perilaku tidak berubah.** Seluruh tempat memakai transformasi yang sama (garis miring terbalik diganda dulu, lalu `%` dan `_` diberi garis miring terbalik, dibungkus `%…%`). Tes lama yang menjaga perilaku ini tetap harus hijau; kelasnya disebut di Step 12.
- **`AuditObserver` tidak perlu diubah.** `write()` memakai `Str::snake(class_basename($model))`, jadi aksi menjadi `facility.*` dan `room_category.*` otomatis; `nameOf()` membaca `name` dari `getAttributes()` (sehingga aman pada model hasil query di mode ketat). Hanya docblock yang diperbarui. `description` (dan `body`) masuk `AuditLogger::NAME_ONLY`: hanya dicatat `[konten diubah]`.
- **`ContactMessage` sengaja tidak diobservasi.** Bila diobservasi, `nameOf()` akan mengambil `name` pengirim ke deskripsi audit (`Pesan kontak "Rudi Hartono" ditambahkan.`), padahal redaksi PII `AuditLogger` hanya menyamarkan nilai di `old_values`/`new_values`, bukan deskripsi. Perubahan status oleh staf dicatat eksplisit oleh controller (Task 6) dengan aksi `contact_message.status_changed` tanpa nama, email, subjek, atau isi.
- **`MediaPolicy`** (`app/Policies/MediaPolicy.php:55-75`): `match ($media->model_type)` menolak tipe lain; pemetaan baru hanya menambah satu arm. `canUseEditor()` sudah mencakup `facilities.manage`. Route `admin.media.*` (`routes/admin.php:169-180`) sudah memuat `facilities.manage` dan **tidak** memuat `rooms.update-availability`, sehingga Petugas Pendaftaran ditolak di middleware dan di Policy.
- **`EDITOR_REFERENCE_COLUMNS`** (`app/Services/Media/MediaService.php:64-68`) dipakai `isReferencedInRichText()` yang membaca tabel mentah lewat `DB::table`, sehingga tabel `facilities` dan `room_categories` harus sudah ada (Task 1).
- **`x-form.file`** (`resources/views/components/form/file.blade.php`): peta placeholder koleksi → tipe ada di baris 25 (`cover => clinic`), dipakai di baris 32 hanya bila `currentMedia` ada. Prop yang dideklarasikan di `@props` tidak ikut `$attributes`, sehingga `placeholder="facility"` tidak bocor menjadi atribut HTML pada `<input>`. Tipe tidak dikenal `x-image` melempar `InvalidArgumentException`; karena itu pemanggil hanya boleh memberi `facility` atau `room` (nilai di `Image::TYPES`).
- **Tab modul** (`config/navigation.php:109-`): 4a mengunci tepat 7 tab konten, jangan ditambah. Grup baru `facilities` berdiri sendiri. Tab ke route yang belum ada otomatis tersembunyi (`NavigationMenu::resolveLink`); karena Task 4 dan 5 baru mendaftarkan route, tes di sini mendaftarkan route palsu **hanya bila belum ada** (`Route::has`), sehingga tetap hijau sebelum dan sesudah Task 4 dan 5. Tab "Kamar dan Kapasitas" memakai izin `facilities.manage|rooms.update-availability` (satu string; `NavigationMenu::allows()` memakai `canAny`).
- **Tes lama yang patah (F4B-R37):** `MediaPolicyTest.php:56` memakai `'App\Models\Facility'` sebagai contoh tipe "belum dipetakan" (`$superOnly`) dan akan gagal; `ContentEditorImagesTest.php:66` (`test_the_reference_columns_include_pages_and_articles`) menegaskan tiga entri konstanta dengan `assertSame`. Keduanya diperbaiki dengan Edit persis di Step 5.
- **Audit dan factory.** `Model::observe()` di `AppServiceProvider::boot()` berlaku untuk semua tes, jadi setiap tes 4b yang membuat `Facility` atau `RoomCategory` ikut menulis baris `audit_logs` (aktor `system` bila tidak ada staf login). Tes yang menghitung seluruh `audit_logs` harus memakai `AuditLog::query()->delete()` atau menyaring `action`.
- **Pola pengikatan gambar editor** (`SanitizesRichText::saved` → `MediaService::syncEditorImages`) sudah dipakai `Clinic`; `facilities` dan `room_categories` ikut setelah konstanta diperbarui. Gambar yang dirujuk `<img src="/storage/editor/…/{uuid}.jpg">` diikat ke pemilik pertama (tidak berpindah pemilik).
- Jumlah tes ±55.

- [ ] **Step 1: Verifikasi prasyarat (Task 1 dan 2 selesai)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git branch --show-current
git status --short
Test-Path app\Models\RoomCategory.php
Test-Path app\Support\LikePattern.php
Test-Path tests\Support\InteractsWithFacilities.php
& $PHP artisan test tests\Feature\Facilities
```
Expected: `fase-4b-fasilitas-kamar-kontak`; `git status` bersih (atau hanya `?? .superpowers/`); `True`; `False`; `True`; seluruh tes di `tests\Feature\Facilities` PASS.

- [ ] **Step 2: Tulis tes yang gagal (tool Write) `tests/Unit/Support/LikePatternTest.php`**

```php
<?php

namespace Tests\Unit\Support;

use App\Support\LikePattern;
use FilesystemIterator;
use PHPUnit\Framework\Attributes\DataProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * F4B-R26: satu pembantu untuk pola LIKE "mengandung". Karakter `\`, `%`, dan `_` diperlakukan sebagai teks biasa.
 */
class LikePatternTest extends TestCase
{
    /** @return array<string, array{0: string, 1: string}> */
    public static function terms(): array
    {
        return [
            'kata biasa' => ['anak', '%anak%'],
            'persen di-escape' => ['50%', '%50\\%%'],
            'garis bawah di-escape' => ['a_b', '%a\\_b%'],
            'garis miring terbalik digandakan' => ['a\\b', '%a\\\\b%'],
            'gabungan ketiganya' => ['100%_\\', '%100\\%\\_\\\\%'],
            'hanya persen' => ['%', '%\\%%'],
            'hanya garis bawah' => ['_', '%\\_%'],
            'kosong' => ['', '%%'],
            'spasi tepi dipertahankan' => [' anak ', '% anak %'],
            'multibyte' => ['Jantung Çinta 心', '%Jantung Çinta 心%'],
            'tanda kutip tidak diubah' => ["O'Brien \"x\"", "%O'Brien \"x\"%"],
        ];
    }

    #[DataProvider('terms')]
    public function test_contains_wraps_and_escapes_the_term(string $term, string $expected): void
    {
        $this->assertSame($expected, LikePattern::contains($term));
    }

    public function test_the_class_is_final_and_has_a_single_public_static_method(): void
    {
        $class = new \ReflectionClass(LikePattern::class);

        $this->assertTrue($class->isFinal());
        $this->assertSame(['contains'], array_map(fn (\ReflectionMethod $m): string => $m->getName(), $class->getMethods(\ReflectionMethod::IS_PUBLIC)));
        $this->assertTrue($class->getMethod('contains')->isStatic());
    }

    public function test_no_other_copy_of_the_like_escape_remains_in_the_application_code(): void
    {
        $offenders = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path('app'), FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php' || $file->getFilename() === 'LikePattern.php') {
                continue;
            }

            if (preg_match('/\'%\'\s*\.\s*(?:str_replace|addcslashes)\s*\(/', (string) file_get_contents($file->getPathname())) === 1) {
                $offenders[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
        }

        $this->assertSame([], $offenders, 'Pakai App\\Support\\LikePattern::contains(), jangan menyalin escape LIKE.');
    }
}
```

- [ ] **Step 3: Tulis tes yang gagal (tool Write) `tests/Feature/Support/LikePatternDatabaseTest.php`**

```php
<?php

namespace Tests\Feature\Support;

use App\Models\Article;
use App\Models\Doctor;
use App\Models\Facility;
use App\Support\LikePattern;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithContent;
use Tests\Support\InteractsWithFacilities;
use Tests\Support\InteractsWithMasterData;
use Tests\TestCase;

/**
 * F4B-R26 pada MariaDB sungguhan: pola `LikePattern::contains()` cocok secara harfiah, dan pemakai lamanya
 * (Article::search, Doctor::search) tetap berperilaku sama setelah refaktor.
 */
class LikePatternDatabaseTest extends TestCase
{
    use InteractsWithContent;
    use InteractsWithFacilities;
    use InteractsWithMasterData;
    use RefreshDatabase;

    /** @return list<string> */
    private function facilityNames(string $term): array
    {
        return Facility::query()
            ->where('name', 'like', LikePattern::contains($term))
            ->orderBy('id')
            ->pluck('name')
            ->all();
    }

    public function test_percent_underscore_and_backslash_match_literally(): void
    {
        foreach (['Gizi 50% Diskon', 'Gizi 50 Diskon', 'Gizi_x', 'Gizi-x', 'A\\B', 'AxB'] as $name) {
            $this->createFacility(['name' => $name]);
        }

        $this->assertSame(['Gizi 50% Diskon'], $this->facilityNames('50%'));
        $this->assertSame(['Gizi_x'], $this->facilityNames('i_x'));
        $this->assertSame(['A\\B'], $this->facilityNames('A\\B'));
        $this->assertSame(['Gizi 50% Diskon', 'Gizi 50 Diskon'], $this->facilityNames('50'));
        $this->assertSame([], $this->facilityNames('tidak ada'));
    }

    public function test_an_unescaped_pattern_would_have_matched_more_rows(): void
    {
        $this->createFacility(['name' => 'Gizi 50% Diskon']);
        $this->createFacility(['name' => 'Gizi 50 Diskon']);
        $this->createFacility(['name' => 'Gizi 500']);

        $unescaped = Facility::query()->where('name', 'like', '%50%%')->count();

        $this->assertSame(3, $unescaped, 'Tanpa escape, persen menjadi wildcard.');
        $this->assertCount(1, $this->facilityNames('50%'));
    }

    public function test_an_empty_term_matches_every_row(): void
    {
        $this->createFacility();
        $this->createFacility();

        $this->assertCount(2, Facility::query()->where('name', 'like', LikePattern::contains(''))->get());
    }

    public function test_article_search_still_treats_special_characters_as_text(): void
    {
        $match = $this->createArticle(['title' => 'Diskon 50% pemeriksaan']);
        $this->createArticle(['title' => 'Diskon 50 pemeriksaan']);
        $this->createArticle(['title' => 'Diskon 500 pemeriksaan']);
        $under = $this->createArticle(['title' => 'Cek_rutin tahunan']);
        $this->createArticle(['title' => 'Cek-rutin tahunan']);

        $this->assertSame([$match->id], Article::query()->search('50%')->pluck('id')->all());
        $this->assertSame([$under->id], Article::query()->search('k_r')->pluck('id')->all());
        $this->assertSame(5, Article::query()->search('   ')->count(), 'Kata kunci kosong tanpa efek.');
    }

    public function test_doctor_search_still_treats_special_characters_as_text(): void
    {
        $underscore = $this->createDoctor(['name' => 'dr. Ana_Budi']);
        $this->createDoctor(['name' => 'dr. AnaXBudi']);
        $percent = $this->createDoctor(['name' => 'dr. Cahya 100%']);
        $this->createDoctor(['name' => 'dr. Cahya 1000']);

        $this->assertSame([$underscore->id], Doctor::query()->search('a_B')->pluck('doctors.id')->all());
        $this->assertSame([$percent->id], Doctor::query()->search('100%')->pluck('doctors.id')->all());
    }
}
```

- [ ] **Step 4: Tulis tes yang gagal (tool Write) `tests/Feature/Facilities/FacilityAuditTest.php`**

```php
<?php

namespace Tests\Feature\Facilities;

use App\Enums\ActorType;
use App\Enums\ContactMessageStatus;
use App\Enums\FacilityCategory;
use App\Models\AuditLog;
use App\Models\Facility;
use App\Models\RoomCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithFacilities;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * F4B-R25 / 12 §8.3: Facility dan RoomCategory diobservasi AuditObserver dengan deskripsi berbahasa Indonesia;
 * ContactMessage tidak diobservasi agar nama pengirim tidak masuk deskripsi audit.
 */
class FacilityAuditTest extends TestCase
{
    use InteractsWithFacilities;
    use InteractsWithStaff;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->createStaff('content-admin');
        $this->actingAs($this->admin);
    }

    private function log(string $action): AuditLog
    {
        return AuditLog::query()->where('action', $action)->latest('id')->firstOrFail();
    }

    public function test_entity_labels_exist_for_the_three_new_models_and_compose_with_the_flash_templates(): void
    {
        $this->assertSame('Fasilitas', __('messages.entities.facility'));
        $this->assertSame('Kategori kamar', __('messages.entities.room_category'));
        $this->assertSame('Pesan kontak', __('messages.entities.contact_message'));
        $this->assertSame('Fasilitas berhasil ditambahkan.', __('messages.created', ['entity' => __('messages.entities.facility')]));
        $this->assertSame('Kategori kamar berhasil diperbarui.', __('messages.updated', ['entity' => __('messages.entities.room_category')]));
        $this->assertSame('Fasilitas berhasil dihapus.', __('messages.deleted', ['entity' => __('messages.entities.facility')]));
        $this->assertSame('Kategori kamar berhasil dihapus.', __('messages.deleted', ['entity' => __('messages.entities.room_category')]));
    }

    public function test_creating_each_audited_model_writes_its_own_action_and_the_contact_message_writes_none(): void
    {
        $this->createFacility();
        $this->createRoomCategory();
        $this->createContactMessage();

        $actions = AuditLog::query()->pluck('action')->all();
        sort($actions);

        $this->assertSame(['facility.created', 'room_category.created'], $actions);
    }

    public function test_facility_lifecycle(): void
    {
        $facility = $this->createFacility(['name' => 'Laboratorium', 'category' => FacilityCategory::Penunjang]);

        $created = $this->log('facility.created');
        $this->assertSame('Fasilitas "Laboratorium" ditambahkan.', $created->description);
        $this->assertSame(ActorType::Staff, $created->actor_type);
        $this->assertSame($this->admin->id, (int) $created->user_id);
        $this->assertSame(Facility::class, $created->auditable_type);
        $this->assertSame($facility->id, (int) $created->auditable_id);
        $this->assertNull($created->old_values);
        $this->assertSame('Laboratorium', $created->new_values['name']);
        $this->assertSame('penunjang', $created->new_values['category']);

        AuditLog::query()->delete();
        $facility->update(['is_active' => false]);

        $updated = $this->log('facility.updated');
        $this->assertSame('Fasilitas "Laboratorium" diperbarui.', $updated->description);
        $this->assertEquals(['is_active' => 1], $updated->old_values);
        $this->assertEquals(['is_active' => 0], $updated->new_values);

        AuditLog::query()->delete();
        $facility->delete();

        $deleted = $this->log('facility.deleted');
        $this->assertSame('Fasilitas "Laboratorium" dihapus.', $deleted->description);
        $this->assertSame('Laboratorium', $deleted->old_values['name']);
        $this->assertNull($deleted->new_values);
        $this->assertSame(1, AuditLog::query()->count());
    }

    public function test_changing_the_facility_category_records_the_raw_values(): void
    {
        $facility = $this->createFacility(['name' => 'Musala', 'category' => FacilityCategory::Umum]);
        AuditLog::query()->delete();

        $facility->update(['category' => FacilityCategory::Medis]);

        $log = $this->log('facility.updated');

        $this->assertSame(['category' => 'umum'], $log->old_values);
        $this->assertSame(['category' => 'medis'], $log->new_values);
    }

    public function test_the_description_is_logged_as_content_changed_only(): void
    {
        $facility = $this->createFacility(['description' => '<p>Isi lama yang cukup panjang.</p>']);
        AuditLog::query()->delete();

        $facility->update(['description' => '<p>Isi baru yang rahasia.</p>']);

        $log = $this->log('facility.updated');

        $this->assertSame(['description' => '[konten diubah]'], $log->new_values);
        $this->assertStringNotContainsString('rahasia', (string) json_encode($log->new_values));
    }

    public function test_room_category_lifecycle(): void
    {
        $room = $this->createRoomCategory(['name' => 'Kamar VIP', 'bed_capacity' => 6, 'beds_available' => 2]);

        $created = $this->log('room_category.created');
        $this->assertSame('Kategori kamar "Kamar VIP" ditambahkan.', $created->description);
        $this->assertSame(ActorType::Staff, $created->actor_type);
        $this->assertSame(RoomCategory::class, $created->auditable_type);
        $this->assertSame($room->id, (int) $created->auditable_id);
        $this->assertSame('Kamar VIP', $created->new_values['name']);
        $this->assertEquals(6, $created->new_values['bed_capacity']);

        AuditLog::query()->delete();
        $room->update(['beds_available' => 1]);

        $updated = $this->log('room_category.updated');
        $this->assertSame('Kategori kamar "Kamar VIP" diperbarui.', $updated->description);
        $this->assertEquals(['beds_available' => 2], $updated->old_values);
        $this->assertEquals(['beds_available' => 1], $updated->new_values);

        AuditLog::query()->delete();
        $room->delete();

        $deleted = $this->log('room_category.deleted');
        $this->assertSame('Kategori kamar "Kamar VIP" dihapus.', $deleted->description);
        $this->assertSame('Kamar VIP', $deleted->old_values['name']);
        $this->assertSame(1, AuditLog::query()->count());
    }

    public function test_a_model_loaded_from_the_database_is_audited_without_a_missing_attribute_error(): void
    {
        $this->createFacility(['name' => 'Dimuat Ulang']);
        $this->createRoomCategory(['name' => 'Kamar Dimuat Ulang']);
        AuditLog::query()->delete();

        Facility::query()->firstOrFail()->update(['short_description' => 'Baru.']);
        RoomCategory::query()->firstOrFail()->update(['short_description' => 'Baru.']);

        $this->assertSame('Fasilitas "Dimuat Ulang" diperbarui.', $this->log('facility.updated')->description);
        $this->assertSame('Kategori kamar "Kamar Dimuat Ulang" diperbarui.', $this->log('room_category.updated')->description);
    }

    public function test_a_save_without_changes_writes_nothing(): void
    {
        $facility = $this->createFacility();
        $room = $this->createRoomCategory();
        AuditLog::query()->delete();

        $facility->save();
        $room->save();
        $room->update(['name' => $room->name]);

        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_without_a_logged_in_staff_the_actor_is_the_system(): void
    {
        auth()->forgetGuards();

        $this->createFacility(['name' => 'Tanpa Login']);

        $log = $this->log('facility.created');

        $this->assertSame(ActorType::System, $log->actor_type);
        $this->assertNull($log->user_id);
        $this->assertSame('Fasilitas "Tanpa Login" ditambahkan.', $log->description);
    }

    public function test_contact_messages_are_never_audited_by_the_observer_even_when_changed_by_staff(): void
    {
        $officer = $this->createStaff('registration-officer');
        $this->actingAs($officer);
        AuditLog::query()->delete();

        $message = $this->createContactMessage(['name' => 'Rudi Hartono', 'subject' => 'Rahasia', 'email' => 'rudi@contoh.test']);
        $message->update(['status' => ContactMessageStatus::Read]);
        $message->forceFill(['status' => ContactMessageStatus::Replied, 'handled_by' => $officer->id, 'handled_at' => now()])->save();
        $message->delete();

        $this->assertSame(0, AuditLog::query()->where('action', 'like', 'contact_message%')->count());
        $this->assertSame(0, AuditLog::query()->count());
    }
}
```

- [ ] **Step 5: Tulis tes yang gagal (tool Write) `tests/Feature/Media/FacilityEditorImagesTest.php`**

```php
<?php

namespace Tests\Feature\Media;

use App\Models\Facility;
use App\Models\Media;
use App\Models\RoomCategory;
use App\Services\Media\MediaService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ImageFixtures;
use Tests\Support\InteractsWithFacilities;
use Tests\TestCase;

/**
 * D-55 / SEK-08 / MED-07: gambar editor `facilities.description` dan `room_categories.description` terikat ke
 * pemilik saat disimpan, dilepas bila tidak dirujuk lagi, dan `media:prune` tidak menghapus yang masih dipakai.
 */
class FacilityEditorImagesTest extends TestCase
{
    use InteractsWithFacilities;
    use RefreshDatabase;

    private MediaService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed(SettingsSeeder::class);

        $this->service = app(MediaService::class);
    }

    private function editorImage(): Media
    {
        return $this->service->store(ImageFixtures::jpeg(400, 300), 'editor', null, ['alt_text' => 'Gambar editor']);
    }

    private function html(Media ...$images): string
    {
        $imgs = array_map(fn (Media $media): string => '<img src="/storage/'.$media->directory.'/'.$media->file_name.'" alt="x">', $images);

        return '<p>Deskripsi yang cukup panjang '.implode(' ', $imgs).'</p>';
    }

    private function age(Media $media, int $hours): void
    {
        $at = now()->subHours($hours);
        DB::table('media')->where('id', $media->id)->update(['created_at' => $at, 'updated_at' => $at]);
    }

    private function assertFilesExist(Media $media): void
    {
        Storage::disk('public')->assertExists($media->directory.'/'.$media->file_name);
    }

    private function assertFilesMissing(Media $media): void
    {
        Storage::disk('public')->assertMissing($media->directory.'/'.$media->file_name);
    }

    public function test_the_reference_columns_include_facilities_and_room_categories(): void
    {
        $this->assertSame(['description'], MediaService::EDITOR_REFERENCE_COLUMNS['facilities']);
        $this->assertSame(['description'], MediaService::EDITOR_REFERENCE_COLUMNS['room_categories']);
    }

    public function test_saving_a_facility_binds_the_referenced_editor_image_to_it(): void
    {
        $image = $this->editorImage();
        $other = $this->editorImage();

        $facility = $this->createFacility(['description' => $this->html($image)]);

        $image->refresh();
        $this->assertSame(Facility::class, $image->model_type);
        $this->assertSame($facility->id, $image->model_id);
        $this->assertNull($other->fresh()->model_id);
        $this->assertCount(1, Facility::query()->with('media')->findOrFail($facility->id)->mediaIn('editor'));
    }

    public function test_saving_a_room_category_binds_the_referenced_editor_image_to_it(): void
    {
        $image = $this->editorImage();

        $room = $this->createRoomCategory(['description' => $this->html($image)]);

        $image->refresh();
        $this->assertSame(RoomCategory::class, $image->model_type);
        $this->assertSame($room->id, $image->model_id);
    }

    public function test_an_image_owned_by_a_facility_is_not_taken_over_by_a_room_category(): void
    {
        $image = $this->editorImage();
        $facility = $this->createFacility(['description' => $this->html($image)]);

        $this->createRoomCategory(['description' => $this->html($image)]);

        $this->assertSame(Facility::class, $image->fresh()->model_type);
        $this->assertSame($facility->id, $image->fresh()->model_id);
    }

    public function test_removing_the_image_from_the_description_releases_it(): void
    {
        $inFacility = $this->editorImage();
        $inRoom = $this->editorImage();
        $facility = $this->createFacility(['description' => $this->html($inFacility)]);
        $room = $this->createRoomCategory(['description' => $this->html($inRoom)]);

        $facility->update(['description' => '<p>Tanpa gambar lagi.</p>']);
        $room->update(['description' => '<p>Tanpa gambar lagi.</p>']);

        $this->assertNull($inFacility->fresh()->model_id);
        $this->assertNull($inFacility->fresh()->model_type);
        $this->assertNull($inRoom->fresh()->model_id);
    }

    public function test_updating_other_attributes_does_not_release_the_image(): void
    {
        $image = $this->editorImage();
        $facility = $this->createFacility(['description' => $this->html($image)]);

        $facility->update(['short_description' => 'Ringkasan baru.']);

        $this->assertSame($facility->id, $image->fresh()->model_id);
    }

    public function test_prune_skips_ownerless_images_still_referenced_by_a_facility_or_room_description(): void
    {
        $inFacility = $this->editorImage();
        $inRoom = $this->editorImage();
        $unreferenced = $this->editorImage();

        // Baris lama sebelum pengikatan diterapkan: pemilik NULL walau dirujuk (jalur yang melewati event model).
        $this->createFacility()->forceFill(['description' => $this->html($inFacility)])->saveQuietly();
        $this->createRoomCategory()->forceFill(['description' => $this->html($inRoom)])->saveQuietly();

        foreach ([$inFacility, $inRoom, $unreferenced] as $media) {
            $this->age($media, 30);
        }

        DB::table('media')->whereIn('id', [$inFacility->id, $inRoom->id])->update(['model_type' => null, 'model_id' => null]);

        $report = $this->service->prune();

        $this->assertSame(1, $report->orphanRows);
        $this->assertNotNull($inFacility->fresh());
        $this->assertNotNull($inRoom->fresh());
        $this->assertNull($unreferenced->fresh());
        $this->assertFilesExist($inFacility);
        $this->assertFilesExist($inRoom);
        $this->assertFilesMissing($unreferenced);
    }

    public function test_deleting_a_facility_or_room_category_deletes_its_editor_images_and_cover_files(): void
    {
        $image = $this->editorImage();
        $facility = $this->createFacility(['description' => $this->html($image)]);
        $cover = $this->service->store(ImageFixtures::jpeg(900, 600, 'cover.jpg'), 'cover', $facility, ['alt_text' => 'Foto contoh']);
        $roomImage = $this->editorImage();
        $room = $this->createRoomCategory(['description' => $this->html($roomImage)]);

        $facility->delete();
        $room->delete();

        $this->assertSame(0, Media::query()->count());
        $this->assertFilesMissing($image);
        $this->assertFilesMissing($cover);
        $this->assertFilesMissing($roomImage);
    }
}
```

- [ ] **Step 6: Tulis tes yang gagal (tool Write) `tests/Feature/View/FormFilePlaceholderTest.php`**

```php
<?php

namespace Tests\Feature\View;

use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * F4B-R27: prop `placeholder` pada <x-form.file> menimpa peta koleksi → tipe placeholder pratinjau.
 */
class FormFilePlaceholderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function makeMedia(string $collection): Media
    {
        return Media::create([
            'model_type' => null,
            'model_id' => null,
            'collection' => $collection,
            'disk' => 'public',
            'directory' => $collection.'/2026/10',
            'file_name' => Str::uuid()->toString().'.jpg',
            'original_name' => 'foto.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size_bytes' => 120000,
            'width' => 1200,
            'height' => 800,
            'thumb_path' => $collection.'/2026/10/thumbs/foto.jpg',
            'alt_text' => 'Foto contoh ruang tunggu',
            'caption' => null,
            'sort_order' => 1,
            'uploaded_by' => null,
        ])->fresh();
    }

    public function test_placeholder_facility_overrides_the_cover_mapping(): void
    {
        $media = $this->makeMedia('cover');

        $this->blade('<x-form.file name="cover" label="Foto sampul" placeholder="facility" :current-media="$media" />', ['media' => $media])
            ->assertSee('data-current-media', false)
            ->assertSee('data-fallback="'.asset('images/placeholders/facility.svg').'"', false)
            ->assertDontSee('placeholders/clinic.svg', false);
    }

    public function test_placeholder_room_overrides_the_gallery_mapping(): void
    {
        $media = $this->makeMedia('gallery');

        $this->blade('<x-form.file name="cover" label="Foto sampul" placeholder="room" :current-media="$media" />', ['media' => $media])
            ->assertSee('data-fallback="'.asset('images/placeholders/room.svg').'"', false)
            ->assertDontSee('placeholders/gallery.svg', false);
    }

    public function test_without_the_prop_the_collection_map_is_unchanged(): void
    {
        $cover = $this->makeMedia('cover');
        $gallery = $this->makeMedia('gallery');
        $photo = $this->makeMedia('photo');
        $unknown = $this->makeMedia('koleksi-lain');

        $this->blade('<x-form.file name="cover" label="Foto" :current-media="$media" />', ['media' => $cover])
            ->assertSee('data-fallback="'.asset('images/placeholders/clinic.svg').'"', false);
        $this->blade('<x-form.file name="gallery" label="Foto" :current-media="$media" />', ['media' => $gallery])
            ->assertSee('data-fallback="'.asset('images/placeholders/gallery.svg').'"', false);
        $this->blade('<x-form.file name="photo" label="Foto" :current-media="$media" />', ['media' => $photo])
            ->assertSee('data-fallback="'.asset('images/placeholders/doctor.svg').'"', false);
        $this->blade('<x-form.file name="lain" label="Foto" :current-media="$media" />', ['media' => $unknown])
            ->assertSee('data-fallback="'.asset('images/placeholders/gallery.svg').'"', false);
    }

    public function test_the_prop_is_not_rendered_as_an_html_attribute(): void
    {
        $media = $this->makeMedia('cover');

        $this->blade('<x-form.file name="cover" label="Foto sampul" placeholder="facility" :current-media="$media" />', ['media' => $media])
            ->assertDontSee('placeholder="facility"', false)
            ->assertSee('type="file"', false)
            ->assertSee('name="cover_alt"', false);
    }

    public function test_the_prop_has_no_effect_and_no_error_without_a_current_media(): void
    {
        $this->blade('<x-form.file name="cover" label="Foto sampul" placeholder="room" />')
            ->assertDontSee('data-current-media', false)
            ->assertDontSee('placeholders/room.svg', false)
            ->assertSee('name="cover"', false);
    }
}
```

- [ ] **Step 7: Tulis tes yang gagal (tool Write) `tests/Feature/Support/FacilitiesModuleTabsTest.php`**

```php
<?php

namespace Tests\Feature\Support;

use App\Models\User;
use App\Support\NavigationMenu;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * F4B-R28: tab modul halaman #36 (`modules.facilities`). Route admin 4b baru didaftarkan Task 4 dan 5, jadi tes
 * mendaftarkan route palsu hanya bila belum ada; tes tetap benar sebelum dan sesudah route asli ada.
 */
class FacilitiesModuleTabsTest extends TestCase
{
    use InteractsWithStaff;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin.room-categories.index' => '/_uji/kamar', 'admin.facilities.index' => '/_uji/fasilitas'] as $name => $uri) {
            if (! Route::has($name)) {
                Route::middleware('web')->get($uri, fn () => 'ok')->name($name);
            }
        }

        Route::getRoutes()->refreshNameLookups();
    }

    public function test_the_configuration_follows_the_blueprint(): void
    {
        $this->assertSame([
            ['label' => 'Kamar dan Kapasitas', 'route' => 'admin.room-categories.index', 'permission' => 'facilities.manage|rooms.update-availability', 'active' => ['admin.room-categories.*']],
            ['label' => 'Fasilitas', 'route' => 'admin.facilities.index', 'permission' => 'facilities.manage', 'active' => ['admin.facilities.*']],
        ], config('navigation.modules.facilities'));
    }

    public function test_every_permission_in_the_tabs_exists_in_the_catalogue(): void
    {
        foreach (config('navigation.modules.facilities') as $tab) {
            foreach (explode('|', $tab['permission']) as $permission) {
                $this->assertContains($permission, RolePermissionSeeder::PERMISSIONS, "Izin {$permission} tidak ada di katalog.");
            }
        }
    }

    public function test_the_tabs_cover_the_same_routes_as_the_sidebar_group(): void
    {
        $group = collect(config('navigation.admin'))->firstWhere('group', 'Fasilitas dan Kamar');

        $this->assertNotNull($group);
        $this->assertSame(array_column($group['items'], 'route'), array_column(config('navigation.modules.facilities'), 'route'));
        $this->assertSame(array_column($group['items'], 'permission'), array_column(config('navigation.modules.facilities'), 'permission'));
        $this->assertSame(array_column($group['items'], 'label'), array_column(config('navigation.modules.facilities'), 'label'));
    }

    public function test_content_admin_and_super_admin_see_both_tabs(): void
    {
        foreach (['content-admin', 'super-admin'] as $role) {
            $labels = array_column(NavigationMenu::items('modules.facilities', $this->createStaff($role)), 'label');

            $this->assertSame(['Kamar dan Kapasitas', 'Fasilitas'], $labels, $role);
        }
    }

    public function test_the_registration_officer_sees_only_the_rooms_tab(): void
    {
        $items = NavigationMenu::items('modules.facilities', $this->createStaff('registration-officer'));

        $this->assertSame(['Kamar dan Kapasitas'], array_column($items, 'label'));
        $this->assertSame(route('admin.room-categories.index'), $items[0]['url']);
    }

    public function test_guests_and_patients_see_no_tabs(): void
    {
        $this->assertSame([], NavigationMenu::items('modules.facilities', null));
        $this->assertSame([], NavigationMenu::items('modules.facilities', User::factory()->create()));
    }

    public function test_a_tab_to_a_missing_route_is_hidden(): void
    {
        $tabs = config('navigation.modules.facilities');
        $tabs[1]['route'] = 'rute.belum.ada';
        config(['navigation.modules.facilities' => $tabs]);

        $this->assertSame(['Kamar dan Kapasitas'], array_column(NavigationMenu::items('modules.facilities', $this->createStaff('content-admin')), 'label'));

        $tabs[0]['route'] = 'rute.belum.ada.juga';
        config(['navigation.modules.facilities' => $tabs]);

        $this->assertSame([], NavigationMenu::items('modules.facilities', $this->createStaff('super-admin')));
    }

    public function test_the_content_tabs_are_unchanged_by_the_new_group(): void
    {
        $this->assertCount(7, config('navigation.modules.content'));
        $this->assertSame(['clinics', 'schedules', 'content', 'facilities'], array_keys(config('navigation.modules')));
    }
}
```

- [ ] **Step 8: Edit tes lama (F4B-R37) `tests/Feature/Auth/MediaPolicyTest.php` dan `tests/Feature/Media/ContentEditorImagesTest.php`**

Edit `tests/Feature/Auth/MediaPolicyTest.php`: ganti teks berikut (persis)

```php
use App\Models\Doctor;
use App\Models\Gallery;
use App\Models\InsurancePartner;
use App\Models\Media;
use App\Models\Page;
use App\Models\Specialty;
```

dengan

```php
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\Gallery;
use App\Models\InsurancePartner;
use App\Models\Media;
use App\Models\Page;
use App\Models\PatientProfile;
use App\Models\RoomCategory;
use App\Models\Specialty;
```

Edit `tests/Feature/Auth/MediaPolicyTest.php`: ganti teks berikut (persis)

```php
            'tipe pemilik yang belum dipetakan' => ['App\Models\Facility', 'cover', $superOnly],
```

dengan

```php
            'cover fasilitas' => [Facility::class, 'cover', $contentOnly],
            'galeri fasilitas' => [Facility::class, 'gallery', $contentOnly],
            'gambar editor fasilitas' => [Facility::class, 'editor', $contentOnly],
            'cover kategori kamar' => [RoomCategory::class, 'cover', $contentOnly],
            'galeri kategori kamar' => [RoomCategory::class, 'gallery', $contentOnly],
            'gambar editor kategori kamar' => [RoomCategory::class, 'editor', $contentOnly],
            'tipe pemilik yang belum dipetakan' => [PatientProfile::class, 'cover', $superOnly],
```

Edit `tests/Feature/Auth/MediaPolicyTest.php`: ganti teks berikut (persis)

```php
    public function test_content_admin_cannot_touch_the_site_logo_but_super_admin_can(): void
```

dengan

```php
    public function test_facility_and_room_media_are_managed_by_facility_managers_not_by_availability_updaters(): void
    {
        $content = $this->userFor('content-admin');
        $super = $this->userFor('super-admin');
        $officer = $this->userFor('registration-officer');
        $patient = $this->userFor('patient');

        $this->assertTrue($officer->can('rooms.update-availability'), 'Petugas memegang izin ketersediaan kamar.');
        $this->assertFalse($officer->can('facilities.manage'));

        foreach ([[Facility::class, 'cover'], [Facility::class, 'gallery'], [Facility::class, 'editor'], [RoomCategory::class, 'cover'], [RoomCategory::class, 'gallery'], [RoomCategory::class, 'editor']] as [$type, $collection]) {
            $media = $this->mediaFor($type, $collection);

            foreach (['update', 'delete', 'reorder'] as $ability) {
                $this->assertTrue(Gate::forUser($content)->allows($ability, $media), "content-admin {$ability} {$type} {$collection}");
                $this->assertTrue(Gate::forUser($super)->allows($ability, $media), "super-admin {$ability} {$type} {$collection}");
                $this->assertFalse(Gate::forUser($officer)->allows($ability, $media), "petugas {$ability} {$type} {$collection}");
                $this->assertFalse(Gate::forUser($patient)->allows($ability, $media), "pasien {$ability} {$type} {$collection}");
            }
        }
    }

    public function test_content_admin_cannot_touch_the_site_logo_but_super_admin_can(): void
```

Edit `tests/Feature/Media/ContentEditorImagesTest.php`: ganti teks berikut (persis)

```php
    public function test_the_reference_columns_include_pages_and_articles(): void
    {
        $this->assertSame(
            ['clinics' => ['description'], 'pages' => ['body'], 'articles' => ['body']],
            MediaService::EDITOR_REFERENCE_COLUMNS,
        );
    }
```

dengan

```php
    public function test_the_reference_columns_include_every_rich_text_table(): void
    {
        $this->assertSame(
            [
                'clinics' => ['description'],
                'pages' => ['body'],
                'articles' => ['body'],
                'facilities' => ['description'],
                'room_categories' => ['description'],
            ],
            MediaService::EDITOR_REFERENCE_COLUMNS,
        );
    }
```

- [ ] **Step 9: Jalankan, harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="LikePatternTest|LikePatternDatabaseTest|FacilityAuditTest|FacilityEditorImagesTest|FormFilePlaceholderTest|FacilitiesModuleTabsTest|MediaPolicyTest|ContentEditorImagesTest"
```
Expected: FAIL. `LikePatternTest` dan `LikePatternDatabaseTest`: `Error: Class "App\Support\LikePattern" not found`; pemindai salinan gagal dengan daftar sembilan berkas; `FacilityAuditTest`: label entitas mengembalikan kunci mentah (`messages.entities.facility`) dan `ModelNotFoundException` untuk `AuditLog` (aksi `facility.created` belum ada); `FacilityEditorImagesTest`: `assertSame` konstanta gagal, gambar editor tetap tanpa pemilik; `FormFilePlaceholderTest`: `data-fallback` memuat `clinic.svg`, bukan `facility.svg`; `FacilitiesModuleTabsTest`: `config('navigation.modules.facilities')` bernilai `null`; `MediaPolicyTest`: kasus `Facility`/`RoomCategory` ditolak untuk Admin Konten; `ContentEditorImagesTest::test_the_reference_columns_include_every_rich_text_table` gagal pada `assertSame`.

- [ ] **Step 10: Implementasi `app/Support/LikePattern.php` dan refaktor sembilan salinan**

Tulis `app/Support/LikePattern.php` (tool Write):

```php
<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Pola LIKE "mengandung" yang aman (F4B-R26): karakter `\`, `%`, dan `_` pada kata kunci diperlakukan sebagai teks
 * biasa, bukan wildcard. Satu-satunya tempat escape ini ditulis; jangan menyalinnya ke kelas lain.
 */
final class LikePattern
{
    /**
     * `%{term}%` dengan `\`, `%`, dan `_` di-escape. Kata kunci tidak dipangkas; pemanggil yang memangkas.
     */
    public static function contains(string $term): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
    }
}
```

Edit `app/Http/Controllers/Admin/ArticleCategoryController.php`: ganti teks berikut (persis)

```php
use App\Models\ArticleCategory;
use Illuminate\Database\Eloquent\Builder;
```

dengan

```php
use App\Models\ArticleCategory;
use App\Support\LikePattern;
use Illuminate\Database\Eloquent\Builder;
```

Edit `app/Http/Controllers/Admin/ArticleCategoryController.php`: ganti teks berikut (persis)

```php
$query->where('name', 'like', $this->like($term))
```

dengan

```php
$query->where('name', 'like', LikePattern::contains($term))
```

Edit `app/Http/Controllers/Admin/ArticleCategoryController.php`: ganti teks berikut (persis)

```php
    }

    /**
     * Pola LIKE dengan karakter `\`, `%`, dan `_` di-escape agar kata kunci diperlakukan sebagai teks biasa.
     */
    private function like(string $term): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
    }
}
```

dengan

```php
    }
}
```

Edit `app/Http/Requests/Admin/ArticleIndexRequest.php`: ganti teks berikut (persis)

```php
use App\Http\Requests\ListFilterRequest;
use Illuminate\Database\Eloquent\Builder;
```

dengan

```php
use App\Http\Requests\ListFilterRequest;
use App\Support\LikePattern;
use Illuminate\Database\Eloquent\Builder;
```

Edit `app/Http/Requests/Admin/ArticleIndexRequest.php`: ganti teks berikut (persis)

```php
$like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['q']).'%';
```

dengan

```php
$like = LikePattern::contains($filters['q']);
```

Edit `app/Http/Requests/Admin/BannerIndexRequest.php`: ganti teks berikut (persis)

```php
use App\Http\Requests\ListFilterRequest;
use Illuminate\Database\Eloquent\Builder;
```

dengan

```php
use App\Http\Requests\ListFilterRequest;
use App\Support\LikePattern;
use Illuminate\Database\Eloquent\Builder;
```

Edit `app/Http/Requests/Admin/BannerIndexRequest.php`: ganti teks berikut (persis)

```php
$like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['q']).'%';
```

dengan

```php
$like = LikePattern::contains($filters['q']);
```

Edit `app/Http/Requests/Admin/FaqIndexRequest.php`: ganti teks berikut (persis)

```php
use App\Models\Faq;
use Illuminate\Database\Eloquent\Builder;
```

dengan

```php
use App\Models\Faq;
use App\Support\LikePattern;
use Illuminate\Database\Eloquent\Builder;
```

Edit `app/Http/Requests/Admin/FaqIndexRequest.php`: ganti teks berikut (persis)

```php
$like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['q']).'%';
```

dengan

```php
$like = LikePattern::contains($filters['q']);
```

Edit `app/Http/Requests/Admin/GalleryIndexRequest.php`: ganti teks berikut (persis)

```php
use App\Http\Requests\ListFilterRequest;
use Illuminate\Database\Eloquent\Builder;
```

dengan

```php
use App\Http\Requests\ListFilterRequest;
use App\Support\LikePattern;
use Illuminate\Database\Eloquent\Builder;
```

Edit `app/Http/Requests/Admin/GalleryIndexRequest.php`: ganti teks berikut (persis)

```php
$like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['q']).'%';
```

dengan

```php
$like = LikePattern::contains($filters['q']);
```

Edit `app/Http/Requests/Admin/MasterDataIndexRequest.php`: ganti teks berikut (persis)

```php
use App\Http\Requests\ListFilterRequest;
use Illuminate\Database\Eloquent\Builder;
```

dengan

```php
use App\Http\Requests\ListFilterRequest;
use App\Support\LikePattern;
use Illuminate\Database\Eloquent\Builder;
```

Edit `app/Http/Requests/Admin/MasterDataIndexRequest.php`: ganti teks berikut (persis)

```php
$like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['q']).'%';
```

dengan

```php
$like = LikePattern::contains($filters['q']);
```

Edit `app/Http/Requests/Admin/ScheduleExceptionIndexRequest.php`: ganti teks berikut (persis)

```php
use App\Models\ScheduleException;
use Illuminate\Database\Eloquent\Builder;
```

dengan

```php
use App\Models\ScheduleException;
use App\Support\LikePattern;
use Illuminate\Database\Eloquent\Builder;
```

Edit `app/Http/Requests/Admin/ScheduleExceptionIndexRequest.php`: ganti teks berikut (persis)

```php
$like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['q']).'%';
```

dengan

```php
$like = LikePattern::contains($filters['q']);
```

Edit `app/Models/Article.php`: ganti teks berikut (persis)

```php
use App\Models\Concerns\SanitizesRichText;
use Database\Factories\ArticleFactory;
```

dengan

```php
use App\Models\Concerns\SanitizesRichText;
use App\Support\LikePattern;
use Database\Factories\ArticleFactory;
```

Edit `app/Models/Article.php`: ganti teks berikut (persis)

```php
$like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
```

dengan

```php
$like = LikePattern::contains($term);
```

Edit `app/Models/Doctor.php`: ganti teks berikut (persis)

```php
use App\Models\Concerns\HasUniqueSlug;
use Database\Factories\DoctorFactory;
```

dengan

```php
use App\Models\Concerns\HasUniqueSlug;
use App\Support\LikePattern;
use Database\Factories\DoctorFactory;
```

Edit `app/Models/Doctor.php`: ganti teks berikut (persis)

```php
$like = '%'.addcslashes($term, '%_\\').'%';
```

dengan

```php
$like = LikePattern::contains($term);
```

- [ ] **Step 11: Implementasi `lang/id/messages.php`, observer, `MediaPolicy`, `MediaService`, `x-form.file`, dan `config/navigation.php`**

Edit `lang/id/messages.php`: ganti teks berikut (persis)

```php
        'insurance_partner' => 'Mitra asuransi',
    ],
```

dengan

```php
        'insurance_partner' => 'Mitra asuransi',
        'facility' => 'Fasilitas',
        'room_category' => 'Kategori kamar',
        'contact_message' => 'Pesan kontak',
    ],
```

Edit `app/Providers/AppServiceProvider.php`: ganti teks berikut (persis)

```php
use App\Models\Clinic;
use App\Models\Faq;
```

dengan

```php
use App\Models\Clinic;
use App\Models\Facility;
use App\Models\Faq;
```

Edit `app/Providers/AppServiceProvider.php`: ganti teks berikut (persis)

```php
use App\Models\Page;
use App\Models\Specialty;
```

dengan

```php
use App\Models\Page;
use App\Models\RoomCategory;
use App\Models\Specialty;
```

Edit `app/Providers/AppServiceProvider.php`: ganti teks berikut (persis)

```php
        InsurancePartner::observe(AuditObserver::class);
```

dengan

```php
        InsurancePartner::observe(AuditObserver::class);

        // Audit otomatis fasilitas dan kategori kamar fase 4b (F4B-R25). ContactMessage sengaja tidak diobservasi:
        // nama pengirim tidak boleh masuk deskripsi audit; perubahan statusnya dicatat eksplisit oleh controller.
        Facility::observe(AuditObserver::class);
        RoomCategory::observe(AuditObserver::class);
```

Edit `app/Observers/AuditObserver.php`: ganti teks berikut (persis)

```php
 * fase 4a (F4A-R8): `page.*`, `banner.*`, `article.*`, `article_category.*`, `gallery.*`, `faq.*`, `insurance_partner.*`.
```

dengan

```php
 * fase 4a (F4A-R8): `page.*`, `banner.*`, `article.*`, `article_category.*`, `gallery.*`, `faq.*`, `insurance_partner.*`;
 * serta fase 4b (F4B-R25): `facility.*` dan `room_category.*`. `ContactMessage` tidak diobservasi.
```

Edit `app/Policies/MediaPolicy.php`: ganti teks berikut (persis)

```php
use App\Models\Doctor;
use App\Models\Gallery;
use App\Models\InsurancePartner;
use App\Models\Media;
use App\Models\Page;
use App\Models\Specialty;
```

dengan

```php
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\Gallery;
use App\Models\InsurancePartner;
use App\Models\Media;
use App\Models\Page;
use App\Models\RoomCategory;
use App\Models\Specialty;
```

Edit `app/Policies/MediaPolicy.php`: ganti teks berikut (persis)

```php
 * - Page, Article, Banner, Gallery, dan InsurancePartner => `content.manage` (F4A-R9).
```

dengan

```php
 * - Page, Article, Banner, Gallery, dan InsurancePartner => `content.manage` (F4A-R9).
 * - Facility dan RoomCategory => `facilities.manage` (F4B-R21). `rooms.update-availability` hanya untuk aksi
 *   ketersediaan, bukan untuk gambar kamar.
```

Edit `app/Policies/MediaPolicy.php`: ganti teks berikut (persis)

```php
 * - `model_type` lain belum dipetakan sehingga ditolak (fase 4b menambah Facility dan RoomCategory).
```

dengan

```php
 * - `model_type` lain (mis. PatientProfile) ditolak.
```

Edit `app/Policies/MediaPolicy.php`: ganti teks berikut (persis)

```php
            Page::class, Article::class, Banner::class, Gallery::class, InsurancePartner::class => $user->can('content.manage'),
            default => false,
```

dengan

```php
            Page::class, Article::class, Banner::class, Gallery::class, InsurancePartner::class => $user->can('content.manage'),
            Facility::class, RoomCategory::class => $user->can('facilities.manage'),
            default => false,
```

Edit `app/Services/Media/MediaService.php`: ganti teks berikut (persis)

```php
     * tanpa pemilik yang masih dirujuk tidak dihapus. Fase 4b WAJIB menambahkan `facilities` dan
     * `room_categories` (kolom `description`) di sini.
     */
    public const EDITOR_REFERENCE_COLUMNS = [
        'clinics' => ['description'],
        'pages' => ['body'],
        'articles' => ['body'],
    ];
```

dengan

```php
     * tanpa pemilik yang masih dirujuk tidak dihapus. Setiap tabel dengan kolom rich text baru WAJIB
     * ditambahkan di sini (fase 4b: `facilities` dan `room_categories`, kolom `description`).
     */
    public const EDITOR_REFERENCE_COLUMNS = [
        'clinics' => ['description'],
        'pages' => ['body'],
        'articles' => ['body'],
        'facilities' => ['description'],
        'room_categories' => ['description'],
    ];
```

Edit `resources/views/components/form/file.blade.php`: ganti teks berikut (persis)

```php
    'altValue' => null,
])
```

dengan

```php
    'altValue' => null,
    'placeholder' => null,
])
```

Edit `resources/views/components/form/file.blade.php`: ganti teks berikut (persis)

```php
<x-image :media="$currentMedia" :type="$placeholderTypes[$currentMedia->collection] ?? 'gallery'" variant="thumb"
```

dengan

```php
<x-image :media="$currentMedia" :type="$placeholder ?? ($placeholderTypes[$currentMedia->collection] ?? 'gallery')" variant="thumb"
```

Edit `config/navigation.php`: ganti teks berikut (persis)

```php
            ['label' => 'Mitra Asuransi', 'route' => 'admin.insurance-partners.index', 'permission' => 'content.manage', 'active' => ['admin.insurance-partners.*']],
        ],
    ],

];
```

dengan

```php
            ['label' => 'Mitra Asuransi', 'route' => 'admin.insurance-partners.index', 'permission' => 'content.manage', 'active' => ['admin.insurance-partners.*']],
        ],
        // Halaman #36 (F4B-R28). Tab ke route yang belum ada otomatis tersembunyi. "Kamar dan Kapasitas" juga terbuka
        // bagi pemegang `rooms.update-availability` (Petugas Pendaftaran, D-12).
        'facilities' => [
            ['label' => 'Kamar dan Kapasitas', 'route' => 'admin.room-categories.index', 'permission' => 'facilities.manage|rooms.update-availability', 'active' => ['admin.room-categories.*']],
            ['label' => 'Fasilitas', 'route' => 'admin.facilities.index', 'permission' => 'facilities.manage', 'active' => ['admin.facilities.*']],
        ],
    ],

];
```

- [ ] **Step 12: Jalankan tes tugas dan tes lama yang terdampak, harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="LikePatternTest|LikePatternDatabaseTest|FacilityAuditTest|FacilityEditorImagesTest|FormFilePlaceholderTest|FacilitiesModuleTabsTest|MediaPolicyTest|ContentEditorImagesTest"
& $PHP artisan test --filter="AdminArticleCategoriesTest|AdminArticlesTest|AdminBannersTest|AdminFaqsTest|AdminGalleriesTest|AdminSpecialtyIndexTest|AdminClinicIndexTest|AdminInsurancePartnersTest|AdminScheduleExceptionsTest|ScheduleIndexRequestsTest|ArticleModelTest|DoctorModelTest|PublicArticlesTest|PublicDoctorIndexTest"
& $PHP artisan test --filter="FormFileComponentTest|ModuleTabsComponentTest|NavigationMenuTest|AuditObserverTest|ContentAuditTest|AdminMediaEndpointsTest|MessagesLangTest|EnumCatalogTest"
```
Expected: seluruh tes PASS. Kelompok kedua dan ketiga adalah tes lama yang menjaga perilaku pencarian, formulir, navigasi, audit, dan media; tidak boleh ada yang berubah. Bila `AuditObserverTest::test_the_observer_is_registered_for_exactly_specialty_and_clinic` gagal, periksa bahwa tes itu hanya membuat spesialisasi dan poliklinik (tidak terdampak observer baru).

- [ ] **Step 13: Pint**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint
& $PHP vendor\bin\pint --test
```
Expected: `--test` PASS. Pint dapat mengurutkan ulang baris `use` hasil Edit; jalankan `git diff` bila ada perubahan di luar berkas Task ini dan batalkan.

- [ ] **Step 14: Suite penuh (latar belakang)**

Jalankan sebagai tugas latar belakang (tool PowerShell dengan `run_in_background`), tunggu sampai selesai, lalu baca ringkasannya.

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
$LOG = Join-Path $env:TEMP "suite-fase4b-t03.txt"
& $PHP artisan test | Out-File -FilePath $LOG -Encoding utf8
Select-String -Path $LOG -Pattern 'Tests:|FAIL|Duration' | Select-Object -Last 20
```
Expected: seluruh tes PASS (±4550 tes: hasil Task 2 + ±55 tes Task 3). Bila ada tes lama yang menghitung seluruh baris `audit_logs` setelah membuat `Facility` atau `RoomCategory`, tidak ada yang ditemukan di repo saat ini (sapuan `tests/`); laporkan bila muncul.

- [ ] **Step 15: Commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git add app/Support/LikePattern.php app/Http/Controllers/Admin/ArticleCategoryController.php app/Http/Requests/Admin/ArticleIndexRequest.php app/Http/Requests/Admin/BannerIndexRequest.php app/Http/Requests/Admin/FaqIndexRequest.php app/Http/Requests/Admin/GalleryIndexRequest.php app/Http/Requests/Admin/MasterDataIndexRequest.php app/Http/Requests/Admin/ScheduleExceptionIndexRequest.php app/Models/Article.php app/Models/Doctor.php app/Providers/AppServiceProvider.php app/Observers/AuditObserver.php lang/id/messages.php app/Policies/MediaPolicy.php app/Services/Media/MediaService.php resources/views/components/form/file.blade.php config/navigation.php tests/Unit/Support/LikePatternTest.php tests/Feature/Support/LikePatternDatabaseTest.php tests/Feature/Facilities/FacilityAuditTest.php tests/Feature/Media/FacilityEditorImagesTest.php tests/Feature/View/FormFilePlaceholderTest.php tests/Feature/Support/FacilitiesModuleTabsTest.php tests/Feature/Auth/MediaPolicyTest.php tests/Feature/Media/ContentEditorImagesTest.php
git commit -m @'
feat(fase-4b): integrasi lintas modul (audit, MediaPolicy, gambar editor, LikePattern, x-form.file, tab modul)

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
'@
```

---

### Task 4: Admin #36 Fasilitas (`admin.facilities.*`) dan blok route 4b dengan penanda

**Files:**
- Create: `app/Policies/FacilityPolicy.php`
- Create: `app/Http/Requests/Admin/FacilityRequest.php`
- Create: `app/Http/Requests/Admin/FacilityIndexRequest.php`
- Create: `app/Http/Controllers/Admin/FacilityController.php`
- Create: `resources/views/admin/facilities/index.blade.php`, `create.blade.php`, `edit.blade.php`, `_form.blade.php`
- Modify: `routes/admin.php` (satu `use`, blok route fasilitas, dan dua penanda `// Task 5: kategori kamar` dan `// Task 6: pesan kontak`, semuanya sebelum komentar `// J. Media`)
- Modify (tes lama, F4B-R37): `tests/Feature/Access/Phase4aRoutePermissionTest.php`
- Test: `tests/Feature/Admin/AdminFacilitiesTest.php`, `tests/Feature/Admin/FacilityRequestTest.php`
- Tidak diubah: model `Facility`, factory, `InteractsWithFacilities` (T2); `MediaPolicy`, `AuditObserver`, kunci `messages.entities.facility`, `LikePattern`, prop `placeholder` `x-form.file`, dan grup tab `modules.facilities` di `config/navigation.php` (T3); `MediaController` dan route `admin.media.*` (fase 2, middleware sudah memuat `facilities.manage`).

**Interfaces:**
- Consumes:
  - T2: `App\Models\Facility` (`fillable`: `name`, `slug`, `category`, `short_description`, `description`, `is_active`, `sort_order`, `meta_title`, `meta_description`; cast `category` ke `FacilityCategory`; `scopeActive`, `scopeOrdered`; trait `HasMedia`, `HasUniqueSlug`, `SanitizesRichText`), `FacilityFactory`, `Tests\Support\InteractsWithFacilities::createFacility(array $attributes = []): Facility`.
  - T3:
    - `App\Support\LikePattern::contains(string $term): string`;
    - `MediaPolicy::manages()` memetakan `Facility` ke `facilities.manage`;
    - `AuditObserver` terdaftar untuk `Facility` (aksi `facility.created|updated|deleted`) dan `messages.entities.facility` = "Fasilitas";
    - `x-form.file` menerima prop `placeholder`;
    - `config('navigation.modules.facilities')` (tab "Kamar dan Kapasitas" dan "Fasilitas", disaring `Route::has` dan izin).
  - Fase 2: `MediaService::store()` / `replace()` / `updateMeta()`, `ImageRules::for()` / `ImageRules::messages()`, `ListFilterRequest`, `x-data-table`, `x-module-tabs`, `x-confirm-modal`, `x-form.rich-text`, `x-form.select`, `x-form.file`, dan route `admin.media.update|reorder|destroy`.
- Produces:
  - `App\Http\Controllers\Admin\FacilityController`: `index`, `create`, `store`, `edit`, `update`, `destroy` (tanpa `show`).
  - Route (semua `permission:facilities.manage`): `admin.facilities.index|create|store|edit|update|destroy` → `/admin/fasilitas`, `/admin/fasilitas/buat`, `/admin/fasilitas/{facility}/ubah`, `/admin/fasilitas/{facility}`. Enam route.
  - `App\Policies\FacilityPolicy` (`final`, semua method memerlukan `facilities.manage`).
  - `Admin\FacilityRequest`: `MAX_GALLERY = 20`, `facilityAttributes(): array`, `coverFile(): ?UploadedFile`, `coverAlt(): string`, `galleryFiles(): array`, `galleryAlt(int|string $index): string`.
  - `Admin\FacilityIndexRequest extends ListFilterRequest`: `statusValues()` `['aktif', 'nonaktif']`, `sortable()` `['urutan', 'nama']`, `applyTo(Builder $query): Builder` (F4B-R22).
  - Penanda untuk T5 dan T6 di `routes/admin.php` (teks persis, indentasi 4 spasi di dalam grup G-ADMIN): `// Task 5: kategori kamar` dan `// Task 6: pesan kontak`.
  - Flash `success`: "Fasilitas berhasil ditambahkan." / "… diperbarui." / "… dihapus.".
  - Judul layout: "Fasilitas", "Tambah Fasilitas", "Ubah {nama}". Breadcrumb: Dasbor › Fasilitas dan Kamar › Fasilitas (› Tambah / Ubah {nama}).

**Test IDs:** CRD-06 (fasilitas), CRD-13 (`facilities` dihapus permanen beserta berkas media), SEK-08 (sanitasi `description`), SEK-09 dan SEK-10 (SVG, `shell.php.jpg`, teks bernama `.jpg` pada unggah cover), MED-05 dan VAL-06 (batas ukuran, dimensi, alt wajib), VAL-01, VAL-07, AKS-04 (Admin Konten 200, Petugas 403), EMP-03, Review Focus 4 (unggahan berbahaya, cover < 800 x 450, galeri 21 gambar, alt kosong).

**Catatan teknis (diverifikasi dari source):**
1. **Pola yang ditiru.** `ClinicController` (otorisasi di controller, `saveMedia` dengan `replace` atau `updateMeta` bila hanya alt yang berubah, hapus lewat `DB::transaction` agar berkas dihapus setelah commit) dan `ClinicRequest` (`MAX_GALLERY`, `withValidator`, alt cover yang kosong ditolak bila cover sudah ada). Hanya nama field, pesan, dan enum yang berbeda.
2. **Tanpa input slug** (F4B-R5, D-31). `HasUniqueSlug` membuat slug dari `name` saat `creating` dan tidak mengubahnya saat nama diedit.
3. **Batas rich text** (F4B-R3): `description` memakai `max:30000` dengan pesan "Deskripsi maksimal 30.000 karakter." (bukan 50.000 seperti 12 V-27). Sanitasi dilakukan trait `SanitizesRichText` saat `saving`, bukan Form Request.
4. **Daftar** (F4B-R22): `with('media')` wajib (mode ketat, `HasMedia::mediaIn()` melempar `LazyLoadingViolationException` tanpa eager load). Halaman ubah memanggil `$facility->load('media')`.
5. **Filter `kategori`.** `ListFilterRequest` sudah punya aturan `kategori` (string bebas). `FacilityIndexRequest::rules()` menimpanya dengan `Rule::in(FacilityCategory::values())`, sehingga nilai tidak valid atau larik diabaikan dan pengguna diberi flash `warning` "Filter tidak valid dan diabaikan." (VAL-07), bukan 500.
6. **`LikePattern`.** Kata kunci `q` memakai `LikePattern::contains()` (T3). Jangan menulis salinan `str_replace` baru.
7. **Kesesuaian dengan tab modul.** `<x-module-tabs group="facilities" />` membaca grup `modules.facilities` (T3). Selama route `admin.room-categories.index` belum ada (sampai T5), tab "Kamar dan Kapasitas" tersembunyi sendiri oleh `NavigationMenu`; tautan breadcrumb "Fasilitas dan Kamar" memakai `Route::has()` dan jatuh ke `admin.facilities.index` (pola `admin/specialties/index.blade.php`). Setelah T5 tautan itu menuju `admin.room-categories.index` tanpa perubahan view.
8. **Penanda route.** T4 memasang blok fasilitas dan dua penanda sekaligus agar T5 dan T6 dapat mengedit teks lama yang persis (F4A pola penanda). Penanda berada langsung di dalam grup G-ADMIN (bukan di dalam grup `prefix`), sehingga barisnya berindentasi 4 spasi dan baris lanjutan di dalam `Route::middleware(...)->group(...)` berindentasi 8 spasi.
9. **Tes lama yang patah.** `Phase4aRoutePermissionTest::test_the_admin_area_has_ninety_routes_after_phase_4a` (menghitung semua `admin/*` = 90) patah begitu enam route fasilitas ada. Diganti tes yang menghitung 40 route `admin/konten` (F4B-R37). Angka total 106 dikunci `Phase4bRoutePermissionTest` di T11.
10. **Hapus selalu boleh.** Fasilitas tidak dirujuk tabel lain (CRD-13): tidak ada `deletionBlockedReason()`; tombol Hapus selalu tampil dan modal menyebut penghapusan permanen beserta gambar.
11. **Id input alt galeri.** `x-form.errors-summary` membentuk tautan dari kunci galat (`gallery_alt.0` menjadi `#gallery_alt-0`: titik diganti `-`, garis bawah dipertahankan). Karena itu input alt galeri berid `gallery_alt-{{ $i }}` (dan `for` labelnya sama), bukan `gallery-alt-N`, agar tautan ringkasan galat mengarah ke input.
12. **Tidak ada perubahan JS atau SCSS**, jadi tidak ada `npm run build`.

- [ ] **Step 1: Verifikasi prasyarat (T1, T2, T3)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git branch --show-current
git status --short
foreach ($p in 'app\Models\Facility.php', 'database\factories\FacilityFactory.php', 'tests\Support\InteractsWithFacilities.php', 'app\Support\LikePattern.php') { "{0} => {1}" -f $p, (Test-Path $p) }
Select-String -Path app\Policies\MediaPolicy.php -Pattern 'Facility::class' | Select-Object -ExpandProperty Line
Select-String -Path app\Providers\AppServiceProvider.php -Pattern 'Facility::observe' | Select-Object -ExpandProperty Line
Select-String -Path lang\id\messages.php -Pattern "'facility'" | Select-Object -ExpandProperty Line
Select-String -Path resources\views\components\form\file.blade.php -Pattern "'placeholder'" | Select-Object -ExpandProperty Line
Select-String -Path config\navigation.php -Pattern "'facilities' =>" | Select-Object -ExpandProperty Line
Select-String -Path routes\admin.php -Pattern 'use App\\Http\\Controllers\\Admin\\FaqController;', '// J. Media' | Select-Object -ExpandProperty Line
& $PHP artisan test tests\Feature\Facilities
& $PHP artisan test tests\Feature\Access
```
Expected: `fase-4b-fasilitas-kamar-kontak`; `git status` bersih (selain `.superpowers/`); keempat berkas `True`; `Facility::class` pada `MediaPolicy`, `Facility::observe(...)`, kunci `'facility'`, `'placeholder'`, dan `'facilities' =>` tercetak; kedua baris `routes/admin.php` tercetak; subset tes terfokus (`tests\Feature\Facilities`, `tests\Feature\Access`) PASS. Suite penuh tidak dijalankan di sini (±11–14 menit, melewati batas tool 10 menit); suite penuh ada di langkah akhir (latar belakang).

- [ ] **Step 2: Tulis tes yang gagal (tool Write) `tests/Feature/Admin/AdminFacilitiesTest.php`**

```php
<?php

namespace Tests\Feature\Admin;

use App\Enums\FacilityCategory;
use App\Models\Facility;
use App\Models\Media;
use App\Models\User;
use App\Services\Media\MediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ImageFixtures;
use Tests\Support\InteractsWithFacilities;
use Tests\Support\InteractsWithMedia;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * Admin #36 tab Fasilitas (T4): akses per peran (AKS-04), daftar dan filter (VAL-07, EMP-03), CRUD (CRD-06),
 * cover dan galeri (D-41, MED-05), unggahan berbahaya (SEK-09, SEK-10), sanitasi (SEK-08), dan hapus permanen (CRD-13).
 */
class AdminFacilitiesTest extends TestCase
{
    use InteractsWithFacilities;
    use InteractsWithMedia;
    use InteractsWithStaff;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Laboratorium Contoh',
            'category' => 'penunjang',
            'short_description' => 'Pemeriksaan laboratorium contoh untuk pengujian.',
            'description' => '<p>Deskripsi <strong>lengkap</strong> fasilitas.</p>',
            'is_active' => '1',
            'sort_order' => '3',
        ], $overrides);
    }

    private function attachCover(Facility $facility, string $alt = 'Foto sampul contoh'): Media
    {
        return app(MediaService::class)->store(ImageFixtures::jpeg(1200, 800, 'cover.jpg'), 'cover', $facility, ['alt_text' => $alt]);
    }

    private function attachGalleryImage(Facility $facility, string $alt): Media
    {
        return app(MediaService::class)->store(ImageFixtures::jpeg(900, 600, 'galeri.jpg'), 'gallery', $facility, ['alt_text' => $alt]);
    }

    // --- Akses (AKS-04) ------------------------------------------------------------------------------

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
        $this->createFacility(['name' => 'Fasilitas Akses']);

        $this->actingAs($this->createStaff($role))
            ->get(route('admin.facilities.index'))
            ->assertStatus($status);
    }

    public function test_patient_gets_403_and_guest_is_redirected_to_admin_login(): void
    {
        $this->actingAs(User::factory()->patient()->create())
            ->get(route('admin.facilities.index'))
            ->assertForbidden();

        $this->app['auth']->forgetGuards();

        $this->get(route('admin.facilities.index'))->assertRedirect(route('admin.login'));
    }

    public function test_urls_use_the_indonesian_segments(): void
    {
        $facility = $this->createFacility();

        $this->assertSame(url('/admin/fasilitas'), route('admin.facilities.index'));
        $this->assertSame(url('/admin/fasilitas/buat'), route('admin.facilities.create'));
        $this->assertSame(url('/admin/fasilitas/'.$facility->id.'/ubah'), route('admin.facilities.edit', $facility));
        $this->assertSame(url('/admin/fasilitas/'.$facility->id), route('admin.facilities.update', $facility));
    }

    public function test_there_is_no_show_route_for_facilities(): void
    {
        $this->assertFalse(Route::has('admin.facilities.show'));
    }

    // --- Daftar --------------------------------------------------------------------------------------

    public function test_index_renders_many_rows_with_category_cover_status_and_an_always_visible_delete_button(): void
    {
        $lab = $this->createFacility(['name' => 'Laboratorium Contoh', 'category' => FacilityCategory::Penunjang, 'sort_order' => 1]);
        $igd = $this->createFacility(['name' => 'IGD Contoh', 'category' => FacilityCategory::Medis, 'sort_order' => 2]);
        $parkir = $this->createFacility(['name' => 'Area Parkir Contoh', 'category' => FacilityCategory::Umum, 'sort_order' => 3, 'is_active' => false]);
        $this->createFacility(['name' => 'Musala Contoh', 'category' => FacilityCategory::Umum, 'sort_order' => 4]);

        $cover = $this->attachCover($lab, 'Foto laboratorium');

        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.facilities.index'))
            ->assertOk()
            ->assertSeeInOrder(['Laboratorium Contoh', 'IGD Contoh', 'Area Parkir Contoh', 'Musala Contoh'])
            ->assertSee('data-label="Kategori"><i class="bi bi-tools" aria-hidden="true"></i> Penunjang</td>', false)
            ->assertSee('data-label="Kategori"><i class="bi bi-heart-pulse" aria-hidden="true"></i> Fasilitas medis</td>', false)
            ->assertSee('data-label="Kategori"><i class="bi bi-building" aria-hidden="true"></i> Fasilitas umum</td>', false)
            ->assertSee('<span class="badge text-bg-success">Aktif</span>', false)
            ->assertSee('<span class="badge text-bg-secondary">Nonaktif</span>', false)
            ->assertSee((string) $cover->thumb_url, false)
            ->assertSee('images/placeholders/facility.svg', false)
            ->assertSee('data-confirm-action="'.route('admin.facilities.destroy', $lab).'"', false)
            ->assertSee('data-confirm-action="'.route('admin.facilities.destroy', $igd).'"', false)
            ->assertSee('data-confirm-action="'.route('admin.facilities.destroy', $parkir).'"', false)
            ->assertSee('href="'.route('admin.facilities.edit', $igd).'"', false);
    }

    public function test_index_shows_module_tab_breadcrumb_and_add_button(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.facilities.index'))
            ->assertOk()
            ->assertSee('href="'.route('admin.facilities.index').'"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('Fasilitas dan Kamar')
            ->assertSee('Tambah Fasilitas')
            ->assertSee('href="'.route('admin.facilities.create').'"', false)
            ->assertSee('id="modal-hapus-fasilitas"', false)
            ->assertSee('Hapus fasilitas?')
            ->assertSee('Fasilitas beserta gambarnya akan dihapus permanen.');
    }

    public function test_empty_list_shows_the_empty_state(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.facilities.index'))
            ->assertOk()
            ->assertSee('Belum ada fasilitas');
    }

    public function test_search_status_category_and_sort_filters(): void
    {
        $this->createFacility(['name' => 'Laboratorium Contoh', 'category' => FacilityCategory::Penunjang, 'sort_order' => 1]);
        $this->createFacility(['name' => 'IGD Contoh', 'category' => FacilityCategory::Medis, 'sort_order' => 2]);
        $this->createFacility(['name' => 'Musala Contoh', 'category' => FacilityCategory::Umum, 'sort_order' => 3, 'is_active' => false]);
        $this->actingAs($this->createStaff('content-admin'));

        $this->get(route('admin.facilities.index', ['q' => 'igd']))
            ->assertOk()->assertSee('IGD Contoh')->assertDontSee('Laboratorium Contoh');

        $this->get(route('admin.facilities.index', ['status' => 'nonaktif']))
            ->assertOk()->assertSee('Musala Contoh')->assertDontSee('IGD Contoh');

        $this->get(route('admin.facilities.index', ['status' => 'aktif']))
            ->assertOk()->assertSee('IGD Contoh')->assertDontSee('Musala Contoh');

        $this->get(route('admin.facilities.index', ['kategori' => 'penunjang']))
            ->assertOk()->assertSee('Laboratorium Contoh')->assertDontSee('IGD Contoh')->assertDontSee('Musala Contoh');

        $this->get(route('admin.facilities.index'))
            ->assertSeeInOrder(['Laboratorium Contoh', 'IGD Contoh', 'Musala Contoh']);

        $this->get(route('admin.facilities.index', ['urut' => 'nama']))
            ->assertSeeInOrder(['IGD Contoh', 'Laboratorium Contoh', 'Musala Contoh']);

        $this->get(route('admin.facilities.index', ['urut' => '-nama']))
            ->assertSeeInOrder(['Musala Contoh', 'Laboratorium Contoh', 'IGD Contoh']);
    }

    public function test_search_treats_percent_and_underscore_literally(): void
    {
        $this->createFacility(['name' => 'Diskon 100% Contoh']);
        $this->createFacility(['name' => 'Diskon 1000 Contoh']);
        $this->createFacility(['name' => 'Ruang_A Contoh']);
        $this->createFacility(['name' => 'RuangXA Contoh']);
        $this->actingAs($this->createStaff('content-admin'));

        $this->get(route('admin.facilities.index', ['q' => '100%']))
            ->assertOk()->assertSee('Diskon 100% Contoh')->assertDontSee('Diskon 1000 Contoh');

        $this->get(route('admin.facilities.index', ['q' => 'Ruang_A']))
            ->assertOk()->assertSee('Ruang_A Contoh')->assertDontSee('RuangXA Contoh');
    }

    public function test_invalid_filters_are_ignored_and_array_params_do_not_cause_a_server_error(): void
    {
        $this->createFacility(['name' => 'Laboratorium Contoh']);
        $this->actingAs($this->createStaff('content-admin'));

        $this->get(route('admin.facilities.index', ['status' => 'zzz', 'kategori' => 'vip', 'urut' => 'zzz', 'kembali' => 'https://situs-lain.test']))
            ->assertOk()
            ->assertSee('Filter tidak valid dan diabaikan.')
            ->assertSee('Laboratorium Contoh')
            ->assertDontSee('situs-lain.test');

        $this->get(route('admin.facilities.index', ['urut' => ['x'], 'status' => ['x'], 'q' => ['x'], 'kategori' => ['x']]))
            ->assertOk();
    }

    // --- Form dan CRUD (CRD-06) ----------------------------------------------------------------------

    public function test_create_form_has_the_facility_fields_cover_gallery_and_no_slug_field(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.facilities.create'))
            ->assertOk()
            ->assertSee('Tambah Fasilitas')
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('action="'.route('admin.facilities.store').'"', false)
            ->assertSee('name="name"', false)
            ->assertSee('name="category"', false)
            ->assertSee('value="medis"', false)
            ->assertSee('value="penunjang"', false)
            ->assertSee('value="umum"', false)
            ->assertSee('name="short_description"', false)
            ->assertSee('data-rich-text', false)
            ->assertSee('name="is_active"', false)
            ->assertSee('name="sort_order"', false)
            ->assertSee('name="meta_title"', false)
            ->assertSee('name="meta_description"', false)
            ->assertSee('name="cover"', false)
            ->assertSee('name="cover_alt"', false)
            ->assertSee('name="gallery[0]"', false)
            ->assertSee('name="gallery_alt[0]"', false)
            ->assertDontSee('name="slug"', false);
    }

    public function test_content_admin_adds_a_facility_without_images_crd06(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.facilities.store'), $this->payload())
            ->assertRedirect(route('admin.facilities.index'))
            ->assertSessionHas('success', 'Fasilitas berhasil ditambahkan.');

        $facility = Facility::query()->sole();

        $this->assertSame('Laboratorium Contoh', $facility->name);
        $this->assertSame('laboratorium-contoh', $facility->slug);
        $this->assertSame(FacilityCategory::Penunjang, $facility->category);
        $this->assertSame('Pemeriksaan laboratorium contoh untuk pengujian.', $facility->short_description);
        $this->assertStringContainsString('<strong>lengkap</strong>', (string) $facility->description);
        $this->assertTrue($facility->is_active);
        $this->assertSame(3, $facility->sort_order);
        $this->assertNull($facility->meta_title);
        $this->assertNull($facility->meta_description);
        $this->assertSame(0, Media::query()->count());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'facility.created',
            'description' => 'Fasilitas "Laboratorium Contoh" ditambahkan.',
        ]);
    }

    public function test_optional_fields_may_be_left_empty_and_every_category_is_accepted(): void
    {
        $this->actingAs($this->createStaff('content-admin'));

        foreach (['medis' => 'IGD Contoh', 'penunjang' => 'Radiologi Contoh', 'umum' => 'Musala Contoh'] as $category => $name) {
            $this->post(route('admin.facilities.store'), $this->payload([
                'name' => $name,
                'category' => $category,
                'description' => '',
                'sort_order' => '',
                'meta_title' => '',
                'meta_description' => '',
            ]))->assertSessionHasNoErrors();
        }

        $this->assertSame(3, Facility::query()->count());

        $facility = Facility::query()->where('name', 'IGD Contoh')->sole();
        $this->assertSame(FacilityCategory::Medis, $facility->category);
        $this->assertNull($facility->description);
        $this->assertSame(0, $facility->sort_order);
        $this->assertNull($facility->meta_title);
    }

    public function test_description_is_sanitized_when_saved_through_the_form_sek08(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.facilities.store'), $this->payload([
                'description' => '<p>Aman</p><script>alert(1)</script><img src="x" onerror="alert(1)"><a href="javascript:alert(1)">klik</a><iframe src="https://evil.test"></iframe>',
            ]))
            ->assertRedirect(route('admin.facilities.index'));

        $description = (string) Facility::query()->sole()->description;

        $this->assertStringContainsString('Aman', $description);
        $this->assertStringNotContainsString('<script', $description);
        $this->assertStringNotContainsString('onerror', $description);
        $this->assertStringNotContainsString('javascript:', $description);
        $this->assertStringNotContainsString('<iframe', $description);
    }

    public function test_edit_form_shows_values_the_selected_category_and_gallery_management_forms(): void
    {
        $facility = $this->createFacility(['name' => 'Laboratorium Contoh', 'category' => FacilityCategory::Penunjang, 'short_description' => 'Ringkasan laboratorium.', 'sort_order' => 5]);
        $first = $this->createMediaRow(['model_type' => Facility::class, 'model_id' => $facility->id, 'collection' => 'gallery', 'alt_text' => 'Foto pertama', 'sort_order' => 1]);
        $second = $this->createMediaRow(['model_type' => Facility::class, 'model_id' => $facility->id, 'collection' => 'gallery', 'alt_text' => 'Foto kedua', 'sort_order' => 2]);

        $this->actingAs($this->createStaff('super-admin'))
            ->get(route('admin.facilities.edit', $facility))
            ->assertOk()
            ->assertSee('Ubah Laboratorium Contoh')
            ->assertSee('action="'.route('admin.facilities.update', $facility).'"', false)
            ->assertSee('value="Laboratorium Contoh"', false)
            ->assertSee('value="penunjang" selected', false)
            ->assertSee('Ringkasan laboratorium.')
            ->assertSee('value="5"', false)
            ->assertSee('Galeri (2 dari 20 foto)')
            ->assertSee('Foto pertama')
            ->assertSee('Foto kedua')
            ->assertSee('action="'.route('admin.media.update', $first).'"', false)
            ->assertSee('action="'.route('admin.media.reorder').'"', false)
            ->assertSee('data-confirm-action="'.route('admin.media.destroy', $second).'"', false);
    }

    public function test_update_changes_fields_keeps_the_slug_and_allows_the_unchanged_name(): void
    {
        $facility = $this->createFacility(['name' => 'Laboratorium Contoh', 'category' => FacilityCategory::Penunjang]);
        $slug = $facility->slug;
        $this->actingAs($this->createStaff('content-admin'));

        $this->put(route('admin.facilities.update', $facility), $this->payload(['short_description' => 'Ringkasan baru.']))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.facilities.index'))
            ->assertSessionHas('success', 'Fasilitas berhasil diperbarui.');

        $this->assertSame('Ringkasan baru.', $facility->fresh()->short_description);

        $this->put(route('admin.facilities.update', $facility), $this->payload([
            'name' => 'Nama Sudah Berbeda',
            'category' => 'umum',
        ]))->assertSessionHasNoErrors();

        $facility->refresh();
        $this->assertSame('Nama Sudah Berbeda', $facility->name);
        $this->assertSame($slug, $facility->slug);
        $this->assertSame(FacilityCategory::Umum, $facility->category);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'facility.updated',
            'description' => 'Fasilitas "Nama Sudah Berbeda" diperbarui.',
        ]);
    }

    public function test_a_facility_is_deactivated_through_the_edit_form_and_leaves_the_active_scope(): void
    {
        $facility = $this->createFacility(['name' => 'Laboratorium Contoh', 'is_active' => true]);
        $this->createFacility(['name' => 'Musala Contoh']);

        $this->actingAs($this->createStaff('content-admin'))
            ->put(route('admin.facilities.update', $facility), $this->payload(['is_active' => '0']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('facilities', ['id' => $facility->id, 'is_active' => 0]);
        $this->assertSame(['Musala Contoh'], Facility::query()->active()->pluck('name')->all());
    }

    // --- Cover dan galeri (D-41, MED-05) -------------------------------------------------------------

    public function test_cover_upload_on_create_stores_media_for_the_facility(): void
    {
        $admin = $this->createStaff('content-admin');

        $this->actingAs($admin)
            ->post(route('admin.facilities.store'), $this->payload([
                'cover' => ImageFixtures::jpeg(1200, 800, 'cover.jpg'),
                'cover_alt' => 'Foto sampul laboratorium',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.facilities.index'));

        $facility = Facility::query()->sole();
        $media = Media::query()->where('collection', 'cover')->sole();

        $this->assertSame(Facility::class, $media->model_type);
        $this->assertSame($facility->id, $media->model_id);
        $this->assertSame('Foto sampul laboratorium', $media->alt_text);
        $this->assertSame($admin->id, $media->uploaded_by);
        $this->assertStringStartsWith('cover/'.now()->format('Y/m'), $media->directory);
        Storage::disk('public')->assertExists($media->directory.'/'.$media->file_name);
        Storage::disk('public')->assertExists($media->thumb_path);
    }

    public function test_replacing_the_cover_removes_the_old_file_after_commit(): void
    {
        $facility = $this->createFacility(['name' => 'Laboratorium Contoh']);
        $old = $this->attachCover($facility, 'Sampul lama');
        $oldPath = $old->directory.'/'.$old->file_name;

        $this->actingAs($this->createStaff('content-admin'))
            ->put(route('admin.facilities.update', $facility), $this->payload([
                'cover' => ImageFixtures::jpeg(1000, 700, 'baru.jpg'),
                'cover_alt' => 'Sampul baru',
            ]))
            ->assertSessionHasNoErrors();

        $new = Media::query()->where('collection', 'cover')->sole();

        $this->assertNotSame($old->id, $new->id);
        $this->assertSame('Sampul baru', $new->alt_text);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($new->directory.'/'.$new->file_name);
    }

    public function test_update_without_a_new_cover_keeps_it_and_a_changed_alt_updates_the_existing_cover(): void
    {
        $facility = $this->createFacility(['name' => 'Laboratorium Contoh']);
        $cover = $this->attachCover($facility, 'Sampul lama');
        $this->actingAs($this->createStaff('content-admin'));

        $this->put(route('admin.facilities.update', $facility), $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertSame($cover->id, Media::query()->where('collection', 'cover')->sole()->id);

        $this->put(route('admin.facilities.update', $facility), $this->payload(['cover_alt' => '  Sampul diperbarui  ']))
            ->assertSessionHasNoErrors();

        $same = Media::query()->where('collection', 'cover')->sole();
        $this->assertSame($cover->id, $same->id);
        $this->assertSame('Sampul diperbarui', $same->alt_text);
        Storage::disk('public')->assertExists($same->directory.'/'.$same->file_name);
    }

    public function test_an_empty_cover_alt_is_rejected_while_a_cover_exists_and_keeps_the_old_alt(): void
    {
        $facility = $this->createFacility(['name' => 'Laboratorium Contoh']);
        $this->attachCover($facility, 'Sampul lama');

        $this->actingAs($this->createStaff('content-admin'))
            ->put(route('admin.facilities.update', $facility), $this->payload(['name' => 'Nama Tidak Tersimpan', 'cover_alt' => '  ']))
            ->assertSessionHasErrors(['cover_alt' => 'Teks alternatif gambar wajib diisi.']);

        $this->assertSame('Sampul lama', Media::query()->where('collection', 'cover')->sole()->alt_text);
        $this->assertSame('Laboratorium Contoh', $facility->fresh()->name);
    }

    public function test_an_empty_cover_alt_without_a_cover_is_not_an_error(): void
    {
        $facility = $this->createFacility(['name' => 'Laboratorium Contoh']);

        $this->actingAs($this->createStaff('content-admin'))
            ->put(route('admin.facilities.update', $facility), $this->payload(['cover_alt' => '']))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, Media::query()->count());
    }

    public function test_gallery_images_are_added_in_input_order_with_their_own_alt_text(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.facilities.store'), $this->payload([
                'gallery' => [0 => ImageFixtures::jpeg(900, 600, 'g1.jpg'), 2 => ImageFixtures::jpeg(900, 600, 'g2.jpg')],
                'gallery_alt' => [0 => 'Ruang pemeriksaan', 1 => '', 2 => 'Ruang tunggu'],
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.facilities.index'));

        $this->assertSame(
            ['Ruang pemeriksaan', 'Ruang tunggu'],
            Media::query()->where('collection', 'gallery')->orderBy('sort_order')->orderBy('id')->pluck('alt_text')->all(),
        );
    }

    public function test_a_gallery_image_without_alt_text_is_rejected_and_nothing_is_stored(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.facilities.store'), $this->payload([
                'gallery' => [0 => ImageFixtures::jpeg(900, 600)],
                'gallery_alt' => [0 => ''],
            ]))
            ->assertSessionHasErrors(['gallery_alt.0' => 'Teks alternatif gambar wajib diisi.']);

        $this->assertSame(0, Facility::query()->count());
        $this->assertSame(0, Media::query()->count());
    }

    /**
     * @return array<string, array{int, int}>
     */
    public static function galleryLimits(): array
    {
        return [
            '19 yang ada ditambah 2 baru' => [19, 2],
            '20 yang ada ditambah 1 baru' => [20, 1],
        ];
    }

    #[DataProvider('galleryLimits')]
    public function test_the_gallery_is_limited_to_20_images_including_existing_ones(int $existing, int $incoming): void
    {
        $facility = $this->createFacility(['name' => 'Laboratorium Contoh']);

        foreach (range(1, $existing) as $position) {
            $this->createMediaRow([
                'model_type' => Facility::class,
                'model_id' => $facility->id,
                'collection' => 'gallery',
                'sort_order' => $position,
            ]);
        }

        $files = [];
        $alts = [];

        foreach (range(0, $incoming - 1) as $index) {
            $files[$index] = ImageFixtures::jpeg(900, 600);
            $alts[$index] = 'Foto baru '.($index + 1);
        }

        $this->actingAs($this->createStaff('content-admin'))
            ->put(route('admin.facilities.update', $facility), $this->payload(['gallery' => $files, 'gallery_alt' => $alts]))
            ->assertSessionHasErrors(['gallery' => 'Galeri maksimal 20 gambar.']);

        $this->assertSame($existing, Media::query()->where('collection', 'gallery')->count());
    }

    public function test_hostile_and_undersized_uploads_are_rejected_without_storing_anything_sek09_sek10(): void
    {
        $this->actingAs($this->createStaff('content-admin'));

        $hostile = [
            'svg berskrip' => ImageFixtures::svgWithScript(),
            'php berkepala jpeg' => ImageFixtures::phpWithJpegHeader(),
            'teks bernama jpg' => ImageFixtures::textNamedJpg(),
            'sampul terlalu kecil' => ImageFixtures::jpeg(799, 449, 'kecil.jpg'),
        ];

        foreach ($hostile as $file) {
            $this->post(route('admin.facilities.store'), $this->payload(['cover' => $file, 'cover_alt' => 'x']))
                ->assertSessionHasErrors('cover');
        }

        $this->post(route('admin.facilities.store'), $this->payload([
            'gallery' => [0 => ImageFixtures::svgWithScript('galeri.svg'), 1 => ImageFixtures::phpWithJpegHeader('galeri.php.jpg')],
            'gallery_alt' => [0 => 'x', 1 => 'y'],
        ]))->assertSessionHasErrors(['gallery.0', 'gallery.1']);

        $this->assertSame(0, Facility::query()->count());
        $this->assertSame(0, Media::query()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_content_admin_manages_facility_gallery_images_through_the_media_endpoints(): void
    {
        $facility = $this->createFacility(['name' => 'Laboratorium Contoh']);
        $a = $this->attachGalleryImage($facility, 'Foto A');
        $b = $this->attachGalleryImage($facility, 'Foto B');
        $editUrl = route('admin.facilities.edit', $facility);
        $this->actingAs($this->createStaff('content-admin'));

        $this->from($editUrl)
            ->patch(route('admin.media.update', $a), ['alt_text' => 'Foto A baru'])
            ->assertRedirect($editUrl)
            ->assertSessionHas('success', 'Gambar berhasil diperbarui.');
        $this->assertSame('Foto A baru', $a->fresh()->alt_text);

        $this->from($editUrl)
            ->post(route('admin.media.reorder'), ['ids' => [$b->id, $a->id]])
            ->assertRedirect($editUrl);
        $this->assertSame(
            [$b->id, $a->id],
            Media::query()->where('collection', 'gallery')->orderBy('sort_order')->pluck('id')->all(),
        );

        $path = $a->directory.'/'.$a->file_name;
        $this->from($editUrl)
            ->delete(route('admin.media.destroy', $a))
            ->assertRedirect($editUrl);
        $this->assertDatabaseMissing('media', ['id' => $a->id]);
        Storage::disk('public')->assertMissing($path);
        $this->assertDatabaseHas('media', ['id' => $b->id]);
    }

    // --- Hapus permanen (CRD-13) ---------------------------------------------------------------------

    public function test_deleting_a_facility_removes_its_media_files_crd13(): void
    {
        $admin = $this->createStaff('content-admin');
        $this->actingAs($admin)->post(route('admin.facilities.store'), $this->payload([
            'cover' => ImageFixtures::jpeg(1200, 800),
            'cover_alt' => 'Sampul',
            'gallery' => [0 => ImageFixtures::jpeg(900, 600)],
            'gallery_alt' => [0 => 'Galeri'],
        ]));
        $facility = Facility::query()->sole();
        $paths = Media::query()->get()->flatMap(fn (Media $media): array => [$media->directory.'/'.$media->file_name, $media->thumb_path])->all();
        $this->assertCount(4, $paths);

        $this->delete(route('admin.facilities.destroy', $facility))
            ->assertRedirect(route('admin.facilities.index'))
            ->assertSessionHas('success', 'Fasilitas berhasil dihapus.');

        $this->assertDatabaseMissing('facilities', ['id' => $facility->id]);
        $this->assertSame(0, Media::query()->count());

        foreach ($paths as $path) {
            Storage::disk('public')->assertMissing($path);
        }

        $this->assertDatabaseHas('audit_logs', ['action' => 'facility.deleted']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function deleters(): array
    {
        return ['admin konten' => ['content-admin'], 'super admin' => ['super-admin']];
    }

    #[DataProvider('deleters')]
    public function test_a_facility_can_always_be_deleted_by_a_manager(string $role): void
    {
        $facility = $this->createFacility(['name' => 'Fasilitas Dihapus']);

        $this->actingAs($this->createStaff($role))
            ->delete(route('admin.facilities.destroy', $facility))
            ->assertRedirect(route('admin.facilities.index'))
            ->assertSessionHas('success', 'Fasilitas berhasil dihapus.');

        $this->assertDatabaseMissing('facilities', ['id' => $facility->id]);
    }

    // --- Rute dan penolakan ---------------------------------------------------------------------------

    public function test_non_numeric_id_is_a_404(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get('/admin/fasilitas/abc/ubah')
            ->assertNotFound();
    }

    public function test_registration_officer_patient_and_guest_cannot_use_facility_routes(): void
    {
        $facility = $this->createFacility(['name' => 'Fasilitas Aman']);

        $requests = [
            ['GET', route('admin.facilities.create'), []],
            ['POST', route('admin.facilities.store'), $this->payload(['name' => 'Tidak Boleh'])],
            ['GET', route('admin.facilities.edit', $facility), []],
            ['PUT', route('admin.facilities.update', $facility), $this->payload(['name' => 'Diubah Paksa'])],
            ['DELETE', route('admin.facilities.destroy', $facility), []],
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

        $this->assertSame('Fasilitas Aman', $facility->fresh()->name);
        $this->assertDatabaseMissing('facilities', ['name' => 'Tidak Boleh']);
        $this->assertDatabaseHas('facilities', ['id' => $facility->id]);
    }

    public function test_registration_officer_cannot_change_facility_images_through_the_media_endpoints(): void
    {
        $facility = $this->createFacility(['name' => 'Laboratorium Contoh']);
        $image = $this->attachGalleryImage($facility, 'Foto tetap');
        $this->actingAs($this->createStaff('registration-officer'));

        $this->patch(route('admin.media.update', $image), ['alt_text' => 'Diubah paksa'])->assertForbidden();
        $this->post(route('admin.media.reorder'), ['ids' => [$image->id]])->assertForbidden();
        $this->delete(route('admin.media.destroy', $image))->assertForbidden();

        $this->assertSame('Foto tetap', $image->fresh()->alt_text);
        $this->assertDatabaseHas('media', ['id' => $image->id]);
    }
}
```

- [ ] **Step 3: Tulis tes yang gagal (tool Write) `tests/Feature/Admin/FacilityRequestTest.php`**

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\Facility;
use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ImageFixtures;
use Tests\Support\InteractsWithFacilities;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * 12 V-27 (dengan F4B-R3, F4B-R4, F4B-R5, F4B-R21): pesan Indonesia per field untuk form fasilitas, tambah dan ubah.
 */
class FacilityRequestTest extends TestCase
{
    use InteractsWithFacilities;
    use InteractsWithStaff;
    use RefreshDatabase;

    /**
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function invalidInputs(): array
    {
        return [
            'nama kosong' => [['name' => ''], 'name', 'Nama fasilitas wajib diisi.'],
            'nama bukan teks' => [['name' => ['a']], 'name', 'Nama fasilitas harus berupa teks.'],
            'nama terlalu pendek' => [['name' => 'Ab'], 'name', 'Nama fasilitas minimal 3 karakter.'],
            'nama terlalu panjang' => [['name' => str_repeat('a', 101)], 'name', 'Nama fasilitas maksimal 100 karakter.'],
            'nama ganda' => [['name' => 'Laboratorium Contoh'], 'name', 'Nama fasilitas sudah digunakan.'],
            'nama ganda beda huruf besar kecil' => [['name' => 'LABORATORIUM CONTOH'], 'name', 'Nama fasilitas sudah digunakan.'],
            'kategori kosong' => [['category' => ''], 'category', 'Kategori fasilitas wajib dipilih.'],
            'kategori tidak dikenal' => [['category' => 'vip'], 'category', 'Kategori fasilitas tidak valid.'],
            'kategori berupa larik' => [['category' => ['medis']], 'category', 'Kategori fasilitas tidak valid.'],
            'deskripsi singkat kosong' => [['short_description' => ''], 'short_description', 'Deskripsi singkat wajib diisi.'],
            'deskripsi singkat bukan teks' => [['short_description' => ['a']], 'short_description', 'Deskripsi singkat harus berupa teks.'],
            'deskripsi singkat terlalu panjang' => [['short_description' => str_repeat('a', 256)], 'short_description', 'Deskripsi singkat maksimal 255 karakter.'],
            'deskripsi bukan teks' => [['description' => ['a']], 'description', 'Deskripsi harus berupa teks.'],
            'deskripsi terlalu panjang' => [['description' => str_repeat('a', 30001)], 'description', 'Deskripsi maksimal 30.000 karakter.'],
            'status aktif hilang' => [['is_active' => null], 'is_active', 'Status aktif tidak valid.'],
            'status aktif tidak valid' => [['is_active' => 'mungkin'], 'is_active', 'Status aktif tidak valid.'],
            'urutan bukan angka' => [['sort_order' => 'abc'], 'sort_order', 'Urutan harus berupa angka bulat.'],
            'urutan terlalu besar' => [['sort_order' => '10000'], 'sort_order', 'Urutan harus antara 0 dan 9999.'],
            'urutan negatif' => [['sort_order' => '-1'], 'sort_order', 'Urutan harus antara 0 dan 9999.'],
            'meta title terlalu panjang' => [['meta_title' => str_repeat('a', 71)], 'meta_title', 'Meta title maksimal 70 karakter.'],
            'meta description terlalu panjang' => [['meta_description' => str_repeat('a', 161)], 'meta_description', 'Meta description maksimal 160 karakter.'],
            'alt sampul wajib' => [['cover' => 'FILE', 'cover_alt' => ''], 'cover_alt', 'Teks alternatif gambar wajib diisi.'],
            'alt sampul terlalu panjang' => [['cover' => 'FILE', 'cover_alt' => str_repeat('a', 256)], 'cover_alt', 'Teks alternatif gambar maksimal 255 karakter.'],
            'sampul terlalu kecil' => [['cover' => 'SMALL', 'cover_alt' => 'x'], 'cover', 'Dimensi gambar minimal 800 x 450 piksel dan maksimal 6000 x 6000 piksel.'],
            'sampul terlalu besar' => [['cover' => 'BIG', 'cover_alt' => 'x'], 'cover', 'Ukuran gambar maksimal 2 MB.'],
            'galeri terlalu kecil' => [['gallery' => ['SMALL'], 'gallery_alt' => ['x']], 'gallery.0', 'Dimensi gambar minimal 600 x 400 piksel dan maksimal 6000 x 6000 piksel.'],
            'alt galeri wajib' => [['gallery' => ['GALLERY'], 'gallery_alt' => ['']], 'gallery_alt.0', 'Teks alternatif gambar wajib diisi.'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function valid(): array
    {
        return [
            'name' => 'Fasilitas Baru Contoh',
            'category' => 'medis',
            'short_description' => 'Ringkasan fasilitas.',
            'is_active' => '1',
        ];
    }

    /**
     * Penanda berkas diubah menjadi UploadedFile di dalam tes (data provider dijalankan sebelum GD siap dipakai).
     */
    private function materialize(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($item) => $this->materialize($item), $value);
        }

        return match ($value) {
            'FILE' => ImageFixtures::jpeg(1200, 800),
            'SMALL' => ImageFixtures::jpeg(400, 300),
            'GALLERY' => ImageFixtures::jpeg(900, 600),
            'BIG' => ImageFixtures::oversizedJpeg(2100, 1200, 800),
            default => $value,
        };
    }

    /**
     * @param  array<string, mixed>  $override
     */
    #[DataProvider('invalidInputs')]
    public function test_store_rejects_invalid_input_with_indonesian_messages(array $override, string $field, string $message): void
    {
        Storage::fake('public');
        $this->createFacility(['name' => 'Laboratorium Contoh']);
        $override = array_map(fn ($value) => $this->materialize($value), $override);

        $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.facilities.create'))
            ->post(route('admin.facilities.store'), array_merge($this->valid(), $override))
            ->assertRedirect(route('admin.facilities.create'))
            ->assertSessionHasErrors([$field => $message]);

        $this->assertSame(1, Facility::query()->count());
        $this->assertSame(0, Media::query()->count());
    }

    /**
     * @param  array<string, mixed>  $override
     */
    #[DataProvider('invalidInputs')]
    public function test_update_applies_the_same_rules_and_keeps_the_old_row(array $override, string $field, string $message): void
    {
        Storage::fake('public');
        $this->createFacility(['name' => 'Laboratorium Contoh']);
        $target = $this->createFacility(['name' => 'Fasilitas Sasaran']);
        $override = array_map(fn ($value) => $this->materialize($value), $override);

        $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.facilities.edit', $target))
            ->put(route('admin.facilities.update', $target), array_merge($this->valid(), $override))
            ->assertRedirect(route('admin.facilities.edit', $target))
            ->assertSessionHasErrors([$field => $message]);

        $this->assertSame('Fasilitas Sasaran', $target->fresh()->name);
        $this->assertSame(0, Media::query()->count());
    }

    public function test_the_slug_field_is_not_accepted_from_the_form(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.facilities.store'), array_merge($this->valid(), ['slug' => 'slug-dari-form']))
            ->assertSessionHasNoErrors();

        $this->assertSame('fasilitas-baru-contoh', Facility::query()->sole()->slug);
    }

    public function test_invalid_form_keeps_old_input_and_shows_an_error_summary(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.facilities.create'))
            ->post(route('admin.facilities.store'), array_merge($this->valid(), ['name' => '', 'short_description' => 'Ringkasan Lama']))
            ->assertRedirect(route('admin.facilities.create'));

        $this->get(route('admin.facilities.create'))
            ->assertOk()
            ->assertSee('data-errors-summary', false)
            ->assertSee('Nama fasilitas wajib diisi.')
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('name-error', false)
            ->assertSee('Ringkasan Lama');
    }
}
```

- [ ] **Step 4: Edit tes lama `tests/Feature/Access/Phase4aRoutePermissionTest.php` (F4B-R37)**

Edit `tests/Feature/Access/Phase4aRoutePermissionTest.php`: ganti blok berikut (persis; muncul tepat satu kali):

```php
    public function test_the_admin_area_has_ninety_routes_after_phase_4a(): void
    {
        $count = 0;

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if ($route->uri() === 'admin' || str_starts_with($route->uri(), 'admin/')) {
                $count++;
            }
        }

        // 31 fase 1-2 + 19 fase 3 + 40 fase 4a (F4A-R28); fase 4b dan seterusnya wajib memperbarui angka ini.
        $this->assertSame(90, $count);
    }
```

dengan:

```php
    public function test_the_content_area_has_forty_routes(): void
    {
        $count = 0;

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (str_starts_with($route->uri(), 'admin/konten/')) {
                $count++;
            }
        }

        // 40 route fase 4a (F4A-R28). Total seluruh area admin dikunci Phase4bRoutePermissionTest (F4B-R37).
        $this->assertSame(40, $count);
    }
```

- [ ] **Step 5: Jalankan tes, harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=AdminFacilitiesTest
& $PHP artisan test --filter=FacilityRequestTest
& $PHP artisan test --filter=Phase4aRoutePermissionTest
```
Expected: `AdminFacilitiesTest` dan `FacilityRequestTest` FAIL dengan `Route [admin.facilities.index] not defined.` (atau `…create`/`…store` not defined; tes yang memakai URL literal gagal dengan 404). `Phase4aRoutePermissionTest` PASS (tes pengganti sudah benar sebelum route fasilitas ada, karena hanya menghitung `admin/konten/`).

- [ ] **Step 6: Implementasi `FacilityPolicy` (tool Write) `app/Policies/FacilityPolicy.php`**

```php
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Facility;
use App\Models\User;

/**
 * 04 §5 / 11 §8 (F4B-R15): seluruh kemampuan atas fasilitas memerlukan izin `facilities.manage`.
 * Fasilitas tidak dirujuk tabel lain sehingga tidak ada penolakan hapus (CRD-13).
 */
final class FacilityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('facilities.manage');
    }

    public function view(User $user, Facility $facility): bool
    {
        return $user->can('facilities.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('facilities.manage');
    }

    public function update(User $user, Facility $facility): bool
    {
        return $user->can('facilities.manage');
    }

    public function delete(User $user, Facility $facility): bool
    {
        return $user->can('facilities.manage');
    }
}
```

- [ ] **Step 7: Implementasi `FacilityRequest` (tool Write) `app/Http/Requests/Admin/FacilityRequest.php`**

```php
<?php

namespace App\Http\Requests\Admin;

use App\Enums\FacilityCategory;
use App\Models\Facility;
use App\Rules\ImageRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * V-27 + F4B-R3 (description max 30000), F4B-R4 (panjang field), F4B-R5 (tanpa input slug), F4B-R21 (cover dan galeri).
 * Satu kelas untuk tambah dan ubah, dibedakan dari `$this->route('facility')`.
 */
class FacilityRequest extends FormRequest
{
    public const MAX_GALLERY = 20;

    public function authorize(): bool
    {
        $user = $this->user();
        $facility = $this->route('facility');

        if ($user === null) {
            return false;
        }

        return $facility instanceof Facility
            ? $user->can('update', $facility)
            : $user->can('create', Facility::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $facility = $this->route('facility');
        $facility = $facility instanceof Facility ? $facility : null;

        $rules = [
            'name' => ['bail', 'required', 'string', 'min:3', 'max:100', Rule::unique('facilities', 'name')->ignore($facility)],
            'category' => ['bail', 'required', Rule::enum(FacilityCategory::class)],
            'short_description' => ['bail', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:30000'],
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
                'name.required' => 'Nama fasilitas wajib diisi.',
                'name.string' => 'Nama fasilitas harus berupa teks.',
                'name.min' => 'Nama fasilitas minimal 3 karakter.',
                'name.max' => 'Nama fasilitas maksimal 100 karakter.',
                'name.unique' => 'Nama fasilitas sudah digunakan.',
                'category.required' => 'Kategori fasilitas wajib dipilih.',
                'category.enum' => 'Kategori fasilitas tidak valid.',
                'short_description.required' => 'Deskripsi singkat wajib diisi.',
                'short_description.string' => 'Deskripsi singkat harus berupa teks.',
                'short_description.max' => 'Deskripsi singkat maksimal 255 karakter.',
                'description.string' => 'Deskripsi harus berupa teks.',
                'description.max' => 'Deskripsi maksimal 30.000 karakter.',
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
     * Batas 20 gambar berlaku untuk jumlah yang sudah ada ditambah yang baru diunggah (F4B-R21).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateExistingCoverAlt($validator);

            $incoming = count($this->galleryFiles());

            if ($incoming === 0 || $validator->errors()->has('gallery')) {
                return;
            }

            $facility = $this->route('facility');
            $existing = $facility instanceof Facility ? $facility->media()->where('collection', 'gallery')->count() : 0;

            if ($existing + $incoming > self::MAX_GALLERY) {
                $validator->errors()->add('gallery', 'Galeri maksimal 20 gambar.');
            }
        });
    }

    /**
     * Alt cover wajib untuk koleksi publik (BR-132): bila cover sudah ada dan form mengirim alt kosong tanpa berkas
     * baru, tolak (alt lama tetap). Dengan berkas baru, `required_with:cover` sudah menjaganya.
     */
    private function validateExistingCoverAlt(Validator $validator): void
    {
        $facility = $this->route('facility');

        if (! $facility instanceof Facility || $this->coverFile() !== null || ! $this->has('cover_alt') || $this->coverAlt() !== '') {
            return;
        }

        if ($facility->media()->where('collection', 'cover')->exists()) {
            $validator->errors()->add('cover_alt', 'Teks alternatif gambar wajib diisi.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function facilityAttributes(): array
    {
        $data = $this->validated();

        return [
            'name' => (string) $data['name'],
            'category' => (string) $data['category'],
            'short_description' => (string) $data['short_description'],
            'description' => $data['description'] ?? null,
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
}
```

- [ ] **Step 8: Implementasi `FacilityIndexRequest` (tool Write) `app/Http/Requests/Admin/FacilityIndexRequest.php`**

```php
<?php

namespace App\Http\Requests\Admin;

use App\Enums\FacilityCategory;
use App\Http\Requests\ListFilterRequest;
use App\Support\LikePattern;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Filter daftar fasilitas (F4B-R22): `q` pada `name`, `status` aktif/nonaktif, `kategori` salah satu FacilityCategory,
 * dan `urut` urutan/nama (awalan "-" = menurun). Parameter yang tidak valid diabaikan oleh basis (12 V-45).
 */
class FacilityIndexRequest extends ListFilterRequest
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
        return ['urutan', 'nama'];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'kategori' => ['nullable', Rule::in(FacilityCategory::values())],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'kategori.in' => 'Kategori tidak valid.',
        ]);
    }

    /**
     * Tanpa `urut` dipakai scope ordered() (sort_order, name, id) lalu id. Karakter `%`, `_`, dan `\` pada kata kunci
     * di-escape oleh LikePattern.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function applyTo(Builder $query): Builder
    {
        $filters = $this->filters();
        $model = $query->getModel();

        if (isset($filters['q'])) {
            $query->where($model->qualifyColumn('name'), 'like', LikePattern::contains($filters['q']));
        }

        $status = $filters['status'] ?? null;

        if ($status === 'aktif' || $status === 'nonaktif') {
            $query->where($model->qualifyColumn('is_active'), $status === 'aktif');
        }

        $category = isset($filters['kategori']) ? FacilityCategory::tryFrom($filters['kategori']) : null;

        if ($category !== null) {
            $query->where($model->qualifyColumn('category'), $category->value);
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
            ->orderBy($model->qualifyColumn('id'), $direction);
    }
}
```

- [ ] **Step 9: Implementasi `FacilityController` (tool Write) `app/Http/Controllers/Admin/FacilityController.php`**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FacilityIndexRequest;
use App\Http\Requests\Admin\FacilityRequest;
use App\Models\Facility;
use App\Services\Media\MediaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Admin #36 tab Fasilitas (F-ADM-09). Pola poliklinik fase 2: `cover` tunggal dan `gallery` banyak lewat MediaService.
 * Fasilitas tidak dirujuk tabel lain, jadi hapus selalu diizinkan dan media ikut terhapus (CRD-13, F4B-R20).
 */
class FacilityController extends Controller
{
    public function index(FacilityIndexRequest $request): View
    {
        $this->authorize('viewAny', Facility::class);

        $facilities = $request
            ->applyTo(Facility::query()->with('media'))
            ->paginate($request->perPage());

        return view('admin.facilities.index', ['facilities' => $facilities]);
    }

    public function create(): View
    {
        $this->authorize('create', Facility::class);

        return view('admin.facilities.create');
    }

    public function store(FacilityRequest $request, MediaService $media): RedirectResponse
    {
        $this->authorize('create', Facility::class);

        DB::transaction(function () use ($request, $media): void {
            $facility = Facility::query()->create($request->facilityAttributes());
            $this->saveMedia($request, $media, $facility);
        });

        return redirect()->route('admin.facilities.index')
            ->with('success', __('messages.created', ['entity' => __('messages.entities.facility')]));
    }

    public function edit(Facility $facility): View
    {
        $this->authorize('update', $facility);

        return view('admin.facilities.edit', ['facility' => $facility->load('media')]);
    }

    public function update(FacilityRequest $request, Facility $facility, MediaService $media): RedirectResponse
    {
        $this->authorize('update', $facility);

        DB::transaction(function () use ($request, $media, $facility): void {
            $facility->update($request->facilityAttributes());
            $this->saveMedia($request, $media, $facility);
        });

        return redirect()->route('admin.facilities.index')
            ->with('success', __('messages.updated', ['entity' => __('messages.entities.facility')]));
    }

    /**
     * Media ikut terhapus lewat trait HasMedia; transaksi wajib agar berkas dihapus setelah commit.
     */
    public function destroy(Facility $facility): RedirectResponse
    {
        $this->authorize('delete', $facility);

        DB::transaction(fn (): ?bool => $facility->delete());

        return redirect()->route('admin.facilities.index')
            ->with('success', __('messages.deleted', ['entity' => __('messages.entities.facility')]));
    }

    private function saveMedia(FacilityRequest $request, MediaService $media, Facility $facility): void
    {
        $user = $request->user();
        $cover = $request->coverFile();

        if ($cover !== null) {
            $media->replace($facility, 'cover', $cover, ['alt_text' => $request->coverAlt()], $user);
        } elseif ($request->has('cover_alt')) {
            // Tanpa berkas baru: alt yang diedit diterapkan ke cover yang ada (caption tidak diubah).
            $existing = $facility->media()->where('collection', 'cover')->first();

            if ($existing !== null && $existing->alt_text !== $request->coverAlt()) {
                $media->updateMeta($existing, $request->coverAlt(), $existing->caption);
            }
        }

        foreach ($request->galleryFiles() as $index => $file) {
            $media->store($file, 'gallery', $facility, ['alt_text' => $request->galleryAlt($index)], $user);
        }
    }
}
```

- [ ] **Step 10: Implementasi view (tool Write)**

`resources/views/admin/facilities/index.blade.php`:

```blade
@php
    $currentSort = is_string(request()->query('urut')) ? request()->query('urut') : '';
    $statusNow = is_string(request()->query('status')) ? request()->query('status') : '';
    $categoryNow = is_string(request()->query('kategori')) ? request()->query('kategori') : '';
    $sortUrl = fn (string $key): string => request()->fullUrlWithQuery(['urut' => $key, 'kembali' => null, 'page' => null]);
    // Tab pertama modul adalah Kamar dan Kapasitas (T5); sebelum route itu ada, induk jatuh ke daftar fasilitas.
    $rootUrl = \Illuminate\Support\Facades\Route::has('admin.room-categories.index') ? route('admin.room-categories.index') : route('admin.facilities.index');
    $breadcrumbItems = [
        ['label' => 'Fasilitas dan Kamar', 'url' => $rootUrl],
        ['label' => 'Fasilitas'],
    ];
@endphp
<x-layouts.admin title="Fasilitas">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="$breadcrumbItems" />
    </x-slot:breadcrumb>

    <x-slot:actions>
        <a href="{{ route('admin.facilities.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg" aria-hidden="true"></i> Tambah Fasilitas
        </a>
    </x-slot:actions>

    <div class="mb-3">
        <x-module-tabs group="facilities" />
    </div>

    <x-data-table
        :rows="$facilities"
        :preserve="['urut']"
        search-placeholder="Cari nama fasilitas…"
        caption="Daftar fasilitas rumah sakit"
        empty-title="Belum ada fasilitas"
        empty-message="Tambahkan fasilitas medis, penunjang, atau umum yang tampil di halaman Fasilitas.">
        <x-slot:filters>
            <div class="col-12 col-md-3 col-lg-2">
                <label for="filter-status" class="form-label fw-semibold">Status</label>
                <select id="filter-status" name="status" class="form-select">
                    <option value="">Semua</option>
                    <option value="aktif" @selected($statusNow === 'aktif')>Aktif</option>
                    <option value="nonaktif" @selected($statusNow === 'nonaktif')>Nonaktif</option>
                </select>
            </div>
            <div class="col-12 col-md-3 col-lg-2">
                <label for="filter-kategori" class="form-label fw-semibold">Kategori</label>
                <select id="filter-kategori" name="kategori" class="form-select">
                    <option value="">Semua</option>
                    @foreach (\App\Enums\FacilityCategory::options() as $value => $label)
                        <option value="{{ $value }}" @selected($categoryNow === $value)>{{ $label }}</option>
                    @endforeach
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
            <th scope="col">Fasilitas</th>
            <th scope="col">Kategori</th>
            <th scope="col">Status</th>
            <th scope="col">Urutan</th>
            <th scope="col">Aksi</th>
        </x-slot:head>

        @foreach ($facilities as $facility)
            <tr>
                <td data-label="Fasilitas">
                    <div class="d-flex align-items-center gap-2">
                        <div class="admin-thumb">
                            <x-image type="facility" :media="$facility->firstMedia('cover')" variant="thumb" ratio="4x3" :decorative="true" />
                        </div>
                        <span class="fw-semibold">{{ $facility->name }}</span>
                    </div>
                </td>
                <td data-label="Kategori"><i class="bi {{ $facility->category->icon() }}" aria-hidden="true"></i> {{ $facility->category->label() }}</td>
                <td data-label="Status">
                    @if ($facility->is_active)
                        <span class="badge text-bg-success">Aktif</span>
                    @else
                        <span class="badge text-bg-secondary">Nonaktif</span>
                    @endif
                </td>
                <td data-label="Urutan">{{ $facility->sort_order }}</td>
                <td data-label="Aksi" class="table-actions">
                    <a href="{{ route('admin.facilities.edit', $facility) }}" class="btn btn-sm btn-outline-primary" aria-label="Ubah {{ $facility->name }}">Ubah</a>
                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modal-hapus-fasilitas" data-confirm-action="{{ route('admin.facilities.destroy', $facility) }}" data-confirm-message="Hapus {{ $facility->name }}? Gambarnya ikut terhapus. Tindakan ini tidak dapat dibatalkan. Bila hanya ingin menyembunyikannya, nonaktifkan saja lewat tombol Ubah." aria-label="Hapus {{ $facility->name }}">Hapus</button>
                </td>
            </tr>
        @endforeach
    </x-data-table>

    <x-confirm-modal
        id="modal-hapus-fasilitas"
        title="Hapus fasilitas?"
        message="Fasilitas beserta gambarnya akan dihapus permanen."
        confirm-label="Ya, hapus"
        variant="danger"
        method="DELETE" />
</x-layouts.admin>
```

`resources/views/admin/facilities/_form.blade.php`:

```blade
{{-- Variabel: $facility (Facility|null), $action, $submitLabel. Tanpa input slug (F4B-R5). --}}
@php($isEdit = $facility !== null)
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
                    <x-form.input name="name" label="Nama fasilitas" :value="$facility?->name" :required="true" maxlength="100" help="Alamat halaman (slug) dibuat otomatis dan tidak berubah saat nama diubah." />
                    <x-form.select name="category" label="Kategori" :options="\App\Enums\FacilityCategory::options()" :selected="$facility?->category" placeholder="Pilih kategori" :required="true" />
                    <x-form.textarea name="short_description" label="Deskripsi singkat" :value="$facility?->short_description" :rows="2" maxlength="255" :required="true" help="Satu atau dua kalimat; tampil di kartu fasilitas." />
                    <x-form.rich-text name="description" label="Deskripsi lengkap" :value="$facility?->description" :maxlength="30000" help="Tampil di halaman detail fasilitas. Gambar disisipkan lewat tombol gambar di editor dan wajib diberi teks alternatif." />

                    <details class="mb-3" @if ($errors->has('meta_title') || $errors->has('meta_description')) open @endif>
                        <summary class="fw-semibold mb-2">Pengaturan SEO (opsional)</summary>
                        <x-form.input name="meta_title" label="Meta title" :value="$facility?->meta_title" maxlength="70" help="Maksimal 70 karakter. Kosong = memakai nama fasilitas." />
                        <x-form.textarea name="meta_description" label="Meta description" :value="$facility?->meta_description" :rows="2" maxlength="160" help="Maksimal 160 karakter. Kosong = memakai deskripsi singkat." />
                    </details>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card mb-4">
                <div class="card-header fw-semibold">Status</div>
                <div class="card-body">
                    <x-form.checkbox name="is_active" label="Aktif (tampil di halaman Fasilitas)" :checked="$facility?->is_active ?? true" :with-hidden="true" />
                    <x-form.input name="sort_order" type="number" label="Urutan tampil" :value="$facility?->sort_order ?? 0" inputmode="numeric" min="0" max="9999" help="Angka kecil tampil lebih dulu." />
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header fw-semibold">Foto sampul</div>
                <div class="card-body">
                    <x-form.file name="cover" label="Foto sampul" placeholder="facility" :current-media="$facility?->firstMedia('cover')" alt-name="cover_alt" :alt-value="old('cover_alt', $facility?->firstMedia('cover')?->alt_text)" help="Minimal 800 x 450 piksel. Tanpa foto, halaman publik memakai gambar pengganti." />
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header fw-semibold">Tambah foto galeri</div>
                <div class="card-body">
                    <p class="form-text">Maksimal 20 foto per fasilitas. JPG, PNG, atau WebP, maksimal 2 MB, minimal 600 x 400 piksel. Teks alternatif wajib untuk setiap foto. Untuk menambah lebih banyak, simpan lalu ubah lagi.</p>
                    @for ($i = 0; $i < 3; $i++)
                        <div class="mb-3">
                            <label for="gallery-{{ $i }}" class="form-label fw-semibold">Foto galeri {{ $i + 1 }}</label>
                            <input type="file" id="gallery-{{ $i }}" name="gallery[{{ $i }}]" accept="image/jpeg,image/png,image/webp" class="form-control{{ $errors->has('gallery.'.$i) ? ' is-invalid' : '' }}" data-max-kb="2048">
                            @if ($errors->has('gallery.'.$i))
                                <div class="invalid-feedback d-block">{{ $errors->first('gallery.'.$i) }}</div>
                            @endif
                            <label for="gallery_alt-{{ $i }}" class="form-label visually-hidden">Teks alternatif foto galeri {{ $i + 1 }}</label>
                            <input type="text" id="gallery_alt-{{ $i }}" name="gallery_alt[{{ $i }}]" value="{{ old('gallery_alt.'.$i) }}" maxlength="255" placeholder="Teks alternatif foto {{ $i + 1 }}" class="form-control mt-1{{ $errors->has('gallery_alt.'.$i) ? ' is-invalid' : '' }}">
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
        <a href="{{ route('admin.facilities.index') }}" class="btn btn-outline-secondary">Batal</a>
        <x-loading-button icon="bi-check-lg" loading-text="Menyimpan…">{{ $submitLabel }}</x-loading-button>
    </div>
</form>
```

`resources/views/admin/facilities/create.blade.php`:

```blade
@php
    $rootUrl = \Illuminate\Support\Facades\Route::has('admin.room-categories.index') ? route('admin.room-categories.index') : route('admin.facilities.index');
    $breadcrumbItems = [
        ['label' => 'Fasilitas dan Kamar', 'url' => $rootUrl],
        ['label' => 'Fasilitas', 'url' => route('admin.facilities.index')],
        ['label' => 'Tambah'],
    ];
@endphp
<x-layouts.admin title="Tambah Fasilitas">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="$breadcrumbItems" />
    </x-slot:breadcrumb>

    @include('admin.facilities._form', [
        'facility' => null,
        'action' => route('admin.facilities.store'),
        'submitLabel' => 'Simpan',
    ])
</x-layouts.admin>
```

`resources/views/admin/facilities/edit.blade.php`:

```blade
@php
    $gallery = $facility->mediaIn('gallery')->values();
    $galleryIds = $gallery->pluck('id')->all();
    $rootUrl = \Illuminate\Support\Facades\Route::has('admin.room-categories.index') ? route('admin.room-categories.index') : route('admin.facilities.index');
    $breadcrumbItems = [
        ['label' => 'Fasilitas dan Kamar', 'url' => $rootUrl],
        ['label' => 'Fasilitas', 'url' => route('admin.facilities.index')],
        ['label' => 'Ubah '.$facility->name],
    ];
@endphp
<x-layouts.admin :title="'Ubah '.$facility->name">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="$breadcrumbItems" />
    </x-slot:breadcrumb>

    @include('admin.facilities._form', [
        'facility' => $facility,
        'action' => route('admin.facilities.update', $facility),
        'submitLabel' => 'Simpan Perubahan',
    ])

    {{-- Kelola galeri: form tersendiri (tidak boleh bersarang di form utama) ke admin.media.* --}}
    <div class="card mt-4" id="galeri-fasilitas">
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
                                <x-image type="facility" :media="$image" :alt="$image->alt_text" variant="thumb" ratio="4x3" class="rounded" />
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

- [ ] **Step 11: Edit `routes/admin.php`: impor controller**

Edit `routes/admin.php`: ganti teks berikut (persis; muncul tepat satu kali):

```php
use App\Http\Controllers\Admin\FaqController;
```

dengan:

```php
use App\Http\Controllers\Admin\FacilityController;
use App\Http\Controllers\Admin\FaqController;
```

(Pint merapikan urutan impor. Impor `RoomCategoryController` dan `ContactMessageController` ditambahkan T5 dan T6.)

- [ ] **Step 12: Edit `routes/admin.php`: blok route fasilitas dan dua penanda sebelum "J. Media"**

Edit `routes/admin.php`: ganti teks berikut (persis; muncul tepat satu kali; di berkas teks ini didahului 4 spasi indentasi yang tetap dipertahankan):

```php
// J. Media (11 §2.3 J, F2-R3). JSON; izin akhir ditentukan MediaPolicy menurut pemilik media.
```

dengan (baris pertama memakai indentasi yang sudah ada; baris berikutnya berindentasi 4 spasi persis seperti tertulis):

```php
// Fasilitas dan kamar (halaman #36) serta kotak pesan kontak, fase 4b (F4B-R13). Semua di dalam G-ADMIN; tanpa show (00 I.6).
    // Fasilitas (tab Fasilitas): izin tunggal facilities.manage.
    Route::middleware('permission:facilities.manage')->group(function (): void {
        Route::resource('fasilitas', FacilityController::class)
            ->names('facilities')
            ->parameters(['fasilitas' => 'facility'])
            ->except(['show']);
    });
    // Task 5: kategori kamar
    // Task 6: pesan kontak

    // J. Media (11 §2.3 J, F2-R3). JSON; izin akhir ditentukan MediaPolicy menurut pemilik media.
```

- [ ] **Step 13: Jalankan tes tugas, harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan route:list --path=admin/fasilitas
& $PHP artisan test --filter=AdminFacilitiesTest
& $PHP artisan test --filter=FacilityRequestTest
& $PHP artisan test --filter=Phase4aRoutePermissionTest
& $PHP artisan test --filter=AdminRouteMiddlewareTest
```
Expected: `route:list --path=admin/fasilitas` menampilkan 6 route `admin.facilities.*` (`index`, `create`, `store`, `edit`, `update`, `destroy`; tanpa `show`); semua kelas tes PASS. `AdminRouteMiddlewareTest` mencakup route baru secara otomatis (`auth`, `account:staff`, `active`, `password.changed`, `permission:*`).

- [ ] **Step 14: Pint**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint
& $PHP vendor\bin\pint --test
git status --short
```
Expected: `pint --test` bersih; `git status` hanya memuat berkas tugas ini.

- [ ] **Step 15: Suite penuh (latar belakang, ±11–14 menit)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test *> "$env:TEMP\suite-t4.txt"
Get-Content "$env:TEMP\suite-t4.txt" -Tail 15
```
Jalankan dengan `run_in_background`, tunggu sampai selesai, lalu baca ekor berkas. Expected: semua lulus; jumlah tes bertambah sebanyak tes tugas ini dibanding sebelum T4. Tes lama yang tersentuh sidebar (`LayoutsTest`, `NavigationMenuTest`, `AdminLayoutTest`) harus tetap hijau: tautan "Fasilitas" kini muncul di sidebar Admin Konten dan Super Admin karena route sudah ada, dan tidak ada tes yang mengunci ketiadaannya (diverifikasi dengan Grep `Fasilitas` di `tests/`).

- [ ] **Step 16: Commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git add app/Policies/FacilityPolicy.php app/Http/Requests/Admin/FacilityRequest.php app/Http/Requests/Admin/FacilityIndexRequest.php app/Http/Controllers/Admin/FacilityController.php resources/views/admin/facilities routes/admin.php tests/Feature/Admin/AdminFacilitiesTest.php tests/Feature/Admin/FacilityRequestTest.php tests/Feature/Access/Phase4aRoutePermissionTest.php
git commit -m @'
feat(fase-4b): admin fasilitas (#36) dan blok route 4b

CRUD fasilitas medis, penunjang, dan umum dengan cover, galeri maksimal 20 gambar, rich text
tersanitasi, dan filter daftar. Hapus permanen beserta berkas media. Blok route 4b dipasang
dengan penanda untuk kamar dan pesan kontak; tes jumlah route 4a diganti penghitung 40 route konten.

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
'@
```

---

### Task 5: Admin #36 Kamar dan Kapasitas (`admin.room-categories.*` dan aksi ketersediaan)

**Files:**
- Create: `app/Policies/RoomCategoryPolicy.php`
- Create: `app/Http/Requests/Admin/RoomCategoryRequest.php`
- Create: `app/Http/Requests/Admin/UpdateRoomAvailabilityRequest.php`
- Create: `app/Http/Controllers/Admin/RoomCategoryController.php`
- Create: `resources/views/admin/room-categories/index.blade.php`, `create.blade.php`, `edit.blade.php`, `_form.blade.php`
- Modify: `routes/admin.php` (satu `use` dan penanda `// Task 5: kategori kamar` yang dipasang T4)
- Test: `tests/Feature/Admin/AdminRoomCategoriesTest.php`, `tests/Feature/Admin/RoomCategoryRequestTest.php`, `tests/Feature/Admin/RoomAvailabilityTest.php`
- Tidak diubah: model `RoomCategory`, factory, `InteractsWithFacilities` (T2); `MediaPolicy`, `AuditObserver`, `messages.entities.room_category`, prop `placeholder` `x-form.file`, grup tab `modules.facilities` (T3); `FacilityController` dan view fasilitas (T4, view fasilitas sudah memakai `Route::has('admin.room-categories.index')` untuk induk breadcrumb).

**Interfaces:**
- Consumes:
  - T2: `App\Models\RoomCategory` (`fillable` termasuk `availability_updated_at`, `availability_updated_by`; cast `amenities` ke `array`, `sample_rate` `decimal:2`; relasi `availabilityUpdater()`; `scopeActive`, `scopeOrdered`; accessor `availability_updated_label`, `sample_rate_label`; trait `HasMedia`, `HasUniqueSlug`, `SanitizesRichText`), `RoomCategoryFactory`, `InteractsWithFacilities::createRoomCategory(array $attributes = []): RoomCategory`.
  - T3: `MediaPolicy::manages()` memetakan `RoomCategory` ke `facilities.manage` (Petugas Pendaftaran tidak dapat mengelola gambar kamar); `AuditObserver` terdaftar untuk `RoomCategory` (`room_category.created|updated|deleted`); `messages.entities.room_category` = "Kategori kamar"; prop `placeholder` `x-form.file`; `modules.facilities`.
  - T4: penanda `// Task 5: kategori kamar` di `routes/admin.php`; impor `FacilityController`; pola `FacilityRequest`/`FacilityController`/view sebagai teladan langsung.
  - Fase 1-2: `AuditLogger::log()`, `Actor::staff()`, `MediaService`, `ImageRules`, `x-module-tabs`, `x-confirm-modal`, `x-form.*`, `x-empty-state`, route `admin.media.*`.
- Produces:
  - `App\Http\Controllers\Admin\RoomCategoryController`: `index`, `create`, `store`, `edit`, `update`, `destroy`, `availability` (tanpa `show`).
  - Route (F4B-R13), tujuh buah:
    - `admin.room-categories.create|store|edit|update|destroy`, `permission:facilities.manage`, URI `/admin/kamar/buat`, `/admin/kamar`, `/admin/kamar/{roomCategory}/ubah`, `/admin/kamar/{roomCategory}`;
    - `admin.room-categories.index`, `GET /admin/kamar`, `permission:facilities.manage|rooms.update-availability`;
    - `admin.room-categories.availability`, `PATCH /admin/kamar/{roomCategory}/ketersediaan`, `permission:rooms.update-availability`.
  - `App\Policies\RoomCategoryPolicy` (`final`): `viewAny` (`canAny` kedua izin), `view`/`create`/`update`/`delete` (`facilities.manage`), `updateAvailability` (`rooms.update-availability`).
  - `Admin\RoomCategoryRequest`:
    - `MAX_GALLERY = 20`;
    - `roomCategoryAttributes(): array`, `initialBedsAvailable(): int`;
    - `coverFile()`, `coverAlt()`, `galleryFiles()`, `galleryAlt()`;
    - `public static belowAvailableMessage(int $available): string` (nama tambahan kecil, hanya dipakai tugas ini).
  - `Admin\UpdateRoomAvailabilityRequest`: `bedsAvailable(): int`, `public static exceedsCapacityMessage(int $capacity): string` (nama tambahan kecil, hanya dipakai tugas ini).
  - Audit eksplisit `room.availability_updated` (old/new `['beds_available' => n]`), deskripsi "Ketersediaan {nama} diperbarui dari {old} menjadi {new} tempat tidur.".
  - Flash `success`: "Kategori kamar berhasil ditambahkan." / "… diperbarui." / "… dihapus." dan "Ketersediaan {nama} berhasil diperbarui.".
  - Judul layout: "Kamar dan Kapasitas", "Tambah Kategori Kamar", "Ubah {nama}".

**Test IDs:** CRD-07 (kategori kamar), CRD-13 (`room_categories` selalu dapat dihapus, berkas ikut terhapus), SEK-09, SEK-10, SEK-08, MED-05, VAL-01, VAL-07, AKS-04/AKS-05 (Admin Konten penuh, Petugas hanya ketersediaan), EMP-03, Review Focus 1 (batas ketersediaan dan izin kamar) dan Review Focus 4 (unggahan berbahaya, cover < 800 x 450, galeri 21 gambar, alt kosong).

**Catatan teknis (diverifikasi dari source):**
1. **Mode ketersediaan Petugas (D-12, F4B-R16).** `admin.room-categories.index` terbuka bagi pemegang `facilities.manage` ATAU `rooms.update-availability`. Petugas Pendaftaran hanya punya yang kedua, sehingga:
   - daftar menampilkan form inline ketersediaan, tetapi tombol "Tambah Kategori Kamar", "Ubah", dan "Hapus" tidak dirender (`create`/`store`/`edit`/`update`/`destroy` memberi 403 lewat middleware `permission:facilities.manage`);
   - `MediaPolicy` memetakan `RoomCategory` ke `facilities.manage`, jadi Petugas juga tidak dapat mengubah, mengurutkan, atau menghapus gambar kamar (403 di `admin.media.*`).
2. **Daftar tanpa paginasi dan filter** (F4B-R16, F4A-R13): `RoomCategory::query()->with(['availabilityUpdater:id,name', 'media'])->ordered()->get()`. Mode ketat mewajibkan kedua eager load (view memakai `availabilityUpdater` dan `firstMedia('cover')`). Tabel `table-cards` biasa (pola `admin/pages/index`), bukan `x-data-table`.
3. **Aksi ketersediaan (F4B-R17).**
   - Dalam `DB::transaction`: baris dibaca ulang dengan `lockForUpdate()`, `beds_available`, `availability_updated_at = now()`, dan `availability_updated_by` diset lalu `saveQuietly()`. Karena itu observer tidak menulis `room_category.updated`; satu-satunya audit adalah `room.availability_updated`.
   - Nilai yang sama dengan yang tersimpan tetap memperbarui waktu (konfirmasi petugas).
   - Setelah mengunci baris, controller memeriksa ulang `beds_available <= bed_capacity` dan melempar `ValidationException` bila kapasitas berubah di antara validasi dan penyimpanan, sehingga CHECK `chk_room_categories_beds` tidak pernah memicu 500 (Review Focus 1). Tes memaksa jalur ini dengan subkelas Form Request.
4. **Kapasitas (F4B-R18).**
   - Saat buat, `beds_available` wajib (`lte:bed_capacity`) dan controller mengisi `availability_updated_at = now()` dan `availability_updated_by`.
   - Saat ubah, `beds_available` tidak ada di `rules()`, dan mengubah kapasitas TIDAK mengubah `availability_updated_*` (06 §13 dan 11 menang atas 09 §5.1). Perubahannya tercatat sebagai `room_category.updated` oleh observer.
   - `bed_capacity < beds_available` saat ini ditolak oleh `withValidator()` (BR-115) dan diperiksa ulang dalam transaksi berkunci.
5. **`amenities` (F4B-R19).**
   - Satu `textarea` bernama `amenities`; `prepareForValidation()` mengubahnya menjadi larik (pecah per baris dengan `\R`, `trim`, buang baris kosong).
   - Larik kosong disimpan NULL. Galat per butir ada pada kunci `amenities.{n}`; view menampilkannya lewat `$errors->first('amenities.*')`.
   - `old('amenities')` dapat berupa string (isian asli, karena `merge()` di Form Request hanya mengubah salinan permintaan, bukan permintaan yang di-flash) atau larik (bila klien mengirim `amenities[]`). `x-form.textarea` mengosongkan nilai non-skalar dan tidak menampilkan galat `amenities.*`, jadi view `_form` memakai `textarea` mentah yang menggabung larik dengan `"\n"` (F4B-R19) dan mencetak galat butir pertama.
6. **Old input berbahaya.** Setelah galat, `old('beds_available')` atau `old('availability_for')` dapat berupa larik (input hostile). View memeriksa `is_scalar()` sebelum mencetak atau membandingkan, supaya halaman tidak melempar "Array to string conversion" (tes `test_array_input_does_not_break_the_next_index_render`).
7. **Hapus selalu boleh (F4B-R20, CRD-13).** Tidak ada `deletionBlockedReason()`. Modal "Hapus kategori kamar?" menyarankan menonaktifkan.
8. **`sample_rate`** divalidasi `bail|nullable|numeric|min:0|max:9999999999|decimal:0,2`. Formulir meminta angka tanpa titik pemisah ribuan (nilai "400.000" akan dibaca 400).
9. **Nama tambahan kecil** (`belowAvailableMessage`, `exceedsCapacityMessage`, `initialBedsAvailable`, `bedsAvailable`) dipakai hanya di tugas ini.
10. **Id untuk tautan ringkasan galat.** `x-form.errors-summary` membentuk tautan dari kunci galat (titik diganti `-`, garis bawah dipertahankan): `gallery_alt.0` menjadi `#gallery_alt-0`, sehingga input alt galeri berid `gallery_alt-{{ $i }}` (juga `for` labelnya). Untuk `amenities.N` tautan menjadi `#amenities-N`, yang tidak ada; textarea hanya berid `amenities`, jadi tautan per butir tidak didukung (tidak ada id per butir, karena semua butir berada di satu textarea). Galat `amenities` (batas 30 butir) tertaut ke textarea.
11. **Tidak ada perubahan JS atau SCSS**, jadi tidak ada `npm run build`.

- [ ] **Step 1: Verifikasi prasyarat (T2, T3, T4)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git branch --show-current
git status --short
foreach ($p in 'app\Models\RoomCategory.php', 'database\factories\RoomCategoryFactory.php', 'tests\Support\InteractsWithFacilities.php', 'app\Http\Controllers\Admin\FacilityController.php', 'app\Policies\FacilityPolicy.php') { "{0} => {1}" -f $p, (Test-Path $p) }
Select-String -Path app\Policies\MediaPolicy.php -Pattern 'RoomCategory::class' | Select-Object -ExpandProperty Line
Select-String -Path app\Providers\AppServiceProvider.php -Pattern 'RoomCategory::observe' | Select-Object -ExpandProperty Line
Select-String -Path lang\id\messages.php -Pattern "'room_category'" | Select-Object -ExpandProperty Line
Select-String -Path routes\admin.php -Pattern '// Task 5: kategori kamar', 'FacilityController;', 'ScheduleExceptionController;' | Select-Object -ExpandProperty Line
& $PHP artisan test --filter='AdminFacilitiesTest|FacilityRequestTest'
& $PHP artisan test tests\Feature\Facilities
```
Expected: `fase-4b-fasilitas-kamar-kontak`; `git status` bersih (selain `.superpowers/`); kelima berkas `True`; `RoomCategory::class` pada `MediaPolicy`, `RoomCategory::observe(...)`, dan kunci `'room_category'` tercetak; ketiga baris `routes/admin.php` tercetak; subset tes terfokus (kelas tes T4 dan `tests\Feature\Facilities`) PASS. Suite penuh tidak dijalankan di sini (±11–14 menit, melewati batas tool 10 menit); suite penuh ada di langkah akhir (latar belakang).

- [ ] **Step 2: Tulis tes yang gagal (tool Write) `tests/Feature/Admin/AdminRoomCategoriesTest.php`**

```php
<?php

namespace Tests\Feature\Admin;

use App\Http\Requests\Admin\RoomCategoryRequest;
use App\Models\AuditLog;
use App\Models\Media;
use App\Models\RoomCategory;
use App\Models\User;
use App\Services\Media\MediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ImageFixtures;
use Tests\Support\InteractsWithFacilities;
use Tests\Support\InteractsWithMedia;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * Admin #36 tab Kamar dan Kapasitas (T5): mode ketersediaan Petugas (D-12), CRUD Admin Konten (CRD-07),
 * kapasitas (BR-115), amenities (BR-118), cover dan galeri, unggahan berbahaya, serta hapus selalu boleh (CRD-13).
 */
class AdminRoomCategoriesTest extends TestCase
{
    use InteractsWithFacilities;
    use InteractsWithMedia;
    use InteractsWithStaff;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        // Selasa, 6 Oktober 2026 pukul 10.30 WIB.
        $this->travelTo(Carbon::parse('2026-10-06 10:30:00'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Kamar Kelas Contoh',
            'short_description' => 'Ruang rawat inap contoh untuk pengujian.',
            'description' => '<p>Deskripsi <strong>kamar</strong> contoh.</p>',
            'amenities' => "AC\nTelevisi\nKamar mandi dalam",
            'bed_capacity' => '12',
            'beds_available' => '5',
            'sample_rate' => '400000',
            'rate_unit' => 'per malam',
            'rate_note' => 'Tarif contoh, belum final',
            'is_active' => '1',
            'sort_order' => '3',
        ], $overrides);
    }

    private function attachCover(RoomCategory $room, string $alt = 'Foto sampul kamar'): Media
    {
        return app(MediaService::class)->store(ImageFixtures::jpeg(1200, 800, 'cover.jpg'), 'cover', $room, ['alt_text' => $alt]);
    }

    private function attachGalleryImage(RoomCategory $room, string $alt): Media
    {
        return app(MediaService::class)->store(ImageFixtures::jpeg(900, 600, 'galeri.jpg'), 'gallery', $room, ['alt_text' => $alt]);
    }

    // --- Akses (AKS-04, AKS-05, D-12) -----------------------------------------------------------------

    /**
     * @return array<string, array{string, int}>
     */
    public static function staffRoles(): array
    {
        return [
            'super admin' => ['super-admin', 200],
            'admin konten' => ['content-admin', 200],
            'petugas pendaftaran (mode ketersediaan)' => ['registration-officer', 200],
        ];
    }

    #[DataProvider('staffRoles')]
    public function test_index_access_per_staff_role(string $role, int $status): void
    {
        $this->createRoomCategory(['name' => 'VIP Akses']);

        $this->actingAs($this->createStaff($role))
            ->get(route('admin.room-categories.index'))
            ->assertStatus($status);
    }

    public function test_patient_gets_403_and_guest_is_redirected_to_admin_login(): void
    {
        $this->actingAs(User::factory()->patient()->create())
            ->get(route('admin.room-categories.index'))
            ->assertForbidden();

        $this->app['auth']->forgetGuards();

        $this->get(route('admin.room-categories.index'))->assertRedirect(route('admin.login'));
    }

    public function test_urls_use_the_indonesian_segments_and_there_is_no_show_route(): void
    {
        $room = $this->createRoomCategory();

        $this->assertSame(url('/admin/kamar'), route('admin.room-categories.index'));
        $this->assertSame(url('/admin/kamar'), route('admin.room-categories.store'));
        $this->assertSame(url('/admin/kamar/buat'), route('admin.room-categories.create'));
        $this->assertSame(url('/admin/kamar/'.$room->id.'/ubah'), route('admin.room-categories.edit', $room));
        $this->assertSame(url('/admin/kamar/'.$room->id), route('admin.room-categories.update', $room));
        $this->assertSame(url('/admin/kamar/'.$room->id.'/ketersediaan'), route('admin.room-categories.availability', $room));
        $this->assertFalse(Route::has('admin.room-categories.show'));
    }

    // --- Daftar ---------------------------------------------------------------------------------------

    public function test_index_renders_many_rows_with_capacity_availability_updater_rate_and_status_in_strict_mode(): void
    {
        $updater = $this->createStaff('registration-officer', ['name' => 'Siti Petugas']);
        $k1 = $this->createRoomCategory([
            'name' => 'BPJS Kelas 1 Contoh', 'bed_capacity' => 12, 'beds_available' => 4, 'sample_rate' => 350000,
            'rate_unit' => 'per malam', 'rate_note' => 'Tarif contoh, belum final', 'sort_order' => 1,
            'availability_updated_at' => now()->subHour(), 'availability_updated_by' => $updater->id,
        ]);
        $k2 = $this->createRoomCategory([
            'name' => 'BPJS Kelas 2 Contoh', 'bed_capacity' => 16, 'beds_available' => 7, 'sample_rate' => null,
            'sort_order' => 2, 'availability_updated_at' => null, 'availability_updated_by' => null,
        ]);
        $this->createRoomCategory(['name' => 'BPJS Kelas 3 Contoh', 'bed_capacity' => 24, 'beds_available' => 0, 'sort_order' => 3]);
        $k4 = $this->createRoomCategory(['name' => 'VIP Contoh', 'bed_capacity' => 6, 'beds_available' => 2, 'sort_order' => 4, 'is_active' => false]);
        $this->attachCover($k1, 'Foto kelas 1');

        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.room-categories.index'))
            ->assertOk()
            ->assertSeeInOrder(['BPJS Kelas 1 Contoh', 'BPJS Kelas 2 Contoh', 'BPJS Kelas 3 Contoh', 'VIP Contoh'])
            ->assertSee('12 tempat tidur')
            ->assertSee('24 tempat tidur')
            ->assertSee('Rp 350.000 per malam')
            ->assertSee('Tarif contoh, belum final')
            ->assertSee('Hubungi kami')
            ->assertSee($k1->availability_updated_label)
            ->assertSee('Belum diperbarui')
            ->assertSee('oleh Siti Petugas')
            ->assertSee('<span class="badge text-bg-success">Aktif</span>', false)
            ->assertSee('<span class="badge text-bg-secondary">Nonaktif</span>', false)
            ->assertSee('images/placeholders/room.svg', false)
            ->assertSee('name="beds_available" value="4" min="0" max="12"', false)
            ->assertSee('name="beds_available" value="0" min="0" max="24"', false)
            ->assertSee('action="'.route('admin.room-categories.availability', $k2).'"', false)
            ->assertSee('data-confirm-action="'.route('admin.room-categories.destroy', $k4).'"', false)
            ->assertDontSee('real-time')
            ->assertDontSee('Real-time');
    }

    public function test_index_shows_module_tabs_breadcrumb_add_button_and_the_delete_modal_to_content_admin(): void
    {
        $this->createRoomCategory(['name' => 'VIP Contoh']);

        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.room-categories.index'))
            ->assertOk()
            ->assertSee('href="'.route('admin.room-categories.index').'"', false)
            ->assertSee('href="'.route('admin.facilities.index').'"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('Fasilitas dan Kamar')
            ->assertSee('Tambah Kategori Kamar')
            ->assertSee('href="'.route('admin.room-categories.create').'"', false)
            ->assertSee('id="modal-hapus-kamar"', false)
            ->assertSee('Hapus kategori kamar?')
            ->assertSee('Kategori kamar beserta gambarnya akan dihapus permanen dan halaman publiknya tidak dapat dibuka lagi. Bila hanya ingin menyembunyikannya, nonaktifkan saja.')
            ->assertDontSee('Anda hanya dapat memperbarui ketersediaan tempat tidur.');
    }

    public function test_registration_officer_sees_only_the_availability_mode_d12(): void
    {
        $a = $this->createRoomCategory(['name' => 'BPJS Kelas 1 Contoh', 'sort_order' => 1]);
        $b = $this->createRoomCategory(['name' => 'VIP Contoh', 'sort_order' => 2, 'is_active' => false]);

        $this->actingAs($this->createStaff('registration-officer'))
            ->get(route('admin.room-categories.index'))
            ->assertOk()
            ->assertSee('Anda hanya dapat memperbarui ketersediaan tempat tidur.')
            ->assertSee('action="'.route('admin.room-categories.availability', $a).'"', false)
            ->assertSee('action="'.route('admin.room-categories.availability', $b).'"', false)
            ->assertDontSee('Tambah Kategori Kamar')
            ->assertDontSee('href="'.route('admin.room-categories.create').'"', false)
            ->assertDontSee('href="'.route('admin.room-categories.edit', $a).'"', false)
            ->assertDontSee('data-confirm-action="'.route('admin.room-categories.destroy', $a).'"', false)
            ->assertDontSee('modal-hapus-kamar')
            ->assertDontSee('href="'.route('admin.facilities.index').'"', false);
    }

    public function test_empty_list_shows_the_empty_state_with_an_add_button_only_for_managers(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.room-categories.index'))
            ->assertOk()
            ->assertSee('Belum ada kategori kamar')
            ->assertSee('href="'.route('admin.room-categories.create').'"', false);

        $this->app['auth']->forgetGuards();

        $this->actingAs($this->createStaff('registration-officer'))
            ->get(route('admin.room-categories.index'))
            ->assertOk()
            ->assertSee('Belum ada kategori kamar')
            ->assertDontSee('href="'.route('admin.room-categories.create').'"', false);
    }

    // --- Form dan CRUD (CRD-07) -----------------------------------------------------------------------

    public function test_create_form_has_the_room_fields_cover_gallery_and_no_slug_field(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.room-categories.create'))
            ->assertOk()
            ->assertSee('Tambah Kategori Kamar')
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('action="'.route('admin.room-categories.store').'"', false)
            ->assertSee('name="name"', false)
            ->assertSee('name="short_description"', false)
            ->assertSee('data-rich-text', false)
            ->assertSee('Fasilitas kamar (satu per baris)')
            ->assertSee('name="amenities"', false)
            ->assertSee('name="bed_capacity"', false)
            ->assertSee('name="beds_available"', false)
            ->assertSee('name="sample_rate"', false)
            ->assertSee('name="rate_unit"', false)
            ->assertSee('value="per malam"', false)
            ->assertSee('name="rate_note"', false)
            ->assertSee('name="is_active"', false)
            ->assertSee('name="sort_order"', false)
            ->assertSee('name="meta_title"', false)
            ->assertSee('name="cover"', false)
            ->assertSee('name="cover_alt"', false)
            ->assertSee('name="gallery[0]"', false)
            ->assertSee('name="gallery_alt[0]"', false)
            ->assertDontSee('name="slug"', false);
    }

    public function test_content_admin_adds_a_room_category_and_availability_metadata_is_filled_crd07(): void
    {
        $admin = $this->createStaff('content-admin');

        $this->actingAs($admin)
            ->post(route('admin.room-categories.store'), $this->payload())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.room-categories.index'))
            ->assertSessionHas('success', 'Kategori kamar berhasil ditambahkan.');

        $room = RoomCategory::query()->sole();

        $this->assertSame('Kamar Kelas Contoh', $room->name);
        $this->assertSame('kamar-kelas-contoh', $room->slug);
        $this->assertSame('Ruang rawat inap contoh untuk pengujian.', $room->short_description);
        $this->assertStringContainsString('<strong>kamar</strong>', (string) $room->description);
        $this->assertSame(['AC', 'Televisi', 'Kamar mandi dalam'], $room->amenities);
        $this->assertSame(12, $room->bed_capacity);
        $this->assertSame(5, $room->beds_available);
        $this->assertSame('400000.00', (string) $room->sample_rate);
        $this->assertSame('per malam', $room->rate_unit);
        $this->assertSame('Tarif contoh, belum final', $room->rate_note);
        $this->assertTrue($room->is_active);
        $this->assertSame(3, $room->sort_order);
        $this->assertTrue($room->availability_updated_at->equalTo(now()));
        $this->assertSame($admin->id, $room->availability_updated_by);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'room_category.created',
            'description' => 'Kategori kamar "Kamar Kelas Contoh" ditambahkan.',
        ]);
    }

    public function test_a_full_room_and_a_room_at_full_capacity_can_be_created(): void
    {
        $this->actingAs($this->createStaff('content-admin'));

        $this->post(route('admin.room-categories.store'), $this->payload(['name' => 'Kamar Penuh Contoh', 'beds_available' => '0']))
            ->assertSessionHasNoErrors();
        $this->post(route('admin.room-categories.store'), $this->payload(['name' => 'Kamar Kosong Contoh', 'beds_available' => '12']))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, RoomCategory::query()->where('name', 'Kamar Penuh Contoh')->value('beds_available'));
        $this->assertSame(12, RoomCategory::query()->where('name', 'Kamar Kosong Contoh')->value('beds_available'));
    }

    public function test_optional_fields_may_be_left_empty_and_the_textarea_amenities_are_normalized(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.room-categories.store'), $this->payload([
                'name' => 'Kamar Tanpa Tarif',
                'description' => '',
                'amenities' => "  AC  \r\n\r\n Televisi\n   \n",
                'sample_rate' => '',
                'rate_note' => '',
                'sort_order' => '',
                'meta_title' => '',
                'meta_description' => '',
            ]))
            ->assertSessionHasNoErrors();

        $room = RoomCategory::query()->sole();

        $this->assertSame(['AC', 'Televisi'], $room->amenities);
        $this->assertNull($room->description);
        $this->assertNull($room->sample_rate);
        $this->assertNull($room->rate_note);
        $this->assertSame(0, $room->sort_order);
        $this->assertNull($room->meta_title);
    }

    public function test_empty_amenities_are_stored_as_null(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.room-categories.store'), $this->payload(['amenities' => "\n  \n"]))
            ->assertSessionHasNoErrors();

        $this->assertNull(RoomCategory::query()->sole()->amenities);
    }

    public function test_description_is_sanitized_when_saved_through_the_form_sek08(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.room-categories.store'), $this->payload([
                'description' => '<p>Aman</p><script>alert(1)</script><img src="x" onerror="alert(1)"><a href="javascript:alert(1)">klik</a>',
            ]))
            ->assertRedirect(route('admin.room-categories.index'));

        $description = (string) RoomCategory::query()->sole()->description;

        $this->assertStringContainsString('Aman', $description);
        $this->assertStringNotContainsString('<script', $description);
        $this->assertStringNotContainsString('onerror', $description);
        $this->assertStringNotContainsString('javascript:', $description);
    }

    public function test_edit_form_shows_values_the_amenities_one_per_line_and_gallery_management_forms(): void
    {
        $room = $this->createRoomCategory([
            'name' => 'VIP Contoh', 'short_description' => 'Ringkasan VIP.', 'amenities' => ['AC', 'Televisi'],
            'bed_capacity' => 6, 'beds_available' => 2, 'sample_rate' => 900000, 'rate_unit' => 'per malam', 'sort_order' => 5,
        ]);
        $first = $this->createMediaRow(['model_type' => RoomCategory::class, 'model_id' => $room->id, 'collection' => 'gallery', 'alt_text' => 'Foto pertama', 'sort_order' => 1]);
        $second = $this->createMediaRow(['model_type' => RoomCategory::class, 'model_id' => $room->id, 'collection' => 'gallery', 'alt_text' => 'Foto kedua', 'sort_order' => 2]);

        $this->actingAs($this->createStaff('super-admin'))
            ->get(route('admin.room-categories.edit', $room))
            ->assertOk()
            ->assertSee('Ubah VIP Contoh')
            ->assertSee('action="'.route('admin.room-categories.update', $room).'"', false)
            ->assertSee('value="VIP Contoh"', false)
            ->assertSee('Ringkasan VIP.')
            ->assertSee('>AC'."\n".'Televisi</textarea>', false)
            ->assertSee('value="6"', false)
            ->assertSee('value="900000"', false)
            ->assertDontSee('name="beds_available"', false)
            ->assertSee('Tempat tidur tersedia saat ini: 2 dari 6.')
            ->assertSee('Galeri (2 dari 20 foto)')
            ->assertSee('action="'.route('admin.media.update', $first).'"', false)
            ->assertSee('action="'.route('admin.media.reorder').'"', false)
            ->assertSee('data-confirm-action="'.route('admin.media.destroy', $second).'"', false);
    }

    public function test_update_changes_fields_keeps_the_slug_and_does_not_touch_availability(): void
    {
        $updater = $this->createStaff('registration-officer');
        $stamp = Carbon::parse('2026-10-05 08:00:00');
        $room = $this->createRoomCategory([
            'name' => 'Kamar Kelas Contoh', 'bed_capacity' => 12, 'beds_available' => 4,
            'availability_updated_at' => $stamp, 'availability_updated_by' => $updater->id,
        ]);
        $slug = $room->slug;
        $this->actingAs($this->createStaff('content-admin'));

        $this->put(route('admin.room-categories.update', $room), $this->payload([
            'name' => 'Nama Sudah Berbeda',
            'bed_capacity' => '20',
            'beds_available' => '9',
            'amenities' => "AC\nKulkas",
        ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.room-categories.index'))
            ->assertSessionHas('success', 'Kategori kamar berhasil diperbarui.');

        $room->refresh();
        $this->assertSame('Nama Sudah Berbeda', $room->name);
        $this->assertSame($slug, $room->slug);
        $this->assertSame(20, $room->bed_capacity);
        $this->assertSame(4, $room->beds_available);
        $this->assertSame(['AC', 'Kulkas'], $room->amenities);
        $this->assertTrue($room->availability_updated_at->equalTo($stamp));
        $this->assertSame($updater->id, $room->availability_updated_by);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'room_category.updated',
            'description' => 'Kategori kamar "Nama Sudah Berbeda" diperbarui.',
        ]);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'room.availability_updated']);
    }

    public function test_capacity_below_the_current_availability_is_rejected_and_equal_is_accepted(): void
    {
        $room = $this->createRoomCategory(['name' => 'Kamar Kelas Contoh', 'bed_capacity' => 12, 'beds_available' => 4]);
        $this->actingAs($this->createStaff('content-admin'));

        $this->from(route('admin.room-categories.edit', $room))
            ->put(route('admin.room-categories.update', $room), $this->payload(['bed_capacity' => '3']))
            ->assertRedirect(route('admin.room-categories.edit', $room))
            ->assertSessionHasErrors(['bed_capacity' => 'Kapasitas tidak boleh lebih kecil dari tempat tidur tersedia saat ini (4). Perbarui ketersediaan terlebih dahulu.']);

        $this->assertSame(12, $room->fresh()->bed_capacity);

        $this->put(route('admin.room-categories.update', $room), $this->payload(['bed_capacity' => '4']))
            ->assertSessionHasNoErrors();

        $this->assertSame(4, $room->fresh()->bed_capacity);
        $this->assertSame(4, $room->fresh()->beds_available);
    }

    public function test_the_controller_rechecks_capacity_under_lock_even_if_validation_was_bypassed(): void
    {
        $room = $this->createRoomCategory(['name' => 'Kamar Kelas Contoh', 'bed_capacity' => 12, 'beds_available' => 4]);
        $this->app->bind(RoomCategoryRequest::class, fn () => new class extends RoomCategoryRequest
        {
            public function withValidator(Validator $validator): void {}
        });
        $this->actingAs($this->createStaff('content-admin'));

        $this->from(route('admin.room-categories.edit', $room))
            ->put(route('admin.room-categories.update', $room), $this->payload(['name' => 'Tidak Tersimpan', 'bed_capacity' => '3']))
            ->assertRedirect(route('admin.room-categories.edit', $room))
            ->assertSessionHasErrors(['bed_capacity' => 'Kapasitas tidak boleh lebih kecil dari tempat tidur tersedia saat ini (4). Perbarui ketersediaan terlebih dahulu.']);

        $room->refresh();
        $this->assertSame(12, $room->bed_capacity);
        $this->assertSame('Kamar Kelas Contoh', $room->name);
    }

    public function test_a_room_is_deactivated_through_the_edit_form_and_leaves_the_active_scope(): void
    {
        $room = $this->createRoomCategory(['name' => 'Kamar Kelas Contoh', 'is_active' => true]);
        $this->createRoomCategory(['name' => 'Kamar Tetap Aktif']);

        $this->actingAs($this->createStaff('content-admin'))
            ->put(route('admin.room-categories.update', $room), $this->payload(['is_active' => '0', 'bed_capacity' => (string) $room->bed_capacity]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('room_categories', ['id' => $room->id, 'is_active' => 0]);
        $this->assertSame(['Kamar Tetap Aktif'], RoomCategory::query()->active()->pluck('name')->all());
    }

    // --- Cover dan galeri (D-41, MED-05, Review Focus 4) ----------------------------------------------

    public function test_cover_upload_on_create_stores_media_for_the_room_category(): void
    {
        $admin = $this->createStaff('content-admin');

        $this->actingAs($admin)
            ->post(route('admin.room-categories.store'), $this->payload([
                'cover' => ImageFixtures::jpeg(1200, 800, 'cover.jpg'),
                'cover_alt' => 'Foto sampul kamar',
                'gallery' => [0 => ImageFixtures::jpeg(900, 600, 'g1.jpg')],
                'gallery_alt' => [0 => 'Tempat tidur pasien'],
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.room-categories.index'));

        $room = RoomCategory::query()->sole();
        $cover = Media::query()->where('collection', 'cover')->sole();
        $gallery = Media::query()->where('collection', 'gallery')->sole();

        $this->assertSame(RoomCategory::class, $cover->model_type);
        $this->assertSame($room->id, $cover->model_id);
        $this->assertSame('Foto sampul kamar', $cover->alt_text);
        $this->assertSame($admin->id, $cover->uploaded_by);
        $this->assertSame('Tempat tidur pasien', $gallery->alt_text);
        Storage::disk('public')->assertExists($cover->directory.'/'.$cover->file_name);
        Storage::disk('public')->assertExists($cover->thumb_path);
    }

    public function test_replacing_the_cover_removes_the_old_file_after_commit(): void
    {
        $room = $this->createRoomCategory(['name' => 'Kamar Kelas Contoh', 'bed_capacity' => 12, 'beds_available' => 4]);
        $old = $this->attachCover($room, 'Sampul lama');
        $oldPath = $old->directory.'/'.$old->file_name;

        $this->actingAs($this->createStaff('content-admin'))
            ->put(route('admin.room-categories.update', $room), $this->payload([
                'cover' => ImageFixtures::jpeg(1000, 700, 'baru.jpg'),
                'cover_alt' => 'Sampul baru',
            ]))
            ->assertSessionHasNoErrors();

        $new = Media::query()->where('collection', 'cover')->sole();

        $this->assertNotSame($old->id, $new->id);
        $this->assertSame('Sampul baru', $new->alt_text);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($new->directory.'/'.$new->file_name);
    }

    public function test_update_without_a_new_cover_keeps_it_a_changed_alt_updates_it_and_an_empty_alt_is_rejected(): void
    {
        $room = $this->createRoomCategory(['name' => 'Kamar Kelas Contoh', 'bed_capacity' => 12, 'beds_available' => 4]);
        $cover = $this->attachCover($room, 'Sampul lama');
        $this->actingAs($this->createStaff('content-admin'));

        $this->put(route('admin.room-categories.update', $room), $this->payload())->assertSessionHasNoErrors();
        $this->assertSame($cover->id, Media::query()->where('collection', 'cover')->sole()->id);

        $this->put(route('admin.room-categories.update', $room), $this->payload(['cover_alt' => '  Sampul diperbarui  ']))
            ->assertSessionHasNoErrors();
        $this->assertSame('Sampul diperbarui', Media::query()->where('collection', 'cover')->sole()->alt_text);

        $this->put(route('admin.room-categories.update', $room), $this->payload(['name' => 'Nama Tidak Tersimpan', 'cover_alt' => '  ']))
            ->assertSessionHasErrors(['cover_alt' => 'Teks alternatif gambar wajib diisi.']);
        $this->assertSame('Sampul diperbarui', Media::query()->where('collection', 'cover')->sole()->alt_text);
        $this->assertSame('Kamar Kelas Contoh', $room->fresh()->name);
    }

    public function test_an_empty_cover_alt_without_a_cover_is_not_an_error(): void
    {
        $room = $this->createRoomCategory(['name' => 'Kamar Kelas Contoh', 'bed_capacity' => 12, 'beds_available' => 4]);

        $this->actingAs($this->createStaff('content-admin'))
            ->put(route('admin.room-categories.update', $room), $this->payload(['cover_alt' => '']))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, Media::query()->count());
    }

    public function test_a_gallery_image_without_alt_text_is_rejected_and_nothing_is_stored(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.room-categories.store'), $this->payload([
                'gallery' => [0 => ImageFixtures::jpeg(900, 600)],
                'gallery_alt' => [0 => ''],
            ]))
            ->assertSessionHasErrors(['gallery_alt.0' => 'Teks alternatif gambar wajib diisi.']);

        $this->assertSame(0, RoomCategory::query()->count());
        $this->assertSame(0, Media::query()->count());
    }

    /**
     * @return array<string, array{int, int}>
     */
    public static function galleryLimits(): array
    {
        return [
            '19 yang ada ditambah 2 baru' => [19, 2],
            '20 yang ada ditambah 1 baru' => [20, 1],
        ];
    }

    #[DataProvider('galleryLimits')]
    public function test_the_gallery_is_limited_to_20_images_including_existing_ones(int $existing, int $incoming): void
    {
        $room = $this->createRoomCategory(['name' => 'Kamar Kelas Contoh', 'bed_capacity' => 12, 'beds_available' => 4]);

        foreach (range(1, $existing) as $position) {
            $this->createMediaRow([
                'model_type' => RoomCategory::class,
                'model_id' => $room->id,
                'collection' => 'gallery',
                'sort_order' => $position,
            ]);
        }

        $files = [];
        $alts = [];

        foreach (range(0, $incoming - 1) as $index) {
            $files[$index] = ImageFixtures::jpeg(900, 600);
            $alts[$index] = 'Foto baru '.($index + 1);
        }

        $this->actingAs($this->createStaff('content-admin'))
            ->put(route('admin.room-categories.update', $room), $this->payload(['gallery' => $files, 'gallery_alt' => $alts]))
            ->assertSessionHasErrors(['gallery' => 'Galeri maksimal 20 gambar.']);

        $this->assertSame($existing, Media::query()->where('collection', 'gallery')->count());
    }

    public function test_hostile_and_undersized_uploads_are_rejected_without_storing_anything_sek09_sek10(): void
    {
        $this->actingAs($this->createStaff('content-admin'));

        $hostile = [
            ImageFixtures::svgWithScript(),
            ImageFixtures::phpWithJpegHeader(),
            ImageFixtures::textNamedJpg(),
            ImageFixtures::jpeg(799, 449, 'kecil.jpg'),
        ];

        foreach ($hostile as $file) {
            $this->post(route('admin.room-categories.store'), $this->payload(['cover' => $file, 'cover_alt' => 'x']))
                ->assertSessionHasErrors('cover');
        }

        $this->post(route('admin.room-categories.store'), $this->payload([
            'gallery' => [0 => ImageFixtures::svgWithScript('galeri.svg'), 1 => ImageFixtures::phpWithJpegHeader('galeri.php.jpg')],
            'gallery_alt' => [0 => 'x', 1 => 'y'],
        ]))->assertSessionHasErrors(['gallery.0', 'gallery.1']);

        $this->assertSame(0, RoomCategory::query()->count());
        $this->assertSame(0, Media::query()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_content_admin_manages_room_images_but_registration_officer_cannot(): void
    {
        $room = $this->createRoomCategory(['name' => 'Kamar Kelas Contoh', 'bed_capacity' => 12, 'beds_available' => 4]);
        $a = $this->attachGalleryImage($room, 'Foto A');
        $b = $this->attachGalleryImage($room, 'Foto B');
        $editUrl = route('admin.room-categories.edit', $room);

        $this->actingAs($this->createStaff('registration-officer'));
        $this->patch(route('admin.media.update', $a), ['alt_text' => 'Diubah paksa'])->assertForbidden();
        $this->post(route('admin.media.reorder'), ['ids' => [$b->id, $a->id]])->assertForbidden();
        $this->delete(route('admin.media.destroy', $a))->assertForbidden();
        $this->assertSame('Foto A', $a->fresh()->alt_text);
        $this->assertDatabaseHas('media', ['id' => $a->id]);

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->createStaff('content-admin'));

        $this->from($editUrl)
            ->patch(route('admin.media.update', $a), ['alt_text' => 'Foto A baru'])
            ->assertRedirect($editUrl);
        $this->assertSame('Foto A baru', $a->fresh()->alt_text);

        $path = $a->directory.'/'.$a->file_name;
        $this->from($editUrl)->delete(route('admin.media.destroy', $a))->assertRedirect($editUrl);
        $this->assertDatabaseMissing('media', ['id' => $a->id]);
        Storage::disk('public')->assertMissing($path);
    }

    // --- Hapus selalu boleh (CRD-13) ------------------------------------------------------------------

    public function test_deleting_a_room_category_removes_its_media_files_crd13(): void
    {
        $admin = $this->createStaff('content-admin');
        $this->actingAs($admin)->post(route('admin.room-categories.store'), $this->payload([
            'cover' => ImageFixtures::jpeg(1200, 800),
            'cover_alt' => 'Sampul',
            'gallery' => [0 => ImageFixtures::jpeg(900, 600)],
            'gallery_alt' => [0 => 'Galeri'],
        ]));
        $room = RoomCategory::query()->sole();
        $paths = Media::query()->get()->flatMap(fn (Media $media): array => [$media->directory.'/'.$media->file_name, $media->thumb_path])->all();
        $this->assertCount(4, $paths);

        $this->delete(route('admin.room-categories.destroy', $room))
            ->assertRedirect(route('admin.room-categories.index'))
            ->assertSessionHas('success', 'Kategori kamar berhasil dihapus.');

        $this->assertDatabaseMissing('room_categories', ['id' => $room->id]);
        $this->assertSame(0, Media::query()->count());

        foreach ($paths as $path) {
            Storage::disk('public')->assertMissing($path);
        }

        $this->assertDatabaseHas('audit_logs', ['action' => 'room_category.deleted']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function deleters(): array
    {
        return ['admin konten' => ['content-admin'], 'super admin' => ['super-admin']];
    }

    #[DataProvider('deleters')]
    public function test_a_room_category_is_always_deletable_by_a_manager_even_when_full_or_active(string $role): void
    {
        $room = $this->createRoomCategory(['name' => 'Kamar Aktif Penuh', 'bed_capacity' => 10, 'beds_available' => 0, 'is_active' => true]);

        $this->actingAs($this->createStaff($role))
            ->delete(route('admin.room-categories.destroy', $room))
            ->assertRedirect(route('admin.room-categories.index'))
            ->assertSessionHas('success', 'Kategori kamar berhasil dihapus.');

        $this->assertDatabaseMissing('room_categories', ['id' => $room->id]);
        $this->assertSame(1, AuditLog::query()->where('action', 'room_category.deleted')->count());
    }

    // --- Rute dan penolakan ---------------------------------------------------------------------------

    public function test_non_numeric_id_is_a_404(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get('/admin/kamar/abc/ubah')
            ->assertNotFound();
    }

    public function test_registration_officer_patient_and_guest_cannot_use_the_structure_routes(): void
    {
        $room = $this->createRoomCategory(['name' => 'Kamar Aman', 'bed_capacity' => 12, 'beds_available' => 4]);

        $requests = [
            ['GET', route('admin.room-categories.create'), []],
            ['POST', route('admin.room-categories.store'), $this->payload(['name' => 'Tidak Boleh'])],
            ['GET', route('admin.room-categories.edit', $room), []],
            ['PUT', route('admin.room-categories.update', $room), $this->payload(['name' => 'Diubah Paksa'])],
            ['DELETE', route('admin.room-categories.destroy', $room), []],
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

        $this->assertSame('Kamar Aman', $room->fresh()->name);
        $this->assertDatabaseMissing('room_categories', ['name' => 'Tidak Boleh']);
        $this->assertDatabaseHas('room_categories', ['id' => $room->id]);
    }
}
```

- [ ] **Step 3: Tulis tes yang gagal (tool Write) `tests/Feature/Admin/RoomCategoryRequestTest.php`**

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\Media;
use App\Models\RoomCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ImageFixtures;
use Tests\Support\InteractsWithFacilities;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * 12 V-28 (dengan F4B-R3, F4B-R4, F4B-R5, F4B-R18, F4B-R19, F4B-R21): pesan Indonesia per field untuk form kategori kamar.
 * `beds_available` hanya divalidasi saat buat; saat ubah ketersediaan hanya lewat aksi terpisah (F4B-R18).
 */
class RoomCategoryRequestTest extends TestCase
{
    use InteractsWithFacilities;
    use InteractsWithStaff;
    use RefreshDatabase;

    /**
     * Aturan yang berlaku untuk tambah dan ubah.
     *
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function invalidInputs(): array
    {
        $thirtyOne = implode("\n", array_map(fn (int $number): string => 'Butir '.$number, range(1, 31)));

        return [
            'nama kosong' => [['name' => ''], 'name', 'Nama kategori kamar wajib diisi.'],
            'nama bukan teks' => [['name' => ['a']], 'name', 'Nama kategori kamar harus berupa teks.'],
            'nama terlalu pendek' => [['name' => 'Ab'], 'name', 'Nama kategori kamar minimal 3 karakter.'],
            'nama terlalu panjang' => [['name' => str_repeat('a', 101)], 'name', 'Nama kategori kamar maksimal 100 karakter.'],
            'nama ganda' => [['name' => 'VIP Contoh'], 'name', 'Nama kategori kamar sudah digunakan.'],
            'nama ganda beda huruf besar kecil' => [['name' => 'vip contoh'], 'name', 'Nama kategori kamar sudah digunakan.'],
            'deskripsi singkat kosong' => [['short_description' => ''], 'short_description', 'Deskripsi singkat wajib diisi.'],
            'deskripsi singkat bukan teks' => [['short_description' => ['a']], 'short_description', 'Deskripsi singkat harus berupa teks.'],
            'deskripsi singkat terlalu panjang' => [['short_description' => str_repeat('a', 256)], 'short_description', 'Deskripsi singkat maksimal 255 karakter.'],
            'deskripsi bukan teks' => [['description' => ['a']], 'description', 'Deskripsi harus berupa teks.'],
            'deskripsi terlalu panjang' => [['description' => str_repeat('a', 30001)], 'description', 'Deskripsi maksimal 30.000 karakter.'],
            'fasilitas lebih dari 30 butir' => [['amenities' => $thirtyOne], 'amenities', 'Fasilitas kamar maksimal 30 butir.'],
            'butir fasilitas terlalu pendek' => [['amenities' => "AC\nA"], 'amenities.1', 'Setiap butir fasilitas harus 2 sampai 80 karakter.'],
            'butir fasilitas terlalu panjang' => [['amenities' => str_repeat('a', 81)], 'amenities.0', 'Setiap butir fasilitas harus 2 sampai 80 karakter.'],
            'butir fasilitas bukan teks' => [['amenities' => [['x']]], 'amenities.0', 'Setiap butir fasilitas harus 2 sampai 80 karakter.'],
            'butir fasilitas ganda' => [['amenities' => "AC\nTV\nAC"], 'amenities.2', 'Butir fasilitas tidak boleh sama.'],
            'kapasitas kosong' => [['bed_capacity' => ''], 'bed_capacity', 'Kapasitas tempat tidur wajib diisi.'],
            'kapasitas bukan angka bulat' => [['bed_capacity' => 'abc'], 'bed_capacity', 'Kapasitas tempat tidur harus berupa angka bulat.'],
            'kapasitas desimal' => [['bed_capacity' => '5.5'], 'bed_capacity', 'Kapasitas tempat tidur harus berupa angka bulat.'],
            'kapasitas nol' => [['bed_capacity' => '0'], 'bed_capacity', 'Kapasitas tempat tidur harus antara 1 dan 500.'],
            'kapasitas terlalu besar' => [['bed_capacity' => '501'], 'bed_capacity', 'Kapasitas tempat tidur harus antara 1 dan 500.'],
            'tarif bukan angka' => [['sample_rate' => 'abc'], 'sample_rate', 'Tarif contoh harus berupa angka.'],
            'tarif negatif' => [['sample_rate' => '-1'], 'sample_rate', 'Tarif contoh tidak boleh negatif.'],
            'tarif terlalu besar' => [['sample_rate' => '10000000000'], 'sample_rate', 'Tarif contoh terlalu besar.'],
            'tarif tiga desimal' => [['sample_rate' => '12.345'], 'sample_rate', 'Tarif contoh maksimal 2 angka desimal.'],
            'satuan tarif kosong' => [['rate_unit' => ''], 'rate_unit', 'Satuan tarif wajib diisi.'],
            'satuan tarif bukan teks' => [['rate_unit' => ['a']], 'rate_unit', 'Satuan tarif harus berupa teks.'],
            'satuan tarif terlalu panjang' => [['rate_unit' => str_repeat('a', 31)], 'rate_unit', 'Satuan tarif maksimal 30 karakter.'],
            'catatan tarif bukan teks' => [['rate_note' => ['a']], 'rate_note', 'Catatan tarif harus berupa teks.'],
            'catatan tarif terlalu panjang' => [['rate_note' => str_repeat('a', 256)], 'rate_note', 'Catatan tarif maksimal 255 karakter.'],
            'status aktif hilang' => [['is_active' => null], 'is_active', 'Status aktif tidak valid.'],
            'status aktif tidak valid' => [['is_active' => 'mungkin'], 'is_active', 'Status aktif tidak valid.'],
            'urutan bukan angka' => [['sort_order' => 'abc'], 'sort_order', 'Urutan harus berupa angka bulat.'],
            'urutan terlalu besar' => [['sort_order' => '10000'], 'sort_order', 'Urutan harus antara 0 dan 9999.'],
            'meta title terlalu panjang' => [['meta_title' => str_repeat('a', 71)], 'meta_title', 'Meta title maksimal 70 karakter.'],
            'meta description terlalu panjang' => [['meta_description' => str_repeat('a', 161)], 'meta_description', 'Meta description maksimal 160 karakter.'],
            'alt sampul wajib' => [['cover' => 'FILE', 'cover_alt' => ''], 'cover_alt', 'Teks alternatif gambar wajib diisi.'],
            'alt sampul terlalu panjang' => [['cover' => 'FILE', 'cover_alt' => str_repeat('a', 256)], 'cover_alt', 'Teks alternatif gambar maksimal 255 karakter.'],
            'sampul terlalu kecil' => [['cover' => 'SMALL', 'cover_alt' => 'x'], 'cover', 'Dimensi gambar minimal 800 x 450 piksel dan maksimal 6000 x 6000 piksel.'],
            'sampul terlalu besar' => [['cover' => 'BIG', 'cover_alt' => 'x'], 'cover', 'Ukuran gambar maksimal 2 MB.'],
            'galeri terlalu kecil' => [['gallery' => ['SMALL'], 'gallery_alt' => ['x']], 'gallery.0', 'Dimensi gambar minimal 600 x 400 piksel dan maksimal 6000 x 6000 piksel.'],
            'alt galeri wajib' => [['gallery' => ['GALLERY'], 'gallery_alt' => ['']], 'gallery_alt.0', 'Teks alternatif gambar wajib diisi.'],
        ];
    }

    /**
     * Aturan `beds_available` yang hanya berlaku saat buat (V-28).
     *
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function invalidCreateOnlyInputs(): array
    {
        return [
            'tersedia kosong' => [['beds_available' => ''], 'beds_available', 'Jumlah tempat tidur tersedia wajib diisi.'],
            'tersedia bukan angka bulat' => [['beds_available' => 'abc'], 'beds_available', 'Jumlah tempat tidur tersedia harus berupa angka bulat.'],
            'tersedia negatif' => [['beds_available' => '-1'], 'beds_available', 'Jumlah tempat tidur tersedia tidak boleh kurang dari 0.'],
            'tersedia melebihi kapasitas' => [['bed_capacity' => '10', 'beds_available' => '11'], 'beds_available', 'Jumlah tempat tidur tersedia tidak boleh melebihi kapasitas.'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function valid(): array
    {
        return [
            'name' => 'Kategori Baru Contoh',
            'short_description' => 'Ringkasan kategori.',
            'bed_capacity' => '10',
            'beds_available' => '4',
            'rate_unit' => 'per malam',
            'is_active' => '1',
        ];
    }

    /**
     * Penanda berkas diubah menjadi UploadedFile di dalam tes (data provider dijalankan sebelum GD siap dipakai).
     */
    private function materialize(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($item) => $this->materialize($item), $value);
        }

        return match ($value) {
            'FILE' => ImageFixtures::jpeg(1200, 800),
            'SMALL' => ImageFixtures::jpeg(400, 300),
            'GALLERY' => ImageFixtures::jpeg(900, 600),
            'BIG' => ImageFixtures::oversizedJpeg(2100, 1200, 800),
            default => $value,
        };
    }

    /**
     * @param  array<string, mixed>  $override
     */
    #[DataProvider('invalidInputs')]
    public function test_store_rejects_invalid_input_with_indonesian_messages(array $override, string $field, string $message): void
    {
        $this->assertStoreRejects($override, $field, $message);
    }

    /**
     * @param  array<string, mixed>  $override
     */
    #[DataProvider('invalidCreateOnlyInputs')]
    public function test_store_rejects_invalid_initial_availability(array $override, string $field, string $message): void
    {
        $this->assertStoreRejects($override, $field, $message);
    }

    /**
     * @param  array<string, mixed>  $override
     */
    private function assertStoreRejects(array $override, string $field, string $message): void
    {
        Storage::fake('public');
        $this->createRoomCategory(['name' => 'VIP Contoh']);
        $override = array_map(fn ($value) => $this->materialize($value), $override);

        $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.room-categories.create'))
            ->post(route('admin.room-categories.store'), array_merge($this->valid(), $override))
            ->assertRedirect(route('admin.room-categories.create'))
            ->assertSessionHasErrors([$field => $message]);

        $this->assertSame(1, RoomCategory::query()->count());
        $this->assertSame(0, Media::query()->count());
    }

    /**
     * @param  array<string, mixed>  $override
     */
    #[DataProvider('invalidInputs')]
    public function test_update_applies_the_same_rules_and_keeps_the_old_row(array $override, string $field, string $message): void
    {
        Storage::fake('public');
        $this->createRoomCategory(['name' => 'VIP Contoh']);
        $target = $this->createRoomCategory(['name' => 'Kamar Sasaran', 'bed_capacity' => 12, 'beds_available' => 4]);
        $override = array_map(fn ($value) => $this->materialize($value), $override);

        $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.room-categories.edit', $target))
            ->put(route('admin.room-categories.update', $target), array_merge($this->valid(), $override))
            ->assertRedirect(route('admin.room-categories.edit', $target))
            ->assertSessionHasErrors([$field => $message]);

        $this->assertSame('Kamar Sasaran', $target->fresh()->name);
        $this->assertSame(12, $target->fresh()->bed_capacity);
        $this->assertSame(0, Media::query()->count());
    }

    public function test_beds_available_is_ignored_on_update_even_when_it_is_invalid(): void
    {
        $target = $this->createRoomCategory(['name' => 'Kamar Sasaran', 'bed_capacity' => 12, 'beds_available' => 4]);

        $this->actingAs($this->createStaff('content-admin'))
            ->put(route('admin.room-categories.update', $target), array_merge($this->valid(), ['bed_capacity' => '12', 'beds_available' => '999']))
            ->assertSessionHasNoErrors();

        $this->assertSame(4, $target->fresh()->beds_available);
    }

    public function test_capacity_error_for_a_non_integer_does_not_also_report_the_below_availability_error(): void
    {
        $target = $this->createRoomCategory(['name' => 'Kamar Sasaran', 'bed_capacity' => 12, 'beds_available' => 4]);

        $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.room-categories.edit', $target))
            ->put(route('admin.room-categories.update', $target), array_merge($this->valid(), ['bed_capacity' => 'abc']))
            ->assertSessionHasErrors(['bed_capacity' => 'Kapasitas tempat tidur harus berupa angka bulat.']);

        $errors = session('errors')->get('bed_capacity');

        $this->assertSame(['Kapasitas tempat tidur harus berupa angka bulat.'], $errors);
    }

    public function test_the_slug_field_is_not_accepted_from_the_form(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->post(route('admin.room-categories.store'), array_merge($this->valid(), ['slug' => 'slug-dari-form']))
            ->assertSessionHasNoErrors();

        $this->assertSame('kategori-baru-contoh', RoomCategory::query()->sole()->slug);
    }

    public function test_invalid_form_keeps_old_input_and_renders_the_amenities_back_one_per_line(): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->from(route('admin.room-categories.create'))
            ->post(route('admin.room-categories.store'), array_merge($this->valid(), [
                'name' => '',
                'amenities' => "AC\nTelevisi\nA",
                'rate_note' => 'Catatan Lama',
            ]))
            ->assertRedirect(route('admin.room-categories.create'));

        $this->get(route('admin.room-categories.create'))
            ->assertOk()
            ->assertSee('data-errors-summary', false)
            ->assertSee('Nama kategori kamar wajib diisi.')
            ->assertSee('Setiap butir fasilitas harus 2 sampai 80 karakter.')
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('>AC'."\n".'Televisi'."\n".'A</textarea>', false)
            ->assertSee('value="Catatan Lama"', false);
    }
}
```

- [ ] **Step 4: Tulis tes yang gagal (tool Write) `tests/Feature/Admin/RoomAvailabilityTest.php`**

```php
<?php

namespace Tests\Feature\Admin;

use App\Enums\ActorType;
use App\Http\Requests\Admin\UpdateRoomAvailabilityRequest;
use App\Models\AuditLog;
use App\Models\RoomCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithFacilities;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * Aksi ketersediaan kamar (T5, F4B-R17, 06 §13, V-29, Review Focus 1): batas 0..kapasitas, izin Petugas, penanda waktu
 * dan petugas, satu audit `room.availability_updated`, dan CHECK database yang tidak pernah memicu 500.
 */
class RoomAvailabilityTest extends TestCase
{
    use InteractsWithFacilities;
    use InteractsWithStaff;
    use RefreshDatabase;

    private const OLD_STAMP = '2026-10-05 08:00:00';

    protected function setUp(): void
    {
        parent::setUp();

        // Selasa, 6 Oktober 2026 pukul 10.30 WIB.
        $this->travelTo(Carbon::parse('2026-10-06 10:30:00'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function room(array $overrides = []): RoomCategory
    {
        return $this->createRoomCategory(array_merge([
            'name' => 'VIP Contoh',
            'bed_capacity' => 12,
            'beds_available' => 4,
            'availability_updated_at' => Carbon::parse(self::OLD_STAMP),
            'availability_updated_by' => null,
        ], $overrides));
    }

    private function indexUrl(): string
    {
        return route('admin.room-categories.index');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function allowedRoles(): array
    {
        return [
            'petugas pendaftaran' => ['registration-officer'],
            'admin konten' => ['content-admin'],
            'super admin' => ['super-admin'],
        ];
    }

    #[DataProvider('allowedRoles')]
    public function test_staff_with_the_availability_permission_updates_the_count_and_the_metadata(string $role): void
    {
        $room = $this->room();
        $user = $this->createStaff($role);

        $this->actingAs($user)
            ->from($this->indexUrl())
            ->patch(route('admin.room-categories.availability', $room), ['beds_available' => '2', 'availability_for' => (string) $room->id])
            ->assertSessionHasNoErrors()
            ->assertRedirect($this->indexUrl())
            ->assertSessionHas('success', 'Ketersediaan VIP Contoh berhasil diperbarui.');

        $room->refresh();
        $this->assertSame(2, $room->beds_available);
        $this->assertSame(12, $room->bed_capacity);
        $this->assertTrue($room->availability_updated_at->equalTo(now()));
        $this->assertSame($user->id, $room->availability_updated_by);
    }

    public function test_exactly_one_availability_audit_is_written_with_old_and_new_values_and_no_generic_update_audit(): void
    {
        $room = $this->room();
        $user = $this->createStaff('registration-officer');

        $this->actingAs($user)
            ->patch(route('admin.room-categories.availability', $room), ['beds_available' => '2'])
            ->assertSessionHasNoErrors();

        $log = AuditLog::query()->where('action', 'room.availability_updated')->sole();

        $this->assertSame(['beds_available' => 4], $log->old_values);
        $this->assertSame(['beds_available' => 2], $log->new_values);
        $this->assertSame('Ketersediaan VIP Contoh diperbarui dari 4 menjadi 2 tempat tidur.', $log->description);
        $this->assertSame(RoomCategory::class, $log->auditable_type);
        $this->assertSame($room->id, $log->auditable_id);
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame(ActorType::Staff, $log->actor_type);
        $this->assertSame(0, AuditLog::query()->where('action', 'room_category.updated')->count());
    }

    public function test_the_same_value_still_refreshes_the_timestamp_as_a_confirmation(): void
    {
        $room = $this->room();
        $user = $this->createStaff('registration-officer');
        $this->travelTo(Carbon::parse('2026-10-06 14:00:00'));

        $this->actingAs($user)
            ->patch(route('admin.room-categories.availability', $room), ['beds_available' => '4'])
            ->assertSessionHasNoErrors();

        $room->refresh();
        $this->assertSame(4, $room->beds_available);
        $this->assertSame('2026-10-06 14:00:00', $room->availability_updated_at->toDateTimeString());
        $this->assertSame($user->id, $room->availability_updated_by);
    }

    public function test_an_inactive_room_can_be_updated(): void
    {
        $room = $this->room(['is_active' => false]);

        $this->actingAs($this->createStaff('registration-officer'))
            ->patch(route('admin.room-categories.availability', $room), ['beds_available' => '1'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $room->fresh()->beds_available);
    }

    /**
     * @return array<string, array{mixed, ?string}>
     */
    public static function boundaries(): array
    {
        return [
            'nol (penuh)' => ['0', null],
            'sama dengan kapasitas' => ['12', null],
            'kapasitas ditambah satu' => ['13', 'Jumlah tempat tidur tersedia tidak boleh melebihi kapasitas (12 tempat tidur).'],
            'sangat besar' => ['99999', 'Jumlah tempat tidur tersedia tidak boleh melebihi kapasitas (12 tempat tidur).'],
            'negatif' => ['-1', 'Jumlah tempat tidur tersedia tidak boleh kurang dari 0.'],
            'huruf' => ['abc', 'Jumlah tempat tidur tersedia harus berupa angka bulat.'],
            'desimal' => ['5.5', 'Jumlah tempat tidur tersedia harus berupa angka bulat.'],
            'kosong' => ['', 'Jumlah tempat tidur tersedia wajib diisi.'],
            'larik' => [['3'], 'Jumlah tempat tidur tersedia harus berupa angka bulat.'],
        ];
    }

    #[DataProvider('boundaries')]
    public function test_the_value_must_be_between_zero_and_the_capacity(mixed $value, ?string $error): void
    {
        $room = $this->room();
        $this->actingAs($this->createStaff('registration-officer'));

        $response = $this->from($this->indexUrl())
            ->patch(route('admin.room-categories.availability', $room), ['beds_available' => $value, 'availability_for' => (string) $room->id]);

        $room->refresh();

        if ($error === null) {
            $response->assertSessionHasNoErrors();
            $this->assertSame((int) $value, $room->beds_available);
            $this->assertSame(1, AuditLog::query()->where('action', 'room.availability_updated')->count());

            return;
        }

        $response->assertRedirect($this->indexUrl())->assertSessionHasErrors(['beds_available' => $error]);
        $this->assertSame(4, $room->beds_available);
        $this->assertSame(self::OLD_STAMP, $room->availability_updated_at->toDateTimeString());
        $this->assertNull($room->availability_updated_by);
        $this->assertSame(0, AuditLog::query()->where('action', 'room.availability_updated')->count());
    }

    public function test_the_validation_error_appears_only_in_the_submitted_row_with_the_typed_value(): void
    {
        $a = $this->room(['name' => 'BPJS Kelas 1 Contoh', 'sort_order' => 1, 'bed_capacity' => 12, 'beds_available' => 4]);
        $b = $this->room(['name' => 'BPJS Kelas 2 Contoh', 'sort_order' => 2, 'bed_capacity' => 16, 'beds_available' => 7]);
        $message = 'Jumlah tempat tidur tersedia tidak boleh melebihi kapasitas (12 tempat tidur).';
        $this->actingAs($this->createStaff('registration-officer'));

        $this->from($this->indexUrl())
            ->patch(route('admin.room-categories.availability', $a), ['beds_available' => '13', 'availability_for' => (string) $a->id])
            ->assertRedirect($this->indexUrl());

        $html = $this->get($this->indexUrl())->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, $message));
        $this->assertStringContainsString('name="beds_available" value="13" min="0" max="12"', $html);
        $this->assertStringContainsString('name="beds_available" value="7" min="0" max="16"', $html);
        $this->assertTrue(strpos($html, $message) < strpos($html, 'BPJS Kelas 2 Contoh'));
        $this->assertTrue(strpos($html, 'BPJS Kelas 1 Contoh') < strpos($html, $message));
        $this->assertSame(4, $a->fresh()->beds_available);
        $this->assertSame(7, $b->fresh()->beds_available);
    }

    public function test_array_input_does_not_break_the_next_index_render(): void
    {
        $room = $this->room();
        $this->actingAs($this->createStaff('registration-officer'));

        $this->from($this->indexUrl())
            ->patch(route('admin.room-categories.availability', $room), ['beds_available' => ['3'], 'availability_for' => [(string) $room->id]])
            ->assertRedirect($this->indexUrl())
            ->assertSessionHasErrors(['beds_available', 'availability_for']);

        $this->get($this->indexUrl())->assertOk()->assertSee('VIP Contoh');
    }

    public function test_the_controller_rechecks_the_capacity_under_lock_even_if_validation_was_bypassed(): void
    {
        $room = $this->room();
        $this->app->bind(UpdateRoomAvailabilityRequest::class, fn () => new class extends UpdateRoomAvailabilityRequest
        {
            public function rules(): array
            {
                return ['beds_available' => ['required', 'integer', 'min:0'], 'availability_for' => ['nullable', 'integer']];
            }
        });
        $this->actingAs($this->createStaff('registration-officer'));

        $this->from($this->indexUrl())
            ->patch(route('admin.room-categories.availability', $room), ['beds_available' => '99', 'availability_for' => (string) $room->id])
            ->assertRedirect($this->indexUrl())
            ->assertSessionHasErrors(['beds_available' => 'Jumlah tempat tidur tersedia tidak boleh melebihi kapasitas (12 tempat tidur).']);

        $room->refresh();
        $this->assertSame(4, $room->beds_available);
        $this->assertSame(self::OLD_STAMP, $room->availability_updated_at->toDateTimeString());
        $this->assertSame(0, AuditLog::query()->where('action', 'room.availability_updated')->count());
    }

    public function test_patient_and_guest_cannot_update_availability_and_unknown_ids_are_404(): void
    {
        $room = $this->room();
        $url = route('admin.room-categories.availability', $room);

        $this->actingAs(User::factory()->patient()->create())
            ->patch($url, ['beds_available' => '1'])
            ->assertForbidden();

        $this->app['auth']->forgetGuards();

        $this->patch($url, ['beds_available' => '1'])->assertRedirect(route('admin.login'));

        $this->actingAs($this->createStaff('registration-officer'));
        $this->patch('/admin/kamar/abc/ketersediaan', ['beds_available' => '1'])->assertNotFound();
        $this->patch('/admin/kamar/999999/ketersediaan', ['beds_available' => '1'])->assertNotFound();

        $this->assertSame(4, $room->fresh()->beds_available);
    }

    public function test_a_role_without_the_availability_permission_is_rejected_by_the_route_middleware(): void
    {
        $room = $this->room();
        $user = $this->createStaff('content-admin');
        $user->syncRoles([]);
        $user->givePermissionTo('facilities.manage');

        $this->actingAs($user)
            ->patch(route('admin.room-categories.availability', $room), ['beds_available' => '1'])
            ->assertForbidden();

        $this->assertSame(4, $room->fresh()->beds_available);
    }
}
```

- [ ] **Step 5: Jalankan tes, harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=AdminRoomCategoriesTest
& $PHP artisan test --filter=RoomCategoryRequestTest
& $PHP artisan test --filter=RoomAvailabilityTest
```
Expected: FAIL. Tes gagal dengan `Route [admin.room-categories.index] not defined.` (atau `…create`/`…store`/`…availability` not defined); tes yang memakai URL literal gagal dengan 404.

- [ ] **Step 6: Implementasi `RoomCategoryPolicy` (tool Write) `app/Policies/RoomCategoryPolicy.php`**

```php
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\RoomCategory;
use App\Models\User;

/**
 * 04 §5 / 11 §8 (F4B-R15): daftar terbuka bagi `facilities.manage` ATAU `rooms.update-availability` (D-12);
 * struktur kategori (lihat, buat, ubah, hapus) memerlukan `facilities.manage`; ketersediaan memerlukan
 * `rooms.update-availability`. Kategori kamar tidak dirujuk tabel lain, jadi hapus tidak punya penolakan (CRD-13).
 */
final class RoomCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAny(['facilities.manage', 'rooms.update-availability']);
    }

    public function view(User $user, RoomCategory $roomCategory): bool
    {
        return $user->can('facilities.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('facilities.manage');
    }

    public function update(User $user, RoomCategory $roomCategory): bool
    {
        return $user->can('facilities.manage');
    }

    public function delete(User $user, RoomCategory $roomCategory): bool
    {
        return $user->can('facilities.manage');
    }

    public function updateAvailability(User $user, RoomCategory $roomCategory): bool
    {
        return $user->can('rooms.update-availability');
    }
}
```

- [ ] **Step 7: Implementasi `RoomCategoryRequest` (tool Write) `app/Http/Requests/Admin/RoomCategoryRequest.php`**

```php
<?php

namespace App\Http\Requests\Admin;

use App\Models\RoomCategory;
use App\Rules\ImageRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * V-28 + F4B-R3 (description max 30000), F4B-R4, F4B-R5 (tanpa input slug), F4B-R18 (kapasitas dan ketersediaan awal),
 * F4B-R19 (amenities satu per baris), F4B-R21 (cover dan galeri). Satu kelas untuk tambah dan ubah.
 * `beds_available` hanya divalidasi saat buat; saat ubah ketersediaan hanya lewat aksi `availability`.
 */
class RoomCategoryRequest extends FormRequest
{
    public const MAX_GALLERY = 20;

    /**
     * BR-115. Dipakai juga controller saat memeriksa ulang di bawah kunci baris.
     */
    public static function belowAvailableMessage(int $available): string
    {
        return "Kapasitas tidak boleh lebih kecil dari tempat tidur tersedia saat ini ({$available}). Perbarui ketersediaan terlebih dahulu.";
    }

    public function authorize(): bool
    {
        $user = $this->user();
        $room = $this->route('roomCategory');

        if ($user === null) {
            return false;
        }

        return $room instanceof RoomCategory
            ? $user->can('update', $room)
            : $user->can('create', RoomCategory::class);
    }

    /**
     * F4B-R19: textarea `amenities` dipecah per baris, di-trim, dan baris kosong dibuang. Nilai yang sudah berupa larik
     * dibiarkan agar validasi per butir menolaknya.
     */
    protected function prepareForValidation(): void
    {
        $amenities = $this->input('amenities');

        if (! is_string($amenities)) {
            return;
        }

        $lines = preg_split('/\R/u', $amenities) ?: [];
        $items = array_values(array_filter(
            array_map('trim', $lines),
            fn (string $line): bool => $line !== '',
        ));

        $this->merge(['amenities' => $items]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $room = $this->route('roomCategory');
        $room = $room instanceof RoomCategory ? $room : null;

        $rules = [
            'name' => ['bail', 'required', 'string', 'min:3', 'max:100', Rule::unique('room_categories', 'name')->ignore($room)],
            'short_description' => ['bail', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:30000'],
            'amenities' => ['nullable', 'array', 'max:30'],
            'amenities.*' => ['string', 'min:2', 'max:80', 'distinct'],
            'bed_capacity' => ['bail', 'required', 'integer', 'between:1,500'],
            'sample_rate' => ['bail', 'nullable', 'numeric', 'min:0', 'max:9999999999', 'decimal:0,2'],
            'rate_unit' => ['bail', 'required', 'string', 'max:30'],
            'rate_note' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'between:0,9999'],
            'meta_title' => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:160'],
            'cover' => ['nullable', ...ImageRules::for('cover')],
            'cover_alt' => ['required_with:cover', 'nullable', 'string', 'max:255'],
            'gallery' => ['nullable', 'array', 'max:'.self::MAX_GALLERY],
            'gallery.*' => ImageRules::for('gallery'),
        ];

        if ($room === null) {
            $rules['beds_available'] = ['bail', 'required', 'integer', 'min:0', 'lte:bed_capacity'];
        }

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
                'name.required' => 'Nama kategori kamar wajib diisi.',
                'name.string' => 'Nama kategori kamar harus berupa teks.',
                'name.min' => 'Nama kategori kamar minimal 3 karakter.',
                'name.max' => 'Nama kategori kamar maksimal 100 karakter.',
                'name.unique' => 'Nama kategori kamar sudah digunakan.',
                'short_description.required' => 'Deskripsi singkat wajib diisi.',
                'short_description.string' => 'Deskripsi singkat harus berupa teks.',
                'short_description.max' => 'Deskripsi singkat maksimal 255 karakter.',
                'description.string' => 'Deskripsi harus berupa teks.',
                'description.max' => 'Deskripsi maksimal 30.000 karakter.',
                'amenities.array' => 'Fasilitas kamar tidak valid.',
                'amenities.max' => 'Fasilitas kamar maksimal 30 butir.',
                'amenities.*.string' => 'Setiap butir fasilitas harus 2 sampai 80 karakter.',
                'amenities.*.min' => 'Setiap butir fasilitas harus 2 sampai 80 karakter.',
                'amenities.*.max' => 'Setiap butir fasilitas harus 2 sampai 80 karakter.',
                'amenities.*.distinct' => 'Butir fasilitas tidak boleh sama.',
                'bed_capacity.required' => 'Kapasitas tempat tidur wajib diisi.',
                'bed_capacity.integer' => 'Kapasitas tempat tidur harus berupa angka bulat.',
                'bed_capacity.between' => 'Kapasitas tempat tidur harus antara 1 dan 500.',
                'beds_available.required' => 'Jumlah tempat tidur tersedia wajib diisi.',
                'beds_available.integer' => 'Jumlah tempat tidur tersedia harus berupa angka bulat.',
                'beds_available.min' => 'Jumlah tempat tidur tersedia tidak boleh kurang dari 0.',
                'beds_available.lte' => 'Jumlah tempat tidur tersedia tidak boleh melebihi kapasitas.',
                'sample_rate.numeric' => 'Tarif contoh harus berupa angka.',
                'sample_rate.min' => 'Tarif contoh tidak boleh negatif.',
                'sample_rate.max' => 'Tarif contoh terlalu besar.',
                'sample_rate.decimal' => 'Tarif contoh maksimal 2 angka desimal.',
                'rate_unit.required' => 'Satuan tarif wajib diisi.',
                'rate_unit.string' => 'Satuan tarif harus berupa teks.',
                'rate_unit.max' => 'Satuan tarif maksimal 30 karakter.',
                'rate_note.string' => 'Catatan tarif harus berupa teks.',
                'rate_note.max' => 'Catatan tarif maksimal 255 karakter.',
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
     * BR-115 (kapasitas tidak boleh di bawah ketersediaan saat ini), batas 20 gambar galeri (yang ada + baru),
     * dan alt cover yang kosong ditolak bila cover sudah ada (BR-132).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateCapacityAgainstAvailability($validator);
            $this->validateExistingCoverAlt($validator);

            $incoming = count($this->galleryFiles());

            if ($incoming === 0 || $validator->errors()->has('gallery')) {
                return;
            }

            $room = $this->route('roomCategory');
            $existing = $room instanceof RoomCategory ? $room->media()->where('collection', 'gallery')->count() : 0;

            if ($existing + $incoming > self::MAX_GALLERY) {
                $validator->errors()->add('gallery', 'Galeri maksimal 20 gambar.');
            }
        });
    }

    private function validateCapacityAgainstAvailability(Validator $validator): void
    {
        $room = $this->route('roomCategory');

        if (! $room instanceof RoomCategory || $validator->errors()->has('bed_capacity')) {
            return;
        }

        $capacity = $this->input('bed_capacity');

        if (is_numeric($capacity) && (int) $capacity < $room->beds_available) {
            $validator->errors()->add('bed_capacity', self::belowAvailableMessage($room->beds_available));
        }
    }

    private function validateExistingCoverAlt(Validator $validator): void
    {
        $room = $this->route('roomCategory');

        if (! $room instanceof RoomCategory || $this->coverFile() !== null || ! $this->has('cover_alt') || $this->coverAlt() !== '') {
            return;
        }

        if ($room->media()->where('collection', 'cover')->exists()) {
            $validator->errors()->add('cover_alt', 'Teks alternatif gambar wajib diisi.');
        }
    }

    /**
     * Atribut model tanpa ketersediaan: `beds_available` dan `availability_updated_*` ditangani controller (F4B-R18).
     *
     * @return array<string, mixed>
     */
    public function roomCategoryAttributes(): array
    {
        $data = $this->validated();
        $amenities = $data['amenities'] ?? [];

        return [
            'name' => (string) $data['name'],
            'short_description' => (string) $data['short_description'],
            'description' => $data['description'] ?? null,
            'amenities' => $amenities === [] ? null : array_values($amenities),
            'bed_capacity' => (int) $data['bed_capacity'],
            'sample_rate' => $data['sample_rate'] ?? null,
            'rate_unit' => (string) $data['rate_unit'],
            'rate_note' => $data['rate_note'] ?? null,
            'is_active' => (bool) $data['is_active'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
        ];
    }

    /**
     * Ketersediaan awal saat buat (aturan `beds_available` hanya ada pada permintaan buat).
     */
    public function initialBedsAvailable(): int
    {
        return (int) $this->validated('beds_available');
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
}
```

- [ ] **Step 8: Implementasi `UpdateRoomAvailabilityRequest` (tool Write) `app/Http/Requests/Admin/UpdateRoomAvailabilityRequest.php`**

```php
<?php

namespace App\Http\Requests\Admin;

use App\Models\RoomCategory;
use Illuminate\Foundation\Http\FormRequest;

/**
 * V-29 (BR-112, F4B-R17): `beds_available` bilangan bulat 0 sampai kapasitas kategori. `availability_for` hanyalah
 * penanda baris pada form inline daftar kamar, dipakai view untuk menampilkan galat di baris yang benar.
 */
class UpdateRoomAvailabilityRequest extends FormRequest
{
    /**
     * Dipakai juga controller saat memeriksa ulang kapasitas di bawah kunci baris.
     */
    public static function exceedsCapacityMessage(int $capacity): string
    {
        return "Jumlah tempat tidur tersedia tidak boleh melebihi kapasitas ({$capacity} tempat tidur).";
    }

    public function authorize(): bool
    {
        $user = $this->user();
        $room = $this->route('roomCategory');

        return $user !== null
            && $room instanceof RoomCategory
            && $user->can('updateAvailability', $room);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'beds_available' => ['bail', 'required', 'integer', 'min:0', 'max:'.$this->capacity()],
            'availability_for' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'beds_available.required' => 'Jumlah tempat tidur tersedia wajib diisi.',
            'beds_available.integer' => 'Jumlah tempat tidur tersedia harus berupa angka bulat.',
            'beds_available.min' => 'Jumlah tempat tidur tersedia tidak boleh kurang dari 0.',
            'beds_available.max' => self::exceedsCapacityMessage($this->capacity()),
            'availability_for.integer' => 'Penanda baris ketersediaan tidak valid.',
        ];
    }

    public function bedsAvailable(): int
    {
        return (int) $this->validated('beds_available');
    }

    private function capacity(): int
    {
        $room = $this->route('roomCategory');

        return $room instanceof RoomCategory ? $room->bed_capacity : 0;
    }
}
```

- [ ] **Step 9: Implementasi `RoomCategoryController` (tool Write) `app/Http/Controllers/Admin/RoomCategoryController.php`**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoomCategoryRequest;
use App\Http\Requests\Admin\UpdateRoomAvailabilityRequest;
use App\Models\RoomCategory;
use App\Services\Audit\AuditLogger;
use App\Services\Media\MediaService;
use App\Support\Actor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Admin #36 tab Kamar dan Kapasitas (F-ADM-10, F-ADM-11). Struktur kategori (`facilities.manage`) dan ketersediaan
 * manual (`rooms.update-availability`) dipisah: ketersediaan hanya berubah lewat `availability()`, yang menulis
 * audit `room.availability_updated` sendiri. Kategori kamar tidak dirujuk tabel lain, jadi hapus selalu boleh (F4B-R20).
 */
class RoomCategoryController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', RoomCategory::class);

        // Tanpa paginasi dan filter (F4B-R16); eager load wajib karena mode ketat.
        $rooms = RoomCategory::query()
            ->with(['availabilityUpdater:id,name', 'media'])
            ->ordered()
            ->get();

        return view('admin.room-categories.index', ['rooms' => $rooms]);
    }

    public function create(): View
    {
        $this->authorize('create', RoomCategory::class);

        return view('admin.room-categories.create');
    }

    public function store(RoomCategoryRequest $request, MediaService $media): RedirectResponse
    {
        $this->authorize('create', RoomCategory::class);

        DB::transaction(function () use ($request, $media): void {
            $room = RoomCategory::query()->create($request->roomCategoryAttributes() + [
                'beds_available' => $request->initialBedsAvailable(),
                'availability_updated_at' => now(),
                'availability_updated_by' => $request->user()?->id,
            ]);
            $this->saveMedia($request, $media, $room);
        });

        return redirect()->route('admin.room-categories.index')
            ->with('success', __('messages.created', ['entity' => __('messages.entities.room_category')]));
    }

    public function edit(RoomCategory $roomCategory): View
    {
        $this->authorize('update', $roomCategory);

        return view('admin.room-categories.edit', ['room' => $roomCategory->load('media')]);
    }

    /**
     * Mengubah kapasitas tidak mengubah `availability_updated_*` (F4B-R18). Baris dikunci dan kapasitas diperiksa
     * ulang terhadap ketersediaan terbaru supaya CHECK database tidak pernah memicu 500.
     */
    public function update(RoomCategoryRequest $request, RoomCategory $roomCategory, MediaService $media): RedirectResponse
    {
        $this->authorize('update', $roomCategory);

        DB::transaction(function () use ($request, $media, $roomCategory): void {
            $room = RoomCategory::query()->lockForUpdate()->findOrFail($roomCategory->getKey());
            $attributes = $request->roomCategoryAttributes();

            if ($attributes['bed_capacity'] < $room->beds_available) {
                throw ValidationException::withMessages([
                    'bed_capacity' => RoomCategoryRequest::belowAvailableMessage($room->beds_available),
                ]);
            }

            $room->update($attributes);
            $this->saveMedia($request, $media, $room);
        });

        return redirect()->route('admin.room-categories.index')
            ->with('success', __('messages.updated', ['entity' => __('messages.entities.room_category')]));
    }

    /**
     * Media ikut terhapus lewat trait HasMedia; transaksi wajib agar berkas dihapus setelah commit.
     */
    public function destroy(RoomCategory $roomCategory): RedirectResponse
    {
        $this->authorize('delete', $roomCategory);

        DB::transaction(fn (): ?bool => $roomCategory->delete());

        return redirect()->route('admin.room-categories.index')
            ->with('success', __('messages.deleted', ['entity' => __('messages.entities.room_category')]));
    }

    /**
     * F4B-R17: transaksi dengan kunci baris, `saveQuietly()` agar observer tidak menulis `room_category.updated`, dan
     * satu audit eksplisit. Nilai yang sama dengan yang tersimpan tetap memperbarui waktu (konfirmasi petugas).
     */
    public function availability(UpdateRoomAvailabilityRequest $request, RoomCategory $roomCategory, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('updateAvailability', $roomCategory);

        $user = $request->user();
        $new = $request->bedsAvailable();
        $name = $roomCategory->name;

        DB::transaction(function () use ($roomCategory, $audit, $user, $new, &$name): void {
            $room = RoomCategory::query()->lockForUpdate()->findOrFail($roomCategory->getKey());

            if ($new > $room->bed_capacity) {
                throw ValidationException::withMessages([
                    'beds_available' => UpdateRoomAvailabilityRequest::exceedsCapacityMessage($room->bed_capacity),
                ]);
            }

            $old = $room->beds_available;
            $name = $room->name;

            $room->beds_available = $new;
            $room->availability_updated_at = now();
            $room->availability_updated_by = $user->id;
            $room->saveQuietly();

            $audit->log(
                'room.availability_updated',
                $room,
                ['beds_available' => $old],
                ['beds_available' => $new],
                "Ketersediaan {$room->name} diperbarui dari {$old} menjadi {$new} tempat tidur.",
                Actor::staff($user),
            );
        });

        return redirect()->route('admin.room-categories.index')
            ->with('success', "Ketersediaan {$name} berhasil diperbarui.");
    }

    private function saveMedia(RoomCategoryRequest $request, MediaService $media, RoomCategory $room): void
    {
        $user = $request->user();
        $cover = $request->coverFile();

        if ($cover !== null) {
            $media->replace($room, 'cover', $cover, ['alt_text' => $request->coverAlt()], $user);
        } elseif ($request->has('cover_alt')) {
            // Tanpa berkas baru: alt yang diedit diterapkan ke cover yang ada (caption tidak diubah).
            $existing = $room->media()->where('collection', 'cover')->first();

            if ($existing !== null && $existing->alt_text !== $request->coverAlt()) {
                $media->updateMeta($existing, $request->coverAlt(), $existing->caption);
            }
        }

        foreach ($request->galleryFiles() as $index => $file) {
            $media->store($file, 'gallery', $room, ['alt_text' => $request->galleryAlt($index)], $user);
        }
    }
}
```

- [ ] **Step 10: Implementasi view (tool Write)**

`resources/views/admin/room-categories/index.blade.php`:

```blade
@php
    $canManage = auth()->user()?->can('create', \App\Models\RoomCategory::class) === true;
    // Old input dapat berupa larik bila permintaan berbahaya; hanya nilai skalar yang dipakai (catatan teknis 6).
    $oldFor = old('availability_for');
    $oldBeds = old('beds_available');
    $breadcrumbItems = [
        ['label' => 'Fasilitas dan Kamar', 'url' => route('admin.room-categories.index')],
        ['label' => 'Kamar dan Kapasitas'],
    ];
@endphp
<x-layouts.admin title="Kamar dan Kapasitas">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="$breadcrumbItems" />
    </x-slot:breadcrumb>

    @if ($canManage)
        <x-slot:actions>
            <a href="{{ route('admin.room-categories.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg" aria-hidden="true"></i> Tambah Kategori Kamar
            </a>
        </x-slot:actions>
    @endif

    <div class="mb-3">
        <x-module-tabs group="facilities" />
    </div>

    <p class="text-secondary mb-2">Ketersediaan tempat tidur diperbarui manual oleh petugas dan tampil di halaman publik beserta waktu pembaruannya.</p>
    @unless ($canManage)
        <p class="text-secondary">Anda hanya dapat memperbarui ketersediaan tempat tidur.</p>
    @endunless

    @if ($rooms->isEmpty())
        <x-empty-state title="Belum ada kategori kamar" message="Tambahkan kategori kamar rawat inap beserta kapasitas tempat tidurnya." icon="bi-building">
            @if ($canManage)
                <a href="{{ route('admin.room-categories.create') }}" class="btn btn-primary">Tambah Kategori Kamar</a>
            @endif
        </x-empty-state>
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle table-cards bg-white">
                <caption class="visually-hidden">Daftar kategori kamar dan ketersediaan tempat tidur</caption>
                <thead class="table-light">
                    <tr>
                        <th scope="col">Kategori</th>
                        <th scope="col">Kapasitas</th>
                        <th scope="col">Tersedia</th>
                        <th scope="col">Diperbarui</th>
                        <th scope="col">Tarif contoh</th>
                        <th scope="col">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rooms as $room)
                        <tr>
                            <td data-label="Kategori">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="admin-thumb">
                                        <x-image type="room" :media="$room->firstMedia('cover')" variant="thumb" ratio="4x3" :decorative="true" />
                                    </div>
                                    <div>
                                        <span class="fw-semibold">{{ $room->name }}</span>
                                        @if ($room->is_active)
                                            <span class="badge text-bg-success">Aktif</span>
                                        @else
                                            <span class="badge text-bg-secondary">Nonaktif</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td data-label="Kapasitas">{{ $room->bed_capacity }} tempat tidur</td>
                            <td data-label="Tersedia">
                                @can('updateAvailability', $room)
                                    @php
                                        $isThisRow = is_scalar($oldFor) && (string) $oldFor === (string) $room->id;
                                        $rowError = $isThisRow ? (string) $errors->first('beds_available') : '';
                                        $shown = $isThisRow && is_scalar($oldBeds) ? $oldBeds : $room->beds_available;
                                        $fieldId = 'beds-available-'.$room->id;
                                    @endphp
                                    <form method="POST" action="{{ route('admin.room-categories.availability', $room) }}" class="d-flex flex-wrap align-items-start gap-2" aria-label="Perbarui ketersediaan {{ $room->name }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="availability_for" value="{{ $room->id }}">
                                        <div>
                                            <label for="{{ $fieldId }}" class="visually-hidden">Tempat tidur tersedia untuk {{ $room->name }}</label>
                                            <input type="number" id="{{ $fieldId }}" name="beds_available" value="{{ $shown }}" min="0" max="{{ $room->bed_capacity }}" step="1" inputmode="numeric" required @if ($rowError !== '') aria-invalid="true" aria-describedby="{{ $fieldId }}-error" @endif class="form-control form-control-sm{{ $rowError !== '' ? ' is-invalid' : '' }}">
                                            <span class="small text-secondary">dari {{ $room->bed_capacity }}</span>
                                            @if ($rowError !== '')
                                                <div id="{{ $fieldId }}-error" class="invalid-feedback d-block">{{ $rowError }}</div>
                                            @endif
                                        </div>
                                        <x-loading-button variant="outline-primary" class="btn-sm" loading-text="Menyimpan…" aria-label="Simpan ketersediaan {{ $room->name }}">Simpan</x-loading-button>
                                    </form>
                                @else
                                    {{ $room->beds_available }} dari {{ $room->bed_capacity }}
                                @endcan
                            </td>
                            <td data-label="Diperbarui">
                                <span>{{ $room->availability_updated_label }}</span>
                                @if ($room->availabilityUpdater)
                                    <div class="small text-secondary">oleh {{ $room->availabilityUpdater->name }}</div>
                                @endif
                            </td>
                            <td data-label="Tarif contoh">
                                {{ $room->sample_rate_label }}
                                @if (filled($room->rate_note))
                                    <div class="small text-secondary">{{ $room->rate_note }}</div>
                                @endif
                            </td>
                            <td data-label="Aksi" class="table-actions">
                                @if ($canManage)
                                    <a href="{{ route('admin.room-categories.edit', $room) }}" class="btn btn-sm btn-outline-primary" aria-label="Ubah {{ $room->name }}">Ubah</a>
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modal-hapus-kamar" data-confirm-action="{{ route('admin.room-categories.destroy', $room) }}" data-confirm-message="Hapus {{ $room->name }}? Gambarnya ikut terhapus dan halaman publiknya tidak dapat dibuka lagi. Bila hanya ingin menyembunyikannya, nonaktifkan saja lewat tombol Ubah. Tindakan ini tidak dapat dibatalkan." aria-label="Hapus {{ $room->name }}">Hapus</button>
                                @else
                                    <span class="text-secondary">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($canManage)
        <x-confirm-modal
            id="modal-hapus-kamar"
            title="Hapus kategori kamar?"
            message="Kategori kamar beserta gambarnya akan dihapus permanen dan halaman publiknya tidak dapat dibuka lagi. Bila hanya ingin menyembunyikannya, nonaktifkan saja."
            confirm-label="Ya, hapus"
            variant="danger"
            method="DELETE" />
    @endif
</x-layouts.admin>
```

`resources/views/admin/room-categories/_form.blade.php`:

```blade
{{-- Variabel: $room (RoomCategory|null), $action, $submitLabel. Tanpa input slug (F4B-R5). --}}
@php
    $isEdit = $room !== null;

    // F4B-R19: old('amenities') berupa larik setelah validasi gagal; gabungkan dengan baris baru.
    $amenitiesValue = old('amenities', $room?->amenities ?? []);
    $amenitiesValue = is_array($amenitiesValue)
        ? implode("\n", array_filter($amenitiesValue, 'is_string'))
        : (is_scalar($amenitiesValue) ? (string) $amenitiesValue : '');
    $amenitiesError = (string) ($errors->first('amenities') ?: $errors->first('amenities.*'));

    $rate = $room?->sample_rate;
    $rateValue = $rate === null ? null : (str_contains((string) $rate, '.') ? rtrim(rtrim((string) $rate, '0'), '.') : (string) $rate);
@endphp
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
                    <x-form.input name="name" label="Nama kategori" :value="$room?->name" :required="true" maxlength="100" help="Nama dan klasifikasi adalah kebutuhan prototipe, bukan ketentuan BPJS yang sudah diverifikasi. Alamat halaman (slug) dibuat otomatis dan tidak berubah saat nama diubah." />
                    <x-form.textarea name="short_description" label="Deskripsi singkat" :value="$room?->short_description" :rows="2" maxlength="255" :required="true" help="Satu atau dua kalimat; tampil di kartu kategori." />
                    <x-form.rich-text name="description" label="Deskripsi lengkap" :value="$room?->description" :maxlength="30000" help="Tampil di halaman detail kamar. Gambar disisipkan lewat tombol gambar di editor dan wajib diberi teks alternatif." />

                    <div class="mb-3">
                        <label for="amenities" class="form-label fw-semibold">Fasilitas kamar (satu per baris)</label>
                        <textarea id="amenities" name="amenities" rows="6" @if ($amenitiesError !== '') aria-invalid="true" @endif aria-describedby="amenities-help{{ $amenitiesError !== '' ? ' amenities-error' : '' }}" class="form-control{{ $amenitiesError !== '' ? ' is-invalid' : '' }}">{{ $amenitiesValue }}</textarea>
                        <div id="amenities-help" class="form-text">Tulis satu fasilitas per baris, mis. AC, Televisi, Kamar mandi dalam. Maksimal 30 butir, tiap butir 2 sampai 80 karakter dan tidak boleh sama.</div>
                        @if ($amenitiesError !== '')
                            <div id="amenities-error" class="invalid-feedback d-block">{{ $amenitiesError }}</div>
                        @endif
                    </div>

                    <div class="row">
                        <div class="col-12 col-md-6">
                            <x-form.input name="bed_capacity" type="number" label="Kapasitas tempat tidur" :value="$room?->bed_capacity" :required="true" inputmode="numeric" min="1" max="500" help="Total tempat tidur kategori ini (1 sampai 500)." />
                        </div>
                        <div class="col-12 col-md-6">
                            @if ($isEdit)
                                <p class="form-label fw-semibold mb-1">Tersedia saat ini</p>
                                <p class="mb-1">Tempat tidur tersedia saat ini: {{ $room->beds_available }} dari {{ $room->bed_capacity }}.</p>
                                <p class="form-text">Ketersediaan diubah dari daftar <a href="{{ route('admin.room-categories.index') }}">Kamar dan Kapasitas</a>. Mengubah kapasitas tidak mengubah waktu pembaruan ketersediaan.</p>
                            @else
                                <x-form.input name="beds_available" type="number" label="Tempat tidur tersedia" value="" :required="true" inputmode="numeric" min="0" max="500" help="Jumlah yang tersedia sekarang; tidak boleh melebihi kapasitas. Waktu dan petugas pembaruan dicatat otomatis." />
                            @endif
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12 col-md-4">
                            <x-form.input name="sample_rate" type="number" label="Tarif contoh (Rp)" :value="$rateValue" inputmode="decimal" min="0" step="0.01" help="Opsional. Isi angka tanpa titik pemisah ribuan, mis. 400000. Kosong = halaman publik menampilkan Hubungi kami." />
                        </div>
                        <div class="col-12 col-md-4">
                            <x-form.input name="rate_unit" label="Satuan tarif" :value="$room?->rate_unit ?? 'per malam'" :required="true" maxlength="30" />
                        </div>
                        <div class="col-12 col-md-4">
                            <x-form.input name="rate_note" label="Catatan tarif" :value="$room?->rate_note" maxlength="255" help="Mis. Tarif contoh, belum final." />
                        </div>
                    </div>

                    <details class="mb-3" @if ($errors->has('meta_title') || $errors->has('meta_description')) open @endif>
                        <summary class="fw-semibold mb-2">Pengaturan SEO (opsional)</summary>
                        <x-form.input name="meta_title" label="Meta title" :value="$room?->meta_title" maxlength="70" help="Maksimal 70 karakter. Kosong = memakai nama kategori." />
                        <x-form.textarea name="meta_description" label="Meta description" :value="$room?->meta_description" :rows="2" maxlength="160" help="Maksimal 160 karakter. Kosong = memakai deskripsi singkat." />
                    </details>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card mb-4">
                <div class="card-header fw-semibold">Status</div>
                <div class="card-body">
                    <x-form.checkbox name="is_active" label="Aktif (tampil di halaman Kamar Rawat Inap)" :checked="$room?->is_active ?? true" :with-hidden="true" />
                    <x-form.input name="sort_order" type="number" label="Urutan tampil" :value="$room?->sort_order ?? 0" inputmode="numeric" min="0" max="9999" help="Angka kecil tampil lebih dulu." />
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header fw-semibold">Foto sampul</div>
                <div class="card-body">
                    <x-form.file name="cover" label="Foto sampul" placeholder="room" :current-media="$room?->firstMedia('cover')" alt-name="cover_alt" :alt-value="old('cover_alt', $room?->firstMedia('cover')?->alt_text)" help="Minimal 800 x 450 piksel. Tanpa foto, halaman publik memakai gambar pengganti." />
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header fw-semibold">Tambah foto galeri</div>
                <div class="card-body">
                    <p class="form-text">Maksimal 20 foto per kategori. JPG, PNG, atau WebP, maksimal 2 MB, minimal 600 x 400 piksel. Teks alternatif wajib untuk setiap foto. Untuk menambah lebih banyak, simpan lalu ubah lagi.</p>
                    @for ($i = 0; $i < 3; $i++)
                        <div class="mb-3">
                            <label for="gallery-{{ $i }}" class="form-label fw-semibold">Foto galeri {{ $i + 1 }}</label>
                            <input type="file" id="gallery-{{ $i }}" name="gallery[{{ $i }}]" accept="image/jpeg,image/png,image/webp" class="form-control{{ $errors->has('gallery.'.$i) ? ' is-invalid' : '' }}" data-max-kb="2048">
                            @if ($errors->has('gallery.'.$i))
                                <div class="invalid-feedback d-block">{{ $errors->first('gallery.'.$i) }}</div>
                            @endif
                            <label for="gallery_alt-{{ $i }}" class="form-label visually-hidden">Teks alternatif foto galeri {{ $i + 1 }}</label>
                            <input type="text" id="gallery_alt-{{ $i }}" name="gallery_alt[{{ $i }}]" value="{{ old('gallery_alt.'.$i) }}" maxlength="255" placeholder="Teks alternatif foto {{ $i + 1 }}" class="form-control mt-1{{ $errors->has('gallery_alt.'.$i) ? ' is-invalid' : '' }}">
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
        <a href="{{ route('admin.room-categories.index') }}" class="btn btn-outline-secondary">Batal</a>
        <x-loading-button icon="bi-check-lg" loading-text="Menyimpan…">{{ $submitLabel }}</x-loading-button>
    </div>
</form>
```

`resources/views/admin/room-categories/create.blade.php`:

```blade
@php
    $breadcrumbItems = [
        ['label' => 'Fasilitas dan Kamar', 'url' => route('admin.room-categories.index')],
        ['label' => 'Kamar dan Kapasitas', 'url' => route('admin.room-categories.index')],
        ['label' => 'Tambah'],
    ];
@endphp
<x-layouts.admin title="Tambah Kategori Kamar">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="$breadcrumbItems" />
    </x-slot:breadcrumb>

    @include('admin.room-categories._form', [
        'room' => null,
        'action' => route('admin.room-categories.store'),
        'submitLabel' => 'Simpan',
    ])
</x-layouts.admin>
```

`resources/views/admin/room-categories/edit.blade.php`:

```blade
@php
    $gallery = $room->mediaIn('gallery')->values();
    $galleryIds = $gallery->pluck('id')->all();
    $breadcrumbItems = [
        ['label' => 'Fasilitas dan Kamar', 'url' => route('admin.room-categories.index')],
        ['label' => 'Kamar dan Kapasitas', 'url' => route('admin.room-categories.index')],
        ['label' => 'Ubah '.$room->name],
    ];
@endphp
<x-layouts.admin :title="'Ubah '.$room->name">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="$breadcrumbItems" />
    </x-slot:breadcrumb>

    @include('admin.room-categories._form', [
        'room' => $room,
        'action' => route('admin.room-categories.update', $room),
        'submitLabel' => 'Simpan Perubahan',
    ])

    {{-- Kelola galeri: form tersendiri (tidak boleh bersarang di form utama) ke admin.media.* --}}
    <div class="card mt-4" id="galeri-kamar">
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
                                <x-image type="room" :media="$image" :alt="$image->alt_text" variant="thumb" ratio="4x3" class="rounded" />
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

- [ ] **Step 11: Edit `routes/admin.php`: impor controller**

Edit `routes/admin.php`: ganti teks berikut (persis; muncul tepat satu kali):

```php
use App\Http\Controllers\Admin\ScheduleExceptionController;
```

dengan:

```php
use App\Http\Controllers\Admin\RoomCategoryController;
use App\Http\Controllers\Admin\ScheduleExceptionController;
```

(Pint merapikan urutan impor.)

- [ ] **Step 12: Edit `routes/admin.php`: route kamar pada penanda T5**

Edit `routes/admin.php`: ganti teks berikut (persis; muncul tepat satu kali, dipasang T4; di berkas teks ini didahului 4 spasi indentasi yang tetap dipertahankan):

```php
// Task 5: kategori kamar
```

dengan (baris pertama memakai indentasi yang sudah ada; baris berikutnya berindentasi persis seperti tertulis):

```php
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
```

- [ ] **Step 13: Jalankan tes tugas, harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan route:list --path=admin/kamar
& $PHP artisan test --filter=AdminRoomCategoriesTest
& $PHP artisan test --filter=RoomCategoryRequestTest
& $PHP artisan test --filter=RoomAvailabilityTest
& $PHP artisan test --filter=AdminFacilitiesTest
& $PHP artisan test --filter=AdminRouteMiddlewareTest
```
Expected: `route:list --path=admin/kamar` menampilkan 7 route (`admin.room-categories.index|create|store|edit|update|destroy|availability`; tanpa `show`); semua kelas tes PASS. `AdminFacilitiesTest` tetap hijau (tab dan breadcrumb fasilitas kini menuju `admin.room-categories.index`).

- [ ] **Step 14: Pint**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint
& $PHP vendor\bin\pint --test
git status --short
```
Expected: `pint --test` bersih; `git status` hanya memuat berkas tugas ini.

- [ ] **Step 15: Suite penuh (latar belakang, ±11–14 menit)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test *> "$env:TEMP\suite-t5.txt"
Get-Content "$env:TEMP\suite-t5.txt" -Tail 15
```
Jalankan dengan `run_in_background`, tunggu sampai selesai, lalu baca ekor berkas. Expected: semua lulus; jumlah tes bertambah sebanyak tes tugas ini dibanding sebelum T5.

- [ ] **Step 16: Commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git add app/Policies/RoomCategoryPolicy.php app/Http/Requests/Admin/RoomCategoryRequest.php app/Http/Requests/Admin/UpdateRoomAvailabilityRequest.php app/Http/Controllers/Admin/RoomCategoryController.php resources/views/admin/room-categories routes/admin.php tests/Feature/Admin/AdminRoomCategoriesTest.php tests/Feature/Admin/RoomCategoryRequestTest.php tests/Feature/Admin/RoomAvailabilityTest.php
git commit -m @'
feat(fase-4b): admin kamar dan kapasitas (#36) dengan pembaruan ketersediaan manual

CRUD kategori kamar untuk Admin Konten (kapasitas, amenities satu per baris, tarif contoh, cover
dan galeri). Petugas Pendaftaran melihat mode ketersediaan: form inline per baris, tanpa tambah,
ubah, atau hapus. Aksi ketersediaan berjalan dalam transaksi berkunci, mengisi waktu dan petugas
pembaruan, dan mencatat satu audit room.availability_updated tanpa audit pembaruan umum.

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
'@
```

---

### Task 6: Admin kotak pesan kontak (`admin.messages.*`)

**Files:**
- Create: `app/Policies/ContactMessagePolicy.php`
- Create: `app/Http/Requests/Admin/ContactMessageIndexRequest.php`
- Create: `app/Http/Requests/Admin/UpdateContactMessageStatusRequest.php`
- Create: `app/Http/Controllers/Admin/ContactMessageController.php`
- Create: `resources/views/admin/messages/index.blade.php`, `show.blade.php`
- Modify: `routes/admin.php` (satu `use` dan penanda `// Task 6: pesan kontak` yang dipasang T4)
- Test: `tests/Feature/Admin/AdminContactMessagesTest.php`
- Tidak diubah: model `ContactMessage`, factory, `InteractsWithFacilities` (T2); `messages.entities.contact_message` dan `LikePattern` (T3); `config/navigation.php` (item sidebar "Pesan Kontak" sudah ada dan muncul sendiri begitu route ada); `AuditObserver` (`ContactMessage` sengaja tidak diobservasi, F4B-R25).

**Interfaces:**
- Consumes:
  - T2: `App\Models\ContactMessage` (`fillable`: `name`, `email`, `phone`, `subject`, `message`, `status`, `consent_at`, `ip_hash`; `handled_by` dan `handled_at` hanya lewat `forceFill`; cast `status` ke `ContactMessageStatus`; relasi `handler()`; `scopeUnread`), `ContactMessageFactory`, `InteractsWithFacilities::createContactMessage(array $attributes = []): ContactMessage` (`handled_by`/`handled_at` boleh di `$attributes`).
  - T3: `App\Support\LikePattern::contains(string $term): string`.
  - Fase 1-2: enum `ContactMessageStatus` (`label()`, `color()`, `icon()`), `x-status-badge`, `x-data-table`, `x-loading-button`, `AuditLogger::log()`, `Actor::staff()`, `ListFilterRequest`.
  - T4: penanda `// Task 6: pesan kontak` dan impor `FacilityController` di `routes/admin.php`.
- Produces:
  - `App\Http\Controllers\Admin\ContactMessageController`: `index`, `show`, `status` (tanpa `destroy`).
  - Route (semua `permission:messages.manage`): `admin.messages.index` (`GET /admin/pesan`), `admin.messages.show` (`GET /admin/pesan/{message}`), `admin.messages.status` (`PATCH /admin/pesan/{message}/status`). Tiga route, tanpa `admin.messages.destroy` (00 C, KNS-01).
  - `App\Policies\ContactMessagePolicy` (`final`): `viewAny`, `view`, `updateStatus` memerlukan `messages.manage`; `delete` selalu `false`.
  - `Admin\ContactMessageIndexRequest extends ListFilterRequest`: konstanta `STATUS_MAP` (`baru`, `dibaca`, `dibalas`, `diarsipkan` ke enum), `applyTo(Builder $query): Builder` (F4B-R23).
  - `Admin\UpdateContactMessageStatusRequest`: `status` `required|in:read,replied,archived`, `statusValue(): ContactMessageStatus`.
  - Audit eksplisit `contact_message.status_changed` (old/new hanya `['status' => nilai]`), deskripsi "Status pesan kontak #{id} diubah dari {label lama} menjadi {label baru}." tanpa nama, email, telepon, subjek, atau isi pesan.
  - Flash `success` "Status pesan berhasil diperbarui.".
  - Judul layout: "Pesan Kontak" dan "Pesan: {subjek}". Breadcrumb: Dasbor › Pesan Kontak (› {subjek}).

**Test IDs:** SEK-15 (escape output nama, subjek, dan isi pesan), AKS-04 (Admin Konten 403 pada semua route pesan, F4B-R39), AKS-05 (Petugas 200), CRD-13 (`admin.messages` tanpa `destroy`: `DELETE` ke route tidak ada), VAL-07, EMP-03, KNS-01 (tidak ada `admin.messages.destroy`), Review Focus 5 (status pesan dan privasi: buka dua kali satu audit, `status=new` ditolak, arsip lalu pulihkan, `replied` mengisi `handled_*`, Admin Konten 403, audit tanpa data pribadi).

**Catatan teknis (diverifikasi dari source):**
1. **Tanpa `destroy`.** Pesan berisi data pribadi dan hanya diarsipkan (00 C; `ContactMessagePolicy::delete` = `false`). `DELETE /admin/pesan/{id}` jatuh ke URI `GET pesan/{message}` sehingga menghasilkan 405.
2. **Admin Konten tidak punya `messages.manage`** (hanya Super Admin dan Petugas Pendaftaran): semua route pesan, termasuk `PATCH`, memberi 403 (F4B-R39). Layar ini memang mengueri `contact_messages`, jadi tidak dimasukkan ke `AdminPageMatrixTest::test_content_screens_never_query_patient_data`.
3. **Membuka pesan `new`** (F4B-R24) mengubahnya menjadi `read` satu kali, dengan audit `contact_message.status_changed` dan tanpa mengisi `handled_*`. Perubahan berjalan dalam `DB::transaction` dengan `lockForUpdate()`, sehingga dua pembukaan serentak tetap menghasilkan satu audit (status baru dibaca ulang di bawah kunci; bila sudah bukan `new`, tidak ada perubahan).
4. **Status lewat `PATCH`** (F4B-R24): tujuan `read`, `replied`, atau `archived`; `new` ditolak ("Status pesan tidak valid."). Status yang sama dengan yang tersimpan tidak mengubah apa pun dan tidak menulis audit, tetapi flash sukses tetap tampil. Target `replied` atau `archived` mengisi `handled_by` dan `handled_at = now()` lewat `forceFill`; kembali ke `read` (pulihkan dari arsip) membiarkan `handled_*`.
5. **Audit tanpa data pribadi.** `ContactMessage` tidak diobservasi (agar `AuditObserver::nameOf()` tidak mengambil nama pengirim); controller menulis `contact_message.status_changed` sendiri dengan old/new `['status' => ...]` dan deskripsi berisi id dan label status saja. `AuditLogger` tetap meredaksi `phone`, `email`, `message` untuk aksi berawalan `contact_message.` sebagai lapis kedua.
6. **Nama variabel view.** Variabel isi pesan di Blade memakai `$contactMessage` (bukan `$message`) karena `@error` dan komponen form memakai `$message`; parameter route tetap `{message}`.
7. **Isi pesan dirender hanya dengan `{{ }}`.** `name`, `email`, `phone`, `subject`, dan `message` tidak pernah lewat `{!! !!}` (BR-122; `RawOutputScanTest`). Agar baris baru terlihat tanpa CSS baru, isi pesan dipecah per baris (`preg_split('/\R/u')`) dan tiap baris dicetak dengan `{{ }}` diselingi `<br>`; ini setara dengan `white-space: pre-line` tanpa menyentuh SCSS (tidak ada `npm run build`).
8. **Tautan balasan manual.** Email ditautkan sebagai `mailto:` dan telepon sebagai `tel:` (hanya digit dan `+`; tanpa tautan bila kosong). Tidak ada pengiriman balasan otomatis dan kalimat bantuan menyatakannya.
9. **Daftar** (F4B-R23): `x-data-table` tanpa `urut`; urutan tetap `created_at` desc lalu `id` desc, paginasi 25. Baris berstatus `new` memakai kelas `fw-bold` (`<tr class="fw-bold">`). Tidak ada relasi yang dipakai view daftar, jadi tidak ada eager load; halaman `show` memanggil `load('handler')`.
10. **Lencana "Pesan Kontak" di sidebar dan widget dasbor** tetap fase 8 (F4B-R1); tugas ini hanya membuat tautan sidebar muncul karena route sudah ada.

- [ ] **Step 1: Verifikasi prasyarat (T2, T3, T4)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git branch --show-current
git status --short
foreach ($p in 'app\Models\ContactMessage.php', 'database\factories\ContactMessageFactory.php', 'tests\Support\InteractsWithFacilities.php', 'app\Support\LikePattern.php') { "{0} => {1}" -f $p, (Test-Path $p) }
Select-String -Path lang\id\messages.php -Pattern "'contact_message'" | Select-Object -ExpandProperty Line
Select-String -Path routes\admin.php -Pattern '// Task 6: pesan kontak', 'use App\\Http\\Controllers\\Admin\\DashboardController;' | Select-Object -ExpandProperty Line
& $PHP artisan test --filter='AdminFacilitiesTest|FacilityRequestTest|AdminRoomCategoriesTest|RoomCategoryRequestTest|RoomAvailabilityTest'
& $PHP artisan test tests\Feature\Facilities
```
Expected: `fase-4b-fasilitas-kamar-kontak`; `git status` bersih (selain `.superpowers/`); keempat berkas `True`; kunci `'contact_message'` dan kedua baris `routes/admin.php` tercetak; subset tes terfokus (kelas tes T4 dan T5, serta `tests\Feature\Facilities`) PASS. Suite penuh tidak dijalankan di sini (±11–14 menit, melewati batas tool 10 menit); suite penuh ada di langkah akhir (latar belakang).

- [ ] **Step 2: Tulis tes yang gagal (tool Write) `tests/Feature/Admin/AdminContactMessagesTest.php`**

```php
<?php

namespace Tests\Feature\Admin;

use App\Enums\ActorType;
use App\Enums\ContactMessageStatus;
use App\Models\AuditLog;
use App\Models\ContactMessage;
use App\Models\User;
use App\Policies\ContactMessagePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithFacilities;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * Admin kotak pesan kontak (T6, F4B-R23, F4B-R24, F4B-R39): akses (Petugas dan Super Admin, bukan Admin Konten),
 * daftar dan filter, escape output (SEK-15), status pesan, serta audit tanpa data pribadi (Review Focus 5).
 */
class AdminContactMessagesTest extends TestCase
{
    use InteractsWithFacilities;
    use InteractsWithStaff;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Selasa, 6 Oktober 2026 pukul 10.30 WIB.
        $this->travelTo(Carbon::parse('2026-10-06 10:30:00'));
    }

    private function statusUrl(ContactMessage $message): string
    {
        return route('admin.messages.status', $message);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function message(array $overrides = []): ContactMessage
    {
        return $this->createContactMessage(array_merge([
            'name' => 'Budi Santoso',
            'email' => 'budi@contoh.test',
            'subject' => 'Jadwal dokter anak',
            'message' => 'Apakah dokter anak praktik pada hari Sabtu?',
            'status' => ContactMessageStatus::New,
        ], $overrides));
    }

    // --- Akses (AKS-04, AKS-05, F4B-R39) --------------------------------------------------------------

    /**
     * @return array<string, array{string, int}>
     */
    public static function staffRoles(): array
    {
        return [
            'super admin' => ['super-admin', 200],
            'petugas pendaftaran' => ['registration-officer', 200],
            'admin konten' => ['content-admin', 403],
        ];
    }

    #[DataProvider('staffRoles')]
    public function test_index_and_show_access_per_staff_role(string $role, int $status): void
    {
        $message = $this->message();
        $this->actingAs($this->createStaff($role));

        $this->get(route('admin.messages.index'))->assertStatus($status);
        $this->get(route('admin.messages.show', $message))->assertStatus($status);
    }

    public function test_content_admin_is_forbidden_on_every_message_route_including_the_status_patch(): void
    {
        $message = $this->message();
        $this->actingAs($this->createStaff('content-admin'));

        $this->get(route('admin.messages.index'))->assertForbidden();
        $this->get(route('admin.messages.show', $message))->assertForbidden();
        $this->patch($this->statusUrl($message), ['status' => 'archived'])->assertForbidden();

        $message->refresh();
        $this->assertSame(ContactMessageStatus::New, $message->status);
        $this->assertNull($message->handled_by);
        $this->assertSame(0, AuditLog::query()->where('action', 'contact_message.status_changed')->count());
    }

    public function test_patient_gets_403_and_guest_is_redirected_to_admin_login(): void
    {
        $message = $this->message();

        $this->actingAs(User::factory()->patient()->create());
        $this->get(route('admin.messages.index'))->assertForbidden();
        $this->get(route('admin.messages.show', $message))->assertForbidden();
        $this->patch($this->statusUrl($message), ['status' => 'read'])->assertForbidden();

        $this->app['auth']->forgetGuards();

        $this->get(route('admin.messages.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.messages.show', $message))->assertRedirect(route('admin.login'));
        $this->patch($this->statusUrl($message), ['status' => 'read'])->assertRedirect(route('admin.login'));

        $this->assertSame(ContactMessageStatus::New, $message->fresh()->status);
    }

    public function test_urls_use_the_indonesian_segments(): void
    {
        $message = $this->message();

        $this->assertSame(url('/admin/pesan'), route('admin.messages.index'));
        $this->assertSame(url('/admin/pesan/'.$message->id), route('admin.messages.show', $message));
        $this->assertSame(url('/admin/pesan/'.$message->id.'/status'), route('admin.messages.status', $message));
    }

    public function test_there_is_no_destroy_route_and_deleting_is_not_possible(): void
    {
        $message = $this->message();

        $this->assertFalse(Route::has('admin.messages.destroy'));

        $this->actingAs($this->createStaff('super-admin'))
            ->delete('/admin/pesan/'.$message->id)
            ->assertStatus(405);

        $this->assertDatabaseHas('contact_messages', ['id' => $message->id]);

        $policy = new ContactMessagePolicy;
        $this->assertFalse($policy->delete($this->createStaff('registration-officer'), $message));
    }

    public function test_non_numeric_id_is_a_404(): void
    {
        $this->actingAs($this->createStaff('registration-officer'));

        $this->get('/admin/pesan/abc')->assertNotFound();
        $this->patch('/admin/pesan/abc/status', ['status' => 'read'])->assertNotFound();
        $this->get('/admin/pesan/999999')->assertNotFound();
    }

    public function test_the_sidebar_link_appears_for_the_officer_but_not_for_content_admin(): void
    {
        $this->actingAs($this->createStaff('registration-officer'))
            ->get(route('admin.messages.index'))
            ->assertOk()
            ->assertSee('href="'.route('admin.messages.index').'"', false)
            ->assertSee('Pesan Kontak');

        $this->app['auth']->forgetGuards();

        $this->actingAs($this->createStaff('content-admin'))
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('href="'.route('admin.messages.index').'"', false);
    }

    // --- Daftar ---------------------------------------------------------------------------------------

    public function test_index_renders_many_rows_newest_first_with_status_badges_and_bold_new_rows(): void
    {
        $oldestNew = $this->message(['name' => 'Pengirim Satu', 'subject' => 'Subjek Satu', 'created_at' => now()->subDays(5)]);
        $this->message(['name' => 'Pengirim Dua', 'subject' => 'Subjek Dua', 'status' => ContactMessageStatus::Read, 'created_at' => now()->subDays(4)]);
        $this->message(['name' => 'Pengirim Tiga', 'subject' => 'Subjek Tiga', 'status' => ContactMessageStatus::Replied, 'created_at' => now()->subDays(3)]);
        $this->message(['name' => 'Pengirim Empat', 'subject' => 'Subjek Empat', 'status' => ContactMessageStatus::Archived, 'created_at' => now()->subDays(2)]);
        $newest = $this->message(['name' => 'Pengirim Lima', 'subject' => 'Subjek Lima', 'email' => 'lima@contoh.test', 'created_at' => now()->subDay()]);

        $html = $this->actingAs($this->createStaff('registration-officer'))
            ->get(route('admin.messages.index'))
            ->assertOk()
            ->assertSeeInOrder(['Subjek Lima', 'Subjek Empat', 'Subjek Tiga', 'Subjek Dua', 'Subjek Satu'])
            ->assertSee('lima@contoh.test')
            ->assertSee('<i class="bi bi-envelope" aria-hidden="true"></i> Baru', false)
            ->assertSee('<i class="bi bi-envelope-open" aria-hidden="true"></i> Dibaca', false)
            ->assertSee('<i class="bi bi-reply" aria-hidden="true"></i> Dibalas', false)
            ->assertSee('<i class="bi bi-archive" aria-hidden="true"></i> Diarsipkan', false)
            ->assertSee('1 Okt 2026, 10.30')
            ->assertSee('5 Okt 2026, 10.30')
            ->assertSee('href="'.route('admin.messages.show', $oldestNew).'"', false)
            ->assertSee('href="'.route('admin.messages.show', $newest).'"', false)
            ->assertSee('Cari nama, email, atau subjek…')
            ->assertDontSee('name="_method" value="DELETE"', false)
            ->assertDontSee('data-confirm-action="'.url('/admin/pesan'), false)
            ->getContent();

        $this->assertSame(2, substr_count($html, '<tr class="fw-bold">'));
    }

    public function test_search_and_status_filters(): void
    {
        $this->message(['name' => 'Budi Santoso', 'email' => 'budi@contoh.test', 'subject' => 'Jadwal dokter anak']);
        $this->message(['name' => 'Citra Lestari', 'email' => 'citra@lain.test', 'subject' => 'Biaya rawat inap', 'status' => ContactMessageStatus::Read]);
        $this->message(['name' => 'Dewi Anggraini', 'email' => 'dewi@contoh.test', 'subject' => 'Keluhan pelayanan', 'status' => ContactMessageStatus::Replied]);
        $this->message(['name' => 'Eko Prasetyo', 'email' => 'eko@contoh.test', 'subject' => 'Pesan lama', 'status' => ContactMessageStatus::Archived]);
        $this->actingAs($this->createStaff('registration-officer'));

        $this->get(route('admin.messages.index', ['q' => 'santoso']))
            ->assertOk()->assertSee('Jadwal dokter anak')->assertDontSee('Biaya rawat inap');

        $this->get(route('admin.messages.index', ['q' => 'citra@lain']))
            ->assertOk()->assertSee('Biaya rawat inap')->assertDontSee('Jadwal dokter anak');

        $this->get(route('admin.messages.index', ['q' => 'rawat inap']))
            ->assertOk()->assertSee('Biaya rawat inap')->assertDontSee('Keluhan pelayanan');

        foreach ([
            'baru' => 'Jadwal dokter anak',
            'dibaca' => 'Biaya rawat inap',
            'dibalas' => 'Keluhan pelayanan',
            'diarsipkan' => 'Pesan lama',
        ] as $status => $subject) {
            $response = $this->get(route('admin.messages.index', ['status' => $status]))->assertOk()->assertSee($subject);

            foreach (['Jadwal dokter anak', 'Biaya rawat inap', 'Keluhan pelayanan', 'Pesan lama'] as $other) {
                if ($other !== $subject) {
                    $response->assertDontSee($other);
                }
            }
        }
    }

    public function test_search_treats_percent_and_underscore_literally(): void
    {
        $this->message(['subject' => 'Diskon 100% promo']);
        $this->message(['subject' => 'Diskon 1000 promo']);
        $this->message(['subject' => 'Kode_A tiket']);
        $this->message(['subject' => 'KodeXA tiket']);
        $this->actingAs($this->createStaff('registration-officer'));

        $this->get(route('admin.messages.index', ['q' => '100%']))
            ->assertOk()->assertSee('Diskon 100% promo')->assertDontSee('Diskon 1000 promo');

        $this->get(route('admin.messages.index', ['q' => 'Kode_A']))
            ->assertOk()->assertSee('Kode_A tiket')->assertDontSee('KodeXA tiket');
    }

    public function test_invalid_filters_are_ignored_and_array_params_do_not_cause_a_server_error(): void
    {
        $this->message(['subject' => 'Jadwal dokter anak']);
        $this->actingAs($this->createStaff('registration-officer'));

        $this->get(route('admin.messages.index', ['status' => 'new', 'kembali' => 'https://situs-lain.test']))
            ->assertOk()
            ->assertSee('Filter tidak valid dan diabaikan.')
            ->assertSee('Jadwal dokter anak')
            ->assertDontSee('situs-lain.test');

        $this->get(route('admin.messages.index', ['status' => 'zzz']))
            ->assertOk()->assertSee('Jadwal dokter anak');

        $this->get(route('admin.messages.index', ['status' => ['x'], 'q' => ['x'], 'urut' => ['x'], 'per_page' => ['x']]))
            ->assertOk();
    }

    public function test_empty_list_shows_the_empty_state(): void
    {
        $this->actingAs($this->createStaff('registration-officer'))
            ->get(route('admin.messages.index'))
            ->assertOk()
            ->assertSee('Belum ada pesan')
            ->assertSee('Pesan dari formulir kontak akan tampil di sini.');
    }

    // --- Escape output (SEK-15) -----------------------------------------------------------------------

    public function test_sender_text_is_escaped_in_the_list_and_the_detail_sek15(): void
    {
        $message = $this->message([
            'name' => '<script>alert(1)</script>',
            'subject' => '<script>alert(2)</script> Halo',
            'message' => "<script>alert(3)</script>\n<img src=x onerror=alert(4)>",
        ]);
        $this->actingAs($this->createStaff('registration-officer'));

        foreach ([route('admin.messages.index'), route('admin.messages.show', $message)] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertDontSee('<script>alert(1)</script>', false)
                ->assertDontSee('<script>alert(2)</script>', false)
                ->assertDontSee('<script>alert(3)</script>', false)
                ->assertDontSee('<img src=x', false)
                ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
                ->assertSee('&lt;script&gt;alert(2)&lt;/script&gt;', false);
        }

        $this->get(route('admin.messages.show', $message))
            ->assertSee('&lt;script&gt;alert(3)&lt;/script&gt;', false)
            ->assertSee('&lt;img src=x onerror=alert(4)&gt;', false);
    }

    // --- Detail dan status (F4B-R24) -----------------------------------------------------------------

    public function test_opening_a_new_message_marks_it_read_once_with_a_single_audit(): void
    {
        $message = $this->message();
        $user = $this->createStaff('registration-officer');
        $this->actingAs($user);

        $this->get(route('admin.messages.show', $message))
            ->assertOk()
            ->assertSee('<i class="bi bi-envelope-open" aria-hidden="true"></i> Dibaca', false);

        $message->refresh();
        $this->assertSame(ContactMessageStatus::Read, $message->status);
        $this->assertNull($message->handled_by);
        $this->assertNull($message->handled_at);

        $this->get(route('admin.messages.show', $message))->assertOk();
        $this->get(route('admin.messages.show', $message))->assertOk();

        $log = AuditLog::query()->where('action', 'contact_message.status_changed')->sole();

        $this->assertSame(['status' => 'new'], $log->old_values);
        $this->assertSame(['status' => 'read'], $log->new_values);
        $this->assertSame('Status pesan kontak #'.$message->id.' diubah dari Baru menjadi Dibaca.', $log->description);
        $this->assertSame(ContactMessage::class, $log->auditable_type);
        $this->assertSame($message->id, $log->auditable_id);
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame(ActorType::Staff, $log->actor_type);
    }

    public function test_opening_a_message_that_is_not_new_changes_nothing(): void
    {
        $handler = $this->createStaff('super-admin');
        $replied = $this->message(['status' => ContactMessageStatus::Replied, 'handled_by' => $handler->id, 'handled_at' => now()->subDay()]);
        $this->actingAs($this->createStaff('registration-officer'));

        $this->get(route('admin.messages.show', $replied))->assertOk();

        $this->assertSame(ContactMessageStatus::Replied, $replied->fresh()->status);
        $this->assertSame(0, AuditLog::query()->where('action', 'contact_message.status_changed')->count());
    }

    public function test_the_detail_shows_metadata_links_body_lines_and_the_manual_reply_hint(): void
    {
        $handler = $this->createStaff('super-admin', ['name' => 'Rina Penangan']);
        $message = $this->message([
            'name' => 'Budi Santoso',
            'email' => 'budi@contoh.test',
            'phone' => '0812-3456-7890',
            'subject' => 'Jadwal dokter anak',
            'message' => "Baris pertama pesan.\nBaris kedua pesan.",
            'status' => ContactMessageStatus::Replied,
            'consent_at' => now()->subDay(),
            'handled_by' => $handler->id,
            'handled_at' => now()->subHour(),
        ]);

        $this->actingAs($this->createStaff('registration-officer'))
            ->get(route('admin.messages.show', $message))
            ->assertOk()
            ->assertSee('Pesan: Jadwal dokter anak')
            ->assertSee('Pesan Kontak')
            ->assertSee('Budi Santoso')
            ->assertSee('href="mailto:budi@contoh.test"', false)
            ->assertSee('href="tel:081234567890"', false)
            ->assertSee('0812-3456-7890')
            ->assertSee('5 Okt 2026, 10.30')
            ->assertSee('<i class="bi bi-reply" aria-hidden="true"></i> Dibalas', false)
            ->assertSee('oleh Rina Penangan pada 6 Okt 2026, 09.30')
            ->assertSee('Baris pertama pesan.')
            ->assertSee('Baris kedua pesan.')
            ->assertSee('Balas pesan ini secara manual lewat email atau telepon. Aplikasi tidak mengirim balasan otomatis.')
            ->assertSee('Kembali ke daftar pesan')
            ->assertSee('href="'.route('admin.messages.index').'"', false);
    }

    public function test_the_detail_hides_the_phone_row_when_there_is_no_phone_and_shows_no_handler_for_unhandled_messages(): void
    {
        $message = $this->message(['phone' => null, 'status' => ContactMessageStatus::Read]);

        $this->actingAs($this->createStaff('registration-officer'))
            ->get(route('admin.messages.show', $message))
            ->assertOk()
            ->assertDontSee('href="tel:', false)
            ->assertDontSee('Ditangani');
    }

    /**
     * @return array<string, array{ContactMessageStatus, list<string>, list<string>}>
     */
    public static function statusButtons(): array
    {
        return [
            'dibaca' => [ContactMessageStatus::Read, ['Tandai Dibalas', 'Arsipkan'], ['Pulihkan']],
            'dibalas' => [ContactMessageStatus::Replied, ['Arsipkan'], ['Tandai Dibalas', 'Pulihkan']],
            'diarsipkan' => [ContactMessageStatus::Archived, ['Pulihkan'], ['Tandai Dibalas', 'Arsipkan']],
        ];
    }

    /**
     * @param  list<string>  $shown
     * @param  list<string>  $hidden
     */
    #[DataProvider('statusButtons')]
    public function test_the_detail_offers_only_the_valid_status_buttons(ContactMessageStatus $status, array $shown, array $hidden): void
    {
        $message = $this->message(['status' => $status]);
        $response = $this->actingAs($this->createStaff('registration-officer'))
            ->get(route('admin.messages.show', $message))
            ->assertOk()
            ->assertSee('action="'.$this->statusUrl($message).'"', false);

        foreach ($shown as $label) {
            $response->assertSee($label);
        }

        foreach ($hidden as $label) {
            $response->assertDontSee($label);
        }
    }

    public function test_marking_replied_fills_the_handler_and_the_time_with_one_audit_and_the_flash(): void
    {
        $message = $this->message(['status' => ContactMessageStatus::Read]);
        $user = $this->createStaff('registration-officer');

        $this->actingAs($user)
            ->from(route('admin.messages.show', $message))
            ->patch($this->statusUrl($message), ['status' => 'replied'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.messages.show', $message))
            ->assertSessionHas('success', 'Status pesan berhasil diperbarui.');

        $message->refresh();
        $this->assertSame(ContactMessageStatus::Replied, $message->status);
        $this->assertSame($user->id, $message->handled_by);
        $this->assertTrue($message->handled_at->equalTo(now()));

        $log = AuditLog::query()->where('action', 'contact_message.status_changed')->sole();
        $this->assertSame(['status' => 'read'], $log->old_values);
        $this->assertSame(['status' => 'replied'], $log->new_values);
        $this->assertSame('Status pesan kontak #'.$message->id.' diubah dari Dibaca menjadi Dibalas.', $log->description);
    }

    public function test_archiving_then_restoring_keeps_the_handler_and_replied_to_archived_records_the_new_handler(): void
    {
        $first = $this->createStaff('registration-officer');
        $second = $this->createStaff('super-admin');
        $message = $this->message(['status' => ContactMessageStatus::Replied]);

        $this->actingAs($first)->patch($this->statusUrl($message), ['status' => 'archived'])->assertSessionHasNoErrors();

        $message->refresh();
        $this->assertSame(ContactMessageStatus::Archived, $message->status);
        $this->assertSame($first->id, $message->handled_by);
        $stamp = $message->handled_at->toDateTimeString();
        $this->assertSame('2026-10-06 10:30:00', $stamp);

        $this->travelTo(Carbon::parse('2026-10-07 09:00:00'));
        $this->app['auth']->forgetGuards();

        $this->actingAs($second)->patch($this->statusUrl($message), ['status' => 'read'])->assertSessionHasNoErrors();

        $message->refresh();
        $this->assertSame(ContactMessageStatus::Read, $message->status);
        $this->assertSame($first->id, $message->handled_by);
        $this->assertSame($stamp, $message->handled_at->toDateTimeString());

        $this->patch($this->statusUrl($message), ['status' => 'archived'])->assertSessionHasNoErrors();

        $message->refresh();
        $this->assertSame($second->id, $message->handled_by);
        $this->assertSame('2026-10-07 09:00:00', $message->handled_at->toDateTimeString());
        $this->assertSame(3, AuditLog::query()->where('action', 'contact_message.status_changed')->count());
    }

    public function test_a_new_message_can_be_archived_directly_without_being_opened(): void
    {
        $message = $this->message();
        $user = $this->createStaff('registration-officer');

        $this->actingAs($user)->patch($this->statusUrl($message), ['status' => 'archived'])->assertSessionHasNoErrors();

        $message->refresh();
        $this->assertSame(ContactMessageStatus::Archived, $message->status);
        $this->assertSame($user->id, $message->handled_by);
        $this->assertSame(
            'Status pesan kontak #'.$message->id.' diubah dari Baru menjadi Diarsipkan.',
            AuditLog::query()->where('action', 'contact_message.status_changed')->sole()->description,
        );
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function invalidStatuses(): array
    {
        return [
            'kembali ke baru' => ['new', 'Status pesan tidak valid.'],
            'tidak dikenal' => ['selesai', 'Status pesan tidak valid.'],
            'huruf besar' => ['READ', 'Status pesan tidak valid.'],
            'kosong' => ['', 'Status pesan wajib dipilih.'],
            'larik' => [['read'], 'Status pesan tidak valid.'],
        ];
    }

    #[DataProvider('invalidStatuses')]
    public function test_invalid_statuses_are_rejected_with_indonesian_messages_and_change_nothing(mixed $value, string $error): void
    {
        $message = $this->message(['status' => ContactMessageStatus::Read]);
        $show = route('admin.messages.show', $message);

        $this->actingAs($this->createStaff('registration-officer'))
            ->from($show)
            ->patch($this->statusUrl($message), ['status' => $value])
            ->assertRedirect($show)
            ->assertSessionHasErrors(['status' => $error]);

        $message->refresh();
        $this->assertSame(ContactMessageStatus::Read, $message->status);
        $this->assertNull($message->handled_by);
        $this->assertSame(0, AuditLog::query()->where('action', 'contact_message.status_changed')->count());
    }

    public function test_a_missing_status_field_is_rejected(): void
    {
        $message = $this->message(['status' => ContactMessageStatus::Read]);

        $this->actingAs($this->createStaff('registration-officer'))
            ->from(route('admin.messages.show', $message))
            ->patch($this->statusUrl($message), [])
            ->assertSessionHasErrors(['status' => 'Status pesan wajib dipilih.']);
    }

    public function test_the_same_status_changes_nothing_writes_no_audit_but_still_flashes(): void
    {
        $handler = $this->createStaff('super-admin');
        $stamp = Carbon::parse('2026-10-05 08:00:00');
        $read = $this->message(['status' => ContactMessageStatus::Read]);
        $replied = $this->message(['status' => ContactMessageStatus::Replied, 'handled_by' => $handler->id, 'handled_at' => $stamp]);
        $this->actingAs($this->createStaff('registration-officer'));

        $this->patch($this->statusUrl($read), ['status' => 'read'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Status pesan berhasil diperbarui.');

        $this->patch($this->statusUrl($replied), ['status' => 'replied'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Status pesan berhasil diperbarui.');

        $this->assertNull($read->fresh()->handled_by);
        $replied->refresh();
        $this->assertSame($handler->id, $replied->handled_by);
        $this->assertSame($stamp->toDateTimeString(), $replied->handled_at->toDateTimeString());
        $this->assertSame(0, AuditLog::query()->where('action', 'contact_message.status_changed')->count());
    }

    // --- Privasi (Review Focus 5) ---------------------------------------------------------------------

    public function test_audit_rows_never_contain_the_sender_name_email_phone_subject_or_message(): void
    {
        $message = $this->message([
            'name' => 'Rahasia Pengirim',
            'email' => 'rahasia@pengirim.test',
            'phone' => '081234567890',
            'subject' => 'Subjek Rahasia',
            'message' => 'Isi pesan yang rahasia sekali.',
        ]);
        $this->actingAs($this->createStaff('registration-officer'));

        $this->get(route('admin.messages.show', $message))->assertOk();
        $this->patch($this->statusUrl($message), ['status' => 'replied'])->assertSessionHasNoErrors();
        $this->patch($this->statusUrl($message), ['status' => 'archived'])->assertSessionHasNoErrors();

        $logs = AuditLog::query()->where('action', 'like', 'contact_message.%')->get();
        $this->assertCount(3, $logs);
        $this->assertSame(['contact_message.status_changed'], $logs->pluck('action')->unique()->values()->all());

        $dump = json_encode($logs->map->toArray()->all(), JSON_UNESCAPED_UNICODE);

        foreach (['Rahasia Pengirim', 'rahasia@pengirim.test', '081234567890', 'Subjek Rahasia', 'Isi pesan yang rahasia'] as $secret) {
            $this->assertStringNotContainsString($secret, (string) $dump);
        }
    }

    public function test_creating_a_message_does_not_write_an_audit_row(): void
    {
        $this->message(['name' => 'Rahasia Pengirim']);

        $this->assertSame(0, AuditLog::query()->where('action', 'like', 'contact_message.%')->count());
    }

    public function test_the_status_route_is_rejected_for_a_user_with_the_wrong_permission(): void
    {
        $message = $this->message(['status' => ContactMessageStatus::Read]);
        $user = $this->createStaff('content-admin');
        $user->syncRoles([]);
        $user->givePermissionTo('rooms.update-availability');

        $this->actingAs($user)
            ->patch($this->statusUrl($message), ['status' => 'archived'])
            ->assertForbidden();

        $this->assertSame(ContactMessageStatus::Read, $message->fresh()->status);
    }
}
```

- [ ] **Step 3: Jalankan tes, harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=AdminContactMessagesTest
```
Expected: FAIL. Tes gagal dengan `Route [admin.messages.index] not defined.` (atau `…show`/`…status` not defined); tes yang memakai URL literal gagal dengan 404.

- [ ] **Step 4: Implementasi `ContactMessagePolicy` (tool Write) `app/Policies/ContactMessagePolicy.php`**

```php
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ContactMessage;
use App\Models\User;

/**
 * 04 §5 / 11 §8 (F4B-R15): daftar, detail, dan ubah status memerlukan `messages.manage`. Pesan berisi data pribadi
 * pengunjung sehingga tidak pernah dihapus, hanya diarsipkan (00 C): `delete` selalu `false`.
 */
final class ContactMessagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('messages.manage');
    }

    public function view(User $user, ContactMessage $contactMessage): bool
    {
        return $user->can('messages.manage');
    }

    public function updateStatus(User $user, ContactMessage $contactMessage): bool
    {
        return $user->can('messages.manage');
    }

    public function delete(User $user, ContactMessage $contactMessage): bool
    {
        return false;
    }
}
```

- [ ] **Step 5: Implementasi `ContactMessageIndexRequest` (tool Write) `app/Http/Requests/Admin/ContactMessageIndexRequest.php`**

```php
<?php

namespace App\Http\Requests\Admin;

use App\Enums\ContactMessageStatus;
use App\Http\Requests\ListFilterRequest;
use App\Support\LikePattern;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Filter daftar pesan kontak (F4B-R23): `q` pada `name`, `email`, atau `subject`, dan `status` dengan nilai Indonesia
 * yang dipetakan ke enum. Tanpa `urut`: urutan tetap terbaru dahulu. Parameter yang tidak valid diabaikan oleh basis (12 V-45).
 */
class ContactMessageIndexRequest extends ListFilterRequest
{
    public const STATUS_MAP = [
        'baru' => ContactMessageStatus::New,
        'dibaca' => ContactMessageStatus::Read,
        'dibalas' => ContactMessageStatus::Replied,
        'diarsipkan' => ContactMessageStatus::Archived,
    ];

    /**
     * @return list<string>
     */
    protected function statusValues(): array
    {
        return array_keys(self::STATUS_MAP);
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function applyTo(Builder $query): Builder
    {
        $filters = $this->filters();
        $model = $query->getModel();

        if (isset($filters['q'])) {
            $like = LikePattern::contains($filters['q']);

            $query->where(function (Builder $inner) use ($model, $like): void {
                $inner->where($model->qualifyColumn('name'), 'like', $like)
                    ->orWhere($model->qualifyColumn('email'), 'like', $like)
                    ->orWhere($model->qualifyColumn('subject'), 'like', $like);
            });
        }

        $status = isset($filters['status']) ? (self::STATUS_MAP[$filters['status']] ?? null) : null;

        if ($status !== null) {
            $query->where($model->qualifyColumn('status'), $status->value);
        }

        return $query
            ->orderByDesc($model->qualifyColumn('created_at'))
            ->orderByDesc($model->qualifyColumn('id'));
    }
}
```

- [ ] **Step 6: Implementasi `UpdateContactMessageStatusRequest` (tool Write) `app/Http/Requests/Admin/UpdateContactMessageStatusRequest.php`**

```php
<?php

namespace App\Http\Requests\Admin;

use App\Enums\ContactMessageStatus;
use App\Models\ContactMessage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * V-38 (F4B-R24): tujuan status `read`, `replied`, atau `archived`. Pesan tidak dapat kembali ke `new`.
 */
class UpdateContactMessageStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $message = $this->route('message');

        return $user !== null
            && $message instanceof ContactMessage
            && $user->can('updateStatus', $message);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['bail', 'required', Rule::in(['read', 'replied', 'archived'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Status pesan wajib dipilih.',
            'status.in' => 'Status pesan tidak valid.',
        ];
    }

    public function statusValue(): ContactMessageStatus
    {
        return ContactMessageStatus::from((string) $this->validated('status'));
    }
}
```

- [ ] **Step 7: Implementasi `ContactMessageController` (tool Write) `app/Http/Controllers/Admin/ContactMessageController.php`**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContactMessageStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ContactMessageIndexRequest;
use App\Http\Requests\Admin\UpdateContactMessageStatusRequest;
use App\Models\ContactMessage;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Actor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Admin kotak pesan kontak (F-ADM-24). Hanya `index`, `show`, dan `status`: pesan berisi data pribadi pengunjung dan tidak
 * pernah dihapus (00 C). `ContactMessage` tidak diobservasi `AuditObserver`; perubahan status dicatat di sini sebagai
 * `contact_message.status_changed` tanpa nama, email, telepon, subjek, atau isi pesan (F4B-R24, F4B-R25).
 */
class ContactMessageController extends Controller
{
    public function index(ContactMessageIndexRequest $request): View
    {
        $this->authorize('viewAny', ContactMessage::class);

        $contactMessages = $request
            ->applyTo(ContactMessage::query())
            ->paginate($request->perPage());

        return view('admin.messages.index', ['contactMessages' => $contactMessages]);
    }

    /**
     * Membuka pesan berstatus `new` mengubahnya menjadi `read` satu kali, tanpa mengisi `handled_*`.
     */
    public function show(Request $request, ContactMessage $message, AuditLogger $audit): View
    {
        $this->authorize('view', $message);

        if ($message->status === ContactMessageStatus::New) {
            $this->transition($message, ContactMessageStatus::Read, $request->user(), $audit);
            $message->refresh();
        }

        return view('admin.messages.show', ['contactMessage' => $message->load('handler')]);
    }

    public function status(UpdateContactMessageStatusRequest $request, ContactMessage $message, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('updateStatus', $message);

        $this->transition($message, $request->statusValue(), $request->user(), $audit);

        return redirect()->route('admin.messages.show', $message)
            ->with('success', 'Status pesan berhasil diperbarui.');
    }

    /**
     * Status dibaca ulang di bawah kunci baris: dua permintaan serentak tidak menulis audit ganda, dan status yang
     * sama dengan yang tersimpan tidak mengubah apa pun. Target `replied` atau `archived` mengisi `handled_*`;
     * kembali ke `read` membiarkannya.
     */
    private function transition(ContactMessage $message, ContactMessageStatus $to, User $user, AuditLogger $audit): void
    {
        DB::transaction(function () use ($message, $to, $user, $audit): void {
            $locked = ContactMessage::query()->lockForUpdate()->findOrFail($message->getKey());
            $from = $locked->status;

            if ($from === $to) {
                return;
            }

            $locked->status = $to;

            if ($to === ContactMessageStatus::Replied || $to === ContactMessageStatus::Archived) {
                $locked->forceFill(['handled_by' => $user->id, 'handled_at' => now()]);
            }

            $locked->save();

            $audit->log(
                'contact_message.status_changed',
                $locked,
                ['status' => $from->value],
                ['status' => $to->value],
                "Status pesan kontak #{$locked->id} diubah dari {$from->label()} menjadi {$to->label()}.",
                Actor::staff($user),
            );
        });
    }
}
```

- [ ] **Step 8: Implementasi view (tool Write)**

`resources/views/admin/messages/index.blade.php`:

```blade
@php
    $statusNow = is_string(request()->query('status')) ? request()->query('status') : '';
    $breadcrumbItems = [
        ['label' => 'Pesan Kontak'],
    ];
@endphp
<x-layouts.admin title="Pesan Kontak">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="$breadcrumbItems" />
    </x-slot:breadcrumb>

    <x-data-table
        :rows="$contactMessages"
        search-placeholder="Cari nama, email, atau subjek…"
        caption="Daftar pesan dari formulir kontak"
        empty-title="Belum ada pesan"
        empty-message="Pesan dari formulir kontak akan tampil di sini.">
        <x-slot:filters>
            <div class="col-12 col-md-3 col-lg-2">
                <label for="filter-status" class="form-label fw-semibold">Status</label>
                <select id="filter-status" name="status" class="form-select">
                    <option value="">Semua</option>
                    @foreach (\App\Http\Requests\Admin\ContactMessageIndexRequest::STATUS_MAP as $key => $status)
                        <option value="{{ $key }}" @selected($statusNow === $key)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
        </x-slot:filters>

        <x-slot:head>
            <th scope="col">Status</th>
            <th scope="col">Pengirim</th>
            <th scope="col">Subjek</th>
            <th scope="col">Diterima</th>
            <th scope="col">Aksi</th>
        </x-slot:head>

        @foreach ($contactMessages as $item)
            <tr @class(['fw-bold' => $item->status === \App\Enums\ContactMessageStatus::New])>
                <td data-label="Status"><x-status-badge :status="$item->status" /></td>
                <td data-label="Pengirim">
                    <span>{{ $item->name }}</span>
                    <div class="small text-secondary fw-normal">{{ $item->email }}</div>
                </td>
                <td data-label="Subjek">{{ $item->subject }}</td>
                <td data-label="Diterima">
                    <time datetime="{{ $item->created_at->toIso8601String() }}">{{ $item->created_at->translatedFormat('j M Y, H.i') }}</time>
                </td>
                <td data-label="Aksi" class="table-actions">
                    <a href="{{ route('admin.messages.show', $item) }}" class="btn btn-sm btn-outline-primary" aria-label="Buka pesan {{ $item->subject }}">Buka</a>
                </td>
            </tr>
        @endforeach
    </x-data-table>
</x-layouts.admin>
```

`resources/views/admin/messages/show.blade.php`:

```blade
@php
    $status = $contactMessage->status;
    $statusUrl = route('admin.messages.status', $contactMessage);
    $format = fn (?\Carbon\CarbonInterface $at): string => $at === null ? '—' : $at->translatedFormat('j M Y, H.i');
    $phoneLink = preg_replace('/[^0-9+]/', '', (string) $contactMessage->phone);
    $lines = preg_split('/\R/u', (string) $contactMessage->message) ?: [];
    $breadcrumbItems = [
        ['label' => 'Pesan Kontak', 'url' => route('admin.messages.index')],
        ['label' => $contactMessage->subject],
    ];
    // Tombol status menurut status saat ini (F4B-R24): [nilai tujuan, label, varian tombol, ikon].
    $actions = match ($status) {
        \App\Enums\ContactMessageStatus::New, \App\Enums\ContactMessageStatus::Read => [
            ['replied', 'Tandai Dibalas', 'primary', 'bi-reply'],
            ['archived', 'Arsipkan', 'outline-secondary', 'bi-archive'],
        ],
        \App\Enums\ContactMessageStatus::Replied => [
            ['archived', 'Arsipkan', 'outline-secondary', 'bi-archive'],
        ],
        \App\Enums\ContactMessageStatus::Archived => [
            ['read', 'Pulihkan', 'outline-primary', 'bi-arrow-counterclockwise'],
        ],
    };
@endphp
<x-layouts.admin :title="'Pesan: '.$contactMessage->subject">
    <x-slot:breadcrumb>
        <x-breadcrumb home="admin.dashboard" :items="$breadcrumbItems" />
    </x-slot:breadcrumb>

    <div class="row g-4">
        <div class="col-12 col-lg-8">
            <div class="card mb-4">
                <div class="card-header fw-semibold">Isi pesan</div>
                <div class="card-body">
                    <h2 class="h5">{{ $contactMessage->subject }}</h2>
                    {{-- Hanya {{ }}: tiap baris di-escape, baris baru dipertahankan dengan <br> (BR-122, SEK-15). --}}
                    <div class="message-body text-break">
                        @foreach ($lines as $line)
                            {{ $line }}@if (! $loop->last)<br>@endif
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 mb-3">
                @foreach ($actions as [$target, $label, $variant, $icon])
                    <form method="POST" action="{{ $statusUrl }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="{{ $target }}">
                        <x-loading-button :variant="$variant" :icon="$icon" loading-text="Menyimpan…">{{ $label }}</x-loading-button>
                    </form>
                @endforeach
            </div>

            <p class="text-secondary">Balas pesan ini secara manual lewat email atau telepon. Aplikasi tidak mengirim balasan otomatis.</p>

            <a href="{{ route('admin.messages.index') }}" class="btn btn-link px-0">
                <i class="bi bi-arrow-left" aria-hidden="true"></i> Kembali ke daftar pesan
            </a>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card">
                <div class="card-header fw-semibold">Rincian</div>
                <div class="card-body">
                    <dl class="mb-0">
                        <dt>Pengirim</dt>
                        <dd>{{ $contactMessage->name }}</dd>
                        <dt>Email</dt>
                        <dd><a href="mailto:{{ $contactMessage->email }}">{{ $contactMessage->email }}</a></dd>
                        @if ($phoneLink !== '')
                            <dt>Telepon</dt>
                            <dd><a href="tel:{{ $phoneLink }}">{{ $contactMessage->phone }}</a></dd>
                        @endif
                        <dt>Diterima</dt>
                        <dd><time datetime="{{ $contactMessage->created_at->toIso8601String() }}">{{ $format($contactMessage->created_at) }}</time></dd>
                        <dt>Status</dt>
                        <dd><x-status-badge :status="$status" /></dd>
                        @if ($contactMessage->handled_at !== null)
                            <dt>Ditangani</dt>
                            <dd>oleh {{ $contactMessage->handler?->name ?? 'akun yang sudah dihapus' }} pada {{ $format($contactMessage->handled_at) }}</dd>
                        @endif
                        <dt>Persetujuan</dt>
                        <dd>{{ $format($contactMessage->consent_at) }}</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</x-layouts.admin>
```

- [ ] **Step 9: Edit `routes/admin.php`: impor controller**

Edit `routes/admin.php`: ganti teks berikut (persis; muncul tepat satu kali):

```php
use App\Http\Controllers\Admin\DashboardController;
```

dengan:

```php
use App\Http\Controllers\Admin\ContactMessageController;
use App\Http\Controllers\Admin\DashboardController;
```

(Pint merapikan urutan impor.)

- [ ] **Step 10: Edit `routes/admin.php`: route pesan pada penanda T6**

Edit `routes/admin.php`: ganti teks berikut (persis; muncul tepat satu kali, dipasang T4; di berkas teks ini didahului 4 spasi indentasi yang tetap dipertahankan):

```php
// Task 6: pesan kontak
```

dengan (baris pertama memakai indentasi yang sudah ada; baris berikutnya berindentasi persis seperti tertulis):

```php
// Kotak pesan kontak (F-ADM-24): izin tunggal messages.manage; tanpa destroy (00 C, KNS-01).
    Route::middleware('permission:messages.manage')->group(function (): void {
        Route::get('pesan', [ContactMessageController::class, 'index'])->name('messages.index');
        Route::get('pesan/{message}', [ContactMessageController::class, 'show'])->name('messages.show');
        Route::patch('pesan/{message}/status', [ContactMessageController::class, 'status'])->name('messages.status');
    });
```

- [ ] **Step 11: Jalankan tes tugas, harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan route:list --path=admin/pesan
& $PHP artisan test --filter=AdminContactMessagesTest
& $PHP artisan test --filter=AdminRouteMiddlewareTest
& $PHP artisan test --filter=Phase3RoutePermissionTest
```
Expected: `route:list --path=admin/pesan` menampilkan 3 route (`admin.messages.index`, `admin.messages.show`, `admin.messages.status`; tanpa `destroy`); semua kelas tes PASS. `Phase3RoutePermissionTest` tetap hijau (tes yang melarang `admin.messages.destroy` kini punya route `admin.messages.*` lain, tetapi tidak ada `destroy`).

- [ ] **Step 12: Pint**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint
& $PHP vendor\bin\pint --test
git status --short
```
Expected: `pint --test` bersih; `git status` hanya memuat berkas tugas ini.

- [ ] **Step 13: Suite penuh (latar belakang, ±11–14 menit)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test *> "$env:TEMP\suite-t6.txt"
Get-Content "$env:TEMP\suite-t6.txt" -Tail 15
```
Jalankan dengan `run_in_background`, tunggu sampai selesai, lalu baca ekor berkas. Expected: semua lulus; jumlah tes bertambah sebanyak tes tugas ini dibanding sebelum T6. Total route admin kini 106 (90 + 6 fasilitas + 7 kamar + 3 pesan); angka itu dikunci `Phase4bRoutePermissionTest` di T11 (periksa dengan `& $PHP artisan route:list --path=admin --json` hanya sebagai catatan).

- [ ] **Step 14: Commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git add app/Policies/ContactMessagePolicy.php app/Http/Requests/Admin/ContactMessageIndexRequest.php app/Http/Requests/Admin/UpdateContactMessageStatusRequest.php app/Http/Controllers/Admin/ContactMessageController.php resources/views/admin/messages routes/admin.php tests/Feature/Admin/AdminContactMessagesTest.php
git commit -m @'
feat(fase-4b): admin kotak pesan kontak

Daftar dengan pencarian dan filter status, detail pesan yang ter-escape, dan perubahan status
(dibaca, dibalas, diarsipkan, pulihkan) oleh Petugas Pendaftaran dan Super Admin. Membuka pesan
baru menandainya dibaca satu kali. Audit contact_message.status_changed tidak memuat data pribadi
pengirim dan tidak ada route hapus.

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
'@
```

---

### Task 7: Halaman publik #9 Daftar Fasilitas dan #10 Detail Fasilitas

**Files:**
- Create: `app/Http/Controllers/Public/FacilityController.php`
- Create: `resources/views/public/facilities/index.blade.php`, `resources/views/public/facilities/show.blade.php`, `resources/views/public/facilities/_card.blade.php`
- Modify: `routes/web.php` (impor `FacilityController`; route `facilities.index` dan `facilities.show` sesudah baris `/jadwal-dokter`)
- Modify: `resources/scss/_public.scss` (kelas `.facility-card` dan `.facility-body`)
- Modify (tes lama): `tests/Feature/View/RawOutputScanTest.php` (daftar baris `{!!` dari 3 menjadi 4)
- Test (baru): `tests/Feature/Public/PublicFacilitiesTest.php`
- Tidak diubah: `resources/js/app.js` (`initGalleryModals()` dipakai apa adanya), `public/clinics/show.blade.php` (hanya dijadikan contoh markup), `config/navigation.php` (menu "Fasilitas" sudah ada dan tersaring `Route::has`), `x-open-graph`, layout publik, model dan factory Task 2.

**Interfaces:**
- Consumes:
  - Task 2: `App\Models\Facility` (scope `active()`, `ordered()`; cast `category` ke `FacilityCategory`; accessor `seo_title`, `seo_description`; relasi `media`, koleksi `cover` dan `gallery`; `description` tersanitasi dan NULL bila kosong), `Tests\Support\InteractsWithFacilities` (`createFacility(array $attributes = []): Facility`).
  - Task 3: `MediaPolicy`/`MediaService::EDITOR_REFERENCE_COLUMNS` (tidak dipanggil langsung oleh tugas ini).
  - Fase 4a: `App\Models\Page::forKey('facilities-index')` (judul, subjudul, `seo_title`, `seo_description`; bawaan `Page::DEFAULTS`), `x-open-graph`, `Tests\Support\InteractsWithContent::createPage`.
  - Fase 1-3: `x-layouts.public`, `x-breadcrumb`, `x-page-header`, `x-empty-state`, `x-image` (tipe `facility`, rasio `4x3` dan `16x9`), `initGalleryModals()` (`[data-gallery-modal]`, `data-gallery-index`), `MediaService`, `ImageFixtures`, `InteractsWithStaff`, enum `App\Enums\FacilityCategory` (`cases()`, `label()`, `icon()`).
- Produces:
  - `App\Http\Controllers\Public\FacilityController` (`index(): View`, `show(Facility $facility): View`).
  - Route `facilities.index` (`/fasilitas`) dan `facilities.show` (`/fasilitas/{facility:slug}`).
  - View `public.facilities.index`, `public.facilities.show`, partial `public.facilities._card` (`$facility`, `$headingLevel` bawaan 3).
  - Kelas SCSS `.facility-card` dan `.facility-body`.
  - Baris keempat `RawOutputScanTest`: `public/facilities/show.blade.php: <div class="rich-text facility-body">{!! $facility->description !!}</div>`.

**Test IDs:** F-PUB-08 dan F-PUB-09 (halaman #9 dan #10), EMP-01 (daftar fasilitas kosong), SEK-08/SEK-15 (deskripsi tersanitasi, `{!! !!}` hanya satu baris baru), AKS-01 (halaman publik terbuka bagi semua aktor), Review Focus 3 (slug tidak dikenal, nonaktif, dan staf; kategori tanpa isi; DB kosong; tanpa `LazyLoadingViolationException`), F4B-R14 (route dan 404 untuk semua aktor), F4B-R29 (pengelompokan per kategori, SEO detail).

**Catatan teknis (diverifikasi dari source):**
1. **Pola modal galeri.** `resources/views/public/clinics/show.blade.php:47-89` memakai tombol `gallery-thumb` (`data-bs-toggle="modal" data-bs-target="#galeri-modal" data-gallery-index`), satu modal `#galeri-modal` bertanda `data-gallery-modal`, dan satu carousel `#galeri-carousel` (`data-bs-ride="false"`, `tabindex="-1"`). `initGalleryModals()` (`resources/js/app.js:309`) mencari `[data-gallery-modal]` dan menyetel slide aktif dari `data-gallery-index` pemicu. Detail fasilitas memakai markup yang sama, hanya `type="facility"`, sehingga JS tidak berubah.
2. **Tanpa Policy dan 404 untuk semua aktor.** `Public\ClinicController::show` (`app/Http/Controllers/Public/ClinicController.php:38`) memakai `abort_unless($clinic->is_active, 404)`. `FacilityController::show` meniru pola itu (A-42, F4B-R14): fasilitas nonaktif 404 bagi tamu, pasien, dan staf. Slug tidak dikenal 404 lewat binding `{facility:slug}`.
3. **Mode ketat.** `HasMedia::mediaIn()` (`app/Models/Concerns/HasMedia.php:60`) memakai `getRelationValue('media')`; pada daftar banyak baris tanpa `with('media')` ia melempar `LazyLoadingViolationException`. Daftar memuat `media` dengan batasan koleksi `cover`; detail memanggil `$facility->load('media')` karena cover dan galeri sama-sama dibaca.
4. **Pengelompokan (F4B-R29).** `FacilityCategory::cases()` mendefinisikan urutan `medis`, `penunjang`, `umum`. Controller menyusun larik pasangan `[FacilityCategory, Collection<Facility>]`; kategori tanpa fasilitas aktif dilewati. Urutan dalam kelompok mengikuti `scopeOrdered()` (`sort_order`, `name`, `id`). Tanpa paginasi.
5. **Judul dan SEO.** #9 memakai `Page::forKey('facilities-index')->seo_title` dan `seo_description` (bawaan: judul "Fasilitas", subjudul "Fasilitas medis, penunjang, dan umum", deskripsi "Daftar fasilitas UNPAM HOSPITAL." dari `Page::DEFAULTS`). #10 memakai `$facility->seo_title` (`meta_title`, lalu `name`) dan deskripsi `Str::limit($facility->seo_description, 160, '')` (`meta_description`, lalu `short_description`); `og:image` hanya bila cover ada. Layout menambah `" — {site.name}"` pada `<title>` (`resources/views/components/layouts/public.blade.php`).
6. **Tautan bersyarat.** Tautan "Lihat Kamar Rawat Inap" dan "Kontak" memakai `Route::has()`, karena route `rooms.index` dan `contact` baru dibuat Task 8 dan 9. Tes memakai pembantu `fakeRoute` (pola `PublicFaqTest`) agar lulus sebelum maupun sesudah tugas-tugas itu.
7. **Menu publik.** `config/navigation.php` sudah memuat dropdown "Fasilitas" dengan anak `facilities.index` dan `rooms.index`; `NavigationMenu` membuang anak yang route-nya belum ada. Setelah tugas ini dropdown muncul dengan satu anak. Sapuan tes lama (`grep` "Fasilitas" di `tests/`) tidak menemukan asersi yang patah karenanya.
8. **`{!! !!}` baru.** Hanya satu baris di `facilities/show.blade.php`. `RawOutputScanTest` membandingkan teks baris yang sudah di-`trim`, jadi baris itu harus berdiri sendiri dan persis `<div class="rich-text facility-body">{!! $facility->description !!}</div>`. Urutan hasil `sort($found)` adalah `articles`, `clinics`, `facilities`, `partials`.
9. **SCSS.** Tugas ini mengubah `resources/scss`, jadi `npm run build` dijalankan sebelum suite (kontrak `FrontendManifestTest`). Tidak ada entri Vite baru.
10. Jumlah tes baru: ±36.

- [ ] **Step 1: Verifikasi prasyarat (Task 1-3 selesai)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git branch --show-current
git status --short
git log --oneline -3
Test-Path app\Models\Facility.php, app\Models\RoomCategory.php, tests\Support\InteractsWithFacilities.php, database\factories\FacilityFactory.php
Test-Path app\Http\Controllers\Public\FacilityController.php, resources\views\public\facilities\index.blade.php
Select-String -Path routes\web.php -Pattern 'facilities\.'
& $PHP artisan test --filter='FacilityModelTest|RawOutputScanTest|PublicFaqTest'
```
Expected: `fase-4b-fasilitas-kamar-kontak`; `git status` bersih (kecuali `.superpowers/`); commit Task 3 terlihat; `True` x4, lalu `False` x2; `Select-String` tidak mencetak apa pun; ketiga kelas tes PASS.

- [ ] **Step 2: Tulis tes yang gagal, dua berkas**

**2a. Edit tes lama `tests/Feature/View/RawOutputScanTest.php` (tool Edit).** `old_string` cocok tepat satu kali:

old_string:

```php
            'public/clinics/show.blade.php: <div class="rich-text clinic-description">{!! $clinic->description !!}</div>',
            'public/partials/_page-body.blade.php: <div class="rich-text page-body">{!! $page->body !!}</div>',
```

new_string:

```php
            'public/clinics/show.blade.php: <div class="rich-text clinic-description">{!! $clinic->description !!}</div>',
            'public/facilities/show.blade.php: <div class="rich-text facility-body">{!! $facility->description !!}</div>',
            'public/partials/_page-body.blade.php: <div class="rich-text page-body">{!! $page->body !!}</div>',
```

**2b. Tes baru (tool Write) `tests/Feature/Public/PublicFacilitiesTest.php`:**

```php
<?php

namespace Tests\Feature\Public;

use App\Enums\FacilityCategory;
use App\Models\Facility;
use App\Models\Media;
use App\Models\User;
use App\Services\Media\MediaService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\ImageFixtures;
use Tests\Support\InteractsWithContent;
use Tests\Support\InteractsWithFacilities;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * Halaman #9 `facilities.index` dan #10 `facilities.show` (05 hal. 9-10, F4B-R14, F4B-R29): hanya fasilitas aktif,
 * dikelompokkan per kategori dalam urutan enum; kategori tanpa isi disembunyikan; 404 untuk fasilitas nonaktif
 * dan slug tidak dikenal bagi semua aktor termasuk staf; detail memakai ulang pola modal dan carousel galeri
 * poliklinik. Mencakup EMP-01, Review Focus 3, dan mode ketat.
 */
class PublicFacilitiesTest extends TestCase
{
    use InteractsWithContent;
    use InteractsWithFacilities;
    use InteractsWithStaff;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    /** Foto sungguhan lewat MediaService (berkas dan thumbnail ada di disk palsu). */
    private function attachImage(Model $owner, string $collection, string $alt, string $name = 'foto.jpg'): Media
    {
        return $this->app->make(MediaService::class)->store(
            ImageFixtures::jpeg(1200, 800, $name),
            $collection,
            $owner,
            ['alt_text' => $alt],
        );
    }

    private function fakeRoute(string $name, string $uri): void
    {
        if (! Route::has($name)) {
            Route::middleware('web')->get($uri, fn () => 'x')->name($name);
            Route::getRoutes()->refreshNameLookups();
        }
    }

    private function noindexTag(): string
    {
        return '<meta name="robots" content="noindex,nofollow">';
    }

    private function showUrl(Facility $facility): string
    {
        return route('facilities.show', $facility->slug);
    }

    // --- #9: header, SEO, breadcrumb --------------------------------------------------------------------

    public function test_index_renders_the_default_header_seo_and_breadcrumb(): void
    {
        $this->createFacility(['name' => 'Ruang Operasi', 'category' => FacilityCategory::Medis]);

        $response = $this->get(route('facilities.index'))->assertOk();

        $response->assertSee('<title>Fasilitas — '.config('app.name').'</title>', false)
            ->assertSee('<h1 class="mb-1">Fasilitas</h1>', false)
            ->assertSee('Fasilitas medis, penunjang, dan umum')
            ->assertSee('<meta name="description" content="Daftar fasilitas UNPAM HOSPITAL.">', false)
            ->assertSee('<meta property="og:title" content="Fasilitas">', false)
            ->assertSee('<link rel="canonical" href="'.route('facilities.index').'">', false)
            ->assertSeeInOrder(['aria-label="Breadcrumb"', 'Beranda', 'Fasilitas'], false)
            ->assertSee('<li class="breadcrumb-item active" aria-current="page">Fasilitas</li>', false)
            ->assertDontSee($this->noindexTag(), false);
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public function test_index_header_and_seo_follow_the_facilities_index_page_row(): void
    {
        $this->createPage('facilities-index', [
            'title' => 'Sarana Kami',
            'subtitle' => 'Sarana untuk pasien',
            'meta_title' => 'Sarana Kami Meta',
            'meta_description' => 'Deskripsi sarana kami.',
        ]);
        $this->createFacility(['category' => FacilityCategory::Umum]);

        $this->get(route('facilities.index'))
            ->assertOk()
            ->assertSee('<title>Sarana Kami Meta — '.config('app.name').'</title>', false)
            ->assertSee('<h1 class="mb-1">Sarana Kami</h1>', false)
            ->assertSee('Sarana untuk pasien')
            ->assertSee('<meta name="description" content="Deskripsi sarana kami.">', false)
            ->assertSee('<meta property="og:title" content="Sarana Kami Meta">', false);
    }

    // --- #9: pengelompokan (F4B-R29) ----------------------------------------------------------------------

    public function test_facilities_are_grouped_by_category_in_the_enum_order_with_icons_and_labels(): void
    {
        $this->createFacility(['name' => 'Kursi Roda Umum', 'category' => FacilityCategory::Umum, 'sort_order' => 1]);
        $this->createFacility(['name' => 'Laboratorium Klinik', 'category' => FacilityCategory::Penunjang, 'sort_order' => 1]);
        $this->createFacility(['name' => 'IGD Dua Puluh Empat Jam', 'category' => FacilityCategory::Medis, 'sort_order' => 2]);
        $this->createFacility(['name' => 'Ruang Operasi', 'category' => FacilityCategory::Medis, 'sort_order' => 1]);

        $response = $this->get(route('facilities.index'))->assertOk();

        $response->assertSeeInOrder([
            'id="fasilitas-medis"',
            'Ruang Operasi',
            'IGD Dua Puluh Empat Jam',
            'id="fasilitas-penunjang"',
            'Laboratorium Klinik',
            'id="fasilitas-umum"',
            'Kursi Roda Umum',
        ], false)
            ->assertSee('<i class="bi bi-heart-pulse" aria-hidden="true"></i> Fasilitas medis</h2>', false)
            ->assertSee('<i class="bi bi-tools" aria-hidden="true"></i> Penunjang</h2>', false)
            ->assertSee('<i class="bi bi-building" aria-hidden="true"></i> Fasilitas umum</h2>', false);
        $this->assertSame(3, substr_count($response->getContent(), '<h2 class="h4 mb-3" id="fasilitas-'));
    }

    public function test_inside_a_category_facilities_follow_sort_order_then_name(): void
    {
        $this->createFacility(['name' => 'Beta Ruang', 'category' => FacilityCategory::Medis, 'sort_order' => 2]);
        $this->createFacility(['name' => 'Alfa Ruang', 'category' => FacilityCategory::Medis, 'sort_order' => 2]);
        $this->createFacility(['name' => 'Zeta Ruang', 'category' => FacilityCategory::Medis, 'sort_order' => 1]);

        $this->get(route('facilities.index'))
            ->assertOk()
            ->assertSeeInOrder(['Zeta Ruang', 'Alfa Ruang', 'Beta Ruang']);
    }

    public function test_a_category_without_active_facilities_is_skipped(): void
    {
        $this->createFacility(['name' => 'Ruang Tindakan', 'category' => FacilityCategory::Medis]);
        $this->createFacility(['name' => 'Lab Nonaktif', 'category' => FacilityCategory::Penunjang, 'is_active' => false]);

        $this->get(route('facilities.index'))
            ->assertOk()
            ->assertSee('id="fasilitas-medis"', false)
            ->assertDontSee('id="fasilitas-penunjang"', false)
            ->assertDontSee('id="fasilitas-umum"', false)
            ->assertDontSee('Lab Nonaktif');
    }

    public function test_inactive_facilities_are_not_listed(): void
    {
        $this->createFacility(['name' => 'Fasilitas Aktif Uji', 'category' => FacilityCategory::Umum]);
        $this->createFacility(['name' => 'Fasilitas Mati Uji', 'category' => FacilityCategory::Umum, 'is_active' => false]);

        $this->get(route('facilities.index'))
            ->assertOk()
            ->assertSee('Fasilitas Aktif Uji')
            ->assertDontSee('Fasilitas Mati Uji');
    }

    // --- #9: kosong (EMP-01) ---------------------------------------------------------------------------------

    public function test_an_empty_database_shows_the_empty_state(): void
    {
        $response = $this->get(route('facilities.index'))->assertOk();

        $response->assertSee('Belum ada fasilitas')
            ->assertSee('Informasi fasilitas akan tampil di sini setelah ditambahkan.')
            ->assertSee('bi-building', false)
            ->assertSee('role="status"', false)
            ->assertDontSee('id="fasilitas-', false)
            ->assertDontSee($this->noindexTag(), false);
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public function test_only_inactive_facilities_also_show_the_empty_state(): void
    {
        $this->createFacility(['name' => 'Hanya Nonaktif', 'is_active' => false]);

        $this->get(route('facilities.index'))
            ->assertOk()
            ->assertSee('Belum ada fasilitas')
            ->assertDontSee('Hanya Nonaktif');
    }

    // --- #9: kartu ------------------------------------------------------------------------------------------------

    public function test_a_card_links_to_the_detail_by_slug_with_one_stretched_link_and_the_placeholder_image(): void
    {
        $facility = $this->createFacility([
            'name' => 'Ruang Hemodialisis',
            'short_description' => 'Layanan cuci darah contoh.',
            'category' => FacilityCategory::Medis,
        ]);

        $response = $this->get(route('facilities.index'))->assertOk();
        $html = $response->getContent();

        $response->assertSee('href="'.$this->showUrl($facility).'"', false)
            ->assertSee('<h3 class="card-title h5 mb-1">', false)
            ->assertSee('Layanan cuci darah contoh.')
            ->assertSee('images/placeholders/facility.svg', false)
            ->assertSee('alt="Ruang Hemodialisis"', false)
            ->assertDontSee('onerror', false);
        $this->assertSame(1, substr_count($html, 'stretched-link'));
        $this->assertSame(0, preg_match('/<a\b[^>]*>(?:(?!<\/a>).)*<a\b/s', $html));
    }

    public function test_a_card_uses_the_cover_thumbnail_and_its_alt_text(): void
    {
        $facility = $this->createFacility(['name' => 'Ruang Radiologi', 'category' => FacilityCategory::Penunjang]);
        $this->attachImage($facility, 'cover', 'Foto contoh ruang radiologi');

        $this->get(route('facilities.index'))
            ->assertOk()
            ->assertSee('alt="Foto contoh ruang radiologi"', false)
            ->assertSee('/thumbs/', false)
            ->assertDontSee('images/placeholders/facility.svg" alt', false);
    }

    public function test_many_facilities_with_covers_render_in_strict_mode(): void
    {
        $this->assertTrue(Model::preventsLazyLoading());

        foreach ([FacilityCategory::Medis, FacilityCategory::Penunjang, FacilityCategory::Umum] as $index => $category) {
            foreach ([1, 2] as $number) {
                $facility = $this->createFacility([
                    'name' => "Fasilitas {$category->value} {$number}",
                    'category' => $category,
                    'sort_order' => $number,
                ]);
                $this->attachImage($facility, 'cover', "Alt {$category->value} {$number}", "c{$index}{$number}.jpg");
            }
        }

        $html = (string) $this->get(route('facilities.index'))->assertOk()->getContent();

        $this->assertSame(6, substr_count($html, 'class="card h-100 facility-card"'));
        $this->assertSame(3, substr_count($html, '<h2 class="h4 mb-3" id="fasilitas-'));
        $this->assertStringContainsString('alt="Alt umum 2"', $html);
        $this->assertSame(0, preg_match('/\son[a-z]+\s*=/i', $html));
    }

    // --- #9: tautan bawah (bersyarat) ------------------------------------------------------------------------------

    public function test_the_bottom_links_appear_only_for_registered_routes(): void
    {
        $this->createFacility();

        if (! Route::has('rooms.index')) {
            $this->get(route('facilities.index'))->assertOk()->assertDontSee('>Lihat Kamar Rawat Inap</a>', false);
        }

        $this->fakeRoute('rooms.index', '/_uji/kamar');
        $this->fakeRoute('contact', '/_uji/kontak');

        $this->get(route('facilities.index'))
            ->assertOk()
            ->assertSee('<a class="btn btn-outline-primary" href="'.route('rooms.index').'">Lihat Kamar Rawat Inap</a>', false)
            ->assertSee('<a class="btn btn-outline-primary" href="'.route('contact').'">Kontak</a>', false);
    }

    // --- menu publik ---------------------------------------------------------------------------------------------------

    public function test_the_facilities_menu_item_is_visible_and_marked_active_on_the_pages(): void
    {
        $facility = $this->createFacility();

        $href = preg_quote(route('facilities.index'), '#');

        // Layout menulis atribut dengan spasi di sekitar @if, sehingga jarak antaratribut dicocokkan longgar.
        $home = (string) $this->get(route('home'))->assertOk()->getContent();
        $this->assertSame(1, preg_match('#<a class="dropdown-item" href="'.$href.'"\s*>Fasilitas</a>#', $home));

        $detail = (string) $this->get($this->showUrl($facility))->assertOk()->getContent();
        $this->assertSame(1, preg_match('#<a class="dropdown-item active" href="'.$href.'"\s+aria-current="page"\s*>Fasilitas</a>#', $detail));
    }

    // --- detail: 404 (Review Focus 3) --------------------------------------------------------------------------------

    public function test_an_inactive_facility_is_404_for_everyone_including_staff(): void
    {
        $facility = $this->createFacility(['name' => 'Fasilitas Mati', 'is_active' => false]);
        $url = $this->showUrl($facility);

        $this->get($url)->assertNotFound();

        foreach (['content-admin', 'registration-officer', 'super-admin'] as $role) {
            $this->actingAs($this->createStaff($role))->get($url)->assertNotFound();
        }
    }

    public function test_an_unknown_slug_is_404(): void
    {
        $this->get(route('facilities.show', 'tidak-ada'))->assertNotFound();
        $this->get('/fasilitas/Tidak%20Ada')->assertNotFound();
        $this->get('/fasilitas/123')->assertNotFound();
    }

    public function test_activating_a_facility_makes_its_detail_reachable(): void
    {
        $facility = $this->createFacility(['name' => 'Segera Dibuka', 'is_active' => false]);

        $this->get($this->showUrl($facility))->assertNotFound();

        $facility->forceFill(['is_active' => true])->save();

        $this->get($this->showUrl($facility))->assertOk()->assertSee('Segera Dibuka');
    }

    // --- detail: isi ------------------------------------------------------------------------------------------------------

    public function test_detail_renders_the_header_description_and_back_links(): void
    {
        $facility = $this->createFacility([
            'name' => 'Ruang Bersalin',
            'category' => FacilityCategory::Medis,
            'short_description' => 'Ruang bersalin contoh.',
            'description' => '<p>Isi deskripsi ruang bersalin.</p>',
        ]);
        $this->fakeRoute('rooms.index', '/_uji/kamar');

        $response = $this->get($this->showUrl($facility))->assertOk();

        $response->assertSee('<h1 class="mb-1">Ruang Bersalin</h1>', false)
            ->assertSee('Fasilitas medis')
            ->assertSee('Ruang bersalin contoh.')
            ->assertSee('<div class="rich-text facility-body"><p>Isi deskripsi ruang bersalin.</p></div>', false)
            ->assertSee('Kembali ke daftar fasilitas')
            ->assertSee('href="'.route('facilities.index').'"', false)
            ->assertSee('>Lihat Kamar Rawat Inap</a>', false)
            ->assertSee('images/placeholders/facility.svg', false)
            ->assertSee('alt="Ruang Bersalin"', false);
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public function test_detail_breadcrumb_links_back_to_the_index(): void
    {
        $facility = $this->createFacility(['name' => 'Jejak Fasilitas']);

        $this->get($this->showUrl($facility))
            ->assertOk()
            ->assertSeeInOrder(['aria-label="Breadcrumb"', 'Beranda', 'Fasilitas', 'Jejak Fasilitas'], false)
            ->assertSee('<li class="breadcrumb-item"><a href="'.route('facilities.index').'">Fasilitas</a></li>', false)
            ->assertSee('<li class="breadcrumb-item active" aria-current="page">Jejak Fasilitas</li>', false);
    }

    public function test_a_dangerous_description_is_sanitized_and_a_blank_one_renders_no_block(): void
    {
        $dangerous = $this->createFacility([
            'name' => 'Fasilitas Berbahaya',
            'description' => '<p>Aman</p><script>alert(1)</script><img src="x" onerror="alert(2)">',
        ]);
        $blank = $this->createFacility(['name' => 'Fasilitas Tanpa Deskripsi', 'description' => '<p><br></p>']);

        $this->get($this->showUrl($dangerous))
            ->assertOk()
            ->assertSee('<p>Aman</p>', false)
            ->assertDontSee('<script>alert(1)', false)
            ->assertDontSee('onerror', false);

        $this->get($this->showUrl($blank))
            ->assertOk()
            ->assertDontSee('facility-body', false);
    }

    public function test_detail_uses_the_cover_with_its_alt_text(): void
    {
        $facility = $this->createFacility(['name' => 'Ruang Berfoto']);
        $media = $this->attachImage($facility, 'cover', 'Foto contoh ruang berfoto');

        $this->get($this->showUrl($facility))
            ->assertOk()
            ->assertSee('alt="Foto contoh ruang berfoto"', false)
            ->assertSee('src="'.$media->url.'"', false)
            ->assertDontSee('images/placeholders/facility.svg" alt', false);
    }

    // --- detail: galeri (pola klinik) -------------------------------------------------------------------------------------

    public function test_detail_renders_thumbnails_and_one_modal_carousel(): void
    {
        $facility = $this->createFacility(['name' => 'Ruang Galeri']);

        foreach ([1, 2, 3] as $number) {
            $this->attachImage($facility, 'gallery', "Foto galeri {$number}", "g{$number}.jpg");
        }

        $response = $this->get($this->showUrl($facility))->assertOk();
        $html = $response->getContent();

        $response->assertSee('<h2 id="galeri-judul" class="h4 mb-3">Galeri</h2>', false)
            ->assertSee('id="galeri-modal"', false)
            ->assertSee('data-gallery-modal', false)
            ->assertSee('role="dialog"', false)
            ->assertSee('aria-labelledby="galeri-modal-judul"', false)
            ->assertSee('Galeri Ruang Galeri')
            ->assertSee('id="galeri-carousel"', false)
            ->assertSee('Perbesar foto 1 dari 3: Foto galeri 1')
            ->assertSee('Perbesar foto 3 dari 3: Foto galeri 3')
            ->assertSee('Foto sebelumnya')
            ->assertSee('Foto berikutnya');
        $this->assertSame(3, substr_count($html, 'data-gallery-index='));
        $this->assertSame(3, substr_count($html, 'class="carousel-item'));
        $this->assertSame(1, substr_count($html, 'class="carousel-item active"'));
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('/thumbs/', $html);
    }

    public function test_the_cover_is_not_part_of_the_gallery(): void
    {
        $facility = $this->createFacility(['name' => 'Ruang Sampul']);
        $this->attachImage($facility, 'cover', 'Sampul ruang');
        $this->attachImage($facility, 'gallery', 'Galeri satu', 'g1.jpg');

        $html = (string) $this->get($this->showUrl($facility))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'data-gallery-index='));
        $this->assertSame(1, substr_count($html, 'class="carousel-item'));
        $this->assertStringNotContainsString('Foto sebelumnya', $html);
    }

    public function test_without_a_gallery_there_is_no_modal(): void
    {
        $facility = $this->createFacility(['name' => 'Ruang Tanpa Galeri']);
        $this->attachImage($facility, 'cover', 'Hanya sampul');

        $this->get($this->showUrl($facility))
            ->assertOk()
            ->assertDontSee('galeri-modal', false)
            ->assertDontSee('data-gallery-index', false)
            ->assertDontSee('id="galeri-judul"', false);
    }

    // --- detail: SEO (F4B-R29) ----------------------------------------------------------------------------------------------------

    public function test_detail_title_and_description_follow_meta_fields_then_name_and_short_description(): void
    {
        $plain = $this->createFacility(['name' => 'Fasilitas Polos', 'short_description' => 'Ringkasan polos.', 'meta_title' => null, 'meta_description' => null]);
        $meta = $this->createFacility(['name' => 'Fasilitas Meta', 'short_description' => 'Ringkasan meta.', 'meta_title' => 'Judul Meta Fasilitas', 'meta_description' => 'Deskripsi meta fasilitas.']);

        $this->get($this->showUrl($plain))
            ->assertOk()
            ->assertSee('<title>Fasilitas Polos — '.config('app.name').'</title>', false)
            ->assertSee('<meta name="description" content="Ringkasan polos.">', false)
            ->assertSee('<meta property="og:type" content="website">', false)
            ->assertSee('<meta property="og:title" content="Fasilitas Polos">', false)
            ->assertSee('<meta property="og:description" content="Ringkasan polos.">', false)
            ->assertSee('<link rel="canonical" href="'.$this->showUrl($plain).'">', false)
            ->assertDontSee('og:image', false)
            ->assertDontSee($this->noindexTag(), false);

        $this->get($this->showUrl($meta))
            ->assertOk()
            ->assertSee('<title>Judul Meta Fasilitas — '.config('app.name').'</title>', false)
            ->assertSee('<meta name="description" content="Deskripsi meta fasilitas.">', false)
            ->assertSee('<meta property="og:title" content="Judul Meta Fasilitas">', false);
    }

    public function test_detail_og_image_is_the_absolute_cover_url(): void
    {
        $facility = $this->createFacility(['name' => 'Fasilitas Berbingkai']);
        $media = $this->attachImage($facility, 'cover', 'Sampul fasilitas');

        $this->get($this->showUrl($facility))
            ->assertOk()
            ->assertSee('<meta property="og:image" content="'.url($media->url).'">', false);
    }

    public function test_detail_description_is_cut_at_160_characters(): void
    {
        $long = str_repeat('Deskripsi fasilitas panjang. ', 10);
        $facility = $this->createFacility(['name' => 'Fasilitas Panjang', 'short_description' => $long, 'meta_description' => null]);

        $html = (string) $this->get($this->showUrl($facility))->assertOk()->getContent();

        $this->assertSame(1, preg_match('/<meta name="description" content="([^"]*)">/', $html, $match));
        $this->assertSame(Str::limit(trim($long), 160, ''), html_entity_decode($match[1]));
        $this->assertLessThanOrEqual(160, mb_strlen(html_entity_decode($match[1])));
    }

    // --- akses dan keamanan markup -------------------------------------------------------------------------------------------------

    public function test_pages_are_identical_for_guests_patients_and_staff(): void
    {
        $facility = $this->createFacility(['name' => 'Terbuka Untuk Semua']);

        foreach ([null, 'patient', 'content-admin', 'registration-officer', 'super-admin'] as $actor) {
            if ($actor === 'patient') {
                $this->actingAs(User::factory()->patient()->create());
            } elseif ($actor !== null) {
                $this->actingAs($this->createStaff($actor));
            }

            $this->get(route('facilities.index'))->assertOk()->assertSee('Terbuka Untuk Semua');
            $this->get($this->showUrl($facility))->assertOk()->assertSee('Terbuka Untuk Semua');
        }
    }

    public function test_the_views_use_escaped_output_and_no_inline_event_handlers(): void
    {
        $facility = $this->createFacility(['name' => 'Nama <b>Tebal</b> Uji', 'short_description' => '<i>Miring</i> ringkas']);

        $index = (string) $this->get(route('facilities.index'))->assertOk()->getContent();
        $show = (string) $this->get($this->showUrl($facility))->assertOk()->getContent();

        foreach ([$index, $show] as $html) {
            $this->assertStringContainsString('Nama &lt;b&gt;Tebal&lt;/b&gt; Uji', $html);
            $this->assertStringNotContainsString('<b>Tebal</b>', $html);
            $this->assertStringNotContainsString('<i>Miring</i>', $html);
            $this->assertSame(0, preg_match('/\son[a-z]+\s*=/i', $html));
            $this->assertStringNotContainsString('javascript:', $html);
        }

        foreach (['index', '_card', 'show'] as $view) {
            $source = (string) file_get_contents(resource_path("views/public/facilities/{$view}.blade.php"));

            $this->assertNotSame('', $source, $view);
            $this->assertSame(0, preg_match('/\son[a-z]{3,}\s*=\s*["\']/i', $source), $view);
        }
    }
}
```

- [ ] **Step 3: Jalankan tes, harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter='PublicFacilitiesTest|RawOutputScanTest'
```
Expected: FAIL. Alasan yang diharapkan: `Route [facilities.index] not defined.` dan `Route [facilities.show] not defined.` (seluruh tes `PublicFacilitiesTest` yang memanggil `route()`), 404 pada URL literal `/fasilitas/...`, serta `RawOutputScanTest::test_raw_blade_output_is_only_used_for_the_rich_text_columns` gagal karena daftar baris `{!!` aktual hanya tiga (baris `public/facilities/show.blade.php` belum ada).

- [ ] **Step 4: Implementasi controller (tool Write)**

`app/Http/Controllers/Public/FacilityController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Enums\FacilityCategory;
use App\Http\Controllers\Controller;
use App\Models\Facility;
use App\Models\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Halaman publik fasilitas (#9 `facilities.index`, #10 `facilities.show`). Hanya fasilitas aktif; yang nonaktif
 * menjawab 404 untuk semua orang, termasuk staf (tanpa Policy, A-42). Daftar dikelompokkan per kategori dalam
 * urutan `FacilityCategory::cases()`; kategori tanpa fasilitas dilewati; tanpa paginasi (F4B-R29).
 */
class FacilityController extends Controller
{
    public function index(): View
    {
        $facilities = Facility::query()
            ->active()
            ->with(['media' => fn ($query) => $query->where('collection', 'cover')])
            ->ordered()
            ->get();

        /** @var Collection<int, array{0: FacilityCategory, 1: Collection<int, Facility>}> $groups */
        $groups = collect(FacilityCategory::cases())
            ->map(fn (FacilityCategory $category): array => [
                $category,
                $facilities->filter(fn (Facility $facility): bool => $facility->category === $category)->values(),
            ])
            ->filter(fn (array $group): bool => $group[1]->isNotEmpty())
            ->values();

        return view('public.facilities.index', [
            'page' => Page::forKey('facilities-index'),
            'groups' => $groups,
        ]);
    }

    public function show(Facility $facility): View
    {
        // Tanpa Policy: nonaktif = 404 untuk semua orang, termasuk staf (A-42).
        abort_unless($facility->is_active, 404);

        $facility->load('media');

        return view('public.facilities.show', [
            'facility' => $facility,
            'gallery' => $facility->mediaIn('gallery'),
            // Deskripsi SEO: `meta_description`, lalu `short_description`; dipotong 160 karakter (F4B-R29).
            'metaDescription' => Str::limit(trim((string) $facility->seo_description), 160, ''),
        ]);
    }
}
```

- [ ] **Step 5: Implementasi route (tool Edit)**

`routes/web.php`, dua Edit. Masing-masing `old_string` cocok tepat satu kali (keadaan sesudah Task 6; tugas 4-6 hanya mengubah `routes/admin.php`).

old_string:

```php
use App\Http\Controllers\Public\DoctorController;
use App\Http\Controllers\Public\FaqController;
```

new_string:

```php
use App\Http\Controllers\Public\DoctorController;
use App\Http\Controllers\Public\FacilityController;
use App\Http\Controllers\Public\FaqController;
```

old_string:

```php
Route::get('/jadwal-dokter', [ScheduleController::class, 'index'])->name('schedules.index');
```

new_string:

```php
Route::get('/jadwal-dokter', [ScheduleController::class, 'index'])->name('schedules.index');

// Fasilitas (#9, #10): hanya fasilitas aktif; yang nonaktif 404 untuk semua orang, termasuk staf (F4B-R14, A-42).
Route::get('/fasilitas', [FacilityController::class, 'index'])->name('facilities.index');
Route::get('/fasilitas/{facility:slug}', [FacilityController::class, 'show'])->name('facilities.show');
```

- [ ] **Step 6: Implementasi SCSS (tool Edit)**

`resources/scss/_public.scss` (blok `.rich-text` adalah akhir berkas). `old_string` cocok tepat satu kali:

old_string:

```scss
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
```

new_string:

```scss
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

// ---------------------------------------------------------------- Fasilitas (#9, #10, F4B-R29)
.facility-card {
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

// Deskripsi fasilitas (rich text tersanitasi): baris teks tidak melebar di layar lebar.
.facility-body {
  max-width: 48rem;
}
```

- [ ] **Step 7: Implementasi view (tool Write)**

`resources/views/public/facilities/_card.blade.php`:

```blade
@php
    $cover = $facility->firstMedia('cover');
    $level = $headingLevel ?? 3;
@endphp
<article class="card h-100 facility-card">
    <x-image type="facility" :media="$cover" :alt="$cover?->alt_text ?: $facility->name" variant="thumb" ratio="4x3" />
    <div class="card-body">
        <h{{ $level }} class="card-title h5 mb-1">
            <a class="stretched-link" href="{{ route('facilities.show', $facility) }}">{{ $facility->name }}</a>
        </h{{ $level }}>
        @if (filled($facility->short_description))
            <p class="card-text mb-0">{{ $facility->short_description }}</p>
        @endif
    </div>
</article>
```

`resources/views/public/facilities/index.blade.php`:

```blade
<x-layouts.public :title="$page->seo_title" :meta-description="$page->seo_description">
    <x-slot:breadcrumb>
        <x-breadcrumb :items="[['label' => 'Fasilitas']]" />
    </x-slot:breadcrumb>

    @push('head')
        <x-open-graph :title="$page->seo_title" :description="$page->seo_description" :canonical="route('facilities.index')" />
    @endpush

    <x-page-header :title="$page->title" :subtitle="$page->subtitle" />

    @if ($groups->isEmpty())
        <x-empty-state title="Belum ada fasilitas" message="Informasi fasilitas akan tampil di sini setelah ditambahkan." icon="bi-building" />
    @else
        @foreach ($groups as [$category, $items])
            <section class="mb-5" aria-labelledby="fasilitas-{{ $category->value }}">
                <h2 class="h4 mb-3" id="fasilitas-{{ $category->value }}"><i class="bi {{ $category->icon() }}" aria-hidden="true"></i> {{ $category->label() }}</h2>
                <div class="row g-4 row-cols-1 row-cols-sm-2 row-cols-lg-3">
                    @foreach ($items as $facility)
                        <div class="col">
                            @include('public.facilities._card', ['facility' => $facility, 'headingLevel' => 3])
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach

        @if (Route::has('rooms.index') || Route::has('contact'))
            <p class="d-flex flex-wrap gap-2 mb-0">
                @if (Route::has('rooms.index'))
                    <a class="btn btn-outline-primary" href="{{ route('rooms.index') }}">Lihat Kamar Rawat Inap</a>
                @endif
                @if (Route::has('contact'))
                    <a class="btn btn-outline-primary" href="{{ route('contact') }}">Kontak</a>
                @endif
            </p>
        @endif
    @endif
</x-layouts.public>
```

`resources/views/public/facilities/show.blade.php`:

```blade
@php
    $cover = $facility->firstMedia('cover');
    $coverAlt = $cover?->alt_text ?: $facility->name;
@endphp
<x-layouts.public :title="$facility->seo_title" :meta-description="$metaDescription">
    <x-slot:breadcrumb>
        <x-breadcrumb :items="[['label' => 'Fasilitas', 'url' => route('facilities.index')], ['label' => $facility->name]]" />
    </x-slot:breadcrumb>

    @push('head')
        <x-open-graph :title="$facility->seo_title" :description="$metaDescription" :image="$cover?->url ? url($cover->url) : null" :canonical="route('facilities.show', $facility)" />
    @endpush

    <x-page-header :title="$facility->name" :subtitle="$facility->category->label()" />

    <div class="row">
        <div class="col-lg-9">
            <x-image type="facility" :media="$cover" :alt="$coverAlt" ratio="16x9" :lazy="false" />

            @if (filled($facility->short_description))
                <p class="lead mt-4">{{ $facility->short_description }}</p>
            @endif

            @if (filled($facility->description))
                <div class="rich-text facility-body">{!! $facility->description !!}</div>
            @endif

            @if ($gallery->isNotEmpty())
                <section class="mt-5" aria-labelledby="galeri-judul">
                    <h2 id="galeri-judul" class="h4 mb-3">Galeri</h2>
                    <div class="gallery-grid">
                        @foreach ($gallery as $index => $photo)
                            <button type="button" class="gallery-thumb" data-bs-toggle="modal" data-bs-target="#galeri-modal" data-gallery-index="{{ $index }}" aria-label="Perbesar foto {{ $index + 1 }} dari {{ $gallery->count() }}: {{ $photo->alt_text }}">
                                <x-image type="facility" :media="$photo" decorative variant="thumb" ratio="4x3" />
                            </button>
                        @endforeach
                    </div>
                </section>

                <div class="modal fade" id="galeri-modal" tabindex="-1" role="dialog" aria-labelledby="galeri-modal-judul" aria-hidden="true" data-gallery-modal>
                    <div class="modal-dialog modal-lg modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <div class="modal-title h5" id="galeri-modal-judul">Galeri {{ $facility->name }}</div>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup galeri"></button>
                            </div>
                            <div class="modal-body p-0">
                                <div id="galeri-carousel" class="carousel slide" tabindex="-1" data-bs-ride="false" aria-label="Foto galeri">
                                    <div class="carousel-inner">
                                        @foreach ($gallery as $index => $photo)
                                            <div @class(['carousel-item', 'active' => $index === 0])>
                                                <x-image type="facility" :media="$photo" :lazy="false" class="d-block w-100" />
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

            <p class="d-flex flex-wrap gap-2 mt-5 mb-0">
                <a class="btn btn-outline-primary" href="{{ route('facilities.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Kembali ke daftar fasilitas</a>
                @if (Route::has('rooms.index'))
                    <a class="btn btn-outline-primary" href="{{ route('rooms.index') }}">Lihat Kamar Rawat Inap</a>
                @endif
            </p>
        </div>
    </div>
</x-layouts.public>
```

Catatan penulisan: (1) `<h{{ $level }} class="card-title h5 mb-1">` menghasilkan `<h3 class="card-title h5 mb-1">` pada bawaan; tes mencocokkan string itu; (2) `<div class="rich-text facility-body">{!! $facility->description !!}</div>` harus berada pada satu baris sendiri agar cocok dengan `RawOutputScanTest`; (3) di galeri, `x-image` pada carousel tidak diberi `alt` eksplisit: alt diambil dari `alt_text` media (wajib terisi oleh `MediaService`), seperti pada galeri poliklinik; (4) `<p class="d-flex ...">` membungkus tautan agar tombol "Kembali" dan "Lihat Kamar Rawat Inap" berdampingan di layar lebar dan bertumpuk di layar sempit.

- [ ] **Step 8: Build aset dan jalankan tes tugas, harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
npm run build
& $PHP artisan test --filter='PublicFacilitiesTest|RawOutputScanTest|FrontendManifestTest|PublicClinicDetailTest|NavigationMenuTest|LayoutsTest|AdminPagesTest'
```
Expected: `npm run build` sukses; seluruh tes lulus. `RawOutputScanTest` hijau karena daftar kini empat baris dan `test_public_views_have_no_inline_event_handlers` ikut memindai view baru. `FrontendManifestTest` hijau karena tidak ada entri Vite baru dan tidak ada listener `error` baru. `NavigationMenuTest`, `LayoutsTest`, dan `AdminPagesTest` (tes yang bergantung pada `Route::has('facilities.index')`) tetap hijau karena asersinya dinamis terhadap `Route::has`.

- [ ] **Step 9: Pint**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint
& $PHP vendor\bin\pint --test
```
Expected: `--test` PASS (0 berkas bermasalah).

- [ ] **Step 10: Suite penuh (latar belakang)**

Suite ±11–14 menit melebihi batas 10 menit sebuah perintah tool. Jalankan sebagai tugas latar belakang (tool PowerShell dengan `run_in_background`), tunggu sampai selesai, lalu baca ringkasannya.

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
$LOG = Join-Path $env:TEMP "suite-fase4b-t07.txt"
& $PHP artisan test | Out-File -FilePath $LOG -Encoding utf8
Select-String -Path $LOG -Pattern 'Tests:|FAIL|Duration' | Select-Object -Last 20
```
Expected: seluruh tes PASS (±36 tes baru di atas angka Task 6).

- [ ] **Step 11: Commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git add app/Http/Controllers/Public/FacilityController.php resources/views/public/facilities/index.blade.php resources/views/public/facilities/show.blade.php resources/views/public/facilities/_card.blade.php resources/scss/_public.scss routes/web.php tests/Feature/Public/PublicFacilitiesTest.php tests/Feature/View/RawOutputScanTest.php
git commit -m @'
feat(fase-4b): halaman publik daftar fasilitas (#9) dan detail fasilitas (#10)

Daftar menampilkan fasilitas aktif dikelompokkan per kategori (medis, penunjang, umum); kategori tanpa isi
dilewati dan keadaan kosong memakai x-empty-state. Detail memakai ulang markup modal dan carousel galeri
poliklinik tanpa mengubah JS, dengan SEO dari meta_title/meta_description lalu name/short_description.
Fasilitas nonaktif dan slug tidak dikenal menjawab 404 untuk semua orang termasuk staf. Daftar baris {!! !!}
yang dikunci RawOutputScanTest bertambah satu (facilities.description).

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
'@
```
Expected: commit tercatat.

---

### Task 8: Halaman publik #11 Ringkasan Kamar, #12–#15 Detail Kamar, dan blok kamar di beranda

**Files:**
- Create: `app/Http/Controllers/Public/RoomCategoryController.php`
- Create: `resources/views/public/rooms/index.blade.php`, `resources/views/public/rooms/show.blade.php`, `resources/views/public/rooms/_availability-badge.blade.php`, `resources/views/public/rooms/_notice.blade.php`
- Create: `resources/views/public/partials/_room-summary.blade.php`
- Modify: `routes/web.php` (impor `RoomCategoryController`; route `rooms.index` dan `rooms.show` sesudah route `facilities.show`)
- Modify: `app/Http/Controllers/Public/HomeController.php` (konstanta `FEATURED_ROOMS = 4`, kueri kategori kamar, variabel view `rooms`)
- Modify: `resources/views/public/home.blade.php` (sisipkan `_room-summary` antara `_featured-doctors` dan `_latest-articles`)
- Modify: `resources/scss/_public.scss` (kelas `.room-card`, `.room-summary-panel`, `.amenity-list`, `.room-body`)
- Modify (tes lama): `tests/Feature/View/RawOutputScanTest.php` (daftar baris `{!!` dari 4 menjadi 5), `tests/Feature/Public/PublicHomeTest.php` (tes blok kamar, trait `InteractsWithFacilities`, daftar view beranda)
- Test (baru): `tests/Feature/Public/PublicRoomsTest.php`
- Tidak diubah: `resources/js/app.js`, `config/navigation.php` (menu "Kamar Rawat Inap" di header dan footer sudah ada dan tersaring `Route::has`), `public/facilities/*` (Task 7), model dan factory Task 2, `x-image`, layout publik.

**Interfaces:**
- Consumes:
  - Task 2: `App\Models\RoomCategory` (`STALE_AFTER_HOURS`; scope `active()`, `ordered()`; accessor `occupancy_label`, `availability_updated_label`, `is_availability_stale`, `is_full`, `sample_rate_label`, `seo_title`, `seo_description`; cast `amenities` ke larik; relasi `media`, koleksi `cover` dan `gallery`), `Tests\Support\InteractsWithFacilities` (`createRoomCategory(array $attributes = []): RoomCategory`).
  - Task 7: pola galeri modal `public/facilities/show.blade.php` (disalin dengan `type="room"`), `FacilityController` sebagai contoh controller, baris route `facilities.show` (jangkar Edit `routes/web.php`), akhir `.facility-body` di `_public.scss` (jangkar Edit SCSS), baris `RawOutputScanTest` keempat (jangkar Edit tes).
  - Fase 4a: `App\Models\Page::forKey('rooms-index')`, `x-open-graph`, `InteractsWithContent::createPage`, `HomeController` dan `public/home.blade.php` (Task 15 4a), `PublicHomeTest` (helper `section()`, `assertInOrder()`, `fakeRoute()`).
  - Fase 1-3: `x-layouts.public`, `x-breadcrumb`, `x-page-header`, `x-empty-state`, `x-image` (tipe `room`, rasio `4x3`), `initGalleryModals()`, `SettingsService::whatsappUrl()`, `MediaService`, `ImageFixtures`.
- Produces:
  - `App\Http\Controllers\Public\RoomCategoryController` (`index(): View`, `show(RoomCategory $roomCategory): View`).
  - Route `rooms.index` (`/kamar-rawat-inap`) dan `rooms.show` (`/kamar-rawat-inap/{roomCategory:slug}`).
  - View `public.rooms.index`, `public.rooms.show`, partial `public.rooms._availability-badge` (`$room`), `public.rooms._notice` (kotak peringatan F4B-R11), `public.partials._room-summary` (`$rooms`).
  - `HomeController::FEATURED_ROOMS = 4` dan variabel view `rooms` pada beranda.
  - Kelas SCSS `.room-card`, `.room-summary-panel`, `.amenity-list`, `.room-body`.
  - Baris kelima `RawOutputScanTest`: `public/rooms/show.blade.php: <div class="rich-text room-body">{!! $room->description !!}</div>`.

**Test IDs:** F-PUB-08 dan F-PUB-09 (halaman #11–#15 dan blok beranda), CRD-07 (kategori kamar tampil di publik dengan label dan teks persis), EMP-01 (daftar kamar kosong), SEK-08/SEK-15 (deskripsi tersanitasi, `{!! !!}` kelima), Review Focus 1 (badge "Penuh" saat 0, kedaluwarsa tepat 24 jam versus 24 jam + 1 menit, "Belum diperbarui", tanpa kata "real-time"), Review Focus 3 (404 nonaktif dan slug acak termasuk staf; DB kosong; tanpa `LazyLoadingViolationException`), F4B-R9 sampai F4B-R12, F4B-R30, F4B-R31.

**Catatan teknis (diverifikasi dari source):**
1. **Badge ketersediaan (F4B-R9).** Hanya dua keadaan: `beds_available > 0` memakai `badge text-bg-success` dengan ikon `bi-check-circle` dan teks "Tersedia"; `beds_available = 0` memakai `badge text-bg-danger` dengan ikon `bi-x-circle` dan teks "Penuh". Ikon `aria-hidden`, teks selalu tampil, sehingga warna tidak menjadi satu-satunya pembeda (00 D). Status "Terbatas" tidak dibuat. Partial menerima `$room` dan dipakai oleh #11, #12–#15, dan blok beranda.
2. **Teks dari accessor (F4B-R10, F4B-R12).** View tidak menghitung apa pun sendiri: `occupancy_label` ("{n} dari {m} tempat tidur tersedia"), `availability_updated_label` ("Diperbarui manual oleh petugas pada {j M Y}, pukul {H.i} WIB" atau "Belum diperbarui"), `is_availability_stale` (NULL atau lebih tua dari `RoomCategory::STALE_AFTER_HOURS` = 24 jam), dan `sample_rate_label` ("Rp 400.000 per malam" atau "Hubungi kami"). Bulan memakai singkatan Indonesia dari Carbon (`Okt`), seperti tes admin 4a (`AdminArticlesTest.php:170`).
3. **Kalimat peringatan (F4B-R11).** Kata "real-time", "langsung", dan "live" tidak dipakai di UI ketersediaan kamar (01, 02 F-NXT-08). #11 dan #12–#15 memakai kotak `alert alert-info` (ikon `bi-info-circle`) dari partial `_notice`; blok beranda memakai versi pendek di dalam `<p>`. Kalimat kedaluwarsa "Data mungkin tidak terkini. Hubungi rumah sakit untuk memastikan ketersediaan." memakai `text-warning-emphasis`.
4. **Satu route, satu view untuk #12–#15.** Keempat kategori seed (`bpjs-kelas-1`, `bpjs-kelas-2`, `bpjs-kelas-3`, `vip`) memakai `rooms.show` dan `public/rooms/show.blade.php` (F4B-R30). Tes membuat keempatnya dengan slug eksplisit lewat `createRoomCategory`.
5. **Tanpa Policy, 404 untuk semua aktor.** `abort_unless($roomCategory->is_active, 404)` seperti `Public\ClinicController::show` (`app/Http/Controllers/Public/ClinicController.php:38`). Binding memakai `{roomCategory:slug}`; nama parameter route `roomCategory` sama dengan nama variabel controller.
6. **Mode ketat.** Daftar memuat `media` dengan batasan koleksi `cover`; detail memanggil `$roomCategory->load('media')`. Relasi `availabilityUpdater` tidak dipakai halaman publik (nama petugas tidak ditampilkan), sehingga tidak perlu di-eager-load. Blok beranda tidak memakai gambar sehingga kuerinya tanpa `with`.
7. **Tombol detail (F4B-R30).** "Tanya Ketersediaan" menaut `route('contact')` hanya bila `Route::has('contact')` (route itu baru dibuat Task 9; tes memakai `fakeRoute`). "Chat via WhatsApp (membuka WhatsApp)" memakai `$site->whatsappUrl()` dan hanya tampil bila tidak null, dengan `target="_blank" rel="noopener noreferrer"` (`SettingsService.php:182`).
8. **Blok beranda (F4B-R31).** `HomeController::index` (`app/Http/Controllers/Public/HomeController.php:32-81`) menambah kueri `RoomCategory::query()->active()->ordered()->limit(self::FEATURED_ROOMS)->get()`; `home.blade.php:37-38` memuat `_featured-doctors` lalu `_latest-articles`, dan `_room-summary` disisipkan di antara keduanya. Partial disembunyikan bila tidak ada kategori aktif. Judul `<h2 id="judul-kamar">` "Ketersediaan Kamar Rawat Inap".
9. **`{!! !!}` baru.** Satu baris di `rooms/show.blade.php`; baris itu harus berdiri sendiri dan persis `<div class="rich-text room-body">{!! $room->description !!}</div>`. Urutan `sort($found)`: `articles`, `clinics`, `facilities`, `partials`, `rooms`.
10. **Tes lama.** `PublicHomeTest` dibaca dari berkas saat ini: helper `section()` (baris 55-63) dan `assertInOrder()` (baris 65-76) dipakai ulang; daftar view di `test_the_home_views_use_escaped_output_and_no_inline_event_handlers` (baris 576) bertambah satu entri. Tes beranda kosong (baris 89-109) tetap hijau karena blok kamar disembunyikan tanpa kategori aktif.
11. **SCSS.** Tugas ini mengubah `resources/scss`, jadi `npm run build` dijalankan sebelum suite.
12. Jumlah tes baru: ±42 (`PublicRoomsTest` ±34, `PublicHomeTest` ±8).

- [ ] **Step 1: Verifikasi prasyarat (Task 1-7 selesai)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git branch --show-current
git status --short
git log --oneline -3
Test-Path app\Models\RoomCategory.php, tests\Support\InteractsWithFacilities.php, app\Http\Controllers\Public\FacilityController.php, resources\views\public\facilities\show.blade.php
Test-Path app\Http\Controllers\Public\RoomCategoryController.php, resources\views\public\rooms\index.blade.php, resources\views\public\partials\_room-summary.blade.php
Select-String -Path routes\web.php -Pattern "facilities\.show|rooms\."
& $PHP artisan test --filter='PublicFacilitiesTest|RoomCategoryModelTest|PublicHomeTest|RawOutputScanTest'
```
Expected: `fase-4b-fasilitas-kamar-kontak`; `git status` bersih (kecuali `.superpowers/`); commit Task 7 terlihat; `True` x4, lalu `False` x3; `Select-String` hanya mencetak baris `facilities.show`; keempat kelas tes PASS.

- [ ] **Step 2: Tulis tes yang gagal**

**2a. Edit tes lama `tests/Feature/View/RawOutputScanTest.php` (tool Edit).** `old_string` (teks baru Task 7) cocok tepat satu kali:

old_string:

```php
            'public/partials/_page-body.blade.php: <div class="rich-text page-body">{!! $page->body !!}</div>',
        ], $found);
```

new_string:

```php
            'public/partials/_page-body.blade.php: <div class="rich-text page-body">{!! $page->body !!}</div>',
            'public/rooms/show.blade.php: <div class="rich-text room-body">{!! $room->description !!}</div>',
        ], $found);
```

**2b. Tes baru (tool Write) `tests/Feature/Public/PublicRoomsTest.php`:**

```php
<?php

namespace Tests\Feature\Public;

use App\Models\Media;
use App\Models\RoomCategory;
use App\Models\User;
use App\Services\Media\MediaService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ImageFixtures;
use Tests\Support\InteractsWithContent;
use Tests\Support\InteractsWithFacilities;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * Halaman #11 `rooms.index` dan #12-#15 `rooms.show` (05 hal. 11-15, 07 W-07, F4B-R9 s.d. F4B-R12, F4B-R30):
 * kartu dan detail kategori kamar aktif dengan badge Tersedia/Penuh (teks + ikon + warna), label dari accessor,
 * kalimat peringatan tanpa kata "real-time", dan 404 untuk kategori nonaktif bagi semua aktor. Waktu dibekukan
 * pada Senin 5 Oktober 2026 pukul 10.00 WIB. Mencakup CRD-07, EMP-01, Review Focus 1 dan 3, serta mode ketat.
 */
class PublicRoomsTest extends TestCase
{
    use InteractsWithContent;
    use InteractsWithFacilities;
    use InteractsWithStaff;
    use RefreshDatabase;

    private const NOTICE = 'Ketersediaan tempat tidur diperbarui manual oleh petugas dan dapat berubah sewaktu-waktu. Hubungi rumah sakit untuk memastikan. Nama dan klasifikasi kategori adalah kebutuhan prototipe, bukan ketentuan BPJS yang sudah diverifikasi. Tarif adalah contoh.';

    private const STALE = 'Data mungkin tidak terkini. Hubungi rumah sakit untuk memastikan ketersediaan.';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->travelTo('2026-10-05 10:00:00'); // Senin
    }

    /** @param  array<string, mixed>  $overrides */
    private function room(array $overrides = []): RoomCategory
    {
        return $this->createRoomCategory(array_merge([
            'name' => 'BPJS Kelas 1',
            'slug' => 'bpjs-kelas-1',
            'short_description' => 'Kamar dua tempat tidur contoh.',
            'bed_capacity' => 20,
            'beds_available' => 12,
            'sample_rate' => 400000,
            'rate_unit' => 'per malam',
            'rate_note' => null,
            'meta_title' => null,
            'meta_description' => null,
            'amenities' => ['AC', 'Kamar mandi dalam'],
            'availability_updated_at' => now()->subHours(2),
            'sort_order' => 1,
            'is_active' => true,
        ], $overrides));
    }

    private function attachImage(Model $owner, string $collection, string $alt, string $name = 'foto.jpg'): Media
    {
        return $this->app->make(MediaService::class)->store(
            ImageFixtures::jpeg(1200, 800, $name),
            $collection,
            $owner,
            ['alt_text' => $alt],
        );
    }

    private function fakeRoute(string $name, string $uri): void
    {
        if (! Route::has($name)) {
            Route::middleware('web')->get($uri, fn () => 'x')->name($name);
            Route::getRoutes()->refreshNameLookups();
        }
    }

    private function showUrl(RoomCategory $room): string
    {
        return route('rooms.show', $room->slug);
    }

    private function noindexTag(): string
    {
        return '<meta name="robots" content="noindex,nofollow">';
    }

    // --- #11: header, SEO, breadcrumb, peringatan ----------------------------------------------------------

    public function test_index_renders_the_default_header_seo_breadcrumb_and_the_notice(): void
    {
        $this->room();

        $response = $this->get(route('rooms.index'))->assertOk();

        $response->assertSee('<title>Kamar Rawat Inap — '.config('app.name').'</title>', false)
            ->assertSee('<h1 class="mb-1">Kamar Rawat Inap</h1>', false)
            ->assertSee('Ringkasan kategori kamar dan ketersediaan')
            ->assertSee('<meta name="description" content="Kategori kamar rawat inap, kapasitas, dan ketersediaan yang diperbarui manual.">', false)
            ->assertSee('<meta property="og:title" content="Kamar Rawat Inap">', false)
            ->assertSee('<link rel="canonical" href="'.route('rooms.index').'">', false)
            ->assertSeeInOrder(['aria-label="Breadcrumb"', 'Beranda', 'Kamar Rawat Inap'], false)
            ->assertSee('<li class="breadcrumb-item active" aria-current="page">Kamar Rawat Inap</li>', false)
            ->assertSee('alert alert-info', false)
            ->assertSee('bi-info-circle', false)
            ->assertSee(self::NOTICE)
            ->assertDontSee($this->noindexTag(), false);
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public function test_index_header_and_seo_follow_the_rooms_index_page_row(): void
    {
        $this->createPage('rooms-index', [
            'title' => 'Kamar Kami',
            'subtitle' => 'Pilihan kamar',
            'meta_title' => 'Kamar Kami Meta',
            'meta_description' => 'Deskripsi kamar kami.',
        ]);
        $this->room();

        $this->get(route('rooms.index'))
            ->assertOk()
            ->assertSee('<title>Kamar Kami Meta — '.config('app.name').'</title>', false)
            ->assertSee('<h1 class="mb-1">Kamar Kami</h1>', false)
            ->assertSee('Pilihan kamar')
            ->assertSee('<meta name="description" content="Deskripsi kamar kami.">', false);
    }

    // --- #11: kartu -----------------------------------------------------------------------------------------------

    public function test_index_lists_active_categories_in_order_with_the_exact_texts(): void
    {
        $this->room(['name' => 'Kamar Kedua', 'slug' => 'kamar-kedua', 'sort_order' => 2, 'bed_capacity' => 6, 'beds_available' => 2, 'sample_rate' => 1250000]);
        $this->room(['name' => 'Kamar Pertama', 'slug' => 'kamar-pertama', 'sort_order' => 1]);
        $this->room(['name' => 'Kamar Nonaktif Uji', 'slug' => 'kamar-nonaktif', 'sort_order' => 0, 'is_active' => false]);

        $response = $this->get(route('rooms.index'))->assertOk();

        $response->assertSeeInOrder(['Kamar Pertama', 'Kamar Kedua'])
            ->assertSee('Kapasitas: 20 tempat tidur')
            ->assertSee('12 dari 20 tempat tidur tersedia')
            ->assertSee('Tarif contoh: Rp 400.000 per malam')
            ->assertSee('Kapasitas: 6 tempat tidur')
            ->assertSee('2 dari 6 tempat tidur tersedia')
            ->assertSee('Tarif contoh: Rp 1.250.000 per malam')
            ->assertSee('Diperbarui manual oleh petugas pada 5 Okt 2026, pukul 08.00 WIB')
            ->assertSee('Kamar dua tempat tidur contoh.')
            ->assertDontSee('Kamar Nonaktif Uji');
        $this->assertSame(2, substr_count($response->getContent(), 'class="card h-100 room-card"'));
    }

    public function test_each_card_has_one_stretched_link_a_decorative_button_and_no_nested_anchors(): void
    {
        $room = $this->room();

        $response = $this->get(route('rooms.index'))->assertOk();
        $html = $response->getContent();

        $response->assertSee('href="'.$this->showUrl($room).'"', false)
            ->assertSee('<h2 class="card-title h5 mb-1">', false)
            ->assertSee('<span class="btn btn-outline-primary mt-auto" aria-hidden="true">Lihat Detail</span>', false)
            ->assertSee('images/placeholders/room.svg', false)
            ->assertSee('alt="BPJS Kelas 1"', false);
        $this->assertSame(1, substr_count($html, 'stretched-link'));
        $this->assertSame(0, preg_match('/<a\b[^>]*>(?:(?!<\/a>).)*<a\b/s', $html));
    }

    public function test_a_card_uses_the_cover_thumbnail_and_its_alt_text(): void
    {
        $room = $this->room();
        $this->attachImage($room, 'cover', 'Foto contoh kamar kelas satu');

        $this->get(route('rooms.index'))
            ->assertOk()
            ->assertSee('alt="Foto contoh kamar kelas satu"', false)
            ->assertSee('/thumbs/', false);
    }

    // --- badge (F4B-R9) -----------------------------------------------------------------------------------------------

    public function test_the_badge_says_available_with_icon_and_color_when_beds_remain(): void
    {
        $this->room(['beds_available' => 1]);

        $response = $this->get(route('rooms.index'))->assertOk();

        $response->assertSee('<span class="badge text-bg-success"><i class="bi bi-check-circle" aria-hidden="true"></i> Tersedia</span>', false)
            ->assertDontSee('text-bg-danger', false)
            ->assertDontSee('Terbatas');
    }

    public function test_the_badge_says_full_with_icon_and_color_when_no_bed_remains(): void
    {
        $this->room(['beds_available' => 0]);

        $response = $this->get(route('rooms.index'))->assertOk();

        $response->assertSee('<span class="badge text-bg-danger"><i class="bi bi-x-circle" aria-hidden="true"></i> Penuh</span>', false)
            ->assertSee('0 dari 20 tempat tidur tersedia')
            ->assertDontSee('text-bg-success', false);
    }

    public function test_the_badge_is_available_when_every_bed_is_free(): void
    {
        $this->room(['bed_capacity' => 8, 'beds_available' => 8]);

        $this->get(route('rooms.index'))
            ->assertOk()
            ->assertSee('8 dari 8 tempat tidur tersedia')
            ->assertSee('bi-check-circle', false);
    }

    // --- pembaruan manual dan kedaluwarsa (F4B-R10, BR-114) ---------------------------------------------------------------

    public function test_a_never_updated_category_says_so_and_is_marked_stale(): void
    {
        $this->room(['availability_updated_at' => null]);

        $this->get(route('rooms.index'))
            ->assertOk()
            ->assertSee('Belum diperbarui')
            ->assertSee(self::STALE)
            ->assertSee('text-warning-emphasis', false)
            ->assertDontSee('Diperbarui manual oleh petugas pada');
    }

    public function test_the_stale_sentence_appears_only_after_exactly_twenty_four_hours(): void
    {
        $this->room(['name' => 'Tepat Dua Puluh Empat', 'slug' => 'tepat-24', 'availability_updated_at' => now()->subHours(24), 'sort_order' => 1]);

        $this->get(route('rooms.index'))->assertOk()->assertDontSee(self::STALE);

        $this->room(['name' => 'Lewat Semenit', 'slug' => 'lewat-semenit', 'availability_updated_at' => now()->subHours(24)->subMinute(), 'sort_order' => 2]);

        $html = (string) $this->get(route('rooms.index'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, self::STALE));
    }

    public function test_a_fresh_update_shows_no_stale_sentence(): void
    {
        $this->room(['availability_updated_at' => now()->subMinutes(5)]);

        $this->get(route('rooms.index'))
            ->assertOk()
            ->assertSee('Diperbarui manual oleh petugas pada 5 Okt 2026, pukul 09.55 WIB')
            ->assertDontSee(self::STALE);
    }

    public function test_the_rate_says_contact_us_when_there_is_no_sample_rate(): void
    {
        $this->room(['sample_rate' => null]);

        $this->get(route('rooms.index'))
            ->assertOk()
            ->assertSee('Tarif contoh: Hubungi kami')
            ->assertDontSee('Rp ');
    }

    public function test_pages_never_use_the_words_real_time_live_or_directly(): void
    {
        $room = $this->room(['beds_available' => 0, 'availability_updated_at' => null]);
        $this->room(['name' => 'Kamar Lain', 'slug' => 'kamar-lain', 'sort_order' => 2]);

        foreach ([route('rooms.index'), $this->showUrl($room)] as $url) {
            $html = (string) $this->get($url)->assertOk()->getContent();
            $text = strip_tags($html);

            $this->assertStringNotContainsString('real-time', $html);
            $this->assertStringNotContainsString('Real-time', $html);
            $this->assertDoesNotMatchRegularExpression('/real[- ]?time|langsung|\blive\b/i', $text, $url);
        }
    }

    // --- #11: kosong dan mode ketat (EMP-01) ----------------------------------------------------------------------------------

    public function test_an_empty_database_shows_the_empty_state_and_still_the_notice(): void
    {
        $response = $this->get(route('rooms.index'))->assertOk();

        $response->assertSee('Belum ada kategori kamar')
            ->assertSee('Informasi kamar rawat inap akan tampil di sini setelah ditambahkan.')
            ->assertSee('bi-building', false)
            ->assertSee('role="status"', false)
            ->assertSee(self::NOTICE)
            ->assertDontSee('room-card', false)
            ->assertDontSee($this->noindexTag(), false);
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public function test_only_inactive_categories_also_show_the_empty_state(): void
    {
        $this->room(['name' => 'Hanya Nonaktif', 'is_active' => false]);

        $this->get(route('rooms.index'))
            ->assertOk()
            ->assertSee('Belum ada kategori kamar')
            ->assertDontSee('Hanya Nonaktif');
    }

    public function test_many_categories_with_covers_render_in_strict_mode(): void
    {
        $this->assertTrue(Model::preventsLazyLoading());

        foreach (range(1, 4) as $number) {
            $room = $this->room(['name' => "Kamar Ketat {$number}", 'slug' => "kamar-ketat-{$number}", 'sort_order' => $number]);
            $this->attachImage($room, 'cover', "Alt kamar {$number}", "r{$number}.jpg");
        }

        $html = (string) $this->get(route('rooms.index'))->assertOk()->getContent();

        $this->assertSame(4, substr_count($html, 'class="card h-100 room-card"'));
        $this->assertStringContainsString('alt="Alt kamar 3"', $html);
        $this->assertSame(0, preg_match('/\son[a-z]+\s*=/i', $html));
    }

    // --- menu publik ---------------------------------------------------------------------------------------------------------------

    public function test_the_room_menu_items_are_visible_and_active_on_the_room_pages(): void
    {
        $room = $this->room();
        $href = preg_quote(route('rooms.index'), '#');

        $home = (string) $this->get(route('home'))->assertOk()->getContent();
        $this->assertSame(1, preg_match('#<a class="dropdown-item" href="'.$href.'"\s*>Kamar Rawat Inap</a>#', $home));
        $this->assertStringContainsString('<li><a href="'.route('rooms.index').'">Kamar Rawat Inap</a></li>', $home);

        $detail = (string) $this->get($this->showUrl($room))->assertOk()->getContent();
        $this->assertSame(1, preg_match('#<a class="dropdown-item active" href="'.$href.'"\s+aria-current="page"\s*>Kamar Rawat Inap</a>#', $detail));
    }

    // --- detail: 404 (Review Focus 3) -------------------------------------------------------------------------------------------------

    public function test_an_inactive_category_is_404_for_everyone_including_staff(): void
    {
        $room = $this->room(['is_active' => false]);
        $url = $this->showUrl($room);

        $this->get($url)->assertNotFound();

        foreach (['content-admin', 'registration-officer', 'super-admin'] as $role) {
            $this->actingAs($this->createStaff($role))->get($url)->assertNotFound();
        }
    }

    public function test_an_unknown_slug_is_404(): void
    {
        $this->get(route('rooms.show', 'tidak-ada'))->assertNotFound();
        $this->get('/kamar-rawat-inap/Tidak%20Ada')->assertNotFound();
        $this->get('/kamar-rawat-inap/123')->assertNotFound();
    }

    public function test_activating_a_category_makes_its_detail_reachable(): void
    {
        $room = $this->room(['name' => 'Segera Dibuka', 'slug' => 'segera-dibuka', 'is_active' => false]);

        $this->get($this->showUrl($room))->assertNotFound();

        $room->forceFill(['is_active' => true])->save();

        $this->get($this->showUrl($room))->assertOk()->assertSee('Segera Dibuka');
    }

    /** @return array<string, array{string, string}> */
    public static function seedSlugs(): array
    {
        return [
            'kelas 1' => ['bpjs-kelas-1', 'BPJS Kelas 1'],
            'kelas 2' => ['bpjs-kelas-2', 'BPJS Kelas 2'],
            'kelas 3' => ['bpjs-kelas-3', 'BPJS Kelas 3'],
            'vip' => ['vip', 'VIP'],
        ];
    }

    #[DataProvider('seedSlugs')]
    public function test_the_four_seed_slugs_share_one_route_and_one_view(string $slug, string $name): void
    {
        $this->room(['name' => $name, 'slug' => $slug]);

        $this->get('/kamar-rawat-inap/'.$slug)
            ->assertOk()
            ->assertSee('<h1 class="mb-1">'.$name.'</h1>', false)
            ->assertSee('<title>'.$name.' — '.config('app.name').'</title>', false);
    }

    // --- detail: isi -----------------------------------------------------------------------------------------------------------------------

    public function test_detail_renders_the_header_notice_breadcrumb_and_back_link(): void
    {
        $room = $this->room();

        $response = $this->get($this->showUrl($room))->assertOk();

        $response->assertSee('<h1 class="mb-1">BPJS Kelas 1</h1>', false)
            ->assertSee('Kamar dua tempat tidur contoh.')
            ->assertSee(self::NOTICE)
            ->assertSeeInOrder(['aria-label="Breadcrumb"', 'Beranda', 'Kamar Rawat Inap', 'BPJS Kelas 1'], false)
            ->assertSee('<li class="breadcrumb-item"><a href="'.route('rooms.index').'">Kamar Rawat Inap</a></li>', false)
            ->assertSee('<li class="breadcrumb-item active" aria-current="page">BPJS Kelas 1</li>', false)
            ->assertSee('Kembali ke daftar kamar')
            ->assertSee('images/placeholders/room.svg', false);
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public function test_detail_shows_the_summary_panel_with_exact_labels(): void
    {
        $room = $this->room(['rate_note' => 'Belum termasuk tindakan medis.']);

        $response = $this->get($this->showUrl($room))->assertOk();

        $response->assertSee('<h2 class="h5 mb-3">Ringkasan</h2>', false)
            ->assertSee('20 tempat tidur')
            ->assertSee('12 dari 20 tempat tidur tersedia')
            ->assertSee('<span class="badge text-bg-success"><i class="bi bi-check-circle" aria-hidden="true"></i> Tersedia</span>', false)
            ->assertSee('Tarif contoh: Rp 400.000 per malam')
            ->assertSee('Belum termasuk tindakan medis.')
            ->assertSee('<time datetime="2026-10-05T08:00:00+07:00">Diperbarui manual oleh petugas pada 5 Okt 2026, pukul 08.00 WIB</time>', false)
            ->assertDontSee(self::STALE);
    }

    public function test_detail_of_a_never_updated_full_category(): void
    {
        $room = $this->room(['beds_available' => 0, 'availability_updated_at' => null, 'sample_rate' => null]);

        $this->get($this->showUrl($room))
            ->assertOk()
            ->assertSee('<span class="badge text-bg-danger"><i class="bi bi-x-circle" aria-hidden="true"></i> Penuh</span>', false)
            ->assertSee('Belum diperbarui')
            ->assertSee(self::STALE)
            ->assertSee('Tarif contoh: Hubungi kami')
            ->assertDontSee('<time', false);
    }

    public function test_detail_lists_amenities_and_hides_the_block_when_there_are_none(): void
    {
        $with = $this->room(['amenities' => ['AC', 'Kamar mandi dalam', '<b>Televisi</b>']]);
        $without = $this->room(['name' => 'Tanpa Fasilitas', 'slug' => 'tanpa-fasilitas', 'amenities' => null, 'sort_order' => 2]);
        $empty = $this->room(['name' => 'Fasilitas Kosong', 'slug' => 'fasilitas-kosong', 'amenities' => [], 'sort_order' => 3]);

        $this->get($this->showUrl($with))
            ->assertOk()
            ->assertSee('<h2 id="fasilitas-kamar-judul" class="h4 mb-3">Fasilitas Kamar</h2>', false)
            ->assertSee('<ul class="amenity-list">', false)
            ->assertSeeInOrder(['<i class="bi bi-check2" aria-hidden="true"></i> AC', 'Kamar mandi dalam', '&lt;b&gt;Televisi&lt;/b&gt;'], false)
            ->assertDontSee('<b>Televisi</b>', false);

        foreach ([$without, $empty] as $room) {
            $this->get($this->showUrl($room))
                ->assertOk()
                ->assertDontSee('Fasilitas Kamar')
                ->assertDontSee('amenity-list', false);
        }
    }

    public function test_a_dangerous_description_is_sanitized_and_a_blank_one_renders_no_block(): void
    {
        $dangerous = $this->room(['description' => '<p>Aman</p><script>alert(1)</script><img src="x" onerror="alert(2)">']);
        $blank = $this->room(['name' => 'Tanpa Deskripsi', 'slug' => 'tanpa-deskripsi', 'description' => '<p><br></p>', 'sort_order' => 2]);

        $this->get($this->showUrl($dangerous))
            ->assertOk()
            ->assertSee('<div class="rich-text room-body"><p>Aman</p>', false)
            ->assertDontSee('<script>alert(1)', false)
            ->assertDontSee('onerror', false);

        $this->get($this->showUrl($blank))->assertOk()->assertDontSee('room-body', false);
    }

    public function test_detail_uses_the_cover_with_its_alt_text(): void
    {
        $room = $this->room();
        $media = $this->attachImage($room, 'cover', 'Foto contoh kamar kelas satu');

        $this->get($this->showUrl($room))
            ->assertOk()
            ->assertSee('alt="Foto contoh kamar kelas satu"', false)
            ->assertSee('src="'.$media->url.'"', false);
    }

    // --- detail: tombol --------------------------------------------------------------------------------------------------------------------------

    public function test_the_whatsapp_button_is_hidden_without_a_configured_number(): void
    {
        $room = $this->room();

        $this->get($this->showUrl($room))
            ->assertOk()
            ->assertDontSee('wa.me')
            ->assertDontSee('Chat via WhatsApp');
    }

    public function test_the_whatsapp_button_opens_wa_me_in_a_new_tab_when_a_number_is_configured(): void
    {
        $this->seed(SettingsSeeder::class);
        $room = $this->room();

        $this->get($this->showUrl($room))
            ->assertOk()
            ->assertSee('href="https://wa.me/6280000000000" target="_blank" rel="noopener noreferrer"', false)
            ->assertSee('Chat via WhatsApp (membuka WhatsApp)');
    }

    public function test_the_ask_availability_button_links_to_contact_only_when_the_route_exists(): void
    {
        $room = $this->room();

        if (! Route::has('contact')) {
            $this->get($this->showUrl($room))->assertOk()->assertDontSee('Tanya Ketersediaan');

            $this->fakeRoute('contact', '/_uji/kontak');
        }

        $this->get($this->showUrl($room))
            ->assertOk()
            ->assertSee('<a class="btn btn-primary w-100 mb-2" href="'.route('contact').'">Tanya Ketersediaan</a>', false);
    }

    // --- detail: galeri -----------------------------------------------------------------------------------------------------------------------------

    public function test_detail_renders_thumbnails_and_one_modal_carousel(): void
    {
        $room = $this->room();

        foreach ([1, 2, 3] as $number) {
            $this->attachImage($room, 'gallery', "Foto kamar {$number}", "k{$number}.jpg");
        }

        $response = $this->get($this->showUrl($room))->assertOk();
        $html = $response->getContent();

        $response->assertSee('id="galeri-modal"', false)
            ->assertSee('data-gallery-modal', false)
            ->assertSee('aria-labelledby="galeri-modal-judul"', false)
            ->assertSee('Galeri BPJS Kelas 1')
            ->assertSee('Perbesar foto 1 dari 3: Foto kamar 1')
            ->assertSee('Perbesar foto 3 dari 3: Foto kamar 3')
            ->assertSee('Foto sebelumnya')
            ->assertSee('Foto berikutnya');
        $this->assertSame(3, substr_count($html, 'data-gallery-index='));
        $this->assertSame(3, substr_count($html, 'class="carousel-item'));
        $this->assertSame(1, substr_count($html, 'class="carousel-item active"'));
        $this->assertSame(1, substr_count($html, '<h1'));
    }

    public function test_without_a_gallery_there_is_no_modal(): void
    {
        $room = $this->room();
        $this->attachImage($room, 'cover', 'Hanya sampul');

        $this->get($this->showUrl($room))
            ->assertOk()
            ->assertDontSee('galeri-modal', false)
            ->assertDontSee('data-gallery-index', false);
    }

    // --- detail: SEO (F4B-R30) -----------------------------------------------------------------------------------------------------------------------------

    public function test_detail_title_and_description_follow_meta_fields_then_name_and_short_description(): void
    {
        $plain = $this->room(['meta_title' => null, 'meta_description' => null]);
        $meta = $this->room(['name' => 'Kamar Meta', 'slug' => 'kamar-meta', 'meta_title' => 'Judul Meta Kamar', 'meta_description' => 'Deskripsi meta kamar.', 'sort_order' => 2]);

        $this->get($this->showUrl($plain))
            ->assertOk()
            ->assertSee('<title>BPJS Kelas 1 — '.config('app.name').'</title>', false)
            ->assertSee('<meta name="description" content="Kamar dua tempat tidur contoh.">', false)
            ->assertSee('<meta property="og:type" content="website">', false)
            ->assertSee('<meta property="og:title" content="BPJS Kelas 1">', false)
            ->assertSee('<meta property="og:description" content="Kamar dua tempat tidur contoh.">', false)
            ->assertSee('<link rel="canonical" href="'.$this->showUrl($plain).'">', false)
            ->assertDontSee('og:image', false)
            ->assertDontSee($this->noindexTag(), false);

        $this->get($this->showUrl($meta))
            ->assertOk()
            ->assertSee('<title>Judul Meta Kamar — '.config('app.name').'</title>', false)
            ->assertSee('<meta name="description" content="Deskripsi meta kamar.">', false)
            ->assertSee('<meta property="og:title" content="Judul Meta Kamar">', false);
    }

    public function test_detail_og_image_is_the_absolute_cover_url(): void
    {
        $room = $this->room();
        $media = $this->attachImage($room, 'cover', 'Sampul kamar');

        $this->get($this->showUrl($room))
            ->assertOk()
            ->assertSee('<meta property="og:image" content="'.url($media->url).'">', false);
    }

    public function test_detail_description_is_cut_at_160_characters(): void
    {
        $long = str_repeat('Deskripsi kamar panjang. ', 10);
        $room = $this->room(['short_description' => $long, 'meta_description' => null]);

        $html = (string) $this->get($this->showUrl($room))->assertOk()->getContent();

        $this->assertSame(1, preg_match('/<meta name="description" content="([^"]*)">/', $html, $match));
        $this->assertSame(Str::limit(trim($long), 160, ''), html_entity_decode($match[1]));
    }

    // --- akses dan keamanan markup -----------------------------------------------------------------------------------------------------------------------------

    public function test_pages_are_identical_for_guests_patients_and_staff(): void
    {
        $room = $this->room(['name' => 'Terbuka Untuk Semua']);

        foreach ([null, 'patient', 'content-admin', 'registration-officer', 'super-admin'] as $actor) {
            if ($actor === 'patient') {
                $this->actingAs(User::factory()->patient()->create());
            } elseif ($actor !== null) {
                $this->actingAs($this->createStaff($actor));
            }

            $this->get(route('rooms.index'))->assertOk()->assertSee('Terbuka Untuk Semua');
            $this->get($this->showUrl($room))->assertOk()->assertSee('Terbuka Untuk Semua');
        }
    }

    public function test_the_views_use_escaped_output_and_no_inline_event_handlers(): void
    {
        $room = $this->room(['name' => 'Kamar <b>Tebal</b> Uji', 'short_description' => '<i>Miring</i> ringkas', 'rate_note' => '<u>Catatan</u>']);

        $index = (string) $this->get(route('rooms.index'))->assertOk()->getContent();
        $show = (string) $this->get($this->showUrl($room))->assertOk()->getContent();

        foreach ([$index, $show] as $html) {
            $this->assertStringContainsString('Kamar &lt;b&gt;Tebal&lt;/b&gt; Uji', $html);
            $this->assertStringNotContainsString('<b>Tebal</b>', $html);
            $this->assertStringNotContainsString('<i>Miring</i>', $html);
            $this->assertStringNotContainsString('<u>Catatan</u>', $html);
            $this->assertSame(0, preg_match('/\son[a-z]+\s*=/i', $html));
            $this->assertStringNotContainsString('javascript:', $html);
        }

        foreach (['index', 'show', '_availability-badge', '_notice'] as $view) {
            $source = (string) file_get_contents(resource_path("views/public/rooms/{$view}.blade.php"));

            $this->assertNotSame('', $source, $view);
            $this->assertSame(0, preg_match('/\son[a-z]{3,}\s*=\s*["\']/i', $source), $view);
        }
    }
}
```

**2c. Edit tes lama `tests/Feature/Public/PublicHomeTest.php` (tool Edit), empat Edit.** Masing-masing `old_string` cocok tepat satu kali.

old_string:

```php
use Tests\Support\InteractsWithContent;
use Tests\Support\InteractsWithMasterData;
```

new_string:

```php
use Tests\Support\InteractsWithContent;
use Tests\Support\InteractsWithFacilities;
use Tests\Support\InteractsWithMasterData;
```

old_string:

```php
    use InteractsWithContent;
    use InteractsWithMasterData;
```

new_string:

```php
    use InteractsWithContent;
    use InteractsWithFacilities;
    use InteractsWithMasterData;
```

old_string:

```php
        foreach (['home', 'partials/_banner-carousel', 'partials/_quick-search', 'partials/_featured-clinics', 'partials/_featured-doctors', 'partials/_latest-articles', 'partials/_help-cta'] as $view) {
```

new_string:

```php
        foreach (['home', 'partials/_banner-carousel', 'partials/_quick-search', 'partials/_featured-clinics', 'partials/_featured-doctors', 'partials/_room-summary', 'partials/_latest-articles', 'partials/_help-cta'] as $view) {
```

old_string:

```php
    // --- CTA bantuan -----------------------------------------------------------------------------
```

new_string:

```php
    // --- ketersediaan kamar (F4B-R31) ------------------------------------------------------------

    /** @param  array<string, mixed>  $overrides */
    private function homeRoom(int $number, array $overrides = []): void
    {
        $this->createRoomCategory(array_merge([
            'name' => "Kamar Beranda {$number}",
            'slug' => "kamar-beranda-{$number}",
            'bed_capacity' => 20,
            'beds_available' => 12,
            'sample_rate' => 400000,
            'rate_unit' => 'per malam',
            'availability_updated_at' => now()->subHours(2),
            'sort_order' => $number,
            'is_active' => true,
        ], $overrides));
    }

    public function test_the_room_block_is_hidden_without_active_categories(): void
    {
        $this->homeRoom(1, ['name' => 'Kamar Nonaktif Uji', 'is_active' => false]);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('Ketersediaan Kamar Rawat Inap')
            ->assertDontSee('id="judul-kamar"', false)
            ->assertDontSee('Kamar Nonaktif Uji');
    }

    public function test_the_room_block_shows_the_first_four_active_categories_in_display_order(): void
    {
        foreach (range(1, 5) as $number) {
            $this->homeRoom($number);
        }

        $this->homeRoom(0, ['name' => 'Kamar Mati Uji', 'slug' => 'kamar-mati-uji', 'is_active' => false]);

        $section = $this->section($this->get('/')->assertOk()->getContent(), 'judul-kamar');

        $this->assertInOrder(['Kamar Beranda 1', 'Kamar Beranda 2', 'Kamar Beranda 3', 'Kamar Beranda 4'], $section);
        $this->assertSame(4, substr_count($section, 'class="card h-100 room-card"'));

        foreach (['Kamar Beranda 5', 'Kamar Mati Uji'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $section);
        }

        $this->assertSame(4, HomeController::FEATURED_ROOMS);
    }

    public function test_each_room_card_has_the_name_link_the_occupancy_the_badge_and_the_update_label(): void
    {
        $this->homeRoom(1);
        $this->homeRoom(2, ['beds_available' => 0, 'availability_updated_at' => null]);

        $section = $this->section($this->get('/')->assertOk()->getContent(), 'judul-kamar');

        $this->assertStringContainsString('<h2 id="judul-kamar" class="mb-0">Ketersediaan Kamar Rawat Inap</h2>', $section);
        $this->assertStringContainsString('href="'.route('rooms.show', 'kamar-beranda-1').'">Kamar Beranda 1</a>', $section);
        $this->assertStringContainsString('12 dari 20 tempat tidur tersedia', $section);
        $this->assertStringContainsString('<span class="badge text-bg-success"><i class="bi bi-check-circle" aria-hidden="true"></i> Tersedia</span>', $section);
        $this->assertStringContainsString('Diperbarui manual oleh petugas pada 5 Okt 2026, pukul 08.00 WIB', $section);
        $this->assertStringContainsString('0 dari 20 tempat tidur tersedia', $section);
        $this->assertStringContainsString('<span class="badge text-bg-danger"><i class="bi bi-x-circle" aria-hidden="true"></i> Penuh</span>', $section);
        $this->assertStringContainsString('Belum diperbarui', $section);
    }

    public function test_the_room_block_has_the_short_notice_and_the_detail_link_without_forbidden_words(): void
    {
        $this->homeRoom(1);

        $section = $this->section($this->get('/')->assertOk()->getContent(), 'judul-kamar');

        $this->assertStringContainsString('Diperbarui manual oleh petugas. Hubungi rumah sakit untuk memastikan ketersediaan.', $section);
        $this->assertStringContainsString('href="'.route('rooms.index').'">Lihat detail kamar</a>', $section);
        $this->assertDoesNotMatchRegularExpression('/real[- ]?time|langsung|\blive\b/i', strip_tags($section));
    }

    public function test_the_room_block_sits_between_the_doctors_and_the_latest_articles(): void
    {
        $this->createDoctor(['name' => 'Uji Urutan', 'title_prefix' => 'dr.', 'title_suffix' => null]);
        $this->createArticle(['title' => 'Artikel Urutan Uji']);
        $this->homeRoom(1);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertInOrder(['id="judul-dokter-kami"', 'id="judul-kamar"', 'id="judul-artikel-terbaru"'], $html);
    }

    public function test_a_page_with_many_active_categories_renders_in_strict_mode(): void
    {
        foreach (range(1, 7) as $number) {
            $this->homeRoom($number);
        }

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertSame(4, substr_count($html, 'class="card h-100 room-card"'));
        $this->assertSame(1, substr_count($html, '<h1'));
    }

    // --- CTA bantuan -----------------------------------------------------------------------------
```

- [ ] **Step 3: Jalankan tes, harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter='PublicRoomsTest|PublicHomeTest|RawOutputScanTest'
```
Expected: FAIL. Alasan yang diharapkan: `Route [rooms.index] not defined.` dan `Route [rooms.show] not defined.` (`PublicRoomsTest`), 404 pada URL literal `/kamar-rawat-inap/...`, `Failed asserting that '…' contains "id=\"judul-kamar\""` pada tes blok beranda, `Undefined constant App\Http\Controllers\Public\HomeController::FEATURED_ROOMS`, dan `RawOutputScanTest::test_raw_blade_output_is_only_used_for_the_rich_text_columns` gagal karena baris `public/rooms/show.blade.php` belum ada. Tes beranda lain (hero, banner, dokter, CTA) tetap PASS.

- [ ] **Step 4: Implementasi controller (tool Write)**

`app/Http/Controllers/Public/RoomCategoryController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\RoomCategory;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Halaman publik kamar rawat inap (#11 `rooms.index`, #12-#15 `rooms.show`). Hanya kategori aktif; yang nonaktif
 * menjawab 404 untuk semua orang, termasuk staf (tanpa Policy, A-42). Satu route dan satu view melayani keempat
 * slug seed (F4B-R30). Tanpa paginasi: kategori biasanya tidak lebih dari sepuluh.
 */
class RoomCategoryController extends Controller
{
    public function index(): View
    {
        $rooms = RoomCategory::query()
            ->active()
            ->with(['media' => fn ($query) => $query->where('collection', 'cover')])
            ->ordered()
            ->get();

        return view('public.rooms.index', [
            'page' => Page::forKey('rooms-index'),
            'rooms' => $rooms,
        ]);
    }

    public function show(RoomCategory $roomCategory): View
    {
        // Tanpa Policy: nonaktif = 404 untuk semua orang, termasuk staf (A-42).
        abort_unless($roomCategory->is_active, 404);

        $roomCategory->load('media');

        return view('public.rooms.show', [
            'room' => $roomCategory,
            'gallery' => $roomCategory->mediaIn('gallery'),
            // Deskripsi SEO: `meta_description`, lalu `short_description`; dipotong 160 karakter (F4B-R30).
            'metaDescription' => Str::limit(trim((string) $roomCategory->seo_description), 160, ''),
        ]);
    }
}
```

- [ ] **Step 5: Implementasi route (tool Edit)**

`routes/web.php`, dua Edit. Masing-masing `old_string` (teks baru Task 7) cocok tepat satu kali.

old_string:

```php
use App\Http\Controllers\Public\PageController;
use App\Http\Controllers\Public\ScheduleController;
```

new_string:

```php
use App\Http\Controllers\Public\PageController;
use App\Http\Controllers\Public\RoomCategoryController;
use App\Http\Controllers\Public\ScheduleController;
```

old_string:

```php
Route::get('/fasilitas/{facility:slug}', [FacilityController::class, 'show'])->name('facilities.show');
```

new_string:

```php
Route::get('/fasilitas/{facility:slug}', [FacilityController::class, 'show'])->name('facilities.show');

// Kamar rawat inap (#11, #12-#15): hanya kategori aktif; satu route detail untuk keempat slug seed; nonaktif 404
// untuk semua orang, termasuk staf (F4B-R14, F4B-R30, A-42).
Route::get('/kamar-rawat-inap', [RoomCategoryController::class, 'index'])->name('rooms.index');
Route::get('/kamar-rawat-inap/{roomCategory:slug}', [RoomCategoryController::class, 'show'])->name('rooms.show');
```

- [ ] **Step 6: Implementasi `HomeController` dan `home.blade.php` (tool Edit)**

`app/Http/Controllers/Public/HomeController.php`, lima Edit. Masing-masing `old_string` cocok tepat satu kali.

old_string:

```php
use App\Models\Page;
use App\Models\Specialty;
```

new_string:

```php
use App\Models\Page;
use App\Models\RoomCategory;
use App\Models\Specialty;
```

old_string:

```php
 * (D-66, F3-R9). Blok kamar menyusul di fase 4b.
```

new_string:

```php
 * (D-66, F3-R9). Blok ketersediaan kamar: kategori aktif pertama, maksimal empat (F4B-R31).
```

old_string:

```php
    public const LATEST_ARTICLES = 3;
```

new_string:

```php
    public const LATEST_ARTICLES = 3;

    public const FEATURED_ROOMS = 4;
```

old_string:

```php
        $specialties = Specialty::query()->active()->ordered()->get(['id', 'name', 'slug']);
```

new_string:

```php
        // Blok kamar tidak memakai gambar, jadi tanpa eager load `media` (F4B-R31).
        $rooms = RoomCategory::query()
            ->active()
            ->ordered()
            ->limit(self::FEATURED_ROOMS)
            ->get();

        $specialties = Specialty::query()->active()->ordered()->get(['id', 'name', 'slug']);
```

old_string:

```php
            'articles' => $articles,
```

new_string:

```php
            'articles' => $articles,
            'rooms' => $rooms,
```

`resources/views/public/home.blade.php`, satu Edit. `old_string` cocok tepat satu kali:

old_string:

```blade
    @include('public.partials._featured-doctors')
    @include('public.partials._latest-articles')
```

new_string:

```blade
    @include('public.partials._featured-doctors')
    @include('public.partials._room-summary')
    @include('public.partials._latest-articles')
```

- [ ] **Step 7: Implementasi SCSS (tool Edit)**

`resources/scss/_public.scss`. `old_string` (akhir berkas, teks baru Task 7) cocok tepat satu kali:

old_string:

```scss
.facility-body {
  max-width: 48rem;
}
```

new_string:

```scss
.facility-body {
  max-width: 48rem;
}

// ---------------------------------------------------------------- Kamar rawat inap (#11 s.d. #15, blok beranda F4B-R31)
.room-card {
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

// Panel ringkasan detail kamar menempel di bawah navbar pada layar lebar.
.room-summary-panel {
  @include media-breakpoint-up(lg) {
    position: sticky;
    top: 5.5rem;
  }
}

// Daftar fasilitas kamar: tanpa bullet, ikon centang di depan tiap butir.
.amenity-list {
  display: grid;
  gap: .375rem;
  margin: 0;
  padding: 0;
  list-style: none;

  @include media-breakpoint-up(sm) {
    grid-template-columns: repeat(2, 1fr);
  }

  .bi {
    margin-right: .375rem;
    color: $teal-dark;
  }
}

// Deskripsi kamar (rich text tersanitasi).
.room-body {
  max-width: 48rem;
}
```

- [ ] **Step 8: Implementasi view (tool Write)**

`resources/views/public/rooms/_availability-badge.blade.php`:

```blade
@if ($room->beds_available > 0)
    <span class="badge text-bg-success"><i class="bi bi-check-circle" aria-hidden="true"></i> Tersedia</span>
@else
    <span class="badge text-bg-danger"><i class="bi bi-x-circle" aria-hidden="true"></i> Penuh</span>
@endif
```

`resources/views/public/rooms/_notice.blade.php`:

```blade
<div class="alert alert-info d-flex align-items-start gap-2" role="note">
    <i class="bi bi-info-circle flex-shrink-0" aria-hidden="true"></i>
    <div>Ketersediaan tempat tidur diperbarui manual oleh petugas dan dapat berubah sewaktu-waktu. Hubungi rumah sakit untuk memastikan. Nama dan klasifikasi kategori adalah kebutuhan prototipe, bukan ketentuan BPJS yang sudah diverifikasi. Tarif adalah contoh.</div>
</div>
```

`resources/views/public/rooms/index.blade.php`:

```blade
<x-layouts.public :title="$page->seo_title" :meta-description="$page->seo_description">
    <x-slot:breadcrumb>
        <x-breadcrumb :items="[['label' => 'Kamar Rawat Inap']]" />
    </x-slot:breadcrumb>

    @push('head')
        <x-open-graph :title="$page->seo_title" :description="$page->seo_description" :canonical="route('rooms.index')" />
    @endpush

    <x-page-header :title="$page->title" :subtitle="$page->subtitle" />

    @include('public.rooms._notice')

    @if ($rooms->isEmpty())
        <x-empty-state title="Belum ada kategori kamar" message="Informasi kamar rawat inap akan tampil di sini setelah ditambahkan." icon="bi-building" />
    @else
        <div class="row g-4 row-cols-1 row-cols-lg-2">
            @foreach ($rooms as $room)
                @php
                    $cover = $room->firstMedia('cover');
                @endphp
                <div class="col">
                    <article class="card h-100 room-card">
                        <x-image type="room" :media="$cover" :alt="$cover?->alt_text ?: $room->name" variant="thumb" ratio="4x3" />
                        <div class="card-body d-flex flex-column">
                            <h2 class="card-title h5 mb-1">
                                <a class="stretched-link" href="{{ route('rooms.show', $room) }}">{{ $room->name }}</a>
                            </h2>
                            @if (filled($room->short_description))
                                <p class="card-text mb-2">{{ $room->short_description }}</p>
                            @endif
                            <ul class="list-unstyled mb-3">
                                <li>Kapasitas: {{ $room->bed_capacity }} tempat tidur</li>
                                <li>{{ $room->occupancy_label }} @include('public.rooms._availability-badge', ['room' => $room])</li>
                                <li>Tarif contoh: {{ $room->sample_rate_label }}</li>
                                <li class="small text-body-secondary">{{ $room->availability_updated_label }}</li>
                            </ul>
                            @if ($room->is_availability_stale)
                                <p class="small text-warning-emphasis mb-3">Data mungkin tidak terkini. Hubungi rumah sakit untuk memastikan ketersediaan.</p>
                            @endif
                            <span class="btn btn-outline-primary mt-auto" aria-hidden="true">Lihat Detail</span>
                        </div>
                    </article>
                </div>
            @endforeach
        </div>
    @endif
</x-layouts.public>
```

`resources/views/public/rooms/show.blade.php`:

```blade
@inject('site', 'App\Services\Settings\SettingsService')
@php
    $cover = $room->firstMedia('cover');
    $coverAlt = $cover?->alt_text ?: $room->name;
    $amenities = collect($room->amenities ?? [])->filter(fn ($item): bool => is_string($item) && trim($item) !== '')->values();
    $whatsappUrl = $site->whatsappUrl();
    $contactUrl = Route::has('contact') ? route('contact') : null;
@endphp
<x-layouts.public :title="$room->seo_title" :meta-description="$metaDescription">
    <x-slot:breadcrumb>
        <x-breadcrumb :items="[['label' => 'Kamar Rawat Inap', 'url' => route('rooms.index')], ['label' => $room->name]]" />
    </x-slot:breadcrumb>

    @push('head')
        <x-open-graph :title="$room->seo_title" :description="$metaDescription" :image="$cover?->url ? url($cover->url) : null" :canonical="route('rooms.show', $room)" />
    @endpush

    <x-page-header :title="$room->name" :subtitle="$room->short_description" />

    @include('public.rooms._notice')

    <div class="row g-4 g-lg-5">
        <div class="col-lg-7">
            <x-image type="room" :media="$cover" :alt="$coverAlt" ratio="4x3" :lazy="false" />

            @if ($gallery->isNotEmpty())
                <section class="mt-4" aria-labelledby="galeri-judul">
                    <h2 id="galeri-judul" class="h4 mb-3">Galeri</h2>
                    <div class="gallery-grid">
                        @foreach ($gallery as $index => $photo)
                            <button type="button" class="gallery-thumb" data-bs-toggle="modal" data-bs-target="#galeri-modal" data-gallery-index="{{ $index }}" aria-label="Perbesar foto {{ $index + 1 }} dari {{ $gallery->count() }}: {{ $photo->alt_text }}">
                                <x-image type="room" :media="$photo" decorative variant="thumb" ratio="4x3" />
                            </button>
                        @endforeach
                    </div>
                </section>

                <div class="modal fade" id="galeri-modal" tabindex="-1" role="dialog" aria-labelledby="galeri-modal-judul" aria-hidden="true" data-gallery-modal>
                    <div class="modal-dialog modal-lg modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <div class="modal-title h5" id="galeri-modal-judul">Galeri {{ $room->name }}</div>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup galeri"></button>
                            </div>
                            <div class="modal-body p-0">
                                <div id="galeri-carousel" class="carousel slide" tabindex="-1" data-bs-ride="false" aria-label="Foto galeri">
                                    <div class="carousel-inner">
                                        @foreach ($gallery as $index => $photo)
                                            <div @class(['carousel-item', 'active' => $index === 0])>
                                                <x-image type="room" :media="$photo" :lazy="false" class="d-block w-100" />
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

            @if (filled($room->description))
                <div class="mt-4">
                    <div class="rich-text room-body">{!! $room->description !!}</div>
                </div>
            @endif

            @if ($amenities->isNotEmpty())
                <section class="mt-5" aria-labelledby="fasilitas-kamar-judul">
                    <h2 id="fasilitas-kamar-judul" class="h4 mb-3">Fasilitas Kamar</h2>
                    <ul class="amenity-list">
                        @foreach ($amenities as $amenity)
                            <li><i class="bi bi-check2" aria-hidden="true"></i> {{ $amenity }}</li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <p class="mt-5 mb-0">
                <a class="btn btn-outline-primary" href="{{ route('rooms.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Kembali ke daftar kamar</a>
            </p>
        </div>

        <aside class="col-lg-5" aria-label="Ringkasan kamar">
            <div class="card room-summary-panel">
                <div class="card-body">
                    <h2 class="h5 mb-3">Ringkasan</h2>
                    <dl class="mb-3">
                        <dt>Kapasitas</dt>
                        <dd>{{ $room->bed_capacity }} tempat tidur</dd>
                        <dt>Tersedia</dt>
                        <dd class="mb-0">{{ $room->occupancy_label }} @include('public.rooms._availability-badge', ['room' => $room])</dd>
                    </dl>

                    <p class="mb-1">Tarif contoh: {{ $room->sample_rate_label }}</p>
                    @if (filled($room->rate_note))
                        <p class="small text-body-secondary mb-2">{{ $room->rate_note }}</p>
                    @endif

                    <p class="small text-body-secondary mt-3 mb-2">
                        @if ($room->availability_updated_at !== null)
                            <time datetime="{{ $room->availability_updated_at->toIso8601String() }}">{{ $room->availability_updated_label }}</time>
                        @else
                            <span>{{ $room->availability_updated_label }}</span>
                        @endif
                    </p>
                    @if ($room->is_availability_stale)
                        <p class="small text-warning-emphasis mb-3">Data mungkin tidak terkini. Hubungi rumah sakit untuk memastikan ketersediaan.</p>
                    @endif

                    @if ($contactUrl !== null)
                        <a class="btn btn-primary w-100 mb-2" href="{{ $contactUrl }}">Tanya Ketersediaan</a>
                    @endif
                    @if ($whatsappUrl !== null)
                        <a class="btn btn-outline-primary w-100" href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer"><i class="bi bi-whatsapp" aria-hidden="true"></i> Chat via WhatsApp (membuka WhatsApp)</a>
                    @endif
                </div>
            </div>
        </aside>
    </div>
</x-layouts.public>
```

`resources/views/public/partials/_room-summary.blade.php`:

```blade
@if ($rooms->isNotEmpty())
    <section class="home-section" aria-labelledby="judul-kamar">
        <div class="home-section-header">
            <h2 id="judul-kamar" class="mb-0">Ketersediaan Kamar Rawat Inap</h2>
            @if (Route::has('rooms.index'))
                <a href="{{ route('rooms.index') }}">Lihat detail kamar</a>
            @endif
        </div>
        <p class="text-body-secondary">Diperbarui manual oleh petugas. Hubungi rumah sakit untuk memastikan ketersediaan.</p>
        <div class="row g-3 row-cols-1 row-cols-md-2 row-cols-xl-4">
            @foreach ($rooms as $room)
                <div class="col">
                    <article class="card h-100 room-card">
                        <div class="card-body">
                            <h3 class="card-title h5 mb-2">
                                <a class="stretched-link" href="{{ route('rooms.show', $room) }}">{{ $room->name }}</a>
                            </h3>
                            <p class="mb-1">{{ $room->occupancy_label }}</p>
                            <p class="mb-2">@include('public.rooms._availability-badge', ['room' => $room])</p>
                            <p class="small text-body-secondary mb-0">{{ $room->availability_updated_label }}</p>
                        </div>
                    </article>
                </div>
            @endforeach
        </div>
    </section>
@endif
```

Catatan penulisan: (1) `RawOutputScanTest` mencocokkan teks baris `{!! !!}` persis setelah `trim`, sehingga baris itu harus `<div class="rich-text room-body">{!! $room->description !!}</div>` tanpa kelas tambahan; jarak atas diberikan oleh `<div class="mt-4">` pembungkusnya; (2) `{!! !!}` hanya satu kali di seluruh view kamar; (3) `.home-section` dan `.home-section-header` adalah kelas beranda 4a yang sudah ada; (4) `_notice.blade.php` adalah partial kecil tambahan (bukan nama kerangka C) yang hanya dipakai `rooms/index` dan `rooms/show`.

- [ ] **Step 9: Build aset dan jalankan tes tugas, harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
npm run build
& $PHP artisan test --filter='PublicRoomsTest|PublicFacilitiesTest|PublicHomeTest|RawOutputScanTest|FrontendManifestTest|NavigationMenuTest|LayoutsTest|AdminPagesTest'
```
Expected: `npm run build` sukses; seluruh tes lulus. `PublicHomeTest` lama hijau: tes beranda kosong tidak melihat blok kamar, dan daftar view bertambah `_room-summary` yang bebas `{!!` dan atribut `on*`. `RawOutputScanTest` hijau dengan lima baris. `PublicFacilitiesTest` (Task 7) tetap hijau karena tautan "Lihat Kamar Rawat Inap" kini memakai route sungguhan.

- [ ] **Step 10: Pint**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint
& $PHP vendor\bin\pint --test
```
Expected: `--test` PASS (0 berkas bermasalah).

- [ ] **Step 11: Suite penuh (latar belakang)**

Suite ±11–14 menit melebihi batas 10 menit sebuah perintah tool. Jalankan sebagai tugas latar belakang (tool PowerShell dengan `run_in_background`), tunggu sampai selesai, lalu baca ringkasannya.

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
$LOG = Join-Path $env:TEMP "suite-fase4b-t08.txt"
& $PHP artisan test | Out-File -FilePath $LOG -Encoding utf8
Select-String -Path $LOG -Pattern 'Tests:|FAIL|Duration' | Select-Object -Last 20
```
Expected: seluruh tes PASS (±42 tes baru di atas angka Task 7).

- [ ] **Step 12: Commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git add app/Http/Controllers/Public/RoomCategoryController.php app/Http/Controllers/Public/HomeController.php resources/views/public/rooms/index.blade.php resources/views/public/rooms/show.blade.php resources/views/public/rooms/_availability-badge.blade.php resources/views/public/rooms/_notice.blade.php resources/views/public/partials/_room-summary.blade.php resources/views/public/home.blade.php resources/scss/_public.scss routes/web.php tests/Feature/Public/PublicRoomsTest.php tests/Feature/Public/PublicHomeTest.php tests/Feature/View/RawOutputScanTest.php
git commit -m @'
feat(fase-4b): halaman publik kamar rawat inap (#11-#15) dan blok ketersediaan kamar di beranda

Ringkasan dan detail kamar menampilkan kapasitas, ketersediaan dengan badge Tersedia/Penuh (teks, ikon, warna),
tarif contoh, dan label pembaruan manual oleh petugas; kalimat peringatan tidak memakai kata real-time dan kategori
yang pembaruannya lebih dari 24 jam atau belum pernah diperbarui diberi catatan data mungkin tidak terkini.
Kategori nonaktif dan slug tidak dikenal menjawab 404 untuk semua orang termasuk staf. Beranda menampilkan
maksimal empat kategori aktif antara blok dokter dan artikel (HomeController::FEATURED_ROOMS) dan menyembunyikan
blok bila tidak ada. Daftar baris {!! !!} yang dikunci RawOutputScanTest menjadi lima.

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
'@
```
Expected: commit tercatat.

---

### Task 9: Halaman publik #22 Kontak dan Lokasi (`x-map-embed`, `initMapEmbeds()`, form pesan)

**Files:**
- Create: `app/Rules/IndonesianPhone.php`
- Create: `app/Http/Requests/Public/StoreContactMessageRequest.php`
- Create: `app/Http/Controllers/Public/ContactController.php`
- Create: `app/View/Components/MapEmbed.php`, `resources/views/components/map-embed.blade.php`
- Create: `resources/views/public/contact.blade.php`
- Modify: `routes/web.php` (impor `ContactController`; route `contact` dan `contact.store` sesudah baris `/faq`)
- Modify: `resources/js/app.js` (fungsi `initMapEmbeds()` ditulis sebelum `initGalleryModals()` dan dipanggil dari `init()`)
- Modify: `resources/scss/_public.scss` (kelas `.map-embed`, `.map-embed-fallback`, keadaan `is-unavailable` dan `is-loaded`)
- Modify (tes lama): `tests/Feature/View/RawOutputScanTest.php` (filter komponen `map-embed`), `tests/Unit/FrontendManifestTest.php` (tes `initMapEmbeds` dan kelas peta), `tests/Feature/Public/PublicDoctorPagesTest.php` (tes skip "fase 4b" dihapus), `tests/Feature/Public/PublicHomeTest.php` (blok bantuan kini selalu tampil karena route `contact` ada)
- Test (baru): `tests/Unit/Rules/IndonesianPhoneTest.php`, `tests/Feature/View/MapEmbedComponentTest.php`, `tests/Feature/Public/PublicContactTest.php`, `tests/Feature/Public/ContactFormTest.php`
- Tidak diubah: `bootstrap/app.php` (penanganan 429 POST sudah ada, F1-R6/D-23), `AppServiceProvider` (limiter `contact` sudah ada: 3 kiriman per 10 menit per IP), `SecurityHeaders` (CSP `frame-src https://www.google.com https://maps.google.com` sudah ada), `config/navigation.php` (menu dan footer "Kontak" sudah ada dan tersaring `Route::has`), `ContactMessage` dan factory Task 2, `x-form.*`, `x-loading-button`, `x-flash-alert`, `x-form.errors-summary`, `resources/js/admin.js`, entri Vite.

**Interfaces:**
- Consumes:
  - Task 2: `App\Models\ContactMessage` (fillable `name`, `email`, `phone`, `subject`, `message`, `status`, `consent_at`, `ip_hash`; `ip_hash` hidden; `handled_by`/`handled_at` tidak fillable), enum `App\Enums\ContactMessageStatus` (`New`), `Tests\Support\InteractsWithFacilities` (tidak dipakai langsung di sini).
  - Fase 4a: `App\Models\Page::forKey('contact')` (judul "Kontak dan Lokasi", subjudul "Hubungi kami atau kunjungi kami", deskripsi "Alamat, telepon, WhatsApp, e-mail, peta, dan formulir kontak UNPAM HOSPITAL."; bawaan `Page::DEFAULTS`), `x-open-graph`, `InteractsWithContent::createPage`, `PublicHomeTest` (helper `section()`).
  - Fase 1-3: `App\Rules\PersonName`, `App\Services\Settings\SettingsService` (`string()`, `whatsappUrl()`; kunci `contact.address`, `contact.phone`, `contact.emergency_phone`, `contact.whatsapp`, `contact.email`, `contact.operational_hours`, `map.directions_url`, `map.embed_url`), `x-layouts.public`, `x-breadcrumb`, `x-page-header`, `x-form.input`, `x-form.textarea`, `x-form.errors-summary`, `x-loading-button`, `x-flash-alert` (dirender layout), limiter `contact`, penanganan 429/419 di `bootstrap/app.php:114-127` dan `:107-112`.
  - Task 7 dan 8: jangkar Edit `routes/web.php` dan `_public.scss` (teks baru Task 8), `RawOutputScanTest` (lima baris).
- Produces:
  - `App\Rules\IndonesianPhone` (`final`, `implements ValidationRule`; `public static function normalize(string $value): string`; konstanta `PATTERN` dan `MESSAGE`).
  - `App\Http\Requests\Public\StoreContactMessageRequest` (`authorize()`, `rules()` V-37, `messages()`, `attributes()`, `prepareForValidation()`, `isHoneypotFilled(): bool`, `messageAttributes(): array`).
  - `App\Http\Controllers\Public\ContactController` (`show(): View`, `store(StoreContactMessageRequest $request): RedirectResponse`).
  - `App\View\Components\MapEmbed` (`final`; props `int $height = 360`, `string $title = 'Peta lokasi UNPAM HOSPITAL'`) dan view `components.map-embed`; tag `<x-map-embed />`.
  - Route `contact` (`GET /kontak`) dan `contact.store` (`POST /kontak`, `throttle:contact`).
  - View `public.contact`.
  - JS `initMapEmbeds()` di `resources/js/app.js` dan kelas SCSS `.map-embed`, `.map-embed-fallback`.

**Test IDs:** F-PUB-15 dan F-PUB-16 (halaman #22 dan formulir pesan), CRD-12 (alamat, telepon, WhatsApp, email, peta dari pengaturan tampil di `/kontak`), SEK-06 (CSRF 419), SEK-07 (CSP `frame-src` tetap), SEK-08/SEK-15 (isi pesan hanya `{{ }}`), SEK-14 (throttle `contact`, `ip_hash` SHA-256 tanpa IP mentah), VAL-04 (pesan V-37 persis), OFF-04 (peta offline: alamat dan tombol Petunjuk Arah tetap ada), A11-05 (iframe `title`, fallback `role="status"`), EMP-01 (kontak kosong: baris disembunyikan, alamat tetap tampil), Review Focus 2 (penyalahgunaan form kontak), F4B-R6 sampai F4B-R8, F4B-R32 sampai F4B-R35.

**Catatan teknis (diverifikasi dari source):**
1. **Throttle (F4B-R6).** `AppServiceProvider.php:84` mendefinisikan `RateLimiter::for('contact', fn ($r) => Limit::perMinutes(10, 3)->by((string) $r->ip()))`: kiriman keempat dalam 10 menit diblokir. `bootstrap/app.php:114-127` mengubah `ThrottleRequestsException` pada POST menjadi redirect 302 ke `SafeRedirect::back()` (`url()->previous()`) dengan `withInput`, flash `error` "Terlalu banyak percobaan. Silakan coba lagi dalam {n} detik." dan header `Retry-After`. Tidak ada teks baru. Middleware throttle berjalan sebelum validasi, jadi kiriman yang gagal validasi ikut dihitung. Kunci limiter di `ThrottleRequests` adalah `md5('contact'.<ip>)` (pola `AdminLoginController.php:30`); tes membersihkannya di `setUp`.
2. **CSRF (SEK-06).** `ValidateCsrfToken` dilewati selama `env` bernilai `testing`; tes 419 mengubah `$this->app['env'] = 'local'` (pola `ExceptionRenderingTest.php:60`). Penanganan 419 di `bootstrap/app.php:107-112` mengarahkan kembali dengan flash `error` "Sesi formulir Anda telah berakhir. Silakan periksa isian Anda lalu kirim ulang." tanpa menyimpan baris.
3. **Honeypot (F4B-R7).** Field `website` berada di `<div class="d-none" aria-hidden="true">` (`type="text"`, `tabindex="-1"`, `autocomplete="off"`, label "Situs web") dan **tidak** ada di `rules()`. Bila terisi, controller mengalihkan ke `contact` dengan flash sukses yang sama tanpa menyimpan. Validasi FormRequest tetap berjalan lebih dulu, sehingga bot yang mengirim data tidak valid melihat galat biasa (tidak membuka informasi tambahan).
4. **`IndonesianPhone` (F4B-R34).** `normalize()` membuang spasi, tanda hubung, titik, dan kurung; nilai lolos bila cocok `/^(\+62|62|0)8[0-9]{7,12}$/`. Nomor tersimpan dalam bentuk ternormalisasi (maksimal 16 karakter: `+62` + `8` + 12 digit; muat di `VARCHAR(20)`). Aturan tidak dijalankan untuk nilai kosong (`nullable`). Pesan ditulis langsung di kelas (tanpa kunci `lang` baru).
5. **Aturan V-37.** Mengikuti 12 V-37 dengan dua catatan: `string` ditambahkan sebelum `email:rfc` agar `email[]=x` menjawab galat biasa (bukan 500), dan `max:255` diletakkan sebelum `email:rfc` agar urutan pesan deterministik untuk email yang sangat panjang. `bail` dipakai pada semua field teks. Nilai larik (`message[]=x`, `name[]=x`, `consent[]=1`) menghasilkan galat berbahasa Indonesia; komponen `x-form.input` dan `x-form.textarea` sudah menyaring nilai lama non-skalar, dan checkbox persetujuan memakai perbandingan ketat `old('consent') === '1'` tanpa konversi string.
6. **Simpan pesan (F4B-R35).** `ContactMessage::query()->create(...)` dengan `status = ContactMessageStatus::New`, `consent_at = now()`, `ip_hash = hash('sha256', $request->ip().config('app.key'))` (SHA-256 heksadesimal 64 karakter; IP mentah tidak pernah disimpan). `ContactMessage` tidak diobservasi audit (F4B-R25): pembuatan pesan oleh tamu tidak menghasilkan baris `audit_logs`. Isi pesan tersimpan apa adanya dan hanya dirender dengan `{{ }}`.
7. **`x-map-embed` (F4B-R33).** Komponen kelas `final` yang menerima `SettingsService` lewat konstruktor (Laravel menyelesaikan dependensi konstruktor otomatis; atribut tag hanya mengisi `height` dan `title`). Wadah `<div class="map-embed" data-map-embed style="--map-height: {h}px">`: atribut `style` hanya memuat variabel CSS dan diizinkan CSP `style-src 'unsafe-inline'` (`SecurityHeaders.php:18`). Kotak pengganti `<div class="map-embed-fallback" role="status" data-map-fallback>` memuat teks status, alamat, dan tombol "Petunjuk Arah"; iframe hanya dirender bila `map.embed_url` berawalan `https://` (pertahanan berlapis terhadap `javascript:`; tautan Petunjuk Arah wajib `http(s)://`). Tanpa JavaScript iframe tampil di atas kotak pengganti.
8. **`initMapEmbeds()` (F4B-R33).** Fungsi baru di `resources/js/app.js` dipanggil dari `init()` (`app.js:336-346`) dan ditulis sebelum `initGalleryModals()` (`app.js:309`) agar potongan kode yang dikunci `RawOutputScanTest` dan `FrontendManifestTest` tidak berubah. Perilaku: `navigator.onLine === false` menambah `is-unavailable` dan mengganti teks pengganti; selain itu listener **`load`** pada iframe menambah `is-loaded`, dan `setTimeout` 8000 ms menambah `is-unavailable` bila belum `is-loaded`. Karena iframe `loading="lazy"` baru dimuat mendekati viewport, pewaktu 8000 ms dimulai lewat `IntersectionObserver` (`rootMargin: '300px'`) saat wadah peta hampir terlihat, bukan saat halaman dimuat; bila `IntersectionObserver` tidak ada, pewaktu langsung dimulai. Cek offline tetap segera. **Tidak ada listener `error` baru** dan tidak ada `onerror` (`RawOutputScanTest.php:81-89`, `FrontendManifestTest.php:120-137`, `:204-217` mengunci satu delegasi gambar).
9. **CSS peta.** `.map-embed.is-unavailable iframe { display: none; }`; kotak pengganti berposisi absolut di belakang iframe; `.map-embed.is-loaded .map-embed-fallback { visibility: hidden; }` (tidak ikut urutan tab). Tinggi memakai `var(--map-height)` dan dibatasi 260 px di bawah `sm` (07 W-08).
10. **Susunan halaman (kerangka E).** ≥ lg dua kolom: kiri kartu "Alamat" (`<address>` selalu tampil, daftar kontak yang kosong disembunyikan, tombol "Petunjuk Arah" dan "Chat via WhatsApp (membuka WhatsApp)") lalu kartu "Peta"; kanan kartu "Kirim Pesan" dengan form. < lg satu kolom dalam urutan yang sama. Nilai `contact.*` dirender apa adanya (seperti footer layout), tanpa label "(placeholder)" tambahan.
11. **Tes lama yang patah karena tugas ini (F4B-R37, `digest-tests-carry.md` §4.3-§4.6).** (a) `PublicHomeTest.php:103`: `_help-cta.blade.php` menampilkan blok bantuan bila ada salah satu kontak atau route `contact`; setelah route ada, beranda kosong selalu memuat blok itu dengan satu tombol "Kirim Pesan", sehingga `assertDontSee('Butuh bantuan segera?')` diganti asersi isi blok. (b) `PublicDoctorPagesTest.php:324-337`: tes ter-skip dihapus (tes positif `test_an_empty_schedule_links_to_contact_when_the_route_exists` sudah ada). (c) `RawOutputScanTest.php:66-72`: filter komponen ditambah `components/map-embed`. Tes lain yang bergantung pada `Route::has('contact')` (`PublicFaqTest`, `PublicContentPagesTest`, `PublicHomeTest.php:446-461`, `AdminPagesTest`) bercabang dinamis dan tetap hijau.
12. **Bahasa.** Kata "(placeholder)" pada label pengaturan seed tidak dirender di halaman publik. Teks tombol WhatsApp persis "Chat via WhatsApp (membuka WhatsApp)" (07 W-08, A11).
13. Jumlah tes baru: ±115 (`PublicContactTest` ±22, `ContactFormTest` ±56 termasuk dataprovider, `MapEmbedComponentTest` ±12, `IndonesianPhoneTest` ±26, `FrontendManifestTest` +2); `FrontendManifestTest` memuat jarum baru `IntersectionObserver`/`startTimer`; dua tes lama diubah dan satu tes lama dihapus (`PublicDoctorPagesTest`).

- [ ] **Step 1: Verifikasi prasyarat (Task 1-8 selesai)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git branch --show-current
git status --short
git log --oneline -3
Test-Path app\Models\ContactMessage.php, app\Enums\ContactMessageStatus.php, app\Rules\PersonName.php, resources\views\public\rooms\show.blade.php
Test-Path app\Rules\IndonesianPhone.php, app\View\Components\MapEmbed.php, app\Http\Controllers\Public\ContactController.php, resources\views\public\contact.blade.php
Select-String -Path routes\web.php -Pattern "contact"
Select-String -Path resources\js\app.js -Pattern "initMapEmbeds"
& $PHP artisan test --filter='PublicRoomsTest|PublicHomeTest|RawOutputScanTest|FrontendManifestTest|PublicDoctorPagesTest|SecurityHeadersTest'
```
Expected: `fase-4b-fasilitas-kamar-kontak`; `git status` bersih (kecuali `.superpowers/`); commit Task 8 terlihat; `True` x4, lalu `False` x4; kedua `Select-String` tidak mencetak apa pun; keenam kelas tes PASS. Catatan: `PublicDoctorPagesTest::test_an_empty_schedule_shows_the_empty_state_without_a_contact_link_when_the_route_is_missing` saat ini berjalan karena route `contact` belum ada; begitu route dibuat, tes itu akan di-skip dan cabangnya mustahil, sehingga tugas ini menghapusnya (Step 2c).

- [ ] **Step 2: Tulis tes yang gagal**

**2a. Edit tes lama `tests/Feature/View/RawOutputScanTest.php` (tool Edit).** Filter komponen yang dipindai ditambah `components/map-embed`. `old_string` cocok tepat satu kali:

old_string:

```php
            || str_contains($this->relative($file), 'components/availability-badge')
            || str_contains($this->relative($file), 'components/open-graph'));
```

new_string:

```php
            || str_contains($this->relative($file), 'components/availability-badge')
            || str_contains($this->relative($file), 'components/map-embed')
            || str_contains($this->relative($file), 'components/open-graph'));
```

**2b. Edit tes lama `tests/Unit/FrontendManifestTest.php` (tool Edit).** Dua tes baru disisipkan sebelum `test_public_styles_define_the_home_page_classes`. `old_string` cocok tepat satu kali:

old_string:

```php
    public function test_public_styles_define_the_home_page_classes(): void
    {
```

new_string:

```php
    public function test_app_script_provides_the_map_embed_fallback_without_a_second_error_listener(): void
    {
        $app = $this->read('resources/js/app.js');

        foreach ([
            'function initMapEmbeds()',
            '[data-map-embed]',
            '[data-map-frame]',
            '[data-map-fallback-text]',
            'navigator.onLine === false',
            "addEventListener('load'",
            '8000',
            'IntersectionObserver',
            'startTimer',
            "classList.add('is-unavailable')",
            "classList.add('is-loaded')",
            'Peta tidak dapat dimuat. Alamat dan tombol Petunjuk Arah tetap dapat dipakai.',
        ] as $needle) {
            $this->assertStringContainsString($needle, $app, "app.js harus memuat {$needle}");
        }

        $init = substr($app, (int) strpos($app, 'function init()'));
        $this->assertStringContainsString('initMapEmbeds();', $init);
        $this->assertStringContainsString('initGalleryModals();', $init);

        // Ditulis sebelum initGalleryModals() agar potongan kode yang dikunci RawOutputScanTest tidak berubah.
        $this->assertLessThan(strpos($app, 'function initGalleryModals()'), strpos($app, 'function initMapEmbeds()'));

        $start = (int) strpos($app, 'function initMapEmbeds()');
        $body = substr($app, $start, (int) strpos($app, 'function initGalleryModals()') - $start);
        $this->assertStringNotContainsString("'error'", $body, 'initMapEmbeds() tidak boleh memasang listener error');

        $this->assertSame(1, preg_match_all("/addEventListener\\(\\s*'error'/", $app), 'Tidak boleh ada listener error kedua');
        $this->assertStringNotContainsString('onerror', strtolower($app));
    }

    public function test_public_styles_define_the_map_embed_classes(): void
    {
        $public = $this->read('resources/scss/_public.scss');

        foreach (['.map-embed', '.map-embed-fallback', '--map-height', '260px'] as $needle) {
            $this->assertStringContainsString($needle, $public, "_public.scss harus memuat {$needle}");
        }

        $this->assertMatchesRegularExpression('/\.map-embed\.is-unavailable\s+iframe\s*\{[^}]*display:\s*none;/s', $public);
        $this->assertMatchesRegularExpression('/\.map-embed\.is-loaded\s+\.map-embed-fallback\s*\{[^}]*visibility:\s*hidden;/s', $public);
    }

    public function test_public_styles_define_the_home_page_classes(): void
    {
```

**2c. Edit tes lama `tests/Feature/Public/PublicDoctorPagesTest.php` (tool Edit).** Tes yang di-skip bila route `contact` ada dihapus: cabang "tanpa route" tidak lagi bisa terjadi, dan cabang "dengan route" sudah dicakup `test_an_empty_schedule_links_to_contact_when_the_route_exists` (tidak diubah, tidak diduplikasi). `old_string` cocok tepat satu kali:

old_string:

```php
    public function test_an_empty_schedule_shows_the_empty_state_without_a_contact_link_when_the_route_is_missing(): void
    {
        if (Route::has('contact')) {
            $this->markTestSkipped('Route contact sudah ada (fase 4b): tautan diuji di tes fase tersebut.');
        }

        $doctor = $this->createDoctor();

        $this->get($this->url($doctor))
            ->assertOk()
            ->assertSee('Belum ada jadwal praktik dua minggu ke depan')
            ->assertDontSee('Hubungi Kami')
            ->assertDontSee('<table', false);
    }

    public function test_an_empty_schedule_links_to_contact_when_the_route_exists(): void
    {
```

new_string:

```php
    public function test_an_empty_schedule_links_to_contact_when_the_route_exists(): void
    {
```

Impor `Illuminate\Support\Facades\Route` di berkas itu tetap dipakai oleh `fakeRoute()` (baris 87-89), jadi tidak dihapus.

**2d. Edit tes lama `tests/Feature/Public/PublicHomeTest.php` (tool Edit).** Setelah route `contact` ada, `_help-cta.blade.php` selalu tampil (kondisi `$contactUrl !== null`). `old_string` cocok tepat satu kali:

old_string:

```php
            ->assertDontSee('Artikel Terbaru')
            ->assertDontSee('Butuh bantuan segera?')
            ->assertDontSee('Situs ini masih dalam tahap pembangunan.');

        $this->assertSame(1, substr_count($html, '<h1'));
```

new_string:

```php
            ->assertDontSee('Artikel Terbaru')
            ->assertDontSee('Situs ini masih dalam tahap pembangunan.');

        $this->assertSame(1, substr_count($html, '<h1'));

        // Route `contact` ada (fase 4b): blok bantuan tetap tampil dengan satu tombol "Kirim Pesan" dan tanpa kontak pengaturan.
        $cta = $this->section($html, 'judul-bantuan');
        $this->assertStringContainsString('</i> Kirim Pesan</a>', $cta);

        foreach (['wa.me', 'IGD:', 'Petunjuk Arah', 'Telepon</a>'] as $absent) {
            $this->assertStringNotContainsString($absent, $cta);
        }
```

**2e. Tes baru (tool Write), empat berkas.**

`tests/Unit/Rules/IndonesianPhoneTest.php`:

```php
<?php

namespace Tests\Unit\Rules;

use App\Rules\IndonesianPhone;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Nomor seluler Indonesia (F4B-R34, 12 Asumsi 1): spasi, tanda hubung, titik, dan kurung dibuang lebih dulu; hasilnya
 * harus cocok `^(\+62|62|0)8[0-9]{7,12}$`. Nilai kosong diserahkan ke `nullable`/`required`.
 */
class IndonesianPhoneTest extends TestCase
{
    /** @return array<string, array{0: string, 1: bool}> */
    public static function numbers(): array
    {
        return [
            'format nol dasar' => ['081234567890', true],
            'dengan tanda hubung' => ['0812-3456-7890', true],
            'format plus enam dua' => ['+62 812 3456 7890', true],
            'format enam dua tanpa plus' => ['6281234567890', true],
            'dengan kurung dan titik' => ['(0812) 3456.7890', true],
            'terpendek (tujuh digit sesudah 8)' => ['08' . '1234567', true],
            'satu digit lebih pendek ditolak' => ['08' . '123456', false],
            'terpanjang (dua belas digit sesudah 8)' => ['08' . '123456789012', true],
            'telepon rumah ditolak' => ['021555', false],
            'telepon rumah panjang ditolak' => ['02155512345', false],
            'terlalu pendek' => ['08123', false],
            'terlalu panjang' => ['08' . '1234567890123', false],
            'huruf ditolak' => ['0812abc4567', false],
            'kode negara lain ditolak' => ['+1 555 123 4567', false],
            'bukan seluler (tujuh)' => ['07123456789', false],
            'plus tanpa enam dua' => ['+812345678901', false],
            'tag ditolak' => ['<b>0812345678</b>', false],
        ];
    }

    #[DataProvider('numbers')]
    public function test_validation(string $value, bool $passes): void
    {
        $validator = Validator::make(['phone' => $value], ['phone' => [new IndonesianPhone]]);

        $this->assertSame($passes, $validator->passes(), $value);
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function normalized(): array
    {
        return [
            'tanda hubung' => ['0812-3456-7890', '081234567890'],
            'spasi dan plus' => ['+62 812 3456 7890', '+6281234567890'],
            'kurung dan titik' => ['(0812) 3456.7890', '081234567890'],
            'sudah bersih' => ['6281234567890', '6281234567890'],
            'banyak pemisah' => [' 0812 - 3456 . 7890 ', '081234567890'],
        ];
    }

    #[DataProvider('normalized')]
    public function test_normalize_strips_separators_only(string $input, string $expected): void
    {
        $this->assertSame($expected, IndonesianPhone::normalize($input));
    }

    public function test_the_longest_valid_number_fits_the_varchar_20_column(): void
    {
        $longest = IndonesianPhone::normalize('+62 8' . '123456789012');

        $this->assertSame(16, strlen($longest));
        $this->assertLessThanOrEqual(20, strlen($longest));
        $this->assertTrue(Validator::make(['phone' => $longest], ['phone' => [new IndonesianPhone]])->passes());
    }

    public function test_the_message_is_the_exact_indonesian_text(): void
    {
        $validator = Validator::make(['phone' => '021555'], ['phone' => [new IndonesianPhone]]);

        $this->assertSame(
            'Nomor telepon tidak valid. Gunakan nomor seluler Indonesia, mis. 081234567890.',
            $validator->errors()->first('phone'),
        );
        $this->assertSame($validator->errors()->first('phone'), IndonesianPhone::MESSAGE);
    }

    public function test_non_string_values_fail_without_an_error(): void
    {
        foreach ([['0812345678'], 8123456789, 8.5, true] as $value) {
            $validator = Validator::make(['phone' => $value], ['phone' => [new IndonesianPhone]]);

            $this->assertTrue($validator->fails(), var_export($value, true));
            $this->assertSame(IndonesianPhone::MESSAGE, $validator->errors()->first('phone'));
        }
    }

    public function test_empty_values_are_left_to_nullable_and_required(): void
    {
        $this->assertTrue(Validator::make(['phone' => ''], ['phone' => ['nullable', new IndonesianPhone]])->passes());
        $this->assertTrue(Validator::make(['phone' => null], ['phone' => ['nullable', new IndonesianPhone]])->passes());
        $this->assertTrue(Validator::make([], ['phone' => ['nullable', new IndonesianPhone]])->passes());
        $this->assertFalse(Validator::make(['phone' => ''], ['phone' => ['required', new IndonesianPhone]])->passes());
    }

    public function test_the_rule_is_a_final_strict_class(): void
    {
        $this->assertTrue((new \ReflectionClass(IndonesianPhone::class))->isFinal());
        $this->assertStringContainsString('declare(strict_types=1);', (string) file_get_contents(app_path('Rules/IndonesianPhone.php')));
    }
}
```

`tests/Feature/View/MapEmbedComponentTest.php`:

```php
<?php

namespace Tests\Feature\View;

use App\Services\Settings\SettingsService;
use App\View\Components\MapEmbed;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * `x-map-embed` (F4B-R33, 10 §3.4, OFF-04, A11-05): alamat dan tombol Petunjuk Arah selalu ada; iframe hanya bila
 * `map.embed_url` berupa URL https; kotak pengganti berperan `status`; tanpa atribut event inline.
 */
class MapEmbedComponentTest extends TestCase
{
    use RefreshDatabase;

    private const EMBED = 'https://www.google.com/maps?q=Universitas+Pamulang+Kampus+2+Viktor&output=embed';

    private const DIRECTIONS = 'https://www.google.com/maps/search/?api=1&query=Universitas+Pamulang+Kampus+2+Viktor';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);
    }

    private function render(string $tag = '<x-map-embed />'): string
    {
        return Blade::render($tag);
    }

    /** @param  array<string, ?string>  $values */
    private function configure(array $values): void
    {
        app(SettingsService::class)->setMany($values);
    }

    public function test_it_renders_the_container_fallback_and_iframe_from_the_settings(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('<div class="map-embed" data-map-embed style="--map-height: 360px">', $html);
        $this->assertStringContainsString('<div class="map-embed-fallback" role="status" data-map-fallback>', $html);
        $this->assertStringContainsString('Memuat peta…', $html);
        $this->assertStringContainsString('<iframe src="'.e(self::EMBED).'" title="Peta lokasi UNPAM HOSPITAL" loading="lazy" referrerpolicy="no-referrer-when-downgrade" data-map-frame></iframe>', $html);
        $this->assertSame(1, substr_count($html, '<iframe'));
    }

    public function test_the_address_is_always_rendered_in_the_fallback(): void
    {
        $this->assertStringContainsString('Universitas Pamulang Kampus 2 Viktor, Jl. Raya Puspitek', $this->render());

        $this->configure(['map.embed_url' => null]);

        $html = $this->render();

        $this->assertStringContainsString('Universitas Pamulang Kampus 2 Viktor, Jl. Raya Puspitek', $html);
        $this->assertStringContainsString('Peta tidak tersedia.', $html);
        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringNotContainsString('data-map-frame', $html);
        $this->assertStringNotContainsString('Memuat peta…', $html);
    }

    public function test_the_directions_button_opens_in_a_new_tab_safely(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('href="'.e(self::DIRECTIONS).'" target="_blank" rel="noopener noreferrer"', $html);
        $this->assertStringContainsString('<i class="bi bi-geo-alt" aria-hidden="true"></i> Petunjuk Arah</a>', $html);

        $this->configure(['map.embed_url' => null]);

        $this->assertStringContainsString('Petunjuk Arah</a>', $this->render());
    }

    public function test_height_and_title_are_props(): void
    {
        $html = $this->render('<x-map-embed :height="300" title="Peta Uji Coba" />');

        $this->assertStringContainsString('style="--map-height: 300px"', $html);
        $this->assertStringContainsString('title="Peta Uji Coba"', $html);
        $this->assertStringNotContainsString('Peta lokasi UNPAM HOSPITAL', $html);
    }

    public function test_a_numeric_string_height_is_coerced_to_an_integer(): void
    {
        $html = $this->render('<x-map-embed height="250" />');

        $this->assertStringContainsString('style="--map-height: 250px"', $html);
    }

    public function test_a_missing_directions_url_hides_the_button_but_keeps_the_address(): void
    {
        $this->configure(['map.directions_url' => null]);

        $html = $this->render();

        $this->assertStringNotContainsString('Petunjuk Arah', $html);
        $this->assertStringContainsString('Jl. Raya Puspitek', $html);
        $this->assertSame(1, substr_count($html, '<iframe'));
    }

    public function test_with_no_address_and_no_urls_only_the_status_text_remains(): void
    {
        $this->configure(['contact.address' => null, 'map.directions_url' => null, 'map.embed_url' => null]);

        $html = $this->render();

        $this->assertStringContainsString('Peta tidak tersedia.', $html);
        $this->assertStringNotContainsString('<address', $html);
        $this->assertStringNotContainsString('<a ', $html);
        $this->assertStringNotContainsString('<iframe', $html);
    }

    public function test_unsafe_urls_are_never_rendered(): void
    {
        $this->configure(['map.embed_url' => 'javascript:alert(1)', 'map.directions_url' => 'javascript:alert(2)']);

        $html = $this->render();

        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringNotContainsString('Petunjuk Arah', $html);

        $this->configure(['map.embed_url' => 'http://www.google.com/maps?output=embed', 'map.directions_url' => 'ftp://contoh.test/arah']);

        $html = $this->render();

        $this->assertStringNotContainsString('<iframe', $html, 'Hanya https yang boleh disematkan.');
        $this->assertStringNotContainsString('ftp://', $html);
    }

    public function test_the_address_and_title_are_escaped(): void
    {
        $this->configure(['contact.address' => '<b>Jl. Uji</b> & Co']);

        $html = Blade::render('<x-map-embed :title="$judul" />', ['judul' => 'Peta "uji" <i>x</i>']);

        $this->assertStringContainsString('&lt;b&gt;Jl. Uji&lt;/b&gt; &amp; Co', $html);
        $this->assertStringContainsString('title="Peta &quot;uji&quot; &lt;i&gt;x&lt;/i&gt;"', $html);
        $this->assertStringNotContainsString('<b>Jl. Uji</b>', $html);
        $this->assertStringNotContainsString('<i>x</i>', $html);
    }

    public function test_the_fallback_is_a_single_status_region_and_the_iframe_has_a_title(): void
    {
        $html = $this->render();

        $this->assertSame(1, substr_count($html, 'role="status"'));
        $this->assertSame(1, preg_match('/<iframe[^>]*\stitle="[^"]+"/', $html));
        $this->assertStringContainsString('data-map-fallback-text', $html);
    }

    public function test_the_markup_has_no_inline_event_handlers_or_scripts(): void
    {
        $html = $this->render();

        $this->assertSame(0, preg_match('/\son[a-z]+\s*=/i', $html));
        $this->assertStringNotContainsString('<script', $html);

        $source = (string) file_get_contents(resource_path('views/components/map-embed.blade.php'));
        $this->assertStringNotContainsString('onerror', strtolower($source));
        $this->assertStringNotContainsString('{!!', $source);
    }

    public function test_the_component_is_a_final_strict_class(): void
    {
        $this->assertTrue((new \ReflectionClass(MapEmbed::class))->isFinal());
        $this->assertStringContainsString('declare(strict_types=1);', (string) file_get_contents(app_path('View/Components/MapEmbed.php')));
    }
}
```

`tests/Feature/Public/PublicContactTest.php`:

```php
<?php

namespace Tests\Feature\Public;

use App\Models\User;
use App\Services\Settings\SettingsService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithContent;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * Halaman #22 `contact` (05 hal. 22, 07 W-08, F4B-R32): alamat selalu tampil; kontak dari pengaturan dirender apa
 * adanya dan baris kosong disembunyikan; tombol Petunjuk Arah dan WhatsApp; peta `x-map-embed`; form dengan honeypot,
 * catatan privasi, dan persetujuan. Mencakup CRD-12, EMP-01, OFF-04, dan A11-05.
 */
class PublicContactTest extends TestCase
{
    use InteractsWithContent;
    use InteractsWithStaff;
    use RefreshDatabase;

    private function section(string $html, string $labelledBy): string
    {
        $found = preg_match('#<section[^>]*aria-labelledby="'.preg_quote($labelledBy, '#').'".*?</section>#s', $html, $matches);

        $this->assertSame(1, $found, "Bagian {$labelledBy} tidak ditemukan.");

        return $matches[0];
    }

    private function seedSettings(): void
    {
        $this->seed(SettingsSeeder::class);
    }

    private function noindexTag(): string
    {
        return '<meta name="robots" content="noindex,nofollow">';
    }

    // --- header, SEO, breadcrumb -------------------------------------------------------------------------

    public function test_it_renders_the_default_header_seo_and_breadcrumb(): void
    {
        $response = $this->get(route('contact'))->assertOk();

        $response->assertSee('<title>Kontak dan Lokasi — '.config('app.name').'</title>', false)
            ->assertSee('<h1 class="mb-1">Kontak dan Lokasi</h1>', false)
            ->assertSee('Hubungi kami atau kunjungi kami')
            ->assertSee('<meta name="description" content="Alamat, telepon, WhatsApp, e-mail, peta, dan formulir kontak UNPAM HOSPITAL.">', false)
            ->assertSee('<meta property="og:title" content="Kontak dan Lokasi">', false)
            ->assertSee('<link rel="canonical" href="'.route('contact').'">', false)
            ->assertSeeInOrder(['aria-label="Breadcrumb"', 'Beranda', 'Kontak dan Lokasi'], false)
            ->assertSee('<li class="breadcrumb-item active" aria-current="page">Kontak dan Lokasi</li>', false)
            ->assertDontSee($this->noindexTag(), false);
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public function test_the_header_and_seo_follow_the_contact_page_row(): void
    {
        $this->createPage('contact', [
            'title' => 'Hubungi Kami',
            'subtitle' => 'Kami siap membantu',
            'meta_title' => 'Hubungi Kami Meta',
            'meta_description' => 'Deskripsi hubungi kami.',
        ]);

        $this->get(route('contact'))
            ->assertOk()
            ->assertSee('<title>Hubungi Kami Meta — '.config('app.name').'</title>', false)
            ->assertSee('<h1 class="mb-1">Hubungi Kami</h1>', false)
            ->assertSee('Kami siap membantu')
            ->assertSee('<meta name="description" content="Deskripsi hubungi kami.">', false);
    }

    // --- alamat dan kontak (CRD-12) ----------------------------------------------------------------------------

    public function test_the_address_card_shows_the_settings_as_they_are(): void
    {
        $this->seedSettings();

        $card = $this->section($this->get(route('contact'))->assertOk()->getContent(), 'judul-alamat');

        $this->assertStringContainsString('<h2 id="judul-alamat" class="h5 mb-3">Alamat</h2>', $card);
        $this->assertStringContainsString('<address class="mb-3">Universitas Pamulang Kampus 2 Viktor, Jl. Raya Puspitek, Buaran, Kecamatan Pamulang, Kota Tangerang Selatan, Banten 15310</address>', $card);
        $this->assertStringContainsString('<li>Telepon: <a href="tel:02100000000">(021) 0000-0000</a></li>', $card);
        $this->assertStringContainsString('<li>IGD: <a href="tel:02100000119">(021) 0000-0119</a></li>', $card);
        $this->assertStringContainsString('<li>WhatsApp: 6280000000000</li>', $card);
        $this->assertStringContainsString('<li>Email: <a href="mailto:info@unpamhospital.test">info@unpamhospital.test</a></li>', $card);
        $this->assertStringContainsString('<li>Jam operasional: Senin–Sabtu 08.00–20.00 (contoh)</li>', $card);
        $this->assertStringNotContainsString('(placeholder)', $card);
    }

    public function test_changing_the_settings_changes_the_page_and_the_footer(): void
    {
        $this->seedSettings();
        app(SettingsService::class)->setMany([
            'contact.address' => 'Jl. Baru No. 1, Pamulang',
            'contact.phone' => '(021) 5555-1234',
            'contact.email' => 'baru@contoh.test',
            'contact.operational_hours' => 'Setiap hari 24 jam',
        ]);

        $html = (string) $this->get(route('contact'))->assertOk()->getContent();
        $card = $this->section($html, 'judul-alamat');

        $this->assertStringContainsString('<address class="mb-3">Jl. Baru No. 1, Pamulang</address>', $card);
        $this->assertStringContainsString('<a href="tel:02155551234">(021) 5555-1234</a>', $card);
        $this->assertStringContainsString('mailto:baru@contoh.test', $card);
        $this->assertStringContainsString('Setiap hari 24 jam', $card);
        $this->assertStringNotContainsString('Pamulang Kampus 2 Viktor, Jl. Raya', $card);
        $this->assertStringContainsString('<address class="mb-0">Jl. Baru No. 1, Pamulang</address>', $html);
    }

    public function test_empty_settings_hide_their_rows_but_the_address_stays(): void
    {
        $response = $this->get(route('contact'))->assertOk();
        $card = $this->section($response->getContent(), 'judul-alamat');

        $this->assertStringContainsString('<address class="mb-3">Alamat belum diisi.</address>', $card);

        foreach (['Telepon:', 'IGD:', 'WhatsApp:', 'Email:', 'Jam operasional:', 'Petunjuk Arah', 'Chat via WhatsApp', '<ul'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $card);
        }

        $response->assertSee('Peta tidak tersedia.')
            ->assertDontSee('<iframe', false);
    }

    public function test_a_number_without_digits_renders_no_empty_tel_link(): void
    {
        $this->seedSettings();
        app(SettingsService::class)->setMany(['contact.phone' => '-', 'contact.emergency_phone' => '-', 'contact.whatsapp' => '-']);

        $card = $this->section($this->get(route('contact'))->assertOk()->getContent(), 'judul-alamat');

        $this->assertStringNotContainsString('href="tel:"', $card);
        $this->assertStringContainsString('<li>Telepon: -</li>', $card);
        $this->assertStringNotContainsString('WhatsApp:', $card);
        $this->assertStringNotContainsString('Chat via WhatsApp', $card);
    }

    public function test_the_directions_and_whatsapp_buttons_open_in_a_new_tab_safely(): void
    {
        $this->seedSettings();

        $card = $this->section($this->get(route('contact'))->assertOk()->getContent(), 'judul-alamat');
        $directions = e((string) app(SettingsService::class)->string('map.directions_url'));

        $this->assertStringContainsString('<a class="btn btn-primary" href="'.$directions.'" target="_blank" rel="noopener noreferrer"><i class="bi bi-geo-alt" aria-hidden="true"></i> Petunjuk Arah</a>', $card);
        $this->assertStringContainsString('<a class="btn btn-outline-primary" href="https://wa.me/6280000000000" target="_blank" rel="noopener noreferrer"><i class="bi bi-whatsapp" aria-hidden="true"></i> Chat via WhatsApp (membuka WhatsApp)</a>', $card);
    }

    public function test_an_unsafe_directions_url_is_not_rendered(): void
    {
        $this->seedSettings();
        app(SettingsService::class)->setMany(['map.directions_url' => 'javascript:alert(1)']);

        $html = (string) $this->get(route('contact'))->assertOk()->getContent();

        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('<i class="bi bi-geo-alt" aria-hidden="true"></i> Petunjuk Arah</a>', $this->section($html, 'judul-alamat'));
    }

    // --- peta ---------------------------------------------------------------------------------------------------------

    public function test_the_map_card_embeds_the_map_with_its_fallback(): void
    {
        $this->seedSettings();

        $card = $this->section($this->get(route('contact'))->assertOk()->getContent(), 'judul-peta');

        $this->assertStringContainsString('<h2 id="judul-peta" class="h5 mb-3">Peta</h2>', $card);
        $this->assertStringContainsString('data-map-embed', $card);
        $this->assertStringContainsString('data-map-fallback', $card);
        $this->assertStringContainsString('role="status"', $card);
        $this->assertStringContainsString('Memuat peta…', $card);
        $this->assertStringContainsString('title="Peta lokasi UNPAM HOSPITAL"', $card);
        $this->assertStringContainsString('loading="lazy"', $card);
        $this->assertStringContainsString('output=embed', $card);
    }

    // --- formulir -------------------------------------------------------------------------------------------------------

    public function test_the_form_has_the_labelled_fields_with_the_right_attributes(): void
    {
        $response = $this->get(route('contact'))->assertOk();
        $form = $this->section($response->getContent(), 'judul-form-kontak');

        $this->assertStringContainsString('<h2 id="judul-form-kontak" class="h5 mb-3">Kirim Pesan</h2>', $form);
        $this->assertStringContainsString('method="POST" action="'.route('contact.store').'"', $form);
        $this->assertStringContainsString('name="_token"', $form);
        $this->assertStringContainsString('<input type="text" id="name" name="name"', $form);
        $this->assertStringContainsString('autocomplete="name"', $form);
        $this->assertStringContainsString('<input type="email" id="email" name="email"', $form);
        $this->assertStringContainsString('autocomplete="email"', $form);
        $this->assertStringContainsString('<input type="tel" id="phone" name="phone"', $form);
        $this->assertStringContainsString('inputmode="tel"', $form);
        $this->assertStringContainsString('<input type="text" id="subject" name="subject"', $form);
        $this->assertStringContainsString('<textarea id="message" name="message"', $form);
        $this->assertStringContainsString('maxlength="2000"', $form);

        foreach (['Nama', 'Email', 'Telepon (opsional)', 'Subjek', 'Pesan'] as $label) {
            $this->assertStringContainsString('class="form-label fw-semibold">'.$label, $form);
        }

        $this->assertStringContainsString('Kolom bertanda', $form);
        $this->assertStringNotContainsString('data-errors-summary', $form);
    }

    public function test_the_message_field_carries_the_privacy_note(): void
    {
        $form = $this->section($this->get(route('contact'))->assertOk()->getContent(), 'judul-form-kontak');

        $this->assertStringContainsString('Jangan menuliskan data medis atau nomor identitas pada pesan ini.', $form);
    }

    public function test_the_honeypot_is_hidden_from_people_and_assistive_technology(): void
    {
        $form = $this->section($this->get(route('contact'))->assertOk()->getContent(), 'judul-form-kontak');

        $this->assertStringContainsString('<div class="d-none" aria-hidden="true">', $form);
        $this->assertStringContainsString('<label for="website">Situs web</label>', $form);
        $this->assertStringContainsString('<input type="text" id="website" name="website" tabindex="-1" autocomplete="off">', $form);
    }

    public function test_the_consent_checkbox_links_to_the_privacy_policy(): void
    {
        $form = $this->section($this->get(route('contact'))->assertOk()->getContent(), 'judul-form-kontak');

        $this->assertStringContainsString('<input type="checkbox" id="consent" name="consent" value="1"', $form);
        $this->assertStringContainsString('Saya menyetujui data saya diproses sesuai <a href="'.route('privacy').'">Kebijakan Privasi</a>.', $form);
        $this->assertStringContainsString('aria-required="true"', $form);
    }

    public function test_the_submit_button_is_a_loading_button(): void
    {
        $form = $this->section($this->get(route('contact'))->assertOk()->getContent(), 'judul-form-kontak');

        $this->assertStringContainsString('data-loading-button', $form);
        $this->assertStringContainsString('data-loading-text="Mengirim…"', $form);
        $this->assertStringContainsString('<span data-loading-label>Kirim Pesan</span>', $form);
    }

    public function test_the_sections_follow_the_documented_order_and_layout(): void
    {
        $html = (string) $this->get(route('contact'))->assertOk()->getContent();

        $this->assertSeeInOrderIn($html, ['id="judul-alamat"', 'id="judul-peta"', 'id="judul-form-kontak"']);
        $this->assertSame(2, substr_count($html, 'class="col-lg-6"'));
        $this->assertSame(3, preg_match_all('/class="card(?:\s[^"]*)?"/', $html), 'Tiga kartu: alamat, peta, dan form.');
    }

    public function test_the_success_flash_is_shown_on_the_page(): void
    {
        $this->withSession(['success' => 'Terima kasih. Pesan Anda telah kami terima dan akan ditindaklanjuti oleh petugas kami.'])
            ->get(route('contact'))
            ->assertOk()
            ->assertSee('data-flash="success"', false)
            ->assertSee('Terima kasih. Pesan Anda telah kami terima dan akan ditindaklanjuti oleh petugas kami.');
    }

    // --- akses dan keamanan markup -------------------------------------------------------------------------------------------

    public function test_the_page_is_identical_for_guests_patients_and_staff(): void
    {
        $this->seedSettings();

        foreach ([null, 'patient', 'content-admin', 'registration-officer', 'super-admin'] as $actor) {
            if ($actor === 'patient') {
                $this->actingAs(User::factory()->patient()->create());
            } elseif ($actor !== null) {
                $this->actingAs($this->createStaff($actor));
            }

            $this->get(route('contact'))->assertOk()->assertSee('Kirim Pesan')->assertSee('id="judul-alamat"', false);
        }
    }

    public function test_the_page_has_no_inline_handlers_and_no_raw_output(): void
    {
        $this->seedSettings();

        $html = (string) $this->get(route('contact'))->assertOk()->getContent();

        $this->assertSame(0, preg_match('/\son[a-z]+\s*=/i', $html));
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertSame(0, preg_match('/<a\b[^>]*>(?:(?!<\/a>).)*<a\b/s', $html));

        $source = (string) file_get_contents(resource_path('views/public/contact.blade.php'));
        $this->assertStringNotContainsString('{!!', $source);
        $this->assertSame(0, preg_match('/\son[a-z]{3,}\s*=\s*["\']/i', $source));
    }

    public function test_settings_values_are_escaped(): void
    {
        $this->seedSettings();
        app(SettingsService::class)->setMany(['contact.address' => '<script>alert(1)</script> Jl. Uji', 'contact.operational_hours' => '<b>24 jam</b>']);

        $this->get(route('contact'))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt; Jl. Uji', false)
            ->assertSee('&lt;b&gt;24 jam&lt;/b&gt;', false)
            ->assertDontSee('<script>alert(1)', false);
    }

    /** @param  list<string>  $needles */
    private function assertSeeInOrderIn(string $html, array $needles): void
    {
        $offset = -1;

        foreach ($needles as $needle) {
            $position = strpos($html, $needle);

            $this->assertNotFalse($position, "{$needle} tidak ditemukan.");
            $this->assertGreaterThan($offset, $position, "{$needle} berada di urutan yang salah.");
            $offset = $position;
        }
    }
}
```

`tests/Feature/Public/ContactFormTest.php`:

```php
<?php

namespace Tests\Feature\Public;

use App\Enums\ContactMessageStatus;
use App\Models\ContactMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * `POST /kontak` `contact.store` (06 §17, F4B-R6 s.d. F4B-R8, F4B-R34, F4B-R35, Review Focus 2): validasi V-37 dengan
 * pesan persis, penyimpanan tanpa IP mentah, throttle kiriman keempat, honeypot, persetujuan, normalisasi telepon,
 * nilai larik tanpa 500, dan CSRF. Waktu dibekukan pada Senin 5 Oktober 2026 pukul 10.00 WIB.
 */
class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    private const SUCCESS = 'Terima kasih. Pesan Anda telah kami terima dan akan ditindaklanjuti oleh petugas kami.';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-05 10:00:00');
        // Kunci ThrottleRequests = md5(nama limiter . kunci Limit) (lihat AdminLoginController).
        RateLimiter::clear(md5('contact'.'127.0.0.1'));
        RateLimiter::clear(md5('contact'.'203.0.113.9'));
    }

    /**
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    private function valid(array $override = []): array
    {
        return array_merge([
            'name' => 'Andi Pratama',
            'email' => 'andi@contoh.test',
            'phone' => '081234567890',
            'subject' => 'Pertanyaan jadwal dokter',
            'message' => 'Mohon info jadwal praktik dokter anak minggu depan.',
            'consent' => '1',
        ], $override);
    }

    /** @param  array<string, mixed>  $data */
    private function submit(array $data): TestResponse
    {
        return $this->from(route('contact'))->post(route('contact.store'), $data);
    }

    // --- sukses dan penyimpanan -----------------------------------------------------------------------------------

    public function test_a_valid_submission_is_saved_and_redirects_with_the_exact_flash(): void
    {
        $this->submit($this->valid())
            ->assertRedirect(route('contact'))
            ->assertSessionHas('success', self::SUCCESS)
            ->assertSessionHasNoErrors();

        $this->assertSame(1, ContactMessage::query()->count());

        $message = ContactMessage::query()->firstOrFail();

        $this->assertSame('Andi Pratama', $message->name);
        $this->assertSame('andi@contoh.test', $message->email);
        $this->assertSame('081234567890', $message->phone);
        $this->assertSame('Pertanyaan jadwal dokter', $message->subject);
        $this->assertSame('Mohon info jadwal praktik dokter anak minggu depan.', $message->message);
        $this->assertSame(ContactMessageStatus::New, $message->status);
        $this->assertTrue($message->consent_at->equalTo(now()));
        $this->assertNull($message->getAttribute('handled_by'));
        $this->assertNull($message->getAttribute('handled_at'));
    }

    public function test_the_flash_is_shown_after_following_the_redirect(): void
    {
        $this->followingRedirects()
            ->from(route('contact'))
            ->post(route('contact.store'), $this->valid())
            ->assertOk()
            ->assertSee('data-flash="success"', false)
            ->assertSee(self::SUCCESS);
    }

    public function test_text_fields_are_trimmed_and_the_phone_is_optional(): void
    {
        $this->submit($this->valid(['name' => '  Siti Nur’aini  ', 'phone' => '', 'subject' => '  Subjek dengan spasi  ']))
            ->assertRedirect(route('contact'))
            ->assertSessionHas('success');

        $message = ContactMessage::query()->firstOrFail();

        $this->assertSame('Siti Nur’aini', $message->name);
        $this->assertSame('Subjek dengan spasi', $message->subject);
        $this->assertNull($message->phone);
    }

    // --- ip_hash (SEK-14) -------------------------------------------------------------------------------------------

    public function test_only_a_sha256_hash_of_the_ip_and_app_key_is_stored(): void
    {
        $this->submit($this->valid())->assertSessionHas('success');

        $row = DB::table('contact_messages')->first();

        $this->assertSame(hash('sha256', '127.0.0.1'.config('app.key')), $row->ip_hash);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $row->ip_hash);
        $this->assertStringNotContainsString('127.0.0.1', json_encode($row, JSON_THROW_ON_ERROR));
    }

    public function test_a_different_ip_produces_a_different_hash(): void
    {
        $this->submit($this->valid());
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->submit($this->valid(['subject' => 'Kiriman dari alamat lain']));

        $hashes = DB::table('contact_messages')->orderBy('id')->pluck('ip_hash')->all();

        $this->assertCount(2, $hashes);
        $this->assertNotSame($hashes[0], $hashes[1]);
        $this->assertSame(hash('sha256', '203.0.113.9'.config('app.key')), $hashes[1]);
    }

    // --- validasi V-37 (VAL-04) -------------------------------------------------------------------------------------

    /** @return array<string, array{0: string, 1: array<string, mixed>, 2: string}> */
    public static function invalidInput(): array
    {
        return [
            'nama kosong' => ['name', ['name' => ''], 'Nama wajib diisi.'],
            'nama terlalu pendek' => ['name', ['name' => 'Ab'], 'Nama minimal 3 karakter.'],
            'nama terlalu panjang' => ['name', ['name' => str_repeat('a', 101)], 'Nama maksimal 100 karakter.'],
            'nama berangka' => ['name', ['name' => 'Andi 2'], 'Nama hanya boleh berisi huruf, spasi, titik, apostrof, dan tanda hubung.'],
            'nama bertag' => ['name', ['name' => '<script>alert(1)</script>'], 'Nama hanya boleh berisi huruf, spasi, titik, apostrof, dan tanda hubung.'],
            'nama larik' => ['name', ['name' => ['x']], 'Nama harus berupa teks.'],
            'email kosong' => ['email', ['email' => ''], 'Email wajib diisi.'],
            'email tidak valid' => ['email', ['email' => 'bukan-email'], 'Format email tidak valid.'],
            'email tanpa domain' => ['email', ['email' => 'andi@'], 'Format email tidak valid.'],
            'email terlalu panjang' => ['email', ['email' => str_repeat('a', 250).'@x.test'], 'Email maksimal 255 karakter.'],
            'email larik' => ['email', ['email' => ['a@b.test']], 'Format email tidak valid.'],
            'telepon rumah' => ['phone', ['phone' => '021555'], 'Nomor telepon tidak valid. Gunakan nomor seluler Indonesia, mis. 081234567890.'],
            'telepon huruf' => ['phone', ['phone' => '0812abc4567'], 'Nomor telepon tidak valid. Gunakan nomor seluler Indonesia, mis. 081234567890.'],
            'telepon terlalu panjang' => ['phone', ['phone' => '08'.'1234567890123'], 'Nomor telepon tidak valid. Gunakan nomor seluler Indonesia, mis. 081234567890.'],
            'telepon larik' => ['phone', ['phone' => ['081234567890']], 'Nomor telepon tidak valid. Gunakan nomor seluler Indonesia, mis. 081234567890.'],
            'subjek kosong' => ['subject', ['subject' => ''], 'Subjek wajib diisi.'],
            'subjek terlalu pendek' => ['subject', ['subject' => 'abcd'], 'Subjek minimal 5 karakter.'],
            'subjek terlalu panjang' => ['subject', ['subject' => str_repeat('a', 151)], 'Subjek maksimal 150 karakter.'],
            'subjek larik' => ['subject', ['subject' => ['x']], 'Subjek harus berupa teks.'],
            'pesan kosong' => ['message', ['message' => ''], 'Pesan wajib diisi.'],
            'pesan terlalu pendek' => ['message', ['message' => 'pendek'], 'Pesan minimal 10 karakter.'],
            'pesan terlalu panjang' => ['message', ['message' => str_repeat('a', 2001)], 'Pesan maksimal 2000 karakter.'],
            'pesan larik' => ['message', ['message' => ['x']], 'Pesan harus berupa teks.'],
            'persetujuan tidak dikirim' => ['consent', ['consent' => null], 'Anda harus menyetujui pengolahan data untuk mengirim pesan.'],
            'persetujuan nol' => ['consent', ['consent' => '0'], 'Anda harus menyetujui pengolahan data untuk mengirim pesan.'],
            'persetujuan larik' => ['consent', ['consent' => ['1']], 'Anda harus menyetujui pengolahan data untuk mengirim pesan.'],
        ];
    }

    /**
     * @param  array<string, mixed>  $override
     */
    #[DataProvider('invalidInput')]
    public function test_invalid_input_is_rejected_with_the_exact_message_and_nothing_is_saved(string $field, array $override, string $message): void
    {
        $this->submit($this->valid($override))
            ->assertRedirect(route('contact'))
            ->assertSessionHasErrors([$field => $message])
            ->assertSessionMissing('success');

        $this->assertSame(0, ContactMessage::query()->count());
    }

    public function test_boundary_lengths_are_accepted(): void
    {
        $this->submit($this->valid([
            'name' => 'Abc',
            'subject' => 'Lima!',
            'message' => 'Sepuluh ok',
        ]))->assertSessionHas('success');

        $this->submit($this->valid([
            'name' => str_repeat('a', 100),
            'subject' => str_repeat('s', 150),
            'message' => str_repeat('m', 2000),
        ]))->assertSessionHas('success');

        $this->assertSame(2, ContactMessage::query()->count());
    }

    public function test_every_field_as_an_array_is_rejected_without_a_server_error(): void
    {
        $response = $this->submit([
            'name' => ['x'],
            'email' => ['x'],
            'phone' => ['x'],
            'subject' => ['x'],
            'message' => ['x'],
            'consent' => ['1'],
            'website' => ['x'],
        ]);

        $response->assertRedirect(route('contact'))
            ->assertSessionHasErrors(['name', 'email', 'phone', 'subject', 'message', 'consent']);
        $this->assertSame(0, ContactMessage::query()->count());

        // Menampilkan ulang halaman dengan nilai lama berupa larik tidak boleh menjadi 500.
        $this->followingRedirects()->from(route('contact'))->post(route('contact.store'), [
            'name' => ['x'], 'email' => ['x'], 'phone' => ['x'], 'subject' => ['x'], 'message' => ['x'], 'consent' => ['1'],
        ])->assertOk()->assertSee('Nama harus berupa teks.');
    }

    public function test_the_failed_form_keeps_old_input_and_shows_the_error_summary(): void
    {
        $response = $this->followingRedirects()
            ->from(route('contact'))
            ->post(route('contact.store'), $this->valid(['name' => 'Ab', 'subject' => 'Subjek dipertahankan']))
            ->assertOk();

        $response->assertSee('data-errors-summary', false)
            ->assertSee('Periksa kembali isian Anda')
            ->assertSee('Nama minimal 3 karakter.')
            ->assertSee('id="name-error" class="invalid-feedback d-block"', false)
            ->assertSee('value="Ab"', false)
            ->assertSee('value="Subjek dipertahankan"', false)
            ->assertSee('Mohon info jadwal praktik dokter anak minggu depan.')
            ->assertSeeInOrder(['id="consent"', 'name="consent"', 'value="1"', 'checked'], false);
        $this->assertSame(1, preg_match('/<input type="checkbox" id="consent" name="consent" value="1"\s+checked/', $response->getContent()));
    }

    public function test_the_honeypot_input_is_never_repopulated(): void
    {
        $response = $this->followingRedirects()
            ->from(route('contact'))
            ->post(route('contact.store'), $this->valid(['name' => 'Ab', 'website' => 'http://spam.test']))
            ->assertOk();

        $response->assertDontSee('http://spam.test', false);
    }

    // --- persetujuan (F4B-R8) -----------------------------------------------------------------------------------

    public function test_the_consent_message_is_the_exact_text(): void
    {
        $this->submit($this->valid(['consent' => null]))
            ->assertSessionHasErrors(['consent' => 'Anda harus menyetujui pengolahan data untuk mengirim pesan.']);

        $this->assertSame(0, ContactMessage::query()->count());
    }

    // --- telepon dinormalisasi (F4B-R34) --------------------------------------------------------------------------

    /** @return array<string, array{0: string, 1: string}> */
    public static function phones(): array
    {
        return [
            'dengan tanda hubung' => ['0812-3456-7890', '081234567890'],
            'format plus enam dua' => ['+62 812 3456 7890', '+6281234567890'],
            'dengan kurung dan titik' => ['(0812) 3456.7890', '081234567890'],
            'format enam dua tanpa plus' => ['6281234567890', '6281234567890'],
        ];
    }

    #[DataProvider('phones')]
    public function test_the_phone_is_stored_normalized(string $input, string $stored): void
    {
        $this->submit($this->valid(['phone' => $input]))->assertSessionHas('success');

        $this->assertSame($stored, ContactMessage::query()->firstOrFail()->phone);
        $this->assertLessThanOrEqual(16, strlen($stored));
    }

    // --- XSS: teks tersimpan apa adanya, ter-escape saat dirender (SEK-15) ---------------------------------------------

    public function test_script_text_in_subject_and_message_is_stored_as_is(): void
    {
        $this->submit($this->valid([
            'subject' => '<script>alert(1)</script> Subjek',
            'message' => "<script>alert('x')</script> isi pesan panjang",
        ]))->assertSessionHas('success');

        $message = ContactMessage::query()->firstOrFail();

        $this->assertSame('<script>alert(1)</script> Subjek', $message->subject);
        $this->assertSame("<script>alert('x')</script> isi pesan panjang", $message->message);
    }

    public function test_script_text_shown_back_as_old_input_is_escaped(): void
    {
        $response = $this->followingRedirects()
            ->from(route('contact'))
            ->post(route('contact.store'), $this->valid([
                'consent' => null,
                'subject' => '<script>alert(1)</script> Subjek',
                'message' => '<script>alert(2)</script> isi pesan panjang',
            ]))
            ->assertOk();

        $response->assertSee('&lt;script&gt;alert(1)&lt;/script&gt; Subjek', false)
            ->assertSee('&lt;script&gt;alert(2)&lt;/script&gt; isi pesan panjang', false)
            ->assertDontSee('<script>alert(1)', false)
            ->assertDontSee('<script>alert(2)', false);
    }

    // --- honeypot (F4B-R7) ------------------------------------------------------------------------------------------------

    public function test_a_filled_honeypot_shows_a_fake_success_and_saves_nothing(): void
    {
        $this->submit($this->valid(['website' => 'http://spam.test']))
            ->assertRedirect(route('contact'))
            ->assertSessionHas('success', self::SUCCESS)
            ->assertSessionHasNoErrors();

        $this->assertSame(0, ContactMessage::query()->count());
    }

    public function test_an_array_honeypot_counts_as_filled_without_a_server_error(): void
    {
        $this->submit($this->valid(['website' => ['x']]))
            ->assertRedirect(route('contact'))
            ->assertSessionHas('success', self::SUCCESS)
            ->assertSessionHasNoErrors();

        $this->assertSame(0, ContactMessage::query()->count());
    }

    public function test_an_empty_honeypot_does_not_prevent_saving(): void
    {
        $this->submit($this->valid(['website' => '']))->assertSessionHas('success', self::SUCCESS);

        $this->assertSame(1, ContactMessage::query()->count());
    }

    // --- throttle (F4B-R6, SEK-14) -------------------------------------------------------------------------------------------

    public function test_the_fourth_submission_in_ten_minutes_is_throttled_without_saving(): void
    {
        foreach ([1, 2, 3] as $number) {
            $this->submit($this->valid(['subject' => "Subjek kiriman {$number}"]))
                ->assertRedirect(route('contact'))
                ->assertSessionHas('success', self::SUCCESS);
        }

        $this->assertSame(3, ContactMessage::query()->count());

        $fourth = $this->submit($this->valid(['subject' => 'Subjek kiriman keempat']));

        $fourth->assertRedirect(route('contact'))
            ->assertStatus(302)
            ->assertHeader('Retry-After')
            ->assertSessionHas('error', fn ($message) => preg_match('/^Terlalu banyak percobaan\. Silakan coba lagi dalam \d+ detik\.$/', $message) === 1)
            ->assertSessionMissing('success')
            ->assertSessionHasInput('subject', 'Subjek kiriman keempat');

        $this->assertSame(3, ContactMessage::query()->count());
        $this->assertSame(0, ContactMessage::query()->where('subject', 'Subjek kiriman keempat')->count());
        $this->assertGreaterThan(0, (int) $fourth->headers->get('Retry-After'));
        $this->assertLessThanOrEqual(600, (int) $fourth->headers->get('Retry-After'));
    }

    public function test_the_throttled_page_shows_the_error_flash_after_following_the_redirect(): void
    {
        foreach ([1, 2, 3] as $number) {
            $this->submit($this->valid(['subject' => "Subjek kiriman {$number}"]));
        }

        $this->followingRedirects()
            ->from(route('contact'))
            ->post(route('contact.store'), $this->valid(['subject' => 'Subjek keempat tertahan']))
            ->assertOk()
            ->assertSee('data-flash="error"', false)
            ->assertSee('Terlalu banyak percobaan. Silakan coba lagi dalam')
            ->assertSee('value="Subjek keempat tertahan"', false);
    }

    public function test_invalid_submissions_also_count_toward_the_limit(): void
    {
        foreach ([1, 2, 3] as $ignored) {
            $this->submit($this->valid(['name' => 'Ab']))->assertSessionHasErrors('name');
        }

        $this->submit($this->valid())
            ->assertHeader('Retry-After')
            ->assertSessionHas('error');

        $this->assertSame(0, ContactMessage::query()->count());
    }

    public function test_another_ip_is_not_blocked(): void
    {
        foreach ([1, 2, 3] as $number) {
            $this->submit($this->valid(['subject' => "Subjek kiriman {$number}"]));
        }

        $this->submit($this->valid())->assertHeader('Retry-After');

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->submit($this->valid(['subject' => 'Kiriman dari alamat lain']))
            ->assertSessionHas('success', self::SUCCESS);

        $this->assertSame(4, ContactMessage::query()->count());
    }

    public function test_the_limit_is_released_after_ten_minutes(): void
    {
        foreach ([1, 2, 3] as $number) {
            $this->submit($this->valid(['subject' => "Subjek kiriman {$number}"]));
        }

        $this->submit($this->valid())->assertHeader('Retry-After');

        $this->travel(11)->minutes();

        $this->submit($this->valid(['subject' => 'Kiriman setelah jeda']))->assertSessionHas('success', self::SUCCESS);
        $this->assertSame(4, ContactMessage::query()->count());
    }

    // --- CSRF (SEK-06) -----------------------------------------------------------------------------------------------------------

    public function test_a_missing_csrf_token_is_rejected_with_a_redirect_and_nothing_is_saved(): void
    {
        $this->app['env'] = 'local'; // ValidateCsrfToken hanya dilewati saat env `testing`

        $this->submit($this->valid())
            ->assertRedirect(route('contact'))
            ->assertSessionHas('error', 'Sesi formulir Anda telah berakhir. Silakan periksa isian Anda lalu kirim ulang.');

        $this->assertSame(0, ContactMessage::query()->count());
    }

    public function test_the_form_is_accepted_with_a_valid_csrf_token_when_the_check_is_active(): void
    {
        $this->app['env'] = 'local';

        $this->withSession(['_token' => 'token-uji'])
            ->from(route('contact'))
            ->post(route('contact.store'), $this->valid() + ['_token' => 'token-uji'])
            ->assertRedirect(route('contact'))
            ->assertSessionHas('success', self::SUCCESS);

        $this->assertSame(1, ContactMessage::query()->count());
    }

    // --- metode lain -------------------------------------------------------------------------------------------------------------------

    public function test_other_http_methods_on_the_contact_url_are_not_allowed(): void
    {
        $this->put('/kontak', $this->valid())->assertStatus(405);
        $this->patch('/kontak', $this->valid())->assertStatus(405);
        $this->delete('/kontak')->assertStatus(405);
        $this->assertSame(0, ContactMessage::query()->count());
    }
}
```

- [ ] **Step 3: Jalankan tes, harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter='IndonesianPhoneTest|MapEmbedComponentTest|PublicContactTest|ContactFormTest|FrontendManifestTest|PublicDoctorPagesTest|PublicHomeTest|RawOutputScanTest'
```
Expected: FAIL. Alasan yang diharapkan: `Class "App\Rules\IndonesianPhone" not found` (`IndonesianPhoneTest`); `Unable to locate a class or view for component [map-embed].` (`MapEmbedComponentTest`); `Route [contact] not defined.` dan `Route [contact.store] not defined.` (`PublicContactTest`, `ContactFormTest`); `FrontendManifestTest` gagal pada `function initMapEmbeds()` dan kelas `.map-embed`; `PublicDoctorPagesTest::test_an_empty_schedule_shows_the_empty_state_with_the_contact_link` gagal pada `Route contact dibuat di fase 4b.`; `PublicHomeTest::test_an_empty_database_renders_the_static_hero_and_hides_every_optional_block` gagal karena `Bagian judul-bantuan tidak ditemukan.`. `RawOutputScanTest` tetap PASS (filter tambahan tidak menemukan berkas).

- [ ] **Step 4: Implementasi rule, request, controller, dan komponen (tool Write)**

`app/Rules/IndonesianPhone.php`:

```php
<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Nomor seluler Indonesia (12 Asumsi 1, F4B-R34): spasi, tanda hubung, titik, dan kurung dibuang lebih dulu
 * (`normalize()`), lalu hasilnya harus cocok `^(\+62|62|0)8[0-9]{7,12}$`. Panjang terjauh 16 karakter (`+62` + `8` +
 * 12 digit) muat di `VARCHAR(20)`. Aturan tidak dijalankan untuk nilai kosong (gunakan `nullable` atau `required`).
 */
final class IndonesianPhone implements ValidationRule
{
    public const PATTERN = '/^(\+62|62|0)8[0-9]{7,12}$/';

    public const MESSAGE = 'Nomor telepon tidak valid. Gunakan nomor seluler Indonesia, mis. 081234567890.';

    /** Membuang spasi, tanda hubung, titik, dan kurung; tanda plus dan digit dipertahankan. */
    public static function normalize(string $value): string
    {
        return preg_replace('/[\s\-.()]+/u', '', $value) ?? $value;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match(self::PATTERN, self::normalize($value)) !== 1) {
            $fail(self::MESSAGE);
        }
    }
}
```

`app/Http/Requests/Public/StoreContactMessageRequest.php`:

```php
<?php

namespace App\Http\Requests\Public;

use App\Rules\IndonesianPhone;
use App\Rules\PersonName;
use Illuminate\Foundation\Http\FormRequest;

/**
 * V-37 (F4B-R34, F4B-R35): form pesan kontak publik, tanpa login. Field jebakan `website` sengaja tidak ada di
 * `rules()`; controller memeriksanya lewat `isHoneypotFilled()` (F4B-R7). Field larik menghasilkan galat berbahasa
 * Indonesia (aturan `string` mendahului aturan lain), bukan 500.
 */
class StoreContactMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Memangkas spasi tepi pada field teks (nilai bukan string dibiarkan agar divalidasi sebagai galat). */
    protected function prepareForValidation(): void
    {
        $trimmed = [];

        foreach (['name', 'email', 'phone', 'subject', 'message'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $trimmed[$field] = trim($value);
            }
        }

        $this->merge($trimmed);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['bail', 'required', 'string', 'min:3', 'max:100', new PersonName],
            'email' => ['bail', 'required', 'string', 'max:255', 'email:rfc'],
            'phone' => ['nullable', new IndonesianPhone],
            'subject' => ['bail', 'required', 'string', 'min:5', 'max:150'],
            'message' => ['bail', 'required', 'string', 'min:10', 'max:2000'],
            'consent' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama wajib diisi.',
            'name.string' => 'Nama harus berupa teks.',
            'name.min' => 'Nama minimal 3 karakter.',
            'name.max' => 'Nama maksimal 100 karakter.',
            'email.required' => 'Email wajib diisi.',
            'email.string' => 'Format email tidak valid.',
            'email.max' => 'Email maksimal 255 karakter.',
            'email.email' => 'Format email tidak valid.',
            'subject.required' => 'Subjek wajib diisi.',
            'subject.string' => 'Subjek harus berupa teks.',
            'subject.min' => 'Subjek minimal 5 karakter.',
            'subject.max' => 'Subjek maksimal 150 karakter.',
            'message.required' => 'Pesan wajib diisi.',
            'message.string' => 'Pesan harus berupa teks.',
            'message.min' => 'Pesan minimal 10 karakter.',
            'message.max' => 'Pesan maksimal 2000 karakter.',
            'consent.accepted' => 'Anda harus menyetujui pengolahan data untuk mengirim pesan.',
        ];
    }

    /**
     * Nama bidang untuk pesan aturan kustom (`PersonName` memakai `:Attribute`).
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'Nama',
            'email' => 'Email',
            'phone' => 'Nomor telepon',
            'subject' => 'Subjek',
            'message' => 'Pesan',
        ];
    }

    /** Bidang jebakan `website` terisi (F4B-R7): nilai apa pun yang tidak kosong, termasuk larik. */
    public function isHoneypotFilled(): bool
    {
        return filled($this->input('website'));
    }

    /**
     * Atribut pesan hasil validasi; telepon disimpan dalam bentuk ternormalisasi atau NULL (F4B-R34).
     *
     * @return array{name: string, email: string, phone: ?string, subject: string, message: string}
     */
    public function messageAttributes(): array
    {
        $data = $this->validated();
        $phone = $data['phone'] ?? null;

        return [
            'name' => (string) $data['name'],
            'email' => (string) $data['email'],
            'phone' => is_string($phone) && $phone !== '' ? IndonesianPhone::normalize($phone) : null,
            'subject' => (string) $data['subject'],
            'message' => (string) $data['message'],
        ];
    }
}
```

`app/Http/Controllers/Public/ContactController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Enums\ContactMessageStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreContactMessageRequest;
use App\Models\ContactMessage;
use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Halaman #22 `contact` dan kiriman pesan `contact.store` (06 §17). Pesan disimpan tanpa IP mentah (`ip_hash` =
 * SHA-256 dari IP dan APP_KEY) dan tidak diaudit (F4B-R25). Honeypot terisi: sukses palsu tanpa menyimpan (F4B-R7).
 * Throttle `contact` (3 per 10 menit per IP) dipasang di route.
 */
class ContactController extends Controller
{
    public const SUCCESS_MESSAGE = 'Terima kasih. Pesan Anda telah kami terima dan akan ditindaklanjuti oleh petugas kami.';

    public function show(): View
    {
        return view('public.contact', ['page' => Page::forKey('contact')]);
    }

    public function store(StoreContactMessageRequest $request): RedirectResponse
    {
        if (! $request->isHoneypotFilled()) {
            ContactMessage::query()->create($request->messageAttributes() + [
                'status' => ContactMessageStatus::New,
                'consent_at' => now(),
                'ip_hash' => hash('sha256', (string) $request->ip().config('app.key')),
            ]);
        }

        return redirect()->route('contact')->with('success', self::SUCCESS_MESSAGE);
    }
}
```

`app/View/Components/MapEmbed.php`:

```php
<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Services\Settings\SettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Peta tersemat (10 §3.4, F4B-R33, OFF-04). Selalu menyediakan kotak pengganti statis berisi alamat dan tombol
 * "Petunjuk Arah"; iframe `map.embed_url` hanya dirender bila berupa URL https. `initMapEmbeds()` di `app.js`
 * menyembunyikan iframe bila peramban offline atau iframe tidak `load` dalam 8000 ms. Tanpa JavaScript iframe tampil
 * di atas kotak pengganti.
 */
final class MapEmbed extends Component
{
    public int $height;

    public string $embedUrl;

    public string $directionsUrl;

    public string $address;

    public function __construct(
        SettingsService $settings,
        int $height = 360,
        public string $title = 'Peta lokasi UNPAM HOSPITAL',
    ) {
        $this->height = $height;

        $embed = $settings->string('map.embed_url');
        $directions = $settings->string('map.directions_url');

        // Pertahanan berlapis (pengaturan hanya diisi admin): iframe wajib https; tautan wajib http(s).
        $this->embedUrl = preg_match('#^https://#i', $embed) === 1 ? $embed : '';
        $this->directionsUrl = preg_match('#^https?://#i', $directions) === 1 ? $directions : '';
        $this->address = $settings->string('contact.address');
    }

    public function render(): View
    {
        return view('components.map-embed');
    }
}
```

`resources/views/components/map-embed.blade.php`:

```blade
<div {{ $attributes->merge(['class' => 'map-embed']) }} data-map-embed style="--map-height: {{ (int) $height }}px">
    <div class="map-embed-fallback" role="status" data-map-fallback>
        <p class="fw-semibold mb-1" data-map-fallback-text>{{ $embedUrl !== '' ? 'Memuat peta…' : 'Peta tidak tersedia.' }}</p>
        @if ($address !== '')
            <p class="small mb-2">{{ $address }}</p>
        @endif
        @if ($directionsUrl !== '')
            <a class="btn btn-outline-primary btn-sm" href="{{ $directionsUrl }}" target="_blank" rel="noopener noreferrer"><i class="bi bi-geo-alt" aria-hidden="true"></i> Petunjuk Arah</a>
        @endif
    </div>
    @if ($embedUrl !== '')
        <iframe src="{{ $embedUrl }}" title="{{ $title }}" loading="lazy" referrerpolicy="no-referrer-when-downgrade" data-map-frame></iframe>
    @endif
</div>
```

Catatan penulisan: (1) kelas `map-embed` harus langsung diikuti `data-map-embed` (tes mencocokkan `<div class="map-embed" data-map-embed style="--map-height: 360px">`); `$attributes->merge(['class' => 'map-embed'])` pada tag tanpa atribut tambahan menghasilkan persis `class="map-embed"`; (2) `(int) $height` di view memastikan variabel CSS selalu bilangan bulat (parameter konstruktor bertipe `int`, jadi nilai bukan angka sudah ditolak `TypeError` sebelum sampai ke view); (3) teks "Memuat peta…" memakai karakter elipsis tunggal (U+2026), sama dengan teks di `app.js`.

- [ ] **Step 5: Implementasi route (tool Edit)**

`routes/web.php`, dua Edit. Masing-masing `old_string` cocok tepat satu kali (keadaan sesudah Task 8).

old_string:

```php
use App\Http\Controllers\Public\ClinicController;
use App\Http\Controllers\Public\DoctorController;
```

new_string:

```php
use App\Http\Controllers\Public\ClinicController;
use App\Http\Controllers\Public\ContactController;
use App\Http\Controllers\Public\DoctorController;
```

old_string:

```php
Route::get('/faq', [FaqController::class, 'index'])->name('faq');
```

new_string:

```php
Route::get('/faq', [FaqController::class, 'index'])->name('faq');

// Kontak dan Lokasi (#22) dan kiriman pesan: throttle `contact` = 3 kiriman per 10 menit per IP (F4B-R6, D-23).
Route::get('/kontak', [ContactController::class, 'show'])->name('contact');
Route::post('/kontak', [ContactController::class, 'store'])->middleware('throttle:contact')->name('contact.store');
```

- [ ] **Step 6: Implementasi JS (tool Edit)**

`resources/js/app.js`, dua Edit. Masing-masing `old_string` cocok tepat satu kali.

old_string:

```js
// ---------------------------------------------------------------- Galeri layanan (modal + carousel Bootstrap)
```

new_string:

```js
// ---------------------------------------------------------------- Peta tersemat (x-map-embed, F4B-R33)
// Markup: [data-map-embed] berisi [data-map-fallback] (kotak pengganti statis: teks status [data-map-fallback-text],
// alamat, tombol Petunjuk Arah) dan, bila ada URL peta, iframe [data-map-frame] yang menumpuk di atasnya. Tanpa
// JavaScript iframe tetap tampil. Dengan JavaScript: bila peramban offline, iframe disembunyikan dan teks pengganti
// diganti; selain itu event load iframe menandai peta termuat, dan bila belum termuat dalam 8000 ms sejak peta
// mendekati viewport (IntersectionObserver; tanpa itu, sejak awal) iframe disembunyikan. Sengaja memakai event load, bukan listener galat baru: hanya ada satu delegasi galat (gambar).
const MAP_LOAD_TIMEOUT = 8000;
const MAP_UNAVAILABLE_TEXT = 'Peta tidak dapat dimuat. Alamat dan tombol Petunjuk Arah tetap dapat dipakai.';

function initMapEmbeds() {
    document.querySelectorAll('[data-map-embed]').forEach((root) => {
        const frame = root.querySelector('[data-map-frame]');

        if (!frame) {
            return;
        }

        const message = root.querySelector('[data-map-fallback-text]');
        const markUnavailable = () => {
            root.classList.add('is-unavailable');

            if (message) {
                message.textContent = MAP_UNAVAILABLE_TEXT;
            }
        };

        if (navigator.onLine === false) {
            markUnavailable();

            return;
        }

        frame.addEventListener('load', () => {
            root.classList.add('is-loaded');
        });

        // Iframe berinfo loading="lazy" baru dimuat mendekati viewport: pewaktu dimulai saat peta hampir terlihat,
        // bukan saat halaman dimuat, supaya peta di bawah lipatan tidak ditandai gagal sebelum sempat dimuat.
        const startTimer = () => {
            window.setTimeout(() => {
                if (!root.classList.contains('is-loaded')) {
                    markUnavailable();
                }
            }, MAP_LOAD_TIMEOUT);
        };

        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
                if (entries.some((entry) => entry.isIntersecting)) {
                    observer.disconnect();
                    startTimer();
                }
            }, { rootMargin: '300px' });

            observer.observe(root);
        } else {
            startTimer();
        }
    });
}

// ---------------------------------------------------------------- Galeri layanan (modal + carousel Bootstrap)
```

old_string:

```js
    initGalleryModals();
    initBannerCarousel();
```

new_string:

```js
    initGalleryModals();
    initBannerCarousel();
    initMapEmbeds();
```

Catatan: konstanta `8000` ditulis sebagai `MAP_LOAD_TIMEOUT = 8000` dan teks galat persis "Peta tidak dapat dimuat. Alamat dan tombol Petunjuk Arah tetap dapat dipakai." (dikunci `FrontendManifestTest`). Jangan menulis kata `onerror` di komentar atau kode JS.

- [ ] **Step 7: Implementasi SCSS (tool Edit)**

`resources/scss/_public.scss`. `old_string` (akhir berkas, teks baru Task 8) cocok tepat satu kali:

old_string:

```scss
.room-body {
  max-width: 48rem;
}
```

new_string:

```scss
.room-body {
  max-width: 48rem;
}

// ---------------------------------------------------------------- Kontak dan lokasi (#22, F4B-R32, F4B-R33)
// Peta tersemat: iframe menumpuk di atas kotak pengganti statis (alamat + Petunjuk Arah). Tinggi dari variabel CSS
// --map-height (bawaan 360 px) dan dibatasi 260 px di layar kecil. initMapEmbeds() menambah `is-loaded` (iframe
// termuat) atau `is-unavailable` (offline atau lewat 8 detik).
.map-embed {
  position: relative;
  height: var(--map-height, 360px);
  overflow: hidden;
  background: $light;
  border: 1px solid var(--bs-border-color);
  border-radius: $border-radius;

  @include media-breakpoint-down(sm) {
    height: min(var(--map-height, 360px), 260px);
  }

  iframe {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    border: 0;
  }
}

.map-embed-fallback {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 1rem;
  text-align: center;
  color: var(--bs-secondary-color);
}

.map-embed.is-unavailable iframe {
  display: none;
}

.map-embed.is-loaded .map-embed-fallback {
  visibility: hidden;
}
```

- [ ] **Step 8: Implementasi view halaman kontak (tool Write)**

`resources/views/public/contact.blade.php`:

```blade
@inject('site', 'App\Services\Settings\SettingsService')
@php
    $address = $site->string('contact.address');
    $phone = $site->string('contact.phone');
    $emergency = $site->string('contact.emergency_phone');
    $email = $site->string('contact.email');
    $hours = $site->string('contact.operational_hours');
    $whatsappUrl = $site->whatsappUrl();
    $directionsUrl = $site->string('map.directions_url');
    $directionsUrl = preg_match('#^https?://#i', $directionsUrl) === 1 ? $directionsUrl : '';
    $telHref = function (string $number): ?string {
        $digits = preg_replace('/[^0-9+]/', '', $number);

        return $digits === '' || $digits === null ? null : 'tel:'.$digits;
    };
    $phoneHref = $phone !== '' ? $telHref($phone) : null;
    $emergencyHref = $emergency !== '' ? $telHref($emergency) : null;
    $consentError = isset($errors) ? (string) $errors->first('consent') : '';
@endphp
<x-layouts.public :title="$page->seo_title" :meta-description="$page->seo_description">
    <x-slot:breadcrumb>
        <x-breadcrumb :items="[['label' => 'Kontak dan Lokasi']]" />
    </x-slot:breadcrumb>

    @push('head')
        <x-open-graph :title="$page->seo_title" :description="$page->seo_description" :canonical="route('contact')" />
    @endpush

    <x-page-header :title="$page->title" :subtitle="$page->subtitle" />

    <div class="row g-4 g-lg-5">
        <div class="col-lg-6">
            <section class="card mb-4" aria-labelledby="judul-alamat">
                <div class="card-body">
                    <h2 id="judul-alamat" class="h5 mb-3">Alamat</h2>
                    <address class="mb-3">{{ $address !== '' ? $address : 'Alamat belum diisi.' }}</address>

                    @if ($phone !== '' || $emergency !== '' || $whatsappUrl !== null || $email !== '' || $hours !== '')
                        <ul class="list-unstyled mb-3">
                            @if ($phone !== '')
                                <li>Telepon: @if ($phoneHref !== null)<a href="{{ $phoneHref }}">{{ $phone }}</a>@else{{ $phone }}@endif</li>
                            @endif
                            @if ($emergency !== '')
                                <li>IGD: @if ($emergencyHref !== null)<a href="{{ $emergencyHref }}">{{ $emergency }}</a>@else{{ $emergency }}@endif</li>
                            @endif
                            @if ($whatsappUrl !== null)
                                <li>WhatsApp: {{ $site->string('contact.whatsapp') }}</li>
                            @endif
                            @if ($email !== '')
                                <li>Email: <a href="mailto:{{ $email }}">{{ $email }}</a></li>
                            @endif
                            @if ($hours !== '')
                                <li>Jam operasional: {{ $hours }}</li>
                            @endif
                        </ul>
                    @endif

                    @if ($directionsUrl !== '' || $whatsappUrl !== null)
                        <div class="d-grid d-sm-flex flex-wrap gap-2">
                            @if ($directionsUrl !== '')
                                <a class="btn btn-primary" href="{{ $directionsUrl }}" target="_blank" rel="noopener noreferrer"><i class="bi bi-geo-alt" aria-hidden="true"></i> Petunjuk Arah</a>
                            @endif
                            @if ($whatsappUrl !== null)
                                <a class="btn btn-outline-primary" href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer"><i class="bi bi-whatsapp" aria-hidden="true"></i> Chat via WhatsApp (membuka WhatsApp)</a>
                            @endif
                        </div>
                    @endif
                </div>
            </section>

            <section class="card" aria-labelledby="judul-peta">
                <div class="card-body">
                    <h2 id="judul-peta" class="h5 mb-3">Peta</h2>
                    <x-map-embed />
                </div>
            </section>
        </div>

        <div class="col-lg-6">
            <section class="card" aria-labelledby="judul-form-kontak">
                <div class="card-body">
                    <h2 id="judul-form-kontak" class="h5 mb-3">Kirim Pesan</h2>

                    <x-form.errors-summary />

                    <form method="POST" action="{{ route('contact.store') }}" novalidate>
                        @csrf
                        <p class="small text-body-secondary">Kolom bertanda <span class="required-mark" aria-hidden="true">*</span> wajib diisi.</p>

                        <x-form.input name="name" label="Nama" :required="true" autocomplete="name" maxlength="100" />
                        <x-form.input name="email" type="email" label="Email" :required="true" autocomplete="email" maxlength="255" />
                        <x-form.input name="phone" type="tel" label="Telepon (opsional)" inputmode="tel" autocomplete="tel" maxlength="25" help="Nomor seluler Indonesia, mis. 081234567890." />
                        <x-form.input name="subject" label="Subjek" :required="true" maxlength="150" />
                        <x-form.textarea name="message" label="Pesan" :required="true" :rows="6" maxlength="2000" help="Jangan menuliskan data medis atau nomor identitas pada pesan ini." />

                        <div class="d-none" aria-hidden="true">
                            <label for="website">Situs web</label>
                            <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" id="consent" name="consent" value="1" @checked(old('consent') === '1') aria-required="true" @if ($consentError !== '') aria-invalid="true" aria-describedby="consent-error" @endif @class(['form-check-input', 'is-invalid' => $consentError !== ''])>
                                <label class="form-check-label" for="consent">Saya menyetujui data saya diproses sesuai @if (Route::has('privacy'))<a href="{{ route('privacy') }}">Kebijakan Privasi</a>@else Kebijakan Privasi@endif. <span class="required-mark" aria-hidden="true">*</span></label>
                            </div>
                            @if ($consentError !== '')
                                <div id="consent-error" class="invalid-feedback d-block">{{ $consentError }}</div>
                            @endif
                        </div>

                        <x-loading-button loading-text="Mengirim…" icon="bi-send">Kirim Pesan</x-loading-button>
                    </form>
                </div>
            </section>
        </div>
    </div>
</x-layouts.public>
```

Catatan penulisan: (1) `<address class="mb-3">…</address>` harus berada pada satu baris agar tes mencocokkan string persis; (2) `<li>Telepon: @if …<a …>…</a>@else{{ $phone }}@endif</li>` ditulis pada satu baris tanpa spasi tambahan; (3) checkbox persetujuan ditulis manual (bukan `x-form.checkbox`) karena label memuat tautan HTML yang tidak dapat dilewatkan lewat atribut `label`; perbandingan `old('consent') === '1'` ketat agar nilai lama berupa larik tidak menjadi konversi string; (4) kalimat label cocok persis dengan `Saya menyetujui data saya diproses sesuai <a href="…">Kebijakan Privasi</a>.`; (5) `<div class="d-none" aria-hidden="true">` membungkus honeypot dan tidak boleh diberi nilai awal.

- [ ] **Step 9: Build aset dan jalankan tes tugas, harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
npm run build
& $PHP artisan test --filter='IndonesianPhoneTest|MapEmbedComponentTest|PublicContactTest|ContactFormTest|FrontendManifestTest|PublicDoctorPagesTest|PublicHomeTest|RawOutputScanTest|PublicFaqTest|PublicContentPagesTest|PublicFacilitiesTest|PublicRoomsTest|SecurityHeadersTest|ExceptionRenderingTest|AdminPagesTest|NavigationMenuTest|LayoutsTest|PublicClinicDetailTest'
```
Expected: `npm run build` sukses tanpa entri Vite baru; seluruh tes lulus. Tes yang bercabang pada `Route::has('contact')` (`PublicFaqTest`, `PublicContentPagesTest`, `PublicHomeTest` tombol "Kirim Pesan", `AdminPagesTest`) memakai route sungguhan. `PublicDoctorPagesTest` tidak lagi memuat tes yang di-skip. `RawOutputScanTest` dan `FrontendManifestTest` hijau: hanya satu `addEventListener('error'`, tidak ada `onerror`.

- [ ] **Step 10: Pint**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint
& $PHP vendor\bin\pint --test
```
Expected: `--test` PASS (0 berkas bermasalah).

- [ ] **Step 11: Suite penuh (latar belakang)**

Suite ±11–14 menit melebihi batas 10 menit sebuah perintah tool. Jalankan sebagai tugas latar belakang (tool PowerShell dengan `run_in_background`), tunggu sampai selesai, lalu baca ringkasannya. Pastikan tidak ada tes yang berstatus skipped dengan alasan "fase 4b".

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
$LOG = Join-Path $env:TEMP "suite-fase4b-t09.txt"
& $PHP artisan test | Out-File -FilePath $LOG -Encoding utf8
Select-String -Path $LOG -Pattern 'Tests:|FAIL|Duration|fase 4b' | Select-Object -Last 20
```
Expected: seluruh tes PASS (±115 tes baru di atas angka Task 8); tidak ada baris yang memuat "fase 4b" pada keluaran tes yang dilewati.

- [ ] **Step 12: Commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git add app/Rules/IndonesianPhone.php app/Http/Requests/Public/StoreContactMessageRequest.php app/Http/Controllers/Public/ContactController.php app/View/Components/MapEmbed.php resources/views/components/map-embed.blade.php resources/views/public/contact.blade.php routes/web.php resources/js/app.js resources/scss/_public.scss tests/Unit/Rules/IndonesianPhoneTest.php tests/Feature/View/MapEmbedComponentTest.php tests/Feature/Public/PublicContactTest.php tests/Feature/Public/ContactFormTest.php tests/Feature/View/RawOutputScanTest.php tests/Unit/FrontendManifestTest.php tests/Feature/Public/PublicDoctorPagesTest.php tests/Feature/Public/PublicHomeTest.php
git commit -m @'
feat(fase-4b): halaman publik kontak dan lokasi (#22) dengan x-map-embed dan formulir pesan

Halaman kontak menampilkan alamat (selalu), kontak dari pengaturan (baris kosong disembunyikan), tombol Petunjuk
Arah dan WhatsApp, peta tersemat, dan formulir pesan. x-map-embed menyediakan kotak pengganti statis berisi alamat
dan tombol Petunjuk Arah; initMapEmbeds() menyembunyikan iframe bila offline atau tidak termuat dalam 8 detik
tanpa listener error baru. Pesan disimpan dengan ip_hash SHA-256 tanpa IP mentah, nomor telepon seluler Indonesia
dinormalisasi (IndonesianPhone), honeypot menjawab sukses palsu tanpa menyimpan, dan kiriman keempat dalam 10
menit dialihkan kembali dengan flash dan Retry-After. Tes lama yang bergantung pada route contact diperbarui.

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
'@
```
Expected: commit tercatat.

---

### Task 10: Seeder demo `RoomCategorySeeder`, `FacilitySeeder`, `ContactMessageSeeder` dan pembaruan invarian seeder

**Files:**
- Create: `database/seeders/RoomCategorySeeder.php`
- Create: `database/seeders/FacilitySeeder.php`
- Create: `database/seeders/ContactMessageSeeder.php`
- Modify: `database/seeders/DemoSeeder.php` (memanggil `RoomCategorySeeder`, `FacilitySeeder` sebelum `ContentSeeder`, dan `ContactMessageSeeder` sesudahnya)
- Test: `tests/Feature/Seeding/FacilitySeedersTest.php`
- Modify (tes lama): `tests/Feature/Seeding/DemoSeederWiringTest.php`, `MasterDataSeederGuardTest.php`, `DemoSeederInvariantsTest.php`, `SeederProductionGuardTest.php`
- Tidak diubah: `DatabaseSeeder` (tidak boleh memanggil ketiga seeder ini; `RolePermissionSeeder`, `SettingsSeeder`, `PageSeeder` tetap seeder inti), `PlaceholderImageGenerator`, `MediaService`, `ContentSeeder`, `ContentSeederTest`.

**Interfaces:**
- Consumes (Task 2-3):
  - model `RoomCategory` (fillable dan relasi `availabilityUpdater()`, accessor `occupancy_label`, `availability_updated_label`, `is_availability_stale`, `is_full`, `sample_rate_label`, scope `ordered()`), `Facility` (fillable, scope `ordered()`, `ofCategory()`), dan `ContactMessage` (fillable, `handler()`; `handled_by`, `handled_at`, `created_at`, `updated_at` diisi lewat `forceFill`);
  - enum `FacilityCategory` dan `ContactMessageStatus`;
  - `AuditObserver` untuk `Facility` dan `RoomCategory` (T3; seeder menulis baris `audit_logs` tanpa pelaku, dan `ContactMessage` tidak diobservasi);
  - `MediaService::store()`, `PlaceholderImageGenerator::make()`, `DemoUserSeeder::ACCOUNTS` (akun `pendaftaran@unpamhospital.test`).
- Produces:
  - `Database\Seeders\RoomCategorySeeder` (konstanta publik `ROOMS`, `UPDATER_EMAIL`, method statis `description()`);
  - `Database\Seeders\FacilitySeeder` (konstanta publik `FACILITIES`, method statis `description()`);
  - `Database\Seeders\ContactMessageSeeder` (konstanta publik `MESSAGES`, `HANDLER_EMAIL`);
  - data demo: 4 kategori kamar, 10 fasilitas, 5 pesan kontak, dan 26 media baru (fasilitas 10 cover + 4 galeri, kamar 4 cover + 8 galeri), sehingga total media demo menjadi **83** (57 + 26: cover 36, gallery 12).

**Test IDs:** SEED-01 (angka acuan 4b), SEED-02 (guard produksi untuk tiga seeder baru), SEED-03 (`media:prune --dry-run` tetap 0 baris dan 0 berkas yatim), CRD-06/CRD-07/CRD-12 (data demo tampil di publik dan admin), EMP-01 (tidak berlaku: data demo selalu ada).

**Catatan teknis (diverifikasi dari source):**
1. Pola `ClinicSeeder.php` dan `ContentSeeder.php` (4a): guard `app()->environment(['local', 'testing'])` dengan pesan `... DILARANG di APP_ENV=...`, idempoten per slug (`warn` lalu `continue`), gambar dari `PlaceholderImageGenerator::make()` lalu `MediaService::store()` dengan `@unlink` di `finally`. `DemoSeeder.php` memanggil seeder lewat satu `$this->call([...])` dan menjaga idempotensi dengan pemeriksaan akun `superadmin@unpamhospital.test`.
2. `PlaceholderImageGenerator::TYPES` sudah mengenal `cover` dan `gallery` (JPEG, teks "Foto contoh"). `MediaService::COLLECTIONS` memuat `cover` (min 800x450) dan `gallery` (min 600x400), sehingga 1200x800 lolos. Tidak ada koleksi baru.
3. `HasUniqueSlug` tidak menimpa slug yang sudah terisi (F4A-R7, F4B-R5), jadi slug eksplisit 13 §8.1 dan §8.2 dipertahankan.
4. `contact_messages.handled_by`, `handled_at`, dan `created_at`/`updated_at` tidak ada di `$fillable` (09 §6.8), jadi `ContactMessageSeeder` memakai `forceFill()` lalu `save()`. Laravel hanya mengisi `created_at`/`updated_at` bila belum kotor, sehingga nilai yang diberikan seeder dipertahankan.
5. `ContactMessage::$hidden` memuat `ip_hash`; tes membacanya lewat `DB::table('contact_messages')`.
6. Waktu: `availability_updated_at` = `now()->subHours(2)` dan seluruh tanggal pesan relatif terhadap `now()` aplikasi (tidak pernah `NOW()` SQL). Tes membekukan waktu dengan `$this->travelTo('2026-10-06 07:00:00')`.
7. CHECK `chk_room_categories_beds` (T1) dipenuhi seluruh baris seed (5 ≤ 12, 0 ≤ 16, 9 ≤ 24, 2 ≤ 6). Total kapasitas 58 dan tersedia 16.
8. `RoomCategorySeeder` dan `ContactMessageSeeder` bergantung pada akun Petugas Pendaftaran Demo, sehingga keduanya melempar `RuntimeException` bila akun itu belum ada. `FacilitySeeder` tidak bergantung pada akun.
9. Urutan `DemoSeeder` (13 §2.1, F4B-R36): `… ScheduleExceptionSeeder, RoomCategorySeeder, FacilitySeeder, ContentSeeder, ContactMessageSeeder`. 13 menaruh `ContactMessageSeeder` sesudah `AppointmentSeeder` (fase 7); karena seeder pasien dan appointment belum ada, di fase 4b seeder pesan ditempatkan tepat sesudah `ContentSeeder`. Fase 6 menyisipkan `PatientSeeder` dan `AppointmentSeeder` di antara keduanya.
10. Tes lama yang terdampak (dibaca dari berkas saat ini): `DemoSeederInvariantsTest.php:51-59` (cover 22 dan media 57), `:154` dan `:171` (larik `[13, 17, 18, 20, 57]`), dan `:167` (daftar seeder); `DemoSeederWiringTest.php:14-22` dan `:31`; `MasterDataSeederGuardTest.php:9-12`, `:25-31`, dan daftar tabel di `:44`; `SeederProductionGuardTest.php:27` (komentar "57 gambar") dan daftar tabel konten di `:35`.
11. Media demo setelah fase 4b: `photo` 16, `cover` 36 (16 layanan, 6 artikel, 10 fasilitas, 4 kamar), `gallery` 12 (4 fasilitas, 8 kamar), `image` 3, `photos` 12, `logo` 4 = **83**.
12. Jumlah tes baru: ±25 kasus (`FacilitySeedersTest` 24 kasus dari 21 method, ditambah satu tes invarian baru di `DemoSeederInvariantsTest`, dan beberapa asersi di tes lama).

- [ ] **Step 1: Verifikasi prasyarat (Task 2 dan 3 selesai)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git branch --show-current
git status --short
Test-Path app\Models\Facility.php, app\Models\RoomCategory.php, app\Models\ContactMessage.php, tests\Support\InteractsWithFacilities.php
Select-String -Path database\seeders\DemoSeeder.php -Pattern 'RoomCategorySeeder::class|FacilitySeeder::class|ContactMessageSeeder::class'
& $PHP artisan test --filter='DemoSeederInvariantsTest|DemoSeederWiringTest|MasterDataSeederGuardTest|SeederProductionGuardTest|ContentSeederTest'
```
Expected: `fase-4b-fasilitas-kamar-kontak`; `git status` bersih (kecuali `.superpowers/`); `True` empat kali; `Select-String` tidak mencetak apa pun (di `DemoSeeder.php` hanya ada komentar yang menyebut nama kelas tanpa `::class`); kelima kelas tes PASS.

- [ ] **Step 2: Tulis tes yang gagal (tool Write) `tests/Feature/Seeding/FacilitySeedersTest.php`**

```php
<?php

namespace Tests\Feature\Seeding;

use App\Enums\ContactMessageStatus;
use App\Models\ContactMessage;
use App\Models\Facility;
use App\Models\Media;
use App\Models\RoomCategory;
use Database\Seeders\ContactMessageSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\FacilitySeeder;
use Database\Seeders\RoomCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * Seeder demo fase 4b (13 §8.1, §8.2, §10.4, F4B-R36): 4 kategori kamar, 10 fasilitas, 5 pesan kontak, dan 26 gambar.
 * Prasyarat hanya akun demo (tanpa data master), agar tes cepat.
 */
class FacilitySeedersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->travelTo('2026-10-06 07:00:00');
    }

    private function seedAll(): void
    {
        $this->seed(DemoUserSeeder::class);
        $this->seed(RoomCategorySeeder::class);
        $this->seed(FacilitySeeder::class);
        $this->seed(ContactMessageSeeder::class);
    }

    public function test_counts_match_the_reference_numbers(): void
    {
        $this->seedAll();

        $this->assertSame(4, RoomCategory::query()->count());
        $this->assertSame(10, Facility::query()->count());
        $this->assertSame(5, ContactMessage::query()->count());
        $this->assertSame(26, Media::query()->count());
        $this->assertSame(14, Media::query()->where('model_type', (new Facility)->getMorphClass())->count());
        $this->assertSame(12, Media::query()->where('model_type', (new RoomCategory)->getMorphClass())->count());
    }

    public function test_room_categories_use_the_documented_values_and_order(): void
    {
        $this->seedAll();

        $rooms = RoomCategory::query()->with('availabilityUpdater')->ordered()->get();

        $this->assertSame(
            [
                ['Kamar BPJS Kelas 1', 'bpjs-kelas-1', 12, 5, '400000.00', 'per malam', 1],
                ['Kamar BPJS Kelas 2', 'bpjs-kelas-2', 16, 0, '250000.00', 'per malam', 2],
                ['Kamar BPJS Kelas 3', 'bpjs-kelas-3', 24, 9, '150000.00', 'per malam', 3],
                ['Kamar VIP/Pribadi', 'vip', 6, 2, '1200000.00', 'per malam', 4],
            ],
            $rooms->map(fn (RoomCategory $room): array => [
                $room->name,
                $room->slug,
                $room->bed_capacity,
                $room->beds_available,
                (string) $room->sample_rate,
                $room->rate_unit,
                $room->sort_order,
            ])->all(),
        );

        $this->assertSame(58, $rooms->sum('bed_capacity'));
        $this->assertSame(16, $rooms->sum('beds_available'));
        $this->assertTrue($rooms->every(fn (RoomCategory $room): bool => $room->is_active));
        $this->assertSame([], DB::select('SELECT id FROM room_categories WHERE beds_available > bed_capacity'));
    }

    public function test_room_amenities_and_rate_notes_are_exact(): void
    {
        $this->seedAll();

        $rooms = RoomCategory::query()->get()->keyBy('slug');

        $this->assertSame(['2 tempat tidur per kamar', 'Kamar mandi dalam', 'AC', 'Televisi', 'Lemari pasien', 'Kursi pendamping'], $rooms['bpjs-kelas-1']->amenities);
        $this->assertSame(['3–4 tempat tidur per kamar', 'Kamar mandi dalam', 'AC', 'Lemari pasien', 'Kursi pendamping'], $rooms['bpjs-kelas-2']->amenities);
        $this->assertSame(['4–6 tempat tidur per kamar', 'Kamar mandi bersama', 'Kipas/ventilasi', 'Lemari pasien'], $rooms['bpjs-kelas-3']->amenities);
        $this->assertSame(['1 tempat tidur', 'Kamar mandi dalam', 'AC', 'Televisi', 'Sofa pendamping', 'Lemari es kecil', 'Meja kerja'], $rooms['vip']->amenities);

        foreach (['bpjs-kelas-1', 'bpjs-kelas-2', 'bpjs-kelas-3'] as $slug) {
            $this->assertSame('Tarif contoh untuk prototipe; bukan tarif resmi dan bukan ketentuan BPJS.', $rooms[$slug]->rate_note, $slug);
        }

        $this->assertSame('Tarif contoh untuk prototipe; bukan tarif resmi.', $rooms['vip']->rate_note);

        foreach ($rooms as $room) {
            foreach ($room->amenities as $amenity) {
                $this->assertGreaterThanOrEqual(2, mb_strlen($amenity));
                $this->assertLessThanOrEqual(80, mb_strlen($amenity));
            }

            $this->assertLessThanOrEqual(255, mb_strlen($room->short_description));
            $this->assertStringEndsWith('(contoh).', $room->short_description, $room->slug);
        }
    }

    public function test_room_availability_is_two_hours_old_and_attributed_to_the_registration_officer(): void
    {
        $this->seedAll();

        $rooms = RoomCategory::query()->with('availabilityUpdater')->ordered()->get();

        foreach ($rooms as $room) {
            $this->assertTrue($room->availability_updated_at->equalTo(now()->subHours(2)), $room->slug);
            $this->assertSame('pendaftaran@unpamhospital.test', $room->availabilityUpdater->email, $room->slug);
            $this->assertSame('Petugas Pendaftaran Demo', $room->availabilityUpdater->name, $room->slug);
            $this->assertFalse($room->is_availability_stale, $room->slug);
        }

        $this->assertSame('5 dari 12 tempat tidur tersedia', $rooms[0]->occupancy_label);
        $this->assertTrue($rooms[1]->is_full);
        $this->assertFalse($rooms[0]->is_full);
        $this->assertStringContainsString('pukul 05.00 WIB', $rooms[0]->availability_updated_label);
        $this->assertSame('Rp 400.000 per malam', $rooms[0]->sample_rate_label);
        $this->assertSame('Rp 1.200.000 per malam', $rooms[3]->sample_rate_label);
    }

    public function test_room_texts_never_use_the_realtime_wording_and_descriptions_end_with_the_example_marker(): void
    {
        $this->seedAll();

        foreach (RoomCategory::query()->get() as $room) {
            $text = $room->name.' '.$room->short_description.' '.$room->description.' '.$room->rate_note.' '.implode(' ', $room->amenities);

            $this->assertStringNotContainsStringIgnoringCase('real-time', $text, $room->slug);
            $this->assertStringNotContainsStringIgnoringCase('realtime', $text, $room->slug);
            $this->assertStringEndsWith('(deskripsi contoh)', trim(strip_tags((string) $room->description)), $room->slug);
            $this->assertSame(1, substr_count((string) $room->description, '<p>'), $room->slug);
            $this->assertNull($room->meta_title);
            $this->assertNull($room->meta_description);
        }
    }

    public function test_each_room_has_one_cover_and_two_gallery_photos_with_the_documented_alt_text(): void
    {
        $this->seedAll();

        foreach (RoomCategory::query()->with('media')->ordered()->get() as $room) {
            $cover = $room->firstMedia('cover');
            $this->assertNotNull($cover, $room->slug);
            $this->assertSame('Foto contoh '.$room->name, $cover->alt_text);
            $this->assertSame([1200, 800], [$cover->width, $cover->height]);
            $this->assertSame('jpg', $cover->extension);

            $gallery = $room->mediaIn('gallery');
            $this->assertCount(2, $gallery, $room->slug);
            $this->assertSame([1, 2], $gallery->pluck('sort_order')->all());
            $this->assertSame(
                ["Foto contoh {$room->name} 1", "Foto contoh {$room->name} 2"],
                $gallery->pluck('alt_text')->all(),
            );

            foreach ($gallery as $photo) {
                $this->assertSame([1200, 800], [$photo->width, $photo->height]);
                $this->assertSame('jpg', $photo->extension);
            }
        }
    }

    public function test_facilities_use_the_documented_names_slugs_categories_and_order(): void
    {
        $this->seedAll();

        $facilities = Facility::query()->ordered()->get();

        $this->assertSame(
            [
                ['Instalasi Gawat Darurat', 'instalasi-gawat-darurat', 'medis', 'Ruang penanganan awal kondisi gawat darurat (contoh).', 1],
                ['Ruang Tindakan Minor', 'ruang-tindakan-minor', 'medis', 'Ruang untuk tindakan ringan rawat jalan (contoh).', 2],
                ['Laboratorium', 'fasilitas-laboratorium', 'penunjang', 'Ruang pemeriksaan sampel untuk penunjang diagnosis (contoh).', 3],
                ['Radiologi', 'fasilitas-radiologi', 'penunjang', 'Ruang pemeriksaan pencitraan penunjang diagnosis (contoh).', 4],
                ['Apotek', 'apotek', 'penunjang', 'Layanan penyiapan obat sesuai resep dokter (contoh).', 5],
                ['Ruang Pendaftaran dan Informasi', 'ruang-pendaftaran-dan-informasi', 'umum', 'Loket pendaftaran, check-in, dan bantuan informasi (contoh).', 6],
                ['Ruang Tunggu Pasien', 'ruang-tunggu-pasien', 'umum', 'Area tunggu dengan tempat duduk dan pendingin ruangan (contoh).', 7],
                ['Musala', 'musala', 'umum', 'Tempat ibadah untuk pasien dan pendamping (contoh).', 8],
                ['Area Parkir', 'area-parkir', 'umum', 'Area parkir kendaraan roda dua dan roda empat (contoh).', 9],
                ['Akses Ramah Difabel', 'akses-ramah-difabel', 'umum', 'Jalur landai dan toilet yang dapat diakses kursi roda (contoh).', 10],
            ],
            $facilities->map(fn (Facility $facility): array => [
                $facility->name,
                $facility->slug,
                $facility->category->value,
                $facility->short_description,
                $facility->sort_order,
            ])->all(),
        );

        $this->assertSame(['medis' => 2, 'penunjang' => 3, 'umum' => 5], $facilities->countBy(fn (Facility $facility): string => $facility->category->value)->all());
        $this->assertTrue($facilities->every(fn (Facility $facility): bool => $facility->is_active));
    }

    public function test_facility_descriptions_are_neutral_single_paragraphs_ending_with_the_example_marker(): void
    {
        $this->seedAll();

        foreach (Facility::query()->get() as $facility) {
            $this->assertStringEndsWith('(deskripsi contoh)', trim(strip_tags((string) $facility->description)), $facility->slug);
            $this->assertSame(1, substr_count((string) $facility->description, '<p>'), $facility->slug);
            $this->assertStringNotContainsString('<script', (string) $facility->description);
            $this->assertNull($facility->meta_title);
            $this->assertNull($facility->meta_description);
            $this->assertLessThanOrEqual(255, mb_strlen($facility->short_description));
        }
    }

    public function test_each_facility_has_a_cover_and_only_the_waiting_and_registration_rooms_have_two_gallery_photos(): void
    {
        $this->seedAll();

        $withGallery = ['ruang-pendaftaran-dan-informasi', 'ruang-tunggu-pasien'];

        foreach (Facility::query()->with('media')->ordered()->get() as $facility) {
            $cover = $facility->firstMedia('cover');
            $this->assertNotNull($cover, $facility->slug);
            $this->assertSame('Foto contoh '.$facility->name, $cover->alt_text);
            $this->assertSame([1200, 800], [$cover->width, $cover->height]);
            $this->assertSame('jpg', $cover->extension);

            $gallery = $facility->mediaIn('gallery');

            if (in_array($facility->slug, $withGallery, true)) {
                $this->assertCount(2, $gallery, $facility->slug);
                $this->assertSame(["Foto contoh {$facility->name} 1", "Foto contoh {$facility->name} 2"], $gallery->pluck('alt_text')->all());
                $this->assertSame([1, 2], $gallery->pluck('sort_order')->all());

                continue;
            }

            $this->assertCount(0, $gallery, $facility->slug);
        }
    }

    public function test_media_counts_per_collection_and_files_exist(): void
    {
        $this->seedAll();

        $this->assertSame(
            ['cover' => 14, 'gallery' => 12],
            Media::query()->selectRaw('collection, COUNT(*) as total')->groupBy('collection')->orderBy('collection')->pluck('total', 'collection')->map(fn ($total): int => (int) $total)->all(),
        );

        foreach (Media::query()->get() as $media) {
            Storage::disk('public')->assertExists($media->directory.'/'.$media->file_name);
            Storage::disk('public')->assertExists((string) $media->thumb_path);
        }
    }

    public function test_contact_messages_use_the_documented_senders_subjects_and_statuses(): void
    {
        $this->seedAll();

        $messages = ContactMessage::query()->with('handler')->orderBy('id')->get();

        $this->assertSame(
            [
                ['Rudi Hartono', 'rudi@contoh.test', 'Pertanyaan jam operasional', 'new', null],
                ['Sinta Maharani', 'sinta@contoh.test', 'Menanyakan ketersediaan kamar VIP', 'new', null],
                ['Tono Wibisono', 'tono@contoh.test', 'Saran tampilan jadwal dokter', 'read', null],
                ['Wati Kusuma', 'wati@contoh.test', 'Perubahan data profil', 'replied', 'pendaftaran@unpamhospital.test'],
                ['Vina Oktaviana', 'vina@contoh.test', 'Pesan uji (diarsipkan)', 'archived', 'pendaftaran@unpamhospital.test'],
            ],
            $messages->map(fn (ContactMessage $message): array => [
                $message->name,
                $message->email,
                $message->subject,
                $message->status->value,
                $message->handler?->email,
            ])->all(),
        );

        $this->assertSame(
            [ContactMessageStatus::New, ContactMessageStatus::New, ContactMessageStatus::Read, ContactMessageStatus::Replied, ContactMessageStatus::Archived],
            $messages->pluck('status')->all(),
        );
        $this->assertSame(2, ContactMessage::query()->unread()->count());
    }

    public function test_contact_message_dates_phone_consent_and_ip_hash_follow_the_rules(): void
    {
        $this->seedAll();

        $messages = ContactMessage::query()->orderBy('id')->get();
        $expectedHash = hash('sha256', '127.0.0.1'.config('app.key'));

        foreach ($messages as $index => $message) {
            $number = $index + 1;

            $this->assertTrue($message->created_at->equalTo(now()->subDays(6 - $number)), "pesan {$number}");
            $this->assertTrue($message->consent_at->equalTo($message->created_at), "pesan {$number}");
            $this->assertGreaterThanOrEqual(10, mb_strlen($message->message), "pesan {$number}");
            $this->assertLessThanOrEqual(2000, mb_strlen($message->message), "pesan {$number}");
            $this->assertStringNotContainsString('<', $message->message);
        }

        $this->assertSame([null, '081234567890', null, null, null], $messages->pluck('phone')->all());

        $hashes = DB::table('contact_messages')->pluck('ip_hash')->all();
        $this->assertSame(array_fill(0, 5, $expectedHash), $hashes);
        $this->assertSame(64, strlen($expectedHash));
        $this->assertDoesNotMatchRegularExpression('/127\.0\.0\.1/', implode(' ', $hashes));
    }

    public function test_only_the_replied_and_archived_messages_have_handler_and_handled_at(): void
    {
        $this->seedAll();

        $messages = ContactMessage::query()->orderBy('id')->get();

        foreach ([0, 1, 2] as $index) {
            $this->assertNull($messages[$index]->handled_by, 'pesan '.($index + 1));
            $this->assertNull($messages[$index]->handled_at, 'pesan '.($index + 1));
        }

        foreach ([3, 4] as $index) {
            $this->assertNotNull($messages[$index]->handled_by, 'pesan '.($index + 1));
            $this->assertTrue($messages[$index]->handled_at->equalTo($messages[$index]->created_at->copy()->addHour()), 'pesan '.($index + 1));
        }
    }

    public function test_running_the_seeders_twice_does_not_duplicate_anything(): void
    {
        $this->seedAll();
        $this->seed(RoomCategorySeeder::class);
        $this->seed(FacilitySeeder::class);
        $this->seed(ContactMessageSeeder::class);

        $this->assertSame([4, 10, 5, 26], [
            RoomCategory::query()->count(),
            Facility::query()->count(),
            ContactMessage::query()->count(),
            Media::query()->count(),
        ]);
    }

    public function test_an_existing_slug_is_skipped_and_keeps_its_edited_values(): void
    {
        $this->seedAll();

        RoomCategory::query()->where('slug', 'vip')->firstOrFail()->update(['name' => 'Kamar VIP Baru', 'beds_available' => 1]);
        Facility::query()->where('slug', 'musala')->firstOrFail()->update(['is_active' => false]);

        $this->seed(RoomCategorySeeder::class);
        $this->seed(FacilitySeeder::class);

        $vip = RoomCategory::query()->where('slug', 'vip')->firstOrFail();
        $this->assertSame('Kamar VIP Baru', $vip->name);
        $this->assertSame(1, $vip->beds_available);
        $this->assertFalse(Facility::query()->where('slug', 'musala')->firstOrFail()->is_active);
        $this->assertSame(4, RoomCategory::query()->count());
        $this->assertSame(10, Facility::query()->count());
    }

    public function test_a_message_with_the_same_email_and_subject_is_not_recreated(): void
    {
        $this->seedAll();

        ContactMessage::query()->where('email', 'rudi@contoh.test')->firstOrFail()->forceFill(['status' => ContactMessageStatus::Archived->value])->save();

        $this->seed(ContactMessageSeeder::class);

        $this->assertSame(5, ContactMessage::query()->count());
        $this->assertSame(ContactMessageStatus::Archived, ContactMessage::query()->where('email', 'rudi@contoh.test')->firstOrFail()->status);
    }

    /** @return array<string, array{class-string, string}> */
    public static function seeders(): array
    {
        return [
            'RoomCategorySeeder' => [RoomCategorySeeder::class, 'RoomCategorySeeder DILARANG di APP_ENV=production. Seeder kamar contoh hanya untuk data demo.'],
            'FacilitySeeder' => [FacilitySeeder::class, 'FacilitySeeder DILARANG di APP_ENV=production. Seeder fasilitas contoh hanya untuk data demo.'],
            'ContactMessageSeeder' => [ContactMessageSeeder::class, 'ContactMessageSeeder DILARANG di APP_ENV=production. Pesan contoh memuat data pribadi fiktif dan hanya untuk data demo.'],
        ];
    }

    #[DataProvider('seeders')]
    public function test_each_seeder_throws_outside_local_and_testing_and_writes_nothing(string $seeder, string $message): void
    {
        $this->seed(DemoUserSeeder::class);

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
        $this->assertSame($message, $thrown->getMessage());
        $this->assertSame(0, DB::table('room_categories')->count() + DB::table('facilities')->count() + DB::table('contact_messages')->count() + DB::table('media')->count());
    }

    #[DataProvider('seedersNeedingTheOfficer')]
    public function test_seeders_that_attribute_rows_require_the_demo_registration_officer(string $seeder): void
    {
        $thrown = null;

        try {
            $this->app->make($seeder)->run();
        } catch (RuntimeException $e) {
            $thrown = $e;
        }

        $this->assertInstanceOf(RuntimeException::class, $thrown);
        $this->assertStringContainsString('Jalankan DemoUserSeeder lebih dulu.', $thrown->getMessage());
        $this->assertSame(0, RoomCategory::query()->count() + ContactMessage::query()->count() + Media::query()->count());
    }

    /** @return array<string, array{class-string}> */
    public static function seedersNeedingTheOfficer(): array
    {
        return [
            'RoomCategorySeeder' => [RoomCategorySeeder::class],
            'ContactMessageSeeder' => [ContactMessageSeeder::class],
        ];
    }

    public function test_the_facility_seeder_does_not_need_any_account(): void
    {
        $this->seed(FacilitySeeder::class);

        $this->assertSame(10, Facility::query()->count());
        $this->assertSame(14, Media::query()->count());
    }

    public function test_media_prune_dry_run_reports_no_orphans_after_seeding(): void
    {
        $this->seedAll();

        $this->artisan('media:prune', ['--dry-run' => true])
            ->expectsOutputToContain('Baris media yatim: 0')
            ->expectsOutputToContain('Berkas yatim: 0')
            ->assertExitCode(0);
    }

    public function test_the_database_seeder_runs_all_three_through_the_demo_seeder_but_never_calls_them_directly(): void
    {
        $source = (string) file_get_contents(database_path('seeders/DatabaseSeeder.php'));

        foreach (['RoomCategorySeeder', 'FacilitySeeder', 'ContactMessageSeeder'] as $class) {
            $this->assertStringNotContainsString($class, $source);
        }

        $this->seed(DatabaseSeeder::class);

        $this->assertSame([4, 10, 5, 83], [
            RoomCategory::query()->count(),
            Facility::query()->count(),
            ContactMessage::query()->count(),
            Media::query()->count(),
        ]);
        $this->assertSame(3, DB::table('users')->count());
    }
}
```

Perbarui tes lama (tool Edit; setiap `old_string` cocok tepat satu kali).

`tests/Feature/Seeding/DemoSeederWiringTest.php`:

old_string:

```php
            ['DemoUserSeeder', 'SpecialtySeeder', 'ClinicSeeder', 'DoctorSeeder', 'DoctorScheduleSeeder', 'ScheduleExceptionSeeder', 'ContentSeeder'],
```

new_string:

```php
            ['DemoUserSeeder', 'SpecialtySeeder', 'ClinicSeeder', 'DoctorSeeder', 'DoctorScheduleSeeder', 'ScheduleExceptionSeeder', 'RoomCategorySeeder', 'FacilitySeeder', 'ContentSeeder', 'ContactMessageSeeder'],
```

old_string:

```php
        foreach (['SpecialtySeeder', 'ClinicSeeder', 'DoctorSeeder', 'DoctorScheduleSeeder', 'ScheduleExceptionSeeder', 'ContentSeeder'] as $class) {
```

new_string:

```php
        foreach (['SpecialtySeeder', 'ClinicSeeder', 'DoctorSeeder', 'DoctorScheduleSeeder', 'ScheduleExceptionSeeder', 'RoomCategorySeeder', 'FacilitySeeder', 'ContentSeeder', 'ContactMessageSeeder'] as $class) {
```

`tests/Feature/Seeding/MasterDataSeederGuardTest.php`:

old_string:

```php
use Database\Seeders\ClinicSeeder;
use Database\Seeders\ContentSeeder;
use Database\Seeders\DoctorSeeder;
use Database\Seeders\SpecialtySeeder;
```

new_string:

```php
use Database\Seeders\ClinicSeeder;
use Database\Seeders\ContactMessageSeeder;
use Database\Seeders\ContentSeeder;
use Database\Seeders\DoctorSeeder;
use Database\Seeders\FacilitySeeder;
use Database\Seeders\RoomCategorySeeder;
use Database\Seeders\SpecialtySeeder;
```

old_string:

```php
            'ContentSeeder' => [ContentSeeder::class],
        ];
```

new_string:

```php
            'ContentSeeder' => [ContentSeeder::class],
            'RoomCategorySeeder' => [RoomCategorySeeder::class],
            'FacilitySeeder' => [FacilitySeeder::class],
            'ContactMessageSeeder' => [ContactMessageSeeder::class],
        ];
```

old_string:

```php
        foreach (['specialties', 'clinics', 'doctors', 'article_categories', 'articles', 'banners', 'galleries', 'faqs', 'insurance_partners', 'media'] as $table) {
```

new_string:

```php
        foreach (['specialties', 'clinics', 'doctors', 'article_categories', 'articles', 'banners', 'galleries', 'faqs', 'insurance_partners', 'room_categories', 'facilities', 'contact_messages', 'media'] as $table) {
```

`tests/Feature/Seeding/SeederProductionGuardTest.php`:

old_string:

```php
        // DemoSeeder membuat 57 gambar contoh (32 fase 2 + 25 konten fase 4a): jangan menulis ke storage nyata.
```

new_string:

```php
        // DemoSeeder membuat 83 gambar contoh (32 fase 2 + 25 konten fase 4a + 26 fasilitas dan kamar fase 4b): jangan menulis ke storage nyata.
```

old_string:

```php
        foreach (['article_categories', 'articles', 'banners', 'galleries', 'faqs', 'insurance_partners'] as $table) {
```

new_string:

```php
        foreach (['article_categories', 'articles', 'banners', 'galleries', 'faqs', 'insurance_partners', 'room_categories', 'facilities', 'contact_messages'] as $table) {
```

`tests/Feature/Seeding/DemoSeederInvariantsTest.php`:

old_string:

```php
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Faq;
```

new_string:

```php
use App\Models\Clinic;
use App\Models\ContactMessage;
use App\Models\Doctor;
use App\Models\Facility;
use App\Models\Faq;
```

old_string:

```php
use App\Models\Media;
use App\Models\Specialty;
```

new_string:

```php
use App\Models\Media;
use App\Models\RoomCategory;
use App\Models\Specialty;
```

old_string:

```php
        $this->assertSame(22, Media::query()->where('collection', 'cover')->count()); // 16 layanan + 6 artikel (ContentSeeder)
        $this->assertSame(57, Media::query()->count()); // 32 master + 25 konten: 6 cover artikel, 3 banner, 12 foto album, 4 logo
```

new_string:

```php
        $this->assertSame(36, Media::query()->where('collection', 'cover')->count()); // 16 layanan + 6 artikel + 10 fasilitas + 4 kamar
        $this->assertSame(12, Media::query()->where('collection', 'gallery')->count()); // 4 fasilitas + 8 kamar (fase 4b)
        $this->assertSame(83, Media::query()->count()); // 32 master + 25 konten (4a) + 26 fasilitas dan kamar (4b)
```

old_string:

```php
    public function test_every_doctor_has_exactly_one_primary_specialty_and_archive_state_is_consistent(): void
```

new_string:

```php
    public function test_phase_4b_counts_and_availability_match_the_reference_numbers(): void
    {
        $this->seedDemo();

        $this->assertSame(4, RoomCategory::query()->count());
        $this->assertSame(10, Facility::query()->count());
        $this->assertSame(5, ContactMessage::query()->count());
        $this->assertSame(2, ContactMessage::query()->unread()->count());
        $this->assertSame(10, Media::query()->where('collection', 'cover')->where('model_type', (new Facility)->getMorphClass())->count());
        $this->assertSame(4, Media::query()->where('collection', 'gallery')->where('model_type', (new Facility)->getMorphClass())->count());
        $this->assertSame(4, Media::query()->where('collection', 'cover')->where('model_type', (new RoomCategory)->getMorphClass())->count());
        $this->assertSame(8, Media::query()->where('collection', 'gallery')->where('model_type', (new RoomCategory)->getMorphClass())->count());
        $this->assertSame(58, (int) RoomCategory::query()->sum('bed_capacity'));
        $this->assertSame(16, (int) RoomCategory::query()->sum('beds_available'));
        $this->assertSame(1, RoomCategory::query()->where('beds_available', 0)->count());
        $this->assertSame([], DB::select('SELECT id FROM room_categories WHERE beds_available > bed_capacity'));
        $this->assertSame([], DB::select('SELECT id FROM room_categories WHERE availability_updated_at IS NULL OR availability_updated_by IS NULL'));
        $this->assertSame([], DB::select("SELECT id FROM contact_messages WHERE (status IN ('replied', 'archived')) <> (handled_by IS NOT NULL)"));
        $this->assertSame([], DB::select('SELECT id FROM contact_messages WHERE CHAR_LENGTH(ip_hash) <> 64'));
    }

    public function test_every_doctor_has_exactly_one_primary_specialty_and_archive_state_is_consistent(): void
```

old_string:

```php
            ->expectsOutputToContain('Data demo sudah ada')
            ->assertExitCode(0);

        $this->assertSame([13, 17, 18, 20, 57], [
```

new_string:

```php
            ->expectsOutputToContain('Data demo sudah ada')
            ->assertExitCode(0);

        $this->assertSame([13, 17, 18, 20, 83], [
```

old_string:

```php
        foreach (['SpecialtySeeder', 'ClinicSeeder', 'DoctorSeeder', 'DoctorScheduleSeeder', 'ScheduleExceptionSeeder', 'ContentSeeder'] as $seeder) {
            $this->artisan('db:seed', ['--class' => "Database\\Seeders\\{$seeder}", '--no-interaction' => true])->assertExitCode(0);
        }

        $this->assertSame([13, 17, 18, 20, 57], [
```

new_string:

```php
        foreach (['SpecialtySeeder', 'ClinicSeeder', 'DoctorSeeder', 'DoctorScheduleSeeder', 'ScheduleExceptionSeeder', 'RoomCategorySeeder', 'FacilitySeeder', 'ContentSeeder', 'ContactMessageSeeder'] as $seeder) {
            $this->artisan('db:seed', ['--class' => "Database\\Seeders\\{$seeder}", '--no-interaction' => true])->assertExitCode(0);
        }

        $this->assertSame([13, 17, 18, 20, 83], [
```

- [ ] **Step 3: Jalankan tes, harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter='FacilitySeedersTest|DemoSeederWiringTest|MasterDataSeederGuardTest|DemoSeederInvariantsTest'
```
Expected: FAIL. Alasan yang diharapkan: `Class "Database\Seeders\RoomCategorySeeder" not found` (`FacilitySeedersTest`, `MasterDataSeederGuardTest`); `Failed asserting that an array does not contain false.` (`DemoSeederWiringTest`); `Failed asserting that 22 is identical to 36` (`DemoSeederInvariantsTest`).

- [ ] **Step 4: Implementasi seeder (tool Write)**

`database/seeders/RoomCategorySeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\RoomCategory;
use App\Models\User;
use App\Services\Media\MediaService;
use App\Services\Media\PlaceholderImageGenerator;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Data demo kategori kamar rawat inap (13 §8.1, F4B-R36). Hanya `local`/`testing`; idempoten per slug.
 * `availability_updated_at` = sekarang dikurangi 2 jam dan `availability_updated_by` = Petugas Pendaftaran Demo.
 * Nama kategori adalah kebutuhan prototipe, bukan klasifikasi BPJS yang sudah diverifikasi (BR-116).
 */
class RoomCategorySeeder extends Seeder
{
    /** Akun yang tercatat sebagai pembaruh ketersediaan (DemoUserSeeder::ACCOUNTS, Petugas Pendaftaran Demo). */
    public const UPDATER_EMAIL = 'pendaftaran@unpamhospital.test';

    /**
     * 13 §8.1 disalin persis. `sort_order` = nomor urut 1…4, `is_active` = true. `label` = teks besar pada gambar contoh.
     * Kelas 2 sengaja `beds_available = 0` agar badge "Penuh" teruji; CHECK `chk_room_categories_beds` terpenuhi.
     *
     * @var list<array{name: string, slug: string, label: string, short: string, amenities: list<string>, capacity: int, available: int, rate: int, note: string}>
     */
    public const ROOMS = [
        [
            'name' => 'Kamar BPJS Kelas 1',
            'slug' => 'bpjs-kelas-1',
            'label' => 'KELAS 1',
            'short' => 'Kamar rawat inap untuk dua pasien per kamar dengan kamar mandi dalam (contoh).',
            'amenities' => ['2 tempat tidur per kamar', 'Kamar mandi dalam', 'AC', 'Televisi', 'Lemari pasien', 'Kursi pendamping'],
            'capacity' => 12,
            'available' => 5,
            'rate' => 400000,
            'note' => 'Tarif contoh untuk prototipe; bukan tarif resmi dan bukan ketentuan BPJS.',
        ],
        [
            'name' => 'Kamar BPJS Kelas 2',
            'slug' => 'bpjs-kelas-2',
            'label' => 'KELAS 2',
            'short' => 'Kamar rawat inap untuk tiga sampai empat pasien per kamar dengan kamar mandi dalam (contoh).',
            'amenities' => ['3–4 tempat tidur per kamar', 'Kamar mandi dalam', 'AC', 'Lemari pasien', 'Kursi pendamping'],
            'capacity' => 16,
            'available' => 0,
            'rate' => 250000,
            'note' => 'Tarif contoh untuk prototipe; bukan tarif resmi dan bukan ketentuan BPJS.',
        ],
        [
            'name' => 'Kamar BPJS Kelas 3',
            'slug' => 'bpjs-kelas-3',
            'label' => 'KELAS 3',
            'short' => 'Kamar rawat inap untuk empat sampai enam pasien per kamar dengan kamar mandi bersama (contoh).',
            'amenities' => ['4–6 tempat tidur per kamar', 'Kamar mandi bersama', 'Kipas/ventilasi', 'Lemari pasien'],
            'capacity' => 24,
            'available' => 9,
            'rate' => 150000,
            'note' => 'Tarif contoh untuk prototipe; bukan tarif resmi dan bukan ketentuan BPJS.',
        ],
        [
            'name' => 'Kamar VIP/Pribadi',
            'slug' => 'vip',
            'label' => 'VIP',
            'short' => 'Kamar rawat inap pribadi untuk satu pasien dengan sofa pendamping (contoh).',
            'amenities' => ['1 tempat tidur', 'Kamar mandi dalam', 'AC', 'Televisi', 'Sofa pendamping', 'Lemari es kecil', 'Meja kerja'],
            'capacity' => 6,
            'available' => 2,
            'rate' => 1200000,
            'note' => 'Tarif contoh untuk prototipe; bukan tarif resmi.',
        ],
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('RoomCategorySeeder DILARANG di APP_ENV='.app()->environment().'. Seeder kamar contoh hanya untuk data demo.');
        }

        $updater = User::query()->where('email', self::UPDATER_EMAIL)->first();

        if ($updater === null) {
            throw new RuntimeException('Akun Petugas Pendaftaran Demo belum ada. Jalankan DemoUserSeeder lebih dulu.');
        }

        $media = app(MediaService::class);
        $generator = app(PlaceholderImageGenerator::class);

        foreach (self::ROOMS as $index => $row) {
            if (RoomCategory::query()->where('slug', $row['slug'])->exists()) {
                $this->command?->warn("Kategori kamar {$row['name']} sudah ada, dilewati.");

                continue;
            }

            $room = RoomCategory::query()->create([
                'name' => $row['name'],
                'slug' => $row['slug'],
                'short_description' => $row['short'],
                'description' => self::description($row['name']),
                'amenities' => $row['amenities'],
                'bed_capacity' => $row['capacity'],
                'beds_available' => $row['available'],
                'sample_rate' => $row['rate'],
                'rate_unit' => 'per malam',
                'rate_note' => $row['note'],
                'is_active' => true,
                'sort_order' => $index + 1,
                'availability_updated_at' => now()->subHours(2),
                'availability_updated_by' => $updater->id,
                'meta_title' => null,
                'meta_description' => null,
            ]);

            $this->attach($media, $generator, 'cover', $row['label'], $room, 'Foto contoh '.$row['name']);

            foreach ([1, 2] as $number) {
                $this->attach($media, $generator, 'gallery', $row['label'].' '.$number, $room, "Foto contoh {$row['name']} {$number}");
            }
        }
    }

    /**
     * Satu paragraf HTML netral yang berakhiran "(deskripsi contoh)"; disanitasi trait SanitizesRichText saat disimpan.
     */
    public static function description(string $name): string
    {
        return '<p>'.e($name.' adalah kategori kamar rawat inap contoh untuk prototipe UNPAM HOSPITAL. Nama kategori, fasilitas, dan tarif dapat berubah dan perlu ditinjau sebelum dipakai (deskripsi contoh)').'</p>';
    }

    /**
     * Membuat satu gambar contoh 1200x800 lewat GD lalu menyimpannya lewat MediaService; berkas sementara selalu dihapus.
     */
    private function attach(MediaService $media, PlaceholderImageGenerator $generator, string $collection, string $label, RoomCategory $owner, string $alt): void
    {
        $file = $generator->make($collection, $label, 1200, 800);

        try {
            $media->store($file, $collection, $owner, ['alt_text' => $alt]);
        } finally {
            @unlink($file->getPathname());
        }
    }
}
```

`database/seeders/FacilitySeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\Facility;
use App\Services\Media\MediaService;
use App\Services\Media\PlaceholderImageGenerator;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Data demo fasilitas (13 §8.2, F4B-R36). Hanya `local`/`testing`; idempoten per slug. Deskripsi netral berakhiran
 * "(deskripsi contoh)" tanpa klaim sertifikasi atau peralatan merek tertentu.
 */
class FacilitySeeder extends Seeder
{
    /**
     * 13 §8.2 disalin persis. `sort_order` = nomor urut 1…10, `is_active` = true. `label` = teks besar pada gambar contoh;
     * `gallery` true hanya untuk #6 dan #7 (masing-masing dua foto galeri).
     *
     * @var list<array{name: string, slug: string, category: string, short: string, label: string, gallery: bool}>
     */
    public const FACILITIES = [
        ['name' => 'Instalasi Gawat Darurat', 'slug' => 'instalasi-gawat-darurat', 'category' => 'medis', 'short' => 'Ruang penanganan awal kondisi gawat darurat (contoh).', 'label' => 'IGD', 'gallery' => false],
        ['name' => 'Ruang Tindakan Minor', 'slug' => 'ruang-tindakan-minor', 'category' => 'medis', 'short' => 'Ruang untuk tindakan ringan rawat jalan (contoh).', 'label' => 'TINDAKAN', 'gallery' => false],
        ['name' => 'Laboratorium', 'slug' => 'fasilitas-laboratorium', 'category' => 'penunjang', 'short' => 'Ruang pemeriksaan sampel untuk penunjang diagnosis (contoh).', 'label' => 'LAB', 'gallery' => false],
        ['name' => 'Radiologi', 'slug' => 'fasilitas-radiologi', 'category' => 'penunjang', 'short' => 'Ruang pemeriksaan pencitraan penunjang diagnosis (contoh).', 'label' => 'RADIOLOGI', 'gallery' => false],
        ['name' => 'Apotek', 'slug' => 'apotek', 'category' => 'penunjang', 'short' => 'Layanan penyiapan obat sesuai resep dokter (contoh).', 'label' => 'APOTEK', 'gallery' => false],
        ['name' => 'Ruang Pendaftaran dan Informasi', 'slug' => 'ruang-pendaftaran-dan-informasi', 'category' => 'umum', 'short' => 'Loket pendaftaran, check-in, dan bantuan informasi (contoh).', 'label' => 'PENDAFTARAN', 'gallery' => true],
        ['name' => 'Ruang Tunggu Pasien', 'slug' => 'ruang-tunggu-pasien', 'category' => 'umum', 'short' => 'Area tunggu dengan tempat duduk dan pendingin ruangan (contoh).', 'label' => 'RUANG TUNGGU', 'gallery' => true],
        ['name' => 'Musala', 'slug' => 'musala', 'category' => 'umum', 'short' => 'Tempat ibadah untuk pasien dan pendamping (contoh).', 'label' => 'MUSALA', 'gallery' => false],
        ['name' => 'Area Parkir', 'slug' => 'area-parkir', 'category' => 'umum', 'short' => 'Area parkir kendaraan roda dua dan roda empat (contoh).', 'label' => 'PARKIR', 'gallery' => false],
        ['name' => 'Akses Ramah Difabel', 'slug' => 'akses-ramah-difabel', 'category' => 'umum', 'short' => 'Jalur landai dan toilet yang dapat diakses kursi roda (contoh).', 'label' => 'AKSES', 'gallery' => false],
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('FacilitySeeder DILARANG di APP_ENV='.app()->environment().'. Seeder fasilitas contoh hanya untuk data demo.');
        }

        $media = app(MediaService::class);
        $generator = app(PlaceholderImageGenerator::class);

        foreach (self::FACILITIES as $index => $row) {
            if (Facility::query()->where('slug', $row['slug'])->exists()) {
                $this->command?->warn("Fasilitas {$row['name']} sudah ada, dilewati.");

                continue;
            }

            $facility = Facility::query()->create([
                'name' => $row['name'],
                'slug' => $row['slug'],
                'category' => $row['category'],
                'short_description' => $row['short'],
                'description' => self::description($row['name']),
                'is_active' => true,
                'sort_order' => $index + 1,
                'meta_title' => null,
                'meta_description' => null,
            ]);

            $this->attach($media, $generator, 'cover', $row['label'], $facility, 'Foto contoh '.$row['name']);

            if ($row['gallery']) {
                foreach ([1, 2] as $number) {
                    $this->attach($media, $generator, 'gallery', $row['label'].' '.$number, $facility, "Foto contoh {$row['name']} {$number}");
                }
            }
        }
    }

    /**
     * Satu paragraf HTML netral yang berakhiran "(deskripsi contoh)"; disanitasi trait SanitizesRichText saat disimpan.
     */
    public static function description(string $name): string
    {
        return '<p>'.e($name.' adalah fasilitas contoh di UNPAM HOSPITAL untuk keperluan prototipe. Keterangan pada halaman ini bersifat umum dan perlu ditinjau sebelum dipakai (deskripsi contoh)').'</p>';
    }

    /**
     * Membuat satu gambar contoh 1200x800 lewat GD lalu menyimpannya lewat MediaService; berkas sementara selalu dihapus.
     */
    private function attach(MediaService $media, PlaceholderImageGenerator $generator, string $collection, string $label, Facility $owner, string $alt): void
    {
        $file = $generator->make($collection, $label, 1200, 800);

        try {
            $media->store($file, $collection, $owner, ['alt_text' => $alt]);
        } finally {
            @unlink($file->getPathname());
        }
    }
}
```

`database/seeders/ContactMessageSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Enums\ContactMessageStatus;
use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Data demo pesan kontak (13 §10.4, F4B-R36). Hanya `local`/`testing`; idempoten per email dan subjek.
 * Pengirim fiktif (`@contoh.test`); `ip_hash` memakai IP 127.0.0.1 (IP mentah tidak disimpan).
 */
class ContactMessageSeeder extends Seeder
{
    /** Penangan pesan berstatus `replied` dan `archived` (DemoUserSeeder::ACCOUNTS, Petugas Pendaftaran Demo). */
    public const HANDLER_EMAIL = 'pendaftaran@unpamhospital.test';

    /**
     * 13 §10.4. Urutan = nomor #1…5; `created_at` = sekarang dikurangi (6 - nomor) hari dan `consent_at` = `created_at`.
     * `handled` true (#4, #5): `handled_by` = Petugas Pendaftaran Demo dan `handled_at` = `created_at` + 1 jam.
     *
     * @var list<array{name: string, email: string, phone: ?string, subject: string, message: string, status: string, handled: bool}>
     */
    public const MESSAGES = [
        ['name' => 'Rudi Hartono', 'email' => 'rudi@contoh.test', 'phone' => null, 'subject' => 'Pertanyaan jam operasional', 'message' => 'Selamat pagi. Mohon informasi jam operasional poliklinik pada hari Sabtu dan hari libur. Terima kasih.', 'status' => 'new', 'handled' => false],
        ['name' => 'Sinta Maharani', 'email' => 'sinta@contoh.test', 'phone' => '081234567890', 'subject' => 'Menanyakan ketersediaan kamar VIP', 'message' => 'Saya ingin menanyakan apakah kamar VIP masih tersedia untuk beberapa hari ke depan. Mohon informasinya.', 'status' => 'new', 'handled' => false],
        ['name' => 'Tono Wibisono', 'email' => 'tono@contoh.test', 'phone' => null, 'subject' => 'Saran tampilan jadwal dokter', 'message' => 'Tampilan jadwal dokter sudah cukup jelas. Saran saya, tambahkan keterangan hari libur pada setiap minggu.', 'status' => 'read', 'handled' => false],
        ['name' => 'Wati Kusuma', 'email' => 'wati@contoh.test', 'phone' => null, 'subject' => 'Perubahan data profil', 'message' => 'Saya ingin mengubah data profil pada akun saya. Mohon petunjuk langkah yang perlu dilakukan.', 'status' => 'replied', 'handled' => true],
        ['name' => 'Vina Oktaviana', 'email' => 'vina@contoh.test', 'phone' => null, 'subject' => 'Pesan uji (diarsipkan)', 'message' => 'Ini adalah pesan uji yang sudah diarsipkan oleh petugas. Tidak ada tindak lanjut yang diperlukan.', 'status' => 'archived', 'handled' => true],
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('ContactMessageSeeder DILARANG di APP_ENV='.app()->environment().'. Pesan contoh memuat data pribadi fiktif dan hanya untuk data demo.');
        }

        $handler = User::query()->where('email', self::HANDLER_EMAIL)->first();

        if ($handler === null) {
            throw new RuntimeException('Akun Petugas Pendaftaran Demo belum ada. Jalankan DemoUserSeeder lebih dulu.');
        }

        $ipHash = hash('sha256', '127.0.0.1'.config('app.key'));

        foreach (self::MESSAGES as $index => $row) {
            if (ContactMessage::query()->where('email', $row['email'])->where('subject', $row['subject'])->exists()) {
                $this->command?->warn("Pesan {$row['email']} ({$row['subject']}) sudah ada, dilewati.");

                continue;
            }

            $receivedAt = now()->subDays(6 - ($index + 1));

            $message = new ContactMessage;
            $message->forceFill([
                'name' => $row['name'],
                'email' => $row['email'],
                'phone' => $row['phone'],
                'subject' => $row['subject'],
                'message' => $row['message'],
                'status' => ContactMessageStatus::from($row['status']),
                'consent_at' => $receivedAt,
                'ip_hash' => $ipHash,
                'handled_by' => $row['handled'] ? $handler->id : null,
                'handled_at' => $row['handled'] ? $receivedAt->copy()->addHour() : null,
                'created_at' => $receivedAt,
                'updated_at' => $row['handled'] ? $receivedAt->copy()->addHour() : $receivedAt,
            ])->save();
        }
    }
}
```

`database/seeders/DemoSeeder.php` (tool Edit; `old_string` cocok tepat satu kali):

old_string:

```php
        // Seeder data master (fase 2 dst.) ditambahkan ke daftar ini mengikuti urutan 13 §2.1.
        // RoomCategorySeeder dan FacilitySeeder (fase 4b) disisipkan sebelum ContentSeeder.
        $this->call([
            DemoUserSeeder::class,
            SpecialtySeeder::class,
            ClinicSeeder::class,
            DoctorSeeder::class,
            DoctorScheduleSeeder::class,
            ScheduleExceptionSeeder::class,
            ContentSeeder::class,
        ]);
```

new_string:

```php
        // Seeder data master (fase 2 dst.) ditambahkan ke daftar ini mengikuti urutan 13 §2.1.
        // PatientSeeder dan AppointmentSeeder (fase 6/7) disisipkan antara ContentSeeder dan ContactMessageSeeder.
        $this->call([
            DemoUserSeeder::class,
            SpecialtySeeder::class,
            ClinicSeeder::class,
            DoctorSeeder::class,
            DoctorScheduleSeeder::class,
            ScheduleExceptionSeeder::class,
            RoomCategorySeeder::class,
            FacilitySeeder::class,
            ContentSeeder::class,
            ContactMessageSeeder::class,
        ]);
```

- [ ] **Step 5: Jalankan tes tugas, harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter='FacilitySeedersTest|DemoSeederWiringTest|MasterDataSeederGuardTest|DemoSeederInvariantsTest|SeederProductionGuardTest|ContentSeederTest|ScheduleSeederTest|RolePermissionSeederTest|PageSeederTest'
```
Expected: PASS. Bila `test_media_prune_dry_run_reports_no_orphans_after_seeding` gagal, periksa bahwa setiap `store()` memakai pemilik yang sudah tersimpan. Bila `DemoSeederInvariantsTest` melaporkan jumlah media selain 83, hitung ulang: 57 + 14 + 12. Bila `availability_updated_label` tidak memuat "pukul 05.00 WIB", periksa accessor Task 2 (`format('H.i')`) dan zona waktu aplikasi `Asia/Jakarta`. Bila `ContactMessageSeeder` menyimpan `created_at` = waktu nyata dan bukan waktu bawaan seeder, pastikan kedua atribut diberikan lewat `forceFill` sebelum `save()` (bukan lewat `create()`).

- [ ] **Step 6: Pint**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint
& $PHP vendor\bin\pint --test
```
Expected: `PASS`.
- [ ] **Step 7: Suite penuh (latar belakang)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
$job = Start-Job -ScriptBlock { param($php, $app) Set-Location $app; & $php artisan test *> "$app\storage\logs\suite-task10.txt" } -ArgumentList $PHP, $APP
Wait-Job $job -Timeout 1200 | Out-Null
Get-Content "$APP\storage\logs\suite-task10.txt" -Tail 15
```
Expected: semua lulus (tes yang memanggil `DatabaseSeeder` kini membuat 26 gambar tambahan di `Storage::fake`, jadi suite sedikit lebih lambat). Bila `Wait-Job` kedaluwarsa sebelum selesai, ulangi `Wait-Job $job -Timeout 600` dan baca ekor berkas lagi.

- [ ] **Step 8: Commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git add database/seeders/RoomCategorySeeder.php database/seeders/FacilitySeeder.php database/seeders/ContactMessageSeeder.php database/seeders/DemoSeeder.php tests/Feature/Seeding/FacilitySeedersTest.php tests/Feature/Seeding/DemoSeederWiringTest.php tests/Feature/Seeding/MasterDataSeederGuardTest.php tests/Feature/Seeding/DemoSeederInvariantsTest.php tests/Feature/Seeding/SeederProductionGuardTest.php
git commit -m @'
feat(fase-4b): seeder demo kamar, fasilitas, dan pesan kontak serta invarian media 83

Data persis 13 §8.1, §8.2, dan §10.4 dengan slug eksplisit dan 26 gambar contoh lokal; hanya local/testing, idempoten,
dipanggil DemoSeeder sebelum dan sesudah ContentSeeder. Tes seeder lama diperbarui (media 57 menjadi 83).

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
'@
```
Expected: commit tercatat.

---

---

### Task 11: Matriks akses fase 4b, `Phase4bRoutePermissionTest`, rekonsiliasi dokumen, README "Fase 4b", dan gerbang fase 4b

**Files:**
- Modify: `tests/Feature/Access/AdminPageMatrixTest.php` (baris publik #9, #10, #11, #12–#15, #22; baris admin #36, #36b, #39b; mutasi 4b termasuk #36c; jenis parameter baru; empat tes baru)
- Create: `tests/Feature/Access/Phase4bRoutePermissionTest.php`
- Modify: `README.md` (bagian "Fase 4b")
- Modify: `docs/superpowers/specs/2026-10-02-unpam-hospital-blueprint/{00-keputusan-inti,05-sitemap-matriks-halaman,06-alur,07-wireframe,11-route-controller-model-service,12-aturan-bisnis-validasi-keamanan,13-data-contoh,17-keputusan-konfirmasi}.md`
- Tidak diubah: `AdminRouteMiddlewareTest.php`, `Phase2RoutePermissionTest.php`, `Phase3RoutePermissionTest.php` (hanya dijalankan; KNS-02 otomatis mencakup 16 route admin baru), `Phase4aRoutePermissionTest.php` (tes total 90 sudah diganti Task 4), `docs/superpowers/CHECKPOINT.md` dan "Catatan Eksekusi" rencana ini (diisi koordinator setelah final review).

**Interfaces:**
- Consumes: semua Task 1–10.
  - Route admin `admin.facilities.*`, `admin.room-categories.*` (termasuk `availability`), `admin.messages.*` (`index`, `show`, `status`).
  - Route publik `facilities.index|show`, `rooms.index|show`, `contact`, `contact.store`.
  - `config('navigation.modules.facilities')`.
  - `Tests\Support\InteractsWithFacilities` (`createFacility()`, `createRoomCategory()`, `createContactMessage()`), `InteractsWithContent`, `InteractsWithMasterData`, `InteractsWithMedia`, `InteractsWithSchedules`, `InteractsWithStaff`.
  - `Support\NavigationMenu::items()`.
- Produces:
  - baris matriks 15 §3.1 untuk #9, #10, #11, #12–#15 (empat slug seed), dan #22 × lima aktor;
  - baris 15 §3.3 untuk #36 (`['L', '403', '200', '403', '200']`), #36b (`['L', '403', '200', '200', '200']`), dan #39b (`['L', '403', '403', '200', '200']`);
  - baris mutasi 4b (#36c: `['G', '403', 'OK', 'OK', 'OK']`);
  - `Phase4bRoutePermissionTest` (16 nama route admin, izin, nama parameter, URI Indonesia, 6 route publik terbuka, ketiadaan `admin.messages.destroy` dan `show` fasilitas/kamar, total 106 route admin, `throttle:contact`, tab modul `facilities`);
  - rekonsiliasi blueprint untuk F4B-R1, R3, R4, R5, R6, R7, R8, R9, R10, R11, R12, R13, R16, R17, R18, R19, R20 (termasuk teks layar #36 di 05), R22, R23, R24, R25, R31, R33, R34, R35, R36, dan R39 (baris 17 §4 D-124 s.d. D-139), nama baru fase 4b (00 J.6), dan angka media demo 83 (13 §11.2);
  - README "Fase 4b" dan verifikasi gerbang fase 4b beserta langkah **MANUAL (koordinator)**.

**Test IDs:** AKS-01 (#9–#15, #22), AKS-02 sampai AKS-05 (#36, #36b, #36c, #39b dan seluruh mutasi 4b), AKS-04 (layar #36 tidak mengueri data pasien atau pesan kontak, F4B-R39; Admin Konten ditolak di kotak pesan), AKS-05 (Petugas hanya mode ketersediaan, D-12), AKS-06 (pasien terhadap `/admin/fasilitas`, `/admin/kamar`, `/admin/pesan`), KNS-01 (bagian fase 4b: tanpa `admin.messages.destroy`, tanpa `show` fasilitas dan kamar), KNS-02 (tetap hijau), KNS-03 (izin `facilities.manage`, `rooms.update-availability`, `messages.manage` dipakai 16 route dan dua tab), CRD-13 (rangkuman di bawah), EMP-01 (matriks publik berjalan di DB hampir kosong), Review Focus 3 dan 5 (akses dan privasi, bagian matriks).

**Rangkuman CRD-13 untuk entitas fase 4b dan pemilik tesnya:**

| Entitas | Aturan | Tes |
|---|---|---|
| `facilities` | hapus permanen; cover dan galeri ikut terhapus | `AdminFacilitiesTest` (Task 4) |
| `room_categories` | hapus selalu boleh (tidak dirujuk tabel lain); gambar ikut terhapus | `AdminRoomCategoriesTest` (Task 5) |
| `contact_messages` | tanpa route `destroy`; diarsipkan | `Phase4bRoutePermissionTest::test_messages_have_no_destroy_create_edit_or_update_routes_and_facilities_and_rooms_have_no_show`, `AdminContactMessagesTest` (Task 6), `Phase3RoutePermissionTest::test_crd_13_destroy_routes_do_not_exist_for_entities_that_are_never_deleted` |

**Catatan teknis (diverifikasi dari source):**
1. `AdminPageMatrixTest.php` saat ini memuat trait `InteractsWithContent`, `InteractsWithMasterData`, `InteractsWithMedia`, `InteractsWithSchedules`, `InteractsWithStaff`, konstanta `MANAGE` dan `CONTENT_SCREEN`, tiga data provider (`matrix`, `publicMatrix`, `mutations`), dan `parameters()` bertipe `match` tanpa cabang bawaan; jenis parameter baru harus ditambahkan ke `match` itu. `use Illuminate\Support\Facades\DB;` sudah ada sejak 4a. Tes mutasi hanya membuktikan "lolos otorisasi" untuk aktor berhak (`200/201/204/302/422`), sehingga `POST`/`PATCH` kosong (validasi gagal, 302) dan `DELETE` tetap lolos.
2. Matriks publik menegaskan `assertOk()` untuk lima aktor. Halaman berparameter slug memakai fasilitas dan kategori kamar yang **aktif** (nonaktif menjawab 404, F4B-R14); empat slug seed kamar dibuat dengan `createRoomCategory()` dan slug eksplisit `bpjs-kelas-1`, `bpjs-kelas-2`, `bpjs-kelas-3`, `vip`.
3. Izin (`RolePermissionSeeder`): Admin Konten memegang `facilities.manage` dan `rooms.update-availability` tetapi **tidak** `messages.manage`; Petugas Pendaftaran memegang `rooms.update-availability` dan `messages.manage` tetapi **tidak** `facilities.manage`. Itu sebabnya #36b Petugas 200, #36 dan tambah/ubah kamar Petugas 403, dan #39b Admin Konten 403.
4. Parameter route admin `facility`, `roomCategory`, dan `message` sudah dibatasi `[0-9]+` di `bootstrap/app.php:29-35`; `DELETE`/`POST`/`PUT` ke route yang tidak ada namun URI-nya terdaftar untuk metode lain menghasilkan 405 karena pencocokan route terjadi sebelum middleware.
5. Tab modul `facilities` ditulis Task 3 (`config/navigation.php`); `NavigationMenu::items('modules.facilities', $user)` menyaring route yang belum ada dan izin pengguna. Petugas Pendaftaran menerima satu tab ("Kamar dan Kapasitas") karena izin tab itu memuat `rooms.update-availability`; pasien dan tamu menerima larik kosong.
6. Basis hitungan route admin: 90 (fase 1–4a) + 16 (fasilitas 6, kamar 7, pesan 3) = **106** (F4B-R13). Hitungan `route:list --path=admin` sama dengan jumlah route berURI `admin` atau `admin/*`. Tes total 90 di `Phase4aRoutePermissionTest` sudah diganti Task 4 dengan tes yang menghitung 40 route `admin/konten`.
7. Dokumen: semua jangkar Edit di bawah sudah dicocokkan dengan berkas nyata (masing-masing tepat satu kali). Nomor D terakhir di 17 adalah **D-123**, sehingga baris baru dimulai dari **D-124** dan berakhir di **D-139** (16 baris). 17 memuat 123 baris D sebelum tugas ini dan 139 sesudahnya.
8. Jumlah tes baru: ±330 kasus (`AdminPageMatrixTest` bertambah ±300 kasus data provider dan 4 tes; `Phase4bRoutePermissionTest` ±45 kasus).
9. Penyimpangan blueprint yang dicatat sebagai baris D dan edit teks: F4B-R3 (rich text 30.000), R6 (throttle kontak 302), R7 (honeypot), R8 (flash kontak), R9 dan R11 (dua badge, kalimat peringatan tanpa kata "real-time"), R16 (daftar kamar tanpa paginasi), R18 dan R19 (kapasitas, `amenities` textarea), R23 dan R24 (filter dan status pesan), R33 (peta tanpa listener `error`), dan R36 (seeder demo).

- [ ] **Step 1: Verifikasi prasyarat**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git branch --show-current
git status --short
git log --oneline -5
& $PHP artisan route:list --path=admin --json | ConvertFrom-Json | Measure-Object | ForEach-Object { "route admin: $($_.Count)" }
& $PHP artisan route:list --except-path=admin --json | ConvertFrom-Json | Where-Object { $_.name -in 'facilities.index', 'facilities.show', 'rooms.index', 'rooms.show', 'contact', 'contact.store' } | ForEach-Object { "$($_.method) $($_.name)" }
& $PHP artisan test --filter='Phase4aRoutePermissionTest|Phase3RoutePermissionTest|AdminPageMatrixTest'
```
Expected: `fase-4b-fasilitas-kamar-kontak`; bersih (hanya `.superpowers/` tidak terlacak); commit Task 10 terlihat; `route admin: 106` (90 + 16; bila berbeda, bandingkan dengan daftar nama di `Phase4bRoutePermissionTest` di bawah sebelum melanjutkan); enam baris publik (`GET|HEAD facilities.index`, `GET|HEAD facilities.show`, `GET|HEAD rooms.index`, `GET|HEAD rooms.show`, `GET|HEAD contact`, `POST contact.store`); ketiga kelas tes PASS.

**(a) Matriks akses**

- [ ] **Step 2: Tulis tes (tool Edit) `tests/Feature/Access/AdminPageMatrixTest.php`**

Baris lama tidak diubah. Setiap `old_string` cocok tepat satu kali.

old_string:

```php
use Tests\Support\InteractsWithContent;
use Tests\Support\InteractsWithMasterData;
```

new_string:

```php
use Tests\Support\InteractsWithContent;
use Tests\Support\InteractsWithFacilities;
use Tests\Support\InteractsWithMasterData;
```

old_string:

```php
use App\Models\Media;
use App\Models\User;
```

new_string:

```php
use App\Enums\ContactMessageStatus;
use App\Models\Media;
use App\Models\User;
```

old_string:

```php
 * 15 §3.1 baris 1 sampai 8, 16 sampai 21 (+ detail album dan privasi) dan §3.3 baris 31, 31b, 32, 33, 33b, 34, 35, 35b,
 * 35d, 39 × lima aktor (AKS-01 sampai AKS-05),
```

new_string:

```php
 * 15 §3.1 baris 1 sampai 22 (+ detail album, empat slug kamar, dan privasi) dan §3.3 baris 31, 31b, 32, 33, 33b, 34, 35,
 * 35b, 35d, 36, 36b, 36c, 39, 39b × lima aktor (AKS-01 sampai AKS-05),
```

old_string:

```php
    use InteractsWithContent;
    use InteractsWithMasterData;
```

new_string:

```php
    use InteractsWithContent;
    use InteractsWithFacilities;
    use InteractsWithMasterData;
```

old_string:

```php
    private const CONTENT_SCREEN = ['L', '403', '200', '403', '200'];
```

new_string:

```php
    private const CONTENT_SCREEN = ['L', '403', '200', '403', '200'];

    /** Layar #36 kelola fasilitas dan kamar (`facilities.manage`): sama dengan konten, Petugas 403 (D-12). */
    private const FACILITY_SCREEN = ['L', '403', '200', '403', '200'];

    /** #36b daftar kamar (`facilities.manage` atau `rooms.update-availability`): Petugas ikut 200 (mode ketersediaan). */
    private const ROOM_LIST = ['L', '403', '200', '200', '200'];

    /** #39b kotak pesan (`messages.manage`): Admin Konten 403, Petugas dan Super Admin 200. */
    private const MESSAGE_SCREEN = ['L', '403', '403', '200', '200'];

    /** #36c PATCH ketersediaan (`rooms.update-availability`): Admin Konten, Petugas, dan Super Admin berhak. */
    private const AVAILABILITY = ['G', '403', 'OK', 'OK', 'OK'];

    /** PATCH status pesan (`messages.manage`): Petugas dan Super Admin berhak. */
    private const MESSAGE_STATUS = ['G', '403', '403', 'OK', 'OK'];
```

old_string:

```php
            '39 Mitra asuransi (ubah)' => ['admin.insurance-partners.edit', 'insurance-partner', self::CONTENT_SCREEN],
        ];
```

new_string:

```php
            '39 Mitra asuransi (ubah)' => ['admin.insurance-partners.edit', 'insurance-partner', self::CONTENT_SCREEN],
            '36 Fasilitas (daftar)' => ['admin.facilities.index', null, self::FACILITY_SCREEN],
            '36 Fasilitas (tambah)' => ['admin.facilities.create', null, self::FACILITY_SCREEN],
            '36 Fasilitas (ubah)' => ['admin.facilities.edit', 'facility', self::FACILITY_SCREEN],
            '36b Kamar dan kapasitas (daftar)' => ['admin.room-categories.index', null, self::ROOM_LIST],
            '36 Kategori kamar (tambah)' => ['admin.room-categories.create', null, self::FACILITY_SCREEN],
            '36 Kategori kamar (ubah)' => ['admin.room-categories.edit', 'room-category', self::FACILITY_SCREEN],
            '39b Kotak pesan (daftar)' => ['admin.messages.index', null, self::MESSAGE_SCREEN],
            '39b Kotak pesan (detail)' => ['admin.messages.show', 'message', self::MESSAGE_SCREEN],
        ];
```

old_string:

```php
            '8 Jadwal dokter' => ['schedules.index', null],
            '16 Info BPJS dan asuransi' => ['insurance', null],
```

new_string:

```php
            '8 Jadwal dokter' => ['schedules.index', null],
            '9 Daftar fasilitas' => ['facilities.index', null],
            '10 Detail fasilitas' => ['facilities.show', 'public-facility'],
            '11 Ringkasan kamar' => ['rooms.index', null],
            '12 Kamar BPJS Kelas 1' => ['rooms.show', 'public-room-bpjs-kelas-1'],
            '13 Kamar BPJS Kelas 2' => ['rooms.show', 'public-room-bpjs-kelas-2'],
            '14 Kamar BPJS Kelas 3' => ['rooms.show', 'public-room-bpjs-kelas-3'],
            '15 Kamar VIP' => ['rooms.show', 'public-room-vip'],
            '16 Info BPJS dan asuransi' => ['insurance', null],
```

old_string:

```php
            '21 FAQ' => ['faq', null],
            'Kebijakan privasi' => ['privacy', null],
```

new_string:

```php
            '21 FAQ' => ['faq', null],
            '22 Kontak dan lokasi' => ['contact', null],
            'Kebijakan privasi' => ['privacy', null],
```

old_string:

```php
            'DELETE mitra asuransi' => ['delete', 'admin.insurance-partners.destroy', 'insurance-partner', self::MANAGE],
        ];
```

new_string:

```php
            'DELETE mitra asuransi' => ['delete', 'admin.insurance-partners.destroy', 'insurance-partner', self::MANAGE],
            'POST fasilitas baru' => ['post', 'admin.facilities.store', null, self::MANAGE],
            'PUT ubah fasilitas' => ['put', 'admin.facilities.update', 'facility', self::MANAGE],
            'DELETE fasilitas' => ['delete', 'admin.facilities.destroy', 'facility', self::MANAGE],
            'POST kategori kamar baru' => ['post', 'admin.room-categories.store', null, self::MANAGE],
            'PUT ubah kategori kamar' => ['put', 'admin.room-categories.update', 'room-category', self::MANAGE],
            'DELETE kategori kamar' => ['delete', 'admin.room-categories.destroy', 'room-category', self::MANAGE],
            'PATCH ketersediaan kamar (36c)' => ['patch', 'admin.room-categories.availability', 'room-category', self::AVAILABILITY],
            'PATCH status pesan kontak' => ['patch', 'admin.messages.status', 'message', self::MESSAGE_STATUS],
        ];
```

old_string:

```php
            'public-gallery' => ['gallery' => $this->createGallery(['is_published' => true])->slug],
        };
```

new_string:

```php
            'public-gallery' => ['gallery' => $this->createGallery(['is_published' => true])->slug],
            'facility' => ['facility' => $this->createFacility()->id],
            'room-category' => ['roomCategory' => $this->createRoomCategory()->id],
            'message' => ['message' => $this->createContactMessage()->id],
            'public-facility' => ['facility' => $this->createFacility(['is_active' => true])->slug],
            'public-room-bpjs-kelas-1' => $this->publicRoomParameters('bpjs-kelas-1', 'Kamar BPJS Kelas 1'),
            'public-room-bpjs-kelas-2' => $this->publicRoomParameters('bpjs-kelas-2', 'Kamar BPJS Kelas 2'),
            'public-room-bpjs-kelas-3' => $this->publicRoomParameters('bpjs-kelas-3', 'Kamar BPJS Kelas 3'),
            'public-room-vip' => $this->publicRoomParameters('vip', 'Kamar VIP/Pribadi'),
        };
```

Empat tes baru dan satu pembantu, disisipkan sebelum pembantu `assertGuestRejected` (`old_string` cocok tepat satu kali):

old_string:

```php
    private function assertGuestRejected(int $status, string $location): void
```

new_string:

```php
    public function test_patient_is_forbidden_from_admin_facility_room_and_message_urls(): void
    {
        $patient = User::factory()->patient()->create();
        $room = $this->createRoomCategory();
        $message = $this->createContactMessage();

        foreach (['/admin/fasilitas', '/admin/kamar', '/admin/pesan'] as $url) {
            $this->actingAs($patient)->get($url)->assertForbidden();
        }

        $this->actingAs($patient)->post('/admin/fasilitas')->assertForbidden();
        $this->actingAs($patient)->post('/admin/kamar')->assertForbidden();
        $this->actingAs($patient)->patch(route('admin.room-categories.availability', $room))->assertForbidden();
        $this->actingAs($patient)->patch(route('admin.messages.status', $message))->assertForbidden();
    }

    /** AKS-05 dan D-12: Petugas hanya mendapat mode ketersediaan; kelola struktur kamar dan fasilitas 403. */
    public function test_registration_officer_only_gets_the_availability_mode_of_the_room_screen(): void
    {
        $officer = $this->createStaff('registration-officer');
        $room = $this->createRoomCategory(['bed_capacity' => 10, 'beds_available' => 3]);

        $this->actingAs($officer)->get('/admin/fasilitas')->assertForbidden();
        $this->actingAs($officer)->get('/admin/kamar')->assertOk();
        $this->actingAs($officer)->get('/admin/kamar/buat')->assertForbidden();
        $this->actingAs($officer)->get(route('admin.room-categories.edit', $room))->assertForbidden();
        $this->actingAs($officer)->delete(route('admin.room-categories.destroy', $room))->assertForbidden();

        $this->actingAs($officer)
            ->patch(route('admin.room-categories.availability', $room), ['beds_available' => 1, 'availability_for' => $room->id])
            ->assertRedirect(route('admin.room-categories.index'));

        $this->assertSame(1, $room->fresh()->beds_available);
        $this->assertDatabaseHas('room_categories', ['id' => $room->id]);
    }

    /** Review Focus 5 dan F4B-R39: Admin Konten tidak memegang `messages.manage`. */
    public function test_content_admin_cannot_open_or_change_the_message_inbox(): void
    {
        $admin = $this->createStaff('content-admin');
        $message = $this->createContactMessage();

        $this->actingAs($admin)->get('/admin/pesan')->assertForbidden();
        $this->actingAs($admin)->get(route('admin.messages.show', $message))->assertForbidden();
        $this->actingAs($admin)->patch(route('admin.messages.status', $message), ['status' => 'archived'])->assertForbidden();

        $this->assertSame(ContactMessageStatus::New, $message->fresh()->status);
    }

    /** AKS-04 dan F4B-R39: layar #36 tidak mengueri data pasien, pendaftaran, atau pesan kontak. */
    public function test_facility_and_room_screens_never_query_patient_data_or_messages(): void
    {
        $admin = $this->createStaff('content-admin');

        $screens = [
            ['admin.facilities.index', []],
            ['admin.facilities.create', []],
            ['admin.facilities.edit', ['facility' => $this->createFacility()->id]],
            ['admin.room-categories.index', []],
            ['admin.room-categories.create', []],
            ['admin.room-categories.edit', ['roomCategory' => $this->createRoomCategory()->id]],
        ];

        foreach ($screens as [$name, $parameters]) {
            DB::flushQueryLog();
            DB::enableQueryLog();

            $this->actingAs($admin)->get(route($name, $parameters))->assertOk();

            foreach (DB::getQueryLog() as $entry) {
                $this->assertDoesNotMatchRegularExpression(
                    '/\b(patient_profiles|appointments|appointment_status_histories|contact_messages)\b/',
                    $entry['query'],
                    "{$name} mengueri data pasien atau pesan: {$entry['query']}",
                );
            }
        }

        DB::disableQueryLog();
    }

    /** @return array<string, string> */
    private function publicRoomParameters(string $slug, string $name): array
    {
        return ['roomCategory' => $this->createRoomCategory(['name' => $name, 'slug' => $slug, 'is_active' => true])->slug];
    }

    private function assertGuestRejected(int $status, string $location): void
```

- [ ] **Step 3: Tulis tes (tool Write) `tests/Feature/Access/Phase4bRoutePermissionTest.php`**

```php
<?php

namespace Tests\Feature\Access;

use App\Support\NavigationMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * KNS-01 (bagian fase 4b), KNS-02 (route admin berizin), KNS-03 (`facilities.manage`, `rooms.update-availability`,
 * `messages.manage`), dan bagian route dari CRD-13: 16 nama route admin 4b, izin, nama parameter, URI Indonesia,
 * enam route publik terbuka, serta route yang sengaja tidak ada.
 */
class Phase4bRoutePermissionTest extends TestCase
{
    use InteractsWithStaff;
    use RefreshDatabase;

    private const FACILITIES = 'permission:facilities.manage';

    private const ROOMS_VIEW = 'permission:facilities.manage|rooms.update-availability';

    private const AVAILABILITY = 'permission:rooms.update-availability';

    private const MESSAGES = 'permission:messages.manage';

    /** @return array<string, array{string, string}> */
    public static function permissions(): array
    {
        $rows = [];

        foreach (['index', 'create', 'store', 'edit', 'update', 'destroy'] as $action) {
            $rows["admin.facilities.{$action}"] = self::FACILITIES;
        }

        foreach (['create', 'store', 'edit', 'update', 'destroy'] as $action) {
            $rows["admin.room-categories.{$action}"] = self::FACILITIES;
        }

        $rows['admin.room-categories.index'] = self::ROOMS_VIEW;
        $rows['admin.room-categories.availability'] = self::AVAILABILITY;

        foreach (['index', 'show', 'status'] as $action) {
            $rows["admin.messages.{$action}"] = self::MESSAGES;
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

        $middleware = array_filter($route->gatherMiddleware(), 'is_string');
        $this->assertContains($permission, $middleware, "Route {$name} harus memakai {$permission}.");
        $this->assertContains('auth', $middleware, "Route {$name} harus berada di grup admin yang memakai auth.");
        $this->assertCount(1, array_filter($middleware, fn (string $item): bool => str_starts_with($item, 'permission:')), "Route {$name} hanya boleh memakai satu middleware izin.");
    }

    public function test_the_phase_4b_admin_route_names_are_exactly_the_sixteen_documented_ones(): void
    {
        $names = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $name = (string) $route->getName();

            if (preg_match('/^admin\.(facilities|room-categories|messages)\./', $name) === 1) {
                $names[] = $name;
            }
        }

        sort($names);

        $this->assertSame(16, count($names));
        $this->assertSame(array_values(array_unique($names)), $names, 'Nama route tidak boleh ganda.');
        $this->assertEqualsCanonicalizing(array_keys(self::permissions()), $names);
    }

    public function test_the_admin_area_has_one_hundred_and_six_routes_after_phase_4b(): void
    {
        $count = 0;

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if ($route->uri() === 'admin' || str_starts_with($route->uri(), 'admin/')) {
                $count++;
            }
        }

        // 31 fase 1-2 + 19 fase 3 + 40 fase 4a + 16 fase 4b (F4B-R13); fase 5 dan seterusnya wajib memperbarui angka ini.
        $this->assertSame(106, $count);
    }

    public function test_route_parameters_are_named_as_documented(): void
    {
        $expected = [
            'admin.facilities.edit' => ['facility'],
            'admin.facilities.update' => ['facility'],
            'admin.facilities.destroy' => ['facility'],
            'admin.room-categories.edit' => ['roomCategory'],
            'admin.room-categories.update' => ['roomCategory'],
            'admin.room-categories.destroy' => ['roomCategory'],
            'admin.room-categories.availability' => ['roomCategory'],
            'admin.messages.show' => ['message'],
            'admin.messages.status' => ['message'],
        ];

        foreach ($expected as $name => $parameters) {
            $this->assertSame($parameters, Route::getRoutes()->getByName($name)->parameterNames(), $name);
        }
    }

    public function test_messages_have_no_destroy_create_edit_or_update_routes_and_facilities_and_rooms_have_no_show(): void
    {
        foreach (['admin.messages.destroy', 'admin.messages.create', 'admin.messages.store', 'admin.messages.edit', 'admin.messages.update', 'admin.facilities.show', 'admin.room-categories.show'] as $name) {
            $this->assertNull(Route::getRoutes()->getByName($name), "{$name} tidak boleh ada (00 C, 00 I.6).");
        }

        $this->delete('/admin/pesan/1')->assertStatus(405);
        $this->put('/admin/pesan/1')->assertStatus(405);
        $this->post('/admin/pesan')->assertStatus(405);
        $this->get('/admin/fasilitas/1')->assertStatus(405);
        $this->get('/admin/kamar/1')->assertStatus(405);
    }

    public function test_methods_of_the_non_resource_routes(): void
    {
        $this->assertSame(['PATCH'], Route::getRoutes()->getByName('admin.room-categories.availability')->methods());
        $this->assertSame(['PATCH'], Route::getRoutes()->getByName('admin.messages.status')->methods());
        $this->assertSame(['GET', 'HEAD'], Route::getRoutes()->getByName('admin.room-categories.index')->methods());
        $this->assertSame(['GET', 'HEAD'], Route::getRoutes()->getByName('admin.messages.index')->methods());
        $this->assertSame(['GET', 'HEAD'], Route::getRoutes()->getByName('admin.messages.show')->methods());
    }

    public function test_uris_use_indonesian_segments(): void
    {
        $this->assertSame('/admin/fasilitas', route('admin.facilities.index', [], false));
        $this->assertSame('/admin/fasilitas/buat', route('admin.facilities.create', [], false));
        $this->assertSame('/admin/fasilitas/4/ubah', route('admin.facilities.edit', 4, false));
        $this->assertSame('/admin/fasilitas/4', route('admin.facilities.update', 4, false));
        $this->assertSame('/admin/kamar', route('admin.room-categories.index', [], false));
        $this->assertSame('/admin/kamar/buat', route('admin.room-categories.create', [], false));
        $this->assertSame('/admin/kamar/5/ubah', route('admin.room-categories.edit', 5, false));
        $this->assertSame('/admin/kamar/5/ketersediaan', route('admin.room-categories.availability', 5, false));
        $this->assertSame('/admin/pesan', route('admin.messages.index', [], false));
        $this->assertSame('/admin/pesan/7', route('admin.messages.show', 7, false));
        $this->assertSame('/admin/pesan/7/status', route('admin.messages.status', 7, false));
    }

    public function test_static_segments_are_matched_before_parameterised_routes(): void
    {
        $routes = Route::getRoutes();

        $this->assertSame('admin.facilities.create', $routes->match(Request::create('/admin/fasilitas/buat', 'GET'))->getName());
        $this->assertSame('admin.room-categories.create', $routes->match(Request::create('/admin/kamar/buat', 'GET'))->getName());
        $this->assertSame('admin.room-categories.availability', $routes->match(Request::create('/admin/kamar/7/ketersediaan', 'PATCH'))->getName());
        $this->assertSame('admin.room-categories.destroy', $routes->match(Request::create('/admin/kamar/7', 'DELETE'))->getName());
        $this->assertSame('admin.facilities.update', $routes->match(Request::create('/admin/fasilitas/7', 'PUT'))->getName());
        $this->assertSame('admin.messages.status', $routes->match(Request::create('/admin/pesan/7/status', 'PATCH'))->getName());
    }

    public function test_admin_ids_must_be_numeric(): void
    {
        $admin = $this->createStaff('super-admin');

        $this->actingAs($admin)->get('/admin/fasilitas/abc/ubah')->assertNotFound();
        $this->actingAs($admin)->get('/admin/kamar/abc/ubah')->assertNotFound();
        $this->actingAs($admin)->get('/admin/pesan/abc')->assertNotFound();
        $this->actingAs($admin)->patch('/admin/kamar/abc/ketersediaan')->assertNotFound();
        $this->actingAs($admin)->patch('/admin/pesan/abc/status')->assertNotFound();
    }

    /** @return array<string, array{string, string, list<string>}> */
    public static function publicRoutes(): array
    {
        return [
            'facilities.index' => ['facilities.index', 'fasilitas', ['GET', 'HEAD']],
            'facilities.show' => ['facilities.show', 'fasilitas/{facility}', ['GET', 'HEAD']],
            'rooms.index' => ['rooms.index', 'kamar-rawat-inap', ['GET', 'HEAD']],
            'rooms.show' => ['rooms.show', 'kamar-rawat-inap/{roomCategory}', ['GET', 'HEAD']],
            'contact' => ['contact', 'kontak', ['GET', 'HEAD']],
            'contact.store' => ['contact.store', 'kontak', ['POST']],
        ];
    }

    /**
     * @param  list<string>  $methods
     */
    #[DataProvider('publicRoutes')]
    public function test_public_routes_are_open_without_auth_or_permission_middleware(string $name, string $uri, array $methods): void
    {
        $route = Route::getRoutes()->getByName($name);

        $this->assertNotNull($route, "Route {$name} belum terdaftar.");
        $this->assertSame($methods, $route->methods());
        $this->assertSame($uri, $route->uri());

        foreach (array_filter($route->gatherMiddleware(), 'is_string') as $middleware) {
            $this->assertStringNotContainsString('permission:', $middleware, "{$name}: {$middleware}");
            $this->assertStringNotContainsString('auth', $middleware, "{$name}: {$middleware}");
            $this->assertStringNotContainsString('account:', $middleware, "{$name}: {$middleware}");
        }
    }

    public function test_facility_and_room_detail_routes_bind_by_slug(): void
    {
        $this->assertSame('slug', Route::getRoutes()->getByName('facilities.show')->bindingFieldFor('facility'));
        $this->assertSame('slug', Route::getRoutes()->getByName('rooms.show')->bindingFieldFor('roomCategory'));
    }

    public function test_only_the_contact_form_submission_is_throttled(): void
    {
        $store = array_filter(Route::getRoutes()->getByName('contact.store')->gatherMiddleware(), 'is_string');
        $this->assertContains('throttle:contact', $store);

        foreach (['contact', 'facilities.index', 'facilities.show', 'rooms.index', 'rooms.show'] as $name) {
            $middleware = array_filter(Route::getRoutes()->getByName($name)->gatherMiddleware(), 'is_string');
            $this->assertNotContains('throttle:contact', $middleware, $name);
        }
    }

    public function test_public_uris_use_indonesian_segments(): void
    {
        $this->assertSame('/fasilitas', route('facilities.index', [], false));
        $this->assertSame('/fasilitas/musala', route('facilities.show', 'musala', false));
        $this->assertSame('/kamar-rawat-inap', route('rooms.index', [], false));
        $this->assertSame('/kamar-rawat-inap/vip', route('rooms.show', 'vip', false));
        $this->assertSame('/kontak', route('contact', [], false));
        $this->assertSame('/kontak', route('contact.store', [], false));
    }

    public function test_the_facilities_module_tabs_follow_the_documented_configuration(): void
    {
        $tabs = config('navigation.modules.facilities');

        $this->assertSame(['admin.room-categories.index', 'admin.facilities.index'], array_column($tabs, 'route'));
        $this->assertSame(['Kamar dan Kapasitas', 'Fasilitas'], array_column($tabs, 'label'));
        $this->assertSame(['facilities.manage|rooms.update-availability', 'facilities.manage'], array_column($tabs, 'permission'));
        $this->assertSame([['admin.room-categories.*'], ['admin.facilities.*']], array_column($tabs, 'active'));
    }

    public function test_the_facilities_tabs_follow_the_user_permissions(): void
    {
        $this->assertSame(['Kamar dan Kapasitas', 'Fasilitas'], array_column(NavigationMenu::items('modules.facilities', $this->createStaff('content-admin')), 'label'));
        $this->assertSame(['Kamar dan Kapasitas', 'Fasilitas'], array_column(NavigationMenu::items('modules.facilities', $this->createStaff('super-admin')), 'label'));
        $this->assertSame(['Kamar dan Kapasitas'], array_column(NavigationMenu::items('modules.facilities', $this->createStaff('registration-officer')), 'label'));
        $this->assertSame([], NavigationMenu::items('modules.facilities', null));
    }

    public function test_the_message_inbox_is_not_a_module_tab(): void
    {
        foreach (config('navigation.modules') as $group => $tabs) {
            $this->assertNotContains('admin.messages.index', array_column($tabs, 'route'), $group);
        }
    }
}
```

- [ ] **Step 4: Jalankan tes, harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter='AdminPageMatrixTest|Phase4bRoutePermissionTest|Phase4aRoutePermissionTest|Phase3RoutePermissionTest|Phase2RoutePermissionTest|AdminRouteMiddlewareTest|PublicDoctorPagesTest'
```
Expected: PASS. Ini tes karakterisasi: route, izin, dan halaman sudah dibangun Task 4–9, jadi tidak ada fase "gagal dulu". Bila satu baris matriks gagal, perbaiki route atau izinnya (Task 4–9), jangan melonggarkan tes. Bila `test_facility_and_room_screens_never_query_patient_data_or_messages` menemukan kueri ke tabel pasien atau `contact_messages`, hapus kueri itu dari layar #36 (F4B-R39), bukan dari tes. Bila `route admin` bukan 106, periksa blok route 4b di `routes/admin.php` (penanda `// Task 5` dan `// Task 6` sudah harus terganti). Bila matriks publik `rooms.show` untuk salah satu slug seed menjawab 404, pastikan `createRoomCategory()` menerima `slug` dan `is_active` sebagai atribut.

- [ ] **Step 5: Pint dan commit matriks**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor\bin\pint
& $PHP vendor\bin\pint --test
git add tests/Feature/Access/AdminPageMatrixTest.php tests/Feature/Access/Phase4bRoutePermissionTest.php
git commit -m @'
test(fase-4b): matriks akses halaman publik 4b dan layar #36/#39b, mutasi 4b, dan Phase4bRoutePermissionTest

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
'@
```
Expected: Pint `PASS`; commit tercatat.

**(b) Rekonsiliasi blueprint**

Daftar edit (ID = keputusan fase 4b). Keputusan tanpa konflik teks langsung (F4B-R2 migration, R14 route publik, R15 policy, R21 media, R27 `x-form.file`, R28 tab modul, R29–R30 susunan halaman, R32 halaman kontak, R37 tes lama, R38 trailer, R40) tidak mengubah teks blueprint; yang menyimpang dicatat sebagai baris D di 17.

| Berkas dan bagian | Ruling | Isi perubahan |
|---|---|---|
| 00 J.6 | nama baru | Bullet "Nama tambahan fase 4b" |
| 05 §4.3 (layar #36) | F4B-R20 | Hapus kategori kamar selalu boleh (bukan "hanya bila tidak dirujuk") |
| 06 §13, §17 | F4B-R6, R7, R9 | Throttle kontak 302, honeypot, tanpa status "Terbatas" |
| 07 W-02, W-07 | F4B-R9, R11 | Kalimat peringatan kamar tanpa kata "real-time" |
| 11 §2.3, §4.1 | F4B-R16, R22, R23 | Filter `FacilityIndexRequest` dan `ContactMessageIndexRequest`; daftar kamar tanpa filter |
| 12 V-27, V-28, V-37, V-46 | F4B-R3, R7, R22, R23 | Batas rich text 30.000, honeypot, kelas filter admin |
| 13 §11.2 | F4B-R36 | Angka acuan media 83 dan jumlah 4b |
| 17 §4 | semua yang menyimpang | Baris D-124 s.d. D-139 |

- [ ] **Step 6: 00 (nama baru fase 4b)**

`docs/superpowers/specs/2026-10-02-unpam-hospital-blueprint/00-keputusan-inti.md` (tool Edit; `old_string` cocok tepat satu kali):

old_string:

```markdown
- **Pengujian:** folder `tests/Feature/Access`, `tests/Feature/Seeding`
```

new_string:

```markdown
- **Nama tambahan fase 4b:** model `Facility`, `RoomCategory` (konstanta `STALE_AFTER_HOURS` = 24), dan `ContactMessage` beserta factory (state `Facility`: `category()`, `inactive()`; `RoomCategory`: `full()`, `inactive()`, `withoutRate()`; `ContactMessage`: `read()`, `replied()`, `archived()`); scope `Facility::scopeActive|Ordered|OfCategory`, `RoomCategory::scopeActive|Ordered`, `ContactMessage::scopeUnread`; accessor `cover_url`, `seo_title`, `seo_description` pada `Facility` dan `RoomCategory`, serta `occupancy_label`, `availability_updated_label`, `is_availability_stale`, `is_full`, `sample_rate_label` pada `RoomCategory`; relasi `RoomCategory::availabilityUpdater()` dan `ContactMessage::handler()`; `Support\LikePattern::contains()` (menggantikan sembilan salinan escape LIKE, F4B-R26); `Rules\IndonesianPhone` (+ `::normalize()`, konstanta `PATTERN` dan `MESSAGE`); komponen `x-map-embed` (`App\View\Components\MapEmbed`) dan fungsi JS `initMapEmbeds()`; prop `placeholder` pada `x-form.file`; kunci tab modul `modules.facilities`; kontrol admin `FacilityController`, `RoomCategoryController` (+ `availability`), `ContactMessageController` (`index`, `show`, `status`) dengan Form Request `FacilityRequest`, `RoomCategoryRequest`, `UpdateRoomAvailabilityRequest`, `UpdateContactMessageStatusRequest` (konstanta `MAX_GALLERY` = 20; method `facilityAttributes()`, `roomCategoryAttributes()`, `coverFile()`, `coverAlt()`, `galleryFiles()`, `galleryAlt()`; `RoomCategoryRequest::belowAvailableMessage()` dan `::initialBedsAvailable()`; `UpdateRoomAvailabilityRequest::exceedsCapacityMessage()` dan `::bedsAvailable()`; `UpdateContactMessageStatusRequest::statusValue()`), filter `FacilityIndexRequest` dan `ContactMessageIndexRequest` (konstanta `STATUS_MAP`; `applyTo()`), dan Policy `FacilityPolicy`, `RoomCategoryPolicy` (+ `updateAvailability`), `ContactMessagePolicy`; publik `Public\FacilityController`, `Public\RoomCategoryController`, `Public\ContactController` (+ konstanta `Public\ContactController::SUCCESS_MESSAGE`, `Public\StoreContactMessageRequest::isHoneypotFilled()` dan `::messageAttributes()`), konstanta `Public\HomeController::FEATURED_ROOMS` = 4; partial `public/rooms/_availability-badge`, `public/rooms/_notice`, `public/partials/_room-summary`, `public/facilities/_card`; seeder `RoomCategorySeeder` (konstanta `ROOMS`, `UPDATER_EMAIL`, method `description()`), `FacilitySeeder` (`FACILITIES`, `description()`), dan `ContactMessageSeeder` (`MESSAGES`, `HANDLER_EMAIL`); pembantu tes `Tests\Support\InteractsWithFacilities`; kelas tes `FacilitySchemaTest`, `FacilityModelTest`, `RoomCategoryModelTest`, `ContactMessageModelTest`, `FacilitySeedersTest`, `Phase4bRoutePermissionTest`.
- **Pengujian:** folder `tests/Feature/Access`, `tests/Feature/Seeding`
```

Verifikasi (PowerShell):

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; Set-Location $APP
$SPEC = "docs\superpowers\specs\2026-10-02-unpam-hospital-blueprint"
(Select-String -Path "$SPEC\00-keputusan-inti.md" -Pattern 'Nama tambahan fase 4b').Count
(Select-String -Path "$SPEC\00-keputusan-inti.md" -Pattern 'Nama tambahan fase 4a').Count
```
Expected: `1` dan `1`.

- [ ] **Step 7: 06 dan 07 (throttle, honeypot, badge, kalimat peringatan)**

`docs/superpowers/specs/2026-10-02-unpam-hospital-blueprint/06-alur.md` (tool Edit; setiap `old_string` cocok tepat satu kali):

old_string:

```markdown
Kolom jebakan (*honeypot*) tersembunyi `website` **[ASUMSI]**: bila terisi, controller menampilkan sukses palsu tanpa menyimpan.
```

new_string:

```markdown
Kolom jebakan (*honeypot*) tersembunyi `website` (di dalam `<div class="d-none" aria-hidden="true">`, `tabindex="-1"`, `autocomplete="off"`; tidak ada di `rules()`): bila terisi, controller mengalihkan ke `contact` dengan flash sukses yang sama tanpa menyimpan apa pun (F4B-R7).
```

old_string:

```markdown
| Throttle `contact` terlampaui | 429 "Terlalu banyak pesan dikirim. Silakan coba lagi dalam {menit} menit." |
```

new_string:

```markdown
| Throttle `contact` terlampaui | Redirect 302 kembali ke `/kontak` dengan flash `error` "Terlalu banyak percobaan. Silakan coba lagi dalam {n} detik.", header `Retry-After`, input lama dipertahankan, tanpa baris tersimpan (D-23, F4B-R6) |
```

old_string:

```markdown
Status ringkas **[ASUMSI]**: `Penuh` bila 0, `Terbatas` bila ≤ 20% kapasitas, selain itu `Tersedia`; label "Data mungkin tidak terkini" bila pembaruan terakhir > 24 jam.
```

new_string:

```markdown
Status ringkas hanya dua badge: `Tersedia` bila `beds_available > 0` dan `Penuh` bila 0, masing-masing berisi teks, ikon, dan warna (status `Terbatas` tidak dibuat, F4B-R9); kalimat "Data mungkin tidak terkini. Hubungi rumah sakit untuk memastikan ketersediaan." tampil bila `availability_updated_at` kosong atau lebih tua dari 24 jam (F4B-R10).
```

old_string:

```markdown
18. **[ASUMSI]** Status ringkas kamar `Penuh`/`Terbatas` (≤ 20%)/`Tersedia` dan label "Data mungkin tidak terkini" bila pembaruan > 24 jam.
```

new_string:

```markdown
18. Status ringkas kamar hanya `Penuh`/`Tersedia` (status `Terbatas` ≤ 20% tidak dibuat, F4B-R9) dan kalimat "Data mungkin tidak terkini" bila pembaruan kosong atau > 24 jam (F4B-R10).
```

`docs/superpowers/specs/2026-10-02-unpam-hospital-blueprint/07-wireframe.md` (tool Edit; setiap `old_string` cocok tepat satu kali; lakukan berurutan):

old_string (satu baris penuh, 80 kolom, 18 spasi sebelum `|` penutup):

```markdown
| bukan data real-time. Hubungi rumah sakit untuk memastikan.                  |
```

new_string (satu baris penuh, tetap 80 kolom: 27 spasi sebelum `|` penutup, supaya garis tepi kanan kotak ASCII tidak bergeser):

```markdown
| Hubungi rumah sakit untuk memastikan ketersediaan.                           |
```

old_string:

```markdown
6. **Ringkasan ketersediaan kamar** (ditunda ke fase 4b, F4A-R1): satu kartu kecil per kategori aktif (nama, “N dari M”, badge Tersedia/Penuh) + kalimat **“Diperbarui: {tanggal-jam}”** dan penjelasan bahwa data diperbarui manual dan bukan real-time (`availability_updated_at` paling lama dari kategori; bila kosong “Belum diperbarui”).
```

new_string:

```markdown
6. **Ringkasan ketersediaan kamar** (fase 4b, F4B-R31): satu kartu kecil per kategori aktif (maksimal 4; nama sebagai tautan `rooms.show`, “N dari M tempat tidur tersedia”, badge Tersedia/Penuh, dan label “Diperbarui manual oleh petugas pada {tanggal}, pukul {jam} WIB” atau “Belum diperbarui”) + kalimat pendek “Diperbarui manual oleh petugas. Hubungi rumah sakit untuk memastikan ketersediaan.” tanpa kata “real-time” (F4B-R11); tautan “Lihat detail kamar” ke `rooms.index`; blok disembunyikan bila tidak ada kategori aktif.
```

old_string:

```markdown
| | (i) Ketersediaan diperbarui manual oleh petugas, bukan data real-time.   | |
```

new_string:

```markdown
| | (i) Ketersediaan tempat tidur diperbarui manual oleh petugas.            | |
```

old_string:

```markdown
| 09.30 WIB (manual, bukan   | |
```

new_string:

```markdown
| 09.30 WIB (diperbarui      | |
```

old_string:

```markdown
| real-time)                 | |
```

new_string:

```markdown
| manual)                    | |
```

old_string:

```markdown
1. Kalimat peringatan (ringkasan dan detail): “Ketersediaan diperbarui manual oleh petugas, bukan data real-time. Nama dan klasifikasi kategori adalah kebutuhan prototipe, bukan ketentuan BPJS yang sudah diverifikasi. Tarif adalah contoh.”
```

new_string:

```markdown
1. Kalimat peringatan (ringkasan dan detail, kotak `alert alert-info` berikon `bi-info-circle`): “Ketersediaan tempat tidur diperbarui manual oleh petugas dan dapat berubah sewaktu-waktu. Hubungi rumah sakit untuk memastikan. Nama dan klasifikasi kategori adalah kebutuhan prototipe, bukan ketentuan BPJS yang sudah diverifikasi. Tarif adalah contoh.” (F4B-R11; kata “real-time” tidak dipakai).
```

Diagram alur 06 (tool Edit; `old_string` cocok tepat satu kali) disesuaikan dengan D-127 (302, bukan 429):

old_string:

```markdown
    C -->|"Tidak"| C1["429 Terlalu banyak pesan"]
```

new_string:

```markdown
    C -->|"Tidak"| C1["302 kembali ke /kontak: Terlalu banyak percobaan (D-127)"]
```

Verifikasi:

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; Set-Location $APP
$SPEC = "docs\superpowers\specs\2026-10-02-unpam-hospital-blueprint"
(Select-String -Path "$SPEC\06-alur.md" -Pattern 'F4B-R').Count
(Select-String -Path "$SPEC\06-alur.md" -Pattern '429 Terlalu banyak pesan').Count
(Select-String -Path "$SPEC\07-wireframe.md" -Pattern 'F4B-R').Count
(Select-String -Path "$SPEC\07-wireframe.md" -Pattern 'bukan data real-time').Count
```
Expected: `4` (06; edit diagram memakai "D-127", bukan "F4B-R"), `2` (07), `0` ("bukan data real-time" di 07), dan `0` ("429 Terlalu banyak pesan" di 06).

- [ ] **Step 8: 05, 11, 12, dan 13**

`docs/superpowers/specs/2026-10-02-unpam-hospital-blueprint/05-sitemap-matriks-halaman.md` (tool Edit; `old_string` cocok tepat satu kali; layar #36 baris "Kamar dan Kapasitas"):

old_string:

```markdown
Perbarui ketersediaan (inline), tambah, ubah, hapus (hanya bila tidak dirujuk) |
```

new_string:

```markdown
Perbarui ketersediaan (inline), tambah, ubah, hapus (selalu boleh; kategori kamar tidak dirujuk tabel lain, F4B-R20, D-135) |
```

`docs/superpowers/specs/2026-10-02-unpam-hospital-blueprint/11-route-controller-model-service.md` (tool Edit; setiap `old_string` cocok tepat satu kali):

old_string:

```markdown
| GET | `/admin/fasilitas` | `admin.facilities.index` | `FacilityController@index` | `permission:facilities.manage` | `ListFilterRequest` | `FacilityPolicy::viewAny` |
```

new_string:

```markdown
| GET | `/admin/fasilitas` | `admin.facilities.index` | `FacilityController@index` | `permission:facilities.manage` | `FacilityIndexRequest` (turunan `ListFilterRequest`: `status`, `kategori`, `urut`, `q`; F4B-R22) | `FacilityPolicy::viewAny` |
```

old_string:

```markdown
| GET | `/admin/kamar` | `admin.room-categories.index` | `RoomCategoryController@index` | `permission:facilities.manage\|rooms.update-availability` | `ListFilterRequest` | `RoomCategoryPolicy::viewAny` |
```

new_string:

```markdown
| GET | `/admin/kamar` | `admin.room-categories.index` | `RoomCategoryController@index` | `permission:facilities.manage\|rooms.update-availability` | — (semua kategori urut `ordered()`, tanpa filter dan paginasi; F4B-R16) | `RoomCategoryPolicy::viewAny` |
```

old_string:

```markdown
| GET | `/admin/pesan` | `admin.messages.index` | `ContactMessageController@index` | `permission:messages.manage` | `ListFilterRequest` | `ContactMessagePolicy::viewAny` |
```

new_string:

```markdown
| GET | `/admin/pesan` | `admin.messages.index` | `ContactMessageController@index` | `permission:messages.manage` | `ContactMessageIndexRequest` (turunan `ListFilterRequest`: `status`, `q`; urutan tetap terbaru lebih dulu; F4B-R23) | `ContactMessagePolicy::viewAny` |
```

`docs/superpowers/specs/2026-10-02-unpam-hospital-blueprint/12-aturan-bisnis-validasi-keamanan.md` (tool Edit; setiap `old_string` cocok tepat satu kali):

old_string:

```markdown
| `description` (rich text) | `nullable` `string` `max:50000`; disanitasi `HtmlSanitizer` | "Deskripsi maksimal 50.000 karakter." |
```

new_string:

```markdown
| `description` (rich text) | `nullable` `string` `max:30000`; disanitasi `HtmlSanitizer` (30.000 menggantikan 50.000 karena `TEXT` 65.535 byte; F4B-R3, 09 §1 butir 10) | "Deskripsi maksimal 30.000 karakter." |
```

old_string:

```markdown
| `description` (rich text) | `nullable` `string` `max:50000`; disanitasi | "Deskripsi maksimal 50.000 karakter." |
```

new_string:

```markdown
| `description` (rich text) | `nullable` `string` `max:30000`; disanitasi (F4B-R3) | "Deskripsi maksimal 30.000 karakter." |
```

old_string:

```markdown
| `website` (kolom jebakan tersembunyi) | `nullable` `max:0`; bila terisi: controller menampilkan sukses palsu tanpa menyimpan | (tidak pernah menampilkan galat) |
```

new_string:

```markdown
| `website` (kolom jebakan tersembunyi) | tidak ada di `rules()` (F4B-R7); controller memeriksa `isHoneypotFilled()` dan, bila terisi, mengalihkan ke `contact` dengan flash sukses yang sama tanpa menyimpan | (tidak pernah menampilkan galat) |
```

old_string:

```markdown
| `AuditLogIndexRequest` | `admin.audit-logs.index` | `dari`, `sampai`, `pengguna`, `aksi`, `objek` |
```

new_string:

```markdown
| `FacilityIndexRequest` | `admin.facilities.index` | `status`, `kategori`, `urut`, `q` | `status` ∈ `aktif`/`nonaktif`; `kategori` = `FacilityCategory`; `urut` ∈ `urutan`/`nama`; `q` pada `name` (`LikePattern::contains()`); 25 per halaman (F4B-R22) |
| `ContactMessageIndexRequest` | `admin.messages.index` | `status`, `q` | `status` ∈ `baru`/`dibaca`/`dibalas`/`diarsipkan` (dipetakan `STATUS_MAP`); `q` pada `name`, `email`, `subject`; tanpa `urut` (terbaru lebih dulu); 25 per halaman (F4B-R23) |
| `AuditLogIndexRequest` | `admin.audit-logs.index` | `dari`, `sampai`, `pengguna`, `aksi`, `objek` |
```

`docs/superpowers/specs/2026-10-02-unpam-hospital-blueprint/13-data-contoh.md` (tool Edit; `old_string` cocok tepat satu kali):

old_string:

```markdown
SELECT COUNT(*) FROM media;                                         -- 57 setelah fase 4a (32 master + 25 konten: 6 cover artikel, 3 banner, 12 foto album, 4 logo; F4A-R27); fase 4b dan 9 menambah gambar fasilitas dan kamar
```

new_string:

```markdown
SELECT COUNT(*) FROM media;                                         -- 83 setelah fase 4b (57 fase 1-4a + 26: fasilitas 10 cover + 4 galeri, kamar 4 cover + 8 galeri; cover 36, gallery 12; F4B-R36)
SELECT COUNT(*) FROM room_categories;                               -- 4 (kapasitas 58, tersedia 16; Kelas 2 penuh); facilities 10 (2 medis, 3 penunjang, 5 umum); contact_messages 5 (2 new, 1 read, 1 replied, 1 archived)
```

Verifikasi:

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; Set-Location $APP
$SPEC = "docs\superpowers\specs\2026-10-02-unpam-hospital-blueprint"
foreach ($n in '11-route-controller-model-service', '12-aturan-bisnis-validasi-keamanan', '13-data-contoh') { "$n : " + (Select-String -Path "$SPEC\$n.md" -Pattern 'F4B-R').Count }
(Select-String -Path "$SPEC\12-aturan-bisnis-validasi-keamanan.md" -Pattern 'max:50000').Count
(Select-String -Path "$SPEC\05-sitemap-matriks-halaman.md" -Pattern 'F4B-R').Count
```
Expected: `11-route-controller-model-service : 3`, `12-aturan-bisnis-validasi-keamanan : 5`, `13-data-contoh : 1`, `0` untuk `max:50000`, dan `1` untuk `05-sitemap-matriks-halaman.md`.

- [ ] **Step 9: 17 §4 (baris D-124 s.d. D-139)**

`docs/superpowers/specs/2026-10-02-unpam-hospital-blueprint/17-keputusan-konfirmasi.md` (tool Edit; `old_string` cocok tepat satu kali pada keadaan berkas saat langkah ini dijalankan, yaitu setelah D-123):

old_string:

```markdown
| D-123 | Media dipertahankan pada soft delete | Hook `deleting` trait `HasMedia` menghapus media hanya bila model tidak memakai `SoftDeletes` atau sedang `forceDelete()`; cover artikel yang terhapus lunak tetap ada. | 09 §6.3 (F4A-R32) |
```

new_string:

```markdown
| D-123 | Media dipertahankan pada soft delete | Hook `deleting` trait `HasMedia` menghapus media hanya bila model tidak memakai `SoftDeletes` atau sedang `forceDelete()`; cover artikel yang terhapus lunak tetap ada. | 09 §6.3 (F4A-R32) |
| D-124 | Cakupan fase 4b | F-PUB-08, F-PUB-09, F-PUB-15, F-PUB-16, F-ADM-09, F-ADM-10, F-ADM-11, dan F-ADM-24: halaman publik #9–#15 dan #22, admin #36 (dua tab: Kamar dan Kapasitas, Fasilitas), kotak pesan, dan blok kamar beranda (ditunda dari 4a, D-112). Pengaturan situs (`settings.manage`, F-ADM-25), lencana angka "Pesan Kontak" di sidebar, dan widget dasbor kamar dan pesan tetap fase 8. Tiga migration memakai nama 09 §10 persis dan berjalan sebagai migration tertunda pada DB yang sudah memigrasikan fase 1–4a (pola D-59). | 14 §5, 09 §10 (F4B-R1, R2) |
| D-125 | Batas rich text fasilitas dan kamar | `description` memakai `max:30000` dengan pesan "Deskripsi maksimal 30.000 karakter.", sama dengan `ClinicRequest`; menyimpang dari 12 V-27/V-28 (`max:50000`) karena 50.000 karakter multibyte dapat melampaui `TEXT` 65.535 byte. | 09 §1 butir 10, 12 V-27, V-28 (F4B-R3) |
| D-126 | Panjang field dan slug fasilitas/kamar | Kolom mengikuti 09 dan aturan form mengikuti 12 yang lebih ketat (`name` maks 100, `short_description` maks 255); `slugMaxLength()` fasilitas 170 dan kamar 120. Form admin tidak punya input slug: `HasUniqueSlug` membuatnya dari `name` dan tidak mengubahnya saat nama diedit (BR-117); seeder mengisi slug eksplisit. | 12 V-27, V-28, BR-117, D-31 (F4B-R4, R5) |
| D-127 | Throttle form kontak | Mengikuti D-23: kiriman ke-4 dalam 10 menit dari IP yang sama dialihkan 302 kembali ke `/kontak` dengan flash `error` "Terlalu banyak percobaan. Silakan coba lagi dalam {n} detik.", header `Retry-After`, input lama dipertahankan, tanpa baris tersimpan. "429" pada SEK-14 dibaca sebagai respons ini; teks 06 §17 "Terlalu banyak pesan dikirim…" tidak dipakai. | D-23, 06 §17, 15 SEK-14 (F4B-R6) |
| D-128 | Honeypot dan teks flash kontak | Kolom `website` tersembunyi (`<div class="d-none" aria-hidden="true">`, `tabindex="-1"`, `autocomplete="off"`) tidak ada di `rules()`; controller memeriksa `isHoneypotFilled()` dan mengalihkan ke `contact` dengan flash sukses yang sama tanpa menyimpan. Flash sukses "Terima kasih. Pesan Anda telah kami terima dan akan ditindaklanjuti oleh petugas kami." (06 §17); teks W-08 tidak dipakai. Pesan persetujuan "Anda harus menyetujui pengolahan data untuk mengirim pesan." | 06 §17, 11 §5.5 (F4B-R7, R8) |
| D-129 | Badge ketersediaan dan kalimat peringatan kamar | Hanya dua badge: `beds_available > 0` = "Tersedia" (`text-bg-success`, `bi-check-circle`) dan 0 = "Penuh" (`text-bg-danger`, `bi-x-circle`), selalu teks + ikon + warna; status "Terbatas" (06 [ASUMSI]) tidak dibuat. Kalimat peringatan #11 dan #12–#15: "Ketersediaan tempat tidur diperbarui manual oleh petugas dan dapat berubah sewaktu-waktu. Hubungi rumah sakit untuk memastikan. Nama dan klasifikasi kategori adalah kebutuhan prototipe, bukan ketentuan BPJS yang sudah diverifikasi. Tarif adalah contoh."; kata "real-time" tidak dipakai (01, 02, 09 menang atas 07 W-07). | 05, 07, 01, 02 F-NXT-08 (F4B-R9, R11) |
| D-130 | Label ketersediaan dan tarif contoh | `occupancy_label` "{n} dari {m} tempat tidur tersedia"; `availability_updated_label` "Diperbarui manual oleh petugas pada {j M Y}, pukul {H.i} WIB" atau "Belum diperbarui" bila NULL; `is_availability_stale` benar bila NULL atau lebih tua dari `RoomCategory::STALE_AFTER_HOURS` (24) jam, dengan kalimat "Data mungkin tidak terkini. Hubungi rumah sakit untuk memastikan ketersediaan." (BR-114). `sample_rate_label` ditulis `'Rp '.number_format(…, 0, ',', '.')` dan satuan (tanpa `Number::currency`, agar tidak ada spasi tak putus), atau "Hubungi kami". | 11 §5.5, 12 BR-114, BR-116 (F4B-R10, R12) |
| D-131 | Blok kamar di beranda | Partial `public/partials/_room-summary` di antara dokter dan artikel; maksimal `HomeController::FEATURED_ROOMS` = 4 kategori aktif urut `ordered()`; nama bertaut `rooms.show`, `occupancy_label`, badge, label pembaruan, kalimat pendek "Diperbarui manual oleh petugas. Hubungi rumah sakit untuk memastikan ketersediaan."; disembunyikan bila tidak ada kategori aktif. | 05 hal. 1, 07 W-02 (F4B-R31) |
| D-132 | Route admin 4b dan daftar kamar | 16 route baru (fasilitas 6, kamar 7 termasuk `availability`, pesan 3) di G-ADMIN sebelum `// J. Media`; total route admin 106. `admin.room-categories.index` memakai `permission:facilities.manage\|rooms.update-availability`, tanpa paginasi dan tanpa filter, dengan form inline ketersediaan bagi pemegang `rooms.update-availability`; tombol Tambah/Ubah/Hapus hanya bagi pemegang `facilities.manage`, sehingga Petugas Pendaftaran melihat mode ketersediaan saja (D-12). Tab modul `modules.facilities`: "Kamar dan Kapasitas" lalu "Fasilitas". | 11 §2.3, 05 §4.3, D-12 (F4B-R13, R16, R28) |
| D-133 | Aksi ketersediaan dan kapasitas | `availability()` mengunci baris (`lockForUpdate`), mengisi `availability_updated_at`/`_by`, `saveQuietly()`, dan satu audit `room.availability_updated` (old/new `beds_available`); nilai sama dengan yang tersimpan tetap memperbarui waktu (konfirmasi petugas). Saat buat, `beds_available` ≤ `bed_capacity` dan waktu pembaruan diisi; saat ubah, `beds_available` tidak ada di `rules()` dan `bed_capacity` yang lebih kecil dari ketersediaan saat ini ditolak (BR-115). Mengubah kapasitas tidak mengubah `availability_updated_*` (06 §13 dan 11 menang atas 09 §5.1). | 06 §13, 09 §5.1, 12 V-28, V-29 (F4B-R17, R18) |
| D-134 | `amenities` sebagai textarea | Satu `textarea` "Fasilitas kamar (satu per baris)"; `prepareForValidation()` memecah per baris, `trim`, dan membuang baris kosong; V-28 (maks 30 butir, tiap butir 2–80 karakter, unik) berlaku pada larik hasil; larik kosong disimpan NULL. | 12 V-28, BR-118 (F4B-R19) |
| D-135 | Hapus fasilitas dan kategori kamar | Selalu boleh karena tidak dirujuk tabel lain (CRD-13): tanpa `deletionBlockedReason()`. Modal "Hapus kategori kamar?" menyarankan menonaktifkan; fasilitas dihapus permanen; gambar ikut terhapus. | 00 C, 12 BR-180 (F4B-R20) |
| D-136 | Filter admin 4b dan `LikePattern` | `FacilityIndexRequest` (status, kategori, urut, q; 25 per halaman) dan `ContactMessageIndexRequest` (status, q; terbaru dulu) memperluas `ListFilterRequest` dengan `applyTo(Builder)`. Escape LIKE dipusatkan di `App\Support\LikePattern::contains()` dan menggantikan sembilan salinan fase 2–4a; `AuditLog` (prefiks) dan `MediaService` (needle) tidak disentuh. | 11 §9.3, 12 V-46 (F4B-R22, R23, R26) |
| D-137 | Status pesan kontak, audit, dan privasi | Membuka pesan `new` mengubahnya menjadi `read` satu kali (audit `contact_message.status_changed`, tanpa `handled_*`); status diubah lewat `PATCH admin.messages.status` ke `read`/`replied`/`archived` (`new` ditolak); `replied` dan `archived` mengisi `handled_by`/`handled_at`; pulihkan (`read`) membiarkannya; status sama tanpa perubahan dan tanpa audit. `ContactMessage` tidak diobservasi `AuditObserver` dan deskripsi audit tidak memuat nama, email, subjek, atau isi pesan; `AuditObserver` hanya untuk `Facility` dan `RoomCategory`. Admin Konten tidak punya `messages.manage` dan layar #36 tidak mengueri `contact_messages`, `patient_profiles`, atau `appointments`. | 06 §17, 12 V-38, BR-175 (F4B-R24, R25, R39) |
| D-138 | Peta tersemat, telepon, dan penyimpanan pesan | `x-map-embed` selalu menampilkan fallback di belakang iframe; `initMapEmbeds()` hanya memasang listener `load` pada iframe (tanpa listener `error` baru, `FrontendManifestTest`) dan menandai `is-unavailable` bila `navigator.onLine === false` atau 8000 ms tanpa `load`. `IndonesianPhone::normalize()` membuang spasi, tanda hubung, titik, dan kurung; pola `IndonesianPhone::PATTERN` `/^(\+62\|62\|0)8[0-9]{7,12}$/` dengan pesan `IndonesianPhone::MESSAGE` "Nomor telepon tidak valid. Gunakan nomor seluler Indonesia, mis. 081234567890."; nomor tersimpan ternormalisasi (maks 16 karakter). Pesan tersimpan dengan `status = new`, `consent_at = now()`, dan `ip_hash = hash('sha256', ip . app.key)`. | 12 V-37, BR-141, 10 §3 (F4B-R33, R34, R35) |
| D-139 | Seeder demo 4b | `RoomCategorySeeder`, `FacilitySeeder`, dan `ContactMessageSeeder` hanya `local`/`testing` (melempar di luar itu), idempoten per slug atau per email dan subjek, dipanggil `DemoSeeder` sebagai `… ScheduleExceptionSeeder, RoomCategorySeeder, FacilitySeeder, ContentSeeder, ContactMessageSeeder`; data persis 13 §8.1, §8.2, §10.4 (kamar diperbarui 2 jam yang lalu oleh Petugas Pendaftaran Demo, Kelas 2 penuh); 26 gambar lokal sehingga media demo menjadi 83 (cover 36, gallery 12). | 13 §2.1, §8, §9.2, §11.2 (F4B-R36) |
```

Verifikasi:

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; Set-Location $APP
$SPEC = "docs\superpowers\specs\2026-10-02-unpam-hospital-blueprint"
Select-String -Path "$SPEC\17-keputusan-konfirmasi.md" -Pattern '^\| D-(124|139) \|' | ForEach-Object { $_.Line.Substring(0, 40) }
(Select-String -Path "$SPEC\17-keputusan-konfirmasi.md" -Pattern '^\| D-\d+ \|').Count
```
Expected: dua baris (`| D-124 |` dan `| D-139 |`); jumlah baris D-nn = `139`.

- [ ] **Step 10: Verifikasi seluruh pembaruan dokumen, lalu commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
$SPEC = "docs\superpowers\specs\2026-10-02-unpam-hospital-blueprint"
git diff --stat -- $SPEC
git status --short
git add "$SPEC\00-keputusan-inti.md" "$SPEC\05-sitemap-matriks-halaman.md" "$SPEC\06-alur.md" "$SPEC\07-wireframe.md" "$SPEC\11-route-controller-model-service.md" "$SPEC\12-aturan-bisnis-validasi-keamanan.md" "$SPEC\13-data-contoh.md" "$SPEC\17-keputusan-konfirmasi.md"
git commit -m @'
docs(fase-4b): rekonsiliasi blueprint dengan keputusan F4B-R1..R39 dan nama baru fase 4b

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
'@
```
Expected: `git diff --stat` menampilkan delapan berkas dokumen berubah (00, 05, 06, 07, 11, 12, 13, 17); `git status` hanya memuat berkas itu (dan `.superpowers/`) sebelum commit; commit dibuat.

**(c) README bagian "Fase 4b"**

- [ ] **Step 11: Tambahkan bagian Fase 4b ke `README.md` (tool Edit) dan commit**

`README.md` (tool Edit; `old_string` cocok tepat satu kali):

old_string:

````markdown
npm run build                            # carousel beranda dan gaya konten ikut dibundel
```
````

new_string:

````markdown
npm run build                            # carousel beranda dan gaya konten ikut dibundel
```

## Fase 4b — Fasilitas, kamar rawat inap, dan kontak

Yang tersedia (dengan fase ini kedua puluh dua halaman publik lengkap):
- **Fasilitas dan Kamar** di <http://unpam-hospital.test/admin/kamar> dengan dua tab: **Kamar dan Kapasitas** (`/admin/kamar`, termasuk pembaruan ketersediaan tempat tidur secara manual) dan **Fasilitas** (`/admin/fasilitas`).
- **Pesan Kontak** (kotak pesan) di `/admin/pesan`: daftar dengan pencarian dan filter status, serta detail pesan dengan tombol Tandai Dibalas, Arsipkan, dan Pulihkan. Aplikasi tidak mengirim balasan otomatis; balas manual lewat email atau telepon.
- Halaman publik **Fasilitas** (`/fasilitas`, `/fasilitas/{slug}`), **Kamar Rawat Inap** (`/kamar-rawat-inap`, `/kamar-rawat-inap/{slug}`; empat kategori contoh), dan **Kontak dan Lokasi** (`/kontak`: alamat, peta tersemat dengan cadangan saat offline, dan formulir pesan). Beranda kini memuat blok **Ketersediaan Kamar Rawat Inap**.
- Tombol "Kirim Pesan" dan tautan "Kontak" yang menunggu route di fase 4a kini tampil otomatis; beranda memuat tautan "Lihat detail kamar" ke Kamar Rawat Inap.
- Pengaturan situs (alamat, telepon, tautan peta) baru dapat diubah dari admin di fase 8; sampai saat itu nilainya berasal dari seeder pengaturan. Bila `map.embed_url` kosong, halaman kontak menulis "Peta tidak tersedia." dan tombol Petunjuk Arah tetap tampil.

### Hak akses

| Peran | Fasilitas dan kategori kamar (tambah, ubah, hapus) | Perbarui ketersediaan kamar | Kotak pesan kontak |
|---|:-:|:-:|:-:|
| Super Admin | ya | ya | ya |
| Admin Konten | ya | ya | tidak (403) |
| Petugas Pendaftaran | tidak (403) | ya | ya |

Petugas Pendaftaran membuka daftar kamar dalam mode ketersediaan saja: ia dapat memperbarui jumlah tempat tidur tersedia, tetapi tidak dapat menambah, mengubah, atau menghapus kategori dan gambarnya. Layar Fasilitas dan Kamar tidak pernah memuat data pasien atau pesan kontak. Halaman publik terbuka untuk semua orang; fasilitas dan kategori kamar nonaktif menjawab 404 di URL-nya, juga untuk staf yang login.

### Aturan yang berlaku untuk semua peran, termasuk Super Admin

Jumlah tempat tidur tersedia tidak boleh melebihi kapasitas, dan kapasitas tidak boleh diturunkan di bawah jumlah tersedia saat ini. Ketersediaan kamar hanya diperbarui manual oleh petugas dan halaman publik menampilkan waktu pembaruan terakhirnya (tanpa klaim waktu nyata); data yang lebih tua dari 24 jam diberi peringatan. Galeri fasilitas dan kamar paling banyak 20 gambar. Status pesan kontak tidak dapat dikembalikan ke "Baru" dan pesan tidak dapat dihapus, hanya diarsipkan. Formulir kontak dibatasi 3 kiriman per 10 menit per alamat IP; alamat IP tidak disimpan, hanya hash-nya.

### Data demo

Seeder `RoomCategorySeeder`, `FacilitySeeder`, dan `ContactMessageSeeder` hanya berjalan di lokal:

```powershell
& $PHP artisan migrate:fresh --seed   # LOKAL SAJA: menghapus semua tabel dan isi database unpam_hospital
```

Selain data fase 1–4a: 4 kategori kamar (satu sengaja penuh untuk menguji badge "Penuh"), 10 fasilitas, dan 5 pesan kontak fiktif (2 baru, 1 dibaca, 1 dibalas, 1 diarsipkan). Total gambar contoh menjadi 83 (57 sebelumnya + 26 fasilitas dan kamar), semuanya dibuat lokal oleh GD. Nama kategori kamar dan tarifnya adalah contoh prototipe, bukan ketentuan BPJS atau tarif resmi, dan harus ditinjau sebelum dipakai di produksi.

### Pemeriksaan cepat (fase 4b)

```powershell
& $PHP artisan route:list --path=admin   # 106 route admin (90 fase 1-4a + 16 fase 4b)
& $PHP artisan route:list --except-path=admin --name=facilities   # facilities.index dan facilities.show (publik)
& $PHP artisan route:list --except-path=admin --name=rooms        # rooms.index dan rooms.show (publik)
& $PHP artisan route:list --except-path=admin --name=contact      # contact (GET) dan contact.store (POST, throttle)
& $PHP artisan media:prune --dry-run     # setelah seeding: 0 baris dan 0 berkas yatim
& $PHP artisan test                      # seluruh suite
npm run build                            # peta tersemat (initMapEmbeds) dan gaya fasilitas/kamar ikut dibundel
```
````

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
Select-String -Path README.md -Pattern '^## Fase' | ForEach-Object { $_.Line }
git add README.md
git commit -m @'
docs(fase-4b): README bagian Fase 4b (fasilitas, kamar, kontak, kotak pesan, data demo, pemeriksaan cepat)

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
'@
```
Expected: lima judul bagian (`## Fase 1 …`, `## Fase 2 …`, `## Fase 3 — Penjadwalan …`, `## Fase 4a — Konten publik …`, `## Fase 4b — Fasilitas, kamar rawat inap, dan kontak`); commit dibuat.

**(d) Gerbang fase 4b**

- [ ] **Step 12: Verifikasi akhir (suite, Pint, build, route list)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
$job = Start-Job -ScriptBlock { param($php, $app) Set-Location $app; & $php artisan test *> "$app\storage\logs\suite-gate-4b.txt" } -ArgumentList $PHP, $APP
Wait-Job $job -Timeout 1500 | Out-Null
Get-Content "$APP\storage\logs\suite-gate-4b.txt" -Tail 15
& $PHP vendor\bin\pint --test
npm run build
& $PHP artisan route:list --path=admin --json | ConvertFrom-Json | Measure-Object | ForEach-Object { "route admin: $($_.Count)" }
& $PHP artisan route:list --except-path=admin --json | ConvertFrom-Json | Where-Object { $_.name -in 'home', 'about', 'vision-mission', 'insurance', 'registration-guide', 'articles.index', 'articles.show', 'galleries.index', 'galleries.show', 'faq', 'privacy', 'facilities.index', 'facilities.show', 'rooms.index', 'rooms.show', 'contact', 'contact.store' } | ForEach-Object { "$($_.method) $($_.name)" }
git status --short
git log --oneline -20
```
Bila `Wait-Job` kedaluwarsa sebelum selesai, ulangi `Wait-Job $job -Timeout 600` lalu baca ekor berkas lagi.

Expected:
- seluruh suite PASS (tes yang di-*skip* hanya yang bersyarat pada route fase 5/6; `PublicDoctorPagesTest` tidak lagi di-skip untuk route `contact`);
- Pint `{"result":"passed"}`; `npm run build` sukses tanpa entri Vite baru;
- `route admin: 106`; tujuh belas baris publik (`GET|HEAD` untuk `home`, `about`, `vision-mission`, `insurance`, `registration-guide`, `articles.index`, `articles.show`, `galleries.index`, `galleries.show`, `faq`, `privacy`, `facilities.index`, `facilities.show`, `rooms.index`, `rooms.show`, `contact`, dan `POST contact.store`);
- `git status` bersih (kecuali `.superpowers/`); log memuat commit Task 1–11 fase 4b.

**Implementer berhenti di sini.** `migrate:fresh --seed` menghapus seluruh isi database pengembangan dan memerlukan izin pemilik, sehingga dua langkah berikut dijalankan koordinator.

- [ ] **Step 13 (MANUAL, koordinator): seeding ulang dan hitungan data**

Daftar periksa; implementer melewatinya. Setelah pemilik mengizinkan, jalankan dari PowerShell (Prelude P):

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan migrate:fresh --seed
& $PHP artisan media:prune --dry-run
```
Expected: ketiga migration 4b (`create_room_categories_table`, `create_facilities_table`, `create_contact_messages_table`) berjalan tanpa galat; seeding selesai dan menampilkan tabel akun demo (waktu ±1,5 menit karena 83 gambar GD); `media:prune --dry-run` melaporkan 0 baris dan 0 berkas yatim. Hitung baris pada database `unpam_hospital`: `room_categories` 4 (kapasitas 58, tersedia 16, satu dengan `beds_available = 0`); `facilities` 10 (medis 2, penunjang 3, umum 5); `contact_messages` 5 (`new` 2, `read` 1, `replied` 1, `archived` 1; semua `ip_hash` 64 karakter); `media` 83 (photo 16, cover 36, gallery 12, image 3, photos 12, logo 4); data 4a tetap (`pages` 15, `articles` 8, dan seterusnya). Pastikan `public\storage` (junction `storage:link`) ada agar gambar tampil. Perlu login ulang karena sesi terhapus.

- [ ] **Step 14 (MANUAL, koordinator): uji manual Playwright dan Lighthouse**

Server lokal hidup (`http://unpam-hospital.test`), data demo baru. Catat hasil pada "Catatan Eksekusi" rencana ini.

1. **Publik, desktop 1280 px, tamu.** Buka dan periksa satu `<h1>`, breadcrumb, judul tab, dan konsol bersih untuk `/fasilitas`, `/fasilitas/instalasi-gawat-darurat`, `/fasilitas/ruang-tunggu-pasien`, `/kamar-rawat-inap`, `/kamar-rawat-inap/bpjs-kelas-1`, `/bpjs-kelas-2`, `/bpjs-kelas-3`, `/vip`, `/kontak`, dan `/`.
   - `/fasilitas`: tiga kelompok berurutan (Fasilitas medis 2, Penunjang 3, Fasilitas umum 5), kartu bergambar, tautan "Lihat Kamar Rawat Inap".
   - `/fasilitas/ruang-tunggu-pasien`: dua thumbnail galeri; klik membuka modal, panah keyboard dan Esc bekerja, fokus kembali ke thumbnail.
   - `/kamar-rawat-inap`: kotak informasi dengan kalimat F4B-R11 lengkap; empat kartu; Kelas 2 berlencana "Penuh" (merah, ikon `bi-x-circle`) dan lainnya "Tersedia"; "5 dari 12 tempat tidur tersedia"; "Tarif contoh: Rp 400.000 per malam"; "Diperbarui manual oleh petugas pada {tanggal hari ini}, pukul {jam − 2}.xx WIB"; **tidak ada kata "real-time" di halaman mana pun** (cari teks di DOM).
   - Detail kamar: panel Ringkasan (kapasitas, tersedia, tarif contoh dan catatan, waktu pembaruan), daftar "Fasilitas Kamar" berikon centang, tombol "Tanya Ketersediaan" (ke `/kontak`) dan "Chat via WhatsApp (membuka WhatsApp)" (`target="_blank" rel="noopener noreferrer"`).
   - `/`: blok "Ketersediaan Kamar Rawat Inap" di antara dokter dan artikel dengan empat kartu, kalimat pendek, dan tautan "Lihat detail kamar".
   - `/kontak`: alamat, daftar kontak, tombol "Petunjuk Arah", peta tersemat, dan formulir. **Offline (OFF-04):** dengan emulasi jaringan offline (atau `navigator.onLine === false`), muat ulang; peta menampilkan "Peta tidak dapat dimuat. Alamat dan tombol Petunjuk Arah tetap dapat dipakai." sedangkan alamat dan tombol tetap tampil.
2. **Formulir kontak.** Kirim kosong (galat per field dengan ringkasan galat di atas form, fokus pindah ke ringkasan); kirim tanpa persetujuan ("Anda harus menyetujui pengolahan data untuk mengirim pesan."); telepon `021555` ditolak dan `0812-3456-7890` diterima; kirim valid (flash "Terima kasih. Pesan Anda telah kami terima dan akan ditindaklanjuti oleh petugas kami."; pesan masuk kotak admin dengan telepon `081234567890`); isi `<script>alert(1)</script>` pada nama dan subjek dan pastikan tampil sebagai teks di admin; kirim empat kali berturut-turut: kiriman ke-4 dialihkan kembali ke `/kontak` dengan peringatan "Terlalu banyak percobaan. Silakan coba lagi dalam {n} detik." dan input terisi kembali. Isi kolom honeypot lewat konsol (`document.querySelector('[name=website]').value = 'x'`) dan kirim: flash sukses tampil tetapi pesan tidak masuk.
3. **Publik, mobile 360 px.** Semua halaman di atas tanpa gulir horizontal; kartu fasilitas dan kamar bertumpuk satu kolom; target sentuh ≥ 44 px untuk tombol; gambar hilang memakai placeholder (hapus satu berkas cover lalu muat ulang).
4. **Admin Konten** (`konten@unpamhospital.test`): sidebar "Fasilitas dan Kamar" ke `/admin/kamar` dengan dua tab; ubah ketersediaan satu kategori lewat formulir inline (flash "Ketersediaan {nama} berhasil diperbarui."; nilai di atas kapasitas ditolak dengan pesan kapasitas hanya pada baris itu); tambah kategori kamar baru dengan `amenities` beberapa baris dan cover (SVG dan `shell.php.jpg` ditolak, < 800x450 ditolak, alt kosong ditolak); turunkan kapasitas di bawah ketersediaan (ditolak); hapus kategori dan fasilitas (modal konfirmasi, berkas gambar hilang); CRUD fasilitas lengkap dengan galeri (urutkan, ubah alt, hapus gambar, galeri ke-21 ditolak); setiap perubahan langsung tampil di halaman publik. `/admin/pesan` menjawab 403 dan item "Pesan Kontak" tidak tampil di sidebar.
5. **Petugas Pendaftaran** (`pendaftaran@unpamhospital.test`): `/admin/kamar` terbuka dalam mode ketersediaan (tanpa tombol Tambah, Ubah, Hapus; formulir inline "Simpan" berfungsi dan mencatat petugas dan waktu); `/admin/kamar/buat`, `/admin/fasilitas` menjawab 403; `/admin/pesan`: buka pesan baru (status berubah menjadi "Dibaca" satu kali, tebal pada daftar hilang), "Tandai Dibalas" lalu "Arsipkan" (pengisi "Ditangani oleh" muncul), "Pulihkan" (kembali "Dibaca"); tidak ada tombol hapus; tampilan kartu di mobile. **Super Admin**: semua layar terbuka.
6. **Konsol dan jaringan.** Tidak ada pelanggaran CSP, galat JavaScript, atau permintaan ke domain eksternal selain `www.google.com/maps` (iframe peta) dan tautan yang diklik; hanya satu pendengar `error` tertaut (milik fallback gambar).
7. **Aksesibilitas dan performa.** Audit Lighthouse aksesibilitas ≥ 90 untuk `/fasilitas`, `/fasilitas/{slug}`, `/kamar-rawat-inap`, `/kamar-rawat-inap/bpjs-kelas-1`, dan `/kontak` (15 A11-05); urutan fokus keyboard `/kontak`: skip link → header → tombol Petunjuk Arah → peta (iframe) → formulir (nama, email, telepon, subjek, pesan, persetujuan, Kirim Pesan) → footer; pembaca layar membaca badge ketersediaan sebagai teks ("Tersedia"/"Penuh"), bukan hanya warna.

Hasil dan temuan dicatat koordinator; tidak ada commit pada langkah ini.

---

## Catatan Eksekusi (2026-10-06 s.d. 2026-10-07)

Dieksekusi dengan `superpowers:subagent-driven-development`. Semua peran memakai subagent **Sonnet 5.5** (preferensi pemilik): implementer, reviewer per tugas, re-reviewer, final reviewer, implementer gelombang perbaikan final, dan penguji manual Playwright. Eksekusi dimulai 2026-10-06 dan dijeda atas permintaan pemilik setelah Task 1. Implementer Task 2 dihentikan setelah semua berkasnya selesai ditulis, lalu berkasnya disimpan di `git stash`. Eksekusi dilanjutkan 2026-10-07: stash dipulihkan, dan agen baru memverifikasi bahwa kesebelas berkas identik dengan kode brief sebelum commit.

Cabang `fase-4b-fasilitas-kamar-kontak` dibuat dari `master` (`ab26123`). Isinya:
- satu commit rencana (`e4cdcf8`);
- satu commit CHECKPOINT jeda (`ec9ab9a`);
- 15 commit kerja (`01240a3`..`76ade11`);
- dua commit gelombang perbaikan final (`4a4ca50`, `fe3716e`);
- commit catatan ini.

**Hasil akhir:**

| Pemeriksaan | Hasil |
|---|---|
| `artisan test` | **5200 tes lulus (26035 asersi), ±28 menit**, diverifikasi koordinator di `fe3716e`; basis fase 4a: 4348 |
| `pint --test` | PASS |
| `npm run build` | Sukses (T7, T8, T9, T11); tanpa entri Vite baru |
| `route:list --path=admin` | **106 route** (90 fase 1–4a + 16 baru: `admin.facilities.*` 6, `admin.room-categories.*` 7, `admin.messages.*` 3) |
| Route publik | 17 route (`facilities.*`, `rooms.*`, `contact`, dan `contact.store` baru) |
| `migrate:fresh --seed` (Step 13) | OK setelah izin pemilik ("Ya, jalankan migrate:fresh --seed"). Hitungan sesuai brief (lihat tabel di bawah). |
| MANUAL (Step 14) | Dijalankan lewat Playwright dan Lighthouse; laporan bergambar di `Documents\UNPAM-Uji-Manual-2026-10-07-fase-4b\index.html`. Hasilnya di bawah. |

Hitungan data setelah Step 13:

| Tabel | Jumlah |
|---|---|
| `room_categories` | 4 (kapasitas 58, tersedia 16, satu penuh) |
| `facilities` | 10 (medis 2, penunjang 3, umum 5) |
| `contact_messages` | 5 (`new` 2, `read` 1, `replied` 1, `archived` 1; `ip_hash` 64 karakter) |
| `media` | 83 (cover 36, gallery 12, photo 16, image 3, photos 12, logo 4) |
| `pages` / `articles` | 15 / 8 |

`media:prune` membersihkan 116 berkas yatim sisa seed lama, sehingga `--dry-run` kini 0/0.

Hasil uji manual (Step 14):
- **Kelompok 1–7:** sesuai. Tercatat 0 galat JS, 0 pelanggaran CSP, 0 atribut event inline, dan satu pendengar `error`. Satu-satunya domain eksternal adalah iframe Google Maps di `/kontak`.
- **Lighthouse:** aksesibilitas dan SEO **100** untuk `/fasilitas`, detail fasilitas, `/kamar-rawat-inap`, detail kamar, dan `/kontak`. Best Practices 78 hanya karena HTTP lokal.
- **Peta:** tetap `is-loaded` lebih dari 10 detik pada empat percobaan. Fallback offline benar.
- **Temuan:** 0 Critical, 0 Important. Catatan info:
  - iframe peta yang diblokir saat `navigator.onLine` bernilai true tetap memicu `load`, sehingga fallback tidak muncul. Penyebabnya lintas-origin; alamat dan tombol Petunjuk Arah tetap ada.
  - Panah carousel beranda 41x44 px (bawaan 4a).
  - URL cover yang terhapus dijawab 403 Apache, bukan 404.
  - Berkas `shell.php.jpg` berisi PHP asli tidak dapat diuji karena dihapus antivirus lokal. Varian lain ditolak atau diganti nama menjadi uuid.

Sisa data uji di DB pengembangan: satu pesan uji "Uji Manual Empat B", dan satu pesan demo yang kini berstatus Dibaca.

**Putaran perbaikan per tugas:** tidak ada. Kesebelas tugas lolos review pada percobaan pertama.

**Final review** (seluruh cabang, `ab26123..76ade11`): verdict **Siap merge**, tanpa temuan Critical atau Important. Enam Minor; triase atas semua minor yang ditunda: tidak ada yang wajib diperbaiki sebelum merge. Satu gelombang perbaikan tetap dijalankan untuk butir murah berikut. Re-review terbatas menyatakan keempatnya ADDRESSED tanpa kerusakan baru.

| Butir | Perbaikan |
|---|---|
| F1 (`4a4ca50`) | `cover_alt` berupa larik pada edit tanpa cover baru tidak lagi menyebabkan 500 ("Array to string conversion" di callback `after()`). `coverAlt()` di `FacilityRequest`, `RoomCategoryRequest`, dan `ClinicRequest` (warisan fase 2) kini memeriksa `is_string`, mengikuti pola `ArticleRequest`. Ditambah satu tes per Request. |
| F2a (`fe3716e`) | Sisa frasa "real-time" di 05 dan 07 diselaraskan dengan F4B-R11 / D-129. |
| F2b (`fe3716e`) | 06 asumsi 23: honeypot dan auto-`read` ditandai sudah diputuskan (D-128, D-137). |
| F2c (`fe3716e`) | Judul 11 §2.3 kini mencatat total 106 route setelah fase 4b (D-132). |

### Keputusan (rulings) selama eksekusi

| ID | Keputusan | Biaya bila keliru |
|---|---|---|
| E1 | Tanpa git worktree; bekerja di checkout utama pada cabang `fase-4b-fasilitas-kamar-kontak` (pola fase 0–4a). | Checkout utama terkunci di cabang ini selama eksekusi. |
| E2 | Semua subagent, termasuk final review, memakai Sonnet 5.5 (preferensi pemilik). | Final review sedikit kurang tajam; dimitigasi review per tugas dan uji manual. |
| E3 | Trailer commit subagen mengikuti atribusi harness (`Claude Sonnet 5.5`), sesuai F4B-R38. | Hanya teks trailer. |
| E4 | Step 13 Task 11 dijalankan koordinator setelah balasan persis pemilik. | Pemilik perlu login ulang. |
| E5 | Suite penuh dijalankan di latar belakang (`run_in_background`), bukan `Start-Job` + `Wait-Job 1500` dari brief T11, karena batas 10 menit sebuah perintah tool. | Tidak ada. |
| E6 | Task 2 diterima tanpa bukti RED. Berkas WIP ditulis implementer yang dihentikan saat jeda, lalu finalizer membuktikan 11/11 berkas identik dengan brief; GREEN 60 tes. | Satu tes bisa saja tidak pernah merah. |
| E7 | Task 3 diterima tanpa RED formal (tes identik dengan brief, suite hijau). Aturan "RED wajib nyata" ditambahkan ke instruksi implementer untuk T4+, dan T4–T10 mencatat RED nyata. | Satu tes T3 bisa saja tidak pernah merah. |
| E8 | Task 9: label persetujuan `…Privasi@endif` dari brief tidak dapat dikompilasi Blade (`@` menempel pada kata). Diubah menjadi `</a>.@else Kebijakan Privasi.@endif`; keluaran yang dikunci tes tetap sama. | Hanya tanda baca pada cabang tanpa route `privacy`. |
| E9 | Step 14 didelegasikan ke subagen penguji (dilarang mengubah kode atau menjalankan artisan yang menulis DB) agar konteks koordinator tetap ringan. Koordinator memeriksa ringkasan dan laporannya. | Penguji bisa melewatkan butir. |
| E10 | Gelombang perbaikan final dijalankan walau verdict "Siap merge": F1 melanggar aturan proyek "input larik tidak boleh 500", dan F2 murah serta mencegah salah arah di fase 8. | Satu putaran kecil yang sebenarnya bisa ditunda. |
| E11 | Kemungkinan balapan `load` di `initMapEmbeds()` diparkir: tidak teramati di uji manual, status iframe lintas-origin tidak dapat dibaca, dan degradasinya anggun. | Sesekali peta tersembunyi dan diganti alamat serta tombol Petunjuk Arah. |

### Bawaan (carry) untuk rencana fase berikutnya

**Fase 5/6 dan sesudahnya:**
- Ekstrak modal galeri publik (kini 3 salinan: klinik, fasilitas, kamar) ke `public.partials._gallery-modal`. Ekstrak juga `saveMedia()` (`FacilityController`, `RoomCategoryController`) ke satu trait dan `attach()` seeder (3 salinan) ke satu helper sebelum salinan berikutnya muncul.
- `ContactMessageController::show` (GET) mengubah `new` menjadi `read` (F4B-R24); prefetch atau crawler staf dapat menandai pesan terbaca.
- `ip_hash` memakai `sha256(ip . APP_KEY)` sesuai spesifikasi; `hash_hmac` lebih tepat bila kelak diubah (seeder dan tes ikut disesuaikan).

**Fase 8 (dasbor dan pengaturan):** ringkasan kamar di dasbor memakai redaksi "diperbarui manual oleh petugas" (F4B-R11). Pengaturan `map.*` dan `contact.*` lewat admin.

**Minor yang ditunda (tidak memblokir):**
- Task 1: tes panjang kolom untuk `rate_note`/`facilities.description`, dan `expectException(QueryException)` tanpa errno.
- Task 2: label tarif berspasi ekor bila `rate_unit` kosong (tidak tercapai).
- Task 4: angka 20 literal di view galeri (ada `MAX_GALLERY`); logika `$rootUrl` diulang di 3 view.
- Task 5: audit dan flash ketersediaan ditulis literal; galat `amenities.N` tanpa tautan di ringkasan galat.
- Task 7: `og:description` kosong pada kombinasi yang tidak tercapai.
- Task 8: `aria-label` thumbnail memakai alt mentah; `Route::has('rooms.index')` di `_room-summary` kini kode mati; kartu beranda tanpa kalimat kedaluwarsa (sesuai F4B-R31).
- Task 9: spasi ganda pada cabang tanpa route `privacy`.
- Task 10: baris seeder dibuat sebelum gambar.
- Target sentuh panah carousel beranda 41x44 px.
- Suite kini ±20 menit (5200 tes); implementer menjalankannya di latar belakang.
