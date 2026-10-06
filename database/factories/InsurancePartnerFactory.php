<?php

namespace Database\Factories;

use App\Models\InsurancePartner;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<InsurancePartner>
 */
class InsurancePartnerFactory extends Factory
{
    /**
     * Mitra asuransi fiktif aktif bertipe `asuransi`; slug dibuat otomatis dari nama.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Asuransi Contoh '.Str::title(fake()->unique()->word()),
            'type' => 'asuransi',
            'description' => 'Contoh mitra asuransi. Bukan kerja sama nyata.',
            'website_url' => null,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    /** Perusahaan rekanan (tipe `perusahaan`). */
    public function company(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => 'perusahaan',
            'name' => 'PT Contoh '.Str::title(fake()->unique()->word()),
        ]);
    }
}
