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
