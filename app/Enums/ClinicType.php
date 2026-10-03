<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ClinicType: string
{
    use HasOptions;

    case Poliklinik = 'poliklinik';
    case Penunjang = 'penunjang';

    public function label(): string
    {
        return match ($this) {
            self::Poliklinik => 'Poliklinik',
            self::Penunjang => 'Layanan penunjang',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Poliklinik => 'bi-hospital',
            self::Penunjang => 'bi-clipboard2-pulse',
        };
    }
}
