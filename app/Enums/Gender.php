<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum Gender: string
{
    use HasOptions;

    case Male = 'L';
    case Female = 'P';

    public function label(): string
    {
        return match ($this) {
            self::Male => 'Laki-laki',
            self::Female => 'Perempuan',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Male => 'bi-gender-male',
            self::Female => 'bi-gender-female',
        };
    }
}
