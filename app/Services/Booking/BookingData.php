<?php

declare(strict_types=1);

namespace App\Services\Booking;

use App\Enums\PaymentType;

/**
 * Data isian pendaftaran yang sudah divalidasi (F6-R13, F6-R21). Nomor BPJS atau nomor polis dipetakan ke
 * satu `paymentReference` (kolom `appointments.payment_reference`, terenkripsi). Normalisasi (buang spasi
 * dan "-") dilakukan Form Request sebelum validasi, bukan di sini. Nilai yang tidak sesuai jenis pembayaran
 * (mis. nomor pada pembayaran umum) sengaja TIDAK dibuang agar `BookingRules::assertBookable()` dapat menolaknya
 * dengan `INVALID_PAYMENT`.
 */
final class BookingData
{
    public function __construct(
        public readonly PaymentType $paymentType,
        public readonly ?string $paymentReference = null,
        public readonly ?int $insurancePartnerId = null,
        public readonly ?string $complaint = null,
        public readonly bool $saveBpjs = false,
    ) {}

    /**
     * @param  array<string, mixed>  $validated  keluaran `validated()` (kunci `payment_type`, `bpjs_number`,
     *                                           `policy_number`, `insurance_partner_id`, `complaint`, `save_bpjs`)
     *
     * @throws \ValueError bila `payment_type` tidak ada atau bukan nilai `PaymentType`
     */
    public static function fromValidated(array $validated): self
    {
        $type = $validated['payment_type'] ?? null;

        return new self(
            paymentType: $type instanceof PaymentType ? $type : PaymentType::from((string) $type),
            paymentReference: self::text($validated['bpjs_number'] ?? null) ?? self::text($validated['policy_number'] ?? null),
            insurancePartnerId: self::positiveInt($validated['insurance_partner_id'] ?? null),
            complaint: self::text($validated['complaint'] ?? null),
            saveBpjs: filter_var($validated['save_bpjs'] ?? false, FILTER_VALIDATE_BOOLEAN),
        );
    }

    /** Empat karakter terakhir referensi (`appointments.payment_reference_last4`), atau NULL bila kosong. */
    public function paymentReferenceLast4(): ?string
    {
        if ($this->paymentReference === null || $this->paymentReference === '') {
            return null;
        }

        return mb_substr($this->paymentReference, -4);
    }

    private static function text(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private static function positiveInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (is_string($value) && ctype_digit($value) && (int) $value > 0) {
            return (int) $value;
        }

        return null;
    }
}
