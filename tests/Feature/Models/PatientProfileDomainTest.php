<?php

namespace Tests\Feature\Models;

use App\Enums\BookingChannel;
use App\Models\PatientProfile;
use App\Models\User;
use App\Services\Patients\PatientNumberGenerator;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithPatients;
use Tests\TestCase;

/**
 * Domain pasien fase 5 (T1): hook last4 BPJS (F5-R11), tampilan tersamar, relasi akun-profil (F5-R12),
 * state factory, dan pembantu tes. SEK-12: nomor BPJS lengkap terenkripsi di DB dan tidak pernah diserialisasi.
 */
class PatientProfileDomainTest extends TestCase
{
    use InteractsWithPatients;
    use RefreshDatabase;

    private const NUMBER = '0009900000011';

    private function storedLast4(PatientProfile $profile): mixed
    {
        return DB::table('patient_profiles')->where('id', $profile->id)->value('bpjs_number_last4');
    }

    private function storedNumber(PatientProfile $profile): mixed
    {
        return DB::table('patient_profiles')->where('id', $profile->id)->value('bpjs_number');
    }

    // --- Hook last4 (F5-R11) ---

    public function test_creating_a_profile_with_a_bpjs_number_fills_last4(): void
    {
        $profile = PatientProfile::factory()->create(['bpjs_number' => self::NUMBER]);

        $this->assertSame('0011', $profile->bpjs_number_last4);
        $this->assertSame('0011', $this->storedLast4($profile));
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function last4Cases(): array
    {
        return [
            'nomor 13 digit' => ['0009900000011', '0011'],
            'tepat empat karakter' => ['1234', '1234'],
            'kurang dari empat karakter' => ['987', '987'],
        ];
    }

    #[DataProvider('last4Cases')]
    public function test_last4_takes_the_last_four_characters(string $number, string $expected): void
    {
        $profile = PatientProfile::factory()->create(['bpjs_number' => $number]);

        $this->assertSame($expected, $this->storedLast4($profile));
    }

    public function test_changing_the_number_updates_last4(): void
    {
        $profile = PatientProfile::factory()->withBpjs()->create();

        $profile->bpjs_number = '0009900000022';
        $profile->save();

        $this->assertSame('0022', $profile->bpjs_number_last4);
        $this->assertSame('0022', $this->storedLast4($profile));
        $this->assertSame('0009900000022', PatientProfile::query()->findOrFail($profile->id)->bpjs_number);
    }

    public function test_clearing_the_number_clears_last4(): void
    {
        $profile = PatientProfile::factory()->withBpjs()->create();

        $profile->bpjs_number = null;
        $profile->save();

        $this->assertNull($this->storedNumber($profile));
        $this->assertNull($this->storedLast4($profile));
        $this->assertNull(PatientProfile::query()->findOrFail($profile->id)->masked_bpjs);
    }

    public function test_saving_other_columns_keeps_the_number_and_last4(): void
    {
        $profile = PatientProfile::factory()->withBpjs()->create();
        $ciphertextBefore = $this->storedNumber($profile);

        $profile->fill(['full_name' => 'Nama Baru'])->save();

        $this->assertSame($ciphertextBefore, $this->storedNumber($profile), 'Ciphertext tidak ditulis ulang.');
        $this->assertSame('0011', $this->storedLast4($profile));
    }

    public function test_reassigning_the_same_number_keeps_last4(): void
    {
        $profile = PatientProfile::factory()->withBpjs()->create();

        $profile->bpjs_number = self::NUMBER;
        $profile->save();

        $this->assertSame('0011', $this->storedLast4($profile));
        $this->assertSame(self::NUMBER, PatientProfile::query()->findOrFail($profile->id)->bpjs_number);
    }

    public function test_profile_without_a_number_has_null_last4(): void
    {
        $profile = PatientProfile::factory()->create();

        $this->assertNull($this->storedNumber($profile));
        $this->assertNull($this->storedLast4($profile));
    }

    public function test_last4_cannot_be_forged_with_force_fill(): void
    {
        $profile = PatientProfile::factory()->create();

        $profile->forceFill(['bpjs_number' => '0009900000022', 'bpjs_number_last4' => '9999'])->save();

        $this->assertSame('0022', $this->storedLast4($profile));
    }

    public function test_last4_is_not_mass_assignable(): void
    {
        $this->expectException(MassAssignmentException::class);

        (new PatientProfile)->fill(['bpjs_number_last4' => '9999']);
    }

    public function test_bpjs_number_is_mass_assignable_and_the_hook_applies(): void
    {
        $profile = PatientProfile::factory()->create();

        $profile->fill(['bpjs_number' => '0009900000033'])->save();

        $this->assertSame('0033', $this->storedLast4($profile));
    }

    // --- Tampilan tersamar ---

    public function test_masked_bpjs_shows_only_the_last_four_digits(): void
    {
        $profile = PatientProfile::factory()->withBpjs()->create();

        $this->assertSame('•••••••••0011', $profile->masked_bpjs);
        $this->assertSame(str_repeat('•', 9).'0011', PatientProfile::query()->findOrFail($profile->id)->masked_bpjs);
        $this->assertStringNotContainsString('00099', $profile->masked_bpjs);
    }

    public function test_masked_bpjs_is_null_without_a_number(): void
    {
        $this->assertNull(PatientProfile::factory()->create()->masked_bpjs);
    }

    public function test_masked_bpjs_is_not_serialized(): void
    {
        $profile = PatientProfile::factory()->withBpjs()->create();
        $fresh = PatientProfile::query()->findOrFail($profile->id);

        $this->assertArrayNotHasKey('masked_bpjs', $fresh->toArray());
        $this->assertStringNotContainsString(self::NUMBER, (string) $fresh->masked_bpjs);
    }

    // --- SEK-12: enkripsi dan $hidden ---

    public function test_bpjs_number_is_stored_encrypted(): void
    {
        $profile = PatientProfile::factory()->withBpjs()->create();

        $raw = (string) $this->storedNumber($profile);

        $this->assertNotSame(self::NUMBER, $raw);
        $this->assertStringNotContainsString(self::NUMBER, $raw);
        $this->assertSame(self::NUMBER, Crypt::decryptString($raw));
        $this->assertSame(self::NUMBER, PatientProfile::query()->findOrFail($profile->id)->bpjs_number);
    }

    public function test_bpjs_number_is_hidden_from_serialization(): void
    {
        $profile = PatientProfile::factory()->withBpjs()->create();
        $fresh = PatientProfile::query()->findOrFail($profile->id);

        $this->assertSame(['bpjs_number'], $fresh->getHidden());
        $this->assertArrayNotHasKey('bpjs_number', $fresh->toArray());
        $this->assertArrayNotHasKey('bpjs_number', $fresh->attributesToArray());
        $this->assertStringNotContainsString(self::NUMBER, $fresh->toJson());
        $this->assertStringNotContainsString(self::NUMBER, (string) json_encode($fresh));
    }

    // --- Factory ---

    public function test_with_bpjs_state_sets_default_and_custom_numbers(): void
    {
        $default = PatientProfile::factory()->withBpjs()->create();
        $custom = PatientProfile::factory()->withBpjs('0009900000044')->create();

        $this->assertSame(self::NUMBER, $default->fresh()->bpjs_number);
        $this->assertSame('0011', $default->fresh()->bpjs_number_last4);
        $this->assertSame('0009900000044', $custom->fresh()->bpjs_number);
        $this->assertSame('0044', $custom->fresh()->bpjs_number_last4);
    }

    // --- Relasi akun-profil (F5-R12, K-07) ---

    public function test_user_has_one_patient_profile(): void
    {
        $user = $this->createPatient();

        $relation = $user->patientProfile();

        $this->assertInstanceOf(HasOne::class, $relation);
        $this->assertSame('user_id', $relation->getForeignKeyName());
        $this->assertInstanceOf(PatientProfile::class, $user->patientProfile);
        $this->assertSame($user->id, $user->patientProfile->user_id);
    }

    public function test_user_without_a_profile_has_a_null_relation(): void
    {
        $staff = User::factory()->staff()->create();
        $bare = User::factory()->patient()->create();

        $this->assertNull($staff->patientProfile);
        $this->assertNull($bare->patientProfile);
    }

    public function test_one_account_has_at_most_one_profile(): void
    {
        $user = $this->createPatient();

        $this->expectException(UniqueConstraintViolationException::class);

        PatientProfile::factory()->create(['user_id' => $user->id]);
    }

    public function test_eager_loading_the_relation_for_many_users_avoids_lazy_loading(): void
    {
        $this->createPatient();
        $this->createPatient();
        $this->createPatient();

        $users = User::query()->patients()->with('patientProfile')->get();

        $this->assertCount(3, $users);

        foreach ($users as $user) {
            $this->assertNotNull($user->patientProfile);
        }
    }

    // --- Pembantu tes ---

    public function test_create_patient_builds_a_consistent_account(): void
    {
        $user = $this->createPatient();
        $profile = $user->patientProfile;

        $this->assertTrue($user->isPatient());
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('Rahasia123', $user->password));
        $this->assertSame($this->patientPassword(), 'Rahasia123');
        $this->assertTrue($user->relationLoaded('patientProfile'));
        $this->assertSame($user->id, $profile->user_id);
        $this->assertSame(PatientNumberGenerator::format($profile->id), $profile->patient_number);
        $this->assertSame($user->email, $profile->email);
        $this->assertSame($user->name, $profile->full_name);
        $this->assertSame('081234567890', $profile->phone);
        $this->assertSame($profile->phone, $user->phone);
        $this->assertSame(BookingChannel::Online, $profile->registration_channel);
        $this->assertNull($profile->created_by_user_id);
        $this->assertTrue($profile->is_active);
    }

    public function test_create_patient_accepts_overrides(): void
    {
        $user = $this->createPatient(
            ['name' => 'Budi Hartanto', 'email' => 'budi@contoh.test', 'password' => 'Lain12345'],
            ['phone' => '081200000009', 'bpjs_number' => self::NUMBER, 'is_active' => false],
        );

        $this->assertTrue(Hash::check('Lain12345', $user->password));
        $this->assertFalse(Hash::check('Rahasia123', $user->password));
        $this->assertSame('budi@contoh.test', $user->email);
        $this->assertSame('Budi Hartanto', $user->patientProfile->full_name);
        $this->assertSame('081200000009', $user->patientProfile->phone);
        $this->assertSame('081200000009', $user->phone);
        $this->assertSame('0011', $user->patientProfile->bpjs_number_last4);
        $this->assertFalse($user->patientProfile->is_active);
    }

    public function test_registration_payload_matches_the_v01_example(): void
    {
        $payload = $this->registrationPayload();

        $this->assertSame([
            'full_name' => 'Siti Rahma',
            'email' => 'siti@contoh.test',
            'phone' => '0812-3456-7890',
            'birth_date' => '1990-03-12',
            'gender' => 'P',
            'address' => 'Jl. Merpati No. 5, Pamulang',
            'password' => 'Rahasia123',
            'password_confirmation' => 'Rahasia123',
            'consent' => '1',
        ], $payload);

        $overridden = $this->registrationPayload(['email' => 'lain@contoh.test', 'consent' => null]);

        $this->assertSame('lain@contoh.test', $overridden['email']);
        $this->assertNull($overridden['consent']);
        $this->assertSame('Siti Rahma', $overridden['full_name']);
    }
}
