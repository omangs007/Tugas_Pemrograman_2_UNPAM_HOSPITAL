# UNPAM HOSPITAL

Prototipe website rumah sakit: situs publik, portal pasien (pendaftaran kunjungan rawat jalan), dan dashboard admin.
Laravel 12 + Blade + Bootstrap 5, MariaDB 10.4 melalui XAMPP 8.2.12 (Windows, localhost). Seluruh data adalah data contoh fiktif.

- Blueprint (sumber kebenaran): [`docs/superpowers/specs/2026-10-02-unpam-hospital-blueprint/`](docs/superpowers/specs/2026-10-02-unpam-hospital-blueprint/README.md)
- Rencana implementasi per fase: [`docs/superpowers/plans/`](docs/superpowers/plans/)
- Panduan instalasi lokal: blueprint bagian 16.

## Setup pertama (mesin baru)

Rincian lengkap ada di blueprint bagian 16; ringkasnya, jalankan dari PowerShell (variabel `$PHP`/`$COMPOSER` seperti pada "Perintah harian"):

1. `& $PHP scripts\check-env.php` harus berakhir OK (blueprint 16 §2).
2. `& $PHP $COMPOSER install`, lalu `npm install` dan `npm run build` (16 §3).
3. Buat database + user `unpam_app`, lalu salin `.env.example` → `.env` dan `.env.testing.example` → `.env.testing` dengan `DB_PASSWORD` yang sama dengan password user DB (16 §4–§5).
4. `& $PHP artisan key:generate` dan `& $PHP artisan key:generate --env=testing` (16 §5).
5. `& $PHP artisan migrate` dan `& $PHP artisan migrate --env=testing`, lalu `& $PHP artisan storage:link` (16 §6). Mulai fase 1, gunakan `& $PHP artisan migrate --seed` untuk data awal (lihat "Fase 1" di bawah).
6. Pasang VirtualHost Apache dan entri `hosts` untuk `unpam-hospital.test` (16 §7).

## Perintah harian (PowerShell)

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"
$env:Path = "$XAMPP\php;$env:Path"; Set-Location "D:\Project Web RS"

& $PHP scripts\check-env.php        # cek PHP & ekstensi XAMPP
& $PHP artisan test                 # suite tes (database unpam_hospital_test)
& $PHP vendor/bin/pint --test       # format kode
npm run build                       # build aset Vite
& $PHP $COMPOSER install            # jangan memakai composer.bat (karakter ^ hilang)
```

URL lokal: <http://unpam-hospital.test> (alternatif `php artisan serve` → <http://127.0.0.1:8000>).

## Fase 1 — Fondasi (akun, peran, audit, media, pengaturan, area admin)

Yang tersedia: login/logout staf di <http://unpam-hospital.test/admin/login>, ganti password wajib untuk akun sementara, dasbor admin sementara (sapaan), peran & izin (Super Admin, Admin Konten, Petugas Pendaftaran), audit log, media, pengaturan situs, dan komponen tampilan dasar. Halaman admin lain menyusul di fase berikutnya.

### Data awal (seeding)

```powershell
& $PHP artisan migrate:fresh --seed   # LOKAL SAJA: menghapus semua tabel dan isi database unpam_hospital
```

Hasil: 3 peran, 19 izin, 19 pengaturan situs, dan 3 akun staf demo. Seeder demo hanya berjalan di `APP_ENV=local`/`testing`; di produksi `db:seed` hanya menjalankan seeder inti dan mencetak peringatan.

| Peran | Email | Password |
|---|---|---|
| Super Admin | `superadmin@unpamhospital.test` | `Demo12345` |
| Admin Konten | `konten@unpamhospital.test` | `Demo12345` |
| Petugas Pendaftaran | `pendaftaran@unpamhospital.test` | `Demo12345` |

Password demo dapat diganti lewat `DEMO_PASSWORD` di `.env` (kosong = `Demo12345`). Akun ini adalah kredensial publik: **jangan** dipakai di produksi. Akun pasien demo ada sejak fase 5 (lihat bagian "Fase 5").

### Super Admin pertama di produksi

Tanpa seeder demo. Setelah `migrate --force` dan `db:seed --force`:

```powershell
& $PHP artisan app:create-super-admin   # interaktif: nama, email, password (tersembunyi) + konfirmasi
```

Akun yang dibuat wajib mengganti password pada login pertama dan tercatat di audit (`staff.created`, pelaku sistem). Mode non-interaktif: `--name=`, `--email=`, `--password=` (ketiganya wajib; opsi `--password` terekam di riwayat shell, hindari di produksi).

### Pembersihan media

```powershell
& $PHP artisan media:prune --dry-run   # hanya melaporkan baris/berkas yatim; tidak ada yang dihapus
& $PHP artisan media:prune             # menghapus baris yatim > 24 jam dan berkas tak tercatat > 1 jam
```

### Pemeriksaan cepat

```powershell
& $PHP artisan route:list --path=admin   # route admin fase 1
& $PHP artisan test                      # seluruh suite
```

## Fase 2 — Master Data (spesialisasi, poliklinik, dokter)

Yang tersedia: pengelolaan **Dokter** (tambah, ubah, foto, aktif/nonaktif, arsip, pemulihan dari arsip) di <http://unpam-hospital.test/admin/dokter>; **Poliklinik dan Spesialisasi** (CRUD, cover, galeri, editor rich text dengan unggah gambar) di `/admin/poliklinik` dan `/admin/spesialisasi`; halaman publik **Layanan Kami** di `/layanan` dan **Detail Layanan** di `/layanan/{slug}`. Halaman publik dokter dan jadwal tersedia sejak fase 3 (lihat bagian "Fase 3").

### Hak akses

| Peran | Dokter (lihat) | Dokter (kelola) | Poliklinik dan Spesialisasi |
|---|:-:|:-:|:-:|
| Super Admin | ya | ya | ya |
| Admin Konten | ya | ya | ya |
| Petugas Pendaftaran | ya | tidak | tidak |

Layanan yang dinonaktifkan menjawab 404 di URL publik, juga untuk staf yang login. Dokter nonaktif atau arsip tidak tampil di halaman publik. Spesialisasi atau poliklinik yang masih dirujuk tidak dapat dihapus (nonaktifkan saja).

### Data demo

```powershell
& $PHP artisan migrate:fresh --seed   # LOKAL SAJA: menghapus semua tabel dan isi database unpam_hospital
```

Selain data fase 1, hasilnya: 13 spesialisasi, 17 layanan (13 poliklinik + 4 penunjang), 18 dokter (16 aktif, 1 nonaktif, 1 arsip), dan 32 gambar contoh (foto 16 dokter, cover 16 layanan; poliklinik Gigi dan dua dokter sengaja tanpa gambar untuk menguji placeholder). Gambar dibuat lokal oleh GD tanpa unduhan dari internet dan disimpan di `storage/app/public` (jalankan `& $PHP artisan storage:link` sekali). Seeder master hanya berjalan di `APP_ENV=local`/`testing`.

### Pemeriksaan cepat (fase 2)

```powershell
& $PHP artisan route:list --path=admin   # 31 route admin (6 fase 1 + 25 fase 2)
& $PHP artisan media:prune --dry-run     # setelah seeding: 0 baris dan 0 berkas yatim
& $PHP artisan test                      # seluruh suite
npm run build                            # editor Quill ikut dibundel (tanpa CDN)
```

## Fase 3 — Penjadwalan (templat, pengecualian, sesi, dan jadwal publik)

Yang tersedia:
- **Jadwal Praktik** di <http://unpam-hospital.test/admin/jadwal> dengan tiga tab: **Templat Mingguan** (`/admin/jadwal`), **Sesi per Tanggal** (`/admin/jadwal/sesi`), dan **Pengecualian** (cuti dan libur, `/admin/jadwal/pengecualian`). Tab **Sesi Terdampak** muncul otomatis setelah fasenya (fase 7).
- Halaman publik **Cari Dokter** (`/dokter`), **Detail Dokter** (`/dokter/{slug}`), dan **Jadwal Dokter** (`/jadwal-dokter`), semuanya memakai satu sumber jadwal (`DoctorAvailabilityQuery`).
- Perintah `sessions:generate` dan jadwal harian pukul 00.05, serta skema booking (`patient_profiles`, `appointments`, `appointment_status_histories`) yang baru berupa tabel, model, dan factory. Pendaftaran kunjungan sungguhan (wizard dan `BookingService`) menyusul di fase 6.

### Hak akses

| Peran | Lihat jadwal | Kelola jadwal (tambah, ubah, hapus) | Sesi Terdampak |
|---|:-:|:-:|:-:|
| Super Admin | ya | ya | ya (fase 7) |
| Admin Konten | ya | ya | tidak |
| Petugas Pendaftaran | ya | tidak | ya (fase 7) |

Admin Konten hanya melihat jumlah terisi dan kuota per sesi ("Terisi"), tidak pernah nama atau data pasien. Halaman publik #6, #7, dan #8 terbuka untuk semua orang; dokter nonaktif, arsip, atau di poliklinik nonaktif menjawab 404 di URL detailnya, juga untuk staf yang login.

### Pembangkitan sesi

Sesi dibangkitkan dari templat mingguan untuk hari ini sampai hari ini + `booking.window_days` (30 hari) lewat tiga pemicu: setiap simpan templat atau pengecualian, sekali per hari saat halaman #6, #7, atau #8 dibuka, dan perintah manual:

```powershell
& $PHP artisan sessions:generate            # jendela bawaan (booking.window_days)
& $PHP artisan sessions:generate --days=45  # 1-120 hari
& $PHP artisan schedule:list                # sessions:generate dijadwalkan harian pukul 00.05
```

Windows Task Scheduler (blueprint 16 §11.1) bersifat opsional; tanpa itu, halaman publik tetap membangkitkan sesi sekali per hari.

Agar pekerjaan harian pukul 00.05 berjalan, cron server (atau Windows Task Scheduler) harus menjalankan `php artisan schedule:run` setiap menit. Bila itu belum dipasang, membuka halaman publik Cari Dokter (#6), Detail Dokter (#7), atau Jadwal Dokter (#8) juga memicu pembangkitan sesi harian sebagai cadangan.

### Aturan yang berlaku untuk semua peran, termasuk Super Admin

Sesi yang sudah berakhir tidak dapat diubah, ditutup, dibatalkan, atau dihapus. Kuota sesi tidak boleh di bawah jumlah terisi. Templat atau sesi yang sudah punya pendaftaran tidak dapat dihapus (nonaktifkan, tutup, atau batalkan). Dokter arsip tidak dapat diberi jadwal, dan dokter dengan pendaftaran aktif pada sesi mendatang tidak dapat diarsipkan.

### Data demo

```powershell
& $PHP artisan migrate:fresh --seed   # LOKAL SAJA: menghapus semua tabel dan isi database unpam_hospital
```

Selain data fase 1–2: 52 templat jadwal (51 aktif, 1 nonaktif milik dokter nonaktif), sesi untuk hari ini sampai hari ini + 30 (tanpa sesi lampau; sekitar 230 sesi), cuti 7 hari dr. Dimas, satu hari libur rumah sakit, satu sesi "Ditutup sementara (contoh)" dr. Citra, dan satu sesi tambahan dr. Teguh berkuota 2. Seluruh tanggal relatif terhadap hari seeding. Appointment demo menyusul di fase 6.

### Pemeriksaan cepat (fase 3)

```powershell
& $PHP artisan route:list --path=admin   # 50 route admin (31 fase 1-2 + 19 fase 3)
& $PHP artisan route:list --except-path=admin --name=doctors     # doctors.index dan doctors.show (publik)
& $PHP artisan route:list --except-path=admin --name=schedules   # schedules.index (publik)
& $PHP artisan test                      # seluruh suite
npm run build                            # gaya grid jadwal ikut dibundel
```

## Fase 4a — Konten publik dan CMS (halaman, banner, artikel, galeri, FAQ, mitra asuransi)

Yang tersedia:
- **Konten Website** di <http://unpam-hospital.test/admin/konten/halaman> dengan tujuh tab: **Halaman** (15 halaman tetap), **Banner**, **Artikel**, **Kategori Artikel**, **Galeri**, **FAQ**, dan **Mitra Asuransi** (`/admin/konten/banner`, `/artikel`, `/kategori-artikel`, `/galeri`, `/faq`, `/asuransi`).
- Halaman publik **Beranda** (`/`, banner carousel, layanan, dokter, artikel terbaru, dan CTA bantuan), **Profil** (`/profil`), **Visi dan Misi** (`/visi-misi`), **BPJS dan Asuransi** (`/bpjs-asuransi`), **Panduan Pendaftaran** (`/panduan-pendaftaran`), **Artikel** (`/artikel`, `/artikel/{slug}`), **Galeri** (`/galeri`, `/galeri/{slug}`), **FAQ** (`/faq`), dan **Kebijakan Privasi** (`/kebijakan-privasi`). Judul halaman #4, #6, dan #8 kini dibaca dari tabel `pages`.
- Fasilitas, kamar rawat inap, kontak, dan kotak pesan menyusul di fase 4b. Tombol menuju fitur fase berikutnya (Daftar Kunjungan, Masuk, Kirim Pesan) baru tampil setelah route-nya ada.

### Hak akses

| Peran | Kelola konten (halaman, banner, artikel, galeri, FAQ, mitra) |
|---|:-:|
| Super Admin | ya |
| Admin Konten | ya |
| Petugas Pendaftaran | tidak (403) |

Layar konten tidak pernah memuat data pasien. Halaman publik terbuka untuk semua orang; artikel draf, terjadwal, atau terhapus dan album yang belum terbit menjawab 404 di URL-nya, juga untuk staf yang login.

### Aturan yang berlaku untuk semua peran, termasuk Super Admin

15 halaman bersifat tetap: tidak dapat ditambah atau dihapus, hanya diubah. Isi artikel tidak boleh kosong setelah disanitasi. Satu album paling banyak 100 foto. Mitra asuransi yang masih dirujuk data pendaftaran tidak dapat dihapus (nonaktifkan saja). Menghapus artikel hanya menyembunyikannya (soft delete); menghapus kategori membuat artikelnya tanpa kategori; menghapus album ikut menghapus berkas fotonya.

### Data awal dan data demo

`PageSeeder` membuat 15 halaman di semua lingkungan (termasuk produksi) lewat `db:seed`. Data contoh konten hanya untuk lokal:

```powershell
& $PHP artisan migrate:fresh --seed   # LOKAL SAJA: menghapus semua tabel dan isi database unpam_hospital
```

Selain data fase 1–3: 15 halaman, 4 kategori artikel, 8 artikel (7 terbit dan 1 draf; satu artikel sengaja tanpa cover untuk menguji placeholder), 3 banner (banner kedua baru memiliki tombol setelah fase 6), 3 album dengan 12 foto, 12 FAQ, dan 4 mitra asuransi fiktif berlabel "Contoh". Total gambar contoh menjadi 57 (32 fase 2 + 25 konten), semuanya dibuat lokal oleh GD. Teks contoh harus ditinjau dan disesuaikan oleh admin sebelum dipakai di produksi.

Checklist deploy: jalankan `php artisan db:seed --class=PageSeeder --force` (aman di produksi dan idempoten). Admin Halaman hanya menampilkan baris yang sudah ada, sehingga bila deploy hanya menjalankan `migrate`, daftarnya kosong.

### Pemeriksaan cepat (fase 4a)

```powershell
& $PHP artisan route:list --path=admin   # 90 route admin (50 fase 1-3 + 40 fase 4a)
& $PHP artisan route:list --except-path=admin --name=articles   # articles.index dan articles.show (publik)
& $PHP artisan route:list --except-path=admin --name=galleries  # galleries.index dan galleries.show (publik)
& $PHP artisan media:prune --dry-run     # setelah seeding: 0 baris dan 0 berkas yatim
& $PHP artisan test                      # seluruh suite
npm run build                            # carousel beranda dan gaya konten ikut dibundel
```

## Fase 4b — Fasilitas, kamar rawat inap, dan kontak

Yang tersedia (dengan fase ini kedua puluh dua halaman publik lengkap):
- **Fasilitas dan Kamar** di <http://unpam-hospital.test/admin/kamar> dengan dua tab: **Kamar dan Kapasitas** (`/admin/kamar`, termasuk pembaruan ketersediaan tempat tidur secara manual) dan **Fasilitas** (`/admin/fasilitas`).
- **Pesan Kontak** (kotak pesan) di `/admin/pesan`: daftar dengan pencarian dan filter status, serta detail pesan dengan tombol Tandai Dibalas, Arsipkan, dan Pulihkan. Aplikasi tidak mengirim balasan otomatis; balas manual lewat email atau telepon.
- Halaman publik **Fasilitas** (`/fasilitas`, `/fasilitas/{slug}`), **Kamar Rawat Inap** (`/kamar-rawat-inap`, `/kamar-rawat-inap/{slug}`; empat kategori contoh), dan **Kontak dan Lokasi** (`/kontak`: alamat, peta tersemat dengan cadangan saat offline, dan formulir pesan). Beranda kini memuat blok **Ketersediaan Kamar Rawat Inap**.
- Tombol "Kirim Pesan" dan tautan "Kontak" yang menunggu route di fase 4a kini tampil otomatis; beranda memuat tautan "Lihat detail kamar" ke Kamar Rawat Inap.
- Pengaturan situs (alamat, telepon, tautan peta) baru dapat diubah dari admin di fase 8; sampai saat itu nilainya berasal dari seeder pengaturan. Bila `map.embed_url` kosong, halaman kontak menulis "Peta tidak tersedia." dan tombol Petunjuk Arah tetap tampil.

### Hak akses

| Peran | Fasilitas dan kategori kamar (tambah, ubah, hapus) | Perbarui ketersediaan kamar | Kotak pesan kontak |
|---|:-:|:-:|:-:|
| Super Admin | ya | ya | ya |
| Admin Konten | ya | ya | tidak (403) |
| Petugas Pendaftaran | tidak (403) | ya | ya |

Petugas Pendaftaran membuka daftar kamar dalam mode ketersediaan saja: ia dapat memperbarui jumlah tempat tidur tersedia, tetapi tidak dapat menambah, mengubah, atau menghapus kategori dan gambarnya. Layar Fasilitas dan Kamar tidak pernah memuat data pasien atau pesan kontak. Halaman publik terbuka untuk semua orang; fasilitas dan kategori kamar nonaktif menjawab 404 di URL-nya, juga untuk staf yang login.

### Aturan yang berlaku untuk semua peran, termasuk Super Admin

Jumlah tempat tidur tersedia tidak boleh melebihi kapasitas, dan kapasitas tidak boleh diturunkan di bawah jumlah tersedia saat ini. Ketersediaan kamar hanya diperbarui manual oleh petugas dan halaman publik menampilkan waktu pembaruan terakhirnya (tanpa klaim waktu nyata); data yang lebih tua dari 24 jam diberi peringatan. Galeri fasilitas dan kamar paling banyak 20 gambar. Status pesan kontak tidak dapat dikembalikan ke "Baru" dan pesan tidak dapat dihapus, hanya diarsipkan. Formulir kontak dibatasi 3 kiriman per 10 menit per alamat IP; alamat IP tidak disimpan, hanya hash-nya.

### Data demo

Seeder `RoomCategorySeeder`, `FacilitySeeder`, dan `ContactMessageSeeder` hanya berjalan di lokal:

```powershell
& $PHP artisan migrate:fresh --seed   # LOKAL SAJA: menghapus semua tabel dan isi database unpam_hospital
```

Selain data fase 1–4a: 4 kategori kamar (satu sengaja penuh untuk menguji badge "Penuh"), 10 fasilitas, dan 5 pesan kontak fiktif (2 baru, 1 dibaca, 1 dibalas, 1 diarsipkan). Total gambar contoh menjadi 83 (57 sebelumnya + 26 fasilitas dan kamar), semuanya dibuat lokal oleh GD. Nama kategori kamar dan tarifnya adalah contoh prototipe, bukan ketentuan BPJS atau tarif resmi, dan harus ditinjau sebelum dipakai di produksi.

### Pemeriksaan cepat (fase 4b)

```powershell
& $PHP artisan route:list --path=admin   # 106 route admin (90 fase 1-4a + 16 fase 4b)
& $PHP artisan route:list --except-path=admin --name=facilities   # facilities.index dan facilities.show (publik)
& $PHP artisan route:list --except-path=admin --name=rooms        # rooms.index dan rooms.show (publik)
& $PHP artisan route:list --except-path=admin --name=contact      # contact (GET) dan contact.store (POST, throttle)
& $PHP artisan media:prune --dry-run     # setelah seeding: 0 baris dan 0 berkas yatim
& $PHP artisan test                      # seluruh suite
npm run build                            # peta tersemat (initMapEmbeds) dan gaya fasilitas/kamar ikut dibundel
```

## Fase 5 — Akun pasien (daftar, masuk, lupa password, dashboard, dan profil)

Yang tersedia:
- **Daftar akun** di <http://unpam-hospital.test/daftar> (nama, tanggal lahir, jenis kelamin, telepon, email, alamat, password, dan persetujuan Kebijakan Privasi). Setelah daftar pasien langsung masuk ke dashboard dan menerima email selamat datang; verifikasi email tidak diwajibkan. Nomor pasien dibuat otomatis (`P000123`).
- **Masuk dan keluar** di `/masuk` (dengan "Ingat saya") dan tombol "Keluar" di header portal. Pesan galat login selalu "Email atau password salah.", apa pun penyebabnya.
- **Lupa password** di `/lupa-password`: tautan atur ulang dikirim lewat email, berlaku 60 menit, sekali pakai. Halaman selalu menjawab sama, terdaftar atau tidak. Setelah reset semua sesi akun berakhir dan pasien masuk lagi dengan password baru.
- **Dashboard pasien** di `/pasien` (sapaan, nomor pasien, keadaan kosong "Kunjungan Mendatang", ringkasan profil) dan **Profil dan Akun** di `/pasien/profil` (ubah profil, hapus nomor BPJS tersimpan, ganti password; sesi di perangkat lain berakhir saat password diganti).
- Belum ada (menyusul): pendaftaran kunjungan, riwayat, dan notifikasi (fase 6); pengelolaan pasien oleh petugas (fase 7). Tombol "Daftar Kunjungan" di beranda dan dashboard tampil otomatis setelah fase 6.

### Akun pasien demo

Seeder `PatientSeeder` hanya berjalan di lokal dan membuat tujuh akun pasien aktif (tanpa peran) beserta profilnya, semuanya berpassword sama dengan akun staf demo (`Demo12345`, atau nilai `DEMO_PASSWORD`):

| Nama | Email | No. pasien |
|---|---|---|
| Anisa Rahmawati (akun demo) | `pasien@unpamhospital.test` | P000001 |
| Budi Hartanto | `budi.hartanto@contoh.test` | P000002 |
| Dewi Susilowati | `dewi.susilowati@contoh.test` | P000003 |
| Fitri Handayani | `fitri.handayani@contoh.test` | P000004 |
| Gilang Saputra | `gilang.saputra@contoh.test` | P000005 |
| Kartika Dewi Anggraini | `kartika.anggraini@contoh.test` | P000006 |
| Mulyani Astuti | `mulyani.astuti@contoh.test` | P000007 |

```powershell
& $PHP artisan migrate:fresh --seed   # LOKAL SAJA: menghapus semua tabel dan isi database unpam_hospital
```

Tiga pasien (nomor 1, 2, dan 4) memiliki nomor BPJS fiktif yang tersimpan terenkripsi dan hanya tampil tersamar. Di produksi tidak ada akun demo: seeder menolak berjalan di luar `local` dan `testing`.

### Mencoba lupa password di lokal

Dengan `MAIL_MAILER=log` (bawaan) email tidak benar-benar terkirim dan tautannya tidak pernah ditampilkan di halaman. Ambil tautan dari log harian setelah mengirim permintaan di `/lupa-password`:

```powershell
$log  = "storage\logs\laravel-$(Get-Date -Format yyyy-MM-dd).log"
$text = (Get-Content $log -Raw) -replace '=\r?\n', '' -replace '=3D', '='
[regex]::Matches($text, 'http://unpam-hospital\.test/reset-password/[A-Za-z0-9]+\?email=[^\s"<>]+') | Select-Object -Last 1 | ForEach-Object Value
```

Rincian lengkap (termasuk Mailpit) ada di blueprint 16 §10.

### Keterbatasan fase 5

- Permintaan tautan reset dan pemakaian tautan reset berbagi satu bucket throttle (`password-reset`, 3 per 10 menit per email dan IP), sehingga permintaan ulang dapat menghabiskan jatah pemakaian tautan.
- Waktu respons permintaan reset untuk email terdaftar sedikit lebih lambat daripada email tak dikenal (pengiriman email); isi respons identik.
- Profil tidak menyediakan input nomor BPJS (nomor masuk lewat wizard fase 6); pasien hanya dapat menghapus nomor yang tersimpan.
- Pemutusan sesi perangkat lain setelah reset atau ganti password bergantung pada `SESSION_DRIVER=database` (bawaan `.env.example`); dengan driver lain sesi di perangkat lain tidak diputus.
- Token reset yang masih berlaku dicabut saat pasien mengganti password lewat halaman profil; ganti password berjalan dalam satu transaksi bersama pemutusan sesi dan audit.

### Aturan keamanan yang berlaku

Percobaan masuk dibatasi 5 per menit per email dan alamat IP, pendaftaran akun 5 per jam per IP, dan permintaan atau pemakaian tautan reset 3 per 10 menit per email dan IP (satu hitungan bersama; ini keterbatasan yang dicatat). Setiap batas yang terlewati memberi pesan "Terlalu banyak percobaan. Silakan coba lagi dalam {n} detik." Staf yang membuka `/pasien` dialihkan ke dashboard admin, dan pasien yang membuka `/admin` mendapat 403. Pasien hanya dapat mengubah profilnya sendiri; nomor pasien, kanal pendaftaran, dan persetujuan privasi tidak dapat diubah dari formulir. Login dan logout pasien tidak dicatat di audit log; pendaftaran, perubahan profil, ganti password, dan reset password dicatat tanpa data pribadi.

### Pemeriksaan cepat (fase 5)

```powershell
& $PHP artisan route:list --except-vendor --path=pasien   # 4 route portal (dashboard, profil, ubah profil, ganti password)
& $PHP artisan route:list --except-vendor --name=password # 7 route: password.request, .email, .reset, .update, patient.password.update, admin.password.edit, admin.password.update
& $PHP artisan route:list --path=admin                    # tetap 106 route admin
& $PHP artisan test                                       # seluruh suite
npm run build                                             # gaya kartu auth lebar (.auth-card-wide) ikut dibundel
```

## Fase 6 — Mesin booking, wizard pendaftaran, riwayat, dan bukti

Yang tersedia:
- **Wizard pendaftaran** di <http://unpam-hospital.test/pasien/pendaftaran/baru> dalam lima langkah (Poliklinik, Dokter, Tanggal & Sesi, Data Pendaftaran, Konfirmasi). Tombol "Daftar" di jadwal dan kartu dokter publik membawa `?sesi=ID` langsung ke langkah 4; tamu diminta masuk lebih dulu dan kembali ke URL yang sama. Jenis pembayaran: Umum, BPJS (13 digit; nomor yang tersimpan di profil dapat dipakai ulang tanpa diketik lagi), atau Asuransi. Wizard berfungsi penuh tanpa JavaScript.
- **Kode booking** `UPH-XXXX-XXXX` dan **nomor antrean** `{kode poli}-{nnn}` diberikan saat pendaftaran berhasil dikirim. Status awal "Menunggu konfirmasi" (atau "Dikonfirmasi" bila pengaturan `booking.auto_confirm` aktif). Menekan tombol kirim berulang kali atau membuka dua tab tidak menggandakan pendaftaran.
- **Riwayat** di `/pasien/pendaftaran` (filter status, rentang tanggal, dan jadwal berubah), **detail** dengan riwayat status dan kode QR, **pembatalan** oleh pasien hingga 120 menit sebelum sesi (pengaturan `booking.cancel_cutoff_minutes`; sesudahnya pasien menghubungi rumah sakit), serta **bukti pendaftaran** yang dapat dicetak (A5) dan diunduh sebagai PDF.
- **Dashboard** menampilkan hingga lima kunjungan mendatang, banner "Jadwal … pendaftaran Anda berubah" dan pemberitahuan yang belum dibaca; header publik memuat dropdown **Akun Saya** (Dashboard, Riwayat Pendaftaran, Profil dan Akun, Keluar).
- **Notifikasi**: setiap perubahan status tercatat di dashboard; email (lokal masuk log) hanya untuk dikonfirmasi, dibatalkan, dan perubahan jadwal (koreksi check-in tidak mengirim email). Dikirim sinkron setelah transaksi berhasil; kegagalan kirim hanya dicatat dan tidak membatalkan pendaftaran.
- **Aturan**: pendaftaran online ditutup 60 menit sebelum sesi dimulai, jendela 30 hari ke depan, maksimal 3 pendaftaran aktif per pasien, satu pendaftaran aktif per sesi, dan sesi pada hari yang sama tidak boleh tumpang tindih; semua angka berasal dari pengaturan situs (`website_settings`).
- Belum ada (menyusul fase 7): layar petugas untuk pendaftaran, antrean, walk-in, dan bukti admin; perubahan jadwal yang memicu notifikasi dari layar Sesi Terdampak; penandaan tidak hadir otomatis.

### Mencoba di lokal

Masuk sebagai `pasien@unpamhospital.test` (password `Demo12345`, lihat "Akun pasien demo"). Sesi praktik dibangkitkan otomatis saat halaman jadwal atau wizard dibuka (atau jalankan `& $PHP artisan sessions:generate`). Fase 6 tidak menambah seeder: pendaftaran dibuat lewat wizard. Untuk memeriksa email notifikasi, buka log harian (`storage\logs\laravel-YYYY-MM-DD.log`; `MAIL_MAILER=log`) setelah pendaftaran dikonfirmasi atau dibatalkan. PDF bukti memakai font DejaVu Sans bawaan dompdf; berkas cache font ditulis ke `storage\fonts` (diabaikan git).

### Keterbatasan fase 6

- Notifikasi dikirim sinkron (tanpa antrean); antrean dirancang di fase 7.
- Notifikasi perubahan jadwal baru dapat dipicu setelah layar Sesi Terdampak (fase 7); kelas dan tampilannya sudah ada dan diuji langsung.
- Pembatalan oleh petugas, check-in, penyelesaian, dan penandaan tidak hadir sudah diatur oleh `AppointmentStatusService` tetapi belum punya layar (fase 7).
- Pemutusan pendaftaran ganda mengandalkan kunci database (`active_flag` UNIQUE) dan kunci aplikasi per akun (`Cache::lock`); dengan `CACHE_STORE=array` kunci aplikasi hanya berlaku dalam satu proses, sedangkan kunci database tetap berlaku.

### Aturan keamanan yang berlaku

Kode booking milik pasien lain, kode berformat salah, dan notifikasi milik pasien lain selalu menjawab 404 yang sama dengan kode yang tidak ada. Nomor BPJS dan polis tidak pernah ditampilkan lengkap (hanya `•••••••••` dan empat digit terakhir), tidak masuk PDF, notifikasi, audit, log, atau data formulir yang dipulihkan setelah galat; kode QR hanya memuat kode booking. Staf yang membuka halaman pendaftaran pasien dialihkan ke dashboard admin. Pendaftaran dibatasi 10 permintaan per menit per akun.

### Pemeriksaan cepat (fase 6)

```powershell
& $PHP artisan route:list --except-vendor --path=pasien   # 13 route portal (dashboard, profil, 8 pendaftaran, 1 notifikasi)
& $PHP artisan route:list --path=admin                    # tetap 106 route admin
& $PHP artisan route:list --except-vendor --json          # 150 route aplikasi
& $PHP artisan test --filter=Concurrency                  # tes konkurensi booking (butuh MariaDB berjalan)
& $PHP artisan test                                       # seluruh suite
npm run build                                             # skrip cetak bukti dan blok pembayaran wizard ikut dibundel
```
