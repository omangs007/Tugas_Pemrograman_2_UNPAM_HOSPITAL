<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ContactMessageStatus: string
{
    use HasOptions;

    case New = 'new';
    case Read = 'read';
    case Replied = 'replied';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Baru',
            self::Read => 'Dibaca',
            self::Replied => 'Dibalas',
            self::Archived => 'Diarsipkan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::New => 'primary',
            self::Read => 'secondary',
            self::Replied => 'success',
            self::Archived => 'dark',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::New => 'bi-envelope',
            self::Read => 'bi-envelope-open',
            self::Replied => 'bi-reply',
            self::Archived => 'bi-archive',
        };
    }
}
