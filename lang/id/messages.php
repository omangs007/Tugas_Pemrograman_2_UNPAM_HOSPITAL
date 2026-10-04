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
    ],

    // Deskripsi baris audit yang ditulis AuditObserver.
    'audit' => [
        'created' => ':entity ":name" ditambahkan.',
        'updated' => ':entity ":name" diperbarui.',
        'deleted' => ':entity ":name" dihapus.',
    ],

];
