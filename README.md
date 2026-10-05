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

## Fase 2 — Master Data (spesialisasi, poliklinik, dokter)

Yang tersedia: pengelolaan **Dokter** (tambah, ubah, foto, aktif/nonaktif, arsip, pemulihan dari arsip) di <http://unpam-hospital.test/admin/dokter>; **Poliklinik dan Spesialisasi** (CRUD, cover, galeri, editor rich text dengan unggah gambar) di `/admin/poliklinik` dan `/admin/spesialisasi`; halaman publik **Layanan Kami** di `/layanan` dan **Detail Layanan** di `/layanan/{slug}`. Halaman publik dokter dan jadwal tersedia sejak fase 3 (lihat bagian "Fase 3").

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

### Pemeriksaan cepat (fase 2)

```powershell
& $PHP artisan route:list --path=admin   # 31 route admin (6 fase 1 + 25 fase 2)
& $PHP artisan media:prune --dry-run     # setelah seeding: 0 baris dan 0 berkas yatim
& $PHP artisan test                      # seluruh suite
npm run build                            # editor Quill ikut dibundel (tanpa CDN)
```

## Fase 3 — Penjadwalan (templat, pengecualian, sesi, dan jadwal publik)

Yang tersedia:
- **Jadwal Praktik** di <http://unpam-hospital.test/admin/jadwal> dengan tiga tab: **Templat Mingguan** (`/admin/jadwal`), **Sesi per Tanggal** (`/admin/jadwal/sesi`), dan **Pengecualian** (cuti dan libur, `/admin/jadwal/pengecualian`). Tab **Sesi Terdampak** muncul otomatis setelah fasenya (fase 7).
- Halaman publik **Cari Dokter** (`/dokter`), **Detail Dokter** (`/dokter/{slug}`), dan **Jadwal Dokter** (`/jadwal-dokter`), semuanya memakai satu sumber jadwal (`DoctorAvailabilityQuery`).
- Perintah `sessions:generate` dan jadwal harian pukul 00.05, serta skema booking (`patient_profiles`, `appointments`, `appointment_status_histories`) yang baru berupa tabel, model, dan factory. Pendaftaran kunjungan sungguhan (wizard dan `BookingService`) menyusul di fase 6.

### Hak akses

| Peran | Lihat jadwal | Kelola jadwal (tambah, ubah, hapus) | Sesi Terdampak |
|---|:-:|:-:|:-:|
| Super Admin | ya | ya | ya (fase 7) |
| Admin Konten | ya | ya | tidak |
| Petugas Pendaftaran | ya | tidak | ya (fase 7) |

Admin Konten hanya melihat jumlah terisi dan kuota per sesi ("Terisi"), tidak pernah nama atau data pasien. Halaman publik #6, #7, dan #8 terbuka untuk semua orang; dokter nonaktif, arsip, atau di poliklinik nonaktif menjawab 404 di URL detailnya, juga untuk staf yang login.

### Pembangkitan sesi

Sesi dibangkitkan dari templat mingguan untuk hari ini sampai hari ini + `booking.window_days` (30 hari) lewat tiga pemicu: setiap simpan templat atau pengecualian, sekali per hari saat halaman #6, #7, atau #8 dibuka, dan perintah manual:

```powershell
& $PHP artisan sessions:generate            # jendela bawaan (booking.window_days)
& $PHP artisan sessions:generate --days=45  # 1-120 hari
& $PHP artisan schedule:list                # sessions:generate dijadwalkan harian pukul 00.05
```

Windows Task Scheduler (blueprint 16 §11.1) bersifat opsional; tanpa itu, halaman publik tetap membangkitkan sesi sekali per hari.

Agar pekerjaan harian pukul 00.05 berjalan, cron server (atau Windows Task Scheduler) harus menjalankan `php artisan schedule:run` setiap menit. Bila itu belum dipasang, membuka halaman publik Cari Dokter (#6), Detail Dokter (#7), atau Jadwal Dokter (#8) juga memicu pembangkitan sesi harian sebagai cadangan.

### Aturan yang berlaku untuk semua peran, termasuk Super Admin

Sesi yang sudah berakhir tidak dapat diubah, ditutup, dibatalkan, atau dihapus. Kuota sesi tidak boleh di bawah jumlah terisi. Templat atau sesi yang sudah punya pendaftaran tidak dapat dihapus (nonaktifkan, tutup, atau batalkan). Dokter arsip tidak dapat diberi jadwal, dan dokter dengan pendaftaran aktif pada sesi mendatang tidak dapat diarsipkan.

### Data demo

```powershell
& $PHP artisan migrate:fresh --seed   # LOKAL SAJA: menghapus semua tabel dan isi database unpam_hospital
```

Selain data fase 1–2: 52 templat jadwal (51 aktif, 1 nonaktif milik dokter nonaktif), sesi untuk hari ini sampai hari ini + 30 (tanpa sesi lampau; sekitar 230 sesi), cuti 7 hari dr. Dimas, satu hari libur rumah sakit, satu sesi "Ditutup sementara (contoh)" dr. Citra, dan satu sesi tambahan dr. Teguh berkuota 2. Seluruh tanggal relatif terhadap hari seeding. Appointment demo menyusul di fase 6.

### Pemeriksaan cepat (fase 3)

```powershell
& $PHP artisan route:list --path=admin   # 50 route admin (31 fase 1-2 + 19 fase 3)
& $PHP artisan route:list --except-path=admin --name=doctors     # doctors.index dan doctors.show (publik)
& $PHP artisan route:list --except-path=admin --name=schedules   # schedules.index (publik)
& $PHP artisan test                      # seluruh suite
npm run build                            # gaya grid jadwal ikut dibundel
```
