<?php

namespace Tests\Unit\Exceptions;

use App\Exceptions\BookingException;
use App\Exceptions\InvalidTransitionException;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use RuntimeException;
use Tests\TestCase;

/**
 * F6-R7, F6-R8, BKG-20 (sembilan kode BookingException punya pesan Indonesia unik), BKG-12 dan KON-05
 * (dua belas kode InvalidTransitionException). Memakai Tests\TestCase karena pesan berasal dari lang/id/booking.php.
 */
class BookingExceptionTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: array<string, mixed>, 2: string}>
     */
    public static function bookingMessages(): array
    {
        return [
            'SESSION_UNAVAILABLE' => ['SESSION_UNAVAILABLE', [], 'Sesi praktik ini tidak tersedia untuk pendaftaran. Silakan pilih sesi lain.'],
            'OUTSIDE_WINDOW' => ['OUTSIDE_WINDOW', ['days' => 30], 'Pendaftaran hanya dapat dilakukan untuk tanggal mulai hari ini sampai 30 hari ke depan.'],
            'CUTOFF_PASSED' => ['CUTOFF_PASSED', ['minutes' => 60], 'Pendaftaran online untuk sesi ini sudah ditutup (60 menit sebelum sesi dimulai). Silakan datang ke loket pendaftaran atau hubungi rumah sakit.'],
            'QUOTA_FULL' => ['QUOTA_FULL', [], 'Kuota sesi ini sudah penuh. Silakan pilih tanggal atau sesi lain.'],
            'DUPLICATE_BOOKING' => ['DUPLICATE_BOOKING', ['subjek' => 'Anda'], 'Anda sudah memiliki pendaftaran aktif pada sesi dokter ini.'],
            'OVERLAPPING_BOOKING' => ['OVERLAPPING_BOOKING', ['subjek' => 'Anda', 'doctor' => 'dr. Rani Oktaviani, Sp.PD', 'start' => '09.00', 'end' => '12.00'], 'Jadwal ini bertabrakan dengan pendaftaran lain Anda pada tanggal yang sama (dr. Rani Oktaviani, Sp.PD, 09.00–12.00).'],
            'ACTIVE_LIMIT_REACHED' => ['ACTIVE_LIMIT_REACHED', ['subjek' => 'Anda', 'max' => 3], 'Anda sudah memiliki 3 pendaftaran aktif yang akan datang (batas maksimum). Selesaikan atau batalkan salah satunya terlebih dahulu.'],
            'INVALID_PAYMENT' => ['INVALID_PAYMENT', [], 'Data pembayaran tidak sesuai: nomor BPJS (13 digit) wajib untuk pembayaran BPJS dan mitra asuransi wajib untuk pembayaran asuransi.'],
            'SYSTEM_BUSY' => ['SYSTEM_BUSY', [], 'Sistem sedang sibuk, silakan coba lagi beberapa saat.'],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: array<string, mixed>, 2: string}>
     */
    public static function transitionMessages(): array
    {
        return [
            'INVALID_TRANSITION' => ['INVALID_TRANSITION', ['dari' => 'pending', 'ke' => 'completed'], "Perubahan status dari 'pending' ke 'completed' tidak diperbolehkan."],
            'ALREADY_FINAL' => ['ALREADY_FINAL', ['status' => 'Selesai'], 'Pendaftaran ini sudah berstatus Selesai dan tidak dapat diubah lagi.'],
            'FORBIDDEN_TRANSITION' => ['FORBIDDEN_TRANSITION', [], 'Anda tidak memiliki izin untuk mengakses halaman ini.'],
            'STALE_STATUS' => ['STALE_STATUS', [], 'Status pendaftaran sudah berubah. Muat ulang halaman untuk melihat status terbaru.'],
            'CANCEL_CUTOFF_PASSED' => ['CANCEL_CUTOFF_PASSED', ['minutes' => 120, 'phone' => '(021) 0000-0000'], 'Batas pembatalan online telah lewat (maksimal 120 menit sebelum sesi dimulai). Silakan hubungi rumah sakit di (021) 0000-0000 atau melalui WhatsApp.'],
            'CONFIRM_AFTER_END' => ['CONFIRM_AFTER_END', [], 'Sesi sudah berakhir sehingga pendaftaran tidak dapat dikonfirmasi.'],
            'CHECKIN_NOT_TODAY' => ['CHECKIN_NOT_TODAY', ['date' => 'Senin, 12 Oktober 2026'], 'Check-in hanya dapat dilakukan pada tanggal kunjungan (Senin, 12 Oktober 2026).'],
            'CHECKIN_NOT_OPEN' => ['CHECKIN_NOT_OPEN', ['time' => '08.00'], 'Check-in belum dibuka. Check-in dibuka pukul 08.00.'],
            'CHECKIN_CLOSED' => ['CHECKIN_CLOSED', [], 'Sesi sudah berakhir sehingga check-in tidak dapat dilakukan.'],
            'CORRECTION_NOT_SAME_DAY' => ['CORRECTION_NOT_SAME_DAY', [], 'Koreksi check-in hanya dapat dilakukan pada hari yang sama.'],
            'SESSION_NOT_ENDED' => ['SESSION_NOT_ENDED', ['time' => '12.00'], 'Tandai tidak hadir hanya dapat dilakukan setelah sesi berakhir (pukul 12.00).'],
            'REASON_REQUIRED' => ['REASON_REQUIRED', [], 'Alasan wajib diisi untuk pembatalan atau koreksi check-in.'],
        ];
    }

    public function test_hierarchy_matches_the_blueprint(): void
    {
        $booking = new ReflectionClass(BookingException::class);
        $transition = new ReflectionClass(InvalidTransitionException::class);

        $this->assertSame(RuntimeException::class, $booking->getParentClass()->getName());
        $this->assertFalse($booking->isFinal(), 'BookingException tidak final: InvalidTransitionException mewarisinya.');
        $this->assertSame(BookingException::class, $transition->getParentClass()->getName());
        $this->assertTrue($transition->isFinal());
        $this->assertInstanceOf(RuntimeException::class, new BookingException('X', 'x'));
    }

    public function test_an_invalid_transition_is_caught_by_a_booking_exception_handler(): void
    {
        try {
            throw InvalidTransitionException::make(InvalidTransitionException::STALE_STATUS);
        } catch (BookingException $e) {
            $this->assertInstanceOf(InvalidTransitionException::class, $e);
            $this->assertSame('STALE_STATUS', $e->errorCode);
        }
    }

    public function test_code_lists_have_nine_and_twelve_codes_with_values_equal_to_their_names(): void
    {
        $this->assertSame(
            ['SESSION_UNAVAILABLE', 'OUTSIDE_WINDOW', 'CUTOFF_PASSED', 'QUOTA_FULL', 'DUPLICATE_BOOKING', 'OVERLAPPING_BOOKING', 'ACTIVE_LIMIT_REACHED', 'INVALID_PAYMENT', 'SYSTEM_BUSY'],
            BookingException::CODES,
        );
        $this->assertSame(
            ['INVALID_TRANSITION', 'ALREADY_FINAL', 'FORBIDDEN_TRANSITION', 'STALE_STATUS', 'CANCEL_CUTOFF_PASSED', 'CONFIRM_AFTER_END', 'CHECKIN_NOT_TODAY', 'CHECKIN_NOT_OPEN', 'CHECKIN_CLOSED', 'CORRECTION_NOT_SAME_DAY', 'SESSION_NOT_ENDED', 'REASON_REQUIRED'],
            InvalidTransitionException::CODES,
        );

        foreach (BookingException::CODES as $code) {
            $this->assertSame($code, constant(BookingException::class.'::'.$code), "Konstanta {$code}.");
        }

        foreach (InvalidTransitionException::CODES as $code) {
            $this->assertSame($code, constant(InvalidTransitionException::class.'::'.$code), "Konstanta {$code}.");
        }
    }

    public function test_the_constructor_exposes_code_context_and_previous_exception(): void
    {
        $previous = new RuntimeException('akar');
        $exception = new BookingException(BookingException::QUOTA_FULL, 'pesan', ['practice_session_id' => 9], $previous);

        $this->assertSame('QUOTA_FULL', $exception->errorCode);
        $this->assertSame('pesan', $exception->getMessage());
        $this->assertSame(['practice_session_id' => 9], $exception->context);
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function test_properties_are_readonly(): void
    {
        $exception = BookingException::make(BookingException::QUOTA_FULL);

        $this->expectException(\Error::class);

        $exception->errorCode = 'LAIN'; // @phpstan-ignore-line
    }

    /**
     * @param  array<string, mixed>  $replace
     */
    #[DataProvider('bookingMessages')]
    public function test_each_booking_code_has_the_documented_indonesian_message_bkg_20(string $code, array $replace, string $expected): void
    {
        $exception = BookingException::make($code, $replace, ['practice_session_id' => 4]);

        $this->assertSame($code, $exception->errorCode);
        $this->assertSame($expected, $exception->getMessage());
        $this->assertSame(['practice_session_id' => 4], $exception->context);
        $this->assertDoesNotMatchRegularExpression('/:[A-Za-z]/', $exception->getMessage(), 'Tidak boleh ada placeholder tersisa.');
        $this->assertStringEndsWith('.', $exception->getMessage());
    }

    public function test_the_nine_booking_messages_are_unique_bkg_20(): void
    {
        $messages = array_map(
            fn (array $case): string => BookingException::make($case[0], $case[1])->getMessage(),
            self::bookingMessages(),
        );

        $this->assertCount(9, $messages);
        $this->assertCount(9, array_unique($messages));
    }

    public function test_the_subject_placeholder_adapts_to_the_counter_channel(): void
    {
        $this->assertSame(
            'Pasien ini sudah memiliki pendaftaran aktif pada sesi dokter ini.',
            BookingException::make(BookingException::DUPLICATE_BOOKING, ['subjek' => 'pasien ini'])->getMessage(),
        );
        $this->assertSame(
            'Jadwal ini bertabrakan dengan pendaftaran lain pasien ini pada tanggal yang sama (dr. Budi, 08.00–10.00).',
            BookingException::make(BookingException::OVERLAPPING_BOOKING, ['subjek' => 'pasien ini', 'doctor' => 'dr. Budi', 'start' => '08.00', 'end' => '10.00'])->getMessage(),
        );
        $this->assertSame(
            'Pasien ini sudah memiliki 5 pendaftaran aktif yang akan datang (batas maksimum). Selesaikan atau batalkan salah satunya terlebih dahulu.',
            BookingException::make(BookingException::ACTIVE_LIMIT_REACHED, ['subjek' => 'pasien ini', 'max' => 5])->getMessage(),
        );
    }

    public function test_profile_inactive_uses_the_session_unavailable_code_with_the_br_45_message(): void
    {
        $exception = BookingException::profileInactive();

        $this->assertSame(BookingException::SESSION_UNAVAILABLE, $exception->errorCode);
        $this->assertSame('Profil pasien tidak aktif atau tidak ditemukan.', $exception->getMessage());
        $this->assertSame([], $exception->context);
    }

    public function test_make_variant_keeps_the_code_but_uses_another_message(): void
    {
        $exception = BookingException::makeVariant(BookingException::CUTOFF_PASSED, 'CUTOFF_PASSED_COUNTER', [], ['practice_session_id' => 2]);

        $this->assertSame('CUTOFF_PASSED', $exception->errorCode);
        $this->assertSame('Sesi ini sudah berakhir.', $exception->getMessage());
        $this->assertSame(['practice_session_id' => 2], $exception->context);
        $this->assertNotSame(BookingException::make(BookingException::CUTOFF_PASSED, ['minutes' => 60])->getMessage(), $exception->getMessage());
    }

    public function test_make_passes_the_previous_exception_through(): void
    {
        $previous = new RuntimeException('deadlock');

        $this->assertSame($previous, BookingException::make(BookingException::SYSTEM_BUSY, [], [], $previous)->getPrevious());
    }

    /**
     * @param  array<string, mixed>  $replace
     */
    #[DataProvider('transitionMessages')]
    public function test_each_transition_code_has_the_documented_indonesian_message(string $code, array $replace, string $expected): void
    {
        $exception = InvalidTransitionException::make($code, $replace, ['appointment_id' => 8]);

        $this->assertInstanceOf(InvalidTransitionException::class, $exception);
        $this->assertSame($code, $exception->errorCode);
        $this->assertSame($expected, $exception->getMessage());
        $this->assertSame(['appointment_id' => 8], $exception->context);
        $this->assertDoesNotMatchRegularExpression('/:[A-Za-z]/', $exception->getMessage());
        $this->assertStringEndsWith('.', $exception->getMessage());
    }

    public function test_the_twelve_transition_messages_are_unique(): void
    {
        $messages = array_map(
            fn (array $case): string => InvalidTransitionException::make($case[0], $case[1])->getMessage(),
            self::transitionMessages(),
        );

        $this->assertCount(12, $messages);
        $this->assertCount(12, array_unique($messages));
    }

    public function test_cancelling_a_checked_in_appointment_has_its_own_br_79_message(): void
    {
        $exception = InvalidTransitionException::makeVariant(InvalidTransitionException::INVALID_TRANSITION, 'CANCEL_CHECKED_IN');

        $this->assertSame('INVALID_TRANSITION', $exception->errorCode);
        $this->assertSame('Pendaftaran yang sudah check-in tidak dapat dibatalkan. Silakan hubungi petugas pendaftaran.', $exception->getMessage());
    }

    public function test_the_submit_in_progress_message_is_br_48(): void
    {
        $this->assertSame(
            'Permintaan Anda sedang diproses. Mohon tunggu sebentar dan jangan menekan tombol berulang kali.',
            __('booking.submit_in_progress'),
        );
    }

    public function test_the_language_file_has_exactly_the_documented_keys(): void
    {
        /** @var array<string, mixed> $lang */
        $lang = require lang_path('id/booking.php');

        $this->assertEqualsCanonicalizing(['errors', 'transitions', 'no_show', 'admin', 'submit_in_progress'], array_keys($lang));
        $this->assertEqualsCanonicalizing(
            [...BookingException::CODES, 'PROFILE_INACTIVE', 'CUTOFF_PASSED_COUNTER'],
            array_keys($lang['errors']),
        );
        $this->assertEqualsCanonicalizing(
            [...InvalidTransitionException::CODES, 'CANCEL_CHECKED_IN', 'SESSION_UNDER_REVIEW'],
            array_keys($lang['transitions']),
        );
        $this->assertEqualsCanonicalizing(['done', 'nothing', 'confirm_required'], array_keys($lang['no_show']));
        $this->assertEqualsCanonicalizing(
            ['confirmed', 'checked_in', 'completed', 'cancelled', 'corrected', 'invalid_target'],
            array_keys($lang['admin']),
        );
    }

    public function test_no_message_falls_back_to_its_translation_key(): void
    {
        foreach (BookingException::CODES as $code) {
            $this->assertStringStartsNotWith('booking.', BookingException::make($code)->getMessage());
        }

        foreach (InvalidTransitionException::CODES as $code) {
            $this->assertStringStartsNotWith('booking.', InvalidTransitionException::make($code)->getMessage());
        }
    }
}
