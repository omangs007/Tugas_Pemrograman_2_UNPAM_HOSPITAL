<?php

namespace Database\Seeders;

use App\Enums\ExceptionType;
use App\Enums\SessionStatus;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\PracticeSession;
use App\Models\ScheduleException;
use App\Models\User;
use App\Services\Scheduling\ScheduleChangeService;
use App\Support\Actor;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ScheduleExceptionSeeder extends Seeder
{
    public const LEAVE_DOCTOR = 'dimas-aryaputra';

    public const LEAVE_REASON = 'Cuti tahunan (contoh)';

    public const HOLIDAY_REASON = 'Contoh libur rumah sakit (bukan tanggal libur nasional)';

    public const CLOSED_DOCTOR = 'citra-anindya-putri';

    public const CLOSED_REASON = 'Ditutup sementara (contoh)';

    public const EXTRA_DOCTOR = 'teguh-wicaksono';

    public const EXTRA_REASON = 'Sesi tambahan (contoh)';

    /**
     * Cadangan hari ini (13 §7.3): bila hari seeding punya kurang dari 3 sesi `open` yang belum berakhir (mis. Minggu),
     * dibuat sesi manual agar skenario "hari ini" selalu punya sesi. [slug dokter, jam mulai, jam selesai].
     *
     * @var list<array{string, string, string}>
     */
    public const TODAY_BACKUP = [
        ['rani-oktaviani', '08:00', '11:00'],
        ['sekar-ayuningtyas', '13:00', '16:00'],
        ['laras-wulandari', '16:00', '19:00'],
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('ScheduleExceptionSeeder DILARANG di APP_ENV='.app()->environment().'. Seeder jadwal hanya untuk data demo.');
        }

        $service = app(ScheduleChangeService::class);
        $actor = Actor::system();
        $today = CarbonImmutable::today();
        $creatorId = User::query()->where('email', DoctorScheduleSeeder::CREATOR_EMAIL)->value('id');
        $doctors = Doctor::query()->get()->keyBy('slug');

        $this->seedLeave($service, $actor, $doctors->get(self::LEAVE_DOCTOR), $today, $creatorId);
        $this->seedHoliday($service, $actor, $today, $creatorId);
        $this->seedClosedSession($service, $actor, $doctors->get(self::CLOSED_DOCTOR), $today);
        $this->seedExtraSession($service, $actor, $doctors->get(self::EXTRA_DOCTOR), $today);
        $this->seedTodayBackup($service, $actor, $doctors, $today);
    }

    /** Pengecualian #1: cuti dr. Dimas, today + 8 s.d. today + 14 (tepat 7 hari, selalu memuat Sel/Rab/Jum). */
    private function seedLeave(ScheduleChangeService $service, Actor $actor, ?Doctor $doctor, CarbonImmutable $today, ?int $creatorId): void
    {
        if ($doctor === null) {
            throw new RuntimeException('Dokter '.self::LEAVE_DOCTOR.' belum ada. Jalankan DoctorSeeder lebih dulu.');
        }

        $this->createException($service, $actor, [
            'doctor_id' => $doctor->id,
            'type' => ExceptionType::DoctorLeave,
            'start_date' => $today->addDays(8)->toDateString(),
            'end_date' => $today->addDays(14)->toDateString(),
            'reason' => self::LEAVE_REASON,
        ], $creatorId);
    }

    /** Pengecualian #2: libur rumah sakit satu hari, tanggal pertama yang bukan Minggu pada atau setelah today + 16. */
    private function seedHoliday(ScheduleChangeService $service, Actor $actor, CarbonImmutable $today, ?int $creatorId): void
    {
        $date = $today->addDays(16);

        if ($date->isSunday()) {
            $date = $date->addDay();
        }

        $this->createException($service, $actor, [
            'doctor_id' => null,
            'type' => ExceptionType::HospitalHoliday,
            'start_date' => $date->toDateString(),
            'end_date' => $date->toDateString(),
            'reason' => self::HOLIDAY_REASON,
        ], $creatorId);
    }

    /**
     * Baris pengecualian lalu `applyException`, sehingga sesi terkait otomatis `cancelled` dengan
     * `schedule_exception_id` terisi. Dilewati bila alasan yang sama sudah ada (idempoten).
     *
     * @param  array<string, mixed>  $attributes
     */
    private function createException(ScheduleChangeService $service, Actor $actor, array $attributes, ?int $creatorId): void
    {
        if (ScheduleException::query()->where('reason', $attributes['reason'])->exists()) {
            $this->command?->warn("Pengecualian \"{$attributes['reason']}\" sudah ada, dilewati.");

            return;
        }

        $exception = new ScheduleException($attributes);
        $exception->forceFill(['created_by' => $creatorId]);
        $exception->save();

        $service->applyException($exception, null, $actor);
    }

    /** Sesi "Ditutup sementara (contoh)": sesi terbuka pertama dr. Citra pada atau setelah today + 3. */
    private function seedClosedSession(ScheduleChangeService $service, Actor $actor, ?Doctor $doctor, CarbonImmutable $today): void
    {
        if ($doctor === null) {
            throw new RuntimeException('Dokter '.self::CLOSED_DOCTOR.' belum ada. Jalankan DoctorSeeder lebih dulu.');
        }

        if (PracticeSession::query()->where('doctor_id', $doctor->id)->where('status_reason', self::CLOSED_REASON)->exists()) {
            $this->command?->warn('Sesi "'.self::CLOSED_REASON.'" sudah ada, dilewati.');

            return;
        }

        $session = PracticeSession::query()
            ->where('doctor_id', $doctor->id)
            ->where('status', SessionStatus::Open->value)
            ->where('session_date', '>=', $today->addDays(3)->toDateString())
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->first();

        if ($session === null) {
            $this->command?->warn('Tidak ada sesi dr. Citra pada atau setelah today + 3; sesi tutup contoh dilewati.');

            return;
        }

        $service->setSessionStatus($session, SessionStatus::Closed, $actor, self::CLOSED_REASON);
    }

    /**
     * Sesi tambahan manual dr. Teguh 08:00-10:00, kuota 2: tanggal pertama pada atau setelah today + 2 yang hari ISO-nya
     * bukan hari templat dr. Teguh (K-5, F3-R28), sehingga tidak pernah bentrok dengan sesi hasil templat.
     */
    private function seedExtraSession(ScheduleChangeService $service, Actor $actor, ?Doctor $doctor, CarbonImmutable $today): void
    {
        if ($doctor === null) {
            throw new RuntimeException('Dokter '.self::EXTRA_DOCTOR.' belum ada. Jalankan DoctorSeeder lebih dulu.');
        }

        if (PracticeSession::query()->where('doctor_id', $doctor->id)->where('status_reason', self::EXTRA_REASON)->exists()) {
            $this->command?->warn('Sesi "'.self::EXTRA_REASON.'" sudah ada, dilewati.');

            return;
        }

        $templateDays = DoctorSchedule::query()->where('doctor_id', $doctor->id)->where('is_active', true)->pluck('day_of_week')->all();
        $date = $today->addDays(2);

        while (in_array($date->isoWeekday(), $templateDays, true)) {
            $date = $date->addDay();
        }

        $session = $service->createManualSession([
            'doctor_id' => $doctor->id,
            'clinic_id' => $doctor->clinic_id,
            'session_date' => $date->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00',
            'quota' => 2,
        ], $actor);

        $session->forceFill(['status_reason' => self::EXTRA_REASON])->save();
    }

    /**
     * Cadangan hari ini (13 §7.3). Sesi yang sudah berakhir pada jam seeding, atau yang bentrok dengan sesi dokter itu
     * hari ini, dilewati (ditolak service dengan ValidationException).
     *
     * @param  Collection<string, Doctor>  $doctors
     */
    private function seedTodayBackup(ScheduleChangeService $service, Actor $actor, Collection $doctors, CarbonImmutable $today): void
    {
        $openToday = PracticeSession::query()
            ->onDate($today)
            ->open()
            ->notEnded()
            ->count();

        if ($openToday >= 3) {
            return;
        }

        foreach (self::TODAY_BACKUP as [$slug, $start, $end]) {
            $doctor = $doctors->get($slug);

            if ($doctor === null) {
                continue;
            }

            try {
                $service->createManualSession([
                    'doctor_id' => $doctor->id,
                    'clinic_id' => $doctor->clinic_id,
                    'session_date' => $today->toDateString(),
                    'start_time' => $start,
                    'end_time' => $end,
                    'quota' => 20,
                ], $actor);
            } catch (ValidationException) {
                $this->command?->warn("Sesi cadangan hari ini {$slug} {$start}-{$end} dilewati.");
            }
        }
    }
}
