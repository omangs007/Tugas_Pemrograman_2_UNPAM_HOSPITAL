<?php

namespace Tests\Feature\Patient;

use App\Enums\ActorType;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithPatients;
use Tests\TestCase;

/**
 * Ganti password pasien (#28, F5-R21): V-07, BR-145, SEK-01, SEK-02, SEK-11. Pola `AdminPasswordChangeTest`; berbeda:
 * sesi lain milik akun dihapus (06 §19), audit berpelaku pasien, redirect ke halaman profil.
 */
class PatientPasswordChangeTest extends TestCase
{
    use InteractsWithPatients;
    use RefreshDatabase;

    private const CURRENT = 'Rahasia123';

    private const NEW_PASSWORD = 'PasswordBaru123';

    private function patient(): User
    {
        return $this->createPatient(['email' => 'pasien@contoh.test']);
    }

    /** @param  array<string, mixed>  $data */
    private function putPassword(array $data): TestResponse
    {
        return $this->from(route('patient.profile.edit'))->put(route('patient.password.update'), $data);
    }

    /** @return array<string, string> */
    private function validPayload(): array
    {
        return [
            'current_password' => self::CURRENT,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sessionRow(string $id, ?int $userId): array
    {
        return [
            'id' => $id,
            'user_id' => $userId,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'uji',
            'payload' => 'x',
            'last_activity' => time(),
        ];
    }

    public function test_the_helper_password_matches_the_documented_constant(): void
    {
        $this->assertSame(self::CURRENT, $this->patientPassword());
    }

    // --- sukses ------------------------------------------------------------------------------

    public function test_a_patient_can_change_the_password_and_stays_logged_in(): void
    {
        $user = $this->patient();

        $this->actingAs($user);
        $this->putPassword($this->validPayload())
            ->assertRedirect(route('patient.profile.edit'))
            ->assertSessionHas('success', 'Password berhasil diganti.')
            ->assertSessionHasNoErrors();

        $fresh = $user->fresh();
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $fresh->password));
        $this->assertFalse(Hash::check(self::CURRENT, $fresh->password));
        $this->assertStringStartsWith('$2y$', DB::table('users')->where('id', $user->id)->value('password'));

        // Sesi sekarang tetap login: halaman terlindungi tetap terbuka dan flash tampil.
        $this->assertAuthenticatedAs($user);
        $this->get(route('patient.profile.edit'))->assertOk()->assertSee('Password berhasil diganti.');
        $this->get(route('patient.dashboard'))->assertOk();
    }

    public function test_the_session_id_is_regenerated_and_the_remember_token_rotates(): void
    {
        $user = $this->patient();
        $rememberBefore = $user->remember_token;

        $this->actingAs($user);
        $this->get(route('patient.profile.edit'));
        $before = session()->getId();

        $this->putPassword($this->validPayload())->assertRedirect(route('patient.profile.edit'));

        $this->assertNotSame($before, session()->getId());
        $rememberAfter = $user->fresh()->remember_token;
        $this->assertNotSame($rememberBefore, $rememberAfter);
        $this->assertSame(60, strlen((string) $rememberAfter));
    }

    public function test_other_sessions_of_the_account_are_deleted_and_other_accounts_are_untouched(): void
    {
        $user = $this->patient();
        $other = $this->createPatient(['email' => 'lain@contoh.test']);

        DB::table('sessions')->insert([
            $this->sessionRow('sesi-lain-1', $user->id),
            $this->sessionRow('sesi-lain-2', $user->id),
            $this->sessionRow('sesi-akun-lain', $other->id),
            $this->sessionRow('sesi-tamu', null),
        ]);

        $this->actingAs($user);
        $this->putPassword($this->validPayload())->assertRedirect(route('patient.profile.edit'));

        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->whereIn('id', ['sesi-lain-1', 'sesi-lain-2'])->count());
        $this->assertEqualsCanonicalizing(['sesi-akun-lain', 'sesi-tamu'], DB::table('sessions')->pluck('id')->all());
    }

    public function test_the_session_cleanup_spares_the_current_session_id(): void
    {
        // Driver `database`: baris sesi sekarang ditulis di akhir permintaan, sehingga dapat dibedakan dari baris palsu.
        // `config()` dipanggil sebelum `actingAs` memulai sesi.
        config(['session.driver' => 'database']);

        $user = $this->patient();
        DB::table('sessions')->insert([
            $this->sessionRow('sesi-lain-1', $user->id),
            $this->sessionRow('sesi-lain-2', $user->id),
        ]);

        $this->actingAs($user);
        $this->putPassword($this->validPayload())->assertRedirect(route('patient.profile.edit'));

        // Hanya baris dengan ID sesi SEKARANG (hasil regenerasi) yang tersisa untuk akun ini.
        $this->assertSame([session()->getId()], DB::table('sessions')->where('user_id', $user->id)->pluck('id')->all());
    }

    public function test_a_password_change_is_audited_without_any_password(): void
    {
        $user = $this->patient();
        $number = $user->patientProfile->patient_number;

        $this->actingAs($user);
        $this->putPassword($this->validPayload())->assertRedirect(route('patient.profile.edit'));

        $log = AuditLog::query()->where('action', 'auth.password_changed')->sole();
        $this->assertSame(ActorType::Patient, $log->actor_type);
        $this->assertEquals($user->id, $log->user_id);
        $this->assertEquals($user->id, $log->auditable_id);
        $this->assertSame(User::class, $log->auditable_type);
        $this->assertNull($log->old_values);
        $this->assertNull($log->new_values);
        $this->assertSame("Pasien {$number} mengganti password akunnya.", $log->description);

        foreach ([self::CURRENT, self::NEW_PASSWORD, 'pasien@contoh.test'] as $secret) {
            $this->assertSame(0, DB::table('audit_logs')
                ->where('old_values', 'like', "%{$secret}%")
                ->orWhere('new_values', 'like', "%{$secret}%")
                ->orWhere('description', 'like', "%{$secret}%")
                ->count(), 'Password dan email tidak boleh ada di audit_logs.');
        }
    }

    public function test_an_account_without_a_profile_can_still_change_the_password(): void
    {
        $account = User::factory()->patient()->create(['email' => 'tanpa-profil@contoh.test', 'password' => self::CURRENT]);

        $this->actingAs($account);
        $this->putPassword($this->validPayload())
            ->assertRedirect(route('patient.profile.edit'))
            ->assertSessionHas('success', 'Password berhasil diganti.');

        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $account->fresh()->password));
        $log = AuditLog::query()->where('action', 'auth.password_changed')->sole();
        $this->assertSame('Pasien - mengganti password akunnya.', $log->description);
    }

    // --- validasi V-07 -----------------------------------------------------------------------

    /**
     * @return array<string, array{array<string, mixed>, string, ?string}>
     */
    public static function invalidPayloads(): array
    {
        $ok = ['current_password' => self::CURRENT, 'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD];

        return [
            'password saat ini salah' => [['current_password' => 'SalahSekali1'] + $ok, 'current_password', 'Password saat ini tidak sesuai.'],
            'password saat ini kosong' => [['current_password' => ''] + $ok, 'current_password', 'Password saat ini wajib diisi.'],
            'password saat ini larik' => [['current_password' => ['x']] + $ok, 'current_password', 'Password saat ini tidak valid.'],
            'sama dengan password lama' => [['password' => self::CURRENT, 'password_confirmation' => self::CURRENT] + $ok, 'password', 'Password baru harus berbeda dari password saat ini.'],
            'tanpa angka' => [['password' => 'Abcdefghij', 'password_confirmation' => 'Abcdefghij'] + $ok, 'password', 'Password harus mengandung angka.'],
            'tanpa huruf' => [['password' => '1234567890', 'password_confirmation' => '1234567890'] + $ok, 'password', 'Password harus mengandung huruf.'],
            'terlalu pendek' => [['password' => 'Ab1', 'password_confirmation' => 'Ab1'] + $ok, 'password', 'Password minimal 8 karakter.'],
            'terlalu panjang' => [['password' => str_repeat('Aa1', 21).'AB', 'password_confirmation' => str_repeat('Aa1', 21).'AB'] + $ok, 'password', 'Password maksimal 64 karakter.'],
            'konfirmasi tidak cocok' => [['password_confirmation' => 'Lain12345'] + $ok, 'password', 'Konfirmasi password tidak cocok.'],
            'password baru kosong' => [['password' => ''] + $ok, 'password', 'Password baru wajib diisi.'],
            'password baru larik' => [['password' => ['x'], 'password_confirmation' => ['x']] + $ok, 'password', null],
            'konfirmasi kosong' => [['password_confirmation' => ''] + $ok, 'password_confirmation', 'Konfirmasi password wajib diisi.'],
            'konfirmasi larik' => [['password_confirmation' => ['x']] + $ok, 'password', null],
        ];
    }

    /** @param  array<string, mixed>  $payload */
    #[DataProvider('invalidPayloads')]
    public function test_invalid_input_is_rejected_with_the_documented_messages_and_changes_nothing(array $payload, string $field, ?string $message): void
    {
        $user = $this->patient();
        $hash = $user->password;
        $remember = $user->remember_token;
        DB::table('sessions')->insert($this->sessionRow('sesi-lain', $user->id));

        $this->actingAs($user);
        $response = $this->putPassword($payload)->assertRedirect(route('patient.profile.edit'));

        if ($message === null) {
            $response->assertSessionHasErrors($field);
        } else {
            $response->assertSessionHasErrors([$field => $message]);
        }

        $fresh = $user->fresh();
        $this->assertSame($hash, $fresh->password);
        $this->assertSame($remember, $fresh->remember_token);
        $this->assertSame(1, DB::table('sessions')->where('id', 'sesi-lain')->count(), 'Sesi lain tidak boleh terhapus bila gagal.');
        $this->assertSame(0, AuditLog::query()->where('action', 'auth.password_changed')->count());
    }

    public function test_password_fields_are_never_flashed_back_into_the_form(): void
    {
        $user = $this->patient();

        $this->actingAs($user);
        $this->putPassword(['current_password' => 'SalahSekali1', 'password' => 'Ab1', 'password_confirmation' => 'Ab1']);

        foreach (['current_password', 'password', 'password_confirmation'] as $field) {
            $this->assertNull(session("_old_input.{$field}"), "{$field} tidak boleh di-flash.");
        }

        $this->get(route('patient.profile.edit'))
            ->assertOk()
            ->assertDontSee('SalahSekali1');
    }

    // --- form dan akses ----------------------------------------------------------------------

    public function test_the_form_carries_a_hidden_username_field_for_password_managers(): void
    {
        $user = $this->patient();

        $this->actingAs($user)->get(route('patient.profile.edit'))
            ->assertOk()
            ->assertSee('<input type="text" name="username" value="pasien@contoh.test" autocomplete="username" readonly hidden>', false)
            ->assertSee('name="current_password"', false)
            ->assertSee('name="password_confirmation"', false);
    }

    public function test_guests_cannot_change_a_password(): void
    {
        $user = $this->patient();
        $hash = $user->password;

        $this->put(route('patient.password.update'), $this->validPayload())->assertRedirect(route('login'));

        $this->assertSame($hash, $user->fresh()->password);
    }
}
