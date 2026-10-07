<?php

namespace Database\Factories;

use App\Enums\ContactMessageStatus;
use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactMessage>
 */
class ContactMessageFactory extends Factory
{
    /**
     * Pesan baru (`new`) tanpa telepon, dengan persetujuan sekarang dan `ip_hash` SHA-256 dari 127.0.0.1 + APP_KEY.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => null,
            'subject' => rtrim(fake()->sentence(4), '.'),
            'message' => fake()->paragraph(2),
            'status' => ContactMessageStatus::New,
            'consent_at' => now(),
            'ip_hash' => hash('sha256', '127.0.0.1'.config('app.key')),
        ];
    }

    /** Sudah dibuka staf tetapi belum ditangani (`handled_*` kosong). */
    public function read(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ContactMessageStatus::Read,
        ]);
    }

    /** Sudah dibalas manual oleh seorang staf. */
    public function replied(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ContactMessageStatus::Replied,
            'handled_by' => User::factory()->staff(),
            'handled_at' => now(),
        ]);
    }

    /** Diarsipkan oleh seorang staf. */
    public function archived(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ContactMessageStatus::Archived,
            'handled_by' => User::factory()->staff(),
            'handled_at' => now(),
        ]);
    }
}
