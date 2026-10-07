<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Pola LIKE "mengandung" yang aman (F4B-R26): karakter `\`, `%`, dan `_` pada kata kunci diperlakukan sebagai teks
 * biasa, bukan wildcard. Satu-satunya tempat escape ini ditulis; jangan menyalinnya ke kelas lain.
 */
final class LikePattern
{
    /**
     * `%{term}%` dengan `\`, `%`, dan `_` di-escape. Kata kunci tidak dipangkas; pemanggil yang memangkas.
     */
    public static function contains(string $term): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
    }
}
