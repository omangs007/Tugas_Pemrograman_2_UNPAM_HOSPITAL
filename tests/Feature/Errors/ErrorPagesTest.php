<?php

namespace Tests\Feature\Errors;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    public function test_unknown_url_renders_indonesian_404_without_stack_trace_or_queries(): void
    {
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $response = $this->get('/tidak-ada');

        $response->assertNotFound()
            ->assertSee('<html lang="id">', false)
            ->assertSee('Halaman tidak ditemukan')
            ->assertSee('Alamat yang Anda buka tidak tersedia atau sudah dipindahkan.')
            ->assertSee('Ke Beranda')
            ->assertDontSee('NotFoundHttpException')
            ->assertDontSee('Stack trace');
        $this->assertSame(0, $queries, 'Halaman galat tidak boleh membaca database.');
    }

    public function test_unknown_admin_url_also_renders_the_indonesian_404(): void
    {
        $this->get('/admin/tidak-ada')
            ->assertNotFound()
            ->assertSee('Halaman tidak ditemukan');
    }

    public function test_404_links_to_the_doctor_directory(): void
    {
        // Route doctors.index ada sejak fase 3 (#6); tautan jadwal menyusul bersama #8.
        $this->get('/tidak-ada')
            ->assertNotFound()
            ->assertSee('Cari Dokter')
            ->assertSee('href="'.route('doctors.index').'"', false);
    }

    public function test_unhandled_exception_with_debug_off_renders_generic_500_without_internal_message(): void
    {
        config(['app.debug' => false]);
        Route::middleware('web')->get('/_uji/meledak', fn () => throw new RuntimeException('Rahasia internal: kata sandi database'));

        $this->get('/_uji/meledak')
            ->assertStatus(500)
            ->assertSee('Terjadi kesalahan pada server')
            ->assertSee('Kami mencatat masalah ini.')
            ->assertDontSee('Rahasia internal')
            ->assertDontSee('RuntimeException')
            ->assertDontSee('Whoops');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function errorViews(): array
    {
        return [
            '403' => ['403', 'Akses ditolak'],
            '404' => ['404', 'Halaman tidak ditemukan'],
            '419' => ['419', 'Sesi berakhir'],
            '429' => ['429', 'Terlalu banyak permintaan'],
            '500' => ['500', 'Terjadi kesalahan pada server'],
            '503' => ['503', 'Sedang dalam pemeliharaan'],
        ];
    }

    #[DataProvider('errorViews')]
    public function test_every_error_view_is_standalone_indonesian_with_single_h1_and_skip_link(string $code, string $heading): void
    {
        $html = view("errors.{$code}", ['retryAfter' => 45, 'detail' => null])->render();

        $this->assertStringContainsString('<html lang="id">', $html);
        $this->assertStringContainsString('<meta name="robots" content="noindex,nofollow">', $html);
        $this->assertStringContainsString('class="skip-link" href="#konten-utama"', $html);
        $this->assertStringContainsString($heading, $html);
        $this->assertSame(1, substr_count($html, '<h1'));
    }

    public function test_429_view_mentions_the_retry_delay_and_403_view_shows_an_optional_detail(): void
    {
        $this->assertStringContainsString('Coba lagi dalam 45 detik.', view('errors.429', ['retryAfter' => 45])->render());
        $this->assertStringContainsString('Pesan khusus uji.', view('errors.403', ['detail' => 'Pesan khusus uji.'])->render());
        $this->assertStringNotContainsString('Pesan khusus uji.', view('errors.403', ['detail' => null])->render());
    }
}
