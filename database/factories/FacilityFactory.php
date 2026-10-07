<?php

namespace Database\Factories;

use App\Enums\FacilityCategory;
use App\Models\Facility;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Facility>
 */
class FacilityFactory extends Factory
{
    /**
     * Fasilitas aktif kategori `umum` tanpa deskripsi dan cover; slug dibuat otomatis dari nama.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Fasilitas '.Str::title(fake()->unique()->words(2, true)),
            'category' => FacilityCategory::Umum,
            'short_description' => fake()->sentence(),
            'description' => null,
            'is_active' => true,
            'sort_order' => 0,
            'meta_title' => null,
            'meta_description' => null,
        ];
    }

    public function category(FacilityCategory $category): static
    {
        return $this->state(fn (array $attributes): array => [
            'category' => $category,
        ]);
    }

    /** Fasilitas nonaktif: halaman publiknya 404. */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
