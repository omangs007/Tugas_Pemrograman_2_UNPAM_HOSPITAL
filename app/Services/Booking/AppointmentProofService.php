<?php

declare(strict_types=1);

namespace App\Services\Booking;

use App\Models\Appointment;
use App\Services\Settings\SettingsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bukti pendaftaran (00 F.10, F6-R26/F6-R27): isi QR, QR SVG untuk halaman, serta QR PNG dan PDF.
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

    /**
     * QR untuk PDF (F6-R27): PNG (chillerlan GD) sebagai data URI base64. Isi QR = `qrPayload()` (kode booking
     * saja). dompdf merender gambar raster dari data URI tanpa akses jarak jauh; PNG dipakai alih-alih SVG di PDF.
     */
    public function qrPngDataUri(Appointment $appointment): string
    {
        $options = new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'outputBase64' => true,
            'eccLevel' => EccLevel::M,
            'scale' => 6,
            'imageTransparent' => false,
        ]);

        return (string) (new QRCode($options))->render($this->qrPayload($appointment));
    }

    /**
     * Bukti PDF A5 potret (F6-R26): unduhan `bukti-{booking_code}.pdf`, tanpa cache. Otorisasi (pemilik) dilakukan
     * pemanggil sebelum method ini. Tanpa keluhan, alamat, telepon, tanggal lahir, atau nomor lengkap.
     */
    public function pdf(Appointment $appointment): Response
    {
        $appointment->loadMissing(['clinic', 'doctor', 'practiceSession', 'patientProfile', 'insurancePartner']);

        // SettingsService di-resolve di sini agar konstruktor kelas ini (milik Task 8) tidak berubah.
        $settings = app(SettingsService::class);

        $pdf = Pdf::loadView('pdf.appointment-proof', [
            'appointment' => $appointment,
            'siteName' => $settings->string('site.name', (string) config('app.name')),
            'logoDataUri' => $this->pdfLogoDataUri(),
            'qrPngDataUri' => $this->qrPngDataUri($appointment),
            'checkinMinutes' => $settings->bookingRules()->checkinOpenMinutes,
            'printedAt' => CarbonImmutable::now(),
        ])->setPaper('a5', 'portrait');

        $response = $pdf->download('bukti-'.$appointment->booking_code.'.pdf');
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    /** Logo lokal untuk PDF sebagai data URI SVG (vektor); NULL bila berkas tidak ada. */
    private function pdfLogoDataUri(): ?string
    {
        $path = public_path('images/placeholders/logo.svg');

        if (! is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        return $contents === false ? null : 'data:image/svg+xml;base64,'.base64_encode($contents);
    }
}
