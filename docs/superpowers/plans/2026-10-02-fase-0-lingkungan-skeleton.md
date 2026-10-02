# Fase 0 — Lingkungan dan Skeleton: Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menyiapkan XAMPP 8.2.12 di `C:\xampp3` dan skeleton Laravel 12 di root repo `D:\Project Web RS` sehingga aplikasi tampil di `http://unpam-hospital.test`, terhubung ke MariaDB 10.4 dengan zona waktu Asia/Jakarta, berbahasa Indonesia, dan suite tes berjalan aman di database uji terpisah.

**Architecture:** Monolit Laravel 12 (Blade + Bootstrap 5 via Vite) di root repo, berdampingan dengan `docs/`. Runtime web = Apache + PHP 8.2.12 bawaan XAMPP; semua perintah CLI (`composer`, `artisan`, `phpunit`, `pint`) juga dijalankan dengan PHP XAMPP agar dependensi dan perilaku identik. Tes memakai MariaDB (`unpam_hospital_test`), bukan SQLite, dan dijaga agar tidak pernah menyentuh database pengembangan.

**Tech Stack:** XAMPP 8.2.12 (Apache 2.4.58, PHP 8.2.12, MariaDB 10.4.32), Laravel 12.x (skeleton v12.12.x), Composer 2.9 (`composer.phar`), Node 24 / npm 11, Vite 7, Bootstrap 5.3, Sass, PHPUnit 11, Laravel Pint.

**Spec:** `docs/superpowers/specs/2026-10-02-unpam-hospital-blueprint/` — terutama `00-keputusan-inti.md` (bagian B, B.1, B.2, C), `14-tahapan-implementasi.md` (Fase 0), `16-instalasi-operasional.md` (§1–§10). Bila ada perbedaan, **00 yang berlaku**.

## Global Constraints

- XAMPP yang dipakai: **`C:\xampp3`** (PHP 8.2.12, Apache 2.4.58, MariaDB 10.4.32). Folder `C:\xampp` lama adalah sisa instalasi — **jangan diubah atau dipakai**.
- Root aplikasi Laravel = root repo **`D:\Project Web RS`**; `.git` dan `docs/` harus tetap utuh.
- **Setiap perintah PowerShell diawali Prelude P** (state shell tidak bertahan antar-perintah):
  `$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $MYSQL = "$XAMPP\mysql\bin\mysql.exe"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP`
- Composer **selalu** dipanggil `& $PHP $COMPOSER …`, tidak pernah `composer …` (`composer.bat` lewat cmd.exe membuang karakter `^`, dan `php` di PATH sistem adalah 8.4). npm ditulis dengan versi mayor saja (mis. `bootstrap@5`), tidak memakai `^`.
- `composer.json` → `"config": {"platform": {"php": "8.2.12"}}`.
- Versi paket: `laravel/framework` ^12.0, `spatie/laravel-permission` **^6.25**, `intervention/image` ^3.11, `mews/purifier` ^3.4, `barryvdh/laravel-dompdf` ^3.1, `chillerlan/php-qrcode` **^6.0**, `phpunit/phpunit` ^11.5. `laravel/pail` dihapus (butuh `pcntl`, tidak ada di Windows).
- npm: hapus `tailwindcss`, `@tailwindcss/vite`, `axios`; pasang `bootstrap@5`, `@popperjs/core@2`, `bootstrap-icons@1`, `chart.js@4`, `quill@2`, `@fontsource/plus-jakarta-sans@5`, dev `sass`.
- Database: `unpam_hospital` (pengembangan), `unpam_hospital_test` (uji), charset `utf8mb4`, collation `utf8mb4_unicode_ci`, user aplikasi `unpam_app` (bukan `root`), `DB_CONNECTION=mariadb`, sesi DB `+07:00`.
- Aplikasi: `timezone = 'Asia/Jakarta'`, `APP_LOCALE=id`, `APP_FALLBACK_LOCALE=en`, `APP_FAKER_LOCALE=id_ID`, `APP_NAME="UNPAM HOSPITAL"`, `APP_URL=http://unpam-hospital.test`.
- Lokal: `LOG_CHANNEL=daily`, `SESSION_DRIVER=database`, `CACHE_STORE=database`, `QUEUE_CONNECTION=sync`, `MAIL_MAILER=log`, `SESSION_SECURE_COOKIE=false`.
- File teks yang ditulis lewat PowerShell memakai `[IO.File]::WriteAllText(path, text, (New-Object System.Text.UTF8Encoding $false))` — **jangan** `Set-Content -Encoding utf8` (PowerShell 5.1 menambah BOM yang merusak `.env`). Lebih baik pakai tool Write/Edit untuk file di repo.
- `.env` dan `.env.testing` **tidak pernah di-commit**.
- Cabang kerja: `fase-0-lingkungan` (dari `master`). Pesan commit: `tipe(fase-0): ringkasan`.
- Langkah bertanda **MANUAL (pemilik)** butuh tindakan pemilik (hak Administrator Windows atau XAMPP Control Panel). Implementer berhenti di langkah itu, melaporkan status `NEEDS_CONTEXT` beserta perintah yang harus dijalankan pemilik, lalu melanjutkan setelah dikonfirmasi.

## Review Focus

1. **Suite tes diarahkan ke database pengembangan** (mis. `.env.testing` hilang atau `phpunit.xml` diubah) → `RefreshDatabase` di fase berikutnya akan menghapus data `unpam_hospital`. Harapan: tes berhenti dengan pesan jelas **sebelum** trait database berjalan. → Task 3 (`DatabaseGuardTest`, `TestCase::setUpTraits`).
2. **Composer dijalankan lewat `composer.bat` atau PHP 8.4** → `^` hilang atau paket yang butuh PHP ≥ 8.3 terpasang. Harapan: `composer.lock` berisi framework 12.x, permission 6.x, image 3.x, purifier 3.x, dompdf 3.x, qrcode 6.x; pail tidak ada. → Task 2 (`ComposerManifestTest`).
3. **Zona waktu sesi MariaDB berbeda dari aplikasi** → `NOW()` di SQL bergeser 7 jam dari `now()` PHP. Harapan: `TIMEDIFF(NOW(), UTC_TIMESTAMP()) = '07:00:00'` pada koneksi aplikasi. → Task 3 (`EnvironmentTest`).
4. **Rahasia ter-commit** (`.env`/`.env.testing` berisi `APP_KEY` dan password DB). Harapan: keduanya di-ignore git dan file contoh tidak memuat nilai rahasia. → Task 3 (`RepositoryHygieneTest` + `git check-ignore`).
5. **Teks bahasa Inggris bocor ke UI** (kunci `lang/id` hilang, atau atribut tampil "full name"). Harapan: semua kunci `lang/en` ada di `lang/id`, dan atribut umum diterjemahkan ("Kolom nama lengkap wajib diisi."). → Task 4 (`LocalizationTest`).

---

### Task 1: Cabang kerja, pemeriksa lingkungan, dan konfigurasi PHP XAMPP

**Files:**
- Create: `scripts/check-env.php`
- Modify (di luar repo): `C:\xampp3\php\php.ini` (cadangan `C:\xampp3\php\php.ini.bak-fase0`)

**Interfaces:**
- Consumes: —
- Produces: `scripts/check-env.php` (keluaran JSON, exit code 0 = OK, 1 = GAGAL) dipakai Task 7 dan kasus INS-01. PHP CLI XAMPP memuat `gd`, `intl`, `zip` dengan `date.timezone = Asia/Jakarta` (dibutuhkan Composer di Task 2 untuk ekstraksi zip).

- [ ] **Step 1: Buat cabang kerja**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $MYSQL = "$XAMPP\mysql\bin\mysql.exe"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git status --short
git switch -c fase-0-lingkungan
```
Expected: `git status` kosong (bersih), lalu `Switched to a new branch 'fase-0-lingkungan'`.

- [ ] **Step 2: Tulis pemeriksa lingkungan `scripts/check-env.php`**

```php
<?php

// Pemeriksa lingkungan UNPAM HOSPITAL (fase 0, kasus uji INS-01).
// Jalankan: C:\xampp3\php\php.exe scripts\check-env.php
// Exit code 0 = semua syarat terpenuhi, 1 = ada yang gagal.

$required = ['pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'fileinfo', 'gd', 'intl', 'zip', 'bcmath', 'curl'];
$missing = array_values(array_filter($required, fn (string $ext): bool => ! extension_loaded($ext)));
$gd = function_exists('gd_info') ? gd_info() : [];

$report = [
    'php_version' => PHP_VERSION,
    'php_ini' => php_ini_loaded_file(),
    'missing_extensions' => $missing,
    'gd_jpeg' => (bool) ($gd['JPEG Support'] ?? false),
    'gd_png' => (bool) ($gd['PNG Support'] ?? false),
    'gd_webp' => (bool) ($gd['WebP Support'] ?? false),
    'gd_freetype' => (bool) ($gd['FreeType Support'] ?? false),
    'date_timezone' => ini_get('date.timezone'),
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'memory_limit' => ini_get('memory_limit'),
];

$isPhp82 = version_compare(PHP_VERSION, '8.2.0', '>=') && version_compare(PHP_VERSION, '8.3.0', '<');
$ok = $isPhp82
    && $missing === []
    && $report['gd_jpeg'] && $report['gd_png'] && $report['gd_webp']
    && $report['date_timezone'] === 'Asia/Jakarta';

$report['status'] = $ok ? 'OK' : 'GAGAL';

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;

exit($ok ? 0 : 1);
```

- [ ] **Step 3: Jalankan pemeriksa — harus GAGAL sebelum php.ini diubah**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $MYSQL = "$XAMPP\mysql\bin\mysql.exe"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP scripts\check-env.php; "exit=$LASTEXITCODE"
```
Expected: `"missing_extensions": ["gd", "intl", "zip"]`, `"date_timezone": "Europe/Berlin"`, `"status": "GAGAL"`, `exit=1`.

- [ ] **Step 4: Cadangkan dan ubah `C:\xampp3\php\php.ini`**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $MYSQL = "$XAMPP\mysql\bin\mysql.exe"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
$ini = "$XAMPP\php\php.ini"
if (-not (Test-Path "$ini.bak-fase0")) { Copy-Item -LiteralPath $ini -Destination "$ini.bak-fase0" }
$enc = [System.Text.Encoding]::Default
$text = [System.IO.File]::ReadAllText($ini, $enc)
foreach ($ext in 'gd', 'intl', 'zip') {
    $text = [regex]::Replace($text, "(?m)^;extension=$ext(?=\r?$)", "extension=$ext")
}
$text = [regex]::Replace($text, '(?m)^date\.timezone=[^\r\n]*', 'date.timezone=Asia/Jakarta')
[System.IO.File]::WriteAllText($ini, $text, $enc)
Select-String -Path $ini -Pattern '^extension=(gd|intl|zip)\s*$', '^date\.timezone=' | ForEach-Object { $_.Line }
```
Expected keluaran empat baris: `extension=gd`, `extension=intl`, `extension=zip`, `date.timezone=Asia/Jakarta`.

- [ ] **Step 5: Jalankan pemeriksa lagi — harus OK**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $MYSQL = "$XAMPP\mysql\bin\mysql.exe"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP scripts\check-env.php; "exit=$LASTEXITCODE"
```
Expected: `"php_version": "8.2.12"`, `"missing_extensions": []`, `"gd_webp": true`, `"date_timezone": "Asia/Jakarta"`, `"status": "OK"`, `exit=0`.

Catatan: Apache baru memakai `php.ini` baru setelah di-restart; restart dilakukan di Task 6 (MANUAL). CLI langsung memakai file baru.

- [ ] **Step 6: Commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $MYSQL = "$XAMPP\mysql\bin\mysql.exe"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git add scripts/check-env.php
git commit -m "chore(fase-0): pemeriksa lingkungan PHP XAMPP"
```

---

### Task 2: Skeleton Laravel 12 dan paket Composer

**Files:**
- Create (dari skeleton `laravel/laravel` ^12.0): seluruh berkas skeleton di root repo (`app/`, `bootstrap/`, `config/`, `database/`, `public/`, `resources/`, `routes/`, `storage/`, `tests/`, `artisan`, `composer.json`, `package.json`, `phpunit.xml`, `vite.config.js`, `.editorconfig`, `.env.example`, `.gitattributes`, `.gitignore`, `README.md`)
- Create: `tests/Unit/ComposerManifestTest.php`
- Modify: `composer.json` (platform, skrip `dev`, paket), `README.md`
- Delete: `tests/Unit/ExampleTest.php`

**Interfaces:**
- Consumes: PHP CLI XAMPP dengan `zip` aktif (Task 1).
- Produces: `vendor/` + `composer.lock` terkunci untuk PHP 8.2.12; paket `spatie/laravel-permission` 6.x, `intervention/image` 3.x, `mews/purifier` 3.x, `barryvdh/laravel-dompdf` 3.x, `chillerlan/php-qrcode` 6.x tersedia untuk fase 1+.

- [ ] **Step 1: Buat skeleton di folder sementara lalu pindahkan ke root repo**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $MYSQL = "$XAMPP\mysql\bin\mysql.exe"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
$TMP = Join-Path $env:TEMP "unpam-skel"
if (Test-Path $TMP) { Remove-Item -LiteralPath $TMP -Recurse -Force }
& $PHP $COMPOSER create-project "laravel/laravel:^12.0" $TMP --no-install --no-scripts --prefer-dist --no-interaction
Get-ChildItem -LiteralPath $TMP -Force | ForEach-Object {
    $target = Join-Path $APP $_.Name
    if (Test-Path -LiteralPath $target) { throw "Sudah ada di repo, hentikan: $($_.Name)" }
    Move-Item -LiteralPath $_.FullName -Destination $target
}
Remove-Item -LiteralPath $TMP -Recurse -Force
Get-ChildItem -Force $APP | ForEach-Object { $_.Name }
```
Expected: baris `Installing laravel/laravel (v12.x.y)` dengan x ≥ 12 (bukan `v12.0.0`); daftar root memuat `.git`, `docs`, `app`, `artisan`, `composer.json`, `package.json`, `phpunit.xml`, `vite.config.js`, `.env.example`, `.gitignore`. Tidak ada `vendor` dan tidak ada `.env`.

- [ ] **Step 2: Pasang dependensi dasar skeleton (baseline)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $MYSQL = "$XAMPP\mysql\bin\mysql.exe"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP $COMPOSER install --no-interaction --no-progress
```
Expected: diakhiri `Generating optimized autoload files` dan `INFO  Discovering packages.` tanpa galat.

- [ ] **Step 3: Tulis tes yang gagal `tests/Unit/ComposerManifestTest.php`**

```php
<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ComposerManifestTest extends TestCase
{
    /** @return array<string, mixed> */
    private function json(string $file): array
    {
        return json_decode(file_get_contents(dirname(__DIR__, 2).'/'.$file), true, flags: JSON_THROW_ON_ERROR);
    }

    private function lockedVersion(string $package): ?string
    {
        $lock = $this->json('composer.lock');

        foreach (array_merge($lock['packages'], $lock['packages-dev']) as $entry) {
            if ($entry['name'] === $package) {
                return ltrim($entry['version'], 'v');
            }
        }

        return null;
    }

    public function test_platform_php_is_pinned_to_xampp_runtime(): void
    {
        $this->assertSame('8.2.12', $this->json('composer.json')['config']['platform']['php'] ?? null);
    }

    public function test_required_packages_resolve_to_expected_major_versions(): void
    {
        $expected = [
            'laravel/framework' => '12',
            'spatie/laravel-permission' => '6',
            'intervention/image' => '3',
            'mews/purifier' => '3',
            'barryvdh/laravel-dompdf' => '3',
            'chillerlan/php-qrcode' => '6',
            'phpunit/phpunit' => '11',
        ];

        foreach ($expected as $package => $major) {
            $version = $this->lockedVersion($package);

            $this->assertNotNull($version, "{$package} belum terpasang");
            $this->assertStringStartsWith($major.'.', $version, "{$package} harus versi {$major}.x, terpasang {$version}");
        }
    }

    public function test_pail_is_removed_because_windows_has_no_pcntl(): void
    {
        $composer = $this->json('composer.json');

        $this->assertArrayNotHasKey('laravel/pail', $composer['require-dev'] ?? []);
        $this->assertStringNotContainsString('pail', implode(' ', (array) ($composer['scripts']['dev'] ?? [])));
        $this->assertNull($this->lockedVersion('laravel/pail'));
    }
}
```

Hapus tes contoh skeleton:

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $MYSQL = "$XAMPP\mysql\bin\mysql.exe"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
Remove-Item -LiteralPath "tests\Unit\ExampleTest.php"
```

- [ ] **Step 4: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $MYSQL = "$XAMPP\mysql\bin\mysql.exe"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor/bin/phpunit tests/Unit/ComposerManifestTest.php
```
Expected: `FAILURES! Tests: 3, ... Failures: 3` — platform `null`, `spatie/laravel-permission belum terpasang`, pail masih ada.

- [ ] **Step 5: Ubah skrip `dev` di `composer.json` (tool Edit)**

Ganti blok ini:

```json
        "dev": [
            "Composer\\Config::disableProcessTimeout",
            "npx concurrently -c \"#93c5fd,#c4b5fd,#fb7185,#fdba74\" \"php artisan serve\" \"php artisan queue:listen --tries=1 --timeout=0\" \"php artisan pail --timeout=0\" \"npm run dev\" --names=server,queue,logs,vite --kill-others"
        ],
```

menjadi:

```json
        "dev": [
            "Composer\\Config::disableProcessTimeout",
            "npx concurrently -c \"#93c5fd,#fdba74\" \"php artisan serve\" \"npm run dev\" --names=server,vite --kill-others"
        ],
```

(`queue:listen` tidak diperlukan karena `QUEUE_CONNECTION=sync`; `pail` tidak berjalan di Windows.)

- [ ] **Step 6: Kunci platform, hapus pail, pasang paket, resolusi ulang**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $MYSQL = "$XAMPP\mysql\bin\mysql.exe"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP $COMPOSER config platform.php 8.2.12
& $PHP $COMPOSER remove --dev laravel/pail --no-update --no-interaction
& $PHP $COMPOSER require "spatie/laravel-permission:^6.25" "intervention/image:^3.11" "mews/purifier:^3.4" "barryvdh/laravel-dompdf:^3.1" "chillerlan/php-qrcode:^6.0" --no-update --no-interaction
& $PHP $COMPOSER update --no-interaction --no-progress
& $PHP $COMPOSER check-platform-reqs
```
Expected: `update` tanpa galat; `check-platform-reqs` semua baris `success`.

- [ ] **Step 7: Jalankan tes — harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $MYSQL = "$XAMPP\mysql\bin\mysql.exe"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor/bin/phpunit tests/Unit/ComposerManifestTest.php
```
Expected: `OK (3 tests, ...)`.

- [ ] **Step 8: Ganti `README.md` skeleton dengan README proyek (tool Write)**

Isi berkas = seluruh teks di antara pagar empat-backtick berikut (pagar luar tidak ikut ditulis):

````markdown
# UNPAM HOSPITAL

Prototipe website rumah sakit: situs publik, portal pasien (pendaftaran kunjungan rawat jalan), dan dashboard admin.
Laravel 12 + Blade + Bootstrap 5, MariaDB 10.4 melalui XAMPP 8.2.12 (Windows, localhost). Seluruh data adalah data contoh fiktif.

- Blueprint (sumber kebenaran): [`docs/superpowers/specs/2026-10-02-unpam-hospital-blueprint/`](docs/superpowers/specs/2026-10-02-unpam-hospital-blueprint/README.md)
- Rencana implementasi per fase: [`docs/superpowers/plans/`](docs/superpowers/plans/)
- Panduan instalasi lokal: blueprint bagian 16.

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
````

- [ ] **Step 9: Commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $MYSQL = "$XAMPP\mysql\bin\mysql.exe"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git add -A
git status --short | Select-String -Pattern 'vendor/|node_modules/|\.env$' ; "harus kosong di atas"
git commit -m "chore(fase-0): skeleton Laravel 12 dengan paket terkunci untuk PHP 8.2.12"
```
Expected: tidak ada baris `vendor/`, `node_modules/`, atau `.env` yang ter-stage.

---

### Task 3: Database MariaDB, file environment, dan pengaman database uji

**Files:**
- Create: `tests/Support/DatabaseGuard.php`, `tests/Unit/DatabaseGuardTest.php`, `tests/Unit/RepositoryHygieneTest.php`, `tests/Feature/EnvironmentTest.php`, `.env.testing.example`
- Create (tidak di-commit): `.env`, `.env.testing`
- Modify: `.env.example`, `.gitignore`, `phpunit.xml`, `config/database.php`, `tests/TestCase.php`, `database/seeders/DatabaseSeeder.php`

**Interfaces:**
- Consumes: `vendor/` (Task 2).
- Produces: `Tests\Support\DatabaseGuard::assertIsTestDatabase(string $database): void` (melempar `RuntimeException` bila nama tidak berakhiran `_test`); `Tests\TestCase` memanggilnya di `setUpTraits()` sehingga semua tes Feature fase berikutnya terlindungi. Database `unpam_hospital` + `unpam_hospital_test` dengan user `unpam_app`; koneksi `mariadb` bersesi `+07:00`; tabel bawaan (`users`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`) termigrasi; `public/storage` → `storage/app/public`.

- [ ] **Step 1: Tulis tes yang gagal**

`tests/Support/DatabaseGuard.php`:

```php
<?php

namespace Tests\Support;

use RuntimeException;

final class DatabaseGuard
{
    /**
     * Mencegah suite tes (mis. RefreshDatabase) menyentuh database selain database uji.
     */
    public static function assertIsTestDatabase(string $database): void
    {
        if ($database === '' || ! str_ends_with($database, '_test')) {
            throw new RuntimeException("Tes dihentikan: database '{$database}' bukan database uji (nama harus berakhiran _test).");
        }
    }
}
```

`tests/Unit/DatabaseGuardTest.php`:

```php
<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\DatabaseGuard;

class DatabaseGuardTest extends TestCase
{
    public function test_accepts_database_name_ending_with_test(): void
    {
        DatabaseGuard::assertIsTestDatabase('unpam_hospital_test');

        $this->addToAssertionCount(1);
    }

    public function test_rejects_development_database(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("database 'unpam_hospital' bukan database uji");

        DatabaseGuard::assertIsTestDatabase('unpam_hospital');
    }

    public function test_rejects_empty_database_name(): void
    {
        $this->expectException(RuntimeException::class);

        DatabaseGuard::assertIsTestDatabase('');
    }
}
```

`tests/Unit/RepositoryHygieneTest.php`:

```php
<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class RepositoryHygieneTest extends TestCase
{
    private function path(string $file): string
    {
        return dirname(__DIR__, 2).'/'.$file;
    }

    public function test_secrets_and_generated_folders_are_git_ignored(): void
    {
        $lines = array_map('trim', file($this->path('.gitignore')));

        foreach (['.env', '.env.testing', '/backups', '/vendor', '/node_modules', '/public/storage', '/public/build'] as $entry) {
            $this->assertContains($entry, $lines, "{$entry} harus ada di .gitignore");
        }
    }

    public function test_example_env_files_contain_no_secrets(): void
    {
        foreach (['.env.example', '.env.testing.example'] as $file) {
            $content = file_get_contents($this->path($file));

            $this->assertMatchesRegularExpression('/^APP_KEY=\r?$/m', $content, "{$file}: APP_KEY harus kosong");
            $this->assertMatchesRegularExpression('/^DB_PASSWORD=\r?$/m', $content, "{$file}: DB_PASSWORD harus kosong");
            $this->assertMatchesRegularExpression('/^DB_CONNECTION=mariadb\r?$/m', $content);
        }
    }
}
```

`tests/Feature/EnvironmentTest.php`:

```php
<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EnvironmentTest extends TestCase
{
    public function test_default_connection_is_the_mariadb_test_database(): void
    {
        $this->assertSame('mariadb', config('database.default'));
        $this->assertSame('unpam_hospital_test', DB::connection()->getDatabaseName());
    }

    public function test_server_is_mariadb_10_4_or_newer(): void
    {
        $version = DB::selectOne('SELECT VERSION() AS v')->v;

        $this->assertStringContainsString('MariaDB', $version);
        $this->assertTrue(version_compare(explode('-', $version)[0], '10.4.0', '>='), "MariaDB terlalu lama: {$version}");
    }

    public function test_database_session_time_zone_matches_jakarta(): void
    {
        $this->assertSame('07:00:00', DB::selectOne('SELECT TIMEDIFF(NOW(), UTC_TIMESTAMP()) AS d')->d);
    }

    public function test_connection_uses_utf8mb4_unicode(): void
    {
        $row = DB::selectOne('SELECT @@character_set_connection AS cs, @@collation_connection AS co');

        $this->assertSame('utf8mb4', $row->cs);
        $this->assertSame('utf8mb4_unicode_ci', $row->co);
    }
}
```

- [ ] **Step 2: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $MYSQL = "$XAMPP\mysql\bin\mysql.exe"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor/bin/phpunit tests/Unit/DatabaseGuardTest.php tests/Unit/RepositoryHygieneTest.php tests/Feature/EnvironmentTest.php
```
Expected: `DatabaseGuardTest` lulus (kelasnya sudah ditulis); `RepositoryHygieneTest` gagal (`.env.testing harus ada di .gitignore`, `.env.testing.example` tidak ada); `EnvironmentTest` gagal (`'sqlite'` ≠ `'mariadb'`).

- [ ] **Step 3: Tulis `.env.example` (tool Write, timpa isi skeleton)**

```dotenv
APP_NAME="UNPAM HOSPITAL"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://unpam-hospital.test

APP_LOCALE=id
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=id_ID

APP_MAINTENANCE_DRIVER=file

BCRYPT_ROUNDS=12

LOG_CHANNEL=daily
LOG_DAILY_DAYS=14
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=unpam_hospital
DB_USERNAME=unpam_app
DB_PASSWORD=
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
MAIL_FROM_ADDRESS="no-reply@unpamhospital.test"
MAIL_FROM_NAME="${APP_NAME}"

VITE_APP_NAME="${APP_NAME}"
```

`.env.testing.example` (tool Write):

```dotenv
APP_NAME="UNPAM HOSPITAL"
APP_ENV=testing
APP_KEY=
APP_DEBUG=true
APP_URL=http://unpam-hospital.test

APP_LOCALE=id
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=id_ID

BCRYPT_ROUNDS=4

LOG_CHANNEL=daily
LOG_LEVEL=debug

DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=unpam_hospital_test
DB_USERNAME=unpam_app
DB_PASSWORD=
DB_TIMEZONE=+07:00

SESSION_DRIVER=array
CACHE_STORE=array
QUEUE_CONNECTION=sync
MAIL_MAILER=array
FILESYSTEM_DISK=local
```

- [ ] **Step 4: Tambahkan entri `.gitignore` (tool Edit)**

Ganti baris:

```
.env.production
```

menjadi:

```
.env.production
.env.testing
/backups
```

- [ ] **Step 5: Buat database, user `unpam_app`, `.env`, `.env.testing`, dan APP_KEY (satu perintah)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $MYSQL = "$XAMPP\mysql\bin\mysql.exe"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
if ((Test-Path ".env") -or (Test-Path ".env.testing")) { throw ".env atau .env.testing sudah ada — jangan ditimpa (APP_KEY lama akan hilang)." }
$pw = -join ((48..57) + (65..90) + (97..122) | Get-Random -Count 24 | ForEach-Object { [char]$_ })
$sql = @"
CREATE DATABASE IF NOT EXISTS unpam_hospital CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS unpam_hospital_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE OR REPLACE USER 'unpam_app'@'localhost' IDENTIFIED BY '$pw';
CREATE OR REPLACE USER 'unpam_app'@'127.0.0.1' IDENTIFIED BY '$pw';
GRANT ALL PRIVILEGES ON unpam_hospital.* TO 'unpam_app'@'localhost', 'unpam_app'@'127.0.0.1';
GRANT ALL PRIVILEGES ON unpam_hospital_test.* TO 'unpam_app'@'localhost', 'unpam_app'@'127.0.0.1';
FLUSH PRIVILEGES;
"@
& $MYSQL -u root -h 127.0.0.1 -e $sql
if ($LASTEXITCODE -ne 0) { throw "Gagal membuat database/user" }
$utf8 = New-Object System.Text.UTF8Encoding $false
foreach ($pair in @(@('.env.example', '.env'), @('.env.testing.example', '.env.testing'))) {
    $text = [System.IO.File]::ReadAllText((Join-Path $APP $pair[0]))
    $text = [regex]::Replace($text, '(?m)^DB_PASSWORD=(?=\r?$)', "DB_PASSWORD=$pw")
    [System.IO.File]::WriteAllText((Join-Path $APP $pair[1]), $text, $utf8)
}
& $PHP artisan key:generate --ansi
& $PHP artisan key:generate --env=testing --ansi
& $MYSQL -u unpam_app "-p$pw" -h 127.0.0.1 -e "SELECT CURRENT_USER() AS user, @@version AS version; SHOW DATABASES LIKE 'unpam_hospital%';"
```
Expected: dua baris `INFO  Application key set successfully.`; keluaran SQL menampilkan `unpam_app@127.0.0.1` (atau `@localhost`), versi `10.4.32-MariaDB`, serta `unpam_hospital` dan `unpam_hospital_test`.

- [ ] **Step 6: Arahkan PHPUnit ke database uji (tool Edit pada `phpunit.xml`)**

Ganti:

```xml
        <env name="DB_CONNECTION" value="sqlite"/>
        <env name="DB_DATABASE" value=":memory:"/>
```

menjadi:

```xml
        <env name="DB_CONNECTION" value="mariadb" force="true"/>
        <env name="DB_DATABASE" value="unpam_hospital_test" force="true"/>
```

- [ ] **Step 7: Zona waktu sesi MariaDB (tool Edit pada `config/database.php`)**

Ganti:

```php
        'mariadb' => [
            'driver' => 'mariadb',
```

menjadi:

```php
        'mariadb' => [
            'driver' => 'mariadb',
            'timezone' => env('DB_TIMEZONE', '+07:00'),
```

- [ ] **Step 8: Pasang pengaman pada `tests/TestCase.php` (tool Write, timpa isi skeleton)**

```php
<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\DatabaseGuard;

abstract class TestCase extends BaseTestCase
{
    /**
     * Dipanggil sebelum trait (mis. RefreshDatabase) menyentuh database,
     * sehingga suite tes tidak pernah berjalan pada database pengembangan.
     */
    protected function setUpTraits()
    {
        $connection = (string) config('database.default');

        DatabaseGuard::assertIsTestDatabase((string) config("database.connections.{$connection}.database"));

        return parent::setUpTraits();
    }
}
```

- [ ] **Step 9: Kosongkan seeder bawaan (tool Write pada `database/seeders/DatabaseSeeder.php`)**

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seeder peran, pengaturan, dan halaman ditambahkan mulai fase 1 (blueprint bagian 13).
     * DemoSeeder hanya dijalankan di lingkungan local/testing.
     */
    public function run(): void
    {
        //
    }
}
```

- [ ] **Step 10: Migrasi kedua database dan buat storage link**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $MYSQL = "$XAMPP\mysql\bin\mysql.exe"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan migrate --ansi
& $PHP artisan migrate --env=testing --ansi
& $PHP artisan storage:link --ansi
Get-Item "public\storage" | Select-Object Name, LinkType, Target
```
Expected: tiga migration (`create_users_table`, `create_cache_table`, `create_jobs_table`) `DONE` pada kedua database; `public\storage` ber-`LinkType` `Junction` menuju `...\storage\app\public`.

- [ ] **Step 11: Jalankan tes — harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $MYSQL = "$XAMPP\mysql\bin\mysql.exe"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="DatabaseGuardTest|RepositoryHygieneTest|EnvironmentTest|ComposerManifestTest"
```
Expected: semua lulus (12 tes).

- [ ] **Step 12: Verifikasi pengaman benar-benar menghentikan tes pada database pengembangan**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $MYSQL = "$XAMPP\mysql\bin\mysql.exe"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
$xml = [System.IO.File]::ReadAllText("$APP\phpunit.xml")
[System.IO.File]::WriteAllText("$APP\phpunit.guardcheck.xml", $xml.Replace('value="unpam_hospital_test"', 'value="unpam_hospital"'), (New-Object System.Text.UTF8Encoding $false))
& $PHP vendor/bin/phpunit -c phpunit.guardcheck.xml tests/Feature/EnvironmentTest.php
Remove-Item -LiteralPath "$APP\phpunit.guardcheck.xml"
```
Expected: semua tes `EnvironmentTest` ERROR dengan pesan `Tes dihentikan: database 'unpam_hospital' bukan database uji`; berkas `phpunit.guardcheck.xml` terhapus.

- [ ] **Step 13: Commit (tanpa `.env`)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $MYSQL = "$XAMPP\mysql\bin\mysql.exe"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git check-ignore .env .env.testing
git add .env.example .env.testing.example .gitignore phpunit.xml config/database.php tests database/seeders/DatabaseSeeder.php
git status --short
git commit -m "feat(fase-0): koneksi MariaDB, environment lokal/uji, dan pengaman database uji"
```
Expected: `git check-ignore` mencetak `.env` dan `.env.testing`; `git status` tidak menampilkan `.env`/`.env.testing` sebagai file yang akan di-commit.

---

### Task 4: Zona waktu, locale, dan terjemahan Bahasa Indonesia

**Files:**
- Create: `tests/Feature/LocalizationTest.php`, `lang/en/{auth,pagination,passwords,validation}.php` (via `lang:publish`), `lang/id/auth.php`, `lang/id/pagination.php`, `lang/id/passwords.php`, `lang/id/validation.php`
- Modify: `config/app.php`

**Interfaces:**
- Consumes: `.env.testing` dengan `APP_LOCALE=id` (Task 3), `Tests\TestCase` (Task 3).
- Produces: `config('app.timezone') === 'Asia/Jakarta'`; pesan validasi/auth/password dalam Bahasa Indonesia; `lang/id/validation.php` → `attributes` memuat nama kolom umum yang dipakai Form Request fase berikutnya (mis. `full_name` → "nama lengkap"). Pesan `passwords.user` = `passwords.sent` (respons generik, 00 H).

- [ ] **Step 1: Tulis tes yang gagal `tests/Feature/LocalizationTest.php`**

```php
<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    public function test_application_timezone_is_jakarta(): void
    {
        $this->assertSame('Asia/Jakarta', config('app.timezone'));
        $this->assertSame('Asia/Jakarta', date_default_timezone_get());
        $this->assertSame('Asia/Jakarta', now()->getTimezone()->getName());
    }

    public function test_application_locale_is_indonesian(): void
    {
        $this->assertSame('id', app()->getLocale());
        $this->assertSame('en', config('app.fallback_locale'));
        $this->assertSame('id_ID', config('app.faker_locale'));
    }

    public function test_validation_messages_are_indonesian_with_translated_attributes(): void
    {
        $errors = Validator::make([], ['full_name' => 'required', 'email' => 'required|email'])->errors();

        $this->assertSame('Kolom nama lengkap wajib diisi.', $errors->first('full_name'));
        $this->assertSame('Kolom email wajib diisi.', $errors->first('email'));
    }

    public function test_auth_and_password_messages_are_generic_indonesian(): void
    {
        $this->assertSame('Email atau password salah.', __('auth.failed'));
        $this->assertSame(__('passwords.sent'), __('passwords.user'));
    }

    /** @return array<string, array{string}> */
    public static function languageFiles(): array
    {
        return [
            'auth' => ['auth'],
            'pagination' => ['pagination'],
            'passwords' => ['passwords'],
            'validation' => ['validation'],
        ];
    }

    #[DataProvider('languageFiles')]
    public function test_indonesian_file_has_every_english_key(string $file): void
    {
        $english = $this->flattenKeys(require lang_path("en/{$file}.php"));
        $indonesian = $this->flattenKeys(require lang_path("id/{$file}.php"));

        $this->assertSame([], array_values(array_diff($english, $indonesian)), "Kunci hilang di lang/id/{$file}.php");
    }

    /**
     * @param  array<string, mixed>  $lines
     * @return list<string>
     */
    private function flattenKeys(array $lines, string $prefix = ''): array
    {
        $keys = [];

        foreach ($lines as $key => $value) {
            if ($prefix === '' && in_array($key, ['custom', 'attributes'], true)) {
                continue;
            }

            $keys = is_array($value)
                ? [...$keys, ...$this->flattenKeys($value, "{$prefix}{$key}.")]
                : [...$keys, "{$prefix}{$key}"];
        }

        return $keys;
    }
}
```

- [ ] **Step 2: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $MYSQL = "$XAMPP\mysql\bin\mysql.exe"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=LocalizationTest
```
Expected: gagal — `'UTC'` ≠ `'Asia/Jakarta'`, pesan validasi masih bahasa Inggris, dan `lang/en/*.php` belum ada.

- [ ] **Step 3: Ubah zona waktu (tool Edit pada `config/app.php`)**

Ganti:

```php
    'timezone' => 'UTC',
```

menjadi:

```php
    'timezone' => 'Asia/Jakarta',
```

- [ ] **Step 4: Terbitkan file bahasa Inggris (dasar pembanding)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $MYSQL = "$XAMPP\mysql\bin\mysql.exe"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan lang:publish --ansi
Get-ChildItem lang\en | ForEach-Object { $_.Name }
```
Expected: `auth.php`, `pagination.php`, `passwords.php`, `validation.php`.

- [ ] **Step 5: Tulis `lang/id/auth.php`**

```php
<?php

return [

    'failed' => 'Email atau password salah.',
    'password' => 'Password yang dimasukkan salah.',
    'throttle' => 'Terlalu banyak percobaan masuk. Silakan coba lagi dalam :seconds detik.',

];
```

- [ ] **Step 6: Tulis `lang/id/pagination.php`**

```php
<?php

return [

    'previous' => '&laquo; Sebelumnya',
    'next' => 'Berikutnya &raquo;',

];
```

- [ ] **Step 7: Tulis `lang/id/passwords.php`**

```php
<?php

// 'sent' dan 'user' sengaja sama agar respons tidak membocorkan apakah email terdaftar (blueprint 00 H).

return [

    'reset' => 'Password Anda telah diatur ulang.',
    'sent' => 'Jika email tersebut terdaftar, tautan untuk mengatur ulang password telah dikirim.',
    'throttled' => 'Mohon tunggu sebelum mencoba lagi.',
    'token' => 'Tautan atur ulang password tidak valid atau sudah kedaluwarsa.',
    'user' => 'Jika email tersebut terdaftar, tautan untuk mengatur ulang password telah dikirim.',

];
```

- [ ] **Step 8: Tulis `lang/id/validation.php`**

```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Baris Bahasa Validasi
    |--------------------------------------------------------------------------
    |
    | Pesan galat bawaan aturan validasi Laravel dalam Bahasa Indonesia.
    | Form Request dapat menimpa pesan atau nama atribut sesuai kebutuhan.
    |
    */

    'accepted' => 'Kolom :attribute harus disetujui.',
    'accepted_if' => 'Kolom :attribute harus disetujui bila :other bernilai :value.',
    'active_url' => 'Kolom :attribute harus berupa URL yang valid.',
    'after' => 'Kolom :attribute harus berupa tanggal setelah :date.',
    'after_or_equal' => 'Kolom :attribute harus berupa tanggal setelah atau sama dengan :date.',
    'alpha' => 'Kolom :attribute hanya boleh berisi huruf.',
    'alpha_dash' => 'Kolom :attribute hanya boleh berisi huruf, angka, tanda hubung, dan garis bawah.',
    'alpha_num' => 'Kolom :attribute hanya boleh berisi huruf dan angka.',
    'any_of' => 'Kolom :attribute tidak valid.',
    'array' => 'Kolom :attribute harus berupa kumpulan data (array).',
    'ascii' => 'Kolom :attribute hanya boleh berisi karakter alfanumerik dan simbol satu bita.',
    'before' => 'Kolom :attribute harus berupa tanggal sebelum :date.',
    'before_or_equal' => 'Kolom :attribute harus berupa tanggal sebelum atau sama dengan :date.',
    'between' => [
        'array' => 'Kolom :attribute harus memiliki :min sampai :max item.',
        'file' => 'Ukuran berkas :attribute harus antara :min dan :max kilobita.',
        'numeric' => 'Kolom :attribute harus bernilai antara :min dan :max.',
        'string' => 'Kolom :attribute harus berisi :min sampai :max karakter.',
    ],
    'boolean' => 'Kolom :attribute harus bernilai benar atau salah.',
    'can' => 'Kolom :attribute berisi nilai yang tidak diizinkan.',
    'confirmed' => 'Konfirmasi :attribute tidak cocok.',
    'contains' => 'Kolom :attribute tidak memuat nilai yang diwajibkan.',
    'current_password' => 'Password saat ini salah.',
    'date' => 'Kolom :attribute harus berupa tanggal yang valid.',
    'date_equals' => 'Kolom :attribute harus berupa tanggal yang sama dengan :date.',
    'date_format' => 'Kolom :attribute harus sesuai format :format.',
    'decimal' => 'Kolom :attribute harus memiliki :decimal angka desimal.',
    'declined' => 'Kolom :attribute harus ditolak.',
    'declined_if' => 'Kolom :attribute harus ditolak bila :other bernilai :value.',
    'different' => 'Kolom :attribute dan :other harus berbeda.',
    'digits' => 'Kolom :attribute harus terdiri dari :digits digit.',
    'digits_between' => 'Kolom :attribute harus terdiri dari :min sampai :max digit.',
    'dimensions' => 'Dimensi gambar :attribute tidak sesuai ketentuan.',
    'distinct' => 'Kolom :attribute memiliki nilai yang duplikat.',
    'doesnt_contain' => 'Kolom :attribute tidak boleh memuat salah satu dari: :values.',
    'doesnt_end_with' => 'Kolom :attribute tidak boleh diakhiri salah satu dari: :values.',
    'doesnt_start_with' => 'Kolom :attribute tidak boleh diawali salah satu dari: :values.',
    'email' => 'Kolom :attribute harus berupa alamat email yang valid.',
    'encoding' => 'Kolom :attribute harus menggunakan pengodean :encoding.',
    'ends_with' => 'Kolom :attribute harus diakhiri salah satu dari: :values.',
    'enum' => ':Attribute yang dipilih tidak valid.',
    'exists' => ':Attribute yang dipilih tidak valid.',
    'extensions' => 'Berkas :attribute harus berekstensi salah satu dari: :values.',
    'file' => 'Kolom :attribute harus berupa berkas.',
    'filled' => 'Kolom :attribute harus diisi.',
    'gt' => [
        'array' => 'Kolom :attribute harus memiliki lebih dari :value item.',
        'file' => 'Ukuran berkas :attribute harus lebih dari :value kilobita.',
        'numeric' => 'Kolom :attribute harus lebih besar dari :value.',
        'string' => 'Kolom :attribute harus berisi lebih dari :value karakter.',
    ],
    'gte' => [
        'array' => 'Kolom :attribute harus memiliki :value item atau lebih.',
        'file' => 'Ukuran berkas :attribute harus lebih dari atau sama dengan :value kilobita.',
        'numeric' => 'Kolom :attribute harus lebih besar dari atau sama dengan :value.',
        'string' => 'Kolom :attribute harus berisi :value karakter atau lebih.',
    ],
    'hex_color' => 'Kolom :attribute harus berupa warna heksadesimal yang valid.',
    'image' => 'Kolom :attribute harus berupa gambar.',
    'in' => ':Attribute yang dipilih tidak valid.',
    'in_array' => 'Kolom :attribute harus ada di :other.',
    'in_array_keys' => 'Kolom :attribute harus memuat setidaknya salah satu kunci berikut: :values.',
    'integer' => 'Kolom :attribute harus berupa bilangan bulat.',
    'ip' => 'Kolom :attribute harus berupa alamat IP yang valid.',
    'ipv4' => 'Kolom :attribute harus berupa alamat IPv4 yang valid.',
    'ipv6' => 'Kolom :attribute harus berupa alamat IPv6 yang valid.',
    'json' => 'Kolom :attribute harus berupa teks JSON yang valid.',
    'list' => 'Kolom :attribute harus berupa daftar.',
    'lowercase' => 'Kolom :attribute harus berhuruf kecil.',
    'lt' => [
        'array' => 'Kolom :attribute harus memiliki kurang dari :value item.',
        'file' => 'Ukuran berkas :attribute harus kurang dari :value kilobita.',
        'numeric' => 'Kolom :attribute harus lebih kecil dari :value.',
        'string' => 'Kolom :attribute harus berisi kurang dari :value karakter.',
    ],
    'lte' => [
        'array' => 'Kolom :attribute tidak boleh memiliki lebih dari :value item.',
        'file' => 'Ukuran berkas :attribute harus kurang dari atau sama dengan :value kilobita.',
        'numeric' => 'Kolom :attribute harus lebih kecil dari atau sama dengan :value.',
        'string' => 'Kolom :attribute harus berisi :value karakter atau kurang.',
    ],
    'mac_address' => 'Kolom :attribute harus berupa alamat MAC yang valid.',
    'max' => [
        'array' => 'Kolom :attribute tidak boleh memiliki lebih dari :max item.',
        'file' => 'Ukuran berkas :attribute tidak boleh lebih dari :max kilobita.',
        'numeric' => 'Kolom :attribute tidak boleh lebih dari :max.',
        'string' => 'Kolom :attribute tidak boleh lebih dari :max karakter.',
    ],
    'max_digits' => 'Kolom :attribute tidak boleh lebih dari :max digit.',
    'mimes' => 'Berkas :attribute harus bertipe: :values.',
    'mimetypes' => 'Berkas :attribute harus bertipe: :values.',
    'min' => [
        'array' => 'Kolom :attribute harus memiliki minimal :min item.',
        'file' => 'Ukuran berkas :attribute minimal :min kilobita.',
        'numeric' => 'Kolom :attribute minimal bernilai :min.',
        'string' => 'Kolom :attribute minimal berisi :min karakter.',
    ],
    'min_digits' => 'Kolom :attribute minimal terdiri dari :min digit.',
    'missing' => 'Kolom :attribute tidak boleh ada.',
    'missing_if' => 'Kolom :attribute tidak boleh ada bila :other bernilai :value.',
    'missing_unless' => 'Kolom :attribute tidak boleh ada kecuali :other bernilai :value.',
    'missing_with' => 'Kolom :attribute tidak boleh ada bila :values diisi.',
    'missing_with_all' => 'Kolom :attribute tidak boleh ada bila :values diisi.',
    'multiple_of' => 'Kolom :attribute harus kelipatan :value.',
    'not_in' => ':Attribute yang dipilih tidak valid.',
    'not_regex' => 'Format :attribute tidak valid.',
    'numeric' => 'Kolom :attribute harus berupa angka.',
    'password' => [
        'letters' => 'Kolom :attribute harus memuat setidaknya satu huruf.',
        'mixed' => 'Kolom :attribute harus memuat setidaknya satu huruf besar dan satu huruf kecil.',
        'numbers' => 'Kolom :attribute harus memuat setidaknya satu angka.',
        'symbols' => 'Kolom :attribute harus memuat setidaknya satu simbol.',
        'uncompromised' => ':Attribute tersebut pernah muncul dalam kebocoran data. Silakan gunakan :attribute lain.',
    ],
    'present' => 'Kolom :attribute wajib ada.',
    'present_if' => 'Kolom :attribute wajib ada bila :other bernilai :value.',
    'present_unless' => 'Kolom :attribute wajib ada kecuali :other bernilai :value.',
    'present_with' => 'Kolom :attribute wajib ada bila :values diisi.',
    'present_with_all' => 'Kolom :attribute wajib ada bila :values diisi.',
    'prohibited' => 'Kolom :attribute tidak boleh diisi.',
    'prohibited_if' => 'Kolom :attribute tidak boleh diisi bila :other bernilai :value.',
    'prohibited_if_accepted' => 'Kolom :attribute tidak boleh diisi bila :other disetujui.',
    'prohibited_if_declined' => 'Kolom :attribute tidak boleh diisi bila :other ditolak.',
    'prohibited_unless' => 'Kolom :attribute tidak boleh diisi kecuali :other bernilai :values.',
    'prohibits' => 'Kolom :attribute melarang :other diisi.',
    'regex' => 'Format :attribute tidak valid.',
    'required' => 'Kolom :attribute wajib diisi.',
    'required_array_keys' => 'Kolom :attribute harus memuat entri untuk: :values.',
    'required_if' => 'Kolom :attribute wajib diisi bila :other bernilai :value.',
    'required_if_accepted' => 'Kolom :attribute wajib diisi bila :other disetujui.',
    'required_if_declined' => 'Kolom :attribute wajib diisi bila :other ditolak.',
    'required_unless' => 'Kolom :attribute wajib diisi kecuali :other bernilai :values.',
    'required_with' => 'Kolom :attribute wajib diisi bila :values diisi.',
    'required_with_all' => 'Kolom :attribute wajib diisi bila :values diisi.',
    'required_without' => 'Kolom :attribute wajib diisi bila :values tidak diisi.',
    'required_without_all' => 'Kolom :attribute wajib diisi bila :values semuanya tidak diisi.',
    'same' => 'Kolom :attribute harus sama dengan :other.',
    'size' => [
        'array' => 'Kolom :attribute harus berisi :size item.',
        'file' => 'Ukuran berkas :attribute harus :size kilobita.',
        'numeric' => 'Kolom :attribute harus bernilai :size.',
        'string' => 'Kolom :attribute harus berisi :size karakter.',
    ],
    'starts_with' => 'Kolom :attribute harus diawali salah satu dari: :values.',
    'string' => 'Kolom :attribute harus berupa teks.',
    'timezone' => 'Kolom :attribute harus berupa zona waktu yang valid.',
    'unique' => ':Attribute sudah digunakan.',
    'uploaded' => 'Berkas :attribute gagal diunggah.',
    'uppercase' => 'Kolom :attribute harus berhuruf besar.',
    'url' => 'Kolom :attribute harus berupa URL yang valid.',
    'ulid' => 'Kolom :attribute harus berupa ULID yang valid.',
    'uuid' => 'Kolom :attribute harus berupa UUID yang valid.',

    /*
    |--------------------------------------------------------------------------
    | Pesan Validasi Kustom
    |--------------------------------------------------------------------------
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Nama Atribut
    |--------------------------------------------------------------------------
    |
    | Nama kolom umum (blueprint 09) agar pesan tidak menampilkan nama teknis
    | berbahasa Inggris. Form Request boleh menambah lewat attributes().
    |
    */

    'attributes' => [
        'name' => 'nama',
        'full_name' => 'nama lengkap',
        'email' => 'email',
        'phone' => 'nomor telepon',
        'password' => 'password',
        'password_confirmation' => 'konfirmasi password',
        'current_password' => 'password saat ini',
        'birth_date' => 'tanggal lahir',
        'gender' => 'jenis kelamin',
        'address' => 'alamat',
        'bpjs_number' => 'nomor BPJS',
        'payment_type' => 'jenis pembayaran',
        'payment_reference' => 'nomor BPJS/polis',
        'insurance_partner_id' => 'mitra asuransi',
        'complaint' => 'keluhan',
        'reason' => 'alasan',
        'consent' => 'persetujuan',
        'title' => 'judul',
        'subtitle' => 'subjudul',
        'slug' => 'slug',
        'code' => 'kode',
        'type' => 'jenis',
        'status' => 'status',
        'category' => 'kategori',
        'description' => 'deskripsi',
        'short_description' => 'deskripsi singkat',
        'body' => 'isi',
        'excerpt' => 'ringkasan',
        'question' => 'pertanyaan',
        'answer' => 'jawaban',
        'subject' => 'subjek',
        'message' => 'pesan',
        'meta_title' => 'judul meta',
        'meta_description' => 'deskripsi meta',
        'photo' => 'foto',
        'image' => 'gambar',
        'alt_text' => 'teks alternatif',
        'caption' => 'keterangan gambar',
        'clinic_id' => 'poliklinik',
        'doctor_id' => 'dokter',
        'specialty_id' => 'spesialisasi',
        'specialties' => 'spesialisasi',
        'title_prefix' => 'gelar depan',
        'title_suffix' => 'gelar belakang',
        'short_bio' => 'profil singkat',
        'bio' => 'profil',
        'day_of_week' => 'hari',
        'start_time' => 'jam mulai',
        'end_time' => 'jam selesai',
        'start_date' => 'tanggal mulai',
        'end_date' => 'tanggal selesai',
        'session_date' => 'tanggal sesi',
        'valid_from' => 'berlaku mulai',
        'valid_until' => 'berlaku sampai',
        'quota' => 'kuota',
        'location' => 'lokasi',
        'amenities' => 'fasilitas kamar',
        'bed_capacity' => 'kapasitas tempat tidur',
        'beds_available' => 'tempat tidur tersedia',
        'sample_rate' => 'tarif contoh',
        'rate_unit' => 'satuan tarif',
        'button_label' => 'label tombol',
        'button_url' => 'tautan tombol',
        'starts_at' => 'mulai tayang',
        'ends_at' => 'selesai tayang',
        'published_at' => 'tanggal terbit',
        'website_url' => 'situs web',
        'sort_order' => 'urutan',
    ],

];
```

- [ ] **Step 9: Jalankan tes — harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $MYSQL = "$XAMPP\mysql\bin\mysql.exe"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter=LocalizationTest
```
Expected: `PASS` 8 tes (4 tes biasa + 4 kasus data provider).

- [ ] **Step 10: Commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $MYSQL = "$XAMPP\mysql\bin\mysql.exe"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git add config/app.php lang tests/Feature/LocalizationTest.php
git commit -m "feat(fase-0): zona waktu Asia/Jakarta dan terjemahan Bahasa Indonesia"
```

---

### Task 5: Pipeline aset Bootstrap 5 (tanpa Tailwind) dan halaman skeleton

**Files:**
- Create: `resources/scss/app.scss`, `resources/scss/admin.scss`, `resources/js/admin.js`, `tests/Unit/FrontendManifestTest.php`, `tests/Feature/SmokeTest.php`
- Modify: `package.json` (via npm), `vite.config.js`, `resources/js/app.js`, `resources/views/welcome.blade.php`
- Delete: `resources/css/app.css` (dan folder `resources/css`), `resources/js/bootstrap.js`, `tests/Feature/ExampleTest.php`

**Interfaces:**
- Consumes: `config('app.name')`, locale `id` (Task 3–4).
- Produces: entri Vite `resources/scss/app.scss`, `resources/scss/admin.scss`, `resources/js/app.js`, `resources/js/admin.js` (dipakai layout fase 1); `window.bootstrap` tersedia di browser; Bootstrap Icons dan font Plus Jakarta Sans dibundel lokal. Token warna dan tema UNPAM HOSPITAL **belum** dibuat (fase 1).

- [ ] **Step 1: Tulis tes yang gagal**

`tests/Unit/FrontendManifestTest.php`:

```php
<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class FrontendManifestTest extends TestCase
{
    private function path(string $file): string
    {
        return dirname(__DIR__, 2).'/'.$file;
    }

    public function test_package_json_uses_bootstrap_stack_without_tailwind(): void
    {
        $package = json_decode(file_get_contents($this->path('package.json')), true, flags: JSON_THROW_ON_ERROR);
        $all = array_merge($package['dependencies'] ?? [], $package['devDependencies'] ?? []);

        foreach (['bootstrap', '@popperjs/core', 'sass', 'bootstrap-icons', 'chart.js', 'quill', '@fontsource/plus-jakarta-sans', 'vite', 'laravel-vite-plugin', 'concurrently'] as $name) {
            $this->assertArrayHasKey($name, $all, "{$name} belum dipasang");
        }

        foreach (['tailwindcss', '@tailwindcss/vite', 'axios'] as $name) {
            $this->assertArrayNotHasKey($name, $all, "{$name} harus dihapus");
        }

        $this->assertStringStartsWith('^5.', $all['bootstrap']);
        $this->assertStringStartsWith('^4.', $all['chart.js']);
        $this->assertStringStartsWith('^2.', $all['quill']);
    }

    public function test_vite_builds_public_and_admin_entries(): void
    {
        $config = file_get_contents($this->path('vite.config.js'));

        foreach (['resources/scss/app.scss', 'resources/scss/admin.scss', 'resources/js/app.js', 'resources/js/admin.js'] as $entry) {
            $this->assertStringContainsString("'{$entry}'", $config);
            $this->assertFileExists($this->path($entry));
        }

        $this->assertStringNotContainsString('tailwind', $config);
        $this->assertFileDoesNotExist($this->path('resources/css/app.css'));
        $this->assertFileDoesNotExist($this->path('resources/js/bootstrap.js'));
    }
}
```

`tests/Feature/SmokeTest.php`:

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class SmokeTest extends TestCase
{
    public function test_home_page_renders_indonesian_skeleton(): void
    {
        $this->withoutVite();

        $this->get('/')
            ->assertOk()
            ->assertSee('<html lang="id">', false)
            ->assertSee('UNPAM HOSPITAL');
    }

    public function test_health_endpoint_is_up(): void
    {
        $this->get('/up')->assertOk();
    }
}
```

Hapus tes contoh skeleton:

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $MYSQL = "$XAMPP\mysql\bin\mysql.exe"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
Remove-Item -LiteralPath "tests\Feature\ExampleTest.php"
```

- [ ] **Step 2: Jalankan tes — harus gagal**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $MYSQL = "$XAMPP\mysql\bin\mysql.exe"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP artisan test --filter="FrontendManifestTest|SmokeTest"
```
Expected: `FrontendManifestTest` gagal (`bootstrap belum dipasang`); `SmokeTest::test_home_page_renders_indonesian_skeleton` gagal (halaman welcome bawaan tidak memuat `UNPAM HOSPITAL`); `test_health_endpoint_is_up` lulus.

- [ ] **Step 3: Ganti paket npm**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $MYSQL = "$XAMPP\mysql\bin\mysql.exe"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
npm install
npm uninstall tailwindcss @tailwindcss/vite axios
npm install bootstrap@5 @popperjs/core@2 bootstrap-icons@1 chart.js@4 quill@2 @fontsource/plus-jakarta-sans@5
npm install --save-dev sass
npm ls --depth=0
```
Expected: `npm ls` memuat `bootstrap@5.3.x`, `@popperjs/core@2.x`, `bootstrap-icons@1.x`, `chart.js@4.x`, `quill@2.x`, `@fontsource/plus-jakarta-sans@5.x`, `sass@1.x`, `vite@7.x`, `laravel-vite-plugin@2.x`, `concurrently@9.x`; tidak ada `tailwindcss`/`axios`.

- [ ] **Step 4: Ganti `vite.config.js` (tool Write)**

```js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/scss/app.scss',
                'resources/scss/admin.scss',
                'resources/js/app.js',
                'resources/js/admin.js',
            ],
            refresh: true,
        }),
    ],
    css: {
        preprocessorOptions: {
            scss: {
                // Bootstrap 5.3 masih memakai @import; sembunyikan peringatan deprecation dari dependensi.
                quietDeps: true,
                silenceDeprecations: ['import'],
            },
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
```

- [ ] **Step 5: Tulis entri SCSS dan JS**

`resources/scss/app.scss`:

```scss
// Entri gaya situs publik dan portal pasien.
// Fase 0 hanya memastikan pipeline Bootstrap 5 + Sass berjalan; token warna dan
// tema UNPAM HOSPITAL (blueprint 00 bagian M) ditambahkan di fase 1 lewat _variables.scss.
@import "bootstrap/scss/bootstrap";
```

`resources/scss/admin.scss`:

```scss
// Entri gaya dashboard admin. Tata letak admin dibangun di fase 1.
@import "bootstrap/scss/bootstrap";
```

`resources/js/app.js` (tool Write, timpa isi skeleton):

```js
import * as bootstrap from 'bootstrap';
import 'bootstrap-icons/font/bootstrap-icons.css';
import '@fontsource/plus-jakarta-sans/400.css';
import '@fontsource/plus-jakarta-sans/600.css';
import '@fontsource/plus-jakarta-sans/700.css';

window.bootstrap = bootstrap;
```

`resources/js/admin.js`:

```js
// Entri skrip dashboard admin; memuat dasar yang sama dengan situs publik.
import './app';
```

Hapus berkas Tailwind/axios bawaan:

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $MYSQL = "$XAMPP\mysql\bin\mysql.exe"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
Remove-Item -LiteralPath "resources\css" -Recurse -Force
Remove-Item -LiteralPath "resources\js\bootstrap.js"
```

- [ ] **Step 6: Ganti `resources/views/welcome.blade.php` (tool Write)**

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
<body class="bg-light">
    <main class="container py-5">
        <h1 class="h3 text-primary">
            <i class="bi bi-hospital" aria-hidden="true"></i>
            {{ config('app.name') }}
        </h1>
        <p class="mb-0">Skeleton aplikasi berjalan. Halaman situs dibangun mulai fase 1 (lihat blueprint bagian 14).</p>
    </main>
</body>
</html>
```

- [ ] **Step 7: Build aset dan jalankan tes — harus lulus**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $MYSQL = "$XAMPP\mysql\bin\mysql.exe"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
npm run build
$manifest = Get-Content "public\build\manifest.json" -Raw | ConvertFrom-Json
'resources/scss/app.scss', 'resources/scss/admin.scss', 'resources/js/app.js', 'resources/js/admin.js' | ForEach-Object { "{0} -> {1}" -f $_, [bool]$manifest.$_ }
(Get-ChildItem "public\build\assets" -Filter *.woff2 | Measure-Object).Count
& $PHP artisan test --filter="FrontendManifestTest|SmokeTest"
```
Expected: `vite build` selesai tanpa galat (`✓ built in …`); keempat entri `-> True`; jumlah berkas `.woff2` > 0 (font dan ikon lokal); tes `PASS` (4 tes).

- [ ] **Step 8: Commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $MYSQL = "$XAMPP\mysql\bin\mysql.exe"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git add -A package.json package-lock.json vite.config.js resources tests
git status --short
git commit -m "feat(fase-0): pipeline Vite Bootstrap 5 tanpa Tailwind dan halaman skeleton"
```
Expected: `public/build` dan `node_modules` tidak ikut ter-stage.

---

### Task 6: VirtualHost Apache `unpam-hospital.test` dan alternatif `artisan serve`

**Files:**
- Modify (di luar repo): `C:\xampp3\apache\conf\extra\httpd-vhosts.conf` (cadangan `httpd-vhosts.conf.bak-fase0`)
- Modify (di luar repo, MANUAL pemilik): `C:\Windows\System32\drivers\etc\hosts`

**Interfaces:**
- Consumes: aplikasi berjalan (Task 3–5), `public/build` hasil Task 5, `php.ini` baru (Task 1).
- Produces: `http://unpam-hospital.test` (DocumentRoot `"D:/Project Web RS/public"`), `http://localhost/phpmyadmin` tetap berfungsi; prosedur cadangan `php artisan serve` → `http://127.0.0.1:8000`.

- [ ] **Step 1: Periksa isi vhosts saat ini (cek awal — situs belum dapat diakses)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $MYSQL = "$XAMPP\mysql\bin\mysql.exe"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
$vh = "$XAMPP\apache\conf\extra\httpd-vhosts.conf"
Select-String -Path $vh -Pattern '^\s*<VirtualHost', 'unpam-hospital' | ForEach-Object { $_.Line }
Select-String -Path "$XAMPP\apache\conf\extra\httpd-xampp.conf" -Pattern 'PHPIniDir' | ForEach-Object { $_.Line }
curl.exe -s -o NUL -w '%{http_code}' --max-time 5 http://unpam-hospital.test/ ; ""
```
Expected: tidak ada baris `<VirtualHost` aktif (hanya contoh berkomentar) dan tidak ada `unpam-hospital`; `PHPIniDir "C:/xampp3/php"`; `curl` mencetak `000` (nama host belum dikenal).

- [ ] **Step 2: Cadangkan dan tambahkan VirtualHost**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $MYSQL = "$XAMPP\mysql\bin\mysql.exe"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
$vh = "$XAMPP\apache\conf\extra\httpd-vhosts.conf"
if (-not (Test-Path "$vh.bak-fase0")) { Copy-Item -LiteralPath $vh -Destination "$vh.bak-fase0" }
if (Select-String -Path $vh -Pattern 'ServerName unpam-hospital\.test' -Quiet) { throw "VirtualHost unpam-hospital.test sudah ada" }
$block = @'

# --- UNPAM HOSPITAL (fase 0) ---
# VirtualHost pertama menjadi default: localhost tetap melayani htdocs dan /phpmyadmin.
<VirtualHost *:80>
    ServerName localhost
    DocumentRoot "C:/xampp3/htdocs"
    <Directory "C:/xampp3/htdocs">
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
'@
[System.IO.File]::AppendAllText($vh, ($block -replace "`r?`n", "`r`n"), [System.Text.Encoding]::ASCII)
cmd /c "$XAMPP\apache\bin\httpd.exe -d $XAMPP\apache -t 2>&1"
```
Expected: `Syntax OK`.

- [ ] **Step 3: MANUAL (pemilik) — tambahkan entri hosts dengan PowerShell sebagai Administrator**

Implementer berhenti dan melaporkan `NEEDS_CONTEXT` dengan perintah berikut untuk dijalankan pemilik di **Windows PowerShell (Run as Administrator)**:

```powershell
$h = "C:\Windows\System32\drivers\etc\hosts"
if (-not (Select-String -Path $h -Pattern 'unpam-hospital\.test' -Quiet)) { Add-Content -Path $h -Value "`r`n127.0.0.1`tunpam-hospital.test" }
Select-String -Path $h -Pattern 'unpam-hospital'
ipconfig /flushdns
```
Expected (dilaporkan pemilik): baris `127.0.0.1	unpam-hospital.test` tercetak.

- [ ] **Step 4: MANUAL (pemilik) — restart Apache dari XAMPP Control Panel**

Pemilik membuka `C:\xampp3\xampp-control.exe`, menekan **Stop** lalu **Start** pada baris Apache (sekaligus memuat `php.ini` baru dari Task 1). MySQL tidak perlu di-restart.

- [ ] **Step 5: Verifikasi situs melalui Apache**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $MYSQL = "$XAMPP\mysql\bin\mysql.exe"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
"home=" + (curl.exe -s -o NUL -w '%{http_code}' --max-time 10 http://unpam-hospital.test/)
"up=" + (curl.exe -s -o NUL -w '%{http_code}' --max-time 10 http://unpam-hospital.test/up)
"phpmyadmin=" + (curl.exe -s -o NUL -w '%{http_code}' --max-time 10 http://localhost/phpmyadmin/)
(curl.exe -s --max-time 10 http://unpam-hospital.test/) -match 'UNPAM HOSPITAL'
(curl.exe -s --max-time 10 http://unpam-hospital.test/) -match '/build/assets/'
Get-ChildItem "storage\logs" -Filter "laravel-*.log" -ErrorAction SilentlyContinue | Select-Object Name
```
Expected: `home=200`, `up=200` (membuktikan `mod_rewrite` + `.htaccess` aktif), `phpmyadmin=200`, kedua `-match` → `True` (halaman memuat aset hasil build). Bila ada berkas log harian, isinya tidak memuat `ERROR`.

- [ ] **Step 6: Verifikasi alternatif `php artisan serve` (INS-04)**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $MYSQL = "$XAMPP\mysql\bin\mysql.exe"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
$p = Start-Process -FilePath $PHP -ArgumentList 'artisan', 'serve', '--host=127.0.0.1', '--port=8000' -WorkingDirectory $APP -PassThru -WindowStyle Hidden
$code = '000'
for ($i = 0; $i -lt 20 -and $code -ne '200'; $i++) { Start-Sleep -Milliseconds 500; $code = curl.exe -s -o NUL -w '%{http_code}' --max-time 2 http://127.0.0.1:8000/ }
"serve=$code"
Get-CimInstance Win32_Process -Filter "Name = 'php.exe'" | Where-Object { $_.CommandLine -match '127\.0\.0\.1:8000|artisan.+serve' } | ForEach-Object { Stop-Process -Id $_.ProcessId -Force }
Stop-Process -Id $p.Id -Force -ErrorAction SilentlyContinue
```
Expected: `serve=200`; tidak ada proses `php.exe` server yang tertinggal (`Get-CimInstance Win32_Process -Filter "Name = 'php.exe'"` tidak lagi memuat `8000`).

- [ ] **Step 7: Catat hasil (tidak ada berkas repo yang berubah)**

Laporkan keluaran Step 5–6 di laporan tugas. Task ini tidak membuat commit karena hanya mengubah konfigurasi Windows/XAMPP; cadangan ada di `httpd-vhosts.conf.bak-fase0`.

---

### Task 7: Standar format kode dan verifikasi akhir fase 0

**Files:**
- Create: `pint.json`
- Modify: berkas PHP yang dirapikan Pint (bila ada)

**Interfaces:**
- Consumes: semua tugas sebelumnya.
- Produces: gerbang fase 0 terpenuhi (kriteria keluar 14 Fase 0 + INS-01–INS-04 sejauh relevan); cabang `fase-0-lingkungan` siap ditinjau dan digabung lewat `superpowers:finishing-a-development-branch`.

- [ ] **Step 1: Tulis `pint.json`**

```json
{
    "preset": "laravel"
}
```

- [ ] **Step 2: Jalankan Pint dalam mode uji — catat temuan**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $MYSQL = "$XAMPP\mysql\bin\mysql.exe"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor/bin/pint --test
```
Expected: `PASS` atau daftar berkas yang perlu dirapikan.

- [ ] **Step 3: Rapikan bila perlu, lalu pastikan bersih**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $MYSQL = "$XAMPP\mysql\bin\mysql.exe"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP vendor/bin/pint
& $PHP vendor/bin/pint --test
```
Expected: perintah kedua `PASS`.

- [ ] **Step 4: Verifikasi gerbang fase 0**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $MYSQL = "$XAMPP\mysql\bin\mysql.exe"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
& $PHP scripts\check-env.php; "check-env exit=$LASTEXITCODE"
& $PHP artisan about --only=environment
& $PHP artisan migrate:status
& $PHP artisan test
npm run build
git status --short
```
Expected:
- `check-env exit=0` (`"status": "OK"`).
- `artisan about`: `PHP Version 8.2.12`, `Laravel Version 12.x`, `Environment local`, `Timezone Asia/Jakarta`, `Locale id`.
- `migrate:status`: tiga migration `Ran`.
- `artisan test`: semua lulus (24 tes: ComposerManifest 3, DatabaseGuard 3, RepositoryHygiene 2, Environment 4, Localization 8, FrontendManifest 2, Smoke 2), tanpa `RISKY`/`WARNING`.
- `npm run build` sukses.
- `git status` hanya memuat perubahan Pint/`pint.json` (tidak ada `.env`, `vendor`, `node_modules`, `public/build`, `public/storage`).

- [ ] **Step 5: Commit**

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"; $MYSQL = "$XAMPP\mysql\bin\mysql.exe"; $APP = "D:\Project Web RS"; $env:Path = "$XAMPP\php;$env:Path"; Set-Location $APP
git add pint.json app bootstrap config database routes tests
git status --short
git commit -m "chore(fase-0): standar Pint dan verifikasi gerbang fase 0"
git log --oneline master..HEAD
```
Expected: commit dibuat; `git log` menampilkan 6 commit fase 0 (Task 1, 2, 3, 4, 5, 7).

- [ ] **Step 6: Laporan gerbang fase**

Laporkan ke koordinator: keluaran Step 4, hasil Task 6 Step 5–6, dan status kasus manual:
- INS-01 (prasyarat): lulus bila `check-env exit=0`.
- INS-02 (pemasangan): lulus tanpa `migrate --seed` data demo (seeder dibuat mulai fase 1) dan tanpa unggah gambar.
- INS-03 (VirtualHost): `/` dan `/up` 200, phpMyAdmin 200; `/admin/login` diverifikasi di fase 1.
- INS-04 (`artisan serve`): `serve=200`.
