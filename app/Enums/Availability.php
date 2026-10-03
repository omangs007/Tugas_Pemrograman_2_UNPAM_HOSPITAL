<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Hasil DoctorAvailabilityQuery per sesi. Tidak disimpan di database (00 D).
 */
enum Availability: string
{
    use HasOptions;

    case Available = 'available';
    case Full = 'full';
    case BookingClosed = 'booking_closed';
    case Closed = 'closed';
    case Leave = 'leave';
    case Holiday = 'holiday';
    case Cancelled = 'cancelled';
    case UnderReview = 'under_review';
    case Past = 'past';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Tersedia',
            self::Full => 'Penuh',
            self::BookingClosed => 'Pendaftaran online ditutup',
            self::Closed => 'Ditutup',
            self::Leave => 'Cuti',
            self::Holiday => 'Libur',
            self::Cancelled => 'Dibatalkan',
            self::UnderReview => 'Sedang diperbarui',
            self::Past => 'Sudah lewat',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Available => 'success',
            self::Full => 'danger',
            self::BookingClosed => 'secondary',
            self::Closed => 'secondary',
            self::Leave => 'warning',
            self::Holiday => 'info',
            self::Cancelled => 'secondary',
            self::UnderReview => 'warning',
            self::Past => 'secondary',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Available => 'bi-check-circle',
            self::Full => 'bi-people-fill',
            self::BookingClosed => 'bi-lock',
            self::Closed => 'bi-slash-circle',
            self::Leave => 'bi-person-dash',
            self::Holiday => 'bi-calendar-event',
            self::Cancelled => 'bi-x-circle',
            self::UnderReview => 'bi-arrow-repeat',
            self::Past => 'bi-clock-history',
        };
    }

    public function isBookable(): bool
    {
        return $this === self::Available;
    }

    /** Ketersediaan untuk sesi yang ditutup karena pengecualian jadwal (00 F.3: selain cuti/libur ⇒ cancelled). */
    public static function forException(?ExceptionType $type): self
    {
        return match ($type) {
            ExceptionType::DoctorLeave => self::Leave,
            ExceptionType::HospitalHoliday, ExceptionType::NationalHoliday => self::Holiday,
            ExceptionType::Other, null => self::Cancelled,
        };
    }
}
