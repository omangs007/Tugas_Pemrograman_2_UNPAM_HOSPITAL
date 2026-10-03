# 11 — Route, Controller, Model, Service, Middleware, dan Policy

> **Tujuan bagian.** Merinci kerangka kode aplikasi di atas pohon folder [10-struktur-folder.md](10-struktur-folder.md): tabel seluruh route (nama persis 00 §I), `bootstrap/app.php`, controller tipis, model (fillable, cast, relasi, scope, accessor), service beserta pseudo-code, middleware, policy, Form Request, notifikasi, perintah Artisan, rate limiter, dan penanganan galat. Ini bagian yang langsung dikerjakan developer saat menulis kode; aturan field validasi rinci ada di [12-aturan-bisnis-validasi-keamanan.md](12-aturan-bisnis-validasi-keamanan.md), skema tabel di [09-data-dictionary.md](09-data-dictionary.md).
>
> **Bagian terkait:** [04-peran-hak-akses.md](04-peran-hak-akses.md) (matriks peran), [05-sitemap-matriks-halaman.md](05-sitemap-matriks-halaman.md), [06-alur.md](06-alur.md), [08-erd.md](08-erd.md), [13-data-contoh.md](13-data-contoh.md), [14-tahapan-implementasi.md](14-tahapan-implementasi.md), [15-pengujian-penerimaan.md](15-pengujian-penerimaan.md). Sumber kebenaran: [00-keputusan-inti.md](00-keputusan-inti.md) (§D enum, §E tabel, §F mesin booking, §G izin, §H auth, §I route, §J komponen).
>
> **Penanda:** **[ASUMSI]** = asumsi baru; **[KONFIRMASI]** = perlu keputusan pemilik. Semua nama yang tidak ada di 00 dikumpulkan pada *Nama baru yang diusulkan* di akhir.

---

## 1. Konvensi Routing dan Otorisasi

**Tiga lapis penegakan hak akses (00 G):** (1) middleware route `permission:*`, (2) Policy per model dipanggil di controller (`$this->authorize(...)`), (3) `authorize()` pada Form Request. Menu `@can` hanya kenyamanan.

**Grup middleware (disingkat di tabel):**

| Singkatan | Isi | Dipakai di |
|---|---|---|
| `web` | Grup bawaan Laravel (session, cookie, CSRF `VerifyCsrfToken`, `SubstituteBindings`) + `SecurityHeaders` (global) | Semua route; **tidak diulang** di kolom Middleware |
| `G-PASIEN` | `auth`, `account:patient`, `active` | `routes/patient.php` |
| `G-ADMIN` | `auth`, `account:staff`, `active`, `password.changed` | `routes/admin.php` (kecuali `admin.login*`, `admin.logout`, `admin.password.*`) |
| `guest` | Hanya tamu; pengguna login dialihkan ke dashboard sesuai tipe akun (lihat §3.1 `redirectUsersTo`) | Login, registrasi, reset |
| `throttle:{nama}` | Rate limiter bernama (§10.3) | Lihat kolom Middleware |

**Aturan implementasi route:**

1. **Resource Indonesia:** `Route::resourceVerbs(['create' => 'buat', 'edit' => 'ubah'])` diset di `AppServiceProvider::boot()`; setiap `Route::resource('dokter', DoctorController::class)` diberi `->names('doctors')` (menghasilkan `admin.doctors.*` bersama `->name('admin.')` grup) dan `->parameters(['dokter' => 'doctor'])`. Peta segmen URL → nama parameter → model ada di §2.4.
2. **Urutan pendaftaran:** route statis (`buat`, `ekspor`, `sesi`, `sesi-terdampak`, `pengecualian`, `urutkan`, `editor`) didaftarkan **sebelum** route berparameter pada awalan yang sama, dan parameter admin berbasis ID dibatasi angka lewat `->where([...])` pada grup admin di `bootstrap/app.php` (§3.1; tidak memakai `Route::pattern` global karena nama `{doctor}`, `{clinic}`, dst. juga dipakai route publik berbasis slug). Untuk sumber daya yang izin lihat ≠ izin kelola (mis. `doctors.view` vs `doctors.manage`), resource dideklarasikan dua kali: `except(['index','show'])` (ditambah `'destroy'` bila resource tidak boleh dihapus, mis. dokter, §3.4) di grup `permission:*.manage` **lebih dulu**, lalu `only(['index','show'])` di grup `permission:*.view`, supaya `/admin/dokter/buat` tidak tertangkap `{doctor}`.
3. **Parameter pasien:** `patient.appointments.*` memakai `{appointment:booking_code}` (kolom `booking_code`), dibatasi regex `UPH-[0-9A-HJKMNP-TV-Z]{4}-[0-9A-HJKMNP-TV-Z]{4}`; route `/pasien/pendaftaran/baru` didaftarkan lebih dulu. Route admin memakai `{appointment}` berbasis `id`.
4. **Binding admin:** `{user}` pada `admin.staff.*` di-bind khusus (`Route::bind('user', …)`) hanya ke `users` dengan `account_type = staff` sehingga akun pasien tidak dapat diakses lewat URL staf (→ 404). `{role}` ke `Spatie\Permission\Models\Role`.
5. **Otorisasi eksplisit di controller:** `Controller` dasar memakai trait `AuthorizesRequests`; setiap method memanggil `$this->authorize('ability', $model|Model::class)`. Format kolom *Policy/ability*: `NamaPolicy::method`.
6. **Respons mutasi:** semua POST/PUT/PATCH/DELETE HTML berakhir `redirect()` (pola PRG) dengan flash `success`/`error`; endpoint JSON hanya `admin.dashboard.chart` dan `admin.media.*`.
7. **Method spoofing:** form HTML memakai `@method('PUT'|'PATCH'|'DELETE')`; kolom Method di bawah menuliskan verba HTTP sebenarnya.

---

## 2. Tabel Route Lengkap

Kolom **Form Request** berisi kelas di `app/Http/Requests/{Area}` (daftar & aturan ringkas di §9). `—` = tidak ada body/filter yang perlu divalidasi. Kolom **Policy/izin** untuk route publik berisi aturan visibilitas data (bukan Policy).

### 2.1 `routes/web.php` — publik dan autentikasi pasien (31 route)

**Publik** (namespace `Public`; middleware hanya `web`):

| Method | URI | Nama route | Controller@method | Middleware | Form Request | Policy/izin |
|---|---|---|---|---|---|---|
| GET | `/` | `home` | `HomeController@index` | — | — | Publik. Memanggil `PracticeSessionGenerator::ensureDailyHorizon()` |
| GET | `/profil` | `about` | `PageController@about` | — | — | Publik; halaman `pages.key=about` |
| GET | `/visi-misi` | `vision-mission` | `PageController@visionMission` | — | — | Publik; `vision-mission` |
| GET | `/layanan` | `clinics.index` | `ClinicController@index` | — | — | Publik; hanya `Clinic::active()` |
| GET | `/layanan/{clinic:slug}` | `clinics.show` | `ClinicController@show` | — | — | Publik; 404 bila `is_active=false` |
| GET | `/dokter` | `doctors.index` | `DoctorController@index` | — | `Public\DoctorIndexRequest` | Publik; hanya `Doctor::active()`; filter `q`, `spesialisasi`, `poli`, `tanggal` |
| GET | `/dokter/{doctor:slug}` | `doctors.show` | `DoctorController@show` | — | — | Publik; 404 bila status ≠ `active` |
| GET | `/jadwal-dokter` | `schedules.index` | `ScheduleController@index` | — | `Public\ScheduleIndexRequest` | Publik; filter `minggu`, `poli`, `dokter`; `ensureDailyHorizon()` |
| GET | `/fasilitas` | `facilities.index` | `FacilityController@index` | — | — | Publik; hanya aktif |
| GET | `/fasilitas/{facility:slug}` | `facilities.show` | `FacilityController@show` | — | — | Publik; 404 bila nonaktif |
| GET | `/kamar-rawat-inap` | `rooms.index` | `RoomCategoryController@index` | — | — | Publik; hanya aktif; tampilkan `availability_updated_at` |
| GET | `/kamar-rawat-inap/{roomCategory:slug}` | `rooms.show` | `RoomCategoryController@show` | — | — | Publik; 404 bila nonaktif |
| GET | `/bpjs-asuransi` | `insurance` | `PageController@insurance` | — | — | Publik; halaman `insurance-info` + `InsurancePartner::active()` |
| GET | `/panduan-pendaftaran` | `registration-guide` | `PageController@registrationGuide` | — | — | Publik |
| GET | `/artikel` | `articles.index` | `ArticleController@index` | — | `Public\ArticleIndexRequest` | Publik; hanya `Article::published()`; filter `kategori`, `q` |
| GET | `/artikel/{article:slug}` | `articles.show` | `ArticleController@show` | — | — | Publik; 404 bila bukan `published` atau `published_at` > sekarang |
| GET | `/galeri` | `galleries.index` | `GalleryController@index` | — | — | Publik; hanya `is_published` |
| GET | `/galeri/{gallery:slug}` | `galleries.show` | `GalleryController@show` | — | — | Publik; 404 bila belum terbit |
| GET | `/faq` | `faq` | `FaqController@index` | — | — | Publik; `Faq::published()` dikelompokkan per `category` |
| GET | `/kontak` | `contact` | `ContactController@show` | — | — | Publik; `<x-map-embed>` |
| POST | `/kontak` | `contact.store` | `ContactController@store` | `throttle:contact` | `Public\StoreContactMessageRequest` | Publik; menyimpan `contact_messages` (`ip_hash`, `consent_at`) |
| GET | `/kebijakan-privasi` | `privacy` | `PageController@privacy` | — | — | Publik; `privacy-policy` |

**Autentikasi pasien** (namespace `Auth`):

| Method | URI | Nama route | Controller@method | Middleware | Form Request | Policy/izin |
|---|---|---|---|---|---|---|
| GET | `/daftar` | `register` | `RegisterController@create` | `guest` | — | Tamu |
| POST | `/daftar` | `register.store` | `RegisterController@store` | `guest`, `throttle:register` | `Auth\RegisterRequest` | Tamu; `PatientRegistrationService::register()`; lalu login otomatis → `patient.dashboard` |
| GET | `/masuk` | `login` | `LoginController@create` | `guest` | — | Tamu |
| POST | `/masuk` | `login.store` | `LoginController@store` | `guest`, `throttle:login` | `Auth\LoginRequest` | Tamu; hanya `account_type=patient` (00 H) |
| POST | `/keluar` | `logout` | `LoginController@destroy` | `auth` | — | Pengguna login |
| GET | `/lupa-password` | `password.request` | `PasswordResetLinkController@create` | `guest` | — | Tamu |
| POST | `/lupa-password` | `password.email` | `PasswordResetLinkController@store` | `guest`, `throttle:password-reset` | `Auth\ForgotPasswordRequest` | Tamu; respons selalu generik |
| GET | `/reset-password/{token}` | `password.reset` | `NewPasswordController@create` | `guest` | — | Tamu |
| POST | `/reset-password` | `password.update` | `NewPasswordController@store` | `guest`, `throttle:password-reset` | `Auth\ResetPasswordRequest` | Tamu |

### 2.2 `routes/patient.php` — portal pasien (13 route)

Prefix `/pasien`, nama `patient.`, middleware `G-PASIEN`. Route statis `baru` lebih dulu dari `{appointment}`.

| Method | URI | Nama route | Controller@method | Middleware | Form Request | Policy/izin |
|---|---|---|---|---|---|---|
| GET | `/pasien` | `patient.dashboard` | `Patient\DashboardController@index` | G-PASIEN | — | Milik sendiri (profil dari `auth()->user()->patientProfile`) |
| GET | `/pasien/profil` | `patient.profile.edit` | `Patient\ProfileController@edit` | G-PASIEN | — | `PatientProfilePolicy::view` |
| PUT | `/pasien/profil` | `patient.profile.update` | `Patient\ProfileController@update` | G-PASIEN | `Patient\UpdatePatientProfileRequest` | `PatientProfilePolicy::update` |
| PUT | `/pasien/password` | `patient.password.update` | `Patient\PasswordController@update` | G-PASIEN | `Patient\UpdatePasswordRequest` | Akun sendiri |
| GET | `/pasien/pendaftaran/baru` | `patient.appointments.create` | `Patient\AppointmentController@create` | G-PASIEN | `Patient\BookingWizardQueryRequest` (query `poli`, `dokter`, `sesi`, `tanggal`) | `AppointmentPolicy::createOnline` |
| POST | `/pasien/pendaftaran/tinjau` | `patient.appointments.review` | `Patient\AppointmentController@review` | G-PASIEN | `Patient\ReviewAppointmentRequest` | `AppointmentPolicy::createOnline` |
| POST | `/pasien/pendaftaran` | `patient.appointments.store` | `Patient\AppointmentController@store` | G-PASIEN, `throttle:booking` | `Patient\StoreAppointmentRequest` | `AppointmentPolicy::createOnline`; `BookingService::book()` |
| GET | `/pasien/pendaftaran` | `patient.appointments.index` | `Patient\AppointmentController@index` | G-PASIEN | `ListFilterRequest` (query `jadwal_berubah`, `page`) | `Appointment::forPatient()`; `AppointmentPolicy::viewAny` tidak dipakai (daftar otomatis milik sendiri); `jadwal_berubah=1` → hanya appointment dengan `schedule_changed_at` terisi (tautan dari banner notifikasi dashboard, 00 I.5); nilai lain diabaikan |
| GET | `/pasien/pendaftaran/{appointment:booking_code}` | `patient.appointments.show` | `Patient\AppointmentController@show` | G-PASIEN | — | `AppointmentPolicy::view` |
| POST | `/pasien/pendaftaran/{appointment:booking_code}/batal` | `patient.appointments.cancel` | `Patient\AppointmentController@cancel` | G-PASIEN | `Patient\CancelAppointmentRequest` | `AppointmentPolicy::cancel` |
| GET | `/pasien/pendaftaran/{appointment:booking_code}/bukti` | `patient.appointments.proof` | `Patient\AppointmentController@proof` | G-PASIEN | — | `AppointmentPolicy::view` |
| GET | `/pasien/pendaftaran/{appointment:booking_code}/bukti.pdf` | `patient.appointments.proof-pdf` | `Patient\AppointmentController@proofPdf` | G-PASIEN | — | `AppointmentPolicy::view` |
| POST | `/pasien/notifikasi/{id}/baca` | `patient.notifications.read` | `Patient\NotificationController@read` | G-PASIEN | — | Hanya notifikasi milik sendiri (`$user->notifications()->findOrFail($id)`) |

Catatan: `patient.appointments.store` mengalirkan galat bisnis (`BookingException`) ke halaman wizard `patient.appointments.create?sesi={id}` (bukan `back()`), karena halaman *review* adalah hasil POST yang tidak bisa dimuat ulang lewat GET (lihat §11.3).

---
### 2.3 `routes/admin.php` — dashboard admin (139 route)

Prefix `/admin`, nama `admin.`. Namespace controller `Admin` kecuali dinyatakan lain. Kolom Middleware menulis **hanya tambahan** setelah `G-ADMIN` (`permission:*` = middleware Spatie; `a|b` berarti salah satu izin cukup). Resource tanpa `show` dideklarasikan `->except(['show'])`; resource tanpa `destroy` memakai arsip/nonaktif (00 C: data yang dirujuk riwayat tidak dihapus). **[ASUMSI]** daftar aksi per resource di bawah, karena 00 hanya menulis "resource" untuk sebagian besar entitas.

**A. Login, logout, ganti password (5 route)** — middleware khusus, bukan `G-ADMIN` penuh:

| Method | URI | Nama route | Controller@method | Middleware | Form Request | Policy/izin |
|---|---|---|---|---|---|---|
| GET | `/admin/login` | `admin.login` | `Auth\AdminLoginController@create` | `guest` | — | Tamu |
| POST | `/admin/login` | `admin.login.store` | `Auth\AdminLoginController@store` | `guest`, `throttle:login` | `Auth\AdminLoginRequest` | Tamu; hanya `account_type=staff` & `is_active` |
| POST | `/admin/logout` | `admin.logout` | `Auth\AdminLoginController@destroy` | `auth`, `account:staff` | — | Staf login |
| GET | `/admin/ganti-password` | `admin.password.edit` | `Auth\AdminPasswordController@edit` | `auth`, `account:staff`, `active` (**tanpa** `password.changed` agar tidak berulang) | — | Akun sendiri |
| PUT | `/admin/ganti-password` | `admin.password.update` | `Auth\AdminPasswordController@update` | `auth`, `account:staff`, `active` | `Admin\UpdatePasswordRequest` | Akun sendiri; menyetel `must_change_password=false` |

**B. Dashboard (2 route)**

| Method | URI | Nama route | Controller@method | Middleware | Form Request | Policy/izin |
|---|---|---|---|---|---|---|
| GET | `/admin` | `admin.dashboard` | `DashboardController@index` | `permission:dashboard.view` | `Admin\DashboardChartRequest` (filter awal) | Widget disaring izin (daftar bernama pasien hanya `appointments.view`) |
| GET | `/admin/dashboard/grafik` | `admin.dashboard.chart` | `DashboardController@chart` | `permission:dashboard.view` | `Admin\DashboardChartRequest` | Respons JSON |

**C. Dokter (9 route)** — `/admin/dokter`, parameter `{doctor}`

| Method | URI | Nama route | Controller@method | Middleware | Form Request | Policy/izin |
|---|---|---|---|---|---|---|
| GET | `/admin/dokter/buat` | `admin.doctors.create` | `DoctorController@create` | `permission:doctors.manage` | — | `DoctorPolicy::create` |
| POST | `/admin/dokter` | `admin.doctors.store` | `DoctorController@store` | `permission:doctors.manage` | `StoreDoctorRequest` | `DoctorPolicy::create` |
| GET | `/admin/dokter/{doctor}/ubah` | `admin.doctors.edit` | `DoctorController@edit` | `permission:doctors.manage` | — | `DoctorPolicy::update` |
| PUT/PATCH | `/admin/dokter/{doctor}` | `admin.doctors.update` | `DoctorController@update` | `permission:doctors.manage` | `UpdateDoctorRequest` | `DoctorPolicy::update` |
| PATCH | `/admin/dokter/{doctor}/status` | `admin.doctors.status` | `DoctorController@status` | `permission:doctors.manage` | `UpdateDoctorStatusRequest` | `DoctorPolicy::changeStatus` |
| PATCH | `/admin/dokter/{doctor}/arsip` | `admin.doctors.archive` | `DoctorController@archive` | `permission:doctors.manage` | `ArchiveDoctorRequest` | `DoctorPolicy::archive` |
| POST | `/admin/dokter/{doctor}/foto` | `admin.doctors.photo` | `DoctorController@photo` | `permission:doctors.manage` | `UploadDoctorPhotoRequest` | `DoctorPolicy::update` |
| GET | `/admin/dokter` | `admin.doctors.index` | `DoctorController@index` | `permission:doctors.view` | `ListFilterRequest` | `DoctorPolicy::viewAny` |
| GET | `/admin/dokter/{doctor}` | `admin.doctors.show` | `DoctorController@show` | `permission:doctors.view` | — | `DoctorPolicy::view` |

**D. Spesialisasi dan poliklinik (12 route)** — izin `clinics.manage`; resource `->except(['show'])`

| Method | URI | Nama route | Controller@method | Middleware | Form Request | Policy/izin |
|---|---|---|---|---|---|---|
| GET | `/admin/spesialisasi` | `admin.specialties.index` | `SpecialtyController@index` | `permission:clinics.manage` | `ListFilterRequest` | `SpecialtyPolicy::viewAny` |
| GET | `/admin/spesialisasi/buat` | `admin.specialties.create` | `SpecialtyController@create` | `permission:clinics.manage` | — | `SpecialtyPolicy::create` |
| POST | `/admin/spesialisasi` | `admin.specialties.store` | `SpecialtyController@store` | `permission:clinics.manage` | `SpecialtyRequest` | `SpecialtyPolicy::create` |
| GET | `/admin/spesialisasi/{specialty}/ubah` | `admin.specialties.edit` | `SpecialtyController@edit` | `permission:clinics.manage` | — | `SpecialtyPolicy::update` |
| PUT/PATCH | `/admin/spesialisasi/{specialty}` | `admin.specialties.update` | `SpecialtyController@update` | `permission:clinics.manage` | `SpecialtyRequest` | `SpecialtyPolicy::update` |
| DELETE | `/admin/spesialisasi/{specialty}` | `admin.specialties.destroy` | `SpecialtyController@destroy` | `permission:clinics.manage` | — | `SpecialtyPolicy::delete`; ditolak (flash `error`) bila masih dipakai dokter/poliklinik |
| GET | `/admin/poliklinik` | `admin.clinics.index` | `ClinicController@index` | `permission:clinics.manage` | `ListFilterRequest` | `ClinicPolicy::viewAny` |
| GET | `/admin/poliklinik/buat` | `admin.clinics.create` | `ClinicController@create` | `permission:clinics.manage` | — | `ClinicPolicy::create` |
| POST | `/admin/poliklinik` | `admin.clinics.store` | `ClinicController@store` | `permission:clinics.manage` | `ClinicRequest` | `ClinicPolicy::create` |
| GET | `/admin/poliklinik/{clinic}/ubah` | `admin.clinics.edit` | `ClinicController@edit` | `permission:clinics.manage` | — | `ClinicPolicy::update` |
| PUT/PATCH | `/admin/poliklinik/{clinic}` | `admin.clinics.update` | `ClinicController@update` | `permission:clinics.manage` | `ClinicRequest` | `ClinicPolicy::update` |
| DELETE | `/admin/poliklinik/{clinic}` | `admin.clinics.destroy` | `ClinicController@destroy` | `permission:clinics.manage` | — | `ClinicPolicy::delete`; ditolak bila dirujuk dokter/jadwal/sesi/appointment (nonaktifkan saja) |

**E. Jadwal: templat, sesi, pengecualian, sesi terdampak (21 route)**

Urutan pendaftaran di file: (1) `sesi-terdampak`, (2) `sesi`, (3) `pengecualian`, (4) resource `jadwal` templat.

| Method | URI | Nama route | Controller@method | Middleware | Form Request | Policy/izin |
|---|---|---|---|---|---|---|
| GET | `/admin/jadwal/sesi-terdampak` | `admin.sessions.review` | `SessionReviewController@index` | `permission:sessions.review` | — | `PracticeSessionPolicy::review` |
| POST | `/admin/jadwal/sesi-terdampak/{session}` | `admin.sessions.resolve` | `SessionReviewController@resolve` | `permission:sessions.review` | `ResolveSessionRequest` | `PracticeSessionPolicy::resolve`; `ScheduleChangeService::resolve()` |
| GET | `/admin/jadwal/sesi` | `admin.sessions.index` | `PracticeSessionController@index` | `permission:schedules.view` | `PracticeSessionIndexRequest` | `PracticeSessionPolicy::viewAny` (admin konten hanya melihat `booked_count`, bukan nama pasien) |
| GET | `/admin/jadwal/sesi/buat` | `admin.sessions.create` | `PracticeSessionController@create` | `permission:schedules.manage` | — | `PracticeSessionPolicy::create` |
| POST | `/admin/jadwal/sesi` | `admin.sessions.store` | `PracticeSessionController@store` | `permission:schedules.manage` | `PracticeSessionRequest` | `PracticeSessionPolicy::create` (sesi tambahan manual, `doctor_schedule_id` NULL) |
| GET | `/admin/jadwal/sesi/{session}/ubah` | `admin.sessions.edit` | `PracticeSessionController@edit` | `permission:schedules.manage` | — | `PracticeSessionPolicy::update` |
| PUT/PATCH | `/admin/jadwal/sesi/{session}` | `admin.sessions.update` | `PracticeSessionController@update` | `permission:schedules.manage` | `PracticeSessionRequest` | `PracticeSessionPolicy::update`; menyetel `is_manually_modified=true`; kuota ≥ `booked_count`; jam berubah pada sesi ber-appointment aktif → `needs_review` sumber `manual_time` |
| PATCH | `/admin/jadwal/sesi/{session}/status` | `admin.sessions.status` | `PracticeSessionController@status` | `permission:schedules.manage` | `UpdateSessionStatusRequest` | `PracticeSessionPolicy::changeStatus` (buka/tutup langsung; batalkan: langsung bila tanpa appointment aktif, selain itu `needs_review` sumber `manual_cancel` dengan alasan wajib; membuka sesi dokter non-`active` ditolak) |
| DELETE | `/admin/jadwal/sesi/{session}` | `admin.sessions.destroy` | `PracticeSessionController@destroy` | `permission:schedules.manage` | — | `PracticeSessionPolicy::delete` (ability baru; route 00 I.4, aturan 00 C): hanya sesi tambahan manual (`doctor_schedule_id` NULL) **tanpa appointment sama sekali**; selain itu ditolak dengan pesan alasan dan tombol diganti "Tutup/Batalkan" (`admin.sessions.status`); `ScheduleChangeService::deleteManualSession()` + audit `session.deleted` |
| GET | `/admin/jadwal/pengecualian` | `admin.exceptions.index` | `ScheduleExceptionController@index` | `permission:schedules.view` | `ListFilterRequest` | `ScheduleExceptionPolicy::viewAny` |
| GET | `/admin/jadwal/pengecualian/buat` | `admin.exceptions.create` | `ScheduleExceptionController@create` | `permission:schedules.manage` | — | `ScheduleExceptionPolicy::create` |
| POST | `/admin/jadwal/pengecualian` | `admin.exceptions.store` | `ScheduleExceptionController@store` | `permission:schedules.manage` | `ScheduleExceptionRequest` | `ScheduleExceptionPolicy::create`; `ScheduleChangeService::applyException()` |
| GET | `/admin/jadwal/pengecualian/{exception}/ubah` | `admin.exceptions.edit` | `ScheduleExceptionController@edit` | `permission:schedules.manage` | — | `ScheduleExceptionPolicy::update` |
| PUT/PATCH | `/admin/jadwal/pengecualian/{exception}` | `admin.exceptions.update` | `ScheduleExceptionController@update` | `permission:schedules.manage` | `ScheduleExceptionRequest` | `ScheduleExceptionPolicy::update`; `start_date` tidak boleh masa lalu |
| DELETE | `/admin/jadwal/pengecualian/{exception}` | `admin.exceptions.destroy` | `ScheduleExceptionController@destroy` | `permission:schedules.manage` | — | `ScheduleExceptionPolicy::delete`; `ScheduleChangeService::removeException()` lebih dulu |
| GET | `/admin/jadwal` | `admin.schedules.index` | `DoctorScheduleController@index` | `permission:schedules.view` | `ListFilterRequest` | `DoctorSchedulePolicy::viewAny` |
| GET | `/admin/jadwal/buat` | `admin.schedules.create` | `DoctorScheduleController@create` | `permission:schedules.manage` | — | `DoctorSchedulePolicy::create` |
| POST | `/admin/jadwal` | `admin.schedules.store` | `DoctorScheduleController@store` | `permission:schedules.manage` | `DoctorScheduleRequest` | `DoctorSchedulePolicy::create`; lalu `PracticeSessionGenerator::ensureRange()` |
| GET | `/admin/jadwal/{schedule}/ubah` | `admin.schedules.edit` | `DoctorScheduleController@edit` | `permission:schedules.manage` | — | `DoctorSchedulePolicy::update` |
| PUT/PATCH | `/admin/jadwal/{schedule}` | `admin.schedules.update` | `DoctorScheduleController@update` | `permission:schedules.manage` | `DoctorScheduleRequest` | `DoctorSchedulePolicy::update`; `ScheduleChangeService::applyTemplateChange()` |
| DELETE | `/admin/jadwal/{schedule}` | `admin.schedules.destroy` | `DoctorScheduleController@destroy` | `permission:schedules.manage` | — | `DoctorSchedulePolicy::delete`; hanya bila **tidak ada sesi ber-appointment** (00 C; sesi tanpa appointment milik templat ikut dihapus lebih dulu oleh `ScheduleChangeService::deleteTemplate()`); bila ada, ditolak dan tombol diganti "Nonaktifkan" |

Catatan E: sesi tidak memiliki `show`; `destroy` sesi hanya untuk sesi tambahan manual tanpa appointment (00 C; sesi lain ditutup/dibatalkan lewat `admin.sessions.status` karena dirujuk `appointments`); `admin.sessions.review` dan `admin.sessions.resolve` memakai izin `sessions.review` (bukan `schedules.*`), sehingga Admin Konten tidak melihatnya (00 G).

**F. Fasilitas, kategori kamar, kapasitas (13 route)** — fasilitas 6 + kategori kamar 7

| Method | URI | Nama route | Controller@method | Middleware | Form Request | Policy/izin |
|---|---|---|---|---|---|---|
| GET | `/admin/fasilitas` | `admin.facilities.index` | `FacilityController@index` | `permission:facilities.manage` | `ListFilterRequest` | `FacilityPolicy::viewAny` |
| GET | `/admin/fasilitas/buat` | `admin.facilities.create` | `FacilityController@create` | `permission:facilities.manage` | — | `FacilityPolicy::create` |
| POST | `/admin/fasilitas` | `admin.facilities.store` | `FacilityController@store` | `permission:facilities.manage` | `FacilityRequest` | `FacilityPolicy::create` |
| GET | `/admin/fasilitas/{facility}/ubah` | `admin.facilities.edit` | `FacilityController@edit` | `permission:facilities.manage` | — | `FacilityPolicy::update` |
| PUT/PATCH | `/admin/fasilitas/{facility}` | `admin.facilities.update` | `FacilityController@update` | `permission:facilities.manage` | `FacilityRequest` | `FacilityPolicy::update` |
| DELETE | `/admin/fasilitas/{facility}` | `admin.facilities.destroy` | `FacilityController@destroy` | `permission:facilities.manage` | — | `FacilityPolicy::delete`; menghapus media terkait via `MediaService` |
| GET | `/admin/kamar` | `admin.room-categories.index` | `RoomCategoryController@index` | `permission:facilities.manage\|rooms.update-availability` | `ListFilterRequest` | `RoomCategoryPolicy::viewAny` |
| GET | `/admin/kamar/buat` | `admin.room-categories.create` | `RoomCategoryController@create` | `permission:facilities.manage` | — | `RoomCategoryPolicy::create` |
| POST | `/admin/kamar` | `admin.room-categories.store` | `RoomCategoryController@store` | `permission:facilities.manage` | `RoomCategoryRequest` | `RoomCategoryPolicy::create` |
| GET | `/admin/kamar/{roomCategory}/ubah` | `admin.room-categories.edit` | `RoomCategoryController@edit` | `permission:facilities.manage` | — | `RoomCategoryPolicy::update` |
| PUT/PATCH | `/admin/kamar/{roomCategory}` | `admin.room-categories.update` | `RoomCategoryController@update` | `permission:facilities.manage` | `RoomCategoryRequest` | `RoomCategoryPolicy::update` |
| DELETE | `/admin/kamar/{roomCategory}` | `admin.room-categories.destroy` | `RoomCategoryController@destroy` | `permission:facilities.manage` | — | `RoomCategoryPolicy::delete` |
| PATCH | `/admin/kamar/{roomCategory}/ketersediaan` | `admin.room-categories.availability` | `RoomCategoryController@availability` | `permission:rooms.update-availability` | `UpdateRoomAvailabilityRequest` | `RoomCategoryPolicy::updateAvailability`; menyetel `availability_updated_at/by` |

**G. Pasien (8 route)** — `/admin/pasien`, parameter `{patient}` (model `PatientProfile`)

| Method | URI | Nama route | Controller@method | Middleware | Form Request | Policy/izin |
|---|---|---|---|---|---|---|
| GET | `/admin/pasien/buat` | `admin.patients.create` | `PatientController@create` | `permission:patients.manage` | — | `PatientProfilePolicy::create` |
| POST | `/admin/pasien` | `admin.patients.store` | `PatientController@store` | `permission:patients.manage` | `StorePatientRequest` | `PatientProfilePolicy::create`; `PatientRegistrationService::createWalkIn()` |
| GET | `/admin/pasien/{patient}/ubah` | `admin.patients.edit` | `PatientController@edit` | `permission:patients.manage` | — | `PatientProfilePolicy::update` |
| PUT/PATCH | `/admin/pasien/{patient}` | `admin.patients.update` | `PatientController@update` | `permission:patients.manage` | `UpdatePatientRequest` | `PatientProfilePolicy::update` |
| PATCH | `/admin/pasien/{patient}/status` | `admin.patients.status` | `PatientController@status` | `permission:patients.manage` | `UpdatePatientStatusRequest` | `PatientProfilePolicy::changeStatus` |
| POST | `/admin/pasien/{patient}/hubungkan-akun` | `admin.patients.link-account` | `PatientController@linkAccount` | `permission:patients.manage` | `LinkPatientAccountRequest` | `PatientProfilePolicy::linkAccount`; `PatientAccountLinker::link()` |
| GET | `/admin/pasien` | `admin.patients.index` | `PatientController@index` | `permission:patients.view` | `ListFilterRequest` | `PatientProfilePolicy::viewAny` |
| GET | `/admin/pasien/{patient}` | `admin.patients.show` | `PatientController@show` | `permission:patients.view` | — | `PatientProfilePolicy::view` |

**H. Pendaftaran dan antrean (9 route)**

Urutan di file: `buat` dan `ekspor` sebelum `{appointment}`; parameter `{appointment}` dibatasi angka.

| Method | URI | Nama route | Controller@method | Middleware | Form Request | Policy/izin |
|---|---|---|---|---|---|---|
| GET | `/admin/pendaftaran/buat` | `admin.appointments.create` | `AppointmentController@create` | `permission:appointments.manage` | `CreateWalkInAppointmentRequest` (query `pasien`, `sesi`, `tanggal`, `poli`, `dokter`, `kembali`) | `AppointmentPolicy::create` (walk-in; memakai `DoctorAvailabilityQuery`) |
| GET | `/admin/pendaftaran/ekspor` | `admin.appointments.export` | `AppointmentController@export` | `permission:reports.export` | `ExportAppointmentsRequest` | `AppointmentPolicy::export`; `AppointmentCsvExporter::stream()` |
| POST | `/admin/pendaftaran` | `admin.appointments.store` | `AppointmentController@store` | `permission:appointments.manage` | `StoreWalkInAppointmentRequest` | `AppointmentPolicy::create`; `BookingService::book()` kanal `loket` |
| GET | `/admin/pendaftaran` | `admin.appointments.index` | `AppointmentController@index` | `permission:appointments.view` | `AppointmentIndexRequest` | `AppointmentPolicy::viewAny` |
| GET | `/admin/pendaftaran/{appointment}` | `admin.appointments.show` | `AppointmentController@show` | `permission:appointments.view` | — | `AppointmentPolicy::view` |
| POST | `/admin/pendaftaran/{appointment}/status` | `admin.appointments.transition` | `AppointmentController@transition` | `permission:appointments.manage` | `TransitionAppointmentRequest` | `AppointmentPolicy::transition`; `AppointmentStatusService::transition()` |
| GET | `/admin/pendaftaran/{appointment}/bukti` | `admin.appointments.proof` | `AppointmentController@proof` | `permission:appointments.view` | — | `AppointmentPolicy::view` |
| GET | `/admin/antrean` | `admin.queue.index` | `QueueController@index` | `permission:appointments.view` | `QueueIndexRequest` | `AppointmentPolicy::viewAny` |
| POST | `/admin/antrean/{session}/tidak-hadir` | `admin.queue.mark-no-show` | `QueueController@markNoShow` | `permission:appointments.manage` | `MarkNoShowRequest` | `PracticeSessionPolicy::markNoShow`; `AppointmentStatusService::markNoShowForSession()` |

**I. Konten (40 route)** — halaman 3 · banner 6 · artikel 6 · kategori artikel 6 · galeri 7 · FAQ 6 · mitra asuransi 6 — seluruhnya `permission:content.manage`; resource `->except(['show'])` kecuali `pages` (`->only(['index','edit','update'])`)

| Method | URI | Nama route | Controller@method | Middleware | Form Request | Policy/izin |
|---|---|---|---|---|---|---|
| GET | `/admin/konten/halaman` | `admin.pages.index` | `PageController@index` | `permission:content.manage` | `ListFilterRequest` | `PagePolicy::viewAny` |
| GET | `/admin/konten/halaman/{page}/ubah` | `admin.pages.edit` | `PageController@edit` | `permission:content.manage` | — | `PagePolicy::update` |
| PUT/PATCH | `/admin/konten/halaman/{page}` | `admin.pages.update` | `PageController@update` | `permission:content.manage` | `PageRequest` | `PagePolicy::update` (kunci `key` tidak dapat diubah; halaman sistem tidak dapat dihapus) |
| GET | `/admin/konten/banner` | `admin.banners.index` | `BannerController@index` | `permission:content.manage` | `ListFilterRequest` | `BannerPolicy::viewAny` |
| GET | `/admin/konten/banner/buat` | `admin.banners.create` | `BannerController@create` | `permission:content.manage` | — | `BannerPolicy::create` |
| POST | `/admin/konten/banner` | `admin.banners.store` | `BannerController@store` | `permission:content.manage` | `BannerRequest` | `BannerPolicy::create` |
| GET | `/admin/konten/banner/{banner}/ubah` | `admin.banners.edit` | `BannerController@edit` | `permission:content.manage` | — | `BannerPolicy::update` |
| PUT/PATCH | `/admin/konten/banner/{banner}` | `admin.banners.update` | `BannerController@update` | `permission:content.manage` | `BannerRequest` | `BannerPolicy::update` |
| DELETE | `/admin/konten/banner/{banner}` | `admin.banners.destroy` | `BannerController@destroy` | `permission:content.manage` | — | `BannerPolicy::delete` |
| GET | `/admin/konten/artikel` | `admin.articles.index` | `ArticleController@index` | `permission:content.manage` | `ListFilterRequest` | `ArticlePolicy::viewAny` |
| GET | `/admin/konten/artikel/buat` | `admin.articles.create` | `ArticleController@create` | `permission:content.manage` | — | `ArticlePolicy::create` |
| POST | `/admin/konten/artikel` | `admin.articles.store` | `ArticleController@store` | `permission:content.manage` | `ArticleRequest` | `ArticlePolicy::create` |
| GET | `/admin/konten/artikel/{article}/ubah` | `admin.articles.edit` | `ArticleController@edit` | `permission:content.manage` | — | `ArticlePolicy::update` |
| PUT/PATCH | `/admin/konten/artikel/{article}` | `admin.articles.update` | `ArticleController@update` | `permission:content.manage` | `ArticleRequest` | `ArticlePolicy::update` |
| DELETE | `/admin/konten/artikel/{article}` | `admin.articles.destroy` | `ArticleController@destroy` | `permission:content.manage` | — | `ArticlePolicy::delete` (soft delete) |
| GET | `/admin/konten/kategori-artikel` | `admin.article-categories.index` | `ArticleCategoryController@index` | `permission:content.manage` | `ListFilterRequest` | `ArticleCategoryPolicy::viewAny` |
| GET | `/admin/konten/kategori-artikel/buat` | `admin.article-categories.create` | `ArticleCategoryController@create` | `permission:content.manage` | — | `ArticleCategoryPolicy::create` |
| POST | `/admin/konten/kategori-artikel` | `admin.article-categories.store` | `ArticleCategoryController@store` | `permission:content.manage` | `ArticleCategoryRequest` | `ArticleCategoryPolicy::create` |
| GET | `/admin/konten/kategori-artikel/{articleCategory}/ubah` | `admin.article-categories.edit` | `ArticleCategoryController@edit` | `permission:content.manage` | — | `ArticleCategoryPolicy::update` |
| PUT/PATCH | `/admin/konten/kategori-artikel/{articleCategory}` | `admin.article-categories.update` | `ArticleCategoryController@update` | `permission:content.manage` | `ArticleCategoryRequest` | `ArticleCategoryPolicy::update` |
| DELETE | `/admin/konten/kategori-artikel/{articleCategory}` | `admin.article-categories.destroy` | `ArticleCategoryController@destroy` | `permission:content.manage` | — | `ArticleCategoryPolicy::delete` (artikel jadi tanpa kategori — FK `nullOnDelete`) |
| GET | `/admin/konten/galeri` | `admin.galleries.index` | `GalleryController@index` | `permission:content.manage` | `ListFilterRequest` | `GalleryPolicy::viewAny` |
| GET | `/admin/konten/galeri/buat` | `admin.galleries.create` | `GalleryController@create` | `permission:content.manage` | — | `GalleryPolicy::create` |
| POST | `/admin/konten/galeri` | `admin.galleries.store` | `GalleryController@store` | `permission:content.manage` | `GalleryRequest` | `GalleryPolicy::create` |
| GET | `/admin/konten/galeri/{gallery}/ubah` | `admin.galleries.edit` | `GalleryController@edit` | `permission:content.manage` | — | `GalleryPolicy::update` |
| PUT/PATCH | `/admin/konten/galeri/{gallery}` | `admin.galleries.update` | `GalleryController@update` | `permission:content.manage` | `GalleryRequest` | `GalleryPolicy::update` |
| DELETE | `/admin/konten/galeri/{gallery}` | `admin.galleries.destroy` | `GalleryController@destroy` | `permission:content.manage` | — | `GalleryPolicy::delete` (menghapus foto album) |
| POST | `/admin/konten/galeri/{gallery}/foto` | `admin.galleries.photos.store` | `GalleryController@storePhotos` | `permission:content.manage` | `StoreGalleryPhotosRequest` | `GalleryPolicy::update` |
| GET | `/admin/konten/faq` | `admin.faqs.index` | `FaqController@index` | `permission:content.manage` | `ListFilterRequest` | `FaqPolicy::viewAny` |
| GET | `/admin/konten/faq/buat` | `admin.faqs.create` | `FaqController@create` | `permission:content.manage` | — | `FaqPolicy::create` |
| POST | `/admin/konten/faq` | `admin.faqs.store` | `FaqController@store` | `permission:content.manage` | `FaqRequest` | `FaqPolicy::create` |
| GET | `/admin/konten/faq/{faq}/ubah` | `admin.faqs.edit` | `FaqController@edit` | `permission:content.manage` | — | `FaqPolicy::update` |
| PUT/PATCH | `/admin/konten/faq/{faq}` | `admin.faqs.update` | `FaqController@update` | `permission:content.manage` | `FaqRequest` | `FaqPolicy::update` |
| DELETE | `/admin/konten/faq/{faq}` | `admin.faqs.destroy` | `FaqController@destroy` | `permission:content.manage` | — | `FaqPolicy::delete` |
| GET | `/admin/konten/asuransi` | `admin.insurance-partners.index` | `InsurancePartnerController@index` | `permission:content.manage` | `ListFilterRequest` | `InsurancePartnerPolicy::viewAny` |
| GET | `/admin/konten/asuransi/buat` | `admin.insurance-partners.create` | `InsurancePartnerController@create` | `permission:content.manage` | — | `InsurancePartnerPolicy::create` |
| POST | `/admin/konten/asuransi` | `admin.insurance-partners.store` | `InsurancePartnerController@store` | `permission:content.manage` | `InsurancePartnerRequest` | `InsurancePartnerPolicy::create` |
| GET | `/admin/konten/asuransi/{insurancePartner}/ubah` | `admin.insurance-partners.edit` | `InsurancePartnerController@edit` | `permission:content.manage` | — | `InsurancePartnerPolicy::update` |
| PUT/PATCH | `/admin/konten/asuransi/{insurancePartner}` | `admin.insurance-partners.update` | `InsurancePartnerController@update` | `permission:content.manage` | `InsurancePartnerRequest` | `InsurancePartnerPolicy::update` |
| DELETE | `/admin/konten/asuransi/{insurancePartner}` | `admin.insurance-partners.destroy` | `InsurancePartnerController@destroy` | `permission:content.manage` | — | `InsurancePartnerPolicy::delete`; ditolak bila dirujuk `appointments.insurance_partner_id` (nonaktifkan saja) |

**J. Pengaturan, staf, hak akses, audit, pesan, media (21 route)** — pengaturan 2 · staf 7 · hak akses 3 · audit 2 · pesan 3 · media 4

| Method | URI | Nama route | Controller@method | Middleware | Form Request | Policy/izin |
|---|---|---|---|---|---|---|
| GET | `/admin/pengaturan` | `admin.settings.edit` | `SettingController@edit` | `permission:settings.manage` | — | `Gate::authorize('settings.manage')` |
| PUT | `/admin/pengaturan` | `admin.settings.update` | `SettingController@update` | `permission:settings.manage` | `UpdateSettingsRequest` | `Gate::authorize('settings.manage')`; `SettingsService::setMany()` |
| GET | `/admin/staf` | `admin.staff.index` | `StaffController@index` | `permission:staff.manage` | `ListFilterRequest` | `UserPolicy::viewAny` |
| GET | `/admin/staf/buat` | `admin.staff.create` | `StaffController@create` | `permission:staff.manage` | — | `UserPolicy::create` |
| POST | `/admin/staf` | `admin.staff.store` | `StaffController@store` | `permission:staff.manage` | `StaffRequest` | `UserPolicy::create`; `StaffAccountService::create()` |
| GET | `/admin/staf/{user}/ubah` | `admin.staff.edit` | `StaffController@edit` | `permission:staff.manage` | — | `UserPolicy::update` |
| PUT/PATCH | `/admin/staf/{user}` | `admin.staff.update` | `StaffController@update` | `permission:staff.manage` | `StaffRequest` | `UserPolicy::update`; `StaffAccountService::update()` |
| PATCH | `/admin/staf/{user}/status` | `admin.staff.status` | `StaffController@status` | `permission:staff.manage` | `UpdateStaffStatusRequest` | `UserPolicy::changeStatus`; minimal satu Super Admin aktif |
| POST | `/admin/staf/{user}/reset-password` | `admin.staff.reset-password` | `StaffController@resetPassword` | `permission:staff.manage` | — | `UserPolicy::resetPassword`; password sementara + `must_change_password=true` |
| GET | `/admin/hak-akses` | `admin.roles.index` | `RoleController@index` | `permission:staff.manage` | — | `RolePolicy::viewAny` |
| GET | `/admin/hak-akses/{role}/ubah` | `admin.roles.edit` | `RoleController@edit` | `permission:staff.manage` | — | `RolePolicy::update` |
| PUT/PATCH | `/admin/hak-akses/{role}` | `admin.roles.update` | `RoleController@update` | `permission:staff.manage` | `UpdateRolePermissionsRequest` | `RolePolicy::update`; peran `super-admin` terkunci |
| GET | `/admin/audit-log` | `admin.audit-logs.index` | `AuditLogController@index` | `permission:audit.view` | `AuditLogIndexRequest` | `AuditLogPolicy::viewAny` |
| GET | `/admin/audit-log/{auditLog}` | `admin.audit-logs.show` | `AuditLogController@show` | `permission:audit.view` | — | `AuditLogPolicy::view` |
| GET | `/admin/pesan` | `admin.messages.index` | `ContactMessageController@index` | `permission:messages.manage` | `ListFilterRequest` | `ContactMessagePolicy::viewAny` |
| GET | `/admin/pesan/{message}` | `admin.messages.show` | `ContactMessageController@show` | `permission:messages.manage` | — | `ContactMessagePolicy::view`; status `new` otomatis → `read` |
| PATCH | `/admin/pesan/{message}/status` | `admin.messages.status` | `ContactMessageController@status` | `permission:messages.manage` | `UpdateContactMessageStatusRequest` | `ContactMessagePolicy::updateStatus` |
| POST | `/admin/media/editor` | `admin.media.editor-upload` | `MediaController@editorUpload` | `permission:content.manage\|clinics.manage\|facilities.manage` | `EditorImageUploadRequest` | `MediaPolicy::upload`; respons JSON `{url, alt, media_id}` |
| POST | `/admin/media/urutkan` | `admin.media.reorder` | `MediaController@reorder` | `permission:doctors.manage\|clinics.manage\|facilities.manage\|content.manage` | `ReorderMediaRequest` | `MediaPolicy::reorder` (izin pemilik) |
| PATCH | `/admin/media/{media}` | `admin.media.update` | `MediaController@update` | `permission:doctors.manage\|clinics.manage\|facilities.manage\|content.manage\|settings.manage` | `UpdateMediaRequest` | `MediaPolicy::update` (izin pemilik) |
| DELETE | `/admin/media/{media}` | `admin.media.destroy` | `MediaController@destroy` | `permission:doctors.manage\|clinics.manage\|facilities.manage\|content.manage\|settings.manage` | — | `MediaPolicy::delete` (izin pemilik) |

### 2.4 Pemetaan segmen URL, parameter, dan model

| Segmen URL | Nama parameter | Model | Kunci binding |
|---|---|---|---|
| `/dokter` | `{doctor}` | `Doctor` | `id` (publik: `{doctor:slug}`) |
| `/spesialisasi` | `{specialty}` | `Specialty` | `id` |
| `/poliklinik` | `{clinic}` | `Clinic` | `id` (publik: `{clinic:slug}`) |
| `/jadwal` | `{schedule}` | `DoctorSchedule` | `id` |
| `/jadwal/pengecualian` | `{exception}` | `ScheduleException` | `id` |
| `/jadwal/sesi`, `/jadwal/sesi-terdampak`, `/antrean` | `{session}` | `PracticeSession` | `id` |
| `/fasilitas` | `{facility}` | `Facility` | `id` (publik: slug) |
| `/kamar` | `{roomCategory}` | `RoomCategory` | `id` (publik: slug) |
| `/pasien` | `{patient}` | `PatientProfile` | `id` |
| `/pendaftaran` | `{appointment}` | `Appointment` | `id` (portal pasien: `booking_code`) |
| `/konten/halaman` | `{page}` | `Page` | `id` |
| `/konten/banner` | `{banner}` | `Banner` | `id` |
| `/konten/artikel` | `{article}` | `Article` | `id` (publik: slug) |
| `/konten/kategori-artikel` | `{articleCategory}` | `ArticleCategory` | `id` |
| `/konten/galeri` | `{gallery}` | `Gallery` | `id` (publik: slug) |
| `/konten/faq` | `{faq}` | `Faq` | `id` |
| `/konten/asuransi` | `{insurancePartner}` | `InsurancePartner` | `id` |
| `/staf` | `{user}` | `User` (hanya `account_type=staff`) | `id` |
| `/hak-akses` | `{role}` | `Spatie\Permission\Models\Role` | `id` |
| `/audit-log` | `{auditLog}` | `AuditLog` | `id` |
| `/pesan` | `{message}` | `ContactMessage` | `id` |
| `/media` | `{media}` | `Media` | `id` |

Model tidak menimpa `getRouteKeyName()`; kunci slug/`booking_code` ditulis eksplisit di route (`{clinic:slug}`), sesuai 00 I.

### 2.5 Rekapitulasi jumlah route

| File | Rincian | Jumlah |
|---|---|:-:|
| `routes/web.php` | 22 publik + 9 autentikasi pasien | 31 |
| `routes/patient.php` | 13 | 13 |
| `routes/admin.php` | A 5 · B 2 · C 9 · D 12 · E 21 · F 13 · G 8 · H 9 · I 40 · J 21 | 140 |
| **Total** | | **184** |

Seluruh route di 00 §I tercakup: publik (I.1), autentikasi pasien (I.2), portal pasien (I.3), dan admin (I.4: halaman 31–40 + pesan kontak + media). Perbedaan dengan 00 hanya pada penguraian resource menjadi aksi (`create`→`/buat`, `edit`→`/ubah` pada URL), penetapan aksi per resource (lihat catatan **[ASUMSI]** di §2.3), tanpa nama route tambahan: `admin.sessions.destroy` (`DELETE /admin/jadwal/sesi/{session}`, hanya sesi tambahan manual tanpa appointment, izin `schedules.manage`) sudah tercantum di 00 I.4 dan menerapkan baris `practice_sessions` pada tabel `destroy` 00 C; nama route lain tidak ada yang diganti. Aturan `destroy` per tabel (00 C) dipetakan ke route sebagai berikut: hapus permanen — `admin.banners`, `admin.faqs`, `admin.galleries`, `admin.article-categories`, `admin.facilities`, `admin.media`; soft delete — `admin.articles`; hapus bersyarat — `admin.specialties`, `admin.clinics`, `admin.insurance-partners`, `admin.room-categories`, `admin.schedules`, `admin.sessions`; cabut pengecualian — `admin.exceptions`; **tanpa `destroy`** — `admin.doctors`, `admin.patients`, `admin.appointments` (`transition`), `admin.pages`, `admin.messages` (`status`), `admin.staff` (`status`), `admin.roles`, `admin.audit-logs`, `admin.settings`.

---
## 3. `bootstrap/app.php`, Provider, dan Kerangka File Route

Laravel 12 tidak memakai `app/Http/Kernel.php`; routing, middleware, dan penanganan exception dikonfigurasi di `bootstrap/app.php`.

### 3.1 Sketsa `bootstrap/app.php`

```php
<?php

use App\Enums\AccountType;
use App\Exceptions\BookingException;
use App\Http\Middleware\{EnsureAccountIsActive, EnsureAccountType, EnsurePasswordChanged, SecurityHeaders};
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\{Exceptions, Middleware};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\{PermissionMiddleware, RoleMiddleware, RoleOrPermissionMiddleware};
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web:      __DIR__.'/../routes/web.php',      // publik + autentikasi pasien
        commands: __DIR__.'/../routes/console.php',
        health:   '/up',                              // route bawaan framework (bukan bagian 00 §I) [ASUMSI]
        then: function () {
            Route::middleware(['web', 'auth', 'account:patient', 'active'])
                ->prefix('pasien')->name('patient.')
                ->group(base_path('routes/patient.php'));

            Route::middleware('web')
                ->prefix('admin')->name('admin.')
                ->where(array_fill_keys([                 // parameter admin berbasis id harus numerik (§1 aturan 2)
                    'doctor', 'specialty', 'clinic', 'schedule', 'exception', 'session', 'facility',
                    'roomCategory', 'patient', 'appointment', 'page', 'banner', 'article',
                    'articleCategory', 'gallery', 'faq', 'insurancePartner', 'user', 'role',
                    'auditLog', 'message', 'media',
                ], '[0-9]+'))
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'account'          => EnsureAccountType::class,       // account:patient | account:staff
            'active'           => EnsureAccountIsActive::class,
            'password.changed' => EnsurePasswordChanged::class,
            'role'             => RoleMiddleware::class,          // Spatie
            'permission'       => PermissionMiddleware::class,    // Spatie; mendukung "a|b"
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);

        $middleware->append(SecurityHeaders::class);              // global (00 J.3)

        // Tamu yang membuka halaman ber-`auth`: URL /admin* → login admin; selain itu → login pasien.
        $middleware->redirectGuestsTo(fn (Request $r) =>
            $r->is('admin', 'admin/*') ? route('admin.login') : route('login'));

        // Pengguna login yang membuka halaman ber-`guest`: arahkan sesuai tipe akun.
        $middleware->redirectUsersTo(fn (Request $r) =>
            $r->user()?->account_type === AccountType::Staff ? route('admin.dashboard') : route('patient.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // lihat §11 untuk isi lengkap setiap penanganan
        $exceptions->dontReport([BookingException::class]);
        $exceptions->dontFlash(['password', 'password_confirmation', 'current_password', 'bpjs_number', 'payment_reference']);
        $exceptions->shouldRenderJsonWhen(fn (Request $r, Throwable $e) =>
            $r->expectsJson() || $r->routeIs('admin.dashboard.chart', 'admin.media.*'));

        $exceptions->render(function (BookingException $e, Request $r) { /* isi: §11.2 — redirect + flash error */ });
        $exceptions->render(function (HttpExceptionInterface $e, Request $r) { /* isi: §11.3 — 403 / 419 / 429 Indonesia */ });
    })
    ->create();
```

**Catatan implementasi penting.**

- Sebelum callback `render()` dijalankan, Laravel mengonversi `AuthorizationException` → `AccessDeniedHttpException` (403) dan `TokenMismatchException` → `HttpException` 419. Maka callback 403/419/429 dipasang pada `HttpExceptionInterface` dan dibedakan lewat `getStatusCode()`; callback yang di-type-hint `AuthorizationException` atau `TokenMismatchException` **tidak akan pernah terpanggil**. `ThrottleRequestsException` sudah berupa `HttpException` 429.
- `redirectGuestsTo`/`redirectUsersTo` menggantikan `Authenticate::redirectTo` dan `RedirectIfAuthenticated` dari versi lama.
- Pasien yang membuka `/admin/...` **bukan tamu** sehingga tidak dialihkan ke login; `account:staff` menolaknya dengan 403 (sesuai 00 H & kebutuhan "akses URL admin ditolak untuk pasien").

### 3.2 Alias dan urutan middleware

| Alias | Kelas | Parameter |
|---|---|---|
| `account` | `App\Http\Middleware\EnsureAccountType` | `patient` atau `staff` |
| `active` | `App\Http\Middleware\EnsureAccountIsActive` | — |
| `password.changed` | `App\Http\Middleware\EnsurePasswordChanged` | — |
| `role` / `permission` / `role_or_permission` | Spatie | `permission:a\|b` = salah satu izin |
| (global) | `App\Http\Middleware\SecurityHeaders` | — |

Urutan eksekusi `G-ADMIN`: `auth` → `account:staff` → `active` → `password.changed` → `permission:*` → (controller) Policy. Perilaku masing-masing: §7.

### 3.3 `app/Providers/AppServiceProvider.php`

```php
public function register(): void
{
    $this->app->scoped(SettingsService::class);   // satu instance per request/job (cache memori + cache aplikasi); scoped agar memo tidak basi di queue worker (Task 7)
}

public function boot(): void
{
    // 1. Mode ketat hanya di non-produksi: mencegah lazy loading (N+1), atribut hilang, atribut yang diam-diam dibuang
    Model::shouldBeStrict(! $this->app->isProduction());

    // 2. Paginasi Bootstrap 5 (view terbit di resources/views/vendor/pagination)
    Paginator::useBootstrapFive();

    // 3. Super Admin melewati semua Gate/Policy (00 G). Hanya untuk pengguna login.
    Gate::before(fn (User $user, string $ability) => $user->hasRole('super-admin') ? true : null);
    Gate::policy(Role::class, RolePolicy::class);            // Role milik Spatie, bukan di App\Models

    // 4. Rate limiter 00 H (rinci §10.3)
    $this->configureRateLimiting();

    // 5. URL resource Indonesia + binding khusus
    Route::resourceVerbs(['create' => 'buat', 'edit' => 'ubah']);
    Route::bind('user', fn (string $v) => User::query()
        ->where('account_type', AccountType::Staff)->findOrFail($v));   // {user} hanya akun staf

    // 6. Pengaturan situs tersedia di semua layout sebagai $site (SettingsService)
    View::composer(['components.layouts.*'], fn ($view) => $view->with('site', app(SettingsService::class)));

    // 7. Audit generik hanya untuk model konten/master sederhana; aksi domain dicatat eksplisit oleh service/controller
    //    (staff.*, patient.*, doctor.*, schedule.*, exception.*, session.*, appointment.*, room.availability_updated — 12 §8)
    foreach ([Specialty::class, Clinic::class, Facility::class, RoomCategory::class, Page::class, Banner::class,
              Article::class, ArticleCategory::class, Gallery::class, Faq::class, InsurancePartner::class] as $model) {
        $model::observe(AuditObserver::class);
    }
}
```

**Penjelasan tiap baris penting.**

| Butir | Alasan |
|---|---|
| `Model::shouldBeStrict(! isProduction())` | Meliputi `preventLazyLoading`, `preventSilentlyDiscardingAttributes`, `preventAccessingMissingAttributes`. Di lokal & pengujian, query N+1 dan salah ketik atribut langsung gagal; di produksi tidak melempar galat ke pengguna. Konsekuensi: relasi yang dipakai accessor (`Appointment::queueLabel` butuh `clinic`; `Doctor::photoUrl` butuh `media`) harus di-*eager load* di controller. |
| `Gate::before` | Mengembalikan `true` untuk Super Admin, `null` untuk lainnya (melanjutkan ke Spatie/Policy). **Konsekuensi:** aturan pengunci tidak boleh bergantung pada Policy saja (Super Admin melewatinya) — penguncian peran `super-admin`, larangan menonaktifkan diri sendiri, dan "minimal satu Super Admin aktif" ditegakkan di `StaffAccountService` dan Form Request (§6.15, §9). |
| Spatie `register_permission_check_method` | `$user->can('settings.manage')` bekerja karena Spatie mendaftarkan izin sebagai ability Gate; "Gate `settings.manage`" (00 J.4) = pemeriksaan izin langsung. |
| `Route::bind('user')` | Mencegah akun pasien diubah lewat endpoint staf. |
| `AuditObserver` | Mencatat `{model}.created`, `.updated`, `.deleted` (`specialty.*`, `clinic.*`, `facility.*`, `room_category.*`, `page.updated`, `banner.*`, `article.*`, `article_category.*`, `gallery.*`, `faq.*`, `insurance_partner.*`; sama dengan 12 §8.3) dengan nilai lama/baru (hanya atribut yang berubah) lewat `AuditLogger`, dengan redaksi (§6.10). `User`, `PatientProfile`, `Doctor`, `DoctorSchedule`, `ScheduleException`, `Appointment`, `PracticeSession`, `Media`, `WebsiteSetting`, `ContactMessage` **tidak** diobservasi karena dicatat eksplisit oleh service/controller dengan nama aksi dan data dampak khusus (12 §8.2), sehingga tidak ada baris audit ganda; pembaruan ketersediaan kamar memakai `saveQuietly()` agar hanya `room.availability_updated` yang tercatat. |

### 3.4 Kerangka file route

```php
// routes/web.php  (publik + auth pasien)
use App\Http\Controllers\{Auth, Public};

Route::get('/', [Public\HomeController::class, 'index'])->name('home');
Route::get('/profil', [Public\PageController::class, 'about'])->name('about');
// … 22 route publik (§2.1)
Route::post('/kontak', [Public\ContactController::class, 'store'])->middleware('throttle:contact')->name('contact.store');

Route::middleware('guest')->group(function () {
    Route::get('/daftar', [Auth\RegisterController::class, 'create'])->name('register');
    Route::post('/daftar', [Auth\RegisterController::class, 'store'])->middleware('throttle:register')->name('register.store');
    // … masuk, lupa-password, reset-password
});
Route::post('/keluar', [Auth\LoginController::class, 'destroy'])->middleware('auth')->name('logout');
```

```php
// routes/patient.php  (prefix 'pasien', nama 'patient.', middleware G-PASIEN dipasang di bootstrap/app.php)
use App\Http\Controllers\Patient;

Route::get('/', [Patient\DashboardController::class, 'index'])->name('dashboard');
Route::get('/profil', [Patient\ProfileController::class, 'edit'])->name('profile.edit');
Route::put('/profil', [Patient\ProfileController::class, 'update'])->name('profile.update');
Route::put('/password', [Patient\PasswordController::class, 'update'])->name('password.update');

Route::prefix('pendaftaran')->name('appointments.')->controller(Patient\AppointmentController::class)->group(function () {
    Route::get('/baru', 'create')->name('create');                       // statis lebih dulu
    Route::post('/tinjau', 'review')->name('review');
    Route::post('/', 'store')->middleware('throttle:booking')->name('store');
    Route::get('/', 'index')->name('index');
    Route::prefix('{appointment:booking_code}')
        ->where(['appointment' => 'UPH-[0-9A-HJKMNP-TV-Z]{4}-[0-9A-HJKMNP-TV-Z]{4}'])->group(function () {
            Route::get('/', 'show')->name('show');
            Route::post('/batal', 'cancel')->name('cancel');
            Route::get('/bukti', 'proof')->name('proof');
            Route::get('/bukti.pdf', 'proofPdf')->name('proof-pdf');
        });
});
Route::post('/notifikasi/{id}/baca', [Patient\NotificationController::class, 'read'])->name('notifications.read');
```

```php
// routes/admin.php  (prefix 'admin', nama 'admin.')
use App\Http\Controllers\{Admin, Auth};

// A. login admin (guest) — di luar G-ADMIN
Route::middleware('guest')->group(function () {
    Route::get('/login', [Auth\AdminLoginController::class, 'create'])->name('login');
    Route::post('/login', [Auth\AdminLoginController::class, 'store'])->middleware('throttle:login')->name('login.store');
});
Route::middleware(['auth', 'account:staff'])->post('/logout', [Auth\AdminLoginController::class, 'destroy'])->name('logout');
Route::middleware(['auth', 'account:staff', 'active'])->group(function () {
    Route::get('/ganti-password', [Auth\AdminPasswordController::class, 'edit'])->name('password.edit');
    Route::put('/ganti-password', [Auth\AdminPasswordController::class, 'update'])->name('password.update');
});

// B–J. G-ADMIN
Route::middleware(['auth', 'account:staff', 'active', 'password.changed'])->group(function () {
    Route::get('/', [Admin\DashboardController::class, 'index'])->middleware('permission:dashboard.view')->name('dashboard');
    Route::get('/dashboard/grafik', [Admin\DashboardController::class, 'chart'])->middleware('permission:dashboard.view')->name('dashboard.chart');

    // Pola resource dengan izin lihat ≠ kelola: kelola DAHULU (agar /dokter/buat tidak tertangkap {doctor}), lalu lihat
    Route::middleware('permission:doctors.manage')->group(function () {
        Route::resource('dokter', Admin\DoctorController::class)->names('doctors')->parameters(['dokter' => 'doctor'])
            ->except(['index', 'show', 'destroy']);              // dokter TIDAK punya destroy (00 C: diarsipkan, bukan dihapus)
        Route::patch('dokter/{doctor}/status',  [Admin\DoctorController::class, 'status'])->name('doctors.status');
        Route::patch('dokter/{doctor}/arsip',   [Admin\DoctorController::class, 'archive'])->name('doctors.archive');
        Route::post('dokter/{doctor}/foto',     [Admin\DoctorController::class, 'photo'])->name('doctors.photo');
    });
    Route::middleware('permission:doctors.view')->group(function () {
        Route::resource('dokter', Admin\DoctorController::class)->names('doctors')->parameters(['dokter' => 'doctor'])
            ->only(['index', 'show']);
    });

    // Jadwal: statis dahulu → sesi-terdampak, sesi, pengecualian, lalu resource templat
    Route::middleware('permission:sessions.review')->prefix('jadwal/sesi-terdampak')->group(function () {
        Route::get('/', [Admin\SessionReviewController::class, 'index'])->name('sessions.review');
        Route::post('{session}', [Admin\SessionReviewController::class, 'resolve'])->name('sessions.resolve');
    });
    // … sesi (schedules.view / schedules.manage), pengecualian, jadwal, dst. (§2.3)
});
```

Registrasi resource kedua kali dengan `->names('doctors')` tidak menimbulkan duplikasi nama karena `except`/`only` saling lepas. Pola yang sama dipakai untuk `schedules`, `exceptions`, `patients`, `appointments` (izin lihat berbeda dari kelola); resource lain berizin tunggal didaftarkan sekali. **Penting (00 C, I.6):** `->except([...])` yang tidak menyebut `destroy` tetap mendaftarkan `DELETE`, jadi setiap resource yang tidak boleh dihapus **wajib** menyebut `destroy` pada `except`/membatasi dengan `only`: `dokter` (`except(['index','show','destroy'])`), `pasien` dan `pendaftaran` (grup kelola `only(['create','store','edit','update'])` untuk pasien dan `only(['create','store'])` untuk pendaftaran; grup lihat `only(['index','show'])`), dan `konten/halaman` (`only(['index','edit','update'])`). Resource yang boleh dihapus (`spesialisasi`, `poliklinik`, `jadwal`, `jadwal/pengecualian`, `fasilitas`, `kamar`, `konten/banner`, `konten/artikel`, `konten/kategori-artikel`, `konten/galeri`, `konten/faq`, `konten/asuransi`) memakai `->except(['show'])`; sesi memakai rute eksplisit (`index`, `create`, `store`, `edit`, `update`, `status`, `destroy`). Tes `KNS-01` (15) membandingkan `route:list` dengan daftar ini sehingga `destroy` yang tak sengaja terdaftar langsung terdeteksi.

---
## 4. Controller

**Prinsip controller tipis:** tidak ada aturan bisnis di controller. Urutan standar tiap method: (1) `$this->authorize()` / Form Request `authorize()`; (2) ambil data tervalidasi (`$request->validated()`); (3) panggil service atau operasi model sederhana dalam `DB::transaction`; (4) flash message + `redirect()` (mutasi) atau `view()` (tampil). Daftar view per halaman ada di [10 §2](10-struktur-folder.md). Semua query daftar memakai *eager loading* eksplisit (mode ketat §3.3 melarang lazy loading).

**Pola CRUD admin standar ("P-CRUD")** — dipakai semua controller sumber daya admin kecuali bagian *Catatan*:

| Method | Langkah |
|---|---|
| `index` | `authorize('viewAny', Model::class)` → `Model::query()->search($filters['q'])->…->paginate(15)->withQueryString()` → `view('admin.{res}.index')` memakai `<x-data-table>`. |
| `create` | `authorize('create', Model::class)` → `view('admin.{res}.create')` (form `_form`). |
| `store` | Request tervalidasi → `DB::transaction`: `Model::create()`, unggah media lewat `MediaService::store()/replace()` bila ada file, sinkronisasi relasi → flash `success` ("… berhasil ditambahkan.") → redirect `index`. Audit dicatat `AuditObserver`. |
| `edit` / `update` | `authorize('update', $model)` → sama seperti `store` dengan `fill()`/`save()`; slug **tidak** berubah otomatis saat nama diubah (menjaga tautan) kecuali dikosongkan **[ASUMSI]**. |
| `destroy` | `authorize('delete', $model)` → bila `deletionBlockedReason()` tidak null → flash `error` + redirect; selain itu `MediaService::delete()` untuk media pemilik lalu `$model->delete()` → flash `success`. |

### 4.1 `App\Http\Controllers\Public` (11 controller)

| Controller | Method (route) | Tanggung jawab | View | Service/Model |
|---|---|---|---|---|
| `HomeController` | `index` (`home`) | Beranda: banner aktif, tombol utama, dokter unggulan beserta sesi terdekat, poliklinik bookable, 3 artikel terbaru, ringkasan kamar. | `public.home` | `PracticeSessionGenerator::ensureDailyHorizon()`, `DoctorAvailabilityQuery`, `Banner::currentlyActive()`, `Article::published()`, `Page::forKey('home')` |
| `PageController` | `about`, `visionMission`, `insurance`, `registrationGuide`, `privacy` | Merender halaman konten dari `pages` (`about`, `vision-mission`, `insurance-info`, `registration-guide`, `privacy-policy`) lewat satu helper privat `render($key, $view, $extra)`; `insurance` juga memuat `InsurancePartner::active()`. Meta title/description dari `pages`. | `public.about`, `public.vision-mission`, `public.insurance`, `public.registration-guide`, `public.privacy` | `Page::forKey()`, `InsurancePartner::active()` |
| `ClinicController` | `index`, `show` | Daftar poliklinik/layanan (dikelompokkan `ClinicType`); detail: deskripsi rich text, galeri, dokter aktif di poliklinik, sesi 7 hari ke depan. | `public.clinics.index`, `public.clinics.show` | `DoctorAvailabilityQuery::sessions()` |
| `DoctorController` | `index`, `show` | Pencarian dokter (`q`, `spesialisasi`, `poli`, `tanggal`) dengan paginasi; detail: profil, spesialisasi, jadwal 14 hari berbadge ketersediaan, tombol "Daftar". | `public.doctors.index`, `public.doctors.show` | `DoctorAvailabilityQuery::doctors()` / `::sessions()`, `PracticeSessionGenerator::ensureDailyHorizon()` |
| `ScheduleController` | `index` | Jadwal mingguan (`minggu`, `poli`, `dokter`) dengan `<x-schedule-table>`; sumber data sama dengan wizard. | `public.schedules.index` | `DoctorAvailabilityQuery::sessions()`, `ensureDailyHorizon()` |
| `FacilityController` | `index`, `show` | Fasilitas aktif per `FacilityCategory`; detail dengan galeri. | `public.facilities.index`, `.show` | — |
| `RoomCategoryController` | `index`, `show` | Ringkasan 4 kategori kamar (tempat tidur tersedia/kapasitas, tarif contoh, **waktu pembaruan terakhir**, tanpa kata "real-time"); detail dengan galeri dan daftar `amenities`. | `public.rooms.index`, `.show` | — |
| `ArticleController` | `index`, `show` | Artikel terbit (filter `kategori`, `q`; 9 per halaman); detail + 3 artikel terkait. | `public.articles.index`, `.show` | — |
| `GalleryController` | `index`, `show` | Album terbit dan foto album. | `public.galleries.index`, `.show` | — |
| `FaqController` | `index` | FAQ terbit dikelompokkan per `category` (accordion Bootstrap). | `public.faq` | — |
| `ContactController` | `show`, `store` | Halaman kontak + `<x-map-embed>`; `store` menyimpan `contact_messages` (`status=new`, `ip_hash = hash('sha256', $ip.config('app.key'))`, `consent_at=now()`), flash `success`. IP mentah tidak disimpan. | `public.contact` | `SettingsService` |

### 4.2 `App\Http\Controllers\Auth` (6 controller)

| Controller | Method | Tanggung jawab | View | Service/Model |
|---|---|---|---|---|
| `RegisterController` | `create`, `store` | `store`: `PatientRegistrationService::register($validated)` (transaksi `users` + `patient_profiles`; setelah commit mengirim `PatientWelcomeNotification` — mail saja, tanpa verifikasi wajib, K-22 default (a)) → `Auth::login()` → `session()->regenerate()` → redirect `patient.dashboard` + flash `success`. | `auth.register` | `PatientRegistrationService` |
| `LoginController` | `create`, `store`, `destroy` | `store`: `LoginRequest::authenticate()` = `Auth::attempt(['email','password','account_type'=>'patient','is_active'=>true], $remember)`; gagal → pesan generik "Email atau password salah"; sukses → `session()->regenerate()`, `last_login_at=now()`, `redirect()->intended(route('patient.dashboard'))`. `destroy`: `logout()` + `session()->invalidate()` + `regenerateToken()` → `home`. | `auth.login` | — (login pasien tidak diaudit; login staf diaudit di `AdminLoginController`) |
| `PasswordResetLinkController` | `create`, `store` | `store`: `Password::broker()->sendResetLink(['email'=>…, 'account_type'=>'patient'])`; **respons selalu sama** ("Jika email terdaftar, tautan reset telah dikirim") apa pun hasilnya; tautan tidak pernah ditampilkan. | `auth.forgot-password` | Password broker |
| `NewPasswordController` | `create`, `store` | `store`: `Password::broker()->reset([... 'account_type'=>'patient'], fn …)` → `forceFill(password, remember_token)->save()` + hapus sesi lain pengguna dari tabel `sessions` **[ASUMSI]** → redirect `login` + flash `success`; token salah/kedaluwarsa → galat `email` (`passwords.token`); sukses diaudit `auth.password_reset` (tanpa token/password). | `auth.reset-password` | Password broker, `AuditLogger` |
| `AdminLoginController` | `create`, `store`, `destroy` | Seperti `LoginController` tetapi `account_type=staff`; mencatat audit `auth.staff_login` (berhasil), `auth.staff_login_failed` (gagal; tanpa password) dan `auth.staff_logout`; redirect `intended(route('admin.dashboard'))`; `destroy` → redirect `admin.login`. | `auth.admin-login` | `AuditLogger` |
| `AdminPasswordController` | `edit`, `update` | `update`: `UpdatePasswordRequest` (password lama benar, baru ≠ lama) → set password, `must_change_password=false`, `session()->regenerate()`, audit `auth.password_changed` → `admin.dashboard`. | `auth.admin-change-password` | `AuditLogger` |

### 4.3 `App\Http\Controllers\Patient` (5 controller)

| Controller | Method | Tanggung jawab | View | Service/Model |
|---|---|---|---|---|
| `DashboardController` | `index` | Ringkasan: janji mendatang (`Appointment::forPatient()->active()->upcoming()`, maks 5), notifikasi belum dibaca (`unreadNotifications`), tombol "Daftar Kunjungan". Jika `schedule_changed_at` terisi, tampilkan peringatan pada kartu janji. | `patient.dashboard` | `Appointment` scope |
| `ProfileController` | `edit`, `update` | Edit profil (nama, tanggal lahir, jenis kelamin, telepon, email, alamat) dan tampilan nomor BPJS tersamar (`maskedBpjs`; hapus lewat `remove_bpjs`); `update` → `PatientRegistrationService::updateProfile()` memperbarui `patient_profiles` dan `users` dalam satu transaksi: `email` profil adalah email login dan **selalu disinkronkan** dengan `users.email` (00 E.1; validasi unik di `users` mengabaikan akun sendiri; bentrok ditolak tanpa perubahan parsial), `name`/`phone` ikut diselaraskan **[ASUMSI]**. | `patient.profile.edit` | `PatientProfilePolicy` |
| `PasswordController` | `update` | `current_password` + password baru; `session()->regenerate()`; flash `success`. | (redirect ke `patient.profile.edit`) | — |
| `AppointmentController` | `create` | Wizard GET tanpa JS dengan indikator `<x-wizard-steps>` (Poliklinik → Dokter → Tanggal & Sesi → Data Pendaftaran → Konfirmasi): langkah ditentukan query `poli` (slug) → `dokter` (slug) → `sesi` (id; `tanggal` `YYYY-MM-DD` menyaring langkah 3, 00 I.5); langkah 3 mendaftar sesi (`SessionAvailability`) dalam jendela `booking.window_days`; langkah 4 menampilkan formulir jenis pembayaran/keluhan; sesi yang tidak `isBookableOnline` ditolak halus (kembali ke langkah 3 + `warning` berisi label `Availability`). | `patient.appointments.create` | `DoctorAvailabilityQuery`, `PracticeSessionGenerator::ensureDailyHorizon()` |
| | `review` | Memvalidasi pilihan, menjalankan `BookingService::precheck()` (pra-cek F.5 tanpa kunci dan tanpa menulis; tidak mengikat — kuota baru dikunci saat `store`), lalu **merender** ringkasan + tombol "Konfirmasi Pendaftaran" (`<x-loading-button>`); kegagalan validasi/pra-cek dialihkan ke `patient.appointments.create` dengan query langkah terakhir + `withInput()` (tidak pernah `back()`, §11.2). | `patient.appointments.review` | `BookingService::precheck()`, `DoctorAvailabilityQuery::forSession()` |
| | `store` | `BookingService::book($profile, $session, BookingData::fromValidated($data), Actor::patient($user))` → flash `success` → redirect `patient.appointments.show`. Galat bisnis → handler global (§11.2). | — | `BookingService` |
| | `index` | Bagian "Mendatang" (aktif, `session_date ≥ hari ini`) dan "Riwayat" (paginasi 10, urut `session_date` menurun); query `jadwal_berubah=1` menyaring hanya appointment dengan `schedule_changed_at` terisi (`Appointment::scopeScheduleChanged()`; tautan dari banner "jadwal berubah" di `patient.dashboard`). | `patient.appointments.index` | `Appointment::forPatient()` |
| | `show` | Detail: kode booking, nomor antrean (`queueLabel`), jadwal, status + riwayat status, tombol batal (hanya bila `canBeCancelledByPatient()`), tautan bukti, peringatan jadwal berubah. | `patient.appointments.show` | `AppointmentPolicy::view` |
| | `cancel` | `AppointmentStatusService::transition($a, Cancelled, Actor::patient($user), $reason)`; setelah batas → `InvalidTransitionException` berpesan "hubungi RS" (telepon/WhatsApp dari `contact.*`). | — | `AppointmentStatusService` |
| | `proof` | Halaman cetak bukti (`<x-proof-card>`, tombol Cetak & Unduh PDF). | `patient.appointments.proof` | `AppointmentProofService::viewData()` |
| | `proofPdf` | Unduhan PDF `bukti-{booking_code}.pdf`, header `Cache-Control: private, no-store`. | `pdf.appointment-proof` | `AppointmentProofService::pdf()` |
| `NotificationController` | `read` | Menandai notifikasi milik sendiri terbaca lalu redirect ke `patient.appointments.show` (berdasarkan `data.booking_code`). | — | `$user->notifications()` |

### 4.4 `App\Http\Controllers\Admin` (26 controller)

| Controller | Method (route) | Tanggung jawab dan penyimpangan dari P-CRUD | View | Service/Model |
|---|---|---|---|---|
| `DashboardController` | `index`, `chart` | `index`: widget disaring izin (angka agregat untuk semua `dashboard.view`; daftar bernama pasien hanya `appointments.view`; hitungan sesi `needs_review` hanya `sessions.review`). `chart`: JSON `{labels, series}` per tanggal kunjungan; filter 7/30/90 hari atau rentang kustom. | `admin.dashboard` | `DashboardMetrics` |
| `DoctorController` | `index`, `create`, `store`, `show`, `edit`, `update`, `status`, `archive`, `photo` | P-CRUD + `specialties()->sync()` dengan `is_primary`; `status`: `DoctorStatus` aktif/nonaktif — saat dinonaktifkan memanggil `ScheduleChangeService::applyDoctorInactive()` (sesi tanpa appointment aktif → `cancelled`; dengan appointment aktif → `needs_review` sumber `doctor_inactive`) dan menampilkan flash `warning` "N sesi perlu ditindaklanjuti petugas pendaftaran"; `archive`: `status=archived`, `archived_at=now()`, hanya bila tidak ada appointment aktif mendatang (00 F.4; ditolak di `ArchiveDoctorRequest` dengan pesan "selesaikan sesi terdampak dahulu"), lalu `applyDoctorInactive()` dan templat dokter dinonaktifkan; tidak ada `destroy`; `photo`: ganti foto (`MediaService::replace($doctor,'photo',…)`) dengan alt text wajib. `show`: profil, templat jadwal, jumlah sesi mendatang (tanpa nama pasien). | `admin.doctors.*` | `MediaService`, `ScheduleChangeService` |
| `SpecialtyController` | P-CRUD (tanpa `show`) | Hapus ditolak bila dipakai dokter/poliklinik. | `admin.specialties.*` | — |
| `ClinicController` | P-CRUD (tanpa `show`) | Memproses `cover` (tunggal) dan `gallery` (banyak) via `MediaService`; `description` rich text disanitasi; `code` (≤5 huruf besar) tidak boleh diubah bila sudah ada appointment (nomor antrean tercetak) **[ASUMSI]**; hapus ditolak bila dirujuk. | `admin.clinics.*` | `MediaService` |
| `DoctorScheduleController` | `index`, `create`, `store`, `edit`, `update`, `destroy` | `store`: simpan templat → `PracticeSessionGenerator::ensureRange(today, today+window, $schedule)`. `update`: simpan `getOriginal()` → update → `ScheduleChangeService::applyTemplateChange($schedule, $before, $actor)` → flash ringkasan `ScheduleChangeReport`; bila ada `needsReview` → flash `warning` "N sesi perlu ditindaklanjuti petugas pendaftaran" (dengan tautan ke `admin.sessions.review` hanya bila pengguna punya `sessions.review`). `destroy` (00 C): hanya bila tidak ada sesi ber-appointment (sesi tanpa appointment milik templat ikut dihapus oleh `ScheduleChangeService::deleteTemplate()` dalam satu transaksi); selain itu ditolak dan tombol diganti "Nonaktifkan" (flash `error` dengan alasan). | `admin.schedules.*` | `PracticeSessionGenerator`, `ScheduleChangeService` |
| `PracticeSessionController` | `index`, `create`, `store`, `edit`, `update`, `status`, `destroy` | Daftar sesi (rentang tanggal, dokter, poliklinik, status; kolom `booked_count/quota` tanpa nama pasien); `store` membuat sesi tambahan (`doctor_schedule_id` NULL); `update` (00 F.4): kuota langsung (≥ `booked_count`); jam — langsung bila tanpa appointment aktif, selain itu sesi menjadi `needs_review` (sumber `manual_time`); selalu menyetel `is_manually_modified`; `status`: buka/tutup langsung; batalkan — langsung bila tanpa appointment aktif, selain itu `needs_review` (sumber `manual_cancel`, alasan wajib); `destroy` (00 C): hanya sesi tambahan manual tanpa appointment sama sekali (`ScheduleChangeService::deleteManualSession()`), selain itu ditolak dan diganti tombol "Tutup/Batalkan". Flash `warning` "N sesi perlu ditindaklanjuti petugas pendaftaran" bila ada `needs_review`. | `admin.sessions.index/create/edit` | `ScheduleChangeService::createManualSession()/updateSession()/setSessionStatus()/deleteManualSession()` |
| `SessionReviewController` | `index`, `resolve` | `index`: sesi `needs_review` beserta `pending_change`, jumlah appointment aktif, pilihan resolusi menurut sumber (`ScheduleChangeService::allowedResolutions()` ← `SessionResolution::allowedFor`; enam sumber: `exception`, `template_time`, `template_removed`, `manual_time`, `manual_cancel`, `doctor_inactive`), peringatan benturan jam pasien (`conflictsFor()`); halaman memakai `<x-module-tabs group="schedules">`. `resolve`: `ScheduleChangeService::resolve($session, $resolution, $actor, $reason)`. | `admin.sessions.review` | `ScheduleChangeService` |
| `ScheduleExceptionController` | P-CRUD (tanpa `show`) | `store`: simpan → `applyException()`; `update`: simpan rentang lama → `applyException($e, $previous)` (membuka kembali tanggal yang dilepas); `destroy`: `removeException()` lalu hapus. | `admin.exceptions.*` | `ScheduleChangeService` |
| `FacilityController` | P-CRUD (tanpa `show`) | `cover` + `gallery`; rich text. | `admin.facilities.*` | `MediaService` |
| `RoomCategoryController` | P-CRUD (tanpa `show`) + `availability` | `amenities` disimpan JSON array teks; `availability`: set `beds_available` (0…`bed_capacity`), `availability_updated_at=now()`, `availability_updated_by` (`saveQuietly()` + audit `room.availability_updated`); `index` menampilkan form ketersediaan inline bagi pemilik izin `rooms.update-availability`; `destroy` mengikuti 00 C ("hapus bila tidak dirujuk"): tidak ada tabel yang merujuk kategori kamar sehingga `deletionBlockedReason()` selalu `null` dan media ikut dihapus, dengan peringatan "lebih baik dinonaktifkan" pada modal konfirmasi. | `admin.room-categories.*` | `MediaService` |
| `PatientController` | `index`, `create`, `store`, `show`, `edit`, `update`, `status`, `linkAccount` | `index`: pencarian nama/no. pasien/telepon/tanggal lahir; `store`: `PatientRegistrationService::createWalkIn()` (profil tanpa akun, `registration_channel=loket`, persetujuan lisan `verbal_consent` dicentang petugas); `update`: `PatientRegistrationService::updateProfile()` — untuk profil berakun, perubahan email memperbarui `users.email` dan `patient_profiles.email` dalam satu transaksi dengan validasi unik di `users` (00 E.1); `status`: `PatientRegistrationService::changeStatus()` — `is_active` profil, dan bila profil punya akun, `users.is_active` ikut disetel sama (dan sesi login akun dihapus saat nonaktif) dalam satu transaksi (00 E.1); `linkAccount`: cari akun pasien dari `account_email` → `PatientAccountLinker::link()`. Nomor BPJS selalu tersamar. | `admin.patients.*` | `PatientRegistrationService`, `PatientAccountLinker` |
| `AppointmentController` | `index`, `create`, `store`, `show`, `transition`, `proof`, `export` | `create`: walk-in GET-berlangkah (query `pasien` = id profil, `sesi`, `kembali`; `Admin\CreateWalkInAppointmentRequest`, indikator `<x-wizard-steps>`: Pasien → Sesi → Pembayaran) dengan `DoctorAvailabilityQuery` (aturan loket, `isBookableCounter`); `store`: `BookingService::book(..., Actor::staff)` (kanal `loket`, status awal `confirmed`); `show`: tombol transisi dari `AppointmentStatusService::availableTransitions()`; `transition`: `AppointmentStatusService::transition()`; `export`: `AppointmentCsvExporter::stream()`. | `admin.appointments.*` | `BookingService`, `AppointmentStatusService`, `AppointmentCsvExporter`, `AppointmentProofService` |
| `QueueController` | `index`, `markNoShow` | `index`: antrean per sesi pada tanggal terpilih (default hari ini), urut `queue_number`, kolom pencarian kode booking (dinormalkan `BookingCodeGenerator::normalize()`; kompatibel pemindai USB); `markNoShow`: `AppointmentStatusService::markNoShowForSession()` (hanya setelah `end_time`). | `admin.queue.index` | `AppointmentStatusService` |
| `PageController` | `index`, `edit`, `update` | Hanya sunting (tanpa tambah/hapus; `is_system`); `key` tidak dapat diubah; `body` hanya untuk halaman berisi rich text (00 E.5); mengisi `updated_by`. | `admin.pages.*` | — |
| `BannerController` | P-CRUD (tanpa `show`) | Gambar `image` wajib (min 1200×400, maks 4 MB); `button_url` internal atau https; jadwal tayang `starts_at`/`ends_at`. | `admin.banners.*` | `MediaService` |
| `ArticleController` | P-CRUD (tanpa `show`) | `slug` unik otomatis dari judul; `author_user_id` = pengguna; `status=published` mengisi `published_at` bila kosong; `destroy` = *soft delete*; `cover` tunggal. | `admin.articles.*` | `MediaService` |
| `ArticleCategoryController` | P-CRUD (tanpa `show`) | Hapus → artikel jadi tanpa kategori (`nullOnDelete`). | `admin.article-categories.*` | — |
| `GalleryController` | P-CRUD (tanpa `show`) + `storePhotos` | `storePhotos`: unggah banyak foto (`photos`) dengan alt text per foto; urutan/hapus lewat `admin.media.*`; `destroy` menghapus seluruh foto album. | `admin.galleries.*` | `MediaService` |
| `FaqController` | P-CRUD (tanpa `show`) | `category` ∈ `pendaftaran, bpjs, layanan, umum`; `answer` teks biasa. | `admin.faqs.*` | — |
| `InsurancePartnerController` | P-CRUD (tanpa `show`) | `logo` opsional; data seed berlabel "Contoh"; hapus ditolak bila dirujuk appointment. | `admin.insurance-partners.*` | `MediaService` |
| `SettingController` | `edit`, `update` | Tab grup `identitas`, `kontak`, `booking`, `seo`, `privasi` (00 E.6); `update` → `SettingsService::setMany()` (validasi tipe per kunci, URL peta dibatasi domain Google Maps), logo situs via `MediaService` (koleksi `logo`) → `site.logo_media_id`; audit `settings.updated` dengan nilai lama/baru. | `admin.settings.edit` | `SettingsService`, `MediaService` |
| `StaffController` | `index`, `create`, `store`, `edit`, `update`, `status`, `resetPassword` | Hanya akun `staff`; `store`/`resetPassword` menghasilkan **password sementara yang ditampilkan sekali** lewat flash (tidak disimpan teks biasa) + `must_change_password=true`; `status` dan `update` dengan penjagaan aturan mutlak 00 G: minimal satu Super Admin aktif, tidak menonaktifkan atau mengubah peran akun sendiri. | `admin.staff.*` | `Staff\StaffAccountService` |
| `RoleController` | `index`, `edit`, `update` | Matriks izin per peran; peran `super-admin` hanya-baca dan terkunci; `update` sinkronisasi izin + `permission:cache-reset`; audit `role.permissions_updated`. | `admin.roles.*` | `StaffAccountService::syncRolePermissions()` |
| `AuditLogController` | `index`, `show` | Hanya-baca; filter tanggal, pengguna, awalan `action`, `auditable_type`; paginasi 25; `show` menampilkan perbandingan `old_values`/`new_values` (sudah ber-redaksi). Tidak ada ubah/hapus. | `admin.audit-logs.*` | — |
| `ContactMessageController` | `index`, `show`, `status` | `show` mengubah `new` → `read` (mencatat `handled_by/at` saat `replied`); balas lewat tautan `mailto:`/`wa.me` manual (bukan pengiriman otomatis). | `admin.messages.*` | — |
| `MediaController` | `editorUpload`, `update`, `destroy`, `reorder` | JSON/redirect: `editorUpload` → `MediaService::store($file,'editor',null,…)` (model NULL, dibersihkan `media:prune` bila tak terpakai); `update` alt/caption; `destroy` → `MediaService::delete()`; `reorder` → `MediaService::reorder($owner,$collection,$ids)`. Izin ditentukan `MediaPolicy` menurut pemilik media. | — | `MediaService` |

---
## 5. Model Eloquent

Semua model berada di `App\Models`, memakai `HasFactory`, dan **mengikuti nama tabel/kolom 00 §E** (kolom lengkap: [09](09-data-dictionary.md)). Aturan umum:

- **Mass assignment:** daftar `$fillable` eksplisit (tidak memakai `$guarded = []`). Kolom yang hanya boleh diubah oleh service (`booked_count`, `last_queue_number`, `active_flag`, `patient_number`, `*_last4`, `account_type`, `is_active` pada `users`, timestamp status appointment) **tidak** ada di `$fillable`; diubah lewat penugasan atribut langsung/`forceFill()` di service. Mode ketat (§3.3) membuat salah penugasan langsung gagal saat pengembangan.
- **Route key:** tidak ada model yang menimpa `getRouteKeyName()`; slug/`booking_code` ditulis eksplisit di route (§2.4).
- **Kolom `TIME`** (`start_time`, `end_time`) dibaca sebagai string `H:i:s`; accessor `startsAt`/`endsAt` menggabungkannya dengan `session_date`.
- **Kolom enum** dicast ke enum PHP (`App\Enums`, [10 §4](10-struktur-folder.md)).
- **Kolom terenkripsi** (`bpjs_number`, `payment_reference`): cast `encrypted`, masuk `$hidden`, tidak pernah dimasukkan ke audit/log/CSV (00 E.6, L).
- **Trait kustom** (`App\Models\Concerns`):

```php
trait HasMedia {                                   // dipakai Doctor, Clinic, Facility, RoomCategory, Article, Banner, Gallery, InsurancePartner
    public function media(): MorphMany;            // morphMany(Media::class,'model') ordered by sort_order, id
    public function firstMedia(string $collection): ?Media;   // memakai relasi `media` bila sudah di-eager-load (tanpa N+1), kalau tidak query
    public function mediaIn(string $collection): Collection; // semua media satu koleksi (urut)
    public function mediaUrl(string $collection, string $variant = 'full', ?string $placeholder = null): string; // fallback placeholder lokal
}
trait SanitizesRichText {                          // Clinic.description, Facility.description, RoomCategory.description, Page.body, Article.body
    // model mendeklarasikan: protected function richTextAttributes(): array { return ['description']; }
    // bootSanitizesRichText(): static::saving(fn ($m) => … HtmlSanitizer::clean() untuk atribut yang isDirty)
}
```

### 5.1 Akun dan pasien

**`User`** (`users`) — traits `HasFactory`, `Notifiable`, `HasRoles` (Spatie, guard `web`).
- `$fillable`: `name`, `email`, `phone`, `password`. `account_type`, `is_active`, `must_change_password`, `last_login_at` diubah via `forceFill()` oleh `PatientRegistrationService`/`StaffAccountService`/controller login.
- `$hidden`: `password`, `remember_token`. Casts: `account_type`→`AccountType`, `is_active`/`must_change_password`→`bool`, `last_login_at`/`email_verified_at`→`datetime`, `password`→`hashed`.
- Relasi: `patientProfile(): HasOne` (profil milik akun), `createdPatientProfiles(): HasMany` (via `created_by_user_id`), `notifications()` (Notifiable, UUID).
- Scope: `staff()`, `patients()`, `active()`. Method: `isStaff()`, `isPatient()`. Accessor `roleLabel` (Super Admin / Admin Konten / Petugas Pendaftaran dari nama peran).
- Catatan: tanpa `SoftDeletes` (dinonaktifkan, bukan dihapus); tidak `MustVerifyEmail` (00 E.1); `sendPasswordResetNotification` memakai bawaan (rute `password.reset`).

**`PatientProfile`** (`patient_profiles`)
- `$fillable`: `user_id`, `full_name`, `birth_date`, `gender`, `phone`, `email`, `address`, `bpjs_number`, `registration_channel`, `created_by_user_id`, `consent_at`, `consent_version`, `is_active`.
- `$hidden`: `bpjs_number`. Casts: `birth_date`→`date`, `gender`→`Gender`, `bpjs_number`→`encrypted`, `registration_channel`→`BookingChannel`, `consent_at`→`datetime`, `is_active`→`bool`.
- Relasi: `user(): BelongsTo`, `appointments(): HasMany`, `creator(): BelongsTo` (User, `created_by_user_id`).
- Scope: `active()`, `walkIn()` (`user_id IS NULL`), `search(string $term)` (nama `LIKE`, `patient_number` sama persis, telepon `LIKE`, `birth_date` bila `$term` berformat tanggal).
- Accessor/method: `maskedBpjs` (`•••••••••` + `bpjs_number_last4`, atau `null`), `age`, `isWalkIn()`, `hasAppointments(): bool`.
- Event `booted()`: `creating` → `patient_number` sementara (`T` + 9 karakter acak; kolom ≥ `VARCHAR(10)`); `created` → `forceFill(['patient_number' => PatientNumberGenerator::format($id)])->saveQuietly()` (format `P000123`); `saving` → bila `bpjs_number` berubah, isi `bpjs_number_last4` (4 digit terakhir) atau `NULL`.

### 5.2 Spesialisasi, poliklinik, dokter

**`Specialty`** (`specialties`): `$fillable` `name`, `slug`, `description`, `is_active`, `sort_order`. Relasi `doctors(): BelongsToMany` (tabel `doctor_specialties`, `withPivot('is_primary')`), `clinics(): HasMany`. Scope `active()`, `ordered()`. Method `deletionBlockedReason(): ?string` (dipakai dokter/poliklinik).

**`Clinic`** (`clinics`) — `HasMedia` (`cover`, `gallery`), `SanitizesRichText` (`description`).
- `$fillable`: `name`, `slug`, `code`, `type`, `is_bookable`, `specialty_id`, `short_description`, `description`, `location`, `is_active`, `sort_order`, `meta_title`, `meta_description`. Casts: `type`→`ClinicType`, `is_bookable`/`is_active`→`bool`, `sort_order`→`int`. Mutator `code` → huruf kapital.
- Relasi: `specialty()`, `doctors(): HasMany`, `schedules(): HasMany` (`DoctorSchedule`), `sessions(): HasMany` (`PracticeSession`), `appointments(): HasMany`.
- Scope: `active()`, `bookable()` (aktif **dan** `is_bookable`), `ordered()`. Accessor: `coverUrl`, `seoTitle` (`meta_title` ?: `name`), `seoDescription` (`meta_description` ?: `short_description`). Method `deletionBlockedReason()`.

**`Doctor`** (`doctors`) — `HasMedia` (`photo`).
- `$fillable`: `clinic_id`, `name`, `slug`, `title_prefix`, `title_suffix`, `gender`, `short_bio`, `bio`, `status`, `sort_order`, `meta_title`, `meta_description` (`archived_at` diset service/controller). Casts: `gender`→`Gender`, `status`→`DoctorStatus`, `archived_at`→`datetime`, `sort_order`→`int`.
- Relasi: `clinic(): BelongsTo`, `specialties(): BelongsToMany` (`doctor_specialties`, `withPivot('is_primary')`), `schedules(): HasMany`, `sessions(): HasMany`, `appointments(): HasMany`.
- Scope: **`scopeActive`** (`status = active`), `search(?string $q)` (nama atau nama spesialisasi), `inSpecialty($idOrSlug)`, `inClinic($id)`, `ordered()`.
- Accessor (`Attribute`): **`displayName`** = `trim("{title_prefix} {name}")` + (`title_suffix` ? `", {title_suffix}"` : `""`), mis. `dr. Andi Pratama, Sp.U`; **`photoUrl`** (media `photo`; fallback `/images/placeholders/doctor.svg`), `photoThumbUrl`, `primarySpecialty` (pivot `is_primary` dari relasi `specialties` yang sudah dimuat, fallback spesialisasi pertama).

### 5.3 Jadwal dan sesi

**`DoctorSchedule`** (`doctor_schedules`): `$fillable` `doctor_id`, `clinic_id`, `day_of_week`, `start_time`, `end_time`, `quota`, `valid_from`, `valid_until`, `is_active`, `notes` (`created_by`/`updated_by` diisi event `saving` dari `Auth::id()`). Casts `day_of_week`/`quota`→`int`, `valid_from`/`valid_until`→`date`, `is_active`→`bool`. Relasi `doctor()`, `clinic()`, `sessions(): HasMany`, `creator()`, `updater()`. Scope `active()`, `effectiveOn($date)` (`valid_from ≤ date ∧ (valid_until null ∨ ≥ date)`), `onDay(int $iso)`. Accessor `dayName` (Senin…Minggu), `timeRange` (`08.00–12.00`). Method `hasUpcomingActiveAppointments()`, `deletionBlockedReason()`.

**`ScheduleException`** (`schedule_exceptions`): `$fillable` `doctor_id`, `type`, `start_date`, `end_date`, `reason` (`created_by` event `creating`). Casts `type`→`ExceptionType`, `start_date`/`end_date`→`date`. Relasi `doctor()` (nullable), `creator()`, `sessions(): HasMany` (via `schedule_exception_id`). Scope `covering($date)`, `forDoctor($id)` (`doctor_id IS NULL OR = id`), `upcoming()` (`end_date ≥ hari ini`). Accessor `isGlobal` (`doctor_id` NULL), `rangeLabel`.

**`PracticeSession`** (`practice_sessions`) — baris yang dikunci saat booking.
- `$fillable`: `doctor_id`, `clinic_id`, `doctor_schedule_id`, `session_date`, `start_time`, `end_time`, `quota`, `status`, `status_reason`, `schedule_exception_id`, `pending_change`, `is_manually_modified`, `reviewed_by`, `reviewed_at`. **Bukan fillable:** `booked_count`, `last_queue_number` (hanya `BookingService`/`AppointmentStatusService` mengubahnya di bawah `lockForUpdate()`).
- Casts: `session_date`→`date`, `quota`/`booked_count`/`last_queue_number`→`int`, `status`→`SessionStatus`, `pending_change`→`array`, `is_manually_modified`→`bool`, `reviewed_at`→`datetime`.
- Relasi: `doctor()`, `clinic()`, `schedule()` (`DoctorSchedule`, `doctor_schedule_id`), `exception()` (`ScheduleException`, `schedule_exception_id`), `appointments(): HasMany`, `reviewer()`.
- Scope: **`scopeBetween($from, $to)`** (`session_date BETWEEN`), `onDate($date)`, `upcoming()` (≥ hari ini), `open()`, `needsReview()`, `forDoctor($id)`, `forClinic($id)`.
- Accessor/method: `remaining` (= `quota - booked_count`), `startsAt`/`endsAt` (`CarbonImmutable`), `timeRange`, `hasActiveAppointments()`, `pendingSource()` (`pending_change['source']`).

### 5.4 Booking

**`Appointment`** (`appointments`) — route key portal pasien: `booking_code`.
- `$fillable`: `booking_code`, `patient_profile_id`, `practice_session_id`, `doctor_id`, `clinic_id`, `session_date`, `queue_number`, `status`, `channel`, `payment_type`, `insurance_partner_id`, `payment_reference`, `complaint`, `created_by_user_id`. Kolom waktu status (`confirmed_at`, `checked_in_at`, `completed_at`, `cancelled_at`, `no_show_at`), `cancelled_by_*`, `cancel_reason`, `schedule_changed_at` diisi `AppointmentStatusService`/`ScheduleChangeService`; `active_flag` (generated) tidak pernah ditulis.
- `$hidden`: `payment_reference`. Casts: `session_date`→`date`, `queue_number`→`int`, `status`→`AppointmentStatus`, `channel`→`BookingChannel`, `payment_type`→`PaymentType`, `payment_reference`→`encrypted`, `*_at`→`datetime`, `cancelled_by_type`→`ActorType`.
- Relasi: `patientProfile()`, `practiceSession()`, `doctor()`, `clinic()`, `insurancePartner()`, `statusHistories(): HasMany` (urut `id`), `creator()` (`created_by_user_id`), `canceller()` (`cancelled_by_user_id`).
- Scope: **`scopeActive`** (`status IN AppointmentStatus::active()`), `quotaConsuming()`, **`scopeForPatient(PatientProfile|int $p)`**, `onDate($d)`, `upcoming()`, `search($term)` (kode booking ternormalisasi / nama pasien), `inStatus(AppointmentStatus $s)`, `scheduleChanged()` (`schedule_changed_at IS NOT NULL`; untuk query `jadwal_berubah=1`).
- Accessor/method: **`queueLabel`** (`QueueNumberFormatter::format($clinic->code, $queue_number)` → `ANK-007`; wajib *eager load* `clinic`), `maskedPaymentReference` (4 digit terakhir), `canBeCancelledByPatient(): bool` (status `pending|confirmed` dan `now ≤ startsAt − booking.cancel_cutoff_minutes`), `hasScheduleChanged()`.
- Event `saving`: bila `payment_reference` berubah → isi `payment_reference_last4`.

**`AppointmentStatusHistory`** (`appointment_status_histories`): `const UPDATED_AT = null`; `$fillable` `appointment_id`, `from_status`, `to_status`, `actor_type`, `actor_user_id`, `reason`; casts `from_status`/`to_status`→`AppointmentStatus`, `actor_type`→`ActorType`; relasi `appointment()`, `actor()` (User).

### 5.5 Fasilitas dan kamar

**`RoomCategory`** (`room_categories`) — `HasMedia` (`cover`, `gallery`), `SanitizesRichText` (`description`). `$fillable`: `name`, `slug`, `short_description`, `description`, `amenities`, `bed_capacity`, `beds_available`, `sample_rate`, `rate_unit`, `rate_note`, `is_active`, `sort_order`, `availability_updated_at`, `availability_updated_by`, `meta_title`, `meta_description`. Casts: `amenities`→`array`, `bed_capacity`/`beds_available`→`int`, `sample_rate`→`decimal:2`, `is_active`→`bool`, `availability_updated_at`→`datetime`. Relasi `availabilityUpdater()`. Scope `active()`, `ordered()`. Accessor `coverUrl`, `sampleRateLabel` (`Number::currency($rate, 'IDR', 'id', precision: 0)` + `rate_unit`; NULL → "Hubungi kami"), `occupancyLabel` ("12 dari 30 tempat tidur tersedia"), `isFull`. Method `deletionBlockedReason()` → selalu `null` (tidak dirujuk riwayat).

**`Facility`** (`facilities`) — `HasMedia`, `SanitizesRichText`. `$fillable`: `name`, `slug`, `category`, `short_description`, `description`, `is_active`, `sort_order`, `meta_title`, `meta_description`. Cast `category`→`FacilityCategory`. Scope `active()`, `ordered()`, `ofCategory(FacilityCategory $c)`. Accessor `coverUrl`.

### 5.6 Konten

**`Page`** (`pages`) — `SanitizesRichText` (`body`). `$fillable`: `title`, `subtitle`, `body`, `meta_title`, `meta_description`, `updated_by` (`key`, `is_system` tidak fillable). Cast `is_system`→`bool`. Relasi `updater()`. `static forKey(string $key): self` (`firstOrFail`); konstanta `Page::KEYS_WITH_BODY` (`about`, `vision-mission`, `insurance-info`, `registration-guide`, `privacy-policy`); `hasBody()`.

**`ArticleCategory`** (`article_categories`): `$fillable` `name`, `slug`, `description`, `sort_order`; relasi `articles(): HasMany`; scope `ordered()`.

**`Article`** (`articles`) — `SoftDeletes`, `HasMedia` (`cover`), `SanitizesRichText` (`body`). `$fillable`: `article_category_id`, `author_user_id`, `title`, `slug`, `excerpt`, `body`, `status`, `published_at`, `meta_title`, `meta_description`. Casts `status`→`ArticleStatus`, `published_at`→`datetime`. Relasi `category(): BelongsTo` (`ArticleCategory`), `author(): BelongsTo` (`User`). Scope **`scopePublished`** (`status = published AND published_at ≤ now()`), `search($q)`, `inCategory($slug)`. Accessor `coverUrl`, `readingTimeMinutes`.

**`Banner`** (`banners`) — `HasMedia` (`image`). `$fillable`: `title`, `subtitle`, `button_label`, `button_url`, `sort_order`, `is_active`, `starts_at`, `ends_at`. Casts `is_active`→`bool`, `sort_order`→`int`, `starts_at`/`ends_at`→`datetime`. Scope **`scopeCurrentlyActive`** (`is_active ∧ (starts_at IS NULL ∨ starts_at ≤ now) ∧ (ends_at IS NULL ∨ ends_at ≥ now)`, urut `sort_order`; `starts_at` NULL = langsung tayang, sesuai 09). Accessor `imageUrl`.

**`Gallery`** (`galleries`) — `HasMedia` (`photos`). `$fillable`: `title`, `slug`, `description`, `is_published`, `sort_order`. Scope `published()`, `ordered()`. Accessor `coverUrl` (foto pertama atau placeholder).

**`Faq`** (`faqs`): `$fillable` `category`, `question`, `answer`, `sort_order`, `is_published`; konstanta `Faq::CATEGORIES` (`pendaftaran`, `bpjs`, `layanan`, `umum`); scope `published()`, `ordered()`, `inCategory($c)`.

**`InsurancePartner`** (`insurance_partners`) — `HasMedia` (`logo`). `$fillable`: `name`, `slug`, `type`, `description`, `website_url`, `is_active`, `sort_order`. Scope `active()`, `ordered()`. Accessor `logoUrl`. Method `deletionBlockedReason()` (dirujuk `appointments`).

**`ContactMessage`** (`contact_messages`): `$fillable` `name`, `email`, `phone`, `subject`, `message`, `status`, `consent_at`, `ip_hash` (`handled_by`/`handled_at` via `forceFill`). Casts `status`→`ContactMessageStatus`, `consent_at`/`handled_at`→`datetime`. Relasi `handler()`. Scope `unread()` (`status = new`).

### 5.7 Sistem

**`Media`** (`media`): `$fillable` `model_type`, `model_id`, `collection`, `disk`, `directory`, `file_name`, `original_name`, `mime_type`, `extension`, `size_bytes`, `width`, `height`, `thumb_path`, `alt_text`, `caption`, `sort_order`, `uploaded_by`. Casts `size_bytes`/`width`/`height`/`sort_order`→`int`. Relasi `model(): MorphTo`, `uploader()`. Scope `inCollection($c)`, `orphaned(int $hours = 24)` (`model_id IS NULL` & `created_at < now − hours`). Accessor `url` (disk `public` → `Storage::disk('public')->url("{directory}/{file_name}")`; disk `local` → `null`, dilayani controller), `thumbUrl`, `humanSize`, `isPublic()`.

**`WebsiteSetting`** (`website_settings`): `$fillable` `key`, `value`, `type`, `group`, `label`, `updated_by`. Tidak dibaca langsung oleh view; seluruh akses lewat `SettingsService` (cast menurut `type`, cache).

**`AuditLog`** (`audit_logs`): `const UPDATED_AT = null`; `$fillable` `actor_type`, `user_id`, `action`, `auditable_type`, `auditable_id`, `description`, `old_values`, `new_values`, `ip_address`, `user_agent`. Casts `actor_type`→`ActorType`, `old_values`/`new_values`→`array`. Relasi `user()`, `auditable(): MorphTo`. Scope `forAction(string $prefix)`, `between($from, $to)`. **Tidak dapat diubah/dihapus:** `booted()` melempar `LogicException` pada `updating` dan `deleting`; UI tidak menyediakan aksi ubah/hapus.

### 5.8 Pivot dan model tanpa kelas

`doctor_specialties` tidak punya model: diakses lewat `Doctor::specialties()` / `Specialty::doctors()` (`withPivot('is_primary')`). Tabel Spatie memakai `Spatie\Permission\Models\Role` dan `Permission`. Tabel bawaan Laravel (`sessions`, `cache`, `jobs`, `notifications`, `password_reset_tokens`) tanpa model aplikasi (notifikasi lewat `$user->notifications`).

### 5.9 `AuditObserver`

```php
class AuditObserver {
    public function created(Model $m): void  { $this->log($m, 'created', [], $m->getAttributes()); }
    public function updated(Model $m): void  { $changes = Arr::except($m->getChanges(), ['updated_at']);
                                               if ($changes) $this->log($m, 'updated', Arr::only($m->getOriginal(), array_keys($changes)), $changes); }
    public function deleted(Model $m): void  { $this->log($m, 'deleted', $m->getOriginal(), []); }
    // aksi: Str::snake(class_basename($m)).".{$event}" → 'clinic.updated'; deskripsi Indonesia singkat; pelaku dari Auth/Actor::system()
}
```
Redaksi ditangani `AuditLogger` (tingkat 1: kunci `password`, `remember_token`, `token`, `bpjs_number`, `payment_reference` → `[disamarkan]`; tingkat 2 sesuai 12 §8.1: kolom PII `address`, `phone`, `email`, `birth_date`, `complaint`, `message` hanya untuk entitas pasien/pendaftaran/pesan, dan teks panjang `body`, `bio`, `short_bio`, `description` dicatat sebagai `[konten diubah]`; teks > 500 karakter dipotong `…[dipotong]`).

---
## 6. Service

Seluruh aturan bisnis berada di `app/Services`. Service bersifat tanpa status (kecuali cache), disuntik lewat *constructor injection*, dan **tidak membaca `request()`** (pelaku selalu dikirim eksplisit sebagai `Actor`). PHP 8.2 bertipe ketat (`declare(strict_types=1)`). Nama dan perilaku mengikuti 00 §F dan §J.1.

### 6.1 DTO dan *value object*

```php
namespace App\Support;
final readonly class Actor {                       // pelaku sebuah operasi
    public static function patient(User $u): self;   // ActorType::Patient
    public static function staff(User $u): self;     // ActorType::Staff
    public static function system(): self;           // ActorType::System (command / otomatis), userId = null
    private function __construct(public ActorType $type, public ?int $userId) {}
    public function isPatient(): bool; public function isStaff(): bool; public function isSystem(): bool;
    public function user(): ?User;
    public function channel(): BookingChannel;       // staf → Loket; pasien → Online
}

namespace App\Services\Booking;
final readonly class BookingData {                 // masukan tervalidasi dari Form Request
    public function __construct(
        public PaymentType $paymentType,
        public ?int $insurancePartnerId = null,
        public ?string $paymentReference = null,    // nomor BPJS 13 digit atau nomor polis
        public ?string $complaint = null,           // ≤ 500 karakter
    ) {}
    public static function fromValidated(array $validated): self;
}

namespace App\Services\Scheduling;
final readonly class AvailabilityFilter {          // masukan DoctorAvailabilityQuery
    public function __construct(
        public ?int $clinicId = null, public ?int $doctorId = null, public ?int $specialtyId = null,
        public ?string $q = null,                   // nama dokter
        public ?CarbonImmutable $date = null,       // satu tanggal; atau rentang:
        public ?CarbonImmutable $from = null, public ?CarbonImmutable $to = null,
        public bool $forLoket = false,              // aturan loket (batas = end_time) alih-alih online
        public bool $includePast = false,
    ) {}
}
final readonly class SessionAvailability {         // keluaran DoctorAvailabilityQuery per sesi
    public function __construct(
        public PracticeSession $session,
        public int $remaining,                      // quota - booked_count (min 0)
        public Availability $availability,
        public bool $isBookableOnline,
        public bool $isBookableCounter,
    ) {}
}
final readonly class ScheduleChangeReport {        // keluaran ScheduleChangeService (untuk flash message)
    public function __construct(public int $regenerated = 0, public int $updated = 0, public int $quotaAdjusted = 0,
                                public int $needsReview = 0, public int $cancelled = 0, public int $reopened = 0) {}
    public function message(): string;              // "3 sesi diperbarui, 2 sesi perlu ditindaklanjuti petugas pendaftaran."
}

namespace App\Services\Booking;
final readonly class BookingRules {                // snapshot booking.* (dibaca sekali per operasi) + pemeriksaan aturan F.5 (00 J.6)
    public function __construct(public int $windowDays, public int $onlineCutoffMinutes, public int $cancelCutoffMinutes,
                                public int $checkinOpenMinutes, public int $maxActivePerPatient, public bool $autoConfirm) {}
    public static function fromSettings(SettingsService $settings): self;                  // dipakai SettingsService::bookingRules()
    public function assertBookable(PatientProfile $p, PracticeSession $s, BookingData $d, Actor $actor, CarbonImmutable $now): void;
                                                                                           // F.5 butir 1–8; melempar BookingException (dipanggil di dalam transaksi, setelah kunci)
}

namespace App\Services\Media;
final readonly class MediaPruneReport { public function __construct(public int $orphanRows, public int $orphanFiles, public int $bytes, public bool $dryRun) {} }
```

### 6.2 `Booking\BookingService`

```php
public function __construct(SettingsService $settings, BookingCodeGenerator $codes,
                            AppointmentStatusService $statuses, AuditLogger $audit) {}

public function book(PatientProfile $patient, PracticeSession $session, BookingData $data, Actor $actor): Appointment;
public function precheck(PatientProfile $patient, PracticeSession $session, BookingData $data, Actor $actor): void;   // diterima 00 J.6
```

`precheck()` menjalankan pemeriksaan F.5 (`BookingRules::assertBookable`) **tanpa kunci dan tanpa menulis** (satu pembacaan biasa, tanpa transaksi) untuk langkah tinjau wizard (`patient.appointments.review`) dan walk-in admin; hasilnya tidak mengikat. `book()` tetap memeriksa ulang di dalam transaksi setelah penguncian. Galatnya `BookingException` yang sama (tanpa `SYSTEM_BUSY`, karena tidak ada penguncian).

**Perilaku langkah demi langkah** (00 F.5–F.6; semua pemeriksaan bisnis di dalam transaksi setelah penguncian):

```
book($patient, $session, $data, $actor):
  0. Penjagaan tanpa DB:
       $actor->isSystem()                                  → LogicException (sistem tidak membuat booking)
       $actor->isPatient() && $patient->user_id !== $actor->userId → AuthorizationException
       !$patient->is_active                                → AuthorizationException('Profil pasien tidak aktif.')
  1. $rules = $settings->bookingRules()                    // snapshot di luar transaksi
  2. $lock = Cache::lock("booking-submit:{$actor->userId}", 10)
       !$lock->get() → BookingException::duplicateBooking('Permintaan pendaftaran sebelumnya masih diproses.')   // cegah kirim ganda
  3. try, ulangi (kode baru) maks 5 kali bila UNIQUE booking_code bentrok:
       $appointment = DB::transaction(function () {
         // --- urutan kunci baku: patient_profiles → practice_sessions → appointments (00 F.6)
         $p = PatientProfile::whereKey($patient->id)->lockForUpdate()->firstOrFail();   // serialisasi per pasien
         $s = PracticeSession::whereKey($session->id)->with(['doctor','clinic'])->lockForUpdate()->firstOrFail(); // serialisasi per sesi
         $now = CarbonImmutable::now('Asia/Jakarta');

         // --- validasi F.5 (urutan = prioritas pesan); diimplementasikan di BookingRules::assertBookable($p, $s, $data, $actor, $now)
         (1) $s->status != Open || $s->doctor->status != Active || !$s->clinic->is_active || !$s->clinic->is_bookable
                                                                   → SESSION_UNAVAILABLE
         (2) $s->session_date < today || $s->session_date > today + $rules->windowDays          → OUTSIDE_WINDOW
         (3) online: $now >= $s->startsAt - $rules->onlineCutoffMinutes menit                    → CUTOFF_PASSED
             loket : $now >= $s->endsAt                                                          → CUTOFF_PASSED
         (4) $s->booked_count >= $s->quota                                                      → QUOTA_FULL
         (5) Appointment::forPatient($p)->active()->where('practice_session_id',$s->id)->exists() → DUPLICATE_BOOKING
         (6) Appointment::forPatient($p)->active()->whereDate('session_date',$s->session_date)
               ->where('practice_session_id','!=',$s->id)
               ->whereHas('practiceSession', fn($q) => $q->where('start_time','<',$s->end_time)
                                                         ->where('end_time','>',$s->start_time))
               ->exists()                                                                       → OVERLAPPING_BOOKING
         (7) online saja: Appointment::forPatient($p)->active()->where('session_date','>=',today)->count()
               >= $rules->maxActivePerPatient                                                   → ACTIVE_LIMIT_REACHED
         (8) payment_type: bpjs     → paymentReference ~ /^\d{13}$/ wajib, insurancePartnerId NULL
                           asuransi → insurancePartnerId ada & InsurancePartner aktif; paymentReference opsional
                           umum     → keduanya NULL                                             → INVALID_PAYMENT

         // --- tulis
         $s->last_queue_number += 1;  $s->booked_count += 1;  $s->save();
         $status = $actor->isStaff() ? Confirmed : ($rules->autoConfirm ? Confirmed : Pending);
         $a = Appointment::create([
               'booking_code' => $codes->generate(), 'patient_profile_id' => $p->id, 'practice_session_id' => $s->id,
               'doctor_id' => $s->doctor_id, 'clinic_id' => $s->clinic_id, 'session_date' => $s->session_date,
               'queue_number' => $s->last_queue_number, 'status' => $status, 'channel' => $actor->channel(),
               'payment_type' => $data->paymentType, 'insurance_partner_id' => …, 'payment_reference' => …,
               'complaint' => $data->complaint, 'created_by_user_id' => $actor->userId ]);
         if ($status === Confirmed) { $a->confirmed_at = $now; $a->save(); }
         AppointmentStatusHistory::create([from_status null, to_status $status, actor…, reason 'Pendaftaran dibuat']);
         $audit->log('appointment.created', $a, [], [kode, tanggal, sesi, kanal, status], "Pendaftaran {$a->booking_code} dibuat");
         DB::afterCommit(fn() => notifikasi AppointmentStatusChangedNotification bila $p->user ada);   // database; + mail hanya bila status awal confirmed (auto_confirm)
         return $a;
       }, attempts: 3);                                    // attempts mengulang deadlock (1213) dan lock wait timeout (1205)
     catch (QueryException $e) → translate($e) (di bawah): 1062/4025/3819 → BookingException sesuai constraint; 1205/1213 → SYSTEM_BUSY
     finally $lock->release()
  4. return $appointment->load(['doctor','clinic','practiceSession'])
```

**Terjemahan pelanggaran constraint dan galat penguncian**, karena constraint DB adalah lapis pengaman terakhir (00 F.6). Yang diperiksa adalah **kode galat driver `$e->errorInfo[1]`** (bukan hanya SQLSTATE 23000): duplikat UNIQUE = errno **1062**; pelanggaran CHECK = errno **4025** di MariaDB (errno 3819 di MySQL 8); lock wait timeout = **1205**; deadlock = **1213**. Nama constraint dibaca dari pesan galat untuk memilih `BookingException` (nama constraint persis 09 bagian 11.4; implementasi `translate()` ada di sana):

| errno | Pesan constraint mengandung | Diterjemahkan menjadi |
|---|---|---|
| 1062 | `appointments_patient_session_active_unique` | `DUPLICATE_BOOKING` |
| 4025 / 3819 | `` `chk_sessions_quota` `` (CHECK gagal; dengan backtick agar tidak cocok `chk_sessions_quota_range`) | `QUOTA_FULL` |
| 1062 | `appointments_practice_session_id_queue_number_unique` | `SYSTEM_BUSY` (tidak mungkin terjadi bila kunci benar; dilaporkan via `report()`) |
| 1062 | `appointments_booking_code_unique` | ulangi transaksi (kode baru, maks 5 kali); setelah itu `report()` + `SYSTEM_BUSY` |
| 1205 / 1213 | — (setelah `attempts: 3` habis; Laravel mengulang transaksi pada keduanya) | `SYSTEM_BUSY`: "Sistem sedang sibuk, silakan coba lagi beberapa saat."; rollback penuh, tidak ada data tersimpan sebagian |

**`BookingException`** (`App\Exceptions`): `final` kelas turunan `RuntimeException` dengan properti `public readonly string $errorCode` dan *named constructor* per kode; pesan diambil dari `lang/id/booking.php`:

| Kode | Pesan (Bahasa Indonesia) |
|---|---|
| `SESSION_UNAVAILABLE` | Sesi praktik ini tidak tersedia untuk pendaftaran. Silakan pilih sesi lain. |
| `OUTSIDE_WINDOW` | Pendaftaran hanya dapat dilakukan untuk tanggal dalam :days hari ke depan. |
| `CUTOFF_PASSED` | Online: Pendaftaran online untuk sesi ini sudah ditutup (batas :minutes menit sebelum praktik dimulai). Silakan datang langsung ke loket. · Loket: Sesi ini sudah berakhir. |
| `QUOTA_FULL` | Kuota sesi ini sudah penuh. Silakan pilih sesi atau tanggal lain. |
| `DUPLICATE_BOOKING` | Anda sudah memiliki pendaftaran aktif pada sesi dokter ini. · (kirim ganda) Permintaan pendaftaran sebelumnya masih diproses. |
| `OVERLAPPING_BOOKING` | Jadwal bertabrakan dengan pendaftaran Anda pada :date pukul :time (:doctor). |
| `ACTIVE_LIMIT_REACHED` | Anda sudah memiliki :max pendaftaran aktif. Selesaikan atau batalkan salah satunya sebelum mendaftar lagi. |
| `INVALID_PAYMENT` | Data pembayaran tidak lengkap atau tidak sesuai: :detail. |
| `SYSTEM_BUSY` | Sistem sedang sibuk, silakan coba lagi beberapa saat. (lock wait timeout 1205 / deadlock 1213 yang masih terjadi setelah `attempts: 3`; tidak ada data tersimpan sebagian — 00 F.5) |

`BookingException` memiliki **9 kode** (00 F.5): delapan aturan F.5 + `SYSTEM_BUSY`.

`InvalidTransitionException extends BookingException` (00 J.6; dilempar `AppointmentStatusService` untuk transisi yang tidak diizinkan, 00 F.5/F.8) memakai kode tambahan `INVALID_TRANSITION`, `ALREADY_FINAL`, `FORBIDDEN_TRANSITION`, `STALE_STATUS`, `CANCEL_CUTOFF_PASSED`, `CONFIRM_AFTER_END`, `CHECKIN_NOT_TODAY`, `CHECKIN_NOT_OPEN`, `CHECKIN_CLOSED`, `CORRECTION_NOT_SAME_DAY`, `SESSION_NOT_ENDED`, `REASON_REQUIRED` (di luar 9 kode F.5 → **[ASUMSI]**; pesan di `lang/id/booking.php`, teks sama dengan 12 BR-58…BR-67 dan 06 §8.2).

### 6.3 `Booking\AppointmentStatusService`

```php
public function transition(Appointment $appointment, AppointmentStatus $to, Actor $actor, ?string $reason = null): Appointment;
public function availableTransitions(Appointment $appointment, Actor $actor): array;   // list<AppointmentStatus> yang valid saat ini (untuk tombol UI)
public function markNoShowForSession(PracticeSession $session, Actor $actor, ?string $reason = null): int;  // aksi massal; hanya setelah end_time
public function markNoShowForDate(?CarbonInterface $date, Actor $actor): int;          // dipakai appointments:mark-no-show; $date NULL = SEMUA sesi yang end_time-nya sudah lewat (00 F.8)
public function cancelAllForSession(PracticeSession $session, Actor $actor, string $reason): int;  // dipakai resolusi `cancel`
```

**Peta transisi (data, 00 F.8):**

```php
public const TRANSITIONS = [                  // from => daftar to yang diizinkan
    'pending'    => ['confirmed', 'checked_in', 'cancelled', 'no_show'],
    'confirmed'  => ['checked_in', 'cancelled', 'no_show'],
    'checked_in' => ['completed', 'confirmed'],
    'completed'  => [], 'cancelled' => [], 'no_show' => [],      // status final
];

public const RULES = [   // kunci "from>to" ⇒ aturan; 'actors' = pelaku yang boleh; 'perm' = izin staf; 'window' = nama pemeriksa waktu
    'pending>confirmed'    => ['actors' => ['staff'],            'perm' => 'appointments.manage', 'window' => 'before_end',        'reason' => false],
    'pending>checked_in'   => ['actors' => ['staff'],            'perm' => 'appointments.manage', 'window' => 'checkin_window',    'reason' => false],
    'confirmed>checked_in' => ['actors' => ['staff'],            'perm' => 'appointments.manage', 'window' => 'checkin_window',    'reason' => false],
    'checked_in>completed' => ['actors' => ['staff'],            'perm' => 'appointments.manage', 'window' => null,                'reason' => false],
    'checked_in>confirmed' => ['actors' => ['staff'],            'perm' => 'appointments.manage', 'window' => 'same_day',          'reason' => true],   // koreksi salah check-in
    'pending>cancelled'    => ['actors' => ['patient_owner', 'staff', 'system'], 'perm' => 'appointments.manage', 'window' => 'cancel_rule', 'reason' => 'staff,system'],
    'confirmed>cancelled'  => ['actors' => ['patient_owner', 'staff', 'system'], 'perm' => 'appointments.manage', 'window' => 'cancel_rule', 'reason' => 'staff,system'],
    'pending>no_show'      => ['actors' => ['staff', 'system'],  'perm' => 'appointments.manage', 'window' => 'after_end',         'reason' => false],
    'confirmed>no_show'    => ['actors' => ['staff', 'system'],  'perm' => 'appointments.manage', 'window' => 'after_end',         'reason' => false],
];
```

| Pemeriksa `window` | Aturan (waktu = `now` Asia/Jakarta) |
|---|---|
| `before_end` | `now < session.endsAt` → selain itu `CONFIRM_AFTER_END` |
| `checkin_window` | `session_date = hari ini` ∧ `session.startsAt − booking.checkin_open_minutes ≤ now ≤ session.endsAt` → selain itu `CHECKIN_NOT_TODAY` (bukan tanggal kunjungan), `CHECKIN_NOT_OPEN` (sebelum jendela dibuka; pesan menyebut jam buka), `CHECKIN_CLOSED` (setelah `end_time`) |
| `same_day` | `session_date = hari ini` → selain itu `CORRECTION_NOT_SAME_DAY` |
| `cancel_rule` | pasien pemilik: `now ≤ session.startsAt − booking.cancel_cutoff_minutes`, selain itu `CANCEL_CUTOFF_PASSED` (pesan: hubungi RS via telepon/WhatsApp `contact.*`); staf/sistem: kapan saja sebelum check-in (status sumber sudah `pending` atau `confirmed`) |
| `after_end` | `now > session.endsAt` → selain itu `SESSION_NOT_ENDED` |

**Perilaku `transition()`:**

```
transition($a, $to, $actor, $reason):
  1. $from = $a->status
     !in_array($to->value, TRANSITIONS[$from->value])     → $from->isFinal() ? ALREADY_FINAL ("Pendaftaran ini sudah berstatus … dan tidak dapat diubah lagi.") : INVALID_TRANSITION ("Perubahan status dari … ke … tidak diperbolehkan.")
  2. $rule = RULES["{$from->value}>{$to->value}"]
     pelaku: patient → harus 'patient_owner' dan $a->patientProfile->user_id == $actor->userId
             staff   → harus 'staff' dan $actor->user()->can($rule['perm'])
             system  → harus 'system'                      → selain itu FORBIDDEN_TRANSITION
     alasan wajib (rule['reason']) untuk pelaku terkait dan kosong → REASON_REQUIRED
  3. DB::transaction(function () {
       // urutan kunci baku: patient_profiles → practice_sessions → appointments
       $p = PatientProfile::whereKey($a->patient_profile_id)->lockForUpdate()->first();
       $s = PracticeSession::whereKey($a->practice_session_id)->lockForUpdate()->first();
       $a = Appointment::whereKey($a->id)->lockForUpdate()->firstOrFail();
       $a->status !== $from → STALE_STATUS ("Status berubah oleh pengguna lain, muat ulang halaman")
       $this->window($rule['window'], $a, $s, $actor)       // pemeriksa di tabel atas
       switch ($to):
         Confirmed : confirmed_at = now; (dari CheckedIn: checked_in_at = NULL)
         CheckedIn : checked_in_at = now; confirmed_at ??= now
         Completed : completed_at = now
         Cancelled : cancelled_at = now; cancelled_by_type = $actor->type; cancelled_by_user_id = $actor->userId; cancel_reason = $reason;
                     $s->booked_count = max(0, $s->booked_count - 1); $s->save();     // kuota dilepas; last_queue_number TIDAK diturunkan
         NoShow    : no_show_at = now                                                  // tetap menghitung kuota
       $a->status = $to; $a->save();
       AppointmentStatusHistory::create([appointment_id, from_status, to_status, actor_type, actor_user_id, reason]);
       $audit->log('appointment.status_changed', $a, ['status'=>$from->value], ['status'=>$to->value, 'reason'=>$reason], …);
       DB::afterCommit(fn() => $p->user?->notify(new AppointmentStatusChangedNotification($a, $from)));   // via(): database untuk SETIAP transisi; mail hanya bila $to ∈ {Confirmed, Cancelled} (00 F.8, K-13)
     }, attempts: 3);
  4. return $a->refresh()
```

- Kuota: hanya `Cancelled` melepas kuota (00 F.8). Sesi `needs_review`/`closed`/`cancelled` tetap boleh dibatalkan appointment-nya.
- `availableTransitions()` menjalankan pemeriksaan yang sama tanpa melempar (mengembalikan tombol yang valid; dipakai `admin.appointments.show` dan baris antrean).
- `markNoShowForSession()`: melempar `SESSION_NOT_ENDED` bila `now ≤ endsAt`; mengiterasi id appointment `pending|confirmed` dan memanggil `transition(…, NoShow, …)` satu per satu (transaksi terpisah per appointment → urutan kunci tetap benar, tanpa deadlock lintas pasien); mengembalikan jumlah.
- `cancelAllForSession()`: memanggil `transition(…, Cancelled, $actor, $reason)` untuk setiap appointment aktif, juga satu per satu.

---
### 6.4 `Scheduling\PracticeSessionGenerator`

```php
public function ensureRange(CarbonInterface $from, CarbonInterface $to, ?DoctorSchedule $only = null): int;  // jumlah sesi baru
public function ensureDailyHorizon(): int;                                                                    // hari ini … + booking.window_days, maks 1×/hari
```

```
ensureRange($from, $to, $only):
  $from = max($from, today)                               // tidak membuat sesi di masa lalu
  $schedules  = DoctorSchedule::active()->when($only, whereKey)
                  ->whereHas('doctor', active)->whereHas('clinic', is_active)
                  ->where('valid_from','<=',$to)->where(fn => valid_until IS NULL OR >= $from)->get()
  $exceptions = ScheduleException::where('end_date','>=',$from)->where('start_date','<=',$to)->get()
  $rows = []
  foreach $schedules as $sch:
    foreach CarbonPeriod(max($from,$sch->valid_from) … min($to, $sch->valid_until ?? $to)) as $date:
      if ($date->isoWeekday() !== $sch->day_of_week) continue
      $ex = $exceptions->first(covering($date) && (doctor_id NULL || doctor_id = $sch->doctor_id))
      $rows[] = [doctor_id, clinic_id, doctor_schedule_id, session_date, start_time, end_time, quota,
                 booked_count 0, last_queue_number 0, is_manually_modified false,
                 status => $ex ? 'cancelled' : 'open', status_reason => $ex?->reason, schedule_exception_id => $ex?->id,
                 created_at, updated_at]
  foreach array_chunk($rows, 500): $n += DB::table('practice_sessions')->insertOrIgnore($chunk)   // idempoten: UNIQUE (doctor_schedule_id, session_date)
  return $n
```
Hanya templat `is_active = true` dalam rentang berlaku (`valid_from`/`valid_until`) dengan dokter `status = active` dan poliklinik `is_active = true` yang dibangkitkan; templat lain dilewati (00 F.2). `insertOrIgnore` melewati Eloquent, sehingga `created_at/updated_at` diisi manual; baris yang bentrok UNIQUE (sudah ada, termasuk yang `is_manually_modified`) tidak ditimpa. Sesi manual (`doctor_schedule_id` NULL) tidak pernah dibuat/diubah oleh generator.

```
ensureDailyHorizon():
  $key = 'sessions:generated:' . today()->format('Y-m-d')
  if Cache::has($key) return 0
  try   return Cache::lock('sessions:generate', 30)->block(5, function () use ($key) {
            if (Cache::has($key)) return 0
            $n = $this->ensureRange(today(), today()->addDays($settings->bookingRules()->windowDays))
            Cache::put($key, true, now()->endOfDay());  return $n })
  catch LockTimeoutException → return 0                   // halaman tetap tampil dengan sesi yang ada
```
Dipanggil oleh `Public\HomeController@index`, `DoctorController@index/show`, `ScheduleController@index`, dan `Patient\AppointmentController@create` (00 F.2(b)); halaman beranda/direktori dokter ditambahkan **[ASUMSI]** karena menampilkan sesi terdekat. Pemicu lain: simpan templat/pengecualian (`ensureRange`), perintah `sessions:generate`.

### 6.5 `Scheduling\DoctorAvailabilityQuery`

Satu-satunya sumber jadwal untuk halaman publik, wizard pasien, dan form walk-in admin (00 F.3). Tidak menulis data.

```php
public function __construct(SettingsService $settings) {}
public function sessions(AvailabilityFilter $f, ?CarbonImmutable $now = null): Collection;                    // Collection<int, SessionAvailability>
public function forSession(PracticeSession $s, bool $forLoket = false, ?CarbonImmutable $now = null): SessionAvailability;
public function doctors(AvailabilityFilter $f, int $perPage = 12): LengthAwarePaginator;                      // direktori dokter (filter q/spesialisasi/poli/tanggal)
public function classify(PracticeSession $s, BookingRules $rules, CarbonImmutable $now, bool $forLoket): Availability; // murni, tanpa query
```

- `sessions()`: `PracticeSession::query()->with(['doctor.specialties','doctor.media','clinic','exception'])`, filter `between(from,to)` (atau `onDate`), `clinicId`, `doctorId`, `specialtyId` (`whereHas('doctor.specialties')`), `q` (nama dokter); hanya dokter `active` dan poliklinik `is_active`; urut `session_date, start_time`; sesi berakhir (`Past`) dibuang kecuali `includePast`. Setiap baris dipetakan ke `SessionAvailability`. **Tidak ada N+1** (semua relasi di-eager-load).
- `doctors()`: `Doctor::active()->search($q)->inSpecialty()->inClinic()->with(['clinic','specialties','media'])->paginate()`; bila `date` ada → hanya dokter yang punya sesi pada tanggal itu (termasuk sesi Cuti/Libur agar pengunjung tahu alasannya), dan sesi halaman itu dimuat satu kali lalu dipasang lewat `$doctor->setRelation('availabilities', …)`.
- `classify()` — urutan prioritas **persis 00 F.3** (yang pertama cocok dipakai; `remaining = max(0, quota − booked_count)`):

| # | Kondisi | `Availability` |
|:-:|---|---|
| 1 | `now ≥ endsAt` (sekarang ≥ waktu selesai sesi) | `past` |
| 2 | `status = cancelled` | `Availability::forException($session->exception?->type)` → `leave` (`doctor_leave`) / `holiday` (`hospital_holiday`, `national_holiday`) / selain itu `cancelled` |
| 3 | `status = needs_review` | `under_review` |
| 4 | `status = closed` ∨ dokter ≠ `active` ∨ poliklinik tidak aktif | `closed` |
| 5 | `remaining = 0` | `full` |
| 6 | online: `now ≥ startsAt − booking.online_cutoff_minutes` ∨ poliklinik `is_bookable = false` ∨ `session_date > today + window_days` (loket: hanya dua syarat terakhir; batas waktunya `now < end_time` sudah terjamin oleh #1) | `booking_closed` |
| 7 | selain itu | `available` |

`SessionAvailability.isBookableOnline` = `classify(..., forLoket:false) === available` (= `is_bookable_online`, 00 F.3); `isBookableCounter` = `classify(..., forLoket:true) === available` (= `is_bookable_counter`: syarat sama kecuali batas waktu menjadi `now < end_time`, 00 F.5 butir 3). `availability` yang ditampilkan mengikuti `forLoket` pada filter. Tombol "Daftar" publik dan baris wizard hanya aktif bila `isBookableOnline`; `BookingService` tetap memvalidasi ulang di dalam transaksi (hasil query hanya tampilan).

### 6.6 `Scheduling\ScheduleChangeService`

Implementasi 00 F.4. Setiap sesi diproses dalam transaksi sendiri dengan `lockForUpdate()` pada sesi (dan appointment bila perlu); `Actor` dikirim untuk audit.

```php
public function assertClinicChangeAllowed(DoctorSchedule $schedule, int $newClinicId): void;                   // ValidationException bila ada sesi mendatang ber-appointment aktif; dipakai UpdateDoctorScheduleRequest
public function applyTemplateChange(DoctorSchedule $schedule, array $before, Actor $actor): ScheduleChangeReport;  // $before = getOriginal() sebelum update
public function applyException(ScheduleException $exception, ?array $previous, Actor $actor): ScheduleChangeReport; // $previous = ['start_date','end_date','doctor_id'] atau null
public function removeException(ScheduleException $exception, Actor $actor): ScheduleChangeReport;             // dipanggil SEBELUM baris dihapus
public function resolve(PracticeSession $session, SessionResolution $resolution, Actor $actor, ?string $reason = null): PracticeSession;
public function conflictsFor(PracticeSession $session, string $newStart, string $newEnd): Collection;           // pasien yang jamnya akan berbenturan bila `apply`
public function allowedResolutions(PracticeSession $session): array;                                           // SessionResolution::allowedFor(sumber), `keep` dibuang bila sumber doctor_inactive dan dokter belum aktif lagi
public function applyDoctorInactive(Doctor $doctor, Actor $actor): ScheduleChangeReport;                        // dokter dinonaktifkan/diarsipkan (00 F.4)
public function createManualSession(array $attributes, Actor $actor): PracticeSession;                          // sesi tambahan, doctor_schedule_id NULL
public function updateSession(PracticeSession $session, array $attributes, Actor $actor): PracticeSession;     // edit manual: kuota langsung; jam → langsung atau needs_review (manual_time)
public function setSessionStatus(PracticeSession $session, SessionStatus $to, Actor $actor, ?string $reason = null): PracticeSession; // buka/tutup; batal → langsung atau needs_review (manual_cancel)
public function deleteTemplate(DoctorSchedule $schedule, Actor $actor): void;                                  // 00 C: hanya bila tidak ada sesi ber-appointment; sesi tanpa appointment milik templat dihapus dulu; selain itu ditolak ("Nonaktifkan")
public function deleteManualSession(PracticeSession $session, Actor $actor): void;                             // 00 C: hanya sesi tambahan manual (doctor_schedule_id NULL) tanpa appointment sama sekali
private function setPendingChange(PracticeSession $session, array $change, Actor $actor): void;                // SATU pending_change per sesi; prioritas F.4 (di bawah); penggantian dicatat di audit
```

**Prioritas `pending_change` (00 F.4, satu-satunya aturan; dipakai `applyTemplateChange`, `applyException`, `updateSession`, `setSessionStatus`, `applyDoctorInactive` lewat `setPendingChange`)** — bila sesi sudah `needs_review` dan sumber baru datang: (1) sumber **jenis-batal** (`exception`, `template_removed`, `manual_cancel`, `doctor_inactive`) **mengalahkan** sumber **jenis-jam** (`template_time`, `manual_time`): sumber jenis-jam yang datang kemudian **tidak** menimpa sumber jenis-batal yang sudah ada; (2) pada tingkat yang sama, **sumber terbaru menggantikan** yang lama; (3) setiap penggantian dicatat di audit (`session.pending_replaced`: `old_values` = `pending_change` lama, `new_values` = baru). Tidak ada urutan lebih halus di dalam satu tingkat.

**`applyTemplateChange`** — hanya menyentuh sesi `session_date ≥ hari ini`, `doctor_schedule_id = $schedule->id`, `is_manually_modified = false`:

```
$changed = diff($before, $schedule): clinic?, day?, time?, quota?, deactivated?, range?
foreach sesi terdampak (chunk, tx per sesi, lock sesi):
  $active = appointments()->active()->count();   $any = appointments()->count()
  $valid  = $schedule->is_active ∧ isoWeekday(session_date) = day_of_week ∧ session_date ∈ [valid_from, valid_until]
  A) $any = 0                     → DELETE sesi                                   (regenerasi di bawah)     report.regenerated++
  B) $active = 0 ∧ $any > 0       → $valid ? update start/end/quota di tempat      : status = cancelled, status_reason 'Jadwal berubah'
  C) $active > 0:
       $valid ∧ hanya kuota berubah        → quota = max(quota_baru, booked_count)                          report.quotaAdjusted++
       $valid ∧ jam berubah (hari sama)    → status = needs_review, pending_change = {source:'template_time', schedule_id, new_start_time, new_end_time}   report.needsReview++
       selain itu (hari berubah / nonaktif / di luar rentang) → status = needs_review, pending_change = {source:'template_removed', schedule_id}  report.needsReview++
     (bila sesi sudah needs_review: `setPendingChange()` menerapkan prioritas F.4 — jenis-batal mengalahkan jenis-jam; setingkat → yang terbaru menggantikan; penggantian diaudit)
akhir: PracticeSessionGenerator::ensureRange(today, today + window, $schedule) → report.regenerated += n   // hari baru / sesi yang dihapus
audit 'schedule.updated' (nilai templat lama → baru + impact: regenerated, quota_adjusted, needs_review, cancelled; tanpa data pasien, 12 §8.2)
```
Ganti poliklinik ditolak oleh `assertClinicChangeAllowed` sebelum penyimpanan (buat templat baru lalu nonaktifkan yang lama).

**`applyException`**:

```
tolak bila start_date < hari ini
bila $previous: tanggal/lingkup yang terlepas dari rentang lama → reopen (logika removeException) hanya untuk sesi ber-schedule_exception_id = $exception->id
sesi target = PracticeSession ∈ [start_date, end_date], session_date ≥ hari ini, status ∈ {open, closed}, dokter sesuai (doctor_id NULL = semua)
foreach (tx, lock):
   $active = 0 → status = cancelled; schedule_exception_id = id; status_reason = exception.reason     report.cancelled++
   $active > 0 → status = needs_review; schedule_exception_id = id; pending_change = {source:'exception', exception_id}   report.needsReview++
ensureRange(today, today + window)       // sesi yang belum ada langsung dibuat berstatus cancelled bila tercakup pengecualian
audit 'exception.created' / 'exception.updated' (+ impact: cancelled, needs_review, reopened; tanpa data pasien, 12 §8.2)
```
**`removeException`** (juga dipakai pada penyempitan): untuk sesi `schedule_exception_id = $exception->id`, `session_date ≥ hari ini`, `endsAt > now`:
`cancelled` → `open` (kosongkan `status_reason`/`schedule_exception_id`); `needs_review` dengan `pending_change.source='exception'` → `open` (`pending_change = NULL`, appointment tetap). Bila tanggal itu masih tercakup pengecualian **lain**, sesi dipindahkan ke pengecualian itu (tetap `cancelled`) alih-alih dibuka. `report.reopened++`; audit `exception.deleted` (impact: reopened).

**`resolve`** (izin `sessions.review`; 00 F.4):

```
status sesi ≠ needs_review                                   → ValidationException
$resolution ∉ allowedResolutions($session)                   → ValidationException   // = SessionResolution::allowedFor(sumber); sumber doctor_inactive: keep hanya bila dokter active
Cancel tanpa $reason                                         → ValidationException (alasan wajib; untuk sumber manual_cancel boleh memakai pending_change.reason)
match $resolution:
  Keep   : tx(lock) status = open; pending_change = NULL; is_manually_modified = true; reviewed_by/at; (sumber exception: schedule_exception_id = NULL)
  Apply  : (hanya template_time dan manual_time) tx(lock): start/end = pending_change.new_*; status = open; pending_change = NULL; reviewed_by/at;
           semua appointment aktif: schedule_changed_at = now; afterCommit notifikasi AppointmentScheduleChangedNotification;
           layar menampilkan peringatan dari conflictsFor() bila jam baru berbenturan dengan booking lain pasien yang sama
  Cancel : AppointmentStatusService::cancelAllForSession($session, $actor, $reason)   // tiap appointment aktif → cancelled (cancelled_by_type = staff), kuota dilepas, notifikasi
           lalu tx(lock): status = cancelled; status_reason = $reason; pending_change = NULL; reviewed_by/at
audit 'session.resolved' (resolusi, alasan, jumlah appointment terdampak)
```
`cancelAllForSession` dipanggil **di luar** transaksi besar: tiap appointment memakai transaksinya sendiri dengan urutan kunci baku (`patient_profiles → practice_sessions → appointments`) sehingga tidak ada risiko deadlock lintas pasien; sesi tetap `needs_review` (tertutup untuk booking baru) sampai seluruh appointment selesai dibatalkan, dan operasi aman diulang bila terputus.

**Edit manual satu sesi (00 F.4)** — selalu menyetel `is_manually_modified = true` agar tidak ditimpa regenerasi:

| Operasi | Sesi tanpa appointment aktif | Sesi dengan appointment aktif |
|---|---|---|
| `updateSession` — ubah kuota | langsung (≥ `booked_count`) | langsung (tidak boleh < `booked_count`) |
| `updateSession` — ubah jam | langsung | status `needs_review`, `pending_change = {source:'manual_time', new_start_time, new_end_time}`; jam lama tetap berlaku sampai resolusi `apply` (yang menyetel `schedule_changed_at` + notifikasi) |
| `setSessionStatus` → `cancelled` | langsung `cancelled` (+ `status_reason`) | status `needs_review`, `pending_change = {source:'manual_cancel', reason}`; resolusi `cancel` menuntaskan pembatalan appointment |
| `setSessionStatus` `open ↔ closed` | langsung | langsung (appointment lama tetap berlaku) |

`createManualSession` menolak tanggal lampau dan benturan jam dengan sesi lain dokter yang sama (`ValidationException`) serta dokter berstatus `archived`. Membuka kembali sesi `cancelled` ditolak bila masih tercakup pengecualian atau dokter belum `active`.

**`applyDoctorInactive($doctor, $actor)`** (dipanggil `DoctorController@status` saat dinonaktifkan dan `@archive`; 00 F.4): sesi mendatang (`session_date ≥ hari ini`, belum lewat) milik dokter — tanpa appointment aktif → `cancelled` (`status_reason` "Dokter tidak aktif"); dengan appointment aktif → `needs_review`, `pending_change = {source:'doctor_inactive', doctor_id}`. `PracticeSessionGenerator` tidak membuat sesi untuk dokter non-`active`. Pemicu pending ditulis lewat `setPendingChange()` (prioritas di atas). **Pengarsipan** hanya diizinkan bila tidak ada appointment aktif mendatang (diperiksa `ArchiveDoctorRequest` untuk `admin.doctors.archive`; "selesaikan sesi terdampak dahulu"); dokter arsip tidak tampil di publik dan tidak dapat diberi templat jadwal. Mengaktifkan kembali dokter **tidak** membuka otomatis sesi yang sudah `cancelled`; sesi `needs_review` sumber `doctor_inactive` dapat di-`keep`, dan sesi `cancelled` dapat dibuka manual lewat `setSessionStatus(open)` **[ASUMSI]**.

### 6.7 Generator dan formatter kecil

```php
// Booking\BookingCodeGenerator
public const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';          // Crockford Base32, tanpa I L O U
public function generate(): string;                                  // 'UPH-' + 4 + '-' + 4 karakter dari random_int; ulangi (maks 5×) bila Appointment::where('booking_code')->exists()
public static function normalize(string $input): string;             // trim, huruf kapital, spasi/tanda hubung dibuang, O→0, I/L→1, format ulang UPH-XXXX-XXXX; '' bila tidak valid (untuk kolom cari antrean/pemindai)
public static function isValid(string $code): bool;                  // /^UPH-[0-9A-HJKMNP-TV-Z]{4}-[0-9A-HJKMNP-TV-Z]{4}$/

// Booking\QueueNumberFormatter
public function format(string $clinicCode, int $queueNumber): string;  // sprintf('%s-%03d', strtoupper($code), $n) → 'ANK-007'
public function forAppointment(Appointment $a): string;               // memakai $a->clinic->code

// Patients\PatientNumberGenerator
public static function format(int $id): string;                       // 'P' + str_pad($id, 6, '0') → 'P000123'
```
Kode booking tidak memuat data pasien; QR hanya berisi string kode (00 F.7).

### 6.8 `Patients\PatientRegistrationService` dan `Patients\PatientAccountLinker`

```php
// PatientRegistrationService
public function register(array $validated): User;                                  // 00 H: users + patient_profiles dalam satu transaksi; setelah commit: PatientWelcomeNotification (K-22)
public function createWalkIn(array $validated, User $staff): PatientProfile;       // profil tanpa akun (00 F.9)
public function updateProfile(PatientProfile $p, array $validated, Actor $actor): PatientProfile;  // profil berakun: email profil = users.email (00 E.1), satu transaksi, unik di users; name/phone diselaraskan
public function changeStatus(PatientProfile $p, bool $active, Actor $actor, ?string $reason): PatientProfile; // profil berakun: users.is_active ikut (dan hapus sesi login bila nonaktif); ditolak bila ada appointment aktif

// PatientAccountLinker
public function link(PatientProfile $walkIn, User $account, Actor $actor): PatientProfile;
```

```
register($v):  DB::transaction:
    $user = new User(['name'=>$v['full_name'], 'email', 'phone', 'password']); forceFill(account_type=patient, is_active=true, must_change_password=false); save
    $profile = PatientProfile::create([user_id=$user->id, full_name, birth_date, gender, phone, email=$v['email'], address,
                                       registration_channel=online, consent_at=now(), consent_version=settings('privacy.consent_version')])
    return $user                                                    // controller: Auth::login + session()->regenerate()

createWalkIn($v, $staff):  PatientProfile::create([... user_id NULL, registration_channel=loket, created_by_user_id=$staff->id,
                                       consent_at=now(), consent_version=…])    // persetujuan privasi lisan dicentang petugas (verbal_consent wajib di request)

link($walkIn, $account, $actor):   // 00 F.9 — hanya petugas, setelah verifikasi identitas di loket
  DB::transaction:
    ambil profil walk-in dan profil milik akun → kunci KEDUANYA (lockForUpdate) berurutan menurut id naik (cegah deadlock)
    $walkIn->user_id !== NULL                                       → ValidationException ("Profil ini sudah terhubung ke akun")
    $account->account_type !== patient                              → ValidationException ("Akun pasien dengan email tersebut tidak ditemukan.")
    !$account->is_active                                            → ValidationException ("Akun pasien tidak aktif.")
    $own = $account->patientProfile
    $own?->appointments()->exists()                                 → ValidationException ("Akun sudah memiliki riwayat pendaftaran; penggabungan = pengembangan lanjutan")
    $own?->delete()                                                 // profil kosong milik akun dihapus lebih dulu (UNIQUE user_id)
    $walkIn->user_id = $account->id; $walkIn->email = $account->email; $walkIn->is_active = $account->is_active; save   // sinkron 00 E.1
    audit 'patient.account_linked' (id profil, id akun, petugas; memuat nomor pasien dan persetujuan profil yang dihapus)
    return $walkIn->refresh()
```

---
### 6.9 `Media\MediaService`

Satu pintu untuk semua file gambar (00 K, E.6). Controller **tidak pernah** memanggil `Storage` langsung untuk unggahan.

```php
public const COLLECTIONS = [   // koleksi baku 00 E.6; dimensi minimum 00 K; maksimum 6000×6000 untuk semua
    'photo'   => ['single' => true,  'disk' => 'public', 'max_kb' => 2048, 'min' => [300, 300]],   // dokter
    'cover'   => ['single' => true,  'disk' => 'public', 'max_kb' => 2048, 'min' => [800, 450]],   // poliklinik, fasilitas, kamar, artikel
    'gallery' => ['single' => false, 'disk' => 'public', 'max_kb' => 2048, 'min' => [600, 400]],   // poliklinik, fasilitas, kamar
    'image'   => ['single' => true,  'disk' => 'public', 'max_kb' => 4096, 'min' => [1200, 400]],  // banner
    'photos'  => ['single' => false, 'disk' => 'public', 'max_kb' => 2048, 'min' => [600, 400]],   // album galeri
    'logo'    => ['single' => true,  'disk' => 'public', 'max_kb' => 2048, 'min' => [120, 40]],    // mitra asuransi, logo situs (model NULL)
    'editor'  => ['single' => false, 'disk' => 'public', 'max_kb' => 2048, 'min' => [200, 100]],   // gambar sisipan rich text (model NULL sampai terikat)
];

public function store(UploadedFile $file, string $collection, ?Model $owner = null, array $meta = [], ?User $uploader = null): Media;
public function replace(Model $owner, string $collection, UploadedFile $file, array $meta = [], ?User $uploader = null): Media; // koleksi tunggal
public function delete(Media $media): void;
public function deleteFor(Model $owner): void;                                   // seluruh media pemilik (saat model dihapus)
public function updateMeta(Media $media, ?string $altText, ?string $caption): Media;
public function reorder(Model $owner, string $collection, array $orderedIds): void;   // set sort_order sesuai urutan id
public function prune(bool $dryRun = false): MediaPruneReport;
```

```
store($file, $collection, $owner, $meta, $uploader):
  1. $cfg = COLLECTIONS[$collection]  (tak dikenal → InvalidArgumentException)
  2. Pertahanan berlapis di luar Form Request: MIME nyata via finfo ∈ {image/jpeg, image/png, image/webp}; ukuran ≤ max_kb; dimensi ≥ min dan ≤ 6000×6000;
     alt_text wajib (non-kosong) untuk semua koleksi publik → ValidationException
  3. Intervention Image v3 (driver GD): read → scaleDown(width: 1600)                  // tidak memperbesar
  4. Encode ulang: JPEG q82 / PNG / WebP q82 sesuai MIME  (membuang EXIF & muatan tersembunyi)
  5. $name = Str::uuid().'.'.$ext;  $dir = "{$collection}/".now()->format('Y/m');
     Storage::disk($cfg['disk'])->put("$dir/$name", $encoded);  thumbnail lebar 400 px → "$dir/thumbs/$name"
  6. Media::create([model_type/model_id dari $owner (NULL bila tanpa pemilik), collection, disk, directory => $dir,
        file_name => $name, original_name => sanitasi(basename, buang karakter kontrol, ≤150), mime_type, extension, size_bytes,
        width, height, thumb_path, alt_text, caption, sort_order => max(sort_order)+1 dalam (pemilik, koleksi), uploaded_by])
     galat di tengah jalan → hapus file yang sudah tertulis (tanpa meninggalkan yatim)
replace($owner, $collection, $file, …):    // 00 K "Penggantian"
  $old = $owner->firstMedia($collection) (query segar);  $new = store(...)       // simpan file + baris baru dulu
  DB::afterCommit(fn () => $old && $this->delete($old))                           // hapus file + baris lama SETELAH commit
prune($dryRun):                                                                    // 00 K "Pembersihan"
  (a) Media::orphaned(24)  = baris model_id NULL berumur > 24 jam (kecuali koleksi `logo` yang dirujuk website_settings) → hapus file + baris
  (b) file di disk `public` pada folder koleksi yang tidak tercatat di `media` dan berumur > 1 jam [ASUMSI: menghindari balapan dengan unggahan berjalan] → hapus
  $dryRun → hanya menghitung & mencetak daftar; return MediaPruneReport(orphanRows, orphanFiles, bytes, dryRun)
```
`delete()` menghapus file asli + thumbnail dari disk lalu baris `media`. Disk `local` (privat) tidak punya URL; file hanya dilayani controller + Policy.

### 6.10 `Audit\AuditLogger`

```php
public function log(string $action, Model $model, ?array $old, ?array $new, string $description, ?Actor $actor = null): AuditLog;   // $model wajib: aksi tanpa satu baris mengirim instance kosong (mis. new User) ⇒ auditable_id NULL (F1-R7)
public function diff(Model $model): array;       // ['old' => …, 'new' => …] atribut yang berubah (tanpa updated_at)
public function redact(array $values, string $action, ?Model $model = null): array;   // redaksi tingkat 1 dan 2 (12 §8.1) menurut awalan $action; atribut $model->getHidden() ikut disamarkan; dipanggil log() (F1-R7)
```

- **Pelaku:** `$actor` bila diberikan; selain itu dari `Auth::user()` (staf/pasien) atau `Actor::system()` bila tidak ada pengguna (CLI). `actor_type` dan `user_id` diisi.
- **Konteks (F1-R8):** `ip_address` dan `user_agent` diisi hanya bila ada permintaan HTTP yang ter-*route* (`request()->route() !== null`; `user_agent` tanpa karakter kontrol, ≤ 255 karakter); CLI dan tes unit ⇒ NULL (09 §7.3 **[ASUMSI]** berlaku atas nilai `'cli'`); `auditable_type = $model->getMorphClass()`, `auditable_id = $model->getKey()` (NULL untuk instance kosong).
- **Redaksi (00 E.6):** secara rekursif, nilai pada kunci `password`, `remember_token`, `token`, `bpjs_number`, `payment_reference` diganti `[disamarkan]` (juga `password_confirmation`, `current_password`); **redaksi tingkat 2** (12 §8.1): kolom PII `address`, `phone`, `email`, `birth_date`, `complaint`, `message` pada aksi `patient.*`, `appointment.*`, `contact_message.*` diganti `[disamarkan]` dan teks panjang `body`, `bio`, `short_bio`, `description` dicatat `[konten diubah]` (metode `redact()`); string > 500 karakter dipotong dengan `…[dipotong]`; `old_values`/`new_values` kosong disimpan `NULL`.
- **Integritas:** dipanggil di dalam transaksi operasi yang diaudit; bila gagal menulis, **galat tidak ditelan** (operasi ikut dibatalkan) agar jejak audit tidak pernah hilang diam-diam.

Nama aksi baku (`{entitas}.{peristiwa}`) — **katalog tunggal ada di [12 §8](12-aturan-bisnis-validasi-keamanan.md)**; bagian ini hanya memetakan sumber penulisnya:

| Sumber | Aksi (12 §8) |
|---|---|
| `AuditObserver` (otomatis, model konten/master) | `specialty.*`, `clinic.*`, `facility.*`, `room_category.*`, `page.updated`, `banner.*`, `article.*`, `article_category.*`, `gallery.*`, `faq.*`, `insurance_partner.*` |
| Controller login/ganti password | `auth.staff_login`, `auth.staff_login_failed`, `auth.staff_logout`, `auth.password_changed`, `auth.password_reset` |
| `StaffAccountService` | `staff.created`, `staff.updated`, `staff.deactivated`, `staff.activated`, `staff.password_reset`, `role.permissions_updated` |
| `DoctorController` | `doctor.created`, `doctor.updated`, `doctor.status_changed`, `doctor.archived`; ganti foto lewat `MediaService` → `media.replaced` |
| `ScheduleChangeService` | `schedule.created`, `schedule.updated` (+ impact), `schedule.deleted`, `exception.created`, `exception.updated`, `exception.deleted`, `session.created`, `session.updated`, `session.status_changed`, `session.deleted`, `session.resolved`, `session.pending_replaced` |
| `BookingService`, `AppointmentStatusService` | `appointment.created`, `appointment.status_changed` |
| `PatientRegistrationService`, `PatientAccountLinker`, controller pasien | `patient.registered`, `patient.created`, `patient.updated`, `patient.status_changed`, `patient.account_linked` |
| `RoomCategoryController@availability` | `room.availability_updated` |
| `SettingsService::setMany`, `AppointmentCsvExporter`, `MediaService`, `PruneMedia` | `settings.updated`, `report.exported`, `media.uploaded`, `media.replaced`, `media.deleted`, `media.pruned` |
| `ContactMessageController@status` | `contact_message.status_changed` |

### 6.11 `Settings\SettingsService`

```php
public function get(string $key, mixed $default = null): mixed;          // nilai bertipe menurut kolom `type`
public function string(string $key, string $default = ''): string;
public function int(string $key, int $default = 0): int;
public function bool(string $key, bool $default = false): bool;
public function set(string $key, mixed $value, ?User $by = null): void;
public function setMany(array $values, ?User $by = null): void;           // satu transaksi + audit 'settings.updated' (nilai lama/baru) + forget()
public function group(string $group): Collection;                         // grup identitas|kontak|booking|seo|privasi (00 E.6) untuk layar pengaturan (key, label, type, value)
public function bookingRules(): BookingRules;                             // snapshot booking.*
public function logoUrl(): string;                                        // media `site.logo_media_id` atau /images/placeholders/logo.svg
public function whatsappUrl(?string $text = null): ?string;               // https://wa.me/{contact.whatsapp}[?text=…] — tautan manual, bukan API
public function forget(): void;
```
- **Cache:** `Cache::rememberForever('settings:all', …)` (kunci cache baku 00 J.1) memuat seluruh baris `website_settings` (kecil) menjadi peta `key → [type, value]`; ditambah memo di dalam instance (didaftarkan `scoped`: satu instance per request/job, sehingga memo tidak basi di queue worker produksi; Task 7). `set/setMany` memanggil `forget()` (00 E.6: "cache dibersihkan saat disimpan").
- **Cast menurut `type`:** `string|text|url|email` → string; `int` → integer; `bool` → boolean (`'1'/'true'`); `json` → `json_decode(..., true)`. Kunci tak dikenal → `$default`.
- **Penjagaan:** `set()` menolak kunci yang tidak ada di seed (tidak membuat kunci baru dari UI); validasi tipe/rentang dilakukan `UpdateSettingsRequest` (12).

### 6.12 `Content\HtmlSanitizer`

```php
public function clean(?string $html): string;                          // '' bila kosong; hasil aman untuk {!! !!}
public function toPlainText(?string $html, int $limit = 160): string;   // untuk meta description otomatis/excerpt
```
`clean()` = `Purifier::clean($html, 'rich_text')` (profil di [10 §7.2](10-struktur-folder.md)) + pasca-proses DOM: (1) `<img>` yang `src`-nya bukan `/storage/…` dibuang (URL absolut ke host aplikasi dinormalkan menjadi path relatif); (2) `<a href="http(s)://host-lain">` dipaksa `target="_blank" rel="noopener noreferrer"`; (3) skema selain `http|https|mailto|tel` dibuang. Dipanggil hanya oleh trait `SanitizesRichText` (saat `saving`), sehingga tidak ada jalur simpan yang melewatinya.

### 6.13 `Reports\AppointmentCsvExporter`

```php
public function stream(array $filters, Actor $actor): StreamedResponse;   // filters tervalidasi ExportAppointmentsRequest
public function query(array $filters): Builder;                           // from/to (session_date), clinic_id, doctor_id, status, channel
public function filename(array $filters): string;                         // pendaftaran_YYYYMMDD-YYYYMMDD.csv
```
Perilaku (00 L): catat `report.exported` (filter + jumlah baris) **sebelum** streaming; respons `text/csv; charset=UTF-8`, `Content-Disposition: attachment`, `Cache-Control: no-store`; keluarkan BOM `\xEF\xBB\xBF`; pemisah `;`; baris diambil dengan `lazyById(500)` (memori konstan) dengan eager load `patientProfile:id,patient_number,full_name`, `doctor`, `clinic`, `practiceSession:id,start_time,end_time`. Kolom: kode booking · tanggal kunjungan (`d/m/Y`) · jam sesi (`08.00–12.00`) · poliklinik · dokter (`displayName`) · nomor antrean (`queueLabel`) · no. pasien · nama pasien · jenis pembayaran · status · kanal · dibuat pada. **Tidak** menyertakan nomor BPJS/polis, alamat, telepon, tanggal lahir, keluhan. Pelindung *CSV injection*: sel yang diawali `=`, `+`, `-`, `@`, tab, atau CR diberi awalan `'`.

### 6.14 `Dashboard\DashboardMetrics`

```php
public function activeDoctors(): int;                                         // Doctor::active()->count()
public function registeredPatients(): int;                                    // PatientProfile::active()->count()
public function todayAppointments(): int;                                     // session_date = hari ini, status ≠ cancelled [ASUMSI]
public function statusCounts(CarbonInterface $from, CarbonInterface $to): array;   // ['pending' => n, …] seluruh enum terisi (nol bila kosong)
public function chart(CarbonInterface $from, CarbonInterface $to): array;          // ['labels' => ['2026-10-01',…], 'series' => [n,…]] per session_date, hari kosong = 0
public function roomSummary(): Collection;                                         // kategori aktif: beds_available, bed_capacity, availability_updated_at
public function pendingReviewSessions(): int;                                      // sesi needs_review (hanya untuk pemilik sessions.review)
public function todaysQueue(int $limit = 10): Collection;                          // hanya untuk appointments.view (memuat nama pasien)
```
Grafik memakai **tanggal kunjungan** (`session_date`) dengan filter 7/30/90 hari atau rentang kustom (maks 366 hari) **[ASUMSI sesuai 00 L]**. Angka agregat dicache 60 detik (`Cache::remember('dashboard:counters', 60, …)`) **[ASUMSI]**; grafik tidak dicache. Ringkasan kamar selalu menampilkan `availability_updated_at` ("Diperbarui manual pada …"), tidak disebut real-time.

### 6.15 `Staff\StaffAccountService`

Menegakkan aturan mutlak 00 G yang tidak boleh bergantung pada Policy karena `Gate::before` (§3.3): peran `super-admin` terkunci, minimal satu Super Admin aktif, dan staf tidak dapat menonaktifkan atau mengubah peran akunnya sendiri.

```php
public function create(array $validated, Actor $actor): array;                    // [User $staff, string $temporaryPassword]
public function update(User $staff, array $validated, Actor $actor): User;        // name, email, phone, peran
public function changeStatus(User $staff, bool $active, Actor $actor): User;
public function resetPassword(User $staff, Actor $actor): string;                 // password sementara baru
public function syncRolePermissions(Role $role, array $permissionNames, Actor $actor): Role;
public function generateTemporaryPassword(): string;                              // 12 karakter acak, memenuhi Password::min(8)->letters()->numbers()
```
- `create`: `forceFill(account_type=staff, is_active=true, must_change_password=true)`, `assignRole`; password sementara dikembalikan **sekali** untuk flash (tidak disimpan sebagai teks biasa, tidak dicatat di audit).
- `changeStatus`: menolak menonaktifkan diri sendiri; menolak bila target Super Admin aktif terakhir (`User::role('super-admin')->where('is_active', true)->lockForUpdate()->count() ≤ 1`); saat menonaktifkan menghapus sesi login pengguna (`DB::table('sessions')->where('user_id', …)->delete()`).
- `update`: **menolak mengubah peran akun sendiri** (`$staff->id === $actor->userId` dan peran berubah → `ValidationException`); pergantian peran akun lain tidak boleh menyisakan nol Super Admin aktif.
- `resetPassword`: set password sementara + `must_change_password=true`, hapus sesi aktif pengguna, audit `staff.password_reset`.
- `syncRolePermissions`: menolak peran `super-admin` (terkunci); nama izin harus bagian dari daftar baku 00 G; `syncPermissions()`, `PermissionRegistrar::forgetCachedPermissions()`, audit `role.permissions_updated` (daftar lama/baru).

### 6.16 `Booking\AppointmentProofService`

```php
public function qrSvg(Appointment $a): string;            // SVG QR (chillerlan/php-qrcode); isi = booking_code SAJA (00 F.7)
public function viewData(Appointment $a): array;          // ['appointment' => dimuat lengkap, 'qrSvg' => …, 'printedAt' => now()]
public function pdf(Appointment $a): \Barryvdh\DomPDF\PDF; // view 'pdf.appointment-proof', A4 portrait, QR sebagai data:image/svg+xml;base64
```
Dipakai `Patient\AppointmentController@proof/proofPdf` dan `Admin\AppointmentController@proof`. Otorisasi (`AppointmentPolicy::view`) dilakukan **sebelum** service dipanggil; service tidak memeriksa hak akses.

---
## 7. Middleware

Kelas di `app/Http/Middleware`; alias didaftarkan di `bootstrap/app.php` (§3.2). Pemeriksaan kepemilikan/izin yang bergantung data dilakukan Policy, bukan middleware.

### 7.1 `EnsureAccountType` (alias `account`)

```php
public function handle(Request $request, Closure $next, string $type): Response
{
    $required = AccountType::from($type);                       // 'patient' | 'staff'
    $user = $request->user();                                    // `auth` sudah memastikan login
    if ($user->account_type === $required) return $next($request);

    if ($required === AccountType::Staff) {                      // PASIEN membuka URL admin
        Log::notice('Akses area admin oleh akun pasien ditolak', ['user_id' => $user->id, 'url' => $request->fullUrl(), 'ip' => $request->ip()]);
        abort(403, 'Anda tidak memiliki izin untuk mengakses halaman ini.');
    }
    // STAF membuka portal pasien (/pasien/*)
    return $request->expectsJson() ? abort(403) : redirect()->route('admin.dashboard');
}
```
Hasil: pasien di `/admin/*` mendapat **403** (halaman `errors.403` Indonesia); staf di `/pasien/*` **dialihkan** ke `admin.dashboard`.

### 7.2 `EnsureAccountIsActive` (alias `active`)

```php
$user = $request->user();
if (! $user->is_active) {          // 00 E.1: profil pasien nonaktif ⇔ users.is_active = false, jadi cukup satu kolom
    $isStaff = $user->isStaff();
    Auth::guard('web')->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return redirect()->route($isStaff ? 'admin.login' : 'login')
        ->with('error', 'Akun Anda dinonaktifkan. Silakan hubungi administrator rumah sakit.');
}
return $next($request);
```
Menangani akun yang dinonaktifkan **saat sesi berjalan** (login baru oleh akun nonaktif sudah gagal generik di `Auth::attempt` karena kondisi `is_active=true`). Menonaktifkan profil pasien berakun menyetel `users.is_active=false` (00 E.1; dikerjakan `PatientController@status`), sehingga middleware cukup memeriksa `users.is_active`. Profil walk-in tanpa akun tidak punya sesi login; penonaktifannya ditolak saat booking (`BookingService`).

### 7.3 `EnsurePasswordChanged` (alias `password.changed`)

```php
if ($request->user()?->must_change_password
    && ! $request->routeIs('admin.password.edit', 'admin.password.update', 'admin.logout')) {
    return $request->expectsJson()
        ? abort(403, 'Ganti password sementara terlebih dahulu.')
        : redirect()->route('admin.password.edit')->with('warning', 'Anda harus mengganti password sementara sebelum melanjutkan.');
}
return $next($request);
```
Hanya relevan untuk staf (kolom `must_change_password`). Daftar pengecualian bersifat pertahanan berlapis; route `admin.password.*`/`admin.logout` sudah berada di luar `G-ADMIN`.

### 7.4 `SecurityHeaders` (global)

| Header | Nilai | Kondisi |
|---|---|---|
| `X-Content-Type-Options` | `nosniff` | selalu |
| `X-Frame-Options` | `SAMEORIGIN` | selalu |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | selalu |
| `Permissions-Policy` | `camera=(), microphone=(), geolocation=(), payment=(), usb=()` | selalu |
| `Cache-Control` | `no-store, private` | path `pasien*`, `admin*`, `masuk`, `daftar`, `lupa-password`, `reset-password/*` (data pribadi/token; tidak menimpa `Cache-Control` yang sudah diset eksplisit, mis. PDF) **[ASUMSI]** |
| `Content-Security-Policy` | `default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-src https://www.google.com https://maps.google.com; frame-ancestors 'self'; base-uri 'self'; form-action 'self'; object-src 'none'` | **hanya produksi** (`APP_ENV=production`); lokal dinonaktifkan agar HMR Vite jalan (00 B.2) |
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains` | produksi dan `$request->isSecure()` |

`style-src 'unsafe-inline'` dipertahankan karena Bootstrap/Chart.js/Quill menulis atribut `style` **[ASUMSI]**. Karena `script-src 'self'` melarang atribut `onerror=` inline, fallback gambar `x-image` diimplementasikan lewat atribut `data-fallback` + listener kapture di `app.js`/`admin.js` (lihat *Kontradiksi/ambiguitas* di akhir bagian ini).

---

## 8. Policy

Dipanggil `$this->authorize('ability', $model|Model::class)` di controller dan pada `Form Request::authorize()`. Policy ditemukan otomatis (`App\Policies\{Model}Policy`); `RolePolicy` didaftarkan manual. Notasi `izin` = `$user->can('izin')` (Spatie).

**Penting — `Gate::before` dan aturan "false/terkunci".** Super Admin melewati **semua** Policy. Karena itu aturan yang harus berlaku **juga untuk Super Admin** (peran `super-admin` terkunci, tak boleh menonaktifkan diri sendiri, minimal satu Super Admin aktif, audit log tak dapat diubah) ditegakkan di `StaffAccountService`, Form Request `withValidator`, model (`AuditLog`), dan dengan **tidak adanya route** — Policy hanya menjadi lapis untuk non-Super-Admin.

| Policy | Method → aturan |
|---|---|
| `DoctorPolicy` | `viewAny`, `view` → `doctors.view` · `create`, `update`, `changeStatus`, `archive` → `doctors.manage` · `delete` → tidak didefinisikan (dokter diarsipkan, tidak dihapus) |
| `SpecialtyPolicy` | `viewAny`, `view`, `create`, `update`, `delete` → `clinics.manage` |
| `ClinicPolicy` | `viewAny`, `view`, `create`, `update`, `delete` → `clinics.manage` |
| `DoctorSchedulePolicy` | `viewAny`, `view` → `schedules.view` · `create`, `update` → `schedules.manage` · `delete` → `schedules.manage` ∧ tidak ada sesi ber-appointment (00 C) |
| `PracticeSessionPolicy` | `viewAny`, `view` → `schedules.view` · `create`, `update`, `changeStatus`, `delete` → `schedules.manage` (`delete` = nama baru yang diusulkan; hanya sesi tambahan manual tanpa appointment, diperiksa `deletionBlockedReason()`) · `review`, `resolve` → `sessions.review` · `markNoShow` → `appointments.manage` |
| `ScheduleExceptionPolicy` | `viewAny`, `view` → `schedules.view` · `create`, `update`, `delete` → `schedules.manage` |
| `AppointmentPolicy` | lihat contoh di bawah |
| `PatientProfilePolicy` | lihat contoh di bawah |
| `FacilityPolicy` | seluruh method → `facilities.manage` |
| `RoomCategoryPolicy` | `viewAny`, `view` → `facilities.manage` ∨ `rooms.update-availability` · `create`, `update`, `delete` → `facilities.manage` · `updateAvailability` → `rooms.update-availability` |
| `PagePolicy` | `viewAny`, `view`, `update` → `content.manage` · `create`, `delete` → `false` (halaman sistem `is_system`) |
| `BannerPolicy`, `ArticlePolicy`, `ArticleCategoryPolicy`, `GalleryPolicy`, `FaqPolicy`, `InsurancePartnerPolicy` | seluruh method → `content.manage` |
| `ContactMessagePolicy` | `viewAny`, `view`, `updateStatus` → `messages.manage` · `delete` → `false` |
| `MediaPolicy` | `upload` → salah satu `content.manage`, `clinics.manage`, `facilities.manage`; `update`, `delete`, `reorder` → izin pemilik media menurut `model_type`: `Doctor`→`doctors.manage`; `Clinic`/`Specialty`→`clinics.manage`; `Facility`/`RoomCategory`→`facilities.manage`; `Article`/`Banner`/`Gallery`/`InsurancePartner`/`Page`→`content.manage`; `model` NULL: koleksi `logo`→`settings.manage`, koleksi `editor`→ pengunggah sendiri atau `content.manage` |
| `UserPolicy` (staf) | `viewAny`, `view`, `create` → `staff.manage` · `update`, `changeStatus`, `resetPassword` → `staff.manage` ∧ `$target->account_type = staff` (+ `changeStatus`: `$target->id ≠ $user->id`) · `delete` → `false` |
| `RolePolicy` | `viewAny`, `view` → `staff.manage` · `update` → `staff.manage` ∧ `$role->name ≠ 'super-admin'` |
| `AuditLogPolicy` | `viewAny`, `view` → `audit.view` · `create`, `update`, `delete` → `false` |

Pengaturan website tidak punya model Policy: `Gate::authorize('settings.manage')` (izin Spatie sebagai ability Gate).

**Contoh `AppointmentPolicy`:**

```php
public function viewAny(User $user): bool { return $user->can('appointments.view'); }

public function view(User $user, Appointment $a): bool                       // dipakai juga untuk bukti (proof / proof-pdf)
{
    if ($user->can('appointments.view')) return true;                         // petugas berwenang
    $a->loadMissing('patientProfile');
    return $user->isPatient() && $a->patientProfile->user_id === $user->id;   // pemilik via profil
}

public function create(User $user): bool        { return $user->can('appointments.manage'); }          // walk-in
public function createOnline(User $user): bool  { return $user->isPatient() && $user->is_active && $user->patientProfile?->is_active === true; }

public function cancel(User $user, Appointment $a): bool                     // kepemilikan saja; batas waktu diperiksa AppointmentStatusService
{
    if ($user->can('appointments.manage')) return true;
    $a->loadMissing('patientProfile');
    return $user->isPatient() && $a->patientProfile->user_id === $user->id;
}

public function transition(User $user, Appointment $a, AppointmentStatus $to): bool
{
    return $user->can('appointments.manage') || ($to === AppointmentStatus::Cancelled && $this->cancel($user, $a));
}

public function export(User $user): bool        { return $user->can('reports.export'); }
```

**Contoh `PatientProfilePolicy`** (pemilik via `user_id`, atau izin `patients.*`):

```php
public function viewAny(User $user): bool                     { return $user->can('patients.view'); }
public function view(User $user, PatientProfile $p): bool     { return $user->can('patients.view') || $p->user_id === $user->id; }
public function create(User $user): bool                      { return $user->can('patients.manage'); }
public function update(User $user, PatientProfile $p): bool   { return $user->can('patients.manage') || $p->user_id === $user->id; }
public function changeStatus(User $user, PatientProfile $p): bool { return $user->can('patients.manage'); }
public function linkAccount(User $user, PatientProfile $p): bool  { return $user->can('patients.manage') && $p->user_id === null; }
```
Admin Konten **tidak** memiliki `patients.*`/`appointments.*` sehingga tidak dapat membaca data pasien (00 G); pada layar jadwal ia hanya melihat `booked_count`.

---

## 9. Form Request

Semua di `app/Http/Requests/{Area}`. `authorize()` memanggil Policy/izin (bukan `return true` kecuali area tamu). Pesan galat Indonesia lewat `lang/id/validation.php` (+ blok `attributes`). **Nama kelas mengikuti katalog [12-aturan-bisnis-validasi-keamanan.md](12-aturan-bisnis-validasi-keamanan.md) §4 (V-01 … V-45)**; aturan rinci per field, batas panjang, regex, dan pesan galat ada di sana — tabel di bawah hanya ringkasan aturan utama dan route pemakai. Satu kelas melayani `store` dan `update` untuk entitas konten sederhana (mis. `ClinicRequest`; perbedaan Store/Update diputuskan dari `$this->route('clinic')`); dokter dan pasien memakai pasangan `Store…`/`Update…` karena aturannya berbeda.

**Basis `App\Http\Requests\ListFilterRequest` (permintaan GET-filter longgar).** 00 I.5 menetapkan "parameter yang tidak valid diabaikan (filter tidak diterapkan) dan tidak menimbulkan galat 500". Form Request biasa mengalihkan dengan galat validasi, maka semua permintaan yang hanya memuat filter/langkah wizard mewarisi `ListFilterRequest`: `failedValidation()` ditimpa agar tidak melempar, dan method `filters(): array` mengembalikan hanya parameter yang lolos aturan (`$this->getValidatorInstance()->valid()`); `ListFilterRequest` sendiri dipakai langsung oleh semua `admin.*.index` sederhana (dokter, spesialisasi, poliklinik, jadwal, pengecualian, fasilitas, kamar, pasien, halaman, banner, artikel, kategori artikel, galeri, FAQ, asuransi, staf, pesan) dengan parameter baku `q` ≤ 100, `status` (daftar enum entitas), `kategori`, `urut` (daftar putih kolom), `per_page` ∈ {10, 25, 50}, `page` ≥ 1, `kembali` (path internal: diawali `/`, bukan `//`, tanpa skema). Turunannya: `DoctorIndexRequest`, `ScheduleIndexRequest`, `ArticleIndexRequest`, `BookingWizardQueryRequest`, `DashboardChartRequest`, `PracticeSessionIndexRequest`, `AppointmentIndexRequest`, `QueueIndexRequest`, `AuditLogIndexRequest`, `CreateWalkInAppointmentRequest`. Permintaan yang menulis data atau mengekspor (`ExportAppointmentsRequest`) tetap Form Request ketat.

### 9.1 `Auth\` (5) dan `Public\` (4)

| Kelas | Route | Ringkasan aturan utama |
|---|---|---|
| `Auth\RegisterRequest` | `register.store` | `full_name`, `email` (unik `users`), `phone`, `birth_date` (≤ hari ini), `gender` (`Gender`), `address`, `password` (konfirmasi; `Password::min(8)->letters()->numbers()`), `consent` diterima. `authorize()` = tamu. |
| `Auth\LoginRequest` | `login.store` | `email`, `password`, `remember`; method `authenticate()` (`Auth::attempt` + `account_type=patient`, `is_active=true`; pesan generik). |
| `Auth\AdminLoginRequest` | `admin.login.store` | Sama, `account_type=staff`. |
| `Auth\ForgotPasswordRequest` | `password.email` | `email`. |
| `Auth\ResetPasswordRequest` | `password.update` | `token`, `email`, `password` (konfirmasi; aturan password). |
| `Public\DoctorIndexRequest` | `doctors.index` | Turunan `ListFilterRequest`: `q` ≤ 100, `spesialisasi` (`specialties.slug`), `poli` (`clinics.slug`), `tanggal` (`YYYY-MM-DD`, hari ini … + `booking.window_days`). |
| `Public\ScheduleIndexRequest` | `schedules.index` | Turunan `ListFilterRequest`: `minggu` (`YYYY-MM-DD`; tanggal mana pun dalam minggu, dinormalkan ke Senin), `poli` (slug), `dokter` (slug). |
| `Public\ArticleIndexRequest` | `articles.index` | Turunan `ListFilterRequest`: `kategori` (`article_categories.slug`), `q` ≤ 100. |
| `Public\StoreContactMessageRequest` | `contact.store` | `name`, `email`, `phone?`, `subject`, `message` (10–2000), `consent` diterima, bidang jebakan (*honeypot*) `website` harus kosong **[ASUMSI]**. Namespace `Public` (00 J.6; sama di 12 V-37). |

### 9.2 `Patient\` (6)

| Kelas | Route | Ringkasan aturan utama |
|---|---|---|
| `Patient\UpdatePatientProfileRequest` | `patient.profile.update` | `full_name`, `birth_date`, `gender`, `phone`, `address`, `email` (email login = email profil, 00 E.1: `Rule::unique('users','email')->ignore($user->id)`; disinkronkan ke `users.email` dalam satu transaksi), `remove_bpjs?` (menghapus nomor BPJS tersimpan; nomor baru disimpan lewat wizard `save_bpjs`, bukan formulir profil). `authorize()` = `PatientProfilePolicy::update` pada profil milik pengguna (tanpa id dari input). |
| `Patient\UpdatePasswordRequest` | `patient.password.update` | `current_password`, `password` baru (konfirmasi). |
| `Patient\BookingWizardQueryRequest` | `patient.appointments.create` | Turunan `ListFilterRequest`; query wizard (00 I.5): `poli` (`clinics.slug`, aktif & bookable), `dokter` (`doctors.slug`, aktif), `sesi` (`practice_sessions.id` numerik), `tanggal` (`YYYY-MM-DD`, menyaring langkah 3). Nilai tidak valid diabaikan (kembali ke langkah sebelumnya). |
| `Patient\ReviewAppointmentRequest` | `patient.appointments.review` | (trait `HasBookingRules`, 12 V-10) `practice_session_id` ada; `payment_type` (`PaymentType`); `insurance_partner_id` wajib bila `asuransi` (aktif); `bpjs_number` wajib 13 digit bila `bpjs` (bila kosong dan profil menyimpan `bpjs_number`, diisi dari profil di `prepareForValidation` **[ASUMSI]**); `policy_number?` (hanya `asuransi`, ≤ 30); `complaint?` ≤ 500; `save_bpjs?`. `BookingData::fromValidated()` memetakan `bpjs_number`/`policy_number` ke `paymentReference`. `withValidator`: pra-cek `BookingService::precheck()` (tanpa kunci dan tanpa menulis; tidak mengikat; `book()` memvalidasi ulang di dalam transaksi). |
| `Patient\StoreAppointmentRequest` | `patient.appointments.store` | Aturan `ReviewAppointmentRequest` + `confirmed` diterima. `getRedirectUrl()` ditimpa → `patient.appointments.create?sesi={practice_session_id}` (halaman *review* adalah hasil POST yang tidak dapat di-GET). `toBookingData()` mengembalikan `BookingData`. |
| `Patient\CancelAppointmentRequest` | `patient.appointments.cancel` | `cancel_reason?` ≤ 255; `authorize()` = `AppointmentPolicy::cancel`. |

### 9.3 `Admin\` (46)

| Kelas | Route | Ringkasan aturan utama |
|---|---|---|
| `Admin\UpdatePasswordRequest` | `admin.password.update` | `current_password` benar, `password` baru (konfirmasi; ≠ lama); menyetel `must_change_password=false`. |
| `DashboardChartRequest` | `admin.dashboard`, `admin.dashboard.chart` | Turunan `ListFilterRequest`: `rentang` ∈ {7, 30, 90, kustom}; `dari`/`sampai` wajib bila `kustom` (`sampai ≥ dari`; maks 366 hari). Nama parameter Indonesia seperti 00 I.5 (12 V-44). |
| `PracticeSessionIndexRequest` | `admin.sessions.index` | Turunan `ListFilterRequest`: `dari`, `sampai`, `dokter` (slug), `poli` (slug), `status` (`SessionStatus`). |
| `AppointmentIndexRequest` | `admin.appointments.index` | Turunan `ListFilterRequest`: `dari`, `sampai`, `poli`, `dokter`, `status` (`AppointmentStatus`), `kanal` (`BookingChannel`), `q`, `urut`. |
| `QueueIndexRequest` | `admin.queue.index` | Turunan `ListFilterRequest`: `tanggal` (default hari ini), `poli`, `dokter`, `kode` (kode booking; dinormalkan `BookingCodeGenerator::normalize()`). |
| `CreateWalkInAppointmentRequest` | `admin.appointments.create` | Turunan `ListFilterRequest` (wizard walk-in): `pasien` (id profil aktif), `sesi` (id sesi), `tanggal`, `poli`, `dokter`, `kembali`; nilai tidak valid diabaikan. |
| `AuditLogIndexRequest` | `admin.audit-logs.index` | Turunan `ListFilterRequest`: `dari`, `sampai`, `pengguna`, `aksi` (awalan), `objek`. |
| `StoreDoctorRequest` | `admin.doctors.store` | `clinic_id` ada; `name`, `slug` unik; gelar; `gender`; `short_bio` ≤ 500; `bio`; `status`; `specialty_ids[]` (1–5) + `primary_specialty_id` (tepat satu, salah satu yang dipilih); foto opsional (aturan unggah 00 K) + `photo_alt` wajib bila ada foto. |
| `UpdateDoctorRequest` | `admin.doctors.update` | Seperti Store (slug unik abaikan diri); `clinic_id` boleh berubah. |
| `UpdateDoctorStatusRequest` | `admin.doctors.status` | `status` ∈ {active, inactive} (menonaktifkan diizinkan walau ada appointment aktif; sesi terdampak ditangani `applyDoctorInactive()`, 00 F.4); `reason?`. Dokter `archived` tidak dapat diubah lewat form ini (dipulihkan menjadi `inactive` lewat aksi tersendiri). |
| `ArchiveDoctorRequest` | `admin.doctors.archive` | `reason?`; **ditolak bila ada appointment aktif pada sesi mendatang** ("selesaikan sesi terdampak dahulu", 00 F.4; kelas terpisah dari `UpdateDoctorStatusRequest`, 00 J.6). |
| `UploadDoctorPhotoRequest` | `admin.doctors.photo` | `photo` (jpg/jpeg/png/webp, ≤ 2048 KB, ≥ 300×300, ≤ 6000×6000), `alt_text` wajib. |
| `SpecialtyRequest` | `admin.specialties.store` / `.update` | `name`, `slug` unik, `description?`, `is_active`, `sort_order`. |
| `ClinicRequest` | `admin.clinics.store` / `.update` | `name`, `slug` unik, `code` (2–5 huruf kapital A–Z, unik; tidak dapat diubah bila sudah ada appointment pada poliklinik itu), `type` (`ClinicType`), `is_bookable` (hanya `poliklinik`), `specialty_id?`, `short_description`, `description` (rich text), `location`, meta; `cover` (≥ 800×450) / `gallery[]` (≥ 600×400) + alt. |
| `DoctorScheduleRequest` | `admin.schedules.store` / `.update` | `doctor_id` (dokter tidak `archived`), `clinic_id`, `day_of_week` 1–7, `start_time < end_time`, `quota` 1–200, `valid_from`, `valid_until ≥ valid_from`, `is_active`; **`NoOverlappingSchedule`** (tak beririsan dengan templat aktif dokter yang sama di hari sama pada rentang berlaku beririsan); Update: `assertClinicChangeAllowed()`. |
| `PracticeSessionRequest` | `admin.sessions.store` / `.update` | `doctor_id`, `clinic_id`, `session_date` (≥ hari ini), jam (`end > start`; **`NoOverlappingSession`**: tidak berbenturan dengan sesi non-batal lain dokter itu), dokter `active`, `quota` (Update: ≥ `booked_count`), `status_reason?`. Update: perubahan jam pada sesi ber-appointment aktif **bukan galat** — sesi menjadi `needs_review` sumber `manual_time` (§6.6). |
| `UpdateSessionStatusRequest` | `admin.sessions.status` | `status` ∈ {open, closed, cancelled}; `status_reason` wajib bila `cancelled` (12 V-24); membuka sesi dokter non-`active` ditolak. |
| `ResolveSessionRequest` | `admin.sessions.resolve` | `resolution` (`SessionResolution`; harus ∈ `ScheduleChangeService::allowedResolutions($session)`); `reason` wajib bila `cancel` (sumber `manual_cancel` boleh memakai alasan bawaan). |
| `ScheduleExceptionRequest` | `admin.exceptions.store` / `.update` | `type`; `doctor_id?` menurut jenis (00 E.2): `doctor_leave` → **wajib**, `hospital_holiday`/`national_holiday` → **harus kosong**, `other` → boleh keduanya (dokter bukan `archived`); `start_date` (≥ hari ini), `end_date ≥ start_date` (maks 365 hari), `reason` ≤ 255. Selain Form Request, CHECK `chk_schedule_exceptions_scope` menegakkan kombinasi ini di database. |
| `FacilityRequest` | `admin.facilities.store` / `.update` | `name`, `slug`, `category` (`FacilityCategory`), deskripsi, `is_active`, meta; `cover`/`gallery[]` + alt. |
| `RoomCategoryRequest` | `admin.room-categories.store` / `.update` | `name`, `slug`, deskripsi, `amenities[]`, `bed_capacity` ≥ `beds_available` saat ini, `beds_available` 0…`bed_capacity`, `sample_rate?`, `rate_unit`, `rate_note?`, `is_active`; media + alt. |
| `UpdateRoomAvailabilityRequest` | `admin.room-categories.availability` | `beds_available` integer 0…`bed_capacity` kategori. |
| `StorePatientRequest` | `admin.patients.store` | Identitas walk-in; `phone` wajib; `verbal_consent` wajib (persetujuan lisan dicentang petugas); `confirm_not_duplicate?` (bila ada profil mirip); `email?` (kontak). Nomor BPJS tidak diisi di sini (hanya lewat booking `bpjs_number`/`save_bpjs`, 12 V-13). |
| `UpdatePatientRequest` | `admin.patients.update` | Seperti Store tanpa `verbal_consent`; field sistem (`user_id`, `patient_number`, `registration_channel`, `consent_*`) tidak dapat diubah; untuk profil berakun, `email` unik di `users` (mengabaikan akun itu) dan disinkronkan ke `users.email` (00 E.1). |
| `UpdatePatientStatusRequest` | `admin.patients.status` | `is_active` boolean (pada profil berakun, `users.is_active` ikut disetel, 00 E.1). |
| `LinkPatientAccountRequest` | `admin.patients.link-account` | `account_email` akun pasien (ada, `account_type=patient`, aktif); `identity_verified` wajib dicentang. |
| `StoreWalkInAppointmentRequest` | `admin.appointments.store` | `patient_profile_id` (aktif), `practice_session_id`, lalu aturan pembayaran yang sama dengan `ReviewAppointmentRequest` (trait `HasBookingRules`: `payment_type`, `insurance_partner_id?`, `bpjs_number?`, `policy_number?`, `complaint?`, `save_bpjs?`). `toBookingData()`. Kelayakan sesi memakai `is_bookable_counter`. |
| `TransitionAppointmentRequest` | `admin.appointments.transition` | `to` ∈ {`confirmed`, `checked_in`, `completed`, `cancelled`} (`pending` dan `no_show` tidak per individu), `reason` (wajib bila `cancelled` oleh staf atau koreksi `checked_in→confirmed`), `expected_status` (anti-aksi ganda → `STALE_STATUS`); `authorize()` = `AppointmentPolicy::transition`. |
| `ExportAppointmentsRequest` | `admin.appointments.export` | Ketat (bukan turunan `ListFilterRequest`): `dari`, `sampai` (wajib, maks 366 hari), `poli?`, `dokter?`, `status?`, `kanal?`. |
| `MarkNoShowRequest` | `admin.queue.mark-no-show` | `confirm` (accepted), `reason?`; sesi harus sudah berakhir (`SESSION_NOT_ENDED`). |
| `PageRequest` | `admin.pages.update` | `title`, `subtitle?`, `body?` (hanya halaman berbody), `meta_title?` ≤ 70, `meta_description?` ≤ 160. |
| `BannerRequest` | `admin.banners.store` / `.update` | `title`, `subtitle?`, `button_label?`, `button_url?` (**`InternalOrHttpsUrl`**), `sort_order`, `is_active`, `starts_at?` (kosong = langsung tayang), `ends_at?`; `image` (maks 4096 KB, ≥ 1200×400) + alt. |
| `ArticleRequest` | `admin.articles.store` / `.update` | `title`, `slug` unik, `excerpt` ≤ 300, `body` (rich text), `article_category_id?`, `status`, `published_at?`, meta; `cover` + alt. |
| `ArticleCategoryRequest` | `admin.article-categories.store` / `.update` | `name`, `slug` unik, `description?`, `sort_order`. |
| `GalleryRequest` | `admin.galleries.store` / `.update` | `title`, `slug` unik, `description?`, `is_published`, `sort_order`. |
| `StoreGalleryPhotosRequest` | `admin.galleries.photos.store` | `photos[]` (≥ 1; tiap file aturan unggah, ≥ 600×400) + `alt_texts[]` per foto. |
| `FaqRequest` | `admin.faqs.store` / `.update` | `category` ∈ {pendaftaran, bpjs, layanan, umum}, `question`, `answer` (teks biasa), `sort_order`, `is_published`. |
| `InsurancePartnerRequest` | `admin.insurance-partners.store` / `.update` | `name`, `slug` unik, `type` ∈ {asuransi, perusahaan}, `description?`, `website_url?` (https), `is_active`, `sort_order`; `logo?` (≥ 120×40) + alt. |
| `UpdateSettingsRequest` | `admin.settings.update` | Validasi per kunci 00 E.7 menurut `type`: `booking.*` integer berbatas; `contact.email` email; `map.*_url` hanya domain Google Maps; `contact.whatsapp` digit; `privacy.consent_version` angka bertitik (grup `privasi`, 00 E.7); `site_logo` unggahan opsional (`logo` ≥ 120×40). |
| `StaffRequest` | `admin.staff.store` / `.update` | `name`, `email` unik, `phone?`, `role` ∈ {`super-admin`, `content-admin`, `registration-officer`}; `withValidator` (Update): menolak mengubah peran akun sendiri dan tidak boleh menyisakan nol Super Admin aktif (00 G). |
| `UpdateStaffStatusRequest` | `admin.staff.status` | `is_active`; menolak menonaktifkan diri sendiri dan Super Admin aktif terakhir (00 G). `admin.staff.reset-password` tidak punya field sehingga tidak memakai Form Request (otorisasi `UserPolicy::resetPassword` di controller). |
| `UpdateRolePermissionsRequest` | `admin.roles.update` | `permissions[]` ⊆ daftar baku 00 G; peran `super-admin` ditolak. |
| `UpdateContactMessageStatusRequest` | `admin.messages.status` | `status` (`ContactMessageStatus`). |
| `EditorImageUploadRequest` | `admin.media.editor-upload` | `image` (aturan unggah 00 K; `editor` ≥ 200×100), `alt_text` wajib. |
| `UpdateMediaRequest` | `admin.media.update` | `alt_text` wajib (koleksi publik), `caption?` ≤ 255. |
| `ReorderMediaRequest` | `admin.media.reorder` | `ids[]` (1–100; seluruhnya milik pemilik dan koleksi yang sama — pemilik dan koleksi diturunkan dari id media, tidak diterima dari input; 12 V-42). |

**Jumlah:** 1 basis (`ListFilterRequest`) + 5 `Auth` + 4 `Public` + 6 `Patient` + 46 `Admin` = **62 kelas Form Request**; 11 di antaranya mewarisi `ListFilterRequest` (termasuk dirinya sebagai basis yang dipakai langsung). Route tanpa body/filter (`create`, `edit`, `show`, `destroy`, `logout`, `notifications.read`, `admin.staff.reset-password`, dsb.) memakai Policy di controller saja (§2, kolom Form Request = `—`).

---
## 10. Notifikasi, Perintah Artisan, dan Rate Limiter

### 10.1 Notifikasi (`app/Notifications`)

Dikirim hanya ke pasien yang **memiliki akun** (`patient_profiles.user_id` terisi); pasien walk-in tanpa akun dihubungi petugas lewat telepon yang tercatat (00 F.8). **Kanal (00 F.8, default K-13 di 17):** `database` untuk **setiap** transisi status; `mail` **hanya** untuk transisi ke `confirmed` dan `cancelled` serta perubahan jadwal (`AppointmentScheduleChangedNotification`, `schedule_changed_at`). `via()` pada `AppointmentStatusChangedNotification`: `['database']`, ditambah `'mail'` bila `$appointment->status` ∈ {`Confirmed`, `Cancelled`}. Surel lokal masuk log harian `storage/logs/laravel-YYYY-MM-DD.log` (`MAIL_MAILER=log`, `LOG_CHANNEL=daily`; format quoted-printable, 16 §10.1) atau Mailpit. Kelas memakai `ShouldQueue` + `$afterCommit = true` (lokal `QUEUE_CONNECTION=sync` → langsung; produksi → worker `database`) **[ASUMSI]**. Isi tidak memuat nomor BPJS/polis atau data medis.

| Kelas | Pemicu | Isi |
|---|---|---|
| `AppointmentStatusChangedNotification` (`Appointment $appointment, ?AppointmentStatus $from`) | `BookingService::book()` (pendaftaran dibuat) dan setiap `AppointmentStatusService::transition()` (konfirmasi, check-in, selesai, batal, tidak hadir) | **Mail (hanya `confirmed`/`cancelled`):** subjek `Status pendaftaran {booking_code}: {label status}`; baris: salam, kode booking, dokter, poliklinik, tanggal & jam sesi, nomor antrean (`queueLabel`), status baru, alasan (bila dibatalkan); tombol "Lihat Pendaftaran" → `patient.appointments.show`; catatan bahwa surel dikirim otomatis. **Database (`toArray`):** `appointment_id`, `booking_code`, `status`, `title`, `body`. |
| `AppointmentScheduleChangedNotification` (`Appointment $appointment, string $oldRange, string $newRange`) | `ScheduleChangeService::resolve(... Apply)` untuk sesi sumber `template_time` atau `manual_time` (menyetel `schedule_changed_at`) | **Mail:** subjek `Perubahan jadwal praktik pendaftaran {booking_code}`; jam lama → baru, instruksi "Jika jadwal baru tidak sesuai, batalkan pendaftaran atau hubungi rumah sakit" + tautan WhatsApp/telepon dari `contact.*`. **Database:** `appointment_id`, `booking_code`, `old_range`, `new_range`. |

Satu kelas notifikasi lagi, `PatientWelcomeNotification` (mail saja; **K-22 default (a)** di 17): dikirim `PatientRegistrationService::register()` setelah commit; berisi sapaan, tautan ke `login`, dan ringkasan cara mendaftar kunjungan; **tanpa** tautan verifikasi (verifikasi email tidak diwajibkan, 00 E.1/N). Bila pemilik memilih K-22 (b), kelas ini cukup tidak dikirim.

Banner notifikasi belum dibaca tampil di `patient.dashboard`; menekan notifikasi memanggil `patient.notifications.read` (menandai terbaca, redirect ke detail pendaftaran). Email reset password memakai notifikasi bawaan Laravel dengan teks Indonesia di `lang/id.json`.

### 10.2 Perintah Artisan (`app/Console/Commands`)

| Kelas | Signature | Pemicu | Perilaku |
|---|---|---|---|
| `GenerateSessions` | `sessions:generate {--days=}` | Manual; opsional jadwal harian | `PracticeSessionGenerator::ensureRange(today, today + days)`; `days` default `booking.window_days`, bila diisi harus 1–120. Keluaran: "N sesi baru dibuat." Idempoten. |
| `MarkNoShowAppointments` | `appointments:mark-no-show {--date=}` | Manual; opsional jadwal harian ±23:30 | `AppointmentStatusService::markNoShowForDate($date, Actor::system())`; tanpa `--date` memproses **semua** sesi yang waktu selesainya sudah lewat hingga saat ini, dengan `--date=YYYY-MM-DD` hanya tanggal itu (00 F.8; tanggal masa depan ditolak); appointment `pending`/`confirmed` → `no_show` (tetap menghitung kuota), tiap perubahan tercatat riwayat + audit (pelaku `system`) + notifikasi. Keluaran: jumlah appointment diubah. |
| `PruneMedia` | `media:prune {--dry-run}` | Manual; opsional jadwal mingguan | `MediaService::prune($dryRun)`; mencetak jumlah baris/berkas yatim dan total byte; `--dry-run` hanya melaporkan (00 K). |
| `CreateSuperAdmin` | `app:create-super-admin {--name=} {--email=} {--password=}` | Produksi (pembuatan Super Admin pertama; tidak ada seeder demo di produksi, 00 B.2) | Meminta nama/email/password secara interaktif (password lewat `secret`, konfirmasi dua kali); validasi email unik + `Password::min(8)->letters()->numbers()`; membuat `users` (`account_type=staff`, `is_active=true`, `must_change_password=true`) + peran `super-admin`; menolak bila peran belum ada (pesan: jalankan `php artisan db:seed --class=RolePermissionSeeder`). Opsi `--password` dipakai hanya non-interaktif (peringatan: terekam di riwayat shell) **[ASUMSI]**. |

`routes/console.php` (opsional, **[ASUMSI]**; fitur inti tetap jalan tanpa Task Scheduler karena generator sesi memakai pemicu lazy 00 F.2):

```php
Schedule::command('sessions:generate')->dailyAt('00:05');
Schedule::command('appointments:mark-no-show')->dailyAt('23:30');
Schedule::command('media:prune')->weeklyOn(0, '02:00');
```

### 10.3 Rate limiter (`AppServiceProvider::configureRateLimiting()`)

```php
RateLimiter::for('login',          fn (Request $r) => Limit::perMinute(5)->by(Str::lower((string) $r->input('email')).'|'.$r->ip()));
RateLimiter::for('password-reset', fn (Request $r) => Limit::perMinutes(10, 3)->by(Str::lower((string) $r->input('email')).'|'.$r->ip()));
RateLimiter::for('register',       fn (Request $r) => Limit::perHour(5)->by($r->ip()));
RateLimiter::for('booking',        fn (Request $r) => Limit::perMinute(10)->by($r->user()?->id ?: $r->ip()));
RateLimiter::for('contact',        fn (Request $r) => Limit::perMinutes(10, 3)->by($r->ip()));
```

| Nama | Batas (00 H) | Kunci | Dipasang pada route |
|---|---|---|---|
| `login` | 5 / menit | email (huruf kecil) + IP | `login.store`, `admin.login.store` |
| `password-reset` | 3 / 10 menit | email + IP | `password.email`, `password.update` |
| `register` | 5 / jam | IP | `register.store` |
| `booking` | 10 / menit | `user_id` (fallback IP) | `patient.appointments.store` |
| `contact` | 3 / 10 menit | IP | `contact.store` |

Terlampaui → `ThrottleRequestsException` (429) → ditangani §11.3 dengan pesan Indonesia berisi sisa detik (`Retry-After`). `booking` melengkapi `Cache::lock("booking-submit:{user_id}", 10)` di `BookingService` (anti kirim ganda, 00 F.6).

---

## 11. Penanganan Galat dan Respons

### 11.1 Konvensi flash message

| Kunci | Warna / komponen | Dipakai untuk |
|---|---|---|
| `success` | hijau (`alert-success`, `role="status"`) | Operasi berhasil: "Dokter berhasil ditambahkan.", "Pendaftaran berhasil dibuat. Kode booking Anda UPH-7K3M-9QXA." |
| `error` | merah (`alert-danger`, `role="alert"`) | Operasi gagal karena aturan bisnis (`BookingException`, hapus ditolak, 419/429, akun nonaktif). |
| `warning` | kuning (`alert-warning`, `role="alert"`) | Perlu tindak lanjut tetapi tidak gagal: "3 sesi perlu ditindaklanjuti petugas pendaftaran.", penggantian password sementara. |

Aturan: kalimat Indonesia lengkap berakhir titik; objek + hasil ("… berhasil ditambahkan/diperbarui/dihapus/dinonaktifkan."); pesan galat menyebut penyebab dan tindakan berikutnya; **tanpa** detail teknis (SQL, stack trace). Kunci pola ada di `lang/id/messages.php` (mis. `messages.created = ':entity berhasil ditambahkan.'`). Galat validasi tidak memakai flash: tampil per field (`invalid-feedback`) dan ringkasan `<x-form.errors-summary>`. Flash dirender sekali per layout oleh `<x-flash-alert>`. Password sementara staf ditampilkan lewat flash `warning` **sekali** dan tidak disimpan.

### 11.2 `BookingException` dan turunannya

```php
$exceptions->render(function (BookingException $e, Request $request) {
    if ($request->expectsJson()) {
        return response()->json(['message' => $e->getMessage(), 'code' => $e->errorCode], 422);
    }
    return redirect()->to(SafeRedirect::back($request))
        ->withInput($request->except(['password', 'payment_reference', 'bpjs_number']))
        ->with('error', $e->getMessage());
});

// app/Support/SafeRedirect.php
final class SafeRedirect {
    public static function back(Request $r): string {
        // Halaman "tinjau" adalah hasil POST (tak bisa di-GET) → kembali ke langkah wizard, bukan back()
        return $r->routeIs('patient.appointments.review', 'patient.appointments.store')
            ? route('patient.appointments.create', array_filter(['sesi' => $r->input('practice_session_id')]))
            : url()->previous(route('home'));
    }
}
```
Tidak dilaporkan ke log galat (`dontReport`) karena merupakan galat bisnis yang diharapkan. Nomor BPJS/polis tidak ikut dikembalikan ke form (disimpan di sesi database) — pengguna mengisinya ulang.

### 11.3 403, 419, dan 429

```php
$exceptions->render(function (HttpExceptionInterface $e, Request $request) {
    $status = $e->getStatusCode();                    // AuthorizationException sudah menjadi 403; TokenMismatchException menjadi 419

    if ($status === 403) {
        $msg    = $e->getMessage();
        $detail = in_array($msg, ['', 'This action is unauthorized.', 'Forbidden'], true) ? null : $msg;   // pesan khusus (sudah Indonesia) bila ada
        return $request->expectsJson()
            ? response()->json(['message' => $detail ?? 'Anda tidak memiliki izin untuk melakukan tindakan ini.'], 403)
            : response()->view('errors.403', ['detail' => $detail], 403);
    }

    if ($status === 419) {                             // CSRF kedaluwarsa
        return $request->expectsJson()
            ? response()->json(['message' => 'Sesi Anda telah berakhir. Muat ulang halaman.'], 419)
            : redirect()->to(SafeRedirect::back($request))
                ->withInput($request->except(['password', 'password_confirmation', 'current_password', 'payment_reference', 'bpjs_number']))
                ->with('error', 'Sesi formulir Anda telah berakhir. Silakan periksa isian Anda lalu kirim ulang.');
    }

    if ($status === 429) {                             // ThrottleRequestsException
        $retry = (int) ($e->getHeaders()['Retry-After'] ?? 60);
        $text  = "Terlalu banyak percobaan. Silakan coba lagi dalam {$retry} detik.";
        if ($request->expectsJson()) return response()->json(['message' => $text], 429, $e->getHeaders());
        return $request->isMethod('POST')
            ? redirect()->to(SafeRedirect::back($request))->withInput($request->except(['password', 'password_confirmation']))
                ->with('error', $text)->header('Retry-After', $retry)
            : response()->view('errors.429', ['retryAfter' => $retry], 429, $e->getHeaders());
    }
    return null;     // 404/500/503 → view errors::{status} (§11.4)
});
```
`ModelNotFoundException`/`NotFoundHttpException` → 404 bawaan; `ValidationException` → redirect back (HTML) atau 422 JSON (bawaan, pesan dari `lang/id/validation.php`).

### 11.4 Halaman galat Indonesia (`resources/views/errors/`)

Semua memakai `errors/layout.blade.php` (mandiri; tanpa query DB, `config('app.name')`; bukan `<x-layouts.*>`), `role="main"`, satu `<h1>`, tombol bertulis jelas.

| Kode | Judul | Pesan | Aksi |
|---|---|---|---|
| 403 | Akses ditolak | Anda tidak memiliki izin untuk mengakses halaman atau melakukan tindakan ini. (+ `$detail` bila ada) | "Kembali", dan "Ke Dasbor" (`admin.dashboard` / `patient.dashboard`) atau "Ke Beranda" menurut tipe akun |
| 404 | Halaman tidak ditemukan | Alamat yang Anda buka tidak tersedia atau sudah dipindahkan. | "Ke Beranda", "Cari Dokter", "Jadwal Dokter" |
| 419 | Sesi berakhir | Sesi Anda telah berakhir. Muat ulang halaman lalu coba lagi. | "Muat Ulang", "Ke Beranda" |
| 429 | Terlalu banyak permintaan | Anda terlalu sering mengirim permintaan. Coba lagi dalam {retryAfter} detik. | "Kembali" |
| 500 | Terjadi kesalahan pada server | Kami mencatat masalah ini. Silakan coba lagi beberapa saat lagi. (tanpa detail teknis; `APP_DEBUG=false` di produksi) | "Ke Beranda" |
| 503 | Sedang dalam pemeliharaan | Layanan akan segera kembali. Terima kasih atas kesabaran Anda. | — (`php artisan down --render="errors::503"`) |

### 11.5 Tabel galat bisnis → respons

| Situasi | Sumber | Respons pengguna |
|---|---|---|
| Kuota penuh / sesi tutup / lewat batas / duplikat / benturan / batas aktif / pembayaran tak valid | `BookingException` (kode F.5) | Kembali ke langkah wizard (pasien) atau form walk-in (petugas) + flash `error` Indonesia (§6.2); **tidak pernah `back()`** (§11.2) |
| Sistem sibuk: lock wait timeout (1205) atau deadlock (1213) masih terjadi setelah `attempts: 3` | `BookingException` kode `SYSTEM_BUSY` | Redirect ke langkah wizard + flash `error` "Sistem sedang sibuk, silakan coba lagi beberapa saat."; tidak ada data tersimpan sebagian |
| Transisi tidak valid, status berubah pihak lain, di luar jendela waktu, alasan kosong | `InvalidTransitionException` | Redirect back + flash `error`; tombol transisi dihitung ulang dari `availableTransitions()` |
| Pasien membatalkan setelah batas | `InvalidTransitionException` (`CANCEL_CUTOFF_PASSED`) | Flash `error` yang mengarahkan menghubungi RS (telepon/WhatsApp dari `contact.*`) |
| Penghapusan data yang dirujuk riwayat | `deletionBlockedReason()` | Flash `error`: "… tidak dapat dihapus karena dipakai pada data lain. Nonaktifkan saja." |
| Pelanggaran aturan jadwal/akun (benturan templat, ganti poliklinik, Super Admin terakhir, dsb.) | `ValidationException` dari service/request | Kembali ke form dengan galat per field + ringkasan |
| Akses tanpa hak | `AuthorizationException`/`abort(403)` | Halaman 403 (§11.3) |

---

## 12. Temuan Kontradiksi dan Ambiguitas pada 00

Dokumen ini ditulis terhadap 00 versi terbaru (termasuk I.5 parameter query, I.6 route `show`, `Staff\StaffAccountService`, `PatientRegistrationService`, `wizard-steps`/`module-tabs`, sumber `manual_*`/`doctor_inactive`, `is_bookable_counter`, dimensi minimum media, dan sinkronisasi status/email pasien). Tidak ditemukan kontradiksi yang menghalangi implementasi. Ambiguitas yang masih diputuskan di bagian ini:

| # | Temuan | Keputusan di bagian ini |
|:-:|---|---|
| 1 | 00 I.6 menyebut resource admin lain "`index` + `create`/`edit` saja (`->except('show')`)", tanpa menyebut `destroy`. | `->except('show')` tetap mendaftarkan `destroy`; `destroy` hanya dipakai entitas pada tabel `destroy` 00 C (selalu lewat `deletionBlockedReason()` bila bersyarat); dokter, pasien, pendaftaran, staf, halaman, pesan tidak punya `destroy` dan dideklarasikan dengan `except([... 'destroy'])`/`only` (§3.4); sesi hanya punya `destroy` untuk sesi tambahan manual tanpa appointment (`admin.sessions.destroy`, 00 I.4) (§2.3) **[ASUMSI]**. |
| 2 | Halaman *review* wizard (`patient.appointments.review`) adalah hasil POST sehingga `back()` setelah galat di `store` berujung 405 (tidak ada route GET). | `getRedirectUrl()` pada Form Request + `SafeRedirect::back()` pada handler exception (§9.2, §11.2). |
| 3 | URL `/admin/jadwal/sesi`, `/admin/jadwal/pengecualian`, `/admin/dokter/buat`, `/admin/pendaftaran/ekspor` berbagi awalan dengan route berparameter. | Urutan pendaftaran route statis lebih dulu + parameter admin dibatasi numerik via `->where([...])` pada grup (§1 aturan 2, §3.1). |
| 4 | `schedule_exceptions.doctor_id` NULL = semua dokter, tetapi 00 tidak menyebut apakah `doctor_leave` boleh global. | `doctor_leave` wajib `doctor_id`; libur RS/nasional wajib NULL; `other` bebas (`ExceptionType::requiresDoctor()`) **[ASUMSI]**. |
| 5 | `patient_number` berformat `P` + 6 digit dari `id`, tetapi `id` baru diketahui setelah insert dan dapat melebihi 999999. | Nomor sementara saat `creating`, final saat `created`; kolom ≥ `VARCHAR(10)` (verifikasi di 09). |
| 6 | "Pendaftaran hari ini" tidak menyebut status mana yang dihitung. | Semua status kecuali `cancelled`, menurut `session_date` **[ASUMSI]**; rincian per status ada di kartu "per status". |
| 7 | 00 F.2(b) memicu `ensureDailyHorizon()` hanya dari halaman jadwal publik/wizard, padahal beranda dan direktori dokter juga menampilkan sesi terdekat. | Dipicu juga dari beranda dan direktori/detail dokter (§6.4) **[ASUMSI]**. |
| 8 | Bila satu sesi memperoleh dua penyebab `needs_review` berbeda (mis. pengecualian dan perubahan jam templat), 00 hanya menyimpan satu `pending_change`. | Sudah ditetapkan 00 F.4: sumber jenis-batal (`exception`, `template_removed`, `manual_cancel`, `doctor_inactive`) mengalahkan jenis-jam (`template_time`, `manual_time`); setingkat → yang terbaru menggantikan; penggantian diaudit (`session.pending_replaced`) — diterapkan `ScheduleChangeService::setPendingChange()` (§6.6). Tidak ada urutan yang lebih halus. |
| 9 | 00 F.4 menyatakan `keep` untuk `doctor_inactive` hanya bila dokter aktif kembali, tetapi tidak menyebut nasib sesi `cancelled` saat dokter diaktifkan ulang. | Sesi `cancelled` tidak dibuka otomatis; dapat dibuka manual lewat `setSessionStatus(open)` (§6.6) **[ASUMSI]**. |
| 10 | 00 I.5 mencantumkan parameter `kembali` ("URL kembali internal") tanpa menyebut halaman yang memakainya. | Dipakai tombol "Kembali" pada form walk-in admin dan layar detail; divalidasi sebagai path internal (diawali `/`, bukan `//`, tanpa skema) di `ListFilterRequest` (§9) **[ASUMSI]**. |
| 11 | Filter GET yang tidak valid harus diabaikan tanpa galat 500 (00 I.5), sedangkan Form Request biasa akan mengalihkan dengan galat validasi. | Basis `ListFilterRequest` (longgar): `failedValidation()` tidak melempar; `filters()` mengembalikan hanya parameter yang lolos (§9). |
| 12 | `x-image` memakai `data-fallback` + satu listener di `app.js` (00 K) sedangkan layout admin memuat `admin.js`. | `admin.js` mengimpor `app.js` sehingga listener yang sama berlaku di admin ([10 §6](10-struktur-folder.md)). |

---
## Asumsi di bagian ini

1. **[ASUMSI]** 00 I.6 menetapkan `->except('show')` untuk resource admin selain lima layar detail; `except('show')` tetap mendaftarkan `destroy`. `destroy` dipakai hanya untuk spesialisasi, poliklinik, templat jadwal, sesi tambahan manual (`admin.sessions.destroy`, 00 I.4), pengecualian (= cabut lewat `removeException`), fasilitas, kamar, banner, artikel (*soft delete*), kategori artikel, galeri, FAQ, mitra asuransi, dan media — sesuai tabel 00 C, dengan `deletionBlockedReason()` untuk yang bersyarat; dokter, pasien, pendaftaran, staf, halaman, pesan, dan audit log tidak punya `destroy` (§2.3, §3.4).
2. **[ASUMSI]** URL aksi resource memakai `buat` dan `ubah` (`Route::resourceVerbs`); nama route tetap `create`/`edit` sesuai 00.
3. **[ASUMSI]** Route kesehatan bawaan framework `/up` (`health:` di `withRouting`) ada di luar daftar 00 §I dan tidak dihitung sebagai halaman.
4. **[ASUMSI]** `PracticeSessionGenerator::ensureDailyHorizon()` juga dipanggil dari beranda dan direktori/detail dokter (selain jadwal publik & wizard, 00 F.2(b)).
5. **[ASUMSI]** Notifikasi memakai `ShouldQueue` + `$afterCommit`; notifikasi `database` juga dicatat saat pendaftaran dibuat (bagi pasien berakun), bukan hanya saat transisi; kanal `mail` mengikuti 00 F.8/K-13 (hanya `confirmed`, `cancelled`, perubahan jadwal).
6. `InvalidTransitionException` (turunan `BookingException`; diterima 00 J.6) dengan kode `INVALID_TRANSITION`, `ALREADY_FINAL`, `FORBIDDEN_TRANSITION`, `STALE_STATUS`, `CANCEL_CUTOFF_PASSED`, `CONFIRM_AFTER_END`, `CHECKIN_NOT_TODAY`, `CHECKIN_NOT_OPEN`, `CHECKIN_CLOSED`, `CORRECTION_NOT_SAME_DAY`, `SESSION_NOT_ENDED`, `REASON_REQUIRED` — **[ASUMSI]** untuk kode-kodenya; di luar sembilan kode `BookingException` F.5 (delapan aturan + `SYSTEM_BUSY`).
7. **[ASUMSI]** Kirim ganda yang tertahan `Cache::lock("booking-submit:{user_id}", 10)` dilaporkan sebagai `DUPLICATE_BOOKING` dengan pesan khusus (kode kesembilan F.5 adalah `SYSTEM_BUSY`, bukan untuk kasus ini).
8. **[ASUMSI]** Profil pasien walk-in nonaktif menolak booking (`AuthorizationException`); untuk pasien berakun, status profil dan `users.is_active` disetel bersamaan (00 E.1) sehingga `EnsureAccountIsActive` cukup memeriksa `users.is_active`.
9. Prioritas `pending_change` mengikuti 00 F.4 (bukan lagi asumsi): jenis-batal mengalahkan jenis-jam; setingkat → terbaru menggantikan; penggantian diaudit.
10. **[ASUMSI]** Mengaktifkan kembali dokter tidak membuka otomatis sesi yang sudah `cancelled`; sesi `needs_review` sumber `doctor_inactive` dapat di-`keep`, sesi `cancelled` dibuka manual lewat `setSessionStatus(open)`.
11. **[ASUMSI]** `AuditObserver` mencatat CRUD model sederhana; `AuditLogger` *fail-closed* (galat tulis audit membatalkan operasi); teks > 500 karakter dipotong.
12. **[ASUMSI]** "Pendaftaran hari ini" = appointment ber-`session_date` hari ini dengan status ≠ `cancelled`; angka dashboard dicache 60 detik.
13. **[ASUMSI]** `clinics.code` tidak dapat diubah setelah ada appointment (label nomor antrean sudah tercetak).
14. **[ASUMSI]** Formulir kontak memakai bidang jebakan `website` (honeypot) selain `throttle:contact`.
15. **[ASUMSI]** Slug tidak berubah otomatis saat nama/judul diedit (menjaga tautan); pengguna boleh mengubah manual.
16. **[ASUMSI]** Setelah reset password, seluruh sesi lain pengguna dihapus dari tabel `sessions`; hal sama saat staf dinonaktifkan atau password staf di-reset.
17. **[ASUMSI]** Selain `email` (disinkronkan menurut 00 E.1), `users.name`/`phone` juga diselaraskan dengan `patient_profiles.full_name`/`phone` untuk akun pasien.
18. **[ASUMSI]** `media:prune` hanya menghapus berkas yatim berumur > 1 jam (menghindari balapan dengan unggahan berjalan).
19. **[ASUMSI]** Wizard mengisi `payment_reference` BPJS dari `patient_profiles.bpjs_number` bila dikosongkan dan profil menyimpannya.
20. **[ASUMSI]** `app:create-super-admin` mengatur `must_change_password=true`; opsi `--password` hanya untuk non-interaktif.
21. **[ASUMSI]** Aturan `ExceptionType`: `doctor_leave` wajib `doctor_id`, libur RS/nasional wajib `doctor_id` NULL, `other` bebas.
22. **[ASUMSI]** Basis `ListFilterRequest` longgar untuk permintaan GET-filter (parameter tak valid diabaikan, 00 I.5); parameter `kembali` hanya menerima path internal.
23. **[ASUMSI]** Cache-Control `no-store, private` pada area pasien/admin/auth; CSP produksi mempertahankan `style-src 'unsafe-inline'`.
24. Surel setelah registrasi akun = K-22 di 17 (default (a): kirim `PatientWelcomeNotification`, tanpa verifikasi wajib); notifikasi saat pendaftaran kunjungan dibuat dicatat `database`.
25. Pembukaan kembali sesi yang dibatalkan akibat dokter dinonaktifkan: manual per sesi lewat `setSessionStatus(open)` setelah dokter `active` kembali (diputuskan; butir 10). Tidak ada pertanyaan terbuka.
26. Keputusan default di 17 yang berlaku untuk bagian ini: K-04 (a) — petugas hanya melihat 4 digit terakhir BPJS/polis, tanpa izin "tampilkan lengkap"; K-07 (a) — satu akun satu profil (`patient_profiles.user_id` UNIQUE); K-13 (b) — kanal `mail` hanya `confirmed`, `cancelled`, perubahan jadwal; K-15 (a) — tanpa pelacakan "sudah dihubungi" pasien walk-in; K-17 (a) — PDF memakai DejaVu Sans; K-22 (a) — email selamat datang tanpa verifikasi wajib.

## Nama baru yang diusulkan

Nama yang tidak ada di 00 (00 hanya mendefinisikan route, service J.1, perintah Artisan J.2, middleware J.3, policy J.4, komponen J.5, model/tabel E). `PatientRegistrationService` dan `Staff\StaffAccountService` **sudah ada** di 00 J.1 dan dipakai apa adanya (metode di bawah adalah rincian). Nama dari [10-struktur-folder.md](10-struktur-folder.md) (trait enum, aturan validasi, komponen `form.rich-text`/`proof-card`, `config/navigation.php`, dst.) dicatat di bagian itu; di bawah hanya yang diperkenalkan di bagian 11.

**Controller (48 + dasar)** — `App\Http\Controllers\Controller` (abstrak, `AuthorizesRequests`);
`Public\`: `HomeController`, `PageController`, `ClinicController`, `DoctorController`, `ScheduleController`, `FacilityController`, `RoomCategoryController`, `ArticleController`, `GalleryController`, `FaqController`, `ContactController` (11);
`Auth\`: `RegisterController`, `LoginController`, `PasswordResetLinkController`, `NewPasswordController`, `AdminLoginController`, `AdminPasswordController` (6);
`Patient\`: `DashboardController`, `ProfileController`, `PasswordController`, `AppointmentController`, `NotificationController` (5);
`Admin\`: `DashboardController`, `DoctorController`, `SpecialtyController`, `ClinicController`, `DoctorScheduleController`, `PracticeSessionController`, `SessionReviewController`, `ScheduleExceptionController`, `FacilityController`, `RoomCategoryController`, `PatientController`, `AppointmentController`, `QueueController`, `PageController`, `BannerController`, `ArticleController`, `ArticleCategoryController`, `GalleryController`, `FaqController`, `InsurancePartnerController`, `SettingController`, `StaffController`, `RoleController`, `AuditLogController`, `ContactMessageController`, `MediaController` (26).

**Form Request (62 kelas)** — nama mengikuti katalog 12 §4 (V-01…V-45); yang tidak ada di 12 ditandai (baru):
`App\Http\Requests\ListFilterRequest` (basis GET-filter longgar; `filters()`; nama sama dengan 12 V-45);
`Auth\`: `RegisterRequest`, `LoginRequest`, `AdminLoginRequest`, `ForgotPasswordRequest`, `ResetPasswordRequest` (5);
`Public\`: `DoctorIndexRequest` (baru), `ScheduleIndexRequest` (baru), `ArticleIndexRequest` (baru), `StoreContactMessageRequest` (namespace `Public`, diterima 00 J.6) (4);
`Patient\`: `UpdatePatientProfileRequest`, `UpdatePasswordRequest`, `BookingWizardQueryRequest`, `ReviewAppointmentRequest`, `StoreAppointmentRequest`, `CancelAppointmentRequest` (6);
`Admin\` (46): `UpdatePasswordRequest`, `DashboardChartRequest`, `PracticeSessionIndexRequest` (baru), `AppointmentIndexRequest` (baru), `QueueIndexRequest` (baru), `CreateWalkInAppointmentRequest` (baru), `AuditLogIndexRequest` (baru), `StoreDoctorRequest`, `UpdateDoctorRequest`, `UpdateDoctorStatusRequest` (`admin.doctors.status`), `ArchiveDoctorRequest` (`admin.doctors.archive`), `UploadDoctorPhotoRequest`, `SpecialtyRequest`, `ClinicRequest`, `DoctorScheduleRequest`, `PracticeSessionRequest`, `UpdateSessionStatusRequest`, `ResolveSessionRequest`, `ScheduleExceptionRequest`, `FacilityRequest`, `RoomCategoryRequest`, `UpdateRoomAvailabilityRequest`, `StorePatientRequest`, `UpdatePatientRequest`, `UpdatePatientStatusRequest`, `LinkPatientAccountRequest`, `StoreWalkInAppointmentRequest`, `TransitionAppointmentRequest`, `ExportAppointmentsRequest`, `MarkNoShowRequest`, `PageRequest`, `BannerRequest`, `ArticleRequest`, `ArticleCategoryRequest`, `GalleryRequest`, `StoreGalleryPhotosRequest`, `FaqRequest`, `InsurancePartnerRequest`, `UpdateSettingsRequest`, `StaffRequest`, `UpdateStaffStatusRequest`, `UpdateRolePermissionsRequest`, `UpdateContactMessageStatusRequest`, `EditorImageUploadRequest`, `UpdateMediaRequest`, `ReorderMediaRequest`.
Method tambahan pada request: `ListFilterRequest::filters()`, `LoginRequest::authenticate()`, `AdminLoginRequest::authenticate()`, `StoreAppointmentRequest::toBookingData()`/`getRedirectUrl()`, `StoreWalkInAppointmentRequest::toBookingData()`; trait `App\Http\Requests\Concerns\HasBookingRules` (aturan pembayaran/keluhan bersama `ReviewAppointmentRequest`, `StoreAppointmentRequest`, `StoreWalkInAppointmentRequest`; 12 V-10/V-11/V-14).

**Command (kelas)** — `GenerateSessions`, `MarkNoShowAppointments`, `PruneMedia`, `CreateSuperAdmin` (signature sesuai 00 J.2).

**Service baru dan method tambahan**
- `Booking\AppointmentProofService` (`qrSvg`, `viewData`, `pdf`; diterima 00 J.6); `PatientRegistrationService` (`register`, `createWalkIn`, `updateProfile`, `changeStatus`); `Staff\StaffAccountService` (`create`, `update`, `changeStatus`, `resetPassword`, `syncRolePermissions`, `generateTemporaryPassword`).
- `BookingService::precheck()` (pemeriksaan F.5 tanpa kunci dan tanpa menulis; **diterima 00 J.6**).
- `AppointmentStatusService`: `availableTransitions`, `markNoShowForSession`, `markNoShowForDate(?date)`, `cancelAllForSession`, konstanta `TRANSITIONS`, `RULES`.
- `ScheduleChangeService`: `assertClinicChangeAllowed`, `conflictsFor`, `allowedResolutions` dan `applyDoctorInactive` (keduanya **diterima 00 J.6**), `createManualSession`, `updateSession`, `setSessionStatus`, `deleteTemplate`, `deleteManualSession`, `setPendingChange` (privat) (di samping `applyTemplateChange`, `applyException`, `removeException`, `resolve`).
- `DoctorAvailabilityQuery`: `sessions`, `forSession`, `doctors`, `classify`; DTO `SessionAvailability` memakai properti `isBookableOnline` dan `isBookableCounter` (= `is_bookable_online`/`is_bookable_counter` di 00 F.3).
- `PracticeSessionGenerator::ensureRange(..., ?DoctorSchedule $only)` (parameter ketiga opsional).
- `BookingCodeGenerator::normalize`, `isValid`, konstanta `ALPHABET`; `PatientNumberGenerator::format`; `QueueNumberFormatter::forAppointment`.
- `MediaService`: `deleteFor`, `updateMeta`, `reorder`, konstanta `COLLECTIONS`; `AuditLogger::diff`, `AuditLogger::redact`; `SettingsService`: `string`, `int`, `bool`, `setMany`, `group`, `bookingRules`, `logoUrl`, `whatsappUrl`, `forget`; `HtmlSanitizer::toPlainText`; `AppointmentCsvExporter`: `stream`, `query`, `filename`; `DashboardMetrics`: `activeDoctors`, `registeredPatients`, `todayAppointments`, `statusCounts`, `chart`, `roomSummary`, `pendingReviewSessions`, `todaysQueue`.

**DTO / value object / dukungan** — `App\Support\Actor`, `App\Support\SafeRedirect`; `Services\Booking\BookingData` (`fromValidated`), `Services\Booking\BookingRules` (snapshot `booking.*` + pemeriksaan F.5); `Services\Scheduling\AvailabilityFilter`, `SessionAvailability`, `ScheduleChangeReport`; `Services\Media\MediaPruneReport` (usulan), `Services\Media\PlaceholderImageGenerator`; `App\Exceptions\InvalidTransitionException` — seluruhnya **diterima di 00 J.6** kecuali `MediaPruneReport`.

**Notifikasi** — `App\Notifications\PatientWelcomeNotification` (mail saja; K-22 default (a); belum ada di 00 L).

**Model — method/scope/accessor tambahan** — `User`: `isStaff`, `isPatient`, `scopeStaff`, `scopePatients`, `scopeActive`, `roleLabel`. `PatientProfile`: `scopeActive`, `scopeWalkIn`, `scopeSearch`, `maskedBpjs`, `age`, `isWalkIn`, `hasAppointments`. `Specialty`/`Clinic`/`DoctorSchedule`/`InsurancePartner`/`RoomCategory`: `deletionBlockedReason`. `Clinic`: `scopeBookable`, `coverUrl`, `seoTitle`, `seoDescription`. `Doctor`: `scopeSearch`, `scopeInSpecialty`, `scopeInClinic`, `scopeOrdered`, `photoThumbUrl`, `primarySpecialty`. `DoctorSchedule`: `scopeEffectiveOn`, `scopeOnDay`, `dayName`, `timeRange`, `hasUpcomingActiveAppointments`. `ScheduleException`: `scopeCovering`, `scopeForDoctor`, `scopeUpcoming`, `isGlobal`, `rangeLabel`. `PracticeSession`: `scopeOnDate`, `scopeUpcoming`, `scopeOpen`, `scopeNeedsReview`, `scopeForDoctor`, `scopeForClinic`, `remaining`, `startsAt`, `endsAt`, `timeRange`, `hasActiveAppointments`, `pendingSource`. `Appointment`: `creator`, `canceller`, `scopeQuotaConsuming`, `scopeOnDate`, `scopeUpcoming`, `scopeSearch`, `scopeInStatus`, `scopeScheduleChanged`, `maskedPaymentReference`, `canBeCancelledByPatient`, `hasScheduleChanged`. `RoomCategory`: `sampleRateLabel`, `occupancyLabel`, `isFull`. `Page`: `forKey`, `hasBody`, `KEYS_WITH_BODY`. `Article`: `scopeSearch`, `scopeInCategory`, `readingTimeMinutes`. `Faq::CATEGORIES`. `Media`: `scopeInCollection`, `scopeOrphaned`, `url`, `thumbUrl`, `humanSize`, `isPublic`. `AuditLog`: `scopeForAction`, `scopeBetween`.

**Route** — tidak ada nama route baru (`admin.sessions.destroy` sudah ada di 00 I.4; ability `PracticeSessionPolicy::delete` baru untuk menerapkan tabel `destroy` 00 C); hanya penetapan URL `buat`/`ubah` untuk aksi `create`/`edit` resource admin dan parameter `{id}`/`{token}` seperti di 00.

**Lain-lain** — kunci cache `settings:all`, `sessions:generated:{Y-m-d}`, `sessions:generate` (lock), `booking-submit:{user_id}` (lock; 00), `dashboard:counters`; nama aksi audit di §6.10; berkas terjemahan `lang/id/booking.php`, `lang/id/messages.php`, `lang/id.json`.
