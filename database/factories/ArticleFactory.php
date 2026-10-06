<?php

namespace Database\Factories;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Services\Media\MediaService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Tests\Support\ImageFixtures;

/**
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory
{
    /**
     * Artikel terbit kemarin tanpa kategori, penulis, dan cover; slug dibuat otomatis dari judul.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'article_category_id' => null,
            'author_user_id' => null,
            'title' => Str::title(fake()->unique()->words(4, true)),
            'excerpt' => fake()->sentence(12),
            'body' => '<p>'.fake()->paragraph(4).'</p><p>'.fake()->paragraph(4).'</p>',
            'status' => ArticleStatus::Published,
            'published_at' => now()->subDay(),
            'meta_title' => null,
            'meta_description' => null,
        ];
    }

    /** Terbit kemarin (tayang). */
    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ArticleStatus::Published,
            'published_at' => now()->subDay(),
        ]);
    }

    /** Draf tanpa tanggal terbit. */
    public function draft(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ArticleStatus::Draft,
            'published_at' => null,
        ]);
    }

    /** Terbit tetapi terjadwal dua hari lagi (belum tayang). */
    public function scheduled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ArticleStatus::Published,
            'published_at' => now()->addDays(2),
        ]);
    }

    /** Sudah di-soft-delete (`deleted_at` terisi). */
    public function trashed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'deleted_at' => now(),
        ]);
    }

    /**
     * Cover nyata 1200x675 lewat MediaService. Hanya untuk tes: pakai `Storage::fake('public')` lebih dulu.
     */
    public function withCover(): static
    {
        return $this->afterCreating(function (Article $article): void {
            app(MediaService::class)->store(
                ImageFixtures::jpeg(1200, 675, 'sampul.jpg'),
                'cover',
                $article,
                ['alt_text' => 'Sampul contoh '.$article->title],
            );
        });
    }
}
