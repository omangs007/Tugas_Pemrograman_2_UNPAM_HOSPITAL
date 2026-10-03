<?php

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use InvalidArgumentException;
use UnitEnum;

class StatusBadge extends Component
{
    public string $label;

    public string $color;

    public string $icon;

    public function __construct(public UnitEnum $status)
    {
        foreach (['label', 'color', 'icon'] as $method) {
            if (! method_exists($status, $method)) {
                throw new InvalidArgumentException(sprintf(
                    'Enum %s tidak dapat dipakai pada status-badge: method %s() tidak ada.',
                    $status::class,
                    $method,
                ));
            }
        }

        $this->label = $status->label();
        $this->color = $status->color();
        $this->icon = $status->icon();
    }

    public function render(): View
    {
        return view('components.status-badge');
    }
}
