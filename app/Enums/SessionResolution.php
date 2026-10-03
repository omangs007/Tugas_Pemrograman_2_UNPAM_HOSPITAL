<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use InvalidArgumentException;

enum SessionResolution: string
{
    use HasOptions;

    case Keep = 'keep';
    case Apply = 'apply';
    case Cancel = 'cancel';

    public function label(): string
    {
        return match ($this) {
            self::Keep => 'Pertahankan sesi lama',
            self::Apply => 'Terapkan perubahan jam',
            self::Cancel => 'Batalkan sesi',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Keep => 'secondary',
            self::Apply => 'primary',
            self::Cancel => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Keep => 'bi-arrow-counterclockwise',
            self::Apply => 'bi-clock-history',
            self::Cancel => 'bi-x-circle',
        };
    }

    /**
     * Resolusi yang diizinkan per sumber perubahan sesi (00 F.4).
     * Penyaringan lanjutan (sesi lampau, dokter nonaktif) dilakukan ScheduleChangeService di fase 7.
     *
     * @return list<self>
     */
    public static function allowedFor(string $source): array
    {
        return match ($source) {
            'exception', 'template_removed', 'manual_cancel', 'doctor_inactive' => [self::Keep, self::Cancel],
            'template_time', 'manual_time' => [self::Keep, self::Apply, self::Cancel],
            default => throw new InvalidArgumentException("Sumber perubahan sesi tidak dikenal: {$source}."),
        };
    }

    /** Hanya pembatalan sesi yang wajib disertai alasan. */
    public function requiresReason(): bool
    {
        return $this === self::Cancel;
    }

    /** Penjelasan singkat untuk layar penyelesaian sesi "perlu tindak lanjut". */
    public function description(): string
    {
        return match ($this) {
            self::Keep => 'Sesi tetap berjalan seperti semula. Perubahan jadwal tidak diterapkan pada sesi ini dan pendaftaran yang ada tidak berubah.',
            self::Apply => 'Jam sesi diubah mengikuti jadwal baru. Pasien yang sudah mendaftar diberi tahu tentang perubahan jam.',
            self::Cancel => 'Sesi dibatalkan dan seluruh pendaftaran aktif pada sesi ini ikut dibatalkan. Alasan pembatalan wajib diisi.',
        };
    }
}
