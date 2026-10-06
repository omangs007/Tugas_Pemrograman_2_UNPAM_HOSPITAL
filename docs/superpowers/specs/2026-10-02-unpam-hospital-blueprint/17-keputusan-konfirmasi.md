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
| D-59 | Urutan migration fase 3 | Tujuh migration memakai nama dan nomor 09 §10 persis, termasuk `000100` (`patient_profiles`) yang urut lebih awal dari migration fase 2 yang sudah jalan: Laravel menjalankannya sebagai migration tertunda, `migrate:fresh` mengikuti urutan nama. | 09 §10 (F3-R2) |
| D-60 | `insurance_partners` di fase 3 | Tabel dibuat di fase 3 dengan skema 09 apa adanya karena `appointments.insurance_partner_id` wajib FK `RESTRICT`; model, factory, seeder, dan admin-nya tetap di fase 4; `Appointment` fase 3 tanpa relasi `insurancePartner()`. | 00 E.3, 09 §10 (F3-R3) |
| D-61 | Skema booking tanpa UI | `patient_profiles`, `appointments`, dan `appointment_status_histories` hanya berupa skema, model, factory, dan pembantu tes; `PatientSeeder` dan akun pasien demo di fase 5 (D-25). | 14 §Fase 3 (F3-R4) |
| D-62 | Bagian `book()` ditunda | Bagian `book()`, wizard, dan walk-in dari JDW-03/04/05/12/13, CRD-02/05, dan KON-01a–d ditunda ke fase 6/7; di fase 3 kasus itu diuji lewat `DoctorAvailabilityQuery` dan halaman #6/#7/#8. JDW-11 milik fase 7. | 14 §Fase 3, 15 (F3-R5) |
| D-63 | KON-01 versi spike | `ConcurrencySpikeTest` dengan dua koneksi PDO mentah membuktikan lock wait 1205, urutan kunci baku, UNIQUE `active_flag`, dan CHECK kuota; versi penuh dengan `BookingService` di fase 6. | 14 tugas 1, 15 §6.2 (F3-R6) |
| D-64 | Tanpa sesi lampau demo | `ensureRange` diklem ke hari ini (BR-22); sesi lampau demo beserta appointment `completed`/`no_show` dibuat seeder appointment di fase 6/7; angka "±300 sesi" diverifikasi saat itu. | 13 §7.1, 09 §15.1 (F3-R7) |
| D-65 | Kunci generator | `ensureDailyHorizon()` memakai `Cache::lock(..., 30)->get()` tanpa menunggu (gagal kunci ⇒ 0 dan halaman tetap tampil); `ensureRange()` tidak mengunci; `sessions:generate` memakai `block(10)` dan keluar dengan kode 1 bila habis waktu. | 11 §6.4 (F3-R8) |
| D-66 | Pemicu pembangkitan harian | Halaman publik #6, #7, dan #8 memicu `ensureDailyHorizon()` di fase 3; beranda di fase 4a dan wizard di fase 6. | 00 F.2(b) (F3-R9) |
| D-67 | Pengecualian tumpang tindih | Dipilih yang khusus dokter, lalu `id` terkecil; aturan sama dipakai generator dan `removeException`. | 12 BR-21 (F3-R10) |
| D-68 | `applyException` dan `needs_review` | Sesi `needs_review` yang punya appointment aktif ikut diproses lewat `setPendingChange` (prioritas F.4); sesi `cancelled` tidak disentuh. | 00 F.4, 11 §6.6 (F3-R11) |
| D-69 | Urutan klasifikasi label | `classify()` memakai tabel 11 §6.5 (`past` → `cancelled` → `under_review` → `closed` → `full` → `booking_closed` → `available`); `is_bookable_*` = `classify(...) === available`. | 11 §6.5 (F3-R12) |
| D-70 | Badge memakai enum apa adanya | Warna dan ikon `Availability`/`SessionStatus` mengikuti enum di kode; perbedaan 07 §7 (ditandai [ASUMSI]) kalah karena `EnumCatalogTest` mengunci kode. | 10 §4, 07 §7 (F3-R13) |
| D-71 | Sumber `pending_change` | Konstanta string di `PracticeSession` (`SOURCE_*`, `CANCEL_SOURCES`, `TIME_SOURCES`), bukan enum baru; payload persis 00 F.4 dengan jam `"HH:MM"`. | 00 F.4 (F3-R14) |
| D-72 | Appointment aktif mendatang | Satu definisi: `Appointment::scopeUpcomingActive()`; `DoctorService::futureActiveAppointmentsCount()` memakainya; nama `ScheduleChangeService::countFutureActiveAppointments` tidak dibuat. | 12 BR-05, D-34 (F3-R15) |
| D-73 | Status dokter dan jadwal | `changeStatus()` ke `inactive` dan `archive()` memanggil `applyDoctorInactive()` dalam transaksi yang sama; mengaktifkan kembali tidak membuka otomatis sesi `cancelled`; flash membawa ringkasan laporan. | 00 F.4, 11 §6.6 (F3-R16) |
| D-74 | Halaman publik dokter | #6 dan #7 memakai `Doctor::publiclyVisible()`; dokter yang tidak tampil di #7 menjawab 404, juga bagi staf yang login. | 05 hal. 5, D-54 (F3-R17) |
| D-75 | Judul statis #6 dan #8 | "Cari Dokter" dan "Jadwal Dokter" beserta subjudul dan meta description tetap sampai model `Page` hadir di fase 4a. | 13 baris 369–370, D-36 (F3-R18) |
| D-76 | Tab modul dan banner #35 | Empat tab (Templat Mingguan, Sesi per Tanggal, Pengecualian, Sesi Terdampak yang tersembunyi sampai fase 7) dan banner "N sesi perlu ditindaklanjuti petugas pendaftaran" bila N > 0. | 10/11/14, 05/07 (F3-R19) |
| D-77 | `admin.sessions.destroy` ada | 00 I.4 menang atas 02/04/05/07: hapus hanya untuk sesi tambahan manual tanpa appointment sama sekali; selain itu disarankan Tutup/Batalkan. | 00 C/I.4 (F3-R20) |
| D-78 | Ubah pengecualian | `start_date` boleh tetap di masa lalu bila nilainya tidak berubah; pengecualian baru atau `start_date` yang diubah wajib ≥ hari ini. | 12 V-25, BR-24 (F3-R21) |
| D-79 | Filter admin #35 | `DoctorScheduleIndexRequest`, `ScheduleExceptionIndexRequest`, dan `PracticeSessionIndexRequest` (turunan `ListFilterRequest`; parameter `hari` dan `jenis` baru). | 07 W-17, 11 §9.3, 00 I.5 (F3-R22) |
| D-80 | Ringkasan dampak | `ScheduleChangeReport::message()` menyusun bagian bernilai > 0 berurutan, dipisah koma, diakhiri titik; flash simpan = pesan baku + spasi + ringkasan. | K-17 (F3-R23) |
| D-81 | Dua tata letak #8 | Grid mingguan (≥ md, partial `_week-grid`) dan daftar bertumpuk per hari (< md, `x-schedule-table`) dari koleksi yang sama; `minggu` dinormalisasi ke Senin dan dibatasi pada `[minggu ini, minggu yang memuat today + window_days]`. | 07 W-05, 10 §3.3 (F3-R24) |
| D-82 | Kartu dokter dengan sesi terdekat | Prop `nextSession` pada `x-doctor-card`; tanpa tombol "Lihat Jadwal" terpisah (tautan bersarang dilarang); tombol Daftar hanya bila route pendaftaran ada dan sesi dapat dipesan online. | 10 §3.3, D-35 (F3-R25) |
| D-83 | Sesi manual dan templat | Sesi manual wajib dokter `active`, tidak bentrok jam, dan tidak di masa lalu; templat boleh untuk dokter `inactive` (dilewati generator) tetapi tidak untuk `archived`. | 12 V-23/V-24 (F3-R26) |
| D-84 | Sesi lampau tidak diubah | Sesi yang `end_time`-nya sudah lewat tidak dapat diubah, ditutup, dibatalkan, atau dihapus lewat UI/service; propagasi hanya menyentuh sesi yang belum berakhir. Aturan ini juga mengikat Super Admin. | 12 V-24, 00 F.4 (F3-R27) |
| D-85 | Seeder demo jadwal | `DoctorScheduleSeeder` dan `ScheduleExceptionSeeder` setelah `DoctorSeeder`, data persis 13 §7; sesi tambahan dr. Teguh memakai tanggal pertama ≥ today + 2 yang bukan hari templatnya; sesi tutup contoh lewat `setSessionStatus`. | 13 §2.1, §7 (F3-R28) |
| D-86 | Carry fase 2 | Dikerjakan di fase 3: `clinicHasAppointments()`, `Clinic::deletionBlockedReason()`, `futureActiveAppointmentsCount()`, `applyDoctorInactive()`, asersi CRD-01/03 publik, dan tes bersyarat `Route::has` fase 2 diganti tes positif. | CHECKPOINT §4 (F3-R29) |
| D-87 | Admin Konten tanpa data pasien | Layar #35 hanya menampilkan `booked_count`/`quota` ("Terisi"); tidak ada nama, telepon, atau kode booking. | 00 G, 04 §6 (F3-R30) |
| D-88 | `Appointment::$fillable` | Mengikuti 09 §4.1: hanya kolom isian (`patient_profile_id`, `practice_session_id`, `doctor_id`, `clinic_id`, `session_date`, `channel`, `payment_type`, `insurance_partner_id`, `payment_reference`, `complaint`, `created_by_user_id`) yang fillable; `booking_code`, `queue_number`, `status`, kolom waktu status, `cancelled_by_*`, `cancel_reason`, dan `schedule_changed_at` diisi lewat `forceFill()` oleh service fase 6; `active_flag` tidak pernah ditulis. | 11 §5.4, 09 §4.1 (catatan penulis fase 3, A5) |
| D-89 | Flash status dan arsip dokter | Tetap kunci `success` dengan ringkasan `ScheduleChangeReport::message()` ("Dokter dinonaktifkan. 1 sesi dibatalkan, 1 sesi perlu ditindaklanjuti petugas pendaftaran."); kunci `warning` pada 12 BR-05/V-20 tidak dipakai. | 12 BR-05, V-20; 11 §4 (B3) |
| D-90 | `deleteTemplate()` dan sesi lampau | Hapus templat menghapus seluruh sesinya yang tanpa appointment, termasuk yang lampau: pengecualian sadar atas D-84, yang berlaku untuk operasi per sesi. Templat yang punya sesi ber-appointment (termasuk riwayat batal) tetap ditolak. | 12 BR-12, 09 §3.5, JDW-19a (B8) |
| D-91 | Dokter pada templat tidak dapat diganti | Saat ubah, `doctor_id` yang berbeda ditolak: "Dokter pada templat tidak dapat diganti. Buat templat baru, lalu nonaktifkan templat lama." Propagasi 00 F.4 tidak mengenal perpindahan dokter; cabang pelepasan sesi saat poliklinik berubah tetap dipakai. | 12 V-23 (C2) |
| D-92 | `NoOverlappingSession` tidak dibuat | Benturan jam sesi non-batal dan pengecualian pada tanggal sesi diperiksa di `PracticeSessionRequest::after()` dengan pesan 12 V-24; hanya `NoOverlappingSchedule` yang berupa kelas aturan. | 12 §3.1, V-24, BR-29 (C3) |
| D-93 | Field alasan status sesi | Field form bernama `reason` (wajib bila `cancelled`, min 5, maks 255; pesan "Alasan minimal 5 karakter."), kolom DB tetap `status_reason`. | 12 V-24, 11 §9.1 (C4) |
| D-94 | Label pengecualian global | Kolom dan filter pengecualian untuk `doctor_id` NULL bertuliskan "Semua dokter" (bukan "Seluruh RS"); label tab modul mengikuti D-76. | 05 hal. 35, 07 W-17 (C14) |
| D-95 | `setSessionStatus` pada sesi `needs_review` dan `cancelled` | Sesi `needs_review` ditolak ("Sesi ini sedang menunggu penyelesaian di layar Sesi terdampak."; diselesaikan di fase 7) dan sesi `cancelled` hanya dapat dibuka kembali ("Sesi yang dibatalkan hanya dapat dibuka kembali."); `needs_review` sebagai status tujuan hanya ditetapkan sistem; penulisan "Sesi terdampak" memakai huruf kecil t (12 BR-35). | 11 §6.6 (B12) |
| D-96 | Keterbatasan: templat dikembalikan | Sesi `needs_review` sumber `template_time` yang jam templatnya kemudian dikembalikan sama dengan jam sesi tetap menunggu tinjauan petugas; tidak dibuka otomatis. Diterima sebagai keterbatasan yang diketahui fase 3. | 11 §6.6 (B-E butir 1) |
| D-97 | Keterbatasan: templat diaktifkan kembali | Sesi `cancelled` berstatus "Jadwal berubah" tidak dibuka otomatis saat templat diaktifkan kembali; dibuka manual lewat `setSessionStatus(open)`, dan UNIQUE (`doctor_schedule_id`, `session_date`) mencegah pembangkitan ulang pada tanggal itu. Diterima sebagai keterbatasan yang diketahui fase 3. | 11 §6.6, 09 §3.7 (B-E butir 2) |
| D-98 | Keterbatasan: templat dokter arsip | `archive()` menonaktifkan seluruh templat dokter (BR-06) dan `DoctorScheduleRequest` menolak `doctor_id` arsip juga saat ubah, sehingga templat dokter arsip hanya dapat dibaca atau dihapus; setelah dokter dipulihkan (status `inactive`) templat dapat diubah lagi. Diterima sebagai keterbatasan yang diketahui fase 3. | 12 BR-06, V-23 (pemeriksaan konsistensi 1) |
| D-99 | `applyException` dan pembangkitan sesi | `applyException()` memanggil `ensureRange()` tanpa `$only`; sesi yang baru dibangkitkan tidak dihitung pada laporan, sehingga laporan pengecualian hanya memuat sesi dibatalkan, perlu ditindaklanjuti, dan dibuka kembali. | 11 §6.6 (pemeriksaan konsistensi 1) |
| D-100 | Status sesi yang sama | `setSessionStatus()` ke status yang sama bukan operasi (tanpa penulisan dan audit), tetapi flash controller tetap "Sesi dibuka kembali."; UI hanya menampilkan tombol yang mengubah status, sehingga kasus ini hanya terjadi lewat permintaan buatan. | 11 §6.6, 12 V-24 (pemeriksaan konsistensi 1) |
| D-101 | Form Request publik berdiri sendiri | `Public\DoctorIndexRequest` dan `Public\ScheduleIndexRequest` memperluas `FormRequest` langsung, bukan `ListFilterRequest`: aturan `status`/`urut`/`kembali`/`per_page` tidak relevan di halaman publik dan flash `warning` "Filter tidak valid dan diabaikan." menggandakan pesan inline. Perilakunya sama (tidak melempar, parameter tidak valid diabaikan); hanya `tanggal` dan `minggu` yang diberi pesan inline. Jendela `tanggal` inklusif (hari ini s.d. hari ini + `booking.window_days`) dengan `date_format:Y-m-d` ketat; galat inline dibagikan lewat `view()->share('errors', …)`. | 11 §9.1 (catatan penulis fase 3, D.1) |
| D-102 | Cadangan hari ini seeder | Sesi cadangan hari ini dibuat hanya bila sesi `open` yang belum berakhir kurang dari 3; cadangan yang sudah berakhir pada jam seeding atau bentrok dengan sesi dokter itu dilewati. | 13 §7.3 (catatan penulis fase 3, D.8) |
| D-103 | Flash ubah templat dan ubah sesi | Ubah templat memasang satu flash `success` (pesan baku + `ScheduleChangeReport::message()`, yang memuat "N sesi perlu ditindaklanjuti petugas pendaftaran" bila ada `needsReview`); tidak ada flash `warning` bertautan, karena peringatan tetap dan tautan ke `admin.sessions.review` ada di banner `_review-banner`. Ubah jam atau batalkan sesi ber-appointment aktif memasang flash `warning` dengan pesan 12 BR-35. | 11 §4 `DoctorScheduleController`, `PracticeSessionController` (pemeriksaan konsistensi 2) |
| D-104 | `Page::forKey()` tanpa 404 | Mengembalikan baris `pages`; bila belum ada, instance tak tersimpan berisi `Page::DEFAULTS[$key]` (`body` NULL); key di luar `Page::KEYS` melempar `InvalidArgumentException`. Menyimpang dari `firstOrFail` karena tes #4/#6/#8 dan matriks AKS berjalan di DB kosong dan EMP-01 mewajibkan 200; `PageSeeder` selalu membuat barisnya. | 11 §5.6 (F4A-R4) |
| D-105 | Fallback SEO | `Page`: `seo_title` = `meta_title` → `title`, `seo_description` = `meta_description` → `subtitle` → `seo.default_meta_description` (layout). `Article`: `meta_title` → `title`, `meta_description` → `excerpt`. Beranda: judul utuh `meta_title` atau "{site.name} — {site.tagline}"; `meta_description` → `seo.default_meta_description` (tanpa `subtitle`). #18 dengan kategori menambah " — {kategori}", halaman ≥ 2 menambah " — Halaman {n}". | 05 §7, 11 §5.6 (F4A-R5) |
| D-106 | Tanpa cache `pages:{key}` | Satu kueri per halaman; hanya `settings:all` yang di-cache. Cache `pages:{key}` di 06 adalah [ASUMSI] yang tidak diadopsi. | 06 §12, 03 §7 (F4A-R6) |
| D-107 | Isi artikel kosong setelah sanitasi | `articles.body` NOT NULL bertabrakan dengan D-58; `ArticleRequest` menolak isi yang kosong secara visual setelah `HtmlSanitizer::clean()` dengan "Isi artikel wajib diisi." lewat `SanitizesRichText::isBlankRichText()` (publik). | D-58, 12 V-32 (F4A-R11) |
| D-108 | `Public\ArticleIndexRequest` berdiri sendiri | Memperluas `FormRequest` (pola D-101), tanpa flash `warning`; `kategori` (slug yang ada) dan `q` (≤ 100); nilai tidak valid diabaikan; `per_page` tidak dibuka di publik. | 11 §9.1, 12 V-46 (F4A-R12) |
| D-109 | Paginasi konten | #18 sembilan per halaman, #20 dua belas album per halaman, #21 tanpa paginasi; daftar admin konten memakai `per_page` bawaan 25 (D-38); daftar admin Halaman menampilkan 15 baris tetap tanpa paginasi dan tanpa filter (urut `Page::KEYS`). | 11 §2.3, 05 §4.1 (F4A-R13) |
| D-110 | Filter admin konten | `BannerIndexRequest`, `ArticleIndexRequest`, `GalleryIndexRequest`, `FaqIndexRequest` memperluas `ListFilterRequest` dengan `applyTo(Builder)`; kategori artikel memakai `ListFilterRequest` dasar dan mitra asuransi memakai `MasterDataIndexRequest`. | 11 §9.3, 12 V-46 (F4A-R14) |
| D-111 | Tombol menuju route fase lain | Tombol di view memakai `Route::has()` ("Daftar Kunjungan", "Daftar Akun", "Masuk", "Kontak"/"Kirim Pesan", "Lihat detail kamar"). Tombol banner dari database tampil bila `Banner::hasUsableButton()` (label dan URL terisi; URL `https://` atau path internal yang cocok dengan route lewat `App\Support\InternalLink`); banner seed #2 tanpa tombol sampai fase 6. | 05 §4.1, 13 §8.3 (F4A-R15) |
| D-112 | Layanan dan dokter unggulan beranda | Enam poliklinik `active()` urut `ordered()` tanpa saringan tipe; empat dokter pertama `DoctorAvailabilityQuery::doctors()` (urut `sort_order` dokter, tanpa kolom unggulan) beserta sesi terdekat. Beranda menghapus parameter `page` dari query sebelum memanggil `doctors()` agar `/?page=2` tidak menggeser dokter unggulan. Di mobile, kartu dokter bertumpuk satu kolom, bukan baris geser scroll-snap (disederhanakan dari W-02). | 05 hal. 1, 07 W-02 (F4A-R16, F4A-R35) |
| D-113 | Hero beranda | `<h1>` selalu `pages.home.title`, subjudul `pages.home.subtitle` → `site.tagline`; tanpa banner aktif hero statis; carousel berputar tiap 6000 ms kecuali `prefers-reduced-motion: reduce`, dengan tombol Jeda/Putar (`aria-pressed`) dan `aria-live` `off`/`polite`; judul slide `<p class="h2">`; tiga tombol utama tepat di bawah `<h1>` dan subjudul, sebelum carousel (menyimpang dari urutan W-02 mobile agar terlihat tanpa gulir di 360 px, 15 RSP-01). | 07 W-02, 15 §7.1 (F4A-R17, F4A-R34) |
| D-114 | Satu partial isi halaman | `{!! !!}` untuk `pages.body` hanya di `public/partials/_page-body.blade.php` dan untuk `articles.body` hanya di `public/articles/show.blade.php`; `RawOutputScanTest` menyisakan tiga baris (clinics, partial halaman, artikel). | 15 SEK-15 (F4A-R18) |
| D-115 | Komponen `x-open-graph` | Props `title`, `description`, `image`, `type`, `canonical`; merender meta `og:*` dan canonical, dipakai lewat `@push('head')` pada halaman 4a; halaman fase 2–3 tidak direfaktor. | 05 §7, 10 §3 (F4A-R19) |
| D-116 | Prop `fullTitle` layout publik | Bawaan `false`; `true` ⇒ `<title>` berisi judul utuh tanpa sufiks nama situs (Beranda). | 10 §3.1 (F4A-R20) |
| D-117 | Mitra asuransi | `InsurancePartner::TYPES`/`TYPE_LABELS`; `deletionBlockedReason()` memakai teks BR-180 "Mitra asuransi masih dipakai oleh {n} data. Nonaktifkan saja."; tombol Hapus selalu tampil dan penolakan lewat flash `error` di controller (juga bagi Super Admin); #16 menampilkan mitra aktif berlencana "Contoh". | 12 BR-180, D-44 (F4A-R21) |
| D-118 | FAQ | `Faq::CATEGORIES` (`pendaftaran`, `bpjs`, `layanan`, `umum`) dan `CATEGORY_LABELS`; tanpa enum baru (`EnumCatalogTest` mengunci 15); "urutkan" = field `sort_order`, tanpa route reorder. | 05 §5.2, 11 §4.4 (F4A-R22) |
| D-119 | Galeri | Album dibuat tanpa foto lalu `store` mengalihkan ke `edit` agar foto langsung diunggah (menyimpang dari D-47); unggah lewat `admin.galleries.photos.store` (5 slot, maks 10 berkas per kirim, total `Gallery::MAX_PHOTOS` = 100); ubah alt/urutan/hapus lewat `admin.media.*`; sampul = foto pertama atau `gallery.svg`; detail memakai ulang modal + carousel klinik. | 11 §4.4, D-47 (F4A-R23) |
| D-120 | Label periode banner | `Banner::scheduleLabel()` menghitung "Tayang", "Terjadwal", "Berakhir", atau "Nonaktif" dengan waktu aplikasi; `currentlyActive()` inklusif pada `starts_at` dan `ends_at`, urut `sort_order`, `id`. | 12 BR-124 (F4A-R25) |
| D-121 | Seeder demo konten | `ContentSeeder` hanya `local`/`testing` (melempar di luar itu), dipanggil `DemoSeeder` setelah `ScheduleExceptionSeeder`; data persis 13 §8.3–§8.7 dengan slug eksplisit; 25 gambar lokal sehingga media demo menjadi 57; idempoten per slug atau judul. | 13 §2.1, §8, §11.2 (F4A-R27) |
| D-122 | Route admin konten | 40 route baru di bawah `admin/konten` (halaman 3, banner 6, artikel 6, kategori 6, galeri 7, FAQ 6, mitra 6), seluruhnya `permission:content.manage`; total route admin menjadi 90; tanpa `admin.pages.create|store|destroy`. | 11 §2.3 (F4A-R28) |
| D-123 | Media dipertahankan pada soft delete | Hook `deleting` trait `HasMedia` menghapus media hanya bila model tidak memakai `SoftDeletes` atau sedang `forceDelete()`; cover artikel yang terhapus lunak tetap ada. | 09 §6.3 (F4A-R32) |

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
