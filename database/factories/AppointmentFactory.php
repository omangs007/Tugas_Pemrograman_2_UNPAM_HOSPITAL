<?php

namespace Database\Factories;

use App\Enums\ActorType;
use App\Enums\AppointmentStatus;
use App\Enums\BookingChannel;
use App\Enums\PaymentType;
use App\Models\Appointment;
use App\Models\PatientProfile;
use App\Models\PracticeSession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

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
            'booking_code' => 'UPH-'.Str::upper(fake()->unique()->bothify('????-????')),
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
}
