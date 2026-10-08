<?php

declare(strict_types=1);

namespace App\Services\Booking;

use App\Models\Appointment;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use RuntimeException;

/**
 * Bukti pendaftaran (00 F.10, F6-R26/F6-R27). T8: isi QR dan QR SVG untuk halaman; T10 menambah QR PNG dan PDF.
 * Otorisasi (AppointmentPolicy::view) dijalankan pemanggil SEBELUM service ini dipanggil.
 */
final class AppointmentProofService
{
    private const SVG_PREFIX = 'data:image/svg+xml;base64,';

    /** Isi QR hanya kode booking (00 F.7, SEK-13): tanpa nama, tanggal lahir, BPJS, atau id numerik. */
    public function qrPayload(Appointment $appointment): string
    {
        return $appointment->booking_code;
    }

    /**
     * QR sebagai data URI SVG base64 untuk `<img src>` (CSP `img-src 'self' data:`); tidak pernah dirender dengan
     * `{!! !!}`.
     */
    public function qrSvgDataUri(Appointment $appointment): string
    {
        $options = new QROptions([
            'outputInterface' => QRMarkupSVG::class,
            'outputBase64' => true,
            'eccLevel' => EccLevel::M,
        ]);

        $uri = (new QRCode($options))->render($this->qrPayload($appointment));

        if (! is_string($uri) || ! str_starts_with($uri, self::SVG_PREFIX)) {
            throw new RuntimeException('QR SVG tidak dapat dibuat.');
        }

        return $uri;
    }
}
