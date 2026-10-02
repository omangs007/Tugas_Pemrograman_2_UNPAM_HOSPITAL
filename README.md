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
5. `& $PHP artisan migrate` dan `& $PHP artisan migrate --env=testing`, lalu `& $PHP artisan storage:link` (16 §6).
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
