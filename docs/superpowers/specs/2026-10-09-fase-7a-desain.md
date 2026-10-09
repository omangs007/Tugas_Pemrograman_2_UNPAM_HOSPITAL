# Fase 7a — Pasien, Walk-in, Pendaftaran, dan Antrean: Dokumen Desain

**Tanggal:** 2026-10-09 · **Status:** disetujui per bagian lewat brainstorming. Dokumen ini menunggu tinjauan pemilik sebelum `superpowers:writing-plans`.
**Spesifikasi induk:** blueprint `docs/superpowers/specs/2026-10-02-unpam-hospital-blueprint/`, khususnya:
- 14 §Fase 7;
- 00 F.3, F.5, F.8–F.10, I.4 (#37, #38);
- 02 F-ADM-12 sampai F-ADM-16, F-SYS-12;
- 05 baris #37/#38;
- 07 W-19, W-20;
- 11 §6.3, §6.8;
- 13 §10;
- 15 (ID uji di §7).

Dokumen ini hanya mencatat **batas, keputusan, dan penyimpangan** fase 7a. Rincian yang tidak disebut di sini mengikuti blueprint.

## 1. Tujuan

Petugas Pendaftaran dapat:
- mengelola data pasien;
- mendaftarkan pasien walk-in di loket;
- memproses antrean dan status kunjungan harian;
- menandai pasien yang tidak hadir.

Semuanya memakai mesin booking dan status yang sama dengan portal pasien (fase 6).

**Ukuran keberhasilan:** kriteria keluar 14 §Fase 7 yang menjadi milik 7a lulus (§7), gerbang fase hijau (§8), dan uji manual tanpa temuan Critical/Important tersisa.

## 2. Keputusan pemilik (2026-10-09)

| Butir | Keputusan |
|---|---|
| Pemecahan fase 7 | **7a** (dokumen ini) dan **7b**. 7b berisi Sesi Terdampak, resolusi `keep`/`apply`/`cancel`, `cancelAllForSession`, pengisian `schedule_changed_at` dan `AppointmentScheduleChangedNotification`, pemberitahuan lintas peran, K-15, dan bawaan fase 3 untuk layar sesi terdampak. |
| K-04 | (a): nomor BPJS dan polis selalu tersamar 4 digit terakhir di semua layar admin. Tanpa izin atau aksi "tampilkan lengkap". |
| K-14 | Default prototipe: verifikasi identitas manual di loket, tanpa kode verifikasi email. |
| Urutan kerja | Domain dulu (service, perintah, policy, bawaan), lalu layar, seeder, dan gerbang. |
| Data pengembangan | Di akhir 7a dijalankan `migrate:fresh --seed` (izin eksplisit diminta saat rencana disetujui), karena `AppointmentSeeder` mengandaikan DB baru (P000009 kini dipakai akun uji `pasien2@`). |

## 3. Cakupan

### 3.1 Masuk 7a: 16 route baru, semuanya di grup admin

| # | Method | URI | Nama | Izin |
|---|---|---|---|---|
| 1 | GET | `/admin/pasien` | `admin.patients.index` | `patients.view` |
| 2 | GET | `/admin/pasien/buat` | `admin.patients.create` | `patients.manage` |
| 3 | POST | `/admin/pasien` | `admin.patients.store` | `patients.manage` |
| 4 | GET | `/admin/pasien/{patient}` | `admin.patients.show` | `patients.view` |
| 5 | GET | `/admin/pasien/{patient}/ubah` | `admin.patients.edit` | `patients.manage` |
| 6 | PUT/PATCH | `/admin/pasien/{patient}` | `admin.patients.update` | `patients.manage` |
| 7 | PATCH | `/admin/pasien/{patient}/status` | `admin.patients.status` | `patients.manage` |
| 8 | POST | `/admin/pasien/{patient}/hubungkan-akun` | `admin.patients.link-account` | `patients.manage` |
| 9 | GET | `/admin/pendaftaran` | `admin.appointments.index` | `appointments.view` |
| 10 | GET | `/admin/pendaftaran/buat` | `admin.appointments.create` | `appointments.manage` |
| 11 | POST | `/admin/pendaftaran` | `admin.appointments.store` | `appointments.manage` |
| 12 | GET | `/admin/pendaftaran/{appointment}` | `admin.appointments.show` | `appointments.view` |
| 13 | POST | `/admin/pendaftaran/{appointment}/status` | `admin.appointments.transition` | `appointments.manage` |
| 14 | GET | `/admin/pendaftaran/{appointment}/bukti` | `admin.appointments.proof` | `appointments.view` |
| 15 | GET | `/admin/antrean` | `admin.queue.index` | `appointments.view` |
| 16 | POST | `/admin/antrean/{session}/tidak-hadir` | `admin.queue.mark-no-show` | `appointments.manage` |

- Sesudah 7a ada **166 route** (150 + 16), **122 di admin** (106 + 16), dan 13 di `/pasien` (tetap).
- Route admin `{appointment}` di-bind lewat `booking_code`, dengan pola regex yang sama dengan route pasien fase 6.
- Penamaan URI mengikuti `Route::resourceVerbs` (`buat`, `ubah`).

Yang juga masuk:
- Perintah `appointments:mark-no-show {--date=}`, terdaftar di scheduler harian pukul 23.30.
- `AppointmentSeeder` beserta 6 profil walk-in P000008–P000013 (13 §10).
- Bawaan fase 6 (§4.6).
- **Tanpa** migration, izin baru, paket baru, atau entri Vite baru. Skema, izin `patients.*`/`appointments.*`, dan entri menu sudah ada sejak fase 1.

### 3.2 Di luar 7a

- **7b:** lihat §2.
- **Fase 8:** ekspor CSV `admin.appointments.export` (`reports.export`). Tombol Ekspor tidak ditampilkan di 7a.
- **Pengembangan lanjutan (00 N):** penggabungan dua profil yang sama-sama memiliki riwayat.

## 4. Komponen domain

### 4.1 `Booking\AppointmentStatusService` (tambahan; tetap satu-satunya jalur tulis status)

- `availableTransitions(Appointment $a, Actor $actor): list<AppointmentStatus>`
  - Pemeriksaan yang ada di `transition()` (tabel `TRANSITIONS`, `RULES`, peran aktor, jendela waktu) dipecah menjadi evaluator yang tidak melempar exception.
  - `transition()` dan `availableTransitions()` memakai evaluator yang sama, sehingga tombol UI tidak bisa berbeda dari aturan server.
- `markNoShowForSession(PracticeSession $s, Actor $actor, ?string $reason = null): int`
  - Bila `now ≤ end` → `SESSION_NOT_ENDED`.
  - Id appointment `pending`/`confirmed` diiterasi, lalu `transition(…, NoShow, …)` dipanggil **satu per satu**. Setiap appointment mendapat transaksinya sendiri dengan urutan kunci baku dan `attempts: 3`. **Tidak ada transaksi luar.**
  - Appointment yang statusnya sudah diubah pihak lain (transisi tak sah atau `STALE_STATUS`) dilewati, tidak dihitung, dan proses tidak gagal.
  - `SYSTEM_BUSY` menghentikan iterasi dan diteruskan ke pemanggil. Proses aman diulang, karena appointment yang sudah `no_show` tidak lagi termasuk.
  - Nilai kembali: jumlah appointment yang benar-benar ditandai.
- `markNoShowForDate(?CarbonInterface $date, Actor $actor): int` menjalankan `markNoShowForSession` untuk setiap sesi yang sudah berakhir dan masih punya appointment `pending`/`confirmed`. Cakupannya sesi pada `$date`, atau semua sesi lampau bila `$date` null (00 F.8).

### 4.2 Perintah `appointments:mark-no-show {--date=}`

- Aktor `Actor::system()`.
- `--date` berformat `Y-m-d`; format salah ditolak dengan pesan dan exit code bukan 0.
- Mencetak jumlah per sesi dan total.
- Didaftarkan di `routes/console.php` harian pukul 23.30 dengan `withoutOverlapping()`, berdampingan dengan `sessions:generate`.

### 4.3 `Patients\PatientRegistrationService` (tambahan)

- `createWalkIn(array $validated, User $staff): PatientProfile`
  - Profil tanpa akun: `registration_channel = loket`, `created_by_user_id = staff`.
  - `consent_at = now()`, `consent_version = privacy.consent_version`. Centang persetujuan lisan wajib di Form Request.
  - `patient_number` dibuat oleh `PatientNumberGenerator` dalam transaksi yang sama (D-145).
  - Audit `patient.created`.
- **Ubah profil oleh petugas:**
  - profil walk-in diperbarui langsung (audit `patient.updated`);
  - profil berakun memakai `updateProfile()` yang sudah ada, sehingga email profil tetap sama dengan `users.email`;
  - email profil berakun tidak dapat diubah dari layar admin.
- `changeStatus(PatientProfile $p, bool $active, Actor $actor, ?string $reason): PatientProfile`
  - Penonaktifan ditolak bila pasien masih punya appointment aktif.
  - Untuk profil berakun, `users.is_active` ikut berubah, dan sesi login pasien dihapus saat dinonaktifkan.
  - Audit `patient.status_changed`.

### 4.4 `Patients\PatientAccountLinker::link(PatientProfile $walkIn, User $account, Actor $actor): PatientProfile`

Sesuai 11 §6.8:
- Satu transaksi; kedua profil dikunci `lockForUpdate` dengan urutan id menaik.
- Ditolak (`ValidationException`) bila:
  - profil walk-in sudah terhubung ke akun;
  - akun bukan akun pasien;
  - akun tidak aktif;
  - profil milik akun sudah punya appointment.
- Profil kosong milik akun dihapus, lalu `user_id`, `email`, dan `is_active` profil walk-in diselaraskan dengan akun.
- Audit `patient.account_linked`, berisi id profil, id akun, petugas, dan nomor pasien profil yang dihapus.

### 4.5 Policy

Izin Spatie dijaga di Policy dan middleware `permission:`. Service tidak memeriksa izin (D-158).
- `PatientProfilePolicy`: `viewAny`, `create`, `changeStatus`, `linkAccount`. `view` dan `update` diperluas untuk staf `patients.*`.
- `AppointmentPolicy`: `viewAny`, `createCounter`, `transition` (`appointments.manage`). `view` sudah melayani staf `appointments.view`, sehingga PAS-04 sisi Petugas tercakup.
- `PracticeSessionPolicy::markNoShow` (`appointments.manage`).

### 4.6 Bawaan fase 6

1. **V-15:** alasan staf 5–255 karakter divalidasi di Form Request (batal, koreksi, nonaktifkan pasien). Alasan opsional yang lebih dari 255 karakter tidak lagi menjadi `QueryException`.
2. **Deadlock metode massal:** tertutup oleh desain §4.1 (tanpa transaksi luar).
3. **`DoctorService::archive`:** retry deadlock (`attempts: 3`). Bila tetap gagal, petugas menerima pesan ramah "sedang sibuk", bukan 500.
4. **Sanitasi global `QueryException`:**
   - Callback `report` di `bootstrap/app.php` mencatat SQLSTATE, kode driver, nama koneksi, dan SQL **tanpa bindings**, lalu menghentikan pelaporan bawaan.
   - Dengan begitu keluhan, alamat, dan nomor BPJS tidak masuk `laravel.log`.
   - Respons ke pengguna tidak berubah.
5. Catatan PAS-04, NTF-07, dan KON-05 di blueprint 15 diperbarui sesuai yang kini teruji.

## 5. Layar dan alur

Ketentuan umum:
- Kerangka admin dan komponen yang sudah ada: `x-data-table`, `x-status-badge`, `x-confirm-modal`, `x-empty-state`, `x-flash-alert`, `x-wizard-steps`, `x-form.*`.
- Semua bekerja tanpa JavaScript wajib. Responsif 360/768/1280; tabel menjadi kartu di bawah 768 px.
- Admin Konten mendapat 403 di semua layar 7a. Menu "Pendaftaran", "Antrean Hari Ini", dan "Pasien" muncul otomatis lewat `config/navigation.php` yang sudah ada.

### 5.1 #37 Pasien

- **Daftar:**
  - satu kotak pencarian untuk nama, no. pasien, telepon, atau tanggal lahir `dd/mm/yyyy`;
  - filter kanal (`online`/`loket`) dan status;
  - kolom: no. pasien, nama, telepon, tanggal lahir, kanal, akun terhubung, status;
  - paginasi.
- **Tambah (walk-in):**
  - field mengikuti W-20 bagian bawah; tanpa NIK dan tanpa akun login;
  - centang "Persetujuan privasi lisan telah dibacakan dan disetujui pasien" wajib;
  - `?kembali=` divalidasi `SafeRedirect`. Bila dibuka dari form walk-in, sesudah simpan pengguna kembali ke form itu dengan `pasien` terisi.
- **Detail:**
  - profil dengan BPJS tersamar, akun terhubung (email) atau "Tanpa akun", dan riwayat pendaftaran terbaru dengan paginasi;
  - tombol: Ubah, **Daftarkan Kunjungan** (`admin.appointments.create?pasien=`), **Hubungkan Akun** (hanya untuk profil tanpa akun), dan Nonaktifkan/Aktifkan (modal beralasan).
- **Hubungkan akun, dua langkah tanpa route tambahan:**
  1. Form GET ke halaman detail dengan `?hubungkan=<email>`.
  2. Halaman detail menampilkan panel konfirmasi yang membandingkan nama, tanggal lahir, dan telepon profil walk-in dengan akun, plus centang wajib "Identitas sudah diverifikasi di loket".
  3. POST `link-account`.
  - Email yang tidak ditemukan, akun nonaktif, atau akun beriwayat ditampilkan sebagai pesan di panel, tanpa membocorkan data akun yang tidak memenuhi syarat.
- **Ubah:** field profil. Untuk profil berakun, email ditampilkan hanya-baca.

### 5.2 #38 Pendaftaran

- **Daftar:**
  - filter tanggal kunjungan (bawaan hari ini, sesuai mitigasi 14), poliklinik, dokter, status, kanal, dan kode/nama;
  - kolom sesuai 05 baris #38 tanpa Ekspor;
  - paginasi.
- **Form walk-in (W-20):**
  - satu halaman bertahap lewat query: `pasien` → `poli`/`dokter`/`tanggal` → `sesi`;
  - jadwal dari `DoctorAvailabilityQuery`, termasuk label "Online ditutup; loket masih bisa" selama `is_bookable_counter`;
  - simpan memanggil `BookingService::book()` dengan `Actor::staff` (kanal loket, status awal `confirmed`);
  - kirim ganda ditahan `Cache::lock` per petugas (pola F6-R9);
  - bila sukses, pengguna diarahkan ke detail dengan flash dan tombol **Cetak Bukti**.
- **Detail:**
  - data booking, riwayat status, dan tombol dari `availableTransitions()`;
  - Batal/Koreksi memakai `x-confirm-modal` dengan alasan wajib.
- **Transisi:**
  - satu `POST {appointment}/status` berisi `to`, `expected`, `reason`, dan `kembali`;
  - `to` hanya boleh `confirmed`, `checked_in`, `completed`, atau `cancelled` (11 `TransitionAppointmentRequest`), karena `no_show` hanya lewat aksi massal atau perintah;
  - `kembali` divalidasi `SafeRedirect` dan hanya boleh menunjuk ke detail atau antrean.
- **Bukti admin:**
  - halaman cetak yang memakai ulang partial bukti fase 6 (QR SVG, BPJS 4 digit);
  - tanpa PDF di sisi admin (F-ADM-16 "cetak").

### 5.3 Antrean Hari Ini (W-19)

- Pilih tanggal (bawaan hari ini) dan sesi. Pilihan sesi menampilkan poliklinik, dokter, jam, dan terisi/kuota.
- Kotak "Cari atau pindai kode booking":
  - `autofocus`, `autocomplete="off"`, dinormalkan `BookingCodeGenerator`, dan kompatibel pemindai USB;
  - bila kode milik sesi lain di tanggal yang sama, halaman pindah ke sesi itu dan barisnya disorot;
  - bila tidak ditemukan, muncul pesan "Kode booking tidak ditemukan".
- Tabel diurutkan menurut nomor antrean, dengan aksi per baris dari `availableTransitions()`. Check-in di luar jendelanya memakai `aria-disabled` dan teks "Dibuka HH.MM".
- Sesudah sesi berakhir muncul banner `role="status"` "N pendaftaran belum check-in" dan tombol **Tandai tidak hadir (N)** dengan modal konfirmasi.
- Tanpa pembaruan otomatis: tombol "Muat ulang" dan keterangan "Dimuat pukul …".

### 5.4 Data demo: `AppointmentSeeder`

- 29 appointment dan 6 profil walk-in, persis 13 §10.
- Dibuat **lewat service** (`BookingService`, `AppointmentStatusService`) di bawah `Carbon::setTestNow()` yang direset di `finally`. Dengan begitu pencacah sesi, riwayat status, audit, dan notifikasi konsisten.
- Pengecualian ketiga di akhir seeder membuat satu sesi `needs_review`. Sesi itu menunggu layar 7b; banner "N sesi perlu ditindaklanjuti" fase 3 sudah menampilkannya.

## 6. Penanganan galat dan konkurensi

### 6.1 Galat

- **`BookingException` di route admin** dipetakan oleh pemetaan admin di `BookingRedirect` (nama method ditetapkan di rencana):
  - galat form walk-in kembali ke langkah terkait dengan input lama, tanpa field sensitif;
  - galat transisi kembali ke `kembali`.
- **`InvalidTransitionException`:** flash `error` berbahasa Indonesia, lalu tombol dihitung ulang.
- **Validasi:** inline di field.
- **`SYSTEM_BUSY`:** "Sistem sedang sibuk", tidak pernah 500.
- **Galat DB tak dikenal:** tetap 500 dengan log tersanitasi (§4.6).

### 6.2 Tes konkurensi

Tes ada di `tests/Feature/Concurrency/` dengan pola fase 6 (D-165): `RefreshDatabase`, `$connectionsToTransact = []`, grup `concurrency`, dan pembersihan `audit_logs`.
- **KON-05 via layar staf:** dua transisi bersamaan; tepat satu menang dan riwayatnya tidak ganda.
- **Tandai tidak hadir massal vs check-in bersamaan:** appointment yang sudah check-in dilewati dan jumlahnya jujur.
- **WLK-02:** walk-in dan booking online berebut kursi terakhir; kuota tidak terlampaui.
- **Dua petugas menghubungkan profil walk-in yang sama:** satu berhasil, satu "sudah terhubung".
- **Arsip dokter vs booking:** dengan retry dan tanpa 500 (bawaan fase 6).

## 7. Pengujian penerimaan

| Kelompok | ID |
|---|---|
| Walk-in dan akun | `WLK-01`–`WLK-07` |
| Booking dan status | `BKG-14`, `BKG-16`, `BKG-19`, `KON-05` (antarmuka staf) |
| Notifikasi | `NTF-03` (transisi staf → pasien berakun; walk-in tanpa notifikasi), `NTF-07` sisi transisi staf |
| Pasien dan bukti | `PAS-04` sisi Petugas, `PAS-06`, `PAS-07` |
| Keamanan | `SEK-15` (nama pasien ber-HTML ter-escape di daftar admin), V-15 |
| Akses | `AKS-03`–`AKS-05` untuk #37/#38 × Super Admin, Petugas, Admin Konten (403), pasien, tamu; `AKS-06` diperbarui (`/admin/pasien` kini 403 untuk pasien, bukan 404) |
| Route | `Phase7aRoutePermissionTest`: 16 route, total 166, admin 122, middleware persis. Asersi angka global `Phase6RoutePermissionTest` dilonggarkan (pola F6-R4). |
| Perintah | `appointments:mark-no-show` dengan dan tanpa `--date`, `--date` salah, dan entri scheduler |

## 8. Gerbang fase 7a

1. `artisan test` penuh hijau (di latar belakang, satu proses PHPUnit), `pint --test` PASS, dan `npm run build` OK.
2. `migrate:fresh --seed` (izin pemilik), lalu pemeriksaan invarian 13 §11. Contohnya I1: `booked_count` = jumlah appointment non-batal per sesi.
3. Uji manual Playwright/DevTools oleh penguji Sonnet tanpa hak ubah kode, ditambah Lighthouse aksesibilitas. Laporan di `Documents\UNPAM-Uji-Manual-<tanggal>-fase-7a\index.html`.
4. Final review dua kursi (produksi; tes dan dokumen), gelombang perbaikan bila perlu, dan re-review terbatas.
5. Rekonsiliasi blueprint (keputusan D-168 dst.), README bagian "Fase 7a", dan catatan eksekusi di rencana.
6. Merge ke `master` atas pilihan pemilik.

## 9. Penyimpangan dari blueprint (direkonsiliasi di tugas dokumen)

| Butir blueprint | Keputusan 7a | Alasan |
|---|---|---|
| 14 §Fase 7 sebagai satu fase | Dipecah 7a + 7b | Ukuran sekitar 1,5× fase 6. Merge lebih kecil dan lebih mudah dijeda. |
| 03 §5: aksi massal mengunci semua profil berurutan lalu sesi | Mengikuti 11 §6.3: satu transaksi per appointment, tanpa transaksi luar; yang berubah dilewati; `SYSTEM_BUSY` menghentikan proses | Tanpa deadlock lintas pasien, retry per appointment berjalan, dan hasilnya idempoten. |
| 11 §6.3 `markNoShowForSession(): int` | Tetap `int`. Jumlah yang dilewati tidak dilaporkan terpisah. | Cukup untuk flash "N ditandai tidak hadir". |
| 14 §Fase 7 risiko "konfirmasi dua langkah" untuk hubungkan akun | Panel konfirmasi lewat `?hubungkan=` di halaman detail, tanpa route GET baru | Jumlah route tetap sesuai 00 I.4. |
| F-ADM-16 bukti admin | Halaman cetak saja, tanpa PDF admin | Sesuai teks "cetak". PDF tetap tersedia bagi pasien. |

## 10. Risiko

| Risiko | Mitigasi |
|---|---|
| Salah menghubungkan akun ke profil orang lain | Tanpa pencocokan otomatis, panel perbandingan identitas, centang verifikasi wajib, dan audit lengkap |
| Antrean lambat untuk sesi besar | Kueri per sesi dengan index `practice_session_id`, `queue_number`; daftar pendaftaran dipaginasi dengan filter bawaan hari ini |
| Tombol UI menyimpang dari aturan server | Satu evaluator untuk `transition()` dan `availableTransitions()` |
| Seeder menghasilkan data tidak konsisten | Seeder hanya lewat service; invarian 13 §11 diperiksa di gerbang |
| Lingkungan MariaDB rusak lagi | MySQL dinyalakan dan dimatikan dengan `scripts\mysql-start.ps1`/`mysql-stop.ps1` (16 §1.3) |
