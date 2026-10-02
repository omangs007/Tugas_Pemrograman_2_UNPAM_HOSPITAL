# Checkpoint — UNPAM HOSPITAL

**Terakhir diperbarui:** 2026-10-03 (rencana fase 1 selesai, menunggu tinjauan pemilik) · **Tag git:** `checkpoint/2026-10-02-fase-0`

Dokumen ini adalah titik lanjut untuk sesi berikutnya. Baca dari atas ke bawah sebelum bekerja.

## 1. Posisi saat ini

| Hal | Status |
|---|---|
| Blueprint (spesifikasi) | Selesai & disetujui — `docs/superpowers/specs/2026-10-02-unpam-hospital-blueprint/` (mulai dari `README.md`, lalu `00-keputusan-inti.md`). |
| Fase 0 — Lingkungan & skeleton | **Selesai**, tervalidasi (review per tugas + review akhir + gelombang perbaikan). Rencana + catatan eksekusi: `docs/superpowers/plans/2026-10-02-fase-0-lingkungan-skeleton.md`. |
| Cabang git | `fase-0-lingkungan` **sudah di-merge** ke `master` (fast-forward `7525304`, 2026-10-03; cabang dihapus). Cabang kerja sekarang: **`fase-1-fondasi`** (dari `master`). Repo tanpa remote. |
| Keputusan pemilik 2026-10-03 | Integrasi fase 0 = merge lokal ke `master`; **K-10 = (a)** urutan fase 0→1→2→3→4→5→6→7→8→9. Butir K lain di `17-keputusan-konfirmasi.md` tetap memakai default (terpenting sebelum fase 5: K-07). |
| Fase 1 — Fondasi | **Rencana selesai, belum dieksekusi**: `docs/superpowers/plans/2026-10-03-fase-1-fondasi.md` (19 tugas, ±390 langkah; rulings F1-R1–F1-R21). Menunggu tinjauan pemilik, lalu eksekusi `superpowers:subagent-driven-development` dengan subagent Sonnet 5.5 mulai Task 1. |

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

git status; git branch --show-current          # harus bersih, di fase-1-fondasi
& $PHP scripts\check-env.php                    # "status": "OK"
& $PHP artisan test                             # 26 passed
curl.exe -s -o NUL -w '%{http_code}' http://unpam-hospital.test/   # 200
```

Bila semua hijau: minta Claude mengeksekusi rencana **Fase 1** (`docs/superpowers/plans/2026-10-03-fase-1-fondasi.md`) dengan `superpowers:subagent-driven-development` memakai subagent **Sonnet 5.5**, mulai dari tugas pertama yang belum dicentang (cek `git log --oneline` untuk commit `(fase-1)` yang sudah ada).

## 4. Bawaan (carry) untuk fase berikutnya

Ringkas — rincian di bagian "Catatan Eksekusi" rencana fase 0:
- **Fase 1:** rekonsiliasi selisih `.env.example`/vhost vs blueprint 10/16 (M-3); rapikan skrip composer bawaan (M-6); manifest Vite menggabungkan `app.scss`/`admin.scss` yang identik (jangan andalkan field `src`).
- **Fase 2:** advisori `quill@2.0.3` (GHSA-v3m3-f69x-jf25) — wajib sanitasi purifier saat simpan.
- **Fase 5:** petakan `passwords.throttled` ke pesan generik `passwords.sent` (cegah enumerasi email).

## 5. Riwayat sesi 2026-10-02

1. Brainstorming → blueprint 17 bagian + `00-keputusan-inti.md` (disusun dengan 6 subagent penulis + 2 subagent pemeriksa konsistensi).
2. Rencana fase 0 (7 tugas) → eksekusi subagent-driven (Sonnet 5.5) → 26 tes hijau.
3. Insiden lingkungan: `mysql.db`/Aria XAMPP rusak → repair + restart MySQL; dokumentasi troubleshooting ditambahkan.
