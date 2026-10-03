<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum SessionStatus: string
{
    use HasOptions;

    case Open = 'open';
    case Closed = 'closed';
    case NeedsReview = 'needs_review';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Dibuka',
            self::Closed => 'Ditutup',
            self::NeedsReview => 'Perlu tindak lanjut',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'success',
            self::Closed => 'secondary',
            self::NeedsReview => 'warning',
            self::Cancelled => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Open => 'bi-door-open',
            self::Closed => 'bi-door-closed',
            self::NeedsReview => 'bi-exclamation-triangle',
            self::Cancelled => 'bi-x-octagon',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Open;
    }

    /** Semua status selain Open menolak pendaftaran baru. */
    public function blocksNewBookings(): bool
    {
        return $this !== self::Open;
    }
}
