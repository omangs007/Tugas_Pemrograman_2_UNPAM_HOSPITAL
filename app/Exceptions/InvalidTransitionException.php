<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Transisi status appointment yang tidak sah (F6-R7, F.8). Dua belas kode baku 00 F.5; pesan dari
 * `lang/id/booking.php` grup `transitions`. Subkelas `BookingException` agar handler yang sama menanganinya.
 */
final class InvalidTransitionException extends BookingException
{
    public const INVALID_TRANSITION = 'INVALID_TRANSITION';

    public const ALREADY_FINAL = 'ALREADY_FINAL';

    public const FORBIDDEN_TRANSITION = 'FORBIDDEN_TRANSITION';

    public const STALE_STATUS = 'STALE_STATUS';

    public const CANCEL_CUTOFF_PASSED = 'CANCEL_CUTOFF_PASSED';

    public const CONFIRM_AFTER_END = 'CONFIRM_AFTER_END';

    public const CHECKIN_NOT_TODAY = 'CHECKIN_NOT_TODAY';

    public const CHECKIN_NOT_OPEN = 'CHECKIN_NOT_OPEN';

    public const CHECKIN_CLOSED = 'CHECKIN_CLOSED';

    public const CORRECTION_NOT_SAME_DAY = 'CORRECTION_NOT_SAME_DAY';

    public const SESSION_NOT_ENDED = 'SESSION_NOT_ENDED';

    public const REASON_REQUIRED = 'REASON_REQUIRED';

    /** Kedua belas kode, urut F.8. */
    public const CODES = [
        self::INVALID_TRANSITION,
        self::ALREADY_FINAL,
        self::FORBIDDEN_TRANSITION,
        self::STALE_STATUS,
        self::CANCEL_CUTOFF_PASSED,
        self::CONFIRM_AFTER_END,
        self::CHECKIN_NOT_TODAY,
        self::CHECKIN_NOT_OPEN,
        self::CHECKIN_CLOSED,
        self::CORRECTION_NOT_SAME_DAY,
        self::SESSION_NOT_ENDED,
        self::REASON_REQUIRED,
    ];

    protected static function langGroup(): string
    {
        return 'transitions';
    }
}
