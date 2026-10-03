<?php

namespace Tests\Support\Fixtures;

enum HostileStatus: string
{
    case Bad = 'bad';

    public function label(): string
    {
        return '<script>alert(1)</script>';
    }

    public function color(): string
    {
        return 'danger';
    }

    public function icon(): string
    {
        return 'bi-x-circle';
    }
}
