<?php

namespace Tests\Support;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\DoctorSchedule;
use App\Models\PatientProfile;
use App\Models\PracticeSession;
use App\Models\ScheduleException;
use Illuminate\Support\Facades\DB;

/**
 * Pembantu tes untuk jadwal, sesi, pengecualian, dan appointment. Dipakai bersama RefreshDatabase.
 * Appointment dibuat lewat factory; pencacah sesi (`booked_count`, `last_queue_number`) dijaga konsisten
 * dengan CHECK `chk_sessions_quota` dan `chk_sessions_queue` serta invarian I-1/I-2.
 */
trait InteractsWithSchedules
{
    /**
     * Templat jadwal aktif (Senin 08.00-12.00, kuota 20) untuk dokter baru, kecuali ditimpa.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function createSchedule(array $overrides = []): DoctorSchedule
    {
        return DoctorSchedule::factory()->create($overrides);
    }

    /**
     * Sesi hasil templat (besok 08.00-12.00, kuota 20, open) dengan templat serasi; isi
     * `doctor_schedule_id => null` untuk sesi tambahan manual.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function createSession(array $overrides = []): PracticeSession
    {
        return PracticeSession::factory()->create($overrides);
    }

    /**
     * Pengecualian jadwal (cuti dokter tiga hari mulai 7 hari lagi), kecuali ditimpa. Jenis libur memakai
     * `type` dan `doctor_id => null`.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function createException(array $overrides = []): ScheduleException
    {
        return ScheduleException::factory()->create($overrides);
    }

    /**
     * Membuat appointment pada sesi dan memperbarui pencacah sesi di bawah kunci tulis: nomor antrean =
     * `last_queue_number + 1` (kecuali `queue_number` ditimpa), `last_queue_number` naik, dan `booked_count`
     * naik kecuali status `cancelled` (yang tidak memakai kuota). Pasien baru dibuat kecuali
     * `patient_profile_id` ditimpa. Melebihi kuota memicu CHECK `chk_sessions_quota` (QueryException).
     * Instance `$session` pemanggil diperbarui dan appointment dikembalikan dalam keadaan ter-refresh
     * (termasuk `active_flag`).
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function bookAppointment(
        PracticeSession $session,
        AppointmentStatus $status = AppointmentStatus::Confirmed,
        array $overrides = [],
    ): Appointment {
        $appointment = DB::transaction(function () use ($session, $status, $overrides): Appointment {
            $locked = PracticeSession::query()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();

            $queue = (int) ($overrides['queue_number'] ?? $locked->last_queue_number + 1);
            $patientId = $overrides['patient_profile_id'] ?? PatientProfile::factory()->create()->id;

            $appointment = Appointment::factory()->status($status)->create(array_merge([
                'patient_profile_id' => $patientId,
                'practice_session_id' => $locked->id,
                'doctor_id' => $locked->doctor_id,
                'clinic_id' => $locked->clinic_id,
                'session_date' => $locked->session_date->toDateString(),
                'queue_number' => $queue,
            ], $overrides));

            $locked->forceFill([
                'last_queue_number' => max((int) $locked->last_queue_number, $queue),
                'booked_count' => (int) $locked->booked_count + ($status === AppointmentStatus::Cancelled ? 0 : 1),
            ])->save();

            return $appointment;
        });

        $session->refresh();

        return $appointment->refresh();
    }
}
