<?php

namespace Database\Factories;

use App\Enums\ActorType;
use App\Enums\AppointmentStatus;
use App\Enums\BookingChannel;
use App\Enums\PaymentType;
use App\Models\Appointment;
use App\Models\PatientProfile;
use App\Models\PracticeSession;
use App\Services\Booking\BookingCodeGenerator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    /**
     * Appointment `confirmed` kanal online, pembayaran umum, nomor antrean 1. Dokter, poliklinik, dan tanggal
     * diturunkan dari sesi. Factory TIDAK mengubah `booked_count`/`last_queue_number` sesi: untuk appointment
     * yang menjaga invarian kuota gunakan `InteractsWithSchedules::bookAppointment()`. Beberapa appointment
     * pada sesi yang sama wajib diberi `queue_number` berbeda.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_code' => BookingCodeGenerator::generate(),
            'patient_profile_id' => PatientProfile::factory(),
            'practice_session_id' => PracticeSession::factory(),
            'doctor_id' => fn (array $attributes): int => PracticeSession::query()->findOrFail($attributes['practice_session_id'])->doctor_id,
            'clinic_id' => fn (array $attributes): int => PracticeSession::query()->findOrFail($attributes['practice_session_id'])->clinic_id,
            'session_date' => fn (array $attributes): string => PracticeSession::query()->findOrFail($attributes['practice_session_id'])->session_date->toDateString(),
            'queue_number' => 1,
            'status' => AppointmentStatus::Confirmed,
            'channel' => BookingChannel::Online,
            'payment_type' => PaymentType::Umum,
            'insurance_partner_id' => null,
            'payment_reference' => null,
            'payment_reference_last4' => null,
            'complaint' => null,
            'confirmed_at' => now(),
        ];
    }

    /**
     * Menetapkan status beserta kolom waktu yang sesuai (09 §4.1, invarian I-14): `confirmed` mengisi
     * `confirmed_at`; `checked_in` menambah `checked_in_at`; `completed` menambah `completed_at`; `cancelled`
     * mengisi `cancelled_at` dan `cancelled_by_type`; `no_show` mengisi `no_show_at`.
     */
    public function status(AppointmentStatus $status): static
    {
        return $this->state(function (array $attributes) use ($status): array {
            $now = now();

            $timestamps = [
                'confirmed_at' => null,
                'checked_in_at' => null,
                'completed_at' => null,
                'cancelled_at' => null,
                'no_show_at' => null,
                'cancelled_by_type' => null,
                'cancel_reason' => null,
            ];

            switch ($status) {
                case AppointmentStatus::Pending:
                    break;
                case AppointmentStatus::Confirmed:
                    $timestamps['confirmed_at'] = $now;
                    break;
                case AppointmentStatus::CheckedIn:
                    $timestamps['confirmed_at'] = $now;
                    $timestamps['checked_in_at'] = $now;
                    break;
                case AppointmentStatus::Completed:
                    $timestamps['confirmed_at'] = $now;
                    $timestamps['checked_in_at'] = $now;
                    $timestamps['completed_at'] = $now;
                    break;
                case AppointmentStatus::Cancelled:
                    $timestamps['cancelled_at'] = $now;
                    $timestamps['cancelled_by_type'] = ActorType::System;
                    $timestamps['cancel_reason'] = 'Dibatalkan';
                    break;
                case AppointmentStatus::NoShow:
                    $timestamps['no_show_at'] = $now;
                    break;
            }

            return ['status' => $status] + $timestamps;
        });
    }

    /**
     * Sesi tertentu: menyalin `practice_session_id`, `doctor_id`, `clinic_id`, dan `session_date` (BR-52).
     * TIDAK menyinkronkan `booked_count`/`last_queue_number` sesi (F6-R33); untuk itu gunakan
     * `InteractsWithSchedules::bookAppointment()` atau BookingService.
     */
    public function forSession(PracticeSession $session): static
    {
        return $this->state(fn (array $attributes): array => [
            'practice_session_id' => $session->id,
            'doctor_id' => $session->doctor_id,
            'clinic_id' => $session->clinic_id,
            'session_date' => $session->session_date->toDateString(),
        ]);
    }

    /** Pasien tertentu (tanpa membuat profil baru). */
    public function forPatient(PatientProfile $profile): static
    {
        return $this->state(fn (array $attributes): array => [
            'patient_profile_id' => $profile->id,
        ]);
    }

    /** Pembayaran BPJS: jenis, referensi (terenkripsi oleh cast), dan `payment_reference_last4`. */
    public function bpjs(string $number = '0009900000011'): static
    {
        return $this->state(fn (array $attributes): array => [
            'payment_type' => PaymentType::Bpjs,
            'payment_reference' => $number,
            'payment_reference_last4' => mb_substr($number, -4),
        ]);
    }
}
