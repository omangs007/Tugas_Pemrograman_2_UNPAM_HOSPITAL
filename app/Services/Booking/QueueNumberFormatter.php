<?php

declare(strict_types=1);

namespace App\Services\Booking;

/**
 * Tampilan nomor antrean (F6-R6, BR-50): `{clinics.code}-{3 digit}`, mis. ANK-007. Nomor ≥ 1000 alami
 * menjadi empat digit atau lebih. Nilai tersimpan tetap bilangan bulat `appointments.queue_number`.
 */
final class QueueNumberFormatter
{
    public static function format(string $clinicCode, int $queueNumber): string
    {
        return sprintf('%s-%03d', $clinicCode, $queueNumber);
    }
}
