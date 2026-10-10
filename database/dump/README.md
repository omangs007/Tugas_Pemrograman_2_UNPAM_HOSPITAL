# Dump database pengembangan

`unpam_hospital-2026-10-10.sql` adalah ekspor database pengembangan `unpam_hospital` (MariaDB 10.4.32, `utf8mb4_unicode_ci`). Ekspor dibuat pada 2026-10-10 sesudah `migrate:fresh --seed` fase 7a dan uji manual fase 7a.

## Isi

- Data demo fase 1 sampai 7a: master data, jadwal, konten, fasilitas, akun demo, 13 profil pasien awal (6 di antaranya walk-in), dan 29 pendaftaran contoh dengan riwayat dan notifikasinya.
- Data hasil uji manual fase 7a: profil P000014 dan P000015, pendaftaran walk-in UPH-K4FF-2MX4, profil walk-in P000012 yang dihubungkan ke akun `mulyani.astuti@contoh.test` (profil kosong P000007 terhapus), dan beberapa perubahan status pendaftaran.
- Tabel `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, dan `password_reset_tokens` hanya disertakan strukturnya, tanpa isi (sesi login dan token tidak ikut).
- Akun demo memakai password `Demo12345` (hanya untuk lokal).

## Batasan

- **Kolom terenkripsi.** `patient_profiles.bpjs_number` dan `appointments.payment_reference` dienkripsi dengan `APP_KEY` mesin asal. Di mesin dengan `APP_KEY` lain nilainya tidak dapat dibaca, dan halaman yang membacanya bisa galat. Empat digit terakhir (`*_last4`) tetap terbaca.
- **Gambar tidak ikut.** Berkas media ada di `storage/app/public` yang tidak masuk git, sehingga gambar dari dump ini akan rusak.

Karena dua batasan itu, cara yang disarankan untuk mencoba aplikasi di mesin lain adalah membuat data demo baru:

```powershell
php artisan migrate:fresh --seed
```

## Memulihkan dump

Gunakan cara ini hanya bila Anda memang membutuhkan isi persis database ini. Cara ini menimpa database `unpam_hospital`.

```powershell
C:\xampp3\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS unpam_hospital CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
C:\xampp3\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 unpam_hospital -e "source database/dump/unpam_hospital-2026-10-10.sql"
```
