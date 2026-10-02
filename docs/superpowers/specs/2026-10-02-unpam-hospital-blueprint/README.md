# Blueprint UNPAM HOSPITAL

Blueprint implementasi website rumah sakit prototipe **UNPAM HOSPITAL**: website publik, portal pasien (registrasi + pendaftaran kunjungan rawat jalan), dan dashboard admin. Ditujukan sebagai pedoman implementasi Laravel 12 pada localhost Windows melalui XAMPP 8.2.12.

| Atribut | Nilai |
|---|---|
| Tanggal | 2026-10-02 |
| Status | Draf untuk review pemilik proyek |
| Tumpukan | Laravel 12 · PHP 8.2.12 · MariaDB 10.4.32 · Apache 2.4.58 (XAMPP 8.2.12, terpasang di `C:\xampp3`) · Blade + Bootstrap 5 · Vite |
| Lokasi prototipe | Universitas Pamulang Kampus 2 Viktor, Jl. Raya Puspitek, Buaran, Kec. Pamulang, Kota Tangerang Selatan, Banten 15310 |
| Sifat data | Seluruh nama dokter, foto, tarif, kontak, layanan, mitra asuransi = **data contoh fiktif** yang dapat diganti admin |

## Cara membaca

1. Mulai dari **[00 — Keputusan Inti](00-keputusan-inti.md)**: sumber kebenaran tunggal untuk semua nama (tabel, kolom, enum, route, izin, pengaturan, service). Bila ada perbedaan antara 00 dan bagian lain, **00 yang berlaku**.
2. Bagian 01–17 mengikuti urutan format keluaran yang diminta pemilik (kebutuhan §17).
3. Kebutuhan asli pemilik disimpan apa adanya di [_kebutuhan-asli.md](_kebutuhan-asli.md) untuk penelusuran.
4. Penanda: **[ASUMSI]** = ditetapkan tanpa data dari pemilik; **[KONFIRMASI]** = perlu keputusan pemilik (dirangkum di bagian 17).

## Daftar isi

| No | Bagian | Isi utama |
|---|---|---|
| 00 | [Keputusan Inti](00-keputusan-inti.md) | Keputusan disetujui, versi, enum, tabel kanonik, mesin booking, izin, route, komponen |
| 01 | [Ringkasan Proyek dan Asumsi](01-ringkasan-asumsi.md) | Tujuan, aktor, lingkungan, daftar induk asumsi, glosarium |
| 02 | [Ruang Lingkup](02-ruang-lingkup.md) | Fitur inti ber-ID, fitur lanjutan, di luar cakupan |
| 03 | [Arsitektur Aplikasi](03-arsitektur.md) | Lapisan, modul, sequence booking, kompatibilitas MariaDB, offline, lokal vs produksi |
| 04 | [Matriks Peran dan Hak Akses](04-peran-hak-akses.md) | Izin per peran, akses per halaman, policy, visibilitas data pasien |
| 05 | [Sitemap dan Matriks Halaman](05-sitemap-matriks-halaman.md) | Sitemap, navigasi, matriks 40 halaman, layar tambahan, SEO, breadcrumb |
| 06 | [Alur](06-alur.md) | Alur pasien, admin, booking, antrean, perubahan jadwal, diagram status |
| 07 | [Wireframe Tekstual](07-wireframe.md) | Design system ringkas + wireframe halaman utama desktop/mobile |
| 08 | [ERD](08-erd.md) | Diagram Mermaid ikhtisar & per domain, daftar relasi |
| 09 | [Data Dictionary](09-data-dictionary.md) | Kolom, tipe, constraint, relasi, cast, aturan hapus, urutan migration, invarian |
| 10 | [Struktur Folder dan Komponen](10-struktur-folder.md) | Pohon folder, view, komponen Blade, enum, konfigurasi |
| 11 | [Route, Controller, Model, Service, Middleware, Policy](11-route-controller-model-service.md) | Tabel route lengkap, controller, model, service + pseudo-code |
| 12 | [Aturan Bisnis, Validasi, dan Keamanan](12-aturan-bisnis-validasi-keamanan.md) | Aturan BR-xx, validasi per form, keamanan, OWASP, privasi, katalog audit |
| 13 | [Rencana Data Contoh](13-data-contoh.md) | Seeder, akun demo, dokter fiktif, jadwal, booking berbagai status |
| 14 | [Tahapan Implementasi](14-tahapan-implementasi.md) | Fase berdasarkan dependensi, kriteria keluar |
| 15 | [Pengujian dan Kriteria Penerimaan](15-pengujian-penerimaan.md) | Kasus uji, tes konkurensi, checklist manual, Definition of Done |
| 16 | [Instalasi dan Operasional Localhost](16-instalasi-operasional.md) | XAMPP, Composer, .env, migration, VirtualHost, backup, troubleshooting |
| 17 | [Keputusan yang Membutuhkan Konfirmasi](17-keputusan-konfirmasi.md) | Daftar [KONFIRMASI] + asumsi berisiko |

## Ringkasan keputusan terpenting

- **Laravel 12** dipilih karena runtime Apache XAMPP 8.2.12 memakai PHP 8.2.12 (Laravel 13 butuh PHP ≥ 8.3). Security fix Laravel 12 berlaku sampai 24 Februari 2027, tetapi **security PHP 8.2 berakhir lebih dulu, 31 Desember 2026** — cukup untuk prototipe; rencana upgrade ke PHP 8.4 + Laravel 13 sebelum produksi ada di [17 §5](17-keputusan-konfirmasi.md).
- **MariaDB 10.4** (label "MySQL" di XAMPP) didukung resmi Laravel 12 (≥ 10.3) memakai driver `mariadb`; tidak adanya partial index diatasi dengan kolom generated STORED.
- **Satu sumber jadwal** (`practice_sessions` via `DoctorAvailabilityQuery`) untuk halaman publik dan form pendaftaran.
- **Kuota aman terhadap pendaftaran bersamaan**: transaksi + `SELECT … FOR UPDATE` (urutan kunci pasien → sesi → booking) + constraint UNIQUE/CHECK sebagai pengaman kedua.
- **Walk-in** didukung: booking merujuk ke `patient_profiles`; akun login opsional.
- **Perubahan jadwal setelah ada booking** tidak pernah diam-diam: sesi terdampak ditinjau petugas (pertahankan / terapkan / batalkan) dan pasien diberi notifikasi.
- **Offline-first untuk fitur inti**: aset, font, ikon, dan placeholder lokal; Google Maps hanya pelengkap.
- **Hak akses ditegakkan di server** (middleware + policy + Form Request); Admin Konten tidak melihat data pribadi pasien.

## Keputusan yang menunggu pemilik

Semua pertanyaan terbuka dirangkum di [17 — Keputusan yang Membutuhkan Konfirmasi](17-keputusan-konfirmasi.md). Setiap butir punya default yang aman sehingga implementasi tidak terhambat; butir yang memengaruhi struktur (mis. K-07 satu akun banyak profil) sebaiknya dijawab sebelum fase terkait.

## Langkah setelah blueprint disetujui

Setiap fase di [14 — Tahapan Implementasi](14-tahapan-implementasi.md) dijadikan rencana implementasi tersendiri (skill superpowers `writing-plans`), lalu dieksekusi dengan pengembangan berbasis subagent (TDD, review per tugas, commit kecil).
