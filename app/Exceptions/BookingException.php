<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Pelanggaran aturan booking F.5 (F6-R7, 00 J.6). Dilempar di dalam transaksi setelah penguncian sehingga
 * menggulung balik; handler di bootstrap/app.php (T7) mengubahnya menjadi flash merah + pengalihan (tidak
 * pernah halaman 500). Pesan Indonesia dari `lang/id/booking.php` (grup `errors`). Kelas ini TIDAK final:
 * `InvalidTransitionException` mewarisinya sehingga satu handler menangkap keduanya.
 */
class BookingException extends RuntimeException
{
    public const SESSION_UNAVAILABLE = 'SESSION_UNAVAILABLE';

    public const OUTSIDE_WINDOW = 'OUTSIDE_WINDOW';

    public const CUTOFF_PASSED = 'CUTOFF_PASSED';

    public const QUOTA_FULL = 'QUOTA_FULL';

    public const DUPLICATE_BOOKING = 'DUPLICATE_BOOKING';

    public const OVERLAPPING_BOOKING = 'OVERLAPPING_BOOKING';

    public const ACTIVE_LIMIT_REACHED = 'ACTIVE_LIMIT_REACHED';

    public const INVALID_PAYMENT = 'INVALID_PAYMENT';

    public const SYSTEM_BUSY = 'SYSTEM_BUSY';

    /** Kesembilan kode (8 aturan F.5 + SYSTEM_BUSY), urut F.5. */
    public const CODES = [
        self::SESSION_UNAVAILABLE,
        self::OUTSIDE_WINDOW,
        self::CUTOFF_PASSED,
        self::QUOTA_FULL,
        self::DUPLICATE_BOOKING,
        self::OVERLAPPING_BOOKING,
        self::ACTIVE_LIMIT_REACHED,
        self::INVALID_PAYMENT,
        self::SYSTEM_BUSY,
    ];

    /**
     * @param  array<string, mixed>  $context  mis. `practice_session_id`, `appointment_code` (DUPLICATE/OVERLAPPING)
     */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly array $context = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Pesan dari kunci `booking.{grup}.{kode}`; `$replace` mengisi placeholder Laravel (`:days`, `:minutes`,
     * `:max`, `:subjek`/`:Subjek`, `:doctor`, `:start`, `:end`, ...).
     *
     * @param  array<string, mixed>  $replace
     * @param  array<string, mixed>  $context
     */
    public static function make(string $errorCode, array $replace = [], array $context = [], ?Throwable $previous = null): static
    {
        return static::makeVariant($errorCode, $errorCode, $replace, $context, $previous);
    }

    /**
     * Kode yang sama dengan pesan dari kunci lain pada grup yang sama (mis. loket: `CUTOFF_PASSED` dengan
     * pesan `CUTOFF_PASSED_COUNTER`; pembatalan check-in: `INVALID_TRANSITION` dengan pesan `CANCEL_CHECKED_IN`).
     *
     * @param  array<string, mixed>  $replace
     * @param  array<string, mixed>  $context
     */
    public static function makeVariant(string $errorCode, string $messageKey, array $replace = [], array $context = [], ?Throwable $previous = null): static
    {
        return new static($errorCode, (string) __('booking.'.static::langGroup().'.'.$messageKey, $replace), $context, $previous);
    }

    /** BR-45: profil pasien tidak aktif atau tidak ditemukan (pemeriksaan ulang di `book()`). */
    public static function profileInactive(): self
    {
        return new self(self::SESSION_UNAVAILABLE, (string) __('booking.errors.PROFILE_INACTIVE'));
    }

    /** Grup `lang/id/booking.php` tempat pesan kelas ini berada. */
    protected static function langGroup(): string
    {
        return 'errors';
    }
}
