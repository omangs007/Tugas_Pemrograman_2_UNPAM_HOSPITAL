<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Appointment;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Pemberitahuan perubahan jam praktik sesi kepada pasien berakun (F6-R14, 11 §10.1): kanal `database` dan `mail`.
 * Fase 6 hanya menyediakan kelas ini; pemicunya (resolusi sesi terdampak `apply`, yang mengisi
 * `appointments.schedule_changed_at`) dibangun fase 7. `$oldRange` dan `$newRange` berupa teks jam siap tampil,
 * mis. "09.00–13.00". Sinkron (bukan ShouldQueue). Tanpa nomor BPJS/polis, keluhan, alamat, dan telepon pasien.
 * `appointment.doctor`, `.clinic`, dan `.practiceSession` wajib sudah dimuat.
 */
final class AppointmentScheduleChangedNotification extends Notification
{
    public function __construct(
        public readonly Appointment $appointment,
        public readonly string $oldRange,
        public readonly string $newRange,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * @return array{appointment_id: int, booking_code: string, old_range: string, new_range: string, title: string, body: string}
     */
    public function toArray(object $notifiable): array
    {
        $appointment = $this->appointment;

        return [
            'appointment_id' => (int) $appointment->id,
            'booking_code' => (string) $appointment->booking_code,
            'old_range' => $this->oldRange,
            'new_range' => $this->newRange,
            'title' => 'Jadwal kunjungan berubah',
            'body' => "Jam praktik {$appointment->doctor->display_name} pada {$this->dateLabel()} kini {$this->newRange} (sebelumnya {$this->oldRange}). "
                ."Pendaftaran {$appointment->booking_code} tetap berlaku; batalkan atau hubungi rumah sakit bila jadwal baru tidak sesuai.",
        ];
    }

    /**
     * @param  User  $notifiable
     */
    public function toMail(object $notifiable): MailMessage
    {
        $appointment = $this->appointment;

        $mail = (new MailMessage)
            ->subject("Perubahan jadwal praktik pendaftaran {$appointment->booking_code}")
            ->greeting("Halo, {$notifiable->name}!")
            ->line('Jam praktik dokter untuk pendaftaran kunjungan Anda telah berubah.')
            ->line("Kode booking: {$appointment->booking_code}")
            ->line("Dokter: {$appointment->doctor->display_name}")
            ->line("Poliklinik: {$appointment->clinic->name}")
            ->line("Tanggal: {$this->dateLabel()}")
            ->line("Jam praktik semula: {$this->oldRange} WIB")
            ->line("Jam praktik baru: {$this->newRange} WIB")
            ->line("Nomor antrean: {$appointment->queue_label}")
            ->line('Pendaftaran Anda tetap berlaku. Jika jadwal baru tidak sesuai, batalkan pendaftaran atau hubungi rumah sakit.');

        $settings = app(SettingsService::class);
        $phone = trim($settings->string('contact.phone'));
        $whatsapp = $settings->whatsappUrl();

        if ($phone !== '') {
            $mail->line("Telepon rumah sakit: {$phone}");
        }

        if ($whatsapp !== null) {
            $mail->line("WhatsApp: [{$whatsapp}]({$whatsapp})");
        }

        return $mail
            ->action('Lihat Pendaftaran', route('patient.appointments.show', $appointment))
            ->line('Surel ini dikirim otomatis. Mohon tidak membalas surel ini.');
    }

    private function dateLabel(): string
    {
        return $this->appointment->session_date->translatedFormat('l, j F Y');
    }
}
