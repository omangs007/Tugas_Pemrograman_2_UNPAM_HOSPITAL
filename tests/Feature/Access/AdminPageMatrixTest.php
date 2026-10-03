<?php

namespace Tests\Feature\Access;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * 15 §3.3 baris 31, 31b, 32 × lima aktor (AKS-01 sampai AKS-05, bagian /admin).
 * Hasil: 200 = tampil, 403 = ditolak, L = redirect ke admin.login, D = redirect dasbor sesuai tipe akun.
 */
class AdminPageMatrixTest extends TestCase
{
    use InteractsWithStaff;
    use RefreshDatabase;

    private const ACTORS = ['guest', 'patient', 'content-admin', 'registration-officer', 'super-admin'];

    /** @return array<string, array{string, string, string}> */
    public static function matrix(): array
    {
        // Urutan hasil mengikuti ACTORS: Tamu, Pasien, Admin Konten, Petugas, Super Admin.
        $rows = [
            '31 Login admin' => ['admin.login', ['200', 'D', 'D', 'D', 'D']],
            '31b Ganti password staf' => ['admin.password.edit', ['L', '403', '200', '200', '200']],
            '32 Dashboard' => ['admin.dashboard', ['L', '403', '200', '200', '200']],
        ];

        $cases = [];

        foreach ($rows as $label => [$routeName, $expected]) {
            foreach (self::ACTORS as $index => $actor) {
                $cases["{$label} / {$actor}"] = [$routeName, $actor, $expected[$index]];
            }
        }

        return $cases;
    }

    #[DataProvider('matrix')]
    public function test_admin_page_matrix(string $routeName, string $actor, string $expected): void
    {
        $user = $this->userFor($actor);

        if ($user !== null) {
            $this->actingAs($user);
        }

        $response = $this->get(route($routeName));

        match ($expected) {
            '200' => $response->assertOk(),
            '403' => $response->assertForbidden(),
            'L' => $response->assertRedirect(route('admin.login')),
            'D' => $response->assertRedirect($this->dashboardFor($actor)),
        };
    }

    private function userFor(string $actor): ?User
    {
        return match ($actor) {
            'guest' => null,
            'patient' => User::factory()->patient()->create(),
            default => $this->createStaff($actor),
        };
    }

    private function dashboardFor(string $actor): string
    {
        if ($actor === 'patient') {
            return Route::has('patient.dashboard') ? route('patient.dashboard') : route('home');
        }

        return route('admin.dashboard');
    }
}
