<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum DoctorStatus: string
{
    use HasOptions;

    case Active = 'active';
    case Inactive = 'inactive';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Inactive => 'Nonaktif',
            self::Archived => 'Diarsipkan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Inactive => 'secondary',
            self::Archived => 'dark',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Active => 'bi-check-circle',
            self::Inactive => 'bi-pause-circle',
            self::Archived => 'bi-archive',
        };
    }

    public function isActive(): bool
    {
        return $this === self::Active;
    }
}
