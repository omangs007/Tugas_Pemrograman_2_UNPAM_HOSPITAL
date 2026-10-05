<?php

declare(strict_types=1);

namespace App\Services\Scheduling;

use Carbon\CarbonImmutable;

/**
 * Masukan DoctorAvailabilityQuery (11 §6.1). Tanggal: satu `date`, atau rentang `from`…`to`; bila ketiganya
 * kosong, kueri memakai hari ini … hari ini + booking.window_days.
 */
final readonly class AvailabilityFilter
{
    public function __construct(
        public ?int $clinicId = null,
        public ?int $doctorId = null,
        public ?int $specialtyId = null,
        public ?string $q = null,
        public ?CarbonImmutable $date = null,
        public ?CarbonImmutable $from = null,
        public ?CarbonImmutable $to = null,
        public bool $forLoket = false,
        public bool $includePast = false,
    ) {}
}
