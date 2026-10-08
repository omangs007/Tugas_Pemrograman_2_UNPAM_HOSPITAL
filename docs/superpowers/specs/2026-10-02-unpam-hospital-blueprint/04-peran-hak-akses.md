# 04 — Matriks Peran dan Hak Akses

> **Tujuan bagian.** Bagian ini menetapkan **siapa boleh melakukan apa**: definisi lima aktor, matriks izin baku, matriks akses per halaman (1–40 dan layar tambahan), matriks Policy per model, aturan visibilitas data pasien, aturan manajemen peran, serta empat lapis penegakan di server. Menyembunyikan menu saja tidak cukup (kebutuhan §10): setiap baris pada matriks halaman harus dapat diuji dengan mengakses URL/POST langsung. Rincian aturan validasi dan keamanan lain ada di [12 — Aturan Bisnis, Validasi, dan Keamanan](12-aturan-bisnis-validasi-keamanan.md).
>
> **Bagian terkait:** [01 — Ringkasan dan Asumsi](01-ringkasan-asumsi.md) · [02 — Ruang Lingkup](02-ruang-lingkup.md) · [03 — Arsitektur](03-arsitektur.md) · [05 — Sitemap dan Matriks Halaman](05-sitemap-matriks-halaman.md) · [06 — Alur](06-alur.md) · [11 — Route, Controller, Model, Service](11-route-controller-model-service.md) · [15 — Pengujian dan Penerimaan](15-pengujian-penerimaan.md).
>
> **Penanda:** **[ASUMSI]** = ditetapkan tanpa data pemilik; **[KONFIRMASI]** = butuh keputusan pemilik. Nama izin, route, dan Policy mengikuti [00 — Keputusan Inti](00-keputusan-inti.md) bagian G, I, dan J.4.

---

## 1. Definisi Aktor

| Aktor | Identitas teknis | Cara masuk | Cakupan |
|---|---|---|---|
| **Tamu** | Pengunjung tanpa session login | Tidak ada | Website publik, registrasi, login, reset password, kirim pesan kontak |
| **Pasien** | `users.account_type = patient`, `is_active = true`, memiliki tepat satu `patient_profiles` (A-08); tanpa peran Spatie | `/masuk` | Portal pasien (`/pasien/*`) hanya untuk data miliknya; website publik |
| **Super Admin** | `account_type = staff`, peran `super-admin` | `/admin/login` | Seluruh area admin; lolos semua pemeriksaan izin/Policy lewat `Gate::before`; mengelola akun staf, hak akses, pengaturan, audit |
| **Admin Konten** | `account_type = staff`, peran `content-admin` | `/admin/login` | Dokter, spesialisasi/poliklinik, jadwal/kuota/libur, fasilitas, kamar, halaman, banner, artikel, galeri, FAQ, mitra asuransi; **tanpa data pribadi pasien** |
| **Petugas Pendaftaran** | `account_type = staff`, peran `registration-officer` | `/admin/login` | Pasien, pendaftaran, antrean, check-in, sesi terdampak, ekspor CSV, pesan kontak, ketersediaan kamar; lihat jadwal dan dokter |

Catatan:

- **Pasien walk-in tanpa akun bukan aktor.** Profilnya (`patient_profiles.user_id` NULL) dikelola Petugas Pendaftaran.
- **Satu peran per staf** pada UI (A-24); izin langsung ke pengguna tidak dipakai.
- Satu tabel `users`, satu guard `web`; pemisahan pasien/staf dilakukan oleh `account_type` dan middleware `account:*`, bukan oleh guard berbeda.
- **Super Admin tidak melewati middleware `account:*`, `active`, `password.changed`**, karena `Gate::before` hanya mempengaruhi pemeriksaan Gate/Policy/izin. Akibatnya Super Admin tidak dapat membuka portal pasien (ia tidak memiliki profil pasien).

---

## 2. Matriks Izin Baku

Sumber: 00 bagian G. Ini adalah **hasil seeder awal** (`RolePermissionSeeder`); Super Admin dapat mengubah izin peran lain lewat `admin.roles.edit` dengan pembatasan pada bagian 9 (**[ASUMSI]** A-25, A-26). Super Admin lolos melalui `Gate::before`; seeder tetap menyinkronkan seluruh izin ke peran `super-admin` agar tampilan hak akses konsisten (**[ASUMSI]** A-41).

| Izin | Arti | Super Admin | Admin Konten | Petugas Pendaftaran | Melindungi (route / ability) |
|---|---|:-:|:-:|:-:|---|
| `dashboard.view` | Lihat dashboard (widget difilter per izin) | Ya | Ya | Ya | `admin.dashboard`, `admin.dashboard.chart` |
| `doctors.view` | Lihat dokter | Ya | Ya | Ya | `admin.doctors.index`, `show`; `DoctorPolicy::viewAny/view` |
| `doctors.manage` | Tambah, ubah, foto, aktif/nonaktif, arsip dokter (tanpa hapus); menonaktifkan memicu `ScheduleChangeService::applyDoctorInactive()`, mengarsipkan hanya bila tidak ada booking aktif mendatang (00 F.4) | Ya | Ya | — | `admin.doctors.create/store/edit/update/status/archive/photo` |
| `clinics.manage` | CRUD spesialisasi dan poliklinik | Ya | Ya | — | `admin.specialties.*`, `admin.clinics.*` |
| `schedules.view` | Lihat templat, sesi, pengecualian | Ya | Ya | Ya | `admin.schedules.index`, `admin.sessions.index`, `admin.exceptions.index` (resource jadwal, sesi, dan pengecualian tidak memiliki `show`; 00 I.6) |
| `schedules.manage` | Kelola templat, sesi, pengecualian | Ya | Ya | — | tulis pada `admin.schedules.*`, `admin.sessions.*` (create/store/edit/update/status), `admin.exceptions.*` |
| `sessions.review` | Selesaikan sesi `needs_review` | Ya | — | Ya | `admin.sessions.review`, `admin.sessions.resolve` |
| `facilities.manage` | CRUD fasilitas dan kategori kamar | Ya | Ya | — | `admin.facilities.*`, `admin.room-categories.*` (kecuali `availability`; `index` juga terbuka bagi `rooms.update-availability`, 00 I.4) |
| `rooms.update-availability` | Perbarui `beds_available` | Ya | Ya | Ya **[ASUMSI]** A-04 | `admin.room-categories.availability`; `admin.room-categories.index` dalam mode ketersediaan (daftar + form ketersediaan saja; diputuskan 00 I.4 / 17 D-12) |
| `content.manage` | Halaman, banner, artikel, kategori artikel, galeri, FAQ, mitra asuransi | Ya | Ya | — | `admin.pages.*`, `admin.banners.*`, `admin.articles.*`, `admin.article-categories.*`, `admin.galleries.*`, `admin.faqs.*`, `admin.insurance-partners.*` |
| `patients.view` | Lihat data pasien | Ya | — | Ya | `admin.patients.index/show` |
| `patients.manage` | Buat walk-in, ubah, hubungkan akun, nonaktifkan | Ya | — | Ya | `admin.patients.create/store/edit/update/status/link-account` |
| `appointments.view` | Lihat booking dan bukti | Ya | — | Ya | `admin.appointments.index/show/proof`, `admin.queue.index` |
| `appointments.manage` | Buat walk-in, konfirmasi, check-in, selesai, batal, tidak hadir | Ya | — | Ya | `admin.appointments.create/store/transition`, `admin.queue.mark-no-show` |
| `reports.export` | Ekspor CSV pendaftaran | Ya | — | Ya | `admin.appointments.export` |
| `messages.manage` | Kotak pesan kontak | Ya | — | Ya **[ASUMSI]** A-05 | `admin.messages.*` |
| `settings.manage` | Pengaturan website | Ya | — | — | `admin.settings.edit/update`; Gate `settings.manage` |
| `staff.manage` | Akun staf dan hak akses peran | Ya | — | — | `admin.staff.*`, `admin.roles.*` |
| `audit.view` | Audit log | Ya | — | — | `admin.audit-logs.index/show` |

Ketergantungan fungsional antar-izin (tidak otomatis, tetapi diuji):

- Pendaftaran walk-in dengan **profil baru** membutuhkan `patients.manage` **dan** `appointments.manage`; keduanya dimiliki Petugas Pendaftaran dan Super Admin.
- `schedules.manage` tanpa `sessions.review` berarti pengguna dapat mengubah jadwal (templat, pengecualian, edit manual sesi) tetapi tidak menyelesaikan sesi terdampak; ia hanya mendapat pemberitahuan "N sesi perlu ditindaklanjuti petugas pendaftaran" (00 bagian G). Hal yang sama berlaku bagi `doctors.manage` tanpa `sessions.review` saat menonaktifkan dokter yang punya booking aktif (sesi menjadi `needs_review` sumber `doctor_inactive`).
- Rute tulis selalu didampingi izin `*.view`/`*.manage` yang tepat; `doctors.manage` tidak otomatis memberi `doctors.view` pada peran kustom, sehingga seeder dan layar hak akses menjaga pasangannya (mencentang `manage` otomatis mencentang `view` yang bersangkutan).

---

## 3. Perilaku Saat Akses Ditolak

00 tidak mendefinisikan respons penolakan; blueprint menetapkan satu aturan konsisten (**[ASUMSI]** A-27, A-49) yang tidak membocorkan keberadaan data.

| Kode | Situasi | Respons | Pesan/arah |
|---|---|---|---|
| **D1** | Tamu mengakses area bertanda login | **302** ke halaman login sesuai area: `/admin/*` → `admin.login`; lainnya → `login`. Untuk GET, URL tujuan diingat (*intended URL*) | "Silakan masuk untuk melanjutkan." |
| **D2** | Akun login, tetapi tipe akun salah atau izin tidak dimiliki | **403** (halaman galat berbahasa Indonesia; permintaan JSON mendapat JSON 403). Pengecualian: staf yang membuka portal pasien dialihkan **302** ke `admin.dashboard` (permintaan JSON tetap 403; D-142) | "Anda tidak memiliki akses ke halaman ini." + tombol kembali ke dashboard sesuai tipe akun |
| **D3** | Akun login membuka halaman khusus tamu (`/masuk`, `/daftar`, `/lupa-password`, `/reset-password/{token}`, `/admin/login`) | **302** ke dashboard sesuai tipe akun: pasien → `patient.dashboard`, staf → `admin.dashboard` | — |
| **D4** | Pasien membuka booking milik orang lain (atau kode tidak ada) | **404** (`Response::denyAsNotFound()`) | Tidak membedakan "tidak ada" dari "bukan milik Anda" |
| **D5** | Staf dengan `must_change_password = true` | **302** ke `admin.password.edit` untuk semua route admin kecuali `admin.password.edit/update` dan `admin.logout` | "Anda harus mengganti password sementara." |
| **D6** | Akun `is_active = false` | Logout paksa (session di-invalidate), **302** ke halaman login area | "Akun Anda dinonaktifkan. Hubungi administrator atau rumah sakit." |
| **D7** | Token CSRF/session kedaluwarsa | **419** (halaman "Sesi berakhir" dengan tombol kembali dan muat ulang formulir) | Data form tidak hilang bila memungkinkan |
| **D8** | Melampaui limiter (`login`, `password-reset`, `register`, `booking`, `contact`) | **429** dengan `Retry-After` | "Terlalu banyak percobaan. Coba lagi dalam beberapa menit." |

**Profil penolakan per area** (dipakai pada kolom terakhir matriks halaman):

| Profil | Area | Tamu | Pasien | Staf tanpa izin |
|---|---|---|---|---|
| **PUB** | Website publik | Akses | Akses | Akses (konten draf/nonaktif/arsip tetap **404**; A-42) |
| **GST** | Halaman khusus tamu (23–26, 31) | Akses | D3 | D3 |
| **PAT** | Portal pasien (27–30) | D1 | Akses | 302 ke `admin.dashboard` (termasuk Super Admin; JSON 403; D-142) |
| **ADM** | Area admin berizin | D1 | D2 | D2; D5 bila wajib ganti password; D6 bila nonaktif |
| **OWN** | Data milik pasien (detail/bukti/batal booking) | D1 | Akses bila pemilik; D4 bila bukan | 302 ke `admin.dashboard` (portal pasien tertutup bagi staf, D-142; staf memakai `admin.appointments.*` dengan `appointments.view`) |

---

## 4. Matriks Halaman

### 4.1 Legenda

| Simbol | Arti |
|---|---|
| **—** | Ditolak (lihat profil penolakan) |
| **L** | Boleh **melihat** (GET, tidak mengubah data) |
| **K** | Boleh **mengelola/melakukan aksi** (membuat, mengubah, menghapus, mengirim, mengubah status) sesuai izin; untuk pasien, hanya pada data miliknya |
| **Izin / aturan** | Izin yang diperiksa middleware `permission:*`/Policy, atau aturan kepemilikan |
| **Ditolak** | Profil penolakan dari bagian 3 |

Kolom aktor: **Tm** = Tamu, **Ps** = Pasien, **SA** = Super Admin, **AK** = Admin Konten, **PP** = Petugas Pendaftaran. Nilai SA/AK/PP adalah **default seeder**.

### 4.2 Website publik (halaman 1–22 + layar tambahan)

| Hlm | Layar | Route (nama) | Izin / aturan | Tm | Ps | SA | AK | PP | Ditolak |
|---|---|---|---|:-:|:-:|:-:|:-:|:-:|---|
| 1 | Beranda | `home` | Publik | L | L | L | L | L | PUB |
| 2 | Profil rumah sakit | `about` | Publik | L | L | L | L | L | PUB |
| 3 | Visi dan misi | `vision-mission` | Publik | L | L | L | L | L | PUB |
| 4 | Daftar layanan/poliklinik | `clinics.index` | Publik; hanya `is_active` | L | L | L | L | L | PUB |
| 5 | Detail layanan | `clinics.show` | Publik; nonaktif → 404 | L | L | L | L | L | PUB |
| 6 | Daftar dokter | `doctors.index` | Publik; hanya `DoctorStatus::active` | L | L | L | L | L | PUB |
| 7 | Detail dokter | `doctors.show` | Publik; nonaktif/arsip → 404 | L | L | L | L | L | PUB |
| 8 | Jadwal dokter | `schedules.index` | Publik | L | L | L | L | L | PUB |
| 9 | Daftar fasilitas | `facilities.index` | Publik; hanya `is_active` | L | L | L | L | L | PUB |
| 10 | Detail fasilitas | `facilities.show` | Publik; nonaktif → 404 | L | L | L | L | L | PUB |
| 11 | Ringkasan kamar rawat inap | `rooms.index` | Publik; hanya `is_active` | L | L | L | L | L | PUB |
| 12 | Kamar BPJS Kelas 1 | `rooms.show` (`bpjs-kelas-1`) | Publik; nonaktif → 404 | L | L | L | L | L | PUB |
| 13 | Kamar BPJS Kelas 2 | `rooms.show` (`bpjs-kelas-2`) | Publik; nonaktif → 404 | L | L | L | L | L | PUB |
| 14 | Kamar BPJS Kelas 3 | `rooms.show` (`bpjs-kelas-3`) | Publik; nonaktif → 404 | L | L | L | L | L | PUB |
| 15 | Kamar VIP/Pribadi | `rooms.show` (`vip`) | Publik; nonaktif → 404 | L | L | L | L | L | PUB |
| 16 | Informasi BPJS dan asuransi | `insurance` | Publik | L | L | L | L | L | PUB |
| 17 | Panduan pendaftaran | `registration-guide` | Publik | L | L | L | L | L | PUB |
| 18 | Daftar artikel | `articles.index` | Publik; hanya `published` | L | L | L | L | L | PUB |
| 19 | Detail artikel | `articles.show` | Publik; draf/soft-deleted → 404 | L | L | L | L | L | PUB |
| 20 | Galeri | `galleries.index` | Publik; hanya `is_published` | L | L | L | L | L | PUB |
| tambahan | Detail album | `galleries.show` | Publik; belum terbit → 404 | L | L | L | L | L | PUB |
| 21 | FAQ | `faq` | Publik; hanya `is_published` | L | L | L | L | L | PUB |
| 22 | Kontak dan lokasi (+ kirim pesan) | `contact`; `contact.store` | Publik; limiter `contact` 3/10 menit (D8) | L | L | L | L | L | PUB |
| tambahan | Kebijakan privasi | `privacy` | Publik | L | L | L | L | L | PUB |

Staf yang sudah login tetap dapat melihat website publik; pratinjau konten tidak terbit dilakukan di layar admin, bukan lewat URL publik.

### 4.3 Autentikasi (halaman 23–26)

| Hlm | Layar | Route (nama) | Izin / aturan | Tm | Ps | SA | AK | PP | Ditolak |
|---|---|---|---|:-:|:-:|:-:|:-:|:-:|---|
| 23 | Registrasi pasien | `register`, `register.store` | Middleware `guest`; limiter `register` | K | — | — | — | — | GST |
| 24 | Login pasien | `login`, `login.store` | `guest`; hanya akun `patient`; limiter `login` | K | — | — | — | — | GST |
| 25 | Permintaan reset password | `password.request`, `password.email` | `guest`; limiter `password-reset`; respons generik | K | — | — | — | — | GST |
| 26 | Form reset password | `password.reset`, `password.update` | `guest`; token valid | K | — | — | — | — | GST |
| tambahan | Logout | `logout` | `auth` (semua tipe akun) | — | K | K | K | K | D1 |

Akun staf yang mencoba login di `/masuk` mendapat pesan generik yang sama dengan salah password ("Email atau password salah"); demikian pula akun pasien di `/admin/login`.

### 4.4 Portal pasien (halaman 27–30)

Middleware: `auth`, `account:patient`, `active`.

| Hlm | Layar | Route (nama) | Izin / aturan | Tm | Ps | SA | AK | PP | Ditolak |
|---|---|---|---|:-:|:-:|:-:|:-:|:-:|---|
| 27 | Dashboard pasien | `patient.dashboard` | Data milik sendiri | — | L | — | — | — | PAT |
| 28 | Profil dan akun | `patient.profile.edit`, `patient.profile.update` | Profil milik sendiri | — | K | — | — | — | PAT |
| 28 | Ganti password | `patient.password.update` | Akun sendiri; butuh password lama | — | K | — | — | — | PAT |
| 29 | Wizard pendaftaran | `patient.appointments.create`, `patient.appointments.review`, `patient.appointments.store` | Profil milik sendiri aktif; limiter `booking` (10/menit) pada pengiriman | — | K | — | — | — | PAT |
| 30 | Riwayat pendaftaran | `patient.appointments.index` | Hanya appointment profil sendiri | — | L | — | — | — | PAT |
| 30 | Detail pendaftaran | `patient.appointments.show` | `AppointmentPolicy::view` (pemilik) | — | L | — | — | — | OWN |
| 30 | Batalkan pendaftaran | `patient.appointments.cancel` | Pemilik; status `pending`/`confirmed`; sampai `booking.cancel_cutoff_minutes` | — | K | — | — | — | OWN |
| 30 | Bukti cetak dan PDF | `patient.appointments.proof`, `patient.appointments.proof-pdf` | `AppointmentPolicy::view` (pemilik) | — | L | — | — | — | OWN |
| tambahan | Tandai notifikasi dibaca | `patient.notifications.read` | Notifikasi milik sendiri (`$user->notifications()`) | — | K | — | — | — | PAT |

Super Admin, Admin Konten, dan Petugas Pendaftaran **tidak** dapat membuka portal pasien (dialihkan ke `admin.dashboard`, D-142): untuk melihat appointment pasien, staf memakai `admin.appointments.*`.

### 4.5 Area admin (halaman 31–40 dan layar tambahan)

Middleware dasar: `auth`, `account:staff`, `active`, `password.changed`, lalu `permission:*`. Pengecualian `password.changed`: `admin.password.edit/update` dan `admin.logout` (**[ASUMSI]** A-48).

| Hlm | Layar | Route (nama) | Izin / aturan | Tm | Ps | SA | AK | PP | Ditolak |
|---|---|---|---|:-:|:-:|:-:|:-:|:-:|---|
| 31 | Login admin | `admin.login`, `admin.login.store` | `guest`; hanya akun `staff`; limiter `login` | K | — | — | — | — | GST |
| 31 | Logout admin | `admin.logout` | `auth` staf | — | — | K | K | K | ADM |
| tambahan | Ganti password staf | `admin.password.edit`, `admin.password.update` | Staf login; akun sendiri | — | — | K | K | K | ADM |
| 32 | Dashboard | `admin.dashboard`, `admin.dashboard.chart` (JSON) | `dashboard.view`; widget per izin (bagian 7) | — | — | L | L | L | ADM |
| 33 | Dokter: daftar dan detail | `admin.doctors.index`, `admin.doctors.show` | `doctors.view` | — | — | L | L | L | ADM |
| 33 | Dokter: tambah, ubah, status, arsip, foto | `admin.doctors.create/store/edit/update`, `admin.doctors.status`, `admin.doctors.archive`, `admin.doctors.photo` | `doctors.manage`; arsip hanya bila tanpa booking aktif mendatang (00 F.4) | — | — | K | K | — | ADM |
| 34 | Spesialisasi | `admin.specialties.*` | `clinics.manage` | — | — | K | K | — | ADM |
| 34 | Poliklinik/layanan | `admin.clinics.*` | `clinics.manage` | — | — | K | K | — | ADM |
| 35 | Templat jadwal: lihat | `admin.schedules.index` | `schedules.view` | — | — | L | L | L | ADM |
| 35 | Templat jadwal: kelola | `admin.schedules.create/store/edit/update/destroy` | `schedules.manage`; hapus hanya bila tidak ada sesi ber-appointment (00 C) | — | — | K | K | — | ADM |
| 35 | Sesi praktik: daftar | `admin.sessions.index` | `schedules.view`; **AK hanya melihat jumlah booking** | — | — | L | L | L | ADM |
| 35 | Sesi praktik: tambah, ubah, buka/tutup/batalkan | `admin.sessions.create/store/edit/update`, `admin.sessions.status` | `schedules.manage`; kuota tidak boleh < `booked_count`; jam/pembatalan sesi ber-appointment aktif menjadi `needs_review` (`manual_time`/`manual_cancel`); tanpa route `destroy` | — | — | K | K | — | ADM |
| 35 | Pengecualian: lihat | `admin.exceptions.index` | `schedules.view` | — | — | L | L | L | ADM |
| 35 | Pengecualian: kelola | `admin.exceptions.create/store/edit/update/destroy` | `schedules.manage`; `start_date` tidak di masa lalu; `destroy` = `ScheduleChangeService::removeException` | — | — | K | K | — | ADM |
| 35 | Sesi terdampak: lihat dan selesaikan | `admin.sessions.review`, `admin.sessions.resolve` | `sessions.review` | — | — | K | — | K | ADM |
| 36 | Fasilitas | `admin.facilities.*` | `facilities.manage` | — | — | K | K | — | ADM |
| 36 | Kategori kamar: daftar | `admin.room-categories.index` | `facilities.manage` **atau** `rooms.update-availability` (middleware `permission:facilities.manage\|rooms.update-availability`; 00 I.4 / 17 D-12); pengguna hanya dengan izin kedua melihat **mode ketersediaan** (daftar + form ketersediaan, tanpa tombol tambah/ubah/hapus) | — | — | K | K | L | ADM |
| 36 | Kategori kamar: tambah, ubah, hapus | `admin.room-categories.create/store/edit/update/destroy` | `facilities.manage` | — | — | K | K | — | ADM |
| 36 | Perbarui ketersediaan kamar | `admin.room-categories.availability` | `rooms.update-availability` | — | — | K | K | K | ADM |
| 37 | Pasien: daftar dan detail | `admin.patients.index`, `admin.patients.show` | `patients.view` | — | — | L | — | L | ADM |
| 37 | Pasien: buat, ubah, nonaktifkan, hubungkan akun | `admin.patients.create/store/edit/update`, `admin.patients.status`, `admin.patients.link-account` | `patients.manage` | — | — | K | — | K | ADM |
| 38 | Pendaftaran: daftar, detail, bukti | `admin.appointments.index`, `admin.appointments.show`, `admin.appointments.proof` | `appointments.view` | — | — | L | — | L | ADM |
| 38 | Pendaftaran: walk-in dan ubah status | `admin.appointments.create/store`, `admin.appointments.transition` | `appointments.manage` (+ `patients.manage` untuk profil baru) | — | — | K | — | K | ADM |
| 38 | Ekspor CSV | `admin.appointments.export` | `reports.export`; tercatat di audit | — | — | K | — | K | ADM |
| 38 | Antrean harian | `admin.queue.index` | `appointments.view` | — | — | L | — | L | ADM |
| 38 | Tandai tidak hadir massal | `admin.queue.mark-no-show` | `appointments.manage`; setelah `end_time` sesi | — | — | K | — | K | ADM |
| 39 | Halaman statis (15 halaman baku) | `admin.pages.index`, `admin.pages.edit`, `admin.pages.update` | `content.manage`; tanpa tambah/hapus | — | — | K | K | — | ADM |
| 39 | Banner | `admin.banners.*` | `content.manage` | — | — | K | K | — | ADM |
| 39 | Artikel | `admin.articles.*` | `content.manage` | — | — | K | K | — | ADM |
| 39 | Kategori artikel | `admin.article-categories.*` | `content.manage` | — | — | K | K | — | ADM |
| 39 | Galeri dan foto album | `admin.galleries.*`, `admin.galleries.photos.store` | `content.manage` | — | — | K | K | — | ADM |
| 39 | FAQ | `admin.faqs.*` | `content.manage` | — | — | K | K | — | ADM |
| 39 | Mitra asuransi | `admin.insurance-partners.*` | `content.manage` | — | — | K | K | — | ADM |
| 40 | Pengaturan website | `admin.settings.edit`, `admin.settings.update` | `settings.manage` | — | — | K | — | — | ADM |
| 40 | Akun staf | `admin.staff.*`, `admin.staff.status`, `admin.staff.reset-password` | `staff.manage` | — | — | K | — | — | ADM |
| 40 | Hak akses peran | `admin.roles.index`, `admin.roles.edit`, `admin.roles.update` | `staff.manage`; peran `super-admin` terkunci | — | — | K | — | — | ADM |
| 40 | Audit log | `admin.audit-logs.index`, `admin.audit-logs.show` | `audit.view`; tanpa ubah/hapus | — | — | L | — | — | ADM |
| tambahan | Kotak pesan: daftar, detail | `admin.messages.index`, `admin.messages.show` | `messages.manage` | — | — | L | — | L | ADM |
| tambahan | Kotak pesan: ubah status | `admin.messages.status` | `messages.manage` | — | — | K | — | K | ADM |
| tambahan | Media: unggah editor, ubah alt/caption, hapus, urutkan | `admin.media.editor-upload`, `admin.media.update`, `admin.media.destroy`, `admin.media.reorder` | `MediaPolicy`: izin pemilik media (bagian 5) | — | — | K | K | — | ADM |

---

## 5. Matriks Policy

Semua Policy didaftarkan untuk modelnya (auto-discovery Laravel). **Super Admin lolos seluruh ability karena `Gate::before`**; kolom "Aturan" menjelaskan perilaku untuk peran lain. Aturan mutlak yang tidak boleh dilanggar Super Admin tidak diletakkan di Policy (lihat bagian 9 dan 10.4).

| Policy | Ability | Aturan (izin atau kepemilikan) | Catatan |
|---|---|---|---|
| `DoctorPolicy` | `viewAny`, `view` | `doctors.view` | |
| | `create`, `update`, `changeStatus`, `archive` | `doctors.manage` | Unggah/ganti foto (`admin.doctors.photo`) memakai `update`. `delete` **tidak disediakan** (dokter dirujuk riwayat booking; hanya nonaktif/arsip). `changeStatus` ke `inactive` diizinkan walau ada booking aktif (sesi terdampak ditangani `applyDoctorInactive()`); `archive` hanya bila tidak ada booking aktif mendatang, diperiksa di Form Request/service (00 F.4) |
| `SpecialtyPolicy` | `viewAny`, `view`, `create`, `update` | `clinics.manage` | |
| | `delete` | `clinics.manage` dan tidak dirujuk (mis. `doctor_specialties`) | Bila dirujuk: `is_active = false` ("Nonaktifkan", 00 C) |
| `ClinicPolicy` | `viewAny`, `view`, `create`, `update` | `clinics.manage` | Mengubah `is_bookable`/`is_active` = `update` |
| | `delete` | `clinics.manage` dan tanpa dokter, templat, sesi, appointment (FK `RESTRICT`) | Bila ada: nonaktifkan |
| `DoctorSchedulePolicy` | `viewAny`, `view` | `schedules.view` | |
| | `create`, `update` | `schedules.manage` | Mengaktifkan/menonaktifkan templat = `update`. Mengganti poliklinik templat ditolak bila ada sesi mendatang ber-appointment aktif (F.4) |
| | `delete` | `schedules.manage` dan templat tidak pernah menghasilkan sesi ber-appointment | Jika pernah: nonaktifkan |
| `PracticeSessionPolicy` | `viewAny`, `view` | `schedules.view` | Admin Konten hanya mendapat jumlah booking, tanpa daftar pasien; tidak ada route `show` untuk sesi |
| | `create`, `update`, `changeStatus` | `schedules.manage` | Kuota ≥ `booked_count` |
| | `delete` | `schedules.manage` dan sesi tambahan manual (`doctor_schedule_id` NULL) tanpa appointment | Route `admin.sessions.destroy` (00 I.4); sesi dari templat ditutup/dibatalkan, tidak dihapus |
| | `review`, `resolve` | `sessions.review` | Resolusi sesuai sumber `pending_change` |
| | `markNoShow` | `appointments.manage` | Setelah `end_time` |

Daftar antrean per sesi (`admin.queue.index`, memuat nama pasien) memakai `AppointmentPolicy::viewAny` (`appointments.view`).
| `ScheduleExceptionPolicy` | `viewAny`, `view` | `schedules.view` | |
| | `create`, `update`, `delete` | `schedules.manage` | `start_date` tidak di masa lalu; `delete` = cabut pengecualian lewat `ScheduleChangeService::removeException` (00 C) |
| `AppointmentPolicy` | `viewAny` | `appointments.view` | Daftar admin |
| | `view` | Pasien: pemilik profil (`appointment.patientProfile.user_id = user.id`), selain itu **404**; Staf: `appointments.view` | Halaman bukti cetak dan PDF memakai ability `view` yang sama |
| | `createOnline` | Akun `patient` aktif dengan profil aktif | Pembuatan oleh pasien; pemeriksaan bisnis di `BookingService` |
| | `create` | `appointments.manage` | Walk-in, kanal `loket` |
| | `cancel` | Pasien: pemilik dan status `pending`/`confirmed`; Staf: `appointments.manage` | Batas waktu `booking.cancel_cutoff_minutes` ditegakkan `AppointmentStatusService` agar pesan "hubungi RS" spesifik |
| | `transition` | `appointments.manage` | Peta transisi di service |
| | `export` | `reports.export` | |
| `PatientProfilePolicy` | `viewAny` | `patients.view` | |
| | `view`, `update` | Pemilik (`user_id = user.id`) **atau** `patients.view` / `patients.manage` | Pasien hanya mengubah kolom yang diizinkan Form Request |
| | `create`, `changeStatus` | `patients.manage` | `delete` **tidak disediakan** |
| | `linkAccount` | `patients.manage` dan profil belum terhubung (`user_id` NULL) | Syarat lain (akun target tanpa riwayat) di `PatientAccountLinker` |
| `FacilityPolicy` | semua | `facilities.manage` | `delete` = hapus permanen (file media ikut dibersihkan; 00 C) |
| `RoomCategoryPolicy` | `viewAny`, `view` | `facilities.manage` **atau** `rooms.update-availability` | |
| | `create`, `update` | `facilities.manage` | |
| | `delete` | `facilities.manage` dan tidak dirujuk | Bila dirujuk: tombol hapus diganti "Nonaktifkan" (00 C) |
| | `updateAvailability` | `rooms.update-availability` | Mencatat `availability_updated_at` dan `availability_updated_by` |
| `PagePolicy` | `viewAny`, `view`, `update` | `content.manage` | `create`/`delete` **tidak disediakan** (15 halaman `is_system`; tidak ada route) |
| `BannerPolicy` | semua | `content.manage` | `delete` = hapus permanen |
| `ArticlePolicy` | `viewAny`, `view`, `create`, `update`, `delete` | `content.manage` | Menerbitkan = `update` (status `published`); `delete` = soft delete |
| `ArticleCategoryPolicy` | semua | `content.manage` | `delete` = hapus permanen; artikel tetap ada (kategori menjadi NULL) |
| `GalleryPolicy` | semua | `content.manage` | Kelola foto album = `update`; `delete` menghapus album beserta fotonya |
| `FaqPolicy` | semua | `content.manage` | `delete` = hapus permanen |
| `InsurancePartnerPolicy` | `viewAny`, `view`, `create`, `update` | `content.manage` | |
| | `delete` | `content.manage` dan belum dirujuk appointment (FK `RESTRICT`) | Bila dirujuk: nonaktifkan |
| `ContactMessagePolicy` | `viewAny`, `view`, `updateStatus` | `messages.manage` | `delete` **tidak disediakan** (arsip); pesan memuat data pribadi pengunjung |
| `MediaPolicy` | `upload`, `update`, `delete`, `reorder` | Izin ditentukan model pemilik (tabel 5.1) | |
| `UserPolicy` (akun staf) | `viewAny`, `view`, `create`, `update`, `changeStatus`, `resetPassword` | `staff.manage` **dan** target `account_type = staff`; `changeStatus` juga mensyaratkan target ≠ diri sendiri | Akun pasien tidak dikelola lewat `staff.*`; `delete` **tidak disediakan** |
| `RolePolicy` | `viewAny`, `view`, `update` | `staff.manage` | `create`/`delete` **tidak disediakan**; mengubah peran `super-admin` ditolak (service/Form Request, bukan Policy) |
| `AuditLogPolicy` | `viewAny`, `view` | `audit.view` | `update`/`delete` **tidak disediakan**; tabel append-only |
| Gate `settings.manage` | — | Izin `settings.manage` | Dipakai `admin.settings.*` |

### 5.1 Pemetaan izin media

`MediaPolicy` memutuskan berdasarkan `model_type` dan koleksi media (00 E.6) (**[ASUMSI]** A-44).

| Pemilik media (`model_type`, koleksi) | Izin yang dibutuhkan |
|---|---|
| `Doctor` — `photo` | `doctors.manage` |
| `Clinic` — `cover`, `gallery` | `clinics.manage` |
| `Facility` — `cover`, `gallery`; `RoomCategory` — `cover`, `gallery` | `facilities.manage` |
| `Article` — `cover`; `Banner` — `image`; `Gallery` — `photos`; `InsurancePartner` — `logo` | `content.manage` |
| Logo situs (`site.logo_media_id`) — `logo` | `settings.manage` |
| `editor` dengan `model_id` NULL (gambar sisipan rich text) | Salah satu dari `content.manage`, `facilities.manage`, `clinics.manage`; penghapusan oleh pengunggah atau pemegang izin kelompok yang sama |

---

## 6. Aturan Visibilitas Data Pasien

Prinsip: **minimum yang diperlukan untuk tugas** (kebutuhan §10, §12). Nilai tabel adalah default seeder.

### 6.1 Matriks visibilitas

| Data | Pasien (miliknya) | Super Admin | Admin Konten | Petugas Pendaftaran |
|---|---|---|---|---|
| Nama lengkap, nomor pasien, jenis kelamin, tanggal lahir | Penuh | Penuh | **Tidak ada** | Penuh |
| Telepon, email kontak, alamat | Penuh | Penuh | **Tidak ada** | Penuh |
| Nomor BPJS (`bpjs_number`) | **Tersamar** (4 digit terakhir); dapat mengganti | Tersamar | **Tidak ada** | Tersamar |
| Nomor polis (`payment_reference` asuransi) | Tersamar | Tersamar | **Tidak ada** | Tersamar |
| Keluhan singkat (`complaint`) | Penuh | Penuh | **Tidak ada** | Penuh |
| Daftar dan detail appointment | Hanya miliknya | Semua | **Hanya jumlah** per sesi dan agregat dashboard | Semua |
| Riwayat status dan alasan batal | Miliknya | Semua | Tidak ada | Semua |
| Pesan kontak (nama, email, telepon, isi) | — (tidak ada akses) | Penuh | Tidak ada | Penuh |
| Audit log | — | Penuh (terredaksi) | Tidak ada | Tidak ada |
| Akun staf dan peran | — | Penuh | Tidak ada | Tidak ada |
| Notifikasi | Miliknya | — | — | — |
| Angka agregat dashboard | — | Ya | Ya | Ya |

### 6.2 Aturan

| No | Aturan | Penegakan |
|---|---|---|
| V1 | **Admin Konten tidak pernah menerima data pribadi pasien**: tidak ada query yang memuat `patient_profiles`, baris `appointments`, atau `contact_messages` pada route yang dapat dijangkau Admin Konten. Pada `admin.sessions.index` hanya `booked_count`/`withCount` yang dibaca | Route middleware (izin tidak dimiliki → 403) + penyusunan data di controller/view-model + uji |
| V2 | **Penyamaran**: nomor BPJS dan nomor polis ditampilkan sebagai `•••••••••1234` (hanya 4 digit terakhir, dari kolom `*_last4`) di **semua** tampilan, bukti pendaftaran, notifikasi, dan layar admin. Tidak ada aksi "tampilkan lengkap" pada prototipe (A-13; **[KONFIRMASI]** K-04). Kolom terenkripsi tidak pernah didekripsi untuk ditampilkan; hanya ditulis | Accessor model + komponen tampilan |
| V3 | **Ekspor CSV tanpa data sensitif**: hanya kode booking, tanggal kunjungan, jam sesi, poliklinik, dokter, nomor antrean, nomor pasien, nama pasien, jenis pembayaran, status, kanal, dibuat pada. **Tidak** menyertakan nomor BPJS/polis, alamat, telepon, tanggal lahir, keluhan. Hanya `reports.export`; setiap ekspor dicatat di audit beserta filter | `AppointmentCsvExporter` + izin |
| V4 | **Scoping pasien**: semua query portal pasien melalui profil milik pengguna login (`patient_profile_id` = profil milik `auth()->user()`). Controller portal **tidak pernah** menerima `patient_profile_id`, `user_id`, `is_active`, atau `registration_channel` dari request; Form Request tidak memasukkannya ke `validated()`; model memakai `$fillable` ketat | Controller, Form Request, `$fillable` |
| V5 | **Kepemilikan di URL**: URL booking memakai `booking_code`, tetapi `AppointmentPolicy::view` selalu diperiksa; bukan pemilik → 404 (D4) | Policy |
| V6 | **Audit tanpa data pribadi berlebih**: `audit_logs.description` memuat paling banyak nomor pasien atau kode booking, bukan nama/telepon/alamat; `old_values`/`new_values` terredaksi (`password`, `remember_token`, `token`, `bpjs_number`, `payment_reference` → `[disamarkan]`) | `AuditLogger` |
| V7 | **Respons non-HTML agregat**: `admin.dashboard.chart` hanya mengembalikan angka | Controller |
| V8 | **Notifikasi dan email** memuat kode booking, poliklinik, dokter, jadwal, status; **tidak** memuat nomor BPJS/polis atau keluhan | Kelas notifikasi |
| V9 | **QR code** hanya berisi `booking_code`; tidak ada data pasien | Pembuatan QR |
| V10 | **Pesan kontak** adalah data pribadi pengunjung; hanya `messages.manage`; IP mentah tidak disimpan (`ip_hash`) | Policy + model |

---

## 7. Visibilitas Widget Dashboard

Izin dasar `dashboard.view` (SA, AK, PP). Widget yang memuat nama pasien membutuhkan `appointments.view` (00 bagian G). Widget bertanda ✱ adalah tambahan blueprint (**[ASUMSI]** A-46).

| Widget | Isi | Izin | SA | AK | PP |
|---|---|---|:-:|:-:|:-:|
| Dokter aktif | Jumlah dokter `DoctorStatus::active` (agregat) | `dashboard.view` | Ya | Ya | Ya |
| Pasien terdaftar | Jumlah profil pasien aktif (termasuk walk-in tanpa akun) | `dashboard.view` | Ya | Ya | Ya |
| Pendaftaran hari ini | Jumlah appointment `session_date` = hari ini | `dashboard.view` | Ya | Ya | Ya |
| Pendaftaran per status | Jumlah per `AppointmentStatus` | `dashboard.view` | Ya | Ya | Ya |
| Grafik pendaftaran | Per `session_date`, filter 7/30/90 hari atau rentang kustom (A-06) | `dashboard.view` | Ya | Ya | Ya |
| Ringkasan kamar | `beds_available`/`bed_capacity` + `availability_updated_at` per kategori | `dashboard.view` | Ya | Ya | Ya |
| Daftar pendaftaran terbaru/antrean hari ini | Nama pasien, kode booking, status | `appointments.view` | Ya | — | Ya |
| Sesi terdampak ✱ | Jumlah dan tautan ke `admin.sessions.review` | `sessions.review` | Ya | — | Ya |
| Pemberitahuan sesi terdampak ✱ | Teks "N sesi perlu ditindaklanjuti petugas pendaftaran" (tanpa tautan ke layar review) | `schedules.manage` atau `doctors.manage`, tanpa `sessions.review` | — | Ya | — |
| Pesan kontak baru ✱ | Jumlah pesan `new` dan tautan | `messages.manage` | Ya | — | Ya |
| Pintasan | Tombol cepat sesuai izin (tambah dokter, walk-in, dst.) | Per izin tujuan | Ya | Ya (terbatas) | Ya (terbatas) |

---

## 8. Menu Admin per Peran (kenyamanan, bukan penegakan)

Menu disusun dengan `@can`; seluruh tujuan tetap dilindungi route middleware dan Policy.

| Kelompok menu | Item | SA | AK | PP |
|---|---|:-:|:-:|:-:|
| Beranda | Dashboard | Ya | Ya | Ya |
| Dokter dan jadwal | Dokter | Ya | Ya | Ya (lihat) |
| | Spesialisasi dan poliklinik | Ya | Ya | — |
| | Jadwal, sesi, pengecualian | Ya | Ya | Ya (lihat) |
| | Sesi terdampak | Ya | — | Ya |
| Fasilitas dan kamar | Fasilitas | Ya | Ya | — |
| | Kategori kamar dan kapasitas | Ya | Ya | Ya (ketersediaan) |
| Pasien dan pendaftaran | Pasien | Ya | — | Ya |
| | Pendaftaran | Ya | — | Ya |
| | Antrean | Ya | — | Ya |
| Konten | Halaman, banner, artikel, kategori artikel, galeri, FAQ, mitra asuransi | Ya | Ya | — |
| Komunikasi | Kotak pesan | Ya | — | Ya |
| Sistem | Pengaturan website | Ya | — | — |
| | Akun staf, hak akses | Ya | — | — |
| | Audit log | Ya | — | — |

---

## 9. Aturan Manajemen Peran

| No | Aturan | Penegakan |
|---|---|---|
| R1 | Hanya **tiga peran baku** (`super-admin`, `content-admin`, `registration-officer`). Layar hak akses hanya `index`, `edit`, `update`; tidak ada membuat/menghapus peran | Route (tidak ada route buat/hapus) |
| R2 | Peran **`super-admin` terkunci**: izinnya tidak dapat diubah, peran tidak dapat dihapus; pada layar hak akses barisnya ditandai terkunci | `StaffAccountService` + Form Request `UpdateRolePermissionsRequest` (bukan Policy; 00 G / 17 D-13; lihat 10.4) |
| R3 | **Minimal satu Super Admin aktif** harus selalu ada: ditolak bila menonaktifkan atau menurunkan peran Super Admin terakhir yang aktif. Pemeriksaan dilakukan dalam transaksi dengan `lockForUpdate()` pada akun Super Admin aktif agar dua Super Admin yang saling menonaktifkan secara bersamaan tidak lolos | `StaffAccountService` + Form Request (`StaffRequest`, `UpdateStaffStatusRequest`) |
| R4 | **Staf tidak dapat menonaktifkan akunnya sendiri, mengubah/menurunkan perannya sendiri, atau mereset passwordnya sendiri** lewat `admin.staff.reset-password` (gunakan `admin.password.edit`) | `StaffAccountService` + Form Request |
| R5 | **Satu peran per akun staf** (A-24); mengganti peran = `syncRoles` satu peran | Form Request |
| R6 | Akun staf **tidak dihapus**, hanya dinonaktifkan (`is_active = false`); menonaktifkan menghapus baris `sessions` milik akun itu sehingga terputus segera (A-45) | `StaffAccountService` |
| R7 | Password sementara (buat akun atau reset) dibuat acak oleh sistem, **ditampilkan sekali** kepada Super Admin, tidak disimpan terbaca, dan menyetel `must_change_password = true` (A-47) | `StaffAccountService` |
| R8 | **Izin terkunci per peran** pada layar hak akses (A-26): izin khusus Super Admin (`staff.manage`, `settings.manage`, `audit.view`) tidak dapat diberikan ke peran lain; izin data pasien (`patients.view`, `patients.manage`, `appointments.view`, `appointments.manage`, `reports.export`, `messages.manage`) tidak dapat diberikan ke `content-admin`. Opsi tersebut tidak ditampilkan dan ditolak di server | `UpdateRolePermissionsRequest` |
| R9 | Mencentang izin `*.manage` otomatis menyertakan `*.view` pasangannya (`doctors`, `schedules`, `patients`, `appointments`) | Form Request/seeder |
| R10 | **Perubahan peran dan izin diaudit**: pembuatan akun, perubahan peran, perubahan status aktif, reset password (tanpa nilai password), perubahan himpunan izin peran (daftar izin lama dan baru). Cache izin Spatie dibersihkan sehingga berlaku pada permintaan berikutnya | `AuditLogger` |
| R11 | Akun **pasien** tidak dikelola lewat `admin.staff.*`; penonaktifan pasien lewat `admin.patients.status` (`patients.manage`) dan juga menyetel `users.is_active = false` bila berakun (A-15) | `UserPolicy` + `PatientProfilePolicy` |
| R12 | Akun demo hanya ada di lingkungan lokal; produksi membuat Super Admin lewat `php artisan app:create-super-admin` | `DemoSeeder` bersyarat |

---

## 10. Lapisan Penegakan

### 10.1 Empat lapis

| Lapis | Mekanisme | Memutuskan | Gagal → |
|---|---|---|---|
| **1. Route middleware** | `auth` → `account:*` → `active` → `password.changed` → `permission:*` | Apakah **jenis akun** dan **izin** cukup untuk menjangkau route | 302 (D1), 403 (D2), 302 (D5/D6) |
| **2. Form Request `authorize()`** | Memeriksa ability Policy pada **objek** yang disasar (mis. `update` pada dokter tertentu) | Apakah pengguna boleh bertindak pada **instans ini** | 403 |
| **3. Policy** | Aturan per model: izin + kepemilikan + keadaan objek | Detail kepemilikan, status objek (mis. hanya `pending`/`confirmed` yang dapat dibatalkan) | 403 atau 404 (D4) |
| **4. Scoping query** | Query pasien selalu melalui profil milik pengguna; query staf dibatasi kolom/jumlah sesuai peran (V1) | Data apa yang **pernah dimuat** | Data tak ditemukan (404) |

Menu (`@can`) hanyalah lapis kenyamanan di atas keempatnya.

### 10.2 Contoh ilustratif

```php
// Lapis 1 — routes/admin.php (ilustrasi; dua grup per resource: view dan manage)
Route::middleware('permission:patients.view')->group(function () {
    Route::get('pasien', …)->name('patients.index');
    Route::get('pasien/{patient}', …)->name('patients.show');
});
Route::middleware('permission:patients.manage')->group(function () {
    Route::post('pasien', …)->name('patients.store');
    Route::patch('pasien/{patient}/status', …)->name('patients.status');
});
// 'permission:a|b' berarti salah satu izin cukup (mis. kategori kamar: facilities.manage|rooms.update-availability)
```

```php
// Lapis 2 — Form Request
public function authorize(): bool
{
    return $this->user()->can('transition', $this->route('appointment'));
}

// Lapis 3 — AppointmentPolicy
public function view(User $user, Appointment $appointment): Response|bool
{
    if ($user->account_type === AccountType::Patient) {
        return $appointment->patientProfile->user_id === $user->id
            ? true
            : Response::denyAsNotFound();
    }
    return $user->can('appointments.view');
}

// Lapis 4 — scoping portal pasien (nama relasi hanya contoh)
$appointment = $request->user()->patientProfile
    ->appointments()->where('booking_code', $code)->firstOrFail();
```

### 10.3 Urutan dan contoh hasil

| Permintaan | Hasil yang diharapkan |
|---|---|
| Tamu: `GET /admin` | 302 → `/admin/login` (D1) |
| Tamu: `GET /pasien/pendaftaran` | 302 → `/masuk` dengan *intended URL* (D1) |
| Pasien: `GET /admin` | 403 (D2) |
| Pasien: `PUT /admin/pengaturan` | 403 (D2) |
| Pasien login: `GET /masuk` | 302 → `patient.dashboard` (D3) |
| Staf login: `GET /pasien` atau `GET /pasien/profil` | 302 → `admin.dashboard` (D-142); permintaan JSON 403 |
| Pasien A: `GET /pasien/pendaftaran/{kode milik B}` | 404 (D4) |
| Pasien A: `GET .../bukti.pdf` kode milik B | 404 (D4) |
| Admin Konten: `GET /admin/pasien` | 403 (D2) |
| Admin Konten: `GET /admin/jadwal/sesi-terdampak` | 403 (D2) |
| Petugas Pendaftaran: `PUT /admin/pengaturan` | 403 (D2) |
| Petugas Pendaftaran: `GET /admin/staf` | 403 (D2) |
| Petugas Pendaftaran: `PATCH /admin/kamar/{id}/ketersediaan` | Berhasil (izin `rooms.update-availability`, A-04) |
| Petugas Pendaftaran: `GET /admin/kamar` | Berhasil, **mode ketersediaan** |
| Petugas Pendaftaran: `POST /admin/kamar` | 403 (D2) |
| Staf `must_change_password`: `GET /admin` | 302 → `admin.password.edit` (D5) |
| Akun nonaktif: permintaan apa pun | Logout paksa + 302 login (D6) |
| Super Admin: `GET /pasien` | 302 → `admin.dashboard` (D-142; permintaan JSON 403); `account:patient` tidak dilewati `Gate::before` |

### 10.4 `Gate::before` dan aturan mutlak

`Gate::before` dipasang di `AppServiceProvider`:

```php
Gate::before(fn (User $user, string $ability) => $user->hasRole('super-admin') ? true : null);
```

Nilai balik `true` **menghentikan evaluasi dan menyetujui**; `null` untuk peran lain melanjutkan evaluasi normal (tidak pernah mengembalikan `false`). Konsekuensi penting:

1. **Policy tidak dapat menahan Super Admin.** Karenanya aturan mutlak berikut **tidak** diletakkan di Policy, melainkan di service/Form Request/ketiadaan route, dan diuji khusus untuk akun Super Admin:

   | Aturan mutlak | Ditegakkan di |
   |---|---|
   | Peran `super-admin` tidak dapat diubah izinnya atau dihapus | `StaffAccountService` + `UpdateRolePermissionsRequest`, tidak ada route hapus peran |
   | Minimal satu Super Admin aktif | `StaffAccountService` + Form Request (transaksi + kunci) |
   | Staf tidak menonaktifkan/menurunkan/mereset password dirinya lewat `admin.staff.*` | `StaffAccountService` + Form Request |
   | Halaman `is_system` tidak dapat dihapus/ditambah | Tidak ada route `create`/`destroy` untuk `admin.pages.*` |
   | `audit_logs` tidak dapat diubah/dihapus | Tidak ada route; model `AuditLog` menolak update/delete |
   | Kuota sesi tidak di bawah `booked_count`; status final tidak berubah; kunci baku | `PracticeSession` update dan `AppointmentStatusService` |
   | Data yang dirujuk riwayat booking tidak dihapus permanen | FK `RESTRICT` di database |

2. **Super Admin tidak melewati middleware non-izin** (`account`, `active`, `password.changed`) maupun constraint database.
3. **Alternatif yang dipertimbangkan:** membatasi `Gate::before` hanya untuk ability berbentuk nama izin (mengandung titik) dan membiarkan Policy tetap berjalan untuk Super Admin. Tidak dipilih karena 00 bagian G menetapkan Super Admin "melewati semua pemeriksaan"; aturan mutlak di service sudah cukup dan lebih mudah diuji.

---

## 11. Pengujian Akses Minimal

Skenario di [15 — Pengujian dan Penerimaan](15-pengujian-penerimaan.md) harus mencakup sekurang-kurangnya:

1. Setiap baris pada matriks 4.2–4.5 diakses oleh setiap dari kelima aktor (data-driven), dengan hasil sesuai kolom Tm/Ps/SA/AK/PP dan kode penolakan.
2. Setiap Policy pada bagian 5: ability dengan dan tanpa izin; Super Admin lolos.
3. Isolasi pasien: pasien A tidak dapat melihat, membatalkan, atau mengunduh bukti pasien B (D4); tidak dapat mengubah profil B melalui manipulasi ID.
4. Admin Konten: `admin.sessions.index` tidak memuat nama pasien pada HTML/JSON; semua route data pasien → 403.
5. Penyamaran: tidak ada tampilan (HTML, PDF, CSV, notifikasi, log) yang memuat nomor BPJS/polis lengkap.
6. Aturan manajemen peran R2–R4, R8 diuji **sebagai Super Admin** (membuktikan aturan mutlak tidak bergantung pada Policy).
7. `must_change_password` dan akun nonaktif (D5, D6).
8. Mass assignment: mengirim `patient_profile_id`, `user_id`, `is_active`, `status` pada form pasien tidak berpengaruh.

---

## Asumsi di bagian ini

- A-04, A-05 (izin kamar dan pesan untuk Petugas) dipakai pada matriks izin dan halaman; A-08 (satu akun satu profil), A-13 (penyamaran tanpa tampil lengkap), A-15 (nonaktifkan profil menyetel `users.is_active`), A-24 (satu peran per staf), A-25, A-26, A-27, A-28.
- **[ASUMSI]** A-41: seeder menyinkronkan seluruh izin ke peran `super-admin`; `Gate::before` tetap penentu.
- **[ASUMSI]** A-42: konten draf/nonaktif/arsip/belum terbit tidak dapat dibuka lewat URL publik (404), termasuk untuk staf.
- A-43: `admin.room-categories.index` dapat dibuka dengan `facilities.manage` atau `rooms.update-availability`; pengguna hanya dengan izin kedua melihat mode ketersediaan → diputuskan di 00 I.4 / 17 D-12 (bukan lagi asumsi).
- A-28 (aturan mutlak Super Admin di `StaffAccountService` + Form Request) → diputuskan di 00 G / 17 D-13.
- **[ASUMSI]** A-44: pemetaan izin media menurut model pemilik; unggahan editor membutuhkan salah satu dari tiga izin.
- **[ASUMSI]** A-45: menonaktifkan staf menghapus baris `sessions` miliknya.
- **[ASUMSI]** A-46: widget dashboard tambahan (sesi terdampak, pemberitahuan, pesan baru) berdasarkan izin.
- **[ASUMSI]** A-47: password sementara staf dibuat acak, ditampilkan sekali, tidak disimpan terbaca.
- **[ASUMSI]** A-48: `admin.password.edit/update` dan `admin.logout` dikecualikan dari `password.changed`.
- **[ASUMSI]** A-49: respons penolakan D1–D8 (403 dengan tautan kembali, JSON 403 untuk permintaan JSON, 404 untuk kepemilikan).
- **[KONFIRMASI]** K-04: apakah Petugas Pendaftaran memerlukan aksi "tampilkan nomor BPJS/polis lengkap" (tercatat di audit); default prototipe: tidak ada.

## Nama baru yang diusulkan

| Nama | Jenis | Keterangan |
|---|---|---|
| `Staff\StaffAccountService` | Service | **Diterima (00 J.1).** Menegakkan aturan mutlak R2–R4, R6–R7 bersama Form Request |
| `UpdateRolePermissionsRequest` | Form Request | Nama mengikuti [11](11-route-controller-model-service.md) (Form Request `admin.roles.update`); menolak perubahan peran `super-admin` dan izin terkunci per peran (R2, R8); belum tercatat di 00 J.6 |
| `RolePermissionSeeder` | Seeder | Membuat tiga peran dan 19 izin baku serta pemetaan awal (bagian 2); nama sama di [13](13-data-contoh.md) dan [11](11-route-controller-model-service.md); belum tercatat di 00 J.6 |
| Ability Policy tambahan | Metode Policy | `changeStatus`, `archive`, `review`, `resolve`, `markNoShow`, `createOnline`, `cancel`, `transition`, `export`, `linkAccount`, `updateAvailability`, `updateStatus`, `resetPassword`, `upload`, `reorder` (bagian 5) — diselaraskan dengan nama final di [11](11-route-controller-model-service.md) §8 |
| Kode penolakan D1–D8 dan profil PUB/GST/PAT/ADM/OWN | Istilah dokumentasi | Hanya penanda di matriks halaman; bukan identifier kode |
