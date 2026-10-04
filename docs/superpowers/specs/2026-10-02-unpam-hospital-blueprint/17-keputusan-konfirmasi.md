# 17 — Keputusan yang Masih Membutuhkan Konfirmasi

**Tujuan bagian.** Menghimpun semua penanda **[KONFIRMASI]** dari bagian 00–16 menjadi satu daftar keputusan yang bisa dijawab pemilik, lengkap dengan opsi, default yang dipakai blueprint bila belum dijawab, dampak, dan kapan keputusan dibutuhkan. Bagian ini juga mencatat keputusan yang sudah diambil pemilik dan keputusan desain yang ditetapkan blueprint untuk menutup ambiguitas (dapat diubah pemilik).

Terkait: [00 — Keputusan Inti](00-keputusan-inti.md) · [01 — Ringkasan & Asumsi](01-ringkasan-asumsi.md) (daftar induk asumsi A-xx dan K-01–K-08) · [12 — Aturan Bisnis, Validasi, dan Keamanan](12-aturan-bisnis-validasi-keamanan.md) · [14 — Tahapan Implementasi](14-tahapan-implementasi.md) · [16 — Instalasi & Operasional](16-instalasi-operasional.md).

> **Prinsip:** setiap butir memiliki default yang aman sehingga implementasi **tidak terhambat** bila belum dijawab. Default hanya berlaku untuk prototipe localhost; butir bertanda "Sebelum produksi" wajib dijawab sebelum aplikasi dipakai pasien nyata.

---

## 1. Keputusan yang Sudah Diambil Pemilik

| ID | Keputusan | Pilihan pemilik | Tercatat di |
|---|---|---|---|
| K1 | Penanganan pasien walk-in tanpa akun | Petugas dapat membuat profil pasien tanpa akun dan mendaftarkannya pada sesi yang sama (kuota bersama). Booking merujuk `patient_profiles`. | 00 A, F.9 |
| K2 | Pendekatan arsitektur | Monolit Laravel 12, Blade + Bootstrap 5 di seluruh aplikasi | 00 A, 03 |
| K3 | Desain akses, mesin booking/antrean, dan model data | Disetujui saat brainstorming 2026-10-02 | 00 |
| — | Lingkungan | XAMPP 8.2.12 terpasang di `C:\xampp3` (Apache 2.4.58, PHP 8.2.12, MariaDB 10.4.32) → Laravel 12 | 00 B |

---

## 2. Dibutuhkan Sebelum atau Selama Implementasi

Butir ini memengaruhi kode/struktur. Bila belum dijawab, implementasi memakai kolom **Default**.

| ID | Pertanyaan | Opsi | Default blueprint | Dampak bila diubah kemudian | Dibutuhkan sebelum |
|---|---|---|---|---|---|
| K-07 | Perlukah satu akun pasien memiliki banyak profil (mis. anggota keluarga)? | (a) Satu akun satu profil; (b) satu akun banyak profil | (a) — `patient_profiles.user_id` UNIQUE | Mengubah constraint, scoping kepemilikan, dan langkah "untuk siapa" pada wizard (F-NXT-15) | Fase 5 (portal pasien) |
| K-10 | Urutan eksekusi fase implementasi | (a) Situs publik dulu: 0→1→2→3→4→5→6→7→8→9; (b) risiko booking dulu: 0→1→2→3→5→6→7→4→8→9 | (a) | Hanya urutan kerja; tidak mengubah desain | Rencana fase pertama |
| K-11 | Password akun demo | (a) Tetap `Demo12345` (hanya lokal); (b) acak per eksekusi seeder, dicetak di konsol | (a) | Hanya `DemoSeeder` | Fase 9 (seeder lengkap) |
| K-12 | Akun `root` MariaDB di mesin pemilik | (a) Dibiarkan tanpa password (bawaan XAMPP, hanya localhost); (b) diberi password + phpMyAdmin memakai login cookie | (a), aplikasi tetap memakai user khusus `unpam_app` | Hanya langkah instalasi (16 §4.3) | Fase 0 |
| K-13 | Volume notifikasi email ke pasien | (a) Setiap transisi status (sesuai 00 F.8); (b) hanya konfirmasi, pembatalan, dan perubahan jadwal; notifikasi database tetap untuk semua | (b) **[ASUMSI]** agar tidak berisik; notifikasi `database` tetap dicatat untuk semua transisi | Hanya `via()` pada kelas notifikasi | Fase 7 |
| K-22 | Email konfirmasi setelah pasien mendaftar akun | (a) Ya (selamat datang, tanpa verifikasi wajib); (b) tidak | (a) | Satu kelas notifikasi | Fase 5 |
| K-04 | Perlukah Petugas Pendaftaran aksi "tampilkan nomor BPJS/polis lengkap" (tercatat di audit)? | (a) Tidak, hanya 4 digit terakhir; (b) ya, dengan izin tambahan dan audit | (a) | Tambah izin + aksi + audit event | Fase 7 |
| K-17 | Font pada PDF bukti pendaftaran | (a) DejaVu Sans bawaan dompdf; (b) TTF Plus Jakarta Sans dipasang ke dompdf | (a) | Hanya konfigurasi dompdf + file font lokal | Fase 6 |
| K-15 | Perlukah pelacakan "sudah dihubungi" untuk pasien walk-in yang jadwalnya berubah? | (a) Tidak (cukup daftar + telepon di layar Sesi terdampak); (b) ya, kolom/tanda per appointment | (a) | Tambah kolom `appointments` + UI | Fase 7 |
| K-16 | Ambang penerimaan aksesibilitas | (a) Lighthouse a11y ≥ 90 + checklist keyboard; (b) ditambah audit manual pembaca layar (NVDA) | (a) | Hanya proses uji | Fase 9 |

---

## 3. Dibutuhkan Sebelum Produksi

Tidak memengaruhi prototipe localhost; wajib dijawab sebelum aplikasi dipakai pasien nyata.

| ID | Pertanyaan | Default prototipe | Catatan |
|---|---|---|---|
| K-01 | Versi database produksi: MariaDB ≥ 10.11 LTS atau MySQL 8.4 LTS? | MariaDB 10.4.32 (XAMPP, sudah EOL dari vendor) | Migration portabel ke keduanya; pemetaan galat constraint memeriksa nomor error (MariaDB 4025 / MySQL 3819 untuk CHECK). |
| K-02 | Penyedia SMTP produksi | `MAIL_MAILER=log` / Mailpit | Hanya `.env` + worker queue. |
| K-03 | Jadwal upgrade runtime & framework | PHP 8.2.12 + Laravel 12 | **PHP 8.2 security berakhir 31 Desember 2026** (php.net), Laravel 12 security berakhir 24 Februari 2027. Rekomendasi: produksi memakai PHP 8.4 + Laravel 13. Lihat §5. |
| K-05 | Penamaan kategori kamar terhadap ketentuan BPJS terbaru (arah Kelas Rawat Inap Standar/KRIS) | "BPJS Kelas 1/2/3", "VIP/Pribadi" (nama prototipe) | Kategori adalah data, bukan enum → cukup ubah via admin/seeder. |
| K-06 | Telaah hukum kebijakan privasi & persetujuan terhadap UU No. 27 Tahun 2022 tentang Pelindungan Data Pribadi dan ketentuan data kesehatan; termasuk apakah kolom `complaint` dipertahankan/dienkripsi dan apakah perubahan `privacy.consent_version` mewajibkan persetujuan ulang | Teks contoh; `complaint` opsional tanpa enkripsi; tanpa persetujuan ulang otomatis | Lihat 12 §7.7. |
| K-08 | Data kontak resmi (telepon, IGD, WhatsApp, email, jam operasional) | Placeholder jelas | Hanya pengaturan admin. |
| K-09 | Kebijakan retensi & penghapusan/anonimisasi (audit log, riwayat status, pesan kontak, profil pasien) termasuk permintaan subjek data | Tidak ada penghapusan otomatis | Usulan awal angka retensi di 09; penghapusan swalayan = pengembangan lanjutan. |
| K-14 | Verifikasi saat menghubungkan profil walk-in ke akun online | Verifikasi identitas di loket oleh petugas | Produksi disarankan kode verifikasi ke email akun. |
| K-18 | Autentikasi dua faktor untuk staf | Tidak ada | Disarankan untuk produksi. |
| K-19 | Pembatasan hak database: akun aplikasi hanya `INSERT, SELECT` pada `audit_logs` dan `appointment_status_histories` | Hak penuh pada database aplikasi (lokal) | Memperkuat sifat tak-dapat-diubah audit. |
| K-20 | Enkripsi backup & disk, lokasi penyimpanan backup, dan penyimpanan `APP_KEY` | Backup lokal tanpa enkripsi ke `backups/` (di-`.gitignore`) | Tanpa `APP_KEY` yang sama, kolom terenkripsi tidak bisa dibaca setelah restore. |
| K-23 | Pemantauan & alarm (login gagal staf berulang, galat aplikasi) | Hanya audit log + log harian | Di luar lingkup prototipe. |

---

## 4. Keputusan Desain yang Ditetapkan Blueprint (dapat diubah pemilik)

Ambiguitas yang ditemukan saat penulisan blueprint dan sudah ditutup di 00. Dicatat di sini agar pemilik bisa menolaknya secara eksplisit.

| No | Topik | Keputusan | Lokasi |
|---|---|---|---|
| D-01 | Dokter dinonaktifkan saat masih punya booking aktif | Sesi mendatang tanpa booking → dibatalkan; dengan booking → Sesi terdampak (`doctor_inactive`). | 00 F.4 |
| D-02 | Dokter diarsipkan | Hanya bila tidak ada booking aktif mendatang. | 00 F.4 |
| D-03 | Edit manual satu sesi berpendaftaran aktif | Kuota langsung (≥ booked_count); jam → `manual_time`; pembatalan → `manual_cancel` (Sesi terdampak). | 00 F.4 |
| D-04 | Pengecualian dicabut ketika sesi masih ditinjau | Sesi kembali dibuka. | 00 F.4 |
| D-05 | Prioritas label ketersediaan | past → leave/holiday/cancelled → under_review → closed → full → booking_closed → available. | 00 F.3 |
| D-06 | Kelayakan booking loket vs online | `is_bookable_counter` (sampai jam selesai) terpisah dari `is_bookable_online` (sampai 60 menit sebelum mulai). | 00 F.3 |
| D-07 | Email profil vs email login | Untuk pasien berakun selalu disinkronkan. | 00 E.1 |
| D-08 | Penonaktifan pasien berakun | Ikut menonaktifkan `users.is_active`. | 00 E.1 |
| D-09 | Galat sibuk setelah 3 percobaan transaksi | `SYSTEM_BUSY` dengan pesan ramah, tanpa data tersimpan sebagian. | 00 F.5 |
| D-10 | Fallback gambar rusak | Listener JavaScript terdelegasi (kompatibel CSP), bukan `onerror` inline. | 00 K |
| D-11 | Dimensi minimum unggahan | Per koleksi (logo 120×40, foto 300×300, banner 1200×400, dst.). | 00 K |
| D-12 | Akses daftar kamar untuk Petugas Pendaftaran | Melihat daftar + form ketersediaan saja (izin `rooms.update-availability`). | 00 I.4 |
| D-13 | Aturan mutlak Super Admin | Ditegakkan di `StaffAccountService` + Form Request karena `Gate::before` melewati Policy. | 00 G |
| D-14 | Parameter query | Slug untuk poliklinik/dokter/spesialisasi/kategori; id untuk sesi; `YYYY-MM-DD` untuk tanggal/minggu. | 00 I.5 |
| D-15 | Default `appointments:mark-no-show` | Tanpa `--date` memproses semua sesi yang sudah selesai. | 00 F.8 |
| D-16 | Aturan hapus per tabel | Hapus permanen hanya untuk konten ringan; data yang dirujuk riwayat booking dinonaktifkan/diarsipkan. `room_categories` secara teknis dapat dihapus (tidak dirujuk FK), tetapi UI menyarankan "Nonaktifkan" karena halaman kamar 12–15 bergantung pada datanya. | 00 C |
| D-17 | Galat resolusi sesi & penghubungan akun | Memakai `ValidationException` (tanpa kelas exception khusus); transisi status memakai `InvalidTransitionException` dengan 12 kode baku. | 00 F.5 |
| D-18 | Generator sesi | Hanya templat aktif dengan dokter `active` dan poliklinik aktif. | 00 F.2 |
| D-19 | Hapus sesi | Hanya sesi tambahan manual tanpa appointment (`admin.sessions.destroy`). | 00 I.4 |
| D-20 | CHECK `chk_schedule_exceptions_scope` (kombinasi jenis pengecualian vs dokter) | Ditambahkan di 09 sebagai pengaman kedua di samping validasi Form Request; **wajib diuji di MariaDB 10.4 pada fase 3** sebelum dipertahankan. | 09, 14 |
| D-21 | Menu dan tautan layout ke route fase berikutnya | Disaring `Route::has()`; pengalihan tamu/pengguna memakai `Route::has('login')`/`Route::has('patient.dashboard')` dengan cadangan `route('home')`. Cadangan dihapus di fase 5. | Rencana fase 1 (F1-R2) |
| D-22 | Login staf dengan akun nonaktif | Pesan generik "Email atau password salah." (bukan pesan khusus); alasan `inactive` hanya di audit `auth.staff_login_failed`. | 00 H, 12 V-03 (F1-R5) |
| D-23 | Throttle pada permintaan POST | Redirect 302 kembali ke form + flash `error` "Terlalu banyak percobaan. Silakan coba lagi dalam {n} detik." + header `Retry-After`; pada GET halaman 429. Berlaku untuk semua route POST ber-throttle (`login`, `register`, `password-reset`, `booking`, `contact`); baris "429" untuk POST di 06 dibaca sebagai respons ini. | 11 §11.3, 15 SEK-03 (F1-R6) |
| D-24 | Signature `AuditLogger` | `log()` menerima `Model $model` wajib (instance kosong untuk aksi tanpa baris); `redact(array $values, string $action, ?Model $model = null)`; `ip_address`/`user_agent` NULL di luar permintaan HTTP ter-*route*. | 11 §6.10 (F1-R7, F1-R8) |
| D-25 | Seeder demo fase 1 | `DemoSeeder` + `DemoUserSeeder` hanya tiga akun staf; akun pasien demo menyusul di fase 5; password dari `config('app.demo_password')` (`DEMO_PASSWORD`, kosong = `Demo12345`). | 13 §2.3, §3 (F1-R10) |
| D-26 | Cakupan `media:prune` fase 1 | (a) baris `model_id` NULL > 24 jam kecuali logo situs, (b) berkas tak tercatat > 1 jam; butir pemilik hilang menunggu model pemilik di fase 2. | 11 §6.9 (F1-R12) |
| D-27 | Detail pesan halaman 403 | Pesan `UnauthorizedException` Spatie dan pesan bawaan framework (berbahasa Inggris) tidak pernah ditampilkan sebagai `$detail`; halaman 403 memakai teks Indonesia generik. | 11 §11.3, 15 ERR-02 |
| D-28 | Endpoint media admin (F-ADM-29) | Ditunda dari fase 1 ke fase 2 (bersama model pemilik pertama); `MediaService` sudah tersedia sejak fase 1. | 14 §4 (F1-R21) |
| D-29 | Editor rich text dan endpoint media | Dibangun di fase 2: `x-form.rich-text`, `resources/js/editor.js`, `MediaController`, `MediaPolicy`, tiga Form Request V-42, empat route `admin.media.*`. Fase 4a memakai ulang editor. Menutup D-28. | 14 §Fase 2 tugas 4, 11 §2.3 J (F2-R2, F2-R3) |
| D-30 | Route key | Model tidak menimpa `getRouteKeyName()`; route publik `{clinic:slug}`; route admin memakai id numerik. | 11 §2.4 (F2-R4) |
| D-31 | Slug | Otomatis lewat trait `HasUniqueSlug` (dari nama tanpa gelar; sufiks `-2`, `-3`; dipotong sesuai kolom; tidak berubah saat nama diedit); form tanpa input slug. | 12 V-19/V-21, BR-15 (F2-R5) |
| D-32 | Form dokter tanpa status | Dokter baru selalu `active`; status lewat `admin.doctors.status`. | 12 V-19 (F2-R6) |
| D-33 | Pemulihan dari arsip | Lewat `admin.doctors.status` dengan `inactive`; `archived_at` dikosongkan; nilai lain ditolak. | BR-06 (F2-R7) |
| D-34 | Aturan F.4 pada fase 2 | Belum ditegakkan; titik sambung `DoctorService::futureActiveAppointmentsCount()` (selalu 0) diganti di fase 3. | 14 §Fase 2 tugas 6 (F2-R8) |
| D-35 | Halaman #5 fase 2 | `x-doctor-card` tanpa jadwal dan tanpa tombol daftar; tautan hanya bila route ada. | 14, F1-R2 (F2-R9) |
| D-36 | Halaman #4 fase 2 | Judul statis "Layanan Kami" sampai `pages` key `clinics-index` (fase 4a). | 05 hal. 4 (F2-R10) |
| D-37 | `x-module-tabs` | Dibangun di fase 2 untuk tab Poliklinik/Spesialisasi. | 05 hal. 34 (F2-R11) |
| D-38 | Paginasi | `per_page` admin default 25; halaman #4 tanpa paginasi, dikelompokkan per `ClinicType`. | 00 I.5 (F2-R12) |
| D-39 | Batas `clinics.description` | `max:30000` pada input mentah. | 09 §1.2 butir 10 (F2-R13) |
| D-40 | Sanitasi rich text | Hanya di trait `SanitizesRichText` saat `saving`. | 11 §6.12 (F2-R14) |
| D-41 | Nama field unggahan | Editor `file` + `alt_text`; foto dokter `photo` + `alt_text`/`photo_alt`; poliklinik `cover`/`cover_alt`, `gallery[]`/`gallery_alt[]` (maks. 20). | 12 V-19/V-20/V-22/V-42 (F2-R15) |
| D-42 | `MediaPolicy` | Pemetaan per pemilik; `model_type` NULL: `logo` → `settings.manage`, `editor` → pengunggah atau salah satu izin kelompok; tipe lain ditolak sampai fasenya. | 11 §8, 04 §5.1 (F2-R16) |
| D-43 | Hapus media | Foto, cover, galeri boleh dihapus; yang dirujuk `site.logo_media_id` ditolak. | 12 V-42 (F2-R17) |
| D-44 | Penolakan hapus | Di controller lewat `deletionBlockedReason()` (berlaku juga bagi Super Admin). | 11 §2.3/§4.4 (F2-R18) |
| D-45 | `AuditObserver` | `App\Observers\AuditObserver`; fase 2 hanya `Specialty` dan `Clinic`. | 10 §2, 11 §5.9 (F2-R19) |
| D-46 | `DoctorService` | Satu-satunya jalur tulis dokter, dengan audit eksplisit. | 09 I-5/I-23 (F2-R20) |
| D-47 | Setelah simpan | Redirect ke `index` dengan flash dari `lang/id/messages.php`. | 11 P-CRUD (F2-R21) |
| D-48 | Seeder master | `SpecialtySeeder`, `ClinicSeeder`, `DoctorSeeder` hanya demo, dipanggil `DemoSeeder`; gambar dari `PlaceholderImageGenerator`. | 13 §2.1–2.3 (F2-R22) |
| D-49 | `meta_title` | Maksimal 70 karakter. | 12 BR-123 (F2-R23) |
| D-50 | `Doctor::primarySpecialty` | Accessor dari relasi `specialties` yang dimuat; tanpa model pivot dan tanpa relasi media khusus. | 11 §5.2 (F2-R24) |
| D-51 | Filter daftar admin | `DoctorIndexRequest` dan `MasterDataIndexRequest` (turunan `ListFilterRequest`). | 07 W-15, 12 V-45 (F2-R25) |
| D-52 | Carry fase 1 | Dikerjakan di fase 2: checkbox larik, `x-data-table`, meta CSRF, poles uji manual, `media:prune` butir (c). | CHECKPOINT §4 (F2-R26) |
| D-53 | Carry pengaturan situs | WhatsApp `62…` dan bool/int ketat ditunda ke fase 8 (form pengaturan). | 14 §Fase 8 (F2-R27) |
| D-54 | Asersi CRD fase 2 | CRD-01/CRD-03 memakai scope `publiclyVisible` dan tampilan #5; asersi URL dokter di fase 3. | 14 §Fase 2 (F2-R28) |
| D-55 | Gambar editor dan `media:prune` | `MediaService::syncEditorImages()` mengikat gambar editor yang dirujuk ke pemiliknya (hook `saved` `SanitizesRichText`) dan melepas yang tidak dirujuk; `Media::orphaned()` memakai `updated_at`; `prune` butir (a) melewati baris `editor` yang masih dirujuk kolom di `MediaService::EDITOR_REFERENCE_COLUMNS`. Fase 4a wajib menambah kolom rich text halaman/artikel ke konstanta itu. Gambar yang dirujuk dua pemilik tetap milik yang pertama (hapus pemilik pertama menghapus berkasnya). | 08 §5.2, 11 §6.9 (final review fase 2) |
| D-56 | Spesialisasi nonaktif di halaman publik | Data dokter publik hanya memuat spesialisasi aktif; spesialisasi utama nonaktif jatuh ke spesialisasi aktif pertama, tanpa spesialisasi aktif = tanpa lencana. | Review Focus 4 rencana fase 2 (final review) |
| D-57 | Alt cover/foto tanpa berkas baru | Mengubah `cover_alt`/`photo_alt` tanpa berkas baru memperbarui alt media yang ada (`MediaService::updateMeta`); alt kosong saat media ada ditolak. | 12 V-20/V-22 (final review fase 2) |
| D-58 | Rich text kosong | Hasil sanitasi tanpa teks dan tanpa `<img>` (mis. `<p><br></p>` dari Quill) disimpan NULL. | 11 §5.1 `SanitizesRichText` (final review fase 2) |

Asumsi berdampak terbesar dari daftar induk 01 yang juga layak ditinjau pemilik:

| Asumsi | Isi | Alasan ditinjau |
|---|---|---|
| `booking.max_active_per_patient = 3` | Batas booking aktif mendatang per pasien online | Bisa terlalu ketat/longgar; dapat diubah di pengaturan tanpa kode. |
| Verifikasi email tidak diwajibkan | Pasien bisa booking tanpa verifikasi email | Mail lokal hanya ke log; untuk produksi pertimbangkan wajib verifikasi. |
| `rooms.update-availability` & `messages.manage` untuk Petugas Pendaftaran | Petugas loket memperbarui tempat tidur dan membaca pesan kontak | Bergantung pembagian tugas nyata; dapat diubah di halaman Hak Akses. |
| Grafik dashboard berdasarkan tanggal kunjungan | Bukan tanggal pembuatan booking | Pemilik mungkin ingin keduanya. |
| Pengecualian jadwal per hari penuh | Cuti setengah hari dilakukan dengan mengedit sesi | Bila sering terjadi, pertimbangkan pengecualian per jam. |
| Batas waktu: booking online ditutup 60 menit sebelum mulai; pembatalan pasien paling lambat 120 menit; check-in dibuka 60 menit sebelum mulai | Semua di `website_settings` | Dapat diubah admin tanpa kode. |

---

## 5. Rencana Upgrade Runtime (rekomendasi untuk K-03)

1. **Sekarang (prototipe):** PHP 8.2.12 (XAMPP) + Laravel 12. Kode ditulis kompatibel PHP 8.2–8.4 (tanpa fitur khusus 8.3/8.4) dan memakai `config.platform.php = 8.2.12`.
2. **Sebelum 31 Desember 2026 / sebelum produksi:** pindah runtime ke PHP 8.4 (mis. XAMPP versi baru dengan PHP 8.4, atau Apache + PHP 8.4 terpisah). Ubah `config.platform.php`, jalankan seluruh tes.
3. **Setelah runtime PHP ≥ 8.3:** upgrade Laravel 12 → 13 mengikuti panduan upgrade resmi; jalankan seluruh tes (termasuk tes konkurensi di MariaDB/MySQL produksi).
4. **Database:** migrasi ke MariaDB ≥ 10.11 LTS atau MySQL 8.4 LTS (K-01) dengan dump/restore dan uji invarian data (09).

---

## 6. Cara Menjawab

Pemilik cukup menjawab per ID, misalnya: "K-07: (a), K-10: (b), K-13: (a)". Jawaban dicatat dengan memperbarui kolom Default di tabel ini dan, bila mengubah nama/aturan, juga di [00 — Keputusan Inti](00-keputusan-inti.md).

## Asumsi di bagian ini

- K-13 default (b) memperketat 00 F.8 (yang menyebut mail untuk setiap transisi) **[ASUMSI]**: notifikasi `database` tetap untuk setiap transisi, email hanya untuk konfirmasi, pembatalan, dan perubahan jadwal.

## Nama baru yang diusulkan

Tidak ada (ID K-09–K-23 dan D-01–D-20 hanya penomoran dokumen).
