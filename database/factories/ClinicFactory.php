<?php

namespace Database\Factories;

use App\Enums\ClinicType;
use App\Models\Clinic;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Clinic>
 */
class ClinicFactory extends Factory
{
    /**
     * Poliklinik aktif yang dapat dipesan, dengan nama dan kode unik.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Poliklinik '.Str::title(fake()->unique()->words(2, true)),
            // Slug sementara dari nama; dihapus di Task 2 saat trait HasUniqueSlug mengambil alih.
            'slug' => fn (array $attributes): string => Str::slug((string) $attributes['name']),
            'code' => Str::upper(fake()->unique()->lexify('???')),
            'type' => ClinicType::Poliklinik,
            'is_bookable' => true,
            'specialty_id' => null,
            'short_description' => fake()->sentence(),
            'description' => null,
            'location' => 'Gedung A Lt. 1',
            'is_active' => true,
            'sort_order' => 0,
            'meta_title' => null,
            'meta_description' => null,
        ];
    }

    /** Poliklinik/layanan nonaktif: halaman publiknya 404. */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    /** Layanan penunjang (mis. Laboratorium): tidak dapat dipesan (09 §3.2). */
    public function support(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => ClinicType::Penunjang,
            'is_bookable' => false,
        ]);
    }
}
