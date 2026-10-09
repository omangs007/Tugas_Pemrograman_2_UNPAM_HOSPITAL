<?php

namespace Tests\Feature\Concurrency;

use App\Enums\AppointmentStatus;
use App\Enums\BookingChannel;
use App\Models\Appointment;
use App\Models\AppointmentStatusHistory;
use App\Models\PatientProfile;
use App\Models\PracticeSession;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\Concurrency\ConcurrencyTestCase;

/**
 * Konkurensi fase 7a (F7A-R27): dua petugas menandai tidak hadir sesi yang sama; tandai tidak hadir massal melawan
 * pembatalan petugas; walk-in loket melawan booking online berebut kursi terakhir; dua petugas menghubungkan
 * profil walk-in yang sama ke dua akun berbeda. Proses anak (tests/Support/Concurrency/book-session.php) memakai jam
 * dinding asli: sesi "sudah berakhir" = kemarin, sesi untuk booking = besok.
 */
#[Group('concurrency')]
class Phase7aConcurrencyTest extends ConcurrencyTestCase
{
    private const LINKED_MESSAGE = 'Profil ini sudah terhubung ke sebuah akun.';

    /** Sesi kemarin 08.00 sampai 12.00: sudah berakhir pada jam asli kapan pun tes berjalan. */
    private function endedSession(int $quota = 6): PracticeSession
    {
        return $this->makeSession($quota, ['session_date' => today()->subDay()->toDateString()]);
    }

    /**
     * Appointment pada sesi, masing-masing milik pasien berakun baru (notifikasi `database` nyata).
     *
     * @param  list<AppointmentStatus>  $statuses
     * @return list<Appointment>
     */
    private function appointmentsOn(PracticeSession $session, array $statuses): array
    {
        $appointments = [];

        foreach ($statuses as $status) {
            $appointments[] = $this->bookAppointment($session, $status, ['patient_profile_id' => $this->makePatient()->id]);
        }

        return $appointments;
    }

    // ------------------------------------------------------------------ (a) dua petugas, satu sesi

    public function test_a_two_staff_marking_the_same_ended_session_mark_every_appointment_exactly_once(): void
    {
        $session = $this->endedSession();
        $appointments = $this->appointmentsOn($session, [
            AppointmentStatus::Pending,
            AppointmentStatus::Confirmed,
            AppointmentStatus::Pending,
            AppointmentStatus::Confirmed,
        ]);
        $first = $this->makeStaff();
        $second = $this->makeStaff();
        $startAt = $this->barrier(2);

        $one = $this->spawn('mark-no-show', ['session' => $session->id, 'as' => 'staff', 'actor-user' => $first->id], $startAt);
        $two = $this->spawn('mark-no-show', ['session' => $session->id, 'as' => 'staff', 'actor-user' => $second->id], $startAt);

        $results = $this->gather([$one, $two]);

        $this->assertNoInfrastructureErrors($results);
        $this->assertCount(2, $this->successes($results), 'Kedua petugas berhasil (yang sudah diubah dilewati): '.json_encode($results));
        $this->assertSame(
            4,
            array_sum(array_map(fn (array $result): int => (int) $result['marked'], $results)),
            'Jumlah yang ditandai kedua petugas harus tepat 4: '.json_encode($results),
        );

        foreach ($appointments as $appointment) {
            $this->assertSame(AppointmentStatus::NoShow, $appointment->fresh()->status);
            $this->assertSame(1, AppointmentStatusHistory::query()->where('appointment_id', $appointment->id)->count(), 'Riwayat tidak boleh ganda.');
        }

        $this->assertSame(4, $this->rowsSince('appointment_status_histories'));
        $this->assertSame(4, $this->rowsSince('audit_logs', 'appointment.status_changed'));
        $this->assertSame(4, $this->notificationsSince());
        $this->assertSame(4, (int) PracticeSession::query()->findOrFail($session->id)->booked_count, 'no_show tetap memakai kuota.');
        $this->assertSessionIsConsistent($session);
    }

    // ------------------------------------------------------------------ (b) tandai tidak hadir massal vs pembatalan

    public function test_b_mass_no_show_racing_a_staff_cancellation_leaves_exactly_one_final_status(): void
    {
        $session = $this->endedSession();
        [$contested, $other] = $this->appointmentsOn($session, [AppointmentStatus::Confirmed, AppointmentStatus::Pending]);
        $marker = $this->makeStaff();
        $canceller = $this->makeStaff();
        $startAt = $this->barrier(2);

        $mass = $this->spawn('mark-no-show', ['session' => $session->id, 'as' => 'staff', 'actor-user' => $marker->id], $startAt);
        $cancel = $this->spawn('transition', [
            'appointment' => $contested->id,
            'to' => 'cancelled',
            'as' => 'staff',
            'actor-user' => $canceller->id,
            'reason' => 'Dibatalkan petugas uji',
        ], $startAt);

        [$massResult, $cancelResult] = $this->gather([$mass, $cancel]);

        $this->assertNoInfrastructureErrors([$massResult, $cancelResult]);
        $this->assertTrue($massResult['ok'], 'Aksi massal tidak boleh gagal karena satu appointment berubah: '.json_encode($massResult));

        $cancelWon = $cancelResult['ok'] === true;
        $fresh = PracticeSession::query()->findOrFail($session->id);

        if ($cancelWon) {
            $this->assertSame(AppointmentStatus::Cancelled, $contested->fresh()->status);
            $this->assertSame(1, (int) $massResult['marked'], 'Massal hanya menandai appointment lain: '.json_encode($massResult));
            $this->assertSame(1, (int) $fresh->booked_count, 'Pembatalan melepas satu kursi.');
        } else {
            $this->assertSame('ALREADY_FINAL', $cancelResult['code'], json_encode($cancelResult));
            $this->assertSame(AppointmentStatus::NoShow, $contested->fresh()->status);
            $this->assertSame(2, (int) $massResult['marked'], json_encode($massResult));
            $this->assertSame(2, (int) $fresh->booked_count, 'no_show tetap memakai kuota.');
        }

        $this->assertSame(AppointmentStatus::NoShow, $other->fresh()->status);

        foreach ([$contested, $other] as $appointment) {
            $this->assertSame(1, AppointmentStatusHistory::query()->where('appointment_id', $appointment->id)->count(), 'Satu riwayat per appointment.');
        }

        $this->assertSame(2, $this->rowsSince('audit_logs', 'appointment.status_changed'), 'Satu audit per appointment.');
        $this->assertSame(2, $this->notificationsSince());
        $this->assertSessionIsConsistent($session);
    }

    // ------------------------------------------------------------------ (c) walk-in vs online, kursi terakhir

    public function test_c_a_counter_booking_and_an_online_booking_share_the_last_seat(): void
    {
        $session = $this->makeSession(1);
        $walkIn = PatientProfile::factory()->create();
        $online = $this->makePatient();
        $staff = $this->makeStaff();
        $startAt = $this->barrier(2);

        $counter = $this->spawn('book', ['patient' => $walkIn->id, 'session' => $session->id, 'as' => 'staff', 'actor-user' => $staff->id], $startAt);
        $web = $this->spawn('book', ['patient' => $online->id, 'session' => $session->id], $startAt);

        $results = $this->gather([$counter, $web]);

        $this->assertNoInfrastructureErrors($results);
        $this->assertCount(1, $this->successes($results), 'Tepat satu pemenang: '.json_encode($results));
        $this->assertSame(['QUOTA_FULL'], $this->failureCodes($results));

        $counterWon = $results[0]['ok'] === true;
        $appointment = Appointment::query()->where('practice_session_id', $session->id)->sole();

        $this->assertSame($counterWon ? $walkIn->id : $online->id, (int) $appointment->patient_profile_id);
        $this->assertSame($counterWon ? BookingChannel::Loket : BookingChannel::Online, $appointment->channel);
        $this->assertSame($counterWon ? AppointmentStatus::Confirmed : AppointmentStatus::Pending, $appointment->status);
        $this->assertSame(1, (int) PracticeSession::query()->findOrFail($session->id)->booked_count);
        $this->assertSame(1, $this->rowsSince('appointment_status_histories'));
        $this->assertSame(1, $this->rowsSince('audit_logs', 'appointment.created'));
        $this->assertSame($counterWon ? 0 : 1, $this->notificationsSince(), 'Walk-in tanpa akun tidak menerima notifikasi (NTF-03); pasien online menerima satu.');
        $this->assertSessionIsConsistent($session);
    }

    // ------------------------------------------------------------------ (d) dua petugas menghubungkan profil walk-in yang sama

    public function test_d_two_staff_linking_the_same_walk_in_to_different_accounts_one_wins_and_one_is_refused(): void
    {
        // Profil kosong akun dibuat lebih dulu (id kecil); walk-in dibuat terakhir (id terbesar): urutan kunci menaik.
        $accountOne = $this->makePatient();
        $accountTwo = $this->makePatient();
        $walkIn = PatientProfile::factory()->create();
        $first = $this->makeStaff();
        $second = $this->makeStaff();
        $startAt = $this->barrier(2);

        $one = $this->spawn('link', ['profile' => $walkIn->id, 'account' => $accountOne->user_id, 'actor-user' => $first->id], $startAt);
        $two = $this->spawn('link', ['profile' => $walkIn->id, 'account' => $accountTwo->user_id, 'actor-user' => $second->id], $startAt);

        $results = $this->gather([$one, $two]);

        $this->assertNoInfrastructureErrors($results);
        $this->assertCount(1, $this->successes($results), 'Tepat satu petugas berhasil: '.json_encode($results));

        $failure = array_values(array_filter($results, fn (array $result): bool => ($result['ok'] ?? false) !== true))[0];
        $this->assertSame('VALIDATION', $failure['code'], json_encode($failure));
        $this->assertSame(self::LINKED_MESSAGE, $failure['message']);

        [$winner, $loser] = $results[0]['ok'] === true ? [$accountOne, $accountTwo] : [$accountTwo, $accountOne];

        $this->assertSame((int) $winner->user_id, (int) $walkIn->fresh()->user_id, 'Walk-in terhubung ke akun pemenang.');
        $this->assertFalse(PatientProfile::query()->whereKey($winner->id)->exists(), 'Profil kosong akun pemenang dihapus.');
        $this->assertTrue(PatientProfile::query()->whereKey($loser->id)->exists(), 'Profil kosong akun yang kalah tetap ada.');
        $this->assertSame((int) $loser->user_id, (int) $loser->fresh()->user_id);
        $this->assertSame(1, PatientProfile::query()->where('user_id', $winner->user_id)->count(), 'Satu profil per akun (UNIQUE user_id).');
        $this->assertSame(1, $this->rowsSince('audit_logs', 'patient.account_linked'), 'Tepat satu audit hubungkan akun.');
    }
}
