<?php

namespace Tests\Feature\Content;

use App\Models\Banner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * F4A-R15: tombol banner dari database tampil hanya bila label dan URL terisi dan URL-nya layak.
 */
class BannerButtonTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{0: ?string, 1: ?string, 2: bool}> */
    public static function buttons(): array
    {
        return [
            'path internal yang ada' => ['Cari Dokter', '/dokter', true],
            'path internal dengan query' => ['Jadwal Dokter', '/jadwal-dokter?poli=x', true],
            'beranda' => ['Beranda', '/', true],
            'https' => ['Situs Mitra', 'https://contoh.test/a', true],
            'label dengan spasi tepi' => ['  Cari Dokter  ', '  /dokter  ', true],
            'path internal yang tidak ada' => ['Halaman', '/tidak-ada', false],
            'http ditolak' => ['Situs', 'http://contoh.test', false],
            'javascript ditolak' => ['Klik', 'javascript:alert(1)', false],
            'protokol relatif ditolak' => ['Klik', '//evil.test', false],
            'data uri ditolak' => ['Klik', 'data:text/html,x', false],
            'https tanpa host' => ['Klik', 'https://', false],
            'label null' => [null, '/dokter', false],
            'label kosong' => ['', '/dokter', false],
            'label hanya spasi' => ['   ', '/dokter', false],
            'url null' => ['Cari Dokter', null, false],
            'url kosong' => ['Cari Dokter', '', false],
            'url hanya spasi' => ['Cari Dokter', '   ', false],
            'keduanya kosong' => [null, null, false],
        ];
    }

    #[DataProvider('buttons')]
    public function test_has_usable_button(?string $label, ?string $url, bool $expected): void
    {
        $banner = new Banner(['title' => 'Banner Uji', 'button_label' => $label, 'button_url' => $url]);

        $this->assertSame($expected, $banner->hasUsableButton());
    }

    /** F6-R31: route wizard ada sejak fase 6, sehingga tombol banner seed #2 tampil. */
    public function test_the_seeded_registration_banner_has_a_usable_button_now_that_the_wizard_route_exists(): void
    {
        $banner = new Banner(['title' => 'Daftar Kunjungan Lebih Mudah', 'button_label' => 'Daftar Kunjungan', 'button_url' => '/pasien/pendaftaran/baru']);

        $this->assertTrue($banner->hasUsableButton());
    }

    public function test_a_stored_banner_gives_the_same_answer_as_an_unsaved_one(): void
    {
        $banner = Banner::query()->create(['title' => 'Tersimpan', 'button_label' => 'Cari Dokter', 'button_url' => '/dokter']);

        $this->assertTrue(Banner::query()->findOrFail($banner->id)->hasUsableButton());
    }
}
