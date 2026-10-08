<?php

namespace Tests\Feature\Patient;

use App\Enums\ActorType;
use App\Enums\AppointmentStatus;
use App\Enums\PaymentType;
use App\Models\Appointment;
use App\Models\AppointmentStatusHistory;
use App\Models\InsurancePartner;
use App\Models\PracticeSession;
use App\Models\User;
use App\Services\Booking\AppointmentProofService;
use App\Services\Booking\BookingData;
use App\Services\Booking\BookingService;
use App\Services\Scheduling\PracticeSessionGenerator;
use App\Support\Actor;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithPatients;
use Tests\Support\InteractsWithSchedules;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;
use Tests\Unit\Services\AppointmentProofServiceTest;

/**
 * Detail pendaftaran (F6-R24): PAS-02, SEK-12, SEK-13, SEK-15, Review Focus 3. Pendaftaran dibuat lewat
 * `BookingService::book()` (jalur produksi), bukan factory.
 */
class AppointmentShowTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithPatients;
    use InteractsWithSchedules;
    use InteractsWithStaff;
    use RefreshDatabase;

    private const BPJS = '0009900000011';

    private ?User $patient = null;

    private ?PracticeSession $bookable = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-05 07:00:00');

        $today = CarbonImmutable::today();
        Cache::put(PracticeSessionGenerator::markerKey($today), true, $today->endOfDay());
    }

    /** @param  array<string, mixed>  $profile */
    private function patient(array $profile = []): User
    {
        return $this->patient ??= $this->createPatient(['name' => 'Siti Rahma', 'email' => 'siti@contoh.test'], $profile + ['full_name' => 'Siti Rahma']);
    }

    /** @param  array<string, mixed>  $doctor */
    private function bookableSession(array $doctor = ['name' => 'Laras Wulandari'], array $clinic = ['name' => 'Poliklinik Anak Uji', 'code' => 'ANK']): PracticeSession
    {
        if ($this->bookable !== null) {
            return $this->bookable;
        }

        $created = $this->createDoctor($doctor, $this->createClinic($clinic));

        return $this->bookable = $this->createSession([
            'doctor_id' => $created->id,
            'clinic_id' => $created->clinic_id,
            'session_date' => '2026-10-06',
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'quota' => 20,
        ]);
    }

    private function book(User $user, ?BookingData $data = null, ?PracticeSession $session = null): Appointment
    {
        return app(BookingService::class)->book(
            $user->patientProfile,
            ($session ?? $this->bookableSession())->id,
            $data ?? new BookingData(PaymentType::Umum, complaint: 'Batuk berdahak sejak tiga hari.'),
            Actor::patient($user),
        );
    }

    private function detailUrl(Appointment $appointment): string
    {
        return route('patient.appointments.show', ['appointment' => $appointment->booking_code]);
    }

    private function open(Appointment $appointment, ?User $user = null): string
    {
        return $this->actingAs($user ?? $this->patient())->get($this->detailUrl($appointment))->assertOk()->getContent();
    }

    // --- isi -----------------------------------------------------------------------------------

    public function test_the_detail_shows_the_booking_data_the_code_box_and_the_status_history(): void
    {
        $user = $this->patient();
        $appointment = $this->book($user);

        $html = $this->open($appointment);

        foreach ([
            '<title>Detail Pendaftaran — '.config('app.name').'</title>',
            $appointment->booking_code,
            'Antrean <strong>ANK-001</strong>',
            'Menunggu konfirmasi',
            'Poliklinik Anak Uji',
            'dr. Laras Wulandari, Sp.PD',
            'Selasa, 6 Oktober 2026</time>, 09.00–12.00',
            'Siti Rahma ('.$user->patientProfile->patient_number.')',
            'Umum/Pribadi',
            'Online',
            'Batuk berdahak sejak tiga hari.',
            'Tunjukkan kode ini di loket. Check-in dibuka 60 menit sebelum sesi dimulai.',
            'Riwayat Status',
            '(oleh Pasien)',
            'Ke Dashboard',
        ] as $text) {
            $this->assertStringContainsString($text, $html, $text);
        }

        $this->assertStringContainsString('<meta name="robots" content="noindex,nofollow">', $html);
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('<li class="breadcrumb-item"><a href="'.route('patient.dashboard').'">Portal Pasien</a></li>', $html);
        $this->assertStringContainsString('<li class="breadcrumb-item active" aria-current="page">'.$appointment->booking_code.'</li>', $html);
        $this->assertStringContainsString('alt="Kode QR booking '.$appointment->booking_code.'"', $html);
    }

    /** FX10: catatan check-in hanya untuk status aktif (`AppointmentStatus::isActive()`); status final tidak memuatnya. */
    public function test_the_check_in_note_shows_only_for_an_active_appointment(): void
    {
        $note = 'Tunjukkan kode ini di loket. Check-in dibuka 60 menit sebelum sesi dimulai.';
        $user = $this->patient();
        $appointment = $this->book($user);

        $this->assertSame(AppointmentStatus::Pending, $appointment->status);
        $this->assertStringContainsString($note, $this->open($appointment));

        foreach ([AppointmentStatus::Cancelled, AppointmentStatus::Completed, AppointmentStatus::NoShow] as $final) {
            DB::table('appointments')->where('id', $appointment->id)->update(['status' => $final->value]);

            $this->assertStringNotContainsString($note, $this->open($appointment->fresh()), $final->value);
        }
    }

    public function test_the_status_history_is_an_ordered_list_of_time_elements_in_chronological_order(): void
    {
        $appointment = $this->book($this->patient());
        (new AppointmentStatusHistory)->forceFill([
            'appointment_id' => $appointment->id,
            'from_status' => AppointmentStatus::Pending,
            'to_status' => AppointmentStatus::Confirmed,
            'actor_type' => ActorType::Staff,
            'reason' => null,
            'created_at' => '2026-10-05 08:45:00',
        ])->save();

        $html = $this->open($appointment);

        $this->assertSame(1, preg_match('#<ol class="list-unstyled" aria-labelledby="judul-riwayat">(.*?)</ol>#s', $html, $matches));
        $history = $matches[1];

        $this->assertSame(2, substr_count($history, '<time datetime="'));
        $this->assertStringContainsString('<time datetime="2026-10-05T08:45:00', $history);
        $this->assertStringContainsString('5 Okt 2026, 08.45', $history);
        $this->assertStringContainsString('(oleh Staf)', $history);
        $this->assertLessThan(strpos($history, 'Dikonfirmasi'), strpos($history, 'Menunggu konfirmasi'));
    }

    public function test_the_schedule_changed_marker_shows_only_when_the_schedule_changed(): void
    {
        $appointment = $this->book($this->patient());

        $this->assertStringNotContainsString('Jadwal berubah pada', $this->open($appointment));

        $appointment->forceFill(['schedule_changed_at' => '2026-10-05 06:00:00'])->save();

        $this->assertStringContainsString('Jadwal berubah pada 5 Okt 2026.', $this->open($appointment));
    }

    public function test_an_insurance_booking_shows_the_partner_name_and_a_masked_policy_number(): void
    {
        $partner = InsurancePartner::factory()->create(['name' => 'Asuransi Contoh Aktif']);
        $appointment = $this->book($this->patient(), new BookingData(PaymentType::Asuransi, 'AB123-45', $partner->id));

        $html = $this->open($appointment);

        $this->assertStringContainsString('Asuransi - Asuransi Contoh Aktif', preg_replace('/\s+/', ' ', $html));
        $this->assertStringContainsString('•••••••••3-45', $html);
        $this->assertStringNotContainsString('AB123-45', $html);
    }

    // --- SEK-12 ---------------------------------------------------------------------------------

    public function test_the_bpjs_number_is_masked_on_the_page_and_encrypted_in_the_database(): void
    {
        $appointment = $this->book($this->patient(), new BookingData(PaymentType::Bpjs, self::BPJS));

        $html = $this->open($appointment);
        $row = DB::table('appointments')->where('id', $appointment->id)->first();

        $this->assertStringContainsString('BPJS Kesehatan', $html);
        $this->assertStringContainsString('•••••••••0011', $html);
        $this->assertStringNotContainsString(self::BPJS, $html);
        $this->assertSame('0011', $row->payment_reference_last4);
        $this->assertNotSame(self::BPJS, $row->payment_reference);
        $this->assertStringNotContainsString(self::BPJS, (string) $row->payment_reference);
        $this->assertSame(self::BPJS, $appointment->fresh()->payment_reference);
    }

    // --- SEK-13 ---------------------------------------------------------------------------------

    public function test_the_qr_on_the_page_decodes_to_the_booking_code_only(): void
    {
        $appointment = $this->book($this->patient(), new BookingData(PaymentType::Bpjs, self::BPJS));

        $html = $this->open($appointment);

        $this->assertSame(1, preg_match('#<img src="(data:image/svg\+xml;base64,[^"]+)" alt="Kode QR booking#', $html, $matches));
        $this->assertSame($matches[1], app(AppointmentProofService::class)->qrSvgDataUri($appointment));

        $svg = (string) base64_decode(substr($matches[1], strlen('data:image/svg+xml;base64,')), true);

        $this->assertSame($appointment->booking_code, AppointmentProofServiceTest::decodeSvg($svg));
        $this->assertSame($appointment->booking_code, app(AppointmentProofService::class)->qrPayload($appointment));
        $this->assertStringNotContainsString('Siti', $svg);
        $this->assertStringNotContainsString(self::BPJS, $svg);
    }

    // --- SEK-15 ---------------------------------------------------------------------------------

    public function test_names_complaint_and_history_reasons_are_escaped(): void
    {
        $script = '<script>alert(1)</script>';
        $user = $this->createPatient(['email' => 'siti@contoh.test'], ['full_name' => $script]);
        $this->patient = $user;
        $session = $this->bookableSession(['name' => $script], ['name' => '<b>Poli</b> Uji', 'code' => 'XSS']);
        $appointment = $this->book($user, new BookingData(PaymentType::Umum, complaint: $script), $session);
        (new AppointmentStatusHistory)->forceFill([
            'appointment_id' => $appointment->id,
            'from_status' => AppointmentStatus::Pending,
            'to_status' => AppointmentStatus::Confirmed,
            'actor_type' => ActorType::Staff,
            'reason' => '<img src=x onerror=alert(1)>',
            'created_at' => '2026-10-05 08:45:00',
        ])->save();

        $html = $this->open($appointment, $user);

        $this->assertStringNotContainsString($script, $html);
        $this->assertStringNotContainsString('<b>Poli</b>', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringContainsString('&lt;b&gt;Poli&lt;/b&gt; Uji', $html);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
    }

    // --- akses (PAS-02, Review Focus 3) -----------------------------------------------------------

    public function test_another_patient_gets_the_same_404_as_for_a_code_that_does_not_exist(): void
    {
        $owner = $this->patient();
        $appointment = $this->book($owner);
        $other = $this->createPatient(['email' => 'lain@contoh.test'], ['full_name' => 'Pasien Lain']);

        $foreign = $this->actingAs($other)->get($this->detailUrl($appointment))->assertNotFound();
        $missing = $this->actingAs($other)->get(route('patient.appointments.show', ['appointment' => 'UPH-ZZZZ-ZZZZ']))->assertNotFound();

        $normalise = fn (string $html): string => (string) preg_replace('/\b[A-Za-z0-9]{40}\b/', 'TOKEN', $html);

        $this->assertSame($normalise($missing->getContent()), $normalise($foreign->getContent()));
        $this->assertStringNotContainsString($appointment->booking_code, $foreign->getContent());
        $this->assertStringNotContainsString('Siti Rahma', $foreign->getContent());
        $this->assertStringNotContainsString('Laras', $foreign->getContent());
    }

    public function test_malformed_codes_are_404_because_the_route_does_not_match(): void
    {
        $appointment = $this->book($this->patient());
        $code = $appointment->booking_code;

        foreach ([strtolower($code), 'ABC', str_replace('-', '', $code), $code.'X', 'UPH-7K3M-9QWI', 'UPH-7K3M-9QWO', $code.'/bukti-palsu'] as $malformed) {
            $this->actingAs($this->patient())->get('/pasien/pendaftaran/'.rawurlencode($malformed))->assertNotFound();
        }
    }

    public function test_guests_go_to_the_login_and_staff_to_the_admin_dashboard(): void
    {
        $appointment = $this->book($this->patient());

        $this->get($this->detailUrl($appointment))->assertRedirect(route('login'));

        $staff = $this->createStaff('registration-officer');
        $this->actingAs($staff)->get($this->detailUrl($appointment))->assertRedirect(route('admin.dashboard'));
        $this->actingAs($staff)->getJson($this->detailUrl($appointment))->assertForbidden();
    }

    public function test_a_walk_in_appointment_without_an_account_is_not_visible_to_any_patient(): void
    {
        $this->patient();
        $walkIn = $this->bookAppointment($this->bookableSession(), AppointmentStatus::Confirmed);

        $this->actingAs($this->patient())->get($this->detailUrl($walkIn))->assertNotFound();
    }

    public function test_the_detail_is_never_cached_and_works_under_strict_lazy_loading(): void
    {
        $appointment = $this->book($this->patient());

        $response = $this->actingAs($this->patient())->get($this->detailUrl($appointment))->assertOk();

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_the_actions_anchor_keeps_the_dashboard_link(): void
    {
        $html = $this->open($this->book($this->patient()));

        $this->assertStringContainsString('data-appointment-actions', $html);
        $this->assertStringContainsString('href="'.route('patient.dashboard').'">Ke Dashboard</a>', $html);
    }
}
