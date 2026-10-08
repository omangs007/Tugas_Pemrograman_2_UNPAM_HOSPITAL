<?php

namespace Tests\Feature\Security;

use App\Enums\AppointmentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithPatients;
use Tests\Support\InteractsWithSchedules;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use InteractsWithPatients;
    use InteractsWithSchedules;
    use RefreshDatabase;

    private const PERMISSIONS_POLICY = 'camera=(), microphone=(), geolocation=(), payment=(), usb=()';

    private const CSP = "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-src https://www.google.com https://maps.google.com; frame-ancestors 'self'; base-uri 'self'; form-action 'self'; object-src 'none'";

    // --- empat header dasar (SEK-07, lingkungan testing) -------------------------------------

    public function test_four_headers_are_always_present_and_csp_and_hsts_are_absent_outside_production(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', self::PERMISSIONS_POLICY)
            ->assertHeaderMissing('Content-Security-Policy')
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_headers_are_also_added_to_error_and_redirect_responses(): void
    {
        foreach (['/tidak-ada' => 404, '/admin' => 302] as $uri => $status) {
            $this->get($uri)
                ->assertStatus($status)
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
                ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
                ->assertHeader('Permissions-Policy', self::PERMISSIONS_POLICY);
        }
    }

    // --- Cache-Control halaman sensitif ------------------------------------------------------

    /**
     * @return array<string, array{string}>
     */
    public static function sensitivePaths(): array
    {
        return [
            'login admin' => ['/admin/login'],
            'dasbor admin (redirect)' => ['/admin'],
            'subpath admin' => ['/admin/apa-saja'],
            'portal pasien' => ['/pasien'],
            'subpath pasien' => ['/pasien/pendaftaran/baru'],
            'login pasien' => ['/masuk'],
            'registrasi' => ['/daftar'],
            'lupa password' => ['/lupa-password'],
            'reset password' => ['/reset-password/abc123'],
        ];
    }

    #[DataProvider('sensitivePaths')]
    public function test_sensitive_paths_are_never_stored_by_caches(string $uri): void
    {
        $this->assertStringContainsString('no-store', (string) $this->get($uri)->headers->get('Cache-Control'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function publicPaths(): array
    {
        return [
            'beranda' => ['/'],
            'halaman publik lain' => ['/dokter'],
            'awalan mirip admin' => ['/administrasi'],
            'awalan mirip pasien' => ['/pasienx'],
        ];
    }

    #[DataProvider('publicPaths')]
    public function test_public_paths_are_not_marked_no_store(string $uri): void
    {
        $this->assertStringNotContainsString('no-store', (string) $this->get($uri)->headers->get('Cache-Control'));
    }

    public function test_explicit_cache_directives_set_by_a_route_are_preserved(): void
    {
        Route::middleware('web')->get('/admin/_uji-cache-umur', fn () => response('x')->header('Cache-Control', 'public, max-age=60'));
        Route::middleware('web')->get('/admin/_uji-cache-tegas', fn () => response('x')->header('Cache-Control', 'no-cache, no-store, must-revalidate'));

        $aged = (string) $this->get('/admin/_uji-cache-umur')->headers->get('Cache-Control');
        $this->assertStringContainsString('max-age=60', $aged);
        $this->assertStringNotContainsString('no-store', $aged);

        $this->assertStringContainsString('must-revalidate', (string) $this->get('/admin/_uji-cache-tegas')->headers->get('Cache-Control'));
    }

    // --- produksi (SEK-07) -------------------------------------------------------------------

    public function test_production_adds_the_exact_csp_with_google_maps_frame_source(): void
    {
        $this->app['env'] = 'production';

        $response = $this->get('/')->assertOk();

        $this->assertSame(self::CSP, $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('frame-src https://www.google.com https://maps.google.com', $response->headers->get('Content-Security-Policy'));
        $response->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', self::PERMISSIONS_POLICY);
    }

    public function test_csp_is_also_sent_on_error_pages_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->get('/tidak-ada')->assertNotFound()->assertHeader('Content-Security-Policy', self::CSP);
    }

    public function test_hsts_is_sent_only_in_production_over_https(): void
    {
        $this->app['env'] = 'production';

        $this->get('http://localhost/')->assertHeaderMissing('Strict-Transport-Security');
        $this->get('https://localhost/')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_hsts_is_not_sent_over_https_outside_production(): void
    {
        $this->get('https://localhost/')
            ->assertOk()
            ->assertHeaderMissing('Strict-Transport-Security')
            ->assertHeaderMissing('Content-Security-Policy');
    }

    // --- halaman pasien fase 6 (SEK-07, SEK-13, SEK-15) ----------------------------------------

    /** @return array<string, array{string, bool}> */
    public static function patientAppointmentPages(): array
    {
        return [
            'wizard' => ['patient.appointments.create', false],
            'riwayat' => ['patient.appointments.index', false],
            'detail' => ['patient.appointments.show', true],
            'bukti cetak' => ['patient.appointments.proof', true],
            'bukti PDF' => ['patient.appointments.proof-pdf', true],
        ];
    }

    /** Halaman berisi data pribadi tidak boleh disimpan cache; HTML tanpa atribut event inline, `javascript:`, atau skrip inline. */
    #[DataProvider('patientAppointmentPages')]
    public function test_patient_appointment_pages_are_never_stored_by_caches_and_html_has_no_inline_scripts(string $routeName, bool $needsCode): void
    {
        $this->travelTo('2026-10-05 07:00:00');
        $patient = $this->createPatient();
        $appointment = $this->bookAppointment($this->createSession(), AppointmentStatus::Confirmed, ['patient_profile_id' => $patient->patientProfile->id]);

        $response = $this->actingAs($patient)->get($needsCode ? route($routeName, ['appointment' => $appointment->booking_code]) : route($routeName))->assertOk();

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        if ($routeName === 'patient.appointments.proof-pdf') {
            return;
        }

        $html = (string) $response->getContent();

        $this->assertSame(0, preg_match('/\son[a-z]+\s*=/i', $html), "Atribut event inline ditemukan di {$routeName}.");
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('<script>', $html, 'Skrip inline (tanpa src) diblokir CSP produksi.');
    }

    /** CSP produksi tidak berubah di fase 6; QR bukti berupa `<img>` data URI yang diizinkan `img-src 'self' data:`. */
    public function test_the_appointment_qr_is_a_data_uri_image_allowed_by_the_unchanged_csp(): void
    {
        $this->travelTo('2026-10-05 07:00:00');
        $patient = $this->createPatient();
        $appointment = $this->bookAppointment($this->createSession(), AppointmentStatus::Confirmed, ['patient_profile_id' => $patient->patientProfile->id]);

        $this->app['env'] = 'production';

        foreach (['patient.appointments.show', 'patient.appointments.proof'] as $routeName) {
            $response = $this->actingAs($patient)->get(route($routeName, ['appointment' => $appointment->booking_code]))->assertOk();

            $this->assertSame(self::CSP, $response->headers->get('Content-Security-Policy'), $routeName);
            $this->assertStringContainsString("img-src 'self' data:", self::CSP);
            $this->assertMatchesRegularExpression('#<img[^>]+src="data:image/svg\+xml;base64,[A-Za-z0-9+/=]+"#', (string) $response->getContent(), $routeName);
        }
    }

    // --- HTML kompatibel dengan CSP ketat ----------------------------------------------------

    /**
     * @return array<string, array{string, int}>
     */
    public static function htmlPages(): array
    {
        return [
            'beranda' => ['/', 200],
            'login admin' => ['/admin/login', 200],
            'login pasien' => ['/masuk', 200],
            'registrasi pasien' => ['/daftar', 200],
            'lupa password' => ['/lupa-password', 200],
            'halaman 404' => ['/tidak-ada', 404],
        ];
    }

    #[DataProvider('htmlPages')]
    public function test_rendered_html_has_no_inline_event_handler_attributes_or_javascript_urls(string $uri, int $status): void
    {
        $html = (string) $this->get($uri)->assertStatus($status)->getContent();

        $this->assertSame(0, preg_match('/\son[a-z]+\s*=/i', $html), "Atribut event inline ditemukan di {$uri}.");
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('<script>', $html, 'Skrip inline (tanpa src) diblokir CSP produksi.');
    }
}
