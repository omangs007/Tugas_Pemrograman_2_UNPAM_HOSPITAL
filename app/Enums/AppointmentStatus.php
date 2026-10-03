<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum AppointmentStatus: string
{
    use HasOptions;

    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case CheckedIn = 'checked_in';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    /** Status "aktif": pendaftaran masih berjalan (00 D). Wajib selaras dengan kolom generated active_flag. */
    public const ACTIVE = [self::Pending, self::Confirmed, self::CheckedIn];

    /** Semua status kecuali Cancelled: masih memakai kuota sesi. */
    public const QUOTA_CONSUMING = [self::Pending, self::Confirmed, self::CheckedIn, self::Completed, self::NoShow];

    /** Status akhir: tidak ada transisi lanjutan biasa. */
    public const FINAL = [self::Completed, self::Cancelled, self::NoShow];

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu konfirmasi',
            self::Confirmed => 'Dikonfirmasi',
            self::CheckedIn => 'Hadir/check-in',
            self::Completed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
            self::NoShow => 'Tidak hadir',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Confirmed => 'primary',
            self::CheckedIn => 'info',
            self::Completed => 'success',
            self::Cancelled => 'secondary',
            self::NoShow => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'bi-hourglass-split',
            self::Confirmed => 'bi-check-circle',
            self::CheckedIn => 'bi-person-check',
            self::Completed => 'bi-check2-all',
            self::Cancelled => 'bi-x-circle',
            self::NoShow => 'bi-person-x',
        };
    }

    /** @return list<self> */
    public static function active(): array
    {
        return self::ACTIVE;
    }

    /** @return list<self> */
    public static function quotaConsuming(): array
    {
        return self::QUOTA_CONSUMING;
    }

    /** @return list<self> */
    public static function finals(): array
    {
        return self::FINAL;
    }

    public function isActive(): bool
    {
        return in_array($this, self::ACTIVE, true);
    }

    public function consumesQuota(): bool
    {
        return in_array($this, self::QUOTA_CONSUMING, true);
    }

    public function isFinal(): bool
    {
        return in_array($this, self::FINAL, true);
    }
}
