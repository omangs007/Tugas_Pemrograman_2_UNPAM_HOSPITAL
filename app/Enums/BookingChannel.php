<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum BookingChannel: string
{
    use HasOptions;

    case Online = 'online';
    case Loket = 'loket';

    public function label(): string
    {
        return match ($this) {
            self::Online => 'Online',
            self::Loket => 'Loket',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Online => 'info',
            self::Loket => 'secondary',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Online => 'bi-globe2',
            self::Loket => 'bi-building',
        };
    }
}
