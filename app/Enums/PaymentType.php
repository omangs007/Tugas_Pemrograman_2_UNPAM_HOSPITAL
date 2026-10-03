<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum PaymentType: string
{
    use HasOptions;

    case Umum = 'umum';
    case Bpjs = 'bpjs';
    case Asuransi = 'asuransi';

    public function label(): string
    {
        return match ($this) {
            self::Umum => 'Umum/Pribadi',
            self::Bpjs => 'BPJS Kesehatan',
            self::Asuransi => 'Asuransi',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Umum => 'secondary',
            self::Bpjs => 'success',
            self::Asuransi => 'primary',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Umum => 'bi-wallet2',
            self::Bpjs => 'bi-shield-plus',
            self::Asuransi => 'bi-shield-check',
        };
    }

    public function requiresBpjsNumber(): bool
    {
        return $this === self::Bpjs;
    }

    public function requiresInsurancePartner(): bool
    {
        return $this === self::Asuransi;
    }

    public function allowsPolicyNumber(): bool
    {
        return $this === self::Asuransi;
    }
}
