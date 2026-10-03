<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ExceptionType: string
{
    use HasOptions;

    case DoctorLeave = 'doctor_leave';
    case HospitalHoliday = 'hospital_holiday';
    case NationalHoliday = 'national_holiday';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::DoctorLeave => 'Cuti dokter',
            self::HospitalHoliday => 'Libur rumah sakit',
            self::NationalHoliday => 'Libur nasional',
            self::Other => 'Lainnya',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DoctorLeave => 'warning',
            self::HospitalHoliday => 'danger',
            self::NationalHoliday => 'info',
            self::Other => 'secondary',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::DoctorLeave => 'bi-person-dash',
            self::HospitalHoliday => 'bi-calendar-x',
            self::NationalHoliday => 'bi-flag',
            self::Other => 'bi-three-dots',
        };
    }

    /** Cuti dokter wajib menyebut dokter (00 E.2, CHECK chk_schedule_exceptions_scope). */
    public function requiresDoctor(): bool
    {
        return $this === self::DoctorLeave;
    }

    /** Libur rumah sakit/nasional berlaku untuk semua dokter; "Lainnya" bebas. */
    public function forbidsDoctor(): bool
    {
        return $this === self::HospitalHoliday || $this === self::NationalHoliday;
    }
}
