<?php

namespace Database\Factories;

use App\Models\RoomCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RoomCategory>
 */
class RoomCategoryFactory extends Factory
{
    /**
     * Kategori kamar aktif: kapasitas 12, tersedia 5, tarif contoh 400000 per malam, diperbarui satu jam yang lalu.
     * Slug dibuat otomatis dari nama; `availability_updated_by` kosong.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Kamar '.Str::title(fake()->unique()->words(2, true)),
            'short_description' => fake()->sentence(),
            'description' => null,
            'amenities' => ['AC', 'Kamar mandi dalam'],
            'bed_capacity' => 12,
            'beds_available' => 5,
            'sample_rate' => 400000,
            'rate_unit' => 'per malam',
            'rate_note' => 'Tarif contoh untuk prototipe; bukan tarif resmi.',
            'is_active' => true,
            'sort_order' => 0,
            'availability_updated_at' => now()->subHour(),
            'availability_updated_by' => null,
            'meta_title' => null,
            'meta_description' => null,
        ];
    }

    /** Penuh: tidak ada tempat tidur tersedia (badge "Penuh"). */
    public function full(): static
    {
        return $this->state(fn (array $attributes): array => [
            'beds_available' => 0,
        ]);
    }

    /** Kategori nonaktif: halaman publiknya 404. */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    /** Tanpa tarif contoh: label "Hubungi kami". */
    public function withoutRate(): static
    {
        return $this->state(fn (array $attributes): array => [
            'sample_rate' => null,
            'rate_note' => null,
        ]);
    }
}
