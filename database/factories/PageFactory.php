<?php

namespace Database\Factories;

use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;
use LogicException;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    /**
     * Halaman sistem dengan key acak yang belum terpakai (dipilih lewat closure, jadi tidak ada kueri bila tes
     * menimpa `key`), judul/subjudul/meta dari `Page::DEFAULTS`, tanpa isi. Untuk key tertentu, tes memakai
     * `InteractsWithContent::createPage()`.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => function (): string {
                $available = array_values(array_diff(Page::KEYS, Page::query()->pluck('key')->all()));

                if ($available === []) {
                    throw new LogicException('Seluruh 15 key halaman sudah terpakai.');
                }

                return fake()->randomElement($available);
            },
            'title' => fn (array $attributes): string => Page::DEFAULTS[$attributes['key']]['title'] ?? 'Halaman Contoh',
            'subtitle' => fn (array $attributes): ?string => Page::DEFAULTS[$attributes['key']]['subtitle'] ?? null,
            'body' => null,
            'meta_title' => null,
            'meta_description' => fn (array $attributes): ?string => Page::DEFAULTS[$attributes['key']]['meta_description'] ?? null,
            'is_system' => true,
            'updated_by' => null,
        ];
    }

    /** Halaman dengan isi rich text contoh. */
    public function withBody(): static
    {
        return $this->state(fn (array $attributes): array => [
            'body' => '<h2>Bagian Contoh</h2><p>'.fake()->paragraph().'</p>',
        ]);
    }
}
