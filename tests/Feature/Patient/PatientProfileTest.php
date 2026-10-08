<?php

namespace Tests\Feature\Patient;

use App\Enums\ActorType;
use App\Enums\BookingChannel;
use App\Enums\Gender;
use App\Models\AuditLog;
use App\Models\PatientProfile;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithPatients;
use Tests\TestCase;

/**
 * Halaman profil pasien (#28, F5-R18/R19/R20): V-06, BR-151, BR-161, PAS-05, PAS-09, SEK-11, SEK-12. Profil selalu
 * `$request->user()->patientProfile`; tidak ada id profil di URL maupun form.
 */
class PatientProfileTest extends TestCase
{
    use InteractsWithPatients;
    use RefreshDatabase;

    private const BPJS = '0009900000011';

    // --- pembantu ----------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $user
     * @param  array<string, mixed>  $profile
     */
    private function patient(array $user = [], array $profile = []): User
    {
        return $this->createPatient(
            $user + ['name' => 'Siti Rahma', 'email' => 'siti@contoh.test'],
            $profile + [
                'full_name' => 'Siti Rahma',
                'birth_date' => '1990-03-12',
                'gender' => 'P',
                'phone' => '081234567890',
                'address' => 'Jl. Merpati No. 5, Pamulang',
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return $overrides + [
            'full_name' => 'Siti Rahma Baru',
            'birth_date' => '1991-02-03',
            'gender' => 'L',
            'phone' => '0812-9999-8888',
            'email' => 'siti.baru@contoh.test',
            'address' => 'Jl. Kenari No. 9, Ciputat, Tangerang Selatan',
        ];
    }

    /** Payload yang sama persis dengan isi profil saat ini (tanpa perubahan). @return array<string, string> */
    private function currentPayload(User $user): array
    {
        $profile = $user->patientProfile;

        return [
            'full_name' => $profile->full_name,
            'birth_date' => $profile->birth_date->format('Y-m-d'),
            'gender' => $profile->gender->value,
            'phone' => $profile->phone,
            'email' => $user->email,
            'address' => $profile->address,
        ];
    }

    /** @param  array<string, mixed>  $data */
    private function putProfile(array $data): TestResponse
    {
        return $this->from(route('patient.profile.edit'))->put(route('patient.profile.update'), $data);
    }

    private function profileRow(PatientProfile $profile): ?object
    {
        return DB::table('patient_profiles')->where('id', $profile->id)->first();
    }

    private function userRow(User $user): ?object
    {
        return DB::table('users')->where('id', $user->id)->first();
    }

    // --- tampilan ---------------------------------------------------------------------------

    public function test_profile_page_renders_the_values_number_breadcrumb_and_both_forms(): void
    {
        $user = $this->patient();
        $profile = $user->patientProfile;

        $response = $this->actingAs($user)->get(route('patient.profile.edit'));

        $response->assertOk()
            ->assertSee('Profil dan Akun')
            ->assertSee('No. pasien: '.$profile->patient_number)
            ->assertSee('Data Profil')
            ->assertSee('Ganti Password')
            ->assertSee('<li class="breadcrumb-item"><a href="'.route('patient.dashboard').'">Portal Pasien</a></li>', false)
            ->assertSee('<li class="breadcrumb-item active" aria-current="page">Profil dan Akun</li>', false)
            ->assertSee('action="'.route('patient.profile.update').'"', false)
            ->assertSee('action="'.route('patient.password.update').'"', false)
            ->assertSee('name="_method" value="PUT"', false)
            ->assertSee('value="Siti Rahma"', false)
            ->assertSee('value="1990-03-12"', false)
            ->assertSee('value="P" checked', false)
            ->assertDontSee('value="L" checked', false)
            ->assertSee('value="081234567890"', false)
            ->assertSee('value="siti@contoh.test"', false)
            ->assertSee('Jl. Merpati No. 5, Pamulang')
            ->assertSee('Simpan Profil')
            ->assertSee('Simpan Password')
            ->assertSee('Password saat ini')
            ->assertSee('Password baru')
            ->assertSee('Konfirmasi password baru');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertSame(1, substr_count($response->getContent(), 'autocomplete="current-password"'));
        $this->assertSame(2, substr_count($response->getContent(), 'autocomplete="new-password"'));
    }

    public function test_the_page_escapes_the_patient_name_and_has_no_errors_summary_without_errors(): void
    {
        $user = $this->patient(['name' => 'Siti'], ['full_name' => '<script>alert(1)</script>']);

        $response = $this->actingAs($user)->get(route('patient.profile.edit'));

        $response->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
        $this->assertStringNotContainsString('data-errors-summary', $response->getContent());
    }

    public function test_the_bpjs_number_is_masked_on_the_page_and_never_rendered_in_full(): void
    {
        $user = $this->patient([], ['bpjs_number' => self::BPJS]);

        $response = $this->actingAs($user)->get(route('patient.profile.edit'));

        $response->assertOk()
            ->assertSee('Nomor BPJS tersimpan: •••••••••0011')
            ->assertSee('name="remove_bpjs"', false)
            ->assertSee('Hapus nomor BPJS dari profil saya')
            ->assertDontSee(self::BPJS)
            ->assertDontSee('name="bpjs_number"', false)
            ->assertDontSee('Belum ada nomor BPJS tersimpan.');

        // Di database nilai terenkripsi, bukan teks asli; last4 terisi.
        $row = $this->profileRow($user->patientProfile);
        $this->assertNotSame(self::BPJS, $row->bpjs_number);
        $this->assertStringNotContainsString(self::BPJS, (string) $row->bpjs_number);
        $this->assertSame('0011', $row->bpjs_number_last4);
    }

    public function test_without_a_stored_bpjs_number_the_page_says_so_and_offers_no_removal(): void
    {
        $user = $this->patient();

        $this->actingAs($user)->get(route('patient.profile.edit'))
            ->assertOk()
            ->assertSee('Belum ada nomor BPJS tersimpan.')
            ->assertDontSee('remove_bpjs', false)
            ->assertDontSee('Hapus nomor BPJS dari profil saya')
            ->assertDontSee('name="bpjs_number"', false);
    }

    public function test_the_dashboard_links_to_the_profile_page(): void
    {
        $user = $this->patient();

        $response = $this->actingAs($user)->get(route('patient.dashboard'));

        $response->assertOk()->assertSee('Ubah Profil dan Akun');
        $this->assertMatchesRegularExpression(
            '#<a[^>]*href="'.preg_quote(route('patient.profile.edit'), '#').'"[^>]*>\s*Ubah Profil dan Akun\s*</a>#',
            $response->getContent(),
        );
    }

    public function test_the_portal_menu_marks_the_profile_item_as_current(): void
    {
        $this->actingAs($this->patient())->get(route('patient.profile.edit'))
            ->assertOk()
            ->assertSee('aria-label="Menu portal pasien"', false)
            ->assertSee('aria-current="page"', false);
    }

    // --- akses ------------------------------------------------------------------------------

    public function test_guests_are_sent_to_the_login_page_and_nothing_changes(): void
    {
        $user = $this->patient();
        $before = $this->profileRow($user->patientProfile);

        $this->get(route('patient.profile.edit'))->assertRedirect(route('login'));
        $this->put(route('patient.profile.update'), $this->validPayload())->assertRedirect(route('login'));

        $this->assertEquals($before, $this->profileRow($user->patientProfile));
    }

    public function test_an_account_without_a_profile_gets_404_on_the_page_and_403_on_the_update(): void
    {
        $account = User::factory()->patient()->create(['email' => 'tanpa-profil@contoh.test']);

        $this->actingAs($account)->get(route('patient.profile.edit'))->assertNotFound();
        $this->actingAs($account)->put(route('patient.profile.update'), $this->validPayload())->assertForbidden();
        $this->assertSame(0, PatientProfile::query()->count());
        $this->assertSame(0, AuditLog::query()->where('action', 'patient.updated')->count());
    }

    // --- pembaruan sukses (PAS-09) -----------------------------------------------------------

    public function test_a_successful_update_changes_the_six_columns_and_syncs_the_account(): void
    {
        $user = $this->patient();
        $profile = $user->patientProfile;
        $number = $profile->patient_number;
        $passwordHash = $user->password;

        $this->actingAs($user);
        $this->putProfile($this->validPayload())
            ->assertRedirect(route('patient.profile.edit'))
            ->assertSessionHas('success', 'Profil berhasil diperbarui.')
            ->assertSessionHasNoErrors();

        $fresh = PatientProfile::query()->findOrFail($profile->id);
        $this->assertSame('Siti Rahma Baru', $fresh->full_name);
        $this->assertSame('1991-02-03', $fresh->birth_date->toDateString());
        $this->assertSame(Gender::Male, $fresh->gender);
        $this->assertSame('081299998888', $fresh->phone, 'Telepon disimpan dalam bentuk ternormalisasi.');
        $this->assertSame('siti.baru@contoh.test', $fresh->email);
        $this->assertSame('Jl. Kenari No. 9, Ciputat, Tangerang Selatan', $fresh->address);
        $this->assertSame($number, $fresh->patient_number);
        $this->assertEquals($user->id, $fresh->user_id);
        $this->assertSame(BookingChannel::Online, $fresh->registration_channel);

        // BR-151/D-07: users.name, users.email, users.phone mengikuti profil; kolom akun lain tidak berubah.
        $account = $user->fresh();
        $this->assertSame('Siti Rahma Baru', $account->name);
        $this->assertSame('siti.baru@contoh.test', $account->email);
        $this->assertSame('081299998888', $account->phone);
        $this->assertSame($fresh->email, $account->email, 'patient_profiles.email selalu sama dengan users.email.');
        $this->assertSame($passwordHash, $account->password);
        $this->assertTrue($account->isPatient());
        $this->assertTrue($account->is_active);
        $this->assertFalse($account->must_change_password);

        // Flash tampil di halaman profil berikutnya.
        $this->get(route('patient.profile.edit'))
            ->assertOk()
            ->assertSee('Profil berhasil diperbarui.')
            ->assertSee('value="Siti Rahma Baru"', false);
    }

    public function test_keeping_the_own_email_is_not_a_conflict(): void
    {
        $user = $this->patient();

        $this->actingAs($user);
        $this->putProfile($this->validPayload(['email' => 'siti@contoh.test']))
            ->assertRedirect(route('patient.profile.edit'))
            ->assertSessionHas('success', 'Profil berhasil diperbarui.')
            ->assertSessionHasNoErrors();

        $this->assertSame('siti@contoh.test', $user->fresh()->email);
        $this->assertSame('siti@contoh.test', $user->patientProfile->fresh()->email);
    }

    public function test_the_email_is_normalised_to_lowercase_before_it_is_stored(): void
    {
        $user = $this->patient();

        $this->actingAs($user);
        $this->putProfile($this->validPayload(['email' => '  Siti.Baru@Contoh.TEST ']))
            ->assertSessionHas('success');

        $this->assertSame('siti.baru@contoh.test', $user->fresh()->email);
        $this->assertSame('siti.baru@contoh.test', $user->patientProfile->fresh()->email);
    }

    // --- PAS-05 / Review Focus G.3 -----------------------------------------------------------

    public function test_extra_fields_are_ignored_and_other_patients_are_untouched(): void
    {
        $owner = $this->patient([], ['bpjs_number' => self::BPJS]);
        $other = $this->createPatient(['email' => 'lain@contoh.test'], ['bpjs_number' => '0001234567890']);
        $ownerProfile = $owner->patientProfile;
        $otherProfile = $other->patientProfile;

        $ownerBefore = $this->profileRow($ownerProfile);
        $otherBefore = $this->profileRow($otherProfile);
        $otherUserBefore = $this->userRow($other);

        $this->actingAs($owner);
        $this->putProfile($this->validPayload([
            'user_id' => $other->id,
            'patient_number' => 'P999999',
            'registration_channel' => 'loket',
            'is_active' => '0',
            'bpjs_number_last4' => '9999',
            'bpjs_number' => '0007777777777',
            'consent_at' => '2000-01-01 00:00:00',
            'consent_version' => '9.9',
            'created_by_user_id' => $other->id,
            'id' => $otherProfile->id,
            'patient_profile_id' => $otherProfile->id,
        ]))
            ->assertRedirect(route('patient.profile.edit'))
            ->assertSessionHas('success', 'Profil berhasil diperbarui.');

        $ownerAfter = $this->profileRow($ownerProfile);

        foreach (['id', 'user_id', 'patient_number', 'registration_channel', 'is_active', 'bpjs_number', 'bpjs_number_last4', 'consent_at', 'consent_version', 'created_by_user_id'] as $column) {
            $this->assertEquals($ownerBefore->{$column}, $ownerAfter->{$column}, "Kolom {$column} milik pasien tidak boleh berubah lewat form.");
        }

        // Enam kolom yang diizinkan berubah.
        $this->assertSame('Siti Rahma Baru', $ownerAfter->full_name);
        $this->assertSame('081299998888', $ownerAfter->phone);

        // Profil dan akun pasien lain tidak tersentuh sama sekali.
        $this->assertEquals($otherBefore, $this->profileRow($otherProfile));
        $this->assertEquals($otherUserBefore, $this->userRow($other));
        $this->assertSame(2, PatientProfile::query()->count());
    }

    // --- email bentrok (PAS-09, G.3) ---------------------------------------------------------

    public function test_a_taken_email_is_rejected_without_any_partial_change(): void
    {
        $owner = $this->patient();
        $this->createPatient(['email' => 'dipakai@contoh.test']);
        $profileBefore = $this->profileRow($owner->patientProfile);
        $userBefore = $this->userRow($owner);

        $this->actingAs($owner);

        foreach (['dipakai@contoh.test', ' Dipakai@Contoh.TEST '] as $email) {
            $this->putProfile($this->validPayload(['email' => $email, 'full_name' => 'Nama Tidak Boleh Tersimpan']))
                ->assertRedirect(route('patient.profile.edit'))
                ->assertSessionHasErrors(['email' => 'Email ini sudah terdaftar. Gunakan email lain.']);
        }

        $this->assertEquals($profileBefore, $this->profileRow($owner->patientProfile));
        $this->assertEquals($userBefore, $this->userRow($owner));
        $this->assertSame(0, AuditLog::query()->where('action', 'patient.updated')->count());
    }

    public function test_a_unique_constraint_race_becomes_an_email_error_and_rolls_the_profile_back(): void
    {
        $owner = $this->patient();
        $profileBefore = $this->profileRow($owner->patientProfile);

        // `users` disimpan SESUDAH profil di dalam transaksi service; pengecualian di sini membuktikan rollback profil.
        User::saving(function (): void {
            throw new UniqueConstraintViolationException(
                'mysql',
                'update `users` set `email` = ? where `id` = ?',
                [],
                new PDOException('Duplicate entry'),
            );
        });

        $this->actingAs($owner);
        $this->putProfile($this->validPayload())
            ->assertRedirect(route('patient.profile.edit'))
            ->assertSessionHasErrors(['email' => 'Email ini sudah terdaftar. Gunakan email lain.']);

        $this->assertSame('siti.baru@contoh.test', session()->getOldInput('email'));
        $this->assertEquals($profileBefore, $this->profileRow($owner->patientProfile));
        $this->assertSame(0, AuditLog::query()->where('action', 'patient.updated')->count());
    }

    // --- hapus BPJS (F5-R19) -----------------------------------------------------------------

    public function test_remove_bpjs_clears_the_number_and_last4_and_the_page_reflects_it(): void
    {
        $user = $this->patient([], ['bpjs_number' => self::BPJS]);
        $this->actingAs($user);

        $this->putProfile($this->currentPayload($user) + ['remove_bpjs' => '1'])
            ->assertRedirect(route('patient.profile.edit'))
            ->assertSessionHas('success', 'Profil berhasil diperbarui.');

        $row = $this->profileRow($user->patientProfile);
        $this->assertNull($row->bpjs_number);
        $this->assertNull($row->bpjs_number_last4);

        $this->get(route('patient.profile.edit'))
            ->assertSee('Belum ada nomor BPJS tersimpan.')
            ->assertDontSee('•••••••••0011');

        // Audit memuat penanda `[disamarkan]` untuk BPJS dan tidak pernah nilai mentah (SEK-11).
        $log = AuditLog::query()->where('action', 'patient.updated')->sole();
        $this->assertSame('[disamarkan]', $log->new_values['bpjs_number'] ?? null);
        $this->assertArrayNotHasKey('bpjs_number_last4', $log->new_values);
        $this->assertSame(0, DB::table('audit_logs')
            ->where('old_values', 'like', '%'.self::BPJS.'%')
            ->orWhere('new_values', 'like', '%'.self::BPJS.'%')
            ->orWhere('description', 'like', '%'.self::BPJS.'%')
            ->count());
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function nonRemovalValues(): array
    {
        return [
            'tanpa field remove_bpjs' => [[]],
            'remove_bpjs 0' => [['remove_bpjs' => '0']],
        ];
    }

    /** @param  array<string, mixed>  $extra */
    #[DataProvider('nonRemovalValues')]
    public function test_the_bpjs_number_is_kept_unless_removal_is_explicitly_requested(array $extra): void
    {
        $user = $this->patient([], ['bpjs_number' => self::BPJS]);
        $before = $this->profileRow($user->patientProfile);
        $this->actingAs($user);

        $this->putProfile($this->validPayload($extra))->assertSessionHas('success');

        $after = $this->profileRow($user->patientProfile);
        $this->assertSame($before->bpjs_number, $after->bpjs_number);
        $this->assertSame('0011', $after->bpjs_number_last4);
        $this->assertSame(self::BPJS, $user->patientProfile->fresh()->bpjs_number);
    }

    // --- audit (SEK-11) ----------------------------------------------------------------------

    public function test_the_update_is_audited_by_field_name_without_raw_personal_data(): void
    {
        $user = $this->patient();
        $number = $user->patientProfile->patient_number;

        $this->actingAs($user);
        $this->putProfile($this->validPayload())->assertSessionHas('success');

        $log = AuditLog::query()->where('action', 'patient.updated')->sole();
        $this->assertSame(ActorType::Patient, $log->actor_type);
        $this->assertEquals($user->id, $log->user_id);
        $this->assertSame(PatientProfile::class, $log->auditable_type);
        $this->assertEquals($user->patientProfile->id, $log->auditable_id);
        $this->assertSame("Profil pasien {$number} diperbarui.", $log->description);

        $keys = array_keys($log->new_values);
        sort($keys);
        $this->assertSame(['address', 'birth_date', 'email', 'full_name', 'gender', 'phone'], $keys);
        $this->assertSame('Siti Rahma', $log->old_values['full_name']);
        $this->assertSame('Siti Rahma Baru', $log->new_values['full_name']);
        $this->assertSame('P', $log->old_values['gender']);
        $this->assertSame('L', $log->new_values['gender']);

        foreach (['address', 'birth_date', 'email', 'phone'] as $redacted) {
            $this->assertSame('[disamarkan]', $log->old_values[$redacted]);
            $this->assertSame('[disamarkan]', $log->new_values[$redacted]);
        }

        foreach (['081234567890', '081299998888', '0812-9999-8888', 'Jl. Merpati No. 5', 'Jl. Kenari No. 9', 'siti@contoh.test', 'siti.baru@contoh.test', '1990-03-12', '1991-02-03'] as $secret) {
            $this->assertSame(0, DB::table('audit_logs')
                ->where('old_values', 'like', "%{$secret}%")
                ->orWhere('new_values', 'like', "%{$secret}%")
                ->orWhere('description', 'like', "%{$secret}%")
                ->count(), "Nilai '{$secret}' tidak boleh tersimpan mentah di audit_logs.");
        }
    }

    public function test_an_update_without_changes_is_not_audited(): void
    {
        $user = $this->patient();

        $this->actingAs($user);
        $this->putProfile($this->currentPayload($user))
            ->assertRedirect(route('patient.profile.edit'))
            ->assertSessionHas('success', 'Profil berhasil diperbarui.');

        $this->assertSame(0, AuditLog::query()->where('action', 'patient.updated')->count());
    }

    // --- validasi V-06 dan input liar (G.2) --------------------------------------------------

    /**
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function invalidInputs(): array
    {
        $personName = 'Nama lengkap hanya boleh berisi huruf, spasi, titik, apostrof, dan tanda hubung.';

        return [
            'nama kosong' => [['full_name' => ''], 'full_name', 'Nama lengkap wajib diisi.'],
            'nama larik' => [['full_name' => ['x']], 'full_name', 'Nama lengkap harus berupa teks.'],
            'nama terlalu pendek' => [['full_name' => 'Ab'], 'full_name', 'Nama lengkap minimal 3 karakter.'],
            'nama terlalu panjang' => [['full_name' => str_repeat('a', 101)], 'full_name', 'Nama lengkap maksimal 100 karakter.'],
            'nama berangka' => [['full_name' => 'Siti123'], 'full_name', $personName],
            'tanggal kosong' => [['birth_date' => ''], 'birth_date', 'Tanggal lahir wajib diisi.'],
            'tanggal larik' => [['birth_date' => ['x']], 'birth_date', 'Format tanggal lahir tidak valid.'],
            'tanggal format salah' => [['birth_date' => '31-12-1990'], 'birth_date', 'Format tanggal lahir tidak valid.'],
            'tanggal masa depan' => [['birth_date' => '2099-01-01'], 'birth_date', 'Tanggal lahir harus sebelum hari ini.'],
            'tanggal lebih dari 120 tahun' => [['birth_date' => '1800-01-01'], 'birth_date', 'Tanggal lahir tidak boleh lebih dari 120 tahun yang lalu.'],
            'jenis kelamin kosong' => [['gender' => ''], 'gender', 'Jenis kelamin wajib dipilih.'],
            'jenis kelamin X' => [['gender' => 'X'], 'gender', 'Jenis kelamin tidak valid.'],
            'jenis kelamin larik' => [['gender' => ['L']], 'gender', 'Jenis kelamin tidak valid.'],
            'telepon kosong' => [['phone' => ''], 'phone', 'Nomor telepon wajib diisi.'],
            'telepon larik' => [['phone' => ['0812']], 'phone', 'Nomor telepon tidak valid. Gunakan nomor seluler Indonesia, mis. 081234567890.'],
            'telepon bukan seluler' => [['phone' => '12345'], 'phone', 'Nomor telepon tidak valid. Gunakan nomor seluler Indonesia, mis. 081234567890.'],
            'email kosong' => [['email' => ''], 'email', 'Email wajib diisi.'],
            'email larik' => [['email' => ['x@contoh.test']], 'email', 'Format email tidak valid.'],
            'email tidak valid' => [['email' => 'bukan-email'], 'email', 'Format email tidak valid.'],
            'email terlalu panjang' => [['email' => str_repeat('a', 250).'@x.test'], 'email', 'Email maksimal 255 karakter.'],
            'alamat kosong' => [['address' => ''], 'address', 'Alamat wajib diisi.'],
            'alamat larik' => [['address' => ['x']], 'address', 'Alamat harus berupa teks.'],
            'alamat terlalu pendek' => [['address' => 'pendek'], 'address', 'Alamat minimal 10 karakter.'],
            'alamat terlalu panjang' => [['address' => str_repeat('a', 501)], 'address', 'Alamat maksimal 500 karakter.'],
            'remove_bpjs abc' => [['remove_bpjs' => 'abc'], 'remove_bpjs', 'Pilihan hapus nomor BPJS tidak valid.'],
            'remove_bpjs larik' => [['remove_bpjs' => ['1']], 'remove_bpjs', 'Pilihan hapus nomor BPJS tidak valid.'],
        ];
    }

    /** @param  array<string, mixed>  $override */
    #[DataProvider('invalidInputs')]
    public function test_invalid_input_is_rejected_with_the_documented_message_and_changes_nothing(array $override, string $field, string $message): void
    {
        $user = $this->patient([], ['bpjs_number' => self::BPJS]);
        $profileBefore = $this->profileRow($user->patientProfile);
        $userBefore = $this->userRow($user);

        $this->actingAs($user);
        $this->putProfile($this->validPayload($override))
            ->assertRedirect(route('patient.profile.edit'))
            ->assertSessionHasErrors([$field => $message]);

        $this->assertEquals($profileBefore, $this->profileRow($user->patientProfile));
        $this->assertEquals($userBefore, $this->userRow($user));
        $this->assertSame(0, AuditLog::query()->where('action', 'patient.updated')->count());
    }

    public function test_after_a_validation_error_the_page_keeps_the_typed_values_and_shows_the_summary(): void
    {
        $user = $this->patient();

        $this->actingAs($user);
        $this->putProfile($this->validPayload(['phone' => '12345']))
            ->assertSessionHasErrors('phone');

        $response = $this->get(route('patient.profile.edit'))
            ->assertOk()
            ->assertSee('Periksa kembali isian Anda')
            ->assertSee('Nomor telepon tidak valid. Gunakan nomor seluler Indonesia, mis. 081234567890.')
            ->assertSee('value="Siti Rahma Baru"', false)
            ->assertSee('value="siti.baru@contoh.test"', false)
            ->assertSee('value="12345"', false);
        // Satu ringkasan galat di atas kedua form.
        $this->assertSame(1, substr_count($response->getContent(), 'data-errors-summary'));
    }

    public function test_array_input_does_not_break_the_form_when_it_is_rendered_again(): void
    {
        $user = $this->patient([], ['bpjs_number' => self::BPJS]);

        $this->actingAs($user);
        $this->putProfile($this->validPayload([
            'full_name' => ['x'],
            'birth_date' => ['x'],
            'gender' => ['L'],
            'phone' => ['0812'],
            'email' => ['x@contoh.test'],
            'address' => ['x'],
            'remove_bpjs' => ['1'],
        ]))->assertSessionHasErrors(['full_name', 'birth_date', 'gender', 'phone', 'email', 'address', 'remove_bpjs']);

        $this->get(route('patient.profile.edit'))
            ->assertOk()
            ->assertSee('Periksa kembali isian Anda');
    }

    public function test_the_phone_number_is_stored_normalised(): void
    {
        $user = $this->patient();

        $this->actingAs($user);
        $this->putProfile($this->validPayload(['phone' => ' (0812) 9999.8888 ']))->assertSessionHas('success');

        $this->assertSame('081299998888', $user->patientProfile->fresh()->phone);
        $this->assertSame('081299998888', $user->fresh()->phone);
    }
}
