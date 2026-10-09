# Checkpoint — UNPAM HOSPITAL

**Terakhir diperbarui:** 2026-10-09 sesi 8, **dijeda** saat penyusunan rencana fase 7a (penutupan fase 6 tuntas; insiden MySQL dipulihkan; desain 7a disetujui dan di-commit; rencana 7a: T1–T9, T11, T12 ditulis, T10 dan T13 belum) · **Tag git:** `checkpoint/2026-10-09-fase-7a-penyusunan` (sebelumnya `checkpoint/2026-10-09-fase-6-merge`, `checkpoint/2026-10-09-fase-6-selesai`, `checkpoint/2026-10-08-fase-6-task-7`, `checkpoint/2026-10-08-fase-6-rencana`, `checkpoint/2026-10-08-fase-5-selesai`, `checkpoint/2026-10-08-fase-5-eksekusi`, `checkpoint/2026-10-08-fase-5-rencana`, `checkpoint/2026-10-07-fase-4b-selesai`, `checkpoint/2026-10-07-fase-4b-eksekusi`, `checkpoint/2026-10-06-fase-4b-task-1`, `checkpoint/2026-10-06-fase-4b-rencana`, `checkpoint/2026-10-06-fase-4a-selesai`, `checkpoint/2026-10-06-fase-4a-eksekusi`, `checkpoint/2026-10-05-fase-4a-rencana`, `checkpoint/2026-10-05-fase-3-selesai`, `checkpoint/2026-10-05-fase-3-eksekusi`, `checkpoint/2026-10-05-fase-3-rencana`, `checkpoint/2026-10-05-fase-2`, `checkpoint/2026-10-04-fase-1`, `checkpoint/2026-10-03-fase-1-task-4`, `checkpoint/2026-10-02-fase-0`)

Dokumen ini adalah titik lanjut untuk sesi berikutnya. Baca dari atas ke bawah sebelum bekerja.

## 1. Posisi saat ini

| Hal | Status |
|---|---|
| Blueprint (spesifikasi) | Selesai & disetujui — `docs/superpowers/specs/2026-10-02-unpam-hospital-blueprint/` (mulai dari `README.md`, lalu `00-keputusan-inti.md`). Diperbarui di fase 1: 00 J.6, 06 §14.A, 10 (pratinjau `FileReader`), 11 §3.3/§6.10/§6.11 (`scoped`), 12 V-03, 15 SEK-03, 16 §5/§7, 17 §4 (D-21–D-28). |
| Fase 0 — Lingkungan & skeleton | **Selesai**, di-merge ke `master`. Rencana + catatan eksekusi: `docs/superpowers/plans/2026-10-02-fase-0-lingkungan-skeleton.md`. |
| Fase 1 — Fondasi | **Selesai** dan **sudah di-merge** ke `master` (fast-forward `0b87342`, 2026-10-04; cabang `fase-1-fondasi` dihapus). Gerbang fase terpenuhi: 980 tes, Pint, `npm run build`, `migrate:fresh --seed`, dan login tiga akun demo. Login diverifikasi koordinator lewat otomasi browser (Playwright); konfirmasi manual pemilik disarankan. Final review seluruh cabang beserta perbaikannya sudah selesai. Rencana, catatan eksekusi, dan keputusan R1–R17: `docs/superpowers/plans/2026-10-03-fase-1-fondasi.md` (bagian akhir "Catatan Eksekusi"). |
| Fase 2 — Master Data | **Eksekusi dan gerbang fase selesai** di cabang `fase-2-master-data`. Semua 16 tugas lulus review. Final review seluruh cabang beserta satu gelombang perbaikan (F1–F5) selesai. `migrate:fresh --seed` dijalankan dengan izin pemilik dan jumlah datanya persis sesuai target. Uji manual Step 19 dijalankan lewat Playwright (laporan bergambar di `Documents\UNPAM-Uji-Manual-2026-10-05`); dua cacat temuannya (M1 `&nbsp;` editor, M2 panah galeri) sudah diperbaiki di `929c13a`. Hasil akhir: 1845 tes lulus (8479 asersi), Pint PASS, `npm run build` OK, 31 route admin. **Sudah di-merge** ke `master` (fast-forward `51134af`, 2026-10-05; cabang `fase-2-master-data` dihapus; suite pada hasil merge 1845 lulus). Rencana, catatan eksekusi, dan keputusan X1–X14 ada di bagian akhir `docs/superpowers/plans/2026-10-04-fase-2-master-data.md`. |
| Cabang git | `master` (fase 0 sampai 6, ujung `57fa3fe`) dan cabang kerja **`fase-7a-pasien-pendaftaran-antrean`** (checkout aktif; berisi commit desain `439b162` dan commit checkpoint jeda). Cabang fase 3 sampai 6 sudah di-merge lalu dihapus. Tidak ada stash. Repo tanpa remote. |
| Keputusan tertunda pemilik | Butir K di `17-keputusan-konfirmasi.md`: semua punya default. K-07 dan K-22 sudah dijawab pemilik 2026-10-08: keduanya (a) — satu akun satu profil, dan email selamat datang tanpa verifikasi wajib. Keduanya dipindah ke 17 §1 oleh Task 8 fase 5. |
| Fase 3 — Penjadwalan (Jadwal Dokter) | Rencana disetujui pemilik. **Task 1–16 selesai** lewat subagent-driven-development (Sonnet 5.5), semuanya lolos review. Putaran perbaikan terjadi di Task 3, 11 (juga view Task 10), dan 13. Final review seluruh cabang: **With fixes** (3 Important, tanpa Critical). Satu gelombang perbaikan final (F1–F3, F6, M1, M2; `49f966a`..`606cfbe`) lolos re-review terbatas. `migrate:fresh --seed` sudah dijalankan dengan izin pemilik, dan uji manual Playwright selesai dengan Lighthouse aksesibilitas 100/100/100 setelah perbaikan. **Gerbang fase terpenuhi:** 2933 tes lulus (14113 asersi), Pint PASS, `npm run build` OK, 50 route admin (19 baru). **Sudah di-merge** ke `master` atas persetujuan pemilik (fast-forward `1558f62`, 2026-10-05; cabang `fase-3-jadwal-dokter` dihapus; suite pada hasil merge 2933 lulus). Rencana, catatan eksekusi, dan keputusan E1–E13: bagian akhir `docs/superpowers/plans/2026-10-05-fase-3-penjadwalan.md`. Rinciannya di §3a. |
| Fase 4a — Konten Publik dan CMS | **Selesai dan sudah di-merge** ke `master` atas pilihan pemilik "1" (fast-forward `92173df`, 2026-10-06; cabang dihapus; suite pada hasil merge 4348 lulus). Rencana disetujui pemilik ("lanjutkan"). Task 1–17 selesai lewat subagent-driven-development (Sonnet 5.5) dan lolos review; putaran perbaikan di Task 9 dan 15. Final review: Dengan perbaikan (1 Important, 0 Critical); gelombang perbaikan F1–F4 (`a57eccf`) lolos re-review. **Gerbang:** 4348 tes lulus (21166 asersi), Pint PASS, `npm run build` OK, 90 route admin. `migrate:fresh --seed` dijalankan dengan izin pemilik; uji manual Playwright 0 Critical/Important dan Lighthouse aksesibilitas 100. Catatan eksekusi dan keputusan E1–E14: bagian akhir `docs/superpowers/plans/2026-10-05-fase-4a-konten-publik.md`. Rinciannya di §3b. |
| Fase 4b — Fasilitas, Kamar, dan Kontak | **Selesai dan sudah di-merge** ke `master` atas pilihan pemilik "1" (fast-forward `bac258a`, 2026-10-07; cabang dihapus; suite pada hasil merge 5200 lulus). Rencana disetujui pemilik ("sesuai"): `docs/superpowers/plans/2026-10-06-fase-4b-fasilitas-kamar-kontak.md` (11 tugas, F4B-R1–R40). Task 1–11 selesai lewat subagent-driven-development (Sonnet 5.5) dan lolos review pada percobaan pertama. Final review: **Siap merge** (0 Critical/Important); gelombang perbaikan F1–F2 (`4a4ca50`, `fe3716e`) lolos re-review. **Gerbang:** 5200 tes lulus (26035 asersi), Pint PASS, `npm run build` OK, 106 route admin. `migrate:fresh --seed` dijalankan dengan izin pemilik; uji manual Playwright 0 Critical/Important dan Lighthouse aksesibilitas/SEO 100. Catatan eksekusi dan keputusan E1–E11: bagian akhir rencana. Rinciannya di §3c. |
| Fase 5 — Portal Pasien (Akun) | **Selesai dan sudah di-merge** ke `master` atas pilihan pemilik "1" (fast-forward `43aa92c`, 2026-10-08; cabang dihapus; suite pada hasil merge 5646 lulus (29278 asersi)). Rencana disetujui pemilik (bersama izin "Ya, jalankan migrate:fresh --seed"): `docs/superpowers/plans/2026-10-08-fase-5-akun-pasien.md` (8 tugas, F5-R1–R31). Task 1–8 selesai lewat subagent-driven-development (Sonnet 5.5) dan lolos review; satu putaran perbaikan dokumen di Task 8. Final review: **Siap merge** (0 Critical/Important, 8 Minor); gelombang perbaikan FX1–FX6 (`e86889c`, `8e23394`, `de5a11b`) lolos re-review. **Gerbang:** 5646 tes lulus (29278 asersi), Pint PASS, `npm run build` OK, 141 route (106 admin). `migrate:fresh --seed` dijalankan; uji manual Playwright 9/9 kelompok sesuai, Lighthouse aksesibilitas 100. Catatan eksekusi dan keputusan E1–E12: bagian akhir rencana. |
| Fase 6 — Mesin Booking, Wizard, dan Bukti | **Selesai dan sudah di-merge** ke `master` atas pilihan pemilik "1" (fast-forward `7523a49`..`e4fe839`, 2026-10-09). **Suite pada hasil merge (`c807668`) 6692 lulus + 1 dilewati (38994 asersi, ±24 menit)**, dijalankan sesudah insiden MySQL dipulihkan (§2). Cabang `fase-6-booking-wizard-bukti`, workspace SDD fase 6, dan `.superpowers/plan-fase-6/` sudah dihapus. Rencana: `docs/superpowers/plans/2026-10-08-fase-6-booking-wizard-bukti.md` (13 tugas, F6-R1–R47; K-13 = (b), K-17 = (a)). Task 1–13 lolos review pada percobaan pertama (`64abcb2`..`d7a462c`). Final review dua kursi (produksi: With fixes; tes dan dokumen: Yes) dan uji manual (1 Important) ditutup gelombang perbaikan FX1–FX11 (`99b0535`..`dc927d4`). **Gerbang:** 6692 tes lulus + 1 dilewati opt-in, Pint PASS, `npm run build` OK, 150 route (106 admin, 13 di `/pasien`). Uji manual: Lighthouse aksesibilitas 100; laporan `Documents\UNPAM-Uji-Manual-2026-10-09-fase-6\index.html`. Catatan eksekusi, keputusan E1–E17, dan bawaan fase 7 ada di bagian akhir rencana. |
| Fase 7a — Pasien, Walk-in, Pendaftaran, dan Antrean | **Penyusunan rencana dijeda** (2026-10-09) di cabang `fase-7a-pasien-pendaftaran-antrean`. Desain disetujui pemilik dan di-commit (`439b162`, `docs/superpowers/specs/2026-10-09-fase-7a-desain.md`). Bahan rencana lokal di `.superpowers/plan-fase-7a/` (digest, `kerangka.md` F7A-R1–R31, `tasks/T1–T9, T11, T12`); T10 dan T13 belum ditulis. Rincian dan langkah lanjut: "Langkah lanjut" butir 3. Fase 7b (Sesi Terdampak) menyusul sesudah 7a di-merge. |

### 3a. Status fase 3 (selesai dan di-merge 2026-10-05)

- **Rencana:** `docs/superpowers/plans/2026-10-05-fase-3-penjadwalan.md` (disetujui pemilik). Bagian akhirnya berisi "Catatan Eksekusi": hasil gerbang, putaran perbaikan, final review, keputusan E1–E13, dan bawaan untuk fase 6/7.
- **Task 1–16:** selesai (commit `083ff72`..`4f79823`), masing-masing lolos review.
- **Gelombang perbaikan final:** `49f966a` (F1 baca terkunci), `917485a` (F2 edit templat dengan poliklinik nonaktif), `4a44d99` (F3 view admin fase 2), `606cfbe` (F6 README cron, M1 kontras navbar, M2 tombol Reset). Re-review terbatas: semuanya ADDRESSED, tanpa kerusakan baru.
- **Gerbang (diverifikasi koordinator di `606cfbe`):** 2933 tes lulus (14113 asersi, ±6 menit), Pint PASS, `npm run build` OK, 50 route admin.
- **DB pengembangan** (di-seed ulang dengan izin pemilik): 52 templat, 231 sesi untuk hari ini s.d. +30, ditambah sedikit data uji manual (templat dr. Bagas Senin 10.00–13.00 kuota 15).
- **Uji manual Playwright:** kelompok 1–6 sesuai. Lighthouse aksesibilitas awalnya 97/96/97, lalu **100/100/100** setelah M1/M2 diperbaiki. Laporan bergambar lengkap (bagian 1–8): `Documents\UNPAM-Uji-Manual-2026-10-05-fase-3\index.html`.

- **Merge:** fast-forward ke `master` (`1558f62`) atas pilihan pemilik "1"; suite pada hasil merge 2933 lulus. Cabang `fase-3-jadwal-dokter`, workspace SDD fase 3, dan bahan `.superpowers/plan-fase-3/` sudah dihapus; catatan lengkapnya kini ada di rencana dan riwayat git.

### 3b. Status fase 4a (selesai dan di-merge 2026-10-06)

- **Rencana:** `docs/superpowers/plans/2026-10-05-fase-4a-konten-publik.md` (disetujui pemilik). Bagian akhirnya berisi "Catatan Eksekusi": hasil gerbang, putaran perbaikan, final review, keputusan E1–E14, dan bawaan untuk fase 4b/6.
- **Task 1–17:** selesai (commit `31d32af`..`f4431e8`), masing-masing lolos review. Putaran perbaikan: Task 9 (pagar Markdown tersalin ke view galeri) dan Task 15 (fokus carousel).
- **Gelombang perbaikan final:** `a57eccf` (F1 draf admin tampil dulu, F2 `url()` og:image beranda, F3 `tel:` kosong, F4 checklist deploy `PageSeeder`). Re-review: semua ADDRESSED.
- **Gerbang (diverifikasi koordinator di `a57eccf`):** 4348 tes lulus (21166 asersi, ±11 menit), Pint PASS, `npm run build` OK, 90 route admin.
- **DB pengembangan** (di-seed ulang dengan izin pemilik): data demo fase 1–4a (15 halaman, 8 artikel, 3 banner, 3 album, 12 FAQ, 4 mitra, 57 media). `media:prune` membersihkan 66 berkas yatim dari isi lama. Sisa uji manual: 2 artikel uji terhapus-lunak.
- **Uji manual Playwright:** kelompok 1–6 sesuai; Lighthouse aksesibilitas dan SEO 100 untuk lima halaman. Laporan bergambar: `Documents\UNPAM-Uji-Manual-2026-10-06-fase-4a\index.html`.
- **Merge:** fast-forward ke `master` (`92173df`) atas pilihan pemilik "1"; suite pada hasil merge 4348 lulus (21166 asersi). Cabang `fase-4a-konten-publik`, bahan penyusunan `.superpowers/plan-fase-4a/`, dan workspace SDD fase 4a sudah dihapus; catatan lengkapnya ada di rencana dan riwayat git.

### 3c. Status fase 4b (selesai dan di-merge 2026-10-07)

- **Rencana:** `docs/superpowers/plans/2026-10-06-fase-4b-fasilitas-kamar-kontak.md` (disetujui pemilik). Bagian akhirnya berisi "Catatan Eksekusi": hasil gerbang, final review, gelombang perbaikan, keputusan E1–E11, dan bawaan.
- **Cakupan:** F-PUB-08/09/15/16 dan F-ADM-09/10/11/24.
  - Publik: `/fasilitas` (#9), `/fasilitas/{slug}` (#10), `/kamar-rawat-inap` (#11), `/kamar-rawat-inap/{slug}` (#12–#15), `/kontak` (#22, peta `x-map-embed`, form pesan dengan throttle, honeypot, dan `ip_hash`), serta blok kamar di beranda.
  - Admin #36: Fasilitas; Kamar dan Kapasitas (Petugas Pendaftaran hanya mode ketersediaan); kotak Pesan Kontak (tanpa hapus, audit tanpa data pribadi).
  - `LikePattern` menggantikan sembilan salinan escape LIKE.
- **Task 1–11:** selesai (commit `01240a3`..`76ade11`), semuanya lolos review tanpa putaran perbaikan.
- **Gelombang perbaikan final:** `4a4ca50` (F1 `cover_alt` larik tidak lagi 500 di Facility/RoomCategory/ClinicRequest) dan `fe3716e` (F2 sisa teks blueprint "real-time", asumsi 23, judul 139 route). Re-review terbatas: semuanya ADDRESSED.
- **Gerbang (diverifikasi koordinator di `fe3716e`):** 5200 tes lulus (26035 asersi, ±28 menit), Pint PASS, `npm run build` OK, 106 route admin, 17 route publik.
- **DB pengembangan** (di-seed ulang dengan izin pemilik): 4 kategori kamar, 10 fasilitas, 5 pesan kontak, dan 83 media, ditambah data fase 1–4a. `media:prune` membersihkan 116 berkas yatim seed lama. Sisa uji manual: satu pesan uji "Uji Manual Empat B", dan satu pesan demo berstatus Dibaca.
- **Uji manual Playwright:** kelompok 1–7 sesuai. Lighthouse aksesibilitas dan SEO **100** untuk lima halaman. Laporan bergambar: `Documents\UNPAM-Uji-Manual-2026-10-07-fase-4b\index.html`.
- **Merge:** fast-forward ke `master` (`bac258a`) atas pilihan pemilik "1"; suite pada hasil merge 5200 lulus (26035 asersi). Cabang `fase-4b-fasilitas-kamar-kontak`, workspace SDD fase 4b, dan bahan `.superpowers/plan-fase-4b/` sudah dihapus; catatan lengkapnya ada di rencana dan riwayat git.

Langkah lanjut:
1. Nyalakan Apache di `C:\xampp3\xampp-control.exe` dan MySQL dengan `powershell -ExecutionPolicy Bypass -File scripts\mysql-start.ps1` (bukan Control Panel; §2).
2. Fase 6 sudah ditutup tuntas.
   - DB pengembangan masih berisi data uji manual fase 6: akun `pasien2@unpamhospital.test` (P000009) dan lima pendaftaran milik `pasien@` (rincian di Catatan Eksekusi rencana fase 6).
   - `AppointmentSeeder` dibuat di fase 7a, dan sesudahnya perlu `migrate:fresh --seed` dengan izin pemilik.
3. **Fase 7a sedang dalam penyusunan rencana dan DIJEDA** (2026-10-09, pemilik: "pause dulu lanjut nanti, buatkan checkpoint"). Cabang kerja `fase-7a-pasien-pendaftaran-antrean` (dari `master` `57fa3fe`).
   - **Brainstorming selesai.** Keputusan pemilik:
     - fase 7 dipecah **7a** (pasien #37, walk-in, pendaftaran dan antrean #38, `appointments:mark-no-show`, bukti admin, `AppointmentSeeder`, bawaan fase 6) dan **7b** (Sesi Terdampak, resolusi `keep`/`apply`/`cancel`, notifikasi perubahan jadwal, K-15);
     - K-04 = (a), yaitu BPJS dan polis hanya 4 digit terakhir;
     - urutan pengerjaan: domain dulu, lalu layar;
     - keempat bagian desain disetujui ("setuju"/"lanjutkan").
   - **Dokumen desain di-commit** (`439b162`): `docs/superpowers/specs/2026-10-09-fase-7a-desain.md`, berisi 16 route baru (total 166, admin 122), tanpa migration, dan penyimpangan dari blueprint di §9. Pemilik meminta lanjut ke rencana ("lanjutkan").
   - **Bahan penyusunan rencana** (lokal, di-ignore git, JANGAN dihapus) ada di `.superpowers/plan-fase-7a/`:
     - empat digest: `digest-data.md`, `digest-domain.md`, `digest-http.md`, `digest-tests.md`;
     - `kerangka.md`: keputusan F7A-R1–R31 yang mengikat, route C.1/C.1a, jangkar C.1b/C.1c, antarmuka D, Review Focus E, dan pembagian tugas F;
     - `tasks/T1.md`–`T9.md`, `T11.md`, `T12.md`.
   - **Status per tugas:**
     - T1–T8 dan T11 selesai dan sudah dilaporkan penulisnya. Catatan penulis sudah ditindaklanjuti di kerangka: `DoctorService::changeStatus` (bukan `updateStatus`), dan profil walk-in nonaktif tidak dapat dihubungkan.
     - T9 dan T12 tampak lengkap (berakhir dengan langkah commit), tetapi penulisnya dihentikan sebelum melapor. **Periksa ulang** sebelum dipakai.
     - **T10** (antrean W-19 dan Tandai tidak hadir massal) dan **T13** (matriks akses, `Phase7aRoutePermissionTest`, rekonsiliasi dokumen, README, gerbang) **belum ditulis**.
   - **Langkah lanjut saat sesi dilanjutkan:**
     1. Kirim penulis baru (Sonnet) untuk T10. Bekalnya: `kerangka.md`, `digest-http.md`, `digest-domain.md`, serta T8 dan T9 sebagai konteks berkas yang sudah diubah. Untuk `lang/id/booking.php` dan `BookingExceptionTest`, jangkarnya mengikuti kode sesudah T2/T8/T9.
     2. Kirim penulis baru untuk T13 (bekal sama, plus T5–T12).
     3. Pemeriksa singkat untuk T9 dan T12 (kelengkapan dan kecocokan jangkar).
     4. Uji coba integrasi dua bagian (T1–T7, lalu T8–T13) di salinan repo, ditambah pemeriksa statis.
     5. Fixer dengan kepemilikan berkas terpisah, lalu perakitan rencana `docs/superpowers/plans/2026-10-09-fase-7a-pasien-pendaftaran-antrean.md` dengan skrip Python, dan commit.
     6. Ajukan rencana ke pemilik bersama permintaan izin `migrate:fresh --seed`.
   - Bawaan fase 6 ada di bagian "Bawaan (carry)" rencana fase 6. Semua yang menjadi milik 7a sudah dipetakan di kerangka.

Yang sudah ada di aplikasi:
- Fase 1: enum, akun & peran (Spatie), audit log, media + sanitasi HTML, pengaturan situs, tema & komponen Blade, layout publik/pasien/auth/admin, login/logout staf, ganti password wajib, header keamanan, `StaffAccountService`, `app:create-super-admin`, `media:prune`, seeder demo staf.
- Fase 2: master data spesialisasi, poliklinik/layanan penunjang, dan dokter (CRUD admin, filter, tab, status/arsip dokter lewat `DoctorService`); editor rich text Quill dengan unggah gambar (`admin.media.*`, `MediaPolicy`, `syncEditorImages`); halaman publik `/layanan` (#4) dan `/layanan/{slug}` (#5); seeder demo 13 spesialisasi, 17 layanan, 18 dokter, 32 gambar contoh; poles login/ganti password/footer admin.
- Fase 3 (di-merge ke `master`): skema jadwal dan booking; templat jadwal praktik, pengecualian (cuti dokter dan libur RS), dan sesi per tanggal di `admin/jadwal` (19 route); `PracticeSessionGenerator` + `sessions:generate` terjadwal; `ScheduleChangeService` sebagai satu-satunya jalur tulis (propagasi dengan kunci, audit); `DoctorAvailabilityQuery`; halaman publik `/dokter` (#6), `/dokter/{slug}` (#7), dan `/jadwal-dokter` (#8); seeder demo jadwal.

## 2. Lingkungan (mesin pemilik)

- XAMPP 8.2.12 ada di **`C:\xampp3`** (bukan `C:\xampp`, itu sisa lama). Sebelum bekerja, **Apache** di-**Start** dari `C:\xampp3\xampp-control.exe`. **MySQL dinyalakan dengan `scripts\mysql-start.ps1` dan dimatikan dengan `scripts\mysql-stop.ps1`**, bukan lewat Control Panel; jalankan stop juga sebelum Windows dimatikan. Start/Stop Control Panel merusak `mysql.db` dan menghapus GRANT (insiden 2026-10-02 dan 2026-10-09, 16 §1.3 dan §13).
- Runtime PHP 8.2.12 (`C:\xampp3\php\php.exe`). PHP 8.4 di PATH **jangan** dipakai untuk proyek. Composer selalu dijalankan sebagai `& $PHP C:\composer\composer.phar …`, bukan `composer.bat` (karakter `^` hilang).
- Database `unpam_hospital` (dev) dan `unpam_hospital_test` (uji), user `unpam_app`. Password hanya ada di `.env`/`.env.testing` (git-ignored, jangan dihapus).
- URL: <http://unpam-hospital.test> (VirtualHost; cadangan konfigurasi `httpd-vhosts.conf.bak-fase0`). Alternatif: `php artisan serve` → <http://127.0.0.1:8000>. Login admin: <http://unpam-hospital.test/admin/login>.
- Akun demo (hanya lokal, password `Demo12345` atau `DEMO_PASSWORD`): `superadmin@`, `konten@`, `pendaftaran@unpamhospital.test`. Akun ini dibuat oleh `php artisan migrate:fresh --seed`, yang menghapus seluruh isi database pengembangan. Database pengembangan sudah di-seed ulang dengan data demo fase 2 (2026-10-05). Isinya ditambah sedikit data dari uji manual: dokter "Laras Wulandari Putri" (Nonaktif), serta dua foto galeri dan satu kalimat tambahan di Poliklinik Penyakit Dalam. Perintah `migrate:fresh --seed` menghapus seluruh isi DB pengembangan, jadi Claude perlu izin eksplisit pemilik untuk menjalankannya karena pengaman auto mode memblokir.
- Tabel sistem MariaDB XAMPP pernah rusak (`mysql.db` + log Aria) dan sudah diperbaiki. Bila hak akses DB hilang lagi, lihat blueprint 16 §13 baris "`GRANT` tidak tersimpan".

## 3. Cara melanjutkan (PowerShell)

```powershell
$XAMPP = "C:\xampp3"; $PHP = "$XAMPP\php\php.exe"; $COMPOSER = "C:\composer\composer.phar"
$env:Path = "$XAMPP\php;$env:Path"; Set-Location "D:\Project Web RS"

git status; git branch --show-current          # harus bersih, di master (atau cabang kerja fase 4)
& $PHP scripts\check-env.php                    # "status": "OK"
& $PHP artisan test                             # 4348 passed (±11 menit; jalankan di latar belakang bila lewat tool)
& $PHP vendor\bin\pint --test                   # PASS
& $PHP artisan route:list --path=admin          # 90 route (40 di admin/konten)
& $PHP artisan media:prune --dry-run            # 0 baris, 0 berkas yatim
curl.exe -s -o NUL -w '%{http_code}' http://unpam-hospital.test/layanan       # 200
```

Bila semua hijau, lanjutkan dari "Langkah lanjut" §3b.

## 4. Bawaan (carry) untuk fase berikutnya

Ringkasan; rinciannya ada di bagian "Catatan Eksekusi" rencana fase 0, fase 1, dan fase 2. Bawaan fase 2 dari fase 1 sudah dikerjakan (D-52), kecuali form pengaturan (D-53, fase 8).

- **Fase 3:** bawaan fase 2 sudah dikerjakan di Task 2, 8, 13, dan 14.
- **Fase 4a:** bawaan fase 1–3 untuk 4a sudah dikerjakan (`HomeController`, `PageSeeder`, `Page` untuk #4/#6/#8, `EDITOR_REFERENCE_COLUMNS`, perapian `[ Lihat Jadwal ]` di 07).
- **Fase 4b:** bawaan 4a untuk 4b sudah dikerjakan (penjaga `ContentSchemaTest` dihapus, `LikePattern` menggantikan salinan escape LIKE, tautan Kontak dan blok kamar muncul lewat `Route::has()`). Minor 4b yang ditunda dan rencana ekstraksi (modal galeri 3 salinan, `saveMedia()`, `attach()` seeder) ada di Catatan Eksekusi rencana fase 4b. Untuk fase 8: redaksi ringkasan kamar di dasbor tetap "diperbarui manual oleh petugas" (F4B-R11).
- **Minor fase 2 yang ditunda:**
  - Suite ±150 s (seed demo per tes).
  - `errorBag` per media galeri.
  - Gambar editor lintas poliklinik.
  - Logo kecil di header publik dan sidebar admin tampak sempit (lebar = tinggi).
  - Lainnya tercantum di Catatan Eksekusi fase 2.
- **Fase 5:** dikerjakan di rencana fase 5 (cadangan F1-R2 dihapus, `passwords.throttled` generik, `lang/id.json`, `routes/patient.php`, akun pasien demo). Butir asal:
  - Hapus cadangan `Route::has('login')`/`Route::has('patient.dashboard')` (F1-R2).
  - `passwords.throttled` dipetakan ke pesan generik `passwords.sent` (mencegah enumerasi email).
  - `lang/id.json`, `routes/patient.php`.
  - Akun pasien demo `pasien@unpamhospital.test` di `DemoUserSeeder`.
- **Fase 6:**
  - Pindahkan `nextSession` + saringan spesialisasi aktif (`HomeController` dan `DoctorController`) ke satu tempat (E12 fase 4a).
  - Tombol banner seed #2 dan "Daftar Kunjungan" beranda muncul otomatis; perbarui asersi banner #2 di `ContentSeederTest`.
  - Handler `BookingException`.
  - `SettingsService::bookingRules()` sudah ada (fase 3); tambahkan `assertBookable`.
  - Prasyarat dari final review fase 3: hitungan appointment di `ScheduleChangeService` sudah berupa baca terkunci (F1, `49f966a`). Penulis booking wajib mengunci sesi lebih dulu dengan urutan kunci yang sama. `assertClinicChangeAllowed` dan cek arsip dokter (`futureActiveAppointmentsCount`) berupa *check-then-act*; uji ulang di bawah kunci booking.
  - Tes konkurensi tanpa transaksi wajib ikut membersihkan `audit_logs` (pola baseline id di `ConcurrencySpikeTest`).
  - Siklus referensi `Doctor → availabilities → session → doctor` dari `DoctorAvailabilityQuery::doctors()` membuat `toArray`/`json_encode` berulang tanpa henti; jangan diserialisasi apa adanya.
- **Fase 7:**
  - Layar Sesi Terdampak (`admin.sessions.review`): perbarui `Phase3RoutePermissionTest` (19 → 20 route) dan angka `route admin: 50`.
  - Ganti tes tautan banner yang ter-skip dengan tes positif.
  - Saat *resolve*, cek ulang status dokter, karena sumber `doctor_inactive` bisa tergantikan `exception`.
- **Fase 8:**
  - `StaffAccountService::syncRolePermissions`, `RolePolicy`, `Route::bind('user')`.
  - Panggilan `StaffAccountService` dibungkus retry deadlock (`DB::transaction(..., 3)`).
  - Widget dasbor menggantikan sapaan sementara (F1-R4).
- **Hardening sebelum produksi:**
  - `auth.timebox_duration` di atas waktu bcrypt server (bcrypt cost 12 terukur 192 ms di mesin ini).
  - Header keamanan untuk respons 503/413.
  - Throttle `PUT /admin/ganti-password` dan batas login per IP.
  - Log `path()`, bukan `fullUrl()`.
  - `audit_logs` immutable di level DB.
  - `autofocus` email di halaman login.
- **Lain-lain:**
  - `.env.testing` mesin ini boleh disamakan dengan `.env.testing.example` (`LOG_CHANNEL=single`).
  - Baris "429" untuk POST di dokumen 06 dibaca sesuai D-23.

## 5. Riwayat sesi

1. **2026-10-02:** Brainstorming → blueprint 17 bagian + `00-keputusan-inti.md`. Rencana fase 0 → eksekusi → 26 tes hijau. Insiden `mysql.db`/Aria XAMPP diperbaiki.
2. **2026-10-03:** Merge fase 0 ke `master`; K-10 = (a). Rencana fase 1 (19 tugas) disusun dan disetujui. Scan pra-eksekusi (`ca70747`). Task 1–3 selesai; Task 4 dijeda setelah siklus `Actor`.
3. **2026-10-03/04:**
   - Task 4–19 selesai lewat subagent-driven-development (Sonnet 5.5).
   - Putaran perbaikan di Task 7, 9, 11, dan 17.
   - Final review seluruh cabang + satu gelombang perbaikan (hash dummy login dibatalkan, R16).
   - Gerbang fase 1 terpenuhi: 980 tes; login diverifikasi lewat Playwright.
   - Blueprint direkonsiliasi (D-21–D-28, `scoped`, `FileReader`).
   - M-3/M-6 dari fase 0 ditutup.
4. **2026-10-04:**
   - Fase 1 di-merge ke `master`.
   - Uji manual login dengan Playwright (laporan bergambar di `Documents\UNPAM-Uji-Manual-2026-10-04`).
   - Rencana Fase 2 disusun: tiga digest spesifikasi, kerangka kontrak, empat penulis, dua pemeriksa konsistensi. Hasilnya 16 tugas dengan keputusan F2-R1–F2-R28.
5. **2026-10-04/05:**
   - Rencana fase 2 disetujui pemilik. Scan pra-eksekusi S1–S13 ditambal (`539ffbb`).
   - Task 1–16 selesai lewat subagent-driven-development (Sonnet 5.5), dengan putaran perbaikan di Task 2, 7, 11, dan 16.
   - Final review seluruh cabang menemukan 3 butir Important: gambar editor terhapus `media:prune`, spesialisasi nonaktif tampil publik, dan alt diabaikan. Ketiganya beserta 2 Minor ditutup dalam satu gelombang perbaikan (`fb4ab7d`). Blueprint direkonsiliasi (D-29–D-58).
   - `migrate:fresh --seed` DB pengembangan sempat ditolak pengaman auto mode, lalu dijalankan setelah izin pemilik.
   - Uji manual Playwright menemukan 2 cacat: spasi `&nbsp;` dari Quill 2 dan panah galeri. Keduanya diperbaiki (`929c13a`, 1845 tes).
   - Fase 2 di-merge ke `master` (fast-forward `51134af`), dan cabang `fase-2-master-data` dihapus.
6. **2026-10-05 (lanjutan):**
   - Penyusunan rencana fase 3 dimulai di cabang `fase-3-jadwal-dokter`.
   - Empat digest spesifikasi/kode dan kerangka kontrak (F3-R1–F3-R30) selesai.
   - Penulis T1–T12 selesai, sedangkan penulis T13–T16 dihentikan karena pemilik meminta jeda ("di stop dulu lanjut nanti").
7. **2026-10-05 (lanjutan 2):**
   - Penulis T13–T16 dikirim ulang dan selesai.
   - Catatan penulis A–D diputuskan (`ruling-konsistensi.md`, F3-P1–F3-P15).
   - Pemeriksa konsistensi 1 (T1–T12) dan 2 (T13–T16) selesai. Pemeriksa 2 sempat dihentikan pemilik, lalu dikirim ulang atas permintaan "lanjutkan".
   - Rencana fase 3 dirakit dan di-commit, lalu diajukan untuk persetujuan pemilik.
8. **2026-10-05 (lanjutan 3):**
   - Rencana fase 3 disetujui pemilik ("setuju").
   - Task 1–16 dieksekusi lewat subagent-driven-development (Sonnet 5.5). Putaran perbaikan terjadi di Task 3 (pembersihan spike tahan galat), Task 11 (filter larik 500 dan dokter arsip), dan Task 13 (panel filter mobile). Hasil: 2925 tes lulus.
   - Final review seluruh cabang: With fixes.
   - `migrate:fresh --seed` dijalankan dengan izin pemilik, lalu uji manual Playwright dan Lighthouse (laporan di `Documents\UNPAM-Uji-Manual-2026-10-05-fase-3`).
   - Dijeda atas permintaan pemilik (ganti jaringan) saat gelombang perbaikan final baru mulai. Perubahan setengah jadi disimpan di `git stash@{0}`.
9. **2026-10-05 (lanjutan 4):**
   - Dilanjutkan atas permintaan pemilik ("lanjutkan"). WIP F1 diterapkan dari stash, lalu satu agen perbaikan final menuntaskan F1–F3, F6, M1, dan M2 (`49f966a`..`606cfbe`).
   - Re-review terbatas: semua ADDRESSED. Lighthouse ulang 100/100/100, dan bagian 8 laporan uji manual dilengkapi.
   - Gerbang diverifikasi koordinator: 2933 tes lulus, Pint PASS, 50 route admin. Catatan Eksekusi rencana fase 3 (E1–E13) ditulis.
   - Pemilik memilih merge lokal: fase 3 di-merge ke `master` (fast-forward `1558f62`), suite pada hasil merge 2933 lulus, lalu cabang dan workspace SDD fase 3 dihapus.
10. **2026-10-05 (lanjutan 5):**
   - Penyusunan rencana fase 4a dimulai di cabang `fase-4a-konten-publik`.
   - Empat digest (data, HTTP/UI, tes dan bawaan, kode) dan kerangka kontrak F4A-R1–R30 ditulis koordinator. Kerangka lalu bertambah R31–R37 dari catatan penulis dan pemeriksa.
   - Lima penulis paralel menulis 17 tugas.
   - Empat dari lima pemeriksa konsistensi selesai.
   - Dijeda atas permintaan pemilik ("Stop dulu lanjut nanti, buatkan checkpoint"), dan pemeriksa K2 dihentikan.
11. **2026-10-06:**
   - Dilanjutkan atas permintaan pemilik ("lanjutkan"). Pemeriksa K2 (T5–T8) dikirim ulang dan selesai (1 Blocker, 3 Minor).
   - Putaran perbaikan oleh agen baru Sonnet 5.5 untuk penulis A, C, D, dan E, ditambah perbaikan langsung koordinator untuk T5/T8/T14/T17 dan kerangka.
   - Rencana fase 4a dirakit, di-self-review, lalu di-commit. Rencana diajukan ke pemilik untuk persetujuan.
   - Rencana disetujui pemilik ("lanjutkan"). Task 1–17 dieksekusi lewat subagent-driven-development (Sonnet 5.5); putaran perbaikan di Task 9 dan 15. Satu agen sempat terputus ENOTFOUND dan dilanjutkan ("coba lagi").
   - Final review: Dengan perbaikan; gelombang F1–F4 (`a57eccf`) lolos re-review. Gerbang: 4348 tes lulus.
   - `migrate:fresh --seed` sempat ditolak pengaman auto mode ("lanjutkan" kurang eksplisit), lalu dijalankan setelah pemilik menulis "Ya, jalankan migrate:fresh --seed". Uji manual Playwright dan Lighthouse (100) lulus. Catatan eksekusi E1–E14 ditulis; menunggu keputusan merge.
   - Pemilik memilih "1": fase 4a di-merge ke `master` (fast-forward `92173df`), suite pada hasil merge 4348 lulus, lalu cabang dan workspace fase 4a dihapus.
   - Rencana fase 4b disusun di cabang `fase-4b-fasilitas-kamar-kontak`: empat digest, kerangka F4B-R1–R40, empat penulis paralel (11 tugas), empat pemeriksa konsistensi, dan satu putaran perbaikan. Rencana dirakit, di-self-review, lalu di-commit.
   - Rencana fase 4b disetujui pemilik ("sesuai"). Task 1 dieksekusi dan lolos review (`01240a3`). Task 2 ditulis dan suite-nya lulus, lalu dijeda atas permintaan pemilik ("cukup hari ini dilanjut nanti, buatkan checkpoint"); WIP disimpan di `git stash@{0}`.
12. **2026-10-07:** Fase 4b dilanjutkan dari stash, Task 2–11 dieksekusi dan lolos review, final review Siap merge, gerbang 5200 tes, lalu di-merge ke `master` (rincian di §3c).
13. **2026-10-08:**
   - Dilanjutkan atas permintaan pemilik ("lanjutkan"). Pemilik menjawab K-07 = (a) dan K-22 = (a).
   - Penyusunan rencana fase 5 di cabang `fase-5-akun-pasien`:
     - lima digest (data, aturan, HTTP/UI, tes dan bawaan, kode);
     - kerangka F5-R1–R31;
     - empat penulis paralel (8 tugas);
     - tiga pemeriksa konsistensi. K1 menerapkan Task 1–4 ke salinan repo dan menjalankan suite: 5422 lulus setelah dua perbaikan.
     - satu putaran perbaikan oleh tiga fixer.
   - Rencana dirakit, di-self-review, lalu di-commit. Rencana diajukan ke pemilik.
   - Rencana disetujui pemilik bersama izin "Ya, jalankan migrate:fresh --seed".
   - Task 1–8 dieksekusi lewat subagent-driven-development (Sonnet 5.5) dan lolos review; satu putaran perbaikan dokumen di Task 8 (URL AKS-07).
   - Suite gerbang pertama menemukan satu tes fase 3 yang flaky (nama faker "Zulkarnain").
   - Final review: Siap merge (0 Critical/Important).
   - Gelombang perbaikan FX1–FX6 menambahkan ganti password atomik dengan pencabutan token reset, `max:255` token, aturan email login, tes JDW-14, README, dan blueprint. Re-review: semuanya ADDRESSED.
   - Gerbang: 5646 tes lulus, 141 route.
   - `migrate:fresh --seed` dijalankan dan `media:prune` membersihkan 167 berkas yatim.
   - Uji manual Playwright 9/9 sesuai, Lighthouse aksesibilitas 100 (`Documents\UNPAM-Uji-Manual-2026-10-08-fase-5`).
   - Catatan eksekusi E1–E12 ditulis.
   - Pemilik memilih "1": fase 5 di-merge ke `master` (fast-forward `43aa92c`), suite pada hasil merge 5646 lulus (29278 asersi), lalu cabang, workspace SDD fase 5, dan `.superpowers/plan-fase-5/` dihapus.
   - Penyusunan rencana fase 6 di cabang `fase-6-booking-wizard-bukti`:
     - Pemilik menjawab K-13 = (b) dan K-17 = (a).
     - Lima digest, kerangka F6-R1–R47, dan enam penulis paralel (13 tugas).
     - Pemeriksaan: uji coba integrasi dua bagian (T1–T6, lalu T7–T13; suite penuh salinan 6674 lulus) ditambah tiga pemeriksa statis.
     - Dua putaran fixer, lalu rencana dirakit dan di-commit, dan diajukan ke pemilik.
   - Rencana fase 6 disetujui pemilik ("lanjutkan"). Task 1–7 dieksekusi lewat subagent-driven-development (Sonnet 5.5) dan lolos review pada percobaan pertama (`64abcb2`..`44798e3`, 142 route).
   - Task 8 dijeda di tengah atas permintaan pemilik ("pause dulu, lanjut nanti buatkan checkpoint"). Commit 1 `51a8629` sudah masuk, sedangkan WIP langkah 7–10 disimpan di `git stash@{0}`.
14. **2026-10-09:**
   - Lanjutan fase 6: Task 8–13, final review dua kursi, gelombang perbaikan FX1–FX11, dan gerbang 6692 lulus. Pemilik memilih "1", dan fase 6 di-merge ke `master` (fast-forward `e4fe839`). Sesi dijeda sebelum suite hasil merge selesai (`c807668`).
   - Sesi 8:
     - Suite hasil merge pertama gagal 5802 tes dengan `[1044] Access denied` karena `mysql.db` rusak dan GRANT `unpam_app` hilang. Penyebabnya: start dari Control Panel menulis log galat MariaDB ke berkas tabel Aria, dan Stop dari Control Panel membunuh `mysqld`.
     - Pemulihan: `aria_chk --recover --zerofill`, `REPAIR TABLE`, dan enam GRANT dibuat ulang. Hasilnya `mysqlcheck --all-databases` OK, dengan cadangan di `C:\xampp3\mysql\backup-2026-10-09-aria\`.
     - Skrip `scripts\mysql-start.ps1` dan `scripts\mysql-stop.ps1` dibuat, dan shutdown bersih pertama terverifikasi. Panduannya di 16 §1.3 dan §13.
     - Suite pada hasil merge: 6692 lulus + 1 dilewati. Cabang dan workspace fase 6 dihapus.
     - Brainstorming fase 7 dimulai: dipecah 7a + 7b, K-04 = (a), urutan domain dulu lalu layar.
     - Desain 7a disetujui per bagian dan di-commit (`439b162`) di cabang baru `fase-7a-pasien-pendaftaran-antrean`.
     - Penyusunan rencana 7a: empat digest paralel, kerangka F7A-R1–R31, lima penulis paralel (T1–T9, T11, T12 selesai).
     - Pemilik menjeda ("pause dulu lanjut nanti, buatkan checkpoint"). Penulis T8–T10 dan T11–T13 dihentikan; T10 dan T13 belum ditulis.
     - Commit checkpoint dan tag `checkpoint/2026-10-09-fase-7a-penyusunan`.
