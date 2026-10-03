<?php

namespace Tests\Feature\Errors;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

class ExceptionRenderingTest extends TestCase
{
    use InteractsWithStaff;
    use RefreshDatabase;

    public function test_403_with_a_custom_indonesian_message_shows_it_on_the_error_page(): void
    {
        Route::middleware('web')->get('/_uji/dilarang', fn () => abort(403, 'Pesan khusus uji.'));

        $this->get('/_uji/dilarang')
            ->assertForbidden()
            ->assertSee('Akses ditolak')
            ->assertSee('Pesan khusus uji.');
    }

    public function test_403_from_a_missing_permission_never_leaks_the_permission_library_message(): void
    {
        Route::middleware(['web', 'auth', 'permission:settings.manage'])->get('/_uji/pengaturan', fn () => 'rahasia');
        $staff = $this->createStaff('content-admin');

        $this->actingAs($staff)->get('/_uji/pengaturan')
            ->assertForbidden()
            ->assertSee('Akses ditolak')
            ->assertSee('Ke Dasbor')
            ->assertDontSee('right permissions')
            ->assertDontSee('settings.manage')
            ->assertDontSee('rahasia');

        $this->actingAs($staff)->getJson('/_uji/pengaturan')
            ->assertForbidden()
            ->assertExactJson(['message' => 'Anda tidak memiliki izin untuk melakukan tindakan ini.']);
    }

    public function test_403_from_the_gate_is_generic_for_html_and_json(): void
    {
        Route::middleware(['web', 'auth', 'can:settings.manage'])->get('/_uji/gerbang', fn () => 'rahasia');
        $staff = $this->createStaff('content-admin');

        $this->actingAs($staff)->get('/_uji/gerbang')
            ->assertForbidden()
            ->assertSee('Akses ditolak')
            ->assertDontSee('This action is unauthorized');

        $this->actingAs($staff)->getJson('/_uji/gerbang')
            ->assertForbidden()
            ->assertExactJson(['message' => 'Anda tidak memiliki izin untuk melakukan tindakan ini.']);
    }

    public function test_419_json_response_has_an_indonesian_message(): void
    {
        $this->app['env'] = 'local'; // ValidateCsrfToken hanya dilewati saat env `testing`
        Route::middleware('web')->post('/_uji/kirim', fn () => 'ok');

        $this->postJson('/_uji/kirim', [])
            ->assertStatus(419)
            ->assertExactJson(['message' => 'Sesi Anda telah berakhir. Muat ulang halaman.']);
    }

    public function test_429_on_get_renders_the_error_page_with_the_retry_after_header(): void
    {
        Route::middleware(['web', 'throttle:2,1'])->get('/_uji/padat', fn () => 'ok');

        $this->get('/_uji/padat')->assertOk();
        $this->get('/_uji/padat')->assertOk();
        $response = $this->get('/_uji/padat');

        $response->assertStatus(429)
            ->assertSee('Terlalu banyak permintaan')
            ->assertHeader('Retry-After');
        $this->assertMatchesRegularExpression('/Coba lagi dalam \d+ detik\./', $response->getContent());
    }

    public function test_429_json_response_has_an_indonesian_message_and_keeps_the_headers(): void
    {
        Route::middleware(['web', 'throttle:1,1'])->get('/_uji/padat-json', fn () => 'ok');

        $this->getJson('/_uji/padat-json')->assertOk();
        $response = $this->getJson('/_uji/padat-json');

        $response->assertStatus(429)->assertHeader('Retry-After');
        $this->assertMatchesRegularExpression('/^Terlalu banyak percobaan\. Silakan coba lagi dalam \d+ detik\.$/', $response->json('message'));
    }

    public function test_429_on_post_redirects_back_with_a_flash_message_and_the_retry_after_header(): void
    {
        Route::middleware(['web', 'throttle:1,1'])->post('/_uji/kirim-padat', fn () => 'ok');

        $this->from('/_uji/form')->post('/_uji/kirim-padat', ['nama' => 'Budi', 'password' => 'RahasiaUji1'])->assertOk();
        $response = $this->from('/_uji/form')->post('/_uji/kirim-padat', ['nama' => 'Budi', 'password' => 'RahasiaUji1']);

        $response->assertRedirect('/_uji/form')
            ->assertHeader('Retry-After')
            ->assertSessionHas('error', fn ($message) => preg_match('/^Terlalu banyak percobaan\. Silakan coba lagi dalam \d+ detik\.$/', $message) === 1)
            ->assertSessionHas('_old_input.nama', 'Budi')
            ->assertSessionMissing('_old_input.password');
    }
}
