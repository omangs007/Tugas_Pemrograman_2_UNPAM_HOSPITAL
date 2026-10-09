<?php

namespace Tests\Unit\Services\Booking;

use App\Enums\AppointmentStatus;
use App\Services\Booking\TransitionCheck;
use Error;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * F7A-R5: pembawa hasil pemeriksaan satu tombol transisi. Murni, tanpa DB.
 */
class TransitionCheckTest extends TestCase
{
    public function test_it_is_allowed_when_there_is_no_error_code_and_has_empty_defaults(): void
    {
        $check = new TransitionCheck(AppointmentStatus::Confirmed);

        $this->assertSame(AppointmentStatus::Confirmed, $check->to);
        $this->assertNull($check->errorCode);
        $this->assertNull($check->message);
        $this->assertSame([], $check->replace);
        $this->assertTrue($check->allowed());
        $this->assertNull($check->opensAt());
    }

    public function test_it_is_not_allowed_when_it_has_an_error_code(): void
    {
        $check = new TransitionCheck(AppointmentStatus::Cancelled, 'CANCEL_CUTOFF_PASSED', 'Batas pembatalan online telah lewat.', ['minutes' => 120]);

        $this->assertFalse($check->allowed());
        $this->assertSame('CANCEL_CUTOFF_PASSED', $check->errorCode);
        $this->assertSame('Batas pembatalan online telah lewat.', $check->message);
        $this->assertSame(['minutes' => 120], $check->replace);
        $this->assertNull($check->opensAt());
    }

    public function test_opens_at_returns_the_time_only_for_checkin_not_open(): void
    {
        $notOpen = new TransitionCheck(AppointmentStatus::CheckedIn, 'CHECKIN_NOT_OPEN', 'Check-in belum dibuka. Check-in dibuka pukul 08.00.', ['time' => '08.00']);

        $this->assertSame('08.00', $notOpen->opensAt());
        $this->assertFalse($notOpen->allowed());

        // Kode lain yang kebetulan membawa placeholder time (mis. SESSION_NOT_ENDED) tidak boleh dianggap jam buka.
        $other = new TransitionCheck(AppointmentStatus::CheckedIn, 'CHECKIN_CLOSED', 'Sesi sudah berakhir.', ['time' => '12.00']);

        $this->assertNull($other->opensAt());
    }

    public function test_opens_at_is_null_when_the_time_placeholder_is_missing(): void
    {
        $this->assertNull((new TransitionCheck(AppointmentStatus::CheckedIn, 'CHECKIN_NOT_OPEN', 'Pesan tanpa jam.'))->opensAt());
    }

    public function test_the_properties_are_readonly_and_the_class_is_final(): void
    {
        $check = new TransitionCheck(AppointmentStatus::Completed);

        $this->assertTrue((new ReflectionClass(TransitionCheck::class))->isFinal());

        $this->expectException(Error::class);
        $this->expectExceptionMessage('Cannot modify readonly property');

        $check->errorCode = 'INVALID_TRANSITION';
    }
}
