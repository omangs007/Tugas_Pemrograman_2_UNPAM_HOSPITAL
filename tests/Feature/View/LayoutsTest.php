<?php

namespace Tests\Feature\View;

use App\Models\User;
use App\Services\Settings\SettingsService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

class LayoutsTest extends TestCase
{
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

        // Cadangan F1-R2: berlaku selama route fase berikutnya belum ada (dihapus bersama cadangan di fase 5).
        if (! Route::has('login') && ! Route::has('patient.appointments.create')) {
            $this->get('/_uji/publik')
                ->assertDontSee('>Masuk<', false)
                ->assertDontSee('Daftar Kunjungan');
        }

        Route::middleware('web')->get('/_uji/masuk', fn () => 'masuk')->name('login');
        Route::middleware('web')->get('/_uji/daftar-kunjungan', fn () => 'daftar')->name('patient.appointments.create');
        Route::middleware('web')->get('/_uji/dokter', fn () => 'dokter')->name('doctors.index');
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

    public function test_patient_layout_omits_portal_navigation_until_patient_routes_exist(): void
    {
        $this->page('/_uji/pasien', '<x-layouts.patient title="Portal Uji"><p>Isi portal</p></x-layouts.patient>');

        // Berlaku selama route portal pasien belum ada (fase 5 menghapus cabang ini).
        if (! Route::has('patient.dashboard') && ! Route::has('logout')) {
            $this->get('/_uji/pasien')
                ->assertOk()
                ->assertDontSee('aria-label="Menu portal pasien"', false)
                ->assertDontSee('Riwayat Pendaftaran');
        }
    }

    public function test_patient_layout_shows_portal_navigation_with_current_item_and_logout_when_routes_exist(): void
    {
        Route::middleware('web')->get('/_uji/pasien-dashboard', fn () => Blade::render('<x-layouts.patient title="Dashboard Uji"><p>Dasbor</p></x-layouts.patient>'))->name('patient.dashboard');
        Route::middleware('web')->get('/_uji/pasien-riwayat', fn () => 'riwayat')->name('patient.appointments.index');
        Route::middleware('web')->get('/_uji/pasien-baru', fn () => 'baru')->name('patient.appointments.create');
        Route::middleware('web')->post('/_uji/keluar', fn () => 'keluar')->name('logout');
        Route::getRoutes()->refreshNameLookups();

        $this->get('/_uji/pasien-dashboard')
            ->assertOk()
            ->assertSee('aria-label="Menu portal pasien"', false)
            ->assertSee('Dashboard')
            ->assertSee('Riwayat Pendaftaran')
            ->assertSee('Daftar Kunjungan')
            ->assertSee('aria-current="page"', false)
            ->assertDontSee('action="'.route('logout').'"', false);

        $this->actingAs(User::factory()->patient()->create())
            ->get('/_uji/pasien-dashboard')
            ->assertSee('action="'.route('logout').'"', false);
    }
}
