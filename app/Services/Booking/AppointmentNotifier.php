<?php

declare(strict_types=1);

namespace App\Services\Booking;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Notifications\AppointmentScheduleChangedNotification;
use App\Notifications\AppointmentStatusChangedNotification;
use Closure;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Satu-satunya pintu pengiriman notifikasi pendaftaran (F6-R14, 06 §18). Aturan:
 *  - hanya pasien yang profilnya punya akun (`patient_profiles.user_id`); pasien walk-in tanpa akun tidak
 *    menerima apa pun dan tidak menimbulkan galat;
 *  - pengiriman dijadwalkan `DB::afterCommit` sehingga tidak ada notifikasi/surel dari transaksi yang
 *    di-rollback, dan tidak ada IO surel di dalam transaksi (bila dipanggil di luar transaksi, langsung jalan);
 *  - kegagalan kirim (kanal, surel, relasi) ditangkap dan dilaporkan (`report()`), tidak pernah dilempar ke
 *    pemanggil sehingga tidak membatalkan pendaftaran atau transisi yang sudah ter-commit (06 §18).
 */
final class AppointmentNotifier
{
    /**
     * Pendaftaran dibuat (`$from` NULL) atau status berubah. `$reason` (mis. alasan batal) ikut ke isi
     * notifikasi.
     */
    public function statusChanged(Appointment $appointment, ?AppointmentStatus $from, AppointmentStatus $to, ?string $reason = null): void
    {
        $this->afterCommit(
            $appointment,
            fn (): Notification => new AppointmentStatusChangedNotification($appointment, $from, $to, $reason),
        );
    }

    /**
     * Jam sesi berubah (resolusi `apply`, fase 7). `$oldRange` dan `$newRange` = teks jam, mis. "09.00–13.00".
     */
    public function scheduleChanged(Appointment $appointment, string $oldRange, string $newRange): void
    {
        $this->afterCommit(
            $appointment,
            fn (): Notification => new AppointmentScheduleChangedNotification($appointment, $oldRange, $newRange),
        );
    }

    /**
     * @param  Closure(): Notification  $notification  Dibuat SESUDAH commit, setelah relasi dimuat.
     */
    private function afterCommit(Appointment $appointment, Closure $notification): void
    {
        DB::afterCommit(function () use ($appointment, $notification): void {
            try {
                // Mode ketat melarang lazy loading pada koleksi; muat eksplisit hanya yang belum ada.
                $appointment->loadMissing(['patientProfile.user', 'doctor', 'clinic', 'practiceSession']);

                $recipient = $appointment->patientProfile->user;

                if ($recipient === null) {
                    return;
                }

                $recipient->notify($notification());
            } catch (Throwable $e) {
                report($e);
            }
        });
    }
}
