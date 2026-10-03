<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use App\Models\User;

enum ActorType: string
{
    use HasOptions;

    case Patient = 'patient';
    case Staff = 'staff';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Patient => 'Pasien',
            self::Staff => 'Staf',
            self::System => 'Sistem',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Patient => 'info',
            self::Staff => 'primary',
            self::System => 'secondary',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Patient => 'bi-person',
            self::Staff => 'bi-person-badge',
            self::System => 'bi-gear',
        };
    }

    /** Pelaku menurut jenis akun: staf ⇒ Staff, selain itu Patient. Pelaku sistem tidak berasal dari User. */
    public static function fromUser(User $user): self
    {
        return $user->account_type === AccountType::Staff ? self::Staff : self::Patient;
    }
}
