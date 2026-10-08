<?php

namespace Tests\Feature\Seeding;

use App\Enums\AccountType;
use App\Enums\BookingChannel;
use App\Enums\Gender;
use App\Models\AuditLog;
use App\Models\PatientProfile;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Rules\IndonesianPhone;
use App\Services\Patients\PatientNumberGenerator;
use App\Services\Settings\SettingsService;
use Closure;
use Database\Seeders\DemoSeeder;
use Database\Seeders\PatientSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

/**
 * Seeder demo pasien fase 5 (13 §10.1, F5-R27): tujuh akun pasien berakun beserta profil online. SEED-01 (I15 sampai
 * I17 dan angka acuan), SEED-02 (guard produksi), SEED-03 (idempoten), SEK-01, NTF-08 dan Review Focus 5 (atomisitas).
 * Walk-in (P000008 sampai P000013) dan appointment menyusul di fase 6 dan 7.
 */
class PatientSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Salinan mandiri data 13 §10.1 (nama, email, jenis kelamin, tanggal lahir, nomor BPJS fiktif), urut P000001 sampai
     * P000007. Sengaja tidak membaca `PatientSeeder::ACCOUNTS` agar perubahan data seeder terdeteksi.
     *
     * @var list<array{0: string, 1: string, 2: string, 3: string, 4: ?string}>
     */
    private const EXPECTED = [
        ['Anisa Rahmawati', 'pasien@unpamhospital.test', 'P', '1994-05-12', '0009900000011'],
        ['Budi Hartanto', 'budi.hartanto@contoh.test', 'L', '1985-11-03', '0009900000022'],
        ['Dewi Susilowati', 'dewi.susilowati@contoh.test', 'P', '1990-07-21', null],
        ['Fitri Handayani', 'fitri.handayani@contoh.test', 'P', '1998-01-30', '0009900000033'],
        ['Gilang Saputra', 'gilang.saputra@contoh.test', 'L', '2001-09-09', null],
        ['Kartika Dewi Anggraini', 'kartika.anggraini@contoh.test', 'P', '1979-12-15', null],
        ['Mulyani Astuti', 'mulyani.astuti@contoh.test', 'P', '1958-02-20', null],
    ];

    private function seedPatients(): void
    {
        $this->seed(PatientSeeder::class);
    }

    private function profileFor(string $email): PatientProfile
    {
        return PatientProfile::query()->where('email', $email)->firstOrFail();
    }

    public function test_it_creates_seven_active_patient_accounts_without_roles(): void
    {
        $this->seedPatients();

        $this->assertSame(7, User::query()->count());
        $this->assertSame(7, User::query()->patients()->count());
        $this->assertSame(7, PatientProfile::query()->count());

        foreach (self::EXPECTED as [$name, $email]) {
            $user = User::query()->where('email', $email)->firstOrFail();

            $this->assertSame($name, $user->name, $email);
            $this->assertSame(AccountType::Patient, $user->account_type, $email);
            $this->assertTrue($user->is_active, $email);
            $this->assertFalse($user->must_change_password, $email);
            $this->assertNull($user->last_login_at, $email);
            $this->assertSame(0, $user->roles()->count(), "{$email}: pasien tidak memegang peran Spatie.");
        }
    }

    public function test_demo_password_is_stored_as_a_bcrypt_hash_and_the_accounts_can_authenticate_as_patients(): void
    {
        $this->seedPatients();

        foreach (self::EXPECTED as [, $email]) {
            $user = User::query()->where('email', $email)->firstOrFail();
            $stored = (string) DB::table('users')->where('id', $user->id)->value('password');

            $this->assertNotSame('Demo12345', $stored, $email);
            $this->assertStringStartsWith('$2y$', $stored, $email);
            $this->assertTrue(Hash::check('Demo12345', $user->password), "{$email} harus memakai password demo bawaan.");
            $this->assertTrue(
                Auth::guard('web')->validate(['email' => $email, 'password' => 'Demo12345', 'account_type' => AccountType::Patient->value, 'is_active' => true]),
                "{$email} harus dapat masuk sebagai pasien aktif.",
            );
        }

        $this->assertFalse(Auth::guard('web')->validate(['email' => 'pasien@unpamhospital.test', 'password' => 'Salah12345', 'account_type' => AccountType::Patient->value, 'is_active' => true]));
    }

    public function test_the_configured_demo_password_is_used(): void
    {
        config(['app.demo_password' => 'Rahasia987']);

        $this->seedPatients();

        $user = User::query()->where('email', 'pasien@unpamhospital.test')->firstOrFail();
        $this->assertTrue(Hash::check('Rahasia987', $user->password));
        $this->assertFalse(Hash::check('Demo12345', $user->password));
    }

    public function test_profiles_follow_the_13_section_10_1_data(): void
    {
        $this->seedPatients();

        foreach (self::EXPECTED as $index => [$name, $email, $gender, $birthDate]) {
            $number = $index + 1;
            $profile = $this->profileFor($email);

            $this->assertSame($name, $profile->full_name, $email);
            $this->assertSame(Gender::from($gender), $profile->gender, $email);
            $this->assertSame($birthDate, $profile->birth_date->toDateString(), $email);
            $this->assertSame("Jl. Contoh No. {$number}, Pamulang, Tangerang Selatan", $profile->address, $email);
            $this->assertSame('08120000000'.$number, $profile->phone, $email);
        }

        $ids = array_map(fn (array $row): int => $this->profileFor($row[1])->id, self::EXPECTED);
        $sorted = $ids;
        sort($sorted);
        $this->assertSame($sorted, $ids, 'Urutan penyimpanan mengikuti P000001 sampai P000007; akun demo adalah profil pertama.');
    }

    public function test_profiles_are_online_registrations_linked_to_their_accounts(): void
    {
        $this->seedPatients();

        foreach (self::EXPECTED as [, $email]) {
            $user = User::query()->where('email', $email)->firstOrFail();
            $profile = $this->profileFor($email);

            $this->assertSame($user->id, $profile->user_id, $email);
            $this->assertSame(BookingChannel::Online, $profile->registration_channel, $email);
            $this->assertNull($profile->created_by_user_id, $email);
            $this->assertTrue($profile->is_active, $email);
            $this->assertSame($profile->id, $user->patientProfile()->value('id'), "{$email}: relasi User::patientProfile().");
        }

        $this->assertSame(7, PatientProfile::query()->whereNotNull('user_id')->count());
        $this->assertSame(0, PatientProfile::query()->where('registration_channel', BookingChannel::Loket->value)->count(), 'Walk-in menyusul di fase 6/7.');
    }

    /** SEED-01 I15: `patient_number` = `P` + `id` 6 digit (nilai literal P000001..P000007 berlaku pada DB baru). */
    public function test_patient_number_is_formatted_from_the_profile_id(): void
    {
        $this->seedPatients();

        foreach (PatientProfile::query()->get() as $profile) {
            $this->assertSame(PatientNumberGenerator::format($profile->id), $profile->patient_number);
            $this->assertSame(1, preg_match('/^P[0-9]{6,}$/', (string) $profile->patient_number));
        }

        $this->assertSame([], DB::select("SELECT id FROM patient_profiles WHERE patient_number IS NULL OR patient_number <> CONCAT('P', LPAD(id, 6, '0'))"));
    }

    /** SEED-01 I16 dan I17 (13 §11.1) serta PAS-09. */
    public function test_profile_email_and_active_flag_match_the_account(): void
    {
        $this->seedPatients();

        $this->assertSame([], DB::select('SELECT p.id FROM patient_profiles p JOIN users u ON u.id = p.user_id WHERE p.email IS NULL OR p.email <> u.email'));
        $this->assertSame([], DB::select('SELECT p.id FROM patient_profiles p JOIN users u ON u.id = p.user_id WHERE p.is_active <> u.is_active'));

        foreach (self::EXPECTED as [, $email]) {
            $this->assertSame($email, User::query()->whereKey($this->profileFor($email)->user_id)->value('email'));
        }
    }

    public function test_consent_version_comes_from_the_settings(): void
    {
        $this->seed(SettingsSeeder::class);
        WebsiteSetting::query()->where('key', 'privacy.consent_version')->update(['value' => '2.0']);
        app(SettingsService::class)->forget();

        $this->seedPatients();

        $this->assertSame(7, PatientProfile::query()->where('consent_version', '2.0')->count());
    }

    public function test_consent_version_falls_back_to_one_point_zero_without_the_setting(): void
    {
        $this->seedPatients();

        $this->assertSame(7, PatientProfile::query()->where('consent_version', '1.0')->count());
    }

    public function test_consent_is_given_at_the_application_time(): void
    {
        $this->travelTo('2026-10-08 09:30:00');

        $this->seedPatients();

        foreach (PatientProfile::query()->get() as $profile) {
            $this->assertSame('2026-10-08 09:30:00', $profile->consent_at->format('Y-m-d H:i:s'));
        }
    }

    public function test_bpjs_is_encrypted_with_last4_for_accounts_one_two_and_four_and_null_for_the_rest(): void
    {
        $this->seedPatients();

        foreach (self::EXPECTED as [, $email, , , $bpjs]) {
            $profile = $this->profileFor($email);
            $raw = DB::table('patient_profiles')->where('id', $profile->id)->first();

            if ($bpjs === null) {
                $this->assertNull($raw->bpjs_number, $email);
                $this->assertNull($raw->bpjs_number_last4, $email);
                $this->assertNull($profile->bpjs_number, $email);
                $this->assertNull($profile->masked_bpjs, $email);

                continue;
            }

            $this->assertNotNull($raw->bpjs_number, $email);
            $this->assertStringNotContainsString($bpjs, (string) $raw->bpjs_number, "{$email}: nomor BPJS tidak boleh tersimpan polos.");
            $this->assertSame($bpjs, $profile->bpjs_number, $email);
            $this->assertSame(substr($bpjs, -4), $raw->bpjs_number_last4, $email);
            $this->assertSame(str_repeat('•', 9).substr($bpjs, -4), $profile->masked_bpjs, $email);
        }

        $this->assertSame(['0011', '0022', '0033'], PatientProfile::query()->whereNotNull('bpjs_number_last4')->orderBy('id')->pluck('bpjs_number_last4')->all());
    }

    public function test_phone_numbers_are_normalised_and_match_the_indonesian_pattern(): void
    {
        $this->seedPatients();

        foreach (self::EXPECTED as [, $email]) {
            $profile = $this->profileFor($email);
            $user = User::query()->where('email', $email)->firstOrFail();

            $this->assertSame(IndonesianPhone::normalize($profile->phone), $profile->phone, "{$email}: telepon sudah ternormalisasi.");
            $this->assertSame(1, preg_match(IndonesianPhone::PATTERN, $profile->phone), "{$email}: {$profile->phone}");
            $this->assertSame(12, strlen($profile->phone), $email);
            $this->assertSame($profile->phone, $user->phone, "{$email}: telepon akun = telepon profil.");
        }
    }

    public function test_running_twice_is_idempotent_and_warns_about_existing_accounts(): void
    {
        $this->seedPatients();

        $pending = $this->artisan('db:seed', ['--class' => PatientSeeder::class, '--no-interaction' => true]);

        foreach (self::EXPECTED as [, $email]) {
            $pending->expectsOutputToContain("Akun pasien demo {$email} sudah ada, dilewati.");
        }

        $pending->assertExitCode(0);

        $this->assertSame(7, User::query()->count());
        $this->assertSame(7, PatientProfile::query()->count());
    }

    public function test_an_existing_account_is_skipped_without_being_touched_and_gets_no_profile(): void
    {
        User::factory()->patient()->create(['email' => 'budi.hartanto@contoh.test', 'name' => 'Budi Lama', 'password' => 'Rahasia123']);

        $this->artisan('db:seed', ['--class' => PatientSeeder::class, '--no-interaction' => true])
            ->expectsOutputToContain('Akun pasien demo budi.hartanto@contoh.test sudah ada, dilewati.')
            ->assertExitCode(0);

        $budi = User::query()->where('email', 'budi.hartanto@contoh.test')->firstOrFail();
        $this->assertSame('Budi Lama', $budi->name);
        $this->assertTrue(Hash::check('Rahasia123', $budi->password));
        $this->assertSame(0, $budi->patientProfile()->count());
        $this->assertSame(7, User::query()->count());
        $this->assertSame(6, PatientProfile::query()->count());
    }

    /** Review Focus 5 (bagian seeder): setiap pasien dibuat dalam satu transaksi sendiri. */
    public function test_each_patient_is_created_in_its_own_transaction(): void
    {
        PatientProfile::creating(function (PatientProfile $profile): void {
            if ($profile->full_name === 'Budi Hartanto') {
                throw new RuntimeException('Gagal uji transaksi.');
            }
        });

        $thrown = null;

        try {
            $this->app->make(PatientSeeder::class)->run();
        } catch (RuntimeException $e) {
            $thrown = $e;
        }

        $this->assertInstanceOf(RuntimeException::class, $thrown);
        $this->assertSame('Gagal uji transaksi.', $thrown->getMessage());
        $this->assertTrue(User::query()->where('email', 'pasien@unpamhospital.test')->exists(), 'Pasien pertama sudah tersimpan.');
        $this->assertSame(1, PatientProfile::query()->count());
        $this->assertFalse(User::query()->where('email', 'budi.hartanto@contoh.test')->exists(), 'Akun pasien yang gagal ikut dibatalkan.');
        $this->assertSame(1, User::query()->count());
    }

    /** SEED-02 (bagian fase 5): guard lingkungan seperti `DemoUserSeeder`. */
    public function test_it_refuses_to_run_outside_the_local_and_testing_environments(): void
    {
        foreach (['production', 'staging'] as $environment) {
            $thrown = null;

            $this->inEnvironment($environment, function () use (&$thrown): void {
                try {
                    $this->app->make(PatientSeeder::class)->run();
                } catch (RuntimeException $e) {
                    $thrown = $e;
                }
            });

            $this->assertInstanceOf(RuntimeException::class, $thrown, $environment);
            $this->assertStringContainsString("PatientSeeder DILARANG di APP_ENV={$environment}", $thrown->getMessage());
            $this->assertSame(0, User::query()->count(), $environment);
            $this->assertSame(0, PatientProfile::query()->count(), $environment);
        }
    }

    /** NTF-08 dan F5-R27: seeder menulis lewat model, bukan lewat service registrasi (tanpa audit, tanpa email). */
    public function test_it_writes_no_audit_and_sends_no_notification(): void
    {
        Notification::fake();
        Mail::fake();

        $this->seedPatients();

        Notification::assertNothingSent();
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        $this->assertSame(0, DB::table('notifications')->count());
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_demo_seeder_runs_the_patient_seeder_and_prints_the_patient_credentials(): void
    {
        Storage::fake('public');
        $output = new BufferedOutput;

        $exit = Artisan::call('db:seed', ['--class' => DemoSeeder::class, '--no-interaction' => true], $output);
        $text = $output->fetch();

        $this->assertSame(0, $exit);
        $this->assertSame(10, User::query()->count());
        $this->assertSame(7, PatientProfile::query()->count());
        $this->assertStringContainsString('Anisa Rahmawati', $text);
        $this->assertStringContainsString('pasien@unpamhospital.test', $text);
        $this->assertStringContainsString('Pasien', $text);
        $this->assertStringContainsString('Demo12345', $text);
        $this->assertStringContainsString('Enam akun pasien lain (@contoh.test) memakai password yang sama.', $text);
        $this->assertStringNotContainsString('budi.hartanto@contoh.test', $text, 'Hanya akun pasien demo yang masuk tabel kredensial.');
    }

    private function inEnvironment(string $environment, Closure $callback): void
    {
        $original = $this->app['env'];
        $this->app['env'] = $environment;

        try {
            $callback();
        } finally {
            $this->app['env'] = $original;
        }
    }
}
