<?php

namespace Tests\Feature\Admin;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentType;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\InsurancePartner;
use App\Models\PatientProfile;
use App\Models\User;
use App\Services\Booking\AppointmentProofService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithMasterData;
use Tests\Support\InteractsWithSchedules;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * Bukti pendaftaran sisi admin (F7A-R22): PAS-04 sisi Petugas (200) dan Admin Konten (403), isi tanpa data sensitif
 * (SEK-12, SEK-15), tanpa PDF, kode tidak ada atau berformat salah 404.
 */
class AdminAppointmentProofTest extends TestCase
{
    use InteractsWithMasterData;
    use InteractsWithSchedules;
    use InteractsWithStaff;
    use RefreshDatabase;

    private const BPJS = '0009900000011';

    private const PHONE = '081355550001';

    private Clinic $clinic;

    private Doctor $doctor;

    private User $officer;

    private Appointment $appointment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-06 08:00:00');
        $this->seed(SettingsSeeder::class);

        $this->clinic = $this->createClinic(['name' => 'Poli Anak', 'code' => 'ANK']);
        $this->doctor = $this->createDoctor(['name' => 'Sinta Maharani', 'title_prefix' => 'dr.', 'title_suffix' => 'Sp.A'], $this->clinic);
        $this->officer = $this->createStaff('registration-officer');
        $this->appointment = $this->appointmentFor('Siti Rahma', AppointmentStatus::Confirmed, [
            'booking_code' => 'UPH-ABCD-2345',
            'queue_number' => 5,
            'payment_type' => PaymentType::Bpjs,
            'payment_reference' => self::BPJS,
            'payment_reference_last4' => '0011',
            'complaint' => 'Nyeri dada kiri sejak semalam',
        ], ['phone' => self::PHONE, 'address' => 'Jl. Merpati No. 5, Pamulang', 'birth_date' => '1985-07-23']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @param  array<string, mixed>  $profile
     */
    private function appointmentFor(string $name, AppointmentStatus $status, array $overrides = [], array $profile = []): Appointment
    {
        $session = $this->createSession([
            'doctor_id' => $this->doctor->id,
            'clinic_id' => $this->clinic->id,
            'session_date' => '2026-10-06',
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'doctor_schedule_id' => null,
        ]);
        $owner = PatientProfile::factory()->create(['full_name' => $name] + $profile);

        return $this->bookAppointment($session, $status, array_merge(['patient_profile_id' => $owner->id], $overrides));
    }

    private function proof(?Appointment $appointment = null, ?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->officer)
            ->get(route('admin.appointments.proof', ['appointment' => ($appointment ?? $this->appointment)->booking_code]));
    }

    /** @return array<string, array{string, int}> */
    public static function roles(): array
    {
        return [
            'super admin' => ['super-admin', 200],
            'petugas pendaftaran' => ['registration-officer', 200],
            'admin konten' => ['content-admin', 403],
        ];
    }

    #[DataProvider('roles')]
    public function test_pas_04_access_per_staff_role(string $role, int $status): void
    {
        $this->proof(null, $this->createStaff($role))->assertStatus($status);
    }

    public function test_a_patient_gets_403_and_a_guest_is_sent_to_the_admin_login(): void
    {
        $this->proof(null, User::factory()->patient()->create())->assertForbidden();

        auth()->logout();
        $this->get(route('admin.appointments.proof', ['appointment' => $this->appointment->booking_code]))->assertRedirect(route('admin.login'));
    }

    public function test_the_proof_shows_the_booking_data_the_qr_and_the_print_control_but_no_pdf(): void
    {
        $service = app(AppointmentProofService::class);
        $response = $this->proof()->assertOk();

        $response->assertSee('Bukti Pendaftaran UPH-ABCD-2345 — ', false)
            ->assertSee('Bukti Pendaftaran Kunjungan')
            ->assertSee('UPH-ABCD-2345')
            ->assertSee('ANK-005')
            ->assertSee('Dikonfirmasi')
            ->assertSee('Siti Rahma')
            ->assertSee('Poli Anak')
            ->assertSee('dr. Sinta Maharani, Sp.A')
            ->assertSee('Selasa, 6 Oktober 2026')
            ->assertSee('09.00–12.00')
            ->assertSee('BPJS Kesehatan')
            ->assertSee('•••••••••0011')
            ->assertSee('Online')
            ->assertSee('Tunjukkan kode ini di loket pendaftaran.')
            ->assertSee('Dicetak pada 6 Okt 2026 08.00 WIB')
            ->assertSee('alt="Kode QR booking UPH-ABCD-2345"', false)
            ->assertSee('Cetak Bukti')
            ->assertSee('href="'.route('admin.appointments.show', ['appointment' => 'UPH-ABCD-2345']).'"', false)
            ->assertDontSee('Unduh PDF')
            ->assertDontSee('proof-pdf');

        $html = $response->getContent();

        $this->assertSame(1, substr_count($html, 'data-print'));
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('d-print-none', $html);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertSame(1, preg_match('#<img[^>]*class="[^"]*proof-qr[^"]*"[^>]*src="(data:image/svg\+xml;base64,[A-Za-z0-9+/=]+)"#', $html, $matches));
        $this->assertSame($service->qrSvgDataUri($this->appointment), $matches[1]);
        $this->assertStringNotContainsString('<svg', $html);
    }

    public function test_the_proof_never_shows_sensitive_patient_data(): void
    {
        $this->proof()
            ->assertOk()
            ->assertDontSee(self::BPJS)
            ->assertDontSee('000990000')
            ->assertDontSee('Nyeri dada')
            ->assertDontSee(self::PHONE)
            ->assertDontSee('Merpati')
            ->assertDontSee('1985');
    }

    public function test_the_policy_number_is_masked(): void
    {
        $partner = InsurancePartner::factory()->create(['name' => 'Asuransi Contoh Sehat']);
        $appointment = $this->appointmentFor('Budi Santoso', AppointmentStatus::Confirmed, [
            'payment_type' => PaymentType::Asuransi,
            'insurance_partner_id' => $partner->id,
            'payment_reference' => 'POL-778899/12',
            'payment_reference_last4' => '9/12',
        ]);

        $this->proof($appointment)
            ->assertOk()
            ->assertSee('Asuransi - Asuransi Contoh Sehat')
            ->assertSee('•••••••••9/12')
            ->assertDontSee('POL-778899');
    }

    public function test_a_cancelled_appointment_carries_the_not_valid_notice(): void
    {
        $cancelled = $this->appointmentFor('Dewi Lestari', AppointmentStatus::Cancelled);

        $this->proof($cancelled)
            ->assertOk()
            ->assertSee('Pendaftaran ini telah dibatalkan. Bukti ini tidak berlaku untuk check-in.')
            ->assertDontSee('Tunjukkan kode ini di loket pendaftaran.');
    }

    public function test_a_walk_in_patient_without_an_account_has_a_proof(): void
    {
        $profile = PatientProfile::factory()->create(['full_name' => 'Pasien Walk-in', 'user_id' => null]);
        $walkIn = $this->appointmentFor('Pasien Walk-in', AppointmentStatus::Confirmed, ['patient_profile_id' => $profile->id]);

        $this->proof($walkIn)->assertOk()->assertSee('Pasien Walk-in');
    }

    public function test_the_patient_name_is_escaped(): void
    {
        $evil = $this->appointmentFor('<script>alert(1)</script>', AppointmentStatus::Confirmed, ['complaint' => '<script>alert(2)</script>']);

        $this->proof($evil)
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('alert(2)');
    }

    public function test_unknown_malformed_and_numeric_codes_are_404(): void
    {
        $this->actingAs($this->officer);

        $this->get('/admin/pendaftaran/UPH-ZZZZ-ZZZZ/bukti')->assertNotFound();
        $this->get('/admin/pendaftaran/abc/bukti')->assertNotFound();
        $this->get('/admin/pendaftaran/'.$this->appointment->id.'/bukti')->assertNotFound();
        $this->get('/admin/pendaftaran/'.strtolower($this->appointment->booking_code).'/bukti')->assertNotFound();
        $this->get('/admin/pendaftaran/UPH-ABCD-2345/bukti.pdf')->assertNotFound();
    }
}
