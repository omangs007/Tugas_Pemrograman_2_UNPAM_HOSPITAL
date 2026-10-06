<?php

namespace Tests\Feature\Public;

use App\Enums\ClinicType;
use App\Models\Clinic;
use App\Services\Media\MediaService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ImageFixtures;
use Tests\Support\InteractsWithMasterData;
use Tests\TestCase;

/**
 * Halaman #4 `clinics.index` (05 hal. 4): hanya layanan aktif, dikelompokkan per ClinicType.
 */
class PublicClinicPagesTest extends TestCase
{
    use InteractsWithMasterData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_strict_mode_is_active_in_the_test_suite(): void
    {
        $this->assertTrue(Model::preventsLazyLoading());
    }

    public function test_index_lists_active_clinics_grouped_by_type_in_sort_order(): void
    {
        $this->createClinic(['name' => 'Poliklinik Anak', 'type' => ClinicType::Poliklinik, 'sort_order' => 2]);
        $this->createClinic(['name' => 'Poliklinik Mata', 'type' => ClinicType::Poliklinik, 'sort_order' => 1]);
        $this->createClinic(['name' => 'Laboratorium', 'type' => ClinicType::Penunjang, 'is_bookable' => false, 'sort_order' => 1]);

        $this->get(route('clinics.index'))
            ->assertOk()
            ->assertSeeInOrder([
                'id="layanan-poliklinik"',
                'Poliklinik Mata',
                'Poliklinik Anak',
                'id="layanan-penunjang"',
                'Laboratorium',
            ], false);
    }

    public function test_index_hides_inactive_clinics_and_omits_empty_groups(): void
    {
        $this->createClinic(['name' => 'Poliklinik Aktif', 'type' => ClinicType::Poliklinik]);
        $this->createClinic(['name' => 'Radiologi Nonaktif', 'type' => ClinicType::Penunjang, 'is_bookable' => false, 'is_active' => false]);

        $this->get(route('clinics.index'))
            ->assertOk()
            ->assertSee('Poliklinik Aktif')
            ->assertDontSee('Radiologi Nonaktif')
            ->assertDontSee('id="layanan-penunjang"', false);
    }

    public function test_index_shows_the_empty_state_when_no_clinic_is_active(): void
    {
        $this->createClinic(['is_active' => false]);

        $this->get(route('clinics.index'))
            ->assertOk()
            ->assertSee('Belum ada layanan')
            ->assertDontSee('id="layanan-poliklinik"', false);
    }

    public function test_bookable_label_appears_only_on_bookable_cards(): void
    {
        $this->createClinic(['name' => 'Poliklinik Gigi', 'is_bookable' => true]);
        $this->createClinic(['name' => 'Poliklinik Umum', 'is_bookable' => true]);
        $this->createClinic(['name' => 'Farmasi', 'type' => ClinicType::Penunjang, 'is_bookable' => false]);

        $html = $this->get(route('clinics.index'))->assertOk()->getContent();

        $this->assertSame(2, substr_count($html, 'Dapat didaftar online'));
    }

    public function test_cards_link_to_the_detail_page_by_slug(): void
    {
        $clinic = $this->createClinic(['name' => 'Poliklinik Saraf']);

        $this->get(route('clinics.index'))
            ->assertOk()
            ->assertSee('href="'.url('/layanan/'.$clinic->slug).'"', false);
    }

    public function test_index_has_one_h1_static_header_and_breadcrumb(): void
    {
        $this->createClinic();

        $response = $this->get(route('clinics.index'))->assertOk();

        $response->assertSee('<title>Layanan dan Poliklinik — '.config('app.name').'</title>', false)
            ->assertSee('<h1 class="mb-1">Layanan dan Poliklinik</h1>', false)
            ->assertSee('Pilih layanan sesuai kebutuhan Anda')
            ->assertSee('<meta name="description" content="Daftar poliklinik dan layanan penunjang UNPAM HOSPITAL.">', false)
            ->assertDontSee('Layanan Kami')
            ->assertSee('aria-label="Breadcrumb"', false)
            ->assertSee('<li class="breadcrumb-item active" aria-current="page">Layanan</li>', false);
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public function test_clinic_without_cover_uses_the_local_placeholder(): void
    {
        $this->createClinic(['name' => 'Poliklinik Gigi']);

        $this->get(route('clinics.index'))
            ->assertOk()
            ->assertSee('images/placeholders/clinic.svg', false)
            ->assertDontSee('onerror', false);
    }

    public function test_index_renders_many_rows_with_covers_in_strict_mode(): void
    {
        $media = $this->app->make(MediaService::class);

        foreach (range(1, 8) as $number) {
            $clinic = $this->createClinic([
                'name' => "Poliklinik Uji {$number}",
                'type' => $number <= 6 ? ClinicType::Poliklinik : ClinicType::Penunjang,
                'is_bookable' => $number <= 6,
                'sort_order' => $number,
            ]);
            $media->store(ImageFixtures::jpeg(1200, 800, 'cover.jpg'), 'cover', $clinic, ['alt_text' => "Foto contoh Poliklinik Uji {$number}"]);
        }

        $html = $this->get(route('clinics.index'))->assertOk()->getContent();

        $this->assertSame(8, substr_count($html, 'service-card'));
        $this->assertStringContainsString('/thumbs/', $html);
        $this->assertStringContainsString('alt="Foto contoh Poliklinik Uji 3"', $html);
        $this->assertSame(8, Clinic::query()->count());
    }
}
