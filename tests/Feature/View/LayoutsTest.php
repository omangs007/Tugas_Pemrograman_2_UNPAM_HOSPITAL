<?php

namespace Tests\Feature\View;

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
}
