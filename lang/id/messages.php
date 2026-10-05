<?php

return [

    /*
    | Pesan baku flash dan jejak audit (11 §11.1). Kalimat lengkap berbahasa Indonesia berakhir titik.
    | Pemakaian: __('messages.created', ['entity' => __('messages.entities.doctor')]).
    */

    'created' => ':entity berhasil ditambahkan.',
    'updated' => ':entity berhasil diperbarui.',
    'deleted' => ':entity berhasil dihapus.',

    // Penolakan hapus: :reason berisi kalimat lengkap dari deletionBlockedReason().
    'delete_blocked' => ':reason',

    'entities' => [
        'specialty' => 'Spesialisasi',
        'clinic' => 'Poliklinik',
        'doctor' => 'Dokter',
        'media' => 'Gambar',
        'schedule' => 'Templat jadwal',
        'exception' => 'Pengecualian jadwal',
        'session' => 'Sesi praktik',
    ],

    // Pesan aturan validasi kustom (12 §3.1); :attribute diganti nama bidang oleh validator.
    'rules' => [
        'person_name' => ':Attribute hanya boleh berisi huruf, spasi, titik, apostrof, dan tanda hubung.',
        'no_html' => ':Attribute tidak boleh mengandung tag HTML.',
    ],

    // Deskripsi baris audit yang ditulis AuditObserver.
    'audit' => [
        'created' => ':entity ":name" ditambahkan.',
        'updated' => ':entity ":name" diperbarui.',
        'deleted' => ':entity ":name" dihapus.',
    ],

];
