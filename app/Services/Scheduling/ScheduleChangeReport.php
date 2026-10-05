<?php

declare(strict_types=1);

namespace App\Services\Scheduling;

/**
 * Ringkasan dampak sebuah perubahan jadwal (11 §6.1), dipakai untuk flash dan audit. Tidak memuat data pasien.
 */
final readonly class ScheduleChangeReport
{
    public function __construct(
        public int $regenerated = 0,
        public int $updated = 0,
        public int $quotaAdjusted = 0,
        public int $needsReview = 0,
        public int $cancelled = 0,
        public int $reopened = 0,
    ) {}

    /**
     * Kalimat ringkasan (F3-R23): bagian bernilai > 0 secara berurutan, dipisah ", " dan diakhiri ".";
     * semua nol menghasilkan string kosong.
     */
    public function message(): string
    {
        $parts = [];

        foreach ([
            [$this->regenerated, 'dibangkitkan'],
            [$this->updated, 'diperbarui'],
            [$this->quotaAdjusted, 'disesuaikan kuotanya'],
            [$this->cancelled, 'dibatalkan'],
            [$this->reopened, 'dibuka kembali'],
            [$this->needsReview, 'perlu ditindaklanjuti petugas pendaftaran'],
        ] as [$count, $label]) {
            if ($count > 0) {
                $parts[] = "{$count} sesi {$label}";
            }
        }

        return $parts === [] ? '' : implode(', ', $parts).'.';
    }
}
