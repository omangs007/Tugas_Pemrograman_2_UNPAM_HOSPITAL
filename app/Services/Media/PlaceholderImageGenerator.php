<?php

declare(strict_types=1);

namespace App\Services\Media;

use GdImage;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use RuntimeException;

/**
 * Pembuat gambar bitmap contoh untuk seeder (13 §9.2): latar warna token, pita dekoratif,
 * inisial pemilik besar di tengah, dan teks kecil "Foto contoh" di bagian bawah.
 * Seluruhnya lokal (GD): tanpa unduhan dari internet.
 */
final class PlaceholderImageGenerator
{
    /** Latar bergilir (token warna 00 M); dipilih deterministik dari label. */
    public const BACKGROUNDS = ['#0A4D8C', '#0E7C74', '#1E7D45', '#13A89E'];

    public const RIBBON = '#13A89E';

    /** Teks pada latar toska muda memakai warna gelap (00 M melarang teks putih di sana). */
    public const DARK_TEXT = '#08233B';

    public const LIGHT_TEXT = '#FFFFFF';

    public const JPEG_QUALITY = 82;

    /** Jenis gambar => [format, teks kecil di bawah]. */
    private const TYPES = [
        'photo' => ['jpeg', 'Foto contoh'],
        'cover' => ['jpeg', 'Foto contoh'],
        'gallery' => ['jpeg', 'Foto contoh'],
        'photos' => ['jpeg', 'Foto contoh'],
        'editor' => ['jpeg', 'Foto contoh'],
        'image' => ['jpeg', 'Banner contoh'],
        'logo' => ['png', 'Logo contoh'],
    ];

    private const FALLBACK_FONT = 5;

    public function __construct(private readonly ?string $fontPath = null) {}

    /**
     * Buat satu gambar dan bungkus sebagai UploadedFile mode uji. Pemanggil wajib menghapus berkas
     * sementara ($file->getPathname()) di blok `finally` setelah MediaService::store().
     */
    public function make(string $type, string $label, int $width, int $height): UploadedFile
    {
        if (! isset(self::TYPES[$type])) {
            throw new InvalidArgumentException('Jenis gambar contoh tidak dikenal: '.$type);
        }

        $label = trim($label);

        if ($label === '') {
            throw new InvalidArgumentException('Label gambar contoh wajib diisi.');
        }

        if ($width < 16 || $height < 16 || $width > 6000 || $height > 6000) {
            throw new InvalidArgumentException('Ukuran gambar contoh harus antara 16 dan 6000 piksel.');
        }

        [$format, $caption] = self::TYPES[$type];
        $background = self::BACKGROUNDS[crc32($label) % count(self::BACKGROUNDS)];
        $textHex = $background === self::RIBBON ? self::DARK_TEXT : self::LIGHT_TEXT;

        $canvas = imagecreatetruecolor($width, $height);

        if ($canvas === false) {
            throw new RuntimeException('GD gagal membuat kanvas gambar contoh.');
        }

        imagefill($canvas, 0, 0, $this->allocate($canvas, $background));
        imagefilledrectangle(
            $canvas,
            0,
            (int) round($height * 0.70),
            $width - 1,
            (int) round($height * 0.76),
            $this->allocate($canvas, self::RIBBON),
        );

        $this->drawText($canvas, strtoupper($label), $textHex, (int) round($height * 0.30), (int) round($height * 0.28));
        $this->drawText($canvas, $caption, $textHex, (int) round($height * 0.07), (int) round($height * 0.84));

        $path = tempnam(sys_get_temp_dir(), 'ph');

        if ($path === false) {
            throw new RuntimeException('Tidak dapat membuat berkas sementara untuk gambar contoh.');
        }

        $written = $format === 'png' ? imagepng($canvas, $path) : imagejpeg($canvas, $path, self::JPEG_QUALITY);

        if (! $written) {
            @unlink($path);

            throw new RuntimeException('GD gagal menulis gambar contoh.');
        }

        $name = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($label)), '-').($format === 'png' ? '.png' : '.jpg');

        return new UploadedFile($path, $name, $format === 'png' ? 'image/png' : 'image/jpeg', null, true);
    }

    /**
     * Teks rata tengah horizontal. $textHeight = tinggi huruf target (piksel), $top = tepi atas teks.
     */
    private function drawText(GdImage $canvas, string $text, string $hex, int $textHeight, int $top): void
    {
        $textHeight = max(8, $textHeight);

        if ($this->fontPath !== null && is_file($this->fontPath) && function_exists('imagettftext')) {
            $this->drawTrueType($canvas, $text, $hex, $textHeight, $top);

            return;
        }

        $this->drawBitmap($canvas, $text, $hex, $textHeight, $top);
    }

    private function drawTrueType(GdImage $canvas, string $text, string $hex, int $textHeight, int $top): void
    {
        $size = $textHeight * 0.75; // titik ≈ 0,75 × piksel
        $box = imagettfbbox($size, 0, (string) $this->fontPath, $text);

        if ($box === false) {
            $this->drawBitmap($canvas, $text, $hex, $textHeight, $top);

            return;
        }

        $textWidth = $box[2] - $box[0];
        $x = (int) round((imagesx($canvas) - $textWidth) / 2) - $box[0];
        $y = $top + $textHeight;

        imagettftext($canvas, $size, 0, $x, $y, $this->allocate($canvas, $hex), (string) $this->fontPath, $text);
    }

    /**
     * Cadangan tanpa berkas font (13 §9.2 [ASUMSI]): imagestring() pada kanvas kecil, lalu diperbesar.
     */
    private function drawBitmap(GdImage $canvas, string $text, string $hex, int $textHeight, int $top): void
    {
        $text = (string) preg_replace('/[^\x20-\x7E]/', '?', $text);
        $glyphWidth = imagefontwidth(self::FALLBACK_FONT);
        $glyphHeight = imagefontheight(self::FALLBACK_FONT);
        $smallWidth = max(1, $glyphWidth * strlen($text));

        $small = imagecreatetruecolor($smallWidth, $glyphHeight);

        if ($small === false) {
            return;
        }

        imagealphablending($small, false);
        imagesavealpha($small, true);
        imagefill($small, 0, 0, (int) imagecolorallocatealpha($small, 0, 0, 0, 127));
        imagestring($small, self::FALLBACK_FONT, 0, 0, $text, $this->allocate($small, $hex));

        $scale = max(1, intdiv($textHeight, $glyphHeight));
        $targetWidth = $smallWidth * $scale;

        // Tidak boleh melebihi 90% lebar kanvas.
        while ($scale > 1 && $targetWidth > imagesx($canvas) * 0.9) {
            $scale--;
            $targetWidth = $smallWidth * $scale;
        }

        $targetHeight = $glyphHeight * $scale;
        $x = intdiv(imagesx($canvas) - $targetWidth, 2);

        imagealphablending($canvas, true);
        imagecopyresized($canvas, $small, $x, $top, 0, 0, $targetWidth, $targetHeight, $smallWidth, $glyphHeight);
    }

    private function allocate(GdImage $image, string $hex): int
    {
        $hex = ltrim($hex, '#');
        $color = imagecolorallocate(
            $image,
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        );

        if ($color === false) {
            throw new RuntimeException('GD gagal mengalokasikan warna.');
        }

        return $color;
    }
}
