<?php

namespace Tests\Feature\Public;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithContent;
use Tests\TestCase;

/**
 * Pemasangan `Page` di #4 `clinics.index`, #6 `doctors.index`, dan #8 `schedules.index` (F4A-R3, F4A-R4, F4A-R5).
 * Tanpa baris `pages`: teks bawaan (#6 dan #8 sama persis dengan teks statis fase 3; #4 memakai teks 13 §8.8). Dengan
 * baris `pages`: judul, subjudul, dan meta mengikutinya. Breadcrumb ketiganya tetap statis.
 */
class PublicPageBindingsTest extends TestCase
{
    use InteractsWithContent;
    use RefreshDatabase;

    /** @return array<string, array{string, string, string, string, string, string}> */
    public static function bindings(): array
    {
        return [
            'clinics-index' => ['clinics-index', 'clinics.index', 'Layanan dan Poliklinik', 'Pilih layanan sesuai kebutuhan Anda', 'Daftar poliklinik dan layanan penunjang UNPAM HOSPITAL.', 'Layanan'],
            'doctors-index' => ['doctors-index', 'doctors.index', 'Cari Dokter', 'Cari berdasarkan nama, spesialisasi, poliklinik, atau tanggal praktik', 'Cari dokter UNPAM HOSPITAL berdasarkan nama, spesialisasi, poliklinik, dan tanggal.', 'Dokter'],
            'schedules-index' => ['schedules-index', 'schedules.index', 'Jadwal Dokter', 'Jadwal praktik per minggu', 'Jadwal praktik dokter UNPAM HOSPITAL per minggu dengan sisa kuota.', 'Jadwal Dokter'],
        ];
    }

    #[DataProvider('bindings')]
    public function test_without_a_page_row_the_defaults_are_used(string $key, string $route, string $title, string $subtitle, string $meta, string $crumb): void
    {
        $this->assertSame(0, Page::query()->count());

        $response = $this->get(route($route))->assertOk();

        $response->assertSee('<title>'.$title.' — '.config('app.name').'</title>', false)
            ->assertSee('<h1 class="mb-1">'.$title.'</h1>', false)
            ->assertSee($subtitle)
            ->assertSee('<meta name="description" content="'.$meta.'">', false)
            ->assertSee('<li class="breadcrumb-item active" aria-current="page">'.$crumb.'</li>', false);
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
        $this->assertSame(0, Page::query()->count());
    }

    #[DataProvider('bindings')]
    public function test_a_page_row_overrides_title_subtitle_and_meta(string $key, string $route, string $title, string $subtitle, string $meta, string $crumb): void
    {
        $this->createPage($key, [
            'title' => 'Judul Diubah Admin',
            'subtitle' => 'Subjudul diubah admin',
            'meta_title' => 'Meta Judul Admin',
            'meta_description' => 'Deskripsi meta dari admin.',
        ]);

        $this->get(route($route))
            ->assertOk()
            ->assertSee('<title>Meta Judul Admin — '.config('app.name').'</title>', false)
            ->assertSee('<h1 class="mb-1">Judul Diubah Admin</h1>', false)
            ->assertSee('Subjudul diubah admin')
            ->assertSee('<meta name="description" content="Deskripsi meta dari admin.">', false)
            ->assertSee('<li class="breadcrumb-item active" aria-current="page">'.$crumb.'</li>', false);
    }

    #[DataProvider('bindings')]
    public function test_title_and_description_fall_back_to_the_page_title_and_subtitle(string $key, string $route): void
    {
        $this->createPage($key, [
            'title' => 'Judul Tanpa Meta',
            'subtitle' => 'Subjudul sebagai deskripsi',
            'meta_title' => null,
            'meta_description' => null,
        ]);

        $this->get(route($route))
            ->assertOk()
            ->assertSee('<title>Judul Tanpa Meta — '.config('app.name').'</title>', false)
            ->assertSee('<meta name="description" content="Subjudul sebagai deskripsi">', false);
    }

    public function test_the_doctors_page_example_from_the_plan_follows_the_page_row(): void
    {
        $this->createPage('doctors-index', ['title' => 'Temukan Dokter']);

        $this->get(route('doctors.index'))
            ->assertOk()
            ->assertSee('<h1 class="mb-1">Temukan Dokter</h1>', false)
            ->assertSee('<link rel="canonical" href="'.route('doctors.index').'">', false);
    }
}
