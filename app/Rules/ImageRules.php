<?php

namespace App\Rules;

use App\Services\Media\MediaService;
use InvalidArgumentException;

/**
 * Aturan dan pesan unggahan gambar per koleksi (12 §3.2). Sumber angka: MediaService::COLLECTIONS.
 */
final class ImageRules
{
    public const INVALID_IMAGE = 'File tidak dapat diproses sebagai gambar yang valid.';

    public const NOT_IMAGE = 'File harus berupa gambar.';

    public const WRONG_TYPE = 'File harus berupa gambar JPG, PNG, atau WebP.';

    public const UPLOAD_FAILED = 'Gambar gagal diunggah. Ukuran file melebihi batas server.';

    /**
     * @return list<string>
     */
    public static function for(string $collection): array
    {
        $config = self::config($collection);
        [$minWidth, $minHeight] = $config['min'];
        $max = MediaService::MAX_DIMENSION;

        return [
            'file',
            'image',
            'mimes:jpg,jpeg,png,webp',
            'mimetypes:image/jpeg,image/png,image/webp',
            'max:'.$config['max_kb'],
            "dimensions:min_width={$minWidth},min_height={$minHeight},max_width={$max},max_height={$max}",
        ];
    }

    /**
     * Pesan untuk `$validator`/`Form Request::messages()`; kunci memakai nama field ($field).
     *
     * @return array<string, string>
     */
    public static function messages(string $field, string $collection): array
    {
        $config = self::config($collection);
        [$minWidth, $minHeight] = $config['min'];
        $max = MediaService::MAX_DIMENSION;
        $maxMb = intdiv($config['max_kb'], 1024);
        $sizeSubject = $collection === 'image' ? 'banner' : 'gambar';
        $dimensionSubject = match ($collection) {
            'image' => 'banner',
            'logo' => 'logo',
            default => 'gambar',
        };

        return [
            "{$field}.file" => self::NOT_IMAGE,
            "{$field}.image" => self::NOT_IMAGE,
            "{$field}.mimes" => self::WRONG_TYPE,
            "{$field}.mimetypes" => self::WRONG_TYPE,
            "{$field}.max" => "Ukuran {$sizeSubject} maksimal {$maxMb} MB.",
            "{$field}.dimensions" => "Dimensi {$dimensionSubject} minimal {$minWidth} x {$minHeight} piksel dan maksimal {$max} x {$max} piksel.",
            "{$field}.uploaded" => self::UPLOAD_FAILED,
        ];
    }

    /**
     * @return array{single: bool, disk: string, max_kb: int, min: array{0: int, 1: int}}
     */
    private static function config(string $collection): array
    {
        return MediaService::COLLECTIONS[$collection]
            ?? throw new InvalidArgumentException("Koleksi media tidak dikenal: {$collection}.");
    }
}
