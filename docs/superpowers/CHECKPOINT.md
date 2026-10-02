# Checkpoint — UNPAM HOSPITAL

**Terakhir diperbarui:** 2026-10-02 (akhir hari kerja) · **Tag git:** `checkpoint/2026-10-02-fase-0`

Dokumen ini adalah titik lanjut untuk sesi berikutnya. Baca dari atas ke bawah sebelum bekerja.

## 1. Posisi saat ini

| Hal | Status |
|---|---|
| Blueprint (spesifikasi) | Selesai & disetujui — `docs/superpowers/specs/2026-10-02-unpam-hospital-blueprint/` (mulai dari `README.md`, lalu `00-keputusan-inti.md`). |
| Fase 0 — Lingkungan & skeleton | **Selesai**, tervalidasi (review per tugas + review akhir + gelombang perbaikan). Rencana + catatan eksekusi: `docs/superpowers/plans/2026-10-02-fase-0-lingkungan-skeleton.md`. |
| Cabang git | `fase-0-lingkungan` (11+ commit di atas `master` `79dc99d`), **belum di-merge**. Repo tanpa remote. |
| Keputusan tertunda pemilik | **Integrasi cabang fase 0**: (1) merge ke `master` lokal, (2) push + PR (butuh remote), atau (3) biarkan dulu. Lalu konfirmasi butir K di `17-keputusan-konfirmasi.md` (semua punya default; terpenting sebelum fase 5: K-07). |
| Fase berikutnya | **Fase 1 — Fondasi** (blueprint `14-tahapan-implementasi.md` §Fase 1): belum ada rencana implementasi. |

## 2. Lingkungan (mesin pemilik)

- XAMPP 8.2.12 di **`C:\xampp3`** (bukan `C:\xampp`, itu sisa lama). Apache + MySQL harus **Start** di `C:\xampp3\xampp-control.exe` sebelum bekerja.
- PHP runtime 8.2.12 (`C:\xampp3\php\php.exe`); PHP 8.4 di PATH **jangan** dipakai untuk proyek. Composer selalu `& $PHP C:\composer\composer.phar …` (bukan `composer.bat`, karakter `^` hilang).
- Database `unpam_hospital` (dev) dan `unpam_hospital_test` (uji), user `unpam_app` — password hanya di `.env`/`.env.testing` (git-ignored, jangan dihapus).
- URL: <http://unpam-hospital.test> (VirtualHost; cadangan konfigurasi `httpd-vhosts.conf.bak-fase0`), alternatif `php artisan serve` → <http://127.0.0.1:8000>.
- Catatan: tabel sistem MariaDB XAMPP pernah rusak (`mysql.db` + log Aria); sudah diperbaiki. Bila hak akses DB hilang lagi → blueprint 16 §13 baris "`GRANT` tidak tersimpan".

## 3. Cara melanjutkan (PowerShell)

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"
$env:Path = "$XAMPP\php;$env:Path"; Set-Location "D:\Project Web RS"

git status; git branch --show-current          # harus bersih, di fase-0-lingkungan
& $PHP scripts\check-env.php                    # "status": "OK"
& $PHP artisan test                             # 26 passed
curl.exe -s -o NUL -w '%{http_code}' http://unpam-hospital.test/   # 200
```

Bila semua hijau: putuskan integrasi cabang fase 0, lalu minta Claude menulis rencana **Fase 1** dengan skill `superpowers:writing-plans`, dieksekusi dengan `superpowers:subagent-driven-development` memakai subagent **Sonnet 5.5**.

## 4. Bawaan (carry) untuk fase berikutnya

Ringkas — rincian di bagian "Catatan Eksekusi" rencana fase 0:
- **Fase 1:** rekonsiliasi selisih `.env.example`/vhost vs blueprint 10/16 (M-3); rapikan skrip composer bawaan (M-6); manifest Vite menggabungkan `app.scss`/`admin.scss` yang identik (jangan andalkan field `src`).
- **Fase 2:** advisori `quill@2.0.3` (GHSA-v3m3-f69x-jf25) — wajib sanitasi purifier saat simpan.
- **Fase 5:** petakan `passwords.throttled` ke pesan generik `passwords.sent` (cegah enumerasi email).

## 5. Riwayat sesi 2026-10-02

1. Brainstorming → blueprint 17 bagian + `00-keputusan-inti.md` (disusun dengan 6 subagent penulis + 2 subagent pemeriksa konsistensi).
2. Rencana fase 0 (7 tugas) → eksekusi subagent-driven (Sonnet 5.5) → 26 tes hijau.
3. Insiden lingkungan: `mysql.db`/Aria XAMPP rusak → repair + restart MySQL; dokumentasi troubleshooting ditambahkan.
