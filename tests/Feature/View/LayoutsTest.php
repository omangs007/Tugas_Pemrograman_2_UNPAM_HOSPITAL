<?php

namespace Tests\Feature\View;

use App\Services\Settings\SettingsService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Tests\Support\InteractsWithPatients;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

class LayoutsTest extends TestCase
{
    use InteractsWithPatients;
    use InteractsWithStaff;
    use RefreshDatabase;

    /** Mendaftarkan halaman uji yang merender potongan Blade di dalam grup middleware `web`. */
    private function page(string $uri, string $blade): void
    {
        Route::middleware('web')->get($uri, fn () => Blade::render($blade));
        Route::getRoutes()->refreshNameLookups();
    }

    private function h1Count(string $html): int
    {
        return substr_count($html, '<h1');
    }

    // --- layout auth -------------------------------------------------------------------------

    public function test_auth_layout_renders_card_with_skip_link_single_h1_and_noindex(): void
    {
        $this->page('/_uji/auth', '<x-layouts.auth title="Masuk Uji" subtitle="Subjudul uji"><p>Isi formulir uji</p><x-slot:footer><a href="#bantuan">Bantuan uji</a></x-slot:footer></x-layouts.auth>');

        $response = $this->get('/_uji/auth')->assertOk();

        $response->assertSee('<html lang="id">', false)
            ->assertSee('<title>Masuk Uji — '.config('app.name').'</title>', false)
            ->assertSee('<meta name="robots" content="noindex,nofollow">', false)
            ->assertSee('class="skip-link" href="#konten-utama"', false)
            ->assertSee('<main id="konten-utama" tabindex="-1"', false)
            ->assertSee('auth-card', false)
            ->assertSee('Subjudul uji')
            ->assertSee('Isi formulir uji')
            ->assertSee('Bantuan uji')
            ->assertSee('Kembali ke beranda')
            ->assertSee('images/placeholders/logo.svg', false);
        $this->assertSame(1, $this->h1Count($response->getContent()));
    }

    public function test_auth_layout_shows_flash_messages_and_validation_summary(): void
    {
        $this->page('/_uji/auth', '<x-layouts.auth title="Masuk Uji"><p>Isi</p></x-layouts.auth>');
        Route::middleware('web')->post('/_uji/validasi', function (Request $request) {
            $request->validate(['email' => 'required']);
        });

        $this->withSession(['error' => 'Gagal uji.'])->get('/_uji/auth')->assertSee('Gagal uji.');

        $this->from('/_uji/auth')->followingRedirects()->post('/_uji/validasi', [])
            ->assertSee('data-errors-summary', false)
            ->assertSee('Kolom email wajib diisi.');
    }

    public function test_auth_layout_heading_prop_sets_the_h1_but_not_the_document_title(): void
    {
        $this->page('/_uji/auth', '<x-layouts.auth title="Masuk" heading="Masuk ke Akun Pasien"><p>Isi</p></x-layouts.auth>');

        $response = $this->get('/_uji/auth')->assertOk();

        $response->assertSee('<title>Masuk — '.config('app.name').'</title>', false)
            ->assertSee('<h1 class="h3 mb-1">Masuk ke Akun Pasien</h1>', false);
        $this->assertSame(1, $this->h1Count($response->getContent()));
    }

    public function test_auth_layout_without_heading_uses_the_title_for_the_h1(): void
    {
        $this->page('/_uji/auth', '<x-layouts.auth title="Masuk Uji"><p>Isi</p></x-layouts.auth>');

        $this->get('/_uji/auth')->assertOk()->assertSee('<h1 class="h3 mb-1">Masuk Uji</h1>', false);
    }

    public function test_auth_layout_wide_prop_adds_the_wide_card_class_and_the_default_does_not(): void
    {
        $this->page('/_uji/auth-lebar', '<x-layouts.auth title="Daftar Uji" :wide="true"><p>Isi</p></x-layouts.auth>');
        $this->page('/_uji/auth-biasa', '<x-layouts.auth title="Masuk Uji"><p>Isi</p></x-layouts.auth>');

        $this->get('/_uji/auth-lebar')->assertOk()->assertSee('class="auth-card auth-card-wide mx-auto"', false);
        $this->get('/_uji/auth-biasa')->assertOk()
            ->assertSee('class="auth-card mx-auto"', false)
            ->assertDontSee('auth-card-wide', false);
    }

    public function test_layouts_read_site_settings_through_the_view_composer(): void
    {
        $this->seed(SettingsSeeder::class);
        app(SettingsService::class)->set('site.name', 'RS Uji Coba');
        $this->page('/_uji/auth', '<x-layouts.auth title="Masuk Uji"><p>Isi</p></x-layouts.auth>');

        $this->get('/_uji/auth')
            ->assertSee('<title>Masuk Uji — RS Uji Coba</title>', false)
            ->assertSee('RS Uji Coba');
    }

    // --- layout publik -----------------------------------------------------------------------

    public function test_public_layout_has_landmarks_skip_link_meta_and_no_noindex(): void
    {
        $this->page('/_uji/publik', '<x-layouts.public title="Halaman Uji" meta-description="Deskripsi uji"><h1>Judul Uji</h1><p>Isi uji</p></x-layouts.public>');

        $response = $this->get('/_uji/publik')->assertOk();

        $response->assertSee('<html lang="id">', false)
            ->assertSee('<title>Halaman Uji — '.config('app.name').'</title>', false)
            ->assertSee('<meta name="description" content="Deskripsi uji">', false)
            ->assertSee('class="skip-link" href="#konten-utama"', false)
            ->assertSee('aria-label="Menu utama"', false)
            ->assertSee('aria-label="Buka menu"', false)
            ->assertSee('<main id="konten-utama" tabindex="-1"', false)
            ->assertSee('<footer', false)
            ->assertSee('Situs prototipe: nama dokter, foto, tarif, kontak, dan layanan adalah data contoh.')
            ->assertSee('Isi uji')
            ->assertDontSee('noindex');
        $this->assertSame(1, $this->h1Count($response->getContent()), 'Layout tidak boleh menambah <h1>; halaman yang memberinya.');
    }

    public function test_public_layout_escapes_the_title_and_renders_the_breadcrumb_slot(): void
    {
        $this->page('/_uji/publik', '<x-layouts.public :title="\'<script>alert(1)</script>\'"><x-slot:breadcrumb><span>Jejak uji</span></x-slot:breadcrumb><p>Isi</p></x-layouts.public>');

        $this->get('/_uji/publik')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertSee('Jejak uji');
    }

    public function test_public_footer_shows_address_and_contacts_from_settings_with_a_fallback_address(): void
    {
        $this->page('/_uji/publik', '<x-layouts.public title="Halaman Uji"><p>Isi</p></x-layouts.public>');

        $this->get('/_uji/publik')->assertSee('Alamat belum diisi.');

        $this->seed(SettingsSeeder::class);
        app(SettingsService::class)->forget(); // singleton + cache masih memegang peta kosong dari permintaan pertama

        $this->get('/_uji/publik')
            ->assertDontSee('Alamat belum diisi.')
            ->assertSee('Universitas Pamulang Kampus 2 Viktor')
            ->assertSee('https://wa.me/6280000000000', false)
            ->assertSee('info@unpamhospital.test');
    }

    public function test_public_layout_hides_links_to_routes_that_do_not_exist_yet_and_shows_them_when_they_do(): void
    {
        $this->page('/_uji/publik', '<x-layouts.public title="Halaman Uji"><p>Isi</p></x-layouts.public>');

        $this->get('/_uji/publik')->assertSee(route('home'), false);

        // `login` kini route asli (fase 5): "Masuk" selalu tampil bagi tamu; "Daftar Kunjungan" menunggu route fase 6.
        $this->get('/_uji/publik')
            ->assertSee('>Masuk<', false)
            ->assertSee('href="'.route('login').'"', false)
            ->assertDontSee('Daftar Kunjungan');

        Route::middleware('web')->get('/_uji/daftar-kunjungan', fn () => 'daftar')->name('patient.appointments.create');
        Route::getRoutes()->refreshNameLookups();

        $this->get('/_uji/publik')
            ->assertSee('>Masuk<', false)
            ->assertSee('Daftar Kunjungan')
            ->assertSee('Daftar Dokter');
    }

    public function test_public_layout_offers_the_admin_panel_and_logout_to_logged_in_staff(): void
    {
        $this->page('/_uji/publik', '<x-layouts.public title="Halaman Uji"><p>Isi</p></x-layouts.public>');

        $this->get('/_uji/publik')->assertDontSee('Buka Panel Admin');

        Route::middleware('web')->get('/_uji/admin-dashboard', fn () => 'dasbor')->name('admin.dashboard');
        Route::middleware('web')->post('/_uji/admin-keluar', fn () => 'keluar')->name('admin.logout');
        Route::getRoutes()->refreshNameLookups();

        $this->actingAs($this->createStaff('content-admin'))
            ->get('/_uji/publik')
            ->assertSee('Buka Panel Admin')
            ->assertSee('action="'.route('admin.logout').'"', false);
    }

    public function test_public_layout_can_be_marked_noindex(): void
    {
        $this->page('/_uji/publik', '<x-layouts.public title="Halaman Uji" :noindex="true"><p>Isi</p></x-layouts.public>');

        $this->get('/_uji/publik')->assertSee('<meta name="robots" content="noindex,nofollow">', false);
    }

    public function test_public_layout_full_title_drops_the_site_name_suffix(): void
    {
        $this->page('/_uji/publik-penuh', '<x-layouts.public title="Judul Utuh Uji" :full-title="true"><p>Isi</p></x-layouts.public>');

        $this->get('/_uji/publik-penuh')
            ->assertOk()
            ->assertSee('<title>Judul Utuh Uji</title>', false)
            ->assertDontSee('Judul Utuh Uji — ', false);
    }

    public function test_public_layout_full_title_is_escaped_and_defaults_to_the_suffixed_title(): void
    {
        $this->page('/_uji/publik-penuh', '<x-layouts.public :title="\'<b>Utuh</b>\'" :full-title="true"><p>Isi</p></x-layouts.public>');
        $this->page('/_uji/publik-biasa', '<x-layouts.public title="Judul Biasa Uji"><p>Isi</p></x-layouts.public>');

        $this->get('/_uji/publik-penuh')->assertSee('<title>&lt;b&gt;Utuh&lt;/b&gt;</title>', false);
        $this->get('/_uji/publik-biasa')->assertSee('<title>Judul Biasa Uji — '.config('app.name').'</title>', false);
    }

    // --- layout pasien -----------------------------------------------------------------------

    public function test_patient_layout_wraps_the_public_layout_with_noindex_and_single_h1(): void
    {
        $this->page('/_uji/pasien', '<x-layouts.patient title="Portal Uji"><x-slot:breadcrumb><span>Jejak pasien</span></x-slot:breadcrumb><h1>Judul Pasien</h1><p>Isi portal</p></x-layouts.patient>');

        $response = $this->get('/_uji/pasien')->assertOk();

        $response->assertSee('<html lang="id">', false)
            ->assertSee('<title>Portal Uji — '.config('app.name').'</title>', false)
            ->assertSee('<meta name="robots" content="noindex,nofollow">', false)
            ->assertSee('class="skip-link" href="#konten-utama"', false)
            ->assertSee('<main id="konten-utama" tabindex="-1"', false)
            ->assertSee('Jejak pasien')
            ->assertSee('Isi portal');
        $this->assertSame(1, $this->h1Count($response->getContent()));
    }

    public function test_patient_layout_shows_no_logout_form_to_guests(): void
    {
        $this->page('/_uji/pasien', '<x-layouts.patient title="Portal Uji"><p>Isi portal</p></x-layouts.patient>');

        $this->get('/_uji/pasien')
            ->assertOk()
            ->assertSee('aria-label="Menu portal pasien"', false)
            ->assertDontSee('action="'.route('logout').'"', false);
    }

    public function test_patient_layout_shows_portal_navigation_with_current_item_and_logout_on_the_real_dashboard(): void
    {
        $this->actingAs($this->createPatient())
            ->get(route('patient.dashboard'))
            ->assertOk()
            ->assertSee('aria-label="Menu portal pasien"', false)
            ->assertSee('Dashboard')
            ->assertSee('aria-current="page"', false)
            ->assertSee('action="'.route('logout').'"', false)
            ->assertDontSee('Riwayat Pendaftaran')
            ->assertDontSee('Daftar Kunjungan');
    }

    public function test_patient_layout_adds_the_phase_6_menu_items_once_their_routes_exist(): void
    {
        Route::middleware('web')->get('/_uji/pasien-riwayat', fn () => 'riwayat')->name('patient.appointments.index');
        Route::middleware('web')->get('/_uji/pasien-baru', fn () => 'baru')->name('patient.appointments.create');
        Route::getRoutes()->refreshNameLookups();

        $this->actingAs($this->createPatient())
            ->get(route('patient.dashboard'))
            ->assertOk()
            ->assertSee('Riwayat Pendaftaran')
            ->assertSee('Daftar Kunjungan');
    }
}
