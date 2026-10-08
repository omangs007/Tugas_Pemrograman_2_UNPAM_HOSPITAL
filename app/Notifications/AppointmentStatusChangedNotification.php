<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Pemberitahuan perubahan status pendaftaran kepada pasien berakun (F6-R14, 11 §10.1, K-13 = b): kanal `database`
 * untuk setiap status tujuan dan kanal `mail` hanya untuk `confirmed` dan `cancelled`, kecuali koreksi check-in (R-b). Dipakai saat pendaftaran
 * dibuat (`$from` NULL) dan pada setiap transisi. Sinkron (bukan ShouldQueue; antrean dirancang fase 7, D-149).
 * Isi sengaja tanpa nomor BPJS/polis, keluhan, alamat, dan telepon (BR-174, NTF-01). `appointment.doctor`,
 * `.clinic`, dan `.practiceSession` wajib sudah dimuat (AppointmentNotifier memuatnya; mode ketat).
 */
final class AppointmentStatusChangedNotification extends Notification
{
    public function __construct(
        public readonly Appointment $appointment,
        public readonly ?AppointmentStatus $from,
        public readonly AppointmentStatus $to,
        public readonly ?string $reason = null,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $this->sendsMail() ? ['database', 'mail'] : ['database'];
    }

    /**
     * R-b (06 §18): surel untuk `cancelled` dan `confirmed`, KECUALI koreksi check-in (`checked_in` kembali ke
     * `confirmed`) yang hanya masuk kotak masuk (`database`).
     */
    private function sendsMail(): bool
    {
        if ($this->to === AppointmentStatus::Cancelled) {
            return true;
        }

        return $this->to === AppointmentStatus::Confirmed && $this->from !== AppointmentStatus::CheckedIn;
    }

    /**
     * Isi baris `notifications.data` (11 §10.1).
     *
     * @return array{appointment_id: int, booking_code: string, status: string, title: string, body: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'appointment_id' => (int) $this->appointment->id,
            'booking_code' => (string) $this->appointment->booking_code,
            'status' => $this->to->value,
            'title' => $this->title(),
            'body' => $this->body(),
        ];
    }

    /**
     * @param  User  $notifiable
     */
    public function toMail(object $notifiable): MailMessage
    {
        $appointment = $this->appointment;

        $mail = (new MailMessage)
            ->subject("Status pendaftaran {$appointment->booking_code}: {$this->to->label()}")
            ->greeting("Halo, {$notifiable->name}!")
            ->line($this->mailIntro())
            ->line("Kode booking: {$appointment->booking_code}")
            ->line("Dokter: {$appointment->doctor->display_name}")
            ->line("Poliklinik: {$appointment->clinic->name}")
            ->line("Jadwal: {$this->dateLabel()}, pukul {$appointment->practiceSession->time_range} WIB")
            ->line("Nomor antrean: {$appointment->queue_label}")
            ->line("Status: {$this->to->label()}");

        $reason = $this->reasonSentence();

        if ($reason !== null) {
            $mail->line($reason);
        }

        return $mail
            ->action('Lihat Pendaftaran', route('patient.appointments.show', $appointment))
            ->line('Surel ini dikirim otomatis. Mohon tidak membalas surel ini.');
    }

    private function isCheckInCorrection(): bool
    {
        return $this->from === AppointmentStatus::CheckedIn && $this->to === AppointmentStatus::Confirmed;
    }

    private function title(): string
    {
        if ($this->isCheckInCorrection()) {
            return 'Check-in dikoreksi';
        }

        return match ($this->to) {
            AppointmentStatus::Pending => 'Pendaftaran diterima',
            AppointmentStatus::Confirmed => 'Pendaftaran dikonfirmasi',
            AppointmentStatus::CheckedIn => 'Check-in berhasil',
            AppointmentStatus::Completed => 'Kunjungan selesai',
            AppointmentStatus::Cancelled => 'Pendaftaran dibatalkan',
            AppointmentStatus::NoShow => 'Ditandai tidak hadir',
        };
    }

    private function body(): string
    {
        $code = $this->appointment->booking_code;
        $queue = $this->appointment->queue_label;
        $visit = $this->visit();

        if ($this->isCheckInCorrection()) {
            $body = "Check-in untuk pendaftaran {$code} ({$visit}) dikoreksi oleh petugas. Status kembali menjadi Dikonfirmasi dan nomor antrean Anda tetap {$queue}.";
        } else {
            $body = match ($this->to) {
                AppointmentStatus::Pending => "Pendaftaran {$code} untuk {$visit} telah diterima dan menunggu konfirmasi petugas. Nomor antrean Anda {$queue}.",
                AppointmentStatus::Confirmed => "Pendaftaran {$code} untuk {$visit} telah dikonfirmasi. Nomor antrean Anda {$queue}.",
                AppointmentStatus::CheckedIn => "Kehadiran Anda untuk pendaftaran {$code} ({$visit}) telah dicatat. Nomor antrean Anda {$queue}.",
                AppointmentStatus::Completed => "Kunjungan untuk pendaftaran {$code} ({$visit}) telah selesai. Terima kasih telah berkunjung.",
                AppointmentStatus::Cancelled => "Pendaftaran {$code} untuk {$visit} telah dibatalkan. Kuota telah dilepas.",
                AppointmentStatus::NoShow => "Pendaftaran {$code} untuk {$visit} tercatat tidak hadir. Hubungi rumah sakit bila Anda merasa hal ini keliru.",
            };
        }

        $reason = $this->reasonSentence();

        return $reason === null ? $body : $body.' '.$reason;
    }

    private function mailIntro(): string
    {
        if ($this->isCheckInCorrection()) {
            return 'Check-in pendaftaran Anda dikoreksi oleh petugas dan status kembali menjadi Dikonfirmasi.';
        }

        return match ($this->to) {
            AppointmentStatus::Confirmed => 'Pendaftaran kunjungan Anda telah dikonfirmasi. Mohon hadir sesuai jadwal dan tunjukkan kode booking di loket pendaftaran.',
            AppointmentStatus::Cancelled => 'Pendaftaran kunjungan Anda telah dibatalkan dan kuota telah dilepas. Anda dapat mendaftar kembali melalui menu Daftar Kunjungan.',
            default => "Status pendaftaran kunjungan Anda berubah menjadi {$this->to->label()}.",
        };
    }

    /** "Alasan: …" (berakhir dengan tanda baca), atau NULL bila alasan kosong. */
    private function reasonSentence(): ?string
    {
        $reason = trim((string) $this->reason);

        if ($reason === '') {
            return null;
        }

        return 'Alasan: '.$reason.(preg_match('/[.!?]$/u', $reason) === 1 ? '' : '.');
    }

    private function visit(): string
    {
        $appointment = $this->appointment;

        return "{$appointment->doctor->display_name} di {$appointment->clinic->name} pada {$this->dateLabel()} pukul {$appointment->practiceSession->time_range} WIB";
    }

    private function dateLabel(): string
    {
        return $this->appointment->session_date->translatedFormat('l, j F Y');
    }
}
