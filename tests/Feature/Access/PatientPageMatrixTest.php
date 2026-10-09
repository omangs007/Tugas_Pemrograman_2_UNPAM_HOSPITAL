<?php

namespace Tests\Feature\Access;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AppointmentStatusHistory;
use App\Models\AuditLog;
use App\Models\PracticeSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithPatients;
use Tests\Support\InteractsWithSchedules;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * 15 §3.2 baris #23 sampai #28 × lima aktor (AKS-01 sampai AKS-05) dengan F5-R5, ditambah matriks mutasi non-GET
 * (15 §3 catatan) untuk `patient.profile.update`, `patient.password.update`, dan `logout`, serta AKS-02 dan AKS-06
 * dijalankan ulang dengan pasien nyata (`createPatient()`). Fase 6 menambah baris #29 dan #30 (`patient.appointments.create|index`),
 * halaman berkode booking (detail, bukti cetak, bukti PDF), mutasi wizard dan notifikasi (`review`, `store`, `cancel`,
 * `notifications.read`), kepemilikan (PAS-02, PAS-03, PAS-04, PAS-08), dan AKS-07 asli. Hasil tambahan: S = berhasil (302 ke
 * detail pendaftaran).
 *
 * Hasil: 200 = tampil, L = redirect ke `login` (BUKAN `admin.login`), D = redirect ke dashboard sesuai tipe akun,
 * A = staf di portal pasien dialihkan ke `admin.dashboard` (F5-R5, D-142; menggantikan "403" di 15 §3.2),
 * G = tamu ditolak (redirect `login`), V = lolos otorisasi lalu ditolak validasi (302 dengan galat), X = keluar.
 */
class PatientPageMatrixTest extends TestCase
{
    use InteractsWithPatients;
    use InteractsWithSchedules;
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
            '29 Pendaftaran kunjungan' => ['patient.appointments.create', null, self::PORTAL],
            '30 Riwayat pendaftaran' => ['patient.appointments.index', null, self::PORTAL],
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
     * AKS-06 (fase 7a): `/admin/pasien` kini punya route, jadi pasien mendapat 403 (bukan 404) tanpa konten admin.
     * Route pasien lain milik fase 7a (`/admin/pendaftaran`, `/admin/antrean`) dimasukkan matriks oleh T13.
     */
    public function test_aks_06_patient_is_forbidden_from_the_admin_patient_list_and_profile(): void
    {
        $patient = $this->createPatient();

        $this->actingAs($patient)
            ->get('/admin/pasien')
            ->assertForbidden()
            ->assertDontSee('admin-sidebar', false)
            ->assertDontSee('Menu admin');

        $this->actingAs($patient)->get('/admin/pasien/'.$patient->patientProfile->id)->assertForbidden();
    }

    /**
     * AKS-06 (carry): `/admin/pengaturan` belum punya route sampai fase 8, sehingga menjawab 404.
     * Fase 8 mengubah tes ini menjadi 403 (15 AKS-06).
     */
    public function test_aks_06_settings_url_answers_404_until_phase_8(): void
    {
        $patient = $this->createPatient();

        $this->actingAs($patient)->get('/admin/pengaturan')->assertNotFound();
        $this->actingAs($patient)->put('/admin/pengaturan')->assertNotFound();
    }

    /**
     * AKS-06 (fase 7a): seluruh URL admin 7a ditolak 403 untuk pasien, GET dan tulis, tanpa konten admin (T5 menutup
     * `/admin/pasien` dan detailnya; di sini semua yang lain, dengan URL literal, bukan `route()`), dan tidak ada efek samping.
     */
    public function test_aks_06_patient_is_forbidden_from_every_phase_7a_admin_url_without_admin_content(): void
    {
        $this->travelTo('2026-10-05 07:00:00');

        $patient = $this->createPatient();
        $appointment = $this->appointmentFor($patient);
        $profileId = $patient->patientProfile->id;
        $code = $appointment->booking_code;
        $sessionId = $appointment->practice_session_id;

        $gets = [
            '/admin/pasien', '/admin/pasien/buat', "/admin/pasien/{$profileId}", "/admin/pasien/{$profileId}/ubah",
            '/admin/pendaftaran', '/admin/pendaftaran/buat', "/admin/pendaftaran/{$code}", "/admin/pendaftaran/{$code}/bukti",
            '/admin/antrean',
        ];

        foreach ($gets as $url) {
            $this->actingAs($patient)
                ->get($url)
                ->assertForbidden()
                ->assertDontSee('admin-sidebar', false)
                ->assertDontSee('Menu admin');
        }

        $writes = [
            ['post', '/admin/pasien'],
            ['put', "/admin/pasien/{$profileId}"],
            ['patch', "/admin/pasien/{$profileId}"],
            ['patch', "/admin/pasien/{$profileId}/status"],
            ['post', "/admin/pasien/{$profileId}/hubungkan-akun"],
            ['post', '/admin/pendaftaran'],
            ['post', "/admin/pendaftaran/{$code}/status"],
            ['post', "/admin/antrean/{$sessionId}/tidak-hadir"],
        ];

        foreach ($writes as [$method, $url]) {
            $this->actingAs($patient)->{$method}($url)->assertForbidden();
        }

        $this->assertSame(AppointmentStatus::Confirmed, $appointment->fresh()->status);
        $this->assertTrue($patient->patientProfile->fresh()->is_active);
    }

    // --- fase 6: halaman berkode booking, mutasi wizard, kepemilikan, dan AKS-07 asli ----------------------------------

    /**
     * Halaman berkode booking (detail, bukti cetak, bukti PDF): Tamu →L, Pasien pemilik 200, staf →A.
     *
     * @return array<string, array{string, string, string}>
     */
    public static function appointmentPages(): array
    {
        $rows = [
            '30a Detail pendaftaran' => 'patient.appointments.show',
            '30b Bukti cetak' => 'patient.appointments.proof',
            '30c Bukti PDF' => 'patient.appointments.proof-pdf',
        ];

        $cases = [];

        foreach ($rows as $label => $routeName) {
            foreach (self::ACTORS as $index => $actor) {
                $cases["{$label} / {$actor}"] = [$routeName, $actor, self::PORTAL[$index]];
            }
        }

        return $cases;
    }

    /**
     * Mutasi wizard dan notifikasi. G = tamu →login; V = lolos otorisasi lalu ditolak validasi (302 dengan galat);
     * S = berhasil (302 ke detail pendaftaran); A = staf →admin.dashboard.
     *
     * @return array<string, array{string, string, string}>
     */
    public static function appointmentMutations(): array
    {
        $rows = [
            'POST tinjau' => ['patient.appointments.review', ['G', 'V', 'A', 'A', 'A']],
            'POST kirim' => ['patient.appointments.store', ['G', 'V', 'A', 'A', 'A']],
            'POST batal' => ['patient.appointments.cancel', ['G', 'S', 'A', 'A', 'A']],
            'POST baca notifikasi' => ['patient.notifications.read', ['G', 'S', 'A', 'A', 'A']],
        ];

        $cases = [];

        foreach ($rows as $label => [$routeName, $expected]) {
            foreach (self::ACTORS as $index => $actor) {
                $cases["{$label} / {$actor}"] = [$routeName, $actor, $expected[$index]];
            }
        }

        return $cases;
    }

    /** @return array<string, array{string}> */
    public static function malformedCodes(): array
    {
        return [
            'bukan kode' => ['abc'],
            'terlalu pendek' => ['UPH-ABCD'],
            'huruf I dikecualikan' => ['UPH-ABCD-EFGI'],
            'huruf O dikecualikan' => ['UPH-ABCD-EFGO'],
            'huruf U dikecualikan' => ['UPH-ABCD-EFGU'],
            'huruf kecil' => ['uph-abcd-efgh'],
            'kelebihan segmen' => ['UPH-ABCD-EFGH-1'],
            'pemisah ganda' => ['UPH--ABCD-EFGH'],
        ];
    }

    #[DataProvider('appointmentPages')]
    public function test_appointment_page_matrix(string $routeName, string $actor, string $expected): void
    {
        $this->travelTo('2026-10-05 07:00:00');

        $user = $this->userFor($actor);
        $appointment = $this->appointmentFor($actor === 'patient' ? $user : $this->createPatient());

        if ($user !== null) {
            $this->actingAs($user);
        }

        $response = $this->get(route($routeName, ['appointment' => $appointment->booking_code]));

        match ($expected) {
            '200' => $response->assertOk(),
            'L' => $response->assertRedirect(route('login')),
            'A' => $response->assertRedirect(route('admin.dashboard')),
        };

        if ($routeName === 'patient.appointments.proof-pdf' && $expected === '200') {
            $this->assertStringContainsString('application/pdf', (string) $response->headers->get('Content-Type'));
            $this->assertStringContainsString('bukti-'.$appointment->booking_code.'.pdf', (string) $response->headers->get('Content-Disposition'));
        }
    }

    #[DataProvider('appointmentMutations')]
    public function test_appointment_mutation_matrix_per_actor(string $routeName, string $actor, string $expected): void
    {
        $this->travelTo('2026-10-05 07:00:00');

        $user = $this->userFor($actor);
        $owner = $actor === 'patient' ? $user : $this->createPatient();
        $appointment = $this->appointmentFor($owner);
        $notification = $this->notificationFor($owner, $appointment);

        $url = match ($routeName) {
            'patient.appointments.cancel' => route($routeName, ['appointment' => $appointment->booking_code]),
            'patient.notifications.read' => route($routeName, ['id' => $notification->id]),
            default => route($routeName),
        };

        if ($user !== null) {
            $this->actingAs($user);
        }

        $response = $this->from(route('patient.dashboard'))->post($url);

        match ($expected) {
            'G' => $response->assertRedirect(route('login')),
            'A' => $response->assertRedirect(route('admin.dashboard')),
            'V' => $this->assertPassedAuthorizationButFailedValidation($response),
            'S' => $response->assertRedirect(route('patient.appointments.show', ['appointment' => $appointment->booking_code])),
        };
    }

    /** AKS-01 dan Review Focus E.3: staf yang meminta JSON mendapat 403 (bukan redirect) di setiap route fase 6. */
    public function test_staff_get_403_on_every_phase_6_route_for_json_requests(): void
    {
        $this->travelTo('2026-10-05 07:00:00');

        $code = $this->appointmentFor($this->createPatient())->booking_code;
        $notificationId = (string) Str::uuid();

        $gets = ['/pasien/pendaftaran/baru', '/pasien/pendaftaran', "/pasien/pendaftaran/{$code}", "/pasien/pendaftaran/{$code}/bukti", "/pasien/pendaftaran/{$code}/bukti.pdf"];
        $posts = ['/pasien/pendaftaran/tinjau', '/pasien/pendaftaran', "/pasien/pendaftaran/{$code}/batal", "/pasien/notifikasi/{$notificationId}/baca"];

        foreach (['content-admin', 'registration-officer', 'super-admin'] as $role) {
            $staff = $this->createStaff($role);

            foreach ($gets as $url) {
                $this->actingAs($staff)->getJson($url)->assertForbidden();
            }

            foreach ($posts as $url) {
                $this->actingAs($staff)->postJson($url)->assertForbidden();
            }
        }
    }

    /**
     * PAS-02, PAS-03, PAS-04, PAS-08 dan Review Focus E.3: kode booking pasien lain menjawab 404 identik dengan kode yang
     * tidak ada, tanpa data pasien itu pada respons dan tanpa efek samping (status, kuota, riwayat, audit).
     */
    public function test_another_patients_booking_code_answers_404_identical_to_a_missing_code_without_side_effects(): void
    {
        $this->travelTo('2026-10-05 07:00:00');

        $me = $this->createPatient();
        $theirs = $this->appointmentFor($this->createPatient());
        $missing = 'UPH-0000-0000';

        $bookedBefore = (int) PracticeSession::query()->whereKey($theirs->practice_session_id)->value('booked_count');
        $historiesBefore = AppointmentStatusHistory::query()->count();
        $auditsBefore = AuditLog::query()->count();

        $this->actingAs($me);

        foreach (['patient.appointments.show', 'patient.appointments.proof', 'patient.appointments.proof-pdf'] as $name) {
            $foreign = $this->get(route($name, ['appointment' => $theirs->booking_code]))->assertNotFound();
            $absent = $this->get(route($name, ['appointment' => $missing]))->assertNotFound();

            $this->assertSame($this->titleOf($absent), $this->titleOf($foreign), "{$name}: 404 pasien lain harus identik dengan 404 kode tak ada.");
            $this->assertStringNotContainsString($theirs->booking_code, (string) $foreign->getContent(), $name);
        }

        $this->post(route('patient.appointments.cancel', ['appointment' => $theirs->booking_code]), ['cancel_reason' => 'Mencoba membatalkan milik orang lain'])->assertNotFound();
        $this->post(route('patient.appointments.cancel', ['appointment' => $missing]))->assertNotFound();

        $this->assertSame(AppointmentStatus::Confirmed, $theirs->fresh()->status);
        $this->assertSame($bookedBefore, (int) PracticeSession::query()->whereKey($theirs->practice_session_id)->value('booked_count'));
        $this->assertSame($historiesBefore, AppointmentStatusHistory::query()->count());
        $this->assertSame($auditsBefore, AuditLog::query()->count());
    }

    /** PAS-08: notifikasi milik pasien lain 404 (dan tetap belum dibaca); id non-UUID 404. */
    public function test_another_patients_notification_answers_404_and_stays_unread(): void
    {
        $this->travelTo('2026-10-05 07:00:00');

        $me = $this->createPatient();
        $other = $this->createPatient();
        $theirs = $this->notificationFor($other, $this->appointmentFor($other));

        $this->actingAs($me)->post(route('patient.notifications.read', ['id' => $theirs->id]))->assertNotFound();
        $this->actingAs($me)->post('/pasien/notifikasi/bukan-uuid/baca')->assertNotFound();

        $this->assertNull($theirs->fresh()->read_at);
    }

    #[DataProvider('malformedCodes')]
    public function test_a_malformed_booking_code_answers_404_on_every_appointment_route(string $code): void
    {
        $this->actingAs($this->createPatient());

        $base = '/pasien/pendaftaran/'.$code;

        $this->get($base)->assertNotFound();
        $this->get($base.'/bukti')->assertNotFound();
        $this->get($base.'/bukti.pdf')->assertNotFound();
        $this->post($base.'/batal')->assertNotFound();
    }

    /**
     * AKS-07 (versi asli, dipasang kembali di fase 6): tamu yang membuka wizard dari tombol "Daftar" publik diminta masuk
     * dan kembali ke URL yang SAMA dengan query `?sesi=ID` utuh.
     */
    public function test_aks_07_guest_is_sent_back_to_the_wizard_with_the_full_session_query_after_login(): void
    {
        $this->travelTo('2026-10-05 07:00:00');
        $this->createPatient(['email' => 'siti@contoh.test']);
        $session = $this->createSession();
        $url = '/pasien/pendaftaran/baru?sesi='.$session->id;

        $this->get($url)->assertRedirect(route('login'));

        $this->from(route('login'))
            ->post(route('login.store'), ['email' => 'siti@contoh.test', 'password' => $this->patientPassword()])
            ->assertRedirect(url($url))
            ->assertSessionMissing('url.intended');

        $this->get($url)->assertOk();
    }

    /** AKS-02 (tambahan fase 6): pasien membuka wizard dan riwayat 200; staf mengikuti `→A` (matriks); tamu `→L`. */
    public function test_aks_02_patient_sees_the_phase_6_pages(): void
    {
        $this->actingAs($this->createPatient());

        foreach (['patient.appointments.create', 'patient.appointments.index'] as $name) {
            $this->get(route($name))->assertOk();
        }
    }

    private function appointmentFor(User $owner): Appointment
    {
        return $this->bookAppointment($this->createSession(), AppointmentStatus::Confirmed, ['patient_profile_id' => $owner->patientProfile->id]);
    }

    private function notificationFor(User $owner, Appointment $appointment): DatabaseNotification
    {
        return $owner->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\AppointmentStatusChangedNotification',
            'data' => [
                'appointment_id' => $appointment->id,
                'booking_code' => $appointment->booking_code,
                'title' => 'Pendaftaran dikonfirmasi',
                'body' => 'Status pendaftaran Anda kini Dikonfirmasi.',
            ],
        ]);
    }

    private function titleOf(TestResponse $response): string
    {
        preg_match('#<title>(.*?)</title>#s', (string) $response->getContent(), $matches);

        return $matches[1] ?? '';
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
