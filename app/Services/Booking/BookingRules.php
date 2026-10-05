<?php

declare(strict_types=1);

namespace App\Services\Booking;

use App\Services\Settings\SettingsService;

/**
 * Snapshot pengaturan `booking.*` (11 §6.1), dibaca sekali per operasi. Fase 3 hanya menyediakan nilai;
 * `assertBookable()` (aturan F.5) ditambahkan fase 6 bersama BookingService.
 */
final readonly class BookingRules
{
    public function __construct(
        public int $windowDays,
        public int $onlineCutoffMinutes,
        public int $cancelCutoffMinutes,
        public int $checkinOpenMinutes,
        public int $maxActivePerPatient,
        public bool $autoConfirm,
    ) {}

    /**
     * Nilai cadangan sama dengan default seeder (00 E.7) sehingga tabel pengaturan yang kosong tidak
     * menghasilkan jendela 0 hari.
     */
    public static function fromSettings(SettingsService $settings): self
    {
        return new self(
            windowDays: $settings->int('booking.window_days', 30),
            onlineCutoffMinutes: $settings->int('booking.online_cutoff_minutes', 60),
            cancelCutoffMinutes: $settings->int('booking.cancel_cutoff_minutes', 120),
            checkinOpenMinutes: $settings->int('booking.checkin_open_minutes', 60),
            maxActivePerPatient: $settings->int('booking.max_active_per_patient', 3),
            autoConfirm: $settings->bool('booking.auto_confirm', false),
        );
    }
}
