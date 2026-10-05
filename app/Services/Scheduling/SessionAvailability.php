<?php

declare(strict_types=1);

namespace App\Services\Scheduling;

use App\Enums\Availability;
use App\Models\PracticeSession;

/**
 * Keluaran DoctorAvailabilityQuery per sesi (11 §6.1). `availability` mengikuti mode filter (online atau
 * loket); dua flag `isBookable*` selalu dihitung untuk kedua mode.
 */
final readonly class SessionAvailability
{
    public function __construct(
        public PracticeSession $session,
        public int $remaining,
        public Availability $availability,
        public bool $isBookableOnline,
        public bool $isBookableCounter,
    ) {}
}
