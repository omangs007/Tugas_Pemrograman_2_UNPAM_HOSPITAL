# Checkpoint — UNPAM HOSPITAL

**Terakhir diperbarui:** 2026-10-04 (akhir fase 1) · **Tag git:** `checkpoint/2026-10-04-fase-1` (sebelumnya `checkpoint/2026-10-03-fase-1-task-4`, `checkpoint/2026-10-02-fase-0`)

Dokumen ini adalah titik lanjut untuk sesi berikutnya. Baca dari atas ke bawah sebelum bekerja.

## 1. Posisi saat ini

| Hal | Status |
|---|---|
| Blueprint (spesifikasi) | Selesai & disetujui — `docs/superpowers/specs/2026-10-02-unpam-hospital-blueprint/` (mulai dari `README.md`, lalu `00-keputusan-inti.md`). Diperbarui di fase 1: 00 J.6, 06 §14.A, 10 (pratinjau `FileReader`), 11 §3.3/§6.10/§6.11 (`scoped`), 12 V-03, 15 SEK-03, 16 §5/§7, 17 §4 (D-21–D-28). |
| Fase 0 — Lingkungan & skeleton | **Selesai**, di-merge ke `master`. Rencana + catatan eksekusi: `docs/superpowers/plans/2026-10-02-fase-0-lingkungan-skeleton.md`. |
| Fase 1 — Fondasi | **Selesai** dan **sudah di-merge** ke `master` (fast-forward `0b87342`, 2026-10-04; cabang `fase-1-fondasi` dihapus). Gerbang fase terpenuhi: 980 tes, Pint, `npm run build`, `migrate:fresh --seed`, dan login tiga akun demo. Login diverifikasi koordinator lewat otomasi browser (Playwright); konfirmasi manual pemilik disarankan. Final review seluruh cabang beserta perbaikannya sudah selesai. Rencana, catatan eksekusi, dan keputusan R1–R17: `docs/superpowers/plans/2026-10-03-fase-1-fondasi.md` (bagian akhir "Catatan Eksekusi"). |
| Cabang git | `master` (fase 0 + fase 1). Repo tanpa remote. Cabang fase berikutnya dibuat dari `master`. |
| Keputusan tertunda pemilik | (1) Konfirmasi manual login per peran (opsional, lihat §3). (2) Butir K di `17-keputusan-konfirmasi.md`: semua punya default; yang terpenting sebelum fase 5 adalah K-07. |
| Fase berikutnya | **Fase 2 — Master Data** (blueprint `14-tahapan-implementasi.md` §Fase 2). Fase 4a (CMS statis) dan fase 5 (akun pasien) juga hanya membutuhkan fondasi. Rencana implementasinya belum ada. |

Yang sudah ada di aplikasi: enum, akun & peran (Spatie), audit log, media + sanitasi HTML, pengaturan situs, tema & komponen Blade, layout publik/pasien/auth/admin, login/logout staf, ganti password wajib, header keamanan, `StaffAccountService` (aturan mutlak Super Admin), `app:create-super-admin`, `media:prune`, dan seeder demo staf. Area admin fase 1 terdiri dari login, ganti password, dan dasbor sapaan (`route:list --path=admin` menampilkan 6 route).

## 2. Lingkungan (mesin pemilik)

- XAMPP 8.2.12 ada di **`C:\xampp3`** (bukan `C:\xampp`, itu sisa lama). Sebelum bekerja, Apache dan MySQL harus di-**Start** dari `C:\xampp3\xampp-control.exe`.
- Runtime PHP 8.2.12 (`C:\xampp3\php\php.exe`). PHP 8.4 di PATH **jangan** dipakai untuk proyek. Composer selalu dijalankan sebagai `& $PHP C:\composer\composer.phar …`, bukan `composer.bat` (karakter `^` hilang).
- Database `unpam_hospital` (dev) dan `unpam_hospital_test` (uji), user `unpam_app`. Password hanya ada di `.env`/`.env.testing` (git-ignored, jangan dihapus).
- URL: <http://unpam-hospital.test> (VirtualHost; cadangan konfigurasi `httpd-vhosts.conf.bak-fase0`). Alternatif: `php artisan serve` → <http://127.0.0.1:8000>. Login admin: <http://unpam-hospital.test/admin/login>.
- Akun demo (hanya lokal, password `Demo12345` atau `DEMO_PASSWORD`): `superadmin@`, `konten@`, `pendaftaran@unpamhospital.test`. Akun ini dibuat oleh `php artisan migrate:fresh --seed`, yang menghapus seluruh isi database pengembangan. Database pengembangan saat ini sudah di-seed dan hanya berisi akun demo.
- Tabel sistem MariaDB XAMPP pernah rusak (`mysql.db` + log Aria) dan sudah diperbaiki. Bila hak akses DB hilang lagi, lihat blueprint 16 §13 baris "`GRANT` tidak tersimpan".

## 3. Cara melanjutkan (PowerShell)

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"
$env:Path = "$XAMPP\php;$env:Path"; Set-Location "D:\Project Web RS"

git status; git branch --show-current          # harus bersih, di master
& $PHP scripts\check-env.php                    # "status": "OK"
& $PHP artisan test                             # 980 passed
& $PHP vendor\bin\pint --test                   # PASS
& $PHP artisan route:list --path=admin          # 6 route
curl.exe -s -o NUL -w '%{http_code}' http://unpam-hospital.test/admin/login   # 200
```

Konfirmasi manual (opsional, ±5 menit): buka <http://unpam-hospital.test/admin/login>, login sebagai ketiga akun demo, buka menu pengguna lalu **Keluar**. Daftar periksa lengkap ada di rencana fase 1, Task 19 Step 22.

Bila semua hijau: minta Claude menulis rencana **Fase 2** dengan skill `superpowers:writing-plans` dan mengeksekusinya dengan `superpowers:subagent-driven-development` memakai subagent **Sonnet 5.5**.

## 4. Bawaan (carry) untuk fase berikutnya

Ringkasan; rinciannya ada di bagian "Catatan Eksekusi" rencana fase 0 dan fase 1.

- **Fase 2:**
  - Endpoint media F-ADM-29 (`MediaController`, `MediaPolicy`, `admin.media.*`, V-42; F1-R21/D-28).
  - Format tanggal Indonesia (`translatedFormat`).
  - `HasMedia`/`SanitizesRichText`, `AuditObserver`, butir (c) `media:prune`, `lang/id/messages.php`.
  - Advisori `quill@2.0.3` (GHSA-v3m3-f69x-jf25): wajib sanitasi `HtmlSanitizer` saat simpan.
  - Validasi form pengaturan: WhatsApp `62…`, bool/int ketat.
  - `deleteFor()` di dalam `DB::transaction`.
  - `x-form.checkbox` bernama array butuh `id` eksplisit dan perbaikan `old()`.
  - Data-table: halaman di luar jangkauan. Confirm-modal: kolom `reason`.
- **Fase 3–4:**
  - `module-tabs`, `availability-badge`, `doctor-card`, `schedule-table` (3).
  - `HomeController` menggantikan `Route::view('/', 'public.home')` (F1-R3).
  - `PageSeeder` ditambahkan ke `DatabaseSeeder`, `form.rich-text` + `editor.js` (4a).
  - `map-embed` (4b).
- **Fase 5:**
  - Hapus cadangan `Route::has('login')`/`Route::has('patient.dashboard')` (F1-R2).
  - `passwords.throttled` dipetakan ke pesan generik `passwords.sent` (mencegah enumerasi email).
  - `lang/id.json`, `routes/patient.php`.
  - Akun pasien demo `pasien@unpamhospital.test` di `DemoUserSeeder`.
- **Fase 6:** handler `BookingException`, `SettingsService::bookingRules()`.
- **Fase 8:**
  - `StaffAccountService::syncRolePermissions`, `RolePolicy`, `Route::bind('user')`.
  - Panggilan `StaffAccountService` dibungkus retry deadlock (`DB::transaction(..., 3)`).
  - Widget dasbor menggantikan sapaan sementara (F1-R4).
- **Hardening sebelum produksi:**
  - `auth.timebox_duration` di atas waktu bcrypt server (bcrypt cost 12 terukur 192 ms di mesin ini).
  - Header keamanan untuk respons 503/413.
  - Throttle `PUT /admin/ganti-password` dan batas login per IP.
  - Log `path()`, bukan `fullUrl()`.
  - `audit_logs` immutable di level DB.
  - `autofocus` email di halaman login.
- **Lain-lain:**
  - `.env.testing` mesin ini boleh disamakan dengan `.env.testing.example` (`LOG_CHANNEL=single`).
  - Baris "429" untuk POST di dokumen 06 dibaca sesuai D-23.

## 5. Riwayat sesi

1. **2026-10-02:** Brainstorming → blueprint 17 bagian + `00-keputusan-inti.md`. Rencana fase 0 → eksekusi → 26 tes hijau. Insiden `mysql.db`/Aria XAMPP diperbaiki.
2. **2026-10-03:** Merge fase 0 ke `master`; K-10 = (a). Rencana fase 1 (19 tugas) disusun dan disetujui. Scan pra-eksekusi (`ca70747`). Task 1–3 selesai; Task 4 dijeda setelah siklus `Actor`.
3. **2026-10-03/04:**
   - Task 4–19 selesai lewat subagent-driven-development (Sonnet 5.5).
   - Putaran perbaikan di Task 7, 9, 11, dan 17.
   - Final review seluruh cabang + satu gelombang perbaikan (hash dummy login dibatalkan, R16).
   - Gerbang fase 1 terpenuhi: 980 tes; login diverifikasi lewat Playwright.
   - Blueprint direkonsiliasi (D-21–D-28, `scoped`, `FileReader`).
   - M-3/M-6 dari fase 0 ditutup.
