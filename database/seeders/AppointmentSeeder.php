<?php

namespace Database\Seeders;

use App\Enums\AppointmentStatus;
use App\Enums\ExceptionType;
use App\Enums\PaymentType;
use App\Enums\SessionStatus;
use App\Exceptions\BookingException;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\InsurancePartner;
use App\Models\PatientProfile;
use App\Models\PracticeSession;
use App\Models\ScheduleException;
use App\Models\User;
use App\Services\Booking\AppointmentStatusService;
use App\Services\Booking\BookingData;
use App\Services\Booking\BookingService;
use App\Services\Patients\PatientRegistrationService;
use App\Services\Scheduling\PracticeSessionGenerator;
use App\Services\Scheduling\ScheduleChangeService;
use App\Support\Actor;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\Seeder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Data demo pendaftaran (13 §10, F7A-R28): enam profil walk-in (P000008 sampai P000013 pada DB baru), sesi lampau 14 hari,
 * 29 pendaftaran A01 sampai A29, dan pengecualian ketiga yang membuat satu sesi `needs_review`.
 *
 * Semua pendaftaran dan perubahan status lewat service (`BookingService::book()`, `AppointmentStatusService`) di bawah jam
 * semu (`Carbon::setTestNow`, dipulihkan di `finally`), sehingga pencacah sesi, riwayat status, audit, dan notifikasi
 * konsisten (13 §1 butir 3). Sesi lampau dibuat `PracticeSessionGenerator::ensureRange` di bawah jam semu tiap hari lampau,
 * karena generator mengklem rentang ke "hari ini". Hanya `local`/`testing`; berhenti bila sudah ada pendaftaran.
 */
class AppointmentSeeder extends Seeder
{
    public const SUDDEN_LEAVE_REASON = 'Cuti mendadak (contoh)';

    /** Hari lampau yang dibangkitkan sesinya: tepat dua minggu. */
    public const PAST_DAYS = 14;

    /**
     * Profil walk-in menurut urutan nomor pasien 8 sampai 13 (13 §10.1). `key` dipakai internal seeder.
     *
     * @var list<array{key: string, name: string, gender: string, birth_date: string}>
     */
    public const WALK_INS = [
        ['key' => 'eko', 'name' => 'Eko Prasetyo', 'gender' => 'L', 'birth_date' => '1975-03-04'],
        ['key' => 'hesti', 'name' => 'Hesti Purwanti', 'gender' => 'P', 'birth_date' => '1988-08-18'],
        ['key' => 'imam', 'name' => 'Imam Santosa', 'gender' => 'L', 'birth_date' => '1969-06-27'],
        ['key' => 'lukman', 'name' => 'Lukman Fauzi', 'gender' => 'L', 'birth_date' => '1992-10-10'],
        ['key' => 'mulyani', 'name' => 'Mulyani Astuti', 'gender' => 'P', 'birth_date' => '1958-02-20'],
        ['key' => 'nayla', 'name' => 'Nayla Putri Prakoso', 'gender' => 'P', 'birth_date' => '2020-03-18'],
    ];

    /** Kunci internal untuk `PatientSeeder::ACCOUNTS` (urutan sama). */
    private const ONLINE_KEYS = ['anisa', 'budi', 'dewi', 'fitri', 'gilang', 'kartika', 'mulyani_online'];

    private BookingService $booking;

    private AppointmentStatusService $statuses;

    private Actor $officer;

    private Actor $system;

    private CarbonImmutable $today;

    private Closure|CarbonInterface|null $previous = null;

    /** @var Collection<string, Doctor> */
    private Collection $doctors;

    /** @var array<string, PatientProfile> */
    private array $profiles = [];

    private ?int $creatorId = null;

    private ?int $leaveSessionId = null;

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('AppointmentSeeder DILARANG di APP_ENV='.app()->environment().'. Data pendaftaran contoh memakai pasien fiktif dan jam semu.');
        }

        if (Appointment::query()->exists()) {
            $this->command?->warn('Data pendaftaran demo sudah ada, dilewati.');

            return;
        }

        $officer = User::query()->where('email', 'pendaftaran@unpamhospital.test')->first()
            ?? throw new RuntimeException('Akun Petugas Pendaftaran Demo belum ada. Jalankan DemoUserSeeder lebih dulu.');

        $this->booking = app(BookingService::class);
        $this->statuses = app(AppointmentStatusService::class);
        $this->officer = Actor::staff($officer);
        $this->system = Actor::system();
        $this->today = CarbonImmutable::today();
        $this->previous = Carbon::getTestNow();
        $this->doctors = Doctor::query()->get()->keyBy('slug');
        $this->creatorId = User::query()->where('email', DoctorScheduleSeeder::CREATOR_EMAIL)->value('id');

        try {
            $this->seedWalkInProfiles($officer);
            $this->loadProfiles();
            $this->ensurePastSessions();
            $this->seedPast();
            $this->seedToday();
            $this->seedUpcoming();
            $this->seedSuddenLeave();
            $this->at(null);
            $this->markPastNotificationsAsRead();
        } finally {
            Carbon::setTestNow($this->previous);
        }

        $this->command?->line('AppointmentSeeder: '.Appointment::query()->count().' pendaftaran contoh dan '.count(self::WALK_INS).' profil walk-in dibuat.');
    }

    // ------------------------------------------------------------------ profil dan sesi lampau

    /** Enam walk-in lewat createWalkIn (waktu nyata, sebelum jam semu pertama). */
    private function seedWalkInProfiles(User $officer): void
    {
        $service = app(PatientRegistrationService::class);

        foreach (self::WALK_INS as $index => $walkIn) {
            $number = $index + 8;

            $service->createWalkIn([
                'full_name' => $walkIn['name'],
                'birth_date' => $walkIn['birth_date'],
                'gender' => $walkIn['gender'],
                'phone' => '0812000000'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                'email' => null,
                'address' => "Jl. Contoh No. {$number}, Pamulang, Tangerang Selatan",
            ], $officer);
        }
    }

    /** Memuat ulang semua profil dengan relasi `user` (mode ketat tidak mengizinkan lazy loading). */
    private function loadProfiles(): void
    {
        foreach (PatientSeeder::ACCOUNTS as $index => $account) {
            $this->profiles[self::ONLINE_KEYS[$index]] = PatientProfile::query()->with('user')->where('email', $account['email'])->first()
                ?? throw new RuntimeException("Profil pasien {$account['email']} belum ada. Jalankan PatientSeeder lebih dulu.");
        }

        foreach (self::WALK_INS as $walkIn) {
            $this->profiles[$walkIn['key']] = PatientProfile::query()->with('user')
                ->where('registration_channel', 'loket')
                ->where('full_name', $walkIn['name'])
                ->firstOrFail();
        }
    }

    /**
     * Sesi 14 hari terakhir lewat generator yang sama: `ensureRange` mengklem rentang ke "hari ini" (BR-22), jadi jam
     * semu diatur ke awal tiap hari lampau. Panggilan pertama sudah membuat semuanya; sisanya no-op (`insertOrIgnore`).
     */
    private function ensurePastSessions(): void
    {
        $generator = app(PracticeSessionGenerator::class);
        $yesterday = $this->today->subDay();

        for ($offset = self::PAST_DAYS; $offset >= 1; $offset--) {
            $day = $this->today->subDays($offset);

            $this->at($day->startOfDay());
            $generator->ensureRange($day, $yesterday);
        }
    }

    // ------------------------------------------------------------------ skenario

    /** A01 sampai A12 dan penandaan tidak hadir A10 lalu A09. */
    private function seedPast(): void
    {
        $umum = $this->umum();

        // A01 sampai A03 dan A07, A08: pasien online, konfirmasi petugas, check-in, selesai.
        $this->visitOnline('anisa', 'rani-oktaviani', 1, $umum);
        $this->visitOnline('budi', 'sekar-ayuningtyas', 2, $this->bpjs('0009900000022', complaint: 'Kontrol rutin'));
        $this->visitOnline('dewi', 'nadira-kusumawardani', 1, $umum);

        // A04 sampai A06: walk-in loket (langsung confirmed), check-in, selesai.
        $this->visitCounter('eko', 'teguh-wicaksono', 1, $umum);
        $this->visitCounter('nayla', 'laras-wulandari', 1, $this->bpjs('0009900000055', save: true));
        $this->visitCounter('hesti', 'citra-anindya-putri', 1, $umum);

        $this->visitOnline('fitri', 'intan-permatasari', 1, $this->asuransi('asuransi-contoh-sehat', 'POL-CONTOH-001'));
        $this->visitOnline('anisa', 'ayu-larasati-hakim', 2, $umum);

        // A09 (online, menunggu penandaan sistem) dan A10 (loket, menunggu penandaan petugas).
        $a09 = $this->bookFirst($this->profiles['gilang'], $this->pastSessions('arif-budiman-santoso'), fn (PracticeSession $s): CarbonImmutable => $this->dayBefore($s, 10), $umum, false);
        $this->move($a09, AppointmentStatus::Confirmed, $this->officer, $this->dayBefore($a09->practiceSession, 14));
        $a10 = $this->bookFirst($this->profiles['imam'], $this->pastSessions('hendra-mahardika'), fn (PracticeSession $s): CarbonImmutable => $s->starts_at->subMinutes(30), $umum, true);

        // A11: dibatalkan pasien; A12: dibatalkan petugas.
        $a11 = $this->bookFirst($this->profiles['kartika'], $this->pastSessions('maya-kartika-sari'), fn (PracticeSession $s): CarbonImmutable => $this->dayBefore($s, 10), $umum, false);
        $this->move($a11, AppointmentStatus::Cancelled, Actor::patient($this->profiles['kartika']->user), $this->dayBefore($a11->practiceSession, 16), 'Berhalangan hadir');

        $a12 = $this->bookFirst($this->profiles['budi'], $this->pastSessions('yoga-prasetya'), fn (PracticeSession $s): CarbonImmutable => $this->dayBefore($s, 10), $umum, false);
        $this->move($a12, AppointmentStatus::Confirmed, $this->officer, $this->dayBefore($a12->practiceSession, 14));
        $this->move($a12, AppointmentStatus::Cancelled, $this->officer, $this->dayBefore($a12->practiceSession, 15), 'Pasien meminta pembatalan melalui telepon');

        // Tidak hadir: petugas (aksi massal sesi A10) LEBIH DULU, baru sistem (perintah per tanggal A09); kalau terbalik,
        // A10 yang masih confirmed ikut ditandai aktor sistem bila kedua sesi jatuh pada tanggal yang sama.
        $session10 = PracticeSession::query()->findOrFail($a10->practice_session_id);
        $this->at($session10->ends_at->addMinutes(15));
        $this->statuses->markNoShowForSession($session10, $this->officer);

        $session09 = PracticeSession::query()->findOrFail($a09->practice_session_id);
        $this->at($session09->ends_at->addMinutes(10));
        $this->statuses->markNoShowForDate($session09->session_date, $this->system);
    }

    /** A13 sampai A16 pada sesi hari ini (dilewati dengan peringatan bila hari seeding tidak punya sesi). */
    private function seedToday(): void
    {
        $sessions = $this->todaySessions();

        if ($sessions->isEmpty()) {
            $this->command?->warn('Tidak ada sesi hari ini; pendaftaran contoh A13 sampai A16 dilewati.');

            return;
        }

        $umum = $this->umum();
        $online = fn (PracticeSession $s): CarbonImmutable => $this->dayBefore($s, 10);
        $counter = fn (PracticeSession $s): CarbonImmutable => $s->starts_at->subMinutes(30);

        // A13: pending. A14: dikonfirmasi petugas. A15: loket, sudah check-in. A16: loket, confirmed.
        $this->bookFirst($this->profiles['anisa'], $this->todayCandidates($sessions, 1), $online, $umum, false);

        $a14 = $this->bookFirst($this->profiles['budi'], $this->todayCandidates($sessions, 2), $online, $this->bpjs('0009900000022'), false);
        $this->move($a14, AppointmentStatus::Confirmed, $this->officer, $this->dayBefore($a14->practiceSession, 14));

        $a15 = $this->bookFirst($this->profiles['hesti'], $this->todayCandidates($sessions, 3), $counter, $umum, true);
        $this->move($a15, AppointmentStatus::CheckedIn, $this->officer, $a15->practiceSession->starts_at->subMinutes(20));

        $this->bookFirst($this->profiles['lukman'], $this->todayCandidates($sessions, 1), $counter, $umum, true);
    }

    /** A17 sampai A29 pada sesi mendatang dan sesi manual, semuanya pada waktu nyata. */
    private function seedUpcoming(): void
    {
        $umum = $this->umum();
        $real = fn (PracticeSession $s): ?CarbonImmutable => null;

        $this->bookFirst($this->profiles['anisa'], $this->futureSessions('raka-pradipta-wibowo'), $real, $this->bpjs('0009900000011'), false);

        $a18 = $this->bookFirst($this->profiles['budi'], $this->futureSessions('sekar-ayuningtyas'), $real, $umum, false);
        $this->move($a18, AppointmentStatus::Confirmed, $this->officer, null);

        $this->bookFirst($this->profiles['dewi'], $this->futureSessions('maya-kartika-sari'), $real, $this->asuransi('asuransi-contoh-sentosa'), false);

        $a20 = $this->bookFirst($this->profiles['fitri'], $this->futureSessions('nadira-kusumawardani'), $real, $this->bpjs('0009900000033'), false);
        $this->move($a20, AppointmentStatus::Confirmed, $this->officer, null);

        $a21 = $this->bookFirst($this->profiles['gilang'], $this->futureSessions('fajar-ramadhan-lubis'), $real, $umum, false);
        $this->move($a21, AppointmentStatus::Confirmed, $this->officer, null);

        // A22 dibatalkan pasien sebelum A23 memesan sesi yang sama (antrean 1 tidak dipakai ulang; A23 mendapat antrean 2).
        $a22 = $this->bookFirst($this->profiles['kartika'], $this->futureSessions('intan-permatasari'), $real, $umum, false);
        $this->move($a22, AppointmentStatus::Confirmed, $this->officer, null);
        $this->move($a22, AppointmentStatus::Cancelled, Actor::patient($this->profiles['kartika']->user), null, 'Jadwal bentrok dengan pekerjaan');

        $a23 = $this->bookFirst($this->profiles['fitri'], PracticeSession::query()->whereKey($a22->practice_session_id)->get(), $real, $umum, false);
        $this->move($a23, AppointmentStatus::Confirmed, $this->officer, null);

        // A24 dan A25: walk-in loket BPJS; A25 menyimpan nomor BPJS ke profil.
        $this->bookFirst($this->profiles['nayla'], $this->futureSessions('laras-wulandari')->slice(1)->values(), $real, $this->bpjs('0009900000055'), true);
        $this->bookFirst($this->profiles['mulyani'], $this->futureSessions('bagas-setiawan-nugroho'), $real, $this->bpjs('0009900000044', save: true), true);

        // A26 dan A27: sesi pertama Hendra pada atau setelah hari ini + 5 (tanggal pengecualian ketiga).
        $a26 = $this->bookFirst($this->profiles['budi'], $this->futureSessions('hendra-mahardika', 5), $real, $umum, false);
        $this->bookFirst($this->profiles['lukman'], PracticeSession::query()->whereKey($a26->practice_session_id)->get(), $real, $umum, true);
        $this->leaveSessionId = (int) $a26->practice_session_id;

        // A28 dan A29: sesi tambahan manual kuota 2 (menjadi penuh).
        $manual = $this->manualSessions();

        if ($manual->isEmpty()) {
            $this->command?->warn('Sesi tambahan manual dr. Teguh tidak ada; A28 dan A29 dilewati.');

            return;
        }

        $this->bookFirst($this->profiles['dewi'], $manual, $real, $umum, false);
        $this->bookFirst($this->profiles['kartika'], $manual, $real, $umum, false);
    }

    /** Pengecualian ketiga pada tanggal sesi A26/A27: sesi menjadi `needs_review` (menunggu layar 7b). */
    private function seedSuddenLeave(): void
    {
        if ($this->leaveSessionId === null) {
            return;
        }

        $session = PracticeSession::query()->findOrFail($this->leaveSessionId);
        $this->at(null);

        $exception = new ScheduleException([
            'doctor_id' => $session->doctor_id,
            'type' => ExceptionType::DoctorLeave,
            'start_date' => $session->session_date->toDateString(),
            'end_date' => $session->session_date->toDateString(),
            'reason' => self::SUDDEN_LEAVE_REASON,
        ]);
        $exception->forceFill(['created_by' => $this->creatorId]);
        $exception->save();

        app(ScheduleChangeService::class)->applyException($exception, null, $this->system);
    }

    /** Notifikasi pendaftaran lampau ditandai dibaca; yang hari ini dan mendatang tetap belum dibaca. */
    private function markPastNotificationsAsRead(): void
    {
        $past = Appointment::query()
            ->where('session_date', '<', $this->today->toDateString())
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($past === []) {
            return;
        }

        DatabaseNotification::query()->whereNull('read_at')->get()
            ->filter(fn (DatabaseNotification $notification): bool => in_array((int) ($notification->data['appointment_id'] ?? 0), $past, true))
            ->each(fn (DatabaseNotification $notification) => $notification->markAsRead());
    }

    // ------------------------------------------------------------------ alur kunjungan

    /** Online: pending (hari sebelum 10.00), confirmed (hari sebelum 14.00), check-in (start - 20), selesai (start + 40). */
    private function visitOnline(string $profile, string $doctor, int $nth, BookingData $data): Appointment
    {
        $appointment = $this->bookFirst(
            $this->profiles[$profile],
            $this->pastSessions($doctor)->slice($nth - 1)->values(),
            fn (PracticeSession $s): CarbonImmutable => $this->dayBefore($s, 10),
            $data,
            false,
        );
        $session = $appointment->practiceSession;

        $this->move($appointment, AppointmentStatus::Confirmed, $this->officer, $this->dayBefore($session, 14));
        $this->move($appointment, AppointmentStatus::CheckedIn, $this->officer, $session->starts_at->subMinutes(20));
        $this->move($appointment, AppointmentStatus::Completed, $this->officer, $session->starts_at->addMinutes(40));

        return $appointment;
    }

    /** Loket: confirmed saat dibuat (start - 30), check-in (start - 20), selesai (start + 40). */
    private function visitCounter(string $profile, string $doctor, int $nth, BookingData $data): Appointment
    {
        $appointment = $this->bookFirst(
            $this->profiles[$profile],
            $this->pastSessions($doctor)->slice($nth - 1)->values(),
            fn (PracticeSession $s): CarbonImmutable => $s->starts_at->subMinutes(30),
            $data,
            true,
        );
        $session = $appointment->practiceSession;

        $this->move($appointment, AppointmentStatus::CheckedIn, $this->officer, $session->starts_at->subMinutes(20));
        $this->move($appointment, AppointmentStatus::Completed, $this->officer, $session->starts_at->addMinutes(40));

        return $appointment;
    }

    /**
     * Mendaftar pada kandidat pertama yang diterima. Jam semu diatur per kandidat (null = waktu nyata). Kandidat yang
     * ditolak karena bentrok, duplikat, kuota penuh, atau tidak tersedia dilewati; galat lain dilempar.
     *
     * @param  iterable<PracticeSession>  $candidates
     * @param  Closure(PracticeSession): ?CarbonInterface  $moment
     */
    private function bookFirst(PatientProfile $profile, iterable $candidates, Closure $moment, BookingData $data, bool $counter): Appointment
    {
        $actor = $counter ? $this->officer : Actor::patient($profile->user ?? throw new RuntimeException('Pasien online tanpa akun.'));
        $last = null;

        foreach ($candidates as $session) {
            $this->at($moment($session));

            try {
                return $this->booking->book($profile, (int) $session->getKey(), $data, $actor);
            } catch (BookingException $e) {
                if (! in_array($e->errorCode, [
                    BookingException::OVERLAPPING_BOOKING,
                    BookingException::DUPLICATE_BOOKING,
                    BookingException::QUOTA_FULL,
                    BookingException::SESSION_UNAVAILABLE,
                ], true)) {
                    throw $e;
                }

                $last = $e;
            }
        }

        throw new RuntimeException('Tidak ada sesi yang dapat dipakai untuk skenario pendaftaran contoh.', 0, $last);
    }

    /** Satu transisi lewat service pada jam semu `$moment` (null = waktu nyata). */
    private function move(Appointment $appointment, AppointmentStatus $to, Actor $actor, ?CarbonInterface $moment, ?string $reason = null): void
    {
        $this->at($moment);
        $this->statuses->transition($appointment, $to, $actor, $reason);
    }

    // ------------------------------------------------------------------ pemilih sesi

    /** Sesi `open` dokter sebelum hari ini, terbaru dulu: `lampau-N` = irisan mulai indeks N-1. */
    private function pastSessions(string $slug): Collection
    {
        return PracticeSession::query()
            ->where('doctor_id', $this->doctor($slug)->id)
            ->where('status', SessionStatus::Open->value)
            ->where('session_date', '<', $this->today->toDateString())
            ->orderByDesc('session_date')
            ->orderByDesc('start_time')
            ->orderByDesc('id')
            ->get();
    }

    /** Sesi `open` dokter dengan `session_date >= today + $minDays`, terdekat dulu (`depan-N`). */
    private function futureSessions(string $slug, int $minDays = 1): Collection
    {
        return PracticeSession::query()
            ->where('doctor_id', $this->doctor($slug)->id)
            ->where('status', SessionStatus::Open->value)
            ->where('session_date', '>=', $this->today->addDays($minDays)->toDateString())
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->orderBy('id')
            ->get();
    }

    /** Sesi `open` hari ini urut jam mulai, satu per poliklinik (`hari ini-N`). */
    private function todaySessions(): Collection
    {
        return PracticeSession::query()
            ->where('session_date', $this->today->toDateString())
            ->where('status', SessionStatus::Open->value)
            ->orderBy('start_time')
            ->orderBy('id')
            ->get()
            ->unique('clinic_id')
            ->values();
    }

    /** Kandidat `hari ini-N`: mulai dari sesi ke-N lalu sisanya (berputar bila sesi kurang dari tiga). */
    private function todayCandidates(Collection $sessions, int $nth): Collection
    {
        return $sessions->slice($nth - 1)->concat($sessions->slice(0, $nth - 1))->values();
    }

    /** Sesi tambahan manual kuota 2 dr. Teguh (ScheduleExceptionSeeder). */
    private function manualSessions(): Collection
    {
        return PracticeSession::query()
            ->where('doctor_id', $this->doctor('teguh-wicaksono')->id)
            ->where('status_reason', ScheduleExceptionSeeder::EXTRA_REASON)
            ->where('status', SessionStatus::Open->value)
            ->orderBy('session_date')
            ->get();
    }

    private function doctor(string $slug): Doctor
    {
        return $this->doctors->get($slug) ?? throw new RuntimeException("Dokter {$slug} belum ada. Jalankan DoctorSeeder lebih dulu.");
    }

    // ------------------------------------------------------------------ waktu dan data pembayaran

    /** Jam semu: hari sebelum sesi pada `$hour:$minute`. */
    private function dayBefore(PracticeSession $session, int $hour, int $minute = 0): CarbonImmutable
    {
        return $session->starts_at->startOfDay()->subDay()->setTime($hour, $minute);
    }

    /** Mengatur jam semu; null memulihkan jam sebelum seeder (nyata atau beku oleh tes). */
    private function at(?CarbonInterface $moment): void
    {
        Carbon::setTestNow($moment ?? $this->previous);
    }

    private function umum(): BookingData
    {
        return new BookingData(paymentType: PaymentType::Umum);
    }

    private function bpjs(string $number, bool $save = false, ?string $complaint = null): BookingData
    {
        return new BookingData(paymentType: PaymentType::Bpjs, paymentReference: $number, complaint: $complaint, saveBpjs: $save);
    }

    private function asuransi(string $slug, ?string $policy = null): BookingData
    {
        $partnerId = InsurancePartner::query()->where('slug', $slug)->value('id')
            ?? throw new RuntimeException("Mitra asuransi {$slug} belum ada. Jalankan ContentSeeder lebih dulu.");

        return new BookingData(paymentType: PaymentType::Asuransi, paymentReference: $policy, insurancePartnerId: (int) $partnerId);
    }
}
