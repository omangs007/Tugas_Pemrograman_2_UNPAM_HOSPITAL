<?php

namespace Tests\Unit\Support;

use App\Support\AdminReturn;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * F7A-R19: `kembali` admin hanya boleh menunjuk ke /admin/pendaftaran... atau /admin/antrean...; selain itu fallback.
 */
class AdminReturnTest extends TestCase
{
    private const FALLBACK = 'http://localhost/admin/pendaftaran/UPH-ABCD-2345';

    /** @return array<string, array{?string}> */
    public static function accepted(): array
    {
        return [
            'daftar pendaftaran' => ['/admin/pendaftaran'],
            'daftar dengan filter' => ['/admin/pendaftaran?dari=2026-10-05&status=pending'],
            'detail pendaftaran' => ['/admin/pendaftaran/UPH-ABCD-2345'],
            'detail dengan kembali bersarang' => ['/admin/pendaftaran/UPH-ABCD-2345?kembali=%2Fadmin%2Fantrean'],
            'antrean' => ['/admin/antrean'],
            'antrean dengan query' => ['/admin/antrean?tanggal=2026-10-06&sesi=3'],
            'antrean dengan fragmen' => ['/admin/antrean?tanggal=2026-10-06#baris-5'],
        ];
    }

    #[DataProvider('accepted')]
    public function test_it_returns_the_path_for_the_two_allowed_areas(?string $value): void
    {
        $this->assertSame($value, AdminReturn::path($value, self::FALLBACK));
    }

    /** @return array<string, array{?string}> */
    public static function rejected(): array
    {
        return [
            'null' => [null],
            'kosong' => [''],
            'protokol relatif' => ['//evil.test/admin/pendaftaran'],
            'url absolut' => ['https://evil.test/admin/pendaftaran'],
            'skema javascript' => ['javascript:alert(1)'],
            'portal pasien' => ['/pasien'],
            'pasien admin' => ['/admin/pasien'],
            'dasbor admin' => ['/admin'],
            'akar situs' => ['/'],
            'awalan mirip pendaftaran' => ['/admin/pendaftaranbaru'],
            'awalan mirip antrean' => ['/admin/antreanbaru'],
            'huruf besar' => ['/ADMIN/pendaftaran'],
            'tanpa garis miring awal' => ['admin/pendaftaran'],
            'backslash' => ['/admin/pendaftaran\\..\\pasien'],
            'segmen titik-titik' => ['/admin/pendaftaran/../pasien'],
            'segmen titik-titik terenkode' => ['/admin/pendaftaran/%2e%2e/pasien'],
            'segmen titik-titik dengan garis miring terenkode' => ['/admin/antrean/..%2Fpasien'],
            'segmen titik tunggal' => ['/admin/antrean/./../pasien'],
            'newline' => ["/admin/pendaftaran\nSet-Cookie: a=b"],
            'tab menyamarkan protokol relatif' => ["/\t/evil.test"],
        ];
    }

    #[DataProvider('rejected')]
    public function test_it_falls_back_for_anything_else(?string $value): void
    {
        $this->assertSame(self::FALLBACK, AdminReturn::path($value, self::FALLBACK));
    }

    public function test_a_path_longer_than_the_safe_redirect_limit_falls_back(): void
    {
        $this->assertSame(self::FALLBACK, AdminReturn::path('/admin/antrean?q='.str_repeat('a', 2000), self::FALLBACK));
    }
}
