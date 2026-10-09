<?php

declare(strict_types=1);

namespace App\Services\Booking;

use App\Enums\AppointmentStatus;
use App\Exceptions\InvalidTransitionException;

/**
 * Hasil pemeriksaan satu tombol transisi (F7A-R5). `errorCode` null = tombol boleh tampil dan diterima server;
 * selain itu kode `InvalidTransitionException` yang akan dilempar `transition()` pada saat yang sama, lengkap
 * dengan pesan Indonesia dan placeholder-nya (`CHECKIN_NOT_OPEN` membawa `['time' => 'H.i']`).
 */
final class TransitionCheck
{
    /**
     * @param  array<string, scalar>  $replace  placeholder pesan
     */
    public function __construct(
        public readonly AppointmentStatus $to,
        public readonly ?string $errorCode = null,
        public readonly ?string $message = null,
        public readonly array $replace = [],
    ) {}

    public function allowed(): bool
    {
        return $this->errorCode === null;
    }

    /** Jam buka check-in `H.i` hanya untuk `CHECKIN_NOT_OPEN`; selain itu null. */
    public function opensAt(): ?string
    {
        if ($this->errorCode !== InvalidTransitionException::CHECKIN_NOT_OPEN) {
            return null;
        }

        $time = $this->replace['time'] ?? null;

        return is_string($time) ? $time : null;
    }
}
