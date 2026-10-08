<?php

namespace Tests\Feature\Auth;

use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Rules\IndonesianPhone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithFormRequests;
use Tests\Support\InteractsWithPatients;
use Tests\Support\InteractsWithStaff;
use Tests\TestCase;

/**
 * V-01 (12 §4): validasi registrasi pasien, tanpa HTTP (VAL-02, Review Focus G.2 dan G.5). Setiap aturan diuji dengan
 * pesan persis. Form tamu: pengguna yang dilewatkan ke helper hanyalah akun pasien yang belum disimpan.
 */
class RegisterRequestTest extends TestCase
{
    use InteractsWithFormRequests;
    use InteractsWithPatients;
    use InteractsWithStaff;
    use RefreshDatabase;

    private const CONSENT_MESSAGE = 'Anda harus menyetujui Kebijakan Privasi untuk melanjutkan.';

    private const EMAIL_TAKEN = 'Email ini sudah terdaftar. Silakan masuk atau gunakan email lain.';

    private function guest(): User
    {
        return User::factory()->patient()->make();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, list<string>>
     */
    private function errors(array $overrides = []): array
    {
        return $this->validationErrors(RegisterRequest::class, $this->registrationPayload($overrides), $this->guest());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validated(array $overrides = []): array
    {
        $request = $this->makeFormRequest(RegisterRequest::class, $this->registrationPayload($overrides), $this->guest());
        $request->validateResolved();

        return $request->validated();
    }

    // --- data valid --------------------------------------------------------------------------

    public function test_the_helper_payload_is_valid_and_the_form_is_open_to_guests(): void
    {
        $this->assertSame([], $this->errors());
        $this->assertTrue($this->makeFormRequest(RegisterRequest::class, $this->registrationPayload(), $this->guest())->authorize());
    }

    public function test_only_the_nine_registration_fields_are_validated_and_extra_fields_are_dropped(): void
    {
        $validated = $this->validated([
            'user_id' => 99,
            'patient_number' => 'P000001',
            'registration_channel' => 'loket',
            'is_active' => '0',
            'account_type' => 'staff',
            'nik' => '3173000000000001',
            'bpjs_number' => '0009900000011',
        ]);

        $this->assertEqualsCanonicalizing(
            ['full_name', 'email', 'phone', 'birth_date', 'gender', 'address', 'password', 'password_confirmation', 'consent'],
            array_keys($validated),
        );
    }

    // --- normalisasi (prepareForValidation) --------------------------------------------------

    public function test_text_fields_are_trimmed_email_is_lowercased_and_phone_is_normalised(): void
    {
        $validated = $this->validated([
            'full_name' => '  Siti Rahma  ',
            'email' => '  SITI@Contoh.TEST ',
            'phone' => ' 0812-3456-7890 ',
            'birth_date' => ' 1990-03-12 ',
            'gender' => ' P ',
            'address' => '  Jl. Merpati No. 5, Pamulang  ',
        ]);

        $this->assertSame('Siti Rahma', $validated['full_name']);
        $this->assertSame('siti@contoh.test', $validated['email']);
        $this->assertSame('081234567890', $validated['phone']);
        $this->assertSame('1990-03-12', $validated['birth_date']);
        $this->assertSame('P', $validated['gender']);
        $this->assertSame('Jl. Merpati No. 5, Pamulang', $validated['address']);
    }

    public function test_the_password_is_never_trimmed(): void
    {
        $validated = $this->validated(['password' => ' Rahasia123 ', 'password_confirmation' => ' Rahasia123 ']);

        $this->assertSame(' Rahasia123 ', $validated['password']);
    }

    #[DataProvider('acceptedPhones')]
    public function test_indonesian_mobile_numbers_are_accepted_and_stored_normalised(string $input, string $stored): void
    {
        $this->assertSame([], $this->errors(['phone' => $input]));
        $this->assertSame($stored, $this->validated(['phone' => $input])['phone']);
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function acceptedPhones(): array
    {
        return [
            'dengan tanda hubung' => ['0812-3456-7890', '081234567890'],
            'kode negara dengan spasi' => ['+62 812 3456 7890', '+6281234567890'],
            'kurung dan titik' => ['(0812) 3456.7890', '081234567890'],
            'tanpa plus' => ['6281234567890', '6281234567890'],
            'tanpa pemisah' => ['081234567890', '081234567890'],
        ];
    }

    // --- galat per aturan, pesan persis ------------------------------------------------------

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidCases')]
    public function test_each_rule_reports_its_exact_indonesian_message(array $overrides, string $field, ?string $message): void
    {
        $errors = $this->errors($overrides);

        $this->assertArrayHasKey($field, $errors, "Galat untuk {$field} tidak muncul.");

        if ($message !== null) {
            $this->assertContains($message, $errors[$field]);
        }
    }

    /**
     * Pesan null = hanya memastikan ada galat pada field (nilai larik: isi pesan bukan inti pengujian).
     *
     * @return array<string, array{0: array<string, mixed>, 1: string, 2: ?string}>
     */
    public static function invalidCases(): array
    {
        $personName = 'Nama lengkap hanya boleh berisi huruf, spasi, titik, apostrof, dan tanda hubung.';

        return [
            // nama lengkap
            'nama kosong' => [['full_name' => ''], 'full_name', 'Nama lengkap wajib diisi.'],
            'nama terlalu pendek' => [['full_name' => 'Ab'], 'full_name', 'Nama lengkap minimal 3 karakter.'],
            'nama terlalu panjang' => [['full_name' => str_repeat('a', 101)], 'full_name', 'Nama lengkap maksimal 100 karakter.'],
            'nama berisi angka' => [['full_name' => 'Siti 123'], 'full_name', $personName],
            'nama berisi tag' => [['full_name' => '<b>Siti</b>'], 'full_name', $personName],
            'nama larik' => [['full_name' => ['Siti']], 'full_name', 'Nama lengkap tidak valid.'],
            // email
            'email kosong' => [['email' => ''], 'email', 'Email wajib diisi.'],
            'email bukan email' => [['email' => 'bukan-email'], 'email', 'Format email tidak valid.'],
            'email tanpa domain' => [['email' => 'siti@'], 'email', 'Format email tidak valid.'],
            'email terlalu panjang' => [['email' => str_repeat('a', 250).'@x.test'], 'email', 'Email maksimal 255 karakter.'],
            'email larik' => [['email' => ['siti@contoh.test']], 'email', 'Format email tidak valid.'],
            // telepon
            'telepon kosong' => [['phone' => ''], 'phone', 'Nomor telepon wajib diisi.'],
            'telepon terlalu pendek' => [['phone' => '12345'], 'phone', IndonesianPhone::MESSAGE],
            'telepon rumah' => [['phone' => '021555'], 'phone', IndonesianPhone::MESSAGE],
            'telepon bukan seluler' => [['phone' => '0712345678'], 'phone', IndonesianPhone::MESSAGE],
            'telepon huruf' => [['phone' => 'abc'], 'phone', IndonesianPhone::MESSAGE],
            'telepon larik' => [['phone' => ['0812']], 'phone', IndonesianPhone::MESSAGE],
            // tanggal lahir (kasus tanggal relatif ada di metode tes terpisah)
            'tanggal kosong' => [['birth_date' => ''], 'birth_date', 'Tanggal lahir wajib diisi.'],
            'tanggal format hari-bulan-tahun' => [['birth_date' => '31-12-1990'], 'birth_date', 'Format tanggal lahir tidak valid.'],
            'tanggal format garis miring' => [['birth_date' => '1990/03/12'], 'birth_date', 'Format tanggal lahir tidak valid.'],
            'tanggal tidak ada di kalender' => [['birth_date' => '1990-02-30'], 'birth_date', 'Format tanggal lahir tidak valid.'],
            'tanggal bukan tanggal' => [['birth_date' => 'bukan tanggal'], 'birth_date', 'Format tanggal lahir tidak valid.'],
            'tanggal larik' => [['birth_date' => ['1990-03-12']], 'birth_date', 'Format tanggal lahir tidak valid.'],
            'tanggal masa depan jauh' => [['birth_date' => '2099-01-01'], 'birth_date', 'Tanggal lahir harus sebelum hari ini.'],
            'tanggal terlalu lampau' => [['birth_date' => '1500-01-01'], 'birth_date', 'Tanggal lahir tidak boleh lebih dari 120 tahun yang lalu.'],
            // jenis kelamin
            'gender kosong' => [['gender' => ''], 'gender', 'Jenis kelamin wajib dipilih.'],
            'gender null' => [['gender' => null], 'gender', 'Jenis kelamin wajib dipilih.'],
            'gender liar' => [['gender' => 'X'], 'gender', 'Jenis kelamin tidak valid.'],
            'gender huruf kecil' => [['gender' => 'l'], 'gender', 'Jenis kelamin tidak valid.'],
            'gender larik' => [['gender' => ['P']], 'gender', 'Jenis kelamin tidak valid.'],
            // alamat
            'alamat kosong' => [['address' => ''], 'address', 'Alamat wajib diisi.'],
            'alamat terlalu pendek' => [['address' => 'Jl. Satu'], 'address', 'Alamat minimal 10 karakter.'],
            'alamat terlalu panjang' => [['address' => str_repeat('a', 501)], 'address', 'Alamat maksimal 500 karakter.'],
            'alamat larik' => [['address' => ['Jl. Merpati No. 5']], 'address', 'Alamat tidak valid.'],
            // password
            'password kosong' => [['password' => '', 'password_confirmation' => ''], 'password', 'Password wajib diisi.'],
            'password terlalu pendek' => [['password' => 'Ab1', 'password_confirmation' => 'Ab1'], 'password', 'Password minimal 8 karakter.'],
            'password tanpa angka' => [['password' => 'Abcdefghij', 'password_confirmation' => 'Abcdefghij'], 'password', 'Password harus mengandung angka.'],
            'password tanpa huruf' => [['password' => '1234567890', 'password_confirmation' => '1234567890'], 'password', 'Password harus mengandung huruf.'],
            'password terlalu panjang' => [['password' => str_repeat('Ab1', 22), 'password_confirmation' => str_repeat('Ab1', 22)], 'password', 'Password maksimal 64 karakter.'],
            'konfirmasi tidak cocok' => [['password' => 'Rahasia123', 'password_confirmation' => 'Rahasia124'], 'password', 'Konfirmasi password tidak cocok.'],
            'password larik' => [['password' => ['Rahasia123'], 'password_confirmation' => ['Rahasia123']], 'password', null],
            'konfirmasi kosong' => [['password_confirmation' => ''], 'password_confirmation', 'Konfirmasi password wajib diisi.'],
            // persetujuan
            'persetujuan tidak dicentang' => [['consent' => null], 'consent', self::CONSENT_MESSAGE],
            'persetujuan kosong' => [['consent' => ''], 'consent', self::CONSENT_MESSAGE],
            'persetujuan nol' => [['consent' => '0'], 'consent', self::CONSENT_MESSAGE],
            'persetujuan teks bebas' => [['consent' => 'tidak'], 'consent', self::CONSENT_MESSAGE],
            'persetujuan larik' => [['consent' => ['1']], 'consent', self::CONSENT_MESSAGE],
        ];
    }

    public function test_an_empty_submission_reports_every_required_field(): void
    {
        $errors = $this->validationErrors(RegisterRequest::class, [], $this->guest());

        $this->assertEqualsCanonicalizing(
            ['full_name', 'email', 'phone', 'birth_date', 'gender', 'address', 'password', 'password_confirmation', 'consent'],
            array_keys($errors),
        );
        $this->assertSame(['Nama lengkap wajib diisi.'], $errors['full_name']);
        $this->assertSame(['Email wajib diisi.'], $errors['email']);
        $this->assertSame(['Nomor telepon wajib diisi.'], $errors['phone']);
        $this->assertSame(['Tanggal lahir wajib diisi.'], $errors['birth_date']);
        $this->assertSame(['Jenis kelamin wajib dipilih.'], $errors['gender']);
        $this->assertSame(['Alamat wajib diisi.'], $errors['address']);
        $this->assertSame(['Password wajib diisi.'], $errors['password']);
        $this->assertSame(['Konfirmasi password wajib diisi.'], $errors['password_confirmation']);
        $this->assertSame([self::CONSENT_MESSAGE], $errors['consent']);
    }

    public function test_a_checked_consent_value_of_one_passes(): void
    {
        $this->assertArrayNotHasKey('consent', $this->errors(['consent' => '1']));
    }

    // --- tanggal lahir relatif ---------------------------------------------------------------

    public function test_a_birth_date_of_today_or_tomorrow_is_rejected(): void
    {
        $this->assertContains('Tanggal lahir harus sebelum hari ini.', $this->errors(['birth_date' => now()->toDateString()])['birth_date']);
        $this->assertContains('Tanggal lahir harus sebelum hari ini.', $this->errors(['birth_date' => now()->addDay()->toDateString()])['birth_date']);
    }

    public function test_a_birth_date_of_yesterday_is_accepted(): void
    {
        $this->assertArrayNotHasKey('birth_date', $this->errors(['birth_date' => now()->subDay()->toDateString()]));
    }

    public function test_a_birth_date_over_one_hundred_and_twenty_years_ago_is_rejected_and_one_hundred_and_nineteen_is_accepted(): void
    {
        $this->assertContains(
            'Tanggal lahir tidak boleh lebih dari 120 tahun yang lalu.',
            $this->errors(['birth_date' => now()->subYears(121)->toDateString()])['birth_date'],
        );
        $this->assertArrayNotHasKey('birth_date', $this->errors(['birth_date' => now()->subYears(119)->toDateString()]));
    }

    // --- email unik (Review Focus G.5) -------------------------------------------------------

    public function test_a_taken_email_is_rejected_for_patient_and_staff_accounts(): void
    {
        $this->createPatient(['email' => 'siti@contoh.test']);
        $this->createStaff('content-admin', ['email' => 'staf@contoh.test']);

        $this->assertContains(self::EMAIL_TAKEN, $this->errors(['email' => 'siti@contoh.test'])['email']);
        $this->assertContains(self::EMAIL_TAKEN, $this->errors(['email' => 'staf@contoh.test'])['email']);
        $this->assertArrayNotHasKey('email', $this->errors(['email' => 'lain@contoh.test']));
    }

    public function test_a_taken_email_is_rejected_regardless_of_letter_case_and_spacing(): void
    {
        $this->createPatient(['email' => 'siti@contoh.test']);

        foreach (['Siti@Contoh.test', 'SITI@CONTOH.TEST', '  siti@contoh.test  '] as $variant) {
            $this->assertContains(self::EMAIL_TAKEN, $this->errors(['email' => $variant])['email'], $variant);
        }
    }
}
