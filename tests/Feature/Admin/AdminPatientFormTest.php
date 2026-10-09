<?php

namespace Tests\Feature\Admin;

use App\Enums\ActorType;
use App\Enums\BookingChannel;
use App\Models\AuditLog;
use App\Models\PatientProfile;
use App\Models\User;
use App\Services\Patients\PatientNumberGenerator;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithPatients;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * Tambah dan ubah profil pasien oleh petugas (#37, F7A-R8/R9/R11/R31): WLK-01 lewat HTTP (sukses, persetujuan lisan,
 * profil mirip, telepon, batas nama/alamat, BPJS tidak tersimpan), `kembali` ke form walk-in, ubah profil walk-in dan
 * profil berakun (email hanya-baca), akses (AKS-04, PAS-07), dan SEK-11 (BPJS tidak masuk audit).
 */
class AdminPatientFormTest extends TestCase
{
    use InteractsWithPatients;
    use InteractsWithStaff;
    use RefreshDatabase;

    private const SIMILAR = 'Terdapat profil yang mirip. Pilih profil yang sudah ada atau centang konfirmasi untuk tetap membuat profil baru.';

    private const CONSENT = 'Persetujuan privasi lisan dari pasien wajib dicentang.';

    private const PHONE = 'Nomor telepon tidak valid. Gunakan nomor seluler Indonesia, mis. 081234567890.';

    private User $officer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);
        $this->officer = $this->createStaff('registration-officer');
    }

    // --- pembantu ----------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'Siti Rahma',
            'birth_date' => '1990-03-12',
            'gender' => 'P',
            'phone' => '0812-3456-7890',
            'email' => '',
            'address' => 'Jl. Merpati No. 5, Pamulang',
            'verbal_consent' => '1',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function store(array $overrides = [], ?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->officer)
            ->from(route('admin.patients.create'))
            ->post(route('admin.patients.store'), $this->payload($overrides));
    }

    private function created(string $name = 'Siti Rahma'): PatientProfile
    {
        return PatientProfile::query()->where('full_name', $name)->sole();
    }

    // --- WLK-01: sukses ----------------------------------------------------------------------

    public function test_a_walk_in_profile_is_created_with_the_verbal_consent_wlk_01(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-05 08:00:00'));

        $response = $this->store();

        $profile = $this->created();

        $response->assertRedirect(route('admin.patients.show', $profile))
            ->assertSessionHas('success', 'Profil pasien '.$profile->patient_number.' berhasil dibuat.');

        $this->assertNull($profile->user_id);
        $this->assertSame(BookingChannel::Loket, $profile->registration_channel);
        $this->assertSame($this->officer->id, (int) $profile->created_by_user_id);
        $this->assertSame(PatientNumberGenerator::format($profile->id), $profile->patient_number);
        $this->assertMatchesRegularExpression('/^P\d{6}$/', $profile->patient_number);
        $this->assertSame('P', $profile->gender->value);
        $this->assertSame('1990-03-12', $profile->birth_date->toDateString());
        $this->assertSame('081234567890', $profile->phone);
        $this->assertNull($profile->email);
        $this->assertSame('Jl. Merpati No. 5, Pamulang', $profile->address);
        $this->assertTrue($profile->is_active);
        $this->assertSame('2026-10-05 08:00:00', $profile->consent_at->format('Y-m-d H:i:s'));
        $this->assertSame(app(SettingsService::class)->string('privacy.consent_version', '1.0'), $profile->consent_version);

        $log = AuditLog::query()->where('action', 'patient.created')->sole();

        $this->assertSame(ActorType::Staff, $log->actor_type);
        $this->assertSame($this->officer->id, (int) $log->user_id);
        $this->assertSame('Profil pasien '.$profile->patient_number.' dibuat oleh petugas (walk-in).', $log->description);
        $this->assertSame('loket', $log->new_values['registration_channel']);
        $this->assertSame($profile->patient_number, $log->new_values['patient_number']);
    }

    public function test_a_valid_email_is_stored_lowercase_and_the_profile_stays_without_an_account(): void
    {
        $this->store(['email' => ' Siti.Rahma@Contoh.Test ']);

        $profile = $this->created();

        $this->assertSame('siti.rahma@contoh.test', $profile->email);
        $this->assertNull($profile->user_id);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_the_bpjs_number_is_never_stored_even_when_it_is_posted(): void
    {
        $this->store(['bpjs_number' => '0009900000011', 'remove_bpjs' => '0']);

        $profile = $this->created();

        $this->assertNull($profile->bpjs_number);
        $this->assertNull($profile->bpjs_number_last4);
        $this->assertNull(DB::table('patient_profiles')->where('id', $profile->id)->value('bpjs_number'));
        $this->assertStringNotContainsString('0009900000011', (string) json_encode(AuditLog::query()->get()->toArray()));
    }

    // --- WLK-01: persetujuan lisan -----------------------------------------------------------

    /**
     * @return array<string, array{string|null}>
     */
    public static function missingConsent(): array
    {
        return [
            'tanpa centang' => [null],
            'kosong' => [''],
            'nol' => ['0'],
            'false' => ['false'],
        ];
    }

    #[DataProvider('missingConsent')]
    public function test_without_the_verbal_consent_nothing_is_saved(?string $value): void
    {
        $this->store(['verbal_consent' => $value])
            ->assertRedirect(route('admin.patients.create'))
            ->assertSessionHasErrors(['verbal_consent' => self::CONSENT]);

        $this->assertDatabaseCount('patient_profiles', 0);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'patient.created']);
    }

    // --- BR-102: profil mirip -----------------------------------------------------------------

    private function existingSiti(array $overrides = []): PatientProfile
    {
        return PatientProfile::factory()->create(array_merge([
            'full_name' => 'Siti Rahma',
            'birth_date' => '1990-03-12',
            'phone' => '081299990000',
            'patient_number' => 'P000500',
        ], $overrides));
    }

    public function test_a_profile_with_the_same_name_and_birth_date_must_be_confirmed(): void
    {
        $this->existingSiti();

        $this->store()
            ->assertRedirect(route('admin.patients.create'))
            ->assertSessionHasErrors(['confirm_not_duplicate' => self::SIMILAR]);

        $this->assertDatabaseCount('patient_profiles', 1);
    }

    public function test_a_profile_with_the_same_phone_must_be_confirmed(): void
    {
        $this->existingSiti(['full_name' => 'Orang Lain', 'birth_date' => '1975-01-01', 'phone' => '081234567890']);

        $this->store()->assertSessionHasErrors(['confirm_not_duplicate' => self::SIMILAR]);

        $this->assertDatabaseCount('patient_profiles', 1);
    }

    public function test_the_second_submit_with_the_confirmation_creates_the_new_profile(): void
    {
        $existing = $this->existingSiti();

        $this->store()->assertSessionHasErrors('confirm_not_duplicate');

        $this->store(['confirm_not_duplicate' => '1'])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('patient_profiles', 2);
        $this->assertTrue(PatientProfile::query()->where('full_name', 'Siti Rahma')->where('id', '!=', $existing->id)->exists());
    }

    public function test_a_profile_that_is_not_similar_needs_no_confirmation(): void
    {
        $this->existingSiti(['birth_date' => '1991-03-12', 'phone' => '081299990000']);

        $this->store()->assertSessionHasNoErrors();

        $this->assertDatabaseCount('patient_profiles', 2);
    }

    public function test_an_inactive_profile_is_not_considered_similar(): void
    {
        $this->existingSiti(['is_active' => false]);

        $this->store()->assertSessionHasNoErrors();

        $this->assertDatabaseCount('patient_profiles', 2);
    }

    public function test_the_form_lists_the_similar_profiles_and_offers_the_confirmation_checkbox(): void
    {
        $existing = $this->existingSiti();

        $this->actingAs($this->officer)
            ->followingRedirects()
            ->from(route('admin.patients.create'))
            ->post(route('admin.patients.store'), $this->payload())
            ->assertOk()
            ->assertSee('Profil yang mirip ditemukan')
            ->assertSee('P000500')
            ->assertSee('081299990000')
            ->assertSee('12/03/1990')
            ->assertSee('href="'.route('admin.patients.show', $existing).'"', false)
            ->assertSee('name="confirm_not_duplicate"', false)
            ->assertSee('Tetap buat profil baru')
            ->assertSee(self::SIMILAR)
            ->assertSee('value="Siti Rahma"', false);
    }

    public function test_the_similar_list_offers_a_pick_link_back_to_the_walk_in_form(): void
    {
        $existing = $this->existingSiti();
        $kembali = '/admin/pendaftaran/buat?poli=x';

        $this->actingAs($this->officer)
            ->followingRedirects()
            ->from(route('admin.patients.create', ['kembali' => $kembali]))
            ->post(route('admin.patients.store'), $this->payload(['kembali' => $kembali]))
            ->assertOk()
            ->assertSee('poli=x&amp;pasien='.$existing->id, false);
    }

    // --- V-13: validasi -----------------------------------------------------------------------

    /**
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function invalidInputs(): array
    {
        return [
            'nama kosong' => [['full_name' => ''], 'full_name', 'Nama lengkap wajib diisi.'],
            'nama 2 karakter' => [['full_name' => 'Ab'], 'full_name', 'Nama lengkap minimal 3 karakter.'],
            'nama 101 karakter' => [['full_name' => 'str:101:a'], 'full_name', 'Nama lengkap maksimal 100 karakter.'],
            'nama berisi angka' => [['full_name' => 'Siti 2'], 'full_name', 'Nama lengkap hanya boleh berisi huruf, spasi, titik, apostrof, dan tanda hubung.'],
            'nama larik' => [['full_name' => ['x']], 'full_name', 'Nama lengkap harus berupa teks.'],
            'tanggal lahir kosong' => [['birth_date' => ''], 'birth_date', 'Tanggal lahir wajib diisi.'],
            'tanggal lahir format salah' => [['birth_date' => '12/03/1990'], 'birth_date', 'Format tanggal lahir tidak valid.'],
            'tanggal lahir di masa depan' => [['birth_date' => '2999-01-01'], 'birth_date', 'Tanggal lahir harus sebelum hari ini.'],
            'tanggal lahir lebih dari 120 tahun' => [['birth_date' => '1800-01-01'], 'birth_date', 'Tanggal lahir tidak boleh lebih dari 120 tahun yang lalu.'],
            'jenis kelamin kosong' => [['gender' => ''], 'gender', 'Jenis kelamin wajib dipilih.'],
            'jenis kelamin asing' => [['gender' => 'X'], 'gender', 'Jenis kelamin tidak valid.'],
            'telepon kosong' => [['phone' => ''], 'phone', 'Nomor telepon wajib diisi.'],
            'telepon huruf' => [['phone' => 'abc'], 'phone', self::PHONE],
            'telepon bukan seluler' => [['phone' => '0712345678'], 'phone', self::PHONE],
            'telepon terlalu pendek' => [['phone' => '08123'], 'phone', self::PHONE],
            'telepon larik' => [['phone' => ['0812']], 'phone', self::PHONE],
            'email format salah' => [['email' => 'bukan-email'], 'email', 'Format email tidak valid.'],
            'email 256 karakter' => [['email' => 'str:250:a:@mail.com'], 'email', 'Email maksimal 255 karakter.'],
            'email larik' => [['email' => ['a@b.test']], 'email', 'Format email tidak valid.'],
            'alamat kosong' => [['address' => ''], 'address', 'Alamat wajib diisi.'],
            'alamat 9 karakter' => [['address' => 'Jl. Mawar'], 'address', 'Alamat minimal 10 karakter.'],
            'alamat 501 karakter' => [['address' => 'str:501:x'], 'address', 'Alamat maksimal 500 karakter.'],
            'konfirmasi profil mirip bukan boolean' => [['confirm_not_duplicate' => 'mungkin'], 'confirm_not_duplicate', 'Konfirmasi profil mirip tidak valid.'],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    #[DataProvider('invalidInputs')]
    public function test_invalid_input_is_rejected_with_an_indonesian_message(array $input, string $field, string $message): void
    {
        $input = array_map(function (mixed $value): mixed {
            if (is_string($value) && str_starts_with($value, 'str:')) {
                [, $length, $char, $suffix] = array_pad(explode(':', $value, 4), 4, '');

                return str_repeat($char, (int) $length).$suffix;
            }

            return $value;
        }, $input);

        $this->store($input)
            ->assertRedirect(route('admin.patients.create'))
            ->assertSessionHasErrors([$field => $message]);

        $this->assertDatabaseCount('patient_profiles', 0);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function boundaryInputs(): array
    {
        return [
            'nama 3 karakter, alamat 10 karakter' => [['full_name' => 'Ani', 'address' => 'Jl. Mawar1']],
            'nama 100 karakter, alamat 500 karakter' => [['full_name' => str_repeat('a', 100), 'address' => str_repeat('x', 500)]],
            'telepon dengan +62' => [['phone' => '+62 812-3456-7890']],
            'email 255 karakter' => [['email' => str_repeat('a', 64).'@'.str_repeat('b', 62).'.'.str_repeat('c', 62).'.'.str_repeat('d', 59).'.test']],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    #[DataProvider('boundaryInputs')]
    public function test_values_exactly_on_the_limits_are_accepted(array $input): void
    {
        $this->store($input)->assertSessionHasNoErrors();

        $this->assertDatabaseCount('patient_profiles', 1);
    }

    public function test_the_phone_is_normalised_before_it_is_stored(): void
    {
        $this->store(['phone' => '+62 812-3456-7890']);

        $this->assertSame('+6281234567890', $this->created()->phone);
    }

    // --- F7A-R31: kembali ke form walk-in -----------------------------------------------------

    public function test_a_valid_return_path_sends_the_user_back_to_the_walk_in_form_with_the_new_patient(): void
    {
        $response = $this->store(['kembali' => '/admin/pendaftaran/buat?poli=x']);

        $profile = $this->created();

        $response->assertRedirect('/admin/pendaftaran/buat?poli=x&pasien='.$profile->id)
            ->assertSessionHas('success', 'Profil pasien '.$profile->patient_number.' berhasil dibuat.');
    }

    public function test_an_existing_pasien_query_is_replaced_and_other_query_values_are_kept(): void
    {
        $response = $this->store(['kembali' => '/admin/pendaftaran/buat?pasien=99&dokter=dr-a&tanggal=2026-10-06']);

        $profile = $this->created();

        $response->assertRedirect('/admin/pendaftaran/buat?pasien='.$profile->id.'&dokter=dr-a&tanggal=2026-10-06');
    }

    public function test_a_return_path_without_a_query_gets_the_new_patient_appended(): void
    {
        $response = $this->store(['kembali' => '/admin/pendaftaran/buat']);

        $this->assertSame(
            url('/admin/pendaftaran/buat?pasien='.$this->created()->id),
            $response->headers->get('Location'),
        );
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function hostileReturnPaths(): array
    {
        return [
            'URL eksternal' => ['https://situs-lain.test/admin/pendaftaran/buat'],
            'protokol relatif' => ['//situs-lain.test/admin/pendaftaran/buat'],
            'area pasien' => ['/pasien'],
            'antrean' => ['/admin/antrean'],
            'awalan palsu' => ['/admin/pendaftaran/buatXYZ'],
            'penelusuran direktori' => ['/admin/pendaftaran/buat/../../pasien'],
            'detail pendaftaran' => ['/admin/pendaftaran/UPH-ABCD-0001'],
            'backslash' => ['/admin/pendaftaran/buat\\evil'],
            'skema javascript' => ['javascript:alert(1)'],
            'larik' => [['/admin/pendaftaran/buat']],
        ];
    }

    #[DataProvider('hostileReturnPaths')]
    public function test_a_hostile_return_path_is_ignored_and_the_profile_is_still_created(mixed $kembali): void
    {
        $response = $this->store(['kembali' => $kembali]);

        $profile = $this->created();

        $response->assertRedirect(route('admin.patients.show', $profile))->assertSessionHasNoErrors();
    }

    public function test_the_create_page_carries_only_a_valid_return_path(): void
    {
        $this->actingAs($this->officer);

        $this->get(route('admin.patients.create', ['kembali' => '/admin/pendaftaran/buat?poli=x']))
            ->assertOk()
            ->assertSee('name="kembali" value="/admin/pendaftaran/buat?poli=x"', false)
            ->assertSee('href="'.url('/admin/pendaftaran/buat?poli=x').'"', false);

        foreach (['https://situs-lain.test', '//situs-lain.test', '/pasien', '/admin/pendaftaran/buatXYZ'] as $path) {
            $this->get(route('admin.patients.create', ['kembali' => $path]))
                ->assertOk()
                ->assertDontSee('name="kembali"', false)
                ->assertDontSee('situs-lain.test');
        }

        $this->get('/admin/pasien/buat?kembali[]=x')->assertOk()->assertDontSee('name="kembali"', false);
    }

    // --- tampilan form tambah -----------------------------------------------------------------

    public function test_the_create_page_has_labelled_fields_the_verbatim_consent_and_no_bpjs_or_account_fields(): void
    {
        $this->actingAs($this->officer)
            ->get(route('admin.patients.create'))
            ->assertOk()
            ->assertSee('<li class="breadcrumb-item active" aria-current="page">Tambah Pasien</li>', false)
            ->assertSee('<h1 class="mb-1">Tambah Pasien</h1>', false)
            ->assertSee('Pasien telah diberi tahu mengenai Kebijakan Privasi dan menyetujui pengolahan datanya (persetujuan lisan)')
            ->assertSee('name="verbal_consent"', false)
            ->assertSee('name="full_name"', false)
            ->assertSee('name="birth_date"', false)
            ->assertSee('name="gender"', false)
            ->assertSee('name="phone"', false)
            ->assertSee('name="email"', false)
            ->assertSee('name="address"', false)
            ->assertSee('Simpan Profil')
            ->assertDontSee('name="bpjs_number"', false)
            ->assertDontSee('name="password"', false)
            ->assertDontSee('name="nik"', false);
    }

    public function test_a_failed_submit_renders_the_errors_summary_and_keeps_the_input_without_bpjs(): void
    {
        $this->actingAs($this->officer)
            ->followingRedirects()
            ->from(route('admin.patients.create'))
            ->post(route('admin.patients.store'), $this->payload(['phone' => 'abc', 'bpjs_number' => '0009900000011']))
            ->assertOk()
            ->assertSee('Periksa kembali isian Anda')
            ->assertSee(self::PHONE)
            ->assertSee('value="Siti Rahma"', false)
            ->assertDontSee('0009900000011');
    }

    // --- ubah profil ---------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function updatePayload(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'Siti Rahmawati',
            'birth_date' => '1990-03-13',
            'gender' => 'P',
            'phone' => '0813-1111-2222',
            'email' => 'Siti@Contoh.test',
            'address' => 'Jl. Baru No. 1, Pamulang',
        ], $overrides);
    }

    public function test_the_edit_page_for_a_walk_in_profile_has_an_editable_email_and_no_bpjs_field(): void
    {
        $profile = PatientProfile::factory()->withBpjs('0009900000011')->create(['full_name' => 'Siti Rahma', 'patient_number' => 'P000600', 'birth_date' => '1990-03-12', 'gender' => 'P']);

        $this->actingAs($this->officer)
            ->get(route('admin.patients.edit', $profile))
            ->assertOk()
            ->assertSee('<h1 class="mb-1">Ubah Siti Rahma</h1>', false)
            ->assertSee('<li class="breadcrumb-item active" aria-current="page">Ubah</li>', false)
            ->assertSee('value="Siti Rahma"', false)
            ->assertSee('value="1990-03-12"', false)
            ->assertSee('value="P" checked', false)
            ->assertSee('name="email"', false)
            ->assertSee('Simpan Perubahan')
            ->assertDontSee('name="verbal_consent"', false)
            ->assertDontSee('name="bpjs_number"', false)
            ->assertDontSee('0009900000011');
    }

    public function test_the_edit_page_for_a_linked_profile_shows_the_email_read_only(): void
    {
        $user = $this->createPatient(['email' => 'akun@contoh.test']);

        $this->actingAs($this->officer)
            ->get(route('admin.patients.edit', $user->patientProfile))
            ->assertOk()
            ->assertSee('akun@contoh.test')
            ->assertSee('Email akun tidak dapat diubah dari layar admin.')
            ->assertSee('readonly', false)
            ->assertDontSee('name="email"', false);
    }

    public function test_a_walk_in_profile_is_updated_and_system_fields_cannot_be_changed(): void
    {
        $profile = PatientProfile::factory()->withBpjs('0009900000011')->create([
            'full_name' => 'Siti Rahma',
            'patient_number' => 'P000600',
            'email' => 'lama@contoh.test',
        ]);
        $consentAt = $profile->consent_at->toDateTimeString();

        $this->actingAs($this->officer)
            ->from(route('admin.patients.edit', $profile))
            ->put(route('admin.patients.update', $profile), $this->updatePayload([
                'registration_channel' => 'online',
                'user_id' => 999,
                'patient_number' => 'P999999',
                'is_active' => '0',
                'bpjs_number' => '1111111111111',
                'remove_bpjs' => '1',
                'consent_at' => '2000-01-01 00:00:00',
                'created_by_user_id' => $this->officer->id,
            ]))
            ->assertRedirect(route('admin.patients.show', $profile))
            ->assertSessionHas('success', 'Profil pasien P000600 diperbarui.');

        $fresh = $profile->fresh();

        $this->assertSame('Siti Rahmawati', $fresh->full_name);
        $this->assertSame('1990-03-13', $fresh->birth_date->toDateString());
        $this->assertSame('081311112222', $fresh->phone);
        $this->assertSame('siti@contoh.test', $fresh->email);
        $this->assertSame('Jl. Baru No. 1, Pamulang', $fresh->address);
        $this->assertSame(BookingChannel::Loket, $fresh->registration_channel);
        $this->assertNull($fresh->user_id);
        $this->assertSame('P000600', $fresh->patient_number);
        $this->assertTrue($fresh->is_active);
        $this->assertSame($consentAt, $fresh->consent_at->toDateTimeString());
        $this->assertNull($fresh->created_by_user_id);
        $this->assertSame('0009900000011', $fresh->bpjs_number);
        $this->assertSame('0011', $fresh->bpjs_number_last4);

        $log = AuditLog::query()->where('action', 'patient.updated')->sole();
        $encoded = (string) json_encode($log->toArray());

        $this->assertStringNotContainsString('0009900000011', $encoded);
        $this->assertStringNotContainsString('1111111111111', $encoded);
    }

    public function test_clearing_the_email_of_a_walk_in_profile_stores_null(): void
    {
        $profile = PatientProfile::factory()->create(['email' => 'lama@contoh.test']);

        $this->actingAs($this->officer)
            ->put(route('admin.patients.update', $profile), $this->updatePayload(['email' => '']))
            ->assertSessionHasNoErrors();

        $this->assertNull($profile->fresh()->email);
    }

    public function test_updating_a_linked_profile_keeps_the_email_and_syncs_name_and_phone_to_the_account(): void
    {
        $user = $this->createPatient(
            ['email' => 'akun@contoh.test', 'name' => 'Nama Lama', 'phone' => '081200000010'],
            ['full_name' => 'Nama Lama', 'phone' => '081200000010', 'patient_number' => 'P000601'],
        );
        $profile = $user->patientProfile;

        $this->actingAs($this->officer)
            ->from(route('admin.patients.edit', $profile))
            ->put(route('admin.patients.update', $profile), $this->updatePayload([
                'full_name' => 'Nama Baru Pasien',
                'phone' => '0813 0000 0020',
                'email' => 'lain@contoh.test',
            ]))
            ->assertRedirect(route('admin.patients.show', $profile))
            ->assertSessionHas('success', 'Profil pasien P000601 diperbarui.');

        $profile->refresh();
        $user->refresh();

        $this->assertSame('Nama Baru Pasien', $profile->full_name);
        $this->assertSame('081300000020', $profile->phone);
        $this->assertSame('akun@contoh.test', $profile->email);
        $this->assertSame('Nama Baru Pasien', $user->name);
        $this->assertSame('081300000020', $user->phone);
        $this->assertSame('akun@contoh.test', $user->email);
    }

    public function test_an_invalid_email_is_ignored_for_a_linked_profile_but_rejected_for_a_walk_in(): void
    {
        $user = $this->createPatient(['email' => 'akun@contoh.test']);
        $walkIn = PatientProfile::factory()->create();

        $this->actingAs($this->officer);

        $this->put(route('admin.patients.update', $user->patientProfile), $this->updatePayload(['email' => 'bukan-email']))
            ->assertSessionHasNoErrors();

        $this->from(route('admin.patients.edit', $walkIn))
            ->put(route('admin.patients.update', $walkIn), $this->updatePayload(['email' => 'bukan-email']))
            ->assertRedirect(route('admin.patients.edit', $walkIn))
            ->assertSessionHasErrors(['email' => 'Format email tidak valid.']);
    }

    public function test_an_invalid_update_changes_nothing(): void
    {
        $profile = PatientProfile::factory()->create(['full_name' => 'Siti Rahma', 'phone' => '081200000001']);

        $this->actingAs($this->officer)
            ->from(route('admin.patients.edit', $profile))
            ->put(route('admin.patients.update', $profile), $this->updatePayload(['phone' => 'abc']))
            ->assertRedirect(route('admin.patients.edit', $profile))
            ->assertSessionHasErrors(['phone' => self::PHONE]);

        $this->assertSame('Siti Rahma', $profile->fresh()->full_name);
        $this->assertSame('081200000001', $profile->fresh()->phone);
    }

    public function test_update_and_edit_of_an_unknown_or_malformed_id_answer_404(): void
    {
        $this->actingAs($this->officer);

        $this->get('/admin/pasien/999999/ubah')->assertNotFound();
        $this->get('/admin/pasien/abc/ubah')->assertNotFound();
        $this->put('/admin/pasien/999999', $this->updatePayload())->assertNotFound();
    }

    // --- akses -------------------------------------------------------------------------------

    public function test_admin_konten_cannot_use_any_mutating_screen_aks_04_pas_07(): void
    {
        $profile = PatientProfile::factory()->create(['full_name' => 'Siti Rahma']);
        $contentAdmin = $this->createStaff('content-admin');

        $this->actingAs($contentAdmin);

        $this->get(route('admin.patients.create'))->assertForbidden();
        $this->post(route('admin.patients.store'), $this->payload(['full_name' => 'Pasien Baru']))->assertForbidden();
        $this->get(route('admin.patients.edit', $profile))->assertForbidden();
        $this->put(route('admin.patients.update', $profile), $this->updatePayload())->assertForbidden();
        $this->patch(route('admin.patients.status', $profile), ['is_active' => '0', 'reason' => 'Uji penonaktifan'])->assertForbidden();

        $this->assertDatabaseCount('patient_profiles', 1);
        $this->assertSame('Siti Rahma', $profile->fresh()->full_name);
        $this->assertTrue($profile->fresh()->is_active);
    }

    public function test_patients_and_guests_cannot_use_the_mutating_screens(): void
    {
        $profile = PatientProfile::factory()->create();
        $requests = [
            ['get', route('admin.patients.create')],
            ['post', route('admin.patients.store')],
            ['get', route('admin.patients.edit', $profile)],
            ['put', route('admin.patients.update', $profile)],
            ['patch', route('admin.patients.status', $profile)],
        ];

        $this->actingAs($this->createPatient());

        foreach ($requests as [$method, $url]) {
            $this->{$method}($url)->assertForbidden();
        }

        $this->app['auth']->forgetGuards();

        foreach ($requests as [$method, $url]) {
            $this->{$method}($url)->assertRedirect(route('admin.login'));
        }
    }

    public function test_super_admin_can_create_a_profile(): void
    {
        $this->store(['full_name' => 'Dibuat Super Admin'], $this->createStaff('super-admin'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('patient_profiles', ['full_name' => 'Dibuat Super Admin', 'registration_channel' => 'loket']);
    }

    public function test_the_routes_use_the_indonesian_segments_and_create_is_registered_before_show(): void
    {
        $profile = PatientProfile::factory()->create();

        $this->assertSame(url('/admin/pasien/buat'), route('admin.patients.create'));
        $this->assertSame(url('/admin/pasien/'.$profile->id.'/ubah'), route('admin.patients.edit', $profile));
        $this->assertSame(url('/admin/pasien/'.$profile->id.'/status'), route('admin.patients.status', $profile));

        $names = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route) => (string) $route->getName())
            ->filter(fn (string $name): bool => str_starts_with($name, 'admin.patients.'))
            ->values()
            ->all();

        $this->assertLessThan(array_search('admin.patients.show', $names, true), array_search('admin.patients.create', $names, true));
    }
}
