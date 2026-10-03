<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum AccountType: string
{
    use HasOptions;

    case Patient = 'patient';
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::Patient => 'Pasien',
            self::Staff => 'Staf',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Patient => 'bi-person',
            self::Staff => 'bi-person-badge',
        };
    }

    public function isStaff(): bool
    {
        return $this === self::Staff;
    }

    public function isPatient(): bool
    {
        return $this === self::Patient;
    }
}
