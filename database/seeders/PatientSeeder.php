<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Enums\BookingChannel;
use App\Models\PatientProfile;
use App\Models\User;
use App\Services\Patients\PatientNumberGenerator;
use App\Services\Settings\SettingsService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Data demo pasien berakun (13 §10.1, F5-R27). Hanya `local`/`testing`; idempoten per email; satu transaksi per pasien.
 *
 * Tujuh akun pasien aktif (tanpa peran) beserta profil kanal `online` P000001 sampai P000007 pada DB baru. Seeder menulis
 * langsung lewat model (BUKAN `PatientRegistrationService`), sehingga tidak ada audit dan tidak ada email selamat datang.
 * Pasien walk-in (P000008 sampai P000013) dan appointment menyusul di fase 6/7.
 *
 * Telepon `0812-0000-00NN` ternormalisasi menjadi `08120000000{n}`; alamat "Jl. Contoh No. {n}, Pamulang, Tangerang
 * Selatan"; `consent_version` dari pengaturan `privacy.consent_version`. Nomor BPJS fiktif 13 digit disimpan terenkripsi
 * dan `bpjs_number_last4` diisi hook `PatientProfile::saving`.
 */
class PatientSeeder extends Seeder
{
    /**
     * Urutan = nomor n (1..7); `bpjs` null bila pasien tidak punya nomor BPJS. Akun pertama adalah akun demo
     * (`DemoSeeder` menampilkannya pada tabel kredensial).
     *
     * @var list<array{name: string, email: string, gender: string, birth_date: string, bpjs: ?string}>
     */
    public const ACCOUNTS = [
        ['name' => 'Anisa Rahmawati', 'email' => 'pasien@unpamhospital.test', 'gender' => 'P', 'birth_date' => '1994-05-12', 'bpjs' => '0009900000011'],
        ['name' => 'Budi Hartanto', 'email' => 'budi.hartanto@contoh.test', 'gender' => 'L', 'birth_date' => '1985-11-03', 'bpjs' => '0009900000022'],
        ['name' => 'Dewi Susilowati', 'email' => 'dewi.susilowati@contoh.test', 'gender' => 'P', 'birth_date' => '1990-07-21', 'bpjs' => null],
        ['name' => 'Fitri Handayani', 'email' => 'fitri.handayani@contoh.test', 'gender' => 'P', 'birth_date' => '1998-01-30', 'bpjs' => '0009900000033'],
        ['name' => 'Gilang Saputra', 'email' => 'gilang.saputra@contoh.test', 'gender' => 'L', 'birth_date' => '2001-09-09', 'bpjs' => null],
        ['name' => 'Kartika Dewi Anggraini', 'email' => 'kartika.anggraini@contoh.test', 'gender' => 'P', 'birth_date' => '1979-12-15', 'bpjs' => null],
        ['name' => 'Mulyani Astuti', 'email' => 'mulyani.astuti@contoh.test', 'gender' => 'P', 'birth_date' => '1958-02-20', 'bpjs' => null],
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('PatientSeeder DILARANG di APP_ENV='.app()->environment().'. Akun pasien contoh berpassword umum.');
        }

        $password = (string) config('app.demo_password');
        $consentVersion = app(SettingsService::class)->string('privacy.consent_version', '1.0');

        foreach (self::ACCOUNTS as $index => $account) {
            if (User::query()->where('email', $account['email'])->exists()) {
                $this->command?->warn("Akun pasien demo {$account['email']} sudah ada, dilewati.");

                continue;
            }

            $number = $index + 1;
            $phone = '08120000000'.$number;
            $address = "Jl. Contoh No. {$number}, Pamulang, Tangerang Selatan";

            DB::transaction(function () use ($account, $password, $consentVersion, $phone, $address): void {
                $user = new User;
                $user->forceFill([
                    'name' => $account['name'],
                    'email' => $account['email'],
                    'phone' => $phone,
                    'password' => $password,
                    'account_type' => AccountType::Patient,
                    'is_active' => true,
                    'must_change_password' => false,
                ])->save();

                $profile = new PatientProfile;
                $profile->fill([
                    'full_name' => $account['name'],
                    'birth_date' => $account['birth_date'],
                    'gender' => $account['gender'],
                    'phone' => $phone,
                    'email' => $account['email'],
                    'address' => $address,
                    'bpjs_number' => $account['bpjs'],
                    'registration_channel' => BookingChannel::Online,
                    'consent_at' => now(),
                    'consent_version' => $consentVersion,
                    'is_active' => true,
                ]);
                $profile->forceFill(['user_id' => $user->id, 'created_by_user_id' => null])->save();

                // Nomor pasien dari id profil, dalam transaksi yang sama (F5-R10).
                $profile->forceFill(['patient_number' => PatientNumberGenerator::format($profile->id)])->save();
            });
        }
    }
}
