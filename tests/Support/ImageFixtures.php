<?php

namespace Tests\Support;

use GdImage;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Pabrik berkas gambar uji (dan berkas berbahaya) berbasis GD.
 *
 * Semua metode mengembalikan Illuminate\Http\UploadedFile mode uji. Berkas fisik berada di
 * direktori sementara per-proses dan dihapus saat proses PHP berakhir. Nama klien (parameter
 * $name) hanya dipakai sebagai nama asli unggahan; berkas fisik memakai nama acak.
 */
final class ImageFixtures
{
    private static ?string $directory = null;

    public static function jpeg(int $width = 800, int $height = 800, string $name = 'foto.jpg'): UploadedFile
    {
        return self::upload(self::jpegBytes($width, $height), $name, 'image/jpeg');
    }

    /**
     * JPEG yang membawa segmen EXIF (APP1) berisi koordinat GPS, disisipkan tepat setelah SOI (FFD8).
     */
    public static function jpegWithGps(int $width = 3000, int $height = 2000, string $name = 'gps.jpg'): UploadedFile
    {
        $bytes = self::jpegBytes($width, $height);

        return self::upload(substr($bytes, 0, 2).self::gpsExifSegment().substr($bytes, 2), $name, 'image/jpeg');
    }

    /**
     * JPEG valid yang diberi padding nol setelah penanda akhir sehingga ukuran berkas >= $kilobytes.
     */
    public static function oversizedJpeg(int $kilobytes, int $width = 800, int $height = 800, string $name = 'besar.jpg'): UploadedFile
    {
        $bytes = self::jpegBytes($width, $height);
        $padding = max(0, $kilobytes * 1024 - strlen($bytes));

        return self::upload($bytes.str_repeat("\0", $padding), $name, 'image/jpeg');
    }

    public static function png(int $width = 800, int $height = 600, string $name = 'gambar.png'): UploadedFile
    {
        return self::upload(self::pngBytes(self::canvas($width, $height)), $name, 'image/png');
    }

    /**
     * PNG dengan latar transparan penuh dan elips merah opak di tengah.
     */
    public static function transparentPng(int $width = 600, int $height = 400, string $name = 'transparan.png'): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagefilledellipse(
            $image,
            intdiv($width, 2),
            intdiv($height, 2),
            intdiv($width, 2),
            intdiv($height, 2),
            imagecolorallocatealpha($image, 200, 30, 30, 0),
        );

        return self::upload(self::pngBytes($image), $name, 'image/png');
    }

    public static function webp(int $width = 900, int $height = 600, string $name = 'gambar.webp'): UploadedFile
    {
        ob_start();
        imagewebp(self::canvas($width, $height), null, 80);

        return self::upload((string) ob_get_clean(), $name, 'image/webp');
    }

    /**
     * Isi PNG sungguhan dengan nama klien .jpg (ekstensi menipu).
     */
    public static function pngNamedJpg(int $width = 800, int $height = 600, string $name = 'foto.jpg'): UploadedFile
    {
        return self::upload(self::pngBytes(self::canvas($width, $height)), $name, 'image/jpeg');
    }

    /**
     * Header PNG lengkap (IHDR valid) diikuti data rusak: getimagesize() berhasil, dekode gagal.
     */
    public static function corruptPng(int $width = 600, int $height = 400, string $name = 'rusak.png'): UploadedFile
    {
        $png = self::pngBytes(self::canvas($width, $height));

        return self::upload(substr($png, 0, 33).str_repeat("\0", 64), $name, 'image/png');
    }

    public static function textNamedJpg(string $name = 'bukan-gambar.jpg'): UploadedFile
    {
        return self::upload("Ini hanya teks biasa, bukan gambar.\n", $name, 'image/jpeg');
    }

    public static function svgWithScript(string $name = 'logo.svg'): UploadedFile
    {
        return self::upload(
            '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="100"><script>alert(1)</script><rect width="200" height="100" fill="#0A4D8C"/></svg>',
            $name,
            'image/svg+xml',
        );
    }

    /**
     * Berkas "PHP berkepala JPEG": FFD8FFE0 diikuti kode PHP.
     */
    public static function phpWithJpegHeader(string $name = 'shell.php.jpg'): UploadedFile
    {
        return self::upload("\xFF\xD8\xFF\xE0<?php echo 'x'; ?>".str_repeat('A', 128), $name, 'image/jpeg');
    }

    public static function phpScript(string $name = 'x.phtml'): UploadedFile
    {
        return self::upload("<?php echo 'x';\n", $name, 'application/x-httpd-php');
    }

    /**
     * Penanda EXIF ("Exif\0\0") ada di byte berkas. Tidak bergantung pada ekstensi exif.
     */
    public static function hasExifMarker(string $path): bool
    {
        return str_contains((string) file_get_contents($path), "Exif\0\0");
    }

    /**
     * Tag GPS terbaca. Memakai exif_read_data() bila ekstensi aktif, selain itu jatuh ke penanda byte.
     */
    public static function hasGpsTag(string $path): bool
    {
        if (! function_exists('exif_read_data')) {
            return self::hasExifMarker($path);
        }

        $data = @exif_read_data($path);

        return is_array($data) && isset($data['GPSLatitudeRef']);
    }

    /**
     * Nilai alfa GD (0 = opak, 127 = transparan penuh) pada titik tertentu.
     */
    public static function alphaAt(string $binary, int $x, int $y): int
    {
        $image = imagecreatefromstring($binary);

        if ($image === false) {
            throw new RuntimeException('Gambar uji tidak dapat didekode.');
        }

        return (imagecolorat($image, $x, $y) >> 24) & 0x7F;
    }

    private static function canvas(int $width, int $height): GdImage
    {
        $image = imagecreatetruecolor($width, $height);
        $stripe = max(1, intdiv($height, 48));

        for ($y = 0, $i = 0; $y < $height; $y += $stripe, $i++) {
            $color = imagecolorallocate($image, 20 + ($i * 5) % 200, 80 + ($i * 3) % 120, 160);
            imagefilledrectangle($image, 0, $y, $width, min($height, $y + $stripe), $color);
        }

        return $image;
    }

    private static function jpegBytes(int $width, int $height): string
    {
        ob_start();
        imagejpeg(self::canvas($width, $height), null, 85);

        return (string) ob_get_clean();
    }

    private static function pngBytes(GdImage $image): string
    {
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    /**
     * Segmen APP1 "Exif" minimal: IFD0 menunjuk ke GPS IFD berisi LatitudeRef/Latitude/LongitudeRef/Longitude.
     */
    private static function gpsExifSegment(): string
    {
        $gps = pack('n', 4)
            .pack('nnN', 1, 2, 2)."S\0\0\0"
            .pack('nnN', 2, 5, 3).pack('N', 80)
            .pack('nnN', 3, 2, 2)."E\0\0\0"
            .pack('nnN', 4, 5, 3).pack('N', 104)
            .pack('N', 0);

        $tiff = 'MM'.pack('n', 42).pack('N', 8)
            .pack('n', 1).pack('nnNN', 0x8825, 4, 1, 26).pack('N', 0)
            .$gps
            .pack('NNNNNN', 6, 1, 21, 1, 0, 1)
            .pack('NNNNNN', 106, 1, 42, 1, 0, 1);

        $payload = "Exif\0\0".$tiff;

        return "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;
    }

    private static function upload(string $bytes, string $clientName, string $mimeType): UploadedFile
    {
        $path = self::directory().DIRECTORY_SEPARATOR.uniqid('fx_', true).'.bin';

        if (file_put_contents($path, $bytes) === false) {
            throw new RuntimeException("Gagal menulis berkas uji: {$path}");
        }

        return new UploadedFile($path, $clientName, $mimeType, null, true);
    }

    private static function directory(): string
    {
        if (self::$directory === null) {
            $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'unpam-hospital-fixtures-'.getmypid();

            if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
                throw new RuntimeException("Gagal membuat direktori fixture: {$directory}");
            }

            self::$directory = $directory;

            register_shutdown_function(static function () use ($directory): void {
                foreach (glob($directory.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
                    @unlink($file);
                }

                @rmdir($directory);
            });
        }

        return self::$directory;
    }
}
