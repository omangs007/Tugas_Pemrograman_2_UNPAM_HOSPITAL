# 00 — Keputusan Inti (Sumber Kebenaran Tunggal)

> **Status:** disetujui pemilik proyek pada sesi brainstorming 2026-10-02.
> **Aturan:** seluruh bagian blueprint (01–17) WAJIB memakai nama tabel, kolom, enum, route, izin, kunci pengaturan, dan service persis seperti di dokumen ini. Jika sebuah bagian membutuhkan nama baru, nama itu dicatat di akhir bagian tersebut pada subjudul **"Nama baru yang diusulkan"** untuk direkonsiliasi ke dokumen ini.
> Penanda: **[ASUMSI]** = asumsi yang ditetapkan tanpa data dari pemilik; **[KONFIRMASI]** = butuh keputusan pemilik sebelum produksi.

---

## A. Keputusan yang Sudah Diambil Pemilik

| # | Keputusan | Pilihan |
|---|---|---|
| K1 | Pasien walk-in tanpa akun | Petugas dapat membuat **profil pasien tanpa akun login** dan mendaftarkan kunjungan pada sesi yang sama; kuota dibagi dengan booking online. Booking merujuk ke `patient_profiles`, bukan `users`. |
| K2 | Pendekatan arsitektur | **Monolit Laravel 12, Blade + Bootstrap 5 di seluruh aplikasi** (publik, pasien, admin). Bukan Filament, bukan SPA. |
| K3 | Desain bagian 1–3 (akses, mesin booking, model data) | Disetujui sebagaimana dirangkum di dokumen ini. |

---

## B. Lingkungan dan Versi

| Komponen | Versi / Nilai | Catatan |
|---|---|---|
| Paket XAMPP | `xampp-windows-x64-8.2.12-0-VS16` | **Sudah terpasang di `C:\xampp3`** (2026-10-02 10.08); Apache (port 80/443) dan MariaDB (port 3306) berjalan. Folder `C:\xampp` lama hanya sisa instalasi lama dan **tidak dipakai**. Seluruh path di blueprint memakai `C:\xampp3` (di mesin lain bisa `C:\xampp`; panduan memakai variabel `$XAMPP`). |
| Apache | 2.4.58 (bawaan XAMPP 8.2.12) | `mod_rewrite` wajib aktif. |
| PHP (runtime Apache) | 8.2.12 (bawaan XAMPP) | Thread Safe, VS16. **Dukungan keamanan PHP 8.2 berakhir 31 Desember 2026** (php.net) — lebih awal daripada Laravel 12 — dan rilis patch 8.2.12 (2023) sudah tertinggal banyak patch keamanan. Cukup untuk prototipe localhost; untuk produksi wajib PHP ≥ 8.3 (disarankan 8.4, keamanan s.d. 31 Des 2028). Kode ditulis agar kompatibel PHP 8.2–8.4 sehingga bisa langsung dijalankan di 8.4. |
| Database | **MariaDB 10.4.32** (bawaan XAMPP; XAMPP memberi label "MySQL") | Laravel 12 mendukung resmi MariaDB 10.3+. Gunakan `DB_CONNECTION=mariadb`. MariaDB 10.4 sudah EOL dari vendor → cukup untuk prototipe localhost, **[KONFIRMASI]** untuk produksi gunakan MariaDB ≥ 10.11 LTS atau MySQL 8.4 LTS. |
| Framework | **Laravel 12.x** | Mendukung PHP 8.2–8.5. Bug fix berakhir 13 Agustus 2026, **security fix sampai 24 Februari 2027**. Laravel 13 butuh PHP ≥ 8.3 sehingga tidak kompatibel dengan PHP XAMPP 8.2.12. Rencana upgrade ke Laravel 13 dicatat di bagian 17. |
| PHP CLI lain di mesin | 8.4.26 di `C:\php\8.4` (ada di PATH) | Agar dependensi cocok dengan runtime Apache: `composer.json` → `"config": {"platform": {"php": "8.2.12"}}`, dan perintah `artisan` dijalankan dengan `C:\xampp3\php\php.exe` (atau PATH sesi diawali `C:\xampp3\php`). |
| Composer | 2.9.2 | Sudah terpasang. |
| Node.js / npm | 24.12.0 / 11.6.2 | Hanya untuk build aset (Vite). |
| Git | 2.45 | Repo di root proyek `D:\Project Web RS`. |

**Ekstensi PHP wajib** (cek dengan `C:\xampp3\php\php.exe -m`; aktifkan di `C:\xampp3\php\php.ini` bila masih dikomentari): `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `fileinfo`, `gd` (JPEG/PNG/WebP), `intl`, `zip`, `bcmath`, `curl`.

**Kondisi `C:\xampp3` saat diperiksa (2026-10-02):** PHP 8.2.12 ZTS VC++2019 x64. Baris `;extension=gd`, `;extension=intl`, `;extension=zip` (dan `;extension=sodium`) **masih dikomentari → wajib diaktifkan**. `date.timezone=Europe/Berlin` → **ubah ke `Asia/Jakarta`**. `upload_max_filesize=40M`, `post_max_size=40M`, `memory_limit=512M` sudah cukup. `openssl` sudah termuat. `httpd.conf` sudah memuat `mod_rewrite` dan `Include conf/extra/httpd-vhosts.conf`. MariaDB: `root` tanpa password (bawaan XAMPP), belum ada database aplikasi.

**Konfigurasi aplikasi:** `config/app.php` → `timezone = 'Asia/Jakarta'`, `locale = 'id'`, `fallback_locale = 'en'`, `faker_locale = 'id_ID'`. File terjemahan `lang/id/{auth,pagination,passwords,validation}.php` dibuat dalam Bahasa Indonesia. Database: `utf8mb4` / `utf8mb4_unicode_ci`, engine InnoDB. Datetime disimpan dalam zona aplikasi (Asia/Jakarta).

**Lokasi aplikasi:** root Laravel = root repo `D:\Project Web RS` (folder `docs/` berdampingan dengan `app/`, `public/`, dst.). URL lokal utama `http://unpam-hospital.test` (VirtualHost Apache, DocumentRoot `"D:/Project Web RS/public"` — path mengandung spasi sehingga wajib diberi tanda kutip). Alternatif: `php artisan serve` → `http://127.0.0.1:8000`.

### B.1 Paket

| Paket Composer | Fungsi |
|---|---|
| `laravel/framework` ^12 | Framework. |
| `spatie/laravel-permission` (versi yang mendukung Laravel 12) | Peran & izin. |
| `intervention/image` ^3 (driver GD) | Resize, re-encode, buang EXIF, thumbnail. |
| `mews/purifier` | Sanitasi HTML rich text (HTMLPurifier). |
| `barryvdh/laravel-dompdf` | PDF bukti pendaftaran. |
| `chillerlan/php-qrcode` | QR code (SVG) berisi kode booking saja. |
| Dev: `laravel/pint`, `phpunit/phpunit` ^11 (bawaan skeleton), `fakerphp/faker` | Format kode, pengujian, data contoh. |

**Versi hasil resolusi dengan `platform.php = 8.2.12` (diverifikasi 2026-10-02):** skeleton `laravel/laravel` v12.12.2, `laravel/framework` 12.69.x, `spatie/laravel-permission` **^6.25** (v8 membutuhkan PHP ≥ 8.3 sehingga otomatis ditolak — gunakan dokumentasi v6), `intervention/image` ^3.11, `mews/purifier` ^3.4, `barryvdh/laravel-dompdf` ^3.1 (dompdf 3.1), `chillerlan/php-qrcode` **^6.0**, `phpunit/phpunit` 11.5, `laravel/pint` 1.30. GD XAMPP mendukung JPEG/PNG/WebP/FreeType; ICU `intl` 71.1.

**Peringatan `^` di PowerShell:** `composer.bat`/`npm.cmd` dijalankan oleh cmd.exe yang membuang `^` (mis. `laravel/laravel:^12.0` menjadi `12.0` → skeleton v12.0.0). Selalu panggil Composer sebagai `& C:\xampp3\php\php.exe C:\composer\composer.phar …` dan tulis versi npm sebagai mayor saja (`bootstrap@5`).

Catatan Windows: skeleton Laravel 12 menyertakan `laravel/pail` dan skrip `composer dev` yang memakai pail; pail memerlukan ekstensi `pcntl` yang tidak tersedia di Windows → hapus pail dari skrip `dev` (gunakan `concurrently` untuk `php artisan serve` + `npm run dev` saja).

| Paket npm | Fungsi |
|---|---|
| `bootstrap` ^5.3 + `@popperjs/core` | UI. |
| `sass` | Kompilasi tema Bootstrap kustom. |
| `bootstrap-icons` | Ikon (font lokal). |
| `chart.js` ^4 | Grafik dashboard. |
| `quill` ^2 | Editor rich text admin. |
| `@fontsource/plus-jakarta-sans` | Font lokal (tanpa Google Fonts → tetap tampil offline). |
| `vite`, `laravel-vite-plugin` (bawaan) | Build aset. **Hapus** `tailwindcss` dan `@tailwindcss/vite` bawaan skeleton Laravel 12. |

Semua aset frontend dibundel lokal oleh Vite. Satu-satunya sumber eksternal: iframe Google Maps dan tautan "Petunjuk Arah" — keduanya opsional dan halaman tetap berfungsi tanpa internet.

### B.2 Mode lokal vs produksi

| Kunci `.env` | Lokal | Produksi |
|---|---|---|
| `APP_ENV` / `APP_DEBUG` | `local` / `true` | `production` / `false` |
| `APP_URL` | `http://unpam-hospital.test` | URL HTTPS resmi |
| `DB_CONNECTION` | `mariadb` | `mariadb` atau `mysql` |
| `DB_USERNAME` | `unpam_app` (user khusus, bukan `root`) | user khusus, password kuat |
| `SESSION_DRIVER` | `database` | `database` |
| `SESSION_SECURE_COOKIE` | `false` | `true` |
| `QUEUE_CONNECTION` | `sync` | `database` + worker |
| `MAIL_MAILER` | `log` (opsional `smtp` ke Mailpit `127.0.0.1:1025`) | `smtp` resmi **[KONFIRMASI]** |
| `LOG_CHANNEL` | `daily` | `daily` |
| Seeder demo | aktif (`DemoSeeder` hanya jalan bila `app()->environment(['local','testing'])`) | **tidak pernah**; Super Admin dibuat via `php artisan app:create-super-admin` |
| Header CSP | dinonaktifkan/longgar (Vite dev server) | aktif ketat |

Tidak ada ketergantungan pada Task Scheduler/cron untuk fitur inti (lihat F.2).

---

## C. Konvensi

- Identifier kode (tabel, kolom, nilai enum, nama route, kelas) berbahasa **Inggris**; URL dan semua label UI berbahasa **Indonesia**.
- PK `id` BIGINT UNSIGNED auto-increment (kecuali tabel bawaan Laravel/Spatie dan `notifications` yang memakai UUID). FK memakai `foreignId()->constrained()`.
- Semua tabel memakai `created_at`/`updated_at` kecuali dinyatakan lain.
- Status dan jenis disimpan sebagai `VARCHAR` + **PHP backed enum** (`App\Enums\*`), bukan kolom `ENUM` MariaDB, agar nilai baru tidak butuh `ALTER TABLE`.
- Entitas publik memakai `slug` unik; route model binding berdasarkan slug.
- Uang: `DECIMAL(12,2)` dalam Rupiah. Tanggal: `DATE`; jam sesi: `TIME`.
- Kolom rich text (sudah disanitasi saat disimpan): `pages.body`, `articles.body`, `clinics.description`, `facilities.description`, `room_categories.description`. Hanya kolom ini yang boleh dirender dengan `{!! !!}`; semua lainnya `{{ }}`.
- Penghapusan: data yang dirujuk riwayat booking **tidak pernah dihapus permanen** (FK `RESTRICT`), cukup dinonaktifkan/diarsipkan. Aturan aksi `destroy` per tabel:

  | Kategori | Tabel |
  |---|---|
  | Hapus permanen diizinkan (file media ikut dibersihkan) | `banners`, `faqs`, `galleries` (beserta fotonya), `article_categories` (artikel menjadi tanpa kategori), `facilities`, `media` |
  | Soft delete | `articles` |
  | Hapus hanya bila tidak dirujuk; bila dirujuk tombol hapus diganti "Nonaktifkan" dengan pesan alasan | `specialties`, `clinics`, `insurance_partners`, `room_categories`, `doctor_schedules` (tidak ada sesi ber-appointment), `practice_sessions` (hanya sesi tambahan manual tanpa appointment) |
  | Hapus = cabut pengecualian lewat `ScheduleChangeService::removeException` | `schedule_exceptions` |
  | Tidak ada aksi hapus | `doctors` (arsip), `users` (nonaktif), `patient_profiles` (nonaktif), `appointments`, `appointment_status_histories`, `pages`, `contact_messages` (status `archived`), `website_settings`, `audit_logs` |

---

## D. Enum (`App\Enums`)

| Enum | Nilai (kode → label UI) | Catatan |
|---|---|---|
| `AccountType` | `patient` → Pasien, `staff` → Staf | |
| `AppointmentStatus` | `pending` → Menunggu konfirmasi · `confirmed` → Dikonfirmasi · `checked_in` → Hadir/check-in · `completed` → Selesai · `cancelled` → Dibatalkan · `no_show` → Tidak hadir | `ACTIVE = [pending, confirmed, checked_in]`; `QUOTA_CONSUMING = semua kecuali cancelled`; final = `completed, cancelled, no_show`. |
| `PaymentType` | `umum` → Umum/Pribadi · `bpjs` → BPJS Kesehatan · `asuransi` → Asuransi | |
| `BookingChannel` | `online` → Online · `loket` → Loket | Dipakai `appointments.channel` dan `patient_profiles.registration_channel`. |
| `SessionStatus` | `open` → Dibuka · `closed` → Ditutup · `needs_review` → Perlu tindak lanjut · `cancelled` → Dibatalkan | |
| `ExceptionType` | `doctor_leave` → Cuti dokter · `hospital_holiday` → Libur rumah sakit · `national_holiday` → Libur nasional · `other` → Lainnya | |
| `DoctorStatus` | `active` → Aktif · `inactive` → Nonaktif · `archived` → Diarsipkan | |
| `Gender` | `L` → Laki-laki · `P` → Perempuan | |
| `ClinicType` | `poliklinik` → Poliklinik · `penunjang` → Layanan penunjang | |
| `FacilityCategory` | `medis` → Fasilitas medis · `penunjang` → Penunjang · `umum` → Fasilitas umum | |
| `ArticleStatus` | `draft` → Draf · `published` → Terbit | |
| `ActorType` | `patient` → Pasien · `staff` → Staf · `system` → Sistem | |
| `ContactMessageStatus` | `new` → Baru · `read` → Dibaca · `replied` → Dibalas · `archived` → Diarsipkan | |
| `SessionResolution` | `keep` → Pertahankan sesi lama · `apply` → Terapkan perubahan jam · `cancel` → Batalkan sesi | Untuk sesi `needs_review`. |
| `Availability` (hasil `DoctorAvailabilityQuery`) | `available` → Tersedia · `full` → Penuh · `booking_closed` → Pendaftaran online ditutup · `closed` → Ditutup · `leave` → Cuti · `holiday` → Libur · `cancelled` → Dibatalkan · `under_review` → Sedang diperbarui · `past` → Sudah lewat | Tidak disimpan di DB. |

**Tampilan status:** badge Bootstrap dengan **teks + ikon + warna** (tidak pernah warna saja). Pemetaan warna `AppointmentStatus`: pending=warning, confirmed=primary, checked_in=info, completed=success, cancelled=secondary, no_show=danger.

---

## E. Tabel dan Kolom Kunci (kanonik)

> Bagian 09 (data dictionary) memperluas tabel di bawah dengan tipe, nullable, default, index, dan aturan hapus lengkap. Kolom di sini wajib ada; kolom tambahan boleh diusulkan.

### E.1 Akun dan akses
- **`users`**: `id`, `name`, `email` (unique), `phone` (nullable), `password`, `account_type`, `is_active` (default true), `must_change_password` (default false), `last_login_at` (nullable), `email_verified_at` (nullable, tidak diwajibkan **[ASUMSI]**), `remember_token`, timestamps. Tidak dihapus; dinonaktifkan.
- **`patient_profiles`**: `id`, `user_id` (nullable, **unique**, FK users `nullOnDelete`), `patient_number` (unique, format `P` + 6 digit dari id, mis. `P000123`), `full_name`, `birth_date`, `gender`, `phone`, `email` (nullable; email kontak, untuk walk-in), `address`, `bpjs_number` (TEXT nullable, **encrypted cast**), `bpjs_number_last4` (CHAR(4) nullable, untuk tampilan tersamar), `registration_channel`, `created_by_user_id` (nullable FK users), `consent_at`, `consent_version`, `is_active` (default true), timestamps. **NIK tidak dikumpulkan.** Menonaktifkan profil pasien yang memiliki akun juga menyetel `users.is_active = false` (dan sebaliknya mengaktifkan kembali keduanya) **[ASUMSI]**. Untuk pasien berakun, `patient_profiles.email` **selalu disinkronkan** dengan `users.email` (email login; perubahan di profil memperbarui keduanya dalam satu transaksi dengan validasi unik di `users`); untuk walk-in tanpa akun, `patient_profiles.email` hanya email kontak opsional.
- Tabel Spatie: `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`.
- Bawaan Laravel: `password_reset_tokens`, `sessions`, `notifications` (UUID), `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`.

### E.2 Dokter dan jadwal
- **`specialties`**: `id`, `name`, `slug` (unique), `description` (nullable), `is_active`, `sort_order`, timestamps.
- **`clinics`** (sekaligus daftar layanan): `id`, `name`, `slug` (unique), `code` (VARCHAR(5) unique, awalan nomor antrean, mis. `ANK`), `type` (`ClinicType`), `is_bookable` (bool), `specialty_id` (nullable FK `nullOnDelete`), `short_description`, `description` (rich text), `location` (mis. "Gedung A Lt. 1"), `is_active`, `sort_order`, `meta_title`, `meta_description`, timestamps.
- **`doctors`**: `id`, `clinic_id` (FK `restrict`, poliklinik utama), `name` (tanpa gelar), `slug` (unique), `title_prefix` (mis. `dr.`, `drg.`), `title_suffix` (mis. `Sp.U`), `gender`, `short_bio` (VARCHAR(500)), `bio` (TEXT, teks biasa), `status` (`DoctorStatus`, default `active`), `archived_at` (nullable), `sort_order`, `meta_title`, `meta_description`, timestamps. Nama tampilan: `"{title_prefix} {name}, {title_suffix}"`.
- **`doctor_specialties`**: `doctor_id`, `specialty_id`, `is_primary` (bool); PK komposit (`doctor_id`, `specialty_id`).
- **`doctor_schedules`** (templat mingguan): `id`, `doctor_id` (FK restrict), `clinic_id` (FK restrict), `day_of_week` (TINYINT 1=Senin … 7=Minggu, ISO-8601), `start_time`, `end_time`, `quota` (SMALLINT, 1–200), `valid_from` (DATE), `valid_until` (DATE nullable), `is_active`, `notes` (nullable), `created_by`, `updated_by` (nullable FK users), timestamps. CHECK `end_time > start_time`. Validasi aplikasi: templat aktif seorang dokter pada hari yang sama tidak boleh beririsan jam dalam rentang berlaku yang beririsan.
- **`schedule_exceptions`**: `id`, `doctor_id` (nullable FK restrict; **NULL = berlaku untuk seluruh dokter/RS**), `type` (`ExceptionType`), `start_date`, `end_date`, `reason` (VARCHAR(255)), `created_by`, timestamps. CHECK `end_date >= start_date`. Granularitas **satu hari penuh** **[ASUMSI]**; perubahan sebagian hari dilakukan dengan mengedit `practice_sessions`. Kombinasi wajib: `doctor_leave` → `doctor_id` **wajib**; `hospital_holiday` dan `national_holiday` → `doctor_id` **harus NULL** (berlaku seluruh dokter); `other` → boleh keduanya.
- **`practice_sessions`** (sesi konkret per tanggal; baris yang dikunci saat booking): `id`, `doctor_id` (FK restrict), `clinic_id` (FK restrict), `doctor_schedule_id` (nullable FK `nullOnDelete`; NULL = sesi tambahan manual), `session_date`, `start_time`, `end_time`, `quota`, `booked_count` (default 0), `last_queue_number` (default 0), `status` (`SessionStatus`, default `open`), `status_reason` (VARCHAR(255) nullable), `schedule_exception_id` (nullable FK `nullOnDelete`; pengecualian penyebab pembatalan/review), `pending_change` (JSON nullable; lihat F.4), `is_manually_modified` (default false), `reviewed_by` (nullable FK users), `reviewed_at` (nullable), timestamps.
  - UNIQUE (`doctor_schedule_id`, `session_date`) — NULL boleh berulang sehingga sesi tambahan tidak terbatas.
  - CHECK `booked_count >= 0 AND booked_count <= quota`; CHECK `end_time > start_time` (ditambahkan via `DB::statement` di migration; MariaDB ≥ 10.2.1 menegakkan CHECK).
  - INDEX (`session_date`, `status`), (`doctor_id`, `session_date`), (`clinic_id`, `session_date`).

### E.3 Booking
- **`appointments`**: `id`, `booking_code` (VARCHAR(13) unique, format `UPH-XXXX-XXXX`), `patient_profile_id` (FK restrict), `practice_session_id` (FK restrict), `doctor_id` (FK restrict, denormalisasi), `clinic_id` (FK restrict, denormalisasi), `session_date` (DATE, denormalisasi untuk filter/laporan), `queue_number` (SMALLINT UNSIGNED), `status` (`AppointmentStatus`), `channel` (`BookingChannel`), `payment_type` (`PaymentType`), `insurance_partner_id` (nullable FK restrict), `payment_reference` (TEXT nullable, **encrypted**: nomor BPJS atau nomor polis), `payment_reference_last4` (CHAR(4) nullable), `complaint` (VARCHAR(500) nullable, keluhan singkat opsional), `created_by_user_id` (nullable FK users), `confirmed_at`, `checked_in_at`, `completed_at`, `cancelled_at`, `no_show_at` (semua nullable), `cancelled_by_type` (`ActorType` nullable), `cancelled_by_user_id` (nullable FK users), `cancel_reason` (VARCHAR(255) nullable), `schedule_changed_at` (nullable; penanda pasien perlu diberi tahu), `active_flag` (TINYINT, **generated STORED**: `CASE WHEN status IN ('pending','confirmed','checked_in') THEN 1 ELSE NULL END`), timestamps.
  - UNIQUE `booking_code`; UNIQUE (`practice_session_id`, `queue_number`); UNIQUE (`patient_profile_id`, `practice_session_id`, `active_flag`) — pengganti partial unique index yang tidak tersedia di MariaDB (nilai NULL boleh berulang).
  - INDEX (`session_date`, `status`), (`patient_profile_id`, `session_date`), (`doctor_id`, `session_date`), (`status`), (`created_at`).
  - Migration: `$table->tinyInteger('active_flag')->nullable()->storedAs("CASE WHEN status IN ('pending','confirmed','checked_in') THEN 1 ELSE NULL END");`
- **`appointment_status_histories`**: `id`, `appointment_id` (FK `cascadeOnDelete`), `from_status` (nullable), `to_status`, `actor_type`, `actor_user_id` (nullable FK `nullOnDelete`), `reason` (VARCHAR(255) nullable), `created_at` (tanpa `updated_at`).

### E.4 Fasilitas dan kamar
- **Model kapasitas:** agregat per kategori kamar, diperbarui manual. **Tidak ada tabel `rooms` per kamar/tempat tidur** (bisa ditambahkan saat integrasi SIMRS).
- **`room_categories`**: `id`, `name`, `slug` (unique), `short_description`, `description` (rich text), `amenities` (JSON array teks), `bed_capacity` (SMALLINT), `beds_available` (SMALLINT), `sample_rate` (DECIMAL(12,2) nullable), `rate_unit` (VARCHAR(30) default `per malam`), `rate_note` (VARCHAR(255) nullable), `is_active`, `sort_order`, `availability_updated_at` (nullable), `availability_updated_by` (nullable FK users), `meta_title`, `meta_description`, timestamps. CHECK `beds_available BETWEEN 0 AND bed_capacity`. Seed slug: `bpjs-kelas-1`, `bpjs-kelas-2`, `bpjs-kelas-3`, `vip`.
- **`facilities`**: `id`, `name`, `slug` (unique), `category` (`FacilityCategory`), `short_description`, `description` (rich text), `is_active`, `sort_order`, `meta_title`, `meta_description`, timestamps.

### E.5 Konten
- **`pages`**: `id`, `key` (unique), `title`, `subtitle` (nullable), `body` (rich text nullable), `meta_title`, `meta_description`, `is_system` (default true; tidak bisa dihapus), `updated_by` (nullable FK users), timestamps. Kunci baku:

  | `key` | Dipakai di route | Punya `body`? |
  |---|---|---|
  | `home` | `home` | tidak (intro saja) |
  | `about` | `about` | ya |
  | `vision-mission` | `vision-mission` | ya |
  | `clinics-index` | `clinics.index` | tidak |
  | `doctors-index` | `doctors.index` | tidak |
  | `schedules-index` | `schedules.index` | tidak |
  | `facilities-index` | `facilities.index` | tidak |
  | `rooms-index` | `rooms.index` | tidak |
  | `insurance-info` | `insurance` | ya (info BPJS, persyaratan, tarif) |
  | `registration-guide` | `registration-guide` | ya |
  | `articles-index` | `articles.index` | tidak |
  | `galleries-index` | `galleries.index` | tidak |
  | `faq` | `faq` | tidak |
  | `contact` | `contact` | tidak |
  | `privacy-policy` | `privacy` | ya |

  Halaman detail (poliklinik, dokter, fasilitas, kamar, artikel) memakai kolom `meta_title`/`meta_description` miliknya; jika kosong, fallback ke nama + ringkasan.
- **`article_categories`**: `id`, `name`, `slug` (unique), `description` (nullable), `sort_order`, timestamps.
- **`articles`**: `id`, `article_category_id` (nullable FK `nullOnDelete`), `author_user_id` (nullable FK `nullOnDelete`), `title`, `slug` (unique), `excerpt` (VARCHAR(300)), `body` (LONGTEXT rich text), `status` (`ArticleStatus`), `published_at` (nullable), `meta_title`, `meta_description`, timestamps, `deleted_at` (soft delete). INDEX (`status`, `published_at`).
- **`banners`**: `id`, `title`, `subtitle` (nullable), `button_label` (nullable), `button_url` (nullable; path internal atau URL https), `sort_order`, `is_active`, `starts_at`, `ends_at` (nullable), timestamps.
- **`galleries`** (album): `id`, `title`, `slug` (unique), `description` (nullable), `is_published`, `sort_order`, timestamps.
- **`faqs`**: `id`, `category` (VARCHAR(50): `pendaftaran`, `bpjs`, `layanan`, `umum`), `question`, `answer` (TEXT teks biasa), `sort_order`, `is_published`, timestamps.
- **`insurance_partners`**: `id`, `name`, `slug` (unique), `type` (VARCHAR(20): `asuransi`, `perusahaan`), `description` (nullable), `website_url` (nullable), `is_active`, `sort_order`, timestamps. Data seed **fiktif**, berlabel "Contoh"; tidak boleh mengklaim kerja sama nyata.
- **`contact_messages`**: `id`, `name`, `email`, `phone` (nullable), `subject`, `message` (TEXT), `status` (`ContactMessageStatus`, default `new`), `consent_at`, `ip_hash` (CHAR(64), SHA-256 dari IP + APP_KEY; IP mentah tidak disimpan), `handled_by` (nullable FK users), `handled_at` (nullable), timestamps.

### E.6 Sistem
- **`media`** (satu tabel untuk semua gambar, polymorphic): `id`, `model_type`, `model_id` (nullable morph — NULL sementara untuk unggahan editor yang belum terikat), `collection`, `disk` (`public` | `local`), `directory`, `file_name` (UUID + ekstensi, dibuat sistem), `original_name` (disanitasi), `mime_type`, `extension`, `size_bytes`, `width`, `height`, `thumb_path` (nullable), `alt_text` (VARCHAR(255), wajib untuk koleksi publik), `caption` (nullable), `sort_order`, `uploaded_by` (nullable FK users `nullOnDelete`), timestamps. INDEX (`model_type`, `model_id`, `collection`, `sort_order`).
  - Koleksi baku: `photo` (dokter, tunggal), `cover` (poliklinik/fasilitas/kamar/artikel, tunggal), `gallery` (poliklinik/fasilitas/kamar, banyak), `image` (banner, tunggal), `photos` (album galeri, banyak), `logo` (mitra asuransi & logo situs, tunggal), `editor` (gambar sisipan rich text).
- **`website_settings`**: `id`, `key` (unique), `value` (TEXT nullable), `type` (`string|text|int|bool|url|email|json`), `group` (`identitas|kontak|booking|seo|privasi`), `label`, `updated_by` (nullable FK users), timestamps. Di-cache; cache dibersihkan saat disimpan.
- **`audit_logs`**: `id`, `actor_type`, `user_id` (nullable FK `nullOnDelete`), `action` (VARCHAR(100), mis. `appointment.status_changed`), `auditable_type`, `auditable_id` (nullable), `description` (VARCHAR(255)), `old_values` (JSON nullable), `new_values` (JSON nullable), `ip_address` (VARCHAR(45)), `user_agent` (VARCHAR(255)), `created_at`. Tidak bisa diubah/dihapus dari UI. **Redaksi wajib:** `password`, `remember_token`, `token`, `bpjs_number`, `payment_reference` tidak pernah disimpan (diganti `[disamarkan]`).

### E.7 Kunci pengaturan (`website_settings.key`)

| Key | Default seed | Grup |
|---|---|---|
| `site.name` | `UNPAM HOSPITAL` | identitas |
| `site.tagline` | `Layanan kesehatan yang ramah dan terpercaya` (contoh) | identitas |
| `site.logo_media_id` | kosong → pakai `public/images/placeholders/logo.svg` | identitas |
| `contact.address` | `Universitas Pamulang Kampus 2 Viktor, Jl. Raya Puspitek, Buaran, Kecamatan Pamulang, Kota Tangerang Selatan, Banten 15310` | kontak |
| `contact.phone` | `(021) 0000-0000` + catatan "placeholder" | kontak |
| `contact.emergency_phone` | `(021) 0000-0119` (placeholder) | kontak |
| `contact.whatsapp` | `6280000000000` (placeholder; dipakai untuk tautan `https://wa.me/{nomor}`) | kontak |
| `contact.email` | `info@unpamhospital.test` (placeholder) | kontak |
| `contact.operational_hours` | `Senin–Sabtu 08.00–20.00 (contoh)` | kontak |
| `map.directions_url` | `https://www.google.com/maps/search/?api=1&query=Universitas+Pamulang+Kampus+2+Viktor` | kontak |
| `map.embed_url` | `https://www.google.com/maps?q=Universitas+Pamulang+Kampus+2+Viktor&output=embed` | kontak |
| `booking.window_days` | `30` | booking |
| `booking.online_cutoff_minutes` | `60` | booking |
| `booking.cancel_cutoff_minutes` | `120` | booking |
| `booking.checkin_open_minutes` | `60` | booking |
| `booking.max_active_per_patient` | `3` **[ASUMSI]** | booking |
| `booking.auto_confirm` | `false` | booking |
| `seo.default_meta_description` | deskripsi umum situs | seo |
| `privacy.consent_version` | `1.0` | privasi |

---

## F. Mesin Jadwal, Booking, dan Antrean

### F.1 Tiga lapis jadwal
1. `doctor_schedules` = templat mingguan.
2. `schedule_exceptions` = pemblokiran rentang tanggal (cuti/libur).
3. `practice_sessions` = sesi nyata per tanggal (hasil generate + sesi tambahan manual). Edit untuk satu tanggal (geser jam, ubah kuota, tutup) dilakukan langsung di sesi dan menyetel `is_manually_modified = true` agar tidak ditimpa regenerasi.

### F.2 Pembangkitan sesi — `PracticeSessionGenerator`
- Idempoten: `insertOrIgnore` berdasarkan UNIQUE (`doctor_schedule_id`, `session_date`) untuk rentang hari ini … hari ini + `booking.window_days`.
- Hanya templat `is_active = true` dalam rentang berlaku, dengan dokter `status = active` dan poliklinik `is_active = true`, yang dibangkitkan; templat lain dilewati.
- Sesi baru yang jatuh pada rentang `schedule_exceptions` langsung dibuat berstatus `cancelled` dengan `schedule_exception_id` terisi (tampil "Cuti"/"Libur" di jadwal publik).
- Dipicu: (a) setiap simpan templat/pengecualian; (b) maksimal **sekali per hari** saat halaman jadwal publik atau wizard booking dibuka (`Cache::lock` + penanda `sessions:generated:{Y-m-d}`); (c) manual `php artisan sessions:generate {--days=}`.

### F.3 `DoctorAvailabilityQuery` — satu sumber jadwal
Dipakai **bersama** oleh halaman publik (`doctors.index` filter tanggal, `doctors.show`, `schedules.index`) dan wizard booking (`patient.appointments.create`) serta form walk-in admin. Mengembalikan per sesi: data sesi, `remaining = quota - booked_count`, `availability` (enum `Availability`), `is_bookable_online`, dan `is_bookable_counter`.
- `is_bookable_online` jika: status `open`; dokter `active`; poliklinik `is_active` dan `is_bookable`; `session_date` dalam jendela; `now < start − booking.online_cutoff_minutes`; `remaining > 0`.
- `is_bookable_counter` (loket/walk-in) jika: syarat yang sama kecuali batas waktu menjadi `now < end_time` pada `session_date`.
- **Prioritas label `availability`** (yang pertama cocok dipakai): `past` (sekarang ≥ waktu selesai sesi) → bila status `cancelled`: `leave` (pengecualian `doctor_leave`), `holiday` (`hospital_holiday`/`national_holiday`), selain itu `cancelled` → `under_review` (`needs_review`) → `closed` → `full` (`remaining = 0`) → `booking_closed` (lewat batas online) → `available`.

### F.4 Perubahan jadwal setelah ada booking — `ScheduleChangeService`
- **Ubah templat** (hanya mempengaruhi sesi `session_date >= hari ini`, berasal dari templat itu, dan `is_manually_modified = false`):
  - Sesi **tanpa appointment sama sekali** → dihapus lalu digenerate ulang.
  - Sesi tanpa appointment **aktif** (hanya riwayat batal) → diperbarui di tempat, atau `cancelled` bila tidak lagi valid.
  - Sesi dengan appointment aktif:
    - Perubahan **kuota saja** → diterapkan sebagai `max(kuota_baru, booked_count)`; admin diberi pesan jumlah sesi yang disesuaikan.
    - Perubahan **jam**, **hari**, penonaktifan, atau penyempitan rentang berlaku → sesi menjadi `needs_review` dengan `pending_change`.
  - **Mengganti poliklinik** templat ditolak bila ada sesi mendatang dengan appointment aktif (buat templat baru lalu nonaktifkan yang lama).
- **Tambah pengecualian:** sesi dalam rentang tanpa appointment aktif → `cancelled` (+ `schedule_exception_id`); dengan appointment aktif → `needs_review` (+ `schedule_exception_id`). **Hapus/persempit pengecualian:** sesi yang dibatalkan olehnya dan belum lewat → dibuka kembali (`open`). `start_date` pengecualian tidak boleh di masa lalu.
- **Edit manual satu sesi** yang memiliki appointment aktif: perubahan kuota langsung diterapkan (tidak boleh < `booked_count`); perubahan jam → `needs_review` sumber `manual_time`; pembatalan sesi → `needs_review` sumber `manual_cancel`. Sesi tanpa appointment aktif langsung diubah/dibatalkan.
- **Dokter dinonaktifkan:** sesi mendatang tanpa appointment aktif → `cancelled`; dengan appointment aktif → `needs_review` sumber `doctor_inactive`. **Dokter diarsipkan** hanya bila tidak ada appointment aktif mendatang (selesaikan sesi terdampak dahulu); dokter arsip tidak tampil di publik dan tidak bisa diberi jadwal.
- **Pengecualian dicabut/dipersempit** sementara sesi terkait masih `needs_review` (belum diselesaikan) → sesi kembali `open`, `pending_change` dan `schedule_exception_id` dikosongkan (bila sesi belum lewat).
- Format `pending_change` (JSON):
  - `{"source":"exception","exception_id":12}`
  - `{"source":"template_time","schedule_id":3,"new_start_time":"09:00","new_end_time":"12:00"}`
  - `{"source":"template_removed","schedule_id":3}` (hari berubah, templat dinonaktifkan, atau di luar rentang berlaku)
  - `{"source":"manual_time","new_start_time":"13:00","new_end_time":"15:00"}`
  - `{"source":"manual_cancel","reason":"…"}`
  - `{"source":"doctor_inactive","doctor_id":7}`
- **Satu sesi hanya menyimpan satu `pending_change`.** Bila sumber baru datang saat sesi masih `needs_review`: sumber jenis-batal (`exception`, `template_removed`, `manual_cancel`, `doctor_inactive`) mengalahkan sumber jenis-jam (`template_time`, `manual_time`); pada tingkat yang sama, sumber terbaru menggantikan yang lama. Penggantian dicatat di audit log.
- Status `needs_review` = tertutup untuk booking baru; booking lama tetap. Muncul di layar **Sesi terdampak** (`admin.sessions.review`). Resolusi oleh izin `sessions.review`:
  - `keep` → sesi tetap dengan nilai lama, status `open`, `is_manually_modified = true`.
  - `apply` (hanya sumber jenis-jam: `template_time` dan `manual_time`) → jam diperbarui, status `open`, appointment aktif diberi `schedule_changed_at = now()` + notifikasi. Layar menampilkan peringatan bila jam baru berbenturan dengan booking lain milik pasien yang sama.
  - `cancel` → sesi `cancelled`; seluruh appointment aktif → `cancelled` (`cancelled_by_type = staff`, alasan wajib), kuota dilepas, notifikasi dikirim; petugas dapat membuat booking pengganti.
  - Pilihan per sumber: `exception` → keep | cancel; `template_time` → keep | apply | cancel; `template_removed` → keep | cancel; `manual_time` → keep | apply | cancel; `manual_cancel` → keep | cancel; `doctor_inactive` → keep | cancel (`keep` hanya bila dokter diaktifkan kembali, selain itu wajib `cancel`).
- Kuota sesi (edit manual) tidak bisa diturunkan di bawah `booked_count`.

### F.5 Aturan booking — `BookingService::book()`
Semua diperiksa **di dalam transaksi** setelah penguncian:
1. Sesi `open`; dokter `active`; poliklinik aktif & `is_bookable`.
2. `session_date` antara hari ini dan hari ini + `booking.window_days`.
3. Online: `now < start − booking.online_cutoff_minutes`. Loket (petugas): `now < end_time` pada tanggal sesi.
4. `booked_count < quota`.
5. Tidak ada appointment aktif pasien pada sesi yang sama.
6. Tidak ada appointment aktif pasien pada tanggal sama di sesi lain yang jamnya beririsan (`start1 < end2 AND start2 < end1`).
7. Jumlah appointment aktif mendatang milik pasien `< booking.max_active_per_patient` (online saja).
8. `payment_type = bpjs` → `payment_reference` (nomor BPJS, 13 digit) wajib; `asuransi` → `insurance_partner_id` wajib + nomor polis opsional; `umum` → keduanya kosong.

Kode galat `BookingException`: `SESSION_UNAVAILABLE`, `OUTSIDE_WINDOW`, `CUTOFF_PASSED`, `QUOTA_FULL`, `DUPLICATE_BOOKING`, `OVERLAPPING_BOOKING`, `ACTIVE_LIMIT_REACHED`, `INVALID_PAYMENT`, `SYSTEM_BUSY`. Masing-masing punya pesan Bahasa Indonesia yang jelas. `SYSTEM_BUSY` ("Sistem sedang sibuk, silakan coba lagi beberapa saat.") dipakai bila lock wait timeout (errno 1205) atau deadlock (errno 1213) masih terjadi setelah `attempts: 3`; tidak ada data yang tersimpan sebagian. Transisi status yang tidak diizinkan melempar `InvalidTransitionException` dengan kode baku: `INVALID_TRANSITION`, `ALREADY_FINAL`, `FORBIDDEN_TRANSITION`, `STALE_STATUS`, `CANCEL_CUTOFF_PASSED`, `CONFIRM_AFTER_END`, `CHECKIN_NOT_TODAY`, `CHECKIN_NOT_OPEN`, `CHECKIN_CLOSED`, `CORRECTION_NOT_SAME_DAY`, `SESSION_NOT_ENDED`, `REASON_REQUIRED`. Galat lain pada resolusi sesi terdampak dan penghubungan akun memakai `ValidationException` (tidak ada kelas exception khusus). `BookingService::precheck()` menjalankan pemeriksaan F.5 **tanpa kunci dan tanpa menulis** untuk langkah tinjau wizard/walk-in; `book()` tetap memeriksa ulang di dalam transaksi.

### F.6 Konkurensi
```php
DB::transaction(function () use ($patientId, $sessionId, $data, $actor) {
    $patient = PatientProfile::whereKey($patientId)->lockForUpdate()->firstOrFail();   // 1) serialisasi per pasien (benturan & duplikat)
    $session = PracticeSession::whereKey($sessionId)->lockForUpdate()->firstOrFail();  // 2) serialisasi per sesi (kuota)
    // validasi F.5 …
    $session->last_queue_number += 1;
    $session->booked_count += 1;
    $session->save();
    $appointment = Appointment::create([... 'queue_number' => $session->last_queue_number, ...]);
    // riwayat status + audit log (+ notifikasi setelah commit)
}, attempts: 3);
```
- **Urutan kunci baku untuk semua operasi** (booking, batal, transisi status, resolusi sesi): `patient_profiles` → `practice_sessions` → `appointments`. Mencegah deadlock.
- Lapis pengaman DB: UNIQUE (`practice_session_id`, `queue_number`), UNIQUE `booking_code`, UNIQUE (`patient_profile_id`, `practice_session_id`, `active_flag`), CHECK `booked_count <= quota`. Pelanggaran constraint diterjemahkan menjadi `BookingException` yang sesuai: duplikat UNIQUE = SQLSTATE 23000 / errno 1062; pelanggaran CHECK = errno **4025** di MariaDB (errno 3819 di MySQL 8) — periksa `errorInfo[1]`, jangan hanya SQLSTATE.
- Cegah kirim ganda: tombol dinonaktifkan saat submit + `Cache::lock("booking-submit:{user_id}", 10)`.

### F.7 Kode booking dan nomor antrean
- `booking_code`: `UPH-` + 8 karakter acak Crockford Base32 (alfabet `0123456789ABCDEFGHJKMNPQRSTVWXYZ`, ±40 bit) dalam format `UPH-XXXX-XXXX`, dibuat dengan `random_int`; ulangi bila bentrok. **Tidak memuat data pasien.** QR code (SVG) hanya berisi string kode ini.
- Nomor antrean: `queue_number` = `++last_queue_number` per sesi (unik per dokter + tanggal + sesi), monoton naik, **tidak pernah dipakai ulang** walau dibatalkan. Tampilan: `{clinics.code}-{3 digit}`, mis. `ANK-007`.
- Check-in di loket: petugas mengetik atau memindai (scanner USB berperilaku sebagai keyboard) kode booking di kolom pencarian antrean — tidak butuh API kamera.

### F.8 Status dan transisi — `AppointmentStatusService::transition()`

| Status | Mengurangi kuota? |
|---|---|
| `pending`, `confirmed`, `checked_in`, `completed`, `no_show` | Ya |
| `cancelled` | Tidak — `booked_count` dikurangi 1 (kuota terbuka kembali selama sesi masih bisa dipesan) |

| Dari → Ke | Pelaku (izin) | Syarat |
|---|---|---|
| (baru) → `pending` | Pasien online | Default; jika `booking.auto_confirm = true` langsung `confirmed`. |
| (baru) → `confirmed` | Petugas (`appointments.manage`), kanal loket | — |
| `pending` → `confirmed` | Petugas | Sebelum sesi berakhir. |
| `pending`/`confirmed` → `checked_in` | Petugas | Pada `session_date`, dari `start − booking.checkin_open_minutes` sampai `end_time`. |
| `checked_in` → `completed` | Petugas | — |
| `checked_in` → `confirmed` | Petugas | Koreksi salah check-in, hari yang sama, alasan wajib. |
| `pending`/`confirmed` → `cancelled` | Pasien pemilik | `now <= start − booking.cancel_cutoff_minutes`. Setelah batas, pasien diarahkan menghubungi RS (telepon/WhatsApp). |
| `pending`/`confirmed` → `cancelled` | Petugas / sistem | Kapan saja sebelum check-in; alasan wajib. |
| `pending`/`confirmed` → `no_show` | Petugas (aksi massal per sesi) atau `php artisan appointments:mark-no-show` | Setelah `end_time` sesi lewat. Perintah tanpa `--date` memproses **semua** sesi yang waktu selesainya sudah lewat hingga saat ini; dengan `--date=YYYY-MM-DD` hanya tanggal itu. |

- `completed`, `cancelled`, `no_show` = **final**.
- Setiap transisi: isi kolom waktu terkait, tulis `appointment_status_histories`, tulis `audit_logs` (aksi staf/sistem), kirim notifikasi ke pasien berakun: kanal `database` untuk **setiap** transisi; kanal `mail` hanya untuk `confirmed`, `cancelled`, dan perubahan jadwal (`schedule_changed_at`) — default K-13 di bagian 17 (mail lokal masuk log). Pasien walk-in tanpa akun dihubungi petugas lewat telepon yang tercatat.

### F.9 Walk-in dan penghubungan akun
- Walk-in: petugas mencari profil (nama/no. pasien/telepon/tanggal lahir) atau membuat profil baru (persetujuan privasi lisan dicentang petugas) → memilih sesi via `DoctorAvailabilityQuery` → `BookingService::book()` dengan kanal `loket`, status awal `confirmed`.
- **Hubungkan profil walk-in ke akun online** hanya oleh petugas setelah verifikasi identitas di loket (tidak ada pencocokan otomatis, untuk mencegah pengambilalihan profil). Diizinkan hanya bila profil milik akun tersebut **belum punya appointment**: dalam satu transaksi, profil kosong milik akun dihapus lalu `user_id` dipasang ke profil walk-in. Penggabungan dua profil yang sama-sama memiliki riwayat = pengembangan lanjutan.

### F.10 Bukti pendaftaran
- Halaman cetak (CSS print) `patient.appointments.proof` dan PDF `patient.appointments.proof-pdf` (dompdf); versi admin `admin.appointments.proof`.
- Isi: nama situs/logo, kode booking + QR, nama pasien + no. pasien, poliklinik, dokter, tanggal, jam sesi, nomor antrean, jenis pembayaran (nomor BPJS/polis hanya 4 digit terakhir), status, instruksi check-in, waktu cetak.
- Akses: `AppointmentPolicy::view` → pemilik profil, atau staf dengan `appointments.view`. URL memakai `booking_code`, tetapi policy tetap diperiksa.

---

## G. Peran dan Izin

**Peran (Spatie, guard `web`):** `super-admin` (Super Admin), `content-admin` (Admin Konten), `registration-officer` (Petugas Pendaftaran). Super Admin melewati semua pemeriksaan melalui `Gate::before`. Peran `super-admin` terkunci (tidak bisa diubah izinnya/dihapus); minimal satu Super Admin aktif harus selalu ada; staf tidak dapat menonaktifkan atau mengubah peran akunnya sendiri. Karena `Gate::before` melewati Policy, **aturan mutlak tersebut ditegakkan di service (`StaffAccountService`) dan Form Request**, bukan di Policy.

**Izin baku:**

| Izin | Arti | super-admin | content-admin | registration-officer |
|---|---|:-:|:-:|:-:|
| `dashboard.view` | Lihat dashboard (widget difilter per izin) | ✔ | ✔ | ✔ |
| `doctors.view` | Lihat dokter | ✔ | ✔ | ✔ |
| `doctors.manage` | CRUD, foto, aktif/nonaktif, arsip dokter | ✔ | ✔ | — |
| `clinics.manage` | CRUD spesialisasi & poliklinik | ✔ | ✔ | — |
| `schedules.view` | Lihat templat, sesi, pengecualian | ✔ | ✔ | ✔ |
| `schedules.manage` | Kelola templat, sesi, pengecualian | ✔ | ✔ | — |
| `sessions.review` | Selesaikan sesi `needs_review` | ✔ | — | ✔ |
| `facilities.manage` | CRUD fasilitas & kategori kamar | ✔ | ✔ | — |
| `rooms.update-availability` | Perbarui `beds_available` | ✔ | ✔ | ✔ **[ASUMSI]** |
| `content.manage` | Halaman, banner, artikel, kategori artikel, galeri, FAQ, mitra asuransi | ✔ | ✔ | — |
| `patients.view` | Lihat data pasien | ✔ | — | ✔ |
| `patients.manage` | Buat walk-in, ubah, hubungkan akun, nonaktifkan | ✔ | — | ✔ |
| `appointments.view` | Lihat booking & bukti | ✔ | — | ✔ |
| `appointments.manage` | Buat walk-in, konfirmasi, check-in, selesai, batal, tidak hadir | ✔ | — | ✔ |
| `reports.export` | Ekspor CSV pendaftaran | ✔ | — | ✔ |
| `messages.manage` | Kotak pesan kontak | ✔ | — | ✔ **[ASUMSI]** |
| `settings.manage` | Pengaturan website | ✔ | — | — |
| `staff.manage` | Akun staf & hak akses peran | ✔ | — | — |
| `audit.view` | Audit log | ✔ | — | — |

- Admin Konten **tidak dapat melihat data pribadi pasien**; pada layar jadwal ia hanya melihat **jumlah** booking per sesi, dan setelah mengubah jadwal ia diberi tahu "N sesi perlu ditindaklanjuti petugas pendaftaran".
- Dashboard: angka agregat (dokter aktif, pasien terdaftar, pendaftaran hari ini, per status, grafik, ringkasan kamar) boleh untuk semua `dashboard.view`; daftar yang memuat nama pasien hanya untuk `appointments.view`.
- Penegakan: middleware route `permission:*` + Policy per model + Form Request `authorize()`. Menu disembunyikan dengan `@can` hanya sebagai kenyamanan.

---

## H. Autentikasi

- Satu tabel `users`, satu guard `web`. Middleware `account:patient` / `account:staff` (`EnsureAccountType`), `active` (`EnsureAccountIsActive`), `password.changed` (`EnsurePasswordChanged`, untuk staf dengan `must_change_password`).
- `/masuk` hanya menerima akun pasien; `/admin/login` hanya akun staf. Pesan galat login generik ("Email atau password salah") untuk kedua kasus.
- Login: `Auth::attempt` + `session()->regenerate()`; logout: `invalidate()` + `regenerateToken()`; update `last_login_at`.
- Password: `Hash`/cast `hashed` (bcrypt); aturan `Password::min(8)->letters()->numbers()`. `uncompromised()` **tidak** dipakai karena butuh internet.
- Reset password: password broker bawaan; tautan dikirim lewat mail (lokal → `storage/logs/laravel-YYYY-MM-DD.log` karena `LOG_CHANNEL=daily`, atau Mailpit). **Tautan tidak pernah ditampilkan di halaman.** Respons permintaan reset selalu generik (tidak membocorkan apakah email terdaftar).
- Registrasi pasien: transaksi membuat `users` (`account_type = patient`) + `patient_profiles` (`registration_channel = online`, `consent_at`, `consent_version`).
- Akun staf dibuat oleh Super Admin dengan password sementara + `must_change_password = true`.

**Rate limiter (`RateLimiter::for`)**

| Nama | Batas | Kunci |
|---|---|---|
| `login` | 5 / menit | email + IP |
| `password-reset` | 3 / 10 menit | email + IP |
| `register` | 5 / jam | IP |
| `booking` | 10 / menit | user id |
| `contact` | 3 / 10 menit | IP |

---

## I. Route Kanonik

### I.1 Publik (middleware `web`)
| # | Halaman | Method & URI | Nama route |
|---|---|---|---|
| 1 | Beranda | GET `/` | `home` |
| 2 | Profil | GET `/profil` | `about` |
| 3 | Visi & misi | GET `/visi-misi` | `vision-mission` |
| 4 | Daftar layanan/poliklinik | GET `/layanan` | `clinics.index` |
| 5 | Detail layanan | GET `/layanan/{clinic:slug}` | `clinics.show` |
| 6 | Daftar dokter (filter `q`, `spesialisasi`, `poli`, `tanggal`) | GET `/dokter` | `doctors.index` |
| 7 | Detail dokter | GET `/dokter/{doctor:slug}` | `doctors.show` |
| 8 | Jadwal dokter (filter `minggu`, `poli`, `dokter`) | GET `/jadwal-dokter` | `schedules.index` |
| 9 | Daftar fasilitas | GET `/fasilitas` | `facilities.index` |
| 10 | Detail fasilitas | GET `/fasilitas/{facility:slug}` | `facilities.show` |
| 11 | Ringkasan kamar rawat inap | GET `/kamar-rawat-inap` | `rooms.index` |
| 12–15 | Kamar BPJS Kelas 1/2/3, VIP | GET `/kamar-rawat-inap/{roomCategory:slug}` | `rooms.show` |
| 16 | Info BPJS & asuransi | GET `/bpjs-asuransi` | `insurance` |
| 17 | Panduan pendaftaran | GET `/panduan-pendaftaran` | `registration-guide` |
| 18 | Daftar artikel (filter `kategori`, `q`) | GET `/artikel` | `articles.index` |
| 19 | Detail artikel | GET `/artikel/{article:slug}` | `articles.show` |
| 20 | Galeri | GET `/galeri` | `galleries.index` |
| — | Detail album (layar tambahan) | GET `/galeri/{gallery:slug}` | `galleries.show` |
| 21 | FAQ | GET `/faq` | `faq` |
| 22 | Kontak & lokasi | GET `/kontak`; POST `/kontak` (throttle `contact`) | `contact`; `contact.store` |
| — | Kebijakan privasi (layar tambahan wajib) | GET `/kebijakan-privasi` | `privacy` |

### I.2 Autentikasi pasien (middleware `guest`, kecuali logout)
| # | Halaman | Method & URI | Nama route |
|---|---|---|---|
| 23 | Registrasi | GET/POST `/daftar` | `register` / `register.store` |
| 24 | Login | GET/POST `/masuk` | `login` / `login.store` |
| — | Logout | POST `/keluar` (auth) | `logout` |
| 25 | Permintaan reset | GET/POST `/lupa-password` | `password.request` / `password.email` |
| 26 | Form reset | GET `/reset-password/{token}`; POST `/reset-password` | `password.reset` / `password.update` |

### I.3 Portal pasien (prefix `/pasien`, nama `patient.`, middleware `auth`, `account:patient`, `active`)
| # | Halaman | Method & URI | Nama route |
|---|---|---|---|
| 27 | Dashboard | GET `/pasien` | `patient.dashboard` |
| 28 | Profil & akun | GET `/pasien/profil`; PUT `/pasien/profil`; PUT `/pasien/password` | `patient.profile.edit` / `patient.profile.update` / `patient.password.update` |
| 29 | Pendaftaran kunjungan (wizard; langkah via query `poli`, `dokter`, `sesi`) | GET `/pasien/pendaftaran/baru`; POST `/pasien/pendaftaran/tinjau`; POST `/pasien/pendaftaran` (throttle `booking`) | `patient.appointments.create` / `patient.appointments.review` / `patient.appointments.store` |
| 30 | Riwayat & detail & bukti | GET `/pasien/pendaftaran`; GET `/pasien/pendaftaran/{appointment:booking_code}`; POST `.../batal`; GET `.../bukti`; GET `.../bukti.pdf` | `patient.appointments.index` / `patient.appointments.show` / `patient.appointments.cancel` / `patient.appointments.proof` / `patient.appointments.proof-pdf` |
| — | Tandai notifikasi dibaca | POST `/pasien/notifikasi/{id}/baca` | `patient.notifications.read` |

Wizard berjalan tanpa JavaScript (setiap langkah adalah form GET yang dirender server); JavaScript hanya memperhalus (indikator loading, tampilkan/sembunyikan field BPJS/asuransi). `patient.appointments.review` (POST) memvalidasi lalu **merender** halaman konfirmasi langsung (tidak menyimpan apa pun); tombol "Ubah" adalah tautan GET ke `patient.appointments.create` dengan query `poli`/`dokter`/`sesi`. Kegagalan validasi pada `review` maupun `store` **dialihkan ke `patient.appointments.create` dengan query langkah terakhir + `withInput()`**, tidak pernah `back()` (yang akan berujung 405 karena halaman konfirmasi adalah hasil POST). Tombol "Daftar" di jadwal publik menaut ke `patient.appointments.create?sesi={id}` (tamu diarahkan login lalu kembali — *intended URL*).

### I.4 Admin (prefix `/admin`, nama `admin.`; semua kecuali login memakai `auth`, `account:staff`, `active`, `password.changed`, lalu `permission:*`)

`password.changed` **tidak** dipasang pada `admin.password.edit`, `admin.password.update`, dan `admin.logout` (mencegah redirect berulang).
| # | Halaman | Route utama (nama) | Izin |
|---|---|---|---|
| 31 | Login admin | GET/POST `/admin/login` (`admin.login` / `admin.login.store`); POST `/admin/logout` (`admin.logout`); GET/PUT `/admin/ganti-password` (`admin.password.edit` / `admin.password.update`) | guest / auth staf |
| 32 | Dashboard | GET `/admin` (`admin.dashboard`); GET `/admin/dashboard/grafik` JSON (`admin.dashboard.chart`) | `dashboard.view` |
| 33 | Dokter | resource `/admin/dokter` (`admin.doctors.*`: index, create, store, show, edit, update); PATCH `{doctor}/status` (`admin.doctors.status`); PATCH `{doctor}/arsip` (`admin.doctors.archive`); POST `{doctor}/foto` (`admin.doctors.photo`) | `doctors.view` / `doctors.manage` |
| 34 | Spesialisasi & poliklinik | resource `/admin/spesialisasi` (`admin.specialties.*`); resource `/admin/poliklinik` (`admin.clinics.*`) | `clinics.manage` |
| 35 | Jadwal, kuota, libur | resource `/admin/jadwal` (`admin.schedules.*`, templat); `/admin/jadwal/sesi` (`admin.sessions.index`, `admin.sessions.create`, `admin.sessions.store`, `admin.sessions.edit`, `admin.sessions.update`, PATCH `admin.sessions.status`, DELETE `admin.sessions.destroy` — hanya sesi tambahan manual tanpa appointment); resource `/admin/jadwal/pengecualian` (`admin.exceptions.*`); GET `/admin/jadwal/sesi-terdampak` (`admin.sessions.review`); POST `/admin/jadwal/sesi-terdampak/{session}` (`admin.sessions.resolve`) | `schedules.view` / `schedules.manage` / `sessions.review` |
| 36 | Fasilitas, kamar, kapasitas | resource `/admin/fasilitas` (`admin.facilities.*`); resource `/admin/kamar` (`admin.room-categories.*`); PATCH `/admin/kamar/{roomCategory}/ketersediaan` (`admin.room-categories.availability`) | `facilities.manage` / `rooms.update-availability` (`admin.room-categories.index` dapat dibuka dengan salah satu dari dua izin ini; pemegang `rooms.update-availability` saja hanya melihat daftar + form ketersediaan) |
| 37 | Pasien | `/admin/pasien` (`admin.patients.index`, `create`, `store`, `show`, `edit`, `update`); PATCH `{patient}/status` (`admin.patients.status`); POST `{patient}/hubungkan-akun` (`admin.patients.link-account`) | `patients.view` / `patients.manage` |
| 38 | Pendaftaran & antrean | `/admin/pendaftaran` (`admin.appointments.index`, `create`, `store`, `show`); POST `{appointment}/status` (`admin.appointments.transition`); GET `{appointment}/bukti` (`admin.appointments.proof`); GET `/admin/pendaftaran/ekspor` (`admin.appointments.export`); GET `/admin/antrean` (`admin.queue.index`); POST `/admin/antrean/{session}/tidak-hadir` (`admin.queue.mark-no-show`) | `appointments.view` / `appointments.manage` / `reports.export` |
| 39 | Konten | `/admin/konten/halaman` (`admin.pages.index`, `edit`, `update`); resource `/admin/konten/banner` (`admin.banners.*`); resource `/admin/konten/artikel` (`admin.articles.*`); resource `/admin/konten/kategori-artikel` (`admin.article-categories.*`); resource `/admin/konten/galeri` (`admin.galleries.*`) + POST `{gallery}/foto` (`admin.galleries.photos.store`); resource `/admin/konten/faq` (`admin.faqs.*`); resource `/admin/konten/asuransi` (`admin.insurance-partners.*`) | `content.manage` |
| 40 | Pengaturan, staf, hak akses, audit | GET/PUT `/admin/pengaturan` (`admin.settings.edit` / `admin.settings.update`); resource `/admin/staf` (`admin.staff.*`) + PATCH `{user}/status` (`admin.staff.status`) + POST `{user}/reset-password` (`admin.staff.reset-password`); `/admin/hak-akses` (`admin.roles.index`, `admin.roles.edit`, `admin.roles.update`); `/admin/audit-log` (`admin.audit-logs.index`, `admin.audit-logs.show`) | `settings.manage` / `staff.manage` / `audit.view` |
| — | Kotak pesan kontak (layar tambahan) | `/admin/pesan` (`admin.messages.index`, `admin.messages.show`, PATCH `admin.messages.status`) | `messages.manage` |
| — | Media (endpoint pendukung) | POST `/admin/media/editor` (`admin.media.editor-upload`); PATCH `/admin/media/{media}` alt/caption (`admin.media.update`); DELETE `/admin/media/{media}` (`admin.media.destroy`); POST `/admin/media/urutkan` (`admin.media.reorder`) | izin pemilik media |

### I.5 Parameter query baku (nilai)

| Parameter | Dipakai di | Nilai |
|---|---|---|
| `q` | pencarian publik & admin | teks bebas (maks 100 karakter) |
| `spesialisasi` | `doctors.index` | `specialties.slug` |
| `poli` | `doctors.index`, `schedules.index`, wizard | `clinics.slug` |
| `dokter` | `schedules.index`, wizard | `doctors.slug` |
| `tanggal` | `doctors.index` (tanggal praktik), wizard langkah 3, admin antrean | `YYYY-MM-DD` |
| `minggu` | `schedules.index` | `YYYY-MM-DD` (tanggal mana pun dalam minggu; dinormalisasi ke Senin) |
| `sesi` | wizard, tautan "Daftar" jadwal publik, admin walk-in & antrean | `practice_sessions.id` (numerik) |
| `kategori` | `articles.index` | `article_categories.slug` |
| `jadwal_berubah` | `patient.appointments.index` | `1` → hanya appointment dengan `schedule_changed_at` terisi (tautan dari banner notifikasi dashboard) |
| `status`, `kanal`, `dari`, `sampai`, `urut`, `kode`, `pasien`, `kembali` | filter admin (status, kanal, rentang tanggal kunjungan, urutan, kode booking, id profil pasien untuk walk-in, URL kembali internal) | sesuai enum / `YYYY-MM-DD` / id numerik / path internal yang divalidasi |
| `rentang` | `admin.dashboard.chart` | `7`, `30`, `90`, atau `kustom` (dengan `dari`/`sampai`) |
| `pengguna`, `aksi`, `objek` | `admin.audit-logs.index` | id user staf / kode `action` / `auditable_type` singkat |
| `per_page`, `page` | semua daftar ber-pagination | `per_page` ∈ {10, 25, 50} (default 10 publik, 25 admin); `page` ≥ 1 |

Parameter yang tidak valid diabaikan (filter tidak diterapkan) dan tidak menimbulkan galat 500.

### I.6 Route `show` admin
Hanya `admin.doctors.show`, `admin.patients.show`, `admin.appointments.show`, `admin.messages.show`, dan `admin.audit-logs.show` yang memiliki layar detail. Resource admin lain memakai `index` + `create`/`edit` saja (`->except('show')`).

**File route:** `routes/web.php` (publik + auth pasien), `routes/patient.php`, `routes/admin.php`, didaftarkan di `bootstrap/app.php` (`withRouting(... then: ...)`). Middleware alias didaftarkan di `bootstrap/app.php` (Laravel 12 tidak memakai `Http/Kernel.php`).

---

## J. Komponen Aplikasi Kanonik

### J.1 Service (`app/Services`)
| Kelas | Tanggung jawab |
|---|---|
| `Scheduling\PracticeSessionGenerator` | `ensureRange($from, $to)`, `ensureDailyHorizon()` (F.2). |
| `Scheduling\DoctorAvailabilityQuery` | Sumber tunggal jadwal publik & booking (F.3). |
| `Scheduling\ScheduleChangeService` | Propagasi perubahan templat/pengecualian & resolusi sesi (F.4). |
| `Booking\BookingService` | `book(PatientProfile, PracticeSession, BookingData, Actor): Appointment` (F.5–F.6). |
| `Booking\AppointmentStatusService` | `transition(Appointment, AppointmentStatus, Actor, ?string $reason)` (F.8). |
| `Booking\BookingCodeGenerator` | Kode booking (F.7). |
| `Booking\QueueNumberFormatter` | Format `ANK-007`. |
| `Patients\PatientNumberGenerator` | `P000123`. |
| `Patients\PatientAccountLinker` | Hubungkan profil walk-in ke akun (F.9). |
| `Patients\PatientRegistrationService` | Registrasi online: transaksi `users` + `patient_profiles` + persetujuan; juga `updateProfile()` (sinkron email E.1) dan `changeStatus()` (aktif/nonaktif profil + akun). |
| `Staff\StaffAccountService` | Buat/nonaktifkan staf, reset password sementara, ubah peran; menegakkan aturan mutlak bagian G. |
| `Media\MediaService` | `store`, `replace`, `delete`, `prune` (bagian K). |
| `Content\HtmlSanitizer` | Bungkus `mews/purifier` profil `rich_text`. |
| `Audit\AuditLogger` | `log(action, model, old, new, description)` dengan redaksi (E.6). **Katalog kode `action` tunggal ada di bagian 12 §8**; bagian lain merujuk ke sana. |
| `Settings\SettingsService` | `get`, `set`, cache (kunci cache baku `settings:all`, dihapus saat simpan). |
| `Reports\AppointmentCsvExporter` | Ekspor CSV streaming (bagian L). |
| `Dashboard\DashboardMetrics` | Angka & data grafik dashboard. |

### J.2 Perintah Artisan
`sessions:generate {--days=}` · `appointments:mark-no-show {--date=}` · `media:prune {--dry-run}` · `app:create-super-admin` (produksi).

### J.3 Middleware
`EnsureAccountType` (alias `account`), `EnsureAccountIsActive` (`active`), `EnsurePasswordChanged` (`password.changed`), `SecurityHeaders` (global: `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy`, CSP di produksi dengan `frame-src https://www.google.com https://maps.google.com`), alias Spatie `role`, `permission`.

### J.4 Policy
`DoctorPolicy`, `SpecialtyPolicy`, `ClinicPolicy`, `DoctorSchedulePolicy`, `PracticeSessionPolicy`, `ScheduleExceptionPolicy`, `AppointmentPolicy`, `PatientProfilePolicy`, `FacilityPolicy`, `RoomCategoryPolicy`, `PagePolicy`, `BannerPolicy`, `ArticlePolicy`, `ArticleCategoryPolicy`, `GalleryPolicy`, `FaqPolicy`, `InsurancePartnerPolicy`, `ContactMessagePolicy`, `MediaPolicy`, `UserPolicy` (staf), `RolePolicy`, `AuditLogPolicy`. Pengaturan memakai Gate `settings.manage`.

### J.5 Komponen Blade (`resources/views/components`)
`layouts.public`, `layouts.patient`, `layouts.admin`, `layouts.auth`, `breadcrumb`, `page-header`, `doctor-card`, `schedule-table`, `status-badge`, `availability-badge`, `empty-state`, `flash-alert`, `loading-button`, `image` (fallback placeholder + alt), `form.input`, `form.select`, `form.textarea`, `form.checkbox`, `form.radio-group`, `form.file` (pratinjau + alt text), `form.errors-summary`, `data-table` (pencarian, slot filter, pagination Bootstrap 5, tampilan kartu di mobile), `confirm-modal`, `stat-card`, `wizard-steps` (indikator langkah wizard pendaftaran), `module-tabs` (tab sub-modul admin, mis. Templat/Sesi/Pengecualian/Sesi terdampak), `map-embed` (iframe + alamat teks + tombol Petunjuk Arah; tetap tampil tanpa internet).

### J.6 Nama tambahan yang diterima (hasil rekonsiliasi penulisan bagian 01–16)
- **Service/DTO/pendukung:** `Booking\AppointmentProofService` (data bukti + QR), `Support\Actor` (pelaku: tipe + user), `Support\SafeRedirect` (validasi URL `kembali` internal), `Scheduling\AvailabilityFilter` (input) & `Scheduling\SessionAvailability` (output DTO `DoctorAvailabilityQuery`), `Scheduling\ScheduleChangeReport` (ringkasan hasil propagasi perubahan), `Booking\BookingData` (input booking), `Booking\BookingRules` (pemeriksaan F.5), `Media\PlaceholderImageGenerator` (gambar contoh lokal untuk seeder), `Audit\AuditObserver`.
- **Method tambahan:** `ScheduleChangeService::applyDoctorInactive()`, `ScheduleChangeService::allowedResolutions()`, `BookingService::precheck()`.
- **Form Request dokter:** `UpdateDoctorStatusRequest` (`admin.doctors.status`) dan `ArchiveDoctorRequest` (`admin.doctors.archive`) — dua kelas terpisah.
- **Exception:** `App\Exceptions\BookingException` (kode F.5), `App\Exceptions\InvalidTransitionException` (F.8).
- **Rule validasi (`App\Rules`):** `PersonName`, `IndonesianPhone`, `NoHtml`, `InternalOrHttpsUrl`, aturan cek irisan jadwal (`NoOverlapping*`), helper `ImageRules` dan `PasswordRules`.
- **Komponen Blade tambahan:** `form.rich-text` (Quill + textarea fallback), `proof-card` (isi bukti pendaftaran untuk halaman cetak/PDF).
- **File:** `config/navigation.php` (definisi menu admin + izin), entri Vite `resources/scss/app.scss`, `resources/scss/admin.scss`, `resources/js/app.js`, `resources/js/admin.js`.
- **Namespace Form Request/Controller publik:** `App\Http\Controllers\Public\*` dan `App\Http\Requests\Public\*` (PHP ≥ 8.0 mengizinkan kata kunci sebagai bagian nama namespace). Contoh: `Public\StoreContactMessageRequest`.
- **Pengujian:** folder `tests/Feature/Access`, `tests/Feature/Seeding`, `tests/Concurrency`, `tests/Support/Concurrency/book-session.php`; database uji `unpam_hospital_test`, database uji restore `unpam_hospital_restore`.

---

## K. Media dan File

- Disk `public` → `storage/app/public/{collection}/{YYYY}/{MM}/{uuid}.{ext}` (diakses via `php artisan storage:link` → `public/storage`). Disk `local` (privat) → `storage/app/private/...` untuk dokumen pasien di masa depan, dilayani **hanya** lewat controller + policy (bukan URL publik).
- Validasi unggahan (Form Request): `file|image|mimes:jpg,jpeg,png,webp|mimetypes:image/jpeg,image/png,image/webp|max:2048` (banner `max:4096`), dimensi maks 6000×6000 untuk semua, dimensi minimum per koleksi:

  | Koleksi | Minimum (lebar × tinggi) |
  |---|---|
  | `photo` | 300 × 300 |
  | `cover` | 800 × 450 |
  | `gallery`, `photos` | 600 × 400 |
  | `image` (banner) | 1200 × 400 |
  | `logo` | 120 × 40 |
  | `editor` | 200 × 100 |

  **SVG unggahan ditolak** (risiko XSS); hanya placeholder bawaan aplikasi yang SVG.
- Pemrosesan (`MediaService`): baca dengan Intervention Image (GD) → resize lebar maks 1600 px → encode ulang (JPEG kualitas 82 / PNG / WebP) yang sekaligus membuang EXIF dan muatan tersembunyi → simpan dengan nama UUID → buat thumbnail lebar 400 px (`thumb_path`). Nama asli hanya disimpan sebagai metadata tersanitasi.
- **Penggantian:** simpan file + baris baru → setelah transaksi commit (`DB::afterCommit`) hapus file + baris lama.
- **Pembersihan:** `media:prune` menghapus (a) baris `media` tanpa pemilik (`model_id` NULL) berumur > 24 jam, (b) file di disk yang tidak tercatat di `media`. Mendukung `--dry-run`.
- **Placeholder lokal:** `public/images/placeholders/{doctor,clinic,facility,room,article,banner,gallery,logo}.svg`. Accessor URL mengembalikan placeholder bila media NULL; bila file fisik hilang, komponen `x-image` memberi atribut `data-fallback="{url placeholder}"` dan **satu listener terdelegasi** di `resources/js/app.js` (`document.addEventListener('error', …, true)`) mengganti `src` ke placeholder. Tidak memakai atribut inline `onerror` agar kompatibel dengan CSP ketat.
- **Backup/restore:** `scripts/backup.ps1` (mysqldump `--single-transaction --routines --default-character-set=utf8mb4` dari `C:\xampp3\mysql\bin` + zip `storage/app/public` & `storage/app/private`) dan `scripts/restore.ps1`. **`APP_KEY` wajib disimpan terpisah dan aman** — tanpa kunci yang sama, kolom terenkripsi (BPJS/polis) tidak dapat dibaca setelah restore.

## L. Laporan, Dashboard, Notifikasi

- Dashboard (`DashboardMetrics`): dokter aktif, pasien terdaftar (profil aktif), pendaftaran hari ini (berdasarkan `session_date`), jumlah per status, grafik Chart.js jumlah pendaftaran per hari berdasarkan **tanggal kunjungan** (`session_date`) **[ASUMSI]** dengan filter 7/30/90 hari atau rentang kustom, ringkasan kamar (`beds_available`/`bed_capacity` + `availability_updated_at`).
- Ekspor CSV (`admin.appointments.export`, izin `reports.export`): filter tanggal kunjungan, poliklinik, dokter, status, kanal. Kolom: kode booking, tanggal kunjungan, jam sesi, poliklinik, dokter, nomor antrean, no. pasien, nama pasien, jenis pembayaran, status, kanal, dibuat pada. **Tidak** menyertakan nomor BPJS/polis, alamat, telepon, tanggal lahir, keluhan. UTF-8 dengan BOM, pemisah `;` (Excel lokal Indonesia), streaming. Setiap ekspor dicatat di audit log beserta filternya.
- Notifikasi pasien: `AppointmentStatusChangedNotification` & `AppointmentScheduleChangedNotification` (kanal `database` + `mail` sesuai F.8/K-13), serta `PatientWelcomeNotification` (mail, setelah registrasi online; K-22). Banner notifikasi belum dibaca tampil di dashboard pasien.
- WhatsApp: hanya tautan `https://wa.me/{contact.whatsapp}` (dibuka manual oleh pengguna). **Bukan** pengiriman otomatis; WhatsApp API = pengembangan lanjutan.

## M. Desain Visual

- Token warna (SCSS override Bootstrap):

  | Token | Nilai | Kegunaan | Kontras vs putih |
  |---|---|---|---|
  | `$primary` | `#0A4D8C` (biru) | navbar, tombol utama, judul | ±8,6:1 ✔ AA |
  | `$secondary-accent` (`$teal-dark`) | `#0E7C74` (toska tua) | tombol sekunder, tautan aksen, badge | ±5,0:1 ✔ AA |
  | `$teal-light` | `#13A89E` | dekorasi, ilustrasi, garis aksen (bukan teks putih di atasnya) | ±3,0:1 ✘ untuk teks |
  | `$success` | `#1E7D45` (hijau) | status berhasil | ✔ AA |
  | `$body-bg` / `$light` | `#FFFFFF` / `#F3F7FB` | latar | — |
- Font: Plus Jakarta Sans (lokal via npm) dengan fallback system font; ukuran dasar 16 px; tinggi baris 1,6.
- Tombol utama beranda/navigasi: **"Cari Dokter"**, **"Jadwal Dokter"**, **"Daftar Kunjungan"**.
- Aksesibilitas: skip link, fokus terlihat, `aria-current` pada navigasi aktif, label + `aria-describedby` untuk pesan galat, alt text wajib pada unggahan publik, kontras AA, navigasi keyboard (komponen Bootstrap native: offcanvas, dropdown, modal), tabel responsif menjadi kartu di < 768 px.
- Kondisi wajib setiap layar data: **loading** (spinner/`aria-busy`, tombol nonaktif saat submit), **kosong** (`x-empty-state` + aksi), **berhasil** (flash hijau), **gagal** (flash merah + ringkasan galat validasi).
- Logo: placeholder SVG lokal; dapat diganti via `site.logo_media_id`.
- Referensi struktur informasi: rumahsakitmulya.com — **hanya struktur**, tidak menyalin logo, foto, atau teks.

## N. Ruang Lingkup Lanjutan (tidak dibangun di tahap ini)
Integrasi BPJS (VClaim/Antrean Online), SATUSEHAT, SIMRS, payment gateway, WhatsApp API, email produksi (SMTP resmi), rekam medis/diagnosis/resep/hasil lab, sinkronisasi kamar real-time, NIK, verifikasi email wajib, penggabungan dua profil pasien yang sama-sama memiliki riwayat, multi-bahasa, aplikasi mobile. Tidak ada UI yang boleh menyiratkan fitur-fitur ini sudah aktif.
