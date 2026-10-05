# Checkpoint — UNPAM HOSPITAL

**Terakhir diperbarui:** 2026-10-05 (penyusunan rencana fase 3 dijeda) · **Tag git:** `checkpoint/2026-10-05-fase-3-rencana` (sebelumnya `checkpoint/2026-10-05-fase-2`, `checkpoint/2026-10-04-fase-1`, `checkpoint/2026-10-03-fase-1-task-4`, `checkpoint/2026-10-02-fase-0`)

Dokumen ini adalah titik lanjut untuk sesi berikutnya. Baca dari atas ke bawah sebelum bekerja.

## 1. Posisi saat ini

| Hal | Status |
|---|---|
| Blueprint (spesifikasi) | Selesai & disetujui — `docs/superpowers/specs/2026-10-02-unpam-hospital-blueprint/` (mulai dari `README.md`, lalu `00-keputusan-inti.md`). Diperbarui di fase 1: 00 J.6, 06 §14.A, 10 (pratinjau `FileReader`), 11 §3.3/§6.10/§6.11 (`scoped`), 12 V-03, 15 SEK-03, 16 §5/§7, 17 §4 (D-21–D-28). |
| Fase 0 — Lingkungan & skeleton | **Selesai**, di-merge ke `master`. Rencana + catatan eksekusi: `docs/superpowers/plans/2026-10-02-fase-0-lingkungan-skeleton.md`. |
| Fase 1 — Fondasi | **Selesai** dan **sudah di-merge** ke `master` (fast-forward `0b87342`, 2026-10-04; cabang `fase-1-fondasi` dihapus). Gerbang fase terpenuhi: 980 tes, Pint, `npm run build`, `migrate:fresh --seed`, dan login tiga akun demo. Login diverifikasi koordinator lewat otomasi browser (Playwright); konfirmasi manual pemilik disarankan. Final review seluruh cabang beserta perbaikannya sudah selesai. Rencana, catatan eksekusi, dan keputusan R1–R17: `docs/superpowers/plans/2026-10-03-fase-1-fondasi.md` (bagian akhir "Catatan Eksekusi"). |
| Fase 2 — Master Data | **Eksekusi dan gerbang fase selesai** di cabang `fase-2-master-data`. Semua 16 tugas lulus review. Final review seluruh cabang beserta satu gelombang perbaikan (F1–F5) selesai. `migrate:fresh --seed` dijalankan dengan izin pemilik dan jumlah datanya persis sesuai target. Uji manual Step 19 dijalankan lewat Playwright (laporan bergambar di `Documents\UNPAM-Uji-Manual-2026-10-05`); dua cacat temuannya (M1 `&nbsp;` editor, M2 panah galeri) sudah diperbaiki di `929c13a`. Hasil akhir: 1845 tes lulus (8479 asersi), Pint PASS, `npm run build` OK, 31 route admin. **Sudah di-merge** ke `master` (fast-forward `51134af`, 2026-10-05; cabang `fase-2-master-data` dihapus; suite pada hasil merge 1845 lulus). Rencana, catatan eksekusi, dan keputusan X1–X14 ada di bagian akhir `docs/superpowers/plans/2026-10-04-fase-2-master-data.md`. |
| Cabang git | `master` (fase 0 + fase 1 + fase 2). Cabang kerja **`fase-3-jadwal-dokter`** dibuat dari `master` (`af4fd02`) dan baru berisi commit CHECKPOINT ini. Repo tanpa remote. |
| Keputusan tertunda pemilik | Butir K di `17-keputusan-konfirmasi.md`: semua punya default; yang terpenting sebelum fase 5 adalah K-07. |
| Fase 3 — Penjadwalan (Jadwal Dokter) | **Rencana sedang disusun, dijeda 2026-10-05** atas permintaan pemilik. Rinciannya di §3a. |

### 3a. Status penyusunan rencana fase 3

Semua bahan ada di `.superpowers/plan-fase-3/` (git-ignored; **jangan** jalankan `git clean -fdx`):

| Berkas | Isi | Status |
|---|---|---|
| `digest-data.md`, `digest-http.md`, `digest-tests-carry.md`, `digest-kode.md` | Kutipan verbatim spesifikasi dan kode yang ada | Selesai |
| `kerangka.md` | Kontrak koordinator: keputusan F3-R1–F3-R30, nama baru, peta berkas, Global Constraints, Review Focus, kontrak T1–T16 | Selesai |
| `tasks-T1-T4.md` | Skema, model, spike konkurensi, generator sesi | Selesai (6876 baris) |
| `tasks-T5-T8.md` | `DoctorAvailabilityQuery`, `ScheduleChangeService` (templat, pengecualian, sesi manual, status dokter) | Selesai (5947 baris) |
| `tasks-T9-T12.md` | Policy dan Form Request, admin #35 (Templat, Pengecualian, Sesi) | Selesai (6638 baris) |
| `tasks-T13-T16.md` | Halaman publik #6/#7/#8, seeder, matriks akses dan gerbang | **Belum.** Penulis dihentikan saat jeda; kirim ulang dengan `prompt-penulis-D.md` |
| `catatan-koordinator.md` | Ringkasan hasil penulis, butir yang perlu keputusan, titik lanjut | Mutakhir |

Langkah lanjut, berurutan:
1. Kirim ulang penulis D (T13–T16).
2. Putuskan butir "Catatan penulis A/B/C" ke `ruling-konsistensi.md`.
3. Jalankan dua pemeriksa konsistensi, lalu perbaiki task.
4. Rakit `docs/superpowers/plans/2026-10-05-fase-3-penjadwalan.md`, commit, dan minta persetujuan pemilik.
5. Eksekusi dengan subagent-driven-development (Sonnet 5.5).

Yang sudah ada di aplikasi:
- Fase 1: enum, akun & peran (Spatie), audit log, media + sanitasi HTML, pengaturan situs, tema & komponen Blade, layout publik/pasien/auth/admin, login/logout staf, ganti password wajib, header keamanan, `StaffAccountService`, `app:create-super-admin`, `media:prune`, seeder demo staf.
- Fase 2: master data spesialisasi, poliklinik/layanan penunjang, dan dokter (CRUD admin, filter, tab, status/arsip dokter lewat `DoctorService`); editor rich text Quill dengan unggah gambar (`admin.media.*`, `MediaPolicy`, `syncEditorImages`); halaman publik `/layanan` (#4) dan `/layanan/{slug}` (#5); seeder demo 13 spesialisasi, 17 layanan, 18 dokter, 32 gambar contoh; poles login/ganti password/footer admin.

## 2. Lingkungan (mesin pemilik)

- XAMPP 8.2.12 ada di **`C:\xampp3`** (bukan `C:\xampp`, itu sisa lama). Sebelum bekerja, Apache dan MySQL harus di-**Start** dari `C:\xampp3\xampp-control.exe`.
- Runtime PHP 8.2.12 (`C:\xampp3\php\php.exe`). PHP 8.4 di PATH **jangan** dipakai untuk proyek. Composer selalu dijalankan sebagai `& $PHP C:\composer\composer.phar …`, bukan `composer.bat` (karakter `^` hilang).
- Database `unpam_hospital` (dev) dan `unpam_hospital_test` (uji), user `unpam_app`. Password hanya ada di `.env`/`.env.testing` (git-ignored, jangan dihapus).
- URL: <http://unpam-hospital.test> (VirtualHost; cadangan konfigurasi `httpd-vhosts.conf.bak-fase0`). Alternatif: `php artisan serve` → <http://127.0.0.1:8000>. Login admin: <http://unpam-hospital.test/admin/login>.
- Akun demo (hanya lokal, password `Demo12345` atau `DEMO_PASSWORD`): `superadmin@`, `konten@`, `pendaftaran@unpamhospital.test`. Akun ini dibuat oleh `php artisan migrate:fresh --seed`, yang menghapus seluruh isi database pengembangan. Database pengembangan sudah di-seed ulang dengan data demo fase 2 (2026-10-05). Isinya ditambah sedikit data dari uji manual: dokter "Laras Wulandari Putri" (Nonaktif), serta dua foto galeri dan satu kalimat tambahan di Poliklinik Penyakit Dalam. Perintah `migrate:fresh --seed` menghapus seluruh isi DB pengembangan, jadi Claude perlu izin eksplisit pemilik untuk menjalankannya karena pengaman auto mode memblokir.
- Tabel sistem MariaDB XAMPP pernah rusak (`mysql.db` + log Aria) dan sudah diperbaiki. Bila hak akses DB hilang lagi, lihat blueprint 16 §13 baris "`GRANT` tidak tersimpan".

## 3. Cara melanjutkan (PowerShell)

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"
$env:Path = "$XAMPP\php;$env:Path"; Set-Location "D:\Project Web RS"

git status; git branch --show-current          # harus bersih, di fase-3-jadwal-dokter
& $PHP scripts\check-env.php                    # "status": "OK"
& $PHP artisan test                             # 1845 passed (±160 s)
& $PHP vendor\bin\pint --test                   # PASS
& $PHP artisan route:list --path=admin          # 31 route
& $PHP artisan media:prune --dry-run            # 0 baris, 0 berkas yatim
curl.exe -s -o NUL -w '%{http_code}' http://unpam-hospital.test/layanan       # 200
```

Bila semua hijau, lanjutkan penyusunan rencana fase 3 dari §3a dengan `superpowers:writing-plans`. Setelah pemilik menyetujui rencananya, eksekusi dengan `superpowers:subagent-driven-development` memakai subagent **Sonnet 5.5**.

## 4. Bawaan (carry) untuk fase berikutnya

Ringkasan; rinciannya ada di bagian "Catatan Eksekusi" rencana fase 0, fase 1, dan fase 2. Bawaan fase 2 dari fase 1 sudah dikerjakan (D-52), kecuali form pengaturan (D-53, fase 8).

- **Fase 3:**
  - Isi `DoctorService::futureActiveAppointmentsCount()` dan `ClinicRequest::clinicHasAppointments()`.
  - Uji end-to-end penolakan arsip dokter.
  - Asersi URL publik dokter (D-54).
  - `applyDoctorInactive()`.
  - `availability-badge`, `schedule-table`, dan `x-doctor-card` dengan jadwal terdekat (F2-R9).
- **Fase 4:**
  - `HomeController` menggantikan `Route::view('/', 'public.home')` (F1-R3).
  - `PageSeeder` ditambahkan ke `DatabaseSeeder` (4a).
  - Model `Page` untuk judul #4 (F2-R10).
  - Kolom rich text baru wajib masuk `MediaService::EDITOR_REFERENCE_COLUMNS` (D-55, 4a).
  - `map-embed` (4b).
- **Minor fase 2 yang ditunda:**
  - Suite ±150 s (seed demo per tes).
  - `errorBag` per media galeri.
  - Gambar editor lintas poliklinik.
  - Logo kecil di header publik dan sidebar admin tampak sempit (lebar = tinggi).
  - Lainnya tercantum di Catatan Eksekusi fase 2.
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
4. **2026-10-04:**
   - Fase 1 di-merge ke `master`.
   - Uji manual login dengan Playwright (laporan bergambar di `Documents\UNPAM-Uji-Manual-2026-10-04`).
   - Rencana Fase 2 disusun: tiga digest spesifikasi, kerangka kontrak, empat penulis, dua pemeriksa konsistensi. Hasilnya 16 tugas dengan keputusan F2-R1–F2-R28.
5. **2026-10-04/05:**
   - Rencana fase 2 disetujui pemilik. Scan pra-eksekusi S1–S13 ditambal (`539ffbb`).
   - Task 1–16 selesai lewat subagent-driven-development (Sonnet 5.5), dengan putaran perbaikan di Task 2, 7, 11, dan 16.
   - Final review seluruh cabang menemukan 3 butir Important: gambar editor terhapus `media:prune`, spesialisasi nonaktif tampil publik, dan alt diabaikan. Ketiganya beserta 2 Minor ditutup dalam satu gelombang perbaikan (`fb4ab7d`). Blueprint direkonsiliasi (D-29–D-58).
   - `migrate:fresh --seed` DB pengembangan sempat ditolak pengaman auto mode, lalu dijalankan setelah izin pemilik.
   - Uji manual Playwright menemukan 2 cacat: spasi `&nbsp;` dari Quill 2 dan panah galeri. Keduanya diperbaiki (`929c13a`, 1845 tes).
   - Fase 2 di-merge ke `master` (fast-forward `51134af`), dan cabang `fase-2-master-data` dihapus.
6. **2026-10-05 (lanjutan):**
   - Penyusunan rencana fase 3 dimulai di cabang `fase-3-jadwal-dokter`.
   - Empat digest spesifikasi/kode dan kerangka kontrak (F3-R1–F3-R30) selesai.
   - Penulis T1–T12 selesai, sedangkan penulis T13–T16 dihentikan karena pemilik meminta jeda ("di stop dulu lanjut nanti").
