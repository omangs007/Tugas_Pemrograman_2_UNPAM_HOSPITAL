<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('DemoSeeder DILARANG di APP_ENV='.app()->environment().'. Data demo memuat akun berpassword umum.');
        }

        if (User::query()->where('email', 'superadmin@unpamhospital.test')->exists()) {
            $this->command?->warn('Data demo sudah ada. Jalankan: php artisan migrate:fresh --seed');

            return;
        }

        $this->command?->warn('MODE DEMO: akun & data contoh dibuat. Jangan gunakan di produksi.');
        config(['mail.default' => 'array']); // surat notifikasi seeding dibuang, bukan ditumpuk di log

        // Seeder data master (fase 2 dst.) ditambahkan ke daftar ini mengikuti urutan 13 §2.1.
        // PatientSeeder (fase 5: 7 akun pasien berakun) berada di antara ContentSeeder dan ContactMessageSeeder;
        // AppointmentSeeder (fase 6/7) disisipkan tepat sesudah PatientSeeder.
        $this->call([
            DemoUserSeeder::class,
            SpecialtySeeder::class,
            ClinicSeeder::class,
            DoctorSeeder::class,
            DoctorScheduleSeeder::class,
            ScheduleExceptionSeeder::class,
            RoomCategorySeeder::class,
            FacilitySeeder::class,
            ContentSeeder::class,
            PatientSeeder::class,
            ContactMessageSeeder::class,
        ]);

        $password = (string) config('app.demo_password');

        $rows = array_map(
            fn (array $account): array => [$account['name'], $account['email'], $account['role'], $password],
            DemoUserSeeder::ACCOUNTS,
        );

        // Satu akun pasien demo pada tabel kredensial; enam akun pasien lain memakai password yang sama (F5-R27).
        $demoPatient = PatientSeeder::ACCOUNTS[0];
        $rows[] = [$demoPatient['name'], $demoPatient['email'], 'Pasien', $password];

        $this->command?->table(['Nama', 'Email', 'Peran', 'Password'], $rows);
        $this->command?->line('Enam akun pasien lain (@contoh.test) memakai password yang sama.');
    }
}
