<?php

namespace Tests\Feature\Patients;

use App\Enums\ActorType;
use App\Enums\BookingChannel;
use App\Enums\Gender;
use App\Models\AuditLog;
use App\Models\PatientProfile;
use App\Models\User;
use App\Services\Patients\PatientNumberGenerator;
use App\Services\Patients\PatientRegistrationService;
use App\Services\Settings\SettingsService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * F7A-R8 / WLK-01 sisi service / BR-99, BR-101..BR-103 / SEK-11: profil walk-in dibuat petugas tanpa akun, kanal loket,
 * persetujuan lisan tercatat, nomor pasien sesudah insert, audit `patient.created` tanpa data pribadi. Validasi dan izin
 * ada di Form Request dan Policy (T4, T6); service hanya membaca enam kunci eksplisit.
 */
class WalkInProfileServiceTest extends TestCase
{
    use InteractsWithStaff;
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-09 10:15:00');
        $this->seed(SettingsSeeder::class);
        $this->staff = $this->createStaff('registration-officer');
    }

    private function service(): PatientRegistrationService
    {
        return $this->app->make(PatientRegistrationService::class);
    }

    /**
     * Data seperti keluaran Form Request: tanpa BPJS dan tanpa field persetujuan.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'full_name' => 'Budi Santoso',
            'birth_date' => '1985-07-21',
            'gender' => 'L',
            'phone' => '081234567890',
            'email' => null,
            'address' => 'Jl. Melati No. 7, Pamulang, Tangerang Selatan',
        ];
    }

    public function test_it_creates_a_walk_in_profile_without_an_account(): void
    {
        $profile = $this->service()->createWalkIn($this->payload(), $this->staff);

        $stored = PatientProfile::query()->findOrFail($profile->id);
        $this->assertSame('Budi Santoso', $stored->full_name);
        $this->assertSame('1985-07-21', $stored->birth_date->toDateString());
        $this->assertSame(Gender::Male, $stored->gender);
        $this->assertSame('081234567890', $stored->phone);
        $this->assertNull($stored->email);
        $this->assertSame('Jl. Melati No. 7, Pamulang, Tangerang Selatan', $stored->address);
        $this->assertSame(BookingChannel::Loket, $stored->registration_channel);
        $this->assertNull($stored->user_id, 'Walk-in tidak punya akun login.');
        $this->assertSame($this->staff->id, (int) $stored->created_by_user_id);
        $this->assertTrue($stored->is_active);
        $this->assertNull($stored->bpjs_number);
        $this->assertNull($stored->bpjs_number_last4);
        $this->assertSame(1, PatientProfile::query()->count());
    }

    public function test_the_patient_number_is_assigned_after_insert_in_the_p_format(): void
    {
        $first = $this->service()->createWalkIn($this->payload(), $this->staff);
        $second = $this->service()->createWalkIn($this->payload(['full_name' => 'Siti Aminah']), $this->staff);

        $this->assertSame(PatientNumberGenerator::format($first->id), $first->patient_number);
        $this->assertSame(PatientNumberGenerator::format($second->id), $second->patient_number);
        $this->assertMatchesRegularExpression('/^P\d{6,}$/', $first->patient_number);
        $this->assertNotSame($first->patient_number, $second->patient_number);
        $this->assertSame($first->patient_number, PatientProfile::query()->findOrFail($first->id)->patient_number);
    }

    public function test_the_verbal_consent_is_recorded_with_the_current_time_and_version(): void
    {
        $profile = $this->service()->createWalkIn($this->payload(), $this->staff);

        $this->assertSame('2026-10-09 10:15:00', $profile->consent_at->format('Y-m-d H:i:s'));
        $this->assertSame('1.0', $profile->consent_version);
    }

    public function test_the_consent_version_follows_the_privacy_setting(): void
    {
        $this->app->make(SettingsService::class)->set('privacy.consent_version', '2.3');

        $profile = $this->service()->createWalkIn($this->payload(), $this->staff);

        $this->assertSame('2.3', PatientProfile::query()->findOrFail($profile->id)->consent_version);
    }

    public function test_it_normalises_text_phone_and_email(): void
    {
        $profile = $this->service()->createWalkIn($this->payload([
            'full_name' => '  Budi Santoso ',
            'phone' => '0812-3456-7890',
            'email' => '  Budi.S@Contoh.TEST ',
            'address' => '  Jl. Melati No. 7, Pamulang  ',
        ]), $this->staff);

        $this->assertSame('Budi Santoso', $profile->full_name);
        $this->assertSame('081234567890', $profile->phone);
        $this->assertSame('budi.s@contoh.test', $profile->email);
        $this->assertSame('Jl. Melati No. 7, Pamulang', $profile->address);
    }

    public function test_the_international_phone_format_is_kept_without_punctuation(): void
    {
        $profile = $this->service()->createWalkIn($this->payload(['phone' => '+62 812-3456-7890']), $this->staff);

        $this->assertSame('+6281234567890', $profile->phone);
    }

    public function test_a_blank_or_missing_email_is_stored_as_null(): void
    {
        $blank = $this->service()->createWalkIn($this->payload(['email' => '   ']), $this->staff);
        $missing = $this->service()->createWalkIn(array_diff_key($this->payload(), ['email' => true]), $this->staff);

        $this->assertNull($blank->email);
        $this->assertNull($missing->email);
    }

    public function test_the_gender_may_be_given_as_a_value_or_an_enum(): void
    {
        $fromValue = $this->service()->createWalkIn($this->payload(['gender' => 'P']), $this->staff);
        $fromEnum = $this->service()->createWalkIn($this->payload(['gender' => Gender::Female]), $this->staff);

        $this->assertSame(Gender::Female, $fromValue->gender);
        $this->assertSame(Gender::Female, $fromEnum->gender);
    }

    public function test_keys_outside_the_six_profile_fields_are_ignored(): void
    {
        $other = User::factory()->patient()->create();

        $profile = $this->service()->createWalkIn($this->payload([
            'bpjs_number' => '0001234567890',
            'user_id' => $other->id,
            'created_by_user_id' => $other->id,
            'is_active' => false,
            'registration_channel' => 'online',
            'patient_number' => 'P999999',
            'consent_version' => '9.9',
            'consent_at' => '2020-01-01 00:00:00',
        ]), $this->staff);

        $stored = PatientProfile::query()->findOrFail($profile->id);
        $this->assertNull($stored->bpjs_number, 'V-13: nomor BPJS tidak diisi di profil walk-in.');
        $this->assertNull($stored->bpjs_number_last4);
        $this->assertNull($stored->user_id);
        $this->assertSame($this->staff->id, (int) $stored->created_by_user_id);
        $this->assertTrue($stored->is_active);
        $this->assertSame(BookingChannel::Loket, $stored->registration_channel);
        $this->assertSame(PatientNumberGenerator::format($stored->id), $stored->patient_number);
        $this->assertSame('1.0', $stored->consent_version);
        $this->assertSame('2026-10-09 10:15:00', $stored->consent_at->format('Y-m-d H:i:s'));
    }

    public function test_it_writes_exactly_one_patient_created_audit_row(): void
    {
        $profile = $this->service()->createWalkIn($this->payload(), $this->staff);

        $this->assertSame(1, AuditLog::query()->where('action', 'like', 'patient.%')->count());

        $log = AuditLog::query()->where('action', 'patient.created')->sole();
        $this->assertSame(ActorType::Staff, $log->actor_type);
        $this->assertSame($this->staff->id, (int) $log->user_id);
        $this->assertSame($profile->getMorphClass(), $log->auditable_type);
        $this->assertSame($profile->id, (int) $log->auditable_id);
        $this->assertNull($log->old_values);
        $this->assertSame([
            'patient_number' => $profile->patient_number,
            'registration_channel' => 'loket',
            'consent_version' => '1.0',
        ], $log->new_values);
        $this->assertSame("Profil pasien {$profile->patient_number} dibuat oleh petugas (walk-in).", $log->description);
    }

    /** SEK-11: tidak ada data pribadi di baris audit. */
    public function test_the_audit_row_carries_no_personal_data(): void
    {
        $this->service()->createWalkIn($this->payload([
            'email' => 'budi.s@contoh.test',
            'phone' => '0812-3456-7890',
        ]), $this->staff);

        $log = AuditLog::query()->where('action', 'patient.created')->sole();
        $dump = $log->description.json_encode($log->new_values).json_encode($log->old_values);

        foreach (['Budi', 'Santoso', '081234567890', '0812-3456-7890', 'budi.s@contoh.test', 'Melati', 'Pamulang', '1985-07-21', '21/07/1985'] as $personal) {
            $this->assertStringNotContainsString($personal, $dump, "Data pribadi bocor ke audit: {$personal}");
        }
    }

    public function test_an_audit_failure_rolls_back_the_profile(): void
    {
        AuditLog::creating(function (): void {
            throw new RuntimeException('Audit gagal ditulis.');
        });

        try {
            $this->service()->createWalkIn($this->payload(), $this->staff);
            $this->fail('Kegagalan audit harus merambat keluar dari service.');
        } catch (RuntimeException $e) {
            $this->assertSame('Audit gagal ditulis.', $e->getMessage());
        }

        $this->assertSame(0, PatientProfile::query()->count());
    }

    public function test_an_unsaved_staff_account_is_rejected_before_anything_is_written(): void
    {
        try {
            $this->service()->createWalkIn($this->payload(), User::factory()->staff()->make());
            $this->fail('Akun petugas yang belum tersimpan harus ditolak.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('Pelaku harus berupa akun yang sudah tersimpan.', $e->getMessage());
        }

        $this->assertSame(0, PatientProfile::query()->count());
    }

    public function test_the_walk_in_messages_are_verbatim(): void
    {
        $this->assertSame('Profil pasien P000012 berhasil dibuat.', __('patients.walk_in.created', ['number' => 'P000012']));
        $this->assertSame('Profil pasien P000012 diperbarui.', __('patients.walk_in.updated', ['number' => 'P000012']));
        $this->assertSame('Terdapat profil yang mirip. Pilih profil yang sudah ada atau centang konfirmasi untuk tetap membuat profil baru.', __('patients.walk_in.similar'));
        $this->assertSame('Persetujuan privasi lisan dari pasien wajib dicentang.', __('patients.walk_in.consent_required'));
    }
}
