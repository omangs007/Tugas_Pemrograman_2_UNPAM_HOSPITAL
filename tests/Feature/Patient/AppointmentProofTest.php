<?php

namespace Tests\Feature\Patient;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentType;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\InsurancePartner;
use App\Models\User;
use App\Services\Booking\AppointmentProofService;
use Carbon\CarbonImmutable;
use chillerlan\QRCode\QRCode;
use Database\Seeders\SettingsSeeder;
use FontLib\Font;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithPatients;
use Tests\Support\InteractsWithSchedules;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * Bukti pendaftaran (#30, F6-R26/R27): halaman cetak, PDF A5, QR. PAS-04 (sisi pasien), SEK-12, SEK-13, SEK-15,
 * data sensitif (Review Focus E.5), kepemilikan (E.3). Waktu dibekukan pada Senin 2026-10-12 08.00.
 *
 * Teks PDF diperiksa dua lapis (lihat `pdfText()` dan `capturedPdfHtml()`): ekstraksi dari byte PDF hanya untuk
 * ASCII; karakter non-ASCII dan escape HTML diperiksa pada HTML view PDF yang sama dengan yang diumpankan ke dompdf.
 */
class AppointmentProofTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithPatients;
    use InteractsWithSchedules;
    use InteractsWithStaff;
    use RefreshDatabase;

    private const BPJS = '0009900000011';

    private const COMPLAINT = 'Nyeri dada kiri sejak semalam';

    private const PHONE = '081355550001';

    private const ADDRESS = 'Jl. Merpati No. 5, Pamulang';

    private Clinic $clinic;

    private Doctor $doctor;

    private User $owner;

    private Appointment $appointment;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-10-12 08:00:00'));

        $this->clinic = $this->createClinic(['name' => 'Poli Anak', 'code' => 'ANK']);
        $this->doctor = $this->createDoctor(['name' => 'Sinta Maharani', 'title_prefix' => 'dr.', 'title_suffix' => 'Sp.A'], $this->clinic);
        $this->owner = $this->createPatient(
            ['name' => 'Siti Rahma', 'email' => 'siti@contoh.test'],
            [
                'full_name' => 'Siti Rahma',
                'phone' => self::PHONE,
                'address' => self::ADDRESS,
                'birth_date' => '1985-07-23',
                'bpjs_number' => self::BPJS,
            ],
        );
        $this->appointment = $this->bookFor($this->owner, '2026-10-13', AppointmentStatus::Confirmed, [
            'queue_number' => 5,
            'payment_type' => PaymentType::Bpjs,
            'payment_reference' => self::BPJS,
            'payment_reference_last4' => '0011',
            'complaint' => self::COMPLAINT,
        ]);
    }

    // --- pembantu ----------------------------------------------------------------------------

    private function code(): string
    {
        return sprintf('UPH-ABCD-%04d', ++$this->sequence);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function bookFor(User $user, string $date, AppointmentStatus $status = AppointmentStatus::Confirmed, array $overrides = []): Appointment
    {
        $session = $this->createSession([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'session_date' => $date,
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'doctor_schedule_id' => null,
        ]);

        return $this->bookAppointment($session, $status, array_merge([
            'booking_code' => $this->code(),
            'patient_profile_id' => $user->patientProfile->id,
        ], $overrides));
    }

    private function proofUrl(Appointment $appointment): string
    {
        return route('patient.appointments.proof', $appointment->booking_code);
    }

    private function pdfUrl(Appointment $appointment): string
    {
        return route('patient.appointments.proof-pdf', $appointment->booking_code);
    }

    private function proofPage(?User $user = null, ?Appointment $appointment = null): TestResponse
    {
        return $this->actingAs($user ?? $this->owner)->get($this->proofUrl($appointment ?? $this->appointment));
    }

    private function pdfResponse(?User $user = null, ?Appointment $appointment = null): TestResponse
    {
        return $this->actingAs($user ?? $this->owner)->get($this->pdfUrl($appointment ?? $this->appointment));
    }

    /**
     * Teks ASCII dari byte PDF dompdf: dekompresi tiap stream Flate, ambil string di operator `TJ`, buang byte NUL
     * di antara huruf (PDF berfont subset dua-byte). Karakter non-ASCII dipetakan ke kode rendah oleh subset font,
     * karena itu hanya dipakai untuk asersi ASCII.
     */
    private static function pdfText(string $pdf): string
    {
        $text = '';
        preg_match_all('~stream\r?\n(.*?)\r?\nendstream~s', $pdf, $streams);

        foreach ($streams[1] as $raw) {
            $data = @gzuncompress($raw);

            if ($data === false || ! str_contains($data, ' TJ')) {
                continue;
            }

            preg_match_all('~\[(.*?)\]\s*TJ~s', $data, $operations);

            foreach ($operations[1] as $chunk) {
                preg_match_all('~\(((?:[^()\\\\]|\\\\.)*)\)~s', $chunk, $parts);
                $line = (string) preg_replace('~\\\\(.)~s', '$1', implode('', $parts[1]));
                $text .= str_replace("\0", '', $line)."\n";
            }
        }

        return $text;
    }

    /**
     * Merekam data view PDF dari permintaan nyata ke endpoint PDF, lalu merender ulang view itu menjadi HTML
     * (persis yang diumpankan ke dompdf).
     */
    private function capturedPdfHtml(?User $user = null, ?Appointment $appointment = null): string
    {
        $captured = null;
        View::composer('pdf.appointment-proof', function ($view) use (&$captured): void {
            $captured = $view->getData();
        });

        $this->pdfResponse($user, $appointment)->assertOk();

        $this->assertIsArray($captured, 'View pdf.appointment-proof tidak dirender oleh endpoint PDF.');

        return view('pdf.appointment-proof', $captured)->render();
    }

    private function service(): AppointmentProofService
    {
        return app(AppointmentProofService::class);
    }

    // --- halaman cetak -----------------------------------------------------------------------

    public function test_the_proof_page_shows_the_booking_details_qr_and_print_controls(): void
    {
        $profile = $this->owner->patientProfile;
        $code = $this->appointment->booking_code;
        $session = $this->appointment->practiceSession;

        $response = $this->proofPage()->assertOk();

        $response->assertSee('<title>Bukti Pendaftaran '.$code.' — '.config('app.name').'</title>', false)
            ->assertSee('<meta name="robots" content="noindex,nofollow">', false)
            ->assertSee('Bukti Pendaftaran Kunjungan')
            ->assertSee('UNPAM HOSPITAL')
            ->assertSee($code)
            ->assertSee('ANK-005')
            ->assertSee('Dikonfirmasi')
            ->assertSee('Siti Rahma')
            ->assertSee($profile->patient_number)
            ->assertSee('Poli Anak')
            ->assertSee('dr. Sinta Maharani, Sp.A')
            ->assertSee($session->session_date->translatedFormat('l, j F Y'))
            ->assertSee('09.00–12.00')
            ->assertSee('BPJS Kesehatan')
            ->assertSee('•••••••••0011')
            ->assertSee('Online')
            ->assertSee('Tunjukkan kode ini di loket pendaftaran. Check-in dibuka 60 menit sebelum sesi dimulai dan ditutup saat sesi berakhir.')
            ->assertSee('Dicetak pada '.CarbonImmutable::now()->translatedFormat('j M Y H.i').' WIB')
            ->assertSee('alt="Kode QR booking '.$code.'"', false)
            ->assertSee('Unduh PDF')
            ->assertSee('href="'.$this->pdfUrl($this->appointment).'"', false)
            ->assertSee('href="'.route('patient.appointments.show', $code).'"', false);
        $this->assertSame(1, substr_count($response->getContent(), 'data-print'));
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
        $this->assertStringContainsString('d-print-none', $response->getContent());
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_the_proof_page_has_the_breadcrumb_trail(): void
    {
        $code = $this->appointment->booking_code;

        $this->proofPage()
            ->assertOk()
            ->assertSee('<li class="breadcrumb-item"><a href="'.route('patient.dashboard').'">Portal Pasien</a></li>', false)
            ->assertSee('<li class="breadcrumb-item"><a href="'.route('patient.appointments.index').'">Riwayat Pendaftaran</a></li>', false)
            ->assertSee('<li class="breadcrumb-item"><a href="'.route('patient.appointments.show', $code).'">'.$code.'</a></li>', false)
            ->assertSee('<li class="breadcrumb-item active" aria-current="page">Bukti Pendaftaran</li>', false);
    }

    public function test_the_proof_page_embeds_the_svg_qr_data_uri_and_nothing_raw(): void
    {
        $html = $this->proofPage()->assertOk()->getContent();

        $this->assertSame(1, preg_match('#<img[^>]*class="[^"]*proof-qr[^"]*"[^>]*src="(data:image/svg\+xml;base64,[A-Za-z0-9+/=]+)"#', $html, $matches));
        $this->assertSame($this->service()->qrSvgDataUri($this->appointment), $matches[1]);
        $this->assertStringNotContainsString('<svg', $html, 'QR disajikan sebagai data URI, bukan SVG mentah di dalam HTML.');
    }

    public function test_the_proof_page_never_shows_sensitive_patient_data(): void
    {
        $this->proofPage()
            ->assertOk()
            ->assertDontSee(self::BPJS)
            ->assertDontSee('000990000')
            ->assertDontSee(self::COMPLAINT)
            ->assertDontSee('Nyeri dada')
            ->assertDontSee(self::PHONE)
            ->assertDontSee('Merpati')
            ->assertDontSee('1985')
            ->assertDontSee('siti@contoh.test');

        // SEK-12: nilai di basis data terenkripsi, bukan teks asli.
        $stored = (string) DB::table('appointments')->where('id', $this->appointment->id)->value('payment_reference');
        $this->assertNotSame('', $stored);
        $this->assertStringNotContainsString(self::BPJS, $stored);
    }

    public function test_an_insurance_appointment_shows_the_partner_and_the_masked_policy_only(): void
    {
        $partner = InsurancePartner::factory()->create(['name' => 'Asuransi Contoh Sehat']);
        $appointment = $this->bookFor($this->owner, '2026-10-14', AppointmentStatus::Confirmed, [
            'payment_type' => PaymentType::Asuransi,
            'insurance_partner_id' => $partner->id,
            'payment_reference' => 'POL-778899/12',
            'payment_reference_last4' => '9/12',
        ]);

        $this->proofPage(null, $appointment)
            ->assertOk()
            ->assertSee('Asuransi - Asuransi Contoh Sehat')
            ->assertSee('•••••••••9/12')
            ->assertDontSee('POL-778899');
    }

    public function test_a_general_payment_appointment_shows_only_the_payment_label(): void
    {
        $appointment = $this->bookFor($this->owner, '2026-10-14');

        $this->proofPage(null, $appointment)
            ->assertOk()
            ->assertSee('Umum/Pribadi')
            ->assertDontSee('•••••••••');
    }

    public function test_a_cancelled_appointment_still_has_a_proof_with_a_not_valid_notice(): void
    {
        $appointment = $this->bookFor($this->owner, '2026-10-14', AppointmentStatus::Cancelled);

        $this->proofPage(null, $appointment)
            ->assertOk()
            ->assertSee('Dibatalkan')
            ->assertSee('Pendaftaran ini telah dibatalkan. Bukti ini tidak berlaku untuk check-in.');

        $this->proofPage()->assertOk()->assertDontSee('Bukti ini tidak berlaku untuk check-in.');
    }

    public function test_the_patient_name_is_escaped_on_the_proof_page(): void
    {
        $evil = $this->createPatient(['email' => 'jahat@contoh.test'], ['full_name' => '<script>alert(1)</script>']);
        $appointment = $this->bookFor($evil, '2026-10-14', AppointmentStatus::Confirmed, ['complaint' => '<script>alert(2)</script>']);

        $this->proofPage($evil, $appointment)
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('alert(2)');
    }

    public function test_the_detail_page_links_to_the_proof_and_the_pdf(): void
    {
        $code = $this->appointment->booking_code;

        $response = $this->actingAs($this->owner)->get(route('patient.appointments.show', $code))->assertOk();

        $response->assertSee('Cetak Bukti')
            ->assertSee('Unduh PDF')
            ->assertSee('href="'.$this->proofUrl($this->appointment).'"', false)
            ->assertSee('href="'.$this->pdfUrl($this->appointment).'"', false)
            ->assertSeeInOrder(['Cetak Bukti', 'Unduh PDF', 'Batalkan Pendaftaran', 'Ke Dashboard']);
    }

    // --- QR (SEK-13) -------------------------------------------------------------------------

    public function test_the_qr_payload_is_only_the_booking_code(): void
    {
        $payload = $this->service()->qrPayload($this->appointment);

        $this->assertSame($this->appointment->booking_code, $payload);
        $this->assertMatchesRegularExpression('/^UPH-[0-9A-HJKMNP-TV-Z]{4}-[0-9A-HJKMNP-TV-Z]{4}$/', $payload);
        $this->assertStringNotContainsString('Siti', $payload);
        $this->assertStringNotContainsString(self::BPJS, $payload);
        $this->assertStringNotContainsString((string) $this->appointment->id.'|', $payload);
    }

    public function test_the_png_qr_data_uri_decodes_to_exactly_the_booking_code(): void
    {
        $uri = $this->service()->qrPngDataUri($this->appointment);

        $this->assertStringStartsWith('data:image/png;base64,', $uri);
        $binary = base64_decode(substr($uri, strlen('data:image/png;base64,')), true);
        $this->assertNotFalse($binary);
        $this->assertStringStartsWith("\x89PNG", $binary);
        $this->assertSame($this->appointment->booking_code, (string) (new QRCode)->readFromBlob($binary));
    }

    public function test_the_svg_qr_data_uri_is_an_image_without_text_or_personal_data(): void
    {
        $uri = $this->service()->qrSvgDataUri($this->appointment);

        $this->assertStringStartsWith('data:image/svg+xml;base64,', $uri);
        $svg = (string) base64_decode(substr($uri, strlen('data:image/svg+xml;base64,')), true);
        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringNotContainsString('<text', $svg);
        $this->assertStringNotContainsString('Siti', $svg);
        $this->assertStringNotContainsString(self::BPJS, $svg);
        $this->assertStringNotContainsString($this->appointment->booking_code, $svg);
    }

    // --- PDF: respons -----------------------------------------------------------------------

    public function test_the_pdf_is_an_a5_attachment_with_the_expected_name_and_headers(): void
    {
        $response = $this->pdfResponse()->assertOk();
        $body = $response->getContent();
        $code = $this->appointment->booking_code;

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertMatchesRegularExpression('~^attachment;\s*filename="?bukti-'.preg_quote($code, '~').'\.pdf"?$~', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame((string) strlen($body), $response->headers->get('Content-Length'));
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
        $this->assertStringStartsWith('%PDF-', $body);
        $this->assertSame(1, preg_match('~/MediaBox \[0\.000 0\.000 419\.530 595\.280\]~', $body), 'Ukuran halaman harus A5 potret.');
        $this->assertGreaterThanOrEqual(1, preg_match_all('~/Subtype /Image~', $body), 'Gambar QR (PNG) harus tertanam.');
        $this->assertStringContainsString('DejaVuSans', $body, 'Font DejaVu Sans harus tertanam.');
    }

    public function test_the_pdf_text_has_the_booking_code_and_no_sensitive_data(): void
    {
        $text = self::pdfText($this->pdfResponse()->assertOk()->getContent());
        $code = $this->appointment->booking_code;

        $this->assertNotSame('', trim($text), 'Ekstraksi teks PDF kosong: format PDF berubah atau font tidak tertanam.');
        $this->assertStringContainsString($code, $text);
        $this->assertStringContainsString('Bukti Pendaftaran Kunjungan', $text);
        $this->assertStringContainsString('ANK-005', $text);
        $this->assertStringContainsString('Siti Rahma', $text);
        $this->assertStringContainsString('Poli Anak', $text);
        $this->assertStringContainsString('BPJS Kesehatan', $text);
        $this->assertStringContainsString('0011', $text);

        foreach ([self::BPJS, '000990000', self::COMPLAINT, 'Nyeri dada', self::PHONE, 'Merpati', '1985', 'siti@contoh.test'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $text, "PDF tidak boleh memuat: {$forbidden}");
        }
    }

    // --- PDF: HTML yang diumpankan ke dompdf -------------------------------------------------

    public function test_the_pdf_html_has_the_mask_dash_png_qr_and_only_local_resources(): void
    {
        $html = $this->capturedPdfHtml();
        $code = $this->appointment->booking_code;

        $this->assertStringContainsString('<title>Bukti Pendaftaran '.$code.'</title>', $html);
        $this->assertStringContainsString('Bukti Pendaftaran Kunjungan', $html);
        $this->assertStringContainsString($code, $html);
        $this->assertStringContainsString('ANK-005', $html);
        $this->assertStringContainsString('BPJS Kesehatan (•••••••••0011)', $html);
        $this->assertStringContainsString('09.00–12.00', $html);
        $this->assertStringContainsString('Siti Rahma', $html);
        $this->assertStringContainsString('Check-in dibuka 60 menit sebelum sesi dimulai', $html);
        $this->assertStringContainsString('Dicetak pada '.CarbonImmutable::now()->translatedFormat('j M Y H.i').' WIB', $html);
        $this->assertStringContainsString("font-family: 'DejaVu Sans'", $html);
        $this->assertSame(1, preg_match('#<img[^>]*src="data:image/png;base64,[A-Za-z0-9+/=]+"[^>]*alt="Kode QR booking '.preg_quote($code, '#').'"#', $html));
        $this->assertSame(0, preg_match('~(?:src|href)\s*=\s*["\']\s*(?:https?:)?//~i', $html), 'PDF tidak boleh memuat sumber jarak jauh.');
        $this->assertSame(0, preg_match('~<script|<link|@import|<iframe~i', $html));
    }

    public function test_the_pdf_html_has_no_sensitive_data_and_escapes_names(): void
    {
        $plain = $this->capturedPdfHtml();

        foreach ([self::BPJS, '000990000', self::COMPLAINT, 'Nyeri dada', self::PHONE, 'Merpati', '1985', 'siti@contoh.test'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $plain, "HTML PDF tidak boleh memuat: {$forbidden}");
        }

        $evil = $this->createPatient(['email' => 'jahat@contoh.test'], ['full_name' => '<script>alert(1)</script>']);
        $appointment = $this->bookFor($evil, '2026-10-14', AppointmentStatus::Confirmed, ['complaint' => '<script>alert(2)</script>']);
        $html = $this->capturedPdfHtml($evil, $appointment);

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('alert(2)', $html);
    }

    public function test_the_pdf_html_adds_the_not_valid_notice_for_a_cancelled_appointment(): void
    {
        $appointment = $this->bookFor($this->owner, '2026-10-14', AppointmentStatus::Cancelled);

        $this->assertStringContainsString('Pendaftaran ini telah dibatalkan. Bukti ini tidak berlaku untuk check-in.', $this->capturedPdfHtml(null, $appointment));
        $this->assertStringNotContainsString('Bukti ini tidak berlaku untuk check-in.', $this->capturedPdfHtml());
    }

    /**
     * @return array<string, array{0: AppointmentStatus}>
     */
    public static function allStatuses(): array
    {
        $cases = [];

        foreach (AppointmentStatus::cases() as $status) {
            $cases[$status->value] = [$status];
        }

        return $cases;
    }

    #[DataProvider('allStatuses')]
    public function test_the_proof_page_and_the_pdf_render_for_every_status(AppointmentStatus $status): void
    {
        $appointment = $this->bookFor($this->owner, '2026-10-15', $status);

        $this->proofPage(null, $appointment)->assertOk()->assertSee($status->label());
        $pdf = $this->pdfResponse(null, $appointment)->assertOk();

        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->assertStringContainsString($appointment->booking_code, self::pdfText($pdf->getContent()));
    }

    public function test_the_pdf_renders_non_ascii_names_without_error(): void
    {
        $patient = $this->createPatient(['email' => 'unicode@contoh.test'], ['full_name' => 'Åsa Ñandú – Ünal Çelik']);
        $appointment = $this->bookFor($patient, '2026-10-15', AppointmentStatus::Confirmed, [
            'payment_type' => PaymentType::Bpjs,
            'payment_reference' => self::BPJS,
            'payment_reference_last4' => '0011',
        ]);

        $response = $this->pdfResponse($patient, $appointment)->assertOk();

        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringContainsString('Ünal Çelik', $this->capturedPdfHtml($patient, $appointment));
    }

    // --- konfigurasi dompdf dan font (F6-R26) -------------------------------------------------

    public function test_dompdf_is_configured_without_remote_access_php_or_javascript(): void
    {
        $this->assertFalse(config('dompdf.options.enable_remote'));
        $this->assertFalse(config('dompdf.options.enable_php'));
        $this->assertFalse(config('dompdf.options.enable_javascript'));
        $this->assertSame('DejaVu Sans', config('dompdf.options.default_font'));
        $this->assertSame('a5', config('dompdf.options.default_paper_size'));
        $this->assertSame('portrait', config('dompdf.options.default_paper_orientation'));
        $this->assertSame(storage_path('fonts'), config('dompdf.options.font_dir'));
        $this->assertSame(storage_path('fonts'), config('dompdf.options.font_cache'));
        $this->assertSame(realpath(base_path()), config('dompdf.options.chroot'));
        $this->assertSame(['data://', 'file://'], array_keys(config('dompdf.options.allowed_protocols')));
        $this->assertFalse(config('dompdf.show_warnings'));
    }

    public function test_the_resolved_dompdf_instance_follows_the_configuration(): void
    {
        $options = app('dompdf')->getOptions();

        $this->assertFalse($options->getIsRemoteEnabled());
        $this->assertFalse($options->getIsPhpEnabled());
        $this->assertFalse($options->getIsJavascriptEnabled());
        $this->assertSame('DejaVu Sans', $options->getDefaultFont());
        $this->assertSame('a5', strtolower((string) $options->getDefaultPaperSize()));
    }

    public function test_the_font_directory_exists_and_its_contents_are_ignored_by_git(): void
    {
        $this->assertDirectoryExists(storage_path('fonts'));
        $this->assertFileExists(storage_path('fonts/.gitignore'));

        $ignore = (string) file_get_contents(storage_path('fonts/.gitignore'));
        $this->assertStringContainsString('*', $ignore);
        $this->assertStringContainsString('!.gitignore', $ignore);
    }

    public function test_dejavu_sans_has_the_glyphs_used_by_the_proof(): void
    {
        $font = Font::load(base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf'));
        $font->parse();

        $map = null;
        foreach ($font->getData('cmap', 'subtables') as $subtable) {
            if (isset($subtable['glyphIndexArray'])) {
                $map = $subtable['glyphIndexArray'];
                break;
            }
        }

        $this->assertIsArray($map);
        foreach ([0x2022 => 'bullet', 0x2013 => 'en dash', 0x00C5 => 'A ring', 0x00D1 => 'N tilde'] as $codePoint => $name) {
            $this->assertArrayHasKey($codePoint, $map, "DejaVu Sans tidak memiliki glyph {$name}.");
        }

        $font->close();
    }

    // --- aset cetak: JS dan SCSS -------------------------------------------------------------

    public function test_the_print_button_is_wired_by_one_delegated_listener_without_inline_handlers(): void
    {
        $app = (string) file_get_contents(base_path('resources/js/app.js'));

        $this->assertStringContainsString('[data-print]', $app);
        $this->assertStringContainsString('window.print()', $app);
        $this->assertSame(1, preg_match_all("/addEventListener\\(\\s*'error'/", $app), 'Tidak boleh ada listener error baru.');
        $this->assertSame(0, preg_match('/\son[a-z]{3,}\s*=\s*["\']/i', (string) file_get_contents(resource_path('views/patient/appointments/proof.blade.php'))));
        $this->assertStringNotContainsString('{!!', (string) file_get_contents(resource_path('views/patient/appointments/proof.blade.php')));
        $this->assertStringNotContainsString('{!!', (string) file_get_contents(resource_path('views/pdf/appointment-proof.blade.php')));
    }

    public function test_the_print_stylesheet_targets_the_real_layout_and_defines_the_a5_page(): void
    {
        $scss = (string) file_get_contents(base_path('resources/scss/_print.scss'));

        foreach (['@media print', '@page proof', 'size: A5 portrait', 'margin: 10mm', 'page: proof', 'body > header', 'body > footer', 'aside', '[data-flash]', '.proof-sheet', '.proof-qr'] as $needle) {
            $this->assertStringContainsString($needle, $scss, "_print.scss harus memuat {$needle}");
        }
    }

    // --- akses (PAS-04, Review Focus E.3) ----------------------------------------------------

    /**
     * @return array<string, array{0: string}>
     */
    public static function proofRoutes(): array
    {
        return [
            'halaman cetak' => ['patient.appointments.proof'],
            'pdf' => ['patient.appointments.proof-pdf'],
        ];
    }

    #[DataProvider('proofRoutes')]
    public function test_the_owner_gets_200(string $route): void
    {
        $this->actingAs($this->owner)->get(route($route, $this->appointment->booking_code))->assertOk();
    }

    #[DataProvider('proofRoutes')]
    public function test_another_patient_gets_the_same_404_as_for_an_unknown_code_and_sees_nothing(string $route): void
    {
        $intruder = $this->createPatient(['email' => 'penyusup@contoh.test'], ['full_name' => 'Penyusup']);
        $this->actingAs($intruder);

        $foreign = $this->get(route($route, $this->appointment->booking_code));
        $unknown = $this->get(route($route, 'UPH-ABCD-9999'));

        $foreign->assertNotFound();
        $unknown->assertNotFound();
        $this->assertSame($unknown->getStatusCode(), $foreign->getStatusCode());
        $this->assertStringNotContainsString($this->appointment->booking_code, $foreign->getContent());
        $this->assertStringNotContainsString('Siti Rahma', $foreign->getContent());
        $this->assertNotSame('application/pdf', $foreign->headers->get('Content-Type'));
    }

    #[DataProvider('proofRoutes')]
    public function test_malformed_codes_give_404(string $route): void
    {
        $this->actingAs($this->owner);

        foreach (['XYZ', 'UPH-ILLE-OOOO', 'uph-abcd-0001', 'UPH-ABCD-00011'] as $code) {
            $this->get(route($route, $code))->assertNotFound();
        }
    }

    #[DataProvider('proofRoutes')]
    public function test_staff_are_sent_to_the_admin_dashboard(string $route): void
    {
        $this->actingAs($this->createStaff('content-admin'))
            ->get(route($route, $this->appointment->booking_code))
            ->assertRedirect(route('admin.dashboard'));
    }

    #[DataProvider('proofRoutes')]
    public function test_guests_are_sent_to_login_with_the_full_intended_url(string $route): void
    {
        $url = route($route, $this->appointment->booking_code);

        $this->get($url)->assertRedirect(route('login'));

        $this->assertSame($url, session('url.intended'));
    }

    public function test_the_routes_have_the_expected_uris_and_patient_guards(): void
    {
        $code = $this->appointment->booking_code;

        $this->assertSame('/pasien/pendaftaran/'.$code.'/bukti', route('patient.appointments.proof', $code, false));
        $this->assertSame('/pasien/pendaftaran/'.$code.'/bukti.pdf', route('patient.appointments.proof-pdf', $code, false));

        foreach (['patient.appointments.proof', 'patient.appointments.proof-pdf'] as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route);
            $this->assertSame(['GET', 'HEAD'], $route->methods());
            $middleware = $route->gatherMiddleware();
            $this->assertContains('auth', $middleware);
            $this->assertContains('account:patient', $middleware);
            $this->assertContains('active', $middleware);
            $this->assertNotContains('throttle:booking', $middleware);
        }
    }
}
