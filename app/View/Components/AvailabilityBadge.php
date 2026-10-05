<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Enums\Availability;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Lencana ketersediaan sesi (10 §3.4). Selalu menampilkan ikon, teks, dan warna (00 D); warna dan ikon diambil
 * dari enum `Availability` apa adanya (F3-R13). Bila `available` dan `remaining` diisi, teksnya "Tersedia · sisa {n}";
 * pada mode `compact` (sel grid mingguan) teks yang tampak "Sisa {n}" dan kata "Tersedia" tetap dibaca pembaca layar.
 */
class AvailabilityBadge extends Component
{
    public string $label;

    public string $color;

    public string $icon;

    /** Awalan khusus pembaca layar pada mode kompak ("Tersedia, "); kosong pada kasus lain. */
    public string $srPrefix = '';

    public function __construct(
        public Availability $availability,
        public ?int $remaining = null,
        public bool $compact = false,
    ) {
        $this->color = $availability->color();
        $this->icon = $availability->icon();
        $this->label = $availability->label();

        if ($availability === Availability::Available && $remaining !== null) {
            $left = max(0, $remaining);

            if ($compact) {
                $this->label = "Sisa {$left}";
                $this->srPrefix = 'Tersedia, ';
            } else {
                $this->label = "Tersedia · sisa {$left}";
            }
        }
    }

    public function render(): View
    {
        return view('components.availability-badge');
    }
}
