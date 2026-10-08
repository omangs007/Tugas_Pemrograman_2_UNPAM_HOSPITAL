<?php

namespace Tests\Unit\Services;

use App\Models\Appointment;
use App\Services\Booking\AppointmentProofService;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Tests\TestCase;

/**
 * SEK-13 (F6-R27): isi QR hanya `booking_code`. Tes mendekode SVG (bukan hanya membandingkan string): dark module
 * digambar ke GD lalu dibaca `QRCode::readFromBlob()`.
 */
class AppointmentProofServiceTest extends TestCase
{
    private const PREFIX = 'data:image/svg+xml;base64,';

    private function appointment(string $code): Appointment
    {
        $appointment = new Appointment;
        $appointment->forceFill(['booking_code' => $code, 'patient_profile_id' => 7, 'queue_number' => 3]);

        return $appointment;
    }

    /** Mendekode SVG QR keluaran chillerlan (path `M{x} {y} h1 v1 h-1Z` ber-fill hitam) menjadi teks payload. */
    public static function decodeSvg(string $svg): string
    {
        preg_match('/viewBox="0 0 (\d+) (\d+)"/', $svg, $box);
        $size = (int) $box[1];
        $scale = 8;

        $image = imagecreatetruecolor($size * $scale, $size * $scale);
        $white = (int) imagecolorallocate($image, 255, 255, 255);
        $black = (int) imagecolorallocate($image, 0, 0, 0);
        imagefill($image, 0, 0, $white);

        preg_match_all('/<path[^>]*fill="#000"[^>]*d="([^"]+)"/', $svg, $paths);

        foreach ($paths[1] as $d) {
            preg_match_all('/M(\d+) (\d+) h1 v1 h-1Z/', $d, $cells, PREG_SET_ORDER);

            foreach ($cells as $cell) {
                imagefilledrectangle($image, (int) $cell[1] * $scale, (int) $cell[2] * $scale, ((int) $cell[1] + 1) * $scale - 1, ((int) $cell[2] + 1) * $scale - 1, $black);
            }
        }

        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();

        return (string) (new QRCode(new QROptions(['readerUseImagickIfAvailable' => false])))->readFromBlob($png);
    }

    public function test_the_payload_is_the_booking_code_only(): void
    {
        $this->assertSame('UPH-7K3M-9QWA', (new AppointmentProofService)->qrPayload($this->appointment('UPH-7K3M-9QWA')));
    }

    public function test_the_svg_data_uri_is_valid_base64_svg_without_scripts(): void
    {
        $uri = (new AppointmentProofService)->qrSvgDataUri($this->appointment('UPH-7K3M-9QWA'));

        $this->assertStringStartsWith(self::PREFIX, $uri);

        $svg = base64_decode(substr($uri, strlen(self::PREFIX)), true);

        $this->assertIsString($svg);
        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('viewBox="0 0 ', $svg);
        $this->assertStringNotContainsString('<script', $svg);
        $this->assertStringNotContainsString('onload', $svg);
    }

    public function test_decoding_the_svg_returns_exactly_the_booking_code(): void
    {
        foreach (['UPH-7K3M-9QWA', 'UPH-0000-ZZZZ', 'UPH-ABCD-2345'] as $code) {
            $uri = (new AppointmentProofService)->qrSvgDataUri($this->appointment($code));

            $this->assertSame($code, self::decodeSvg((string) base64_decode(substr($uri, strlen(self::PREFIX)), true)), $code);
        }
    }

    public function test_the_output_is_deterministic_and_differs_between_codes(): void
    {
        $service = new AppointmentProofService;

        $this->assertSame($service->qrSvgDataUri($this->appointment('UPH-7K3M-9QWA')), $service->qrSvgDataUri($this->appointment('UPH-7K3M-9QWA')));
        $this->assertNotSame($service->qrSvgDataUri($this->appointment('UPH-7K3M-9QWA')), $service->qrSvgDataUri($this->appointment('UPH-7K3M-9QWB')));
    }

    public function test_other_attributes_of_the_appointment_never_influence_the_qr(): void
    {
        $service = new AppointmentProofService;
        $plain = $this->appointment('UPH-7K3M-9QWA');
        $other = $this->appointment('UPH-7K3M-9QWA');
        $other->forceFill(['patient_profile_id' => 99, 'queue_number' => 42, 'complaint' => 'Nama Pasien 0009900000011']);

        $this->assertSame($service->qrSvgDataUri($plain), $service->qrSvgDataUri($other));
    }
}
