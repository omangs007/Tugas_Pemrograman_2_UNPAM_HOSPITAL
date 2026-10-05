<?php

namespace Tests\Feature\Access;

use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithMedia;
use Tests\Support\InteractsWithSchedules;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * 15 §3.1 baris 4 sampai 8 dan §3.3 baris 31, 31b, 32, 33, 33b, 34, 35, 35b, 35d × lima aktor (AKS-01 sampai AKS-05),
 * ditambah matriks mutasi non-GET (15 §3 catatan) untuk modul master data, media, dan jadwal.
 * Hasil: 200 = tampil, 403 = ditolak, L = redirect ke admin.login, D = redirect dasbor sesuai tipe akun,
 * OK = lolos otorisasi (200/201/204/302/422, bukan 403/login), G = tamu (redirect login atau 401 JSON).
 * Baris 35c (`admin.sessions.review`, Sesi Terdampak) menyusul di fase 7 bersama route-nya.
 */
class AdminPageMatrixTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithMedia;
    use InteractsWithSchedules;
    use InteractsWithStaff;
    use RefreshDatabase;

    private const ACTORS = ['guest', 'patient', 'content-admin', 'registration-officer', 'super-admin'];

    /** Hasil mutasi untuk route berizin kelola: Tamu G, Pasien 403, Admin Konten OK, Petugas 403, Super Admin OK. */
    private const MANAGE = ['G', '403', 'OK', '403', 'OK'];

    /** @return array<string, array{string, ?string, string, string}> */
    public static function matrix(): array
    {
        // Urutan hasil mengikuti ACTORS: Tamu, Pasien, Admin Konten, Petugas, Super Admin.
        $rows = [
            '31 Login admin' => ['admin.login', null, ['200', 'D', 'D', 'D', 'D']],
            '31b Ganti password staf' => ['admin.password.edit', null, ['L', '403', '200', '200', '200']],
            '32 Dashboard' => ['admin.dashboard', null, ['L', '403', '200', '200', '200']],
            '33 Dokter (daftar)' => ['admin.doctors.index', null, ['L', '403', '200', '200', '200']],
            '33 Dokter (detail)' => ['admin.doctors.show', 'doctor', ['L', '403', '200', '200', '200']],
            '33b Dokter (tambah)' => ['admin.doctors.create', null, ['L', '403', '200', '403', '200']],
            '33b Dokter (ubah)' => ['admin.doctors.edit', 'doctor', ['L', '403', '200', '403', '200']],
            '34 Poliklinik (daftar)' => ['admin.clinics.index', null, ['L', '403', '200', '403', '200']],
            '34 Poliklinik (tambah)' => ['admin.clinics.create', null, ['L', '403', '200', '403', '200']],
            '34 Poliklinik (ubah)' => ['admin.clinics.edit', 'clinic', ['L', '403', '200', '403', '200']],
            '34 Spesialisasi (daftar)' => ['admin.specialties.index', null, ['L', '403', '200', '403', '200']],
            '34 Spesialisasi (tambah)' => ['admin.specialties.create', null, ['L', '403', '200', '403', '200']],
            '34 Spesialisasi (ubah)' => ['admin.specialties.edit', 'specialty', ['L', '403', '200', '403', '200']],
            '35 Jadwal (templat, daftar)' => ['admin.schedules.index', null, ['L', '403', '200', '200', '200']],
            '35 Jadwal (sesi, daftar)' => ['admin.sessions.index', null, ['L', '403', '200', '200', '200']],
            '35 Jadwal (pengecualian, daftar)' => ['admin.exceptions.index', null, ['L', '403', '200', '200', '200']],
            '35b Jadwal (templat, tambah)' => ['admin.schedules.create', null, ['L', '403', '200', '403', '200']],
            '35b Jadwal (templat, ubah)' => ['admin.schedules.edit', 'schedule', ['L', '403', '200', '403', '200']],
            '35b Jadwal (sesi, tambah)' => ['admin.sessions.create', null, ['L', '403', '200', '403', '200']],
            '35b Jadwal (sesi, ubah)' => ['admin.sessions.edit', 'session', ['L', '403', '200', '403', '200']],
            '35b Jadwal (pengecualian, tambah)' => ['admin.exceptions.create', null, ['L', '403', '200', '403', '200']],
            '35b Jadwal (pengecualian, ubah)' => ['admin.exceptions.edit', 'exception', ['L', '403', '200', '403', '200']],
        ];

        $cases = [];

        foreach ($rows as $label => [$routeName, $kind, $expected]) {
            foreach (self::ACTORS as $index => $actor) {
                $cases["{$label} / {$actor}"] = [$routeName, $kind, $actor, $expected[$index]];
            }
        }

        return $cases;
    }

    /** @return array<string, array{string, ?string, string}> */
    public static function publicMatrix(): array
    {
        $cases = [];

        $rows = [
            '4 Daftar layanan' => ['clinics.index', null],
            '5 Detail layanan' => ['clinics.show', 'public-clinic'],
            '6 Daftar dokter' => ['doctors.index', null],
            '7 Detail dokter' => ['doctors.show', 'public-doctor'],
            '8 Jadwal dokter' => ['schedules.index', null],
        ];

        foreach ($rows as $label => [$routeName, $kind]) {
            foreach (self::ACTORS as $actor) {
                $cases["{$label} / {$actor}"] = [$routeName, $kind, $actor];
            }
        }

        return $cases;
    }

    /** @return array<string, array{string, string, ?string, string, string}> */
    public static function mutations(): array
    {
        // Hasil ditentukan per baris: [metode, route, jenis parameter, hasil menurut urutan ACTORS].
        $rows = [
            'POST dokter baru' => ['post', 'admin.doctors.store', null, self::MANAGE],
            'PUT ubah dokter' => ['put', 'admin.doctors.update', 'doctor', self::MANAGE],
            'PATCH status dokter' => ['patch', 'admin.doctors.status', 'doctor', self::MANAGE],
            'PATCH arsip dokter' => ['patch', 'admin.doctors.archive', 'doctor', self::MANAGE],
            'POST foto dokter' => ['post', 'admin.doctors.photo', 'doctor', self::MANAGE],
            'POST spesialisasi baru' => ['post', 'admin.specialties.store', null, self::MANAGE],
            'PUT ubah spesialisasi' => ['put', 'admin.specialties.update', 'specialty', self::MANAGE],
            'DELETE spesialisasi' => ['delete', 'admin.specialties.destroy', 'specialty', self::MANAGE],
            'POST poliklinik baru' => ['post', 'admin.clinics.store', null, self::MANAGE],
            'PUT ubah poliklinik' => ['put', 'admin.clinics.update', 'clinic', self::MANAGE],
            'DELETE poliklinik' => ['delete', 'admin.clinics.destroy', 'clinic', self::MANAGE],
            'POST unggah gambar editor' => ['post', 'admin.media.editor-upload', null, self::MANAGE],
            'POST urutkan media' => ['post', 'admin.media.reorder', null, self::MANAGE],
            'PATCH ubah media dokter' => ['patch', 'admin.media.update', 'doctor-media', self::MANAGE],
            'DELETE media dokter' => ['delete', 'admin.media.destroy', 'doctor-media', self::MANAGE],
            'POST templat jadwal baru' => ['post', 'admin.schedules.store', null, self::MANAGE],
            'PUT ubah templat jadwal' => ['put', 'admin.schedules.update', 'schedule', self::MANAGE],
            'DELETE templat jadwal' => ['delete', 'admin.schedules.destroy', 'schedule', self::MANAGE],
            'POST pengecualian baru' => ['post', 'admin.exceptions.store', null, self::MANAGE],
            'PUT ubah pengecualian' => ['put', 'admin.exceptions.update', 'exception', self::MANAGE],
            'DELETE pengecualian' => ['delete', 'admin.exceptions.destroy', 'exception', self::MANAGE],
            'POST sesi tambahan manual' => ['post', 'admin.sessions.store', null, self::MANAGE],
            'PUT ubah sesi' => ['put', 'admin.sessions.update', 'session', self::MANAGE],
            'PATCH status sesi' => ['patch', 'admin.sessions.status', 'session', self::MANAGE],
            'DELETE sesi (35d)' => ['delete', 'admin.sessions.destroy', 'session', self::MANAGE],
        ];

        $cases = [];

        foreach ($rows as $label => [$method, $routeName, $kind, $expected]) {
            foreach (self::ACTORS as $index => $actor) {
                $cases["{$label} / {$actor}"] = [$method, $routeName, $kind, $actor, $expected[$index]];
            }
        }

        return $cases;
    }

    #[DataProvider('matrix')]
    public function test_admin_page_matrix(string $routeName, ?string $kind, string $actor, string $expected): void
    {
        $user = $this->userFor($actor);

        if ($user !== null) {
            $this->actingAs($user);
        }

        $response = $this->get(route($routeName, $this->parameters($kind)));

        match ($expected) {
            '200' => $response->assertOk(),
            '403' => $response->assertForbidden(),
            'L' => $response->assertRedirect(route('admin.login')),
            'D' => $response->assertRedirect($this->dashboardFor($actor)),
        };
    }

    #[DataProvider('publicMatrix')]
    public function test_public_page_matrix_is_open_to_every_actor(string $routeName, ?string $kind, string $actor): void
    {
        $user = $this->userFor($actor);

        if ($user !== null) {
            $this->actingAs($user);
        }

        $this->get(route($routeName, $this->parameters($kind)))->assertOk();
    }

    #[DataProvider('mutations')]
    public function test_mutation_matrix_denies_actors_without_permission(string $method, string $routeName, ?string $kind, string $actor, string $expected): void
    {
        $user = $this->userFor($actor);

        if ($user !== null) {
            $this->actingAs($user);
        }

        $response = $this->{$method}(route($routeName, $this->parameters($kind)));

        match ($expected) {
            '403' => $response->assertForbidden(),
            'G' => $this->assertGuestRejected($response->getStatusCode(), (string) $response->headers->get('Location')),
            'OK' => $this->assertAuthorized($response->getStatusCode(), (string) $response->headers->get('Location')),
        };
    }

    public function test_patient_is_forbidden_from_admin_doctor_urls_aks_06(): void
    {
        $patient = User::factory()->patient()->create();

        $this->actingAs($patient)->get('/admin/dokter')->assertForbidden();
        $this->actingAs($patient)->post('/admin/dokter')->assertForbidden();
    }

    public function test_patient_is_forbidden_from_admin_schedule_urls(): void
    {
        $patient = User::factory()->patient()->create();

        $this->actingAs($patient)->get('/admin/jadwal')->assertForbidden();
        $this->actingAs($patient)->get('/admin/jadwal/sesi')->assertForbidden();
        $this->actingAs($patient)->get('/admin/jadwal/pengecualian')->assertForbidden();
        $this->actingAs($patient)->post('/admin/jadwal')->assertForbidden();
    }

    private function assertGuestRejected(int $status, string $location): void
    {
        $this->assertContains($status, [302, 401], "Tamu harus dialihkan ke login atau menerima 401, bukan {$status}.");

        if ($status === 302) {
            $this->assertSame(route('admin.login'), $location);
        }
    }

    private function assertAuthorized(int $status, string $location): void
    {
        $this->assertContains($status, [200, 201, 204, 302, 422], "Aktor berhak tidak boleh menerima {$status}.");
        $this->assertNotSame(route('admin.login'), $location);
    }

    /** @return array<string, int|string> */
    private function parameters(?string $kind): array
    {
        return match ($kind) {
            null => [],
            'doctor' => ['doctor' => $this->createDoctor()->id],
            'clinic' => ['clinic' => $this->createClinic()->id],
            'specialty' => ['specialty' => $this->createSpecialty()->id],
            'public-clinic' => ['clinic' => $this->createClinic()->slug],
            'public-doctor' => ['doctor' => $this->createDoctor()->slug],
            'doctor-media' => ['media' => $this->doctorPhotoRow()->id],
            'schedule' => ['schedule' => $this->createSchedule()->id],
            'session' => ['session' => $this->createSession()->id],
            'exception' => ['exception' => $this->createException()->id],
        };
    }

    private function doctorPhotoRow(): Media
    {
        $doctor = $this->createDoctor();

        return $this->createMediaRow([
            'model_type' => $doctor->getMorphClass(),
            'model_id' => $doctor->id,
            'collection' => 'photo',
            'directory' => 'photo/2026/10',
        ]);
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
