<?php

namespace Tests\Feature\Patients;

use App\Enums\ActorType;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\PatientProfile;
use App\Models\User;
use App\Services\Patients\PatientAccountLinker;
use App\Services\Patients\PatientRegistrationService;
use App\Support\Actor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\Support\InteractsWithPatients;
use Tests\Support\InteractsWithSchedules;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * F7A-R12 / WLK-05, WLK-06 sisi service / BR-106..BR-109: hubungkan profil walk-in ke akun pasien. Semua penolakan 06 §16
 * (`ValidationException` kunci `account_email`), akun tanpa profil diizinkan, `problem()` tidak menulis dan tidak mengunci,
 * dan tidak ada pencocokan otomatis. Pembuktian dua petugas bersamaan ada di T11.
 */
class PatientAccountLinkerTest extends TestCase
{
    use InteractsWithPatients;
    use InteractsWithSchedules;
    use InteractsWithStaff;
    use RefreshDatabase;

    private const ALREADY_LINKED = 'Profil ini sudah terhubung ke sebuah akun.';

    private const NOT_FOUND = 'Akun pasien dengan email tersebut tidak ditemukan.';

    private const INACTIVE = 'Akun pasien tidak aktif.';

    private const HAS_HISTORY = 'Akun ini sudah memiliki riwayat pendaftaran. Penggabungan dua profil berriwayat belum didukung.';

    private User $staff;

    private Actor $actor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-09 10:15:00');
        $this->staff = $this->createStaff('registration-officer');
        $this->actor = Actor::staff($this->staff);
    }

    private function linker(): PatientAccountLinker
    {
        return $this->app->make(PatientAccountLinker::class);
    }

    private function walkIn(array $attributes = []): PatientProfile
    {
        return PatientProfile::factory()->create($attributes);
    }

    private function linkedAudits(): int
    {
        return AuditLog::query()->where('action', 'patient.account_linked')->count();
    }

    /** Memastikan penolakan: pesan pada kunci `account_email`, tanpa audit dan tanpa perubahan profil. */
    private function assertRejected(PatientProfile $walkIn, User $account, string $message): void
    {
        $profilesBefore = PatientProfile::query()->count();

        try {
            $this->linker()->link($walkIn, $account, $this->actor);
            $this->fail('Penghubungan harus ditolak.');
        } catch (ValidationException $e) {
            $this->assertSame(['account_email' => [$message]], $e->errors());
        }

        $this->assertSame(0, $this->linkedAudits());
        $this->assertSame($profilesBefore, PatientProfile::query()->count(), 'Tidak ada profil yang dihapus pada penolakan.');
    }

    // --- findAccount ---

    public function test_find_account_trims_and_lowercases_the_email(): void
    {
        $account = $this->createPatient(['email' => 'siti.rahma@contoh.test']);

        $this->assertSame($account->id, $this->linker()->findAccount('  Siti.Rahma@CONTOH.test ')?->id);
    }

    public function test_find_account_returns_null_for_unknown_blank_and_staff_emails(): void
    {
        $this->createPatient(['email' => 'siti.rahma@contoh.test']);
        $staff = $this->createStaff('content-admin', ['email' => 'konten@contoh.test']);

        $this->assertNull($this->linker()->findAccount('tidak.ada@contoh.test'));
        $this->assertNull($this->linker()->findAccount(''));
        $this->assertNull($this->linker()->findAccount('   '));
        $this->assertNull($this->linker()->findAccount($staff->email), 'Akun staf bukan akun pasien.');
    }

    public function test_find_account_returns_an_inactive_patient_so_the_reason_can_be_reported(): void
    {
        $account = $this->createPatient(['email' => 'nonaktif@contoh.test', 'is_active' => false]);

        $this->assertSame($account->id, $this->linker()->findAccount('nonaktif@contoh.test')?->id);
    }

    // --- problem() ---

    public function test_problem_is_null_when_the_link_is_allowed(): void
    {
        $this->assertNull($this->linker()->problem($this->walkIn(), $this->createPatient()));
    }

    public function test_problem_allows_an_account_without_any_profile(): void
    {
        $this->assertNull($this->linker()->problem($this->walkIn(), User::factory()->patient()->create()));
    }

    public function test_problem_reports_a_profile_that_is_already_linked_first(): void
    {
        $linked = $this->createPatient()->patientProfile;

        $this->assertSame(self::ALREADY_LINKED, $this->linker()->problem($linked, $this->createPatient()));
        $this->assertSame(self::ALREADY_LINKED, $this->linker()->problem($linked, null), 'Profil terhubung didahulukan, juga tanpa akun.');
        $this->assertSame(self::ALREADY_LINKED, $this->linker()->problem($linked, User::factory()->patient()->inactive()->create()));
    }

    public function test_problem_reports_a_missing_account_and_a_non_patient_account(): void
    {
        $this->assertSame(self::NOT_FOUND, $this->linker()->problem($this->walkIn(), null));
        $this->assertSame(self::NOT_FOUND, $this->linker()->problem($this->walkIn(), $this->createStaff('registration-officer')));
    }

    public function test_problem_reports_an_inactive_account(): void
    {
        $this->assertSame(self::INACTIVE, $this->linker()->problem($this->walkIn(), $this->createPatient(['is_active' => false])));
    }

    public function test_problem_reports_history_of_any_status_on_the_account_profile(): void
    {
        foreach ([AppointmentStatus::Pending, AppointmentStatus::Cancelled, AppointmentStatus::Completed] as $status) {
            $account = $this->createPatient();
            $this->bookAppointment($this->createSession(), $status, ['patient_profile_id' => $account->patientProfile->id]);

            $this->assertSame(self::HAS_HISTORY, $this->linker()->problem($this->walkIn(), $account), $status->value);
        }
    }

    public function test_a_walk_in_history_does_not_block_the_link(): void
    {
        $walkIn = $this->walkIn();
        $this->bookAppointment($this->createSession(), AppointmentStatus::Completed, ['patient_profile_id' => $walkIn->id]);

        $this->assertNull($this->linker()->problem($walkIn, $this->createPatient()));
    }

    public function test_problem_neither_writes_nor_locks(): void
    {
        $walkIn = $this->walkIn();
        $account = $this->createPatient();
        $statements = [];
        DB::listen(function ($query) use (&$statements): void {
            $statements[] = strtolower($query->sql);
        });

        $this->linker()->problem($walkIn, $account);
        $this->linker()->problem($walkIn, null);

        $this->assertNotEmpty($statements);

        foreach ($statements as $sql) {
            $this->assertDoesNotMatchRegularExpression('/^\s*(insert|update|delete)\b|for update/', $sql);
        }

        $this->assertSame(0, $this->linkedAudits());
    }

    // --- link(): sukses ---

    public function test_it_links_the_walk_in_profile_and_removes_the_empty_account_profile(): void
    {
        $walkIn = $this->walkIn([
            'full_name' => 'Budi Santoso',
            'birth_date' => '1985-07-21',
            'phone' => '081234567890',
            'consent_at' => '2026-10-09 09:00:00',
            'consent_version' => '1.0',
        ]);
        $account = $this->createPatient(['email' => 'budi.s@contoh.test'], ['consent_at' => '2026-09-30 08:00:00', 'consent_version' => '1.4']);
        $emptyProfileId = $account->patientProfile->id;

        $result = $this->linker()->link($walkIn, $account, $this->actor);

        $this->assertNull(PatientProfile::query()->find($emptyProfileId), 'Profil kosong milik akun dihapus.');

        $stored = PatientProfile::query()->findOrFail($walkIn->id);
        $this->assertSame($account->id, (int) $stored->user_id);
        $this->assertSame('budi.s@contoh.test', $stored->email);
        $this->assertTrue($stored->is_active);
        $this->assertSame('Budi Santoso', $stored->full_name);
        $this->assertSame('1985-07-21', $stored->birth_date->toDateString());
        $this->assertSame('081234567890', $stored->phone);
        $this->assertSame($walkIn->patient_number, $stored->patient_number);
        $this->assertSame('2026-10-09 09:00:00', $stored->consent_at->format('Y-m-d H:i:s'));
        $this->assertSame('1.0', $stored->consent_version);

        $this->assertSame($walkIn, $result);
        $this->assertSame($account->id, (int) $result->user_id);
        $this->assertSame($walkIn->id, User::query()->with('patientProfile')->findOrFail($account->id)->patientProfile->id);
    }

    public function test_the_walk_in_history_now_belongs_to_the_account_profile(): void
    {
        $walkIn = $this->walkIn();
        $this->bookAppointment($this->createSession(), AppointmentStatus::Completed, ['patient_profile_id' => $walkIn->id]);
        $account = $this->createPatient();

        $this->linker()->link($walkIn, $account, $this->actor);

        $this->assertSame(1, Appointment::query()->where('patient_profile_id', User::query()->with('patientProfile')->findOrFail($account->id)->patientProfile->id)->count());
    }

    public function test_it_audits_the_link_with_the_removed_profile_and_no_personal_data(): void
    {
        $walkIn = $this->walkIn(['full_name' => 'Budi Santoso', 'phone' => '081234567890']);
        $account = $this->createPatient(['email' => 'budi.s@contoh.test', 'name' => 'Budi Akun'], ['consent_at' => '2026-09-30 08:00:00', 'consent_version' => '1.4']);
        $removed = $account->patientProfile;

        $this->linker()->link($walkIn, $account, $this->actor);

        $this->assertSame(1, $this->linkedAudits());
        $log = AuditLog::query()->where('action', 'patient.account_linked')->sole();
        $this->assertSame(ActorType::Staff, $log->actor_type);
        $this->assertSame($this->staff->id, (int) $log->user_id);
        $this->assertSame($walkIn->getMorphClass(), $log->auditable_type);
        $this->assertSame($walkIn->id, (int) $log->auditable_id);
        $this->assertSame(['removed_profile' => [
            'id' => $removed->id,
            'patient_number' => $removed->patient_number,
            'consent_at' => '2026-09-30 08:00:00',
            'consent_version' => '1.4',
        ]], $log->old_values);
        $this->assertSame(['profile_id' => $walkIn->id, 'user_id' => $account->id], $log->new_values);
        $this->assertSame("Profil pasien {$walkIn->patient_number} dihubungkan ke akun pasien.", $log->description);

        $dump = $log->description.json_encode($log->old_values).json_encode($log->new_values);

        foreach (['Budi', 'budi.s@contoh.test', '081234567890'] as $personal) {
            $this->assertStringNotContainsString($personal, $dump);
        }
    }

    public function test_an_account_without_a_profile_is_linked_and_the_audit_records_no_removed_profile(): void
    {
        $walkIn = $this->walkIn();
        $account = User::factory()->patient()->create(['email' => 'baru@contoh.test']);

        $this->linker()->link($walkIn, $account, $this->actor);

        $stored = PatientProfile::query()->findOrFail($walkIn->id);
        $this->assertSame($account->id, (int) $stored->user_id);
        $this->assertSame('baru@contoh.test', $stored->email);

        $log = AuditLog::query()->where('action', 'patient.account_linked')->sole();
        $this->assertSame(['removed_profile' => null], $log->old_values);
        $this->assertSame(['profile_id' => $walkIn->id, 'user_id' => $account->id], $log->new_values);
    }

    public function test_the_account_row_itself_is_not_modified(): void
    {
        $walkIn = $this->walkIn(['full_name' => 'Nama Walk-in', 'phone' => '081111111111']);
        $account = $this->createPatient(['name' => 'Nama Akun', 'phone' => '082222222222']);
        $before = User::query()->findOrFail($account->id)->only(['name', 'email', 'phone', 'is_active', 'password']);

        $this->linker()->link($walkIn, $account, $this->actor);

        $this->assertSame($before, User::query()->findOrFail($account->id)->only(['name', 'email', 'phone', 'is_active', 'password']));
    }

    // --- link(): semua penolakan ---

    public function test_it_rejects_a_profile_that_is_already_linked(): void
    {
        $linked = $this->createPatient()->patientProfile;
        $other = $this->createPatient();

        $this->assertRejected($linked, $other, self::ALREADY_LINKED);
        $this->assertNotNull(PatientProfile::query()->where('user_id', $other->id)->first(), 'Profil akun tujuan tetap ada.');
    }

    public function test_it_rejects_a_stale_instance_of_a_profile_linked_in_the_meantime(): void
    {
        $walkIn = $this->walkIn();
        $account = $this->createPatient();
        $elsewhere = User::factory()->patient()->create();
        PatientProfile::query()->whereKey($walkIn->id)->update(['user_id' => $elsewhere->id]);

        $this->assertNull($walkIn->user_id, 'Instance pemanggil masih basi.');
        $this->assertRejected($walkIn, $account, self::ALREADY_LINKED);
        $this->assertSame($elsewhere->id, (int) PatientProfile::query()->findOrFail($walkIn->id)->user_id);
    }

    public function test_it_rejects_a_staff_account(): void
    {
        $this->assertRejected($this->walkIn(), $this->createStaff('registration-officer'), self::NOT_FOUND);
    }

    public function test_it_rejects_an_inactive_account(): void
    {
        $account = $this->createPatient(['is_active' => false]);

        $this->assertRejected($this->walkIn(), $account, self::INACTIVE);
        $this->assertNotNull(PatientProfile::query()->find($account->patientProfile->id));
    }

    public function test_it_rejects_an_account_whose_profile_has_history(): void
    {
        $account = $this->createPatient();
        $this->bookAppointment($this->createSession(), AppointmentStatus::Cancelled, ['patient_profile_id' => $account->patientProfile->id]);

        $this->assertRejected($this->walkIn(), $account, self::HAS_HISTORY);
        $this->assertNotNull(PatientProfile::query()->find($account->patientProfile->id), 'Profil berriwayat tidak dihapus.');
    }

    public function test_a_failed_link_leaves_the_walk_in_profile_unlinked(): void
    {
        $walkIn = $this->walkIn();
        $account = $this->createPatient(['is_active' => false]);

        $this->assertRejected($walkIn, $account, self::INACTIVE);
        $this->assertNull(PatientProfile::query()->findOrFail($walkIn->id)->user_id);
    }

    // --- atomisitas dan tanpa pencocokan otomatis ---

    public function test_an_audit_failure_rolls_back_the_deletion_and_the_link(): void
    {
        $walkIn = $this->walkIn();
        $account = $this->createPatient();
        $accountProfileId = $account->patientProfile->id;
        AuditLog::creating(function (): void {
            throw new RuntimeException('Audit gagal ditulis.');
        });

        try {
            $this->linker()->link($walkIn, $account, $this->actor);
            $this->fail('Kegagalan audit harus merambat keluar dari service.');
        } catch (RuntimeException $e) {
            $this->assertSame('Audit gagal ditulis.', $e->getMessage());
        }

        $this->assertNull(PatientProfile::query()->findOrFail($walkIn->id)->user_id);
        $this->assertNotNull(PatientProfile::query()->find($accountProfileId));
        $this->assertSame($accountProfileId, (int) PatientProfile::query()->where('user_id', $account->id)->value('id'));
    }

    /** 00 F.9, BR-109: tidak ada pencocokan otomatis berdasarkan email, telepon, atau nama; juga tidak lewat pembuatan walk-in. */
    public function test_nothing_is_linked_automatically_even_for_identical_identity_data(): void
    {
        $account = $this->createPatient(
            ['name' => 'Budi Santoso', 'email' => 'budi.s@contoh.test'],
            ['full_name' => 'Budi Santoso', 'birth_date' => '1985-07-21', 'phone' => '081234567890'],
        );
        $accountProfileId = $account->patientProfile->id;

        $walkIn = $this->app->make(PatientRegistrationService::class)->createWalkIn([
            'full_name' => 'Budi Santoso',
            'birth_date' => '1985-07-21',
            'gender' => 'L',
            'phone' => '081234567890',
            'email' => 'budi.s@contoh.test',
            'address' => 'Jl. Melati No. 7, Pamulang, Tangerang Selatan',
        ], $this->staff);
        $this->linker()->findAccount('budi.s@contoh.test');
        $this->linker()->problem($walkIn, $account);

        $this->assertNull(PatientProfile::query()->findOrFail($walkIn->id)->user_id);
        $this->assertSame($accountProfileId, (int) PatientProfile::query()->where('user_id', $account->id)->value('id'));
        $this->assertSame(0, $this->linkedAudits());
    }

    public function test_the_link_messages_are_verbatim(): void
    {
        $this->assertSame(self::ALREADY_LINKED, __('patients.link.PROFILE_ALREADY_LINKED'));
        $this->assertSame(self::NOT_FOUND, __('patients.link.ACCOUNT_NOT_FOUND'));
        $this->assertSame(self::INACTIVE, __('patients.link.ACCOUNT_INACTIVE'));
        $this->assertSame(self::HAS_HISTORY, __('patients.link.ACCOUNT_HAS_HISTORY'));
        $this->assertSame('Anda harus menegaskan bahwa identitas pasien telah diverifikasi di loket.', __('patients.link.identity_required'));
        $this->assertSame('Profil pasien P000012 berhasil dihubungkan ke akun siti@contoh.test.', __('patients.link.linked', ['number' => 'P000012', 'email' => 'siti@contoh.test']));
    }
}
