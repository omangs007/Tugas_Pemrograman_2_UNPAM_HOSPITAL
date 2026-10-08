<?php

/*
|--------------------------------------------------------------------------
| Konfigurasi dompdf (barryvdh/laravel-dompdf ^3.1) — bukti pendaftaran PDF
|--------------------------------------------------------------------------
|
| Struktur disalin dari `vendor/barryvdh/laravel-dompdf/config/dompdf.php`. Paket menggabungkan berkas ini
| DANGKAL (`mergeConfigFrom`): blok `options` di sini menggantikan seluruh `options` vendor, jadi semua kunci
| harus ada. Nilai khusus fase 6 (F6-R26): font bawaan DejaVu Sans (mendukung "•" dan "–"), tanpa akses jarak
| jauh, tanpa PHP/JavaScript di dokumen, protokol hanya `data://` dan `file://`, kertas A5 potret.
|
*/

return [

    // Lempar pengecualian pada peringatan dompdf (mati: peringatan tidak menggagalkan unduhan).
    'show_warnings' => false,

    // Menimpa path `public` bila perlu (NULL = `base_path('public')`).
    'public_path' => null,

    // Mengubah "€" dan "£" menjadi entitas HTML sebelum dirender.
    'convert_entities' => true,

    'options' => [
        // Folder font dan cache metrik font. WAJIB ada dan dapat ditulis web server: `storage/fonts`
        // (isinya di-gitignore; DejaVu Sans dibaca dari `vendor/dompdf/dompdf/lib/fonts`).
        'font_dir' => storage_path('fonts'),

        'font_cache' => storage_path('fonts'),

        // Folder sementara untuk dompdf.
        'temp_dir' => sys_get_temp_dir(),

        // Berkas lokal yang dibuka dompdf harus berada di dalam folder ini. Jangan diubah ke "/".
        'chroot' => realpath(base_path()),

        // Protokol yang diizinkan: hanya data URI (QR PNG, logo) dan berkas lokal. Tanpa http/https.
        'allowed_protocols' => [
            'data://' => ['rules' => []],
            'file://' => ['rules' => []],
        ],

        'artifactPathValidation' => null,

        'log_output_file' => null,

        // Subset font: hanya glyph yang dipakai tertanam (PDF ±22 KB, bukan ±880 KB dengan DejaVu Sans utuh).
        'enable_font_subsetting' => true,

        'pdf_backend' => 'CPDF',

        'default_media_type' => 'screen',

        // Bukti pendaftaran dicetak A5 potret (07 W-12).
        'default_paper_size' => 'a5',

        'default_paper_orientation' => 'portrait',

        // DejaVu Sans: font bawaan dompdf yang memuat "•" (U+2022) dan "–" (U+2013) (K-17 pilihan a).
        'default_font' => 'DejaVu Sans',

        'dpi' => 96,

        // Tidak pernah mengeksekusi PHP atau JavaScript tertanam di dokumen.
        'enable_php' => false,

        'enable_javascript' => false,

        // Tidak pernah mengambil sumber jarak jauh (SSRF); semua gambar berupa data URI.
        'enable_remote' => false,

        'allowed_remote_hosts' => null,

        'font_height_ratio' => 1.1,

        'enable_html5_parser' => true,
    ],

];
