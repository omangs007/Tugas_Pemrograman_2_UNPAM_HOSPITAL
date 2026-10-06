<?php

namespace Tests\Feature\Support;

use App\Support\InternalLink;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * F4A-R15: path internal harus cocok dengan route GET yang terdaftar.
 */
class InternalLinkTest extends TestCase
{
    /** @return array<string, array{0: string}> */
    public static function existingPaths(): array
    {
        return [
            'beranda' => ['/'],
            'layanan' => ['/layanan'],
            'dokter' => ['/dokter'],
            'jadwal dokter' => ['/jadwal-dokter'],
            'dengan query' => ['/dokter?q=a'],
            'dengan query dan fragmen' => ['/dokter?q=a#hasil'],
            'dengan fragmen' => ['/jadwal-dokter#senin'],
            'segmen berparameter' => ['/layanan/poliklinik-anak'],
        ];
    }

    /** @return array<string, array{0: string}> */
    public static function missingPaths(): array
    {
        return [
            'tidak ada' => ['/tidak-ada'],
            'segmen berlebih' => ['/dokter/a/b/c'],
            'kosong' => [''],
            'tanpa slash awal' => ['dokter'],
            'protokol relatif' => ['//x'],
            'protokol relatif dengan path' => ['//x.test/dokter'],
            'url penuh' => ['https://x.test'],
            'url penuh ke host sendiri' => ['https://localhost/dokter'],
            'javascript' => ['javascript:alert(1)'],
            'backslash' => ['/\\dokter'],
            'spasi' => ['/dokter dua'],
            'karakter kontrol' => ["/dokter\x00"],
            'hanya query' => ['?q=a'],
        ];
    }

    #[DataProvider('existingPaths')]
    public function test_a_path_matching_a_registered_get_route_exists(string $path): void
    {
        $this->assertTrue(InternalLink::exists($path));
    }

    #[DataProvider('missingPaths')]
    public function test_anything_else_does_not_exist(string $path): void
    {
        $this->assertFalse(InternalLink::exists($path));
    }

    public function test_the_patient_registration_path_is_missing_until_its_route_exists(): void
    {
        if (Route::has('patient.appointments.create')) {
            $this->markTestSkipped('Route pendaftaran pasien sudah ada (fase 6): tautan banner #2 kini valid.');
        }

        $this->assertFalse(InternalLink::exists('/pasien/pendaftaran/baru'));
    }

    public function test_a_route_registered_later_is_recognised_including_parameters(): void
    {
        $this->assertFalse(InternalLink::exists('/rute-sementara/12'));

        Route::get('/rute-sementara/{id}', fn () => 'ok');

        $this->assertTrue(InternalLink::exists('/rute-sementara/12'));
        $this->assertTrue(InternalLink::exists('/rute-sementara/12?x=1'));
    }

    public function test_a_route_that_only_answers_other_methods_does_not_count(): void
    {
        Route::post('/hanya-post', fn () => 'ok');

        $this->assertFalse(InternalLink::exists('/hanya-post'));
    }
}
