<?php

return [

    /*
    | Pesan layanan dan layar pasien oleh petugas (fase 7a, F7A-R8/R10/R11/R12).
    | `link` = menghubungkan profil walk-in ke akun pasien (BR-106, BR-107; kode huruf besar = pesan ValidationException
    | pada kunci `account_email`), `status` = aktif/nonaktif profil (BR-105; galat pada kunci `is_active`),
    | `walk_in` = profil walk-in. Placeholder: :number (nomor pasien), :email, :count.
    */

    'link' => [
        'PROFILE_ALREADY_LINKED' => 'Profil ini sudah terhubung ke sebuah akun.',
        'ACCOUNT_NOT_FOUND' => 'Akun pasien dengan email tersebut tidak ditemukan.',
        'ACCOUNT_INACTIVE' => 'Akun pasien tidak aktif.',
        'ACCOUNT_HAS_HISTORY' => 'Akun ini sudah memiliki riwayat pendaftaran. Penggabungan dua profil berriwayat belum didukung.',
        'identity_required' => 'Anda harus menegaskan bahwa identitas pasien telah diverifikasi di loket.',
        'linked' => 'Profil pasien :number berhasil dihubungkan ke akun :email.',
    ],

    'status' => [
        'active_appointments' => 'Pasien masih memiliki :count pendaftaran aktif. Batalkan atau selesaikan terlebih dahulu.',
        'deactivated' => 'Profil pasien :number dinonaktifkan.',
        'activated' => 'Profil pasien :number diaktifkan kembali.',
    ],

    'walk_in' => [
        'created' => 'Profil pasien :number berhasil dibuat.',
        'updated' => 'Profil pasien :number diperbarui.',
        'similar' => 'Terdapat profil yang mirip. Pilih profil yang sudah ada atau centang konfirmasi untuk tetap membuat profil baru.',
        'consent_required' => 'Persetujuan privasi lisan dari pasien wajib dicentang.',
    ],

];
