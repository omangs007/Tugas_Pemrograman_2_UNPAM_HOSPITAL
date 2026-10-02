# 01 — Ringkasan Proyek dan Asumsi

> **Tujuan bagian.** Bagian ini memberi gambaran satu halaman tentang proyek UNPAM HOSPITAL: apa yang dibangun, untuk siapa, di lingkungan apa, dengan batasan apa, dan asumsi apa yang dipakai karena data dari pemilik belum ada. Pembaca yang hanya membaca satu bagian blueprint sebaiknya membaca bagian ini. Seluruh nama tabel, kolom, enum, route, izin, dan service mengikuti [00 — Keputusan Inti](00-keputusan-inti.md); bagian ini tidak mengubahnya.
>
> **Bagian terkait:** [02 — Ruang Lingkup](02-ruang-lingkup.md) · [03 — Arsitektur](03-arsitektur.md) · [04 — Peran dan Hak Akses](04-peran-hak-akses.md) · [12 — Aturan Bisnis, Validasi, dan Keamanan](12-aturan-bisnis-validasi-keamanan.md) · [16 — Instalasi dan Operasional](16-instalasi-operasional.md) · [17 — Keputusan dan Konfirmasi](17-keputusan-konfirmasi.md).
>
> **Penanda:** **[ASUMSI]** = ditetapkan tanpa data dari pemilik; **[KONFIRMASI]** = butuh keputusan pemilik sebelum produksi.

---

## 1. Ringkasan Proyek

### 1.1 Tujuan proyek

UNPAM HOSPITAL adalah **prototipe website rumah sakit** yang dapat dijalankan penuh di localhost Windows (XAMPP) tanpa layanan berbayar dan tanpa koneksi internet untuk fitur inti. Prototipe memiliki tiga tujuan:

1. **Menyajikan informasi** rumah sakit (profil, layanan, dokter, jadwal, fasilitas, kamar, BPJS/asuransi, artikel, kontak) yang seluruhnya dapat dikelola admin.
2. **Memungkinkan pasien mendaftar kunjungan rawat jalan secara online**: registrasi akun, memilih poliklinik, dokter, tanggal, dan sesi praktik, mendapat kode booking, nomor antrean, dan bukti pendaftaran.
3. **Memberi petugas alat kerja** untuk mengelola konten, jadwal dokter, pasien, pendaftaran, dan antrean harian, termasuk pasien datang langsung (walk-in) yang berbagi kuota dengan pendaftaran online.

Proyek ini **bukan** sistem informasi rumah sakit (SIMRS), bukan rekam medis, dan tidak terhubung ke sistem eksternal mana pun (lihat [02 — Ruang Lingkup](02-ruang-lingkup.md)).

### 1.2 Tiga bagian utama

| Bagian | Isi | Halaman (nomor di 00 bagian I) | Pengguna utama |
|---|---|---|---|
| **Website publik** | Informasi rumah sakit, layanan, dokter, jadwal, fasilitas, kamar, BPJS/asuransi, panduan, artikel, galeri, FAQ, kontak, kebijakan privasi | 1–22 + `galleries.show` + `privacy` | Tamu (siapa saja), pasien |
| **Autentikasi dan portal pasien** | Registrasi, login, reset password, dashboard, profil, pendaftaran kunjungan (wizard), riwayat, pembatalan, bukti pendaftaran | 23–30 | Tamu (registrasi/login), pasien |
| **Dashboard admin** | Login staf, dashboard, dokter, spesialisasi/poliklinik, jadwal/kuota/libur, fasilitas/kamar, pasien, pendaftaran/antrean, konten, pengaturan/staf/hak akses/audit | 31–40 + kotak pesan, media | Super Admin, Admin Konten, Petugas Pendaftaran |

Ketiganya adalah **satu aplikasi monolit Laravel 12** dengan Blade + Bootstrap 5 (keputusan K2): bukan Filament, bukan SPA. Satu database, satu tabel `users`, satu guard `web`.

### 1.3 Pengguna dan kebutuhannya

| Aktor | Siapa | Kebutuhan utama | Yang tidak boleh terjadi |
|---|---|---|---|
| **Tamu** | Pengunjung tanpa login | Mencari dokter, melihat jadwal dan kuota, melihat fasilitas dan kamar, membaca artikel/FAQ, menemukan lokasi (alamat teks, peta, petunjuk arah), mengirim pesan kontak, mendaftar akun | Melihat data pasien; membuat pendaftaran tanpa login |
| **Pasien** | Akun `account_type = patient` dengan satu `patient_profiles` | Mengelola profil sendiri, mendaftar kunjungan dengan sedikit langkah, melihat kuota tersisa, mendapat kode booking dan nomor antrean, melihat riwayat, membatalkan sesuai aturan, mencetak/mengunduh bukti, diberi tahu bila jadwal berubah | Melihat atau mengubah data pasien lain; mendaftar ganda atau pada jadwal yang bentrok; mengakses area admin |
| **Super Admin** | Staf peran `super-admin` | Seluruh pengaturan website, akun staf, hak akses, audit log, plus semua fungsi peran lain | Melewati aturan mutlak: peran `super-admin` terkunci, minimal satu Super Admin aktif, tidak menonaktifkan atau menurunkan akun sendiri (ditegakkan di service/Form Request karena `Gate::before` melewati Policy; lihat [04](04-peran-hak-akses.md)) |
| **Admin Konten** | Staf peran `content-admin` | Mengelola dokter, spesialisasi/poliklinik, jadwal/kuota/libur, fasilitas, kamar, halaman, banner, artikel, galeri, FAQ, mitra asuransi | Melihat data pribadi pasien; menyelesaikan sesi terdampak; mengubah pengaturan atau akun staf |
| **Petugas Pendaftaran** | Staf peran `registration-officer` | Mencari/membuat profil pasien walk-in, mendaftarkan kunjungan, mengonfirmasi, check-in, menyelesaikan, membatalkan, menandai tidak hadir, menyelesaikan sesi terdampak, mengekspor CSV, membalas pesan kontak, memperbarui ketersediaan kamar | Mengubah konten website atau dokter; mengelola akun staf atau pengaturan |

Pasien walk-in tanpa akun **bukan aktor sistem**: mereka tidak login. Profilnya dibuat dan dikelola Petugas Pendaftaran (keputusan K1).

### 1.4 Nilai prototipe

- **Dapat didemokan penuh secara offline** di satu laptop: seluruh alur dari beranda sampai bukti pendaftaran dan antrean harian berjalan tanpa internet (peta tersemat adalah satu-satunya fitur opsional yang butuh internet).
- **Membuktikan bagian yang paling berisiko**: mesin jadwal tiga lapis, kuota yang tidak bisa terlampaui saat pendaftaran bersamaan, perubahan jadwal setelah ada booking, dan pemisahan hak akses di sisi server.
- **Fondasi yang dapat dikembangkan**: lapisan service terpisah dari controller, sehingga integrasi lanjutan (BPJS, SATUSEHAT, SIMRS, WhatsApp API, payment gateway) ditambahkan tanpa menulis ulang inti ([02 — Ruang Lingkup](02-ruang-lingkup.md), bagian fitur lanjutan).
- **Data contoh fiktif lengkap** sehingga setiap layar punya isi saat dipresentasikan ([13 — Data Contoh](13-data-contoh.md)).
- **Dokumentasi yang dapat dieksekusi**: setiap fitur memiliki halaman, route, tabel, izin, dan alur yang konsisten ([05](05-sitemap-matriks-halaman.md), [06](06-alur.md), [09](09-data-dictionary.md), [11](11-route-controller-model-service.md)).

### 1.5 Kriteria sukses tingkat tinggi

Kriteria rinci dan skenario ada di [15 — Pengujian dan Penerimaan](15-pengujian-penerimaan.md). Ringkasannya:

| ID | Kriteria sukses | Bukti |
|---|---|---|
| KS-01 | Seluruh 40 halaman utama dan layar tambahan dapat diakses sesuai matriks hak akses; tidak ada halaman tanpa route, tabel sumber, dan izin | Uji akses per aktor ([04](04-peran-hak-akses.md), [15](15-pengujian-penerimaan.md)) |
| KS-02 | Alur login → poliklinik → dokter → tanggal/sesi → cek kuota → isi data → konfirmasi → bukti selesai, **termasuk dengan JavaScript dimatikan** | Uji manual dan uji fitur |
| KS-03 | Uji konkurensi: 20 permintaan serentak ke sesi dengan sisa kuota 5 menghasilkan tepat 5 booking, 15 ditolak `QUOTA_FULL`, `booked_count = 5`, nomor antrean 1–5 unik | Skrip konkurensi pada Apache (bukan `php artisan serve`) |
| KS-04 | Tidak ada pendaftaran aktif ganda pada sesi yang sama dan tidak ada benturan jam untuk pasien yang sama, bahkan bila dikirim bersamaan | Uji konkurensi + constraint UNIQUE |
| KS-05 | Jadwal publik dan jadwal pada wizard/walk-in berasal dari satu sumber (`DoctorAvailabilityQuery`) | Uji perbandingan keluaran |
| KS-06 | Semua pembatasan peran ditegakkan di server: akses langsung ke URL/POST tanpa lewat menu ditolak | Uji tembak langsung per route |
| KS-07 | Admin Konten tidak pernah melihat data pribadi pasien; nomor BPJS/polis hanya tampil 4 digit terakhir; CSV tanpa data sensitif | Uji visibilitas data |
| KS-08 | Seluruh halaman publik, portal pasien, dan admin tampil dengan benar tanpa internet (kecuali peta tersemat, yang memiliki fallback alamat teks) | Uji dengan jaringan diputus |
| KS-09 | Perubahan konten/jadwal oleh admin langsung tampil di halaman publik | Uji CRUD → publik |
| KS-10 | Memenuhi WCAG 2.1 AA pada token warna, navigasi keyboard, label form, dan alt text; tampilan benar di 576/768/992/1200 px | Audit aksesibilitas dan uji responsif |
| KS-11 | Seeder demo menghasilkan ≥ 15 dokter, ≥ 10 spesialisasi, 4 kategori kamar, dan booking berbagai status | Hitung data setelah `php artisan migrate --seed` |
| KS-12 | Backup (database + file unggahan + `APP_KEY`) dapat dipulihkan pada instalasi bersih | Uji restore |
| KS-13 | Tidak ada teks, logo, atau tombol yang menyiratkan akreditasi, kerja sama, atau integrasi yang belum ada | Tinjauan UI ([02](02-ruang-lingkup.md)) |
| KS-14 | Instalasi dari nol mengikuti panduan 16 tanpa langkah yang tidak terdokumentasi, lalu seluruh uji penerimaan lulus | Uji instalasi bersih |

---

## 2. Lingkungan dan Versi (eksplisit)

### 2.1 Tabel versi

Sumber: 00 bagian B. Tabel ini adalah satu-satunya rujukan versi di seluruh blueprint.

| Komponen | Versi / Nilai | Catatan |
|---|---|---|
| Sistem operasi | Windows 11 Pro | Mesin pemilik |
| Paket XAMPP | `xampp-windows-x64-8.2.12-0-VS16` | **Terpasang di `C:\xampp3`**; Apache & MariaDB berjalan |
| Apache | 2.4.58 | `mod_rewrite` wajib aktif |
| PHP (runtime Apache) | **8.2.12** (Thread Safe, VS16) | Bawaan XAMPP |
| Database | **MariaDB 10.4.32** (XAMPP menyebutnya "MySQL") | `DB_CONNECTION=mariadb` |
| Framework | **Laravel 12.x** | PHP 8.2–8.5 |
| PHP CLI lain | 8.4.26 di `C:\php\8.4` (ada di PATH) | Bukan runtime Apache |
| Composer | 2.9.2 | Terpasang |
| Node.js / npm | 24.12.0 / 11.6.2 | Hanya build aset |
| Git | 2.45 | Repo di `D:\Project Web RS` |
| Zona waktu / locale | `Asia/Jakarta` / `id` (fallback `en`, faker `id_ID`) | `config/app.php` |
| Charset database | `utf8mb4` / `utf8mb4_unicode_ci`, InnoDB | |

Ekstensi PHP wajib: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `fileinfo`, `gd` (JPEG/PNG/WebP), `intl`, `zip`, `bcmath`, `curl`. Daftar paket Composer dan npm ada di 00 bagian B.1; cara memeriksa dan mengaktifkannya ada di [16 — Instalasi dan Operasional](16-instalasi-operasional.md).

### 2.2 Mengapa Laravel 12, bukan Laravel 13

| Pertimbangan | Penjelasan |
|---|---|
| **Kompatibilitas PHP** | Laravel 12 mendukung PHP 8.2–8.5. Laravel 13 membutuhkan PHP ≥ 8.3, sedangkan runtime Apache dari paket XAMPP 8.2.12 adalah PHP 8.2.12. Memakai Laravel 13 berarti tidak bisa berjalan di Apache XAMPP yang sudah diunduh pemilik. |
| **Kompatibilitas MariaDB** | Laravel 12 secara resmi mendukung MariaDB 10.3+, sehingga MariaDB 10.4.32 bawaan XAMPP didukung dengan driver `mariadb`. |
| **Satu runtime untuk web dan CLI** | Dengan Laravel 12, `artisan`, PHPUnit, dan Apache dapat memakai versi PHP yang sama (8.2.12), sehingga perilaku pengujian sama dengan perilaku web. |
| **Ekosistem paket** | Paket yang dipilih (Spatie Permission, Intervention Image 3, Purifier, dompdf, chillerlan QR) tersedia untuk Laravel 12 dan PHP 8.2. |

### 2.3 Tanggal dukungan dan implikasinya

| Komponen | Dukungan | Implikasi untuk proyek |
|---|---|---|
| Laravel 12 | Bug fix berakhir **13 Agustus 2026** (sudah lewat per 2 Oktober 2026); **security fix sampai 24 Februari 2027** | Untuk prototipe localhost cukup. Hanya perbaikan keamanan yang masih diterbitkan; jadwal upgrade dibahas di [17](17-keputusan-konfirmasi.md). |
| PHP 8.2 (XAMPP) | Perbaikan keamanan PHP 8.2 berakhir **31 Desember 2026** (terverifikasi di php.net/supported-versions, 2026-10-02); patch 8.2.12 bawaan XAMPP berasal dari Oktober 2023 | Batas praktis runtime lebih awal daripada Laravel 12. Untuk produksi, runtime harus PHP ≥ 8.3 (disarankan 8.4, keamanan s.d. 31 Desember 2028) sehingga Laravel 13 dapat dipakai. |
| MariaDB 10.4 | Sudah melewati akhir masa dukungan vendor (2024) | Cukup untuk prototipe localhost. **[KONFIRMASI]** Produksi memakai MariaDB ≥ 10.11 LTS atau MySQL 8.4 LTS. |
| Laravel 13 | Butuh PHP ≥ 8.3 | Rencana upgrade dicatat di bagian 17; arsitektur tidak memakai fitur khusus Laravel 12 yang menyulitkan upgrade (service terpisah, tanpa paket tidak terpelihara). |

### 2.4 Fakta mesin pemilik dan konsekuensinya

| Fakta | Konsekuensi |
|---|---|
| **XAMPP 8.2.12 terpasang di `C:\xampp3`**; Apache (80/443) dan MariaDB (3306) sudah berjalan. `C:\xampp` hanya sisa instalasi lama dan tidak dipakai | Panduan 16 memakai `C:\xampp3` (variabel `$XAMPP`). Di `php.ini` ekstensi `gd`, `intl`, `zip` masih dikomentari dan `date.timezone=Europe/Berlin` → wajib diaktifkan/diubah sebelum instalasi Laravel. |
| **PHP CLI 8.4.26 ada di PATH** (`C:\php\8.4`), berbeda dari PHP Apache 8.2.12 | `composer.json` memuat `"config": {"platform": {"php": "8.2.12"}}` agar Composer memilih versi paket yang cocok dengan runtime Apache. Perintah `artisan` dan PHPUnit dijalankan dengan `C:\xampp3\php\php.exe` (atau PATH sesi diawali `C:\xampp3\php`) agar ekstensi dan `php.ini` sama dengan Apache. |
| **Composer 2.9.2 dan Node 24 sudah ada** | Tidak perlu instalasi tambahan; Node hanya dipakai saat `npm run build`/`npm run dev`. |
| **Path proyek mengandung spasi** (`D:\Project Web RS`) | `DocumentRoot "D:/Project Web RS/public"` wajib diberi tanda kutip di VirtualHost; skrip PowerShell memakai tanda kutip pada semua path. |
| **Skeleton Laravel 12 memuat `laravel/pail`** yang butuh `pcntl` (tidak ada di Windows) | `pail` dihapus dari skrip `composer dev`; gunakan `concurrently` untuk `php artisan serve` + `npm run dev`. |

### 2.5 Konvensi lintas-bagian (ringkas)

Identifier kode berbahasa Inggris; URL dan label UI berbahasa Indonesia; status disimpan sebagai `VARCHAR` + PHP backed enum; uang `DECIMAL(12,2)` Rupiah; data yang dirujuk riwayat booking tidak pernah dihapus permanen. Rincian: 00 bagian C dan D.

---

## 3. Batasan Prototipe

### 3.1 Data fiktif dan placeholder

- Nama dokter, foto, tarif, layanan, artikel, FAQ, banner, dan informasi operasional adalah **data contoh** yang dapat diganti admin. Foto memakai placeholder SVG lokal (`public/images/placeholders/`).
- Telepon `(021) 0000-0000`, telepon darurat `(021) 0000-0119`, WhatsApp `6280000000000`, dan email `info@unpamhospital.test` adalah **placeholder yang jelas**, bukan nomor/alamat resmi, sampai pemilik menyediakan data resmi lewat layar pengaturan.
- Alamat lokasi prototipe: Universitas Pamulang Kampus 2 Viktor, Jl. Raya Puspitek, Buaran, Kecamatan Pamulang, Kota Tangerang Selatan, Banten 15310.
- Mitra asuransi pada seeder bersifat **fiktif dan berlabel "Contoh"**.
- Akun demo (satu per peran dan satu pasien) hanya dibuat oleh `DemoSeeder` di lingkungan `local`/`testing`; **tidak pernah di produksi** (Super Admin produksi dibuat dengan `php artisan app:create-super-admin`).

### 3.2 Tidak ada klaim akreditasi, kerja sama, atau integrasi

UNPAM HOSPITAL pada tahap ini tidak boleh menampilkan: logo/status akreditasi, klaim kerja sama dengan BPJS atau asuransi tertentu, klaim terhubung SATUSEHAT/SIMRS, atau kata "real-time" untuk ketersediaan kamar. Tombol WhatsApp hanyalah tautan `https://wa.me/{nomor}`; bukan pengiriman otomatis (dijelaskan di [02](02-ruang-lingkup.md)). Footer menampilkan penanda **"Prototipe — data contoh"** (**[ASUMSI]** A-35).

### 3.3 Kategori kamar "BPJS Kelas 1/2/3/VIP"

Empat kategori kamar (`bpjs-kelas-1`, `bpjs-kelas-2`, `bpjs-kelas-3`, `vip`) adalah **nama prototipe yang bisa diubah admin**, mengikuti kebutuhan pemilik. Nama tersebut **bukan ketentuan BPJS yang sudah diverifikasi**. Kebijakan kelas rawat inap Jaminan Kesehatan Nasional sedang/telah berubah ke arah **Kelas Rawat Inap Standar (KRIS)**, sehingga pembagian "Kelas 1/2/3" dapat sudah tidak berlaku. Karena itu:

- Teks pada halaman kamar dan halaman info BPJS bersifat informasi umum dan dapat diedit admin; tidak boleh disajikan sebagai hak peserta yang pasti.
- **[KONFIRMASI]** Sebelum produksi, pemilik harus memverifikasi ketentuan terbaru ke sumber resmi BPJS Kesehatan/Kementerian Kesehatan, lalu mengubah nama kategori, deskripsi, dan tarif melalui admin atau seeder (tanpa perubahan struktur karena kategori disimpan sebagai data, bukan enum).

### 3.4 Referensi rumahsakitmulya.com

Situs rumahsakitmulya.com dipakai **hanya sebagai referensi struktur informasi dan pengalaman pengguna** (menu, pengelompokan halaman, alur mencari dokter). Desain UNPAM HOSPITAL orisinal: logo placeholder sendiri, palet biru–toska sendiri, teks dan foto sendiri. **Tidak ada logo, foto, atau teks yang disalin.**

### 3.5 Batas keamanan dan hukum untuk produksi

- Prototipe menerapkan praktik keamanan yang layak (CSRF, hash password, otorisasi server, sanitasi, enkripsi kolom nomor BPJS/polis, audit), tetapi **belum melalui uji penetrasi, penilaian dampak privasi, atau telaah hukum**.
- Teks kebijakan privasi dan persetujuan adalah contoh. **[KONFIRMASI]** Sebelum produksi, teks tersebut ditelaah penasihat hukum terhadap peraturan pelindungan data pribadi yang berlaku (mis. UU Pelindungan Data Pribadi) dan ketentuan data kesehatan.
- Blueprint tidak mengklaim kepatuhan terhadap standar atau regulasi tertentu.

### 3.6 Keterbatasan teknis lingkungan lokal

- Server pengembangan bawaan `php artisan serve` di Windows memproses permintaan secara berurutan, sehingga **tidak dapat dipakai untuk membuktikan perilaku konkurensi**; uji konkurensi dilakukan melalui Apache XAMPP.
- Email lokal tidak benar-benar terkirim: `MAIL_MAILER=log` menulis ke log (opsional Mailpit pada `127.0.0.1:1025`).
- Tidak ada Task Scheduler/cron untuk fitur inti: sesi praktik dibangkitkan secara lazy (maksimal sekali per hari saat halaman jadwal/wizard dibuka) atau lewat perintah manual.

---

## 4. Daftar Induk Asumsi

Format: ID · pernyataan · alasan · dampak bila salah. Asumsi A-01 sampai A-06 diambil langsung dari penanda **[ASUMSI]** di 00; sisanya ditetapkan oleh blueprint karena data pemilik belum tersedia. Setiap bagian lain merujuk ID ini.

### 4.1 Asumsi yang berasal dari 00

| ID | Pernyataan | Alasan | Dampak bila salah |
|---|---|---|---|
| A-01 | `users.email_verified_at` nullable dan **verifikasi email tidak diwajibkan** untuk pasien (00 E.1) | Verifikasi butuh email produksi; lokal hanya `log` | Jika diwajibkan: tambahkan `MustVerifyEmail` + middleware `verified` ([02](02-ruang-lingkup.md), F-NXT-10). Tanpa verifikasi, seseorang dapat mendaftar memakai email orang lain (risiko pengambilalihan nama email); mitigasi: reset password hanya lewat email tercatat dan respons generik |
| A-02 | Granularitas `schedule_exceptions` adalah **satu hari penuh**; perubahan sebagian hari dilakukan dengan mengedit `practice_sessions` (00 E.2) | Menyederhanakan tumpang tindih dengan sesi per tanggal | Jika butuh cuti setengah hari: tambah kolom jam pada pengecualian dan ubah `ScheduleChangeService` |
| A-03 | `booking.max_active_per_patient` = **3** untuk pendaftaran online (00 E.7, F.5) | Mencegah satu akun menahan banyak kuota | Terlalu kecil: pasien kronis kesulitan; terlalu besar: penimbunan kuota. Dapat diubah di pengaturan tanpa kode |
| A-04 | Petugas Pendaftaran memiliki izin `rooms.update-availability` (00 G) | Petugas di loket paling cepat mengetahui sisa tempat tidur | Jika tidak: Petugas tidak melihat layar ketersediaan; cukup cabut izin lewat hak akses |
| A-05 | Petugas Pendaftaran memiliki izin `messages.manage` (00 G) | Pesan kontak sering berisi pertanyaan pendaftaran | Jika tidak: pesan hanya ditangani Super Admin; cabut izin lewat hak akses |
| A-06 | Grafik dashboard menghitung pendaftaran per **tanggal kunjungan** (`session_date`), bukan tanggal dibuat (00 L) | Sesuai kebutuhan beban kerja poliklinik | Jika yang diinginkan tanggal dibuat: ubah query `DashboardMetrics` dan label grafik |

### 4.2 Asumsi domain dan data

| ID | Pernyataan | Alasan | Dampak bila salah |
|---|---|---|---|
| A-07 | Satu rumah sakit, satu lokasi, satu zona waktu (WIB, tanpa perubahan musiman), satu bahasa (Indonesia) | Kebutuhan §3 dan §2 menyebut satu alamat dan satu zona | Multi-cabang butuh tabel lokasi dan penyaringan di hampir semua entitas |
| A-08 | **Satu akun pasien = satu profil pasien** (`patient_profiles.user_id` unik); pendaftaran untuk anggota keluarga dilakukan lewat loket (profil walk-in) atau akun terpisah | Sesuai K1 dan constraint di 00 E.1; menjaga scoping kepemilikan sederhana | Jika pemilik ingin satu akun banyak profil: ubah constraint, tambah pilihan "untuk siapa" pada wizard (F-NXT-15). **[KONFIRMASI]** K-07 |
| A-09 | Kuota berlaku **per sesi** (bukan per slot waktu); nomor antrean adalah **urutan pendaftaran** pada sesi, bukan jam panggilan | Sesuai model `practice_sessions.quota` dan F.7 | Jika butuh slot per 15 menit: model sesi berubah besar. Pemanggilan antrean tidak dibangun |
| A-10 | Setiap sesi berada dalam **satu hari kalender** (`end_time > start_time`, tidak melewati tengah malam) | CHECK di 00 E.2; praktik poliklinik rawat jalan | Praktik malam lintas hari harus dipecah dua sesi |
| A-11 | **Dokter bukan pengguna sistem**: tidak ada login dokter; `doctors` hanya data profil/jadwal | Peran yang diminta hanya tiga (kebutuhan §10) | Fitur klinis di masa depan butuh peran dan tautan `doctors` ↔ `users` |
| A-12 | Nomor BPJS divalidasi **13 digit angka**; nomor polis asuransi teks bebas opsional (maks. 30 karakter); keduanya **tidak diverifikasi** ke pihak luar | Tidak ada integrasi (kebutuhan §14) | Nomor salah tidak terdeteksi; petugas memeriksa kartu fisik di loket |
| A-13 | Nomor BPJS/polis **disamarkan di semua tampilan** (4 digit terakhir) dan tidak ada aksi "tampilkan nomor lengkap" pada prototipe | Minimasi data sensitif (kebutuhan §12) | Jika petugas butuh nomor lengkap: tambah aksi eksplisit tercatat di audit. **[KONFIRMASI]** K-04 |
| A-14 | Nomor BPJS tidak diminta saat registrasi; pada wizard diminta hanya saat jenis pembayaran BPJS dipilih, dan pasien **boleh** mencentang "simpan ke profil" (`patient_profiles.bpjs_number`) | Kebutuhan §8; kolom profil sudah ada di 00 | Jika tidak ingin disimpan: hapus centang; kolom tetap kosong |
| A-15 | Menonaktifkan profil pasien berakun (`admin.patients.status`) juga menyetel `users.is_active = false` agar login terblokir (→ diputuskan di 00 E.1 / 17 D-08; menonaktifkan/mengaktifkan kembali ditegakkan bersama) | Menghindari akun aktif tanpa profil aktif | Jika hanya profil yang nonaktif: pasien masih bisa login tetapi tidak bisa mendaftar |
| A-16 | Pembatalan oleh pasien **tanpa penalti**; status `no_show` tidak memblokir pasien secara otomatis | Tidak ada kebijakan denda dari pemilik | Penyalahgunaan kuota dibatasi oleh `booking.max_active_per_patient` dan limiter `booking` |
| A-17 | Notifikasi status/jadwal dikirim **hanya ke pasien berakun**: kanal `database` untuk setiap transisi, kanal `mail` hanya untuk `confirmed`, `cancelled`, dan perubahan jadwal (00 F.8; default K-13 di 17). Pasien walk-in dihubungi petugas lewat telepon tercatat | 00 F.8 | Pasien walk-in tanpa telepon valid tidak terjangkau; pelacakan "sudah dihubungi" tidak dibangun (default K-15) |
| A-18 | Kamar dimodelkan **agregat per kategori** (`bed_capacity`, `beds_available`); `beds_available` diisi manual; `sample_rate` hanya informasi, bukan harga mengikat | 00 E.4; kebutuhan §7 | Per-kamar/per-tempat-tidur butuh tabel `rooms` baru |
| A-19 | Persetujuan privasi diberikan sekali saat registrasi atau dicentang petugas saat walk-in; perubahan `privacy.consent_version` **tidak** memaksa persetujuan ulang pada prototipe | Persetujuan ulang bukan kebutuhan prototipe | Untuk produksi, persetujuan ulang perlu dibangun |
| A-20 | Teks kebijakan privasi dan persetujuan adalah **contoh** dan butuh telaah hukum sebelum produksi | Blueprint tidak memberi nasihat hukum | Risiko kepatuhan. **[KONFIRMASI]** K-06 |
| A-21 | Rich text hanya diisi staf tepercaya, tetapi **tetap disanitasi** saat disimpan (hanya lima kolom di 00 C yang boleh dirender `{!! !!}`) | Pertahanan berlapis | Tanpa sanitasi, staf yang akunnya diambil alih dapat menyisipkan skrip |
| A-22 | Layanan penunjang (`ClinicType::penunjang`) pada data contoh **tidak dapat dibooking** (`is_bookable = false`); pendaftaran online hanya untuk rawat jalan poliklinik | Kebutuhan §8 membatasi portal pada pendaftaran kunjungan rawat jalan | Jika penunjang perlu dibooking, setel `is_bookable = true` dan sediakan templat jadwal |
| A-23 | Slug dibuat otomatis dari judul/nama, unik, dan dapat diubah admin sebelum terbit | Konvensi 00 C | Mengubah slug halaman terbit memutus tautan lama (tidak ada pengalihan otomatis pada prototipe) |

### 4.3 Asumsi akses dan keamanan

| ID | Pernyataan | Alasan | Dampak bila salah |
|---|---|---|---|
| A-24 | **Satu peran per akun staf** pada UI; izin langsung ke pengguna (`model_has_permissions`) tidak dipakai | Menyederhanakan audit dan tampilan hak akses | Staf lintas peran harus dibuat dua akun atau peran baru |
| A-25 | Matriks izin di 00 G adalah **hasil seeder awal**; Super Admin dapat mengubahnya lewat `admin.roles.edit`, kecuali peran `super-admin` yang terkunci | 00 G menyatakan hanya `super-admin` terkunci | Jika izin tidak boleh diubah sama sekali: sembunyikan layar hak akses |
| A-26 | **Izin terkunci per peran** pada layar hak akses: izin khusus Super Admin (`staff.manage`, `settings.manage`, `audit.view`) tidak dapat diberikan ke peran lain; izin data pasien (`patients.*`, `appointments.*`, `reports.export`, `messages.manage`) tidak dapat diberikan ke `content-admin` | Menjaga aturan "Admin Konten tidak melihat data pribadi pasien" dan mencegah eskalasi hak | Tanpa pembatasan, Super Admin dapat melanggar aturan itu sendiri lewat UI |
| A-27 | Perilaku penolakan akses: tamu → redirect ke halaman login sesuai area; akun salah tipe atau tanpa izin → **403**; halaman khusus tamu saat sudah login → redirect ke dashboard sesuai tipe akun; booking milik orang lain → **404** | 00 tidak mendefinisikan; ini praktik umum yang tidak membocorkan keberadaan data | Uji penerimaan mengikuti aturan ini; bila diubah, ubah matriks [04](04-peran-hak-akses.md) |
| A-28 | Aturan mutlak (peran `super-admin` terkunci, minimal satu Super Admin aktif, tidak menonaktifkan diri sendiri) ditegakkan di **`StaffAccountService` dan Form Request**, bukan Policy, karena `Gate::before` meloloskan Super Admin dari Policy (→ diputuskan di 00 G / 17 D-13) | Lihat [04](04-peran-hak-akses.md) bagian penegakan | Bila hanya di Policy, Super Admin dapat melewatinya |

### 4.4 Asumsi lingkungan dan operasional

| ID | Pernyataan | Alasan | Dampak bila salah |
|---|---|---|---|
| A-29 | Pengujian otomatis berjalan pada **MariaDB** (database terpisah `.env.testing`), bukan SQLite | Kolom generated, CHECK, dan `FOR UPDATE` tidak setara di SQLite | Uji SQLite memberi rasa aman palsu untuk kuota dan constraint |
| A-30 | Beban prototipe kecil: ≤ 100 dokter, ≤ 50.000 appointment, puluhan pengguna bersamaan | Prototipe localhost | Beban produksi nyata butuh uji beban dan penyetelan indeks/cache |
| A-31 | Pengguna memakai peramban modern (dua versi terakhir Chrome, Edge, Firefox, Safari); JavaScript aktif untuk penyempurnaan tetapi wizard tetap berjalan tanpa JS | Bootstrap 5.3 dan Blade server-render | Peramban lama (mis. IE) tidak didukung |
| A-32 | Internet dibutuhkan hanya untuk instalasi/build awal (Composer, npm) dan peta tersemat; runtime inti tidak memerlukannya | Kebutuhan §2 dan §16 | Ketergantungan tersembunyi pada CDN merusak mode offline; font, ikon, grafik, editor dibundel lokal |
| A-33 | Sesi database MariaDB memakai zona `+07:00` (`timezone` pada koneksi) agar `TIMESTAMP` konsisten dengan `Asia/Jakarta` di mesin mana pun | Menghindari pergeseran jam saat DB di server berzona lain | Tanpa ini, jam booking bisa bergeser saat dipindah server |
| A-34 | Format tampilan: tanggal "2 Oktober 2026", jam 24 jam dengan titik ("08.00"), Rupiah "Rp 150.000"; `Carbon::setLocale('id')` | Kenyamanan pengguna Indonesia | Hanya kosmetik |
| A-35 | Footer seluruh halaman publik dan layar login menampilkan penanda **"Prototipe — data contoh"** | Mencegah salah paham bahwa data adalah resmi | Tanpa penanda, pengunjung dapat mengira data nyata |
| A-36 | Kredensial akun demo hanya ada di `DemoSeeder` lokal dan tidak boleh ada di dokumen produksi | Kebutuhan §13 | Kebocoran akun demo ke produksi |
| A-37 | Port default XAMPP (Apache 80, MariaDB 3306) tersedia; bila tidak, port diubah di XAMPP dan `.env`, atau gunakan `php artisan serve` pada 8000 | Praktik umum | Instalasi gagal start sampai port diubah |
| A-38 | Backup lokal dilakukan manual lewat `scripts/backup.ps1`; RPO = sejak backup terakhir; RTO target < 30 menit untuk prototipe | Tidak ada otomasi (tanpa Task Scheduler) | Data setelah backup terakhir hilang |

### 4.5 Asumsi tambahan dari bagian arsitektur dan hak akses

| ID | Pernyataan | Alasan | Dampak bila salah |
|---|---|---|---|
| A-39 | CRUD sederhana tanpa aturan lintas-model (FAQ, banner, kategori artikel, dst.) boleh langsung dari controller ke model setelah Form Request, dengan `AuditLogger`; selain itu wajib lewat service ([03](03-arsitektur.md) 3.3) | Menghindari service kosong yang hanya meneruskan panggilan | Bila aturan lintas-model muncul belakangan, logika dipindah ke service baru |
| A-40 | Baris konfigurasi tambahan (`CACHE_STORE=database`, `LOG_LEVEL`, `BCRYPT_ROUNDS=12`, `timezone` koneksi, `innodb_lock_wait_timeout=10`) adalah rekomendasi blueprint, bukan keputusan 00 | Praktik umum untuk Windows/XAMPP | Nilai lain tetap berfungsi; hanya kenyamanan dan konsistensi |
| A-41 | Seeder menyinkronkan seluruh izin ke peran `super-admin` agar tampilan hak akses konsisten; `Gate::before` tetap penentu ([04](04-peran-hak-akses.md)) | Kolom terkunci pada layar hak akses terlihat lengkap | Hanya tampilan |
| A-42 | Konten draf/nonaktif/arsip/belum terbit **tidak** dapat dibuka lewat URL publik (404), termasuk untuk staf; pratinjau lewat layar admin | Mencegah kebocoran konten belum siap | Bila staf butuh pratinjau URL publik, tambahkan mode pratinjau bertoken |
| A-43 | `admin.room-categories.index` dapat dibuka dengan `facilities.manage` **atau** `rooms.update-availability`; pengguna hanya dengan izin kedua melihat daftar + form ketersediaan (→ diputuskan di 00 I.4 / 17 D-12) | 00 memberi izin `rooms.update-availability` kepada Petugas; layar daftarnya ditetapkan di 00 I.4 | Tanpa ini, Petugas tidak punya layar untuk memperbarui kamar |
| A-44 | Izin media mengikuti model pemilik (dokter → `doctors.manage`, dst.); unggahan editor (`model_id` NULL) butuh salah satu dari `content.manage`, `facilities.manage`, `clinics.manage` | `MediaPolicy` perlu aturan tunggal untuk satu tabel polymorphic | Pemetaan lain mengubah matriks Policy |
| A-45 | Menonaktifkan akun staf menghapus baris `sessions` miliknya agar terputus segera | Driver session `database` memungkinkan penghapusan langsung | Tanpa ini, akun nonaktif baru terputus pada permintaan berikutnya (tetap dicegah middleware `active`) |
| A-46 | Widget dashboard tambahan: sesi terdampak, pemberitahuan sesi terdampak untuk Admin Konten, pesan kontak baru | Membantu tindak lanjut tanpa membuka data pasien kepada Admin Konten | Hanya tampilan |
| A-47 | Password sementara staf (buat/reset) dibuat acak oleh sistem, ditampilkan **sekali** kepada Super Admin, tidak disimpan terbaca, dan menyetel `must_change_password = true` | Sesuai 00 H; mencegah password lemah buatan manusia | Bila hilang sebelum disalin, reset ulang |
| A-48 | `admin.password.edit/update` dan `admin.logout` dikecualikan dari middleware `password.changed` | Bila tidak, staf wajib-ganti-password terjebak dalam pengalihan tanpa akhir | Perulangan redirect |
| A-49 | Respons penolakan: 403 berbahasa Indonesia dengan tautan kembali (JSON 403 untuk permintaan JSON), 404 untuk kepemilikan, 419 untuk sesi kedaluwarsa, 429 untuk limiter | Pelengkap A-27 | Uji penerimaan mengikuti kode D1–D8 pada [04](04-peran-hak-akses.md) |

---

## 5. Keputusan yang Membutuhkan Konfirmasi Pemilik

Daftar ringkas **[KONFIRMASI]** K-01 sampai K-08 yang muncul di bagian ini; ID, opsi, default, dan waktu dibutuhkan mengikuti [17 — Keputusan dan Konfirmasi](17-keputusan-konfirmasi.md). Butir K-09 sampai K-23 (retensi data, verifikasi penghubungan akun K-14, pelacakan "sudah dihubungi" K-15, volume email K-13, dan lain-lain) hanya dicatat di 17.

| ID | Pertanyaan | Default prototipe | Dampak | Dibutuhkan (17) |
|---|---|---|---|---|
| K-01 | Versi database produksi: MariaDB ≥ 10.11 LTS atau MySQL 8.4 LTS? (00 B) | MariaDB 10.4.32 (XAMPP, sudah EOL dari vendor) | Migration sudah portabel ke keduanya; pemetaan error constraint memeriksa nomor error (lihat [03](03-arsitektur.md)) | Sebelum produksi |
| K-02 | Layanan SMTP produksi yang dipakai? (00 B.2) | `MAIL_MAILER=log` / Mailpit | Hanya konfigurasi `.env` + worker queue | Sebelum produksi |
| K-03 | Jadwal upgrade runtime dan framework (Laravel 13 butuh PHP ≥ 8.3) | PHP 8.2.12 + Laravel 12 di prototipe | Batas dukungan: keamanan PHP 8.2 berakhir 31 Des 2026 (terverifikasi php.net), Laravel 12 sampai 24 Feb 2027 | Sebelum produksi |
| K-04 | Apakah Petugas Pendaftaran perlu aksi "tampilkan nomor BPJS/polis lengkap" (tercatat di audit)? | Tidak ada; hanya 4 digit terakhir | Memengaruhi alur loket BPJS | Fase 7 |
| K-05 | Verifikasi nama kategori kamar terhadap ketentuan KRIS terbaru | Nama prototipe "BPJS Kelas 1/2/3", "VIP" | Mengubah data seeder dan teks halaman | Sebelum produksi |
| K-06 | Telaah hukum teks kebijakan privasi dan persetujuan | Teks contoh | Kepatuhan sebelum produksi | Sebelum produksi |
| K-07 | Perlukah satu akun memiliki banyak profil (anggota keluarga)? | Satu akun satu profil | Mengubah constraint `patient_profiles.user_id` dan wizard | Fase 5 |
| K-08 | Data kontak resmi (telepon, IGD, WhatsApp, email, jam operasional) | Placeholder | Hanya data pengaturan, tanpa perubahan kode | Sebelum produksi |

---

## 6. Glosarium

| Istilah | Definisi |
|---|---|
| **Prototipe** | Versi awal yang berjalan di localhost dengan data fiktif untuk memvalidasi alur dan desain; belum untuk pasien nyata |
| **Monolit** | Satu aplikasi Laravel yang melayani publik, portal pasien, dan admin, dengan satu database |
| **Templat jadwal** (`doctor_schedules`) | Aturan praktik mingguan seorang dokter: hari (1=Senin … 7=Minggu), jam mulai/selesai, kuota, tanggal berlaku. Dasar pembangkitan sesi; **bukan** yang dipesan pasien |
| **Pengecualian jadwal** (`schedule_exceptions`) | Pemblokiran rentang tanggal penuh karena cuti dokter, libur rumah sakit, libur nasional, atau lainnya; `doctor_id` NULL = berlaku untuk semua |
| **Sesi praktik** (`practice_sessions`) | Praktik nyata seorang dokter pada satu tanggal dan jam tertentu; baris inilah yang dipesan pasien dan **dikunci** saat booking |
| **Sesi tambahan manual** | Sesi tanpa templat (`doctor_schedule_id` NULL) yang dibuat admin untuk tanggal tertentu |
| **Pembangkitan sesi** | Proses `PracticeSessionGenerator` membuat sesi dari templat untuk rentang hari ini … hari ini + `booking.window_days`; idempoten |
| **Kuota** (`quota`) | Jumlah maksimum pendaftaran yang dapat tercatat pada satu sesi (1–200) |
| **`booked_count`** | Jumlah pendaftaran pada sesi yang masih memakai kuota (semua status kecuali `cancelled`); tidak pernah melebihi `quota` (CHECK) |
| **Sisa kuota** (`remaining`) | `quota - booked_count`, dihitung `DoctorAvailabilityQuery` |
| **`last_queue_number`** | Penghitung nomor antrean per sesi; hanya naik, tidak pernah turun |
| **Nomor antrean** | `queue_number` dalam sesi, ditampilkan sebagai `{kode poliklinik}-{3 digit}` mis. `ANK-007`; tidak dipakai ulang walau dibatalkan |
| **Kode booking** | Identitas pendaftaran berformat `UPH-XXXX-XXXX` (Crockford Base32, acak); tidak memuat data pasien; dasar QR bukti |
| **Bukti pendaftaran** | Halaman cetak atau PDF berisi kode booking, QR, pasien, poliklinik, dokter, jadwal, antrean, jenis pembayaran (tersamar), status |
| **Profil pasien** (`patient_profiles`) | Data identitas kunjungan seseorang; booking merujuk ke profil, bukan ke akun |
| **Akun** (`users`) | Kredensial login. Satu akun pasien memiliki satu profil; profil walk-in dapat tidak memiliki akun |
| **Nomor pasien** | `patient_number`, format `P` + 6 digit, mis. `P000123` |
| **Walk-in** | Pasien datang langsung ke loket; petugas membuat/menemukan profil dan mendaftarkan kunjungan pada sesi yang sama dengan kuota bersama pendaftaran online |
| **Kanal** (`BookingChannel`) | Asal pendaftaran: `online` (portal pasien) atau `loket` (petugas) |
| **Loket** | Meja pendaftaran fisik tempat Petugas Pendaftaran bekerja |
| **Hubungkan akun** | Aksi petugas memasangkan profil walk-in ke akun online setelah verifikasi identitas di loket; hanya diizinkan bila profil bawaan milik akun itu belum punya appointment (profil kosong itu dihapus, lalu `user_id` dipasang ke profil walk-in; 00 F.9) |
| **Jendela booking** | Rentang tanggal yang boleh dipesan: hari ini sampai hari ini + `booking.window_days` (30) |
| **Batas tutup online** | `booking.online_cutoff_minutes` (60): pendaftaran online ditutup 60 menit sebelum jam mulai sesi |
| **Batas batal** | `booking.cancel_cutoff_minutes` (120): pasien dapat membatalkan sendiri sampai 120 menit sebelum mulai |
| **Batas buka check-in** | `booking.checkin_open_minutes` (60): check-in dibuka 60 menit sebelum mulai sampai `end_time` |
| **Check-in** | Petugas menandai pasien hadir (`checked_in`) dengan mengetik atau memindai kode booking |
| **Tidak hadir** (`no_show`) | Pasien tidak datang sampai sesi berakhir; ditandai petugas (massal per sesi) atau `appointments:mark-no-show` |
| **Status final** | `completed`, `cancelled`, `no_show`; tidak dapat berubah lagi |
| **`active_flag`** | Kolom generated (STORED) bernilai 1 untuk status aktif dan NULL lainnya; membuat UNIQUE (`patient_profile_id`, `practice_session_id`, `active_flag`) bertindak sebagai "unik hanya untuk yang aktif" |
| **Sesi terdampak** | Sesi `needs_review`: ada appointment aktif tetapi jadwal berubah (jam, hari, cuti, templat dinonaktifkan, edit manual jam atau pembatalan sesi, dokter dinonaktifkan); tertutup untuk booking baru sampai diselesaikan |
| **`pending_change`** | Kolom JSON yang menjelaskan perubahan menunggu keputusan pada sesi terdampak; enam sumber: `exception`, `template_time`, `template_removed`, `manual_time`, `manual_cancel`, `doctor_inactive` (00 F.4). Satu sesi hanya menyimpan satu `pending_change`; sumber jenis-batal mengalahkan sumber jenis-jam |
| **Resolusi sesi** (`SessionResolution`) | Keputusan petugas atas sesi terdampak: `keep`, `apply`, `cancel` |
| **`is_manually_modified`** | Penanda sesi yang diedit manual sehingga tidak ditimpa regenerasi dari templat |
| **Ketersediaan** (`Availability`) | Hasil hitung `DoctorAvailabilityQuery` (tersedia, penuh, pendaftaran online ditutup, ditutup, cuti, libur, dibatalkan, sedang diperbarui, sudah lewat); tidak disimpan di DB |
| **Kunci baris** (`lockForUpdate`) | `SELECT … FOR UPDATE` InnoDB yang membuat transaksi lain menunggu; dipakai berurutan `patient_profiles` → `practice_sessions` → `appointments` |
| **Penyamaran** (masking) | Menampilkan hanya 4 digit terakhir nomor BPJS/polis (`bpjs_number_last4`, `payment_reference_last4`) |
| **Super Admin / Admin Konten / Petugas Pendaftaran** | Peran Spatie `super-admin`, `content-admin`, `registration-officer` |
| **`Gate::before`** | Hook Laravel yang meloloskan Super Admin dari pemeriksaan izin/Policy |
| **Policy** | Kelas otorisasi per model (mis. `AppointmentPolicy`) |
| **Form Request** | Kelas validasi dan `authorize()` per aksi sebelum controller berjalan |
| **Service** | Kelas di `app/Services` yang memuat aturan bisnis; dipanggil controller |
| **Disk `public` / `local`** | Penyimpanan Laravel: `public` untuk media publik (lewat `storage:link`), `local` privat dilayani hanya lewat controller + policy |
| **Rich text** | Kolom HTML hasil editor Quill yang disanitasi `HtmlSanitizer` (`pages.body`, `articles.body`, `clinics.description`, `facilities.description`, `room_categories.description`) |
| **Placeholder lokal** | Gambar SVG bawaan aplikasi pengganti foto bila media kosong atau file hilang |
| **`wa.me`** | Tautan `https://wa.me/{nomor}` yang membuka WhatsApp pengguna; **bukan** WhatsApp API |
| **WhatsApp API** | Pengiriman pesan otomatis oleh server lewat platform resmi WhatsApp; **belum ada** di prototipe |
| **SIMRS** | Sistem Informasi Manajemen Rumah Sakit; integrasi lanjutan |
| **SATUSEHAT** | Platform pertukaran data kesehatan nasional; integrasi lanjutan |
| **VClaim / Antrean Online** | Layanan web BPJS Kesehatan; integrasi lanjutan |
| **KRIS** | Kelas Rawat Inap Standar; arah kebijakan rawat inap JKN (lihat 3.3) |
| **Mailpit** | Penangkap email lokal opsional (SMTP `127.0.0.1:1025`) |
| **VirtualHost** | Konfigurasi Apache yang memetakan `unpam-hospital.test` ke folder `public` |
| **Vite** | Pembungkus aset frontend (SCSS Bootstrap, JS) yang menghasilkan `public/build` |
| **Intended URL** | URL tujuan semula yang diingat saat tamu diarahkan login, lalu dikembalikan setelah berhasil |
| **Audit log** | Catatan append-only perubahan penting (`audit_logs`) dengan redaksi data sensitif |

---

## Asumsi di bagian ini

- A-01 sampai A-06: asumsi bawaan 00.
- A-07 sampai A-23: asumsi domain dan data (satu RS, satu akun satu profil, kuota per sesi, dokter bukan pengguna, penyamaran BPJS, kamar agregat, penunjang tidak dibooking).
- A-24 sampai A-28: asumsi akses dan keamanan (satu peran per staf, izin terkunci per peran, perilaku penolakan, aturan mutlak di service).
- A-29 sampai A-38: asumsi lingkungan (pengujian di MariaDB, beban kecil, browser modern, offline, zona DB, penanda prototipe, port, backup manual).
- A-39 sampai A-49: asumsi tambahan dari bagian 03 dan 04 (CRUD sederhana, konfigurasi tambahan, sinkronisasi izin Super Admin, konten tidak terbit, layar kamar untuk Petugas, pemetaan izin media, widget dashboard, password sementara, pengecualian `password.changed`, respons penolakan).
- Asumsi yang kini menjadi keputusan: A-15 → 00 E.1 / 17 D-08; A-28 → 00 G / 17 D-13; A-43 → 00 I.4 / 17 D-12. A-01 sampai A-06 tetap bertanda [ASUMSI] di 00.
- Butuh konfirmasi: K-01 sampai K-08 (bagian 5); daftar lengkap K-01 sampai K-23 di 17.

## Nama baru yang diusulkan

Tidak ada.
