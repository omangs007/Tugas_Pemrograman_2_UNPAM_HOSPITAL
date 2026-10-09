<?php

namespace Tests\Feature\Seeding;

use App\Enums\ActorType;
use App\Enums\AppointmentStatus;
use App\Enums\BookingChannel;
use App\Enums\PaymentType;
use App\Enums\SessionStatus;
use App\Models\Appointment;
use App\Models\PatientProfile;
use App\Models\PracticeSession;
use App\Models\ScheduleException;
use App\Models\User;
use Database\Seeders\AppointmentSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * AppointmentSeeder (13 §10, F7A-R28): 6 profil walk-in, sesi lampau lewat generator di bawah jam semu, 29 pendaftaran
 * A01 sampai A29 lewat BookingService dan AppointmentStatusService, pengecualian ketiga (`needs_review`), dan notifikasi
 * lampau dibaca. Tes skenario membekukan Senin 5 Oktober 2026 07.00 agar sesi hari ini tersedia (A13 sampai A16).
 */
class AppointmentSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public'); // DatabaseSeeder membuat 83 gambar contoh.
    }

    /** Satu pendaftaran menurut nama pasien, slug dokter, dan status akhir (A-kode 13 §10.3 tidak disimpan). */
    private function appointmentOf(string $patientName, string $doctorSlug, string $status): Appointment
    {
        return Appointment::query()
            ->with(['patientProfile', 'doctor', 'practiceSession', 'statusHistories', 'insurancePartner'])
            ->where('status', $status)
            ->whereHas('patientProfile', fn (Builder $query): Builder => $query->where('full_name', $patientName))
            ->whereHas('doctor', fn (Builder $query): Builder => $query->where('slug', $doctorSlug))
            ->sole();
    }

    /**
     * @param  list<string>  $statuses
     * @param  list<string>  $actors
     */
    private function assertHistory(Appointment $appointment, array $statuses, array $actors, string $label): void
    {
        $this->assertSame($statuses, $appointment->statusHistories->map(fn ($history): string => $history->to_status->value)->all(), "{$label}: status riwayat");
        $this->assertSame($actors, $appointment->statusHistories->map(fn ($history): string => $history->actor_type->value)->all(), "{$label}: pelaku riwayat");
    }

    public function test_the_seeder_builds_the_29_scenarios_of_13_section_10_3_through_the_services(): void
    {
        $this->travelTo('2026-10-05 07:00:00'); // Senin
        $this->seed(DatabaseSeeder::class);

        $officer = User::query()->where('email', 'pendaftaran@unpamhospital.test')->firstOrFail();

        $this->assertSame('2026-10-05 07:00:00', now()->format('Y-m-d H:i:s'), 'Jam beku tes harus dipulihkan seeder.');
        $this->assertSame(29, Appointment::query()->count());
        $this->assertSame(102, PracticeSession::query()->where('session_date', '<', '2026-10-05')->count(), '14 hari lampau = 2 minggu x 51 templat aktif.');

        // Enam walk-in lewat createWalkIn (13 §10.1).
        $walkIns = PatientProfile::query()->where('registration_channel', BookingChannel::Loket->value)->orderBy('id')->get();
        $this->assertCount(6, $walkIns);

        foreach (AppointmentSeeder::WALK_INS as $index => $row) {
            $number = $index + 8;
            $profile = $walkIns->firstWhere('full_name', $row['name']);

            $this->assertNotNull($profile, $row['name']);
            $this->assertNull($profile->user_id, $row['name']);
            $this->assertSame((int) $officer->id, (int) $profile->created_by_user_id, $row['name']);
            $this->assertSame('0812000000'.str_pad((string) $number, 2, '0', STR_PAD_LEFT), $profile->phone, $row['name']);
            $this->assertSame("Jl. Contoh No. {$number}, Pamulang, Tangerang Selatan", $profile->address, $row['name']);
            $this->assertTrue($profile->is_active, $row['name']);
            $this->assertNotNull($profile->consent_at, $row['name']);
            $this->assertNotNull($profile->patient_number, $row['name']);
        }

        $this->assertSame('0044', $walkIns->firstWhere('full_name', 'Mulyani Astuti')->bpjs_number_last4);
        $this->assertSame('0055', $walkIns->firstWhere('full_name', 'Nayla Putri Prakoso')->bpjs_number_last4);
        $this->assertSame(2, $walkIns->whereNotNull('bpjs_number_last4')->count(), 'Hanya P000012 dan P000013 yang menyimpan BPJS.');

        // Lampau: selesai, dengan jam semu (A01) dan pelaku riwayat.
        $a01 = $this->appointmentOf('Anisa Rahmawati', 'rani-oktaviani', 'completed');
        $this->assertHistory($a01, ['pending', 'confirmed', 'checked_in', 'completed'], ['patient', 'staff', 'staff', 'staff'], 'A01');
        $session = $a01->practiceSession;
        $day = $session->starts_at->startOfDay();
        $this->assertSame([
            $day->subDay()->setTime(10, 0)->format('Y-m-d H:i'),
            $day->subDay()->setTime(14, 0)->format('Y-m-d H:i'),
            $session->starts_at->subMinutes(20)->format('Y-m-d H:i'),
            $session->starts_at->addMinutes(40)->format('Y-m-d H:i'),
        ], $a01->statusHistories->map(fn ($history): string => $history->created_at->format('Y-m-d H:i'))->all(), 'A01: jam semu');
        $this->assertTrue($session->session_date->lt(Carbon::parse('2026-10-05')), 'A01 pada sesi lampau.');

        $a02 = $this->appointmentOf('Budi Hartanto', 'sekar-ayuningtyas', 'completed');
        $this->assertSame(PaymentType::Bpjs, $a02->payment_type);
        $this->assertSame('0022', $a02->payment_reference_last4);
        $this->assertSame('Kontrol rutin', $a02->complaint);
        $this->assertSame(1, Appointment::query()->whereNotNull('complaint')->count(), 'Keluhan hanya pada A02.');

        $a04 = $this->appointmentOf('Eko Prasetyo', 'teguh-wicaksono', 'completed');
        $this->assertSame(BookingChannel::Loket, $a04->channel);
        $this->assertHistory($a04, ['confirmed', 'checked_in', 'completed'], ['staff', 'staff', 'staff'], 'A04');

        $a07 = $this->appointmentOf('Fitri Handayani', 'intan-permatasari', 'completed');
        $this->assertSame('Asuransi Contoh Sehat', $a07->insurancePartner->name);
        $this->assertSame('POL-CONTOH-001', $a07->payment_reference);

        $this->assertSame(8, Appointment::query()->where('status', 'completed')->count());

        // Tidak hadir: A09 oleh sistem (markNoShowForDate), A10 oleh petugas (markNoShowForSession).
        $a09 = $this->appointmentOf('Gilang Saputra', 'arif-budiman-santoso', 'no_show');
        $this->assertHistory($a09, ['pending', 'confirmed', 'no_show'], ['patient', 'staff', 'system'], 'A09');
        $this->assertNotNull($a09->no_show_at);

        $a10 = $this->appointmentOf('Imam Santosa', 'hendra-mahardika', 'no_show');
        $this->assertHistory($a10, ['confirmed', 'no_show'], ['staff', 'staff'], 'A10');
        $this->assertSame((int) $officer->id, (int) $a10->statusHistories->last()->actor_user_id);

        // Batal: A11 pasien, A12 petugas, A22 pasien.
        $a11 = $this->appointmentOf('Kartika Dewi Anggraini', 'maya-kartika-sari', 'cancelled');
        $this->assertHistory($a11, ['pending', 'cancelled'], ['patient', 'patient'], 'A11');
        $this->assertSame(ActorType::Patient, $a11->cancelled_by_type);
        $this->assertSame('Berhalangan hadir', $a11->cancel_reason);

        $a12 = $this->appointmentOf('Budi Hartanto', 'yoga-prasetya', 'cancelled');
        $this->assertHistory($a12, ['pending', 'confirmed', 'cancelled'], ['patient', 'staff', 'staff'], 'A12');
        $this->assertSame(ActorType::Staff, $a12->cancelled_by_type);
        $this->assertSame('Pasien meminta pembatalan melalui telepon', $a12->cancel_reason);

        // A22 dibatalkan lalu A23 memesan sesi yang sama: antrean 1 tidak dipakai ulang.
        $a22 = $this->appointmentOf('Kartika Dewi Anggraini', 'intan-permatasari', 'cancelled');
        $a23 = $this->appointmentOf('Fitri Handayani', 'intan-permatasari', 'confirmed');
        $this->assertHistory($a22, ['pending', 'confirmed', 'cancelled'], ['patient', 'staff', 'patient'], 'A22');
        $this->assertSame('Jadwal bentrok dengan pekerjaan', $a22->cancel_reason);
        $this->assertHistory($a23, ['pending', 'confirmed'], ['patient', 'staff'], 'A23');
        $this->assertSame($a22->practice_session_id, $a23->practice_session_id);
        $this->assertSame([1, 2], [(int) $a22->queue_number, (int) $a23->queue_number]);
        $shared = PracticeSession::query()->findOrFail($a22->practice_session_id);
        $this->assertSame([1, 2], [(int) $shared->booked_count, (int) $shared->last_queue_number]);

        // Hari ini (Senin): A13 pending, A14 confirmed (bpjs), A15 checked_in, A16 confirmed; semuanya pada sesi hari ini.
        $today = Appointment::query()->where('session_date', '2026-10-05')->get();
        $this->assertCount(4, $today);
        $this->assertSame(['checked_in' => 1, 'confirmed' => 2, 'pending' => 1], $today->countBy(fn (Appointment $appointment): string => $appointment->status->value)->sortKeys()->all());
        $a15 = Appointment::query()->with('statusHistories')->where('status', 'checked_in')->sole();
        $this->assertHistory($a15, ['confirmed', 'checked_in'], ['staff', 'staff'], 'A15');

        // Mendatang: A25 walk-in BPJS, A28 dan A29 mengisi sesi manual hingga penuh.
        $a25 = $this->appointmentOf('Mulyani Astuti', 'bagas-setiawan-nugroho', 'confirmed');
        $this->assertSame(BookingChannel::Loket, $a25->channel);
        $this->assertNull($a25->patientProfile->user_id);
        $this->assertSame('0044', $a25->payment_reference_last4);

        $a19 = $this->appointmentOf('Dewi Susilowati', 'maya-kartika-sari', 'pending');
        $this->assertSame('Asuransi Contoh Sentosa', $a19->insurancePartner->name);

        $a28 = $this->appointmentOf('Dewi Susilowati', 'teguh-wicaksono', 'pending');
        $a29 = $this->appointmentOf('Kartika Dewi Anggraini', 'teguh-wicaksono', 'pending');
        $manual = PracticeSession::query()->findOrFail($a28->practice_session_id);
        $this->assertSame($a28->practice_session_id, $a29->practice_session_id);
        $this->assertNull($manual->doctor_schedule_id, 'A28 dan A29 memesan sesi tambahan manual.');
        $this->assertSame([2, 2, SessionStatus::Open], [(int) $manual->quota, (int) $manual->booked_count, $manual->status], 'Sesi manual menjadi penuh.');

        // Jumlah per jenis pembayaran: umum 20, BPJS 7, asuransi 2.
        $this->assertSame(
            ['asuransi' => 2, 'bpjs' => 7, 'umum' => 20],
            Appointment::query()->get()->countBy(fn (Appointment $appointment): string => $appointment->payment_type->value)->sortKeys()->all(),
        );

        // Pengecualian ketiga: sesi A26/A27 menjadi needs_review, appointment tetap aktif.
        $a26 = $this->appointmentOf('Budi Hartanto', 'hendra-mahardika', 'pending');
        $a27 = $this->appointmentOf('Lukman Fauzi', 'hendra-mahardika', 'confirmed');
        $this->assertSame($a26->practice_session_id, $a27->practice_session_id);

        $review = PracticeSession::query()->findOrFail($a26->practice_session_id);
        $exception = ScheduleException::query()->where('reason', AppointmentSeeder::SUDDEN_LEAVE_REASON)->sole();

        $this->assertSame(SessionStatus::NeedsReview, $review->status);
        $this->assertSame(2, (int) $review->booked_count);
        $this->assertSame('exception', $review->pending_change['source']);
        $this->assertSame((int) $exception->id, (int) $review->pending_change['exception_id']);
        $this->assertSame((int) $exception->id, (int) $review->schedule_exception_id);
        $this->assertSame($review->session_date->toDateString(), $exception->start_date->toDateString());
        $this->assertSame($review->session_date->toDateString(), $exception->end_date->toDateString());
        $this->assertSame(1, PracticeSession::query()->where('status', SessionStatus::NeedsReview->value)->count());

        // Notifikasi: pendaftaran lampau dibaca, yang mendatang masih belum dibaca; walk-in tidak punya notifikasi sama sekali.
        $past = Appointment::query()->where('session_date', '<', '2026-10-05')->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $this->assertCount(12, $past);
        $notifications = DatabaseNotification::query()->get();
        $ofPast = $notifications->filter(fn (DatabaseNotification $notification): bool => in_array((int) ($notification->data['appointment_id'] ?? 0), $past, true));

        $this->assertNotEmpty($ofPast, 'Pendaftaran lampau punya notifikasi.');
        $this->assertTrue($ofPast->every(fn (DatabaseNotification $notification): bool => $notification->read_at !== null), 'Notifikasi pendaftaran lampau ditandai dibaca.');
        $this->assertTrue($notifications->diff($ofPast)->contains(fn (DatabaseNotification $notification): bool => $notification->read_at === null), 'Notifikasi hari ini dan mendatang tetap belum dibaca.');
        $this->assertSame(0, $notifications->where('notifiable_type', (new PatientProfile)->getMorphClass())->count());
    }

    public function test_the_seeder_works_on_the_real_clock_and_leaves_no_frozen_time_behind(): void
    {
        Carbon::setTestNow(null);

        $this->seed(DatabaseSeeder::class);

        $this->assertFalse(Carbon::hasTestNow(), 'Seeder harus memulihkan keadaan tanpa jam beku.');
        $this->assertSame(6, PatientProfile::query()->where('registration_channel', BookingChannel::Loket->value)->count());
        $this->assertGreaterThanOrEqual(25, Appointment::query()->count(), 'Paling banyak A13 sampai A16 dilewati (hari seeding tanpa sesi).');
        $this->assertLessThanOrEqual(29, Appointment::query()->count());
    }

    /**
     * Banyak tes lama menjalankan DatabaseSeeder pada jam nyata, jadi seeder tidak boleh gagal pada hari atau jam
     * mana pun. Minggu 4 sampai Sabtu 10 Oktober 2026 pukul 07.00, ditambah dua malam (Minggu malam tanpa sesi hari
     * ini; Rabu malam dengan semua sesi hari ini sudah berakhir).
     *
     * @return array<string, array{string}>
     */
    public static function seedingMoments(): array
    {
        return [
            'minggu pagi' => ['2026-10-04 07:00:00'],
            'senin pagi' => ['2026-10-05 07:00:00'],
            'selasa pagi' => ['2026-10-06 07:00:00'],
            'rabu pagi' => ['2026-10-07 07:00:00'],
            'kamis pagi' => ['2026-10-08 07:00:00'],
            'jumat pagi' => ['2026-10-09 07:00:00'],
            'sabtu pagi' => ['2026-10-10 07:00:00'],
            'minggu malam' => ['2026-10-04 23:30:00'],
            'rabu malam' => ['2026-10-07 23:30:00'],
        ];
    }

    #[DataProvider('seedingMoments')]
    public function test_the_seeder_completes_at_any_seeding_moment_with_the_core_invariants(string $moment): void
    {
        $this->travelTo($moment);

        $this->seed(DatabaseSeeder::class);

        $count = Appointment::query()->count();
        $this->assertGreaterThanOrEqual(25, $count, $moment);
        $this->assertLessThanOrEqual(29, $count, $moment);
        $this->assertSame(6, PatientProfile::query()->where('registration_channel', BookingChannel::Loket->value)->count(), $moment);
        $this->assertSame(1, PracticeSession::query()->where('status', SessionStatus::NeedsReview->value)->count(), $moment);
        $this->assertSame(2, Appointment::query()->where('status', AppointmentStatus::NoShow->value)->count(), $moment);
        $this->assertSame($moment, now()->format('Y-m-d H:i:s'), 'Jam beku tes dipulihkan seeder.');
    }

    public function test_the_seeder_skips_when_appointments_already_exist(): void
    {
        Appointment::factory()->create();
        $profiles = PatientProfile::query()->count();

        $this->seed(AppointmentSeeder::class);

        $this->assertSame($profiles, PatientProfile::query()->count(), 'Penjaga berhenti sebelum membuat walk-in.');
        $this->assertSame(1, Appointment::query()->count());
    }
}
