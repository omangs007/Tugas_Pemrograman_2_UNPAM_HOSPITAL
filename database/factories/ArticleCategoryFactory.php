<?php

namespace Database\Factories;

use App\Models\ArticleCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ArticleCategory>
 */
class ArticleCategoryFactory extends Factory
{
    /**
     * Kategori artikel dengan nama unik; slug dibuat otomatis oleh HasUniqueSlug.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Kategori '.Str::title(fake()->unique()->words(2, true)),
            'description' => fake()->sentence(),
            'sort_order' => 0,
        ];
    }
}
