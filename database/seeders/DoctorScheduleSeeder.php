<?php

namespace Database\Seeders;

use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\User;
use App\Services\Scheduling\PracticeSessionGenerator;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use RuntimeException;

class DoctorScheduleSeeder extends Seeder
{
    /**
     * 13 §7.1, templat mingguan (disalin persis): [slug dokter, hari ISO (1 = Senin), jam mulai, jam selesai, kuota].
     * 51 templat aktif; dr. Eko (nonaktif) memakai satu templat nonaktif (`INACTIVE_ROWS`). Tidak ada hari Minggu dan
     * tidak ada irisan jam pada hari yang sama untuk satu dokter.
     *
     * @var list<array{string, int, string, string, int}>
     */
    public const ROWS = [
        ['raka-pradipta-wibowo', 1, '09:00', '12:00', 15],
        ['raka-pradipta-wibowo', 3, '09:00', '12:00', 15],
        ['raka-pradipta-wibowo', 5, '13:00', '15:00', 10],
        ['sekar-ayuningtyas', 1, '08:00', '11:00', 20],
        ['sekar-ayuningtyas', 3, '08:00', '11:00', 20],
        ['sekar-ayuningtyas', 5, '08:00', '11:00', 20],
        ['sekar-ayuningtyas', 6, '08:00', '10:00', 15],
        ['bagas-setiawan-nugroho', 2, '13:00', '16:00', 20],
        ['bagas-setiawan-nugroho', 4, '13:00', '16:00', 20],
        ['bagas-setiawan-nugroho', 6, '10:00', '12:00', 15],
        ['laras-wulandari', 1, '09:00', '12:00', 20],
        ['laras-wulandari', 2, '09:00', '12:00', 20],
        ['laras-wulandari', 4, '09:00', '12:00', 20],
        ['laras-wulandari', 6, '09:00', '11:00', 15],
        ['dimas-aryaputra', 2, '16:00', '19:00', 15],
        ['dimas-aryaputra', 3, '13:00', '16:00', 20],
        ['dimas-aryaputra', 5, '13:00', '16:00', 20],
        ['nadira-kusumawardani', 1, '13:00', '16:00', 15],
        ['nadira-kusumawardani', 3, '13:00', '16:00', 15],
        ['nadira-kusumawardani', 5, '09:00', '12:00', 15],
        ['hendra-mahardika', 2, '09:00', '12:00', 12],
        ['hendra-mahardika', 4, '09:00', '12:00', 12],
        ['maya-kartika-sari', 1, '16:00', '19:00', 12],
        ['maya-kartika-sari', 4, '16:00', '19:00', 12],
        ['maya-kartika-sari', 6, '09:00', '12:00', 10],
        ['fajar-ramadhan-lubis', 2, '13:00', '16:00', 15],
        ['fajar-ramadhan-lubis', 5, '13:00', '16:00', 15],
        ['intan-permatasari', 2, '08:00', '11:00', 20],
        ['intan-permatasari', 4, '08:00', '11:00', 20],
        ['intan-permatasari', 6, '08:00', '11:00', 15],
        ['yoga-prasetya', 1, '13:00', '16:00', 15],
        ['yoga-prasetya', 3, '09:00', '12:00', 15],
        ['yoga-prasetya', 5, '16:00', '19:00', 15],
        ['citra-anindya-putri', 1, '16:00', '19:00', 20],
        ['citra-anindya-putri', 3, '16:00', '19:00', 20],
        ['citra-anindya-putri', 6, '09:00', '12:00', 20],
        ['arif-budiman-santoso', 2, '16:00', '19:00', 12],
        ['arif-budiman-santoso', 4, '13:00', '16:00', 12],
        ['arif-budiman-santoso', 6, '09:00', '11:00', 10],
        ['rani-oktaviani', 1, '08:00', '11:00', 30],
        ['rani-oktaviani', 2, '08:00', '11:00', 30],
        ['rani-oktaviani', 3, '08:00', '11:00', 30],
        ['rani-oktaviani', 4, '08:00', '11:00', 30],
        ['teguh-wicaksono', 1, '13:00', '16:00', 25],
        ['teguh-wicaksono', 4, '16:00', '19:00', 25],
        ['teguh-wicaksono', 5, '13:00', '16:00', 25],
        ['teguh-wicaksono', 6, '08:00', '11:00', 30],
        ['ayu-larasati-hakim', 1, '09:00', '12:00', 15],
        ['ayu-larasati-hakim', 3, '13:00', '16:00', 15],
        ['ayu-larasati-hakim', 5, '09:00', '12:00', 15],
        ['ayu-larasati-hakim', 6, '09:00', '12:00', 15],
    ];

    /**
     * Templat nonaktif (`is_active = false`): dr. Eko (Bedah Umum, dokter nonaktif), Selasa 13:00-16:00, kuota 12.
     *
     * @var list<array{string, int, string, string, int}>
     */
    public const INACTIVE_ROWS = [
        ['eko-susanto', 2, '13:00', '16:00', 12],
    ];

    /** Akun staf pembuat templat (13 §7.1: `created_by` = Admin Konten Demo). */
    public const CREATOR_EMAIL = 'konten@unpamhospital.test';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('DoctorScheduleSeeder DILARANG di APP_ENV='.app()->environment().'. Seeder jadwal hanya untuk data demo.');
        }

        $today = CarbonImmutable::today();
        $creatorId = User::query()->where('email', self::CREATOR_EMAIL)->value('id');
        $doctors = Doctor::query()->get()->keyBy('slug');

        $rows = array_merge(
            array_map(fn (array $row): array => [...$row, true], self::ROWS),
            array_map(fn (array $row): array => [...$row, false], self::INACTIVE_ROWS),
        );

        foreach ($rows as [$slug, $day, $start, $end, $quota, $active]) {
            $doctor = $doctors->get($slug) ?? throw new RuntimeException("Dokter {$slug} belum ada. Jalankan DoctorSeeder lebih dulu.");

            $exists = DoctorSchedule::query()
                ->where('doctor_id', $doctor->id)
                ->where('day_of_week', $day)
                ->where('start_time', $start.':00')
                ->exists();

            if ($exists) {
                $this->command?->warn("Templat {$slug} hari {$day} {$start} sudah ada, dilewati.");

                continue;
            }

            // Seeder melewati Form Request: valid_from (45 hari lalu) sengaja di masa lalu agar sesi hari ini terbentuk.
            $schedule = new DoctorSchedule([
                'doctor_id' => $doctor->id,
                'clinic_id' => $doctor->clinic_id,
                'day_of_week' => $day,
                'start_time' => $start.':00',
                'end_time' => $end.':00',
                'quota' => $quota,
                'valid_from' => $today->subDays(45)->toDateString(),
                'valid_until' => null,
                'is_active' => $active,
                'notes' => null,
            ]);
            $schedule->forceFill(['created_by' => $creatorId, 'updated_by' => $creatorId]);
            $schedule->save();
        }

        // Sesi hanya untuk hari ini s.d. hari ini + booking.window_days (F3-R7: tanpa sesi lampau; sesi lampau demo
        // dibuat seeder appointment di fase 6/7, bukan generator).
        app(SettingsService::class)->forget();
        $windowDays = max(1, app(SettingsService::class)->bookingRules()->windowDays);
        $created = app(PracticeSessionGenerator::class)->ensureRange($today, $today->addDays($windowDays));

        $this->command?->info("{$created} sesi praktik dibangkitkan untuk {$today->format('d/m/Y')} s.d. {$today->addDays($windowDays)->format('d/m/Y')}.");
    }
}
