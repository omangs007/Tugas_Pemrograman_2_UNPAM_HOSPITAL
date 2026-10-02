<?php

// Pemeriksa lingkungan UNPAM HOSPITAL (fase 0, kasus uji INS-01).
// Jalankan: C:\xampp3\php\php.exe scripts\check-env.php
// Exit code 0 = semua syarat terpenuhi, 1 = ada yang gagal.

$required = ['pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'fileinfo', 'gd', 'intl', 'zip', 'bcmath', 'curl'];
$missing = array_values(array_filter($required, fn (string $ext): bool => ! extension_loaded($ext)));
$gd = function_exists('gd_info') ? gd_info() : [];

$report = [
    'php_version' => PHP_VERSION,
    'php_ini' => php_ini_loaded_file(),
    'missing_extensions' => $missing,
    'gd_jpeg' => (bool) ($gd['JPEG Support'] ?? false),
    'gd_png' => (bool) ($gd['PNG Support'] ?? false),
    'gd_webp' => (bool) ($gd['WebP Support'] ?? false),
    'gd_freetype' => (bool) ($gd['FreeType Support'] ?? false),
    'date_timezone' => ini_get('date.timezone'),
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'memory_limit' => ini_get('memory_limit'),
];

$isPhp82 = version_compare(PHP_VERSION, '8.2.0', '>=') && version_compare(PHP_VERSION, '8.3.0', '<');
$ok = $isPhp82
    && $missing === []
    && $report['gd_jpeg'] && $report['gd_png'] && $report['gd_webp']
    && $report['date_timezone'] === 'Asia/Jakarta';

$report['status'] = $ok ? 'OK' : 'GAGAL';

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;

exit($ok ? 0 : 1);
