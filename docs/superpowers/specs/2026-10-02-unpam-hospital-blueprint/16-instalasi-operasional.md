# 16 — Panduan Instalasi dan Operasional Localhost

**Tujuan bagian.** Panduan langkah demi langkah untuk menjalankan UNPAM HOSPITAL di Windows 11 dengan XAMPP 8.2.12, dari pemeriksaan XAMPP sampai backup–restore dan troubleshooting, dengan perintah PowerShell yang dapat disalin. Seluruh butir §15 kebutuhan pemilik tercakup: cek PHP/ekstensi, Composer, dependency frontend, database, `.env`, application key, migration dan seeder, storage link, build aset, VirtualHost, URL lokal, alternatif `php artisan serve`, izin folder, uji reset password, backup/restore, dan troubleshooting.

Bagian terkait: [00 — Keputusan inti](00-keputusan-inti.md) (B: lingkungan) · [03 — Arsitektur](03-arsitektur.md) · [10 — Struktur folder](10-struktur-folder.md) · [13 — Data contoh](13-data-contoh.md) · [14 — Tahapan implementasi](14-tahapan-implementasi.md) (fase 0) · [15 — Pengujian & penerimaan](15-pengujian-penerimaan.md) (`INS-*`, `BAK-*`).

> **Konvensi.** Semua perintah dijalankan di **Windows PowerShell** (bukan cmd). Kutip setiap path yang mengandung spasi (`"D:\Project Web RS"`). Tanda `#` pada blok kode adalah komentar. Nilai `GANTI_…` harus Anda ganti. Perintah yang butuh hak Administrator diberi label **(Admin)**: klik kanan PowerShell → *Run as administrator*. Panduan ini hanya memeriksa dan mengubah file konfigurasi yang disebut secara eksplisit; tidak ada langkah yang menghapus data XAMPP atau database yang sudah ada.

---

## Prasyarat dan Ringkasan Langkah

### Fakta mesin pemilik (hasil pemeriksaan saat blueprint ditulis)

| Komponen | Kondisi | Tindakan |
|---|---|---|
| OS | Windows 11 Pro | — |
| **XAMPP** | **Sudah terpasang di `C:\xampp3`** (PHP 8.2.12 ZTS VC++2019 x64; Apache 2.4.58 di port 80/443 dan MariaDB 10.4.32 di port 3306 **sedang berjalan**) | Lewati pemasangan (§1); hanya pakai |
| `C:\xampp` lama | Sisa instalasi lama (hanya `xampp-control.exe`, `xampp-control.ini`, `locale`) — **tidak dipakai** | Abaikan; opsional ganti nama menjadi `C:\xampp-old` agar tidak tertukar |
| `C:\xampp3\php\php.ini` | `;extension=gd`, `;extension=intl`, `;extension=zip` (dan `;extension=sodium`) **masih dikomentari**; `date.timezone=Europe/Berlin`; `upload_max_filesize=40M`, `post_max_size=40M`, `memory_limit=512M`; `openssl` sudah termuat walau barisnya dikomentari | Aktifkan `gd`, `intl`, `zip`; ubah zona waktu ke `Asia/Jakarta` (§2) |
| `C:\xampp3\apache\conf\httpd.conf` | `mod_rewrite` sudah dimuat dan `Include conf/extra/httpd-vhosts.conf` sudah aktif | Cukup verifikasi (§7) |
| MariaDB | User `root` **tanpa password** (`root@localhost`, `127.0.0.1`, `::1`, serta `pma@localhost`); belum ada database selain bawaan | Buat database + user khusus `unpam_app` (§4); `root` dibiarkan tanpa password selama hanya localhost (keputusan K-12 di 17, default (a)); opsi (b) memberinya password (§4.3) |
| PHP CLI di PATH | 8.4.26 di `C:\php\8.4` | Samakan dengan PHP XAMPP 8.2.12 (§2.4) |
| Composer | 2.9.2 di `C:\composer\composer.bat` | Dipakai apa adanya |
| Node.js / npm | 24.12.0 / 11.6.2 | Hanya untuk build aset |
| Git | 2.45 | Repo di `D:\Project Web RS` (berisi `.git` dan `docs/`) |
| MySQL Workbench | 8.0 (klien saja) | Opsional untuk melihat database XAMPP |

### Variabel sesi (tempel di awal setiap sesi PowerShell)

Path XAMPP didefinisikan **sekali** sebagai variabel. Di mesin pemilik nilainya `C:\xampp3`; di mesin lain yang memasang XAMPP ke folder bawaan, ganti menjadi `C:\xampp`.

```powershell
$XAMPP = "C:\xampp3"                         # folder XAMPP (mesin lain: mis. "C:\xampp")
$APP   = "D:\Project Web RS"                 # root proyek Laravel (sekaligus root repo git)
$PHP   = "$XAMPP\php\php.exe"                # PHP 8.2.12 milik XAMPP
$MYSQL = "$XAMPP\mysql\bin\mysql.exe"        # klien MariaDB milik XAMPP
Set-Location $APP
```

Setelah itu `& $PHP artisan about` setara dengan `C:\xampp3\php\php.exe artisan about`. Dokumen lain pada blueprint menulis path penuh `C:\xampp3\…` agar dapat disalin langsung.

### Ringkasan urutan

| § | Langkah | Hasil |
|:-:|---|---|
| 1 | XAMPP (sudah terpasang — verifikasi; panduan pasang untuk mesin baru) | Apache + MariaDB + PHP + phpMyAdmin berjalan |
| 2 | Cek PHP dan ekstensi, aktifkan `gd`/`intl`/`zip`, zona waktu, samakan PHP CLI | PHP 8.2.12 di CLI dan Apache |
| 3 | Buat proyek Laravel 12 di root repo, pasang paket Composer dan npm | Skeleton siap |
| 4 | Buat database dan user | `unpam_hospital`, `unpam_hospital_test`, `unpam_app` |
| 5 | `.env`, `.env.testing`, `APP_KEY` | Konfigurasi lokal |
| 6 | `migrate --seed`, `storage:link`, `npm run build` | Aplikasi berisi data contoh |
| 7 | VirtualHost Apache + `hosts` | `http://unpam-hospital.test` |
| 8 | Alternatif `php artisan serve` | `http://127.0.0.1:8000` |
| 9 | Izin folder | `storage`, `bootstrap/cache` dapat ditulis |
| 10 | Uji reset password (log/Mailpit) | Tautan reset terlihat di log |
| 11 | Operasional harian | Perintah artisan, Task Scheduler (opsional) |
| 12 | Backup dan restore | `scripts\backup.ps1`, `scripts\restore.ps1` |
| 13 | Troubleshooting | Tabel gejala–penyebab–solusi |
| 14 | Catatan produksi | Daftar periksa |

---

## 1. Persiapan: XAMPP dan Control Panel

### 1.1 Di mesin pemilik: XAMPP sudah terpasang — lewati pemasangan

XAMPP 8.2.12 sudah dipasang di **`C:\xampp3`**. Jangan menjalankan installer lagi dan jangan memakai `C:\xampp` (sisa lama). Cukup verifikasi bahwa komponen terpasang dan berjalan (perintah hanya membaca):

```powershell
& $PHP -v                                           # PHP 8.2.12 (cli) ... ZTS Visual C++ 2019 x64
& "$XAMPP\apache\bin\httpd.exe" -v                  # Apache/2.4.58 ...
& $MYSQL --version                                  # ... Distrib 10.4.32-MariaDB ...
Get-NetTCPConnection -LocalPort 80,443,3306 -State Listen |
  Select-Object LocalPort, @{n='Proses';e={(Get-Process -Id $_.OwningProcess).ProcessName}}
# harapan: httpd pada 80 dan 443, mysqld pada 3306
```

Jika `httpd` atau `mysqld` tidak tampil, jalankan lewat Control Panel (§1.3).

### 1.2 Hanya untuk mesin baru: memasang XAMPP 8.2.12

Lewati bagian ini di mesin pemilik. Di mesin lain:

1. Periksa folder lama dan bila perlu ganti namanya agar installer memakai folder bersih (installer dapat menolak folder yang tidak kosong):

   ```powershell
   Get-ChildItem C:\xampp -Force -ErrorAction SilentlyContinue | Select-Object Name, Mode, LastWriteTime
   Get-Process -Name xampp-control -ErrorAction SilentlyContinue | Stop-Process -Force
   Rename-Item -Path C:\xampp -NewName C:\xampp-old       # hanya jika isinya sisa; bila ada htdocs/mysql\data, tanyakan dulu
   ```
2. Jalankan installer `xampp-windows-x64-8.2.12-0-VS16-installer.exe` (mis. dari Downloads):

   ```powershell
   Start-Process "$env:USERPROFILE\Downloads\xampp-windows-x64-8.2.12-0-VS16-installer.exe"
   ```
3. Pasang ke `C:\xampp` (bukan `C:\Program Files`); komponen: **Apache**, **MySQL** (berisi MariaDB 10.4.32), **PHP**, **phpMyAdmin**; hapus centang FileZilla, Mercury, Tomcat, Perl, Webalizer, Fake Sendmail. Pada dialog Windows Firewall pilih **Private networks** saja.
4. Set `$XAMPP = "C:\xampp"` pada variabel sesi di atas, lalu verifikasi dengan perintah §1.1.

### 1.3 Menjalankan XAMPP Control Panel

```powershell
Start-Process "$XAMPP\xampp-control.exe" -Verb RunAs     # (Admin) disarankan agar dapat memulai/menghentikan service
```

Klik **Start** pada **Apache** dan **MySQL** bila belum hijau. Tombol **Config** pada baris Apache/MySQL membuka `httpd.conf`, `httpd-vhosts.conf`, `my.ini`, dan `php.ini` dengan cepat. Uji cepat: `http://localhost/` (dashboard XAMPP) dan `http://localhost/phpmyadmin`. Bila Apache tidak mau start karena port 80 terpakai, lihat §13 baris "Port 80 dipakai".

---

## 2. Cek PHP, Ekstensi, `php.ini`, dan Menyamakan PHP CLI

### 2.1 Versi dan ekstensi

Ekstensi wajib (00 B): `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `fileinfo`, `gd` (JPEG/PNG/WebP), `intl`, `zip`, `bcmath`, `curl`.

```powershell
& $PHP -v
& $PHP --ini                                     # php.ini yang dipakai harus C:\xampp3\php\php.ini
$need = 'pdo_mysql','mbstring','openssl','tokenizer','xml','ctype','fileinfo','gd','intl','zip','bcmath','curl'
$have = (& $PHP -m) | ForEach-Object { $_.Trim().ToLower() }
$need | ForEach-Object { '{0,-10} {1}' -f $_, $(if ($have -contains $_) { 'OK' } else { 'HILANG' }) }
```

Di mesin pemilik, sebelum diubah, hasil yang diharapkan: `gd`, `intl`, dan `zip` berstatus **HILANG**; `openssl` dan lainnya **OK** (`openssl` sudah termuat walau barisnya dikomentari di `php.ini`).

### 2.2 Mengaktifkan `gd`, `intl`, `zip` dan mengubah zona waktu (mesin pemilik)

Pada `C:\xampp3\php\php.ini` baris `;extension=gd`, `;extension=intl`, `;extension=zip` masih dikomentari (tanda `;` di depan = nonaktif) dan `date.timezone=Europe/Berlin`. Skrip berikut membuat cadangan `php.ini`, membuka komentar ketiga ekstensi, dan mengubah zona waktu menjadi `Asia/Jakarta`:

```powershell
$ini = "$XAMPP\php\php.ini"
Copy-Item $ini "$ini.bak-$(Get-Date -Format yyyyMMdd-HHmmss)"          # cadangan
$lines = Get-Content $ini
foreach ($e in 'gd','intl','zip') {
    $lines = $lines -replace "^;\s*extension=$e\s*$", "extension=$e"
}
$lines = $lines -replace '^\s*;?\s*date\.timezone\s*=.*$', 'date.timezone = Asia/Jakarta'
[System.IO.File]::WriteAllLines($ini, $lines)                          # UTF-8 tanpa BOM
Select-String -Path $ini -Pattern '^\s*extension=(gd|intl|zip)\s*$', '^\s*date\.timezone' | ForEach-Object { $_.Line }
```

Keluaran harus memuat `extension=gd`, `extension=intl`, `extension=zip`, dan `date.timezone = Asia/Jakarta`. (`extension=sodium` tidak dibutuhkan oleh blueprint; boleh dibiarkan nonaktif, atau diaktifkan dengan cara yang sama bila Anda menghendaki.) Pada mesin lain, ulangi dengan daftar penuh bila `HILANG`: `'pdo_mysql','mbstring','openssl','fileinfo','gd','intl','zip','bcmath','curl'`.

**Restart Apache** agar modul PHP Apache membaca `php.ini` baru: buka **`C:\xampp3\xampp-control.exe`** → **Stop** lalu **Start** pada baris Apache. (PHP CLI langsung memakai `php.ini` baru.) Verifikasi:

```powershell
(& $PHP -m) | Select-String -Pattern '^(gd|intl|zip)$'                  # ketiganya muncul
& $PHP -r "echo ini_get('date.timezone'), ' | ', ini_get('upload_max_filesize'), ' | ', ini_get('post_max_size'), ' | ', ini_get('memory_limit'), PHP_EOL;"
& $PHP -r "print_r(array_intersect_key(gd_info(), array_flip(['FreeType Support','JPEG Support','PNG Support','WebP Support'])));"
```

Keluaran yang benar: `Asia/Jakarta | 40M | 40M | 512M`; keempat dukungan GD bernilai `1` (FreeType dipakai untuk gambar placeholder, lihat [13 §9](13-data-contoh.md#9-gambar-placeholder-lokal)).

### 2.3 Batas unggah

Bagian 00 K membatasi unggahan gambar `max:2048` KB (banner `max:4096` KB). `upload_max_filesize` dan `post_max_size` harus **≥ 8M**; nilai bawaan XAMPP **40M** sudah memenuhi sehingga **tidak perlu diubah**. Jika di mesin lain nilainya lebih kecil, setel `upload_max_filesize = 8M` dan `post_max_size = 10M` dengan pola `-replace` yang sama seperti di §2.2.

### 2.4 Dua PHP di mesin ini (8.4 di PATH vs 8.2 XAMPP)

`php` tanpa path menjalankan 8.4.26 dari `C:\php\8.4`, sedangkan Apache memakai 8.2.12. Dependensi yang dipasang dengan PHP 8.4 dapat membutuhkan PHP ≥ 8.3 dan **gagal di Apache**. Samakan dengan salah satu cara berikut:

```powershell
# Cara A (disarankan): per sesi PowerShell — awali PATH dengan PHP XAMPP
$env:Path = "$XAMPP\php;" + $env:Path
php -v                                   # kini PHP 8.2.12
(Get-Command php).Source                 # C:\xampp3\php\php.exe
composer --version                       # baris terakhir menyebut PHP 8.2.12

# Cara B: panggil langsung PHP XAMPP tanpa mengubah PATH
& $PHP artisan about
```

Aturan praktis: **buka sesi PowerShell baru → tempel variabel sesi → jalankan Cara A → baru kerjakan proyek.** Jangan mengubah PATH sistem secara permanen (proyek PHP lain di mesin ini mungkin membutuhkan 8.4). Pengunci versi untuk Composer (`composer config platform.php 8.2.12`) ada di §3.

---

## 3. Composer, Pembuatan Proyek, dan Dependency Frontend

Folder `D:\Project Web RS` sudah berisi `.git` dan `docs/`, sedangkan `composer create-project` menolak folder yang tidak kosong. Karena itu proyek dibuat di **folder sementara** lalu isinya dipindahkan ke root repo.

### 3.1 Siapkan sesi

```powershell
# (variabel sesi dari bagian Prasyarat sudah ditempel)
$env:Path = "$XAMPP\php;" + $env:Path          # PHP 8.2.12 untuk composer
composer --version                              # harus menyebut PHP 8.2.12
node -v; npm -v                                 # v24.12.0 ; 11.6.2
git -C $APP status --short | Select-Object -First 5
$COMPOSER = "C:\composer\composer.phar"          # panggil composer.phar langsung (lihat peringatan di bawah)
```

> **Peringatan karakter `^` (terverifikasi 2026-10-02).** Di PowerShell, perintah `composer` dan `npm` dijalankan lewat `composer.bat`/`npm.cmd` (cmd.exe), dan **cmd.exe membuang karakter `^`**. Akibatnya `composer create-project "laravel/laravel:^12.0"` terbaca `12.0` dan memasang skeleton lama v12.0.0, bukan rilis 12.x terbaru (v12.12.2 per 2026-10-02). Karena itu panduan ini memanggil **`& $PHP $COMPOSER …`** untuk Composer (memakai PHP 8.2.12 XAMPP sekaligus menjaga `^`), dan untuk npm menulis versi mayor saja (mis. `bootstrap@5`), yang tetap disimpan sebagai `^5.x.y` di `package.json`.

### 3.2 Buat skeleton Laravel 12 di folder sementara lalu pindahkan

```powershell
$TMP = "D:\laravel-tmp"
if (Test-Path $TMP) { throw "Folder $TMP sudah ada. Ganti nama atau hapus lebih dulu." }
& $PHP $COMPOSER create-project "laravel/laravel:^12.0" $TMP --prefer-dist

# pindahkan semua isi KECUALI yang sudah ada di repo (.git, docs tidak ada di skeleton)
Get-ChildItem $TMP -Force | Where-Object { $_.Name -ne '.git' } | ForEach-Object {
    $target = Join-Path $APP $_.Name
    if (Test-Path -LiteralPath $target) { Write-Warning "DILEWATI (sudah ada di repo): $($_.Name)" }
    else { Move-Item -LiteralPath $_.FullName -Destination $target }
}

# jika .gitignore repo sudah ada, gabungkan baris dari skeleton
if (Test-Path "$TMP\.gitignore") {
    $mine = Get-Content "$APP\.gitignore"
    Get-Content "$TMP\.gitignore" | Where-Object { $_ -notin $mine } | Add-Content "$APP\.gitignore"
}

# hapus folder sementara hanya bila artisan sudah ada di root repo
if (Test-Path "$APP\artisan") { Remove-Item $TMP -Recurse -Force } else { throw "artisan tidak ditemukan di $APP" }

# skeleton membuat database SQLite bawaan; tidak dipakai (kita memakai MariaDB)
Remove-Item "$APP\database\database.sqlite" -ErrorAction SilentlyContinue
Get-ChildItem $APP -Force | Select-Object Name        # periksa: .git, docs, app, public, vendor, artisan, ...
```

### 3.3 Kunci versi PHP dan pasang paket Composer

```powershell
Set-Location $APP
& $PHP $COMPOSER config platform.php 8.2.12                 # dependensi dipilih agar cocok dengan PHP Apache (8.2.12)
& $PHP $COMPOSER require "spatie/laravel-permission:^6.25" "intervention/image:^3.11" "mews/purifier:^3.4" "barryvdh/laravel-dompdf:^3.1" "chillerlan/php-qrcode:^6.0"
& $PHP $COMPOSER remove laravel/pail --dev                  # pail butuh ekstensi pcntl yang tidak ada di Windows
& $PHP $COMPOSER check-platform-reqs                        # semua baris harus 'success'
& $PHP $COMPOSER show laravel/framework laravel/pint phpunit/phpunit fakerphp/faker | Select-String '^(name|versions)'
```

`laravel/pint`, `phpunit/phpunit` (^11), dan `fakerphp/faker` sudah ada di `require-dev` skeleton. `intervention/image` ^3 dipakai langsung dengan driver GD (`new ImageManager(new Driver())`) di `MediaService`; paket jembatan Laravel tidak diperlukan.

**Hapus pail dari skrip `composer dev`.** Skrip `dev` bawaan memanggil `php artisan pail`; ganti dengan hanya server dan Vite:

```powershell
$f = "$APP\composer.json"
$t = [System.IO.File]::ReadAllText($f)
$new = '"npx concurrently -c \"#93c5fd,#fdba74\" \"php artisan serve\" \"npm run dev\" --names=server,vite --kill-others"'
$t = [regex]::Replace($t, '"npx concurrently[^\r\n]*pail[^\r\n]*"', $new)
[System.IO.File]::WriteAllText($f, $t)
Select-String -Path $f -Pattern 'pail', 'concurrently'
```

Keluaran harus hanya memuat baris `concurrently` baru tanpa kata `pail`. Bila masih ada `pail`, buka `composer.json` dan ubah manual elemen kedua array `scripts.dev` menjadi baris `npx concurrently …` di atas.

### 3.4 Dependency frontend (npm)

```powershell
npm install
npm uninstall tailwindcss @tailwindcss/vite                       # hapus Tailwind bawaan skeleton Laravel 12
npm install bootstrap@5 @popperjs/core@2 bootstrap-icons@1 chart.js@4 quill@2 @fontsource/plus-jakarta-sans@5   # tanpa ^ (lihat peringatan §3.1)
npm install --save-dev sass
npm ls --depth=0
```

Ganti entri bawaan (Tailwind) dengan entri minimal Bootstrap agar `npm run build` langsung berhasil; desain lengkap dibuat pada [fase 1](14-tahapan-implementasi.md).

```powershell
Remove-Item "$APP\resources\css" -Recurse -Force -ErrorAction SilentlyContinue
New-Item -ItemType Directory -Force "$APP\resources\scss" | Out-Null

[System.IO.File]::WriteAllText("$APP\resources\scss\app.scss", @'
// Entri SCSS minimal; token warna dan komponen dibuat pada fase 1.
$primary: #0A4D8C;
@import "bootstrap/scss/bootstrap";
'@)

[System.IO.File]::WriteAllText("$APP\resources\js\app.js", @'
import './bootstrap';
import * as bootstrap from 'bootstrap';
import 'bootstrap-icons/font/bootstrap-icons.css';
import '@fontsource/plus-jakarta-sans/400.css';
import '@fontsource/plus-jakarta-sans/600.css';
import '@fontsource/plus-jakarta-sans/700.css';

window.bootstrap = bootstrap;
'@)

[System.IO.File]::WriteAllText("$APP\vite.config.js", @'
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/scss/app.scss', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    css: {
        preprocessorOptions: {
            scss: {
                quietDeps: true,
                silenceDeprecations: ['import', 'global-builtin', 'color-functions', 'mixed-decls'],
            },
        },
    },
    server: { watch: { ignored: ['**/storage/framework/views/**'] } },
});
'@)

# view bawaan masih merujuk resources/css/app.css -> arahkan ke SCSS baru
$w = "$APP\resources\views\welcome.blade.php"
$c = [System.IO.File]::ReadAllText($w) -replace 'resources/css/app\.css', 'resources/scss/app.scss'
[System.IO.File]::WriteAllText($w, $c)
```

(Penanganan peringatan deprecation Sass pada Bootstrap 5.3 lewat `silenceDeprecations` bersifat opsional; hapus blok itu bila versi Sass Anda tidak mengenalinya.)

Konfigurasi aplikasi dasar (zona waktu di `config/app.php`; locale lewat `.env` pada §5):

```powershell
$f = "$APP\config\app.php"
$t = [System.IO.File]::ReadAllText($f) -replace "'timezone'\s*=>\s*'UTC'", "'timezone' => 'Asia/Jakarta'"
[System.IO.File]::WriteAllText($f, $t)
Select-String -Path $f -Pattern "'timezone'"

Add-Content "$APP\.gitignore" "`n/backups`n.env.testing`n.env.restore-test"    # backup & env uji tidak masuk git
```

Entri di atas adalah **titik awal**: fase 1 menambah `resources/scss/admin.scss` dan `resources/js/admin.js` pada `input` Vite serta berkas SCSS/JS lain sesuai [10](10-struktur-folder.md).

Skeleton Laravel 12 tidak menyertakan folder `lang`. Jalankan `& $PHP artisan lang:publish`, lalu terjemahkan `auth.php`, `pagination.php`, `passwords.php`, `validation.php` ke Bahasa Indonesia di `lang/id` (ditambah `booking.php`, `messages.php`, dan `lang/id.json`) pada fase 0–1; sampai ada, `APP_LOCALE=id` jatuh ke bahasa Inggris.

Zona waktu koneksi database (00/03, A-33): tambahkan `'timezone' => '+07:00',` pada blok koneksi `mariadb` di `config/database.php` (setelah `'collation' => …`) agar kolom `TIMESTAMP` konsisten dengan `Asia/Jakarta` di mesin mana pun.

---

## 4. Membuat Database dan User

MariaDB XAMPP di mesin pemilik sudah berjalan, berisi database bawaan saja, dan akun `root` **tanpa password**. Aplikasi memakai **user khusus `unpam_app`** (bukan `root`) dengan hak hanya pada database proyek (00 B.2). Database: `unpam_hospital` (aplikasi), `unpam_hospital_test` (PHPUnit), dan `unpam_hospital_restore` (uji restore, [15 §9](15-pengujian-penerimaan.md)). Semuanya `utf8mb4` / `utf8mb4_unicode_ci`.

### 4.1 Buat password kuat untuk `unpam_app`

```powershell
$bytes = New-Object byte[] 36
[System.Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($bytes)
$pw = ([Convert]::ToBase64String($bytes) -replace '[^A-Za-z0-9]', '').Substring(0, 24)
$pw                                              # SALIN dan simpan di pengelola password; dipakai di .env
```

Password hanya huruf dan angka agar aman di SQL dan `.env` tanpa tanda kutip khusus.

### 4.2 Jalankan SQL lewat `mysql.exe` (root tanpa password)

```powershell
$sql = @"
CREATE DATABASE IF NOT EXISTS unpam_hospital         CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS unpam_hospital_test    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS unpam_hospital_restore CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'unpam_app'@'localhost'  IDENTIFIED BY '$pw';
CREATE USER IF NOT EXISTS 'unpam_app'@'127.0.0.1'  IDENTIFIED BY '$pw';
GRANT ALL PRIVILEGES ON unpam_hospital.*         TO 'unpam_app'@'localhost';
GRANT ALL PRIVILEGES ON unpam_hospital_test.*    TO 'unpam_app'@'localhost';
GRANT ALL PRIVILEGES ON unpam_hospital_restore.* TO 'unpam_app'@'localhost';
GRANT ALL PRIVILEGES ON unpam_hospital.*         TO 'unpam_app'@'127.0.0.1';
GRANT ALL PRIVILEGES ON unpam_hospital_test.*    TO 'unpam_app'@'127.0.0.1';
GRANT ALL PRIVILEGES ON unpam_hospital_restore.* TO 'unpam_app'@'127.0.0.1';
FLUSH PRIVILEGES;
"@
$sql | & $MYSQL -u root --default-character-set=utf8mb4
```

**Alternatif lewat phpMyAdmin** (`http://localhost/phpmyadmin` → tab **SQL**): tempel SQL yang sama dengan mengganti `$pw` oleh password pilihan Anda, lalu **Go**.

Verifikasi:

```powershell
"SHOW DATABASES LIKE 'unpam%'; SHOW GRANTS FOR 'unpam_app'@'localhost';" | & $MYSQL -u root
$env:MYSQL_PWD = $pw
& $MYSQL -h 127.0.0.1 -u unpam_app -e "SELECT VERSION() AS versi, @@character_set_server AS charset, @@innodb_lock_wait_timeout AS lock_wait;" unpam_hospital
Remove-Item Env:\MYSQL_PWD
```

`versi` harus memuat `10.4.32-MariaDB`. MariaDB ≥ 10.2.1 menegakkan `CHECK` dan mendukung kolom `STORED` generated (dipakai `appointments.active_flag`), sehingga migration dan query proyek kompatibel.

### 4.3 Akun `root`: dibiarkan tanpa password (K-12 default (a)) atau diberi password (opsi (b))

Keputusan **K-12** (17): default (a) — `root` MariaDB dibiarkan tanpa password bawaan XAMPP (hanya localhost) dan aplikasi tetap memakai user khusus `unpam_app`, sehingga langkah pemberian password di bawah **opsional** (hanya bila pemilik memilih (b)). `root` tanpa password hanya aman bila MariaDB tidak dapat dijangkau dari jaringan; **verifikasi itu selalu** (apa pun pilihan K-12):

```powershell
Select-String -Path "$XAMPP\mysql\bin\my.ini" -Pattern '^\s*(bind-address|skip-networking|port)\s*=' | ForEach-Object { $_.Line }
```

Bila tidak ada `bind-address`, tambahkan `bind-address=127.0.0.1` pada bagian `[mysqld]` di `my.ini` lalu restart MySQL dari Control Panel; pastikan firewall tidak membuka port 3306. **Hanya untuk opsi (b)** — memberi `root` password:

```powershell
$rootPw = Read-Host "Password baru untuk root MariaDB"
$sql = "ALTER USER 'root'@'localhost' IDENTIFIED BY '$rootPw'; ALTER USER 'root'@'127.0.0.1' IDENTIFIED BY '$rootPw'; ALTER USER 'root'@'::1' IDENTIFIED BY '$rootPw'; FLUSH PRIVILEGES;"
$sql | & $MYSQL -u root
```

Setelah itu phpMyAdmin bawaan (`auth_type = 'config'` tanpa password) tidak dapat masuk. Cadangkan lalu ubah ke login cookie:

```powershell
$cfg = "$XAMPP\phpMyAdmin\config.inc.php"
Copy-Item $cfg "$cfg.bak"
(Get-Content $cfg) -replace "\['auth_type'\]\s*=\s*'config';", "['auth_type'] = 'cookie';" | Set-Content $cfg
```

Mulai saat itu login phpMyAdmin dengan `root` + password baru, dan semua perintah `mysql.exe -u root` di panduan ini (mis. §4.2) ditambah `-p`. Pemakaian aplikasi sehari-hari tidak butuh `root` (cukup `unpam_app`). Di produksi `root` wajib berpassword (§14 butir 13).

### 4.4 Penyetelan opsional: `innodb_lock_wait_timeout` (rekomendasi blueprint, 01 A-40)

Bawaan MariaDB 50 detik membuat permintaan yang menunggu kunci baris terasa "menggantung". Blueprint merekomendasikan 10 detik (booking memakai `lockForUpdate()` dan `attempts: 3`). Perubahan ini opsional; ia mengubah `my.ini` XAMPP sehingga cadangkan lebih dulu:

```powershell
$my = "$XAMPP\mysql\bin\my.ini"
Copy-Item $my "$my.bak-$(Get-Date -Format yyyyMMdd-HHmmss)"
if (-not (Select-String -Path $my -Pattern '^\s*innodb_lock_wait_timeout' -Quiet)) {
    $l = [System.Collections.Generic.List[string]](Get-Content $my)
    $i = $l.FindIndex({ param($s) $s -match '^\[mysqld\]' })
    if ($i -lt 0) { throw "Bagian [mysqld] tidak ditemukan di $my" }
    $l.Insert($i + 1, 'innodb_lock_wait_timeout=10')
    [System.IO.File]::WriteAllLines($my, $l)
}
# restart MySQL dari Control Panel (Stop lalu Start), kemudian:
"SELECT @@innodb_lock_wait_timeout;" | & $MYSQL -u root -N                  # 10
```

---

## 5. File `.env`, `.env.testing`, dan `APP_KEY`

### 5.1 `.env` lokal (semua kunci 00 B.2)

Bila repo sudah memuat `.env.example` dan `.env.testing.example` ([10](10-struktur-folder.md)), salin dari sana (`Copy-Item "$APP\.env.example" "$APP\.env"`) lalu isi nilainya; templat di bawah adalah padanannya. Buat dari templat di bawah; placeholder `__DB_PASSWORD__` diganti password `unpam_app` dari §4.1 (variabel `$pw` masih ada bila Anda memakai sesi yang sama):

```powershell
$envTemplate = @'
APP_NAME="UNPAM HOSPITAL"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://unpam-hospital.test

APP_LOCALE=id
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=id_ID
APP_MAINTENANCE_DRIVER=file
PHP_CLI_SERVER_WORKERS=4
BCRYPT_ROUNDS=12

LOG_CHANNEL=daily
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug
LOG_DAILY_DAYS=14

DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=unpam_hospital
DB_USERNAME=unpam_app
DB_PASSWORD=__DB_PASSWORD__
DB_TIMEZONE=+07:00

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=false

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
CACHE_STORE=database

MAIL_MAILER=log
MAIL_SCHEME=null
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS="info@unpamhospital.test"
MAIL_FROM_NAME="${APP_NAME}"

VITE_APP_NAME="${APP_NAME}"

# Opsional (hanya local/testing): password akun demo. Kosong = Demo12345. DILARANG di produksi.
DEMO_PASSWORD=
'@
[System.IO.File]::WriteAllText("$APP\.env", $envTemplate.Replace('__DB_PASSWORD__', $pw))
```

Penjelasan kunci utama (lokal vs produksi, 00 B.2): `APP_ENV`/`APP_DEBUG` = `local`/`true` (produksi `production`/`false`); `DB_CONNECTION=mariadb` (Laravel 12 mendukung MariaDB 10.3+); `DB_USERNAME=unpam_app`; `SESSION_DRIVER=database`; `SESSION_SECURE_COOKIE=false` (produksi `true`); `QUEUE_CONNECTION=sync` (produksi `database` + worker); `MAIL_MAILER=log` (opsional `smtp` ke Mailpit `127.0.0.1:1025`, §10); `LOG_CHANNEL=daily`. Header CSP dinonaktifkan/longgar pada lokal (Vite dev server) dan diaktifkan ketat di produksi. Tidak ada kredensial yang ditulis ke kode; `.env` tidak boleh di-commit (sudah ada di `.gitignore` skeleton).

### 5.2 `.env.testing` (basis data uji terpisah)

```powershell
$testTemplate = @'
APP_NAME="UNPAM HOSPITAL"
APP_ENV=testing
APP_KEY=
APP_DEBUG=true
APP_URL=http://unpam-hospital.test
APP_LOCALE=id
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=id_ID
BCRYPT_ROUNDS=4
LOG_CHANNEL=single
LOG_LEVEL=debug

DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=unpam_hospital_test
DB_USERNAME=unpam_app
DB_PASSWORD=__DB_PASSWORD__
DB_TIMEZONE=+07:00

SESSION_DRIVER=array
CACHE_STORE=array
QUEUE_CONNECTION=sync
MAIL_MAILER=array
FILESYSTEM_DISK=local
'@
[System.IO.File]::WriteAllText("$APP\.env.testing", $testTemplate.Replace('__DB_PASSWORD__', $pw))
```

Agar tes **tidak pernah** menyentuh database aplikasi, paksa nama database uji di `phpunit.xml` (di dalam blok `<php>`; sisakan baris `<env>` bawaan lainnya):

```xml
<env name="DB_CONNECTION" value="mariadb" force="true"/>
<env name="DB_DATABASE" value="unpam_hospital_test" force="true"/>
```

### 5.3 Application key

```powershell
Set-Location $APP
& $PHP artisan key:generate                       # mengisi APP_KEY di .env
& $PHP artisan key:generate --env=testing         # kunci terpisah untuk .env.testing
Select-String -Path "$APP\.env" -Pattern '^APP_KEY=' | ForEach-Object { $_.Line.Substring(0, 18) + '…' }
```

**Peringatan `APP_KEY`:**

- Simpan `APP_KEY` yang **asli** di tempat aman terpisah dari repo dan dari backup database (pengelola password atau berkas terenkripsi).
- Kunci dipakai mengenkripsi nomor BPJS dan nomor polis (`bpjs_number`, `payment_reference`), cookie, dan sesi. **Jika kunci berubah atau hilang, data terenkripsi tidak dapat dibaca lagi** dan semua pengguna terputus (lihat §13).
- `key:generate` di lokal menimpa kunci lama **tanpa konfirmasi**. Jangan menjalankannya lagi setelah ada data (`--show` hanya menampilkan kunci baru tanpa menulis).
- Backup database tanpa `APP_KEY` yang sama tidak lengkap (§12).

---

## 6. Migration, Seeder, Storage Link, dan Build Aset

### 6.1 Migration dan seeder

```powershell
Set-Location $APP
& $PHP artisan optimize:clear
& $PHP artisan migrate --seed
```

Pada `APP_ENV=local`, `DatabaseSeeder` menjalankan seeder inti (peran, pengaturan, halaman) **dan** `DemoSeeder` (data contoh, [13](13-data-contoh.md)); di akhir, konsol menampilkan tabel akun demo beserta password-nya (default `Demo12345` atau `DEMO_PASSWORD`; keputusan K-11 di 17). **Catat/abaikan hanya di lokal**; password demo tidak boleh dipakai di produksi. Seeder demo membuat ±83 gambar placeholder lewat GD sehingga dapat memakan 30–60 detik.

Untuk mengulang dari awal (menghapus **semua** tabel dan isi database `unpam_hospital`; hanya untuk lokal):

```powershell
& $PHP artisan migrate:fresh --seed
```

Pemeriksaan hasil seeding ([13 §11](13-data-contoh.md#11-invarian-setelah-seeding-dan-cara-memeriksanya)):

```powershell
Get-Content -Raw "$APP\scripts\sql\seed-invariants.sql" | & $MYSQL -u unpam_app -p --default-character-set=utf8mb4 --table unpam_hospital
```

### 6.2 Storage link

```powershell
& $PHP artisan storage:link
Get-Item "$APP\public\storage" | Select-Object Name, LinkType, Target        # LinkType = Junction
```

Di Windows perintah ini membuat **junction** (`mklink /J`) dari `public\storage` ke `storage\app\public`; tidak membutuhkan hak Administrator. Bila muncul "The [public/storage] link already exists" tetapi gambar tidak tampil, hapus junction lama **tanpa menghapus isinya**, lalu buat ulang:

```powershell
cmd /c rmdir "$APP\public\storage"        # rmdir pada junction hanya menghapus tautannya
& $PHP artisan storage:link
```

### 6.3 Build aset

```powershell
npm run build                              # hasil di public\build (dipakai Apache/artisan serve)
```

Selama pengembangan frontend gunakan Vite dev server (membuat berkas `public\hot`; hentikan dengan Ctrl+C):

```powershell
npm run dev                                # http://localhost:5173 ; hot reload
# atau server PHP + Vite sekaligus (tanpa pail):
composer dev
```

Jika `npm run dev` berhenti tidak wajar dan halaman terus mencari server Vite, hapus sisa berkas: `Remove-Item "$APP\public\hot" -ErrorAction SilentlyContinue`. Setelah mengubah aset untuk dipakai tanpa dev server, jalankan lagi `npm run build`.

### 6.4 Pemeriksaan akhir

```powershell
& $PHP artisan about
& $PHP artisan route:list --except-vendor | Select-Object -First 15
& $PHP artisan test --exclude-group=concurrency
```

`artisan about` harus menampilkan PHP 8.2.12, `Environment: local`, `Timezone: Asia/Jakarta`, `Locale: id`, driver database `mariadb`.

---

## 7. VirtualHost Apache dan URL Lokal

Tujuan: `http://unpam-hospital.test` mengarah ke `D:\Project Web RS\public` (path berspasi **wajib dikutip**).

### 7.1 Verifikasi `mod_rewrite` dan `Include` vhost

Di mesin pemilik `httpd.conf` sudah memuat `mod_rewrite` dan `Include conf/extra/httpd-vhosts.conf`; cukup verifikasi (kedua baris **tanpa** `#` di depan):

```powershell
Select-String -Path "$XAMPP\apache\conf\httpd.conf" -Pattern '^\s*#?\s*LoadModule rewrite_module', '^\s*#?\s*Include\s+"?conf/extra/httpd-vhosts\.conf' | ForEach-Object { $_.Line }
```

Jika salah satunya masih diawali `#` (mesin lain), buka komentarnya (cadangan dibuat dulu):

```powershell
$conf = "$XAMPP\apache\conf\httpd.conf"
Copy-Item $conf "$conf.bak-$(Get-Date -Format yyyyMMdd-HHmmss)"
$l = Get-Content $conf
$l = $l -replace '^\s*#\s*(LoadModule rewrite_module .*)$', '$1'
$l = $l -replace '^\s*#\s*(Include\s+"?conf/extra/httpd-vhosts\.conf"?)\s*$', '$1'
[System.IO.File]::WriteAllLines($conf, $l)
```

### 7.2 Blok `<VirtualHost>`

Periksa apakah sudah ada vhost aktif di berkas (default XAMPP hanya berisi contoh yang dikomentari):

```powershell
$vh = "$XAMPP\apache\conf\extra\httpd-vhosts.conf"
Select-String -Path $vh -Pattern '^\s*<VirtualHost' | ForEach-Object { $_.Line }      # kosong = belum ada vhost aktif
```

Tambahkan dua blok: vhost **`localhost`** dipertahankan sebagai vhost pertama (dashboard XAMPP dan phpMyAdmin tetap dapat dibuka), lalu vhost proyek. Jika sudah ada vhost `localhost` aktif, hilangkan blok pertama dari skrip.

```powershell
Copy-Item $vh "$vh.bak-$(Get-Date -Format yyyyMMdd-HHmmss)"
$x = $XAMPP.Replace('\', '/')
$block = @"

# === UNPAM HOSPITAL (lokal) ===
# Vhost pertama = default; mempertahankan http://localhost (dashboard XAMPP, phpMyAdmin)
<VirtualHost *:80>
    ServerName localhost
    DocumentRoot "$x/htdocs"
    <Directory "$x/htdocs">
        Require all granted
    </Directory>
</VirtualHost>

<VirtualHost *:80>
    ServerName unpam-hospital.test
    DocumentRoot "D:/Project Web RS/public"
    <Directory "D:/Project Web RS/public">
        Options FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    ErrorLog "logs/unpam-hospital-error.log"
    CustomLog "logs/unpam-hospital-access.log" common
</VirtualHost>
"@
Add-Content -Path $vh -Value $block
& "$XAMPP\apache\bin\httpd.exe" -t                 # harus: Syntax OK
```

Catatan: gunakan garis miring `/` dan kutip ganda pada path; `AllowOverride All` wajib agar `public\.htaccess` Laravel (rewrite ke `index.php`) dibaca; `Require all granted` mengizinkan akses (sintaks Apache 2.4).

### 7.3 Berkas `hosts` **(Admin)**

```powershell
if (-not ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    throw "Jalankan PowerShell sebagai Administrator."
}
$hosts = "$env:SystemRoot\System32\drivers\etc\hosts"
if (-not (Select-String -Path $hosts -Pattern 'unpam-hospital\.test' -Quiet)) {
    Add-Content -Path $hosts -Value "`r`n127.0.0.1`tunpam-hospital.test"
}
ipconfig /flushdns | Out-Null
Select-String -Path $hosts -Pattern 'unpam-hospital' | ForEach-Object { $_.Line }
[System.Net.Dns]::GetHostAddresses('unpam-hospital.test')          # 127.0.0.1
```

### 7.4 Restart Apache dan uji

Buka `C:\xampp3\xampp-control.exe` → **Stop** lalu **Start** pada Apache (pastikan tidak ada pesan galat berwarna merah). Uji:

```powershell
(Invoke-WebRequest http://unpam-hospital.test -UseBasicParsing).StatusCode                 # 200
(Invoke-WebRequest http://unpam-hospital.test/dokter -UseBasicParsing).StatusCode          # 200 (membuktikan rewrite; 404 = lihat §13)
(Invoke-WebRequest http://localhost/ -UseBasicParsing).StatusCode                          # 200 (vhost localhost tetap hidup)
```

### 7.5 URL lokal

| Fungsi | URL |
|---|---|
| Situs publik | `http://unpam-hospital.test` |
| Login pasien / registrasi | `http://unpam-hospital.test/masuk` · `http://unpam-hospital.test/daftar` |
| Login admin | `http://unpam-hospital.test/admin/login` |
| phpMyAdmin | `http://localhost/phpmyadmin` |
| Dashboard XAMPP | `http://localhost/` |
| Alternatif `artisan serve` (§8) | `http://127.0.0.1:8000` |
| Mailpit (opsional, §10) | `http://localhost:8025` |
| Vite dev server | `http://localhost:5173` |

Pastikan `APP_URL` di `.env` sama persis dengan URL yang Anda buka (`http://unpam-hospital.test`), lalu `& $PHP artisan config:clear`.

---

## 8. Alternatif: `php artisan serve`

Tanpa Apache/VirtualHost/`hosts` (mis. untuk uji cepat). Server bawaan PHP tidak memakai `.htaccess`, sehingga tidak ada masalah rewrite.

```powershell
Set-Location $APP
(Get-Content "$APP\.env") -replace '^APP_URL=.*$', 'APP_URL=http://127.0.0.1:8000' | Set-Content "$APP\.env"
& $PHP artisan config:clear
& $PHP artisan serve --host=127.0.0.1 --port=8000        # hentikan dengan Ctrl+C
```

Buka `http://127.0.0.1:8000` dan `http://127.0.0.1:8000/admin/login`. Bila port 8000 terpakai, ganti `--port=8001` dan `APP_URL` yang sesuai. Untuk kembali ke VirtualHost, ubah `APP_URL` menjadi `http://unpam-hospital.test` lalu `config:clear`. Cookie sesi terpisah per host, jadi login di `127.0.0.1:8000` tidak berlaku di `unpam-hospital.test`. `storage:link` dan `npm run build` tetap diperlukan.

---

## 9. Izin Folder `storage` dan `bootstrap\cache`

Laravel menulis log, cache, sesi, tampilan terkompilasi, dan unggahan ke `storage\` serta cache konfigurasi ke `bootstrap\cache\`. Pada Windows tidak ada `chmod`; yang menentukan adalah akun yang menjalankan Apache.

**Apache dari XAMPP Control Panel** berjalan sebagai **pengguna yang sedang login** (itu sebabnya tidak perlu pengaturan tambahan). **Apache sebagai service Windows** berjalan sebagai akun service (mis. `SYSTEM`). Periksa:

```powershell
Get-CimInstance Win32_Process -Filter "Name='httpd.exe'" |
  ForEach-Object { Invoke-CimMethod -InputObject $_ -MethodName GetOwner } |
  Select-Object Domain, User | Sort-Object User -Unique
```

Uji tulis pada setiap folder penting (tanpa mengubah apa pun selain berkas uji sementara):

```powershell
foreach ($d in 'storage\logs','storage\framework\cache','storage\framework\sessions','storage\framework\views','storage\app\public','storage\app\private','bootstrap\cache') {
    $p = Join-Path $APP $d
    if (-not (Test-Path $p)) { New-Item -ItemType Directory -Path $p -Force | Out-Null }
    $t = Join-Path $p '.tulis-uji'
    try { Set-Content $t 'ok'; Remove-Item $t; '{0,-34} dapat ditulis' -f $d } catch { '{0,-34} GAGAL' -f $d }
}
```

Jika ada yang `GAGAL`, atau Apache berjalan sebagai service dengan akun selain pemilik folder, beri hak **Modify** kepada akun yang relevan **(Admin)**:

```powershell
icacls "$APP\storage"         /grant "Users:(OI)(CI)M" /T
icacls "$APP\bootstrap\cache" /grant "Users:(OI)(CI)M" /T
# atau akun spesifik, mis. service LocalService:
# icacls "$APP\storage" /grant "NT AUTHORITY\LocalService:(OI)(CI)M" /T
icacls "$APP\storage"                                    # tinjau hasil
```

(`(OI)(CI)` = berlaku untuk berkas dan subfolder baru; `M` = Modify; `/T` = rekursif.) Berkas log yang dibuat oleh Apache dan CLI dengan akun berbeda sering menimbulkan "Permission denied" pada `storage\logs\laravel-YYYY-MM-DD.log`; pemberian hak ke grup `Users` di atas mencegahnya.

---

## 10. Uji Reset Password Lokal

Tautan reset **tidak pernah ditampilkan di halaman**; ia hanya terkirim lewat surel. Di lokal ada dua cara.

### 10.1 Cara 1 — `MAIL_MAILER=log` (bawaan, tanpa perangkat tambahan)

1. Pastikan `.env`: `MAIL_MAILER=log`, `LOG_CHANNEL=daily`, lalu `& $PHP artisan config:clear`.
2. Buka `http://unpam-hospital.test/lupa-password`, isi email akun demo (`pasien@unpamhospital.test`), kirim. Halaman menjawab generik ("Jika email terdaftar, tautan untuk mengatur ulang password telah dikirim. Periksa kotak masuk Anda."), apa pun alamat yang diisi.
3. Ambil tautan dari log harian `storage\logs\laravel-YYYY-MM-DD.log`. Surel di log berupa pesan MIME mentah dengan pembungkus baris `=` dan `=3D` (quoted-printable), sehingga tautan perlu dirapikan:

   ```powershell
   $log  = "$APP\storage\logs\laravel-$(Get-Date -Format yyyy-MM-dd).log"
   $text = (Get-Content $log -Raw) -replace '=\r?\n', '' -replace '=3D', '='
   [regex]::Matches($text, 'http://unpam-hospital\.test/reset-password/[A-Za-z0-9]+\?email=[^\s"<>]+') |
       Select-Object -Last 1 | ForEach-Object Value
   ```

   Jika `APP_URL` Anda berbeda (mis. `http://127.0.0.1:8000`), sesuaikan awalan pada pola. Bila perintah tidak menemukan apa pun, tampilkan akhir log: `Get-Content $log -Tail 120`.
4. Salin tautan ke peramban (host yang sama dengan `APP_URL`), isi password baru (min. 8 karakter, huruf + angka), lalu login. Tautan bersifat sekali pakai dan kedaluwarsa 60 menit (bawaan `config/auth.php`); permintaan ke-4 dalam 10 menit memicu throttle `password-reset`.

### 10.2 Cara 2 — Mailpit (opsional; menampilkan surel di antarmuka web)

1. Unduh binary Windows `mailpit-windows-amd64.zip` dari halaman rilis resmi Mailpit (`github.com/axllent/mailpit/releases`), ekstrak ke `C:\tools\mailpit`.
2. Jalankan (biarkan jendela terbuka; terima dialog firewall untuk **Private networks**):

   ```powershell
   Start-Process "C:\tools\mailpit\mailpit.exe"           # SMTP 127.0.0.1:1025 ; UI http://localhost:8025
   Get-NetTCPConnection -LocalPort 1025,8025 -State Listen | Select-Object LocalPort
   ```
3. Ubah `.env` lalu bersihkan cache konfigurasi:

   ```powershell
   $e = Get-Content "$APP\.env"
   $e = $e -replace '^MAIL_MAILER=.*$', 'MAIL_MAILER=smtp' -replace '^MAIL_HOST=.*$', 'MAIL_HOST=127.0.0.1' -replace '^MAIL_PORT=.*$', 'MAIL_PORT=1025'
   [System.IO.File]::WriteAllLines("$APP\.env", $e)
   & $PHP artisan config:clear
   ```
4. Kirim permintaan reset; buka `http://localhost:8025`, buka surel, klik tombol/tautan reset. Kembalikan `MAIL_MAILER=log` bila tidak lagi memakai Mailpit.

Surel notifikasi pasien lain (konfirmasi, pembatalan, perubahan jadwal — kanal `mail` menurut 00 F.8/K-13 — dan email selamat datang K-22) di lokal juga masuk log harian yang sama (`laravel-YYYY-MM-DD.log`, quoted-printable) atau Mailpit; kanal `database` tidak memakai surel dan terlihat sebagai banner di dashboard pasien.

Catatan: mengirim surel ke SMTP produksi **tidak** diuji di lokal; surel produksi termasuk pengembangan lanjutan ([00 N](00-keputusan-inti.md), §14).

---

## 11. Operasional Harian

Fitur inti **tidak** bergantung pada Task Scheduler/cron (00 B.2): sesi praktik dibangkitkan otomatis maksimal sekali per hari saat halaman jadwal publik atau wizard dibuka. Perintah berikut tersedia untuk dijalankan manual kapan saja.

| Perintah | Fungsi | Kapan |
|---|---|---|
| `& $PHP artisan sessions:generate` | Membangkitkan `practice_sessions` dari templat sampai hari ini + `booking.window_days` (idempoten) | Setelah mengubah templat/pengecualian lewat basis data langsung, atau bila halaman jadwal publik tampak kosong |
| `& $PHP artisan sessions:generate --days=60` | Sama, rentang 60 hari | Persiapan jadwal jangka panjang |
| `& $PHP artisan appointments:mark-no-show` | Menandai `pending`/`confirmed` pada sesi yang sudah berakhir sebagai `no_show` (aktor sistem). Tanpa `--date` memproses **semua** sesi yang waktu selesainya sudah lewat hingga saat ini (00 F.8) | Akhir hari praktik |
| `& $PHP artisan appointments:mark-no-show --date=YYYY-MM-DD` | Hanya sesi pada tanggal itu | Koreksi tanggal tertentu |
| `& $PHP artisan media:prune --dry-run` | Melaporkan media yatim (baris `model_id` NULL > 24 jam, file tak tercatat) **tanpa** menghapus | Pemeriksaan berkala |
| `& $PHP artisan media:prune` | Menghapus media yatim | Setelah `--dry-run` terlihat wajar |
| `& $PHP artisan queue:work --tries=3` | Hanya bila `QUEUE_CONNECTION=database`; lokal memakai `sync` sehingga tidak perlu | Produksi |
| `& $PHP artisan optimize:clear` | Membersihkan cache konfigurasi, rute, tampilan, aplikasi | Setelah mengubah `.env` atau config |

### 11.1 Windows Task Scheduler (opsional, tidak wajib)

Untuk menjalankan otomatis tanpa membuka PowerShell. Contoh tiga tugas milik pengguna saat ini (tidak butuh Administrator; berjalan saat Anda login):

```powershell
$php  = "$XAMPP\php\php.exe"
$wd   = $APP
$user = "$env:USERDOMAIN\$env:USERNAME"

$tasks = @(
    @{ Name = 'UnpamHospital-GenerateSessions'; Time = '02:00'; Exe = $php; Args = 'artisan sessions:generate' },
    @{ Name = 'UnpamHospital-MarkNoShow';       Time = '23:30'; Exe = $php; Args = 'artisan appointments:mark-no-show' },
    @{ Name = 'UnpamHospital-Backup';           Time = '01:00'; Exe = 'powershell.exe'; Args = "-NoProfile -ExecutionPolicy Bypass -File `"$APP\scripts\backup.ps1`"" }
)
foreach ($t in $tasks) {
    $action  = New-ScheduledTaskAction -Execute $t.Exe -Argument $t.Args -WorkingDirectory $wd
    $trigger = New-ScheduledTaskTrigger -Daily -At $t.Time
    Register-ScheduledTask -TaskName $t.Name -Action $action -Trigger $trigger -User $user -Description "UNPAM HOSPITAL: $($t.Name)" -Force | Out-Null
}
Get-ScheduledTask -TaskName 'UnpamHospital-*' | Select-Object TaskName, State
```

Hapus tugas bila tidak diperlukan lagi: `Unregister-ScheduledTask -TaskName 'UnpamHospital-*' -Confirm:$false`. Tugas Task Scheduler hanya berjalan bila komputer menyala; fitur inti tetap berfungsi tanpanya.

---

## 12. Backup dan Restore

Yang harus dicadangkan: (1) **basis data** (`mysqldump`), (2) **berkas unggahan** (`storage\app\public` dan `storage\app\private`), dan (3) **`APP_KEY`** — disimpan **terpisah** dari (1)/(2). Folder `backups\` sudah ada di `.gitignore` (§3.4). Cadangan memuat data pribadi pasien (nomor BPJS/polis terenkripsi, tetapi nama, telepon, alamat tidak) sehingga perlakukan sebagai data sensitif.

### 12.1 `scripts\backup.ps1`

```powershell
<#
.SYNOPSIS  Cadangan basis data dan berkas unggahan UNPAM HOSPITAL.
.DESCRIPTION
  Menghasilkan di folder backups\ :
    <db>-YYYYMMDD-HHmmss.sql       dump mysqldump (utf8mb4, --single-transaction --routines)
    storage-public-<stempel>.zip   isi storage\app\public
    storage-private-<stempel>.zip  isi storage\app\private
    SHA256SUMS-<stempel>.txt       checksum berkas di atas
  APP_KEY TIDAK ikut dicadangkan; simpan terpisah dan aman.
.EXAMPLE
  powershell -NoProfile -ExecutionPolicy Bypass -File scripts\backup.ps1 -KeepDays 30
#>
[CmdletBinding()]
param(
    [string]$ProjectRoot = (Split-Path -Parent $PSScriptRoot),
    [string]$XamppRoot   = 'C:\xampp3',     # mesin lain: C:\xampp
    [int]$KeepDays       = 30                # 0 = jangan hapus cadangan lama
)
$ErrorActionPreference = 'Stop'

function Get-EnvValue([string]$Key) {
    $line = Select-String -Path (Join-Path $ProjectRoot '.env') -Pattern "^\s*$Key\s*=" | Select-Object -First 1
    if (-not $line) { return $null }
    ($line.Line -split '=', 2)[1].Trim().Trim('"').Trim("'")
}

$db     = Get-EnvValue 'DB_DATABASE'
$user   = Get-EnvValue 'DB_USERNAME'
$pass   = Get-EnvValue 'DB_PASSWORD'
$dbHost = Get-EnvValue 'DB_HOST'; if (-not $dbHost) { $dbHost = '127.0.0.1' }
$port   = Get-EnvValue 'DB_PORT'; if (-not $port)   { $port   = '3306' }
if (-not $db -or -not $user) { throw '.env tidak memuat DB_DATABASE/DB_USERNAME.' }

$dump = Join-Path $XamppRoot 'mysql\bin\mysqldump.exe'
if (-not (Test-Path $dump)) { throw "mysqldump tidak ditemukan: $dump" }

$stamp  = Get-Date -Format 'yyyyMMdd-HHmmss'
$outDir = Join-Path $ProjectRoot 'backups'
New-Item -ItemType Directory -Force -Path $outDir | Out-Null
$sqlFile = Join-Path $outDir "$db-$stamp.sql"

Write-Host "[1/3] Dump basis data '$db' ..."
$env:MYSQL_PWD = $pass                      # kata sandi tidak muncul di baris perintah
try {
    & $dump "--host=$dbHost" "--port=$port" "--user=$user" `
        --single-transaction --routines --default-character-set=utf8mb4 `
        "--result-file=$sqlFile" $db
    if ($LASTEXITCODE -ne 0) { throw "mysqldump gagal (kode $LASTEXITCODE)." }
} finally { Remove-Item Env:\MYSQL_PWD -ErrorAction SilentlyContinue }
if ((Get-Item $sqlFile).Length -lt 1024) { throw 'Berkas dump terlalu kecil; periksa kredensial/basis data.' }

Write-Host '[2/3] Arsipkan berkas unggahan ...'
$zips = @()
foreach ($part in 'public', 'private') {
    $src = Join-Path $ProjectRoot "storage\app\$part"
    $zip = Join-Path $outDir "storage-$part-$stamp.zip"
    if ((Test-Path $src) -and (Get-ChildItem $src -Recurse -Force -File | Select-Object -First 1)) {
        Compress-Archive -Path (Join-Path $src '*') -DestinationPath $zip -CompressionLevel Optimal
        $zips += $zip
    } else {
        Write-Warning "storage\app\$part kosong atau tidak ada; arsip dilewati."
    }
}

Write-Host '[3/3] Checksum ...'
$sumFile = Join-Path $outDir "SHA256SUMS-$stamp.txt"
(@($sqlFile) + $zips) | Get-FileHash -Algorithm SHA256 |
    ForEach-Object { '{0}  {1}' -f $_.Hash, (Split-Path $_.Path -Leaf) } |
    Set-Content -Path $sumFile -Encoding ascii

if ($KeepDays -gt 0) {                      # retensi: hanya berkas bernama pola cadangan
    Get-ChildItem $outDir -File | Where-Object {
        $_.LastWriteTime -lt (Get-Date).AddDays(-$KeepDays) -and
        $_.Name -match '^(.+-\d{8}-\d{6}\.(sql|zip)|SHA256SUMS-\d{8}-\d{6}\.txt)$'
    } | Remove-Item -Force
}

Write-Host "`nSelesai. Berkas di ${outDir}:" -ForegroundColor Green
Get-ChildItem $outDir -File | Where-Object Name -like "*$stamp*" | Select-Object Name, @{n='KB';e={[math]::Round($_.Length/1KB,1)}}
Write-Host 'INGAT: simpan APP_KEY terpisah dan aman. Tanpa APP_KEY yang sama, nomor BPJS/polis tidak dapat dibaca setelah restore.' -ForegroundColor Yellow
```

### 12.2 `scripts\restore.ps1`

```powershell
<#
.SYNOPSIS  Memulihkan dump SQL (dan opsional berkas unggahan) UNPAM HOSPITAL.
.EXAMPLE   # ke database uji (aman)
  powershell -NoProfile -ExecutionPolicy Bypass -File scripts\restore.ps1 `
     -SqlFile "D:\Project Web RS\backups\unpam_hospital-20261002-010000.sql" -Database unpam_hospital_restore
.EXAMPLE   # menimpa database aktif (data lama DITIMPA; pra-cadangan otomatis dibuat)
  powershell -NoProfile -ExecutionPolicy Bypass -File scripts\restore.ps1 `
     -SqlFile "...sql" -PublicZip "...storage-public-....zip" -PrivateZip "...storage-private-....zip" -Force
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory)][string]$SqlFile,
    [string]$Database,                       # default: DB_DATABASE dari .env
    [string]$PublicZip,                      # opsional: pulihkan storage\app\public
    [string]$PrivateZip,                     # opsional: pulihkan storage\app\private
    [string]$ProjectRoot = (Split-Path -Parent $PSScriptRoot),
    [string]$XamppRoot   = 'C:\xampp3',      # mesin lain: C:\xampp
    [switch]$Force                           # wajib bila target = database aktif di .env
)
$ErrorActionPreference = 'Stop'

function Get-EnvValue([string]$Key) {
    $line = Select-String -Path (Join-Path $ProjectRoot '.env') -Pattern "^\s*$Key\s*=" | Select-Object -First 1
    if (-not $line) { return $null }
    ($line.Line -split '=', 2)[1].Trim().Trim('"').Trim("'")
}

$envDb  = Get-EnvValue 'DB_DATABASE'
$user   = Get-EnvValue 'DB_USERNAME'
$pass   = Get-EnvValue 'DB_PASSWORD'
$dbHost = Get-EnvValue 'DB_HOST'; if (-not $dbHost) { $dbHost = '127.0.0.1' }
$port   = Get-EnvValue 'DB_PORT'; if (-not $port)   { $port   = '3306' }
if (-not $Database) { $Database = $envDb }

if (-not (Test-Path -LiteralPath $SqlFile)) { throw "Berkas SQL tidak ditemukan: $SqlFile" }
if ($Database -notmatch '^[A-Za-z0-9_]+$')  { throw 'Nama database hanya boleh huruf, angka, dan garis bawah.' }
if ($Database -eq $envDb -and -not $Force)  {
    throw "Target '$Database' adalah database AKTIF di .env; data lama akan DITIMPA. Ulangi dengan -Force bila yakin."
}

$mysql = Join-Path $XamppRoot 'mysql\bin\mysql.exe'
if (-not (Test-Path $mysql)) { throw "mysql.exe tidak ditemukan: $mysql" }

$env:MYSQL_PWD = $pass
try {
    if ($Database -eq $envDb) {
        Write-Host '[0/3] Pra-cadangan database aktif ...'
        & (Join-Path $PSScriptRoot 'backup.ps1') -ProjectRoot $ProjectRoot -XamppRoot $XamppRoot -KeepDays 0
    }

    Write-Host "[1/3] Menyiapkan database '$Database' ..."
    & $mysql "--host=$dbHost" "--port=$port" "--user=$user" -e ('CREATE DATABASE IF NOT EXISTS `{0}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci' -f $Database)
    if ($LASTEXITCODE -ne 0) { throw 'Gagal membuat/memeriksa database target (hak akses?).' }

    Write-Host "[2/3] Mengimpor $SqlFile ..."
    $err = [System.IO.Path]::GetTempFileName()
    $p = Start-Process -FilePath $mysql -NoNewWindow -Wait -PassThru `
            -ArgumentList @("--host=$dbHost", "--port=$port", "--user=$user", '--default-character-set=utf8mb4', $Database) `
            -RedirectStandardInput $SqlFile -RedirectStandardError $err
    if ($p.ExitCode -ne 0) { throw ('Impor gagal: ' + (Get-Content $err -Raw)) }
    Remove-Item $err -ErrorAction SilentlyContinue
} finally { Remove-Item Env:\MYSQL_PWD -ErrorAction SilentlyContinue }

function Restore-Zip([string]$Zip, [string]$Dest) {
    if (-not $Zip) { return }
    if (-not (Test-Path -LiteralPath $Zip)) { throw "Arsip tidak ditemukan: $Zip" }
    New-Item -ItemType Directory -Force -Path $Dest | Out-Null
    Expand-Archive -LiteralPath $Zip -DestinationPath $Dest -Force
    Write-Host "      dipulihkan: $Dest"
}
Write-Host '[3/3] Berkas unggahan ...'
Restore-Zip $PublicZip  (Join-Path $ProjectRoot 'storage\app\public')
Restore-Zip $PrivateZip (Join-Path $ProjectRoot 'storage\app\private')

Write-Host "`nSelesai. Langkah berikutnya:" -ForegroundColor Green
Write-Host '  1) php artisan optimize:clear ; php artisan storage:link (bila perlu)'
Write-Host '  2) Pastikan APP_KEY sama dengan saat cadangan dibuat (tanpa itu BPJS/polis tidak terbaca).'
Write-Host '  3) Jalankan scripts\sql\seed-invariants.sql pada database hasil restore (semua pelanggaran = 0).'
```

Catatan: skrip memakai `Start-Process -RedirectStandardInput` karena operator `<` tidak tersedia di PowerShell dan pengalihan lewat pipe memakai pengodean ASCII yang merusak karakter non-ASCII. Dump dibuat dengan `--result-file` (bukan `>`) karena `>` di Windows PowerShell 5.1 menulis UTF-16.

### 12.3 Prosedur manual setara

```powershell
# --- backup manual ---
New-Item -ItemType Directory -Force "$APP\backups" | Out-Null
$env:MYSQL_PWD = "GANTI_PASSWORD_unpam_app"
& "$XAMPP\mysql\bin\mysqldump.exe" --host=127.0.0.1 --user=unpam_app --single-transaction --routines --default-character-set=utf8mb4 --result-file="$APP\backups\manual.sql" unpam_hospital
Compress-Archive -Path "$APP\storage\app\public\*" -DestinationPath "$APP\backups\manual-public.zip" -Force
Remove-Item Env:\MYSQL_PWD

# --- restore manual ke database uji ---
$env:MYSQL_PWD = "GANTI_PASSWORD_unpam_app"
cmd /c "`"$XAMPP\mysql\bin\mysql.exe`" --host=127.0.0.1 --user=unpam_app --default-character-set=utf8mb4 unpam_hospital_restore < `"$APP\backups\manual.sql`""
Expand-Archive -Path "$APP\backups\manual-public.zip" -DestinationPath "$APP\storage\app\public" -Force
Remove-Item Env:\MYSQL_PWD
```

Alternatif GUI: phpMyAdmin → pilih database → **Export** (metode *Custom*, centang *Add DROP TABLE*, set karakter UTF-8) dan **Import** (batas ukuran mengikuti `upload_max_filesize`, 40M).

### 12.4 Menyimpan `APP_KEY` terpisah

`APP_KEY` mengenkripsi `patient_profiles.bpjs_number` dan `appointments.payment_reference`. Cadangkan **di tempat berbeda** dari berkas backup:

- Simpan nilainya di pengelola password atau cetak dan simpan fisik. Baca dengan: `Select-String -Path "$APP\.env" -Pattern '^APP_KEY=' | ForEach-Object Line`.
- Opsi lokal terenkripsi DPAPI (terikat akun dan mesin Windows ini — **tidak dapat dibuka di mesin lain**, jadi bukan satu-satunya salinan):

  ```powershell
  (Select-String -Path "$APP\.env" -Pattern '^APP_KEY=').Line | ConvertTo-SecureString -AsPlainText -Force |
      ConvertFrom-SecureString | Set-Content "$env:USERPROFILE\unpam-appkey.dpapi"
  ```
- Jangan menyimpannya di folder `backups\`, di repo, atau di layanan sinkronisasi tanpa enkripsi.

### 12.5 Uji pemulihan

Backup baru dianggap valid setelah **dipulihkan**. Lakukan prosedur [15 §9](15-pengujian-penerimaan.md#9-prosedur-manual-backup-dan-restore-bak-01bak-05) (restore ke `unpam_hospital_restore`, hitung baris, pulihkan berkas, uji `APP_KEY`, jalankan invarian) minimal sekali sebelum menyerahkan prototipe dan setiap kali skema berubah besar. **[ASUMSI]** `mysqldump` MariaDB 10.4 menangani kolom generated `appointments.active_flag` (tidak ikut di `INSERT`); diverifikasi pada `BAK-02`/`BAK-05`.

---

## 13. Troubleshooting

| Gejala | Penyebab | Solusi |
|---|---|---|
| `SQLSTATE[HY000] [2002] Connection refused` | MariaDB belum berjalan; port/host salah; memakai `localhost` yang di Windows dapat diselesaikan ke `::1` | Start **MySQL** di Control Panel; `Get-NetTCPConnection -LocalPort 3306 -State Listen`; set `DB_HOST=127.0.0.1` dan `DB_PORT` yang benar; `& $PHP artisan config:clear` |
| `SQLSTATE[HY000] [1045] Access denied for user 'unpam_app'` | Password di `.env` salah; user belum dibuat untuk host `127.0.0.1`; config ter-cache | Ulangi §4.2 (`ALTER USER 'unpam_app'@'127.0.0.1' IDENTIFIED BY '…'`); samakan `DB_PASSWORD`; `config:clear`; uji `& $MYSQL -h 127.0.0.1 -u unpam_app -p unpam_hospital` |
| `GRANT` tidak tersimpan: `unpam_app` hanya melihat sebagian database / `SHOW GRANTS` hanya `USAGE` setelah `FLUSH PRIVILEGES`, atau `ERROR 3 … Error writing file 'aria_log.00000001' (Errcode: 9 "Bad file descriptor")` (**terjadi di mesin pemilik, 2026-10-02**) | Data dir bawaan paket XAMPP 8.2.12: tabel sistem Aria `mysql.db` rusak (`CHECK TABLE mysql.db` → *Size of indexfile … Expected …* / *Corrupt*) dan proses MariaDB kehilangan handle tulis ke log Aria (`SHOW ENGINE ARIA LOGS` → status `unknown`, berkas `aria_log.00000001` bertanggal 2019) | (1) **Stop MySQL** di Control Panel, cadangkan `C:\xampp3\mysql\data\mysql\db.frm`, `db.MAD`, `db.MAI` ke `C:\xampp3\mysql\backup-fase0-mysqldb\`, lalu **Start MySQL** (salinan saat server berjalan hanya *best-effort*); (2) `CHECK TABLE mysql.db; REPAIR TABLE mysql.db; CHECK TABLE mysql.db;` → `OK` (peringatan ukuran index boleh); (3) **Stop → Start MySQL** di Control Panel; (4) ulangi kedua `GRANT` + `FLUSH PRIVILEGES`; (5) verifikasi `SELECT Host, Db, User FROM mysql.db WHERE User='unpam_app';` = 4 baris dan `aria_log.00000001` kini ber-*timestamp* baru. Bila log Aria tetap tak tertulis: hentikan MySQL, pindahkan `aria_log.00000001` + `aria_log_control` ke folder cadangan, start lagi (MariaDB membuat ulang). |
| `SQLSTATE[HY000] [1049] Unknown database 'unpam_hospital'` | Database belum dibuat atau namanya salah ketik di `.env` | Jalankan SQL §4.2; periksa `DB_DATABASE`; `"SHOW DATABASES;" \| & $MYSQL -u root` |
| Port 3306 bentrok dengan MySQL lain (service MySQL80/MariaDB lain), MySQL XAMPP gagal start | Dua server memakai port yang sama | Cari pemilik port: `Get-NetTCPConnection -LocalPort 3306 -State Listen \| ForEach-Object { Get-Process -Id $_.OwningProcess }`; hentikan service lain (`Stop-Service MySQL80` **(Admin)**) atau pindahkan MariaDB XAMPP ke port 3307: ubah `port=3307` pada `[client]` dan `[mysqld]` di `my.ini`, `DB_PORT=3307` di `.env`/`.env.testing`, dan `$cfg['Servers'][$i]['port']` di `phpMyAdmin\config.inc.php`; restart MySQL |
| `MissingAppKeyException: No application encryption key has been specified` | `APP_KEY` kosong; `.env` tidak terbaca; config ter-cache tanpa kunci | Pastikan `.env` ada di `D:\Project Web RS`; `& $PHP artisan key:generate` **hanya** bila belum ada data terenkripsi; `config:clear` |
| `DecryptException: The MAC is invalid`, nomor BPJS/polis tidak terbaca, semua pengguna tiba-tiba logout, 419 setelah restore | `APP_KEY` berubah atau berbeda dari saat data dienkripsi | Pulihkan `APP_KEY` lama dari cadangan (§12.4) lalu `config:clear`. Bila kunci hilang, nilai terenkripsi **tidak dapat dipulihkan** — kosongkan dan minta pasien/petugas mengisi ulang |
| Gambar tidak tampil (404 pada `/storage/...`, atau tampil placeholder) | `storage:link` belum dijalankan atau junction rusak; `APP_URL` tidak sama dengan URL yang dibuka | `cmd /c rmdir "$APP\public\storage"` lalu `& $PHP artisan storage:link` (§6.2); samakan `APP_URL` dengan URL peramban; `config:clear`; periksa berkas ada di `storage\app\public` |
| Semua route 404 kecuali `/` | `mod_rewrite` mati; `AllowOverride None` pada direktori; `public\.htaccess` hilang; DocumentRoot bukan `public` | Verifikasi §7.1; pastikan `<Directory "D:/Project Web RS/public">` berisi `AllowOverride All`; periksa `Test-Path "$APP\public\.htaccess"`; `httpd.exe -t`; restart Apache |
| `Vite manifest not found at: …\public\build\manifest.json` | `npm run build` belum dijalankan atau gagal; `public\hot` tertinggal tanpa dev server | `npm run build`; hapus `public\hot` bila dev server tidak berjalan (§6.3) |
| **419 Page Expired** pada form | Token CSRF kedaluwarsa; cookie sesi tidak tersimpan (APP_URL beda host, `SESSION_SECURE_COOKIE=true` di HTTP, tabel `sessions` belum ada); `APP_KEY` berubah | Buka situs dengan host yang sama dengan `APP_URL`; set `SESSION_SECURE_COOKIE=false` di lokal; `& $PHP artisan migrate`; hapus cookie situs; muat ulang form |
| `composer dev` gagal: `Call to undefined function pcntl_…` / pail error | `laravel/pail` memerlukan ekstensi `pcntl` yang tidak tersedia di Windows | Hapus pail (§3.3: `composer remove laravel/pail --dev` dan ganti skrip `dev`); pantau log dengan `Get-Content "$APP\storage\logs\laravel-$(Get-Date -Format yyyy-MM-dd).log" -Wait -Tail 50` |
| Halaman error di Apache tetapi `artisan` normal: `Your Composer dependencies require a PHP version ">= 8.3"`, atau perilaku beda antara CLI dan web | PHP CLI 8.4 (PATH) berbeda dari PHP Apache 8.2.12; `composer.lock` dibuat tanpa `platform.php` | `composer config platform.php 8.2.12` lalu `composer update`; `composer check-platform-reqs`; selalu jalankan `& $PHP artisan …` atau awali PATH dengan `$XAMPP\php` (§2.4) |
| Apache tidak mau start: "Port 80 in use by …" (IIS/World Wide Web Publishing Service, Skype, proses PID 4 `System`) | Aplikasi lain memakai port 80/443 | Temukan: `Get-NetTCPConnection -LocalPort 80 -State Listen \| ForEach-Object { Get-Process -Id $_.OwningProcess }`. Hentikan IIS **(Admin)**: `Stop-Service W3SVC; Set-Service W3SVC -StartupType Disabled`; tutup Skype/aplikasi pemakai port. **Atau ganti port Apache**: pada `httpd.conf` ubah `Listen 80` → `Listen 8080` dan `ServerName localhost:8080`; pada `httpd-vhosts.conf` ubah `<VirtualHost *:80>` → `*:8080`; pada `extra\httpd-ssl.conf` ubah `Listen 443` → `Listen 4433` bila 443 bentrok; set `APP_URL=http://unpam-hospital.test:8080`; restart Apache dan buka `http://unpam-hospital.test:8080` |
| `Call to undefined function imagecreatetruecolor()`, `Class "IntlDateFormatter" not found`, `could not find driver` | Ekstensi `gd`, `intl`, `pdo_mysql` belum aktif di `php.ini` yang dipakai (CLI vs Apache) | Aktifkan di `C:\xampp3\php\php.ini` (§2.2); `& $PHP --ini` memastikan berkas yang sama; restart Apache; cek `& $PHP -m` |
| Unggahan gagal: `PostTooLargeException`, "The photo failed to upload", 413 | `post_max_size`/`upload_max_filesize` terlalu kecil; berkas melebihi batas aplikasi (2 MB foto, 4 MB banner) | Pastikan ≥ 8M di `php.ini` (bawaan XAMPP 40M); restart Apache; kecilkan gambar bila melebihi batas aplikasi |
| Jam sesi/`created_at` bergeser 7 jam, kode "terlambat" salah | Zona waktu `UTC` di `config/app.php` atau `date.timezone` salah di `php.ini` (`Europe/Berlin`) | Set `'timezone' => 'Asia/Jakarta'` (§3.4) dan `date.timezone = Asia/Jakarta` (§2.2); `config:clear` |
| `Syntax error … DocumentRoot must be a directory` / Apache tidak memuat vhost | Path berspasi tanpa tanda kutip, memakai `\`, atau salah ketik | Gunakan `DocumentRoot "D:/Project Web RS/public"` (kutip + `/`); `httpd.exe -t` |
| `unpam-hospital.test` tidak ditemukan di peramban | Entri `hosts` belum ada/ diblokir antivirus; cache DNS | §7.3 sebagai Administrator; `ipconfig /flushdns`; uji `[System.Net.Dns]::GetHostAddresses('unpam-hospital.test')` |

---

## 14. Catatan Produksi Singkat

Panduan ini untuk localhost. Sebelum dipakai di server nyata, selesaikan daftar berikut (pilihan **[KONFIRMASI]** dicatat di [17](17-keputusan-konfirmasi.md)).

| # | Item | Tindakan |
|:-:|---|---|
| 1 | `APP_ENV` / `APP_DEBUG` | `production` / `false` |
| 2 | HTTPS | Sertifikat TLS valid; `APP_URL=https://…`; paksa HTTPS; HSTS |
| 3 | Cookie | `SESSION_SECURE_COOKIE=true`, `SESSION_SAME_SITE=lax`, sesi `database` |
| 4 | CSP dan header keamanan | Aktifkan profil CSP ketat produksi (`frame-src https://www.google.com https://maps.google.com` untuk peta); uji `SEK-07` |
| 5 | Surel | SMTP resmi (`MAIL_MAILER=smtp`, kredensial di `.env`) **[KONFIRMASI]**; tidak memakai `log`/Mailpit |
| 6 | Tanpa data demo | **Jangan** menjalankan `DemoSeeder`; hanya `php artisan db:seed --force` (seeder inti). Verifikasi: `SELECT email FROM users WHERE email LIKE '%.test';` → 0 baris; hapus `DEMO_PASSWORD` dari `.env` |
| 7 | Super Admin | `php artisan app:create-super-admin` (password kuat, simpan di pengelola password) |
| 8 | Cache | `composer install --no-dev --optimize-autoloader`, `php artisan config:cache`, `route:cache`, `view:cache` (jalankan ulang setiap deploy) |
| 9 | Queue | `QUEUE_CONNECTION=database` + worker permanen (`php artisan queue:work --tries=3`) yang diawasi (service/NSSM atau supervisor) |
| 10 | Penjadwalan | Walau fitur inti tidak bergantung padanya, jadwalkan `sessions:generate`, `appointments:mark-no-show`, `media:prune` (Task Scheduler/cron) |
| 11 | Basis data | User khusus dengan hak minimum, password kuat; **upgrade MariaDB ≥ 10.11 LTS atau MySQL 8.4 LTS** **[KONFIRMASI]** (10.4 sudah EOL) |
| 12 | Backup | Terjadwal harian, **terenkripsi** (mis. 7-Zip AES-256), salinan di luar server, uji restore berkala; `APP_KEY` disimpan terpisah |
| 13 | Akses | Nonaktifkan phpMyAdmin di server publik; firewall hanya 80/443; `root` MariaDB berpassword dan tidak dapat diakses jaringan |
| 14 | Log | `LOG_CHANNEL=daily`, `LOG_LEVEL=warning`, retensi 14 hari; pantau galat |
| 15 | Privasi | Kebijakan privasi ditinjau pihak hukum; kebijakan retensi data pasien **[KONFIRMASI]** |
| 16 | Versi PHP | **Dukungan keamanan PHP 8.2 berakhir 31 Desember 2026** (lebih awal dari Laravel 12) dan PHP 8.2.12 bawaan XAMPP sudah tertinggal banyak patch keamanan. Produksi **wajib PHP ≥ 8.3** (disarankan 8.4, keamanan sampai 31 Desember 2028); kode kompatibel PHP 8.2–8.4, jalankan suite tes dengan PHP 8.4 CLI sebelum produksi |
| 17 | Versi framework | Laravel 12 menerima perbaikan keamanan sampai **24 Februari 2027**; rencanakan upgrade ke **Laravel 13** (butuh PHP ≥ 8.3 — tidak kompatibel dengan PHP XAMPP 8.2.12) bersama pemutakhiran runtime PHP. Rinciannya di [17](17-keputusan-konfirmasi.md) |

Perintah deploy minimal (server produksi, PowerShell/Bash sesuai OS):

```powershell
composer install --no-dev --optimize-autoloader
npm ci; npm run build
php artisan migrate --force
php artisan db:seed --force
php artisan app:create-super-admin
php artisan storage:link
php artisan config:cache; php artisan route:cache; php artisan view:cache
```

---

## Asumsi di bagian ini

- **[ASUMSI]** `DEMO_PASSWORD` adalah kunci `.env` opsional (lokal/testing saja); kosong = `Demo12345` (keputusan K-11 di 17, default (a); opsi (b): password acak per eksekusi seeder, dicetak di konsol).
- **[ASUMSI]** `intervention/image` ^3 dipakai tanpa paket jembatan Laravel (`ImageManager` dibuat di `MediaService`).
- **[ASUMSI]** Tautan reset dari `MAIL_MAILER=log` perlu didekode dari format quoted-printable (`=` + baris baru dibuang, `=3D` → `=`).
- **[ASUMSI]** `mysqldump` MariaDB 10.4 menangani kolom generated `appointments.active_flag` dengan benar (diverifikasi pada `BAK-02`/`BAK-05`).
- **[ASUMSI]** Blok `silenceDeprecations` Sass pada `vite.config.js` bersifat opsional dan bergantung versi Sass.
- Akun `root` MariaDB di mesin pemilik: keputusan K-12 di 17, default (a) dibiarkan tanpa password karena hanya localhost (opsi (b): password + phpMyAdmin login cookie, §4.3).
- **[KONFIRMASI]** Target produksi (MariaDB ≥ 10.11 LTS atau MySQL 8.4 LTS = K-01; rencana upgrade PHP/Laravel 13 = K-03; SMTP resmi = K-02) — lihat [17](17-keputusan-konfirmasi.md).

## Nama baru yang diusulkan

| Nama | Jenis | Keterangan |
|---|---|---|
| `scripts/backup.ps1`, `scripts/restore.ps1` | Skrip | Isi lengkap di §12 (sudah ada di 00 K) |
| `scripts/sql/seed-invariants.sql` | Skrip SQL | Pemeriksaan invarian ([13 §11](13-data-contoh.md#11-invarian-setelah-seeding-dan-cara-memeriksanya)) |
| `backups/` | Folder (di-`.gitignore`) | Keluaran backup |
| Berkas cadangan `<db>-YYYYMMDD-HHmmss.sql`, `storage-public-*.zip`, `storage-private-*.zip`, `SHA256SUMS-*.txt` | Konvensi nama | §12.1 |
| Parameter skrip `-ProjectRoot`, `-XamppRoot`, `-KeepDays`, `-SqlFile`, `-Database`, `-PublicZip`, `-PrivateZip`, `-Force` | Parameter | §12 |
| Variabel sesi `$XAMPP`, `$APP`, `$PHP`, `$MYSQL` | Konvensi dokumen | Prasyarat |
| Database `unpam_hospital_restore`, `.env.restore-test` | Sumber daya uji | Uji restore |
| Tugas terjadwal `UnpamHospital-GenerateSessions`, `UnpamHospital-MarkNoShow`, `UnpamHospital-Backup` | Task Scheduler | §11.1 (opsional) |
| Kunci `.env` `DEMO_PASSWORD` | Konfigurasi | Password akun demo (lokal) |
