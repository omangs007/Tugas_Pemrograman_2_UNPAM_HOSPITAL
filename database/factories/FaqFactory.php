<?php

namespace Database\Factories;

use App\Models\Faq;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Faq>
 */
class FaqFactory extends Factory
{
    /**
     * FAQ terbit kategori `umum` dengan pertanyaan unik.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category' => 'umum',
            'question' => rtrim(fake()->unique()->sentence(6), '.').'?',
            'answer' => fake()->paragraph(2),
            'sort_order' => 0,
            'is_published' => true,
        ];
    }

    public function unpublished(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_published' => false,
        ]);
    }

    public function category(string $category): static
    {
        return $this->state(fn (array $attributes): array => [
            'category' => $category,
        ]);
    }
}
