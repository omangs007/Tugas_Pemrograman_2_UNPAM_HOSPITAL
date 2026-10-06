# 07 — Wireframe Tekstual Halaman Utama

## Tujuan bagian

Bagian ini menggambarkan tata letak 21 kelompok layar utama UNPAM HOSPITAL dalam bentuk wireframe teks (ASCII) beserta daftar elemen, perilaku interaksi, kondisi loading/kosong/berhasil/gagal, catatan responsif, dan catatan aksesibilitas. Wireframe adalah *panduan struktur*, bukan desain visual akhir: teks, nama, angka, dan tarif di dalamnya **fiktif dan hanya contoh**, tanpa klaim akreditasi atau kerja sama, dan tidak menyalin teks, logo, atau foto situs referensi.

Acuan: nama route, komponen Blade, izin, dan kunci pengaturan mengikuti [00 — Keputusan Inti](00-keputusan-inti.md) (bagian I, J.5, G, E.7, M). Nomor halaman mengikuti [05 — Sitemap dan Matriks Halaman](05-sitemap-matriks-halaman.md). Bagian terkait: [04 — Peran dan Hak Akses](04-peran-hak-akses.md), [06 — Alur](06-alur.md) (urutan interaksi), [12 — Aturan Bisnis, Validasi, Keamanan](12-aturan-bisnis-validasi-keamanan.md) (pesan galat), [13 — Data Contoh](13-data-contoh.md), dan [15 — Pengujian Penerimaan](15-pengujian-penerimaan.md).

---

## Cara membaca wireframe

| Simbol | Arti |
|---|---|
| `+---+`, tanda hubung, dan karakter pipa | Batas kotak, kartu, tabel, atau bingkai halaman. Bingkai terluar = jendela halaman. |
| `[ Teks ]` | Tombol. `[ Teks ]` yang diikuti keterangan “nonaktif” berarti tombol dinonaktifkan. |
| `[_____]` | Kolom isian; `[Semua v]` = pilihan (select); `[v]` / `[^]` = panah buka/tutup. |
| `( )` / `(o)` | Radio belum/sudah dipilih. `[ ]` / `[x]` = checkbox belum/sudah dicentang. |
| `<Teks>` | Tautan. `{foto}`, `{QR}`, `{cover}` = gambar atau placeholder gambar. |
| `(v)` `(x)` `(!)` `(i)` | Ikon status: berhasil/tersedia, penuh/gagal, peringatan/cuti, informasi. Pada UI nyata berupa ikon bootstrap-icons **disertai teks**. |
| `*` setelah label | Isian wajib. |
| `(h1)`, `(x-...)` di tepi kanan | Catatan implementasi (level judul atau komponen Blade); bukan teks yang tampil. |

Lebar bingkai desktop pada dokumen ini 80 kolom (admin 88 kolom) sehingga satu baris ASCII tidak mewakili piksel; gunakan catatan responsif untuk ukuran sesungguhnya. Tampilan ponsel digambar pada lebar 34 kolom (± 360 px).

Peta wireframe terhadap halaman pada bagian 05:

| ID | Wireframe | Halaman (05) | Route utama | Layout |
|---|---|---|---|---|
| W-01 | Kerangka global publik | Semua halaman publik 1–22 | — | `x-layouts.public` |
| W-02 | Beranda | 1 | `home` | `x-layouts.public` |
| W-03 | Daftar dokter | 6 | `doctors.index` | `x-layouts.public` |
| W-04 | Detail dokter | 7 | `doctors.show` | `x-layouts.public` |
| W-05 | Jadwal dokter | 8 | `schedules.index` | `x-layouts.public` |
| W-06 | Detail layanan dan poliklinik | 5 (dan 4) | `clinics.show` | `x-layouts.public` |
| W-07 | Kamar rawat inap: ringkasan dan detail kategori | 11 dan 12–15 | `rooms.index`, `rooms.show` | `x-layouts.public` |
| W-08 | Kontak dan lokasi | 22 | `contact`, `contact.store` | `x-layouts.public` |
| W-09 | Registrasi dan login pasien | 23, 24 (juga 25, 26) | `register`, `login`, `password.request` | `x-layouts.auth` |
| W-10 | Dashboard pasien | 27 | `patient.dashboard` | `x-layouts.patient` |
| W-11 | Wizard pendaftaran kunjungan | 29 (+ layar sukses) | `patient.appointments.create`, `.review`, `.store`, `.show` | `x-layouts.patient` |
| W-12 | Riwayat, detail, dan bukti pendaftaran | 30 (+ tambahan) | `patient.appointments.index`, `.show`, `.proof` | `x-layouts.patient` |
| W-13 | Kerangka admin | 31–40 | — | `x-layouts.admin` |
| W-14 | Dashboard admin | 32 | `admin.dashboard` | `x-layouts.admin` |
| W-15 | Pola `x-data-table` (daftar dokter) | 33 | `admin.doctors.index` | `x-layouts.admin` |
| W-16 | Form dokter | tambahan (induk 33) | `admin.doctors.create`, `.edit` | `x-layouts.admin` |
| W-17 | Jadwal admin | 35 (+ tambahan) | `admin.schedules.index`, `admin.sessions.index`, `admin.exceptions.index` | `x-layouts.admin` |
| W-18 | Sesi terdampak | tambahan (induk 35) | `admin.sessions.review` | `x-layouts.admin` |
| W-19 | Antrean hari ini | tambahan (induk 38) | `admin.queue.index` | `x-layouts.admin` |
| W-20 | Form walk-in | tambahan (induk 38) | `admin.appointments.create` | `x-layouts.admin` |
| W-21 | Pengaturan website dan hak akses | 40 (+ tambahan) | `admin.settings.edit`, `admin.roles.index`, `admin.roles.edit` | `x-layouts.admin` |

---

## Ringkasan design system

### 1. Token warna

Token mengikuti 00 bagian M (SCSS override Bootstrap 5). Nilai di bawah baris “bawaan Bootstrap” adalah **[ASUMSI]**: 00 tidak menetapkannya, sehingga dipakai nilai bawaan Bootstrap 5.3.

| Token | Nilai | Dipakai untuk | Kontras |
|---|---|---|---|
| `$primary` | `#0A4D8C` (biru) | Bar menu, sidebar admin, tombol utama, judul, tautan | vs putih ±8,6:1 (AA, bahkan AAA); vs `$light` ±7,9:1 |
| `$secondary-accent` (`$teal-dark`) | `#0E7C74` (toska tua) | Tombol sekunder/aksen, tautan aksen, badge, blok CTA | vs putih ±5,0:1 (AA); vs `$light` ±4,7:1 |
| `$teal-light` | `#13A89E` | **Hanya dekoratif**: garis aksen, ilustrasi, penanda item menu aktif | vs putih ±3,0:1 → **tidak dipakai untuk teks** maupun sebagai latar teks putih |
| `$success` | `#1E7D45` (hijau) | Status berhasil/tersedia, flash sukses | vs putih ±5,2:1 (AA) |
| `$body-bg` / `$light` | `#FFFFFF` / `#F3F7FB` | Latar halaman / latar bagian, kartu filter, kepala tabel | — |
| `$warning` (bawaan) | `#FFC107` | Menunggu konfirmasi, cuti, perlu tindak lanjut | Teks **hitam** di atasnya (>10:1) |
| `$info` (bawaan) | `#0DCAF0` | Hadir/check-in, libur, informasi | Teks **hitam** di atasnya (>7:1) |
| `$danger` (bawaan) | `#DC3545` | Penuh, tidak hadir, galat | Teks putih ±4,5:1 (AA) |
| `$secondary` (bawaan) | `#6C757D` | Dibatalkan, ditutup, nonaktif | Teks putih ±4,7:1 (AA) |
| `$body-color` (bawaan) | `#212529` | Teks isi | vs putih >15:1 |

Aturan pemakaian: teks isi memakai `$body-color`; tautan di dalam paragraf memakai `$primary` **bergaris bawah**; tidak ada informasi yang hanya dibedakan oleh warna (lihat pola status badge). Cincin fokus: `outline: 3px solid #0A4D8C; outline-offset: 2px` (putih pada latar `$primary`/gelap) **[ASUMSI]**.

### 2. Tipografi

Font **Plus Jakarta Sans** (400/500/600/700) dari `@fontsource/plus-jakarta-sans` (lokal, `font-display: swap`), fallback `system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif` (00 bagian M). Ukuran dasar 16 px, tinggi baris 1,6. Skala berikut adalah **[ASUMSI]**:

| Level | Desktop | Mobile (< 576 px) | Berat | Tinggi baris |
|---|---|---|---|---|
| Display (judul hero) | 2,5 rem | 1,875 rem | 700 | 1,2 |
| h1 (judul halaman) | 2 rem | 1,625 rem | 700 | 1,25 |
| h2 (judul bagian) | 1,5 rem | 1,375 rem | 600 | 1,3 |
| h3 (judul kartu) | 1,25 rem | 1,125 rem | 600 | 1,35 |
| Isi | 1 rem | 1 rem | 400 | 1,6 |
| Keterangan/label kecil | 0,875 rem | 0,875 rem | 400–500 | 1,5 |
| Isi tabel admin | 0,9375 rem | kartu: 1 rem | 400 | 1,5 |

Satu `<h1>` per halaman; tingkat judul tidak dilompati.

### 3. Spasi, bentuk, dan grid

| Aspek | Nilai |
|---|---|
| Skala spasi | Bootstrap `$spacer` = 1 rem (0,25 / 0,5 / 1 / 1,5 / 3 rem) |
| Jarak antarbagian halaman | `py-5` (desktop), `py-4` (mobile) |
| Jarak antarkartu | `g-3` (mobile), `g-4` (≥ lg) |
| Radius | 0,5 rem (kartu, tombol, input); badge `rounded-pill` |
| Bayangan | `shadow-sm` pada kartu dan header lengket |
| Container | `container` Bootstrap (maks 1140 px pada xl, 1320 px pada xxl), gutter 16 px pada mobile |
| Admin | Sidebar tetap 260 px (≥ lg); konten `container-fluid` dengan padding 24 px |
| Area sentuh | Minimal 44 × 44 px untuk tombol, tautan menu, dan kontrol tabel |

### 4. Breakpoint

| Rentang | Nama | Perilaku global |
|---|---|---|
| < 576 px | xs (ponsel) | 1 kolom; menu publik di offcanvas; tabel admin menjadi kartu |
| ≥ 576 px | sm | Kartu 2 kolom |
| ≥ 768 px | md (tablet) | Tabel admin kembali berbentuk tabel (kartu hanya < 768 px, 00 bagian M); footer 2 kolom |
| ≥ 992 px | lg | Menu publik terbuka penuh (2 tingkat); sidebar admin tetap; kartu 3 kolom |
| ≥ 1200 px | xl | Kartu dokter 4 kolom pada Beranda; container 1140 px |
| ≥ 1400 px | xxl | Container 1320 px |

### 5. Komponen Blade pada wireframe

| Komponen (00 bagian J.5) | Peran pada wireframe |
|---|---|
| `x-layouts.public`, `x-layouts.patient`, `x-layouts.admin`, `x-layouts.auth` | Kerangka halaman (W-01, W-13) |
| `x-breadcrumb`, `x-page-header` | Jejak halaman dan blok judul (h1 + subjudul) |
| `x-doctor-card` | Kartu dokter: foto, nama + gelar (tautan ke detail dokter), spesialisasi, poliklinik, dan bila `nextSession` ada: `x-availability-badge` + "Jadwal terdekat" + tombol Daftar (hanya bila route pendaftaran ada dan sesi dapat dipesan online). Tanpa tombol "Lihat Jadwal" terpisah karena tautan bersarang dilarang (W-02, W-03, W-06; F3-R25) |
| `x-schedule-table`, `x-availability-badge` | Tabel/grid jadwal dari `DoctorAvailabilityQuery` dan label status sesi (W-04, W-05, W-11) |
| `x-status-badge` | Badge status teks + ikon + warna (bagian 7 di bawah) |
| `x-empty-state`, `x-flash-alert`, `x-loading-button` | Kondisi kosong, pesan berhasil/gagal, tombol dengan status loading |
| `x-image` | Gambar dengan `alt` dan `data-fallback`; bila file fisik hilang, satu listener JavaScript terdelegasi (`resources/js/app.js`, kompatibel CSP; bukan `onerror` inline, 00 K) mengganti `src` ke placeholder lokal |
| `x-form.input`, `.select`, `.textarea`, `.checkbox`, `.radio-group`, `.file`, `.errors-summary` | Seluruh formulir (W-08, W-09, W-11, W-16, W-17, W-20, W-21) |
| `x-data-table` | Tabel admin/riwayat: pencarian, slot filter, pagination, kartu di mobile (W-12, W-15, W-19) |
| `x-confirm-modal` | Konfirmasi aksi merusak/mengubah status (W-12, W-15, W-18, W-19) |
| `x-stat-card` | Kartu angka dashboard (W-14) |
| `x-map-embed` | Peta tersemat + alamat teks + tombol Petunjuk Arah (W-08) |
| `x-wizard-steps` | Indikator langkah wizard pendaftaran (W-11); diterima di 00 J.5 |
| `x-module-tabs` | Tab modul admin dengan penyaringan izin (W-17 dan modul 34, 36, 38, 39, 40); diterima di 00 J.5 |

### 6. Ikon

Ikon memakai **bootstrap-icons** (font lokal dari npm; tidak memuat CDN): `<i class="bi bi-NAMA" aria-hidden="true"></i>`. Ikon yang membawa makna selalu disertai teks; tombol hanya-ikon wajib `aria-label` dan `title`. Ikon yang dipakai: `bi-telephone`, `bi-whatsapp` (tautan biasa, bukan integrasi), `bi-geo-alt`, `bi-search`, `bi-calendar-check`, `bi-calendar-x`, `bi-person-badge`, `bi-hospital`, `bi-info-circle`, `bi-exclamation-triangle`, `bi-check-circle`, `bi-x-circle`, `bi-slash-circle`, `bi-lock`, `bi-airplane`, `bi-arrow-repeat`, `bi-clock-history`, `bi-hourglass-split`, `bi-person-check`, `bi-person-x`, `bi-check2-all`. Ikon menu admin: lihat 05 bagian 3.5.

### 7. Pola status badge

`x-status-badge` dan `x-availability-badge` selalu menampilkan **ikon + teks + warna** (00 bagian D). Pemetaan `AppointmentStatus` berasal dari 00; pemetaan lainnya **[ASUMSI]**.

| Enum | Nilai → badge (warna, ikon, teks) |
|---|---|
| `AppointmentStatus` | `pending` → warning, `bi-hourglass-split`, “Menunggu konfirmasi” · `confirmed` → primary, `bi-check-circle`, “Dikonfirmasi” · `checked_in` → info, `bi-person-check`, “Hadir/check-in” · `completed` → success, `bi-check2-all`, “Selesai” · `cancelled` → secondary, `bi-x-circle`, “Dibatalkan” · `no_show` → danger, `bi-person-x`, “Tidak hadir” |
| `Availability` | `available` → success, `bi-check-circle`, “Tersedia” (varian kompak: “Sisa N”) · `full` → danger, `bi-slash-circle`, “Penuh” · `booking_closed` → secondary, `bi-lock`, “Pendaftaran online ditutup” · `closed` → secondary, `bi-lock`, “Ditutup” · `leave` → warning, `bi-airplane`, “Cuti” · `holiday` → info, `bi-calendar-x`, “Libur” · `cancelled` → secondary, `bi-x-circle`, “Dibatalkan” · `under_review` → warning, `bi-arrow-repeat`, “Sedang diperbarui” · `past` → light + garis tepi, `bi-clock-history`, “Sudah lewat” |
| `SessionStatus` | `open` → success “Dibuka” · `closed` → secondary “Ditutup” · `needs_review` → warning “Perlu tindak lanjut” · `cancelled` → secondary “Dibatalkan” |
| `DoctorStatus` | `active` → success “Aktif” · `inactive` → secondary “Nonaktif” · `archived` → dark outline “Diarsipkan” |
| `ArticleStatus` / `ContactMessageStatus` | `draft` → secondary “Draf”; `published` → success “Terbit” · `new` → primary “Baru”; `read` → secondary “Dibaca”; `replied` → success “Dibalas”; `archived` → dark outline “Diarsipkan” |
| Kamar (ringkasan) | `beds_available > 0` → success “Tersedia”; `beds_available = 0` → danger “Penuh” |

Teks pada badge `warning` dan `info` berwarna hitam (`text-bg-warning`, `text-bg-info`); pada badge lain putih.

Satu sesi selalu menampilkan **satu** label `Availability`, dipilih menurut prioritas 00 F.3 (kondisi pertama yang cocok): `past` → bila status `cancelled`: `leave` (pengecualian `doctor_leave`), `holiday` (`hospital_holiday`/`national_holiday`), selain itu `cancelled` → `under_review` (`needs_review`) → `closed` → `full` (`remaining = 0`) → `booking_closed` → `available`. Tombol **Daftar** hanya muncul bila `is_bookable_online` benar; petugas loket memakai `is_bookable_counter` (batas waktu `now < end_time`).

**Petunjuk unggah gambar** (`x-form.file`): setiap form unggah menyebut format (JPG/PNG/WebP; SVG ditolak), ukuran maksimum (2 MB; banner 4 MB), dan **dimensi minimum koleksi** (00 K; 17 D-11): `photo` 300×300, `cover` 800×450, `gallery`/`photos` 600×400, `image` (banner) 1200×400, `logo` 120×40, `editor` 200×100; dimensi maksimum 6000×6000 untuk semua. Teks alternatif (`alt_text`) wajib untuk koleksi publik.

### 8. Pola kondisi: loading, kosong, berhasil, gagal

| Kondisi | Pola | ARIA |
|---|---|---|
| Loading (kirim formulir) | `x-loading-button`: tombol dinonaktifkan, spinner kecil + teks “Memproses…”; formulir diberi `aria-busy="true"` | Tombol tetap berlabel; spinner `aria-hidden`, teks status ikut dibaca |
| Loading (data JSON, mis. grafik) | Kerangka `placeholder-glow` pada wadah | `aria-busy="true"` pada wadah; `role="status"` untuk teks “Memuat…” |
| Loading (gambar) | `loading="lazy"`, atribut `width`/`height` tetap (tanpa pergeseran tata letak), latar `$light` | `alt` tetap ada |
| Kosong | `x-empty-state`: ikon, judul, satu kalimat penjelas, aksi (mis. “Reset filter”, “Tambah …”) | Judul memakai heading tingkat yang sesuai |
| Berhasil | `x-flash-alert` hijau di atas konten setelah redirect | `role="status"` (`aria-live="polite"`) |
| Gagal | `x-flash-alert` merah (`role="alert"`) dan, untuk formulir, `x-form.errors-summary` di atas formulir + pesan per field (`invalid-feedback`) | Fokus dipindah ke ringkasan; tiap field `aria-invalid="true"` dan `aria-describedby` ke pesan |

### 9. Aksesibilitas dasar (berlaku pada semua wireframe)

- Skip link pertama; landmark `header`, `nav` (dengan `aria-label`), `main`, `footer`; satu `h1` per halaman.
- Fokus selalu terlihat; urutan fokus mengikuti urutan baca (kiri–kanan, atas–bawah); tidak ada perangkap fokus kecuali modal/offcanvas (perilaku bawaan Bootstrap, fokus kembali ke pemicu saat ditutup).
- Setiap input memiliki `<label>` terhubung (`for`/`id`); petunjuk dan galat dihubungkan lewat `aria-describedby`; tanda wajib berupa teks “wajib” atau `*` yang dijelaskan di awal formulir.
- Tabel memakai `<th scope="col">`/`scope="row">` dan `<caption>` (visually-hidden) bila perlu; pada < 768 px diubah menjadi kartu dengan label kolom ditulis ulang di dalam kartu.
- Gambar bermakna memakai `alt` (wajib pada unggahan publik, 00 bagian E.6); gambar dekoratif `alt=""`. Gambar yang file fisiknya hilang diganti placeholder oleh satu listener terdelegasi (`document.addEventListener('error', …, true)` di `resources/js/app.js`; `x-image` memberi atribut `data-fallback`), tanpa atribut inline `onerror` agar kompatibel dengan CSP ketat.
- Animasi (carousel, spinner) menghormati `prefers-reduced-motion`; carousel memiliki tombol jeda.
- Halaman tetap dapat dipakai pada perbesaran 200 % tanpa gulir horizontal untuk konten isi.

---

## W-01 Kerangka global publik

**Dipakai oleh:** seluruh halaman publik (1–22) beserta layar tambahan publik melalui `x-layouts.public`; header dan footer yang sama juga membungkus portal pasien (`x-layouts.patient`). Struktur menu dan isi footer secara lengkap ada pada [05 — bagian 3](05-sitemap-matriks-halaman.md#3-navigasi).

### Desktop (≥ 992 px)

```text
+------------------------------------------------------------------------------+
| <Lewati ke konten utama>   (skip link, tampil saat Tab pertama)              |
+------------------------------------------------------------------------------+
| (LOGO) UNPAM HOSPITAL                     IGD: (021) 0000-0119 (placeholder) |
|        tagline situs                        [ Daftar Kunjungan ]   [ Masuk ] |
+------------------------------------------------------------------------------+
| Tentang Kami v   Layanan   Dokter v   Fasilitas v   Informasi v   Kontak     |
|                                                     +---------------------+  |
|                                                     | BPJS & Asuransi     |  |
|                                                     | Panduan Pendaftaran |  |
|                                                     | Artikel             |  |
|                                                     | Galeri              |  |
|                                                     | FAQ                 |  |
|                                                     +---------------------+  |
+------------------------------------------------------------------------------+
| Beranda > Dokter > dr. Raka Wirawan, Sp.U    (breadcrumb: halaman internal)  |
| +--------------------------------------------------------------------------+ |
| |                                                                          | |
| | <main id="konten-utama">  isi halaman  </main>                           | |
| |                                                                          | |
| +--------------------------------------------------------------------------+ |
+------------------------------------------------------------------------------+
| UNPAM HOSPITAL          KONTAK                    TAUTAN CEPAT               |
| Universitas Pamulang    Telp : (021) 0000-0000    Cari Dokter                |
| Kampus 2 Viktor,        IGD  : (021) 0000-0119    Jadwal Dokter              |
| Jl. Raya Puspitek,      WA   : 6280000000000      Layanan                    |
| Buaran, Kecamatan       info@unpamhospital.test   Kamar Rawat Inap           |
| Pamulang, Kota          Sen-Sab 08.00-20.00       BPJS & Asuransi            |
| Tangerang Selatan,      (semua: placeholder)      Panduan Pendaftaran        |
| Banten 15310                                      Artikel   Galeri   FAQ     |
|                                                   Kontak                     |
+------------------------------------------------------------------------------+
| (c) 2026 UNPAM HOSPITAL      <Kebijakan Privasi>                             |
| Situs prototipe: nama dokter, foto, tarif, kontak, dan layanan adalah data   |
| contoh.                                                                      |
+------------------------------------------------------------------------------+
```

### Mobile (< 992 px): header ringkas dan offcanvas

```text
+--------------------------------+      +--------------------------------+
| [=] (LOGO) UNPAM     [ Masuk ] |      | Menu                       [X] |
+--------------------------------+      +--------------------------------+
| Beranda > Dokter > dr. Raka W. |      | Beranda                        |
+--------------------------------+      | Tentang Kami               [v] |
| +----------------------------+ |      |    Profil                      |
| |                            | |      |    Visi & Misi                 |
| | <main>  isi halaman        | |      | Layanan                        |
| |                            | |      | Dokter                     [v] |
| +----------------------------+ |      | Fasilitas                  [v] |
+--------------------------------+      | Informasi                  [v] |
| UNPAM HOSPITAL                 |      | Kontak                         |
| Universitas Pamulang Kampus 2  |      +--------------------------------+
| Viktor, Jl. Raya Puspitek,     |      | [   Daftar Kunjungan   ]       |
| Buaran, Pamulang, Tangerang    |      | [         Masuk        ]       |
| Selatan, Banten 15310          |      |                                |
|                                |      | IGD: (021) 0000-0119           |
| KONTAK                         |      | (placeholder)                  |
| Telp : (021) 0000-0000         |      +--------------------------------+
| IGD  : (021) 0000-0119         |
| WA   : 6280000000000           |
|                                |
| TAUTAN CEPAT                   |
| Cari Dokter  Jadwal Dokter     |
| Layanan  Kamar  BPJS  FAQ      |
+--------------------------------+
| (c) 2026 UNPAM HOSPITAL        |
| <Kebijakan Privasi>            |
| Situs prototipe: data contoh.  |
+--------------------------------+
```

**Elemen**

1. Skip link “Lewati ke konten utama” (`<a href="#konten-utama">`), tersembunyi sampai menerima fokus.
2. Baris atas: logo SVG placeholder (`public/images/placeholders/logo.svg`, atau media `site.logo_media_id`) + `site.name` + `site.tagline` (satu tautan ke `home`); nomor IGD dari `contact.emergency_phone` (tautan `tel:`, berlabel placeholder selama nilai seed); tombol utama **Daftar Kunjungan** (`patient.appointments.create`); tombol **Masuk** (`login`).
3. Bar menu latar `$primary`: Tentang Kami (dropdown: Profil, Visi & Misi) · Layanan · Dokter (dropdown: Daftar Dokter, Jadwal Dokter) · Fasilitas (dropdown: Fasilitas, Kamar Rawat Inap) · Informasi (dropdown: BPJS & Asuransi, Panduan Pendaftaran, Artikel, Galeri, FAQ) · Kontak.
4. Breadcrumb (`x-breadcrumb`) pada halaman internal (daftar lengkap di 05 bagian 8).
5. Area `<main id="konten-utama" tabindex="-1">`, didahului tumpukan `x-flash-alert` (pesan berhasil/gagal setelah redirect).
6. Footer: identitas + **alamat teks lengkap** (`contact.address`), kontak (telepon, IGD, WhatsApp, email, jam operasional; semua berlabel “placeholder/contoh” selama nilai seed), tautan cepat, bilah bawah dengan tautan **Kebijakan Privasi** dan catatan tetap “Situs prototipe: nama dokter, foto, tarif, kontak, dan layanan adalah data contoh”.
7. Mobile: hamburger membuka offcanvas dari kanan berisi menu bertingkat (accordion), dua tombol penuh-lebar, dan baris IGD.

**Perilaku interaksi**

- Dropdown: klik/Enter/Spasi pada tombol induk membuka; Esc atau klik di luar menutup; panah bawah berpindah antaritem (perilaku Bootstrap 5).
- Bar menu menempel (`sticky-top`) saat gulir; baris atas ikut menggulir **[ASUMSI]**.
- Item menu aktif ditandai tebal + garis bawah `$teal-light` dan `aria-current="page"`; induk dropdown dari halaman aktif ikut ditandai.
- Varian login: pasien melihat dropdown “Akun Saya” (Dashboard, Riwayat Pendaftaran, Profil dan Akun, Keluar) sebagai ganti “Masuk”; staf melihat “Buka Panel Admin” (lihat 05 bagian 3.1).
- Tombol WhatsApp pada footer/halaman lain hanyalah tautan `https://wa.me/{contact.whatsapp}` yang dibuka pengunjung; bukan pengiriman otomatis.

**Kondisi**

| Kondisi | Tampilan |
|---|---|
| Loading | Halaman dirender server; logo dimuat langsung (di atas lipatan), gambar lain `lazy` |
| Kosong | Nilai kontak kosong di pengaturan → baris itu disembunyikan; **alamat teks selalu tampil** |
| Berhasil | `x-flash-alert` hijau di atas `<main>` (mis. “Anda berhasil keluar”) |
| Gagal | `x-flash-alert` merah (mis. “Sesi Anda berakhir, silakan masuk kembali”); halaman galat 403/404/419/429/500/503 memakai kerangka yang sama |

**Responsif**

| Lebar | Perilaku |
|---|---|
| ≥ 992 px (lg) | Header dua tingkat (baris atas + bar menu); footer 3 kolom |
| 768–991 px | Header satu baris (hamburger + logo + “Masuk”); footer 2 kolom (identitas + kontak; tautan cepat) |
| < 768 px | Header satu baris; footer 1 kolom berurutan: identitas, kontak, tautan cepat, bilah bawah |

**Aksesibilitas**

- Urutan fokus: skip link → logo → telepon IGD → Daftar Kunjungan → Masuk → item menu (kiri ke kanan) → konten → footer.
- `<header>`, `<nav aria-label="Menu utama">`, `<main>`, `<footer>`; tombol induk dropdown memakai `aria-expanded` dan `aria-controls`; offcanvas memakai `aria-labelledby` pada judul “Menu”; tombol hamburger `aria-label="Buka menu"`.
- Logo `alt="UNPAM HOSPITAL"` (bila menjadi tautan, nama tautan “UNPAM HOSPITAL, ke beranda”); ikon telepon/WhatsApp dekoratif (`aria-hidden`) dengan teks yang tetap terbaca.
- Kontras: teks putih pada `$primary` ±8,6:1; teks pada tombol aksen putih di atas `$secondary-accent` ±5,0:1; penanda aktif `$teal-light` hanya dekoratif, penanda utama adalah teks tebal dan `aria-current`; cincin fokus putih pada bar biru.
- Area sentuh item menu dan tombol offcanvas ≥ 44 px.

---

## W-02 Beranda

**Halaman 1** (`home`). Sumber: `banners`, `pages` (`home`), `clinics`, `doctors` + `DoctorAvailabilityQuery`, `room_categories`, `articles`, `website_settings` (lihat 05 baris 1).

### Desktop

```text
+------------------------------------------------------------------------------+
| (header global, lihat W-01)                                                  |
+------------------------------------------------------------------------------+
| +- HERO: BANNER CAROUSEL (x-image) ----------------------------------------+ |
| |                                                                          | |
| |   Layanan kesehatan yang ramah dan terpercaya   (banners.title, contoh)  | |
| |   Subjudul banner - dapat diganti admin             (banners.subtitle)   | |
| |                                                                          | |
| |   [ Cari Dokter ]    [ Jadwal Dokter ]    [ Daftar Kunjungan ]           | |
| |                                                                          | |
| |   ( o o o )  indikator slide                  [ Jeda ]      [<]  [>]     | |
| +--------------------------------------------------------------------------+ |
| +--------------------------------------------------------------------------+ |
| | Cari dokter cepat                                       (GET /dokter)    | |
| | [Nama dokter___] [Spesialisasi v] [Poliklinik v] [Tanggal____] [ Cari ]  | |
| +--------------------------------------------------------------------------+ |
+------------------------------------------------------------------------------+
| LAYANAN UNGGULAN  (6 kartu; 3 kolom pada lg)           <Lihat semua layanan> |
| +----------------------+  +----------------------+  +----------------------+ |
| | {ikon / foto}        |  | {ikon / foto}        |  | {ikon / foto}        | |
| | Poli Anak            |  | Poli Penyakit Dalam  |  | Poli Urologi         | |
| | Gedung A Lt. 1       |  | Gedung A Lt. 1       |  | Gedung A Lt. 2       | |
| | <Selengkapnya>       |  | <Selengkapnya>       |  | <Selengkapnya>       | |
| +----------------------+  +----------------------+  +----------------------+ |
+------------------------------------------------------------------------------+
| DOKTER KAMI  (x-doctor-card; 4 kolom pada xl)           <Lihat semua dokter> |
| +----------------------+  +----------------------+  +----------------------+ |
| | +------------------+ |  | +------------------+ |  | +------------------+ | |
| | | {foto dokter}    | |  | | {foto dokter}    | |  | | {foto dokter}    | | |
| | |                  | |  | |                  | |  | |                  | | |
| | +------------------+ |  | +------------------+ |  | +------------------+ | |
| | dr. Raka Wirawan,    |  | dr. Sinta Maharani,  |  | dr. Anita Lestari,   | |
| | Sp.U                 |  | Sp.PD                |  | Sp.A                 | |
| | Urologi              |  | Penyakit Dalam       |  | Anak                 | |
| | Poli Urologi         |  | Poli Penyakit Dalam  |  | Poli Anak            | |
| | Tersedia - 6 Okt     |  | Penuh - 6 Okt        |  | Cuti - 7 Okt         | |
| +----------------------+  +----------------------+  +----------------------+ |
+------------------------------------------------------------------------------+
| KETERSEDIAAN KAMAR RAWAT INAP                           <Lihat detail kamar> |
| +---------------+  +---------------+  +---------------+  +---------------+   |
| | BPJS Kelas 1  |  | BPJS Kelas 2  |  | BPJS Kelas 3  |  | VIP           |   |
| | 4 dari 12     |  | 7 dari 16     |  | 0 dari 24     |  | 2 dari 6      |   |
| | [Tersedia]    |  | [Tersedia]    |  | [Tersedia]    |  | [Tersedia]    |   |
| +---------------+  +---------------+  +---------------+  +---------------+   |
| Diperbarui: 02 Okt 2026 09.30 WIB - diperbarui manual oleh petugas,          |
| bukan data real-time. Hubungi rumah sakit untuk memastikan.                  |
+------------------------------------------------------------------------------+
| ARTIKEL TERBARU                                        <Lihat semua artikel> |
| +----------------------+  +----------------------+  +----------------------+ |
| | {cover}              |  | {cover}              |  | {cover}              | |
| | Kesehatan, 1 Okt 2026|  | Gizi - 28 Sep 2026   |  | Anak - 25 Sep 2026   | |
| | Judul artikel contoh |  | Judul artikel 2      |  | Judul artikel 3      | |
| | <Baca selengkapnya>  |  | <Baca selengkapnya>  |  | <Baca selengkapnya>  | |
| +----------------------+  +----------------------+  +----------------------+ |
+------------------------------------------------------------------------------+
| +- CTA KONTAK / IGD (latar $secondary-accent) -----------------------------+ |
| | Butuh bantuan segera?   IGD: (021) 0000-0119 (placeholder)               | |
| | [ WhatsApp ]   [ Telepon ]   [ Petunjuk Arah ]   [ Kirim Pesan ]         | |
| +--------------------------------------------------------------------------+ |
+------------------------------------------------------------------------------+
| (footer global, lihat W-01)                                                  |
+------------------------------------------------------------------------------+
```

### Mobile

```text
+--------------------------------+      +--------------------------------+
| [=] (LOGO) UNPAM     [ Masuk ] |      | DOKTER KAMI   (geser >>)       |
+--------------------------------+      | +----------------------------+ |
| +- HERO ---------------------+ |      | | {foto dokter}              | |
| |                            | |      | | dr. Raka Wirawan,          | |
| | Layanan kesehatan yang     | |      | | Sp.U - Urologi             | |
| | ramah dan terpercaya       | |      | | Tersedia - 6 Okt           | |
| |                            | |      | +----------------------------+ |
| | ( o o o )  [Jeda] [<] [>]  | |      | o o o   (scroll-snap)          |
| +----------------------------+ |      +--------------------------------+
| [ Cari Dokter           ]      |      | KAMAR (diperbarui manual)      |
| [ Jadwal Dokter         ]      |      | +-----------+  +-----------+   |
| [ Daftar Kunjungan      ]      |      | | BPJS 1    |  | BPJS 2    |   |
+--------------------------------+      | | 4 dari 12 |  | 7 dari 16 |   |
| Cari dokter cepat  [v]         |      | +-----------+  +-----------+   |
| (accordion, tertutup)          |      | +-----------+  +-----------+   |
+--------------------------------+      | | BPJS 3    |  | VIP       |   |
| LAYANAN UNGGULAN               |      | | 0 dari 24 |  | 2 dari 6  |   |
| [Poli Anak           ]         |      | +-----------+  +-----------+   |
| [Poli Penyakit Dalam ]         |      | Diperbarui: 02 Okt 2026 09.30  |
| [Poli Urologi        ]         |      +--------------------------------+
| <Lihat semua layanan>          |      | ARTIKEL TERBARU (1 kolom)      |
+--------------------------------+      | +----------------------------+ |
                                        | | {cover}                    | |
                                        | | Judul artikel contoh       | |
                                        | +----------------------------+ |
                                        +--------------------------------+
                                        | IGD: (021) 0000-0119           |
                                        | [ WhatsApp ]  [ Telepon ]      |
                                        +--------------------------------+
```

**Elemen**

1. **Hero/banner carousel** (`x-image`): judul, subjudul, tombol opsional dari `banners` (`button_label`, `button_url`: path internal atau URL https), indikator slide, panah sebelumnya/berikutnya, tombol jeda/putar. `<h1>` tetap berasal dari `pages.home.title` (tidak ikut berganti saat slide berpindah); judul slide memakai elemen non-heading bergaya h2 **[ASUMSI]**.
2. **Tiga tombol utama**: **Cari Dokter** → `doctors.index`; **Jadwal Dokter** → `schedules.index`; **Daftar Kunjungan** → `patient.appointments.create` (tamu diarahkan ke `login`, lalu kembali); tombol ini hanya tampil bila route-nya ada (fase 6, F4A-R15). Tiga tombol ditempatkan tepat di bawah `<h1>` dan subjudul, sebelum carousel, agar terlihat tanpa gulir pada 360 px (15 RSP-01; F4A-R34).
3. **Kotak pencarian dokter cepat** (`x-form.input`, `x-form.select`): nama, spesialisasi, poliklinik, tanggal praktik; kirim GET ke `doctors.index`.
4. **Layanan unggulan**: enam kartu poliklinik aktif (urut `sort_order`) berisi gambar `cover`/ikon, nama, lokasi, tautan ke `clinics.show`; tautan “Lihat semua layanan”.
5. **Dokter**: empat kartu `x-doctor-card` pertama menurut `sort_order` (foto, nama + gelar sebagai satu-satunya tautan ke `doctors.show`, spesialisasi, poliklinik, dan bila ada lencana ketersediaan, “Jadwal terdekat”, serta tombol Daftar hanya bila route pendaftaran ada); tanpa tombol “Lihat Jadwal” terpisah karena tautan bersarang dilarang (F3-R25, F4A-R16); tautan “Lihat semua dokter”.
6. **Ringkasan ketersediaan kamar** (ditunda ke fase 4b, F4A-R1): satu kartu kecil per kategori aktif (nama, “N dari M”, badge Tersedia/Penuh) + kalimat **“Diperbarui: {tanggal-jam}”** dan penjelasan bahwa data diperbarui manual dan bukan real-time (`availability_updated_at` paling lama dari kategori; bila kosong “Belum diperbarui”).
7. **Artikel terbaru**: tiga artikel terbit (cover, kategori, tanggal, judul, tautan).
8. **CTA kontak/IGD**: blok latar `$secondary-accent` dengan nomor IGD (placeholder), tombol WhatsApp (tautan biasa), Telepon, Petunjuk Arah (`map.directions_url`), Kirim Pesan (`contact`; hanya bila route ada, fase 4b, F4A-R15). Blok disembunyikan bila semua isiannya kosong.

**Perilaku interaksi**

- Carousel berganti otomatis tiap 6 detik **[ASUMSI]**; berhenti saat kursor/fokus berada di atasnya, saat tombol jeda ditekan, dan **tidak berjalan sama sekali** bila `prefers-reduced-motion: reduce`. Panah dan indikator dapat dioperasikan dengan keyboard.
- Pencarian cepat bekerja tanpa JavaScript (formulir GET). Kolom tanggal membatasi `min` = hari ini dan `max` = hari ini + `booking.window_days`.
- Kartu kamar tidak menjanjikan ketersediaan; tombol “Lihat detail kamar” membuka `rooms.index`.

**Kondisi**

| Kondisi | Tampilan |
|---|---|
| Loading | Gambar banner dan kartu `lazy` dengan rasio tetap dan latar `$light`; submit pencarian → tombol “Cari” menampilkan spinner |
| Kosong | Tanpa banner aktif → hero statis (`pages.home.title` + `site.tagline`) dengan tiga tombol utama tetap tampil; tanpa dokter/layanan/artikel → bagian terkait disembunyikan (bukan kotak kosong); tanpa kategori kamar aktif → bagian kamar disembunyikan |
| Berhasil | Pesan flash dari tindakan sebelumnya (mis. “Anda telah keluar”) |
| Gagal | Gambar hilang → placeholder lokal (`banner.svg`, `doctor.svg`, `clinic.svg`, `article.svg`) lewat `x-image`; galat server → halaman 500 |

**Responsif**

| Lebar | Perilaku |
|---|---|
| ≥ 1200 px | Hero rasio ±3:1 dengan teks di kiri; dokter 4 kolom; layanan 3 kolom |
| 992–1199 px | Dokter 3 kolom; layanan 3 kolom |
| 576–991 px | Dokter dan layanan 2 kolom; kamar 2 × 2 |
| < 576 px | Hero ±16:9; tiga tombol utama bertumpuk penuh-lebar; pencarian cepat berupa accordion tertutup; dokter berupa baris geser (scroll-snap) 1 kartu terlihat + sebagian kartu berikutnya; kamar 2 × 2; artikel 1 kolom |

**Aksesibilitas**

- Urutan fokus: header → tiga tombol utama → hero (indikator, jeda, panah, tombol slide; F4A-R34) → pencarian cepat → layanan → dokter → kamar → artikel → CTA → footer.
- Carousel: `role="region" aria-roledescription="carousel" aria-label="Banner"`; tiap slide `role="group" aria-roledescription="slide" aria-label="1 dari 3"`; indikator `aria-label="Slide 1"`; kemajuan otomatis memakai `aria-live="off"` dan beralih `polite` saat jeda.
- Teks di atas gambar hero ditempatkan pada **panel solid** `rgba(10,77,140,.85)` (teks putih ±8:1) agar kontras terjaga apa pun gambarnya; `alt` gambar diambil dari `media.alt_text` (wajib saat unggah).
- Setiap field pencarian punya `<label>` (boleh visually-hidden di mobile); tombol utama berupa tautan dengan teks jelas; badge kamar membawa teks “Tersedia/Penuh”, bukan warna saja.

---

## W-03 Daftar dokter

**Halaman 6** (`doctors.index`). Filter via query `q`, `spesialisasi`, `poli`, `tanggal`.

### Desktop

```text
+------------------------------------------------------------------------------+
| Beranda > Dokter                                                             |
| DAFTAR DOKTER                                   (h1, pages.doctors-index)    |
| Temukan dokter berdasarkan nama, spesialisasi, poliklinik, atau tanggal.     |
| +- FILTER (form GET, tanpa JavaScript tetap berfungsi) --------------------+ |
| | Nama dokter         Spesialisasi       Poliklinik        Tanggal praktik | |
| | [_______________]   [Semua       v]    [Semua      v]    [__/__/____]    | |
| |                                                                          | |
| |                                      [ Cari Dokter ]   [ Reset filter ]  | |
| +--------------------------------------------------------------------------+ |
| Menampilkan 12 dari 15 dokter                          (aria-live="polite")  |
| +----------------------+  +----------------------+  +----------------------+ |
| | +------------------+ |  | +------------------+ |  | +------------------+ | |
| | | {foto dokter}    | |  | | {foto dokter}    | |  | | {foto dokter}    | | |
| | |                  | |  | |                  | |  | |                  | | |
| | +------------------+ |  | +------------------+ |  | +------------------+ | |
| | dr. Raka Wirawan,    |  | dr. Sinta Maharani,  |  | drg. Bima Santoso    | |
| | Sp.U                 |  | Sp.PD                |  |                      | |
| | Urologi              |  | Penyakit Dalam       |  | Dokter Gigi          | |
| | Poli Urologi         |  | Poli Penyakit Dalam  |  | Poli Gigi            | |
| | Tersedia - 6 Okt     |  | Penuh - 6 Okt        |  | Tersedia - 7 Okt     | |
| +----------------------+  +----------------------+  +----------------------+ |
| (baris berikutnya: kartu 4-6 dst., 12 kartu per halaman)                     |
+------------------------------------------------------------------------------+
| Halaman 1 dari 2                                          [<]  [1]  [2]  [>] |
+------------------------------------------------------------------------------+
```

Kondisi kosong:

```text
+- x-empty-state (hasil kosong) -----------------------------+
|                                                            |
|                      (ikon bi-search)                      |
|                   Dokter tidak ditemukan                   |
|      Coba ubah kata kunci atau pilihan filter Anda.        |
|                     [ Reset filter ]                       |
|                                                            |
+------------------------------------------------------------+
```

### Mobile (filter terlipat dan terbuka)

```text
+--------------------------------+      +--------------------------------+
| [=] (LOGO) UNPAM     [ Masuk ] |      | [=] (LOGO) UNPAM     [ Masuk ] |
+--------------------------------+      +--------------------------------+
| Beranda > Dokter               |      | Daftar Dokter                  |
| Daftar Dokter                  |      | [ Filter (2 aktif)         ^ ] |
| [ Filter (2 aktif)         v ] |      | Nama dokter                    |
| 12 dari 15 dokter              |      | [______________________]       |
| +----------------------------+ |      | Spesialisasi                   |
| | {foto}  dr. Raka Wirawan,  | |      | [Semua               v]        |
| |         Sp.U               | |      | Poliklinik                     |
| |         Urologi            | |      | [Semua               v]        |
| |         Tersedia - 6 Okt   | |      | Tanggal praktik                |
| +----------------------------+ |      | [__/__/____]                   |
| +----------------------------+ |      | [   Cari Dokter    ]           |
| | {foto}  dr. Sinta Maharani,| |      | [   Reset filter   ]           |
| |         Sp.PD              | |      +--------------------------------+
| |         Penyakit Dalam     | |
| |         Penuh - 6 Okt      | |
| +----------------------------+ |
| [<]  [1] [2]  [>]              |
+--------------------------------+
```

**Elemen**

1. Breadcrumb dan `x-page-header` (fase 3: judul statis "Cari Dokter" dan subjudul "Cari berdasarkan nama, spesialisasi, poliklinik, atau tanggal praktik" sampai model `Page` ada di fase 4a, F3-R18).
2. Formulir filter (GET): **nama dokter** (`q`), **spesialisasi** (`spesialisasi`; pilihan dari `specialties` aktif), **poliklinik** (`poli`; dari `clinics` aktif berjenis poliklinik), **tanggal praktik** (`tanggal`); tombol **Cari Dokter** dan **Reset filter**. Nilai parameter mengikuti 00 I.5: `spesialisasi` dan `poli` berupa slug (`specialties.slug`, `clinics.slug`), `tanggal` berupa `YYYY-MM-DD`; parameter tidak valid diabaikan.
3. Teks hasil “Menampilkan N dari M dokter” (`aria-live="polite"`).
4. Grid `x-doctor-card`: foto (`x-image`, rasio 4:5), nama + gelar (`{title_prefix} {name}, {title_suffix}`), spesialisasi (badge; primer lebih dulu), poliklinik; nama dokter adalah tautan `stretched-link` ke `doctors.show` dan kartu **tidak** memiliki tombol "Lihat Jadwal" terpisah (tautan bersarang dilarang, F3-R25). Kartu menampilkan `x-availability-badge` dan "Jadwal terdekat: {Hari, j F Y} · {HH.MM–HH.MM}": bila `tanggal` terisi untuk sesi dokter pada tanggal itu (termasuk Cuti/Libur), selain itu untuk sesi terdekat yang bukan pembatalan; tombol **Daftar** hanya bila route pendaftaran ada (fase 6) dan sesi dapat dipesan online.
5. Pagination Bootstrap 5 (12 kartu per halaman) **[ASUMSI]**.

**Perilaku interaksi**

- Semua filter bersifat GET sehingga URL dapat dibagikan; menekan **Cari Dokter** memuat ulang daftar dari halaman 1; **Reset filter** menuju `doctors.index` tanpa parameter.
- Dengan `tanggal`: daftar hanya memuat dokter yang memiliki sesi pada tanggal itu, termasuk sesi Cuti/Libur yang tampil berlabel agar pengunjung tahu alasannya (sumber `DoctorAvailabilityQuery`); tanggal berformat salah atau di luar jendela (hari ini sampai hari ini + `booking.window_days`, kedua ujung termasuk) memunculkan pesan validasi inline dan daftar tidak difilter tanggal.
- Hanya dokter berstatus `active` yang tampil.

**Kondisi**

| Kondisi | Tampilan |
|---|---|
| Loading | Tombol **Cari Dokter** berubah menjadi spinner + “Mencari…” selama permintaan; foto `lazy` |
| Kosong | `x-empty-state` “Dokter tidak ditemukan” + saran + tombol **Reset filter** |
| Berhasil | Hasil tampil beserta jumlahnya; filter aktif dirangkum pada tombol “Filter (n aktif)” di mobile |
| Gagal | Tanggal tidak valid atau slug tidak dikenal → filter itu diabaikan tanpa galat 500, dengan pesan di bawah field terkait (`invalid-feedback`) bila relevan; foto hilang → `doctor.svg` |

**Responsif**

| Lebar | Perilaku |
|---|---|
| ≥ 1200 px | Filter satu baris; kartu 4 kolom |
| 992–1199 px | Filter dua baris; kartu 3 kolom |
| 576–991 px | Filter dua kolom; kartu 2 kolom |
| < 576 px | Filter berada di accordion “Filter (n aktif)” dengan field bertumpuk dan tombol penuh-lebar; kartu 1 kolom horizontal (foto kiri, teks kanan) |

**Aksesibilitas**

- Urutan fokus: breadcrumb → filter (nama → spesialisasi → poliklinik → tanggal → Cari → Reset) → kartu (tautan nama dokter, lalu tombol Daftar bila ada) → pagination.
- Kartu tidak memiliki tombol “Lihat Jadwal” terpisah (nama dokter adalah satu-satunya tautan ke detail); tombol Daftar (bila ada) dilengkapi `aria-label="Daftar ke dr. Raka Wirawan, Sp.U, {hari, tanggal, jam}"` agar unik; foto `alt="Foto dr. Raka Wirawan, Sp.U"` (atau `alt` kustom dari admin); pagination `<nav aria-label="Navigasi halaman">` dengan `aria-current="page"`.
- Kontras: teks gelap pada kartu putih; badge spesialisasi teks putih pada `$secondary-accent` (±5,0:1); tombol Daftar putih pada `$primary`.

---

## W-04 Detail dokter

**Halaman 7** (`doctors.show`). Jadwal berasal dari `DoctorAvailabilityQuery`, sumber yang sama dengan wizard pendaftaran.

### Desktop

```text
+------------------------------------------------------------------------------+
| Beranda > Dokter > dr. Raka Wirawan, Sp.U                                    |
+------------------------------------------------------------------------------+
| +------------------------+  dr. Raka Wirawan, Sp.U                    (h1)   |
| | +--------------------+ |  [Urologi - utama]  [Bedah Umum]                  |
| | | {foto dokter 4:5}  | |  Poliklinik : Poli Urologi, Gedung A Lt. 1        |
| | |                    | |  <Lihat layanan Poli Urologi>                     |
| | |                    | |                                                   |
| | |                    | |  Profil singkat                                   |
| | |                    | |  Dokter spesialis urologi dengan minat pada       |
| | |                    | |  penanganan batu saluran kemih (data contoh).     |
| | |                    | |                                                   |
| | |                    | |  Biodata                                          |
| | +--------------------+ |  Teks biodata lengkap (bio, teks biasa) tampil    |
| | alt: Foto dr. Raka     |  di sini; paragraf dipisah baris kosong.          |
| | Wirawan, Sp.U          |                                                   |
| +------------------------+                                                   |
+------------------------------------------------------------------------------+
| JADWAL PRAKTIK 14 HARI KE DEPAN      (x-schedule-table)                      |
| +-------------+-------------+------------+--------------------+-----------+  |
| | Tanggal     | Jam         | Sisa       | Status             | Aksi      |  |
| +-------------+-------------+------------+--------------------+-----------+  |
| | Jum, 2 Okt  | 13.00-15.00 | 6 dari 20  | (i) Pendaftaran    | -         |  |
| |             |             |            |     online ditutup |           |  |
| +-------------+-------------+------------+--------------------+-----------+  |
| | Sen, 5 Okt  | 09.00-12.00 | 5 dari 20  | (v) Tersedia       | [Daftar]  |  |
| +-------------+-------------+------------+--------------------+-----------+  |
| | Rab, 7 Okt  | 09.00-12.00 | -          | (!) Cuti           | -         |  |
| +-------------+-------------+------------+--------------------+-----------+  |
| | Jum, 9 Okt  | 09.00-12.00 | 0 dari 20  | (x) Penuh          | -         |  |
| +-------------+-------------+------------+--------------------+-----------+  |
| | Sen, 12 Okt | 09.00-12.00 | 12 dari 20 | (v) Tersedia       | [Daftar]  |  |
| +-------------+-------------+------------+--------------------+-----------+  |
| Legenda: (v) Tersedia (x) Penuh (!) Cuti/Libur (i) Pendaftaran ditutup       |
| Jadwal dapat berubah. Sumber data sama dengan formulir pendaftaran.          |
| [ Lihat jadwal mingguan semua dokter ]                                       |
+------------------------------------------------------------------------------+
```

### Mobile

```text
+--------------------------------+
| Beranda > Dokter > dr. Raka W. |
+--------------------------------+
| +----------------------------+ |
| | {foto dokter}              | |
| |                            | |
| +----------------------------+ |
| dr. Raka Wirawan, Sp.U         |
| [Urologi]  [Bedah Umum]        |
| Poli Urologi, Gedung A Lt. 1   |
| Profil singkat ... (teks)      |
+--------------------------------+
| JADWAL 14 HARI                 |
| +----------------------------+ |
| | Sen, 5 Okt  09.00-12.00    | |
| | Sisa 5 dari 20             | |
| | (v) Tersedia               | |
| | [ Daftar ]                 | |
| +----------------------------+ |
| +----------------------------+ |
| | Rab, 7 Okt  09.00-12.00    | |
| | (!) Cuti                   | |
| | (tidak dapat dipesan)      | |
| +----------------------------+ |
| +----------------------------+ |
| | Jum, 9 Okt  09.00-12.00    | |
| | 0 dari 20                  | |
| | (x) Penuh                  | |
| +----------------------------+ |
+--------------------------------+
```

**Elemen**

1. Breadcrumb: Beranda › Dokter › {nama tampilan}.
2. Foto besar (`x-image`, rasio 4:5) dengan `alt`.
3. Identitas: `<h1>` nama tampilan; badge spesialisasi (primer ditandai); poliklinik utama (tautan ke `clinics.show`) beserta lokasi.
4. **Profil singkat** (`short_bio`) dan **biodata** (`bio`, teks biasa; paragraf dipisah baris kosong, di-escape).
5. **Tabel jadwal 14 hari ke depan** (`x-schedule-table`, id `jadwal`): tanggal + hari, jam, “Sisa N dari M”, `x-availability-badge` (teks + ikon + warna), tombol **Daftar** hanya untuk sesi yang dapat dipesan online (`patient.appointments.create?sesi={id}`).
6. Legenda status dan catatan “Jadwal dapat berubah; sumber data sama dengan formulir pendaftaran”.
7. Tautan ke jadwal mingguan semua dokter (`schedules.index`).

**Perilaku interaksi**

- **Daftar**: tamu diarahkan ke `login` lalu kembali ke formulir pendaftaran dengan sesi tersebut (*intended URL*); pasien langsung ke wizard langkah 4 (poliklinik dan dokter diturunkan dari sesi).
- Sesi `leave`, `holiday`, `full`, `booking_closed`, `under_review`, `closed`, `cancelled`: hanya menampilkan teks status, tanpa tombol Daftar. Sesi hari ini yang sudah melewati batas `booking.online_cutoff_minutes` tampil “Pendaftaran online ditutup”.
- Pemanggilan halaman memicu `PracticeSessionGenerator::ensureDailyHorizon()` (maksimal sekali per hari).

**Kondisi**

| Kondisi | Tampilan |
|---|---|
| Loading | Foto `lazy` berukuran tetap; tombol Daftar tidak butuh JavaScript |
| Kosong | Tidak ada sesi 14 hari → `x-empty-state` “Belum ada jadwal praktik dua minggu ke depan” + tautan ke `contact` |
| Berhasil | Setelah kembali dari pendaftaran: flash hijau di halaman tujuan (portal), bukan di sini |
| Gagal | Slug tidak ada atau dokter `inactive`/`archived` → 404; foto hilang → `doctor.svg`; sesi yang baru penuh saat diklik → wizard menampilkan pesan `QUOTA_FULL` |

**Responsif**

| Lebar | Perilaku |
|---|---|
| ≥ 992 px | Foto di kolom kiri (4/12), isi di kanan (8/12); jadwal berupa tabel |
| 768–991 px | Foto dan identitas berdampingan lalu jadwal tabel penuh-lebar |
| < 768 px | Foto di atas identitas; jadwal berubah menjadi daftar kartu per hari (label kolom ditulis di dalam kartu) |

**Aksesibilitas**

- Urutan fokus: breadcrumb → tautan poliklinik → tabel jadwal (tombol Daftar berurutan) → tautan jadwal mingguan.
- Tabel memakai `<th scope="col">`; baris memakai `<time datetime="2026-10-05">`; tombol Daftar `aria-label="Daftar ke dr. Raka Wirawan, Senin 5 Oktober 2026, 09.00 sampai 12.00"`.
- Status selalu teks + ikon + warna (contoh “Penuh”, “Cuti”), kontras badge sesuai pola bagian 7; `alt` foto = “Foto dr. Raka Wirawan, Sp.U”.

---

## W-05 Jadwal dokter

**Halaman 8** (`schedules.index`). Filter via query `minggu`, `poli`, `dokter`.

### Desktop (grid mingguan)

```text
+------------------------------------------------------------------------------+
| Beranda > Jadwal Dokter                                                      |
| JADWAL DOKTER                                    (h1, pages.schedules-index) |
| +- FILTER MINGGU / POLIKLINIK / DOKTER ------------------------------------+ |
| | [<] Minggu 5 - 11 Okt 2026 [>]                   Poliklinik [Semua    v] | |
| | Dokter [Semua              v]                    [ Terapkan ]  [ Reset ] | |
| +--------------------------------------------------------------------------+ |
| +-----------+--------+--------+--------+--------+--------+--------+--------+ |
| |Dokter     | Sen 5  | Sel 6  | Rab 7  | Kam 8  | Jum 9  | Sab 10 | Min 11 | |
| +-----------+--------+--------+--------+--------+--------+--------+--------+ |
| |dr. Raka   | 09-12  | -      | 09-12  | 09-12  | 09-12  | -      | -      | |
| |Urologi    | Sisa 5 |        | Cuti   | Libur  | Penuh  |        |        | |
| +-----------+--------+--------+--------+--------+--------+--------+--------+ |
| |dr. Sinta  | 10-14  | 10-14  | 10-14  | 10-14  | 10-14  | -      | -      | |
| |Peny. Dalam| Sisa 12| Sisa 8 | Sisa 3 | Libur  | Sisa 10|        |        | |
| +-----------+--------+--------+--------+--------+--------+--------+--------+ |
| |dr. Anita  | -      | 13-16  | 13-16  | 13-16  | -      | 08-11  | -      | |
| |Anak       |        | Sisa 7 | Sisa 4 | Libur  |        | Ditutup|        | |
| +-----------+--------+--------+--------+--------+--------+--------+--------+ |
| Sel berisi jam praktik dan status; sel Sisa N berisi tautan Daftar.          |
| +--------------------------------------------------------------------------+ |
| | Legenda (teks, warna, ikon): Sisa N = Tersedia (hijau); Penuh (merah);   | |
| | Cuti (kuning); Libur (biru muda); Ditutup, Pend. online ditutup (abu);   | |
| | Sedang diperbarui (kuning); Dibatalkan (abu); Sudah lewat (abu muda).    | |
| +--------------------------------------------------------------------------+ |
+------------------------------------------------------------------------------+
```

### Mobile (bertumpuk per hari)

```text
+--------------------------------+
| Jadwal Dokter                  |
| [<] 5 - 11 Okt 2026 [>]        |
| [ Filter (poli, dokter)   v ]  |
+--------------------------------+
| SENIN, 5 OKT 2026              |
| +----------------------------+ |
| | dr. Raka W. - Urologi      | |
| | 09.00-12.00  Sisa 5        | |
| | (v) Tersedia  [ Daftar ]   | |
| +----------------------------+ |
| +----------------------------+ |
| | dr. Sinta M. - Peny. Dalam | |
| | 10.00-14.00  Sisa 12       | |
| | (v) Tersedia  [ Daftar ]   | |
| +----------------------------+ |
+--------------------------------+
| RABU, 7 OKT 2026               |
| +----------------------------+ |
| | dr. Raka W. - Urologi      | |
| | 09.00-12.00                | |
| | (!) Cuti                   | |
| +----------------------------+ |
| +----------------------------+ |
| | dr. Sinta M. - Peny. Dalam | |
| | 10.00-14.00  Sisa 3        | |
| | (v) Tersedia  [ Daftar ]   | |
| +----------------------------+ |
+--------------------------------+
| KAMIS, 8 OKT 2026              |
| (!) Libur - tidak ada praktik  |
+--------------------------------+
```

**Elemen**

1. `x-page-header` (fase 3: judul statis "Jadwal Dokter" dan subjudul "Jadwal praktik per minggu" sampai model `Page` ada di fase 4a, F3-R18).
2. Filter: navigasi minggu (tombol minggu sebelumnya/berikutnya + label rentang tanggal), poliklinik, dokter, tombol **Terapkan** dan **Reset**. `minggu` berisi tanggal mana pun dalam minggu (`YYYY-MM-DD`) yang dinormalisasi ke Senin; `poli` dan `dokter` berisi slug (00 I.5).
3. **Grid mingguan** (`x-schedule-table`): baris = dokter (nama + spesialisasi), kolom = Senin–Minggu dengan tanggal; sel = jam praktik + `x-availability-badge` ringkas (“Sisa 5”, “Penuh”, “Cuti”, “Libur”, “Ditutup”); sel kosong bertanda “-”. Satu sel menampilkan satu label `Availability` menurut prioritas 00 F.3: `past` → `leave`/`holiday`/`cancelled` → `under_review` → `closed` → `full` → `booking_closed` → `available`. Grid dan daftar bertumpuk berasal dari koleksi `SessionAvailability` yang sama (F3-R24): grid (partial `public/schedules/_week-grid`) tampil di layar ≥ md, sedangkan di layar < md daftar bertumpuk per hari memakai `x-schedule-table` per tanggal; hari lampau pada minggu berjalan tampil "Sudah lewat" (`includePast`).
4. **Legenda status** (teks + warna + ikon) selalu terlihat di bawah grid.
5. Tampilan mobile: judul hari (“Senin, 5 Okt 2026”) diikuti kartu per dokter yang praktik pada hari itu.

**Perilaku interaksi**

- Sel “Sisa N” adalah tautan **Daftar** ke `patient.appointments.create?sesi={id}`; sel status lain tidak dapat diklik. Dokter dengan lebih dari satu sesi per hari menampilkan sel bertumpuk.
- Navigasi minggu dibatasi dari minggu berjalan sampai minggu yang masih mencakup `booking.window_days`; tombol di luar batas dinonaktifkan dengan teks penjelas.
- Memilih poliklinik membatasi daftar dokter pada poliklinik tersebut; seluruh filter bekerja lewat GET.

**Kondisi**

| Kondisi | Tampilan |
|---|---|
| Loading | Tombol **Terapkan** ber-spinner; grid tidak dikosongkan sebelum hasil baru tiba (halaman dirender server) |
| Kosong | `x-empty-state` “Tidak ada jadwal praktik pada pekan ini” + tombol pindah ke minggu berikutnya dan **Reset** |
| Berhasil | Grid terisi; sel `leave`/`holiday` tampil jelas sebagai “Cuti”/“Libur” dan tidak dapat dipesan |
| Gagal | `minggu` tidak valid atau di luar jendela → pesan inline dan minggu terdekat yang valid; galat server → halaman 500 |

**Responsif**

| Lebar | Perilaku |
|---|---|
| ≥ 992 px | Grid 7 kolom penuh |
| < 992 px | Berganti ke tampilan bertumpuk per hari (grid 7 kolom tidak muat nyaman); filter menjadi accordion “Filter” |
| < 576 px | Kartu penuh-lebar; tombol Daftar penuh-lebar |

**Aksesibilitas**

- Grid memakai `<table>` sungguhan: `<th scope="col"><time datetime="…">Sen 5</time></th>` dan `<th scope="row">` nama dokter; sel kosong berisi “tidak ada praktik” untuk pembaca layar (visually-hidden) selain tanda “-”.
- Tautan Daftar bernama jelas (dokter + tanggal + jam + sisa kuota); tidak ada informasi yang hanya dibedakan warna (legenda memakai teks dan ikon); kontras badge sesuai bagian 7 (kuning dengan teks hitam, merah dengan teks putih ±4,5:1).
- Urutan fokus: filter → grid (baris demi baris, tautan Daftar) → legenda; pada mobile urutan mengikuti tanggal.

---

## W-06 Detail layanan dan poliklinik

**Halaman 5** (`clinics.show`; daftar layanan halaman 4 memakai kartu serupa W-02).

### Desktop

```text
+------------------------------------------------------------------------------+
| Beranda > Layanan > Poli Urologi                                             |
+------------------------------------------------------------------------------+
| +----------------------------------------------+  +- INFORMASI ------------+ |
| |                                              |  | Lokasi                 | |
| |    {cover poliklinik, rasio 16:9}            |  | Gedung A Lt. 2         | |
| |                                              |  | Universitas Pamulang   | |
| +----------------------------------------------+  | Kampus 2 Viktor        | |
| Poli Urologi                                (h1)  +------------------------+ |
| Gedung A Lt. 2   -   Kode antrean: URO            | Layanan ini dapat      | |
| Ringkasan layanan (short_description) satu-dua    | didaftar secara online | |
| kalimat tentang poliklinik ini (data contoh).     | (is_bookable)          | |
|                                                   |                        | |
| Deskripsi (rich text tersanitasi): paragraf,      | [ Daftar Kunjungan ]   | |
| daftar tindakan, persiapan kunjungan, dsb.        | [ Jadwal Dokter    ]   | |
|                                                   +------------------------+ |
| GALERI                                            | Butuh bantuan?         | |
| +---------+ +---------+ +---------+ +---------+   | Telp: (021) 0000-0000  | |
| | {f1}    | | {f2}    | | {f3}    | | {f4}    |   | (placeholder)          | |
| +---------+ +---------+ +---------+ +---------+   +------------------------+ |
|                                                                              |
| DOKTER DI POLIKLINIK INI                                                     |
| +---------------------+  +---------------------+                             |
| | dr. Raka Wirawan,   |  | dr. Bima Santoso,   |                             |
| | Sp.U                |  | Sp.U                |                             |
| | Sisa 5 (Sen 5 Okt)  |  | Sisa 12 (Sen 12 Okt)|                             |
| | Tersedia - 5 Okt    |  | Tersedia - 12 Okt   |                             |
| +---------------------+  +---------------------+                             |
+------------------------------------------------------------------------------+
```

```text
+- Varian kondisi -----------------------------------------------------------+
| Layanan penunjang (is_bookable = false):                                   |
| tombol Daftar Kunjungan diganti catatan:                                   |
| "Layanan ini tidak dapat didaftar online. Hubungi kami di (021) 0000-0000."|
| Tanpa dokter terjadwal: x-empty-state 'Belum ada dokter terjadwal'.        |
+----------------------------------------------------------------------------+
```

**Elemen**

1. Breadcrumb: Beranda › Layanan › {nama}.
2. Gambar `cover` (rasio 16:9) dan galeri `gallery` (thumbnail; klik membuka lightbox Bootstrap dengan `alt`).
3. `<h1>` nama; lokasi (`location`, mis. “Gedung A Lt. 2”); `code` poliklinik (awalan nomor antrean) hanya bila `is_bookable`; `short_description`; `description` (rich text tersanitasi, dirender `{!! !!}`).
4. **Dokter di poliklinik ini**: `x-doctor-card` ringkas (fase 2: foto, nama tampilan, spesialisasi utama, dan tautan `doctors.show` bila route ada; jadwal praktik terdekat dari `DoctorAvailabilityQuery` ditambahkan di fase 3, F2-R9) (“Sisa 5 (Sen 5 Okt)”).
5. Panel samping: lokasi, status dapat didaftar online, tombol **Daftar Kunjungan** (`patient.appointments.create?poli={clinics.slug}`) dan **Jadwal Dokter** (`schedules.index?poli={clinics.slug}`), serta kontak bantuan (placeholder).

**Perilaku interaksi**

- “Daftar Kunjungan” membuka wizard langkah 2 (poliklinik sudah dipilih); tamu diarahkan login terlebih dahulu.
- Layanan penunjang (`is_bookable = false`): tombol Daftar diganti catatan dan tautan ke `contact`.
- Klik thumbnail membuka lightbox yang dapat ditutup dengan Esc dan dioperasikan dengan panah keyboard.

**Kondisi**

| Kondisi | Tampilan |
|---|---|
| Loading | Gambar `lazy`; panel samping tidak bergantung JavaScript |
| Kosong | Tanpa dokter terjadwal → `x-empty-state` “Belum ada dokter terjadwal”; tanpa galeri → hanya cover/placeholder `clinic.svg`; `description` kosong → hanya ringkasan |
| Berhasil | — (halaman informasi) |
| Gagal | Slug salah atau `is_active = false` → 404; gambar hilang → placeholder |

**Responsif**

| Lebar | Perilaku |
|---|---|
| ≥ 992 px | Konten 8/12 + panel samping 4/12 (lengket saat gulir) |
| < 992 px | Panel samping berpindah ke bawah judul (tombol utama tetap terlihat tanpa gulir jauh) |
| < 576 px | Kartu dokter 1 kolom; galeri berupa baris geser |

**Aksesibilitas**

- Urutan fokus: breadcrumb → konten → galeri → kartu dokter → panel samping (tombol Daftar Kunjungan).
- `alt` pada cover dan setiap foto galeri dari `media.alt_text`; lightbox: `role="dialog"`, fokus berpindah ke dialog dan kembali ke thumbnail saat ditutup.
- Heading terstruktur: h1 nama, h2 “Dokter di poliklinik ini”, h2 “Galeri”; kontras tombol sesuai token (`$primary` putih ±8,6:1).

---

## W-07 Kamar rawat inap (ringkasan dan detail kategori)

**Halaman 11** (`rooms.index`) dan **halaman 12–15** (`rooms.show`, slug seed `bpjs-kelas-1`, `bpjs-kelas-2`, `bpjs-kelas-3`, `vip`; penjelasan mengapa dihitung 4 halaman pada [05 — bagian 5.1](05-sitemap-matriks-halaman.md#51-halaman-1215-memakai-route-yang-sama-roomsshow-tetapi-tetap-dihitung-4-halaman)).

### Ringkasan (halaman 11)

```text
+------------------------------------------------------------------------------+
| Beranda > Kamar Rawat Inap                                                   |
| KAMAR RAWAT INAP                                 (h1, pages.rooms-index)     |
| +--------------------------------------------------------------------------+ |
| | (i) Ketersediaan diperbarui manual oleh petugas, bukan data real-time.   | |
| | Nama dan klasifikasi kategori adalah kebutuhan prototipe, bukan ketentuan| |
| | BPJS yang sudah diverifikasi. Tarif adalah contoh.                       | |
| +--------------------------------------------------------------------------+ |
| +-----------------------------------+  +-----------------------------------+ |
| | +-------------------------------+ |  | +-------------------------------+ | |
| | | {foto kamar}                  | |  | | {foto kamar}                  | | |
| | |                               | |  | |                               | | |
| | +-------------------------------+ |  | +-------------------------------+ | |
| | BPJS Kelas 1                      |  | BPJS Kelas 2                      | |
| | Ruang bersama 2 tempat tidur.     |  | Ruang bersama 4 tempat tidur.     | |
| | Kapasitas: 12 tempat tidur        |  | Kapasitas: 16 tempat tidur        | |
| | Tersedia : 4 dari 12  [Tersedia]  |  | Tersedia : 7 dari 16  [Tersedia]  | |
| | Tarif contoh: Rp 350.000 / malam  |  | Tarif contoh: Rp 350.000 / malam  | |
| | (contoh, bukan tarif resmi)       |  | (contoh, bukan tarif resmi)       | |
| | Diperbarui: 02 Okt 2026, 09.30    |  | Diperbarui: 02 Okt 2026, 09.30    | |
| | [ Lihat Detail ]                  |  | [ Lihat Detail ]                  | |
| +-----------------------------------+  +-----------------------------------+ |
| +-----------------------------------+  +-----------------------------------+ |
| | +-------------------------------+ |  | +-------------------------------+ | |
| | | {foto kamar}                  | |  | | {foto kamar}                  | | |
| | |                               | |  | |                               | | |
| | +-------------------------------+ |  | +-------------------------------+ | |
| | BPJS Kelas 3                      |  | VIP / Pribadi                     | |
| | Ruang bersama 6 tempat tidur.     |  | Ruang pribadi dengan fasilitas.   | |
| | Kapasitas: 24 tempat tidur        |  | Kapasitas: 6 tempat tidur         | |
| | Tersedia : 0 dari 24  [Penuh]     |  | Tersedia : 2 dari 6  [Tersedia]   | |
| | Tarif contoh: Rp 350.000 / malam  |  | Tarif contoh: Rp 350.000 / malam  | |
| | (contoh, bukan tarif resmi)       |  | (contoh, bukan tarif resmi)       | |
| | Diperbarui: 02 Okt 2026, 09.30    |  | Diperbarui: 02 Okt 2026, 09.30    | |
| | [ Lihat Detail ]                  |  | [ Lihat Detail ]                  | |
| +-----------------------------------+  +-----------------------------------+ |
+------------------------------------------------------------------------------+
```

### Detail kategori (halaman 12–15), contoh “BPJS Kelas 1”

```text
+------------------------------------------------------------------------------+
| Beranda > Kamar Rawat Inap > BPJS Kelas 1                                    |
| BPJS KELAS 1                                          (h1)                   |
+------------------------------------------------------------------------------+
| +------------------------------------------+  +- RINGKASAN ----------------+ |
| |                                          |  | Kapasitas : 12 tempat tidur| |
| |   {foto utama kamar, 4:3}                |  | Tersedia  : 4 dari 12      | |
| |   (klik: lightbox, alt wajib)            |  | [Tersedia]                 | |
| |                                          |  | Tarif contoh:              | |
| +------------------------------------------+  | Rp 350.000 per malam       | |
| +------------+ +------------+ +------------+  | (label: contoh)            | |
| | {t1}       | | {t2}       | | {t3}       |  | Catatan: tidak termasuk    | |
| +------------+ +------------+ +------------+  | tindakan medis (rate_note).| |
|                                               +----------------------------+ |
| Deskripsi kategori (rich text tersanitasi).   | Diperbarui : 02 Okt 2026   | |
|                                               | 09.30 WIB (manual, bukan   | |
| FASILITAS KAMAR (amenities)                   | real-time)                 | |
| (v) Tempat tidur      (v) Televisi            +----------------------------+ |
| (v) Kamar mandi dalam (v) Lemari pasien       | [ Tanya Ketersediaan ]     | |
| (v) AC                (v) Bel panggil         | [ Chat WhatsApp      ]     | |
|                                               +----------------------------+ |
+------------------------------------------------------------------------------+
```

### Mobile (detail kategori)

```text
+--------------------------------+
| Beranda > Kamar > BPJS Kelas 1 |
| BPJS Kelas 1                   |
| +----------------------------+ |
| | {foto utama}               | |
| |                            | |
| +----------------------------+ |
| [t1] [t2] [t3]  (geser)        |
| +----------------------------+ |
| | Kapasitas : 12             | |
| | Tersedia  : 4 dari 12      | |
| | Tarif contoh: Rp 350.000   | |
| | per malam (contoh)         | |
| | Diperbarui: 02 Okt 09.30   | |
| +----------------------------+ |
| Deskripsi ...                  |
| FASILITAS                      |
| (v) Tempat tidur               |
| (v) Kamar mandi dalam          |
| [ Tanya Ketersediaan ]         |
+--------------------------------+
```

**Elemen**

1. Kalimat peringatan (ringkasan dan detail): “Ketersediaan diperbarui manual oleh petugas, bukan data real-time. Nama dan klasifikasi kategori adalah kebutuhan prototipe, bukan ketentuan BPJS yang sudah diverifikasi. Tarif adalah contoh.”
2. Ringkasan: kartu per kategori aktif (`room_categories`): gambar `cover`, nama, `short_description`, kapasitas (`bed_capacity`), tersedia (`beds_available`, badge Tersedia/Penuh), **tarif contoh** (`sample_rate` + `rate_unit`, selalu berlabel “contoh”), **“Diperbarui: {availability_updated_at}”**, tombol **Lihat Detail**.
3. Detail: galeri (`gallery`, foto utama + thumbnail), deskripsi rich text, **daftar fasilitas** (`amenities`, JSON array teks) berikon centang, panel ringkasan (kapasitas, tersedia, tarif contoh + `rate_note`, waktu pembaruan), tombol **Tanya Ketersediaan** (`contact`) dan **Chat WhatsApp** (tautan `wa.me`, bukan otomatis).

**Perilaku interaksi**

- Kartu ringkasan membuka halaman detail sesuai slug; klik foto galeri membuka lightbox.
- Tombol WhatsApp membuka `https://wa.me/{contact.whatsapp}` pada tab baru (`rel="noopener"`); label menjelaskan “membuka WhatsApp, bukan balasan otomatis”.
- Angka ketersediaan tidak berubah tanpa tindakan admin/petugas; tidak ada polling.

**Kondisi**

| Kondisi | Tampilan |
|---|---|
| Loading | Gambar `lazy` dengan rasio tetap |
| Kosong | Tanpa kategori aktif → `x-empty-state`; `amenities` kosong → blok fasilitas disembunyikan; `sample_rate` kosong → “Tarif: hubungi kami”; `availability_updated_at` kosong → “Belum diperbarui”; galeri kosong → placeholder `room.svg` |
| Berhasil | — |
| Gagal | Slug salah atau kategori `is_active = false` → 404; `beds_available = 0` → badge “Penuh” (bukan galat) |

**Responsif**

| Lebar | Perilaku |
|---|---|
| ≥ 992 px | Ringkasan 2 kolom (4 kategori = 2 × 2); detail: galeri + deskripsi (7/12), panel ringkasan (5/12, lengket) |
| < 992 px | Kartu 1 kolom; panel ringkasan di bawah galeri |
| < 576 px | Thumbnail galeri berupa baris geser; tombol penuh-lebar |

**Aksesibilitas**

- Urutan fokus: breadcrumb → peringatan → galeri → deskripsi → fasilitas → panel ringkasan.
- Badge Tersedia/Penuh memuat teks + ikon + warna (`$success` ±5,2:1; `$danger` ±4,5:1); label “contoh” terbaca pembaca layar (bukan hanya gaya visual).
- `alt` unik per foto; daftar fasilitas berupa `<ul>` sungguhan; waktu pembaruan memakai `<time datetime="2026-10-02T09:30">`.

---

## W-08 Kontak dan lokasi

**Halaman 22** (`contact`; kirim `contact.store`, throttle `contact` 3 per 10 menit per IP).

### Desktop

```text
+------------------------------------------------------------------------------+
| Beranda > Kontak dan Lokasi                                                  |
| KONTAK DAN LOKASI                                  (h1, pages.contact)       |
| +- ALAMAT (address, selalu tampil) ----+  +- FORMULIR KONTAK --------------+ |
| | Universitas Pamulang Kampus 2        |  | Nama lengkap *                 | |
| | Viktor, Jl. Raya Puspitek,           |  | [_________________________]    | |
| | Buaran, Kecamatan Pamulang,          |  | Email *                        | |
| | Kota Tangerang Selatan,              |  | [_________________________]    | |
| | Banten 15310                         |  | Nomor telepon (opsional)       | |
| |                                      |  | [_________________________]    | |
| | Telp: (021) 0000-0000 (placeholder)  |  | Subjek *                       | |
| | IGD : (021) 0000-0119 (placeholder)  |  | [_________________________]    | |
| | WA  : 6280000000000 (placeholder)    |  | Pesan *                        | |
| | Email: info@unpamhospital.test       |  | [                         ]    | |
| | Jam : Sen-Sab 08.00-20.00 (contoh)   |  | [                         ]    | |
| |                                      |  | [x] Saya menyetujui data saya  | |
| | [ Petunjuk Arah ] [ Chat WhatsApp ]  |  |     diproses sesuai Kebijakan  | |
| +--------------------------------------+  |     Privasi. *                 | |
| +- PETA (x-map-embed) -----------------+  | [       Kirim Pesan       ]    | |
| |                                      |  +--------------------------------+ |
| |    { peta tersemat }                 |                                     |
| |    iframe Google Maps                |                                     |
| |    loading=lazy                      |                                     |
| |                                      |                                     |
| +--------------------------------------+                                     |
+------------------------------------------------------------------------------+
```

Kondisi peta offline dan hasil kirim:

```text
+- Fallback peta offline ------------+  +- Sukses / throttle contact --------+
| (!) Peta tidak dapat dimuat.       |  | (v) Pesan Anda telah terkirim.     |
| Periksa koneksi internet Anda.     |  | Kami akan menghubungi Anda         |
| Alamat dan tombol Petunjuk Arah    |  | melalui email atau telepon.        |
| tetap dapat dipakai.               |  |                                    |
| [ Petunjuk Arah ]                  |  | (!) Terlalu banyak pengiriman.     |
+------------------------------------+  | Coba lagi dalam beberapa menit.    |
                                        +------------------------------------+
```

**Elemen**

1. `x-page-header` (`pages` key `contact`).
2. **Alamat teks** dalam `<address>` (`contact.address`) — **selalu tampil**, tidak bergantung peta.
3. Kontak placeholder: telepon, IGD, WhatsApp, email, jam operasional (`contact.*`); tiap nilai seed diberi label “(placeholder)” atau “(contoh)”.
4. Tombol **Petunjuk Arah** (`map.directions_url`, tab baru, `rel="noopener"`) dan tombol WhatsApp (tautan `wa.me` biasa).
5. **Peta tersemat** (`x-map-embed`): iframe Google Maps dari `map.embed_url` (`loading="lazy"`, `title="Peta lokasi UNPAM HOSPITAL"`); di belakangnya terdapat kotak pengganti statis berisi alamat dan tombol Petunjuk Arah yang terlihat bila iframe tidak termuat.
6. **Formulir kontak**: nama, email, telepon (opsional), subjek, pesan, checkbox persetujuan penggunaan data (tautan **Kebijakan Privasi**), tombol **Kirim Pesan**.

**Perilaku interaksi**

- Peta hanya dicoba dimuat bila `navigator.onLine !== false`; bila iframe tidak memicu `load` dalam 8 detik atau perangkat luring, kotak pengganti ditampilkan dan pesan “Peta tidak dapat dimuat” muncul **[ASUMSI]**. Iframe diizinkan oleh CSP produksi (`frame-src https://www.google.com https://maps.google.com`).
- Kirim formulir: validasi server (Form Request); sukses → redirect dengan flash hijau dan formulir kosong; gagal → ringkasan galat di atas formulir, isian dipertahankan, fokus ke ringkasan.
- Data disimpan ke `contact_messages` (`status = new`, `consent_at`, `ip_hash`); IP mentah tidak disimpan. Throttle terlampaui → pesan inline “Terlalu banyak pengiriman, coba lagi nanti”.

**Kondisi**

| Kondisi | Tampilan |
|---|---|
| Loading | Teks “Memuat peta…” pada kotak peta; tombol **Kirim Pesan** → spinner + “Mengirim…” dan dinonaktifkan |
| Kosong | Kontak di pengaturan kosong → baris disembunyikan (alamat tetap tampil); `map.embed_url` kosong → hanya kotak pengganti + tombol Petunjuk Arah |
| Berhasil | `x-flash-alert` hijau “Pesan Anda telah terkirim. Kami akan menghubungi Anda melalui email atau telepon.” |
| Gagal | Peta gagal/offline → kotak pengganti; validasi → `x-form.errors-summary` + `invalid-feedback`; throttle → alert kuning/merah; server → halaman 500 |

**Responsif**

| Lebar | Perilaku |
|---|---|
| ≥ 992 px | Dua kolom: alamat + peta (kiri), formulir (kanan) |
| < 992 px | Satu kolom: alamat → tombol → peta → formulir |
| < 576 px | Peta setinggi 260 px; tombol Petunjuk Arah dan WhatsApp penuh-lebar |

**Aksesibilitas**

- Urutan fokus: alamat/kontak → Petunjuk Arah → WhatsApp → (peta: iframe memiliki `title`; kotak pengganti berisi tombol) → formulir (nama → email → telepon → subjek → pesan → persetujuan → kirim).
- Setiap field berlabel; `*` dijelaskan “wajib”; kolom telepon `inputmode="tel"`, email `type="email"` dengan `autocomplete`; pesan galat per field via `aria-describedby`; ringkasan galat `role="alert"`.
- Checkbox persetujuan berlabel tautan Kebijakan Privasi dengan teks tautan jelas (“Kebijakan Privasi”); tombol WhatsApp berteks “Chat via WhatsApp (membuka WhatsApp)”; kontras tombol sesuai token.

---

## W-09 Registrasi dan login pasien

**Halaman 23** (`register`) dan **24** (`login`); halaman 25 (`password.request`) dan 26 (`password.reset`) memakai kerangka `x-layouts.auth` yang sama (kartu terpusat di atas latar `$light`, logo di atas kartu, tautan “Kembali ke beranda”). Kiri: registrasi dengan galat validasi; kanan: login dengan pesan throttle.

### Desktop

```text
+------------------------------------+    +------------------------------------+
| (LOGO) UNPAM HOSPITAL              |    | (LOGO) UNPAM HOSPITAL              |
| Daftar Akun Pasien      (h1)       |    | Masuk ke Akun Pasien    (h1)       |
| +- Galat ------------------------+ |    | +- Throttle login ---------------+ |
| | Periksa isian berikut:         | |    | | (!) Terlalu banyak percobaan   | |
| | - Email sudah terdaftar.       | |    | | masuk. Coba lagi dalam 54      | |
| | - Password minimal 8 karakter. | |    | | detik.                         | |
| +--------------------------------+ |    | +--------------------------------+ |
| Nama lengkap *                     |    | Email                              |
| [Siti Rahma______________]         |    | [siti@contoh.test_________]        |
| Tanggal lahir *                    |    | Password                           |
| [12/03/1990]                       |    | [**********]  [Tampilkan]          |
| Jenis kelamin *                    |    | [x] Ingat saya                     |
| ( ) Laki-laki   (o) Perempuan      |    | [ Masuk (nonaktif, 54 dtk) ]       |
| Nomor telepon *                    |    | <Lupa password?>                   |
| [0812-0000-0000__________]         |    | Belum punya akun? <Daftar>         |
| Email *                            |    +------------------------------------+
| [siti@contoh.test_________]        |    | Galat biasa: alert generik         |
| (!) Email sudah terdaftar.         |    | "Email atau password salah"        |
| Alamat *                           |    | (tanpa membedakan penyebab).       |
| [_________________________]        |    +------------------------------------+
| Password *                         |
| [*****]                            |
| (!) Minimal 8 karakter, berisi     |
|     huruf dan angka.               |
| Konfirmasi password *              |
| [*****]                            |
| [x] Saya menyetujui Kebijakan      |
|     Privasi dan penggunaan data    |
|     untuk pendaftaran. *           |
| [         Daftar         ]         |
| Sudah punya akun? <Masuk>          |
+------------------------------------+
```

### Mobile (login dengan galat, dan permintaan reset password)

```text
+--------------------------------+      +--------------------------------+
| (LOGO) UNPAM HOSPITAL          |      | (LOGO) UNPAM HOSPITAL          |
| Masuk ke Akun Pasien           |      | Lupa Password                  |
| +- Galat --------------------+ |      | Masukkan email akun Anda.      |
| | (!) Email atau password    | |      | Kami akan mengirim tautan      |
| | salah.                     | |      | reset jika email terdaftar.    |
| +----------------------------+ |      | Email                          |
| Email                          |      | [siti@contoh.test____________] |
| [siti@contoh.test____________] |      | [   Kirim tautan reset   ]     |
| Password                       |      +--------------------------------+
| [**********]  [Tampilkan]      |      | Setelah dikirim (selalu        |
| [x] Ingat saya                 |      | generik):                      |
| [          Masuk          ]    |      | +----------------------------+ |
| <Lupa password?>               |      | | (v) Jika email terdaftar,  | |
| Belum punya akun? <Daftar>     |      | | tautan reset telah dikirim.| |
| <Kembali ke beranda>           |      | +----------------------------+ |
+--------------------------------+      | <Kembali ke halaman masuk>     |
                                        +--------------------------------+
```

**Elemen**

1. Registrasi: nama lengkap, tanggal lahir, jenis kelamin (`x-form.radio-group`: Laki-laki/Perempuan), nomor telepon, email, alamat (`x-form.textarea`), password, konfirmasi password (tombol “Tampilkan”), checkbox **persetujuan** penggunaan data (tautan `privacy`), tombol **Daftar**, tautan “Sudah punya akun? Masuk”. **NIK tidak diminta.**
2. Login: email, password, “Ingat saya”, tombol **Masuk**, tautan “Lupa password?” (`password.request`) dan “Belum punya akun? Daftar” (`register`).
3. Ringkasan galat (`x-form.errors-summary`) di atas formulir + `invalid-feedback` di bawah field.
4. Lupa password: satu field email + tombol **Kirim tautan reset**; form reset (`password.reset`): email (terisi, hanya baca), password baru, konfirmasi.

**Perilaku interaksi**

- Registrasi: validasi server (Form Request); aturan password `Password::min(8)->letters()->numbers()`; sukses → transaksi membuat `users` (`account_type = patient`) dan `patient_profiles` (`registration_channel = online`, `consent_at`, `consent_version`), pengguna otomatis login dan diarahkan ke `patient.dashboard`.
- Login: `Auth::attempt` + regenerasi session; sukses → *intended URL* (mis. kembali ke wizard) atau `patient.dashboard`. Akun staf ditolak dengan pesan generik yang sama (“Email atau password salah”).
- Throttle: `login` 5 per menit per email+IP; `register` 5 per jam per IP; `password-reset` 3 per 10 menit per email+IP. Saat terlampaui halaman menampilkan alert “Terlalu banyak percobaan masuk. Coba lagi dalam {n} detik.”, tombol Masuk dinonaktifkan dengan hitung mundur (JavaScript; tanpa JavaScript teks detik saja) **[ASUMSI]**: throttle form memakai respons *redirect back* dengan galat, sedangkan halaman 429 hanya untuk permintaan non-form.
- Permintaan reset: respons **selalu generik** (“Jika email terdaftar, tautan reset telah dikirim”); tautan reset tidak pernah tampil di halaman (lokal: `storage/logs/laravel-YYYY-MM-DD.log` atau Mailpit).

**Kondisi**

| Kondisi | Tampilan |
|---|---|
| Loading | `x-loading-button` “Memproses…”; seluruh formulir `aria-busy` |
| Kosong | — (formulir awal tanpa isian) |
| Berhasil | Registrasi → dashboard dengan flash “Akun berhasil dibuat”; reset berhasil → `login` dengan flash hijau; permintaan reset → pesan generik hijau |
| Gagal | Validasi → ringkasan galat + pesan per field (email sudah dipakai, password kurang kuat, konfirmasi tidak sama, persetujuan belum dicentang); login → alert merah generik; throttle → alert dengan hitung mundur; token reset kedaluwarsa → galat + tautan “Minta tautan baru” |

**Responsif**

| Lebar | Perilaku |
|---|---|
| ≥ 768 px | Kartu terpusat, lebar maksimal 480 px (registrasi 560 px; field tanggal lahir dan jenis kelamin sebaris) |
| < 768 px | Kartu penuh-lebar tanpa bayangan; semua field bertumpuk; tombol penuh-lebar |

**Aksesibilitas**

- Urutan fokus: logo → judul → ringkasan galat (fokus otomatis saat ada galat) → field berurutan → persetujuan → tombol → tautan sekunder.
- Setiap field berlabel; `autocomplete` sesuai (`name`, `bday`, `tel`, `email`, `new-password`, `current-password`); galat per field `aria-describedby` + `aria-invalid`; alert throttle `role="alert"`; tombol “Tampilkan” memakai `aria-pressed` dan label “Tampilkan password”.
- Kontras: tombol Daftar/Masuk putih pada `$primary` (±8,6:1); pesan galat teks `$danger` pada latar putih (±4,5:1) disertai ikon (!).

---

## W-10 Dashboard pasien

**Halaman 27** (`patient.dashboard`).

### Desktop

```text
+------------------------------------------------------------------------------+
| Beranda > Portal Pasien                    (header global, lihat W-01)       |
+------------------------------------------------------------------------------+
| +------------------------+  Halo, Siti Rahma      (No. pasien: P000123)      |
| | Portal Pasien          |                                                   |
| +------------------------+  +- Banner notifikasi (kuning) -----------------+ |
| | > Dashboard            |  | (!) Jadwal kunjungan berubah                 | |
| |   Daftar Kunjungan     |  | Jam praktik dr. Sinta M. pada Rab, 7 Okt     | |
| |   Riwayat Pendaftaran  |  | kini 10.00-14.00 (sebelumnya 09.00-13.00).   | |
| |   Profil dan Akun      |  | <Lihat detail>   <Tandai sudah dibaca>       | |
| +------------------------+  +----------------------------------------------+ |
| |   Keluar               |                                                   |
| +------------------------+  [ Daftar Kunjungan ]                             |
|                                                                              |
|                             KUNJUNGAN MENDATANG                              |
|                             +----------------------------------------------+ |
|                             | UPH-7K3M-9QWA                 [Dikonfirmasi] | |
|                             | Antrean: PDL-007                             | |
|                             | dr. Sinta Maharani, Sp.PD                    | |
|                             | Poli Penyakit Dalam                          | |
|                             | Rab, 7 Okt 2026, 10.00-14.00                 | |
|                             | (!) Jadwal berubah                           | |
|                             | <Detail>   <Bukti>   [ Batalkan ]            | |
|                             +----------------------------------------------+ |
|                                                                              |
|                             RIWAYAT TERBARU                                  |
|                             +-----------+--------------+-------------+       |
|                             | Tanggal   | Poliklinik   | Status      |       |
|                             +-----------+--------------+-------------+       |
|                             | 12 Sep 26 | Poli Urologi | Selesai     |       |
|                             +-----------+--------------+-------------+       |
|                             | 30 Agu 26 | Poli Anak    | Dibatalkan  |       |
|                             +-----------+--------------+-------------+       |
|                             | 14 Agu 26 | Poli Gigi    | Tidak hadir |       |
|                             +-----------+--------------+-------------+       |
|                             <Lihat semua riwayat>                            |
+------------------------------------------------------------------------------+
```

Kondisi tanpa kunjungan mendatang:

```text
+- x-empty-state (tanpa kunjungan mendatang) ----------------+
|                                                            |
|                     (ikon bi-calendar-x)                   |
|              Belum ada kunjungan mendatang                 |
|        Daftarkan kunjungan rawat jalan Anda sekarang.      |
|                   [ Daftar Kunjungan ]                     |
|                                                            |
+------------------------------------------------------------+
```

### Mobile

```text
+--------------------------------+
| Beranda > Portal Pasien        |
| Halo, Siti Rahma               |
| [Dashboard][Daftar][Riwayat]>> |
| (nav-pills, dapat digulir)     |
+--------------------------------+
| +- Notifikasi ---------------+ |
| | (!) Jadwal kunjungan       | |
| | berubah. Jam baru 10.00-   | |
| | 14.00.                     | |
| | <Lihat detail>             | |
| +----------------------------+ |
| [ Daftar Kunjungan      ]      |
| KUNJUNGAN MENDATANG            |
| +----------------------------+ |
| | UPH-7K3M-9QWA              | |
| | [Dikonfirmasi]             | |
| | Antrean: PDL-007           | |
| | dr. Sinta Maharani, Sp.PD  | |
| | Poli Penyakit Dalam        | |
| | Rab, 7 Okt, 10.00-14.00    | |
| | <Detail> <Bukti>           | |
| +----------------------------+ |
| RIWAYAT TERBARU                |
| 12 Sep  Poli Urologi  Selesai  |
| <Lihat semua riwayat>          |
+--------------------------------+
```

**Elemen**

1. Sapaan “Halo, {nama}” dan nomor pasien (`patient_number`, mis. `P000123`).
2. **Banner notifikasi** (kuning, `x-flash-alert`) untuk notifikasi belum dibaca (`AppointmentScheduleChangedNotification`, `AppointmentStatusChangedNotification`) beserta tautan **Lihat detail** dan **Tandai sudah dibaca** (POST `patient.notifications.read`).
3. Tombol utama **Daftar Kunjungan** (`patient.appointments.create`).
4. **Kunjungan mendatang**: kartu per pendaftaran aktif (kode booking, `x-status-badge`, nomor antrean berformat `{clinics.code}-{3 digit}`, dokter, poliklinik, tanggal, jam, penanda “Jadwal berubah” bila `schedule_changed_at`), tautan **Detail**, **Bukti**, tombol **Batalkan** bila masih dalam batas pembatalan.
5. **Riwayat terbaru**: tiga–lima pendaftaran terakhir (tanggal, poliklinik, status) dan tautan ke `patient.appointments.index`.
6. Navigasi portal (daftar vertikal pada desktop; `nav-pills` pada mobile).

**Perilaku interaksi**

- “Tandai sudah dibaca” mengirim POST dan banner hilang tanpa memuat ulang halaman penuh (progressive enhancement; tanpa JavaScript halaman dimuat ulang).
- “Batalkan” membuka `x-confirm-modal` (alasan opsional); bila melewati batas pembatalan, tombol diganti info “hubungi RS” dengan telepon/WhatsApp.
- Hanya data milik pengguna (profil terhubung ke `user_id`) yang dimuat.

**Kondisi**

| Kondisi | Tampilan |
|---|---|
| Loading | Halaman dirender server; tombol aksi memakai `x-loading-button` |
| Kosong | `x-empty-state` “Belum ada kunjungan mendatang” + tombol **Daftar Kunjungan**; riwayat kosong → teks “Belum ada riwayat” |
| Berhasil | Flash hijau (mis. “Pendaftaran dibatalkan”, “Profil diperbarui”) |
| Gagal | Pembatalan ditolak (lewat batas atau status final) → flash merah dengan alasan; sesi habis → halaman 419/login |

**Responsif**

| Lebar | Perilaku |
|---|---|
| ≥ 992 px | Navigasi portal kolom kiri (3/12), konten kolom kanan (9/12) |
| < 992 px | Navigasi portal menjadi `nav-pills` yang dapat digulir di atas konten; kartu penuh-lebar |
| < 576 px | Tombol utama penuh-lebar; riwayat berupa daftar tiga baris per item |

**Aksesibilitas**

- Banner notifikasi memakai `role="status"` (informasi) atau `role="alert"` bila kunjungan terkait hari ini; judul banner dalam teks, bukan hanya warna kuning (ikon (!) + teks “Jadwal kunjungan berubah”).
- Urutan fokus: navigasi portal → banner → Daftar Kunjungan → kartu kunjungan (Detail, Bukti, Batalkan) → riwayat.
- Tombol/tautan memuat kode booking dalam nama aksesibel (“Detail pendaftaran UPH-7K3M-9QWA”); badge status teks + ikon + warna; kontras badge sesuai bagian 7.

---

## W-11 Wizard pendaftaran kunjungan

**Halaman 29** (`patient.appointments.create`, `.review`, `.store`). Alur: Poliklinik → Dokter → Tanggal dan Sesi → Data Pendaftaran → Konfirmasi → bukti. Setiap langkah adalah halaman GET yang dirender server (query `poli`, `dokter`, `sesi`); wizard berfungsi **tanpa JavaScript**, yang hanya memperhalus (indikator loading, penampil/penyembunyi field pembayaran).

Indikator langkah: `(v)` selesai (tautan kembali), `[n]` langkah aktif (`aria-current="step"`), `(n)` belum (bukan tautan).

### Langkah 1: poliklinik

```text
+------------------------------------------------------------------------------+
| ------[1]------------(2)------------(3)------------(4)------------(5)------  |
|    Poliklinik       Dokter       Tanggal &         Data        Konfirmasi    |
|                                     Sesi       Pendaftaran                   |
+------------------------------------------------------------------------------+
| Langkah 1 - Pilih poliklinik   (hanya yang aktif dan dapat didaftar online)  |
| +----------------------------+------------------+--------------+             |
| | Poliklinik                 | Lokasi           | Aksi         |             |
| +----------------------------+------------------+--------------+             |
| | Poli Anak (ANK)            | Gedung A Lt. 1   | [ Pilih ]    |             |
| +----------------------------+------------------+--------------+             |
| | Poli Penyakit Dalam (PDL)  | Gedung A Lt. 1   | [ Pilih ]    |             |
| +----------------------------+------------------+--------------+             |
| | Poli Urologi (URO)         | Gedung A Lt. 2   | [ Pilih ]    |             |
| +----------------------------+------------------+--------------+             |
| Tanpa JavaScript: tiap [ Pilih ] adalah tautan GET ke ...?poli={slug}.       |
+------------------------------------------------------------------------------+
```

### Langkah 2: dokter

```text
+------------------------------------------------------------------------------+
| ------(v)------------[2]------------(3)------------(4)------------(5)------  |
|    Poliklinik       Dokter       Tanggal &         Data        Konfirmasi    |
|                                     Sesi       Pendaftaran                   |
+------------------------------------------------------------------------------+
| Langkah 2 - Pilih dokter                    Poliklinik: Poli Urologi  <Ubah> |
| +------------------------+--------------------------+------------------+     |
| | Dokter                 | Praktik terdekat         | Aksi             |     |
| +------------------------+--------------------------+------------------+     |
| | dr. Raka Wirawan,      | Sen, 5 Okt 09.00-12.00   | [ Pilih ]        |     |
| | Sp.U                   | Sisa 5 dari 20           |                  |     |
| +------------------------+--------------------------+------------------+     |
| | dr. Bima Santoso,      | Sen, 12 Okt 13.00-15.00  | [ Pilih ]        |     |
| | Sp.U                   | Sisa 12 dari 20          |                  |     |
| +------------------------+--------------------------+------------------+     |
| | dr. Dewi Anggraini,    | Belum ada jadwal dalam   | (tidak tersedia) |     |
| | Sp.U                   | 30 hari ke depan         |                  |     |
| +------------------------+--------------------------+------------------+     |
+------------------------------------------------------------------------------+
```

### Langkah 3: tanggal dan sesi (kuota, sesi nonaktif beserta alasan)

```text
+------------------------------------------------------------------------------+
| ------(v)------------(v)------------[3]------------(4)------------(5)------  |
|    Poliklinik       Dokter       Tanggal &         Data        Konfirmasi    |
|                                     Sesi       Pendaftaran                   |
+------------------------------------------------------------------------------+
| Langkah 3 - Pilih tanggal dan sesi                                           |
| Poliklinik: Poli Urologi <Ubah>   Dokter: dr. Raka Wirawan, Sp.U <Ubah>      |
| Tanggal praktik (30 hari ke depan):                                          |
| [ Sen 5 Okt ] [ Rab 7 Okt - Cuti ] [ Jum 9 Okt - Penuh ] [ Sen 12 Okt ]      |
| Rab 7 Okt: Cuti dokter, tidak dapat dipesan (chip nonaktif, ada teks)        |
+------------------------------------------------------------------------------+
| Sesi pada Senin, 5 Oktober 2026:                                             |
| +--------------------------------------------------------------------------+ |
| | (o) 09.00-12.00   Sisa 5 dari 20        (v) Tersedia                     | |
| | ( ) 13.00-15.00   Tidak dapat dipilih   (i) Pend. online ditutup         | |
| |     Alasan: batas pendaftaran online 60 menit sebelum sesi dimulai.      | |
| | ( ) 16.00-18.00   Sisa 0 dari 15        (x) Penuh                        | |
| +--------------------------------------------------------------------------+ |
| [ Kembali ]                                    [ Lanjut: Data Pendaftaran ]  |
+------------------------------------------------------------------------------+
```

### Langkah 4: data pendaftaran (field BPJS hanya bila BPJS dipilih)

```text
+------------------------------------------------------------------------------+
| ------(v)------------(v)------------(v)------------[4]------------(5)------  |
|    Poliklinik       Dokter       Tanggal &         Data        Konfirmasi    |
|                                     Sesi       Pendaftaran                   |
+------------------------------------------------------------------------------+
| Langkah 4 - Data pendaftaran                                                 |
| Poli Urologi - dr. Raka Wirawan, Sp.U - Sen, 5 Okt 2026, 09.00-12.00         |
| Pasien: Siti Rahma (P000123)                       <Ubah profil>             |
| Jenis pembayaran *                                                           |
| ( ) Umum / Pribadi     (o) BPJS Kesehatan     ( ) Asuransi                   |
| +--------------------------------------------------------------------------+ |
| | Nomor BPJS (13 digit) *               (hanya tampil bila BPJS dipilih)   | |
| | [1234567893456]                                                          | |
| | [x] Simpan nomor ini di profil saya     (disimpan terenkripsi)           | |
| +--------------------------------------------------------------------------+ |
| Keluhan singkat (opsional, maks 500 karakter)                        0/500   |
| +--------------------------------------------------------------------------+ |
| |                                                                          | |
| |                                                                          | |
| +--------------------------------------------------------------------------+ |
| [ Kembali ]                                       [ Tinjau Pendaftaran ]     |
+------------------------------------------------------------------------------+
```

Varian pilihan pembayaran lain:

```text
+- Varian Asuransi ------------------+  +- Varian Umum ----------------------+
| Jika Asuransi dipilih:             |  | Jika Umum/Pribadi dipilih:         |
| Mitra asuransi *  [Pilih v]        |  | tidak ada field tambahan;          |
| Nomor polis (opsional)             |  | nomor BPJS dan mitra               |
| [____________________]             |  | asuransi tidak dikirim.            |
+------------------------------------+  +------------------------------------+
```

### Langkah 5: konfirmasi

```text
+------------------------------------------------------------------------------+
| ------(v)------------(v)------------(v)------------(v)------------[5]------  |
|    Poliklinik       Dokter       Tanggal &         Data        Konfirmasi    |
|                                     Sesi       Pendaftaran                   |
+------------------------------------------------------------------------------+
| Langkah 5 - Periksa dan kirim                      (respons POST .../tinjau) |
| +- RINGKASAN --------------------------------------------------------------+ |
| | Poliklinik : Poli Urologi                                                | |
| | Dokter     : dr. Raka Wirawan, Sp.U                                      | |
| | Tanggal    : Senin, 5 Oktober 2026                                       | |
| | Jam sesi   : 09.00-12.00   (sisa kuota saat ini: 5 dari 20)              | |
| | Pasien     : Siti Rahma (P000123)                                        | |
| | Pembayaran : BPJS Kesehatan - nomor *********3456                        | |
| | Keluhan    : Nyeri saat buang air kecil                                  | |
| +--------------------------------------------------------------------------+ |
| (i) Nomor antrean diberikan setelah pendaftaran berhasil dikirim.            |
| (i) Pembatalan oleh pasien dapat dilakukan hingga 120 menit sebelum sesi.    |
| [ Ubah data ]                                       [ Kirim Pendaftaran ]    |
+------------------------------------------------------------------------------+
```

Status loading saat kirim dan kegagalan:

```text
+- Loading saat kirim ---------------+  +- Gagal (BookingException) ---------+
| [ (o) Memproses... ]               |  | (!) Kuota sesi ini baru saja penuh.|
| tombol nonaktif dan spinner;       |  | Silakan pilih sesi lain.           |
| form aria-busy=true.               |  | <Kembali ke langkah 3>             |
| Klik ganda tidak mengirim ulang    |  | (alert merah, role=alert,          |
| (Cache::lock booking-submit).      |  | kode QUOTA_FULL)                   |
+------------------------------------+  +------------------------------------+
```

### Halaman sukses (`patient.appointments.show`)

```text
+------------------------------------------------------------------------------+
| Portal Pasien > Riwayat Pendaftaran > UPH-3H6Z-8RME                          |
+------------------------------------------------------------------------------+
| +- Sukses (flash hijau) ---------------------------------------------------+ |
| | (v) Pendaftaran berhasil dikirim. Status: Menunggu konfirmasi.           | |
| +--------------------------------------------------------------------------+ |
| +----------------+  Kode booking   : UPH-3H6Z-8RME                           |
| |                |  Nomor antrean  : URO-006                                 |
| |   {QR}         |  Poliklinik     : Poli Urologi                            |
| |   (SVG)        |  Dokter         : dr. Raka Wirawan, Sp.U                  |
| |                |  Jadwal         : Senin, 5 Okt 2026, 09.00-12.00          |
| +----------------+  Pembayaran     : BPJS Kesehatan (*********3456)          |
| Tunjukkan kode ini di loket. Check-in dibuka 60 menit sebelum sesi dimulai.  |
| [ Lihat / Cetak Bukti ]   [ Unduh PDF ]   [ Ke Dashboard ]                   |
+------------------------------------------------------------------------------+
```

### Mobile (langkah 3 dan 4)

```text
+--------------------------------+      +--------------------------------+
| (1)-(2)-[3]-(4)-(5)            |      | (1)-(2)-(3)-[4]-(5)            |
| Tanggal & Sesi                 |      | Data Pendaftaran               |
+--------------------------------+      +--------------------------------+
| Poli Urologi <Ubah>            |      | Sen 5 Okt, 09.00-12.00         |
| dr. Raka W. <Ubah>             |      | Pasien: Siti Rahma             |
| Tanggal:                       |      | Jenis pembayaran *             |
| [Sen 5 Okt][Rab 7 Okt-Cuti]>>  |      | ( ) Umum / Pribadi             |
| Sesi, Senin 5 Okt 2026:        |      | (o) BPJS Kesehatan             |
| +----------------------------+ |      | ( ) Asuransi                   |
| | (o) 09.00-12.00            | |      | Nomor BPJS (13 digit) *        |
| |     Sisa 5 dari 20         | |      | [1234567893456]                |
| |     (v) Tersedia           | |      | [x] Simpan di profil saya      |
| +----------------------------+ |      | Keluhan singkat (opsional)     |
| +----------------------------+ |      | +----------------------------+ |
| | ( ) 13.00-15.00            | |      | |                            | |
| |     (i) Pendaftaran        | |      | |                            | |
| |     online ditutup         | |      | +----------------------------+ |
| +----------------------------+ |      | [ Kembali ] [ Tinjau ]         |
| +----------------------------+ |      +--------------------------------+
| | ( ) 16.00-18.00            | |
| |     Sisa 0 dari 15         | |
| |     (x) Penuh              | |
| +----------------------------+ |
| [ Kembali ] [ Lanjut ]         |
+--------------------------------+
```

**Elemen**

1. Indikator langkah 5 tahap (`<ol>` dengan label teks; di mobile ringkas “Langkah 3 dari 5: Tanggal & Sesi”).
2. Langkah 1: daftar poliklinik aktif **dan** `is_bookable` (kode antrean + lokasi) dengan tombol **Pilih**.
3. Langkah 2: dokter pada poliklinik terpilih (foto kecil, nama + gelar, praktik terdekat + sisa kuota) dengan tombol **Pilih**; dokter tanpa jadwal ≤ `booking.window_days` ditampilkan nonaktif beserta alasannya.
4. Langkah 3: *chip* tanggal (jendela `booking.window_days`; tanggal terpilih dibawa query `tanggal` = `YYYY-MM-DD`, 00 I.5) — tanggal cuti/libur/penuh tampil nonaktif beserta teks alasannya; daftar sesi tanggal terpilih sebagai radio: jam, **“Sisa N dari M”**, `x-availability-badge`; sesi tidak dapat dipilih (penuh, ditutup, cuti, libur, dibatalkan, sedang diperbarui, pendaftaran online ditutup, sudah lewat) disabled dan **menampilkan alasannya** (mis. “batas pendaftaran online 60 menit sebelum sesi dimulai”).
5. Langkah 4: ringkasan pilihan; data pasien (nama, `patient_number`; tautan ubah profil); **jenis pembayaran** (`x-form.radio-group`: Umum/Pribadi, BPJS Kesehatan, Asuransi); **nomor BPJS 13 digit hanya bila BPJS dipilih** (opsi “simpan di profil”, disimpan terenkripsi, tampil 4 digit terakhir); **mitra asuransi** (`insurance_partners` aktif) + nomor polis opsional bila Asuransi dipilih; keluhan singkat opsional (maks 500 karakter, penghitung).
6. Langkah 5 (respons `POST patient.appointments.review`, yang **merender** halaman konfirmasi langsung dan tidak menyimpan apa pun): ringkasan lengkap (nomor BPJS/polis hanya 4 digit terakhir), catatan “nomor antrean diberikan setelah pendaftaran berhasil dikirim” dan aturan pembatalan, tombol **Ubah data** (tautan GET ke `patient.appointments.create` dengan query `poli`/`dokter`/`sesi`) dan **Kirim Pendaftaran** (`x-loading-button`).
7. Halaman sukses: flash hijau, kode booking + QR SVG (isi kode saja), nomor antrean, jadwal, status “Menunggu konfirmasi” (atau “Dikonfirmasi” bila `booking.auto_confirm = true`), tombol **Lihat/Cetak Bukti**, **Unduh PDF**, **Ke Dashboard**.

**Perilaku interaksi**

- Tautan “Daftar” dari jadwal publik langsung membawa `?sesi={id}` (langkah 4); `poli` dan `dokter` diturunkan dari sesi. Sesi yang tidak lagi valid membawa pengguna kembali ke langkah 3 dengan pesan.
- Memilih radio jenis pembayaran menampilkan/menyembunyikan blok BPJS atau asuransi; tanpa JavaScript semua blok tampil dengan teks bantuan “isi hanya bila memilih BPJS/Asuransi” dan validasi memakai `required_if`.
- **Kirim Pendaftaran**: tombol dinonaktifkan saat submit; `Cache::lock("booking-submit:{user_id}", 10)` mencegah kirim ganda; `BookingService::book()` memeriksa ulang seluruh aturan di dalam transaksi (00 bagian F.5–F.6). Throttle `booking` 10 per menit per pengguna.
- Sukses → redirect ke `patient.appointments.show` (flash sukses); pasien melihat kode booking dan nomor antrean.

Kegagalan validasi pada `review` maupun `store`, dan galat `BookingException`, dialihkan ke `patient.appointments.create` dengan query langkah terakhir + `withInput()`; tidak pernah `back()` karena halaman konfirmasi adalah hasil POST (00 I.3). Pengecualian: `DUPLICATE_BOOKING` mengarah ke detail pendaftaran yang sudah ada.

Perilaku layar untuk `BookingException`:

| Kode | Tampilan |
|---|---|
| `SESSION_UNAVAILABLE`, `OUTSIDE_WINDOW`, `CUTOFF_PASSED`, `QUOTA_FULL` | Kembali ke langkah 3, alert merah dengan penjelasan; sesi dimuat ulang dari `DoctorAvailabilityQuery` |
| `DUPLICATE_BOOKING` | Alert “Anda sudah memiliki pendaftaran aktif pada sesi ini” + tautan ke detailnya (`patient.appointments.show`) |
| `OVERLAPPING_BOOKING` | Alert menyebut kode booking yang berbenturan + tautan; kembali ke langkah 3 |
| `ACTIVE_LIMIT_REACHED` | Alert “Batas pendaftaran aktif tercapai” + tautan ke Riwayat Pendaftaran |
| `INVALID_PAYMENT` | Kembali ke langkah 4 dengan galat pada field BPJS/mitra asuransi |
| `SYSTEM_BUSY` | Alert “Sistem sedang sibuk, silakan coba lagi beberapa saat.” (deadlock/lock wait masih terjadi setelah 3 percobaan); tidak ada data tersimpan sebagian; isian terisi ulang dan pasien dapat mengirim ulang |

Teks pesan final berada di [12 — Aturan Bisnis, Validasi, Keamanan](12-aturan-bisnis-validasi-keamanan.md).

**Kondisi**

| Kondisi | Tampilan |
|---|---|
| Loading | Tombol langkah dan **Kirim Pendaftaran** menampilkan spinner + “Memproses…”; formulir `aria-busy` |
| Kosong | Tidak ada poliklinik dapat didaftar → `x-empty-state` + tautan `contact`; dokter tanpa sesi → pesan pada baris dokter; tanggal tanpa sesi → “Tidak ada sesi pada tanggal ini” |
| Berhasil | Halaman sukses dengan kode booking, QR, dan tombol bukti |
| Gagal | Galat validasi per field + ringkasan; `BookingException` sesuai tabel; throttle → alert “Terlalu banyak percobaan, coba lagi sebentar” |

**Responsif**

| Lebar | Perilaku |
|---|---|
| ≥ 992 px | Indikator 5 langkah penuh di atas; tabel/daftar dalam kartu lebar |
| 576–991 px | Indikator tetap penuh, label dua baris; tabel menjadi daftar kartu |
| < 576 px | Indikator ringkas “Langkah n dari 5” + judul langkah; opsi sesi berupa kartu radio penuh-lebar; tombol Kembali/Lanjut berdampingan dan melekat di bawah layar (`sticky-bottom`) **[ASUMSI]** |

**Aksesibilitas**

- Urutan fokus: indikator langkah → ringkasan pilihan → kontrol langkah → tombol Kembali → tombol Lanjut/Kirim; saat langkah berganti, fokus dipindah ke judul langkah (`tabindex="-1"`).
- Kelompok radio memakai `<fieldset><legend>` (“Jenis pembayaran”, “Pilih sesi”); opsi disabled memiliki `aria-describedby` ke teks alasan yang selalu terlihat (bukan tooltip); “Sisa 5 dari 20” dibaca apa adanya.
- Field BPJS memakai `inputmode="numeric"`, `autocomplete="off"`, dan petunjuk “13 digit angka”; galat `aria-live` bila format salah; nomor BPJS tidak ditampilkan penuh di ringkasan.
- Kontras: status sesi memakai badge dengan teks (bukan warna saja); tombol Lanjut/Kirim putih pada `$primary`.

---

## W-12 Riwayat, detail, dan bukti pendaftaran

**Halaman 30** (`patient.appointments.index`) beserta layar tambahan `patient.appointments.show` dan `patient.appointments.proof` (PDF: `patient.appointments.proof-pdf`).

### Daftar riwayat (desktop)

```text
+------------------------------------------------------------------------------+
| Portal Pasien > Riwayat Pendaftaran                                          |
| RIWAYAT PENDAFTARAN                                   (x-data-table)         |
| +- FILTER -----------------------------------------------------------------+ |
| | Status [Semua v]   Dari [__/__/____]  Sampai [__/__/____]                | |
| |                                                   [ Terapkan ] [ Reset ] | |
| +--------------------------------------------------------------------------+ |
| +---------------+------------+--------------+---------+--------------+       |
| | Kode          | Tanggal    | Dokter       | Antrean | Status       |       |
| +---------------+------------+--------------+---------+--------------+       |
| | UPH-7K3M-9QWA | 7 Okt 26   | dr. Sinta M. | PDL-007 | Dikonfirmasi |       |
| |               |            | Peny. Dalam  |         |              |       |
| +---------------+------------+--------------+---------+--------------+       |
| | UPH-4D8F-2HNB | 12 Sep 26  | dr. Raka W.  | URO-003 | Selesai      |       |
| |               |            | Poli Urologi |         |              |       |
| +---------------+------------+--------------+---------+--------------+       |
| | UPH-9T2C-5XJE | 30 Agu 26  | dr. Anita L. | ANK-011 | Dibatalkan   |       |
| |               |            | Poli Anak    |         |              |       |
| +---------------+------------+--------------+---------+--------------+       |
| Kode booking = tautan ke detail. Menampilkan 1-10 dari 3.   [<] [1] [>]      |
+------------------------------------------------------------------------------+
```

### Detail pendaftaran (desktop)

```text
+------------------------------------------------------------------------------+
| Portal Pasien > Riwayat Pendaftaran > UPH-7K3M-9QWA                          |
+------------------------------------------------------------------------------+
| +- KODE ---------------+  Status       : [Dikonfirmasi]                      |
| | {QR}                 |  Poliklinik   : Poli Penyakit Dalam                 |
| |                      |  Dokter       : dr. Sinta Maharani, Sp.PD           |
| | UPH-7K3M-9QWA        |  Jadwal       : Rab, 7 Okt 2026, 10.00-14.00        |
| |                      |  Pasien       : Siti Rahma (P000123)                |
| | Antrean PDL-007      |  Pembayaran   : BPJS Kesehatan (*********3456)      |
| +----------------------+  Kanal        : Online                              |
|                           (!) Jadwal berubah pada 5 Okt 2026 (jam baru)      |
| RIWAYAT STATUS                                                               |
| 7 Okt 08.10  Menunggu konfirmasi (oleh Pasien)                               |
| 7 Okt 08.45  Dikonfirmasi (oleh Petugas)                                     |
| [ Cetak Bukti ]   [ Unduh PDF ]   [ Batalkan Pendaftaran ]                   |
| Batal hanya sampai 120 menit sebelum sesi; sesudahnya tombol diganti info:   |
| "Hubungi RS: (021) 0000-0000 / WhatsApp" (placeholder).                      |
+------------------------------------------------------------------------------+
```

Konfirmasi pembatalan:

```text
+- x-confirm-modal ----------------------------------------------+
| Batalkan pendaftaran UPH-7K3M-9QWA?                            |
| Kuota dilepas dan nomor antrean PDL-007 tidak dipakai ulang.   |
| Alasan (opsional): [______________________________]            |
| [ Tidak, kembali ]                  [ Ya, batalkan ]           |
+----------------------------------------------------------------+
```

### Bukti pendaftaran (tampilan cetak)

```text
+- Bukti cetak (A5 potret, @page; dapat dicetak ke A4) --------------+
| (LOGO) UNPAM HOSPITAL                                              |
| BUKTI PENDAFTARAN KUNJUNGAN RAWAT JALAN                            |
+--------------------------------------------------------------------+
| +------------+  Kode booking   : UPH-7K3M-9QWA                     |
| |            |  NOMOR ANTREAN  : PDL-007                           |
| |  {QR}      |  Status         : Dikonfirmasi                      |
| |            |                                                     |
| +------------+                                                     |
| Pasien       : Siti Rahma (P000123)                                |
| Poliklinik   : Poli Penyakit Dalam                                 |
| Dokter       : dr. Sinta Maharani, Sp.PD                           |
| Tanggal/jam  : Rabu, 7 Okt 2026, 10.00-14.00                       |
| Pembayaran   : BPJS Kesehatan (nomor *********3456)                |
+--------------------------------------------------------------------+
| Tunjukkan kode ini di loket pendaftaran. Check-in dibuka 60 menit  |
| sebelum sesi dimulai dan ditutup saat sesi berakhir.               |
+--------------------------------------------------------------------+
| Dicetak: 02 Okt 2026 10.15 WIB                                     |
| Dokumen dari sistem prototipe (data contoh).                       |
+--------------------------------------------------------------------+
```

### Mobile (daftar riwayat sebagai kartu)

```text
+--------------------------------+
| Portal Pasien > Riwayat        |
| Riwayat Pendaftaran            |
| [ Filter v ]                   |
| +----------------------------+ |
| | UPH-7K3M-9QWA              | |
| | [Dikonfirmasi]             | |
| | Rab, 7 Okt 2026            | |
| | dr. Sinta M. - Peny. Dalam | |
| | Antrean PDL-007            | |
| | <Detail>                   | |
| +----------------------------+ |
| +----------------------------+ |
| | UPH-4D8F-2HNB              | |
| | [Selesai]                  | |
| | Sen, 12 Sep 2026           | |
| | dr. Raka W. - Poli Urologi | |
| | Antrean URO-003            | |
| | <Detail>                   | |
| +----------------------------+ |
| [<] [1] [>]                    |
+--------------------------------+
```

**Elemen**

1. Daftar (`x-data-table`): filter status dan rentang tanggal; kolom kode booking (tautan ke detail), tanggal kunjungan, dokter + poliklinik, nomor antrean, status (`x-status-badge`); pagination.
2. Detail: kode booking + QR, status, poliklinik, dokter, jadwal, pasien, pembayaran (nomor BPJS/polis hanya 4 digit terakhir), kanal, penanda “Jadwal berubah”, **riwayat status** (`appointment_status_histories`), tombol **Cetak Bukti**, **Unduh PDF**, **Batalkan Pendaftaran**.
3. Bukti cetak: nama situs/logo, judul “BUKTI PENDAFTARAN KUNJUNGAN RAWAT JALAN”; kode booking + QR; **nomor antrean** besar; nama pasien + nomor pasien; poliklinik; dokter; tanggal; jam sesi; jenis pembayaran (4 digit terakhir BPJS/polis); status; instruksi check-in; waktu cetak; catatan “dokumen dari sistem prototipe (data contoh)”.

**Perilaku interaksi**

- Hanya pemilik profil yang dapat membuka detail/bukti (`AppointmentPolicy::view`); pengguna lain menerima 404 (tidak membedakan "bukan milik Anda" dari "tidak ada"; D4 di [04](04-peran-hak-akses.md)) walaupun mengetahui `booking_code`.
- **Batalkan** hanya tersedia untuk status `pending`/`confirmed` dan selama `now <= start − booking.cancel_cutoff_minutes`; membuka `x-confirm-modal` dengan alasan opsional, sukses → status `cancelled`, kuota dilepas, nomor antrean tidak dipakai ulang. Setelah batas, tombol diganti info “Hubungi RS: telepon/WhatsApp (placeholder)”.
- **Cetak Bukti** membuka `patient.appointments.proof` (CSS print); **Unduh PDF** mengunduh `proof-pdf` (dompdf). Bukti memakai `@page { size: A5 portrait; margin: 10mm }`; dapat dicetak ke A4 dengan opsi “sesuaikan ke halaman” **[ASUMSI]**. Elemen navigasi/tombol disembunyikan saat cetak (`d-print-none`).
- QR hanya memuat string kode booking (tanpa data pribadi).

**Kondisi**

| Kondisi | Tampilan |
|---|---|
| Loading | Tombol batal/unduh memakai `x-loading-button`; PDF diunduh sebagai berkas (tab tidak berganti) |
| Kosong | Riwayat kosong → `x-empty-state` “Belum ada pendaftaran” + **Daftar Kunjungan**; filter tanpa hasil → “Tidak ada pendaftaran pada filter ini” + **Reset** |
| Berhasil | Flash hijau “Pendaftaran berhasil dibatalkan”; kuota dilepas |
| Gagal | Lewat batas pembatalan/status final → flash merah dengan alasan; bukan pemilik → 404; gagal membuat PDF → pesan + saran memakai Cetak Bukti |

**Responsif**

| Lebar | Perilaku |
|---|---|
| ≥ 768 px | Tabel riwayat; detail dua kolom (kode/QR di kiri) |
| < 768 px | Tabel menjadi kartu per pendaftaran; detail satu kolom; tombol aksi bertumpuk penuh-lebar |
| Cetak | Satu kolom A5, font 11 pt, hitam-putih; QR minimal 28 mm |

**Aksesibilitas**

- Tautan kode booking diberi nama “Detail pendaftaran UPH-7K3M-9QWA”; tabel memakai `<th scope>`; riwayat status berupa daftar berurutan (`<ol>`) dengan tanggal `<time>`.
- Modal pembatalan: fokus ke judul modal, tombol “Tidak, kembali” sebagai fokus awal yang aman, Esc menutup, fokus kembali ke tombol Batalkan.
- QR memiliki `alt="Kode QR booking UPH-7K3M-9QWA"` dan kode booking juga ditulis sebagai teks (alternatif); bukti dicetak dengan kontras hitam di atas putih.

---

## W-13 Kerangka admin

**Layout `x-layouts.admin`** untuk halaman 31 (login memakai `x-layouts.auth`) dan 32–40 beserta layar tambahan. Daftar item sidebar dan syarat `@can` ada pada [05 — bagian 3.5](05-sitemap-matriks-halaman.md#35-sidebar-admin-layoutsadmin).

### Desktop (contoh peran Super Admin; menu pengguna terbuka)

```text
+--------------------------------------------------------------------------------------+
| +- SIDEBAR (Super Admin) ----+  +- TOPBAR -----------------------------------------+ |
| | (LOGO) UNPAM Admin         |  | Dasbor > Dokter       [Lihat Situs] [Andi A. v]  | |
| +----------------------------+  +--------------------------------------------------+ |
| | > Dashboard                |                                                       |
| | PELAYANAN PASIEN           |                          +------------------------+   |
| |   Pendaftaran              |                          | Andi Admin             |   |
| |   Antrean Hari Ini         |                          | Super Admin            |   |
| |   Pasien                   |                          +------------------------+   |
| |   Pesan Kontak          (2)|                          | Ganti Password         |   |
| | DOKTER DAN JADWAL          |                          | Keluar                 |   |
| |   Dokter                   |                          +------------------------+   |
| |   Poliklinik & Spesialisasi|                                                       |
| |   Jadwal Praktik           |  Dokter                          [ Tambah Dokter ]    |
| |   Sesi Terdampak        (3)|                                                       |
| | FASILITAS DAN KAMAR        |  +- KONTEN -----------------------------------------+ |
| |   Kamar dan Kapasitas      |  |                                                  | |
| |   Fasilitas                |  |    Konten halaman admin                          | |
| | KONTEN WEBSITE             |  |    (x-data-table, form, dst.)                    | |
| |   Halaman                  |  |                                                  | |
| |   Banner                   |  +--------------------------------------------------+ |
| |   Artikel                  |                                                       |
| |   Kategori Artikel         |  UNPAM HOSPITAL Admin - prototipe                     |
| |   Galeri                   |                                                       |
| |   FAQ                      |                                                       |
| |   Mitra Asuransi           |                                                       |
| | SISTEM                     |                                                       |
| |   Pengaturan Website       |                                                       |
| |   Akun Staf                |                                                       |
| |   Hak Akses                |                                                       |
| |   Audit Log                |                                                       |
| +----------------------------+                                                       |
+--------------------------------------------------------------------------------------+
```

Sidebar yang sama setelah difilter `@can` untuk dua peran lain:

```text
+- Petugas Pendaftaran ------+  +- Admin Konten -------------+
| > Dashboard                |  | > Dashboard                |
| PELAYANAN PASIEN           |  | DOKTER DAN JADWAL          |
|   Pendaftaran              |  |   Dokter                   |
|   Antrean Hari Ini         |  |   Poliklinik & Spesialisasi|
|   Pasien                   |  |   Jadwal Praktik           |
|   Pesan Kontak          (2)|  | FASILITAS DAN KAMAR        |
| DOKTER DAN JADWAL          |  |   Kamar dan Kapasitas      |
|   Dokter                   |  |   Fasilitas                |
|   Jadwal Praktik           |  | KONTEN WEBSITE             |
|   Sesi Terdampak        (3)|  |   Halaman, Banner, dst.    |
| FASILITAS DAN KAMAR        |  +----------------------------+
|   Kamar dan Kapasitas      |
+----------------------------+
```

### Mobile (header dan offcanvas sidebar, contoh Petugas Pendaftaran)

```text
+--------------------------------+      +--------------------------------+
| [=] (LOGO) Admin   [Siti P. v] |      | Menu admin                 [X] |
+--------------------------------+      +--------------------------------+
| Dasbor > Dokter                |      | > Dashboard                    |
| Dokter       [ Tambah Dokter ] |      | PELAYANAN PASIEN               |
| +----------------------------+ |      |   Pendaftaran                  |
| | (kartu data, lihat W-15)   | |      |   Antrean Hari Ini             |
| +----------------------------+ |      |   Pasien                       |
+--------------------------------+      |   Pesan Kontak        (2)      |
                                        | DOKTER DAN JADWAL              |
                                        |   Dokter                       |
                                        |   Jadwal Praktik               |
                                        |   Sesi Terdampak      (3)      |
                                        | FASILITAS DAN KAMAR            |
                                        |   Kamar dan Kapasitas          |
                                        +--------------------------------+
                                        | Lihat Situs   Keluar           |
                                        +--------------------------------+
```

**Elemen**

1. **Sidebar** latar `$primary`, lebar 260 px: logo + “UNPAM Admin”; Dashboard; grup **Pelayanan Pasien**, **Dokter dan Jadwal**, **Fasilitas dan Kamar**, **Konten Website**, **Sistem**; setiap item = ikon + teks; lencana angka pada “Sesi Terdampak” dan “Pesan Kontak”.
2. **Topbar**: tombol hamburger (< 992 px), breadcrumb (`x-breadcrumb`), tautan **Lihat Situs** (tab baru), **menu pengguna** (nama staf, nama peran, Ganti Password → `admin.password.edit`, Keluar → POST `admin.logout`).
3. Area konten: `x-page-header` (judul + tombol aksi utama), `x-flash-alert`, konten halaman.
4. Footer kecil “UNPAM HOSPITAL Admin — prototipe”.

**Perilaku interaksi**

- Setiap item dan grup dibungkus `@can`/`@canany`; grup tanpa item terlihat disembunyikan. Penyembunyian hanyalah kenyamanan: akses sebenarnya ditegakkan middleware `permission:*`, Policy, dan Form Request.
- Item aktif memakai `aria-current="page"` dan latar `$light`; lencana dihitung dari agregat (jumlah sesi `needs_review`, pesan `new`), **tanpa** nama pasien/pengirim.
- Staf dengan `must_change_password` hanya dapat membuka `admin.password.edit` sampai password diganti (middleware `password.changed`).
- Menu pengguna dan sidebar mobile berperilaku sebagai dropdown/offcanvas Bootstrap.

**Kondisi**

| Kondisi | Tampilan |
|---|---|
| Loading | Lencana sidebar dirender server; aksi lama memakai `x-loading-button` |
| Kosong | Grup tanpa izin tidak tampil; staf tanpa izin apa pun hanya melihat Dashboard (bila `dashboard.view`) atau halaman 403 |
| Berhasil | `x-flash-alert` hijau di bawah topbar |
| Gagal | 403 untuk URL tanpa izin (walau menu disembunyikan); 419 saat sesi berakhir; flash merah untuk galat tindakan |

**Responsif**

| Lebar | Perilaku |
|---|---|
| ≥ 992 px | Sidebar tetap 260 px di kiri; topbar menempel (`sticky-top`) |
| < 992 px | Sidebar menjadi offcanvas kiri (urutan, ikon, filter `@can` sama); topbar menampilkan hamburger, nama halaman, dan menu pengguna |
| < 576 px | Breadcrumb menyingkat menjadi “‹ {induk}”; tombol aksi halaman pindah di bawah judul |

**Aksesibilitas**

- Urutan fokus: skip link → sidebar (atas ke bawah) → topbar → konten; `<nav aria-label="Menu admin">` untuk sidebar dan `<nav aria-label="Breadcrumb">` untuk jejak.
- Judul grup berupa teks (bukan hanya ikon); item aktif `aria-current="page"`; lencana angka memiliki teks tersembunyi “3 sesi perlu tindak lanjut”.
- Kontras sidebar: teks putih pada `$primary` ±8,6:1; item aktif `$light` dengan teks `$primary` ±7,9:1; cincin fokus putih pada sidebar; tombol hamburger `aria-label="Buka menu admin"`.

---

## W-14 Dashboard admin

**Halaman 32** (`admin.dashboard`; data grafik JSON `admin.dashboard.chart`). Sumber: `DashboardMetrics`, `room_categories`, `practice_sessions`.

### Desktop (area konten)

```text
+--------------------------------------------------------------------------------------+
| Dasbor                                                           (judul: Dashboard)  |
| +------------------------+   +------------------------+   +------------------------+ |
| | (bi-person-badge)      |   | (bi-person-lines-fill) |   | (bi-journal-check)     | |
| | 15                     |   | 128                    |   | 21                     | |
| | Dokter aktif           |   | Pasien terdaftar       |   | Pendaftaran hari ini   | |
| +------------------------+   +------------------------+   +------------------------+ |
| PENDAFTARAN PER STATUS  (periode grafik: 7 hari terakhir, tanggal kunjungan)         |
| +----------------------------------------------------------------------------------+ |
| | [ 8 ]        [ 12 ]       [ 3 ]        [ 21 ]       [ 2 ]        [ 1 ]           | |
| | Menunggu     Dikonfirmasi Hadir /      Selesai      Dibatalkan   Tidak hadir     | |
| | konfirmasi                check-in                                               | |
| +----------------------------------------------------------------------------------+ |
| +- GRAFIK (Chart.js) --------------------------------------------------------------+ |
| | Pendaftaran per hari (tanggal kunjungan)          Periode [7 hari v] [ Terapkan ]| |
| | Kustom: Dari [__/__/____]  Sampai [__/__/____]                                   | |
| |  20 |                                                                            | |
| |  15 |           ##                                                               | |
| |  10 |      ##   ##        ##                                                     | |
| |   5 | ##   ##   ##   ##   ##   ##                                                | |
| |   0 +--------------------------------------------                                | |
| |       Sen  Sel  Rab  Kam  Jum  Sab  Min                                          | |
| | <Lihat data dalam tabel>   (alternatif teks untuk pembaca layar)                 | |
| +----------------------------------------------------------------------------------+ |
| +- RINGKASAN KAMAR ---------------------+  +- SESI TERDAMPAK (3) ------------------+ |
| | BPJS Kelas 1   4 dari 12  [####------]|  | Rab 14 Okt dr. Raka W. - Poli Urologi | |
| | BPJS Kelas 2   7 dari 16  [#####-----]|  |   Cuti dokter; 3 booking aktif        | |
| | BPJS Kelas 3   0 dari 24  [----------]|  | Sen 12 Okt dr. Sinta M. - Peny. Dalam | |
| | VIP            2 dari 6   [###-------]|  |   Perubahan jam; 4 booking aktif      | |
| | Diperbarui: 02 Okt 2026 09.30 WIB     |  | [ Tindak lanjut (3) ]                 | |
| | (manual, bukan real-time)             |  +---------------------------------------+ |
| +---------------------------------------+                                            |
+--------------------------------------------------------------------------------------+
```

Kondisi grafik:

```text
+- Kondisi grafik -----------------------------------------------------------------+
| (!) Grafik tidak dapat dimuat.   [ Coba lagi ]        (permintaan JSON gagal)    |
| ... memuat grafik ...  (kerangka placeholder-glow, aria-busy=true)  (loading)    |
| Belum ada pendaftaran pada periode ini.                       (x-empty-state)    |
+----------------------------------------------------------------------------------+
```

**Elemen**

1. **`x-stat-card`** ×3: **Dokter aktif**, **Pasien terdaftar** (profil aktif), **Pendaftaran hari ini** (berdasarkan `session_date`).
2. **Pendaftaran per status** (enam angka dengan `x-status-badge`) untuk periode yang dipilih pada grafik **[ASUMSI]**.
3. **Grafik Chart.js**: jumlah pendaftaran per hari berdasarkan **tanggal kunjungan** (`session_date`); filter periode 7/30/90 hari atau rentang kustom (Dari–Sampai, maksimal 366 hari **[ASUMSI]**).
4. **Ringkasan kamar**: per kategori “N dari M” + bilah progres teks + **“Diperbarui: {waktu}”** (manual, bukan real-time).
5. **Sesi terdampak**: daftar sesi `needs_review` (tanggal, dokter, poliklinik, sumber, jumlah booking aktif) dengan tombol **Tindak lanjut** → `admin.sessions.review`.

Visibilitas widget menurut izin (00 bagian G):

| Widget | Syarat |
|---|---|
| Kartu angka, pendaftaran per status, grafik, ringkasan kamar | `dashboard.view` (data agregat; boleh untuk semua peran) |
| Daftar “Pendaftaran terbaru/hari ini” yang memuat nama pasien (bila diaktifkan) | `appointments.view` |
| Sesi terdampak | `sessions.review` (peran lain hanya melihat jumlahnya pada banner jadwal, bukan di sini) |

**Perilaku interaksi**

- Mengubah periode memanggil `admin.dashboard.chart?dari=&sampai=` (JSON) dan memperbarui grafik + angka per status; tanpa JavaScript, formulir periode memuat ulang halaman dengan query yang sama.
- Chart memiliki tooltip dan **tabel data alternatif** (`<details>` “Lihat data dalam tabel”).
- Klik angka status dapat membuka `admin.appointments.index` dengan filter status dan periode yang sama (tautan pada kartu) **[ASUMSI]**.

**Kondisi**

| Kondisi | Tampilan |
|---|---|
| Loading | Kerangka `placeholder-glow` pada grafik dan kartu angka JSON; `aria-busy="true"` |
| Kosong | Periode tanpa pendaftaran → `x-empty-state` “Belum ada pendaftaran pada periode ini”; ringkasan kamar kosong → “Belum ada kategori kamar aktif”; sesi terdampak kosong → panel disembunyikan |
| Berhasil | Angka dan grafik terisi; “Dimuat {jam}” |
| Gagal | Permintaan JSON gagal → “Grafik tidak dapat dimuat” + tombol **Coba lagi**; rentang kustom tidak valid → galat inline |

**Responsif**

| Lebar | Perilaku |
|---|---|
| ≥ 1200 px | Tiga kartu angka sebaris; ringkasan kamar dan sesi terdampak dua kolom |
| 768–1199 px | Kartu angka sebaris/dua baris; panel bawah dua kolom |
| < 768 px | Satu kolom; angka status 2 × 3; grafik setinggi 240 px dengan label sumbu X dirotasi/dijarangkan |

**Aksesibilitas**

- Grafik: `<canvas role="img" aria-label="Grafik pendaftaran per hari 5 sampai 11 Oktober 2026">` ditambah tabel alternatif; warna deret `$primary` dengan label nilai (tidak mengandalkan warna); garis kisi netral.
- `x-stat-card` berupa `<dl>` (judul-nilai) agar dibaca “Dokter aktif: 15”; angka status disertai label teks.
- Urutan fokus: filter periode → grafik (alternatif tabel) → ringkasan kamar → sesi terdampak (tombol Tindak lanjut).

---

## W-15 Pola x-data-table (contoh: daftar dokter)

**Halaman 33** (`admin.doctors.index`). Pola yang sama dipakai pada seluruh daftar admin (pasien, pendaftaran, templat jadwal, sesi, pengecualian, fasilitas, kamar, konten, staf, audit log) dan riwayat pasien (W-12).

### Desktop

```text
+--------------------------------------------------------------------------------------+
| Dasbor > Dokter                                                                      |
| DAFTAR DOKTER                              (h1)                    [ Tambah Dokter ] |
| +- TOOLBAR x-data-table (slot filter; form GET) -----------------------------------+ |
| | Cari [____________________]  Status [Aktif v]  Poliklinik [Semua v]              | |
| | Spesialisasi [Semua v]                          [ Terapkan ]  [ Reset ]          | |
| +----------------------------------------------------------------------------------+ |
| Menampilkan 1-15 dari 32 dokter                       Urutkan: [Nama v]              |
| +------------------------+--------------+-------------+------------+---------------+ |
| | Dokter                 | Spesialisasi | Poliklinik  | Status     | Aksi          | |
| +------------------------+--------------+-------------+------------+---------------+ |
| | {o} dr. Raka Wirawan,  | Urologi      | Poli        | [Aktif]    | [Lihat][Ubah] | |
| |     Sp.U               | Bedah Umum   | Urologi     |            | [...]         | |
| +------------------------+--------------+-------------+------------+---------------+ |
| | {o} dr. Anita Lestari, | Anak         | Poli Anak   | [Aktif]    | [Lihat][Ubah] | |
| |     Sp.A               |              |             |            | [...]         | |
| +------------------------+--------------+-------------+------------+---------------+ |
| | {o} drg. Bima Santoso  | Dokter Gigi  | Poli Gigi   | [Nonaktif] | [Lihat][Ubah] | |
| |                        |              |             |            | [...]         | |
| +------------------------+--------------+-------------+------------+---------------+ |
| Halaman 1 dari 3                                                 [<] [1] [2] [3] [>] |
+--------------------------------------------------------------------------------------+
```

### Mobile (kartu) dan pelengkap

```text
+--------------------------------+    +- Pelengkap --------------------+
| Dokter     [ Tambah Dokter ]   |    | Menu aksi baris [...]:         |
| [ Cari ______________ ]        |    |   Nonaktifkan                  |
| [ Filter (1 aktif)    v ]      |    |   Arsipkan                     |
| Menampilkan 1-15 dari 32       |    +--------------------------------+
| +----------------------------+ |    | Setiap aksi ubah-status        |
| | {o} dr. Raka Wirawan,      | |    | memakai x-confirm-modal.       |
| |     Sp.U                   | |    |                                |
| | Urologi, Bedah Umum        | |    | Kosong (x-empty-state):        |
| | Poli Urologi   [Aktif]     | |    |   Belum ada dokter             |
| | [Lihat] [Ubah] [...]       | |    |   yang cocok.                  |
| +----------------------------+ |    |   [ Tambah Dokter ]            |
| +----------------------------+ |    |   [ Reset filter  ]            |
| | {o} dr. Sinta Maharani,    | |    |                                |
| |     Sp.PD                  | |    | Loading: tombol Terapkan       |
| | Penyakit Dalam             | |    | ber-spinner; tabel             |
| | Poli Peny. Dalam [Aktif]   | |    | aria-busy=true.                |
| | [Lihat] [Ubah] [...]       | |    +--------------------------------+
| +----------------------------+ |
| [<] [1] [2] [3] [>]            |
+--------------------------------+
```

**Elemen**

1. `x-page-header`: judul + tombol **Tambah Dokter** (hanya dengan `doctors.manage`).
2. Toolbar `x-data-table`: kolom **pencarian** (`q`), slot filter (status, poliklinik, spesialisasi), tombol **Terapkan** dan **Reset**; teks jumlah hasil; pilihan **Urutkan** (`urut`, 00 I.5).
3. Tabel: foto kecil + nama tampilan, spesialisasi, poliklinik, status (`x-status-badge`), aksi (**Lihat**, **Ubah**, menu **…** berisi Nonaktifkan/Arsipkan).
4. Pagination Bootstrap 5 (15 baris per halaman **[ASUMSI]**) dan teks “Menampilkan 1–15 dari 32”.
5. Mobile (< 768 px): tiap baris menjadi kartu (foto + nama, spesialisasi, poliklinik, status, tombol aksi).

**Perilaku interaksi**

- Pencarian, filter, dan urutan memakai GET sehingga URL dapat disimpan; perubahan filter mengembalikan ke halaman 1; filter aktif tetap terlihat (chip atau nilai terpilih) dan dapat direset satu-satu.
- Aksi mengubah status (nonaktifkan, arsipkan) selalu melalui `x-confirm-modal` yang menyebut nama objek dan akibatnya. Modal **Nonaktifkan** menyebut jumlah sesi mendatang yang akan dibatalkan (tanpa booking aktif) dan yang menjadi Sesi terdampak (dengan booking aktif); tidak diblokir (00 F.4). Modal **Arsipkan** menyebut bahwa arsip hanya diizinkan bila tidak ada booking aktif mendatang; bila ada, tombol konfirmasi dinonaktifkan dengan teks alasan “selesaikan sesi terdampak dahulu”. Sukses → redirect + flash hijau (mis. “Dokter dinonaktifkan. N sesi perlu ditindaklanjuti petugas pendaftaran.” berwarna kuning).
- Tombol/menu aksi hanya dirender bila pengguna berizin (`@can`), tetapi tiap endpoint tetap diperiksa di server.
- Filter status bawaan “Aktif”; “Diarsipkan” dan “Nonaktif” tersedia lewat filter.

**Kondisi**

| Kondisi | Tampilan |
|---|---|
| Loading | Tombol **Terapkan** ber-spinner; wadah tabel `aria-busy="true"` selama permintaan |
| Kosong | `x-empty-state` “Belum ada dokter yang cocok” + **Reset filter**; bila data memang kosong, “Belum ada dokter” + **Tambah Dokter** (bila berizin) |
| Berhasil | Flash hijau setelah aksi (“Dokter dinonaktifkan”); baris menampilkan status terbaru |
| Gagal | Aksi ditolak (izin/status tidak valid) → flash merah; parameter filter tidak valid → diabaikan dengan pesan kecil “Filter tidak dikenali” |

**Responsif**

| Lebar | Perilaku |
|---|---|
| ≥ 768 px | Tabel penuh; toolbar satu–dua baris |
| < 768 px | Kartu per baris; toolbar menjadi pencarian + tombol “Filter (n aktif)” (accordion); aksi berupa tombol berdampingan |

**Aksesibilitas**

- Tabel memiliki `<caption>` visually-hidden “Daftar dokter”; kolom terurut menandai `aria-sort`; tombol urut berupa `<button>` dengan teks.
- Tombol menu aksi bernama “Aksi untuk dr. Raka Wirawan, Sp.U”; hasil pencarian diumumkan lewat `aria-live="polite"` (“Menampilkan 12 dari 32 dokter”).
- Kartu mobile mempertahankan urutan baca (nama → status → aksi); target sentuh ≥ 44 px; badge status teks + ikon + warna.

---

## W-16 Form dokter

**Layar tambahan** `admin.doctors.create` (simpan: `admin.doctors.store`) dan `admin.doctors.edit` (simpan: `admin.doctors.update`); induk halaman 33; izin `doctors.manage`.

### Desktop

```text
+--------------------------------------------------------------------------------------+
| Dasbor > Dokter > Tambah Dokter / Ubah                                               |
| FORM DOKTER                                                    (h1: Tambah Dokter)   |
| +- FOTO DOKTER --------------+ +- DATA DOKTER -------------------------------------+ |
| | +------------------------+ | | Nama (tanpa gelar) *                              | |
| | | {pratinjau foto}       | | | [Raka Wirawan________________]                    | |
| | |                        | | | Gelar depan              Gelar belakang           | |
| | |                        | | | [dr.________]            [Sp.U_______]            | |
| | |                        | | | Nama tampilan: dr. Raka Wirawan, Sp.U  (otomatis) | |
| | |                        | | | Jenis kelamin *    (o) Laki-laki   ( ) Perempuan  | |
| | |                        | | | Poliklinik utama *                                | |
| | +------------------------+ | | [Poli Urologi                       v]            | |
| | [ Pilih berkas ]           | | Spesialisasi * (centang; tepat satu Utama)        | |
| | foto-raka.jpg (412 KB)     | |   [x] Urologi               (o) Utama             | |
| | JPG/PNG/WebP, maks 2 MB,   | |   [x] Bedah umum            ( ) Utama             | |
| | min 300x300; SVG ditolak.  | |   [ ] Penyakit dalam        ( ) Utama             | |
| | Teks alternatif (alt) *    | |   ... 13 spesialisasi aktif (daftar dapat digulir)| |
| | [Foto dr. Raka Wirawan___] | | Profil singkat * (maks 500 karakter)       212/500| |
| | (!) Teks alternatif wajib  | | +----------------------------------------------+  | |
| |     diisi.                 | | |                                              |  | |
| | [ Ganti foto ]  [ Hapus ]  | | |                                              |  | |
| +----------------------------+ | +----------------------------------------------+  | |
|                                | Biodata lengkap (teks biasa)                      | |
|                                | +----------------------------------------------+  | |
|                                | |                                              |  | |
|                                | |                                              |  | |
|                                | |                                              |  | |
|                                | +----------------------------------------------+  | |
|                                | Status [Aktif v]              Urutan tampil [10__]| |
|                                | > SEO (opsional): meta title, meta description    | |
|                                | [ Simpan ]   [ Batal ]                            | |
|                                +---------------------------------------------------+ |
+--------------------------------------------------------------------------------------+
```

Galat dan sukses:

```text
+- Galat dan sukses ---------------------------------------------------------------+
| (!) Periksa isian berikut:          (x-form.errors-summary; fokus ke sini)       |
|     - Nama wajib diisi.                                                          |
|     - Pilih tepat satu spesialisasi sebagai Utama.                               |
|     - Foto: ukuran maksimal 2 MB; dimensi minimal 300x300 px.                    |
| (v) Dokter berhasil disimpan.                     (flash hijau)                  |
+----------------------------------------------------------------------------------+
```

**Elemen**

1. **Kartu foto**: pratinjau (`x-form.file` dengan pratinjau), tombol **Pilih berkas**, petunjuk format, kolom **teks alternatif (alt)** — wajib begitu ada foto, tombol **Ganti foto** dan **Hapus** (pada mode ubah).
2. **Nama (tanpa gelar)** (`name`), **gelar depan** (`title_prefix`, mis. `dr.`, `drg.`), **gelar belakang** (`title_suffix`, mis. `Sp.U`); **nama tampilan** otomatis `"{title_prefix} {name}, {title_suffix}"` sebagai pratinjau hanya-baca.
3. **Jenis kelamin** (`gender`: Laki-laki/Perempuan), **poliklinik utama** (`clinic_id`, hanya poliklinik aktif).
4. **Spesialisasi** multi-pilih (`doctor_specialties`): daftar centang dari `specialties` aktif, masing-masing dengan radio **Utama** (`is_primary`); minimal satu spesialisasi dan tepat satu Utama **[ASUMSI]**.
5. **Profil singkat** (`short_bio`, maks 500 karakter, penghitung) dan **biodata lengkap** (`bio`, teks biasa tanpa HTML).
6. **urutan tampil** (`sort_order`); **tidak ada field status** (dokter baru selalu `active`; aktif/nonaktif dan pemulihan lewat `admin.doctors.status`, arsip dari daftar/detail dengan konfirmasi, F2-R6/F2-R7); bagian lipat **SEO** (`meta_title` ≤ 70 (F2-R23), `meta_description` ≤ 160 karakter, dengan penghitung).
7. Tombol **Simpan** dan **Batal**.

**Perilaku interaksi**

- Mode tambah: berkas foto dikirim bersama formulir (multipart) dan diproses `MediaService` setelah validasi lolos. Mode ubah: kartu foto memiliki formulir sendiri (`admin.doctors.photo`) sehingga foto dapat diganti tanpa menyimpan seluruh data **[ASUMSI]**; penggantian membuat berkas + baris `media` baru dan menghapus yang lama setelah transaksi commit.
- Pratinjau foto ditampilkan segera di browser (FileReader); validasi sebenarnya di server: `jpg/jpeg/png/webp`, maks 2 MB, dimensi minimum per koleksi (`photo` 300×300) sampai maksimal 6000×6000 px; **SVG ditolak**; gambar dire-encode, EXIF dibuang, nama berkas dibuat sistem (UUID).
- Teks alternatif terisi saran “Foto {nama tampilan}” yang dapat diubah.
- Formulir tidak memiliki field status (F2-R6). Menonaktifkan dokter dilakukan dari aksi status di halaman detail (`admin.doctors.status`, dengan modal konfirmasi): pada fase 3 modal itu menampilkan peringatan bahwa sesi tanpa booking aktif akan dibatalkan dan sesi dengan booking aktif akan masuk Sesi terdampak (sumber `doctor_inactive`); tidak diblokir (00 F.4; 17 D-01). Pengarsipan (dari daftar/detail) hanya diizinkan bila tidak ada booking aktif mendatang (17 D-02).
- `slug` dibuat otomatis dari nama saat dibuat dan tidak pernah berubah saat ubah; tidak ada input slug (F2-R5).

**Kondisi**

| Kondisi | Tampilan |
|---|---|
| Loading | **Simpan** → spinner + “Menyimpan…”; unggah foto besar menampilkan teks “Mengunggah foto…” |
| Kosong | Mode tambah: semua field kosong; belum ada foto → pratinjau placeholder `doctor.svg` dengan teks “Belum ada foto” |
| Berhasil | Redirect ke `admin.doctors.index` (atau `show`) + flash hijau “Dokter berhasil disimpan”; audit log `doctor.created`/`doctor.updated` |
| Gagal | `x-form.errors-summary` + pesan per field (nama kosong, Utama tidak tepat satu, foto terlalu besar/dimensi/format, alt kosong); isian dipertahankan, kecuali berkas foto yang harus dipilih ulang (dinyatakan jelas) |

**Responsif**

| Lebar | Perilaku |
|---|---|
| ≥ 992 px | Dua kolom: kartu foto (4/12) dan data (8/12) |
| < 992 px | Satu kolom: kartu foto di atas, lalu data |
| < 576 px | Gelar depan/belakang bertumpuk; tombol Simpan dan Batal penuh-lebar |

**Aksesibilitas**

- Urutan fokus: foto (pilih berkas → alt → ganti/hapus) → nama → gelar → jenis kelamin → poliklinik → spesialisasi (centang lalu radio Utama) → profil → biodata → status → SEO → Simpan/Batal.
- Pilih berkas memakai `<input type="file">` berlabel “Foto dokter”; alt wajib diberi tanda “wajib” teks; kelompok jenis kelamin dan spesialisasi memakai `<fieldset><legend>`; radio Utama berlabel “Jadikan Urologi spesialisasi utama”.
- Penghitung karakter `aria-live="polite"` yang tidak terlalu sering (diumumkan saat mendekati batas); ringkasan galat menerima fokus; kontras tombol sesuai token.

---

## W-17 Jadwal admin

**Halaman 35** (`admin.schedules.index`) dengan tab **Templat Mingguan**, **Sesi per Tanggal** (`admin.sessions.index`), **Pengecualian (Cuti dan Libur)** (`admin.exceptions.index`); form templat `admin.schedules.create`/`edit` (induk 35).

### Tab Templat Mingguan

```text
+--------------------------------------------------------------------------------------+
| Dasbor > Jadwal Praktik > Templat Mingguan                                           |
| JADWAL PRAKTIK                                    (h1)            [ Tambah Templat ] |
| [ Templat Mingguan ]   Sesi per Tanggal   Pengecualian (Cuti dan Libur)              |
+--------------------------------------------------------------------------------------+
| +- Peringatan setelah simpan (kuning) ---------------------------------------------+ |
| | (!) 2 sesi perlu ditindaklanjuti petugas pendaftaran.      <Buka Sesi Terdampak> | |
| +----------------------------------------------------------------------------------+ |
| Dokter [Semua v]   Hari [Semua v]   Status [Semua v]   [ Terapkan ]   [ Reset ]      |
| +--------------+-------+-------------+-------+--------------+------------+---------+ |
| | Dokter       | Hari  | Jam         | Kuota | Berlaku      | Status     | Aksi    | |
| +--------------+-------+-------------+-------+--------------+------------+---------+ |
| | dr. Raka W.  | Sen   | 09.00-12.00 | 20    | 5 Okt 26 -   | [Aktif]    | [Ubah]  | |
| | Poli Urologi |       |             |       | (terbuka)    |            |         | |
| +--------------+-------+-------------+-------+--------------+------------+---------+ |
| | dr. Sinta M. | Sen   | 10.00-14.00 | 20    | 5 Okt 26 -   | [Aktif]    | [Ubah]  | |
| | Peny. Dalam  |       |             |       | (terbuka)    |            |         | |
| +--------------+-------+-------------+-------+--------------+------------+---------+ |
| | dr. Sinta M. | Sel   | 10.00-14.00 | 20    | 5 Okt 26 -   | [Aktif]    | [Ubah]  | |
| | Peny. Dalam  |       |             |       | (terbuka)    |            |         | |
| +--------------+-------+-------------+-------+--------------+------------+---------+ |
| | dr. Anita L. | Sel   | 13.00-16.00 | 15    | 5 Okt 26 -   | [Nonaktif] | [Ubah]  | |
| | Poli Anak    |       |             |       | 31 Des 26    |            |         | |
| +--------------+-------+-------------+-------+--------------+------------+---------+ |
| Menampilkan 1-4 dari 4 templat                                           [<] [1] [>] |
+--------------------------------------------------------------------------------------+
```

### Form templat (tambah/ubah)

```text
+--------------------------------------------------------------------------------------+
| Dasbor > Jadwal Praktik > Templat Mingguan > Tambah Templat                          |
+--------------------------------------------------------------------------------------+
| +- FORM TEMPLAT -------------------------------------------------------------------+ |
| | Dokter *                                  Berlaku dari *                         | |
| | [dr. Raka Wirawan, Sp.U          v]       [05/10/2026]                           | |
| | Poliklinik *                              Berlaku sampai (opsional)              | |
| | [Poli Urologi                    v]       [__/__/____]                           | |
| | Hari praktik *                            Catatan (opsional)                     | |
| | [Senin                           v]       [______________________________]       | |
| | Jam mulai *         Jam selesai *         Status                                 | |
| | [09:00]             [12:00]               [x] Templat aktif                      | |
| | Kuota (1-200) *                                                                  | |
| | [20____]                                                                         | |
| | (!) Jam selesai harus setelah jam mulai.                     (contoh galat)      | |
| | (!) Bertabrakan dengan templat aktif lain: Senin 09.00-12.00.                    | |
| | [ Simpan Templat ]   [ Batal ]                                                   | |
| +----------------------------------------------------------------------------------+ |
| +- Flash setelah simpan (ScheduleChangeService) -----------------------------------+ |
| | (v) Templat disimpan. 12 sesi dibuat; 1 sesi disesuaikan kuotanya;               | |
| |     2 sesi perlu ditindaklanjuti petugas pendaftaran.                            | |
| +----------------------------------------------------------------------------------+ |
+--------------------------------------------------------------------------------------+
```

### Tab Sesi per Tanggal dan Pengecualian

```text
+--------------------------------------------------------------------------------------+
| TAB: SESI PER TANGGAL                                                [ Tambah Sesi ] |
| Dari [__/__/____] Sampai [__/__/____] Dokter [Semua v] Status [Semua v] [ Terapkan ] |
| +------------+--------------+-------------+---------+---------------+---------+      |
| | Tanggal    | Dokter       | Jam         | Terisi  | Status        | Aksi    |      |
| +------------+--------------+-------------+---------+---------------+---------+      |
| | 5 Okt 26   | dr. Raka W.  | 09.00-12.00 | 15/20   | [Dibuka]      | [Ubah]  |      |
| |            | Urologi      |             |         |               |         |      |
| +------------+--------------+-------------+---------+---------------+---------+      |
| | 7 Okt 26   | dr. Raka W.  | 09.00-12.00 | 0/20    | [Dibatalkan]  | [Ubah]  |      |
| |            | Urologi      |             |         | Cuti dokter   |         |      |
| +------------+--------------+-------------+---------+---------------+---------+      |
| | 14 Okt 26  | dr. Raka W.  | 09.00-12.00 | 3/20    | [Perlu tindak | [Ubah]  |      |
| |            | Urologi      |             |         | lanjut]       |         |      |
| +------------+--------------+-------------+---------+---------------+---------+      |
| Admin Konten hanya melihat jumlah terisi, tanpa nama pasien.                         |
+--------------------------------------------------------------------------------------+
| TAB: PENGECUALIAN (CUTI DAN LIBUR)                           [ Tambah Pengecualian ] |
| +-------------------+--------------+----------------+--------------+---------------+ |
| | Jenis             | Dokter       | Tanggal        | Alasan       | Aksi          | |
| +-------------------+--------------+----------------+--------------+---------------+ |
| | Cuti dokter       | dr. Raka W.  | 7 Okt 2026     | Cuti tahunan | [Ubah][Hapus] | |
| +-------------------+--------------+----------------+--------------+---------------+ |
| | Libur rumah sakit | Semua dokter | 20 Okt 2026    | Hari libur   | [Ubah][Hapus] | |
| +-------------------+--------------+----------------+--------------+---------------+ |
| Form pengecualian: Jenis, Dokter (atau Semua dokter), Mulai, Selesai, Alasan.        |
| Tanggal mulai tidak boleh di masa lalu; rentang dihitung per hari penuh.             |
+--------------------------------------------------------------------------------------+
```

**Elemen**

1. `x-page-header` dengan tombol sesuai tab (Tambah Templat / Tambah Sesi / Tambah Pengecualian; hanya dengan `schedules.manage`).
2. **Tab** (`nav-tabs`): Templat Mingguan · Sesi per Tanggal · Pengecualian (Cuti dan Libur); tab aktif `aria-current="page"`.
3. **Peringatan** (kuning) “N sesi perlu ditindaklanjuti petugas pendaftaran”: tampil bila ada sesi `needs_review` (atau setelah simpan yang menimbulkannya); dengan `sessions.review` disertai tautan **Buka Sesi Terdampak**, tanpa izin itu hanya teks (00 bagian G).
4. Tabel templat: dokter + poliklinik, hari, jam mulai–selesai, kuota, berlaku dari–sampai, status aktif (`x-status-badge`), aksi **Ubah** (aktif/nonaktifkan dari halaman ubah atau menu baris).
5. Form templat: dokter, poliklinik (bawaan = poliklinik utama dokter), hari praktik (Senin–Minggu), jam mulai/selesai, kuota (1–200), berlaku dari, berlaku sampai (opsional), catatan, status aktif (`x-form.checkbox` varian *switch*).
6. Tab Sesi: tanggal, dokter, jam, **terisi/kuota (hanya jumlah)**, status sesi + alasan, aksi **Ubah** (jam, kuota, tutup/buka/batalkan) dan **Hapus** hanya untuk sesi tambahan manual tanpa appointment sama sekali (`admin.sessions.destroy`, 00 I.4, F3-R20) dan **Tambah Sesi** manual (`doctor_schedule_id` NULL).
7. Tab Pengecualian: jenis (Cuti dokter, Libur rumah sakit, Libur nasional, Lainnya), dokter (wajib untuk Cuti dokter; harus kosong untuk Libur rumah sakit dan Libur nasional = seluruh RS; opsional untuk Lainnya), tanggal mulai–selesai, alasan; **Ubah**, **Hapus** (mencabut pengecualian lewat `ScheduleChangeService::removeException`).

**Perilaku interaksi**

- Simpan templat memicu `PracticeSessionGenerator` dan `ScheduleChangeService`; flash menyebut ringkasan dampak (sesi dibuat, kuota disesuaikan, sesi perlu ditindaklanjuti). Ringkasan perilaku (00 bagian F.4):

| Perubahan templat | Dampak pada sesi mendatang berasal dari templat itu (`is_manually_modified = false`) |
|---|---|
| Sesi tanpa appointment sama sekali | Dihapus lalu digenerate ulang |
| Sesi hanya punya riwayat batal | Diperbarui di tempat, atau `cancelled` bila tidak valid lagi |
| Kuota saja (ada appointment aktif) | `max(kuota_baru, booked_count)`; admin diberi tahu jumlah sesi yang disesuaikan |
| Jam, hari, penonaktifan, atau penyempitan rentang berlaku (ada appointment aktif) | Sesi menjadi `needs_review` dengan `pending_change` |
| Ganti poliklinik dengan appointment aktif mendatang | Ditolak: buat templat baru lalu nonaktifkan yang lama |

- Edit manual satu sesi yang punya appointment aktif (00 F.4; 17 D-03): perubahan kuota langsung berlaku (tidak boleh < `booked_count`); geser jam → sesi `needs_review` sumber `manual_time`; pembatalan sesi → sumber `manual_cancel` dengan alasan wajib. Sesi tanpa appointment aktif langsung diubah atau dibatalkan. Layar menampilkan flash “N sesi perlu ditindaklanjuti petugas pendaftaran”.
- Dokter dinonaktifkan (dari W-15/W-16): sesi mendatang tanpa appointment aktif → `cancelled`; dengan appointment aktif → `needs_review` sumber `doctor_inactive`.
- Validasi templat: jam selesai > jam mulai; templat aktif seorang dokter pada hari yang sama tidak boleh beririsan jam pada rentang berlaku yang beririsan; kuota 1–200.
- Pengecualian: granularitas satu hari penuh **[ASUMSI]**; tanggal mulai tidak boleh di masa lalu; sesi dalam rentang tanpa appointment aktif → `cancelled`, dengan appointment aktif → `needs_review`; menghapus/mempersempit membuka kembali sesi yang dibatalkan olehnya dan belum lewat. Hapus memakai `x-confirm-modal`.
- Tab Sesi: edit manual menyetel `is_manually_modified = true`; kuota tidak boleh di bawah `booked_count`; tombol Hapus sesi hanya untuk sesi tambahan manual tanpa appointment sama sekali (`admin.sessions.destroy`); sesi lain ditutup atau dibatalkan, dan sesi hasil templat atau sesi ber-appointment menampilkan saran "Tutup/Batalkan" (F3-R20). Templat dihapus hanya bila tidak pernah menghasilkan sesi ber-appointment, selain itu dinonaktifkan.
- Admin Konten tidak melihat nama pasien; hanya jumlah booking per sesi.

**Kondisi**

| Kondisi | Tampilan |
|---|---|
| Loading | **Simpan Templat** → spinner (proses generate bisa beberapa detik); tabel `aria-busy` saat filter |
| Kosong | `x-empty-state` “Belum ada templat jadwal” + **Tambah Templat** (berizin); tab Pengecualian kosong → “Belum ada cuti atau libur terdaftar” |
| Berhasil | Flash hijau berisi ringkasan dampak; peringatan kuning bila ada sesi perlu tindak lanjut |
| Gagal | Galat validasi (jam, irisan templat, kuota, `start_date` masa lalu, ganti poliklinik ditolak) pada ringkasan + field |

**Responsif**

| Lebar | Perilaku |
|---|---|
| ≥ 768 px | Tabel penuh; tab horizontal |
| < 768 px | Tabel menjadi kartu; tab dapat digulir horizontal; form satu kolom |

**Aksesibilitas**

- Tab memakai `<ul class="nav nav-tabs">` dengan tautan nyata (tiap tab punya URL) dan `aria-current`; judul tab aktif diulang di `<h1>`/`<h2>`.
- Peringatan sesi terdampak `role="status"` dengan ikon (!) + teks; hari memakai nama lengkap pada pembaca layar (“Senin”) walau tampil “Sen”.
- Kolom jam `type="time"` dengan label “Jam mulai”/“Jam selesai”; switch aktif berlabel “Templat aktif”; kontras badge status sesuai bagian 7.

---

## W-18 Sesi terdampak

**Layar tambahan** `admin.sessions.review` (aksi simpan: `admin.sessions.resolve`); induk halaman 35 dan 32; izin `sessions.review` (Super Admin, Petugas Pendaftaran).

### Desktop

```text
+--------------------------------------------------------------------------------------+
| Dasbor > Jadwal Praktik > Sesi Terdampak                                             |
| SESI TERDAMPAK                (h1)                                 Sumber: [Semua v] |
| Sesi berstatus needs_review tertutup untuk booking baru; booking lama tetap berlaku. |
| +- SESI 1 DARI 4 ------------------------------------------------------------------+ |
| | Sen, 12 Okt 2026  jam baru 11.00-14.00 (lama 10.00-14.00) [Perlu tindak lanjut]  | |
| | dr. Sinta Maharani, Sp.PD - Poli Penyakit Dalam                                  | |
| | Sumber perubahan: Perubahan jam templat (template_time)                          | |
| | Booking aktif: 4   <Tampilkan daftar>   (nama pasien: izin appointments.view)    | |
| | +------------------------------------------------------------------------------+ | |
| | | (!) Jam baru berbenturan dengan booking lain milik 1 pasien:                 | | |
| | |     Dewi Lestari (UPH-4D8F-2HNB), Poli Anak, Sen 12 Okt 11.30-12.30.         | | |
| | +------------------------------------------------------------------------------+ | |
| | Resolusi *  (o) Terapkan perubahan jam (apply)   ( ) Pertahankan (keep)          | |
| |             ( ) Batalkan sesi dan seluruh booking aktif (cancel)                 | |
| | Alasan *    [Jadwal praktik diubah; pasien diberi tahu lewat notifikasi______]   | |
| | [ Simpan resolusi ]                                                              | |
| +----------------------------------------------------------------------------------+ |
| +- SESI 2 DARI 4 ------------------------------------------------------------------+ |
| | Rab, 14 Okt 2026   09.00-12.00                            [Perlu tindak lanjut]  | |
| | dr. Raka Wirawan, Sp.U - Poli Urologi                                            | |
| | Sumber perubahan: Pengecualian - Cuti dokter, 14-16 Okt 2026 (exception)         | |
| | Booking aktif: 3                                                                 | |
| | Resolusi *  ( ) Pertahankan (keep)   (o) Batalkan sesi (cancel)                  | |
| | Alasan *    [Dokter cuti; mohon hubungi loket untuk jadwal pengganti__________]  | |
| | [ Simpan resolusi ]                                                              | |
| +----------------------------------------------------------------------------------+ |
| +- SESI 3 DARI 4 (diringkas) ------------------------------------------------------+ |
| | Sel, 20 Okt 2026   13.00-16.00                            [Perlu tindak lanjut]  | |
| | dr. Anita Lestari, Sp.A - Poli Anak       Booking aktif: 2                       | |
| | Sumber: Templat dinonaktifkan (template_removed)                                 | |
| | Pilihan resolusi: keep atau cancel (tanpa apply)                                 | |
| +----------------------------------------------------------------------------------+ |
| +- SESI 4 DARI 4 (diringkas) ------------------------------------------------------+ |
| | Kam, 22 Okt 2026   08.00-11.00                            [Perlu tindak lanjut]  | |
| | dr. Bima Santoso, Sp.U - Poli Urologi     Booking aktif: 1                       | |
| | Sumber: Dokter dinonaktifkan (doctor_inactive)                                   | |
| | Pilihan resolusi: cancel (keep hanya bila dokter diaktifkan kembali)             | |
| +----------------------------------------------------------------------------------+ |
+--------------------------------------------------------------------------------------+
```

Konfirmasi pembatalan dan daftar booking aktif:

```text
+- x-confirm-modal (aksi cancel) ----------+  +- Dibuka dari Tampilkan daftar -------+
| Batalkan sesi dan 3 booking aktif?       |  | Daftar booking aktif sesi 2          |
| - Booking aktif menjadi Dibatalkan       |  | URO-003 UPH-2X6R-9CEF Agus Salim     |
|   (oleh staf; alasan dikirim ke pasien). |  |         BPJS - Dikonfirmasi          |
| - Kuota dilepas; sesi menjadi Dibatalkan.|  | URO-004 UPH-6B1V-4YKA Rina Wati      |
| - Notifikasi dikirim ke pasien berakun;  |  |         Asuransi - Menunggu          |
|   pasien walk-in dihubungi lewat telepon.|  | URO-005 UPH-9Q3D-7MPT Budi Santoso   |
| [ Kembali ]       [ Ya, batalkan sesi ]  |  |         Umum - Dikonfirmasi          |
+------------------------------------------+  +--------------------------------------+
```

Kondisi kosong:

```text
+- Kondisi kosong -----------------------------------------------------------------+
| Belum ada sesi yang perlu ditindaklanjuti.                                       |
| Semua sesi sudah sesuai jadwal.                              (x-empty-state)     |
+----------------------------------------------------------------------------------+
```

**Elemen**

1. Penjelasan singkat: sesi `needs_review` **tertutup untuk booking baru**; booking lama tetap berlaku sampai ditindaklanjuti.
2. Kartu per sesi: tanggal + jam (dengan jam lama/baru bila `template_time` atau `manual_time`), dokter, poliklinik, badge “Perlu tindak lanjut”, **sumber perubahan**, **jumlah booking aktif**, tautan **Tampilkan daftar** (kode, nama pasien, antrean, status — hanya bila `appointments.view`); pasien walk-in tanpa akun diberi tanda “walk-in” beserta telepon agar petugas menghubunginya (tanpa penanda “sudah dihubungi”, default K-15).
3. **Peringatan benturan** pada opsi apply: bila jam baru berbenturan dengan booking lain milik pasien yang sama, layar menyebut pasien, kode booking, dan jamnya (peringatan, tidak memblokir).
4. **Pilihan resolusi** per sumber (`pending_change.source`, enam sumber, 00 bagian F.4):

| Sumber | Pilihan |
|---|---|
| `exception` (cuti/libur) | `keep` (Pertahankan sesi) · `cancel` (Batalkan sesi) |
| `template_time` (jam templat berubah) | `keep` · `apply` (Terapkan perubahan jam) · `cancel` |
| `template_removed` (hari berubah, templat dinonaktifkan, atau di luar rentang berlaku) | `keep` · `cancel` |
| `manual_time` (jam sesi digeser manual) | `keep` (jam semula dipertahankan) · `apply` (jam baru berlaku) · `cancel` |
| `manual_cancel` (sesi dibatalkan manual) | `keep` (sesi tetap berjalan) · `cancel` |
| `doctor_inactive` (dokter dinonaktifkan) | `keep` hanya bila dokter sudah `active` kembali; selain itu wajib `cancel` (alasan dikirim ke pasien) |

Satu sesi hanya menyimpan satu sumber: sumber jenis-batal (`exception`, `template_removed`, `manual_cancel`, `doctor_inactive`) mengalahkan sumber jenis-jam (`template_time`, `manual_time`); pada tingkat yang sama sumber terbaru menggantikan yang lama (00 F.4; [06 bagian 11.F](06-alur.md)).

5. Kolom **alasan** (wajib untuk semua resolusi, min. 5 karakter **[ASUMSI]**; pada `cancel` menjadi `cancel_reason` yang dikirim ke pasien) dan tombol **Simpan resolusi**.

**Perilaku interaksi**

- `keep` → sesi tetap bernilai lama, status `open`, `is_manually_modified = true` (sumber `doctor_inactive`: hanya bila dokter sudah aktif kembali). `apply` → jam diperbarui, status `open`, seluruh appointment aktif diberi `schedule_changed_at = now()` dan notifikasi `AppointmentScheduleChangedNotification`. `cancel` → sesi `cancelled`; seluruh appointment aktif `cancelled` (`cancelled_by_type = staff`), kuota dilepas, notifikasi dikirim; petugas dapat membuat booking pengganti lewat Walk-in (W-20).
- `cancel` selalu melalui `x-confirm-modal` yang merinci akibatnya; `apply` dan `keep` tanpa modal tetapi dengan ringkasan di tombol.
- Urutan penguncian baku `patient_profiles` → `practice_sessions` → `appointments` dipakai saat resolusi (mencegah deadlock).
- Daftar dapat disaring berdasarkan sumber; urut default tanggal sesi terdekat.

**Kondisi**

| Kondisi | Tampilan |
|---|---|
| Loading | **Simpan resolusi** → spinner; kartu yang sedang diproses dinonaktifkan |
| Kosong | `x-empty-state` “Belum ada sesi yang perlu ditindaklanjuti” |
| Berhasil | Flash hijau “Sesi 12 Okt 2026 diselesaikan: jam baru diterapkan, 4 pasien diberi tahu”; kartu hilang dari daftar; lencana sidebar berkurang |
| Gagal | Alasan kosong → galat pada field; sesi sudah diselesaikan petugas lain → flash “Sesi sudah ditindaklanjuti” dan daftar dimuat ulang; kegagalan transaksi → tidak ada perubahan sebagian (rollback) |

**Responsif**

| Lebar | Perilaku |
|---|---|
| ≥ 992 px | Kartu lebar penuh; pilihan resolusi sebaris |
| < 992 px | Pilihan resolusi bertumpuk (radio); tombol Simpan penuh-lebar |

**Aksesibilitas**

- Setiap kartu berupa `<section aria-labelledby>` dengan judul tanggal-dokter; radio resolusi dalam `<fieldset><legend>Resolusi</legend>`; peringatan benturan `role="alert"` dengan ikon (!) dan teks.
- Modal pembatalan: judul menyebut jumlah booking; tombol bahaya memakai `$danger` dengan teks putih (±4,5:1) dan fokus awal pada “Kembali”.
- Daftar booking aktif berupa tabel kecil dengan `<th scope>`; nama pasien hanya dirender untuk pengguna berizin `appointments.view`.

---

## W-19 Antrean hari ini

**Layar tambahan** `admin.queue.index` (tab “Antrean Hari Ini” modul 38); aksi `admin.appointments.transition` dan `admin.queue.mark-no-show`; izin lihat `appointments.view`, aksi `appointments.manage`.

### Desktop

```text
+--------------------------------------------------------------------------------------+
| Dasbor > Pendaftaran > Antrean Hari Ini                                              |
| ANTREAN HARI INI          (h1)                      Dimuat 10.42 WIB  [ Muat ulang ] |
| +- PILIH SESI DAN CARI ------------------------------------------------------------+ |
| | Tanggal [02/10/2026]   [ Tampilkan ]                                             | |
| | Sesi [URO - dr. Raka W. - 09.00-12.00 - 7/20 terisi          v]                  | |
| | Cari atau pindai kode booking: [UPH-____-____]  [ Cari ]       (fokus awal)      | |
| +----------------------------------------------------------------------------------+ |
| +---------+---------------+--------------+----------+--------------+--------------+  |
| | Antrean | Kode booking  | Pasien       | Bayar    | Status       | Aksi         |  |
| +---------+---------------+--------------+----------+--------------+--------------+  |
| | URO-001 | UPH-5G2K-7NWD | Budi Santoso | Umum     | Selesai      | -            |  |
| |         |               | P000045      |          |              |              |  |
| +---------+---------------+--------------+----------+--------------+--------------+  |
| | URO-002 | UPH-8M4T-1ZQH | Dewi Lestari | BPJS     | Hadir /      | [Selesai]    |  |
| |         |               | P000051      |          | check-in     | [Koreksi]    |  |
| +---------+---------------+--------------+----------+--------------+--------------+  |
| | URO-003 | UPH-2X6R-9CEF | Agus Salim   | BPJS     | Dikonfirmasi | [Check-in]   |  |
| |         |               | P000062      |          |              | [Batal]      |  |
| +---------+---------------+--------------+----------+--------------+--------------+  |
| | URO-004 | UPH-6B1V-4YKA | Rina Wati    | Asuransi | Menunggu     | [Konfirmasi] |  |
| |         |               | P000070      |          | konfirmasi   | [Check-in]   |  |
| |         |               |              |          |              | [Batal]      |  |
| +---------+---------------+--------------+----------+--------------+--------------+  |
| | URO-005 | UPH-9Q3D-7MPT | Hadi Gunawan | Umum     | Dibatalkan   | -            |  |
| |         |               | P000081      |          |              |              |  |
| +---------+---------------+--------------+----------+--------------+--------------+  |
| Urut nomor antrean; nomor yang dibatalkan tidak dipakai ulang. Terisi 7 dari 20.     |
| [Check-in] nonaktif sebelum jam dibuka, dengan teks 'Dibuka 08.00' di sampingnya.    |
+--------------------------------------------------------------------------------------+
```

Setelah sesi berakhir dan konfirmasi batal:

```text
+- Setelah sesi berakhir --------------+    +- x-confirm-modal (Batal) ------------+
| (!) Sesi telah berakhir pukul 12.00. |    | Batalkan URO-003?                    |
| 3 pendaftaran belum check-in         |    | Alasan * [__________________]        |
| (Menunggu / Dikonfirmasi).           |    | (wajib; dikirim ke pasien)           |
| [ Tandai tidak hadir (3) ]           |    | [ Kembali ]   [ Ya, batalkan ]       |
+--------------------------------------+    +--------------------------------------+
```

**Elemen**

1. Pemilih **tanggal** (bawaan hari ini) dan **sesi** (poliklinik, dokter, jam, “terisi/kuota”); tombol **Tampilkan**.
2. Kolom **cari atau pindai kode booking** (autofokus; pemindai USB berperilaku sebagai keyboard dan mengetik kode + Enter; **tanpa API kamera**) beserta tombol **Cari**.
3. Tabel antrean **urut nomor antrean** (`{clinics.code}-{3 digit}`): nomor, kode booking, pasien (nama + nomor pasien), pembayaran, status (`x-status-badge`), aksi.
4. Aksi per baris menurut status (00 bagian F.8): `pending` → **Konfirmasi**, **Check-in** (langsung), **Batal**; `confirmed` → **Check-in**, **Batal**; `checked_in` → **Selesai**, **Koreksi** (kembali ke `confirmed`, hari yang sama, alasan wajib); status final → tanpa aksi.
5. Ringkasan jumlah per status dan “Terisi N dari kuota”.
6. Banner **setelah sesi berakhir**: “Sesi telah berakhir. N pendaftaran belum check-in” dengan tombol **Tandai tidak hadir (N)** (aksi massal per sesi → `no_show` untuk `pending`/`confirmed`).
7. “Dimuat {jam}” + tombol **Muat ulang**.

**Perilaku interaksi**

- Mencari kode: GET `admin.queue.index?tanggal=&sesi=&kode=`. Kode ditemukan → baris disorot dan, bila milik sesi lain pada tanggal yang sama, halaman berpindah ke sesi tersebut; kode tidak ditemukan → pesan “Kode booking tidak ditemukan”; hasil juga dapat langsung diberi aksi **Check-in**.
- **Check-in** hanya aktif pada `session_date` dari `start − booking.checkin_open_minutes` sampai `end_time`; di luar rentang tombol nonaktif dan teks di sampingnya menyebut jam dibuka (“Dibuka 08.00”) — **tidak hanya tooltip**.
- **Batal** dan **Koreksi** membuka `x-confirm-modal` dengan alasan wajib (dikirim ke pasien berakun); setiap transisi menulis `appointment_status_histories`, `audit_logs`, dan notifikasi pasien. Pasien walk-in dihubungi petugas lewat telepon yang tercatat.
- Transisi memeriksa status terkini di server; bila sudah berubah (petugas lain) tampil “Status sudah berubah, muat ulang”.
- Halaman **tidak** memperbarui otomatis; petugas memakai **Muat ulang** **[ASUMSI]**. Tombol dan tautan hanya muncul bila `appointments.manage`.

**Kondisi**

| Kondisi | Tampilan |
|---|---|
| Loading | Tombol aksi baris `x-loading-button`; baris diberi `aria-busy` selama permintaan |
| Kosong | Belum ada sesi pada tanggal ini → `x-empty-state` “Tidak ada sesi praktik pada tanggal ini”; sesi tanpa pendaftaran → “Belum ada pendaftaran pada sesi ini” |
| Berhasil | Flash hijau “URO-003 check-in berhasil”; baris diperbarui dan badge berganti |
| Gagal | Transisi tidak valid/terlalu dini → flash merah dengan penjelasan (mis. “Check-in dibuka mulai 08.00”); status berubah oleh pihak lain → pesan muat ulang; kode tidak ditemukan |

**Responsif**

| Lebar | Perilaku |
|---|---|
| ≥ 768 px | Tabel antrean penuh; kolom pencarian di atas |
| < 768 px | Tiap antrean menjadi kartu (nomor besar, nama, status, tombol aksi bertumpuk); pencarian tetap di atas dan sticky |

**Aksesibilitas**

- Kolom pencarian berlabel “Kode booking” (visible), `autofocus`, `autocomplete="off"`; hasil dan galat diumumkan lewat `aria-live`.
- Tabel `<th scope="col">`; tombol aksi bernama jelas (“Check-in URO-003 Agus Salim”); tombol nonaktif tetap berada dalam urutan fokus lewat `aria-disabled="true"` agar alasan terbaca.
- Banner tidak hadir `role="status"`; aksi massal memakai modal dengan jumlah yang akan terdampak; kontras badge status dan tombol sesuai token.

---

## W-20 Form walk-in

**Layar tambahan** `admin.appointments.create` (simpan: `admin.appointments.store`); induk halaman 38; izin `appointments.manage`. Alur: cari atau buat profil pasien → pilih sesi → data pembayaran (00 bagian F.9). Berjalan sebagai satu halaman bertahap lewat query (`pasien`, `sesi`) sehingga berfungsi tanpa JavaScript **[ASUMSI]**.

### Formulir

```text
+--------------------------------------------------------------------------------------+
| Dasbor > Pendaftaran > Pendaftaran Walk-in                                           |
| PENDAFTARAN WALK-IN                                  (h1)                            |
| +- 1. PASIEN ----------------------------------------------------------------------+ |
| | Cari pasien (nama, no. pasien, telepon, atau tanggal lahir):                     | |
| | [siti____________________]  [ Cari ]                                             | |
| | +------------------+------------+----------------+------------+-----------+      | |
| | | Nama             | No. pasien | Telepon        | Lahir      | Aksi      |      | |
| | +------------------+------------+----------------+------------+-----------+      | |
| | | Siti Rahma       | P000123    | 0812-0000-0000 | 12/03/1990 | [Pilih]   |      | |
| | +------------------+------------+----------------+------------+-----------+      | |
| | | Siti Rahmawati   | P000208    | 0813-0000-0000 | 03/07/1985 | [Pilih]   |      | |
| | +------------------+------------+----------------+------------+-----------+      | |
| | Tidak ditemukan?  [ Buat profil baru ]  (lalu kembali ke formulir ini)           | |
| | Terpilih: Siti Rahma (P000123), lahir 12/03/1990, BPJS *********3456  <Ganti>    | |
| +----------------------------------------------------------------------------------+ |
| +- 2. SESI ------------------------------------------------------------------------+ |
| | Poliklinik [Poli Urologi v]   Dokter [dr. Raka W. v]   Tanggal [05/10/2026]      | |
| | (o) 09.00-12.00   Sisa 5 dari 20     (v) Tersedia                                | |
| | ( ) 13.00-15.00   Sisa 6 dari 20     (i) Online ditutup; loket masih bisa        | |
| | ( ) 16.00-18.00   Sisa 0 dari 15     (x) Penuh                                   | |
| | Loket boleh mendaftar sampai jam selesai sesi (sumber: DoctorAvailabilityQuery). | |
| +----------------------------------------------------------------------------------+ |
| +- 3. DATA PEMBAYARAN -------------------------------------------------------------+ |
| | Jenis pembayaran *   (o) Umum / Pribadi   ( ) BPJS Kesehatan   ( ) Asuransi      | |
| | (field nomor BPJS atau mitra asuransi muncul sesuai pilihan; kosong untuk Umum)  | |
| | Keluhan singkat (opsional)  [_____________________________________________]      | |
| | Kanal: Loket   Status awal: Dikonfirmasi   Benturan jadwal tetap diperiksa.      | |
| | [ Simpan Pendaftaran ]   [ Batal ]                                               | |
| +----------------------------------------------------------------------------------+ |
+--------------------------------------------------------------------------------------+
```

Pembuatan profil walk-in (`admin.patients.create`):

```text
+- admin.patients.create (profil walk-in) -----------------------------------------+
| Nama lengkap * [__________]   Tanggal lahir * [__/__/____]                       |
| Jenis kelamin * (o) L ( ) P   Telepon * [__________]   Email [____________]      |
| Alamat * [____________________________________]                                  |
| Nomor BPJS (opsional) [_____________]                                            |
| [x] Persetujuan privasi lisan telah dibacakan dan disetujui pasien. *            |
| [ Simpan Profil ]   [ Batal ]        (tanpa NIK; tanpa akun login)               |
+----------------------------------------------------------------------------------+
```

**Elemen**

1. **Bagian 1 — Pasien**: pencarian (nama, nomor pasien, telepon, tanggal lahir) dengan hasil berupa tabel dan tombol **Pilih**; tautan **Buat profil baru** (ke `admin.patients.create?kembali={path internal formulir ini}`, divalidasi `SafeRedirect`; setelah simpan kembali ke formulir ini dengan `pasien` terisi; 00 I.5); ringkasan pasien terpilih (BPJS tersamar 4 digit terakhir).
2. **Bagian 2 — Sesi**: poliklinik → dokter → tanggal → daftar sesi dari `DoctorAvailabilityQuery` (jadwal **sama** dengan situs publik dan wizard pasien) dengan “Sisa N dari M” dan status; sesi yang online-nya sudah ditutup tetap dapat dipilih petugas (“Online ditutup; loket masih bisa”) selama `is_bookable_counter` benar, yaitu sebelum `end_time` (00 F.3).
3. **Bagian 3 — Data pembayaran**: jenis pembayaran (Umum/Pribadi, BPJS Kesehatan, Asuransi), nomor BPJS 13 digit atau mitra asuransi + nomor polis opsional, keluhan singkat; catatan kanal “Loket” dan status awal “Dikonfirmasi”.
4. Tombol **Simpan Pendaftaran** dan **Batal**.
5. Profil baru: nama, tanggal lahir, jenis kelamin, telepon, email (opsional), alamat, nomor BPJS (opsional), **checkbox persetujuan privasi lisan** yang dicentang petugas (`consent_at`, `consent_version`); tanpa NIK dan tanpa akun login.

**Perilaku interaksi**

- Bagian 2 baru aktif setelah pasien dipilih; bagian 3 setelah sesi dipilih; setiap bagian dapat diubah kembali tanpa kehilangan pilihan lain.
- Simpan memanggil `BookingService::book()` dengan kanal `loket` dan status awal `confirmed`; aturan loket: `now < end_time` pada tanggal sesi; **batas pendaftaran aktif per pasien tidak berlaku** untuk loket, tetapi duplikat dan benturan jadwal tetap diperiksa (F.5).
- Sukses → `admin.appointments.show` dengan flash hijau dan tombol **Cetak Bukti** (`admin.appointments.proof`).
- Menghubungkan profil walk-in ke akun online dilakukan terpisah dari `admin.patients.show` setelah verifikasi identitas di loket, tidak otomatis (00 bagian F.9).

**Kondisi**

| Kondisi | Tampilan |
|---|---|
| Loading | **Cari** dan **Simpan Pendaftaran** → spinner; submit ganda dicegah (`Cache::lock`) |
| Kosong | Pencarian tanpa hasil → “Pasien tidak ditemukan” + **Buat profil baru**; dokter tanpa sesi di tanggal terpilih → “Tidak ada sesi pada tanggal ini” |
| Berhasil | Redirect ke detail pendaftaran dengan kode booking, nomor antrean, dan bukti |
| Gagal | `BookingException` (kuota penuh, duplikat, benturan, sesi tidak tersedia, pembayaran tidak valid) → alert merah berbahasa Indonesia dan kembali ke bagian terkait; validasi nomor BPJS 13 digit |

**Responsif**

| Lebar | Perilaku |
|---|---|
| ≥ 992 px | Tiga bagian bertumpuk dengan tabel hasil lebar penuh |
| < 992 px | Tabel hasil menjadi kartu; tombol Pilih/Simpan penuh-lebar |

**Aksesibilitas**

- Urutan fokus: pencarian → hasil → ringkasan pasien → pemilih sesi → pembayaran → Simpan; bagian yang belum aktif memakai `aria-disabled` dengan teks “Pilih pasien terlebih dahulu”.
- Kelompok radio berlabel `<fieldset>`; sesi nonaktif menampilkan alasan sebagai teks; nomor BPJS tidak diulang penuh di ringkasan; petunjuk checkbox persetujuan jelas (“dibacakan kepada pasien”).

---

## W-21 Pengaturan website dan hak akses

**Halaman 40** (`admin.settings.edit`, simpan `admin.settings.update`; izin `settings.manage`) dan layar tambahan hak akses `admin.roles.index` / `admin.roles.edit` (izin `staff.manage`).

### Pengaturan website: grup Kontak

```text
+--------------------------------------------------------------------------------------+
| Dasbor > Sistem > Pengaturan Website                                                 |
| PENGATURAN WEBSITE                                   (h1)                            |
| Identitas   [ Kontak ]   Booking   SEO   Privasi                                     |
+--------------------------------------------------------------------------------------+
| +- GRUP KONTAK (contoh isi seed; label kolom website_settings.label) --------------+ |
| | Alamat lengkap (contact.address)                                                 | |
| | +----------------------------------------------------------------------------+   | |
| | | Universitas Pamulang Kampus 2 Viktor, Jl. Raya Puspitek, Buaran, Kecamatan |   | |
| | | Pamulang, Kota Tangerang Selatan, Banten 15310                             |   | |
| | +----------------------------------------------------------------------------+   | |
| | Telepon (contact.phone)  [(021) 0000-0000]  [placeholder]                        | |
| | Telepon IGD (contact.emergency_phone)  [(021) 0000-0119]  [placeholder]          | |
| | WhatsApp (contact.whatsapp)  [6280000000000]  [placeholder]                      | |
| |   Format 62812xxxx tanpa tanda plus, spasi, atau tanda hubung; untuk tautan      | |
| |   wa.me yang dibuka manual pengunjung (bukan pengiriman otomatis/WhatsApp API).  | |
| | Email (contact.email) [info@unpamhospital.test]  [placeholder]                   | |
| | Jam operasional (contact.operational_hours) [Senin-Sabtu 08.00-20.00 (contoh)]   | |
| | Tautan Petunjuk Arah (map.directions_url)                                        | |
| |   [https://www.google.com/maps/search/?api=1&query=Universitas...]               | |
| | Tautan peta tersemat (map.embed_url)                                             | |
| |   [https://www.google.com/maps?q=Universitas...&output=embed]                    | |
| | (!) map.embed_url harus berupa URL https (galat validasi contoh).                | |
| | [ Simpan Pengaturan ]                                                            | |
| +----------------------------------------------------------------------------------+ |
+--------------------------------------------------------------------------------------+
```

Grup Booking:

```text
+- Grup Booking (isi tab Booking) -------------------------------------------------+
| Jendela pendaftaran, hari ke depan (booking.window_days)           [30]          |
| Batas daftar online, menit sebelum mulai (booking.online_cutoff_minutes)  [60]   |
| Batas batal pasien, menit sebelum mulai (booking.cancel_cutoff_minutes)  [120]   |
| Check-in dibuka, menit sebelum mulai (booking.checkin_open_minutes)  [60]        |
| Maks. pendaftaran aktif per pasien (booking.max_active_per_patient)  [3]         |
| [ ] Konfirmasi otomatis  (booking.auto_confirm; bawaan: nonaktif)                |
+----------------------------------------------------------------------------------+
```

### Hak akses: ikhtisar matriks (hanya-baca)

```text
+--------------------------------------------------------------------------------------+
| Dasbor > Sistem > Hak Akses                                                          |
| HAK AKSES (admin.roles.index)                      (h1)                              |
|                                  [ Ubah Admin Konten ]  [ Ubah Petugas Pendaftaran ] |
| +----------------------------+-------------+--------------+---------------+          |
| | Izin                       | Super Admin | Admin Konten | Petugas Pend. |          |
| +----------------------------+-------------+--------------+---------------+          |
| | dashboard.view             | [x]*        | [x]          | [x]           |          |
| | doctors.view               | [x]*        | [x]          | [x]           |          |
| | doctors.manage             | [x]*        | [x]          | [ ]           |          |
| | clinics.manage             | [x]*        | [x]          | [ ]           |          |
| | schedules.view             | [x]*        | [x]          | [x]           |          |
| | schedules.manage           | [x]*        | [x]          | [ ]           |          |
| | sessions.review            | [x]*        | [ ]          | [x]           |          |
| | facilities.manage          | [x]*        | [x]          | [ ]           |          |
| | rooms.update-availability  | [x]*        | [x]          | [x]           |          |
| | content.manage             | [x]*        | [x]          | [ ]           |          |
| | patients.view              | [x]*        | [ ]          | [x]           |          |
| | patients.manage            | [x]*        | [ ]          | [x]           |          |
| | appointments.view          | [x]*        | [ ]          | [x]           |          |
| | appointments.manage        | [x]*        | [ ]          | [x]           |          |
| | reports.export             | [x]*        | [ ]          | [x]           |          |
| | messages.manage            | [x]*        | [ ]          | [x]           |          |
| | settings.manage            | [x]*        | [ ]          | [ ]           |          |
| | staff.manage               | [x]*        | [ ]          | [ ]           |          |
| | audit.view                 | [x]*        | [ ]          | [ ]           |          |
| +----------------------------+-------------+--------------+---------------+          |
| * Super Admin terkunci: izin tidak dapat diubah atau dihapus (Gate::before).         |
| Matriks hanya-baca; ubah izin per peran lewat tombol Ubah (admin.roles.edit).        |
+--------------------------------------------------------------------------------------+
```

### Hak akses: ubah satu peran

```text
+- admin.roles.edit ---------------------------------------------------------------+
| Peran: Admin Konten (content-admin)                      Super Admin: terkunci   |
+----------------------------------------------------------------------------------+
| DASHBOARD           [x] dashboard.view                                           |
| DOKTER DAN JADWAL   [x] doctors.view  [x] doctors.manage  [x] clinics.manage     |
|                     [x] schedules.view  [x] schedules.manage  [ ] sessions.review|
| FASILITAS DAN KAMAR [x] facilities.manage  [x] rooms.update-availability         |
| KONTEN              [x] content.manage                                           |
| PASIEN              [ ] patients.view  [ ] patients.manage  [ ] appointments.view|
|                     [ ] appointments.manage  [ ] reports.export                  |
|                     [ ] messages.manage                                          |
| SISTEM              [ ] settings.manage  [ ] staff.manage  [ ] audit.view        |
+----------------------------------------------------------------------------------+
| (!) Mencabut izin berlaku pada permintaan berikutnya; tercatat di audit log.     |
| [ Simpan Hak Akses ]   [ Batal ]                                                 |
+----------------------------------------------------------------------------------+
```

**Elemen**

1. Tab grup pengaturan (`website_settings.group`; lima grup `identitas|kontak|booking|seo|privasi`): **Identitas** (`site.name`, `site.tagline`, logo `site.logo_media_id` lewat `x-form.file` dengan alt; koleksi `logo` minimum 120×40 px, SVG unggahan ditolak), **Kontak** (`contact.address`, `contact.phone`, `contact.emergency_phone`, `contact.whatsapp`, `contact.email`, `contact.operational_hours`, `map.directions_url`, `map.embed_url`), **Booking** (`booking.window_days`, `booking.online_cutoff_minutes`, `booking.cancel_cutoff_minutes`, `booking.checkin_open_minutes`, `booking.max_active_per_patient`, `booking.auto_confirm`), **SEO** (`seo.default_meta_description`), dan **Privasi** (`privacy.consent_version`; grup `privasi` pada 00 bagian E.6/E.7).
2. Setiap field memakai `label` dari kolom `website_settings.label`, tipe input sesuai kolom `type` (`string|text|int|bool|url|email|json`), teks bantuan, dan badge **Placeholder — ganti dengan data resmi** selama nilai masih sama dengan nilai seed **[ASUMSI]**.
3. Tombol **Simpan Pengaturan** tunggal untuk semua grup.
4. Hak akses (ikhtisar): tabel izin × peran (hanya-baca; ✔ diwakili `[x]`), kolom Super Admin bertanda terkunci (`*`), tombol **Ubah** per peran selain Super Admin.
5. Ubah peran: daftar centang izin dikelompokkan menurut area, peringatan bahwa pencabutan berlaku pada permintaan berikutnya dan dicatat di audit log, tombol **Simpan Hak Akses**.

**Perilaku interaksi**

- Simpan pengaturan: satu formulir `PUT`; setiap tab hanyalah panel (Bootstrap tabs) sehingga tanpa JavaScript seluruh grup tampil bertumpuk; validasi menurut tipe (URL harus `https`, email valid, angka dalam rentang: `booking.window_days` 1–90, `booking.online_cutoff_minutes` 0–1440, `booking.cancel_cutoff_minutes` 0–2880, `booking.checkin_open_minutes` 0–480, `booking.max_active_per_patient` 1–10 **[ASUMSI]**).
- Cache pengaturan dibersihkan saat simpan; setiap perubahan dicatat di `audit_logs` (nilai sebelum/sesudah; tidak ada nilai sensitif pada pengaturan ini).
- `contact.whatsapp` hanya dipakai membentuk tautan `https://wa.me/{nomor}` yang dibuka manual oleh pengunjung; bukan pengiriman otomatis (WhatsApp API = pengembangan lanjutan).
- Hak akses: peran `super-admin` terkunci (tidak dapat diubah atau dihapus; `Gate::before` melewati semua izin); minimal satu Super Admin aktif harus selalu ada (dijaga di Akun Staf). Mengubah izin peran tidak mengubah akun yang sudah login sampai permintaan berikutnya (cache izin Spatie di-reset saat simpan).

**Kondisi**

| Kondisi | Tampilan |
|---|---|
| Loading | **Simpan** → spinner + “Menyimpan…”; formulir `aria-busy` |
| Kosong | Nilai opsional kosong diperbolehkan (mis. `contact.email`); halaman publik menyembunyikan baris yang kosong, kecuali alamat |
| Berhasil | Flash hijau “Pengaturan disimpan”; tab yang sedang aktif dipertahankan |
| Gagal | Ringkasan galat + pesan per field (URL bukan https, angka di luar rentang); upaya mengubah peran Super Admin → ditolak dengan pesan “Peran ini terkunci” |

**Responsif**

| Lebar | Perilaku |
|---|---|
| ≥ 992 px | Formulir dua kolom (label di atas input); tab horizontal |
| < 992 px | Satu kolom; tab dapat digulir; matriks hak akses dapat digulir horizontal dengan kolom izin lengket |
| < 576 px | Matriks hak akses beralih ke daftar per peran (accordion) |

**Aksesibilitas**

- Urutan fokus: tab → field (atas ke bawah) → Simpan; bantuan field dihubungkan lewat `aria-describedby`; badge placeholder berupa teks.
- Matriks hak akses memakai `<table>` dengan `<th scope="col">` peran dan `<th scope="row">` izin; kotak centang memiliki label “Admin Konten: doctors.manage”; baris Super Admin diberi `disabled` dan teks “terkunci”.
- Peringatan pencabutan izin `role="alert"`; kontras sesuai token; tidak ada informasi hanya lewat warna (centang berupa `[x]` dengan teks status “diizinkan/tidak”).

---

## Asumsi di bagian ini

1. Nilai parameter query mengikuti 00 I.5 (17 D-14): `poli`, `dokter`, `spesialisasi`, `kategori` berisi slug; `sesi` berisi id; `minggu` (dinormalisasi ke Senin) dan `tanggal` berformat `YYYY-MM-DD` (bukan lagi asumsi).
2. **[ASUMSI]** Carousel beranda berganti otomatis tiap 6 detik dengan tombol jeda dan berhenti pada `prefers-reduced-motion`; `<h1>` beranda tetap (`pages.home.title`), judul slide bergaya h2 tanpa tag heading.
3. **[ASUMSI]** Header publik dua tingkat (baris atas dengan IGD dan tombol aksi; bar menu `$primary`) pada ≥ 992 px; di bawahnya satu baris dengan offcanvas. Bar menu `sticky-top`.
4. **[ASUMSI]** Grid mingguan jadwal hanya pada ≥ 992 px; di bawahnya tampilan bertumpuk per hari.
5. **[ASUMSI]** Throttle formulir (`login`, `register`, `password-reset`, `contact`, `booking`) ditampilkan sebagai pesan inline lewat respons *redirect back*; halaman 429 hanya untuk permintaan non-form.
6. **[ASUMSI]** Jumlah baris per halaman: 12 kartu dokter (publik), 15 baris tabel admin. Urutan tabel lewat parameter `urut` (00 I.5).
7. **[ASUMSI]** Bukti pendaftaran dicetak pada A5 potret (`@page`), dapat dicetak ke A4 dengan opsi sesuaikan ke halaman; PDF dompdf memakai ukuran yang sama.
8. **[ASUMSI]** Pada wizard, tanpa JavaScript semua blok pembayaran tampil dengan teks bantuan dan validasi `required_if`; tombol Kembali/Lanjut melekat di bawah layar pada < 576 px.
9. **[ASUMSI]** Pada mode ubah dokter, foto diganti lewat formulir tersendiri (`admin.doctors.photo`); `slug` dibuat otomatis dan tidak pernah berubah saat nama diubah (F2-R5); minimal satu spesialisasi dan tepat satu Utama.
10. **[ASUMSI]** Dashboard admin: angka per status mengikuti periode grafik; rentang kustom maksimal 366 hari; kartu status dapat ditautkan ke daftar pendaftaran dengan filter yang sama.
11. **[ASUMSI]** Alasan wajib untuk semua resolusi sesi terdampak (min. 5 karakter); pada `cancel` menjadi `cancel_reason`.
12. **[ASUMSI]** Antrean hari ini tidak diperbarui otomatis; petugas memakai **Muat ulang** dan server memeriksa status terkini pada setiap transisi.
13. **[ASUMSI]** Formulir walk-in berjalan bertahap lewat query `pasien` dan `sesi`; pembuatan profil baru kembali ke formulir lewat `admin.patients.create?kembali={path internal}` (parameter `pasien`, `sesi`, `kembali` ada di 00 I.5).
14. **[ASUMSI]** Rentang validasi pengaturan booking: `window_days` 1–90, `online_cutoff_minutes` 0–1440, `cancel_cutoff_minutes` 0–2880, `checkin_open_minutes` 0–480, `max_active_per_patient` 1–10; badge “Placeholder” pada nilai yang masih sama dengan seed.
15. **[ASUMSI]** Warna `$warning`, `$info`, `$danger`, `$secondary`, `$body-color` memakai nilai bawaan Bootstrap 5.3; skala tipografi dan cincin fokus pada bagian ringkasan design system.
16. Perilaku saat dokter dinonaktifkan/diarsipkan sementara memiliki booking aktif mendatang → diputuskan di 00 F.4 / 17 D-01, D-02 (nonaktif: sesi tanpa booking dibatalkan, sesi berbooking menjadi Sesi terdampak `doctor_inactive`; arsip: hanya bila tidak ada booking aktif mendatang). W-15, W-16, W-17, dan W-18 mengikuti keputusan itu.

## Nama baru yang diusulkan

Untuk direkonsiliasi ke 00 bagian J.5 dan bagian 10/11:

- **`x-wizard-steps`** — komponen Blade anonim untuk indikator langkah wizard (`<ol>` dengan `aria-current="step"`), dipakai di W-11. **Diterima (00 J.5).**
- **`x-module-tabs`** — komponen Blade anonim untuk tab modul admin (`nav-tabs` dengan `aria-current` dan penyaringan izin), dipakai di W-17 dan modul 34, 36, 38, 39, 40 (lihat 05 bagian 5.2). **Diterima (00 J.5).**
- Parameter query (bukan nama route): `tanggal`, `urut`, `kode`, `sesi`, `pasien`, `kembali` → **diterima (00 I.5)** (pemakaian: pemilih tanggal wizard dan antrean, urutan kolom daftar admin, pencarian kode booking dan pemilih sesi pada `admin.queue.index`, profil terpilih dan tujuan kembali pada alur walk-in).
