<?php

namespace Tests\Feature\Access;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithPatients;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * 15 §3.2 baris #23 sampai #28 × lima aktor (AKS-01 sampai AKS-05) dengan F5-R5, ditambah matriks mutasi non-GET
 * (15 §3 catatan) untuk `patient.profile.update`, `patient.password.update`, dan `logout`, serta AKS-02 dan AKS-06
 * dijalankan ulang dengan pasien nyata (`createPatient()`). Baris #29 dan #30 (`patient.appointments.*`) menyusul di fase 6.
 *
 * Hasil: 200 = tampil, L = redirect ke `login` (BUKAN `admin.login`), D = redirect ke dashboard sesuai tipe akun,
 * A = staf di portal pasien dialihkan ke `admin.dashboard` (F5-R5, D-142; menggantikan "403" di 15 §3.2),
 * G = tamu ditolak (redirect `login`), V = lolos otorisasi lalu ditolak validasi (302 dengan galat), X = keluar.
 */
class PatientPageMatrixTest extends TestCase
{
    use InteractsWithPatients;
    use InteractsWithStaff;
    use RefreshDatabase;

    private const ACTORS = ['guest', 'patient', 'content-admin', 'registration-officer', 'super-admin'];

    /** #23 sampai #26 (`guest`): Tamu 200; semua akun yang login diarahkan ke dashboard sesuai tipe. */
    private const GUEST_ONLY = ['200', 'D', 'D', 'D', 'D'];

    /** #27 dan #28: Tamu →L, Pasien 200, staf →A (F5-R5). */
    private const PORTAL = ['L', '200', 'A', 'A', 'A'];

    /** Mutasi profil dan password: Tamu G, Pasien V (lolos otorisasi, galat validasi), staf A. */
    private const PATIENT_MUTATION = ['G', 'V', 'A', 'A', 'A'];

    /** `POST logout` (hanya `auth`): Tamu G; Pasien dan semua staf X (keluar, F5-R8). */
    private const LOGOUT = ['G', 'X', 'X', 'X', 'X'];

    /** @return array<string, array{string, ?string, string, string}> */
    public static function matrix(): array
    {
        // Urutan hasil mengikuti ACTORS: Tamu, Pasien, Admin Konten, Petugas, Super Admin.
        $rows = [
            '23 Registrasi' => ['register', null, self::GUEST_ONLY],
            '24 Login pasien' => ['login', null, self::GUEST_ONLY],
            '25 Permintaan reset' => ['password.request', null, self::GUEST_ONLY],
            '26 Form reset' => ['password.reset', 'token', self::GUEST_ONLY],
            '27 Dashboard pasien' => ['patient.dashboard', null, self::PORTAL],
            '28 Profil dan akun' => ['patient.profile.edit', null, self::PORTAL],
        ];

        $cases = [];

        foreach ($rows as $label => [$routeName, $kind, $expected]) {
            foreach (self::ACTORS as $index => $actor) {
                $cases["{$label} / {$actor}"] = [$routeName, $kind, $actor, $expected[$index]];
            }
        }

        return $cases;
    }

    /** @return array<string, array{string, string, string, string}> */
    public static function mutations(): array
    {
        $rows = [
            'PUT ubah profil' => ['put', 'patient.profile.update', self::PATIENT_MUTATION],
            'PUT ganti password' => ['put', 'patient.password.update', self::PATIENT_MUTATION],
            'POST keluar' => ['post', 'logout', self::LOGOUT],
        ];

        $cases = [];

        foreach ($rows as $label => [$method, $routeName, $expected]) {
            foreach (self::ACTORS as $index => $actor) {
                $cases["{$label} / {$actor}"] = [$method, $routeName, $actor, $expected[$index]];
            }
        }

        return $cases;
    }

    #[DataProvider('matrix')]
    public function test_patient_page_matrix(string $routeName, ?string $kind, string $actor, string $expected): void
    {
        $user = $this->userFor($actor);

        if ($user !== null) {
            $this->actingAs($user);
        }

        $response = $this->get(route($routeName, $this->parameters($kind)));

        match ($expected) {
            '200' => $response->assertOk(),
            'L' => $response->assertRedirect(route('login')),
            'D' => $response->assertRedirect($this->dashboardFor($actor)),
            'A' => $response->assertRedirect(route('admin.dashboard')),
        };
    }

    #[DataProvider('mutations')]
    public function test_mutation_matrix_per_actor(string $method, string $routeName, string $actor, string $expected): void
    {
        $user = $this->userFor($actor);

        if ($user !== null) {
            $this->actingAs($user);
        }

        $response = $this->{$method}(route($routeName));

        match ($expected) {
            'G' => $response->assertRedirect(route('login')),
            'A' => $response->assertRedirect(route('admin.dashboard')),
            'V' => $this->assertPassedAuthorizationButFailedValidation($response),
            'X' => $this->assertLoggedOut($response),
        };
    }

    /** Staf tidak pernah mencapai controller portal pasien: JSON mendapat 403 (F5-R5), bukan redirect. */
    public function test_staff_get_403_on_the_patient_area_for_json_requests(): void
    {
        foreach (['content-admin', 'registration-officer', 'super-admin'] as $role) {
            $staff = $this->createStaff($role);

            $this->actingAs($staff)->getJson('/pasien')->assertForbidden();
            $this->actingAs($staff)->getJson('/pasien/profil')->assertForbidden();
            $this->actingAs($staff)->putJson('/pasien/profil', [])->assertForbidden();
            $this->actingAs($staff)->putJson('/pasien/password', [])->assertForbidden();
        }
    }

    /** AKS-02: Pasien aktif; publik 200, portal pasien 200, halaman admin 403, halaman khusus tamu →D. */
    public function test_aks_02_patient_sees_public_and_portal_pages_but_not_admin_pages(): void
    {
        $this->actingAs($this->createPatient());

        foreach (['home', 'about', 'vision-mission', 'clinics.index', 'doctors.index', 'schedules.index', 'facilities.index', 'rooms.index', 'insurance', 'registration-guide', 'articles.index', 'galleries.index', 'faq', 'contact', 'privacy'] as $name) {
            $this->get(route($name))->assertOk();
        }

        foreach (['patient.dashboard', 'patient.profile.edit'] as $name) {
            $this->get(route($name))->assertOk();
        }

        foreach (['admin.dashboard', 'admin.password.edit', 'admin.doctors.index', 'admin.clinics.index', 'admin.specialties.index', 'admin.schedules.index', 'admin.sessions.index', 'admin.exceptions.index', 'admin.pages.index', 'admin.articles.index', 'admin.facilities.index', 'admin.room-categories.index', 'admin.messages.index'] as $name) {
            $this->get(route($name))->assertForbidden();
        }

        foreach (['register', 'login', 'password.request', 'admin.login'] as $name) {
            $this->get(route($name))->assertRedirect(route('patient.dashboard'));
        }

        $this->get(route('password.reset', ['token' => 'token-contoh']))->assertRedirect(route('patient.dashboard'));
    }

    /** AKS-03 (bagian fase 5): Super Admin membuka halaman admin 200 dan portal pasien →A (bukan 403, D-142). */
    public function test_aks_03_super_admin_is_redirected_away_from_the_patient_area(): void
    {
        $admin = $this->createStaff('super-admin');

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($admin)->get(route('patient.dashboard'))->assertRedirect(route('admin.dashboard'));
        $this->actingAs($admin)->get(route('patient.profile.edit'))->assertRedirect(route('admin.dashboard'));
    }

    /** AKS-06: URL admin yang ada ditolak 403 untuk pasien, termasuk POST `/admin/dokter`, tanpa konten admin. */
    public function test_aks_06_patient_is_forbidden_from_existing_admin_urls_without_admin_content(): void
    {
        $patient = $this->createPatient();

        foreach (['/admin', '/admin/dokter', '/admin/jadwal', '/admin/konten/halaman', '/admin/fasilitas', '/admin/kamar', '/admin/pesan'] as $url) {
            $this->actingAs($patient)
                ->get($url)
                ->assertForbidden()
                ->assertDontSee('admin-sidebar', false)
                ->assertDontSee('Menu admin');
        }

        foreach (['/admin/dokter', '/admin/fasilitas', '/admin/kamar'] as $url) {
            $this->actingAs($patient)->post($url)->assertForbidden();
        }
    }

    /**
     * AKS-06 (carry): `/admin/pasien` dan `/admin/pengaturan` belum punya route sampai fase 7 dan 8, sehingga menjawab 404.
     * Fase 7 dan 8 mengubah tes ini menjadi 403 (15 AKS-06).
     */
    public function test_aks_06_urls_that_do_not_exist_yet_answer_404_until_phases_7_and_8(): void
    {
        $patient = $this->createPatient();

        $this->actingAs($patient)->get('/admin/pasien')->assertNotFound();
        $this->actingAs($patient)->get('/admin/pengaturan')->assertNotFound();
        $this->actingAs($patient)->put('/admin/pengaturan')->assertNotFound();
    }

    private function assertPassedAuthorizationButFailedValidation(TestResponse $response): void
    {
        $response->assertStatus(302);
        $response->assertSessionHasErrors();

        $this->assertNotSame(route('login'), $response->headers->get('Location'));
        $this->assertNotSame(route('admin.dashboard'), $response->headers->get('Location'));
    }

    private function assertLoggedOut(TestResponse $response): void
    {
        $response->assertRedirect(route('home'));
        $this->assertGuest();
    }

    /** @return array<string, string> */
    private function parameters(?string $kind): array
    {
        return match ($kind) {
            null => [],
            'token' => ['token' => 'token-contoh'],
        };
    }

    private function userFor(string $actor): ?User
    {
        return match ($actor) {
            'guest' => null,
            'patient' => $this->createPatient(),
            default => $this->createStaff($actor),
        };
    }

    private function dashboardFor(string $actor): string
    {
        return $actor === 'patient' ? route('patient.dashboard') : route('admin.dashboard');
    }
}
