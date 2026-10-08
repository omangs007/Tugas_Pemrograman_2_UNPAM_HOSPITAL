<?php

// Teks reset password pasien (F5-R16, 12 V-04/V-05).
// 'sent', 'user', dan 'throttled' SENGAJA sama: respons permintaan tautan tidak boleh membedakan email terdaftar,
// tidak terdaftar, staf, nonaktif, atau permintaan kedua dalam 60 detik (throttle broker `config('auth.passwords.users.throttle')`).
// Controller juga mengabaikan status broker; baris ini adalah lapis kedua (anti-enumerasi, bawaan fase 0).

return [

    'reset' => 'Password berhasil diubah. Silakan masuk dengan password baru.',
    'sent' => 'Jika email terdaftar, tautan untuk mengatur ulang password telah dikirim. Periksa kotak masuk Anda.',
    'throttled' => 'Jika email terdaftar, tautan untuk mengatur ulang password telah dikirim. Periksa kotak masuk Anda.',
    'token' => 'Tautan pengaturan ulang password tidak valid atau sudah kedaluwarsa. Silakan ajukan permintaan baru.',
    'user' => 'Jika email terdaftar, tautan untuk mengatur ulang password telah dikirim. Periksa kotak masuk Anda.',

];
