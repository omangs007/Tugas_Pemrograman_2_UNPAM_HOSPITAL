<?php

namespace Tests\Feature\Patient;

use App\Enums\AccountType;
use App\Enums\ActorType;
use App\Enums\BookingChannel;
use App\Enums\Gender;
use App\Models\AuditLog;
use App\Models\PatientProfile;
use App\Models\User;
use App\Notifications\PatientWelcomeNotification;
use App\Services\Audit\AuditLogger;
use App\Services\Patients\PatientNumberGenerator;
use App\Services\Patients\PatientRegistrationService;
use App\Services\Settings\SettingsService;
use App\Support\Actor;
use Database\Seeders\SettingsSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\InteractsWithPatients;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * F5-R9 (register), F5-R20 (updateProfile), F5-R24 (audit). Transaksi, atomisitas, mass-assignment, dan
 * kegagalan kirim email (Review Focus G.3 dan G.5; SEK-01 sisi service; NTF-08; WLK-06).
 */
class PatientRegistrationServiceTest extends TestCase
{
    use InteractsWithPatients;
    use InteractsWithStaff;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-08 09:30:00');
        app()->setLocale('id');

        // Route `login` baru dibuat T3; sebelum itu dipasang route pengganti untuk tombol email selamat datang.
        if (! Route::has('login')) {
            Route::middleware('web')->get('/_uji/masuk', fn () => 'masuk')->name('login');
            Route::getRoutes()->refreshNameLookups();
        }
    }

    private function service(): PatientRegistrationService
    {
        return app(PatientRegistrationService::class);
    }

    /**
     * Data registrasi seperti keluaran RegisterRequest::validated(): telepon sudah dinormalisasi.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return $overrides + $this->registrationPayload(['phone' => '081234567890']);
    }

    private function patientToUpdate(): User
    {
        return $this->createPatient(
            ['name' => 'Siti Rahma', 'email' => 'siti@contoh.test'],
            [
                'full_name' => 'Siti Rahma',
                'birth_date' => '1990-03-12',
                'gender' => 'P',
                'phone' => '081234567890',
                'address' => 'Jl. Merpati No. 5, Pamulang',
            ],
        );
    }

    /**
     * Enam kolom profil yang sama dengan nilai saat ini, ditimpa $overrides.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function samePayload(PatientProfile $profile, array $overrides = []): array
    {
        return $overrides + [
            'full_name' => $profile->full_name,
            'birth_date' => $profile->birth_date->format('Y-m-d'),
            'gender' => $profile->gender->value,
            'phone' => $profile->phone,
            'email' => $profile->email,
            'address' => $profile->address,
        ];
    }

    // --- register: data dan transaksi ---

    public function test_register_creates_the_account_and_profile_in_one_transaction(): void
    {
        $user = $this->service()->register($this->payload());

        $account = User::query()->findOrFail($user->id);
        $this->assertSame('Siti Rahma', $account->name);
        $this->assertSame('siti@contoh.test', $account->email);
        $this->assertSame('081234567890', $account->phone);
        $this->assertSame(AccountType::Patient, $account->account_type);
        $this->assertTrue($account->is_active);
        $this->assertFalse($account->must_change_password);
        $this->assertNull($account->email_verified_at, 'Tanpa verifikasi email wajib (K-22 = a).');
        $this->assertNull($account->last_login_at);

        $profile = PatientProfile::query()->where('user_id', $account->id)->sole();
        $this->assertSame(PatientNumberGenerator::format($profile->id), $profile->patient_number);
        $this->assertMatchesRegularExpression('/^P\d{6,}$/', $profile->patient_number);
        $this->assertSame('Siti Rahma', $profile->full_name);
        $this->assertSame('1990-03-12', $profile->birth_date->toDateString());
        $this->assertSame(Gender::Female, $profile->gender);
        $this->assertSame('081234567890', $profile->phone);
        $this->assertSame('siti@contoh.test', $profile->email);
        $this->assertSame('Jl. Merpati No. 5, Pamulang', $profile->address);
        $this->assertSame(BookingChannel::Online, $profile->registration_channel);
        $this->assertNull($profile->created_by_user_id);
        $this->assertSame('2026-10-08 09:30:00', $profile->consent_at->format('Y-m-d H:i:s'));
        $this->assertSame('1.0', $profile->consent_version);
        $this->assertTrue($profile->is_active);
        $this->assertNull($profile->bpjs_number);
        $this->assertNull($profile->bpjs_number_last4);

        $this->assertTrue($user->relationLoaded('patientProfile'));
        $this->assertSame($profile->id, $user->patientProfile->id);
        $this->assertSame(1, User::query()->count());
        $this->assertSame(1, PatientProfile::query()->count());
    }

    /** SEK-01 (sisi service): hash bcrypt, satu kali hash, password mentah tidak tersimpan. */
    public function test_register_hashes_the_password_once(): void
    {
        $user = $this->service()->register($this->payload(['password' => 'RahasiaUji1', 'password_confirmation' => 'RahasiaUji1']));

        $row = DB::table('users')->where('id', $user->id)->first();
        $raw = (string) $row->password;

        $this->assertStringStartsWith('$2y$', $raw);
        $this->assertNotSame('RahasiaUji1', $raw);
        $this->assertSame('bcrypt', Hash::info($raw)['algoName']);
        $this->assertTrue(Hash::check('RahasiaUji1', $raw), 'Hash tunggal: password asli lolos Hash::check.');
        $this->assertTrue(Hash::check('RahasiaUji1', $user->password));
        $this->assertStringNotContainsString('RahasiaUji1', (string) json_encode($row));
    }

    public function test_consent_version_follows_the_privacy_setting(): void
    {
        $this->seed(SettingsSeeder::class);
        $settings = app(SettingsService::class);
        $settings->forget();
        $settings->set('privacy.consent_version', '2.3');

        $user = $this->service()->register($this->payload());

        $this->assertSame('2.3', PatientProfile::query()->where('user_id', $user->id)->sole()->consent_version);
    }

    /** PAS-05 / mass-assignment (sisi register): kunci liar pada $validated tidak berpengaruh. */
    public function test_register_ignores_keys_outside_the_whitelist(): void
    {
        $staff = $this->createStaff('registration-officer');

        $user = $this->service()->register($this->payload([
            'account_type' => 'staff',
            'is_active' => false,
            'must_change_password' => true,
            'email_verified_at' => '2000-01-01 00:00:00',
            'last_login_at' => '2000-01-01 00:00:00',
            'user_id' => 12345,
            'patient_number' => 'P999999',
            'registration_channel' => 'loket',
            'created_by_user_id' => $staff->id,
            'bpjs_number' => '0009900000099',
            'bpjs_number_last4' => '9999',
            'consent_at' => '2000-01-01 00:00:00',
            'consent_version' => '9.9',
        ]));

        $account = User::query()->findOrFail($user->id);
        $this->assertSame(AccountType::Patient, $account->account_type);
        $this->assertTrue($account->is_active);
        $this->assertFalse($account->must_change_password);
        $this->assertNull($account->email_verified_at);
        $this->assertNull($account->last_login_at);
        $this->assertSame([], $account->getRoleNames()->all());

        $profile = PatientProfile::query()->where('user_id', $account->id)->sole();
        $this->assertSame($account->id, $profile->user_id);
        $this->assertSame(PatientNumberGenerator::format($profile->id), $profile->patient_number);
        $this->assertSame(BookingChannel::Online, $profile->registration_channel);
        $this->assertNull($profile->created_by_user_id);
        $this->assertNull($profile->bpjs_number);
        $this->assertNull($profile->bpjs_number_last4);
        $this->assertSame('2026-10-08 09:30:00', $profile->consent_at->format('Y-m-d H:i:s'));
        $this->assertSame('1.0', $profile->consent_version);
    }

    /** WLK-06: tidak ada pencocokan otomatis dengan profil walk-in yang kebetulan sama. */
    public function test_register_does_not_link_a_matching_walk_in_profile(): void
    {
        $walkIn = PatientProfile::factory()->create([
            'full_name' => 'Siti Rahma',
            'birth_date' => '1990-03-12',
            'phone' => '081234567890',
        ]);

        $user = $this->service()->register($this->payload());

        $this->assertSame(2, PatientProfile::query()->count());
        $this->assertNull($walkIn->fresh()->user_id);
        $this->assertNotSame($walkIn->id, $user->patientProfile->id);
    }

    // --- register: audit (F5-R24) ---

    public function test_register_writes_one_audit_row_with_exact_values(): void
    {
        $user = $this->service()->register($this->payload());
        $profile = PatientProfile::query()->where('user_id', $user->id)->sole();

        $log = AuditLog::query()->where('action', 'patient.registered')->sole();

        $this->assertSame(1, AuditLog::query()->count());
        $this->assertSame(ActorType::Patient, $log->actor_type);
        $this->assertSame($user->id, (int) $log->user_id);
        $this->assertSame($profile->getMorphClass(), $log->auditable_type);
        $this->assertSame($profile->id, (int) $log->auditable_id);
        $this->assertSame("Pasien {$profile->patient_number} mendaftar akun secara online.", $log->description);
        $this->assertNull($log->old_values);
        $this->assertEquals([
            'patient_number' => $profile->patient_number,
            'registration_channel' => 'online',
            'consent_version' => '1.0',
        ], $log->new_values);
    }

    public function test_the_registration_audit_row_carries_no_personal_data(): void
    {
        $this->service()->register($this->payload());

        $log = AuditLog::query()->where('action', 'patient.registered')->sole();
        $dump = (string) json_encode([$log->description, $log->old_values, $log->new_values]);

        foreach (['Siti Rahma', 'siti@contoh.test', '081234567890', 'Merpati', '1990-03-12', 'Rahasia123', 'password'] as $fragment) {
            $this->assertStringNotContainsString($fragment, $dump, "Audit memuat: {$fragment}");
        }
    }

    // --- register: notifikasi ---

    public function test_the_welcome_notification_is_sent_once_by_mail_to_the_new_user(): void
    {
        Notification::fake();

        $user = $this->service()->register($this->payload());

        Notification::assertSentToTimes($user, PatientWelcomeNotification::class, 1);
        Notification::assertSentTimes(PatientWelcomeNotification::class, 1);
        Notification::assertSentTo(
            $user,
            PatientWelcomeNotification::class,
            fn (PatientWelcomeNotification $notification, array $channels): bool => $channels === ['mail'],
        );
    }

    public function test_the_welcome_notification_is_sent_after_the_transaction_commits(): void
    {
        $baseLevel = DB::transactionLevel();
        $levels = [];
        Event::listen(NotificationSending::class, function () use (&$levels): void {
            $levels[] = DB::transactionLevel();
        });

        $user = $this->service()->register($this->payload());

        $this->assertSame([$baseLevel], $levels, 'Notifikasi dikirim tepat satu kali, di luar transaksi registrasi.');
        $messages = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $this->assertSame($user->email, $messages->first()->getOriginalMessage()->getTo()[0]->getAddress());
    }

    /**
     * NTF-08 / 06 §18: kegagalan kirim email tidak membatalkan registrasi dan dilaporkan.
     * Kegagalan dipaksa lewat listener NotificationSending yang melempar (jalur kirim sungguhan).
     */
    public function test_a_notification_failure_is_reported_and_does_not_roll_back_the_registration(): void
    {
        Event::listen(NotificationSending::class, function (): void {
            throw new RuntimeException('Surel gagal dikirim.');
        });
        Exceptions::fake();

        $user = $this->service()->register($this->payload());

        $this->assertSame(1, User::query()->where('email', 'siti@contoh.test')->count());
        $this->assertSame(1, PatientProfile::query()->where('user_id', $user->id)->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'patient.registered')->count());
        $this->assertNotNull($user->patientProfile->patient_number);
        $this->assertCount(0, app('mailer')->getSymfonyTransport()->messages());

        Exceptions::assertReportedCount(1);
        Exceptions::assertReported(RuntimeException::class);
        Exceptions::assertReported(fn (RuntimeException $e): bool => $e->getMessage() === 'Surel gagal dikirim.');
    }

    // --- register: atomisitas (Review Focus G.5) ---

    public function test_a_profile_failure_rolls_back_the_account_the_audit_and_the_notification(): void
    {
        Notification::fake();
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        PatientProfile::creating(function (): void {
            throw new RuntimeException('Profil gagal dibuat.');
        });

        try {
            $this->service()->register($this->payload());
            $this->fail('Pembuatan profil yang gagal harus merambat keluar dari service.');
        } catch (RuntimeException $e) {
            $this->assertSame('Profil gagal dibuat.', $e->getMessage());
        }

        $this->assertNotEmpty(
            array_filter($queries, fn (string $sql): bool => str_starts_with($sql, 'insert into `users`')),
            'Baris akun sempat ditulis di dalam transaksi sebelum dibatalkan.',
        );
        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, PatientProfile::query()->count());
        $this->assertSame(0, AuditLog::query()->count());
        Notification::assertNothingSent();
    }

    public function test_an_audit_failure_rolls_back_the_account_and_the_profile(): void
    {
        Notification::fake();
        AuditLog::creating(function (): void {
            throw new RuntimeException('Audit gagal ditulis.');
        });

        try {
            $this->service()->register($this->payload());
            $this->fail('Kegagalan audit harus merambat keluar dari service.');
        } catch (RuntimeException $e) {
            $this->assertSame('Audit gagal ditulis.', $e->getMessage());
        }

        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, PatientProfile::query()->count());
        Notification::assertNothingSent();
    }

    /** Review Focus G.5: email ganda beda huruf besar ditolak DB (kolasi utf8mb4_unicode_ci) tanpa data parsial. */
    public function test_a_duplicate_email_in_another_case_is_rejected_by_the_database(): void
    {
        Notification::fake();
        $this->service()->register($this->payload());

        try {
            $this->service()->register($this->payload(['full_name' => 'Siti Kedua', 'email' => 'Siti@Contoh.test']));
            $this->fail('Email ganda harus melempar UniqueConstraintViolationException.');
        } catch (UniqueConstraintViolationException $e) {
            $this->assertStringContainsString('users_email_unique', $e->getMessage());
        }

        $this->assertSame(1, User::query()->count());
        $this->assertSame(1, PatientProfile::query()->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'patient.registered')->count());
        Notification::assertSentTimes(PatientWelcomeNotification::class, 1);
    }

    // --- updateProfile ---

    public function test_update_profile_writes_the_six_columns_and_syncs_the_account(): void
    {
        $user = $this->patientToUpdate();
        $profile = $user->patientProfile;
        $consentAt = $profile->consent_at->format('Y-m-d H:i:s');
        $payload = $this->samePayload($profile, [
            'full_name' => 'Siti Rahma Lestari',
            'birth_date' => '1991-04-13',
            'gender' => 'L',
            'phone' => '081298765432',
            'email' => 'siti.baru@contoh.test',
            'address' => 'Jl. Kenari No. 7, Ciputat',
        ]);

        $result = $this->service()->updateProfile($profile, $payload, Actor::patient($user));

        $this->assertTrue($result->is($profile));
        $this->assertSame('Siti Rahma Lestari', $result->full_name);

        $fresh = PatientProfile::query()->findOrFail($profile->id);
        $this->assertSame('Siti Rahma Lestari', $fresh->full_name);
        $this->assertSame('1991-04-13', $fresh->birth_date->toDateString());
        $this->assertSame(Gender::Male, $fresh->gender);
        $this->assertSame('081298765432', $fresh->phone);
        $this->assertSame('siti.baru@contoh.test', $fresh->email);
        $this->assertSame('Jl. Kenari No. 7, Ciputat', $fresh->address);

        // BR-151 / D-07: akun login ikut berubah.
        $account = User::query()->findOrFail($user->id);
        $this->assertSame('Siti Rahma Lestari', $account->name);
        $this->assertSame('siti.baru@contoh.test', $account->email);
        $this->assertSame('081298765432', $account->phone);
        $this->assertTrue(Hash::check('Rahasia123', $account->password));
        $this->assertSame(AccountType::Patient, $account->account_type);

        // Kolom sistem tidak tersentuh.
        $this->assertSame($profile->patient_number, $fresh->patient_number);
        $this->assertSame($user->id, $fresh->user_id);
        $this->assertSame(BookingChannel::Online, $fresh->registration_channel);
        $this->assertSame($consentAt, $fresh->consent_at->format('Y-m-d H:i:s'));
        $this->assertSame('1.0', $fresh->consent_version);
        $this->assertTrue($fresh->is_active);
    }

    /** Review Focus G.3 (sisi service) / PAS-05: hanya enam kolom whitelist yang berubah. */
    public function test_update_profile_ignores_keys_outside_the_whitelist(): void
    {
        $user = $this->patientToUpdate();
        $other = $this->createPatient();
        $profile = $user->patientProfile;
        $before = PatientProfile::query()->findOrFail($profile->id)->getAttributes();

        $this->service()->updateProfile($profile, $this->samePayload($profile, [
            'full_name' => 'Nama Sah',
            'user_id' => $other->id,
            'patient_number' => 'P999999',
            'registration_channel' => 'loket',
            'is_active' => false,
            'created_by_user_id' => $other->id,
            'bpjs_number' => '0009900000099',
            'bpjs_number_last4' => '9999',
            'consent_at' => '2000-01-01 00:00:00',
            'consent_version' => '9.9',
        ]), Actor::patient($user));

        $after = PatientProfile::query()->findOrFail($profile->id);
        $this->assertSame('Nama Sah', $after->full_name);

        foreach (['user_id', 'patient_number', 'registration_channel', 'is_active', 'created_by_user_id', 'bpjs_number', 'bpjs_number_last4', 'consent_at', 'consent_version'] as $column) {
            $this->assertSame($before[$column], $after->getAttributes()[$column], "Kolom {$column} tidak boleh berubah.");
        }

        $this->assertNull($after->bpjs_number);
        $this->assertSame($other->patientProfile->patient_number, PatientProfile::query()->findOrFail($other->patientProfile->id)->patient_number, 'Profil pasien lain tidak tersentuh.');
        $this->assertSame($other->name, User::query()->findOrFail($other->id)->name);
    }

    public function test_remove_bpjs_clears_the_number_and_last4_and_is_audited_without_values(): void
    {
        $user = $this->createPatient(['name' => 'Siti Rahma', 'email' => 'siti@contoh.test'], ['bpjs_number' => '0009900000011']);
        $profile = $user->patientProfile;
        $this->assertSame('0011', $profile->bpjs_number_last4);

        $this->service()->updateProfile($profile, $this->samePayload($profile, ['remove_bpjs' => true]), Actor::patient($user));

        $row = DB::table('patient_profiles')->where('id', $profile->id)->first();
        $this->assertNull($row->bpjs_number);
        $this->assertNull($row->bpjs_number_last4);
        $this->assertNull(PatientProfile::query()->findOrFail($profile->id)->masked_bpjs);

        $log = AuditLog::query()->where('action', 'patient.updated')->sole();
        $this->assertEquals(['bpjs_number' => AuditLogger::REDACTED], $log->old_values);
        $this->assertEquals(['bpjs_number' => AuditLogger::REDACTED], $log->new_values);
        $dump = (string) json_encode([$log->old_values, $log->new_values]);
        $this->assertStringNotContainsString('0009900000011', $dump);
        $this->assertStringNotContainsString('bpjs_number_last4', $dump);
        $this->assertStringNotContainsString('0011', $dump);
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function keepBpjsInputs(): array
    {
        return [
            'remove_bpjs false' => [['remove_bpjs' => false]],
            'remove_bpjs "0"' => [['remove_bpjs' => '0']],
            'remove_bpjs null' => [['remove_bpjs' => null]],
            'tanpa remove_bpjs' => [[]],
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    #[DataProvider('keepBpjsInputs')]
    public function test_the_bpjs_number_is_kept_unless_removal_is_requested(array $extra): void
    {
        $user = $this->createPatient([], ['bpjs_number' => '0009900000011']);
        $profile = $user->patientProfile;

        $this->service()->updateProfile($profile, $this->samePayload($profile, ['full_name' => 'Nama Baru'] + $extra), Actor::patient($user));

        $fresh = PatientProfile::query()->findOrFail($profile->id);
        $this->assertSame('Nama Baru', $fresh->full_name);
        $this->assertSame('0009900000011', $fresh->bpjs_number);
        $this->assertSame('0011', $fresh->bpjs_number_last4);
    }

    public function test_update_without_changes_writes_no_audit_and_no_row_update(): void
    {
        $user = $this->patientToUpdate();
        $profile = $user->patientProfile;
        $updatedAt = DB::table('patient_profiles')->where('id', $profile->id)->value('updated_at');
        $this->travelTo(now()->addHour());

        $result = $this->service()->updateProfile($profile, $this->samePayload($profile), Actor::patient($user));

        $this->assertSame(0, AuditLog::query()->count());
        $this->assertSame($updatedAt, DB::table('patient_profiles')->where('id', $profile->id)->value('updated_at'));
        $this->assertSame('Siti Rahma', $result->full_name);
    }

    public function test_update_with_stale_changes_on_the_instance_writes_no_audit(): void
    {
        $user = $this->patientToUpdate();
        $profile = $user->patientProfile;
        $profile->forceFill(['address' => 'Alamat sementara'])->save();

        $this->service()->updateProfile($profile, $this->samePayload($profile, ['address' => 'Alamat sementara']), Actor::patient($user));

        $this->assertSame(0, AuditLog::query()->where('action', 'patient.updated')->count());
    }

    /** F5-R24 / 12 §8.2: PII disamarkan, tanpa bpjs_number_last4 dan updated_at, aktor pasien. */
    public function test_the_update_audit_redacts_personal_data_and_omits_last4(): void
    {
        $user = $this->createPatient(
            ['name' => 'Siti Rahma', 'email' => 'siti@contoh.test'],
            [
                'full_name' => 'Siti Rahma',
                'birth_date' => '1990-03-12',
                'gender' => 'P',
                'phone' => '081234567890',
                'address' => 'Jl. Merpati No. 5, Pamulang',
                'bpjs_number' => '0009900000011',
            ],
        );
        $profile = $user->patientProfile;

        $this->service()->updateProfile($profile, $this->samePayload($profile, [
            'full_name' => 'Siti Rahma Lestari',
            'birth_date' => '1991-04-13',
            'gender' => 'L',
            'phone' => '081298765432',
            'email' => 'siti.baru@contoh.test',
            'address' => 'Jl. Kenari No. 7, Ciputat',
        ]), Actor::patient($user));

        $log = AuditLog::query()->where('action', 'patient.updated')->sole();

        $this->assertSame(1, AuditLog::query()->count());
        $this->assertSame(ActorType::Patient, $log->actor_type);
        $this->assertSame($user->id, (int) $log->user_id);
        $this->assertSame($profile->getMorphClass(), $log->auditable_type);
        $this->assertSame($profile->id, (int) $log->auditable_id);
        $this->assertSame("Profil pasien {$profile->patient_number} diperbarui.", $log->description);
        $this->assertEquals([
            'full_name' => 'Siti Rahma',
            'birth_date' => AuditLogger::REDACTED,
            'gender' => 'P',
            'phone' => AuditLogger::REDACTED,
            'email' => AuditLogger::REDACTED,
            'address' => AuditLogger::REDACTED,
        ], $log->old_values);
        $this->assertEquals([
            'full_name' => 'Siti Rahma Lestari',
            'birth_date' => AuditLogger::REDACTED,
            'gender' => 'L',
            'phone' => AuditLogger::REDACTED,
            'email' => AuditLogger::REDACTED,
            'address' => AuditLogger::REDACTED,
        ], $log->new_values);
        $this->assertArrayNotHasKey('bpjs_number_last4', $log->old_values);
        $this->assertArrayNotHasKey('bpjs_number_last4', $log->new_values);
        $this->assertArrayNotHasKey('updated_at', $log->new_values);

        $dump = (string) json_encode([$log->description, $log->old_values, $log->new_values]);

        foreach (['siti@contoh.test', 'siti.baru@contoh.test', '081234567890', '081298765432', 'Merpati', 'Kenari', '1990-03-12', '1991-04-13', '0009900000011'] as $fragment) {
            $this->assertStringNotContainsString($fragment, $dump, "Audit memuat: {$fragment}");
        }
    }

    /** Review Focus G.3: email bentrok di users tidak meninggalkan perubahan parsial pada profil maupun akun. */
    public function test_an_email_conflict_leaves_the_profile_and_the_account_unchanged(): void
    {
        $user = $this->patientToUpdate();
        $profile = $user->patientProfile;
        User::factory()->create(['email' => 'dipakai@contoh.test']);

        try {
            $this->service()->updateProfile($profile, $this->samePayload($profile, [
                'full_name' => 'Nama Lain',
                'phone' => '081200001111',
                'address' => 'Jl. Lain No. 1',
                'email' => 'dipakai@contoh.test',
            ]), Actor::patient($user));
            $this->fail('Email bentrok harus melempar UniqueConstraintViolationException.');
        } catch (UniqueConstraintViolationException $e) {
            $this->assertStringContainsString('users_email_unique', $e->getMessage());
        }

        $fresh = PatientProfile::query()->findOrFail($profile->id);
        $this->assertSame('Siti Rahma', $fresh->full_name);
        $this->assertSame('081234567890', $fresh->phone);
        $this->assertSame('Jl. Merpati No. 5, Pamulang', $fresh->address);
        $this->assertSame('siti@contoh.test', $fresh->email);

        $account = User::query()->findOrFail($user->id);
        $this->assertSame('Siti Rahma', $account->name);
        $this->assertSame('siti@contoh.test', $account->email);
        $this->assertSame('081234567890', $account->phone);

        $this->assertSame(0, AuditLog::query()->where('action', 'patient.updated')->count());
    }

    public function test_update_profile_of_a_walk_in_without_an_account_touches_only_the_profile(): void
    {
        $staff = $this->createStaff('registration-officer');
        $walkIn = PatientProfile::factory()->create([
            'full_name' => 'Rudi',
            'birth_date' => '1980-01-01',
            'gender' => 'L',
            'phone' => '081211112222',
            'email' => null,
            'address' => 'Jl. Lama',
        ]);
        $accounts = User::query()->count();

        $this->service()->updateProfile($walkIn, $this->samePayload($walkIn, ['full_name' => 'Rudi Hartono', 'email' => 'rudi@contoh.test']), Actor::staff($staff));

        $fresh = PatientProfile::query()->findOrFail($walkIn->id);
        $this->assertSame('Rudi Hartono', $fresh->full_name);
        $this->assertSame('rudi@contoh.test', $fresh->email);
        $this->assertNull($fresh->user_id);
        $this->assertSame($accounts, User::query()->count());

        $log = AuditLog::query()->where('action', 'patient.updated')->sole();
        $this->assertSame(ActorType::Staff, $log->actor_type);
        $this->assertSame($staff->id, (int) $log->user_id);
    }
}
