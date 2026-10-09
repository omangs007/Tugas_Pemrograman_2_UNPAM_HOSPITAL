<?php

namespace Tests\Feature\Security;

use App\Enums\AppointmentStatus;
use App\Enums\BookingChannel;
use App\Enums\PaymentType;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\PatientProfile;
use App\Models\PracticeSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithContent;
use Tests\Support\InteractsWithPatients;
use Tests\Support\InteractsWithSchedules;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * SEK-15, SEK-11, SEK-12 dan Review Focus E.4 untuk SELURUH layar admin fase 7a dengan SATU fixture: profil dan pasien berisi
 * `<script>alert(1)</script>`, nomor BPJS, polis, dan keluhan uji. Setiap halaman GET 7a dibuka sebagai Petugas dan harus
 * (a) tidak memuat payload mentah, (b) meng-escape nama bila ditampilkan, (c) tidak memuat nomor BPJS/polis lengkap,
 * (d) berstatus `no-store`, dan (e) tanpa atribut event inline. Lalu alur tulis lewat layar (profil, pendaftaran walk-in,
 * konfirmasi, hubungkan akun, status, tandai tidak hadir) tidak boleh meninggalkan nomor lengkap, keluhan, nama, atau telepon
 * di `audit_logs`, flash/sesi, atau `notifications`. Tes per layar (T5 sampai T10) tidak diulang; ini sapuan lintas layar.
 */
class Phase7aOutputSweepTest extends TestCase
{
    use InteractsWithContent;
    use InteractsWithPatients;
    use InteractsWithSchedules;
    use InteractsWithStaff;
    use RefreshDatabase;

    private const HOSTILE = '<script>alert(1)</script>';

    private const ESCAPED = '&lt;script&gt;alert(1)&lt;/script&gt;';

    private const BPJS = '0009900000044';

    private const BPJS_TYPED = '0009900000088';

    private const BPJS_ACCOUNT = '0009900000099';

    private const POLICY = 'POLIS-RAHASIA-98765432';

    private const COMPLAINT_MARK = 'KELUHAN-RAHASIA-SAPUAN';

    private const MORNING = '2026-10-05 07:00:00';

    private const AFTERNOON = '2026-10-05 13:00:00';

    private User $officer;

    private User $account;

    private PatientProfile $walkIn;

    private PracticeSession $session;

    private Appointment $bpjsAppointment;

    private Appointment $insuranceAppointment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(self::MORNING);

        $this->officer = $this->createStaff('registration-officer');
        $this->session = $this->createSession(['session_date' => '2026-10-05', 'start_time' => '09:00:00', 'end_time' => '12:00:00']);

        // Data lama dengan nama berbahaya (PersonName menolaknya di layar, jadi ditanam lewat factory).
        $this->walkIn = PatientProfile::factory()->withBpjs(self::BPJS)->create([
            'full_name' => self::HOSTILE,
            'address' => 'Jl. '.self::HOSTILE.' No. 9, Pamulang',
            'phone' => '081200000099',
            'email' => null,
        ]);

        $this->bpjsAppointment = $this->bookAppointment($this->session, AppointmentStatus::Confirmed, [
            'patient_profile_id' => $this->walkIn->id,
            'channel' => BookingChannel::Loket,
            'payment_type' => PaymentType::Bpjs,
            'payment_reference' => self::BPJS,
            'payment_reference_last4' => '0044',
            'complaint' => self::COMPLAINT_MARK.' '.self::HOSTILE,
        ]);

        $this->insuranceAppointment = $this->bookAppointment($this->session, AppointmentStatus::Pending, [
            'patient_profile_id' => PatientProfile::factory()->create(['full_name' => 'Asuransi '.self::HOSTILE])->id,
            'payment_type' => PaymentType::Asuransi,
            'insurance_partner_id' => $this->createInsurancePartner()->id,
            'payment_reference' => self::POLICY,
            'payment_reference_last4' => '5432',
        ]);

        // Akun online tanpa riwayat (kandidat hubungkan akun) dengan nama berbahaya.
        $this->account = $this->createPatient(['name' => 'Akun '.self::HOSTILE, 'email' => 'akun.sapuan@contoh.test']);
    }

    /** @return array<string, array{string}> */
    public static function pages(): array
    {
        $keys = [
            'pasien: daftar', 'pasien: pencarian nama', 'pasien: pencarian berbahaya', 'pasien: detail',
            'pasien: panel hubungkan akun', 'pasien: panel dengan email berbahaya', 'pasien: ubah', 'pasien: tambah dengan kembali berbahaya',
            'pendaftaran: daftar', 'pendaftaran: pencarian nama', 'pendaftaran: pencarian berbahaya', 'pendaftaran: detail',
            'pendaftaran: detail dengan kembali berbahaya', 'pendaftaran: bukti',
            'walk-in: pasien terpilih', 'walk-in: pasien dan sesi terpilih', 'walk-in: pencarian nama', 'walk-in: pencarian berbahaya',
            'antrean: sesi', 'antrean: kode', 'antrean: kode berbahaya', 'antrean: sesudah sesi berakhir',
        ];

        return array_combine($keys, array_map(fn (string $key): array => [$key], $keys));
    }

    /**
     * URL, apakah nama berbahaya HARUS tampil ter-escape, dan waktu bekunya.
     *
     * @return array{string, bool, string}
     */
    private function target(string $key): array
    {
        $code = $this->bpjsAppointment->booking_code;
        $day = ['tanggal' => '2026-10-05'];
        $range = ['dari' => '2026-10-05', 'sampai' => '2026-10-05'];

        return match ($key) {
            'pasien: daftar' => [route('admin.patients.index'), true, self::MORNING],
            'pasien: pencarian nama' => [route('admin.patients.index', ['q' => 'script']), true, self::MORNING],
            'pasien: pencarian berbahaya' => [route('admin.patients.index', ['q' => self::HOSTILE]), false, self::MORNING],
            'pasien: detail' => [route('admin.patients.show', $this->walkIn), true, self::MORNING],
            'pasien: panel hubungkan akun' => [route('admin.patients.show', ['patient' => $this->walkIn, 'hubungkan' => $this->account->email]), true, self::MORNING],
            'pasien: panel dengan email berbahaya' => [route('admin.patients.show', ['patient' => $this->walkIn, 'hubungkan' => self::HOSTILE]), true, self::MORNING],
            'pasien: ubah' => [route('admin.patients.edit', $this->walkIn), true, self::MORNING],
            'pasien: tambah dengan kembali berbahaya' => [route('admin.patients.create', ['kembali' => '"><script>alert(1)</script>']), false, self::MORNING],
            'pendaftaran: daftar' => [route('admin.appointments.index', $range), true, self::MORNING],
            'pendaftaran: pencarian nama' => [route('admin.appointments.index', $range + ['q' => 'script']), true, self::MORNING],
            'pendaftaran: pencarian berbahaya' => [route('admin.appointments.index', $range + ['q' => self::HOSTILE]), false, self::MORNING],
            'pendaftaran: detail' => [route('admin.appointments.show', ['appointment' => $code]), true, self::MORNING],
            'pendaftaran: detail dengan kembali berbahaya' => [route('admin.appointments.show', ['appointment' => $code, 'kembali' => '//evil.test"><script>alert(1)</script>']), true, self::MORNING],
            'pendaftaran: bukti' => [route('admin.appointments.proof', ['appointment' => $code]), true, self::MORNING],
            'walk-in: pasien terpilih' => [route('admin.appointments.create', ['pasien' => $this->walkIn->id]), true, self::MORNING],
            'walk-in: pasien dan sesi terpilih' => [route('admin.appointments.create', ['pasien' => $this->walkIn->id, 'sesi' => $this->session->id]), true, self::MORNING],
            'walk-in: pencarian nama' => [route('admin.appointments.create', ['cari' => 'script']), true, self::MORNING],
            'walk-in: pencarian berbahaya' => [route('admin.appointments.create', ['cari' => self::HOSTILE]), false, self::MORNING],
            'antrean: sesi' => [route('admin.queue.index', $day + ['sesi' => $this->session->id]), true, self::MORNING],
            'antrean: kode' => [route('admin.queue.index', $day + ['kode' => $code]), true, self::MORNING],
            'antrean: kode berbahaya' => [route('admin.queue.index', $day + ['kode' => self::HOSTILE]), false, self::MORNING],
            'antrean: sesudah sesi berakhir' => [route('admin.queue.index', $day + ['sesi' => $this->session->id]), true, self::AFTERNOON],
        };
    }

    #[DataProvider('pages')]
    public function test_every_phase_7a_page_escapes_hostile_values_and_never_shows_full_numbers(string $key): void
    {
        [$url, $expectsName, $time] = $this->target($key);

        $this->travelTo($time);

        $response = $this->actingAs($this->officer)->get($url)->assertOk();
        $html = (string) $response->getContent();

        $this->assertStringNotContainsString('<script>alert(1)', $html, "{$key}: payload mentah di HTML.");

        if ($expectsName) {
            $this->assertStringContainsString(self::ESCAPED, $html, "{$key}: nama berbahaya harus tampil ter-escape.");
        }

        foreach ([self::BPJS, '000990000', self::POLICY, 'POLIS-RAHASIA'] as $secret) {
            $this->assertStringNotContainsString($secret, $html, "{$key}: nomor lengkap {$secret} tampil.");
        }

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'), "{$key}: halaman data pribadi harus no-store.");
        $this->assertSame(0, preg_match('/\son[a-z]{3,}\s*=\s*["\']/i', $html), "{$key}: atribut event inline.");
    }

    /** SEK-12: nomor tampil hanya sebagai `•••••••••` + 4 digit terakhir di detail pasien, detail pendaftaran, dan bukti admin. */
    public function test_numbers_are_masked_to_the_last_four_digits_on_the_detail_pages(): void
    {
        $this->actingAs($this->officer);

        $this->get(route('admin.patients.show', $this->walkIn))->assertOk()->assertSee('•••••••••0044', false);
        $this->get(route('admin.appointments.show', ['appointment' => $this->bpjsAppointment->booking_code]))->assertOk()->assertSee('•••••••••0044', false);

        foreach (['admin.appointments.show', 'admin.appointments.proof'] as $name) {
            $this->get(route($name, ['appointment' => $this->insuranceAppointment->booking_code]))->assertOk()->assertSee('•••••••••5432', false);
        }
    }

    /**
     * SEK-11 dan Review Focus E.4: alur tulis lewat layar (profil baru, pendaftaran BPJS walk-in dengan keluhan dan simpan nomor,
     * konfirmasi, hubungkan akun, nonaktifkan, tandai tidak hadir) tidak meninggalkan nomor lengkap, keluhan, nama, telepon, atau
     * email akun di audit, flash/sesi, atau notifikasi.
     */
    public function test_audit_rows_flash_data_and_notifications_written_by_the_screens_hold_no_sensitive_values(): void
    {
        $this->actingAs($this->officer);

        // 1. Profil walk-in baru (patient.created); alamat memuat payload.
        $this->post(route('admin.patients.store'), [
            'full_name' => 'Sapuan Uji',
            'birth_date' => '1990-03-12',
            'gender' => 'P',
            'phone' => '0812-0000-0077',
            'email' => '',
            'address' => 'Jl. '.self::HOSTILE.' No. 7, Pamulang',
            'verbal_consent' => '1',
        ])->assertSessionHasNoErrors();

        $created = PatientProfile::query()->where('full_name', 'Sapuan Uji')->sole();

        // 2. Pendaftaran walk-in BPJS dengan keluhan; nomor disimpan ke profil (appointment.created dan patient.updated).
        $this->post(route('admin.appointments.store'), [
            'patient_profile_id' => (string) $created->id,
            'practice_session_id' => (string) $this->session->id,
            'payment_type' => 'bpjs',
            'bpjs_number' => self::BPJS_TYPED,
            'save_bpjs' => '1',
            'complaint' => self::COMPLAINT_MARK.' '.self::HOSTILE,
        ])->assertSessionHasNoErrors();

        $this->assertStringNotContainsString(self::BPJS_TYPED, json_encode(session()->all(), JSON_THROW_ON_ERROR), 'Nomor BPJS tersimpan di flash/sesi.');

        // 3. Konfirmasi pendaftaran pasien berakun (appointment.status_changed dan notifikasi database).
        $owner = $this->createPatient();
        $pending = $this->bookAppointment($this->session, AppointmentStatus::Pending, [
            'patient_profile_id' => $owner->patientProfile->id,
            'payment_type' => PaymentType::Bpjs,
            'payment_reference' => self::BPJS_ACCOUNT,
            'payment_reference_last4' => '0099',
            'complaint' => self::COMPLAINT_MARK.' '.self::HOSTILE,
        ]);

        $this->post(route('admin.appointments.transition', ['appointment' => $pending->booking_code]), ['to' => 'confirmed', 'expected_status' => 'pending'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $owner->notifications()->count(), 'Pasien berakun harus menerima satu notifikasi.');

        // 4. Hubungkan profil walk-in ke akun online tanpa riwayat (patient.account_linked).
        $this->post(route('admin.patients.link-account', $created), ['account_email' => $this->account->email, 'identity_verified' => '1'])
            ->assertSessionHasNoErrors();

        $this->assertSame($this->account->id, $created->fresh()->user_id);

        // 5. Nonaktifkan profil tanpa pendaftaran aktif (patient.status_changed).
        $spare = PatientProfile::factory()->create();

        $this->patch(route('admin.patients.status', $spare), ['is_active' => '0', 'reason' => 'Data ganda terdeteksi saat verifikasi'])
            ->assertSessionHasNoErrors();

        // 6. Tandai tidak hadir sesudah sesi berakhir.
        $this->travelTo(self::AFTERNOON);

        $this->post(route('admin.queue.mark-no-show', ['session' => $this->session->id]), ['confirm' => '1'])->assertSessionHasNoErrors();

        $this->assertSame(4, Appointment::query()->where('status', AppointmentStatus::NoShow)->count());

        $actions = AuditLog::query()->pluck('action')->all();

        foreach (['patient.created', 'appointment.created', 'patient.updated', 'appointment.status_changed', 'patient.account_linked', 'patient.status_changed'] as $action) {
            $this->assertContains($action, $actions, "Audit {$action} tidak tercatat (tes ini tidak boleh kosong).");
        }

        $forbidden = [
            'alert(1)', self::COMPLAINT_MARK, self::BPJS, self::BPJS_TYPED, self::BPJS_ACCOUNT,
            '0812-0000-0077', '081200000077', 'Sapuan Uji', $this->account->email,
        ];

        $audit = json_encode(DB::table('audit_logs')->get()->all(), JSON_THROW_ON_ERROR);
        $notifications = json_encode(DB::table('notifications')->pluck('data')->all(), JSON_THROW_ON_ERROR);

        $this->assertNotSame('[]', $notifications);

        foreach ($forbidden as $fragment) {
            $this->assertStringNotContainsString($fragment, $audit, "audit_logs memuat {$fragment}.");
            $this->assertStringNotContainsString($fragment, $notifications, "notifications memuat {$fragment}.");
        }
    }
}
