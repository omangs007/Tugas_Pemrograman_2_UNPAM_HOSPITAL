# 15 — Skenario Pengujian dan Kriteria Penerimaan

**Tujuan bagian.** Menetapkan strategi pengujian, daftar kasus uji (otomatis dan manual) yang membuktikan seluruh butir §16 kebutuhan pemilik beserta tambahan keamanan dan operasional, rancangan tes konkurensi, daftar periksa manual (responsif, aksesibilitas, offline, backup–restore), dan kriteria penerimaan (Definition of Done).

Bagian terkait: [00 — Keputusan inti](00-keputusan-inti.md) · [04 — Peran & hak akses](04-peran-hak-akses.md) · [05 — Sitemap & matriks halaman](05-sitemap-matriks-halaman.md) · [06 — Alur](06-alur.md) · [12 — Aturan bisnis, validasi, keamanan](12-aturan-bisnis-validasi-keamanan.md) · [13 — Data contoh](13-data-contoh.md) · [14 — Tahapan implementasi](14-tahapan-implementasi.md) · [16 — Instalasi & operasional](16-instalasi-operasional.md).

---

## 1. Strategi Pengujian

### 1.1 Alat dan basis data

| Aspek | Keputusan |
|---|---|
| Kerangka | **PHPUnit 11** (bawaan skeleton Laravel 12), suite `Feature` dan `Unit`; `php artisan test`. |
| Basis data tes | **MariaDB** database terpisah `unpam_hospital_test` (user `unpam_app`), **bukan SQLite**. Alasan: `lockForUpdate()` (kunci baris InnoDB), kolom generated `appointments.active_flag` (`storedAs`), constraint `CHECK`, perilaku `UNIQUE` dengan nilai `NULL`, kolom JSON, dan kolom terenkripsi harus diuji pada mesin yang sama dengan runtime. |
| Lingkungan | `phpunit.xml` memaksa `APP_ENV=testing`, `DB_CONNECTION=mariadb`, `DB_DATABASE=unpam_hospital_test`, `CACHE_STORE=array`, `SESSION_DRIVER=array`, `QUEUE_CONNECTION=sync`, `MAIL_MAILER=array`, `BCRYPT_ROUNDS=4`. Tes konkurensi memakai `CACHE_STORE=file` agar proses anak berbagi penyimpanan. |
| Isolasi | `RefreshDatabase` (transaksi per tes) untuk hampir semua tes. **`DatabaseTruncation`** (atau `DatabaseMigrations`) untuk tes konkurensi multi-koneksi karena data harus ter-*commit* agar terlihat oleh koneksi/proses lain. |
| Data | Factory ([13 §12](13-data-contoh.md#12-factory-yang-dibutuhkan)); `RolePermissionSeeder` dan `SettingsSeeder` dipanggil di `TestCase::setUp()`; `DemoSeeder` hanya pada `SEED-*`. |
| Waktu | `Carbon::setTestNow()` / `$this->travelTo()` untuk batas cut-off, check-in, tanggal sesi. |
| Berkas | `Storage::fake('public')` dan `Storage::fake('local')`; gambar dibuat `UploadedFile::fake()->image()`. |
| Surat/notifikasi | `Mail::fake()`, `Notification::fake()`; uji `MAIL_MAILER=log` secara manual (NTF-04). |
| Pengujian manual | Terdokumentasi pada §7–§9 dengan templat bukti (tanggal, penguji, lingkungan, hasil, lokasi tangkapan layar). |

### 1.2 Struktur folder tes (usulan)

```
tests/
  Feature/
    Auth/  Public/  Patient/  Admin/  Booking/  Scheduling/  Security/    (struktur dari 10)
    Access/        (baru: matriks akses halaman × peran, grup AKS dan KNS)
    Seeding/       (baru: DemoSeederInvariantsTest, SeederProductionGuardTest)
  Unit/
    Enums/  Services/  Models/                                             (struktur dari 10)
  Concurrency/     (baru: KON-*; DatabaseTruncation + proses paralel)
  Support/
    Concurrency/book-session.php   (baru: skrip pembantu proses paralel)
  TestCase.php
```

Tes unggahan dan validasi media berada di `Feature/Security` (aturan unggah) dan `Unit/Services` (`MediaService`); tes CSV di `Feature/Admin`.

### 1.3 Perintah (PowerShell)

> Folder XAMPP di mesin pemilik adalah **`C:\xampp3`** (folder `C:\xampp` lama hanya sisa dan tidak dipakai). Di mesin lain sesuaikan path (mis. `C:\xampp`). Panduan [16](16-instalasi-operasional.md) mendefinisikan `$XAMPP` dan `$PHP` sekali per sesi; perintah di bawah memakai path penuh agar dapat disalin langsung.

```powershell
# seluruh suite kecuali konkurensi (cepat)
C:\xampp3\php\php.exe artisan test --exclude-group=concurrency
# hanya konkurensi (lebih lambat, butuh MariaDB berjalan)
C:\xampp3\php\php.exe artisan test --group=concurrency
# satu area
C:\xampp3\php\php.exe artisan test --filter=BookingServiceTest
# format kode
C:\xampp3\php\php.exe vendor\bin\pint --test
```

---

## 2. Konvensi Kasus Uji

- **ID:** `GRUP-NN` (grup di bawah). **Jenis:** `otomatis` (PHPUnit) atau `manual` (dilakukan penguji; bukti dicatat).
- **Kolom "Kebutuhan §16":** `K1`–`K16` = butir §16 kebutuhan pemilik (daftar di §2.1). `Tambahan (§N)` = butir turunan dari bagian N kebutuhan asli atau keputusan 00 yang ditambahkan.
- Rujukan role: **Tamu** (belum login), **Pasien**, **Admin Konten** (`content-admin`), **Petugas** (`registration-officer`), **Super Admin** (`super-admin`).

### 2.1 Butir §16 dan grup kasus yang membuktikannya

| Kode | Butir §16 | Grup/kasus utama |
|---|---|---|
| K1 | Semua halaman utama dapat diakses sesuai hak pengguna | `AKS-01`–`AKS-11` (matriks §3) |
| K2 | CRUD admin tersimpan dan muncul di publik | `CRD-01`–`CRD-13` |
| K3 | Foto dokter dapat diunggah dan diganti | `MED-01`, `MED-02` |
| K4 | Jadwal libur tidak dapat dipesan | `JDW-03`, `JDW-04`, `BKG-05` |
| K5 | Pasien hanya melihat/mengubah data miliknya | `PAS-01`–`PAS-10` |
| K6 | URL admin ditolak untuk pasien | `AKS-06` |
| K7 | Password tersimpan dalam bentuk hash | `SEK-01` |
| K8 | Dua permintaan bersamaan tidak melampaui kuota | `KON-01`–`KON-05` |
| K9 | Booking ganda ditolak | `BKG-02`, `KON-03` |
| K10 | Perubahan status dan pembatalan memengaruhi kuota dengan benar | `BKG-10`–`BKG-13`, `KON-04` |
| K11 | Nomor antrean unik | `BKG-17`, `BKG-10`, `KON-02` |
| K12 | Form memberi pesan validasi jelas | `VAL-01`–`VAL-08` |
| K13 | Responsif mobile, tablet, desktop | `RSP-01`–`RSP-06` |
| K14 | Data kosong dan gambar hilang tertangani | `EMP-01`–`EMP-05`, `MED-08` |
| K15 | Fitur inti tanpa internet | `OFF-01`–`OFF-06` |
| K16 | Backup dapat dipulihkan | `BAK-01`–`BAK-05` |

### 2.2 Daftar grup

| Grup | Isi | Jenis dominan |
|---|---|---|
| `AKS` | Akses halaman per peran, redirect, akun nonaktif | otomatis |
| `KNS` | Konsistensi route–izin–tabel–halaman | otomatis |
| `CRD` | CRUD admin dan pengaruhnya di publik | otomatis |
| `MED` | Media: unggah, ganti, validasi, prune, gambar hilang | otomatis |
| `JDW` | Templat, sesi, pengecualian, sesi terdampak | otomatis |
| `PAS` | Kepemilikan data pasien | otomatis |
| `SEK` | Keamanan | otomatis |
| `BKG` | Aturan booking, status, kuota, antrean | otomatis |
| `KON` | Konkurensi | otomatis (grup `concurrency`) |
| `WLK` | Walk-in dan hubungkan akun | otomatis |
| `VAL` | Pesan validasi | otomatis |
| `NTF` | Notifikasi dan surat | otomatis + manual |
| `EMP` | Data kosong, loading, gagal | otomatis + manual |
| `DSH` | Dashboard dan ekspor CSV | otomatis |
| `AUD` / `STF` / `SET` | Audit log / staf dan hak akses / pengaturan | otomatis |
| `ERR` | Halaman galat | otomatis + manual |
| `SEED` | Seeder dan invarian | otomatis |
| `RSP` / `A11` / `OFF` / `BAK` / `INS` | Responsif / aksesibilitas / offline / backup / instalasi | manual |

---

## 3. Matriks Akses 40 Halaman × Peran (`AKS-01`–`AKS-05`)

Satu tes berbasis *data provider* mengakses setiap baris dengan lima aktor dan memeriksa hasil. Legenda: **200** = halaman tampil; **→L** = redirect ke login yang sesuai (`/masuk` untuk area pasien, `/admin/login` untuk area admin); **→D** = redirect ke dashboard sesuai tipe akun (middleware `guest`); **403** = ditolak (`account:*` atau `permission:*`). Seluruh akun aktif; akun nonaktif diuji di `AKS-08`.

### 3.1 Website publik (#1–#22) — semua aktor 200

| # | Halaman | Route | Tamu | Pasien | Admin Konten | Petugas | Super Admin |
|---|---|---|:-:|:-:|:-:|:-:|:-:|
| 1 | Beranda | `home` | 200 | 200 | 200 | 200 | 200 |
| 2 | Profil | `about` | 200 | 200 | 200 | 200 | 200 |
| 3 | Visi & misi | `vision-mission` | 200 | 200 | 200 | 200 | 200 |
| 4 | Daftar layanan | `clinics.index` | 200 | 200 | 200 | 200 | 200 |
| 5 | Detail layanan | `clinics.show` | 200 | 200 | 200 | 200 | 200 |
| 6 | Daftar dokter | `doctors.index` | 200 | 200 | 200 | 200 | 200 |
| 7 | Detail dokter | `doctors.show` | 200 | 200 | 200 | 200 | 200 |
| 8 | Jadwal dokter | `schedules.index` | 200 | 200 | 200 | 200 | 200 |
| 9 | Daftar fasilitas | `facilities.index` | 200 | 200 | 200 | 200 | 200 |
| 10 | Detail fasilitas | `facilities.show` | 200 | 200 | 200 | 200 | 200 |
| 11 | Ringkasan kamar | `rooms.index` | 200 | 200 | 200 | 200 | 200 |
| 12 | Kamar BPJS Kelas 1 | `rooms.show` (`bpjs-kelas-1`) | 200 | 200 | 200 | 200 | 200 |
| 13 | Kamar BPJS Kelas 2 | `rooms.show` (`bpjs-kelas-2`) | 200 | 200 | 200 | 200 | 200 |
| 14 | Kamar BPJS Kelas 3 | `rooms.show` (`bpjs-kelas-3`) | 200 | 200 | 200 | 200 | 200 |
| 15 | Kamar VIP | `rooms.show` (`vip`) | 200 | 200 | 200 | 200 | 200 |
| 16 | Info BPJS & asuransi | `insurance` | 200 | 200 | 200 | 200 | 200 |
| 17 | Panduan pendaftaran | `registration-guide` | 200 | 200 | 200 | 200 | 200 |
| 18 | Daftar artikel | `articles.index` | 200 | 200 | 200 | 200 | 200 |
| 19 | Detail artikel | `articles.show` | 200 | 200 | 200 | 200 | 200 |
| 20 | Galeri | `galleries.index` | 200 | 200 | 200 | 200 | 200 |
| 21 | FAQ | `faq` | 200 | 200 | 200 | 200 | 200 |
| 22 | Kontak & lokasi | `contact` | 200 | 200 | 200 | 200 | 200 |

### 3.2 Autentikasi dan portal pasien (#23–#30)

| # | Halaman | Route | Tamu | Pasien | Admin Konten | Petugas | Super Admin |
|---|---|---|:-:|:-:|:-:|:-:|:-:|
| 23 | Registrasi | `register` | 200 | →D | →D | →D | →D |
| 24 | Login pasien | `login` | 200 | →D | →D | →D | →D |
| 25 | Permintaan reset | `password.request` | 200 | →D | →D | →D | →D |
| 26 | Form reset | `password.reset` | 200 | →D | →D | →D | →D |
| 27 | Dashboard pasien | `patient.dashboard` | →L | 200 | 403 | 403 | 403 |
| 28 | Profil & akun | `patient.profile.edit` | →L | 200 | 403 | 403 | 403 |
| 29 | Pendaftaran kunjungan | `patient.appointments.create` | →L | 200 | 403 | 403 | 403 |
| 30 | Riwayat pendaftaran | `patient.appointments.index` | →L | 200 | 403 | 403 | 403 |

### 3.3 Dashboard admin (#31–#40) — baris tambahan (`b`, `c`, `d`) menguji izin berbeda pada halaman yang sama

| # | Halaman | Route | Izin | Tamu | Pasien | Admin Konten | Petugas | Super Admin |
|---|---|---|---|:-:|:-:|:-:|:-:|:-:|
| 31 | Login admin | `admin.login` | guest | 200 | →D | →D | →D | →D |
| 31b | Ganti password staf | `admin.password.edit` | auth staf | →L | 403 | 200 | 200 | 200 |
| 32 | Dashboard | `admin.dashboard` | `dashboard.view` | →L | 403 | 200 | 200 | 200 |
| 33 | Dokter (daftar/detail) | `admin.doctors.index` | `doctors.view` | →L | 403 | 200 | 200 | 200 |
| 33b | Dokter (tambah/ubah) | `admin.doctors.create` | `doctors.manage` | →L | 403 | 200 | 403 | 200 |
| 34 | Spesialisasi & poliklinik | `admin.clinics.index`, `admin.specialties.index` | `clinics.manage` | →L | 403 | 200 | 403 | 200 |
| 35 | Jadwal (templat/sesi/pengecualian) | `admin.schedules.index` | `schedules.view` | →L | 403 | 200 | 200 | 200 |
| 35b | Jadwal (tambah/ubah) | `admin.schedules.create` | `schedules.manage` | →L | 403 | 200 | 403 | 200 |
| 35c | Sesi terdampak | `admin.sessions.review` | `sessions.review` | →L | 403 | 403 | 200 | 200 |
| 35d | Hapus sesi tambahan manual (DELETE) | `admin.sessions.destroy` | `schedules.manage` | →L | 403 | 200 | 403 | 200 |
| 36 | Fasilitas | `admin.facilities.index` | `facilities.manage` | →L | 403 | 200 | 403 | 200 |
| 36b | Kategori kamar (daftar) | `admin.room-categories.index` | `facilities.manage` atau `rooms.update-availability` (00 I.4, A-43) | →L | 403 | 200 | 200 | 200 |
| 36c | Perbarui ketersediaan kamar (PATCH) | `admin.room-categories.availability` | `rooms.update-availability` | →L | 403 | 200 | 200 | 200 |
| 37 | Pasien | `admin.patients.index` | `patients.view` | →L | 403 | 403 | 200 | 200 |
| 38 | Pendaftaran & antrean | `admin.appointments.index`, `admin.queue.index` | `appointments.view` | →L | 403 | 403 | 200 | 200 |
| 38b | Ekspor CSV | `admin.appointments.export` | `reports.export` | →L | 403 | 403 | 200 | 200 |
| 39 | Konten (halaman, banner, artikel, galeri, FAQ, asuransi) | `admin.pages.index` dan setara | `content.manage` | →L | 403 | 200 | 403 | 200 |
| 39b | Kotak pesan kontak | `admin.messages.index` | `messages.manage` | →L | 403 | 403 | 200 | 200 |
| 40 | Pengaturan website | `admin.settings.edit` | `settings.manage` | →L | 403 | 403 | 403 | 200 |
| 40b | Akun staf | `admin.staff.index` | `staff.manage` | →L | 403 | 403 | 403 | 200 |
| 40c | Hak akses peran | `admin.roles.index` | `staff.manage` | →L | 403 | 403 | 403 | 200 |
| 40d | Audit log | `admin.audit-logs.index` | `audit.view` | →L | 403 | 403 | 403 | 200 |

Pemetaan kasus: `AKS-01` = tamu, `AKS-02` = pasien, `AKS-03` = Super Admin, `AKS-04` = Admin Konten, `AKS-05` = Petugas. Catatan: untuk halaman yang sama, penolakan **403** berlaku juga pada metode non-GET (POST/PUT/PATCH/DELETE) — tiap rute mutasi diuji dengan aktor tanpa izin.

---

## 4. Kasus Uji Otomatis dan Campuran

Format tabel: `ID | Skenario | Langkah | Data | Hasil yang diharapkan | Jenis | Kebutuhan §16 terkait`.

### 4.1 Akses dan konsistensi (`AKS`, `KNS`)

| ID | Skenario | Langkah | Data | Hasil yang diharapkan | Jenis | Kebutuhan §16 |
|---|---|---|---|---|---|---|
| AKS-01 | Matriks akses — Tamu | Akses setiap baris §3 sebagai Tamu | Data provider §3 | Hasil sesuai kolom Tamu (publik 200, area pasien →L `/masuk`, area admin →L `/admin/login`) | otomatis | K1 |
| AKS-02 | Matriks akses — Pasien | Idem sebagai Pasien aktif | Pasien factory | Publik 200; portal pasien 200; halaman admin 403; halaman guest-only →D | otomatis | K1 |
| AKS-03 | Matriks akses — Super Admin | Idem sebagai Super Admin | Staf `super-admin` | Semua halaman admin 200; area pasien 403 | otomatis | K1 |
| AKS-04 | Matriks akses — Admin Konten | Idem sebagai `content-admin`; ulangi untuk metode mutasi | Staf `content-admin` | Sesuai kolom Admin Konten; halaman `patients.*`, `appointments.*`, `admin.queue.*` 403 | otomatis | K1 |
| AKS-05 | Matriks akses — Petugas | Idem sebagai `registration-officer` | Staf petugas | Sesuai kolom Petugas; `doctors.manage`, `clinics.manage`, `content.manage`, `settings.manage`, `staff.manage`, `audit.view` 403 | otomatis | K1 |
| AKS-06 | URL admin ditolak untuk pasien | Login pasien; GET `/admin`, `/admin/dokter`, `/admin/pasien`, `/admin/pengaturan`; POST `/admin/dokter` | Pasien aktif | Semua **403**; tidak ada konten admin pada respons | otomatis | K6 |
| AKS-07 | Tamu ke area terlindungi + *intended URL* | GET `/pasien/pendaftaran/baru?sesi=ID` sebagai tamu, login, ikuti redirect | Sesi bookable | Redirect ke `/masuk`; setelah login kembali ke URL semula dengan `sesi` utuh | otomatis | K1 |
| AKS-08 | Akun nonaktif | Set `is_active=false` untuk pasien dan staf; coba login; dan nonaktifkan saat sesi berjalan | Pasien + staf | Login ditolak dengan pesan generik; sesi berjalan diputus oleh middleware `active` (logout + pesan) | otomatis | K1 (tambahan §12) |
| AKS-09 | Staf wajib ganti password | Login staf `must_change_password=true`; GET `/admin`, `/admin/dokter`; lalu GET `admin.password.edit`, PUT `admin.password.update`, POST `admin.logout` | Staf tipe ini | Rute lain redirect ke `admin.password.edit` sampai password diganti; `admin.password.edit`/`update` dan `admin.logout` **tidak** diredirect (tanpa loop, 00 I.4); setelah ganti flag menjadi false | otomatis | Tambahan (H) |
| AKS-10 | Pemisahan kanal login | Pasien login di `/admin/login`; staf login di `/masuk` | Kredensial benar | Gagal dengan pesan generik "Email atau password salah"; tidak ada sesi terbentuk | otomatis | Tambahan (H) |
| AKS-11 | `Gate::before` dan peran terkunci | Super Admin akses rute berizin apa pun; coba ubah izin peran `super-admin` dan hapus peran | Super Admin | Akses 200; perubahan peran `super-admin` ditolak | otomatis | Tambahan (G) |
| KNS-01 | Route kanonik lengkap | Bandingkan `route:list` dengan seluruh nama route 00 I | Daftar nama route 00 | Tidak ada nama hilang atau berlebih; hanya `admin.doctors.show`, `admin.patients.show`, `admin.appointments.show`, `admin.messages.show`, `admin.audit-logs.show` yang memiliki `show` (resource lain `except('show')`, 00 I.6); route `*.destroy` hanya untuk entitas pada tabel 00 C (`admin.sessions.destroy` termasuk, 00 I.4) — tidak ada `destroy` untuk dokter, pasien, pendaftaran, halaman, pesan, staf, audit | otomatis | Tambahan (konsistensi) |
| KNS-02 | Setiap route admin berizin | Periksa middleware setiap route `admin.*` | `route:list --json` | Semua (kecuali login/logout/ganti-password/dashboard) memiliki `permission:*`; semua mutasi memiliki Policy atau Form Request `authorize()` | otomatis | K1, K6 |
| KNS-03 | Setiap izin dipakai | Untuk 19 izin 00 G cari route/Policy yang memakainya | Daftar izin | Tidak ada izin yatim; tidak ada route memakai izin tak terdaftar | otomatis | Tambahan |
| KNS-04 | Tabel ↔ migration ↔ model | Cocokkan tabel 00 E dengan migration dan model | Daftar tabel | Setiap tabel kanonik ada migration + model/relasi; kolom wajib ada (`Schema::hasColumns`) | otomatis | Tambahan |
| KNS-05 | Halaman ↔ tes akses | Cocokkan matriks 05 dengan data provider §3 | Matriks 05 | Setiap halaman memiliki baris akses | otomatis | K1 |

### 4.2 CRUD admin tampil di publik (`CRD`)

| ID | Skenario | Langkah | Data | Hasil yang diharapkan | Jenis | Kebutuhan §16 |
|---|---|---|---|---|---|---|
| CRD-01 | Spesialisasi | Admin Konten tambah, ubah, nonaktifkan spesialisasi | Spesialisasi "Contoh Baru" | Tersimpan; muncul pada filter `doctors.index` (fase 2: pada pilihan spesialisasi admin dan scope `Specialty::active()`; asersi `doctors.index` publik ditambah di fase 3, F2-R28); nonaktif menghilang dari filter | otomatis | K2 |
| CRD-02 | Poliklinik | Tambah poliklinik (`code` unik), ubah, set `is_bookable=false` | `code=XYZ` | Tampil di `/layanan` dan `/layanan/{slug}`; `is_bookable=false` → tanpa tombol daftar dan tidak ada sesi yang dapat dipesan; `code` ganda ditolak | otomatis | K2 |
| CRD-03 | Dokter | Tambah dokter; nonaktifkan; arsipkan (tanpa dan dengan appointment aktif mendatang); buka URL langsung | Dokter baru | Tampil di `/dokter` dan `/dokter/{slug}` (fase 2: scope `Doctor::publiclyVisible()` dan tampilan #5; asersi URL dokter ditambah di fase 3, F2-R28); nonaktif/arsip hilang dari publik dan wizard dan URL langsungnya **404** (juga untuk staf, A-42); arsip **ditolak** bila ada appointment aktif mendatang (pesan: selesaikan sesi terdampak lebih dulu); riwayat booking lama tetap utuh | otomatis | K2 |
| CRD-04 | Nama tampilan dokter | Isi `title_prefix`, `name`, `title_suffix` | `dr.`, `Laras Wulandari`, `Sp.A` | Kartu dan detail menampilkan "dr. Laras Wulandari, Sp.A"; tanpa sufiks tidak ada koma menggantung | otomatis | K2 |
| CRD-05 | Jadwal | Tambah templat; nonaktifkan; tambah pengecualian | Templat Sen 09:00–12:00 kuota 15 | Sesi terbentuk; tampil di `/jadwal-dokter`, `/dokter/{slug}`, dan wizard (sumber sama; wizard di fase 6); nonaktif/cuti hilang atau berlabel | otomatis | K2 |
| CRD-06 | Fasilitas | CRUD fasilitas | Fasilitas "Contoh" | Tampil di `/fasilitas` dan detail; nonaktif hilang | otomatis | K2 |
| CRD-07 | Kategori kamar | Ubah `bed_capacity`, `beds_available`, `sample_rate`; nonaktifkan | VIP 6/2 → 6/1 | Tampil di `/kamar-rawat-inap` dengan "Diperbarui manual pada {waktu}" (bukan "real-time"); `beds_available > bed_capacity` ditolak; nonaktif hilang | otomatis | K2 |
| CRD-08 | Artikel | Buat draf; terbitkan; hapus (soft delete) | Artikel + kategori | Draf tidak tampil di `/artikel` dan URL langsungnya 404 (juga untuk staf, A-42); terbit tampil dan terurut `published_at`; terhapus hilang | otomatis | K2 |
| CRD-09 | Banner dan galeri | Buat banner (jadwal `starts_at`/`ends_at`); buat album + foto | Banner aktif/kedaluwarsa | Hanya banner aktif tampil di beranda; album terbit tampil di `/galeri` | otomatis | K2 |
| CRD-10 | FAQ dan mitra asuransi | CRUD FAQ (kelompok) dan mitra | FAQ terbit/draf | Tampil di `/faq` dan `/bpjs-asuransi`; tidak terbit tidak tampil | otomatis | K2 |
| CRD-11 | Halaman statis dan meta | Ubah `about.body`, `meta_title`, `meta_description` | Isi dengan `<script>` | Isi tampil (tersanitasi); `<title>` dan meta description sesuai data | otomatis | K2 |
| CRD-12 | Pengaturan situs | Ubah alamat, telepon, WhatsApp, email, tautan peta | `contact.*`, `map.*` | Nilai baru tampil di `/kontak` dan footer tanpa `cache:clear`; tautan `wa.me/{nomor}` benar | otomatis | K2 |
| CRD-13 | Aturan `destroy` per tabel (00 C) | Untuk setiap entitas pada tabel 00 C: coba hapus; untuk yang bersyarat coba saat dirujuk lalu saat tidak dirujuk; panggil `DELETE` ke route yang seharusnya tidak ada | Entitas dengan dan tanpa rujukan; dokter, pasien, pendaftaran, halaman, pesan, staf, audit | **Hapus permanen** berhasil dan file media ikut terhapus: `banners`, `faqs`, `galleries` (beserta foto), `article_categories` (artikel menjadi tanpa kategori), `facilities`, `media`; `articles` = soft delete (`deleted_at`, hilang dari publik); **bersyarat**: `specialties`, `clinics`, `insurance_partners` ditolak bila dirujuk (flash alasan + saran "Nonaktifkan") dan terhapus bila tidak; `room_categories` selalu dapat dihapus (tidak dirujuk); `doctor_schedules` hanya bila tidak ada sesi ber-appointment; `practice_sessions` hanya sesi manual tanpa appointment; `schedule_exceptions` dihapus lewat `removeException` (sesi dibuka kembali); **tanpa route**: `DELETE` ke `admin.doctors`, `admin.patients`, `admin.appointments`, `admin.pages`, `admin.messages`, `admin.staff`, `admin.roles`, `admin.audit-logs` → 405/404 dan tidak ada nama route `*.destroy` di `route:list` selain daftar 00 C; tidak ada data hilang pada penolakan | otomatis | K2 |

### 4.3 Media (`MED`)

| ID | Skenario | Langkah | Data | Hasil yang diharapkan | Jenis | Kebutuhan §16 |
|---|---|---|---|---|---|---|
| MED-01 | Unggah foto dokter | Admin Konten unggah JPEG 800×800 + `alt_text` | Berkas JPEG valid | Baris `media` (`photo`, `disk=public`, UUID `.jpg`, `alt_text`); file di `photo/{YYYY}/{MM}/`; `thumb_path` terisi; foto tampil di kartu dan detail | otomatis | K3 |
| MED-02 | Ganti foto dokter | Unggah foto kedua untuk dokter yang sama | Dua berkas | Foto baru tampil; setelah commit file dan baris lama **terhapus** (`Storage::assertMissing`); bila transaksi gagal (pengecualian disimulasikan) file lama tetap | otomatis | K3 |
| MED-03 | Re-encode dan EXIF | Unggah JPEG berEXIF (GPS) lebar 3000 px | Berkas uji | Hasil lebar ≤ 1600 px; tanpa EXIF/GPS | otomatis | K3 |
| MED-04 | Nama berkas aman | Unggah dengan nama `../../x<script>.jpg` | Berkas uji | Disimpan sebagai UUID; `original_name` tersanitasi; tidak ada path traversal | otomatis | Tambahan (§12) |
| MED-05 | Batas ukuran dan dimensi | Unggah >2 MB; di bawah minimum per koleksi (`photo` 300×300, `cover` 800×450, `gallery`/`photos` 600×400, banner 1200×400, `logo` 120×40, `editor` 200×100); >6000 px; banner >4 MB | Berkas uji | Ditolak dengan pesan Bahasa Indonesia yang menyebut batas (00 K) | otomatis | K12 |
| MED-06 | Thumbnail | Unggah gambar valid | Berkas uji | Thumbnail lebar 400 px tersedia dan dipakai pada daftar | otomatis | K3 |
| MED-07 | `media:prune` | Buat baris `media` tanpa pemilik >24 jam, file yatim, dan media terpakai; jalankan `--dry-run` lalu nyata | Data uji | Dry-run hanya melaporkan; eksekusi menghapus yatim saja; media terpakai aman | otomatis | Tambahan (K) |
| MED-08 | Gambar hilang | Hapus file fisik dari disk (baris tetap); media `NULL` | Dokter dengan foto hilang | Halaman 200; `x-image` memuat placeholder lokal (accessor) dan, untuk file hilang, memberi atribut `data-fallback` berisi URL placeholder yang dipakai satu listener `error` terdelegasi di `app.js`; **tidak ada** atribut `onerror` inline (kompatibel CSP); alt text tetap ada | otomatis + manual | K14 |

### 4.4 Jadwal, sesi, pengecualian, sesi terdampak (`JDW`)

| ID | Skenario | Langkah | Data | Hasil yang diharapkan | Jenis | Kebutuhan §16 |
|---|---|---|---|---|---|---|
| JDW-01 | Generator dari templat | `ensureRange(today, today+30)`; ulangi dengan templat nonaktif, di luar rentang berlaku, dokter `inactive`/`archived`, dan poliklinik tidak aktif | Templat Sen/Rab + empat templat "tidak layak" | Satu sesi per tanggal sesuai `day_of_week` dalam `valid_from`/`valid_until`; jam dan kuota = templat; keempat templat tidak layak tidak menghasilkan sesi (00 F.2: hanya templat aktif dalam rentang berlaku dengan dokter `active` dan poliklinik aktif) | otomatis | K2 |
| JDW-02 | Idempoten | Jalankan generator dua kali; edit satu sesi manual lalu regenerasi | Sesi dengan `is_manually_modified` | Tanpa duplikat (UNIQUE `doctor_schedule_id`+`session_date`); sesi manual tidak ditimpa | otomatis | Tambahan (F.2) |
| JDW-03 | Cuti dokter tidak dapat dipesan | Tambah `doctor_leave` 3 hari; cek `DoctorAvailabilityQuery`; coba `book()` | Dokter bersesi di rentang | Sesi `cancelled` + `schedule_exception_id`; `Availability::leave`; `book()` → `SESSION_UNAVAILABLE`; tidak muncul sebagai pilihan wizard (fase 3: diuji lewat `DoctorAvailabilityQuery` dengan `is_bookable_online = false` dan halaman #6/#7/#8; `book()` dan wizard di fase 6, F3-R5) | otomatis | K4 |
| JDW-04 | Libur RS tidak dapat dipesan | Pengecualian `hospital_holiday` (`doctor_id` NULL) satu hari | Banyak dokter | Semua sesi hari itu `cancelled`; `Availability::holiday`; `book()` ditolak (fase 3: lewat `DoctorAvailabilityQuery` dan halaman #6/#7/#8; `book()` di fase 6, F3-R5) | otomatis | K4 |
| JDW-05 | Lampau, cut-off, jendela, prioritas label | Query dan `book()` untuk sesi yang sudah berakhir, sesi mulai 30 menit lagi (kuota tersisa), sesi penuh dan lewat batas online, dan hari ke-31 | `setTestNow` | Prioritas label F.3: `past` → (`leave`/`holiday`/`cancelled`) → `under_review` → `closed` → `full` → `booking_closed` → `available`; sesi mulai 30 menit lagi → `booking_closed`, `is_bookable_online = false` tetapi `is_bookable_counter = true`; `book()` online → `CUTOFF_PASSED`; hari ke-31 → `OUTSIDE_WINDOW`; loket berhasil sampai `end_time` (fase 3: label, `is_bookable_online`, dan `is_bookable_counter` lewat `DoctorAvailabilityQuery`; `book()` di fase 6, F3-R5) | otomatis | K4 |
| JDW-06 | Templat beririsan | Simpan templat dokter sama, hari sama, jam beririsan, rentang berlaku beririsan; lalu rentang tidak beririsan | Dua templat | Yang pertama ditolak (pesan menyebut templat bentrok); yang kedua diterima | otomatis | K12 |
| JDW-07 | Kuota sesi tidak di bawah `booked_count` | Edit kuota sesi dengan 5 booking menjadi 4 lalu 5 | Sesi `booked_count=5` | 4 ditolak (pesan jelas); 5 diterima | otomatis | K10 |
| JDW-08 | Ubah templat dengan booking | (a) kuota saja, (b) jam, (c) ganti hari/nonaktif, (d) ganti poliklinik; sesi dengan/ tanpa appointment aktif | Sesi campuran + sesi manual + sesi lampau | (a) `max(kuota_baru, booked_count)` + pesan jumlah; (b)(c) sesi dengan appointment aktif → `needs_review` + `pending_change` (`template_time`/`template_removed`), lainnya diperbarui/digenerate ulang; sesi manual dan lampau tak berubah; (d) ditolak bila ada sesi mendatang dengan appointment aktif | otomatis | Tambahan (F.4) |
| JDW-09 | Tambah pengecualian dengan booking | Pengecualian di rentang berisi sesi dengan dan tanpa appointment aktif | Dua sesi | Tanpa appointment aktif → `cancelled`; dengan → `needs_review` (`{"source":"exception",…}`); booking lama tetap aktif, booking baru ditolak; `start_date` masa lalu ditolak | otomatis | K4 |
| JDW-10 | Hapus/persempit pengecualian | `removeException()` (dipanggil sebelum baris dihapus) dan persempit rentang | Sesi `cancelled` oleh pengecualian; sesi `needs_review` bersumber pengecualian itu | Sesi yang belum lewat dibuka kembali (`open`); sesi `needs_review` kembali `open` dengan `pending_change` dan `schedule_exception_id` dikosongkan; sesi yang sudah lewat tidak berubah (00 F.4) | otomatis | Tambahan (F.4) |
| JDW-11 | Resolusi sesi terdampak | `resolve()` dengan `keep`, `apply`, `cancel` untuk setiap sumber: `exception`, `template_time`, `template_removed`, `manual_time`, `manual_cancel`, `doctor_inactive` | Sesi `needs_review` dengan appointment aktif | `keep` → `open` + `is_manually_modified`; `apply` (hanya `template_time`/`manual_time`) → jam baru, `schedule_changed_at`, notifikasi, peringatan benturan; `cancel` → sesi dan appointment aktif `cancelled` (staf, alasan wajib; `cancelAllForSession`), kuota dilepas, `booked_count` konsisten; pilihan per sumber sesuai F.4 (`exception`/`template_removed`/`manual_cancel`: keep atau cancel; `doctor_inactive`: `keep` hanya bila dokter aktif kembali); pilihan tidak sah ditolak; izin `sessions.review` (Admin Konten 403) | otomatis | Tambahan (F.4) |
| JDW-12 | Satu sumber jadwal | Bandingkan hasil `DoctorAvailabilityQuery` dengan data di `doctors.show`, `schedules.index`, wizard, form walk-in | Set sesi campuran | Daftar sesi, `remaining`, `availability`, `is_bookable_online`, dan `is_bookable_counter` identik di keempatnya; tes kontrak memastikan keempatnya memanggil kelas yang sama (fase 3: `doctors.show` dan `schedules.index`, `PublicScheduleTest`; wizard dan form walk-in di fase 6/7, F3-R5) | otomatis | K2, K4 |
| JDW-13 | Sesi tambahan manual | Buat sesi tanpa templat (`doctor_schedule_id` NULL); regenerasi | Sesi manual | Bisa dibuat berulang (UNIQUE dengan NULL); tidak terhapus regenerasi; dapat dipesan (fase 3: `is_bookable_online = true` pada `DoctorAvailabilityQuery`; `book()` di fase 6, F3-R5) | otomatis | Tambahan (E.2) |
| JDW-14 | Admin Konten tanpa data pasien | Admin Konten buka layar jadwal sesudah ubah templat | Sesi dengan appointment | Hanya **jumlah** booking per sesi; pesan "N sesi perlu ditindaklanjuti petugas pendaftaran"; tidak ada nama/telepon pasien di HTML | otomatis | Tambahan (G) |
| JDW-15 | Edit manual sesi dengan appointment aktif | `updateSession()` dan `setSessionStatus()` pada sesi yang memiliki appointment aktif; ulangi pada sesi tanpa appointment aktif | Dua sesi | Kuota: dinaikkan langsung, diturunkan di bawah `booked_count` ditolak; jam diubah → `needs_review` sumber `manual_time`; pembatalan sesi → `needs_review` sumber `manual_cancel`; sesi tanpa appointment aktif langsung berubah/`cancelled`; `is_manually_modified = true` | otomatis | Tambahan (F.4) |
| JDW-16 | Dokter dinonaktifkan atau diarsipkan | Nonaktifkan dokter bersesi mendatang (dengan/tanpa appointment aktif); coba arsipkan; aktifkan kembali | Dokter + sesi | Sesi mendatang tanpa appointment aktif → `cancelled`; dengan appointment aktif → `needs_review` sumber `doctor_inactive`; arsip hanya bila tidak ada appointment aktif mendatang (selain itu ditolak dengan pesan); dokter arsip tidak tampil di publik dan tidak dapat diberi jadwal | otomatis | Tambahan (F.4) |
| JDW-17 | Prioritas `pending_change` (satu per sesi) | Sesi ber-appointment aktif: (a) `template_time` lalu tambah pengecualian (`exception`); (b) `exception` lalu ubah jam templat; (c) `template_time` lalu `manual_time`; (d) `manual_cancel` lalu `doctor_inactive`; (e) pengecualian dicabut saat sesi masih `needs_review` | Satu sesi, beberapa pemicu berurutan | (a) `pending_change` menjadi `exception` (jenis-batal mengalahkan jenis-jam); (b) tetap `exception` (jenis-jam tidak menimpa); (c) `manual_time` menggantikan `template_time` (setingkat → terbaru); (d) `doctor_inactive` menggantikan `manual_cancel` (setingkat → terbaru); setiap penggantian mencatat audit `session.pending_replaced` (lama → baru); selalu tepat satu `pending_change`; (e) sesi kembali `open`, `pending_change` dan `schedule_exception_id` NULL (bila belum lewat) | otomatis | Tambahan (F.4) |
| JDW-18 | Kombinasi jenis pengecualian vs dokter | Buat pengecualian: `doctor_leave` tanpa dokter; `hospital_holiday` dan `national_holiday` dengan dokter; `other` dengan dan tanpa dokter; ulangi dengan `DB::table()->insert` langsung | Empat jenis | Form Request menolak tiga kombinasi pertama dengan pesan jelas (BR-25); `other` diterima pada kedua bentuk; `doctor_leave` berdokter dan libur RS/nasional tanpa dokter diterima; insert langsung yang melanggar ditolak DB (errno 4025, `chk_schedule_exceptions_scope`) | otomatis | Tambahan (E.2) |
| JDW-19 | Hapus templat dan sesi tambahan manual | (a) hapus templat yang hanya punya sesi tanpa appointment; (b) hapus templat yang punya sesi ber-appointment (juga hanya riwayat batal); (c) `DELETE admin.sessions.destroy` pada sesi manual tanpa appointment; (d) pada sesi manual ber-appointment; (e) pada sesi hasil templat; (f) tanpa `schedules.manage` | Templat dan sesi campuran | (a) templat dan sesi miliknya terhapus (tidak ada sesi yatim `doctor_schedule_id` NULL); (b) ditolak, tombol "Nonaktifkan", tidak ada yang terhapus; (c) terhapus + audit `session.deleted`; (d) dan (e) ditolak dengan pesan alasan dan saran "Tutup/Batalkan"; (f) Petugas 403 | otomatis | Tambahan (00 C) |

### 4.5 Kepemilikan data pasien (`PAS`)

| ID | Skenario | Langkah | Data | Hasil yang diharapkan | Jenis | Kebutuhan §16 |
|---|---|---|---|---|---|---|
| PAS-01 | Riwayat hanya milik sendiri | Pasien A membuka `patient.appointments.index` | A: 3 booking, B: 2 booking | Hanya 3 baris milik A; tidak ada `booking_code` B pada HTML | otomatis | K5 |
| PAS-02 | Detail booking orang lain | A membuka `/pasien/pendaftaran/{booking_code B}` | Kode milik B | **404** (`AppointmentPolicy::view` dengan `denyAsNotFound`, BR-54) agar keberadaan kode tidak dapat diuji; tidak ada data B pada respons | otomatis | K5 |
| PAS-03 | Batal booking orang lain | A `POST .../{kode B}/batal` | Booking B `pending` | **404** (BR-81); status B, `booked_count`, dan riwayat tidak berubah | otomatis | K5 |
| PAS-04 | Bukti hanya pemilik dan petugas | GET `bukti` dan `bukti.pdf` sebagai: pemilik, pasien lain, Petugas, Admin Konten, Tamu | Satu booking | Pemilik 200; pasien lain **404**; Petugas (`appointments.view`) 200 lewat `admin.appointments.proof`; Admin Konten 403; Tamu →L; nomor BPJS/polis hanya 4 digit terakhir | otomatis | K5 |
| PAS-05 | Ubah profil hanya milik sendiri | `PUT /pasien/profil` dengan tambahan `user_id`, `patient_number`, `registration_channel`, `is_active`, `bpjs_number_last4` | Pasien A, profil B ada | Hanya kolom yang diizinkan berubah; kolom tambahan diabaikan; profil B tidak tersentuh; tidak ada ID profil di URL | otomatis | K5 |
| PAS-06 | Petugas melihat data pasien | Petugas buka `admin.patients.show` | Profil dengan BPJS | 200; BPJS tampil tersamar (4 digit terakhir); aksi tercatat di audit bila ada perubahan | otomatis | K5 |
| PAS-07 | Admin Konten tanpa data pasien | Admin Konten GET `admin.patients.*`, `admin.appointments.*`; buka dashboard | Data pasien ada | Rute pasien/pendaftaran 403; dashboard hanya angka agregat (tanpa nama pasien) | otomatis | K5 |
| PAS-08 | Notifikasi milik sendiri | `POST /pasien/notifikasi/{id}/baca` untuk notifikasi sendiri dan milik pasien lain | Dua pasien | Milik sendiri ditandai dibaca; milik lain 404; `read_at` lain tidak berubah | otomatis | K5 |
| PAS-09 | Sinkronisasi email profil dan akun | Pasien berakun mengubah email di profil; ulangi dengan email milik akun lain; nonaktifkan profil berakun | Dua pasien | Email profil dan `users.email` berubah bersamaan dalam satu transaksi dengan validasi unik di `users`; email bentrok ditolak tanpa perubahan parsial; `patient_profiles.email` selalu = `users.email` (00 E.1); nonaktif profil menyetel `users.is_active=false` | otomatis | K5 |
| PAS-10 | Filter `jadwal_berubah` pada riwayat | GET `patient.appointments.index?jadwal_berubah=1`, `=0`, `=abc` | Pasien dengan 1 appointment ber-`schedule_changed_at` dan 2 lainnya; pasien lain dengan appointment berubah jadwal | `=1` → hanya appointment milik sendiri yang berubah jadwal; nilai lain diabaikan (daftar penuh, bukan 500); banner "jadwal berubah" di dashboard menaut ke URL ini | otomatis | K5 |

### 4.6 Keamanan (`SEK`)

| ID | Skenario | Langkah | Data | Hasil yang diharapkan | Jenis | Kebutuhan §16 |
|---|---|---|---|---|---|---|
| SEK-01 | Password tersimpan hash | Registrasi; buat staf; ganti password | `Rahasia123` | `users.password` ≠ teks asli, berawalan `$2y$`, lolos `Hash::check`; password tidak ada di log, audit, atau respons | otomatis | K7 |
| SEK-02 | Regenerasi sesi dan logout | Catat ID sesi sebelum dan sesudah login; logout | Pasien dan staf | ID sesi berubah setelah login; logout memanggil invalidate + token CSRF baru; sesi lama tidak valid | otomatis | Tambahan (§12) |
| SEK-03 | Throttle login | 6 percobaan gagal dalam 1 menit (email + IP sama) | Email sama; email lain | Percobaan ke-6: pada POST → redirect 302 kembali ke form dengan flash `error` "Terlalu banyak percobaan. Silakan coba lagi dalam {n} detik." dan header `Retry-After`; pada GET → halaman 429 (11 §11.3); kunci limiter tidak membedakan huruf besar/kecil dan spasi pada email; email lain tidak terkena; setelah 1 menit boleh lagi | otomatis | Tambahan (H) |
| SEK-04 | Throttle reset password | 4 permintaan dalam 10 menit | Email sama | Ke-4 → 429; seluruh respons sukses tetap generik | otomatis | Tambahan (H) |
| SEK-05 | Throttle registrasi dan booking | 6 registrasi/jam/IP; 11 booking/menit/user | IP dan user sama | Registrasi ke-6 dan booking ke-11 → 429 | otomatis | Tambahan (H) |
| SEK-06 | CSRF | POST tanpa token ke form publik, pasien, admin (CSRF diaktifkan kembali di tes) | Form kontak, login, booking, hapus admin | **419** pada semua; dengan token valid berhasil | otomatis | Tambahan (§12) |
| SEK-07 | Header keamanan | GET `/` pada `APP_ENV=testing` dan `production` | Respons | `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy` ada; produksi memuat CSP dengan `frame-src https://www.google.com https://maps.google.com`; HTML tidak memuat atribut event inline (`onerror`, `onclick`, dst.) sehingga CSP ketat tidak memblokir; lokal tidak memblokir Vite | otomatis | Tambahan (J.3) |
| SEK-08 | Sanitasi rich text | Simpan `<script>`, `onerror`, `javascript:`, `style` berbahaya ke `pages.body`, `articles.body`, `clinics.description`, `facilities.description`, `room_categories.description` | Payload XSS | Tersimpan tanpa skrip/atribut event/URL `javascript:`; tag aman (`p`, `ul`, `strong`, `a`, `img` ber-alt) dipertahankan; render tidak mengeksekusi | otomatis | Tambahan (§12) |
| SEK-09 | Tolak unggahan SVG | Unggah `.svg` berisi `<script>` pada form foto dokter, banner, galeri, logo, editor | Berkas SVG | 422 dengan pesan jelas; tidak ada berkas tersimpan; tidak ada baris `media` | otomatis | Tambahan (§12) |
| SEK-10 | Ekstensi ganda dan MIME palsu | Unggah `shell.php.jpg`, `x.phtml`, berkas PHP berkepala JPEG, teks bernama `.jpg` | Berkas uji | Ditolak (mimes + mimetypes + dimensi/re-encode gagal); tidak ada berkas dapat dieksekusi di disk; berkas yang lolos selalu bernama UUID | otomatis | Tambahan (§12) |
| SEK-11 | Redaksi audit log | Ubah password staf, buat booking BPJS, ubah profil ber-BPJS, reset password | Nilai sensitif uji | `old_values`/`new_values` memuat `[disamarkan]` untuk `password`, `remember_token`, `token`, `bpjs_number`, `payment_reference`; pencarian nilai asli di `audit_logs` = 0 hasil | otomatis | Tambahan (E.6) |
| SEK-12 | Enkripsi BPJS/polis | Simpan profil dan booking berpembayaran BPJS | `0009900000011` | Nilai mentah di DB (`DB::table`) terenkripsi (bukan teks asli); model membaca benar; `*_last4` terisi; halaman hanya menampilkan 4 digit terakhir | otomatis | Tambahan (E.1, E.3) |
| SEK-13 | QR tanpa data sensitif | Render bukti; decode SVG QR | Satu booking | Isi QR persis `UPH-XXXX-XXXX`; tidak memuat nama, tanggal lahir, BPJS, atau ID numerik | otomatis | Tambahan (F.7) |
| SEK-14 | Throttle kontak dan hash IP | 4 kiriman form kontak dalam 10 menit dari IP sama; periksa tabel | Form valid | Ke-4 → 429; `ip_hash` 64 heksadesimal dan ≠ IP mentah; IP mentah tidak tersimpan; persetujuan wajib | otomatis | Tambahan (H, E.5) |
| SEK-15 | Escape output | Nama pasien/keluhan/subjek pesan berisi `<script>alert(1)</script>`; periksa layar pasien, admin, PDF | Payload | Seluruhnya ter-escape; uji statis: `{!!` hanya dipakai untuk lima kolom rich text | otomatis | Tambahan (§12) |
| SEK-16 | Pesan generik | Login email tak terdaftar vs password salah; reset email terdaftar vs tidak | Dua kasus | Teks dan status respons identik ("Email atau password salah"; respons reset sama) | otomatis | Tambahan (H) |
| SEK-17 | Disk privat tidak terekspos | Simpan berkas di disk `local`; GET `/storage/…` | Berkas privat | 404; tidak ada URL publik; akses hanya lewat controller + policy | otomatis | Tambahan (K) |

### 4.7 Booking, status, kuota, antrean (`BKG`)

| ID | Skenario | Langkah | Data | Hasil yang diharapkan | Jenis | Kebutuhan §16 |
|---|---|---|---|---|---|---|
| BKG-01 | Booking online berhasil | `BookingService::book()` pasien online | Sesi `open` kuota 3, `auto_confirm=false` | Appointment `pending`, `queue_number=1`, `booked_count=1`, `last_queue_number=1`, `booking_code` valid; riwayat ∅→`pending`; audit log tercatat | otomatis | K10, K11 |
| BKG-02 | Booking ganda ditolak | Pasien yang sama memesan sesi yang sama dua kali; lalu batalkan dan pesan lagi | Sesi kuota 5 | Kedua → `DUPLICATE_BOOKING`, `booked_count` tetap; setelah pembatalan boleh memesan lagi (unik hanya untuk status aktif) | otomatis | K9 |
| BKG-03 | Benturan jam ditolak | Pesan sesi 09:00–12:00 lalu sesi lain tanggal sama 11:00–13:00; lalu 12:00–14:00 | Dua dokter | Yang beririsan → `OVERLAPPING_BOOKING`; yang berimpit tepat di batas (12:00) diterima | otomatis | Tambahan (§9) |
| BKG-04 | Kuota penuh | Kuota 2, dua booking, pasien ketiga | Tiga pasien | Ketiga → `QUOTA_FULL`; `booked_count` tetap 2; wizard menampilkan "Penuh" | otomatis | K8 |
| BKG-05 | Sesi tidak tersedia | `book()` pada sesi `closed`, `needs_review`, `cancelled` (cuti/libur); dokter nonaktif; poliklinik tidak aktif/`is_bookable=false` | Enam kondisi | Semua → `SESSION_UNAVAILABLE` | otomatis | K4 |
| BKG-06 | Di luar jendela | Sesi kemarin dan today+31; ubah `booking.window_days` menjadi 7 | `setTestNow` | `OUTSIDE_WINDOW`; pengaturan baru langsung berlaku | otomatis | Tambahan (F.5) |
| BKG-07 | Batas pendaftaran online dan loket | Online pada `start−59` dan `start−61` menit; loket (petugas) pada `start−10` dan `end+1` | Sesi 09:00–12:00 | Online −59 → `CUTOFF_PASSED`, −61 berhasil; loket berhasil sampai `end_time`, setelahnya `CUTOFF_PASSED` | otomatis | Tambahan (F.5) |
| BKG-08 | Batas aktif per pasien | `booking.max_active_per_patient=3`; booking ke-4 online; booking loket; ubah ke 4 | Pasien dengan 3 aktif mendatang | Online → `ACTIVE_LIMIT_REACHED`; loket tidak dibatasi; setelah diubah ke 4 berhasil | otomatis | Tambahan (F.5) |
| BKG-09 | Aturan pembayaran | `bpjs` tanpa/dengan 13 digit; `asuransi` tanpa mitra/mitra nonaktif/mitra aktif; `umum` dengan referensi | Tujuh kombinasi | `INVALID_PAYMENT` untuk yang salah; yang benar tersimpan (`payment_reference` terenkripsi + `last4`); `umum` selalu tanpa referensi | otomatis | K12 |
| BKG-10 | Pembatalan melepas kuota; antrean tidak dipakai ulang | A (antrean 1), B (2); A batal; C memesan | Sesi kuota 3 | Setelah A batal `booked_count=1`, `last_queue_number=2`; C mendapat antrean **3** (1 tidak dipakai ulang); kuota terbuka kembali | otomatis | K10, K11 |
| BKG-11 | Batas pembatalan pasien | Batal pada `start−121` dan `start−119` menit; status final; bukan pemilik | `booking.cancel_cutoff_minutes=120` | −121 berhasil; −119 → `InvalidTransitionException` kode `CANCEL_CUTOFF_PASSED` dengan arahan menghubungi RS (telepon/WhatsApp); status final tidak dapat dibatalkan (`ALREADY_FINAL`); bukan pemilik 404 | otomatis | K10 |
| BKG-12 | Matriks transisi status | Uji setiap pasangan (dari, ke, pelaku) tabel F.8 | 6 status × pelaku (pasien, petugas, sistem) | Transisi sah berhasil dan menyetel kolom waktunya; sisanya ditolak dengan `InvalidTransitionException` berkode baku (00 F.5): `INVALID_TRANSITION` (mis. `pending`→`completed`), `ALREADY_FINAL` (status final → apa pun), `FORBIDDEN_TRANSITION` (pelaku tak berhak), `STALE_STATUS` (status berubah sejak dimuat), `CANCEL_CUTOFF_PASSED` (pasien lewat batas batal), `CONFIRM_AFTER_END` (konfirmasi setelah sesi berakhir), `CHECKIN_NOT_TODAY`/`CHECKIN_NOT_OPEN`/`CHECKIN_CLOSED` (check-in), `CORRECTION_NOT_SAME_DAY` (koreksi bukan hari yang sama), `REASON_REQUIRED` (alasan wajib kosong), `SESSION_NOT_ENDED` (`no_show` sebelum `end_time`); semua 12 kode terpicu minimal satu kali | otomatis | K10 |
| BKG-13 | Dampak kuota per status | Telusuri `booked_count` pada tiap transisi; batalkan dua kali | Sesi kuota 3 | `pending/confirmed/checked_in/completed/no_show` tetap menghitung; `cancelled` mengurangi tepat 1; pembatalan ulang ditolak (tidak mengurangi dua kali); invarian I1 benar | otomatis | K10 |
| BKG-14 | Check-in petugas | Check-in pada `start−61`, `start−59`, setelah `end_time`, dan pada tanggal lain; oleh non-petugas | Booking `confirmed` | Pada hari lain → `CHECKIN_NOT_TODAY`; sebelum jendela (`start−61`) → `CHECKIN_NOT_OPEN` (pesan menyebut jam buka); setelah `end_time` → `CHECKIN_CLOSED`; di jendela (`start−59`) berhasil (`checked_in_at`, riwayat); tanpa `appointments.manage` 403 | otomatis | Tambahan (F.8) |
| BKG-15 | Konfirmasi otomatis | `booking.auto_confirm=true`; booking online | Sesi terbuka | Appointment langsung `confirmed` dengan riwayat ∅→`confirmed` | otomatis | Tambahan (F.8) |
| BKG-16 | Tidak hadir | `appointments:mark-no-show --date=…` (`markNoShowForDate`) dan aksi massal petugas (`markNoShowForSession`); tanpa `--date` | Sesi selesai berisi pending/confirmed/checked_in; sesi belum selesai | `pending`/`confirmed` pada sesi berakhir → `no_show` (`no_show_at`, aktor sistem/petugas); `checked_in` dan sesi belum berakhir tidak berubah; `markNoShowForSession` sebelum `end_time` → `SESSION_NOT_ENDED`; tanpa `--date` memproses semua sesi yang sudah berakhir (00 F.8); `booked_count` tetap | otomatis | K10 |
| BKG-17 | Antrean dan kode booking | 5 booking sesi yang sama; 1000 pembuatan kode; simulasi bentrok kode | Poliklinik `ANK` | `queue_number` 1..5 unik berurutan; tampilan `ANK-001`…; `booking_code` cocok `^UPH-[0-9A-HJKMNP-TV-Z]{4}-[0-9A-HJKMNP-TV-Z]{4}$` dan unik; bentrok memicu pembuatan ulang | otomatis | K11 |
| BKG-18 | Pelanggaran constraint dipetakan | Simulasikan galat driver di tengah `book()`: errno 1062 pada `appointments_patient_session_active_unique`, errno 1062 pada `appointments_booking_code_unique`, errno 4025 (MariaDB) pada `chk_sessions_quota`, errno 1062 pada `appointments_practice_session_id_queue_number_unique`, errno 1205 dan 1213 setelah `attempts: 3` | Mock/hook `QueryException` (`errorInfo[1]`) | Berturut-turut: `DUPLICATE_BOOKING`; kode booking baru dan ulangi (maks 5); `QUOTA_FULL`; `SYSTEM_BUSY` (+ `report()`); `SYSTEM_BUSY`; bukan HTTP 500; rollback penuh: tanpa appointment/riwayat parsial dan `booked_count` kembali; CHECK lain di luar `chk_sessions_quota` → galat sistem 500 generik yang dicatat | otomatis | K8, K9 |
| BKG-19 | Koreksi check-in | `checked_in`→`confirmed` dengan/tanpa alasan; hari sama/beda | Booking `checked_in` | Hanya hari yang sama dan alasan wajib (`REASON_REQUIRED` bila kosong); selainnya ditolak; riwayat mencatat alasan | otomatis | Tambahan (F.8) |
| BKG-20 | Pesan galat jelas | Panggil semua kode `BookingException` | 9 kode (8 aturan F.5 + `SYSTEM_BUSY`) | Setiap kode memiliki pesan Bahasa Indonesia unik dan ditampilkan sebagai flash pada wizard (bukan halaman 500) | otomatis | K12 |
| BKG-21 | Sistem sibuk (`SYSTEM_BUSY`) | Tahan kunci baris sesi dari koneksi lain melewati `innodb_lock_wait_timeout` (diset 1 detik) sehingga `book()` kehabisan `attempts: 3`; ulangi dengan galat 1213 yang disimulasikan; ulangi dengan `BookingService::precheck()` | Koneksi penahan kunci; mock `QueryException` 1205 dan 1213 | `BookingException` kode `SYSTEM_BUSY` berpesan "Sistem sedang sibuk, silakan coba lagi beberapa saat."; rollback penuh (tidak ada appointment/riwayat/audit parsial; `booked_count` dan `last_queue_number` tidak berubah); pengguna melihat flash `error` dan kembali ke `patient.appointments.create` (bukan HTTP 500); `precheck()` tidak mengunci dan tidak menulis (tidak pernah `SYSTEM_BUSY`); setelah kunci dilepas booking yang sama berhasil | otomatis | K8 |

### 4.8 Walk-in dan hubungkan akun (`WLK`)

| ID | Skenario | Langkah | Data | Hasil yang diharapkan | Jenis | Kebutuhan §16 |
|---|---|---|---|---|---|---|
| WLK-01 | Profil walk-in baru | Petugas mengisi form pasien dengan persetujuan lisan dicentang; ulangi tanpa centang | Data pasien | `user_id` NULL, `registration_channel=loket`, `created_by_user_id`, `consent_at`, `patient_number` `P`+6 digit; tanpa centang ditolak dengan pesan jelas | otomatis | Tambahan (K1 00) |
| WLK-02 | Cari profil | Cari berdasarkan nama, no. pasien, telepon, tanggal lahir | 13 profil demo | Hasil tepat; BPJS tersamar; hanya `patients.view` | otomatis | Tambahan (F.9) |
| WLK-03 | Booking loket | Petugas memilih sesi via `DoctorAvailabilityQuery` (`is_bookable_counter`) lalu `book()` dengan `Actor::staff` | Walk-in + sesi | Status awal `confirmed`; antrean berikutnya; batas `now < end_time` (sesi `booking_closed` untuk online tetap dapat dipilih di loket); riwayat ∅→`confirmed` (petugas) | otomatis | Tambahan (F.9) |
| WLK-04 | Berbagi kuota dengan online | Kuota 2: satu online + satu loket; lalu online dan loket ketiga | Sesi kuota 2 | Keduanya berikut → `QUOTA_FULL`; `booked_count=2` | otomatis | K8 |
| WLK-05 | Hubungkan akun — berhasil | Petugas hubungkan akun pasien tanpa appointment ke profil walk-in beriwayat | Akun + profil | Satu transaksi: profil kosong akun dihapus, `user_id` dipasang; pasien login melihat riwayat walk-in; audit log tercatat | otomatis | Tambahan (F.9) |
| WLK-06 | Hubungkan akun — ditolak | Profil akun sudah punya appointment; akun sudah terhubung; non-petugas; registrasi dengan nama/tanggal lahir sama | Beberapa kondisi | Ditolak dengan pesan (penggabungan belum didukung); tanpa `patients.manage` 403; **tidak ada** pencocokan otomatis | otomatis | Tambahan (F.9) |
| WLK-07 | Nonaktifkan pasien | `admin.patients.status` → nonaktif; ulangi untuk profil berakun | Profil beriwayat | Tidak dapat membuat booking baru; riwayat tidak terhapus; dapat diaktifkan kembali; tercatat di audit; profil berakun menyetel `users.is_active` sama (dan sebaliknya, 00 E.1) | otomatis | Tambahan (E.1) |

### 4.9 Validasi, notifikasi, data kosong (`VAL`, `NTF`, `EMP`)

| ID | Skenario | Langkah | Data | Hasil yang diharapkan | Jenis | Kebutuhan §16 |
|---|---|---|---|---|---|---|
| VAL-01 | Form dokter | Kirim form kosong dan sebagian salah | Nama kosong, gender salah, poliklinik tidak ada | Pesan Bahasa Indonesia per field; ringkasan galat (`form.errors-summary`) di atas form; nilai lama dipertahankan; field memiliki `aria-invalid` dan `aria-describedby` | otomatis | K12 |
| VAL-02 | Form registrasi | Email ganda, password lemah, tanggal lahir masa depan, persetujuan tak dicentang | Beberapa kasus | Pesan spesifik ("Email sudah terdaftar", "Password minimal 8 karakter dan memuat huruf serta angka", dst.); tidak ada akun/profil parsial | otomatis | K12 |
| VAL-03 | Wizard pendaftaran | BPJS kurang dari 13 digit, asuransi tanpa mitra, sesi tidak valid, keluhan >500 karakter | Beberapa kasus | Pesan jelas per field dan kembali ke langkah terkait dengan pilihan dipertahankan | otomatis | K12 |
| VAL-04 | Form kontak | Email salah, pesan kosong, tanpa persetujuan | Beberapa kasus | Pesan per field; data tidak tersimpan; tidak ada pesan kosong di tabel | otomatis | K12 |
| VAL-05 | Form jadwal | `end_time ≤ start_time`, kuota 0 dan 201, `valid_until < valid_from`, hari 8 | Beberapa kasus | Pesan jelas ("Jam selesai harus setelah jam mulai", "Kuota 1–200", dst.); CHECK DB tidak pernah menjadi satu-satunya pagar | otomatis | K12 |
| VAL-06 | Form unggah | Format salah, ukuran berlebih, dimensi kecil, alt text kosong untuk koleksi publik | Berkas uji | Pesan menyebut format/batas yang diizinkan; alt text wajib | otomatis | K12 |
| VAL-07 | Parameter query tidak valid | GET `/dokter?tanggal=abc&spesialisasi=tidak-ada&poli[]=x`, `/jadwal-dokter?minggu=xx`, `/artikel?kategori=%00`, admin `?status=zzz&kembali=https://situs-lain.test` | Parameter 00 I.5 | Halaman 200 tanpa filter yang tidak valid (bukan 500); `kembali` eksternal diabaikan (`SafeRedirect`: hanya path internal) | otomatis | K12 |
| VAL-08 | Alur `review`/`store` wizard tanpa `back()` | POST `patient.appointments.review` dan `.store` dengan BPJS 12 digit, sesi penuh, sesi tidak ada, dan token CSRF kedaluwarsa (419) | Pasien aktif; sesi valid dan sesi penuh | Semua kegagalan = redirect 302 ke `patient.appointments.create?sesi={id}` (query `poli`/`dokter`/`sesi` terakhir) dengan `withInput()` dan galat per field atau flash; **tidak** ada redirect ke URL halaman tinjau dan mengikuti redirect memberi 200 (bukan 405); nomor BPJS tidak ikut dikembalikan ke formulir | otomatis | K12 |
| NTF-01 | Notifikasi perubahan status | Petugas konfirmasi booking pasien berakun | Booking `pending` | Satu `database` notification `AppointmentStatusChangedNotification` ke pasien (isi: kode, status baru; **tanpa** nomor BPJS); banner belum dibaca di dashboard | otomatis | Tambahan (L) |
| NTF-02 | Surat notifikasi | Perubahan status ke `confirmed` dan `cancelled` (kanal `mail`, K-13) dengan `Mail::fake()` dan `MAIL_MAILER=log` | Booking + akun | Surat berbahasa Indonesia, berisi kode booking dan jadwal; mode `log` menulis ke `storage/logs/laravel-YYYY-MM-DD.log` | otomatis + manual | Tambahan (L) |
| NTF-03 | Walk-in tanpa akun | Ubah status booking walk-in | Profil tanpa `user_id` | Tidak ada galat dan tidak ada notifikasi/surat; riwayat dan audit tetap tercatat | otomatis | Tambahan (F.8) |
| NTF-04 | Reset password lewat mail log | Minta reset; buka log; salin tautan; setel password baru; login | Akun pasien; `MAIL_MAILER=log` | Halaman **tidak** menampilkan tautan; tautan ada di log; password baru berlaku; token sekali pakai (pemakaian ulang gagal); throttle berlaku | manual + otomatis (`Notification::fake`) | Tambahan (H) |
| NTF-05 | Respons reset generik | Minta reset untuk email terdaftar dan tidak terdaftar | Dua email | Pesan dan status identik ("Jika email terdaftar, tautan telah dikirim"); tidak ada penanda terdaftar | otomatis | Tambahan (H) |
| NTF-06 | Jadwal berubah | Resolusi `apply` pada sesi dengan appointment | Booking aktif | `schedule_changed_at` terisi; `AppointmentScheduleChangedNotification` terkirim; banner "jadwal berubah" tampil pada detail booking | otomatis | Tambahan (F.4) |
| NTF-07 | Kanal notifikasi per transisi (K-13) | Lakukan semua transisi F.8 dan resolusi `apply` pada appointment pasien berakun dengan `Notification::fake()` | Satu pasien berakun | `database` dibuat untuk **setiap** transisi (termasuk `checked_in`, `completed`, `no_show`); `mail` hanya untuk `confirmed`, `cancelled`, dan `AppointmentScheduleChangedNotification`; `via()` sesuai; pasien walk-in tanpa akun tidak menerima notifikasi apa pun | otomatis | Tambahan (F.8) |
| NTF-08 | Email selamat datang (K-22) | Registrasi pasien baru dengan `Notification::fake()` dan `MAIL_MAILER=log` | Data registrasi valid; satu registrasi gagal (rollback) | `PatientWelcomeNotification` (mail saja) terkirim setelah commit; tanpa tautan verifikasi; tidak terkirim bila registrasi gagal; tidak memuat data sensitif; mode `log` menulis ke `storage/logs/laravel-YYYY-MM-DD.log` | otomatis | Tambahan (K-22) |
| EMP-01 | Daftar publik kosong | Hapus semua data (dokter, artikel, galeri, FAQ, fasilitas, kamar) lalu buka halaman | Basis data kosong | Setiap halaman 200 dengan `x-empty-state` dan teks jelas (bukan error atau tabel kosong) | otomatis | K14 |
| EMP-02 | Pencarian tanpa hasil | Cari dokter dengan kata acak dan tanggal tanpa praktik | Filter | Pesan "Tidak ada dokter yang cocok" + saran mengubah filter; filter dipertahankan | otomatis | K14 |
| EMP-03 | Tabel admin kosong | Buka setiap daftar admin tanpa data; pencarian tanpa hasil | Basis data kosong | `x-empty-state` + tombol aksi "Tambah …" bila berizin; tidak ada galat | otomatis | K14 |
| EMP-04 | Dashboard pasien baru | Pasien tanpa appointment | Akun baru | Kartu "Belum ada pendaftaran" + tombol Daftar Kunjungan | otomatis | K14 |
| EMP-05 | Loading dan gagal | Kirim form: tombol `loading-button` nonaktif + `aria-busy`; simulasikan galat server dan validasi | Form kontak, booking | Tombol nonaktif saat submit; flash merah dan ringkasan galat pada gagal; flash hijau pada berhasil | manual + otomatis (flash) | K12, K14 |

### 4.10 Dashboard, ekspor, audit, staf, pengaturan (`DSH`, `AUD`, `STF`, `SET`)

| ID | Skenario | Langkah | Data | Hasil yang diharapkan | Jenis | Kebutuhan §16 |
|---|---|---|---|---|---|---|
| DSH-01 | Angka dashboard | Buka `admin.dashboard` pada data demo | `DemoSeeder` | Dokter aktif = 16; pasien terdaftar = profil aktif; pendaftaran hari ini berdasarkan `session_date`; jumlah per status cocok kueri langsung | otomatis | Tambahan (L) |
| DSH-02 | Widget per izin | Buka dashboard sebagai Admin Konten dan Petugas | Data demo | Admin Konten: angka agregat tanpa daftar bernama; Petugas: daftar pendaftaran hari ini bernama; ringkasan kamar + waktu pembaruan untuk semua | otomatis | K5 |
| DSH-03 | Grafik dan filter | `admin.dashboard.chart` untuk 7/30/90 hari dan rentang kustom | Data demo | JSON jumlah per hari menurut tanggal kunjungan; rentang tidak valid → 422; Chart.js tampil | otomatis + manual | Tambahan (L) |
| DSH-04 | Format CSV | `admin.appointments.export` dengan filter | Data demo | Berkas UTF-8 **dengan BOM**, pemisah `;`, kolom: kode booking, tanggal kunjungan, jam sesi, poliklinik, dokter, nomor antrean, no. pasien, nama pasien, jenis pembayaran, status, kanal, dibuat pada; streaming | otomatis | Tambahan (L) |
| DSH-05 | CSV tanpa data sensitif | Periksa isi CSV | Pasien ber-BPJS/telepon/alamat | Tidak ada nomor BPJS/polis, alamat, telepon, tanggal lahir, keluhan; nama pasien aman dari injeksi rumus (`=`/`+`/`-`/`@` diawali tanda kutip) | otomatis | Tambahan (L) |
| DSH-06 | Izin dan audit ekspor | Ekspor oleh Admin Konten (403), Petugas, Super Admin | Filter tertentu | Tanpa `reports.export` 403; ekspor berhasil mencatat audit `report.exported` beserta filternya | otomatis | Tambahan (L) |
| AUD-01 | Aksi penting tercatat | Lakukan: login staf, ubah jadwal, ubah status booking, ubah peran, ubah pengaturan | Staf demo | Satu `audit_logs` per aksi dengan `action`, `user_id`, `auditable_*`, `ip_address`, `user_agent` | otomatis | Tambahan (E.6) |
| AUD-02 | Redaksi pada UI | Buka detail audit log perubahan sensitif | Entri AUD-01 | Nilai sensitif tampil `[disamarkan]` | otomatis | Tambahan (E.6) |
| AUD-03 | Tidak dapat diubah | Cari tombol/rute ubah-hapus audit; coba `PUT`/`DELETE` | Entri audit | Tidak ada rute mutasi; permintaan manual 405/404 | otomatis | Tambahan (E.6) |
| AUD-04 | Filter dan izin | Filter aksi/pelaku/tanggal; akses oleh non-`audit.view` | Banyak entri | Filter akurat dan terpaginasi; selain Super Admin 403 | otomatis | Tambahan (G) |
| STF-01 | Buat akun staf | Super Admin buat staf dengan password sementara | Staf baru | `account_type=staff`, `must_change_password=true`, peran terpasang; login pertama dipaksa ganti password | otomatis | Tambahan (H) |
| STF-02 | Nonaktifkan dan reset password | Nonaktifkan staf; reset password | Staf aktif | Tidak dapat login; sesi berjalan terputus; reset menghasilkan password sementara baru + `must_change_password`; tercatat di audit | otomatis | Tambahan (H) |
| STF-03 | Minimal satu Super Admin | Nonaktifkan/hapus/ubah peran Super Admin terakhir; dua Super Admin secara bersamaan | Satu atau dua Super Admin | Super Admin terakhir ditolak (pesan jelas); dua yang berlomba tidak dapat sama-sama menghabisi (transaksi + kunci); aturan ditegakkan di `StaffAccountService` dan Form Request, bukan Policy | otomatis | Tambahan (G) |
| STF-04 | Ubah izin peran | Ubah izin `content-admin` | Peran | Perubahan berlaku segera (cache izin dibersihkan); tercatat di audit | otomatis | Tambahan (G) |
| STF-05 | Peran `super-admin` terkunci | Coba ubah/hapus peran `super-admin` | UI dan request manual | Ditolak; `Gate::before` tetap berlaku | otomatis | Tambahan (G) |
| STF-06 | Hanya Super Admin | Akses `admin.staff.*` dan `admin.roles.*` oleh peran lain | Dua peran | 403 | otomatis | K1 |
| STF-07 | Staf tidak mengubah akunnya sendiri | Staf (Super Admin dan peran lain) mencoba menonaktifkan atau mengubah peran akunnya sendiri | Dua staf | Ditolak dengan pesan jelas walau pelaku Super Admin (`Gate::before` melewati Policy, sehingga aturan ada di `StaffAccountService`); akun lain dapat diubah | otomatis | Tambahan (G) |
| SET-01 | Cache pengaturan | Simpan pengaturan; baca lewat `SettingsService` | `site.name` | Nilai baru terbaca segera (cache dibersihkan); tercatat di audit | otomatis | K2 |
| SET-02 | Pengaturan memengaruhi aturan | Ubah `booking.window_days`, `online_cutoff_minutes`, `cancel_cutoff_minutes`, `max_active_per_patient`, `auto_confirm` | Nilai baru | Perilaku `BookingService`/wizard mengikuti nilai baru (lihat BKG-06–BKG-08, BKG-11, BKG-15) | otomatis | Tambahan (E.7) |
| SET-03 | Validasi pengaturan | Isi URL tidak valid, email salah, angka negatif | Nilai salah | Pesan jelas; tidak tersimpan | otomatis | K12 |
| SET-04 | Peta dan WhatsApp | Ubah `map.*` dan `contact.whatsapp` | Nilai baru | Tombol "Petunjuk Arah" memakai `map.directions_url`; WhatsApp berupa tautan `https://wa.me/{nomor}` yang dibuka manual (bukan pengiriman otomatis) | otomatis | K2 |

### 4.11 Galat dan seeder (`ERR`, `SEED`)

| ID | Skenario | Langkah | Data | Hasil yang diharapkan | Jenis | Kebutuhan §16 |
|---|---|---|---|---|---|---|
| ERR-01 | 404 | Buka URL tak ada (publik dan admin) | `/tidak-ada` | Halaman Bahasa Indonesia dengan navigasi; tanpa jejak stack | otomatis + manual | K14 |
| ERR-02 | 403 | Pasien ke URL admin | Pasien | Halaman 403 ramah dengan tautan kembali; tanpa detail izin | otomatis | K6 |
| ERR-03 | 419 | Kirim form dengan sesi kedaluwarsa | Token tidak valid | Halaman "Sesi formulir kedaluwarsa" dengan tautan kembali, bukan galat mentah | otomatis | K12 |
| ERR-04 | 500 produksi | Paksa pengecualian dengan `APP_DEBUG=false` | Rute uji | Halaman 500 generik tanpa pesan internal; galat dicatat di log | otomatis | Tambahan (§12) |
| ERR-05 | `BookingException` bukan 500 | Picu setiap kode di wizard | 9 kode | Flash dan kembali ke langkah terkait; kode HTTP 302/422 | otomatis | K12 |
| SEED-01 | Invarian setelah seeding | `seed(DatabaseSeeder)` di `testing`; jalankan `scripts/sql/seed-invariants.sql` | DemoSeeder | 17 invarian `pelanggaran = 0`; angka acuan [13 §11.2](13-data-contoh.md#112-angka-acuan-db-baru) cocok | otomatis | Tambahan (§13) |
| SEED-02 | Guard produksi | `APP_ENV=production`: `db:seed`; `db:seed --class=DemoSeeder` | Lingkungan produksi | `DatabaseSeeder` hanya menjalankan seeder inti dan memberi peringatan; `DemoSeeder` melempar `RuntimeException`; tidak ada pengguna `.test` | otomatis | Tambahan (§13) |
| SEED-03 | Media setelah seeding | `media:prune --dry-run` setelah `DemoSeeder` | Data demo | 0 baris yatim, 0 berkas yatim; semua gambar berasal dari generator lokal (tidak ada permintaan jaringan) | otomatis | Tambahan (§13) |

---

## 5. Kasus Uji Manual (`RSP`, `A11`, `OFF`, `BAK`, `INS`)

Prosedur rinci ada di §7–§9; tabel ini adalah daftar ID untuk pencatatan hasil. Setiap kasus dicatat dengan templat bukti: **tanggal · penguji · lingkungan (Windows, browser + versi, PHP) · hasil (lulus/gagal) · bukti (nama berkas tangkapan layar)**.

| ID | Skenario | Langkah | Data | Hasil yang diharapkan | Jenis | Kebutuhan §16 |
|---|---|---|---|---|---|---|
| RSP-01 | Mobile 360 px — publik | Emulasi lebar 360 px; buka beranda, `/dokter`, `/dokter/{slug}`, `/jadwal-dokter`, `/kontak`, `/kamar-rawat-inap` | Data demo | Tanpa gulir horizontal; menu menjadi offcanvas; kartu satu kolom; tombol ≥ 44 px; teks terbaca tanpa zoom | manual | K13 |
| RSP-02 | Tablet 768 px | Lebar 768 px pada halaman yang sama + wizard | Data demo | Grid dua kolom; navigasi benar (titik putus Bootstrap `md`); tabel jadwal terbaca | manual | K13 |
| RSP-03 | Desktop 1280 px | Lebar 1280 px | Data demo | Navbar penuh, grid 3–4 kolom, tidak ada elemen meluber | manual | K13 |
| RSP-04 | Admin responsif | 360/768/1280 px pada daftar dokter, pasien, pendaftaran, form jadwal | Data demo | Tabel menjadi kartu di < 768 px; sidebar menjadi offcanvas; filter dapat dijangkau; aksi tetap dapat ditekan | manual | K13 |
| RSP-05 | Wizard dan bukti di ponsel | Wizard booking penuh di 360 px; pratinjau cetak bukti (A4) | Akun demo | Setiap langkah terjangkau tanpa gulir horizontal; bukti muat satu halaman A4 dan QR terbaca | manual | K13 |
| RSP-06 | Zoom dan orientasi | Zoom 200% dan rotasi potret/lanskap | Halaman utama | Konten tetap dapat dipakai; tidak ada teks terpotong | manual | K13 |
| A11-01 | Navigasi hanya keyboard | Tab/Shift+Tab/Enter/Esc di beranda, wizard, tabel admin, modal | — | Skip link muncul; semua kontrol dapat dijangkau; modal menjebak fokus lalu mengembalikannya; dropdown dan offcanvas dapat ditutup dengan Esc | manual | K13 (tambahan §4) |
| A11-02 | Fokus terlihat dan urutan | Tab melalui halaman | — | Indikator fokus jelas (kontras cukup); urutan fokus logis | manual | Tambahan (M) |
| A11-03 | Kontras | Periksa teks, tombol, badge status (Lighthouse/axe) | Token warna 00 M | Semua teks lolos WCAG AA; status selalu teks + ikon + warna; `#13A89E` tidak dipakai untuk teks putih | manual | Tambahan (M) |
| A11-04 | Alt text | Telaah gambar bermakna dan dekoratif | Data demo | Gambar bermakna memiliki alt informatif ("Foto contoh …"); dekoratif `alt=""`; unggahan publik mewajibkan alt | manual + otomatis (VAL-06) | Tambahan (§4) |
| A11-05 | Lighthouse aksesibilitas ≥ 90 | Audit 10 halaman: beranda, `/dokter`, detail dokter, `/jadwal-dokter`, `/kontak`, `/masuk`, `/daftar`, wizard langkah 1, dashboard pasien, `/admin` | Data demo | Skor aksesibilitas **≥ 90** pada setiap halaman | manual | Tambahan (§4) |
| A11-06 | Form dan pembaca layar | Kirim form salah; baca dengan NVDA (opsional) | Form kontak, wizard | Label terhubung; galat dihubungkan `aria-describedby`; ringkasan galat menerima fokus; flash memakai `role="alert"` | manual | K12 |
| OFF-01 | Halaman inti tanpa internet | Putuskan jaringan (matikan Wi-Fi/cabut kabel; **bukan** kotak Offline DevTools yang ikut memblokir localhost), muat ulang beranda, `/dokter`, `/jadwal-dokter`, `/kontak`, `/masuk`, `/admin/login` | Server lokal hidup | Seluruh halaman tampil penuh (HTML, CSS, JS, font, ikon, gambar) | manual | K15 |
| OFF-02 | Aset dan font lokal | Daftar permintaan jaringan saat memuat halaman | DevTools Network | Tidak ada permintaan ke domain eksternal (kecuali iframe peta); font Plus Jakarta Sans dan ikon dari `/build/` | manual | K15 |
| OFF-03 | Gambar placeholder | Buka halaman dengan dokter tanpa foto, poliklinik tanpa cover, kamar, banner | Data demo | Placeholder lokal tampil; tidak ada URL gambar eksternal di HTML | manual | K14, K15 |
| OFF-04 | Peta fallback | Buka `/kontak` offline | — | Alamat teks dan tombol "Petunjuk Arah" tampil; area peta tidak merusak tata letak | manual | K15 |
| OFF-05 | Alur inti offline | Registrasi → login → wizard → bukti; login admin → CRUD; reset password via log | Localhost tanpa internet | Seluruhnya berfungsi tanpa layanan eksternal | manual | K15 |
| OFF-06 | Pemindai tautan eksternal | `Get-ChildItem public\build -Recurse \| Select-String 'https?://'` dan pada `resources\views` | Hasil build | Hanya allowlist: peta Google (`maps`), tautan petunjuk arah, `wa.me`; selain itu kosong | manual | K15 |
| BAK-01 | Backup berhasil | Jalankan `scripts\backup.ps1` | Data demo | Empat berkas ber-timestamp di `backups\` (dump SQL, zip publik, zip privat, `SHA256SUMS-*.txt`), ukuran > 0, pesan sukses | manual | K16 |
| BAK-02 | Restore basis data | `scripts\restore.ps1` ke database `unpam_hospital_restore` | Berkas BAK-01 | Jumlah baris tiap tabel utama sama dengan sumber | manual | K16 |
| BAK-03 | Restore berkas unggahan | Ekstrak zip ke `storage\app\public` salinan | Zip BAK-01 | Foto dokter dan gambar lain tampil | manual | K16 |
| BAK-04 | Peran `APP_KEY` | Baca `bpjs_number` pada DB restore dengan `APP_KEY` sama dan berbeda | Salinan `.env` | Dengan kunci sama terbaca; dengan kunci berbeda gagal → membuktikan `APP_KEY` wajib disimpan terpisah | manual | K16 |
| BAK-05 | Aplikasi di atas DB restore | Arahkan `.env` salinan ke DB restore; login, jadwal, bukti; jalankan `seed-invariants.sql` | Salinan aplikasi | Semua berfungsi; 17 invarian `pelanggaran = 0` | manual | K16 |
| INS-01 | Prasyarat | Ikuti [16 §1–§2](16-instalasi-operasional.md) di mesin bersih | Windows 11 | `C:\xampp3\php\php.exe -v` = 8.2.12; seluruh ekstensi wajib aktif | manual | Tambahan (§15) |
| INS-02 | Pemasangan proyek | [16 §3–§6](16-instalasi-operasional.md): composer, npm, DB, `.env`, `migrate --seed`, `storage:link`, `npm run build` | Klon bersih | Tanpa galat; `php artisan about` benar | manual | Tambahan (§15) |
| INS-03 | VirtualHost | [16 §7](16-instalasi-operasional.md) | Apache XAMPP | `http://unpam-hospital.test` dan `/admin/login` tampil; `http://localhost/phpmyadmin` tetap dapat dibuka | manual | Tambahan (§15) |
| INS-04 | `php artisan serve` | [16 §8](16-instalasi-operasional.md) | APP_URL disesuaikan | `http://127.0.0.1:8000` tampil; gambar muncul | manual | Tambahan (§15) |
| INS-05 | Izin folder | [16 §9](16-instalasi-operasional.md) | — | Log tertulis; unggah foto berhasil | manual | Tambahan (§15) |
| INS-06 | Troubleshooting | Rusak sengaja: kosongkan `APP_KEY`, matikan `mod_rewrite`, hapus `public\build` | — | Gejala sesuai tabel [16 §13](16-instalasi-operasional.md#13-troubleshooting); perbaikan mengikuti tabel berhasil | manual | Tambahan (§15) |

---

## 6. Rancangan Tes Konkurensi (K8: dua permintaan bersamaan tidak melampaui kuota)

### 6.1 Prinsip dan penyiapan

- Grup PHPUnit `concurrency`, di folder `tests/Concurrency/`, memakai **`DatabaseTruncation`** (data harus ter-*commit* agar terlihat oleh koneksi/proses lain; `RefreshDatabase` membungkus tes dalam transaksi sehingga **tidak boleh** dipakai di sini).
- Basis data: MariaDB `unpam_hospital_test`; MariaDB XAMPP memakai InnoDB dengan `innodb_lock_wait_timeout` bawaan 50 detik (blueprint merekomendasikan 10 detik, 01 A-40; [16 §4.4](16-instalasi-operasional.md)) dan `max_connections` 151 (cukup untuk N ≤ 50).
- Proses anak dijalankan dengan `Symfony\Component\Process\Process` memanggil `PHP_BINARY` (PHP yang menjalankan tes; pada mesin pemilik gunakan `C:\xampp3\php\php.exe`) dan skrip pembantu **`tests/Support/Concurrency/book-session.php`** (di luar kode aplikasi sehingga tidak ada perintah uji di produksi). Lingkungan anak mewarisi `APP_ENV=testing` dan `CACHE_STORE=file`.
- Skrip pembantu (ringkas):

```php
<?php
// tests/Support/Concurrency/book-session.php
// php book-session.php --patient=ID --session=ID [--as=patient|staff] [--staff=USER_ID] [--start-at=EPOCH.FRAK] [--lock-timeout=DETIK]
require __DIR__ . '/../../../vendor/autoload.php';
$app = require __DIR__ . '/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$o = getopt('', ['patient:', 'session:', 'as::', 'staff::', 'start-at::', 'lock-timeout::']);
if (isset($o['lock-timeout'])) {
    DB::statement('SET SESSION innodb_lock_wait_timeout = ' . (int) $o['lock-timeout']);
}
while (isset($o['start-at']) && microtime(true) < (float) $o['start-at']) { usleep(1000); }  // penghalang serempak

try {
    $profile = PatientProfile::with('user')->findOrFail($o['patient']);
    $actor   = ($o['as'] ?? 'patient') === 'staff'
        ? Actor::staff(User::findOrFail($o['staff']))          // kanal loket
        : Actor::patient($profile->user);                       // kanal online
    $a = app(BookingService::class)->book(
        $profile, PracticeSession::findOrFail($o['session']), new BookingData(PaymentType::Umum), $actor,
    );
    echo json_encode(['ok' => true, 'queue' => $a->queue_number, 'code' => $a->booking_code]);
} catch (BookingException $e) {
    echo json_encode(['ok' => false, 'code' => $e->errorCode]);               // mis. QUOTA_FULL, DUPLICATE_BOOKING, SYSTEM_BUSY (1205/1213 sudah diterjemahkan BookingService)
} catch (Illuminate\Database\QueryException $e) {
    echo json_encode(['ok' => false, 'code' => 'DB_' . ($e->errorInfo[1] ?? 'ERR')]);  // galat DB tak terduga (tidak boleh muncul pada jalur normal)
}
```

  (`Actor::patient/staff/system`, `BookingData`, dan `BookingException::$errorCode` mengikuti [11 §6.1](11-route-controller-model-service.md); pasien uji dibuat dengan `PatientProfileFactory::withAccount()` agar `Actor::patient()` memiliki `User`.)

### 6.2 (a) Tes deterministik dua koneksi — `KON-01`

Membuktikan bahwa kunci baris `practice_sessions` (`FOR UPDATE`) benar-benar menyerialisasi booking, tanpa bergantung pada keberuntungan waktu.

**Versi spike fase 3 (F3-R6):** `tests/Feature/Database/ConcurrencySpikeTest.php` memakai dua `PDO` mentah ke database uji, tanpa `BookingService`, dan membuktikan empat hal: (a) `SELECT … FOR UPDATE` pada sesi yang sama dari koneksi kedua menunggu sampai galat 1205; (b) urutan kunci baku `patient_profiles → practice_sessions → appointments` tidak deadlock; (c) UNIQUE (`patient_profile_id`, `practice_session_id`, `active_flag`) menolak appointment aktif kedua tetapi menerima yang `cancelled`; (d) CHECK `chk_sessions_quota` menolak `booked_count > quota`. Varian `KON-01a`–`d` di bawah dengan `BookingService` dikerjakan di fase 6.

**Koneksi A** = `PDO` mentah di proses tes (`beginTransaction` lalu `SELECT … FROM practice_sessions WHERE id = ? FOR UPDATE`). **Koneksi B** = proses anak `book-session.php` dengan `--lock-timeout` rendah.

| Varian | Langkah | Hasil yang diharapkan |
|---|---|---|
| `KON-01a` A mengambil kursi terakhir | Sesi kuota 1. A mengunci sesi, B (pasien lain) dijalankan; tunggu ±0,8 detik; **assert B masih berjalan** (menunggu kunci); A menyisipkan appointment pasien-1 (kolom dari `Appointment::factory()->forSession()->raw()`), menaikkan `booked_count` dan `last_queue_number` menjadi 1, lalu `commit`; tunggu B | B selesai setelah commit dengan `QUOTA_FULL`; `booked_count = 1`; hanya 1 appointment |
| `KON-01b` A membatalkan | Sama seperti 01a tetapi A `rollBack` | B berhasil: `queue = 1`, `booked_count = 1`, 1 appointment |
| `KON-01c` Lock wait habis | A memegang kunci ≥ 6 detik (lebih lama dari 3 percobaan × timeout 1 detik, karena `attempts: 3` mengulang lock-wait 1205), B `--lock-timeout=1` | B gagal `SYSTEM_BUSY` (`BookingService` menerjemahkan lock wait timeout 1205 setelah `attempts: 3`, 00 F.5); **tidak ada** appointment/riwayat parsial; `booked_count` tetap 0; setelah A commit, B yang diulang berhasil |
| `KON-01d` Serialisasi per pasien | A mengunci baris `patient_profiles` pasien P; B (pasien P, sesi lain) dijalankan | B menunggu selama A memegang kunci pasien (langkah 1 urutan kunci baku), lalu berlanjut setelah commit — membuktikan kunci pertama berlaku |

Seluruh varian diakhiri dengan pemeriksaan invarian I1, I2, I4, I8 ([13 §11](13-data-contoh.md#11-invarian-setelah-seeding-dan-cara-memeriksanya)).

### 6.3 (b) Uji beban sederhana — N proses paralel, kuota K < N — `KON-02` s.d. `KON-05`

| ID | Skenario | N | K / kondisi awal | Verifikasi |
|---|---|:-:|---|---|
| `KON-02a` | Banyak pasien berbeda, satu sesi | 20 | K = 5 | Tepat **5** berhasil; **15** `QUOTA_FULL`; tidak ada `DB_1213` (deadlock) atau galat lain; `booked_count = 5`; `last_queue_number = 5`; `queue_number` kelima appointment = himpunan {1,2,3,4,5} tanpa duplikat; jumlah appointment 5 |
| `KON-02b` | Kursi terakhir | 10 | K = 1 | Tepat 1 berhasil dengan antrean 1; 9 `QUOTA_FULL` |
| `KON-02c` | Stres (opsional, manual: `$env:KON_STRESS = "1"`) | 50 | K = 10 | Tepat 10 berhasil; antrean 1..10 unik; waktu total tercatat |
| `KON-03` | Pasien yang sama, banyak permintaan | 8 | satu pasien, K = 10 | Tepat 1 berhasil; 7 `DUPLICATE_BOOKING`; satu appointment aktif (UNIQUE `active_flag` tidak terlanggar) |
| `KON-04` | Pembatalan bersamaan dengan booking | 1 batal + 5 pesan | K = 1, sudah terisi 1 | Setelah semua selesai: appointment non-batal ≤ K; `booked_count` = jumlah non-batal (I1); antrean unik dan tidak dipakai ulang; berhasil memesan ≤ 1 |
| `KON-05` | Dua petugas mentransisi appointment sama | 2 | `confirmed` → `checked_in` vs `confirmed` → `cancelled` | Tepat satu menang, yang lain ditolak (transisi tidak sah setelah penguncian); `booked_count` konsisten; riwayat tidak ganda |

Alur `KON-02a` (juga dipakai KON-02b/02c/03 dengan parameter berbeda):

```php
#[Group('concurrency')]
public function test_n_proses_paralel_tidak_melampaui_kuota(): void
{
    $k = 5; $n = 20;
    $session  = PracticeSession::factory()->open()->create(['quota' => $k]);
    $patients = PatientProfile::factory()->count($n)->create();
    $startAt  = microtime(true) + 4.0;                       // waktu boot semua proses

    $procs = $patients->map(fn ($p) => new Process(
        [PHP_BINARY, base_path('tests/Support/Concurrency/book-session.php'),
         "--patient={$p->id}", "--session={$session->id}", "--start-at={$startAt}"],
        base_path(), ['APP_ENV' => 'testing'], null, 60
    ))->each->start();

    $results = $procs->map(fn ($pr) => json_decode($pr->wait() === 0 ? $pr->getOutput() : '{"ok":false,"code":"PROC"}', true));

    $this->assertCount($k, $results->where('ok', true));
    $this->assertCount($n - $k, $results->where('code', 'QUOTA_FULL'));          // tidak ada DB_1213 / PROC
    $s = $session->fresh();
    $this->assertSame($k, $s->booked_count);
    $this->assertSame($k, $s->last_queue_number);
    $this->assertEqualsCanonicalizing(range(1, $k), Appointment::where('practice_session_id', $s->id)->pluck('queue_number')->all());
    $this->assertSame($k, Appointment::where('practice_session_id', $s->id)->count());
}
```

Catatan operasional: jalankan 3–5 kali berturut-turut (`for` di PowerShell) karena kegagalan balapan bersifat probabilistik; satu kegagalan sudah berarti cacat. Pada Windows, boot tiap proses PHP ±0,3–1 detik, sehingga penghalang `--start-at` diperlukan agar permintaan benar-benar serempak.

```powershell
1..5 | ForEach-Object { C:\xampp3\php\php.exe artisan test --group=concurrency --stop-on-failure }
```

`QueryException` dengan kode 1205/1213 yang masih terjadi setelah `attempts: 3` diterjemahkan `BookingService` menjadi `BookingException` kode `SYSTEM_BUSY` (00 F.5; tes `BKG-21`); tes `KON-02` menegaskan bahwa pada jalur normal galat ini **tidak muncul** karena urutan kunci baku dan `attempts: 3`.

---

## 7. Prosedur Manual: Responsif dan Aksesibilitas

### 7.1 Responsif (`RSP-01`–`RSP-06`)

Alat: Chrome atau Edge → DevTools (F12) → **Toggle device toolbar** (Ctrl+Shift+M) → mode *Responsive*.

| Lebar | Perangkat acuan | Halaman yang diperiksa |
|---|---|---|
| 360 × 800 | Ponsel kecil | Beranda, `/dokter`, `/dokter/{slug}`, `/jadwal-dokter`, `/layanan`, `/kamar-rawat-inap`, `/kontak`, `/masuk`, `/daftar`, wizard (`/pasien/pendaftaran/baru`), riwayat, `/admin` dan daftar admin |
| 768 × 1024 | Tablet | Idem |
| 1280 × 800 | Laptop/desktop | Idem |

Daftar periksa tiap lebar:
1. Tidak ada gulir horizontal pada `<body>` (DevTools Console: `document.documentElement.scrollWidth <= window.innerWidth` harus `true`).
2. Navigasi: < 992 px berupa tombol menu/offcanvas; ≥ 992 px navbar penuh; tombol utama "Cari Dokter", "Jadwal Dokter", "Daftar Kunjungan" terlihat tanpa gulir pada beranda.
3. Kartu dokter: foto, nama, spesialisasi, tombol jadwal; grid 1 kolom (360), 2 kolom (768), 3–4 kolom (1280).
4. Target sentuh ≥ 44 × 44 px untuk tombol dan tautan penting.
5. Tabel admin berubah menjadi kartu di < 768 px (nama kolom tampil sebagai label); filter/pencarian tetap dapat dijangkau.
6. Teks ≥ 16 px, tinggi baris 1,6; tidak ada teks terpotong pada zoom 200%.
7. Bukti pendaftaran: pratinjau cetak (Ctrl+P) muat 1 halaman A4; QR terbaca (uji dengan pemindai ponsel — isinya hanya `UPH-XXXX-XXXX`).

### 7.2 Aksesibilitas (`A11-01`–`A11-06`)

**Keyboard saja** (cabut atau abaikan mouse):
- Tab pertama pada setiap halaman memunculkan **skip link** "Lewati ke konten utama".
- Semua tautan, tombol, dropdown, offcanvas, modal dapat dijangkau dan dioperasikan (Enter/Spasi); Esc menutup modal, offcanvas, dropdown dan mengembalikan fokus ke pemicu.
- Wizard pendaftaran dapat diselesaikan penuh tanpa mouse.
- Urutan fokus mengikuti urutan visual; indikator fokus jelas.

**Lighthouse (≥ 90)**:

```powershell
# halaman publik (tanpa login)
npx lighthouse http://unpam-hospital.test/ --only-categories=accessibility --chrome-flags="--headless" --output=json --output-path="$env:TEMP\lh-home.json" --quiet
(Get-Content "$env:TEMP\lh-home.json" -Raw | ConvertFrom-Json).categories.accessibility.score
```

Nilai yang dicetak adalah 0–1; **harus ≥ 0,90**. Untuk halaman yang memerlukan login (dashboard pasien, wizard, admin) gunakan panel **Lighthouse** di DevTools pada sesi yang sudah login (kategori *Accessibility*, mode *Navigation*). Catat skor tiap halaman pada templat bukti.

**Kontras dan status:** badge `AppointmentStatus` menampilkan teks + ikon + warna; teks putih tidak pernah di atas `#13A89E`; perhatikan peringatan kontras Lighthouse/axe.

**Alt text:** gambar bermakna memiliki alt informatif; gambar dekoratif `alt=""`; pada form unggah publik alt text wajib.

**Form:** setiap field memiliki `<label>`; galat dihubungkan `aria-describedby`; ringkasan galat menerima fokus setelah submit gagal; pesan sukses/gagal memakai `role="status"`/`role="alert"`.

---

## 8. Prosedur Manual: Offline (`OFF-01`–`OFF-06`)

Server lokal (Apache/`artisan serve`) harus berjalan dan resolusi `unpam-hospital.test` berasal dari berkas `hosts`, sehingga aplikasi tetap dapat dijangkau tanpa internet.

1. **Putuskan jaringan.** Matikan Wi-Fi atau cabut kabel. Jangan memakai kotak *Offline* di DevTools: opsi itu ikut memblokir `localhost`/`127.0.0.1` sehingga hasilnya menyesatkan. Alternatif PowerShell (jalankan sebagai Administrator; **aktifkan kembali sesudah uji**):

   ```powershell
   Get-NetAdapter | Where-Object Status -eq 'Up'                       # catat nama adapter
   Disable-NetAdapter -Name "Wi-Fi" -Confirm:$false                    # putuskan
   # ... lakukan uji ...
   Enable-NetAdapter  -Name "Wi-Fi" -Confirm:$false                    # pulihkan
   ```
2. Muat ulang (Ctrl+Shift+R) beranda, `/dokter`, `/jadwal-dokter`, `/kontak`, `/masuk`, `/admin/login`: seluruh tata letak, font Plus Jakarta Sans, ikon Bootstrap, dan gambar harus tampil (OFF-01, OFF-02).
3. **Kolom Network** (DevTools): tidak boleh ada permintaan ke domain eksternal selain iframe peta Google; tidak ada `fonts.googleapis.com`, CDN Bootstrap/jQuery, dsb. (OFF-02).
4. **Placeholder** (OFF-03): halaman dengan dokter tanpa foto (dr. Teguh, dr. Ayu), poliklinik Gigi tanpa cover, artikel #5 tanpa cover menampilkan placeholder lokal.
5. **Peta** (OFF-04): `/kontak` menampilkan alamat teks dan tombol **Petunjuk Arah**; area peta tidak merusak tata letak.
6. **Alur inti** (OFF-05): daftar akun pasien → masuk → wizard sampai bukti; masuk admin → ubah satu data; minta reset password lalu ambil tautan dari `storage\logs\laravel-YYYY-MM-DD.log`.
7. **Pemindai tautan eksternal** (OFF-06; jalankan saat online/offline):

   ```powershell
   # aset hasil build
   Get-ChildItem "D:\Project Web RS\public\build" -Recurse -Include *.css,*.js |
     Select-String -Pattern 'https?://[^"''\s)]+' -AllMatches |
     ForEach-Object { $_.Matches.Value } | Sort-Object -Unique
   # HTML beranda
   curl.exe -s http://unpam-hospital.test/ | Select-String -Pattern '(src|href)="https?://[^"]+' -AllMatches |
     ForEach-Object { $_.Matches.Value } | Sort-Object -Unique
   ```

   Keluaran HTML hanya boleh memuat allowlist: iframe/tautan Google Maps dan `https://wa.me/…`. Keluaran aset boleh memuat URL pada komentar lisensi pustaka dan namespace `http://www.w3.org/2000/svg`; tidak boleh ada URL yang **diambil** saat runtime (cek ulang di tab Network).

---

## 9. Prosedur Manual: Backup dan Restore (`BAK-01`–`BAK-05`)

Skrip `scripts\backup.ps1` dan `scripts\restore.ps1` didefinisikan di [16 §12](16-instalasi-operasional.md#12-backup-dan-restore).

1. **Siapkan data:** `php artisan migrate:fresh --seed` (data demo), unggah satu foto dokter lewat admin (agar ada berkas nyata).
2. **Backup (BAK-01):**

   ```powershell
   powershell -ExecutionPolicy Bypass -File "D:\Project Web RS\scripts\backup.ps1"
   Get-ChildItem "D:\Project Web RS\backups" | Sort-Object LastWriteTime -Descending | Select-Object -First 4 Name, Length
   ```
   Harus ada empat berkas ber-timestamp (dump `.sql`, zip `storage-public`, zip `storage-private`, `SHA256SUMS-*.txt`) dengan `Length` > 0; bandingkan hash dengan `Get-FileHash -Algorithm SHA256`.
3. **Restore basis data ke database kosong (BAK-02):**

   ```powershell
   powershell -ExecutionPolicy Bypass -File "D:\Project Web RS\scripts\restore.ps1" -SqlFile "D:\Project Web RS\backups\<berkas>.sql" -Database unpam_hospital_restore
   ```
4. **Bandingkan jumlah baris** (bukti):

   ```powershell
   $env:MYSQL_PWD = Read-Host "Password unpam_app"
   $mysql = "C:\xampp3\mysql\bin\mysql.exe"
   foreach ($t in 'users','patient_profiles','doctors','practice_sessions','appointments','appointment_status_histories','media') {
     $a = & $mysql -u unpam_app -N -e "SELECT COUNT(*) FROM unpam_hospital.$t"
     $b = & $mysql -u unpam_app -N -e "SELECT COUNT(*) FROM unpam_hospital_restore.$t"
     "{0,-32} sumber={1,-6} restore={2,-6} {3}" -f $t, $a, $b, $(if ($a -eq $b) { 'OK' } else { 'BEDA' })
   }
   Remove-Item Env:\MYSQL_PWD
   ```
   Semua baris harus `OK`.
5. **Restore berkas (BAK-03):** ekstrak zip `storage-public` ke salinan `storage\app\public`; buka halaman dokter dan pastikan foto tampil.
6. **`APP_KEY` (BAK-04):**

   ```powershell
   $env:DB_DATABASE = "unpam_hospital_restore"
   # kunci yang sama (dari .env asli): data terenkripsi terbaca
   C:\xampp3\php\php.exe artisan tinker --execute="echo App\Models\PatientProfile::whereNotNull('bpjs_number_last4')->first()->bpjs_number;"
   # kunci berbeda: harus gagal (DecryptException)
   $env:APP_KEY = (C:\xampp3\php\php.exe artisan key:generate --show)
   C:\xampp3\php\php.exe artisan tinker --execute="echo App\Models\PatientProfile::whereNotNull('bpjs_number_last4')->first()->bpjs_number;"
   Remove-Item Env:\DB_DATABASE, Env:\APP_KEY
   ```
   Hasil pertama menampilkan 13 digit fiktif; hasil kedua `DecryptException` — membuktikan `APP_KEY` wajib dicadangkan terpisah.
7. **Aplikasi di atas DB restore (BAK-05):** salin `.env` ke `.env.restore-test`, ubah `DB_DATABASE`, jalankan dengan `php artisan serve --env=restore-test`, login dan buka jadwal serta bukti; jalankan `scripts\sql\seed-invariants.sql` pada `unpam_hospital_restore` → 17 invarian `pelanggaran = 0`.
8. **Bersihkan:** `DROP DATABASE unpam_hospital_restore;` setelah bukti disimpan.

---

## 10. Kriteria Penerimaan (Definition of Done)

### 10.1 Per area

| Area | Kriteria selesai | Bukti |
|---|---|---|
| Akses dan peran | Matriks 40 halaman × 5 aktor lulus (`AKS-01`–`AKS-11`); setiap route admin berizin dan Policy (`KNS-02`); pasien ditolak di URL admin | Suite `Feature/Access` hijau |
| CRUD dan publik | Seluruh entitas CMS dan master data dapat dibuat/diubah/dinonaktifkan dan efeknya terlihat di publik (`CRD-01`–`CRD-13`) | Suite `Feature/Admin` hijau |
| Jadwal | Templat → sesi → pengecualian → sesi terdampak bekerja sesuai F.2–F.4; satu sumber jadwal, prioritas `pending_change`, aturan `destroy` (`JDW-01`–`JDW-19`) | Suite `Feature/Scheduling` hijau |
| Booking dan antrean | Semua aturan F.5, transisi F.8, batas pembatalan, antrean unik dan tidak dipakai ulang, `SYSTEM_BUSY` (`BKG-01`–`BKG-21`) | Suite `Feature/Booking` hijau |
| Konkurensi | Kuota tidak pernah terlampaui pada `KON-01`–`KON-05`, diulang 5 kali berturut-turut tanpa kegagalan | Keluaran 5 putaran `--group=concurrency` |
| Walk-in | Walk-in, berbagi kuota, hubungkan akun sesuai F.9 (`WLK-01`–`WLK-07`) | Suite hijau |
| Keamanan | Seluruh `SEK-01`–`SEK-17` lulus; tidak ada `{!! !!}` di luar lima kolom rich text; header keamanan aktif | Suite hijau + pemeriksaan statis |
| Data dan media | Unggah/ganti/validasi/prune benar; SVG dan ganda ekstensi ditolak (`MED-01`–`MED-08`); gambar hilang tertangani | Suite hijau + OFF-03 |
| Validasi dan galat | Pesan validasi jelas (`VAL-01`–`VAL-08`, termasuk alur wizard tanpa `back()`); halaman galat Indonesia (`ERR-01`–`ERR-05`) | Suite hijau |
| Notifikasi dan reset | `NTF-01`–`NTF-08` (termasuk kanal per K-13 dan email selamat datang K-22); reset password teruji via log/Mailpit | Suite hijau + catatan manual NTF-04 |
| Dashboard, ekspor, audit, staf, pengaturan | `DSH`, `AUD`, `STF`, `SET` lulus; CSV tanpa data sensitif dan berizin | Suite hijau |
| Data contoh | `SEED-01`–`SEED-03` lulus; invarian [13 §11](13-data-contoh.md#11-invarian-setelah-seeding-dan-cara-memeriksanya) = 0; tidak ada akun demo di produksi | Keluaran kueri invarian |
| Responsif dan a11y | `RSP-01`–`RSP-06` dan `A11-01`–`A11-06` lulus; Lighthouse aksesibilitas ≥ 90 pada 10 halaman acuan | Tangkapan layar + skor |
| Offline | `OFF-01`–`OFF-06` lulus tanpa permintaan eksternal selain peta | Catatan + tangkapan Network |
| Backup dan restore | `BAK-01`–`BAK-05` lulus; pemulihan terbukti (jumlah baris sama, berkas pulih, `APP_KEY` dipahami) | Keluaran skrip + tabel hitung |
| Instalasi | `INS-01`–`INS-06` lulus di klon bersih mengikuti [16](16-instalasi-operasional.md) apa adanya | Catatan penguji |
| Dokumentasi | README lokal, catatan produksi, daftar keputusan terbuka di [17](17-keputusan-konfirmasi.md); tidak ada bagian kosong atau berstatus tertunda pada dokumen blueprint | Telaah dokumen |

### 10.2 Konsistensi: setiap fitur punya halaman, route, tabel, hak akses, dan alur

Dibuktikan oleh `KNS-01`–`KNS-05` dan **matriks keterlacakan** yang dilengkapi untuk seluruh ID fitur [02](02-ruang-lingkup.md) (`F-PUB-01` … `F-SYS-20`; pemetaan ke fase di [14 §4](14-tahapan-implementasi.md#4-pemetaan-id-fitur-dari-02-ke-fase)) pada fase 9. Contoh baris yang sudah valid:

| Fitur (ID 02) | Halaman # | Route (00 I) | Tabel (00 E) | Izin (00 G) | Alur (06) | Tes |
|---|---|---|---|---|---|---|
| F-ADM-02 Kelola dokter + foto (F-PUB-05, F-PUB-06) | 33, 6, 7 | `admin.doctors.*`, `doctors.show` | `doctors`, `doctor_specialties`, `media` | `doctors.view`, `doctors.manage` | Admin kelola dokter | `CRD-03`, `MED-01`, `MED-02` |
| F-ADM-05/06/07 Jadwal dan sesi (F-SYS-01, F-SYS-02, F-PUB-07) | 35, 6, 7, 8 | `admin.schedules.*`, `admin.sessions.*`, `admin.exceptions.*`, `schedules.index` | `doctor_schedules`, `schedule_exceptions`, `practice_sessions` | `schedules.view`, `schedules.manage` | Admin jadwal → publik | `JDW-01`–`JDW-19` |
| F-PAS-04…09 Booking dan bukti (F-SYS-03, F-SYS-04, F-SYS-05) | 29, 30 | `patient.appointments.*` | `appointments`, `appointment_status_histories`, `practice_sessions` | (akun pasien) + `AppointmentPolicy` | Alur pasien booking | `BKG-*`, `KON-*`, `PAS-*` |
| F-ADM-08 Sesi terdampak (F-SYS-06) | 35 (tinjau) | `admin.sessions.review`, `admin.sessions.resolve` | `practice_sessions.pending_change`, `appointments` | `sessions.review` | Alur perubahan jadwal | `JDW-11` |
| F-ADM-10/11 Kamar dan kapasitas (F-PUB-09) | 36, 11–15 | `admin.room-categories.*`, `rooms.*` | `room_categories`, `media` | `facilities.manage`, `rooms.update-availability` | Admin perbarui kapasitas | `CRD-07` |
| F-PUB-15, F-ADM-24 Kontak dan pesan | 22 + kotak pesan | `contact.store`, `admin.messages.*` | `contact_messages`, `website_settings` | `messages.manage`, `settings.manage` | Pengunjung → petugas | `SEK-14`, `CRD-12`, `VAL-04` |
| F-ADM-17 Ekspor CSV | 38 | `admin.appointments.export` | `appointments`, `audit_logs` | `reports.export` | Petugas ekspor laporan | `DSH-04`–`DSH-06` |

### 10.3 Penerimaan keseluruhan

Prototipe dinyatakan **diterima** bila seluruh syarat berikut terpenuhi:

1. `C:\xampp3\php\php.exe artisan test --exclude-group=concurrency` hijau, dan `--group=concurrency` hijau 5 putaran berturut-turut (195 ID kasus: 161 otomatis/campuran, 5 konkurensi — `KON-01` s.d. `KON-05` dengan sub-varian —, 29 manual).
2. Seluruh kasus manual (`RSP`, `A11`, `OFF`, `BAK`, `INS`) bertanda lulus dengan bukti tercatat.
3. Matriks akses 40 halaman lulus untuk kelima aktor; pasien tidak dapat membuka satu pun URL admin.
4. Invarian [13 §11](13-data-contoh.md#11-invarian-setelah-seeding-dan-cara-memeriksanya) = 0 setelah seeding, setelah suite konkurensi, dan pada basis data hasil restore.
5. Lighthouse aksesibilitas ≥ 90 pada 10 halaman acuan; tampilan benar pada 360/768/1280 px.
6. Seluruh fitur inti berjalan tanpa internet (peta jatuh ke alamat teks); tidak ada klaim integrasi (BPJS, SATUSEHAT, SIMRS, payment gateway, WhatsApp API) yang tampak aktif di UI.
7. Tidak ada kredensial demo atau `DemoSeeder` pada konfigurasi produksi; `php artisan app:create-super-admin` terbukti membuat Super Admin.
8. `vendor\bin\pint --test` bersih; tidak ada penanda pekerjaan tertunda pada kode maupun dokumen.
9. Konsistensi: setiap fitur memiliki halaman, route, tabel, hak akses, dan alur (`KNS-01`–`KNS-05` hijau dan matriks keterlacakan lengkap).
10. Hal bertanda **[KONFIRMASI]** di seluruh dokumen tercatat di [17](17-keputusan-konfirmasi.md) beserta keputusan atau jadwal keputusannya.

---

## Asumsi di bagian ini

- **[ASUMSI]** Non-pemilik menerima **404** (bukan 403) untuk booking, bukti, pembatalan, dan notifikasi milik pasien lain (BR-54, BR-81, BR-150 di [12](12-aturan-bisnis-validasi-keamanan.md)); Admin Konten pada rute admin.* tetap 403 karena middleware izin.
- Pelanggaran lock wait (SQLSTATE `HY000`/1205) dan deadlock (1213) yang lolos setelah `attempts: 3` menjadi `BookingException::SYSTEM_BUSY` dengan pesan "Sistem sedang sibuk, silakan coba lagi beberapa saat." (00 F.5; bukan lagi asumsi).
- **[ASUMSI]** Skrip konkurensi berupa berkas PHP di `tests/Support/Concurrency/` (bukan perintah artisan) agar tidak ada perintah khusus uji di produksi; nama kelas yang dipakai mengikuti [11 §6.1](11-route-controller-model-service.md).
- **[ASUMSI]** CSV melindungi dari injeksi rumus dengan awalan tanda kutip untuk sel yang diawali `=`, `+`, `-`, `@`.
- Ambang aksesibilitas = K-16 di 17, default (a): Lighthouse ≥ 90 + daftar periksa keyboard (A11-01); opsi (b) menambah audit manual dengan pembaca layar (NVDA) hanya bila dipilih pemilik.

## Nama baru yang diusulkan

| Nama | Jenis | Keterangan |
|---|---|---|
| Grup ID kasus uji `AKS`, `KNS`, `CRD`, `MED`, `JDW`, `PAS`, `SEK`, `BKG`, `KON`, `WLK`, `VAL`, `NTF`, `EMP`, `DSH`, `AUD`, `STF`, `SET`, `ERR`, `SEED`, `RSP`, `A11`, `OFF`, `BAK`, `INS` | Penanda uji | Didefinisikan di §2 |
| Grup PHPUnit `concurrency` | Atribut tes | Memisahkan tes proses paralel |
| `tests/Concurrency/`, `tests/Support/Concurrency/book-session.php`, `tests/Feature/Access/`, `tests/Feature/Seeding/` | Folder/berkas tes | §1.2 dan §6 |
| `unpam_hospital_restore` | Basis data sementara | Uji restore (BAK) |
| `.env.restore-test` | Berkas lingkungan sementara | BAK-05 |
| Variabel lingkungan `KON_STRESS` | Penanda uji | Mengaktifkan `KON-02c` |
