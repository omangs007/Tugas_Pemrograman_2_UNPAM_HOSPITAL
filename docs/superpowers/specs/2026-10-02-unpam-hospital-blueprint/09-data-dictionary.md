# 09 — Data Dictionary

**Tujuan bagian.** Mendokumentasikan **setiap tabel** database UNPAM HOSPITAL (tabel domain, tabel Spatie, dan tabel bawaan Laravel) sampai level yang langsung dapat dijadikan migration: tujuan tabel, kolom beserta tipe MariaDB nyata, nullability, default, primary key, foreign key (dengan aksi `ON DELETE`), relasi Eloquent, index dan unique, CHECK constraint, cast Eloquent, serta aturan hapus/arsip. Bagian ini juga memuat cuplikan migration khusus MariaDB, urutan migration, invarian data, data sensitif dan retensi, catatan kompatibilitas MariaDB 10.4, serta perkiraan volume. Gambar relasi ada di [08-erd.md](08-erd.md). Semua nama tabel, kolom, dan enum mengikuti [00-keputusan-inti.md](00-keputusan-inti.md) (bagian C, D, E, F, K) **persis**.

**Bagian terkait:** [03-arsitektur.md](03-arsitektur.md) · [04-peran-hak-akses.md](04-peran-hak-akses.md) · [08-erd.md](08-erd.md) · [11-route-controller-model-service.md](11-route-controller-model-service.md) · [12-aturan-bisnis-validasi-keamanan.md](12-aturan-bisnis-validasi-keamanan.md) · [13-data-contoh.md](13-data-contoh.md) · [14-tahapan-implementasi.md](14-tahapan-implementasi.md) · [15-pengujian-penerimaan.md](15-pengujian-penerimaan.md) · [16-instalasi-operasional.md](16-instalasi-operasional.md) · [17-keputusan-konfirmasi.md](17-keputusan-konfirmasi.md)

---

## 1. Konvensi pembacaan dan konvensi skema

### 1.1 Cara membaca tabel kolom

| Kolom tabel | Arti |
|---|---|
| **Kolom** | Nama kolom persis (huruf kecil, snake_case). |
| **Tipe MariaDB** | Tipe nyata yang dihasilkan migration Laravel 12 pada koneksi `mariadb` (MariaDB 10.4.32). `BIGINT UNSIGNED` = `id()`/`foreignId()`. |
| **Null** | `NO` = `NOT NULL`; `YES` = boleh NULL. |
| **Default** | Nilai default di database. `—` = tanpa default (wajib diisi aplikasi); `NULL` = default NULL. |
| **Keterangan** | Makna, sumber nilai, dan aturan yang relevan. |

### 1.2 Konvensi skema (turunan 00 bagian C)

1. **Mesin & charset:** semua tabel InnoDB, `utf8mb4` / `utf8mb4_unicode_ci`. Kolom string dibandingkan tanpa membedakan huruf besar/kecil dan aksen, sehingga UNIQUE pada `email`, `slug`, `booking_code` bersifat *case-insensitive*.
2. **PK:** `id BIGINT UNSIGNED AUTO_INCREMENT` (`$table->id()`), kecuali `notifications` (UUID, disimpan `CHAR(36)`), `sessions`, `cache`, `cache_locks`, `job_batches` (kunci string), `password_reset_tokens` (email), dan tabel pivot (PK komposit).
3. **FK:** `foreignId('x_id')->constrained('tabel')` + aksi eksplisit `->restrictOnDelete()`, `->cascadeOnDelete()`, atau `->nullOnDelete()`. Nama FK default Laravel `{tabel}_{kolom}_foreign` (semuanya ≤ 64 karakter).
4. **Timestamp:** `$table->timestamps()` menghasilkan `created_at`/`updated_at` bertipe `TIMESTAMP NULL`. Kolom waktu bisnis (`confirmed_at`, `published_at`, `last_login_at`, `starts_at`, dst.) memakai `DATETIME NULL` (`$table->dateTime()`), **[ASUMSI]** agar bebas batas tahun 2038 dan tidak bergantung konversi zona sesi `TIMESTAMP`. Tabel *append-only* (`appointment_status_histories`, `audit_logs`) hanya punya `created_at` `TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP` (model memakai `const UPDATED_AT = null`).
5. **Enum:** status/jenis disimpan `VARCHAR(n)` + PHP backed enum (`App\Enums\*`). **Tidak** memakai kolom `ENUM` MariaDB dan **tidak** memasang CHECK atas daftar nilai enum, agar nilai baru tidak memerlukan `ALTER TABLE`. Pengecualian yang disengaja hanya dua: generated column `appointments.active_flag` (bagian 11.1, Invarian I-15) dan CHECK `chk_schedule_exceptions_scope` (bagian 11.3, Invarian I-34), yang hanya membatasi kombinasi jenis pengecualian dengan `doctor_id` (00 E.2) dan dirumuskan sebagai larangan (`type <> 'doctor_leave' OR doctor_id IS NOT NULL`, dst.), sehingga nilai `ExceptionType` baru yang tidak disebut tetap lolos tanpa `ALTER TABLE`.
6. **Boolean:** `TINYINT(1)`. Konvensi default: `is_active` = `1`; `is_published` = `0` **[ASUMSI]** (konten baru tidak tampil sebelum diterbitkan).
7. **Urutan tampil:** `sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0` **[ASUMSI]** pada semua tabel yang punya `sort_order`.
8. **Waktu sesi:** `TIME` dibaca Eloquent sebagai string `HH:MM:SS` (tanpa cast bawaan); gunakan accessor atau `Carbon::createFromFormat('H:i:s', …)`. Sesi tidak boleh melewati tengah malam (CHECK `end_time > start_time`).
9. **JSON:** MariaDB 10.4 tidak punya tipe JSON native; `JSON` hanyalah alias `LONGTEXT` (collation `utf8mb4_bin`) dan MariaDB ≥ 10.4.3 otomatis menambahkan `CHECK (json_valid(kolom))` (diverifikasi pada 10.4.32; nama CHECK otomatis = nama kolom). Kolom JSON dibuat dengan `$table->json('kolom')->nullable()` (sesuai [03-arsitektur.md](03-arsitektur.md) bagian 5.2 butir 2) dan di-cast `array` di Eloquent; tidak ada query ke dalam isi JSON. Dokumen ini menulis tipenya sebagai `LONGTEXT` (JSON).
10. **Rich text:** hanya `pages.body`, `articles.body`, `clinics.description`, `facilities.description`, `room_categories.description` (sudah disanitasi saat disimpan; hanya kolom ini yang dirender `{!! !!}`). Form Request membatasi panjang HTML agar tidak melewati batas tipe (`TEXT` 65.535 byte → batas aplikasi 30.000 karakter).
11. **Nullability yang tidak ditegaskan 00** (**[ASUMSI]**): kolom meta SEO (`meta_title`, `meta_description`), deskripsi rich text, `short_bio`/`bio` dokter, `amenities`, dan kolom jejak (`created_by`, `updated_by`, dst.) dibuat *nullable*; kolom identitas inti (nama, slug, `short_description`, `location`, `address`, `phone` pasien) dibuat `NOT NULL`.
12. **Kata cadangan SQL:** `key` (`pages.key`, `website_settings.key`, `cache.key`) dan `group` (`website_settings.group`) adalah kata cadangan MariaDB. Eloquent/Query Builder otomatis memberi tanda backtick; **SQL mentah wajib memakai backtick** (`` `key` ``, `` `group` ``).
13. **Penamaan index/constraint:** nama index default Laravel (`{tabel}_{kolom…}_{index|unique}`) dipakai selama ≤ 64 karakter. Satu pengecualian yang **wajib eksplisit** karena nama default 70 karakter: `appointments_patient_session_active_unique`. Nama CHECK memakai awalan `chk_` (daftar lengkap di bagian 11.3). Nama constraint adalah bagian dari kontrak: `BookingService` mencocokkan kode galat driver (`errorInfo[1]`: 1062, 4025/3819, 1205, 1213) dan nama constraint pada pesan galat, bukan hanya SQLSTATE 23000 (bagian 11.4).
14. **Penghapusan:** data yang dirujuk riwayat booking tidak pernah dihapus permanen (FK `RESTRICT`); cukup `is_active = false`/`status` diarsipkan.
15. **Penamaan relasi Eloquent** pada dokumen ini mengikuti konvensi (`camelCase`, tunggal untuk `belongsTo`/`hasOne`/`morphOne`, jamak untuk `hasMany`/`belongsToMany`/`morphMany`); bagian [11-route-controller-model-service.md](11-route-controller-model-service.md) memuat daftar model final dan harus memakai nama yang sama.

### 1.3 Ringkasan tabel

| No | Tabel | Kelompok | PK | FK keluar | Perilaku hapus |
|---:|---|---|---|---:|---|
| 1 | `users` | Akun & akses | `id` | 0 | Tidak dihapus; `is_active = false` |
| 2 | `patient_profiles` | Akun & akses | `id` | 2 | Tidak ada aksi hapus; `is_active = false` (satu-satunya penghapusan: profil kosong milik akun saat penghubungan akun, F.9) |
| 3 | `roles` | Spatie | `id` | 0 | Tidak dihapus (3 peran baku) |
| 4 | `permissions` | Spatie | `id` | 0 | Tidak dihapus (19 izin baku) |
| 5 | `model_has_roles` | Spatie | komposit | 1 | Ikut peran/pengguna (aplikasi) |
| 6 | `model_has_permissions` | Spatie | komposit | 1 | Tidak dipakai |
| 7 | `role_has_permissions` | Spatie | komposit | 2 | `CASCADE` dari peran/izin |
| 8 | `password_reset_tokens` | Bawaan Laravel | `email` | 0 | Dihapus setelah dipakai/kedaluwarsa |
| 9 | `sessions` | Bawaan Laravel | `id` | 0 | Garbage collection otomatis |
| 10 | `notifications` | Bawaan Laravel | `id` (UUID) | 0 | Tidak dihapus lewat UI |
| 11 | `cache` | Bawaan Laravel | `key` | 0 | Kedaluwarsa otomatis |
| 12 | `cache_locks` | Bawaan Laravel | `key` | 0 | Kedaluwarsa otomatis |
| 13 | `jobs` | Bawaan Laravel | `id` | 0 | Dihapus worker setelah sukses |
| 14 | `job_batches` | Bawaan Laravel | `id` | 0 | Tidak dipakai (tanpa batch) |
| 15 | `failed_jobs` | Bawaan Laravel | `id` | 0 | Dibersihkan operator |
| 16 | `specialties` | Dokter & jadwal | `id` | 0 | Dihapus hanya bila tidak terpakai |
| 17 | `clinics` | Dokter & jadwal | `id` | 1 | Tidak dihapus bila terpakai; nonaktifkan |
| 18 | `doctors` | Dokter & jadwal | `id` | 1 | Tidak dihapus; `inactive`/`archived` |
| 19 | `doctor_specialties` | Dokter & jadwal | komposit | 2 | Mengikuti dokter/spesialisasi |
| 20 | `doctor_schedules` | Dokter & jadwal | `id` | 4 | Hapus hanya bila tidak ada sesi ber-appointment; selain itu "Nonaktifkan" (00 C) |
| 21 | `schedule_exceptions` | Dokter & jadwal | `id` | 2 | Hapus = cabut lewat `ScheduleChangeService::removeException` (sesi dibuka kembali) |
| 22 | `practice_sessions` | Dokter & jadwal | `id` | 5 | Hapus hanya sesi tambahan manual tanpa appointment; selain itu tutup/batalkan (00 C) |
| 23 | `appointments` | Booking | `id` | 7 | Tidak pernah dihapus |
| 24 | `appointment_status_histories` | Booking | `id` | 2 | Append-only; tidak dihapus |
| 25 | `room_categories` | Fasilitas & kamar | `id` | 1 | Hapus bila tidak dirujuk (tidak ada tabel yang merujuknya, jadi selalu dapat dihapus; media ikut) |
| 26 | `facilities` | Fasilitas & kamar | `id` | 0 | Dapat dihapus (media ikut) |
| 27 | `pages` | Konten | `id` | 1 | Tidak dihapus (`is_system`) |
| 28 | `article_categories` | Konten | `id` | 0 | Dapat dihapus (artikel jadi tanpa kategori) |
| 29 | `articles` | Konten | `id` | 2 | Soft delete |
| 30 | `banners` | Konten | `id` | 0 | Dapat dihapus (media ikut) |
| 31 | `galleries` | Konten | `id` | 0 | Dapat dihapus (media ikut) |
| 32 | `faqs` | Konten | `id` | 0 | Dapat dihapus |
| 33 | `insurance_partners` | Konten | `id` | 0 | Tidak dihapus bila terpakai; nonaktifkan |
| 34 | `contact_messages` | Konten | `id` | 1 | Diarsipkan (`status = archived`) |
| 35 | `media` | Sistem | `id` | 1 | Hapus permanen diizinkan, hanya lewat `MediaService` / `media:prune` |
| 36 | `website_settings` | Sistem | `id` | 1 | Tidak dihapus |
| 37 | `audit_logs` | Sistem | `id` | 1 | Tidak diubah/dihapus dari UI |
| — | `migrations` | Internal framework | `id` | 0 | Dikelola `artisan migrate` |

Total **37 tabel** wajib (00 bagian E) + `migrations` (tabel internal Laravel, dicatat agar backup/restore tidak mengejutkan). Total **38 FOREIGN KEY** (12 `RESTRICT`, 6 `CASCADE`, 20 `SET NULL`); daftar lengkap bernomor ada di [08-erd.md](08-erd.md) bagian 4.1.

---

## 2. Domain: Akun & akses

### 2.1 `users`

**Tujuan.** Satu tabel akun login untuk pasien (`account_type = patient`) dan staf (`account_type = staff`). Satu guard `web`. Peran staf disimpan di tabel Spatie, bukan di sini. Akun tidak pernah dihapus.

| Kolom | Tipe MariaDB | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | NO | AUTO_INCREMENT | PK. |
| `name` | `VARCHAR(255)` | NO | — | Nama akun. Untuk pasien: disalin dari `patient_profiles.full_name` saat registrasi dan diperbarui bersama profil dalam satu transaksi **[ASUMSI]**; sumber nama resmi pasien tetap profil. |
| `email` | `VARCHAR(255)` | NO | — | UNIQUE. Dinormalisasi huruf kecil di Form Request **[ASUMSI]**. Identitas login. Untuk akun pasien, pasien dapat mengubahnya lewat profil (validasi unik di `users`) dan perubahan itu menyinkronkan `patient_profiles.email` dalam transaksi yang sama (00 E.1). |
| `phone` | `VARCHAR(20)` | YES | `NULL` | Telepon akun (opsional). |
| `email_verified_at` | `TIMESTAMP` | YES | `NULL` | Verifikasi email tidak diwajibkan **[ASUMSI]** (00 E.1); kolom bawaan skeleton dipertahankan. |
| `password` | `VARCHAR(255)` | NO | — | Hash bcrypt (cast `hashed`). Aturan `Password::min(8)->letters()->numbers()`. |
| `account_type` | `VARCHAR(10)` | NO | — | `AccountType`: `patient` / `staff`. Tanpa default agar tidak ada akun yang jadi staf/pasien secara tidak sengaja. |
| `is_active` | `TINYINT(1)` | NO | `1` | `0` = tidak boleh login (`EnsureAccountIsActive`). |
| `must_change_password` | `TINYINT(1)` | NO | `0` | `1` untuk staf dengan password sementara (`EnsurePasswordChanged`). |
| `last_login_at` | `DATETIME` | YES | `NULL` | Diperbarui setiap login berhasil. |
| `remember_token` | `VARCHAR(100)` | YES | `NULL` | Bawaan Laravel. |
| `created_at` | `TIMESTAMP` | YES | `NULL` | |
| `updated_at` | `TIMESTAMP` | YES | `NULL` | |

- **Primary key:** `id`.
- **Foreign key:** tidak ada (tabel ini hanya menjadi induk).
- **Relasi Eloquent:** `User::patientProfile()` hasOne `PatientProfile` (`user_id`); `User::createdPatientProfiles()` hasMany `PatientProfile` (`created_by_user_id`); `User::authoredArticles()` hasMany `Article` (`author_user_id`); `User::uploadedMedia()` hasMany `Media` (`uploaded_by`); `User::auditLogs()` hasMany `AuditLog` (`user_id`); `User::roles()` dan `User::permissions()` morphToMany (trait Spatie `HasRoles`); `User::notifications()` dan `unreadNotifications()` morphMany `DatabaseNotification` (trait `Notifiable`). Relasi balik jejak (`created_by`, `updated_by`, `reviewed_by`, `handled_by`, …) didefinisikan di sisi anak sebagai `belongsTo` (`DoctorSchedule::creator()`, `PracticeSession::reviewer()`, dst.), bukan di `User`.
- **Index & unique:** `PRIMARY (id)`; `users_email_unique (email)`.
- **CHECK constraint:** tidak ada (nilai `account_type` dijaga cast enum + Form Request, lihat konvensi 1.2 butir 5).
- **Cast Eloquent:** `email_verified_at` → `datetime`; `password` → `hashed`; `account_type` → `AccountType`; `is_active` → `boolean`; `must_change_password` → `boolean`; `last_login_at` → `datetime`. `$hidden = ['password', 'remember_token']`.
- **Aturan hapus & arsip:** tidak ada route hapus; `is_active = false`. Saat dinonaktifkan, baris `sessions` milik akun dihapus agar langsung keluar. Minimal satu akun `super-admin` aktif harus selalu ada (dijaga `UserPolicy` + service). Profil pasien milik akun nonaktif **tidak** dihapus. Email tetap terpakai (UNIQUE) selama akun ada; pemulihan dilakukan petugas dengan mengaktifkan kembali.

### 2.2 `patient_profiles`

**Tujuan.** Identitas pasien yang dirujuk booking. Dapat milik akun login (`user_id` terisi, kanal `online`) atau pasien walk-in tanpa akun (`user_id` NULL, kanal `loket`, K1). NIK **tidak** dikumpulkan.

| Kolom | Tipe MariaDB | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | NO | AUTO_INCREMENT | PK. |
| `user_id` | `BIGINT UNSIGNED` | YES | `NULL` | FK → `users.id`, **UNIQUE**. NULL = walk-in tanpa akun. |
| `patient_number` | `VARCHAR(10)` | YES | `NULL` | UNIQUE. Format `P` + 6 digit dari `id` (mis. `P000123`; `P1000000` bila id ≥ 1.000.000). Diisi `PatientNumberGenerator` **setelah insert dalam transaksi yang sama** karena `id` baru diketahui setelah insert; nullable di DB hanya selama transaksi (NULL boleh berulang pada UNIQUE). Setelah commit selalu terisi (Invarian I-19). **[ASUMSI]** |
| `full_name` | `VARCHAR(150)` | NO | — | Nama lengkap pasien. |
| `birth_date` | `DATE` | NO | — | Tanggal lahir. |
| `gender` | `CHAR(1)` | NO | — | `Gender`: `L` / `P`. |
| `phone` | `VARCHAR(20)` | NO | — | Telepon kontak (dipakai petugas menghubungi pasien walk-in). |
| `email` | `VARCHAR(255)` | YES | `NULL` | Walk-in tanpa akun: email kontak opsional. Profil berakun: **selalu sama** dengan `users.email` (00 E.1, D-07); perubahan email di profil memperbarui `users.email` dan kolom ini dalam satu transaksi dengan validasi unik di `users`, dan penghubungan akun menyamakannya dengan email akun. |
| `address` | `VARCHAR(500)` | NO | — | Alamat. |
| `bpjs_number` | `TEXT` | YES | `NULL` | Nomor BPJS, **terenkripsi** (`encrypted`). Tersimpan hanya bila pasien mencentang "simpan ke profil" (01 A-14); wizard dapat menyalinnya ke `appointments.payment_reference` di sisi server tanpa menampilkan nomor penuh (01 A-13). Nilai otoritatif per kunjungan ada di `appointments.payment_reference`. |
| `bpjs_number_last4` | `CHAR(4)` | YES | `NULL` | 4 digit terakhir untuk tampilan tersamar; diisi otomatis bersama `bpjs_number`. |
| `registration_channel` | `VARCHAR(10)` | NO | — | `BookingChannel`: `online` / `loket`. |
| `created_by_user_id` | `BIGINT UNSIGNED` | YES | `NULL` | FK → `users.id`. Petugas pembuat profil walk-in; NULL untuk registrasi online. |
| `consent_at` | `DATETIME` | NO | — | Waktu persetujuan privasi (walk-in: dicentang petugas atas persetujuan lisan). |
| `consent_version` | `VARCHAR(20)` | NO | — | Versi kebijakan privasi saat persetujuan (`privacy.consent_version`). |
| `is_active` | `TINYINT(1)` | NO | `1` | `0` = tidak boleh dipakai pada booking baru. |
| `created_at` | `TIMESTAMP` | YES | `NULL` | |
| `updated_at` | `TIMESTAMP` | YES | `NULL` | |

- **Primary key:** `id`.
- **Foreign key:** `user_id` → `users.id` `ON DELETE SET NULL`; `created_by_user_id` → `users.id` `ON DELETE SET NULL`.
- **Relasi Eloquent:** `PatientProfile::user()` belongsTo `User`; `PatientProfile::creator()` belongsTo `User` (`created_by_user_id`); `PatientProfile::appointments()` hasMany `Appointment`.
- **Index & unique:** `PRIMARY (id)`; `patient_profiles_user_id_unique (user_id)`; `patient_profiles_patient_number_unique (patient_number)`; `patient_profiles_created_by_user_id_foreign`; `patient_profiles_full_name_index (full_name)` dan `patient_profiles_phone_index (phone)` untuk pencarian petugas **[ASUMSI]**. Tidak ada UNIQUE pada nama + tanggal lahir (nama kembar sah); `PatientProfileController` hanya menampilkan peringatan kemungkinan duplikat.
- **CHECK constraint:** tidak ada.
- **Cast Eloquent:** `birth_date` → `date`; `gender` → `Gender`; `bpjs_number` → `encrypted`; `registration_channel` → `BookingChannel`; `consent_at` → `datetime`; `is_active` → `boolean`. `$hidden = ['bpjs_number']`. Accessor `bpjs_masked` (mis. `•••• •••• 1234`) memakai `bpjs_number_last4`; mutator `setBpjsNumberAttribute` mengisi `bpjs_number_last4` dari 4 digit terakhir.
- **Aturan hapus & arsip:** profil beriwayat tidak dihapus (FK `RESTRICT` dari `appointments`); `PATCH admin.patients.status` mengubah `is_active` (bila profil punya akun, `users.is_active` ikut diubah dalam transaksi yang sama dan sebaliknya, 00 E.1, D-08; tidak ada route `destroy`). Satu-satunya penghapusan yang diizinkan adalah pada penghubungan akun (F.9): profil **kosong** milik akun dihapus **sebelum** `user_id` dipasang ke profil walk-in, karena `user_id` UNIQUE (urutan: hapus, lalu update, dalam satu transaksi).

### 2.3 `roles` (Spatie)

**Tujuan.** Daftar peran: `super-admin`, `content-admin`, `registration-officer` (guard `web`). Label UI ("Super Admin", …) berasal dari berkas bahasa `lang/id`, bukan kolom **[ASUMSI]**.

| Kolom | Tipe MariaDB | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | NO | AUTO_INCREMENT | PK. |
| `name` | `VARCHAR(255)` | NO | — | Kode peran. |
| `guard_name` | `VARCHAR(255)` | NO | — | `web`. |
| `created_at` | `TIMESTAMP` | YES | `NULL` | |
| `updated_at` | `TIMESTAMP` | YES | `NULL` | |

- **Primary key:** `id`. **Foreign key:** tidak ada.
- **Relasi Eloquent (model `Spatie\Permission\Models\Role`):** `Role::permissions()` belongsToMany `Permission` (pivot `role_has_permissions`); `Role::users()` morphedByMany `User` (pivot `model_has_roles`).
- **Index & unique:** `roles_name_guard_name_unique (name, guard_name)`.
- **CHECK constraint:** tidak ada. **Cast Eloquent:** tidak ada tambahan.
- **Aturan hapus & arsip:** tidak ada route hapus (`admin.roles.*` hanya `index`, `edit`, `update`). Peran `super-admin` terkunci (`RolePolicy`): izin tidak dapat diubah dan peran tidak dapat dihapus.

### 2.4 `permissions` (Spatie)

**Tujuan.** Daftar 19 izin baku (00 bagian G), mis. `appointments.manage`.

| Kolom | Tipe MariaDB | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | NO | AUTO_INCREMENT | PK. |
| `name` | `VARCHAR(255)` | NO | — | Kode izin. |
| `guard_name` | `VARCHAR(255)` | NO | — | `web`. |
| `created_at` | `TIMESTAMP` | YES | `NULL` | |
| `updated_at` | `TIMESTAMP` | YES | `NULL` | |

- **Primary key:** `id`. **Foreign key:** tidak ada.
- **Relasi Eloquent:** `Permission::roles()` belongsToMany `Role`; `Permission::users()` morphedByMany `User`.
- **Index & unique:** `permissions_name_guard_name_unique (name, guard_name)`.
- **CHECK constraint:** tidak ada. **Cast Eloquent:** tidak ada.
- **Aturan hapus & arsip:** izin ditambah/dihapus hanya lewat seeder/migration saat fitur berubah, tidak lewat UI.

### 2.5 `model_has_roles` (Spatie, pivot polymorphic)

**Tujuan.** Menghubungkan pengguna (polymorphic) dengan peran. Hanya akun staf yang punya baris.

| Kolom | Tipe MariaDB | Null | Default | Keterangan |
|---|---|---|---|---|
| `role_id` | `BIGINT UNSIGNED` | NO | — | FK → `roles.id`. |
| `model_type` | `VARCHAR(255)` | NO | — | `App\Models\User`. |
| `model_id` | `BIGINT UNSIGNED` | NO | — | `users.id` (tanpa FK; polymorphic). |

- **Primary key:** komposit (`role_id`, `model_id`, `model_type`) (di MariaDB selalu bernama `PRIMARY`).
- **Foreign key:** `role_id` → `roles.id` `ON DELETE CASCADE`.
- **Relasi Eloquent:** tidak ada model sendiri; diakses lewat `User::roles()` / `Role::users()`.
- **Index & unique:** PK di atas; `model_has_roles_model_id_model_type_index (model_id, model_type)`.
- **CHECK constraint:** tidak ada. **Cast Eloquent:** tidak ada.
- **Aturan hapus & arsip:** baris dibuat/diubah `StaffController` (satu peran per staf **[ASUMSI]**). Tidak ada FK untuk `model_id`; akun tidak dihapus, sehingga tidak ada baris yatim.

### 2.6 `model_has_permissions` (Spatie, pivot polymorphic)

**Tujuan.** Izin langsung ke pengguna. Skema ada (bawaan paket) tetapi **tidak dipakai**: izin hanya lewat peran **[ASUMSI]**.

| Kolom | Tipe MariaDB | Null | Default | Keterangan |
|---|---|---|---|---|
| `permission_id` | `BIGINT UNSIGNED` | NO | — | FK → `permissions.id`. |
| `model_type` | `VARCHAR(255)` | NO | — | `App\Models\User`. |
| `model_id` | `BIGINT UNSIGNED` | NO | — | `users.id` (tanpa FK). |

- **Primary key:** komposit (`permission_id`, `model_id`, `model_type`) (di MariaDB selalu bernama `PRIMARY`).
- **Foreign key:** `permission_id` → `permissions.id` `ON DELETE CASCADE`.
- **Relasi Eloquent:** melalui `User::permissions()` / `Permission::users()`.
- **Index & unique:** PK; `model_has_permissions_model_id_model_type_index (model_id, model_type)`.
- **CHECK constraint:** tidak ada. **Cast Eloquent:** tidak ada.
- **Aturan hapus & arsip:** tabel dibiarkan kosong; tes penerimaan memastikan tidak ada baris.

### 2.7 `role_has_permissions` (Spatie, pivot)

**Tujuan.** Matriks peran ↔ izin (dikelola `admin.roles.*`). Isi awal: `super-admin` = 19 izin; `content-admin` = 9; `registration-officer` = 11 (00 bagian G).

| Kolom | Tipe MariaDB | Null | Default | Keterangan |
|---|---|---|---|---|
| `permission_id` | `BIGINT UNSIGNED` | NO | — | FK → `permissions.id`. |
| `role_id` | `BIGINT UNSIGNED` | NO | — | FK → `roles.id`. |

- **Primary key:** komposit (`permission_id`, `role_id`) (di MariaDB selalu bernama `PRIMARY`).
- **Foreign key:** `permission_id` → `permissions.id` `CASCADE`; `role_id` → `roles.id` `CASCADE`.
- **Relasi Eloquent:** `Role::permissions()` / `Permission::roles()`.
- **Index & unique:** PK; index FK `role_has_permissions_role_id_foreign (role_id)`.
- **CHECK constraint:** tidak ada. **Cast Eloquent:** tidak ada.
- **Aturan hapus & arsip:** diubah via `admin.roles.update`; setelah perubahan wajib `forgetCachedPermissions()` (cache `spatie.permission.cache` ada di tabel `cache`).

### 2.8 `password_reset_tokens` (bawaan Laravel)

**Tujuan.** Token reset password (di-hash). Tautan dikirim lewat mail (lokal: `storage/logs/laravel-YYYY-MM-DD.log` karena `LOG_CHANNEL=daily`, atau Mailpit) dan tidak pernah ditampilkan di halaman.

| Kolom | Tipe MariaDB | Null | Default | Keterangan |
|---|---|---|---|---|
| `email` | `VARCHAR(255)` | NO | — | PK. Kunci alami ke `users.email` (tanpa FK; email dapat belum terdaftar). |
| `token` | `VARCHAR(255)` | NO | — | Hash token. |
| `created_at` | `TIMESTAMP` | YES | `NULL` | Dasar kedaluwarsa (`auth.passwords.users.expire` = 60 menit) dan throttle 60 detik. |

- **Primary key:** `email`. **Foreign key:** tidak ada. **Relasi Eloquent:** tidak ada model (diakses `DatabaseTokenRepository`). **Index & unique:** hanya PK. **CHECK:** tidak ada. **Cast:** tidak ada.
- **Aturan hapus & arsip:** baris dihapus setelah reset berhasil; baris kedaluwarsa tidak dibersihkan otomatis (tanpa scheduler) — dibersihkan manual `php artisan auth:clear-resets`; tidak berbahaya karena hanya hash dan sudah kedaluwarsa. Tabel ini tidak punya kolom `updated_at`.

### 2.9 `sessions` (bawaan Laravel)

**Tujuan.** Penyimpanan session (`SESSION_DRIVER=database`).

| Kolom | Tipe MariaDB | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `VARCHAR(255)` | NO | — | PK. ID session. |
| `user_id` | `BIGINT UNSIGNED` | YES | `NULL` | Pemilik session; **hanya index, tanpa FK** (desain bawaan Laravel). |
| `ip_address` | `VARCHAR(45)` | YES | `NULL` | |
| `user_agent` | `TEXT` | YES | `NULL` | |
| `payload` | `LONGTEXT` | NO | — | Data session terenkripsi/serial. |
| `last_activity` | `INT` | NO | — | Epoch detik; di-index untuk garbage collection. |

- **Primary key:** `id`. **Foreign key:** tidak ada. **Relasi Eloquent:** tidak ada model. **Index & unique:** `sessions_user_id_index (user_id)`; `sessions_last_activity_index (last_activity)`. **CHECK:** tidak ada. **Cast:** tidak ada.
- **Aturan hapus & arsip:** garbage collection otomatis oleh Laravel (lotere `session.lottery`, umur `SESSION_LIFETIME`). Saat akun dinonaktifkan atau password diganti/direset staf, baris `user_id` terkait dihapus (`DB::table('sessions')->where('user_id', $id)->delete()`).

### 2.10 `notifications` (bawaan Laravel)

**Tujuan.** Notifikasi dalam aplikasi (kanal `database`) untuk pasien berakun: `AppointmentStatusChangedNotification`, `AppointmentScheduleChangedNotification`. Kanal `database` mencatat **setiap** transisi status; kanal `mail` hanya untuk `confirmed`, `cancelled`, dan perubahan jadwal (00 F.8; K-13 (b), diputuskan pemilik 2026-10-08, di 17), sehingga tabel ini tetap memuat semua transisi. Banner "belum dibaca" tampil di dashboard pasien.

| Kolom | Tipe MariaDB | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `CHAR(36)` | NO | — | PK. UUID teks. Migration bawaan `uuid('id')` menghasilkan `CHAR(36)` pada MariaDB 10.4 (diverifikasi); tipe `UUID` native baru ada di MariaDB ≥ 10.7 (lihat bagian 14). |
| `type` | `VARCHAR(255)` | NO | — | Nama kelas notifikasi. |
| `notifiable_type` | `VARCHAR(255)` | NO | — | `App\Models\User`. |
| `notifiable_id` | `BIGINT UNSIGNED` | NO | — | `users.id` (tanpa FK; polymorphic). |
| `data` | `TEXT` | NO | — | JSON (kode booking, label status, pesan). **Dilarang** memuat nomor BPJS/polis, keluhan, atau data pribadi lain. |
| `read_at` | `TIMESTAMP` | YES | `NULL` | Waktu dibaca. |
| `created_at` | `TIMESTAMP` | YES | `NULL` | |
| `updated_at` | `TIMESTAMP` | YES | `NULL` | |

- **Primary key:** `id`. **Foreign key:** tidak ada (polymorphic). **Relasi Eloquent (`Illuminate\Notifications\DatabaseNotification`):** `notifiable()` morphTo; dari sisi pemilik `User::notifications()` morphMany.
- **Index & unique:** `notifications_notifiable_type_notifiable_id_index (notifiable_type, notifiable_id)`.
- **CHECK constraint:** tidak ada. **Cast Eloquent:** `data` → `array`; `read_at` → `datetime`.
- **Aturan hapus & arsip:** tidak ada UI hapus; ditandai dibaca lewat `patient.notifications.read`. **[KONFIRMASI]** retensi produksi (bagian 13).

---

## 3. Domain: Dokter & jadwal

### 3.1 `specialties`

**Tujuan.** Master spesialisasi medis (13 contoh: Urologi, Penyakit dalam, Anak, Obstetri dan ginekologi, Bedah umum, Jantung, Saraf, Mata, THT, Kulit dan kelamin, Ortopedi, Dokter umum, Dokter gigi). Dokter dapat memiliki lebih dari satu (lewat `doctor_specialties`).

| Kolom | Tipe MariaDB | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | NO | AUTO_INCREMENT | PK. |
| `name` | `VARCHAR(100)` | NO | — | Nama spesialisasi. Keunikan nama divalidasi di Form Request (UNIQUE database hanya pada `slug`, sesuai 00). |
| `slug` | `VARCHAR(120)` | NO | — | UNIQUE. |
| `description` | `TEXT` | YES | `NULL` | Teks biasa. |
| `is_active` | `TINYINT(1)` | NO | `1` | Spesialisasi nonaktif tidak muncul sebagai filter publik. |
| `sort_order` | `SMALLINT UNSIGNED` | NO | `0` | |
| `created_at` | `TIMESTAMP` | YES | `NULL` | |
| `updated_at` | `TIMESTAMP` | YES | `NULL` | |

- **Primary key:** `id`. **Foreign key:** tidak ada (induk dari `clinics` dan `doctor_specialties`).
- **Relasi Eloquent:** `Specialty::doctors()` belongsToMany `Doctor` (pivot `doctor_specialties`, `withPivot('is_primary')`); `Specialty::clinics()` hasMany `Clinic`.
- **Index & unique:** `PRIMARY (id)`; `specialties_slug_unique (slug)`; `specialties_is_active_sort_order_index (is_active, sort_order)`.
- **CHECK constraint:** tidak ada. **Cast Eloquent:** `is_active` → `boolean`. Tanpa route publik pada fase 2; model tanpa `getRouteKeyName()`, route admin memakai id (F2-R4).
- **Aturan hapus & arsip:** boleh dihapus hanya bila tidak dipakai dokter maupun poliklinik (`SpecialtyPolicy::delete` menolak; FK `doctor_specialties.specialty_id` `RESTRICT` sebagai jaring pengaman, `clinics.specialty_id` `SET NULL` hanya bila operator menghapus langsung di DB). Selain itu nonaktifkan (`is_active = false`).

### 3.2 `clinics`

**Tujuan.** Poliklinik sekaligus daftar layanan (`type = poliklinik`) dan layanan penunjang (`type = penunjang`, mis. laboratorium). Hanya yang `is_bookable = true` dapat dipilih pada wizard pendaftaran. `code` menjadi awalan nomor antrean (`ANK-007`).

| Kolom | Tipe MariaDB | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | NO | AUTO_INCREMENT | PK. |
| `name` | `VARCHAR(120)` | NO | — | Nama poliklinik/layanan. |
| `slug` | `VARCHAR(140)` | NO | — | UNIQUE; route key `layanan/{clinic:slug}`. |
| `code` | `VARCHAR(5)` | NO | — | UNIQUE. Awalan nomor antrean, huruf kapital A–Z 2–5 karakter (mis. `ANK`; 12 V-22). **[ASUMSI]** Tidak boleh diubah setelah ada appointment pada poliklinik ini, karena `QueueNumberFormatter` menurunkan nomor tampil dari `code` saat ditampilkan (bukan disimpan). |
| `type` | `VARCHAR(20)` | NO | — | `ClinicType`: `poliklinik` / `penunjang`. |
| `is_bookable` | `TINYINT(1)` | NO | `1` | Dapat dipesan online/loket. **[ASUMSI]** default 1; layanan `penunjang` di-seed `0`. |
| `specialty_id` | `BIGINT UNSIGNED` | YES | `NULL` | FK → `specialties.id`. NULL untuk layanan tanpa spesialisasi. |
| `short_description` | `VARCHAR(300)` | NO | — | Ringkasan untuk kartu. |
| `description` | `TEXT` | YES | `NULL` | **Rich text** (disanitasi; `{!! !!}`). |
| `location` | `VARCHAR(150)` | NO | — | Mis. "Gedung A Lt. 1". |
| `is_active` | `TINYINT(1)` | NO | `1` | |
| `sort_order` | `SMALLINT UNSIGNED` | NO | `0` | |
| `meta_title` | `VARCHAR(255)` | YES | `NULL` | Fallback: nama. Form Request menyarankan ≤ 70 karakter. |
| `meta_description` | `VARCHAR(255)` | YES | `NULL` | Fallback: `short_description`. Form Request menyarankan ≤ 160 karakter. |
| `created_at` | `TIMESTAMP` | YES | `NULL` | |
| `updated_at` | `TIMESTAMP` | YES | `NULL` | |

- **Primary key:** `id`.
- **Foreign key:** `specialty_id` → `specialties.id` `ON DELETE SET NULL`.
- **Relasi Eloquent:** `Clinic::specialty()` belongsTo `Specialty`; `Clinic::doctors()` hasMany `Doctor` (poliklinik utama); `Clinic::schedules()` hasMany `DoctorSchedule`; `Clinic::sessions()` hasMany `PracticeSession`; `Clinic::appointments()` hasMany `Appointment`; cover dan galeri lewat trait `HasMedia` (`firstMedia('cover')`, `mediaIn('gallery')`, urut `sort_order`, `id`); tidak ada relasi `cover()`/`gallery()` khusus (F2-R24).
- **Index & unique:** `PRIMARY (id)`; `clinics_slug_unique (slug)`; `clinics_code_unique (code)`; `clinics_specialty_id_foreign (specialty_id)`; `clinics_is_active_type_sort_order_index (is_active, type, sort_order)`.
- **CHECK constraint:** tidak ada.
- **Cast Eloquent:** `type` → `ClinicType`; `is_bookable` → `boolean`; `is_active` → `boolean`. Route publik memakai binding slug eksplisit `{clinic:slug}`; model tanpa `getRouteKeyName()` (F2-R4).
- **Aturan hapus & arsip:** poliklinik yang pernah dirujuk dokter/templat/sesi/appointment tidak dapat dihapus (FK `RESTRICT` dari `doctors`, `doctor_schedules`, `practice_sessions`, `appointments`); nonaktifkan lewat `is_active = false` (hilang dari publik dan wizard; booking lama tetap). Poliklinik tanpa referensi apa pun boleh dihapus (media ikut dihapus).

### 3.3 `doctors`

**Tujuan.** Profil dokter publik. Nama tampilan: `"{title_prefix} {name}, {title_suffix}"` (accessor `display_name`; bagian yang NULL dilewati). Foto di `media` (koleksi `photo`, tunggal).

| Kolom | Tipe MariaDB | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | NO | AUTO_INCREMENT | PK. |
| `clinic_id` | `BIGINT UNSIGNED` | NO | — | FK → `clinics.id`. Poliklinik utama (dokter dapat berpraktik di poliklinik lain lewat `doctor_schedules.clinic_id`). |
| `name` | `VARCHAR(150)` | NO | — | Nama **tanpa gelar**. |
| `slug` | `VARCHAR(170)` | NO | — | UNIQUE; route key `dokter/{doctor:slug}`. |
| `title_prefix` | `VARCHAR(20)` | YES | `NULL` | Mis. `dr.`, `drg.`. |
| `title_suffix` | `VARCHAR(50)` | YES | `NULL` | Mis. `Sp.U`. |
| `gender` | `CHAR(1)` | NO | — | `Gender`: `L` / `P`. |
| `short_bio` | `VARCHAR(500)` | YES | `NULL` | Ringkasan untuk kartu dokter. **[ASUMSI]** nullable. |
| `bio` | `TEXT` | YES | `NULL` | Teks biasa (bukan HTML), dirender `{{ }}`. |
| `status` | `VARCHAR(10)` | NO | `'active'` | `DoctorStatus`: `active` / `inactive` / `archived`. |
| `archived_at` | `DATETIME` | YES | `NULL` | Terisi saat `status` menjadi `archived`; dikosongkan saat dipulihkan **[ASUMSI]**. |
| `sort_order` | `SMALLINT UNSIGNED` | NO | `0` | |
| `meta_title` | `VARCHAR(255)` | YES | `NULL` | Fallback: nama tampilan. |
| `meta_description` | `VARCHAR(255)` | YES | `NULL` | Fallback: `short_bio`. |
| `created_at` | `TIMESTAMP` | YES | `NULL` | |
| `updated_at` | `TIMESTAMP` | YES | `NULL` | |

- **Primary key:** `id`.
- **Foreign key:** `clinic_id` → `clinics.id` `ON DELETE RESTRICT`.
- **Relasi Eloquent:** `Doctor::clinic()` belongsTo `Clinic`; `Doctor::specialties()` belongsToMany `Specialty` (pivot `doctor_specialties`, `withPivot('is_primary')`); `Doctor::primarySpecialty` = accessor (bukan relasi) dari relasi `specialties` yang sudah dimuat: baris pivot `is_primary`, cadangan spesialisasi pertama (11 §5.2, F2-R24); `Doctor::schedules()` hasMany `DoctorSchedule`; `Doctor::exceptions()` hasMany `ScheduleException`; `Doctor::sessions()` hasMany `PracticeSession`; `Doctor::appointments()` hasMany `Appointment`; foto lewat trait `HasMedia` (`firstMedia('photo')`, accessor `photoUrl`/`photoThumbUrl`); tidak ada relasi `photo()` khusus (F2-R24).
- **Index & unique:** `PRIMARY (id)`; `doctors_slug_unique (slug)`; `doctors_clinic_id_foreign (clinic_id)`; `doctors_status_sort_order_index (status, sort_order)`. Pencarian nama memakai `LIKE` (volume kecil; tanpa FULLTEXT).
- **CHECK constraint:** tidak ada (konsistensi `status = archived` ↔ `archived_at` terisi dijaga service, Invarian I-23).
- **Cast Eloquent:** `gender` → `Gender`; `status` → `DoctorStatus`; `archived_at` → `datetime`. Route publik memakai binding slug eksplisit `{doctor:slug}` (fase 3); model tanpa `getRouteKeyName()` (F2-R4).
- **Aturan hapus & arsip:** **tidak ada route hapus** (resource `admin.doctors.*` hanya `index/create/store/show/edit/update`). `inactive` dan `archived` menutup booking baru (F.3 mensyaratkan dokter `active`) dan menyembunyikan dokter dari halaman publik (detail menjawab 404). Menurut 00 F.4 (D-01, D-02): saat dokter **dinonaktifkan**, `ScheduleChangeService::applyDoctorInactive()` membatalkan sesi mendatang tanpa appointment aktif (`cancelled`) dan menjadikan sesi dengan appointment aktif `needs_review` dengan `pending_change` sumber `doctor_inactive` (appointment lama tidak dibatalkan otomatis; petugas menyelesaikannya di `admin.sessions.review`); saat **diarsipkan**, aksi ditolak selama masih ada appointment aktif mendatang ("selesaikan sesi terdampak dahulu"), dokter arsip tidak dapat diberi jadwal, dan `archived_at` terisi. FK `RESTRICT` dari `doctor_schedules`, `schedule_exceptions`, `practice_sessions`, `appointments` mencegah penghapusan fisik.

### 3.4 `doctor_specialties`

**Tujuan.** Pivot many-to-many dokter ↔ spesialisasi. Setiap dokter memiliki **tepat satu** baris `is_primary = 1` dan minimal satu spesialisasi **[ASUMSI]**.

| Kolom | Tipe MariaDB | Null | Default | Keterangan |
|---|---|---|---|---|
| `doctor_id` | `BIGINT UNSIGNED` | NO | — | FK → `doctors.id`. |
| `specialty_id` | `BIGINT UNSIGNED` | NO | — | FK → `specialties.id`. |
| `is_primary` | `TINYINT(1)` | NO | `0` | Spesialisasi utama (untuk kartu dokter dan filter). |

- **Primary key:** komposit (`doctor_id`, `specialty_id`). Tabel pivot **tanpa** `created_at`/`updated_at` **[ASUMSI]** (00 menyebut kolomnya tanpa timestamp).
- **Foreign key:** `doctor_id` → `doctors.id` `ON DELETE CASCADE`; `specialty_id` → `specialties.id` `ON DELETE RESTRICT` **[ASUMSI]**.
- **Relasi Eloquent:** melalui `Doctor::specialties()` dan `Specialty::doctors()`; model pivot opsional `DoctorSpecialty extends Pivot` (`$incrementing = false`, `$timestamps = false`, cast `is_primary` → `boolean`).
- **Index & unique:** `PRIMARY (doctor_id, specialty_id)`; `doctor_specialties_specialty_id_foreign (specialty_id)`.
- **CHECK constraint:** tidak ada (satu `is_primary` per dokter dijaga service; pembahasan alasan tidak memakai generated column ada di Invarian I-5).
- **Cast Eloquent:** `is_primary` → `boolean` (lewat model pivot).
- **Aturan hapus & arsip:** `DoctorService::syncSpecialties()` bekerja dalam transaksi: set semua `is_primary = 0` terlebih dahulu, lalu satu baris menjadi `1`; validasi Form Request: minimal satu spesialisasi dan tepat satu utama.

### 3.5 `doctor_schedules`

**Tujuan.** Templat jadwal praktik **mingguan** (lapis 1 mesin jadwal, F.1). Dari templat inilah `PracticeSessionGenerator` membuat `practice_sessions` per tanggal.

| Kolom | Tipe MariaDB | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | NO | AUTO_INCREMENT | PK. |
| `doctor_id` | `BIGINT UNSIGNED` | NO | — | FK → `doctors.id`. |
| `clinic_id` | `BIGINT UNSIGNED` | NO | — | FK → `clinics.id`. Tempat praktik pada templat. |
| `day_of_week` | `TINYINT UNSIGNED` | NO | — | 1 = Senin … 7 = Minggu (ISO-8601). |
| `start_time` | `TIME` | NO | — | Jam mulai. |
| `end_time` | `TIME` | NO | — | Jam selesai; harus > `start_time`. |
| `quota` | `SMALLINT UNSIGNED` | NO | — | Kuota per sesi, 1–200. |
| `valid_from` | `DATE` | NO | — | Awal berlaku. |
| `valid_until` | `DATE` | YES | `NULL` | Akhir berlaku; NULL = tanpa batas. |
| `is_active` | `TINYINT(1)` | NO | `1` | |
| `notes` | `VARCHAR(255)` | YES | `NULL` | Catatan internal/publik singkat. |
| `created_by` | `BIGINT UNSIGNED` | YES | `NULL` | FK → `users.id`. **[ASUMSI]** nullable (data seeder/sistem). |
| `updated_by` | `BIGINT UNSIGNED` | YES | `NULL` | FK → `users.id`. |
| `created_at` | `TIMESTAMP` | YES | `NULL` | |
| `updated_at` | `TIMESTAMP` | YES | `NULL` | |

- **Primary key:** `id`.
- **Foreign key:** `doctor_id` → `doctors.id` `RESTRICT`; `clinic_id` → `clinics.id` `RESTRICT`; `created_by` → `users.id` `SET NULL`; `updated_by` → `users.id` `SET NULL`.
- **Relasi Eloquent:** `DoctorSchedule::doctor()` belongsTo `Doctor`; `DoctorSchedule::clinic()` belongsTo `Clinic`; `DoctorSchedule::sessions()` hasMany `PracticeSession` (`doctor_schedule_id`); `DoctorSchedule::creator()` / `updater()` belongsTo `User`.
- **Index & unique:** `PRIMARY (id)`; `doctor_schedules_doctor_id_day_of_week_is_active_index (doctor_id, day_of_week, is_active)`; `doctor_schedules_is_active_valid_from_valid_until_index (is_active, valid_from, valid_until)` (dipakai generator); `doctor_schedules_clinic_id_foreign (clinic_id)`; FK `created_by`, `updated_by`. **Tidak ada** UNIQUE untuk mencegah irisan jam: MariaDB 10.4 tidak punya *exclusion constraint*; larangan irisan (templat aktif seorang dokter pada hari yang sama dengan rentang berlaku yang beririsan) dijaga validasi aplikasi di dalam transaksi yang mengunci baris `doctors`.
- **CHECK constraint:** `chk_schedules_time (end_time > start_time)`; `chk_schedules_day (day_of_week BETWEEN 1 AND 7)`; `chk_schedules_quota (quota BETWEEN 1 AND 200)`; `chk_schedules_validity (valid_until IS NULL OR valid_until >= valid_from)`.
- **Cast Eloquent:** `day_of_week` → `integer`; `quota` → `integer`; `valid_from` → `date`; `valid_until` → `date`; `is_active` → `boolean`. (`start_time`/`end_time` dibaca sebagai string `HH:MM:SS`.)
- **Aturan hapus & arsip (00 C):** hapus diizinkan **hanya bila tidak ada satu pun sesi miliknya yang ber-appointment** (termasuk riwayat batal); sesi-sesi tanpa appointment milik templat itu (lampau maupun mendatang; pengecualian sadar atas aturan "sesi lampau tidak diubah", yang hanya berlaku untuk operasi per sesi, JDW-19a) dihapus lebih dulu oleh service dalam transaksi yang sama, sebab menghapus templat saja akan memutus `doctor_schedule_id` (menjadi `NULL`) sehingga sesi lama tampak sebagai sesi manual yatim yang tidak ikut propagasi. Bila ada sesi ber-appointment, tombol hapus diganti "Nonaktifkan" (`is_active = false`) dengan pesan alasan, dan nonaktif memicu `ScheduleChangeService` (F.4). Mengganti `clinic_id` ditolak bila ada sesi mendatang dengan appointment aktif (F.4). Templat dokter berstatus `archived` tidak dapat dibuat.

### 3.6 `schedule_exceptions`

**Tujuan.** Pemblokiran rentang tanggal (lapis 2): cuti dokter, libur rumah sakit, libur nasional, lainnya. Granularitas **satu hari penuh** **[ASUMSI]**; perubahan sebagian hari dilakukan dengan mengedit `practice_sessions`.

| Kolom | Tipe MariaDB | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | NO | AUTO_INCREMENT | PK. |
| `doctor_id` | `BIGINT UNSIGNED` | YES | `NULL` | FK → `doctors.id`. **NULL = berlaku untuk seluruh dokter/RS.** Kombinasi wajib (00 E.2): `doctor_leave` → `doctor_id` **wajib**; `hospital_holiday` dan `national_holiday` → `doctor_id` **harus NULL**; `other` → boleh keduanya. Ditegakkan Form Request (`ScheduleExceptionRequest`, 12 V-25) dan CHECK `chk_schedule_exceptions_scope`. |
| `type` | `VARCHAR(20)` | NO | — | `ExceptionType`: `doctor_leave`, `hospital_holiday`, `national_holiday`, `other`. |
| `start_date` | `DATE` | NO | — | Tidak boleh di masa lalu saat dibuat. |
| `end_date` | `DATE` | NO | — | ≥ `start_date`. |
| `reason` | `VARCHAR(255)` | NO | — | Alasan (tampil sebagai "Cuti"/"Libur" di jadwal publik). |
| `created_by` | `BIGINT UNSIGNED` | YES | `NULL` | FK → `users.id`. **[ASUMSI]** nullable. |
| `created_at` | `TIMESTAMP` | YES | `NULL` | |
| `updated_at` | `TIMESTAMP` | YES | `NULL` | |

- **Primary key:** `id`.
- **Foreign key:** `doctor_id` → `doctors.id` `RESTRICT`; `created_by` → `users.id` `SET NULL`.
- **Relasi Eloquent:** `ScheduleException::doctor()` belongsTo `Doctor` (nullable); `ScheduleException::sessions()` hasMany `PracticeSession` (`schedule_exception_id`); `ScheduleException::creator()` belongsTo `User`.
- **Index & unique:** `PRIMARY (id)`; `schedule_exceptions_start_date_end_date_index (start_date, end_date)`; `schedule_exceptions_doctor_id_start_date_index (doctor_id, start_date)` (juga melayani FK `doctor_id`); FK `created_by`.
- **CHECK constraint:** `chk_exceptions_dates (end_date >= start_date)`; `chk_schedule_exceptions_scope ((type <> 'doctor_leave' OR doctor_id IS NOT NULL) AND (type NOT IN ('hospital_holiday','national_holiday') OR doctor_id IS NULL))` — kombinasi jenis vs dokter menurut 00 E.2; `other` tidak dibatasi, dan nilai `ExceptionType` lain yang kelak ditambahkan lolos tanpa `ALTER TABLE` (konvensi 1.2 butir 5). Ditambahkan sebagai **[ASUMSI]** konsisten dengan 00 E.2 (00 hanya menyebut aturannya, bukan constraint-nya).
- **Cast Eloquent:** `type` → `ExceptionType`; `start_date` → `date`; `end_date` → `date`.
- **Aturan hapus & arsip (00 C):** "hapus" = **cabut pengecualian** lewat `ScheduleChangeService::removeException` (tidak ada penghapusan langsung dari tabel): dalam transaksi yang sama sesi yang dibatalkan olehnya dan belum lewat dibuka kembali (`open`), dan sesi `needs_review` bersumber pengecualian itu kembali `open` dengan `pending_change` dan `schedule_exception_id` dikosongkan, sebelum baris dihapus; FK `practice_sessions.schedule_exception_id` `SET NULL` hanya jaring pengaman. Pengecualian yang `end_date`-nya sudah lewat tidak diedit; `start_date` tidak boleh di masa lalu saat dibuat.

### 3.7 `practice_sessions`

**Tujuan.** Sesi praktik **konkret per tanggal** (lapis 3): hasil generate dari templat atau sesi tambahan manual. Inilah baris yang **dikunci (`lockForUpdate`) saat booking**, memegang kuota (`quota`, `booked_count`) dan pencacah nomor antrean (`last_queue_number`).

| Kolom | Tipe MariaDB | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | NO | AUTO_INCREMENT | PK. |
| `doctor_id` | `BIGINT UNSIGNED` | NO | — | FK → `doctors.id`. Tidak berubah setelah dibuat **[ASUMSI]**. |
| `clinic_id` | `BIGINT UNSIGNED` | NO | — | FK → `clinics.id`. Tidak berubah setelah dibuat. |
| `doctor_schedule_id` | `BIGINT UNSIGNED` | YES | `NULL` | FK → `doctor_schedules.id`. NULL = sesi tambahan manual. |
| `session_date` | `DATE` | NO | — | Tanggal praktik. Tidak berubah setelah dibuat. |
| `start_time` | `TIME` | NO | — | Jam mulai (dapat digeser lewat edit sesi/`apply`). |
| `end_time` | `TIME` | NO | — | Jam selesai; > `start_time`. |
| `quota` | `SMALLINT UNSIGNED` | NO | — | Kuota; tidak boleh turun di bawah `booked_count`. |
| `booked_count` | `SMALLINT UNSIGNED` | NO | `0` | Jumlah appointment berstatus `QUOTA_CONSUMING` (semua kecuali `cancelled`). |
| `last_queue_number` | `SMALLINT UNSIGNED` | NO | `0` | Pencacah nomor antrean; hanya naik (`++`), tidak pernah dipakai ulang. |
| `status` | `VARCHAR(20)` | NO | `'open'` | `SessionStatus`: `open`, `closed`, `needs_review`, `cancelled`. |
| `status_reason` | `VARCHAR(255)` | YES | `NULL` | Alasan penutupan/pembatalan (mis. "Cuti dokter"). |
| `schedule_exception_id` | `BIGINT UNSIGNED` | YES | `NULL` | FK → `schedule_exceptions.id`. Pengecualian penyebab `cancelled`/`needs_review`. |
| `pending_change` | `LONGTEXT` (JSON) | YES | `NULL` | Perubahan menunggu tinjauan (F.4); `$table->json()` → CHECK `json_valid` otomatis. Terisi **hanya** saat `status = needs_review`; **satu sesi hanya menyimpan satu** `pending_change` (enam sumber di bawah, aturan prioritas di bawah). |
| `is_manually_modified` | `TINYINT(1)` | NO | `0` | `1` = edit manual per tanggal; tidak ditimpa regenerasi. |
| `reviewed_by` | `BIGINT UNSIGNED` | YES | `NULL` | FK → `users.id`. Petugas yang menyelesaikan tinjauan. |
| `reviewed_at` | `DATETIME` | YES | `NULL` | |
| `created_at` | `TIMESTAMP` | YES | `NULL` | |
| `updated_at` | `TIMESTAMP` | YES | `NULL` | |

Format `pending_change` (00 F.4), enam bentuk menurut `source`:

```json
{"source":"exception","exception_id":12}
{"source":"template_time","schedule_id":3,"new_start_time":"09:00","new_end_time":"12:00"}
{"source":"template_removed","schedule_id":3}
{"source":"manual_time","new_start_time":"13:00","new_end_time":"15:00"}
{"source":"manual_cancel","reason":"…"}
{"source":"doctor_inactive","doctor_id":7}
```

**Prioritas bila sumber baru datang saat sesi masih `needs_review` (00 F.4):** sumber jenis-batal (`exception`, `template_removed`, `manual_cancel`, `doctor_inactive`) mengalahkan sumber jenis-jam (`template_time`, `manual_time`); pada tingkat yang sama, sumber terbaru menggantikan yang lama; sumber jenis-jam tidak pernah menimpa sumber jenis-batal. Setiap penggantian dicatat di audit log (12 §8, `session.pending_replaced`). Bila pengecualian yang menjadi sumber dicabut/dipersempit sementara sesi belum diselesaikan, sesi kembali `open` dan `pending_change` serta `schedule_exception_id` dikosongkan (bila sesi belum lewat).

- **Primary key:** `id`.
- **Foreign key:** `doctor_id` → `doctors.id` `RESTRICT`; `clinic_id` → `clinics.id` `RESTRICT`; `doctor_schedule_id` → `doctor_schedules.id` `SET NULL`; `schedule_exception_id` → `schedule_exceptions.id` `SET NULL`; `reviewed_by` → `users.id` `SET NULL`.
- **Relasi Eloquent:** `PracticeSession::doctor()` belongsTo `Doctor`; `PracticeSession::clinic()` belongsTo `Clinic`; `PracticeSession::schedule()` belongsTo `DoctorSchedule` (`doctor_schedule_id`); `PracticeSession::exception()` belongsTo `ScheduleException` (`schedule_exception_id`); `PracticeSession::appointments()` hasMany `Appointment`; `PracticeSession::reviewer()` belongsTo `User` (`reviewed_by`). Accessor `remaining` = `quota - booked_count`.
- **Index & unique:** `PRIMARY (id)`; **UNIQUE** `practice_sessions_doctor_schedule_id_session_date_unique (doctor_schedule_id, session_date)` (NULL boleh berulang, sehingga sesi tambahan manual tidak terbatas; sekaligus membuat `insertOrIgnore` generator idempoten); `practice_sessions_session_date_status_index (session_date, status)`; `practice_sessions_doctor_id_session_date_index (doctor_id, session_date)`; `practice_sessions_clinic_id_session_date_index (clinic_id, session_date)`; FK `schedule_exception_id`, `reviewed_by`.
- **CHECK constraint:** `chk_sessions_quota (booked_count >= 0 AND booked_count <= quota)`; `chk_sessions_quota_range (quota BETWEEN 1 AND 200)` **[ASUMSI]** (mengikuti batas templat); `chk_sessions_time (end_time > start_time)`; `chk_sessions_queue (last_queue_number >= booked_count)` (selalu benar karena setiap appointment non-batal punya nomor unik ≤ `last_queue_number`); ditambah CHECK otomatis `pending_change` = `json_valid(pending_change)` dari tipe `JSON` (bukan `chk_*`).
- **Cast Eloquent:** `session_date` → `date`; `quota`, `booked_count`, `last_queue_number` → `integer`; `status` → `SessionStatus`; `pending_change` → `array`; `is_manually_modified` → `boolean`; `reviewed_at` → `datetime`.
- **Aturan hapus & arsip (00 C):** aksi hapus oleh admin (`admin.sessions.destroy`, 00 I.4) **hanya untuk sesi tambahan manual** (`doctor_schedule_id` NULL) **tanpa appointment sama sekali**; selain itu tombol hapus diganti "Tutup/Batalkan" (`admin.sessions.status`) dengan pesan alasan. Penghapusan internal oleh `ScheduleChangeService` (propagasi templat F.4 menghapus sesi templat tanpa appointment lalu menggenerate ulang; penghapusan templat) bukan aksi `destroy` UI dan hanya menyentuh sesi tanpa appointment. Sesi yang pernah memiliki appointment tidak dapat dihapus (FK `RESTRICT` dari `appointments`) dan tidak pernah dibersihkan, termasuk sesi lampau, karena menjadi dasar riwayat dan laporan **[ASUMSI]**. Sesi ditutup/dibatalkan lewat `status`, bukan dihapus. Sesi tidak boleh tumpang tindih jam dengan sesi non-batal lain milik dokter yang sama pada tanggal yang sama (validasi aplikasi, **[ASUMSI]**).

---

## 4. Domain: Booking

### 4.1 `appointments`

**Tujuan.** Satu baris = satu pendaftaran kunjungan rawat jalan seorang pasien (profil) pada satu sesi praktik. Memegang kode booking, nomor antrean, status, kanal, jenis pembayaran, dan jejak waktu tiap transisi. Dibuat **hanya** oleh `BookingService::book()` dan diubah statusnya **hanya** oleh `AppointmentStatusService::transition()` (keduanya dalam transaksi dengan penguncian `patient_profiles` → `practice_sessions` → `appointments`, F.6).

| Kolom | Tipe MariaDB | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | NO | AUTO_INCREMENT | PK. |
| `booking_code` | `VARCHAR(13)` | NO | — | UNIQUE. Format `UPH-XXXX-XXXX` (Crockford Base32, `random_int`); tidak memuat data pasien. Route key portal pasien (`{appointment:booking_code}`). |
| `patient_profile_id` | `BIGINT UNSIGNED` | NO | — | FK → `patient_profiles.id`. |
| `practice_session_id` | `BIGINT UNSIGNED` | NO | — | FK → `practice_sessions.id`. |
| `doctor_id` | `BIGINT UNSIGNED` | NO | — | FK → `doctors.id`. **Denormalisasi** dari sesi (filter/laporan). |
| `clinic_id` | `BIGINT UNSIGNED` | NO | — | FK → `clinics.id`. **Denormalisasi** dari sesi. |
| `session_date` | `DATE` | NO | — | **Denormalisasi** tanggal sesi. |
| `queue_number` | `SMALLINT UNSIGNED` | NO | — | `++last_queue_number` sesi; unik per sesi; tidak dipakai ulang. Tampil `{clinics.code}-{3 digit}` (`ANK-007`). |
| `status` | `VARCHAR(20)` | NO | — | `AppointmentStatus`. Tanpa default: wajib ditentukan service (`pending`, atau `confirmed` untuk loket/`auto_confirm`). |
| `channel` | `VARCHAR(10)` | NO | — | `BookingChannel`: `online` / `loket`. |
| `payment_type` | `VARCHAR(10)` | NO | — | `PaymentType`: `umum` / `bpjs` / `asuransi`. |
| `insurance_partner_id` | `BIGINT UNSIGNED` | YES | `NULL` | FK → `insurance_partners.id`. Wajib bila `asuransi`; NULL bila `umum`/`bpjs`. |
| `payment_reference` | `TEXT` | YES | `NULL` | **Terenkripsi** (`encrypted`): nomor BPJS (13 digit angka, wajib bila `bpjs`) atau nomor polis (teks bebas maks 30 karakter, opsional bila `asuransi`; 01 A-12). Ciphertext jauh lebih panjang dari nilainya → `TEXT`. Tidak pernah ditampilkan penuh di UI (01 A-13). |
| `payment_reference_last4` | `CHAR(4)` | YES | `NULL` | 4 digit terakhir untuk tampilan tersamar (bukti pendaftaran, layar staf). |
| `complaint` | `VARCHAR(500)` | YES | `NULL` | Keluhan singkat opsional. Dianggap data kesehatan (bagian 13). |
| `created_by_user_id` | `BIGINT UNSIGNED` | YES | `NULL` | FK → `users.id`. Petugas pembuat (kanal `loket`); NULL untuk online. |
| `confirmed_at` | `DATETIME` | YES | `NULL` | Waktu masuk `confirmed`. |
| `checked_in_at` | `DATETIME` | YES | `NULL` | Waktu `checked_in`; dikosongkan bila dikoreksi kembali ke `confirmed`. |
| `completed_at` | `DATETIME` | YES | `NULL` | Waktu `completed`. |
| `cancelled_at` | `DATETIME` | YES | `NULL` | Waktu `cancelled`. |
| `no_show_at` | `DATETIME` | YES | `NULL` | Waktu `no_show`. |
| `cancelled_by_type` | `VARCHAR(10)` | YES | `NULL` | `ActorType`: `patient` / `staff` / `system`. Terisi hanya bila `cancelled`. |
| `cancelled_by_user_id` | `BIGINT UNSIGNED` | YES | `NULL` | FK → `users.id`. NULL untuk pelaku `system`. |
| `cancel_reason` | `VARCHAR(255)` | YES | `NULL` | Wajib untuk pembatalan oleh petugas/sistem. |
| `schedule_changed_at` | `DATETIME` | YES | `NULL` | Penanda jadwal sesi berubah (resolusi `apply`) sehingga pasien perlu diberi tahu. |
| `active_flag` | `TINYINT` | YES | **generated STORED** | `CASE WHEN status IN ('pending','confirmed','checked_in') THEN 1 ELSE NULL END`. Tidak boleh ditulis aplikasi. Dasar UNIQUE pengganti partial index. |
| `created_at` | `TIMESTAMP` | YES | `NULL` | |
| `updated_at` | `TIMESTAMP` | YES | `NULL` | |

- **Primary key:** `id`.
- **Foreign key:** `patient_profile_id` → `patient_profiles.id` `RESTRICT`; `practice_session_id` → `practice_sessions.id` `RESTRICT`; `doctor_id` → `doctors.id` `RESTRICT`; `clinic_id` → `clinics.id` `RESTRICT`; `insurance_partner_id` → `insurance_partners.id` `RESTRICT`; `created_by_user_id` → `users.id` `SET NULL`; `cancelled_by_user_id` → `users.id` `SET NULL`.
- **Relasi Eloquent:** `Appointment::patientProfile()` belongsTo `PatientProfile`; `Appointment::practiceSession()` belongsTo `PracticeSession`; `Appointment::doctor()` belongsTo `Doctor`; `Appointment::clinic()` belongsTo `Clinic`; `Appointment::insurancePartner()` belongsTo `InsurancePartner`; `Appointment::statusHistories()` hasMany `AppointmentStatusHistory` (urut `created_at`, `id`); `Appointment::creator()` belongsTo `User` (`created_by_user_id`); `Appointment::canceller()` belongsTo `User` (`cancelled_by_user_id`).
- **Index & unique:**
  - **UNIQUE** `appointments_booking_code_unique (booking_code)`.
  - **UNIQUE** `appointments_practice_session_id_queue_number_unique (practice_session_id, queue_number)` — nomor antrean unik per sesi (juga melayani FK `practice_session_id`).
  - **UNIQUE** `appointments_patient_session_active_unique (patient_profile_id, practice_session_id, active_flag)` — **nama eksplisit** (nama default 70 karakter melewati batas 64). Pengganti partial unique index: `active_flag = 1` hanya untuk status aktif, selain itu `NULL` (nilai NULL boleh berulang) sehingga appointment yang dibatalkan/selesai tidak menghalangi pendaftaran ulang, tetapi dua appointment aktif milik pasien yang sama pada sesi yang sama mustahil.
  - INDEX `appointments_session_date_status_index (session_date, status)`; `appointments_patient_profile_id_session_date_index (patient_profile_id, session_date)`; `appointments_doctor_id_session_date_index (doctor_id, session_date)`; `appointments_status_index (status)`; `appointments_created_at_index (created_at)`.
  - Index FK otomatis: `appointments_clinic_id_foreign`, `appointments_insurance_partner_id_foreign`, `appointments_created_by_user_id_foreign`, `appointments_cancelled_by_user_id_foreign`.
- **CHECK constraint:** `chk_appointments_queue (queue_number >= 1)`.
- **Cast Eloquent:** `session_date` → `date`; `queue_number` → `integer`; `status` → `AppointmentStatus`; `channel` → `BookingChannel`; `payment_type` → `PaymentType`; `payment_reference` → `encrypted`; `confirmed_at`, `checked_in_at`, `completed_at`, `cancelled_at`, `no_show_at`, `schedule_changed_at` → `datetime`; `cancelled_by_type` → `ActorType`; `active_flag` → `integer` (hanya baca). `$hidden = ['payment_reference']`. `active_flag`, `booking_code`, `queue_number`, `status` **tidak** ada di `$fillable` (diisi service lewat `forceFill`). Accessor `queue_label` (`QueueNumberFormatter`) dan `payment_reference_masked`. Setelah `create()`, panggil `->refresh()` bila `active_flag` dibutuhkan (kolom generated tidak dikembalikan insert).
- **Aturan hapus & arsip:** **tidak pernah dihapus** (tidak ada route hapus; semua FK induknya `RESTRICT`). Pembatalan hanya mengubah `status` ke `cancelled` (nomor antrean tetap terpakai, kuota dilepas). Pengubahan status langsung lewat `Appointment::update(['status' => …])` dilarang di luar `AppointmentStatusService`.

Kaitan status dengan kolom waktu (ditegakkan service, Invarian I-14):

| Status | Kolom yang harus terisi | Kolom yang harus kosong |
|---|---|---|
| `pending` | — | `confirmed_at`, `checked_in_at`, `completed_at`, `cancelled_at`, `no_show_at` |
| `confirmed` | `confirmed_at` | `checked_in_at`, `completed_at`, `cancelled_at`, `no_show_at` |
| `checked_in` | `confirmed_at`, `checked_in_at` | `completed_at`, `cancelled_at`, `no_show_at` |
| `completed` | `confirmed_at`, `checked_in_at`, `completed_at` | `cancelled_at`, `no_show_at` |
| `cancelled` | `cancelled_at`, `cancelled_by_type` (+ `cancel_reason` bila pelaku staf/sistem) | `completed_at`, `no_show_at` |
| `no_show` | `no_show_at` | `checked_in_at`, `completed_at`, `cancelled_at` |

### 4.2 `appointment_status_histories`

**Tujuan.** Jejak setiap perubahan status appointment (termasuk pembuatan). *Append-only*: tidak ada `updated_at`, tidak ada UI ubah/hapus.

| Kolom | Tipe MariaDB | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | NO | AUTO_INCREMENT | PK. |
| `appointment_id` | `BIGINT UNSIGNED` | NO | — | FK → `appointments.id`. |
| `from_status` | `VARCHAR(20)` | YES | `NULL` | NULL pada baris pembuatan. |
| `to_status` | `VARCHAR(20)` | NO | — | `AppointmentStatus`. |
| `actor_type` | `VARCHAR(10)` | NO | — | `ActorType`: `patient` / `staff` / `system`. |
| `actor_user_id` | `BIGINT UNSIGNED` | YES | `NULL` | FK → `users.id`; NULL untuk `system`. |
| `reason` | `VARCHAR(255)` | YES | `NULL` | Alasan (wajib untuk pembatalan staf/sistem dan koreksi `checked_in → confirmed`). |
| `created_at` | `TIMESTAMP` | NO | `CURRENT_TIMESTAMP` | Waktu perubahan. Tanpa `updated_at`. |

- **Primary key:** `id`.
- **Foreign key:** `appointment_id` → `appointments.id` `ON DELETE CASCADE` (appointment tidak pernah dihapus; cascade hanya jaring pengaman); `actor_user_id` → `users.id` `ON DELETE SET NULL`.
- **Relasi Eloquent:** `AppointmentStatusHistory::appointment()` belongsTo `Appointment`; `AppointmentStatusHistory::actor()` belongsTo `User` (`actor_user_id`).
- **Index & unique:** `PRIMARY (id)`; `appointment_status_histories_appointment_id_created_at_index (appointment_id, created_at)` (juga melayani FK); `appointment_status_histories_actor_user_id_foreign (actor_user_id)`.
- **CHECK constraint:** tidak ada.
- **Cast Eloquent:** `from_status`, `to_status` → `AppointmentStatus`; `actor_type` → `ActorType`; `created_at` → `datetime`. Model: `const UPDATED_AT = null`; `updating`/`deleting` melempar `LogicException` (append-only).
- **Aturan hapus & arsip:** tidak dihapus. Baris pertama setiap appointment memiliki `from_status = NULL`; `to_status` baris terakhir selalu sama dengan `appointments.status` (Invarian I-16).

---

## 5. Domain: Fasilitas & kamar

### 5.1 `room_categories`

**Tujuan.** Kategori kamar rawat inap dengan **model kapasitas agregat** (00 E.4): jumlah tempat tidur total dan tersedia per kategori, diperbarui **manual** oleh staf. Seed: `bpjs-kelas-1`, `bpjs-kelas-2`, `bpjs-kelas-3`, `vip` (nama dapat diubah admin dan tidak boleh dianggap klasifikasi BPJS resmi; penamaan terhadap ketentuan BPJS terbaru/KRIS = keputusan K-05 di 17, cukup diubah lewat admin/seeder karena kategori adalah data, bukan enum). Alasan tidak ada tabel `rooms`/`room_availability` ada di bagian 9.

| Kolom | Tipe MariaDB | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | NO | AUTO_INCREMENT | PK. |
| `name` | `VARCHAR(100)` | NO | — | Nama kategori. |
| `slug` | `VARCHAR(120)` | NO | — | UNIQUE; route key `kamar-rawat-inap/{roomCategory:slug}`. |
| `short_description` | `VARCHAR(300)` | NO | — | |
| `description` | `TEXT` | YES | `NULL` | **Rich text**. |
| `amenities` | `LONGTEXT` (JSON) | YES | `NULL` | Array teks fasilitas kamar (mis. `["AC","TV","Kamar mandi dalam"]`); `$table->json()` → CHECK `json_valid` otomatis. |
| `bed_capacity` | `SMALLINT UNSIGNED` | NO | `0` | Total tempat tidur kategori. |
| `beds_available` | `SMALLINT UNSIGNED` | NO | `0` | Tempat tidur tersedia pada pembaruan terakhir; 0 ≤ nilai ≤ `bed_capacity`. |
| `sample_rate` | `DECIMAL(12,2)` | YES | `NULL` | Tarif **contoh** (Rupiah); NULL = tidak ditampilkan. |
| `rate_unit` | `VARCHAR(30)` | NO | `'per malam'` | |
| `rate_note` | `VARCHAR(255)` | YES | `NULL` | Mis. "Tarif contoh, belum final". |
| `is_active` | `TINYINT(1)` | NO | `1` | |
| `sort_order` | `SMALLINT UNSIGNED` | NO | `0` | |
| `availability_updated_at` | `DATETIME` | YES | `NULL` | Waktu pembaruan ketersediaan terakhir; ditampilkan publik (tidak pernah disebut *real-time*). |
| `availability_updated_by` | `BIGINT UNSIGNED` | YES | `NULL` | FK → `users.id`. |
| `meta_title` | `VARCHAR(255)` | YES | `NULL` | |
| `meta_description` | `VARCHAR(255)` | YES | `NULL` | |
| `created_at` | `TIMESTAMP` | YES | `NULL` | |
| `updated_at` | `TIMESTAMP` | YES | `NULL` | |

- **Primary key:** `id`.
- **Foreign key:** `availability_updated_by` → `users.id` `ON DELETE SET NULL`.
- **Relasi Eloquent:** `RoomCategory::availabilityUpdater()` belongsTo `User`; `RoomCategory::cover()` morphOne `Media`; `RoomCategory::gallery()` morphMany `Media` (galeri foto kamar, kebutuhan §7).
- **Index & unique:** `PRIMARY (id)`; `room_categories_slug_unique (slug)`; `room_categories_is_active_sort_order_index (is_active, sort_order)`; FK `availability_updated_by`.
- **CHECK constraint:** `chk_room_categories_beds (beds_available >= 0 AND beds_available <= bed_capacity)`; ditambah CHECK otomatis `amenities` = `json_valid(amenities)` dari tipe `JSON`.
- **Cast Eloquent:** `amenities` → `array`; `bed_capacity`, `beds_available` → `integer`; `sample_rate` → `decimal:2`; `is_active` → `boolean`; `availability_updated_at` → `datetime`. Route key = `slug`.
- **Aturan hapus & arsip (00 C, "hapus hanya bila tidak dirujuk"):** tidak ada tabel yang merujuk `room_categories` (model kapasitas agregat, tanpa tabel `rooms`), sehingga `RoomCategory::deletionBlockedReason()` selalu `null` dan kategori dapat dihapus (media ikut dihapus lewat `MediaService`); cara yang dianjurkan tetap `is_active = false` (detail kamar tidak diakses publik) dan UI menampilkan peringatan sebelum menghapus. `availability_updated_at`/`availability_updated_by` hanya diubah oleh aksi pembaruan ketersediaan (`admin.room-categories.availability`, izin `rooms.update-availability`) atau perubahan `bed_capacity`, bukan oleh edit deskripsi. Riwayat perubahan ketersediaan **tidak** disimpan di tabel ini, melainkan di `audit_logs` (aksi `room.availability_updated`, `old_values`/`new_values` berisi `bed_capacity` dan `beds_available`).

### 5.2 `facilities`

**Tujuan.** Fasilitas rumah sakit (medis, penunjang, umum) dengan halaman daftar dan detail.

| Kolom | Tipe MariaDB | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | NO | AUTO_INCREMENT | PK. |
| `name` | `VARCHAR(150)` | NO | — | |
| `slug` | `VARCHAR(170)` | NO | — | UNIQUE; route key `fasilitas/{facility:slug}`. |
| `category` | `VARCHAR(20)` | NO | — | `FacilityCategory`: `medis`, `penunjang`, `umum`. |
| `short_description` | `VARCHAR(300)` | NO | — | |
| `description` | `TEXT` | YES | `NULL` | **Rich text**. |
| `is_active` | `TINYINT(1)` | NO | `1` | |
| `sort_order` | `SMALLINT UNSIGNED` | NO | `0` | |
| `meta_title` | `VARCHAR(255)` | YES | `NULL` | |
| `meta_description` | `VARCHAR(255)` | YES | `NULL` | |
| `created_at` | `TIMESTAMP` | YES | `NULL` | |
| `updated_at` | `TIMESTAMP` | YES | `NULL` | |

- **Primary key:** `id`. **Foreign key:** tidak ada.
- **Relasi Eloquent:** `Facility::cover()` morphOne `Media`; `Facility::gallery()` morphMany `Media`.
- **Index & unique:** `PRIMARY (id)`; `facilities_slug_unique (slug)`; `facilities_is_active_category_sort_order_index (is_active, category, sort_order)`.
- **CHECK constraint:** tidak ada. **Cast Eloquent:** `category` → `FacilityCategory`; `is_active` → `boolean`. Route key = `slug`.
- **Aturan hapus & arsip:** tidak dirujuk tabel lain; dapat dihapus (media ikut dihapus) atau dinonaktifkan.

---

## 6. Domain: Konten

### 6.1 `pages`

**Tujuan.** Halaman statis/semi-statis situs yang dikelola admin: judul, subjudul, isi (untuk halaman yang punya `body`), serta meta SEO. Terdapat **15 kunci baku** (00 E.5): `home`, `about`, `vision-mission`, `clinics-index`, `doctors-index`, `schedules-index`, `facilities-index`, `rooms-index`, `insurance-info`, `registration-guide`, `articles-index`, `galleries-index`, `faq`, `contact`, `privacy-policy`. Halaman daftar (`*-index`, `faq`, `contact`, `home`) memakai `title`/`subtitle`/meta saja; `body` dipakai oleh `about`, `vision-mission`, `insurance-info`, `registration-guide`, `privacy-policy`.

| Kolom | Tipe MariaDB | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | NO | AUTO_INCREMENT | PK. |
| `key` | `VARCHAR(50)` | NO | — | UNIQUE; kunci baku di atas. Kata cadangan SQL (gunakan backtick pada SQL mentah). |
| `title` | `VARCHAR(150)` | NO | — | |
| `subtitle` | `VARCHAR(255)` | YES | `NULL` | |
| `body` | `MEDIUMTEXT` | YES | `NULL` | **Rich text** (disanitasi). |
| `meta_title` | `VARCHAR(255)` | YES | `NULL` | |
| `meta_description` | `VARCHAR(255)` | YES | `NULL` | |
| `is_system` | `TINYINT(1)` | NO | `1` | `1` = bawaan sistem, tidak bisa dihapus. |
| `updated_by` | `BIGINT UNSIGNED` | YES | `NULL` | FK → `users.id`. |
| `created_at` | `TIMESTAMP` | YES | `NULL` | |
| `updated_at` | `TIMESTAMP` | YES | `NULL` | |

- **Primary key:** `id`.
- **Foreign key:** `updated_by` → `users.id` `ON DELETE SET NULL`.
- **Relasi Eloquent:** `Page::updater()` belongsTo `User`; gambar sisipan rich text lewat `Page::media()` morphMany (koleksi `editor`).
- **Index & unique:** `PRIMARY (id)`; `pages_key_unique (key)`; FK `updated_by`.
- **CHECK constraint:** tidak ada. **Cast Eloquent:** `is_system` → `boolean`.
- **Aturan hapus & arsip:** tidak ada route `create`/`destroy` (`admin.pages.*` hanya `index`, `edit`, `update`); 15 baris dibuat seeder (juga di produksi, karena halaman publik bergantung padanya).

### 6.2 `article_categories`

**Tujuan.** Kategori artikel kesehatan (filter `kategori`).

| Kolom | Tipe MariaDB | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | NO | AUTO_INCREMENT | PK. |
| `name` | `VARCHAR(100)` | NO | — | |
| `slug` | `VARCHAR(120)` | NO | — | UNIQUE. |
| `description` | `VARCHAR(255)` | YES | `NULL` | |
| `sort_order` | `SMALLINT UNSIGNED` | NO | `0` | |
| `created_at` | `TIMESTAMP` | YES | `NULL` | |
| `updated_at` | `TIMESTAMP` | YES | `NULL` | |

- **Primary key:** `id`. **Foreign key:** tidak ada.
- **Relasi Eloquent:** `ArticleCategory::articles()` hasMany `Article`.
- **Index & unique:** `PRIMARY (id)`; `article_categories_slug_unique (slug)`.
- **CHECK constraint:** tidak ada. **Cast Eloquent:** tidak ada.
- **Aturan hapus & arsip:** dapat dihapus; artikel di kategori tersebut menjadi tanpa kategori (`articles.article_category_id` `SET NULL`).

### 6.3 `articles`

**Tujuan.** Artikel kesehatan. Hanya artikel `status = published` dengan `published_at ≤ sekarang` yang tampil publik (publikasi terjadwal **[ASUMSI]**).

| Kolom | Tipe MariaDB | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | NO | AUTO_INCREMENT | PK. |
| `article_category_id` | `BIGINT UNSIGNED` | YES | `NULL` | FK → `article_categories.id`. |
| `author_user_id` | `BIGINT UNSIGNED` | YES | `NULL` | FK → `users.id`. |
| `title` | `VARCHAR(200)` | NO | — | |
| `slug` | `VARCHAR(220)` | NO | — | UNIQUE (termasuk baris yang di-soft-delete: slug artikel terhapus tidak dapat dipakai ulang); route key `artikel/{article:slug}`. |
| `excerpt` | `VARCHAR(300)` | NO | — | Ringkasan. |
| `body` | `LONGTEXT` | NO | — | **Rich text** (disanitasi). |
| `status` | `VARCHAR(10)` | NO | `'draft'` | `ArticleStatus`: `draft` / `published`. Default `draft` **[ASUMSI]**. |
| `published_at` | `DATETIME` | YES | `NULL` | Wajib terisi bila `published`. |
| `meta_title` | `VARCHAR(255)` | YES | `NULL` | |
| `meta_description` | `VARCHAR(255)` | YES | `NULL` | |
| `created_at` | `TIMESTAMP` | YES | `NULL` | |
| `updated_at` | `TIMESTAMP` | YES | `NULL` | |
| `deleted_at` | `TIMESTAMP` | YES | `NULL` | Soft delete (`$table->softDeletes()`). |

- **Primary key:** `id`.
- **Foreign key:** `article_category_id` → `article_categories.id` `ON DELETE SET NULL`; `author_user_id` → `users.id` `ON DELETE SET NULL`.
- **Relasi Eloquent:** `Article::category()` belongsTo `ArticleCategory`; `Article::author()` belongsTo `User` (`author_user_id`); `Article::cover()` morphOne `Media`; `Article::media()` morphMany (koleksi `editor`). Scope `published()`.
- **Index & unique:** `PRIMARY (id)`; `articles_slug_unique (slug)`; `articles_status_published_at_index (status, published_at)`; FK `article_category_id`, `author_user_id`.
- **CHECK constraint:** tidak ada (`published` ⇒ `published_at` terisi dijaga Form Request, Invarian I-24).
- **Cast Eloquent:** `status` → `ArticleStatus`; `published_at` → `datetime`; trait `SoftDeletes`. Route key = `slug`.
- **Aturan hapus & arsip:** **soft delete** (`deleted_at`); tidak ada UI tempat sampah/pemulihan di prototipe **[ASUMSI]** (pemulihan lewat `tinker`). Media artikel yang di-soft-delete dipertahankan dan tidak disentuh `media:prune` (`model_id` tetap terisi).

### 6.4 `banners`

**Tujuan.** Banner/karusel beranda dengan jadwal tayang opsional. Gambar di `media` (koleksi `image`, tunggal; min 1200×400, maks 4 MB).

| Kolom | Tipe MariaDB | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | NO | AUTO_INCREMENT | PK. |
| `title` | `VARCHAR(150)` | NO | — | |
| `subtitle` | `VARCHAR(255)` | YES | `NULL` | |
| `button_label` | `VARCHAR(50)` | YES | `NULL` | |
| `button_url` | `VARCHAR(255)` | YES | `NULL` | Path internal (diawali `/`) atau URL `https://`; skema lain (mis. `javascript:`) ditolak Form Request. |
| `sort_order` | `SMALLINT UNSIGNED` | NO | `0` | |
| `is_active` | `TINYINT(1)` | NO | `1` | |
| `starts_at` | `DATETIME` | YES | `NULL` | NULL = langsung tayang **[ASUMSI]**. |
| `ends_at` | `DATETIME` | YES | `NULL` | NULL = tanpa batas akhir. |
| `created_at` | `TIMESTAMP` | YES | `NULL` | |
| `updated_at` | `TIMESTAMP` | YES | `NULL` | |

- **Primary key:** `id`. **Foreign key:** tidak ada.
- **Relasi Eloquent:** `Banner::image()` morphOne `Media` (koleksi `image`). Scope `currentlyActive()`: `is_active` dan jendela `starts_at`/`ends_at` (NULL = tanpa batas).
- **Index & unique:** `PRIMARY (id)`; `banners_is_active_sort_order_index (is_active, sort_order)`.
- **CHECK constraint:** `chk_banners_period (starts_at IS NULL OR ends_at IS NULL OR ends_at > starts_at)`.
- **Cast Eloquent:** `sort_order` → `integer`; `is_active` → `boolean`; `starts_at`, `ends_at` → `datetime`.
- **Aturan hapus & arsip:** dapat dihapus (media ikut dihapus) atau dinonaktifkan.

### 6.5 `galleries`

**Tujuan.** Album galeri foto; foto di `media` (koleksi `photos`, banyak). Sampul album = foto pertama menurut `sort_order` (tidak ada kolom sampul).

| Kolom | Tipe MariaDB | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | NO | AUTO_INCREMENT | PK. |
| `title` | `VARCHAR(150)` | NO | — | |
| `slug` | `VARCHAR(170)` | NO | — | UNIQUE; route key `galeri/{gallery:slug}`. |
| `description` | `VARCHAR(500)` | YES | `NULL` | |
| `is_published` | `TINYINT(1)` | NO | `0` | Hanya album terbit yang tampil. |
| `sort_order` | `SMALLINT UNSIGNED` | NO | `0` | |
| `created_at` | `TIMESTAMP` | YES | `NULL` | |
| `updated_at` | `TIMESTAMP` | YES | `NULL` | |

- **Primary key:** `id`. **Foreign key:** tidak ada.
- **Relasi Eloquent:** `Gallery::photos()` morphMany `Media` (koleksi `photos`, urut `sort_order`, `id`).
- **Index & unique:** `PRIMARY (id)`; `galleries_slug_unique (slug)`; `galleries_is_published_sort_order_index (is_published, sort_order)`.
- **CHECK constraint:** tidak ada. **Cast Eloquent:** `is_published` → `boolean`. Route key = `slug`.
- **Aturan hapus & arsip:** dapat dihapus; seluruh foto album (`media`) dan berkasnya ikut dihapus oleh `MediaService`.

### 6.6 `faqs`

**Tujuan.** Pertanyaan yang sering diajukan, dikelompokkan per kategori.

| Kolom | Tipe MariaDB | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | NO | AUTO_INCREMENT | PK. |
| `category` | `VARCHAR(50)` | NO | — | Salah satu `pendaftaran`, `bpjs`, `layanan`, `umum` (konstanta `Faq::CATEGORIES`, divalidasi Form Request). |
| `question` | `VARCHAR(255)` | NO | — | |
| `answer` | `TEXT` | NO | — | Teks biasa (bukan HTML), dirender `{{ }}`. |
| `sort_order` | `SMALLINT UNSIGNED` | NO | `0` | |
| `is_published` | `TINYINT(1)` | NO | `0` | |
| `created_at` | `TIMESTAMP` | YES | `NULL` | |
| `updated_at` | `TIMESTAMP` | YES | `NULL` | |

- **Primary key:** `id`. **Foreign key:** tidak ada. **Relasi Eloquent:** tidak ada.
- **Index & unique:** `PRIMARY (id)`; `faqs_is_published_category_sort_order_index (is_published, category, sort_order)`.
- **CHECK constraint:** tidak ada. **Cast Eloquent:** `is_published` → `boolean`.
- **Aturan hapus & arsip:** dapat dihapus atau di-unpublish.

### 6.7 `insurance_partners`

**Tujuan.** Daftar asuransi/perusahaan mitra yang ditampilkan pada halaman informasi BPJS & asuransi dan sebagai pilihan `appointments.insurance_partner_id`. Data seed **fiktif**, berlabel "Contoh"; dilarang mengklaim kerja sama nyata.

| Kolom | Tipe MariaDB | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | NO | AUTO_INCREMENT | PK. |
| `name` | `VARCHAR(150)` | NO | — | |
| `slug` | `VARCHAR(170)` | NO | — | UNIQUE. |
| `type` | `VARCHAR(20)` | NO | — | `asuransi` / `perusahaan` (konstanta `InsurancePartner::TYPES`). |
| `description` | `VARCHAR(500)` | YES | `NULL` | |
| `website_url` | `VARCHAR(255)` | YES | `NULL` | URL `https://` (divalidasi). |
| `is_active` | `TINYINT(1)` | NO | `1` | Nonaktif = tidak muncul sebagai pilihan booking baru. |
| `sort_order` | `SMALLINT UNSIGNED` | NO | `0` | |
| `created_at` | `TIMESTAMP` | YES | `NULL` | |
| `updated_at` | `TIMESTAMP` | YES | `NULL` | |

- **Primary key:** `id`. **Foreign key:** tidak ada (induk dari `appointments`).
- **Relasi Eloquent:** `InsurancePartner::appointments()` hasMany `Appointment`; `InsurancePartner::logo()` morphOne `Media` (koleksi `logo`).
- **Index & unique:** `PRIMARY (id)`; `insurance_partners_slug_unique (slug)`; `insurance_partners_is_active_type_sort_order_index (is_active, type, sort_order)`.
- **CHECK constraint:** tidak ada. **Cast Eloquent:** `is_active` → `boolean`.
- **Aturan hapus & arsip:** mitra yang pernah dipakai appointment tidak dapat dihapus (FK `RESTRICT`); nonaktifkan. Mitra yang tidak pernah dipakai boleh dihapus (logo ikut dihapus).

### 6.8 `contact_messages`

**Tujuan.** Pesan dari formulir kontak publik (dibatasi `throttle:contact`). Mengandung data pribadi pengirim.

| Kolom | Tipe MariaDB | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | NO | AUTO_INCREMENT | PK. |
| `name` | `VARCHAR(100)` | NO | — | |
| `email` | `VARCHAR(255)` | NO | — | |
| `phone` | `VARCHAR(20)` | YES | `NULL` | |
| `subject` | `VARCHAR(150)` | NO | — | |
| `message` | `TEXT` | NO | — | Teks biasa, dirender `{{ }}`. |
| `status` | `VARCHAR(10)` | NO | `'new'` | `ContactMessageStatus`: `new`, `read`, `replied`, `archived`. |
| `consent_at` | `DATETIME` | NO | — | Waktu pengirim menyetujui penggunaan data. |
| `ip_hash` | `CHAR(64)` | NO | — | SHA-256 dari IP + `APP_KEY` (heksadesimal). IP mentah **tidak** disimpan. |
| `handled_by` | `BIGINT UNSIGNED` | YES | `NULL` | FK → `users.id`. |
| `handled_at` | `DATETIME` | YES | `NULL` | |
| `created_at` | `TIMESTAMP` | YES | `NULL` | |
| `updated_at` | `TIMESTAMP` | YES | `NULL` | |

- **Primary key:** `id`.
- **Foreign key:** `handled_by` → `users.id` `ON DELETE SET NULL`.
- **Relasi Eloquent:** `ContactMessage::handler()` belongsTo `User` (`handled_by`).
- **Index & unique:** `PRIMARY (id)`; `contact_messages_status_created_at_index (status, created_at)`; FK `handled_by`.
- **CHECK constraint:** tidak ada. **Cast Eloquent:** `status` → `ContactMessageStatus`; `consent_at`, `handled_at` → `datetime`. `$hidden = ['ip_hash']`.
- **Aturan hapus & arsip:** tidak dihapus lewat UI; diarsipkan (`status = archived`). **[KONFIRMASI]** retensi produksi (bagian 13).

---

## 7. Domain: Sistem

### 7.1 `media`

**Tujuan.** Satu tabel untuk **semua gambar** (polymorphic). Berkas fisik disimpan di Laravel Storage (disk `public` atau `local`); database hanya menyimpan path dan metadata, **tanpa BLOB** (00 bagian K). Pemetaan pemilik → koleksi ada di [08-erd.md](08-erd.md) bagian 5.

| Kolom | Tipe MariaDB | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | NO | AUTO_INCREMENT | PK. |
| `model_type` | `VARCHAR(255)` | YES | `NULL` | Kelas pemilik (mis. `App\Models\Doctor`). NULL bersama `model_id` = unggahan editor yang belum terikat. |
| `model_id` | `BIGINT UNSIGNED` | YES | `NULL` | Id pemilik (tanpa FK; polymorphic). |
| `collection` | `VARCHAR(40)` | NO | — | `photo`, `cover`, `gallery`, `image`, `photos`, `logo`, `editor`. |
| `disk` | `VARCHAR(10)` | NO | `'public'` | `public` atau `local` (privat; belum dipakai). |
| `directory` | `VARCHAR(255)` | NO | — | Direktori relatif pada disk, mis. `photo/2026/10`. |
| `file_name` | `VARCHAR(100)` | NO | — | UUID + ekstensi buatan sistem (mis. `3f2a…c1.jpg`). UNIQUE. Path berkas = `directory` + `/` + `file_name`. |
| `original_name` | `VARCHAR(255)` | NO | — | Nama berkas asli yang **disanitasi**; hanya metadata, tidak dipakai sebagai path. |
| `mime_type` | `VARCHAR(100)` | NO | — | `image/jpeg`, `image/png`, `image/webp` (hasil re-encode). |
| `extension` | `VARCHAR(10)` | NO | — | `jpg`, `png`, `webp`. |
| `size_bytes` | `INT UNSIGNED` | NO | — | Ukuran berkas setelah re-encode. |
| `width` | `SMALLINT UNSIGNED` | NO | — | Piksel, ≤ 6000 pada unggahan (setelah resize ≤ 1600). |
| `height` | `SMALLINT UNSIGNED` | NO | — | Piksel. |
| `thumb_path` | `VARCHAR(255)` | YES | `NULL` | Path thumbnail lebar 400 px relatif pada disk (mis. `photo/2026/10/thumbs/{uuid}.jpg`) **[ASUMSI]**. |
| `alt_text` | `VARCHAR(255)` | NO | — | Teks alternatif; wajib non-kosong untuk semua koleksi publik (Form Request/`MediaService`). |
| `caption` | `VARCHAR(255)` | YES | `NULL` | |
| `sort_order` | `SMALLINT UNSIGNED` | NO | `0` | Urutan dalam koleksi banyak. |
| `uploaded_by` | `BIGINT UNSIGNED` | YES | `NULL` | FK → `users.id`. |
| `created_at` | `TIMESTAMP` | YES | `NULL` | |
| `updated_at` | `TIMESTAMP` | YES | `NULL` | |

- **Primary key:** `id`.
- **Foreign key:** `uploaded_by` → `users.id` `ON DELETE SET NULL`. (Tidak ada FK untuk `model_id`; polymorphic.)
- **Relasi Eloquent:** `Media::model()` morphTo; `Media::uploader()` belongsTo `User` (`uploaded_by`). Dari sisi pemilik: trait `HasMedia` → `media()` morphMany serta akses per koleksi lewat `firstMedia()`/`mediaIn()` (tanpa relasi khusus per koleksi pada `Doctor`/`Clinic`, F2-R24). Accessor `url` (`Storage::disk($disk)->url("$directory/$file_name")`, hanya disk `public`), `thumb_url`.
- **Index & unique:** `PRIMARY (id)`; `media_model_type_model_id_collection_sort_order_index (model_type, model_id, collection, sort_order)`; `media_file_name_unique (file_name)` **[ASUMSI]**; FK `media_uploaded_by_foreign`. Pembersihan `media:prune` (baris `model_id` NULL berumur > 24 jam) memindai tabel kecil sehingga tidak perlu index tambahan.
- **CHECK constraint:** `chk_media_owner ((model_type IS NULL AND model_id IS NULL) OR (model_type IS NOT NULL AND model_id IS NOT NULL))` **[ASUMSI]** — pasangan morph terisi bersama atau kosong bersama.
- **Cast Eloquent:** `size_bytes`, `width`, `height`, `sort_order` → `integer`.
- **Aturan hapus & arsip:** dihapus **hanya** lewat `MediaService` (`delete`, `replace`, `prune`): berkas fisik dihapus setelah transaksi commit (`DB::afterCommit`). Pemilik yang dihapus (event `deleting` trait `HasMedia`) membawa seluruh medianya. Koleksi tunggal diganti dengan menyimpan baris baru lalu menghapus baris lama. `media:prune` menghapus (a) baris `model_id` NULL > 24 jam, (b) berkas di disk yang tidak tercatat di `media`; **[ASUMSI]** juga (c) baris yang pemiliknya sudah tidak ada (`--dry-run` mendukung ketiganya). Soft delete pemilik (`articles`) tidak memicu penghapusan media.

### 7.2 `website_settings`

**Tujuan.** Pengaturan situs berpasangan kunci–nilai (identitas, kontak, peta, parameter booking, SEO), diedit lewat `admin.settings.edit` (izin `settings.manage`). Di-cache; cache dibersihkan saat disimpan. 19 kunci baku (00 E.7).

| Kolom | Tipe MariaDB | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | NO | AUTO_INCREMENT | PK. |
| `key` | `VARCHAR(100)` | NO | — | UNIQUE; mis. `booking.window_days`. Kata cadangan SQL (backtick pada SQL mentah). |
| `value` | `TEXT` | YES | `NULL` | Nilai serial string; kosong = pakai default aplikasi. |
| `type` | `VARCHAR(10)` | NO | — | `string`, `text`, `int`, `bool`, `url`, `email`, `json`; menentukan validasi dan konversi `SettingsService`. |
| `group` | `VARCHAR(20)` | NO | — | `identitas`, `kontak`, `booking`, `seo`, `privasi` (00 E.6). Kata cadangan SQL (backtick pada SQL mentah). |
| `label` | `VARCHAR(150)` | NO | — | Label formulir (Bahasa Indonesia). |
| `updated_by` | `BIGINT UNSIGNED` | YES | `NULL` | FK → `users.id`. |
| `created_at` | `TIMESTAMP` | YES | `NULL` | |
| `updated_at` | `TIMESTAMP` | YES | `NULL` | |

- **Primary key:** `id`.
- **Foreign key:** `updated_by` → `users.id` `ON DELETE SET NULL`.
- **Relasi Eloquent:** `WebsiteSetting::updater()` belongsTo `User`; `WebsiteSetting::logo()` morphOne `Media` (koleksi `logo`; hanya bermakna untuk baris `site.logo_media_id`).
- **Index & unique:** `PRIMARY (id)`; `website_settings_key_unique (key)`; `website_settings_group_index (group)`.
- **CHECK constraint:** tidak ada (validasi nilai per `type` di Form Request/`SettingsService`).
- **Cast Eloquent:** tidak ada pada model; `SettingsService::get()` mengonversi `value` menurut `type` (`int`, `bool` dari `"1"`/`"0"`, `json` → array).
- **Aturan hapus & arsip:** tidak ada route tambah/hapus: set kunci baku dibuat seeder (juga di produksi) dan UI hanya mengubah `value`. Penempatan grup mengikuti 00 E.7: `privacy.consent_version` berada pada grup `privasi` (kunci `map.*` pada grup `kontak`); layar `admin.settings.edit` menampilkan lima tab (identitas, kontak, booking, seo, privasi).

### 7.3 `audit_logs`

**Tujuan.** Jejak audit perubahan penting (akun, peran, jadwal, sesi, pendaftaran, pengaturan, ketersediaan kamar, ekspor CSV). *Append-only*, hanya dapat dilihat Super Admin (`audit.view`). Contoh `action`: `appointment.created`, `appointment.status_changed`, `session.status_changed`, `session.resolved`, `session.pending_replaced`, `schedule.updated`, `exception.created`, `staff.created`, `staff.deactivated`, `role.permissions_updated`, `settings.updated`, `room.availability_updated`, `report.exported`, `patient.account_linked` (katalog baku di [12-aturan-bisnis-validasi-keamanan.md](12-aturan-bisnis-validasi-keamanan.md) bagian 8; bila berbeda, 12 yang berlaku).

| Kolom | Tipe MariaDB | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | NO | AUTO_INCREMENT | PK. |
| `actor_type` | `VARCHAR(10)` | NO | — | `ActorType`: `patient` / `staff` / `system`. |
| `user_id` | `BIGINT UNSIGNED` | YES | `NULL` | FK → `users.id`; NULL untuk `system`. |
| `action` | `VARCHAR(100)` | NO | — | Mis. `appointment.status_changed`. |
| `auditable_type` | `VARCHAR(255)` | NO | — | Kelas model yang diaudit. |
| `auditable_id` | `BIGINT UNSIGNED` | YES | `NULL` | NULL untuk aksi tanpa satu baris (mis. ekspor). |
| `description` | `VARCHAR(255)` | NO | — | Kalimat ringkas Bahasa Indonesia. |
| `old_values` | `LONGTEXT` (JSON) | YES | `NULL` | Nilai sebelum (setelah redaksi); `$table->json()` → CHECK `json_valid` otomatis. |
| `new_values` | `LONGTEXT` (JSON) | YES | `NULL` | Nilai sesudah (setelah redaksi); `$table->json()` → CHECK `json_valid` otomatis. |
| `ip_address` | `VARCHAR(45)` | YES | `NULL` | IPv4/IPv6; NULL untuk aksi CLI/sistem **[ASUMSI]**. |
| `user_agent` | `VARCHAR(255)` | YES | `NULL` | Dipotong 255 karakter; NULL untuk CLI **[ASUMSI]**. |
| `created_at` | `TIMESTAMP` | NO | `CURRENT_TIMESTAMP` | Tanpa `updated_at`. |

- **Primary key:** `id`.
- **Foreign key:** `user_id` → `users.id` `ON DELETE SET NULL`.
- **Relasi Eloquent:** `AuditLog::user()` belongsTo `User`; `AuditLog::auditable()` morphTo.
- **Index & unique:** `PRIMARY (id)`; `audit_logs_auditable_type_auditable_id_index (auditable_type, auditable_id)`; `audit_logs_user_id_created_at_index (user_id, created_at)` (juga melayani FK); `audit_logs_action_created_at_index (action, created_at)`; `audit_logs_created_at_index (created_at)`.
- **CHECK constraint:** tidak ada yang bernama `chk_*`; hanya CHECK otomatis `old_values` = `json_valid(old_values)` dan `new_values` = `json_valid(new_values)` dari tipe `JSON`.
- **Cast Eloquent:** `actor_type` → `ActorType`; `old_values`, `new_values` → `array`; `created_at` → `datetime`. Model: `const UPDATED_AT = null`; `updating`/`deleting` melempar `LogicException`.
- **Aturan hapus & arsip:** tidak dapat diubah/dihapus dari UI. **Redaksi wajib** oleh `AuditLogger` sebelum `old_values`/`new_values` ditulis (nilai diganti `[disamarkan]`): `password`, `remember_token`, `token`, `bpjs_number`, `payment_reference` (00 E.6); **[ASUMSI]** ditambah (redaksi tingkat 2, 12 §8.1) `complaint`, `address`, `phone`, `email`, `birth_date`, `message` (hanya untuk `patient.*`, `appointment.*`, `contact_message.*`) dan setiap atribut `$hidden`/`encrypted` pada model; teks panjang (`body`, `bio`, `short_bio`, `description`) dicatat sebagai nama bidang. Perubahan pada kolom yang diredaksi tetap dicatat sebagai "diubah" tanpa isi.

---

## 8. Tabel bawaan Laravel lainnya (cache, antrean, migrasi)

Dibuat oleh migration skeleton Laravel 12 (`0001_01_01_000001_create_cache_table.php` dan `0001_01_01_000002_create_jobs_table.php`). Tidak ada model Eloquent untuk tabel-tabel ini dan tidak ada FK. Struktur di bawah diverifikasi terhadap migration skeleton `laravel/laravel` 12.x (diterapkan pada MariaDB 10.4.32); bila skeleton yang terpasang berbeda (mis. tipe `expiration`), ikuti skeleton dan perbarui bagian ini.

**Penggunaan nyata dalam aplikasi ini** (`CACHE_STORE=database` **[ASUMSI]**, default skeleton): cache pengaturan situs (`website_settings`), cache izin Spatie (`spatie.permission.cache`), penanda `sessions:generated:{Y-m-d}`, kunci `Cache::lock` untuk `PracticeSessionGenerator` dan `booking-submit:{user_id}` (tabel `cache_locks`), serta penghitung *rate limiter* (`login`, `password-reset`, `register`, `booking`, `contact`). `QUEUE_CONNECTION=sync` pada lokal, sehingga `jobs`/`job_batches`/`failed_jobs` tetap **kosong** pada prototipe; tabel dibuat agar pindah ke `database` + worker di produksi tidak memerlukan migration baru.

### 8.1 `cache`

**Tujuan.** Penyimpanan cache (driver `database`).

| Kolom | Tipe MariaDB | Null | Default | Keterangan |
|---|---|---|---|---|
| `key` | `VARCHAR(255)` | NO | — | PK. Kata cadangan SQL. |
| `value` | `MEDIUMTEXT` | NO | — | Nilai serial. |
| `expiration` | `INT` | NO | — | Epoch detik kedaluwarsa; di-index. |

- **Primary key:** `key`. **Foreign key:** tidak ada. **Relasi Eloquent:** tidak ada (akses lewat `Cache`). **Index & unique:** `cache_expiration_index (expiration)`. **CHECK:** tidak ada. **Cast:** tidak ada.
- **Aturan hapus & arsip:** entri kedaluwarsa dibuang saat dibaca/lotere; `php artisan cache:clear` mengosongkan tabel. Aman dikosongkan kapan saja (data turunan).

### 8.2 `cache_locks`

**Tujuan.** Kunci atomik untuk `Cache::lock()`.

| Kolom | Tipe MariaDB | Null | Default | Keterangan |
|---|---|---|---|---|
| `key` | `VARCHAR(255)` | NO | — | PK. |
| `owner` | `VARCHAR(255)` | NO | — | Pemilik kunci. |
| `expiration` | `INT` | NO | — | Epoch detik kedaluwarsa; di-index. |

- **Primary key:** `key`. **Foreign key / Relasi / Cast / CHECK:** tidak ada. **Index & unique:** `cache_locks_expiration_index (expiration)`.
- **Aturan hapus & arsip:** kunci kedaluwarsa diambil alih saat `acquire`; aman dikosongkan.

### 8.3 `jobs`

**Tujuan.** Antrean job (driver `database`; produksi).

| Kolom | Tipe MariaDB | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | NO | AUTO_INCREMENT | PK. |
| `queue` | `VARCHAR(255)` | NO | — | Nama antrean. |
| `payload` | `LONGTEXT` | NO | — | Payload job (serial). |
| `attempts` | `TINYINT UNSIGNED` | NO | — | Jumlah percobaan. |
| `reserved_at` | `INT UNSIGNED` | YES | `NULL` | Epoch saat dipesan worker. |
| `available_at` | `INT UNSIGNED` | NO | — | Epoch job siap diproses. |
| `created_at` | `INT UNSIGNED` | NO | — | Epoch pembuatan. |

- **Primary key:** `id`. **Foreign key / Relasi / Cast / CHECK:** tidak ada. **Index & unique:** `jobs_queue_index (queue)`.
- **Aturan hapus & arsip:** baris dihapus worker setelah sukses; gagal permanen dipindahkan ke `failed_jobs`.

### 8.4 `job_batches`

**Tujuan.** Metadata batch job (`Bus::batch`). **Tidak dipakai** pada rancangan ini; dibuat karena bagian migration skeleton.

| Kolom | Tipe MariaDB | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `VARCHAR(255)` | NO | — | PK. |
| `name` | `VARCHAR(255)` | NO | — | |
| `total_jobs` | `INT` | NO | — | |
| `pending_jobs` | `INT` | NO | — | |
| `failed_jobs` | `INT` | NO | — | |
| `failed_job_ids` | `LONGTEXT` | NO | — | |
| `options` | `MEDIUMTEXT` | YES | `NULL` | |
| `cancelled_at` | `INT` | YES | `NULL` | |
| `created_at` | `INT` | NO | — | |
| `finished_at` | `INT` | YES | `NULL` | |

- **Primary key:** `id`. **Foreign key / Relasi / Cast / CHECK:** tidak ada. **Index & unique:** hanya PK.
- **Aturan hapus & arsip:** tidak dipakai; bila kelak dipakai, bersihkan dengan `queue:prune-batches`.

### 8.5 `failed_jobs`

**Tujuan.** Job yang gagal permanen (untuk `queue:retry`).

| Kolom | Tipe MariaDB | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | NO | AUTO_INCREMENT | PK. |
| `uuid` | `VARCHAR(255)` | NO | — | UNIQUE. |
| `connection` | `TEXT` | NO | — | |
| `queue` | `TEXT` | NO | — | |
| `payload` | `LONGTEXT` | NO | — | |
| `exception` | `LONGTEXT` | NO | — | Jejak galat; **dapat memuat data dari payload** sehingga diperlakukan sebagai data internal (bagian 13). |
| `failed_at` | `TIMESTAMP` | NO | `CURRENT_TIMESTAMP` | |

- **Primary key:** `id`. **Foreign key / Relasi / Cast / CHECK:** tidak ada. **Index & unique:** `failed_jobs_uuid_unique (uuid)`.
- **Aturan hapus & arsip:** dibersihkan operator (`queue:flush`/`queue:forget`); tidak ada penghapusan otomatis.

### 8.6 `migrations` (internal framework)

**Tujuan.** Catatan migration yang sudah dijalankan oleh `php artisan migrate`. Bukan bagian domain; wajib ikut backup agar `migrate` tidak mengulang migration setelah restore.

| Kolom | Tipe MariaDB | Null | Default | Keterangan |
|---|---|---|---|---|
| `id` | `INT UNSIGNED` | NO | AUTO_INCREMENT | PK. |
| `migration` | `VARCHAR(255)` | NO | — | Nama berkas migration tanpa `.php`. |
| `batch` | `INT` | NO | — | Nomor batch. |

- **Primary key:** `id`. **Foreign key / Relasi / Cast / CHECK:** tidak ada. **Index & unique:** hanya PK.
- **Aturan hapus & arsip:** jangan diedit manual.

---

## 9. Mengapa tidak ada tabel `rooms` / `room_availability`

Kebutuhan awal (§11) meminta pemilihan antara `rooms` atau `room_availability` "sesuai model kapasitas". Keputusan 00 bagian E.4: **model kapasitas agregat per kategori kamar**, diperbarui manual, **tanpa** tabel per kamar/tempat tidur. Alasannya:

1. **Tidak ada sumber data per tempat tidur.** Prototipe tidak terintegrasi dengan SIMRS. Tabel `rooms` per kamar atau `room_beds` per tempat tidur hanya berisi data yang harus diketik ulang admin (puluhan sampai ratusan baris) tanpa ada sistem yang memvalidasinya, sehingga memberi kesan presisi yang palsu.
2. **Kebutuhan fitur seluruhnya berada pada level kategori** (§7): nama, deskripsi, galeri foto, daftar fasilitas, kapasitas tempat tidur, tempat tidur tersedia, tarif contoh, status aktif, dan waktu pembaruan terakhir. Semuanya sudah menjadi kolom `room_categories`.
3. **Sederhana dan aman konsisten.** Satu `UPDATE` pada satu baris dengan CHECK `0 ≤ beds_available ≤ bed_capacity` menjaga invarian; tidak ada penjumlahan lintas baris yang dapat melenceng, dan dashboard cukup membaca empat baris.
4. **Menghindari klaim *real-time*** (kebutuhan §7 dan §14): UI selalu menampilkan "Diperbarui manual pada {`availability_updated_at`}", bukan "tersedia sekarang".

**Riwayat perubahan ketersediaan tercatat di `audit_logs`**, bukan di tabel khusus. Setiap pembaruan (aksi `room.availability_updated`) menulis satu baris dengan `auditable_type = App\Models\RoomCategory`, `auditable_id` = id kategori, `user_id` pelaku, `old_values`/`new_values` berisi `bed_capacity` dan `beds_available`, serta `created_at`. Super Admin menelusurinya di `admin.audit-logs.index` dengan filter aksi tersebut.

**Keterbatasan yang diterima:** (a) tidak ada status per kamar atau per tempat tidur; (b) tidak ada deret waktu okupansi terstruktur untuk grafik tren (hanya jejak audit); (c) kapasitas tidak dapat dipecah per lantai atau ruang.

**Jalur pengembangan saat integrasi SIMRS (di luar tahap ini):** tambahkan `rooms` (`room_category_id` FK `RESTRICT`, `code`, `floor`, `status`) dan/atau `room_availability_snapshots` (`room_category_id`, `bed_capacity`, `beds_available`, `captured_at`, `source`); `room_categories.beds_available` berubah menjadi nilai turunan/cache tanpa mengubah halaman publik.

---

## 10. Urutan migration

Laravel menjalankan migration menurut urutan nama berkas. Urutan di bawah dibuat agar setiap tabel dibuat **setelah** semua tabel yang dirujuk FK-nya. Berkas `0001_01_01_*` adalah bawaan skeleton (jangan diganti nama); migration proyek memakai awalan `2026_10_02_` + nomor berurutan 6 digit.

| No | Berkas migration | Tabel yang dibuat/diubah | Bergantung pada |
|---:|---|---|---|
| 1 | `0001_01_01_000000_create_users_table.php` | `users`, `password_reset_tokens`, `sessions` (bawaan) | — |
| 2 | `0001_01_01_000001_create_cache_table.php` | `cache`, `cache_locks` (bawaan) | — |
| 3 | `0001_01_01_000002_create_jobs_table.php` | `jobs`, `job_batches`, `failed_jobs` (bawaan) | — |
| 4 | `2026_10_02_000010_add_account_fields_to_users_table.php` | `users` + `phone`, `account_type`, `is_active`, `must_change_password`, `last_login_at` | 1 |
| 5 | `2026_10_02_000020_create_permission_tables.php` | `permissions`, `roles`, `model_has_permissions`, `model_has_roles`, `role_has_permissions` (Spatie; hasil `vendor:publish` lalu **diganti nama** agar urut) | 1 |
| 6 | `2026_10_02_000030_create_notifications_table.php` | `notifications` (dari `php artisan make:notifications-table` apa adanya; `uuid('id')` menjadi `CHAR(36)` di MariaDB 10.4) | 1 |
| 7 | `2026_10_02_000100_create_patient_profiles_table.php` | `patient_profiles` (dibuat di fase 3; pada database yang sudah memigrasikan fase 1–2 Laravel menjalankannya sebagai migration tertunda, sedangkan `migrate:fresh` mengikuti urutan nama; F3-R2) | `users` |
| 8 | `2026_10_02_000200_create_specialties_table.php` | `specialties` | — |
| 9 | `2026_10_02_000300_create_clinics_table.php` | `clinics` | `specialties` |
| 10 | `2026_10_02_000400_create_doctors_table.php` | `doctors` | `clinics` |
| 11 | `2026_10_02_000500_create_doctor_specialties_table.php` | `doctor_specialties` | `doctors`, `specialties` |
| 12 | `2026_10_02_000600_create_doctor_schedules_table.php` | `doctor_schedules` (+ 4 CHECK) | `doctors`, `clinics`, `users` |
| 13 | `2026_10_02_000700_create_schedule_exceptions_table.php` | `schedule_exceptions` (+ 2 CHECK) | `doctors`, `users` |
| 14 | `2026_10_02_000800_create_practice_sessions_table.php` | `practice_sessions` (+ 4 CHECK) | `doctors`, `clinics`, `doctor_schedules`, `schedule_exceptions`, `users` |
| 15 | `2026_10_02_000900_create_insurance_partners_table.php` | `insurance_partners` (dibuat sebelum `appointments` karena dirujuk FK, walau bertema konten; dibuat di fase 3 dengan skema ini apa adanya, sedangkan model, factory, seeder, dan admin-nya di fase 4, F3-R3) | — |
| 16 | `2026_10_02_001000_create_appointments_table.php` | `appointments` (+ generated column, UNIQUE komposit, CHECK) | `patient_profiles`, `practice_sessions`, `doctors`, `clinics`, `insurance_partners`, `users` |
| 17 | `2026_10_02_001100_create_appointment_status_histories_table.php` | `appointment_status_histories` | `appointments`, `users` |
| 18 | `2026_10_02_001200_create_room_categories_table.php` | `room_categories` (+ 1 CHECK) | `users` |
| 19 | `2026_10_02_001300_create_facilities_table.php` | `facilities` | — |
| 20 | `2026_10_02_001400_create_pages_table.php` | `pages` | `users` |
| 21 | `2026_10_02_001500_create_article_categories_table.php` | `article_categories` | — |
| 22 | `2026_10_02_001600_create_articles_table.php` | `articles` | `article_categories`, `users` |
| 23 | `2026_10_02_001700_create_banners_table.php` | `banners` (+ CHECK) | — |
| 24 | `2026_10_02_001800_create_galleries_table.php` | `galleries` | — |
| 25 | `2026_10_02_001900_create_faqs_table.php` | `faqs` | — |
| 26 | `2026_10_02_002000_create_contact_messages_table.php` | `contact_messages` | `users` |
| 27 | `2026_10_02_002100_create_media_table.php` | `media` (+ CHECK) | `users` |
| 28 | `2026_10_02_002200_create_website_settings_table.php` | `website_settings` | `users` |
| 29 | `2026_10_02_002300_create_audit_logs_table.php` | `audit_logs` | `users` |

Catatan:

- Migration 5 (Spatie) membaca `config/permission.php`; berkas konfigurasi itu harus sudah dipublikasikan (`php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"`) sebelum `migrate`, bila tidak migration melempar galat.
- Migration 5 juga memanggil `app('cache')->forget(...)` pada akhir `up()` (menghapus cache izin), sehingga dengan `CACHE_STORE=database` tabel `cache` harus sudah ada: urutan 2 sebelum 5 pada tabel di atas wajib dipertahankan (diverifikasi).
- Migration 4 menambah kolom ke `users` lewat berkas terpisah agar migration skeleton tidak diubah. Karena proyek baru, menggabungkannya ke migration 1 juga dapat diterima; keduanya menghasilkan skema akhir yang sama.
- Setiap migration yang punya CHECK atau generated column memakai `DB::statement()`/`storedAs()` (bagian 11) dan **hanya** berjalan di MariaDB/MySQL; pengujian otomatis wajib memakai database MariaDB khusus (mis. `unpam_hospital_test`), bukan SQLite in-memory (bagian 14).
- `down()` setiap migration memanggil `Schema::dropIfExists('<tabel>')`; CHECK ikut terhapus bersama tabel. Rollback dilakukan terbalik; DDL MariaDB tidak transaksional (auto-commit), sehingga kegagalan di tengah migration pada lingkungan pengembangan diselesaikan dengan `php artisan migrate:fresh`.
- Urutan seeder mengikuti urutan dependensi yang sama (peran/izin, pengaturan, halaman, master spesialisasi/poliklinik/dokter, templat jadwal, sesi, pasien demo, booking demo); rinciannya di [13-data-contoh.md](13-data-contoh.md) dan [14-tahapan-implementasi.md](14-tahapan-implementasi.md).

---

## 11. Cuplikan migration khusus MariaDB

Laravel 12 belum punya API schema builder untuk `CHECK`; constraint ditambahkan lewat `DB::statement()` tepat setelah `Schema::create()` pada migration yang sama. Kolom generated memakai `storedAs()`. Cuplikan di bawah adalah bentuk yang dianjurkan (nama kolom/constraint wajib sama).

### 11.1 `appointments`: generated column `active_flag` + unique komposit

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
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->string('booking_code', 13)->unique();
            $table->foreignId('patient_profile_id')->constrained('patient_profiles')->restrictOnDelete();
            $table->foreignId('practice_session_id')->constrained('practice_sessions')->restrictOnDelete();
            $table->foreignId('doctor_id')->constrained('doctors')->restrictOnDelete();
            $table->foreignId('clinic_id')->constrained('clinics')->restrictOnDelete();
            $table->date('session_date');
            $table->unsignedSmallInteger('queue_number');
            $table->string('status', 20);
            $table->string('channel', 10);
            $table->string('payment_type', 10);
            $table->foreignId('insurance_partner_id')->nullable()->constrained('insurance_partners')->restrictOnDelete();
            $table->text('payment_reference')->nullable();            // terenkripsi (cast encrypted)
            $table->char('payment_reference_last4', 4)->nullable();
            $table->string('complaint', 500)->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('confirmed_at')->nullable();
            $table->dateTime('checked_in_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->dateTime('no_show_at')->nullable();
            $table->string('cancelled_by_type', 10)->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancel_reason', 255)->nullable();
            $table->dateTime('schedule_changed_at')->nullable();

            // Kolom generated STORED. Tidak boleh di-INSERT/UPDATE oleh aplikasi.
            // Bernilai 1 hanya untuk status aktif; selain itu NULL (NULL boleh berulang pada UNIQUE).
            $table->tinyInteger('active_flag')->nullable()
                ->storedAs("CASE WHEN status IN ('pending','confirmed','checked_in') THEN 1 ELSE NULL END");

            $table->timestamps();

            // Nomor antrean unik per sesi.
            $table->unique(['practice_session_id', 'queue_number']);
            // Pengganti partial unique index; NAMA EKSPLISIT karena nama default 70 karakter (> batas 64).
            $table->unique(
                ['patient_profile_id', 'practice_session_id', 'active_flag'],
                'appointments_patient_session_active_unique'
            );
            $table->index(['session_date', 'status']);
            $table->index(['patient_profile_id', 'session_date']);
            $table->index(['doctor_id', 'session_date']);
            $table->index('status');
            $table->index('created_at');
        });

        DB::statement('ALTER TABLE appointments ADD CONSTRAINT chk_appointments_queue CHECK (queue_number >= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
```

Hasil pada `SHOW CREATE TABLE appointments` di MariaDB 10.4.32 (diverifikasi; disederhanakan):

```sql
`active_flag` tinyint(4) GENERATED ALWAYS AS (case when `status` in ('pending','confirmed','checked_in') then 1 else NULL end) STORED,
UNIQUE KEY `appointments_patient_session_active_unique` (`patient_profile_id`,`practice_session_id`,`active_flag`),
CONSTRAINT `chk_appointments_queue` CHECK (`queue_number` >= 1)
```

Catatan penting:

- Laravel tidak menambahkan `NULL`/`NOT NULL` pada kolom generated kecuali `nullable(false)` diminta, sehingga `->nullable()->storedAs(...)` menghasilkan DDL yang diterima MariaDB 10.4.
- Pada `Appointment::create()` jangan menyertakan `active_flag`; panggil `->refresh()` bila nilainya dibutuhkan.
- Ekspresi generated menduplikasi `AppointmentStatus::ACTIVE`. Tes wajib melintasi semua *case* enum dan memastikan `active_flag` ≡ `in_array($status, AppointmentStatus::ACTIVE)` (Invarian I-15). Menambah status baru yang aktif memerlukan `ALTER TABLE` pada ekspresi ini.

### 11.2 `practice_sessions`: CHECK kuota, jam, antrean

```php
Schema::create('practice_sessions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('doctor_id')->constrained('doctors')->restrictOnDelete();
    $table->foreignId('clinic_id')->constrained('clinics')->restrictOnDelete();
    $table->foreignId('doctor_schedule_id')->nullable()->constrained('doctor_schedules')->nullOnDelete();
    $table->date('session_date');
    $table->time('start_time');
    $table->time('end_time');
    $table->unsignedSmallInteger('quota');
    $table->unsignedSmallInteger('booked_count')->default(0);
    $table->unsignedSmallInteger('last_queue_number')->default(0);
    $table->string('status', 20)->default('open');
    $table->string('status_reason', 255)->nullable();
    $table->foreignId('schedule_exception_id')->nullable()->constrained('schedule_exceptions')->nullOnDelete();
    $table->json('pending_change')->nullable();   // MariaDB: LONGTEXT + CHECK json_valid otomatis
    $table->boolean('is_manually_modified')->default(false);
    $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
    $table->dateTime('reviewed_at')->nullable();
    $table->timestamps();

    // NULL pada doctor_schedule_id boleh berulang -> sesi tambahan manual tidak terbatas.
    $table->unique(['doctor_schedule_id', 'session_date']);
    $table->index(['session_date', 'status']);
    $table->index(['doctor_id', 'session_date']);
    $table->index(['clinic_id', 'session_date']);
});

// CHECK utama (diwajibkan 00 bagian E.2 / F.6): kuota tidak pernah terlampaui.
DB::statement('ALTER TABLE practice_sessions ADD CONSTRAINT chk_sessions_quota CHECK (booked_count >= 0 AND booked_count <= quota)');
DB::statement('ALTER TABLE practice_sessions ADD CONSTRAINT chk_sessions_time CHECK (end_time > start_time)');
DB::statement('ALTER TABLE practice_sessions ADD CONSTRAINT chk_sessions_quota_range CHECK (quota BETWEEN 1 AND 200)');
DB::statement('ALTER TABLE practice_sessions ADD CONSTRAINT chk_sessions_queue CHECK (last_queue_number >= booked_count)');
```

Kolom `JSON` pada MariaDB 10.4.32 menghasilkan `longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`pending_change`))`; nama CHECK otomatis sama dengan nama kolom. Karena itu keempat CHECK JSON (`practice_sessions.pending_change`, `room_categories.amenities`, `audit_logs.old_values`, `audit_logs.new_values`) **tidak** ditulis lewat `DB::statement` dan tidak berawalan `chk_`.

### 11.3 CHECK lain (14 constraint bernama + 4 CHECK JSON otomatis)

| Tabel | Nama CHECK | Ekspresi | Pelanggaran berarti |
|---|---|---|---|
| `doctor_schedules` | `chk_schedules_time` | `end_time > start_time` | Jam templat terbalik. |
| `doctor_schedules` | `chk_schedules_day` | `day_of_week BETWEEN 1 AND 7` | Hari di luar ISO-8601. |
| `doctor_schedules` | `chk_schedules_quota` | `quota BETWEEN 1 AND 200` | Kuota templat tidak valid. |
| `doctor_schedules` | `chk_schedules_validity` | `valid_until IS NULL OR valid_until >= valid_from` | Rentang berlaku terbalik. |
| `schedule_exceptions` | `chk_exceptions_dates` | `end_date >= start_date` | Rentang pengecualian terbalik. |
| `schedule_exceptions` | `chk_schedule_exceptions_scope` | `(type <> 'doctor_leave' OR doctor_id IS NOT NULL) AND (type NOT IN ('hospital_holiday','national_holiday') OR doctor_id IS NULL)` | Cuti dokter tanpa dokter, atau libur RS/nasional berdokter (00 E.2). |
| `practice_sessions` | `chk_sessions_quota` | `booked_count >= 0 AND booked_count <= quota` | **Kuota terlampaui** → `QUOTA_FULL`. |
| `practice_sessions` | `chk_sessions_quota_range` | `quota BETWEEN 1 AND 200` | Kuota sesi tidak valid. |
| `practice_sessions` | `chk_sessions_time` | `end_time > start_time` | Jam sesi terbalik. |
| `practice_sessions` | `chk_sessions_queue` | `last_queue_number >= booked_count` | Pencacah antrean tidak sinkron (bug). |
| `appointments` | `chk_appointments_queue` | `queue_number >= 1` | Nomor antrean nol/negatif. |
| `room_categories` | `chk_room_categories_beds` | `beds_available >= 0 AND beds_available <= bed_capacity` | Tempat tidur tersedia melebihi kapasitas. |
| `banners` | `chk_banners_period` | `starts_at IS NULL OR ends_at IS NULL OR ends_at > starts_at` | Periode tayang terbalik. |
| `media` | `chk_media_owner` | `(model_type IS NULL AND model_id IS NULL) OR (model_type IS NOT NULL AND model_id IS NOT NULL)` | Pasangan morph setengah terisi. |

CHECK JSON otomatis (nama = nama kolom): `practice_sessions.pending_change`, `room_categories.amenities`, `audit_logs.old_values`, `audit_logs.new_values`, masing-masing `json_valid(kolom)`.

Bentuk migration untuk tabel lain mengikuti pola 11.2, mis.:

```php
// create_room_categories_table.php (setelah Schema::create)
DB::statement('ALTER TABLE room_categories ADD CONSTRAINT chk_room_categories_beds CHECK (beds_available >= 0 AND beds_available <= bed_capacity)');

// create_doctor_schedules_table.php (setelah Schema::create) - satu ALTER, empat constraint
DB::statement('ALTER TABLE doctor_schedules
    ADD CONSTRAINT chk_schedules_time CHECK (end_time > start_time),
    ADD CONSTRAINT chk_schedules_day CHECK (day_of_week BETWEEN 1 AND 7),
    ADD CONSTRAINT chk_schedules_quota CHECK (quota BETWEEN 1 AND 200),
    ADD CONSTRAINT chk_schedules_validity CHECK (valid_until IS NULL OR valid_until >= valid_from)');

// create_schedule_exceptions_table.php (setelah Schema::create) - dua constraint
// kombinasi jenis vs dokter (00 E.2): doctor_leave wajib berdokter; libur RS/nasional wajib doctor_id NULL; other bebas
DB::statement("ALTER TABLE schedule_exceptions
    ADD CONSTRAINT chk_exceptions_dates CHECK (end_date >= start_date),
    ADD CONSTRAINT chk_schedule_exceptions_scope CHECK (
        (type <> 'doctor_leave' OR doctor_id IS NOT NULL)
        AND (type NOT IN ('hospital_holiday','national_holiday') OR doctor_id IS NULL))");
```

`down()` cukup `Schema::dropIfExists('<tabel>')` karena CHECK dibuat pada migration yang sama dengan tabelnya (CHECK ikut terhapus). Bila suatu CHECK perlu dicabut tersendiri: `ALTER TABLE <tabel> DROP CONSTRAINT <nama>` (diterima MariaDB ≥ 10.2.1 dan MySQL ≥ 8.0.19).

Verifikasi setelah `php artisan migrate`:

```sql
-- semua CHECK terpasang: 14 berawalan chk_ + 4 JSON otomatis = 18 baris
SELECT TABLE_NAME, CONSTRAINT_NAME, CHECK_CLAUSE
FROM information_schema.CHECK_CONSTRAINTS
WHERE CONSTRAINT_SCHEMA = DATABASE()
ORDER BY TABLE_NAME, CONSTRAINT_NAME;

-- kolom generated
SELECT COLUMN_NAME, EXTRA, GENERATION_EXPRESSION
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appointments' AND COLUMN_NAME = 'active_flag';

-- jumlah FK per aturan hapus: CASCADE 6, RESTRICT 12, SET NULL 20 (total 38)
SELECT DELETE_RULE, COUNT(*) AS jumlah
FROM information_schema.REFERENTIAL_CONSTRAINTS
WHERE CONSTRAINT_SCHEMA = DATABASE()
GROUP BY DELETE_RULE;
```

### 11.4 Menerjemahkan pelanggaran constraint dan galat penguncian (errno 1062, 4025/3819, 1205, 1213)

00 F.5–F.6 mewajibkan pelanggaran constraint diterjemahkan menjadi `BookingException` dan galat penguncian yang masih terjadi setelah `attempts: 3` menjadi `SYSTEM_BUSY`. Penerjemah mencocokkan **kode galat driver** (`$e->errorInfo[1]`) dan **nama constraint/index** pada pesan, bukan hanya SQLSTATE `23000` ([03-arsitektur.md](03-arsitektur.md) bagian 5.2 butir 5). `DB::transaction(..., attempts: 3)` sendiri mengulang transaksi pada deadlock (1213) dan lock wait timeout (1205); hanya `QueryException` terakhir yang sampai ke penerjemah. Format pesan MariaDB 10.4.32 (diverifikasi): galat duplikat `Duplicate entry '1-1-1' for key 'appointments_patient_session_active_unique'` (nama diapit petik tunggal; MySQL ≥ 8.0.19 menambah awalan `tabel.`), galat CHECK ``CONSTRAINT `chk_sessions_quota` failed for `db`.`practice_sessions` `` (nama diapit backtick). Karena itu nama constraint pada bagian ini adalah bagian dari kontrak, dan pencocokan memakai `str_contains` pada nama saja. Kode galat CHECK: **4025** di MariaDB (SQLSTATE `23000`) dan **3819** di MySQL 8.4 (SQLSTATE `HY000`); duplikat UNIQUE: **1062** di keduanya.

| Kode galat | Nama pada pesan | Terjemahan |
|---|---|---|
| `1062` (duplicate entry) | `appointments_patient_session_active_unique` | `BookingException::DUPLICATE_BOOKING` |
| `1062` | `appointments_booking_code_unique` | Bukan galat pengguna: buat kode baru dan ulangi (maks 5 kali). |
| `1062` | `appointments_practice_session_id_queue_number_unique` | Tidak seharusnya terjadi (sesi terkunci); dilaporkan lewat `report()` dan diterjemahkan `BookingException::SYSTEM_BUSY` (aman dicoba ulang; tidak ada data tersimpan sebagian). |
| `4025` / `3819` (CHECK gagal) | `` `chk_sessions_quota` `` | `BookingException::QUOTA_FULL` |
| `1205` (lock wait timeout) / `1213` (deadlock) | — | Masih terjadi setelah `attempts: 3` → `BookingException::SYSTEM_BUSY` ("Sistem sedang sibuk, silakan coba lagi beberapa saat."); rollback penuh, tidak ada data tersimpan sebagian. |
| `4025` / `3819` / `1452` / `1906` lain | `chk_*` selain di atas, `*_foreign`, kolom generated | Galat sistem (bug atau data tidak konsisten): dicatat di log, respons 500 generik. Galat `1906` muncul bila aplikasi menulis `active_flag` dengan nilai selain `DEFAULT`. |

```php
private function translate(\Illuminate\Database\QueryException $e): BookingException
{
    $code = $e->errorInfo[1] ?? null;      // 1062 duplikat; 4025 (MariaDB) / 3819 (MySQL 8) CHECK gagal; 1205 lock wait; 1213 deadlock
    $text = $e->errorInfo[2] ?? '';
    return match (true) {
        $code === 1062 && str_contains($text, 'appointments_patient_session_active_unique') => BookingException::duplicateBooking(),
        $code === 1062 && str_contains($text, 'appointments_practice_session_id_queue_number_unique') => BookingException::systemBusy(),   // + report()
        in_array($code, [4025, 3819], true) && str_contains($text, '`chk_sessions_quota`') => BookingException::quotaFull(),   // backtick agar tidak cocok dengan chk_sessions_quota_range
        in_array($code, [1205, 1213], true) => BookingException::systemBusy(),
        default => throw $e,                // termasuk 1062 appointments_booking_code_unique: ditangani pengulang kode di BookingService
    };
}
```

### 11.5 `notifications`: `id` UUID

Migration `php artisan make:notifications-table` dipakai apa adanya (`$table->uuid('id')->primary()`). Pada MariaDB 10.4.32 Laravel 12 menghasilkan `CHAR(36)` (diverifikasi); pada MariaDB ≥ 10.7 Laravel dapat memakai tipe `UUID` native, yang tetap kompatibel dengan model `DatabaseNotification` (kunci string). Bila skema harus identik lintas versi server, tulis `$table->char('id', 36)->primary()`.

---

## 12. Invarian data

Invarian adalah kebenaran yang **harus selalu berlaku** setelah setiap transaksi commit. Penjagaan berlapis: (1) constraint database (UNIQUE, FK, CHECK, generated column) sebagai pengaman terakhir; (2) service dalam transaksi dengan penguncian urutan baku `patient_profiles` → `practice_sessions` → `appointments`; (3) Form Request; (4) Policy. Sebagian invarian lintas tabel tidak dapat dinyatakan sebagai constraint MariaDB 10.4 (CHECK tidak boleh merujuk tabel lain; tidak ada partial/exclusion constraint) sehingga dijaga service dan diuji.

| ID | Invarian | Dijaga oleh | Cara verifikasi |
|---|---|---|---|
| **I-1** | `practice_sessions.booked_count` = jumlah `appointments` sesi itu dengan status ∈ `QUOTA_CONSUMING` (semua kecuali `cancelled`). | Service (`BookingService`, `AppointmentStatusService`, `ScheduleChangeService`) di bawah kunci sesi; DB hanya CHECK rentang `0..quota` (`chk_sessions_quota`). | Kueri K-1; tes konkurensi + tes transisi. |
| **I-2** | `last_queue_number` ≥ `MAX(queue_number)` sesi itu (dalam praktik sama persis karena appointment tidak pernah dihapus), dan ≥ `booked_count`. Nomor tidak pernah dipakai ulang. | Service (`++last_queue_number` di bawah kunci sesi); DB: UNIQUE (`practice_session_id`, `queue_number`) dan `chk_sessions_queue`. | Kueri K-2. |
| **I-3** | `appointments.doctor_id`, `clinic_id`, `session_date` = nilai pada `practice_sessions` induknya. | Service (disalin dari sesi saat `book()`); `doctor_id`/`clinic_id`/`session_date` sesi tidak dapat diubah (I-32). DB: tidak ada (lintas tabel). | Kueri K-3. |
| **I-4** | `0 ≤ room_categories.beds_available ≤ bed_capacity`. | DB (`chk_room_categories_beds`) + Form Request. | Dijamin DB. |
| **I-5** | Setiap dokter punya ≥ 1 spesialisasi dan **tepat satu** `doctor_specialties.is_primary = 1`. | Service `Doctors\DoctorService::syncSpecialties()` (satu-satunya jalur tulis dokter, F2-R20; transaksi: nolkan semua, lalu set satu) + Form Request. Tidak dipasang sebagai generated-column UNIQUE karena `sync()` pivot dapat membuat dua baris utama sesaat sehingga constraint per baris gagal. | Kueri K-5. |
| **I-6** | Paling banyak satu `media` per (`model_type`, `model_id`, `collection`) pada koleksi tunggal (`photo`, `cover`, `image`, `logo`). | Service `MediaService::replace()` yang mengunci baris pemilik (`lockForUpdate`) saat mengganti; tidak ada partial unique index di MariaDB 10.4. | Kueri K-6; tes unggah paralel. |
| **I-7** | Tidak ada dua appointment dengan `queue_number` sama pada satu sesi. | DB UNIQUE `appointments_practice_session_id_queue_number_unique`. | Dijamin DB. |
| **I-8** | Seorang pasien memiliki paling banyak satu appointment **aktif** (`pending`/`confirmed`/`checked_in`) pada satu sesi. | DB UNIQUE `appointments_patient_session_active_unique` (via `active_flag`) + pemeriksaan service (pesan jelas). | Dijamin DB. |
| **I-9** | Appointment aktif seorang pasien pada tanggal yang sama tidak boleh beririsan jam (`start1 < end2 AND start2 < end1`). | Service di bawah kunci `patient_profiles` (F.5 butir 6). DB tidak dapat (irisan interval lintas baris). | Kueri K-9; tes. |
| **I-10** | Jumlah appointment aktif mendatang pasien < `booking.max_active_per_patient` (kanal online). | Service (F.5 butir 7). | Tes. |
| **I-11** | Sesi: `quota ≥ booked_count`; `end_time > start_time`; `quota` 1–200. Edit manual tidak boleh menurunkan kuota di bawah `booked_count`. | DB CHECK (`chk_sessions_quota`, `chk_sessions_time`, `chk_sessions_quota_range`) + Form Request. | Dijamin DB. |
| **I-12** | `practice_sessions.status = 'needs_review'` ⇔ `pending_change IS NOT NULL`; resolusi mengosongkan `pending_change`. | Service `ScheduleChangeService`. | Kueri K-12. |
| **I-13** | Rujukan dalam `pending_change` konsisten: `source` ∈ {`exception`, `template_time`, `template_removed`, `manual_time`, `manual_cancel`, `doctor_inactive`}; `exception` ⇒ `exception_id` = `schedule_exception_id` dan barisnya ada; `template_time`/`template_removed` ⇒ `schedule_id` = `doctor_schedule_id`; `doctor_inactive` ⇒ `doctor_id` = `practice_sessions.doctor_id`; `manual_time` ⇒ `new_start_time < new_end_time`. Satu sesi hanya satu `pending_change`; sumber jenis-batal tidak pernah ditimpa sumber jenis-jam (00 F.4). | Service (`ScheduleChangeService::setPendingChange`); JSON valid dijaga CHECK. | Tes `ScheduleChangeService`; kueri K-13. |
| **I-14** | Kolom waktu dan pembatalan konsisten dengan `status` (tabel di bagian 4.1). Koreksi `checked_in → confirmed` mengosongkan `checked_in_at`. | Service `AppointmentStatusService::transition()`. | Tes transisi. |
| **I-15** | `appointments.active_flag = 1` ⇔ `status ∈ AppointmentStatus::ACTIVE`, selain itu NULL. | DB generated column; tes yang melintasi semua *case* enum menangkap perbedaan dengan enum. | Kueri K-15. |
| **I-16** | Setiap appointment punya ≥ 1 riwayat; riwayat pertama `from_status = NULL`; `to_status` riwayat terakhir = `appointments.status`. Setiap transisi menulis riwayat dalam transaksi yang sama. | Service (`AppointmentStatusService`, `BookingService`). | Kueri K-16. |
| **I-17** | Akun `patient` memiliki tepat satu `patient_profiles` (dibuat satu transaksi dengan akun); akun `staff` tidak punya profil; satu akun ≤ satu profil. | DB UNIQUE `user_id` (≤ 1) + Service registrasi dan `PatientAccountLinker`. | Kueri K-17. |
| **I-18** | Minimal satu akun `super-admin` aktif selalu ada. | Service/`UserPolicy` (menolak menonaktifkan/mengubah peran yang terakhir). | Kueri K-18. |
| **I-19** | `patient_profiles.patient_number` tidak NULL setelah commit, berformat `P` + ≥ 6 digit dari `id`, unik. | DB UNIQUE + `PatientNumberGenerator` dalam transaksi insert. | Kueri K-19. |
| **I-20** | `booking_code` unik, berformat `UPH-XXXX-XXXX` (alfabet Crockford), tidak memuat data pasien. | DB UNIQUE + `BookingCodeGenerator`. | Tes format. |
| **I-21** | `bpjs_number_last4` / `payment_reference_last4` = 4 digit terakhir nilai terdekripsi; keduanya NULL bersama kolom terenkripsinya. | Mutator model. | Tes model. |
| **I-22** | Konsistensi pembayaran: `umum` ⇒ `insurance_partner_id` dan `payment_reference` NULL; `bpjs` ⇒ `payment_reference` 13 digit, `insurance_partner_id` NULL; `asuransi` ⇒ `insurance_partner_id` terisi. | Service `BookingService` (F.5 butir 8) + Form Request. Tidak dipasang CHECK agar nilai `PaymentType` baru tidak memerlukan `ALTER TABLE`. | Tes `INVALID_PAYMENT`. |
| **I-23** | `doctors.status = 'archived'` ⇔ `archived_at IS NOT NULL`. | Service `DoctorService`. | Kueri K-23. |
| **I-24** | `articles.status = 'published'` ⇒ `published_at IS NOT NULL`. | Form Request/Service. | Kueri K-24. |
| **I-25** | `website_settings`: kunci hanya yang baku; `value` sesuai `type`; `site.logo_media_id` kosong atau berisi `media.id` koleksi `logo` milik baris itu. | `SettingsService` + Form Request. | Tes pengaturan. |
| **I-26** | `audit_logs` dan `appointment_status_histories` tidak pernah diubah/dihapus; nilai teredaksi tidak pernah tersimpan. | Guard model (`updating`/`deleting` melempar), tanpa route ubah/hapus, `AuditLogger`. Opsi produksi: akun aplikasi hanya diberi `INSERT, SELECT` pada kedua tabel **[KONFIRMASI]**. | Tes guard + tes redaksi. |
| **I-27** | Templat aktif seorang dokter pada hari yang sama tidak beririsan jam pada rentang berlaku yang beririsan. | Validasi aplikasi dalam transaksi yang mengunci baris dokter (tidak ada exclusion constraint di MariaDB 10.4). | Tes. |
| **I-28** | Sesi non-batal seorang dokter pada tanggal yang sama tidak beririsan jam. | Validasi aplikasi pada edit/tambah sesi **[ASUMSI]**. | Tes. |
| **I-29** | Setiap akun staf memiliki tepat satu peran; akun pasien tidak punya peran; `model_has_permissions` kosong. | Service `StaffController` + seeder. | Kueri K-29. |
| **I-30** | Persetujuan privasi selalu tercatat: `patient_profiles.consent_at`/`consent_version` dan `contact_messages.consent_at` tidak NULL. | DB `NOT NULL` + Form Request. | Dijamin DB. |
| **I-31** | `clinics.code` tidak berubah setelah ada appointment pada poliklinik itu. | Form Request/`ClinicPolicy` **[ASUMSI]**. | Tes. |
| **I-32** | `practice_sessions.doctor_id`, `clinic_id`, `session_date` tidak berubah setelah sesi dibuat. | Formulir edit sesi tidak memuatnya + guard model `updating` **[ASUMSI]**. | Tes. |
| **I-33** | Pasangan `media.model_type`/`model_id` terisi bersama atau kosong bersama. | DB CHECK `chk_media_owner`. | Dijamin DB. |
| **I-34** | Kombinasi jenis pengecualian dan dokter: `doctor_leave` ⇒ `doctor_id` terisi; `hospital_holiday`/`national_holiday` ⇒ `doctor_id` NULL; `other` bebas (00 E.2). | DB CHECK `chk_schedule_exceptions_scope` + `ScheduleExceptionRequest`. | Dijamin DB; kueri K-34. |
| **I-35** | Dokter non-`active` (`inactive`/`archived`) tidak memiliki sesi mendatang yang belum berakhir berstatus `open`/`closed`: sesi tanpa appointment aktif `cancelled`, sesi ber-appointment aktif `needs_review` (sumber `doctor_inactive` atau sumber lain yang lebih dahulu mengalahkannya); dokter `archived` tidak memiliki appointment aktif pada sesi yang belum berakhir (00 F.4, D-01, D-02). | Service `ScheduleChangeService::applyDoctorInactive()`, `setSessionStatus()` (menolak membuka sesi dokter non-aktif) + `UpdateDoctorStatusRequest` (menolak arsip). | Kueri K-35a, K-35b. |
| **I-36** | Untuk profil berakun: `patient_profiles.email` = `users.email` dan `patient_profiles.is_active` = `users.is_active` (00 E.1). | Service pasien (`PatientProfileController`/`PatientRegistrationService`/`PatientAccountLinker`) dalam satu transaksi. | Kueri K-36 (13 §11 I16, I17). |

Alternatif yang **tidak** dipilih: *trigger* MariaDB untuk I-3/I-9 dan *composite FK* (`practice_sessions(id, doctor_id, clinic_id, session_date)` ← `appointments`) untuk I-3. Keduanya menyembunyikan logika di database, sulit diuji lewat PHPUnit, dan menyulitkan migrasi ke MySQL 8.4; aturan dipusatkan di service sesuai 00 bagian F.

Catatan isolasi transaksi: InnoDB memakai `REPEATABLE READ`. Snapshot dibuat pada pembacaan biasa pertama dalam transaksi, sehingga **pembacaan pertama dalam transaksi booking harus berupa `lockForUpdate()`** (urutan di F.6), dan pemeriksaan duplikasi/irisan dilakukan setelah kunci diperoleh. Jangan menjalankan `SELECT` biasa sebelum kunci.

### 12.1 Kueri verifikasi invarian (untuk tes dan audit data)

Semua kueri harus mengembalikan **nol baris** (kecuali dinyatakan lain).

```sql
-- K-1  booked_count = jumlah appointment non-batal
SELECT ps.id, ps.booked_count, COUNT(a.id) AS aktual
FROM practice_sessions ps
LEFT JOIN appointments a ON a.practice_session_id = ps.id AND a.status <> 'cancelled'
GROUP BY ps.id, ps.booked_count
HAVING ps.booked_count <> COUNT(a.id);

-- K-2  last_queue_number >= MAX(queue_number)
SELECT ps.id, ps.last_queue_number, MAX(a.queue_number) AS maks
FROM practice_sessions ps
JOIN appointments a ON a.practice_session_id = ps.id
GROUP BY ps.id, ps.last_queue_number
HAVING ps.last_queue_number < MAX(a.queue_number);

-- K-3  salinan doctor_id/clinic_id/session_date sama dengan sesi
SELECT a.id
FROM appointments a
JOIN practice_sessions ps ON ps.id = a.practice_session_id
WHERE a.doctor_id <> ps.doctor_id OR a.clinic_id <> ps.clinic_id OR a.session_date <> ps.session_date;

-- K-5  tepat satu spesialisasi utama per dokter
SELECT d.id
FROM doctors d
LEFT JOIN doctor_specialties ds ON ds.doctor_id = d.id AND ds.is_primary = 1
GROUP BY d.id
HAVING COUNT(ds.doctor_id) <> 1;

-- K-6  media koleksi tunggal ganda
SELECT model_type, model_id, collection, COUNT(*) AS jumlah
FROM media
WHERE model_id IS NOT NULL AND collection IN ('photo','cover','image','logo')
GROUP BY model_type, model_id, collection
HAVING COUNT(*) > 1;

-- K-9  irisan jam appointment aktif pasien yang sama pada tanggal yang sama
SELECT a1.id AS a1, a2.id AS a2
FROM appointments a1
JOIN appointments a2 ON a2.patient_profile_id = a1.patient_profile_id
                    AND a2.session_date = a1.session_date AND a2.id > a1.id
JOIN practice_sessions s1 ON s1.id = a1.practice_session_id
JOIN practice_sessions s2 ON s2.id = a2.practice_session_id
WHERE a1.active_flag = 1 AND a2.active_flag = 1
  AND s1.start_time < s2.end_time AND s2.start_time < s1.end_time;

-- K-12  needs_review <=> pending_change terisi
SELECT id FROM practice_sessions
WHERE (status = 'needs_review') <> (pending_change IS NOT NULL);

-- K-13  rujukan pending_change konsisten (sumber dikenal; exception_id dan doctor_id cocok)
SELECT id FROM practice_sessions
WHERE status = 'needs_review'
  AND (JSON_VALUE(pending_change, '$.source') NOT IN ('exception','template_time','template_removed','manual_time','manual_cancel','doctor_inactive')
    OR (JSON_VALUE(pending_change, '$.source') = 'exception'
        AND CAST(JSON_VALUE(pending_change, '$.exception_id') AS UNSIGNED) <> schedule_exception_id)
    OR (JSON_VALUE(pending_change, '$.source') = 'doctor_inactive'
        AND CAST(JSON_VALUE(pending_change, '$.doctor_id') AS UNSIGNED) <> doctor_id));

-- K-15  active_flag selaras dengan status aktif
SELECT id FROM appointments
WHERE (status IN ('pending','confirmed','checked_in')) <> (active_flag IS NOT NULL);

-- K-16  riwayat terakhir = status saat ini; tiap appointment punya riwayat
SELECT a.id
FROM appointments a
WHERE NOT EXISTS (SELECT 1 FROM appointment_status_histories h WHERE h.appointment_id = a.id)
   OR a.status <> (SELECT h2.to_status FROM appointment_status_histories h2
                   WHERE h2.appointment_id = a.id ORDER BY h2.id DESC LIMIT 1);

-- K-17  setiap akun pasien punya profil
SELECT u.id FROM users u
LEFT JOIN patient_profiles p ON p.user_id = u.id
WHERE u.account_type = 'patient' AND p.id IS NULL;

-- K-18  harus >= 1 baris (super-admin aktif)
SELECT COUNT(*) AS super_admin_aktif
FROM users u
JOIN model_has_roles mr ON mr.model_id = u.id AND mr.model_type = 'App\\Models\\User'
JOIN roles r ON r.id = mr.role_id AND r.name = 'super-admin'
WHERE u.is_active = 1;

-- K-19  nomor pasien kosong setelah commit
SELECT id FROM patient_profiles WHERE patient_number IS NULL;

-- K-23  arsip dokter selaras archived_at
SELECT id FROM doctors WHERE (status = 'archived') <> (archived_at IS NOT NULL);

-- K-24  artikel terbit tanpa published_at
SELECT id FROM articles WHERE status = 'published' AND published_at IS NULL;

-- K-34  kombinasi jenis pengecualian vs dokter (00 E.2)
SELECT id FROM schedule_exceptions
WHERE (type = 'doctor_leave' AND doctor_id IS NULL)
   OR (type IN ('hospital_holiday','national_holiday') AND doctor_id IS NOT NULL);

-- K-35a dokter non-aktif tidak punya sesi mendatang open/closed (00 F.4)
SELECT ps.id FROM practice_sessions ps
JOIN doctors d ON d.id = ps.doctor_id
WHERE d.status <> 'active' AND ps.status IN ('open','closed')
  AND TIMESTAMP(ps.session_date, ps.end_time) > NOW();

-- K-35b dokter arsip tidak punya appointment aktif pada sesi yang belum berakhir
SELECT a.id FROM appointments a
JOIN doctors d ON d.id = a.doctor_id
JOIN practice_sessions ps ON ps.id = a.practice_session_id
WHERE d.status = 'archived' AND a.active_flag = 1
  AND TIMESTAMP(ps.session_date, ps.end_time) > NOW();

-- K-36  profil berakun selaras dengan akun (email, is_active; 00 E.1)
SELECT p.id FROM patient_profiles p
JOIN users u ON u.id = p.user_id
WHERE p.email IS NULL OR p.email <> u.email OR p.is_active <> u.is_active;

-- K-29  staf tanpa peran / pasien berperan / izin langsung
SELECT u.id FROM users u
LEFT JOIN model_has_roles mr ON mr.model_id = u.id AND mr.model_type = 'App\\Models\\User'
WHERE (u.account_type = 'staff'   AND mr.role_id IS NULL)
   OR (u.account_type = 'patient' AND mr.role_id IS NOT NULL);
SELECT COUNT(*) AS izin_langsung_harus_nol FROM model_has_permissions;
```

---

## 13. Data sensitif dan retensi

### 13.1 Klasifikasi kolom

| Kolom | Perlakuan | Tampil di UI | Audit log | Ekspor CSV | Catatan |
|---|---|---|---|---|---|
| `patient_profiles.bpjs_number` | **Terenkripsi** (`encrypted`, `TEXT`), `$hidden` | Hanya `bpjs_number_last4` tersamar; **tidak ada** aksi "tampilkan nomor lengkap" pada prototipe (01 A-13) | **Tidak pernah** (`[disamarkan]`) | Tidak | Tidak dapat dicari/diurutkan/di-UNIQUE. |
| `appointments.payment_reference` | **Terenkripsi** (`encrypted`, `TEXT`), `$hidden` | Hanya `payment_reference_last4` | **Tidak pernah** | Tidak | Nomor BPJS atau polis. |
| `*_last4` (`bpjs_number_last4`, `payment_reference_last4`) | **Tersamar**: hanya 4 digit terakhir, `CHAR(4)` | Boleh (bukti pendaftaran, layar staf) | Boleh | Tidak | Format tampil `•••• •••• 1234`. |
| `appointments.complaint` | Teks biasa; **data kesehatan** | Pemilik dan staf `appointments.view` | **Tidak** (diredaksi **[ASUMSI]**) | Tidak | Tidak ada di notifikasi, QR, maupun bukti pendaftaran. |
| `patient_profiles.full_name`, `gender`, `phone`, `email` | Teks biasa; data pribadi | Pemilik dan staf `patients.view` | Boleh | `full_name` saja | Admin Konten tidak punya akses. |
| `patient_profiles.birth_date`, `address` | Teks biasa; data pribadi | Pemilik dan staf `patients.view` | **Tidak** (diredaksi **[ASUMSI]**) | Tidak | |
| `users.email`, `users.phone` | Teks biasa | Pemilik; Super Admin (staf) | Boleh | Tidak | |
| `users.password`, `users.remember_token` | **Hash** / token | Tidak pernah | **Tidak pernah** | Tidak | `$hidden`. |
| `password_reset_tokens.token` | **Hash** | Tidak pernah (tautan hanya lewat mail) | **Tidak pernah** | Tidak | |
| `contact_messages.name/email/phone/message` | Teks biasa; data pribadi | Staf `messages.manage` | Tidak perlu | Tidak | |
| `contact_messages.ip_hash` | **Hash** SHA-256 (IP + `APP_KEY`) | Tidak | Tidak | Tidak | IP mentah tidak disimpan. |
| `audit_logs.ip_address`, `user_agent` | Teks biasa; IP mentah adalah data pribadi | Hanya `audit.view` | — | Tidak | Satu-satunya tempat IP mentah disimpan. |
| `sessions.payload`, `ip_address`, `user_agent` | Data internal framework | Tidak | Tidak | Tidak | |
| `notifications.data` | JSON; hanya kode booking, label status, pesan | Pemilik | Tidak | Tidak | Dilarang memuat nomor BPJS/polis, keluhan. |
| `failed_jobs.payload`, `exception` | Data internal; dapat memuat salinan data job | Tidak | Tidak | Tidak | Kosong pada prototipe (`QUEUE_CONNECTION=sync`). |
| `media.original_name` | Disanitasi; hanya metadata | Tidak (tidak pernah dipakai di URL/path) | Boleh | Tidak | |

Daftar kolom yang **tidak boleh masuk `audit_logs`** (diredaksi `AuditLogger`): `password`, `remember_token`, `token`, `bpjs_number`, `payment_reference` (00 E.6), ditambah **[ASUMSI]** `complaint`, `address`, `birth_date`, dan setiap atribut `encrypted`/`$hidden`. Ekspor CSV mengikuti 00 bagian L: tidak memuat nomor BPJS/polis, alamat, telepon, tanggal lahir, keluhan.

### 13.2 Enkripsi kolom

- Cast `encrypted` memakai `Crypt::encryptString` dengan `APP_KEY` (AES-256-CBC + HMAC, `config/app.php` `cipher`). Hasil (base64 JSON) jauh lebih panjang dari 13 digit, sehingga kolomnya `TEXT`.
- Enkripsi bersifat acak (IV berbeda tiap tulis): kolom tidak dapat dipakai pada `WHERE`, `ORDER BY`, UNIQUE, atau index. Pencarian pasien di loket memakai nama, nomor pasien, telepon, dan tanggal lahir (F.9), bukan nomor BPJS.
- Rancang layar agar **membaca `*_last4`** untuk tampilan. Dekripsi hanya terjadi di sisi server, misalnya saat wizard menyalin nomor BPJS yang tersimpan di profil ke `appointments.payment_reference` ("gunakan nomor tersimpan"), atau kelak untuk integrasi BPJS; nomor penuh tidak dikirim ke peramban.

### 13.3 Retensi

Prototipe **tidak menghapus data otomatis** (tidak ada scheduler; 00 B.2). Satu-satunya pembersihan otomatis adalah bawaan framework (session, cache, kunci kedaluwarsa) dan perintah manual `media:prune`. **[KONFIRMASI]** Kebijakan retensi produksi harus ditetapkan pemilik bersama bagian hukum/rekam medis rumah sakit (termasuk pertimbangan UU No. 27 Tahun 2022 tentang Pelindungan Data Pribadi untuk data kesehatan); angka berikut hanya **usulan awal** **[ASUMSI]**:

| Data | Prototipe | Usulan produksi |
|---|---|---|
| `appointments`, `appointment_status_histories`, `patient_profiles` | Disimpan selamanya | Mengikuti kebijakan RS; jangan dihapus otomatis. Penghapusan pasien memerlukan prosedur **anonimisasi** (nama diganti penanda, `phone`/`address` diganti `-`, `email`/`bpjs_*` dikosongkan, `is_active = 0`, akun dinonaktifkan) karena FK `RESTRICT`; belum dibangun. |
| `contact_messages` | Disimpan selamanya | Hapus/anonimkan 12 bulan setelah `archived`. |
| `audit_logs` | Disimpan selamanya | 24 bulan lalu arsipkan ke berkas dump. |
| `notifications` | Disimpan selamanya | Hapus yang sudah dibaca > 90 hari. |
| `sessions`, `cache`, `cache_locks` | Garbage collection bawaan | Sama. |
| `password_reset_tokens` | Dihapus setelah dipakai; kedaluwarsa tidak dibersihkan otomatis | `auth:clear-resets` berkala. |
| `failed_jobs` | Manual | Hapus > 30 hari. |
| `media` | Dibersihkan `media:prune` (yatim) | Sama. |

### 13.4 Dampak kehilangan atau penggantian `APP_KEY`

| Dampak | Penjelasan |
|---|---|
| Kolom terenkripsi **tidak dapat dibaca** | `patient_profiles.bpjs_number` dan `appointments.payment_reference` tidak dapat didekripsi (`DecryptException`) dan **tidak dapat dipulihkan**. Kolom `*_last4` tetap terbaca, sehingga layar yang hanya memakai `last4` (bukti pendaftaran, daftar antrean) tetap berfungsi. Pemulihan operasional: kosongkan kedua kolom terenkripsi (pasien mengisi ulang), lalu aplikasi berjalan normal. |
| Semua session dan cookie "ingat saya" tidak valid | Cookie terenkripsi dengan `APP_KEY`; semua pengguna harus login ulang. |
| Hash password **tidak terpengaruh** | bcrypt tidak memakai `APP_KEY`; login tetap bisa. |
| `contact_messages.ip_hash` lama tidak cocok dengan hash baru | Tidak ada fitur yang membutuhkan pencocokan lintas waktu; dampak diabaikan. |

Mitigasi: simpan `APP_KEY` **terpisah dan aman** dari dump database (00 bagian K); backup tanpa `APP_KEY` tidak lengkap. Untuk rotasi terencana gunakan `APP_PREVIOUS_KEYS` (Laravel 11+): kunci lama dimasukkan ke `APP_PREVIOUS_KEYS`, kunci baru ke `APP_KEY`; nilai lama tetap terbaca dan dienkripsi ulang dengan kunci baru saat disimpan; setelah seluruh baris diperbarui (skrip sekali jalan) kunci lama dibuang **[ASUMSI]**.

---

## 14. Catatan kompatibilitas MariaDB 10.4

Skema dirancang untuk **MariaDB 10.4.32** (bawaan XAMPP 8.2.12, koneksi `DB_CONNECTION=mariadb`) dan tetap portabel ke MariaDB ≥ 10.11 LTS atau MySQL 8.4 LTS **[KONFIRMASI]** untuk produksi (10.4 sudah EOL dari vendor).

| Topik | Kondisi di MariaDB 10.4 | Keputusan skema |
|---|---|---|
| **JSON** | Tidak ada tipe JSON native; `JSON` hanyalah alias `LONGTEXT` (collation `utf8mb4_bin`) dan sejak 10.4.3 otomatis diberi `CHECK (json_valid(kolom))`. | `$table->json()->nullable()` + cast `array` (sesuai 03 bagian 5.2 butir 2). Diverifikasi: menghasilkan `longtext … CHECK (json_valid(...))` dengan nama CHECK = nama kolom. Tidak memakai operator path (`->`, `whereJson*`) atau index fungsional. |
| **Generated column** | `GENERATED ALWAYS AS (...) STORED` didukung (≥ 10.2.1); boleh dijadikan bagian UNIQUE. Ekspresi harus deterministik, tanpa subquery, tanpa merujuk kolom `AUTO_INCREMENT`. | `appointments.active_flag` (`storedAs`). `patient_number` **tidak** dibuat generated karena merujuk `id` (AUTO_INCREMENT). |
| **CHECK** | Didukung dan **ditegakkan** sejak 10.2.1; tidak boleh merujuk tabel lain; galat `4025`/SQLSTATE 23000. Laravel tidak menyediakan API schema. | `DB::statement('ALTER TABLE … ADD CONSTRAINT chk_… CHECK (…)')` (bagian 11). Tidak ada CHECK atas nilai enum. |
| **Partial / filtered unique index** | Tidak ada. | Generated column + UNIQUE komposit (NULL tidak dianggap sama): `appointments_patient_session_active_unique`. Untuk koleksi media tunggal dan satu `is_primary` dipakai kunci + service. |
| **Exclusion / overlap constraint** | Tidak ada (`WITHOUT OVERLAPS` baru di 10.5.3+). | Larangan irisan jam templat/sesi/appointment dijaga service dengan penguncian. |
| **Tipe UUID** | Tipe `UUID` native baru ada di MariaDB ≥ 10.7 (10.4 hanya punya fungsi `UUID()`). | `notifications.id`: `uuid('id')` bawaan menghasilkan `CHAR(36)` di 10.4 (diverifikasi; bagian 11.5). `failed_jobs.uuid` (`string`) tidak terpengaruh. |
| **Panjang index utf8mb4** | `ROW_FORMAT=DYNAMIC` default sejak 10.2.2; batas kunci 3072 byte. `VARCHAR(255)` = 1020 byte dan PK komposit Spatie ≈ 1036 byte masih aman. Batas **nama** identifier 64 karakter. | Tidak perlu `Schema::defaultStringLength(191)`. Nama unique komposit `appointments_patient_session_active_unique` dibuat eksplisit. |
| **Collation** | Default server MariaDB 10.4 di Windows adalah `latin1` / `latin1_swedish_ci` (diverifikasi), bukan utf8mb4. Collation MySQL 8 `utf8mb4_0900_ai_ci` **tidak ada** di MariaDB. | `DB_COLLATION=utf8mb4_unicode_ci`; `CREATE DATABASE … CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci`. Perbandingan *case-insensitive* dan mengabaikan spasi akhir (PAD SPACE). |
| **TIMESTAMP** | `explicit_defaults_for_timestamp` OFF (default 10.4): `TIMESTAMP NOT NULL` pertama tanpa default mendapat `ON UPDATE CURRENT_TIMESTAMP` implisit. Rentang maksimal 2038-01-19. Dikonversi mengikuti zona sesi koneksi. | `timestamps()`/`softDeletes()` selalu nullable; kolom `created_at` log memakai `useCurrent()` eksplisit; kolom waktu bisnis `DATETIME`. Koneksi database memakai `'timezone' => '+07:00'` (03 bagian 5.1; 01 A-33) agar `CURRENT_TIMESTAMP` dan konversi `TIMESTAMP` selaras dengan `Asia/Jakarta`. Produksi jangka panjang: MariaDB ≥ 11.5 memperluas rentang `TIMESTAMP`. |
| **Penguncian** | `SELECT … FOR UPDATE` (Laravel `lockForUpdate()`) didukung; `SKIP LOCKED` baru di 10.6; `NOWAIT` ada sejak 10.3. Isolasi default `REPEATABLE READ`. `innodb_lock_wait_timeout` default 50 detik. | Hanya `lockForUpdate()` biasa dengan urutan kunci baku (03 bagian 5.3); `DB::transaction(..., attempts: 3)` mengulang deadlock (1213) dan lock-wait (1205); bila masih terjadi, `BookingService` menerjemahkannya menjadi `SYSTEM_BUSY` (bagian 11.4). Menurunkan `innodb_lock_wait_timeout` ke 10 detik di `my.ini` direkomendasikan (01 A-40). |
| **`insertOrIgnore`** | Menjadi `INSERT IGNORE`: selain duplikat unik, galat FK (1452), data terpotong/di luar rentang (1264), dan NOT NULL tanpa default (1364) diturunkan menjadi *warning* dan baris dilewati atau nilainya diubah diam-diam. Kegagalan CHECK (4025) **tetap galat** pada 10.4.32 (diverifikasi). | Dipakai `PracticeSessionGenerator` sesuai 00 F.2; data divalidasi di PHP sebelum insert dan jumlah baris tersisip dibandingkan dengan jumlah yang diharapkan. |
| **Angka unsigned** | `booked_count - 1` pada `SMALLINT UNSIGNED` bernilai 0 memicu galat *out of range* pada mode strict. | Kurangi hanya setelah memeriksa status (hanya transisi ke `cancelled`); galat ini menjadi pengaman tambahan. |
| **Mode SQL** | Laravel `strict => true` mengaktifkan `ONLY_FULL_GROUP_BY` dan `STRICT_TRANS_TABLES`. | Kueri dashboard memakai `GROUP BY` lengkap (lihat kueri K-1, K-2). |
| **DDL tidak transaksional** | `ALTER`/`CREATE` meng-*commit* otomatis. | Kegagalan migration di tengah jalan pada dev diselesaikan `migrate:fresh`; produksi: backup sebelum migrate. |
| **Mesin penyimpanan** | Harus InnoDB (FK, transaksi, kunci baris). MyISAM mengabaikan FK. | `config/database.php` `engine => 'InnoDB'`; XAMPP default InnoDB. |
| **Nama tabel di Windows** | `lower_case_table_names=1` pada Windows. | Semua nama tabel/kolom huruf kecil agar backup/restore ke Linux aman. |
| **Pengujian** | SQLite tidak mendukung `ALTER TABLE … ADD CONSTRAINT … CHECK` dan tidak setara untuk generated column maupun `FOR UPDATE` (01 A-29). | `phpunit.xml` memakai database MariaDB khusus `unpam_hospital_test`; tes konkurensi memakai koneksi terpisah (bukan `RefreshDatabase` yang membungkus satu transaksi). |
| **`max_allowed_packet`** | Default server MariaDB 10.4 = 16M (diverifikasi); `my.ini` XAMPP dapat menetapkan nilai lain. | Periksa nilainya di `my.ini` dan naikkan bila artikel `LONGTEXT` sangat panjang atau dump besar. |
| **Pindah ke MySQL 8.4** | CHECK, generated column, UNIQUE komposit, `DROP CONSTRAINT` didukung; collation harus diganti jika memakai `0900`. | Skema ini tidak memakai sintaks khusus MariaDB selain `DB::statement` standar. |

### 14.1 Hasil verifikasi skema pada MariaDB 10.4.32

Pada sesi penulisan blueprint (2026-10-02) rancangan skema di bagian 2 sampai 11 dijalankan nyata pada **MariaDB 10.4.32 (winx64, versi yang sama dengan XAMPP 8.2.12)** memakai skeleton **Laravel 12.x** (koneksi `mariadb`) dan paket `spatie/laravel-permission` 8.x. Hasil yang relevan bagi implementasi:

| Hal yang diuji | Hasil |
|---|---|
| Seluruh skema (migration bawaan, Spatie, dan seluruh tabel domain bagian 2–7 dijalankan sebagai satu migration gabungan yang setara dengan migration 4–29) | Berhasil tanpa galat; 38 tabel (37 wajib + `migrations`). |
| FOREIGN KEY | 38 FK: **12 `RESTRICT`**, **6 `CASCADE`**, **20 `SET NULL`**; cocok dengan 08 bagian 4. |
| CHECK constraint | 13 `chk_*` + 4 CHECK JSON otomatis = 17 baris di `information_schema.CHECK_CONSTRAINTS` pada sesi verifikasi. Skema final memiliki 14 `chk_*` (+ `chk_schedule_exceptions_scope`, ditambahkan sesudah sesi verifikasi dan **belum diuji**; wajib diuji pada fase 3: pelanggaran memberi errno 4025) + 4 JSON = 18 baris. |
| Generated column `active_flag` | `->nullable()->storedAs(...)` diterima; berada di UNIQUE komposit; menulis nilai eksplisit memberi galat 1906. |
| UNIQUE `appointments_patient_session_active_unique` | Appointment aktif ganda ditolak (1062); setelah `cancelled`, appointment aktif baru diterima; kode booking dibandingkan *case-insensitive*. |
| Nama index default 70 karakter | Ditolak MariaDB (galat 1059) sehingga nama eksplisit wajib. |
| `chk_sessions_quota`, `chk_sessions_queue`, dst. | Pelanggaran memberi galat 4025 (SQLSTATE 23000) dengan nama constraint pada pesan. |
| `booked_count - 1` pada nilai 0 | Galat 1690 *out of range* (kolom `UNSIGNED`). |
| `INSERT IGNORE` | FK, data terpotong, NOT NULL → warning; CHECK → tetap galat. |
| `ALTER TABLE … DROP CONSTRAINT` | Diterima. |
| `uuid()` dan `json()` Laravel 12 | `CHAR(36)`; `LONGTEXT` + CHECK `json_valid`. |
| Seluruh kueri verifikasi K-1 sampai K-29 | Berjalan tanpa galat pada mode `ONLY_FULL_GROUP_BY` + `STRICT_TRANS_TABLES` dan mendeteksi data yang sengaja dirusak. Kueri K-13 (versi enam sumber, memakai `JSON_VALUE`, tersedia sejak MariaDB 10.2.3) dan K-34, K-35a, K-35b, K-36 ditambahkan sesudah sesi verifikasi dan harus dijalankan pada fase 3 dan 7. |
| Kolom, tipe, nullability, dan default pada seluruh tabel bagian 2–8; 63 definisi index; 38 FK beserta aksi hapus | Dibandingkan otomatis dengan `information_schema`: tidak ada selisih. |

Ini bukan pengganti pengujian di tahap 14: seluruh langkah harus diulang pada instalasi XAMPP pemilik dengan migration proyek yang sebenarnya.

---

## 15. Ukuran dan volume perkiraan

Angka berikut adalah **perkiraan** **[ASUMSI]** untuk lingkungan prototipe setelah `DemoSeeder` (rencana isi ada di [13-data-contoh.md](13-data-contoh.md); bila berbeda, 13 yang berlaku).

### 15.1 Baris per tabel setelah seeder demo

Angka mengikuti rencana seeder di [13-data-contoh.md](13-data-contoh.md) bagian 2.2 (bila 13 berubah, 13 yang berlaku); angka yang tidak ditetapkan 13 adalah perkiraan **[ASUMSI]**.

| Tabel | Perkiraan baris | Dasar |
|---|---:|---|
| `users` | 10 | 3 staf demo + 7 akun pasien (13 §2.2) |
| `patient_profiles` | 13 | 7 berakun + 6 walk-in (13 §2.2) |
| `roles` / `permissions` | 3 / 19 | 00 bagian G |
| `role_has_permissions` | 39 | super-admin 19 + content-admin 9 + registration-officer 11 |
| `model_has_roles` | 3 | satu peran per staf demo |
| `model_has_permissions` | 0 | tidak dipakai |
| `password_reset_tokens` | 0 | |
| `sessions` | 1–10 | termasuk sesi tamu |
| `notifications` | 10–20 | untuk appointment milik pasien berakun |
| `cache` / `cache_locks` | 20–100 / 0 | transien |
| `jobs` / `job_batches` / `failed_jobs` | 0 | `QUEUE_CONNECTION=sync` |
| `migrations` | 29 | jumlah berkas migration (bagian 10) |
| `specialties` | 13 | daftar di kebutuhan §6 |
| `clinics` | 17 | 13 poliklinik + 4 layanan penunjang |
| `doctors` | 18 | 16 aktif, 1 nonaktif, 1 arsip |
| `doctor_specialties` | 20 (18 primer + 2 sekunder, 13 §6) | sebagian dokter dua spesialisasi |
| `doctor_schedules` | 52 | 51 aktif + 1 nonaktif |
| `schedule_exceptions` | 3 | 2 dari seeder jadwal + 1 dari seeder appointment |
| `practice_sessions` | ±230–260 | hari ini s.d. hari ini + 30 saja (F3-R7: generator tidak membuat sesi lampau); 51 templat aktif × tanggalnya (230 pada seeding hari Senin), + 1 sesi tambahan manual, + 3 sesi cadangan bila hari seeding Minggu; sesi lampau untuk appointment `completed`/`no_show` dibuat `AppointmentSeeder` (fase 7a, D-178) lewat generator di bawah jam semu: 14 hari lampau menambah 102 sesi (333 pada seeding Senin 07.00) |
| `insurance_partners` | 4 | fiktif, berlabel "Contoh" |
| `appointments` | 29 | contoh semua status |
| `appointment_status_histories` | 64 | tabel acuan 13 §10.3/§11.2 (29 appointment, ±2,2 baris per appointment) |
| `room_categories` | 4 | `bpjs-kelas-1`, `bpjs-kelas-2`, `bpjs-kelas-3`, `vip` |
| `facilities` | 10 | |
| `pages` | 15 | kunci baku |
| `article_categories` / `articles` | 4 / 8 | |
| `banners` / `galleries` / `faqs` | 3 / 3 / 12 | |
| `contact_messages` | 5 | |
| `media` | ±80–100 | satu gambar placeholder (dibuat GD, disimpan lewat `MediaService`) per dokter, poliklinik, kamar, fasilitas, banner, artikel, logo mitra, dan beberapa foto album |
| `website_settings` | 19 | kunci di 00 bagian E.7 |
| `audit_logs` | ±80 | aksi pembuatan/transisi appointment dan perubahan demo |

Total ±950 baris; ukuran basis data setelah seeder ±5–10 MB, hampir seluruhnya overhead halaman InnoDB (38 tabel × beberapa index × 16 KB). Berkas gambar contoh berada di `storage/app/public`, bukan di database.

### 15.2 Pertumbuhan

Skala prototipe mengikuti 01 A-30: ≤ 100 dokter dan ≤ **50.000 appointment**.

| Tabel | Pola pertumbuhan | Perkiraan pada skala 50.000 appointment |
|---|---|---|
| `appointments` | Satu baris per pendaftaran | ±1 KB/baris termasuk 11 index → ±50 MB. |
| `appointment_status_histories` | ±3 baris per appointment | ±150.000 baris × ±0,2 KB → ±30 MB. |
| `audit_logs` | ±3–4 baris per appointment ditambah perubahan admin | Tabel terbesar: ±2,5 KB per appointment (JSON) → ±125 MB; kandidat arsip pertama (bagian 13.3). |
| `practice_sessions` | Satu baris per templat per hari praktik | 50 templat × ±52 minggu ≈ 2.600 baris/tahun (< 2 MB). Tidak dibersihkan. |
| `sessions` | Satu baris per pengunjung (termasuk tamu dan bot) selama `SESSION_LIFETIME` | Dibatasi garbage collection; pada beban publik tinggi dapat melebihi 10.000 baris sementara. |
| `media` | Per unggahan | Beberapa ratus baris; yang membesar adalah berkas di `storage/app/public` (dibackup bersama dump, 00 bagian K). |
| `notifications` | 1 per perubahan status pasien berakun | Sebanding dengan appointment milik pasien berakun. |

Total basis data pada skala 50.000 appointment ±200–250 MB. Sebagai pembanding di luar skala prototipe, rumah sakit menengah dengan ±200 pendaftaran per hari menghasilkan ±73.000 appointment per tahun (±250 MB/tahun). Pada skala prototipe tidak diperlukan partisi, arsip, atau index tambahan di luar bagian ini; bila `appointments` melebihi ±1 juta baris, tinjau ulang index komposit (`session_date`, `status`) dan pertimbangkan arsip per tahun untuk `audit_logs`.

---

## Asumsi di bagian ini

1. **[ASUMSI]** Kolom waktu bisnis memakai `DATETIME`; hanya `created_at`/`updated_at`/`deleted_at`/`email_verified_at`/`read_at` memakai `TIMESTAMP` (bawaan Laravel). Koneksi database memakai `'timezone' => '+07:00'` (03 bagian 5.1; 01 A-33).
2. Kolom JSON dibuat dengan `$table->json()` (sesuai 03 bagian 5.2 butir 2): MariaDB 10.4.32 menyimpannya sebagai `LONGTEXT` + CHECK `json_valid` otomatis (diverifikasi), dengan cast `array`.
3. `notifications.id` memakai `uuid('id')` bawaan, yang menjadi `CHAR(36)` pada MariaDB 10.4 (diverifikasi).
4. **[ASUMSI]** Aturan nullability untuk kolom yang tidak ditegaskan 00 (konvensi 1.2 butir 11); `sort_order` = `SMALLINT UNSIGNED DEFAULT 0`; `is_active` default `1`, `is_published` default `0`; `clinics.is_bookable` default `1`; `articles.status` default `draft`.
5. **[ASUMSI]** `patient_profiles.patient_number` nullable di DB (diisi setelah insert dalam transaksi yang sama) karena `id` baru diketahui setelah insert.
6. `patient_profiles.bpjs_number` hanya terisi bila pasien mencentang "simpan ke profil" (01 A-14) dan tidak pernah ditampilkan penuh (01 A-13); nilai otoritatif per kunjungan ada di `appointments.payment_reference`. Menonaktifkan profil berakun juga menonaktifkan `users.is_active` (01 A-15).
7. **[ASUMSI]** Semua FK jejak pembuat/pengubah ke `users` nullable dengan `SET NULL`; `doctor_specialties.specialty_id` `RESTRICT`; `doctor_specialties` tanpa timestamp; kolom `created_by`/`updated_by`/`reviewed_by` nullable.
8. **[ASUMSI]** CHECK tambahan di luar 00: `chk_schedules_day`, `chk_schedules_quota`, `chk_schedules_validity`, `chk_sessions_quota_range`, `chk_sessions_queue`, `chk_appointments_queue`, `chk_banners_period`, `chk_media_owner`, `chk_schedule_exceptions_scope` (menegakkan kombinasi jenis vs dokter 00 E.2 di database; belum diuji pada sesi verifikasi 14.1); ekspresi `chk_sessions_quota` dan `chk_room_categories_beds` sesuai 00.
9. **[ASUMSI]** Index tambahan di luar 00: `patient_profiles_full_name_index`, `patient_profiles_phone_index`, `doctor_schedules_is_active_valid_from_valid_until_index`, `media_file_name_unique`, `schedule_exceptions_*`, `audit_logs_*`, dan index `is_active`/`sort_order` pada tabel konten.
10. **[ASUMSI]** `clinics.code` tidak berubah setelah ada appointment; sesi tidak mengubah `doctor_id`/`clinic_id`/`session_date`; sesi non-batal seorang dokter tidak beririsan; sesi lampau tidak dihapus; `archived_at` dikosongkan saat dokter dipulihkan.
11. **[ASUMSI]** Logo situs dimiliki baris `website_settings` berkunci `site.logo_media_id`; `media:prune` juga membersihkan baris yang pemiliknya sudah tidak ada; `thumb_path` berada di subfolder `thumbs`.
12. **[ASUMSI]** Redaksi audit ditambah `complaint`, `address`, `birth_date`; `ip_address`/`user_agent` audit boleh NULL untuk aksi CLI/sistem.
13. **[ASUMSI]** `CACHE_STORE=database` (01 A-40); struktur `cache`/`jobs` mengikuti skeleton Laravel 12.x yang diverifikasi.
14. **[ASUMSI]** Setiap staf memiliki tepat satu peran; `model_has_permissions` kosong; label peran/izin dari berkas bahasa.
15. **[ASUMSI]** Publikasi artikel terjadwal (`published_at` ≤ sekarang); tanpa UI pemulihan artikel; pembaruan `users.name` pasien bersama profil; email dinormalisasi huruf kecil.
16. **[ASUMSI]** Angka retensi produksi (bagian 13.3) hanyalah usulan; perkiraan volume (bagian 15) mengikuti 13 untuk angka seeder dan 01 A-30 untuk skala pertumbuhan.
17. **[KONFIRMASI]** Kebijakan retensi dan anonimisasi data pasien di produksi (K-09, K-06 di 17); versi database produksi (MariaDB ≥ 10.11 LTS atau MySQL 8.4 LTS; K-01); pembatasan hak `INSERT, SELECT` pada tabel audit untuk akun aplikasi produksi (K-19). Pertanyaan "apakah pengarsipan dokter ditolak bila ada appointment aktif mendatang" **sudah diputuskan** (00 F.4, D-02): ditolak; penonaktifan menghasilkan sesi `doctor_inactive` (D-01).
18. Keputusan di 00/17 yang diwujudkan di dokumen ini (bukan lagi asumsi): grup `privasi` (E.6/E.7), sinkron email/`is_active` profil berakun (E.1, D-07, D-08), enam sumber `pending_change` beserta prioritasnya (F.4, D-03, D-04), tabel `destroy` (C), errno 1062/4025(3819)/1205/1213 dan `SYSTEM_BUSY` (F.5–F.6, D-09), dimensi minimum media per koleksi (K, D-11).

## Nama baru yang diusulkan

Tidak ada tabel, kolom, atau enum baru; seluruhnya mengikuti 00 bagian E. Yang diusulkan hanyalah **nama constraint/index** agar dapat direkonsiliasi ke 00 dan dipakai `BookingService` serta pengujian:

- Index unique eksplisit: `appointments_patient_session_active_unique`.
- 14 constraint CHECK bernama: `chk_schedules_time`, `chk_schedules_day`, `chk_schedules_quota`, `chk_schedules_validity`, `chk_exceptions_dates`, `chk_schedule_exceptions_scope` (baru; usulan untuk 00 E.2), `chk_sessions_quota`, `chk_sessions_quota_range`, `chk_sessions_time`, `chk_sessions_queue`, `chk_appointments_queue`, `chk_room_categories_beds`, `chk_banners_period`, `chk_media_owner` (CHECK JSON bersifat otomatis dan tidak diberi nama).
- Ability `PracticeSessionPolicy::delete` (baru): menerapkan baris `practice_sessions` pada tabel `destroy` 00 C untuk route `admin.sessions.destroy` (`DELETE /admin/jadwal/sesi/{session}`, sudah tercantum di 00 I.4; hanya sesi tambahan manual tanpa appointment). Matriks Policy di 04 belum memuat ability ini.
- Kode aksi audit `session.pending_replaced` (penggantian `pending_change`, 00 F.4) dan `session.deleted` (hapus sesi manual); lihat 12 §8.
- Nama final yang **menggantikan contoh ilustratif** di [03-arsitektur.md](03-arsitektur.md) bagian 5.4: `uq_appt_patient_session_active` → `appointments_patient_session_active_unique`; `chk_sessions_booked` → `chk_sessions_quota`. Bagian 03 sendiri menyatakan nama final ditetapkan di 09.
- Nama berkas migration pada bagian 10 (`2026_10_02_000010` … `2026_10_02_002300`).
- Nama model/relasi yang disebut di dokumen ini tetapi final di bagian 11: `DoctorSpecialty` (model pivot), `WebsiteSetting`, trait `HasMedia`, relasi `PatientProfile::creator()`, `Appointment::patientProfile()`/`practiceSession()`/`statusHistories()`/`creator()`/`canceller()`, `PracticeSession::schedule()`/`exception()`/`reviewer()`, `RoomCategory::availabilityUpdater()`, `Doctor::primarySpecialty()`.
