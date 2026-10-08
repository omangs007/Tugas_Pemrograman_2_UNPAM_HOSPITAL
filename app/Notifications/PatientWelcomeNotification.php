<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email selamat datang sesudah registrasi pasien (F5-R25, 06 §18, K-22 = a). Hanya kanal mail, sinkron
 * (bukan ShouldQueue; antrean notifikasi dirancang fase 7). Isi sengaja minim: tanpa telepon, alamat, tanggal
 * lahir, BPJS, nomor pasien, dan tanpa tautan verifikasi (BR-174, NTF-08).
 */
final class PatientWelcomeNotification extends Notification
{
    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * @param  User  $notifiable
     */
    public function toMail(object $notifiable): MailMessage
    {
        $app = (string) config('app.name');

        return (new MailMessage)
            ->subject("Selamat Datang di {$app}")
            ->greeting("Halo, {$notifiable->name}!")
            ->line("Akun pasien Anda di {$app} telah berhasil dibuat.")
            ->line('Masuk ke akun Anda untuk melihat dan memperbarui profil.')
            ->line('Pendaftaran kunjungan rawat jalan dapat dilakukan melalui menu Daftar Kunjungan setelah fitur tersebut tersedia, atau langsung di loket pendaftaran.')
            ->action('Masuk ke Akun', route('login'));
    }
}
