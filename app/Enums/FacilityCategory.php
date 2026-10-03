<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum FacilityCategory: string
{
    use HasOptions;

    case Medis = 'medis';
    case Penunjang = 'penunjang';
    case Umum = 'umum';

    public function label(): string
    {
        return match ($this) {
            self::Medis => 'Fasilitas medis',
            self::Penunjang => 'Penunjang',
            self::Umum => 'Fasilitas umum',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Medis => 'bi-heart-pulse',
            self::Penunjang => 'bi-tools',
            self::Umum => 'bi-building',
        };
    }
}
