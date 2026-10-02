# Kebutuhan Asli dari Pemilik Proyek

> Salinan apa adanya dari permintaan pemilik proyek (2026-10-02). Dokumen blueprint lain harus dapat ditelusuri ke kebutuhan di sini. Jangan diubah; perubahan kebutuhan dicatat di `17-keputusan-konfirmasi.md`.

---

Bertindaklah sebagai Senior System Analyst, Laravel Developer, Database Designer, dan UI/UX Designer.

Buatkan BLUEPRINT lengkap untuk website rumah sakit bernama **UNPAM HOSPITAL**, yang nantinya dikembangkan menggunakan Laravel dan dijalankan pada localhost Windows melalui XAMPP.

Pada tahap ini, prioritaskan blueprint yang siap menjadi pedoman implementasi. Jangan langsung menghasilkan seluruh source code sebelum blueprint selesai.

## 1. Tujuan dan ruang lingkup

Website memiliki tiga bagian utama:

1. Website publik untuk informasi rumah sakit, layanan, fasilitas, dokter, dan jadwal praktik.
2. Portal pasien untuk registrasi akun dan pendaftaran kunjungan rawat jalan.
3. Dashboard admin untuk mengelola konten website, jadwal dokter, pasien, dan pendaftaran.

Gunakan https://rumahsakitmulya.com/ sebagai referensi struktur informasi dan pengalaman pengguna. Buat desain orisinal untuk UNPAM HOSPITAL; jangan menyalin logo, foto, atau konten rumah sakit tersebut.

Anggap proyek sebagai prototipe rumah sakit. Nama dokter, foto, tarif, kontak, layanan, dan informasi operasional awal merupakan data contoh yang dapat diganti admin. Jangan mengarang klaim akreditasi atau kerja sama resmi.

## 2. Teknologi dan lingkungan

- Backend: Laravel.
- Database: MySQL-compatible melalui XAMPP.
- Web server: Apache XAMPP pada Windows.
- Frontend: Blade dan Bootstrap 5, dengan JavaScript sesuai kebutuhan.
- Bahasa antarmuka: Bahasa Indonesia.
- Zona waktu aplikasi: Asia/Jakarta.
- Dependency management: Composer; Node.js/NPM apabila diperlukan untuk build aset.
- Pilih versi Laravel berdasarkan kompatibilitas dengan versi PHP yang tersedia. Nyatakan versi dan prasyarat secara eksplisit.
- Jelaskan apabila paket XAMPP menggunakan MariaDB, termasuk kompatibilitas migration dan query yang digunakan.

Website harus dinamis, responsive, dan tetap dapat menjalankan fitur inti pada localhost tanpa layanan berbayar.

## 3. Identitas dan lokasi

Nama: UNPAM HOSPITAL.

Gunakan alamat berikut sebagai lokasi prototipe sesuai kebutuhan proyek:

Universitas Pamulang Kampus 2 Viktor
Jl. Raya Puspitek, Buaran, Kecamatan Pamulang, Kota Tangerang Selatan, Banten 15310.

Sediakan:
- Halaman kontak dan lokasi.
- Tombol “Petunjuk Arah” menuju pencarian Google Maps Kampus UNPAM Viktor.
- Peta tersemat jika koneksi internet tersedia.
- Alamat teks yang tetap tampil tanpa peta.
- Pengaturan alamat, tautan peta, telepon, WhatsApp, dan email melalui admin.

Nomor telepon, WhatsApp, dan email menggunakan placeholder yang jelas sampai tersedia data resmi.

## 4. Desain antarmuka

Buat tampilan rumah sakit yang profesional, bersih, dan mudah digunakan.

Ketentuan:
- Warna utama biru, putih, dan aksen hijau/toska.
- Logo placeholder yang mudah diganti.
- Navigasi desktop dan menu mobile.
- Breadcrumb pada halaman internal.
- Kartu dokter dengan foto, nama, spesialisasi, dan tombol jadwal.
- Tombol utama “Cari Dokter”, “Jadwal Dokter”, dan “Daftar Kunjungan”.
- Form dengan label, validasi, dan pesan kesalahan yang jelas.
- Tabel admin mendukung pencarian, filter, pagination, dan tampilan mobile.
- Status ditampilkan dengan teks serta warna.
- Sediakan kondisi loading, data kosong, berhasil, dan gagal.
- Perhatikan kontras, navigasi keyboard, alt text gambar, dan keterbacaan.
- Halaman publik memiliki judul dan meta description yang dapat dikelola admin.

## 5. Halaman dan tampilan

Rancang minimal **40 halaman/tampilan utama** berikut. Halaman harus memiliki fungsi yang berbeda; jangan menghitung modal, pagination, atau perubahan filter sebagai halaman tambahan.

### A. Website publik — 22 halaman

1. Beranda.
2. Profil rumah sakit.
3. Visi dan misi.
4. Daftar layanan/poliklinik.
5. Detail layanan/poliklinik.
6. Daftar dokter.
7. Detail dokter.
8. Jadwal dokter.
9. Daftar fasilitas.
10. Detail fasilitas.
11. Ringkasan kamar rawat inap.
12. Kamar BPJS Kelas 1.
13. Kamar BPJS Kelas 2.
14. Kamar BPJS Kelas 3.
15. Kamar VIP/Pribadi.
16. Informasi BPJS dan asuransi.
17. Panduan pendaftaran pasien.
18. Daftar artikel kesehatan.
19. Detail artikel.
20. Galeri.
21. FAQ.
22. Kontak dan lokasi.

Nama kategori kamar mengikuti kebutuhan prototipe dan harus dapat diubah admin. Jangan menganggap klasifikasi tersebut sebagai ketentuan BPJS yang sudah diverifikasi.

### B. Autentikasi dan portal pasien — 8 halaman

23. Registrasi pasien.
24. Login pasien.
25. Permintaan reset password.
26. Form reset password.
27. Dashboard pasien.
28. Profil dan pengaturan akun.
29. Pendaftaran kunjungan.
30. Riwayat dan detail pendaftaran, termasuk bukti pendaftaran.

### C. Dashboard admin — 10 halaman

31. Login admin.
32. Dashboard ringkasan.
33. Pengelolaan dokter.
34. Pengelolaan spesialisasi dan poliklinik.
35. Pengelolaan jadwal, kuota, dan hari libur dokter.
36. Pengelolaan fasilitas, kategori kamar, dan kapasitas.
37. Pengelolaan pasien.
38. Pengelolaan pendaftaran dan antrean.
39. Pengelolaan konten: profil, banner, artikel, galeri, FAQ, dan informasi asuransi.
40. Pengaturan website, akun admin, hak akses, dan audit log.

Buat matriks untuk setiap halaman: nama, URL/route, pengguna yang boleh mengakses, komponen, sumber data, dan aksi utama. Identifikasi layar tambah/edit/detail yang diperlukan di luar daftar utama.

## 6. Pengelolaan dokter dan jadwal

Admin dapat:
- Menambah, mengubah, menonaktifkan, dan mengarsipkan dokter.
- Mengunggah serta mengganti foto dokter.
- Mengelola nama, gelar, profil singkat, spesialisasi, dan poliklinik.
- Mengelola jadwal mingguan, jam mulai/selesai, kuota, serta tanggal berlaku.
- Menambahkan pengecualian jadwal, cuti, dan hari libur.
- Mengaktifkan atau menonaktifkan jadwal.

Sediakan contoh spesialisasi:
- Urologi.
- Penyakit dalam.
- Anak.
- Obstetri dan ginekologi.
- Bedah umum.
- Jantung.
- Saraf.
- Mata.
- THT.
- Kulit dan kelamin.
- Ortopedi.
- Dokter umum.
- Dokter gigi.

Pengunjung dapat mencari dokter berdasarkan nama, spesialisasi, poliklinik, dan tanggal praktik.

Jadwal pada halaman publik dan form pendaftaran wajib menggunakan sumber data yang sama.

## 7. Fasilitas dan kamar

Setiap kategori kamar memiliki:
- Nama dan deskripsi.
- Galeri foto.
- Daftar fasilitas.
- Kapasitas tempat tidur.
- Jumlah tempat tidur tersedia.
- Tarif contoh jika digunakan.
- Status aktif/nonaktif.
- Waktu pembaruan terakhir.

Ketersediaan kamar pada tahap awal diperbarui manual oleh admin. Tampilkan waktu pembaruannya dan jangan menyebutnya real-time apabila belum terintegrasi dengan sistem rumah sakit.

Informasi BPJS, tarif, dan persyaratan dapat diedit admin.

## 8. Akun dan portal pasien

Pasien dapat:
- Registrasi, login, logout, dan reset password.
- Mengelola profil: nama, tanggal lahir, jenis kelamin, nomor telepon, email, dan alamat.
- Mengganti password.
- Memilih poliklinik, dokter, tanggal, dan sesi praktik.
- Memilih jenis pembayaran: umum/pribadi, BPJS, atau asuransi.
- Melihat kuota yang masih tersedia.
- Mengirim pendaftaran.
- Melihat kode booking, nomor antrean, jadwal, dan status.
- Melihat riwayat pendaftaran.
- Membatalkan pendaftaran sesuai aturan.
- Mencetak atau mengunduh bukti pendaftaran.

Nomor BPJS hanya diminta ketika jenis pembayaran BPJS dipilih. NIK tidak wajib untuk akun prototipe kecuali dijelaskan kebutuhan bisnisnya.

Portal ini berfokus pada pendaftaran kunjungan. Rekam medis, diagnosis, resep, hasil laboratorium, dan pembayaran online ditempatkan sebagai pengembangan lanjutan.

## 9. Alur pendaftaran dan antrean

Rancang alur berikut:

Login → pilih poliklinik → pilih dokter → pilih tanggal/sesi → periksa kuota → isi data pendaftaran → konfirmasi → terbitkan bukti.

Aturan:
- Pasien tidak dapat memilih jadwal yang sudah lewat atau tidak aktif.
- Jadwal cuti/libur tidak dapat dipesan.
- Cegah pendaftaran aktif ganda untuk pasien pada sesi dokter yang sama.
- Cegah benturan kunjungan pasien pada sesi yang waktunya beririsan.
- Kuota tidak boleh terlampaui meskipun ada pendaftaran bersamaan.
- Gunakan transaksi database, locking, dan constraint yang sesuai.
- Nomor antrean unik per dokter, tanggal, dan sesi.
- Nomor antrean yang dibatalkan tidak digunakan ulang; kuota dapat dibuka kembali sesuai aturan.
- Bukti pendaftaran hanya dapat diakses pemilik dan petugas berwenang.
- Kode booking atau QR code, jika digunakan, tidak memuat data sensitif.

Definisikan status dan transisi yang diizinkan:
- Menunggu konfirmasi.
- Dikonfirmasi.
- Hadir/check-in.
- Selesai.
- Dibatalkan.
- Tidak hadir.

Jelaskan status mana yang mengurangi kuota, siapa yang boleh mengubahnya, batas pembatalan, dan penanganan ketika jadwal dokter berubah setelah ada booking.

## 10. Hak akses admin

Rancang minimal tiga peran:

- Super Admin: seluruh pengaturan dan pengelolaan akun petugas.
- Admin Konten: dokter, layanan, fasilitas, artikel, banner, dan galeri.
- Petugas Pendaftaran: pasien, booking, check-in, dan antrean.

Terapkan pembatasan di sisi server menggunakan middleware serta policy/gate. Menyembunyikan menu saja tidak cukup.

Dashboard menampilkan:
- Jumlah dokter aktif.
- Jumlah pasien terdaftar.
- Pendaftaran hari ini.
- Pendaftaran per status.
- Grafik pendaftaran berdasarkan periode.
- Ringkasan ketersediaan kamar dari data terakhir.

Sediakan filter tanggal dan ekspor CSV untuk laporan pendaftaran sesuai hak akses. Catat perubahan penting dalam audit log tanpa menyimpan password atau data sensitif berlebihan.

## 11. Database dan penyimpanan gambar

Rancang ERD serta data dictionary. Usulkan tabel yang diperlukan, antara lain:

- users.
- roles dan relasi hak akses jika diperlukan.
- patient_profiles.
- specialties.
- clinics.
- doctors.
- doctor_specialties jika dokter dapat memiliki lebih dari satu spesialisasi.
- doctor_schedules.
- schedule_exceptions.
- practice_sessions untuk sesi pada tanggal tertentu.
- appointments.
- appointment_status_histories.
- room_categories.
- rooms atau room_availability sesuai model kapasitas.
- facilities.
- articles dan article_categories.
- banners.
- galleries.
- media.
- faqs.
- insurance_partners.
- contact_messages.
- website_settings.
- audit_logs.

Untuk setiap tabel jelaskan:
- Tujuan.
- Kolom, tipe data, nullable, dan default.
- Primary key dan foreign key.
- Relasi.
- Index dan unique constraint.
- Aturan penghapusan dan pengarsipan.

Gambar disimpan sebagai file di Laravel Storage; database menyimpan path, nama, MIME type, ukuran, alt text, dan metadata. Jangan menyimpan seluruh gambar sebagai BLOB secara default.

Pisahkan:
- Media publik: foto dokter, fasilitas, banner, dan artikel.
- Dokumen privat pasien, apabila nanti ditambahkan: akses melalui controller dengan pemeriksaan hak akses.

Jelaskan penggantian gambar, pembersihan file yang tidak lagi digunakan, placeholder lokal, dan backup database beserta file unggahan.

## 12. Keamanan dan validasi

Sertakan:
- Hash password melalui fasilitas Laravel.
- CSRF protection dan regenerasi session setelah login.
- Pembatasan percobaan login serta permintaan reset password.
- Validasi server menggunakan Form Request.
- Otorisasi akses data per pengguna.
- Query aman melalui Eloquent/query builder.
- Escape output dan sanitasi konten rich text.
- Validasi unggahan berdasarkan MIME, ekstensi, ukuran, dan dimensi.
- Larangan file executable; gunakan nama file yang dibuat sistem.
- Kredensial dalam .env, tanpa hardcode.
- Halaman privasi dan persetujuan penggunaan data yang relevan.
- Pembatasan akses admin terhadap data pasien sesuai tugas.
- Audit perubahan akun, jadwal, dan pendaftaran.

Bedakan konfigurasi lokal dan produksi. Untuk localhost, reset password dapat diuji melalui mail log atau mail catcher; jangan menampilkan tautan reset di halaman publik.

## 13. Data contoh

Rancang seeder berisi:
- Akun demo untuk setiap peran serta satu pasien.
- Minimal 15 dokter fiktif.
- Minimal 10 spesialisasi.
- Jadwal mingguan dan contoh hari libur.
- Empat kategori kamar sesuai permintaan.
- Contoh fasilitas, banner, FAQ, dan artikel.
- Contoh booking dengan berbagai status.

Gunakan data fiktif dan placeholder foto lokal. Aplikasi harus tetap tampil dengan baik tanpa mengambil gambar dari internet.

Jangan memasukkan kredensial akun demo ke lingkungan produksi.

## 14. Batas fitur dan pengembangan lanjutan

Fitur inti harus berfungsi tanpa integrasi eksternal.

Pisahkan fitur lanjutan:
- Integrasi BPJS.
- Integrasi SATUSEHAT.
- Integrasi SIMRS.
- Payment gateway.
- WhatsApp API.
- Email produksi.
- Rekam medis dan hasil pemeriksaan.
- Sinkronisasi kamar secara real-time.

Jangan menyatakan integrasi tersebut sudah tersedia jika masih berupa simulasi. Tombol WhatsApp biasa harus dibedakan dari pengiriman otomatis melalui API.

## 15. Panduan instalasi yang perlu dirancang

Blueprint harus menyertakan rencana panduan instalasi Windows/XAMPP:

- Pengecekan versi PHP dan ekstensi.
- Instalasi Composer dan dependency frontend.
- Pembuatan database.
- Konfigurasi .env.
- Generate application key.
- Migration dan seeder.
- Storage link.
- Build aset.
- VirtualHost Apache dengan DocumentRoot mengarah ke folder public Laravel.
- URL lokal yang digunakan.
- Alternatif menjalankan aplikasi melalui php artisan serve.
- Pengaturan izin folder storage dan bootstrap/cache.
- Pengujian reset password lokal.
- Backup dan restore.
- Troubleshooting umum: koneksi database, APP_KEY, gambar tidak tampil, rewrite Apache, dan aset frontend.

## 16. Pengujian dan kriteria penerimaan

Buat skenario pengujian yang membuktikan:
- Semua halaman utama dapat diakses sesuai hak pengguna.
- CRUD admin tersimpan dan perubahan muncul pada website publik.
- Foto dokter dapat diunggah serta diganti.
- Jadwal libur tidak dapat dipesan.
- Pasien hanya dapat melihat dan mengubah data miliknya.
- Akses langsung ke URL admin ditolak untuk pasien.
- Password tersimpan dalam bentuk hash.
- Dua permintaan bersamaan tidak membuat kuota terlampaui.
- Booking ganda ditolak.
- Perubahan status dan pembatalan memengaruhi kuota dengan benar.
- Nomor antrean unik.
- Form memberikan pesan validasi yang jelas.
- Tampilan berfungsi pada mobile, tablet, dan desktop.
- Kondisi data kosong dan gambar hilang ditangani.
- Fitur inti tetap berjalan tanpa koneksi internet.
- Backup dapat dipulihkan.

## 17. Format keluaran blueprint

Susun hasil dengan urutan:

1. Ringkasan proyek dan asumsi.
2. Ruang lingkup fitur inti dan fitur lanjutan.
3. Arsitektur aplikasi.
4. Matriks peran dan hak akses.
5. Sitemap serta matriks minimal 40 halaman.
6. Alur pasien, admin, pendaftaran, dan antrean.
7. Wireframe tekstual halaman utama.
8. ERD menggunakan Mermaid.
9. Data dictionary.
10. Struktur folder dan komponen Laravel.
11. Rancangan route, controller, model, service, middleware, dan policy.
12. Aturan bisnis, validasi, serta keamanan.
13. Rencana data contoh.
14. Tahapan implementasi berdasarkan dependensi.
15. Skenario pengujian dan kriteria penerimaan.
16. Panduan instalasi dan operasional localhost.
17. Keputusan yang masih membutuhkan konfirmasi.

Buat blueprint yang konsisten: fitur harus memiliki halaman, route, tabel, hak akses, dan alur yang mendukungnya.

Jika ada informasi yang belum tersedia, tetapkan asumsi yang masuk akal dan tandai dengan jelas. Ajukan hanya pertanyaan yang benar-benar mengubah arsitektur atau ruang lingkup. Jika jawaban terlalu panjang, bagi menjadi bagian bernomor tanpa menghilangkan bagian yang diminta.
