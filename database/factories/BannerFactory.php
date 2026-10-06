<?php

namespace Database\Factories;

use App\Models\Banner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Banner>
 */
class BannerFactory extends Factory
{
    /**
     * Banner aktif tanpa periode dan tanpa tombol.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->unique()->sentence(3),
            'subtitle' => fake()->sentence(8),
            'button_label' => null,
            'button_url' => null,
            'sort_order' => 0,
            'is_active' => true,
            'starts_at' => null,
            'ends_at' => null,
        ];
    }

    /** Periode sudah lewat (berakhir kemarin). */
    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->subDay(),
        ]);
    }

    /** Periode belum mulai (mulai dua hari lagi). */
    public function scheduled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(10),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
