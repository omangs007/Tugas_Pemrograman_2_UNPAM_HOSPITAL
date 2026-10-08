# 02 — Ruang Lingkup Fitur Inti dan Fitur Lanjutan

> **Tujuan bagian.** Bagian ini menetapkan **apa yang dibangun** pada tahap prototipe (fitur inti, dengan ID yang dipakai bagian lain), **apa yang sengaja ditunda** (fitur lanjutan, beserta prasyarat dan titik perluasan di arsitektur), dan **apa yang tidak akan dibangun sama sekali** (di luar cakupan). Setiap butir kebutuhan asli §3–§13 ditelusuri ke minimal satu baris fitur (tabel keterlusuran di bagian 3).
>
> **Bagian terkait:** [01 — Ringkasan dan Asumsi](01-ringkasan-asumsi.md) · [03 — Arsitektur](03-arsitektur.md) · [04 — Peran dan Hak Akses](04-peran-hak-akses.md) · [05 — Sitemap dan Matriks Halaman](05-sitemap-matriks-halaman.md) · [06 — Alur](06-alur.md) · [09 — Data Dictionary](09-data-dictionary.md) · [11 — Route, Controller, Model, Service](11-route-controller-model-service.md) · [15 — Pengujian dan Penerimaan](15-pengujian-penerimaan.md) · [17 — Keputusan dan Konfirmasi](17-keputusan-konfirmasi.md).
>
> **Penanda:** **[ASUMSI]** = ditetapkan tanpa data pemilik; **[KONFIRMASI]** = butuh keputusan pemilik. Nama tabel, enum, route, izin, dan service mengikuti [00 — Keputusan Inti](00-keputusan-inti.md).

---

## 1. Cara Membaca Tabel Fitur

| Kolom | Arti |
|---|---|
| **ID** | `F-PUB-xx` publik · `F-AUTH-xx` autentikasi · `F-PAS-xx` portal pasien · `F-ADM-xx` admin · `F-SYS-xx` sistem/non-fungsional. Fitur lanjutan memakai `F-NXT-xx`. |
| **Kebutuhan** | Nomor bagian pada [_kebutuhan-asli.md](_kebutuhan-asli.md) (mis. §8 = Akun dan portal pasien). |
| **Hlm** | Nomor halaman 1–40 pada 00 bagian I; "tambahan" = layar di luar daftar 40 (galleries.show, privacy, kotak pesan, ganti password, endpoint media); "semua" = lintas halaman. |
| **Tabel utama** | Tabel dari 00 bagian E yang dibaca/ditulis fitur. |
| **Izin** | Izin dari 00 bagian G; "Publik" = tanpa login; "Pasien" = akun `patient` pemilik data; "Staf" = akun `staff` terautentikasi. |

Fitur "inti" artinya **dibangun dan harus berfungsi tanpa integrasi eksternal** (kebutuhan §14).

---

## 2. Fitur Inti

### 2.1 Website publik (`F-PUB`)

| ID | Fitur dan deskripsi | Kebutuhan | Hlm | Tabel utama | Izin |
|---|---|---|---|---|---|
| F-PUB-01 | **Beranda dinamis**: banner aktif sesuai periode (`starts_at`–`ends_at`), tiga tombol utama "Cari Dokter", "Jadwal Dokter", "Daftar Kunjungan", sorotan poliklinik, dokter, artikel terbaru, ringkasan kontak/jam operasional | §4, §5A-1 | 1 | `pages` (`home`), `banners`, `clinics`, `doctors`, `articles`, `media`, `website_settings` | Publik; kelola: `content.manage` |
| F-PUB-02 | **Profil rumah sakit** (teks rich text yang dapat diedit admin) | §5A-2 | 2 | `pages` (`about`) | Publik; kelola: `content.manage` |
| F-PUB-03 | **Visi dan misi** | §5A-3 | 3 | `pages` (`vision-mission`) | Publik; kelola: `content.manage` |
| F-PUB-04 | **Daftar dan detail layanan/poliklinik**: jenis (poliklinik/penunjang), lokasi gedung, deskripsi, galeri, daftar dokter pada poliklinik, tombol ke jadwal | §5A-4, 5 | 4, 5 | `clinics`, `specialties`, `doctors`, `media`, `pages` (`clinics-index`) | Publik; kelola: `clinics.manage` |
| F-PUB-05 | **Pencarian dan daftar dokter** berdasarkan nama, spesialisasi, poliklinik, dan tanggal praktik; kartu dokter (foto, nama, spesialisasi, tombol jadwal) | §4, §5A-6, §6 | 6 | `doctors`, `doctor_specialties`, `specialties`, `clinics`, `practice_sessions` (via `DoctorAvailabilityQuery`), `media` | Publik; kelola: `doctors.manage` |
| F-PUB-06 | **Detail dokter**: profil, spesialisasi, poliklinik, jadwal terdekat dengan kuota tersisa, tombol "Daftar" ke wizard | §5A-7 | 7 | `doctors`, `practice_sessions`, `schedule_exceptions`, `media` | Publik |
| F-PUB-07 | **Jadwal dokter mingguan** (filter minggu, poliklinik, dokter) dengan badge ketersediaan (Tersedia, Penuh, Pendaftaran online ditutup, Ditutup, Cuti, Libur, Dibatalkan, Sedang diperbarui, Sudah lewat; prioritas label mengikuti 00 F.3) dan tombol "Daftar" hanya untuk sesi yang dapat dipesan online | §5A-8, §6 | 8 | `practice_sessions`, `doctors`, `clinics`, `schedule_exceptions` | Publik |
| F-PUB-08 | **Daftar dan detail fasilitas** (kategori medis/penunjang/umum, deskripsi, galeri) | §5A-9, 10 | 9, 10 | `facilities`, `media` | Publik; kelola: `facilities.manage` |
| F-PUB-09 | **Kamar rawat inap**: ringkasan empat kategori dan halaman detail per kategori (deskripsi, galeri, daftar fasilitas, kapasitas tempat tidur, tempat tidur tersedia, tarif contoh, status aktif, **waktu pembaruan terakhir** dengan label "diperbarui manual") | §5A-11–15, §7 | 11, 12–15 | `room_categories`, `media`, `pages` (`rooms-index`) | Publik; kelola: `facilities.manage`; ketersediaan: `rooms.update-availability` |
| F-PUB-10 | **Informasi BPJS dan asuransi**: halaman yang dapat diedit (info, persyaratan, tarif) dan daftar mitra asuransi **contoh** | §5A-16, §7 | 16 | `pages` (`insurance-info`), `insurance_partners`, `media` | Publik; kelola: `content.manage` |
| F-PUB-11 | **Panduan pendaftaran pasien** (langkah online dan loket) | §5A-17 | 17 | `pages` (`registration-guide`) | Publik; kelola: `content.manage` |
| F-PUB-12 | **Artikel kesehatan**: daftar (filter kategori, pencarian `q`, hanya yang terbit) dan detail | §5A-18, 19 | 18, 19 | `articles`, `article_categories`, `media` | Publik; kelola: `content.manage` |
| F-PUB-13 | **Galeri**: daftar album dan detail album (foto dengan alt text/caption) | §5A-20 | 20, tambahan (`galleries.show`) | `galleries`, `media` | Publik; kelola: `content.manage` |
| F-PUB-14 | **FAQ** dikelompokkan per kategori (`pendaftaran`, `bpjs`, `layanan`, `umum`) | §5A-21 | 21 | `faqs`, `pages` (`faq`) | Publik; kelola: `content.manage` |
| F-PUB-15 | **Kontak dan lokasi**: alamat teks (tetap tampil tanpa peta), peta tersemat bila online, tombol "Petunjuk Arah" ke pencarian Google Maps Kampus UNPAM Viktor, telepon/WhatsApp/email placeholder, jam operasional, **form pesan kontak** dengan persetujuan privasi dan pembatasan 3 kiriman/10 menit | §3, §5A-22 | 22 | `website_settings` (`contact.*`, `map.*`), `contact_messages`, `pages` (`contact`) | Publik; pesan masuk: `messages.manage`; alamat/peta: `settings.manage` |
| F-PUB-16 | **Tombol tautan WhatsApp (`wa.me`)**: tautan `https://wa.me/{contact.whatsapp}` yang dibuka manual oleh pengguna. **Bukan** pengiriman otomatis (lihat bagian 4.2) | §3, §14 | 22, footer semua halaman | `website_settings` (`contact.whatsapp`) | Publik; ubah nomor: `settings.manage` |
| F-PUB-17 | **Kebijakan privasi** (layar tambahan wajib) yang ditautkan dari registrasi, form kontak, dan footer | §12 | tambahan (`privacy`) | `pages` (`privacy-policy`), `website_settings` (`privacy.consent_version`) | Publik; kelola: `content.manage` |
| F-PUB-18 | **Judul dan meta description yang dapat dikelola admin** untuk setiap halaman publik; halaman detail memakai `meta_title`/`meta_description` miliknya dengan fallback nama + ringkasan; fallback global `seo.default_meta_description` | §4 | 1–22, tambahan | `pages`, kolom meta pada `clinics`, `doctors`, `facilities`, `room_categories`, `articles`, `website_settings` | Publik; kelola: izin entitas bersangkutan |
| F-PUB-19 | **Kerangka navigasi**: navbar desktop, menu mobile (offcanvas), breadcrumb pada halaman internal, footer, skip link, penanda "Prototipe — data contoh" | §4 | semua publik | — | Publik |
| F-PUB-20 | **Gambar dengan fallback lokal**: placeholder SVG per jenis entitas bila media kosong; bila file fisik hilang, satu listener JavaScript terdelegasi (kompatibel CSP, bukan `onerror` inline; 00 K) mengganti `src` ke placeholder; alt text pada setiap gambar | §4, §11, §13 | semua | `media`, berkas `public/images/placeholders/*.svg` | Publik |

### 2.2 Autentikasi (`F-AUTH`)

| ID | Fitur dan deskripsi | Kebutuhan | Hlm | Tabel utama | Izin |
|---|---|---|---|---|---|
| F-AUTH-01 | **Registrasi pasien**: transaksi membuat `users` (`account_type = patient`) + `patient_profiles` (`registration_channel = online`, `consent_at`, `consent_version`); NIK dan nomor BPJS tidak diminta; password `min(8)` huruf + angka | §8 | 23 | `users`, `patient_profiles` | Tamu; limiter `register` (5/jam/IP) |
| F-AUTH-02 | **Login pasien**: hanya akun pasien; pesan galat generik "Email atau password salah"; regenerasi session; `last_login_at`; kembali ke *intended URL* | §8, §12 | 24 | `users`, `sessions` | Tamu; limiter `login` (5/menit/email+IP) |
| F-AUTH-03 | **Logout** pasien dan staf: `invalidate()` + `regenerateToken()` | §8, §12 | tambahan (`logout`, `admin.logout`) | `sessions` | Pengguna login |
| F-AUTH-04 | **Permintaan reset password**: respons selalu generik; tautan dikirim lewat mail (lokal ke log/Mailpit) dan **tidak pernah ditampilkan di halaman** | §8, §12 | 25 | `password_reset_tokens`, `users` | Tamu; limiter `password-reset` (3/10 menit) |
| F-AUTH-05 | **Form reset password** dengan token sekali pakai | §8 | 26 | `password_reset_tokens`, `users` | Tamu |
| F-AUTH-06 | **Login admin** terpisah: hanya akun staf; pesan galat generik yang sama | §5C-31, §10 | 31 | `users` | Tamu; limiter `login` |
| F-AUTH-07 | **Ganti password staf**: wajib pada login pertama (`must_change_password`), dapat dilakukan kapan saja | §10, §12 | tambahan (`admin.password.edit`) | `users` | Staf |
| F-AUTH-08 | **Pemisahan tipe akun dan status akun**: middleware `account:patient`/`account:staff`, `active`, `password.changed`; akun nonaktif diputus pada permintaan berikutnya | §10, §12 | semua area bertanda login | `users` | Sesuai area |
| F-AUTH-09 | **Pembatasan laju** bernama: `login`, `password-reset`, `register`, `booking`, `contact` | §12 | 22–26, 29, 31 | `cache` (rate limiter di driver database) | — |

### 2.3 Portal pasien (`F-PAS`)

| ID | Fitur dan deskripsi | Kebutuhan | Hlm | Tabel utama | Izin |
|---|---|---|---|---|---|
| F-PAS-01 | **Dashboard pasien**: kunjungan mendatang, banner notifikasi belum dibaca (termasuk perubahan jadwal), pintasan "Daftar Kunjungan" | §5B-27, §8 | 27 | `appointments`, `notifications`, `patient_profiles` | Pasien (data sendiri) |
| F-PAS-02 | **Profil dan pengaturan akun**: nama, tanggal lahir, jenis kelamin, telepon, email, alamat; nomor BPJS opsional tersamar | §5B-28, §8 | 28 | `patient_profiles`, `users` | Pasien (data sendiri) |
| F-PAS-03 | **Ganti password** (memerlukan password lama) | §8 | 28 | `users` | Pasien |
| F-PAS-04 | **Wizard pendaftaran kunjungan** berurutan: poliklinik → dokter → tanggal/sesi → cek kuota → data pendaftaran → tinjau → kirim → bukti; setiap langkah form GET server-render sehingga **berjalan tanpa JavaScript**; sesi lewat/nonaktif/cuti/libur tidak dapat dipilih | §5B-29, §8, §9 | 29 | `clinics`, `doctors`, `practice_sessions`, `appointments`, `appointment_status_histories` | Pasien; limiter `booking` (10/menit) |
| F-PAS-05 | **Jenis pembayaran**: umum/pribadi, BPJS (nomor 13 digit **hanya** diminta di sini), asuransi (pilih `insurance_partners` + nomor polis opsional); opsi simpan nomor BPJS ke profil | §8 | 29 | `appointments` (`payment_type`, `insurance_partner_id`, `payment_reference`), `insurance_partners`, `patient_profiles` | Pasien |
| F-PAS-06 | **Tampilan kuota tersisa** dan ketersediaan (badge teks + ikon) untuk setiap sesi pada wizard | §8, §9 | 29 | `practice_sessions` (via `DoctorAvailabilityQuery`) | Pasien |
| F-PAS-07 | **Riwayat dan detail pendaftaran**: kode booking, nomor antrean (`ANK-007`), jadwal, status, riwayat perubahan status | §5B-30, §8 | 30 | `appointments`, `appointment_status_histories` | Pasien (data sendiri) |
| F-PAS-08 | **Pembatalan pendaftaran** sampai `booking.cancel_cutoff_minutes` (120 menit) sebelum sesi; setelah batas, pasien diarahkan menghubungi RS (telepon/tautan `wa.me`) | §8, §9 | 30 | `appointments`, `practice_sessions` | Pasien pemilik |
| F-PAS-09 | **Bukti pendaftaran**: halaman cetak (CSS print) dan PDF, berisi kode booking + QR (hanya kode), nomor pasien, poliklinik, dokter, jadwal, antrean, pembayaran (4 digit terakhir), status, instruksi check-in | §8, §9 | 30 | `appointments`, `patient_profiles`, `website_settings` | Pasien pemilik; staf `appointments.view` |
| F-PAS-10 | **Notifikasi pasien**: kanal `database` untuk setiap transisi status dan perubahan jadwal, kanal `mail` hanya untuk `confirmed`, `cancelled`, dan perubahan jadwal (default K-13); tandai dibaca | §9 | 27, 30 | `notifications` | Pasien (data sendiri) |

### 2.4 Admin (`F-ADM`)

| ID | Fitur dan deskripsi | Kebutuhan | Hlm | Tabel utama | Izin |
|---|---|---|---|---|---|
| F-ADM-01 | **Dashboard ringkasan**: dokter aktif, pasien terdaftar (profil aktif), pendaftaran hari ini, jumlah per status, grafik pendaftaran per periode (7/30/90 hari atau rentang kustom), ringkasan kamar dengan waktu pembaruan terakhir; daftar bernama pasien hanya untuk `appointments.view` | §5C-32, §10 | 32 | `doctors`, `patient_profiles`, `appointments`, `room_categories` | `dashboard.view` (+ `appointments.view` untuk daftar) |
| F-ADM-02 | **Kelola dokter**: tambah, ubah, nonaktifkan, arsipkan, unggah/ganti foto, nama, gelar, profil singkat, spesialisasi (banyak), poliklinik utama. Menonaktifkan dokter yang masih punya sesi mendatang menjalankan `ScheduleChangeService::applyDoctorInactive()`: sesi tanpa booking aktif dibatalkan, sesi dengan booking aktif menjadi Sesi terdampak (sumber `doctor_inactive`); mengarsipkan hanya diizinkan bila tidak ada booking aktif mendatang (00 F.4; D-01, D-02). Dokter tidak pernah dihapus | §5C-33, §6 | 33 | `doctors`, `doctor_specialties`, `practice_sessions`, `media` | `doctors.view` / `doctors.manage` |
| F-ADM-03 | **Kelola spesialisasi** (13 contoh dari kebutuhan §6); hapus hanya bila tidak dirujuk, selain itu "Nonaktifkan" (00 C) | §5C-34, §6 | 34 | `specialties` | `clinics.manage` |
| F-ADM-04 | **Kelola poliklinik/layanan**: kode antrean (mis. `ANK`), jenis, `is_bookable`, spesialisasi, lokasi, deskripsi rich text, galeri, meta SEO; hapus hanya bila tidak dirujuk, selain itu "Nonaktifkan" | §5C-34 | 34 | `clinics`, `media` | `clinics.manage` |
| F-ADM-05 | **Templat jadwal mingguan**: hari, jam mulai/selesai, kuota (1–200), tanggal berlaku, aktif/nonaktif, catatan; validasi tidak beririsan; propagasi ke sesi lewat `ScheduleChangeService`; hapus hanya bila tidak ada sesi ber-appointment, selain itu nonaktifkan | §5C-35, §6 | 35 | `doctor_schedules`, `practice_sessions` | `schedules.view` / `schedules.manage` |
| F-ADM-06 | **Pengecualian jadwal**: cuti dokter (`doctor_leave`, dokter wajib), libur rumah sakit dan libur nasional (tanpa dokter; berlaku seluruh RS), lainnya (`other`, dokter opsional); satu hari penuh (**[ASUMSI]** A-02); menutup/membuka sesi terkait; hapus = cabut pengecualian lewat `ScheduleChangeService::removeException` | §5C-35, §6 | 35 | `schedule_exceptions`, `practice_sessions` | `schedules.view` / `schedules.manage` |
| F-ADM-07 | **Sesi praktik**: lihat per tanggal (Admin Konten hanya melihat **jumlah** booking), tambah sesi manual, ubah jam/kuota satu tanggal (kuota tidak di bawah `booked_count`), tutup/buka. Edit manual sesi yang punya booking aktif: kuota langsung berlaku; geser jam menjadi Sesi terdampak sumber `manual_time`; pembatalan sesi menjadi sumber `manual_cancel` (00 F.4; D-03). Sesi tidak dihapus (ditutup atau dibatalkan) | §5C-35, §6 | 35 | `practice_sessions` | `schedules.view` / `schedules.manage` |
| F-ADM-08 | **Sesi terdampak** (`admin.sessions.review`): menyelesaikan sesi `needs_review` dengan resolusi `keep` / `apply` / `cancel` sesuai enam sumber perubahan (`exception`, `template_time`, `template_removed`, `manual_time`, `manual_cancel`, `doctor_inactive`); pasien walk-in tanpa akun ditampilkan beserta telepon untuk dihubungi petugas (tanpa pelacakan "sudah dihubungi", default K-15) | §9 | 35 | `practice_sessions`, `appointments` | `sessions.review` |
| F-ADM-09 | **Kelola fasilitas**: kategori (medis, penunjang, umum), deskripsi, galeri, urutan, aktif; hapus permanen diizinkan (file media ikut dibersihkan) | §5C-36 | 36 | `facilities`, `media` | `facilities.manage` |
| F-ADM-10 | **Kelola kategori kamar**: nama, deskripsi, daftar fasilitas kamar (`amenities`), kapasitas tempat tidur, tarif contoh, galeri, aktif/nonaktif; hapus hanya bila tidak dirujuk, selain itu "Nonaktifkan" | §5C-36, §7 | 36 | `room_categories`, `media` | `facilities.manage` |
| F-ADM-11 | **Perbarui ketersediaan kamar manual** (`beds_available` 0..`bed_capacity`) yang mencatat waktu dan pengguna pembaruan; tidak disebut real-time. `admin.room-categories.index` dapat dibuka dengan `facilities.manage` atau `rooms.update-availability`; pemegang izin kedua saja hanya melihat daftar + form ketersediaan (00 I.4; D-12) | §7 | 36 | `room_categories` | `rooms.update-availability` |
| F-ADM-12 | **Kelola pasien**: cari/daftar, detail (riwayat kunjungan), buat profil walk-in (persetujuan privasi dicentang petugas), ubah, nonaktifkan | §5C-37 | 37 | `patient_profiles`, `users` | `patients.view` / `patients.manage` |
| F-ADM-13 | **Hubungkan profil walk-in ke akun online** setelah verifikasi identitas di loket (`PatientAccountLinker`; hanya bila profil milik akun belum punya appointment; verifikasi di loket = default K-14) | §5C-37 | 37 | `patient_profiles`, `users` | `patients.manage` |
| F-ADM-14 | **Kelola pendaftaran**: daftar dengan filter (tanggal kunjungan, poliklinik, dokter, status, kanal), detail, **pendaftaran walk-in** (kanal `loket`, status awal `confirmed`), transisi status (konfirmasi, check-in, selesai, batal, koreksi) | §5C-38, §9 | 38 | `appointments`, `appointment_status_histories` | `appointments.view` / `appointments.manage` |
| F-ADM-15 | **Antrean harian per sesi**: cari/pindai kode booking (scanner USB sebagai keyboard), check-in, tandai tidak hadir massal setelah sesi berakhir | §5C-38, §9 | 38 | `practice_sessions`, `appointments` | `appointments.view` / `appointments.manage` |
| F-ADM-16 | **Bukti pendaftaran versi admin** (cetak) | §9 | 38 | `appointments` | `appointments.view` |
| F-ADM-17 | **Ekspor CSV pendaftaran** (filter tanggal, poliklinik, dokter, status, kanal; UTF-8 BOM, pemisah `;`, streaming; **tanpa** nomor BPJS/polis, alamat, telepon, tanggal lahir, keluhan); setiap ekspor dicatat di audit | §10 | 38 | `appointments`, `audit_logs` | `reports.export` |
| F-ADM-18 | **Kelola halaman statis**: 15 halaman baku (`pages.key`), judul, subjudul, isi rich text bila ada, meta; tidak ada aksi tambah atau hapus | §5C-39 | 39 | `pages`, `media` | `content.manage` |
| F-ADM-19 | **Kelola banner**: judul, tombol, periode tayang, urutan, aktif; hapus permanen diizinkan | §5C-39 | 39 | `banners`, `media` | `content.manage` |
| F-ADM-20 | **Kelola artikel dan kategori artikel**: draf/terbit, cover, kategori, editor rich text; artikel soft delete, kategori dihapus permanen (artikel menjadi tanpa kategori) | §5C-39 | 39 | `articles`, `article_categories`, `media` | `content.manage` |
| F-ADM-21 | **Kelola galeri**: album, unggah foto banyak, alt text/caption, urutan; hapus album ikut menghapus fotonya | §5C-39 | 39 | `galleries`, `media` | `content.manage` |
| F-ADM-22 | **Kelola FAQ** (hapus permanen diizinkan) | §5C-39 | 39 | `faqs` | `content.manage` |
| F-ADM-23 | **Kelola mitra asuransi** (data contoh berlabel "Contoh"); hapus hanya bila belum dirujuk appointment, selain itu "Nonaktifkan" | §5C-39 | 39 | `insurance_partners`, `media` | `content.manage` |
| F-ADM-24 | **Kotak pesan kontak**: daftar, detail, ubah status (`new`, `read`, `replied`, `archived`) | §3 (form kontak) | tambahan (`admin.messages.*`) | `contact_messages` | `messages.manage` |
| F-ADM-25 | **Pengaturan website**: identitas, logo, alamat, telepon, WhatsApp, email, jam operasional, tautan petunjuk arah dan peta, jendela dan batas booking, SEO bawaan, versi persetujuan privasi | §3, §5C-40 | 40 | `website_settings`, `media` | `settings.manage` |
| F-ADM-26 | **Akun staf**: buat dengan password sementara (`must_change_password`), ubah, nonaktifkan (tidak dihapus), reset password; aturan mutlak (minimal satu Super Admin aktif, tidak menonaktifkan atau menurunkan diri sendiri) ditegakkan di `StaffAccountService` dan Form Request (00 G; D-13) | §10, §5C-40 | 40 | `users`, `model_has_roles` | `staff.manage` |
| F-ADM-27 | **Hak akses peran**: lihat dan ubah izin peran (kecuali `super-admin` yang terkunci), dengan pembatasan **[ASUMSI]** A-26 | §10, §5C-40 | 40 | `roles`, `permissions`, `role_has_permissions` | `staff.manage` |
| F-ADM-28 | **Audit log**: daftar dengan filter dan detail; tidak dapat diubah/dihapus dari UI | §10, §12, §5C-40 | 40 | `audit_logs` | `audit.view` |
| F-ADM-29 | **Pengelolaan media**: unggah gambar editor, ubah alt/caption, hapus, urutkan | §11 | tambahan (endpoint media) | `media` | Izin pemilik media (lihat [04](04-peran-hak-akses.md)) |

### 2.5 Sistem dan non-fungsional (`F-SYS`)

| ID | Fitur dan deskripsi | Kebutuhan | Hlm | Tabel utama | Izin |
|---|---|---|---|---|---|
| F-SYS-01 | **Mesin jadwal tiga lapis** (templat → pengecualian → sesi) dan `PracticeSessionGenerator` idempoten, lazy maksimal sekali per hari, dan perintah `sessions:generate` | §6, §9 | 6–8, 29, 35 | `doctor_schedules`, `schedule_exceptions`, `practice_sessions` | — |
| F-SYS-02 | **Sumber jadwal tunggal** `DoctorAvailabilityQuery` untuk halaman publik, wizard, dan form walk-in | §6 | 6–8, 29, 38 | `practice_sessions` | — |
| F-SYS-03 | **Aturan booking dan konkurensi** `BookingService::book()`: transaksi, `lockForUpdate` berurutan, delapan aturan F.5, kode galat `BookingException` (termasuk `SYSTEM_BUSY` bila deadlock atau lock wait masih terjadi setelah 3 percobaan), constraint UNIQUE/CHECK sebagai lapis terakhir | §9, §16 | 29, 38 | `appointments`, `practice_sessions`, `patient_profiles` | — |
| F-SYS-04 | **Status, transisi, dan kuota** `AppointmentStatusService::transition()` (siapa, kapan, efek ke `booked_count`, riwayat, audit, notifikasi) | §9 | 30, 38 | `appointments`, `appointment_status_histories`, `practice_sessions` | — |
| F-SYS-05 | **Kode booking, QR, nomor antrean**: `UPH-XXXX-XXXX` acak tanpa data pasien; QR SVG hanya berisi kode; antrean `++last_queue_number`, tidak dipakai ulang | §9 | 29, 30 | `appointments`, `practice_sessions` | — |
| F-SYS-06 | **Perubahan jadwal setelah ada booking** `ScheduleChangeService`: sesi `needs_review`, `pending_change` (enam sumber; satu sesi satu `pending_change`, sumber jenis-batal mengalahkan jenis-jam), notifikasi pasien, pembukaan kembali saat pengecualian dihapus atau dipersempit | §9 | 35 | `practice_sessions`, `appointments` | — |
| F-SYS-07 | **Media**: unggah tervalidasi (MIME, ekstensi, ukuran 2 MB/banner 4 MB, dimensi maksimal 6000×6000 dan minimum per koleksi (00 K; 17 D-11): `photo` 300×300, `cover` 800×450, `gallery`/`photos` 600×400, `image` 1200×400, `logo` 120×40, `editor` 200×100), SVG unggahan ditolak, re-encode + buang EXIF, nama UUID, thumbnail, penggantian setelah commit, `media:prune` | §11, §12 | semua unggahan | `media` | — |
| F-SYS-08 | **Audit log** dengan redaksi (`password`, `remember_token`, `token`, `bpjs_number`, `payment_reference` → `[disamarkan]`) untuk akun, jadwal, pendaftaran, ekspor | §10, §12 | 40 | `audit_logs` | — |
| F-SYS-09 | **Pengaturan website bercache** `SettingsService` dengan pembersihan cache saat disimpan | §3 | 40 | `website_settings` | — |
| F-SYS-10 | **Keamanan aplikasi**: hash password, CSRF, regenerasi session, header keamanan, CSP (produksi), escape output, sanitasi rich text (`HtmlSanitizer`), kolom terenkripsi (`bpjs_number`, `payment_reference`), kredensial di `.env` | §12 | semua | `users`, `patient_profiles`, `appointments` | — |
| F-SYS-11 | **Otorisasi berlapis**: middleware `permission:*`, Policy per model, Form Request `authorize()`, scoping query kepemilikan pasien; Super Admin lewat `Gate::before` (aturan mutlak Super Admin ditegakkan di `StaffAccountService` dan Form Request) | §10, §12 | semua | `roles`, `permissions`, `model_has_roles` | — |
| F-SYS-12 | **Perintah Artisan**: `sessions:generate`, `appointments:mark-no-show`, `media:prune`, `app:create-super-admin` | §6, §9, §11, §13 | — | `practice_sessions`, `appointments`, `media`, `users` | — |
| F-SYS-13 | **Backup dan restore**: `scripts/backup.ps1` (mysqldump + file unggahan) dan `scripts/restore.ps1`; `APP_KEY` disimpan terpisah | §11, §15, §16 | — | seluruh database + `storage/app` | — |
| F-SYS-14 | **Data contoh (seeder)**: akun demo per peran + satu pasien, ≥ 15 dokter, ≥ 10 spesialisasi, jadwal dan libur, 4 kategori kamar, fasilitas, banner, FAQ, artikel, booking berbagai status; `DemoSeeder` hanya `local`/`testing` | §13 | — | semua tabel | — |
| F-SYS-15 | **Antarmuka responsif dan aksesibel**: Bootstrap 5, breakpoint 576/768/992/1200, WCAG 2.1 AA, komponen Blade bersama (`data-table`, `form.*`, `status-badge`, dst.), tabel admin dengan pencarian, filter, pagination, tampilan kartu di mobile | §4, §16 | semua | — | — |
| F-SYS-16 | **Kondisi antarmuka wajib**: loading, data kosong, berhasil, gagal pada setiap layar data; status = teks + ikon + warna | §4, §16 | semua | — | — |
| F-SYS-17 | **Offline-first**: semua aset (CSS, JS, font, ikon, Chart.js, Quill) dibundel lokal; placeholder lokal; peta opsional dengan fallback alamat teks | §2, §13, §16 | semua | — | — |
| F-SYS-18 | **Lokalisasi**: bahasa antarmuka Indonesia, `lang/id/*`, zona waktu `Asia/Jakarta`, format tanggal/uang Indonesia | §2 | semua | — | — |
| F-SYS-19 | **Notifikasi dan mail lokal**: `AppointmentStatusChangedNotification` dan `AppointmentScheduleChangedNotification`; kanal `database` untuk setiap transisi, kanal `mail` hanya untuk `confirmed`, `cancelled`, dan perubahan jadwal (00 F.8; default K-13); mail ke log atau Mailpit | §9, §12 | 27, 30 | `notifications` | — |
| F-SYS-20 | **Pengujian otomatis** pada MariaDB: akses per peran, kuota konkurensi, aturan booking, constraint, validasi unggahan | §16 | — | — | — |
| F-SYS-21 | **Aturan penghapusan dan penonaktifan data** (00 C): data yang dirujuk riwayat booking tidak dihapus permanen (FK `RESTRICT`); tombol hapus diganti "Nonaktifkan" bila dirujuk; `doctors`, `users`, `patient_profiles`, `appointments`, `appointment_status_histories`, `pages`, `contact_messages`, `website_settings`, dan `audit_logs` tidak punya aksi hapus | §11, §12 | 33–40 | semua tabel | Mengikuti izin entitas (lihat [04](04-peran-hak-akses.md) bagian 5) |

---

## 3. Keterlusuran Kebutuhan Asli → Fitur

| Bagian kebutuhan asli | Butir | Fitur inti yang memenuhi |
|---|---|---|
| §1 Tujuan dan ruang lingkup | Tiga bagian utama; desain orisinal; data contoh | Seluruh F-PUB, F-AUTH, F-PAS, F-ADM; F-PUB-19; F-SYS-14 |
| §2 Teknologi dan lingkungan | Laravel, MariaDB, Apache, Blade/Bootstrap, id, Asia/Jakarta, tanpa layanan berbayar | F-SYS-15, F-SYS-17, F-SYS-18; [03](03-arsitektur.md) |
| §3 Identitas dan lokasi | Halaman kontak, Petunjuk Arah, peta tersemat, alamat teks, pengaturan alamat/peta/telepon/WA/email, placeholder | F-PUB-15, F-PUB-16, F-ADM-25, F-SYS-09 |
| §4 Desain antarmuka | Warna, logo placeholder, navigasi, breadcrumb, kartu dokter, tombol utama, form, tabel admin, status teks+warna, loading/kosong/berhasil/gagal, aksesibilitas, meta | F-PUB-01, F-PUB-05, F-PUB-18, F-PUB-19, F-PUB-20, F-SYS-15, F-SYS-16 |
| §5 40 halaman | A (22), B (8), C (10) + layar tambahan | F-PUB-01–17, F-AUTH-01–07, F-PAS-01–09, F-ADM-01–28 ([05](05-sitemap-matriks-halaman.md)) |
| §6 Dokter dan jadwal | CRUD dokter, foto, jadwal mingguan, kuota, pengecualian, 13 spesialisasi, pencarian, sumber data sama | F-ADM-02–07, F-PUB-05–07, F-SYS-01, F-SYS-02 |
| §7 Fasilitas dan kamar | Kategori kamar, kapasitas, tersedia, tarif contoh, manual + waktu pembaruan, info BPJS editable | F-PUB-08–10, F-ADM-09–11, F-ADM-18 |
| §8 Akun dan portal pasien | Registrasi, login, reset, profil, ganti password, wizard, pembayaran, kuota, riwayat, batal, bukti, NIK tidak wajib, BPJS bersyarat | F-AUTH-01–05, F-PAS-01–10 |
| §9 Alur pendaftaran dan antrean | Alur, aturan, transaksi/lock, antrean unik, status, kuota, batas batal, jadwal berubah | F-PAS-04–09, F-SYS-03–06, F-ADM-08, F-ADM-14, F-ADM-15 |
| §10 Hak akses admin | 3 peran, middleware + policy, dashboard, filter tanggal, CSV, audit | F-AUTH-06–08, F-ADM-01, F-ADM-17, F-ADM-26–28, F-SYS-08, F-SYS-11 |
| §11 Database dan gambar | ERD, data dictionary, media di Storage, pisah publik/privat, penggantian, pembersihan, placeholder, backup | F-SYS-07, F-SYS-13, F-ADM-29, F-PUB-20; [08](08-erd.md), [09](09-data-dictionary.md) |
| §12 Keamanan dan validasi | Hash, CSRF, throttle, Form Request, otorisasi, query aman, escape/sanitasi, unggahan, kredensial `.env`, privasi, audit, lokal vs produksi | F-AUTH-02–09, F-SYS-07, F-SYS-08, F-SYS-10, F-SYS-11, F-PUB-17 |
| §13 Data contoh | Akun demo, ≥ 15 dokter, ≥ 10 spesialisasi, jadwal, 4 kamar, fasilitas, banner, FAQ, artikel, booking | F-SYS-14; [13](13-data-contoh.md) |
| §14 Batas fitur | Pisahkan fitur lanjutan; tombol WhatsApp ≠ API | Bagian 4 dokumen ini; F-PUB-16 |
| §15 Panduan instalasi | Windows/XAMPP | [16](16-instalasi-operasional.md) |
| §16 Pengujian | Skenario penerimaan | F-SYS-20; [15](15-pengujian-penerimaan.md) |

---

## 4. Fitur Lanjutan (tidak dibangun pada tahap ini)

### 4.1 Prinsip

1. **Fitur inti berfungsi penuh tanpa integrasi eksternal.** Tidak satu pun alur inti (registrasi, booking, antrean, bukti, admin) bergantung pada fitur lanjutan.
2. **Tidak ada simulasi yang tampil seperti fitur nyata.** Bila sebuah fitur lanjutan belum dibangun, antarmuka tidak menampilkan tombol, menu, lencana, logo, atau teks yang menyiratkan fitur itu aktif atau sedang bekerja di belakang layar. Menu "segera hadir" yang menyerupai fitur juga tidak dipakai.
3. **Titik perluasan sudah disiapkan di arsitektur**, bukan diretrofit: logika bisnis berada di `app/Services`, notifikasi memakai kelas `Notification` Laravel dengan kanal, status berupa enum PHP, disk `local` privat sudah ada, dan migration tambahan hanya **menambah** kolom/tabel tanpa mengubah kolom inti.
4. **Panggilan ke sistem eksternal tidak dilakukan di dalam transaksi database** booking/transisi. Hasilnya dibaca sebelum transaksi atau dikerjakan setelah commit lewat queue (`QUEUE_CONNECTION=database` di produksi).
5. Setiap fitur lanjutan membutuhkan keputusan dan prasyarat pemilik; tidak ada yang dapat diaktifkan hanya dengan flag.

### 4.2 Tombol WhatsApp (`wa.me`) versus WhatsApp API

| Aspek | Tombol tautan WhatsApp (**ada**, F-PUB-16) | WhatsApp API (**tidak ada**, F-NXT-05) |
|---|---|---|
| Mekanisme | Elemen `<a href="https://wa.me/{contact.whatsapp}">`; peramban membuka aplikasi/WhatsApp Web pengguna | Server memanggil API resmi WhatsApp Business Platform untuk mengirim pesan |
| Siapa yang mengirim pesan | **Pengguna** mengetik dan menekan kirim sendiri | **Server UNPAM HOSPITAL** mengirim otomatis |
| Server mengetahui hasil kirim? | Tidak; tidak ada status terkirim/terbaca | Ya (webhook status) |
| Prasyarat | Hanya nomor di `website_settings` (`contact.whatsapp`, saat ini placeholder `6280000000000`) | Akun bisnis terverifikasi, nomor khusus, templat pesan disetujui, persetujuan (opt-in) pasien, biaya per percakapan |
| Dipakai untuk | Pasien menghubungi RS (kontak, saat melewati batas batal, pertanyaan) | Pengingat jadwal, notifikasi status otomatis |
| Teks UI yang diperbolehkan | "Hubungi kami via WhatsApp" | — (tidak ditampilkan) |
| Teks UI yang **dilarang** | "Kami akan mengirim pengingat via WhatsApp", "Notifikasi WhatsApp otomatis", "Terhubung WhatsApp" | — |

Notifikasi status/jadwal pada prototipe hanya melalui kanal `database` (setiap transisi; tampil di dashboard pasien) dan `mail` (hanya `confirmed`, `cancelled`, dan perubahan jadwal; default K-13; lokal masuk log). Antarmuka tidak boleh mengatakan pasien akan menerima pesan WhatsApp.

### 4.3 Ringkasan fitur lanjutan

| ID | Fitur lanjutan | Sumber | Tergantung pada |
|---|---|---|---|
| F-NXT-01 | Integrasi BPJS (VClaim/Antrean Online) | 00 N, §14 | Kerja sama dan kredensial resmi BPJS Kesehatan |
| F-NXT-02 | Integrasi SATUSEHAT | 00 N, §14 | F-NXT-09 (NIK), registrasi organisasi, idealnya F-NXT-03/07 |
| F-NXT-03 | Integrasi SIMRS | 00 N, §14 | Spesifikasi API vendor SIMRS |
| F-NXT-04 | Payment gateway | 00 N, §14 | Model tarif/tagihan, akun merchant |
| F-NXT-05 | WhatsApp API | 00 N, §14 | Akun bisnis, templat, opt-in; F-NXT-06 untuk queue produksi |
| F-NXT-06 | Email produksi (SMTP resmi) | 00 N, §14 | Domain dan layanan SMTP |
| F-NXT-07 | Rekam medis, diagnosis, resep, hasil laboratorium (+ dokumen privat pasien) | 00 N, §8, §14 | Regulasi, peran klinis, model data klinis |
| F-NXT-08 | Sinkronisasi kamar real-time | 00 N, §14 | F-NXT-03 |
| F-NXT-09 | NIK | 00 N, §8 | Kebutuhan bisnis tertulis (mis. F-NXT-01/02), persetujuan baru |
| F-NXT-10 | Verifikasi email wajib | 00 N | F-NXT-06 |
| F-NXT-11 | Penggabungan dua profil pasien yang sama-sama punya riwayat | 00 N, F.9 | Aturan verifikasi identitas |
| F-NXT-12 | Multi-bahasa | 00 N | Strategi konten terjemahan |
| F-NXT-13 | Aplikasi mobile | 00 N | API (token) dan kebutuhan produk |
| F-NXT-14 | Autentikasi dua faktor akun staf (usulan blueprint) | Usulan | Keputusan pemilik; email/aplikasi autentikator |
| F-NXT-15 | Banyak profil per akun (anggota keluarga) (usulan blueprint) | Usulan, A-08 | K-07 diputuskan pemilik 2026-10-08: (a) satu akun satu profil (17 §1/D-140) |
| F-NXT-16 | Layar pemanggilan antrean dan pengingat otomatis (usulan blueprint) | Usulan, A-09 | F-NXT-05 atau SMS; perangkat layar |

### 4.4 Rincian per fitur lanjutan

#### F-NXT-01 — Integrasi BPJS (VClaim/Antrean Online)

- **Alasan ditunda:** butuh perjanjian kerja sama, kredensial resmi, dan lingkungan uji dari BPJS Kesehatan yang tidak tersedia untuk prototipe; booking harus tetap jalan tanpa integrasi.
- **Prasyarat integrasi:** status fasilitas kesehatan mitra, kredensial layanan web resmi, pembatasan jaringan/IP yang disyaratkan, kebijakan penyimpanan nomor kartu, dan kejelasan kebijakan kelas rawat (KRIS, lihat [01](01-ringkasan-asumsi.md) 3.3).
- **Titik perluasan:** (a) `BookingService::book()` langkah 8 (validasi `payment_type = bpjs`) memanggil antarmuka verifikasi baru **sebelum** transaksi dibuka, hasilnya dibawa ke dalam transaksi; (b) kolom tambahan pada `appointments` (mis. status verifikasi, nomor rujukan) lewat migration baru; (c) kredensial di `.env`, bukan di `website_settings`; (d) `AppointmentStatus` dan `PaymentType` tidak berubah.
- **Pernyataan UI:** nomor BPJS dicatat **sebagaimana diisi** dan diperiksa petugas di loket. Tidak boleh ada lencana "terverifikasi", frasa "cek kepesertaan otomatis", atau logo BPJS yang menyiratkan kerja sama.

#### F-NXT-02 — Integrasi SATUSEHAT

- **Alasan ditunda:** butuh registrasi organisasi di platform, identitas pasien (umumnya NIK, yang sengaja tidak dikumpulkan), dan data klinis agar bermakna.
- **Prasyarat integrasi:** kredensial klien platform, pemetaan identitas (pasien, tenaga kesehatan, lokasi, organisasi), F-NXT-09, idealnya F-NXT-03 dan F-NXT-07.
- **Titik perluasan:** kolom/tabel pemetaan identitas eksternal pada `patient_profiles`, `doctors`, `clinics`; job queue yang dipicu **setelah commit** dari `AppointmentStatusService::transition()` (mis. saat `checked_in`/`completed`); `AuditLogger` mencatat percobaan kirim tanpa isi sensitif.
- **Pernyataan UI:** tidak ada logo, lencana, atau teks "terhubung SATUSEHAT".

#### F-NXT-03 — Integrasi SIMRS

- **Alasan ditunda:** butuh spesifikasi antarmuka vendor SIMRS dan kesepakatan sumber kebenaran data (master dokter/poli/jadwal).
- **Prasyarat integrasi:** dokumen API/antarmuka, lingkungan staging, aturan pemetaan nomor rekam medis ke `patient_number`, kebijakan konflik jadwal.
- **Titik perluasan:** `PracticeSessionGenerator` dapat dilengkapi sumber impor jadwal; **`DoctorAvailabilityQuery` tetap menjadi satu-satunya sumber tampilan jadwal**; kolom `external_id` pada `doctors`, `clinics`, `practice_sessions`, `patient_profiles`, `appointments`; pengiriman appointment ke SIMRS lewat job setelah commit; `rooms` per kamar (lihat F-NXT-08).
- **Pernyataan UI:** jadwal dan data tetap berlabel dikelola admin; tidak ada teks "sinkron dengan SIMRS".

#### F-NXT-04 — Payment gateway

- **Alasan ditunda:** prototipe tidak memproses uang; `room_categories.sample_rate` hanya informasi; belum ada model tagihan.
- **Prasyarat integrasi:** model tarif dan tagihan, akun merchant, kebijakan pengembalian dana yang selaras dengan batas batal, webhook dengan verifikasi tanda tangan, kepatuhan penyedia pembayaran.
- **Titik perluasan:** tabel pembayaran baru dan `PaymentService` baru; status pembayaran **terpisah** dari `AppointmentStatus` (nilai enum tidak ditambah); route webhook di luar proteksi CSRF dengan verifikasi tanda tangan; `PaymentType` tetap hanya jenis penanggung biaya.
- **Pernyataan UI:** tidak ada tombol "Bayar", logo bank/e-wallet, atau kolom status pembayaran.

#### F-NXT-05 — WhatsApp API

- **Alasan ditunda:** butuh akun bisnis terverifikasi, templat pesan disetujui, opt-in pasien, dan biaya; fitur inti tidak membutuhkannya.
- **Prasyarat integrasi:** lihat tabel 4.2; antrean queue produksi (F-NXT-06 serupa); kolom persetujuan pesan pasien.
- **Titik perluasan:** kanal notifikasi kustom ditambahkan pada metode `via()` `AppointmentStatusChangedNotification` dan `AppointmentScheduleChangedNotification` (struktur notifikasi sudah multi-kanal); persetujuan pesan disimpan di `patient_profiles` (kolom baru); pengiriman via queue `database` setelah commit.
- **Pernyataan UI:** hanya tautan `wa.me` (F-PUB-16) dengan teks "Hubungi kami via WhatsApp". Dilarang menyebut pengingat/notifikasi otomatis lewat WhatsApp.

#### F-NXT-06 — Email produksi (SMTP resmi)

- **Alasan ditunda:** lokal memakai `MAIL_MAILER=log` (opsional Mailpit); layanan SMTP produksi belum dipilih (**[KONFIRMASI]** K-02).
- **Prasyarat integrasi:** domain pengirim, layanan SMTP/relay transaksional, SPF/DKIM/DMARC, batas kuota.
- **Titik perluasan:** hanya konfigurasi `.env` (`MAIL_*`, `MAIL_FROM_ADDRESS`) dan `QUEUE_CONNECTION=database` dengan worker; kelas notifikasi dan reset password **tidak berubah**.
- **Pernyataan UI:** teks reset password tetap generik ("Jika email terdaftar, tautan akan dikirim"); antarmuka tidak menjanjikan email diterima pada lingkungan lokal.

#### F-NXT-07 — Rekam medis, diagnosis, resep, hasil laboratorium (dan dokumen privat pasien)

- **Alasan ditunda:** di luar fokus portal (pendaftaran kunjungan); membawa risiko privasi dan regulasi data kesehatan yang jauh lebih besar.
- **Prasyarat integrasi:** ketentuan rekam medis elektronik dan pelindungan data pribadi yang berlaku, peran klinis (dokter, perawat, laboratorium), model data klinis, kontrol akses berbasis penugasan, retensi, audit akses baca, enkripsi.
- **Titik perluasan:** disk `local` privat dan pola akses **hanya lewat controller + policy** sudah ditetapkan; `AuditLogger` untuk akses baca; tautan opsional `doctors` ↔ `users` dan enum `AccountType` baru; `AppointmentStatus::completed` sebagai pemicu pembuatan kunjungan klinis; menu baru pada portal pasien.
- **Pernyataan UI:** tidak ada menu, tombol, atau placeholder "Rekam Medis", "Hasil Lab", "Resep", termasuk versi nonaktif. Panduan dan FAQ menyatakan bahwa portal hanya untuk pendaftaran kunjungan.

#### F-NXT-08 — Sinkronisasi kamar real-time

- **Alasan ditunda:** butuh sumber data tempat tidur dari SIMRS (F-NXT-03); prototipe memakai agregat manual per kategori.
- **Prasyarat integrasi:** umpan data admisi/tempat tidur dan pemetaan kategori.
- **Titik perluasan:** tabel `rooms`/`room_availability` baru (00 E.4); `room_categories.beds_available` dihitung dari tabel itu; `availability_updated_at` menjadi waktu sinkronisasi terakhir; izin `rooms.update-availability` tetap sebagai koreksi manual.
- **Pernyataan UI:** selama manual, label wajib **"Diperbarui manual oleh petugas pada {tanggal dan jam}"**. Kata "real-time", "langsung", "live", dan "otomatis" tidak dipakai untuk ketersediaan kamar.

#### F-NXT-09 — NIK

- **Alasan ditunda:** NIK tidak dikumpulkan (00 E.1; kebutuhan §8) kecuali ada kebutuhan bisnis yang dijelaskan.
- **Prasyarat integrasi:** kebutuhan bisnis tertulis (F-NXT-01/02), dasar pemrosesan dan persetujuan baru (naikkan `privacy.consent_version`), keputusan masking.
- **Titik perluasan:** kolom `patient_profiles.nik` ber-cast terenkripsi + `nik_last4`; tambahkan `nik` ke daftar redaksi `AuditLogger`; tetap dikecualikan dari CSV; validasi 16 digit pada Form Request.
- **Pernyataan UI:** form tidak menampilkan kolom NIK dan tidak menyebut NIK diperlukan.

#### F-NXT-10 — Verifikasi email wajib

- **Alasan ditunda:** butuh email produksi (F-NXT-06); lokal tidak mengirim email nyata (**[ASUMSI]** A-01).
- **Titik perluasan:** `users.email_verified_at` sudah ada; `User` mengimplementasikan `MustVerifyEmail`, middleware `verified` pada grup `patient.`, route verifikasi baru, backfill akun lama.
- **Pernyataan UI:** tidak ada pesan "verifikasi email Anda" sampai fitur aktif.

#### F-NXT-11 — Penggabungan dua profil pasien yang sama-sama punya riwayat

- **Alasan ditunda:** `PatientAccountLinker` hanya menangani profil kosong (F.9); penggabungan menyentuh FK `RESTRICT`, benturan UNIQUE (`patient_profile_id`, `practice_session_id`, `active_flag`), dan benturan jam.
- **Prasyarat integrasi:** aturan verifikasi identitas, kebijakan data ganda, UI perbandingan.
- **Titik perluasan:** service baru yang memindahkan `appointments` dalam satu transaksi dengan urutan kunci `patient_profiles` (id menaik) → `practice_sessions` → `appointments`; profil sumber **dinonaktifkan**, bukan dihapus; audit penuh.
- **Pernyataan UI:** tidak ada tombol "Gabungkan profil".

#### F-NXT-12 — Multi-bahasa

- **Alasan ditunda:** satu bahasa (Indonesia) dan konten dinamis tersimpan tunggal di tabel (`pages`, `articles`, dst.).
- **Titik perluasan:** `lang/en`, pemilih bahasa berbasis session, strategi konten terjemahan (kolom/tabel terjemahan), `hreflang`.
- **Pernyataan UI:** tidak ada pemilih bahasa atau bendera sebelum tersedia.

#### F-NXT-13 — Aplikasi mobile

- **Alasan ditunda:** kebutuhan produk belum ada; web responsif sudah memadai.
- **Titik perluasan:** `routes/api.php` berversi, autentikasi token, kelas resource JSON; controller API **memanggil service yang sama** (`BookingService`, `DoctorAvailabilityQuery`, dst.) sehingga aturan bisnis tidak diduplikasi.
- **Pernyataan UI:** tidak ada tombol "Unduh aplikasi".

#### F-NXT-14 — Autentikasi dua faktor akun staf (usulan)

- **Alasan ditunda:** tidak diminta; meningkatkan keamanan akun admin pada produksi.
- **Titik perluasan:** langkah tambahan setelah `Auth::attempt` pada login admin dan middleware tambahan pada grup `admin.`.
- **Pernyataan UI:** tidak ada pengaturan 2FA sebelum tersedia.

#### F-NXT-15 — Banyak profil per akun / anggota keluarga (usulan)

- **Alasan ditunda:** `patient_profiles.user_id` unik (A-08) agar scoping kepemilikan sederhana. K-07 diputuskan pemilik 2026-10-08: (a) satu akun satu profil (17 §1/D-140).
- **Titik perluasan:** hilangkan UNIQUE pada `user_id`, tambah kolom hubungan; wizard memiliki langkah "Untuk siapa"; kepemilikan tetap diperiksa lewat `user_id`.
- **Pernyataan UI:** wizard hanya menyebut pendaftaran untuk diri sendiri.

#### F-NXT-16 — Layar pemanggilan antrean dan pengingat otomatis (usulan)

- **Alasan ditunda:** nomor antrean pada prototipe hanya urutan pendaftaran (A-09); tidak ada layar/loket pemanggil dan tidak ada pengirim pengingat.
- **Titik perluasan:** halaman tampilan layar yang membaca `practice_sessions`/`appointments` (status `checked_in`); pengingat memakai kanal notifikasi baru (F-NXT-05 atau SMS).
- **Pernyataan UI:** tidak ada teks "Anda akan dipanggil/diingatkan otomatis".

---

## 5. Di Luar Cakupan

Butir berikut **tidak** dibangun pada tahap prototipe dan tidak dijadwalkan sebagai fitur lanjutan terdekat. Berbeda dari bagian 4, butir ini tidak memiliki titik perluasan yang disiapkan.

| No | Di luar cakupan | Catatan |
|---|---|---|
| 1 | Kasir, penagihan, akuntansi, klaim BPJS (INA-CBG), laporan keuangan | Payment gateway pun hanya fitur lanjutan |
| 2 | Farmasi/apotek, stok obat, laboratorium dan radiologi sebagai sistem, manajemen tempat tidur per pasien (admisi), IGD/triase | Kamar hanya informasi agregat |
| 3 | Pendaftaran rawat inap, IGD, atau layanan penunjang online | Portal hanya untuk rawat jalan poliklinik (A-22) |
| 4 | Telemedicine, konsultasi online, chat langsung, panggilan video | |
| 5 | Layar pemanggilan antrean dan anjungan mandiri (kiosk) | Lihat F-NXT-16 untuk pengembangan lanjutan paling dekat |
| 6 | Penjadwalan per slot waktu (mis. per 15 menit) | Kuota per sesi (A-09) |
| 7 | Multi-cabang, multi-rumah sakit, multi-tenant | A-07 |
| 8 | Login media sosial, SSO | |
| 9 | Ulasan/penilaian dokter, komentar artikel, forum | |
| 10 | Notifikasi push dan SMS | |
| 11 | Impor massal data (CSV impor dokter/pasien); ekspor selain CSV pendaftaran (PDF laporan, Excel) | |
| 12 | API publik dan webhook keluar | Aplikasi mobile hanya F-NXT-13 |
| 13 | Page builder/editor tema visual, mode gelap | Konten hanya lewat 15 halaman baku dan editor rich text |
| 14 | Manajemen SDM, penggajian, inventaris, aset | |
| 15 | Chatbot/kecerdasan buatan, rekomendasi dokter otomatis | |
| 16 | Penyediaan server produksi: HTTPS/sertifikat, domain, CI/CD, Docker, pemantauan, pemulihan bencana | Hanya perbedaan konfigurasi lokal vs produksi ([03](03-arsitektur.md)) |
| 17 | Uji beban skala besar, uji penetrasi pihak ketiga, sertifikasi keamanan, penilaian kepatuhan hukum formal | Disarankan sebelum produksi ([01](01-ringkasan-asumsi.md) 3.5) |
| 18 | Klaim akreditasi, status kerja sama resmi, dan penggunaan logo pihak lain | Dilarang, bukan sekadar ditunda |
| 19 | UI untuk dokumen privat pasien (unggah rujukan, hasil) | Hanya infrastruktur disk `local` yang disiapkan |
| 20 | Pengalihan otomatis (redirect) saat slug berubah, riwayat versi konten | A-23 |

---

## Asumsi di bagian ini

- A-02 (pengecualian satu hari penuh) dan A-26 (izin terkunci per peran) dipakai pada F-ADM-06 dan F-ADM-27.
- A-08, A-09, A-22: akun-profil, kuota per sesi, dan penunjang tidak dibooking membatasi cakupan fitur.
- **[ASUMSI]** Fitur F-NXT-14, F-NXT-15, dan F-NXT-16 ditambahkan oleh blueprint sebagai usulan pengembangan lanjutan; keputusan ada pada pemilik.
- Pembatalan pasien sampai 120 menit sebelum sesi dan pesan "hubungi RS" setelah batas dengan tautan `wa.me` (F-PAS-08) mengikuti 00 F.8 dan L (bukan asumsi).
- Keputusan baru yang tercermin di fitur: penonaktifan/pengarsipan dokter (F-ADM-02 → diputuskan di 00 F.4 / 17 D-01, D-02), edit manual sesi (F-ADM-07 → 00 F.4 / 17 D-03), aturan hapus (F-SYS-21 → 00 C), akses daftar kamar (F-ADM-11 → 00 I.4 / 17 D-12), aturan mutlak Super Admin (F-ADM-26 → 00 G / 17 D-13), volume email (F-PAS-10, F-SYS-19 → 17 K-13), verifikasi penghubungan akun (F-ADM-13 → 17 K-14), pelacakan "sudah dihubungi" (F-ADM-08 → 17 K-15).
- **[KONFIRMASI]** K-02 (SMTP produksi) berdampak pada F-NXT-06 (ID mengikuti 17); K-07 (banyak profil per akun) sudah diputuskan pemilik 2026-10-08: (a) satu akun satu profil (17 §1/D-140), sehingga F-NXT-15 tetap usulan.

## Nama baru yang diusulkan

Tidak ada. (Frasa seperti "antarmuka verifikasi", "tabel pembayaran", dan "service penggabungan" pada bagian 4.4 adalah deskripsi titik perluasan, bukan nama kelas/tabel yang diusulkan masuk ke 00.)
