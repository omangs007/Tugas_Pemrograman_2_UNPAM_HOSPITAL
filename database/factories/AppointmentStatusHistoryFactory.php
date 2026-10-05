<?php

namespace Database\Factories;

use App\Enums\ActorType;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AppointmentStatusHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppointmentStatusHistory>
 */
class AppointmentStatusHistoryFactory extends Factory
{
    /**
     * Baris pembuatan (`from_status` NULL) oleh pelaku sistem. `created_at` diisi Eloquent.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'appointment_id' => Appointment::factory(),
            'from_status' => null,
            'to_status' => AppointmentStatus::Pending,
            'actor_type' => ActorType::System,
            'actor_user_id' => null,
            'reason' => null,
        ];
    }
}
